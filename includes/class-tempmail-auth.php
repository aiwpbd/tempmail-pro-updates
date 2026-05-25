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
        $loc  = $args->theme_location ?? '';
        $skip = apply_filters( 'tmpmp_nav_skip_locations', [] );
        if ( in_array( $loc, $skip, true ) ) return $items;

        $s = get_option( 'tmpmp_settings', [] );

        // ── Pricing link ──────────────────────────────────────────────────────
        $pricing_url   = $s['nav_pricing_url']   ?? home_url('/tempmail-pricing/');
        $pricing_label = $s['nav_pricing_label']  ?: __( 'Pricing', 'tempmail-pro' );
        if ( $pricing_url ) {
            $items .= '<li class="menu-item tmpmp-nav-page-item">'
                . '<a href="' . esc_url( $pricing_url ) . '" class="tmpmp-nav-page-link">'
                . '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/></svg>'
                . esc_html( $pricing_label )
                . '</a>'
                . '</li>';
        }

        // ── Custom links ──────────────────────────────────────────────────────
        $custom_links = $s['nav_custom_links'] ?? [];
        foreach ( (array) $custom_links as $lnk ) {
            $lbl = sanitize_text_field( $lnk['label'] ?? '' );
            $url = esc_url( $lnk['url'] ?? '' );
            if ( ! $lbl || ! $url ) continue;
            $items .= '<li class="menu-item tmpmp-nav-page-item">'
                . '<a href="' . $url . '" class="tmpmp-nav-page-link">' . esc_html( $lbl ) . '</a>'
                . '</li>';
        }

        if ( is_user_logged_in() ) {
            $user       = wp_get_current_user();
            $name       = esc_html( mb_strimwidth( $user->display_name ?: $user->user_email, 0, 18, "\u2026" ) );
            $avatar     = get_user_meta( $user->ID, 'tmpmp_avatar_url', true );
            $dash_url   = esc_url( self::dashboard_url() );
            $logout_url = esc_url( wp_logout_url( get_permalink() ?: home_url('/') ) );
            $initial    = esc_html( strtoupper( substr( $user->display_name ?: $user->user_email, 0, 1 ) ) );

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
                      . '<div class="tmpmp-nav-drop-head">'
                          . '<div class="tmpmp-nav-av tmpmp-nav-av--lg">' . $av . '</div>'
                          . '<span>' . $name . '</span>'
                      . '</div>'
                      . '<div class="tmpmp-nav-drop-divider"></div>'
                      . '<a href="' . $dash_url . '" class="tmpmp-nav-drop-item">'
                          . '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>'
                          . esc_html__( 'My Dashboard', 'tempmail-pro' )
                      . '</a>'
                      . '<a href="' . $logout_url . '" class="tmpmp-nav-drop-item tmpmp-nav-drop-item--logout">'
                          . '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>'
                          . esc_html__( 'Logout', 'tempmail-pro' )
                      . '</a>'
                  . '</div>'
                . '</li>';
        } else {
            $login_url = esc_url( self::login_url() );
            $items .= '<li class="menu-item tmpmp-nav-account-item">'
                . '<a href="' . $login_url . '" class="tmpmp-nav-login-btn">'
                . '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M15 3h4a2 2 0 012 2v14a2 2 0 01-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>'
                . esc_html__( 'Sign In / Register', 'tempmail-pro' )
                . '</a>'
                . '</li>';
        }
        return $items;
    }


    // ── Header Nav — CSS output (dropdown) ──────────────────────────────────
    public function output_nav_account_css() : void { ?>
<style id="tmpmp-nav-account-css">
/* TempMail Pro — header nav account dropdown */
.tmpmp-nav-account-item{position:relative!important;display:inline-flex!important;align-items:center;list-style:none!important;padding:0!important;margin:0!important;}
/* Trigger pill */
.tmpmp-nav-trigger{display:inline-flex;align-items:center;gap:8px;padding:5px 12px 5px 5px;border-radius:99px;background:linear-gradient(135deg,rgba(99,102,241,.14),rgba(139,92,246,.09));border:1.5px solid rgba(99,102,241,.32);cursor:pointer;transition:all .2s ease;user-select:none;}
.tmpmp-nav-account-item:hover .tmpmp-nav-trigger,.tmpmp-nav-account-item:focus-within .tmpmp-nav-trigger{background:linear-gradient(135deg,rgba(99,102,241,.24),rgba(139,92,246,.18));border-color:rgba(99,102,241,.6);box-shadow:0 4px 16px rgba(99,102,241,.22);}
/* Avatar circle */
.tmpmp-nav-av{width:28px;height:28px;border-radius:50%;overflow:hidden;display:flex;align-items:center;justify-content:center;background:linear-gradient(135deg,#6366f1,#8b5cf6);flex-shrink:0;border:2px solid rgba(255,255,255,.15);}
.tmpmp-nav-av--lg{width:34px;height:34px;}
.tmpmp-nav-av-img{width:100%;height:100%;object-fit:cover;display:block;}
.tmpmp-nav-av-init{font-size:12px;font-weight:800;color:#fff;line-height:1;}
/* Username */
.tmpmp-nav-uname{font-size:13px;font-weight:700;color:#818cf8;white-space:nowrap;max-width:130px;overflow:hidden;text-overflow:ellipsis;}
/* Chevron */
.tmpmp-nav-chevron{color:#818cf8;transition:transform .22s ease;flex-shrink:0;}
.tmpmp-nav-account-item:hover .tmpmp-nav-chevron,.tmpmp-nav-account-item:focus-within .tmpmp-nav-chevron{transform:rotate(180deg);}
/* Dropdown panel */
.tmpmp-nav-dropdown{position:absolute;top:calc(100% + 10px);right:0;min-width:210px;background:#1a1040;border:1.5px solid rgba(99,102,241,.28);border-radius:14px;box-shadow:0 12px 40px rgba(0,0,0,.5),0 0 0 1px rgba(99,102,241,.08);padding:8px;z-index:99999;opacity:0;visibility:hidden;transform:translateY(-8px) scale(.97);transition:opacity .2s ease,transform .22s ease,visibility .2s;pointer-events:none;}
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
/* Page links (Pricing / custom) */
.tmpmp-nav-page-item{display:inline-flex!important;align-items:center;list-style:none!important;padding:0!important;margin:0!important;}
.tmpmp-nav-page-link{display:inline-flex!important;align-items:center;gap:5px;padding:6px 12px;border-radius:8px;font-size:13px;font-weight:600;text-decoration:none!important;color:#818cf8!important;transition:all .18s ease;white-space:nowrap;border:1.5px solid transparent;}
.tmpmp-nav-page-link:hover{background:rgba(99,102,241,.12)!important;color:#fff!important;border-color:rgba(99,102,241,.28);transform:translateY(-1px);}
.tmpmp-nav-page-link svg{flex-shrink:0;opacity:.75;}
/* Sign In button */
.tmpmp-nav-login-btn{display:inline-flex!important;align-items:center;gap:7px;padding:7px 18px;border-radius:9px;font-size:13px;font-weight:700;text-decoration:none!important;background:linear-gradient(135deg,#6366f1,#8b5cf6)!important;color:#fff!important;box-shadow:0 2px 12px rgba(99,102,241,.35);transition:all .2s ease;white-space:nowrap;}
.tmpmp-nav-login-btn:hover{transform:translateY(-2px);box-shadow:0 6px 20px rgba(99,102,241,.55)!important;filter:brightness(1.08);color:#fff!important;}
/* Mobile */
@media(max-width:768px){.tmpmp-nav-uname{display:none;}.tmpmp-nav-dropdown{right:auto;left:0;}.tmpmp-nav-login-btn{padding:7px 12px;font-size:12px;}}
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
