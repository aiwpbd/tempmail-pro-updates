<?php if ( ! defined('ABSPATH') ) exit; ?>
<?php
// ── Live status checks ────────────────────────────────────────────────────────
$s = $settings;

$checks = [
    'mail'     => ! empty($s['mail_protocol']),
    'domains'  => ! empty($domains),
    'plans'    => ! empty($plans) && count($plans) > 1,
    'stripe'   => ! empty($s['stripe_enabled']) && ! empty($s['stripe_pk']),
    'paypal'   => ! empty($s['paypal_enabled']) && ! empty($s['paypal_client_id']),
    'wc'       => ! empty($s['wc_enabled']),
    'custom'   => ! empty($s['custom_api_enabled']) && ! empty($s['custom_api_endpoint']),
    'google'   => ! empty($s['google_login']) && ! empty($s['google_client_id']),
    'pages'    => (bool) get_page_by_path('tempmail-dashboard'),
    'pricing'  => ! empty($s['pricing_heading']),
    'cron'     => defined('DISABLE_WP_CRON') && DISABLE_WP_CRON,
];

$payment_ok = $checks['stripe'] || $checks['paypal'] || $checks['wc'] || $checks['custom'];
$total      = 8; // counted milestones
$done       = (int)$checks['mail'] + (int)$checks['domains'] + (int)$checks['plans']
            + (int)$payment_ok + (int)$checks['google'] + (int)$checks['pages'] + (int)$checks['pricing']
            + (int)$checks['cron'];
$pct        = round($done / $total * 100);

