<?php if ( ! defined('ABSPATH') ) exit; ?>
<?php
// ── Helper: initials avatar ────────────────────────────────────────────────
function tmpmp_initials_color( string $name ) : string {
    $colors = ['#6366f1','#8b5cf6','#ec4899','#ef4444','#f97316','#22c55e','#06b6d4','#3b82f6'];
    return $colors[ abs( crc32( $name ) ) % count($colors) ];
}
function tmpmp_initials( string $name ) : string {
    $parts = preg_split('/\s+/', trim($name));
    return strtoupper( mb_substr($parts[0],0,1) . (isset($parts[1]) ? mb_substr($parts[1],0,1) : '') );
}
$fmt_date = get_option('date_format');
$nonce    = wp_create_nonce('tempmail_pro_nonce');
$ajax_url = admin_url('admin-ajax.php');
$plans_json = json_encode( array_values($plans) );
?>
<style>
/* ── Users Page Styles ────────────────────────────────────────────── */
.tmpmp-users-stats { display:grid; grid-template-columns:repeat(4,1fr); gap:14px; margin-bottom:22px; }
.tmpmp-stat-card { background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:18px 22px;
    display:flex; align-items:center; gap:14px; box-shadow:0 1px 4px rgba(0,0,0,.04); }
.tmpmp-stat-icon { width:42px; height:42px; border-radius:10px; display:flex; align-items:center;
    justify-content:center; font-size:20px; flex-shrink:0; }
