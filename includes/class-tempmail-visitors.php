<?php
defined('ABSPATH') || exit;

/**
 * TempMail_Visitors
 * Tracks front-end page views + enforces IP / User-Agent block lists.
 */
class TempMail_Visitors {

    /** Option keys for block lists */
    const OPT_BLOCKED_IPS = 'tmpmp_blocked_ips';
    const OPT_BLOCKED_UAS = 'tmpmp_blocked_uas';

    /** Hook in early — priority 1 so block fires before anything else */
    public static function init() : void {
        add_action( 'template_redirect', [ __CLASS__, 'maybe_block' ], 1 );
        add_action( 'template_redirect', [ __CLASS__, 'track' ],       5 );
    }

    // ══════════════════════════════════════════════════════════════════
    //  BLOCKING — runs before tracking
    // ══════════════════════════════════════════════════════════════════

    /** Terminate the request with 403 if the visitor is on a block list */
    public static function maybe_block() : void {
        if ( is_admin() || wp_doing_ajax() || wp_doing_cron() ) return;
        if ( defined('REST_REQUEST') && REST_REQUEST ) return;

        $ip = self::get_ip();
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';

        if ( self::is_blocked_ip( $ip ) || self::is_blocked_ua( $ua ) ) {
            status_header( 403 );
            nocache_headers();
            wp_die(
                esc_html__( 'Access denied.', 'tempmail-pro' ),
                esc_html__( 'Forbidden', 'tempmail-pro' ),
                [ 'response' => 403 ]
            );
        }
    }

    /** Check if an IP is in the block list (supports exact IPs and CIDR /24 ranges) */
    public static function is_blocked_ip( string $ip ) : bool {
        $list = self::get_blocked_ips();
        foreach ( $list as $entry ) {
            $entry = trim( $entry );
            if ( $entry === '' ) continue;
            // CIDR range e.g. 192.168.1.0/24
            if ( str_contains( $entry, '/' ) ) {
                if ( self::ip_in_cidr( $ip, $entry ) ) return true;
            } elseif ( $entry === $ip ) {
                return true;
            }
        }
        return false;
    }

    /** Check if a user-agent string matches any blocked pattern (substring, case-insensitive) */
    public static function is_blocked_ua( string $ua ) : bool {
        if ( $ua === '' ) return false;
        $list = self::get_blocked_uas();
        foreach ( $list as $pattern ) {
            $pattern = trim( $pattern );
            if ( $pattern === '' ) continue;
            if ( stripos( $ua, $pattern ) !== false ) return true;
        }
        return false;
    }

    /** CIDR helper — checks if $ip is within $cidr (e.g. 192.168.1.0/24) */
    private static function ip_in_cidr( string $ip, string $cidr ) : bool {
        [ $subnet, $prefix ] = explode( '/', $cidr, 2 );
        $prefix = (int) $prefix;
        $ipLong  = ip2long( $ip );
        $subLong = ip2long( $subnet );
        if ( $ipLong === false || $subLong === false ) return false;
        $mask = $prefix > 0 ? ( ~0 << ( 32 - $prefix ) ) : 0;
        return ( $ipLong & $mask ) === ( $subLong & $mask );
    }

    // ══════════════════════════════════════════════════════════════════
    //  BLOCK LIST CRUD
    // ══════════════════════════════════════════════════════════════════

    public static function get_blocked_ips() : array {
        $raw = get_option( self::OPT_BLOCKED_IPS, [] );
        return is_array( $raw ) ? array_filter( array_map( 'trim', $raw ) ) : [];
    }

    public static function get_blocked_uas() : array {
        $raw = get_option( self::OPT_BLOCKED_UAS, [] );
        return is_array( $raw ) ? array_filter( array_map( 'trim', $raw ) ) : [];
    }

    /** Save the full IP block list (array of strings) */
    public static function save_blocked_ips( array $ips ) : void {
        $clean = array_values( array_unique( array_filter( array_map( 'sanitize_text_field', $ips ) ) ) );
        update_option( self::OPT_BLOCKED_IPS, $clean, false );
    }

    /** Save the full UA block list */
    public static function save_blocked_uas( array $uas ) : void {
        $clean = array_values( array_unique( array_filter( array_map( 'sanitize_text_field', $uas ) ) ) );
        update_option( self::OPT_BLOCKED_UAS, $clean, false );
    }

