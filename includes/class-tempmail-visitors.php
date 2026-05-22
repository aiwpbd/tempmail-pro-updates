<?php
defined('ABSPATH') || exit;

/**
 * TempMail_Visitors
 * Tracks front-end page views into tmpmp_visitors table.
 */
class TempMail_Visitors {

    /** Hook in early */
    public static function init() : void {
        add_action( 'template_redirect', [ __CLASS__, 'track' ], 1 );
    }

    /** ── Record a page visit ─────────────────────────────────────────────── */
    public static function track() : void {
        // Only track singular front-end pages (not admin, AJAX, REST, cron)
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
        $page_title = '';  // grabbed client-side; stored empty server-side

        $wpdb->insert(
            $wpdb->prefix . 'tmpmp_visitors',
            [
                'ip'         => sanitize_text_field( $ip ),
                'country'    => self::get_country( $ip ),
                'page_url'   => esc_url_raw( substr( $page_url, 0, 1000 ) ),
                'page_title' => '',
                'referrer'   => esc_url_raw( substr( $referrer, 0, 1000 ) ),
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

    /** ── Helpers ─────────────────────────────────────────────────────────── */
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
        // Use WordPress's built-in geolocation if WooCommerce is active
        if ( class_exists('WC_Geolocation') ) {
            $geo  = WC_Geolocation::geolocate_ip( $ip );
            return strtoupper( $geo['country'] ?? '' );
        }
        return '';
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

    /** ── Admin Queries ───────────────────────────────────────────────────── */
    public static function get_stats() : array {
        global $wpdb;
        $t = $wpdb->prefix . 'tmpmp_visitors';
        return [
            'total'    => (int) $wpdb->get_var("SELECT COUNT(*) FROM $t"),
            'unique'   => (int) $wpdb->get_var("SELECT COUNT(DISTINCT ip) FROM $t"),
            'today'    => (int) $wpdb->get_var("SELECT COUNT(*) FROM $t WHERE DATE(visited_at)=CURDATE()"),
            'bots'     => (int) $wpdb->get_var("SELECT COUNT(*) FROM $t WHERE is_bot=1"),
            'humans'   => (int) $wpdb->get_var("SELECT COUNT(*) FROM $t WHERE is_bot=0"),
        ];
    }

    public static function get_visitors( array $args = [] ) : array {
        global $wpdb;
        $t      = $wpdb->prefix . 'tmpmp_visitors';
        $limit  = intval( $args['limit']  ?? 50 );
        $offset = intval( $args['offset'] ?? 0 );
        $search = sanitize_text_field( $args['search'] ?? '' );
        $filter = sanitize_text_field( $args['filter'] ?? 'all' ); // all|bots|humans

        $where = [];
        if ( $filter === 'bots' )   $where[] = 'is_bot = 1';
        if ( $filter === 'humans' ) $where[] = 'is_bot = 0';
        if ( $search ) {
            $like = '%' . $wpdb->esc_like($search) . '%';
            $where[] = $wpdb->prepare(
                '(ip LIKE %s OR page_url LIKE %s OR browser LIKE %s OR os LIKE %s)',
                $like, $like, $like, $like
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

        $where = [];
        if ( $filter === 'bots' )   $where[] = 'is_bot = 1';
        if ( $filter === 'humans' ) $where[] = 'is_bot = 0';
        if ( $search ) {
            $like = '%' . $wpdb->esc_like($search) . '%';
            $where[] = $wpdb->prepare(
                '(ip LIKE %s OR page_url LIKE %s OR browser LIKE %s OR os LIKE %s)',
                $like, $like, $like, $like
            );
        }
        $sql_where = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        return (int) $wpdb->get_var("SELECT COUNT(*) FROM $t $sql_where");
    }

    public static function get_chart_data( int $days = 14 ) : array {
        global $wpdb;
        $t = $wpdb->prefix . 'tmpmp_visitors';
        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT DATE(visited_at) as d, COUNT(*) as total,
                    SUM(CASE WHEN is_bot=0 THEN 1 ELSE 0 END) as humans
             FROM $t
             WHERE visited_at >= DATE_SUB(CURDATE(), INTERVAL %d DAY)
             GROUP BY d ORDER BY d ASC",
            $days
        ), ARRAY_A );
        return $rows ?: [];
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

    /** Create the table directly (for use when plugin is already active) */
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
