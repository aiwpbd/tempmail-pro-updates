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
            'has_custom_user'  => intval( $_POST['has_custom_user'] ?? 0 ),
            'has_api_access'   => intval( $_POST['has_api_access']  ?? 0 ),
            'has_attachments'  => intval( $_POST['has_attachments']  ?? 0 ),
            'no_ads'           => intval( $_POST['no_ads']           ?? 0 ),
            'is_active'        => intval( $_POST['is_active']        ?? 1 ),
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
