<?php
/**
 * TempMail Pro — GitHub Update & Notification System
 *
 * Checks GitHub Releases API for plugin updates and fetches
 * notifications.json for custom admin notices.
 *
 * GitHub Repository: https://github.com/aiwpbd/tempmail-pro-updates
 *
 * ── How it works ─────────────────────────────────────────────────────────────
 * 1. Checks GitHub Releases API for latest release tag (e.g. v2.0.2)
 * 2. If newer than installed → injects into WP update transient (yellow row)
 * 3. Fetches notifications.json → shows admin_notices banner on all admin pages
 * 4. All results cached for 12 hours to avoid GitHub API rate limits
 * ─────────────────────────────────────────────────────────────────────────────
 *
 * ── GitHub repository structure ──────────────────────────────────────────────
 *   aiwpbd/tempmail-pro-updates/
 *   ├── notifications.json        ← controls admin notice banners
 *   ├── update-info.json          ← fallback update info (existing)
 *   └── README.md
 *
 * ── notifications.json format ────────────────────────────────────────────────
 * {
 *   "enabled"     : true,
 *   "type"        : "update-message",
 *   "class"       : "notice notice-warning notice-alt",
 *   "message"     : "TempMail Pro v2.0.2 is available! <a href='...'>Update now</a>",
 *   "version"     : "2.0.2",
 *   "dismissible" : true
 * }
 *
 * ── GitHub Releases API ───────────────────────────────────────────────────────
 * Create a release on GitHub → tag it v2.0.2 → attach plugin ZIP
 * The API automatically exposes: tag_name, assets[0].browser_download_url
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class TempMail_GitHub_Updater {

    /* ── Config ─────────────────────────────────────────────────────────── */
    const GITHUB_USER    = 'aiwpbd';
    const GITHUB_REPO    = 'tempmail-pro-updates';
    const RELEASES_API   = 'https://api.github.com/repos/aiwpbd/tempmail-pro-updates/releases/latest';
    const NOTIF_URL      = 'https://raw.githubusercontent.com/aiwpbd/tempmail-pro-updates/main/notifications.json';
    const CACHE_RELEASE  = 'tmpmp_gh_release_info';
    const CACHE_NOTIF    = 'tmpmp_gh_notif_info';
    const CACHE_TTL      = 12 * HOUR_IN_SECONDS;
    const NOTIF_OPT      = 'tmpmp_gh_notif_dismissed'; // stores dismissed version

    private string $plugin_file;
    private string $plugin_slug;

    public function __construct( string $plugin_file ) {
        $this->plugin_file = plugin_basename( $plugin_file );
        $this->plugin_slug = dirname( $this->plugin_file );

        if ( ! is_admin() ) return;

        /* ── Update hooks ── */
        add_filter( 'pre_set_site_transient_update_plugins', [ $this, 'inject_update'    ] );
        add_filter( 'plugins_api',                           [ $this, 'plugin_info'      ], 20, 3 );
        add_action( 'in_plugin_update_message-' . $this->plugin_file,
                    [ $this, 'update_row_message' ], 10, 2 );

        /* ── Notification hooks ── */
        add_action( 'admin_notices',  [ $this, 'show_admin_notice'   ] );
        add_action( 'admin_head',     [ $this, 'notice_styles'       ] );
        add_action( 'wp_ajax_tmpmp_gh_dismiss_notice', [ $this, 'ajax_dismiss_notice' ] );

        /* ── Cache busting ── */
        if ( isset( $_GET['tmpmp_clear_update_cache'] ) && current_user_can( 'manage_options' ) ) {
            delete_transient( self::CACHE_RELEASE );
            delete_transient( self::CACHE_NOTIF );
        }
    }

    /* ══════════════════════════════════════════════════════════════════════
     * 1. GitHub Releases API — fetch latest release
     * ══════════════════════════════════════════════════════════════════════ */

    private function get_release() : ?object {
        $cached = get_transient( self::CACHE_RELEASE );
        if ( $cached !== false ) return $cached ?: null;

        $resp = wp_remote_get( self::RELEASES_API, [
            'timeout'    => 10,
            'user-agent' => 'TempMail-Pro/' . TMPMP_VERSION . '; ' . home_url(),
            'headers'    => [
                'Accept' => 'application/vnd.github+json',
                'X-GitHub-Api-Version' => '2022-11-28',
            ],
        ] );

        if ( is_wp_error( $resp ) || 200 !== (int) wp_remote_retrieve_response_code( $resp ) ) {
            set_transient( self::CACHE_RELEASE, false, HOUR_IN_SECONDS );
            return null;
        }

        $data = json_decode( wp_remote_retrieve_body( $resp ) );
        if ( ! $data || empty( $data->tag_name ) ) {
            set_transient( self::CACHE_RELEASE, false, HOUR_IN_SECONDS );
            return null;
        }

        set_transient( self::CACHE_RELEASE, $data, self::CACHE_TTL );
        return $data;
    }

    /** Strip leading "v" from tag like "v2.0.2" → "2.0.2" */
    private static function clean_version( string $tag ) : string {
        return ltrim( $tag, 'vV' );
    }

    /* ══════════════════════════════════════════════════════════════════════
     * 2. Inject update into WordPress native transient
     * ══════════════════════════════════════════════════════════════════════ */

    public function inject_update( $transient ) {
        if ( empty( $transient->checked ) ) return $transient;

        $release = $this->get_release();
        if ( ! $release ) return $transient;

        $remote_ver  = self::clean_version( $release->tag_name );
        $download    = $release->assets[0]->browser_download_url
                       ?? $release->zipball_url
                       ?? '';

        if ( version_compare( TMPMP_VERSION, $remote_ver, '<' ) ) {
            $transient->response[ $this->plugin_file ] = (object) [
                'id'           => $this->plugin_file,
                'slug'         => $this->plugin_slug,
                'plugin'       => $this->plugin_file,
                'new_version'  => $remote_ver,
                'url'          => 'https://github.com/' . self::GITHUB_USER . '/' . self::GITHUB_REPO . '/releases',
                'package'      => $download,
                'requires'     => '5.8',
                'requires_php' => '7.4',
                'tested'       => get_bloginfo( 'version' ),
                'icons'        => [],
                'banners'      => [],
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

    /* ══════════════════════════════════════════════════════════════════════
     * 3. Plugin details popup ("View version X details")
     * ══════════════════════════════════════════════════════════════════════ */

    public function plugin_info( $result, $action, $args ) {
        if ( 'plugin_information' !== $action ) return $result;
        if ( ! isset( $args->slug ) || $args->slug !== $this->plugin_slug ) return $result;

        $release = $this->get_release();
        if ( ! $release ) return $result;

        $remote_ver = self::clean_version( $release->tag_name );
        $download   = $release->assets[0]->browser_download_url ?? $release->zipball_url ?? '';
        $changelog  = $this->markdown_to_html( $release->body ?? '' );

        return (object) [
            'name'           => 'TempMail Pro',
            'slug'           => $this->plugin_slug,
            'version'        => $remote_ver,
            'author'         => '<a href="https://wa.me/+8801516514216">TempMail Pro</a>',
            'author_profile' => 'https://wa.me/+8801516514216',
            'homepage'       => 'https://github.com/' . self::GITHUB_USER . '/' . self::GITHUB_REPO,
            'requires'       => '5.8',
            'requires_php'   => '7.4',
            'tested'         => get_bloginfo( 'version' ),
            'last_updated'   => $release->published_at ?? '',
            'download_link'  => $download,
            'sections'       => [
                'description' => 'TempMail Pro — A full-featured temporary/disposable email SaaS platform for WordPress.',
                'changelog'   => $changelog ?: '<p>See <a href="' . esc_url('https://github.com/' . self::GITHUB_USER . '/' . self::GITHUB_REPO . '/releases') . '" target="_blank">GitHub Releases</a> for full changelog.</p>',
            ],
            'banners' => [],
            'ratings' => [],
        ];
    }

    public function update_row_message( array $plugin_data, object $new_data ) : void {
        $release = $this->get_release();
        if ( $release && ! empty( $release->name ) ) {
            printf( ' — <strong>%s</strong>', esc_html( $release->name ) );
        }
    }

    /* ══════════════════════════════════════════════════════════════════════
     * 4. notifications.json — custom admin notice
     * ══════════════════════════════════════════════════════════════════════ */

    private function get_notification() : ?object {
        $cached = get_transient( self::CACHE_NOTIF );
        if ( $cached !== false ) return $cached ?: null;

        $resp = wp_remote_get( self::NOTIF_URL, [
            'timeout'    => 8,
            'user-agent' => 'TempMail-Pro/' . TMPMP_VERSION,
            'headers'    => [ 'Accept' => 'application/json' ],
        ] );

        if ( is_wp_error( $resp ) || 200 !== (int) wp_remote_retrieve_response_code( $resp ) ) {
            set_transient( self::CACHE_NOTIF, false, HOUR_IN_SECONDS );
            return null;
        }

        $data = json_decode( wp_remote_retrieve_body( $resp ) );
        if ( ! $data || empty( $data->message ) ) {
            set_transient( self::CACHE_NOTIF, false, HOUR_IN_SECONDS );
            return null;
        }

        set_transient( self::CACHE_NOTIF, $data, self::CACHE_TTL );
        return $data;
    }

    public function show_admin_notice() : void {
        if ( ! current_user_can( 'manage_options' ) ) return;

        $notif = $this->get_notification();
        if ( ! $notif ) return;
        if ( empty( $notif->enabled ) ) return;

        // Check if dismissed for this notice version
        $dismiss_key = $notif->version ?? $notif->message;
        if ( get_option( self::NOTIF_OPT ) === md5( $dismiss_key ) ) return;

        $class       = sanitize_html_class( $notif->class ?? 'notice notice-warning notice-alt' );
        $type        = $notif->type ?? 'update-message';
        $message     = wp_kses( $notif->message ?? '', [
            'a'      => [ 'href' => [], 'target' => [], 'class' => [] ],
            'strong' => [], 'em' => [], 'code' => [], 'br' => [],
        ] );
        $dismissible = ! empty( $notif->dismissible );
        $nonce       = wp_create_nonce( 'tmpmp_gh_dismiss_nonce' );
        $notice_id   = 'tmpmp-gh-notice-' . md5( $dismiss_key );
        ?>
        <div id="<?php echo esc_attr( $notice_id ); ?>"
             class="<?php echo esc_attr( $class ); ?> tmpmp-gh-notice"
             data-type="<?php echo esc_attr( $type ); ?>">
            <div class="tmpmp-ghn-inner">
                <span class="tmpmp-ghn-icon">🔔</span>
                <p class="tmpmp-ghn-msg"><?php echo $message; ?></p>
                <?php if ( $dismissible ) : ?>
                <button type="button" class="notice-dismiss tmpmp-gh-dismiss"
                    data-nonce="<?php echo esc_attr($nonce); ?>"
                    data-notice="<?php echo esc_attr($notice_id); ?>"
                    data-key="<?php echo esc_attr( md5($dismiss_key) ); ?>">
                    <span class="screen-reader-text"><?php esc_html_e('Dismiss this notice','tempmail-pro'); ?></span>
                </button>
                <?php endif; ?>
            </div>
        </div>
        <script>
        (function(){
            var btn = document.querySelector('.tmpmp-gh-dismiss[data-notice="<?php echo esc_js($notice_id); ?>"]');
            if(!btn) return;
            btn.addEventListener('click',function(){
                var el = document.getElementById(this.dataset.notice);
                if(el){ el.style.opacity='0'; el.style.transition='opacity .2s'; setTimeout(function(){ el.remove(); },220); }
                fetch('<?php echo esc_url(admin_url('admin-ajax.php')); ?>',{
                    method:'POST',
                    headers:{'Content-Type':'application/x-www-form-urlencoded'},
                    body:'action=tmpmp_gh_dismiss_notice&nonce='+encodeURIComponent(this.dataset.nonce)+'&key='+encodeURIComponent(this.dataset.key)
                });
            });
        })();
        </script>
        <?php
    }

    public function ajax_dismiss_notice() : void {
        check_ajax_referer( 'tmpmp_gh_dismiss_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( [], 403 );
        $key = sanitize_text_field( $_POST['key'] ?? '' );
        if ( $key ) update_option( self::NOTIF_OPT, $key );
        wp_send_json_success();
    }

    public function notice_styles() : void {
        ?>
        <style>
        .tmpmp-gh-notice { border-left-color: #f59e0b !important; }
        .tmpmp-ghn-inner { display:flex; align-items:center; gap:10px; padding:8px 36px 8px 12px; }
        .tmpmp-ghn-icon  { font-size:20px; flex-shrink:0; }
        .tmpmp-ghn-msg   { margin:0; font-size:13px; color:#1e293b; flex:1; }
        .tmpmp-ghn-msg a { color:#6366f1; font-weight:600; }
        .tmpmp-ghn-msg a:hover { color:#4f46e5; }
        </style>
        <?php
    }

    /* ══════════════════════════════════════════════════════════════════════
     * Helper — basic Markdown → HTML for GitHub release bodies
     * ══════════════════════════════════════════════════════════════════════ */

    private function markdown_to_html( string $md ) : string {
        if ( ! $md ) return '';
        $html = esc_html( $md );
        // Headers
        $html = preg_replace('/^### (.+)$/m', '<h3>$1</h3>', $html);
        $html = preg_replace('/^## (.+)$/m',  '<h2>$1</h2>', $html);
        $html = preg_replace('/^# (.+)$/m',   '<h1>$1</h1>', $html);
        // Bold / italic
        $html = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $html);
        $html = preg_replace('/\*(.+?)\*/',     '<em>$1</em>', $html);
        // Code
        $html = preg_replace('/`(.+?)`/', '<code>$1</code>', $html);
        // List items
        $html = preg_replace('/^- (.+)$/m', '<li>$1</li>', $html);
        $html = preg_replace('/(<li>.*<\/li>)/s', '<ul>$1</ul>', $html);
        // Line breaks
        $html = nl2br( $html );
        return $html;
    }
}
