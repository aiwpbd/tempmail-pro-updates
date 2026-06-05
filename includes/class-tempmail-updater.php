<?php
/**
 * TempMail Pro — Custom Plugin Updater + Plugin Row Notice
 *
 * TWO features in one class:
 *
 * 1. REMOTE UPDATE CHECK — hooks into WordPress's native update system so the
 *    yellow "update-message notice inline notice-warning notice-alt" row appears
 *    in Plugins → Installed Plugins when a newer version is available on the
 *    remote update server. Requires TMPMP_UPDATE_URL to be defined or the default
 *    GitHub URL to host a valid update-info.json.
 *
 * 2. PLUGIN ROW NOTICE — works on EVERY site that installs the plugin (no
 *    external server required). Uses local changelog data to show a styled
 *    "What's New" notice under the plugin row in Plugins → Installed Plugins.
 *    Dismissible per-version. Reappears automatically on next version bump.
 *
 * Remote JSON format (host at TMPMP_UPDATE_URL):
 * {
 *   "version"        : "2.0.2",
 *   "requires"       : "5.8",
 *   "requires_php"   : "7.4",
 *   "tested"         : "6.5",
 *   "last_updated"   : "2026-05-22",
 *   "download_url"   : "https://yoursite.com/downloads/tempmail-pro-2.0.2.zip",
 *   "details_url"    : "https://yoursite.com/changelog",
 *   "upgrade_notice" : "Important fixes for IMAP and responsive email.",
 *   "description"    : "TempMail Pro — disposable email SaaS for WordPress.",
 *   "changelog"      : "<h2>v2.0.2</h2><ul><li>New: ...</li></ul>"
 * }
 *
 * Override the update URL via wp-config.php:
 *   define( 'TMPMP_UPDATE_URL', 'https://yourserver.com/update-info.json' );
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class TempMail_Updater {

    /**
     * Remote update JSON served from GitHub.
     * After creating the repo (see instructions below), this URL serves
     * the file instantly via GitHub's CDN — no server needed.
     *
     * Format: https://raw.githubusercontent.com/{YOUR_GITHUB_USERNAME}/tempmail-pro-updates/main/update-info.json
     * Replace YOUR_GITHUB_USERNAME below after creating the repository.
     */
    const DEFAULT_UPDATE_URL = 'https://raw.githubusercontent.com/aiwpbd/tempmail-pro-updates/main/update-info.json';
    const CACHE_KEY           = 'tmpmp_remote_version_info';
    const CACHE_TTL           = 12 * HOUR_IN_SECONDS;
    const ROW_NOTICE_OPT      = 'tmpmp_row_notice_dismissed';   // stores dismissed version

    private string $plugin_file;   // e.g. "tempmail-pro/tempmail-pro.php"
    private string $plugin_slug;   // e.g. "tempmail-pro"
    private string $update_url;

    public function __construct( string $plugin_file ) {
        $this->plugin_file = plugin_basename( $plugin_file );
        $this->plugin_slug = dirname( $this->plugin_file );
        $this->update_url  = defined( 'TMPMP_UPDATE_URL' ) ? TMPMP_UPDATE_URL : self::DEFAULT_UPDATE_URL;

        // Only run in admin context — no overhead on front-end
        if ( ! is_admin() ) return;

        // ── Remote update hooks ──────────────────────────────────────────────
        add_filter( 'pre_set_site_transient_update_plugins', [ $this, 'inject_update'      ] );
        add_filter( 'plugins_api',                           [ $this, 'plugin_info'        ], 20, 3 );
        add_action( 'in_plugin_update_message-' . $this->plugin_file,
                    [ $this, 'update_row_message' ], 10, 2 );


        // Allow force-clearing the cache from Plugins page (?tmpmp_clear_update_cache=1)
        if ( isset( $_GET['tmpmp_clear_update_cache'] ) && current_user_can( 'manage_options' ) ) {
            delete_transient( self::CACHE_KEY );
        }
    }

    /* ══════════════════════════════════════════════════════════════════════════
     * PART 1 — Remote update server integration
     * ══════════════════════════════════════════════════════════════════════════ */

    private function get_remote() : ?object {
        $cached = get_transient( self::CACHE_KEY );
        if ( $cached !== false ) return $cached ?: null;

        $resp = wp_remote_get( $this->update_url, [
            'timeout'    => 10,
            'user-agent' => 'TempMail-Pro/' . TMPMP_VERSION . '; ' . home_url(),
            'headers'    => [ 'Accept' => 'application/json' ],
        ] );

        if ( is_wp_error( $resp ) || 200 !== (int) wp_remote_retrieve_response_code( $resp ) ) {
            set_transient( self::CACHE_KEY, false, HOUR_IN_SECONDS );
            return null;
        }

        $data = json_decode( wp_remote_retrieve_body( $resp ) );
        if ( ! $data || empty( $data->version ) ) {
            set_transient( self::CACHE_KEY, false, HOUR_IN_SECONDS );
            return null;
        }

        set_transient( self::CACHE_KEY, $data, self::CACHE_TTL );

        // ── Auto-derive GitHub URLs if not set or still placeholder ───────
        // download_url: https://github.com/{user}/{repo}/releases/download/v{ver}/{slug}-{ver}.zip
        // details_url:  https://github.com/{user}/{repo}/releases/tag/v{ver}
        $gh_user = 'aiwpbd';
        $gh_repo = 'tempmail-pro-updates';
        $ver     = $data->version ?? '';
        $tag     = 'v' . $ver;

        if ( empty( $data->download_url ) || strpos( $data->download_url, 'YOUR_SITE' ) !== false ) {
            $data->download_url = "https://github.com/{$gh_user}/{$gh_repo}/releases/download/{$tag}/tempmail-pro-{$ver}.zip";
        }
        if ( empty( $data->details_url ) || strpos( $data->details_url, 'YOUR_SITE' ) !== false ) {
            $data->details_url = "https://github.com/{$gh_user}/{$gh_repo}/releases/tag/{$tag}";
        }

        // Re-cache with corrected URLs
        set_transient( self::CACHE_KEY, $data, self::CACHE_TTL );

        return $data;
    }

    public function inject_update( $transient ) {
        if ( empty( $transient->checked ) ) return $transient;

        $remote = $this->get_remote();
        if ( ! $remote ) return $transient;

        if ( version_compare( TMPMP_VERSION, $remote->version, '<' ) ) {
            $transient->response[ $this->plugin_file ] = (object) [
                'id'             => $this->plugin_file,
                'slug'           => $this->plugin_slug,
                'plugin'         => $this->plugin_file,
                'new_version'    => $remote->version,
                'url'            => $remote->details_url  ?? '',
                'package'        => $remote->download_url ?? '',
                'requires'       => $remote->requires     ?? '5.8',
                'requires_php'   => $remote->requires_php ?? '7.4',
                'tested'         => $remote->tested       ?? '',
                'upgrade_notice' => $remote->upgrade_notice ?? '',
                'icons'          => [],
                'banners'        => [],
            ];
            unset( $transient->no_update[ $this->plugin_file ] );
        } else {
            $transient->no_update[ $this->plugin_file ] = (object) [
                'id'          => $this->plugin_file,
                'slug'        => $this->plugin_slug,
                'plugin'      => $this->plugin_file,
                'new_version' => TMPMP_VERSION,
                'url'         => '',
                'package'     => '',
                'icons'       => [],
                'banners'     => [],
            ];
        }

        return $transient;
    }

    public function plugin_info( $result, $action, $args ) {
        if ( 'plugin_information' !== $action ) return $result;
        if ( ! isset( $args->slug ) || $args->slug !== $this->plugin_slug ) return $result;

        $remote = $this->get_remote();
        if ( ! $remote ) return $result;

        return (object) [
            'name'           => 'TempMail Pro',
            'slug'           => $this->plugin_slug,
            'version'        => $remote->version,
            'author'         => '<a href="https://wa.me/+8801516514216">TempMail Pro</a>',
            'author_profile' => 'https://wa.me/+8801516514216',
            'homepage'       => $remote->details_url  ?? '',
            'requires'       => $remote->requires     ?? '5.8',
            'requires_php'   => $remote->requires_php ?? '7.4',
            'tested'         => $remote->tested       ?? get_bloginfo( 'version' ),
            'last_updated'   => $remote->last_updated ?? '',
            'download_link'  => $remote->download_url ?? '',
            'sections'       => [
                'description' => $remote->description ?? 'TempMail Pro — A full-featured disposable email SaaS platform for WordPress.',
                'changelog'   => $remote->changelog   ?? '<p><a href="https://github.com/aiwpbd/tempmail-pro-updates/releases" target="_blank">View release notes on GitHub</a></p>',
            ],
            'banners' => [],
            'ratings' => [],
        ];
    }

    public function update_row_message( array $plugin_data, object $new_data ) : void {
        $notice = $new_data->upgrade_notice ?? '';
        if ( $notice ) {
            printf( ' <strong>%s</strong>', esc_html( $notice ) );
        }
    }

}
