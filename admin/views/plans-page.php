<?php if ( ! defined('ABSPATH') ) exit; ?>
<div class="wrap tmpmp-admin-wrap">
<h1 class="tmpmp-admin-title"><span class="dashicons dashicons-awards"></span> <?php esc_html_e('Subscription Plans','tempmail-pro'); ?> <span class="tmpmp-version-pill">v<?php echo esc_html(TMPMP_VERSION); ?></span></h1>

<style>
/* ── Plans page base ─────────────────────────────────────────── */
.tmpmp-page-card{background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:20px 24px;margin-bottom:20px;}
.tmpmp-page-section-title{font-size:11px;font-weight:800;letter-spacing:.8px;text-transform:uppercase;color:#6366f1;margin:0 0 16px;}
.tmpmp-page-field{display:grid;grid-template-columns:180px 1fr;gap:12px 20px;align-items:start;padding:14px 0;border-bottom:1px solid #f1f5f9;}
.tmpmp-page-field:last-child{border-bottom:none;}
.tmpmp-page-label{font-size:13px;font-weight:600;color:#334155;padding-top:9px;}
.tmpmp-page-input{width:100%;padding:8px 12px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:13px;font-family:inherit;outline:none;transition:border-color .15s;box-sizing:border-box;}
.tmpmp-page-input:focus{border-color:#6366f1;box-shadow:0 0 0 3px rgba(99,102,241,.1);}
.tmpmp-page-select{padding:8px 12px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:13px;font-family:inherit;outline:none;cursor:pointer;background:#fff;}
.tmpmp-page-select:focus{border-color:#6366f1;}
.tmpmp-page-hint{font-size:12px;color:#94a3b8;margin-top:6px;}

/* ── Table — desktop ─────────────────────────────────────────── */
.tmpmp-styled-table{width:100%;border-collapse:collapse;font-size:13px;}
.tmpmp-styled-table th{background:#f8fafc;color:#475569;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;padding:10px 14px;text-align:left;border-bottom:2px solid #e2e8f0;}
.tmpmp-styled-table td{padding:12px 14px;border-bottom:1px solid #f1f5f9;color:#334155;vertical-align:middle;}
.tmpmp-styled-table tr:last-child td{border-bottom:none;}
.tmpmp-styled-table tr:hover td{background:#fafbff;}

/* ── Badges ──────────────────────────────────────────────────── */
.tmpmp-badge{display:inline-flex;align-items:center;padding:3px 10px;border-radius:20px;font-size:11.5px;font-weight:700;line-height:1.4;}
.tmpmp-badge--green{background:#dcfce7;color:#065f46;}
.tmpmp-badge--red{background:#fee2e2;color:#991b1b;}

/* ── Buttons ─────────────────────────────────────────────────── */
.tmpmp-icon-btn{display:inline-flex;align-items:center;gap:5px;padding:5px 10px;border-radius:6px;font-size:12px;font-weight:600;cursor:pointer;border:1.5px solid #e2e8f0;background:#fff;transition:all .15s;text-decoration:none;}
.tmpmp-icon-btn:hover{border-color:#6366f1;color:#6366f1;}
.tmpmp-icon-btn--danger:hover{border-color:#ef4444;color:#ef4444;}
.tmpmp-add-btn{display:inline-flex;align-items:center;gap:8px;padding:9px 18px;background:linear-gradient(135deg,#6366f1,#8b5cf6);color:#fff;border:none;border-radius:8px;font-size:13px;font-weight:700;cursor:pointer;font-family:inherit;transition:all .15s;}
.tmpmp-add-btn:hover{opacity:.9;transform:translateY(-1px);box-shadow:0 4px 12px rgba(99,102,241,.3);}
.tmpmp-empty-row td{text-align:center;padding:32px!important;color:#94a3b8;}

/* ── Form field responsive ───────────────────────────────────── */
@media(max-width:600px){.tmpmp-page-field{grid-template-columns:1fr;}.tmpmp-page-card{padding:16px 14px;}}

/* ════════════════════════════════════════════════════════════════
   RESPONSIVE TABLE — stacked card rows on mobile
   Each <td data-label="..."> becomes a labelled row in the card.
════════════════════════════════════════════════════════════════ */
@media (max-width: 700px) {
    /* Kill the overflow wrapper so cards go full width */
    .tmpmp-table-wrap { overflow-x: visible !important; }

    /* Hide desktop header */
    .tmpmp-styled-table thead { display: none; }

    /* Reflow elements as blocks */
    .tmpmp-styled-table,
    .tmpmp-styled-table tbody,
    .tmpmp-styled-table tr { display: block; width: 100%; }

    /* Each row = a card */
    .tmpmp-styled-table tr {
        border: 1.5px solid #e2e8f0;
        border-radius: 14px;
        margin-bottom: 12px;
        background: #fff;
        box-shadow: 0 1px 6px rgba(0,0,0,.05);
        overflow: hidden;
        padding: 0;
    }
    .tmpmp-styled-table tr:hover { background: #fafbff; }
    .tmpmp-styled-table tr:last-child { margin-bottom: 0; }

    /* Each cell = a label-value row */
    .tmpmp-styled-table td {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 12px;
        padding: 11px 16px;
        border-bottom: 1px solid #f1f5f9;
        font-size: 13px;
        text-align: right;
        vertical-align: unset;
    }
    .tmpmp-styled-table td:last-child { border-bottom: none; }

    /* Label via data-label */
    .tmpmp-styled-table td::before {
        content: attr(data-label);
        font-size: 10.5px;
        font-weight: 800;
        letter-spacing: .5px;
        text-transform: uppercase;
        color: #6366f1;
        white-space: nowrap;
        flex-shrink: 0;
        text-align: left;
    }

    /* Hide ID on mobile */
    .tmpmp-styled-table td[data-label="ID"] { display: none; }

    /* Plan name cell — stack strong+code on the right */
    .tmpmp-styled-table td[data-label="Plan"] { align-items: flex-start; }
    .tmpmp-styled-table td[data-label="Plan"] strong { display: block; margin-bottom: 2px; }

    /* Features — allow wrap */
    .tmpmp-styled-table td[data-label="Features"] {
        align-items: flex-start;
        text-align: right;
        word-break: break-word;
    }

    /* Actions cell — full-width row, no label, right-aligned buttons */
    .tmpmp-styled-table td[data-label="Actions"] {
        justify-content: flex-end;
        background: #f8fafc;
        padding: 10px 14px;
        gap: 8px;
    }
    .tmpmp-styled-table td[data-label="Actions"]::before { display: none; }

    /* Empty row */
    .tmpmp-empty-row td {
        justify-content: center;
        border-radius: 14px;
    }
    .tmpmp-empty-row td::before { display: none; }
}


/* ══════════════════════════════════════════════════════════════
   PLAN MODAL — clean card style (matches UA-box design)
══════════════════════════════════════════════════════════════ */

/* Dialog = the card */
#tmpmp-plan-modal {
    border: none;
    outline: none;
    padding: 0;
    background: #ffffff;
    border-radius: 20px;
    width: min(780px, calc(100vw - 32px));
    max-width: calc(100vw - 32px);
    /* Both height AND max-height so flex children (header/body/footer) share the space */
    height: min(880px, calc(100vh - 48px));
    max-height: calc(100vh - 48px);
    overflow: hidden;
    box-shadow:
        0 0 0 1px rgba(0,0,0,.06),
        0 8px 24px rgba(0,0,0,.10),
        0 32px 64px rgba(0,0,0,.12);
    display: none;
}
#tmpmp-plan-modal[open] {
    display: flex;
    flex-direction: column;
    animation: tmSlideUp .26s cubic-bezier(.22,1,.36,1);
}
#tmpmp-plan-modal::backdrop {
    background: rgba(15, 23, 42, .55);
    backdrop-filter: blur(8px);
    -webkit-backdrop-filter: blur(8px);
    animation: tmFadeIn .2s ease;
}
@keyframes tmFadeIn { from{opacity:0} to{opacity:1} }
@keyframes tmSlideUp {
    from { opacity: 0; transform: translateY(28px) scale(.97); }
    to   { opacity: 1; transform: translateY(0) scale(1); }
}

/* Inner flex column fills the dialog */
.tmpmp-modal-box {
    flex: 1;
    display: flex;
    flex-direction: column;
    overflow: hidden;
    min-height: 0;
    height: 100%;   /* fills the dialog's defined height */
}

/* ── Header — clean white, like UA-box ──────────────────────── */
.tmpmp-modal-header {
    flex-shrink: 0;
    position: relative;
    padding: 22px 52px 18px 22px;
    background: #ffffff;
    border-bottom: 1px solid #f1f5f9;
    display: flex;
    align-items: center;
    gap: 14px;
}
.tmpmp-modal-header::before,
.tmpmp-modal-header::after { display: none; }

.tmpmp-modal-header-icon {
    width: 46px; height: 46px;
    background: linear-gradient(135deg, #ede9fe 0%, #ddd6fe 100%);
    border: 1.5px solid #c4b5fd;
    border-radius: 13px;
    display: flex; align-items: center; justify-content: center;
    font-size: 22px;
    flex-shrink: 0;
}
.tmpmp-modal-title {
    margin: 0;
    font-size: 17px;
    font-weight: 800;
    color: #0f172a;
    letter-spacing: -.3px;
    line-height: 1.25;
}
.tmpmp-modal-subtitle {
    font-size: 12px;
    color: #94a3b8;
    margin: 3px 0 0;
    font-weight: 400;
}
/* Plan info badges */
.tmpmp-modal-header-badges { display:flex; flex-wrap:wrap; gap:6px; margin-top:7px; }
.tmpmp-modal-badge {
    display: inline-flex; align-items: center; gap: 4px;
    padding: 3px 10px; border-radius: 20px;
    font-size: 11.5px; font-weight: 700; line-height: 1.4;
    background: #f1f5f9; color: #475569; border: 1px solid #e2e8f0;
}
.tmpmp-modal-badge--purple { background:#ede9fe; color:#5b21b6; border-color:#c4b5fd; }
.tmpmp-modal-badge--green  { background:#dcfce7; color:#065f46; border-color:#86efac; }

/* Close button — top-right light pill */
.tmpmp-modal-close {
    position: absolute; top: 15px; right: 16px;
    width: 32px; height: 32px;
    background: #f8fafc; border: 1.5px solid #e2e8f0; border-radius: 8px;
    display: flex; align-items: center; justify-content: center;
    font-size: 17px; cursor: pointer; color: #64748b; line-height: 1;
    transition: background .14s, border-color .14s, color .14s; z-index: 2;
}
.tmpmp-modal-close:hover { background:#fee2e2; border-color:#fecaca; color:#dc2626; transform:none; }

/* ── Scrollable body ─────────────────────────────────────── */
.tmpmp-modal-body {
    flex: 1;            /* takes all remaining height between header and footer */
    min-height: 0;      /* allows flex child to shrink below its content height  */
    overflow-y: auto;   /* scroll when content exceeds the flex-allocated space  */
    /* NO max-height here — the flex layout handles the constraint */
    padding: 18px 22px 14px; background: #f8fafc;
    scrollbar-width: thin; scrollbar-color: #c7d2fe #f1f5f9;
}
.tmpmp-modal-body::-webkit-scrollbar { width: 4px; }
.tmpmp-modal-body::-webkit-scrollbar-track { background: #f1f5f9; }
.tmpmp-modal-body::-webkit-scrollbar-thumb { background: #c7d2fe; border-radius: 99px; }

/* ── Section cards ──────────────────────────────────────────── */
.tmpmp-modal-section {
    background: #fff; border: 1.5px solid #e8edf5; border-radius: 14px;
    padding: 16px 18px; margin-bottom: 10px;
    transition: border-color .18s, box-shadow .18s;
}
.tmpmp-modal-section:last-child { margin-bottom: 0; }
.tmpmp-modal-section:hover { border-color: #c7d2fe; box-shadow: 0 2px 12px rgba(99,102,241,.07); }
.tmpmp-modal-section-title {
    font-size: 10px; font-weight: 800; letter-spacing: 1px;
    text-transform: uppercase; color: #6366f1; margin: 0 0 14px;
    display: flex; align-items: center; gap: 8px;
}
.tmpmp-modal-section-title::after {
    content: ''; flex: 1; height: 1px;
    background: linear-gradient(90deg, #e0e7ff, transparent);
}

/* ── Grid ───────────────────────────────────────────────────── */
.tmpmp-modal-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(150px,1fr)); gap:14px; }
.tmpmp-modal-grid--2col { grid-template-columns: 1fr 1fr; }

/* ── Labels & inputs ────────────────────────────────────────── */
.tmpmp-modal-label {
    display:flex; flex-direction:column; gap:6px;
    font-size:10.5px; font-weight:700; letter-spacing:.6px;
    text-transform:uppercase; color:#6366f1;
}
.tmpmp-modal-label input,
.tmpmp-modal-label select,
.tmpmp-modal-label textarea {
    font-size:13.5px; font-weight:500; color:#1e293b;
    padding:10px 13px; background:#f8fafc;
    border:1.5px solid #e2e8f0; border-radius:10px; outline:none;
    font-family:inherit; transition:border-color .15s,box-shadow .15s,background .15s;
    box-sizing:border-box; width:100%;
}
.tmpmp-modal-label input:hover,
.tmpmp-modal-label select:hover,
.tmpmp-modal-label textarea:hover { border-color:#a5b4fc; }
.tmpmp-modal-label input:focus,
.tmpmp-modal-label select:focus,
.tmpmp-modal-label textarea:focus { border-color:#6366f1; box-shadow:0 0 0 3px rgba(99,102,241,.13); background:#fff; }
.tmpmp-modal-label textarea { resize:vertical; min-height:90px; line-height:1.55; }

/* ── Toggles ────────────────────────────────────────────────── */
.tmpmp-modal-toggles { display:grid; grid-template-columns:repeat(auto-fill,minmax(220px,1fr)); gap:2px 8px; align-items:start; }
.tmpmp-toggle-item { display:flex; align-items:center; gap:10px; cursor:pointer; user-select:none; min-width:0; padding:8px 10px; border-radius:10px; transition:background .12s; }
.tmpmp-toggle-item:hover { background:#eef2ff; }
.tmpmp-toggle-item input[type=checkbox] { display:none; }
.tmpmp-toggle-track { flex-shrink:0; width:40px; height:22px; background:#cbd5e1; border-radius:99px; position:relative; transition:background .22s; }
.tmpmp-toggle-track::after { content:''; position:absolute; top:3px; left:3px; width:16px; height:16px; background:#fff; border-radius:50%; box-shadow:0 1px 4px rgba(0,0,0,.18); transition:transform .22s cubic-bezier(.34,1.56,.64,1); }
.tmpmp-toggle-item input:checked + .tmpmp-toggle-track { background:linear-gradient(135deg,#6366f1,#8b5cf6); }
.tmpmp-toggle-item input:checked + .tmpmp-toggle-track::after { transform:translateX(18px); }
.tmpmp-toggle-label { flex:1; min-width:0; font-size:13px; font-weight:600; color:#334155; line-height:1.35; word-break:normal!important; overflow-wrap:break-word; hyphens:none!important; }

/* ── Footer ─────────────────────────────────────────────────── */
.tmpmp-modal-footer {
    flex-shrink:0; display:flex; align-items:center; gap:10px;
    padding:14px 22px; border-top:1px solid #f1f5f9; background:#ffffff;
}
/* Save = purple gradient pill */
.tmpmp-modal-save-btn {
    display:inline-flex; align-items:center; gap:7px; padding:10px 22px;
    background:linear-gradient(135deg,#6366f1 0%,#8b5cf6 100%);
    color:#fff; border:none; border-radius:10px;
    font-size:13.5px; font-weight:700; cursor:pointer; font-family:inherit;
    box-shadow:0 3px 10px rgba(99,102,241,.35);
    transition:opacity .16s,transform .16s,box-shadow .16s;
}
.tmpmp-modal-save-btn:hover { opacity:.9; transform:translateY(-1px); box-shadow:0 6px 18px rgba(99,102,241,.42); }
.tmpmp-modal-save-btn:active { transform:none; opacity:1; }
.tmpmp-modal-save-btn:disabled { opacity:.55; cursor:not-allowed; transform:none; }
/* Cancel = light gray bordered */
.tmpmp-modal-cancel-btn {
    display:inline-flex; align-items:center; gap:6px; padding:10px 18px;
    background:#f8fafc; color:#64748b; border:1.5px solid #e2e8f0; border-radius:10px;
    font-size:13px; font-weight:600; cursor:pointer; font-family:inherit;
    transition:background .14s,border-color .14s,color .14s;
}
.tmpmp-modal-cancel-btn:hover { background:#f1f5f9; border-color:#c7d2fe; color:#334155; }
/* Footer hint */
.tmpmp-modal-footer-hint { margin-left:auto; font-size:11.5px; color:#94a3b8; display:flex; align-items:center; gap:5px; }
.tmpmp-modal-footer-hint::before { content:''; display:inline-block; width:7px; height:7px; border-radius:50%; background:#22c55e; animation:tmDot 2s ease-in-out infinite; }
@keyframes tmDot { 0%,100%{opacity:1;transform:scale(1)} 50%{opacity:.4;transform:scale(.7)} }

/* ── Responsive: bottom sheet on mobile ─────────────────────── */
@media (max-width: 640px) {
    #tmpmp-plan-modal,
    #tmpmp-plan-modal[open] {
        position: fixed !important;
        bottom: 0 !important; left: 0 !important; right: 0 !important; top: auto !important;
        width: 100% !important;
        max-width: 100% !important;
        height: 92vh !important;
        max-height: 92vh !important;
        margin: 0 !important;
        border-radius: 20px 20px 0 0;
    }
    #tmpmp-plan-modal[open] {
        animation: tmSlideUpMob .3s cubic-bezier(.22,1,.36,1) !important;
    }
    @keyframes tmSlideUpMob {
        from { transform: translateY(100%); opacity: .7; }
        to   { transform: translateY(0); opacity: 1; }
    }
    .tmpmp-modal-header      { padding: 16px 48px 13px 16px; }
    .tmpmp-modal-header-icon { width: 38px; height: 38px; font-size: 18px; }
    .tmpmp-modal-title       { font-size: 15px; }
    .tmpmp-modal-body        { padding: 12px 12px 8px; }
    .tmpmp-modal-footer      { padding: 12px 14px; flex-wrap: wrap; }
    .tmpmp-modal-grid        { grid-template-columns: 1fr 1fr !important; }
    .tmpmp-modal-grid--2col  { grid-template-columns: 1fr !important; }
    .tmpmp-modal-toggles     { grid-template-columns: 1fr !important; }
    .tmpmp-modal-footer-hint { display: none; }
}
@media (max-width: 400px) {
    .tmpmp-modal-grid       { grid-template-columns: 1fr !important; }
    .tmpmp-modal-save-btn,
    .tmpmp-modal-cancel-btn { flex: 1; justify-content: center; }
}
</style>

<!-- Header action -->
<div style="margin-bottom:20px;">
    <button class="tmpmp-add-btn" id="tmpmp-add-plan-btn">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        <?php esc_html_e('Add New Plan','tempmail-pro'); ?>
    </button>
</div>

<!-- Plan Modal — rendered in browser top-layer via showModal(); always viewport-centred -->
<dialog id="tmpmp-plan-modal">
<div class="tmpmp-modal-box">

    <!-- Modal Header -->
    <div class="tmpmp-modal-header">
        <div class="tmpmp-modal-header-icon">💎</div>
        <div>
            <h2 class="tmpmp-modal-title" id="tmpmp-plan-modal-title"><?php esc_html_e('Add New Plan','tempmail-pro'); ?></h2>
            <p class="tmpmp-modal-subtitle"><?php esc_html_e('Configure pricing, limits and capabilities','tempmail-pro'); ?></p>
        </div>
        <button class="tmpmp-modal-close" id="tmpmp-plan-modal-close" title="<?php esc_attr_e('Close','tempmail-pro'); ?>">&#215;</button>
    </div>

    <!-- Modal Body -->
    <div class="tmpmp-modal-body">
    <input type="hidden" id="tmpmp-plan-id" name="id" value="0">
    <form id="tmpmp-plan-form">

        <!-- Basic Info -->
        <div class="tmpmp-modal-section">
            <p class="tmpmp-modal-section-title">📋 <?php esc_html_e('Basic Info','tempmail-pro'); ?></p>
            <div class="tmpmp-modal-grid">
                <label class="tmpmp-modal-label"><?php esc_html_e('Slug','tempmail-pro'); ?>
                    <input type="text" name="slug" id="pf-slug" placeholder="pro" required>
                </label>
                <label class="tmpmp-modal-label"><?php esc_html_e('Name','tempmail-pro'); ?>
                    <input type="text" name="name" id="pf-name" placeholder="Pro" required>
                </label>
                <label class="tmpmp-modal-label"><?php esc_html_e('Monthly ($)','tempmail-pro'); ?>
                    <input type="number" name="price_monthly" id="pf-price-monthly" value="9.99" step="0.01" min="0">
                </label>
                <label class="tmpmp-modal-label"><?php esc_html_e('Yearly ($)','tempmail-pro'); ?>
                    <input type="number" name="price_yearly" id="pf-price-yearly" value="79.99" step="0.01" min="0">
                </label>
                <label class="tmpmp-modal-label"><?php esc_html_e('Sort Order','tempmail-pro'); ?>
                    <input type="number" name="sort_order" value="0">
                </label>
            </div>
        </div>

        <!-- Limits -->
        <div class="tmpmp-modal-section">
            <p class="tmpmp-modal-section-title">⚙️ <?php esc_html_e('Limits','tempmail-pro'); ?></p>
            <div class="tmpmp-modal-grid">
                <label class="tmpmp-modal-label"><?php esc_html_e('Max Inboxes','tempmail-pro'); ?>
                    <input type="number" name="max_inboxes" id="pf-max-inboxes" value="10">
                </label>
                <label class="tmpmp-modal-label"><?php esc_html_e('Lifetime (min)','tempmail-pro'); ?>
                    <input type="number" name="inbox_lifetime" id="pf-lifetime" value="120">
                </label>
                <label class="tmpmp-modal-label"><?php esc_html_e('Refresh (sec)','tempmail-pro'); ?>
                    <input type="number" name="refresh_interval" value="10">
                </label>
                <label class="tmpmp-modal-label"><?php esc_html_e('Storage (MB)','tempmail-pro'); ?>
                    <input type="number" name="max_storage_mb" value="50">
                </label>
            </div>
        </div>

        <!-- Domains & Features -->
        <div class="tmpmp-modal-section">
            <p class="tmpmp-modal-section-title">🌐 <?php esc_html_e('Domains & Features','tempmail-pro'); ?></p>
            <div class="tmpmp-modal-grid tmpmp-modal-grid--2col">
                <label class="tmpmp-modal-label"><?php esc_html_e('Allowed Domain Categories (JSON)','tempmail-pro'); ?>
                    <input type="text" name="domains_allowed" value='["free"]' placeholder='["free","premium","vip"]'>
                </label>
                <label class="tmpmp-modal-label"><?php esc_html_e('Features (one per line)','tempmail-pro'); ?>
                    <textarea name="features" rows="4" placeholder="10 inboxes&#10;2hr lifetime&#10;No ads"></textarea>
                </label>
            </div>
        </div>

        <!-- Capabilities -->
        <div class="tmpmp-modal-section">
            <p class="tmpmp-modal-section-title">✅ <?php esc_html_e('Capabilities','tempmail-pro'); ?></p>
            <div class="tmpmp-modal-toggles">
                <label class="tmpmp-toggle-item">
                    <input type="checkbox" name="has_custom_user" value="1">
                    <span class="tmpmp-toggle-track"></span>
                    <span class="tmpmp-toggle-label"><?php esc_html_e('Custom Username','tempmail-pro'); ?></span>
                </label>
                <label class="tmpmp-toggle-item">
                    <input type="checkbox" name="has_api_access" value="1">
                    <span class="tmpmp-toggle-track"></span>
                    <span class="tmpmp-toggle-label"><?php esc_html_e('API Access','tempmail-pro'); ?></span>
                </label>
                <label class="tmpmp-toggle-item">
                    <input type="checkbox" name="has_attachments" value="1">
                    <span class="tmpmp-toggle-track"></span>
                    <span class="tmpmp-toggle-label"><?php esc_html_e('Attachments','tempmail-pro'); ?></span>
                </label>
                <label class="tmpmp-toggle-item">
                    <input type="checkbox" name="no_ads" value="1">
                    <span class="tmpmp-toggle-track"></span>
                    <span class="tmpmp-toggle-label"><?php esc_html_e('No Ads','tempmail-pro'); ?></span>
                </label>
                <label class="tmpmp-toggle-item">
                    <input type="checkbox" name="is_active" value="1" checked>
                    <span class="tmpmp-toggle-track"></span>
                    <span class="tmpmp-toggle-label"><?php esc_html_e('Active','tempmail-pro'); ?></span>
                </label>
            </div>
        </div>

        <!-- Premium Features -->
        <div class="tmpmp-modal-section">
            <p class="tmpmp-modal-section-title">&#11088; <?php esc_html_e('Premium Features','tempmail-pro'); ?></p>
            <div class="tmpmp-modal-toggles">
                <label class="tmpmp-toggle-item">
                    <input type="checkbox" name="has_premium_domains" value="1">
                    <span class="tmpmp-toggle-track"></span>
                    <span class="tmpmp-toggle-label"><?php esc_html_e('Premium Domains Access','tempmail-pro'); ?></span>
                </label>
                <label class="tmpmp-toggle-item">
                    <input type="checkbox" name="has_premium_storage" value="1">
                    <span class="tmpmp-toggle-track"></span>
                    <span class="tmpmp-toggle-label"><?php esc_html_e('Premium Inbox Storage','tempmail-pro'); ?></span>
                </label>
                <label class="tmpmp-toggle-item">
                    <input type="checkbox" name="has_custom_branding" value="1">
                    <span class="tmpmp-toggle-track"></span>
                    <span class="tmpmp-toggle-label"><?php esc_html_e('Custom Branding','tempmail-pro'); ?></span>
                </label>
                <label class="tmpmp-toggle-item">
                    <input type="checkbox" name="has_inbox_retention" value="1">
                    <span class="tmpmp-toggle-track"></span>
                    <span class="tmpmp-toggle-label"><?php esc_html_e('Dedicated Inbox Retention','tempmail-pro'); ?></span>
                </label>
                <label class="tmpmp-toggle-item">
                    <input type="checkbox" name="has_vip_domains" value="1">
                    <span class="tmpmp-toggle-track"></span>
                    <span class="tmpmp-toggle-label"><?php esc_html_e('VIP Domains &amp; Reserved Usernames','tempmail-pro'); ?></span>
                </label>
                <label class="tmpmp-toggle-item">
                    <input type="checkbox" name="has_unlimited_attachments" value="1">
                    <span class="tmpmp-toggle-track"></span>
                    <span class="tmpmp-toggle-label"><?php esc_html_e('Unlimited Attachment Downloads','tempmail-pro'); ?></span>
                </label>
                <label class="tmpmp-toggle-item">
                    <input type="checkbox" name="has_email_forwarding" value="1">
                    <span class="tmpmp-toggle-track"></span>
                    <span class="tmpmp-toggle-label"><?php esc_html_e('Email Forwarding','tempmail-pro'); ?></span>
                </label>
                <label class="tmpmp-toggle-item">
                    <input type="checkbox" name="has_alias_management" value="1">
                    <span class="tmpmp-toggle-track"></span>
                    <span class="tmpmp-toggle-label"><?php esc_html_e('Disposable Alias Management','tempmail-pro'); ?></span>
                </label>
                <label class="tmpmp-toggle-item">
                    <input type="checkbox" name="has_advanced_spam" value="1">
                    <span class="tmpmp-toggle-track"></span>
                    <span class="tmpmp-toggle-label"><?php esc_html_e('Advanced Spam Filtering','tempmail-pro'); ?></span>
                </label>
                <label class="tmpmp-toggle-item">
                    <input type="checkbox" name="has_custom_domain" value="1">
                    <span class="tmpmp-toggle-track"></span>
                    <span class="tmpmp-toggle-label"><?php esc_html_e('Custom Domain (DNS Wizard)','tempmail-pro'); ?></span>
                </label>
            </div>
        </div>

    </form>
    </div><!-- /.tmpmp-modal-body -->

    <!-- Modal Footer -->
    <div class="tmpmp-modal-footer">
        <button type="button" class="tmpmp-modal-save-btn" id="tmpmp-save-plan-btn">
            💾 <?php esc_html_e('Save Plan','tempmail-pro'); ?>
        </button>
        <button type="button" class="tmpmp-modal-cancel-btn" id="tmpmp-plan-cancel">&#215; <?php esc_html_e('Close','tempmail-pro'); ?></button>
        <span class="tmpmp-modal-footer-hint"><?php esc_html_e('Changes saved immediately.','tempmail-pro'); ?></span>
    </div>

</div>
</dialog>

<!-- Plans Table -->
<div class="tmpmp-page-card">
    <p class="tmpmp-page-section-title">💎 <?php esc_html_e('All Plans','tempmail-pro'); ?></p>
    <div class="tmpmp-table-wrap" style="overflow-x:auto;">
    <table class="tmpmp-styled-table">
    <thead><tr>
        <th><?php esc_html_e('ID','tempmail-pro'); ?></th>
        <th><?php esc_html_e('Plan','tempmail-pro'); ?></th>
        <th><?php esc_html_e('Monthly','tempmail-pro'); ?></th>
        <th><?php esc_html_e('Yearly','tempmail-pro'); ?></th>
        <th><?php esc_html_e('Inboxes','tempmail-pro'); ?></th>
        <th><?php esc_html_e('Lifetime','tempmail-pro'); ?></th>
        <th><?php esc_html_e('Features','tempmail-pro'); ?></th>
        <th><?php esc_html_e('Status','tempmail-pro'); ?></th>
        <th><?php esc_html_e('Actions','tempmail-pro'); ?></th>
    </tr></thead>
    <tbody>
    <?php foreach( $plans as $p ): ?>
    <tr id="plan-row-<?php echo intval($p->id); ?>"
        data-slug="<?php echo esc_attr($p->slug); ?>"
        data-monthly="<?php echo esc_attr($p->price_monthly); ?>"
        data-yearly="<?php echo esc_attr($p->price_yearly); ?>"
        data-max-inboxes="<?php echo esc_attr($p->max_inboxes); ?>"
        data-lifetime="<?php echo esc_attr($p->inbox_lifetime); ?>">
        <td data-label="ID" style="color:#94a3b8;font-size:12px;">#<?php echo intval($p->id); ?></td>
        <td data-label="Plan">
            <strong class="plan-name" style="display:block;"><?php echo esc_html($p->name); ?></strong>
            <code style="font-size:11px;background:#f1f5f9;padding:1px 5px;border-radius:3px;color:#6366f1;"><?php echo esc_html($p->slug); ?></code>
        </td>
        <td data-label="Monthly"><strong>$<?php echo number_format($p->price_monthly,2); ?></strong><span style="color:#94a3b8;font-size:11px;">/mo</span></td>
        <td data-label="Yearly"><strong>$<?php echo number_format($p->price_yearly,2); ?></strong><span style="color:#94a3b8;font-size:11px;">/yr</span></td>
        <td data-label="Inboxes"><?php echo $p->max_inboxes == -1 ? '&#8734;' : intval($p->max_inboxes); ?></td>
        <td data-label="Lifetime"><?php echo intval($p->inbox_lifetime); ?> min</td>
        <td data-label="Features" style="font-size:12px;max-width:180px;color:#64748b;">
            <?php
            $feats = json_decode($p->features ?? '[]', true) ?: [];
            echo esc_html(implode(', ', array_slice($feats, 0, 3)));
            if(count($feats) > 3) echo '&hellip;';
            ?>
        </td>
        <td data-label="Status"><span class="tmpmp-badge <?php echo $p->is_active ? 'tmpmp-badge--green' : 'tmpmp-badge--red'; ?>"><?php echo $p->is_active ? esc_html__('Active','tempmail-pro') : esc_html__('Inactive','tempmail-pro'); ?></span></td>
        <td data-label="Actions" style="white-space:nowrap;">
            <button class="tmpmp-icon-btn tmpmp-edit-plan" data-id="<?php echo intval($p->id); ?>" data-plan='<?php echo esc_attr(json_encode($p)); ?>'>
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                <?php esc_html_e('Edit','tempmail-pro'); ?>
            </button>
            <?php if($p->slug !== 'free'): ?>
            <button class="tmpmp-icon-btn tmpmp-icon-btn--danger tmpmp-delete-plan" data-id="<?php echo intval($p->id); ?>" style="margin-left:4px;">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/></svg>
                <?php esc_html_e('Delete','tempmail-pro'); ?>
            </button>
            <?php endif; ?>
        </td>
    </tr>
    <?php endforeach; ?>
    <?php if(empty($plans)): ?><tr class="tmpmp-empty-row"><td colspan="9"><?php esc_html_e('No plans yet.','tempmail-pro'); ?></td></tr><?php endif; ?>
    </tbody>
    </table>
    </div>
</div>


</div><!-- /.wrap -->

<script>
jQuery(function($){
    var modal  = document.getElementById('tmpmp-plan-modal');
    var $modal = $(modal);

    function openModal()  { modal.showModal(); } // top-layer — always viewport-centred
    function closeModal() { modal.close(); }

    // Open Add modal
    $('#tmpmp-add-plan-btn').on('click', function(){
        $modal.find('#tmpmp-plan-form')[0].reset();
        $modal.find('#tmpmp-plan-id').val('0');
        $modal.find('#tmpmp-plan-modal-title').text('<?php esc_html_e('Add New Plan','tempmail-pro'); ?>');
        $modal.find('.tmpmp-modal-subtitle').text('<?php esc_html_e('Configure pricing, limits and capabilities','tempmail-pro'); ?>');
        $modal.find('.tmpmp-modal-header-badges').remove();
        openModal();
    });

    // Close via button
    $modal.on('click', '#tmpmp-plan-modal-close, #tmpmp-plan-cancel', function(){
        closeModal();
    });

    // Close on backdrop click (dialog is now the card; backdrop clicks land on dialog
    // element but with coordinates outside its bounding rect)
    $modal.on('click', function(e){
        var r = modal.getBoundingClientRect();
        if (e.clientX < r.left || e.clientX > r.right ||
            e.clientY < r.top  || e.clientY > r.bottom) {
            closeModal();
        }
    });


    // Escape key handled natively by <dialog>
    $modal.on('cancel', function(){});

    // Edit plan
    $(document).on('click', '.tmpmp-edit-plan', function(){
        const p = $(this).data('plan');
        $modal.find('#tmpmp-plan-id').val(p.id);
        $modal.find('[name="slug"]').val(p.slug);
        $modal.find('[name="name"]').val(p.name);
        $modal.find('[name="price_monthly"]').val(p.price_monthly);
        $modal.find('[name="price_yearly"]').val(p.price_yearly);
        $modal.find('[name="max_inboxes"]').val(p.max_inboxes);
        $modal.find('[name="inbox_lifetime"]').val(p.inbox_lifetime);
        $modal.find('[name="refresh_interval"]').val(p.refresh_interval);
        $modal.find('[name="max_storage_mb"]').val(p.max_storage_mb);
        $modal.find('[name="sort_order"]').val(p.sort_order);
        $modal.find('[name="domains_allowed"]').val(p.domains_allowed);
        try { $modal.find('[name="features"]').val(JSON.parse(p.features||'[]').join('\n')); } catch(e){}
        $modal.find('[name="has_custom_user"]').prop('checked',            !!+p.has_custom_user);
        $modal.find('[name="has_api_access"]').prop('checked',             !!+p.has_api_access);
        $modal.find('[name="has_attachments"]').prop('checked',            !!+p.has_attachments);
        $modal.find('[name="no_ads"]').prop('checked',                     !!+p.no_ads);
        $modal.find('[name="is_active"]').prop('checked',                  !!+p.is_active);
        $modal.find('[name="has_premium_domains"]').prop('checked',        !!+p.has_premium_domains);
        $modal.find('[name="has_premium_storage"]').prop('checked',        !!+p.has_premium_storage);
        $modal.find('[name="has_custom_branding"]').prop('checked',        !!+p.has_custom_branding);
        $modal.find('[name="has_inbox_retention"]').prop('checked',        !!+p.has_inbox_retention);
        $modal.find('[name="has_vip_domains"]').prop('checked',            !!+p.has_vip_domains);
        $modal.find('[name="has_unlimited_attachments"]').prop('checked',  !!+p.has_unlimited_attachments);
        $modal.find('[name="has_email_forwarding"]').prop('checked',       !!+p.has_email_forwarding);
        $modal.find('[name="has_alias_management"]').prop('checked',       !!+p.has_alias_management);
        $modal.find('[name="has_advanced_spam"]').prop('checked',          !!+p.has_advanced_spam);
        $modal.find('[name="has_custom_domain"]').prop('checked',          !!+p.has_custom_domain);
        // Title & badges
        $modal.find('#tmpmp-plan-modal-title').text('<?php esc_html_e('Edit Plan','tempmail-pro'); ?>: ' + p.name);
        $modal.find('.tmpmp-modal-subtitle').text('<?php esc_html_e('Configure pricing, limits and capabilities','tempmail-pro'); ?>');
        $modal.find('.tmpmp-modal-header-badges').remove();
        const statusCls = !!+p.is_active ? 'tmpmp-modal-badge--green' : 'tmpmp-modal-badge--slate';
        const statusTxt = !!+p.is_active ? '🟢 <?php esc_html_e('Active','tempmail-pro'); ?>' : '⚫ <?php esc_html_e('Inactive','tempmail-pro'); ?>';
        const badges = $('<div class="tmpmp-modal-header-badges">')
            .append('<span class="tmpmp-modal-badge tmpmp-modal-badge--purple">🏷️ ' + p.slug + '</span>')
            .append('<span class="tmpmp-modal-badge tmpmp-modal-badge--slate">💰 $' + parseFloat(p.price_monthly).toFixed(2) + '/mo</span>')
            .append('<span class="tmpmp-modal-badge ' + statusCls + '">' + statusTxt + '</span>');
        $modal.find('.tmpmp-modal-subtitle').after(badges);
        openModal();
    });
});
</script>
