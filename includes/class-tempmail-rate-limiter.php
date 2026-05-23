<?php
/**
 * TempMail Pro — Rate Limiter
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class TempMail_Rate_Limiter {

    private static ?self $instance = null;
    public static function instance() : self { return self::$instance ??= new self(); }

    public static function check( string $ip, string $action, int $user_id = 0 ) : bool|WP_Error {
        global $wpdb;
        $settings = get_option( 'tmpmp_settings', [] );
        $limit    = intval( $settings['rate_limit'] ?? 10 );
        $window   = intval( $settings['rate_window'] ?? 24 );

        $count = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}tmpmp_ratelimit
             WHERE ip_address = %s AND action = %s
               AND created_at > DATE_SUB(UTC_TIMESTAMP(), INTERVAL %d HOUR)",
            $ip, $action, $window
        ) );

        if ( $count >= $limit ) {
            return new WP_Error( 'rate_limited',
                sprintf( __( 'Rate limit reached. Max %d per %d hours.', 'tempmail-pro' ), $limit, $window )
            );
        }
        return true;
    }

    public static function log( string $ip, string $action, int $user_id = 0 ) : void {
        global $wpdb;
        $wpdb->insert( $wpdb->prefix . 'tmpmp_ratelimit', [
            'ip_address' => $ip,
            'action'     => $action,
            'user_id'    => $user_id,
            'created_at' => gmdate( 'Y-m-d H:i:s' ),
        ] );
    }

    public static function get_client_ip() : string {
        $keys = [
            'HTTP_CF_CONNECTING_IP',
            'HTTP_X_REAL_IP',
            'HTTP_X_FORWARDED_FOR',
            'REMOTE_ADDR',
        ];
        foreach ( $keys as $key ) {
            if ( ! empty( $_SERVER[ $key ] ) ) {
                $ip = trim( explode( ',', sanitize_text_field( wp_unslash( $_SERVER[ $key ] ) ) )[0] );
                if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) return $ip;
            }
        }
        return '0.0.0.0';
    }
}
