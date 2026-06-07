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
            'tmpmp_dash_inbox_app',
            // Permanent inbox actions
            'tmpmp_create_permanent_inbox',
            'tmpmp_get_permanent_inboxes',
            'tmpmp_delete_permanent_inbox',
            'tmpmp_export_inbox',
            'tmpmp_mark_email_read',
            'tmpmp_delete_inbox_address',   // My Inboxes delete — all logged-in users
            'tmpmp_bulk_delete_inbox_addresses', // Bulk delete — single call, all IDs
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

    /**
     * Delete an address from "My Inboxes" — available to ALL logged-in users
     * (not premium-gated). Verifies the address belongs to the current user.
     */
    public function handle_delete_inbox_address() : void {
        $this->nonce();
        $user_id    = get_current_user_id();
        if ( ! $user_id ) wp_send_json_error( ['message' => __('You must be logged in.','tempmail-pro')], 401 );
        $address_id = intval( $_POST['address_id'] ?? 0 );
        if ( ! $address_id ) wp_send_json_error( ['message' => __('Invalid address.','tempmail-pro')], 400 );
        $ok = TempMail_Database::delete_history_address( $address_id, $user_id );
        $ok ? wp_send_json_success() : wp_send_json_error( ['message' => __('Could not delete inbox. It may have already been removed.','tempmail-pro')] );
    }

    /**
     * Bulk-delete multiple inbox addresses in one AJAX call.
     * Accepts address_ids[] in POST; deletes each in a server-side loop.
     * Returns { deleted: N, total: N } — no concurrency, no pool exhaustion.
     */
    public function handle_bulk_delete_inbox_addresses() : void {
        $this->nonce();
        $user_id = get_current_user_id();
        if ( ! $user_id ) wp_send_json_error( ['message' => __('You must be logged in.','tempmail-pro')], 401 );

        $raw_ids = isset( $_POST['address_ids'] ) ? (array) $_POST['address_ids'] : [];
        $ids     = array_values( array_filter( array_map( 'intval', $raw_ids ) ) );
        if ( ! $ids ) wp_send_json_error( ['message' => __('No address IDs provided.','tempmail-pro')], 400 );

        $deleted = 0;
        foreach ( $ids as $id ) {
            if ( TempMail_Database::delete_history_address( $id, $user_id ) ) {
                $deleted++;
            }
        }

        wp_send_json_success( [ 'deleted' => $deleted, 'total' => count( $ids ) ] );
    }

    public function handle_mark_email_read() : void {
        global $wpdb;
        $this->nonce();
        $user_id    = get_current_user_id();
        if ( ! $user_id ) wp_send_json_error(['message' => __('Login required.','tempmail-pro')], 401);
        $email_id   = intval( $_POST['email_id']   ?? 0 );
        $address_id = intval( $_POST['address_id'] ?? 0 );
        if ( ! $email_id || ! $address_id ) wp_send_json_error(['message' => 'Invalid params.'], 400);
        // Verify the address belongs to this user
        $owned = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}tmpmp_addresses WHERE id = %d AND user_id = %d",
            $address_id, $user_id
        ) );
        if ( ! $owned ) wp_send_json_error(['message' => __('Access denied.','tempmail-pro')], 403);
        TempMail_Database::mark_email_read( $email_id );
        wp_send_json_success();
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

    // ── Dashboard Inbox App tab (premium-only, renders shortcode via AJAX) ────
    public function handle_dash_inbox_app() : void {
        $this->nonce();

        $user_id = get_current_user_id();
        if ( ! $user_id ) {
            wp_send_json_error( ['message' => __('Login required.','tempmail-pro')], 401 );
        }

        if ( ! TempMail_Subscription::is_premium_user( $user_id ) ) {
            wp_send_json_error( ['message' => __('This feature requires a paid subscription.','tempmail-pro')], 403 );
        }

        // Render the inbox shortcode in a sandboxed context
        // Set up the global $post so shortcode helpers work correctly
        global $post;
        $inbox_page = get_page_by_path('tempmail-app') ?: get_page_by_path('tempmail');
        if ( $inbox_page ) {
            $post = $inbox_page; // phpcs:ignore WordPress.WP.GlobalVariablesOverride
            setup_postdata( $post );
        }

        // Capture the rendered shortcode HTML
        ob_start();
        echo do_shortcode( '[tempmail_app]' );
        $html = ob_get_clean();

        wp_reset_postdata();

        wp_send_json_success( ['html' => $html] );
    }

    // ── Permanent Inbox handlers ───────────────────────────────────────────────

    /** Create a new permanent inbox (premium-gated, plan-limit enforced) */
    public function handle_create_permanent_inbox() : void {
        $this->nonce();
        $user_id = get_current_user_id();
        if ( ! $user_id ) wp_send_json_error( ['message' => __('Login required.','tempmail-pro')], 401 );
        if ( ! TempMail_Subscription::is_premium_user( $user_id ) )
            wp_send_json_error( ['message' => __('Requires an active paid subscription.','tempmail-pro')], 403 );

        // Check plan limit
        $sub = TempMail_Database::get_user_subscription( $user_id );
        $max = isset( $sub->max_permanent_inboxes ) ? (int) $sub->max_permanent_inboxes : 0;
        if ( ! isset( $sub->has_permanent_inbox ) || ! $sub->has_permanent_inbox )
            wp_send_json_error( ['message' => __('Your plan does not include Permanent Inboxes.','tempmail-pro')], 403 );

        $current = TempMail_Database::count_permanent_inboxes_for_user( $user_id );
        if ( $max !== -1 && $current >= $max )
            wp_send_json_error( [
                'message' => sprintf(
                    __('You have reached your plan limit of %d permanent inbox(es).','tempmail-pro'),
                    $max
                ),
            ], 403 );

        $domain   = sanitize_text_field( $_POST['domain']   ?? '' );
        $username = sanitize_text_field( $_POST['username'] ?? '' );

        if ( ! $domain ) wp_send_json_error( ['message' => __('Domain is required.','tempmail-pro')] );

        // Validate domain: must be a global system domain OR the user's own verified custom domain
        $global_domains = TempMail_Database::get_all_domains();
        $valid_global   = array_filter( $global_domains, fn($d) => $d->domain === $domain );

        if ( empty( $valid_global ) ) {
            // Check user's verified custom domains
            $user_custom   = TempMail_UserDomains::get_for_user( $user_id );
            $valid_custom  = array_filter( $user_custom, function( $d ) use ( $domain ) {
                return $d->domain === $domain
                    && $d->txt_verified && $d->mx_verified && $d->spf_verified
                    && $d->dkim_verified && $d->dmarc_verified;
            });
            if ( empty( $valid_custom ) ) {
                wp_send_json_error( ['message' => __('Invalid domain. Your custom domain must be fully verified before use.','tempmail-pro')] );
            }
        }


        // Generate username if not provided
        if ( ! $username ) {
            $username = strtolower( wp_generate_password(8, false) );
        }
        $username = preg_replace('/[^a-z0-9._+-]/i', '', strtolower($username));
        if ( strlen($username) < 3 ) wp_send_json_error( ['message' => __('Username must be at least 3 characters.','tempmail-pro')] );

        $address = $username . '@' . $domain;

        // Check address not already taken
        if ( TempMail_Database::get_address( $address ) )
            wp_send_json_error( ['message' => __('This address is already taken. Try a different username.','tempmail-pro')] );

        $now = gmdate('Y-m-d H:i:s');
        $id  = TempMail_Database::insert_address( [
            'address'      => $address,
            'session_id'   => '',
            'ip_address'   => '',
            'user_id'      => $user_id,
            'plan'         => TempMail_Subscription::get_user_plan( $user_id ),
            'is_private'   => 1,
            'is_permanent' => 1,
            'created_at'   => $now,
            'expires_at'   => '9999-12-31 23:59:59', // sentinel — never expires
        ] );

        if ( ! $id ) wp_send_json_error( ['message' => __('Could not create inbox. Please try again.','tempmail-pro')] );

        wp_send_json_success( [
            'id'         => $id,
            'address'    => $address,
            'created_at' => $now,
            'email_count'=> 0,
        ] );
    }

    /** List the current user's permanent inboxes */
    public function handle_get_permanent_inboxes() : void {
        $this->nonce();
        $user_id = get_current_user_id();
        if ( ! $user_id ) wp_send_json_error( ['message' => __('Login required.','tempmail-pro')], 401 );
        if ( ! TempMail_Subscription::is_premium_user( $user_id ) )
            wp_send_json_error( ['message' => __('Requires an active paid subscription.','tempmail-pro')], 403 );

        $inboxes = TempMail_Database::get_permanent_inboxes_for_user( $user_id );
        $sub     = TempMail_Database::get_user_subscription( $user_id );
        $max     = isset( $sub->max_permanent_inboxes ) ? (int) $sub->max_permanent_inboxes : 0;

        wp_send_json_success( [
            'inboxes' => $inboxes,
            'count'   => count($inboxes),
            'max'     => $max,
            'can_create' => ( $max === -1 || count($inboxes) < $max ),
        ] );
    }

    /** Delete a permanent inbox (user must own it) */
    public function handle_delete_permanent_inbox() : void {
        $this->nonce();
        $user_id    = get_current_user_id();
        $address_id = (int) ( $_POST['address_id'] ?? 0 );
        if ( ! $user_id )      wp_send_json_error( ['message' => __('Login required.','tempmail-pro')], 401 );
        if ( ! $address_id )   wp_send_json_error( ['message' => __('Invalid inbox.','tempmail-pro')] );

        global $wpdb;
        $row = $wpdb->get_row( $wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}tmpmp_addresses WHERE id = %d AND user_id = %d AND is_permanent = 1",
            $address_id, $user_id
        ) );
        if ( ! $row ) wp_send_json_error( ['message' => __('Inbox not found.','tempmail-pro')], 404 );

        TempMail_Database::delete_address( $address_id );
        wp_send_json_success( ['deleted' => $address_id] );
    }

    /** Export all emails for a permanent inbox as JSON or CSV */
    public function handle_export_inbox() : void {
        $this->nonce();
        $user_id    = get_current_user_id();
        $address_id = (int) ( $_POST['address_id'] ?? 0 );
        $format     = in_array( $_POST['format'] ?? '', ['json','csv'], true ) ? $_POST['format'] : 'json';

        if ( ! $user_id )    wp_send_json_error( ['message' => __('Login required.','tempmail-pro')], 401 );
        if ( ! $address_id ) wp_send_json_error( ['message' => __('Invalid inbox.','tempmail-pro')] );

        $data = TempMail_Database::get_emails_for_permanent_export( $address_id, $user_id );
        if ( ! $data ) wp_send_json_error( ['message' => __('Inbox not found.','tempmail-pro')], 404 );

        $address = $data['address']->address;
        $emails  = $data['emails'];

        if ( $format === 'json' ) {
            $out = [];
            foreach ( $emails as $e ) {
                $out[] = [
                    'id'          => (int) $e->id,
                    'from'        => $e->sender,
                    'from_name'   => $e->sender_name,
                    'subject'     => $e->subject,
                    'body_text'   => $e->body_text,
                    'body_html'   => $e->body_html,
                    'received_at' => $e->received_at,
                    'is_read'     => (bool) $e->is_read,
                    'size_bytes'  => (int) $e->size_bytes,
                ];
            }
            wp_send_json_success( [
                'format'   => 'json',
                'address'  => $address,
                'count'    => count($out),
                'emails'   => $out,
            ] );
        }

        // CSV — send raw and signal client to download
        $lines   = [];
        $lines[] = implode(',', ['id','from','from_name','subject','received_at','is_read','size_bytes']);
        foreach ( $emails as $e ) {
            $lines[] = implode(',', [
                (int) $e->id,
                '"' . str_replace('"','""', $e->sender)      . '"',
                '"' . str_replace('"','""', $e->sender_name) . '"',
                '"' . str_replace('"','""', $e->subject)     . '"',
                '"' . $e->received_at . '"',
                $e->is_read ? '1' : '0',
                (int) $e->size_bytes,
            ]);
        }
        wp_send_json_success( [
            'format'   => 'csv',
            'address'  => $address,
            'count'    => count($emails),
            'content'  => implode("\n", $lines),
            'filename' => 'inbox-' . sanitize_file_name($address) . '-' . gmdate('Y-m-d') . '.csv',
        ] );
    }
}

