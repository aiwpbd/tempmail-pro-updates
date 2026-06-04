<?php
/**
 * TempMail Pro — WP-Cron jobs
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class TempMail_Cron {

    private static ?self $instance = null;
    public static function instance() : self { return self::$instance ??= new self(); }

    private function __construct() {
        add_filter( 'cron_schedules',           [ $this, 'add_schedules'          ] );
        add_action( 'tmpmp_purge_expired',       [ $this, 'purge_expired'         ] );
        add_action( 'tmpmp_imap_poll',           [ $this, 'imap_poll'             ] );
        add_action( 'tmpmp_optimize_db',         [ $this, 'optimize_db'           ] );
        add_action( 'tmpmp_verify_user_domains', [ $this, 'verify_user_domains'   ] );
        add_action( 'init',                      [ $this, 'ensure_scheduled'      ] );
    }

    public static function schedule_events() : void {
        if ( ! wp_next_scheduled('tmpmp_purge_expired') ) {
            wp_schedule_event( time(), 'tmpmp_5min', 'tmpmp_purge_expired' );
        }
        if ( ! wp_next_scheduled('tmpmp_imap_poll') ) {
            wp_schedule_event( time(), 'tmpmp_1min', 'tmpmp_imap_poll' );
        }
        if ( ! wp_next_scheduled('tmpmp_optimize_db') ) {
            wp_schedule_event( time(), 'daily', 'tmpmp_optimize_db' );
        }
        if ( ! wp_next_scheduled('tmpmp_verify_user_domains') ) {
            wp_schedule_event( time(), 'hourly', 'tmpmp_verify_user_domains' );
        }
    }

    public static function clear_events() : void {
        foreach ( ['tmpmp_purge_expired','tmpmp_imap_poll','tmpmp_optimize_db','tmpmp_verify_user_domains'] as $hook ) {
            $ts = wp_next_scheduled( $hook );
            if ( $ts ) wp_unschedule_event( $ts, $hook );
        }
    }

    public function ensure_scheduled() : void {
        self::schedule_events();
    }

    public function add_schedules( array $schedules ) : array {
        $schedules['tmpmp_1min'] = ['interval' => 60,  'display' => '1 Minute'];
        $schedules['tmpmp_5min'] = ['interval' => 300, 'display' => '5 Minutes'];
        return $schedules;
    }

    public function purge_expired() : void {
        $count = TempMail_Database::purge_expired();
        update_option('tmpmp_last_purge', ['time' => gmdate('c'), 'count' => $count]);
    }

    public function imap_poll() : void {
        $settings = get_option('tmpmp_settings', []);
        if ( ! in_array($settings['mail_protocol'] ?? '', ['imap','pop3'], true) ) return;
        TempMail_IMAP::poll();
    }

    public function optimize_db() : void {
        global $wpdb;
        $tables = [
            $wpdb->prefix . 'tmpmp_addresses',
            $wpdb->prefix . 'tmpmp_emails',
            $wpdb->prefix . 'tmpmp_ratelimit',
        ];
        foreach ( $tables as $t ) {
            $wpdb->query( "OPTIMIZE TABLE $t" );
        }
    }

    public function verify_user_domains() : void {
        TempMail_UserDomains::verify_all_pending();
    }
}
