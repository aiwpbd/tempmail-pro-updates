<?php
if ( ! defined('ABSPATH') ) exit;
$user    = wp_get_current_user();
$sub     = TempMail_Database::get_user_subscription($user->ID);
$plan    = TempMail_Subscription::get_user_plan_data($user->ID);
// Current plan slug for display — reflects the user's live subscription, not per-address stored plan
$current_plan_slug = $sub ? ( $plan->plan_slug ?? $plan->slug ?? 'pro' ) : 'free';
global $wpdb;
$my_addresses = $wpdb->get_results($wpdb->prepare(
    "SELECT a.*, (SELECT COUNT(*) FROM {$wpdb->prefix}tmpmp_emails e WHERE e.address_id=a.id) as email_count
     FROM {$wpdb->prefix}tmpmp_addresses a WHERE a.user_id=%d ORDER BY a.created_at DESC LIMIT 500",
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

// ── Pre-load permanent inboxes for instant render ─────────────────────────
$perm_inboxes    = $is_premium ? TempMail_Database::get_permanent_inboxes_for_user( $user->ID ) : [];
$perm_sub        = TempMail_Database::get_user_subscription( $user->ID );
$perm_max        = isset( $perm_sub->max_permanent_inboxes ) ? (int) $perm_sub->max_permanent_inboxes : 0;
$perm_can_create = $is_premium && ( $perm_max === -1 || count( $perm_inboxes ) < $perm_max );



// ── Server-side domain add (form POST, no AJAX required) ──────────────────
$_tmpmp_domain_msg   = '';
$_tmpmp_domain_type  = ''; // 'ok' | 'err'
$_tmpmp_domain_debug = []; // visible debug info (cleared on success)
if ( isset( $_POST['tmpmp_add_domain_submit'] ) ) {
    $nonce_ok = wp_verify_nonce( $_POST['tmpmp_add_domain_nonce'] ?? '', 'tmpmp_add_domain_' . $user->ID );
    $_tmpmp_domain_debug[] = 'POST received. User ID: ' . $user->ID . ' | nonce_ok: ' . ( $nonce_ok ? 'YES' : 'NO' );
    error_log( '[TmpmpDomain] POST received. user=' . $user->ID . ' nonce_ok=' . ( $nonce_ok ? '1' : '0' ) );

    if ( ! $nonce_ok ) {
        $_tmpmp_domain_msg  = __( 'Security check failed. Please refresh the page and try again.', 'tempmail-pro' );
        $_tmpmp_domain_type = 'err';
    } else {
        $wpdb->suppress_errors( true );

        // Step 1: Create table
        $ud_table = $wpdb->prefix . 'tmpmp_user_domains';
        $charset  = $wpdb->get_charset_collate();
        $wpdb->query(
            "CREATE TABLE IF NOT EXISTS `{$ud_table}` (
                id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                user_id          BIGINT UNSIGNED NOT NULL,
                domain           VARCHAR(255) NOT NULL,
                status           VARCHAR(32)  NOT NULL DEFAULT 'pending',
                verify_token     VARCHAR(128) NOT NULL DEFAULT '',
                txt_verified     TINYINT(1)   NOT NULL DEFAULT 0,
                mx_verified      TINYINT(1)   NOT NULL DEFAULT 0,
                spf_verified     TINYINT(1)   NOT NULL DEFAULT 0,
                dkim_selector    VARCHAR(64)  NOT NULL DEFAULT 'tmpro',
                dkim_private_key LONGTEXT,
                dkim_public_key  LONGTEXT,
                dkim_verified    TINYINT(1)   NOT NULL DEFAULT 0,
                dmarc_verified   TINYINT(1)   NOT NULL DEFAULT 0,
                last_checked     DATETIME     DEFAULT NULL,
                verified_at      DATETIME     DEFAULT NULL,
                created_at       DATETIME     NOT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY user_domain (user_id, domain),
                KEY status  (status),
                KEY user_id (user_id)
            ) {$charset}"
        );
        $create_err = $wpdb->last_error;
        $_tmpmp_domain_debug[] = 'CREATE TABLE error: ' . ( $create_err ?: 'none' );
        error_log( '[TmpmpDomain] CREATE TABLE last_error: ' . ( $create_err ?: 'none' ) );

        // Step 2: Check subscription — show raw DB value
        $sub_row = $wpdb->get_row( $wpdb->prepare(
            "SELECT id, plan_id, status FROM `{$wpdb->prefix}tmpmp_subscriptions`
             WHERE user_id = %d ORDER BY created_at DESC LIMIT 1",
            $user->ID
        ) );
        $sub_err = $wpdb->last_error;
        $_tmpmp_domain_debug[] = 'Subscription row: ' . ( $sub_row ? "id={$sub_row->id} plan={$sub_row->plan_id} status={$sub_row->status}" : 'NULL' ) . ' | db_err: ' . ( $sub_err ?: 'none' );
        error_log( '[TmpmpDomain] sub_row: ' . json_encode( $sub_row ) . ' last_error: ' . $sub_err );

        $has_sub = $sub_row && in_array( $sub_row->status, [ 'active', 'trialing' ], true );
        $_tmpmp_domain_debug[] = 'has_sub: ' . ( $has_sub ? 'YES' : 'NO (status mismatch or no row)' );

        if ( ! $has_sub ) {
            $_tmpmp_domain_msg  = sprintf(
                __( 'No active subscription found. DB status: %s', 'tempmail-pro' ),
                $sub_row ? esc_html( $sub_row->status ) : 'no row'
            );
            $_tmpmp_domain_type = 'err';
        } else {
            // Step 3: Add domain
            $raw_domain = sanitize_text_field( wp_unslash( $_POST['tmpmp_domain'] ?? '' ) );
            $_tmpmp_domain_debug[] = 'Calling add() with domain: ' . $raw_domain;
            error_log( '[TmpmpDomain] calling add() domain=' . $raw_domain );

            $result = TempMail_UserDomains::add( $user->ID, $raw_domain );
            $add_db_err = $wpdb->last_error;
            $_tmpmp_domain_debug[] = 'add() result: ' . ( is_wp_error( $result ) ? 'WP_Error: ' . $result->get_error_message() : 'ID=' . $result ) . ' | db_err: ' . ( $add_db_err ?: 'none' );
            error_log( '[TmpmpDomain] add() result=' . ( is_wp_error( $result ) ? 'ERR:' . $result->get_error_message() : $result ) . ' db_err=' . $add_db_err );

            if ( is_wp_error( $result ) ) {
                $_tmpmp_domain_msg  = $result->get_error_message();
                $_tmpmp_domain_type = 'err';
            } else {
                $_tmpmp_domain_msg   = __( 'Domain added! Configure the DNS records below.', 'tempmail-pro' );
                $_tmpmp_domain_type  = 'ok';
                $_tmpmp_domain_debug = []; // clear debug on success
                $my_custom_domains_fresh = TempMail_UserDomains::get_for_user( $user->ID );
            }
        }
    }
}

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

/* Inbox App tab skeleton shimmer */
.tmpmp-skel {
    background: linear-gradient(90deg, #f1f5f9 25%, #e2e8f0 50%, #f1f5f9 75%);
    background-size: 200% 100%;
    animation: tmpmp-skel-shine 1.4s infinite;
    display: block;
}
@keyframes tmpmp-skel-shine {
    0%   { background-position: 200% 0; }
    100% { background-position: -200% 0; }
}

/* ── Inbox App tab: strip redundant elements that duplicate the dashboard ── */
#dash-tab-inbox-app .tmpmp-account-bar          { display: none !important; }
#dash-tab-inbox-app .tmpmp-wrap                 { padding-top: 0 !important; margin-top: 0 !important; }
#dash-tab-inbox-app .tmpmp-faq                  { display: none !important; }
#dash-tab-inbox-app [class*="tmpmp-ad-"]        { display: none !important; }
#dash-tab-inbox-app .tmpmp-ad-slot              { display: none !important; }

/* Ensure pill tabs scroll horizontally on any screen that can’t fit all 6 tabs */
.tmpmp-dash-tabs {
    display: flex !important;
    overflow-x: auto !important;
    flex-wrap: wrap !important;
    width: 100%;
    box-sizing: border-box;
}
.dash-tab-btn { flex-shrink: 0; white-space: nowrap; }

/* ── Permanent Inbox tab styles ─────────────────────────────────────────── */
.tmpmp-perm-header      { display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:12px; margin-bottom:20px; }
.tmpmp-perm-header-left { display:flex; align-items:center; gap:10px; font-size:16px; font-weight:800; color:#0f172a; }

.tmpmp-perm-count-badge { background:#6366f1; color:#fff; font-size:11px; font-weight:700; padding:2px 8px; border-radius:20px; }
.tmpmp-perm-cards       { display:grid; grid-template-columns:repeat(auto-fill,minmax(280px,1fr)); gap:16px; }
.tmpmp-perm-card        { background:#fff; border:1.5px solid #e2e8f0; border-radius:14px; padding:18px 20px; display:flex; flex-direction:column; gap:8px; transition:box-shadow .15s; }
.tmpmp-perm-card:hover  { box-shadow:0 4px 20px rgba(99,102,241,.12); border-color:#c7d2fe; }
.tmpmp-perm-card-addr   { font-family:monospace; font-weight:700; color:#4f46e5; font-size:13px; word-break:break-all; }
@keyframes tmpmp-perm-saving-pulse { 0%,100%{opacity:1} 50%{opacity:.6} }
.tmpmp-perm-card--saving { animation:tmpmp-perm-saving-pulse 1.2s ease-in-out infinite; pointer-events:none; }
.tmpmp-perm-card--saving .tmpmp-perm-card-addr::after { content:' …'; color:#94a3b8; font-weight:400; font-size:11px; }

/* Copy address button */
.tmpmp-perm-copy-wrap   { display:inline-flex; align-items:center; gap:6px; cursor:pointer; position:relative;
                           border-radius:8px; padding:3px 7px 3px 4px; transition:background .15s, color .15s;
                           user-select:none; max-width:100%; }
.tmpmp-perm-copy-wrap:hover  { background:#ede9fe; }
.tmpmp-perm-copy-wrap:hover .tmpmp-perm-copy-icon { opacity:1; }
.tmpmp-perm-copy-icon   { opacity:0; flex-shrink:0; transition:opacity .15s; color:#6366f1; }
.tmpmp-perm-copy-wrap.copied { background:#d1fae5 !important; color:#065f46 !important; }
.tmpmp-perm-copy-wrap.copied .tmpmp-perm-card-addr { color:#065f46 !important; }
.tmpmp-perm-copy-wrap.copied .tmpmp-perm-copy-icon { opacity:1; color:#16a34a !important; }
/* Tooltip */
.tmpmp-perm-copy-wrap::after {
    content:'Click to copy';
    position:absolute; bottom:calc(100% + 6px); left:50%; transform:translateX(-50%);
    background:#1e293b; color:#fff; font-size:11px; font-weight:600;
    padding:4px 9px; border-radius:6px; white-space:nowrap;
    opacity:0; pointer-events:none; transition:opacity .15s;
    font-family:inherit;
}
.tmpmp-perm-copy-wrap:hover::after  { opacity:1; }
.tmpmp-perm-copy-wrap.copied::after { content:'Copied! ✓'; background:#16a34a; opacity:1; }

.tmpmp-perm-badge       { display:inline-flex; align-items:center; gap:4px; background:#f0fdf4; color:#16a34a; border:1px solid #bbf7d0; border-radius:20px; font-size:11px; font-weight:700; padding:2px 9px; }
.tmpmp-perm-meta        { font-size:12px; color:#64748b; display:flex; align-items:center; gap:6px; flex-wrap:wrap; }
.tmpmp-perm-meta-count  { font-weight:700; color:#1e293b; }
.tmpmp-perm-unread-dot  { display:inline-flex; align-items:center; gap:3px; background:#ef4444;
                           color:#fff; font-size:10px; font-weight:800; padding:1px 7px;
                           border-radius:20px; white-space:nowrap; animation:tmpmp-dot-pop .3s ease; }
.tmpmp-perm-unread-dot[hidden] { display:none !important; }
@keyframes tmpmp-dot-pop { from{transform:scale(.6);opacity:0} to{transform:scale(1);opacity:1} }
.tmpmp-perm-actions     { display:flex; gap:8px; margin-top:6px; flex-wrap:wrap; }
.tmpmp-perm-btn         { font-size:12px; font-weight:600; padding:5px 12px; border-radius:8px; border:1.5px solid; cursor:pointer; transition:all .15s; background:transparent; }
.tmpmp-perm-btn--view   { border-color:#6366f1; color:#6366f1; }
.tmpmp-perm-btn--view:hover   { background:#6366f1; color:#fff; }
.tmpmp-perm-btn--del    { border-color:#ef4444; color:#ef4444; }
.tmpmp-perm-btn--del:hover    { background:#ef4444; color:#fff; }
.tmpmp-perm-btn--exp    { border-color:#0ea5e9; color:#0ea5e9; }
.tmpmp-perm-btn--exp:hover    { background:#0ea5e9; color:#fff; }
/* Export dropdown */
.tmpmp-exp-wrap         { position:relative; }
.tmpmp-exp-menu         { display:none; position:absolute; top:calc(100% + 4px); right:0; background:#fff; border:1.5px solid #e2e8f0; border-radius:10px; box-shadow:0 8px 24px rgba(0,0,0,.1); z-index:200; min-width:120px; overflow:hidden; }
.tmpmp-exp-menu.open    { display:block; }
.tmpmp-exp-menu button  { display:block; width:100%; padding:9px 16px; font-size:13px; font-weight:600; text-align:left; background:transparent; border:none; cursor:pointer; color:#1e293b; }
.tmpmp-exp-menu button:hover { background:#f1f5f9; }
/* ── Email viewer modal ─────────────────────────────────────────────────── */
.tmpmp-view-modal-bg    { display:none; position:fixed; inset:0; background:rgba(15,23,42,.55);
                           backdrop-filter:blur(4px); -webkit-backdrop-filter:blur(4px);
                           z-index:10000; align-items:center; justify-content:center; padding:16px; }
.tmpmp-view-modal-bg.open { display:flex; animation:tmpmp-vm-fade .18s ease; }
@keyframes tmpmp-vm-fade { from{opacity:0} to{opacity:1} }
.tmpmp-view-modal       { background:#fff; border-radius:20px; width:min(700px,100%); max-height:88vh;
                           display:flex; flex-direction:column; box-shadow:0 24px 80px rgba(0,0,0,.22);
                           animation:tmpmp-vm-pop .2s cubic-bezier(.34,1.56,.64,1); overflow:hidden; }
@keyframes tmpmp-vm-pop { from{opacity:0;transform:scale(.93) translateY(16px)} to{opacity:1;transform:none} }
.tmpmp-view-modal-hdr   { display:flex; align-items:center; gap:10px; padding:18px 22px 16px;
                           border-bottom:1px solid #e2e8f0; flex-shrink:0; }
.tmpmp-view-modal-back  { display:none; background:none; border:none; cursor:pointer; padding:6px 9px;
                           border-radius:8px; color:#6366f1; font-size:18px; line-height:1;
                           transition:background .15s; flex-shrink:0; }
.tmpmp-view-modal-back:hover  { background:#ede9fe; }
.tmpmp-view-modal-back.visible{ display:inline-flex; align-items:center; }
.tmpmp-view-modal-title { flex:1; min-width:0; }
.tmpmp-view-modal-title h3 { margin:0; font-size:16px; font-weight:800; color:#0f172a;
                              white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.tmpmp-view-modal-title p  { margin:2px 0 0; font-size:12px; color:#64748b; }
.tmpmp-view-modal-close { background:none; border:none; cursor:pointer; font-size:20px; color:#94a3b8;
                           padding:5px 8px; border-radius:8px; line-height:1; flex-shrink:0;
                           transition:all .15s; }
.tmpmp-view-modal-close:hover { background:#f1f5f9; color:#0f172a; }
.tmpmp-view-modal-body  { flex:1; overflow-y:auto; overflow-x:hidden; }
/* Email list */
.tmpmp-email-list       { list-style:none; margin:0; padding:8px 0; }
.tmpmp-email-list-item  { display:flex; flex-direction:column; gap:3px; padding:14px 44px 14px 22px;
                           cursor:pointer; border-bottom:1px solid #f1f5f9; position:relative;
                           transition:background .12s; }
.tmpmp-email-list-item:last-child { border-bottom:none; }
.tmpmp-email-list-item:hover { background:#f8fafc; }
.tmpmp-email-list-item::after { content:'›'; position:absolute; right:18px; top:50%;
                                  transform:translateY(-50%); color:#cbd5e1; font-size:22px; font-weight:300; }
.tmpmp-email-list-item.tmpmp-unread { border-left:3px solid #6366f1; padding-left:19px; }
.tmpmp-email-list-subj  { font-weight:700; font-size:13.5px; color:#1e293b;
                           display:flex; align-items:center; gap:7px; overflow:hidden; }
.tmpmp-email-list-subj-txt { white-space:nowrap; overflow:hidden; text-overflow:ellipsis; flex:1; min-width:0; }
.tmpmp-unread .tmpmp-email-list-subj-txt { color:#0f172a; font-weight:800; }
.tmpmp-email-unread-badge { display:inline-flex; align-items:center; gap:3px; flex-shrink:0;
                             background:linear-gradient(135deg,#6366f1,#4f46e5);
                             color:#fff; font-size:10px; font-weight:800; letter-spacing:.4px;
                             padding:2px 8px; border-radius:20px; white-space:nowrap;
                             box-shadow:0 0 0 0 rgba(99,102,241,.5);
                             animation:tmpmp-unread-pulse 2.2s ease-in-out infinite;
                             transition:opacity .25s; }
.tmpmp-email-unread-badge.hidden { opacity:0; pointer-events:none; }
@keyframes tmpmp-unread-pulse {
    0%,100%{box-shadow:0 0 0 0 rgba(99,102,241,.45);}
    50%{box-shadow:0 0 0 6px rgba(99,102,241,0);}
}
.tmpmp-email-list-meta  { font-size:11.5px; color:#64748b; display:flex; gap:10px; flex-wrap:wrap; }
.tmpmp-email-list-sender{ flex:1; min-width:0; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.tmpmp-email-list-date  { flex-shrink:0; }
.tmpmp-email-list-none  { text-align:center; padding:52px 24px; color:#94a3b8; }
.tmpmp-email-list-none span { font-size:44px; display:block; margin-bottom:12px; line-height:1; }
.tmpmp-email-list-none p    { font-size:14px; font-weight:600; margin:0 0 4px; }
/* Email body */
.tmpmp-email-body-meta  { padding:16px 22px 14px; border-bottom:1px solid #e2e8f0; background:#f8fafc; flex-shrink:0; }
.tmpmp-email-body-subj  { font-size:15px; font-weight:800; color:#0f172a; margin:0 0 10px; word-break:break-word; }
.tmpmp-email-body-row   { display:flex; gap:6px; font-size:12px; color:#64748b; margin-top:5px; flex-wrap:wrap; }
.tmpmp-email-body-row strong { color:#374151; min-width:36px; }
.tmpmp-email-body-frame { border:none; width:100%; min-height:340px; display:block; overflow:hidden; max-width:100%; box-sizing:border-box; }
.tmpmp-email-body-plain { padding:20px 22px; font-size:13px; color:#374151; line-height:1.75;
                           white-space:pre-wrap; word-break:break-word; }
/* Skeleton loader */
.tmpmp-view-skel        { padding:14px 22px; border-bottom:1px solid #f1f5f9; }
.tmpmp-view-skel-line   { height:13px; border-radius:6px; margin-bottom:8px;
                           background:linear-gradient(90deg,#f1f5f9 25%,#e2e8f0 50%,#f1f5f9 75%);
                           background-size:200% 100%; animation:tmpmp-skel-sh 1.3s infinite; }
@keyframes tmpmp-skel-sh { 0%{background-position:200% 0} 100%{background-position:-200% 0} }
@media (max-width:580px) {
    .tmpmp-view-modal   { border-radius:14px; max-height:95vh; }
    .tmpmp-email-list-item { padding:12px 38px 12px 16px; }
    .tmpmp-view-modal-hdr  { padding:14px 16px 12px; }
    .tmpmp-email-body-meta { padding:12px 16px 10px; }
    .tmpmp-email-body-plain{ padding:14px 16px; }
}
/* Create modal */
.tmpmp-perm-modal-bg    { display:none; position:fixed; inset:0; background:rgba(0,0,0,.45); z-index:10000; align-items:center; justify-content:center; }
.tmpmp-perm-modal-bg.open { display:flex; }
.tmpmp-perm-modal       { background:#fff; border-radius:18px; padding:28px 28px 24px; width:min(480px,90vw); box-shadow:0 20px 60px rgba(0,0,0,.18); }
.tmpmp-perm-modal h3    { margin:0 0 6px; font-size:17px; font-weight:700; color:#1e293b; }
.tmpmp-perm-modal p     { margin:0 0 18px; font-size:13px; color:#64748b; }
.tmpmp-perm-modal label { display:block; font-size:12px; font-weight:600; color:#374151; margin-bottom:5px; }
.tmpmp-perm-modal select,
.tmpmp-perm-modal input  { width:100%; padding:9px 12px; border:1.5px solid #e2e8f0; border-radius:9px; font-size:14px; margin-bottom:14px; box-sizing:border-box; }
.tmpmp-perm-modal select:focus,
.tmpmp-perm-modal input:focus { outline:none; border-color:#6366f1; }
.tmpmp-perm-modal-footer{ display:flex; gap:10px; justify-content:flex-end; margin-top:4px; }
.tmpmp-perm-btn--cancel { background:#f1f5f9; color:#374151; border:none; border-radius:9px; padding:9px 18px; font-weight:600; font-size:13px; cursor:pointer; }
.tmpmp-perm-btn--create { background:#6366f1; color:#fff; border:none; border-radius:9px; padding:9px 18px; font-weight:700; font-size:13px; cursor:pointer; }
.tmpmp-perm-btn--create:disabled { opacity:.6; cursor:not-allowed; }
.tmpmp-perm-error       { color:#ef4444; font-size:13px; margin-bottom:10px; display:none; }

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

/* Mobile: stack header vertically, horizontal-scroll tabs */
@media (max-width: 640px) {
    .tmpmp-dashboard-wrap { padding: 14px 10px !important; }
    .tmpmp-dash-header {
        flex-direction: column !important;
        align-items: flex-start !important;
        gap: 10px;
        padding: 14px 16px !important;
    }
    .tmpmp-dash-actions { width: 100%; display: flex; flex-wrap: wrap; gap: 8px; }
    .tmpmp-dash-header h1 { font-size: 17px; }
    .tmpmp-dash-tabs {
        flex-wrap: nowrap !important;
        overflow-x: auto !important;
        scrollbar-width: none;
        -ms-overflow-style: none;
        gap: 2px !important;
        padding: 3px !important;
        border-radius: 10px !important;
    }
    .tmpmp-dash-tabs::-webkit-scrollbar { display: none; }
    .dash-tab-btn {
        padding: 8px 12px !important;
        font-size: 12px !important;
        border-radius: 7px !important;
        flex-shrink: 0 !important;
    }
    .dash-tab-btn.is-active {
        background: #ffffff !important;
        color: #6366f1 !important;
        box-shadow: 0 2px 8px rgba(99,102,241,.12), 0 0 0 1px rgba(99,102,241,.1) !important;
    }
    .tmpmp-billing-active { padding: 14px !important; }
}
@media (max-width: 540px) {
    .tmpmp-perm-header { flex-direction:column; align-items:stretch; }
    .tmpmp-perm-header-left { justify-content:center; }
    #tmpmp-perm-create-btn { width:100%; justify-content:center; }
}
@media (max-width: 380px) {
    .tmpmp-dash-actions { flex-direction: column; }
    .tmpmp-dash-actions .tmpmp-pub-btn,
    .tmpmp-dash-actions .tmpmp-pub-badge { text-align: center; width: 100%; }
    .dash-tab-btn { font-size: 11px !important; padding: 7px 8px !important; }
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

/* ── Danger Zone (Delete Account) ───────────────────────────────────────── */
.tmpmp-danger-card {
    border-color: #fecaca !important;
    background: #fff5f5 !important;
}
.tmpmp-danger-card h3 { color: #dc2626 !important; }
.tmpmp-pub-btn--danger {
    background: linear-gradient(135deg, #ef4444, #dc2626);
    color: #fff;
    border: none;
    border-radius: 10px;
    padding: 10px 20px;
    font-size: 14px;
    font-weight: 700;
    cursor: pointer;
    transition: all .2s;
    width: 100%;
    margin-top: 4px;
    display: block;
}
.tmpmp-pub-btn--danger:hover { opacity:.88; transform:translateY(-1px); }

/* ── Delete Account Modal ────────────────────────────────────────────────── */
#tmpmp-delete-modal {
    display: none;
    position: fixed;
    inset: 0;
    z-index: 9999999;
    align-items: center;
    justify-content: center;
    padding: 16px;
}
#tmpmp-delete-modal.open { display: flex; }
#tmpmp-delete-backdrop {
    position: absolute;
    inset: 0;
    background: rgba(15,23,42,.6);
    backdrop-filter: blur(4px);
}
#tmpmp-delete-box {
    position: relative;
    background: #fff;
    border-radius: 18px;
    box-shadow: 0 24px 64px rgba(0,0,0,.24), 0 4px 16px rgba(0,0,0,.1);
    padding: 32px;
    width: 100%;
    max-width: 440px;
    z-index: 1;
    animation: tmpmpDelIn .22s cubic-bezier(.34,1.56,.64,1);
}
@keyframes tmpmpDelIn { from{opacity:0;transform:scale(.92) translateY(14px)} to{opacity:1;transform:none} }
#tmpmp-delete-box .del-modal-icon {
    font-size: 40px;
    text-align: center;
    margin-bottom: 12px;
}
#tmpmp-delete-box h4 {
    margin: 0 0 8px;
    font-size: 19px;
    font-weight: 800;
    color: #0f172a;
    text-align: center;
}
#tmpmp-delete-box .del-modal-warn {
    background: #fef2f2;
    border: 1.5px solid #fecaca;
    border-radius: 10px;
    padding: 12px 16px;
    font-size: 13px;
    color: #991b1b;
    line-height: 1.65;
    margin-bottom: 20px;
    text-align: center;
}
#tmpmp-delete-box label {
    display: block;
    font-size: 12px;
    font-weight: 700;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: .4px;
    margin-bottom: 6px;
}
#tmpmp-delete-confirm-email {
    width: 100%;
    padding: 10px 14px;
    border: 1.5px solid #e2e8f0;
    border-radius: 9px;
    font-size: 14px;
    color: #0f172a;
    background: #f8fafc;
    box-sizing: border-box;
    outline: none;
    font-family: inherit;
    transition: border-color .18s;
    margin-bottom: 6px;
}
#tmpmp-delete-confirm-email:focus { border-color: #ef4444; background: #fff; }
#tmpmp-delete-confirm-email.invalid { border-color: #ef4444; }
.del-modal-footer {
    display: flex;
    gap: 10px;
    margin-top: 18px;
}
.del-modal-footer button { flex: 1; }
#tmpmp-delete-cancel {
    padding: 10px 16px;
    background: #f1f5f9;
    border: none;
    border-radius: 9px;
    font-size: 14px;
    font-weight: 700;
    cursor: pointer;
    color: #64748b;
    transition: all .15s;
}
#tmpmp-delete-cancel:hover { background: #e2e8f0; }
#tmpmp-delete-confirm {
    padding: 10px 16px;
    background: linear-gradient(135deg, #ef4444, #dc2626);
    color: #fff;
    border: none;
    border-radius: 9px;
    font-size: 14px;
    font-weight: 800;
    cursor: pointer;
    transition: all .2s;
}
#tmpmp-delete-confirm:disabled { opacity:.45; cursor:not-allowed; transform:none; }
#tmpmp-delete-confirm:not(:disabled):hover { opacity:.88; }
#tmpmp-delete-modal-msg {
    margin-top: 10px;
    padding: 9px 13px;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 600;
    display: none;
    text-align: center;
}
#tmpmp-delete-modal-msg.err { background:#fef2f2; color:#991b1b; border:1px solid #fecaca; display:block; }

/* ── Custom Domains Tab ───────────────────────────────────────────────── */
.tmpmp-ud-card {
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    padding: 20px 22px;
    margin-bottom: 14px;
    box-shadow: 0 2px 8px rgba(0,0,0,.04);
}
.tmpmp-ud-card-header {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
}
.tmpmp-ud-domain-name {
    font-size: 15px;
    font-weight: 800;
    color: #0f172a;
    font-family: monospace;
    flex: 1;
    min-width: 0;
    word-break: break-all;
}
.tmpmp-ud-status-badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 4px 11px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 700;
    white-space: nowrap;
}
.tmpmp-ud-status-badge.pending  { background:#fef9c3; color:#92400e; }
.tmpmp-ud-status-badge.verified { background:#dcfce7; color:#065f46; }
.tmpmp-ud-status-badge.failed   { background:#fee2e2; color:#991b1b; }
.tmpmp-ud-status-badge.checking { background:#dbeafe; color:#1d4ed8; }
.tmpmp-ud-actions { display:flex; gap:8px; flex-shrink:0; }
.tmpmp-ud-btn {
    padding: 7px 14px;
    border-radius: 8px;
    font-size: 12.5px;
    font-weight: 700;
    cursor: pointer;
    border: none;
    transition: all .15s;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    white-space: nowrap;
}
.tmpmp-ud-btn--verify { background:#ede9fe; color:#5b21b6; }
.tmpmp-ud-btn--verify:hover { background:#ddd6fe; }
.tmpmp-ud-btn--delete { background:#fee2e2; color:#991b1b; }
.tmpmp-ud-btn--delete:hover { background:#fecaca; }
.tmpmp-ud-btn:disabled { opacity:.5; cursor:not-allowed; }
/* Accordion */
.tmpmp-ud-accordion-toggle {
    margin-top: 14px;
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 12.5px;
    font-weight: 600;
    color: #4f46e5;
    cursor: pointer;
    background: #f5f3ff;
    border: 1.5px solid #e0e7ff;
    border-radius: 10px;
    padding: 9px 14px 9px 17px;
    width: 100%;
    text-align: left;
    transition: background 0.18s ease, border-color 0.18s ease, box-shadow 0.18s ease, color 0.18s ease;
    position: relative;
    overflow: hidden;
    box-sizing: border-box;
    line-height: 1.4;
}
/* Left accent stripe */
.tmpmp-ud-accordion-toggle::before {
    content: '';
    position: absolute;
    left: 0; top: 0; bottom: 0;
    width: 4px;
    background: linear-gradient(180deg, #818cf8, #6366f1);
    border-radius: 10px 0 0 10px;
}
/* Arrow icon */
.tmpmp-ud-accordion-toggle .tmpmp-ud-acc-arrow {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-style: normal;
    font-size: 10px;
    color: #818cf8;
    transition: transform 0.22s ease;
    flex-shrink: 0;
    margin-right: 2px;
}
.tmpmp-ud-accordion-toggle.open .tmpmp-ud-acc-arrow {
    transform: rotate(90deg);
}
/* Verified count pushed to right */
.tmpmp-ud-accordion-toggle .tmpmp-ud-acc-count {
    margin-left: auto;
    font-size: 11px;
    font-weight: 700;
    background: #ede9fe;
    color: #6366f1;
    border: 1px solid #c4b5fd;
    border-radius: 20px;
    padding: 1px 9px;
    flex-shrink: 0;
    white-space: nowrap;
    transition: background 0.18s, color 0.18s;
}
/* Hover */
.tmpmp-ud-accordion-toggle:hover {
    background: #ede9fe;
    border-color: #a5b4fc;
    color: #3730a3;
    box-shadow: 0 3px 14px rgba(99,102,241,0.13);
}
.tmpmp-ud-accordion-toggle:hover .tmpmp-ud-acc-count {
    background: #c4b5fd;
    color: #3730a3;
}
/* All verified state */
.tmpmp-ud-accordion-toggle.all-verified {
    background: #f0fdf4;
    border-color: #bbf7d0;
    color: #15803d;
}
.tmpmp-ud-accordion-toggle.all-verified::before {
    background: linear-gradient(180deg, #4ade80, #16a34a);
}
.tmpmp-ud-accordion-toggle.all-verified .tmpmp-ud-acc-arrow { color: #4ade80; }
.tmpmp-ud-accordion-toggle.all-verified .tmpmp-ud-acc-count {
    background: #dcfce7; color: #15803d; border-color: #86efac;
}
.tmpmp-ud-accordion-toggle.all-verified:hover {
    background: #dcfce7; border-color: #86efac; color: #14532d;
    box-shadow: 0 3px 14px rgba(34,197,94,0.13);
}
/* Responsive */
@media (max-width: 480px) {
    .tmpmp-ud-accordion-toggle { font-size: 11.5px; padding: 8px 12px 8px 16px; }
    .tmpmp-ud-accordion-toggle .tmpmp-ud-acc-count { display: none; }
}
.tmpmp-ud-accordion-body { display:none; margin-top:12px; }
.tmpmp-ud-accordion-body.open { display:block; }

/* DNS Records Table */
.tmpmp-dns-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 13px;
    margin-top: 8px;
}
.tmpmp-dns-table th {
    text-align: left;
    padding: 8px 10px;
    font-size: 11.5px;
    font-weight: 700;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: .4px;
    border-bottom: 2px solid #e2e8f0;
    white-space: nowrap;
}
.tmpmp-dns-table td {
    padding: 10px 10px;
    border-bottom: 1px solid #f1f5f9;
    vertical-align: top;
}
.tmpmp-dns-table tr:last-child td { border-bottom: none; }
.tmpmp-dns-record-value {
    font-family: monospace;
    font-size: 12px;
    color: #334155;
    word-break: break-all;
    max-width: 280px;
}
.tmpmp-dns-copy {
    background: #f1f5f9;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
    padding: 5px 10px;
    font-size: 11.5px;
    font-weight: 600;
    cursor: pointer;
    color: #475569;
    transition: all .15s;
    white-space: nowrap;
}
.tmpmp-dns-copy:hover { background:#6366f1; color:#fff; border-color:#6366f1; }
.tmpmp-dns-rec-status { font-size: 16px; }
.tmpmp-dns-rec-label { font-weight: 700; color: #0f172a; font-size: 13px; }
.tmpmp-dns-rec-desc { font-size: 11.5px; color: #94a3b8; margin-top:2px; }
.tmpmp-dns-priority { font-size:12px; color:#64748b; }
/* Generate DKIM key button */
.tmpmp-dns-gen-dkim {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    background: #fffbeb;
    border: 1px solid #fcd34d;
    border-radius: 6px;
    padding: 5px 10px;
    font-size: 11.5px;
    font-weight: 600;
    cursor: pointer;
    color: #92400e;
    transition: all .15s;
    white-space: nowrap;
}
.tmpmp-dns-gen-dkim:hover { background:#f59e0b; color:#fff; border-color:#f59e0b; }
.tmpmp-dns-gen-dkim:disabled { opacity:.55; cursor:not-allowed; }
.tmpmp-dns-dkim-missing {
    font-size: 11.5px;
    color: #b45309;
    font-style: italic;
}

/* Add domain form */
.tmpmp-ud-add-form {
    display: flex;
    gap: 10px;
    margin-bottom: 20px;
    flex-wrap: wrap;
}
.tmpmp-ud-add-form input {
    flex: 1;
    min-width: 200px;
    padding: 10px 14px;
    border: 1.5px solid #e2e8f0;
    border-radius: 9px;
    font-size: 14px;
    color: #0f172a;
    background: #f8fafc;
    outline: none;
    font-family: inherit;
    transition: border-color .18s;
}
.tmpmp-ud-add-form input:focus { border-color:#6366f1; background:#fff; }
.tmpmp-ud-add-btn {
    padding: 10px 20px;
    background: linear-gradient(135deg,#6366f1,#4f46e5);
    color: #fff;
    border: none;
    border-radius: 9px;
    font-size: 14px;
    font-weight: 700;
    cursor: pointer;
    transition: all .2s;
    white-space: nowrap;
}
.tmpmp-ud-add-btn:hover { opacity:.88; transform:translateY(-1px); }
.tmpmp-ud-add-btn:disabled { opacity:.5; cursor:not-allowed; transform:none; }
.tmpmp-ud-msg {
    padding: 10px 14px;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 600;
    margin-bottom: 14px;
    display: none;
}
.tmpmp-ud-msg.ok  { background:#f0fdf4; color:#065f46; border:1px solid #bbf7d0; display:block; }
.tmpmp-ud-msg.err { background:#fef2f2; color:#991b1b; border:1px solid #fecaca; display:block; }
.tmpmp-ud-empty {
    text-align: center;
    padding: 40px 20px;
    color: #94a3b8;
    font-size: 14px;
    background: #f8fafc;
    border-radius: 12px;
    border: 1.5px dashed #e2e8f0;
}
.tmpmp-ud-empty .tmpmp-ud-empty-icon { font-size: 40px; margin-bottom: 10px; }
@media(max-width:640px){
    .tmpmp-ud-actions { flex-wrap:wrap; }
    .tmpmp-dns-table thead { display:none; }
    .tmpmp-dns-table, .tmpmp-dns-table tbody,
    .tmpmp-dns-table tr, .tmpmp-dns-table td { display:block; width:100%; }
    .tmpmp-dns-table td { padding:8px 0; border-bottom:1px solid #f1f5f9; }
    .tmpmp-dns-record-value { max-width:100%; }
}

/* ── My Inboxes: Search toolbar ───────────────────────────────────── */
.tmpmp-inbox-toolbar {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 16px;
    flex-wrap: wrap;
}
.tmpmp-inbox-search-wrap {
    position: relative !important;
    display: flex;
    align-items: center;
    flex: 1 1 240px;
    min-width: 0;
    max-width: 440px;
}
.tmpmp-inbox-search-icon {
    position: absolute !important;
    left: 13px !important;
    top: 50% !important;
    transform: translateY(-50%) !important;
    width: 16px !important;
    height: 16px !important;
    color: #94a3b8 !important;
    pointer-events: none !important;
    flex-shrink: 0;
    z-index: 1;
}
.tmpmp-inbox-search-input {
    width: 100% !important;
    padding: 10px 36px 10px 42px !important;
    border: 1.5px solid #e2e8f0 !important;
    border-radius: 10px !important;
    font-family: inherit !important;
    font-size: 13.5px !important;
    color: #0f172a !important;
    background: #fff !important;
    outline: none !important;
    transition: border-color .18s, box-shadow .18s;
    box-sizing: border-box !important;
    height: auto !important;
    margin: 0 !important;
    display: block !important;
}
.tmpmp-inbox-search-input:focus {
    border-color: #6366f1;
    box-shadow: 0 0 0 3px rgba(99,102,241,.1);
}
.tmpmp-inbox-search-input::placeholder { color: #94a3b8; }
.tmpmp-inbox-search-clear {
    position: absolute;
    right: 10px;
    top: 50%;
    transform: translateY(-50%);
    background: none;
    border: none;
    cursor: pointer;
    color: #94a3b8;
    font-size: 15px;
    padding: 0;
    line-height: 1;
    display: none;
    width: 20px;
    height: 20px;
    align-items: center;
    justify-content: center;
    transition: color .15s;
}
.tmpmp-inbox-search-clear:hover { color: #ef4444; }
.tmpmp-inbox-meta {
    font-size: 13px;
    color: #64748b;
    font-weight: 500;
    white-space: nowrap;
    flex-shrink: 0;
}
/* Per-page selector */
.tmpmp-inbox-perpage-wrap {
    display: flex; align-items: center; gap: 6px;
    flex-shrink: 0; white-space: nowrap;
}
.tmpmp-inbox-perpage-label {
    font-size: 12px; font-weight: 600; color: #94a3b8;
}
.tmpmp-inbox-perpage-select {
    padding: 6px 28px 6px 10px;
    border: 1.5px solid #e2e8f0;
    border-radius: 9px;
    background: #fff url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%2394a3b8' stroke-width='2.5'%3E%3Cpolyline points='6 9 12 15 18 9'/%3E%3C/svg%3E") no-repeat right 8px center / 12px;
    font-family: inherit;
    font-size: 13px;
    font-weight: 600;
    color: #374151;
    cursor: pointer;
    appearance: none;
    -webkit-appearance: none;
    outline: none;
    transition: border-color .15s, box-shadow .15s;
    min-width: 72px;
}
.tmpmp-inbox-perpage-select:focus {
    border-color: #6366f1;
    box-shadow: 0 0 0 3px rgba(99,102,241,.1);
}
@media (max-width: 580px) {
    .tmpmp-inbox-perpage-wrap { width: 100%; justify-content: flex-start; }
}
/* Mobile: stack toolbar vertically */
@media (max-width: 580px) {
    .tmpmp-inbox-toolbar { flex-direction: column; align-items: flex-start; gap: 8px; }
    .tmpmp-inbox-search-wrap { max-width: 100%; width: 100%; flex: 1 1 100%; }
    .tmpmp-inbox-search-input { font-size: 13px; }
}

/* No-results state */
.tmpmp-inbox-no-results {
    text-align: center;
    padding: 48px 20px;
    color: #94a3b8;
}
.tmpmp-inbox-no-results svg {
    margin: 0 auto 12px;
    display: block;
    color: #cbd5e1;
}
.tmpmp-inbox-no-results p { font-size: 14px; margin: 0; }

/* ── My Inboxes: delete button ── */
.tmpmp-inbox-del-btn {
    display: inline-flex; align-items: center; gap: 5px;
    padding: 5px 11px; border-radius: 8px;
    border: 1.5px solid #fecaca; background: #fff5f5;
    color: #dc2626; font-size: 12px; font-weight: 600;
    font-family: inherit; cursor: pointer;
    transition: background .15s, border-color .15s, transform .1s;
    white-space: nowrap;
}
.tmpmp-inbox-del-btn:hover  { background: #fee2e2; border-color: #f87171; }
.tmpmp-inbox-del-btn:active { transform: scale(.96); }
@keyframes tmpmp-row-fade-out { to { opacity:0; transform:translateX(18px); } }
#tmpmp-inbox-tbody tr.tmpmp-row-deleting { animation: tmpmp-row-fade-out .25s ease forwards; pointer-events:none; }

/* ── My Inboxes: checkbox column ── */
.tmpmp-inbox-cb-th { width:36px; text-align:center; padding:0 6px; }
.tmpmp-inbox-cb-td { text-align:center; padding:0 6px; vertical-align:middle; }
.tmpmp-inbox-cb    { width:16px; height:16px; cursor:pointer; accent-color:#6366f1; vertical-align:middle; }
#tmpmp-inbox-tbody tr.is-selected { background:#f0f4ff !important; }
#tmpmp-inbox-tbody tr.is-selected:hover { background:#e8edff !important; }

/* ── My Inboxes: filter chips ── */
.tmpmp-inbox-filters {
    display:flex; align-items:center; gap:7px; flex-wrap:wrap; margin-bottom:12px;
}
.tmpmp-inbox-filter-label { font-size:12px; font-weight:600; color:#94a3b8; margin-right:2px; }
.tmpmp-inbox-filter-chip {
    display:inline-flex; align-items:center; gap:4px;
    padding:5px 13px; border-radius:20px;
    border:1.5px solid #e2e8f0; background:#f8fafc;
    font-size:12px; font-weight:600; color:#475569;
    font-family:inherit; cursor:pointer;
    transition:background .14s, border-color .14s, color .14s;
    white-space:nowrap; line-height:1.3;
}
.tmpmp-inbox-filter-chip:hover  { border-color:#a5b4fc; background:#eef2ff; color:#4f46e5; }
.tmpmp-inbox-filter-chip.is-active { background:#6366f1; border-color:#6366f1; color:#fff; }
.tmpmp-inbox-filter-chip .chip-count {
    display:inline-flex; align-items:center; justify-content:center;
    background:rgba(255,255,255,.28); border-radius:10px;
    padding:0 5px; font-size:11px; min-width:18px; height:16px;
}
.tmpmp-inbox-filter-chip.is-active .chip-count { background:rgba(255,255,255,.3); }

/* ── My Inboxes: bulk action bar ── */
.tmpmp-inbox-bulk-bar {
    display:none; align-items:center; gap:10px; flex-wrap:wrap;
    padding:10px 14px; border-radius:10px;
    background:#f0f4ff; border:1.5px solid #c7d2fe;
    margin-bottom:12px; font-size:13px; color:#4338ca;
    animation:tmpmp-bar-in .18s ease;
}
@keyframes tmpmp-bar-in { from{opacity:0;transform:translateY(-6px)} to{opacity:1;transform:none} }
.tmpmp-inbox-bulk-bar.is-visible { display:flex; }
.tmpmp-inbox-bulk-count { font-weight:700; margin-right:auto; }
.tmpmp-inbox-bulk-del {
    display:inline-flex; align-items:center; gap:5px;
    padding:6px 14px; border-radius:8px;
    border:1.5px solid #fca5a5; background:#fee2e2;
    color:#dc2626; font-size:12px; font-weight:700;
    font-family:inherit; cursor:pointer;
    transition:background .15s, border-color .15s;
}
.tmpmp-inbox-bulk-del:hover  { background:#fecaca; border-color:#f87171; }
.tmpmp-inbox-bulk-cancel {
    display:inline-flex; align-items:center; gap:5px;
    padding:6px 12px; border-radius:8px;
    border:1.5px solid #e2e8f0; background:#fff;
    color:#64748b; font-size:12px; font-weight:600;
    font-family:inherit; cursor:pointer; transition:background .15s;
}
.tmpmp-inbox-bulk-cancel:hover { background:#f1f5f9; }

/* Pagination row */
.tmpmp-inbox-pagination {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    margin-top: 18px;
    flex-wrap: wrap;
}
.tmpmp-inbox-page-btn {
    padding: 7px 15px;
    border: 1.5px solid #e2e8f0;
    border-radius: 9px;
    background: #fff;
    font-family: inherit;
    font-size: 13px;
    font-weight: 600;
    color: #475569;
    cursor: pointer;
    transition: all .15s;
    white-space: nowrap;
}
.tmpmp-inbox-page-btn:hover:not(:disabled) { border-color:#6366f1; color:#6366f1; background:#f5f3ff; }
.tmpmp-inbox-page-btn:disabled { opacity:.4; cursor:not-allowed; }
.tmpmp-inbox-page-numbers { display:flex; align-items:center; gap:4px; flex-wrap:wrap; justify-content:center; }
.tmpmp-inbox-page-num {
    min-width: 34px;
    height: 34px;
    border: 1.5px solid #e2e8f0;
    border-radius: 8px;
    background: #fff;
    font-family: inherit;
    font-size: 13px;
    font-weight: 600;
    color: #475569;
    cursor: pointer;
    transition: all .15s;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 0 8px;
}
.tmpmp-inbox-page-num:hover:not(.is-active) { border-color:#6366f1; color:#6366f1; background:#f5f3ff; }
.tmpmp-inbox-page-num.is-active {
    background: #6366f1;
    border-color: #6366f1;
    color: #fff;
    box-shadow: 0 2px 8px rgba(99,102,241,.28);
}
.tmpmp-inbox-page-ellipsis { font-size:13px; color:#94a3b8; padding:0 2px; line-height:34px; }
@media (max-width: 580px) {
    .tmpmp-inbox-page-btn { padding:6px 10px; font-size:12px; }
    .tmpmp-inbox-page-num { min-width:30px; height:30px; font-size:12px; }
}
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
                <?php echo esc_html( ucfirst( $plan->plan_slug ?? $plan->slug ?? 'free' ) ); ?> Plan
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
        <?php
        $has_custom_domain_feat = TempMail_Subscription::user_has_feature( $user->ID, 'has_custom_domain' );
        ?>
        <button class="dash-tab-btn<?php echo $has_custom_domain_feat ? '' : ' tmpmp-tab-locked'; ?>" data-tab="domains" title="<?php echo $has_custom_domain_feat ? '' : esc_attr__('Requires a plan with Custom Domains enabled','tempmail-pro'); ?>">
            &#127758; <?php esc_html_e('My Domains','tempmail-pro'); ?><?php if (!$has_custom_domain_feat): ?> &#128274;<?php endif; ?>
        </button>
        <?php if ( $is_premium ) : ?>
        <button class="dash-tab-btn" data-tab="inbox-app">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-1px"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="M2 9h20"/><path d="M8 4v5M16 4v5"/></svg>
            <?php esc_html_e('Inbox App','tempmail-pro'); ?>
        </button>
        <?php else : ?>
        <button class="dash-tab-btn tmpmp-tab-locked" data-tab="inbox-app" title="<?php esc_attr_e('Requires an active paid subscription','tempmail-pro'); ?>">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-1px"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="M2 9h20"/></svg>
            <?php esc_html_e('Inbox App','tempmail-pro'); ?> &#128274;
        </button>
        <?php endif; ?>
        <?php if ( $is_premium && ! empty( $plan->has_permanent_inbox ) ) : ?>
        <button class="dash-tab-btn" data-tab="permanent">
            &#9854; <?php esc_html_e('Permanent Inboxes','tempmail-pro'); ?>
        </button>
        <?php else : ?>
        <button class="dash-tab-btn tmpmp-tab-locked" data-tab="permanent" title="<?php esc_attr_e('Requires a paid plan with Permanent Inbox feature','tempmail-pro'); ?>">
            &#9854; <?php esc_html_e('Permanent Inboxes','tempmail-pro'); ?> &#128274;
        </button>
        <?php endif; ?>

    </div>

    <!-- ── Inboxes Tab ─────────────────────────────────────────────────── -->
    <div class="dash-tab-panel" id="dash-tab-inboxes">
        <?php if ( empty($my_addresses) ) : ?>
        <div class="tmpmp-empty-state">
            <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
            <p><?php esc_html_e('No inboxes yet. Use the TempMail app to generate your first inbox.','tempmail-pro'); ?></p>
        </div>
        <?php else : ?>

        <!-- Search toolbar -->
        <div class="tmpmp-inbox-toolbar">
            <div class="tmpmp-inbox-search-wrap">
                <svg class="tmpmp-inbox-search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
                <input type="text" id="tmpmp-inbox-search" class="tmpmp-inbox-search-input"
                       placeholder="<?php esc_attr_e('Search by address, domain…','tempmail-pro'); ?>"
                       autocomplete="off" spellcheck="false">
                <button type="button" id="tmpmp-inbox-search-clear" class="tmpmp-inbox-search-clear" aria-label="<?php esc_attr_e('Clear search','tempmail-pro'); ?>">&#10005;</button>
            </div>
            <div class="tmpmp-inbox-meta" id="tmpmp-inbox-meta">
                <?php printf( esc_html( _n('%d address','%d addresses', count($my_addresses),'tempmail-pro') ), count($my_addresses) ); ?>
            </div>
            <div class="tmpmp-inbox-perpage-wrap">
                <label class="tmpmp-inbox-perpage-label" for="tmpmp-inbox-perpage"><?php esc_html_e('Show:','tempmail-pro'); ?></label>
                <select id="tmpmp-inbox-perpage" class="tmpmp-inbox-perpage-select" aria-label="<?php esc_attr_e('Rows per page','tempmail-pro'); ?>">
                    <option value="10" selected>10</option>
                    <option value="25">25</option>
                    <option value="50">50</option>
                    <option value="100">100</option>
                    <option value="all"><?php esc_html_e('All','tempmail-pro'); ?></option>
                </select>
            </div>
        </div>

        <!-- Filter chips -->
        <?php
        $cnt_all     = count($my_addresses);
        $cnt_active  = count(array_filter($my_addresses, function($a){ return strtotime($a->expires_at.' UTC') >= time(); }));
        $cnt_expired = $cnt_all - $cnt_active;
        $cnt_has_mail= count(array_filter($my_addresses, function($a){ return intval($a->email_count) > 0; }));
        ?>
        <div class="tmpmp-inbox-filters" id="tmpmp-inbox-filters">
            <span class="tmpmp-inbox-filter-label"><?php esc_html_e('Filter:','tempmail-pro'); ?></span>
            <button type="button" class="tmpmp-inbox-filter-chip is-active" data-filter="all">
                <?php esc_html_e('All','tempmail-pro'); ?> <span class="chip-count"><?php echo $cnt_all; ?></span>
            </button>
            <button type="button" class="tmpmp-inbox-filter-chip" data-filter="active">
                &#9989; <?php esc_html_e('Active','tempmail-pro'); ?> <span class="chip-count"><?php echo $cnt_active; ?></span>
            </button>
            <button type="button" class="tmpmp-inbox-filter-chip" data-filter="expired">
                &#128683; <?php esc_html_e('Expired','tempmail-pro'); ?> <span class="chip-count"><?php echo $cnt_expired; ?></span>
            </button>
            <button type="button" class="tmpmp-inbox-filter-chip" data-filter="has_mail">
                &#128231; <?php esc_html_e('Has Emails','tempmail-pro'); ?> <span class="chip-count"><?php echo $cnt_has_mail; ?></span>
            </button>
        </div>

        <!-- Bulk action bar (shown when rows are selected) -->
        <div class="tmpmp-inbox-bulk-bar" id="tmpmp-inbox-bulk-bar">
            <span class="tmpmp-inbox-bulk-count" id="tmpmp-inbox-bulk-count">0 <?php esc_html_e('selected','tempmail-pro'); ?></span>
            <button type="button" class="tmpmp-inbox-bulk-del" id="tmpmp-inbox-bulk-del">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6M14 11v6"/><path d="M9 6V4h6v2"/></svg>
                <?php esc_html_e('Delete Selected','tempmail-pro'); ?>
            </button>
            <button type="button" class="tmpmp-inbox-bulk-cancel" id="tmpmp-inbox-bulk-cancel">
                <?php esc_html_e('Cancel','tempmail-pro'); ?>
            </button>
        </div>

        <!-- Table -->
        <div class="tmpmp-pub-table-wrap" id="tmpmp-inbox-table-wrap">
            <table class="tmpmp-pub-table">
                <thead>
                    <tr>
                        <th class="tmpmp-inbox-cb-th">
                            <input type="checkbox" class="tmpmp-inbox-cb" id="tmpmp-inbox-cb-all" title="<?php esc_attr_e('Select all on this page','tempmail-pro'); ?>">
                        </th>
                        <th><?php esc_html_e('Address','tempmail-pro'); ?></th>
                        <th><?php esc_html_e('Emails','tempmail-pro'); ?></th>
                        <th><?php esc_html_e('Plan','tempmail-pro'); ?></th>
                        <th><?php esc_html_e('Created','tempmail-pro'); ?></th>
                        <th><?php esc_html_e('Expires','tempmail-pro'); ?></th>
                        <th style="text-align:center;"><?php esc_html_e('Actions','tempmail-pro'); ?></th>
                    </tr>
                </thead>
                <tbody id="tmpmp-inbox-tbody">
                <?php foreach ( $my_addresses as $addr ) :
                    $expired = strtotime( $addr->expires_at . ' UTC' ) < time();
                ?>
                <tr data-address="<?php echo esc_attr( strtolower( $addr->address ) ); ?>" data-id="<?php echo intval($addr->id); ?>" data-status="<?php echo $expired ? 'expired' : 'active'; ?>" data-email-count="<?php echo intval($addr->email_count); ?>" data-has-mail="<?php echo intval($addr->email_count) > 0 ? '1' : '0'; ?>">
                    <td class="tmpmp-inbox-cb-td">
                        <input type="checkbox" class="tmpmp-inbox-cb tmpmp-inbox-row-cb"
                            data-id="<?php echo intval($addr->id); ?>"
                            data-addr="<?php echo esc_attr($addr->address); ?>"
                            aria-label="<?php esc_attr_e('Select inbox','tempmail-pro'); ?>">
                    </td>
                    <td data-label="<?php esc_attr_e('Address','tempmail-pro'); ?>" style="font-family:monospace;font-weight:600;color:#6366f1;word-break:break-all;"><?php echo esc_html($addr->address); ?></td>
                    <td data-label="<?php esc_attr_e('Emails','tempmail-pro'); ?>"><?php echo intval($addr->email_count); ?></td>
                    <td data-label="<?php esc_attr_e('Plan','tempmail-pro'); ?>"><span class="tmpmp-pub-badge <?php echo $sub ? 'tmpmp-pub-badge--green' : 'tmpmp-pub-badge--indigo'; ?>"><?php echo esc_html(ucfirst($current_plan_slug)); ?></span></td>
                    <td data-label="<?php esc_attr_e('Created','tempmail-pro'); ?>" style="color:#64748b;"><?php echo esc_html(date_i18n(get_option('date_format'), strtotime($addr->created_at))); ?></td>
                    <td data-label="<?php esc_attr_e('Expires','tempmail-pro'); ?>">
                        <span class="tmpmp-pub-badge <?php echo $expired ? 'tmpmp-pub-badge--red' : 'tmpmp-pub-badge--green'; ?>">
                            <?php echo $expired ? esc_html__('Expired','tempmail-pro') : esc_html(date_i18n('d M H:i', strtotime($addr->expires_at.' UTC'))); ?>
                        </span>
                    </td>
                    <td data-label="<?php esc_attr_e('Actions','tempmail-pro'); ?>" style="text-align:center;">
                        <button type="button"
                            class="tmpmp-inbox-del-btn"
                            data-id="<?php echo intval($addr->id); ?>"
                            data-addr="<?php echo esc_attr($addr->address); ?>"
                            title="<?php esc_attr_e('Delete this inbox and all its emails','tempmail-pro'); ?>"
                            aria-label="<?php esc_attr_e('Delete','tempmail-pro'); ?>">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6M14 11v6"/><path d="M9 6V4h6v2"/></svg>
                            <?php esc_html_e('Delete','tempmail-pro'); ?>
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- No-search-results state -->
        <div id="tmpmp-inbox-no-results" class="tmpmp-inbox-no-results" style="display:none">
            <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
            <p><?php esc_html_e('No addresses match your search.','tempmail-pro'); ?></p>
        </div>

        <!-- Pagination -->
        <div class="tmpmp-inbox-pagination" id="tmpmp-inbox-pagination" style="display:none">
            <button type="button" id="tmpmp-inbox-prev" class="tmpmp-inbox-page-btn">
                &#8592; <?php esc_html_e('Prev','tempmail-pro'); ?>
            </button>
            <div class="tmpmp-inbox-page-numbers" id="tmpmp-inbox-page-numbers"></div>
            <button type="button" id="tmpmp-inbox-next" class="tmpmp-inbox-page-btn">
                <?php esc_html_e('Next','tempmail-pro'); ?> &#8594;
            </button>
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

                <!-- ☠ Danger Zone: Delete Account -->
                <?php
                $settings = get_option('tmpmp_settings', []);
                if ( ! empty( $settings['allow_account_deletion'] ?? 1 ) ) :
                ?>
                <div class="tmpmp-account-card tmpmp-danger-card" id="tmpmp-delete-account-card">
                    <h3>🗑️ <?php esc_html_e('Delete My Account','tempmail-pro'); ?></h3>
                    <p class="tmpmp-reset-info">
                        <?php esc_html_e('This will permanently delete your account and all associated data — temp email addresses, inbox history, subscriptions, payments and API keys.','tempmail-pro'); ?>
                        <strong><?php esc_html_e('This action cannot be undone.','tempmail-pro'); ?></strong>
                    </p>
                    <button id="tmpmp-open-delete-modal" class="tmpmp-pub-btn--danger">
                        🗑️ <?php esc_html_e('Delete My Account','tempmail-pro'); ?>
                    </button>
                </div>
                <?php endif; ?>

            </div><!-- /security column -->
        </div><!-- /account-grid -->
    </div><!-- /#dash-tab-account -->

    <!-- ── Delete Account Confirmation Modal ────────────────────────────────── -->
    <?php if ( ! empty( $settings['allow_account_deletion'] ?? 1 ) ) : ?>
    <div id="tmpmp-delete-modal" role="dialog" aria-modal="true" aria-labelledby="tmpmp-delete-modal-title">
        <div id="tmpmp-delete-backdrop"></div>
        <div id="tmpmp-delete-box">
            <div class="del-modal-icon">⚠️</div>
            <h4 id="tmpmp-delete-modal-title"><?php esc_html_e('Delete Your Account?','tempmail-pro'); ?></h4>
            <div class="del-modal-warn">
                <?php printf(
                    esc_html__('You are about to permanently delete the account for %s. All your data will be erased and you will be logged out immediately.','tempmail-pro'),
                    '<strong>' . esc_html($user->user_email) . '</strong>'
                ); ?>
            </div>
            <label for="tmpmp-delete-confirm-email">
                <?php esc_html_e('Type your email address to confirm:','tempmail-pro'); ?>
            </label>
            <input type="email" id="tmpmp-delete-confirm-email"
                placeholder="<?php echo esc_attr($user->user_email); ?>"
                autocomplete="off">
            <div id="tmpmp-delete-modal-msg"></div>
            <div class="del-modal-footer">
                <button id="tmpmp-delete-cancel"><?php esc_html_e('Cancel','tempmail-pro'); ?></button>
                <button id="tmpmp-delete-confirm" disabled>
                    🗑️ <?php esc_html_e('Delete My Account','tempmail-pro'); ?>
                </button>
            </div>
        </div>
    </div>
    <?php endif; ?>


    <!-- ── My Domains Tab ───────────────────────────────────────────────── -->
    <div class="dash-tab-panel" id="dash-tab-domains">
        <?php
        $settings_cd       = get_option('tmpmp_settings', []);
        // After a successful form POST add, use the already-fetched fresh list
        $my_custom_domains = $my_custom_domains_fresh ?? TempMail_UserDomains::get_for_user( $user->ID );
        ?>

        <!-- Add domain form (server-side POST + AJAX enhancement) -->
        <?php if ( $_tmpmp_domain_msg ) : ?>
        <div class="tmpmp-ud-msg <?php echo esc_attr( $_tmpmp_domain_type ); ?>" id="tmpmp-ud-server-msg">
            <?php echo esc_html( $_tmpmp_domain_msg ); ?>
        </div>
        <?php endif; ?>
        <?php if ( ! empty( $_tmpmp_domain_debug ) ) : ?>
        <div style="background:#1e1b4b;color:#a5b4fc;font-family:monospace;font-size:12px;padding:12px 16px;border-radius:8px;margin-bottom:12px;border:1px solid #4f46e5;">
            <strong style="color:#818cf8;">🔍 Debug Info:</strong><br>
            <?php foreach ( $_tmpmp_domain_debug as $d ) : ?>
            &nbsp;→ <?php echo esc_html( $d ); ?><br>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <div id="tmpmp-ud-add-msg" class="tmpmp-ud-msg"></div>
        <form id="tmpmp-ud-add-form" class="tmpmp-ud-add-form" method="POST"
              action="<?php echo esc_url( get_permalink() ?: home_url( '/' ) ); ?>">
            <?php wp_nonce_field( 'tmpmp_add_domain_' . $user->ID, 'tmpmp_add_domain_nonce' ); ?>
            <input type="hidden" name="tmpmp_add_domain_submit" value="1">
            <input type="text" id="tmpmp-ud-domain-input" name="tmpmp_domain"
                placeholder="<?php esc_attr_e('e.g. mail.mycompany.com','tempmail-pro'); ?>"
                autocomplete="off" spellcheck="false"
                value="<?php echo esc_attr( $_POST['tmpmp_domain'] ?? '' ); ?>">
            <button id="tmpmp-ud-add-btn" type="submit" class="tmpmp-ud-add-btn">
                &#127760; <?php esc_html_e('Add Domain','tempmail-pro'); ?>
            </button>
        </form>

        <!-- Domain list -->
        <div id="tmpmp-ud-list">
        <?php if ( empty($my_custom_domains) ) : ?>
            <div class="tmpmp-ud-empty" id="tmpmp-ud-empty-state">
                <div class="tmpmp-ud-empty-icon">&#127760;</div>
                <p><strong><?php esc_html_e('No custom domains yet','tempmail-pro'); ?></strong></p>
                <p><?php esc_html_e('Add your own domain above to receive emails on your personal or business address.','tempmail-pro'); ?></p>
            </div>
        <?php else : ?>
            <div id="tmpmp-ud-empty-state" style="display:none;"></div>
        <?php endif; ?>

        <?php foreach ( $my_custom_domains as $ud_row ) :
            $ud_records = TempMail_UserDomains::get_required_records( $ud_row );
            $ud_status  = $ud_row->status;
            $ud_icons   = [ 'pending'=>'🟡', 'verified'=>'🟢', 'failed'=>'🔴' ];
            $ud_labels  = [
                'pending'  => __('Pending','tempmail-pro'),
                'verified' => __('Verified','tempmail-pro'),
                'failed'   => __('Failed','tempmail-pro'),
            ];
        ?>
        <div class="tmpmp-ud-card" id="tmpmp-ud-card-<?php echo (int)$ud_row->id; ?>" data-domain-id="<?php echo (int)$ud_row->id; ?>">
            <div class="tmpmp-ud-card-header">
                <span class="tmpmp-ud-domain-name">&#127760; <?php echo esc_html($ud_row->domain); ?></span>
                <span class="tmpmp-ud-status-badge <?php echo esc_attr($ud_status); ?>" id="tmpmp-ud-badge-<?php echo (int)$ud_row->id; ?>">
                    <?php echo esc_html( ($ud_icons[$ud_status]??'🟡') . ' ' . ($ud_labels[$ud_status]??ucfirst($ud_status)) ); ?>
                </span>
                <div class="tmpmp-ud-actions">
                    <button class="tmpmp-ud-btn tmpmp-ud-btn--verify" data-id="<?php echo (int)$ud_row->id; ?>">
                        🔄 <?php esc_html_e('Verify Now','tempmail-pro'); ?>
                    </button>
                    <button class="tmpmp-ud-btn tmpmp-ud-btn--delete" data-id="<?php echo (int)$ud_row->id; ?>">
                        🗑️ <?php esc_html_e('Remove','tempmail-pro'); ?>
                    </button>
                </div>
            </div>

            <!-- DNS Accordion -->
            <?php
            $ud_verified_count = array_sum( array_column( $ud_records, 'verified' ) );
            $ud_total_count    = count( $ud_records );
            $ud_all_verified   = $ud_verified_count === $ud_total_count && $ud_total_count > 0;
            ?>
            <button class="tmpmp-ud-accordion-toggle<?php echo $ud_all_verified ? ' all-verified' : ''; ?>"
                    data-target="tmpmp-ud-acc-<?php echo (int)$ud_row->id; ?>">
                <span class="tmpmp-ud-acc-arrow">►</span>
                <?php esc_html_e('DNS Records Required','tempmail-pro'); ?>
                <span class="tmpmp-ud-acc-count"><?php echo $ud_verified_count; ?>/<?php echo $ud_total_count; ?> <?php esc_html_e('verified','tempmail-pro'); ?></span>
            </button>
            <div class="tmpmp-ud-accordion-body" id="tmpmp-ud-acc-<?php echo (int)$ud_row->id; ?>">
                <table class="tmpmp-dns-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Status','tempmail-pro'); ?></th>
                            <th><?php esc_html_e('Record','tempmail-pro'); ?></th>
                            <th><?php esc_html_e('Type','tempmail-pro'); ?></th>
                            <th><?php esc_html_e('Host','tempmail-pro'); ?></th>
                            <th><?php esc_html_e('Value','tempmail-pro'); ?></th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody id="tmpmp-ud-dns-<?php echo (int)$ud_row->id; ?>">
                    <?php foreach ($ud_records as $rec) : ?>
                        <tr id="tmpmp-ud-rec-<?php echo (int)$ud_row->id; ?>-<?php echo esc_attr($rec['id']); ?>">
                            <td><span class="tmpmp-dns-rec-status"><?php echo $rec['verified'] ? '✅' : '⏳'; ?></span></td>
                            <td>
                                <div class="tmpmp-dns-rec-label"><?php echo esc_html($rec['label']); ?></div>
                                <div class="tmpmp-dns-rec-desc"><?php echo esc_html($rec['description']); ?></div>
                            </td>
                            <td><code><?php echo esc_html($rec['type']); ?></code><?php if($rec['priority']): ?><br><span class="tmpmp-dns-priority"><?php printf(esc_html__('Priority: %s','tempmail-pro'), esc_html($rec['priority'])); ?></span><?php endif; ?></td>
                            <td><span class="tmpmp-dns-record-value"><?php echo esc_html($rec['host']); ?></span></td>
                            <td>
                                <?php if ( ! empty( $rec['dkim_key_missing'] ) ) : ?>
                                <span class="tmpmp-dns-dkim-missing"><?php esc_html_e( '— key not yet generated —', 'tempmail-pro' ); ?></span>
                                <?php else : ?>
                                <span class="tmpmp-dns-record-value"><?php echo esc_html( $rec['value'] ); ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ( ! empty( $rec['dkim_key_missing'] ) ) : ?>
                                <button class="tmpmp-dns-gen-dkim"
                                        data-domain-id="<?php echo (int) $rec['domain_id']; ?>"
                                        title="<?php esc_attr_e( 'Generate your DKIM key so you can add it to your DNS records.', 'tempmail-pro' ); ?>">
                                    🔑 <?php esc_html_e( 'Generate Key', 'tempmail-pro' ); ?>
                                </button>
                                <?php else : ?>
                                <button class="tmpmp-dns-copy" data-copy="<?php echo esc_attr( $rec['value'] ); ?>">📋 <?php esc_html_e( 'Copy', 'tempmail-pro' ); ?></button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php if ($ud_row->last_checked): ?>
                <p style="font-size:11.5px;color:#94a3b8;margin-top:8px;">&#128336; <?php printf(esc_html__('Last checked: %s','tempmail-pro'), esc_html(get_date_from_gmt($ud_row->last_checked, get_option('date_format').' '.get_option('time_format')))); ?></p>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
        </div><!-- /#tmpmp-ud-list -->
    </div><!-- /#dash-tab-domains -->

    <!-- ── Inbox App Tab ───────────────────────────────────────────────── -->
    <div class="dash-tab-panel" id="dash-tab-inbox-app">
        <div id="tmpmp-dash-inbox-wrap" style="min-height:320px;">
            <!-- skeleton shown while AJAX loads -->
            <div id="tmpmp-dash-inbox-skeleton" style="padding:24px 0;">
                <div style="display:flex;gap:10px;margin-bottom:16px;">
                    <div class="tmpmp-skel" style="height:38px;width:60%;border-radius:10px;"></div>
                    <div class="tmpmp-skel" style="height:38px;flex:1;border-radius:10px;"></div>
                </div>
                <div class="tmpmp-skel" style="height:220px;border-radius:14px;"></div>
            </div>
        </div>
    </div>

    <!-- ── Permanent Inboxes Tab ────────────────────────────────────────── -->
    <div class="dash-tab-panel" id="dash-tab-permanent">
        <div class="tmpmp-perm-header">
            <div class="tmpmp-perm-header-left">
                <strong><?php esc_html_e('Permanent Inboxes','tempmail-pro'); ?></strong>
                <span class="tmpmp-perm-count-badge" id="tmpmp-perm-count">0</span>
            </div>
            <button type="button" class="tmpmp-pub-btn tmpmp-pub-btn--primary" id="tmpmp-perm-create-btn" style="font-size:13px;padding:9px 18px;gap:7px;">
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                <?php esc_html_e('New Permanent Inbox','tempmail-pro'); ?>
            </button>

        </div>
        <div id="tmpmp-perm-cards" class="tmpmp-perm-cards"></div>
        <div id="tmpmp-perm-empty" style="display:none;text-align:center;padding:40px 0;color:#94a3b8;">
            <div style="font-size:40px;margin-bottom:10px;">&#9854;</div>
            <p style="font-weight:600;"><?php esc_html_e('No permanent inboxes yet.','tempmail-pro'); ?></p>
            <p style="font-size:13px;"><?php esc_html_e('Create one to get a reusable, never-expiring email address.','tempmail-pro'); ?></p>
        <!-- Inline pre-loaded data: rendered server-side so cards appear instantly -->
        <script>
        window.__tmpmpPermInboxes  = <?php echo wp_json_encode( array_values( $perm_inboxes ) ); ?>;
        window.__tmpmpPermCanCreate = <?php echo $perm_can_create ? 'true' : 'false'; ?>;
        </script>

        </div>
    </div>

</div><!-- .tmpmp-dashboard-wrap -->

<!-- ── Permanent Inbox: Email Viewer Modal ──────────────────────────────── -->
<div class="tmpmp-view-modal-bg" id="tmpmp-view-modal-bg" role="dialog" aria-modal="true" aria-labelledby="tmpmp-view-modal-title">
    <div class="tmpmp-view-modal" id="tmpmp-view-modal">
        <div class="tmpmp-view-modal-hdr">
            <button class="tmpmp-view-modal-back" id="tmpmp-view-back" title="<?php esc_attr_e('Back to inbox list','tempmail-pro'); ?>">&#8592;</button>
            <div class="tmpmp-view-modal-title">
                <h3 id="tmpmp-view-modal-title"><?php esc_html_e('Inbox','tempmail-pro'); ?></h3>
                <p id="tmpmp-view-modal-sub"></p>
            </div>
            <button class="tmpmp-view-modal-close" id="tmpmp-view-close" title="<?php esc_attr_e('Close','tempmail-pro'); ?>">&#10005;</button>
        </div>
        <div class="tmpmp-view-modal-body" id="tmpmp-view-modal-body"></div>
    </div>
</div>

<!-- ── Permanent Inbox: Create Modal ───────────────────────────────────── -->
<div class="tmpmp-perm-modal-bg" id="tmpmp-perm-modal-bg">
    <div class="tmpmp-perm-modal">
        <h3>&#9854; <?php esc_html_e('Create Permanent Inbox','tempmail-pro'); ?></h3>
        <p><?php esc_html_e('This inbox will never expire and stays linked to your account permanently.','tempmail-pro'); ?></p>
        <div id="tmpmp-perm-modal-err" class="tmpmp-perm-error"></div>
        <label for="tmpmp-perm-domain"><?php esc_html_e('Domain','tempmail-pro'); ?></label>
        <select id="tmpmp-perm-domain">
            <?php
            // Global / system domains
            foreach ( TempMail_Database::get_all_domains() as $d ) :
            ?>
            <option value="<?php echo esc_attr($d->domain); ?>"><?php echo esc_html('@'.$d->domain); ?></option>
            <?php endforeach; ?>
            <?php
            // User's verified custom domains
            $user_custom_domains = TempMail_UserDomains::get_for_user( $user->ID );
            $verified_custom     = array_filter( $user_custom_domains, function( $d ) {
                return $d->txt_verified && $d->mx_verified && $d->spf_verified
                    && $d->dkim_verified && $d->dmarc_verified;
            });
            if ( ! empty( $verified_custom ) ) :
            ?>
            <option disabled>── <?php esc_html_e('Your Custom Domains','tempmail-pro'); ?> ──</option>
            <?php foreach ( $verified_custom as $cd ) : ?>
            <option value="<?php echo esc_attr($cd->domain); ?>"><?php echo esc_html('@'.$cd->domain); ?> ✓</option>
            <?php endforeach; ?>
            <?php endif; ?>
        </select>
        <label for="tmpmp-perm-username"><?php esc_html_e('Username (leave blank to auto-generate)','tempmail-pro'); ?></label>
        <input type="text" id="tmpmp-perm-username" placeholder="e.g. myname" autocomplete="off" maxlength="64">
        <div class="tmpmp-perm-modal-footer">
            <button type="button" class="tmpmp-perm-btn--cancel" id="tmpmp-perm-modal-cancel"><?php esc_html_e('Cancel','tempmail-pro'); ?></button>
            <button type="button" class="tmpmp-perm-btn--create" id="tmpmp-perm-modal-submit"><?php esc_html_e('Create','tempmail-pro'); ?></button>
        </div>
    </div>
</div>

<script>
jQuery(function($){
    const nonce = TempMailPro.nonce, url = TempMailPro.ajax_url;

    // ── Tabs ───────────────────────────────────────────────────────────────
    var inboxAppLoaded = false;
    var permInboxLoaded = false;

    function activateTab(tab){
        // Block locked tabs
        if ($('.dash-tab-btn[data-tab="'+tab+'"]').hasClass('tmpmp-tab-locked')) return;
        $('.dash-tab-btn').removeClass('is-active');
        $('.dash-tab-panel').removeClass('is-active');
        $('.dash-tab-btn[data-tab="'+tab+'"]').addClass('is-active');
        $('#dash-tab-'+tab).addClass('is-active');

        // Lazy-load Inbox App via AJAX on first activation
        if (tab === 'inbox-app' && !inboxAppLoaded) {
            loadInboxApp();
        }
        // Permanent Inboxes tab — render instantly from server-side pre-loaded data
        if (tab === 'permanent' && !permInboxLoaded) {
            if (window.__tmpmpPermInboxes && window.__tmpmpPermInboxes.length >= 0) {
                // Data already available — render with zero AJAX delay
                permInboxLoaded = true;
                permInboxData   = window.__tmpmpPermInboxes;
                renderInboxCards(permInboxData, window.__tmpmpPermCanCreate);
                $('#tmpmp-perm-count').text(permInboxData.length);
                if (!window.__tmpmpPermCanCreate) {
                    $('#tmpmp-perm-create-btn').prop('disabled', true);
                }
            } else {
                // Fallback: fetch via AJAX
                loadPermanentInboxes();
            }
        }
    }
    $('.dash-tab-btn').on('click', function(){ activateTab($(this).data('tab')); });
    activateTab('inboxes');

    // ── AJAX: load Inbox App tab content ───────────────────────────────────
    function loadInboxApp(){
        var $wrap  = $('#tmpmp-dash-inbox-wrap');
        var $skel  = $('#tmpmp-dash-inbox-skeleton');

        $skel.show();

        $.post(url, {
            action : 'tmpmp_dash_inbox_app',
            nonce  : nonce
        })
        .done(function(res){
            if (res.success && res.data && res.data.html) {
                inboxAppLoaded = true;
                $wrap.html(res.data.html);

                // Re-execute any inline <script> blocks inside the returned HTML
                $wrap.find('script').each(function(){
                    var s = document.createElement('script');
                    if (this.src) {
                        s.src = this.src;
                    } else {
                        s.textContent = this.textContent;
                    }
                    document.head.appendChild(s);
                    document.head.removeChild(s);
                });

                // Re-initialize the custom domain picker widget on the injected HTML
                // (initDomainPicker ran on page load before this HTML existed)
                if (typeof window.tmpmpInitDomainPicker === 'function') {
                    window.tmpmpInitDomainPicker();
                }
            } else {
                var msg = (res.data && res.data.message)
                    ? res.data.message
                    : '<?php echo esc_js(__('Could not load Inbox App. Please refresh the page.','tempmail-pro')); ?>';
                $wrap.html(
                    '<div style="padding:32px;text-align:center;color:#ef4444;">' +
                    '<svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="margin-bottom:10px;opacity:.6"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>' +
                    '<p style="margin:0;font-size:14px;font-weight:600;">' + $('<div>').text(msg).html() + '</p>' +
                    '</div>'
                );
            }
        })
        .fail(function(){
            $wrap.html(
                '<div style="padding:32px;text-align:center;color:#ef4444;">' +
                '<p style="margin:0;font-size:14px;font-weight:600;"><?php echo esc_js(__('Network error — could not load Inbox App.','tempmail-pro')); ?></p>' +
                '</div>'
            );
        });
    }

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

    // ══════════════════════════════════════════════════════════════
    // PERMANENT INBOXES
    // ══════════════════════════════════════════════════════════════

    var permInboxData = [];

    function loadPermanentInboxes() {
        permInboxLoaded = true;
        $.post(url, { action: 'tmpmp_get_permanent_inboxes', nonce: nonce })
        .done(function(res) {
            if (res.success) {
                permInboxData = res.data.inboxes || [];
                renderInboxCards(permInboxData, res.data.can_create);
                $('#tmpmp-perm-count').text(permInboxData.length);
                // show/hide create button based on plan limit
                if (!res.data.can_create) {
                    $('#tmpmp-perm-create-btn').prop('disabled', true)
                        .attr('title', '<?php esc_js( esc_html_e('Plan limit reached','tempmail-pro') ); ?>');
                }
            }
        });
    }

    function renderInboxCards(inboxes, canCreate) {
        var $cards = $('#tmpmp-perm-cards');
        $cards.empty();
        if (!inboxes.length) { $('#tmpmp-perm-empty').show(); return; }
        $('#tmpmp-perm-empty').hide();
        inboxes.forEach(function(inbox) {
            var date = inbox.created_at ? inbox.created_at.substring(0,10) : '';
            var html = '<div class="tmpmp-perm-card" data-id="'+inbox.id+'">' +
                '<div style="display:flex;align-items:center;justify-content:space-between;gap:8px;">' +
                    '<div class="tmpmp-perm-copy-wrap" title="" data-copy-addr>' +
                        '<div class="tmpmp-perm-card-addr">'+escHtml(inbox.address)+'</div>' +
                        '<svg class="tmpmp-perm-copy-icon" xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>' +
                    '</div>' +

                    '<span class="tmpmp-perm-badge">&#9854; <?php esc_html_e('Permanent','tempmail-pro'); ?></span>' +
                '</div>' +
                '<div class="tmpmp-perm-meta">' +
                    '&#128197; <?php esc_html_e('Created','tempmail-pro'); ?>: ' + escHtml(date) +
                    ' &nbsp;|&nbsp; ' +
                    '&#128231; <span class="tmpmp-perm-meta-count" id="tmpmp-ecount-' + inbox.id + '">' + parseInt(inbox.email_count || 0, 10) + '</span> <?php esc_html_e('emails','tempmail-pro'); ?>' +
                    '<span class="tmpmp-perm-unread-dot" id="tmpmp-unread-' + inbox.id + '"' + (parseInt(inbox.unread_count || 0, 10) > 0 ? '' : ' hidden') + '>' +
                        '&#128276; <span id="tmpmp-unread-num-' + inbox.id + '">' + parseInt(inbox.unread_count || 0, 10) + '</span> <?php esc_html_e('unread','tempmail-pro'); ?>' +
                    '</span>' +
                '</div>' +

                '<div class="tmpmp-perm-actions">' +
                    '<button type="button" class="tmpmp-perm-btn tmpmp-perm-btn--view" data-view="'+inbox.id+'">'+
                        '&#128231; <?php esc_html_e('View Emails','tempmail-pro'); ?></button>' +
                    '<div class="tmpmp-exp-wrap">' +
                        '<button type="button" class="tmpmp-perm-btn tmpmp-perm-btn--exp" data-exp-toggle="'+inbox.id+'">'+
                            '&#128190; <?php esc_html_e('Export','tempmail-pro'); ?> &#9660;</button>' +
                        '<div class="tmpmp-exp-menu" id="tmpmp-exp-menu-'+inbox.id+'">' +
                            '<button type="button" data-export="'+inbox.id+'" data-fmt="json">&#123;&#125; JSON</button>' +
                            '<button type="button" data-export="'+inbox.id+'" data-fmt="csv">&#128196; CSV</button>' +
                        '</div>' +
                    '</div>' +
                    '<button type="button" class="tmpmp-perm-btn tmpmp-perm-btn--del" data-delete="'+inbox.id+'">'+
                        '&#128465; <?php esc_html_e('Delete','tempmail-pro'); ?></button>' +
                '</div>' +
            '</div>';
            $cards.append(html);
        });
    }

    // ── Email Viewer Modal ────────────────────────────────────────────────
    var $viewBg    = $('#tmpmp-view-modal-bg');
    var $viewBody  = $('#tmpmp-view-modal-body');
    var $viewTitle = $('#tmpmp-view-modal-title');
    var $viewSub   = $('#tmpmp-view-modal-sub');
    var $viewBack  = $('#tmpmp-view-back');
    var activeViewId   = null;
    var activeViewAddr = '';

    function skelRows() {
        var s = '';
        for (var i = 0; i < 5; i++) {
            s += '<div class="tmpmp-view-skel"><div class="tmpmp-view-skel-line" style="width:'+(52+i*8)+'%;"></div><div class="tmpmp-view-skel-line" style="width:34%;"></div></div>';
        }
        return s;
    }

    function showEmailList(addrId) {
        $viewBack.removeClass('visible');
        $viewBody.html(skelRows());
        $.post(url, { action: 'tmpmp_get_history_emails', nonce: nonce, address_id: addrId })
        .done(function(r) {
            if (r.success && r.data && r.data.emails && r.data.emails.length) {
                var emails = r.data.emails;
                $viewSub.text(emails.length + ' <?php esc_html_e('emails','tempmail-pro'); ?>');
                var html = '<ul class="tmpmp-email-list">';
                emails.forEach(function(e) {
                    var subj     = escHtml(e.subject || '<?php esc_html_e('(no subject)','tempmail-pro'); ?>');
                    var sender   = escHtml(e.sender || '');
                    var rawDate  = (e.received_at || '').replace(' ','T');
                    var date     = escHtml((e.received_at||'').substring(0,16).replace('T',' '));
                    var isUnread = !parseInt(e.is_read, 10);
                    var badge    = isUnread
                        ? '<span class="tmpmp-email-unread-badge">&#x1F4E7; <?php esc_html_e('NEW','tempmail-pro'); ?></span>'
                        : '';
                    html += '<li class="tmpmp-email-list-item' + (isUnread ? ' tmpmp-unread' : '') + '"'
                        + ' data-email-id="' + escHtml(String(e.id)) + '"'
                        + ' data-addr-id="'  + escHtml(String(addrId)) + '">' +
                        '<div class="tmpmp-email-list-subj"><span class="tmpmp-email-list-subj-txt">' + subj + '</span>' + badge + '</div>' +
                        '<div class="tmpmp-email-list-meta">' +
                            '<span class="tmpmp-email-list-sender">&#9993; ' + sender + '</span>' +
                            '<span class="tmpmp-email-list-date">&#128336; ' + date + '</span>' +
                        '</div></li>';
                });
                html += '</ul>';
                $viewBody.html(html);
            } else {
                $viewSub.text('0 <?php esc_html_e('emails','tempmail-pro'); ?>');
                $viewBody.html('<div class="tmpmp-email-list-none"><span>&#128231;</span><p><?php esc_html_e('No emails yet. Emails sent to this address will appear here.','tempmail-pro'); ?></p></div>');
            }
        })
        .fail(function() {
            $viewBody.html('<div class="tmpmp-email-list-none"><span>&#9888;</span><p style="color:#ef4444;"><?php esc_html_e('Failed to load emails. Please try again.','tempmail-pro'); ?></p></div>');
        });
    }

    function showEmailBody(emailId, addrId) {
        $viewBack.addClass('visible');
        $viewBody.html(skelRows());
        $.post(url, { action: 'tmpmp_get_history_email_body', nonce: nonce, email_id: emailId, address_id: addrId })
        .done(function(r) {
            if (!r.success || !r.data) {
                $viewBody.html('<div class="tmpmp-email-list-none"><span>&#9888;</span><p style="color:#ef4444;"><?php esc_html_e('Could not load email.','tempmail-pro'); ?></p></div>');
                return;
            }
            var e    = r.data;
            var subj = e.subject || '<?php esc_html_e('(no subject)','tempmail-pro'); ?>';
            $viewTitle.text(subj);
            $viewSub.text(escHtml(e.sender || ''));
            var meta =
                '<div class="tmpmp-email-body-meta">' +
                    '<p class="tmpmp-email-body-subj">' + escHtml(subj) + '</p>' +
                    '<div class="tmpmp-email-body-row"><strong><?php esc_html_e('From','tempmail-pro'); ?>:</strong><span>' + escHtml(e.sender||'') + '</span></div>' +
                    '<div class="tmpmp-email-body-row"><strong><?php esc_html_e('To','tempmail-pro'); ?>:</strong><span>' + escHtml(activeViewAddr) + '</span></div>' +
                    '<div class="tmpmp-email-body-row"><strong><?php esc_html_e('Date','tempmail-pro'); ?>:</strong><span>' + escHtml((e.received_at||'').substring(0,16).replace('T',' ')) + '</span></div>' +
                '</div>';
            var bodyHtml = e.body_html || '';
            var bodyText = e.body_text || '';
            var bodyPart = '';
            if (bodyHtml) {
                // srcdoc iframe — sandboxed, no scripts
                var safe = bodyHtml
                    .replace(/&/g,'&amp;').replace(/"/g,'&quot;')
                    .replace(/</g,'&lt;').replace(/>/g,'&gt;');
                var iframeStyles = [
                    // Universal cap — overrides inline style="width:NNNpx" too
                    '*{max-width:100% !important;box-sizing:border-box !important;overflow-x:hidden !important;}',
                    'html,body{margin:0;padding:0;overflow-x:hidden !important;word-break:break-word;}',
                    'body{font-family:sans-serif;font-size:14px;padding:16px;line-height:1.65;color:#1e293b;}',
                    // Re-allow vertical scrolling and normal overflow on specific safe elements
                    'html,body,div,section,article,main,aside,header,footer,li,ul,ol{overflow-x:hidden !important;}',
                    'img,video,audio,embed,object,iframe{max-width:100% !important;height:auto !important;display:block;}',
                    'table{width:100% !important;table-layout:fixed !important;border-collapse:collapse;}',
                    'td,th{word-break:break-word;overflow-wrap:anywhere;padding:4px;}',
                    'pre,code{white-space:pre-wrap;word-break:break-all;}',
                    'a{word-break:break-all;}'
                ].join('');
                // IMPORTANT: use single-quoted HTML attributes in the srcdoc head so
                // they never conflict with the outer srcdoc="..." double-quote delimiter.
                var srcdocHead = "<!DOCTYPE html><html><head>"
                    + "<meta charset='utf-8'>"
                    + "<meta name='viewport' content='width=device-width,initial-scale=1'>"
                    + "<style>" + iframeStyles + "</style>"
                    + "</head><body>";
                bodyPart = '<iframe class="tmpmp-email-body-frame" sandbox="allow-same-origin"'
                    + ' srcdoc="' + srcdocHead + safe + '</body></html>"'
                    + ' onload="var d=this.contentDocument,h=d.documentElement.scrollHeight||d.body.scrollHeight;this.style.height=(h+24)+\'px\'"></iframe>';
            } else {
                bodyPart = '<div class="tmpmp-email-body-plain">' + escHtml(bodyText || '<?php esc_html_e('(empty)','tempmail-pro'); ?>') + '</div>';
            }
            $viewBody.html(bodyPart);

        })
        .fail(function() {
            $viewBody.html('<div class="tmpmp-email-list-none"><span>&#9888;</span><p style="color:#ef4444;"><?php esc_html_e('Failed to load email.','tempmail-pro'); ?></p></div>');
        });
    }

    function closeViewModal() {
        $viewBg.removeClass('open');
        $('body').css('overflow','');

        // Refresh the live email count + unread dot on the card
        if (activeViewId) {
            var refreshId = activeViewId;
            $.post(url, { action: 'tmpmp_get_history_emails', nonce: nonce, address_id: refreshId })
            .done(function(r) {
                if (!r.success || !r.data) return;
                var total   = r.data.emails ? r.data.emails.length : 0;
                var unread  = r.data.emails ? r.data.emails.filter(function(e){ return !parseInt(e.is_read,10); }).length : 0;
                $('#tmpmp-ecount-'     + refreshId).text(total);
                $('#tmpmp-unread-num-' + refreshId).text(unread);
                var $dot = $('#tmpmp-unread-' + refreshId);
                if (unread > 0) { $dot.removeAttr('hidden'); } else { $dot.attr('hidden', ''); }
            });
        }

        activeViewId   = null;
        activeViewAddr = '';
    }

    // Open via View Emails button
    $(document).on('click', '[data-view]', function() {
        var id    = $(this).data('view');
        var $card = $(this).closest('.tmpmp-perm-card');
        var addr  = $card.find('.tmpmp-perm-card-addr').text().trim();
        var cnt   = $card.find('.tmpmp-perm-meta strong').first().text().trim();
        activeViewId   = id;
        activeViewAddr = addr;
        $viewTitle.text(addr);
        $viewSub.text(cnt + ' <?php esc_html_e('emails','tempmail-pro'); ?>');
        $viewBg.addClass('open');
        $('body').css('overflow','hidden');
        showEmailList(id);
    });

    // Click email row → body view + mark as read
    $(document).on('click', '.tmpmp-email-list-item', function() {
        var $row    = $(this);
        var emailId = $row.data('email-id');
        var addrId  = $row.data('addr-id');

        // Optimistic UI: immediately clear unread state
        if ($row.hasClass('tmpmp-unread')) {
            $row.removeClass('tmpmp-unread');
            $row.find('.tmpmp-email-unread-badge').addClass('hidden');
            // Fire mark-as-read in background (no wait)
            $.post(url, {
                action     : 'tmpmp_mark_email_read',
                nonce      : nonce,
                email_id   : emailId,
                address_id : addrId
            });
        }

        showEmailBody(emailId, addrId);
    });


    // Back arrow → email list
    $viewBack.on('click', function() {
        $viewTitle.text(activeViewAddr);
        $viewSub.text('');
        showEmailList(activeViewId);
    });

    // Close
    $('#tmpmp-view-close').on('click', closeViewModal);
    $viewBg.on('click', function(e) { if (e.target === this) closeViewModal(); });
    $(document).on('keydown', function(e) { if (e.key === 'Escape' && $viewBg.hasClass('open')) closeViewModal(); });

    // ── Copy address ──────────────────────────────────────────────────────
    $(document).on('click', '[data-copy-addr]', function() {
        var $wrap = $(this);
        var addr  = $wrap.find('.tmpmp-perm-card-addr').text().trim();
        if (!addr) return;

        // Replace icon with checkmark during feedback
        var $icon = $wrap.find('.tmpmp-perm-copy-icon');
        var origIcon = $icon[0].outerHTML;
        $icon.replaceWith('<svg class="tmpmp-perm-copy-icon" xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.8" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>');

        function showCopied() {
            $wrap.addClass('copied');
            setTimeout(function() {
                $wrap.removeClass('copied');
                $wrap.find('.tmpmp-perm-copy-icon').replaceWith(origIcon);
            }, 1800);
        }

        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(addr).then(showCopied).catch(function() {
                // fallback
                var ta = document.createElement('textarea');
                ta.value = addr; ta.style.position='fixed'; ta.style.opacity='0';
                document.body.appendChild(ta); ta.select();
                try { document.execCommand('copy'); } catch(e){}
                document.body.removeChild(ta);
                showCopied();
            });
        } else {
            var ta = document.createElement('textarea');
            ta.value = addr; ta.style.position='fixed'; ta.style.opacity='0';
            document.body.appendChild(ta); ta.select();
            try { document.execCommand('copy'); } catch(e){}
            document.body.removeChild(ta);
            showCopied();
        }
    });

    // ── View emails drawer ─────────────────────────────────────────────
    $(document).on('click', '[data-view]', function() {
        var id      = $(this).data('view');
        var $drawer = $('#tmpmp-drawer-'+id);
        if ($drawer.hasClass('open')) { $drawer.removeClass('open'); return; }
        $drawer.html('<p style="color:#6366f1;font-size:13px;">&#8987; <?php esc_html_e('Loading…','tempmail-pro'); ?></p>').addClass('open');

        $.post(url, { action: 'tmpmp_get_history_emails', nonce: nonce, address_id: id })
        .done(function(r) {
            if (r.success && r.data && r.data.emails && r.data.emails.length) {
                var html = '';
                r.data.emails.forEach(function(e) {
                    html += '<div class="tmpmp-perm-drawer-email">' +
                        '<div class="tmpmp-perm-drawer-subj">'+escHtml(e.subject || '<?php esc_html_e('(no subject)','tempmail-pro'); ?>')+'</div>' +
                        '<div class="tmpmp-perm-drawer-meta">'+escHtml(e.sender)+' &mdash; '+escHtml((e.received_at||'').substring(0,16))+'</div>' +
                    '</div>';
                });
                $drawer.html(html);
            } else {
                $drawer.html('<div class="tmpmp-perm-drawer-none">&#128231; <?php esc_html_e('No emails yet.','tempmail-pro'); ?></div>');
            }
        })
        .fail(function() {
            $drawer.html('<div class="tmpmp-perm-drawer-none" style="color:#ef4444;"><?php esc_html_e('Failed to load emails.','tempmail-pro'); ?></div>');
        });
    });


    // Export dropdown toggle
    $(document).on('click', '[data-exp-toggle]', function(e) {
        e.stopPropagation();
        var id  = $(this).data('exp-toggle');
        var $m  = $('#tmpmp-exp-menu-'+id);
        var wasOpen = $m.hasClass('open');
        $('.tmpmp-exp-menu').removeClass('open');
        if (!wasOpen) $m.addClass('open');
    });
    $(document).on('click', function() { $('.tmpmp-exp-menu').removeClass('open'); });

    // Export action
    $(document).on('click', '[data-export]', function() {
        var id  = $(this).data('export');
        var fmt = $(this).data('fmt');
        $('.tmpmp-exp-menu').removeClass('open');
        var $btn = $(this);
        $btn.text('⏳ <?php esc_html_e('Exporting…','tempmail-pro'); ?>');

        $.post(url, { action: 'tmpmp_export_inbox', nonce: nonce, address_id: id, format: fmt })
        .done(function(res) {
            if (!res.success) { alert(res.data?.message || '<?php esc_html_e('Export failed.','tempmail-pro'); ?>'); return; }
            var content, mime, filename;
            if (fmt === 'json') {
                content  = JSON.stringify({ address: res.data.address, emails: res.data.emails }, null, 2);
                mime     = 'application/json';
                filename = 'inbox-' + res.data.address.replace('@','_at_') + '-' + today() + '.json';
            } else {
                content  = res.data.content;
                mime     = 'text/csv';
                filename = res.data.filename;
            }
            var blob = new Blob([content], { type: mime });
            var a    = document.createElement('a');
            a.href   = URL.createObjectURL(blob);
            a.download = filename;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(a.href);
        })
        .fail(function() { alert('<?php esc_html_e('Export failed. Please try again.','tempmail-pro'); ?>'); })
        .always(function() { $btn.text(fmt === 'json' ? '{}  JSON' : '📄 CSV'); });
    });

    // Delete permanent inbox — optimistic UI (remove instantly, restore on failure)
    $(document).on('click', '[data-delete]', function() {
        var id    = $(this).data('delete');
        var addr  = $(this).closest('.tmpmp-perm-card').find('.tmpmp-perm-card-addr').text();
        if (!confirm('<?php esc_html_e('Delete permanent inbox','tempmail-pro'); ?> ' + addr + '?\n<?php esc_html_e('All stored emails will be permanently removed.','tempmail-pro'); ?>')) return;

        var $card     = $(this).closest('.tmpmp-perm-card');
        var cardHtml  = $card[0].outerHTML; // snapshot for rollback

        // ── Optimistic: remove immediately ──────────────────────────────────
        $card.slideUp(200, function() { $(this).remove(); });
        permInboxData = permInboxData.filter(function(x) { return x.id != id; });
        $('#tmpmp-perm-count').text(permInboxData.length);
        if (!permInboxData.length) $('#tmpmp-perm-empty').show();
        $('#tmpmp-perm-create-btn').prop('disabled', false).removeAttr('title');

        // ── Background AJAX ─────────────────────────────────────────────────
        $.post(url, { action: 'tmpmp_delete_permanent_inbox', nonce: nonce, address_id: id })
        .done(function(res) {
            if (!res.success) {
                // Rollback: re-insert card and update data
                $('#tmpmp-perm-empty').hide();
                $('#tmpmp-perm-cards').prepend(cardHtml);
                permInboxData = window.__tmpmpPermInboxes || permInboxData;
                $('#tmpmp-perm-count').text($('#tmpmp-perm-cards .tmpmp-perm-card').length);
                alert(res.data?.message || '<?php esc_html_e('Delete failed. Please try again.','tempmail-pro'); ?>');
            }
        })
        .fail(function() {
            // Network error — rollback
            $('#tmpmp-perm-empty').hide();
            $('#tmpmp-perm-cards').prepend(cardHtml);
            $('#tmpmp-perm-count').text($('#tmpmp-perm-cards .tmpmp-perm-card').length);
            alert('<?php esc_html_e('Network error. The inbox may not have been deleted.','tempmail-pro'); ?>');
        });
    });


    // ── Create modal ────────────────────────────────────────────────────────
    $('#tmpmp-perm-create-btn').on('click', function() {
        $('#tmpmp-perm-modal-err').hide().text('');
        $('#tmpmp-perm-username').val('');
        $('#tmpmp-perm-modal-bg').addClass('open');
    });
    $('#tmpmp-perm-modal-cancel, #tmpmp-perm-modal-bg').on('click', function(e) {
        if (e.target === this) $('#tmpmp-perm-modal-bg').removeClass('open');
    });
    $('#tmpmp-perm-modal-bg .tmpmp-perm-modal').on('click', function(e) { e.stopPropagation(); });

    $('#tmpmp-perm-modal-submit').on('click', function() {
        var domain   = $('#tmpmp-perm-domain').val();
        var username = $.trim($('#tmpmp-perm-username').val());
        var $err     = $('#tmpmp-perm-modal-err');
        var $btn     = $(this);
        $err.hide().text('');
        $btn.prop('disabled', true).text('<?php esc_html_e('Creating…','tempmail-pro'); ?>');

        $.post(url, {
            action  : 'tmpmp_create_permanent_inbox',
            nonce   : nonce,
            domain  : domain,
            username: username
        })
        .done(function(res) {
            if (res.success) {
                $('#tmpmp-perm-modal-bg').removeClass('open');
                var newInbox = {
                    id          : res.data.id,
                    address     : res.data.address,
                    created_at  : res.data.created_at,
                    email_count : 0,
                };
                permInboxData.unshift(newInbox);
                $('#tmpmp-perm-count').text(permInboxData.length);
                renderInboxCards(permInboxData, true);
                // Flash the new card
                var $newCard = $('#tmpmp-perm-cards .tmpmp-perm-card:first-child');
                $newCard.css({ background:'#eef2ff', borderColor:'#6366f1' });
                setTimeout(function() { $newCard.css({ background:'', borderColor:'' }); }, 1500);
            } else {
                $err.text(res.data?.message || '<?php esc_html_e('Error creating inbox.','tempmail-pro'); ?>').show();
            }
        })
        .fail(function() { $err.text('<?php esc_html_e('Connection error.','tempmail-pro'); ?>').show(); })
        .always(function() { $btn.prop('disabled', false).text('<?php esc_html_e('Create','tempmail-pro'); ?>'); });
    });

    // Helper: today's date YYYY-MM-DD
    function today() {
        return new Date().toISOString().substring(0,10);
    }
    // Helper: escape HTML for safe insertion
    function escHtml(str) {
        return $('<div>').text(str||'').html();
    }

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

    /* ── Delete Account Modal ────────────────────────────────────── */
    (function() {
        const modal       = document.getElementById('tmpmp-delete-modal');
        if (!modal) return; // feature disabled via settings

        const backdrop    = document.getElementById('tmpmp-delete-backdrop');
        const openBtn     = document.getElementById('tmpmp-open-delete-modal');
        const cancelBtn   = document.getElementById('tmpmp-delete-cancel');
        const confirmBtn  = document.getElementById('tmpmp-delete-confirm');
        const emailInput  = document.getElementById('tmpmp-delete-confirm-email');
        const msgEl       = document.getElementById('tmpmp-delete-modal-msg');
        const userEmail   = <?php echo json_encode( strtolower( $user->user_email ) ); ?>;

        function openModal() {
            modal.classList.add('open');
            emailInput.value = '';
            confirmBtn.disabled = true;
            emailInput.classList.remove('invalid');
            msgEl.className = '';
            msgEl.textContent = '';
            document.body.style.overflow = 'hidden';
            setTimeout(() => emailInput.focus(), 80);
        }

        function closeModal() {
            modal.classList.remove('open');
            document.body.style.overflow = '';
        }

        if (openBtn) openBtn.addEventListener('click', openModal);
        cancelBtn.addEventListener('click', closeModal);
        backdrop.addEventListener('click', closeModal);

        // Escape key closes modal
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && modal.classList.contains('open')) closeModal();
        });

        // Enable confirm button only when typed email matches
        emailInput.addEventListener('input', function() {
            const matches = emailInput.value.trim().toLowerCase() === userEmail;
            confirmBtn.disabled = !matches;
            emailInput.classList.toggle('invalid', emailInput.value.length > 0 && !matches);
        });

        // AJAX delete
        confirmBtn.addEventListener('click', function() {
            confirmBtn.disabled = true;
            const origText = confirmBtn.textContent;
            confirmBtn.textContent = '<?php esc_html_e('Deleting…','tempmail-pro'); ?>';
            msgEl.className = '';
            msgEl.textContent = '';

            const body = new URLSearchParams({
                action:        'tmpmp_delete_account',
                nonce:         nonce,
                confirm_email: emailInput.value.trim(),
            });

            fetch(url, {method:'POST', body, credentials:'same-origin'})
                .then(r => r.json())
                .then(r => {
                    if (r.success) {
                        confirmBtn.textContent = '<?php esc_html_e('Deleted! Redirecting…','tempmail-pro'); ?>';
                        setTimeout(() => {
                            window.location.href = r.data?.redirect_url || '<?php echo esc_js(home_url('/')); ?>';
                        }, 1200);
                    } else {
                        msgEl.textContent = r.data?.message || '<?php esc_html_e('An error occurred. Please try again.','tempmail-pro'); ?>';
                        msgEl.className   = 'err';
                        confirmBtn.disabled = false;
                        confirmBtn.textContent = origText;
                    }
                })
                .catch(() => {
                    msgEl.textContent = '<?php esc_html_e('Connection error. Please try again.','tempmail-pro'); ?>';
                    msgEl.className   = 'err';
                    confirmBtn.disabled = false;
                    confirmBtn.textContent = origText;
                });
        });
    })();

    /* ── My Domains Tab ────────────────────────────────────────────────── */
    (function() {
        const STATUS_ICONS  = { pending:'🟡', verified:'🟢', failed:'🔴', checking:'🔵' };
        const STATUS_LABELS = {
            pending:  '<?php esc_html_e('Pending','tempmail-pro'); ?>',
            verified: '<?php esc_html_e('Verified','tempmail-pro'); ?>',
            failed:   '<?php esc_html_e('Failed','tempmail-pro'); ?>',
            checking: '<?php esc_html_e('Checking…','tempmail-pro'); ?>',
        };

        // ── Accordion toggle ────────────────────────────────────────────
        document.addEventListener('click', function(e) {
            const btn = e.target.closest('.tmpmp-ud-accordion-toggle');
            if (!btn) return;
            const targetId = btn.getAttribute('data-target');
            const body = document.getElementById(targetId);
            if (!body) return;
            const isOpen = body.classList.toggle('open');
            const arrow  = btn.querySelector('.tmpmp-ud-acc-arrow');
            if (arrow) arrow.textContent = isOpen ? '▼' : '►';
        });

        // ── Copy to clipboard ───────────────────────────────────────────
        document.addEventListener('click', function(e) {
            const btn = e.target.closest('.tmpmp-dns-copy');
            if (!btn) return;
            e.preventDefault();

            const text = btn.getAttribute('data-copy') || '';
            if (!text) return;

            const origHTML = btn.innerHTML;
            function flash() {
                btn.textContent = '✅ <?php esc_html_e('Copied!','tempmail-pro'); ?>';
                setTimeout(function() { btn.innerHTML = origHTML; }, 1800);
            }
            function execFallback() {
                try {
                    const ta = document.createElement('textarea');
                    ta.value = text;
                    ta.setAttribute('readonly', '');
                    ta.style.cssText = 'position:fixed;left:-9999px;top:-9999px;opacity:0;';
                    document.body.appendChild(ta);
                    ta.focus(); ta.select();
                    document.execCommand('copy');
                    document.body.removeChild(ta);
                } catch(ex) { /* silent */ }
                flash();
            }

            // navigator.clipboard only available in secure contexts (HTTPS)
            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(text).then(flash).catch(execFallback);
            } else {
                execFallback();
            }
        });

        // ── Generate DKIM Key ────────────────────────────────────────────
        document.addEventListener('click', function(e) {
            const btn = e.target.closest('.tmpmp-dns-gen-dkim');
            if (!btn) return;
            e.preventDefault();

            const domainId = btn.getAttribute('data-domain-id');
            if (!domainId) return;

            // Access TempMailPro lazily — always defined by the time user can click
            var nonce = (typeof TempMailPro !== 'undefined') ? TempMailPro.nonce    : '';
            var url   = (typeof TempMailPro !== 'undefined') ? TempMailPro.ajax_url : '';
            if (!url || !nonce) {
                alert('<?php esc_html_e('Session expired — please reload the page and try again.','tempmail-pro'); ?>');
                return;
            }

            const origHTML = btn.innerHTML;
            btn.disabled = true;
            btn.textContent = '⏳ <?php esc_html_e('Generating…','tempmail-pro'); ?>';

            const controller = new AbortController();
            const abort = setTimeout(function() { controller.abort(); }, 110000);

            fetch(url, {
                method: 'POST',
                credentials: 'same-origin',
                signal: controller.signal,
                body: new URLSearchParams({ action: 'tmpmp_generate_dkim_key', nonce: nonce, domain_id: domainId }),
            })
            .then(function(res) { return res.json(); })
            .then(function(r) {
                clearTimeout(abort);
                if (!r.success) {
                    const msg = (r.data && r.data.message) ? r.data.message
                        : '<?php esc_html_e('Key generation failed.','tempmail-pro'); ?>';
                    const row = btn.closest('tr');
                    if (row) {
                        const valueTd = row.querySelectorAll('td')[4];
                        if (valueTd) valueTd.innerHTML =
                            '<span style="color:#dc2626;font-size:11px;">' + msg + '</span>';
                    }
                    btn.disabled = false;
                    btn.innerHTML = origHTML;
                    return;
                }
                const dkimValue = r.data.dkim_value;
                // Update the Value cell
                const row = btn.closest('tr');
                if (row) {
                    const valueTd = row.querySelectorAll('td')[4];
                    if (valueTd) {
                        valueTd.innerHTML = '<span class="tmpmp-dns-record-value" style="word-break:break-all;">' +
                            dkimValue.replace(/&/g,'&amp;').replace(/</g,'&lt;') + '</span>';
                    }
                }
                // Replace Generate button with Copy button
                btn.outerHTML = '<button class="tmpmp-dns-copy" data-copy="' +
                    dkimValue.replace(/"/g,'&quot;') +
                    '">📋 <?php esc_html_e('Copy','tempmail-pro'); ?></button>';
            })
            .catch(function(err) {
                clearTimeout(abort);
                btn.disabled = false;
                btn.innerHTML = origHTML;
                const isTimeout = err && err.name === 'AbortError';
                const msg = isTimeout
                    ? '<?php esc_html_e('Timed out — please try again.','tempmail-pro'); ?>'
                    : '<?php esc_html_e('Network error — please try again.','tempmail-pro'); ?>';
                const row = btn.closest('tr');
                if (row) {
                    const valueTd = row.querySelectorAll('td')[4];
                    if (valueTd) valueTd.innerHTML =
                        '<span style="color:#dc2626;font-size:11px;">' + msg + '</span>';
                }
            });
        });



        function setMsg(msg, type) {
            const el = document.getElementById('tmpmp-ud-add-msg');
            if (!el) return;
            el.textContent = msg;
            el.className = 'tmpmp-ud-msg ' + (type || '');
        }

        function setBadge(domainId, status) {
            const badge = document.getElementById('tmpmp-ud-badge-' + domainId);
            if (!badge) return;
            badge.className = 'tmpmp-ud-status-badge ' + status;
            badge.textContent = (STATUS_ICONS[status] || '🟡') + ' ' + (STATUS_LABELS[status] || status);
        }

        function updateRecordIcons(domainId, checks) {
            const ids = ['txt','mx','spf','dkim','dmarc'];
            ids.forEach(id => {
                const row = document.getElementById('tmpmp-ud-rec-' + domainId + '-' + id);
                if (!row) return;
                const icon = row.querySelector('.tmpmp-dns-rec-status');
                if (!icon) return;
                icon.textContent = checks[id] ? '✅' : '❌';
            });
        }

        function buildDomainCard(d, records) {
            const statusLabel = (STATUS_ICONS[d.status]||'🟡') + ' ' + (STATUS_LABELS[d.status]||d.status);
            const recRows = records.map(rec => `
                <tr id="tmpmp-ud-rec-${d.id}-${rec.id}">
                    <td><span class="tmpmp-dns-rec-status">${rec.verified ? '✅' : '⏳'}</span></td>
                    <td>
                        <div class="tmpmp-dns-rec-label">${rec.label}</div>
                        <div class="tmpmp-dns-rec-desc">${rec.description}</div>
                    </td>
                    <td><code>${rec.type}</code>${rec.priority ? `<br><span class="tmpmp-dns-priority"><?php esc_html_e('Priority:','tempmail-pro'); ?> ${rec.priority}</span>` : ''}</td>
                    <td><span class="tmpmp-dns-record-value">${rec.host}</span></td>
                    <td><span class="tmpmp-dns-record-value">${rec.value}</span></td>
                    <td><button class="tmpmp-dns-copy" data-copy="${rec.value.replace(/"/g,'&quot;')}">📋 <?php esc_html_e('Copy','tempmail-pro'); ?></button></td>
                </tr>`).join('');
            const card = document.createElement('div');
            card.className = 'tmpmp-ud-card';
            card.id = 'tmpmp-ud-card-' + d.id;
            card.dataset.domainId = d.id;
            card.innerHTML = `
                <div class="tmpmp-ud-card-header">
                    <span class="tmpmp-ud-domain-name">🌐 ${d.domain}</span>
                    <span class="tmpmp-ud-status-badge ${d.status}" id="tmpmp-ud-badge-${d.id}">${statusLabel}</span>
                    <div class="tmpmp-ud-actions">
                        <button class="tmpmp-ud-btn tmpmp-ud-btn--verify" data-id="${d.id}">🔄 <?php esc_html_e('Verify Now','tempmail-pro'); ?></button>
                        <button class="tmpmp-ud-btn tmpmp-ud-btn--delete" data-id="${d.id}">🗑️ <?php esc_html_e('Remove','tempmail-pro'); ?></button>
                    </div>
                </div>
                <button class="tmpmp-ud-accordion-toggle" data-target="tmpmp-ud-acc-${d.id}">
                    <span class="tmpmp-ud-acc-arrow">►</span>
                    <?php esc_html_e('DNS Records Required','tempmail-pro'); ?>
                    <span class="tmpmp-ud-acc-count">0/${records.length} <?php esc_html_e('verified','tempmail-pro'); ?></span>
                </button>
                <div class="tmpmp-ud-accordion-body open" id="tmpmp-ud-acc-${d.id}">
                    <table class="tmpmp-dns-table">
                        <thead><tr>
                            <th><?php esc_html_e('Status','tempmail-pro'); ?></th>
                            <th><?php esc_html_e('Record','tempmail-pro'); ?></th>
                            <th><?php esc_html_e('Type','tempmail-pro'); ?></th>
                            <th><?php esc_html_e('Host','tempmail-pro'); ?></th>
                            <th><?php esc_html_e('Value','tempmail-pro'); ?></th>
                            <th></th>
                        </tr></thead>
                        <tbody id="tmpmp-ud-dns-${d.id}">${recRows}</tbody>
                    </table>
                </div>`;
            return card;
        }

        // ── Add domain — AJAX (no page reload) ──────────────────────────────
        const addForm  = document.getElementById('tmpmp-ud-add-form');
        const addBtn   = document.getElementById('tmpmp-ud-add-btn');
        const addInput = document.getElementById('tmpmp-ud-domain-input');
        if (addForm && addBtn && addInput) {
            addForm.addEventListener('submit', function(e) {
                e.preventDefault(); // Always prevent full-page reload

                const domain = addInput.value.trim();
                if (!domain) {
                    setMsg('<?php esc_html_e('Please enter a domain name.','tempmail-pro'); ?>', 'err');
                    return;
                }

                var nonce = (typeof TempMailPro !== 'undefined') ? TempMailPro.nonce    : '';
                var url   = (typeof TempMailPro !== 'undefined') ? TempMailPro.ajax_url : '';
                if (!url || !nonce) {
                    setMsg('<?php esc_html_e('Configuration error. Please refresh the page.','tempmail-pro'); ?>', 'err');
                    return;
                }

                // Disable button and show loading state
                addBtn.disabled    = true;
                addBtn.textContent = '<?php esc_html_e('Adding…','tempmail-pro'); ?>';
                setMsg('', '');

                var xhr = new XMLHttpRequest();
                xhr.open('POST', url, true);
                xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                xhr.onreadystatechange = function() {
                    if (xhr.readyState !== 4) return;

                    // Restore button
                    addBtn.disabled   = false;
                    addBtn.innerHTML  = '&#127760; <?php esc_html_e('Add Domain','tempmail-pro'); ?>';

                    try {
                        var resp = JSON.parse(xhr.responseText);
                        if (resp.success && resp.data) {
                            setMsg(resp.data.message || '<?php esc_html_e('Domain added! Configure the DNS records below.','tempmail-pro'); ?>', 'ok');
                            addInput.value = '';

                            // Inject the new card into the list without reload
                            var d       = resp.data.domain;
                            var records = resp.data.records || [];
                            if (d && typeof buildDomainCard === 'function') {
                                var card  = buildDomainCard(d, records);
                                var list  = document.getElementById('tmpmp-ud-list');
                                var empty = document.getElementById('tmpmp-ud-empty-state');
                                if (empty) { empty.style.display = 'none'; }
                                if (list)  { list.insertBefore(card, list.firstChild); }
                            }
                        } else {
                            var msg = (resp.data && resp.data.message)
                                ? resp.data.message
                                : '<?php esc_html_e('Could not add domain. Please try again.','tempmail-pro'); ?>';
                            setMsg(msg, 'err');
                        }
                    } catch(ex) {
                        setMsg('<?php esc_html_e('Unexpected error. Please refresh and try again.','tempmail-pro'); ?>', 'err');
                    }
                };
                xhr.send(
                    'action=tmpmp_add_custom_domain'
                    + '&nonce='  + encodeURIComponent(nonce)
                    + '&domain=' + encodeURIComponent(domain)
                );
            });
        }


        // ── Verify domain ───────────────────────────────────────────────
        document.addEventListener('click', function(e) {
            const btn = e.target.closest('.tmpmp-ud-btn--verify');
            if (!btn) return;
            const id = btn.dataset.id;

            var nonce = (typeof TempMailPro !== 'undefined') ? TempMailPro.nonce    : '';
            var url   = (typeof TempMailPro !== 'undefined') ? TempMailPro.ajax_url : '';
            if (!url || !nonce) return;

            btn.disabled = true;
            btn.innerHTML = '🔵 <?php esc_html_e('Checking…','tempmail-pro'); ?>';
            setBadge(id, 'checking');

            function resetBtn() {
                btn.disabled = false;
                btn.innerHTML = '🔄 <?php esc_html_e('Verify Now','tempmail-pro'); ?>';
            }
            function showTimeoutNote() {
                const tog = document.querySelector('[data-target="tmpmp-ud-acc-' + id + '"]');
                if (!tog) return;
                const note = document.createElement('small');
                note.style.cssText = 'color:#b45309;font-size:11px;display:block;margin-top:4px;';
                note.textContent = '<?php esc_html_e('DNS check timed out — please try Verify Now again.','tempmail-pro'); ?>';
                tog.parentNode.insertBefore(note, tog.nextSibling);
                setTimeout(function() { if (note.parentNode) note.remove(); }, 6000);
            }

            var xhr = new XMLHttpRequest();
            xhr.open('POST', url, true);
            xhr.timeout = 20000;
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');

            xhr.onload = function() {
                resetBtn();
                var r;
                try { r = JSON.parse(xhr.responseText); } catch(err) {
                    setBadge(id, 'failed'); return;
                }
                if (r && r.success) {
                    var d = r.data;
                    setBadge(id, d.status);
                    updateRecordIcons(id, d.checks);
                    var passed = Object.values(d.checks).filter(Boolean).length;
                    var total  = Object.values(d.checks).length;
                    var tog = document.querySelector('[data-target="tmpmp-ud-acc-' + id + '"]');
                    if (tog) {
                        var chip = tog.querySelector('.tmpmp-ud-acc-count');
                        if (chip) chip.textContent = passed + '/' + total + ' <?php esc_html_e('verified','tempmail-pro'); ?>';
                        tog.classList.toggle('all-verified', passed === total && total > 0);
                    }
                } else {
                    setBadge(id, 'failed');
                }
            };
            xhr.onerror   = function() { resetBtn(); setBadge(id, 'failed'); };
            xhr.ontimeout = function() { resetBtn(); setBadge(id, 'pending'); showTimeoutNote(); };

            xhr.send('action=tmpmp_verify_custom_domain&nonce=' + encodeURIComponent(nonce) + '&domain_id=' + encodeURIComponent(id));
        });


        // ── Delete domain (optimistic UI) ───────────────────────────────
        // The card is removed immediately when the user confirms.
        // The AJAX runs in the background. If the server rejects it,
        // the card is restored and an error alert is shown.
        document.addEventListener('click', function(e) {
            var btn = e.target.closest('.tmpmp-ud-btn--delete');
            if (!btn) return;
            if (!confirm('<?php esc_html_e('Remove this custom domain? This cannot be undone.','tempmail-pro'); ?>')) return;

            var id    = btn.dataset.id;
            var nonce = (typeof TempMailPro !== 'undefined') ? TempMailPro.nonce    : '';
            var url   = (typeof TempMailPro !== 'undefined') ? TempMailPro.ajax_url : '';

            if (!url || !nonce) {
                alert('<?php esc_html_e('Session expired — please reload the page and try again.','tempmail-pro'); ?>');
                return;
            }

            // Snapshot the card so we can restore it on server error
            var card       = document.getElementById('tmpmp-ud-card-' + id);
            var cardHTML   = card ? card.outerHTML : '';
            var cardParent = card ? card.parentNode : null;
            var cardNext   = card ? card.nextSibling : null;

            // Optimistically fade + remove the card right now
            if (card) {
                card.style.transition = 'opacity .25s';
                card.style.opacity    = '0';
                setTimeout(function() { if (card.parentNode) card.remove(); }, 260);
            }

            // Show empty-state if this was the last card
            var remaining = document.querySelectorAll('#tmpmp-ud-list .tmpmp-ud-card').length - 1;
            if (remaining <= 0) {
                var empty = document.getElementById('tmpmp-ud-empty-state');
                if (empty) {
                    empty.className = 'tmpmp-ud-empty';
                    empty.innerHTML = '<div class="tmpmp-ud-empty-icon">&#127760;</div><p><strong><?php esc_html_e('No custom domains yet','tempmail-pro'); ?></strong></p>';
                    empty.style.display = '';
                }
            }

            // Restore card helper (called only on confirmed server-side failure)
            function restoreCard(errMsg) {
                if (cardParent && cardHTML) {
                    var tmp = document.createElement('div');
                    tmp.innerHTML = cardHTML;
                    var restored = tmp.firstChild;
                    if (restored) {
                        restored.style.opacity = '1';
                        if (cardNext) { cardParent.insertBefore(restored, cardNext); }
                        else          { cardParent.appendChild(restored); }
                    }
                }
                var e2 = document.getElementById('tmpmp-ud-empty-state');
                if (e2) e2.style.display = 'none';
                alert(errMsg || '<?php esc_html_e('Could not remove domain — please try again.','tempmail-pro'); ?>');
            }

            // Fire AJAX in the background
            var xhr = new XMLHttpRequest();
            xhr.open('POST', url, true);
            xhr.timeout = 15000;
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhr.onload = function() {
                var r;
                try { r = JSON.parse(xhr.responseText); } catch(ex) { return; }
                if (r && r.success === false) {
                    restoreCard((r.data && r.data.message) ? r.data.message : '');
                }
                // success === true → card is already gone, nothing to do
            };
            xhr.onerror   = function() { /* network error — leave card removed, it was probably deleted */ };
            xhr.ontimeout = function() { /* timed out — server likely succeeded, leave card removed */ };
            xhr.send('action=tmpmp_delete_custom_domain&nonce=' + encodeURIComponent(nonce) + '&domain_id=' + encodeURIComponent(id));
        });

    })();

})();

// ── My Inboxes: search + filter chips + multi-select + bulk delete ───────────
(function () {
    var tbody      = document.getElementById('tmpmp-inbox-tbody');
    if (!tbody) return;

    var searchInput  = document.getElementById('tmpmp-inbox-search');
    var clearBtn     = document.getElementById('tmpmp-inbox-search-clear');
    var metaEl       = document.getElementById('tmpmp-inbox-meta');
    var prevBtn      = document.getElementById('tmpmp-inbox-prev');
    var nextBtn      = document.getElementById('tmpmp-inbox-next');
    var pageNumsEl   = document.getElementById('tmpmp-inbox-page-numbers');
    var noResultsEl  = document.getElementById('tmpmp-inbox-no-results');
    var tableWrapEl  = document.getElementById('tmpmp-inbox-table-wrap');
    var paginationEl = document.getElementById('tmpmp-inbox-pagination');
    var bulkBar      = document.getElementById('tmpmp-inbox-bulk-bar');
    var bulkCount    = document.getElementById('tmpmp-inbox-bulk-count');
    var bulkDel      = document.getElementById('tmpmp-inbox-bulk-del');
    var bulkCancel   = document.getElementById('tmpmp-inbox-bulk-cancel');
    var cbAll        = document.getElementById('tmpmp-inbox-cb-all');
    var filterChips  = document.querySelectorAll('.tmpmp-inbox-filter-chip');
    var perPageSel   = document.getElementById('tmpmp-inbox-perpage');

    var PER_PAGE    = 10;
    var currentPage = 1;
    var activeFilter = 'all';          // 'all' | 'active' | 'expired' | 'has_mail'
    var searchQuery  = '';
    var allRows      = Array.prototype.slice.call(tbody.querySelectorAll('tr'));
    var filtered     = allRows.slice();

    var AJAX_URL = (typeof TempMailPro !== 'undefined' && TempMailPro.ajax_url) ? TempMailPro.ajax_url : '<?php echo esc_js( admin_url("admin-ajax.php") ); ?>';
    var NONCE    = (typeof TempMailPro !== 'undefined' && TempMailPro.nonce)    ? TempMailPro.nonce    : '<?php echo esc_js( wp_create_nonce("tempmail_pro_nonce") ); ?>';

    /* ── helpers ─────────────────────────────────────────────────────── */
    function getSelected() {
        return Array.prototype.slice.call(
            tbody.querySelectorAll('.tmpmp-inbox-row-cb:checked')
        );
    }

    function updateBulkBar() {
        var sel = getSelected();
        if (sel.length > 0) {
            bulkBar.classList.add('is-visible');
            bulkCount.textContent = sel.length + ' <?php echo esc_js(__('selected','tempmail-pro')); ?>';
        } else {
            bulkBar.classList.remove('is-visible');
        }
        // Sync select-all checkbox state
        var visibleCbs = Array.prototype.slice.call(
            tbody.querySelectorAll('.tmpmp-inbox-row-cb')
        ).filter(function(cb){ return cb.closest('tr').style.display !== 'none'; });
        var checkedVisible = visibleCbs.filter(function(cb){ return cb.checked; });
        cbAll.checked       = visibleCbs.length > 0 && checkedVisible.length === visibleCbs.length;
        cbAll.indeterminate = checkedVisible.length > 0 && checkedVisible.length < visibleCbs.length;
    }

    function clearSelection() {
        tbody.querySelectorAll('.tmpmp-inbox-row-cb').forEach(function(cb){
            cb.checked = false;
            cb.closest('tr').classList.remove('is-selected');
        });
        cbAll.checked = false;
        cbAll.indeterminate = false;
        bulkBar.classList.remove('is-visible');
    }

    /* ── combined filter (search + chip) ─────────────────────────────── */
    function applyFilter() {
        var q = searchQuery.trim().toLowerCase();
        filtered = allRows.filter(function(r) {
            var addr        = (r.getAttribute('data-address') || '').toLowerCase();
            var status      = (r.getAttribute('data-status') || 'active').trim();
            var emailCount  = parseInt(r.getAttribute('data-email-count') || '0', 10);
            var hasMail     = emailCount > 0;

            var matchSearch = !q || addr.indexOf(q) !== -1;
            var matchChip   = activeFilter === 'all'      ? true
                            : activeFilter === 'active'   ? status === 'active'
                            : activeFilter === 'expired'  ? status === 'expired'
                            : activeFilter === 'has_mail' ? hasMail
                            : true;
            return matchSearch && matchChip;
        });
        currentPage = 1;
        clearSelection();
        render();
    }

    /* ── live chip count refresh ──────────────────────────────────────── */
    function updateChipCounts() {
        var cntAll = allRows.length;
        var cntActive = 0, cntExpired = 0, cntHasMail = 0;
        allRows.forEach(function(r) {
            var status     = (r.getAttribute('data-status') || 'active').trim();
            var emailCount = parseInt(r.getAttribute('data-email-count') || '0', 10);
            if (status === 'active')  cntActive++;
            if (status === 'expired') cntExpired++;
            if (emailCount > 0)       cntHasMail++;
        });
        document.querySelectorAll('.tmpmp-inbox-filter-chip').forEach(function(chip) {
            var f     = chip.getAttribute('data-filter');
            var badge = chip.querySelector('.chip-count');
            if (!badge) return;
            if (f === 'all')      badge.textContent = cntAll;
            if (f === 'active')   badge.textContent = cntActive;
            if (f === 'expired')  badge.textContent = cntExpired;
            if (f === 'has_mail') badge.textContent = cntHasMail;
        });
    }

    /* ── main render ─────────────────────────────────────────────────── */
    function render() {
        var total      = filtered.length;
        var perPage    = isFinite(PER_PAGE) ? PER_PAGE : total || 1;
        var totalPages = Math.max(1, Math.ceil(total / perPage));
        if (currentPage > totalPages) currentPage = totalPages;

        var start = (currentPage - 1) * perPage;
        var end   = start + perPage;

        allRows.forEach(function(r){ r.style.display = 'none'; });
        filtered.forEach(function(r, i){
            r.style.display = (i >= start && i < end) ? '' : 'none';
        });

        if (total === 0) {
            tableWrapEl.style.display  = 'none';
            noResultsEl.style.display  = '';
            paginationEl.style.display = 'none';
        } else {
            tableWrapEl.style.display  = '';
            noResultsEl.style.display  = 'none';
            paginationEl.style.display = totalPages > 1 ? 'flex' : 'none';
        }


        var pageInfo = totalPages > 1 ? ' \u00b7 <?php echo esc_js(__('Page','tempmail-pro')); ?> ' + currentPage + ' <?php echo esc_js(__('of','tempmail-pro')); ?> ' + totalPages : '';
        metaEl.textContent = total + (total === 1 ? ' <?php echo esc_js(__('address','tempmail-pro')); ?>' : ' <?php echo esc_js(__('addresses','tempmail-pro')); ?>') + pageInfo;

        prevBtn.disabled = (currentPage <= 1);
        nextBtn.disabled = (currentPage >= totalPages);
        buildPageNumbers(totalPages);
        updateBulkBar();
        updateChipCounts();
    }

    /* ── build numbered page buttons ─────────────────────────────────── */
    function buildPageNumbers(totalPages) {
        pageNumsEl.innerHTML = '';
        if (totalPages <= 1) return;
        var pages = [];
        for (var i = 1; i <= totalPages; i++) {
            if (i === 1 || i === totalPages || (i >= currentPage - 1 && i <= currentPage + 1)) pages.push(i);
        }
        var prev = null;
        pages.forEach(function(p) {
            if (prev !== null && p - prev > 1) {
                var ell = document.createElement('span');
                ell.className = 'tmpmp-inbox-page-ellipsis';
                ell.textContent = '\u2026';
                pageNumsEl.appendChild(ell);
            }
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'tmpmp-inbox-page-num' + (p === currentPage ? ' is-active' : '');
            btn.textContent = p;
            btn.setAttribute('data-p', p);
            btn.addEventListener('click', function(){
                currentPage = parseInt(this.getAttribute('data-p'), 10);
                clearSelection();
                render();
                var panel = document.getElementById('dash-tab-inboxes');
                if (panel) panel.scrollIntoView({ behavior: 'smooth', block: 'start' });
            });
            pageNumsEl.appendChild(btn);
            prev = p;
        });
    }

    /* ── delete a single row (core logic, reused by individual + bulk) ── */
    function deleteRow(row, id, addr, onDone) {
        row.classList.add('tmpmp-row-deleting');
        var riFiltered = filtered.indexOf(row);
        var riAll      = allRows.indexOf(row);

        setTimeout(function() {
            if (row.parentNode) row.parentNode.removeChild(row);
            if (riFiltered !== -1) filtered.splice(riFiltered, 1);
            if (riAll      !== -1) allRows.splice(riAll, 1);

            if (!allRows.length) {
                if (tableWrapEl)  tableWrapEl.style.display  = 'none';
                if (paginationEl) paginationEl.style.display = 'none';
                var emptyEl = document.createElement('div');
                emptyEl.className = 'tmpmp-empty-state';
                emptyEl.innerHTML = '<svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg><p><?php echo esc_js(__('No inboxes yet. Use the TempMail app to generate your first inbox.','tempmail-pro')); ?></p>';
                var panel = document.getElementById('dash-tab-inboxes');
                if (panel) panel.prepend(emptyEl);
            } else {
                var totalPages = Math.ceil(filtered.length / PER_PAGE);
                if (currentPage > totalPages && currentPage > 1) currentPage = totalPages || 1;
                render();
            }
            if (onDone) onDone();
        }, 260);

        var xhr = new XMLHttpRequest();
        xhr.open('POST', AJAX_URL, true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        xhr.timeout = 15000;
        xhr.onload = function() {
            var r; try { r = JSON.parse(xhr.responseText); } catch(ex) { return; }
            if (r && r.success === false) {
                // Rollback
                row.classList.remove('tmpmp-row-deleting');
                row.style.animation = 'none';
                var rows = Array.prototype.slice.call(tbody.querySelectorAll('tr'));
                tbody.insertBefore(row, rows[riAll] || null);
                allRows.splice(riAll !== -1 ? riAll : allRows.length, 0, row);
                filtered.splice(riFiltered !== -1 ? riFiltered : filtered.length, 0, row);
                if (tableWrapEl) tableWrapEl.style.display = '';
                var errSpan = document.createElement('span');
                errSpan.style.cssText = 'color:#dc2626;font-size:11px;margin-left:6px;';
                errSpan.textContent = (r.data && r.data.message) ? r.data.message : '<?php echo esc_js(__('Delete failed.','tempmail-pro')); ?>';
                var delBtn = row.querySelector('.tmpmp-inbox-del-btn');
                if (delBtn) delBtn.insertAdjacentElement('afterend', errSpan);
                setTimeout(function(){ if (errSpan.parentNode) errSpan.parentNode.removeChild(errSpan); }, 4000);
                render();
            }
        };
        xhr.onerror = xhr.ontimeout = function(){
            // Silently swallow — Local/SSL quirks can fire onerror even on successful
            // requests. The server already deleted the row in most cases.
            // The user will see the row gone; a reload will confirm state.
        };
        xhr.send('action=tmpmp_delete_inbox_address&nonce=' + encodeURIComponent(NONCE) + '&address_id=' + encodeURIComponent(id));
    }

    /* ── events: filter chips ─────────────────────────────────────────── */
    filterChips.forEach(function(chip) {
        chip.addEventListener('click', function() {
            filterChips.forEach(function(c){ c.classList.remove('is-active'); });
            this.classList.add('is-active');
            activeFilter = this.getAttribute('data-filter') || 'all';
            applyFilter();
        });
    });

    /* ── events: search ─────────────────────────────────────────────── */
    searchInput.addEventListener('input', function() {
        clearBtn.style.display = this.value ? 'flex' : 'none';
        searchQuery = this.value;
        applyFilter();
    });
    clearBtn.addEventListener('click', function() {
        searchInput.value = '';
        clearBtn.style.display = 'none';
        searchQuery = '';
        searchInput.focus();
        applyFilter();
    });

    /* ── events: pagination ─────────────────────────────────────────── */
    prevBtn.addEventListener('click', function() {
        if (currentPage > 1) { currentPage--; clearSelection(); render(); }
    });
    nextBtn.addEventListener('click', function() {
        var tp = Math.ceil(filtered.length / PER_PAGE);
        if (currentPage < tp) { currentPage++; clearSelection(); render(); }
    });

    /* ── events: per-page selector ──────────────────────────────────── */
    if (perPageSel) {
        perPageSel.addEventListener('change', function() {
            var val = this.value;
            PER_PAGE = (val === 'all') ? Infinity : parseInt(val, 10);
            currentPage = 1;
            clearSelection();
            render();
        });
    }

    /* ── events: select-all checkbox ─────────────────────────────────── */
    cbAll.addEventListener('change', function() {
        var visibleCbs = Array.prototype.slice.call(
            tbody.querySelectorAll('.tmpmp-inbox-row-cb')
        ).filter(function(cb){ return cb.closest('tr').style.display !== 'none'; });
        visibleCbs.forEach(function(cb){
            cb.checked = cbAll.checked;
            cb.closest('tr').classList.toggle('is-selected', cbAll.checked);
        });
        updateBulkBar();
    });

    /* ── events: individual row checkbox ─────────────────────────────── */
    tbody.addEventListener('change', function(e) {
        if (!e.target.classList.contains('tmpmp-inbox-row-cb')) return;
        e.target.closest('tr').classList.toggle('is-selected', e.target.checked);
        updateBulkBar();
    });

    /* ── events: bulk delete ────────────────────────────────────────── */
    bulkDel.addEventListener('click', function() {
        var selectedCbs = getSelected();
        if (!selectedCbs.length) return;
        var count = selectedCbs.length;
        if (!confirm('<?php echo esc_js(__('Delete','tempmail-pro')); ?> ' + count + ' <?php echo esc_js(__('selected inbox(es) and all their emails? This cannot be undone.','tempmail-pro')); ?>')) return;

        bulkBar.classList.remove('is-visible');

        // ── 1. Collect everything BEFORE any mutation ──────────────────
        var toDelete = [];
        selectedCbs.forEach(function(cb) {
            var row = cb.closest('tr');
            var id  = cb.getAttribute('data-id');
            if (row && id) toDelete.push({ row: row, id: id });
        });
        if (!toDelete.length) return;

        // ── 2. Animate all rows out simultaneously ─────────────────────
        toDelete.forEach(function(item) {
            item.row.classList.add('tmpmp-row-deleting');
        });

        setTimeout(function() {
            // ── 3. Remove from DOM ─────────────────────────────────────
            toDelete.forEach(function(item) {
                if (item.row.parentNode) item.row.parentNode.removeChild(item.row);
            });

            // ── 4. Filter arrays by reference — no index arithmetic ────
            var deleteSet = toDelete.map(function(item) { return item.row; });
            allRows  = allRows.filter(function(r)  { return deleteSet.indexOf(r) === -1; });
            filtered = filtered.filter(function(r) { return deleteSet.indexOf(r) === -1; });

            cbAll.checked       = false;
            cbAll.indeterminate = false;

            if (!allRows.length) {
                if (tableWrapEl)  tableWrapEl.style.display  = 'none';
                if (paginationEl) paginationEl.style.display = 'none';
                var emptyEl = document.createElement('div');
                emptyEl.className = 'tmpmp-empty-state';
                emptyEl.innerHTML = '<svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg><p><?php echo esc_js(__('No inboxes yet. Use the TempMail app to generate your first inbox.','tempmail-pro')); ?></p>';
                var panel = document.getElementById('dash-tab-inboxes');
                if (panel) panel.prepend(emptyEl);
            } else {
                var perPage    = isFinite(PER_PAGE) ? PER_PAGE : filtered.length || 1;
                var totalPages = Math.max(1, Math.ceil(filtered.length / perPage));
                if (currentPage > totalPages) currentPage = totalPages;
                render();
            }

            // ── 5. Send server-side deletes in batches of 5 ──────────
            //    Firing all XHRs at once overwhelms Local's PHP-FPM pool
            //    and produces 502 Bad Gateway errors in the console.
            //    Batching keeps concurrent requests within server limits.
            var BATCH = 5;
            function sendBatch(offset) {
                var slice = toDelete.slice(offset, offset + BATCH);
                if (!slice.length) return;
                var done = 0;
                slice.forEach(function(item) {
                    var xhr = new XMLHttpRequest();
                    xhr.open('POST', AJAX_URL, true);
                    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                    xhr.timeout = 15000;
                    xhr.onloadend = function() {
                        done++;
                        if (done === slice.length) {
                            // All in this batch finished — start next batch
                            sendBatch(offset + BATCH);
                        }
                    };
                    xhr.onerror = xhr.ontimeout = function() {
                        done++;
                        if (done === slice.length) sendBatch(offset + BATCH);
                    };
                    xhr.send('action=tmpmp_delete_inbox_address&nonce=' + encodeURIComponent(NONCE) + '&address_id=' + encodeURIComponent(item.id));
                });
            }
            sendBatch(0);
        }, 260);
    });

    /* ── events: cancel bulk selection ──────────────────────────────── */
    bulkCancel.addEventListener('click', clearSelection);

    /* ── events: individual delete button ───────────────────────────── */
    tbody.addEventListener('click', function(e) {
        var btn = e.target.closest('.tmpmp-inbox-del-btn');
        if (!btn) return;
        var id   = btn.getAttribute('data-id');
        var addr = btn.getAttribute('data-addr');
        var row  = btn.closest('tr');
        if (!row || !id) return;
        if (!confirm('<?php echo esc_js(__('Delete inbox','tempmail-pro')); ?> "' + addr + '"?\n<?php echo esc_js(__('This will permanently remove the address and all its emails.','tempmail-pro')); ?>')) return;
        deleteRow(row, id, addr, null);
    });

    /* ── initial render ─────────────────────────────────────────────── */
    render();
}());
</script>



