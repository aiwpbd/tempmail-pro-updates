<?php if ( ! defined('ABSPATH') ) exit; ?>
<div class="wrap tmpmp-admin-wrap">
<h1 class="tmpmp-admin-title"><span class="dashicons dashicons-admin-site"></span> <?php esc_html_e('Domain Management','tempmail-pro'); ?> <span class="tmpmp-version-pill">v<?php echo esc_html(TMPMP_VERSION); ?></span></h1>

<style>
.tmpmp-page-card{background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:20px 24px;margin-bottom:20px;}
.tmpmp-page-section-title{font-size:11px;font-weight:800;letter-spacing:.8px;text-transform:uppercase;color:#6366f1;margin:0 0 16px;}
.tmpmp-page-field{display:grid;grid-template-columns:180px 1fr;gap:12px 20px;align-items:start;padding:14px 0;border-bottom:1px solid #f1f5f9;}
.tmpmp-page-field:last-child{border-bottom:none;}
.tmpmp-page-label{font-size:13px;font-weight:600;color:#334155;padding-top:9px;}
.tmpmp-page-input{width:100%;padding:8px 12px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:13px;font-family:inherit;outline:none;transition:border-color .15s;box-sizing:border-box;}
.tmpmp-page-input:focus{border-color:#6366f1;box-shadow:0 0 0 3px rgba(99,102,241,.1);}
.tmpmp-page-select{padding:8px 12px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:13px;font-family:inherit;outline:none;cursor:pointer;background:#fff;min-width:160px;}
.tmpmp-page-select:focus{border-color:#6366f1;}
.tmpmp-page-hint{font-size:12px;color:#94a3b8;margin-top:6px;}
.tmpmp-page-hint code{background:#f1f5f9;color:#475569;padding:2px 6px;border-radius:4px;font-size:11px;}
.tmpmp-action-bar{display:flex;gap:10px;align-items:center;flex-wrap:wrap;padding:14px 0;border-bottom:1px solid #f1f5f9;}
.tmpmp-action-bar:last-child{border-bottom:none;}
/* Table styling */
.tmpmp-styled-table{width:100%;border-collapse:collapse;font-size:13px;}
.tmpmp-styled-table th{background:#f8fafc;color:#475569;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;padding:10px 14px;text-align:left;border-bottom:2px solid #e2e8f0;}
.tmpmp-styled-table td{padding:12px 14px;border-bottom:1px solid #f1f5f9;color:#334155;vertical-align:middle;}
.tmpmp-styled-table tr:last-child td{border-bottom:none;}
.tmpmp-styled-table tr:hover td{background:#fafbff;}
.tmpmp-icon-btn{display:inline-flex;align-items:center;gap:5px;padding:5px 10px;border-radius:6px;font-size:12px;font-weight:600;cursor:pointer;border:1.5px solid #e2e8f0;background:#fff;transition:all .15s;}
.tmpmp-icon-btn:hover{border-color:#6366f1;color:#6366f1;}
.tmpmp-icon-btn--danger:hover{border-color:#ef4444;color:#ef4444;}
.tmpmp-add-btn{display:inline-flex;align-items:center;gap:8px;padding:9px 18px;background:linear-gradient(135deg,#6366f1,#8b5cf6);color:#fff;border:none;border-radius:8px;font-size:13px;font-weight:700;cursor:pointer;font-family:inherit;transition:all .15s;}
.tmpmp-add-btn:hover{opacity:.9;transform:translateY(-1px);box-shadow:0 4px 12px rgba(99,102,241,.3);}
.tmpmp-empty-row td{text-align:center;padding:32px!important;color:#94a3b8;}
@media(max-width:600px){.tmpmp-page-field{grid-template-columns:1fr;gap:6px;}.tmpmp-page-label{padding-top:0;}.tmpmp-page-card{padding:16px 14px;}}
</style>

<!-- ① Add Domain -->
<div class="tmpmp-page-card">
    <p class="tmpmp-page-section-title">➕ <?php esc_html_e('Add New Domain','tempmail-pro'); ?></p>

    <div class="tmpmp-page-field">
        <label class="tmpmp-page-label" for="tmpmp-new-domain"><?php esc_html_e('Domain Name','tempmail-pro'); ?></label>
        <div>
            <input type="text" id="tmpmp-new-domain" class="tmpmp-page-input" placeholder="mail.example.com">
        </div>
    </div>

    <div class="tmpmp-page-field">
        <label class="tmpmp-page-label" for="tmpmp-new-category"><?php esc_html_e('Category','tempmail-pro'); ?></label>
        <div>
            <select id="tmpmp-new-category" class="tmpmp-page-select">
                <option value="free">🆓 Free</option>
                <option value="premium">⭐ Premium</option>
                <option value="vip">💎 VIP</option>
            </select>
        </div>
    </div>

    <div class="tmpmp-page-field">
        <label class="tmpmp-page-label"><?php esc_html_e('Webhook URL','tempmail-pro'); ?></label>
        <div>
            <p class="tmpmp-page-hint">
                <?php esc_html_e('Ensure your domain\'s MX record points to your mail server. Webhook endpoint:','tempmail-pro'); ?><br>
                <code><?php echo esc_url( rest_url('tempmail-pro/v1/receive') ); ?></code>
            </p>
        </div>
    </div>

    <div style="padding-top:16px;">
        <button class="tmpmp-add-btn" id="tmpmp-add-domain-btn">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            <?php esc_html_e('Add Domain','tempmail-pro'); ?>
        </button>
    </div>
</div>

<!-- ② Domains Table -->
<div class="tmpmp-page-card">
    <p class="tmpmp-page-section-title">🌐 <?php esc_html_e('Active Domains','tempmail-pro'); ?></p>
    <div style="overflow-x:auto;">
    <table class="tmpmp-styled-table">
        <thead><tr>
            <th><?php esc_html_e('Domain','tempmail-pro'); ?></th>
            <th><?php esc_html_e('Category','tempmail-pro'); ?></th>
            <th><?php esc_html_e('Emails','tempmail-pro'); ?></th>
            <th><?php esc_html_e('Health','tempmail-pro'); ?></th>
            <th><?php esc_html_e('Active','tempmail-pro'); ?></th>
            <th><?php esc_html_e('Added','tempmail-pro'); ?></th>
            <th><?php esc_html_e('Actions','tempmail-pro'); ?></th>
        </tr></thead>
        <tbody>
        <?php if ( empty($domains) ): ?>
        <tr class="tmpmp-empty-row"><td colspan="7"><?php esc_html_e('No domains yet. Add your first domain above.','tempmail-pro'); ?></td></tr>
        <?php else: foreach($domains as $d): ?>
        <tr id="domain-row-<?php echo intval($d->id); ?>">
            <td><strong><?php echo esc_html($d->domain); ?></strong></td>
            <td>
                <select class="tmpmp-page-select tmpmp-domain-category" data-id="<?php echo intval($d->id); ?>" style="min-width:120px;">
                    <option value="free"    <?php selected($d->category,'free');    ?>>🆓 Free</option>
                    <option value="premium" <?php selected($d->category,'premium'); ?>>⭐ Premium</option>
                    <option value="vip"     <?php selected($d->category,'vip');     ?>>💎 VIP</option>
                </select>
            </td>
            <td><?php echo number_format($d->emails_count); ?></td>
            <td>
                <span class="tmpmp-badge <?php echo $d->health_status === 'healthy' ? 'tmpmp-badge--green' : 'tmpmp-badge--gray'; ?>">
                    <?php echo esc_html(ucfirst($d->health_status ?? 'unknown')); ?>
                </span>
            </td>
            <td>
                <label class="tmpmp-toggle-label">
                    <input type="checkbox" class="tmpmp-domain-status" data-id="<?php echo intval($d->id); ?>" <?php checked($d->is_active, 1); ?>>
                    <span class="tmpmp-toggle-slider"></span>
                </label>
            </td>
            <td style="font-size:12px;color:#64748b;"><?php echo esc_html(date_i18n(get_option('date_format'), strtotime($d->created_at))); ?></td>
            <td>
                <button class="tmpmp-icon-btn tmpmp-icon-btn--danger tmpmp-delete-domain" data-id="<?php echo intval($d->id); ?>">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/><path d="M10 11v6M14 11v6"/></svg>
                    <?php esc_html_e('Delete','tempmail-pro'); ?>
                </button>
            </td>
        </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>
    </div>
</div>

<!-- ③ Category Guide -->
<div class="tmpmp-page-card">
    <p class="tmpmp-page-section-title">📋 <?php esc_html_e('Domain Categories Explained','tempmail-pro'); ?></p>
    <div class="tmpmp-page-field">
        <label class="tmpmp-page-label"><span class="tmpmp-badge tmpmp-badge--gray">🆓 Free</span></label>
        <div style="padding-top:8px;font-size:13px;color:#475569;"><?php esc_html_e('Available to all users including guests. No subscription required.','tempmail-pro'); ?></div>
    </div>
    <div class="tmpmp-page-field">
        <label class="tmpmp-page-label"><span class="tmpmp-badge tmpmp-badge--yellow">⭐ Premium</span></label>
        <div style="padding-top:8px;font-size:13px;color:#475569;"><?php esc_html_e('Available to Starter and above subscribers.','tempmail-pro'); ?></div>
    </div>
    <div class="tmpmp-page-field">
        <label class="tmpmp-page-label"><span class="tmpmp-badge tmpmp-badge--purple">💎 VIP</span></label>
        <div style="padding-top:8px;font-size:13px;color:#475569;"><?php esc_html_e('Available to Pro and Business subscribers only.','tempmail-pro'); ?></div>
    </div>
</div>

</div><!-- /.wrap -->
