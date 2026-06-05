<?php
/**
 * TempMail Pro — Database schema creation & all query helpers
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class TempMail_Database {

    // ── Install (runs on activation) ─────────────────────────────────────────
    public static function install() {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        // Temp addresses
        dbDelta( "CREATE TABLE {$wpdb->prefix}tmpmp_addresses (
            id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            address      VARCHAR(255) NOT NULL,
            session_id   VARCHAR(64)  NOT NULL DEFAULT '',
            ip_address   VARCHAR(45)  NOT NULL DEFAULT '',
            user_id      BIGINT UNSIGNED NOT NULL DEFAULT 0,
            plan         VARCHAR(32) NOT NULL DEFAULT 'free',
            is_private   TINYINT(1)  NOT NULL DEFAULT 0,
            is_permanent TINYINT(1)  NOT NULL DEFAULT 0,
            created_at   DATETIME    NOT NULL,
            expires_at   DATETIME    NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY   address (address),
            KEY          session_id (session_id),
            KEY          expires_at (expires_at),
            KEY          user_id (user_id),
            KEY          is_permanent (is_permanent)
        ) $charset;" );

        // Emails in each inbox
        dbDelta( "CREATE TABLE {$wpdb->prefix}tmpmp_emails (
            id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            address_id   BIGINT UNSIGNED NOT NULL,
            message_id   VARCHAR(255) NOT NULL DEFAULT '',
            sender       VARCHAR(255) NOT NULL DEFAULT '',
            sender_name  VARCHAR(255) NOT NULL DEFAULT '',
            subject      VARCHAR(500) NOT NULL DEFAULT '',
            body_text    LONGTEXT,
            body_html    LONGTEXT,
            has_attach   TINYINT(1)  NOT NULL DEFAULT 0,
            received_at  DATETIME    NOT NULL,
            is_read      TINYINT(1)  NOT NULL DEFAULT 0,
            is_spam      TINYINT(1)  NOT NULL DEFAULT 0,
            size_bytes   INT UNSIGNED NOT NULL DEFAULT 0,
            PRIMARY KEY  (id),
            KEY          address_id (address_id),
            KEY          received_at (received_at),
            KEY          message_id (message_id)
        ) $charset;" );

        // Rate limiting log
        dbDelta( "CREATE TABLE {$wpdb->prefix}tmpmp_ratelimit (
            id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            ip_address   VARCHAR(45)  NOT NULL,
            action       VARCHAR(64)  NOT NULL,
            user_id      BIGINT UNSIGNED NOT NULL DEFAULT 0,
            created_at   DATETIME    NOT NULL,
            PRIMARY KEY  (id),
            KEY          ip_action (ip_address, action),
            KEY          created_at (created_at)
        ) $charset;" );

        // Subscription plans
        dbDelta( "CREATE TABLE {$wpdb->prefix}tmpmp_plans (
            id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            slug              VARCHAR(64) NOT NULL,
            name              VARCHAR(128) NOT NULL,
            price_monthly     DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            price_yearly      DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            max_inboxes       INT NOT NULL DEFAULT 1,
            inbox_lifetime    INT NOT NULL DEFAULT 30,
            refresh_interval  INT NOT NULL DEFAULT 10,
            max_storage_mb    INT NOT NULL DEFAULT 5,
            domains_allowed   LONGTEXT,
            features          LONGTEXT,
            has_custom_user              TINYINT(1) NOT NULL DEFAULT 0,
            has_api_access               TINYINT(1) NOT NULL DEFAULT 0,
            has_attachments              TINYINT(1) NOT NULL DEFAULT 0,
            no_ads                       TINYINT(1) NOT NULL DEFAULT 0,
            is_active                    TINYINT(1) NOT NULL DEFAULT 1,
            sort_order                   INT NOT NULL DEFAULT 0,
            has_premium_domains          TINYINT(1) NOT NULL DEFAULT 0,
            has_premium_storage          TINYINT(1) NOT NULL DEFAULT 0,
            has_custom_branding          TINYINT(1) NOT NULL DEFAULT 0,
            has_inbox_retention          TINYINT(1) NOT NULL DEFAULT 0,
            has_vip_domains              TINYINT(1) NOT NULL DEFAULT 0,
            has_unlimited_attachments    TINYINT(1) NOT NULL DEFAULT 0,
            has_email_forwarding         TINYINT(1) NOT NULL DEFAULT 0,
            has_alias_management         TINYINT(1) NOT NULL DEFAULT 0,
            has_advanced_spam            TINYINT(1) NOT NULL DEFAULT 0,
            has_custom_domain            TINYINT(1) NOT NULL DEFAULT 0,
            has_permanent_inbox          TINYINT(1) NOT NULL DEFAULT 0,
            max_permanent_inboxes        INT NOT NULL DEFAULT 0,
            PRIMARY KEY (id),
            UNIQUE KEY slug (slug)
        ) $charset;" );

        // User subscriptions
        dbDelta( "CREATE TABLE {$wpdb->prefix}tmpmp_subscriptions (
            id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id         BIGINT UNSIGNED NOT NULL,
            plan_id         BIGINT UNSIGNED NOT NULL,
            gateway         VARCHAR(64) NOT NULL DEFAULT 'stripe',
            gateway_sub_id  VARCHAR(255) NOT NULL DEFAULT '',
            gateway_cust_id VARCHAR(255) NOT NULL DEFAULT '',
            status          VARCHAR(32) NOT NULL DEFAULT 'active',
            billing_cycle   VARCHAR(16) NOT NULL DEFAULT 'monthly',
            amount          DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            currency        VARCHAR(8) NOT NULL DEFAULT 'USD',
            trial_ends      DATETIME DEFAULT NULL,
            current_period_start DATETIME DEFAULT NULL,
            current_period_end   DATETIME DEFAULT NULL,
            cancelled_at    DATETIME DEFAULT NULL,
            created_at      DATETIME NOT NULL,
            updated_at      DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY status (status)
        ) $charset;" );

        // Payment/invoice history
        dbDelta( "CREATE TABLE {$wpdb->prefix}tmpmp_payments (
            id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id         BIGINT UNSIGNED NOT NULL,
            subscription_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            gateway         VARCHAR(64) NOT NULL,
            gateway_txn_id  VARCHAR(255) NOT NULL DEFAULT '',
            amount          DECIMAL(10,2) NOT NULL,
            currency        VARCHAR(8) NOT NULL DEFAULT 'USD',
            status          VARCHAR(32) NOT NULL DEFAULT 'completed',
            invoice_number  VARCHAR(64) NOT NULL DEFAULT '',
            description     VARCHAR(255) NOT NULL DEFAULT '',
            tax_amount      DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            metadata        LONGTEXT,
            created_at      DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY gateway_txn_id (gateway_txn_id)
        ) $charset;" );

        // Domains registry
        dbDelta( "CREATE TABLE {$wpdb->prefix}tmpmp_domains (
            id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            domain       VARCHAR(255) NOT NULL,
            category     VARCHAR(32) NOT NULL DEFAULT 'free',
            description  VARCHAR(255) NOT NULL DEFAULT '',
            mx_record    VARCHAR(255) NOT NULL DEFAULT '',
            is_active    TINYINT(1)  NOT NULL DEFAULT 1,
            is_catch_all TINYINT(1)  NOT NULL DEFAULT 1,
            health_status VARCHAR(32) NOT NULL DEFAULT 'unknown',
            last_checked DATETIME DEFAULT NULL,
            emails_count BIGINT UNSIGNED NOT NULL DEFAULT 0,
            created_at   DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY domain (domain)
        ) $charset;" );

        // API Keys
        dbDelta( "CREATE TABLE {$wpdb->prefix}tmpmp_api_keys (
            id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id     BIGINT UNSIGNED NOT NULL,
            api_key     VARCHAR(64) NOT NULL,
            label       VARCHAR(128) NOT NULL DEFAULT 'Default',
            permissions VARCHAR(255) NOT NULL DEFAULT 'read',
            calls_count BIGINT UNSIGNED NOT NULL DEFAULT 0,
            last_used   DATETIME DEFAULT NULL,
            expires_at  DATETIME DEFAULT NULL,
            is_active   TINYINT(1) NOT NULL DEFAULT 1,
            created_at  DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY api_key (api_key),
            KEY user_id (user_id)
        ) $charset;" );

        // Ad placements
        dbDelta( "CREATE TABLE {$wpdb->prefix}tmpmp_ads (
            id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            name        VARCHAR(128) NOT NULL,
            placement   VARCHAR(64) NOT NULL,
            type        VARCHAR(32) NOT NULL DEFAULT 'banner',
            code        LONGTEXT NOT NULL,
            is_active   TINYINT(1) NOT NULL DEFAULT 1,
            impressions BIGINT UNSIGNED NOT NULL DEFAULT 0,
            clicks      BIGINT UNSIGNED NOT NULL DEFAULT 0,
            created_at  DATETIME NOT NULL,
            PRIMARY KEY (id)
        ) $charset;" );

        // Blocked IPs
        dbDelta( "CREATE TABLE {$wpdb->prefix}tmpmp_blocked_ips (
            id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            ip_address  VARCHAR(45) NOT NULL,
            reason      VARCHAR(255) NOT NULL DEFAULT '',
            blocked_at  DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY ip_address (ip_address)
        ) $charset;" );

        // Visitors log
        dbDelta( "CREATE TABLE {$wpdb->prefix}tmpmp_visitors (
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

        // User custom domains (premium feature — DNS Verification Wizard)
        dbDelta( "CREATE TABLE {$wpdb->prefix}tmpmp_user_domains (
            id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id          BIGINT UNSIGNED NOT NULL,
            domain           VARCHAR(255) NOT NULL,
            status           VARCHAR(32) NOT NULL DEFAULT 'pending',
            verify_token     VARCHAR(128) NOT NULL DEFAULT '',
            txt_verified     TINYINT(1) NOT NULL DEFAULT 0,
            mx_verified      TINYINT(1) NOT NULL DEFAULT 0,
            spf_verified     TINYINT(1) NOT NULL DEFAULT 0,
            dkim_selector    VARCHAR(64) NOT NULL DEFAULT 'tmpro',
            dkim_private_key LONGTEXT,
            dkim_public_key  LONGTEXT,
            dkim_verified    TINYINT(1) NOT NULL DEFAULT 0,
            dmarc_verified   TINYINT(1) NOT NULL DEFAULT 0,
            last_checked     DATETIME DEFAULT NULL,
            verified_at      DATETIME DEFAULT NULL,
            created_at       DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY user_domain (user_id, domain),
            KEY status (status),
            KEY user_id (user_id)
        ) $charset;" );

        update_option( 'tmpmp_db_version', TMPMP_VERSION );
        update_option( 'tmpmp_settings', self::default_settings() );

        // Insert default plans and domains
        self::insert_default_plans();
        self::insert_default_domains();
    }


    // ── Default plans ─────────────────────────────────────────────────────────
    private static function insert_default_plans() {
        global $wpdb;
        $t = $wpdb->prefix . 'tmpmp_plans';
        if ( $wpdb->get_var( "SELECT COUNT(*) FROM $t" ) > 0 ) return;

        $plans = [
            [
                'slug' => 'free', 'name' => 'Free', 'price_monthly' => 0, 'price_yearly' => 0,
                'max_inboxes' => 3, 'inbox_lifetime' => 30, 'refresh_interval' => 15,
                'max_storage_mb' => 5, 'has_custom_user' => 0, 'has_api_access' => 0,
                'has_attachments' => 0, 'no_ads' => 0, 'sort_order' => 0,
                'features' => json_encode(['3 inboxes','30 min lifetime','Basic domains','Ad-supported']),
                'domains_allowed' => json_encode(['free']),
            ],
            [
                'slug' => 'starter', 'name' => 'Starter', 'price_monthly' => 4.99, 'price_yearly' => 39.99,
                'max_inboxes' => 10, 'inbox_lifetime' => 120, 'refresh_interval' => 10,
                'max_storage_mb' => 50, 'has_custom_user' => 1, 'has_api_access' => 0,
                'has_attachments' => 1, 'no_ads' => 1, 'sort_order' => 1,
                'features' => json_encode(['10 inboxes','2hr lifetime','Premium domains','No ads','Custom username','Attachments']),
                'domains_allowed' => json_encode(['free','premium']),
            ],
            [
                'slug' => 'pro', 'name' => 'Pro', 'price_monthly' => 9.99, 'price_yearly' => 79.99,
                'max_inboxes' => 50, 'inbox_lifetime' => 720, 'refresh_interval' => 5,
                'max_storage_mb' => 500, 'has_custom_user' => 1, 'has_api_access' => 1,
                'has_attachments' => 1, 'no_ads' => 1, 'sort_order' => 2,
                'features' => json_encode(['50 inboxes','12hr lifetime','All domains','API access','Private inboxes','Priority support']),
                'domains_allowed' => json_encode(['free','premium','vip']),
            ],
            [
                'slug' => 'business', 'name' => 'Business', 'price_monthly' => 29.99, 'price_yearly' => 239.99,
                'max_inboxes' => -1, 'inbox_lifetime' => 4320, 'refresh_interval' => 5,
                'max_storage_mb' => -1, 'has_custom_user' => 1, 'has_api_access' => 1,
                'has_attachments' => 1, 'no_ads' => 1, 'sort_order' => 3,
                'features' => json_encode(['Unlimited inboxes','3 day lifetime','Reserved domains','Full API','White-label','Dedicated support']),
                'domains_allowed' => json_encode(['free','premium','vip']),
            ],
        ];

        $now = gmdate('Y-m-d H:i:s');
        foreach ( $plans as $p ) {
            $wpdb->insert( $t, array_merge( $p, ['created_at' => $now] ) );
        }
    }

    // ── Default domains ───────────────────────────────────────────────────────
    private static function insert_default_domains() {
        global $wpdb;
        $t = $wpdb->prefix . 'tmpmp_domains';
        if ( $wpdb->get_var( "SELECT COUNT(*) FROM $t" ) > 0 ) return;

        $now = gmdate('Y-m-d H:i:s');
        $domains = [
            ['domain' => 'tempmail.dev',   'category' => 'free',    'description' => 'Default free domain'],
            ['domain' => 'inboxpro.io',    'category' => 'premium', 'description' => 'Premium speed domain'],
            ['domain' => 'privmail.net',   'category' => 'premium', 'description' => 'Private email domain'],
            ['domain' => 'vaultmail.org',  'category' => 'vip',     'description' => 'VIP exclusive domain'],
        ];

        foreach ( $domains as $d ) {
            $wpdb->insert( $t, array_merge( $d, ['created_at' => $now] ) );
        }
    }

    // ── Default settings ──────────────────────────────────────────────────────
    public static function default_settings() : array {
        return [
            'refresh_interval'      => 10,
            'mail_protocol'         => 'webhook',
            'webhook_secret'        => wp_generate_password( 32, false ),
            'spam_filter'           => 1,
            'spam_keywords'         => "casino\nviagra\nlottery",
            'imap_host'             => '',
            'imap_port'             => 993,
            'imap_user'             => '',
            'imap_pass'             => '',
            'imap_protocol'         => 'imap',
            'enable_captcha'        => 0,
            'rate_limit'            => 10,
            'rate_window'           => 24,
            'stripe_enabled'        => 0,
            'stripe_pk'             => '',
            'stripe_sk'             => '',
            'stripe_webhook_secret' => '',
            'paypal_enabled'        => 0,
            'paypal_client_id'      => '',
            'paypal_secret'         => '',
            'ssl_store_id'          => '',
            'ssl_store_pass'        => '',
            'ssl_live'              => 0,
            'google_login'          => 0,
            'google_client_id'      => '',
            'facebook_login'        => 0,
            'adsense_code'             => '',
            'server_cron_token'        => wp_generate_password( 48, false ),
            'custom_domain_mx_host'    => '',
            'custom_domain_spf_include'=> '',
            'custom_domain_max_per_user' => 3,
        ];
    }

    // ── Query helpers ─────────────────────────────────────────────────────────

    public static function get_address( string $address ) : ?object {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}tmpmp_addresses WHERE address = %s",
            $address
        ) ) ?: null;
    }

    public static function get_active_address( string $address ) : ?object {
        global $wpdb;
        // Permanent inboxes (is_permanent=1) are always active regardless of expires_at
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}tmpmp_addresses
              WHERE address = %s AND (is_permanent = 1 OR expires_at > UTC_TIMESTAMP())",
            $address
        ) ) ?: null;
    }

    public static function get_address_by_id( int $id ) : ?object {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}tmpmp_addresses WHERE id = %d",
            $id
        ) ) ?: null;
    }

    public static function get_emails_for_address( int $address_id ) : array {
        global $wpdb;
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT id, sender, sender_name, subject, received_at, is_read, is_spam, size_bytes
             FROM {$wpdb->prefix}tmpmp_emails
             WHERE address_id = %d AND is_spam = 0
             ORDER BY received_at DESC LIMIT 100",
            $address_id
        ) ) ?: [];
    }

    public static function get_email( int $email_id, int $address_id ) : ?object {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}tmpmp_emails WHERE id = %d AND address_id = %d",
            $email_id, $address_id
        ) ) ?: null;
    }

    public static function insert_address( array $data ) : int {
        global $wpdb;
        $wpdb->insert( $wpdb->prefix . 'tmpmp_addresses', $data );
        return (int) $wpdb->insert_id;
    }

    public static function insert_email( array $data ) : int {
        global $wpdb;
        $wpdb->insert( $wpdb->prefix . 'tmpmp_emails', $data );
        return (int) $wpdb->insert_id;
    }

    public static function mark_email_read( int $email_id ) : void {
        global $wpdb;
        $wpdb->update(
            $wpdb->prefix . 'tmpmp_emails',
            [ 'is_read' => 1 ],
            [ 'id'      => $email_id ]
        );
    }

    public static function delete_email( int $email_id, int $address_id ) : bool {
        global $wpdb;
        return (bool) $wpdb->delete(
            $wpdb->prefix . 'tmpmp_emails',
            [ 'id' => $email_id, 'address_id' => $address_id ]
        );
    }

    public static function delete_address( int $address_id ) : void {
        global $wpdb;
        $wpdb->delete( $wpdb->prefix . 'tmpmp_emails',    ['address_id' => $address_id] );
        $wpdb->delete( $wpdb->prefix . 'tmpmp_addresses', ['id'         => $address_id] );
    }

    public static function purge_expired() : int {
        global $wpdb;
        $p = $wpdb->prefix;

        // ── 1. Anonymous/guest addresses — keep address row for 3 days post-expiry
        //       Permanent inboxes (is_permanent=1) are NEVER purged.
        $anon_expired_ids = $wpdb->get_col(
            "SELECT id FROM {$p}tmpmp_addresses
              WHERE expires_at <= UTC_TIMESTAMP()
                AND user_id = 0
                AND is_permanent = 0"
        );
        if ( ! empty( $anon_expired_ids ) ) {
            $in = implode( ',', array_map('intval', $anon_expired_ids) );
            $wpdb->query( "DELETE FROM {$p}tmpmp_emails WHERE address_id IN ($in)" );
        }

        // Hard-delete anonymous address rows older than 3 days
        $anon_old_ids = $wpdb->get_col(
            "SELECT id FROM {$p}tmpmp_addresses
              WHERE user_id = 0
                AND is_permanent = 0
                AND expires_at <= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 3 DAY)"
        );
        if ( ! empty( $anon_old_ids ) ) {
            $in = implode( ',', array_map('intval', $anon_old_ids) );
            $wpdb->query( "DELETE FROM {$p}tmpmp_addresses WHERE id IN ($in)" );
        }

        // ── 2. Premium user addresses — purge emails but keep address row.
        //       SKIP permanent inboxes — they keep their emails forever.
        $user_expired_ids = $wpdb->get_col(
            "SELECT id FROM {$p}tmpmp_addresses
              WHERE expires_at <= UTC_TIMESTAMP()
                AND user_id != 0
                AND is_permanent = 0"
        );
        if ( ! empty( $user_expired_ids ) ) {
            $in = implode( ',', array_map('intval', $user_expired_ids) );
            $wpdb->query( "DELETE FROM {$p}tmpmp_emails WHERE address_id IN ($in)" );
        }

        // ── 3. Hard-delete premium address records older than 90 days (not permanent)
        $old_ids = $wpdb->get_col(
            "SELECT id FROM {$p}tmpmp_addresses
              WHERE user_id != 0
                AND is_permanent = 0
                AND expires_at <= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 90 DAY)"
        );
        if ( ! empty( $old_ids ) ) {
            $in = implode( ',', array_map('intval', $old_ids) );
            $wpdb->query( "DELETE FROM {$p}tmpmp_addresses WHERE id IN ($in)" );
        }

        // ── 4. Purge old ratelimit records (> 24 h)
        $wpdb->query( "DELETE FROM {$p}tmpmp_ratelimit WHERE created_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL 24 HOUR)" );

        return count( $anon_old_ids ) + count( $old_ids );
    }

    public static function get_stats() : array {
        global $wpdb;
        $p = $wpdb->prefix;
        return [
            'total_addresses'  => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$p}tmpmp_addresses" ),
            'active_addresses' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$p}tmpmp_addresses WHERE expires_at > UTC_TIMESTAMP()" ),
            'total_emails'     => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$p}tmpmp_emails" ),
            'emails_today'     => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$p}tmpmp_emails WHERE DATE(received_at) = UTC_DATE()" ),
            'total_domains'    => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$p}tmpmp_domains WHERE is_active = 1" ),
            'premium_users'    => (int) $wpdb->get_var( "SELECT COUNT(DISTINCT user_id) FROM {$p}tmpmp_subscriptions WHERE status = 'active'" ),
            'total_revenue'    => (float) $wpdb->get_var( "SELECT COALESCE(SUM(amount),0) FROM {$p}tmpmp_payments WHERE status = 'completed'" ),
        ];
    }

    // Plans
    public static function get_all_plans( bool $active_only = true ) : array {
        global $wpdb;
        $where = $active_only ? 'WHERE is_active = 1' : '';
        return $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}tmpmp_plans $where ORDER BY sort_order ASC"
        ) ?: [];
    }

    public static function get_plan( int $id ) : ?object {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}tmpmp_plans WHERE id = %d", $id
        ) ) ?: null;
    }

    public static function get_plan_by_slug( string $slug ) : ?object {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}tmpmp_plans WHERE slug = %s", $slug
        ) ) ?: null;
    }

    // Domains
    public static function get_all_domains( string $category = '' ) : array {
        global $wpdb;
        if ( $category ) {
            return $wpdb->get_results( $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}tmpmp_domains WHERE is_active = 1 AND category = %s ORDER BY domain ASC",
                $category
            ) ) ?: [];
        }
        return $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}tmpmp_domains WHERE is_active = 1 ORDER BY category, domain"
        ) ?: [];
    }

    // User subscription
    public static function get_user_subscription( int $user_id ) : ?object {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT s.*, p.slug as plan_slug, p.name as plan_name, p.features,
                    p.max_inboxes, p.inbox_lifetime, p.refresh_interval,
                    p.max_storage_mb, p.domains_allowed, p.has_custom_user,
                    p.no_ads, p.has_api_access, p.has_attachments,
                    p.has_premium_domains, p.has_premium_storage, p.has_custom_branding,
                    p.has_inbox_retention, p.has_vip_domains, p.has_unlimited_attachments,
                    p.has_email_forwarding, p.has_alias_management, p.has_advanced_spam,
                    p.has_custom_domain
             FROM {$wpdb->prefix}tmpmp_subscriptions s
             JOIN {$wpdb->prefix}tmpmp_plans p ON p.id = s.plan_id
             WHERE s.user_id = %d AND s.status IN ('active','trialing')
             ORDER BY s.created_at DESC LIMIT 1",
            $user_id
        ) ) ?: null;
    }

    // API Keys
    public static function get_api_key_record( string $key ) : ?object {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT k.*, s.plan_id FROM {$wpdb->prefix}tmpmp_api_keys k
             LEFT JOIN {$wpdb->prefix}tmpmp_subscriptions s ON s.user_id = k.user_id AND s.status = 'active'
             WHERE k.api_key = %s AND k.is_active = 1 AND (k.expires_at IS NULL OR k.expires_at > UTC_TIMESTAMP())",
            $key
        ) ) ?: null;
    }

    // Blocked IPs
    public static function is_ip_blocked( string $ip ) : bool {
        global $wpdb;
        return (bool) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}tmpmp_blocked_ips WHERE ip_address = %s",
            $ip
        ) );
    }

    // ── Address History (premium users) ──────────────────────────────────────

    /**
     * Get paginated address history for a logged-in premium user.
     * Returns addresses regardless of expiry (including expired ones kept for history).
     */
    public static function get_address_history_for_user( int $user_id, int $per_page = 20, int $page = 1 ) : array {
        global $wpdb;
        $offset = max(0, $page - 1) * $per_page;
        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT a.*,
                    ( SELECT COUNT(*) FROM {$wpdb->prefix}tmpmp_emails e WHERE e.address_id = a.id ) AS email_count,
                    CASE WHEN a.expires_at > UTC_TIMESTAMP() THEN 'active' ELSE 'expired' END AS status_label
             FROM {$wpdb->prefix}tmpmp_addresses a
             WHERE a.user_id = %d
             ORDER BY a.created_at DESC
             LIMIT %d OFFSET %d",
            $user_id, $per_page, $offset
        ) ) ?: [];
        $total = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}tmpmp_addresses WHERE user_id = %d",
            $user_id
        ) );
        return [ 'rows' => $rows, 'total' => $total, 'per_page' => $per_page, 'page' => $page ];
    }

    /**
     * Get emails for a history address — verifies ownership via user_id.
     */
    public static function get_history_emails( int $address_id, int $user_id ) : ?array {
        global $wpdb;
        $addr = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}tmpmp_addresses WHERE id = %d AND user_id = %d",
            $address_id, $user_id
        ) );
        if ( ! $addr ) return null;
        $emails = $wpdb->get_results( $wpdb->prepare(
            "SELECT id, sender, sender_name, subject, received_at, is_read, has_attach, size_bytes
             FROM {$wpdb->prefix}tmpmp_emails
             WHERE address_id = %d
             ORDER BY received_at DESC LIMIT 100",
            $address_id
        ) ) ?: [];
        return [ 'address' => $addr, 'emails' => $emails ];
    }

    /**
     * Delete a history address + its emails (user must own it).
     */
    public static function delete_history_address( int $address_id, int $user_id ) : bool {
        global $wpdb;
        $ok = $wpdb->delete(
            $wpdb->prefix . 'tmpmp_addresses',
            [ 'id' => $address_id, 'user_id' => $user_id ]
        );
        if ( $ok ) {
            $wpdb->delete( $wpdb->prefix . 'tmpmp_emails', [ 'address_id' => $address_id ] );
        }
        return (bool) $ok;
    }

    /**
     * Get verified custom domains for a specific user.
     */
    public static function get_user_verified_domains( int $user_id ) : array {
        global $wpdb;
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT domain FROM {$wpdb->prefix}tmpmp_user_domains
              WHERE user_id = %d AND status = 'verified'
              ORDER BY domain ASC",
            $user_id
        ) ) ?: [];
    }

    // ── Permanent Inbox helpers ───────────────────────────────────────────────

    /**
     * Get all permanent inboxes for a user (with email counts).
     */
    public static function get_permanent_inboxes_for_user( int $user_id ) : array {
        global $wpdb;
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT a.*,
                    ( SELECT COUNT(*) FROM {$wpdb->prefix}tmpmp_emails e WHERE e.address_id = a.id ) AS email_count
             FROM {$wpdb->prefix}tmpmp_addresses a
             WHERE a.user_id = %d AND a.is_permanent = 1
             ORDER BY a.created_at DESC",
            $user_id
        ) ) ?: [];
    }

    /**
     * Count permanent inboxes owned by a user.
     */
    public static function count_permanent_inboxes_for_user( int $user_id ) : int {
        global $wpdb;
        return (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}tmpmp_addresses WHERE user_id = %d AND is_permanent = 1",
            $user_id
        ) );
    }

    /**
     * Get all emails for a permanent inbox (for export). Verifies ownership.
     * Returns null if address not found or not owned by user.
     */
    public static function get_emails_for_permanent_export( int $address_id, int $user_id ) : ?array {
        global $wpdb;
        $addr = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}tmpmp_addresses WHERE id = %d AND user_id = %d AND is_permanent = 1",
            $address_id, $user_id
        ) );
        if ( ! $addr ) return null;
        $emails = $wpdb->get_results( $wpdb->prepare(
            "SELECT id, sender, sender_name, subject, body_text, body_html,
                    has_attach, received_at, is_read, size_bytes
             FROM {$wpdb->prefix}tmpmp_emails
             WHERE address_id = %d
             ORDER BY received_at DESC",
            $address_id
        ) ) ?: [];
        return [ 'address' => $addr, 'emails' => $emails ];
    }
}
