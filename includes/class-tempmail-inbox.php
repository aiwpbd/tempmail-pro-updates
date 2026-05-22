<?php
/**
 * TempMail Pro — Inbox management (read, receive, delete)
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class TempMail_Inbox {

    // ── Get inbox list ────────────────────────────────────────────────────────
    public static function get_inbox( string $address ) : array|WP_Error {
        $row = TempMail_Database::get_address( $address );
        if ( ! $row ) {
            return new WP_Error( 'not_found', __( 'Email address not found or has expired.', 'tempmail-pro' ) );
        }
        if ( strtotime( $row->expires_at . ' UTC' ) < time() ) {
            return new WP_Error( 'expired', __( 'This inbox has expired.', 'tempmail-pro' ) );
        }
        $emails   = TempMail_Database::get_emails_for_address( (int) $row->id );
        $remaining = max( 0, strtotime( $row->expires_at . ' UTC' ) - time() );
        return [
            'address'   => $row->address,
            'expires_at'=> $row->expires_at,
            'remaining' => $remaining,
            'emails'    => array_map( fn($e) => [
                'id'          => (int) $e->id,
                'sender'      => esc_html( $e->sender ),
                'sender_name' => esc_html( $e->sender_name ),
                'subject'     => esc_html( $e->subject ),
                'received_at' => $e->received_at,
                'is_read'     => (bool) $e->is_read,
                'size_bytes'  => (int) $e->size_bytes,
            ], $emails ),
        ];
    }

    // ── Get single email ──────────────────────────────────────────────────────
    public static function get_email( int $email_id, string $address ) : array|WP_Error {
        $addr_row = TempMail_Database::get_address( $address );
        if ( ! $addr_row ) {
            return new WP_Error( 'not_found', __( 'Address not found.', 'tempmail-pro' ) );
        }
        $email = TempMail_Database::get_email( $email_id, (int) $addr_row->id );
        if ( ! $email ) {
            return new WP_Error( 'not_found', __( 'Email not found.', 'tempmail-pro' ) );
        }
        TempMail_Database::mark_email_read( $email_id );

        // Plain-text fallback: if no text/plain was stored, derive it from HTML
        $body_text = $email->body_text ?: '';
        if ( ! $body_text && $email->body_html ) {
            // Strip tags, decode entities, collapse whitespace
            $body_text = wp_strip_all_tags( $email->body_html );
            $body_text = html_entity_decode( $body_text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
            $body_text = preg_replace( '/[ \t]+/', ' ', $body_text );          // collapse spaces
            $body_text = preg_replace( '/(\s*\n\s*){3,}/', "\n\n", $body_text ); // max 2 blank lines
            $body_text = trim( $body_text );
        }

        return [
            'id'          => (int) $email->id,
            'sender'      => esc_html( $email->sender ),
            'sender_name' => esc_html( $email->sender_name ),
            'subject'     => esc_html( $email->subject ),
            'body_text'   => $body_text,
            'body_html'   => $email->body_html ?: '',
            'received_at' => $email->received_at,
            'has_attach'  => (bool) $email->has_attach,
        ];
    }


    // ── Receive email (from webhook / IMAP) ───────────────────────────────────
    public static function receive_email( array $data ) : bool|WP_Error {
        $to      = strtolower( sanitize_email( $data['to'] ?? '' ) );
        $from    = sanitize_email( $data['from'] ?? '' );
        $name    = sanitize_text_field( $data['from_name'] ?? '' );
        $subject = sanitize_text_field( $data['subject'] ?? '(No Subject)' );
        // Use wp_kses_no_null so email HTML/CSS is preserved (wp_kses_post strips tables/style)
        $text    = wp_kses_no_null( $data['body_text'] ?? '' );
        $html    = wp_kses_no_null( $data['body_html'] ?? '' );
        $attach  = ! empty( $data['attachments'] ) ? 1 : 0;
        $size    = intval( strlen( $text ) + strlen( $html ) );

        if ( ! $to ) return new WP_Error( 'no_recipient', 'No recipient.' );

        $row = TempMail_Database::get_active_address( $to );
        if ( ! $row ) {
            return new WP_Error( 'no_inbox', "No active inbox for: $to" );
        }

        // ── Deduplication via transient (no DB column needed — works immediately) ──
        $raw_mid    = sanitize_text_field( $data['message_id'] ?? '' );
        $dedup_seed = $raw_mid ?: ( $from . '|' . $subject . '|' . substr( $text . $html, 0, 256 ) );
        $dedup_key  = 'tmpmp_mid_' . substr( md5( $dedup_seed . '|' . $row->id ), 0, 20 );

        if ( get_transient( $dedup_key ) ) {
            return true; // already stored — silently skip
        }

        // Spam filter
        $settings = get_option( 'tmpmp_settings', [] );
        if ( ! empty( $settings['spam_filter'] ) ) {
            $keywords = array_filter( array_map( 'trim', explode( "\n", $settings['spam_keywords'] ?? '' ) ) );
            foreach ( $keywords as $kw ) {
                if ( $kw && stripos( $subject . ' ' . $text, $kw ) !== false ) {
                    TempMail_Database::insert_email( [
                        'address_id'  => (int) $row->id,
                        'sender'      => $from,
                        'sender_name' => $name,
                        'subject'     => $subject,
                        'body_text'   => $text,
                        'body_html'   => $html,
                        'has_attach'  => $attach,
                        'received_at' => gmdate( 'Y-m-d H:i:s' ),
                        'is_spam'     => 1,
                        'size_bytes'  => $size,
                    ] );
                    set_transient( $dedup_key, 1, 7 * DAY_IN_SECONDS );
                    return true;
                }
            }
        }

        $id = TempMail_Database::insert_email( [
            'address_id'  => (int) $row->id,
            'sender'      => $from,
            'sender_name' => $name,
            'subject'     => $subject,
            'body_text'   => $text,
            'body_html'   => $html,
            'has_attach'  => $attach,
            'received_at' => gmdate( 'Y-m-d H:i:s' ),
            'is_spam'     => 0,
            'size_bytes'  => $size,
        ] );

        if ( $id > 0 ) {
            // Mark as processed — prevents re-insert on every IMAP poll
            set_transient( $dedup_key, 1, 7 * DAY_IN_SECONDS );
            return true;
        }

        return false;
    }





    // ── Delete email ──────────────────────────────────────────────────────────
    public static function delete_email( int $email_id, string $address ) : bool {
        $row = TempMail_Database::get_address( $address );
        if ( ! $row ) return false;
        return TempMail_Database::delete_email( $email_id, (int) $row->id );
    }

    // ── Delete inbox ──────────────────────────────────────────────────────────
    public static function delete_inbox( string $address, string $session_id ) : bool {
        $row = TempMail_Database::get_address( $address );
        if ( ! $row ) return false;
        if ( $row->session_id !== $session_id ) return false;
        TempMail_Database::delete_address( (int) $row->id );
        return true;
    }
}
