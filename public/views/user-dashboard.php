<?php
if ( ! defined('ABSPATH') ) exit;
$user    = wp_get_current_user();
$sub     = TempMail_Database::get_user_subscription($user->ID);
$plan    = TempMail_Subscription::get_user_plan_data($user->ID);
global $wpdb;
$my_addresses = $wpdb->get_results($wpdb->prepare(
    "SELECT a.*, (SELECT COUNT(*) FROM {$wpdb->prefix}tmpmp_emails e WHERE e.address_id=a.id) as email_count
     FROM {$wpdb->prefix}tmpmp_addresses a WHERE a.user_id=%d ORDER BY a.created_at DESC LIMIT 50",
    $user->ID
));
$my_payments = $wpdb->get_results($wpdb->prepare(
    "SELECT * FROM {$wpdb->prefix}tmpmp_payments WHERE user_id=%d ORDER BY created_at DESC LIMIT 20",
    $user->ID
));
$my_keys = $wpdb->get_results($wpdb->prepare(
    "SELECT * FROM {$wpdb->prefix}tmpmp_api_keys WHERE user_id=%d AND is_active=1 ORDER BY created_at DESC",
    $user->ID
));
$is_premium = TempMail_Subscription::is_premium_user( $user->ID );
?>
<style>
/* ── Dashboard responsive override (guaranteed, not cached) ── */