    /** Add a single IP to the block list */
    public static function block_ip( string $ip ) : void {
        $ip   = sanitize_text_field( trim( $ip ) );
        $list = self::get_blocked_ips();
        if ( $ip && ! in_array( $ip, $list, true ) ) {
            $list[] = $ip;
            self::save_blocked_ips( $list );
        }
    }

    /** Remove a single IP from the block list */
    public static function unblock_ip( string $ip ) : void {
        $ip   = sanitize_text_field( trim( $ip ) );
        $list = array_values( array_diff( self::get_blocked_ips(), [ $ip ] ) );
        self::save_blocked_ips( $list );
    }

    /** Add a single UA pattern to the block list */
    public static function block_ua( string $ua ) : void {
        $ua   = sanitize_text_field( trim( $ua ) );
        $list = self::get_blocked_uas();
        if ( $ua && ! in_array( $ua, $list, true ) ) {
            $list[] = $ua;
            self::save_blocked_uas( $list );
        }
    }

    /** Remove a single UA pattern from the block list */
    public static function unblock_ua( string $ua ) : void {
        $ua   = sanitize_text_field( trim( $ua ) );
        $list = array_values( array_diff( self::get_blocked_uas(), [ $ua ] ) );
        self::save_blocked_uas( $list );
    }

    // ══════════════════════════════════════════════════════════════════
    //  TRACKING
    // ══════════════════════════════════════════════════════════════════

    public static function track() : void {
        if ( is_admin() || wp_doing_ajax() || wp_doing_cron() ) return;
        if ( defined('REST_REQUEST') && REST_REQUEST ) return;

        global $wpdb;

        $ua         = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $is_bot     = self::is_bot( $ua ) ? 1 : 0;
        $ip         = self::get_ip();
        $page_url   = ( is_ssl() ? 'https' : 'http' ) . '://' . ($_SERVER['HTTP_HOST']??'') . ($_SERVER['REQUEST_URI']??'');
        $referrer   = $_SERVER['HTTP_REFERER'] ?? '';
        $browser    = self::parse_browser( $ua );
        $os         = self::parse_os( $ua );
        $user_id    = get_current_user_id();

        $wpdb->insert(
            $wpdb->prefix . 'tmpmp_visitors',
            [
                'ip'         => sanitize_text_field( $ip ),
                'country'    => self::get_country( $ip ),
                'page_url'   => esc_url_raw( substr( $page_url, 0, 1000 ) ),
                'page_title' => '',
                'referrer'   => sanitize_text_field( substr( $referrer, 0, 1000 ) ),
                'user_agent' => sanitize_text_field( substr( $ua, 0, 500 ) ),
                'browser'    => sanitize_text_field( $browser ),
                'os'         => sanitize_text_field( $os ),
                'is_bot'     => $is_bot,
                'user_id'    => intval( $user_id ),
                'visited_at' => current_time( 'mysql', true ),
            ],
            [ '%s','%s','%s','%s','%s','%s','%s','%s','%d','%d','%s' ]
        );
    }

    // ══════════════════════════════════════════════════════════════════
    //  HELPERS
    // ══════════════════════════════════════════════════════════════════

