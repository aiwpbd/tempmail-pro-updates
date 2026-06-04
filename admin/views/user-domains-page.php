<?php
if ( ! defined('ABSPATH') ) exit;

// ── Page params ───────────────────────────────────────────────────────────────
$search   = sanitize_text_field( $_GET['s']      ?? '' );
$status_f = sanitize_key(        $_GET['status']  ?? 'all' );
$page_num = max( 1, (int)       ( $_GET['paged']  ?? 1 ) );
$per_page = 20;

$data  = TempMail_UserDomains::get_all_for_admin([
    'search'   => $search,
    'status'   => $status_f,
    'page'     => $page_num,
    'per_page' => $per_page,
]);
$rows  = $data['rows'];
$total = $data['total'];
$pages = (int) ceil( $total / $per_page );
$stats = TempMail_UserDomains::get_stats_for_admin();

// User list for "Add Domain" modal (all WP users)
$wp_users = get_users(['fields' => ['ID','display_name','user_email'], 'number' => 500]);

$nonce    = wp_create_nonce('tempmail_pro_nonce');
$ajax_url = admin_url('admin-ajax.php');
$base_url = admin_url('admin.php?page=tmpmp-user-domains');

// Helper: time-ago label
function tmpmp_ud_ago( string $dt ) : string {
    if ( ! $dt ) return '—';
    $diff = time() - strtotime($dt);
    if ( $diff < 60 )    return 'Just now';
    if ( $diff < 3600 )  return round($diff/60)  . 'm ago';
    if ( $diff < 86400 ) return round($diff/3600) . 'h ago';
    return round($diff/86400) . 'd ago';
}
?>
<div class="wrap tmpmp-admin-wrap tmpmp-ud-wrap">

<!-- ── Page title ─────────────────────────────────────────────────────────── -->
<h1 class="tmpmp-admin-title">
    <span style="font-size:22px;">🔐</span>
    <?php esc_html_e('User Custom Domains','tempmail-pro'); ?>
    <span class="tmpmp-version-pill">v<?php echo esc_html(TMPMP_VERSION); ?></span>
    <button class="tmpmp-ud-btn tmpmp-ud-btn--primary" id="tmpmp-ud-open-add" style="margin-left:auto;">
        ➕ <?php esc_html_e('Add Domain','tempmail-pro'); ?>
    </button>
    <a class="tmpmp-ud-btn tmpmp-ud-btn--ghost" id="tmpmp-ud-export-csv" href="#">
        ⬇ <?php esc_html_e('Export CSV','tempmail-pro'); ?>
    </a>
</h1>

