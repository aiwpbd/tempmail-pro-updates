<?php
/**
 * TempMail Pro — Uninstall
 * Runs when plugin is deleted from WP admin.
 */
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) exit;

global $wpdb;

// Drop all custom tables
$tables = [
    'tmpmp_addresses',
    'tmpmp_emails',
    'tmpmp_ratelimit',
    'tmpmp_plans',
    'tmpmp_subscriptions',
    'tmpmp_payments',
    'tmpmp_domains',
    'tmpmp_api_keys',
    'tmpmp_ads',
    'tmpmp_blocked_ips',
];
foreach ( $tables as $table ) {
    $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}{$table}" );
}

// Delete all plugin options
$options = [
    'tmpmp_settings',
    'tmpmp_db_version',
    'tmpmp_last_seen_version',
    'tmpmp_changelog_dismissed',
    'tmpmp_last_webhook_hit',
    'tmpmp_last_webhook_error',
    'tmpmp_last_imap_poll',
    'tmpmp_last_server_cron',
    'tmpmp_last_purge',
];
foreach ( $options as $opt ) {
    delete_option( $opt );
}

// Clear scheduled events
$hooks = [ 'tmpmp_purge_expired', 'tmpmp_imap_poll', 'tmpmp_optimize_db' ];
foreach ( $hooks as $hook ) {
    $ts = wp_next_scheduled( $hook );
    if ( $ts ) wp_unschedule_event( $ts, $hook );
}
