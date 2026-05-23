<?php
/**
 * TempMail Pro — API Keys
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class TempMail_API_Keys {

    private static ?self $instance = null;
    public static function instance() : self { return self::$instance ??= new self(); }

    private function __construct() {
        add_action( 'wp_ajax_tmpmp_create_api_key',  [ $this, 'ajax_create' ] );
        add_action( 'wp_ajax_tmpmp_revoke_api_key',  [ $this, 'ajax_revoke' ] );
        add_action( 'wp_ajax_tmpmp_list_api_keys',   [ $this, 'ajax_list' ] );
    }

    public static function generate_key( int $user_id, string $label = 'Default' ) : mixed {
        // Must be on a plan with API access
        $plan = TempMail_Subscription::get_user_plan_data( $user_id );
        if ( empty( $plan->has_api_access ) ) {
            return new WP_Error( 'no_api', __( 'API access requires Pro or Business plan.', 'tempmail-pro' ) );
        }
        global $wpdb;
        $key = 'tmpmp_' . bin2hex( random_bytes( 20 ) );
        $wpdb->insert( $wpdb->prefix . 'tmpmp_api_keys', [
            'user_id'    => $user_id,
            'api_key'    => $key,
            'label'      => sanitize_text_field( $label ),
            'created_at' => gmdate('Y-m-d H:i:s'),
        ] );
        return $key;
    }

    public static function validate( string $key ) : mixed {
        $record = TempMail_Database::get_api_key_record( $key );
        if ( ! $record ) {
            return new WP_Error( 'invalid_key', 'Invalid or expired API key.', [ 'status' => 401 ] );
        }
        // Increment usage
        global $wpdb;
        $wpdb->query( $wpdb->prepare(
            "UPDATE {$wpdb->prefix}tmpmp_api_keys SET calls_count = calls_count + 1, last_used = %s WHERE api_key = %s",
            gmdate('Y-m-d H:i:s'), $key
        ) );
        return $record;
    }

    public function ajax_create() : void {
        check_ajax_referer( 'tempmail_pro_nonce', 'nonce' );
        $user_id = get_current_user_id();
        if ( ! $user_id ) wp_send_json_error(['message' => 'Login required.'], 401);
        $label   = sanitize_text_field( $_POST['label'] ?? 'Default' );
        $key     = self::generate_key( $user_id, $label );
        if ( is_wp_error( $key ) ) wp_send_json_error(['message' => $key->get_error_message()]);
        wp_send_json_success(['api_key' => $key]);
    }

    public function ajax_revoke() : void {
        check_ajax_referer( 'tempmail_pro_nonce', 'nonce' );
        $user_id = get_current_user_id();
        $key_id  = intval( $_POST['key_id'] ?? 0 );
        global $wpdb;
        $wpdb->update(
            $wpdb->prefix . 'tmpmp_api_keys',
            ['is_active' => 0],
            ['id' => $key_id, 'user_id' => $user_id]
        );
        wp_send_json_success();
    }

    public function ajax_list() : void {
        check_ajax_referer( 'tempmail_pro_nonce', 'nonce' );
        $user_id = get_current_user_id();
        if ( ! $user_id ) wp_send_json_error(['message' => 'Login required.'], 401);
        global $wpdb;
        $keys = $wpdb->get_results( $wpdb->prepare(
            "SELECT id, label, api_key, calls_count, last_used, is_active, created_at FROM {$wpdb->prefix}tmpmp_api_keys WHERE user_id = %d ORDER BY created_at DESC",
            $user_id
        ) );
        wp_send_json_success(['keys' => $keys]);
    }
}
