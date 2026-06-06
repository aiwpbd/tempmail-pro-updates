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
            'slug'                    => 'free',
            'name'                    => 'Free',
            'inbox_lifetime'          => 30,
            'refresh_interval'        => 15,
            'max_inboxes'             => 3,
            'max_storage_mb'          => 5,
            'no_ads'                  => 0,
            'has_api_access'          => 0,
            'has_attachments'         => 0,
            'has_custom_user'         => 0,
            'domains_allowed'         => '["free"]',
            'has_premium_domains'     => 0,
            'has_premium_storage'     => 0,
            'has_custom_branding'     => 0,
            'has_inbox_retention'     => 0,
            'has_vip_domains'         => 0,
            'has_unlimited_attachments' => 0,
            'has_email_forwarding'    => 0,
            'has_alias_management'    => 0,
            'has_advanced_spam'       => 0,
            'has_custom_domain'       => 0,
            'has_permanent_inbox'     => 0,
            'max_permanent_inboxes'   => 0,
        ];

    }

    /**
     * Check whether the user's active plan has a specific feature flag enabled.
     *
     * @param int    $user_id  0 = current user
     * @param string $feature  Column name e.g. 'has_premium_domains'
     */
    public static function user_has_feature( int $user_id, string $feature ) : bool {
        $plan = self::get_user_plan_data( $user_id );
        return ! empty( $plan->$feature );
    }

    /**
     * Returns the effective domain category list for a user.
     * Starts from the plan's domains_allowed JSON and expands it based
     * on the has_premium_domains / has_vip_domains flags.
     *
     * @param int $user_id  0 = current user
     * @return string[]
     */
    public static function get_allowed_domain_cats( int $user_id = 0 ) : array {
        $plan = self::get_user_plan_data( $user_id );
        $cats = json_decode( $plan->domains_allowed ?? '["free"]', true );
        if ( ! is_array( $cats ) ) $cats = ['free'];

        if ( ! empty( $plan->has_premium_domains ) && ! in_array( 'premium', $cats, true ) ) {
            $cats[] = 'premium';
        }
        if ( ! empty( $plan->has_vip_domains ) && ! in_array( 'vip', $cats, true ) ) {
            $cats[] = 'vip';
        }
        return $cats;
    }

    /**
     * Returns a flat map of all feature flags for the current user's plan.
     * Sent to the frontend so it can show/hide premium UI elements.
     *
     * @param int $user_id  0 = current user
     * @return array<string,bool>
     */
    public static function get_user_features( int $user_id = 0 ) : array {
        $plan = self::get_user_plan_data( $user_id );
        $features = [
            'has_custom_user'           => ! empty( $plan->has_custom_user ),
            'has_attachments'           => ! empty( $plan->has_attachments ),
            'has_api_access'            => ! empty( $plan->has_api_access ),
            'no_ads'                    => ! empty( $plan->no_ads ),
            'has_premium_domains'       => ! empty( $plan->has_premium_domains ),
            'has_premium_storage'       => ! empty( $plan->has_premium_storage ),
            'has_custom_branding'       => ! empty( $plan->has_custom_branding ),
            'has_inbox_retention'       => ! empty( $plan->has_inbox_retention ),
            'has_vip_domains'           => ! empty( $plan->has_vip_domains ),
            'has_unlimited_attachments' => ! empty( $plan->has_unlimited_attachments ),
            'has_email_forwarding'      => ! empty( $plan->has_email_forwarding ),
            'has_alias_management'      => ! empty( $plan->has_alias_management ),
            'has_advanced_spam'         => ! empty( $plan->has_advanced_spam ),
            'has_custom_domain'         => ! empty( $plan->has_custom_domain ),
            'has_permanent_inbox'       => ! empty( $plan->has_permanent_inbox ),
            'max_permanent_inboxes'     => intval( $plan->max_permanent_inboxes ?? 0 ),
            'allowed_domain_cats'       => self::get_allowed_domain_cats( $user_id ),
        ];
        return $features;

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

        $sub_id = (int) $wpdb->insert_id;

        // ── Backfill address plan slugs ──────────────────────────────────────
        // When a user subscribes/upgrades, sync all their existing addresses
        // so the "My Inboxes" tab shows the correct plan badge immediately.
        $plan_obj  = TempMail_Database::get_plan( $plan_id );
        $plan_slug = $plan_obj ? sanitize_key( $plan_obj->slug ?? $plan_obj->plan_slug ?? 'pro' ) : 'pro';
        $wpdb->update(
            $wpdb->prefix . 'tmpmp_addresses',
            [ 'plan' => $plan_slug ],
            [ 'user_id' => $user_id ]
        );

        return $sub_id;
    }

    // ── Cancel ────────────────────────────────────────────────────────────────
    public static function cancel( int $user_id ) : bool {
        global $wpdb;
        $affected = $wpdb->update(
            $wpdb->prefix . 'tmpmp_subscriptions',
            [ 'status' => 'cancelled', 'cancelled_at' => gmdate('Y-m-d H:i:s'), 'updated_at' => gmdate('Y-m-d H:i:s') ],
            [ 'user_id' => $user_id, 'status' => 'active' ]
        );
        if ( $affected ) {
            // Revert address plan badges to free
            $wpdb->update(
                $wpdb->prefix . 'tmpmp_addresses',
                [ 'plan' => 'free' ],
                [ 'user_id' => $user_id ]
            );
        }
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