    private static function get_ip() : string {
        foreach ( ['HTTP_CF_CONNECTING_IP','HTTP_X_FORWARDED_FOR','HTTP_CLIENT_IP','REMOTE_ADDR'] as $key ) {
            if ( ! empty( $_SERVER[$key] ) ) {
                $ip = trim( explode(',', $_SERVER[$key])[0] );
                if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) return $ip;
            }
        }
        return '0.0.0.0';
    }

    private static function get_country( string $ip ) : string {
        // Skip private / reserved IP ranges (localhost, LAN, etc.)
        if ( ! filter_var( $ip, FILTER_VALIDATE_IP,
                FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
            return '';
        }

        // 1. WooCommerce built-in GeoLite2 (fastest, local DB)
        if ( class_exists( 'WC_Geolocation' ) ) {
            $geo     = WC_Geolocation::geolocate_ip( $ip );
            $country = strtoupper( $geo['country'] ?? '' );
            if ( $country ) return $country;
        }

        // 2. Transient-cached free API fallback (ip-api.com)
        $cache_key = 'tmpmp_geo_' . md5( $ip );
        $cached    = get_transient( $cache_key );
        if ( $cached !== false ) return (string) $cached;

        $response = wp_remote_get(
            'http://ip-api.com/json/' . rawurlencode( $ip ) . '?fields=countryCode',
            [ 'timeout' => 2, 'blocking' => true, 'user-agent' => 'TempMailPro/1.0' ]
        );

        $country = '';
        if ( ! is_wp_error( $response ) ) {
            $body = json_decode( wp_remote_retrieve_body( $response ), true );
            $country = strtoupper( $body['countryCode'] ?? '' );
        }

        // Cache for 24 hours (even empty, to avoid hammering the API)
        set_transient( $cache_key, $country, DAY_IN_SECONDS );
        return $country;
    }

    private static function is_bot( string $ua ) : bool {
        if ( empty($ua) ) return true;
        $bots = 'bot|crawl|spider|slurp|mediapartners|facebookexternalhit|bingpreview|pingdom|uptimerobot|curl|wget|python|java|go-http|axios|node-fetch';
        return (bool) preg_match( "/$bots/i", $ua );
    }

    private static function parse_browser( string $ua ) : string {
        if ( str_contains($ua,'Edg/')  || str_contains($ua,'Edge/') ) return 'Edge';
        if ( str_contains($ua,'OPR/')  || str_contains($ua,'Opera/') ) return 'Opera';
        if ( str_contains($ua,'Chrome/')  ) return 'Chrome';
        if ( str_contains($ua,'Firefox/') ) return 'Firefox';
        if ( str_contains($ua,'Safari/')  && str_contains($ua,'Version/') ) return 'Safari';
        if ( str_contains($ua,'MSIE')     || str_contains($ua,'Trident/') ) return 'IE';
        return 'Other';
    }

    private static function parse_os( string $ua ) : string {
        if ( str_contains($ua,'Windows') ) return 'Windows';
        if ( str_contains($ua,'iPhone') || str_contains($ua,'iPad') ) return 'iOS';
        if ( str_contains($ua,'Android') ) return 'Android';
        if ( str_contains($ua,'Mac OS X') ) return 'macOS';
        if ( str_contains($ua,'Linux') ) return 'Linux';
        return 'Other';
    }

    // ══════════════════════════════════════════════════════════════════
    //  ADMIN QUERIES
    // ══════════════════════════════════════════════════════════════════

    public static function get_stats() : array {
        global $wpdb;
        $t = $wpdb->prefix . 'tmpmp_visitors';
        return [
            'total'       => (int) $wpdb->get_var("SELECT COUNT(*) FROM $t"),
            'unique'      => (int) $wpdb->get_var("SELECT COUNT(DISTINCT ip) FROM $t"),
            'today'       => (int) $wpdb->get_var("SELECT COUNT(*) FROM $t WHERE DATE(visited_at)=CURDATE()"),
            'bots'        => (int) $wpdb->get_var("SELECT COUNT(*) FROM $t WHERE is_bot=1"),
            'humans'      => (int) $wpdb->get_var("SELECT COUNT(*) FROM $t WHERE is_bot=0"),
            'blocked_ips' => count( self::get_blocked_ips() ),
            'blocked_uas' => count( self::get_blocked_uas() ),
        ];
    }

    public static function get_visitors( array $args = [] ) : array {
        global $wpdb;
        $t      = $wpdb->prefix . 'tmpmp_visitors';
        $limit  = intval( $args['limit']  ?? 50 );
        $offset = intval( $args['offset'] ?? 0 );
        $search = sanitize_text_field( $args['search'] ?? '' );
        $filter = sanitize_text_field( $args['filter'] ?? 'all' );

        $where = [];
        if ( $filter === 'bots' )   $where[] = 'is_bot = 1';
        if ( $filter === 'humans' ) $where[] = 'is_bot = 0';
        if ( $search ) {
            $like = '%' . $wpdb->esc_like($search) . '%';
            $where[] = $wpdb->prepare(
                '(ip LIKE %s OR page_url LIKE %s OR referrer LIKE %s OR browser LIKE %s OR os LIKE %s OR user_agent LIKE %s OR country LIKE %s)',
                $like, $like, $like, $like, $like, $like, $like
            );
        }
        $sql_where = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        return $wpdb->get_results(
            "SELECT * FROM $t $sql_where ORDER BY visited_at DESC LIMIT $limit OFFSET $offset",
            ARRAY_A
        ) ?: [];
    }

    public static function get_total_count( array $args = [] ) : int {
        global $wpdb;
        $t      = $wpdb->prefix . 'tmpmp_visitors';
        $search = sanitize_text_field( $args['search'] ?? '' );
        $filter = sanitize_text_field( $args['filter'] ?? 'all' );
        $where  = [];
        if ( $filter === 'bots' )   $where[] = 'is_bot = 1';
        if ( $filter === 'humans' ) $where[] = 'is_bot = 0';
        if ( $search ) {
            $like = '%' . $wpdb->esc_like($search) . '%';
            $where[] = $wpdb->prepare(
                '(ip LIKE %s OR page_url LIKE %s OR referrer LIKE %s OR browser LIKE %s OR os LIKE %s OR user_agent LIKE %s OR country LIKE %s)',
                $like, $like, $like, $like, $like, $like, $like
            );
        }
        $sql_where = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        return (int) $wpdb->get_var("SELECT COUNT(*) FROM $t $sql_where");
    }

    public static function get_chart_data( int $days = 14 ) : array {
        global $wpdb;
        $t = $wpdb->prefix . 'tmpmp_visitors';
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT DATE(visited_at) as d, COUNT(*) as total,
                    SUM(CASE WHEN is_bot=0 THEN 1 ELSE 0 END) as humans
             FROM $t
             WHERE visited_at >= DATE_SUB(CURDATE(), INTERVAL %d DAY)
             GROUP BY d ORDER BY d ASC",
            $days
        ), ARRAY_A ) ?: [];
    }

    public static function get_top_pages( int $limit = 10 ) : array {
        global $wpdb;
        $t = $wpdb->prefix . 'tmpmp_visitors';
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT page_url, COUNT(*) as views FROM $t WHERE is_bot=0 GROUP BY page_url ORDER BY views DESC LIMIT %d",
            $limit
        ), ARRAY_A ) ?: [];
    }

    public static function get_top_browsers( int $limit = 6 ) : array {
        global $wpdb;
        $t = $wpdb->prefix . 'tmpmp_visitors';
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT browser, COUNT(*) as cnt FROM $t WHERE is_bot=0 GROUP BY browser ORDER BY cnt DESC LIMIT %d",
            $limit
        ), ARRAY_A ) ?: [];
    }

    public static function get_top_oses( int $limit = 6 ) : array {
        global $wpdb;
        $t = $wpdb->prefix . 'tmpmp_visitors';
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT os, COUNT(*) as cnt FROM $t WHERE is_bot=0 GROUP BY os ORDER BY cnt DESC LIMIT %d",
            $limit
        ), ARRAY_A ) ?: [];
    }

    public static function purge_old( int $days = 90 ) : int {
        global $wpdb;
        $t = $wpdb->prefix . 'tmpmp_visitors';
        return (int) $wpdb->query( $wpdb->prepare(
            "DELETE FROM $t WHERE visited_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
            $days
        ) );
    }

    public static function maybe_create_table() : void {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();
        $t = $wpdb->prefix . 'tmpmp_visitors';
        if ( $wpdb->get_var("SHOW TABLES LIKE '$t'") === $t ) return;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( "CREATE TABLE $t (
            id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            ip          VARCHAR(45) NOT NULL DEFAULT '',
            country     VARCHAR(3) NOT NULL DEFAULT '',
            page_url    VARCHAR(1000) NOT NULL DEFAULT '',
            page_title  VARCHAR(255) NOT NULL DEFAULT '',
            referrer    VARCHAR(1000) NOT NULL DEFAULT '',
            user_agent  VARCHAR(500) NOT NULL DEFAULT '',
            browser     VARCHAR(100) NOT NULL DEFAULT '',
            os          VARCHAR(100) NOT NULL DEFAULT '',
            is_bot      TINYINT(1) NOT NULL DEFAULT 0,
            user_id     BIGINT UNSIGNED NOT NULL DEFAULT 0,
            visited_at  DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY idx_ip (ip(45)),
            KEY idx_visited_at (visited_at),
            KEY idx_page (page_url(191))
        ) $charset;" );
    }
}
