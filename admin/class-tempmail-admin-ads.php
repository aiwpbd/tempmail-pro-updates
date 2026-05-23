<?php
/**
 * TempMail Pro — Admin: Ads Manager
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class TempMail_Admin_Ads {
    private static ?self $instance = null;
    public static function instance() : self { return self::$instance ??= new self(); }
    private function __construct() {
        add_action('wp_ajax_tmpmp_save_ad',   [self::class,'ajax_save']);
        add_action('wp_ajax_tmpmp_delete_ad', [self::class,'ajax_delete']);
    }
    public static function render() : void {
        global $wpdb;
        $ads = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}tmpmp_ads ORDER BY created_at DESC");
        include TMPMP_PLUGIN_DIR . 'admin/views/ads-page.php';
    }
    public static function ajax_save() : void {
        check_ajax_referer('tempmail_pro_nonce','nonce');
        if (!current_user_can('manage_options')) wp_send_json_error([],403);
        global $wpdb;
        $id   = intval($_POST['id'] ?? 0);
        $data = [
            'name'      => sanitize_text_field($_POST['name'] ?? ''),
            'placement' => sanitize_key($_POST['placement'] ?? 'sidebar'),
            'type'      => sanitize_key($_POST['type'] ?? 'banner'),
            'code'      => wp_kses_post(wp_unslash($_POST['code'] ?? '')),
            'is_active' => intval($_POST['is_active'] ?? 1),
        ];
        if ($id) {
            $wpdb->update($wpdb->prefix.'tmpmp_ads', $data, ['id'=>$id]);
        } else {
            $wpdb->insert($wpdb->prefix.'tmpmp_ads', array_merge($data,['created_at'=>gmdate('Y-m-d H:i:s')]));
            $id = $wpdb->insert_id;
        }
        wp_send_json_success(['id'=>$id]);
    }
    public static function ajax_delete() : void {
        check_ajax_referer('tempmail_pro_nonce','nonce');
        if (!current_user_can('manage_options')) wp_send_json_error([],403);
        global $wpdb;
        $wpdb->delete($wpdb->prefix.'tmpmp_ads',['id'=>intval($_POST['id']??0)]);
        wp_send_json_success();
    }
}
