<?php
define('ABSPATH', dirname(__DIR__, 3) . '/');
require_once dirname(__DIR__, 3) . '/wp-load.php';

global $wpdb;

// Show last 5 addresses with their user_ids
$rows = $wpdb->get_results(
    "SELECT id, address, user_id, expires_at FROM {$wpdb->prefix}tmpmp_addresses ORDER BY created_at DESC LIMIT 10"
);

echo "=== Last 10 addresses ===\n";
foreach ($rows as $r) {
    echo "id={$r->id}  user_id={$r->user_id}  addr={$r->address}\n";
}

echo "\n=== wpdb last_error: " . ($wpdb->last_error ?: 'none') . " ===\n";

// Try a dry-run delete on the first address (show what SQL would run)
if (!empty($rows)) {
    $first = $rows[0];
    echo "\n=== DRY DELETE test on id={$first->id}, user_id={$first->user_id} ===\n";
    $result = $wpdb->delete(
        $wpdb->prefix . 'tmpmp_addresses',
        ['id' => $first->id, 'user_id' => $first->user_id]
    );
    echo "Result: " . var_export($result, true) . "\n";
    echo "Rows affected: " . $wpdb->rows_affected . "\n";
    echo "Last error: " . ($wpdb->last_error ?: 'none') . "\n";
}
