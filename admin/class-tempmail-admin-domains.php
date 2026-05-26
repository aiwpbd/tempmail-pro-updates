<?php
/**
 * TempMail Pro — Admin: Domains Manager
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class TempMail_Admin_Domains {

    private static ?self $instance = null;
    public static function instance() : self { return self::$instance ??= new self(); }

    private function __construct() {
        add_action( 'wp_ajax_tmpmp_add_domain',        [ self::class, 'ajax_add'        ] );
        add_action( 'wp_ajax_tmpmp_update_domain',     [ self::class, 'ajax_update'     ] );
        add_action( 'wp_ajax_tmpmp_delete_domain',     [ self::class, 'ajax_delete'     ] );
        add_action( 'wp_ajax_tmpmp_verify_domain_dns', [ self::class, 'ajax_verify_dns' ] );
        add_action( 'wp_ajax_tmpmp_verify_all_dns',    [ self::class, 'ajax_verify_all' ] );
    }

    public static function render() : void {
        $domains = TempMail_Database::get_all_domains();
        include TMPMP_PLUGIN_DIR . 'admin/views/domains-page.php';
    }

    public static function ajax_add() : void {
        check_ajax_referer( 'tempmail_pro_nonce', 'nonce' );
        if ( ! current_user_can('manage_options') ) wp_send_json_error( [], 403 );

        $domain   = strtolower( sanitize_text_field( $_POST['domain'] ?? '' ) );
        $category = sanitize_key( $_POST['category'] ?? 'free' );
        $desc     = sanitize_text_field( $_POST['desc'] ?? '' );

        if ( ! $domain || ! preg_match( '/^[a-z0-9.-]+\.[a-z]{2,}$/i', $domain ) ) {
            wp_send_json_error( ['message' => 'Invalid domain format.'] );
        }

        $result = TempMail_Domains::add( $domain, $category, $desc );
        if ( is_wp_error( $result ) ) {
            wp_send_json_error( ['message' => $result->get_error_message()] );
        }
        wp_send_json_success( ['id' => $result] );
    }

    public static function ajax_update() : void {
        check_ajax_referer( 'tempmail_pro_nonce', 'nonce' );
        if ( ! current_user_can('manage_options') ) wp_send_json_error( [], 403 );

        $id   = intval( $_POST['id'] ?? 0 );
        $data = [];

        if ( isset( $_POST['category'] ) )  $data['category']  = sanitize_key( $_POST['category'] );
        if ( isset( $_POST['is_active'] ) ) $data['is_active'] = intval( $_POST['is_active'] );

        if ( $id && ! empty($data) ) {
            TempMail_Domains::update( $id, $data );
        }
        wp_send_json_success();
    }

    public static function ajax_delete() : void {
        check_ajax_referer( 'tempmail_pro_nonce', 'nonce' );
        if ( ! current_user_can('manage_options') ) wp_send_json_error( [], 403 );
        $id = intval( $_POST['id'] ?? 0 );
        TempMail_Domains::delete( $id );
        wp_send_json_success();
    }

    // ── DNS Verification ─────────────────────────────────────────────────────

    public static function ajax_verify_dns() : void {
        check_ajax_referer( 'tempmail_pro_nonce', 'nonce' );
        if ( ! current_user_can('manage_options') ) wp_send_json_error( [], 403 );

        $id = intval( $_POST['id'] ?? 0 );
        if ( ! $id ) wp_send_json_error( ['message' => 'Domain ID required.'], 400 );

        $domain_obj = TempMail_Domains::get_full( $id );
        if ( ! $domain_obj ) wp_send_json_error( ['message' => 'Domain not found.'], 404 );

        $result = TempMail_Domains::verify_dns( $domain_obj->domain );
        wp_send_json_success( $result );
    }

    public static function ajax_verify_all() : void {
        check_ajax_referer( 'tempmail_pro_nonce', 'nonce' );
        if ( ! current_user_can('manage_options') ) wp_send_json_error( [], 403 );

        $results = TempMail_Domains::verify_all();
        wp_send_json_success( ['results' => $results] );
    }
}
