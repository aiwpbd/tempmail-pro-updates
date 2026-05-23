<?php
/**
 * TempMail Pro — Email Generator
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class TempMail_Email_Generator {

    /**
     * Generate a new temporary email address and store it.
     *
     * @param string $username  '' = random
     * @param string $domain    '' = pick from pool
     * @param string $session_id
     * @param string $ip
     * @param int    $user_id   0 = guest
     * @return array{address:string,session_id:string,expires_at:string,address_id:int}|WP_Error
     */
    public static function generate(
        string $username   = '',
        string $domain     = '',
        string $session_id = '',
        string $ip         = '',
        int    $user_id    = 0
    ) : array|WP_Error {

        // Rate check
        $limit_check = TempMail_Rate_Limiter::check( $ip, 'generate', $user_id );
        if ( is_wp_error( $limit_check ) ) return $limit_check;

        // IP blocked?
        if ( TempMail_Database::is_ip_blocked( $ip ) ) {
            return new WP_Error( 'blocked', __( 'Your IP is blocked.', 'tempmail-pro' ) );
        }

        // Resolve plan limits
        $plan       = TempMail_Subscription::get_user_plan_data( $user_id );
        $lifetime   = intval( $plan->inbox_lifetime ?? 30 );

        // Validate / pick domain
        $resolved_domain = self::resolve_domain( $domain, $plan );
        if ( is_wp_error( $resolved_domain ) ) return $resolved_domain;

        // Build username
        if ( ! $username ) {
            $username = self::random_username();
        } else {
            $username = sanitize_title( $username );
            if ( strlen( $username ) < 3 ) {
                return new WP_Error( 'short_user', __( 'Username too short.', 'tempmail-pro' ) );
            }
            // Premium-only custom username
            if ( ! TempMail_Subscription::is_premium_user( $user_id ) ) {
                $username = self::random_username(); // override for free users
            }
        }

        $address = strtolower( $username . '@' . $resolved_domain );

        // Uniqueness
        $existing = TempMail_Database::get_address( $address );
        if ( $existing ) {
            // Re-use if same session owns it and it's still active
            if ( $existing->session_id === $session_id && strtotime( $existing->expires_at . ' UTC' ) > time() ) {
                return [
                    'address'    => $existing->address,
                    'session_id' => $existing->session_id,
                    'expires_at' => $existing->expires_at,
                    'address_id' => (int) $existing->id,
                ];
            }
            // Otherwise make unique
            $username = self::random_username();
            $address  = strtolower( $username . '@' . $resolved_domain );
        }

        if ( ! $session_id ) $session_id = wp_generate_password( 32, false );

        $now        = gmdate( 'Y-m-d H:i:s' );
        $expires_at = gmdate( 'Y-m-d H:i:s', time() + $lifetime * 60 );

        $id = TempMail_Database::insert_address( [
            'address'    => $address,
            'session_id' => $session_id,
            'ip_address' => $ip,
            'user_id'    => $user_id,
            'plan'       => $plan->slug ?? 'free',
            'created_at' => $now,
            'expires_at' => $expires_at,
        ] );

        if ( ! $id ) {
            return new WP_Error( 'db_error', __( 'Could not create address.', 'tempmail-pro' ) );
        }

        TempMail_Rate_Limiter::log( $ip, 'generate', $user_id );

        return [
            'address'    => $address,
            'session_id' => $session_id,
            'expires_at' => $expires_at,
            'address_id' => $id,
        ];
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /** Public: generate a preview username with no DB side-effects */
    public static function preview_username() : string { return self::random_username(); }

    private static function random_username() : string {
        $s          = get_option( 'tmpmp_settings', [] );
        $format     = $s['eg_format']     ?? 'adj_noun_num';
        $sep        = $s['eg_separator']  ?? '_';
        $num_suffix = $s['eg_num_suffix'] ?? 'always';
        $num_min    = max( 1,        intval( $s['eg_num_min']     ?? 100 ) );
        $num_max    = max( $num_min, intval( $s['eg_num_max']     ?? 999 ) );
        $char_len   = max( 4, min( 24, intval( $s['eg_char_length'] ?? 8 ) ) );
        $char_set   = $s['eg_char_set'] ?? 'alphanumeric';

        $adj_raw  = trim( $s['eg_adj_list']  ?? '' );
        $noun_raw = trim( $s['eg_noun_list'] ?? '' );

        $adj_pool  = $adj_raw  ? array_values( array_filter( preg_split('/\r?\n/', $adj_raw  ) ) )
                               : ['swift','brave','calm','dark','epic','fast','keen','kind','mild','pure','rare','true','wild'];
        $noun_pool = $noun_raw ? array_values( array_filter( preg_split('/\r?\n/', $noun_raw ) ) )
                               : ['fox','owl','bee','cat','elk','jay','bat','eel','koi','pug','arc','bit'];

        $add_num = ( $num_suffix === 'always' ) ? true
                 : ( ( $num_suffix === 'never' ) ? false : (bool) rand(0,1) );
        $num_str = $add_num ? (string) rand( $num_min, $num_max ) : '';

        switch ( $format ) {
            case 'adj_noun':
                return implode( $sep, [ $adj_pool[ array_rand($adj_pool) ], $noun_pool[ array_rand($noun_pool) ] ] );
            case 'noun_num':
                return $noun_pool[ array_rand($noun_pool) ] . ( $num_str ? $sep . $num_str : '' );
            case 'random_chars':
                return self::random_chars( $char_len, $char_set );
            case 'short_uuid':
                return self::short_uuid( $char_len );
            default: // adj_noun_num
                return implode( $sep, array_filter( [ $adj_pool[ array_rand($adj_pool) ], $noun_pool[ array_rand($noun_pool) ], $num_str ] ) );
        }
    }

    private static function random_chars( int $length, string $charset ) : string {
        $chars = ( $charset === 'alpha' ) ? 'abcdefghijklmnopqrstuvwxyz'
               : ( ( $charset === 'numeric' ) ? '0123456789' : 'abcdefghijklmnopqrstuvwxyz0123456789' );
        $result = ''; $max = strlen($chars) - 1;
        for ( $i = 0; $i < $length; $i++ ) $result .= $chars[ random_int(0, $max) ];
        return $result;
    }

    private static function short_uuid( int $length ) : string {
        return substr( str_replace('-','', wp_generate_uuid4()), 0, $length );
    }

    private static function resolve_domain( string $requested, object $plan ) : mixed {
        $allowed_cats  = json_decode( $plan->domains_allowed ?? '["free"]', true ) ?: ['free'];
        $all_domains   = TempMail_Database::get_all_domains();

        if ( $requested ) {
            // Find the domain in DB
            foreach ( $all_domains as $d ) {
                if ( $d->domain === $requested ) {
                    if ( in_array( $d->category, $allowed_cats, true ) ) {
                        return $d->domain;
                    }
                    return new WP_Error( 'plan_required', __( 'This domain requires a higher plan.', 'tempmail-pro' ) );
                }
            }
            return new WP_Error( 'invalid_domain', __( 'Domain not available.', 'tempmail-pro' ) );
        }

        // Pick random from allowed
        $pool = array_filter( $all_domains, fn($d) => in_array( $d->category, $allowed_cats, true ) );
        if ( empty( $pool ) ) {
            // Fallback: first active domain
            $pool = $all_domains;
        }
        if ( empty( $pool ) ) {
            return new WP_Error( 'no_domains', __( 'No domains configured.', 'tempmail-pro' ) );
        }
        $pool = array_values( $pool );
        return $pool[ array_rand( $pool ) ]->domain;
    }
}

