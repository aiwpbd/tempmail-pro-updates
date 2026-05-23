<?php if ( ! defined('ABSPATH') ) exit;
$_ps = get_option('tmpmp_settings', []);
$_eyebrow   = sanitize_text_field( $_ps['pricing_eyebrow']      ?? '⚡ Simple, Transparent Pricing' );
$_heading   = sanitize_text_field( $_ps['pricing_heading']       ?? __('Choose Your Plan','tempmail-pro') );
$_subtext   = sanitize_text_field( $_ps['pricing_subtext']       ?? __('Start free, upgrade anytime. Cancel anytime. No hidden fees.','tempmail-pro') );
$_save_txt  = sanitize_text_field( $_ps['pricing_yearly_save']   ?? __('Save 33%','tempmail-pro') );
$_lbl_mo    = sanitize_text_field( $_ps['pricing_label_monthly'] ?? __('Monthly','tempmail-pro') );
$_lbl_yr    = sanitize_text_field( $_ps['pricing_label_yearly']  ?? __('Yearly','tempmail-pro') );
?>
<div class="tmpmp-page-section tmpmp-pricing-wrap">

    <!-- Hero -->
    <div class="tmpmp-pricing-hero">
        <?php if ( $_eyebrow ) : ?>
            <div class="tmpmp-pricing-eyebrow"><?php echo esc_html( $_eyebrow ); ?></div>
        <?php endif; ?>
        <h2><?php echo esc_html( $_heading ); ?></h2>
        <?php if ( $_subtext ) : ?>
            <p class="tmpmp-pricing-sub"><?php echo esc_html( $_subtext ); ?></p>
        <?php endif; ?>
    </div>

    <!-- Billing toggle -->
    <div class="tmpmp-billing-toggle">
        <span id="tmpmp-cycle-monthly" class="active"><?php echo esc_html( $_lbl_mo ); ?></span>
        <label class="tmpmp-toggle">
            <input type="checkbox" id="tmpmp-cycle-toggle">
            <span class="tmpmp-toggle-slider"></span>
        </label>
        <span id="tmpmp-cycle-yearly">
            <?php echo esc_html( $_lbl_yr ); ?>
            <?php if ( $_save_txt ) : ?>
                <span class="tmpmp-billing-save"><?php echo esc_html( $_save_txt ); ?></span>
            <?php endif; ?>
        </span>
    </div>


    <!-- Plan cards -->
    <div class="tmpmp-plans-grid">
    <?php foreach( $plans as $p ) : ?>
    <?php
        /* ── Parse features ─────────────────────────────────────────────────
         * Supports: JSON array ["a","b"], one-per-line text, or empty (auto) */

        $raw_feats = trim( $p->features ?? '' );
        $feats     = json_decode( $raw_feats, true );
        if ( ! is_array( $feats ) ) {
            // plain one-per-line
            $feats = array_values( array_filter( array_map( 'strip_tags', preg_split( '/\r?\n/', $raw_feats ) ) ) );
        }

        // Auto-generate from plan limits when features list is empty
        if ( empty( $feats ) ) {
            $feats      = [];
            $max        = intval( $p->max_inboxes ?? 1 );
            $lifetime   = intval( $p->inbox_lifetime ?? 30 );
            $storage    = intval( $p->max_storage_mb ?? 5 );
            $refresh    = intval( $p->refresh_interval ?? 15 );

            // Inboxes
            $feats[] = $max < 0 ? '∞ Unlimited inboxes' : $max . ' inbox' . ( $max === 1 ? '' : 'es' );

            // Lifetime
            if ( $lifetime >= 1440 ) {
                $d = intdiv( $lifetime, 1440 );
                $feats[] = $d . ' day' . ( $d > 1 ? 's' : '' ) . ' inbox lifetime';
            } elseif ( $lifetime >= 60 ) {
                $h = intdiv( $lifetime, 60 );
                $feats[] = $h . 'hr inbox lifetime';
            } else {
                $feats[] = $lifetime . ' min inbox lifetime';
            }

            // Storage
            $feats[] = $storage < 0 ? 'Unlimited storage' : $storage . ' MB storage';

            // Refresh
            $feats[] = 'Auto-refresh every ' . $refresh . 's';

            // Capabilities
            if ( ! empty( $p->no_ads ) )           $feats[] = 'Ad-free experience';
            if ( ! empty( $p->has_custom_user ) )  $feats[] = 'Custom username';
            if ( ! empty( $p->has_api_access ) )   $feats[] = 'REST API access';
            if ( ! empty( $p->has_attachments ) )  $feats[] = 'Attachment support';
        }

        $is_featured = ( $p->slug === 'pro' );
        $is_free     = ( floatval( $p->price_monthly ) == 0 );
    ?>

    <div class="tmpmp-plan-card <?php echo $is_featured ? 'featured' : ''; ?>">
        <?php if ( $is_featured ) : ?>
            <div class="tmpmp-plan-badge">&#11088; <?php esc_html_e('Most Popular','tempmail-pro'); ?></div>
        <?php endif; ?>

        <div class="tmpmp-plan-name"><?php echo esc_html( $p->name ); ?></div>

        <div class="tmpmp-plan-price">
            <sup>$</sup><span class="price-monthly"><?php echo number_format( $p->price_monthly, 0 ); ?></span><span class="price-yearly" hidden><?php echo number_format( $p->price_yearly, 0 ); ?></span><span class="per">/<span class="cycle-label"><?php esc_html_e('mo','tempmail-pro'); ?></span></span>
        </div>
        <?php if ( $is_free ) : ?>
            <p class="tmpmp-plan-free-label"><?php esc_html_e('Free forever','tempmail-pro'); ?></p>
        <?php endif; ?>

        <hr class="tmpmp-plan-divider">

        <ul class="tmpmp-plan-features">
            <?php foreach ( $feats as $f ) : ?>
                <li><?php echo esc_html( $f ); ?></li>
            <?php endforeach; ?>
        </ul>

        <?php if ( ! $is_free ) : ?>
        <button type="button" class="tmpmp-plan-cta tmpmp-checkout-btn"
            data-plan-id="<?php echo intval( $p->id ); ?>"
            data-plan-name="<?php echo esc_attr( $p->name ); ?>">
            <?php esc_html_e('Get Started','tempmail-pro'); ?> &rarr;
        </button>
        <?php else : ?>
        <a href="<?php echo esc_url( home_url('/') ); ?>" class="tmpmp-plan-cta tmpmp-plan-cta--free">
            <?php esc_html_e('Start Free','tempmail-pro'); ?>
        </a>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
    </div>

    <!-- Payment Modal -->
    <div id="tmpmp-pay-modal" class="tmpmp-pay-modal" role="dialog" aria-modal="true">
        <div class="tmpmp-pay-modal-inner">
            <button id="tmpmp-pay-close" class="tmpmp-pay-close" aria-label="<?php esc_attr_e('Close','tempmail-pro'); ?>">&times;</button>
            <h3 class="tmpmp-pay-title" id="tmpmp-pay-title"></h3>
            <p class="tmpmp-pay-sub"><?php esc_html_e('Select a payment method to continue:','tempmail-pro'); ?></p>
            <div class="tmpmp-gw-btns" id="tmpmp-gateway-btns">
                <?php $s = get_option('tmpmp_settings',[]); ?>
                <?php if ( !empty($s['stripe_enabled']) ) : ?>
                <button class="tmpmp-gw-btn tmpmp-gw-btn--stripe" data-gw="stripe">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
                    <?php esc_html_e('Pay with Stripe','tempmail-pro'); ?>
                </button>
                <?php endif; ?>
                <?php if ( !empty($s['paypal_enabled']) ) : ?>
                <button class="tmpmp-gw-btn tmpmp-gw-btn--paypal" data-gw="paypal">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M7.076 21.337H2.47a.641.641 0 0 1-.633-.74L4.944.901C5.026.382 5.474 0 5.998 0h7.46c2.57 0 4.578.543 5.69 1.81 1.01 1.15 1.304 2.42 1.012 4.287-.023.143-.047.288-.077.437-.983 5.05-4.349 6.797-8.647 6.797h-2.19c-.524 0-.968.382-1.05.9l-1.12 7.106zm14.146-14.42a3.35 3.35 0 0 0-.607-.541c-.013.076-.026.175-.041.254-.59 3.025-2.566 6.643-8.558 6.643H9.82l-1.52 9.665h4.014a.641.641 0 0 0 .633-.543l.026-.13.502-3.178.032-.174a.641.641 0 0 1 .633-.544h.399c2.576 0 4.595-.543 5.186-2.114.246-.661.373-1.458.373-2.396 0-.757-.107-1.373-.476-1.942z"/></svg>
                    <?php esc_html_e('Pay with PayPal','tempmail-pro'); ?>
                </button>
                <?php endif; ?>
                <?php if ( !empty($s['wc_enabled']) && function_exists('wc_create_order') ) : ?>
                <button class="tmpmp-gw-btn tmpmp-gw-btn--woocommerce" data-gw="woocommerce">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
                    <?php esc_html_e('Pay with WooCommerce','tempmail-pro'); ?>
                </button>
                <?php endif; ?>
                <?php if ( !empty($s['custom_api_enabled']) && !empty($s['custom_api_endpoint']) ) : ?>
                <button class="tmpmp-gw-btn tmpmp-gw-btn--custom" data-gw="custom_api">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 13v6a2 2 0 01-2 2H5a2 2 0 01-2-2V8a2 2 0 012-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
                    <?php echo esc_html( $s['custom_api_label'] ?? __('Pay with Custom API','tempmail-pro') ); ?>
                </button>
                <?php endif; ?>
            </div>
            <div class="tmpmp-pay-loading" id="tmpmp-pay-loading">
                <?php esc_html_e('Redirecting to payment…','tempmail-pro'); ?>
            </div>
        </div>
    </div>
