<?php
/**
 * TempMail Pro — [tempmail_app] Shortcode
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class TempMail_Shortcode {

    private static ?self $instance = null;
    public static function instance() : self { return self::$instance ??= new self(); }

    private function __construct() {
        add_shortcode( 'tempmail_app', [ $this, 'render' ] );
    }

    public function render( array $atts ) : string {
        // Default theme comes from admin Design settings; shortcode attribute can override it.
        $saved_theme = TempMail_Design::get('design_theme') ?: 'dark';

        $a = shortcode_atts([
            'theme'  => $saved_theme,
            'height' => 'auto',
        ], $atts, 'tempmail_app' );

        // 'auto' = follow OS — set data-theme="auto" so CSS media query handles it
        // 'dark' / 'light' — set directly so CSS attribute selectors fire immediately
        $data_theme = in_array($a['theme'], ['dark','light','auto'], true) ? $a['theme'] : 'dark';

        $plan     = TempMail_Subscription::get_user_plan();
        $domains  = TempMail_Database::get_all_domains();
        $settings = get_option( 'tmpmp_settings', [] );
        $is_prem  = TempMail_Subscription::is_premium_user();

        ob_start();
        ?>
        <div class="tmpmp-wrap" data-theme="<?php echo esc_attr($data_theme); ?>" id="tmpmp-main">
            <?php echo TempMail_Ads::render('top_banner'); ?>

            <!-- ── Account Nav Bar ─────────────────────────────────────────── -->
            <?php
            $is_logged_in  = is_user_logged_in();
            $current_user  = $is_logged_in ? wp_get_current_user() : null;
            $avatar_url    = $is_logged_in ? get_user_meta( $current_user->ID, 'tmpmp_avatar_url', true ) : '';
            $display_name  = $is_logged_in ? ( $current_user->display_name ?: $current_user->user_email ) : '';
            $logout_url    = $is_logged_in ? wp_logout_url( get_permalink() ?: home_url('/') ) : '';
            $dash_url      = TempMail_Auth::dashboard_url();
            $login_url     = TempMail_Auth::login_url();
            ?>
            <div class="tmpmp-account-bar">
            <?php if ( $is_logged_in ) : ?>
                <div class="tmpmp-account-bar__user">
                    <div class="tmpmp-account-bar__avatar">
                        <?php if ( $avatar_url ) : ?>
                            <img src="<?php echo esc_url( $avatar_url ); ?>" alt="">
                        <?php else : ?>
                            <span><?php echo esc_html( strtoupper( substr( $display_name, 0, 1 ) ) ); ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="tmpmp-account-bar__info">
                        <span class="tmpmp-account-bar__greeting"><?php esc_html_e( 'Welcome back,', 'tempmail-pro' ); ?></span>
                        <span class="tmpmp-account-bar__name"><?php echo esc_html( $display_name ); ?></span>
                    </div>
                </div>
                <div class="tmpmp-account-bar__actions">
                    <a href="<?php echo esc_url( $dash_url ); ?>" class="tmpmp-acct-btn tmpmp-acct-btn--dash">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
                        <?php esc_html_e( 'My Dashboard', 'tempmail-pro' ); ?>
                    </a>
                    <a href="<?php echo esc_url( $logout_url ); ?>" class="tmpmp-acct-btn tmpmp-acct-btn--logout">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                        <?php esc_html_e( 'Logout', 'tempmail-pro' ); ?>
                    </a>
                </div>
            <?php else : ?>
                <?php if ( ! empty( $settings['show_acct_login_btn'] ) ) : ?>
                <div class="tmpmp-account-bar__guest">
                    <span class="tmpmp-account-bar__guest-text">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                        <?php esc_html_e( 'Get private inbox, history & more', 'tempmail-pro' ); ?>
                    </span>
                    <a href="<?php echo esc_url( $login_url ); ?>" class="tmpmp-acct-btn tmpmp-acct-btn--login">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 3h4a2 2 0 012 2v14a2 2 0 01-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>
                        <?php esc_html_e( 'Sign In / Register', 'tempmail-pro' ); ?>
                    </a>
                </div>
                <?php endif; ?>
            <?php endif; ?>
            </div>
            <!-- ── / Account Nav Bar ──────────────────────────────────────── -->

            <!-- Address Bar -->
            <div class="tmpmp-address-bar">
                <div class="tmpmp-addr-box">
                    <span class="tmpmp-addr-icon">✉️</span>
                    <span class="tmpmp-addr-text" id="tmpmp-address">
                        <?php esc_html_e('Generating…','tempmail-pro'); ?>
                    </span>
                    <button class="tmpmp-btn tmpmp-btn--icon" id="tmpmp-copy-btn" title="<?php esc_attr_e('Copy','tempmail-pro'); ?>">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 01-2-2V4a2 2 0 012-2h9a2 2 0 012 2v1"/></svg>
                    </button>
                    <button class="tmpmp-btn tmpmp-btn--icon" id="tmpmp-qr-btn" title="<?php esc_attr_e('QR Code','tempmail-pro'); ?>">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="3" height="3"/></svg>
                    </button>
                </div>

                <div class="tmpmp-addr-actions">
                    <!-- Domain selector -->
                    <div class="tmpmp-select-wrap">
                        <select class="tmpmp-select" id="tmpmp-domain-select">
                            <?php
                            // Group domains: free first, then premium, then vip
                            $free_domains    = array_filter($domains, fn($d) => $d->category === 'free');
                            $premium_domains = array_filter($domains, fn($d) => $d->category === 'premium');
                            $vip_domains     = array_filter($domains, fn($d) => $d->category === 'vip');

                            // Free domains — always accessible
                            if ($free_domains): ?>
                                <optgroup label="— Free Domains —">
                                <?php foreach ($free_domains as $d): ?>
                                    <option value="<?php echo esc_attr($d->domain); ?>"
                                            data-cat="free"
                                            data-locked="0">
                                        @<?php echo esc_html($d->domain); ?>
                                    </option>
                                <?php endforeach; ?>
                                </optgroup>
                            <?php endif;

                            // Premium domains — locked for non-premium users
                            if ($premium_domains): ?>
                                <optgroup label="⭐ Premium Domains">
                                <?php foreach ($premium_domains as $d): ?>
                                    <option value="<?php echo esc_attr($d->domain); ?>"
                                            data-cat="premium"
                                            data-locked="<?php echo $is_prem ? '0' : '1'; ?>"
                                            class="tmpmp-opt-premium">
                                        ⭐ @<?php echo esc_html($d->domain); ?><?php echo $is_prem ? '' : ' 🔒'; ?>
                                    </option>
                                <?php endforeach; ?>
                                </optgroup>
                            <?php endif;

                            // VIP domains — locked for non-premium users
                            if ($vip_domains): ?>
                                <optgroup label="💎 VIP Domains">
                                <?php foreach ($vip_domains as $d): ?>
                                    <option value="<?php echo esc_attr($d->domain); ?>"
                                            data-cat="vip"
                                            data-locked="<?php echo $is_prem ? '0' : '1'; ?>"
                                            class="tmpmp-opt-vip">
                                        💎 @<?php echo esc_html($d->domain); ?><?php echo $is_prem ? '' : ' 🔒'; ?>
                                    </option>
                                <?php endforeach; ?>
                                </optgroup>
                            <?php endif; ?>
                        </select>
                        <span class="tmpmp-select-chevron" aria-hidden="true">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
                        </span>
                    </div>

                    <?php if($is_prem): ?>
                    <input type="text" class="tmpmp-input" id="tmpmp-custom-username"
                        placeholder="<?php esc_attr_e('Custom username…','tempmail-pro'); ?>" maxlength="40">
                    <?php endif; ?>
                    <button class="tmpmp-btn tmpmp-btn--primary" id="tmpmp-generate-btn">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M23 4v6h-6"/><path d="M1 20v-6h6"/><path d="M3.51 9a9 9 0 0114.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0020.49 15"/></svg>
                        <?php esc_html_e('New Email','tempmail-pro'); ?>
                    </button>
                    <button class="tmpmp-btn tmpmp-btn--danger" id="tmpmp-delete-btn">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6M14 11v6"/><path d="M9 6V4h6v2"/></svg>
                        <?php esc_html_e('Delete','tempmail-pro'); ?>
                    </button>
                </div>

                <!-- Expiry timer -->
                <div class="tmpmp-expiry-bar">
                    <div class="tmpmp-expiry-label">
                        <span><?php esc_html_e('Expires in:','tempmail-pro'); ?></span>
                        <span id="tmpmp-expiry-countdown">--:--</span>
                    </div>
                    <div class="tmpmp-progress-track">
                        <div class="tmpmp-progress-fill" id="tmpmp-expiry-bar"></div>
                    </div>
                </div>

                <!-- Rate-limit warning banner -->
                <div class="tmpmp-rl-banner" id="tmpmp-rl-banner" style="display:none;" role="alert">
                    <span class="tmpmp-rl-banner-icon">🚫</span>
                    <div class="tmpmp-rl-banner-body">
                        <strong class="tmpmp-rl-banner-title"><?php esc_html_e('Rate Limit Reached','tempmail-pro'); ?></strong>
                        <p class="tmpmp-rl-banner-msg" id="tmpmp-rl-msg"></p>
                    </div>
                    <button class="tmpmp-rl-banner-close" id="tmpmp-rl-close" aria-label="<?php esc_attr_e('Dismiss','tempmail-pro'); ?>">✕</button>
                </div>
            </div>

            <!-- QR Modal -->
            <div class="tmpmp-modal" id="tmpmp-qr-modal" hidden>
                <div class="tmpmp-modal-inner">
                    <button class="tmpmp-modal-close" id="tmpmp-qr-close">&times;</button>
                    <h3><?php esc_html_e('Scan to open inbox','tempmail-pro'); ?></h3>
                    <div id="tmpmp-qr-container"></div>
                    <p id="tmpmp-qr-address" class="tmpmp-qr-url"></p>
                </div>
            </div>

            <!-- Main columns -->
            <div class="tmpmp-columns">

                <!-- Inbox list -->
                <div class="tmpmp-inbox-panel">
                    <div class="tmpmp-panel-header">
                        <h3><?php esc_html_e('Inbox','tempmail-pro'); ?></h3>
                        <div class="tmpmp-panel-header-right">
                            <button class="tmpmp-sound-toggle" id="tmpmp-sound-btn" title="Sound On"></button>
                            <button class="tmpmp-btn tmpmp-btn--sm" id="tmpmp-refresh-btn">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M23 4v6h-6"/><path d="M1 20v-6h6"/><path d="M3.51 9a9 9 0 0114.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0020.49 15"/></svg>
                                <?php esc_html_e('Refresh','tempmail-pro'); ?>
                            </button>
                        </div>
                    </div>
                    <div class="tmpmp-email-list" id="tmpmp-email-list">
                        <div class="tmpmp-empty-state" id="tmpmp-empty-state">
                            <div class="tmpmp-empty-icon">📭</div>
                            <p><?php esc_html_e('No emails yet — waiting…','tempmail-pro'); ?></p>
                        </div>
                    </div>
                    <?php echo TempMail_Ads::render('inbox_sidebar'); ?>
                </div>

                <!-- Email viewer -->
                <div class="tmpmp-viewer-panel" id="tmpmp-viewer-panel" hidden>
                    <div class="tmpmp-viewer-header">
                        <button class="tmpmp-btn tmpmp-btn--sm" id="tmpmp-viewer-back">
                            <?php esc_html_e('Back','tempmail-pro'); ?>
                        </button>
                        <div class="tmpmp-viewer-subject" id="tmpmp-viewer-subject"></div>
                        <button class="tmpmp-btn tmpmp-btn--danger tmpmp-btn--sm" id="tmpmp-viewer-delete">🗑</button>
                    </div>
                    <div class="tmpmp-viewer-meta" id="tmpmp-viewer-meta"></div>
                    <div class="tmpmp-viewer-tabs">
                        <button class="tmpmp-tab active" data-tab="html"><?php esc_html_e('HTML','tempmail-pro'); ?></button>
                        <button class="tmpmp-tab" data-tab="text"><?php esc_html_e('Plain Text','tempmail-pro'); ?></button>
                    </div>
                    <div class="tmpmp-viewer-body">
                        <div class="tmpmp-tab-content active" id="tmpmp-view-body-html"></div>
                        <div class="tmpmp-tab-content" id="tmpmp-view-body-text"></div>
                    </div>
                </div>

            </div><!-- .tmpmp-columns -->

            <?php
            $faq_pos = get_option('tmpmp_settings',[])['faq_position'] ?? 'below';
            if ( $faq_pos === 'above' ) echo TempMail_FAQ::render();
            ?>
            <?php echo TempMail_Ads::render('bottom_banner'); ?>
            <?php if ( $faq_pos === 'below' ) echo TempMail_FAQ::render(); ?>


            <!-- Toast container -->
            <div class="tmpmp-toast-container" id="tmpmp-toast-container"></div>
        </div>
        <?php
        return ob_get_clean();
    }
}