/* Account / Profile cards */
.tmpmp-account-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
    align-items: start;
}
.tmpmp-account-card {
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    padding: 24px;
    box-shadow: 0 2px 8px rgba(0,0,0,.04);
}
.tmpmp-account-card h3 {
    margin: 0 0 18px;
    font-size: 15px;
    font-weight: 800;
    color: #0f172a;
    display: flex;
    align-items: center;
    gap: 8px;
}
.tmpmp-account-card .tmpmp-field {
    margin-bottom: 14px;
}
.tmpmp-account-card label {
    display: block;
    font-size: 12px;
    font-weight: 700;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: .4px;
    margin-bottom: 5px;
}
.tmpmp-account-card input[type=text],
.tmpmp-account-card input[type=password] {
    width: 100%;
    padding: 9px 13px;
    border: 1.5px solid #e2e8f0;
    border-radius: 9px;
    font-size: 14px;
    color: #0f172a;
    background: #f8fafc;
    box-sizing: border-box;
    transition: border-color .18s;
    outline: none;
    font-family: inherit;
}
.tmpmp-account-card input:focus {
    border-color: #6366f1;
    background: #fff;
}
.tmpmp-account-msg {
    margin-top: 10px;
    padding: 9px 13px;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 600;
    display: none;
}
.tmpmp-account-msg.ok  { background: #f0fdf4; color: #065f46; border: 1px solid #bbf7d0; display: block; }
.tmpmp-account-msg.err { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; display: block; }
.tmpmp-reset-info {
    font-size: 13px;
    color: #64748b;
    margin-bottom: 16px;
    line-height: 1.6;
}
/* Responsive: stack cards on mobile */
@media (max-width: 640px) {
    .tmpmp-account-grid { grid-template-columns: 1fr; }
    .tmpmp-account-card { padding: 18px 16px; }
}

/* ── Avatar ── */
.tmpmp-avatar {
    width: 56px;
    height: 56px;
    border-radius: 50%;
    object-fit: cover;
    border: 3px solid #e2e8f0;
    background: #f1f5f9;
    flex-shrink: 0;
    display: block;
}
.tmpmp-avatar--lg {
    width: 100px;
    height: 100px;
    border: 4px solid #e2e8f0;
}
.tmpmp-avatar-wrap {
    position: relative;
    display: inline-block;
    cursor: pointer;
    border-radius: 50%;
    flex-shrink: 0;
}
.tmpmp-avatar-overlay {
    position: absolute;
    inset: 0;
    border-radius: 50%;
    background: rgba(0,0,0,.45);
    color: #fff;
    font-size: 11px;
    font-weight: 700;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-direction: column;
    gap: 2px;
    opacity: 0;
    transition: opacity .2s;
    user-select: none;
}
.tmpmp-avatar-wrap:hover .tmpmp-avatar-overlay { opacity: 1; }
.tmpmp-avatar-section {
    display: flex;
    align-items: center;
    gap: 20px;
    padding: 4px 0 20px;
    margin-bottom: 18px;
    border-bottom: 1px solid #f1f5f9;
}
.tmpmp-avatar-btns {
    display: flex;
    flex-direction: column;
    gap: 8px;
}
.tmpmp-avatar-hint {
    font-size: 11px;
    color: #94a3b8;
    margin: 0;
}
/* header left: avatar + text side by side */
.tmpmp-dash-header-left {
    display: flex;
    align-items: center;
    gap: 16px;
}
@media (max-width: 480px) {
    .tmpmp-avatar--lg { width: 80px; height: 80px; }
    .tmpmp-avatar-section { gap: 14px; }
}
/* Locked tab */
.tmpmp-tab-locked { opacity:.55; cursor:not-allowed; }
.tmpmp-tab-locked:hover { background:transparent !important; color:inherit !important; }

/* History tab */
.tmpmp-history-table { width:100%; border-collapse:collapse; font-size:13.5px; }
.tmpmp-history-table th { text-align:left; padding:9px 12px; font-weight:600; color:#64748b; border-bottom:2px solid #e2e8f0; white-space:nowrap; }
.tmpmp-history-table td { padding:10px 12px; border-bottom:1px solid #f1f5f9; vertical-align:middle; }
.tmpmp-history-table tbody tr:hover { background:#f8fafc; cursor:pointer; }
.tmpmp-history-table .tmpmp-hist-addr { font-family:monospace; font-size:13px; color:#0f172a; font-weight:600; }
.tmpmp-hist-badge { display:inline-block; padding:2px 8px; border-radius:20px; font-size:11px; font-weight:700; }
.tmpmp-hist-badge--active  { background:#dcfce7; color:#16a34a; }
.tmpmp-hist-badge--expired { background:#f1f5f9; color:#94a3b8; }
.tmpmp-hist-badge--free    { background:#e0f2fe; color:#0369a1; }
.tmpmp-hist-badge--premium { background:#ede9fe; color:#6d28d9; }
.tmpmp-hist-badge--vip     { background:#fef3c7; color:#d97706; }
.tmpmp-hist-actions { display:flex; gap:8px; }
.tmpmp-hist-del-btn { background:none; border:1px solid #fca5a5; color:#ef4444; border-radius:6px; padding:4px 10px; font-size:12px; cursor:pointer; transition:background .15s; }
.tmpmp-hist-del-btn:hover { background:#fee2e2; }
.tmpmp-history-inbox { margin-top:20px; }
.tmpmp-history-inbox-header { display:flex; align-items:center; gap:12px; padding:12px 16px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px 10px 0 0; }
.tmpmp-history-inbox-header h4 { margin:0; font-size:14px; color:#0f172a; flex:1; }
.tmpmp-hist-back { background:none; border:1px solid #e2e8f0; border-radius:6px; padding:4px 12px; font-size:12px; cursor:pointer; color:#475569; }
.tmpmp-hist-back:hover { background:#f1f5f9; }
.tmpmp-hist-email-list { border:1px solid #e2e8f0; border-top:none; border-radius:0 0 10px 10px; overflow:hidden; }
.tmpmp-hist-email-row { display:flex; align-items:center; gap:12px; padding:12px 16px; border-bottom:1px solid #f1f5f9; cursor:pointer; transition:background .15s; }
.tmpmp-hist-email-row:last-child { border-bottom:none; }
.tmpmp-hist-email-row:hover { background:#f8fafc; }
.tmpmp-hist-email-row.unread { font-weight:600; }
.tmpmp-hist-email-sender { min-width:140px; max-width:160px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; font-size:13px; color:#0f172a; }
.tmpmp-hist-email-subject { flex:1; font-size:13px; color:#475569; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.tmpmp-hist-email-date { font-size:11px; color:#94a3b8; white-space:nowrap; }
.tmpmp-hist-email-body { border:1px solid #e2e8f0; border-top:none; padding:20px; background:#fff; border-radius:0 0 10px 10px; }
.tmpmp-hist-email-body-header { margin-bottom:12px; padding-bottom:12px; border-bottom:1px solid #f1f5f9; }
.tmpmp-hist-email-body-header h5 { margin:0 0 4px; font-size:14px; color:#0f172a; }
.tmpmp-hist-email-body-header small { color:#64748b; font-size:12px; }
.tmpmp-hist-empty { text-align:center; padding:40px 20px; color:#94a3b8; font-size:14px; }
.tmpmp-hist-pagination { display:flex; align-items:center; justify-content:center; gap:8px; margin-top:16px; }
.tmpmp-hist-page-btn { background:#fff; border:1px solid #e2e8f0; border-radius:6px; padding:6px 14px; font-size:13px; cursor:pointer; color:#475569; transition:all .15s; }
.tmpmp-hist-page-btn:hover:not(:disabled) { background:#6366f1; color:#fff; border-color:#6366f1; }
.tmpmp-hist-page-btn:disabled { opacity:.4; cursor:not-allowed; }
.tmpmp-hist-page-info { font-size:13px; color:#64748b; }
.tmpmp-hist-loading { text-align:center; padding:40px; color:#94a3b8; }

.tmpmp-dash-tabs {
    display: flex !important;
    flex-wrap: wrap !important;
    overflow: visible !important;
    overflow-x: unset !important;
    width: 100%;
    box-sizing: border-box;
    border-bottom: 2px solid #e2e8f0;
    margin-bottom: 24px;
}
.dash-tab-btn { flex-shrink: 0; white-space: nowrap; }

/* ── Responsive card table ── */
@media (max-width: 580px) {
    /* Hide the normal table header row */
    .tmpmp-pub-table thead { display: none !important; }

    /* Make the table itself, tbody, tr and td all block */
    .tmpmp-pub-table,
    .tmpmp-pub-table tbody,
    .tmpmp-pub-table tr,
    .tmpmp-pub-table td { display: block !important; width: 100% !important; }

    /* Remove the wrapper's overflow-x scroll — no longer needed */
    .tmpmp-pub-table-wrap { overflow-x: visible !important; border: none !important; border-radius: 0 !important; }

    /* Each row = a card */
    .tmpmp-pub-table tr {
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        margin-bottom: 12px;
        padding: 12px 14px;
        box-shadow: 0 1px 4px rgba(0,0,0,.05);
    }

    /* Each cell = label + value row */
    .tmpmp-pub-table td {
        display: flex !important;
        justify-content: space-between;
        align-items: center;
        padding: 7px 0 !important;
        font-size: 13px !important;
        border-bottom: 1px solid #f1f5f9;
        width: auto !important;
        box-sizing: border-box;
    }
    .tmpmp-pub-table td:last-child { border-bottom: none; }

    /* Label shown before value via data-label */
    .tmpmp-pub-table td::before {
        content: attr(data-label);
        font-size: 11px;
        font-weight: 700;
        color: #94a3b8;
        text-transform: uppercase;
        letter-spacing: .5px;
        flex-shrink: 0;
        margin-right: 12px;
        white-space: nowrap;
    }
}

/* Mobile: stack header, full-width actions */
@media (max-width: 640px) {
    .tmpmp-dashboard-wrap { padding: 16px 12px !important; }
    .tmpmp-dash-header {
        flex-direction: column !important;
        align-items: flex-start !important;
        gap: 12px;
    }
    .tmpmp-dash-actions { width: 100%; display: flex; flex-wrap: wrap; gap: 8px; }
    .tmpmp-dash-header h1 { font-size: 18px; }
    .tmpmp-dash-tabs { gap: 4px; }
    .dash-tab-btn {
        flex: 1 1 auto;
        justify-content: center;
        padding: 9px 8px;
        font-size: 12px;
        border-radius: 8px 8px 0 0;
        background: #f8fafc;
        gap: 4px;
    }
    .dash-tab-btn.is-active { background: #ede9fe; }
    .tmpmp-billing-active { padding: 14px !important; }
}
@media (max-width: 380px) {
    .tmpmp-dash-actions { flex-direction: column; }
    .tmpmp-dash-actions .tmpmp-pub-btn,
    .tmpmp-dash-actions .tmpmp-pub-badge { text-align: center; width: 100%; }
    .dash-tab-btn { font-size: 11px; padding: 8px 5px; }
}
/* ── Password Generator ───────────────────────────────────────────── */
.tmpmp-pass-input-wrap { position:relative; display:flex; align-items:center; }
.tmpmp-pass-input-wrap input { padding-right:44px !important; box-sizing:border-box; }
.tmpmp-pass-eye {
    position:absolute; right:12px; background:none; border:none; cursor:pointer;
    font-size:17px; color:#94a3b8; padding:0; line-height:1; transition:color .15s;
}
.tmpmp-pass-eye:hover { color:#374151; }
.tmpmp-pass-actions { display:flex; align-items:center; gap:8px; margin-top:8px; flex-wrap:wrap; }
.tmpmp-gen-pass-btn {
    display:inline-flex; align-items:center; gap:5px;
    padding:7px 14px; background:#f5f3ff; color:#6d28d9;
    border:1.5px solid #ddd6fe; border-radius:9px; font-size:13px; font-weight:700;
    cursor:pointer; transition:all .15s; white-space:nowrap;
}
.tmpmp-gen-pass-btn:hover { background:#ede9fe; border-color:#a78bfa; }
.tmpmp-pass-copy-btn {
    background:#f8fafc; border:1.5px solid #e2e8f0; border-radius:9px;
    padding:7px 12px; cursor:pointer; font-size:13px; color:#64748b;
    transition:all .15s; white-space:nowrap; font-weight:600;
}
.tmpmp-pass-copy-btn:hover { background:#f1f5f9; color:#0f172a; border-color:#94a3b8; }
.tmpmp-pass-strength-wrap { display:flex; align-items:center; gap:6px; }
.tmpmp-pass-strength-bars { display:flex; gap:3px; }
.tmpmp-pass-strength-bars span {
    display:block; width:24px; height:5px; border-radius:3px;
    background:#e2e8f0; transition:background .2s;
}
.tmpmp-pass-strength-label { font-size:12px; font-weight:700; }
</style>
<div class="tmpmp-page-section tmpmp-dashboard-wrap">

    <!-- Header -->
    <div class="tmpmp-dash-header">
        <div class="tmpmp-dash-header-left">
            <?php
            // Read directly from DB — bypasses any object/persistent cache
            $avatar_url = $wpdb->get_var( $wpdb->prepare(
                "SELECT meta_value FROM {$wpdb->usermeta} WHERE user_id=%d AND meta_key='tmpmp_avatar_url' LIMIT 1",
                $user->ID
            ) );
            $avatar_url = $avatar_url
                        ?: get_avatar_url( $user->ID, [ 'size' => 56, 'default' => 'identicon' ] );
            ?>
            <a href="#dash-tab-account" class="tmpmp-avatar-wrap" id="tmpmp-header-avatar-wrap"
               title="<?php esc_attr_e('Click to update profile picture','tempmail-pro'); ?>"
               onclick="event.preventDefault();activateTab('account');">
                <img class="tmpmp-avatar" id="tmpmp-header-avatar"
                     src="<?php echo esc_url( $avatar_url ); ?>"
                     alt="<?php echo esc_attr( $user->display_name ); ?>">
                <div class="tmpmp-avatar-overlay">&#128247;<br><?php esc_html_e('Edit','tempmail-pro'); ?></div>
            </a>
            <div>
                <h1>&#128075; <?php echo esc_html( sprintf( __('Hi, %s','tempmail-pro'), $user->display_name ) ); ?></h1>
                <p><?php echo esc_html( $user->user_email ); ?></p>
            </div>
        </div>
        <div class="tmpmp-dash-actions">
            <span class="tmpmp-pub-badge <?php echo $sub ? 'tmpmp-pub-badge--green' : 'tmpmp-pub-badge--indigo'; ?>">
                <?php echo esc_html( ucfirst( $plan->slug ?? 'free' ) ); ?> Plan
            </span>
            <?php if ( ! $sub ) : ?>
            <a href="<?php echo esc_url( home_url('/tempmail-pricing/') ); ?>" class="tmpmp-pub-btn tmpmp-pub-btn--primary">
                &#11014; <?php esc_html_e('Upgrade','tempmail-pro'); ?>
            </a>
            <?php endif; ?>
            <a href="<?php echo esc_url( wp_logout_url( home_url() ) ); ?>" class="tmpmp-pub-btn tmpmp-pub-btn--outline">
                <?php esc_html_e('Logout','tempmail-pro'); ?>
            </a>
        </div>
    </div>

    <!-- Tabs -->
    <div class="tmpmp-dash-tabs">
        <?php foreach ( ['inboxes' => '&#9993; '.__('My Inboxes','tempmail-pro'), 'billing' => '&#128179; '.__('Billing','tempmail-pro'), 'api' => '&#128273; '.__('API Keys','tempmail-pro'), 'account' => '&#128100; '.__('Account','tempmail-pro')] as $tab => $label ) : ?>
        <button class="dash-tab-btn" data-tab="<?php echo esc_attr($tab); ?>"><?php echo $label; ?></button>
        <?php endforeach; ?>
        <button class="dash-tab-btn<?php echo $is_premium ? '' : ' tmpmp-tab-locked'; ?>" data-tab="history" title="<?php echo $is_premium ? '' : esc_attr__('Premium feature','tempmail-pro'); ?>">
            &#128196; <?php esc_html_e('History','tempmail-pro'); ?><?php if (!$is_premium): ?> &#128274;<?php endif; ?>
        </button>
    </div>

    <!-- ── Inboxes Tab ─────────────────────────────────────────────────── -->
    <div class="dash-tab-panel" id="dash-tab-inboxes">
        <?php if ( empty($my_addresses) ) : ?>
        <div class="tmpmp-empty-state">
            <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
            <p><?php esc_html_e('No inboxes yet. Use the TempMail app to generate your first inbox.','tempmail-pro'); ?></p>
        </div>
        <?php else : ?>
        <div class="tmpmp-pub-table-wrap">
            <table class="tmpmp-pub-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Address','tempmail-pro'); ?></th>
                        <th><?php esc_html_e('Emails','tempmail-pro'); ?></th>
                        <th><?php esc_html_e('Plan','tempmail-pro'); ?></th>
                        <th><?php esc_html_e('Created','tempmail-pro'); ?></th>
                        <th><?php esc_html_e('Expires','tempmail-pro'); ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ( $my_addresses as $addr ) :
                    $expired = strtotime( $addr->expires_at . ' UTC' ) < time();
                ?>
                <tr>
                    <td data-label="<?php esc_attr_e('Address','tempmail-pro'); ?>" style="font-family:monospace;font-weight:600;color:#6366f1;word-break:break-all;"><?php echo esc_html($addr->address); ?></td>
                    <td data-label="<?php esc_attr_e('Emails','tempmail-pro'); ?>"><?php echo intval($addr->email_count); ?></td>
                    <td data-label="<?php esc_attr_e('Plan','tempmail-pro'); ?>"><span class="tmpmp-pub-badge tmpmp-pub-badge--indigo"><?php echo esc_html(ucfirst($addr->plan)); ?></span></td>
                    <td data-label="<?php esc_attr_e('Created','tempmail-pro'); ?>" style="color:#64748b;"><?php echo esc_html(date_i18n(get_option('date_format'), strtotime($addr->created_at))); ?></td>
                    <td data-label="<?php esc_attr_e('Expires','tempmail-pro'); ?>">
                        <span class="tmpmp-pub-badge <?php echo $expired ? 'tmpmp-pub-badge--red' : 'tmpmp-pub-badge--green'; ?>">
                            <?php echo $expired ? esc_html__('Expired','tempmail-pro') : esc_html(date_i18n('d M H:i', strtotime($addr->expires_at.' UTC'))); ?>
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

    <!-- ── Billing Tab ────────────────────────────────────────────────── -->
    <div class="dash-tab-panel" id="dash-tab-billing">
        <?php if ( $sub ) : ?>
        <div class="tmpmp-billing-active">
            <h3>&#9989; <?php echo esc_html( sprintf( __('Active: %s Plan','tempmail-pro'), $sub->plan_name ) ); ?></h3>
            <p>
                <?php echo esc_html( sprintf(
                    __('Billing: %s &middot; $%s &middot; Renews: %s','tempmail-pro'),
                    ucfirst($sub->billing_cycle),
                    number_format($sub->amount,2),
                    date_i18n(get_option('date_format'), strtotime($sub->current_period_end))
                ) ); ?>
            </p>
            <div class="tmpmp-billing-actions">
                <button id="tmpmp-cancel-sub" class="tmpmp-pub-btn tmpmp-pub-btn--danger">
                    <?php esc_html_e('Cancel Subscription','tempmail-pro'); ?>
                </button>
            </div>
        </div>
        <?php else : ?>
        <div class="tmpmp-locked-notice" style="background:#f0f4ff;border-color:#c7d2fe;color:#4338ca;">
            <p><?php esc_html_e('You are on the Free plan.','tempmail-pro'); ?>
                <a href="<?php echo esc_url(home_url('/tempmail-pricing/')); ?>"><?php esc_html_e('Upgrade now &rarr;','tempmail-pro'); ?></a>
            </p>
        </div>
        <?php endif; ?>

        <?php if ( ! empty($my_payments) ) : ?>
        <h3 style="font-size:15px;font-weight:700;margin:28px 0 14px;color:#0f172a;"><?php esc_html_e('Payment History','tempmail-pro'); ?></h3>
        <div class="tmpmp-pub-table-wrap">
            <table class="tmpmp-pub-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Invoice','tempmail-pro'); ?></th>
                        <th><?php esc_html_e('Amount','tempmail-pro'); ?></th>
                        <th><?php esc_html_e('Gateway','tempmail-pro'); ?></th>
                        <th><?php esc_html_e('Status','tempmail-pro'); ?></th>
                        <th><?php esc_html_e('Date','tempmail-pro'); ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ( $my_payments as $py ) : ?>
                <tr>
                    <td data-label="<?php esc_attr_e('Invoice','tempmail-pro'); ?>"><code style="font-size:12px;color:#4338ca;"><?php echo esc_html($py->invoice_number); ?></code></td>
                    <td data-label="<?php esc_attr_e('Amount','tempmail-pro'); ?>" style="font-weight:700;">$<?php echo number_format($py->amount,2); ?></td>
                    <td data-label="<?php esc_attr_e('Gateway','tempmail-pro'); ?>"><?php echo esc_html(ucfirst($py->gateway ?? '')); ?></td>
                    <td data-label="<?php esc_attr_e('Status','tempmail-pro'); ?>">
                        <span class="tmpmp-pub-badge <?php echo $py->status==='completed' ? 'tmpmp-pub-badge--green' : 'tmpmp-pub-badge--red'; ?>">
                            <?php echo esc_html(ucfirst($py->status)); ?>
                        </span>
                    </td>
                    <td data-label="<?php esc_attr_e('Date','tempmail-pro'); ?>" style="color:#64748b;"><?php echo esc_html(date_i18n(get_option('date_format'), strtotime($py->created_at))); ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

    <!-- ── API Keys Tab ───────────────────────────────────────────────── -->
    <div class="dash-tab-panel" id="dash-tab-api">
        <?php if ( empty($plan->has_api_access) ) : ?>
        <div class="tmpmp-locked-notice">
            &#128274; <?php esc_html_e('API access requires Pro or Business plan.','tempmail-pro'); ?>
            <a href="<?php echo esc_url(home_url('/tempmail-pricing/')); ?>"><?php esc_html_e('Upgrade &rarr;','tempmail-pro'); ?></a>
        </div>
        <?php else : ?>
        <div style="display:flex;gap:10px;margin-bottom:20px;flex-wrap:wrap;align-items:center;">
            <input type="text" id="api-key-label" class="tmpmp-auth-input"
                placeholder="<?php esc_attr_e('Key label…','tempmail-pro'); ?>"
                style="max-width:240px;margin:0;">
            <button id="tmpmp-gen-key" class="tmpmp-pub-btn tmpmp-pub-btn--primary">
                <?php esc_html_e('Generate Key','tempmail-pro'); ?>
            </button>
        </div>
        <div id="tmpmp-new-key-result" class="tmpmp-api-new-key" style="display:none;"></div>
        <div class="tmpmp-pub-table-wrap">
            <table class="tmpmp-pub-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Label','tempmail-pro'); ?></th>
                        <th><?php esc_html_e('Key','tempmail-pro'); ?></th>
                        <th style="text-align:center;"><?php esc_html_e('Uses','tempmail-pro'); ?></th>
                        <th><?php esc_html_e('Actions','tempmail-pro'); ?></th>
                    </tr>
                </thead>
                <tbody id="api-keys-tbody">
                <?php foreach ( $my_keys as $k ) : ?>
                <tr id="key-row-<?php echo intval($k->id); ?>">
                    <td data-label="<?php esc_attr_e('Label','tempmail-pro'); ?>"><?php echo esc_html($k->label); ?></td>
                    <td data-label="<?php esc_attr_e('Key','tempmail-pro'); ?>"><code style="font-size:12px;color:#4338ca;"><?php echo esc_html(substr($k->api_key,0,16)); ?>…</code></td>
                    <td data-label="<?php esc_attr_e('Uses','tempmail-pro'); ?>" style="text-align:center;"><?php echo intval($k->calls_count); ?></td>
                    <td data-label="<?php esc_attr_e('Actions','tempmail-pro'); ?>">
                        <button class="tmpmp-revoke-key tmpmp-pub-btn tmpmp-pub-btn--danger"
                            data-id="<?php echo intval($k->id); ?>"
                            style="padding:6px 12px;font-size:12px;">
                            <?php esc_html_e('Revoke','tempmail-pro'); ?>
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

    <!-- ── Account Tab ──────────────────────────────────────────────────── -->
    <div class="dash-tab-panel" id="dash-tab-account">
        <div class="tmpmp-account-grid">

            <!-- Profile card -->
            <div class="tmpmp-account-card">
                <h3>&#128100; <?php esc_html_e('My Profile','tempmail-pro'); ?></h3>

                <!-- Avatar upload section -->
                <?php
                // Read directly from DB — bypasses any object/persistent cache
                $_raw_av = $wpdb->get_var( $wpdb->prepare(
                    "SELECT meta_value FROM {$wpdb->usermeta} WHERE user_id=%d AND meta_key='tmpmp_avatar_url' LIMIT 1",
                    $user->ID
                ) );
                $av_lg      = $_raw_av ?: get_avatar_url( $user->ID, [ 'size' => 120, 'default' => 'identicon' ] );
                $has_custom = ! empty( $_raw_av );
                ?>
                <div class="tmpmp-avatar-section">
                    <div class="tmpmp-avatar-wrap" id="acc-avatar-wrap"
                         title="<?php esc_attr_e('Click to upload a new photo','tempmail-pro'); ?>"
                         onclick="document.getElementById('acc-avatar-file').click()">
                        <img class="tmpmp-avatar tmpmp-avatar--lg" id="acc-avatar-img"
                             src="<?php echo esc_url( $av_lg ); ?>"
                             alt="<?php echo esc_attr( $user->display_name ); ?>">
                        <div class="tmpmp-avatar-overlay">&#128247;<br><?php esc_html_e('Upload','tempmail-pro'); ?></div>
                    </div>
                    <div class="tmpmp-avatar-btns">
                        <input type="file" id="acc-avatar-file" accept="image/jpeg,image/png,image/gif,image/webp" style="display:none;">
                        <button id="tmpmp-upload-avatar" class="tmpmp-pub-btn tmpmp-pub-btn--primary" style="font-size:13px;padding:8px 16px;">
                            &#128247; <?php esc_html_e('Upload Photo','tempmail-pro'); ?>
                        </button>
                        <button id="tmpmp-remove-avatar" class="tmpmp-pub-btn tmpmp-pub-btn--outline" style="font-size:13px;padding:8px 16px;<?php echo $has_custom ? '' : 'display:none;'; ?>">
                            &#128465; <?php esc_html_e('Remove Photo','tempmail-pro'); ?>
                        </button>
                        <p class="tmpmp-avatar-hint"><?php esc_html_e('JPG, PNG, GIF or WebP · Max 2 MB','tempmail-pro'); ?></p>
                        <div id="tmpmp-avatar-msg" class="tmpmp-account-msg"></div>
                    </div>
                </div>

                <div class="tmpmp-field">
                    <label for="acc-first-name"><?php esc_html_e('First Name','tempmail-pro'); ?></label>
                    <input type="text" id="acc-first-name" value="<?php echo esc_attr( $user->first_name ); ?>" autocomplete="given-name">
                </div>
                <div class="tmpmp-field">
                    <label for="acc-last-name"><?php esc_html_e('Last Name','tempmail-pro'); ?></label>
                    <input type="text" id="acc-last-name" value="<?php echo esc_attr( $user->last_name ); ?>" autocomplete="family-name">
                </div>
                <div class="tmpmp-field">
                    <label for="acc-display-name"><?php esc_html_e('Display Name','tempmail-pro'); ?></label>
                    <input type="text" id="acc-display-name" value="<?php echo esc_attr( $user->display_name ); ?>" autocomplete="nickname">
                </div>
                <div class="tmpmp-field">
                    <label><?php esc_html_e('Email','tempmail-pro'); ?></label>
                    <input type="text" value="<?php echo esc_attr( $user->user_email ); ?>" readonly style="opacity:.6;cursor:default;">
                </div>
                <button id="tmpmp-save-profile" class="tmpmp-pub-btn tmpmp-pub-btn--primary" style="width:100%;margin-top:4px;">
                    <?php esc_html_e('Save Profile','tempmail-pro'); ?>
                </button>
                <div id="tmpmp-profile-msg" class="tmpmp-account-msg"></div>
            </div>

            <!-- Security card -->
            <div style="display:flex;flex-direction:column;gap:20px;">

                <!-- Change Password -->
                <div class="tmpmp-account-card">
                    <h3>&#128274; <?php esc_html_e('Change Password','tempmail-pro'); ?></h3>
                    <div class="tmpmp-field">
                        <label for="acc-cur-pass"><?php esc_html_e('Current Password','tempmail-pro'); ?></label>
                        <input type="password" id="acc-cur-pass" autocomplete="current-password">
                    </div>
                    <div class="tmpmp-field">
                        <label for="acc-new-pass"><?php esc_html_e('New Password','tempmail-pro'); ?></label>
                        <div class="tmpmp-pass-input-wrap">
                            <input type="password" id="acc-new-pass" autocomplete="new-password" placeholder="<?php esc_attr_e('Min 8 characters…','tempmail-pro'); ?>">
                            <button type="button" class="tmpmp-pass-eye" data-target="acc-new-pass" title="<?php esc_attr_e('Show / Hide','tempmail-pro'); ?>">👁</button>
                        </div>
                        <div class="tmpmp-pass-actions">
                            <button type="button" class="tmpmp-gen-pass-btn" data-targets="acc-new-pass,acc-conf-pass">🔑 <?php esc_html_e('Generate Password','tempmail-pro'); ?></button>
                            <div class="tmpmp-pass-strength-wrap" id="acc-new-pass-sw" style="display:none;">
                                <div class="tmpmp-pass-strength-bars"><span></span><span></span><span></span><span></span><span></span></div>
                                <span class="tmpmp-pass-strength-label"></span>
                            </div>
                            <button type="button" class="tmpmp-pass-copy-btn" id="acc-new-pass-copy" data-target="acc-new-pass" style="display:none;">📋 <?php esc_html_e('Copy','tempmail-pro'); ?></button>
                        </div>
                    </div>
                    <div class="tmpmp-field">
                        <label for="acc-conf-pass"><?php esc_html_e('Confirm New Password','tempmail-pro'); ?></label>
                        <div class="tmpmp-pass-input-wrap">
                            <input type="password" id="acc-conf-pass" autocomplete="new-password" placeholder="<?php esc_attr_e('Re-enter password…','tempmail-pro'); ?>">
                            <button type="button" class="tmpmp-pass-eye" data-target="acc-conf-pass" title="<?php esc_attr_e('Show / Hide','tempmail-pro'); ?>">👁</button>
                        </div>
                    </div>
                    <button id="tmpmp-change-pass" class="tmpmp-pub-btn tmpmp-pub-btn--primary" style="width:100%;margin-top:4px;">
                        <?php esc_html_e('Update Password','tempmail-pro'); ?>
                    </button>
                    <div id="tmpmp-pass-msg" class="tmpmp-account-msg"></div>
                </div>

                <!-- Reset Password -->
                <div class="tmpmp-account-card">
                    <h3>&#128140; <?php esc_html_e('Reset Password','tempmail-pro'); ?></h3>
                    <p class="tmpmp-reset-info">
                        <?php printf(
                            esc_html__( 'Forgot your password, or signed in with magic link and never set one? Send a reset link to %s.', 'tempmail-pro' ),
                            '<strong>' . esc_html( $user->user_email ) . '</strong>'
                        ); ?>
                    </p>
                    <button id="tmpmp-send-reset" class="tmpmp-pub-btn tmpmp-pub-btn--outline" style="width:100%;">
                        &#128140; <?php esc_html_e('Send Password Reset Email','tempmail-pro'); ?>
                    </button>
                    <div id="tmpmp-reset-msg" class="tmpmp-account-msg"></div>
                </div>

            </div><!-- /security column -->
        </div><!-- /account-grid -->
    </div><!-- /#dash-tab-account -->

    <!-- ── History Tab ──────────────────────────────────────────────────── -->
    <div class="dash-tab-panel" id="dash-tab-history">
        <?php if ( ! $is_premium ) : ?>
        <div class="tmpmp-upgrade-notice" style="text-align:center;padding:48px 24px;">
            <div style="font-size:48px;margin-bottom:12px;">📂</div>
            <h3 style="margin:0 0 8px;font-size:18px;color:#0f172a;"><?php esc_html_e('Address History — Premium Feature','tempmail-pro'); ?></h3>
            <p style="color:#64748b;margin:0 0 20px;"><?php esc_html_e('Upgrade to any paid plan to keep a full history of all your temporary email addresses for 90 days.','tempmail-pro'); ?></p>
            <a href="<?php echo esc_url( home_url('/tempmail-pricing/') ); ?>" class="tmpmp-pub-btn tmpmp-pub-btn--primary">&#11014; <?php esc_html_e('Upgrade Now','tempmail-pro'); ?></a>
        </div>
        <?php else : ?>

        <!-- History list view -->
        <div id="tmpmp-hist-list-view">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;gap:12px;flex-wrap:wrap;">
                <h3 style="margin:0;font-size:16px;color:#0f172a;">&#128196; <?php esc_html_e('Temporary Address History','tempmail-pro'); ?></h3>
                <span style="font-size:12px;color:#64748b;"><?php esc_html_e('Addresses are kept for 90 days. Click a row to view its inbox.','tempmail-pro'); ?></span>
            </div>
            <div id="tmpmp-hist-table-wrap">
                <div class="tmpmp-hist-loading">&#9203; <?php esc_html_e('Loading history…','tempmail-pro'); ?></div>
            </div>
            <div class="tmpmp-hist-pagination" id="tmpmp-hist-pagination" style="display:none;"></div>
        </div>

        <!-- Inbox drill-down view (hidden by default) -->
        <div id="tmpmp-hist-inbox-view" style="display:none;">
            <div class="tmpmp-history-inbox-header">
                <button class="tmpmp-hist-back" id="tmpmp-hist-back-btn">&#8592; <?php esc_html_e('Back to History','tempmail-pro'); ?></button>
                <h4 id="tmpmp-hist-inbox-title"></h4>
                <span id="tmpmp-hist-inbox-status" class="tmpmp-hist-badge"></span>
            </div>
            <div id="tmpmp-hist-inbox-body">
                <div class="tmpmp-hist-loading">&#9203; <?php esc_html_e('Loading inbox…','tempmail-pro'); ?></div>
            </div>
        </div>

        <?php endif; ?>
    </div><!-- /#dash-tab-history -->

</div><!-- .tmpmp-dashboard-wrap -->

<script>
jQuery(function($){
    const nonce = TempMailPro.nonce, url = TempMailPro.ajax_url;

    // Tabs
    function activateTab(tab){
        // Block locked tabs (non-premium History)
        if ($('.dash-tab-btn[data-tab="'+tab+'"]').hasClass('tmpmp-tab-locked')) return;
        $('.dash-tab-btn').removeClass('is-active');
        $('.dash-tab-panel').removeClass('is-active');
        $('.dash-tab-btn[data-tab="'+tab+'"]').addClass('is-active');
        $('#dash-tab-'+tab).addClass('is-active');
        if (tab === 'history') histLoadPage(histState.page);
    }
    $('.dash-tab-btn').on('click', function(){ activateTab($(this).data('tab')); });
    activateTab('inboxes');

    // ── Address History ───────────────────────────────────────────────────────
    var histState = { page: 1, total: 0, perPage: 20, loaded: false };
    var histCurrentAddr = null; // { id, address, status_label, plan }

    function histFmt(dt){
        if (!dt) return '—';
        var d = new Date(dt.replace(' ','T')+'Z');
        return d.toLocaleDateString(undefined,{year:'numeric',month:'short',day:'numeric'})
             + ' ' + d.toLocaleTimeString(undefined,{hour:'2-digit',minute:'2-digit'});
    }
    function histPlanBadge(plan){
        var cls = plan === 'vip' ? 'vip' : plan === 'free' ? 'free' : 'premium';
        return '<span class="tmpmp-hist-badge tmpmp-hist-badge--'+cls+'">'+(plan||'free').toUpperCase()+'</span>';
    }
    function histStatusBadge(status){
        var active = status === 'active';
        return '<span class="tmpmp-hist-badge tmpmp-hist-badge--'+(active?'active':'expired')+'">'
             + (active ? '● Active' : '✕ Expired') + '</span>';
    }

    function histLoadPage(page){
        if (!$('#tmpmp-hist-table-wrap').length) return;
        histState.page = page;
        $('#tmpmp-hist-table-wrap').html('<div class="tmpmp-hist-loading">⏳ <?php esc_html_e('Loading…','tempmail-pro'); ?></div>');
        $('#tmpmp-hist-pagination').hide();
        $.post(url,{action:'tmpmp_get_address_history',nonce,page,per_page:histState.perPage},function(r){
            if (!r.success){ $('#tmpmp-hist-table-wrap').html('<div class="tmpmp-hist-empty">'+escHtml(r.data?.message||'Error')+'</div>'); return; }
            var d = r.data;
            histState.total   = d.total;
            histState.perPage = d.per_page;
            if (!d.rows || !d.rows.length){
                $('#tmpmp-hist-table-wrap').html('<div class="tmpmp-hist-empty">📭 <?php esc_html_e('No address history yet. Generate a temp email while logged in to start building history.','tempmail-pro'); ?></div>');
                return;
            }
            var html = '<div class="tmpmp-pub-table-wrap"><table class="tmpmp-history-table">'
                     + '<thead><tr>'
                     + '<th><?php esc_html_e('Address','tempmail-pro'); ?></th>'
                     + '<th><?php esc_html_e('Plan','tempmail-pro'); ?></th>'
                     + '<th><?php esc_html_e('Emails','tempmail-pro'); ?></th>'
                     + '<th><?php esc_html_e('Created','tempmail-pro'); ?></th>'
                     + '<th><?php esc_html_e('Expired','tempmail-pro'); ?></th>'
                     + '<th><?php esc_html_e('Status','tempmail-pro'); ?></th>'
                     + '<th></th>'
                     + '</tr></thead><tbody>';
            $.each(d.rows, function(_,row){
                html += '<tr class="tmpmp-hist-row" data-id="'+row.id+'" data-address="'+escAttr(row.address)+'" data-status="'+escAttr(row.status_label)+'" data-plan="'+escAttr(row.plan||'free')+'">'
                      + '<td class="tmpmp-hist-addr">'+escHtml(row.address)+'</td>'
                      + '<td>'+histPlanBadge(row.plan)+'</td>'
                      + '<td>'+parseInt(row.email_count||0)+'</td>'
                      + '<td style="font-size:12px;color:#64748b;">'+histFmt(row.created_at)+'</td>'
                      + '<td style="font-size:12px;color:#64748b;">'+histFmt(row.expires_at)+'</td>'
                      + '<td>'+histStatusBadge(row.status_label)+'</td>'
                      + '<td><div class="tmpmp-hist-actions"><button class="tmpmp-hist-del-btn" data-id="'+row.id+'">🗑</button></div></td>'
                      + '</tr>';
            });
            html += '</tbody></table></div>';
            $('#tmpmp-hist-table-wrap').html(html);

            // Pagination
            var totalPages = Math.ceil(histState.total / histState.perPage);
            if (totalPages > 1){
                var pg = '<button class="tmpmp-hist-page-btn" id="tmpmp-hist-prev" '+(page<=1?'disabled':'')+'>◀ <?php esc_html_e('Prev','tempmail-pro'); ?></button>'
                       + '<span class="tmpmp-hist-page-info"><?php esc_html_e('Page','tempmail-pro'); ?> '+page+' / '+totalPages+'</span>'
                       + '<button class="tmpmp-hist-page-btn" id="tmpmp-hist-next" '+(page>=totalPages?'disabled':'')+'>><?php esc_html_e('Next','tempmail-pro'); ?> ▶</button>';
                $('#tmpmp-hist-pagination').html(pg).show();
            } else {
                $('#tmpmp-hist-pagination').hide();
            }
        });
    }

    // Row click → open inbox
    $(document).on('click', '.tmpmp-hist-row', function(e){
        if ($(e.target).closest('.tmpmp-hist-del-btn').length) return;
        var $r = $(this);
        histCurrentAddr = { id:$r.data('id'), address:$r.data('address'), status:$r.data('status'), plan:$r.data('plan') };
        histOpenInbox(histCurrentAddr);
    });

    // Delete button
    $(document).on('click', '.tmpmp-hist-del-btn', function(e){
        e.stopPropagation();
        var id = $(this).data('id');
        if (!confirm('<?php esc_html_e('Delete this address from history? This cannot be undone.','tempmail-pro'); ?>')) return;
        var $btn = $(this).prop('disabled',true);
        $.post(url,{action:'tmpmp_delete_history_address',nonce,address_id:id},function(r){
            if (r.success) histLoadPage(histState.page);
            else { alert(r.data?.message||'<?php esc_html_e('Delete failed.','tempmail-pro'); ?>'); $btn.prop('disabled',false); }
        });
    });

    // Pagination
    $(document).on('click','#tmpmp-hist-prev',function(){ if(histState.page>1) histLoadPage(histState.page-1); });
    $(document).on('click','#tmpmp-hist-next',function(){
        var tp = Math.ceil(histState.total/histState.perPage);
        if(histState.page<tp) histLoadPage(histState.page+1);
    });

    // Back button
    $(document).on('click','#tmpmp-hist-back-btn',function(){
        $('#tmpmp-hist-inbox-view').hide();
        $('#tmpmp-hist-list-view').show();
        histCurrentAddr = null;
    });

    // Open inbox drill-down
    function histOpenInbox(addr){
        $('#tmpmp-hist-list-view').hide();
        $('#tmpmp-hist-inbox-title').text(addr.address);
        var $st = $('#tmpmp-hist-inbox-status');
        $st.attr('class','tmpmp-hist-badge tmpmp-hist-badge--'+(addr.status==='active'?'active':'expired'))
           .text(addr.status==='active'?'● Active':'✕ Expired');
        $('#tmpmp-hist-inbox-body').html('<div class="tmpmp-hist-loading">⏳ <?php esc_html_e('Loading inbox…','tempmail-pro'); ?></div>');
        $('#tmpmp-hist-inbox-view').show();

        $.post(url,{action:'tmpmp_get_history_emails',nonce,address_id:addr.id},function(r){
            if (!r.success){ $('#tmpmp-hist-inbox-body').html('<div class="tmpmp-hist-empty">'+escHtml(r.data?.message||'Error')+'</div>'); return; }
            var emails = r.data.emails;
            if (!emails || !emails.length){
                $('#tmpmp-hist-inbox-body').html('<div class="tmpmp-hist-empty">📭 <?php esc_html_e('No emails found for this address. Emails are purged when the inbox expires.','tempmail-pro'); ?></div>');
                return;
            }
            var html = '<div class="tmpmp-hist-email-list">';
            $.each(emails,function(_,em){
                var sender = em.sender_name ? escHtml(em.sender_name) : escHtml(em.sender);
                html += '<div class="tmpmp-hist-email-row'+(em.is_read?'':' unread')+'" data-email-id="'+em.id+'" data-addr-id="'+addr.id+'">'
                      + '<div class="tmpmp-hist-email-sender">'+sender+'</div>'
                      + '<div class="tmpmp-hist-email-subject">'+escHtml(em.subject||'(no subject)')+'</div>'
                      + '<div class="tmpmp-hist-email-date">'+histFmt(em.received_at)+'</div>'
                      + '</div>';
            });
            html += '</div>';
            $('#tmpmp-hist-inbox-body').html(html);
        });
    }

    // Email row click → read email body
    $(document).on('click','.tmpmp-hist-email-row',function(){
        var emailId = $(this).data('email-id'), addrId = $(this).data('addr-id');
        var $row = $(this);
        $row.addClass('is-read').removeClass('unread');
        // Remove any previously shown body
        $('.tmpmp-hist-email-body').remove();
        var $loading = $('<div class="tmpmp-hist-email-body"><div class="tmpmp-hist-loading">⏳ <?php esc_html_e('Loading…','tempmail-pro'); ?></div></div>');
        $row.after($loading);

        $.post(url,{action:'tmpmp_get_history_email_body',nonce,email_id:emailId,address_id:addrId},function(r){
            if (!r.success){ $loading.html('<div class="tmpmp-hist-email-body"><p style="color:#ef4444;padding:16px;">'+escHtml(r.data?.message||'Error')+'</p></div>'); return; }
            var em = r.data;
            var bodyHtml = em.body_html
                ? '<iframe srcdoc="'+escAttr(em.body_html)+'" style="width:100%;min-height:300px;border:none;" sandbox="allow-same-origin"></iframe>'
                : '<pre style="white-space:pre-wrap;font-size:13px;color:#475569;">'+escHtml(em.body_text||'<?php esc_html_e('(empty)','tempmail-pro'); ?>')+'</pre>';
            $loading.replaceWith(
                '<div class="tmpmp-hist-email-body">'
              + '<div class="tmpmp-hist-email-body-header">'
              + '<h5>'+escHtml(em.subject||'(no subject)')+'</h5>'
              + '<small><?php esc_html_e('From:','tempmail-pro'); ?> '+escHtml(em.sender_name?em.sender_name+' <'+em.sender+'>':em.sender)
              + ' &nbsp;·&nbsp; '+histFmt(em.received_at)+'</small>'
              + '</div>'
              + bodyHtml
              + '</div>'
            );
        });
    });


    // Cancel subscription
    $('#tmpmp-cancel-sub').on('click', function(){
        if(!confirm('<?php esc_js( esc_html_e('Cancel your subscription? You keep access until the end of the current period.','tempmail-pro') ); ?>')) return;
        $(this).prop('disabled',true);
        $.post(url,{action:'tmpmp_cancel_subscription',nonce},function(r){
            if(r.success) location.reload();
            else { alert(r.data?.message||'<?php esc_js( esc_html_e('Failed to cancel.','tempmail-pro') ); ?>'); $(this).prop('disabled',false); }
        });
    });

    // Generate API key
    $('#tmpmp-gen-key').on('click', function(){
        const label = $('#api-key-label').val().trim()||'Default';
        $.post(url,{action:'tmpmp_create_api_key',nonce,label},function(r){
            if(r.success){
                $('#tmpmp-new-key-result').show().text('&#9989; <?php esc_js( esc_html_e('New key (copy now — shown once):','tempmail-pro') ); ?> '+r.data.api_key);
                setTimeout(()=>location.reload(),5000);
            } else alert(r.data?.message||'<?php esc_js( esc_html_e('Failed.','tempmail-pro') ); ?>');
        });
    });

    // Revoke key
    $(document).on('click','.tmpmp-revoke-key', function(){
        if(!confirm('<?php esc_js( esc_html_e('Revoke this API key?','tempmail-pro') ); ?>')) return;
        const id=$(this).data('id');
        $.post(url,{action:'tmpmp_revoke_api_key',nonce,key_id:id},function(r){
            if(r.success) $('#key-row-'+id).fadeOut(300,function(){$(this).remove();});
        });
    });

    /* ── Account tab helpers ────────────────────────────────────── */
    function acctPost(action, data, btnId, msgId, onSuccess) {
        const btn = document.getElementById(btnId);
        const msg = document.getElementById(msgId);
        const orig = btn.textContent.trim();
        btn.disabled = true;
        btn.textContent = '<?php esc_html_e('Saving…','tempmail-pro'); ?>';
        msg.className = 'tmpmp-account-msg';
        const body = new URLSearchParams({action, nonce, ...data});
        fetch(url, {method:'POST', body, credentials:'same-origin'})
            .then(r => r.json())
            .then(r => {
                msg.textContent = r.data?.message || '';
                msg.className   = 'tmpmp-account-msg ' + (r.success ? 'ok' : 'err');
                if (r.success && typeof onSuccess === 'function') onSuccess(r.data);
            })
            .catch(() => {
                msg.textContent = '<?php esc_html_e('Connection error. Please try again.','tempmail-pro'); ?>';
                msg.className   = 'tmpmp-account-msg err';
            })
            .finally(() => { btn.disabled = false; btn.textContent = orig; });
    }

    // Save Profile — update header greeting on success
    document.getElementById('tmpmp-save-profile')?.addEventListener('click', function() {
        acctPost('tmpmp_update_profile', {
            first_name:   document.getElementById('acc-first-name').value.trim(),
            last_name:    document.getElementById('acc-last-name').value.trim(),
            display_name: document.getElementById('acc-display-name').value.trim(),
        }, 'tmpmp-save-profile', 'tmpmp-profile-msg', function(data) {
            // Live-update input fields with confirmed saved values
            if (data.first_name   !== undefined) document.getElementById('acc-first-name').value   = data.first_name;
            if (data.last_name    !== undefined) document.getElementById('acc-last-name').value    = data.last_name;
            if (data.display_name !== undefined) {
                document.getElementById('acc-display-name').value = data.display_name;
                // Update the greeting in the dashboard header
                const h1 = document.querySelector('.tmpmp-dash-header h1');
                if (h1) h1.innerHTML = '&#128075; <?php esc_html_e('Hi,','tempmail-pro'); ?> ' + data.display_name;
            }
        });
    });

    // Change Password
    document.getElementById('tmpmp-change-pass')?.addEventListener('click', function() {
        acctPost('tmpmp_change_password', {
            current_password: document.getElementById('acc-cur-pass').value,
            new_password:     document.getElementById('acc-new-pass').value,
            confirm_password: document.getElementById('acc-conf-pass').value,
        }, 'tmpmp-change-pass', 'tmpmp-pass-msg');
    });

    // Send Reset Email
    document.getElementById('tmpmp-send-reset')?.addEventListener('click', function() {
        const btn = this;
        const msg = document.getElementById('tmpmp-reset-msg');
        btn.disabled = true;
        btn.textContent = '<?php esc_html_e('Sending…','tempmail-pro'); ?>';
        msg.className = 'tmpmp-account-msg';
        const body = new URLSearchParams({action:'tmpmp_send_password_reset', nonce});
        fetch(url, {method:'POST', body, credentials:'same-origin'})
            .then(r => r.json())
            .then(r => {
                msg.textContent = r.data?.message || '';
                msg.className   = 'tmpmp-account-msg ' + (r.success ? 'ok' : 'err');
            })
            .catch(() => {
                msg.textContent = '<?php esc_html_e('Connection error. Please try again.','tempmail-pro'); ?>';
                msg.className   = 'tmpmp-account-msg err';
            })
            .finally(() => {
                btn.disabled = false;
                btn.textContent = '\u{1F4DC} <?php esc_html_e('Send Password Reset Email','tempmail-pro'); ?>';
            });
    });

    /* ── Avatar upload / remove ──────────────────────────────── */
    function updateAllAvatars(src) {
        document.querySelectorAll('#tmpmp-header-avatar, #acc-avatar-img').forEach(img => img.src = src);
    }

    // Clicking 'Upload Photo' or the avatar circle opens the file picker
    document.getElementById('tmpmp-upload-avatar')?.addEventListener('click', () =>
        document.getElementById('acc-avatar-file').click()
    );

    // File selected → upload immediately
    document.getElementById('acc-avatar-file')?.addEventListener('change', function() {
        const file = this.files[0];
        if (!file) return;
        const msg = document.getElementById('tmpmp-avatar-msg');
        const btn = document.getElementById('tmpmp-upload-avatar');
        const orig = btn.textContent.trim();
        btn.disabled = true;
        btn.textContent = '<?php esc_html_e('Uploading…','tempmail-pro'); ?>';
        msg.className = 'tmpmp-account-msg';

        // Local preview while uploading
        const reader = new FileReader();
        reader.onload = e => updateAllAvatars(e.target.result);
        reader.readAsDataURL(file);

        const fd = new FormData();
        fd.append('action', 'tmpmp_upload_avatar');
        fd.append('nonce',  nonce);
        fd.append('avatar', file);

        fetch(url, {method:'POST', body:fd, credentials:'same-origin'})
            .then(r => r.json())
            .then(r => {
                msg.textContent = r.data?.message || '';
                msg.className   = 'tmpmp-account-msg ' + (r.success ? 'ok' : 'err');
                if (r.success && r.data?.url) {
                    updateAllAvatars(r.data.url);
                    document.getElementById('tmpmp-remove-avatar').style.display = '';
                }
            })
            .catch(() => {
                msg.textContent = '<?php esc_html_e('Upload failed. Please try again.','tempmail-pro'); ?>';
                msg.className   = 'tmpmp-account-msg err';
            })
            .finally(() => { btn.disabled = false; btn.textContent = orig; this.value=''; });
    });

    // Remove avatar
    document.getElementById('tmpmp-remove-avatar')?.addEventListener('click', function() {
        const msg = document.getElementById('tmpmp-avatar-msg');
        const btn = this;
        btn.disabled = true;
        msg.className = 'tmpmp-account-msg';
        const body = new URLSearchParams({action:'tmpmp_remove_avatar', nonce});
        fetch(url, {method:'POST', body, credentials:'same-origin'})
            .then(r => r.json())
            .then(r => {
                msg.textContent = r.data?.message || '';
                msg.className   = 'tmpmp-account-msg ' + (r.success ? 'ok' : 'err');
                if (r.success) {
                    if (r.data?.url) updateAllAvatars(r.data.url);
                    btn.style.display = 'none';
                }
            })
            .catch(() => {
                msg.textContent = '<?php esc_html_e('Connection error. Please try again.','tempmail-pro'); ?>';
                msg.className   = 'tmpmp-account-msg err';
            })
            .finally(() => { btn.disabled = false; });
    });
});

// ── Password Generator ──────────────────────────────────────────────────
(function() {
    function genPass(len) {
        len = len || 18;
        var lower='abcdefghijklmnopqrstuvwxyz', upper='ABCDEFGHIJKLMNOPQRSTUVWXYZ',
            digits='0123456789', syms='!@#$%^&*_+-=';
        var all = lower+upper+digits+syms;
        var arr = new Uint8Array(len);
        crypto.getRandomValues(arr);
        var pass = [
            lower[arr[0]%lower.length], upper[arr[1]%upper.length],
            digits[arr[2]%digits.length], syms[arr[3]%syms.length]
        ];
        for (var i=4;i<len;i++) pass.push(all[arr[i]%all.length]);
        for (var i=pass.length-1;i>0;i--) {
            var j=arr[i%arr.length]%(i+1);
            var t=pass[i]; pass[i]=pass[j]; pass[j]=t;
        }
        return pass.join('');
    }
    function passStrength(p) {
        var s=0;
        if(p.length>=8) s++; if(p.length>=12) s++;
        if(/[A-Z]/.test(p)) s++; if(/[0-9]/.test(p)) s++; if(/[^A-Za-z0-9]/.test(p)) s++;
        var lbl=['','Weak','Fair','Good','Strong','Very Strong'];
        var col=['','#ef4444','#f97316','#eab308','#22c55e','#10b981'];
        return { score:s, label:lbl[s]||'Very Strong', color:col[s]||'#10b981' };
    }
    function updateStrength(inputId) {
        var inp = document.getElementById(inputId);
        var sw  = document.getElementById(inputId+'-sw');
        if (!inp||!sw) return;
        if (!inp.value) { sw.style.display='none'; return; }
        var s = passStrength(inp.value);
        var bars = sw.querySelectorAll('.tmpmp-pass-strength-bars span');
        bars.forEach(function(b,i){ b.style.background = i<s.score ? s.color : '#e2e8f0'; });
        var lbl = sw.querySelector('.tmpmp-pass-strength-label');
        if(lbl){ lbl.textContent=s.label; lbl.style.color=s.color; }
        sw.style.display='flex';
    }
    document.addEventListener('click', function(e) {
        var btn = e.target.closest('.tmpmp-pass-eye');
        if (!btn) return;
        var inp = document.getElementById(btn.dataset.target);
        if (!inp) return;
        inp.type = inp.type==='password' ? 'text' : 'password';
        btn.textContent = inp.type==='text' ? '🙈' : '👁';
    });
    document.addEventListener('click', function(e) {
        var btn = e.target.closest('.tmpmp-gen-pass-btn');
        if (!btn) return;
        var pass = genPass(18);
        var targets = (btn.dataset.targets||'').split(',').filter(Boolean);
        targets.forEach(function(id) {
            id = id.trim();
            var inp = document.getElementById(id);
            if (!inp) return;
            inp.value = pass;
            inp.type  = 'text';
            inp.dispatchEvent(new Event('input'));
            updateStrength(id);
            var eye = inp.closest && inp.closest('.tmpmp-pass-input-wrap') && inp.closest('.tmpmp-pass-input-wrap').querySelector('.tmpmp-pass-eye');
            if (eye) eye.textContent = '🙈';
            var copy = document.getElementById(id+'-copy');
            if (copy) copy.style.display='';
        });
        var sw = document.getElementById((targets[0]||'').trim()+'-sw');
        if (sw) sw.style.display='flex';
        btn.textContent = '🔄 Regenerate';
    });
    document.addEventListener('click', function(e) {
        var btn = e.target.closest('.tmpmp-pass-copy-btn');
        if (!btn) return;
        var inp = document.getElementById(btn.dataset.target);
        if (!inp || !inp.value) return;

        function onCopied() {
            var orig = btn.innerHTML;
            btn.innerHTML = '✓ Copied!';
            setTimeout(function(){ btn.innerHTML = orig; }, 1600);
        }

        // Prefer modern Clipboard API (HTTPS only)
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(inp.value).then(onCopied).catch(function() {
                fallbackCopy(inp.value, onCopied);
            });
        } else {
            fallbackCopy(inp.value, onCopied);
        }
    });

    function fallbackCopy(text, cb) {
        var ta = document.createElement('textarea');
        ta.value = text;
        ta.style.cssText = 'position:fixed;top:-9999px;left:-9999px;opacity:0;';
        document.body.appendChild(ta);
        ta.focus();
        ta.select();
        try {
            var ok = document.execCommand('copy');
            if (ok && typeof cb === 'function') cb();
        } catch(err) {}
        document.body.removeChild(ta);
    }
    document.addEventListener('input', function(e) {
        var inp = e.target;
        if (!inp.id) return;
        updateStrength(inp.id);
    });
})();
</script>
