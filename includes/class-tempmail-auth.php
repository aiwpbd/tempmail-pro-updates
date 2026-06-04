<?php
/**
 * TempMail Pro — User Authentication (Magic Link, WP Login, Google OAuth, Cancel Sub)
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class TempMail_Auth {

    private static ?self $instance = null;
    public static function instance() : self { return self::$instance ??= new self(); }

    private function __construct() {
        // Magic link — allow both guests and logged-in
        add_action( 'wp_ajax_nopriv_tmpmp_magic_link_request', [ $this, 'ajax_magic_link_request' ] );
        add_action( 'wp_ajax_tmpmp_magic_link_request',        [ $this, 'ajax_magic_link_request' ] );
        add_action( 'wp_ajax_nopriv_tmpmp_magic_link_verify',  [ $this, 'ajax_magic_link_verify'  ] );
        add_action( 'wp_ajax_tmpmp_magic_link_verify',         [ $this, 'ajax_magic_link_verify'  ] );
        // Subscription cancel (logged-in only)
        add_action( 'wp_ajax_tmpmp_cancel_subscription', [ $this, 'ajax_cancel_subscription' ] );
        // Profile & Security
        add_action( 'wp_ajax_tmpmp_update_profile',       [ $this, 'ajax_update_profile'       ] );
        add_action( 'wp_ajax_tmpmp_change_password',      [ $this, 'ajax_change_password'      ] );
        add_action( 'wp_ajax_tmpmp_send_password_reset',  [ $this, 'ajax_send_password_reset'  ] );
        // Avatar
        add_action( 'wp_ajax_tmpmp_upload_avatar',        [ $this, 'ajax_upload_avatar'        ] );
        add_action( 'wp_ajax_tmpmp_remove_avatar',        [ $this, 'ajax_remove_avatar'        ] );
        // Account self-deletion
        add_action( 'wp_ajax_tmpmp_delete_account',          [ $this, 'ajax_delete_account'          ] );
        // Custom Domains (DNS Wizard)
        add_action( 'wp_ajax_tmpmp_add_custom_domain',       [ $this, 'ajax_add_custom_domain'       ] );
        add_action( 'wp_ajax_tmpmp_verify_custom_domain',    [ $this, 'ajax_verify_custom_domain'    ] );
        add_action( 'wp_ajax_tmpmp_delete_custom_domain',    [ $this, 'ajax_delete_custom_domain'    ] );
        add_action( 'wp_ajax_tmpmp_generate_dkim_key',       [ $this, 'ajax_generate_dkim_key'       ] );
        add_filter( 'pre_get_avatar_data',                [ $this, 'filter_avatar_data'        ], 10, 2 );
        // Magic link URL handler
        add_action( 'init', [ $this, 'handle_magic_link_login' ] );
        // Redirect non-admin users after WP login to subscription dashboard
        add_filter( 'login_redirect', [ $this, 'filter_login_redirect' ], 10, 3 );
        // Redirect newly registered users to dashboard
        add_action( 'user_register', [ $this, 'on_user_register' ], 10, 1 );
        // Shortcodes
        add_shortcode( 'tempmail_login',     [ $this, 'render_login_page'     ] );
        add_shortcode( 'tempmail_dashboard', [ $this, 'render_user_dashboard' ] );
        // Header nav menu account button
        add_filter( 'wp_nav_menu_items', [ $this, 'inject_nav_account_btn' ], 99, 2 );
        add_action( 'wp_head',           [ $this, 'output_nav_account_css'  ], 99    );
    }

    // ── URL Helpers ────────────────────────────────────────────────────────────
    public static function dashboard_url() : string {
        $s   = get_option( 'tmpmp_settings', [] );
        $url = $s['dashboard_url'] ?? '';
        if ( $url ) return esc_url_raw( $url );
        // Auto-detect by page slug
        $page = get_page_by_path('tempmail-dashboard') ?? get_page_by_path('dashboard');
        return $page ? get_permalink( $page->ID ) : home_url('/dashboard/');
    }

    public static function login_url() : string {
        $s   = get_option( 'tmpmp_settings', [] );
        $url = $s['login_page_url'] ?? '';
        if ( $url ) return esc_url_raw( $url );
        $page = get_page_by_path('tempmail-login') ?? get_page_by_path('login');
        return $page ? get_permalink( $page->ID ) : home_url('/login/');
    }

    // ── Header Nav — inject account button (dropdown) ────────────────────────
    public function inject_nav_account_btn( string $items, object $args ) : string {
        $s   = get_option( 'tmpmp_settings', [] );

        // ── 0. Global kill-switch ─────────────────────────────────────────────
        if ( empty( $s['nav_enabled'] ) || ! intval( $s['nav_enabled'] ) ) {
            // Default: enabled if the key was never saved (backwards compat)
            if ( array_key_exists( 'nav_enabled', $s ) ) return $items;
        }

        // ── 1. Location gating ────────────────────────────────────────────────
        $loc  = $args->theme_location ?? '';
        $skip = apply_filters( 'tmpmp_nav_skip_locations', [] );
        if ( in_array( $loc, (array) $skip, true ) ) return $items;

        $target = $s['nav_target'] ?? 'all';
        if ( $target === 'specific' ) {
            $allowed_raw = $s['nav_target_locations'] ?? '';
            $allowed     = array_filter( array_map( 'trim', explode( ',', $allowed_raw ) ) );
            if ( $allowed && $loc && ! in_array( $loc, $allowed, true ) ) {
                return $items; // not in the allowed list
            }
        }

        // ── 2. Appearance settings ────────────────────────────────────────────
        $link_style = $s['nav_link_style'] ?? 'pill';
        $btn_style  = $s['nav_btn_style']  ?? 'gradient';

        // ── 3. Logo ───────────────────────────────────────────────────────────
        $logo_icon = sanitize_text_field( $s['nav_logo_icon'] ?? '' );
        $logo_text = sanitize_text_field( $s['nav_logo_text'] ?? '' );
        $logo_url  = esc_url( $s['nav_logo_url'] ?? home_url('/') );
        if ( $logo_icon || $logo_text ) {
            $items = '<li class="menu-item tmpmp-nav-logo-item">' .
                '<a href="' . $logo_url . '" class="tmpmp-nav-logo-link">' .
                ( $logo_icon ? '<span class="tmpmp-nav-logo-icon">' . esc_html($logo_icon) . '</span>' : '' ) .
                ( $logo_text ? '<span class="tmpmp-nav-logo-text">'  . esc_html($logo_text) . '</span>'  : '' ) .
                '</a></li>' . $items; // prepend so logo is first
        }

        // ── 4. Home link ──────────────────────────────────────────────────────
        if ( ! empty( $s['nav_show_home'] ) ) {
            $home_label = sanitize_text_field( $s['nav_home_label'] ?? __('Home','tempmail-pro') );
            $home_url   = esc_url( $s['nav_home_url'] ?? home_url('/') );
            $items .= '<li class="menu-item tmpmp-nav-page-item">' .
                '<a href="' . $home_url . '" class="tmpmp-nav-page-link tmpmp-nav-link-style-' . esc_attr($link_style) . '">' .
                '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>' .
                esc_html( $home_label ) . '</a></li>';
        }

        // ── 5. Pricing link ───────────────────────────────────────────────────
        $pricing_url   = $s['nav_pricing_url']   ?? home_url('/tempmail-pricing/');
        $pricing_label = ( $s['nav_pricing_label'] ?? '' ) ?: __( 'Pricing', 'tempmail-pro' );
        if ( $pricing_url ) {
            $items .= '<li class="menu-item tmpmp-nav-page-item">' .
                '<a href="' . esc_url( $pricing_url ) . '" class="tmpmp-nav-page-link tmpmp-nav-link-style-' . esc_attr($link_style) . '">' .
                '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/></svg>' .
                esc_html( $pricing_label ) . '</a></li>';
        }

        // ── 6. Custom links ───────────────────────────────────────────────────
        foreach ( (array)( $s['nav_custom_links'] ?? [] ) as $lnk ) {
            $lbl = sanitize_text_field( $lnk['label'] ?? '' );
            $url = esc_url( $lnk['url'] ?? '' );
            if ( ! $lbl || ! $url ) continue;
            $items .= '<li class="menu-item tmpmp-nav-page-item">' .
                '<a href="' . $url . '" class="tmpmp-nav-page-link tmpmp-nav-link-style-' . esc_attr($link_style) . '">' . esc_html( $lbl ) . '</a>' .
                '</li>';
        }

        // ── 7. Account button (logged-in vs guest) ────────────────────────────
        if ( is_user_logged_in() ) {
            if ( empty( $s['nav_show_account_drop'] ) && array_key_exists('nav_show_account_drop', $s) ) {
                return $items; // dropdown explicitly disabled
            }
            $user       = wp_get_current_user();
            $name       = esc_html( mb_strimwidth( $user->display_name ?: $user->user_email, 0, 18, "…" ) );
            $avatar     = get_user_meta( $user->ID, 'tmpmp_avatar_url', true );
            $dash_url   = esc_url( self::dashboard_url() );
            $logout_url = esc_url( wp_logout_url( get_permalink() ?: home_url('/') ) );
            $initial    = esc_html( strtoupper( substr( $user->display_name ?: $user->user_email, 0, 1 ) ) );
            $dash_label = sanitize_text_field( $s['nav_dashboard_label'] ?? __('My Dashboard','tempmail-pro') );

            $av = $avatar
                ? '<img src="' . esc_url( $avatar ) . '" alt="" class="tmpmp-nav-av-img">'
                : '<span class="tmpmp-nav-av-init">' . $initial . '</span>';

            $chevron = '<svg class="tmpmp-nav-chevron" width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 12 15 18 9"/></svg>';

            $items .=
                '<li class="menu-item tmpmp-nav-account-item">'
                  . '<div class="tmpmp-nav-trigger">'
                      . '<div class="tmpmp-nav-av">' . $av . '</div>'
                      . '<span class="tmpmp-nav-uname">' . $name . '</span>'
                      . $chevron
                  . '</div>'
                  . '<div class="tmpmp-nav-dropdown">'
                      . '<div class="tmpmp-nav-dropdown-inner">'
                          . '<div class="tmpmp-nav-drop-head">'
                              . '<div class="tmpmp-nav-av tmpmp-nav-av--lg">' . $av . '</div>'
                              . '<span>' . $name . '</span>'
                          . '</div>'
                          . '<div class="tmpmp-nav-drop-divider"></div>'
                          . '<a href="' . $dash_url . '" class="tmpmp-nav-drop-item">'
                              . '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>'
                              . esc_html( $dash_label )
                          . '</a>'
                          . '<a href="' . $logout_url . '" class="tmpmp-nav-drop-item tmpmp-nav-drop-item--logout">'
                              . '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>'
                              . esc_html__( 'Logout', 'tempmail-pro' )
                          . '</a>'
                      . '</div>'
                  . '</div>'
                . '</li>';
        } else {
            // Guest sign-in button
            $show_btn = ! array_key_exists('nav_show_account_btn', $s) || ! empty( $s['nav_show_account_btn'] );
            if ( $show_btn ) {
                $login_url = esc_url( self::login_url() );
                $btn_label = sanitize_text_field( $s['nav_account_btn_label'] ?? __('Sign In / Register','tempmail-pro') );
                $items .= '<li class="menu-item tmpmp-nav-account-item">'
                    . '<a href="' . $login_url . '" class="tmpmp-nav-login-btn tmpmp-nav-btn-style-' . esc_attr($btn_style) . '">'
                    . '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M15 3h4a2 2 0 012 2v14a2 2 0 01-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>'
                    . esc_html( $btn_label )
                    . '</a></li>';
            }
        }
        return $items;
    }


    // ── Header Nav — CSS output (dropdown + style variants) ─────────────────
    public function output_nav_account_css() : void {
        $s          = get_option( 'tmpmp_settings', [] );
        $link_style = $s['nav_link_style'] ?? 'pill';
        $btn_style  = $s['nav_btn_style']  ?? 'gradient';

        // Spacing & Sizing values
        $gap     = max( 0,  min( 40, intval( $s['nav_item_gap']       ?? 10 ) ) );
        $px      = max( 0,  min( 40, intval( $s['nav_link_px']        ?? 14 ) ) );
        $py      = max( 0,  min( 24, intval( $s['nav_link_py']        ?? 6  ) ) );
        $radius  = max( 0,  min( 32, intval( $s['nav_link_radius']    ?? 8  ) ) );
        $fs      = max( 10, min( 22, intval( $s['nav_font_size']      ?? 13 ) ) );
        $mTop    = max( 0,  min( 40, intval( $s['nav_margin_top']     ?? 0  ) ) );
        $mBot    = max( 0,  min( 40, intval( $s['nav_margin_bottom']  ?? 0  ) ) );
        $minH    = max( 0,  min( 80, intval( $s['nav_bar_min_height'] ?? 0  ) ) );
        ?>
<style id="tmpmp-nav-account-css">
/* TempMail Pro — header nav account dropdown + menu-temp */
:root{
  --tmpmp-nav-gap:<?php echo $gap; ?>px;
  --tmpmp-nav-px:<?php echo $px; ?>px;
  --tmpmp-nav-py:<?php echo $py; ?>px;
  --tmpmp-nav-radius:<?php echo $radius; ?>px;
  --tmpmp-nav-fs:<?php echo $fs; ?>px;
  --tmpmp-nav-mt:<?php echo $mTop; ?>px;
  --tmpmp-nav-mb:<?php echo $mBot; ?>px;
  --tmpmp-nav-minh:<?php echo ($minH > 0 ? $minH.'px' : 'auto'); ?>;
}
/* Logo */
.tmpmp-nav-logo-item{display:inline-flex!important;align-items:center;list-style:none!important;padding:0!important;margin:var(--tmpmp-nav-mt) var(--tmpmp-nav-gap) var(--tmpmp-nav-mb) 0!important;}
.tmpmp-nav-logo-link{display:inline-flex!important;align-items:center;gap:7px;font-size:calc(var(--tmpmp-nav-fs) + 2px);font-weight:800;color:inherit!important;text-decoration:none!important;white-space:nowrap;}
.tmpmp-nav-logo-icon{font-size:calc(var(--tmpmp-nav-fs) + 4px);line-height:1;}
.tmpmp-nav-logo-text{letter-spacing:-.3px;}
/* Account item */
.tmpmp-nav-account-item{position:relative!important;display:inline-flex!important;align-items:center;list-style:none!important;padding:0!important;margin:var(--tmpmp-nav-mt) 0 var(--tmpmp-nav-mb) var(--tmpmp-nav-gap)!important;min-height:var(--tmpmp-nav-minh);}
/* Trigger pill */
.tmpmp-nav-trigger{display:inline-flex;align-items:center;gap:8px;padding:var(--tmpmp-nav-py) var(--tmpmp-nav-px) var(--tmpmp-nav-py) 5px;border-radius:99px;background:linear-gradient(135deg,rgba(99,102,241,.14),rgba(139,92,246,.09));border:1.5px solid rgba(99,102,241,.32);cursor:pointer;transition:all .2s ease;user-select:none;min-height:var(--tmpmp-nav-minh);}
.tmpmp-nav-account-item:hover .tmpmp-nav-trigger,.tmpmp-nav-account-item:focus-within .tmpmp-nav-trigger{background:linear-gradient(135deg,rgba(99,102,241,.24),rgba(139,92,246,.18));border-color:rgba(99,102,241,.6);box-shadow:0 4px 16px rgba(99,102,241,.22);}
/* Avatar circle */
.tmpmp-nav-av{width:28px;height:28px;border-radius:50%;overflow:hidden;display:flex;align-items:center;justify-content:center;background:linear-gradient(135deg,#6366f1,#8b5cf6);flex-shrink:0;border:2px solid rgba(255,255,255,.15);}
.tmpmp-nav-av--lg{width:34px;height:34px;}
.tmpmp-nav-av-img{width:100%;height:100%;object-fit:cover;display:block;}
.tmpmp-nav-av-init{font-size:12px;font-weight:800;color:#fff;line-height:1;}
/* Username */
.tmpmp-nav-uname{font-size:var(--tmpmp-nav-fs);font-weight:700;color:#818cf8;white-space:nowrap;max-width:130px;overflow:hidden;text-overflow:ellipsis;}
/* Chevron */
.tmpmp-nav-chevron{color:#818cf8;transition:transform .22s ease;flex-shrink:0;}
.tmpmp-nav-account-item:hover .tmpmp-nav-chevron,.tmpmp-nav-account-item:focus-within .tmpmp-nav-chevron{transform:rotate(180deg);}
/* Dropdown panel */
.tmpmp-nav-dropdown{position:absolute;top:100%;right:0;padding-top:10px;min-width:210px;z-index:99999;opacity:0;visibility:hidden;transform:translateY(-6px) scale(.97);transition:opacity .2s ease,transform .22s ease,visibility .2s;pointer-events:none;}
.tmpmp-nav-dropdown-inner{background:#1a1040;border:1.5px solid rgba(99,102,241,.28);border-radius:14px;box-shadow:0 12px 40px rgba(0,0,0,.5),0 0 0 1px rgba(99,102,241,.08);padding:8px;}
.tmpmp-nav-account-item:hover .tmpmp-nav-dropdown,.tmpmp-nav-account-item:focus-within .tmpmp-nav-dropdown{opacity:1;visibility:visible;transform:translateY(0) scale(1);pointer-events:auto;}
/* Dropdown head */
.tmpmp-nav-drop-head{display:flex;align-items:center;gap:10px;padding:8px 10px 10px;font-size:13px;font-weight:700;color:#c7d2fe;}
.tmpmp-nav-drop-divider{height:1px;background:rgba(99,102,241,.18);margin:4px 2px 6px;}
/* Dropdown items */
.tmpmp-nav-drop-item{display:flex!important;align-items:center;gap:9px;padding:10px 12px;border-radius:9px;font-size:13px;font-weight:600;color:#a5b4fc!important;text-decoration:none!important;transition:all .15s ease;white-space:nowrap;}
.tmpmp-nav-drop-item svg{flex-shrink:0;}
.tmpmp-nav-drop-item:hover{background:rgba(99,102,241,.18)!important;color:#fff!important;transform:translateX(3px);}
.tmpmp-nav-drop-item--logout{color:#fca5a5!important;}
.tmpmp-nav-drop-item--logout:hover{background:rgba(239,68,68,.18)!important;color:#fff!important;}
/* Page links */
.tmpmp-nav-page-item{display:inline-flex!important;align-items:center;list-style:none!important;padding:0!important;margin:var(--tmpmp-nav-mt) 0 var(--tmpmp-nav-mb) var(--tmpmp-nav-gap)!important;min-height:var(--tmpmp-nav-minh);}
/* Link style: pill (default) */
.tmpmp-nav-page-link{display:inline-flex!important;align-items:center;gap:5px;padding:var(--tmpmp-nav-py) var(--tmpmp-nav-px);border-radius:var(--tmpmp-nav-radius);font-size:var(--tmpmp-nav-fs);font-weight:700;text-decoration:none!important;color:#6366f1!important;transition:all .2s ease;white-space:nowrap;border:1.5px solid rgba(99,102,241,.25);background:transparent;}
.tmpmp-nav-page-link:hover{background:linear-gradient(135deg,#6366f1,#8b5cf6)!important;color:#fff!important;border-color:transparent;box-shadow:0 3px 14px rgba(99,102,241,.4);transform:translateY(-1px);}
.tmpmp-nav-page-link svg{flex-shrink:0;}
.tmpmp-nav-page-link:hover svg{stroke:#fff;}
<?php if ( $link_style === 'flat' ) : ?>
/* Link style: flat */
.tmpmp-nav-link-style-flat{border:none!important;color:#6366f1!important;background:transparent!important;}
.tmpmp-nav-link-style-flat:hover{background:rgba(99,102,241,.08)!important;color:#4f46e5!important;border:none!important;transform:none!important;box-shadow:none!important;}
<?php elseif ( $link_style === 'minimal' ) : ?>
/* Link style: minimal */
.tmpmp-nav-link-style-minimal{border:none!important;border-bottom:2px solid rgba(99,102,241,.4)!important;border-radius:0!important;color:#6366f1!important;background:transparent!important;padding-left:2px!important;padding-right:2px!important;}
.tmpmp-nav-link-style-minimal:hover{border-bottom-color:#4f46e5!important;color:#4f46e5!important;background:transparent!important;transform:none!important;box-shadow:none!important;}
<?php endif; ?>
/* Sign-In button — gradient (default) */
.tmpmp-nav-login-btn{display:inline-flex!important;align-items:center;gap:7px;padding:var(--tmpmp-nav-py) var(--tmpmp-nav-px);border-radius:var(--tmpmp-nav-radius);font-size:var(--tmpmp-nav-fs);font-weight:700;text-decoration:none!important;background:linear-gradient(135deg,#6366f1,#8b5cf6)!important;color:#fff!important;box-shadow:0 2px 12px rgba(99,102,241,.35);transition:all .2s ease;white-space:nowrap;margin:var(--tmpmp-nav-mt) 0 var(--tmpmp-nav-mb);min-height:var(--tmpmp-nav-minh);}
.tmpmp-nav-login-btn:hover{transform:translateY(-2px);box-shadow:0 6px 20px rgba(99,102,241,.55)!important;filter:brightness(1.08);color:#fff!important;}
<?php if ( $btn_style === 'solid' ) : ?>
/* Button style: solid */
.tmpmp-nav-btn-style-solid{background:#6366f1!important;background-image:none!important;box-shadow:none!important;}
.tmpmp-nav-btn-style-solid:hover{background:#4f46e5!important;box-shadow:0 4px 14px rgba(99,102,241,.4)!important;filter:none!important;}
<?php elseif ( $btn_style === 'outline' ) : ?>
/* Button style: outline */
.tmpmp-nav-btn-style-outline{background:transparent!important;background-image:none!important;color:#6366f1!important;border:2px solid #6366f1!important;box-shadow:none!important;}
.tmpmp-nav-btn-style-outline:hover{background:#6366f1!important;color:#fff!important;filter:none!important;}
<?php endif; ?>
/* Mobile */
@media(max-width:768px){.tmpmp-nav-uname{display:none;}.tmpmp-nav-dropdown{right:auto;left:0;}.tmpmp-nav-login-btn{font-size:12px;}.tmpmp-nav-logo-text{display:none;}}
</style>
    <?php }


    // ── Login Redirect Filter ──────────────────────────────────────────────────

    public function filter_login_redirect( string $redirect_to, string $requested_redirect_to, $user ) : string {
        if ( is_wp_error( $user ) ) return $redirect_to;
        // Admins and editors go to WP dashboard as normal
        if ( user_can( $user, 'manage_options' ) || user_can( $user, 'edit_posts' ) ) {
            return $redirect_to;
        }
        // All other users (subscribers) go to subscription dashboard
        return self::dashboard_url();
    }

    // ── New User Registration Redirect ─────────────────────────────────────────
    public function on_user_register( int $user_id ) : void {
        // Never redirect during AJAX — would kill the JSON response
        if ( wp_doing_ajax() ) return;
        // Only redirect frontend registrations (not admin-created users)
        if ( is_admin() ) return;
        wp_safe_redirect( self::dashboard_url() );
        exit;
    }

    // ── Magic Link Request ─────────────────────────────────────────────────────
    public function ajax_magic_link_request() : void {
        check_ajax_referer( 'tempmail_pro_nonce', 'nonce' );
        $email = sanitize_email( $_POST['email'] ?? '' );
        if ( ! is_email($email) ) {
            wp_send_json_error(['message' => __('Please enter a valid email address.','tempmail-pro')]);
        }

        $user = get_user_by('email', $email);
        if ( ! $user ) {
            // Auto-create account
            $username = explode('@', $email)[0] . '_' . rand(100,999);
            $user_id  = wp_create_user( $username, wp_generate_password(24, true, true), $email );
            if ( is_wp_error($user_id) ) {
                wp_send_json_error(['message' => __('Could not create account. Try standard login.','tempmail-pro')]);
            }
            $user = get_user_by('id', $user_id);
        }

        $token = bin2hex( random_bytes(20) );
        set_transient( 'tmpmp_magic_' . $token, $user->ID, 15 * MINUTE_IN_SECONDS );

        $login_link = add_query_arg( [ 'tmpmp_magic' => $token ], home_url('/') );
        $site_name  = get_bloginfo('name');
        $site_url   = home_url('/');
        $host       = wp_parse_url( home_url(), PHP_URL_HOST );

        // ── Pull Login Email customizer settings ───────────────────────────────
        $le          = get_option( 'tmpmp_settings', [] );
        $from_name   = ! empty($le['le_from_name'])    ? $le['le_from_name']    : $site_name;
        $from_email  = 'noreply@' . $host;
        $hdr_title   = ! empty($le['le_header_title']) ? $le['le_header_title'] : $site_name;
        $logo_emoji  = ! empty($le['le_logo_emoji'])   ? $le['le_logo_emoji']   : '✉';
        $hdr_color1  = ! empty($le['le_hdr_color1'])   ? $le['le_hdr_color1']   : '#6366f1';
        $hdr_color2  = ! empty($le['le_hdr_color2'])   ? $le['le_hdr_color2']   : '#8b5cf6';
        $btn_text    = ! empty($le['le_btn_text'])      ? $le['le_btn_text']     : sprintf( __('Sign In to %s','tempmail-pro'), $site_name );
        $btn_color   = ! empty($le['le_btn_color'])     ? $le['le_btn_color']    : '#6366f1';
        $body_msg    = ! empty($le['le_body_msg'])      ? $le['le_body_msg']     : __('Click the button below to sign in instantly — no password needed.','tempmail-pro');
        $security    = ! empty($le['le_security_msg'])  ? $le['le_security_msg'] : __('If you did not request this link, you can safely ignore this email. Someone may have entered your email address by mistake.','tempmail-pro');
        $footer_txt  = ! empty($le['le_footer_text'])   ? $le['le_footer_text']  : '© ' . date('Y') . ' ' . $site_name;

        // ── Subject ───────────────────────────────────────────────────────────
        if ( ! empty($le['le_subject']) ) {
            $subject = str_replace( '{site}', $site_name, $le['le_subject'] );
        } else {
            $subject = sprintf( __('Your login link for %s','tempmail-pro'), $site_name );
        }

        // ── HTML email body ────────────────────────────────────────────────────
        $html = '
<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body style="margin:0;padding:0;background:#f1f5f9;font-family:Arial,sans-serif;">
  <table width="100%" cellpadding="0" cellspacing="0" style="background:#f1f5f9;padding:32px 16px;">
    <tr><td align="center">
      <table width="100%" cellpadding="0" cellspacing="0" style="max-width:520px;background:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 4px 20px rgba(0,0,0,.08);">

        <!-- Header -->
        <tr>
          <td style="background:linear-gradient(135deg,' . esc_attr($hdr_color1) . ',' . esc_attr($hdr_color2) . ');padding:28px 32px;text-align:center;">
            <div style="font-size:32px;line-height:1;margin-bottom:8px;">' . esc_html($logo_emoji) . '</div>
            <div style="color:#ffffff;font-size:20px;font-weight:700;letter-spacing:.5px;">' . esc_html($hdr_title) . '</div>
          </td>
        </tr>

        <!-- Body -->
        <tr>
          <td style="padding:32px;">
            <h2 style="margin:0 0 12px;font-size:18px;color:#0f172a;">&#128279; Your Magic Login Link</h2>
            <p style="margin:0 0 24px;font-size:14px;color:#475569;line-height:1.6;">' . nl2br( esc_html($body_msg) ) . '<br><strong>' . __('This link expires in 15 minutes','tempmail-pro') . '</strong> ' . __('and can only be used once.','tempmail-pro') . '</p>

            <!-- CTA Button -->
            <table cellpadding="0" cellspacing="0" style="margin:0 auto 24px;">
              <tr>
                <td style="background:' . esc_attr($btn_color) . ';border-radius:8px;">
                  <a href="' . esc_url($login_link) . '"
                     style="display:inline-block;padding:14px 32px;color:#ffffff;font-size:15px;font-weight:700;text-decoration:none;border-radius:8px;letter-spacing:.3px;">
                    ' . esc_html($btn_text) . '
                  </a>
                </td>
              </tr>
            </table>

            <!-- Fallback link -->
            <p style="margin:0 0 8px;font-size:12px;color:#94a3b8;">' . __('Or copy and paste this link into your browser:','tempmail-pro') . '</p>
            <p style="margin:0 0 24px;font-size:12px;word-break:break-all;">
              <a href="' . esc_url($login_link) . '" style="color:' . esc_attr($hdr_color1) . ';">' . esc_html($login_link) . '</a>
            </p>

            <!-- Security note -->
            <table width="100%" cellpadding="0" cellspacing="0" style="background:#f8fafc;border-radius:8px;margin-bottom:8px;">
              <tr>
                <td style="padding:12px 16px;font-size:12px;color:#64748b;">
                  &#128274; ' . esc_html($security) . '
                </td>
              </tr>
            </table>
          </td>
        </tr>

        <!-- Footer -->
        <tr>
          <td style="padding:16px 32px;background:#f8fafc;border-top:1px solid #e2e8f0;text-align:center;">
            <p style="margin:0;font-size:11px;color:#94a3b8;">
              <a href="' . esc_url($site_url) . '" style="color:' . esc_attr($hdr_color1) . ';text-decoration:none;">' . esc_html($footer_txt) . '</a>
            </p>
          </td>
        </tr>

      </table>
    </td></tr>
  </table>
</body>
</html>';

        // ── Mail headers ───────────────────────────────────────────────────────
        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $from_name . ' <' . $from_email . '>',
            'Reply-To: ' . $from_name . ' <' . $from_email . '>',
            'X-Mailer: TempMail Pro ' . TMPMP_VERSION,
        ];

        $sent = wp_mail( $email, $subject, $html, $headers );

        if ( ! $sent ) {
            wp_send_json_error( [ 'message' => __( 'Failed to send email. Please check your mail server settings or try again.', 'tempmail-pro' ) ] );
        }

        wp_send_json_success( [ 'message' => __( 'Magic link sent! Check your inbox (and spam folder if needed).', 'tempmail-pro' ) ] );

    }

    // ── Magic Link Verify (AJAX token check) ──────────────────────────────────
    public function ajax_magic_link_verify() : void {
        check_ajax_referer( 'tempmail_pro_nonce', 'nonce' );
        $token   = sanitize_text_field( $_POST['token'] ?? '' );
        $user_id = $token ? get_transient('tmpmp_magic_' . $token) : false;
        if ( ! $user_id ) {
            wp_send_json_error(['message' => __('Token expired or invalid.','tempmail-pro')]);
        }
        delete_transient('tmpmp_magic_' . $token);
        wp_set_current_user($user_id);
        wp_set_auth_cookie($user_id, true);
        wp_send_json_success(['redirect' => self::dashboard_url()]);
    }

    // ── Magic Link URL Handler (GET redirect) ─────────────────────────────────
    public function handle_magic_link_login() : void {
        $token = sanitize_text_field( $_GET['tmpmp_magic'] ?? '' );
        if ( ! $token ) return;
        $user_id = get_transient('tmpmp_magic_' . $token);
        if ( ! $user_id ) {
            wp_die( __('This magic link has expired or already been used.', 'tempmail-pro'), '', ['response' => 403] );
        }
        delete_transient('tmpmp_magic_' . $token);
        wp_set_current_user($user_id);
        wp_set_auth_cookie($user_id, true);
        wp_safe_redirect( self::dashboard_url() );
        exit;
    }

    // ── Cancel Subscription ───────────────────────────────────────────────────
    public function ajax_cancel_subscription() : void {
        check_ajax_referer('tempmail_pro_nonce','nonce');
        $user_id = get_current_user_id();
        if ( ! $user_id ) wp_send_json_error(['message' => 'Login required.'], 401);
        $ok = TempMail_Subscription::cancel($user_id);
        $ok
            ? wp_send_json_success(['message' => __('Subscription cancelled. Access continues until period end.','tempmail-pro')])
            : wp_send_json_error(['message' => __('No active subscription found.','tempmail-pro')]);
    }

    // ── Google OAuth URL ──────────────────────────────────────────────────────
    public static function google_auth_url() : string {
        $settings  = get_option('tmpmp_settings', []);
        $client_id = $settings['google_client_id'] ?? '';
        if ( ! $client_id ) return '';
        return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
            'client_id'     => $client_id,
            'redirect_uri'  => home_url('/wp-json/tempmail-pro/v1/oauth/google'),
            'response_type' => 'code',
            'scope'         => 'openid email profile',
            'state'         => wp_create_nonce('tmpmp_google_oauth'),
        ]);
    }

    // ── Update Profile ────────────────────────────────────────────────────────
    public function ajax_update_profile() : void {
        check_ajax_referer( 'tempmail_pro_nonce', 'nonce' );
        if ( ! is_user_logged_in() ) {
            wp_send_json_error( [ 'message' => __( 'You must be logged in.', 'tempmail-pro' ) ] );
        }

        global $wpdb;
        $user_id      = get_current_user_id();
        $first_name   = sanitize_text_field( wp_unslash( $_POST['first_name']   ?? '' ) );
        $last_name    = sanitize_text_field( wp_unslash( $_POST['last_name']    ?? '' ) );
        $display_name = sanitize_text_field( wp_unslash( $_POST['display_name'] ?? '' ) );

        // Fallback: build display name from first+last if left blank
        if ( empty( $display_name ) ) {
            $display_name = trim( "$first_name $last_name" );
        }
        if ( empty( $display_name ) ) {
            $display_name = get_userdata( $user_id )->user_login;
        }

        // ── 1. Explicitly save first/last as user meta ───────────────────────
        update_user_meta( $user_id, 'first_name', $first_name );
        update_user_meta( $user_id, 'last_name',  $last_name  );

        // ── 2. Update display_name in wp_users via wp_update_user ────────────
        $result = wp_update_user( [
            'ID'           => $user_id,
            'display_name' => $display_name,
        ] );

        // ── 3. Fallback: direct DB write if wp_update_user fails ─────────────
        if ( is_wp_error( $result ) ) {
            $updated = $wpdb->update(
                $wpdb->users,
                [ 'display_name' => $display_name ],
                [ 'ID'           => $user_id ],
                [ '%s' ],
                [ '%d' ]
            );
            if ( $updated === false ) {
                wp_send_json_error( [ 'message' => __( 'Failed to save profile. Please try again.', 'tempmail-pro' ) ] );
            }
            // Clean user cache so next page load reads fresh data
            clean_user_cache( $user_id );
        }

        wp_send_json_success( [
            'message'      => __( 'Profile updated successfully.', 'tempmail-pro' ),
            'display_name' => $display_name,
            'first_name'   => $first_name,
            'last_name'    => $last_name,
        ] );
    }

    // ── Change Password ───────────────────────────────────────────────────────
    public function ajax_change_password() : void {
        check_ajax_referer( 'tempmail_pro_nonce', 'nonce' );
        if ( ! is_user_logged_in() ) {
            wp_send_json_error( [ 'message' => __( 'You must be logged in.', 'tempmail-pro' ) ] );
        }
        $current  = wp_unslash( $_POST['current_password'] ?? '' );
        $new      = wp_unslash( $_POST['new_password']     ?? '' );
        $confirm  = wp_unslash( $_POST['confirm_password'] ?? '' );

        if ( empty( $current ) || empty( $new ) || empty( $confirm ) ) {
            wp_send_json_error( [ 'message' => __( 'All password fields are required.', 'tempmail-pro' ) ] );
        }
        if ( $new !== $confirm ) {
            wp_send_json_error( [ 'message' => __( 'New passwords do not match.', 'tempmail-pro' ) ] );
        }
        if ( strlen( $new ) < 8 ) {
            wp_send_json_error( [ 'message' => __( 'New password must be at least 8 characters.', 'tempmail-pro' ) ] );
        }

        $user = wp_get_current_user();
        if ( ! wp_check_password( $current, $user->user_pass, $user->ID ) ) {
            wp_send_json_error( [ 'message' => __( 'Current password is incorrect.', 'tempmail-pro' ) ] );
        }

        wp_set_password( $new, $user->ID );
        // Re-authenticate so the user isn't logged out
        wp_set_auth_cookie( $user->ID, true );
        wp_send_json_success( [ 'message' => __( 'Password changed successfully.', 'tempmail-pro' ) ] );
    }

    // ── Send Password Reset Email ─────────────────────────────────────────────
    public function ajax_send_password_reset() : void {
        check_ajax_referer( 'tempmail_pro_nonce', 'nonce' );
        if ( ! is_user_logged_in() ) {
            wp_send_json_error( [ 'message' => __( 'You must be logged in.', 'tempmail-pro' ) ] );
        }
        $user  = wp_get_current_user();
        $key   = get_password_reset_key( $user );
        if ( is_wp_error( $key ) ) {
            wp_send_json_error( [ 'message' => $key->get_error_message() ] );
        }
        $reset_link = network_site_url( "wp-login.php?action=rp&key=$key&login=" . rawurlencode( $user->user_login ), 'login' );
        $subject    = sprintf( __( 'Password Reset for %s', 'tempmail-pro' ), get_bloginfo( 'name' ) );
        $message    = sprintf(
            __( "Hi %s,\n\nClick the link below to reset your password:\n\n%s\n\nThis link expires in 24 hours.\n\nIf you didn't request this, ignore this email.", 'tempmail-pro' ),
            $user->display_name,
            $reset_link
        );
        $sent = wp_mail( $user->user_email, $subject, $message );
        if ( ! $sent ) {
            wp_send_json_error( [ 'message' => __( 'Failed to send reset email. Please check mail settings.', 'tempmail-pro' ) ] );
        }
        wp_send_json_success( [ 'message' => sprintf( __( 'Reset link sent to %s', 'tempmail-pro' ), $user->user_email ) ] );
    }

    // ── Avatar: filter get_avatar to serve custom upload ─────────────────────
    public function filter_avatar_data( array $args, $id_or_email ) : array {
        $user_id = 0;
        if ( is_numeric( $id_or_email ) ) {
            $user_id = (int) $id_or_email;
        } elseif ( $id_or_email instanceof \WP_User ) {
            $user_id = $id_or_email->ID;
        } elseif ( is_string( $id_or_email ) ) {
            $u = get_user_by( 'email', $id_or_email );
            if ( $u ) $user_id = $u->ID;
        }
        if ( $user_id ) {
            $url = get_user_meta( $user_id, 'tmpmp_avatar_url', true );
            if ( $url ) {
                $args['url']           = esc_url( $url );
                $args['found_avatar']  = true;
            }
        }
        return $args;
    }

    // ── Avatar: upload ────────────────────────────────────────────────────────
    public function ajax_upload_avatar() : void {
        check_ajax_referer( 'tempmail_pro_nonce', 'nonce' );
        if ( ! is_user_logged_in() ) {
            wp_send_json_error( [ 'message' => __( 'You must be logged in.', 'tempmail-pro' ) ] );
        }
        if ( empty( $_FILES['avatar'] ) || (int) $_FILES['avatar']['error'] !== UPLOAD_ERR_OK ) {
            $code = $_FILES['avatar']['error'] ?? -1;
            wp_send_json_error( [ 'message' => sprintf( __( 'Upload error (code %d). Please try again.', 'tempmail-pro' ), $code ) ] );
        }

        $file         = $_FILES['avatar'];
        $allowed_mime = [ 'image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp' ];

        // ── MIME validation (finfo with mime_content_type fallback) ───────────
        $real_mime = false;
        if ( function_exists( 'finfo_open' ) ) {
            $finfo     = finfo_open( FILEINFO_MIME_TYPE );
            $real_mime = $finfo ? finfo_file( $finfo, $file['tmp_name'] ) : false;
            if ( $finfo ) finfo_close( $finfo );
        }
        if ( ! $real_mime && function_exists( 'mime_content_type' ) ) {
            $real_mime = mime_content_type( $file['tmp_name'] );
        }
        // Fallback: trust the browser-supplied MIME (last resort)
        if ( ! $real_mime ) {
            $real_mime = $file['type'];
        }

        if ( ! in_array( strtolower( $real_mime ), $allowed_mime, true ) ) {
            wp_send_json_error( [ 'message' => __( 'Only JPG, PNG, GIF, or WebP images are allowed.', 'tempmail-pro' ) ] );
        }
        if ( $file['size'] > 2 * 1024 * 1024 ) {
            wp_send_json_error( [ 'message' => __( 'Image must be under 2 MB.', 'tempmail-pro' ) ] );
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';

        $upload = wp_handle_upload( $file, [
            'test_form' => false,
            'mimes'     => [
                'jpg|jpeg|jpe' => 'image/jpeg',
                'png'          => 'image/png',
                'gif'          => 'image/gif',
                'webp'         => 'image/webp',
            ],
        ] );

        if ( ! isset( $upload['url'] ) || ! isset( $upload['file'] ) || isset( $upload['error'] ) ) {
            $err = $upload['error'] ?? __( 'Upload could not be processed.', 'tempmail-pro' );
            wp_send_json_error( [ 'message' => $err ] );
        }

        global $wpdb;
        $user_id    = get_current_user_id();
        $saved_url  = esc_url_raw( $upload['url'] );
        $saved_path = $upload['file'];

        // ── Normalise protocol to match site URL (avoids http vs https mismatch) ─
        $site_scheme = wp_parse_url( home_url(), PHP_URL_SCHEME );
        $saved_url   = preg_replace( '#^https?://#', $site_scheme . '://', $saved_url );

        // ── Delete old avatar file from disk ──────────────────────────────────
        $old_path = get_user_meta( $user_id, 'tmpmp_avatar_path', true );
        if ( $old_path && file_exists( $old_path ) ) {
            @unlink( $old_path );
        }

        // ── Save via WP meta API (handles deduplication + cache correctly) ────
        update_user_meta( $user_id, 'tmpmp_avatar_url',  $saved_url  );
        update_user_meta( $user_id, 'tmpmp_avatar_path', $saved_path );

        // ── Purge all cache layers (WP internal + persistent cache) ───────────
        clean_user_cache( $user_id );
        wp_cache_delete( $user_id,                'user_meta' );
        wp_cache_delete( 'user_meta_' . $user_id, 'default'   );

        // ── Verify the save worked ────────────────────────────────────────────
        $verified_url = get_user_meta( $user_id, 'tmpmp_avatar_url', true );

        if ( empty( $verified_url ) ) {
            wp_send_json_error( [ 'message' => __( 'Failed to save profile picture. Please try again.', 'tempmail-pro' ) ] );
        }

        // ── Return URL with cache-busting timestamp so browser shows new image ─
        wp_send_json_success( [
            'message' => __( 'Profile picture updated.', 'tempmail-pro' ),
            'url'     => add_query_arg( 'v', time(), $verified_url ),
        ] );
    }

    // ── Avatar: remove ────────────────────────────────────────────────────────
    public function ajax_remove_avatar() : void {
        check_ajax_referer( 'tempmail_pro_nonce', 'nonce' );
        if ( ! is_user_logged_in() ) {
            wp_send_json_error( [ 'message' => __( 'You must be logged in.', 'tempmail-pro' ) ] );
        }
        $user_id  = get_current_user_id();

        // Delete file from disk
        $old_path = get_user_meta( $user_id, 'tmpmp_avatar_path', true );
        if ( $old_path && file_exists( $old_path ) ) {
            @unlink( $old_path );
        }

        // Delete via WP meta API (handles deduplication + cache correctly)
        delete_user_meta( $user_id, 'tmpmp_avatar_url' );
        delete_user_meta( $user_id, 'tmpmp_avatar_path' );

        // Purge all cache layers
        clean_user_cache( $user_id );
        wp_cache_delete( $user_id,                'user_meta' );
        wp_cache_delete( 'user_meta_' . $user_id, 'default'   );

        // Return default avatar URL so JS can revert the preview
        $default_url = get_avatar_url( $user_id, [ 'size' => 120, 'default' => 'identicon', 'force_default' => true ] );
        wp_send_json_success( [
            'message' => __( 'Profile picture removed.', 'tempmail-pro' ),
            'url'     => $default_url,
        ] );
    }

    // ── Account: self-deletion ─────────────────────────────────────────────────
    public function ajax_delete_account() : void {
        check_ajax_referer( 'tempmail_pro_nonce', 'nonce' );

        if ( ! is_user_logged_in() ) {
            wp_send_json_error( [ 'message' => __( 'You must be logged in.', 'tempmail-pro' ) ] );
        }

        // Check admin toggle
        $settings = get_option( 'tmpmp_settings', [] );
        if ( empty( $settings['allow_account_deletion'] ) ) {
            wp_send_json_error( [ 'message' => __( 'Account deletion is currently disabled by the administrator.', 'tempmail-pro' ) ] );
        }

        $user    = wp_get_current_user();
        $user_id = (int) $user->ID;

        // Verify the user typed their own email address
        $typed = sanitize_email( wp_unslash( $_POST['confirm_email'] ?? '' ) );
        if ( strtolower( $typed ) !== strtolower( $user->user_email ) ) {
            wp_send_json_error( [ 'message' => __( 'The email address you entered does not match your account. Please try again.', 'tempmail-pro' ) ] );
        }

        global $wpdb;
        $p = $wpdb->prefix;

        // 1. Delete avatar file from disk
        $avatar_path = get_user_meta( $user_id, 'tmpmp_avatar_path', true );
        if ( $avatar_path && file_exists( $avatar_path ) ) {
            @unlink( $avatar_path );
        }

        // 2. Delete all emails for this user's addresses
        $address_ids = $wpdb->get_col( $wpdb->prepare(
            "SELECT id FROM {$p}tmpmp_addresses WHERE user_id = %d",
            $user_id
        ) );
        if ( ! empty( $address_ids ) ) {
            $placeholders = implode( ',', array_fill( 0, count( $address_ids ), '%d' ) );
            $wpdb->query( $wpdb->prepare(
                "DELETE FROM {$p}tmpmp_emails WHERE address_id IN ( $placeholders )",
                ...$address_ids
            ) );
        }

        // 3. Delete addresses
        $wpdb->delete( "{$p}tmpmp_addresses",    [ 'user_id' => $user_id ], [ '%d' ] );

        // 4. Delete subscriptions, payments, API keys, rate-limit rows
        $wpdb->delete( "{$p}tmpmp_subscriptions", [ 'user_id' => $user_id ], [ '%d' ] );
        $wpdb->delete( "{$p}tmpmp_payments",      [ 'user_id' => $user_id ], [ '%d' ] );
        $wpdb->delete( "{$p}tmpmp_api_keys",      [ 'user_id' => $user_id ], [ '%d' ] );
        $wpdb->delete( "{$p}tmpmp_ratelimit",     [ 'user_id' => $user_id ], [ '%d' ] );

        // 5. Delete the WordPress user (also removes all usermeta)
        require_once ABSPATH . 'wp-admin/includes/user.php';
        wp_delete_user( $user_id );

        // 6. Destroy the session immediately
        wp_logout();

        wp_send_json_success( [
            'message'      => __( 'Your account has been permanently deleted.', 'tempmail-pro' ),
            'redirect_url' => home_url( '/' ),
        ] );
    }

    // ── Custom Domain: Add ─────────────────────────────────────────────────────
    public function ajax_add_custom_domain() : void {
        ob_start();
        @set_time_limit( 30 );

        // 1 — Verify nonce
        check_ajax_referer( 'tempmail_pro_nonce', 'nonce' );

        // 2 — Must be logged in
        if ( ! is_user_logged_in() ) {
            ob_end_clean();
            wp_send_json_error( [ 'message' => __( 'You must be logged in.', 'tempmail-pro' ) ] );
            return;
        }

        $user_id = get_current_user_id();
        global $wpdb;
        $wpdb->suppress_errors( true );
        $wpdb->hide_errors();

        // 3 — Validate domain input
        $domain = sanitize_text_field( wp_unslash( $_POST['domain'] ?? '' ) );
        if ( ! $domain ) {
            ob_end_clean();
            wp_send_json_error( [ 'message' => __( 'Please enter a domain name.', 'tempmail-pro' ) ] );
            return;
        }

        // 4 — Check active subscription
        $has_sub = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM `{$wpdb->prefix}tmpmp_subscriptions`
             WHERE user_id = %d AND status IN ('active','trialing')",
            $user_id
        ) );
        error_log( '[TempMailPro] add_custom_domain: user=' . $user_id . ' has_sub=' . $has_sub . ' domain=' . $domain );

        if ( ! $has_sub ) {
            ob_end_clean();
            error_log( '[TempMailPro] add_custom_domain: BLOCKED — no active subscription for user ' . $user_id );
            wp_send_json_error( [ 'message' => __( 'Custom domains require a paid subscription.', 'tempmail-pro' ) ] );
            return;
        }

        // 5 — Ensure wp_tmpmp_user_domains table exists (idempotent, fast if already exists)
        $ud_table = $wpdb->prefix . 'tmpmp_user_domains';
        $charset  = $wpdb->get_charset_collate();
        $wpdb->query(
            "CREATE TABLE IF NOT EXISTS `{$ud_table}` (
                id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                user_id          BIGINT UNSIGNED NOT NULL,
                domain           VARCHAR(255) NOT NULL,
                status           VARCHAR(32)  NOT NULL DEFAULT 'pending',
                verify_token     VARCHAR(128) NOT NULL DEFAULT '',
                txt_verified     TINYINT(1)   NOT NULL DEFAULT 0,
                mx_verified      TINYINT(1)   NOT NULL DEFAULT 0,
                spf_verified     TINYINT(1)   NOT NULL DEFAULT 0,
                dkim_selector    VARCHAR(64)  NOT NULL DEFAULT 'tmpro',
                dkim_private_key LONGTEXT,
                dkim_public_key  LONGTEXT,
                dkim_verified    TINYINT(1)   NOT NULL DEFAULT 0,
                dmarc_verified   TINYINT(1)   NOT NULL DEFAULT 0,
                last_checked     DATETIME     DEFAULT NULL,
                verified_at      DATETIME     DEFAULT NULL,
                created_at       DATETIME     NOT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY user_domain (user_id, domain),
                KEY status  (status),
                KEY user_id (user_id)
            ) {$charset}"
        );
        if ( $wpdb->last_error ) {
            error_log( '[TempMailPro] add_custom_domain: CREATE TABLE error: ' . $wpdb->last_error );
        }

        // 6 — Insert domain
        $result = TempMail_UserDomains::add( $user_id, $domain );
        if ( is_wp_error( $result ) ) {
            ob_end_clean();
            error_log( '[TempMailPro] add_custom_domain: add() failed — ' . $result->get_error_message() . ' | wpdb last_error: ' . $wpdb->last_error );
            wp_send_json_error( [ 'message' => $result->get_error_message() ] );
            return;
        }

        // 7 — Return new domain row + DNS records
        $row = TempMail_UserDomains::get( $result, $user_id );
        ob_end_clean();
        error_log( '[TempMailPro] add_custom_domain: SUCCESS user=' . $user_id . ' domain=' . $domain . ' id=' . $result );

        if ( ! $row ) {
            wp_send_json_error( [ 'message' => __( 'Domain saved. Please refresh the page to see it.', 'tempmail-pro' ) ] );
            return;
        }

        wp_send_json_success( [
            'message' => __( 'Domain added! Please configure the DNS records below.', 'tempmail-pro' ),
            'domain'  => $row,
            'records' => TempMail_UserDomains::get_required_records( $row ),
        ] );
    }




    // ── Custom Domain: Verify ──────────────────────────────────────────────────────
    public function ajax_verify_custom_domain() : void {
        // Each dns_get_record() call can block 5-30 s on Windows — cap total at 60 s.
        @set_time_limit( 60 );

        check_ajax_referer( 'tempmail_pro_nonce', 'nonce' );
        if ( ! is_user_logged_in() ) {
            wp_send_json_error( [ 'message' => __( 'You must be logged in.', 'tempmail-pro' ) ] );
        }
        $user_id   = get_current_user_id();
        $domain_id = (int) ( $_POST['domain_id'] ?? 0 );
        if ( ! $domain_id ) {
            wp_send_json_error( [ 'message' => __( 'Invalid domain.', 'tempmail-pro' ) ] );
        }

        error_log( '[TmpmpVerify] Starting verify for domain_id=' . $domain_id . ' user=' . $user_id );

        $result = TempMail_UserDomains::verify( $domain_id, $user_id );

        error_log( '[TmpmpVerify] verify() returned: ' . ( is_wp_error( $result ) ? 'WP_ERROR: ' . $result->get_error_message() : wp_json_encode( $result ) ) );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( [ 'message' => $result->get_error_message() ] );
        }
        // Include fresh DKIM key (may have just been generated during verify)
        global $wpdb;
        $fresh_row = $wpdb->get_row( $wpdb->prepare(
            "SELECT dkim_public_key FROM {$wpdb->prefix}tmpmp_user_domains WHERE id = %d AND user_id = %d",
            $domain_id, $user_id
        ) );
        $result['dkim_public_key'] = $fresh_row ? (string) $fresh_row->dkim_public_key : '';
        wp_send_json_success( $result );
    }

    // ── Custom Domain: Generate DKIM Key ──────────────────────────────────────
    public function ajax_generate_dkim_key() : void {
        // RSA 2048-bit generation can take 30-90 s on Windows — give it room.
        @set_time_limit( 120 );

        check_ajax_referer( 'tempmail_pro_nonce', 'nonce' );
        if ( ! is_user_logged_in() ) {
            wp_send_json_error( [ 'message' => __( 'You must be logged in.', 'tempmail-pro' ) ] );
        }
        if ( ! extension_loaded( 'openssl' ) ) {
            error_log( '[TmpmpDKIM] openssl extension NOT loaded' );
            wp_send_json_error( [
                'message'         => __( 'OpenSSL is not enabled on this server. Ask your hosting provider to enable the PHP OpenSSL extension.', 'tempmail-pro' ),
                'openssl_missing' => true,
            ] );
        }

        $user_id   = get_current_user_id();
        $domain_id = (int) ( $_POST['domain_id'] ?? 0 );
        if ( ! $domain_id ) {
            wp_send_json_error( [ 'message' => __( 'Invalid domain.', 'tempmail-pro' ) ] );
        }

        global $wpdb;
        $row = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}tmpmp_user_domains WHERE id = %d AND user_id = %d",
            $domain_id, $user_id
        ) );
        if ( ! $row ) {
            wp_send_json_error( [ 'message' => __( 'Domain not found.', 'tempmail-pro' ) ] );
        }

        error_log( '[TmpmpDKIM] Generating DKIM key for domain_id=' . $domain_id . ' user=' . $user_id );

        // Seed the OpenSSL PRNG with strong random bytes — dramatically speeds
        // up openssl_pkey_new() on low-entropy Windows systems.
        if ( function_exists( 'openssl_random_pseudo_bytes' ) ) {
            $seed = openssl_random_pseudo_bytes( 64 );
            if ( function_exists( 'openssl_digest' ) ) {
                openssl_digest( $seed, 'sha256' ); // forces PRNG to absorb bytes
            }
        }

        $dkim = TempMail_UserDomains::generate_dkim_keypair();

        error_log( '[TmpmpDKIM] generate_dkim_keypair result: private=' .
            ( empty( $dkim['private'] ) ? 'EMPTY' : 'OK(' . strlen( $dkim['private'] ) . 'chars)' ) .
            ' public=' . ( empty( $dkim['public_dns'] ) ? 'EMPTY' : 'OK(' . strlen( $dkim['public_dns'] ) . 'chars)' )
        );

        if ( empty( $dkim['private'] ) ) {
            // Collect OpenSSL errors for the log
            $errs = [];
            while ( $e = openssl_error_string() ) { $errs[] = $e; }
            error_log( '[TmpmpDKIM] OpenSSL errors: ' . implode( ' | ', $errs ) );
            wp_send_json_error( [
                'message' => __( 'Key generation failed. OpenSSL errors: ', 'tempmail-pro' ) . ( $errs ? implode( '; ', $errs ) : __( 'unknown', 'tempmail-pro' ) ),
            ] );
        }

        $wpdb->update(
            $wpdb->prefix . 'tmpmp_user_domains',
            [ 'dkim_private_key' => $dkim['private'], 'dkim_public_key' => $dkim['public_dns'] ],
            [ 'id' => $domain_id ],
            [ '%s', '%s' ],
            [ '%d' ]
        );

        error_log( '[TmpmpDKIM] Key saved to DB. wpdb_error: ' . ( $wpdb->last_error ?: 'none' ) );

        $selector = $row->dkim_selector ?: 'tmpro';
        wp_send_json_success( [
            'dkim_value'      => "v=DKIM1; k=rsa; p={$dkim['public_dns']}",
            'dkim_public_key' => $dkim['public_dns'],
            'selector'        => $selector,
            'message'         => __( 'DKIM key generated! Copy the value and add it as a TXT record in your DNS.', 'tempmail-pro' ),
        ] );
    }


    // ── Custom Domain: Delete ─────────────────────────────────────────────────────
    public function ajax_delete_custom_domain() : void {
        error_log( '[TmpmpDeleteDomain] AJAX handler fired. POST=' . json_encode( $_POST ) );
        $nonce_ok = check_ajax_referer( 'tempmail_pro_nonce', 'nonce', false );
        error_log( '[TmpmpDeleteDomain] nonce_ok=' . var_export( $nonce_ok, true ) );
        if ( ! $nonce_ok ) {
            error_log( '[TmpmpDeleteDomain] Nonce FAILED — dying with -1' );
            wp_die( '-1', '', [ 'response' => 403 ] );
        }
        if ( ! is_user_logged_in() ) {
            error_log( '[TmpmpDeleteDomain] Not logged in' );
            wp_send_json_error( [ 'message' => __( 'You must be logged in.', 'tempmail-pro' ) ] );
        }
        $user_id   = get_current_user_id();
        $domain_id = (int) ( $_POST['domain_id'] ?? 0 );
        error_log( '[TmpmpDeleteDomain] user_id=' . $user_id . ' domain_id=' . $domain_id );
        if ( ! $domain_id ) {
            error_log( '[TmpmpDeleteDomain] domain_id is 0 — invalid' );
            wp_send_json_error( [ 'message' => __( 'Invalid domain.', 'tempmail-pro' ) ] );
        }
        $ok = TempMail_UserDomains::delete( $domain_id, $user_id );
        error_log( '[TmpmpDeleteDomain] delete() returned: ' . var_export( $ok, true ) );
        if ( ! $ok ) {
            wp_send_json_error( [ 'message' => __( 'Could not delete domain. It may not exist or you do not own it.', 'tempmail-pro' ) ] );
        }
        error_log( '[TmpmpDeleteDomain] SUCCESS — domain ' . $domain_id . ' deleted for user ' . $user_id );
        wp_send_json_success( [ 'message' => __( 'Domain removed successfully.', 'tempmail-pro' ) ] );
    }


    // ── Shortcodes ────────────────────────────────────────────────────────────
    public function render_login_page( array $atts ) : string {
        if ( is_user_logged_in() ) {
            wp_safe_redirect( self::dashboard_url() );
            exit;
        }
        ob_start();
        include TMPMP_PLUGIN_DIR . 'public/views/login-page.php';
        return ob_get_clean();
    }

    public function render_user_dashboard( array $atts ) : string {
        if ( ! is_user_logged_in() ) {
            ob_start();
            echo '<script>window.location="' . esc_url( self::login_url() ) . '";</script>';
            return ob_get_clean();
        }
        ob_start();
        include TMPMP_PLUGIN_DIR . 'public/views/user-dashboard.php';
        return ob_get_clean();
    }
}
