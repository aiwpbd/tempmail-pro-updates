<?php if ( ! defined('ABSPATH') ) exit; ?>
<div class="wrap tmpmp-admin-wrap">
<h1 class="tmpmp-admin-title"><span class="dashicons dashicons-money-alt"></span> <?php esc_html_e('Payment Transactions','tempmail-pro'); ?> <span class="tmpmp-version-pill">v<?php echo esc_html(TMPMP_VERSION); ?></span></h1>

<!-- Revenue Summary -->
<div class="tmpmp-stats-grid" style="margin-bottom:20px;">
    <div class="tmpmp-stat-card">
        <div class="tmpmp-stat-icon" style="background:linear-gradient(135deg,#10b981,#059669);">💰</div>
        <div class="tmpmp-stat-value">$<?php echo number_format($total_revenue, 2); ?></div>
        <div class="tmpmp-stat-label"><?php esc_html_e('Total Revenue','tempmail-pro'); ?></div>
    </div>
    <?php
    global $wpdb;
    $month_rev = (float)$wpdb->get_var("SELECT COALESCE(SUM(amount),0) FROM {$wpdb->prefix}tmpmp_payments WHERE status='completed' AND MONTH(created_at)=MONTH(UTC_DATE()) AND YEAR(created_at)=YEAR(UTC_DATE())");
    $txn_count = count($payments);
    ?>
    <div class="tmpmp-stat-card">
        <div class="tmpmp-stat-icon" style="background:linear-gradient(135deg,#6366f1,#8b5cf6);">📅</div>
        <div class="tmpmp-stat-value">$<?php echo number_format($month_rev, 2); ?></div>
        <div class="tmpmp-stat-label"><?php esc_html_e('This Month','tempmail-pro'); ?></div>
    </div>
    <div class="tmpmp-stat-card">
        <div class="tmpmp-stat-icon" style="background:linear-gradient(135deg,#f59e0b,#d97706);">🧾</div>
        <div class="tmpmp-stat-value"><?php echo number_format($txn_count); ?></div>
        <div class="tmpmp-stat-label"><?php esc_html_e('Transactions','tempmail-pro'); ?></div>
    </div>
</div>

<!-- Transactions Table -->
<div class="tmpmp-card">
<h2 class="tmpmp-card-title">🧾 <?php esc_html_e('Transaction History','tempmail-pro'); ?></h2>
<?php if(empty($payments)): ?>
<p style="color:#64748b;padding:8px 0;"><?php esc_html_e('No transactions yet.','tempmail-pro'); ?></p>
<?php else: ?>
<table class="widefat striped tmpmp-data-table" style="font-size:13px;">
<thead><tr>
    <th><?php esc_html_e('Invoice','tempmail-pro'); ?></th>
    <th><?php esc_html_e('User','tempmail-pro'); ?></th>
    <th><?php esc_html_e('Gateway','tempmail-pro'); ?></th>
    <th><?php esc_html_e('Description','tempmail-pro'); ?></th>
    <th><?php esc_html_e('Amount','tempmail-pro'); ?></th>
    <th><?php esc_html_e('Status','tempmail-pro'); ?></th>
    <th><?php esc_html_e('Date','tempmail-pro'); ?></th>
</tr></thead>
<tbody>
<?php foreach($payments as $p): ?>
<tr>
    <td><code style="font-size:11px;"><?php echo esc_html($p->invoice_number ?: '—'); ?></code></td>
    <td style="font-size:12px;"><?php echo esc_html($p->user_email ?: 'Guest #'.$p->user_id); ?></td>
    <td><span class="tmpmp-badge tmpmp-badge--gray"><?php echo esc_html(ucfirst($p->gateway)); ?></span></td>
    <td style="font-size:12px;color:#64748b;"><?php echo esc_html($p->description ?: '—'); ?></td>
    <td><strong>$<?php echo number_format($p->amount, 2); ?> <span style="font-size:11px;color:#94a3b8;"><?php echo esc_html($p->currency); ?></span></strong></td>
    <td>
        <span class="tmpmp-badge <?php echo $p->status === 'completed' ? 'tmpmp-badge--green' : ($p->status === 'refunded' ? 'tmpmp-badge--red' : 'tmpmp-badge--yellow'); ?>">
            <?php echo esc_html(ucfirst($p->status)); ?>
        </span>
    </td>
    <td style="color:#64748b;font-size:12px;"><?php echo esc_html(date_i18n(get_option('date_format') . ' H:i', strtotime($p->created_at))); ?></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
<p style="color:#64748b;font-size:12px;margin-top:8px;"><?php esc_html_e('Showing last 200 transactions.','tempmail-pro'); ?></p>
<?php endif; ?>
</div>
</div>
