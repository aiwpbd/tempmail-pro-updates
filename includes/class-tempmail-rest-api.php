<?php
/**
 * TempMail Pro — REST API (v1)
 *
 * Base: /wp-json/tempmail-pro/v1
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class TempMail_REST_API {

    private static ?self $instance = null;
    public static function instance() : self { return self::$instance ??= new self(); }

    private string $namespace = 'tempmail-pro/v1';

    private function __construct() {
        add_action( 'rest_api_init', [ $this, 'register_routes' ] );
    }

    public function register_routes() : void {
        // Public: generate
        register_rest_route( $this->namespace, '/generate', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [ $this, 'generate_inbox' ],
            'permission_callback' => '__return_true',
        ] );

        // Inbox
        register_rest_route( $this->namespace, '/inbox/(?P<address>[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+)', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [ $this, 'get_inbox' ],
            'permission_callback' => [ $this, 'api_auth' ],
            'args'                => ['address' => ['required' => true]],
        ] );

        // Single email
        register_rest_route( $this->namespace, '/email/(?P<id>\d+)', [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [ $this, 'get_email' ],
                'permission_callback' => [ $this, 'api_auth' ],
            ],
            [
                'methods'             => WP_REST_Server::DELETABLE,
                'callback'            => [ $this, 'delete_email' ],
                'permission_callback' => [ $this, 'api_auth' ],
            ],
        ] );

        // Receive (webhook from mail server)
        register_rest_route( $this->namespace, '/receive', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [ $this, 'receive_email' ],
            'permission_callback' => [ $this, 'webhook_auth' ],
        ] );

        // Server cron trigger
        register_rest_route( $this->namespace, '/server-cron', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [ $this, 'server_cron' ],
            'permission_callback' => [ $this, 'server_cron_auth' ],
        ] );

        // Admin purge
        register_rest_route( $this->namespace, '/purge', [
            'methods'             => WP_REST_Server::DELETABLE,
            'callback'            => [ $this, 'purge_expired' ],
            'permission_callback' => [ $this, 'admin_auth' ],
        ] );

        // Domains list (public)
        register_rest_route( $this->namespace, '/domains', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [ $this, 'list_domains' ],
            'permission_callback' => '__return_true',
        ] );

        // ── SSE stream — premium users: near-instant email notification ──
        register_rest_route( $this->namespace, '/sse', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [ $this, 'sse_stream' ],
            'permission_callback' => '__return_true',
        ] );
    }

    // ── Auth callbacks ────────────────────────────────────────────────────────

    public function admin_auth() : bool {
        return current_user_can( 'manage_options' );
    }

    public function api_auth( WP_REST_Request $req ) : bool|WP_Error {
        // API key in header or query
        $key = $req->get_header('X-TempMail-Key')
            ?? $req->get_param('api_key')
            ?? '';
        if ( $key ) {
            $record = TempMail_API_Keys::validate( $key );
            if ( is_wp_error($record) ) return $record;
            return true;
        }
        // Allow logged-in users too
        if ( is_user_logged_in() ) return true;
        return new WP_Error( 'no_auth', 'Authentication required.', ['status' => 401] );
    }

    public function webhook_auth( WP_REST_Request $req ) : bool {
        $stored = get_option( 'tmpmp_settings', [] )['webhook_secret'] ?? '';
        if ( empty($stored) ) return true; // open mode
        $provided = $req->get_header('X-TempMail-Secret') ?? '';
        return hash_equals( $stored, $provided );
    }

    public function server_cron_auth( WP_REST_Request $req ) : bool {
        $stored   = get_option( 'tmpmp_settings', [] )['server_cron_token'] ?? '';
        $provided = $req->get_param('token') ?? $req->get_header('X-Cron-Token') ?? '';
        if ( ! $stored || ! $provided ) return false;
        return hash_equals( $stored, $provided );
    }

    // ── Handlers ──────────────────────────────────────────────────────────────

    public function generate_inbox( WP_REST_Request $req ) : WP_REST_Response {
        $ip      = TempMail_Rate_Limiter::get_client_ip();
        $domain  = sanitize_text_field( $req->get_param('domain') ?? '' );
        $user    = sanitize_text_field( $req->get_param('username') ?? '' );
        $sid     = sanitize_text_field( $req->get_param('session_id') ?? '' );
        $user_id = get_current_user_id();
        $result  = TempMail_Email_Generator::generate( $user, $domain, $sid, $ip, $user_id );
        if ( is_wp_error($result) ) {
            return new WP_REST_Response(['code' => $result->get_error_code(), 'message' => $result->get_error_message()], 429);
        }
        return new WP_REST_Response($result, 201);
    }

    public function get_inbox( WP_REST_Request $req ) : WP_REST_Response {
        $address = strtolower( $req->get_param('address') );
        $result  = TempMail_Inbox::get_inbox( $address );
        if ( is_wp_error($result) ) return new WP_REST_Response(['message'=>$result->get_error_message()],404);
        return new WP_REST_Response($result, 200);
    }

    public function get_email( WP_REST_Request $req ) : WP_REST_Response {
        $id      = intval( $req->get_param('id') );
        $address = strtolower( $req->get_param('address') ?? '' );
        $result  = TempMail_Inbox::get_email( $id, $address );
        if ( is_wp_error($result) ) return new WP_REST_Response(['message'=>$result->get_error_message()],404);
        return new WP_REST_Response($result, 200);
    }

    public function delete_email( WP_REST_Request $req ) : WP_REST_Response {
        $id      = intval( $req->get_param('id') );
        $address = strtolower( $req->get_param('address') ?? '' );
        $ok = TempMail_Inbox::delete_email( $id, $address );
        return new WP_REST_Response(['deleted' => $ok], $ok ? 200 : 404);
    }

    public function receive_email( WP_REST_Request $req ) : WP_REST_Response {
        // Accept JSON, form-encoded, multipart
        $data = $req->get_json_params() ?: $req->get_body_params() ?: [];

        // Normalize Mailgun format
        if ( isset($data['recipient']) ) {
            $data['to']        = $data['to'] ?? $data['recipient'];
            $data['from']      = $data['from'] ?? $data['sender'] ?? '';
            $data['body_text'] = $data['body_text'] ?? $data['body-plain'] ?? '';
            $data['body_html'] = $data['body_html'] ?? $data['body-html'] ?? '';
        }

        // ── Fix custom domain delivery ─────────────────────────────────────────
        // When ImprovMX (or similar forwarder) delivers via webhook, the original
        // recipient (e.g. user@yourdomain.com) is often in a delivery header, not 'to'.
        // Check these headers in priority order and use the first one that matches
        // an active inbox in our DB.
        $delivery_candidates = array_filter( array_map( 'strtolower', array_map( 'trim', [
            $data['x-improvmx-delivered-to'] ?? $data['X-ImprovMX-Delivered-To'] ?? '',
            $data['x-original-to']           ?? $data['X-Original-To']           ?? '',
            $data['x-forwarded-to']          ?? $data['X-Forwarded-To']          ?? '',
            $data['envelope-to']             ?? $data['Envelope-To']             ?? '',
            $data['delivered-to']            ?? $data['Delivered-To']            ?? '',
            $data['recipient']               ?? '',
            $data['to']                      ?? '',
        ] ) ) );

        foreach ( $delivery_candidates as $candidate ) {
            // Strip angle brackets and display names: "Name <addr>" → "addr"
            if ( preg_match('/<([^>]+)>/', $candidate, $m) ) { $candidate = $m[1]; }
            $candidate = sanitize_email( trim( $candidate ) );
            if ( ! $candidate ) continue;
            // Check if this address has an active inbox
            if ( TempMail_Database::get_active_address( $candidate ) ) {
                $data['to'] = $candidate;
                break;
            }
        }

        $result = TempMail_Inbox::receive_email( $data );
        if ( is_wp_error($result) ) {
            update_option('tmpmp_last_webhook_error', ['time'=>gmdate('c'),'msg'=>$result->get_error_message(),'data'=>$data]);
            return new WP_REST_Response(['message'=>$result->get_error_message()],200); // 200 to stop retries
        }
        update_option('tmpmp_last_webhook_hit', gmdate('c'));
        return new WP_REST_Response(['received'=>true], 200);
    }


    public function server_cron( WP_REST_Request $req ) : WP_REST_Response {
        $poll   = TempMail_IMAP::poll();
        $purged = TempMail_Database::purge_expired();
        update_option('tmpmp_last_server_cron', gmdate('c'));
        return new WP_REST_Response(['poll'=>$poll,'purged'=>$purged,'time'=>gmdate('c')], 200);
    }

    public function purge_expired( WP_REST_Request $req ) : WP_REST_Response {
        $count = TempMail_Database::purge_expired();
        return new WP_REST_Response(['purged'=>$count], 200);
    }

    public function list_domains( WP_REST_Request $req ) : WP_REST_Response {
        $domains = TempMail_Database::get_all_domains();
        $out = array_map(fn($d) => [
            'domain'   => $d->domain,
            'category' => $d->category,
        ], $domains);
        return new WP_REST_Response($out, 200);
    }

    // ── SSE stream — keeps connection alive, pushes when new email arrives ──────
    public function sse_stream( WP_REST_Request $req ) : void {
        global $wpdb;

        // Validate nonce (passed as query param since EventSource can't set headers)
        $nonce   = sanitize_text_field( $req->get_param('nonce') ?: '' );
        $address = sanitize_email( $req->get_param('address') ?: '' );

        if ( ! $nonce || ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
            http_response_code( 403 );
            header( 'Content-Type: text/event-stream' );
            echo "data: {\"error\":\"invalid_nonce\"}\n\n";
            flush(); exit;
        }

        if ( ! TempMail_Subscription::is_premium_user() ) {
            http_response_code( 403 );
            header( 'Content-Type: text/event-stream' );
            echo "data: {\"error\":\"premium_required\"}\n\n";
            flush(); exit;
        }

        if ( ! $address ) {
            http_response_code( 400 );
            header( 'Content-Type: text/event-stream' );
            echo "data: {\"error\":\"address_required\"}\n\n";
            flush(); exit;
        }

        // ── SSE headers ──
        header( 'Content-Type: text/event-stream' );
        header( 'Cache-Control: no-cache, no-store' );
        header( 'X-Accel-Buffering: no' );    // disable Nginx buffering
        header( 'Connection: keep-alive' );

        @ini_set( 'output_buffering', 'off' );
        @ini_set( 'zlib.output_compression', false );
        @set_time_limit( 70 );                // 70s hard limit (stream runs 55s)
        if ( ob_get_level() ) ob_end_clean();

        // ── Resolve the address ID ──
        $row = TempMail_Database::get_active_address( $address );
        if ( ! $row ) {
            echo "data: {\"error\":\"not_found\"}\n\n";
            flush(); exit;
        }
        $address_id = (int) $row->id;

        // Baseline email count — we only push when this increases
        $last_count = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}tmpmp_emails WHERE address_id = %d",
            $address_id
        ) );

        // ── Send initial connected event ──
        echo ": connected\n\n";
        flush();

        $start_time     = time();
        $max_duration   = 55;   // seconds before telling client to reconnect
        $check_interval = 2;    // seconds between DB checks
        $ping_interval  = 20;   // seconds between keep-alive pings
        $last_ping      = $start_time;

        while ( time() - $start_time < $max_duration ) {

            if ( connection_aborted() ) break;

            // Check for new emails (fast indexed query)
            $current_count = (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}tmpmp_emails WHERE address_id = %d",
                $address_id
            ) );

            if ( $current_count > $last_count ) {
                $new = $current_count - $last_count;
                $last_count = $current_count;
                echo "data: " . wp_json_encode( [ 'new_emails' => $new ] ) . "\n\n";
                flush();
            }

            // Keep-alive ping so proxies/load-balancers don't drop idle connections
            if ( time() - $last_ping >= $ping_interval ) {
                echo ": ping\n\n";
                flush();
                $last_ping = time();
            }

            sleep( $check_interval );
        }

        // Tell client to reconnect immediately (our 55s window is up)
        echo "data: " . wp_json_encode( [ 'reconnect' => true ] ) . "\n\n";
        flush();
        exit;
    }
}
