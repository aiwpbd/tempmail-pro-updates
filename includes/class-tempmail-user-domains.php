<?php
/**
 * TempMail Pro — User Custom Domains (DNS Verification Wizard)
 * Handles per-user custom domain registration, DKIM keypair generation,
 * DNS record requirements display, and multi-record verification.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class TempMail_UserDomains {

    private static ?self $instance = null;
    public static function instance() : self { return self::$instance ??= new self(); }
    private function __construct() {}

    // ── Helpers ───────────────────────────────────────────────────────────────

    /** Retrieve admin settings with sensible defaults. */
    private static function settings() : array {
        $s   = get_option( 'tmpmp_settings', [] );
        $host = parse_url( home_url(), PHP_URL_HOST ) ?: 'mail.example.com';
        return [
            'mx_host'      => ! empty( $s['custom_domain_mx_host'] )    ? $s['custom_domain_mx_host']    : 'mail.' . $host,
            'spf_include'  => ! empty( $s['custom_domain_spf_include'] ) ? $s['custom_domain_spf_include'] : $host,
            'max_per_user' => (int) ( $s['custom_domain_max_per_user'] ?? 3 ),
        ];
    }

    /** Generate a unique ownership token. */
    private static function generate_token() : string {
        return 'tmpro-' . wp_generate_password( 32, false );
    }

    /**
     * Generate a 2048-bit RSA DKIM keypair via OpenSSL.
     * Returns [ 'private' => PEM, 'public_dns' => base64 for DNS TXT ].
     */
    public static function generate_dkim_keypair() : array {
        if ( ! extension_loaded( 'openssl' ) ) {
            return [ 'private' => '', 'public_dns' => '' ];
        }

        // Windows: openssl_pkey_new() fails if OPENSSL_CONF is not set.
        // Find or create a minimal config so key generation succeeds.
        if ( DIRECTORY_SEPARATOR === '\\' && ! getenv( 'OPENSSL_CONF' ) ) {
            $conf_path = self::resolve_openssl_conf();
            if ( $conf_path ) {
                putenv( "OPENSSL_CONF={$conf_path}" );
            }
        }

        $config = [
            'digest_alg'       => 'sha256',
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ];
        // Pass explicit config path if available
        $conf = getenv( 'OPENSSL_CONF' );
        if ( $conf && @file_exists( $conf ) ) {
            $config['config'] = $conf;
        }

        try {
            $res = @openssl_pkey_new( $config );
        } catch ( \Throwable $e ) {
            return [ 'private' => '', 'public_dns' => '' ];
        }
        if ( ! $res ) return [ 'private' => '', 'public_dns' => '' ];

        openssl_pkey_export( $res, $private_pem, null,
            $conf && @file_exists( $conf ) ? [ 'config' => $conf ] : []
        );
        $details    = openssl_pkey_get_details( $res );
        $public_der = $details['key'] ?? '';

        // Strip PEM headers, collapse to single base64 string
        $public_b64 = preg_replace( '/-----[^-]+-----|\s/', '', $public_der );

        return [ 'private' => $private_pem, 'public_dns' => $public_b64 ];
    }

    /**
     * Locate or create a minimal openssl.cnf for Windows environments
     * where OPENSSL_CONF is not set (e.g. LocalWP, WAMP, XAMPP).
     */
    private static function resolve_openssl_conf() : string {
        // PHP_BINARY is the exact executable path (php-fpm.exe, php.exe, etc.)
        // dirname(PHP_BINARY) is always the correct directory, unlike PHP_BINDIR
        // which may differ between SAPI modes.
        $php_dir = defined( 'PHP_BINARY' ) ? dirname( PHP_BINARY ) : PHP_BINDIR;

        $candidates = [
            // 1. Already set in environment (e.g. injected via php.ini OPENSSL_CONF=)
            getenv( 'OPENSSL_CONF' ) ?: '',
            // 2. LocalWP / standard PHP-for-Windows bundle (extras/ssl/ subfolder)
            $php_dir   . '/extras/ssl/openssl.cnf',
            PHP_BINDIR . '/extras/ssl/openssl.cnf',
            // 3. Sibling ssl directory
            $php_dir   . '/../ssl/openssl.cnf',
            PHP_BINDIR . '/../ssl/openssl.cnf',
            $php_dir   . '/ssl/openssl.cnf',
            PHP_BINDIR . '/ssl/openssl.cnf',
            // 4. Stand-alone OpenSSL installs on Windows
            'C:/Program Files/OpenSSL-Win64/bin/openssl.cfg',
            'C:/Program Files (x86)/OpenSSL-Win32/bin/openssl.cfg',
            'C:/OpenSSL-Win64/bin/openssl.cfg',
            // 5. WAMP / XAMPP
            'C:/xampp/apache/conf/openssl.cnf',
            'C:/wamp64/bin/apache/apache2.4.54/conf/openssl.cnf',
        ];
        foreach ( $candidates as $p ) {
            if ( $p && @file_exists( $p ) ) return $p;
        }

        // Last resort: create a minimal config in the uploads dir
        if ( function_exists( 'wp_upload_dir' ) ) {
            $upload = wp_upload_dir( null, false );
            $dir    = trailingslashit( $upload['basedir'] ) . 'tmpmpro-ssl';
            @wp_mkdir_p( $dir );
            $path = $dir . '/openssl.cnf';
            if ( ! @file_exists( $path ) ) {
                @file_put_contents( $path,
                    "[req]\ndistinguished_name = req_distinguished_name\n" .
                    "[req_distinguished_name]\n[v3_req]\n[v3_ca]\n"
                );
            }
            if ( @file_exists( $path ) ) return $path;
        }

        return '';
    }

    // ── CRUD ──────────────────────────────────────────────────────────────────

    /**
     * Add a custom domain for a user.
     * Returns WP_Error on failure, domain row ID on success.
     */
    public static function add( int $user_id, string $raw_domain ) : int|WP_Error {
        global $wpdb;

        $domain = strtolower( trim( $raw_domain ) );
        $domain = preg_replace( '#^https?://#', '', $domain );
        $domain = rtrim( $domain, '/' );

        // Basic domain validation
        if ( ! preg_match( '/^[a-z0-9]([a-z0-9\-]{0,61}[a-z0-9])?(\.[a-z0-9]([a-z0-9\-]{0,61}[a-z0-9])?)+$/i', $domain ) ) {
            return new WP_Error( 'invalid_domain', __( 'Invalid domain format. Please enter a valid domain (e.g. mail.mycompany.com).', 'tempmail-pro' ) );
        }

        // Check limit
        $cfg   = self::settings();
        $count = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}tmpmp_user_domains WHERE user_id = %d",
            $user_id
        ) );
        if ( $count >= $cfg['max_per_user'] ) {
            return new WP_Error( 'limit_reached', sprintf(
                /* translators: %d = max domains allowed */
                __( 'You can add a maximum of %d custom domain(s) on your current plan.', 'tempmail-pro' ),
                $cfg['max_per_user']
            ) );
        }

        // Unique per user
        $existing = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}tmpmp_user_domains WHERE user_id = %d AND domain = %s",
            $user_id, $domain
        ) );
        if ( $existing ) {
            return new WP_Error( 'duplicate', __( 'You have already added this domain.', 'tempmail-pro' ) );
        }

        // Generate ownership token — DKIM keypair is deferred to first verify()
        // call so this AJAX response is instant (openssl_pkey_new can block for
        // 30-60 s on low-entropy or misconfigured servers).
        $token = self::generate_token();

        $wpdb->insert( $wpdb->prefix . 'tmpmp_user_domains', [
            'user_id'          => $user_id,
            'domain'           => $domain,
            'status'           => 'pending',
            'verify_token'     => $token,
            'dkim_selector'    => 'tmpro',
            'dkim_private_key' => '',   // generated on first verify
            'dkim_public_key'  => '',   // generated on first verify
            'created_at'       => gmdate( 'Y-m-d H:i:s' ),
        ] );

        if ( ! $wpdb->insert_id ) {
            return new WP_Error( 'db_error', __( 'Could not add domain. Please try again.', 'tempmail-pro' ) );
        }

        return (int) $wpdb->insert_id;
    }

    /** Get all domains for a user. */
    public static function get_for_user( int $user_id ) : array {
        global $wpdb;
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}tmpmp_user_domains WHERE user_id = %d ORDER BY created_at DESC",
            $user_id
        ) ) ?: [];
    }

    /** Get a single domain row (with ownership check). */
    public static function get( int $id, int $user_id ) : ?object {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}tmpmp_user_domains WHERE id = %d AND user_id = %d",
            $id, $user_id
        ) ) ?: null;
    }

    /** Delete a domain (with ownership check). */
    public static function delete( int $id, int $user_id ) : bool {
        global $wpdb;
        return (bool) $wpdb->delete(
            $wpdb->prefix . 'tmpmp_user_domains',
            [ 'id' => $id, 'user_id' => $user_id ],
            [ '%d', '%d' ]
        );
    }

    // ── DNS Record Requirements ────────────────────────────────────────────────

    /**
     * Return the 5 DNS records a user must set for their domain.
     * Each record: [ type, host, value, label, description ]
     */
    public static function get_required_records( object $row ) : array {
        $cfg      = self::settings();
        $domain   = $row->domain;
        $token    = $row->verify_token;
        $selector = $row->dkim_selector ?: 'tmpro';
        $pub_key  = $row->dkim_public_key;
        $mx_host  = $cfg['mx_host'];
        $spf_inc  = $cfg['spf_include'];

        $records = [
            [
                'id'          => 'txt',
                'label'       => __( 'TXT Verification', 'tempmail-pro' ),
                'description' => __( 'Proves you own this domain.', 'tempmail-pro' ),
                'type'        => 'TXT',
                'host'        => $domain,
                'value'       => $token,
                'priority'    => '',
                'verified'    => (bool) $row->txt_verified,
            ],
            [
                'id'          => 'mx',
                'label'       => __( 'MX Record', 'tempmail-pro' ),
                'description' => __( 'Routes incoming email to our mail server.', 'tempmail-pro' ),
                'type'        => 'MX',
                'host'        => $domain,
                'value'       => $mx_host,
                'priority'    => '10',
                'verified'    => (bool) $row->mx_verified,
            ],
            [
                'id'          => 'spf',
                'label'       => __( 'SPF Record', 'tempmail-pro' ),
                'description' => __( 'Authorises our server to send on your behalf.', 'tempmail-pro' ),
                'type'        => 'TXT',
                'host'        => $domain,
                'value'       => "v=spf1 include:{$spf_inc} ~all",
                'priority'    => '',
                'verified'    => (bool) $row->spf_verified,
            ],
            [
                'id'          => 'dkim',
                'label'       => __( 'DKIM Record', 'tempmail-pro' ),
                'description' => __( 'Cryptographically signs outgoing emails.', 'tempmail-pro' ),
                'type'        => 'TXT',
                'host'        => "{$selector}._domainkey.{$domain}",
                'value'       => $pub_key
                    ? "v=DKIM1; k=rsa; p={$pub_key}"
                    : __( '— key not yet generated, click Generate below —', 'tempmail-pro' ),
                'priority'       => '',
                'verified'       => (bool) $row->dkim_verified,
                'dkim_key_missing' => empty( $pub_key ),
                'domain_id'      => (int) $row->id,
            ],
            [
                'id'          => 'dmarc',
                'label'       => __( 'DMARC Record', 'tempmail-pro' ),
                'description' => __( 'Defines policy for unauthenticated emails.', 'tempmail-pro' ),
                'type'        => 'TXT',
                'host'        => "_dmarc.{$domain}",
                'value'       => "v=DMARC1; p=quarantine; rua=mailto:dmarc@{$spf_inc}",
                'priority'    => '',
                'verified'    => (bool) $row->dmarc_verified,
            ],
        ];

        return $records;
    }

    // ── DNS Verification ──────────────────────────────────────────────────────

    /**
     * Run all 5 DNS checks for a domain row.
     * Updates DB flags and returns result array.
     */
    public static function verify( int $id, int $user_id ) : array|WP_Error {
        global $wpdb;

        $row = self::get( $id, $user_id );
        if ( ! $row ) {
            return new WP_Error( 'not_found', __( 'Domain not found.', 'tempmail-pro' ) );
        }

        // ── Lazy DKIM generation ──────────────────────────────────────────────
        if ( empty( $row->dkim_private_key ) ) {
            $dkim = self::generate_dkim_keypair();
            if ( ! empty( $dkim['private'] ) ) {
                $wpdb->update(
                    $wpdb->prefix . 'tmpmp_user_domains',
                    [
                        'dkim_private_key' => $dkim['private'],
                        'dkim_public_key'  => $dkim['public_dns'],
                    ],
                    [ 'id' => $id ],
                    [ '%s', '%s' ],
                    [ '%d' ]
                );
                $row->dkim_private_key = $dkim['private'];
                $row->dkim_public_key  = $dkim['public_dns'];
            }
        }

        $cfg      = self::settings();
        $domain   = $row->domain;
        $selector = $row->dkim_selector ?: 'tmpro';

        // ── Instant cache path ────────────────────────────────────────────────
        // If all 5 records are already verified in the DB and were checked
        // within the last 30 minutes, return the cached result immediately
        // without making any DNS queries.
        $all_db_pass = $row->txt_verified && $row->mx_verified &&
                       $row->spf_verified && $row->dkim_verified && $row->dmarc_verified;
        $checked_at  = $row->last_checked ? strtotime( $row->last_checked ) : 0;
        $age_seconds = time() - $checked_at;

        if ( $all_db_pass && $age_seconds < 1800 ) {
            error_log( '[TmpmpVerify] Instant cache hit for domain=' . $domain . ' age=' . $age_seconds . 's' );
            return [
                'domain'   => $domain,
                'status'   => 'verified',
                'checks'   => [
                    'txt'   => true,
                    'mx'    => true,
                    'spf'   => true,
                    'dkim'  => true,
                    'dmarc' => true,
                ],
                'all_pass' => true,
            ];
        }

        // ── Parallel DNS checks via cURL multi-handle ─────────────────────────
        // Fire all DoH requests simultaneously — worst case 5 s instead of 25 s.
        error_log( '[TmpmpVerify] Running parallel DNS checks for domain=' . $domain );
        $results = self::doh_parallel( [
            'txt'   => 'https://cloudflare-dns.com/dns-query?name=' . urlencode( $domain )                          . '&type=TXT',
            'mx'    => 'https://cloudflare-dns.com/dns-query?name=' . urlencode( $domain )                          . '&type=MX',
            'spf'   => 'https://cloudflare-dns.com/dns-query?name=' . urlencode( $domain )                          . '&type=TXT',
            'dkim'  => 'https://cloudflare-dns.com/dns-query?name=' . urlencode( $selector . '._domainkey.' . $domain ) . '&type=TXT',
            'dmarc' => 'https://cloudflare-dns.com/dns-query?name=' . urlencode( '_dmarc.' . $domain )              . '&type=TXT',
        ] );

        // Parse parallel results
        $txt_ok   = self::parse_txt_ownership( $results['txt'],   $row->verify_token );
        $mx_ok    = self::parse_mx(            $results['mx'],    $cfg['mx_host'] );
        $spf_ok   = self::parse_spf(           $results['spf'],   $cfg['spf_include'] );
        $dkim_ok  = self::parse_dkim(          $results['dkim'],  $row->dkim_public_key );
        $dmarc_ok = self::parse_dmarc(         $results['dmarc'] );

        error_log( '[TmpmpVerify] Results: txt=' . (int)$txt_ok . ' mx=' . (int)$mx_ok . ' spf=' . (int)$spf_ok . ' dkim=' . (int)$dkim_ok . ' dmarc=' . (int)$dmarc_ok );

        // Overall status
        $all_pass = $txt_ok && $mx_ok && $spf_ok && $dkim_ok && $dmarc_ok;
        $status   = $all_pass ? 'verified' : 'pending';

        $now = gmdate( 'Y-m-d H:i:s' );
        $wpdb->update(
            $wpdb->prefix . 'tmpmp_user_domains',
            [
                'txt_verified'   => (int) $txt_ok,
                'mx_verified'    => (int) $mx_ok,
                'spf_verified'   => (int) $spf_ok,
                'dkim_verified'  => (int) $dkim_ok,
                'dmarc_verified' => (int) $dmarc_ok,
                'status'         => $status,
                'last_checked'   => $now,
                'verified_at'    => $all_pass ? $now : null,
            ],
            [ 'id' => $id ]
        );

        return [
            'domain'   => $domain,
            'status'   => $status,
            'checks'   => [
                'txt'   => $txt_ok,
                'mx'    => $mx_ok,
                'spf'   => $spf_ok,
                'dkim'  => $dkim_ok,
                'dmarc' => $dmarc_ok,
            ],
            'all_pass' => $all_pass,
        ];
    }

    /** Cron callback — verifies all non-fully-verified domains. */
    public static function verify_all_pending() : void {
        global $wpdb;
        $rows = $wpdb->get_results(
            "SELECT id, user_id FROM {$wpdb->prefix}tmpmp_user_domains WHERE status != 'verified'"
        );
        foreach ( (array) $rows as $r ) {
            self::verify( (int) $r->id, (int) $r->user_id );
        }
    }

    // ── DNS helpers ───────────────────────────────────────────────────────────

    /**
     * Fire multiple DoH requests in parallel using cURL multi-handle.
     * All requests complete in parallel — total time = slowest single request
     * (max 5 s) instead of N × 5 s sequentially.
     *
     * @param  array<string,string> $requests  key => URL
     * @return array<string,array|null>        key => decoded JSON body or null
     */
    private static function doh_parallel( array $requests ) : array {
        if ( ! function_exists( 'curl_multi_init' ) ) {
            // Fallback: sequential
            $out = [];
            foreach ( $requests as $key => $url ) {
                $out[ $key ] = self::doh_get_json( $url );
            }
            return $out;
        }

        $mh      = curl_multi_init();
        $handles = [];
        foreach ( $requests as $key => $url ) {
            $ch = curl_init( $url );
            curl_setopt_array( $ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 5,
                CURLOPT_CONNECTTIMEOUT => 3,
                CURLOPT_HTTPHEADER     => [ 'Accept: application/dns-json' ],
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS      => 2,
                CURLOPT_SSL_VERIFYPEER => true,
            ] );
            curl_multi_add_handle( $mh, $ch );
            $handles[ $key ] = $ch;
        }

        // Execute all handles in parallel
        do {
            $status = curl_multi_exec( $mh, $active );
            if ( $active ) {
                curl_multi_select( $mh, 0.05 );
            }
        } while ( $active && $status === CURLM_OK );

        $out = [];
        foreach ( $handles as $key => $ch ) {
            $body = curl_multi_getcontent( $ch );
            $err  = curl_error( $ch );
            if ( $err ) {
                error_log( '[TmpmpVerify] Parallel DoH error [' . $key . ']: ' . $err );
            }
            $out[ $key ] = ( $body && ! $err ) ? json_decode( $body, true ) : null;
            curl_multi_remove_handle( $mh, $ch );
            curl_close( $ch );
        }
        curl_multi_close( $mh );
        return $out;
    }

    // ── Per-check parsers (operate on already-fetched DoH JSON) ──────────────

    private static function parse_txts( ?array $doh_body ) : array {
        $out = [];
        foreach ( (array) ( $doh_body['Answer'] ?? [] ) as $ans ) {
            if ( (int) ( $ans['type'] ?? 0 ) === 16 ) {
                $out[] = trim( $ans['data'] ?? '', '"' );
            }
        }
        return $out;
    }

    private static function parse_txt_ownership( ?array $doh_body, string $token ) : bool {
        foreach ( self::parse_txts( $doh_body ) as $val ) {
            if ( $val === $token ) return true;
        }
        return false;
    }

    private static function parse_mx( ?array $doh_body, string $expected_host ) : bool {
        if ( ! $expected_host ) return false;
        foreach ( (array) ( $doh_body['Answer'] ?? [] ) as $ans ) {
            if ( (int) ( $ans['type'] ?? 0 ) === 15 ) {
                $parts = explode( ' ', trim( $ans['data'] ?? '' ), 2 );
                $host  = rtrim( $parts[1] ?? '', '.' );
                if ( strcasecmp( $host, rtrim( $expected_host, '.' ) ) === 0 ) return true;
            }
        }
        return false;
    }

    private static function parse_spf( ?array $doh_body, string $spf_include ) : bool {
        if ( ! $spf_include ) return false;
        foreach ( self::parse_txts( $doh_body ) as $val ) {
            if ( str_starts_with( $val, 'v=spf1' ) && str_contains( $val, "include:{$spf_include}" ) ) return true;
        }
        return false;
    }

    private static function parse_dkim( ?array $doh_body, string $public_key ) : bool {
        if ( ! $public_key ) return false;
        foreach ( self::parse_txts( $doh_body ) as $val ) {
            if ( str_contains( $val, 'v=DKIM1' ) ) return true;
        }
        return false;
    }

    private static function parse_dmarc( ?array $doh_body ) : bool {
        foreach ( self::parse_txts( $doh_body ) as $val ) {
            if ( str_starts_with( $val, 'v=DMARC1' ) ) return true;
        }
        return false;
    }

    // ── Individual DNS checks (kept for cron/legacy use) ─────────────────────

    /**
     * Low-level DNS-over-HTTPS helper via direct cURL.
     * wp_remote_get() can ignore timeouts on Windows; cURL's CURLOPT_TIMEOUT
     * is enforced at the socket level and is much more reliable.
     *
     * @return array|null  Decoded JSON body, or null on error/timeout.
     */
    private static function doh_get_json( string $url ) : ?array {
        if ( ! function_exists( 'curl_init' ) ) return null;
        $ch = curl_init( $url );
        curl_setopt_array( $ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 5,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_HTTPHEADER     => [ 'Accept: application/dns-json' ],
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 2,
            CURLOPT_SSL_VERIFYPEER => true,
        ] );
        $body = curl_exec( $ch );
        $err  = curl_error( $ch );
        curl_close( $ch );
        if ( $err ) {
            error_log( '[TmpmpVerify] DoH cURL error: ' . $err . ' url=' . $url );
        }
        return ( $body && ! $err ) ? json_decode( $body, true ) : null;
    }

    /**
     * Fetch TXT records via Cloudflare DoH with a hard 5-second timeout.
     * Falls back to dns_get_record() only if cURL itself is unavailable.
     */
    private static function dns_query_txt( string $host ) : array {
        $url  = 'https://cloudflare-dns.com/dns-query?name=' . urlencode( $host ) . '&type=TXT';
        $data = self::doh_get_json( $url );
        if ( $data !== null ) {
            $out = [];
            foreach ( (array) ( $data['Answer'] ?? [] ) as $ans ) {
                if ( (int) ( $ans['type'] ?? 0 ) === 16 ) {
                    $out[] = trim( $ans['data'] ?? '', '"' );
                }
            }
            error_log( '[TmpmpVerify] DoH TXT ' . $host . ' → ' . count( $out ) . ' record(s)' );
            return $out;
        }
        error_log( '[TmpmpVerify] DoH failed, falling back to dns_get_record for ' . $host );
        $records = @dns_get_record( $host, DNS_TXT );
        $out = [];
        foreach ( (array) $records as $r ) {
            $out[] = is_array( $r['txt'] ?? null ) ? implode( '', $r['txt'] ) : ( $r['txt'] ?? '' );
        }
        return $out;
    }

    /**
     * Fetch MX records via Cloudflare DoH.
     * Returns array of [ 'host' => string, 'pri' => int ].
     */
    private static function dns_query_mx( string $host ) : array {
        $url  = 'https://cloudflare-dns.com/dns-query?name=' . urlencode( $host ) . '&type=MX';
        $data = self::doh_get_json( $url );
        if ( $data !== null ) {
            $out = [];
            foreach ( (array) ( $data['Answer'] ?? [] ) as $ans ) {
                if ( (int) ( $ans['type'] ?? 0 ) === 15 ) {
                    $parts = explode( ' ', trim( $ans['data'] ?? '' ), 2 );
                    $out[] = [
                        'pri'  => (int) ( $parts[0] ?? 0 ),
                        'host' => rtrim( $parts[1] ?? '', '.' ),
                    ];
                }
            }
            error_log( '[TmpmpVerify] DoH MX ' . $host . ' → ' . count( $out ) . ' record(s)' );
            return $out;
        }
        error_log( '[TmpmpVerify] DoH MX failed, falling back to dns_get_record for ' . $host );
        $records = @dns_get_record( $host, DNS_MX );
        $out = [];
        foreach ( (array) $records as $r ) {
            $out[] = [ 'pri' => (int) ( $r['pri'] ?? 0 ), 'host' => rtrim( $r['target'] ?? '', '.' ) ];
        }
        return $out;
    }

    private static function check_txt_ownership( string $domain, string $token ) : bool {
        foreach ( self::dns_query_txt( $domain ) as $val ) {
            if ( $val === $token ) return true;
        }
        return false;
    }

    private static function check_mx( string $domain, string $expected_host ) : bool {
        if ( ! $expected_host ) return false;
        foreach ( self::dns_query_mx( $domain ) as $rec ) {
            if ( strcasecmp( $rec['host'], rtrim( $expected_host, '.' ) ) === 0 ) return true;
        }
        return false;
    }

    private static function check_spf( string $domain, string $spf_include ) : bool {
        if ( ! $spf_include ) return false;
        foreach ( self::dns_query_txt( $domain ) as $val ) {
            if ( str_starts_with( $val, 'v=spf1' ) && str_contains( $val, "include:{$spf_include}" ) ) return true;
        }
        return false;
    }

    private static function check_dkim( string $domain, string $selector, string $public_key ) : bool {
        if ( ! $public_key ) return false;
        $host = "{$selector}._domainkey.{$domain}";
        foreach ( self::dns_query_txt( $host ) as $val ) {
            if ( str_contains( $val, 'v=DKIM1' ) ) return true;
        }
        return false;
    }

    private static function check_dmarc( string $domain ) : bool {
        foreach ( self::dns_query_txt( "_dmarc.{$domain}" ) as $val ) {
            if ( str_starts_with( $val, 'v=DMARC1' ) ) return true;
        }
        return false;
    }

    // ── Admin-side methods ────────────────────────────────────────────────────

    /**
     * Paginated, searchable list of ALL user custom domains (admin view).
     * Joins with wp_users to surface owner display_name and user_email.
     *
     * $args keys:
     *   search   string  — domain or username/email substring
     *   status   string  — 'all'|'verified'|'pending'|'suspended'
     *   page     int     — 1-based page number
     *   per_page int     — rows per page (default 20)
     *
     * Returns [ 'rows' => [...], 'total' => int ]
     */
    public static function get_all_for_admin( array $args = [] ) : array {
        global $wpdb;

        $search   = sanitize_text_field( $args['search']   ?? '' );
        $status   = sanitize_key(        $args['status']   ?? 'all' );
        $page     = max( 1, (int)      ( $args['page']     ?? 1 ) );
        $per_page = max( 1, (int)      ( $args['per_page'] ?? 20 ) );
        $offset   = ( $page - 1 ) * $per_page;

        $t  = $wpdb->prefix . 'tmpmp_user_domains';
        $u  = $wpdb->users;
        $um = $wpdb->usermeta;

        // Base WHERE
        $where   = "WHERE 1=1";
        $params  = [];

        if ( $status !== 'all' && $status !== '' ) {
            $where    .= " AND d.status = %s";
            $params[]  = $status;
        }

        if ( $search !== '' ) {
            $like      = '%' . $wpdb->esc_like( $search ) . '%';
            $where    .= " AND ( d.domain LIKE %s OR u.display_name LIKE %s OR u.user_email LIKE %s )";
            $params[]  = $like;
            $params[]  = $like;
            $params[]  = $like;
        }

        $base_sql = "FROM {$t} d
                     LEFT JOIN {$u} u ON u.ID = d.user_id
                     {$where}";

        // Total count
        $count_sql = $params
            ? $wpdb->prepare( "SELECT COUNT(*) {$base_sql}", ...$params )
            : "SELECT COUNT(*) {$base_sql}";
        $total = (int) $wpdb->get_var( $count_sql );

        // Paginated rows
        $row_sql = "SELECT d.*,
                           u.display_name AS user_display_name,
                           u.user_email   AS user_email,
                           u.user_login   AS user_login
                    {$base_sql}
                    ORDER BY d.created_at DESC
                    LIMIT %d OFFSET %d";

        $row_params   = array_merge( $params, [ $per_page, $offset ] );
        $rows         = $wpdb->get_results(
            $wpdb->prepare( $row_sql, ...$row_params )
        ) ?: [];

        return [ 'rows' => $rows, 'total' => $total ];
    }

    /**
     * Summary stats for the admin panel header cards.
     */
    public static function get_stats_for_admin() : array {
        global $wpdb;
        $t    = $wpdb->prefix . 'tmpmp_user_domains';
        $rows = $wpdb->get_results( "SELECT status, COUNT(*) AS cnt FROM {$t} GROUP BY status" );
        $out  = [ 'total' => 0, 'verified' => 0, 'pending' => 0, 'suspended' => 0 ];
        foreach ( (array) $rows as $r ) {
            $out['total'] += (int) $r->cnt;
            if ( isset( $out[ $r->status ] ) ) {
                $out[ $r->status ] = (int) $r->cnt;
            }
        }
        return $out;
    }

    /**
     * Admin-level add: bypasses user limit, supports assigning to any user.
     */
    public static function admin_add( int $user_id, string $raw_domain ) : int|WP_Error {
        global $wpdb;

        $domain = strtolower( trim( $raw_domain ) );
        $domain = preg_replace( '#^https?://#', '', $domain );
        $domain = rtrim( $domain, '/' );

        if ( ! preg_match( '/^[a-z0-9]([a-z0-9\-]{0,61}[a-z0-9])?(\.[a-z0-9]([a-z0-9\-]{0,61}[a-z0-9])?)+$/i', $domain ) ) {
            return new WP_Error( 'invalid_domain', __( 'Invalid domain format.', 'tempmail-pro' ) );
        }

        $existing = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}tmpmp_user_domains WHERE user_id = %d AND domain = %s",
            $user_id, $domain
        ) );
        if ( $existing ) {
            return new WP_Error( 'duplicate', __( 'This user already has this domain registered.', 'tempmail-pro' ) );
        }

        $wpdb->insert( $wpdb->prefix . 'tmpmp_user_domains', [
            'user_id'          => $user_id,
            'domain'           => $domain,
            'status'           => 'pending',
            'verify_token'     => self::generate_token(),
            'dkim_selector'    => 'tmpro',
            'dkim_private_key' => '',
            'dkim_public_key'  => '',
            'created_at'       => gmdate( 'Y-m-d H:i:s' ),
        ] );

        if ( ! $wpdb->insert_id ) {
            return new WP_Error( 'db_error', __( 'Could not add domain.', 'tempmail-pro' ) );
        }
        return (int) $wpdb->insert_id;
    }

    /**
     * Admin-level delete (no user_id ownership check).
     */
    public static function admin_delete( int $id ) : bool {
        global $wpdb;
        return (bool) $wpdb->delete(
            $wpdb->prefix . 'tmpmp_user_domains',
            [ 'id' => $id ],
            [ '%d' ]
        );
    }

    /**
     * Suspend a domain — blocks it from being used as an inbox domain.
     * Preserves the previous status in a note field if needed later.
     */
    public static function suspend( int $id ) : bool {
        global $wpdb;
        return (bool) $wpdb->update(
            $wpdb->prefix . 'tmpmp_user_domains',
            [ 'status' => 'suspended' ],
            [ 'id'     => $id ],
            [ '%s' ],
            [ '%d' ]
        );
    }

    /**
     * Unsuspend: restores status to 'verified' if all 5 DNS checks pass,
     * otherwise to 'pending'.
     */
    public static function unsuspend( int $id ) : bool {
        global $wpdb;
        $row = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}tmpmp_user_domains WHERE id = %d",
            $id
        ) );
        if ( ! $row ) return false;

        $all_pass = $row->txt_verified && $row->mx_verified &&
                    $row->spf_verified && $row->dkim_verified && $row->dmarc_verified;
        $new_status = $all_pass ? 'verified' : 'pending';

        return (bool) $wpdb->update(
            $wpdb->prefix . 'tmpmp_user_domains',
            [ 'status' => $new_status ],
            [ 'id'     => $id ],
            [ '%s' ],
            [ '%d' ]
        );
    }

    /**
     * Admin-level verify (no user_id ownership check).
     * Wraps verify() by looking up the actual user_id from DB first.
     */
    public static function admin_verify( int $id ) : array|WP_Error {
        global $wpdb;
        $user_id = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT user_id FROM {$wpdb->prefix}tmpmp_user_domains WHERE id = %d",
            $id
        ) );
        if ( ! $user_id ) {
            return new WP_Error( 'not_found', __( 'Domain not found.', 'tempmail-pro' ) );
        }
        return self::verify( $id, $user_id );
    }

    /**
     * Get a single domain row by ID only (admin, no ownership check).
     */
    public static function admin_get( int $id ) : ?object {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}tmpmp_user_domains WHERE id = %d",
            $id
        ) ) ?: null;
    }
}
