<?php
/**
 * TempMail Pro — Temporary Diagnostic Tool
 * ACCESS: yoursite.com/wp-content/plugins/tempmail-pro/tmpmp-debug.php
 * DELETE this file after diagnosis is complete!
 */

// ── Bootstrap WordPress ───────────────────────────────────────────────────────
$wp_root = dirname( __DIR__, 3 ); // up from plugins/tempmail-pro/
if ( ! file_exists( $wp_root . '/wp-load.php' ) ) {
    // Try one more level up
    $wp_root = dirname( __DIR__, 4 );
}
require_once $wp_root . '/wp-load.php';

// ── Security: admin only ──────────────────────────────────────────────────────
if ( ! current_user_can('manage_options') ) {
    wp_die( 'Access denied. Please log in as admin first.' );
}

// ── Handle "Inject Test Email" POST ──────────────────────────────────────────
$inject_result = '';
if ( isset($_POST['inject_to']) && wp_verify_nonce($_POST['_nonce'] ?? '', 'tmpmp_debug') ) {
    $addr = sanitize_email(trim($_POST['inject_to']));
    if ( $addr ) {
        $ok = TempMail_Inbox::receive_email([
            'to'        => $addr,
            'from'      => 'debug@test.com',
            'from_name' => 'Debug Tool',
            'subject'   => '✅ Debug Test — ' . gmdate('H:i:s'),
            'body_text' => 'Direct DB injection test at ' . gmdate('Y-m-d H:i:s') . ' UTC.',
            'body_html' => '<h2>Debug Injection ✅</h2><p>Sent via debug tool at <b>' . gmdate('Y-m-d H:i:s') . ' UTC</b></p>',
        ]);
        $inject_result = is_wp_error($ok)
            ? '❌ ' . esc_html($ok->get_error_message())
            : '✅ Injected! Check the frontend inbox now.';
    }
}

// ── Handle "Force IMAP Poll" POST ─────────────────────────────────────────────
$poll_result = '';
if ( isset($_POST['force_poll']) && wp_verify_nonce($_POST['_nonce'] ?? '', 'tmpmp_debug') ) {
    $r = TempMail_IMAP::poll();
    $poll_result = json_encode($r, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}

// ── Gather data ───────────────────────────────────────────────────────────────
global $wpdb;
$settings   = get_option('tmpmp_settings', []);
$protocol   = $settings['mail_protocol']  ?? '(not set)';
$imap_host  = $settings['imap_host']      ?? '';
$imap_port  = $settings['imap_port']      ?? 993;
$imap_user  = $settings['imap_user']      ?? '';
$imap_pass  = $settings['imap_pass']      ?? '';
$imap_proto = $settings['imap_protocol']  ?? 'imap';

// Active addresses
$active = $wpdb->get_results(
    "SELECT id, address, expires_at, session_id
     FROM {$wpdb->prefix}tmpmp_addresses
     WHERE expires_at > UTC_TIMESTAMP()
     ORDER BY id DESC LIMIT 10"
);

// All addresses (incl expired)
$all_addrs_count = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}tmpmp_addresses" );
$all_emails_count = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}tmpmp_emails" );

// Recent emails (any)
$recent_emails = $wpdb->get_results(
    "SELECT e.id, a.address, e.sender, e.subject, e.received_at, e.is_spam
     FROM {$wpdb->prefix}tmpmp_emails e
     JOIN {$wpdb->prefix}tmpmp_addresses a ON a.id = e.address_id
     ORDER BY e.id DESC LIMIT 20"
);

// Last IMAP poll debug
$last_poll  = get_option('tmpmp_last_imap_poll', []);
$last_debug = get_option('tmpmp_last_imap_debug', []);

// PHP info
$php_imap_loaded  = extension_loaded('imap');
$imap_open_exists = function_exists('imap_open');
$ssl_exists       = extension_loaded('openssl');
$php_version      = PHP_VERSION;

