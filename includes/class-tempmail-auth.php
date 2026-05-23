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
        // Magic link URL handler
        add_action( 'init', [ $this, 'handle_magic_link_login' ] );
        // Shortcodes
        add_shortcode( 'tempmail_login',     [ $this, 'render_login_page'     ] );
        add_shortcode( 'tempmail_dashboard', [ $this, 'render_user_dashboard' ] );
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
        wp_send_json_success(['redirect' => home_url('/dashboard/')]);
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
        wp_safe_redirect( home_url('/dashboard/') );
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

    // ── Shortcodes ────────────────────────────────────────────────────────────
    public function render_login_page( array $atts ) : string {
        if ( is_user_logged_in() ) {
            wp_safe_redirect( home_url('/dashboard/') );
            exit;
        }
        ob_start();
        include TMPMP_PLUGIN_DIR . 'public/views/login-page.php';
        return ob_get_clean();
    }

    public function render_user_dashboard( array $atts ) : string {
        if ( ! is_user_logged_in() ) {
            ob_start();
            echo '<script>window.location="' . esc_url(home_url('/login/')) . '";</script>';
            return ob_get_clean();
        }
        ob_start();
        include TMPMP_PLUGIN_DIR . 'public/views/user-dashboard.php';
        return ob_get_clean();
    }
}