<style>
/* ── Reset / base ──────────────────────────────────────────────────────────── */
.tmpmp-ud-wrap *{box-sizing:border-box;}
.tmpmp-ud-wrap{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;color:#0f172a;}

/* ── Stat cards ────────────────────────────────────────────────────────────── */
.tmpmp-ud-stats{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:20px;}
.tmpmp-ud-stat{background:#fff;border:1px solid #e2e8f0;border-radius:14px;padding:18px 20px;display:flex;align-items:center;gap:14px;transition:box-shadow .15s;}
.tmpmp-ud-stat:hover{box-shadow:0 4px 16px rgba(99,102,241,.1);}
.tmpmp-ud-stat-icon{width:46px;height:46px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:20px;flex-shrink:0;}
.tmpmp-ud-stat-icon.total    {background:linear-gradient(135deg,#ede9fe,#ddd6fe);}
.tmpmp-ud-stat-icon.verified {background:linear-gradient(135deg,#dcfce7,#bbf7d0);}
.tmpmp-ud-stat-icon.pending  {background:linear-gradient(135deg,#fef9c3,#fde68a);}
.tmpmp-ud-stat-icon.suspended{background:linear-gradient(135deg,#fee2e2,#fecaca);}
.tmpmp-ud-stat-num{font-size:26px;font-weight:800;line-height:1;color:#0f172a;}
.tmpmp-ud-stat-label{font-size:12px;color:#64748b;margin-top:2px;}

/* ── Toolbar ───────────────────────────────────────────────────────────────── */
.tmpmp-ud-toolbar{display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:16px;}
.tmpmp-ud-search{flex:1;min-width:220px;max-width:340px;padding:9px 14px;border:1.5px solid #e2e8f0;border-radius:9px;font-size:13px;font-family:inherit;outline:none;transition:border-color .15s;}
.tmpmp-ud-search:focus{border-color:#6366f1;box-shadow:0 0 0 3px rgba(99,102,241,.1);}
.tmpmp-ud-filter{padding:9px 14px;border:1.5px solid #e2e8f0;border-radius:9px;font-size:13px;background:#fff;cursor:pointer;font-family:inherit;}
.tmpmp-ud-filter:focus{border-color:#6366f1;}
.tmpmp-ud-bulk-select{padding:9px 14px;border:1.5px solid #e2e8f0;border-radius:9px;font-size:13px;background:#fff;cursor:pointer;}
.tmpmp-ud-bulk-btn{padding:9px 16px;background:#f1f5f9;border:1.5px solid #e2e8f0;border-radius:9px;font-size:13px;font-weight:600;cursor:pointer;transition:all .15s;}
.tmpmp-ud-bulk-btn:hover{background:#e2e8f0;}
.tmpmp-ud-count{margin-left:auto;font-size:12px;color:#94a3b8;white-space:nowrap;}

/* ── Buttons ───────────────────────────────────────────────────────────────── */
.tmpmp-ud-btn{display:inline-flex;align-items:center;gap:6px;padding:8px 16px;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;border:none;text-decoration:none;transition:all .15s;font-family:inherit;}
.tmpmp-ud-btn--primary{background:linear-gradient(135deg,#6366f1,#8b5cf6);color:#fff;}
.tmpmp-ud-btn--primary:hover{opacity:.9;transform:translateY(-1px);box-shadow:0 4px 12px rgba(99,102,241,.3);color:#fff;}
.tmpmp-ud-btn--ghost{background:#f8fafc;color:#475569;border:1.5px solid #e2e8f0;}
.tmpmp-ud-btn--ghost:hover{border-color:#6366f1;color:#6366f1;}
.tmpmp-ud-icon-btn{display:inline-flex;align-items:center;gap:4px;padding:5px 10px;border-radius:7px;font-size:12px;font-weight:600;cursor:pointer;border:1.5px solid #e2e8f0;background:#fff;transition:all .15s;white-space:nowrap;font-family:inherit;}
.tmpmp-ud-icon-btn:hover{border-color:#6366f1;color:#6366f1;}
.tmpmp-ud-icon-btn--danger:hover{border-color:#ef4444;color:#ef4444;}
.tmpmp-ud-icon-btn--warn:hover{border-color:#f59e0b;color:#d97706;}
.tmpmp-ud-icon-btn--blue{border-color:#0ea5e9;color:#0369a1;}
.tmpmp-ud-icon-btn--blue:hover{background:#e0f2fe;border-color:#0369a1;}
.tmpmp-ud-icon-btn--green:hover{border-color:#16a34a;color:#16a34a;}

/* ── Card / Table ──────────────────────────────────────────────────────────── */
.tmpmp-ud-card{background:#fff;border:1px solid #e2e8f0;border-radius:14px;overflow:hidden;margin-bottom:20px;}
.tmpmp-ud-table{width:100%;border-collapse:collapse;font-size:13px;}
.tmpmp-ud-table th{background:#f8fafc;color:#475569;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;padding:11px 14px;text-align:left;border-bottom:2px solid #e2e8f0;}
.tmpmp-ud-table td{padding:13px 14px;border-bottom:1px solid #f1f5f9;color:#334155;vertical-align:middle;}
.tmpmp-ud-table tr:last-child td{border-bottom:none;}
.tmpmp-ud-table tr:hover td{background:#fafbff;}
.tmpmp-ud-table th:first-child,.tmpmp-ud-table td:first-child{width:36px;padding-right:0;}
.tmpmp-ud-table input[type=checkbox]{width:16px;height:16px;cursor:pointer;accent-color:#6366f1;}

/* ── User cell ─────────────────────────────────────────────────────────────── */
.tmpmp-ud-user{display:flex;align-items:center;gap:9px;}
.tmpmp-ud-avatar{width:30px;height:30px;border-radius:50%;background:linear-gradient(135deg,#6366f1,#8b5cf6);display:flex;align-items:center;justify-content:center;color:#fff;font-size:11px;font-weight:700;flex-shrink:0;}
.tmpmp-ud-user-name{font-weight:600;font-size:13px;color:#0f172a;}
.tmpmp-ud-user-email{font-size:11px;color:#94a3b8;margin-top:1px;}

/* ── Domain cell ───────────────────────────────────────────────────────────── */
.tmpmp-ud-domain-name{font-weight:700;font-size:13px;color:#0f172a;word-break:break-all;}
.tmpmp-ud-domain-token{font-size:10px;color:#94a3b8;margin-top:2px;word-break:break-all;}

/* ── Status badges ─────────────────────────────────────────────────────────── */
.tmpmp-ud-badge{display:inline-flex;align-items:center;gap:5px;padding:4px 11px;border-radius:20px;font-size:11px;font-weight:700;white-space:nowrap;}
.tmpmp-ud-badge--verified {background:#dcfce7;color:#16a34a;}
.tmpmp-ud-badge--pending  {background:#fef9c3;color:#ca8a04;}
.tmpmp-ud-badge--suspended{background:#fee2e2;color:#dc2626;}

/* ── DNS check pips ────────────────────────────────────────────────────────── */
.tmpmp-ud-pips{display:flex;gap:4px;flex-wrap:wrap;}
.tmpmp-ud-pip{width:22px;height:22px;border-radius:6px;display:flex;align-items:center;justify-content:center;font-size:10px;font-weight:700;cursor:default;position:relative;}
.tmpmp-ud-pip.pass{background:#dcfce7;color:#16a34a;}
.tmpmp-ud-pip.fail{background:#fee2e2;color:#dc2626;}
.tmpmp-ud-pip:hover::after{content:attr(data-tip);position:absolute;bottom:calc(100% + 5px);left:50%;transform:translateX(-50%);background:#0f172a;color:#fff;font-size:10px;padding:4px 8px;border-radius:6px;white-space:nowrap;z-index:999;pointer-events:none;}

/* ── Time cell ─────────────────────────────────────────────────────────────── */
.tmpmp-ud-time{font-size:11px;color:#94a3b8;}
.tmpmp-ud-time strong{color:#475569;font-size:12px;display:block;}

/* ── Actions cell ──────────────────────────────────────────────────────────── */
.tmpmp-ud-actions{display:flex;gap:5px;flex-wrap:wrap;}

/* ── Empty / loading ───────────────────────────────────────────────────────── */
.tmpmp-ud-empty{text-align:center;padding:48px 20px;color:#94a3b8;}
.tmpmp-ud-empty-icon{font-size:40px;margin-bottom:10px;}
.tmpmp-ud-empty p{margin:0;font-size:14px;}

/* ── Pagination ────────────────────────────────────────────────────────────── */
.tmpmp-ud-pagination{display:flex;align-items:center;gap:6px;padding:14px 16px;border-top:1px solid #f1f5f9;justify-content:flex-end;}
.tmpmp-ud-pagination a,.tmpmp-ud-pagination span{display:inline-flex;align-items:center;justify-content:center;width:32px;height:32px;border-radius:7px;font-size:13px;font-weight:600;text-decoration:none;border:1.5px solid #e2e8f0;color:#475569;transition:all .15s;}
.tmpmp-ud-pagination a:hover{border-color:#6366f1;color:#6366f1;}
.tmpmp-ud-pagination span.current{background:#6366f1;border-color:#6366f1;color:#fff;}
.tmpmp-ud-pagination span.dots{border:none;color:#94a3b8;}

/* ── Modals shared ─────────────────────────────────────────────────────────── */
.tmpmp-ud-overlay{display:none;position:fixed;inset:0;z-index:999998;background:rgba(15,23,42,.55);backdrop-filter:blur(4px);align-items:center;justify-content:center;padding:16px;}
.tmpmp-ud-overlay.is-open{display:flex;}
.tmpmp-ud-modal{background:#fff;border-radius:18px;width:100%;max-width:540px;max-height:90vh;display:flex;flex-direction:column;overflow:hidden;box-shadow:0 25px 60px rgba(15,23,42,.25),0 0 0 1px rgba(15,23,42,.06);animation:tmpmp-ud-slide-up .25s cubic-bezier(.34,1.56,.64,1);}
.tmpmp-ud-modal--wide{max-width:700px;}
@keyframes tmpmp-ud-slide-up{from{opacity:0;transform:translateY(24px) scale(.97);}to{opacity:1;transform:translateY(0) scale(1);}}
.tmpmp-ud-modal-head{display:flex;align-items:center;gap:12px;padding:20px 22px 16px;border-bottom:1px solid #f1f5f9;flex-shrink:0;}
.tmpmp-ud-modal-head-icon{width:42px;height:42px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:18px;flex-shrink:0;}
.tmpmp-ud-modal-head-text{flex:1;}
.tmpmp-ud-modal-head-text h3{margin:0 0 2px;font-size:16px;font-weight:700;color:#0f172a;}
.tmpmp-ud-modal-head-text p{margin:0;font-size:12px;color:#64748b;}
.tmpmp-ud-modal-close{background:none;border:none;cursor:pointer;padding:6px;border-radius:8px;color:#94a3b8;font-size:18px;line-height:1;transition:all .15s;display:flex;align-items:center;justify-content:center;width:30px;height:30px;}
.tmpmp-ud-modal-close:hover{background:#f1f5f9;color:#334155;}
.tmpmp-ud-modal-body{padding:20px 22px;overflow-y:auto;flex:1;}
.tmpmp-ud-modal-foot{padding:14px 22px;border-top:1px solid #f1f5f9;display:flex;gap:10px;justify-content:flex-end;flex-shrink:0;}

/* ── Form fields ───────────────────────────────────────────────────────────── */
.tmpmp-ud-field{margin-bottom:16px;}
.tmpmp-ud-field label{display:block;font-size:12px;font-weight:700;color:#374151;margin-bottom:6px;text-transform:uppercase;letter-spacing:.4px;}
.tmpmp-ud-field input,.tmpmp-ud-field select{width:100%;padding:9px 13px;border:1.5px solid #e2e8f0;border-radius:9px;font-size:13px;font-family:inherit;outline:none;transition:border-color .15s;background:#fff;}
.tmpmp-ud-field input:focus,.tmpmp-ud-field select:focus{border-color:#6366f1;box-shadow:0 0 0 3px rgba(99,102,241,.1);}
.tmpmp-ud-field .hint{font-size:11px;color:#94a3b8;margin-top:5px;}

/* ── DNS check rows (verify modal) ────────────────────────────────────────── */
.tmpmp-ud-dns-row{display:flex;align-items:center;gap:12px;padding:13px 0;border-bottom:1px solid #f8fafc;}
.tmpmp-ud-dns-row:last-child{border-bottom:none;}
.tmpmp-ud-dns-dot{width:28px;height:28px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:13px;flex-shrink:0;}
.tmpmp-ud-dns-dot.pass{background:#dcfce7;color:#16a34a;}
.tmpmp-ud-dns-dot.fail{background:#fee2e2;color:#dc2626;}
.tmpmp-ud-dns-dot.loading{background:#f1f5f9;color:#94a3b8;animation:tmpmp-ud-pulse 1s infinite;}
@keyframes tmpmp-ud-pulse{0%,100%{opacity:1;}50%{opacity:.4;}}
.tmpmp-ud-dns-info{flex:1;min-width:0;}
.tmpmp-ud-dns-info strong{font-size:13px;color:#0f172a;display:block;}
.tmpmp-ud-dns-info span{font-size:11px;color:#64748b;word-break:break-all;}
.tmpmp-ud-dns-pill{font-size:10px;font-weight:700;padding:2px 8px;border-radius:20px;flex-shrink:0;}
.tmpmp-ud-dns-pill.pass{background:#dcfce7;color:#16a34a;}
.tmpmp-ud-dns-pill.fail{background:#fee2e2;color:#dc2626;}
.tmpmp-ud-dns-pill.loading{background:#f1f5f9;color:#94a3b8;}
.tmpmp-ud-dns-summary{padding:12px 14px;border-radius:10px;font-size:13px;font-weight:600;margin-bottom:16px;display:none;}
.tmpmp-ud-dns-summary.pass{background:#f0fdf4;color:#16a34a;border:1px solid #bbf7d0;}
.tmpmp-ud-dns-summary.fail{background:#fef2f2;color:#dc2626;border:1px solid #fecaca;}
.tmpmp-ud-dns-summary.partial{background:#fefce8;color:#ca8a04;border:1px solid #fde68a;}

/* ── Toast ─────────────────────────────────────────────────────────────────── */
#tmpmp-ud-toast{position:fixed;bottom:24px;right:24px;z-index:9999999;display:flex;flex-direction:column;gap:8px;pointer-events:none;}
.tmpmp-ud-toast-item{padding:12px 18px;border-radius:10px;font-size:13px;font-weight:600;color:#fff;box-shadow:0 4px 16px rgba(0,0,0,.15);animation:tmpmp-ud-toast-in .25s ease;pointer-events:auto;}
.tmpmp-ud-toast-item.success{background:linear-gradient(135deg,#16a34a,#15803d);}
.tmpmp-ud-toast-item.error  {background:linear-gradient(135deg,#dc2626,#b91c1c);}
.tmpmp-ud-toast-item.info   {background:linear-gradient(135deg,#6366f1,#4f46e5);}
@keyframes tmpmp-ud-toast-in{from{opacity:0;transform:translateY(12px);}to{opacity:1;transform:translateY(0);}}

/* ── Spin ──────────────────────────────────────────────────────────────────── */
@keyframes tmpmp-ud-spin{to{transform:rotate(360deg);}}
.tmpmp-ud-spin{display:inline-block;animation:tmpmp-ud-spin .7s linear infinite;}

/* ── Responsive ────────────────────────────────────────────────────────────── */
@media(max-width:900px){.tmpmp-ud-stats{grid-template-columns:repeat(2,1fr);}
    .tmpmp-ud-table th:nth-child(6),.tmpmp-ud-table td:nth-child(6){display:none;}}
@media(max-width:640px){.tmpmp-ud-stats{grid-template-columns:1fr 1fr;}
    .tmpmp-ud-table th:nth-child(5),.tmpmp-ud-table td:nth-child(5){display:none;}
    .tmpmp-ud-modal{border-radius:16px 16px 0 0;position:fixed;bottom:0;left:0;right:0;max-width:100%;max-height:85vh;}
    .tmpmp-ud-overlay{align-items:flex-end;padding:0;}}
</style>

<!-- ── Stat cards ─────────────────────────────────────────────────────────── -->
<div class="tmpmp-ud-stats">
    <?php
    $stat_items = [
        ['icon'=>'🌐','label'=>'Total Domains',    'key'=>'total',    'class'=>'total'],
        ['icon'=>'✅','label'=>'Verified',          'key'=>'verified',  'class'=>'verified'],
        ['icon'=>'⏳','label'=>'Pending Verification','key'=>'pending','class'=>'pending'],
        ['icon'=>'🚫','label'=>'Suspended',         'key'=>'suspended', 'class'=>'suspended'],
    ];
    foreach ( $stat_items as $s ) : ?>
    <div class="tmpmp-ud-stat">
        <div class="tmpmp-ud-stat-icon <?php echo $s['class']; ?>"><?php echo $s['icon']; ?></div>
        <div>
            <div class="tmpmp-ud-stat-num" id="tmpmp-ud-stat-<?php echo $s['key']; ?>"><?php echo number_format($stats[$s['key']]); ?></div>
            <div class="tmpmp-ud-stat-label"><?php echo esc_html($s['label']); ?></div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- ── Toolbar ────────────────────────────────────────────────────────────── -->
<form method="get" action="<?php echo esc_url($base_url); ?>" style="display:contents;">
    <input type="hidden" name="page" value="tmpmp-user-domains">
    <div class="tmpmp-ud-toolbar">
        <input type="text" name="s" class="tmpmp-ud-search"
               placeholder="🔍 Search domain, user or email…"
               value="<?php echo esc_attr($search); ?>">

        <select name="status" class="tmpmp-ud-filter" onchange="this.form.submit()">
            <?php foreach ( ['all'=>'All Statuses','verified'=>'✅ Verified','pending'=>'⏳ Pending','suspended'=>'🚫 Suspended'] as $v => $l ) : ?>
            <option value="<?php echo $v; ?>" <?php selected($status_f,$v); ?>><?php echo esc_html($l); ?></option>
            <?php endforeach; ?>
        </select>

        <button type="submit" class="tmpmp-ud-btn tmpmp-ud-btn--ghost" style="padding:8px 14px;">
            🔍 <?php esc_html_e('Search','tempmail-pro'); ?>
        </button>

        <?php if ( $search || $status_f !== 'all' ) : ?>
        <a href="<?php echo esc_url($base_url); ?>" class="tmpmp-ud-btn tmpmp-ud-btn--ghost" style="padding:8px 12px;">✕ Clear</a>
        <?php endif; ?>

        <!-- Bulk controls -->
        <select id="tmpmp-ud-bulk-action" class="tmpmp-ud-bulk-select" style="margin-left:auto;">
            <option value=""><?php esc_html_e('Bulk Action…','tempmail-pro'); ?></option>
            <option value="verify"><?php esc_html_e('Verify DNS','tempmail-pro'); ?></option>
            <option value="suspend"><?php esc_html_e('Suspend','tempmail-pro'); ?></option>
            <option value="unsuspend"><?php esc_html_e('Unsuspend','tempmail-pro'); ?></option>
            <option value="delete"><?php esc_html_e('Delete','tempmail-pro'); ?></option>
        </select>
        <button type="button" class="tmpmp-ud-bulk-btn" id="tmpmp-ud-bulk-apply">
            <?php esc_html_e('Apply','tempmail-pro'); ?>
        </button>

        <span class="tmpmp-ud-count"><?php printf( esc_html__('%d domain(s) found','tempmail-pro'), $total ); ?></span>
    </div>
</form>

<!-- ── Main table ─────────────────────────────────────────────────────────── -->
<div class="tmpmp-ud-card">
    <div style="overflow-x:auto;">
    <table class="tmpmp-ud-table" id="tmpmp-ud-table">
        <thead><tr>
            <th><input type="checkbox" id="tmpmp-ud-check-all" title="Select all"></th>
            <th><?php esc_html_e('User','tempmail-pro'); ?></th>
            <th><?php esc_html_e('Domain','tempmail-pro'); ?></th>
            <th><?php esc_html_e('Status','tempmail-pro'); ?></th>
            <th><?php esc_html_e('DNS Records','tempmail-pro'); ?></th>
            <th><?php esc_html_e('Last Checked','tempmail-pro'); ?></th>
            <th><?php esc_html_e('Created','tempmail-pro'); ?></th>
            <th><?php esc_html_e('Actions','tempmail-pro'); ?></th>
        </tr></thead>
        <tbody>
        <?php if ( empty($rows) ) : ?>
        <tr>
            <td colspan="8">
                <div class="tmpmp-ud-empty">
                    <div class="tmpmp-ud-empty-icon">🔐</div>
                    <p><?php esc_html_e('No user custom domains found.','tempmail-pro'); ?></p>
                </div>
            </td>
        </tr>
        <?php else : foreach ( $rows as $row ) :
            $st      = $row->status ?? 'pending';
            $all_ok  = $row->txt_verified && $row->mx_verified && $row->spf_verified && $row->dkim_verified && $row->dmarc_verified;
            $initials= strtoupper( substr( $row->user_display_name ?? $row->user_login ?? '?', 0, 2 ) );
            $row_id  = (int) $row->id;

            // DNS pip data
            $pips = [
                'TXT' => (bool)$row->txt_verified,
                'MX'  => (bool)$row->mx_verified,
                'SPF' => (bool)$row->spf_verified,
                'DKIM'=> (bool)$row->dkim_verified,
                'DMARC'=> (bool)$row->dmarc_verified,
            ];
        ?>
        <tr id="tmpmp-ud-row-<?php echo $row_id; ?>"
            data-id="<?php echo $row_id; ?>"
            data-domain="<?php echo esc_attr($row->domain); ?>"
            data-status="<?php echo esc_attr($st); ?>">

            <!-- Checkbox -->
            <td><input type="checkbox" class="tmpmp-ud-row-check" value="<?php echo $row_id; ?>"></td>

            <!-- User -->
            <td>
                <div class="tmpmp-ud-user">
                    <div class="tmpmp-ud-avatar"><?php echo esc_html($initials); ?></div>
                    <div>
                        <div class="tmpmp-ud-user-name"><?php echo esc_html($row->user_display_name ?: $row->user_login ?: 'User #'.$row->user_id); ?></div>
                        <div class="tmpmp-ud-user-email"><?php echo esc_html($row->user_email ?? ''); ?></div>
                    </div>
                </div>
            </td>

            <!-- Domain -->
            <td>
                <div class="tmpmp-ud-domain-name"><?php echo esc_html($row->domain); ?></div>
                <div class="tmpmp-ud-domain-token" title="Verify token">🔑 <?php echo esc_html(substr($row->verify_token ?? '',0,24)); ?>…</div>
            </td>

            <!-- Status badge -->
            <td>
                <span class="tmpmp-ud-badge tmpmp-ud-badge--<?php echo esc_attr($st); ?>" id="tmpmp-ud-badge-<?php echo $row_id; ?>">
                    <?php echo ['verified'=>'✅ Verified','pending'=>'⏳ Pending','suspended'=>'🚫 Suspended'][$st] ?? esc_html($st); ?>
                </span>
            </td>

            <!-- DNS pips -->
            <td>
                <div class="tmpmp-ud-pips" id="tmpmp-ud-pips-<?php echo $row_id; ?>">
                    <?php foreach ( $pips as $label => $ok ) : ?>
                    <div class="tmpmp-ud-pip <?php echo $ok ? 'pass' : 'fail'; ?>"
                         data-tip="<?php echo esc_attr($label.': '.($ok?'Pass':'Fail')); ?>">
                        <?php echo $ok ? '✓' : '✗'; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </td>

            <!-- Last checked -->
            <td>
                <div class="tmpmp-ud-time" id="tmpmp-ud-checked-<?php echo $row_id; ?>">
                    <?php if ( $row->last_checked ) : ?>
                    <strong><?php echo esc_html(date_i18n('M j, Y', strtotime($row->last_checked))); ?></strong>
                    <?php echo esc_html(tmpmp_ud_ago($row->last_checked)); ?>
                    <?php else : ?>—<?php endif; ?>
                </div>
            </td>

            <!-- Created -->
            <td>
                <div class="tmpmp-ud-time">
                    <strong><?php echo esc_html(date_i18n('M j, Y', strtotime($row->created_at))); ?></strong>
                    <?php echo esc_html(tmpmp_ud_ago($row->created_at)); ?>
                </div>
            </td>

            <!-- Actions -->
            <td>
                <div class="tmpmp-ud-actions">
                    <!-- Verify/Health Check -->
                    <button class="tmpmp-ud-icon-btn tmpmp-ud-icon-btn--blue tmpmp-ud-verify-btn"
                            data-id="<?php echo $row_id; ?>" data-domain="<?php echo esc_attr($row->domain); ?>"
                            title="<?php esc_attr_e('Verify DNS Records','tempmail-pro'); ?>">
                        🔍 <?php esc_html_e('Verify','tempmail-pro'); ?>
                    </button>

                    <!-- Suspend / Unsuspend -->
                    <?php if ( $st === 'suspended' ) : ?>
                    <button class="tmpmp-ud-icon-btn tmpmp-ud-icon-btn--green tmpmp-ud-suspend-btn"
                            data-id="<?php echo $row_id; ?>" data-action="unsuspend"
                            title="<?php esc_attr_e('Unsuspend domain','tempmail-pro'); ?>">
                        ✅ <?php esc_html_e('Unsuspend','tempmail-pro'); ?>
                    </button>
                    <?php else : ?>
                    <button class="tmpmp-ud-icon-btn tmpmp-ud-icon-btn--warn tmpmp-ud-suspend-btn"
                            data-id="<?php echo $row_id; ?>" data-action="suspend"
                            title="<?php esc_attr_e('Suspend domain','tempmail-pro'); ?>">
                        🚫 <?php esc_html_e('Suspend','tempmail-pro'); ?>
                    </button>
                    <?php endif; ?>

                    <!-- Delete -->
                    <button class="tmpmp-ud-icon-btn tmpmp-ud-icon-btn--danger tmpmp-ud-delete-btn"
                            data-id="<?php echo $row_id; ?>" data-domain="<?php echo esc_attr($row->domain); ?>"
                            title="<?php esc_attr_e('Delete domain','tempmail-pro'); ?>">
                        🗑
                    </button>
                </div>
            </td>
        </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>
    </div>

    <!-- Pagination -->
    <?php if ( $pages > 1 ) :
        $current  = $page_num;
        $pg_base  = add_query_arg(['page'=>'tmpmp-user-domains','s'=>$search,'status'=>$status_f], admin_url('admin.php'));
    ?>
    <div class="tmpmp-ud-pagination">
        <?php if ( $current > 1 ) : ?>
            <a href="<?php echo esc_url(add_query_arg('paged', $current-1, $pg_base)); ?>">‹</a>
        <?php endif; ?>
        <?php for ( $p = 1; $p <= $pages; $p++ ) :
            if ( $p === $current ) : ?>
                <span class="current"><?php echo $p; ?></span>
            <?php elseif ( abs($p - $current) <= 2 || $p === 1 || $p === $pages ) : ?>
                <a href="<?php echo esc_url(add_query_arg('paged',$p,$pg_base)); ?>"><?php echo $p; ?></a>
            <?php elseif ( abs($p - $current) === 3 ) : ?>
                <span class="dots">…</span>
            <?php endif;
        endfor; ?>
        <?php if ( $current < $pages ) : ?>
            <a href="<?php echo esc_url(add_query_arg('paged', $current+1, $pg_base)); ?>">›</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div><!-- /.tmpmp-ud-card -->

<!-- ════════════════════════════════════════════════════════════════════════
     MODALS
     ════════════════════════════════════════════════════════════════════════ -->

<!-- ── Add Domain Modal ───────────────────────────────────────────────────── -->
<div class="tmpmp-ud-overlay" id="tmpmp-ud-add-overlay">
    <div class="tmpmp-ud-modal" role="dialog" aria-modal="true" aria-labelledby="tmpmp-ud-add-title">
        <div class="tmpmp-ud-modal-head">
            <div class="tmpmp-ud-modal-head-icon" style="background:linear-gradient(135deg,#ede9fe,#ddd6fe);">➕</div>
            <div class="tmpmp-ud-modal-head-text">
                <h3 id="tmpmp-ud-add-title"><?php esc_html_e('Add Custom Domain','tempmail-pro'); ?></h3>
                <p><?php esc_html_e('Assign a custom domain to any user account.','tempmail-pro'); ?></p>
            </div>
            <button class="tmpmp-ud-modal-close tmpmp-ud-close-modal">✕</button>
        </div>
        <div class="tmpmp-ud-modal-body">
            <div class="tmpmp-ud-field">
                <label for="tmpmp-ud-add-user"><?php esc_html_e('User Account','tempmail-pro'); ?></label>
                <select id="tmpmp-ud-add-user">
                    <option value=""><?php esc_html_e('— Select a user —','tempmail-pro'); ?></option>
                    <?php foreach ( $wp_users as $u ) : ?>
                    <option value="<?php echo intval($u->ID); ?>">
                        <?php echo esc_html("{$u->display_name} ({$u->user_email})"); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <p class="hint"><?php esc_html_e('The domain will be assigned to this user\'s account.','tempmail-pro'); ?></p>
            </div>
            <div class="tmpmp-ud-field">
                <label for="tmpmp-ud-add-domain"><?php esc_html_e('Domain Name','tempmail-pro'); ?></label>
                <input type="text" id="tmpmp-ud-add-domain" placeholder="mail.company.com">
                <p class="hint"><?php esc_html_e('Enter a valid domain or subdomain. No http:// prefix needed.','tempmail-pro'); ?></p>
            </div>
            <div id="tmpmp-ud-add-error" style="display:none;padding:10px 14px;background:#fef2f2;border:1px solid #fecaca;border-radius:9px;color:#dc2626;font-size:13px;margin-top:4px;"></div>
        </div>
        <div class="tmpmp-ud-modal-foot">
            <button class="tmpmp-ud-btn tmpmp-ud-btn--ghost tmpmp-ud-close-modal" style="padding:8px 18px;"><?php esc_html_e('Cancel','tempmail-pro'); ?></button>
            <button class="tmpmp-ud-btn tmpmp-ud-btn--primary" id="tmpmp-ud-add-submit" style="padding:9px 22px;">
                ➕ <?php esc_html_e('Add Domain','tempmail-pro'); ?>
            </button>
        </div>
    </div>
</div>

<!-- ── Verify / Health-Check Modal ───────────────────────────────────────── -->
<div class="tmpmp-ud-overlay" id="tmpmp-ud-verify-overlay">
    <div class="tmpmp-ud-modal tmpmp-ud-modal--wide" role="dialog" aria-modal="true" aria-labelledby="tmpmp-ud-verify-title">
        <div class="tmpmp-ud-modal-head">
            <div class="tmpmp-ud-modal-head-icon" id="tmpmp-ud-verify-icon" style="background:linear-gradient(135deg,#e0f2fe,#bae6fd);">🔍</div>
            <div class="tmpmp-ud-modal-head-text">
                <h3 id="tmpmp-ud-verify-title"><?php esc_html_e('DNS Verification','tempmail-pro'); ?></h3>
                <p id="tmpmp-ud-verify-sub"><?php esc_html_e('Checking all 5 DNS records…','tempmail-pro'); ?></p>
            </div>
            <button class="tmpmp-ud-modal-close" id="tmpmp-ud-verify-close">✕</button>
        </div>
        <div class="tmpmp-ud-modal-body">
            <div class="tmpmp-ud-dns-summary" id="tmpmp-ud-dns-summary"></div>
            <div id="tmpmp-ud-dns-rows">
                <!-- Filled by JS -->
                <?php foreach ( ['TXT Ownership','MX Record','SPF Record','DKIM Record','DMARC Record'] as $i => $label ) : ?>
                <div class="tmpmp-ud-dns-row">
                    <div class="tmpmp-ud-dns-dot loading">⟳</div>
                    <div class="tmpmp-ud-dns-info">
                        <strong><?php echo esc_html($label); ?></strong>
                        <span><?php esc_html_e('Checking…','tempmail-pro'); ?></span>
                    </div>
                    <span class="tmpmp-ud-dns-pill loading"><?php esc_html_e('WAIT','tempmail-pro'); ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="tmpmp-ud-modal-foot">
            <span style="font-size:11px;color:#94a3b8;margin-right:auto;" id="tmpmp-ud-verify-meta"></span>
            <button class="tmpmp-ud-btn tmpmp-ud-btn--ghost tmpmp-ud-verify-rerun" id="tmpmp-ud-verify-rerun" style="padding:8px 16px;display:none;">
                🔄 <?php esc_html_e('Re-check','tempmail-pro'); ?>
            </button>
            <button class="tmpmp-ud-btn tmpmp-ud-btn--ghost" id="tmpmp-ud-verify-close-btn" style="padding:8px 18px;"><?php esc_html_e('Close','tempmail-pro'); ?></button>
        </div>
    </div>
</div>

<!-- Toast container -->
<div id="tmpmp-ud-toast"></div>

<!-- ════════════════════════════════════════════════════════════════════════
     JavaScript
     ════════════════════════════════════════════════════════════════════════ -->
<script>
(function($){
    var AJAX = '<?php echo esc_js($ajax_url); ?>';
    var NONCE= '<?php echo esc_js($nonce); ?>';

    /* ── Toast ──────────────────────────────────────────────────────────── */
    function toast(msg, type){
        type = type || 'success';
        var $t = $('<div class="tmpmp-ud-toast-item '+type+'">'+escHtml(msg)+'</div>');
        $('#tmpmp-ud-toast').append($t);
        setTimeout(function(){ $t.fadeOut(400, function(){ $t.remove(); }); }, 3000);
    }

    /* ── Modal helpers ──────────────────────────────────────────────────── */
    function openOverlay(id){ $('#'+id).addClass('is-open'); $('body').css('overflow','hidden'); }
    function closeOverlay(id){ $('#'+id).removeClass('is-open'); $('body').css('overflow',''); }

    $(document).on('click', '.tmpmp-ud-close-modal', function(){
        $(this).closest('.tmpmp-ud-overlay').removeClass('is-open');
        $('body').css('overflow','');
    });
    $('.tmpmp-ud-overlay').on('click', function(e){
        if($(e.target).is($(this))){ $(this).removeClass('is-open'); $('body').css('overflow',''); }
    });
    $(document).on('keydown', function(e){ if(e.key==='Escape'){ $('.tmpmp-ud-overlay.is-open').first().removeClass('is-open'); $('body').css('overflow',''); }});

    /* ── Select all ─────────────────────────────────────────────────────── */
    $('#tmpmp-ud-check-all').on('change', function(){
        $('.tmpmp-ud-row-check').prop('checked', $(this).is(':checked'));
    });

    /* ── Open Add modal ─────────────────────────────────────────────────── */
    $('#tmpmp-ud-open-add').on('click', function(){ openOverlay('tmpmp-ud-add-overlay'); });

    /* ── Add Domain submit ──────────────────────────────────────────────── */
    $('#tmpmp-ud-add-submit').on('click', function(){
        var uid    = $('#tmpmp-ud-add-user').val();
        var domain = $('#tmpmp-ud-add-domain').val().trim();
        var $err   = $('#tmpmp-ud-add-error').hide();
        if(!uid){ $err.text('<?php esc_html_e('Please select a user.','tempmail-pro'); ?>').show(); return; }
        if(!domain){ $err.text('<?php esc_html_e('Please enter a domain.','tempmail-pro'); ?>').show(); return; }
        var $btn = $(this).prop('disabled',true).text('Adding…');
        $.post(AJAX,{action:'tmpmp_admin_add_user_domain',nonce:NONCE,user_id:uid,domain:domain},function(r){
            $btn.prop('disabled',false).html('➕ <?php esc_html_e('Add Domain','tempmail-pro'); ?>');
            if(r.success){ toast('<?php esc_html_e('Domain added!','tempmail-pro'); ?>','success'); closeOverlay('tmpmp-ud-add-overlay'); setTimeout(function(){location.reload();},600); }
            else{ $err.text(r.data?.message||'Error').show(); }
        });
    });

    /* ── Delete ─────────────────────────────────────────────────────────── */
    $(document).on('click', '.tmpmp-ud-delete-btn', function(){
        var id     = $(this).data('id');
        var domain = $(this).data('domain');
        if(!confirm('<?php esc_html_e('Delete domain','tempmail-pro'); ?> "'+domain+'"?\n<?php esc_html_e('This cannot be undone.','tempmail-pro'); ?>')) return;
        var $btn = $(this).prop('disabled',true).text('…');
        $.post(AJAX,{action:'tmpmp_admin_delete_user_domain',nonce:NONCE,id:id},function(r){
            if(r.success){
                $('#tmpmp-ud-row-'+id).fadeOut(300,function(){ $(this).remove(); });
                toast('<?php esc_html_e('Domain deleted.','tempmail-pro'); ?>','success');
                refreshStats();
            } else { toast(r.data?.message||'Error','error'); $btn.prop('disabled',false).text('🗑'); }
        });
    });

    /* ── Suspend / Unsuspend ─────────────────────────────────────────────── */
    $(document).on('click', '.tmpmp-ud-suspend-btn', function(){
        var id      = $(this).data('id');
        var action  = $(this).data('action');
        var $btn    = $(this).prop('disabled',true);
        $.post(AJAX,{action:'tmpmp_admin_suspend_user_domain',nonce:NONCE,id:id,action_type:action},function(r){
            if(r.success){
                var st = r.data.status;
                updateRowStatus(id, st);
                toast(r.data.message, 'success');
                refreshStats();
            } else { toast(r.data?.message||'Error','error'); }
            $btn.prop('disabled',false);
        });
    });

    /* ── Verify (single) ─────────────────────────────────────────────────── */
    var _verifyId = null;
    $(document).on('click', '.tmpmp-ud-verify-btn', function(){
        _verifyId  = $(this).data('id');
        var domain = $(this).data('domain');
        openVerifyModal(domain, _verifyId);
    });

    function openVerifyModal(domain, id){
        $('#tmpmp-ud-verify-title').text(domain);
        $('#tmpmp-ud-verify-sub').text('<?php esc_html_e('Running DNS verification…','tempmail-pro'); ?>');
        $('#tmpmp-ud-verify-icon').css('background','linear-gradient(135deg,#e0f2fe,#bae6fd)').text('🔍');
        $('#tmpmp-ud-dns-summary').hide().removeClass('pass fail partial');
        $('#tmpmp-ud-verify-meta').text('');
        $('#tmpmp-ud-verify-rerun').hide();
        renderDnsRows(null); // loading state
        openOverlay('tmpmp-ud-verify-overlay');
        runVerify(id);
    }

    function runVerify(id){
        $.post(AJAX,{action:'tmpmp_admin_verify_user_domain',nonce:NONCE,id:id},function(r){
            if(!r.success){
                $('#tmpmp-ud-verify-sub').text(r.data?.message||'Error');
                return;
            }
            var d = r.data;
            renderDnsRows(d.checks_detail);

            var passCount = (d.checks_detail||[]).filter(function(c){return c.verified;}).length;
            var total5    = (d.checks_detail||[]).length || 5;
            var allPass   = d.all_pass;

            // Summary bar
            var sumCls = allPass ? 'pass' : (passCount>0?'partial':'fail');
            var sumMsg = allPass
                ? '✅ <?php esc_html_e('All 5 DNS records verified! Domain is fully active.','tempmail-pro'); ?>'
                : '⚠️ '+passCount+'/'+total5+' <?php esc_html_e('records verified. Set missing records at your registrar.','tempmail-pro'); ?>';
            $('#tmpmp-ud-dns-summary').addClass(sumCls).text(sumMsg).show();
            $('#tmpmp-ud-verify-icon').css('background', allPass ? 'linear-gradient(135deg,#dcfce7,#bbf7d0)' : 'linear-gradient(135deg,#fee2e2,#fecaca)').text(allPass?'✅':'❌');
            $('#tmpmp-ud-verify-sub').text(allPass ? '<?php esc_html_e('All checks passed','tempmail-pro'); ?>' : '<?php esc_html_e('Some records need attention','tempmail-pro'); ?>');
            $('#tmpmp-ud-verify-meta').text('<?php esc_html_e('Checked','tempmail-pro'); ?> '+new Date().toLocaleTimeString());
            $('#tmpmp-ud-verify-rerun').show();

            // Update table row
            updateRowPips(id, d.checks_detail);
            if(id){ updateRowStatus(id, d.status); }
            refreshStats();
        });
    }

    $('#tmpmp-ud-verify-rerun').on('click', function(){
        if(!_verifyId) return;
        $('#tmpmp-ud-dns-summary').hide().removeClass('pass fail partial');
        renderDnsRows(null);
        $(this).hide();
        runVerify(_verifyId);
    });
    $('#tmpmp-ud-verify-close, #tmpmp-ud-verify-close-btn').on('click', function(){ closeOverlay('tmpmp-ud-verify-overlay'); });

    function renderDnsRows(checks){
        var labels = ['TXT Ownership','MX Record','SPF Record','DKIM Record','DMARC Record'];
        var html   = '';
        for(var i=0;i<5;i++){
            var c    = checks ? checks[i] : null;
            var pass = c ? c.verified : null;
            var dotCls = (pass===null)?'loading':(pass?'pass':'fail');
            var pill   = (pass===null)?'WAIT':(pass?'PASS':'FAIL');
            var pillCls= (pass===null)?'loading':(pass?'pass':'fail');
            var detail = c ? (c.host ? '📍 '+escHtml(c.host)+(c.value?' → '+escHtml(c.value):'') : '') : '<?php esc_html_e('Checking…','tempmail-pro'); ?>';
            var icon   = (pass===null)?'<span class="tmpmp-ud-spin">⟳</span>':(pass?'✓':'✗');
            html += '<div class="tmpmp-ud-dns-row">'
                  + '<div class="tmpmp-ud-dns-dot '+dotCls+'">'+icon+'</div>'
                  + '<div class="tmpmp-ud-dns-info"><strong>'+escHtml(c?c.label:labels[i])+'</strong><span>'+detail+'</span></div>'
                  + '<span class="tmpmp-ud-dns-pill '+pillCls+'">'+pill+'</span>'
                  + '</div>';
        }
        $('#tmpmp-ud-dns-rows').html(html);
    }

    /* ── Bulk actions ────────────────────────────────────────────────────── */
    $('#tmpmp-ud-bulk-apply').on('click', function(){
        var action = $('#tmpmp-ud-bulk-action').val();
        if(!action){ toast('<?php esc_html_e('Please select a bulk action.','tempmail-pro'); ?>','info'); return; }
        var ids = [];
        $('.tmpmp-ud-row-check:checked').each(function(){ ids.push($(this).val()); });
        if(!ids.length){ toast('<?php esc_html_e('Please select at least one domain.','tempmail-pro'); ?>','info'); return; }
        if(action==='delete'&&!confirm('<?php esc_html_e('Delete the selected domains? This cannot be undone.','tempmail-pro'); ?>')) return;

        var $btn=$(this).prop('disabled',true).text('<?php esc_html_e('Processing…','tempmail-pro'); ?>');
        $.post(AJAX,{action:'tmpmp_admin_bulk_user_domains',nonce:NONCE,bulk_action:action,ids:ids},function(r){
            $btn.prop('disabled',false).text('<?php esc_html_e('Apply','tempmail-pro'); ?>');
            if(r.success){
                toast(r.data.message,'success');
                setTimeout(function(){ location.reload(); }, 600);
            } else { toast(r.data?.message||'Error','error'); }
        });
    });

    /* ── Export CSV ──────────────────────────────────────────────────────── */
    $('#tmpmp-ud-export-csv').on('click', function(e){
        e.preventDefault();
        var rows = [['ID','User','Email','Domain','Status','TXT','MX','SPF','DKIM','DMARC','Last Checked','Created']];
        $('#tmpmp-ud-table tbody tr[id]').each(function(){
            var $r   = $(this);
            var id   = $r.data('id');
            var dom  = $r.data('domain');
            var st   = $r.data('status');
            var user = $r.find('.tmpmp-ud-user-name').text().trim();
            var mail = $r.find('.tmpmp-ud-user-email').text().trim();
            var pips = [];
            $r.find('.tmpmp-ud-pip').each(function(){ pips.push($(this).hasClass('pass')?'PASS':'FAIL'); });
            var lc = $r.find('[id^=tmpmp-ud-checked-]').text().trim().replace(/\s+/g,' ');
            var cr = $r.find('.tmpmp-ud-time').last().text().trim().replace(/\s+/g,' ');
            rows.push([id,user,mail,dom,st].concat(pips).concat([lc,cr]));
        });
        var csv = rows.map(function(r){ return r.map(function(c){ return '"'+String(c).replace(/"/g,'""')+'"'; }).join(','); }).join('\n');
        var blob = new Blob([csv], {type:'text/csv'});
        var a    = document.createElement('a');
        a.href   = URL.createObjectURL(blob);
        a.download = 'user-domains-'+new Date().toISOString().slice(0,10)+'.csv';
        a.click();
        toast('<?php esc_html_e('CSV exported!','tempmail-pro'); ?>','success');
    });

    /* ── Helpers ─────────────────────────────────────────────────────────── */
    function updateRowStatus(id, st){
        var $row   = $('#tmpmp-ud-row-'+id);
        var labels = {verified:'✅ Verified',pending:'⏳ Pending',suspended:'🚫 Suspended'};
        $row.attr('data-status', st);
        $('#tmpmp-ud-badge-'+id)
            .attr('class','tmpmp-ud-badge tmpmp-ud-badge--'+st)
            .text(labels[st]||st);
        // Swap suspend button
        var $actions = $row.find('.tmpmp-ud-actions');
        var $old     = $actions.find('.tmpmp-ud-suspend-btn');
        var id_      = $old.data('id');
        $old.remove();
        var $new;
        if(st==='suspended'){
            $new = $('<button class="tmpmp-ud-icon-btn tmpmp-ud-icon-btn--green tmpmp-ud-suspend-btn">✅ <?php esc_html_e('Unsuspend','tempmail-pro'); ?></button>');
            $new.data({id:id_,action:'unsuspend'});
        } else {
            $new = $('<button class="tmpmp-ud-icon-btn tmpmp-ud-icon-btn--warn tmpmp-ud-suspend-btn">🚫 <?php esc_html_e('Suspend','tempmail-pro'); ?></button>');
            $new.data({id:id_,action:'suspend'});
        }
        $actions.find('.tmpmp-ud-verify-btn').after($new);
    }

    function updateRowPips(id, checks){
        if(!checks) return;
        var html='';
        var tipMap=['TXT','MX','SPF','DKIM','DMARC'];
        for(var i=0;i<checks.length;i++){
            var ok=checks[i].verified;
            html+='<div class="tmpmp-ud-pip '+(ok?'pass':'fail')+'" data-tip="'+escHtml(tipMap[i]+': '+(ok?'Pass':'Fail'))+'">'+(ok?'✓':'✗')+'</div>';
        }
        $('#tmpmp-ud-pips-'+id).html(html);
        var now=new Date();
        var mo=['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'][now.getMonth()];
        $('#tmpmp-ud-checked-'+id).html('<strong>'+mo+' '+now.getDate()+', '+now.getFullYear()+'</strong>Just now');
    }

    function refreshStats(){
        // Re-tally from visible rows
        var counts={total:0,verified:0,pending:0,suspended:0};
        $('#tmpmp-ud-table tbody tr[id]').each(function(){
            var st=$(this).data('status')||'pending';
            counts.total++;
            if(counts[st]!==undefined) counts[st]++;
        });
        $.each(counts,function(k,v){ $('#tmpmp-ud-stat-'+k).text(v); });
    }

    function escHtml(s){ return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }

})(jQuery);
</script>

</div><!-- /.wrap -->
