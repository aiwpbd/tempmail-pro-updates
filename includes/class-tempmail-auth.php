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

        $token = bin2hex(random_bytes(20));
        set_transient( 'tmpmp_magic_' . $token, $user->ID, 15 * MINUTE_IN_SECONDS );

        $link    = add_query_arg(['tmpmp_magic' => $token], home_url('/'));
        $subject = __('Your TempMail Pro login link', 'tempmail-pro');
        $message = sprintf(
            /* translators: %1$s: site name, %2$s: login link */
            __("Hello!\n\nClick the link below to log in to %1\$s:\n%2\$s\n\nThis link expires in 15 minutes and can only be used once.\n\nIf you did not request this, please ignore this email.", 'tempmail-pro'),
            get_bloginfo('name'),
            $link
        );

        wp_mail( $email, $subject, $message );
        wp_send_json_success(['message' => __('Magic link sent! Check your inbox.','tempmail-pro')]);
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
