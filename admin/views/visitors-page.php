<?php defined('ABSPATH') || exit; ?>
<?php
// ── Handle block/unblock form actions ─────────────────────────────────────
if ( isset($_POST['tmpmp_block_action']) && check_admin_referer('tmpmp_block_action') ) {
    $action = sanitize_key($_POST['tmpmp_block_action']);
    if ( $action === 'save_ips' ) {
        $ips = array_filter( array_map( 'trim', preg_split('/[\r\n,]+/', sanitize_textarea_field($_POST['blocked_ips']??'')) ) );
        TempMail_Visitors::save_blocked_ips($ips);
        echo '<div class="notice notice-success is-dismissible"><p>'.esc_html__('IP block list saved.','tempmail-pro').'</p></div>';
    }
    if ( $action === 'save_uas' ) {
        $uas = array_filter( array_map( 'trim', preg_split('/[\r\n,]+/', sanitize_textarea_field($_POST['blocked_uas']??'')) ) );
        TempMail_Visitors::save_blocked_uas($uas);
        echo '<div class="notice notice-success is-dismissible"><p>'.esc_html__('User agent block list saved.','tempmail-pro').'</p></div>';
    }
    if ( $action === 'block_ip_single'  && !empty($_POST['block_ip'])  ) { TempMail_Visitors::block_ip(sanitize_text_field($_POST['block_ip']));   echo '<div class="notice notice-success is-dismissible"><p>'.esc_html__('IP blocked.','tempmail-pro').'</p></div>'; }
    if ( $action === 'unblock_ip'       && !empty($_POST['unblock_ip']) ) { TempMail_Visitors::unblock_ip(sanitize_text_field($_POST['unblock_ip'])); echo '<div class="notice notice-success is-dismissible"><p>'.esc_html__('IP unblocked.','tempmail-pro').'</p></div>'; }
    if ( $action === 'block_ua_single'  && !empty($_POST['block_ua'])  ) { TempMail_Visitors::block_ua(sanitize_text_field($_POST['block_ua']));   echo '<div class="notice notice-success is-dismissible"><p>'.esc_html__('User agent blocked.','tempmail-pro').'</p></div>'; }
    if ( $action === 'unblock_ua'       && !empty($_POST['unblock_ua']) ) { TempMail_Visitors::unblock_ua(sanitize_text_field($_POST['unblock_ua'])); echo '<div class="notice notice-success is-dismissible"><p>'.esc_html__('User agent unblocked.','tempmail-pro').'</p></div>'; }
}

TempMail_Visitors::maybe_create_table();

$subtab  = sanitize_key($_GET['subtab'] ?? 'visitors');
$paged   = max(1, intval($_GET['paged'] ?? 1));
$limit   = 50;
$offset  = ($paged - 1) * $limit;
$search  = sanitize_text_field($_GET['s'] ?? '');
$filter  = sanitize_text_field($_GET['filter'] ?? 'all');

$args        = ['limit'=>$limit,'offset'=>$offset,'search'=>$search,'filter'=>$filter];
$rows        = TempMail_Visitors::get_visitors($args);
$total       = TempMail_Visitors::get_total_count($args);
$stats       = TempMail_Visitors::get_stats();
$pages       = ceil($total / $limit);
$chart       = TempMail_Visitors::get_chart_data(14);
$top_pg      = TempMail_Visitors::get_top_pages(8);
$browsers    = TempMail_Visitors::get_top_browsers();
$blocked_ips = TempMail_Visitors::get_blocked_ips();
$blocked_uas = TempMail_Visitors::get_blocked_uas();

