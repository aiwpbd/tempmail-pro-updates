<?php
/**
 * Plugin Name: TempMail Pro
 * Plugin URI:  https://wa.me/+8801516514216
 * Description: A full-featured temporary/disposable email SaaS platform for WordPress â€” with subscriptions, multi-domain, API, and monetization.
 * Version:     2.0.5
 * Author:      TempMail Pro
 * Author URI:  https://wa.me/+8801516514216
 * License:     GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: tempmail-pro
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 8.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// â”€â”€ Constants â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
define( 'TMPMP_VERSION',     '2.0.5' );
define( 'TMPMP_PLUGIN_FILE', __FILE__ );
define( 'TMPMP_PLUGIN_DIR',  plugin_dir_path( __FILE__ ) );
define( 'TMPMP_PLUGIN_URL',  plugin_dir_url( __FILE__ ) );
define( 'TMPMP_PLUGIN_BASE', plugin_basename( __FILE__ ) );

// â”€â”€ Autoloader â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
spl_autoload_register( function ( $class ) {
    $prefix = 'TempMail_';
    if ( strpos( $class, $prefix ) !== 0 ) return;

    $map = [
        'TempMail_Database'         => 'includes/class-tempmail-database.php',
        'TempMail_Email_Generator'  => 'includes/class-tempmail-email-generator.php',
        'TempMail_Inbox'            => 'includes/class-tempmail-inbox.php',
        'TempMail_AJAX'             => 'includes/class-tempmail-ajax.php',
        'TempMail_REST_API'         => 'includes/class-tempmail-rest-api.php',
        'TempMail_Shortcode'        => 'includes/class-tempmail-shortcode.php',
        'TempMail_Cron'             => 'includes/class-tempmail-cron.php',
        'TempMail_Rate_Limiter'     => 'includes/class-tempmail-rate-limiter.php',
        'TempMail_IMAP'             => 'includes/class-tempmail-imap.php',
        'TempMail_Subscription'     => 'includes/class-tempmail-subscription.php',
        'TempMail_Payments'         => 'includes/class-tempmail-payments.php',
        'TempMail_Domains'          => 'includes/class-tempmail-domains.php',
        'TempMail_Ads'              => 'includes/class-tempmail-ads.php',
        'TempMail_Auth'             => 'includes/class-tempmail-auth.php',
        'TempMail_API_Keys'         => 'includes/class-tempmail-api-keys.php',
        'TempMail_Security'         => 'includes/class-tempmail-security.php',
        'TempMail_Changelog'        => 'includes/class-tempmail-changelog.php',
        'TempMail_Admin'            => 'admin/class-tempmail-admin.php',
        'TempMail_Admin_Domains'    => 'admin/class-tempmail-admin-domains.php',
        'TempMail_Admin_Plans'      => 'admin/class-tempmail-admin-plans.php',
        'TempMail_Admin_Users'      => 'admin/class-tempmail-admin-users.php',
        'TempMail_Admin_Payments'   => 'admin/class-tempmail-admin-payments.php',
        'TempMail_Admin_Ads'        => 'admin/class-tempmail-admin-ads.php',
        'TempMail_Admin_Analytics'  => 'admin/class-tempmail-admin-analytics.php',
        'TempMail_Admin_Export'     => 'admin/class-tempmail-admin-export.php',
        'TempMail_Gutenberg'        => 'includes/class-tempmail-gutenberg.php',
        'TempMail_Setup'            => 'includes/class-tempmail-setup.php',
        'TempMail_Visitors'         => 'includes/class-tempmail-visitors.php',
        'TempMail_Updater'          => 'includes/class-tempmail-updater.php',
        'TempMail_GitHub_Updater'   => 'includes/class-github-updater.php',
        'TempMail_Design'           => 'includes/class-tempmail-design.php',
        'TempMail_FAQ'              => 'includes/class-tempmail-faq.php',
    ];

    if ( isset( $map[ $class ] ) ) {
        $file = TMPMP_PLUGIN_DIR . $map[ $class ];
        if ( file_exists( $file ) ) require_once $file;
    }
} );

// â”€â”€ Activation / Deactivation / Uninstall â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
register_activation_hook(   __FILE__, [ 'TempMail_Database', 'install' ] );
register_activation_hook(   __FILE__, [ 'TempMail_Cron',     'schedule_events' ] );
register_activation_hook(   __FILE__, [ 'TempMail_Setup',    'create_pages' ] );
register_deactivation_hook( __FILE__, [ 'TempMail_Cron',     'clear_events' ] );

// â”€â”€ Schema upgrade on version change (runs on every request type) â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
add_action( 'plugins_loaded', function () {
    if ( get_option( 'tmpmp_db_version' ) !== TMPMP_VERSION ) {
        TempMail_Database::install();
        update_option( 'tmpmp_db_version', TMPMP_VERSION );
    }
}, 1 ); // priority 1 = before tmpmp_init at priority 10

// â”€â”€ Plugin Update Checkers (run early, before plugins_loaded) â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
// Registered here so pre_set_site_transient_update_plugins fires at the right time.
new TempMail_Updater( __FILE__ );        // JSON-based fallback updater
new TempMail_GitHub_Updater( __FILE__ ); // GitHub Releases API + notifications.json

// â”€â”€ Boot â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
add_action( 'plugins_loaded', 'tmpmp_init', 10 );

function tmpmp_init() {
    load_plugin_textdomain( 'tempmail-pro', false, dirname( TMPMP_PLUGIN_BASE ) . '/languages' );

    // Core systems
    TempMail_Security::instance();
    TempMail_Rate_Limiter::instance();
    TempMail_Domains::instance();
    TempMail_Subscription::instance();
    TempMail_Payments::instance();
    TempMail_Auth::instance();
    TempMail_API_Keys::instance();
    TempMail_Ads::instance();
    TempMail_AJAX::instance();
    TempMail_REST_API::instance();
    TempMail_Shortcode::instance();
    TempMail_Cron::instance();
    TempMail_Changelog::instance();
    TempMail_Design::instance();
    TempMail_FAQ::instance();
    TempMail_Gutenberg::instance();

    // Visitor tracker (front-end only, non-admin, non-AJAX)
    TempMail_Visitors::init();

    // Admin systems
    if ( is_admin() ) {
        TempMail_Admin::instance();
        TempMail_Admin_Domains::instance();
        TempMail_Admin_Plans::instance();
        TempMail_Admin_Users::instance();
        TempMail_Admin_Payments::instance();
        TempMail_Admin_Ads::instance();
        TempMail_Admin_Analytics::instance();
        TempMail_Admin_Export::instance();
    }
}

// â”€â”€ Frontend assets â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
add_action( 'wp_enqueue_scripts', 'tmpmp_enqueue_frontend' );
function tmpmp_enqueue_frontend() {
    wp_enqueue_style(
        'tempmail-pro-public',
        TMPMP_PLUGIN_URL . 'assets/css/tempmail-app.css',
        [],
        filemtime( TMPMP_PLUGIN_DIR . 'assets/css/tempmail-app.css' )
    );
    // QR code library (client-side, no external API)
    wp_register_script(
        'qrcodejs',
        'https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js',
        [],
        '1.0.0',
        true
    );
    wp_enqueue_script(
        'tempmail-pro-public',
        TMPMP_PLUGIN_URL . 'assets/js/tempmail-app.js',
        [ 'jquery', 'qrcodejs' ],
        TMPMP_VERSION,
        true
    );

    $settings = get_option( 'tmpmp_settings', [] );
    wp_localize_script( 'tempmail-pro-public', 'TempMailPro', [
        'ajax_url'         => admin_url( 'admin-ajax.php' ),
        'rest_url'         => esc_url_raw( rest_url( 'tempmail-pro/v1' ) ),
        'nonce'            => wp_create_nonce( 'tempmail_pro_nonce' ),
        'rest_nonce'       => wp_create_nonce( 'wp_rest' ),
        'refresh_interval' => intval( $settings['refresh_interval'] ?? 10 ) * 1000,
        'mail_protocol'    => $settings['mail_protocol'] ?? 'webhook',
        'bg_poll_interval' => 60000,
        'version'          => TMPMP_VERSION,
        'is_premium'       => TempMail_Subscription::is_premium_user() ? 1 : 0,
        'user_plan'        => TempMail_Subscription::get_user_plan(),
        'upgrade_url'      => esc_url( $settings['upgrade_url'] ?? '' ),
        'strings'          => [
            'copy_success'   => __( 'Copied!', 'tempmail-pro' ),
            'copy_fail'      => __( 'Copy failed', 'tempmail-pro' ),
            'generating'     => __( 'Generating...', 'tempmail-pro' ),
            'no_emails'      => __( 'No emails yet. Waiting...', 'tempmail-pro' ),
            'email_expired'  => __( 'This inbox has expired.', 'tempmail-pro' ),
            'error_generic'  => __( 'Something went wrong.', 'tempmail-pro' ),
        ],
    ] );
}
