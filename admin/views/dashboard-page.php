<?php if ( ! defined('ABSPATH') ) exit; ?>
<div class="wrap tmpmp-admin-wrap">

    <!-- Header -->
    <div class="tmpmp-dash-header">
        <div>
            <h1 class="tmpmp-admin-title">
                <span class="tmpmp-logo-icon">✉</span>
                TempMail Pro
                <span class="tmpmp-version-pill">v<?php echo esc_html(TMPMP_VERSION); ?></span>
            </h1>
            <p style="color:#64748b;margin:0;font-size:13px;"><?php esc_html_e('SaaS Temporary Email Platform — Admin Dashboard','tempmail-pro'); ?></p>
        </div>
        <div class="tmpmp-dash-header-links">
            <a href="<?php echo admin_url('admin.php?page=tmpmp-settings'); ?>" class="button">⚙️ <?php esc_html_e('Settings','tempmail-pro'); ?></a>
            <a href="<?php echo admin_url('admin.php?page=tmpmp-analytics'); ?>" class="button">📈 <?php esc_html_e('Analytics','tempmail-pro'); ?></a>
        </div>
    </div>

    <!-- Stats Grid -->
    <div class="tmpmp-stats-grid" style="margin-bottom:24px;">
        <div class="tmpmp-stat-card">
            <div class="tmpmp-stat-icon" style="background:linear-gradient(135deg,#6366f1,#818cf8);">📧</div>
            <div class="tmpmp-stat-value"><?php echo number_format($stats['total_addresses']); ?></div>
            <div class="tmpmp-stat-label"><?php esc_html_e('Total Inboxes','tempmail-pro'); ?></div>
            <div class="tmpmp-stat-trend tmpmp-stat-trend--up">
                <span>●</span> <?php echo number_format($stats['active_addresses']); ?> <?php esc_html_e('active','tempmail-pro'); ?>
            </div>
        </div>
        <div class="tmpmp-stat-card">
            <div class="tmpmp-stat-icon" style="background:linear-gradient(135deg,#10b981,#34d399);">📨</div>
            <div class="tmpmp-stat-value"><?php echo number_format($stats['total_emails']); ?></div>
            <div class="tmpmp-stat-label"><?php esc_html_e('Total Emails','tempmail-pro'); ?></div>
            <div class="tmpmp-stat-trend tmpmp-stat-trend--up">
                <span>●</span> <?php echo number_format($stats['emails_today']); ?> <?php esc_html_e('today','tempmail-pro'); ?>
            </div>
        </div>
        <div class="tmpmp-stat-card">
            <div class="tmpmp-stat-icon" style="background:linear-gradient(135deg,#8b5cf6,#a78bfa);">💎</div>
            <div class="tmpmp-stat-value"><?php echo number_format($stats['premium_users']); ?></div>
            <div class="tmpmp-stat-label"><?php esc_html_e('Premium Users','tempmail-pro'); ?></div>
            <div class="tmpmp-stat-trend" style="color:#8b5cf6;">
                <span>●</span> <?php echo number_format($stats['total_domains']); ?> <?php esc_html_e('domains','tempmail-pro'); ?>
            </div>
        </div>
        <div class="tmpmp-stat-card">
            <div class="tmpmp-stat-icon" style="background:linear-gradient(135deg,#ec4899,#f472b6);">💰</div>
            <div class="tmpmp-stat-value">$<?php echo number_format($stats['total_revenue'],2); ?></div>
            <div class="tmpmp-stat-label"><?php esc_html_e('Total Revenue','tempmail-pro'); ?></div>
            <div class="tmpmp-stat-trend tmpmp-stat-trend--up"><span>●</span> <?php esc_html_e('All time','tempmail-pro'); ?></div>
        </div>
    </div>

    <!-- Three Column Cards -->
    <div class="tmpmp-three-cols">

        <!-- ① Quick Actions -->
        <div class="tmpmp-card">
            <h2 class="tmpmp-card-title">
                <span class="tmpmp-card-title-icon" style="background:#fff7ed;color:#f59e0b;">⚡</span>
                <?php esc_html_e('Quick Actions','tempmail-pro'); ?>
            </h2>

            <div class="tmpmp-qa-section">
                <label class="tmpmp-qa-label"><?php esc_html_e('Inject test email to address:','tempmail-pro'); ?></label>
                <input type="email" id="tmpmp-test-address" class="tmpmp-qa-input" placeholder="user@domain.com">
                <button class="tmpmp-qa-btn tmpmp-qa-btn--primary" id="tmpmp-inject-test">
                    📨 <?php esc_html_e('Inject Test Email','tempmail-pro'); ?>
                </button>
                <p id="tmpmp-inject-result" class="tmpmp-qa-result" hidden></p>
            </div>

            <div class="tmpmp-qa-divider"></div>

            <div class="tmpmp-qa-btns">
                <button class="tmpmp-qa-btn tmpmp-qa-btn--outline" id="tmpmp-purge-now">
                    🗑 <?php esc_html_e('Purge Expired Now','tempmail-pro'); ?>
                </button>
                <button class="tmpmp-qa-btn tmpmp-qa-btn--outline" id="tmpmp-poll-imap">
                    📡 <?php esc_html_e('Poll IMAP Now','tempmail-pro'); ?>
                </button>
            </div>
            <p id="tmpmp-purge-result" class="tmpmp-qa-result" hidden></p>
            <p id="tmpmp-poll-result"  class="tmpmp-qa-result" hidden></p>

            <div class="tmpmp-qa-divider"></div>
            <div class="tmpmp-qa-nav-links">
                <a href="<?php echo admin_url('admin.php?page=tmpmp-domains'); ?>">🌐 <?php esc_html_e('Domains','tempmail-pro'); ?></a>
                <a href="<?php echo admin_url('admin.php?page=tmpmp-plans'); ?>">💎 <?php esc_html_e('Plans','tempmail-pro'); ?></a>
                <a href="<?php echo admin_url('admin.php?page=tmpmp-payments'); ?>">💳 <?php esc_html_e('Payments','tempmail-pro'); ?></a>
                <a href="<?php echo admin_url('admin.php?page=tmpmp-users'); ?>">👥 <?php esc_html_e('Users','tempmail-pro'); ?></a>
            </div>
        </div>

        <!-- ② Shortcodes & Setup -->
        <div class="tmpmp-card">
            <h2 class="tmpmp-card-title">
                <span class="tmpmp-card-title-icon" style="background:#f0fdf4;color:#10b981;">📋</span>
                <?php esc_html_e('Shortcodes & Setup','tempmail-pro'); ?>
            </h2>

            <div class="tmpmp-shortcode-list">
                <?php
                $shortcodes = [
                    __('Main App','tempmail-pro')     => '[tempmail_app]',
                    __('Pricing','tempmail-pro')      => '[tempmail_pricing]',
                    __('Login','tempmail-pro')        => '[tempmail_login]',
                    __('Dashboard','tempmail-pro')    => '[tempmail_dashboard]',
                    __('Webhook URL','tempmail-pro')  => rest_url('tempmail-pro/v1/receive'),
                ];
                foreach ($shortcodes as $label => $code): ?>
                <div class="tmpmp-sc-row">
                    <span class="tmpmp-sc-label"><?php echo esc_html($label); ?></span>
                    <div class="tmpmp-sc-code-wrap">
                        <code class="tmpmp-sc-code" data-copy="<?php echo esc_attr($code); ?>" title="<?php esc_attr_e('Click to copy','tempmail-pro'); ?>"><?php echo esc_html($code); ?></code>
                        <button class="tmpmp-sc-copy" data-copy="<?php echo esc_attr($code); ?>" title="<?php esc_attr_e('Copy','tempmail-pro'); ?>">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 01-2-2V4a2 2 0 012-2h9a2 2 0 012 2v1"/></svg>
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <div class="tmpmp-qa-divider"></div>
            <p class="tmpmp-sc-tip">💡 <?php esc_html_e('Add each shortcode to a WordPress page. Click the copy icon to copy.','tempmail-pro'); ?></p>
        </div>

        <!-- ③ Mail Delivery Status -->
        <div class="tmpmp-card">
            <h2 class="tmpmp-card-title">
                <span class="tmpmp-card-title-icon" style="background:#eff6ff;color:#3b82f6;">📡</span>
                <?php esc_html_e('Mail Delivery Status','tempmail-pro'); ?>
            </h2>
            <?php
            $protocol  = $settings['mail_protocol'] ?? 'webhook';
            $last_hook = get_option('tmpmp_last_webhook_hit','—');
            $last_err  = get_option('tmpmp_last_webhook_error');
            $last_poll = get_option('tmpmp_last_imap_poll');
            $last_cron = get_option('tmpmp_last_cron_result', []);
            $cron_endpoint = rest_url('tempmail-pro/v1/server-cron');
            $cron_token    = $settings['server_cron_token'] ?? '';
            $curl_cmd = '*/1 * * * * curl -s -X POST "' . esc_url($cron_endpoint) . '?token=' . esc_attr($cron_token) . '" > /dev/null 2>&1';
            ?>
            <div class="tmpmp-status-rows">
                <div class="tmpmp-status-row">
                    <span class="tmpmp-status-key"><?php esc_html_e('Protocol','tempmail-pro'); ?></span>
                    <span class="tmpmp-badge <?php echo $protocol==='webhook' ? 'tmpmp-badge--blue' : 'tmpmp-badge--green'; ?>"><?php echo esc_html(strtoupper($protocol)); ?></span>
                </div>
                <div class="tmpmp-status-row">
                    <span class="tmpmp-status-key"><?php esc_html_e('Last Webhook Hit','tempmail-pro'); ?></span>
                    <span class="tmpmp-status-val"><?php echo esc_html($last_hook); ?></span>
                </div>
                <div class="tmpmp-status-row">
                    <span class="tmpmp-status-key"><?php esc_html_e('Last IMAP Poll','tempmail-pro'); ?></span>
                    <span class="tmpmp-status-val"><?php echo $last_poll ? esc_html($last_poll['time'].' — stored:'.$last_poll['stored']) : '—'; ?></span>
                </div>
                <div class="tmpmp-status-row">
                    <span class="tmpmp-status-key"><?php esc_html_e('Last Server Cron','tempmail-pro'); ?></span>
                    <span class="tmpmp-status-val">
                    <?php if ( ! empty($last_cron['time']) ) : ?>
                        <?php echo esc_html($last_cron['time']); ?>
                        <span style="color:#94a3b8;font-size:11px;margin-left:6px;">
                            <?php printf( esc_html__('%s fetched &middot; %s stored &middot; %sms','tempmail-pro'),
                                intval($last_cron['fetched']??0),
                                intval($last_cron['stored']??0),
                                intval($last_cron['duration_ms']??0)
                            ); ?>
                        </span>
                    <?php else : ?>
                        <span>—</span>
                        <button type="button" id="tmpmp-dash-cron-test"
                            style="margin-left:8px;padding:3px 10px;background:#ede9fe;color:#6366f1;border:none;border-radius:6px;font-size:11px;font-weight:700;cursor:pointer;">
                            <?php esc_html_e('Run Test','tempmail-pro'); ?>
                        </button>
                    <?php endif; ?>
                    </span>
                </div>
                <?php if($last_err): ?>
                <div class="tmpmp-status-row">
                    <span class="tmpmp-status-key" style="color:#ef4444;"><?php esc_html_e('Last Error','tempmail-pro'); ?></span>
                    <span class="tmpmp-status-val" style="color:#ef4444;"><?php echo esc_html($last_err['msg']??''); ?></span>
                </div>
                <?php endif; ?>
            </div>

            <!-- Cron command block -->
            <div class="tmpmp-qa-divider"></div>
            <p style="font-size:11px;font-weight:800;letter-spacing:.6px;text-transform:uppercase;color:#94a3b8;margin:0 0 8px;">
                <?php esc_html_e('Server Cron Command','tempmail-pro'); ?>
            </p>
            <div style="background:#0f172a;border-radius:9px;padding:11px 14px;display:flex;align-items:center;gap:10px;">
                <code id="tmpmp-dash-curl-cmd" style="flex:1;font-family:monospace;font-size:10.5px;color:#e2e8f0;word-break:break-all;line-height:1.6;"><?php echo esc_html($curl_cmd); ?></code>
                <button class="tmpmp-sc-copy" data-copy="tmpmp-dash-curl-cmd"
                    style="background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.15);border-radius:6px;padding:5px 8px;color:#94a3b8;cursor:pointer;flex-shrink:0;"
                    title="<?php esc_attr_e('Copy','tempmail-pro'); ?>">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 01-2-2V4a2 2 0 012-2h9a2 2 0 012 2v1"/></svg>
                </button>
            </div>

            <div class="tmpmp-qa-divider"></div>
            <a href="<?php echo admin_url('admin.php?page=tmpmp-settings#tab-mail'); ?>" class="tmpmp-qa-btn tmpmp-qa-btn--outline" style="width:100%;display:flex;justify-content:center;box-sizing:border-box;">
                ⚙️ <?php esc_html_e('Configure Mail Server','tempmail-pro'); ?>
            </a>
        </div>



    </div><!-- /.tmpmp-three-cols -->