$url_settings = admin_url('admin.php?page=tmpmp-settings');
$url_domains  = admin_url('admin.php?page=tmpmp-domains');
$url_plans    = admin_url('admin.php?page=tmpmp-plans');
$url_pages    = admin_url('admin.php?page=tmpmp-pages');
?>
<style>
.tmpmp-guide-wrap { max-width:900px; margin:30px auto; font-family:'Inter',system-ui,sans-serif; }
.tmpmp-guide-hero { background:linear-gradient(135deg,#6366f1 0%,#8b5cf6 100%);
    border-radius:18px; padding:40px 44px; color:#fff; margin-bottom:32px;
    display:flex; align-items:center; gap:28px; flex-wrap:wrap; }
.tmpmp-guide-hero-icon { font-size:52px; line-height:1; }
.tmpmp-guide-hero h1 { margin:0 0 6px; font-size:28px; font-weight:800; }
.tmpmp-guide-hero p  { margin:0; opacity:.88; font-size:15px; }

/* Progress bar */
.tmpmp-progress-wrap { margin-bottom:32px; background:#fff; border:1.5px solid #e2e8f0;
    border-radius:14px; padding:22px 28px; }
.tmpmp-progress-label { display:flex; justify-content:space-between; align-items:center;
    font-size:14px; font-weight:700; color:#0f172a; margin-bottom:10px; }
.tmpmp-progress-bar { height:10px; background:#f1f5f9; border-radius:999px; overflow:hidden; }
.tmpmp-progress-fill { height:100%; border-radius:999px;
    background:linear-gradient(90deg,#6366f1,#8b5cf6); transition:width .5s ease; }
.tmpmp-progress-steps { display:flex; gap:6px; margin-top:12px; flex-wrap:wrap; }
.tmpmp-step-dot { width:8px; height:8px; border-radius:50%;
    background:#e2e8f0; transition:background .3s; }
.tmpmp-step-dot.done { background:#6366f1; }

/* Step cards */
.tmpmp-guide-steps { display:flex; flex-direction:column; gap:16px; }
.tmpmp-guide-step { background:#fff; border:1.5px solid #e2e8f0; border-radius:14px;
    overflow:hidden; transition:box-shadow .2s; }
.tmpmp-guide-step:hover { box-shadow:0 4px 20px rgba(99,102,241,.12); }
.tmpmp-guide-step.is-done { border-color:#d1fae5; }
.tmpmp-guide-step.is-warn { border-color:#fde68a; }

.tmpmp-step-head { display:flex; align-items:center; gap:16px; padding:18px 24px;
    cursor:pointer; user-select:none; }
.tmpmp-step-num { width:36px; height:36px; border-radius:50%; display:flex;
    align-items:center; justify-content:center; font-size:15px; font-weight:800;
    flex-shrink:0; background:#f1f5f9; color:#6366f1; }
.tmpmp-guide-step.is-done .tmpmp-step-num { background:#d1fae5; color:#059669; }
.tmpmp-step-title { flex:1; }
.tmpmp-step-title strong { display:block; font-size:15px; font-weight:700; color:#0f172a; }
.tmpmp-step-title span { font-size:12.5px; color:#64748b; }
.tmpmp-step-status { font-size:12px; font-weight:700; padding:4px 12px; border-radius:999px; }
.status-done { background:#d1fae5; color:#059669; }
.status-todo { background:#ede9fe; color:#6366f1; }
.status-warn { background:#fef3c7; color:#d97706; }
.tmpmp-step-arrow { font-size:16px; color:#94a3b8; transition:transform .25s; margin-left:6px; }
.tmpmp-step-head[aria-expanded="true"] .tmpmp-step-arrow { transform:rotate(180deg); }

.tmpmp-step-body { display:none; padding:0 24px 24px; border-top:1px solid #f1f5f9; }
.tmpmp-step-body.is-open { display:block; }
.tmpmp-step-body p { margin:14px 0 10px; color:#374151; font-size:13.5px; line-height:1.65; }
.tmpmp-step-body ul { padding-left:20px; margin:8px 0 14px; }
.tmpmp-step-body ul li { font-size:13.5px; color:#374151; margin-bottom:6px; line-height:1.6; }
.tmpmp-step-body code { background:#f1f5f9; padding:2px 7px; border-radius:5px;
    font-size:12px; color:#4338ca; font-family:monospace; }
.tmpmp-step-actions { display:flex; gap:10px; flex-wrap:wrap; margin-top:16px; }
.tmpmp-guide-btn { display:inline-flex; align-items:center; gap:6px; padding:9px 18px;
    border-radius:9px; font-size:13px; font-weight:700; text-decoration:none;
    border:none; cursor:pointer; transition:opacity .2s; }
.tmpmp-guide-btn:hover { opacity:.85; }
.tmpmp-guide-btn--primary { background:linear-gradient(135deg,#6366f1,#8b5cf6); color:#fff; }
.tmpmp-guide-btn--outline { background:#fff; color:#6366f1; border:1.5px solid #c7d2fe; }

/* Sub-checklist */
.tmpmp-sub-check { display:flex; align-items:center; gap:8px; font-size:13px;
    color:#374151; margin-bottom:6px; }
.tmpmp-sub-check .dot { width:16px; height:16px; border-radius:50%; flex-shrink:0;
    display:flex; align-items:center; justify-content:center; font-size:10px; }
.dot-ok  { background:#d1fae5; color:#059669; }
.dot-no  { background:#fee2e2; color:#dc2626; }

@media(max-width:600px){
    .tmpmp-guide-hero { padding:24px 20px; }
    .tmpmp-guide-hero h1 { font-size:20px; }
    .tmpmp-step-head { padding:14px 16px; }
    .tmpmp-step-body { padding:0 16px 18px; }
}
</style>

<div class="tmpmp-guide-wrap">

    <!-- Hero -->
    <div class="tmpmp-guide-hero">
        <div class="tmpmp-guide-hero-icon">&#128640;</div>
        <div>
            <h1><?php esc_html_e('TempMail Pro — Setup Guide','tempmail-pro'); ?></h1>
            <p><?php esc_html_e('Follow these steps to go from a fresh install to a fully working SaaS. Each step shows your live configuration status.','tempmail-pro'); ?></p>
        </div>
    </div>

    <!-- Progress -->
    <div class="tmpmp-progress-wrap">
        <div class="tmpmp-progress-label">
            <span><?php printf( esc_html__('%d of %d steps completed','tempmail-pro'), $done, $total ); ?></span>
            <span style="color:#6366f1;font-size:20px;"><?php echo $pct; ?>%</span>
        </div>
        <div class="tmpmp-progress-bar">
            <div class="tmpmp-progress-fill" style="width:<?php echo $pct; ?>%"></div>
        </div>
        <div class="tmpmp-progress-steps">
            <?php for($i=0;$i<$total;$i++): ?>
            <div class="tmpmp-step-dot <?php echo $i < $done ? 'done' : ''; ?>"></div>
            <?php endfor; ?>
        </div>
    </div>

    <!-- ── Mail Server Setup Guide ─────────────────────────────────────────── -->
    <div style="background:#fff;border:1.5px solid #e2e8f0;border-radius:14px;margin-bottom:28px;overflow:hidden;">

        <div style="padding:20px 28px 0;border-bottom:1px solid #f1f5f9;">
            <p style="margin:0 0 4px;font-size:17px;font-weight:800;color:#0f172a;">&#128233; <?php esc_html_e('Mail Server Setup Guide','tempmail-pro'); ?></p>
            <p style="margin:0 0 16px;font-size:13px;color:#64748b;"><?php esc_html_e('Choose your hosting type below to see step-by-step instructions.','tempmail-pro'); ?></p>

            <!-- Tab nav -->
            <div style="display:flex;gap:0;flex-wrap:wrap;margin-bottom:-1px;">
                <?php
                $ms_tabs = [
                    'ms-shared'   => ['&#127760; Shared Hosting (cPanel / Plesk)', true],
                    'ms-vps'      => ['&#128187; VPS / Dedicated Server', false],
                    'ms-improvmx' => ['&#9889; ImprovMX (Free, Any Hosting)', false],
                    'ms-mailgun'  => ['&#9889; Mailgun Webhook', false],
                ];
                foreach ($ms_tabs as $id => [$label, $active]): ?>
                <button onclick="tmpmpMsTab(this,'<?php echo $id; ?>')"
                    style="padding:10px 18px;border:1.5px solid <?php echo $active ? '#6366f1' : '#e2e8f0'; ?>;
                        border-bottom:none;border-radius:10px 10px 0 0;
                        background:<?php echo $active ? '#6366f1' : '#f8fafc'; ?>;
                        color:<?php echo $active ? '#fff' : '#64748b'; ?>;
                        font-size:12.5px;font-weight:700;cursor:pointer;margin-right:4px;
                        transition:all .2s;"
                    class="ms-tab-btn<?php echo $active ? ' ms-active' : ''; ?>">
                    <?php echo $label; ?>
                </button>
                <?php endforeach; ?>
            </div>
        </div>

        <style>
        .ms-tab-panel { display:none; padding:24px 28px; }
        .ms-tab-panel.ms-active { display:block; }
        .ms-step { display:flex; gap:16px; margin-bottom:22px; align-items:flex-start; }
        .ms-step-num { width:32px;height:32px;border-radius:50%;background:#6366f1;color:#fff;
            display:flex;align-items:center;justify-content:center;font-size:14px;font-weight:800;flex-shrink:0;margin-top:2px; }
        .ms-step-content { flex:1; }
        .ms-step-content strong { display:block;font-size:14px;font-weight:700;color:#0f172a;margin-bottom:4px; }
        .ms-step-content p { margin:4px 0;font-size:13px;color:#374151;line-height:1.6; }
        .ms-step-content a { color:#6366f1;text-decoration:none; }
        .ms-code { background:#0f172a;color:#e2e8f0;font-family:monospace;font-size:12px;
            padding:12px 16px;border-radius:10px;overflow-x:auto;white-space:pre;margin:8px 0;line-height:1.8; }
        .ms-notice { padding:12px 16px;border-radius:8px;font-size:12.5px;margin:8px 0;line-height:1.6; }
        .ms-notice-green { background:#f0fdf4;border:1px solid #bbf7d0;color:#166534; }
        .ms-notice-yellow { background:#fefce8;border:1px solid #fde68a;color:#854d0e; }
        </style>

        <!-- Shared Hosting (cPanel / Plesk) -->
        <div id="ms-shared" class="ms-tab-panel ms-active">
            <div class="ms-notice ms-notice-green">&#9989; <?php esc_html_e('Works on any shared hosting. You just need a mailbox on your domain (e.g. via cPanel → Email Accounts).','tempmail-pro'); ?></div>

            <div class="ms-step">
                <div class="ms-step-num">1</div>
                <div class="ms-step-content">
                    <strong><?php esc_html_e('Create a catch-all mailbox','tempmail-pro'); ?></strong>
                    <p><?php esc_html_e('In cPanel → Email → Email Accounts → Create a new account e.g. catchall@yourdomain.com.','tempmail-pro'); ?></p>
                    <p><?php esc_html_e('Then go to Default Address → set it to forward to that mailbox (so ANY address receives mail).','tempmail-pro'); ?></p>
                </div>
            </div>

            <div class="ms-step">
                <div class="ms-step-num">2</div>
                <div class="ms-step-content">
                    <strong><?php esc_html_e('Note your IMAP credentials','tempmail-pro'); ?></strong>
                    <div class="ms-code">IMAP Host : mail.yourdomain.com  (or imap.yourdomain.com)
Port      : 993  (SSL)  or  143 (STARTTLS)
Username  : catchall@yourdomain.com
Password  : your email password</div>
                </div>
            </div>

            <div class="ms-step">
                <div class="ms-step-num">3</div>
                <div class="ms-step-content">
                    <strong><?php esc_html_e('Add MX record (if not already set)','tempmail-pro'); ?></strong>
                    <p><?php esc_html_e('In your domain registrar → DNS → add:','tempmail-pro'); ?></p>
                    <div class="ms-code">MX  @  mail.yourdomain.com  Priority 10</div>
                    <p><?php esc_html_e('(cPanel hosting usually has this already configured.)','tempmail-pro'); ?></p>
                </div>
            </div>

            <div class="ms-step">
                <div class="ms-step-num">4</div>
                <div class="ms-step-content">
                    <strong><?php esc_html_e('Configure plugin Settings','tempmail-pro'); ?></strong>
                    <div class="ms-code">Receiving Method : IMAP
IMAP Host        : mail.yourdomain.com
Port             : 993
Username         : catchall@yourdomain.com
Password         : your email password</div>
                    <p><?php esc_html_e('Click Test IMAP Connection → ✅ → Save Settings.','tempmail-pro'); ?></p>
                </div>
            </div>
            <div class="ms-step-actions" style="margin-top:4px;">
                <a href="<?php echo esc_url(admin_url('admin.php?page=tmpmp-settings#tab-mail')); ?>" class="tmpmp-guide-btn tmpmp-guide-btn--primary">&#9881; <?php esc_html_e('Open Mail Settings','tempmail-pro'); ?></a>
            </div>
        </div>

        <!-- VPS / Dedicated Server -->
        <div id="ms-vps" class="ms-tab-panel">
            <div class="ms-notice ms-notice-green">&#128640; <?php esc_html_e('Best performance. Install Postfix or Haraka on your VPS and point MX records to it.','tempmail-pro'); ?></div>

            <div class="ms-step">
                <div class="ms-step-num">1</div>
                <div class="ms-step-content">
                    <strong><?php esc_html_e('Install Postfix (Ubuntu/Debian)','tempmail-pro'); ?></strong>
                    <div class="ms-code">sudo apt update && sudo apt install postfix -y
# Choose: Internet Site → enter your domain</div>
                </div>
            </div>

            <div class="ms-step">
                <div class="ms-step-num">2</div>
                <div class="ms-step-content">
                    <strong><?php esc_html_e('Enable catch-all in /etc/postfix/main.cf','tempmail-pro'); ?></strong>
                    <div class="ms-code">virtual_alias_domains = yourdomain.com
virtual_alias_maps    = hash:/etc/postfix/virtual</div>
                    <p><?php esc_html_e('Then create /etc/postfix/virtual:','tempmail-pro'); ?></p>
                    <div class="ms-code">@yourdomain.com  catchall@yourdomain.com</div>
                    <div class="ms-code">sudo postmap /etc/postfix/virtual && sudo systemctl restart postfix</div>
                </div>
            </div>

            <div class="ms-step">
                <div class="ms-step-num">3</div>
                <div class="ms-step-content">
                    <strong><?php esc_html_e('Add MX + A records in your DNS','tempmail-pro'); ?></strong>
                    <div class="ms-code">A    mail  →  YOUR.VPS.IP.ADDRESS
MX   @    →  mail.yourdomain.com  Priority 10</div>
                </div>
            </div>

            <div class="ms-step">
                <div class="ms-step-num">4</div>
                <div class="ms-step-content">
                    <strong><?php esc_html_e('Use Webhook endpoint (fastest delivery)','tempmail-pro'); ?></strong>
                    <p><?php esc_html_e('Configure Postfix to pipe mail to the plugin webhook:','tempmail-pro'); ?></p>
                    <div class="ms-code"># /etc/postfix/master.cf — add:
tmpmp unix - n n - - pipe
  flags=Rq user=www-data argv=/usr/bin/curl -s -X POST \
  <?php echo esc_url(home_url('/wp-json/tempmail-pro/v1/receive')); ?> \
  -H "Content-Type: message/rfc822" --data-binary @-</div>
                </div>
            </div>
            <div style="margin-top:4px;">
                <a href="<?php echo esc_url(admin_url('admin.php?page=tmpmp-settings#tab-mail')); ?>" class="tmpmp-guide-btn tmpmp-guide-btn--primary">&#9881; <?php esc_html_e('Open Mail Settings','tempmail-pro'); ?></a>
            </div>
        </div>

        <!-- ImprovMX -->
        <div id="ms-improvmx" class="ms-tab-panel">
            <div class="ms-notice ms-notice-green">&#9989; <?php esc_html_e('Easiest setup — works on any hosting including local! ImprovMX is free and forwards all mail for your domain to a Gmail/any mailbox. Plugin then polls that mailbox via IMAP.','tempmail-pro'); ?></div>

            <div class="ms-step">
                <div class="ms-step-num">1</div>
                <div class="ms-step-content">
                    <strong><?php esc_html_e('Sign up at ImprovMX','tempmail-pro'); ?></strong>
                    <p><?php esc_html_e('Go to','tempmail-pro'); ?> <a href="https://improvmx.com" target="_blank" rel="noopener">improvmx.com</a> → <?php esc_html_e('click Add a domain → enter your domain (e.g.','tempmail-pro'); ?> <code>clickte.com</code> ).</p>
                </div>
            </div>

            <div class="ms-step">
                <div class="ms-step-num">2</div>
                <div class="ms-step-content">
                    <strong><?php esc_html_e('Set MX records at your domain registrar','tempmail-pro'); ?></strong>
                    <p><?php esc_html_e('ImprovMX will show you exactly what to add. Typically:','tempmail-pro'); ?></p>
                    <div class="ms-code">MX  @  mx1.improvmx.com  Priority 10
MX  @  mx2.improvmx.com  Priority 20</div>
                </div>
            </div>

            <div class="ms-step">
                <div class="ms-step-num">3</div>
                <div class="ms-step-content">
                    <strong><?php esc_html_e('Create a catch-all alias','tempmail-pro'); ?></strong>
                    <p><?php esc_html_e('In ImprovMX dashboard → Aliases → Add:','tempmail-pro'); ?></p>
                    <div class="ms-code">Alias     : * (asterisk = catch-all)
Forward to: yourcatchall@gmail.com</div>
                    <p><?php esc_html_e('Now ALL mail to','tempmail-pro'); ?> <code>anything@yourdomain.com</code> <?php esc_html_e('is forwarded to your Gmail.','tempmail-pro'); ?></p>
                </div>
            </div>

            <div class="ms-step">
                <div class="ms-step-num">4</div>
                <div class="ms-step-content">
                    <strong><?php esc_html_e('Enable IMAP in Gmail &amp; create App Password','tempmail-pro'); ?></strong>
                    <p>Gmail → Settings (&#9881;) → <?php esc_html_e('See all settings → Forwarding and POP/IMAP → Enable IMAP → Save','tempmail-pro'); ?></p>
                    <p><?php esc_html_e('Go to','tempmail-pro'); ?> <a href="https://myaccount.google.com/security" target="_blank" rel="noopener">myaccount.google.com/security</a> → <?php esc_html_e('enable 2-Step Verification','tempmail-pro'); ?></p>
                    <p><?php esc_html_e('Go to','tempmail-pro'); ?> <a href="https://myaccount.google.com/apppasswords" target="_blank" rel="noopener">App Passwords</a> → <?php esc_html_e('Create → Name: TempMail → copy the 16-character password','tempmail-pro'); ?></p>
                </div>
            </div>

            <div class="ms-step">
                <div class="ms-step-num">5</div>
                <div class="ms-step-content">
                    <strong><?php esc_html_e('Configure plugin Settings','tempmail-pro'); ?></strong>
                    <div class="ms-code">Receiving Method : IMAP
IMAP Host        : imap.gmail.com
Port             : 993
Username         : yourcatchall@gmail.com
Password         : xxxx xxxx xxxx xxxx  ← App Password (no spaces)</div>
                    <p><?php esc_html_e('Click Test IMAP Connection → ✅ → Save Settings.','tempmail-pro'); ?></p>
                </div>
            </div>
            <div class="ms-notice ms-notice-yellow">&#9888; <?php esc_html_e('ImprovMX free plan forwards up to 25 emails/day. Upgrade for more.','tempmail-pro'); ?></div>
            <div style="margin-top:12px;">
                <a href="<?php echo esc_url(admin_url('admin.php?page=tmpmp-settings#tab-mail')); ?>" class="tmpmp-guide-btn tmpmp-guide-btn--primary">&#9881; <?php esc_html_e('Open Mail Settings','tempmail-pro'); ?></a>
                <a href="https://improvmx.com" target="_blank" rel="noopener" class="tmpmp-guide-btn tmpmp-guide-btn--outline">&#8599; ImprovMX</a>
            </div>
        </div>

        <!-- Mailgun Webhook -->
        <div id="ms-mailgun" class="ms-tab-panel">
            <div class="ms-notice ms-notice-green">&#9889; <?php esc_html_e('Mailgun Routes forward inbound email to your webhook endpoint instantly — no polling delay.','tempmail-pro'); ?></div>

            <div class="ms-step">
                <div class="ms-step-num">1</div>
                <div class="ms-step-content">
                    <strong><?php esc_html_e('Add your domain to Mailgun','tempmail-pro'); ?></strong>
                    <p><?php esc_html_e('Go to','tempmail-pro'); ?> <a href="https://app.mailgun.com/mg/sending/domains" target="_blank" rel="noopener">app.mailgun.com</a> → <?php esc_html_e('Add Domain → enter your domain.','tempmail-pro'); ?></p>
                </div>
            </div>

            <div class="ms-step">
                <div class="ms-step-num">2</div>
                <div class="ms-step-content">
                    <strong><?php esc_html_e('Add MX records at your DNS registrar','tempmail-pro'); ?></strong>
                    <div class="ms-code">MX  @  mxa.mailgun.org  Priority 10
MX  @  mxb.mailgun.org  Priority 10</div>
                </div>
            </div>

            <div class="ms-step">
                <div class="ms-step-num">3</div>
                <div class="ms-step-content">
                    <strong><?php esc_html_e('Create an Inbound Route','tempmail-pro'); ?></strong>
                    <p><?php esc_html_e('Mailgun Dashboard → Receiving → Create Route:','tempmail-pro'); ?></p>
                    <div class="ms-code">Expression type : Match Recipient
Recipient       : .*@yourdomain\.com
Action          : Forward → <?php echo esc_url(home_url('/wp-json/tempmail-pro/v1/receive')); ?></div>
                </div>
            </div>

            <div class="ms-step">
                <div class="ms-step-num">4</div>
                <div class="ms-step-content">
                    <strong><?php esc_html_e('Configure plugin Settings','tempmail-pro'); ?></strong>
                    <div class="ms-code">Receiving Method : Webhook
Webhook Secret   : (leave blank or set in Mailgun → Webhooks → Signing key)</div>
                    <p><?php esc_html_e('Save Settings. Emails now arrive via webhook instantly — no cron required.','tempmail-pro'); ?></p>
                </div>
            </div>
            <div style="margin-top:4px;">
                <a href="<?php echo esc_url(admin_url('admin.php?page=tmpmp-settings#tab-mail')); ?>" class="tmpmp-guide-btn tmpmp-guide-btn--primary">&#9881; <?php esc_html_e('Open Mail Settings','tempmail-pro'); ?></a>
                <a href="https://app.mailgun.com" target="_blank" rel="noopener" class="tmpmp-guide-btn tmpmp-guide-btn--outline">&#8599; Mailgun</a>
            </div>
        </div>

    </div><!-- /.mail-setup-guide -->

    <script>
    function tmpmpMsTab(btn, panelId) {
        var wrap = btn.closest('div[style*="border:1.5px"]') || btn.parentNode.parentNode;
        // Reset all tab buttons
        btn.parentNode.querySelectorAll('.ms-tab-btn').forEach(function(b) {
            b.style.background = '#f8fafc';
            b.style.color      = '#64748b';
            b.style.borderColor= '#e2e8f0';
            b.classList.remove('ms-active');
        });
        // Reset all panels
        document.querySelectorAll('.ms-tab-panel').forEach(function(p) { p.classList.remove('ms-active'); });
        // Activate clicked
        btn.style.background  = '#6366f1';
        btn.style.color       = '#fff';
        btn.style.borderColor = '#6366f1';
        btn.classList.add('ms-active');
        document.getElementById(panelId).classList.add('ms-active');
    }
    </script>

    <!-- Steps -->
    <div class="tmpmp-guide-steps" id="tmpmp-guide-steps">

        <?php
        // Helper to render each step
        function tmpmp_guide_step( $num, $title, $desc, $status, $body_html ) {
            $cls  = $status === 'done' ? 'is-done' : ($status === 'warn' ? 'is-warn' : '');
            $slbl = $status === 'done' ? '&#10003; Done' : ($status === 'warn' ? '&#9888; Partial' : '&#9679; To Do');
            $scls = $status === 'done' ? 'status-done' : ($status === 'warn' ? 'status-warn' : 'status-todo');
            ?>
            <div class="tmpmp-guide-step <?php echo $cls; ?>">
                <div class="tmpmp-step-head" role="button" aria-expanded="false">
                    <div class="tmpmp-step-num"><?php echo $status === 'done' ? '&#10003;' : $num; ?></div>
                    <div class="tmpmp-step-title">
                        <strong><?php echo esc_html($title); ?></strong>
                        <span><?php echo esc_html($desc); ?></span>
                    </div>
                    <span class="tmpmp-step-status <?php echo $scls; ?>"><?php echo $slbl; ?></span>
                    <span class="tmpmp-step-arrow">&#8964;</span>
                </div>
                <div class="tmpmp-step-body"><?php echo $body_html; ?></div>
            </div>
            <?php
        }
        ?>

        <?php /* ── Step 1: Mail Server ──────────────────────────────────── */ ?>
        <?php ob_start(); ?>
        <p><?php esc_html_e('TempMail Pro needs a mail server to receive emails. Choose one of these two methods:','tempmail-pro'); ?></p>
        <ul>
            <li><strong><?php esc_html_e('IMAP/POP3 (recommended for self-hosted)','tempmail-pro'); ?></strong> — <?php esc_html_e('Enter your mail server credentials under Settings → Mail Server → IMAP Settings. The plugin polls the inbox on a schedule.','tempmail-pro'); ?></li>
            <li><strong><?php esc_html_e('Webhook (recommended for VPS/cloud)','tempmail-pro'); ?></strong> — <?php esc_html_e('Configure Postfix/Haraka/Mailgun to POST incoming mail JSON to:','tempmail-pro'); ?> <code><?php echo esc_html(home_url('/wp-json/tempmail-pro/v1/receive')); ?></code></li>
        </ul>
        <div class="tmpmp-sub-check">
            <div class="dot <?php echo !empty($settings['mail_protocol']) ? 'dot-ok' : 'dot-no'; ?>"><?php echo !empty($settings['mail_protocol']) ? '&#10003;' : '&#215;'; ?></div>
            <?php esc_html_e('Mail protocol selected','tempmail-pro'); ?>
        </div>
        <div class="tmpmp-sub-check">
            <div class="dot <?php echo !empty($settings['imap_host']) ? 'dot-ok' : 'dot-no'; ?>"><?php echo !empty($settings['imap_host']) ? '&#10003;' : '&#215;'; ?></div>
            <?php esc_html_e('IMAP host configured','tempmail-pro'); ?>
        </div>
        <div class="tmpmp-step-actions">
            <a href="<?php echo esc_url($url_settings . '#tab-mail'); ?>" class="tmpmp-guide-btn tmpmp-guide-btn--primary">&#9881; <?php esc_html_e('Open Mail Settings','tempmail-pro'); ?></a>
        </div>
        <?php $body = ob_get_clean();
        tmpmp_guide_step(1, __('Configure Mail Server','tempmail-pro'), __('Set up IMAP/POP3 or webhook to receive emails','tempmail-pro'), $checks['mail'] ? 'done' : 'todo', $body); ?>

        <?php /* ── Step 2: Add Domains ──────────────────────────────────── */ ?>
        <?php ob_start(); ?>
        <p><?php esc_html_e('Add the domains users can generate email addresses with. At least one domain is required before the inbox generator works.','tempmail-pro'); ?></p>
        <ul>
            <li><?php esc_html_e('Go to Domains → Add New Domain.','tempmail-pro'); ?></li>
            <li><?php esc_html_e('Set the domain category (free, basic, pro, business) to control which plan gets access.','tempmail-pro'); ?></li>
            <li><?php esc_html_e('Add a wildcard MX record pointing to your mail server: ','tempmail-pro'); ?><code>@ MX 10 mail.yourdomain.com</code></li>
        </ul>
        <div class="tmpmp-sub-check">
            <div class="dot <?php echo !empty($domains) ? 'dot-ok' : 'dot-no'; ?>"><?php echo !empty($domains) ? '&#10003;' : '&#215;'; ?></div>
            <?php printf( esc_html__('%d domain(s) added','tempmail-pro'), count($domains) ); ?>
        </div>
        <div class="tmpmp-step-actions">
            <a href="<?php echo esc_url($url_domains); ?>" class="tmpmp-guide-btn tmpmp-guide-btn--primary">&#127760; <?php esc_html_e('Manage Domains','tempmail-pro'); ?></a>
        </div>
        <?php $body = ob_get_clean();
        tmpmp_guide_step(2, __('Add Email Domains','tempmail-pro'), __('Register the domains for inbox generation','tempmail-pro'), !empty($domains) ? 'done' : 'todo', $body); ?>

        <?php /* ── Step 3: Plans ────────────────────────────────────────── */ ?>
        <?php ob_start(); ?>
        <p><?php esc_html_e('Plans define what each subscriber gets: inbox count, lifetime, storage, API access, and pricing. The plugin ships with default Free / Basic / Pro / Business plans.','tempmail-pro'); ?></p>
        <ul>
            <li><?php esc_html_e('Edit plan limits (max inboxes, lifetime, storage) to match your server capacity.','tempmail-pro'); ?></li>
            <li><?php esc_html_e('Set monthly and yearly prices. Set price to 0 for a free plan.','tempmail-pro'); ?></li>
            <li><?php esc_html_e('Use the Features text area (one item per line) to control what bullets appear on the pricing page.','tempmail-pro'); ?></li>
        </ul>
        <div class="tmpmp-sub-check">
            <div class="dot <?php echo count($plans) > 1 ? 'dot-ok' : 'dot-no'; ?>"><?php echo count($plans) > 1 ? '&#10003;' : '&#215;'; ?></div>
            <?php printf( esc_html__('%d plan(s) configured','tempmail-pro'), count($plans) ); ?>
        </div>
        <div class="tmpmp-step-actions">
            <a href="<?php echo esc_url($url_plans); ?>" class="tmpmp-guide-btn tmpmp-guide-btn--primary">&#128142; <?php esc_html_e('Manage Plans','tempmail-pro'); ?></a>
        </div>
        <?php $body = ob_get_clean();
        tmpmp_guide_step(3, __('Configure Plans','tempmail-pro'), __('Set pricing tiers and limits','tempmail-pro'), count($plans) > 1 ? 'done' : 'todo', $body); ?>

        <?php /* ── Step 4: Payment Gateways ─────────────────────────────── */ ?>
        <?php ob_start(); ?>
        <p><?php esc_html_e('Enable one or more payment gateways so users can upgrade their plans. All gateway settings are under Settings → Payments.','tempmail-pro'); ?></p>
        <table style="width:100%;border-collapse:collapse;font-size:13px;margin:10px 0;">
            <thead><tr style="background:#f8fafc;">
                <th style="padding:8px 12px;text-align:left;border-bottom:1px solid #e2e8f0;"><?php esc_html_e('Gateway','tempmail-pro'); ?></th>
                <th style="padding:8px 12px;text-align:left;border-bottom:1px solid #e2e8f0;"><?php esc_html_e('Requirement','tempmail-pro'); ?></th>
                <th style="padding:8px 12px;text-align:center;border-bottom:1px solid #e2e8f0;"><?php esc_html_e('Status','tempmail-pro'); ?></th>
            </tr></thead>
            <tbody>
            <?php foreach([
                ['Stripe',   $checks['stripe'], __('Publishable key + Secret key','tempmail-pro')],
                ['PayPal',   $checks['paypal'], __('Client ID + Secret','tempmail-pro')],
                ['WooCommerce', $checks['wc'], __('WooCommerce plugin installed','tempmail-pro')],
                ['Custom API',  $checks['custom'], __('Endpoint URL + Bearer key','tempmail-pro')],
            ] as [$gw,$ok,$req]): ?>
            <tr>
                <td style="padding:8px 12px;font-weight:700;color:#0f172a;"><?php echo esc_html($gw); ?></td>
                <td style="padding:8px 12px;color:#64748b;"><?php echo esc_html($req); ?></td>
                <td style="padding:8px 12px;text-align:center;">
                    <span style="font-size:11px;font-weight:700;padding:3px 10px;border-radius:999px;<?php echo $ok ? 'background:#d1fae5;color:#059669' : 'background:#f1f5f9;color:#94a3b8'; ?>">
                        <?php echo $ok ? esc_html__('Enabled','tempmail-pro') : esc_html__('Off','tempmail-pro'); ?>
                    </span>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <div class="tmpmp-step-actions">
            <a href="<?php echo esc_url($url_settings . '&tab=payments'); ?>" class="tmpmp-guide-btn tmpmp-guide-btn--primary">&#128179; <?php esc_html_e('Open Payment Settings','tempmail-pro'); ?></a>
        </div>
        <?php $body = ob_get_clean();
        tmpmp_guide_step(4, __('Payment Gateways','tempmail-pro'), __('Stripe, PayPal, WooCommerce, or Custom API','tempmail-pro'), $payment_ok ? 'done' : ($checks['stripe']||$checks['paypal'] ? 'warn' : 'todo'), $body); ?>

        <?php /* ── Step 5: Social Login ─────────────────────────────────── */ ?>
        <?php ob_start(); ?>
        <p><?php esc_html_e('Allow users to sign in with Google (OAuth 2.0) for a frictionless experience. The magic-link login works without any OAuth setup.','tempmail-pro'); ?></p>
        <ul>
            <li><?php esc_html_e('Go to Google Cloud Console → Create OAuth 2.0 credentials.','tempmail-pro'); ?></li>
            <li><?php esc_html_e('Add this URL as an Authorised Redirect URI:','tempmail-pro'); ?> <code><?php echo esc_html(home_url('/wp-json/tempmail-pro/v1/oauth/google')); ?></code></li>
            <li><?php esc_html_e('Paste the Client ID and Client Secret into Settings → Social Login.','tempmail-pro'); ?></li>
        </ul>
        <div class="tmpmp-sub-check">
            <div class="dot <?php echo $checks['google'] ? 'dot-ok' : 'dot-no'; ?>"><?php echo $checks['google'] ? '&#10003;' : '&#215;'; ?></div>
            <?php esc_html_e('Google OAuth configured','tempmail-pro'); ?>
        </div>
        <div class="tmpmp-step-actions">
            <a href="<?php echo esc_url($url_settings . '&tab=social'); ?>" class="tmpmp-guide-btn tmpmp-guide-btn--primary">&#128273; <?php esc_html_e('Open Social Login Settings','tempmail-pro'); ?></a>
            <a href="https://console.cloud.google.com/apis/credentials" target="_blank" rel="noopener" class="tmpmp-guide-btn tmpmp-guide-btn--outline">&#8599; <?php esc_html_e('Google Console','tempmail-pro'); ?></a>
        </div>
        <?php $body = ob_get_clean();
        tmpmp_guide_step(5, __('Social Login (Google OAuth)','tempmail-pro'), __('Optional — lets users sign in with Google','tempmail-pro'), $checks['google'] ? 'done' : 'todo', $body); ?>

        <?php /* ── Step 6: Pricing Page ─────────────────────────────────── */ ?>
        <?php ob_start(); ?>
        <p><?php esc_html_e('Customise the text that appears on your public pricing page — the eyebrow badge, heading, subtitle, and billing toggle labels.','tempmail-pro'); ?></p>
        <ul>
            <li><?php esc_html_e('Go to Settings → General → Pricing Page section.','tempmail-pro'); ?></li>
            <li><?php esc_html_e('Leave any field blank to use the built-in default.','tempmail-pro'); ?></li>
            <li><?php esc_html_e('The "Save" badge next to the Yearly toggle can be changed or hidden.','tempmail-pro'); ?></li>
        </ul>
        <div class="tmpmp-sub-check">
            <div class="dot <?php echo $checks['pricing'] ? 'dot-ok' : 'dot-no'; ?>"><?php echo $checks['pricing'] ? '&#10003;' : '&#215;'; ?></div>
            <?php esc_html_e('Pricing page text customised','tempmail-pro'); ?>
        </div>
        <div class="tmpmp-step-actions">
            <a href="<?php echo esc_url($url_settings . '#tab-general'); ?>" class="tmpmp-guide-btn tmpmp-guide-btn--primary">&#127991; <?php esc_html_e('Customise Pricing Page','tempmail-pro'); ?></a>
            <?php $pricing_page = get_page_by_path('tempmail-pricing'); ?>
            <?php if($pricing_page): ?>
            <a href="<?php echo esc_url(get_permalink($pricing_page->ID)); ?>" target="_blank" class="tmpmp-guide-btn tmpmp-guide-btn--outline">&#128065; <?php esc_html_e('Preview Pricing Page','tempmail-pro'); ?></a>
            <?php endif; ?>
        </div>
        <?php $body = ob_get_clean();
        tmpmp_guide_step(6, __('Pricing Page Text','tempmail-pro'), __('Customise hero, heading, subtitle and toggle labels','tempmail-pro'), $checks['pricing'] ? 'done' : 'todo', $body); ?>

        <?php /* ── Step 7: Publish Pages ────────────────────────────────── */ ?>
        <?php ob_start(); ?>
        <p><?php esc_html_e('The plugin auto-creates the three frontend pages. Verify they exist and are published.','tempmail-pro'); ?></p>
        <?php
        $page_slugs = [
            'tempmail-app'      => __('Inbox App','tempmail-pro'),
            'tempmail-pricing'  => __('Pricing','tempmail-pro'),
            'tempmail-dashboard'=> __('User Dashboard','tempmail-pro'),
            'tempmail-login'    => __('Login','tempmail-pro'),
        ];
        foreach($page_slugs as $slug => $label):
            $pg = get_page_by_path($slug);
        ?>
        <div class="tmpmp-sub-check">
            <div class="dot <?php echo $pg ? 'dot-ok' : 'dot-no'; ?>"><?php echo $pg ? '&#10003;' : '&#215;'; ?></div>
            <?php echo esc_html($label); ?>
            <?php if($pg): ?>
                — <a href="<?php echo esc_url(get_permalink($pg->ID)); ?>" target="_blank" style="font-size:12px;color:#6366f1;">
                    <?php esc_html_e('View','tempmail-pro'); ?> &#8599;
                </a>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
        <div class="tmpmp-step-actions">
            <a href="<?php echo esc_url($url_pages); ?>" class="tmpmp-guide-btn tmpmp-guide-btn--primary">&#128196; <?php esc_html_e('Manage Pages','tempmail-pro'); ?></a>
        </div>
        <?php $body = ob_get_clean();
        tmpmp_guide_step(7, __('Frontend Pages','tempmail-pro'), __('Verify the public pages are published','tempmail-pro'), $checks['pages'] ? 'done' : 'todo', $body); ?>

        <?php /* ── Step 8: WP-Cron Setup ───────────────────────────────── */ ?>
        <?php ob_start(); ?>
        <style>
        .cron-tabs { display:flex; gap:8px; flex-wrap:wrap; margin:14px 0 0; }
        .cron-tab-btn { padding:7px 16px; border-radius:8px; border:1.5px solid #c7d2fe;
            background:#fff; color:#6366f1; font-size:12.5px; font-weight:700;
            cursor:pointer; transition:all .2s; }
        .cron-tab-btn.active,.cron-tab-btn:hover { background:#6366f1; color:#fff; border-color:#6366f1; }
        .cron-tab-panel { display:none; margin-top:14px; }
        .cron-tab-panel.active { display:block; }
        .cron-code { background:#0f172a; color:#e2e8f0; font-family:monospace;
            font-size:12px; padding:14px 18px; border-radius:10px; overflow-x:auto;
            white-space:pre; margin:10px 0 6px; line-height:1.7; }
        .cron-note { font-size:12px; color:#64748b; margin:4px 0 12px; }
        </style>

        <p><?php esc_html_e('By default, WordPress only runs cron jobs when someone visits your site. For reliable email delivery, add a real server cron job that fires every minute.','tempmail-pro'); ?></p>

        <div class="tmpmp-sub-check">
            <div class="dot <?php echo $checks['cron'] ? 'dot-ok' : 'dot-no'; ?>"><?php echo $checks['cron'] ? '&#10003;' : '&#215;'; ?></div>
            <?php echo $checks['cron']
                ? esc_html__('DISABLE_WP_CRON is set — real cron is active','tempmail-pro')
                : esc_html__('DISABLE_WP_CRON not set — add to wp-config.php','tempmail-pro'); ?>
        </div>

        <p style="margin-top:12px;"><strong><?php esc_html_e('Step 1 — Add to wp-config.php (before the last line):', 'tempmail-pro'); ?></strong></p>
        <div class="cron-code">define('DISABLE_WP_CRON', true);</div>
        <p class="cron-note"><?php esc_html_e('This disables fake cron on every page load and lets your real cron handle it.','tempmail-pro'); ?></p>

        <p style="margin-top:6px;"><strong><?php esc_html_e('Step 2 — Add a real cron job on your server:', 'tempmail-pro'); ?></strong></p>

        <div class="cron-tabs">
            <button class="cron-tab-btn active" onclick="tmpmpSwitchCron(this,'cron-aapanel')">&#128640; aaPanel / VPS</button>
            <button class="cron-tab-btn" onclick="tmpmpSwitchCron(this,'cron-cpanel')">&#127760; cPanel</button>
            <button class="cron-tab-btn" onclick="tmpmpSwitchCron(this,'cron-ssh')">&#128187; SSH Terminal</button>
        </div>

        <div id="cron-aapanel" class="cron-tab-panel active">
            <ol style="font-size:13px;color:#374151;margin:10px 0 0 18px;line-height:2;">
                <li><?php esc_html_e('Log in to aaPanel → Cron (计划任务) → Add Task','tempmail-pro'); ?></li>
                <li><?php esc_html_e('Task type: Shell Script | Execution cycle: Every 1 minute','tempmail-pro'); ?></li>
                <li><?php esc_html_e('Script content:','tempmail-pro'); ?></li>
            </ol>
            <div class="cron-code">wget -q -O /dev/null "<?php echo esc_url(home_url('/wp-cron.php?doing_wp_cron')); ?>" 2>/dev/null</div>
            <p class="cron-note"><?php esc_html_e('Click Add Task → Done.','tempmail-pro'); ?></p>
        </div>

        <div id="cron-cpanel" class="cron-tab-panel">
            <ol style="font-size:13px;color:#374151;margin:10px 0 0 18px;line-height:2;">
                <li><?php esc_html_e('Log in to cPanel → Advanced → Cron Jobs → Add New Cron Job','tempmail-pro'); ?></li>
                <li><?php esc_html_e('Common Settings: Every Minute (*/1 * * * *)','tempmail-pro'); ?></li>
                <li><?php esc_html_e('Command:','tempmail-pro'); ?></li>
            </ol>
            <div class="cron-code">wget -q -O /dev/null "<?php echo esc_url(home_url('/wp-cron.php?doing_wp_cron')); ?>" >/dev/null 2>&amp;1</div>
            <p class="cron-note"><?php esc_html_e('Click Add New Cron Job → Done.','tempmail-pro'); ?></p>
        </div>

        <div id="cron-ssh" class="cron-tab-panel">
            <p style="font-size:13px;color:#374151;margin:8px 0 4px;"><?php esc_html_e('Connect via SSH and run:','tempmail-pro'); ?></p>
            <div class="cron-code">crontab -e</div>
            <p style="font-size:13px;color:#374151;margin:8px 0 4px;"><?php esc_html_e('Add this line at the bottom:','tempmail-pro'); ?></p>
            <div class="cron-code">* * * * * wget -q -O /dev/null "<?php echo esc_url(home_url('/wp-cron.php?doing_wp_cron')); ?>" >/dev/null 2>&amp;1</div>
            <p class="cron-note"><?php esc_html_e('Save and exit (Ctrl+X → Y → Enter). Verify: crontab -l','tempmail-pro'); ?></p>
            <p style="font-size:13px;color:#374151;margin:8px 0 4px;"><?php esc_html_e('Alternative using PHP-CLI (faster, no HTTP):','tempmail-pro'); ?></p>
            <div class="cron-code">* * * * * php <?php echo esc_html(ABSPATH); ?>wp-cron.php > /dev/null 2>&amp;1</div>
        </div>

        <script>
        function tmpmpSwitchCron(btn, panelId) {
            btn.closest('.tmpmp-step-body').querySelectorAll('.cron-tab-btn').forEach(function(b){ b.classList.remove('active'); });
            btn.closest('.tmpmp-step-body').querySelectorAll('.cron-tab-panel').forEach(function(p){ p.classList.remove('active'); });
            btn.classList.add('active');
            document.getElementById(panelId).classList.add('active');
        }
        </script>
        <?php $body = ob_get_clean();
        tmpmp_guide_step(8, __('Real Server Cron (WP-Cron)','tempmail-pro'), __('Required for fast email delivery — runs every minute','tempmail-pro'), $checks['cron'] ? 'done' : 'warn', $body); ?>

    </div><!-- /.tmpmp-guide-steps -->

    <!-- Quick links footer -->
    <div style="margin-top:32px;background:#f8fafc;border:1.5px solid #e2e8f0;border-radius:14px;padding:24px 28px;">
        <p style="font-size:13px;font-weight:800;color:#0f172a;margin:0 0 14px;text-transform:uppercase;letter-spacing:.6px;">&#128279; <?php esc_html_e('Quick Links','tempmail-pro'); ?></p>
        <div style="display:flex;flex-wrap:wrap;gap:10px;">
            <?php foreach([
                [$url_settings,         '&#9881;',  __('Settings','tempmail-pro')],
                [$url_domains,          '&#127760;',__('Domains','tempmail-pro')],
                [$url_plans,            '&#128142;',__('Plans','tempmail-pro')],
                [$url_pages,            '&#128196;',__('Pages','tempmail-pro')],
                [admin_url('admin.php?page=tmpmp-analytics'), '&#128200;', __('Analytics','tempmail-pro')],
                [admin_url('admin.php?page=tmpmp-users'),     '&#128101;', __('Users','tempmail-pro')],
            ] as [$href,$icon,$lbl]): ?>
            <a href="<?php echo esc_url($href); ?>" class="tmpmp-guide-btn tmpmp-guide-btn--outline">
                <?php echo $icon; ?> <?php echo esc_html($lbl); ?>
            </a>
            <?php endforeach; ?>
        </div>
    </div>

</div><!-- /.tmpmp-guide-wrap -->

<script>
document.querySelectorAll('.tmpmp-step-head').forEach(function(head){
    head.addEventListener('click', function(){
        var body    = this.closest('.tmpmp-guide-step').querySelector('.tmpmp-step-body');
        var isOpen  = body.classList.contains('is-open');
        // Close all
        document.querySelectorAll('.tmpmp-step-body').forEach(function(b){ b.classList.remove('is-open'); });
        document.querySelectorAll('.tmpmp-step-head').forEach(function(h){ h.setAttribute('aria-expanded','false'); });
        // Toggle clicked
        if(!isOpen){
            body.classList.add('is-open');
            this.setAttribute('aria-expanded','true');
        }
    });
});
// Auto-open first incomplete step
document.querySelectorAll('.tmpmp-guide-step').forEach(function(step){
    if(!step.classList.contains('is-done')){
        step.querySelector('.tmpmp-step-head').click();
        return false;
    }
});
</script>
