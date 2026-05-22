<?php
/**
 * TempMail Pro — Admin: Analytics
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class TempMail_Admin_Analytics {
    private static ?self $instance = null;
    public static function instance() : self { return self::$instance ??= new self(); }
    private function __construct() {
        add_action('wp_ajax_tmpmp_get_chart_data',[self::class,'ajax_chart_data']);
    }
    public static function render() : void {
        global $wpdb;
        $stats  = TempMail_Database::get_stats();
        $days   = 30;
        $emails_chart = $wpdb->get_results(
            "SELECT DATE(received_at) as day, COUNT(*) as cnt
             FROM {$wpdb->prefix}tmpmp_emails
             WHERE received_at >= DATE_SUB(UTC_DATE(), INTERVAL {$days} DAY)
             GROUP BY day ORDER BY day ASC"
        );
        $revenue_chart = $wpdb->get_results(
            "SELECT DATE(created_at) as day, SUM(amount) as total
             FROM {$wpdb->prefix}tmpmp_payments
             WHERE created_at >= DATE_SUB(UTC_DATE(), INTERVAL {$days} DAY) AND status='completed'
             GROUP BY day ORDER BY day ASC"
        );
        $top_domains = $wpdb->get_results(
            "SELECT SUBSTRING_INDEX(address,'@',-1) as domain, COUNT(*) as cnt
             FROM {$wpdb->prefix}tmpmp_addresses
             GROUP BY domain ORDER BY cnt DESC LIMIT 10"
        );
        include TMPMP_PLUGIN_DIR . 'admin/views/analytics-page.php';
    }
    public static function ajax_chart_data() : void {
        check_ajax_referer('tempmail_pro_nonce','nonce');
        if (!current_user_can('manage_options')) wp_send_json_error([],403);
        $stats = TempMail_Database::get_stats();
        wp_send_json_success($stats);
    }
}