.tmpmp-stat-label { font-size:11px; font-weight:600; color:#94a3b8; text-transform:uppercase; letter-spacing:.5px; }
.tmpmp-stat-value { font-size:26px; font-weight:800; color:#0f172a; line-height:1.1; }

/* Tabs */
.tmpmp-tab-bar { display:flex; gap:2px; margin-bottom:16px; background:#f1f5f9; border-radius:10px; padding:4px; width:fit-content; }
.tmpmp-tab-btn { padding:7px 18px; border:none; background:transparent; border-radius:7px; font-size:13px;
    font-weight:600; color:#64748b; cursor:pointer; transition:all .15s; }
.tmpmp-tab-btn.active { background:#fff; color:#6366f1; box-shadow:0 1px 4px rgba(0,0,0,.08); }

/* Search + filter bar */
.tmpmp-users-toolbar { display:flex; gap:10px; margin-bottom:16px; align-items:center; flex-wrap:wrap; }
.tmpmp-users-search { flex:1; min-width:200px; max-width:340px; position:relative; }
.tmpmp-users-search input { width:100%; padding:8px 12px 8px 34px; border:1px solid #e2e8f0;
    border-radius:8px; font-size:13px; outline:none; }
.tmpmp-users-search input:focus { border-color:#6366f1; box-shadow:0 0 0 3px rgba(99,102,241,.12); }
.tmpmp-users-search .srch-ico { position:absolute; left:10px; top:50%; transform:translateY(-50%);
    color:#94a3b8; font-size:14px; pointer-events:none; }
.tmpmp-filter-sel { padding:8px 12px; border:1px solid #e2e8f0; border-radius:8px;
    font-size:13px; background:#fff; color:#374151; cursor:pointer; outline:none; }
.tmpmp-filter-sel:focus { border-color:#6366f1; }

/* Users table */
.tmpmp-users-table-wrap { overflow-x:auto; }
.tmpmp-users-table { width:100%; border-collapse:collapse; font-size:13px; }
.tmpmp-users-table th { background:#f8fafc; color:#64748b; font-size:11px; font-weight:700;
    text-transform:uppercase; letter-spacing:.5px; padding:10px 14px; text-align:left;
    border-bottom:1px solid #e2e8f0; white-space:nowrap; }
.tmpmp-users-table td { padding:11px 14px; border-bottom:1px solid #f1f5f9; vertical-align:middle; }
.tmpmp-users-table tbody tr:hover { background:#fafbff; }
.tmpmp-users-table tbody tr:last-child td { border-bottom:none; }

/* User cell */
.tmpmp-user-cell { display:flex; align-items:center; gap:10px; }
.tmpmp-avatar-circle { width:36px; height:36px; border-radius:50%; display:flex; align-items:center;
    justify-content:center; font-size:13px; font-weight:700; color:#fff; flex-shrink:0; }
.tmpmp-user-name { font-weight:600; color:#0f172a; line-height:1.2; }
.tmpmp-user-email { font-size:11px; color:#94a3b8; }

/* Badges */
.tmpmp-plan-badge { display:inline-flex; align-items:center; gap:4px; padding:3px 10px;
    border-radius:20px; font-size:11px; font-weight:700; white-space:nowrap; }
.tmpmp-plan-free { background:#f1f5f9; color:#64748b; }
.tmpmp-plan-premium { background:linear-gradient(135deg,#6366f1,#8b5cf6); color:#fff; }
.tmpmp-plan-starter { background:#dbeafe; color:#1d4ed8; }
.tmpmp-plan-pro { background:#ede9fe; color:#6d28d9; }
.tmpmp-plan-business { background:#fef3c7; color:#92400e; }
.tmpmp-status-active { background:#dcfce7; color:#166534; padding:2px 8px; border-radius:10px;
    font-size:11px; font-weight:600; }
.tmpmp-status-cancelled { background:#fee2e2; color:#991b1b; padding:2px 8px; border-radius:10px;
    font-size:11px; font-weight:600; }
.tmpmp-status-free { background:#f1f5f9; color:#64748b; padding:2px 8px; border-radius:10px;
    font-size:11px; font-weight:600; }

/* Action buttons */
.tmpmp-act-btn { padding:4px 10px; border-radius:6px; font-size:12px; font-weight:600;
    cursor:pointer; border:1px solid transparent; transition:all .12s; line-height:1.4; }
.tmpmp-act-edit { background:#eff6ff; color:#2563eb; border-color:#bfdbfe; }
.tmpmp-act-edit:hover { background:#dbeafe; }
.tmpmp-act-delete { background:#fff1f2; color:#e11d48; border-color:#fecdd3; }
.tmpmp-act-delete:hover { background:#fee2e2; }
.tmpmp-act-cancel { background:#fff7ed; color:#c2410c; border-color:#fed7aa; }
.tmpmp-act-cancel:hover { background:#ffedd5; }

/* Empty state */
.tmpmp-empty { padding:40px; text-align:center; color:#94a3b8; font-size:14px; }

/* ── Edit User Modal ─────────────────────────────────────────────── */
#tmpmp-user-modal-overlay {
    display:none; position:fixed; inset:0; background:rgba(15,23,42,.45);
    z-index:99999; backdrop-filter:blur(3px); align-items:center; justify-content:center;
}
#tmpmp-user-modal-overlay.show { display:flex; }
#tmpmp-user-modal {
    background:#fff; border-radius:16px; box-shadow:0 20px 60px rgba(0,0,0,.18);
    width:680px; max-width:95vw; max-height:88vh; display:flex; flex-direction:column;
    overflow:hidden; animation:modalIn .2s ease;
}
@keyframes modalIn { from{opacity:0;transform:scale(.96) translateY(10px)} to{opacity:1;transform:none} }
.tmpmp-modal-header {
    padding:20px 24px 16px; border-bottom:1px solid #f1f5f9;
    display:flex; align-items:center; gap:14px; flex-shrink:0;
}
.tmpmp-modal-avatar { width:52px; height:52px; border-radius:50%; display:flex; align-items:center;
    justify-content:center; font-size:18px; font-weight:800; color:#fff; flex-shrink:0; }
.tmpmp-modal-title { font-size:18px; font-weight:800; color:#0f172a; }
.tmpmp-modal-subtitle { font-size:12px; color:#94a3b8; }
.tmpmp-modal-close { margin-left:auto; background:none; border:none; font-size:20px; color:#94a3b8;
    cursor:pointer; padding:4px 8px; border-radius:6px; line-height:1; }
.tmpmp-modal-close:hover { background:#f1f5f9; color:#374151; }

/* Modal tabs */
.tmpmp-modal-tabs { display:flex; gap:0; border-bottom:1px solid #f1f5f9; flex-shrink:0; padding:0 24px; }
.tmpmp-modal-tab { padding:12px 16px; font-size:13px; font-weight:600; color:#94a3b8;
    cursor:pointer; border-bottom:2px solid transparent; transition:all .15s; }
.tmpmp-modal-tab.active { color:#6366f1; border-bottom-color:#6366f1; }

/* Modal body */
.tmpmp-modal-body { padding:22px 24px; overflow-y:auto; flex:1; }
.tmpmp-modal-panel { display:none; }
.tmpmp-modal-panel.active { display:block; }

/* Form fields inside modal */
.tmpmp-modal-row { display:grid; grid-template-columns:1fr 1fr; gap:14px; margin-bottom:14px; }
.tmpmp-modal-row.full { grid-template-columns:1fr; }
.tmpmp-modal-field label { display:block; font-size:12px; font-weight:600; color:#374151;
    margin-bottom:5px; }
.tmpmp-modal-field input, .tmpmp-modal-field select {
    width:100%; padding:9px 12px; border:1px solid #e2e8f0; border-radius:8px;
    font-size:13px; outline:none; transition:border .15s; box-sizing:border-box;
}
.tmpmp-modal-field input:focus, .tmpmp-modal-field select:focus {
    border-color:#6366f1; box-shadow:0 0 0 3px rgba(99,102,241,.12);
}
.tmpmp-modal-section-title { font-size:13px; font-weight:700; color:#374151; margin:18px 0 10px;
    padding-bottom:6px; border-bottom:1px solid #f1f5f9; }
.tmpmp-plan-info-grid { display:grid; grid-template-columns:1fr 1fr; gap:10px; margin-bottom:14px; }
.tmpmp-plan-info-item { background:#f8fafc; border-radius:8px; padding:10px 12px; }
.tmpmp-plan-info-item .pii-label { font-size:11px; color:#94a3b8; font-weight:600; text-transform:uppercase; letter-spacing:.4px; }
.tmpmp-plan-info-item .pii-value { font-size:14px; font-weight:700; color:#0f172a; margin-top:2px; }

/* Activity table */
.tmpmp-mini-table { width:100%; font-size:12px; border-collapse:collapse; }
.tmpmp-mini-table th { background:#f8fafc; color:#64748b; font-size:10px; font-weight:700;
    text-transform:uppercase; padding:6px 10px; border-bottom:1px solid #e2e8f0; text-align:left; }
.tmpmp-mini-table td { padding:7px 10px; border-bottom:1px solid #f1f5f9; color:#374151; }
.tmpmp-mini-table tbody tr:last-child td { border-bottom:none; }

/* Modal footer */
.tmpmp-modal-footer { padding:16px 24px; border-top:1px solid #f1f5f9; display:flex;
    align-items:center; gap:10px; flex-shrink:0; }
.tmpmp-modal-msg { flex:1; font-size:13px; font-weight:500; }
.tmpmp-modal-msg.ok { color:#16a34a; }
.tmpmp-modal-msg.err { color:#dc2626; }
.tmpmp-modal-btn { padding:9px 20px; border-radius:8px; font-size:13px; font-weight:700;
    cursor:pointer; border:none; transition:all .15s; }
.tmpmp-modal-btn-primary { background:#6366f1; color:#fff; }
.tmpmp-modal-btn-primary:hover { background:#4f46e5; }
.tmpmp-modal-btn-secondary { background:#f1f5f9; color:#374151; border:1px solid #e2e8f0; }
.tmpmp-modal-btn-secondary:hover { background:#e2e8f0; }
.tmpmp-modal-btn-danger { background:#fee2e2; color:#dc2626; border:1px solid #fecaca; }
.tmpmp-modal-btn-danger:hover { background:#fecaca; }

/* Danger zone */
.tmpmp-danger-zone { border:1px solid #fecaca; border-radius:10px; padding:14px 16px; margin-top:18px; background:#fff5f5; }
.tmpmp-danger-zone h4 { color:#dc2626; font-size:13px; font-weight:700; margin:0 0 6px; }
.tmpmp-danger-zone p { font-size:12px; color:#7f1d1d; margin:0 0 10px; }

/* ── Password Generator ───────────────────────────────────────────── */
.tmpmp-pass-input-wrap { position:relative; display:flex; align-items:center; }
.tmpmp-pass-input-wrap input { padding-right:40px !important; }
.tmpmp-pass-eye {
    position:absolute; right:10px; background:none; border:none; cursor:pointer;
    font-size:16px; color:#94a3b8; padding:0; line-height:1; transition:color .15s;
}
.tmpmp-pass-eye:hover { color:#374151; }
.tmpmp-pass-actions { display:flex; align-items:center; gap:8px; margin-top:7px; flex-wrap:wrap; }
.tmpmp-gen-pass-btn {
    display:inline-flex; align-items:center; gap:5px;
    padding:5px 12px; background:#f5f3ff; color:#6d28d9;
    border:1px solid #ddd6fe; border-radius:7px; font-size:12px; font-weight:700;
    cursor:pointer; transition:all .15s; white-space:nowrap;
}
.tmpmp-gen-pass-btn:hover { background:#ede9fe; border-color:#c4b5fd; }
.tmpmp-pass-copy-btn {
    background:#f8fafc; border:1px solid #e2e8f0; border-radius:7px;
    padding:5px 10px; cursor:pointer; font-size:12px; color:#64748b;
    transition:all .15s; white-space:nowrap;
}
.tmpmp-pass-copy-btn:hover { background:#f1f5f9; color:#0f172a; }
.tmpmp-pass-strength-wrap { display:flex; align-items:center; gap:5px; }
.tmpmp-pass-strength-bars { display:flex; gap:3px; }
.tmpmp-pass-strength-bars span {
    display:block; width:22px; height:4px; border-radius:2px;
    background:#e2e8f0; transition:background .2s;
}
.tmpmp-pass-strength-label { font-size:11px; font-weight:700; }

/* Responsive */
@media(max-width:640px) {
    .tmpmp-users-stats { grid-template-columns:1fr 1fr; }
    .tmpmp-modal-row { grid-template-columns:1fr; }
    .tmpmp-plan-info-grid { grid-template-columns:1fr; }
}
</style>

<div class="wrap tmpmp-admin-wrap">
<h1 class="tmpmp-admin-title">
    <span class="dashicons dashicons-groups"></span>
    <?php esc_html_e('Users & Subscriptions','tempmail-pro'); ?>
    <span class="tmpmp-version-pill">v<?php echo esc_html(TMPMP_VERSION); ?></span>
</h1>

<!-- Stats bar -->
<div class="tmpmp-users-stats">
    <div class="tmpmp-stat-card">
        <div class="tmpmp-stat-icon" style="background:#eff6ff;">👥</div>
        <div>
            <div class="tmpmp-stat-label"><?php esc_html_e('Total Users','tempmail-pro'); ?></div>
            <div class="tmpmp-stat-value"><?php echo number_format($total_users); ?></div>
        </div>
    </div>
    <div class="tmpmp-stat-card">
        <div class="tmpmp-stat-icon" style="background:#f5f3ff;">💎</div>
        <div>
            <div class="tmpmp-stat-label"><?php esc_html_e('Premium','tempmail-pro'); ?></div>
            <div class="tmpmp-stat-value" style="color:#6366f1;"><?php echo number_format($premium_count); ?></div>
        </div>
    </div>
    <div class="tmpmp-stat-card">
        <div class="tmpmp-stat-icon" style="background:#f0fdf4;">🆓</div>
        <div>
            <div class="tmpmp-stat-label"><?php esc_html_e('Free Users','tempmail-pro'); ?></div>
            <div class="tmpmp-stat-value" style="color:#16a34a;"><?php echo number_format($free_count); ?></div>
        </div>
    </div>
    <div class="tmpmp-stat-card">
        <div class="tmpmp-stat-icon" style="background:#fefce8;">💰</div>
        <div>
            <div class="tmpmp-stat-label"><?php esc_html_e('Total Revenue','tempmail-pro'); ?></div>
            <div class="tmpmp-stat-value" style="color:#d97706;">$<?php echo number_format($total_revenue, 2); ?></div>
        </div>
    </div>
</div>

<!-- Tabs -->
<div class="tmpmp-tab-bar">
    <button class="tmpmp-tab-btn active" data-tab="all">👥 <?php esc_html_e('All Users','tempmail-pro'); ?> <span id="tab-count-all">(<?php echo $total_users; ?>)</span></button>
    <button class="tmpmp-tab-btn" data-tab="premium">💎 <?php esc_html_e('Premium','tempmail-pro'); ?> <span id="tab-count-premium">(<?php echo $premium_count; ?>)</span></button>
    <button class="tmpmp-tab-btn" data-tab="free">🆓 <?php esc_html_e('Free','tempmail-pro'); ?> <span id="tab-count-free">(<?php echo $free_count; ?>)</span></button>
    <button class="tmpmp-tab-btn" data-tab="blocked">🚫 <?php esc_html_e('Blocked IPs','tempmail-pro'); ?> <span>(<?php echo count($blocked); ?>)</span></button>
</div>

<!-- Users Panel -->
<div id="tmpmp-panel-all" class="tmpmp-tab-panel" style="display:block;">
<div class="tmpmp-card" style="padding:0;overflow:hidden;">

    <!-- Toolbar -->
    <div class="tmpmp-users-toolbar" style="padding:14px 16px 0;">
        <div class="tmpmp-users-search">
            <span class="srch-ico dashicons dashicons-search"></span>
            <input type="text" id="tmpmp-user-search" placeholder="<?php esc_attr_e('Search name or email…','tempmail-pro'); ?>">
        </div>
        <select id="tmpmp-plan-filter" class="tmpmp-filter-sel">
            <option value=""><?php esc_html_e('All Plans','tempmail-pro'); ?></option>
            <option value="free"><?php esc_html_e('Free (no sub)','tempmail-pro'); ?></option>
            <?php foreach ($plans as $pl): ?>
            <option value="<?php echo esc_attr($pl->slug); ?>"><?php echo esc_html($pl->name); ?></option>
            <?php endforeach; ?>
        </select>
        <select id="tmpmp-status-filter" class="tmpmp-filter-sel">
            <option value=""><?php esc_html_e('All Statuses','tempmail-pro'); ?></option>
            <option value="active"><?php esc_html_e('Active Sub','tempmail-pro'); ?></option>
            <option value="free"><?php esc_html_e('Free','tempmail-pro'); ?></option>
            <option value="cancelled"><?php esc_html_e('Cancelled','tempmail-pro'); ?></option>
        </select>
        <span id="tmpmp-filtered-count" style="font-size:12px;color:#94a3b8;margin-left:auto;"></span>
    </div>

    <!-- Table -->
    <div class="tmpmp-users-table-wrap" style="padding:14px 0 0;">
    <?php if (empty($users)): ?>
    <div class="tmpmp-empty">
        <?php esc_html_e('No users found. Users will appear here once they register.','tempmail-pro'); ?>
    </div>
    <?php else: ?>
    <table class="tmpmp-users-table" id="tmpmp-users-table">
    <thead><tr>
        <th><?php esc_html_e('User','tempmail-pro'); ?></th>
        <th><?php esc_html_e('Plan','tempmail-pro'); ?></th>
        <th><?php esc_html_e('Status','tempmail-pro'); ?></th>
        <th><?php esc_html_e('Inboxes','tempmail-pro'); ?></th>
        <th><?php esc_html_e('Payments','tempmail-pro'); ?></th>
        <th><?php esc_html_e('Spent','tempmail-pro'); ?></th>
        <th><?php esc_html_e('Registered','tempmail-pro'); ?></th>
        <th><?php esc_html_e('Period End','tempmail-pro'); ?></th>
        <th><?php esc_html_e('Actions','tempmail-pro'); ?></th>
    </tr></thead>
    <tbody id="tmpmp-users-tbody">
    <?php foreach ($users as $u):
        $is_premium  = !empty($u->sub_id);
        $plan_slug   = $is_premium ? ($u->plan_slug ?? 'free') : 'free';
        $plan_name   = $is_premium ? ($u->plan_name ?? 'Unknown') : 'Free';
        $sub_status  = $is_premium ? ($u->sub_status ?? 'active') : 'free';
        $initials    = tmpmp_initials($u->display_name ?: $u->user_email);
        $color       = tmpmp_initials_color($u->display_name ?: $u->user_email);
        $plan_cls    = 'tmpmp-plan-' . $plan_slug;
    ?>
    <tr data-uid="<?php echo intval($u->user_id); ?>"
        data-name="<?php echo esc_attr(strtolower($u->display_name)); ?>"
        data-email="<?php echo esc_attr(strtolower($u->user_email)); ?>"
        data-plan="<?php echo esc_attr($plan_slug); ?>"
        data-status="<?php echo esc_attr($sub_status); ?>"
        data-type="<?php echo $is_premium ? 'premium' : 'free'; ?>">
        <td>
            <div class="tmpmp-user-cell">
                <div class="tmpmp-avatar-circle" style="background:<?php echo esc_attr($color); ?>;"><?php echo esc_html($initials); ?></div>
                <div>
                    <div class="tmpmp-user-name"><?php echo esc_html($u->display_name ?: '—'); ?></div>
                    <div class="tmpmp-user-email"><?php echo esc_html($u->user_email); ?></div>
                </div>
            </div>
        </td>
        <td>
            <span class="tmpmp-plan-badge <?php echo esc_attr($plan_cls); ?>">
                <?php echo $is_premium ? '💎' : ''; ?> <?php echo esc_html($plan_name); ?>
            </span>
        </td>
        <td>
            <?php if ($sub_status === 'active'): ?>
                <span class="tmpmp-status-active">✓ Active</span>
            <?php elseif ($sub_status === 'cancelled'): ?>
                <span class="tmpmp-status-cancelled">✗ Cancelled</span>
            <?php else: ?>
                <span class="tmpmp-status-free">— Free</span>
            <?php endif; ?>
        </td>
        <td><strong><?php echo intval($u->address_count); ?></strong></td>
        <td><?php echo intval($u->payment_count); ?></td>
        <td><?php echo $u->total_spent > 0 ? '<strong>$'.number_format($u->total_spent,2).'</strong>' : '<span style="color:#94a3b8">$0.00</span>'; ?></td>
        <td style="color:#64748b;font-size:12px;"><?php echo esc_html(date_i18n($fmt_date, strtotime($u->user_registered))); ?></td>
        <td style="font-size:12px;color:#64748b;">
            <?php echo $u->current_period_end ? esc_html(date_i18n($fmt_date, strtotime($u->current_period_end))) : '<span style="color:#cbd5e1;">—</span>'; ?>
        </td>
        <td>
            <div style="display:flex;gap:5px;flex-wrap:wrap;">
                <button class="tmpmp-act-btn tmpmp-act-edit tmpmp-open-user-modal"
                    data-uid="<?php echo intval($u->user_id); ?>"
                    data-name="<?php echo esc_attr($u->display_name); ?>"
                    data-email="<?php echo esc_attr($u->user_email); ?>"
                    data-color="<?php echo esc_attr($color); ?>"
                    data-initials="<?php echo esc_attr($initials); ?>"
                    data-plan-slug="<?php echo esc_attr($plan_slug); ?>"
                    data-plan-id="<?php echo intval($u->sub_plan_id ?? 0); ?>"
                    data-sub-id="<?php echo intval($u->sub_id ?? 0); ?>"
                    data-sub-status="<?php echo esc_attr($sub_status); ?>"
                    data-billing="<?php echo esc_attr($u->billing_cycle ?? ''); ?>"
                    data-amount="<?php echo esc_attr($u->sub_amount ?? ''); ?>"
                    data-currency="<?php echo esc_attr($u->currency ?? 'USD'); ?>"
                    data-gateway="<?php echo esc_attr($u->gateway ?? ''); ?>"
                    data-period-end="<?php echo esc_attr($u->current_period_end ?? ''); ?>"
                    data-registered="<?php echo esc_attr($u->user_registered); ?>"
                    data-inboxes="<?php echo intval($u->address_count); ?>"
                    data-payments="<?php echo intval($u->payment_count); ?>"
                    data-spent="<?php echo esc_attr($u->total_spent ?? 0); ?>">
                    ✏️ <?php esc_html_e('Edit','tempmail-pro'); ?>
                </button>
                <?php if ($is_premium && $sub_status === 'active'): ?>
                <button class="tmpmp-act-btn tmpmp-act-cancel tmpmp-cancel-user-sub"
                    data-uid="<?php echo intval($u->user_id); ?>">
                    ✗ <?php esc_html_e('Cancel','tempmail-pro'); ?>
                </button>
                <?php endif; ?>
            </div>
        </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
    </table>
    <?php endif; ?>
    </div>
    <div id="tmpmp-table-empty-msg" class="tmpmp-empty" style="display:none;"><?php esc_html_e('No users match your search.','tempmail-pro'); ?></div>
</div>
</div><!-- /panel all -->

<!-- Blocked IPs Panel -->
<div id="tmpmp-panel-blocked" class="tmpmp-tab-panel" style="display:none;">
<div class="tmpmp-card">
    <h2 class="tmpmp-card-title">🚫 <?php esc_html_e('Blocked IP Addresses','tempmail-pro'); ?></h2>
    <div style="margin-bottom:12px;">
        <button class="button button-secondary tmpmp-ban-ip">🚫 <?php esc_html_e('Ban an IP','tempmail-pro'); ?></button>
    </div>
    <?php if (empty($blocked)): ?>
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
    <?php foreach ($blocked as $b): ?>
    <tr>
        <td><code><?php echo esc_html($b->ip_address); ?></code></td>
        <td><?php echo esc_html($b->reason ?: '—'); ?></td>
        <td style="color:#64748b;"><?php echo esc_html(date_i18n($fmt_date, strtotime($b->blocked_at))); ?></td>
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
</div><!-- /panel blocked -->
</div><!-- .wrap -->

<!-- ── Edit User Modal ─────────────────────────────────────────────────────── -->
<div id="tmpmp-user-modal-overlay">
<div id="tmpmp-user-modal">

    <!-- Header -->
    <div class="tmpmp-modal-header">
        <div id="modal-avatar" class="tmpmp-modal-avatar">U</div>
        <div>
            <div id="modal-title" class="tmpmp-modal-title">User Name</div>
            <div id="modal-subtitle" class="tmpmp-modal-subtitle">email@example.com · ID #0</div>
        </div>
        <button class="tmpmp-modal-close" id="tmpmp-modal-close">✕</button>
    </div>

    <!-- Tabs -->
    <div class="tmpmp-modal-tabs">
        <div class="tmpmp-modal-tab active" data-mtab="profile">👤 <?php esc_html_e('Profile','tempmail-pro'); ?></div>
        <div class="tmpmp-modal-tab" data-mtab="plan">💎 <?php esc_html_e('Plan & Subscription','tempmail-pro'); ?></div>
        <div class="tmpmp-modal-tab" data-mtab="activity">📊 <?php esc_html_e('Activity','tempmail-pro'); ?></div>
    </div>

    <!-- Body -->
    <div class="tmpmp-modal-body">

        <!-- Profile Panel -->
        <div id="modal-panel-profile" class="tmpmp-modal-panel active">
            <div class="tmpmp-modal-row">
                <div class="tmpmp-modal-field">
                    <label><?php esc_html_e('Display Name','tempmail-pro'); ?></label>
                    <input type="text" id="modal-display-name" placeholder="<?php esc_attr_e('Display Name','tempmail-pro'); ?>">
                </div>
                <div class="tmpmp-modal-field">
                    <label><?php esc_html_e('Email Address','tempmail-pro'); ?></label>
                    <input type="email" id="modal-email" placeholder="user@example.com">
                </div>
            </div>
            <div class="tmpmp-modal-row full">
                <div class="tmpmp-modal-field">
                    <label><?php esc_html_e('New Password','tempmail-pro'); ?> <span style="color:#94a3b8;font-weight:400;">(<?php esc_html_e('leave blank to keep current','tempmail-pro'); ?>)</span></label>
                    <div class="tmpmp-pass-input-wrap">
                        <input type="password" id="modal-password" placeholder="<?php esc_attr_e('Min 8 characters…','tempmail-pro'); ?>" minlength="8" autocomplete="new-password">
                        <button type="button" class="tmpmp-pass-eye" data-target="modal-password" title="<?php esc_attr_e('Show / Hide','tempmail-pro'); ?>">👁</button>
                    </div>
                    <div class="tmpmp-pass-actions">
                        <button type="button" class="tmpmp-gen-pass-btn" data-targets="modal-password">🔑 <?php esc_html_e('Generate Password','tempmail-pro'); ?></button>
                        <div class="tmpmp-pass-strength-wrap" id="modal-password-sw" style="display:none;">
                            <div class="tmpmp-pass-strength-bars"><span></span><span></span><span></span><span></span><span></span></div>
                            <span class="tmpmp-pass-strength-label"></span>
                        </div>
                        <button type="button" class="tmpmp-pass-copy-btn" id="modal-password-copy" data-target="modal-password" style="display:none;">📋 <?php esc_html_e('Copy','tempmail-pro'); ?></button>
                    </div>
                </div>
            </div>
            <div class="tmpmp-modal-section-title"><?php esc_html_e('Quick Info','tempmail-pro'); ?></div>
            <div class="tmpmp-plan-info-grid" id="modal-quick-info">
                <div class="tmpmp-plan-info-item">
                    <div class="pii-label"><?php esc_html_e('User ID','tempmail-pro'); ?></div>
                    <div class="pii-value" id="qi-uid">—</div>
                </div>
                <div class="tmpmp-plan-info-item">
                    <div class="pii-label"><?php esc_html_e('Registered','tempmail-pro'); ?></div>
                    <div class="pii-value" id="qi-reg">—</div>
                </div>
                <div class="tmpmp-plan-info-item">
                    <div class="pii-label"><?php esc_html_e('Temp Inboxes','tempmail-pro'); ?></div>
                    <div class="pii-value" id="qi-inboxes">0</div>
                </div>
                <div class="tmpmp-plan-info-item">
                    <div class="pii-label"><?php esc_html_e('Payments Made','tempmail-pro'); ?></div>
                    <div class="pii-value" id="qi-payments">0</div>
                </div>
            </div>
            <div style="margin-top:12px;display:flex;gap:8px;flex-wrap:wrap;">
                <a id="modal-wp-edit-link" href="#" target="_blank" class="button button-small">
                    ⚙️ <?php esc_html_e('Edit in WP Admin','tempmail-pro'); ?>
                </a>
            </div>
        </div>

        <!-- Plan Panel -->
        <div id="modal-panel-plan" class="tmpmp-modal-panel">

            <!-- Current sub info -->
            <div class="tmpmp-modal-section-title"><?php esc_html_e('Current Subscription','tempmail-pro'); ?></div>
            <div class="tmpmp-plan-info-grid" id="modal-sub-info">
                <div class="tmpmp-plan-info-item">
                    <div class="pii-label"><?php esc_html_e('Plan','tempmail-pro'); ?></div>
                    <div class="pii-value" id="si-plan">Free</div>
                </div>
                <div class="tmpmp-plan-info-item">
                    <div class="pii-label"><?php esc_html_e('Status','tempmail-pro'); ?></div>
                    <div class="pii-value" id="si-status">—</div>
                </div>
                <div class="tmpmp-plan-info-item">
                    <div class="pii-label"><?php esc_html_e('Gateway','tempmail-pro'); ?></div>
                    <div class="pii-value" id="si-gateway">—</div>
                </div>
                <div class="tmpmp-plan-info-item">
                    <div class="pii-label"><?php esc_html_e('Billing Cycle','tempmail-pro'); ?></div>
                    <div class="pii-value" id="si-cycle">—</div>
                </div>
                <div class="tmpmp-plan-info-item">
                    <div class="pii-label"><?php esc_html_e('Amount','tempmail-pro'); ?></div>
                    <div class="pii-value" id="si-amount">—</div>
                </div>
                <div class="tmpmp-plan-info-item">
                    <div class="pii-label"><?php esc_html_e('Period End','tempmail-pro'); ?></div>
                    <div class="pii-value" id="si-period-end">—</div>
                </div>
                <div class="tmpmp-plan-info-item">
                    <div class="pii-label"><?php esc_html_e('Total Spent','tempmail-pro'); ?></div>
                    <div class="pii-value" id="si-spent">$0.00</div>
                </div>
            </div>

            <!-- Manual plan assignment -->
            <div class="tmpmp-modal-section-title"><?php esc_html_e('Assign / Change Plan','tempmail-pro'); ?></div>
            <div class="tmpmp-modal-row">
                <div class="tmpmp-modal-field">
                    <label><?php esc_html_e('Plan','tempmail-pro'); ?></label>
                    <select id="modal-plan-id">
                        <option value="free"><?php esc_html_e('— Free (no subscription) —','tempmail-pro'); ?></option>
                        <?php foreach ($plans as $pl): if ($pl->slug === 'free') continue; ?>
                        <option value="<?php echo intval($pl->id); ?>" data-monthly="<?php echo esc_attr($pl->price_monthly); ?>" data-yearly="<?php echo esc_attr($pl->price_yearly); ?>">
                            <?php echo esc_html($pl->name); ?> (<?php echo $pl->slug; ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="tmpmp-modal-field" id="modal-billing-wrap">
                    <label><?php esc_html_e('Billing Cycle','tempmail-pro'); ?></label>
                    <select id="modal-billing-cycle">
                        <option value="monthly"><?php esc_html_e('Monthly','tempmail-pro'); ?></option>
                        <option value="yearly"><?php esc_html_e('Yearly','tempmail-pro'); ?></option>
                    </select>
                </div>
            </div>
            <div class="tmpmp-modal-row" id="modal-period-row">
                <div class="tmpmp-modal-field">
                    <label><?php esc_html_e('Amount (USD)','tempmail-pro'); ?></label>
                    <input type="number" id="modal-plan-amount" step="0.01" min="0" placeholder="0.00">
                </div>
                <div class="tmpmp-modal-field">
                    <label><?php esc_html_e('Period End Date','tempmail-pro'); ?></label>
                    <input type="date" id="modal-period-end">
                </div>
            </div>
            <button id="modal-save-plan" class="button button-primary">
                💎 <?php esc_html_e('Save Plan Change','tempmail-pro'); ?>
            </button>

            <!-- Danger: cancel sub -->
            <div id="modal-cancel-zone" class="tmpmp-danger-zone" style="display:none;">
                <h4>⚠️ <?php esc_html_e('Cancel Subscription','tempmail-pro'); ?></h4>
                <p><?php esc_html_e('Cancelling will immediately downgrade this user to the Free plan.','tempmail-pro'); ?></p>
                <button id="modal-cancel-sub" class="button" style="color:#dc2626;border-color:#fca5a5;">
                    ✗ <?php esc_html_e('Cancel Subscription','tempmail-pro'); ?>
                </button>
            </div>
        </div>

        <!-- Activity Panel -->
        <div id="modal-panel-activity" class="tmpmp-modal-panel">
            <div class="tmpmp-modal-section-title"><?php esc_html_e('Recent Temp Inboxes','tempmail-pro'); ?></div>
            <div id="modal-addresses-wrap">
                <p style="color:#94a3b8;font-size:13px;"><?php esc_html_e('Loading…','tempmail-pro'); ?></p>
            </div>
            <div class="tmpmp-modal-section-title" style="margin-top:20px;"><?php esc_html_e('Payment History','tempmail-pro'); ?></div>
            <div id="modal-payments-wrap">
                <p style="color:#94a3b8;font-size:13px;"><?php esc_html_e('Loading…','tempmail-pro'); ?></p>
            </div>

            <!-- Danger: delete user -->
            <div class="tmpmp-danger-zone" style="margin-top:22px;">
                <h4>🗑️ <?php esc_html_e('Delete User','tempmail-pro'); ?></h4>
                <p><?php esc_html_e('This permanently deletes the user account and ALL their data (inboxes, emails, payments, API keys). This cannot be undone.','tempmail-pro'); ?></p>
                <button id="modal-delete-user" class="tmpmp-modal-btn tmpmp-modal-btn-danger">
                    🗑️ <?php esc_html_e('Delete User Permanently','tempmail-pro'); ?>
                </button>
            </div>
        </div>

    </div><!-- /modal-body -->

    <!-- Footer -->
    <div class="tmpmp-modal-footer">
        <div id="modal-msg" class="tmpmp-modal-msg"></div>
        <button class="tmpmp-modal-btn tmpmp-modal-btn-secondary" id="modal-btn-cancel"><?php esc_html_e('Close','tempmail-pro'); ?></button>
        <button class="tmpmp-modal-btn tmpmp-modal-btn-primary" id="modal-btn-save">💾 <?php esc_html_e('Save Profile','tempmail-pro'); ?></button>
    </div>

</div><!-- /modal -->
</div><!-- /overlay -->

<script>
jQuery(function($) {
    const nonce    = '<?php echo esc_js($nonce); ?>';
    const ajaxUrl  = '<?php echo esc_js($ajax_url); ?>';
    const dateOpts = { year:'numeric', month:'short', day:'numeric' };
    const plansData = <?php echo $plans_json; ?>;

    // ── Tab switching ────────────────────────────────────────────────
    let activeTab = 'all';
    $('.tmpmp-tab-btn').on('click', function() {
        const tab = $(this).data('tab');
        activeTab = tab;
        $('.tmpmp-tab-btn').removeClass('active');
        $(this).addClass('active');
        $('.tmpmp-tab-panel').hide();
        if (tab === 'blocked') {
            $('#tmpmp-panel-blocked').show();
        } else {
            $('#tmpmp-panel-all').show();
            filterTable();
        }
    });

    // ── Client-side search + filter ──────────────────────────────────
    function filterTable() {
        const q      = ($('#tmpmp-user-search').val() || '').toLowerCase().trim();
        const plan   = $('#tmpmp-plan-filter').val();
        const status = $('#tmpmp-status-filter').val();
        const tab    = activeTab; // all | premium | free

        let visible = 0;
        $('#tmpmp-users-tbody tr').each(function() {
            const $tr    = $(this);
            const name   = $tr.data('name') || '';
            const email  = $tr.data('email') || '';
            const tp     = $tr.data('type') || '';     // free|premium
            const pl     = $tr.data('plan') || '';     // slug
            const st     = $tr.data('status') || '';   // active|free|cancelled

            let show = true;
            if (q && !name.includes(q) && !email.includes(q)) show = false;
            if (plan) {
                if (plan === 'free' && tp !== 'free') show = false;
                else if (plan !== 'free' && pl !== plan) show = false;
            }
            if (status) {
                if (status === 'free' && st !== 'free') show = false;
                else if (status === 'active' && st !== 'active') show = false;
                else if (status === 'cancelled' && st !== 'cancelled') show = false;
            }
            if (tab === 'premium' && tp !== 'premium') show = false;
            if (tab === 'free'    && tp !== 'free')    show = false;

            $tr.toggle(show);
            if (show) visible++;
        });

        const total = $('#tmpmp-users-tbody tr').length;
        $('#tmpmp-filtered-count').text(visible === total ? '' : visible + ' / ' + total + ' <?php esc_html_e('shown','tempmail-pro'); ?>');
        $('#tmpmp-table-empty-msg').toggle(visible === 0);
    }

    $('#tmpmp-user-search, #tmpmp-plan-filter, #tmpmp-status-filter').on('input change', filterTable);

    // ── Initialize on page load: ensure panel is visible and table is filtered ─
    $('#tmpmp-panel-all').show();
    $('#tmpmp-panel-blocked').hide();
    filterTable();

    // ── Modal logic ──────────────────────────────────────────────────
    let currentUid = 0;

    function openModal(btn) {
        const $b = $(btn);
        currentUid = parseInt($b.data('uid'));

        // Header
        const color    = $b.data('color') || '#6366f1';
        const initials = $b.data('initials') || 'U';
        const name     = $b.data('name') || '';
        const email    = $b.data('email') || '';
        $('#modal-avatar').css('background', color).text(initials);
        $('#modal-title').text(name || email);
        $('#modal-subtitle').text(email + ' · ID #' + currentUid);
        $('#modal-wp-edit-link').attr('href', '<?php echo esc_js(admin_url('user-edit.php?user_id=')); ?>' + currentUid);

        // Profile tab
        $('#modal-display-name').val(name);
        $('#modal-email').val(email);
        $('#modal-password').val('');
        // Reset password generator UI on every modal open
        $('#modal-password').attr('type', 'password');
        $('.tmpmp-pass-eye[data-target="modal-password"]').text('\ud83d\udc41');
        $('.tmpmp-gen-pass-btn[data-targets="modal-password"]').text('\ud83d\udd11 <?php esc_html_e("Generate Password","tempmail-pro"); ?>');
        $('#modal-password-sw').hide();
        $('#modal-password-copy').hide();
        $('#qi-uid').text('#' + currentUid);
        const reg = $b.data('registered') || '';
        $('#qi-reg').text(reg ? new Date(reg).toLocaleDateString('en', dateOpts) : '—');
        $('#qi-inboxes').text($b.data('inboxes') || 0);
        $('#qi-payments').text($b.data('payments') || 0);

        // Plan/sub tab
        const planName   = $b.data('plan-slug') === 'free' ? 'Free' : ($b.data('plan-name') || $b.closest('tr').find('.tmpmp-plan-badge').text().trim());
        const subStatus  = $b.data('sub-status') || 'free';
        const billing    = $b.data('billing') || '—';
        const amount     = $b.data('amount') || '';
        const currency   = $b.data('currency') || 'USD';
        const gateway    = $b.data('gateway') || '—';
        const periodEnd  = $b.data('period-end') || '';
        const spent      = parseFloat($b.data('spent') || 0);
        const subId      = parseInt($b.data('sub-id') || 0);
        const planId     = parseInt($b.data('plan-id') || 0);

        $('#si-plan').text(planName);
        $('#si-status').html(subStatus === 'active' ? '<span class="tmpmp-status-active">✓ Active</span>'
                           : subStatus === 'cancelled' ? '<span class="tmpmp-status-cancelled">✗ Cancelled</span>'
                           : '<span class="tmpmp-status-free">— Free</span>');
        $('#si-gateway').text(gateway ? gateway.charAt(0).toUpperCase()+gateway.slice(1) : '—');
        $('#si-cycle').text(billing ? billing.charAt(0).toUpperCase()+billing.slice(1) : '—');
        $('#si-amount').text(amount ? currency+' '+parseFloat(amount).toFixed(2) : '—');
        $('#si-period-end').text(periodEnd ? new Date(periodEnd).toLocaleDateString('en', dateOpts) : '—');
        $('#si-spent').text('$' + spent.toFixed(2));

        // Pre-fill plan fields
        if (planId) {
            $('#modal-plan-id').val(planId);
            updatePlanAmountFromSelect();
        } else {
            $('#modal-plan-id').val('free');
        }
        $('#modal-billing-cycle').val(billing !== '—' ? billing : 'monthly');
        $('#modal-plan-amount').val(amount ? parseFloat(amount).toFixed(2) : '');
        if (periodEnd) {
            const d = new Date(periodEnd);
            $('#modal-period-end').val(d.toISOString().split('T')[0]);
        } else {
            const next = new Date(); next.setMonth(next.getMonth()+1);
            $('#modal-period-end').val(next.toISOString().split('T')[0]);
        }
        togglePlanFields();

        // Cancel zone
        $('#modal-cancel-zone').toggle(subStatus === 'active');

        // Activity panel – load on first open
        loadActivity(currentUid);

        // Show correct footer button and reset to profile tab
        activateModalTab('profile');
        updateFooterBtn('profile');

        $('#modal-msg').text('').removeClass('ok err');
        $('#tmpmp-user-modal-overlay').addClass('show');
    }

    function activateModalTab(tab) {
        $('.tmpmp-modal-tab').removeClass('active');
        $(`.tmpmp-modal-tab[data-mtab="${tab}"]`).addClass('active');
        $('.tmpmp-modal-panel').removeClass('active');
        $(`#modal-panel-${tab}`).addClass('active');
    }
    function updateFooterBtn(tab) {
        const $btn = $('#modal-btn-save');
        if (tab === 'profile') { $btn.show().text('💾 <?php esc_html_e('Save Profile','tempmail-pro'); ?>'); }
        else if (tab === 'plan') { $btn.hide(); }
        else { $btn.hide(); }
    }

    $('.tmpmp-modal-tab').on('click', function() {
        const tab = $(this).data('mtab');
        activateModalTab(tab);
        updateFooterBtn(tab);
    });

    // Open modal
    $(document).on('click', '.tmpmp-open-user-modal', function() { openModal(this); });

    // Close modal
    $('#tmpmp-modal-close, #tmpmp-user-modal-overlay, #modal-btn-cancel').on('click', function(e) {
        if (e.target === this) $('#tmpmp-user-modal-overlay').removeClass('show');
    });
    $('#tmpmp-user-modal').on('click', function(e) { e.stopPropagation(); });

    // ── Save Profile ─────────────────────────────────────────────────
    $('#modal-btn-save').on('click', function() {
        const $btn = $(this);
        const orig = $btn.text();
        $btn.prop('disabled', true).text('<?php esc_html_e('Saving…','tempmail-pro'); ?>');
        $('#modal-msg').text('').removeClass('ok err');

        $.post(ajaxUrl, {
            action:       'tmpmp_admin_update_user',
            nonce,
            user_id:      currentUid,
            display_name: $('#modal-display-name').val(),
            user_email:   $('#modal-email').val(),
            user_pass:    $('#modal-password').val(),
        }, function(r) {
            $('#modal-msg').text(r.data?.message || '').addClass(r.success ? 'ok' : 'err');
            if (r.success) {
                // Update table row data
                const $row = $(`#tmpmp-users-tbody tr[data-uid="${currentUid}"]`);
                $row.find('.tmpmp-user-name').text($('#modal-display-name').val());
                $row.find('.tmpmp-user-email').text($('#modal-email').val());
                $row.data('name', $('#modal-display-name').val().toLowerCase());
                $row.data('email', $('#modal-email').val().toLowerCase());
                $('#modal-title').text($('#modal-display-name').val());
                $('#modal-subtitle').text($('#modal-email').val() + ' · ID #' + currentUid);
            }
        }).always(() => $btn.prop('disabled', false).text(orig));
    });

    // ── Plan selector: auto-fill amount ──────────────────────────────
    function updatePlanAmountFromSelect() {
        const selVal = $('#modal-plan-id').val();
        if (!selVal || selVal === 'free') { togglePlanFields(); return; }
        const $opt   = $('#modal-plan-id option:selected');
        const cycle  = $('#modal-billing-cycle').val();
        const amt    = cycle === 'yearly' ? $opt.data('yearly') : $opt.data('monthly');
        if (amt !== undefined) $('#modal-plan-amount').val(parseFloat(amt).toFixed(2));
        togglePlanFields();
    }
    function togglePlanFields() {
        const isFree = $('#modal-plan-id').val() === 'free';
        $('#modal-billing-wrap, #modal-period-row').toggle(!isFree);
    }
    $('#modal-plan-id, #modal-billing-cycle').on('change', updatePlanAmountFromSelect);

    // ── Save Plan ────────────────────────────────────────────────────
    $('#modal-save-plan').on('click', function() {
        const $btn = $(this);
        const orig = $btn.text();
        $btn.prop('disabled', true).text('<?php esc_html_e('Saving…','tempmail-pro'); ?>');
        $('#modal-msg').text('').removeClass('ok err');

        const planVal = $('#modal-plan-id').val();
        const isFreePlan = (planVal === 'free');

        // Find free plan ID
        let freePlanId = 0;
        plansData.forEach(p => { if (p.slug === 'free') freePlanId = p.id; });

        $.post(ajaxUrl, {
            action:        'tmpmp_admin_set_plan',
            nonce,
            user_id:       currentUid,
            plan_id:       isFreePlan ? freePlanId : parseInt(planVal),
            billing_cycle: $('#modal-billing-cycle').val(),
            amount:        $('#modal-plan-amount').val(),
            period_end:    $('#modal-period-end').val() ? $('#modal-period-end').val() + ' 23:59:59' : '',
        }, function(r) {
            $('#modal-msg').addClass(r.success ? 'ok' : 'err').text(r.data?.message || '');
            if (r.success) {
                // Refresh the row badge + status
                const $row = $(`#tmpmp-users-tbody tr[data-uid="${currentUid}"]`);
                if (isFreePlan) {
                    $row.find('.tmpmp-plan-badge').text('Free').attr('class','tmpmp-plan-badge tmpmp-plan-free');
                    $row.find('td:nth-child(3)').html('<span class="tmpmp-status-free">— Free</span>');
                    $row.data('type','free').data('plan','free').data('status','free');
                    $('#modal-cancel-zone').hide();
                } else {
                    const $opt   = $('#modal-plan-id option:selected');
                    const pName  = $opt.text().replace(/\s*\(.*\)/,'').trim();
                    const pSlug  = planVal; // actually the id but badge uses name
                    $row.find('.tmpmp-plan-badge').text('💎 ' + pName).attr('class','tmpmp-plan-badge tmpmp-plan-premium');
                    $row.find('td:nth-child(3)').html('<span class="tmpmp-status-active">✓ Active</span>');
                    $row.data('type','premium').data('status','active');
                    $('#modal-cancel-zone').show();
                }
            }
        }).always(() => $btn.prop('disabled', false).text(orig));
    });

    // ── Cancel sub from modal ─────────────────────────────────────────
    $('#modal-cancel-sub').on('click', function() {
        if (!confirm('<?php esc_html_e('Cancel this subscription?','tempmail-pro'); ?>')) return;
        $.post(ajaxUrl, { action:'tmpmp_cancel_user_sub', nonce, user_id:currentUid }, function(r) {
            if (r.success) {
                $('#modal-cancel-zone').hide();
                $('#modal-msg').addClass('ok').text('<?php esc_html_e('Subscription cancelled.','tempmail-pro'); ?>');
                $('#si-status').html('<span class="tmpmp-status-cancelled">✗ Cancelled</span>');
                const $row = $(`#tmpmp-users-tbody tr[data-uid="${currentUid}"]`);
                $row.find('td:nth-child(3)').html('<span class="tmpmp-status-cancelled">✗ Cancelled</span>');
                $row.data('status','cancelled');
                $row.find('.tmpmp-act-cancel').remove();
            }
        });
    });

    // ── Cancel sub from table ─────────────────────────────────────────
    $(document).on('click', '.tmpmp-cancel-user-sub', function() {
        if (!confirm('<?php esc_html_e('Cancel this subscription?','tempmail-pro'); ?>')) return;
        const uid = $(this).data('uid');
        const $btn = $(this);
        $.post(ajaxUrl, { action:'tmpmp_cancel_user_sub', nonce, user_id:uid }, function(r) {
            if (r.success) {
                const $row = $(`#tmpmp-users-tbody tr[data-uid="${uid}"]`);
                $row.find('td:nth-child(3)').html('<span class="tmpmp-status-cancelled">✗ Cancelled</span>');
                $row.data('status','cancelled');
                $btn.remove();
            }
        });
    });

    // ── Delete user ───────────────────────────────────────────────────
    $('#modal-delete-user').on('click', function() {
        if (!confirm('<?php esc_html_e('DELETE USER? This cannot be undone — all data will be permanently removed.','tempmail-pro'); ?>')) return;
        const $btn = $(this);
        $btn.prop('disabled', true).text('<?php esc_html_e('Deleting…','tempmail-pro'); ?>');
        $.post(ajaxUrl, { action:'tmpmp_admin_delete_user', nonce, user_id:currentUid }, function(r) {
            if (r.success) {
                $(`#tmpmp-users-tbody tr[data-uid="${currentUid}"]`).fadeOut(300, function(){ $(this).remove(); });
                $('#tmpmp-user-modal-overlay').removeClass('show');
            } else {
                $('#modal-msg').addClass('err').text(r.data?.message || '<?php esc_html_e('Error.','tempmail-pro'); ?>');
                $btn.prop('disabled', false).text('🗑️ <?php esc_html_e('Delete User Permanently','tempmail-pro'); ?>');
            }
        });
    });

    // ── Load activity data ────────────────────────────────────────────
    let loadedUid = 0;
    function loadActivity(uid) {
        if (loadedUid === uid) return;
        loadedUid = uid;
        $('#modal-addresses-wrap, #modal-payments-wrap').html('<p style="color:#94a3b8;font-size:13px;"><?php esc_html_e('Loading…','tempmail-pro'); ?></p>');
        $.post(ajaxUrl, { action:'tmpmp_admin_get_user', nonce, user_id:uid }, function(r) {
            if (!r.success) return;
            const d = r.data;

            // Addresses
            let aHtml = '';
            if (d.addresses && d.addresses.length) {
                aHtml = '<table class="tmpmp-mini-table"><thead><tr><th><?php esc_html_e('Address','tempmail-pro'); ?></th><th><?php esc_html_e('Emails','tempmail-pro'); ?></th><th><?php esc_html_e('Created','tempmail-pro'); ?></th><th><?php esc_html_e('Expires','tempmail-pro'); ?></th></tr></thead><tbody>';
                d.addresses.forEach(a => {
                    const cr = a.created_at ? new Date(a.created_at).toLocaleDateString('en', dateOpts) : '—';
                    const ex = a.expires_at ? new Date(a.expires_at).toLocaleDateString('en', dateOpts) : '—';
                    aHtml += `<tr><td><code style="font-size:11px;">${a.address}</code></td><td>${a.email_count}</td><td>${cr}</td><td>${ex}</td></tr>`;
                });
                aHtml += '</tbody></table>';
            } else {
                aHtml = '<p style="color:#94a3b8;font-size:13px;"><?php esc_html_e('No temp inboxes found.','tempmail-pro'); ?></p>';
            }
            $('#modal-addresses-wrap').html(aHtml);

            // Payments
            let pHtml = '';
            if (d.payments && d.payments.length) {
                pHtml = '<table class="tmpmp-mini-table"><thead><tr><th><?php esc_html_e('Date','tempmail-pro'); ?></th><th><?php esc_html_e('Amount','tempmail-pro'); ?></th><th><?php esc_html_e('Gateway','tempmail-pro'); ?></th><th><?php esc_html_e('Status','tempmail-pro'); ?></th><th><?php esc_html_e('Txn ID','tempmail-pro'); ?></th></tr></thead><tbody>';
                d.payments.forEach(p => {
                    const dt = p.created_at ? new Date(p.created_at).toLocaleDateString('en', dateOpts) : '—';
                    pHtml += `<tr><td>${dt}</td><td><strong>$${parseFloat(p.amount).toFixed(2)}</strong></td><td>${p.gateway||'—'}</td><td>${p.status||'—'}</td><td><code style="font-size:10px;">${(p.gateway_txn_id||'—').substring(0,20)}…</code></td></tr>`;
                });
                pHtml += '</tbody></table>';
            } else {
                pHtml = '<p style="color:#94a3b8;font-size:13px;"><?php esc_html_e('No payments found.','tempmail-pro'); ?></p>';
            }
            $('#modal-payments-wrap').html(pHtml);
        });
    }

    // Reset loadedUid when modal opens
    $(document).on('click', '.tmpmp-open-user-modal', function() { loadedUid = 0; });

    // ── Blocked IPs ───────────────────────────────────────────────────
    $(document).on('click', '.tmpmp-ban-ip', function() {
        const ip     = prompt('<?php esc_html_e('Enter IP address to ban:','tempmail-pro'); ?>');
        if (!ip) return;
        const reason = prompt('<?php esc_html_e('Reason (optional):','tempmail-pro'); ?>') || '';
        $.post(ajaxUrl, { action:'tmpmp_ban_ip', nonce, ip, reason }, function(r) {
            if (r.success) location.reload();
            else alert('<?php esc_html_e('Failed to ban IP.','tempmail-pro'); ?>');
        });
    });
    $(document).on('click', '.tmpmp-unban-ip', function() {
        if (!confirm('<?php esc_html_e('Unban this IP?','tempmail-pro'); ?>')) return;
        const ip = $(this).data('ip');
        $.post(ajaxUrl, { action:'tmpmp_unban_ip', nonce, ip }, function(r) {
            if (r.success) location.reload();
        });
    });

    // ── Password Generator ──────────────────────────────────────────────────────
    // Bound to #tmpmp-user-modal (not document) so it fires even with stopPropagation
    function tmpmpGenPass(len) {
        len = len || 18;
        var lower='abcdefghijklmnopqrstuvwxyz', upper='ABCDEFGHIJKLMNOPQRSTUVWXYZ',
            digits='0123456789', syms='!@#$%^&*_+-=';
        var all = lower + upper + digits + syms;
        var arr = new Uint8Array(len);
        crypto.getRandomValues(arr);
        var pass = [
            lower[arr[0]%lower.length], upper[arr[1]%upper.length],
            digits[arr[2]%digits.length], syms[arr[3]%syms.length]
        ];
        for (var i = 4; i < len; i++) pass.push(all[arr[i] % all.length]);
        // Fisher-Yates shuffle
        for (var i = pass.length - 1; i > 0; i--) {
            var j = arr[i % arr.length] % (i + 1);
            var t = pass[i]; pass[i] = pass[j]; pass[j] = t;
        }
        return pass.join('');
    }
    function tmpmpPassStrength(p) {
        var s = 0;
        if (p.length >= 8)  s++;
        if (p.length >= 12) s++;
        if (/[A-Z]/.test(p)) s++;
        if (/[0-9]/.test(p)) s++;
        if (/[^A-Za-z0-9]/.test(p)) s++;
        var lbl = ['','Weak','Fair','Good','Strong','Very Strong'];
        var col = ['','#ef4444','#f97316','#eab308','#22c55e','#10b981'];
        return { score: s, label: lbl[s] || 'Very Strong', color: col[s] || '#10b981' };
    }
    function tmpmpUpdateStrength(inputId) {
        var $inp = $('#' + inputId);
        var $sw  = $('#' + inputId + '-sw');
        if (!$inp.length || !$sw.length) return;
        var val = $inp.val();
        if (!val) { $sw.hide(); return; }
        var s = tmpmpPassStrength(val);
        $sw.find('.tmpmp-pass-strength-bars span').each(function(i) {
            $(this).css('background', i < s.score ? s.color : '#e2e8f0');
        });
        $sw.find('.tmpmp-pass-strength-label').text(s.label).css('color', s.color);
        $sw.show();
    }

    // Eye toggle — delegated to modal so it fires despite stopPropagation
    $('#tmpmp-user-modal').on('click', '.tmpmp-pass-eye', function(e) {
        var $inp = $('#' + $(this).data('target'));
        if (!$inp.length) return;
        var isPass = $inp.attr('type') === 'password';
        $inp.attr('type', isPass ? 'text' : 'password');
        $(this).text(isPass ? '\ud83d\ude48' : '\ud83d\udc41');
    });

    // Generate password — delegated to modal
    $('#tmpmp-user-modal').on('click', '.tmpmp-gen-pass-btn', function() {
        var $btn    = $(this);
        var pass    = tmpmpGenPass(18);
        var targets = ($btn.data('targets') || '').split(',').filter(Boolean);
        targets.forEach(function(id) {
            id = id.trim();
            var $inp = $('#' + id);
            if (!$inp.length) return;
            $inp.val(pass).attr('type', 'text').trigger('input');
            tmpmpUpdateStrength(id);
            $inp.closest('.tmpmp-pass-input-wrap').find('.tmpmp-pass-eye').text('\ud83d\ude48');
            $('#' + id + '-copy').show();
        });
        if (targets.length) $('#' + targets[0].trim() + '-sw').show();
        $btn.text('\ud83d\udd04 <?php esc_html_e("Regenerate","tempmail-pro"); ?>');
    });

    // Copy to clipboard — delegated to modal
    $('#tmpmp-user-modal').on('click', '.tmpmp-pass-copy-btn', function() {
        var val = $('#' + $(this).data('target')).val();
        if (!val) return;
        var $btn = $(this);
        navigator.clipboard.writeText(val).then(function() {
            var orig = $btn.text();
            $btn.text('\u2713 <?php esc_html_e("Copied!","tempmail-pro"); ?>');
            setTimeout(function() { $btn.text(orig); }, 1600);
        });
    });

    // Strength meter on manual typing — delegated to modal
    $('#tmpmp-user-modal').on('input', '.tmpmp-pass-input-wrap input', function() {
        tmpmpUpdateStrength(this.id);
    });

});
</script>
