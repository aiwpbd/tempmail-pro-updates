<?php
/**
 * TempMail Pro — Admin: Users Manager
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class TempMail_Admin_Users {
    private static ?self $instance = null;
    public static function instance() : self { return self::$instance ??= new self(); }
    private function __construct() {
        add_action('wp_ajax_tmpmp_ban_ip',          [self::class,'ajax_ban_ip']);
        add_action('wp_ajax_tmpmp_unban_ip',        [self::class,'ajax_unban_ip']);
        add_action('wp_ajax_tmpmp_cancel_user_sub', [self::class,'ajax_cancel_sub']);
    }
    public static function render() : void {
        global $wpdb;
        $subs = $wpdb->get_results(
            "SELECT s.*, u.user_email, u.display_name, p.name as plan_name
             FROM {$wpdb->prefix}tmpmp_subscriptions s
             JOIN {$wpdb->users} u ON u.ID = s.user_id
             JOIN {$wpdb->prefix}tmpmp_plans p ON p.id = s.plan_id
             ORDER BY s.created_at DESC LIMIT 200"
        );
        $blocked = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}tmpmp_blocked_ips ORDER BY blocked_at DESC");
        include TMPMP_PLUGIN_DIR . 'admin/views/users-page.php';
    }
    public static function ajax_ban_ip() : void {
        check_ajax_referer('tempmail_pro_nonce','nonce');
        if (!current_user_can('manage_options')) wp_send_json_error([],403);
        global $wpdb;
        $ip     = sanitize_text_field($_POST['ip'] ?? '');
        $reason = sanitize_text_field($_POST['reason'] ?? '');
        if (!filter_var($ip, FILTER_VALIDATE_IP)) wp_send_json_error(['message'=>'Invalid IP.']);
        $wpdb->replace($wpdb->prefix.'tmpmp_blocked_ips',['ip_address'=>$ip,'reason'=>$reason,'blocked_at'=>gmdate('Y-m-d H:i:s')]);
        wp_send_json_success();
    }
    public static function ajax_unban_ip() : void {
        check_ajax_referer('tempmail_pro_nonce','nonce');
        if (!current_user_can('manage_options')) wp_send_json_error([],403);
        global $wpdb;
        $ip = sanitize_text_field($_POST['ip'] ?? '');
        $wpdb->delete($wpdb->prefix.'tmpmp_blocked_ips',['ip_address'=>$ip]);
        wp_send_json_success();
    }
    public static function ajax_cancel_sub() : void {
        check_ajax_referer('tempmail_pro_nonce','nonce');
        if (!current_user_can('manage_options')) wp_send_json_error([],403);
        $user_id = intval($_POST['user_id'] ?? 0);
        TempMail_Subscription::cancel($user_id);
        wp_send_json_success();
    }
}