</div>

<script>
jQuery(function($){
    const nonce = typeof TempMailPro !== 'undefined' ? TempMailPro.nonce : '';
    const url   = typeof TempMailPro !== 'undefined' ? TempMailPro.ajax_url : '';
    let   cycle = 'monthly';
    let   selectedPlanId = 0;

    // Toggle billing cycle
    $('#tmpmp-cycle-toggle').on('change', function(){
        cycle = this.checked ? 'yearly' : 'monthly';
        $('.price-monthly').toggle(!this.checked);
        $('.price-yearly').toggle(this.checked);
        $('.cycle-label').text(this.checked ? '<?php esc_js( esc_html_e('yr','tempmail-pro') ); ?>' : '<?php esc_js( esc_html_e('mo','tempmail-pro') ); ?>');
        $('#tmpmp-cycle-monthly').toggleClass('active', !this.checked);
        $('#tmpmp-cycle-yearly').toggleClass('active', this.checked);
    });

    // Open modal
    $(document).on('click','.tmpmp-checkout-btn', function(e){
        e.preventDefault();
        selectedPlanId = $(this).data('plan-id');
        $('#tmpmp-pay-title').text('<?php esc_js( esc_html_e('Subscribe to','tempmail-pro') ); ?> ' + $(this).data('plan-name') + ' (' + cycle + ')');
        $('#tmpmp-pay-modal').addClass('is-open');
        $('body').css('overflow','hidden');
    });

    // Close modal
    $('#tmpmp-pay-close').on('click', closeModal);
    $('#tmpmp-pay-modal').on('click', function(e){ if(e.target===this) closeModal(); });
    $(document).on('keydown', function(e){ if(e.key==='Escape') closeModal(); });
    function closeModal(){
        $('#tmpmp-pay-modal').removeClass('is-open');
        $('body').css('overflow','');
        $('#tmpmp-gateway-btns').show();
        $('#tmpmp-pay-loading').hide();
    }

    // Gateway click
    $(document).on('click','.tmpmp-gw-btn', function(){
        const gateway = $(this).data('gw');
        $('#tmpmp-gateway-btns').hide();
        $('#tmpmp-pay-loading').show();
        $.post(url,{action:'tmpmp_create_checkout',nonce,plan_id:selectedPlanId,gateway,cycle},function(r){
            if(r.success && r.data.url){
                window.location.href = r.data.url;
            } else {
                alert(r.data?.message || '<?php esc_js( esc_html_e('Could not create checkout.','tempmail-pro') ); ?>');
                $('#tmpmp-gateway-btns').show();
                $('#tmpmp-pay-loading').hide();
            }
        });
    });
});
</script>
