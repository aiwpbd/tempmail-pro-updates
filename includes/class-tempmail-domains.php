<?php
/**
 * TempMail Pro — Domains manager
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class TempMail_Domains {

    private static ?self $instance = null;
    public static function instance() : self { return self::$instance ??= new self(); }

    private function __construct() {}

    /** Returns domains accessible for a given plan slug */
    public static function get_for_plan( string $plan_slug ) : array {
        $plan = TempMail_Database::get_plan_by_slug( $plan_slug );
        $cats = $plan ? json_decode( $plan->domains_allowed ?? '["free"]', true ) : ['free'];
        return TempMail_Database::get_all_domains() ? array_filter(
            TempMail_Database::get_all_domains(),
            fn($d) => in_array( $d->category, $cats, true )
        ) : [];
    }

    public static function get_all() : array {
        return TempMail_Database::get_all_domains();
    }

    public static function add( string $domain, string $category = 'free', string $desc = '' ) : mixed {
        global $wpdb;
        if ( ! preg_match( '/^[a-z0-9.-]+\.[a-z]{2,}$/i', $domain ) ) {
            return new WP_Error( 'invalid_domain', __( 'Invalid domain format.', 'tempmail-pro' ) );
        }
        $wpdb->insert( $wpdb->prefix . 'tmpmp_domains', [
            'domain'      => strtolower( $domain ),
            'category'    => $category,
            'description' => $desc,
            'is_active'   => 1,
            'created_at'  => gmdate('Y-m-d H:i:s'),
        ] );
        return (int) $wpdb->insert_id;
    }

    public static function update( int $id, array $data ) : bool {
        global $wpdb;
        return (bool) $wpdb->update(
            $wpdb->prefix . 'tmpmp_domains',
            $data,
            ['id' => $id]
        );
    }

    public static function delete( int $id ) : bool {
        global $wpdb;
        return (bool) $wpdb->delete( $wpdb->prefix . 'tmpmp_domains', ['id' => $id] );
    }
}
