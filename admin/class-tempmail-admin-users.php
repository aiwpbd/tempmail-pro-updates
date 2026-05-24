<?php
/**
 * TempMail Pro — Admin: Users & Subscriptions Manager
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class TempMail_Admin_Users {
    private static ?self $instance = null;
    public static function instance() : self { return self::$instance ??= new self(); }

    private function __construct() {
        add_action( 'wp_ajax_tmpmp_ban_ip',             [ self::class, 'ajax_ban_ip'          ] );
        add_action( 'wp_ajax_tmpmp_unban_ip',           [ self::class, 'ajax_unban_ip'        ] );
        add_action( 'wp_ajax_tmpmp_cancel_user_sub',    [ self::class, 'ajax_cancel_sub'      ] );
        add_action( 'wp_ajax_tmpmp_admin_update_user',  [ self::class, 'ajax_update_user'     ] );
        add_action( 'wp_ajax_tmpmp_admin_set_plan',     [ self::class, 'ajax_set_plan'        ] );
        add_action( 'wp_ajax_tmpmp_admin_delete_user',  [ self::class, 'ajax_delete_user'     ] );
        add_action( 'wp_ajax_tmpmp_admin_get_user',     [ self::class, 'ajax_get_user'        ] );
    }

    // ── Render page ────────────────────────────────────────────────────────────
    public static function render() : void {
        global $wpdb;

        $cap_key = $wpdb->prefix . 'capabilities';

        $users = $wpdb->get_results( "
            SELECT
                u.ID              AS user_id,
                u.user_email,
                u.display_name,
                u.user_registered,
                s.id              AS sub_id,
                s.plan_id         AS sub_plan_id,
                s.status          AS sub_status,
                s.billing_cycle,
                s.amount          AS sub_amount,
                s.currency,
                s.gateway,
                s.current_period_end,
                p.name            AS plan_name,
                p.slug            AS plan_slug,
                COALESCE(addr.cnt,   0)    AS address_count,
                COALESCE(pay.cnt,    0)    AS payment_count,
                COALESCE(pay.total,  0.00) AS total_spent
            FROM {$wpdb->users} u
            LEFT JOIN (
                SELECT user_id, MAX(id) AS max_id
                FROM {$wpdb->prefix}tmpmp_subscriptions
                WHERE status IN ('active','trialing')
                GROUP BY user_id
            ) sm ON sm.user_id = u.ID
            LEFT JOIN {$wpdb->prefix}tmpmp_subscriptions s ON s.id = sm.max_id
            LEFT JOIN {$wpdb->prefix}tmpmp_plans p ON p.id = s.plan_id
            LEFT JOIN (
                SELECT user_id, COUNT(*) AS cnt
                FROM   {$wpdb->prefix}tmpmp_addresses
                WHERE  user_id > 0
                GROUP  BY user_id
            ) addr ON addr.user_id = u.ID
            LEFT JOIN (
                SELECT user_id, COUNT(*) AS cnt, COALESCE(SUM(amount),0) AS total
                FROM   {$wpdb->prefix}tmpmp_payments
                WHERE  status = 'completed'
                GROUP  BY user_id
            ) pay ON pay.user_id = u.ID
            WHERE u.ID NOT IN (
                SELECT user_id FROM {$wpdb->usermeta}
                WHERE meta_key = '{$cap_key}' AND meta_value LIKE '%administrator%'
            )
            ORDER BY u.user_registered DESC
            LIMIT 500
        " );

        $blocked       = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}tmpmp_blocked_ips ORDER BY blocked_at DESC" );
        $plans         = TempMail_Database::get_all_plans( false );
        $total_users   = count( $users );
        $premium_count = count( array_filter( $users, fn( $u ) => ! empty( $u->sub_id ) ) );
        $free_count    = $total_users - $premium_count;
        $total_revenue = array_sum( array_column( $users, 'total_spent' ) );

        include TMPMP_PLUGIN_DIR . 'admin/views/users-page.php';
    }

    // ── AJAX: Update user profile ──────────────────────────────────────────────
    public static function ajax_update_user() : void {
        check_ajax_referer( 'tempmail_pro_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( [], 403 );

        $user_id = intval( $_POST['user_id'] ?? 0 );
        if ( ! $user_id ) wp_send_json_error( [ 'message' => __( 'Invalid user.', 'tempmail-pro' ) ] );

        $args = [ 'ID' => $user_id ];

        if ( ! empty( $_POST['display_name'] ) ) {
            $args['display_name'] = sanitize_text_field( wp_unslash( $_POST['display_name'] ) );
        }
        if ( ! empty( $_POST['user_email'] ) && is_email( $_POST['user_email'] ) ) {
            // Check email not taken by another user
            $existing = get_user_by( 'email', sanitize_email( $_POST['user_email'] ) );
            if ( $existing && (int) $existing->ID !== $user_id ) {
                wp_send_json_error( [ 'message' => __( 'Email already in use by another account.', 'tempmail-pro' ) ] );
            }
            $args['user_email'] = sanitize_email( $_POST['user_email'] );
        }
        if ( ! empty( $_POST['user_pass'] ) && strlen( $_POST['user_pass'] ) >= 8 ) {
            $args['user_pass'] = wp_unslash( $_POST['user_pass'] );
        }

        $result = wp_update_user( $args );
        if ( is_wp_error( $result ) ) {
            wp_send_json_error( [ 'message' => $result->get_error_message() ] );
        }

        wp_send_json_success( [ 'message' => __( 'User profile updated.', 'tempmail-pro' ) ] );
    }

    // ── AJAX: Assign / change plan ─────────────────────────────────────────────
    public static function ajax_set_plan() : void {
        check_ajax_referer( 'tempmail_pro_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( [], 403 );

        global $wpdb;
        $user_id       = intval( $_POST['user_id']       ?? 0 );
        $plan_id       = intval( $_POST['plan_id']        ?? 0 );
        $billing_cycle = sanitize_text_field( $_POST['billing_cycle'] ?? 'monthly' );
        $period_end    = sanitize_text_field( $_POST['period_end']    ?? '' );

        if ( ! $user_id || ! $plan_id ) wp_send_json_error( [ 'message' => __( 'Invalid parameters.', 'tempmail-pro' ) ] );

        $plan = TempMail_Database::get_plan( $plan_id );
        if ( ! $plan ) wp_send_json_error( [ 'message' => __( 'Plan not found.', 'tempmail-pro' ) ] );

        $now = gmdate( 'Y-m-d H:i:s' );

        if ( $plan->slug === 'free' ) {
            // Cancel any active sub
            $wpdb->query( $wpdb->prepare(
                "UPDATE {$wpdb->prefix}tmpmp_subscriptions
                 SET status='cancelled', cancelled_at=%s, updated_at=%s
                 WHERE user_id=%d AND status IN ('active','trialing')",
                $now, $now, $user_id
            ) );
            wp_send_json_success( [ 'message' => sprintf( __( 'User set to %s plan.', 'tempmail-pro' ), $plan->name ) ] );
        }

        $amount = ( $billing_cycle === 'yearly' )
            ? floatval( $plan->price_yearly )
            : floatval( $plan->price_monthly );

        if ( ! $period_end ) {
            $period_end = gmdate( 'Y-m-d H:i:s', strtotime( $billing_cycle === 'yearly' ? '+1 year' : '+1 month' ) );
        }

        // Cancel existing active subs first
        $wpdb->query( $wpdb->prepare(
            "UPDATE {$wpdb->prefix}tmpmp_subscriptions
             SET status='cancelled', cancelled_at=%s, updated_at=%s
             WHERE user_id=%d AND status IN ('active','trialing')",
            $now, $now, $user_id
        ) );

        // Create fresh manual subscription
        $wpdb->insert( $wpdb->prefix . 'tmpmp_subscriptions', [
            'user_id'              => $user_id,
            'plan_id'              => $plan_id,
            'gateway'              => 'manual',
            'gateway_sub_id'       => 'manual_' . $user_id . '_' . time(),
            'gateway_cust_id'      => '',
            'status'               => 'active',
            'billing_cycle'        => $billing_cycle,
            'amount'               => $amount,
            'currency'             => 'USD',
            'trial_ends'           => null,
            'current_period_start' => $now,
            'current_period_end'   => $period_end,
            'cancelled_at'         => null,
            'created_at'           => $now,
            'updated_at'           => $now,
        ] );

        wp_send_json_success( [ 'message' => sprintf( __( 'Plan set to %s.', 'tempmail-pro' ), $plan->name ) ] );
    }

    // ── AJAX: Delete user & all their data ────────────────────────────────────
    public static function ajax_delete_user() : void {
        check_ajax_referer( 'tempmail_pro_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( [], 403 );

        global $wpdb;
        $user_id = intval( $_POST['user_id'] ?? 0 );
        if ( ! $user_id ) wp_send_json_error( [ 'message' => __( 'Invalid user.', 'tempmail-pro' ) ] );

        if ( user_can( $user_id, 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Cannot delete admin users.', 'tempmail-pro' ) ] );
        }

        // Delete plugin data
        $addr_ids = $wpdb->get_col( $wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}tmpmp_addresses WHERE user_id = %d", $user_id
        ) );
        if ( $addr_ids ) {
            $in = implode( ',', array_map( 'intval', $addr_ids ) );
            $wpdb->query( "DELETE FROM {$wpdb->prefix}tmpmp_emails WHERE address_id IN ($in)" );
            $wpdb->query( "DELETE FROM {$wpdb->prefix}tmpmp_addresses WHERE id IN ($in)" );
        }
        $wpdb->delete( $wpdb->prefix . 'tmpmp_subscriptions', [ 'user_id' => $user_id ] );
        $wpdb->delete( $wpdb->prefix . 'tmpmp_payments',      [ 'user_id' => $user_id ] );
        $wpdb->delete( $wpdb->prefix . 'tmpmp_api_keys',      [ 'user_id' => $user_id ] );

        require_once ABSPATH . 'wp-admin/includes/user.php';
        wp_delete_user( $user_id );

        wp_send_json_success( [ 'message' => __( 'User and all associated data deleted.', 'tempmail-pro' ) ] );
    }

    // ── AJAX: Get full user details for modal ──────────────────────────────────
    public static function ajax_get_user() : void {
        check_ajax_referer( 'tempmail_pro_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( [], 403 );

        global $wpdb;
        $user_id = intval( $_POST['user_id'] ?? 0 );
        if ( ! $user_id ) wp_send_json_error( [ 'message' => __( 'Invalid user.', 'tempmail-pro' ) ] );

        $user = get_userdata( $user_id );
        if ( ! $user ) wp_send_json_error( [ 'message' => __( 'User not found.', 'tempmail-pro' ) ] );

        $sub = TempMail_Database::get_user_subscription( $user_id );

        $payments = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}tmpmp_payments
             WHERE user_id = %d ORDER BY created_at DESC LIMIT 10",
            $user_id
        ) );

        $addresses = $wpdb->get_results( $wpdb->prepare(
            "SELECT a.*,
                    (SELECT COUNT(*) FROM {$wpdb->prefix}tmpmp_emails e WHERE e.address_id = a.id) AS email_count
             FROM   {$wpdb->prefix}tmpmp_addresses a
             WHERE  a.user_id = %d
             ORDER  BY a.created_at DESC LIMIT 20",
            $user_id
        ) );

        wp_send_json_success( [
            'user_id'      => $user_id,
            'display_name' => $user->display_name,
            'user_email'   => $user->user_email,
            'registered'   => $user->user_registered,
            'sub'          => $sub,
            'payments'     => $payments,
            'addresses'    => $addresses,
        ] );
    }

    // ── AJAX: Ban IP ───────────────────────────────────────────────────────────
    public static function ajax_ban_ip() : void {
        check_ajax_referer( 'tempmail_pro_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( [], 403 );
        global $wpdb;
        $ip     = sanitize_text_field( $_POST['ip']     ?? '' );
        $reason = sanitize_text_field( $_POST['reason'] ?? '' );
        if ( ! filter_var( $ip, FILTER_VALIDATE_IP ) ) wp_send_json_error( [ 'message' => 'Invalid IP.' ] );
        $wpdb->replace( $wpdb->prefix . 'tmpmp_blocked_ips', [
            'ip_address' => $ip,
            'reason'     => $reason,
            'blocked_at' => gmdate( 'Y-m-d H:i:s' ),
        ] );
        wp_send_json_success();
    }

    // ── AJAX: Unban IP ─────────────────────────────────────────────────────────
    public static function ajax_unban_ip() : void {
        check_ajax_referer( 'tempmail_pro_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( [], 403 );
        global $wpdb;
        $ip = sanitize_text_field( $_POST['ip'] ?? '' );
        $wpdb->delete( $wpdb->prefix . 'tmpmp_blocked_ips', [ 'ip_address' => $ip ] );
        wp_send_json_success();
    }

    // ── AJAX: Cancel subscription ──────────────────────────────────────────────
    public static function ajax_cancel_sub() : void {
        check_ajax_referer( 'tempmail_pro_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( [], 403 );
        $user_id = intval( $_POST['user_id'] ?? 0 );
        TempMail_Subscription::cancel( $user_id );
        wp_send_json_success();
    }
}
