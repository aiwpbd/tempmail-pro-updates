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
        <?php foreach ( ['inboxes' => '&#9993; '.__('My Inboxes','tempmail-pro'), 'billing' => '&#128179; '.__('Billing','tempmail-pro'), 'api' => '&#128273; '.__('API Keys','tempmail-pro')] as $tab => $label ) : ?>
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
                    <td style="font-family:monospace;font-weight:600;color:#6366f1;"><?php echo esc_html($addr->address); ?></td>
                    <td><?php echo intval($addr->email_count); ?></td>
                    <td><span class="tmpmp-pub-badge tmpmp-pub-badge--indigo"><?php echo esc_html(ucfirst($addr->plan)); ?></span></td>
                    <td style="color:#64748b;"><?php echo esc_html(date_i18n(get_option('date_format'), strtotime($addr->created_at))); ?></td>
                    <td>
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
                    <td><code style="font-size:12px;color:#4338ca;"><?php echo esc_html($py->invoice_number); ?></code></td>
                    <td style="font-weight:700;">$<?php echo number_format($py->amount,2); ?></td>
                    <td><?php echo esc_html(ucfirst($py->gateway ?? '')); ?></td>
                    <td>
                        <span class="tmpmp-pub-badge <?php echo $py->status==='completed' ? 'tmpmp-pub-badge--green' : 'tmpmp-pub-badge--red'; ?>">
                            <?php echo esc_html(ucfirst($py->status)); ?>
                        </span>
                    </td>
                    <td style="color:#64748b;"><?php echo esc_html(date_i18n(get_option('date_format'), strtotime($py->created_at))); ?></td>
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
                    <td><?php echo esc_html($k->label); ?></td>
                    <td><code style="font-size:12px;color:#4338ca;"><?php echo esc_html(substr($k->api_key,0,16)); ?>…</code></td>
                    <td style="text-align:center;"><?php echo intval($k->calls_count); ?></td>
                    <td>
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
});
</script>
