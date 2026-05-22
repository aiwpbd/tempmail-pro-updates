<?php if ( ! defined('ABSPATH') ) exit; ?>
<div class="wrap tmpmp-admin-wrap">
<h1 class="tmpmp-admin-title"><span class="dashicons dashicons-groups"></span> <?php esc_html_e('Users & Subscriptions','tempmail-pro'); ?> <span class="tmpmp-version-pill">v<?php echo esc_html(TMPMP_VERSION); ?></span></h1>

<!-- Active Subscriptions -->
<div class="tmpmp-card" style="margin-bottom:20px;">
<h2 class="tmpmp-card-title">💎 <?php esc_html_e('Active Subscriptions','tempmail-pro'); ?></h2>
<?php if(empty($subs)): ?>
<p style="color:#64748b;padding:8px 0;"><?php esc_html_e('No active subscriptions yet.','tempmail-pro'); ?></p>
<?php else: ?>
<table class="widefat striped tmpmp-data-table" style="font-size:13px;">
<thead><tr>
    <th><?php esc_html_e('User','tempmail-pro'); ?></th>
    <th><?php esc_html_e('Plan','tempmail-pro'); ?></th>
    <th><?php esc_html_e('Gateway','tempmail-pro'); ?></th>
    <th><?php esc_html_e('Cycle','tempmail-pro'); ?></th>
    <th><?php esc_html_e('Amount','tempmail-pro'); ?></th>
    <th><?php esc_html_e('Status','tempmail-pro'); ?></th>
    <th><?php esc_html_e('Period End','tempmail-pro'); ?></th>
    <th><?php esc_html_e('Actions','tempmail-pro'); ?></th>
</tr></thead>
<tbody>
<?php foreach($subs as $s): ?>
<tr>
    <td>
        <strong><?php echo esc_html($s->display_name); ?></strong><br>
        <span style="color:#64748b;font-size:11px;"><?php echo esc_html($s->user_email); ?></span>
    </td>
    <td><span class="tmpmp-badge tmpmp-badge--purple"><?php echo esc_html($s->plan_name); ?></span></td>
    <td><code><?php echo esc_html(ucfirst($s->gateway)); ?></code></td>
    <td><?php echo esc_html(ucfirst($s->billing_cycle)); ?></td>
    <td><strong>$<?php echo number_format($s->amount, 2); ?></strong></td>
    <td>
        <span class="tmpmp-badge <?php echo $s->status === 'active' ? 'tmpmp-badge--green' : 'tmpmp-badge--red'; ?>">
            <?php echo esc_html(ucfirst($s->status)); ?>
        </span>
    </td>
    <td style="font-size:12px;color:#64748b;">
        <?php echo $s->current_period_end ? esc_html(date_i18n(get_option('date_format'), strtotime($s->current_period_end))) : '—'; ?>
    </td>
    <td>
        <button class="button button-small tmpmp-cancel-user-sub" data-uid="<?php echo intval($s->user_id); ?>" style="color:#ef4444;">
            ✗ <?php esc_html_e('Cancel','tempmail-pro'); ?>
        </button>
    </td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
<?php endif; ?>
</div>

<!-- Blocked IPs -->
<div class="tmpmp-card">
<h2 class="tmpmp-card-title">🚫 <?php esc_html_e('Blocked IP Addresses','tempmail-pro'); ?></h2>
<div style="margin-bottom:12px;">
    <button class="button button-secondary tmpmp-ban-ip">🚫 <?php esc_html_e('Ban an IP','tempmail-pro'); ?></button>
</div>
<?php if(empty($blocked)): ?>
<p style="color:#64748b;"><?php esc_html_e('No IPs blocked.','tempmail-pro'); ?></p>
<?php else: ?>
<table class="widefat striped" style="font-size:13px;">
<thead><tr>
    <th><?php esc_html_e('IP Address','tempmail-pro'); ?></th>
    <th><?php esc_html_e('Reason','tempmail-pro'); ?></th>
    <th><?php esc_html_e('Blocked At','tempmail-pro'); ?></th>
    <th><?php esc_html_e('Actions','tempmail-pro'); ?></th>
</tr></thead>
<tbody>
<?php foreach($blocked as $b): ?>
<tr>
    <td><code><?php echo esc_html($b->ip_address); ?></code></td>
    <td><?php echo esc_html($b->reason ?: '—'); ?></td>
    <td style="color:#64748b;"><?php echo esc_html(date_i18n(get_option('date_format'), strtotime($b->blocked_at))); ?></td>
    <td>
        <button class="button button-small tmpmp-unban-ip" data-ip="<?php echo esc_attr($b->ip_address); ?>">
            ✓ <?php esc_html_e('Unban','tempmail-pro'); ?>
        </button>
    </td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
<?php endif; ?>
</div>
</div>
