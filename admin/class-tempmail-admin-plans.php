<?php
/**
 * TempMail Pro — Admin: Plans Manager
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class TempMail_Admin_Plans {

    private static ?self $instance = null;
    public static function instance() : self { return self::$instance ??= new self(); }

    private function __construct() {
        add_action( 'wp_ajax_tmpmp_save_plan',   [ self::class, 'ajax_save'   ] );
        add_action( 'wp_ajax_tmpmp_delete_plan', [ self::class, 'ajax_delete' ] );
        // Run on 'init' (not just 'admin_init') so it fires on front-end AJAX requests too
        add_action( 'init', [ self::class, 'maybe_migrate' ], 5 );
    }

    /**
     * Ensures all premium-feature columns exist in tmpmp_plans and that
     * the tmpmp_user_domains table exists.
     * Runs once when the stored DB micro-version doesn't match the current one.
     */
    public static function maybe_migrate() : void {
        $current_micro = '1.4'; // bump whenever you add new plan columns or data migrations
        if ( get_option( 'tmpmp_plan_cols_ver' ) === $current_micro ) return;

        global $wpdb;
        $table = $wpdb->prefix . 'tmpmp_plans';

        // ── 1. Ensure all feature columns exist ──────────────────────────────
        $new_cols = [
            'has_premium_domains'       => "TINYINT(1) NOT NULL DEFAULT 0",
            'has_premium_storage'       => "TINYINT(1) NOT NULL DEFAULT 0",
            'has_custom_branding'       => "TINYINT(1) NOT NULL DEFAULT 0",
            'has_inbox_retention'       => "TINYINT(1) NOT NULL DEFAULT 0",
            'has_vip_domains'           => "TINYINT(1) NOT NULL DEFAULT 0",
            'has_unlimited_attachments' => "TINYINT(1) NOT NULL DEFAULT 0",
            'has_email_forwarding'      => "TINYINT(1) NOT NULL DEFAULT 0",
            'has_alias_management'      => "TINYINT(1) NOT NULL DEFAULT 0",
            'has_advanced_spam'         => "TINYINT(1) NOT NULL DEFAULT 0",
            'has_custom_domain'         => "TINYINT(1) NOT NULL DEFAULT 0",
        ];

        $existing = $wpdb->get_col( "DESC `{$table}`", 0 );
        foreach ( $new_cols as $col => $definition ) {
            if ( ! in_array( $col, $existing, true ) ) {
                $wpdb->query( "ALTER TABLE `{$table}` ADD COLUMN `{$col}` {$definition}" );
            }
        }

        // ── 2. Data migration: enable has_custom_domain for all non-free plans ──
        // Existing paid plans have has_custom_domain = 0 (DEFAULT). Auto-enable
        // so subscribers can use the Custom Domain feature immediately.
        $wpdb->query(
            "UPDATE `{$table}` SET `has_custom_domain` = 1
             WHERE `slug` != 'free' AND `has_custom_domain` = 0"
        );

        // ── 3. Ensure the user_domains table exists (idempotent via dbDelta) ──
        $charset = $wpdb->get_charset_collate();
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( "CREATE TABLE {$wpdb->prefix}tmpmp_user_domains (
            id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id          BIGINT UNSIGNED NOT NULL,
            domain           VARCHAR(255) NOT NULL,
            status           VARCHAR(32) NOT NULL DEFAULT 'pending',
            verify_token     VARCHAR(128) NOT NULL DEFAULT '',
            txt_verified     TINYINT(1) NOT NULL DEFAULT 0,
            mx_verified      TINYINT(1) NOT NULL DEFAULT 0,
            spf_verified     TINYINT(1) NOT NULL DEFAULT 0,
            dkim_selector    VARCHAR(64) NOT NULL DEFAULT 'tmpro',
            dkim_private_key LONGTEXT,
            dkim_public_key  LONGTEXT,
            dkim_verified    TINYINT(1) NOT NULL DEFAULT 0,
            dmarc_verified   TINYINT(1) NOT NULL DEFAULT 0,
            last_checked     DATETIME DEFAULT NULL,
            verified_at      DATETIME DEFAULT NULL,
            created_at       DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY user_domain (user_id, domain),
            KEY status (status),
            KEY user_id (user_id)
        ) {$charset};" );

        update_option( 'tmpmp_plan_cols_ver', $current_micro );
    }

    public static function render() : void {
        $plans = TempMail_Database::get_all_plans( false );
        include TMPMP_PLUGIN_DIR . 'admin/views/plans-page.php';
    }

    public static function ajax_save() : void {
        check_ajax_referer( 'tempmail_pro_nonce', 'nonce' );
        if ( ! current_user_can('manage_options') ) wp_send_json_error( [], 403 );

        global $wpdb;
        $id = intval( $_POST['id'] ?? 0 );

        $data = [
            'slug'             => sanitize_key( $_POST['slug'] ?? '' ),
            'name'             => sanitize_text_field( $_POST['name'] ?? '' ),
            'price_monthly'    => floatval( $_POST['price_monthly'] ?? 0 ),
            'price_yearly'     => floatval( $_POST['price_yearly'] ?? 0 ),
            'max_inboxes'      => intval( $_POST['max_inboxes'] ?? 1 ),
            'inbox_lifetime'   => intval( $_POST['inbox_lifetime'] ?? 30 ),
            'refresh_interval' => intval( $_POST['refresh_interval'] ?? 10 ),
            'max_storage_mb'   => intval( $_POST['max_storage_mb'] ?? 5 ),
            'sort_order'       => intval( $_POST['sort_order'] ?? 0 ),
            'domains_allowed'  => sanitize_text_field( $_POST['domains_allowed'] ?? '["free"]' ),
            'features'         => (static function() {
                $raw     = trim( wp_unslash( $_POST['features'] ?? '' ) );
                $decoded = json_decode( $raw, true );
                if ( is_array( $decoded ) ) return wp_json_encode( $decoded );
                $lines = array_values( array_filter( array_map( 'sanitize_text_field', preg_split( '/\r?\n/', $raw ) ) ) );
                return wp_json_encode( $lines ?: [] );
            })(),
            // Original capabilities
            'has_custom_user'  => intval( $_POST['has_custom_user'] ?? 0 ),
            'has_api_access'   => intval( $_POST['has_api_access']  ?? 0 ),
            'has_attachments'  => intval( $_POST['has_attachments']  ?? 0 ),
            'no_ads'           => intval( $_POST['no_ads']           ?? 0 ),
            'is_active'        => intval( $_POST['is_active']        ?? 1 ),
            // Premium feature capabilities
            'has_premium_domains'       => intval( $_POST['has_premium_domains']       ?? 0 ),
            'has_premium_storage'       => intval( $_POST['has_premium_storage']       ?? 0 ),
            'has_custom_branding'       => intval( $_POST['has_custom_branding']       ?? 0 ),
            'has_inbox_retention'       => intval( $_POST['has_inbox_retention']       ?? 0 ),
            'has_vip_domains'           => intval( $_POST['has_vip_domains']           ?? 0 ),
            'has_unlimited_attachments' => intval( $_POST['has_unlimited_attachments'] ?? 0 ),
            'has_email_forwarding'      => intval( $_POST['has_email_forwarding']      ?? 0 ),
            'has_alias_management'      => intval( $_POST['has_alias_management']      ?? 0 ),
            'has_advanced_spam'         => intval( $_POST['has_advanced_spam']         ?? 0 ),
            'has_custom_domain'         => intval( $_POST['has_custom_domain']         ?? 0 ),
        ];

        if ( ! $data['slug'] || ! $data['name'] ) {
            wp_send_json_error( ['message' => 'Slug and name are required.'] );
        }

        if ( $id ) {
            $wpdb->update( $wpdb->prefix . 'tmpmp_plans', $data, [ 'id' => $id ] );
        } else {
            $wpdb->insert( $wpdb->prefix . 'tmpmp_plans', $data );
            $id = $wpdb->insert_id;
        }

        wp_send_json_success( [ 'id' => $id ] );
    }

    public static function ajax_delete() : void {
        check_ajax_referer( 'tempmail_pro_nonce', 'nonce' );
        if ( ! current_user_can('manage_options') ) wp_send_json_error( [], 403 );
        global $wpdb;
        $id = intval( $_POST['id'] ?? 0 );
        // Prevent deleting "free" plan
        $plan = TempMail_Database::get_plan( $id );
        if ( $plan && $plan->slug === 'free' ) {
            wp_send_json_error( ['message' => 'Cannot delete the Free plan.'] );
        }
        $wpdb->delete( $wpdb->prefix . 'tmpmp_plans', [ 'id' => $id ] );
        wp_send_json_success();
    }
}
