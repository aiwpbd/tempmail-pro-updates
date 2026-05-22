<?php
/**
 * TempMail Pro — All AJAX action handlers (frontend)
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class TempMail_AJAX {

    private static ?self $instance = null;
    public static function instance() : self { return self::$instance ??= new self(); }

    private function __construct() {
        $public_actions = [
            'tmpmp_generate_email',
            'tmpmp_get_inbox',
            'tmpmp_get_email',
            'tmpmp_delete_email',
            'tmpmp_delete_inbox',
            'tmpmp_refresh_expiry',
            'tmpmp_get_domains',
            'tmpmp_background_poll_imap',
            'tmpmp_track_ad_click',
        ];
        foreach ( $public_actions as $action ) {
            add_action( "wp_ajax_{$action}",        [ $this, str_replace('tmpmp_', 'handle_', $action) ] );
            add_action( "wp_ajax_nopriv_{$action}", [ $this, str_replace('tmpmp_', 'handle_', $action) ] );
        }
    }

    // ── Nonce check helper ────────────────────────────────────────────────────
    private function nonce() : void {
        if ( ! check_ajax_referer( 'tempmail_pro_nonce', 'nonce', false ) ) {
            wp_send_json_error( ['message' => __('Security check failed.','tempmail-pro')], 403 );
        }
    }

    private function ip() : string {
        return TempMail_Rate_Limiter::get_client_ip();
    }

    // ── Generate address ──────────────────────────────────────────────────────
    public function handle_generate_email() : void {
        $this->nonce();
        $ip         = $this->ip();
        $session_id = sanitize_text_field( $_POST['session_id'] ?? '' );
        $username   = sanitize_text_field( $_POST['username']   ?? '' );
        $domain     = sanitize_text_field( $_POST['domain']     ?? '' );
        $user_id    = get_current_user_id();

        $result = TempMail_Email_Generator::generate( $username, $domain, $session_id, $ip, $user_id );

        if ( is_wp_error($result) ) {
            // Return 200 so jQuery .done() can read the error message and code
            wp_send_json_error( [
                'message' => $result->get_error_message(),
                'code'    => $result->get_error_code(),
            ] );
        }
        wp_send_json_success( $result );
    }

    // ── Get inbox ─────────────────────────────────────────────────────────────
    public function handle_get_inbox() : void {
        $this->nonce();
        $address = TempMail_Security::sanitize_address( $_POST['address'] ?? '' );
        if ( ! $address ) wp_send_json_error(['message' => 'Address required.'], 400);

        $result = TempMail_Inbox::get_inbox( $address );
        if ( is_wp_error($result) ) wp_send_json_error(['message' => $result->get_error_message()]);
        wp_send_json_success( $result );
    }

    // ── Get single email ──────────────────────────────────────────────────────
    public function handle_get_email() : void {
        $this->nonce();
        $email_id = intval( $_POST['email_id'] ?? 0 );
        $address  = TempMail_Security::sanitize_address( $_POST['address'] ?? '' );
        if ( ! $email_id || ! $address ) wp_send_json_error(['message'=>'Invalid params.'],400);

        $result = TempMail_Inbox::get_email( $email_id, $address );
        if ( is_wp_error($result) ) wp_send_json_error(['message' => $result->get_error_message()]);
        wp_send_json_success( $result );
    }

    // ── Delete single email ───────────────────────────────────────────────────
    public function handle_delete_email() : void {
        $this->nonce();
        $email_id = intval( $_POST['email_id'] ?? 0 );
        $address  = TempMail_Security::sanitize_address( $_POST['address'] ?? '' );
        $ok = TempMail_Inbox::delete_email( $email_id, $address );
        $ok ? wp_send_json_success() : wp_send_json_error(['message'=>'Failed.']);
    }

    // ── Delete inbox ──────────────────────────────────────────────────────────
    public function handle_delete_inbox() : void {
        $this->nonce();
        $address    = TempMail_Security::sanitize_address( $_POST['address'] ?? '' );
        $session_id = sanitize_text_field( $_POST['session_id'] ?? '' );
        $ok = TempMail_Inbox::delete_inbox( $address, $session_id );
        $ok ? wp_send_json_success() : wp_send_json_error(['message'=>'Delete failed.']);
    }

    // ── Refresh expiry timer ──────────────────────────────────────────────────
    public function handle_refresh_expiry() : void {
        $this->nonce();
        $address = TempMail_Security::sanitize_address( $_POST['address'] ?? '' );
        $row = TempMail_Database::get_active_address( $address );
        if ( ! $row ) wp_send_json_error(['message'=>'Expired or not found.']);
        $remaining = max(0, strtotime($row->expires_at . ' UTC') - time());
        wp_send_json_success([
            'expires_at' => $row->expires_at,
            'remaining'  => $remaining,
        ]);
    }

    // ── Get available domains ──────────────────────────────────────────────────
    public function handle_get_domains() : void {
        $this->nonce();
        $user_id   = get_current_user_id();
        $plan_slug = TempMail_Subscription::get_user_plan( $user_id );
        $domains   = TempMail_Domains::get_for_plan( $plan_slug );
        wp_send_json_success( array_values( $domains ) );
    }

    // ── Background IMAP poll (triggered by frontend JS) ───────────────────────
    public function handle_background_poll_imap() : void {
        $this->nonce();
        $settings = get_option('tmpmp_settings', []);
        $protocol = $settings['mail_protocol'] ?? 'webhook';
        if ( ! in_array($protocol, ['imap','pop3'], true) ) {
            wp_send_json_success(['stored' => 0, 'protocol' => $protocol]);
        }
        $result = TempMail_IMAP::poll();
        wp_send_json_success($result);
    }

    // ── Track ad click ────────────────────────────────────────────────────────
    public function handle_track_ad_click() : void {
        $ad_id = intval( $_POST['ad_id'] ?? 0 );
        if ( $ad_id ) TempMail_Ads::track_click( $ad_id );
        wp_send_json_success();
    }
}
