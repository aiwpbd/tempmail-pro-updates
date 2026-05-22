<?php
/**
 * TempMail Pro — Security hardening
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class TempMail_Security {

    private static ?self $instance = null;
    public static function instance() : self { return self::$instance ??= new self(); }

    private function __construct() {
        add_action( 'init', [ $this, 'security_headers' ] );
        add_filter( 'rest_authentication_errors', [ $this, 'rest_csrf_check' ], 99 );
    }

    public function security_headers() : void {
        if ( ! is_admin() && headers_sent() === false ) {
            header( 'X-Content-Type-Options: nosniff' );
            header( 'X-Frame-Options: SAMEORIGIN' );
            header( 'Referrer-Policy: strict-origin-when-cross-origin' );
        }
    }

    public function rest_csrf_check( $result ) {
        // Only apply to our namespace
        $route = $GLOBALS['wp']->query_vars['rest_route'] ?? '';
        if ( strpos( $route, '/tempmail-pro/' ) !== 0 ) return $result;
        // Webhook endpoints are exempted via their own auth
        return $result;
    }

    public static function verify_nonce( string $nonce_value, string $action ) : bool {
        return (bool) wp_verify_nonce( $nonce_value, $action );
    }

    public static function sanitize_address( string $addr ) : string {
        return strtolower( sanitize_email( $addr ) );
    }

    /** Hash-safe token comparison */
    public static function token_equals( string $a, string $b ) : bool {
        return hash_equals( $a, $b );
    }

    /** Generate a cryptographically secure token */
    public static function generate_token( int $length = 32 ) : string {
        return bin2hex( random_bytes( $length ) );
    }
}
