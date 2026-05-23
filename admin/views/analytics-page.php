<?php if ( ! defined('ABSPATH') ) exit; ?>
<div class="wrap tmpmp-admin-wrap">
<h1 class="tmpmp-admin-title"><span class="dashicons dashicons-chart-bar"></span> <?php esc_html_e('Analytics','tempmail-pro'); ?> <span class="tmpmp-version-pill">v<?php echo esc_html(TMPMP_VERSION); ?></span></h1>

<!-- KPI Cards -->
<div class="tmpmp-stats-grid" style="margin-bottom:24px;">
    <div class="tmpmp-stat-card">
        <div class="tmpmp-stat-icon" style="background:linear-gradient(135deg,#6366f1,#8b5cf6);">📧</div>
        <div class="tmpmp-stat-value"><?php echo number_format($stats['total_emails'] ?? 0); ?></div>
        <div class="tmpmp-stat-label"><?php esc_html_e('Total Emails','tempmail-pro'); ?></div>
    </div>
    <div class="tmpmp-stat-card">
        <div class="tmpmp-stat-icon" style="background:linear-gradient(135deg,#10b981,#059669);">📬</div>
        <div class="tmpmp-stat-value"><?php echo number_format($stats['emails_today'] ?? 0); ?></div>
        <div class="tmpmp-stat-label"><?php esc_html_e('Emails Today','tempmail-pro'); ?></div>
    </div>
    <div class="tmpmp-stat-card">
        <div class="tmpmp-stat-icon" style="background:linear-gradient(135deg,#f59e0b,#d97706);">💰</div>
        <div class="tmpmp-stat-value">$<?php echo number_format($stats['total_revenue'] ?? 0, 2); ?></div>
        <div class="tmpmp-stat-label"><?php esc_html_e('Total Revenue','tempmail-pro'); ?></div>
    </div>
    <div class="tmpmp-stat-card">
        <div class="tmpmp-stat-icon" style="background:linear-gradient(135deg,#ec4899,#db2777);">👑</div>
        <div class="tmpmp-stat-value"><?php echo number_format($stats['premium_users'] ?? 0); ?></div>
        <div class="tmpmp-stat-label"><?php esc_html_e('Premium Users','tempmail-pro'); ?></div>
    </div>
</div>

<!-- Charts Row -->
<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:24px;">
    <div class="tmpmp-card">
        <h3 class="tmpmp-card-title">📧 <?php esc_html_e('Emails Received (30 days)','tempmail-pro'); ?></h3>
        <canvas id="tmpmp-emails-chart" height="200"></canvas>
    </div>
    <div class="tmpmp-card">
        <h3 class="tmpmp-card-title">💰 <?php esc_html_e('Revenue (30 days)','tempmail-pro'); ?></h3>
        <canvas id="tmpmp-revenue-chart" height="200"></canvas>
    </div>
</div>

<!-- Top Domains Table -->
<div class="tmpmp-card">
<h3 class="tmpmp-card-title">🌐 <?php esc_html_e('Top Domains by Usage','tempmail-pro'); ?></h3>
<?php if(empty($top_domains)): ?>
<p style="color:#64748b;padding:16px;"><?php esc_html_e('No data yet. Domains will appear here once inboxes are generated.','tempmail-pro'); ?></p>
<?php else: ?>
<table class="widefat striped" style="font-size:13px;">
<thead><tr>
    <th>#</th>
    <th><?php esc_html_e('Domain','tempmail-pro'); ?></th>
    <th><?php esc_html_e('Inboxes Created','tempmail-pro'); ?></th>
    <th><?php esc_html_e('Share','tempmail-pro'); ?></th>
</tr></thead>
<tbody>
<?php
$total_dom = array_sum(array_column($top_domains,'cnt'));
foreach($top_domains as $i => $row):
    $share = $total_dom > 0 ? round($row->cnt/$total_dom*100,1) : 0;
?>
<tr>
    <td><?php echo $i+1; ?></td>
    <td><strong><?php echo esc_html($row->domain); ?></strong></td>
    <td><?php echo number_format($row->cnt); ?></td>
    <td>
        <div style="display:flex;align-items:center;gap:8px;">
            <div style="flex:1;background:#e2e8f0;border-radius:999px;height:8px;">
                <div style="width:<?php echo $share; ?>%;background:linear-gradient(90deg,#6366f1,#8b5cf6);height:8px;border-radius:999px;"></div>
            </div>
            <span style="font-size:11px;color:#64748b;min-width:32px;"><?php echo $share; ?>%</span>
        </div>
    </td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
<?php endif; ?>
</div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function(){
    if(typeof Chart === 'undefined') return;

    const emailLabels = <?php echo json_encode(array_column($emails_chart,'day')); ?>;
    const emailData   = <?php echo json_encode(array_column($emails_chart,'cnt')); ?>;
    const revLabels   = <?php echo json_encode(array_column($revenue_chart,'day')); ?>;
    const revData     = <?php echo json_encode(array_column($revenue_chart,'total')); ?>;

    const chartDefaults = {
        type: 'line',
        options: {
            responsive: true,
            plugins: { legend: { display: false } },
            scales: { y: { beginAtZero: true, grid: { color: 'rgba(0,0,0,.05)' } }, x: { grid: { display: false } } },
            elements: { line: { tension: 0.4 }, point: { radius: 3 } }
        }
    };

    new Chart(document.getElementById('tmpmp-emails-chart'), {
        ...chartDefaults,
        data: {
            labels: emailLabels,
            datasets: [{ data: emailData, borderColor: '#6366f1', backgroundColor: 'rgba(99,102,241,.1)', fill: true }]
        }
    });

    new Chart(document.getElementById('tmpmp-revenue-chart'), {
        ...chartDefaults,
        data: {
            labels: revLabels,
            datasets: [{ data: revData, borderColor: '#10b981', backgroundColor: 'rgba(16,185,129,.1)', fill: true }]
        }
    });
});
</script>
