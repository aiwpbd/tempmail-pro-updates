<?php
/**
 * TempMailPro — AJAX direct diagnostic
 * Run via LocalWP PHP: bootstraps WordPress, simulates the AJAX call, returns results.
 */
$_SERVER['HTTP_HOST']   = 'mailsaas.local';
$_SERVER['REQUEST_URI'] = '/';
$_SERVER['REQUEST_METHOD'] = 'POST';

$wp_root = 'C:/Users/Tabassum\'s PC/Local Sites/mailsaas/app/public/';
define('ABSPATH', $wp_root);
define('WPINC', 'wp-includes');

// Minimal WP bootstrap
require $wp_root . 'wp-load.php';

global $wpdb;
$out = [];
$t = microtime(true);

$out['01_connected']        = $wpdb->db_version();
$out['02_prefix']           = $wpdb->prefix;

// SHOW COLUMNS test
$t2 = microtime(true);
$col = $wpdb->get_row("SHOW COLUMNS FROM `{$wpdb->prefix}tmpmp_plans` LIKE 'has_custom_domain'");
$out['03_show_columns_ms']  = round((microtime(true)-$t2)*1000, 2);
$out['04_col_exists']       = $col ? 'YES' : 'NO';

// SHOW TABLES test
$ud = $wpdb->prefix . 'tmpmp_user_domains';
$t3 = microtime(true);
$tbl = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $ud));
$out['05_show_tables_ms']   = round((microtime(true)-$t3)*1000, 2);
$out['06_user_domains_tbl'] = ($tbl === $ud) ? 'EXISTS' : 'MISSING';

// Subscriptions
$subs = $wpdb->prefix . 'tmpmp_subscriptions';
$t4 = microtime(true);
$cnt = (int)$wpdb->get_var("SELECT COUNT(*) FROM `{$subs}` WHERE status IN ('active','trialing')");
$out['07_subs_query_ms']    = round((microtime(true)-$t4)*1000, 2);
$out['08_active_subs']      = $cnt;

// Plans table
$plans = $wpdb->get_results("SELECT id,slug,name,has_custom_domain FROM `{$wpdb->prefix}tmpmp_plans`");
$out['09_plans']            = $plans;

// Subscriptions detail
$srows = $wpdb->get_results("SELECT id,user_id,plan_id,status FROM `{$subs}` LIMIT 5");
$out['10_subscriptions']    = $srows;

// DB last error
$out['11_last_error']       = $wpdb->last_error ?: 'none';

// Total time
$out['12_total_ms']         = round((microtime(true)-$t)*1000, 2);

header('Content-Type: application/json');
echo json_encode($out, JSON_PRETTY_PRINT);
