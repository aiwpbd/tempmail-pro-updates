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
            'tmpmp_get_user_features',
        ];
        foreach ( $public_actions as $action ) {
            add_action( "wp_ajax_{$action}",        [ $this, str_replace('tmpmp_', 'handle_', $action) ] );
            add_action( "wp_ajax_nopriv_{$action}", [ $this, str_replace('tmpmp_', 'handle_', $action) ] );
        }

        // Logged-in-only actions (history + premium features)
        $auth_actions = [
            'tmpmp_get_address_history',
            'tmpmp_get_history_emails',
            'tmpmp_delete_history_address',
            'tmpmp_get_history_email_body',
            'tmpmp_save_forwarding_email',
            'tmpmp_get_forwarding_email',
            'tmpmp_save_spam_rules',
            'tmpmp_get_spam_rules',
        ];
        foreach ( $auth_actions as $action ) {
            add_action( "wp_ajax_{$action}", [ $this, str_replace('tmpmp_', 'handle_', $action) ] );
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
        // ── Prevent ANY caching layer (CDN, LiteSpeed, WP Rocket, browser)
        //    from serving a stale inbox response. This is the #1 cause of
        //    "inbox doesn't refresh without page reload" on live servers.
        nocache_headers();
        header( 'Cache-Control: no-store, no-cache, must-revalidate, max-age=0, private' );
        header( 'Pragma: no-cache' );
        header( 'Vary: *' );

        $this->nonce();
        $address = TempMail_Security::sanitize_address( $_POST['address'] ?? '' );
        if ( ! $address ) wp_send_json_error(['message' => 'Address required.'], 400);

        $result = TempMail_Inbox::get_inbox( $address );
        if ( is_wp_error($result) ) wp_send_json_error([
            'message' => $result->get_error_message(),
            'code'    => $result->get_error_code(),
        ]);
        wp_send_json_success( $result );
    }

    // ── Get single email ──────────────────────────────────────────────────────
    public function handle_get_email() : void {
        $this->nonce();
        $email_id = intval( $_POST['email_id'] ?? 0 );
        $address  = TempMail_Security::sanitize_address( $_POST['address'] ?? '' );
        if ( ! $email_id || ! $address ) wp_send_json_error(['message'=>'Invalid params.'],400);

        $result = TempMail_Inbox::get_email( $email_id, $address );
        if ( is_wp_error($result) ) wp_send_json_error([
            'message' => $result->get_error_message(),
            'code'    => $result->get_error_code(),
        ]);
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
        $user_id      = get_current_user_id();
        // Use expanded category list so has_premium_domains / has_vip_domains unlock their pools
        $allowed_cats = TempMail_Subscription::get_allowed_domain_cats( $user_id );
        $all_domains  = TempMail_Database::get_all_domains();
        $domains      = array_values( array_filter(
            $all_domains,
            fn($d) => in_array( $d->category, $allowed_cats, true )
        ) );
        wp_send_json_success( $domains );
    }

    // ── Get all feature flags for the current user ────────────────────────────
    public function handle_get_user_features() : void {
        $this->nonce();
        $user_id  = get_current_user_id();
        $features = TempMail_Subscription::get_user_features( $user_id );
        wp_send_json_success( $features );
    }

    // ── Background IMAP poll (triggered by frontend JS) ───────────────────────
    public function handle_background_poll_imap() : void {
        nocache_headers();
        header( 'Cache-Control: no-store, no-cache, must-revalidate, max-age=0, private' );
        header( 'Pragma: no-cache' );
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

    // ══════════════════════════════════════════════════════════════════════════
    // Address History — premium users only
    // ══════════════════════════════════════════════════════════════════════════

    private function require_premium() : int {
        $this->nonce();
        $user_id = get_current_user_id();
        if ( ! $user_id ) {
            wp_send_json_error( ['message' => __('You must be logged in.','tempmail-pro')], 401 );
        }
        if ( ! TempMail_Subscription::is_premium_user( $user_id ) ) {
            wp_send_json_error( ['message' => __('This feature requires a Premium subscription.','tempmail-pro')], 403 );
        }
        return $user_id;
    }

    // ── GET paginated history list ────────────────────────────────────────────
    public function handle_get_address_history() : void {
        $user_id  = $this->require_premium();
        $page     = max(1, intval( $_POST['page']     ?? 1 ));
        $per_page = max(1, min(50, intval( $_POST['per_page'] ?? 20 )));
        $result   = TempMail_Database::get_address_history_for_user( $user_id, $per_page, $page );
        wp_send_json_success( $result );
    }

    // ── GET email list for a history address ─────────────────────────────────
    public function handle_get_history_emails() : void {
        $user_id    = $this->require_premium();
        $address_id = intval( $_POST['address_id'] ?? 0 );
        if ( ! $address_id ) wp_send_json_error(['message' => 'address_id required.'], 400);
        $result = TempMail_Database::get_history_emails( $address_id, $user_id );
        if ( $result === null ) wp_send_json_error(['message' => __('Address not found.','tempmail-pro')], 404);
        wp_send_json_success( $result );
    }

    // ── GET single email body from history ────────────────────────────────────
    public function handle_get_history_email_body() : void {
        global $wpdb;
        $user_id    = $this->require_premium();
        $email_id   = intval( $_POST['email_id']   ?? 0 );
        $address_id = intval( $_POST['address_id'] ?? 0 );
        if ( ! $email_id || ! $address_id ) wp_send_json_error(['message'=>'Invalid params.'], 400);

        // Verify ownership
        $owned = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}tmpmp_addresses WHERE id = %d AND user_id = %d",
            $address_id, $user_id
        ) );
        if ( ! $owned ) wp_send_json_error(['message' => __('Access denied.','tempmail-pro')], 403);

        $email = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}tmpmp_emails WHERE id = %d AND address_id = %d",
            $email_id, $address_id
        ) );
        if ( ! $email ) wp_send_json_error(['message' => __('Email not found.','tempmail-pro')], 404);
        wp_send_json_success( $email );
    }

    // ── DELETE a history address ──────────────────────────────────────────────
    public function handle_delete_history_address() : void {
        $user_id    = $this->require_premium();
        $address_id = intval( $_POST['address_id'] ?? 0 );
        if ( ! $address_id ) wp_send_json_error(['message' => 'address_id required.'], 400);
        $ok = TempMail_Database::delete_history_address( $address_id, $user_id );
        $ok ? wp_send_json_success() : wp_send_json_error(['message' => __('Delete failed.','tempmail-pro')]);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // Email Forwarding — requires has_email_forwarding
    // ══════════════════════════════════════════════════════════════════════════

    private function require_feature( string $feature ) : int {
        $this->nonce();
        $user_id = get_current_user_id();
        if ( ! $user_id ) {
            wp_send_json_error( ['message' => __('You must be logged in.','tempmail-pro')], 401 );
        }
        if ( ! TempMail_Subscription::user_has_feature( $user_id, $feature ) ) {
            wp_send_json_error( [
                'message' => sprintf(
                    /* translators: %s: feature name */
                    __('Your plan does not include %s. Please upgrade.','tempmail-pro'),
                    $feature
                )
            ], 403 );
        }
        return $user_id;
    }

    /** Save the forwarding address for the current user */
    public function handle_save_forwarding_email() : void {
        $user_id = $this->require_feature( 'has_email_forwarding' );
        $email   = sanitize_email( $_POST['forwarding_email'] ?? '' );
        if ( $email && ! is_email( $email ) ) {
            wp_send_json_error( ['message' => __('Invalid email address.','tempmail-pro')], 400 );
        }
        update_user_meta( $user_id, 'tmpmp_forwarding_email', $email );
        wp_send_json_success( ['forwarding_email' => $email] );
    }

    /** Get the forwarding address for the current user */
    public function handle_get_forwarding_email() : void {
        $this->nonce();
        $user_id = get_current_user_id();
        if ( ! $user_id ) wp_send_json_error(['message'=>'Login required.'],401);
        $email = get_user_meta( $user_id, 'tmpmp_forwarding_email', true ) ?: '';
        wp_send_json_success( ['forwarding_email' => $email] );
    }

    // ══════════════════════════════════════════════════════════════════════════
    // Advanced Spam Rules — requires has_advanced_spam
    // ══════════════════════════════════════════════════════════════════════════

    /** Save per-user spam keyword list (newline-separated) */
    public function handle_save_spam_rules() : void {
        $user_id  = $this->require_feature( 'has_advanced_spam' );
        $keywords = sanitize_textarea_field( $_POST['spam_keywords'] ?? '' );
        update_user_meta( $user_id, 'tmpmp_spam_keywords', $keywords );
        wp_send_json_success( ['spam_keywords' => $keywords] );
    }

    /** Get the per-user spam keyword list */
    public function handle_get_spam_rules() : void {
        $this->nonce();
        $user_id = get_current_user_id();
        if ( ! $user_id ) wp_send_json_error(['message'=>'Login required.'],401);
        $keywords = get_user_meta( $user_id, 'tmpmp_spam_keywords', true ) ?: '';
        wp_send_json_success( ['spam_keywords' => $keywords] );
    }
}
