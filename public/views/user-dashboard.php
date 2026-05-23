<?php
if ( ! defined('ABSPATH') ) exit;
$user    = wp_get_current_user();
$sub     = TempMail_Database::get_user_subscription($user->ID);
$plan    = TempMail_Subscription::get_user_plan_data($user->ID);
global $wpdb;
$my_addresses = $wpdb->get_results($wpdb->prepare(
    "SELECT a.*, (SELECT COUNT(*) FROM {$wpdb->prefix}tmpmp_emails e WHERE e.address_id=a.id) as email_count
     FROM {$wpdb->prefix}tmpmp_addresses a WHERE a.user_id=%d ORDER BY a.created_at DESC LIMIT 50",
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
.tmpmp-dash-tabs {
    display: flex !important;
    flex-wrap: wrap !important;
    overflow: visible !important;
    overflow-x: unset !important;
    width: 100%;
    box-sizing: border-box;
    border-bottom: 2px solid #e2e8f0;
    margin-bottom: 24px;
}
.dash-tab-btn { flex-shrink: 0; white-space: nowrap; }

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

/* Mobile: stack header, full-width actions */
@media (max-width: 640px) {
    .tmpmp-dashboard-wrap { padding: 16px 12px !important; }
    .tmpmp-dash-header {
        flex-direction: column !important;
        align-items: flex-start !important;
        gap: 12px;
    }
    .tmpmp-dash-actions { width: 100%; display: flex; flex-wrap: wrap; gap: 8px; }
    .tmpmp-dash-header h1 { font-size: 18px; }
    .tmpmp-dash-tabs { gap: 4px; }
    .dash-tab-btn {
        flex: 1 1 auto;
        justify-content: center;
        padding: 9px 8px;
        font-size: 12px;
        border-radius: 8px 8px 0 0;
        background: #f8fafc;
        gap: 4px;
    }
    .dash-tab-btn.is-active { background: #ede9fe; }
    .tmpmp-billing-active { padding: 14px !important; }
}
@media (max-width: 380px) {
    .tmpmp-dash-actions { flex-direction: column; }
    .tmpmp-dash-actions .tmpmp-pub-btn,
    .tmpmp-dash-actions .tmpmp-pub-badge { text-align: center; width: 100%; }
    .dash-tab-btn { font-size: 11px; padding: 8px 5px; }
}
</style>
<div class="tmpmp-page-section tmpmp-dashboard-wrap">

    <!-- Header -->
    <div class="tmpmp-dash-header">
        <div>
            <h1>&#128075; <?php echo esc_html( sprintf( __('Hi, %s','tempmail-pro'), $user->display_name ) ); ?></h1>
            <p><?php echo esc_html( $user->user_email ); ?></p>
        </div>
        <div class="tmpmp-dash-actions">
            <span class="tmpmp-pub-badge <?php echo $sub ? 'tmpmp-pub-badge--green' : 'tmpmp-pub-badge--indigo'; ?>">
                <?php echo esc_html( ucfirst( $plan->slug ?? 'free' ) ); ?> Plan
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
    </div>

    <!-- ── Inboxes Tab ─────────────────────────────────────────────────── -->
    <div class="dash-tab-panel" id="dash-tab-inboxes">
        <?php if ( empty($my_addresses) ) : ?>
        <div class="tmpmp-empty-state">
            <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
            <p><?php esc_html_e('No inboxes yet. Use the TempMail app to generate your first inbox.','tempmail-pro'); ?></p>
        </div>
        <?php else : ?>
        <div class="tmpmp-pub-table-wrap">
            <table class="tmpmp-pub-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Address','tempmail-pro'); ?></th>
                        <th><?php esc_html_e('Emails','tempmail-pro'); ?></th>
                        <th><?php esc_html_e('Plan','tempmail-pro'); ?></th>
                        <th><?php esc_html_e('Created','tempmail-pro'); ?></th>
                        <th><?php esc_html_e('Expires','tempmail-pro'); ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ( $my_addresses as $addr ) :
                    $expired = strtotime( $addr->expires_at . ' UTC' ) < time();
                ?>
                <tr>
                    <td data-label="<?php esc_attr_e('Address','tempmail-pro'); ?>" style="font-family:monospace;font-weight:600;color:#6366f1;word-break:break-all;"><?php echo esc_html($addr->address); ?></td>
                    <td data-label="<?php esc_attr_e('Emails','tempmail-pro'); ?>"><?php echo intval($addr->email_count); ?></td>
                    <td data-label="<?php esc_attr_e('Plan','tempmail-pro'); ?>"><span class="tmpmp-pub-badge tmpmp-pub-badge--indigo"><?php echo esc_html(ucfirst($addr->plan)); ?></span></td>
                    <td data-label="<?php esc_attr_e('Created','tempmail-pro'); ?>" style="color:#64748b;"><?php echo esc_html(date_i18n(get_option('date_format'), strtotime($addr->created_at))); ?></td>
                    <td data-label="<?php esc_attr_e('Expires','tempmail-pro'); ?>">
                        <span class="tmpmp-pub-badge <?php echo $expired ? 'tmpmp-pub-badge--red' : 'tmpmp-pub-badge--green'; ?>">
                            <?php echo $expired ? esc_html__('Expired','tempmail-pro') : esc_html(date_i18n('d M H:i', strtotime($addr->expires_at.' UTC'))); ?>
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
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
                        <input type="password" id="acc-new-pass" autocomplete="new-password">
                    </div>
                    <div class="tmpmp-field">
                        <label for="acc-conf-pass"><?php esc_html_e('Confirm New Password','tempmail-pro'); ?></label>
                        <input type="password" id="acc-conf-pass" autocomplete="new-password">
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

            </div><!-- /security column -->
        </div><!-- /account-grid -->
    </div><!-- /#dash-tab-account -->

</div><!-- .tmpmp-dashboard-wrap -->

<script>
jQuery(function($){
    const nonce = TempMailPro.nonce, url = TempMailPro.ajax_url;

    // Tabs
    function activateTab(tab){
        $('.dash-tab-btn').removeClass('is-active');
        $('.dash-tab-panel').removeClass('is-active');
        $('.dash-tab-btn[data-tab="'+tab+'"]').addClass('is-active');
        $('#dash-tab-'+tab).addClass('is-active');
    }
    $('.dash-tab-btn').on('click', function(){ activateTab($(this).data('tab')); });
    activateTab('inboxes');

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
    function acctPost(action, data, btnId, msgId) {
        const btn = document.getElementById(btnId);
        const msg = document.getElementById(msgId);
        const orig = btn.textContent;
        btn.disabled = true;
        btn.textContent = '<?php esc_html_e('Saving…','tempmail-pro'); ?>';
        msg.className = 'tmpmp-account-msg';
        const body = new URLSearchParams({action, nonce, ...data});
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
            .finally(() => { btn.disabled = false; btn.textContent = orig; });
    }

    // Save Profile
    document.getElementById('tmpmp-save-profile')?.addEventListener('click', function() {
        acctPost('tmpmp_update_profile', {
            first_name:   document.getElementById('acc-first-name').value,
            last_name:    document.getElementById('acc-last-name').value,
            display_name: document.getElementById('acc-display-name').value,
        }, 'tmpmp-save-profile', 'tmpmp-profile-msg');
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
});
</script>
