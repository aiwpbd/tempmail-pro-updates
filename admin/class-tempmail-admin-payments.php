<?php
/**
 * TempMail Pro — Admin: Payments
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class TempMail_Admin_Payments {
    private static ?self $instance = null;
    public static function instance() : self { return self::$instance ??= new self(); }
    private function __construct() {}
    public static function render() : void {
        global $wpdb;
        $payments = $wpdb->get_results(
            "SELECT p.*, u.user_email
             FROM {$wpdb->prefix}tmpmp_payments p
             LEFT JOIN {$wpdb->users} u ON u.ID = p.user_id
             ORDER BY p.created_at DESC LIMIT 200"
        );
        $total_revenue = (float)$wpdb->get_var("SELECT COALESCE(SUM(amount),0) FROM {$wpdb->prefix}tmpmp_payments WHERE status='completed'");
        include TMPMP_PLUGIN_DIR . 'admin/views/payments-page.php';
    }
}
