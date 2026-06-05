<?php
/**
 * TempMail Pro — Auto Setup (page creation on activation)
 *
 * Creates all required frontend pages with their shortcodes on first activation.
 * Safe to re-run: skips pages that already exist by slug or stored ID.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class TempMail_Setup {

    /**
     * Pages to create on activation.
     * key        => used to store the page ID in tmpmp_pages option
     * title      => WordPress page title
     * slug       => URL slug
     * shortcode  => content placed inside the page
     * settings_key => if set, page URL is saved into tmpmp_settings under this key
     */
    private static array $pages = [
        'inbox' => [
            'title'        => 'TempMail',
            'slug'         => 'tempmail-app',
            'shortcode'    => '[tempmail_app]',
            'settings_key' => '',                  // main page – no dedicated settings key
        ],
        'pricing' => [
            'title'        => 'TempMail Pricing',
            'slug'         => 'tempmail-pricing',
            'shortcode'    => '[tempmail_pricing]',
            'settings_key' => 'pricing_url',
        ],
        'login' => [
            'title'        => 'TempMail Login',
            'slug'         => 'tempmail-login',
            'shortcode'    => '[tempmail_login]',
            'settings_key' => 'login_url',
        ],
        'dashboard' => [
            'title'        => 'TempMail Dashboard',
            'slug'         => 'tempmail-dashboard',
            'shortcode'    => '[tempmail_dashboard]',
            'settings_key' => 'dashboard_url',
        ],
    ];

    // ── Public API ────────────────────────────────────────────────────────────

    /**
     * Called by register_activation_hook().
     * Creates pages that don't already exist and stores their IDs.
     */
    public static function create_pages() : void {
        $stored   = get_option( 'tmpmp_pages', [] );
        $settings = get_option( 'tmpmp_settings', [] );
        $changed  = false;

        foreach ( self::$pages as $key => $cfg ) {

            // 1. Skip if we already created this page and it still exists
            if ( ! empty( $stored[ $key ] ) ) {
                $existing = get_post( (int) $stored[ $key ] );
                if ( $existing && $existing->post_status !== 'trash' ) {
                    continue;
                }
            }

            // 2. Skip if a published page with the same slug already exists
            $by_slug = get_page_by_path( $cfg['slug'], OBJECT, 'page' );
            if ( $by_slug && $by_slug->post_status !== 'trash' ) {
                $stored[ $key ] = $by_slug->ID;
                if ( $cfg['settings_key'] ) {
                    $settings[ $cfg['settings_key'] ] = get_permalink( $by_slug->ID );
                }
                $changed = true;
                continue;
            }

            // 3. Create the page
            $id = wp_insert_post( [
                'post_title'   => $cfg['title'],
                'post_name'    => $cfg['slug'],
                'post_content' => $cfg['shortcode'],
                'post_status'  => 'publish',
                'post_type'    => 'page',
                'post_author'  => get_current_user_id() ?: 1,
                'comment_status' => 'closed',
                'ping_status'    => 'closed',
            ], true );

            if ( is_wp_error( $id ) ) continue;

            $stored[ $key ] = $id;

            if ( $cfg['settings_key'] ) {
                $settings[ $cfg['settings_key'] ] = get_permalink( $id );
            }
            $changed = true;
        }

        if ( $changed ) {
            update_option( 'tmpmp_pages', $stored );
            update_option( 'tmpmp_settings', $settings );
        }

        // Flush rewrite rules so the new page slugs work immediately
        flush_rewrite_rules();
    }

    /**
     * Returns page data (ID + URL) for admin display.
     * @return array<string, array{id:int, url:string, title:string, shortcode:string}>
     */
    public static function get_page_info() : array {
        $stored = get_option( 'tmpmp_pages', [] );
        $result = [];

        foreach ( self::$pages as $key => $cfg ) {
            $id  = (int) ( $stored[ $key ] ?? 0 );
            $url = $id ? (string) get_permalink( $id ) : '';
            $result[ $key ] = [
                'id'        => $id,
                'url'       => $url,
                'title'     => $cfg['title'],
                'shortcode' => $cfg['shortcode'],
            ];
        }

        return $result;
    }

    /**
     * Delete all auto-created pages (called from uninstall or admin reset).
     */
    public static function delete_pages() : void {
        $stored = get_option( 'tmpmp_pages', [] );
        foreach ( $stored as $id ) {
            wp_delete_post( (int) $id, true );
        }
        delete_option( 'tmpmp_pages' );
    }

    /**
     * Run DB migrations for existing installs.
     * Safe to call on every plugins_loaded — each migration checks before altering.
     */
    public static function maybe_run_migrations() : void {
        global $wpdb;

        // ── Addresses table: add is_permanent column ──────────────────────────
        $col = $wpdb->get_results( $wpdb->prepare(
            "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
              WHERE TABLE_SCHEMA = %s
                AND TABLE_NAME   = %s
                AND COLUMN_NAME  = 'is_permanent'",
            DB_NAME,
            $wpdb->prefix . 'tmpmp_addresses'
        ) );
        if ( empty( $col ) ) {
            $wpdb->query(
                "ALTER TABLE {$wpdb->prefix}tmpmp_addresses
                   ADD COLUMN is_permanent TINYINT(1) NOT NULL DEFAULT 0 AFTER is_private,
                   ADD KEY is_permanent (is_permanent)"
            );
        }

        // ── Plans table: add has_permanent_inbox + max_permanent_inboxes ─────
        $col2 = $wpdb->get_results( $wpdb->prepare(
            "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
              WHERE TABLE_SCHEMA = %s
                AND TABLE_NAME   = %s
                AND COLUMN_NAME  = 'has_permanent_inbox'",
            DB_NAME,
            $wpdb->prefix . 'tmpmp_plans'
        ) );
        if ( empty( $col2 ) ) {
            $wpdb->query(
                "ALTER TABLE {$wpdb->prefix}tmpmp_plans
                   ADD COLUMN has_permanent_inbox   TINYINT(1) NOT NULL DEFAULT 0,
                   ADD COLUMN max_permanent_inboxes INT        NOT NULL DEFAULT 0"
            );

            // Set defaults for existing plan rows
            $plan_limits = [
                'free'     => [ 'has_permanent_inbox' => 0, 'max_permanent_inboxes' => 0 ],
                'starter'  => [ 'has_permanent_inbox' => 1, 'max_permanent_inboxes' => 1 ],
                'pro'      => [ 'has_permanent_inbox' => 1, 'max_permanent_inboxes' => 5 ],
                'business' => [ 'has_permanent_inbox' => 1, 'max_permanent_inboxes' => -1 ],
            ];
            foreach ( $plan_limits as $slug => $vals ) {
                $wpdb->update(
                    $wpdb->prefix . 'tmpmp_plans',
                    $vals,
                    [ 'slug' => $slug ]
                );
            }
        }
    }
}
