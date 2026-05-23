<?php
/**
 * TempMail Pro — Subscription & Plan management
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class TempMail_Subscription {

    private static ?self $instance = null;
    public static function instance() : self { return self::$instance ??= new self(); }

    private function __construct() {
        add_action( 'init', [ $this, 'init_hooks' ] );
    }

    public function init_hooks() : void {
        add_shortcode( 'tempmail_pricing', [ $this, 'render_pricing' ] );
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    public static function is_premium_user( int $user_id = 0 ) : bool {
        if ( ! $user_id ) $user_id = get_current_user_id();
        if ( ! $user_id ) return false;
        $sub = TempMail_Database::get_user_subscription( $user_id );
        return $sub !== null;
    }

    public static function get_user_plan( int $user_id = 0 ) : string {
        if ( ! $user_id ) $user_id = get_current_user_id();
        if ( ! $user_id ) return 'free';
        $sub = TempMail_Database::get_user_subscription( $user_id );
        return $sub ? $sub->plan_slug : 'free';
    }

    public static function get_user_plan_data( int $user_id = 0 ) : object {
        if ( ! $user_id ) $user_id = get_current_user_id();
        if ( $user_id ) {
            $sub = TempMail_Database::get_user_subscription( $user_id );
            if ( $sub ) return $sub;
        }
        // Return free plan data from DB, or a hard-coded fallback
        $free = TempMail_Database::get_plan_by_slug( 'free' );
        if ( $free ) return $free;

        return (object) [
            'slug'             => 'free',
            'name'             => 'Free',
            'inbox_lifetime'   => 30,
            'refresh_interval' => 15,
            'max_inboxes'      => 3,
            'max_storage_mb'   => 5,
            'no_ads'           => 0,
            'has_api_access'   => 0,
            'has_attachments'  => 0,
            'has_custom_user'  => 0,
            'domains_allowed'  => '["free"]',
        ];
    }

    public static function can_create_inbox( int $user_id = 0, int $current_count = 0 ) : bool {
        $plan = self::get_user_plan_data( $user_id );
        $max  = intval( $plan->max_inboxes ?? 3 );
        if ( $max === -1 ) return true; // unlimited
        return $current_count < $max;
    }

    // ── Activate subscription ─────────────────────────────────────────────────
    public static function activate( int $user_id, int $plan_id, string $gateway,
                                     string $gateway_sub_id, string $billing_cycle,
                                     float $amount, string $currency = 'USD' ) : int {
        global $wpdb;

        // Deactivate any existing
        $wpdb->update(
            $wpdb->prefix . 'tmpmp_subscriptions',
            [ 'status' => 'cancelled', 'cancelled_at' => gmdate('Y-m-d H:i:s'), 'updated_at' => gmdate('Y-m-d H:i:s') ],
            [ 'user_id' => $user_id, 'status' => 'active' ]
        );

        $period_days = $billing_cycle === 'yearly' ? 365 : 30;
        $now  = gmdate('Y-m-d H:i:s');
        $end  = gmdate('Y-m-d H:i:s', strtotime( "+{$period_days} days" ) );

        $wpdb->insert( $wpdb->prefix . 'tmpmp_subscriptions', [
            'user_id'              => $user_id,
            'plan_id'              => $plan_id,
            'gateway'              => $gateway,
            'gateway_sub_id'       => $gateway_sub_id,
            'status'               => 'active',
            'billing_cycle'        => $billing_cycle,
            'amount'               => $amount,
            'currency'             => $currency,
            'current_period_start' => $now,
            'current_period_end'   => $end,
            'created_at'           => $now,
            'updated_at'           => $now,
        ] );

        return (int) $wpdb->insert_id;
    }

    // ── Cancel ────────────────────────────────────────────────────────────────
    public static function cancel( int $user_id ) : bool {
        global $wpdb;
        $affected = $wpdb->update(
            $wpdb->prefix . 'tmpmp_subscriptions',
            [ 'status' => 'cancelled', 'cancelled_at' => gmdate('Y-m-d H:i:s'), 'updated_at' => gmdate('Y-m-d H:i:s') ],
            [ 'user_id' => $user_id, 'status' => 'active' ]
        );
        return $affected > 0;
    }

    // ── Pricing shortcode ─────────────────────────────────────────────────────
    public function render_pricing( array $atts ) : string {
        $plans = TempMail_Database::get_all_plans();
        ob_start();
        include TMPMP_PLUGIN_DIR . 'public/views/pricing-page.php';
        return ob_get_clean();
    }
}