$nonce_val = wp_create_nonce('tmpmp_debug');
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>TempMail Pro Diagnostic</title>
<style>
body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; max-width: 960px; margin: 30px auto; padding: 0 20px; background: #f1f5f9; color: #0f172a; }
h1 { color: #6366f1; margin-bottom: 4px; }
.warn { background: #fff7ed; border: 2px solid #fb923c; border-radius: 10px; padding: 14px 18px; margin: 16px 0; font-size: 13px; color: #92400e; }
.ok { background: #f0fdf4; border: 2px solid #4ade80; border-radius: 10px; padding: 14px 18px; margin: 16px 0; color: #166534; }
.err { background: #fef2f2; border: 2px solid #f87171; border-radius: 10px; padding: 14px 18px; margin: 16px 0; color: #991b1b; }
.card { background: #fff; border-radius: 12px; padding: 20px 24px; margin: 16px 0; box-shadow: 0 1px 4px rgba(0,0,0,.08); }
h2 { font-size: 16px; margin: 0 0 14px; color: #1e293b; border-bottom: 1px solid #e2e8f0; padding-bottom: 8px; }
table { width: 100%; border-collapse: collapse; font-size: 13px; }
th, td { text-align: left; padding: 8px 10px; border-bottom: 1px solid #f1f5f9; }
th { background: #f8fafc; font-weight: 700; }
tr:hover td { background: #f8fafc; }
pre { background: #0f172a; color: #e2e8f0; padding: 14px; border-radius: 8px; overflow-x: auto; font-size: 12px; line-height: 1.6; }
input[type=email], input[type=text] { padding: 8px 12px; border: 1.5px solid #e2e8f0; border-radius: 8px; font-size: 13px; width: 260px; }
button { padding: 9px 18px; background: #6366f1; color: #fff; border: none; border-radius: 8px; font-size: 13px; font-weight: 700; cursor: pointer; margin-left: 8px; }
button.danger { background: #ef4444; }
button.green  { background: #10b981; }
.badge { display: inline-block; padding: 2px 8px; border-radius: 99px; font-size: 11px; font-weight: 700; }
.badge.ok  { background: #dcfce7; color: #166534; }
.badge.bad { background: #fee2e2; color: #991b1b; }
.badge.warn { background: #fef9c3; color: #92400e; }
.inject-result { margin: 12px 0; font-weight: 700; font-size: 14px; }
</style>
</head>
<body>
<h1>🔍 TempMail Pro Diagnostic</h1>
<p style="color:#64748b;font-size:13px;">⚠️ <strong>Delete this file after use!</strong> — <code>wp-content/plugins/tempmail-pro/tmpmp-debug.php</code></p>

<!-- ── PHP Environment ── -->
<div class="card">
<h2>🖥 PHP Environment</h2>
<table>
<tr><th>PHP Version</th><td><?= esc_html($php_version) ?></td></tr>
<tr>
    <th>IMAP Extension Loaded</th>
    <td><?= $php_imap_loaded ? '<span class="badge ok">YES</span>' : '<span class="badge bad">NO</span>' ?></td>
</tr>
<tr>
    <th><code>imap_open()</code> function exists</th>
    <td>
        <?php if ($imap_open_exists): ?>
            <span class="badge ok">YES — native imap_open() will be used</span>
        <?php else: ?>
            <span class="badge warn">NO — socket fallback will be used</span>
        <?php endif; ?>
    </td>
</tr>
<tr>
    <th>OpenSSL Extension</th>
    <td><?= $ssl_exists ? '<span class="badge ok">YES</span>' : '<span class="badge bad">NO — SSL connections will fail!</span>' ?></td>
</tr>
</table>
</div>

<!-- ── Mail Protocol Settings ── -->
<div class="card">
<h2>📡 Mail Protocol Settings</h2>
<table>
<tr><th>Mail Protocol</th>
    <td>
        <strong><?= esc_html(strtoupper($protocol)) ?></strong>
        <?php if ($protocol === 'webhook'): ?>
            <span class="badge warn">WEBHOOK — emails come via POST to /wp-json/tempmail-pro/v1/receive</span>
        <?php elseif (in_array($protocol, ['imap','pop3'])): ?>
            <span class="badge ok">Will poll via IMAP/POP3</span>
        <?php else: ?>
            <span class="badge bad">NOT SET — go to Settings → Mail Server</span>
        <?php endif; ?>
    </td>
</tr>
<?php if (in_array($protocol, ['imap','pop3'])): ?>
<tr><th>IMAP Host</th>
    <td><?= $imap_host ? esc_html($imap_host) : '<span class="badge bad">EMPTY — not configured!</span>' ?></td>
</tr>
<tr><th>IMAP Port</th><td><?= esc_html($imap_port) ?></td></tr>
<tr><th>IMAP Username</th>
    <td><?= $imap_user ? esc_html($imap_user) : '<span class="badge bad">EMPTY!</span>' ?></td>
</tr>
<tr><th>IMAP Password</th>
    <td><?= $imap_pass ? '<span class="badge ok">SET (hidden)</span>' : '<span class="badge bad">EMPTY!</span>' ?></td>
</tr>
<tr><th>IMAP Sub-Protocol</th><td><?= esc_html($imap_proto) ?></td></tr>
<?php endif; ?>
</table>
</div>

<!-- ── Active Addresses ── -->
<div class="card">
<h2>📬 Active Inboxes (not expired)</h2>
<?php if ( $active ): ?>
<table>
<tr><th>ID</th><th>Address</th><th>Expires At (UTC)</th></tr>
<?php foreach ($active as $a): ?>
<tr>
    <td><?= (int)$a->id ?></td>
    <td><strong><?= esc_html($a->address) ?></strong></td>
    <td><?= esc_html($a->expires_at) ?></td>
</tr>
<?php endforeach; ?>
</table>
<?php else: ?>
<div class="err">❌ No active inboxes found! Generate an address on the frontend first.</div>
<?php endif; ?>
<p style="font-size:12px;color:#64748b;margin-top:10px;">
    Total addresses in DB: <strong><?= (int)$all_addrs_count ?></strong> &nbsp;|&nbsp;
    Total emails in DB: <strong><?= (int)$all_emails_count ?></strong>
</p>
</div>

<!-- ── Recent Emails in DB ── -->
<div class="card">
<h2>📨 Recent Emails in DB (last 20)</h2>
<?php if ($recent_emails): ?>
<table>
<tr><th>ID</th><th>To Address</th><th>From</th><th>Subject</th><th>Received</th><th>Spam</th></tr>
<?php foreach ($recent_emails as $e): ?>
<tr>
    <td><?= (int)$e->id ?></td>
    <td><?= esc_html($e->address) ?></td>
    <td><?= esc_html($e->sender) ?></td>
    <td><?= esc_html($e->subject) ?></td>
    <td><?= esc_html($e->received_at) ?></td>
    <td><?= $e->is_spam ? '🚫' : '✅' ?></td>
</tr>
<?php endforeach; ?>
</table>
<?php else: ?>
<div class="warn">⚠️ No emails in DB at all. IMAP or webhook has never stored an email successfully.</div>
<?php endif; ?>
</div>

<!-- ── Inject Test Email ── -->
<div class="card">
<h2>💉 Inject Test Email (bypasses IMAP/webhook)</h2>
<p style="font-size:13px;color:#475569;">Paste an <strong>active</strong> temp address from above. If this works but real emails don't appear, IMAP/webhook is the problem — not the frontend display.</p>
<form method="POST">
    <input type="hidden" name="_nonce" value="<?= esc_attr($nonce_val) ?>">
    <input type="email" name="inject_to"
           value="<?= esc_attr($active[0]->address ?? '') ?>"
           placeholder="user@domain.com" required>
    <button class="green" type="submit">📨 Inject Now</button>
</form>
<?php if ($inject_result): ?>
<p class="inject-result"><?= esc_html($inject_result) ?></p>
<?php endif; ?>
</div>

<!-- ── Force IMAP Poll ── -->
<?php if ( in_array($protocol, ['imap','pop3']) ): ?>
<div class="card">
<h2>⚡ Force IMAP Poll (runs poll() right now)</h2>
<p style="font-size:13px;color:#475569;">This calls <code>TempMail_IMAP::poll()</code> synchronously and shows the raw result including any error messages.</p>
<form method="POST">
    <input type="hidden" name="_nonce" value="<?= esc_attr($nonce_val) ?>">
    <button class="danger" type="submit" name="force_poll" value="1">🔄 Force Poll IMAP Now</button>
</form>
<?php if ($poll_result): ?>
<pre><?= esc_html($poll_result) ?></pre>
<?php endif; ?>
</div>

<!-- ── Last Poll Results ── -->
<div class="card">
<h2>🕐 Last IMAP Poll Results</h2>
<?php if ($last_poll): ?>
<pre><?= esc_html(json_encode($last_poll, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></pre>
<?php else: ?>
<div class="warn">No poll has run yet. WP-Cron may not have fired.</div>
<?php endif; ?>
</div>

<?php if ($last_debug): ?>
<div class="card">
<h2>🔎 Last IMAP Poll — Address Match Analysis</h2>

<?php
$d_active  = $last_debug['active_addresses'] ?? [];
$d_domains = $last_debug['active_domains'] ?? [];
$d_domain_found = $last_debug['domain_search_found'] ?? 'n/a (old log — re-poll to refresh)';
$d_msgs    = $last_debug['msgs'] ?? [];
$d_stored  = $last_debug['stored'] ?? 0;
$d_fetched = $last_debug['fetched'] ?? 0;
?>

<table>
<tr><th>Active temp addresses</th><td><?= esc_html(implode(', ', $d_active) ?: 'NONE — no active inboxes at poll time!') ?></td></tr>
<tr><th>Active domains</th><td><?= esc_html(implode(', ', $d_domains) ?: 'NONE') ?></td></tr>
<tr>
    <th>Domain-targeted search found</th>
    <td>
        <?php if ($d_domain_found === 0): ?>
            <strong style="color:#dc2626">0 — No emails TO @<?= esc_html($d_domains[0] ?? 'yourdomain') ?> in the IMAP inbox!<br>
            This means emails sent to temp addresses are NOT arriving in this IMAP mailbox.<br>
            Fix: Set up a catchall mailbox, or use ImprovMX/Cloudflare Email to forward all @domain.com mail here.</strong>
        <?php elseif (is_numeric($d_domain_found) && $d_domain_found > 0): ?>
            <span class="badge ok"><?= (int)$d_domain_found ?> emails found — domain routing OK ✅</span>
        <?php else: ?>
            <?= esc_html($d_domain_found) ?>
        <?php endif; ?>
    </td>
</tr>
<tr><th>Fetched / Stored</th><td><?= (int)$d_fetched ?> / <?= (int)$d_stored ?></td></tr>
</table>

<?php if ($d_msgs): ?>
<h3 style="margin-top:18px;font-size:14px;">Per-Email Analysis (first 20)</h3>
<table>
<tr><th>#</th><th>UID</th><th>Subject</th><th>To: Candidates Found</th><th>Result</th></tr>
<?php foreach (array_slice($d_msgs, 0, 20) as $i => $msg): ?>
<tr>
    <td><?= $i+1 ?></td>
    <td><?= esc_html($msg['uid'] ?? '-') ?></td>
    <td><?= esc_html($msg['subject'] ?? '') ?></td>
    <td><?= esc_html(implode(', ', $msg['candidates'] ?? [])) ?: '<em style="color:#94a3b8">none extracted</em>' ?></td>
    <td>
        <?php if (!empty($msg['matched'])): ?>
            <span class="badge ok">✅ matched → <?= esc_html($msg['matched']) ?></span>
        <?php elseif (($msg['skip_reason'] ?? '') === 'no_candidates_extracted'): ?>
            <span class="badge bad">❌ No To: address found in email headers</span>
        <?php elseif (($msg['skip_reason'] ?? '') === 'candidates_not_in_active_list'): ?>
            <span class="badge warn">⚠️ To: not a temp address</span>
        <?php else: ?>
            <span class="badge warn">skipped</span>
        <?php endif; ?>
    </td>
</tr>
<?php endforeach; ?>
</table>
<?php

// Show what was found vs what's active
$all_candidates = [];
foreach ($d_msgs as $msg) {
    foreach (($msg['candidates'] ?? []) as $c) {
        $all_candidates[$c] = ($all_candidates[$c] ?? 0) + 1;
    }
}
if ($all_candidates):
?>
<h3 style="margin-top:18px;font-size:14px;">All Unique To: Addresses Found in IMAP (with counts)</h3>
<table>
<tr><th>Address</th><th>Emails</th><th>Is Active Temp Address?</th></tr>
<?php foreach ($all_candidates as $addr => $cnt): ?>
<tr>
    <td><?= esc_html($addr) ?></td>
    <td><?= (int)$cnt ?></td>
    <td><?= in_array($addr, $d_active) ? '<span class="badge ok">YES ✅</span>' : '<span class="badge bad">NO</span>' ?></td>
</tr>
<?php endforeach; ?>
</table>
<?php endif; ?>
<?php endif; ?>

<details style="margin-top:16px;"><summary style="cursor:pointer;color:#64748b;font-size:12px;">Raw JSON (click to expand)</summary>
<pre><?= esc_html(json_encode($last_debug, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></pre>
</details>
</div>
<?php endif; ?>
<?php endif; ?>

<!-- ── Webhook Info ── -->
<?php if ($protocol === 'webhook'): ?>
<div class="card">
<h2>🌐 Webhook Setup</h2>
<div class="warn">
<strong>You are using Webhook mode.</strong> Emails only arrive when your mail relay POSTs to:<br>
<code style="display:block;margin:8px 0;padding:8px;background:#fff3cd;border-radius:6px;">
    POST <?= esc_html(rest_url('tempmail-pro/v1/receive')) ?>
</code>
You need to configure <strong>ImprovMX / Mailgun / Cloudflare Email / Postmark / SendGrid</strong> to forward incoming emails to this URL.<br><br>
Webhook Secret (set as header <code>X-TempMail-Secret</code>): <code><?= esc_html($settings['webhook_secret'] ?? '(none)') ?></code><br><br>
Last webhook hit: <strong><?= esc_html(get_option('tmpmp_last_webhook_hit', 'Never')) ?></strong><br>
Last webhook error: <?php $e = get_option('tmpmp_last_webhook_error'); echo $e ? '<pre>' . esc_html(json_encode($e, JSON_PRETTY_PRINT)) . '</pre>' : 'None'; ?>
</div>
</div>
<?php endif; ?>

<hr style="margin:30px 0;border:none;border-top:1px solid #e2e8f0;">
<p style="font-size:12px;color:#94a3b8;text-align:center;">
    ⚠️ Delete this file when done: <code>wp-content/plugins/tempmail-pro/tmpmp-debug.php</code>
</p>
</body>
</html>