$b_colors = ['Chrome'=>'#4285f4','Firefox'=>'#ff6d00','Safari'=>'#1c9be1','Edge'=>'#0078d4','Opera'=>'#ff1b2d','IE'=>'#1ebbee','Other'=>'#94a3b8'];
$base_url = admin_url('admin.php?page=tmpmp-visitors');
?>
<style>
/* ── Base — mobile-safe foundations ── */
.tmpmp-vis-page{
    font-family:'Inter',system-ui,sans-serif;
    color:#0f172a;
    max-width:100%;
    overflow-x:hidden; /* prevent any child from causing horizontal scroll */
    box-sizing:border-box;
}
*,.tmpmp-vis-page *{box-sizing:border-box;}
.tmpmp-vis-header{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:20px;}
.tmpmp-vis-title{font-size:22px;font-weight:800;color:#0f172a;display:flex;align-items:center;gap:10px;margin:0;}
.tmpmp-vis-title span{font-size:13px;font-weight:500;color:#64748b;}

/* ── Sub-tabs ── */
.tmpmp-vis-subtabs{display:flex;gap:4px;margin-bottom:22px;border-bottom:2px solid #f1f5f9;flex-wrap:wrap;}
.tmpmp-vis-subtabs a{display:inline-flex;align-items:center;gap:6px;padding:8px 16px;border-radius:8px 8px 0 0;font-size:13px;font-weight:700;text-decoration:none;color:#64748b;border:1.5px solid transparent;border-bottom:none;transition:all .18s;margin-bottom:-2px;}
.tmpmp-vis-subtabs a.active{background:#fff;color:#6366f1;border-color:#e2e8f0;border-bottom:2px solid #fff;}
.tmpmp-vis-subtabs a:not(.active):hover{color:#6366f1;background:#f8fafc;}
.tmpmp-vis-subtabs .badge{background:#ef4444;color:#fff;border-radius:20px;font-size:10px;font-weight:800;padding:1px 6px;}
.tmpmp-vis-subtabs .badge.blue{background:#6366f1;}

/* ── Stats grid ── */
.tmpmp-vis-stats{display:grid;grid-template-columns:repeat(auto-fill,minmax(110px,1fr));gap:12px;margin-bottom:22px;}
.tmpmp-vis-stat{background:#fff;border:1.5px solid #f1f5f9;border-radius:12px;padding:16px;text-align:center;box-shadow:0 1px 3px rgba(0,0,0,.04);min-width:0;}
.tmpmp-vis-stat-val{font-size:24px;font-weight:800;line-height:1;}
.tmpmp-vis-stat-label{font-size:10.5px;color:#64748b;margin-top:5px;font-weight:600;text-transform:uppercase;letter-spacing:.5px;}

/* ── Charts ── */
.tmpmp-vis-charts{display:grid;grid-template-columns:2fr 1fr 1fr;gap:14px;margin-bottom:20px;}
.tmpmp-vis-chart-card{
    background:#fff;border:1.5px solid #f1f5f9;border-radius:12px;
    padding:18px;box-shadow:0 1px 3px rgba(0,0,0,.04);
    min-width:0;     /* critical: prevents grid item overflow */
    overflow:hidden; /* clips canvas if it renders too wide */
    width:100%;
}
.tmpmp-vis-chart-card h3{margin:0 0 14px;font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:.5px;color:#64748b;}
.tmpmp-vis-chart-card canvas{max-width:100%;display:block;} /* prevent canvas overflow */
.tmpmp-vis-top-pages{list-style:none;margin:0;padding:0;}
.tmpmp-vis-top-pages li{display:flex;align-items:center;justify-content:space-between;padding:6px 0;border-bottom:1px solid #f8fafc;font-size:12px;gap:8px;}
.tmpmp-vis-top-pages li:last-child{border-bottom:none;}
.tmpmp-vis-top-pages .url{overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:#374151;flex:1;min-width:0;}
.tmpmp-vis-top-pages .cnt{font-weight:800;color:#6366f1;white-space:nowrap;}
.tmpmp-vis-donut-list{list-style:none;margin:0;padding:0;}
.tmpmp-vis-donut-list li{display:flex;align-items:center;gap:8px;padding:4px 0;font-size:12px;}
.tmpmp-vis-donut-dot{width:9px;height:9px;border-radius:50%;flex-shrink:0;}
.tmpmp-vis-donut-label{flex:1;color:#374151;min-width:0;}
.tmpmp-vis-donut-val{font-weight:700;}

/* ── Toolbar ── */
.tmpmp-vis-toolbar{display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:14px;}
.tmpmp-vis-search{padding:8px 13px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:13px;min-width:200px;outline:none;max-width:100%;}
.tmpmp-vis-search:focus{border-color:#6366f1;box-shadow:0 0 0 3px rgba(99,102,241,.1);}
.tmpmp-vis-filter{display:flex;gap:5px;flex-wrap:wrap;}
.tmpmp-vis-filter a{padding:7px 13px;border-radius:7px;font-size:12px;font-weight:700;text-decoration:none;color:#64748b;border:1.5px solid #e2e8f0;background:#fff;transition:all .15s;}
.tmpmp-vis-filter a.active,.tmpmp-vis-filter a:hover{border-color:#6366f1;color:#6366f1;background:#f5f3ff;}

/* ── Table wrapper ── */
.tmpmp-vis-table-wrap{background:#fff;border:1.5px solid #f1f5f9;border-radius:12px;overflow:hidden;box-shadow:0 1px 4px rgba(0,0,0,.04);margin-bottom:20px;max-width:100%;}
.tmpmp-vis-table-scroll{overflow-x:auto;-webkit-overflow-scrolling:touch;max-width:100%;}
.tmpmp-vis-table{width:100%;min-width:900px;border-collapse:collapse;font-size:12.5px;}
.tmpmp-vis-table th{background:#f8fafc;padding:10px 12px;text-align:left;font-size:10.5px;font-weight:800;text-transform:uppercase;letter-spacing:.6px;color:#64748b;border-bottom:1.5px solid #f1f5f9;white-space:nowrap;}
.tmpmp-vis-table td{padding:9px 12px;border-bottom:1px solid #f8fafc;vertical-align:middle;}
.tmpmp-vis-table tr:last-child td{border-bottom:none;}
.tmpmp-vis-table tr:hover td{background:#fafbff;}

/* ── Cell types ── */
.tmpmp-vis-ip{font-family:'SFMono-Regular',Consolas,monospace;font-size:12px;color:#6366f1;font-weight:700;white-space:nowrap;}
/* IP button */
.tmpmp-ip-btn{background:none;border:none;padding:0;cursor:pointer;font-family:inherit;font-size:inherit;font-weight:700;color:#6366f1;font-family:'SFMono-Regular',Consolas,monospace;text-decoration:underline dotted;transition:color .15s;}
.tmpmp-ip-btn:hover{color:#4f46e5;}
/* IP Info Modal */
#tmpmp-ip-modal{display:none;position:fixed;inset:0;z-index:999998;align-items:center;justify-content:center;}
#tmpmp-ip-modal.open{display:flex;}
#tmpmp-ip-backdrop{position:absolute;inset:0;background:rgba(10,18,40,.60);backdrop-filter:blur(3px);}
#tmpmp-ip-box{position:relative;background:linear-gradient(145deg,#1a2f52,#152240);border:1px solid rgba(255,255,255,.12);border-radius:14px;box-shadow:0 32px 80px rgba(0,0,0,.45),0 4px 20px rgba(0,0,0,.3),inset 0 1px 0 rgba(255,255,255,.08);padding:28px 32px;width:100%;max-width:380px;margin:16px;z-index:1;color:#e2e8f0;animation:tmpmpIpIn .22s cubic-bezier(.34,1.4,.64,1);}
@keyframes tmpmpIpIn{from{opacity:0;transform:scale(.90) translateY(16px)}to{opacity:1;transform:none}}
#tmpmp-ip-box .ip-modal-head{display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;}
#tmpmp-ip-box .ip-modal-addr{font-family:'SFMono-Regular',Consolas,monospace;font-size:15px;font-weight:800;color:#7dd3fc;letter-spacing:.5px;}
#tmpmp-ip-close-btn{background:rgba(255,255,255,.1);border:none;color:#94a3b8;border-radius:6px;padding:4px 9px;cursor:pointer;font-size:14px;font-weight:700;transition:all .15s;}
#tmpmp-ip-close-btn:hover{background:rgba(255,255,255,.2);color:#fff;}
.tmpmp-ip-rows{display:grid;grid-template-columns:auto 1fr;row-gap:11px;column-gap:18px;align-items:baseline;}
.tmpmp-ip-label{font-size:12.5px;font-weight:700;color:#94a3b8;white-space:nowrap;}
.tmpmp-ip-value{font-size:13px;font-weight:700;color:#38bdf8;word-break:break-word;}
.tmpmp-ip-value.empty{color:#475569;font-weight:400;font-style:italic;}
#tmpmp-ip-spinner{text-align:center;padding:24px 0;font-size:13px;color:#94a3b8;}
#tmpmp-ip-error{color:#fca5a5;font-size:13px;text-align:center;padding:16px 0;}
img.tmpmp-flag{width:20px;height:14px;object-fit:cover;border-radius:2px;vertical-align:middle;margin-right:5px;box-shadow:0 0 0 1px rgba(0,0,0,.08);}
.tmpmp-vis-page-url{display:block;max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:12px;color:#374151;min-width:0;}
.tmpmp-vis-ua-cell{display:block;max-width:150px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:11px;color:#64748b;font-family:'SFMono-Regular',Consolas,monospace;min-width:0;}
/* ua-wrap: must always allow shrinking so it never overflows the td */
.tmpmp-vis-ua-wrap{display:flex;align-items:center;gap:5px;min-width:0;overflow:hidden;}
.tmpmp-ua-btn{display:inline-flex;align-items:center;gap:3px;padding:2px 7px;background:#f1f5f9;border:1.5px solid #e2e8f0;border-radius:5px;font-size:10.5px;font-weight:700;color:#475569;cursor:pointer;transition:all .15s;white-space:nowrap;flex-shrink:0;}
.tmpmp-ua-btn:hover{background:#6366f1;border-color:#6366f1;color:#fff;}
/* UA Modal */
#tmpmp-ua-modal{display:none;position:fixed;inset:0;z-index:999999;align-items:center;justify-content:center;}
#tmpmp-ua-modal.open{display:flex;}
#tmpmp-ua-backdrop{position:absolute;inset:0;background:rgba(15,23,42,.55);backdrop-filter:blur(2px);}
#tmpmp-ua-box{position:relative;background:#fff;border-radius:16px;box-shadow:0 24px 64px rgba(0,0,0,.22),0 4px 16px rgba(0,0,0,.1);padding:28px;width:100%;max-width:600px;margin:16px;z-index:1;animation:tmpmpUaIn .2s cubic-bezier(.34,1.56,.64,1);}
@keyframes tmpmpUaIn{from{opacity:0;transform:scale(.92) translateY(12px)}to{opacity:1;transform:none}}
#tmpmp-ua-box h4{margin:0 0 6px;font-size:16px;font-weight:800;color:#0f172a;display:flex;align-items:center;gap:8px;}
#tmpmp-ua-box .tmpmp-ua-meta{display:flex;gap:6px;flex-wrap:wrap;margin-bottom:14px;}
#tmpmp-ua-box .tmpmp-ua-meta span{padding:3px 9px;border-radius:20px;font-size:11.5px;font-weight:700;background:#f1f5f9;color:#475569;}
#tmpmp-ua-string{font-family:'SFMono-Regular',Consolas,monospace;font-size:12.5px;color:#1e293b;background:#f8fafc;border:1.5px solid #e2e8f0;border-radius:10px;padding:14px 16px;line-height:1.7;word-break:break-all;white-space:pre-wrap;max-height:180px;overflow-y:auto;margin-bottom:16px;}
.tmpmp-ua-footer{display:flex;align-items:center;justify-content:space-between;gap:10px;}
#tmpmp-ua-copy{padding:8px 18px;background:linear-gradient(135deg,#6366f1,#8b5cf6);color:#fff;border:none;border-radius:8px;font-size:13px;font-weight:700;cursor:pointer;transition:all .2s;display:flex;align-items:center;gap:6px;}
#tmpmp-ua-copy:hover{opacity:.88;transform:translateY(-1px);}
#tmpmp-ua-copy.copied{background:linear-gradient(135deg,#10b981,#059669);}
#tmpmp-ua-close{padding:8px 16px;background:#f1f5f9;border:none;border-radius:8px;font-size:13px;font-weight:700;cursor:pointer;color:#64748b;transition:all .15s;}
#tmpmp-ua-close:hover{background:#e2e8f0;}
.tmpmp-vis-ref-cell{display:block;max-width:130px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:11.5px;color:#0ea5e9;text-decoration:none;min-width:0;}
.tmpmp-vis-ref-cell:hover{text-decoration:underline;color:#0284c7;}
.tmpmp-vis-badge{display:inline-block;padding:2px 7px;border-radius:20px;font-size:10.5px;font-weight:700;white-space:nowrap;}
.tmpmp-vis-badge--bot{background:#fee2e2;color:#dc2626;}
.tmpmp-vis-badge--human{background:#dcfce7;color:#16a34a;}
.tmpmp-vis-badge--blocked{background:#fef3c7;color:#b45309;}

/* ── Action buttons ── */
.tmpmp-qb{display:inline-flex;align-items:center;gap:3px;padding:3px 8px;border-radius:5px;font-size:10.5px;font-weight:700;border:none;cursor:pointer;transition:all .15s;white-space:nowrap;}
.tmpmp-qb--block{background:#fee2e2;color:#dc2626;}
.tmpmp-qb--block:hover{background:#dc2626;color:#fff;}
.tmpmp-qb--unblock{background:#dcfce7;color:#16a34a;}
.tmpmp-qb--unblock:hover{background:#16a34a;color:#fff;}
.tmpmp-qb--ua{background:#ede9fe;color:#7c3aed;}
.tmpmp-qb--ua:hover{background:#7c3aed;color:#fff;}
.tmpmp-vis-actions{display:flex;gap:4px;flex-wrap:nowrap;}

/* ── Pagination ── */
.tmpmp-vis-pagination{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;margin-top:12px;}
.tmpmp-vis-pagination a,.tmpmp-vis-pagination span{padding:6px 12px;border:1.5px solid #e2e8f0;border-radius:7px;font-size:12.5px;font-weight:600;text-decoration:none;color:#374151;}
.tmpmp-vis-pagination a:hover{border-color:#6366f1;color:#6366f1;}
.tmpmp-vis-pagination .current{background:#6366f1;color:#fff;border-color:#6366f1;}

/* ── Block panels ── */
.tmpmp-block-two-col{display:grid;grid-template-columns:1fr 1fr;gap:20px;align-items:flex-start;}
.tmpmp-block-card{background:#fff;border:1.5px solid #f1f5f9;border-radius:14px;padding:22px;box-shadow:0 1px 4px rgba(0,0,0,.04);}
.tmpmp-block-card h3{margin:0 0 5px;font-size:14px;font-weight:800;color:#0f172a;display:flex;align-items:center;gap:8px;}
.tmpmp-block-card p{margin:0 0 14px;font-size:12px;color:#64748b;}
.tmpmp-block-textarea{width:100%;min-height:130px;padding:11px 13px;border:1.5px solid #e2e8f0;border-radius:9px;font-family:monospace;font-size:12px;resize:vertical;outline:none;box-sizing:border-box;color:#1e293b;line-height:1.7;}
.tmpmp-block-textarea:focus{border-color:#6366f1;box-shadow:0 0 0 3px rgba(99,102,241,.1);}
.tmpmp-block-hint{font-size:11px;color:#94a3b8;margin:5px 0 12px;}
.tmpmp-block-save-btn{padding:8px 18px;background:linear-gradient(135deg,#6366f1,#8b5cf6);color:#fff;border:none;border-radius:8px;font-size:13px;font-weight:700;cursor:pointer;transition:all .2s;}
.tmpmp-block-save-btn:hover{opacity:.9;transform:translateY(-1px);}
.tmpmp-blocklist-table{width:100%;border-collapse:collapse;margin-top:14px;}
.tmpmp-blocklist-table th{background:#f8fafc;padding:8px 11px;text-align:left;font-size:10.5px;font-weight:800;text-transform:uppercase;letter-spacing:.5px;color:#64748b;border-bottom:1.5px solid #f1f5f9;}
.tmpmp-blocklist-table td{padding:8px 11px;border-bottom:1px solid #f8fafc;font-size:12.5px;vertical-align:middle;}
.tmpmp-blocklist-table tr:last-child td{border-bottom:none;}
.tmpmp-blocklist-table tr:hover td{background:#fafbff;}
.tmpmp-blocklist-empty{text-align:center;padding:26px;color:#94a3b8;font-size:13px;}
.tmpmp-add-row{display:flex;gap:7px;margin-top:12px;}
.tmpmp-add-row input[type=text]{flex:1;padding:7px 11px;border:1.5px solid #e2e8f0;border-radius:7px;font-size:13px;outline:none;font-family:monospace;}
.tmpmp-add-row input[type=text]:focus{border-color:#6366f1;}
.tmpmp-add-row button{padding:7px 14px;background:#6366f1;color:#fff;border:none;border-radius:7px;font-weight:700;cursor:pointer;font-size:13px;}
.tmpmp-add-row button:hover{background:#4f46e5;}
.tmpmp-presets{display:flex;flex-wrap:wrap;gap:5px;margin-top:12px;}

/* ═══════════════════════════════════════════════════════════════
   RESPONSIVE  (desktop → tablet → mobile)
   ═══════════════════════════════════════════════════════════════ */

/* Tablet landscape ≤1100px */
@media (max-width:1100px){
    .tmpmp-vis-charts{grid-template-columns:1fr 1fr;}
}

/* Tablet portrait ≤900px */
@media (max-width:900px){
    .tmpmp-vis-charts{grid-template-columns:1fr;}
    .tmpmp-block-two-col{grid-template-columns:1fr;}
    .tmpmp-vis-stats{grid-template-columns:repeat(auto-fill,minmax(100px,1fr));}
}

/* ── Mobile ≤600px ── */
@media (max-width:600px){

    /* Page container — no horizontal bleed */
    .tmpmp-vis-page{overflow-x:hidden;}

    /* Header */
    .tmpmp-vis-title{font-size:16px;}
    .tmpmp-vis-title span{font-size:11px;}

    /* Sub-tabs: wrap tightly */
    .tmpmp-vis-subtabs{gap:3px;margin-bottom:12px;overflow-x:auto;flex-wrap:nowrap;padding-bottom:2px;border-bottom:2px solid #f1f5f9;}
    .tmpmp-vis-subtabs a{padding:7px 12px;font-size:12px;white-space:nowrap;}

    /* Stats: 2 per row */
    .tmpmp-vis-stats{grid-template-columns:repeat(3,1fr);gap:8px;margin-bottom:12px;}
    .tmpmp-vis-stat{padding:10px 6px;}
    .tmpmp-vis-stat-val{font-size:17px;}
    .tmpmp-vis-stat-label{font-size:9px;letter-spacing:0;}

    /* Charts: single column, constrained */
    .tmpmp-vis-charts{grid-template-columns:1fr;gap:10px;margin-bottom:12px;}
    .tmpmp-vis-chart-card{padding:14px;overflow:hidden;}
    .tmpmp-vis-chart-card canvas{max-width:100% !important;height:auto !important;}

    /* Toolbar: stack */
    .tmpmp-vis-toolbar{flex-direction:column;align-items:stretch;gap:6px;}
    .tmpmp-vis-toolbar form{display:flex;flex-direction:column;gap:6px;width:100%;}
    .tmpmp-vis-search{width:100%;min-width:unset;}
    .tmpmp-vis-filter{gap:4px;}
    .tmpmp-vis-filter a{padding:6px 10px;font-size:11.5px;}

    /* Table: switch to card layout — disable horizontal scroll */
    .tmpmp-vis-table-wrap{border-radius:8px;overflow:hidden;}
    .tmpmp-vis-table-scroll{overflow:visible;} /* cards don't need h-scroll */
    .tmpmp-vis-table{min-width:unset !important;width:100%;border:none;}

    /* Hide column headers */
    .tmpmp-vis-table thead{display:none;}

    /* Each row = a card */
    .tmpmp-vis-table tbody tr{
        display:block;
        background:#fff;
        padding:12px 14px;
        border-bottom:2px solid #f1f5f9;
    }
    .tmpmp-vis-table tbody tr:last-child{border-bottom:none;}
    .tmpmp-vis-table tbody tr:hover{background:#fafbff;}

    /* Each cell = label + value row */
    .tmpmp-vis-table td{
        display:flex;
        align-items:flex-start;
        gap:8px;
        padding:4px 0;
        border:none;
        font-size:12.5px;
        line-height:1.5;
        min-width:0;
        word-break:break-word;
    }
    .tmpmp-vis-table td::before{
        content:attr(data-label);
        display:inline-block;
        min-width:72px;
        flex-shrink:0;
        font-size:9.5px;
        font-weight:800;
        text-transform:uppercase;
        letter-spacing:.4px;
        color:#94a3b8;
        padding-top:2px;
    }
    .tmpmp-vis-table td.col-num{display:none;}
    .tmpmp-vis-table td.col-actions .tmpmp-vis-actions{flex-wrap:wrap;gap:4px;}

    /* Card mode cell layout — each td is: [::before label 72px] | [content area]
       Key rules:
         - ua-wrap  : flex container, flex:1 min-width:0 — fills remaining td width
                      NO overflow:hidden here — that would clip the View button!
         - ua-cell  : flex:1 min-width:0 — shrinks and lets white-space:nowrap+ellipsis truncate
         - ua-btn   : flex-shrink:0 — ALWAYS visible, never squeezed out
         - ref-cell / page-url : flex:1 min-width:0 — fill & truncate
    */
    .tmpmp-vis-ua-wrap{
        flex:1;
        min-width:0;
        display:flex;
        align-items:center;
        gap:5px;
        /* overflow must NOT be hidden here — button would be clipped */
    }
    .tmpmp-vis-ua-cell{
        flex:1;
        min-width:0;
        /* white-space:nowrap + overflow:hidden + text-overflow:ellipsis
           already set in the base rule — they truncate correctly here */
    }
    .tmpmp-ua-btn{
        flex-shrink:0; /* View button is always visible */
    }
    .tmpmp-vis-ref-cell,
    .tmpmp-vis-page-url{
        flex:1;
        min-width:0;
        max-width:100%;
        display:block;
    }
    /* Generic inline spans inside td don't blow out the card */
    .tmpmp-vis-table td>span{max-width:100%;word-break:break-word;}

    /* Pagination */
    .tmpmp-vis-pagination{flex-direction:column;align-items:center;gap:6px;}
    .tmpmp-vis-pagination>div{display:flex;gap:4px;flex-wrap:wrap;justify-content:center;}

    /* Block panels */
    .tmpmp-block-two-col{grid-template-columns:1fr;gap:12px;}
    .tmpmp-block-card{padding:14px;}
    .tmpmp-block-textarea{min-height:100px;}
    .tmpmp-add-row{flex-direction:column;gap:6px;}
    .tmpmp-add-row input[type=text]{width:100%;}
    .tmpmp-add-row button{width:100%;}

    /* Modals */
    #tmpmp-ua-box,#tmpmp-ip-box{padding:18px 14px;margin:8px;max-width:calc(100vw - 16px);}
    .tmpmp-ua-footer{flex-direction:column;gap:8px;}
    #tmpmp-ua-copy,#tmpmp-ua-close{width:100%;justify-content:center;text-align:center;}
    #tmpmp-ua-string{font-size:11px;max-height:140px;}
    #tmpmp-ua-box h4{font-size:14px;}
    .tmpmp-ip-rows{column-gap:12px;}
}

/* ── Very small ≤380px ── */
@media (max-width:380px){
    .tmpmp-vis-stats{grid-template-columns:repeat(2,1fr);gap:6px;}
    .tmpmp-vis-stat-val{font-size:15px;}
    .tmpmp-vis-subtabs a{padding:6px 9px;font-size:11px;}
    .tmpmp-vis-table td::before{min-width:60px;}
}
</style>

<div class="wrap tmpmp-vis-page">

<div class="tmpmp-vis-header">
    <h1 class="tmpmp-vis-title">👁️ <?php esc_html_e('Visitors','tempmail-pro'); ?> <span><?php printf(esc_html__('%s records','tempmail-pro'),number_format($stats['total'])); ?></span></h1>
</div>

<!-- Sub-tabs -->
<div class="tmpmp-vis-subtabs">
    <a href="<?php echo esc_url(add_query_arg('subtab','visitors',$base_url)); ?>" class="<?php echo $subtab==='visitors'?'active':''; ?>">📊 <?php esc_html_e('Visitor Log','tempmail-pro'); ?></a>
    <a href="<?php echo esc_url(add_query_arg('subtab','blocked-ips',$base_url)); ?>" class="<?php echo $subtab==='blocked-ips'?'active':''; ?>">
        🚫 <?php esc_html_e('Blocked IPs','tempmail-pro'); ?>
        <?php if($stats['blocked_ips']>0): ?><span class="badge"><?php echo $stats['blocked_ips']; ?></span><?php endif; ?>
    </a>
    <a href="<?php echo esc_url(add_query_arg('subtab','blocked-uas',$base_url)); ?>" class="<?php echo $subtab==='blocked-uas'?'active':''; ?>">
        🤖 <?php esc_html_e('Blocked UAs','tempmail-pro'); ?>
        <?php if($stats['blocked_uas']>0): ?><span class="badge blue"><?php echo $stats['blocked_uas']; ?></span><?php endif; ?>
    </a>
</div>

<?php if($subtab==='visitors'): ?>

<!-- Stats -->
<div class="tmpmp-vis-stats">
    <div class="tmpmp-vis-stat"><div class="tmpmp-vis-stat-val"><?php echo number_format($stats['total']); ?></div><div class="tmpmp-vis-stat-label"><?php esc_html_e('Total','tempmail-pro'); ?></div></div>
    <div class="tmpmp-vis-stat"><div class="tmpmp-vis-stat-val" style="color:#6366f1;"><?php echo number_format($stats['unique']); ?></div><div class="tmpmp-vis-stat-label"><?php esc_html_e('Unique IPs','tempmail-pro'); ?></div></div>
    <div class="tmpmp-vis-stat"><div class="tmpmp-vis-stat-val" style="color:#0ea5e9;"><?php echo number_format($stats['today']); ?></div><div class="tmpmp-vis-stat-label"><?php esc_html_e('Today','tempmail-pro'); ?></div></div>
    <div class="tmpmp-vis-stat"><div class="tmpmp-vis-stat-val" style="color:#10b981;"><?php echo number_format($stats['humans']); ?></div><div class="tmpmp-vis-stat-label"><?php esc_html_e('Humans','tempmail-pro'); ?></div></div>
    <div class="tmpmp-vis-stat"><div class="tmpmp-vis-stat-val" style="color:#f59e0b;"><?php echo number_format($stats['bots']); ?></div><div class="tmpmp-vis-stat-label"><?php esc_html_e('Bots','tempmail-pro'); ?></div></div>
    <div class="tmpmp-vis-stat"><div class="tmpmp-vis-stat-val" style="color:#ef4444;"><?php echo number_format($stats['blocked_ips']); ?></div><div class="tmpmp-vis-stat-label"><?php esc_html_e('Blocked IPs','tempmail-pro'); ?></div></div>
    <div class="tmpmp-vis-stat"><div class="tmpmp-vis-stat-val" style="color:#7c3aed;"><?php echo number_format($stats['blocked_uas']); ?></div><div class="tmpmp-vis-stat-label"><?php esc_html_e('Blocked UAs','tempmail-pro'); ?></div></div>
</div>

<!-- Charts -->
<div class="tmpmp-vis-charts">
    <div class="tmpmp-vis-chart-card">
        <h3><?php esc_html_e('Visits — Last 14 Days','tempmail-pro'); ?></h3>
        <canvas id="tmpmp-vis-line" height="130"></canvas>
    </div>
    <div class="tmpmp-vis-chart-card">
        <h3><?php esc_html_e('Top Pages','tempmail-pro'); ?></h3>
        <ul class="tmpmp-vis-top-pages">
            <?php foreach($top_pg as $pg): ?>
            <li>
                <span class="url" title="<?php echo esc_attr($pg['page_url']); ?>"><?php echo esc_html(parse_url($pg['page_url'],PHP_URL_PATH)?:'/'); ?></span>
                <span class="cnt"><?php echo number_format($pg['views']); ?></span>
            </li>
            <?php endforeach; if(empty($top_pg)): ?><li style="color:#94a3b8;"><?php esc_html_e('No data yet','tempmail-pro'); ?></li><?php endif; ?>
        </ul>
    </div>
    <div class="tmpmp-vis-chart-card">
        <h3><?php esc_html_e('Browsers','tempmail-pro'); ?></h3>
        <canvas id="tmpmp-vis-donut" height="100" style="margin-bottom:12px;"></canvas>
        <ul class="tmpmp-vis-donut-list">
            <?php foreach($browsers as $b): $col=$b_colors[$b['browser']]??'#94a3b8'; ?>
            <li>
                <span class="tmpmp-vis-donut-dot" style="background:<?php echo esc_attr($col); ?>;"></span>
                <span class="tmpmp-vis-donut-label"><?php echo esc_html($b['browser']); ?></span>
                <span class="tmpmp-vis-donut-val"><?php echo number_format($b['cnt']); ?></span>
            </li>
            <?php endforeach; if(empty($browsers)): ?><li style="color:#94a3b8;"><?php esc_html_e('No data yet','tempmail-pro'); ?></li><?php endif; ?>
        </ul>
    </div>
</div>

<!-- Toolbar -->
<div class="tmpmp-vis-toolbar">
    <form method="get" style="display:flex;gap:7px;align-items:center;flex-wrap:wrap;">
        <input type="hidden" name="page" value="tmpmp-visitors">
        <input type="hidden" name="subtab" value="visitors">
        <?php if($filter&&$filter!=='all'): ?><input type="hidden" name="filter" value="<?php echo esc_attr($filter); ?>"><?php endif; ?>
        <input type="search" name="s" class="tmpmp-vis-search" placeholder="<?php esc_attr_e('Search IP, page, browser, UA, referrer…','tempmail-pro'); ?>" value="<?php echo esc_attr($search); ?>">
        <button type="submit" style="padding:8px 15px;background:#6366f1;color:#fff;border:none;border-radius:8px;font-weight:700;cursor:pointer;font-size:13px;"><?php esc_html_e('Search','tempmail-pro'); ?></button>
        <?php if($search||$filter!=='all'): ?>
        <a href="<?php echo esc_url(add_query_arg('subtab','visitors',$base_url)); ?>" style="padding:8px 13px;background:#f1f5f9;color:#374151;border-radius:8px;font-size:13px;font-weight:600;text-decoration:none;">✕ <?php esc_html_e('Clear','tempmail-pro'); ?></a>
        <?php endif; ?>
    </form>
    <div class="tmpmp-vis-filter">
        <?php
        $fb = add_query_arg('subtab','visitors',$base_url).($search?'&s='.urlencode($search):'');
        foreach(['all'=>__('All','tempmail-pro'),'humans'=>__('Humans','tempmail-pro'),'bots'=>__('Bots','tempmail-pro')] as $k=>$lbl):
            $ac = ($filter===$k||($k==='all'&&!$filter))?'active':'';
            $hr = $k==='all'?$fb:$fb.'&filter='.$k;
        ?>
        <a href="<?php echo esc_url($hr); ?>" class="<?php echo $ac; ?>"><?php echo esc_html($lbl); ?></a>
        <?php endforeach; ?>
    </div>
    <span style="font-size:12px;color:#94a3b8;margin-left:auto;"><?php printf(esc_html__('%d–%d of %d','tempmail-pro'),$offset+1,min($offset+$limit,$total),$total); ?></span>
</div>

<!-- Visitor table -->
<div class="tmpmp-vis-table-wrap">
  <div class="tmpmp-vis-table-scroll">
    <table class="tmpmp-vis-table">
        <thead>
            <tr>
                <th style="width:36px;">#</th>
                <th><?php esc_html_e('IP Address','tempmail-pro'); ?></th>
                <th><?php esc_html_e('Country','tempmail-pro'); ?></th>
                <th><?php esc_html_e('Page','tempmail-pro'); ?></th>
                <th><?php esc_html_e('Referrer','tempmail-pro'); ?></th>
                <th><?php esc_html_e('Browser','tempmail-pro'); ?></th>
                <th><?php esc_html_e('OS','tempmail-pro'); ?></th>
                <th><?php esc_html_e('User Agent','tempmail-pro'); ?></th>
                <th><?php esc_html_e('Type','tempmail-pro'); ?></th>
                <th><?php esc_html_e('Time','tempmail-pro'); ?></th>
                <th><?php esc_html_e('Actions','tempmail-pro'); ?></th>
            </tr>
        </thead>
        <tbody>
        <?php if(empty($rows)): ?>
            <tr><td colspan="11" style="text-align:center;padding:38px;color:#94a3b8;"><?php esc_html_e('No visitor records found.','tempmail-pro'); ?></td></tr>
        <?php else: ?>
        <?php foreach($rows as $i=>$row):
            $path          = parse_url($row['page_url'],PHP_URL_PATH)?:'/';
            /* ── Referrer: robust parsing ── */
                $raw_ref   = trim($row['referrer'] ?? '');
                $ref_label = '';
                $ref_url   = '';
                if ( $raw_ref !== '' ) {
                    // Add scheme if missing so parse_url works
                    $parsable = (str_starts_with($raw_ref,'http://')||str_starts_with($raw_ref,'https://'))
                                ? $raw_ref
                                : 'https://' . $raw_ref;
                    $host = parse_url($parsable, PHP_URL_HOST);
                    if ($host) {
                        $ref_label = preg_replace('/^www\./','',$host); // strip leading www.
                        $ref_url   = $raw_ref;
                    } else {
                        // Non-URL referrer string (e.g. android-app://, custom) — show truncated
                        $ref_label = mb_strimwidth($raw_ref, 0, 35, '…');
                        $ref_url   = '';
                    }
                }
            $ua_short      = mb_strimwidth($row['user_agent']??'', 0, 60, '…');
            $is_ip_blocked = TempMail_Visitors::is_blocked_ip($row['ip']);
            $is_ua_blocked = !empty($row['user_agent']) && TempMail_Visitors::is_blocked_ua($row['user_agent']);
        ?>
            <tr>
                <td class="col-num" data-label="#" style="color:#94a3b8;font-size:11px;"><?php echo $offset+$i+1; ?></td>

                <td data-label="<?php esc_attr_e('IP','tempmail-pro'); ?>">
                    <button type="button" class="tmpmp-ip-btn tmpmp-vis-ip"
                        data-ip="<?php echo esc_attr($row['ip']); ?>"
                        data-country="<?php echo esc_attr($row['country']); ?>"
                        title="<?php esc_attr_e('Click to view IP info','tempmail-pro'); ?>"><?php echo esc_html($row['ip']); ?></button>
                    <?php if($is_ip_blocked): ?><span class="tmpmp-vis-badge tmpmp-vis-badge--blocked" style="margin-left:4px;font-size:9.5px;">blocked</span><?php endif; ?>
                </td>

                <td data-label="<?php esc_attr_e('Country','tempmail-pro'); ?>" style="font-size:12.5px;font-weight:700;white-space:nowrap;">
                    <?php if($row['country']): ?>
                        <?php $cc = strtolower(esc_attr($row['country'])); ?>
                        <img class="tmpmp-flag"
                             src="https://flagcdn.com/20x15/<?php echo $cc; ?>.png"
                             srcset="https://flagcdn.com/40x30/<?php echo $cc; ?>.png 2x"
                             width="20" height="15"
                             alt="<?php echo esc_attr($row['country']); ?>"
                             onerror="this.style.display='none'"><?php echo esc_html($row['country']); ?>
                    <?php else: ?>
                        <span style="color:#cbd5e1;font-size:11px;">—</span>
                    <?php endif; ?>
                </td>

                <td data-label="<?php esc_attr_e('Page','tempmail-pro'); ?>">
                    <span class="tmpmp-vis-page-url" title="<?php echo esc_attr($row['page_url']); ?>"><?php echo esc_html($path); ?></span>
                </td>

                <td data-label="<?php esc_attr_e('Referrer','tempmail-pro'); ?>" style="max-width:150px;overflow:hidden;">
                    <?php if($ref_label !== ''): ?>
                        <?php if($ref_url !== ''): ?>
                        <a class="tmpmp-vis-ref-cell"
                           href="<?php echo esc_attr($ref_url); ?>"
                           target="_blank" rel="noopener noreferrer"
                           title="<?php echo esc_attr($raw_ref); ?>"><?php echo esc_html($ref_label); ?></a>
                        <?php else: ?>
                        <span class="tmpmp-vis-ref-cell" style="color:#94a3b8;" title="<?php echo esc_attr($raw_ref); ?>"><?php echo esc_html($ref_label); ?></span>
                        <?php endif; ?>
                    <?php else: ?>
                        <span style="color:#cbd5e1;font-size:11px;">direct</span>
                    <?php endif; ?>
                </td>

                <td data-label="<?php esc_attr_e('Browser','tempmail-pro'); ?>" style="font-size:12px;"><?php echo esc_html($row['browser']?:'—'); ?></td>

                <td data-label="<?php esc_attr_e('OS','tempmail-pro'); ?>" style="font-size:12px;"><?php echo esc_html($row['os']?:'—'); ?></td>

                <td data-label="<?php esc_attr_e('User Agent','tempmail-pro'); ?>">
                    <div class="tmpmp-vis-ua-wrap">
                        <span class="tmpmp-vis-ua-cell" title="<?php echo esc_attr($row['user_agent']??''); ?>"><?php echo esc_html($ua_short?:'—'); ?></span>
                        <?php if(!empty($row['user_agent'])): ?>
                        <button type="button" class="tmpmp-ua-btn"
                            data-ua="<?php echo esc_attr($row['user_agent']); ?>"
                            data-browser="<?php echo esc_attr($row['browser']?:'Unknown'); ?>"
                            data-os="<?php echo esc_attr($row['os']?:'Unknown'); ?>"
                            data-bot="<?php echo $row['is_bot']?'1':'0'; ?>"
                            aria-label="<?php esc_attr_e('View full user agent','tempmail-pro'); ?>">
                            👁 <?php esc_html_e('View','tempmail-pro'); ?>
                        </button>
                        <?php endif; ?>
                    </div>
                </td>

                <td data-label="<?php esc_attr_e('Type','tempmail-pro'); ?>">
                    <?php if($row['is_bot']): ?>
                    <span class="tmpmp-vis-badge tmpmp-vis-badge--bot"><?php esc_html_e('Bot','tempmail-pro'); ?></span>
                    <?php else: ?>
                    <span class="tmpmp-vis-badge tmpmp-vis-badge--human"><?php esc_html_e('Human','tempmail-pro'); ?></span>
                    <?php endif; ?>
                </td>

                <td data-label="<?php esc_attr_e('Time','tempmail-pro'); ?>" style="font-size:11.5px;color:#64748b;white-space:nowrap;">
                    <?php echo esc_html(get_date_from_gmt($row['visited_at'],'Y-m-d H:i')); ?>
                </td>

                <td class="col-actions" data-label="<?php esc_attr_e('Actions','tempmail-pro'); ?>">
                    <div class="tmpmp-vis-actions">
                    <?php if($is_ip_blocked): ?>
                        <form method="post" style="display:inline;">
                            <?php wp_nonce_field('tmpmp_block_action'); ?>
                            <input type="hidden" name="tmpmp_block_action" value="unblock_ip">
                            <input type="hidden" name="unblock_ip" value="<?php echo esc_attr($row['ip']); ?>">
                            <button type="submit" class="tmpmp-qb tmpmp-qb--unblock">✓ <?php esc_html_e('Unblock','tempmail-pro'); ?></button>
                        </form>
                    <?php else: ?>
                        <form method="post" style="display:inline;">
                            <?php wp_nonce_field('tmpmp_block_action'); ?>
                            <input type="hidden" name="tmpmp_block_action" value="block_ip_single">
                            <input type="hidden" name="block_ip" value="<?php echo esc_attr($row['ip']); ?>">
                            <button type="submit" class="tmpmp-qb tmpmp-qb--block">🚫 IP</button>
                        </form>
                    <?php endif; ?>
                    <?php if(!empty($row['user_agent']) && !$is_ua_blocked): ?>
                        <form method="post" style="display:inline;">
                            <?php wp_nonce_field('tmpmp_block_action'); ?>
                            <input type="hidden" name="tmpmp_block_action" value="block_ua_single">
                            <input type="hidden" name="block_ua" value="<?php echo esc_attr(mb_substr($row['user_agent'],0,80)); ?>">
                            <button type="submit" class="tmpmp-qb tmpmp-qb--ua" title="<?php echo esc_attr($row['user_agent']); ?>">🤖 UA</button>
                        </form>
                    <?php endif; ?>
                    </div>
                </td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>
  </div>
</div>

<!-- Pagination -->
<?php if($pages>1): ?>
<div class="tmpmp-vis-pagination">
    <div style="font-size:12.5px;color:#64748b;"><?php printf(esc_html__('Page %d of %d','tempmail-pro'),$paged,$pages); ?></div>
    <div style="display:flex;gap:5px;flex-wrap:wrap;">
        <?php if($paged>1): ?><a href="<?php echo esc_url(add_query_arg('paged',$paged-1)); ?>">← <?php esc_html_e('Prev','tempmail-pro'); ?></a><?php endif; ?>
        <?php for($p=max(1,$paged-3);$p<=min($pages,$paged+3);$p++): ?>
        <a href="<?php echo esc_url(add_query_arg('paged',$p)); ?>" class="<?php echo $p===$paged?'current':''; ?>"><?php echo $p; ?></a>
        <?php endfor; ?>
        <?php if($paged<$pages): ?><a href="<?php echo esc_url(add_query_arg('paged',$paged+1)); ?>"><?php esc_html_e('Next','tempmail-pro'); ?> →</a><?php endif; ?>
    </div>
</div>
<?php endif; ?>

<script>
(function(){
    const d=<?php echo wp_json_encode($chart); ?>;
    const labels=d.map(r=>r.d),tot=d.map(r=>+r.total||0),hum=d.map(r=>+r.humans||0),bots=tot.map((v,i)=>v-hum[i]);
    const lEl=document.getElementById('tmpmp-vis-line');
    if(lEl&&labels.length) new Chart(lEl,{type:'line',data:{labels,datasets:[
        {label:'<?php echo esc_js(__('Humans','tempmail-pro')); ?>',data:hum,borderColor:'#6366f1',backgroundColor:'rgba(99,102,241,.08)',fill:true,tension:.4,pointRadius:2},
        {label:'<?php echo esc_js(__('Bots','tempmail-pro')); ?>',data:bots,borderColor:'#f59e0b',backgroundColor:'rgba(245,158,11,.06)',fill:true,tension:.4,pointRadius:2}
    ]},options:{responsive:true,plugins:{legend:{position:'bottom',labels:{font:{size:10}}}},scales:{y:{beginAtZero:true,ticks:{precision:0,font:{size:10}}}}}});
    const bd=<?php echo wp_json_encode(array_values($browsers)); ?>;
    const dEl=document.getElementById('tmpmp-vis-donut');
    if(dEl&&bd.length) new Chart(dEl,{type:'doughnut',data:{
        labels:bd.map(b=>b.browser),
        datasets:[{data:bd.map(b=>+b.cnt||0),backgroundColor:bd.map(b=>({'Chrome':'#4285f4','Firefox':'#ff6d00','Safari':'#1c9be1','Edge':'#0078d4','Opera':'#ff1b2d','IE':'#1ebbee','Other':'#94a3b8'})[b.browser]||'#94a3b8'),borderWidth:2}]
    },options:{responsive:true,cutout:'65%',plugins:{legend:{display:false}}}});
})();
</script>

<?php elseif($subtab==='blocked-ips'): ?>

<!-- ── Blocked IPs ─────────────────────────────────────────────────── -->
<div class="tmpmp-block-two-col">
    <div class="tmpmp-block-card">
        <h3>🚫 <?php esc_html_e('IP Block List','tempmail-pro'); ?></h3>
        <p><?php esc_html_e('One IP or CIDR range per line. Blocked visitors get a 403 response.','tempmail-pro'); ?></p>
        <form method="post">
            <?php wp_nonce_field('tmpmp_block_action'); ?>
            <input type="hidden" name="tmpmp_block_action" value="save_ips">
            <textarea name="blocked_ips" class="tmpmp-block-textarea" placeholder="192.168.1.100&#10;10.0.0.0/24"><?php echo esc_textarea(implode("\n",$blocked_ips)); ?></textarea>
            <div class="tmpmp-block-hint">💡 <?php esc_html_e('Supports exact IPv4 and CIDR ranges e.g. 192.168.1.0/24','tempmail-pro'); ?></div>
            <button type="submit" class="tmpmp-block-save-btn">💾 <?php esc_html_e('Save List','tempmail-pro'); ?></button>
        </form>
        <form method="post" class="tmpmp-add-row">
            <?php wp_nonce_field('tmpmp_block_action'); ?>
            <input type="hidden" name="tmpmp_block_action" value="block_ip_single">
            <input type="text" name="block_ip" placeholder="<?php esc_attr_e('Quick-add IP or CIDR…','tempmail-pro'); ?>" required>
            <button type="submit">+ <?php esc_html_e('Block','tempmail-pro'); ?></button>
        </form>
    </div>
    <div class="tmpmp-block-card">
        <h3>📋 <?php printf(esc_html__('Active Blocks (%d)','tempmail-pro'),count($blocked_ips)); ?></h3>
        <p><?php esc_html_e('Click Remove to immediately unblock.','tempmail-pro'); ?></p>
        <?php if(empty($blocked_ips)): ?>
        <div class="tmpmp-blocklist-empty">🟢 <?php esc_html_e('No IPs blocked — all visitors are allowed.','tempmail-pro'); ?></div>
        <?php else: ?>
        <table class="tmpmp-blocklist-table">
            <thead><tr><th><?php esc_html_e('IP / Range','tempmail-pro'); ?></th><th><?php esc_html_e('Type','tempmail-pro'); ?></th><th></th></tr></thead>
            <tbody>
            <?php foreach($blocked_ips as $ip):
                $type  = str_contains($ip,'/')?'CIDR':'Exact';
                $tcolor= str_contains($ip,'/')?'#7c3aed':'#dc2626';
            ?>
            <tr>
                <td><code style="font-size:12.5px;"><?php echo esc_html($ip); ?></code></td>
                <td><span style="font-size:10.5px;font-weight:700;color:<?php echo $tcolor; ?>;"><?php echo $type; ?></span></td>
                <td>
                    <form method="post" style="display:inline;">
                        <?php wp_nonce_field('tmpmp_block_action'); ?>
                        <input type="hidden" name="tmpmp_block_action" value="unblock_ip">
                        <input type="hidden" name="unblock_ip" value="<?php echo esc_attr($ip); ?>">
                        <button type="submit" class="tmpmp-qb tmpmp-qb--unblock">✓ <?php esc_html_e('Remove','tempmail-pro'); ?></button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>

<?php elseif($subtab==='blocked-uas'): ?>

<!-- ── Blocked User Agents ─────────────────────────────────────────── -->
<div class="tmpmp-block-two-col">
    <div class="tmpmp-block-card">
        <h3>🤖 <?php esc_html_e('User Agent Block List','tempmail-pro'); ?></h3>
        <p><?php esc_html_e('One pattern per line. Substring match, case-insensitive. Matching visitors are blocked.','tempmail-pro'); ?></p>
        <form method="post">
            <?php wp_nonce_field('tmpmp_block_action'); ?>
            <input type="hidden" name="tmpmp_block_action" value="save_uas">
            <textarea name="blocked_uas" class="tmpmp-block-textarea" placeholder="python-requests&#10;curl/&#10;AhrefsBot&#10;SemrushBot"><?php echo esc_textarea(implode("\n",$blocked_uas)); ?></textarea>
            <div class="tmpmp-block-hint">💡 <?php esc_html_e('"python" matches "python-requests/2.28", "Python/3.11" etc.','tempmail-pro'); ?></div>
            <button type="submit" class="tmpmp-block-save-btn">💾 <?php esc_html_e('Save List','tempmail-pro'); ?></button>
        </form>
        <form method="post" class="tmpmp-add-row">
            <?php wp_nonce_field('tmpmp_block_action'); ?>
            <input type="hidden" name="tmpmp_block_action" value="block_ua_single">
            <input type="text" name="block_ua" placeholder="<?php esc_attr_e('Quick-add UA pattern…','tempmail-pro'); ?>" required>
            <button type="submit">+ <?php esc_html_e('Block','tempmail-pro'); ?></button>
        </form>
        <div style="margin-top:14px;">
            <div style="font-size:10.5px;font-weight:800;color:#94a3b8;text-transform:uppercase;letter-spacing:.5px;margin-bottom:7px;">⚡ <?php esc_html_e('Common Presets','tempmail-pro'); ?></div>
            <div class="tmpmp-presets">
            <?php foreach(['python-requests','curl/','scrapy','AhrefsBot','SemrushBot','MJ12bot','DotBot','BLEXBot','PetalBot','DataForSeoBot'] as $p):
                $blocked_p = TempMail_Visitors::is_blocked_ua($p); ?>
            <?php if(!$blocked_p): ?>
            <form method="post" style="display:inline;">
                <?php wp_nonce_field('tmpmp_block_action'); ?>
                <input type="hidden" name="tmpmp_block_action" value="block_ua_single">
                <input type="hidden" name="block_ua" value="<?php echo esc_attr($p); ?>">
                <button type="submit" style="padding:3px 9px;border:1.5px solid #e2e8f0;border-radius:20px;font-size:11px;font-weight:600;background:#fff;cursor:pointer;color:#374151;">+ <?php echo esc_html($p); ?></button>
            </form>
            <?php else: ?>
            <span style="padding:3px 9px;border:1.5px solid #dcfce7;border-radius:20px;font-size:11px;font-weight:600;background:#dcfce7;color:#16a34a;">✓ <?php echo esc_html($p); ?></span>
            <?php endif; ?>
            <?php endforeach; ?>
            </div>
        </div>
    </div>
    <div class="tmpmp-block-card">
        <h3>📋 <?php printf(esc_html__('Active Blocks (%d)','tempmail-pro'),count($blocked_uas)); ?></h3>
        <p><?php esc_html_e('Any request whose User-Agent contains one of these patterns is blocked (403).','tempmail-pro'); ?></p>
        <?php if(empty($blocked_uas)): ?>
        <div class="tmpmp-blocklist-empty">🟢 <?php esc_html_e('No UA patterns blocked yet.','tempmail-pro'); ?></div>
        <?php else: ?>
        <table class="tmpmp-blocklist-table">
            <thead><tr><th><?php esc_html_e('Pattern','tempmail-pro'); ?></th><th></th></tr></thead>
            <tbody>
            <?php foreach($blocked_uas as $ua): ?>
            <tr>
                <td><code style="font-size:12px;color:#7c3aed;"><?php echo esc_html($ua); ?></code></td>
                <td>
                    <form method="post" style="display:inline;">
                        <?php wp_nonce_field('tmpmp_block_action'); ?>
                        <input type="hidden" name="tmpmp_block_action" value="unblock_ua">
                        <input type="hidden" name="unblock_ua" value="<?php echo esc_attr($ua); ?>">
                        <button type="submit" class="tmpmp-qb tmpmp-qb--unblock">✓ <?php esc_html_e('Remove','tempmail-pro'); ?></button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>

<?php endif; ?>


<!-- UA Detail Modal -->
<div id="tmpmp-ua-modal" role="dialog" aria-modal="true" aria-labelledby="tmpmp-ua-title">
    <div id="tmpmp-ua-backdrop"></div>
    <div id="tmpmp-ua-box">
        <h4 id="tmpmp-ua-title">🔍 <?php esc_html_e('User Agent Details','tempmail-pro'); ?></h4>
        <div class="tmpmp-ua-meta" id="tmpmp-ua-meta"></div>
        <div id="tmpmp-ua-string"></div>
        <div class="tmpmp-ua-footer">
            <button id="tmpmp-ua-copy">📋 <?php esc_html_e('Copy','tempmail-pro'); ?></button>
            <button id="tmpmp-ua-close">✕ <?php esc_html_e('Close','tempmail-pro'); ?></button>
        </div>
    </div>
</div>
<script>
(function(){
    const modal   = document.getElementById('tmpmp-ua-modal');
    const box     = document.getElementById('tmpmp-ua-box');
    const strEl   = document.getElementById('tmpmp-ua-string');
    const metaEl  = document.getElementById('tmpmp-ua-meta');
    const copyBtn = document.getElementById('tmpmp-ua-copy');
    const closeBtn= document.getElementById('tmpmp-ua-close');
    const backdrop= document.getElementById('tmpmp-ua-backdrop');
    let currentUA = '';

    function openModal(btn){
        currentUA = btn.dataset.ua || '';
        const browser = btn.dataset.browser || '';
        const os      = btn.dataset.os || '';
        const isBot   = btn.dataset.bot === '1';

        strEl.textContent = currentUA || '—';

        // Build meta badges
        let badges = '';
        if(browser) badges += `<span>🌐 ${escH(browser)}</span>`;
        if(os)      badges += `<span>💻 ${escH(os)}</span>`;
        if(isBot)   badges += `<span style="background:#fee2e2;color:#dc2626;">🤖 Bot</span>`;
        else        badges += `<span style="background:#dcfce7;color:#16a34a;">👤 Human</span>`;
        metaEl.innerHTML = badges;

        copyBtn.innerHTML = '&#128203; <?php echo esc_js(__('Copy','tempmail-pro')); ?>';
        copyBtn.classList.remove('copied');
        modal.classList.add('open');
        document.body.style.overflow = 'hidden';
        closeBtn.focus();
    }

    function closeModal(){
        modal.classList.remove('open');
        document.body.style.overflow = '';
    }

    function escH(s){ const d=document.createElement('div'); d.textContent=s; return d.innerHTML; }

    // Attach to all UA buttons
    document.querySelectorAll('.tmpmp-ua-btn').forEach(btn=>{
        btn.addEventListener('click', ()=> openModal(btn));
    });

    backdrop.addEventListener('click', closeModal);
    closeBtn.addEventListener('click', closeModal);

    document.addEventListener('keydown', e=>{ if(e.key==='Escape' && modal.classList.contains('open')) closeModal(); });


    /* ── Copy helper ─────────────────────────────────────────────────── */
    function showCopied() {
        copyBtn.innerHTML = '&#10003; <?php echo esc_js(__('Copied!','tempmail-pro')); ?>';
        copyBtn.classList.add('copied');
        setTimeout(function(){
            copyBtn.innerHTML = '&#128203; <?php echo esc_js(__('Copy','tempmail-pro')); ?>';
            copyBtn.classList.remove('copied');
        }, 2200);
    }

    function execFallbackCopy(text) {
        try {
            var ta = document.createElement('textarea');
            ta.value = text;
            ta.setAttribute('readonly', '');
            ta.style.cssText = 'position:fixed;top:-9999px;left:-9999px;opacity:0;pointer-events:none;';
            document.body.appendChild(ta);
            ta.focus();
            ta.select();
            var ok = document.execCommand('copy');
            document.body.removeChild(ta);
            if (ok) showCopied();
        } catch(err) {
            /* silent — nothing more we can do without clipboard API */
        }
    }

    copyBtn.addEventListener('click', function(){
        if (!currentUA) return;
        /* Use modern Clipboard API only when available AND in a secure context */
        if (navigator.clipboard && typeof navigator.clipboard.writeText === 'function' && window.isSecureContext) {
            navigator.clipboard.writeText(currentUA)
                .then(showCopied)
                .catch(function(){ execFallbackCopy(currentUA); });
        } else {
            execFallbackCopy(currentUA);
        }
    });
})();
</script>

<!-- IP Info Modal -->
<div id="tmpmp-ip-modal" role="dialog" aria-modal="true" aria-labelledby="tmpmp-ip-addr">
    <div id="tmpmp-ip-backdrop"></div>
    <div id="tmpmp-ip-box">
        <div class="ip-modal-head">
            <span class="ip-modal-addr" id="tmpmp-ip-addr">—</span>
            <button id="tmpmp-ip-close-btn" aria-label="<?php esc_attr_e('Close','tempmail-pro'); ?>">✕</button>
        </div>
        <div id="tmpmp-ip-spinner">⏳ <?php esc_html_e('Looking up IP info…','tempmail-pro'); ?></div>
        <div id="tmpmp-ip-error" style="display:none;"></div>
        <div class="tmpmp-ip-rows" id="tmpmp-ip-rows" style="display:none;">
            <span class="tmpmp-ip-label"><?php esc_html_e('ISP:','tempmail-pro'); ?></span>      <span class="tmpmp-ip-value" id="pip-isp"></span>
            <span class="tmpmp-ip-label"><?php esc_html_e('Services:','tempmail-pro'); ?></span>  <span class="tmpmp-ip-value" id="pip-services"></span>
            <span class="tmpmp-ip-label"><?php esc_html_e('City:','tempmail-pro'); ?></span>     <span class="tmpmp-ip-value" id="pip-city"></span>
            <span class="tmpmp-ip-label"><?php esc_html_e('Region:','tempmail-pro'); ?></span>   <span class="tmpmp-ip-value" id="pip-region"></span>
            <span class="tmpmp-ip-label"><?php esc_html_e('Country:','tempmail-pro'); ?></span>  <span class="tmpmp-ip-value" id="pip-country"></span>
        </div>
    </div>
</div>
<script>
(function(){
    /* ── Country flag images (flagcdn.com — works on all OS including Windows) ── */
    function flagImg(cc, size) {
        if (!cc || cc.length !== 2) return '';
        var s = size || '20x15', s2 = (size === '16x12') ? '32x24' : '40x30';
        var lc = cc.toLowerCase();
        return '<img class="tmpmp-flag" src="https://flagcdn.com/'+s+'/'+lc+'.png" srcset="https://flagcdn.com/'+s2+'/'+lc+'.png 2x" width="20" height="15" alt="'+cc+'" onerror="this.style.display=\'none\'">';
    }

    /* ── IP Info Modal ── */
    var ipModal   = document.getElementById('tmpmp-ip-modal');
    var ipBackdrop= document.getElementById('tmpmp-ip-backdrop');
    var ipAddr    = document.getElementById('tmpmp-ip-addr');
    var ipSpinner = document.getElementById('tmpmp-ip-spinner');
    var ipError   = document.getElementById('tmpmp-ip-error');
    var ipRows    = document.getElementById('tmpmp-ip-rows');
    var ipCloseBtn= document.getElementById('tmpmp-ip-close-btn');
    var cache     = {};

    function val(id, text){
        var el = document.getElementById(id);
        if(!el) return;
        if(text){ el.textContent = text; el.classList.remove('empty'); }
        else    { el.textContent = '<?php echo esc_js(__('Unknown','tempmail-pro')); ?>'; el.classList.add('empty'); }
    }

    function showData(data){
        val('pip-isp',      data.isp||'');
        val('pip-services', data.org||data.as||'');
        val('pip-city',     data.city||'');
        val('pip-region',   data.regionName||'');
        /* Country: use flag image + full name */
        var countryEl = document.getElementById('pip-country');
        if (countryEl) {
            var cc      = data.countryCode || '';
            var country = data.country     || '';
            if (country) {
                countryEl.innerHTML = flagImg(cc) + ' ' + country;
                countryEl.classList.remove('empty');
            } else {
                countryEl.textContent = '<?php echo esc_js(__('Unknown','tempmail-pro')); ?>';
                countryEl.classList.add('empty');
            }
        }
        ipSpinner.style.display = 'none';
        ipError.style.display   = 'none';
        ipRows.style.display    = 'grid';
    }

    function showError(msg){
        ipSpinner.style.display = 'none';
        ipRows.style.display    = 'none';
        ipError.style.display   = 'block';
        ipError.textContent     = msg;
    }

    function openIpModal(ip){
        ipAddr.textContent      = ip;
        ipSpinner.style.display = 'block';
        ipRows.style.display    = 'none';
        ipError.style.display   = 'none';
        ipModal.classList.add('open');
        document.body.style.overflow = 'hidden';

        if(cache[ip]){ showData(cache[ip]); return; }

        fetch('http://ip-api.com/json/'+encodeURIComponent(ip)+'?fields=status,message,country,countryCode,regionName,city,isp,org,as')
            .then(function(r){ return r.json(); })
            .then(function(d){
                if(d.status==='fail'){
                    showError('<?php echo esc_js(__('Could not look up this IP address.','tempmail-pro')); ?> ('+(d.message||'unknown')+')');
                } else {
                    cache[ip] = d;
                    showData(d);
                }
            })
            .catch(function(){
                showError('<?php echo esc_js(__('Network error. Please check your connection.','tempmail-pro')); ?>');
            });
    }

    function closeIpModal(){
        ipModal.classList.remove('open');
        document.body.style.overflow = '';
    }

    document.querySelectorAll('.tmpmp-ip-btn').forEach(function(btn){
        btn.addEventListener('click', function(){ openIpModal(btn.dataset.ip); });
    });
    ipBackdrop.addEventListener('click', closeIpModal);
    ipCloseBtn.addEventListener('click', closeIpModal);
    document.addEventListener('keydown', function(e){
        if(e.key==='Escape' && ipModal.classList.contains('open')) closeIpModal();
    });
})();
</script>