</div>

<style>
.tmpmp-dash-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:24px;flex-wrap:wrap;gap:12px;}
.tmpmp-dash-header-links{display:flex;gap:8px;flex-wrap:wrap;}
@media(max-width:600px){.tmpmp-dash-header{flex-direction:column;align-items:flex-start;}.tmpmp-dash-header-links{width:100%;}} /* handled in mobile block below */
.tmpmp-logo-icon{display:inline-flex;align-items:center;justify-content:center;width:34px;height:34px;background:linear-gradient(135deg,#6366f1,#8b5cf6);border-radius:8px;color:#fff;font-size:18px;margin-right:2px;vertical-align:middle;}
.tmpmp-stat-trend{font-size:11px;color:#10b981;margin-top:6px;}
.tmpmp-stat-trend span{font-size:8px;}
.tmpmp-three-cols{display:grid;grid-template-columns:repeat(3,1fr);gap:20px;}
@media(max-width:1200px){.tmpmp-three-cols{grid-template-columns:1fr 1fr;}}
@media(max-width:782px){.tmpmp-three-cols{grid-template-columns:1fr;gap:14px;}}
.tmpmp-card-title-icon{display:inline-flex;align-items:center;justify-content:center;width:28px;height:28px;border-radius:8px;font-size:14px;margin-right:6px;vertical-align:middle;}
/* Quick Actions */
.tmpmp-qa-section{margin-bottom:4px;}
.tmpmp-qa-label{display:block;font-size:12px;font-weight:600;color:#475569;margin-bottom:6px;}
.tmpmp-qa-input{width:100%;padding:8px 12px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:13px;box-sizing:border-box;outline:none;transition:border-color .15s;}
.tmpmp-qa-input:focus{border-color:#6366f1;}
.tmpmp-qa-btn{display:inline-flex;align-items:center;gap:6px;padding:8px 14px;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;border:none;transition:all .15s;font-family:inherit;text-decoration:none;box-sizing:border-box;}
.tmpmp-qa-btn--primary{background:linear-gradient(135deg,#6366f1,#8b5cf6);color:#fff;width:100%;justify-content:center;margin-top:8px;}
.tmpmp-qa-btn--primary:hover{opacity:.9;transform:translateY(-1px);box-shadow:0 4px 12px rgba(99,102,241,.3);}
.tmpmp-qa-btn--outline{background:#f8fafc;color:#475569;border:1.5px solid #e2e8f0;}
.tmpmp-qa-btn--outline:hover{border-color:#6366f1;color:#6366f1;background:#f5f3ff;}
.tmpmp-qa-btns{display:flex;flex-direction:column;gap:8px;}
.tmpmp-qa-result{font-size:13px;font-weight:600;margin:8px 0 0;padding:8px 12px;border-radius:8px;background:#f0fdf4;color:#065f46;}
.tmpmp-qa-divider{height:1px;background:#f1f5f9;margin:16px 0;}
.tmpmp-qa-nav-links{display:grid;grid-template-columns:1fr 1fr;gap:6px;}
.tmpmp-qa-nav-links a{display:block;padding:7px 10px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;font-size:12px;font-weight:600;color:#475569;text-decoration:none;text-align:center;transition:all .15s;}
.tmpmp-qa-nav-links a:hover{border-color:#6366f1;color:#6366f1;background:#f5f3ff;}
/* Shortcodes */
.tmpmp-shortcode-list{display:flex;flex-direction:column;gap:4px;}
.tmpmp-sc-row{display:flex;align-items:flex-start;justify-content:space-between;gap:8px;padding:8px 0;border-bottom:1px solid #f8fafc;flex-wrap:wrap;}
.tmpmp-sc-row:last-child{border-bottom:none;}
.tmpmp-sc-label{font-size:13px;font-weight:600;color:#334155;min-width:90px;flex-shrink:0;}
.tmpmp-sc-code-wrap{display:flex;align-items:center;gap:6px;flex:1;min-width:0;}
.tmpmp-sc-code{display:block;background:#f1f5f9;color:#6366f1;padding:5px 10px;border-radius:6px;font-size:12px;font-family:monospace;flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;cursor:pointer;transition:background .15s,color .15s;user-select:all;}
.tmpmp-sc-code:hover{background:#e0e7ff;color:#4f46e5;}
.tmpmp-sc-code.tmpmp-sc-copied{background:#d1fae5!important;color:#065f46!important;animation:tmpmp-flash .35s ease;}
.tmpmp-sc-copy{background:#fff;border:1.5px solid #e2e8f0;border-radius:6px;padding:5px 7px;cursor:pointer;color:#64748b;display:flex;align-items:center;transition:all .15s;flex-shrink:0;}
.tmpmp-sc-copy:hover{border-color:#6366f1;color:#6366f1;background:#f5f3ff;}
.tmpmp-sc-copy.copied{border-color:#10b981;color:#10b981;background:#f0fdf4;}
.tmpmp-sc-tip{font-size:12px;color:#94a3b8;margin:0;}
@keyframes tmpmp-flash{0%{transform:scale(1)}40%{transform:scale(1.03)}100%{transform:scale(1)}}
/* Status */
.tmpmp-status-rows{display:flex;flex-direction:column;gap:2px;}
.tmpmp-status-row{display:flex;align-items:center;justify-content:space-between;padding:9px 0;border-bottom:1px solid #f8fafc;}
.tmpmp-status-row:last-child{border-bottom:none;}
.tmpmp-status-key{font-size:13px;color:#64748b;font-weight:500;flex-shrink:0;}
.tmpmp-status-val{font-size:12px;color:#334155;font-weight:600;text-align:right;max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
/* ── Mobile overrides ≤600px ── */
@media(max-width:600px){
  .tmpmp-admin-wrap{overflow-x:hidden;}
  /* Header */
  .tmpmp-dash-header{flex-direction:column;align-items:flex-start;}
  .tmpmp-dash-header-links{width:100%;}
  /* Shortcode rows: stack label then full-width code+btn */
  .tmpmp-sc-row{flex-direction:column;align-items:stretch;gap:6px;}
  .tmpmp-sc-label{min-width:unset;}
  .tmpmp-sc-code-wrap{width:100%;box-sizing:border-box;}
  .tmpmp-sc-code{white-space:normal;word-break:break-all;overflow:visible;text-overflow:unset;flex:1;}
  /* Status rows: stack key→value */
  .tmpmp-status-row{flex-direction:column;align-items:flex-start;gap:2px;}
  .tmpmp-status-val{max-width:100%;white-space:normal;word-break:break-all;text-align:left;}
}
</style>

<script>
jQuery(function($){
    const nonce = TempMailAdmin.nonce;
    const url   = TempMailAdmin.ajax_url;

    // Shared copy helper
    function tmpmpDoCopy(text, $btn, $code) {
        function onSuccess() {
            if ($btn) {
                $btn.addClass('copied').html('<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>');
                setTimeout(function(){ $btn.removeClass('copied').html('<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 01-2-2V4a2 2 0 012-2h9a2 2 0 012 2v1"/></svg>'); }, 1800);
            }
            if ($code) {
                $code.addClass('tmpmp-sc-copied');
                setTimeout(function(){ $code.removeClass('tmpmp-sc-copied'); }, 1800);
            }
        }
        function fallback() {
            const ta = document.createElement('textarea');
            ta.value = text; document.body.appendChild(ta); ta.select(); document.execCommand('copy'); document.body.removeChild(ta);
            onSuccess();
        }
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(onSuccess).catch(fallback);
        } else {
            fallback();
        }
    }

    // Click on the code element itself
    $(document).on('click', '.tmpmp-sc-code[data-copy]', function(){
        const $code = $(this);
        const text  = $code.data('copy');
        const $btn  = $code.closest('.tmpmp-sc-code-wrap').find('.tmpmp-sc-copy');
        tmpmpDoCopy(text, $btn.length ? $btn : null, $code);
    });

    // Click on the copy button — supports both data-copy="text" and data-copy="element-id"
    $(document).on('click', '.tmpmp-sc-copy', function(){
        const $btn   = $(this);
        const copyVal = $btn.data('copy');
        let text;
        // If data-copy is an element ID, read its content
        const $byId = $('#' + copyVal);
        if ($byId.length) {
            text = $byId.val() || $byId.text();
        } else {
            text = copyVal;
        }
        const $code = $btn.closest('.tmpmp-sc-code-wrap').find('.tmpmp-sc-code');
        tmpmpDoCopy(text.trim(), $btn, $code.length ? $code : null);
    });

    // Dashboard "Run Test" cron button
    $(document).on('click', '#tmpmp-dash-cron-test', function(){
        const $btn = $(this).prop('disabled', true).text('⏳…');
        $.post(TempMailAdmin.ajax_url, { action: 'tmpmp_test_cron', nonce }, function(r){
            if (r.success) {
                const d = r.data;
                $btn.closest('.tmpmp-status-val').html(
                    `<span>${d.time}</span>` +
                    `<span style="color:#94a3b8;font-size:11px;margin-left:6px;">${d.fetched} fetched &middot; ${d.stored} stored &middot; ${d.duration_ms}ms</span>`
                );
            } else {
                $btn.prop('disabled', false).text('Run Test');
                alert('❌ ' + (r.data?.message || 'Cron test failed.'));
            }
        });
    });


    // Inject test email
    $('#tmpmp-inject-test').on('click', function(){
        const addr = $('#tmpmp-test-address').val().trim();
        if(!addr){ alert('Enter an address first.'); return; }
        const self = $(this).prop('disabled',true).text('⏳ Sending…');
        $.post(url, {action:'tmpmp_inject_test_email', nonce, address:addr}, function(r){
            const res = $('#tmpmp-inject-result').prop('hidden',false);
            res.css({'background':r.success?'#f0fdf4':'#fef2f2','color':r.success?'#065f46':'#991b1b'}).text(r.success ? '✅ '+r.data.message : '❌ '+(r.data?.message||'Failed'));
            self.prop('disabled',false).text('📨 Inject Test Email');
        });
    });

    // Purge
    $('#tmpmp-purge-now').on('click', function(){
        const self = $(this).prop('disabled',true).text('⏳ Purging…');
        $.post(url, {action:'tmpmp_purge_now', nonce}, function(r){
            $('#tmpmp-purge-result').prop('hidden',false)
                .css({'background':'#f0fdf4','color':'#065f46'})
                .text(r.success ? '✅ '+r.data.message : '❌ Failed');
            self.prop('disabled',false).text('🗑 Purge Expired Now');
        });
    });

    // Poll IMAP
    $('#tmpmp-poll-imap').on('click', function(){
        const self = $(this).prop('disabled',true).text('⏳ Polling…');
        $.post(url, {action:'tmpmp_poll_imap', nonce}, function(r){
            const msg = r.success ? '✅ Fetched: '+(r.data.fetched||0)+' | Stored: '+(r.data.stored||0) : '❌ Poll failed';
            $('#tmpmp-poll-result').prop('hidden',false)
                .css({'background':r.success?'#f0fdf4':'#fef2f2','color':r.success?'#065f46':'#991b1b'})
                .text(msg);
            self.prop('disabled',false).text('📡 Poll IMAP Now');
        });
    });
});
</script>
