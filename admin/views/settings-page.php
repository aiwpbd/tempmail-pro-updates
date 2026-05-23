<?php if ( ! defined('ABSPATH') ) exit; ?>
<div class="wrap tmpmp-admin-wrap">
<h1 class="tmpmp-admin-title"><span class="dashicons dashicons-admin-settings"></span> <?php esc_html_e('TempMail Pro — Settings','tempmail-pro'); ?> <span class="tmpmp-version-pill">v<?php echo esc_html(TMPMP_VERSION); ?></span></h1>
<div id="tmpmp-settings-saved" class="notice notice-success" style="display:none;"><p><?php esc_html_e('Settings saved!','tempmail-pro'); ?></p></div>

<form id="tmpmp-settings-form">
<div class="tmpmp-settings-tabs">
    <button type="button" class="tmpmp-tab-btn active" data-tab="general">⚙️ <?php esc_html_e('General','tempmail-pro'); ?></button>
    <button type="button" class="tmpmp-tab-btn" data-tab="mail">📡 <?php esc_html_e('Mail Server','tempmail-pro'); ?></button>
    <button type="button" class="tmpmp-tab-btn" data-tab="payments">💳 <?php esc_html_e('Payments','tempmail-pro'); ?></button>
    <button type="button" class="tmpmp-tab-btn" data-tab="social">🔐 <?php esc_html_e('Social Login','tempmail-pro'); ?></button>
    <button type="button" class="tmpmp-tab-btn" data-tab="ads">📢 <?php esc_html_e('Ads','tempmail-pro'); ?></button>
    <button type="button" class="tmpmp-tab-btn" data-tab="emailgen">✉️ <?php esc_html_e('Email Generation','tempmail-pro'); ?></button>
    <button type="button" class="tmpmp-tab-btn" data-tab="security">🛡️ <?php esc_html_e('Security','tempmail-pro'); ?></button>
    <button type="button" class="tmpmp-tab-btn" data-tab="design">🎨 <?php esc_html_e('Design','tempmail-pro'); ?></button>
    <button type="button" class="tmpmp-tab-btn" data-tab="faq">❓ <?php esc_html_e('FAQ','tempmail-pro'); ?></button>
    <button type="button" class="tmpmp-tab-btn" data-tab="loginemail">✉️ <?php esc_html_e('Login Email','tempmail-pro'); ?></button>
</div>

<!-- General Tab -->
<div class="tmpmp-tab-panel active" id="tab-general">

<!-- Email Generation -->
<div class="tmpmp-mail-card">
    <p class="tmpmp-mail-section-title">⚙️ <?php esc_html_e('Email Generation','tempmail-pro'); ?></p>

    <div class="tmpmp-mail-field">
        <label class="tmpmp-mail-label" for="refresh_interval"><?php esc_html_e('Inbox Refresh (sec)','tempmail-pro'); ?></label>
        <div>
            <input type="number" id="refresh_interval" name="refresh_interval" class="tmpmp-mail-input" style="max-width:100px;"
                value="<?php echo esc_attr($settings['refresh_interval']??10); ?>" min="5" max="120">
            <p class="tmpmp-mail-hint"><?php esc_html_e('How often the inbox auto-refreshes. Min 5s, Max 120s.','tempmail-pro'); ?></p>
        </div>
    </div>

    <div class="tmpmp-mail-field">
        <label class="tmpmp-mail-label"><?php esc_html_e('Spam Filter','tempmail-pro'); ?></label>
        <div style="padding-top:6px;">
            <label class="tmpmp-toggle-label">
                <input type="checkbox" name="spam_filter" value="1" <?php checked($settings['spam_filter']??1); ?>>
                <span class="tmpmp-toggle-slider"></span>
            </label>
            <span style="margin-left:10px;font-size:13px;color:#475569;vertical-align:middle;"><?php esc_html_e('Enable keyword-based spam filter','tempmail-pro'); ?></span>
        </div>
    </div>

    <div class="tmpmp-mail-field">
        <label class="tmpmp-mail-label" for="spam_keywords"><?php esc_html_e('Spam Keywords','tempmail-pro'); ?></label>
        <div>
            <textarea id="spam_keywords" name="spam_keywords" rows="5" class="tmpmp-mail-input" style="height:auto;resize:vertical;" placeholder="<?php esc_attr_e('One keyword per line…','tempmail-pro'); ?>"><?php echo esc_textarea($settings['spam_keywords']??''); ?></textarea>
            <p class="tmpmp-mail-hint"><?php esc_html_e('One keyword per line. Emails matching any keyword will be rejected.','tempmail-pro'); ?></p>
        </div>
    </div>

    <div class="tmpmp-mail-field">
        <label class="tmpmp-mail-label" for="upgrade_url"><?php esc_html_e('Upgrade / Pricing Page URL','tempmail-pro'); ?></label>
        <div>
            <?php
            // Auto-detect the pricing page permalink
            $pricing_page     = get_page_by_path('tempmail-pricing');
            $auto_pricing_url = $pricing_page ? get_permalink( $pricing_page->ID ) : '';
            $upgrade_url_val  = $settings['upgrade_url'] ?? '';
            // If setting is empty, use the auto-detected URL
            if ( empty( $upgrade_url_val ) && $auto_pricing_url ) {
                $upgrade_url_val = $auto_pricing_url;
            }
            ?>
            <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
                <input type="url" id="upgrade_url" name="upgrade_url" class="tmpmp-mail-input"
                    value="<?php echo esc_attr( $upgrade_url_val ); ?>"
                    placeholder="<?php echo esc_attr( $auto_pricing_url ?: 'https://yoursite.com/pricing' ); ?>"
                    style="flex:1;min-width:280px;">
                <?php if ( $auto_pricing_url ) : ?>
                <button type="button" id="tmpmp-auto-fill-url"
                    data-url="<?php echo esc_attr( $auto_pricing_url ); ?>"
                    style="padding:8px 14px;background:#ede9fe;color:#6366f1;border:none;border-radius:8px;font-size:12px;font-weight:700;cursor:pointer;white-space:nowrap;">
                    &#128279; <?php esc_html_e('Use Pricing Page','tempmail-pro'); ?>
                </button>
                <?php endif; ?>
            </div>
            <?php if ( $auto_pricing_url ) : ?>
            <p class="tmpmp-mail-hint">
                <?php esc_html_e('Auto-detected:','tempmail-pro'); ?>
                <a href="<?php echo esc_url($auto_pricing_url); ?>" target="_blank" style="color:#6366f1;">
                    <?php echo esc_html($auto_pricing_url); ?> &#8599;
                </a>
            </p>
            <?php else : ?>
            <p class="tmpmp-mail-hint"><?php esc_html_e('Pricing page not found. Create it via TempMail Pro → Pages first.','tempmail-pro'); ?></p>
            <?php endif; ?>
            <p class="tmpmp-mail-hint"><?php esc_html_e('Users selecting a Premium or VIP domain will be redirected here to upgrade their plan.','tempmail-pro'); ?></p>
        </div>
    </div>
    <!-- Dashboard URL -->
    <div class="tmpmp-mail-field">
        <label class="tmpmp-mail-label" for="dashboard_url"><?php esc_html_e('Subscription Dashboard URL','tempmail-pro'); ?></label>
        <div>
            <?php
            $dashboard_page     = get_page_by_path('tempmail-dashboard');
            $auto_dashboard_url = $dashboard_page ? get_permalink( $dashboard_page->ID ) : '';
            $dashboard_url_val  = $settings['dashboard_url'] ?? '';
            if ( empty($dashboard_url_val) && $auto_dashboard_url ) $dashboard_url_val = $auto_dashboard_url;
            ?>
            <input type="url" id="dashboard_url" name="dashboard_url" class="tmpmp-mail-input"
                value="<?php echo esc_attr($dashboard_url_val); ?>"
                placeholder="<?php echo esc_attr( $auto_dashboard_url ?: home_url('/dashboard/') ); ?>">
            <p class="tmpmp-mail-hint"><?php esc_html_e('Users are redirected here after login, registration and magic link. Must contain [tempmail_dashboard].','tempmail-pro'); ?></p>
        </div>
    </div>

    <!-- Login Page URL -->
    <div class="tmpmp-mail-field">
        <label class="tmpmp-mail-label" for="login_page_url"><?php esc_html_e('Login Page URL','tempmail-pro'); ?></label>
        <div>
            <?php
            $login_page     = get_page_by_path('tempmail-login');
            $auto_login_url = $login_page ? get_permalink( $login_page->ID ) : '';
            $login_url_val  = $settings['login_page_url'] ?? '';
            if ( empty($login_url_val) && $auto_login_url ) $login_url_val = $auto_login_url;
            ?>
            <input type="url" id="login_page_url" name="login_page_url" class="tmpmp-mail-input"
                value="<?php echo esc_attr($login_url_val); ?>"
                placeholder="<?php echo esc_attr( $auto_login_url ?: home_url('/login/') ); ?>">
            <p class="tmpmp-mail-hint"><?php esc_html_e('Non-logged-in users visiting the dashboard are sent here. Must contain [tempmail_login].','tempmail-pro'); ?></p>
        </div>
    </div>

</div>


<!-- Rate Limiting -->
<div class="tmpmp-mail-card">
    <p class="tmpmp-mail-section-title">🛡️ <?php esc_html_e('Rate Limiting','tempmail-pro'); ?></p>

    <div class="tmpmp-mail-field">
        <label class="tmpmp-mail-label"><?php esc_html_e('Rate Limit (per IP)','tempmail-pro'); ?></label>
        <div>
            <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
                <input type="number" name="rate_limit" class="tmpmp-mail-input" style="max-width:90px;"
                    value="<?php echo esc_attr($settings['rate_limit']??10); ?>">
                <span style="font-size:13px;color:#64748b;"><?php esc_html_e('requests per','tempmail-pro'); ?></span>
                <input type="number" name="rate_window" class="tmpmp-mail-input" style="max-width:80px;"
                    value="<?php echo esc_attr($settings['rate_window']??24); ?>">
                <span style="font-size:13px;color:#64748b;"><?php esc_html_e('hours','tempmail-pro'); ?></span>
            </div>
            <p class="tmpmp-mail-hint"><?php esc_html_e('Limits how many inboxes a single IP can create within the time window.','tempmail-pro'); ?></p>
        </div>
    </div>
</div>

<!-- Pricing Page Customisation -->
<div class="tmpmp-mail-card">
    <p class="tmpmp-mail-section-title">&#127991; <?php esc_html_e('Pricing Page','tempmail-pro'); ?></p>

    <div class="tmpmp-mail-field">
        <label class="tmpmp-mail-label"><?php esc_html_e('Eyebrow Badge Text','tempmail-pro'); ?></label>
        <div>
            <input type="text" name="pricing_eyebrow" class="tmpmp-mail-input"
                placeholder="&#9889; SIMPLE, TRANSPARENT PRICING"
                value="<?php echo esc_attr($settings['pricing_eyebrow'] ?? ''); ?>">
            <p class="tmpmp-mail-hint"><?php esc_html_e('Small badge that appears above the heading. Leave blank to hide.','tempmail-pro'); ?></p>
        </div>
    </div>

    <div class="tmpmp-mail-field">
        <label class="tmpmp-mail-label"><?php esc_html_e('Main Heading','tempmail-pro'); ?></label>
        <div>
            <input type="text" name="pricing_heading" class="tmpmp-mail-input"
                placeholder="Choose Your Plan"
                value="<?php echo esc_attr($settings['pricing_heading'] ?? ''); ?>">
        </div>
    </div>

    <div class="tmpmp-mail-field">
        <label class="tmpmp-mail-label"><?php esc_html_e('Subtitle / Description','tempmail-pro'); ?></label>
        <div>
            <input type="text" name="pricing_subtext" class="tmpmp-mail-input"
                placeholder="Start free, upgrade anytime. Cancel anytime. No hidden fees."
                value="<?php echo esc_attr($settings['pricing_subtext'] ?? ''); ?>">
        </div>
    </div>

    <div class="tmpmp-mail-field">
        <label class="tmpmp-mail-label"><?php esc_html_e('"Save" Badge Text','tempmail-pro'); ?></label>
        <div>
            <input type="text" name="pricing_yearly_save" class="tmpmp-mail-input" style="max-width:200px;"
                placeholder="Save 33%"
                value="<?php echo esc_attr($settings['pricing_yearly_save'] ?? ''); ?>">
            <p class="tmpmp-mail-hint"><?php esc_html_e('Displayed next to the "Yearly" toggle. Leave blank to hide.','tempmail-pro'); ?></p>
        </div>
    </div>

    <div class="tmpmp-mail-field">
        <label class="tmpmp-mail-label"><?php esc_html_e('Toggle Labels','tempmail-pro'); ?></label>
        <div style="display:flex;gap:12px;flex-wrap:wrap;">
            <div>
                <label style="font-size:12px;color:#64748b;display:block;margin-bottom:4px;"><?php esc_html_e('Monthly Label','tempmail-pro'); ?></label>
                <input type="text" name="pricing_label_monthly" class="tmpmp-mail-input" style="max-width:160px;"
                    placeholder="Monthly"
                    value="<?php echo esc_attr($settings['pricing_label_monthly'] ?? ''); ?>">
            </div>
            <div>
                <label style="font-size:12px;color:#64748b;display:block;margin-bottom:4px;"><?php esc_html_e('Yearly Label','tempmail-pro'); ?></label>
                <input type="text" name="pricing_label_yearly" class="tmpmp-mail-input" style="max-width:160px;"
                    placeholder="Yearly"
                    value="<?php echo esc_attr($settings['pricing_label_yearly'] ?? ''); ?>">
            </div>
        </div>
    </div>
</div>

<!-- Shortcodes & Setup -->
<div class="tmpmp-mail-card">
    <p class="tmpmp-mail-section-title">📋 <?php esc_html_e('Shortcodes & Setup','tempmail-pro'); ?></p>
    <p style="font-size:13px;color:#64748b;margin:0 0 16px;">
        <?php esc_html_e('Copy and paste these shortcodes into any page, post, or widget area.','tempmail-pro'); ?>
    </p>

    <?php
    $shortcodes = [
        [
            'code'  => '[tempmail_app]',
            'title' => __('Full Inbox Widget','tempmail-pro'),
            'desc'  => __('Complete disposable email app — address bar, inbox, email viewer.','tempmail-pro'),
            'attrs' => '[tempmail_app theme="dark"]  &nbsp;|&nbsp;  [tempmail_app theme="light"]',
        ],
        [
            'code'  => '[tempmail_faq]',
            'title' => __('FAQ Section Only','tempmail-pro'),
            'desc'  => __('Standalone FAQ accordion — place it on any page independently of the inbox.','tempmail-pro'),
            'attrs' => '',
        ],
        [
            'code'  => '[tempmail_app theme="auto"]',
            'title' => __('Auto Theme Inbox','tempmail-pro'),
            'desc'  => __('Follows the visitor\'s OS dark/light mode preference automatically.','tempmail-pro'),
            'attrs' => '',
        ],
    ];
    foreach ( $shortcodes as $sc ) : ?>
    <div style="background:#f8fafc;border:1.5px solid #e2e8f0;border-radius:10px;padding:14px 16px;margin-bottom:10px;">
        <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:10px;flex-wrap:wrap;">
            <div style="flex:1;min-width:200px;">
                <div style="display:flex;align-items:center;gap:8px;margin-bottom:6px;">
                    <code style="background:#ede9fe;color:#5b21b6;padding:4px 10px;border-radius:6px;font-size:13px;font-weight:700;font-family:monospace;cursor:pointer;" class="tmpmp-sc-copy" title="<?php esc_attr_e('Click to copy','tempmail-pro'); ?>"><?php echo esc_html($sc['code']); ?></code>
                    <span style="font-size:12px;font-weight:700;color:#374151;"><?php echo esc_html($sc['title']); ?></span>
                </div>
                <p style="font-size:12px;color:#64748b;margin:0;"><?php echo esc_html($sc['desc']); ?></p>
                <?php if ( $sc['attrs'] ) : ?>
                <p style="font-size:11px;color:#94a3b8;margin:4px 0 0;font-family:monospace;"><?php echo $sc['attrs']; ?></p>
                <?php endif; ?>
            </div>
            <button type="button" class="tmpmp-sc-copy-btn"
                data-code="<?php echo esc_attr($sc['code']); ?>"
                style="flex-shrink:0;padding:6px 14px;background:#6366f1;color:#fff;border:none;border-radius:7px;font-size:12px;font-weight:700;cursor:pointer;white-space:nowrap;">
                📋 <?php esc_html_e('Copy','tempmail-pro'); ?>
            </button>
        </div>
    </div>
    <?php endforeach; ?>

    <script>
    (function(){
        document.querySelectorAll('.tmpmp-sc-copy-btn, .tmpmp-sc-copy').forEach(function(el){
            el.addEventListener('click', function(){
                var code = this.dataset.code || this.textContent.trim();
                navigator.clipboard.writeText(code).then(function(){}).catch(function(){
                    var ta = document.createElement('textarea');
                    ta.value = code; document.body.appendChild(ta); ta.select();
                    document.execCommand('copy'); document.body.removeChild(ta);
                });
                var orig = el.textContent;
                if(el.classList.contains('tmpmp-sc-copy-btn')){ el.textContent = '✅ Copied!'; setTimeout(function(){ el.textContent = orig; }, 1500); }
            });
        });
    })();
    </script>
