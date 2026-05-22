<?php
/**
 * TempMail Pro — Main Admin Controller
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class TempMail_Admin {

    private static ?self $instance = null;
    public static function instance() : self { return self::$instance ??= new self(); }

    private function __construct() {
        add_action( 'admin_menu',            [ $this, 'register_menus'   ] );
        add_action( 'admin_head',             [ $this, 'menu_icon_color'  ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets'   ] );
        add_action( 'admin_notices',         [ 'TempMail_Changelog', 'render_banner' ] );
        add_action( 'wp_ajax_tmpmp_save_settings',          [ $this, 'ajax_save_settings'         ] );
        add_action( 'wp_ajax_tmpmp_inject_test_email',       [ $this, 'ajax_inject_test'           ] );
        add_action( 'wp_ajax_tmpmp_purge_now',               [ $this, 'ajax_purge_now'             ] );
        add_action( 'wp_ajax_tmpmp_poll_imap',               [ $this, 'ajax_poll_imap'             ] );
        add_action( 'wp_ajax_tmpmp_regen_token',             [ $this, 'ajax_regen_token'           ] );
        add_action( 'wp_ajax_tmpmp_test_imap_connection',    [ $this, 'ajax_test_imap_connection'  ] );
        add_action( 'wp_ajax_tmpmp_eg_preview',              [ $this, 'ajax_eg_preview'            ] );
        add_action( 'wp_ajax_tmpmp_recreate_pages',           [ $this, 'ajax_recreate_pages'        ] );
        add_action( 'wp_ajax_tmpmp_test_cron',                 [ $this, 'ajax_test_cron'             ] );
    }

    // ── Menus ─────────────────────────────────────────────────────────────────
    public function register_menus() : void {
        add_menu_page(
            __('TempMail Pro','tempmail-pro'),
            'TempMail Pro',
            'manage_options',
            'tempmail-pro',
            [ $this, 'render_dashboard' ],
            'data:image/svg+xml;base64,' . base64_encode( '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path fill="#6366f1" d="M20 4H4C2.9 4 2 4.9 2 6v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 2-8 5.01L4 6h16zm0 12H4V8l8 5 8-5v10z"/><path fill="#818cf8" d="M13.5 13.5 16 11l1.5 1.5-4 4-4-4L11 11l2.5 2.5z"/></svg>' ),
            30
        );
        add_submenu_page('tempmail-pro', __('Setup Guide','tempmail-pro'),   __('🚀 Setup Guide','tempmail-pro'),   'manage_options', 'tmpmp-guide',   [$this,'render_guide']);
        add_submenu_page('tempmail-pro', __('Dashboard','tempmail-pro'),   __('📊 Dashboard','tempmail-pro'),   'manage_options', 'tempmail-pro',           [$this,'render_dashboard']);
        add_submenu_page('tempmail-pro', __('Domains','tempmail-pro'),     __('🌐 Domains','tempmail-pro'),     'manage_options', 'tmpmp-domains',           ['TempMail_Admin_Domains','render']);
        add_submenu_page('tempmail-pro', __('Plans','tempmail-pro'),       __('💎 Plans','tempmail-pro'),       'manage_options', 'tmpmp-plans',             ['TempMail_Admin_Plans','render']);
        add_submenu_page('tempmail-pro', __('Users','tempmail-pro'),       __('👥 Users','tempmail-pro'),       'manage_options', 'tmpmp-users',             ['TempMail_Admin_Users','render']);
        add_submenu_page('tempmail-pro', __('Payments','tempmail-pro'),    __('💳 Payments','tempmail-pro'),    'manage_options', 'tmpmp-payments',          ['TempMail_Admin_Payments','render']);
        add_submenu_page('tempmail-pro', __('Ads','tempmail-pro'),         __('📢 Ads','tempmail-pro'),         'manage_options', 'tmpmp-ads',               ['TempMail_Admin_Ads','render']);
        add_submenu_page('tempmail-pro', __('Analytics','tempmail-pro'),   __('📈 Analytics','tempmail-pro'),   'manage_options', 'tmpmp-analytics',         ['TempMail_Admin_Analytics','render']);
        add_submenu_page('tempmail-pro', __('Visitors','tempmail-pro'),    __('👁️ Visitors','tempmail-pro'),    'manage_options', 'tmpmp-visitors',           [$this,'render_visitors']);
        add_submenu_page('tempmail-pro', __('Pages','tempmail-pro'),       __('📄 Pages','tempmail-pro'),       'manage_options', 'tmpmp-pages',             [$this,'render_pages']);
        add_submenu_page('tempmail-pro', __('Settings','tempmail-pro'),    __('⚙️ Settings','tempmail-pro'),    'manage_options', 'tmpmp-settings',          [$this,'render_settings']);
        add_submenu_page('tempmail-pro', __('Changelog','tempmail-pro'),   __('🆕 Changelog','tempmail-pro'),   'manage_options', 'tmpmp-changelog',         [$this,'render_changelog']);
    }

    // ── Admin icon color CSS ──────────────────────────────────────────────────
    public function menu_icon_color() : void {
        ?>
        <style>
        /* Force TempMail Pro brand color on the sidebar icon */
        #toplevel_page_tempmail-pro .wp-menu-image img {
            opacity: 1 !important;
            filter: none !important;
        }
        #toplevel_page_tempmail-pro:hover .wp-menu-image img,
        #toplevel_page_tempmail-pro.wp-has-current-submenu .wp-menu-image img,
        #toplevel_page_tempmail-pro.current .wp-menu-image img {
            opacity: 1 !important;
            filter: brightness(0) invert(1) sepia(1) saturate(4) hue-rotate(210deg) !important;
        }
        </style>
        <?php
    }

    // ── Asset enqueue ─────────────────────────────────────────────────────────
    public function enqueue_assets( string $hook ) : void {
        if ( strpos($hook, 'tempmail') === false && strpos($hook, 'tmpmp') === false ) return;
        wp_enqueue_style('tempmail-pro-admin', TMPMP_PLUGIN_URL . 'assets/css/tempmail-admin.css', [], TMPMP_VERSION);
        wp_enqueue_script('tempmail-pro-admin', TMPMP_PLUGIN_URL . 'assets/js/tempmail-admin.js', ['jquery','wp-util'], TMPMP_VERSION, true);
        wp_localize_script('tempmail-pro-admin','TempMailAdmin',[
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('tempmail_pro_nonce'),
            'rest_url' => esc_url_raw(rest_url('tempmail-pro/v1')),
            'version'  => TMPMP_VERSION,
        ]);
        // Chart.js only on analytics page
        if ( strpos($hook,'tmpmp-analytics') !== false ) {
            wp_enqueue_script('chartjs', 'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js', [], '4.4.0', true);
        }
        // WP Media Library for branding image pickers on settings page
        if ( strpos($hook,'tmpmp-settings') !== false ) {
            wp_enqueue_media();
        }
    }

    // ── Page renderers ────────────────────────────────────────────────────────
    public function render_dashboard() : void {
        $stats    = TempMail_Database::get_stats();
        $settings = get_option('tmpmp_settings',[]);
        include TMPMP_PLUGIN_DIR . 'admin/views/dashboard-page.php';
    }

    public function render_settings() : void {
        $settings = get_option('tmpmp_settings', TempMail_Database::default_settings());
        $domains  = TempMail_Database::get_all_domains();
        include TMPMP_PLUGIN_DIR . 'admin/views/settings-page.php';
    }

    public function render_guide() : void {
        $settings = get_option('tmpmp_settings', []);
        $plans    = TempMail_Database::get_all_plans( false );
        $domains  = TempMail_Database::get_all_domains();
        include TMPMP_PLUGIN_DIR . 'admin/views/guide-page.php';
    }

    // ── AJAX: Test Server Cron ────────────────────────────────────────────────
    public function ajax_test_cron() : void {
        check_ajax_referer( 'tempmail_pro_nonce', 'nonce' );
        if ( ! current_user_can('manage_options') ) wp_send_json_error( ['message' => 'Unauthorized.'], 403 );

        $settings = get_option('tmpmp_settings', []);
        $token    = $settings['server_cron_token'] ?? '';
        $endpoint = rest_url('tempmail-pro/v1/server-cron');
        $start    = microtime(true);

        $response = wp_remote_post( add_query_arg('token', $token, $endpoint), [
            'timeout'   => 30,
            'sslverify' => false,
            'headers'   => ['Content-Type' => 'application/json'],
        ] );

        $duration_ms = (int) round( (microtime(true) - $start) * 1000 );

        if ( is_wp_error($response) ) {
            wp_send_json_error( ['message' => $response->get_error_message()] );
        }

        $body = json_decode( wp_remote_retrieve_body($response), true );
        $result = [
            'time'        => current_time('Y-m-d H:i:s'),
            'fetched'     => intval( $body['fetched']  ?? 0 ),
            'stored'      => intval( $body['stored']   ?? 0 ),
            'purged'      => intval( $body['purged']   ?? 0 ),
            'duration_ms' => $duration_ms,
        ];

        update_option( 'tmpmp_last_cron_result', $result );
        wp_send_json_success( $result );
    }

    public function render_changelog() : void {
        $log = TempMail_Changelog::get_changelog();
        include TMPMP_PLUGIN_DIR . 'admin/views/changelog-page.php';
    }

    public function render_pages() : void {
        $pages = TempMail_Setup::get_page_info();
        include TMPMP_PLUGIN_DIR . 'admin/views/pages-page.php';
    }

    public function render_visitors() : void {
        include TMPMP_PLUGIN_DIR . 'admin/views/visitors-page.php';
    }

    // ── AJAX: Recreate pages ──────────────────────────────────────────────────
    public function ajax_recreate_pages() : void {
        check_ajax_referer( 'tempmail_pro_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( ['message' => 'Unauthorized.'], 403 );
        TempMail_Setup::create_pages();
        wp_send_json_success( [ 'pages' => TempMail_Setup::get_page_info() ] );
    }

    // ── AJAX: Save settings ───────────────────────────────────────────────────
    public function ajax_save_settings() : void {
        check_ajax_referer('tempmail_pro_nonce','nonce');
        if ( ! current_user_can('manage_options') ) wp_send_json_error(['message'=>'Unauthorized.'],403);

        $current  = get_option('tmpmp_settings', TempMail_Database::default_settings());
        $new_data = [];

        // Text fields
        $text_fields = ['mail_protocol','imap_host','imap_user','imap_protocol',
                        'webhook_secret','server_cron_token','stripe_pk','paypal_client_id',
                        'ssl_store_id','google_client_id','google_client_secret',
                        'facebook_app_id','facebook_app_secret',
                        'captcha_provider','captcha_site_key','captcha_secret_key',
                        'adsense_code','inbox_emoji','eg_format','eg_separator','eg_num_suffix','eg_char_set',
                        'pricing_eyebrow','pricing_heading','pricing_subtext','pricing_yearly_save',
                        'pricing_label_monthly','pricing_label_yearly'];
        foreach ( $text_fields as $k ) {
            if ( isset($_POST[$k]) ) $new_data[$k] = sanitize_text_field(wp_unslash($_POST[$k]));
        }

        // Textarea fields
        if ( isset($_POST['spam_keywords']) ) {
            $new_data['spam_keywords'] = sanitize_textarea_field(wp_unslash($_POST['spam_keywords']));
        }
        foreach ( ['eg_adj_list','eg_noun_list'] as $k ) {
            if ( isset($_POST[$k]) ) {
                $raw   = sanitize_textarea_field(wp_unslash($_POST[$k]));
                $lines = array_values(array_filter(array_map('sanitize_key', preg_split('/\r?\n/', $raw))));
                $new_data[$k] = implode("\n", array_slice($lines, 0, 200));
            }
        }
        foreach ( ['eg_num_min','eg_num_max','eg_char_length'] as $k ) {
            if ( isset($_POST[$k]) ) $new_data[$k] = intval($_POST[$k]);
        }
        foreach ( ['free_prefix','free_suffix','premium_prefix','premium_suffix','vip_prefix','vip_suffix'] as $k ) {
            if ( isset($_POST[$k]) ) {
                $new_data[$k] = preg_replace('/[^a-zA-Z0-9_\-]/', '', substr(sanitize_text_field(wp_unslash($_POST[$k])), 0, 20));
            }
        }
        foreach ( ['upgrade_url','inbox_logo_url','empty_inbox_img_url'] as $k ) {
            if ( isset($_POST[$k]) ) $new_data[$k] = esc_url_raw(wp_unslash($_POST[$k]));
        }
        foreach ( ['inbox_logo_id','empty_inbox_img_id'] as $k ) {
            if ( isset($_POST[$k]) ) $new_data[$k] = absint($_POST[$k]);
        }
        if ( isset($_POST['avatar_style']) ) $new_data['avatar_style'] = sanitize_key(wp_unslash($_POST['avatar_style']));

        // Design / Appearance fields
        if ( isset($_POST['design_theme']) && in_array($_POST['design_theme'], ['dark','light','auto'], true) ) {
            $new_data['design_theme'] = $_POST['design_theme'];
        }
        if ( isset($_POST['design_accent']) && preg_match('/^#[0-9a-fA-F]{6}$/', $_POST['design_accent']) ) {
            $new_data['design_accent'] = $_POST['design_accent'];
        }
        if ( isset($_POST['design_font']) ) {
            $new_data['design_font'] = sanitize_text_field(wp_unslash($_POST['design_font']));
        }
        if ( isset($_POST['design_radius']) ) {
            $new_data['design_radius'] = (string) max(0, min(28, intval($_POST['design_radius'])));
        }
        if ( isset($_POST['design_max_width']) ) {
            $new_data['design_max_width'] = (string) max(480, min(1200, intval($_POST['design_max_width'])));
        }
        if ( isset($_POST['design_custom_css']) ) {
            $new_data['design_custom_css'] = sanitize_textarea_field(wp_unslash($_POST['design_custom_css']));
        }

        $int_fields = ['refresh_interval','imap_port','rate_limit','rate_window','spam_filter',
                       'stripe_enabled','paypal_enabled','ssl_live','google_login','facebook_login','enable_captcha',
                       'wc_enabled','custom_api_enabled'];
        foreach ( $int_fields as $k ) {
            $new_data[$k] = intval($_POST[$k] ?? $current[$k] ?? 0);
        }

        $secret_fields = ['imap_pass','webhook_secret','server_cron_token','stripe_sk','stripe_webhook_secret',
                          'paypal_secret','ssl_store_pass','custom_api_key','custom_api_webhook_secret'];
        foreach ( $secret_fields as $k ) {
            if ( ! empty($_POST[$k]) ) {
                $new_data[$k] = sanitize_text_field(wp_unslash($_POST[$k]));
            } else {
                $new_data[$k] = $current[$k] ?? '';
            }
        }

        if ( isset($_POST['custom_api_endpoint']) ) {
            $new_data['custom_api_endpoint'] = esc_url_raw( wp_unslash( $_POST['custom_api_endpoint'] ) );
        }

        update_option('tmpmp_settings', array_merge($current, $new_data));
        wp_send_json_success(['message' => __('Settings saved!','tempmail-pro')]);
    }

    // ── AJAX: Inject test email ───────────────────────────────────────────────
    public function ajax_inject_test() : void {
        check_ajax_referer('tempmail_pro_nonce','nonce');
        if ( ! current_user_can('manage_options') ) wp_send_json_error([],403);
        $address = sanitize_email($_POST['address'] ?? '');
        if ( ! $address ) wp_send_json_error(['message'=>'Address required.']);
        $result = TempMail_Inbox::receive_email([
            'to'        => $address,
            'from'      => 'test@example.com',
            'from_name' => 'TempMail Test',
            'subject'   => 'Test Email — ' . gmdate('H:i:s'),
            'body_text' => 'This is a test email sent from the TempMail Pro admin dashboard.',
            'body_html' => '<h2>Test Email</h2><p>Sent from <strong>TempMail Pro</strong> admin panel at ' . gmdate('Y-m-d H:i:s') . ' UTC.</p>',
        ]);
        is_wp_error($result)
            ? wp_send_json_error(['message'=>$result->get_error_message()])
            : wp_send_json_success(['message'=>'Test email injected.']);
    }

    // ── AJAX: Purge expired ───────────────────────────────────────────────────
    public function ajax_purge_now() : void {
        check_ajax_referer('tempmail_pro_nonce','nonce');
        if ( ! current_user_can('manage_options') ) wp_send_json_error([],403);
        $count = TempMail_Database::purge_expired();
        wp_send_json_success(['message' => sprintf(__('Purged %d expired records.','tempmail-pro'), $count)]);
    }

    // ── AJAX: Poll IMAP ───────────────────────────────────────────────────────
    public function ajax_poll_imap() : void {
        check_ajax_referer('tempmail_pro_nonce','nonce');
        if ( ! current_user_can('manage_options') ) wp_send_json_error([],403);
        $result = TempMail_IMAP::poll();
        wp_send_json_success($result);
    }

    // ── AJAX: Regenerate token ────────────────────────────────────────────────
    public function ajax_regen_token() : void {
        check_ajax_referer('tempmail_pro_nonce','nonce');
        if ( ! current_user_can('manage_options') ) wp_send_json_error(['message'=>'Invalid field.']);
        $field   = sanitize_key($_POST['field'] ?? '');
        $allowed = ['webhook_secret','server_cron_token'];
        if ( ! in_array($field, $allowed, true) ) wp_send_json_error(['message'=>'Invalid field.']);
        $token    = wp_generate_password(48, false);
        $settings = get_option('tmpmp_settings',[]);
        $settings[$field] = $token;
        update_option('tmpmp_settings', $settings);
        wp_send_json_success(['token' => $token]);
    }

    // ── AJAX: Test IMAP connection ────────────────────────────────────────────
    public function ajax_test_imap_connection() : void {
        check_ajax_referer('tempmail_pro_nonce','nonce');
        if ( ! current_user_can('manage_options') ) {
            wp_send_json_error(['message' => 'Unauthorized.'], 403);
            return;
        }

        $host     = sanitize_text_field(wp_unslash($_POST['imap_host'] ?? ''));
        $port     = intval($_POST['imap_port'] ?? 993);
        $user     = sanitize_text_field(wp_unslash($_POST['imap_user'] ?? ''));
        $pass     = sanitize_text_field(wp_unslash($_POST['imap_pass'] ?? ''));
        $protocol = sanitize_key($_POST['protocol'] ?? 'imap');

        if ( ! $host || ! $user || ! $pass ) {
            wp_send_json_error(['message' => __('Host, username and password are required.','tempmail-pro')]);
            return;
        }

        // Try php-imap extension first; fall back to raw socket if unavailable
        if ( function_exists('imap_open') ) {
            $this->test_imap_via_extension( $host, $port, $user, $pass, $protocol );
        } else {
            $this->test_imap_via_socket( $host, $port, $user, $pass );
        }
    }

    /**
     * Test using the native php-imap extension.
     */
    private function test_imap_via_extension( string $host, int $port, string $user, string $pass, string $protocol ) : void {
        $proto = ( $protocol === 'pop3' ) ? '/pop3' : '/imap';
        $flags = ( $port === 993 || $port === 995 ) ? '/ssl' : '/notls';
        $mbox  = '{' . $host . ':' . $port . $proto . $flags . '/novalidate-cert}INBOX';

        if ( function_exists('imap_timeout') ) {
            imap_timeout( IMAP_OPENTIMEOUT, 10 );
            imap_timeout( IMAP_READTIMEOUT, 10 );
            imap_timeout( IMAP_CLOSETIMEOUT, 5 );
        }
        @set_time_limit(30);

        try {
            ob_start();
            imap_errors();
            $conn   = @imap_open( $mbox, $user, $pass, 0, 1 );
            $errors = imap_errors();
            ob_end_clean();

            if ( $conn ) {
                $count = imap_num_msg($conn);
                imap_close($conn);
                wp_send_json_success([
                    'message' => sprintf(
                        __('Connection successful! %d message(s) in INBOX.','tempmail-pro'),
                        $count
                    ),
                ]);
            } else {
                $err = ! empty($errors)
                    ? implode(' | ', (array)$errors)
                    : __('Could not connect. Check host, port, credentials and SSL settings.','tempmail-pro');
                wp_send_json_error(['message' => $err]);
            }
        } catch ( \Throwable $e ) {
            wp_send_json_error(['message' => 'Exception: ' . $e->getMessage()]);
        }
    }

    /**
     * Test using raw TCP/SSL socket — works without php-imap extension.
     * Performs a real IMAP LOGIN handshake and reports success or the server's error.
     */
    private function test_imap_via_socket( string $host, int $port, string $user, string $pass ) : void {
        $use_ssl = ( $port === 993 || $port === 995 );
        $timeout = 10;

        $ctx = stream_context_create([
            'ssl' => [
                'verify_peer'      => false,
                'verify_peer_name' => false,
            ],
        ]);

        $uri  = ( $use_ssl ? 'ssl' : 'tcp' ) . "://{$host}:{$port}";
        $conn = @stream_socket_client( $uri, $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT, $ctx );

        if ( ! $conn ) {
            wp_send_json_error([
                'message' => sprintf(
                    __('Cannot reach %s:%d — %s. Check host and port.','tempmail-pro'),
                    $host, $port, $errstr ?: "error {$errno}"
                ),
            ]);
            return;
        }

        stream_set_timeout( $conn, $timeout );

        // Read server greeting (* OK ...)
        $greeting = fgets( $conn, 2048 );
        if ( ! $greeting || stripos( $greeting, '* OK' ) === false ) {
            fclose($conn);
            wp_send_json_error([
                'message' => __('Unexpected server greeting. This may not be an IMAP server.','tempmail-pro')
                             . ' ' . trim( (string) $greeting ),
            ]);
            return;
        }

        // Send LOGIN command
        $tag  = 'A001';
        $user_esc = addslashes($user);
        $pass_esc = addslashes($pass);
        fwrite( $conn, "{$tag} LOGIN \"{$user_esc}\" \"{$pass_esc}\"\r\n" );

        // Read response lines until we see our tag
        $response = '';
        $deadline = time() + $timeout;
        while ( ! feof($conn) && time() < $deadline ) {
            $line = fgets( $conn, 2048 );
            if ( $line === false ) break;
            $response .= $line;
            if ( stripos( $line, "{$tag} OK" ) !== false ) {
                // Logged in — now SELECT INBOX to count messages
                fwrite( $conn, "A002 SELECT INBOX\r\n" );
                $exists = 0;
                $sel_deadline = time() + 5;
                while ( ! feof($conn) && time() < $sel_deadline ) {
                    $sline = fgets( $conn, 2048 );
                    if ( $sline === false ) break;
                    if ( preg_match('/\* (\d+) EXISTS/', $sline, $m) ) {
                        $exists = (int) $m[1];
                    }
                    if ( stripos( $sline, 'A002' ) !== false ) break;
                }
                fwrite( $conn, "A003 LOGOUT\r\n" );
                fclose($conn);
                wp_send_json_success([
                    'message' => sprintf(
                        __('Connection successful! %d message(s) in INBOX.','tempmail-pro'),
                        $exists
                    ),
                ]);
                return;
            }
            if ( stripos( $line, "{$tag} NO" ) !== false || stripos( $line, "{$tag} BAD" ) !== false ) {
                fclose($conn);
                wp_send_json_error([
                    'message' => __('Authentication failed.','tempmail-pro') . ' ' . trim($line),
                ]);
                return;
            }
        }

        fclose($conn);
        wp_send_json_error([
            'message' => __('No valid response from IMAP server. Check credentials and SSL settings.','tempmail-pro'),
        ]);
    }


    // ── AJAX: Email Generation preview ───────────────────────────────────────
    public function ajax_eg_preview() : void {
        check_ajax_referer( 'tempmail_pro_nonce', 'nonce' );
        if ( ! current_user_can('manage_options') ) wp_send_json_error([], 403);

        $username = TempMail_Email_Generator::preview_username();

        $domains = TempMail_Database::get_all_domains();
        $domain  = 'example.com';
        foreach ( $domains as $d ) {
            if ( ! empty($d->is_active) ) { $domain = $d->domain; break; }
        }

        wp_send_json_success([ 'address' => strtolower( $username . '@' . $domain ) ]);
    }
}
