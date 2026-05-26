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
.tmpmp-styled-table{width:100%;border-collapse:collapse;font-size:13px;}
.tmpmp-styled-table th{background:#f8fafc;color:#475569;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;padding:10px 14px;text-align:left;border-bottom:2px solid #e2e8f0;}
.tmpmp-styled-table td{padding:12px 14px;border-bottom:1px solid #f1f5f9;color:#334155;vertical-align:middle;}
.tmpmp-styled-table tr:last-child td{border-bottom:none;}
.tmpmp-styled-table tr:hover td{background:#fafbff;}
.tmpmp-icon-btn{display:inline-flex;align-items:center;gap:5px;padding:5px 10px;border-radius:6px;font-size:12px;font-weight:600;cursor:pointer;border:1.5px solid #e2e8f0;background:#fff;transition:all .15s;}
.tmpmp-icon-btn:hover{border-color:#6366f1;color:#6366f1;}
.tmpmp-icon-btn--danger:hover{border-color:#ef4444;color:#ef4444;}
.tmpmp-icon-btn--verify{border-color:#0ea5e9;color:#0369a1;}
.tmpmp-icon-btn--verify:hover{background:#e0f2fe;border-color:#0369a1;}
.tmpmp-add-btn{display:inline-flex;align-items:center;gap:8px;padding:9px 18px;background:linear-gradient(135deg,#6366f1,#8b5cf6);color:#fff;border:none;border-radius:8px;font-size:13px;font-weight:700;cursor:pointer;font-family:inherit;transition:all .15s;}
.tmpmp-add-btn:hover{opacity:.9;transform:translateY(-1px);box-shadow:0 4px 12px rgba(99,102,241,.3);}
.tmpmp-verify-all-btn{display:inline-flex;align-items:center;gap:8px;padding:9px 18px;background:linear-gradient(135deg,#0ea5e9,#6366f1);color:#fff;border:none;border-radius:8px;font-size:13px;font-weight:700;cursor:pointer;font-family:inherit;transition:all .15s;}
.tmpmp-verify-all-btn:hover{opacity:.9;transform:translateY(-1px);}
.tmpmp-empty-row td{text-align:center;padding:32px!important;color:#94a3b8;}
/* Health badges */
.tmpmp-health-healthy{background:#dcfce7;color:#16a34a;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700;}
.tmpmp-health-warning{background:#fef9c3;color:#ca8a04;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700;}
.tmpmp-health-error  {background:#fee2e2;color:#dc2626;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700;}
.tmpmp-health-unknown{background:#f1f5f9;color:#94a3b8;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700;}
.tmpmp-health-spinning{animation:tmpmp-spin .8s linear infinite;display:inline-block;}
@keyframes tmpmp-spin{to{transform:rotate(360deg);}}
/* DNS Result Panel */
.tmpmp-dns-result{margin-top:8px;border:1px solid #e2e8f0;border-radius:10px;overflow:hidden;display:none;}
.tmpmp-dns-result-header{display:flex;align-items:center;gap:10px;padding:10px 16px;font-size:13px;font-weight:700;}
.tmpmp-dns-result-header.healthy{background:#dcfce7;color:#16a34a;}
.tmpmp-dns-result-header.warning{background:#fef9c3;color:#ca8a04;}
.tmpmp-dns-result-header.error  {background:#fee2e2;color:#dc2626;}
.tmpmp-dns-checks{padding:0;}
.tmpmp-dns-check-row{display:flex;align-items:flex-start;gap:12px;padding:10px 16px;border-bottom:1px solid #f1f5f9;font-size:13px;}
.tmpmp-dns-check-row:last-child{border-bottom:none;}
.tmpmp-dns-check-icon{width:20px;flex-shrink:0;text-align:center;font-weight:700;}
.tmpmp-dns-check-icon.pass{color:#16a34a;}
.tmpmp-dns-check-icon.fail{color:#dc2626;}
.tmpmp-dns-check-icon.warn{color:#ca8a04;}
.tmpmp-dns-check-icon.skip{color:#94a3b8;}
.tmpmp-dns-check-name{width:150px;font-weight:600;color:#334155;flex-shrink:0;}
.tmpmp-dns-check-detail{flex:1;color:#64748b;word-break:break-all;}
.tmpmp-dns-mx-row{padding:8px 16px;font-size:12px;color:#64748b;background:#f8fafc;border-top:1px solid #f1f5f9;}
.tmpmp-dns-mx-row strong{color:#0f172a;}
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

    <div style="padding-top:16px;display:flex;gap:12px;flex-wrap:wrap;">
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
        <tr id="domain-row-<?php echo intval($d->id); ?>">
            <td>
                <strong><?php echo esc_html($d->domain); ?></strong>
                <!-- DNS Result Panel (inline, hidden by default) -->
                <div class="tmpmp-dns-result" id="dns-result-<?php echo intval($d->id); ?>"></div>
            </td>
            <td>
                <select class="tmpmp-page-select tmpmp-domain-category" data-id="<?php echo intval($d->id); ?>" style="min-width:120px;">
                    <option value="free"    <?php selected($d->category,'free');    ?>>🆓 Free</option>
                    <option value="premium" <?php selected($d->category,'premium'); ?>>⭐ Premium</option>
                    <option value="vip"     <?php selected($d->category,'vip');     ?>>💎 VIP</option>
                </select>
            </td>
            <td><?php echo number_format($d->emails_count); ?></td>
            <td style="font-size:12px;color:#64748b;max-width:180px;word-break:break-all;">
                <?php echo $d->mx_record ? esc_html($d->mx_record) : '<span style="color:#cbd5e1;">—</span>'; ?>
            </td>
            <td>
                <?php
                $hs  = $d->health_status ?? 'unknown';
                $cls = match($hs) { 'healthy'=>'healthy','warning'=>'warning','error'=>'error', default=>'unknown' };
                $lbl = match($hs) { 'healthy'=>'✅ Healthy','warning'=>'⚠️ Warning','error'=>'❌ Error', default=>'— Unknown' };
                ?>
                <span class="tmpmp-health-<?php echo $cls; ?>" id="health-badge-<?php echo intval($d->id); ?>"><?php echo $lbl; ?></span>
            </td>
            <td style="font-size:11px;color:#94a3b8;" id="last-checked-<?php echo intval($d->id); ?>">
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
                    <button class="tmpmp-icon-btn tmpmp-icon-btn--verify tmpmp-verify-dns-btn" data-id="<?php echo intval($d->id); ?>" data-domain="<?php echo esc_attr($d->domain); ?>">
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

<!-- ④ DNS Guide -->
<div class="tmpmp-page-card">
    <p class="tmpmp-page-section-title">🔍 <?php esc_html_e('DNS Configuration Guide','tempmail-pro'); ?></p>
    <div class="tmpmp-page-field">
        <label class="tmpmp-page-label">MX Record</label>
        <div style="padding-top:8px;font-size:13px;color:#475569;">
            <?php esc_html_e('Points incoming emails to your mail server. Required for receiving email.','tempmail-pro'); ?><br>
            <code style="display:block;margin-top:6px;background:#f1f5f9;padding:8px 12px;border-radius:6px;font-size:12px;">example.com  IN  MX  10  mail.yourserver.com</code>
        </div>
    </div>
    <div class="tmpmp-page-field">
        <label class="tmpmp-page-label">SPF Record</label>
        <div style="padding-top:8px;font-size:13px;color:#475569;">
            <?php esc_html_e('Prevents spoofing. Recommended for deliverability.','tempmail-pro'); ?><br>
            <code style="display:block;margin-top:6px;background:#f1f5f9;padding:8px 12px;border-radius:6px;font-size:12px;">example.com  IN  TXT  "v=spf1 mx ~all"</code>
        </div>
    </div>
    <div class="tmpmp-page-field">
        <label class="tmpmp-page-label">DMARC Record</label>
        <div style="padding-top:8px;font-size:13px;color:#475569;">
            <?php esc_html_e('Email authentication policy. Improves trust with major providers.','tempmail-pro'); ?><br>
            <code style="display:block;margin-top:6px;background:#f1f5f9;padding:8px 12px;border-radius:6px;font-size:12px;">_dmarc.example.com  IN  TXT  "v=DMARC1; p=none; rua=mailto:dmarc@example.com"</code>
        </div>
    </div>
</div>

<script>
jQuery(function($){
    var nonce = (typeof TempMailAdmin !== 'undefined' ? TempMailAdmin.nonce : '') || (typeof TempMailProAdmin !== 'undefined' ? TempMailProAdmin.nonce : '');
    var url   = (typeof TempMailAdmin !== 'undefined' ? TempMailAdmin.ajax_url : '') || ajaxurl || '';

    // ── Add Domain ───────────────────────────────────────────────────────────
    $('#tmpmp-add-domain-btn').on('click', function(){
        var domain   = $('#tmpmp-new-domain').val().trim();
        var category = $('#tmpmp-new-category').val();
        if(!domain){ alert('<?php esc_html_e('Please enter a domain.','tempmail-pro'); ?>'); return; }
        var $btn = $(this).prop('disabled',true).text('Adding…');
        $.post(url,{action:'tmpmp_add_domain',nonce,domain,category},function(r){
            if(r.success) location.reload();
            else { alert(r.data?.message||'Failed'); $btn.prop('disabled',false).text('Add Domain'); }
        });
    });

    // ── Category change ───────────────────────────────────────────────────────
    $(document).on('change','.tmpmp-domain-category',function(){
        $.post(url,{action:'tmpmp_update_domain',nonce,id:$(this).data('id'),category:$(this).val()});
    });

    // ── Active toggle ─────────────────────────────────────────────────────────
    $(document).on('change','.tmpmp-domain-status',function(){
        $.post(url,{action:'tmpmp_update_domain',nonce,id:$(this).data('id'),is_active:$(this).is(':checked')?1:0});
    });

    // ── Delete ────────────────────────────────────────────────────────────────
    $(document).on('click','.tmpmp-delete-domain',function(){
        if(!confirm('<?php esc_html_e('Delete this domain?','tempmail-pro'); ?>')) return;
        var id = $(this).data('id');
        $.post(url,{action:'tmpmp_delete_domain',nonce,id},function(r){
            if(r.success) $('#domain-row-'+id).fadeOut(300,function(){ $(this).remove(); });
        });
    });

    // ── DNS Verification helpers ──────────────────────────────────────────────
    function iconHtml(status){
        var map = {pass:'<span class="tmpmp-dns-check-icon pass">✅</span>',
                   fail:'<span class="tmpmp-dns-check-icon fail">❌</span>',
                   warn:'<span class="tmpmp-dns-check-icon warn">⚠️</span>',
                   skip:'<span class="tmpmp-dns-check-icon skip">⏭</span>'};
        return map[status]||'<span class="tmpmp-dns-check-icon">?</span>';
    }
    function healthLabel(overall){
        var map={healthy:'✅ Healthy',warning:'⚠️ Warning',error:'❌ Error'};
        return map[overall]||overall;
    }
    function healthBadgeClass(overall){
        return {healthy:'tmpmp-health-healthy',warning:'tmpmp-health-warning',error:'tmpmp-health-error'}[overall]||'tmpmp-health-unknown';
    }

    function renderDnsResult(id, data){
        var html = '<div class="tmpmp-dns-result-header '+data.overall+'">'
                 + healthLabel(data.overall)
                 + ' &nbsp;·&nbsp; <small style="font-weight:400;">'+escHtml(data.summary)+'</small>'
                 + '</div>';
        if(data.mx_record){
            html += '<div class="tmpmp-dns-mx-row">📬 <strong>Primary MX:</strong> '+escHtml(data.mx_record)+'</div>';
        }
        html += '<div class="tmpmp-dns-checks">';
        $.each(data.checks,function(_,c){
            html += '<div class="tmpmp-dns-check-row">'
                  + iconHtml(c.status)
                  + '<div class="tmpmp-dns-check-name">'+escHtml(c.name)+'</div>'
                  + '<div class="tmpmp-dns-check-detail">'+escHtml(c.detail)+'</div>'
                  + '</div>';
        });
        html += '</div>';
        $('#dns-result-'+id).html(html).slideDown(200);

        // Update badge
        var $badge = $('#health-badge-'+id);
        $badge.attr('class', healthBadgeClass(data.overall)).text(healthLabel(data.overall));

        // Update MX cell (col index 3 → td:nth-child(4))
        $('#domain-row-'+id+' td:nth-child(4)').text(data.mx_record||'—');

        // Update Last Checked
        var now = new Date();
        $('#last-checked-'+id).text(now.toLocaleDateString(undefined,{month:'short',day:'numeric',year:'numeric'})
            +' '+now.toLocaleTimeString(undefined,{hour:'2-digit',minute:'2-digit'}));
    }

    // ── Single Verify ─────────────────────────────────────────────────────────
    $(document).on('click','.tmpmp-verify-dns-btn',function(){
        var id     = $(this).data('id');
        var domain = $(this).data('domain');
        var $btn   = $(this).prop('disabled',true);
        var $badge = $('#health-badge-'+id);
        $badge.attr('class','tmpmp-health-unknown').html('<span class="tmpmp-health-spinning">⟳</span> Checking…');
        $('#dns-result-'+id).slideUp(100);

        $.post(url,{action:'tmpmp_verify_domain_dns',nonce,id},function(r){
            $btn.prop('disabled',false);
            if(r.success){ renderDnsResult(id, r.data); }
            else { $badge.attr('class','tmpmp-health-error').text('❌ Error'); alert(r.data?.message||'Verify failed'); }
        });
    });

    // ── Verify All ────────────────────────────────────────────────────────────
    $('#tmpmp-verify-all-btn').on('click',function(){
        var $btn = $(this).prop('disabled',true).html('⟳ Verifying all…');
        // Spin all badges
        $('.tmpmp-styled-table tbody tr').each(function(){
            var id = $(this).attr('id')?.replace('domain-row-','');
            if(id) $('#health-badge-'+id).attr('class','tmpmp-health-unknown').html('<span class="tmpmp-health-spinning">⟳</span>');
        });
        $.post(url,{action:'tmpmp_verify_all_dns',nonce},function(r){
            $btn.prop('disabled',false).html('🔍 <?php esc_html_e('Verify All DNS','tempmail-pro'); ?>');
            if(!r.success){ alert('Failed'); return; }
            // r.data.results keyed by domain name; we need id from row
            $('.tmpmp-styled-table tbody tr').each(function(){
                var rowId  = $(this).attr('id')?.replace('domain-row-','');
                var domain = $(this).find('td:first strong').text().trim();
                if(rowId && r.data.results[domain]){
                    renderDnsResult(rowId, r.data.results[domain]);
                }
            });
        });
    });

    function escHtml(s){ return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
});
</script>

</div><!-- /.wrap -->
