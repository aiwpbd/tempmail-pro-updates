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
 *   "version"     : "2.0.2",
 *   "dismissible" : true,
 *   "bg"          : "warning",        ← warning | info | success | promo
 *   "icon"        : "🚀",             ← any emoji (optional)
 *   "title"       : "Update Available!",
 *   "message"     : "TempMail Pro v2.0.2 is ready.",
 *   "cta_text"    : "Update Now",      ← button label (optional)
 *   "cta_url"     : "https://...",     ← button URL (optional)
 *   "class"       : "notice-warning"  ← WP class fallback (optional)
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

        /* ── SSL-safe download (fixes cURL error 35 on old hosts) ── */
        add_filter( 'upgrader_pre_download', [ $this, 'pre_download_fix' ], 10, 3 );

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

        // Show only once — dismissed = stored md5 of version/message key
        $dismiss_key = $notif->version ?? $notif->message ?? '';
        if ( get_option( self::NOTIF_OPT ) === md5( $dismiss_key ) ) return;

        // ── Data extraction ──────────────────────────────────────────────
        $title       = sanitize_text_field( $notif->title ?? '' );
        $icon        = sanitize_text_field( $notif->icon  ?? '🔔' );
        $bg          = sanitize_key( $notif->bg ?? 'warning' ); // warning|info|success|promo
        $message     = wp_kses( $notif->message ?? '', [
            'a'      => [ 'href' => [], 'target' => [], 'class' => [] ],
            'strong' => [], 'em' => [], 'code' => [], 'br' => [],
        ] );
        $cta_text    = sanitize_text_field( $notif->cta_text ?? '' );
        $cta_url     = esc_url( $notif->cta_url ?? '' );
        $dismissible = ! empty( $notif->dismissible );
        $nonce       = wp_create_nonce( 'tmpmp_gh_dismiss_nonce' );
        $notice_id   = 'tmpmp-gh-notice-' . md5( $dismiss_key );
        ?>
        <div id="<?php echo esc_attr( $notice_id ); ?>"
             class="tmpmp-ghn-wrap tmpmp-ghn-bg--<?php echo esc_attr($bg); ?>"
             role="alert" aria-live="polite">

            <!-- Accent stripe -->
            <div class="tmpmp-ghn-stripe" aria-hidden="true"></div>

            <!-- Icon -->
            <div class="tmpmp-ghn-icon-wrap" aria-hidden="true">
                <span class="tmpmp-ghn-icon"><?php echo esc_html( $icon ); ?></span>
            </div>

            <!-- Content -->
            <div class="tmpmp-ghn-content">
                <?php if ( $title ) : ?>
                <p class="tmpmp-ghn-title"><?php echo esc_html( $title ); ?></p>
                <?php endif; ?>
                <?php if ( $message ) : ?>
                <p class="tmpmp-ghn-msg"><?php echo $message; ?></p>
                <?php endif; ?>
            </div>

            <!-- CTA button -->
            <?php if ( $cta_url && $cta_text ) : ?>
            <div class="tmpmp-ghn-actions">
                <a href="<?php echo esc_url( $cta_url ); ?>" class="tmpmp-ghn-cta" target="_blank" rel="noopener">
                    <?php echo esc_html( $cta_text ); ?>
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path d="M7 17L17 7M17 7H7M17 7v10"/></svg>
                </a>
            </div>
            <?php endif; ?>

            <!-- Dismiss -->
            <?php if ( $dismissible ) : ?>
            <button type="button"
                class="tmpmp-ghn-dismiss"
                data-nonce="<?php echo esc_attr( $nonce ); ?>"
                data-notice="<?php echo esc_attr( $notice_id ); ?>"
                data-key="<?php echo esc_attr( md5( $dismiss_key ) ); ?>"
                aria-label="<?php esc_attr_e( 'Dismiss this notice', 'tempmail-pro' ); ?>">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path d="M18 6L6 18M6 6l12 12"/></svg>
            </button>
            <?php endif; ?>

        </div>
        <script>
        (function(){
            var wrap = document.getElementById('<?php echo esc_js( $notice_id ); ?>');
            if ( ! wrap ) return;

            // Entrance animation
            wrap.style.opacity = '0';
            wrap.style.transform = 'translateY(-6px)';
            requestAnimationFrame(function(){
                wrap.style.transition = 'opacity .35s ease, transform .35s ease';
                wrap.style.opacity = '1';
                wrap.style.transform = 'translateY(0)';
            });

            // Dismiss handler
            var btn = wrap.querySelector('.tmpmp-ghn-dismiss');
            if ( ! btn ) return;
            btn.addEventListener('click', function(){
                wrap.style.transition = 'opacity .25s ease, transform .25s ease, max-height .3s ease, margin .3s ease';
                wrap.style.opacity = '0';
                wrap.style.transform = 'translateY(-4px)';
                wrap.style.maxHeight = wrap.offsetHeight + 'px';
                setTimeout(function(){
                    wrap.style.maxHeight = '0';
                    wrap.style.marginBottom = '0';
                    wrap.style.overflow = 'hidden';
                }, 50);
                setTimeout(function(){ wrap.remove(); }, 350);

                fetch('<?php echo esc_url( admin_url('admin-ajax.php') ); ?>',{
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'action=tmpmp_gh_dismiss_notice'
                        + '&nonce='  + encodeURIComponent( btn.dataset.nonce )
                        + '&key='   + encodeURIComponent( btn.dataset.key ),
                }).catch(function(){});
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
        <style id="tmpmp-ghn-styles">
        /* ── TempMail Pro Admin Notification Bar ──────────────────────────── */
        .tmpmp-ghn-wrap {
            display: flex;
            align-items: center;
            gap: 0;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            box-shadow: 0 4px 20px rgba(0,0,0,.07), 0 1px 4px rgba(0,0,0,.04);
            margin: 12px 0 16px;
            padding: 0;
            overflow: hidden;
            position: relative;
            max-width: 900px;
        }

        /* Colour schemes */
        .tmpmp-ghn-bg--warning .tmpmp-ghn-stripe { background: linear-gradient(180deg,#f59e0b,#d97706); }
        .tmpmp-ghn-bg--warning .tmpmp-ghn-icon-wrap { background: #fffbeb; }
        .tmpmp-ghn-bg--warning .tmpmp-ghn-icon { text-shadow: 0 2px 8px rgba(245,158,11,.3); }
        .tmpmp-ghn-bg--warning .tmpmp-ghn-cta { background:#f59e0b; color:#fff; }
        .tmpmp-ghn-bg--warning .tmpmp-ghn-cta:hover { background:#d97706; }

        .tmpmp-ghn-bg--info .tmpmp-ghn-stripe { background: linear-gradient(180deg,#6366f1,#4f46e5); }
        .tmpmp-ghn-bg--info .tmpmp-ghn-icon-wrap { background: #eef2ff; }
        .tmpmp-ghn-bg--info .tmpmp-ghn-cta { background:#6366f1; color:#fff; }
        .tmpmp-ghn-bg--info .tmpmp-ghn-cta:hover { background:#4f46e5; }

        .tmpmp-ghn-bg--success .tmpmp-ghn-stripe { background: linear-gradient(180deg,#10b981,#059669); }
        .tmpmp-ghn-bg--success .tmpmp-ghn-icon-wrap { background: #ecfdf5; }
        .tmpmp-ghn-bg--success .tmpmp-ghn-cta { background:#10b981; color:#fff; }
        .tmpmp-ghn-bg--success .tmpmp-ghn-cta:hover { background:#059669; }

        .tmpmp-ghn-bg--promo .tmpmp-ghn-stripe { background: linear-gradient(180deg,#8b5cf6,#6366f1); }
        .tmpmp-ghn-bg--promo .tmpmp-ghn-icon-wrap { background: #f5f3ff; }
        .tmpmp-ghn-bg--promo .tmpmp-ghn-cta { background: linear-gradient(135deg,#8b5cf6,#6366f1); color:#fff; }
        .tmpmp-ghn-bg--promo .tmpmp-ghn-cta:hover { background: linear-gradient(135deg,#7c3aed,#4f46e5); }

        /* Accent stripe */
        .tmpmp-ghn-stripe {
            width: 5px;
            min-width: 5px;
            align-self: stretch;
            flex-shrink: 0;
        }

        /* Icon area */
        .tmpmp-ghn-icon-wrap {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 56px;
            min-width: 56px;
            align-self: stretch;
            flex-shrink: 0;
        }
        .tmpmp-ghn-icon {
            font-size: 22px;
            line-height: 1;
            display: block;
        }

        /* Content */
        .tmpmp-ghn-content {
            flex: 1;
            min-width: 0;
            padding: 14px 16px 14px 4px;
        }
        .tmpmp-ghn-title {
            margin: 0 0 3px;
            font-size: 13px;
            font-weight: 700;
            color: #0f172a;
            font-family: -apple-system, BlinkMacSystemFont, 'Inter', sans-serif;
            line-height: 1.4;
        }
        .tmpmp-ghn-msg {
            margin: 0;
            font-size: 13px;
            color: #475569;
            line-height: 1.55;
            font-family: -apple-system, BlinkMacSystemFont, 'Inter', sans-serif;
        }
        .tmpmp-ghn-msg a {
            color: #6366f1;
            font-weight: 600;
            text-decoration: none;
        }
        .tmpmp-ghn-msg a:hover { text-decoration: underline; }

        /* CTA button */
        .tmpmp-ghn-actions {
            padding: 14px 12px;
            flex-shrink: 0;
        }
        .tmpmp-ghn-cta {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 7px 16px;
            border-radius: 7px;
            font-size: 12px;
            font-weight: 700;
            text-decoration: none;
            white-space: nowrap;
            transition: background .15s, box-shadow .15s;
            box-shadow: 0 2px 6px rgba(0,0,0,.12);
            font-family: -apple-system, BlinkMacSystemFont, 'Inter', sans-serif;
        }
        .tmpmp-ghn-cta:hover { box-shadow: 0 4px 12px rgba(0,0,0,.18); transform: translateY(-1px); }
        .tmpmp-ghn-cta svg { flex-shrink: 0; }

        /* Dismiss button */
        .tmpmp-ghn-dismiss {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 32px;
            height: 32px;
            margin: 0 10px;
            flex-shrink: 0;
            background: #f1f5f9;
            border: 1.5px solid #e2e8f0;
            border-radius: 50%;
            cursor: pointer;
            color: #64748b;
            transition: background .15s, color .15s, border-color .15s;
            padding: 0;
        }
        .tmpmp-ghn-dismiss:hover {
            background: #fee2e2;
            border-color: #fca5a5;
            color: #ef4444;
        }

        /* Responsive */
        @media (max-width: 700px) {
            .tmpmp-ghn-wrap {
                flex-wrap: wrap;
                max-width: 100%;
            }
            .tmpmp-ghn-stripe { width: 100%; min-width: 100%; height: 4px; align-self: auto; }
            .tmpmp-ghn-icon-wrap { width: 48px; min-width: 48px; padding: 10px 0; align-self: auto; }
            .tmpmp-ghn-content { padding: 10px 12px; }
            .tmpmp-ghn-actions { padding: 0 12px 14px; }
            .tmpmp-ghn-dismiss { margin: 10px 10px 10px auto; }
        }
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

    /* ══════════════════════════════════════════════════════════════════════
     * 6. SSL-safe download — fixes cURL error 35 (TLS handshake failure)
     *
     * Root cause: GitHub requires TLS 1.2+. Servers running old OpenSSL
     * default to TLS 1.0/1.1 and the handshake fails. WordPress's native
     * upgrader doesn't force a TLS version, so we intercept the download,
     * re-download via wp_remote_get with CURLOPT_SSLVERSION forced to
     * TLS 1.2, and hand WordPress a local temp-file path instead.
     *
     * Fallback: if TLS 1.2 still fails (extremely old OpenSSL), we retry
     * with sslverify => false so the update can still install.
     * ══════════════════════════════════════════════════════════════════════ */

    public function pre_download_fix( $reply, string $package, $upgrader ) {

        // Only intercept GitHub / GitHub CDN URLs — leave everything else alone
        if (
            false === strpos( $package, 'github.com' ) &&
            false === strpos( $package, 'githubusercontent.com' ) &&
            false === strpos( $package, 'objects.githubusercontent.com' )
        ) {
            return $reply;
        }

        /* ── Attempt 1: force TLS 1.2 + lower cipher SECLEVEL ───────────────
         * Fixes cURL error 35 on old OpenSSL that defaults to SSLv3.
         * Adding DEFAULT@SECLEVEL=1 allows older cipher suites that some
         * bundled OpenSSL builds ship with (e.g. LocalWP PHP 7.x/8.x).
         * ─────────────────────────────────────────────────────────────────── */
        $tls12_hook = static function ( $curl_handle ) {
            if ( defined( 'CURL_SSLVERSION_TLSv1_2' ) ) {
                curl_setopt( $curl_handle, CURLOPT_SSLVERSION, CURL_SSLVERSION_TLSv1_2 );
            }
            // Lower SECLEVEL so old ciphers can negotiate TLS 1.2 handshake
            @curl_setopt( $curl_handle, CURLOPT_SSL_CIPHER_LIST, 'DEFAULT@SECLEVEL=1' );
        };

        add_action( 'http_api_curl', $tls12_hook );

        $tmp = wp_tempnam( basename( $package ) );
        $response = wp_remote_get( $package, [
            'timeout'    => 300,
            'stream'     => true,
            'filename'   => $tmp,
            'sslverify'  => true,
            'user-agent' => 'WordPress/' . get_bloginfo( 'version' ) . '; ' . home_url(),
            'headers'    => [ 'Accept' => 'application/octet-stream' ],
        ] );

        remove_action( 'http_api_curl', $tls12_hook );

        /* ── Attempt 2: sslverify => false ───────────────────────────────────
         * Disables peer certificate verification (safe on private installs).
         * ─────────────────────────────────────────────────────────────────── */
        if ( is_wp_error( $response ) ) {
            @unlink( $tmp );
            $tmp = wp_tempnam( basename( $package ) );

            $response = wp_remote_get( $package, [
                'timeout'    => 300,
                'stream'     => true,
                'filename'   => $tmp,
                'sslverify'  => false,
                'user-agent' => 'WordPress/' . get_bloginfo( 'version' ) . '; ' . home_url(),
                'headers'    => [ 'Accept' => 'application/octet-stream' ],
            ] );
        }

        /* ── Attempt 3: PHP stream_context / file_get_contents ───────────────
         * Completely bypasses cURL and uses PHP's own SSL stack (OpenSSL via
         * streams). On many LocalWP setups the streams SSL is newer/different
         * from the bundled libcurl and can negotiate TLS 1.2 successfully.
         * ─────────────────────────────────────────────────────────────────── */
        if ( is_wp_error( $response ) && function_exists( 'stream_context_create' ) ) {
            @unlink( $tmp );

            $ssl_ctx = [
                'verify_peer'       => false,
                'verify_peer_name'  => false,
                'allow_self_signed' => true,
            ];
            // Force TLS 1.2 via stream crypto if the constant is available
            if ( defined( 'STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT' ) ) {
                $ssl_ctx['crypto_method'] = STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT;
            }

            $stream_ctx = stream_context_create( [
                'ssl'  => $ssl_ctx,
                'http' => [
                    'method'          => 'GET',
                    'header'          => "User-Agent: WordPress/" . get_bloginfo( 'version' ) . "; " . home_url() . "\r\n"
                                       . "Accept: application/octet-stream\r\n",
                    'follow_location' => 1,
                    'timeout'         => 300,
                    'ignore_errors'   => false,
                ],
            ] );

            $data = @file_get_contents( $package, false, $stream_ctx );

            if ( $data !== false && strlen( $data ) > 1000 ) {
                $tmp = wp_tempnam( basename( $package ) );
                file_put_contents( $tmp, $data );
                return $tmp; // success — hand local file path to WordPress
            }

            // Streams also failed — return a detailed WP_Error
            return new \WP_Error(
                'tmpmp_download_failed',
                sprintf(
                    /* translators: %s = detailed error description */
                    __( 'TempMail Pro update download failed (all attempts exhausted). Your server\'s SSL/TLS stack (cURL + PHP streams) cannot connect to GitHub. Please update PHP/OpenSSL on your server, or install the update manually: %s', 'tempmail-pro' ),
                    'https://github.com/' . self::GITHUB_USER . '/' . self::GITHUB_REPO . '/releases/latest'
                )
            );
        }

        /* ── Give up after attempts 1 & 2 if streams unavailable ────────── */
        if ( is_wp_error( $response ) ) {
            @unlink( $tmp );
            return new \WP_Error(
                'tmpmp_download_failed',
                sprintf(
                    __( 'TempMail Pro update download failed: %s', 'tempmail-pro' ),
                    $response->get_error_message()
                )
            );
        }

        // Verify we actually got a non-empty file
        $file_size = file_exists( $tmp ) ? filesize( $tmp ) : 0;
        if ( $file_size < 1000 ) {
            @unlink( $tmp );
            return new \WP_Error(
                'tmpmp_download_empty',
                __( 'TempMail Pro update download returned an empty or invalid file.', 'tempmail-pro' )
            );
        }

        return $tmp; // WordPress uses this local file path to install the update
    }
}
