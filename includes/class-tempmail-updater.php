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

        // ── Plugin row notice (works on every install, no remote server) ─────
        add_action( 'after_plugin_row_' . $this->plugin_file,
                    [ $this, 'show_plugin_row_notice' ], 10, 3 );
        add_action( 'wp_ajax_tmpmp_dismiss_row_notice', [ $this, 'ajax_dismiss_row_notice' ] );
        add_action( 'admin_head', [ $this, 'row_notice_styles' ] );

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
                'changelog'   => $remote->changelog   ?? '<p>See the full changelog inside the plugin admin panel.</p>',
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

    /* ══════════════════════════════════════════════════════════════════════════
     * PART 2 — Plugin row "What's New" notice (works on every install)
     * ══════════════════════════════════════════════════════════════════════════ */

    /** Output the notice row below the plugin entry in Plugins list */
    public function show_plugin_row_notice( string $plugin_file, array $plugin_data, string $status ) : void {
        // Only show on the plugins list page
        $screen = get_current_screen();
        if ( ! $screen || $screen->id !== 'plugins' ) return;

        // Already dismissed for this version?
        if ( get_option( self::ROW_NOTICE_OPT ) === TMPMP_VERSION ) return;

        // Get the latest changelog entry
        $log     = TempMail_Changelog::get_changelog();
        $entry   = $log[ TMPMP_VERSION ] ?? null;
        if ( ! $entry ) return;

        $features = array_slice( $entry['features'] ?? [], 0, 3 );  // show top 3
        $fixes    = array_slice( $entry['bugfixes'] ?? [], 0, 2 );  // show top 2
        $subtitle = $entry['subtitle'] ?? strtoupper( $entry['label'] ?? '' );
        $date     = $entry['date']     ?? '';

        $changelog_url = admin_url( 'admin.php?page=tmpmp-changelog' );
        $nonce         = wp_create_nonce( 'tmpmp_row_notice_nonce' );

        // Count active columns in the plugins table (WP uses 3 or 4)
        $cols = 4;
        ?>
        <tr class="plugin-update-tr active tmpmp-row-notice-tr" id="tmpmp-row-notice-<?php echo esc_attr( TMPMP_VERSION ); ?>">
            <td colspan="<?php echo (int) $cols; ?>" class="plugin-update colspanchange">
                <div class="update-message notice inline notice-warning notice-alt">
                    <div class="tmpmp-rn-inner">
                        <span class="tmpmp-rn-icon">🎉</span>
                        <div class="tmpmp-rn-body">
                            <p class="tmpmp-rn-title">
                                <?php
                                printf(
                                    /* translators: %s = version number */
                                    esc_html__( 'TempMail Pro v%s is installed — here\'s what\'s new:', 'tempmail-pro' ),
                                    esc_html( TMPMP_VERSION )
                                );
                                ?>
                                <span class="tmpmp-rn-badge"><?php echo esc_html( $subtitle ); ?></span>
                                <?php if ( $date ) : ?>
                                    <span class="tmpmp-rn-date"><?php echo esc_html( $date ); ?></span>
                                <?php endif; ?>
                            </p>
                            <ul class="tmpmp-rn-list">
                                <?php foreach ( $features as $f ) : ?>
                                    <li class="tmpmp-rn-feature">
                                        <span class="tmpmp-rn-dot tmpmp-rn-dot--feature">★ <?php esc_html_e('New','tempmail-pro'); ?></span>
                                        <?php echo wp_kses( $f, [ 'code' => [], 'strong' => [], 'em' => [] ] ); ?>
                                    </li>
                                <?php endforeach; ?>
                                <?php foreach ( $fixes as $f ) : ?>
                                    <li class="tmpmp-rn-feature">
                                        <span class="tmpmp-rn-dot tmpmp-rn-dot--fix">✓ <?php esc_html_e('Fix','tempmail-pro'); ?></span>
                                        <?php echo wp_kses( $f, [ 'code' => [], 'strong' => [], 'em' => [] ] ); ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                            <p class="tmpmp-rn-actions">
                                <a href="<?php echo esc_url( $changelog_url ); ?>" class="tmpmp-rn-btn">
                                    📄 <?php esc_html_e( 'View Full Changelog', 'tempmail-pro' ); ?>
                                </a>
                                <button type="button" class="tmpmp-rn-dismiss button-link"
                                    data-nonce="<?php echo esc_attr( $nonce ); ?>"
                                    data-notice="tmpmp-row-notice-<?php echo esc_attr( TMPMP_VERSION ); ?>">
                                    <?php esc_html_e( 'Dismiss', 'tempmail-pro' ); ?>
                                </button>
                            </p>
                        </div>
                    </div>
                </div>
            </td>
        </tr>
        <script>
        (function(){
            var btn = document.querySelector('.tmpmp-rn-dismiss[data-notice="tmpmp-row-notice-<?php echo esc_js(TMPMP_VERSION); ?>"]');
            if(!btn) return;
            btn.addEventListener('click', function(){
                var row = document.getElementById(this.dataset.notice);
                if(row){ row.style.opacity='0'; row.style.transition='opacity .2s'; setTimeout(function(){ row.remove(); },220); }
                fetch('<?php echo esc_url( admin_url('admin-ajax.php') ); ?>',{
                    method:'POST',
                    headers:{'Content-Type':'application/x-www-form-urlencoded'},
                    body:'action=tmpmp_dismiss_row_notice&nonce='+encodeURIComponent(this.dataset.nonce)
                });
            });
        })();
        </script>
        <?php
    }

    /** AJAX: mark the row notice as dismissed for the current version */
    public function ajax_dismiss_row_notice() : void {
        check_ajax_referer( 'tmpmp_row_notice_nonce', 'nonce' );
        if ( ! current_user_can( 'activate_plugins' ) ) wp_send_json_error( [], 403 );
        update_option( self::ROW_NOTICE_OPT, TMPMP_VERSION );
        wp_send_json_success();
    }

    /** Inline styles for the row notice — only on plugins page */
    public function row_notice_styles() : void {
        $screen = get_current_screen();
        if ( ! $screen || $screen->id !== 'plugins' ) return;
        ?>
        <style>
        .tmpmp-row-notice-tr .notice { margin:0; border-radius:0; }
        .tmpmp-rn-inner  { display:flex; align-items:flex-start; gap:10px; padding:10px 14px; }
        .tmpmp-rn-icon   { font-size:22px; line-height:1; flex-shrink:0; margin-top:2px; }
        .tmpmp-rn-body   { flex:1; min-width:0; }
        .tmpmp-rn-title  { font-size:13px; font-weight:700; color:#1e293b; margin:0 0 6px; display:flex; align-items:center; flex-wrap:wrap; gap:8px; }
        .tmpmp-rn-badge  { background:#ede9fe; color:#5b21b6; font-size:10px; font-weight:800; letter-spacing:.7px; padding:2px 8px; border-radius:4px; text-transform:uppercase; }
        .tmpmp-rn-date   { font-size:11px; color:#94a3b8; font-weight:400; }
        .tmpmp-rn-list   { margin:0 0 8px 0; padding:0; list-style:none; display:flex; flex-direction:column; gap:4px; }
        .tmpmp-rn-feature{ font-size:12px; color:#374151; display:flex; align-items:flex-start; gap:7px; line-height:1.5; }
        .tmpmp-rn-dot    { flex-shrink:0; font-size:10px; font-weight:800; padding:1px 7px; border-radius:4px; text-transform:uppercase; margin-top:1px; letter-spacing:.4px; }
        .tmpmp-rn-dot--feature { background:#dcfce7; color:#15803d; }
        .tmpmp-rn-dot--fix     { background:#fee2e2; color:#b91c1c; }
        .tmpmp-rn-actions{ margin:6px 0 0; display:flex; align-items:center; gap:14px; }
        .tmpmp-rn-btn    { display:inline-flex; align-items:center; gap:5px; font-size:12px; font-weight:600; color:#4f46e5; text-decoration:none; }
        .tmpmp-rn-btn:hover { color:#3730a3; text-decoration:underline; }
        .tmpmp-rn-dismiss{ font-size:12px; color:#94a3b8; cursor:pointer; background:none; border:none; text-decoration:underline; padding:0; }
        .tmpmp-rn-dismiss:hover { color:#475569; }
        </style>
        <?php
    }
}