</div>

</div><!-- /#tab-general -->

<!-- Mail Server Tab -->
<div class="tmpmp-tab-panel" id="tab-mail">

<style>
.tmpmp-mail-section-title{font-size:11px;font-weight:800;letter-spacing:.8px;text-transform:uppercase;color:#6366f1;margin:0 0 14px;}
.tmpmp-mail-alert{display:flex;align-items:flex-start;gap:10px;background:#eff6ff;border:1px solid #bfdbfe;border-radius:10px;padding:12px 16px;margin-bottom:20px;font-size:13px;color:#1e40af;line-height:1.5;}
.tmpmp-mail-alert svg{flex-shrink:0;margin-top:1px;}
.tmpmp-mail-card{background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:20px 24px;margin-bottom:20px;}
.tmpmp-mail-field{display:grid;grid-template-columns:180px 1fr;gap:12px 20px;align-items:start;padding:14px 0;border-bottom:1px solid #f1f5f9;}
.tmpmp-mail-field:last-child{border-bottom:none;}
.tmpmp-mail-label{font-size:13px;font-weight:600;color:#334155;padding-top:9px;}
.tmpmp-mail-input{width:100%;padding:8px 12px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:13px;font-family:inherit;outline:none;transition:border-color .15s;box-sizing:border-box;}
.tmpmp-mail-input:focus{border-color:#6366f1;box-shadow:0 0 0 3px rgba(99,102,241,.1);}
.tmpmp-mail-hint{font-size:12px;color:#94a3b8;margin-top:6px;display:flex;flex-wrap:wrap;align-items:center;gap:4px;}
.tmpmp-mail-hint code{background:#f1f5f9;color:#475569;padding:2px 6px;border-radius:4px;font-size:11px;cursor:pointer;transition:background .15s;}
.tmpmp-mail-hint code:hover{background:#e0e7ff;color:#4f46e5;}
.tmpmp-mail-badge{display:inline-block;background:#f1f5f9;color:#475569;padding:2px 8px;border-radius:4px;font-size:11px;font-weight:700;cursor:pointer;transition:background .15s;}
.tmpmp-mail-badge:hover{background:#e0e7ff;color:#4f46e5;}
.tmpmp-mail-warn{display:flex;align-items:center;gap:6px;font-size:12px;color:#92400e;background:#fef3c7;border:1px solid #fde68a;border-radius:6px;padding:6px 10px;margin-top:6px;}
.tmpmp-test-btn{display:inline-flex;align-items:center;gap:8px;padding:9px 18px;background:linear-gradient(135deg,#6366f1,#8b5cf6);color:#fff;border:none;border-radius:8px;font-size:13px;font-weight:700;cursor:pointer;font-family:inherit;transition:all .15s;}
.tmpmp-test-btn:hover{opacity:.9;transform:translateY(-1px);box-shadow:0 4px 12px rgba(99,102,241,.3);}
.tmpmp-test-btn:disabled{opacity:.6;cursor:not-allowed;transform:none;}
/* Captcha provider cards */
.tmpmp-captcha-provider-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:10px;}
.tmpmp-captcha-provider-grid label{cursor:pointer;}
.tmpmp-captcha-option{border:2px solid #e2e8f0;background:#fff;border-radius:12px;padding:14px 12px;text-align:center;transition:all .2s;user-select:none;}
.tmpmp-captcha-option:hover{border-color:#a5b4fc;background:#f5f3ff;transform:translateY(-2px);box-shadow:0 4px 12px rgba(99,102,241,.12);}
.tmpmp-captcha-option--active{box-shadow:0 4px 14px rgba(99,102,241,.18);transform:translateY(-2px);}
.tmpmp-captcha-option-icon{font-size:22px;margin-bottom:6px;}
.tmpmp-captcha-option-name{font-weight:800;font-size:13px;color:#0f172a;}
.tmpmp-captcha-option-badge{font-size:11px;font-weight:700;margin-top:3px;}
.tmpmp-captcha-console-btn{display:inline-flex;align-items:center;gap:8px;padding:9px 18px;background:linear-gradient(135deg,#6366f1,#8b5cf6);color:#fff;text-decoration:none;border-radius:8px;font-size:13px;font-weight:700;cursor:pointer;transition:all .15s;margin-top:6px;margin-bottom:4px;}
.tmpmp-captcha-console-btn:hover{opacity:.9;transform:translateY(-1px);box-shadow:0 4px 12px rgba(99,102,241,.3);color:#fff;}
@media(max-width:700px){.tmpmp-captcha-provider-grid{grid-template-columns:repeat(2,1fr);}}
@media(max-width:420px){.tmpmp-captcha-provider-grid{grid-template-columns:1fr 1fr;}}
#tmpmp-imap-test-result{margin-top:12px;padding:10px 14px;border-radius:8px;font-size:13px;font-weight:600;display:none;}
.tmpmp-protocol-select{padding:8px 12px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:13px;font-family:inherit;outline:none;transition:border-color .15s;min-width:220px;cursor:pointer;}
.tmpmp-protocol-select:focus{border-color:#6366f1;}
@media(max-width:600px){.tmpmp-mail-field{grid-template-columns:1fr;gap:6px;}.tmpmp-mail-label{padding-top:0;}.tmpmp-mail-card{padding:16px 14px;}}
</style>

<!-- ① Receiving Method -->
<div class="tmpmp-mail-card">
    <p class="tmpmp-mail-section-title">📡 <?php esc_html_e('Receiving Method','tempmail-pro'); ?></p>
    <div class="tmpmp-mail-alert">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#3b82f6" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
        <?php esc_html_e('Select IMAP for shared hosting and local. Select Webhook for VPS/dedicated where a public URL is available.','tempmail-pro'); ?>
    </div>
    <div class="tmpmp-mail-field">
        <label class="tmpmp-mail-label" for="tmpmp-protocol"><?php esc_html_e('Protocol','tempmail-pro'); ?></label>
        <div>
            <select name="mail_protocol" id="tmpmp-protocol" class="tmpmp-protocol-select">
                <option value="imap"  <?php selected($settings['mail_protocol']??'webhook','imap');  ?>>📡 <?php esc_html_e('IMAP (recommended)','tempmail-pro'); ?></option>
                <option value="pop3"  <?php selected($settings['mail_protocol']??'','pop3');  ?>>📬 <?php esc_html_e('POP3','tempmail-pro'); ?></option>
                <option value="webhook" <?php selected($settings['mail_protocol']??'webhook','webhook'); ?>>🌐 <?php esc_html_e('Webhook (Mailgun / SendGrid)','tempmail-pro'); ?></option>
            </select>
        </div>
    </div>
</div>

<!-- ② IMAP / POP3 Settings -->
<div class="tmpmp-mail-card" id="tmpmp-imap-section">
    <p class="tmpmp-mail-section-title">🔧 <?php esc_html_e('IMAP / POP3 Settings','tempmail-pro'); ?></p>

    <!-- Server Host -->
    <div class="tmpmp-mail-field">
        <label class="tmpmp-mail-label" for="imap_host"><?php esc_html_e('Server Host','tempmail-pro'); ?></label>
        <div>
            <input type="text" id="imap_host" name="imap_host" class="tmpmp-mail-input"
                value="<?php echo esc_attr($settings['imap_host']??''); ?>"
                placeholder="mail.yourdomain.com">
            <div class="tmpmp-mail-hint">
                <?php esc_html_e('Gmail:','tempmail-pro'); ?>
                <code data-fill="imap_host" data-val="imap.gmail.com">imap.gmail.com</code>
                <span style="color:#cbd5e1">|</span>
                <?php esc_html_e('Outlook:','tempmail-pro'); ?>
                <code data-fill="imap_host" data-val="outlook.office365.com">outlook.office365.com</code>
            </div>
        </div>
    </div>

    <!-- Port -->
    <div class="tmpmp-mail-field">
        <label class="tmpmp-mail-label" for="imap_port"><?php esc_html_e('Port','tempmail-pro'); ?></label>
        <div>
            <input type="number" id="imap_port" name="imap_port" class="tmpmp-mail-input" style="max-width:120px;"
                value="<?php echo esc_attr($settings['imap_port']??993); ?>"
                placeholder="993">
            <div class="tmpmp-mail-hint">
                <?php esc_html_e('IMAP SSL:','tempmail-pro'); ?>
                <span class="tmpmp-mail-badge" data-fill="imap_port" data-val="993">993</span>
                <span style="color:#cbd5e1">|</span>
                <?php esc_html_e('POP3 SSL:','tempmail-pro'); ?>
                <span class="tmpmp-mail-badge" data-fill="imap_port" data-val="995">995</span>
            </div>
        </div>
    </div>

    <!-- Username -->
    <div class="tmpmp-mail-field">
        <label class="tmpmp-mail-label" for="imap_user"><?php esc_html_e('Username','tempmail-pro'); ?></label>
        <div>
            <input type="text" id="imap_user" name="imap_user" class="tmpmp-mail-input"
                value="<?php echo esc_attr($settings['imap_user']??''); ?>"
                placeholder="mail@yourdomain.com">
        </div>
    </div>

    <!-- Password -->
    <div class="tmpmp-mail-field">
        <label class="tmpmp-mail-label" for="imap_pass"><?php esc_html_e('Password / App Password','tempmail-pro'); ?></label>
        <div>
            <input type="password" id="imap_pass" name="imap_pass" class="tmpmp-mail-input"
                value="<?php echo esc_attr($settings['imap_pass']??''); ?>"
                placeholder="<?php esc_attr_e('Enter password or app password','tempmail-pro'); ?>">
            <div class="tmpmp-mail-warn">
                ⚠️ <?php printf(
                    esc_html__('Gmail: use an %s. 2-Step Verification must be on.','tempmail-pro'),
                    '<a href="https://myaccount.google.com/apppasswords" target="_blank" rel="noopener" style="color:#92400e;font-weight:700;">'
                        . esc_html__('App Password','tempmail-pro') . '</a>'
                ); ?>
            </div>
        </div>
    </div>

    <!-- Connection Test -->
    <div class="tmpmp-mail-field">
        <label class="tmpmp-mail-label"><?php esc_html_e('Connection Test','tempmail-pro'); ?></label>
        <div>
            <button type="button" id="tmpmp-test-imap" class="tmpmp-test-btn">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07A19.5 19.5 0 013.07 8.81 19.79 19.79 0 01.07 2.18 2 2 0 012.06 0h3a2 2 0 012 1.72c.127.96.361 1.903.7 2.81a2 2 0 01-.45 2.11L6.09 7.91a16 16 0 006 6l1.27-1.27a2 2 0 012.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0122 14.92z"/></svg>
                <?php esc_html_e('Test IMAP Connection','tempmail-pro'); ?>
            </button>
            <div id="tmpmp-imap-test-result"></div>
        </div>
    </div>
</div>

<!-- ③ Webhook Settings -->
<div class="tmpmp-mail-card" id="tmpmp-webhook-section">
    <p class="tmpmp-mail-section-title">🌐 <?php esc_html_e('Webhook Settings','tempmail-pro'); ?></p>
    <div class="tmpmp-mail-field">
        <label class="tmpmp-mail-label"><?php esc_html_e('Webhook Secret','tempmail-pro'); ?></label>
        <div>
            <div style="display:flex;gap:8px;align-items:center;">
                <input type="text" name="webhook_secret" value="<?php echo esc_attr($settings['webhook_secret']??''); ?>" class="tmpmp-mail-input" id="tmpmp-webhook-secret" readonly>
                <button type="button" class="button" id="tmpmp-regen-webhook" style="flex-shrink:0;"><?php esc_html_e('Regenerate','tempmail-pro'); ?></button>
            </div>
            <p class="tmpmp-mail-hint" style="margin-top:6px;"><?php esc_html_e('Set this in your Mailgun/SendGrid webhook header as X-TempMail-Secret.','tempmail-pro'); ?></p>
        </div>
    </div>
</div>

<!-- ④ Real Server Cron -->
<div class="tmpmp-mail-card" id="tmpmp-real-cron-card">
<style>
#tmpmp-real-cron-card .cron-title{font-size:16px;font-weight:800;color:#0f172a;display:flex;align-items:center;gap:8px;margin:0 0 4px;}
#tmpmp-real-cron-card .cron-subtitle{font-size:13px;color:#64748b;margin:0 0 18px;}
#tmpmp-real-cron-card .cron-desc{font-size:13.5px;color:#374151;line-height:1.65;margin:0 0 22px;padding-bottom:18px;border-bottom:1px solid #f1f5f9;}
#tmpmp-real-cron-card .cron-row{display:flex;align-items:center;gap:14px;margin-bottom:18px;flex-wrap:wrap;}
#tmpmp-real-cron-card .cron-row-label{font-size:13px;font-weight:700;color:#0f172a;min-width:120px;}
#tmpmp-real-cron-card .cron-endpoint-inp{flex:1;min-width:200px;background:#f8fafc;border:1.5px solid #e2e8f0;border-radius:9px;padding:9px 14px;font-size:13px;color:#475569;font-family:monospace;}
#tmpmp-real-cron-card .cron-copy-btn{display:inline-flex;align-items:center;gap:6px;padding:8px 14px;background:#fff;border:1.5px solid #e2e8f0;border-radius:8px;font-size:12.5px;font-weight:700;color:#475569;cursor:pointer;white-space:nowrap;transition:all .2s;}
#tmpmp-real-cron-card .cron-copy-btn:hover{border-color:#6366f1;color:#6366f1;}
#tmpmp-real-cron-card .cron-token-inp{flex:1;min-width:200px;background:#f8fafc;border:1.5px solid #e2e8f0;border-radius:9px;padding:9px 14px;font-size:13px;color:#0f172a;font-family:monospace;letter-spacing:.08em;}
#tmpmp-real-cron-card .cron-show-btn{padding:7px 14px;background:#fff;border:1.5px solid #6366f1;border-radius:8px;font-size:12px;font-weight:700;color:#6366f1;cursor:pointer;}
#tmpmp-real-cron-card .cron-regen-btn{padding:7px 14px;background:#fff;border:1.5px solid #e2e8f0;border-radius:8px;font-size:12px;font-weight:700;color:#475569;cursor:pointer;display:inline-flex;align-items:center;gap:5px;}
#tmpmp-real-cron-card .cron-regen-btn:hover{border-color:#6366f1;color:#6366f1;}
.cron-code-section{margin:20px 0;}
.cron-code-label{font-size:10.5px;font-weight:800;letter-spacing:.8px;color:#64748b;text-transform:uppercase;margin:0 0 6px;}
.cron-code-block{background:#0f172a;border-radius:10px;padding:14px 16px;display:flex;align-items:center;gap:12px;margin-bottom:10px;}
.cron-code-block code{flex:1;font-family:'Fira Code',monospace;font-size:12.5px;color:#e2e8f0;word-break:break-all;line-height:1.6;}
.cron-code-copy{background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.15);border-radius:7px;padding:6px 10px;color:#94a3b8;font-size:11px;cursor:pointer;white-space:nowrap;display:inline-flex;align-items:center;gap:4px;transition:all .2s;}
.cron-code-copy:hover{background:rgba(255,255,255,.14);color:#fff;}
#tmpmp-cron-test-btn{background:linear-gradient(135deg,#0ea5e9,#6366f1);color:#fff;border:none;border-radius:10px;padding:12px 22px;font-size:14px;font-weight:700;cursor:pointer;display:inline-flex;align-items:center;gap:8px;transition:opacity .2s;}
#tmpmp-cron-test-btn:hover{opacity:.88;}
#tmpmp-cron-test-btn:disabled{opacity:.55;cursor:not-allowed;}
#tmpmp-cron-result{background:#f8fafc;border:1.5px solid #e2e8f0;border-radius:10px;padding:16px 20px;font-size:13px;color:#374151;line-height:1.8;}
#tmpmp-cron-result .cr-row{display:flex;align-items:center;gap:8px;}
#tmpmp-cron-result .cr-row span{color:#0f172a;font-weight:600;}
.cron-disable-tip{background:#fffbeb;border:1.5px solid #fde68a;border-radius:12px;padding:18px 22px;margin-top:22px;}
.cron-disable-tip p{margin:0 0 10px;font-size:13.5px;color:#92400e;}
.cron-disable-tip strong{color:#78350f;font-size:14px;}
</style>

    <?php
    $cron_token    = $settings['server_cron_token'] ?? '';
    $cron_endpoint = rest_url('tempmail-pro/v1/server-cron');
    $curl_cmd  = '*/1 * * * * curl -s -X POST "' . esc_url($cron_endpoint) . '?token=' . esc_attr($cron_token) . '" > /dev/null 2>&1';
    $wget_cmd  = '*/1 * * * * wget -q -O /dev/null "' . esc_url($cron_endpoint) . '?token=' . esc_attr($cron_token) . '" 2>/dev/null';
    $last_cron = get_option('tmpmp_last_cron_result', []);
    ?>

    <div class="cron-title">&#128336; <?php esc_html_e('Real Server Cron','tempmail-pro'); ?></div>
    <div class="cron-subtitle"><?php esc_html_e('Replaces unreliable WP-Cron with true system cron','tempmail-pro'); ?></div>
    <p class="cron-desc"><?php esc_html_e('WP-Cron only fires when your site receives a page visit. Real Server Cron fires every minute on the server clock regardless of traffic — giving near-instant email delivery.','tempmail-pro'); ?></p>

    <!-- Endpoint -->
    <div class="cron-row">
        <div class="cron-row-label"><?php esc_html_e('Cron Endpoint','tempmail-pro'); ?></div>
        <input type="text" class="cron-endpoint-inp" readonly value="<?php echo esc_attr($cron_endpoint); ?>" id="tmpmp-cron-endpoint-inp">
        <button type="button" class="cron-copy-btn" data-copy="tmpmp-cron-endpoint-inp">&#128203; <?php esc_html_e('Click to copy','tempmail-pro'); ?></button>
    </div>

    <!-- Token -->
    <div class="cron-row">
        <div class="cron-row-label"><?php esc_html_e('Secret Token','tempmail-pro'); ?></div>
        <input type="password" class="cron-token-inp" id="tmpmp-cron-token"
            name="server_cron_token"
            value="<?php echo esc_attr($cron_token); ?>">
        <button type="button" class="cron-show-btn" id="tmpmp-cron-show">&#128065; <?php esc_html_e('Show','tempmail-pro'); ?></button>
        <button type="button" class="cron-regen-btn" id="tmpmp-regen-cron">&#128260; <?php esc_html_e('Regenerate Token','tempmail-pro'); ?></button>
    </div>

    <!-- Crontab commands -->
    <div class="cron-code-section">
        <hr style="border:none;border-top:1px solid #f1f5f9;margin:0 0 18px;">
        <p style="font-size:13.5px;font-weight:700;color:#0f172a;margin:0 0 4px;">&#128203; <?php esc_html_e('Add one of these lines to your server\'s crontab:','tempmail-pro'); ?></p>
        <p style="font-size:12.5px;color:#64748b;margin:0 0 14px;"><?php esc_html_e('Run: crontab -e on your server and paste one line:','tempmail-pro'); ?></p>

        <div class="cron-code-label"><?php esc_html_e('CURL:','tempmail-pro'); ?></div>
        <div class="cron-code-block">
            <code id="tmpmp-curl-cmd"><?php echo esc_html($curl_cmd); ?></code>
            <button type="button" class="cron-code-copy" data-copy="tmpmp-curl-cmd">&#128203; <?php esc_html_e('Click to copy','tempmail-pro'); ?></button>
        </div>

        <div class="cron-code-label"><?php esc_html_e('WGET (ALTERNATIVE):','tempmail-pro'); ?></div>
        <div class="cron-code-block">
            <code id="tmpmp-wget-cmd"><?php echo esc_html($wget_cmd); ?></code>
            <button type="button" class="cron-code-copy" data-copy="tmpmp-wget-cmd">&#128203; <?php esc_html_e('Click to copy','tempmail-pro'); ?></button>
        </div>
    </div>

    <!-- Test button + result -->
    <div style="display:flex;align-items:flex-start;gap:20px;flex-wrap:wrap;margin-top:4px;">
        <button type="button" id="tmpmp-cron-test-btn">&#9889; <?php esc_html_e('Test Server Cron Now','tempmail-pro'); ?></button>
        <?php if ( ! empty($last_cron) ) : ?>
        <div id="tmpmp-cron-result">
            <div class="cr-row">&#128336; <?php esc_html_e('Last Run:','tempmail-pro'); ?> <span><?php echo esc_html($last_cron['time'] ?? '—'); ?></span></div>
            <div class="cr-row">&#128140; <?php esc_html_e('IMAP:','tempmail-pro'); ?> <span><?php echo intval($last_cron['fetched']??0); ?> <?php esc_html_e('fetched,','tempmail-pro'); ?> <?php echo intval($last_cron['stored']??0); ?> <?php esc_html_e('stored','tempmail-pro'); ?></span></div>
            <div class="cr-row">&#128465; <?php esc_html_e('Purged:','tempmail-pro'); ?> <span><?php echo intval($last_cron['purged']??0); ?> <?php esc_html_e('expired','tempmail-pro'); ?></span></div>
            <div class="cr-row">&#9889; <?php esc_html_e('Duration:','tempmail-pro'); ?> <span><?php echo intval($last_cron['duration_ms']??0); ?>ms</span></div>
        </div>
        <?php else : ?>
        <div id="tmpmp-cron-result" style="display:none;"></div>
        <?php endif; ?>
    </div>

    <!-- WP-Cron disable tip -->
    <div class="cron-disable-tip">
        <strong>&#128161; <?php esc_html_e('Disable WP-Cron for best performance (optional)','tempmail-pro'); ?></strong>
        <p><?php esc_html_e('Once real server cron is running, disable WP-Cron to reduce overhead on every page load. Add this line to your wp-config.php:','tempmail-pro'); ?></p>
        <div class="cron-code-block" style="background:#1e293b;">
            <code id="tmpmp-wpcron-code">define('DISABLE_WP_CRON', true);</code>
            <button type="button" class="cron-code-copy" data-copy="tmpmp-wpcron-code">&#128203; <?php esc_html_e('Click to copy','tempmail-pro'); ?></button>
        </div>
    </div>

</div>



</div><!-- /#tab-mail -->

<!-- Payments Tab -->
<div class="tmpmp-tab-panel" id="tab-payments">

<!-- Stripe -->
<div class="tmpmp-mail-card">
    <p class="tmpmp-mail-section-title">💳 <?php esc_html_e('Stripe','tempmail-pro'); ?></p>

    <div class="tmpmp-mail-field">
        <label class="tmpmp-mail-label"><?php esc_html_e('Enable Stripe','tempmail-pro'); ?></label>
        <div style="padding-top:6px;">
            <label class="tmpmp-toggle-label">
                <input type="checkbox" name="stripe_enabled" value="1" <?php checked($settings['stripe_enabled']??0); ?>>
                <span class="tmpmp-toggle-slider"></span>
            </label>
            <span style="margin-left:10px;font-size:13px;color:#475569;vertical-align:middle;"><?php esc_html_e('Accept payments via Stripe','tempmail-pro'); ?></span>
        </div>
    </div>
    <div class="tmpmp-mail-field">
        <label class="tmpmp-mail-label" for="stripe_pk"><?php esc_html_e('Publishable Key','tempmail-pro'); ?></label>
        <div>
            <input type="text" id="stripe_pk" name="stripe_pk" class="tmpmp-mail-input"
                value="<?php echo esc_attr($settings['stripe_pk']??''); ?>" placeholder="pk_live_…">
        </div>
    </div>
    <div class="tmpmp-mail-field">
        <label class="tmpmp-mail-label"><?php esc_html_e('Secret Key','tempmail-pro'); ?></label>
        <div>
            <input type="password" name="stripe_sk" class="tmpmp-mail-input" placeholder="<?php esc_attr_e('sk_live_… (leave blank to keep current)','tempmail-pro'); ?>">
        </div>
    </div>
    <div class="tmpmp-mail-field">
        <label class="tmpmp-mail-label"><?php esc_html_e('Webhook Secret','tempmail-pro'); ?></label>
        <div>
            <input type="password" name="stripe_webhook_secret" class="tmpmp-mail-input" placeholder="whsec_…">
            <p class="tmpmp-mail-hint"><?php esc_html_e('Leave blank to keep current value.','tempmail-pro'); ?></p>
        </div>
    </div>
</div>

<!-- PayPal -->
<div class="tmpmp-mail-card">
    <p class="tmpmp-mail-section-title">🅿️ <?php esc_html_e('PayPal','tempmail-pro'); ?></p>

    <div class="tmpmp-mail-field">
        <label class="tmpmp-mail-label"><?php esc_html_e('Enable PayPal','tempmail-pro'); ?></label>
        <div style="padding-top:6px;">
            <label class="tmpmp-toggle-label">
                <input type="checkbox" name="paypal_enabled" value="1" <?php checked($settings['paypal_enabled']??0); ?>>
                <span class="tmpmp-toggle-slider"></span>
            </label>
            <span style="margin-left:10px;font-size:13px;color:#475569;vertical-align:middle;"><?php esc_html_e('Accept payments via PayPal','tempmail-pro'); ?></span>
        </div>
    </div>
    <div class="tmpmp-mail-field">
        <label class="tmpmp-mail-label"><?php esc_html_e('Client ID','tempmail-pro'); ?></label>
        <div>
            <input type="text" name="paypal_client_id" class="tmpmp-mail-input"
                value="<?php echo esc_attr($settings['paypal_client_id']??''); ?>" placeholder="AYour-PayPal-Client-ID">
        </div>
    </div>
    <div class="tmpmp-mail-field">
        <label class="tmpmp-mail-label"><?php esc_html_e('Secret','tempmail-pro'); ?></label>
        <div>
            <input type="password" name="paypal_secret" class="tmpmp-mail-input" placeholder="<?php esc_attr_e('Leave blank to keep current','tempmail-pro'); ?>">
        </div>
    </div>
</div>

<!-- SSLCommerz -->
<div class="tmpmp-mail-card">
    <p class="tmpmp-mail-section-title">🏦 <?php esc_html_e('SSLCommerz (Bangladesh)','tempmail-pro'); ?></p>

    <div class="tmpmp-mail-field">
        <label class="tmpmp-mail-label"><?php esc_html_e('Store ID','tempmail-pro'); ?></label>
        <div>
            <input type="text" name="ssl_store_id" class="tmpmp-mail-input"
                value="<?php echo esc_attr($settings['ssl_store_id']??''); ?>" placeholder="yourstore">
        </div>
    </div>
    <div class="tmpmp-mail-field">
        <label class="tmpmp-mail-label"><?php esc_html_e('Store Password','tempmail-pro'); ?></label>
        <div>
            <input type="password" name="ssl_store_pass" class="tmpmp-mail-input" placeholder="<?php esc_attr_e('Leave blank to keep current','tempmail-pro'); ?>">
        </div>
    </div>
    <div class="tmpmp-mail-field">
        <label class="tmpmp-mail-label"><?php esc_html_e('Live Mode','tempmail-pro'); ?></label>
        <div style="padding-top:6px;">
            <label class="tmpmp-toggle-label">
                <input type="checkbox" name="ssl_live" value="1" <?php checked($settings['ssl_live']??0); ?>>
                <span class="tmpmp-toggle-slider"></span>
            </label>
            <span style="margin-left:10px;font-size:13px;color:#475569;vertical-align:middle;"><?php esc_html_e('Enable live mode (uncheck for sandbox)','tempmail-pro'); ?></span>
        </div>
    </div>
</div>

<!-- WooCommerce -->
<div class="tmpmp-mail-card">
    <p class="tmpmp-mail-section-title">&#128722; <?php esc_html_e('WooCommerce','tempmail-pro'); ?></p>

    <div class="tmpmp-mail-field">
        <label class="tmpmp-mail-label"><?php esc_html_e('Enable WooCommerce','tempmail-pro'); ?></label>
        <div style="padding-top:6px;">
            <label class="tmpmp-toggle-label">
                <input type="checkbox" name="wc_enabled" value="1" <?php checked($settings['wc_enabled']??0); ?>>
                <span class="tmpmp-toggle-slider"></span>
            </label>
            <span style="margin-left:10px;font-size:13px;color:#475569;vertical-align:middle;"><?php esc_html_e('Let customers pay via any WooCommerce payment gateway','tempmail-pro'); ?></span>
        </div>
    </div>

    <?php if ( ! function_exists('wc_create_order') ) : ?>
    <div style="margin-top:10px;padding:10px 14px;background:#fffbeb;border:1px solid #fde68a;border-radius:8px;font-size:13px;color:#92400e;">
        &#9888; <?php esc_html_e('WooCommerce is not currently active. Install and activate WooCommerce for this gateway to work.','tempmail-pro'); ?>
    </div>
    <?php endif; ?>

    <div class="tmpmp-mail-field" style="margin-top:12px;">
        <label class="tmpmp-mail-label" style="font-size:12px;color:#64748b;"><?php esc_html_e('Webhook URL (for WooCommerce order status)','tempmail-pro'); ?></label>
        <div>
            <input type="text" class="tmpmp-mail-input" readonly
                value="<?php echo esc_attr( rest_url('tempmail-pro/v1/webhook/woocommerce') ); ?>"
                onclick="this.select();" style="background:#f8fafc;color:#475569;cursor:text;">
            <p style="margin:4px 0 0;font-size:12px;color:#94a3b8;"><?php esc_html_e('Not needed — WooCommerce fires activation hooks server-side automatically.','tempmail-pro'); ?></p>
        </div>
    </div>
</div>

<!-- Custom API -->
<div class="tmpmp-mail-card">
    <p class="tmpmp-mail-section-title">&#128279; <?php esc_html_e('Custom Payment API','tempmail-pro'); ?></p>

    <div class="tmpmp-mail-field">
        <label class="tmpmp-mail-label"><?php esc_html_e('Enable Custom API','tempmail-pro'); ?></label>
        <div style="padding-top:6px;">
            <label class="tmpmp-toggle-label">
                <input type="checkbox" name="custom_api_enabled" value="1" <?php checked($settings['custom_api_enabled']??0); ?>>
                <span class="tmpmp-toggle-slider"></span>
            </label>
            <span style="margin-left:10px;font-size:13px;color:#475569;vertical-align:middle;"><?php esc_html_e('Enable a custom payment gateway via your own API','tempmail-pro'); ?></span>
        </div>
    </div>
    <div class="tmpmp-mail-field">
        <label class="tmpmp-mail-label" for="custom_api_endpoint"><?php esc_html_e('API Endpoint URL','tempmail-pro'); ?></label>
        <div>
            <input type="url" id="custom_api_endpoint" name="custom_api_endpoint" class="tmpmp-mail-input"
                value="<?php echo esc_attr($settings['custom_api_endpoint']??''); ?>"
                placeholder="https://your-payment-api.com/checkout">
            <p style="margin:4px 0 0;font-size:12px;color:#94a3b8;"><?php esc_html_e('TempMail Pro will POST plan + user data here and expect { checkout_url, txn_id } in response.','tempmail-pro'); ?></p>
        </div>
    </div>
    <div class="tmpmp-mail-field">
        <label class="tmpmp-mail-label" for="custom_api_key"><?php esc_html_e('API Bearer Key','tempmail-pro'); ?></label>
        <div>
            <input type="password" id="custom_api_key" name="custom_api_key" class="tmpmp-mail-input"
                placeholder="<?php esc_attr_e('Leave blank to keep current','tempmail-pro'); ?>">
        </div>
    </div>
    <div class="tmpmp-mail-field">
        <label class="tmpmp-mail-label" for="custom_api_webhook_secret"><?php esc_html_e('Webhook Secret','tempmail-pro'); ?></label>
        <div>
            <input type="password" id="custom_api_webhook_secret" name="custom_api_webhook_secret" class="tmpmp-mail-input"
                placeholder="<?php esc_attr_e('Leave blank to skip signature verification','tempmail-pro'); ?>">
        </div>
    </div>
    <div class="tmpmp-mail-field">
        <label class="tmpmp-mail-label"><?php esc_html_e('Incoming Webhook URL','tempmail-pro'); ?></label>
        <div>
            <input type="text" class="tmpmp-mail-input" readonly
                value="<?php echo esc_attr( rest_url('tempmail-pro/v1/webhook/custom-api') ); ?>"
                onclick="this.select();" style="background:#f8fafc;color:#475569;cursor:text;">
            <p style="margin:4px 0 0;font-size:12px;color:#94a3b8;"><?php esc_html_e('Configure your payment API to POST { status, user_id, plan_id, cycle, txn_id } to this URL when a payment is confirmed.','tempmail-pro'); ?></p>
        </div>
    </div>
</div>

</div><!-- /#tab-payments -->

<!-- Social Login Tab -->
<div class="tmpmp-tab-panel" id="tab-social">

<!-- Google OAuth -->
<div class="tmpmp-mail-card">
    <p class="tmpmp-mail-section-title">
        <svg width="18" height="18" viewBox="0 0 24 24" style="vertical-align:middle;margin-right:6px;" fill="none"><path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/><path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/><path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l3.66-2.84z" fill="#FBBC05"/><path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/></svg>
        <?php esc_html_e('Google OAuth','tempmail-pro'); ?>
    </p>

    <!-- Enable toggle -->
    <div class="tmpmp-mail-field">
        <label class="tmpmp-mail-label"><?php esc_html_e('Google Login','tempmail-pro'); ?></label>
        <div style="padding-top:6px;">
            <label class="tmpmp-toggle-label">
                <input type="checkbox" name="google_login" value="1" <?php checked($settings['google_login']??0); ?>>
                <span class="tmpmp-toggle-slider"></span>
            </label>
            <span style="margin-left:10px;font-size:13px;color:#475569;vertical-align:middle;"><?php esc_html_e('Enable Google OAuth login','tempmail-pro'); ?></span>
        </div>
    </div>

    <!-- Client ID -->
    <div class="tmpmp-mail-field">
        <label class="tmpmp-mail-label" for="google_client_id"><?php esc_html_e('Client ID','tempmail-pro'); ?></label>
        <div>
            <input type="text" id="google_client_id" name="google_client_id" class="tmpmp-mail-input"
                value="<?php echo esc_attr($settings['google_client_id']??''); ?>"
                placeholder="xxxxxxxxxxxx-xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx.apps.googleusercontent.com">
            <p class="tmpmp-mail-hint"><?php esc_html_e('OAuth 2.0 Client ID from Google Cloud Console.','tempmail-pro'); ?></p>
        </div>
    </div>

    <!-- Client Secret -->
    <div class="tmpmp-mail-field">
        <label class="tmpmp-mail-label" for="google_client_secret"><?php esc_html_e('Client Secret','tempmail-pro'); ?></label>
        <div>
            <div style="display:flex;gap:8px;align-items:center;">
                <input type="password" id="google_client_secret" name="google_client_secret" class="tmpmp-mail-input"
                    value="<?php echo esc_attr($settings['google_client_secret']??''); ?>"
                    placeholder="GOCSPX-••••••••••••••••••••••••••••"
                    style="flex:1;">
                <button type="button" class="tmpmp-oauth-show-btn" data-target="google_client_secret"
                    style="padding:8px 14px;background:#fff;border:1.5px solid #6366f1;border-radius:8px;font-size:12px;font-weight:700;color:#6366f1;cursor:pointer;white-space:nowrap;">
                    👁 <?php esc_html_e('Show','tempmail-pro'); ?>
                </button>
            </div>
            <p class="tmpmp-mail-hint">
                <?php esc_html_e('OAuth 2.0 Client Secret from Google Cloud Console.','tempmail-pro'); ?>
                <a href="https://console.cloud.google.com/apis/credentials" target="_blank" style="color:#6366f1;margin-left:4px;">
                    <?php esc_html_e('Open Google Console ↗','tempmail-pro'); ?>
                </a>
            </p>
        </div>
    </div>

    <!-- Redirect URI hint -->
    <div class="tmpmp-mail-field">
        <label class="tmpmp-mail-label"><?php esc_html_e('Authorised Redirect URI','tempmail-pro'); ?></label>
        <div>
            <div style="display:flex;gap:8px;align-items:center;">
                <input type="text" class="tmpmp-mail-input" readonly id="google-redirect-uri"
                    value="<?php echo esc_attr( rest_url('tempmail-pro/v1/auth/google/callback') ); ?>"
                    style="background:#f8fafc;color:#475569;font-family:monospace;font-size:12.5px;">
                <button type="button" class="tmpmp-oauth-copy-btn" data-target="google-redirect-uri"
                    style="padding:8px 14px;background:#ede9fe;color:#6366f1;border:none;border-radius:8px;font-size:12px;font-weight:700;cursor:pointer;">
                    📋 <?php esc_html_e('Copy','tempmail-pro'); ?>
                </button>
            </div>
            <p class="tmpmp-mail-hint"><?php esc_html_e('Add this exact URL to Authorised Redirect URIs in your Google Cloud OAuth app.','tempmail-pro'); ?></p>
        </div>
    </div>
</div>

<!-- Facebook OAuth -->
<div class="tmpmp-mail-card">
    <p class="tmpmp-mail-section-title">
        <svg width="18" height="18" viewBox="0 0 24 24" style="vertical-align:middle;margin-right:6px;" fill="#1877F2"><path d="M24 12.073C24 5.404 18.627 0 12 0S0 5.404 0 12.073C0 18.1 4.388 23.094 10.125 24v-8.437H7.078v-3.49h3.047V9.41c0-3.025 1.792-4.697 4.533-4.697 1.312 0 2.686.236 2.686.236v2.97h-1.513c-1.491 0-1.956.93-1.956 1.886v2.267h3.328l-.532 3.49h-2.796V24C19.612 23.094 24 18.1 24 12.073z"/></svg>
        <?php esc_html_e('Facebook OAuth','tempmail-pro'); ?>
    </p>

    <!-- Enable toggle -->
    <div class="tmpmp-mail-field">
        <label class="tmpmp-mail-label"><?php esc_html_e('Facebook Login','tempmail-pro'); ?></label>
        <div style="padding-top:6px;">
            <label class="tmpmp-toggle-label">
                <input type="checkbox" name="facebook_login" value="1" <?php checked($settings['facebook_login']??0); ?>>
                <span class="tmpmp-toggle-slider"></span>
            </label>
            <span style="margin-left:10px;font-size:13px;color:#475569;vertical-align:middle;"><?php esc_html_e('Enable Facebook OAuth login','tempmail-pro'); ?></span>
        </div>
    </div>

    <!-- App ID -->
    <div class="tmpmp-mail-field">
        <label class="tmpmp-mail-label" for="facebook_app_id"><?php esc_html_e('App ID (Client ID)','tempmail-pro'); ?></label>
        <div>
            <input type="text" id="facebook_app_id" name="facebook_app_id" class="tmpmp-mail-input"
                value="<?php echo esc_attr($settings['facebook_app_id']??''); ?>"
                placeholder="1234567890123456">
            <p class="tmpmp-mail-hint"><?php esc_html_e('Your Facebook App ID from Meta for Developers.','tempmail-pro'); ?></p>
        </div>
    </div>

    <!-- App Secret -->
    <div class="tmpmp-mail-field">
        <label class="tmpmp-mail-label" for="facebook_app_secret"><?php esc_html_e('App Secret (Client Secret)','tempmail-pro'); ?></label>
        <div>
            <div style="display:flex;gap:8px;align-items:center;">
                <input type="password" id="facebook_app_secret" name="facebook_app_secret" class="tmpmp-mail-input"
                    value="<?php echo esc_attr($settings['facebook_app_secret']??''); ?>"
                    placeholder="••••••••••••••••••••••••••••••••"
                    style="flex:1;">
                <button type="button" class="tmpmp-oauth-show-btn" data-target="facebook_app_secret"
                    style="padding:8px 14px;background:#fff;border:1.5px solid #6366f1;border-radius:8px;font-size:12px;font-weight:700;color:#6366f1;cursor:pointer;white-space:nowrap;">
                    👁 <?php esc_html_e('Show','tempmail-pro'); ?>
                </button>
            </div>
            <p class="tmpmp-mail-hint">
                <?php esc_html_e('Facebook App Secret from Meta Developer Dashboard.','tempmail-pro'); ?>
                <a href="https://developers.facebook.com/apps/" target="_blank" style="color:#6366f1;margin-left:4px;">
                    <?php esc_html_e('Open Meta Developers ↗','tempmail-pro'); ?>
                </a>
            </p>
        </div>
    </div>

    <!-- Redirect URI hint -->
    <div class="tmpmp-mail-field">
        <label class="tmpmp-mail-label"><?php esc_html_e('Valid OAuth Redirect URI','tempmail-pro'); ?></label>
        <div>
            <div style="display:flex;gap:8px;align-items:center;">
                <input type="text" class="tmpmp-mail-input" readonly id="fb-redirect-uri"
                    value="<?php echo esc_attr( rest_url('tempmail-pro/v1/auth/facebook/callback') ); ?>"
                    style="background:#f8fafc;color:#475569;font-family:monospace;font-size:12.5px;">
                <button type="button" class="tmpmp-oauth-copy-btn" data-target="fb-redirect-uri"
                    style="padding:8px 14px;background:#ede9fe;color:#6366f1;border:none;border-radius:8px;font-size:12px;font-weight:700;cursor:pointer;">
                    📋 <?php esc_html_e('Copy','tempmail-pro'); ?>
                </button>
            </div>
            <p class="tmpmp-mail-hint"><?php esc_html_e('Add this URL to Valid OAuth Redirect URIs in your Facebook App settings.','tempmail-pro'); ?></p>
        </div>
    </div>
</div>

<script>
jQuery(function($){
    // Show / Hide secret fields
    $(document).on('click', '.tmpmp-oauth-show-btn', function(){
        const id  = $(this).data('target');
        const inp = document.getElementById(id);
        if (!inp) return;
        const show = inp.type === 'password';
        inp.type = show ? 'text' : 'password';
        $(this).html(show
            ? '🔒 <?php echo esc_js(__('Hide','tempmail-pro')); ?>'
            : '👁 <?php echo esc_js(__('Show','tempmail-pro')); ?>');
    });

    // Copy redirect URI buttons — works on HTTP and HTTPS
    function tmpmpOAuthCopy(text, $btn, origHtml) {
        const done = function() {
            $btn.html('✓ <?php echo esc_js(__('Copied!','tempmail-pro')); ?>').css({'background':'#d1fae5','color':'#059669'});
            setTimeout(function(){ $btn.html(origHtml).css({'background':'#ede9fe','color':'#6366f1'}); }, 2000);
        };
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(text).then(done).catch(function(){
                tmpmpOAuthExecCopy(text, done);
            });
        } else {
            tmpmpOAuthExecCopy(text, done);
        }
    }
    function tmpmpOAuthExecCopy(text, cb) {
        var ta = document.createElement('textarea');
        ta.value = text;
        ta.style.cssText = 'position:fixed;top:0;left:0;opacity:0;pointer-events:none;';
        document.body.appendChild(ta);
        ta.focus(); ta.select();
        try { document.execCommand('copy'); if (cb) cb(); } catch(e) {}
        document.body.removeChild(ta);
    }
    $(document).on('click', '.tmpmp-oauth-copy-btn', function(){
        const id  = $(this).data('target');
        const inp = document.getElementById(id);
        if (!inp) return;
        tmpmpOAuthCopy(inp.value.trim(), $(this), $(this).html());
    });
});
</script>

</div><!-- /#tab-social -->


<!-- Ads Tab -->
<div class="tmpmp-tab-panel" id="tab-ads">
<div class="tmpmp-mail-card">
    <p class="tmpmp-mail-section-title">📢 <?php esc_html_e('Ad Placements','tempmail-pro'); ?></p>
    <div class="tmpmp-mail-field">
        <label class="tmpmp-mail-label" for="adsense_code"><?php esc_html_e('Google AdSense Code','tempmail-pro'); ?></label>
        <div>
            <textarea id="adsense_code" name="adsense_code" rows="6" class="tmpmp-mail-input" style="height:auto;resize:vertical;font-family:monospace;font-size:12px;"
                placeholder="&lt;script async src=&quot;https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js&quot;&gt;&lt;/script&gt;"><?php echo esc_textarea($settings['adsense_code']??''); ?></textarea>
            <p class="tmpmp-mail-hint"><?php esc_html_e('Paste your AdSense auto-ads or individual ad unit code here.','tempmail-pro'); ?></p>
        </div>
    </div>
</div>
</div><!-- /#tab-ads -->

<!-- Security Tab -->
<div class="tmpmp-tab-panel" id="tab-security">
<div class="tmpmp-mail-card">
    <p class="tmpmp-mail-section-title">🛡️ <?php esc_html_e('Access & Bot Protection','tempmail-pro'); ?></p>

    <?php
    $captcha_provider   = $settings['captcha_provider']   ?? 'recaptcha_v2';
    $captcha_site_key   = $settings['captcha_site_key']   ?? '';
    $captcha_secret_key = $settings['captcha_secret_key'] ?? '';
    ?>

    <!-- Enable toggle -->
    <div class="tmpmp-mail-field">
        <label class="tmpmp-mail-label"><?php esc_html_e('Enable CAPTCHA','tempmail-pro'); ?></label>
        <div style="padding-top:6px;">
            <label class="tmpmp-toggle-label">
                <input type="checkbox" id="enable_captcha" name="enable_captcha" value="1" <?php checked($settings['enable_captcha']??0); ?>>
                <span class="tmpmp-toggle-slider"></span>
            </label>
            <span style="margin-left:10px;font-size:13px;color:#475569;vertical-align:middle;"><?php esc_html_e('Show CAPTCHA challenge on inbox generation','tempmail-pro'); ?></span>
        </div>
    </div>

    <!-- Provider selector -->
    <div class="tmpmp-mail-field tmpmp-captcha-config" id="tmpmp-captcha-fields">
        <label class="tmpmp-mail-label"><?php esc_html_e('CAPTCHA Provider','tempmail-pro'); ?></label>
        <div>
            <div class="tmpmp-captcha-provider-grid" id="tmpmp-captcha-providers">
                <?php
                $providers = [
                    'recaptcha_v2'  => ['label'=>'reCAPTCHA v2',      'icon'=>'🔲', 'badge'=>'Google',     'color'=>'#4285f4'],
                    'recaptcha_v3'  => ['label'=>'reCAPTCHA v3',      'icon'=>'🤖', 'badge'=>'Google',     'color'=>'#34a853'],
                    'hcaptcha'      => ['label'=>'hCaptcha',           'icon'=>'🧩', 'badge'=>'Privacy',    'color'=>'#ff9800'],
                    'turnstile'     => ['label'=>'Turnstile',          'icon'=>'☁️', 'badge'=>'Cloudflare', 'color'=>'#f6821f'],
                ];
                foreach ($providers as $key => $p):
                    $active = ($captcha_provider === $key);
                ?>
                <label style="cursor:pointer;">
                    <input type="radio" name="captcha_provider" value="<?php echo esc_attr($key); ?>"
                        class="tmpmp-captcha-radio" <?php checked($captcha_provider, $key); ?>
                        style="display:none;">
                    <div class="tmpmp-captcha-option <?php echo $active ? 'tmpmp-captcha-option--active' : ''; ?>"
                        style="border-color:<?php echo $active ? esc_attr($p['color']) : '#e2e8f0'; ?>;background:<?php echo $active ? esc_attr($p['color']).'18' : '#fff'; ?>;">
                        <div class="tmpmp-captcha-option-icon"><?php echo $p['icon']; ?></div>
                        <div class="tmpmp-captcha-option-name"><?php echo esc_html($p['label']); ?></div>
                        <div class="tmpmp-captcha-option-badge" style="color:<?php echo esc_attr($p['color']); ?>"><?php echo esc_html($p['badge']); ?></div>
                    </div>
                </label>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Site Key -->
    <div class="tmpmp-mail-field tmpmp-captcha-config">
        <label class="tmpmp-mail-label" for="captcha_site_key"><?php esc_html_e('Site Key','tempmail-pro'); ?></label>
        <div>
            <input type="text" id="captcha_site_key" name="captcha_site_key" class="tmpmp-mail-input"
                value="<?php echo esc_attr($captcha_site_key); ?>"
                placeholder="<?php esc_attr_e('Paste your Site Key here…','tempmail-pro'); ?>">
            <p class="tmpmp-mail-hint tmpmp-captcha-hint" id="captcha-hint-site"></p>
        </div>
    </div>

    <!-- Secret Key -->
    <div class="tmpmp-mail-field tmpmp-captcha-config">
        <label class="tmpmp-mail-label" for="captcha_secret_key"><?php esc_html_e('Secret Key','tempmail-pro'); ?></label>
        <div>
            <div style="display:flex;gap:8px;align-items:center;">
                <input type="password" id="captcha_secret_key" name="captcha_secret_key" class="tmpmp-mail-input"
                    value="<?php echo esc_attr($captcha_secret_key); ?>"
                    placeholder="<?php esc_attr_e('Paste your Secret Key here…','tempmail-pro'); ?>"
                    style="flex:1;">
                <button type="button" id="tmpmp-captcha-show-secret"
                    style="padding:8px 14px;background:#fff;border:1.5px solid #6366f1;border-radius:8px;font-size:12px;font-weight:700;color:#6366f1;cursor:pointer;white-space:nowrap;">
                    👁 <?php esc_html_e('Show','tempmail-pro'); ?>
                </button>
            </div>
            <p class="tmpmp-mail-hint tmpmp-captcha-hint" id="captcha-hint-secret"></p>
        </div>
    </div>

    <!-- Console link panel -->
    <div class="tmpmp-captcha-config" id="tmpmp-captcha-console-link">
    </div>

</div>

<script>
jQuery(function($){
    const hints = {
        recaptcha_v2: {
            site:   '<?php echo esc_js(__('reCAPTCHA v2 Site Key — from Google reCAPTCHA Admin Console.','tempmail-pro')); ?>',
            secret: '<?php echo esc_js(__('reCAPTCHA v2 Secret Key — used to verify the challenge server-side.','tempmail-pro')); ?>',
            link:   'https://www.google.com/recaptcha/admin/create',
            label:  '<?php echo esc_js(__('Open Google reCAPTCHA Console ↗','tempmail-pro')); ?>',
            color:  '#4285f4',
        },
        recaptcha_v3: {
            site:   '<?php echo esc_js(__('reCAPTCHA v3 Site Key — from Google reCAPTCHA Admin Console (v3 badge).','tempmail-pro')); ?>',
            secret: '<?php echo esc_js(__('reCAPTCHA v3 Secret Key — used to verify the score server-side.','tempmail-pro')); ?>',
            link:   'https://www.google.com/recaptcha/admin/create',
            label:  '<?php echo esc_js(__('Open Google reCAPTCHA Console ↗','tempmail-pro')); ?>',
            color:  '#34a853',
        },
        hcaptcha: {
            site:   '<?php echo esc_js(__('hCaptcha Site Key — from your hCaptcha Dashboard under Sites.','tempmail-pro')); ?>',
            secret: '<?php echo esc_js(__('hCaptcha Secret Key — from Settings → Secret Key in your hCaptcha account.','tempmail-pro')); ?>',
            link:   'https://dashboard.hcaptcha.com/sites',
            label:  '<?php echo esc_js(__('Open hCaptcha Dashboard ↗','tempmail-pro')); ?>',
            color:  '#ff9800',
        },
        turnstile: {
            site:   '<?php echo esc_js(__('Cloudflare Turnstile Site Key — from Zero Trust → Turnstile in Cloudflare dashboard.','tempmail-pro')); ?>',
            secret: '<?php echo esc_js(__('Cloudflare Turnstile Secret Key — from the same widget settings page.','tempmail-pro')); ?>',
            link:   'https://dash.cloudflare.com/?to=/:account/turnstile',
            label:  '<?php echo esc_js(__('Open Cloudflare Turnstile Dashboard ↗','tempmail-pro')); ?>',
            color:  '#f6821f',
        },
    };

    function applyProvider(val) {
        const h = hints[val] || hints.recaptcha_v2;
        $('#captcha-hint-site').text(h.site);
        $('#captcha-hint-secret').text(h.secret);
        $('#tmpmp-captcha-console-link').html(
            '<a href="'+h.link+'" target="_blank" class="tmpmp-captcha-console-btn">🔗 '+h.label+'</a>'
        );

        // Highlight selected card
        $('#tmpmp-captcha-providers .tmpmp-captcha-option').each(function(){
            const $lbl = $(this).closest('label');
            const radio = $lbl.find('input[type=radio]');
            const active = radio.val() === val;
            const color = hints[radio.val()]?.color || '#e2e8f0';
            $(this)
                .toggleClass('tmpmp-captcha-option--active', active)
                .css({
                    'border-color': active ? color : '#e2e8f0',
                    'background':   active ? color+'18' : '#fff',
                });
        });
    }

    // Boot with saved value
    applyProvider($('input[name="captcha_provider"]:checked').val() || 'recaptcha_v2');

    // Provider card click
    $(document).on('click', '.tmpmp-captcha-option', function(){
        const $radio = $(this).closest('label').find('input[type=radio]');
        $radio.prop('checked', true);
        applyProvider($radio.val());
    });

    // Enable toggle — only toggles active state visually, settings always accessible
    $('#enable_captcha').on('change', function(){
        // Nothing to hide — fields always visible
    });

    // Show / Hide secret key
    $('#tmpmp-captcha-show-secret').on('click', function(){
        const inp = document.getElementById('captcha_secret_key');
        const show = inp.type === 'password';
        inp.type = show ? 'text' : 'password';
        $(this).html(show
            ? '🔒 <?php echo esc_js(__('Hide','tempmail-pro')); ?>'
            : '👁 <?php echo esc_js(__('Show','tempmail-pro')); ?>');
    });
});
</script>

</div><!-- /#tab-security -->

<!-- ----------------------- Email Generation Tab ----------------------- -->
<div class="tmpmp-tab-panel" id="tab-emailgen">
<?php
$eg_format     = $settings['eg_format']      ?? 'adj_noun_num';
$eg_sep        = $settings['eg_separator']   ?? '_';
$eg_num_sfx    = $settings['eg_num_suffix']  ?? 'always';
$eg_num_min    = intval($settings['eg_num_min']    ?? 100);
$eg_num_max    = intval($settings['eg_num_max']    ?? 999);
$eg_char_len   = intval($settings['eg_char_length'] ?? 8);
$eg_char_set   = $settings['eg_char_set']    ?? 'alphanumeric';
$eg_adj_list   = $settings['eg_adj_list']    ?? '';
$eg_noun_list  = $settings['eg_noun_list']   ?? '';
?>
<!-- USERNAME FORMAT -->
<div class="tmpmp-mail-card">
  <div class="tmpmp-eg-section-heading"><span class="tmpmp-eg-section-line"></span><span class="tmpmp-eg-section-label"><?php esc_html_e('USERNAME FORMAT','tempmail-pro'); ?></span><span class="tmpmp-eg-section-line"></span></div>
  <div class="tmpmp-mail-field">
    <label class="tmpmp-mail-label" for="eg_format"><?php esc_html_e('Format Style','tempmail-pro'); ?></label>
    <div>
      <select name="eg_format" id="eg_format" class="tmpmp-mail-input tmpmp-eg-select">
        <option value="adj_noun_num" <?php selected($eg_format,'adj_noun_num'); ?>>🔥 <?php esc_html_e('Adjective + Noun + Number (e.g. swift_fox_42)','tempmail-pro'); ?></option>
        <option value="adj_noun"     <?php selected($eg_format,'adj_noun'); ?>>🐾 <?php esc_html_e('Adjective + Noun (e.g. swift_fox)','tempmail-pro'); ?></option>
        <option value="noun_num"     <?php selected($eg_format,'noun_num'); ?>>🔢 <?php esc_html_e('Noun + Number (e.g. fox_42)','tempmail-pro'); ?></option>
        <option value="random_chars" <?php selected($eg_format,'random_chars'); ?>>🎲 <?php esc_html_e('Random Characters (e.g. k7xqm2)','tempmail-pro'); ?></option>
        <option value="short_uuid"   <?php selected($eg_format,'short_uuid'); ?>>🆔 <?php esc_html_e('Short UUID (e.g. a1b2c3d4)','tempmail-pro'); ?></option>
      </select>
      <p class="tmpmp-mail-hint"><?php esc_html_e('Determines how the random part of the generated email address looks.','tempmail-pro'); ?></p>
    </div>
  </div>
  <div class="tmpmp-mail-field tmpmp-eg-adj-noun-only">
    <label class="tmpmp-mail-label" for="eg_separator"><?php esc_html_e('Word Separator','tempmail-pro'); ?></label>
    <div>
      <select name="eg_separator" id="eg_separator" class="tmpmp-mail-input tmpmp-eg-select">
        <option value="_" <?php selected($eg_sep,'_'); ?>><?php esc_html_e('Underscore _','tempmail-pro'); ?></option>
        <option value="-" <?php selected($eg_sep,'-'); ?>><?php esc_html_e('Hyphen -','tempmail-pro'); ?></option>
        <option value="." <?php selected($eg_sep,'.'); ?>><?php esc_html_e('Dot .','tempmail-pro'); ?></option>
        <option value=""  <?php selected($eg_sep,''); ?>><?php esc_html_e('None (no separator)','tempmail-pro'); ?></option>
      </select>
      <p class="tmpmp-mail-hint"><?php esc_html_e('Character placed between words. Applies to adj_noun formats.','tempmail-pro'); ?></p>
    </div>
  </div>
  <div class="tmpmp-mail-field tmpmp-eg-adj-noun-only">
    <label class="tmpmp-mail-label" for="eg_num_suffix"><?php esc_html_e('Number Suffix','tempmail-pro'); ?></label>
    <div>
      <select name="eg_num_suffix" id="eg_num_suffix" class="tmpmp-mail-input tmpmp-eg-select">
        <option value="always" <?php selected($eg_num_sfx,'always'); ?>><?php esc_html_e('Always add number','tempmail-pro'); ?></option>
        <option value="random" <?php selected($eg_num_sfx,'random'); ?>><?php esc_html_e('Add number randomly (50%)','tempmail-pro'); ?></option>
        <option value="never"  <?php selected($eg_num_sfx,'never'); ?>><?php esc_html_e('Never add number','tempmail-pro'); ?></option>
      </select>
    </div>
  </div>
  <div class="tmpmp-mail-field tmpmp-eg-adj-noun-only" id="tmpmp-eg-num-range-row">
    <label class="tmpmp-mail-label"><?php esc_html_e('Number Range','tempmail-pro'); ?></label>
    <div>
      <div class="tmpmp-eg-range-wrap">
        <input type="number" name="eg_num_min" id="eg_num_min" class="tmpmp-mail-input tmpmp-eg-num-input" value="<?php echo esc_attr($eg_num_min); ?>" min="1" max="99999">
        <span class="tmpmp-eg-range-sep">&#8212;</span>
        <input type="number" name="eg_num_max" id="eg_num_max" class="tmpmp-mail-input tmpmp-eg-num-input" value="<?php echo esc_attr($eg_num_max); ?>" min="1" max="99999">
        <span class="tmpmp-eg-range-hint"><?php esc_html_e('Min/max for the numeric suffix.','tempmail-pro'); ?></span>
      </div>
    </div>
  </div>
</div>
<!-- RANDOM CHARACTER SETTINGS -->
<div class="tmpmp-mail-card" id="tmpmp-eg-random-card">
  <div class="tmpmp-eg-section-heading"><span class="tmpmp-eg-section-line"></span><span class="tmpmp-eg-section-label"><?php esc_html_e('RANDOM CHARACTER SETTINGS','tempmail-pro'); ?></span><span class="tmpmp-eg-section-line"></span></div>
  <div class="tmpmp-mail-field">
    <label class="tmpmp-mail-label" for="eg_char_length"><?php esc_html_e('Character Length','tempmail-pro'); ?></label>
    <div>
      <input type="number" name="eg_char_length" id="eg_char_length" class="tmpmp-mail-input" style="max-width:90px;" value="<?php echo esc_attr($eg_char_len); ?>" min="4" max="24">
      <p class="tmpmp-mail-hint"><?php esc_html_e('Length of generated string for "Random Characters" and "Short UUID" formats (4�24).','tempmail-pro'); ?></p>
    </div>
  </div>
  <div class="tmpmp-mail-field">
    <label class="tmpmp-mail-label" for="eg_char_set"><?php esc_html_e('Character Set','tempmail-pro'); ?></label>
    <div>
      <select name="eg_char_set" id="eg_char_set" class="tmpmp-mail-input tmpmp-eg-select">
        <option value="alphanumeric" <?php selected($eg_char_set,'alphanumeric'); ?>><?php esc_html_e('Letters + Numbers (abc123)','tempmail-pro'); ?></option>
        <option value="alpha"        <?php selected($eg_char_set,'alpha'); ?>><?php esc_html_e('Letters only (abcdef)','tempmail-pro'); ?></option>
        <option value="numeric"      <?php selected($eg_char_set,'numeric'); ?>><?php esc_html_e('Numbers only (123456)','tempmail-pro'); ?></option>
      </select>
    </div>
  </div>
</div>
<!-- CUSTOM WORD LISTS -->
<div class="tmpmp-mail-card tmpmp-eg-adj-noun-only">
  <div class="tmpmp-eg-section-heading"><span class="tmpmp-eg-section-line"></span><span class="tmpmp-eg-section-label"><?php esc_html_e('CUSTOM WORD LISTS','tempmail-pro'); ?></span><span class="tmpmp-eg-section-line"></span></div>
  <p style="font-size:13px;color:#64748b;margin:0 0 16px;"><?php esc_html_e('Override the built-in adjective and noun pools. Leave blank to use defaults.','tempmail-pro'); ?></p>
  <div class="tmpmp-mail-field">
    <label class="tmpmp-mail-label" for="eg_adj_list"><?php esc_html_e('Custom Adjectives','tempmail-pro'); ?></label>
    <div><textarea name="eg_adj_list" id="eg_adj_list" rows="5" class="tmpmp-mail-input tmpmp-eg-wordlist" placeholder="swift&#10;bright&#10;lucky&#10;cosmic"><?php echo esc_textarea($eg_adj_list); ?></textarea><p class="tmpmp-mail-hint"><?php esc_html_e('One word per line. Used with adj_noun formats.','tempmail-pro'); ?></p></div>
  </div>
  <div class="tmpmp-mail-field">
    <label class="tmpmp-mail-label" for="eg_noun_list"><?php esc_html_e('Custom Nouns','tempmail-pro'); ?></label>
    <div><textarea name="eg_noun_list" id="eg_noun_list" rows="5" class="tmpmp-mail-input tmpmp-eg-wordlist" placeholder="fox&#10;hawk&#10;wolf&#10;puma"><?php echo esc_textarea($eg_noun_list); ?></textarea><p class="tmpmp-mail-hint"><?php esc_html_e('One word per line. Used with adj_noun formats.','tempmail-pro'); ?></p></div>
  </div>
</div>
<!-- LIVE PREVIEW -->
<div class="tmpmp-mail-card">
  <div class="tmpmp-eg-section-heading"><span class="tmpmp-eg-section-line"></span><span class="tmpmp-eg-section-label"><?php esc_html_e('LIVE PREVIEW','tempmail-pro'); ?></span><span class="tmpmp-eg-section-line"></span></div>
  <div class="tmpmp-eg-preview-wrap">
    <div class="tmpmp-eg-preview-result" id="tmpmp-eg-preview-result"><span class="tmpmp-eg-preview-placeholder">&#9993; Click &ldquo;Generate Preview&rdquo; to see a sample address&hellip;</span></div>
    <div class="tmpmp-eg-preview-actions">
      <button type="button" class="tmpmp-test-btn" id="tmpmp-eg-generate-preview">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M23 4v6h-6"/><path d="M20.49 15a9 9 0 11-2.12-9.36L23 10"/></svg>
        <?php esc_html_e('Generate Preview','tempmail-pro'); ?>
      </button>
    </div>
  </div>
  <p class="tmpmp-mail-hint" style="margin-top:8px;"><?php esc_html_e('Click to see what a generated address looks like with current settings. Save Settings first to update.','tempmail-pro'); ?></p>
  <div class="tmpmp-eg-notice">
    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#b45309" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
    <?php esc_html_e('These settings affect auto-generated addresses only. Users can always set a custom alias via the Change button.','tempmail-pro'); ?>
  </div>
</div>
</div><!-- /#tab-emailgen -->

<!-- ═══════════════════════════════════════════════════════════════════════ -->
<!-- Design Tab                                                              -->
<!-- ═══════════════════════════════════════════════════════════════════════ -->
<div class="tmpmp-tab-panel" id="tab-design">
<?php
$d_theme    = $settings['design_theme']     ?? 'dark';
$d_accent   = $settings['design_accent']    ?? '#6366f1';
$d_radius   = $settings['design_radius']    ?? '14';
$d_font     = $settings['design_font']      ?? 'Inter';
$d_max_w    = $settings['design_max_width'] ?? '780';
$d_css      = $settings['design_custom_css'] ?? '';
?>

<!-- ── Colour & Theme ───────────────────────────────────────────────────── -->
<div class="tmpmp-settings-card">
    <div class="tmpmp-card-header"><span class="tmpmp-card-icon">🎨</span><div><h3><?php esc_html_e('Theme & Colours','tempmail-pro'); ?></h3><p><?php esc_html_e('Control the colour scheme and accent colour of the inbox widget.','tempmail-pro'); ?></p></div></div>
    <div class="tmpmp-card-body">

        <div class="tmpmp-field-row">
            <label class="tmpmp-field-label"><?php esc_html_e('Default Theme','tempmail-pro'); ?></label>
            <div class="tmpmp-field-control">
                <style>
                .tmpmp-theme-option { display:flex;align-items:center;gap:6px;cursor:pointer;font-size:13px;font-weight:600;padding:8px 16px;border-radius:8px;border:1.5px solid #e2e8f0;background:#f8fafc;color:#374151;transition:all .15s; }
                .tmpmp-theme-option.is-selected { border-color:#6366f1;background:#ede9fe;color:#6366f1; }
                .tmpmp-theme-option input[type=radio] { accent-color:#6366f1; }
                </style>
                <div style="display:flex;gap:10px;flex-wrap:wrap;" id="tmpmp-theme-options">
                    <?php foreach(['dark'=>'🌙 Dark','light'=>'☀️ Light','auto'=>'🖥 Follow System'] as $val=>$lbl): ?>
                    <label class="tmpmp-theme-option<?php echo $d_theme===$val?' is-selected':''; ?>">
                        <input type="radio" name="design_theme" value="<?php echo esc_attr($val); ?>" <?php checked($d_theme,$val); ?>>
                        <?php echo esc_html($lbl); ?>
                    </label>
                    <?php endforeach; ?>
                </div>
                <p class="tmpmp-field-desc"><?php esc_html_e('"Follow System" switches automatically based on the visitor\'s OS preference.','tempmail-pro'); ?></p>
                <script>
                document.querySelectorAll('#tmpmp-theme-options input[type=radio]').forEach(function(r){
                    r.addEventListener('change', function(){
                        document.querySelectorAll('#tmpmp-theme-options .tmpmp-theme-option').forEach(function(l){ l.classList.remove('is-selected'); });
                        this.closest('.tmpmp-theme-option').classList.add('is-selected');
                    });
                });
                </script>
            </div>
        </div>

        <div class="tmpmp-field-row">
            <label class="tmpmp-field-label"><?php esc_html_e('Accent Colour','tempmail-pro'); ?></label>
            <div class="tmpmp-field-control">
                <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
                    <input type="color" name="design_accent" id="design_accent"
                        value="<?php echo esc_attr($d_accent); ?>"
                        style="width:48px;height:40px;border-radius:8px;border:1.5px solid #e2e8f0;cursor:pointer;padding:2px;">
                    <input type="text" id="design_accent_hex"
                        value="<?php echo esc_attr($d_accent); ?>"
                        maxlength="7" placeholder="#6366f1"
                        style="width:110px;padding:8px 12px;border:1.5px solid #e2e8f0;border-radius:8px;font-family:monospace;font-size:13px;">
                    <div style="display:flex;gap:8px;flex-wrap:wrap;" id="tmpmp-preset-colours">
                        <?php foreach(['#6366f1','#8b5cf6','#ec4899','#f59e0b','#10b981','#0ea5e9','#ef4444','#64748b'] as $c): ?>
                        <button type="button" onclick="document.getElementById('design_accent').value='<?php echo esc_js($c); ?>';document.getElementById('design_accent_hex').value='<?php echo esc_js($c); ?>';document.getElementById('tmpmp-preview-accent').style.setProperty('--pa','<?php echo esc_js($c); ?>');" title="<?php echo esc_attr($c); ?>"
                            style="width:28px;height:28px;border-radius:50%;border:2px solid <?php echo ($d_accent===$c)?'#000':'transparent'; ?>;background:<?php echo esc_attr($c); ?>;cursor:pointer;"></button>
                        <?php endforeach; ?>
                    </div>
                </div>
                <p class="tmpmp-field-desc"><?php esc_html_e('Used for buttons, highlights, and interactive elements throughout the widget.','tempmail-pro'); ?></p>
                <script>
                document.getElementById('design_accent').addEventListener('input',function(){
                    document.getElementById('design_accent_hex').value=this.value;
                });
                document.getElementById('design_accent_hex').addEventListener('input',function(){
                    if(/^#[0-9a-fA-F]{6}$/.test(this.value)) document.getElementById('design_accent').value=this.value;
                });
                </script>
            </div>
        </div>

    </div>
</div>

<!-- ── Layout & Typography ──────────────────────────────────────────────── -->
<div class="tmpmp-settings-card">
    <div class="tmpmp-card-header"><span class="tmpmp-card-icon">📐</span><div><h3><?php esc_html_e('Layout & Typography','tempmail-pro'); ?></h3><p><?php esc_html_e('Adjust the widget width, corner radius, and font to match your theme.','tempmail-pro'); ?></p></div></div>
    <div class="tmpmp-card-body">

        <div class="tmpmp-field-row">
            <label class="tmpmp-field-label"><?php esc_html_e('Widget Max-Width','tempmail-pro'); ?></label>
            <div class="tmpmp-field-control">
                <div style="display:flex;align-items:center;gap:10px;">
                    <input type="range" name="design_max_width" id="design_max_width"
                        min="480" max="1200" step="20" value="<?php echo esc_attr($d_max_w); ?>"
                        style="width:220px;accent-color:#6366f1;">
                    <span id="design_max_width_val" style="font-size:13px;font-weight:700;color:#6366f1;min-width:52px;"><?php echo esc_html($d_max_w); ?>px</span>
                </div>
                <p class="tmpmp-field-desc"><?php esc_html_e('Maximum width of the inbox widget. Default: 780px. Set higher for full-width layouts.','tempmail-pro'); ?></p>
                <script>document.getElementById('design_max_width').addEventListener('input',function(){ document.getElementById('design_max_width_val').textContent=this.value+'px'; });</script>
            </div>
        </div>

        <div class="tmpmp-field-row">
            <label class="tmpmp-field-label"><?php esc_html_e('Border Radius','tempmail-pro'); ?></label>
            <div class="tmpmp-field-control">
                <div style="display:flex;align-items:center;gap:10px;">
                    <input type="range" name="design_radius" id="design_radius"
                        min="0" max="28" step="2" value="<?php echo esc_attr($d_radius); ?>"
                        style="width:220px;accent-color:#6366f1;">
                    <span id="design_radius_val" style="font-size:13px;font-weight:700;color:#6366f1;min-width:36px;"><?php echo esc_html($d_radius); ?>px</span>
                </div>
                <p class="tmpmp-field-desc"><?php esc_html_e('Corner rounding for cards and inputs. 0 = sharp, 28 = very rounded.','tempmail-pro'); ?></p>
                <script>document.getElementById('design_radius').addEventListener('input',function(){ document.getElementById('design_radius_val').textContent=this.value+'px'; });</script>
            </div>
        </div>

        <div class="tmpmp-field-row">
            <label class="tmpmp-field-label"><?php esc_html_e('Font Family','tempmail-pro'); ?></label>
            <div class="tmpmp-field-control">
                <select name="design_font" id="design_font" class="tmpmp-input" style="max-width:260px;">
                    <?php foreach([
                        'Inter'       => 'Inter (Default)',
                        'Roboto'      => 'Roboto',
                        'Poppins'     => 'Poppins',
                        'Open Sans'   => 'Open Sans',
                        'Nunito'      => 'Nunito',
                        'Lato'        => 'Lato',
                        'DM Sans'     => 'DM Sans',
                        'System UI'   => 'System UI (No Google Font)',
                    ] as $val => $lbl): ?>
                    <option value="<?php echo esc_attr($val); ?>" <?php selected($d_font,$val); ?>><?php echo esc_html($lbl); ?></option>
                    <?php endforeach; ?>
                </select>
                <p class="tmpmp-field-desc"><?php esc_html_e('Google Font loaded automatically. "System UI" uses the browser default — no external request.','tempmail-pro'); ?></p>
            </div>
        </div>

    </div>
</div>

<!-- ── Custom CSS ───────────────────────────────────────────────────────── -->
<div class="tmpmp-settings-card">
    <div class="tmpmp-card-header"><span class="tmpmp-card-icon">✏️</span><div><h3><?php esc_html_e('Custom CSS','tempmail-pro'); ?></h3><p><?php esc_html_e('Add extra CSS rules targeting .tmpmp-wrap elements. Added after all plugin styles.','tempmail-pro'); ?></p></div></div>
    <div class="tmpmp-card-body">
        <div class="tmpmp-field-row">
            <label class="tmpmp-field-label"><?php esc_html_e('Additional CSS','tempmail-pro'); ?></label>
            <div class="tmpmp-field-control">
                <textarea name="design_custom_css" rows="8"
                    style="width:100%;font-family:monospace;font-size:12px;padding:12px;border:1.5px solid #e2e8f0;border-radius:8px;resize:vertical;background:#0f172a;color:#e2e8f0;line-height:1.7;"
                    placeholder="/* Example: */&#10;.tmpmp-btn-primary { border-radius: 4px !important; }&#10;.tmpmp-wrap { margin: 0 auto; }"><?php echo esc_textarea($d_css); ?></textarea>
                <p class="tmpmp-field-desc"><?php esc_html_e('Use CSS variables like var(--accent), var(--bg), var(--radius) for theme-aware rules.','tempmail-pro'); ?></p>
            </div>
        </div>
    </div>
</div>

<!-- ── Live Preview ─────────────────────────────────────────────────────── -->
<div class="tmpmp-settings-card">
    <div class="tmpmp-card-header"><span class="tmpmp-card-icon">👁️</span><div><h3><?php esc_html_e('Quick Preview','tempmail-pro'); ?></h3><p><?php esc_html_e('A sample of your accent colour and radius applied to common elements.','tempmail-pro'); ?></p></div></div>
    <div class="tmpmp-card-body">
        <div id="tmpmp-preview-accent" style="--pa:<?php echo esc_attr($d_accent); ?>;display:flex;gap:12px;align-items:center;flex-wrap:wrap;padding:16px;background:#f8fafc;border-radius:10px;">
            <button style="background:var(--pa);color:#fff;border:none;padding:9px 20px;border-radius:<?php echo esc_attr($d_radius); ?>px;font-weight:700;font-size:13px;cursor:default;">Primary Button</button>
            <button style="background:transparent;color:var(--pa);border:1.5px solid var(--pa);padding:9px 20px;border-radius:<?php echo esc_attr($d_radius); ?>px;font-weight:700;font-size:13px;cursor:default;">Outline Button</button>
            <span style="background:var(--pa);opacity:.15;color:var(--pa);padding:4px 12px;border-radius:999px;font-size:12px;font-weight:700;">Badge</span>
            <div style="width:200px;height:6px;border-radius:999px;background:#e2e8f0;overflow:hidden;"><div style="width:65%;height:100%;background:var(--pa);"></div></div>
        </div>
        <p style="font-size:12px;color:#64748b;margin-top:10px;"><?php esc_html_e('Save settings and reload your inbox page to see the full result.','tempmail-pro'); ?></p>
    </div>
</div>

</div><!-- /#tab-design -->

<!-- ═══════════════════════════════════════════════════════════════════════ -->
<!-- FAQ Tab                                                                 -->
<!-- ═══════════════════════════════════════════════════════════════════════ -->
<div class="tmpmp-tab-panel" id="tab-faq">
<?php
$faq_enabled    = $settings['faq_enabled']    ?? 1;
$faq_title      = $settings['faq_title']      ?? 'Frequently Asked Questions';
$faq_position   = $settings['faq_position']   ?? 'below';
$faq_accordion  = $settings['faq_accordion']  ?? 'single';
$faq_icon_open  = $settings['faq_icon_open']  ?? '−';
$faq_icon_shut  = $settings['faq_icon_shut']  ?? '+';
$faq_items_raw  = $settings['faq_items']      ?? '';
$faq_items      = [];
if ( $faq_items_raw ) {
    $decoded = json_decode( stripslashes($faq_items_raw), true );
    if ( is_array($decoded) ) $faq_items = $decoded;
}
if ( empty($faq_items) ) $faq_items = TempMail_FAQ::default_items();
?>

<!-- ── General Settings ─────────────────────────────────────────────────── -->
<div class="tmpmp-settings-card">
    <div class="tmpmp-card-header"><span class="tmpmp-card-icon">❓</span><div><h3><?php esc_html_e('FAQ Section','tempmail-pro'); ?></h3><p><?php esc_html_e('Display an accordion FAQ below or above the inbox widget on the front end.','tempmail-pro'); ?></p></div></div>
    <div class="tmpmp-card-body">

        <div class="tmpmp-field-row">
            <label class="tmpmp-field-label"><?php esc_html_e('Enable FAQ Section','tempmail-pro'); ?></label>
            <div class="tmpmp-field-control">
                <label class="tmpmp-toggle-label">
                    <input type="checkbox" name="faq_enabled" value="1" <?php checked($faq_enabled,1); ?>>
                    <span class="tmpmp-toggle-slider"></span>
                </label>
                <p class="tmpmp-field-desc" style="margin-top:6px;"><?php esc_html_e('Show FAQ section on front end','tempmail-pro'); ?></p>
            </div>
        </div>

        <div class="tmpmp-field-row">
            <label class="tmpmp-field-label"><?php esc_html_e('Section Title','tempmail-pro'); ?></label>
            <div class="tmpmp-field-control">
                <input type="text" name="faq_title" value="<?php echo esc_attr($faq_title); ?>" class="tmpmp-input" style="max-width:380px;" placeholder="Frequently Asked Questions">
                <p class="tmpmp-field-desc"><?php esc_html_e('Leave empty to hide the title row.','tempmail-pro'); ?></p>
            </div>
        </div>

        <div class="tmpmp-field-row">
            <label class="tmpmp-field-label"><?php esc_html_e('Position','tempmail-pro'); ?></label>
            <div class="tmpmp-field-control">
                <div class="tmpmp-faq-option-group">
                    <?php foreach(['below'=>'⬇️ Below inbox','above'=>'⬆️ Above inbox'] as $val=>$lbl): ?>
                    <label class="tmpmp-faq-option">
                        <input type="radio" name="faq_position" value="<?php echo esc_attr($val); ?>" <?php checked($faq_position,$val); ?>>
                        <?php echo esc_html($lbl); ?>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <div class="tmpmp-field-row">
            <label class="tmpmp-field-label"><?php esc_html_e('Accordion Mode','tempmail-pro'); ?></label>
            <div class="tmpmp-field-control">
                <div class="tmpmp-faq-option-group">
                    <?php foreach(['single'=>'🔒 Open one at a time','multiple'=>'📂 Allow multiple open'] as $val=>$lbl): ?>
                    <label class="tmpmp-faq-option">
                        <input type="radio" name="faq_accordion" value="<?php echo esc_attr($val); ?>" <?php checked($faq_accordion,$val); ?>>
                        <?php echo esc_html($lbl); ?>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <div class="tmpmp-field-row">
            <label class="tmpmp-field-label"><?php esc_html_e('Toggle Icons','tempmail-pro'); ?></label>
            <div class="tmpmp-field-control">
                <div class="tmpmp-faq-icons-row">
                    <label class="tmpmp-faq-icon-label">
                        <?php esc_html_e('Open:','tempmail-pro'); ?>
                        <input type="text" name="faq_icon_open" value="<?php echo esc_attr($faq_icon_open); ?>" maxlength="4" class="tmpmp-faq-icon-input">
                    </label>
                    <label class="tmpmp-faq-icon-label">
                        <?php esc_html_e('Closed:','tempmail-pro'); ?>
                        <input type="text" name="faq_icon_shut" value="<?php echo esc_attr($faq_icon_shut); ?>" maxlength="4" class="tmpmp-faq-icon-input">
                    </label>
                    <span style="font-size:12px;color:#94a3b8;"><?php esc_html_e('Use any character, emoji, or symbol.','tempmail-pro'); ?></span>
                </div>
            </div>
        </div>


    </div>
</div>


<!-- ── FAQ Items Editor ─────────────────────────────────────────────────── -->
<div class="tmpmp-settings-card">
    <div class="tmpmp-card-header">
        <span class="tmpmp-card-icon">📝</span>
        <div>
            <h3><?php esc_html_e('FAQ Items','tempmail-pro'); ?> <span id="tmpmp-faq-count-badge" style="background:#ede9fe;color:#5b21b6;font-size:11px;font-weight:800;padding:2px 9px;border-radius:4px;margin-left:8px;"><?php echo count($faq_items); ?></span></h3>
            <p><?php esc_html_e('Add, edit, reorder and remove FAQ questions and answers.','tempmail-pro'); ?></p>
        </div>
    </div>
    <div class="tmpmp-card-body">

        <!-- Hidden JSON field submitted with the form -->
        <input type="hidden" name="faq_items" id="tmpmp-faq-items-json" value="<?php echo esc_attr( wp_json_encode($faq_items) ); ?>">

        <div id="tmpmp-faq-list" style="display:flex;flex-direction:column;gap:10px;margin-bottom:16px;">
            <?php foreach($faq_items as $idx=>$item): ?>
            <div class="tmpmp-faq-row" data-idx="<?php echo $idx; ?>" style="background:#f8fafc;border:1.5px solid #e2e8f0;border-radius:10px;padding:14px 16px;">
                <div style="display:flex;align-items:center;gap:8px;margin-bottom:10px;">
                    <span class="tmpmp-faq-drag" title="Drag to reorder" style="cursor:grab;color:#94a3b8;font-size:18px;">⠿</span>
                    <span style="font-size:12px;font-weight:700;color:#6366f1;background:#ede9fe;padding:2px 8px;border-radius:4px;">Q<?php echo $idx+1; ?></span>
                    <button type="button" class="tmpmp-faq-remove button-link" style="margin-left:auto;color:#ef4444;font-size:12px;font-weight:600;">✕ <?php esc_html_e('Remove','tempmail-pro'); ?></button>
                </div>
                <input type="text" class="tmpmp-faq-q-input tmpmp-input" placeholder="<?php esc_attr_e('Question…','tempmail-pro'); ?>" value="<?php echo esc_attr($item['q']??''); ?>" style="width:100%;margin-bottom:8px;">
                <textarea class="tmpmp-faq-a-input tmpmp-input" rows="3" placeholder="<?php esc_attr_e('Answer — supports basic HTML…','tempmail-pro'); ?>" style="width:100%;resize:vertical;"><?php echo esc_textarea($item['a']??''); ?></textarea>
            </div>
            <?php endforeach; ?>
        </div>

        <button type="button" id="tmpmp-faq-add" class="tmpmp-test-btn" style="background:#6366f1;color:#fff;border:none;">
            + <?php esc_html_e('Add New Question','tempmail-pro'); ?>
        </button>

        <script>
        (function(){
            var list    = document.getElementById('tmpmp-faq-list');
            var jsonFld = document.getElementById('tmpmp-faq-items-json');
            var badge   = document.getElementById('tmpmp-faq-count-badge');
            var addBtn  = document.getElementById('tmpmp-faq-add');

            function sync(){
                var rows = list.querySelectorAll('.tmpmp-faq-row');
                var data = [];
                rows.forEach(function(row,i){
                    var q = row.querySelector('.tmpmp-faq-q-input').value.trim();
                    var a = row.querySelector('.tmpmp-faq-a-input').value.trim();
                    row.querySelector('span[style*="Q"]').textContent = 'Q'+(i+1);
                    if(q||a) data.push({q:q,a:a});
                });
                jsonFld.value = JSON.stringify(data);
                badge.textContent = data.length;
            }

            function makeRow(q,a,idx){
                var div = document.createElement('div');
                div.className = 'tmpmp-faq-row';
                div.dataset.idx = idx;
                div.style.cssText = 'background:#f8fafc;border:1.5px solid #e2e8f0;border-radius:10px;padding:14px 16px;';
                div.innerHTML = '<div style="display:flex;align-items:center;gap:8px;margin-bottom:10px;">'
                    +'<span class="tmpmp-faq-drag" style="cursor:grab;color:#94a3b8;font-size:18px;">⠿</span>'
                    +'<span style="font-size:12px;font-weight:700;color:#6366f1;background:#ede9fe;padding:2px 8px;border-radius:4px;">Q'+(idx+1)+'</span>'
                    +'<button type="button" class="tmpmp-faq-remove button-link" style="margin-left:auto;color:#ef4444;font-size:12px;font-weight:600;">✕ <?php esc_js( esc_html__('Remove','tempmail-pro') ); ?></button>'
                    +'</div>'
                    +'<input type="text" class="tmpmp-faq-q-input tmpmp-input" placeholder="<?php echo esc_js( esc_attr__('Question…','tempmail-pro') ); ?>" value="'+q.replace(/"/g,'&quot;')+'" style="width:100%;margin-bottom:8px;">'
                    +'<textarea class="tmpmp-faq-a-input tmpmp-input" rows="3" placeholder="<?php echo esc_js( esc_attr__('Answer — supports basic HTML…','tempmail-pro') ); ?>" style="width:100%;resize:vertical;">'+a+'</textarea>';
                bindRow(div);
                return div;
            }

            function bindRow(row){
                row.querySelector('.tmpmp-faq-remove').addEventListener('click',function(){
                    row.remove(); sync();
                });
                row.querySelectorAll('input,textarea').forEach(function(el){
                    el.addEventListener('input', sync);
                });
            }

            // Bind existing rows
            list.querySelectorAll('.tmpmp-faq-row').forEach(bindRow);

            // Add new
            addBtn.addEventListener('click',function(){
                var idx = list.querySelectorAll('.tmpmp-faq-row').length;
                var row = makeRow('','',idx);
                list.appendChild(row);
                row.querySelector('.tmpmp-faq-q-input').focus();
                sync();
            });

            // Drag-to-reorder (simple)
            var dragging = null;
            list.addEventListener('dragstart',function(e){
                dragging = e.target.closest('.tmpmp-faq-row');
                if(dragging) dragging.style.opacity='0.5';
            });
            list.addEventListener('dragend',function(){
                if(dragging){ dragging.style.opacity='1'; dragging=null; sync(); }
            });
            list.addEventListener('dragover',function(e){
                e.preventDefault();
                var target = e.target.closest('.tmpmp-faq-row');
                if(target && target!==dragging){
                    var rect = target.getBoundingClientRect();
                    if(e.clientY < rect.top + rect.height/2) list.insertBefore(dragging,target);
                    else list.insertBefore(dragging,target.nextSibling);
                }
            });
            list.querySelectorAll('.tmpmp-faq-row').forEach(function(r){ r.draggable=true; });
            // Make newly added rows draggable
            var ob = new MutationObserver(function(muts){
                muts.forEach(function(m){
                    m.addedNodes.forEach(function(n){
                        if(n.classList && n.classList.contains('tmpmp-faq-row')) n.draggable=true;
                    });
                });
            });
            ob.observe(list,{childList:true});
        })();
        </script>

    </div>
</div>

<!-- ── Reset to Defaults ────────────────────────────────────────────────── -->
<div class="tmpmp-settings-card">
    <div class="tmpmp-card-header"><span class="tmpmp-card-icon">🔄</span><div><h3><?php esc_html_e('Reset FAQ Items','tempmail-pro'); ?></h3><p><?php esc_html_e('Replace all FAQ items with the built-in default questions.','tempmail-pro'); ?></p></div></div>
    <div class="tmpmp-card-body">
        <button type="button" id="tmpmp-faq-reset" class="tmpmp-test-btn" style="background:#f1f5f9;color:#374151;border:1.5px solid #e2e8f0;">
            🔄 <?php esc_html_e('Restore Default FAQ Items','tempmail-pro'); ?>
        </button>
        <p class="tmpmp-field-desc" style="margin-top:8px;"><?php esc_html_e('This replaces all current items — you can edit them again afterwards.','tempmail-pro'); ?></p>
        <script>
        document.getElementById('tmpmp-faq-reset').addEventListener('click',function(){
            if(!confirm('<?php echo esc_js(__('Replace all FAQ items with defaults?','tempmail-pro')); ?>')) return;
            var defaults = <?php echo wp_json_encode( TempMail_FAQ::default_items() ); ?>;
            var list = document.getElementById('tmpmp-faq-list');
            list.innerHTML = '';
            // Trigger re-render via add (reuse makeRow if in scope)
            document.getElementById('tmpmp-faq-items-json').value = JSON.stringify(defaults);
            location.reload(); // simple reload to re-render rows
        });
        </script>
    </div>
</div>
</div><!-- /#tab-faq -->

<!-- ══════════════════════════════════════════════════════════════════════
     Login Email Tab
     ══════════════════════════════════════════════════════════════════════ -->
<div class="tmpmp-tab-panel" id="tab-loginemail">
<?php
$le = $settings; // shorthand
$le_from_name    = $le['le_from_name']    ?? get_bloginfo('name');
$le_subject      = $le['le_subject']      ?? '';
$le_header_title = $le['le_header_title'] ?? '';
$le_hdr_color1   = $le['le_hdr_color1']  ?? '#6366f1';
$le_hdr_color2   = $le['le_hdr_color2']  ?? '#8b5cf6';
$le_btn_text     = $le['le_btn_text']     ?? '';
$le_btn_color    = $le['le_btn_color']    ?? '#6366f1';
$le_body_msg     = $le['le_body_msg']     ?? '';
$le_security_msg = $le['le_security_msg'] ?? '';
$le_footer_text  = $le['le_footer_text']  ?? '';
$le_logo_emoji   = $le['le_logo_emoji']   ?? '✉';
$site_name       = get_bloginfo('name');
?>

<!-- ── Sender ─────────────────────────────────────────────────────────── -->
<div class="tmpmp-settings-card">
    <div class="tmpmp-card-header">
        <span class="tmpmp-card-icon">📤</span>
        <div>
            <h3><?php esc_html_e('Sender Settings','tempmail-pro'); ?></h3>
            <p><?php esc_html_e('Controls the "From" name and email subject line.','tempmail-pro'); ?></p>
        </div>
    </div>
    <div class="tmpmp-card-body">

        <div class="tmpmp-field-row">
            <label class="tmpmp-field-label"><?php esc_html_e('From Name','tempmail-pro'); ?></label>
            <div class="tmpmp-field-control">
                <input type="text" name="le_from_name" class="tmpmp-input" id="le-from-name"
                    value="<?php echo esc_attr($le_from_name); ?>"
                    placeholder="<?php echo esc_attr($site_name); ?>">
                <p class="tmpmp-field-desc"><?php esc_html_e('Sender name shown in the recipient\'s inbox. Defaults to site name.','tempmail-pro'); ?></p>
            </div>
        </div>

        <div class="tmpmp-field-row">
            <label class="tmpmp-field-label"><?php esc_html_e('Email Subject','tempmail-pro'); ?></label>
            <div class="tmpmp-field-control">
                <input type="text" name="le_subject" class="tmpmp-input" id="le-subject"
                    value="<?php echo esc_attr($le_subject); ?>"
                    placeholder="<?php printf( esc_attr__('Your login link for %s','tempmail-pro'), esc_attr($site_name) ); ?>">
                <p class="tmpmp-field-desc"><?php esc_html_e('Leave blank to use default. You can use {site} as a placeholder.','tempmail-pro'); ?></p>
            </div>
        </div>

    </div>
</div>

<!-- ── Email Header ───────────────────────────────────────────────────── -->
<div class="tmpmp-settings-card">
    <div class="tmpmp-card-header">
        <span class="tmpmp-card-icon">🎨</span>
        <div>
            <h3><?php esc_html_e('Email Header','tempmail-pro'); ?></h3>
            <p><?php esc_html_e('Logo emoji, title text, and header gradient colours.','tempmail-pro'); ?></p>
        </div>
    </div>
    <div class="tmpmp-card-body">

        <div class="tmpmp-field-row">
            <label class="tmpmp-field-label"><?php esc_html_e('Logo Emoji','tempmail-pro'); ?></label>
            <div class="tmpmp-field-control">
                <input type="text" name="le_logo_emoji" class="tmpmp-faq-icon-input" id="le-logo-emoji"
                    value="<?php echo esc_attr($le_logo_emoji); ?>" maxlength="4">
                <p class="tmpmp-field-desc"><?php esc_html_e('Emoji shown at the top of the email header.','tempmail-pro'); ?></p>
            </div>
        </div>

        <div class="tmpmp-field-row">
            <label class="tmpmp-field-label"><?php esc_html_e('Header Title','tempmail-pro'); ?></label>
            <div class="tmpmp-field-control">
                <input type="text" name="le_header_title" class="tmpmp-input" id="le-header-title"
                    value="<?php echo esc_attr($le_header_title); ?>"
                    placeholder="<?php echo esc_attr($site_name); ?>">
                <p class="tmpmp-field-desc"><?php esc_html_e('Title shown in the coloured header. Defaults to site name.','tempmail-pro'); ?></p>
            </div>
        </div>

        <div class="tmpmp-field-row">
            <label class="tmpmp-field-label"><?php esc_html_e('Header Gradient','tempmail-pro'); ?></label>
            <div class="tmpmp-field-control">
                <div style="display:flex;gap:20px;align-items:center;flex-wrap:wrap;">
                    <label style="display:flex;align-items:center;gap:8px;font-size:12px;color:#64748b;font-weight:600;">
                        <?php esc_html_e('Start','tempmail-pro'); ?>
                        <input type="color" name="le_hdr_color1" id="le-hdr-color1"
                            value="<?php echo esc_attr($le_hdr_color1); ?>"
                            style="width:44px;height:34px;border:1.5px solid #e2e8f0;border-radius:6px;cursor:pointer;padding:2px;">
                    </label>
                    <label style="display:flex;align-items:center;gap:8px;font-size:12px;color:#64748b;font-weight:600;">
                        <?php esc_html_e('End','tempmail-pro'); ?>
                        <input type="color" name="le_hdr_color2" id="le-hdr-color2"
                            value="<?php echo esc_attr($le_hdr_color2); ?>"
                            style="width:44px;height:34px;border:1.5px solid #e2e8f0;border-radius:6px;cursor:pointer;padding:2px;">
                    </label>
                    <div id="le-gradient-preview" style="flex:1;min-width:120px;height:34px;border-radius:6px;background:linear-gradient(135deg,<?php echo esc_attr($le_hdr_color1); ?>,<?php echo esc_attr($le_hdr_color2); ?>);"></div>
                </div>
            </div>
        </div>

    </div>
</div>

<!-- ── Email Body ─────────────────────────────────────────────────────── -->
<div class="tmpmp-settings-card">
    <div class="tmpmp-card-header">
        <span class="tmpmp-card-icon">📝</span>
        <div>
            <h3><?php esc_html_e('Email Body','tempmail-pro'); ?></h3>
            <p><?php esc_html_e('Customise the button, body message, security notice, and footer.','tempmail-pro'); ?></p>
        </div>
    </div>
    <div class="tmpmp-card-body">

        <div class="tmpmp-field-row">
            <label class="tmpmp-field-label"><?php esc_html_e('Button Text','tempmail-pro'); ?></label>
            <div class="tmpmp-field-control" style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;">
                <input type="text" name="le_btn_text" class="tmpmp-input" id="le-btn-text"
                    value="<?php echo esc_attr($le_btn_text); ?>"
                    placeholder="Sign In to <?php echo esc_attr($site_name); ?>"
                    style="flex:1;min-width:200px;">
                <input type="color" name="le_btn_color" id="le-btn-color"
                    value="<?php echo esc_attr($le_btn_color); ?>"
                    style="width:44px;height:38px;border:1.5px solid #e2e8f0;border-radius:6px;cursor:pointer;padding:2px;flex-shrink:0;"
                    title="<?php esc_attr_e('Button colour','tempmail-pro'); ?>">
            </div>
        </div>

        <div class="tmpmp-field-row">
            <label class="tmpmp-field-label"><?php esc_html_e('Body Message','tempmail-pro'); ?></label>
            <div class="tmpmp-field-control">
                <textarea name="le_body_msg" rows="3" class="tmpmp-input" id="le-body-msg"
                    placeholder="<?php esc_attr_e('Click the button below to sign in instantly — no password needed.','tempmail-pro'); ?>"><?php echo esc_textarea($le_body_msg); ?></textarea>
                <p class="tmpmp-field-desc"><?php esc_html_e('Main paragraph shown above the login button.','tempmail-pro'); ?></p>
            </div>
        </div>

        <div class="tmpmp-field-row">
            <label class="tmpmp-field-label"><?php esc_html_e('Security Notice','tempmail-pro'); ?></label>
            <div class="tmpmp-field-control">
                <textarea name="le_security_msg" rows="2" class="tmpmp-input" id="le-security-msg"
                    placeholder="<?php esc_attr_e('If you did not request this link, you can safely ignore this email.','tempmail-pro'); ?>"><?php echo esc_textarea($le_security_msg); ?></textarea>
                <p class="tmpmp-field-desc"><?php esc_html_e('Shown in the grey security box below the button.','tempmail-pro'); ?></p>
            </div>
        </div>

        <div class="tmpmp-field-row">
            <label class="tmpmp-field-label"><?php esc_html_e('Footer Text','tempmail-pro'); ?></label>
            <div class="tmpmp-field-control">
                <input type="text" name="le_footer_text" class="tmpmp-input" id="le-footer-text"
                    value="<?php echo esc_attr($le_footer_text); ?>"
                    placeholder="<?php echo esc_attr( '© ' . date('Y') . ' ' . $site_name ); ?>">
                <p class="tmpmp-field-desc"><?php esc_html_e('Footer attribution line. Leave blank to use auto-generated.','tempmail-pro'); ?></p>
            </div>
        </div>

    </div>
</div>

<!-- ── Live Preview ───────────────────────────────────────────────────── -->
<div class="tmpmp-settings-card">
    <div class="tmpmp-card-header">
        <span class="tmpmp-card-icon">👁️</span>
        <div>
            <h3><?php esc_html_e('Live Preview','tempmail-pro'); ?></h3>
            <p><?php esc_html_e('Rendered preview updates as you type.','tempmail-pro'); ?></p>
        </div>
    </div>
    <div class="tmpmp-card-body" style="background:#f1f5f9;padding:24px;border-radius:0 0 12px 12px;">
        <div style="max-width:480px;margin:0 auto;" id="le-preview-wrap">
            <div style="background:#fff;border-radius:10px;overflow:hidden;box-shadow:0 4px 20px rgba(0,0,0,.1);">
                <!-- Header -->
                <div id="le-prev-header" style="padding:24px;text-align:center;background:linear-gradient(135deg,<?php echo esc_attr($le_hdr_color1); ?>,<?php echo esc_attr($le_hdr_color2); ?>);">
                    <div id="le-prev-emoji" style="font-size:28px;line-height:1;margin-bottom:6px;"><?php echo esc_html($le_logo_emoji ?: '✉'); ?></div>
                    <div id="le-prev-title" style="color:#fff;font-size:17px;font-weight:700;"><?php echo esc_html($le_header_title ?: $site_name); ?></div>
                </div>
                <!-- Body -->
                <div style="padding:24px;">
                    <p style="margin:0 0 6px;font-size:15px;font-weight:700;color:#0f172a;">🔗 Your Magic Login Link</p>
                    <p id="le-prev-body" style="margin:0 0 18px;font-size:13px;color:#475569;line-height:1.6;"><?php echo esc_html($le_body_msg ?: __('Click the button below to sign in instantly — no password needed.','tempmail-pro')); ?></p>
                    <!-- Button -->
                    <div style="text-align:center;margin-bottom:18px;">
                        <span id="le-prev-btn" style="display:inline-block;padding:12px 28px;border-radius:7px;font-size:14px;font-weight:700;color:#fff;background:<?php echo esc_attr($le_btn_color); ?>;">
                            <?php echo esc_html($le_btn_text ?: 'Sign In to ' . $site_name); ?>
                        </span>
                    </div>
                    <!-- Security -->
                    <div id="le-prev-security" style="background:#f8fafc;border-radius:7px;padding:10px 14px;font-size:12px;color:#64748b;">
                        🔒 <?php echo esc_html($le_security_msg ?: __('If you did not request this link, you can safely ignore this email.','tempmail-pro')); ?>
                    </div>
                </div>
                <!-- Footer -->
                <div style="padding:12px 24px;background:#f8fafc;border-top:1px solid #e2e8f0;text-align:center;">
                    <div id="le-prev-footer" style="font-size:11px;color:#94a3b8;">
                        <?php echo esc_html($le_footer_text ?: '© ' . date('Y') . ' ' . $site_name); ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
(function(){
    var sn = '<?php echo esc_js($site_name); ?>';
    function g(id){ return document.getElementById(id); }
    function val(id, fallback){ var el = g(id); return el ? el.value.trim() || fallback : fallback; }

    function updatePreview(){
        var c1  = val('le-hdr-color1','#6366f1');
        var c2  = val('le-hdr-color2','#8b5cf6');
        var btn = val('le-btn-color','#6366f1');
        var grad = 'linear-gradient(135deg,'+c1+','+c2+')';

        g('le-prev-header').style.background = grad;
        g('le-gradient-preview').style.background = grad;
        g('le-prev-emoji').textContent  = val('le-logo-emoji','✉');
        g('le-prev-title').textContent  = val('le-header-title', sn);
        g('le-prev-body').textContent   = val('le-body-msg','<?php echo esc_js(__('Click the button below to sign in instantly — no password needed.','tempmail-pro')); ?>');
        g('le-prev-btn').textContent    = val('le-btn-text','Sign In to '+sn);
        g('le-prev-btn').style.background = btn;
        g('le-prev-security').textContent = '🔒 ' + val('le-security-msg','<?php echo esc_js(__('If you did not request this link, you can safely ignore this email.','tempmail-pro')); ?>');
        g('le-prev-footer').textContent = val('le-footer-text','© <?php echo esc_js(date('Y').' '.$site_name); ?>');
    }

    ['le-from-name','le-subject','le-header-title','le-logo-emoji',
     'le-hdr-color1','le-hdr-color2','le-btn-text','le-btn-color',
     'le-body-msg','le-security-msg','le-footer-text'
    ].forEach(function(id){
        var el = document.getElementById(id);
        if (el) el.addEventListener('input', updatePreview);
    });

    updatePreview();
})();
</script>

</div><!-- /#tab-loginemail -->

<p class="submit" style="padding-top:4px;">
    <button type="button" class="tmpmp-test-btn" id="tmpmp-save-settings">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M19 21H5a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v11a2 2 0 01-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/>
        </svg>
        <?php esc_html_e('Save All Settings','tempmail-pro'); ?>
    </button>
</p>
</form>
</div>

<script>
jQuery(function($){
    // Protocol show/hide
    function tmpmpToggleProtocol() {
        const val = $('#tmpmp-protocol').val();
        const isImap = (val === 'imap' || val === 'pop3');
        $('#tmpmp-imap-section').toggle(isImap);
        $('#tmpmp-webhook-section').toggle(val === 'webhook');
    }
    $('#tmpmp-protocol').on('change', tmpmpToggleProtocol);
    tmpmpToggleProtocol();

    // Quick-fill hints (host examples, port badges)
    $(document).on('click', '[data-fill]', function(){
        const field = $(this).data('fill');
        const val   = $(this).data('val');
        $('#' + field).val(val).trigger('focus');
    });

    // Test IMAP connection
    $('#tmpmp-test-imap').on('click', function(){
        const btn  = $(this);
        const orig = btn.html();
        btn.prop('disabled', true).html(
            '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg> Testing…'
        );
        const $res = $('#tmpmp-imap-test-result').hide();
        const connBtn = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07A19.5 19.5 0 013.07 8.81 19.79 19.79 0 01.07 2.18 2 2 0 012.06 0h3a2 2 0 012 1.72c.127.96.361 1.903.7 2.81a2 2 0 01-.45 2.11L6.09 7.91a16 16 0 006 6l1.27-1.27a2 2 0 012.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0122 14.92z"/></svg> <?php echo esc_js(__('Test IMAP Connection','tempmail-pro')); ?>';
        $.ajax({
            url:     TempMailAdmin.ajax_url,
            type:    'POST',
            timeout: 35000,
            data: {
                action:    'tmpmp_test_imap_connection',
                nonce:     TempMailAdmin.nonce,
                imap_host: $('#imap_host').val(),
                imap_port: $('#imap_port').val(),
                imap_user: $('#imap_user').val(),
                imap_pass: $('#imap_pass').val(),
                protocol:  $('#tmpmp-protocol').val(),
            },
            success: function(r) {
                $res.show().css({
                    background: r.success ? '#f0fdf4' : '#fef2f2',
                    color:      r.success ? '#065f46' : '#991b1b',
                    border:     '1px solid ' + (r.success ? '#bbf7d0' : '#fecaca'),
                }).html((r.success ? '✅ ' : '❌ ') + (r.data?.message || (r.success ? '<?php echo esc_js(__('Connection successful!','tempmail-pro')); ?>' : '<?php echo esc_js(__('Connection failed.','tempmail-pro')); ?>')));
                btn.prop('disabled', false).html(connBtn);
            },
            error: function(xhr, status) {
                const msg = status === 'timeout'
                    ? '<?php echo esc_js(__('Connection timed out. Check host, port and firewall settings.','tempmail-pro')); ?>'
                    : '<?php echo esc_js(__('Request failed. Check your network connection.','tempmail-pro')); ?>';
                $res.show().css({
                    background: '#fef2f2', color: '#991b1b', border: '1px solid #fecaca',
                }).html('❌ ' + msg);
                btn.prop('disabled', false).html(connBtn);
            },
        });
    });

    // Tabs
    $('.tmpmp-tab-btn').on('click',function(){
        $('.tmpmp-tab-btn').removeClass('active');
        $('.tmpmp-tab-panel').removeClass('active');
        $(this).addClass('active');
        $('#tab-'+$(this).data('tab')).addClass('active');
    });

    // Save settings
    $('#tmpmp-save-settings').on('click', function(){
        const $btn = $(this);
        const data = { action: 'tmpmp_save_settings', nonce: TempMailAdmin.nonce };
        $('#tmpmp-settings-form').serializeArray().forEach(f => { data[f.name] = f.value; });
        // Send '0' for unchecked checkboxes (serializeArray skips them)
        $('input[type=checkbox]').each(function(){
            if ( ! $(this).is(':checked') ) data[$(this).attr('name')] = '0';
        });
        const saveSvg  = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v11a2 2 0 01-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>';
        const savingHtml = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12a9 9 0 11-6.219-8.56"/></svg> Saving\u2026';
        const saveHtml   = saveSvg + ' <?php esc_js( esc_html__('Save All Settings','tempmail-pro') ); ?>';
        $btn.prop('disabled', true).html(savingHtml);
        $.post(TempMailAdmin.ajax_url, data, function(r) {
            if ( r.success ) {
                $('#tmpmp-settings-saved').fadeIn().delay(3000).fadeOut();
                $btn.prop('disabled', false).html(saveHtml);
            } else {
                alert('❌ ' + (r.data?.message || 'Save failed. Please try again.'));
                $btn.prop('disabled', false).html(saveHtml);
            }
        }).fail(function() {
            alert('❌ Network error. Settings not saved.');
            $btn.prop('disabled', false).html(saveHtml);
        });
    });

    // Regen tokens — also refresh cron command code blocks live
    ['webhook','cron'].forEach(type=>{
        $(`#tmpmp-regen-${type}`).on('click',function(){
            const field = type==='webhook' ? 'webhook_secret' : 'server_cron_token';
            $.post(TempMailAdmin.ajax_url,{action:'tmpmp_regen_token',nonce:TempMailAdmin.nonce,field},r=>{
                if(r.success){
                    const inp = $(`#tmpmp-${type==='webhook'?'webhook-secret':'cron-token'}`);
                    inp.val(r.data.token);
                    if(type==='cron'){
                        const endpoint = $('#tmpmp-cron-endpoint-inp').val();
                        const tok      = r.data.token;
                        $('#tmpmp-curl-cmd').text(`*/1 * * * * curl -s -X POST "${endpoint}?token=${tok}" > /dev/null 2>&1`);
                        $('#tmpmp-wget-cmd').text(`*/1 * * * * wget -q -O /dev/null "${endpoint}?token=${tok}" 2>/dev/null`);
                    }
                }
            });
        });
    });

    // Show / Hide cron token
    $('#tmpmp-cron-show').on('click', function(){
        const inp  = $('#tmpmp-cron-token');
        const show = inp.attr('type') === 'password';
        inp.attr('type', show ? 'text' : 'password');
        $(this).html(show ? '&#128274; <?php esc_js( esc_html_e('Hide','tempmail-pro') ); ?>' : '&#128065; <?php esc_js( esc_html_e('Show','tempmail-pro') ); ?>');
    });

    // Robust copy helper — works on HTTP (local) and HTTPS
    function tmpmpDoCopy(text, $btn) {
        const orig = $btn.html();
        const feedback = function() {
            $btn.html('&#10003; <?php echo esc_js(__('Copied!','tempmail-pro')); ?>');
            setTimeout(function(){ $btn.html(orig); }, 1800);
        };
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(text).then(feedback).catch(function(){
                tmpmpExecCopy(text, feedback);
            });
        } else {
            tmpmpExecCopy(text, feedback);
        }
    }
    function tmpmpExecCopy(text, cb) {
        var ta = document.createElement('textarea');
        ta.value = text;
        ta.style.cssText = 'position:fixed;top:0;left:0;opacity:0;pointer-events:none;';
        document.body.appendChild(ta);
        ta.focus();
        ta.select();
        try { document.execCommand('copy'); if (cb) cb(); } catch(e) {}
        document.body.removeChild(ta);
    }

    // Copy buttons (endpoint, code blocks)
    $(document).on('click', '.cron-copy-btn, .cron-code-copy', function(){
        const targetId = $(this).data('copy');
        const el       = document.getElementById(targetId);
        const text     = el ? (el.value || el.textContent || '').trim() : '';
        if (!text) return;
        tmpmpDoCopy(text, $(this));
    });


    // Test Server Cron Now
    $('#tmpmp-cron-test-btn').on('click', function(){
        const btn = $(this);
        btn.prop('disabled', true).html('&#8987; Running&hellip;');
        $.post(TempMailAdmin.ajax_url, {
            action: 'tmpmp_test_cron',
            nonce:  TempMailAdmin.nonce
        }, function(r){
            btn.prop('disabled', false).html('&#9889; <?php esc_js( esc_html_e('Test Server Cron Now','tempmail-pro') ); ?>');
            const box = $('#tmpmp-cron-result');
            if(r.success){
                const d = r.data;
                box.html(
                    `<div class="cr-row">&#128336; <?php echo esc_js(__('Last Run:','tempmail-pro')); ?> <span>${d.time}</span></div>` +
                    `<div class="cr-row">&#128140; <?php echo esc_js(__('IMAP:','tempmail-pro')); ?> <span>${d.fetched} <?php echo esc_js(__('fetched,','tempmail-pro')); ?> ${d.stored} <?php echo esc_js(__('stored','tempmail-pro')); ?></span></div>` +
                    `<div class="cr-row">&#128465; <?php echo esc_js(__('Purged:','tempmail-pro')); ?> <span>${d.purged} <?php echo esc_js(__('expired','tempmail-pro')); ?></span></div>` +
                    `<div class="cr-row">&#9889; <?php echo esc_js(__('Duration:','tempmail-pro')); ?> <span>${d.duration_ms}ms</span></div>`
                ).show();
            } else {
                box.html(`<div style="color:#dc2626;">&#10060; ${r.data?.message || 'Cron test failed.'}</div>`).show();
            }
        }).fail(function(){
            btn.prop('disabled', false).html('&#9889; <?php esc_js( esc_html_e('Test Server Cron Now','tempmail-pro') ); ?>');
            $('#tmpmp-cron-result').html('<div style="color:#dc2626;">&#10060; Network error.</div>').show();
        });
    });

    // Auto-fill pricing URL button
    $('#tmpmp-auto-fill-url').on('click', function(){
        var url = $(this).data('url');
        $('#upgrade_url').val(url).trigger('change');
        $(this).text('✓ Applied').css({'background':'#d1fae5','color':'#059669'});
        setTimeout(function(){ $('#tmpmp-auto-fill-url').html('&#128279; <?php esc_js( esc_html_e('Use Pricing Page','tempmail-pro') ); ?>').css({'background':'#ede9fe','color':'#6366f1'}); }, 2000);
    });
});
</script>




