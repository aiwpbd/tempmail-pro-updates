<?php
/**
 * TempMail Pro — IMAP / POP3 Poller
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class TempMail_IMAP {

    public static function poll() : array {
        $settings = get_option('tmpmp_settings', []);
        $host     = $settings['imap_host']     ?? '';
        $port     = intval($settings['imap_port'] ?? 993);
        $user     = $settings['imap_user']     ?? '';
        $pass     = $settings['imap_pass']     ?? '';
        $protocol = $settings['imap_protocol'] ?? 'imap';

        if ( ! $host || ! $user || ! $pass ) {
            return ['error' => 'IMAP not configured.', 'stored' => 0, 'fetched' => 0];
        }

        // Use native imap extension only when imap_open() actually exists.
        // On some hosts (e.g. aaPanel PHP 8.3) extension_loaded('imap') returns
        // TRUE but the functions are not compiled in — always check function_exists.
        $result = function_exists('imap_open')
            ? self::poll_native($host, $port, $user, $pass, $protocol)
            : self::poll_socket($host, $port, $user, $pass, $protocol);

        update_option('tmpmp_last_imap_poll', array_merge($result, ['time' => gmdate('c')]));
        return $result;
    }

    // ── Native IMAP extension ─────────────────────────────────────────────────
    private static function poll_native(string $host, int $port, string $user, string $pass, string $protocol) : array {
        @set_time_limit(120);
        try {

        $flags   = $protocol === 'pop3' ? '/pop3/ssl' : '/imap/ssl';
        $base    = '{' . $host . ':' . $port . $flags . '/novalidate-cert}';

        $conn = @imap_open($base . 'INBOX', $user, $pass, OP_SILENT, 1);
        if ( ! $conn ) {
            return ['error' => 'IMAP connection failed: ' . imap_last_error(), 'stored' => 0, 'fetched' => 0];
        }

        $active  = self::get_active_addresses();
        $since3  = date('d-M-Y', strtotime('-3 days'));
        $is_gmail = ( stripos($host, 'gmail') !== false || stripos($host, 'google') !== false );

        // Build active domain list for targeted search
        $active_domains = array_unique(array_map(function($a) {
            $parts = explode('@', $a);
            return strtolower($parts[1] ?? '');
        }, array_keys($active)));
        $active_domains = array_filter($active_domains);

        $all_uids  = [];
        $seen_keys = [];
        $debug     = [
            'active_addresses' => array_keys($active),
            'active_domains'   => array_values($active_domains),
            'since'            => $since3,
            'folders'          => [],
            'msgs'             => [],
        ];

        // ── STAGE 1: Domain-targeted search — ALL IMAP servers, highest priority ──
        // Finds emails TO @apkfifa.com (or whatever active domains exist) directly.
        // This guarantees temp-address emails are never crowded out by other mail.
        $domain_priority_uids = [];
        foreach ( $active_domains as $domain ) {
            $q     = "SINCE \"{$since3}\" TO \"{$domain}\"";
            $found = @imap_search($conn, $q) ?: [];
            // Also try broader BODY/HEADER search as fallback for some servers
            if ( empty($found) ) {
                $q2    = "SINCE \"{$since3}\"";
                // We'll rely on Stage 2 for the full scan in this case
            }
            foreach ( $found as $uid ) {
                $key = 'INBOX:' . $uid;
                if ( ! isset($seen_keys[$key]) ) {
                    $domain_priority_uids[] = $uid;
                    $seen_keys[$key]        = true;
                }
            }
        }
        $debug['domain_search_found'] = count($domain_priority_uids);
        // Add domain-priority UIDs first (newest first within group)
        rsort($domain_priority_uids);
        foreach ( $domain_priority_uids as $uid ) {
            $all_uids[] = ['folder' => 'INBOX', 'uid' => $uid];
        }

        // ── STAGE 2: UNSEEN + recent INBOX fallback ───────────────────────────────
        $inbox_unseen = @imap_search($conn, 'UNSEEN')                    ?: [];
        $inbox_since  = @imap_search($conn, "SINCE \"{$since3}\"")       ?: [];
        // Merge, sort DESCENDING so newest messages are first (highest seq# = newest)
        $inbox_all = array_unique(array_merge($inbox_unseen, $inbox_since));
        rsort($inbox_all); // newest first — prevents new emails being cut off by the cap
        $debug['folders']['INBOX'] = count($inbox_all);
        foreach ( $inbox_all as $uid ) {
            $key = 'INBOX:' . $uid;
            if ( ! isset($seen_keys[$key]) ) {
                $all_uids[]      = ['folder' => 'INBOX', 'uid' => $uid];
                $seen_keys[$key] = true;
            }
        }

        // ── STAGE 3 (Gmail only): All Mail & Spam folders ─────────────────────────
        if ( $is_gmail && ! empty($active_domains) ) {
            $gmail_extra_folders = ['[Gmail]/All Mail', '[Gmail]/Spam'];
            foreach ( $gmail_extra_folders as $gfolder ) {
                if ( ! @imap_reopen($conn, $base . $gfolder, 0) ) continue;
                $folder_uids = [];
                foreach ( $active_domains as $domain ) {
                    $q = "SINCE \"{$since3}\" TO \"{$domain}\"";
                    $found = @imap_search($conn, $q) ?: [];
                    rsort($found); // newest first
                    foreach ( $found as $uid ) {
                        $key = $gfolder . ':' . $uid;
                        if ( ! isset($seen_keys[$key]) ) {
                            $all_uids[]      = ['folder' => $gfolder, 'uid' => $uid];
                            $seen_keys[$key] = true;
                            $folder_uids[]   = $uid;
                        }
                    }
                }
                $debug['folders'][$gfolder] = count($folder_uids);
            }
            @imap_reopen($conn, $base . 'INBOX', 0);
        }

        // Cap at 50 messages
        $all_uids = array_slice($all_uids, 0, 50);
        $fetched  = count($all_uids);
        $stored   = 0;
        $current_folder = 'INBOX';

        foreach ( $all_uids as $item ) {
            $folder = $item['folder'];
            $uid    = $item['uid'];

            if ( $folder !== $current_folder ) {
                if ( @imap_reopen($conn, $base . $folder, 0) ) {
                    $current_folder = $folder;
                } else {
                    continue;
                }
            }

            $header = imap_headerinfo($conn, $uid);
            if ( ! $header ) continue;

            $to_addr = self::extract_recipient($conn, $uid, $header);
            $msg_debug = [
                'uid'        => $uid,
                'folder'     => $folder,
                'subject'    => isset($header->subject) ? imap_utf8($header->subject) : '',
                'candidates' => $to_addr,
                'matched'    => false,
                'skip_reason'=> '',
            ];

            $matched = null;
            foreach ( $to_addr as $candidate ) {
                $c = strtolower(trim($candidate));
                if ( isset($active[$c]) ) { $matched = $active[$c]; break; }
            }
            if ( ! $matched ) {
                $msg_debug['skip_reason'] = empty($to_addr)
                    ? 'no_candidates_extracted'
                    : 'candidates_not_in_active_list';
                $debug['msgs'][] = $msg_debug;
                continue;
            }
            $msg_debug['matched'] = $matched->address;
            $debug['msgs'][] = $msg_debug;

            $from      = $header->from[0] ?? null;
            $from_addr = $from ? ($from->mailbox . '@' . $from->host) : '';
            $from_name = $from ? ($from->personal ?? '') : '';
            $subject   = isset($header->subject) ? imap_utf8($header->subject) : '(No Subject)';

            $raw_header = imap_fetchheader($conn, $uid);
            $message_id = '';
            if ( preg_match('/^Message-ID:\s*(.+)$/mi', $raw_header, $mid_m) ) {
                $message_id = trim($mid_m[1]);
            }

            $struct = @imap_fetchstructure($conn, $uid);
            $html   = '';
            $text   = '';

            $extract = null;
            $extract = function( $part, $prefix ) use ( $conn, $uid, &$html, &$text, &$extract ) {
                if ( $part->type === 1 && ! empty( $part->parts ) ) {
                    foreach ( $part->parts as $si => $sub ) {
                        $extract( $sub, $prefix . ($si + 1) . '.' );
                    }
                    return;
                }
                $subtype = strtolower( $part->subtype ?? '' );
                if ( $subtype !== 'plain' && $subtype !== 'html' ) return;

                $content = imap_fetchbody( $conn, $uid, rtrim( $prefix, '.' ) );
                if ( $part->encoding == 3 ) $content = base64_decode( $content );
                if ( $part->encoding == 4 ) $content = quoted_printable_decode( $content );

                $charset = '';
                if ( ! empty( $part->parameters ) ) {
                    foreach ( $part->parameters as $p ) {
                        if ( strtolower( $p->attribute ) === 'charset' ) { $charset = $p->value; break; }
                    }
                }
                if ( $charset && strtolower( $charset ) !== 'utf-8' ) {
                    $conv = @iconv( $charset, 'UTF-8//TRANSLIT//IGNORE', $content );
                    if ( $conv !== false ) $content = $conv;
                }

                if ( $subtype === 'html' && ! $html ) $html = $content;
                if ( $subtype === 'plain' && ! $text ) $text = $content;
            };

            if ( ! $struct ) {
                $text = imap_fetchbody( $conn, $uid, '1' );
            } elseif ( $struct->type === 1 && ! empty( $struct->parts ) ) {
                foreach ( $struct->parts as $pi => $part ) {
                    $extract( $part, ($pi + 1) . '.' );
                }
            } else {
                $content = imap_fetchbody( $conn, $uid, '1' );
                if ( $struct->encoding == 3 ) $content = base64_decode( $content );
                if ( $struct->encoding == 4 ) $content = quoted_printable_decode( $content );
                $subtype = strtolower( $struct->subtype ?? '' );
                if ( $subtype === 'html' ) $html = $content;
                else $text = $content;
            }

            $result = TempMail_Inbox::receive_email([
                'to'         => $matched->address,
                'from'       => $from_addr,
                'from_name'  => $from_name,
                'subject'    => $subject,
                'body_text'  => $text,
                'body_html'  => $html,
                'message_id' => $message_id,
            ]);
            if ( ! is_wp_error($result) ) {
                imap_setflag_full($conn, (string)$uid, '\\Seen');
                $stored++;
            }
        }

        imap_close($conn);
        $result = ['fetched' => $fetched, 'stored' => $stored, 'debug' => $debug];
        update_option('tmpmp_last_imap_debug', array_merge($result, ['time' => gmdate('c')]));
        return $result;

        } catch ( \Throwable $e ) {
            return ['error' => 'Exception: ' . $e->getMessage(), 'stored' => 0, 'fetched' => 0, 'debug' => []];
        }
    }

    // ── Socket fallback (no imap_open available) ──────────────────────────────
    private static function poll_socket(string $host, int $port, string $user, string $pass, string $protocol) : array {
        return $protocol === 'pop3'
            ? self::poll_socket_pop3($host, $port, $user, $pass)
            : self::poll_socket_imap($host, $port, $user, $pass);
    }

    // ── IMAP socket poll ──────────────────────────────────────────────────────
    private static function poll_socket_imap(string $host, int $port, string $user, string $pass) : array {
        $ctx = stream_context_create([
            'ssl' => ['verify_peer' => false, 'verify_peer_name' => false],
        ]);
        $use_ssl = ($port === 993);
        $uri  = ($use_ssl ? 'ssl' : 'tcp') . "://{$host}:{$port}";
        $sock = @stream_socket_client($uri, $errno, $errstr, 15, STREAM_CLIENT_CONNECT, $ctx);
        if ( ! $sock ) {
            return ['error' => "Cannot connect to {$host}:{$port} — {$errstr}", 'fetched' => 0, 'stored' => 0];
        }
        stream_set_timeout($sock, 20);

        // Read server greeting
        $greeting = fgets($sock, 2048);
        if ( ! $greeting || stripos($greeting, '* OK') === false ) {
            fclose($sock);
            return ['error' => 'No IMAP greeting from server.', 'fetched' => 0, 'stored' => 0];
        }

        // LOGIN
        $user_q = str_replace(['"', '\\'], ['\\"', '\\\\'], $user);
        $pass_q = str_replace(['"', '\\'], ['\\"', '\\\\'], $pass);
        fwrite($sock, "A001 LOGIN \"{$user_q}\" \"{$pass_q}\"\r\n");
        $resp = self::imap_sock_read($sock, 'A001');
        if ( stripos($resp, 'A001 OK') === false ) {
            fclose($sock);
            return ['error' => 'IMAP LOGIN failed: ' . trim($resp), 'fetched' => 0, 'stored' => 0];
        }

        // SELECT INBOX
        fwrite($sock, "A002 SELECT INBOX\r\n");
        $resp = self::imap_sock_read($sock, 'A002');
        if ( stripos($resp, 'A002 OK') === false ) {
            fclose($sock);
            return ['error' => 'IMAP SELECT INBOX failed.', 'fetched' => 0, 'stored' => 0];
        }

        $active = self::get_active_addresses();
        $since  = strtoupper(date('d-M-Y', strtotime('-3 days')));
        $tag    = 10;
        $seen   = [];
        $uids   = [];

        // ── Phase 1: Domain-targeted search (high priority) ──────────────────
        // Finds emails specifically addressed to our temp-mail domain(s).
        $active_domains = array_unique(array_filter(array_map(
            function($a) { $p = explode('@', $a); return strtolower($p[1] ?? ''); },
            array_keys($active)
        )));
        foreach ( $active_domains as $dom ) {
            $tag++;
            $t = 'S' . str_pad($tag, 3, '0', STR_PAD_LEFT);
            fwrite($sock, "{$t} SEARCH SINCE {$since} TO \"{$dom}\"\r\n");
            $resp2 = self::imap_sock_read($sock, $t);
            if ( preg_match('/\* SEARCH([\d\s]*)/i', $resp2, $sm) ) {
                foreach ( array_filter(array_map('intval', explode(' ', trim($sm[1])))) as $seq ) {
                    if ( ! isset($seen[$seq]) ) { $uids[] = $seq; $seen[$seq] = true; }
                }
            }
        }
        // Sort domain hits newest-first
        rsort($uids);

        // ── Phase 2: UNSEEN SINCE fallback (fills remaining capacity) ─────────
        $tag++;
        $tu = 'S' . str_pad($tag, 3, '0', STR_PAD_LEFT);
        fwrite($sock, "{$tu} SEARCH UNSEEN SINCE {$since}\r\n");
        $resp3 = self::imap_sock_read($sock, $tu);
        $unseen = [];
        if ( preg_match('/\* SEARCH([\d\s]*)/i', $resp3, $um) ) {
            $unseen = array_filter(array_map('intval', explode(' ', trim($um[1]))));
        }
        rsort($unseen); // newest first
        foreach ( $unseen as $seq ) {
            if ( ! isset($seen[$seq]) ) { $uids[] = $seq; $seen[$seq] = true; }
        }

        $uids    = array_slice($uids, 0, 50); // cap at 50 total, newest first
        $fetched = count($uids);
        $stored  = 0;

        // ── Debug log ─────────────────────────────────────────────────────────
        $sock_debug = [
            'method'           => 'poll_socket_imap',
            'active_addresses' => array_keys($active),
            'active_domains'   => array_values($active_domains),
            'domain_search_found' => count(array_keys($seen)) - count($unseen),
            'fetched'          => $fetched,
            'uids'             => $uids,
            'msgs'             => [],
        ];

        foreach ( $uids as $seq ) {
            $tag++;
            $t = 'B' . str_pad($tag, 3, '0', STR_PAD_LEFT);
            $msg_dbg = ['seq' => $seq, 'skip_reason' => '', 'to_found' => '', 'matched' => false, 'subject' => ''];

            // ── Fetch full raw message (RFC822 is supported by ALL IMAP servers)
            fwrite($sock, "{$t} FETCH {$seq} RFC822\r\n");
            $raw = self::imap_sock_read($sock, $t);

            // Extract full message from RFC822 literal: * N FETCH (RFC822 {SIZE}\r\n<data>)
            $full_msg = '';
            if ( preg_match('/RFC822\s+\{(\d+)\}/i', $raw, $hm) ) {
                $mlen  = intval($hm[1]);
                $mpos  = strpos($raw, $hm[0]) + strlen($hm[0]);
                // Skip \r\n or \n after the literal size marker
                $nl = substr($raw, $mpos, 2);
                $mpos += ($nl === "\r\n") ? 2 : 1;
                $full_msg = substr($raw, $mpos, $mlen);
            } elseif ( preg_match('/RFC822\s+"([^"]+)"/i', $raw, $hm) ) {
                // Some servers return small messages as quoted strings (not literals)
                $full_msg = stripcslashes($hm[1]);
            }

            if ( ! $full_msg ) {
                $msg_dbg['skip_reason'] = 'rfc822_empty — raw_preview='
                    . substr(str_replace(["\r", "\n"], ['↵', '↵'], $raw), 0, 250);
                $sock_debug['msgs'][] = $msg_dbg;
                continue;
            }

            // Split full message into header block and body at the first blank line
            $blank = strpos($full_msg, "\r\n\r\n");
            if ( $blank !== false ) {
                $header_block = substr($full_msg, 0, $blank);
                $body_block   = substr($full_msg, $blank + 4);
            } else {
                // Fallback: try \n\n (some servers use Unix line endings)
                $blank = strpos($full_msg, "\n\n");
                $header_block = ($blank !== false) ? substr($full_msg, 0, $blank) : $full_msg;
                $body_block   = ($blank !== false) ? substr($full_msg, $blank + 2) : '';
            }

            // Subject for debug
            if ( preg_match('/^Subject:\s*(.+)/mi', $header_block, $sm) ) {
                $msg_dbg['subject'] = self::decode_mime_header(trim($sm[1]));
            }

            // ── Parse To: address ────────────────────────────────────────────
            $to        = '';
            $all_found = [];
            foreach ( ['Delivered-To','X-Original-To','X-Forwarded-To','X-Rcpt-To','Envelope-To','To'] as $hdr ) {
                // .*? (zero-or-more non-greedy) lets bare addresses match from position 0
                $pat = '/^' . preg_quote($hdr, '/') . ':\s*.*?([a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+)/mi';
                if ( preg_match($pat, $header_block, $m) ) {
                    $candidate = strtolower(trim($m[1]));
                    $all_found[$hdr] = $candidate;
                    if ( isset($active[$candidate]) ) { $to = $candidate; break; }
                    if ( ! $to ) $to = $candidate;
                }
            }
            $msg_dbg['to_found']      = $to;
            $msg_dbg['all_hdr_addrs'] = $all_found;

            if ( ! $to || ! isset($active[$to]) ) {
                $msg_dbg['skip_reason'] = empty($all_found)
                    ? 'no_email_extracted_from_headers'
                    : 'address_not_in_active_list';
                $sock_debug['msgs'][] = $msg_dbg;
                continue;
            }
            $msg_dbg['matched'] = $to;

            // ── Parse From ───────────────────────────────────────────────────
            $from = '';
            if ( preg_match('/^From:\s*.*?([a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+)/mi', $header_block, $m) ) {
                $from = trim($m[1]);
            }
            $from_name = '';
            if ( preg_match('/^From:\s*"?([^"<\r\n]+?)"?\s*</mi', $header_block, $m) ) {
                $from_name = trim($m[1]);
            }

            // ── Parse Subject ─────────────────────────────────────────────────
            $subject = $msg_dbg['subject'] ?: '(No Subject)';

            // ── Parse Message-ID (deduplication) ─────────────────────────────
            $message_id = '';
            if ( preg_match('/^Message-ID:\s*(.+)/mi', $header_block, $m) ) {
                $message_id = trim($m[1]);
            }

            // ── Decode body — handles multipart/alternative, multipart/mixed, etc. ──
            $mime      = self::parse_mime_parts($header_block, $body_block);
            $body_html = $mime['html'];
            $body_text = $mime['text'];

            // ── Store email ───────────────────────────────────────────────────
            $result = TempMail_Inbox::receive_email([
                'to'         => $to,
                'from'       => $from,
                'from_name'  => $from_name,
                'subject'    => $subject,
                'body_text'  => $body_text,
                'body_html'  => $body_html,
                'message_id' => $message_id,
            ]);

            if ( ! is_wp_error($result) ) {
                $tag++;
                $ts = 'B' . str_pad($tag, 3, '0', STR_PAD_LEFT);
                fwrite($sock, "{$ts} STORE {$seq} +FLAGS (\\Seen)\r\n");
                self::imap_sock_read($sock, $ts);
                $stored++;
                $msg_dbg['stored'] = true;
            } else {
                $msg_dbg['skip_reason'] = 'receive_email_error: ' . $result->get_error_message();
            }
            $sock_debug['msgs'][] = $msg_dbg;
        }

        // LOGOUT
        $tag++;
        $tl = 'B' . str_pad($tag, 3, '0', STR_PAD_LEFT);
        fwrite($sock, "{$tl} LOGOUT\r\n");
        fclose($sock);

        $sock_debug['stored'] = $stored;
        update_option('tmpmp_last_imap_debug', array_merge($sock_debug, ['time' => gmdate('c')]));

        return ['fetched' => $fetched, 'stored' => $stored];
    }

    /**
     * Recursively parse MIME parts from raw header + body blocks.
     * Handles multipart/alternative, multipart/mixed, and simple text/html or text/plain.
     * Returns ['html' => '...', 'text' => '...']
     */
    private static function parse_mime_parts(string $headers, string $body) : array {
        $result = ['html' => '', 'text' => ''];

        // Get content-type (just the type/subtype, not the parameters)
        $ct = '';
        if ( preg_match('/^Content-Type:\s*([^\r\n;]+)/mi', $headers, $m) ) {
            $ct = strtolower(trim($m[1]));
        }

        // Get encoding
        $enc = '';
        if ( preg_match('/^Content-Transfer-Encoding:\s*(\S+)/mi', $headers, $m) ) {
            $enc = strtolower(trim($m[1]));
        }

        if ( strpos($ct, 'multipart/') !== false ) {
            // Extract boundary — search the full header block (handles folded headers)
            $boundary = '';
            if ( preg_match('/boundary\s*=\s*"?([^"\r\n;]+)"?/mi', $headers, $mb) ) {
                $boundary = trim($mb[1]);
            }
            if ( ! $boundary ) return $result;

            // Split body by boundary delimiter — prepend \r\n so first part splits correctly
            $delimiter = "\r\n--" . $boundary;
            $raw_parts = explode($delimiter, "\r\n" . $body);
            array_shift($raw_parts); // discard preamble

            foreach ( $raw_parts as $raw_part ) {
                if ( substr(ltrim($raw_part, "\r\n"), 0, 2) === '--' ) continue; // final boundary
                $raw_part = ltrim($raw_part, "\r\n"); // strip leading CRLF

                // Split part into its own headers and body
                $sep = strpos($raw_part, "\r\n\r\n");
                if ( $sep === false ) {
                    $sep = strpos($raw_part, "\n\n");
                    if ( $sep === false ) continue;
                    $part_hdrs = substr($raw_part, 0, $sep);
                    $part_body = substr($raw_part, $sep + 2);
                } else {
                    $part_hdrs = substr($raw_part, 0, $sep);
                    $part_body = substr($raw_part, $sep + 4);
                }

                // Strip trailing boundary dash-line from last part body
                $part_body = rtrim($part_body, "\r\n");

                $sub = self::parse_mime_parts($part_hdrs, $part_body);
                if ( $sub['html'] && ! $result['html'] ) $result['html'] = $sub['html'];
                if ( $sub['text'] && ! $result['text'] ) $result['text'] = $sub['text'];
            }

        } elseif ( strpos($ct, 'text/html') !== false ) {
            $decoded = $body;
            if ( $enc === 'base64' )            $decoded = base64_decode(preg_replace('/\s+/', '', $body));
            elseif ( $enc === 'quoted-printable' ) $decoded = quoted_printable_decode($body);
            $result['html'] = $decoded;

        } else {
            // text/plain or unknown — treat as plain text
            $decoded = $body;
            if ( $enc === 'base64' )            $decoded = base64_decode(preg_replace('/\s+/', '', $body));
            elseif ( $enc === 'quoted-printable' ) $decoded = quoted_printable_decode($body);
            $result['text'] = $decoded;
        }

        return $result;
    }

    /**
     * Read IMAP response lines until we see one starting with the given tag.
     * Handles IMAP literal strings {N} by reading exactly N more bytes.
     */
    private static function imap_sock_read($sock, string $tag, int $max_bytes = 2097152) : string {
        $buf      = '';
        $deadline = time() + 15;

        while ( ! feof($sock) && time() < $deadline && strlen($buf) < $max_bytes ) {
            $line = fgets($sock, 8192);
            if ( $line === false ) break;
            $buf .= $line;

            // Handle IMAP literal: {N}\r\n → read exactly N bytes
            if ( preg_match('/\{(\d+)\}\r?\n$/', $line, $lm) ) {
                $n       = intval($lm[1]);
                $literal = '';
                while ( strlen($literal) < $n && ! feof($sock) && time() < $deadline ) {
                    $chunk = fread($sock, $n - strlen($literal));
                    if ( $chunk === false || $chunk === '' ) break;
                    $literal .= $chunk;
                }
                $buf .= $literal;
                continue;
            }

            // Check if this line starts with our tag (response complete)
            if ( preg_match('/^' . preg_quote($tag, '/') . '\s+(OK|NO|BAD)/i', $line) ) {
                break;
            }
        }
        return $buf;
    }

    // ── POP3 socket poll (legacy fallback) ────────────────────────────────────
    private static function poll_socket_pop3(string $host, int $port, string $user, string $pass) : array {
        $ctx  = stream_context_create(['ssl' => ['verify_peer' => false, 'verify_peer_name' => false]]);
        $sock = @stream_socket_client("ssl://{$host}:{$port}", $errno, $errstr, 10, STREAM_CLIENT_CONNECT, $ctx);
        if ( ! $sock ) return ['error' => "Socket failed: $errstr", 'fetched' => 0, 'stored' => 0];

        stream_set_timeout($sock, 10);
        self::sock_read($sock); // greeting
        self::sock_send($sock, "USER $user");
        self::sock_send($sock, "PASS $pass");
        $stat = self::sock_cmd($sock, 'STAT');
        preg_match('/\+OK (\d+)/', $stat, $m);
        $count  = intval($m[1] ?? 0);
        $stored = 0;
        $active = self::get_active_addresses();

        for ( $i = 1; $i <= min($count, 50); $i++ ) {
            self::sock_send($sock, "RETR $i");
            $raw = '';
            while ( ($line = fgets($sock, 4096)) !== false ) {
                if ( rtrim($line) === '.' ) break;
                $raw .= $line;
            }
            $parsed = self::parse_raw_email($raw, $active);
            if ( $parsed ) {
                $result = TempMail_Inbox::receive_email($parsed);
                if ( ! is_wp_error($result) ) {
                    self::sock_cmd($sock, "DELE $i");
                    $stored++;
                }
            }
        }
        self::sock_cmd($sock, 'QUIT');
        fclose($sock);
        return ['fetched' => $count, 'stored' => $stored];
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private static function get_active_addresses() : array {
        global $wpdb;
        $rows = $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}tmpmp_addresses WHERE expires_at > UTC_TIMESTAMP()"
        );
        $map = [];
        foreach ( $rows as $row ) {
            $map[strtolower($row->address)] = $row;
        }
        return $map;
    }

    private static function extract_recipient($conn, int $uid, $header) : array {
        if ( ! $header ) return [];
        $candidates = [];

        foreach (['to','cc','reply_to'] as $field) {
            foreach ((array)($header->$field ?? []) as $addr) {
                if ( isset($addr->mailbox, $addr->host) && $addr->host !== 'localhost' ) {
                    $candidates[] = strtolower($addr->mailbox . '@' . $addr->host);
                }
            }
        }

        $raw = imap_fetchheader($conn, $uid);
        $delivery_headers = [
            'Delivered-To', 'X-Original-To', 'X-Forwarded-To',
            'X-Google-Original-To', 'Envelope-To',
            'X-ImprovMX-Delivered-To', 'X-Rcpt-To',
        ];
        foreach ( $delivery_headers as $hdr ) {
            if ( preg_match('/^' . preg_quote($hdr, '/') . ':\s*<?([^\s>,]+@[^\s>,]+)>?/mi', $raw, $m) ) {
                $candidates[] = strtolower(trim($m[1]));
            }
        }

        preg_match_all('/^Received:.*?for\s+<?([a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,})>?/mi', $raw, $recv_matches);
        foreach ( $recv_matches[1] as $addr ) {
            $candidates[] = strtolower(trim($addr));
        }

        return array_values(array_unique($candidates));
    }

    private static function decode_mime_header(string $str) : string {
        // Handle =?charset?encoding?text?= encoded words
        return preg_replace_callback('/=\?([^?]+)\?([BbQq])\?([^?]*)\?=/', function($m) {
            $charset  = $m[1];
            $encoding = strtoupper($m[2]);
            $text     = $m[3];
            if ( $encoding === 'B' ) $text = base64_decode($text);
            elseif ( $encoding === 'Q' ) $text = quoted_printable_decode(str_replace('_', ' ', $text));
            if ( strtolower($charset) !== 'utf-8' ) {
                $conv = @iconv($charset, 'UTF-8//TRANSLIT//IGNORE', $text);
                if ( $conv !== false ) $text = $conv;
            }
            return $text;
        }, $str);
    }

    private static function parse_raw_email(string $raw, array $active) : ?array {
        $lines   = explode("\n", $raw);
        $headers = [];
        $body    = '';
        $in_body = false;
        foreach ( $lines as $line ) {
            if ( $in_body ) { $body .= $line . "\n"; continue; }
            if ( trim($line) === '' ) { $in_body = true; continue; }
            if ( preg_match('/^([\w-]+):\s*(.+)/', $line, $m) ) {
                $headers[strtolower($m[1])] = trim($m[2]);
            }
        }
        $to = $headers['delivered-to'] ?? $headers['x-original-to'] ?? $headers['to'] ?? '';
        $to = strtolower(trim($to));
        // Strip angle brackets if present
        $to = preg_replace('/.*<([^>]+)>.*/', '$1', $to);
        if ( ! isset($active[$to]) ) return null;
        return [
            'to'        => $to,
            'from'      => $headers['from'] ?? '',
            'subject'   => $headers['subject'] ?? '(No Subject)',
            'body_text' => $body,
            'body_html' => '',
        ];
    }

    private static function sock_send($sock, string $cmd) : string {
        fwrite($sock, $cmd . "\r\n");
        return self::sock_read($sock);
    }

    private static function sock_cmd($sock, string $cmd) : string {
        return self::sock_send($sock, $cmd);
    }

    private static function sock_read($sock) : string {
        return fgets($sock, 4096) ?: '';
    }
}
