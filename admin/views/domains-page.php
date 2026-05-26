<?php if ( ! defined('ABSPATH') ) exit; ?>
<div class="wrap tmpmp-admin-wrap">
<h1 class="tmpmp-admin-title"><span class="dashicons dashicons-admin-site"></span> <?php esc_html_e('Domain Management','tempmail-pro'); ?> <span class="tmpmp-version-pill">v<?php echo esc_html(TMPMP_VERSION); ?></span></h1>

<style>
/* ── Base ─────────────────────────────────────────────────────────────────── */
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
.tmpmp-styled-table{width:100%;border-collapse:collapse;font-size:13px;}
.tmpmp-styled-table th{background:#f8fafc;color:#475569;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;padding:10px 14px;text-align:left;border-bottom:2px solid #e2e8f0;}
.tmpmp-styled-table td{padding:12px 14px;border-bottom:1px solid #f1f5f9;color:#334155;vertical-align:middle;}
.tmpmp-styled-table tr:last-child td{border-bottom:none;}
.tmpmp-styled-table tr:hover td{background:#fafbff;}
.tmpmp-icon-btn{display:inline-flex;align-items:center;gap:5px;padding:5px 10px;border-radius:6px;font-size:12px;font-weight:600;cursor:pointer;border:1.5px solid #e2e8f0;background:#fff;transition:all .15s;white-space:nowrap;}
.tmpmp-icon-btn:hover{border-color:#6366f1;color:#6366f1;}
.tmpmp-icon-btn--danger:hover{border-color:#ef4444;color:#ef4444;}
.tmpmp-icon-btn--verify{border-color:#0ea5e9;color:#0369a1;}
.tmpmp-icon-btn--verify:hover{background:#e0f2fe;border-color:#0369a1;}
.tmpmp-add-btn{display:inline-flex;align-items:center;gap:8px;padding:9px 18px;background:linear-gradient(135deg,#6366f1,#8b5cf6);color:#fff;border:none;border-radius:8px;font-size:13px;font-weight:700;cursor:pointer;font-family:inherit;transition:all .15s;}
.tmpmp-add-btn:hover{opacity:.9;transform:translateY(-1px);box-shadow:0 4px 12px rgba(99,102,241,.3);}
.tmpmp-verify-all-btn{display:inline-flex;align-items:center;gap:8px;padding:9px 18px;background:linear-gradient(135deg,#0ea5e9,#6366f1);color:#fff;border:none;border-radius:8px;font-size:13px;font-weight:700;cursor:pointer;font-family:inherit;transition:all .15s;}
.tmpmp-verify-all-btn:hover{opacity:.9;transform:translateY(-1px);box-shadow:0 4px 12px rgba(14,165,233,.3);}
.tmpmp-empty-row td{text-align:center;padding:32px!important;color:#94a3b8;}
/* ── Health badges ─────────────────────────────────────────────────────────── */
.tmpmp-health-badge{display:inline-flex;align-items:center;gap:5px;padding:4px 12px;border-radius:20px;font-size:11px;font-weight:700;white-space:nowrap;}
.tmpmp-health-healthy{background:#dcfce7;color:#16a34a;}
.tmpmp-health-warning{background:#fef9c3;color:#ca8a04;}
.tmpmp-health-error  {background:#fee2e2;color:#dc2626;}
.tmpmp-health-unknown{background:#f1f5f9;color:#94a3b8;}
@keyframes tmpmp-spin{to{transform:rotate(360deg);}}
.tmpmp-spin{display:inline-block;animation:tmpmp-spin .7s linear infinite;}

/* ── DNS Modal Overlay ─────────────────────────────────────────────────────── */
#tmpmp-dns-modal-overlay{
    display:none;position:fixed;inset:0;z-index:999999;
    background:rgba(15,23,42,.55);backdrop-filter:blur(4px);
    align-items:center;justify-content:center;padding:16px;
    animation:tmpmp-fade-in .2s ease;
}
#tmpmp-dns-modal-overlay.is-open{display:flex;}
@keyframes tmpmp-fade-in{from{opacity:0;}to{opacity:1;}}

#tmpmp-dns-modal{
    background:#fff;border-radius:20px;width:100%;max-width:680px;
    max-height:90vh;display:flex;flex-direction:column;overflow:hidden;
    box-shadow:0 25px 60px rgba(15,23,42,.25),0 0 0 1px rgba(15,23,42,.06);
    animation:tmpmp-slide-up .25s cubic-bezier(.34,1.56,.64,1);
}
@keyframes tmpmp-slide-up{from{opacity:0;transform:translateY(28px) scale(.97);}to{opacity:1;transform:translateY(0) scale(1);}}

/* Modal Header */
#tmpmp-dns-modal-head{
    display:flex;align-items:flex-start;gap:14px;padding:22px 24px 18px;
    border-bottom:1px solid #f1f5f9;flex-shrink:0;
}
.tmpmp-dns-modal-head-icon{
    width:48px;height:48px;border-radius:14px;display:flex;align-items:center;
    justify-content:center;font-size:22px;flex-shrink:0;
}
.tmpmp-dns-modal-head-icon.healthy{background:linear-gradient(135deg,#dcfce7,#bbf7d0);}
.tmpmp-dns-modal-head-icon.warning{background:linear-gradient(135deg,#fef9c3,#fde68a);}
.tmpmp-dns-modal-head-icon.error  {background:linear-gradient(135deg,#fee2e2,#fecaca);}
.tmpmp-dns-modal-head-icon.loading{background:linear-gradient(135deg,#f1f5f9,#e2e8f0);}
.tmpmp-dns-modal-head-text{flex:1;min-width:0;}
.tmpmp-dns-modal-head-text h2{margin:0 0 4px;font-size:17px;font-weight:700;color:#0f172a;line-height:1.3;word-break:break-all;}
.tmpmp-dns-modal-head-text p{margin:0;font-size:13px;color:#64748b;}
#tmpmp-dns-modal-close{
    background:none;border:none;cursor:pointer;padding:6px;border-radius:8px;
    color:#94a3b8;font-size:20px;line-height:1;flex-shrink:0;transition:all .15s;
    display:flex;align-items:center;justify-content:center;width:32px;height:32px;
}
#tmpmp-dns-modal-close:hover{background:#f1f5f9;color:#334155;}

/* Overall status bar */
#tmpmp-dns-modal-status{
    padding:12px 24px;font-size:13px;font-weight:600;
    display:flex;align-items:center;gap:10px;flex-shrink:0;flex-wrap:wrap;
}
#tmpmp-dns-modal-status.healthy{background:linear-gradient(90deg,#f0fdf4,#fff);border-bottom:1px solid #dcfce7;color:#16a34a;}
#tmpmp-dns-modal-status.warning{background:linear-gradient(90deg,#fefce8,#fff);border-bottom:1px solid #fde68a;color:#ca8a04;}
#tmpmp-dns-modal-status.error  {background:linear-gradient(90deg,#fef2f2,#fff);border-bottom:1px solid #fecaca;color:#dc2626;}

/* MX record strip */
#tmpmp-dns-modal-mx{
    padding:10px 24px;background:#f8fafc;border-bottom:1px solid #f1f5f9;
    font-size:12px;color:#64748b;display:none;flex-wrap:wrap;gap:6px;align-items:center;
}
#tmpmp-dns-modal-mx strong{color:#0f172a;font-size:13px;}
#tmpmp-dns-modal-mx code{background:#e2e8f0;padding:2px 8px;border-radius:5px;font-size:12px;color:#334155;}

/* Checks list */
#tmpmp-dns-modal-checks{overflow-y:auto;flex:1;padding:8px 0;}
.tmpmp-dns-check-item{
    display:grid;grid-template-columns:44px 1fr;align-items:start;
    padding:14px 24px;border-bottom:1px solid #f8fafc;transition:background .12s;
}
.tmpmp-dns-check-item:last-child{border-bottom:none;}
.tmpmp-dns-check-item:hover{background:#fafbff;}
.tmpmp-dns-check-left{display:flex;flex-direction:column;align-items:center;gap:6px;padding-top:2px;}
.tmpmp-dns-status-dot{width:28px;height:28px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:14px;flex-shrink:0;}
.tmpmp-dns-status-dot.pass{background:#dcfce7;color:#16a34a;}
.tmpmp-dns-status-dot.fail{background:#fee2e2;color:#dc2626;}
.tmpmp-dns-status-dot.warn{background:#fef9c3;color:#ca8a04;}
.tmpmp-dns-status-dot.skip{background:#f1f5f9;color:#94a3b8;}
.tmpmp-dns-check-right{padding-left:4px;}
.tmpmp-dns-check-right h4{margin:0 0 4px;font-size:13px;font-weight:700;color:#0f172a;}
.tmpmp-dns-check-right p{margin:0;font-size:12px;color:#64748b;line-height:1.6;word-break:break-word;}
.tmpmp-dns-check-pill{display:inline-block;font-size:10px;font-weight:700;padding:2px 7px;border-radius:20px;margin-bottom:5px;}
.tmpmp-dns-check-pill.pass{background:#dcfce7;color:#16a34a;}
.tmpmp-dns-check-pill.fail{background:#fee2e2;color:#dc2626;}
.tmpmp-dns-check-pill.warn{background:#fef9c3;color:#ca8a04;}
.tmpmp-dns-check-pill.skip{background:#f1f5f9;color:#94a3b8;}

/* Loading state */
.tmpmp-dns-modal-loading{
    display:flex;flex-direction:column;align-items:center;justify-content:center;
    padding:60px 24px;color:#94a3b8;gap:16px;
}
.tmpmp-dns-modal-loading .tmpmp-dns-big-spin{
    width:48px;height:48px;border:4px solid #e2e8f0;border-top-color:#6366f1;
    border-radius:50%;animation:tmpmp-spin .7s linear infinite;
}
.tmpmp-dns-modal-loading p{margin:0;font-size:14px;}

/* Footer */
#tmpmp-dns-modal-foot{
    padding:16px 24px;border-top:1px solid #f1f5f9;display:flex;
    align-items:center;justify-content:space-between;flex-shrink:0;flex-wrap:wrap;gap:10px;
}
.tmpmp-dns-modal-meta{font-size:11px;color:#94a3b8;}
.tmpmp-dns-close-btn{padding:8px 20px;background:#f1f5f9;border:none;border-radius:8px;font-size:13px;font-weight:600;color:#334155;cursor:pointer;transition:all .15s;}
.tmpmp-dns-close-btn:hover{background:#e2e8f0;color:#0f172a;}

/* Responsive */
@media(max-width:600px){
    #tmpmp-dns-modal{border-radius:16px 16px 0 0;position:fixed;bottom:0;left:0;right:0;max-width:100%;max-height:85vh;animation:tmpmp-slide-up-mobile .25s ease;}
    @keyframes tmpmp-slide-up-mobile{from{transform:translateY(100%);}to{transform:translateY(0);}}
    #tmpmp-dns-modal-overlay{align-items:flex-end;padding:0;}
    #tmpmp-dns-modal-head{padding:18px 16px 14px;}
    .tmpmp-dns-check-item{padding:12px 16px;}
    #tmpmp-dns-modal-status,#tmpmp-dns-modal-mx,#tmpmp-dns-modal-foot{padding-left:16px;padding-right:16px;}
    .tmpmp-styled-table th:nth-child(4),.tmpmp-styled-table td:nth-child(4){display:none;}/* hide MX col on mobile */
    .tmpmp-page-field{grid-template-columns:1fr;gap:6px;}
    .tmpmp-page-label{padding-top:0;}
    .tmpmp-page-card{padding:16px 14px;}
}
</style>

<!-- ① Add Domain -->
<div class="tmpmp-page-card">
    <p class="tmpmp-page-section-title">➕ <?php esc_html_e('Add New Domain','tempmail-pro'); ?></p>
    <div class="tmpmp-page-field">
        <label class="tmpmp-page-label" for="tmpmp-new-domain"><?php esc_html_e('Domain Name','tempmail-pro'); ?></label>
        <div><input type="text" id="tmpmp-new-domain" class="tmpmp-page-input" placeholder="mail.example.com"></div>
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
                <?php esc_html_e('MX record must point to your mail server. Webhook endpoint:','tempmail-pro'); ?><br>
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
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;flex-wrap:wrap;gap:10px;">
        <p class="tmpmp-page-section-title" style="margin:0;">🌐 <?php esc_html_e('Active Domains','tempmail-pro'); ?></p>
        <?php if ( ! empty($domains) ) : ?>
        <button class="tmpmp-verify-all-btn" id="tmpmp-verify-all-btn">
            🔍 <?php esc_html_e('Verify All DNS','tempmail-pro'); ?>
        </button>
        <?php endif; ?>
    </div>
    <div style="overflow-x:auto;">
    <table class="tmpmp-styled-table">
        <thead><tr>
            <th><?php esc_html_e('Domain','tempmail-pro'); ?></th>
            <th><?php esc_html_e('Category','tempmail-pro'); ?></th>
            <th><?php esc_html_e('Emails','tempmail-pro'); ?></th>
            <th><?php esc_html_e('MX Record','tempmail-pro'); ?></th>
            <th><?php esc_html_e('Health','tempmail-pro'); ?></th>
            <th><?php esc_html_e('Last Checked','tempmail-pro'); ?></th>
            <th><?php esc_html_e('Active','tempmail-pro'); ?></th>
            <th><?php esc_html_e('Actions','tempmail-pro'); ?></th>
        </tr></thead>
        <tbody>
        <?php if ( empty($domains) ): ?>
        <tr class="tmpmp-empty-row"><td colspan="8"><?php esc_html_e('No domains yet. Add your first domain above.','tempmail-pro'); ?></td></tr>
        <?php else: foreach($domains as $d): ?>
        <?php
        $hs  = $d->health_status ?? 'unknown';
        $cls = match($hs) { 'healthy'=>'healthy','warning'=>'warning','error'=>'error', default=>'unknown' };
        $lbl = match($hs) { 'healthy'=>'✅ Healthy','warning'=>'⚠️ Warning','error'=>'❌ Error', default=>'— Unknown' };
        ?>
        <tr id="domain-row-<?php echo intval($d->id); ?>">
            <td><strong><?php echo esc_html($d->domain); ?></strong></td>
            <td>
                <select class="tmpmp-page-select tmpmp-domain-category" data-id="<?php echo intval($d->id); ?>" style="min-width:110px;">
                    <option value="free"    <?php selected($d->category,'free');    ?>>🆓 Free</option>
                    <option value="premium" <?php selected($d->category,'premium'); ?>>⭐ Premium</option>
                    <option value="vip"     <?php selected($d->category,'vip');     ?>>💎 VIP</option>
                </select>
            </td>
            <td><?php echo number_format($d->emails_count); ?></td>
            <td style="font-size:12px;color:#64748b;max-width:160px;word-break:break-all;" id="mx-cell-<?php echo intval($d->id); ?>">
                <?php echo $d->mx_record ? esc_html($d->mx_record) : '<span style="color:#cbd5e1;">—</span>'; ?>
            </td>
            <td>
                <span class="tmpmp-health-badge tmpmp-health-<?php echo $cls; ?>" id="health-badge-<?php echo intval($d->id); ?>"><?php echo $lbl; ?></span>
            </td>
            <td style="font-size:11px;color:#94a3b8;white-space:nowrap;" id="last-checked-<?php echo intval($d->id); ?>">
                <?php echo $d->last_checked ? esc_html( date_i18n('M j, Y H:i', strtotime($d->last_checked) ) ) : '—'; ?>
            </td>
            <td>
                <label class="tmpmp-toggle-label">
                    <input type="checkbox" class="tmpmp-domain-status" data-id="<?php echo intval($d->id); ?>" <?php checked($d->is_active, 1); ?>>
                    <span class="tmpmp-toggle-slider"></span>
                </label>
            </td>
            <td>
                <div style="display:flex;gap:6px;flex-wrap:wrap;">
                    <button class="tmpmp-icon-btn tmpmp-icon-btn--verify tmpmp-verify-dns-btn"
                            data-id="<?php echo intval($d->id); ?>"
                            data-domain="<?php echo esc_attr($d->domain); ?>">
                        🔍 <?php esc_html_e('Verify DNS','tempmail-pro'); ?>
                    </button>
                    <button class="tmpmp-icon-btn tmpmp-icon-btn--danger tmpmp-delete-domain" data-id="<?php echo intval($d->id); ?>">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/><path d="M10 11v6M14 11v6"/></svg>
                        <?php esc_html_e('Delete','tempmail-pro'); ?>
                    </button>
                </div>
            </td>
        </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>
    </div>
</div>

<!-- ③ DNS Guide -->
<div class="tmpmp-page-card">
    <p class="tmpmp-page-section-title">📋 <?php esc_html_e('DNS Configuration Guide','tempmail-pro'); ?></p>
    <div class="tmpmp-page-field">
        <label class="tmpmp-page-label">MX Record</label>
        <div style="padding-top:8px;font-size:13px;color:#475569;">
            <?php esc_html_e('Points incoming emails to your mail server. Required for receiving email.','tempmail-pro'); ?><br>
            <code style="display:block;margin-top:6px;background:#f1f5f9;padding:8px 12px;border-radius:6px;font-size:12px;">example.com &nbsp; IN &nbsp; MX &nbsp; 10 &nbsp; mail.yourserver.com</code>
        </div>
    </div>
    <div class="tmpmp-page-field">
        <label class="tmpmp-page-label">SPF Record</label>
        <div style="padding-top:8px;font-size:13px;color:#475569;">
            <?php esc_html_e('Prevents spoofing. Recommended for deliverability.','tempmail-pro'); ?><br>
            <code style="display:block;margin-top:6px;background:#f1f5f9;padding:8px 12px;border-radius:6px;font-size:12px;">example.com &nbsp; IN &nbsp; TXT &nbsp; "v=spf1 mx ~all"</code>
        </div>
    </div>
    <div class="tmpmp-page-field">
        <label class="tmpmp-page-label">DMARC Record</label>
        <div style="padding-top:8px;font-size:13px;color:#475569;">
            <?php esc_html_e('Email authentication policy. Improves trust with major providers.','tempmail-pro'); ?><br>
            <code style="display:block;margin-top:6px;background:#f1f5f9;padding:8px 12px;border-radius:6px;font-size:12px;">_dmarc.example.com &nbsp; IN &nbsp; TXT &nbsp; "v=DMARC1; p=none; rua=mailto:dmarc@example.com"</code>
        </div>
    </div>
</div>

<!-- ④ DNS Result Modal (global, single instance) -->
<div id="tmpmp-dns-modal-overlay" role="dialog" aria-modal="true" aria-labelledby="tmpmp-dns-modal-title">
    <div id="tmpmp-dns-modal">

        <!-- Header -->
        <div id="tmpmp-dns-modal-head">
            <div class="tmpmp-dns-modal-head-icon loading" id="tmpmp-dns-modal-icon">⏳</div>
            <div class="tmpmp-dns-modal-head-text">
                <h2 id="tmpmp-dns-modal-title">DNS Verification</h2>
                <p id="tmpmp-dns-modal-subtitle">Checking records…</p>
            </div>
            <button id="tmpmp-dns-modal-close" aria-label="Close">✕</button>
        </div>

        <!-- Status bar -->
        <div id="tmpmp-dns-modal-status" style="display:none;"></div>

        <!-- MX strip -->
        <div id="tmpmp-dns-modal-mx"></div>

        <!-- Checks list -->
        <div id="tmpmp-dns-modal-checks">
            <div class="tmpmp-dns-modal-loading">
                <div class="tmpmp-dns-big-spin"></div>
                <p><?php esc_html_e('Running DNS checks…','tempmail-pro'); ?></p>
            </div>
        </div>

        <!-- Footer -->
        <div id="tmpmp-dns-modal-foot">
            <span class="tmpmp-dns-modal-meta" id="tmpmp-dns-modal-time"></span>
            <button class="tmpmp-dns-close-btn" id="tmpmp-dns-modal-close-btn"><?php esc_html_e('Close','tempmail-pro'); ?></button>
        </div>
    </div>
</div>

<script>
jQuery(function($){
    var nonce = (typeof TempMailAdmin !== 'undefined' ? TempMailAdmin.nonce : '') || (typeof TempMailProAdmin !== 'undefined' ? TempMailProAdmin.nonce : '');
    var url   = (typeof TempMailAdmin !== 'undefined' ? TempMailAdmin.ajax_url : '') || ajaxurl || '';

    // ── Add Domain ────────────────────────────────────────────────────────────
    $('#tmpmp-add-domain-btn').on('click', function(){
        var domain = $('#tmpmp-new-domain').val().trim();
        var category = $('#tmpmp-new-category').val();
        if(!domain){ alert('<?php esc_html_e('Please enter a domain.','tempmail-pro'); ?>'); return; }
        var $btn = $(this).prop('disabled',true).text('Adding…');
        $.post(url,{action:'tmpmp_add_domain',nonce,domain,category},function(r){
            if(r.success) location.reload();
            else{ alert(r.data?.message||'Failed'); $btn.prop('disabled',false).text('Add Domain'); }
        });
    });

    // ── Category / Active ─────────────────────────────────────────────────────
    $(document).on('change','.tmpmp-domain-category',function(){
        $.post(url,{action:'tmpmp_update_domain',nonce,id:$(this).data('id'),category:$(this).val()});
    });
    $(document).on('change','.tmpmp-domain-status',function(){
        $.post(url,{action:'tmpmp_update_domain',nonce,id:$(this).data('id'),is_active:$(this).is(':checked')?1:0});
    });

    // ── Delete ────────────────────────────────────────────────────────────────
    $(document).on('click','.tmpmp-delete-domain',function(){
        if(!confirm('<?php esc_html_e('Delete this domain?','tempmail-pro'); ?>')) return;
        var id=$(this).data('id');
        $.post(url,{action:'tmpmp_delete_domain',nonce,id},function(r){
            if(r.success) $('#domain-row-'+id).fadeOut(300,function(){$(this).remove();});
        });
    });

    // ─────────────────────────────────────────────────────────────────────────
    // DNS Result Modal
    // ─────────────────────────────────────────────────────────────────────────
    var $overlay = $('#tmpmp-dns-modal-overlay');
    var $modal   = $('#tmpmp-dns-modal');

    function openModal(domain){
        $('#tmpmp-dns-modal-icon').attr('class','tmpmp-dns-modal-head-icon loading').text('🔍');
        $('#tmpmp-dns-modal-title').text(domain);
        $('#tmpmp-dns-modal-subtitle').text('<?php esc_html_e('Checking DNS records…','tempmail-pro'); ?>');
        $('#tmpmp-dns-modal-status').hide().text('').attr('class','');
        $('#tmpmp-dns-modal-mx').hide().text('');
        $('#tmpmp-dns-modal-checks').html(
            '<div class="tmpmp-dns-modal-loading">'
          + '<div class="tmpmp-dns-big-spin"></div>'
          + '<p><?php esc_html_e('Running DNS checks…','tempmail-pro'); ?></p>'
          + '</div>'
        );
        $('#tmpmp-dns-modal-time').text('');
        $overlay.addClass('is-open');
        $('body').css('overflow','hidden');
    }

    function closeModal(){
        $overlay.removeClass('is-open');
        $('body').css('overflow','');
    }

    $('#tmpmp-dns-modal-close, #tmpmp-dns-modal-close-btn').on('click', closeModal);
    $overlay.on('click', function(e){ if($(e.target).is($overlay)) closeModal(); });
    $(document).on('keydown', function(e){ if(e.key==='Escape') closeModal(); });

    function statusMeta(overall){
        var map={
            healthy:{icon:'✅',label:'<?php esc_html_e('All checks passed','tempmail-pro'); ?>'},
            warning:{icon:'⚠️',label:'<?php esc_html_e('Some warnings found','tempmail-pro'); ?>'},
            error  :{icon:'❌',label:'<?php esc_html_e('Critical issues detected','tempmail-pro'); ?>'}
        };
        return map[overall]||{icon:'?',label:overall};
    }

    function dotIcon(status){
        var map={pass:'✓',fail:'✗',warn:'!',skip:'—'};
        return map[status]||'?';
    }

    function pillLabel(status){
        var map={pass:'PASS',fail:'FAIL',warn:'WARN',skip:'SKIP'};
        return map[status]||status.toUpperCase();
    }

    function renderModal(data, rowId){
        var meta = statusMeta(data.overall);

        // Icon + head
        $('#tmpmp-dns-modal-icon')
            .attr('class','tmpmp-dns-modal-head-icon '+data.overall)
            .text(meta.icon);
        $('#tmpmp-dns-modal-subtitle').text(meta.label+' · '+escHtml(data.summary));

        // Status bar
        $('#tmpmp-dns-modal-status')
            .attr('class', data.overall)
            .html('<span style="font-size:18px;">'+meta.icon+'</span>'
                + '<span>'+meta.label+'</span>'
                + '<span style="opacity:.6;font-weight:400;margin-left:auto;">'+escHtml(data.summary)+'</span>')
            .show();

        // MX strip
        if(data.mx_record){
            $('#tmpmp-dns-modal-mx')
                .html('📬 <strong>Primary MX:</strong> <code>'+escHtml(data.mx_record)+'</code>')
                .css('display','flex');
        } else {
            $('#tmpmp-dns-modal-mx').hide();
        }

        // Check rows
        var html='';
        $.each(data.checks,function(_,c){
            html+='<div class="tmpmp-dns-check-item">'
                +'<div class="tmpmp-dns-check-left">'
                +'<div class="tmpmp-dns-status-dot '+c.status+'">'+dotIcon(c.status)+'</div>'
                +'</div>'
                +'<div class="tmpmp-dns-check-right">'
                +'<div class="tmpmp-dns-check-pill '+c.status+'">'+pillLabel(c.status)+'</div>'
                +'<h4>'+escHtml(c.name)+'</h4>'
                +'<p>'+escHtml(c.detail)+'</p>'
                +'</div>'
                +'</div>';
        });
        $('#tmpmp-dns-modal-checks').html(html);

        // Time
        var now=new Date();
        $('#tmpmp-dns-modal-time').text('<?php esc_html_e('Checked','tempmail-pro'); ?> '
            +now.toLocaleTimeString(undefined,{hour:'2-digit',minute:'2-digit'}));

        // Update table row
        if(rowId){
            var cls={healthy:'tmpmp-health-healthy',warning:'tmpmp-health-warning',error:'tmpmp-health-error'}[data.overall]||'tmpmp-health-unknown';
            var lbl={healthy:'✅ Healthy',warning:'⚠️ Warning',error:'❌ Error'}[data.overall]||'— Unknown';
            $('#health-badge-'+rowId).attr('class','tmpmp-health-badge '+cls).text(lbl);
            $('#mx-cell-'+rowId).text(data.mx_record||'—');
            $('#last-checked-'+rowId).text(now.toLocaleDateString(undefined,{month:'short',day:'numeric',year:'numeric'})+' '+now.toLocaleTimeString(undefined,{hour:'2-digit',minute:'2-digit'}));
        }
    }

    // ── Single Verify ─────────────────────────────────────────────────────────
    $(document).on('click','.tmpmp-verify-dns-btn',function(){
        var id     = $(this).data('id');
        var domain = $(this).data('domain');
        var $btn   = $(this).prop('disabled',true);
        // Spin badge
        $('#health-badge-'+id).attr('class','tmpmp-health-badge tmpmp-health-unknown')
            .html('<span class="tmpmp-spin">⟳</span>');
        openModal(domain);
        $.post(url,{action:'tmpmp_verify_domain_dns',nonce,id},function(r){
            $btn.prop('disabled',false);
            if(r.success){ renderModal(r.data, id); }
            else{
                $('#health-badge-'+id).attr('class','tmpmp-health-badge tmpmp-health-error').text('❌ Error');
                $('#tmpmp-dns-modal-checks').html('<div style="padding:32px;text-align:center;color:#ef4444;">❌ '+escHtml(r.data?.message||'Verification failed')+'</div>');
            }
        });
    });

    // ── Verify All ────────────────────────────────────────────────────────────
    $('#tmpmp-verify-all-btn').on('click',function(){
        var $btn=$(this).prop('disabled',true).html('<span class="tmpmp-spin">⟳</span> <?php esc_html_e('Verifying…','tempmail-pro'); ?>');
        // Spin all badges
        $('.tmpmp-styled-table tbody tr[id]').each(function(){
            var id=$(this).attr('id').replace('domain-row-','');
            $('#health-badge-'+id).attr('class','tmpmp-health-badge tmpmp-health-unknown').html('<span class="tmpmp-spin">⟳</span>');
        });
        openModal('<?php esc_html_e('All Domains','tempmail-pro'); ?>');
        $('#tmpmp-dns-modal-title').text('<?php esc_html_e('Verifying All Domains','tempmail-pro'); ?>');

        $.post(url,{action:'tmpmp_verify_all_dns',nonce},function(r){
            $btn.prop('disabled',false).html('🔍 <?php esc_html_e('Verify All DNS','tempmail-pro'); ?>');
            if(!r.success){ alert('Failed'); closeModal(); return; }

            // Update all rows
            var results=r.data.results;
            var pass=0,warn=0,fail=0;
            $('.tmpmp-styled-table tbody tr[id]').each(function(){
                var rowId=$(this).attr('id').replace('domain-row-','');
                var domain=$(this).find('td:first strong').text().trim();
                if(results[domain]){
                    var d=results[domain];
                    var cls={healthy:'tmpmp-health-healthy',warning:'tmpmp-health-warning',error:'tmpmp-health-error'}[d.overall]||'tmpmp-health-unknown';
                    var lbl={healthy:'✅ Healthy',warning:'⚠️ Warning',error:'❌ Error'}[d.overall]||'— Unknown';
                    $('#health-badge-'+rowId).attr('class','tmpmp-health-badge '+cls).text(lbl);
                    $('#mx-cell-'+rowId).text(d.mx_record||'—');
                    var now=new Date();
                    $('#last-checked-'+rowId).text(now.toLocaleDateString(undefined,{month:'short',day:'numeric',year:'numeric'})+' '+now.toLocaleTimeString(undefined,{hour:'2-digit',minute:'2-digit'}));
                    if(d.overall==='healthy') pass++;
                    else if(d.overall==='warning') warn++;
                    else fail++;
                }
            });

            // Show summary in modal
            var totalOverall = fail>0?'error':(warn>0?'warning':'healthy');
            var summaryData={
                domain:'<?php esc_html_e('All Domains','tempmail-pro'); ?>',
                overall:totalOverall,
                mx_record:'',
                summary:pass+' healthy, '+warn+' warnings, '+fail+' errors',
                checks:$.map(Object.keys(results),function(domain){
                    var d=results[domain];
                    return {
                        name:domain,
                        status:d.overall==='healthy'?'pass':(d.overall==='warning'?'warn':'fail'),
                        detail:d.summary+(d.mx_record?' — MX: '+d.mx_record:'')
                    };
                })
            };
            renderModal(summaryData, null);
            $('#tmpmp-dns-modal-title').text('<?php esc_html_e('All Domains — Summary','tempmail-pro'); ?>');
        });
    });

    function escHtml(s){ return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
});
</script>

</div><!-- /.wrap -->
