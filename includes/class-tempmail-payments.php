<?php
/**
 * TempMail Pro — Payment Gateways (Stripe, PayPal, SSLCommerz, WooCommerce, Custom API)
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class TempMail_Payments {

    private static ?self $instance = null;
    public static function instance() : self { return self::$instance ??= new self(); }

    private function __construct() {
        add_action( 'wp_ajax_tmpmp_create_checkout',        [ $this, 'ajax_create_checkout'   ] );
        add_action( 'wp_ajax_nopriv_tmpmp_create_checkout', [ $this, 'ajax_create_checkout'   ] );
        add_action( 'wp_ajax_tmpmp_verify_payment',         [ $this, 'ajax_verify_payment'    ] );
        add_action( 'wp_ajax_nopriv_tmpmp_verify_payment',  [ $this, 'ajax_verify_payment'    ] );
        add_action( 'wp_ajax_tmpmp_cancel_subscription',    [ $this, 'ajax_cancel'            ] );
        add_action( 'rest_api_init', [ $this, 'register_webhook_routes' ] );
        add_action( 'init',          [ $this, 'handle_payment_return'   ] );
        // WooCommerce: activate plan when order is paid
        add_action( 'woocommerce_order_status_completed',   [ $this, 'wc_order_completed'     ] );
        add_action( 'woocommerce_order_status_processing',  [ $this, 'wc_order_completed'     ] );
    }

    public function register_webhook_routes() : void {
        register_rest_route( 'tempmail-pro/v1', '/webhook/stripe', [
            'methods' => 'POST', 'callback' => [$this,'stripe_webhook'], 'permission_callback' => '__return_true',
        ] );
        register_rest_route( 'tempmail-pro/v1', '/webhook/paypal', [
            'methods' => 'POST', 'callback' => [$this,'paypal_webhook'], 'permission_callback' => '__return_true',
        ] );
        register_rest_route( 'tempmail-pro/v1', '/webhook/sslcommerz', [
            'methods' => 'POST', 'callback' => [$this,'sslcommerz_webhook'], 'permission_callback' => '__return_true',
        ] );
        register_rest_route( 'tempmail-pro/v1', '/webhook/custom-api', [
            'methods' => 'POST', 'callback' => [$this,'custom_api_webhook'], 'permission_callback' => '__return_true',
        ] );
    }

    // ── Handle return from payment gateway ────────────────────────────────────
    public function handle_payment_return() : void {
        if ( ! is_user_logged_in() ) return;
        $user_id = get_current_user_id();

        // Stripe return
        if ( ! empty( $_GET['tmpmp_success'] ) && ! empty( $_GET['session_id'] ) ) {
            $session_id = sanitize_text_field( $_GET['session_id'] );
            $plan_id    = intval( $_GET['plan_id'] ?? 0 );
            $cycle      = sanitize_key( $_GET['cycle'] ?? 'monthly' );
            $this->complete_stripe( $user_id, $session_id, $plan_id, $cycle );
        }

        // PayPal return
        if ( ! empty( $_GET['tmpmp_paypal'] ) && ! empty( $_GET['token'] ) ) {
            $order_id = sanitize_text_field( $_GET['token'] );
            $plan_id  = intval( $_GET['plan_id'] ?? 0 );
            $cycle    = sanitize_key( $_GET['cycle'] ?? 'monthly' );
            $this->complete_paypal( $user_id, $order_id, $plan_id, $cycle );
        }

        // Custom API return
        if ( ! empty( $_GET['tmpmp_custom_api'] ) && ! empty( $_GET['txn_id'] ) ) {
            $txn_id  = sanitize_text_field( $_GET['txn_id'] );
            $plan_id = intval( $_GET['plan_id'] ?? 0 );
            $cycle   = sanitize_key( $_GET['cycle'] ?? 'monthly' );
            $this->complete_custom_api( $user_id, $txn_id, $plan_id, $cycle );
        }
    }

    // ── AJAX: create checkout ─────────────────────────────────────────────────
    public function ajax_create_checkout() : void {
        check_ajax_referer( 'tempmail_pro_nonce', 'nonce' );
        $user_id = get_current_user_id();
        if ( ! $user_id ) wp_send_json_error(['message' => 'Login required.'], 401);

        $plan_id = intval( $_POST['plan_id'] ?? 0 );
        $gateway = sanitize_key( $_POST['gateway'] ?? 'stripe' );
        $cycle   = sanitize_key( $_POST['cycle']   ?? 'monthly' );
        $plan    = TempMail_Database::get_plan( $plan_id );
        if ( ! $plan ) wp_send_json_error(['message' => 'Plan not found.']);

        $amount = $cycle === 'yearly' ? $plan->price_yearly : $plan->price_monthly;

        $result = match($gateway) {
            'stripe'     => $this->stripe_create_session( $user_id, $plan, $amount, $cycle ),
            'paypal'     => $this->paypal_create_order( $user_id, $plan, $amount, $cycle ),
            'woocommerce'=> $this->wc_create_order( $user_id, $plan, $amount, $cycle ),
            'custom_api' => $this->custom_api_create_checkout( $user_id, $plan, $amount, $cycle ),
            default      => new WP_Error( 'unsupported', 'Gateway not supported.' ),
        };

        is_wp_error($result)
            ? wp_send_json_error(['message' => $result->get_error_message()])
            : wp_send_json_success($result);
    }

    // ── AJAX: verify (redirect-based gateways) ────────────────────────────────
    public function ajax_verify_payment() : void {
        check_ajax_referer( 'tempmail_pro_nonce', 'nonce' );
        $gateway  = sanitize_key( $_POST['gateway'] ?? '' );
        $sid      = sanitize_text_field( $_POST['session_id'] ?? '' );
        $user_id  = get_current_user_id();
        $plan_id  = intval( $_POST['plan_id'] ?? 0 );
        $cycle    = sanitize_key( $_POST['cycle'] ?? 'monthly' );

        $result = match($gateway) {
            'stripe' => $this->stripe_verify( $sid, $user_id, $plan_id, $cycle ),
            default  => new WP_Error('unsupported','Unsupported.'),
        };

        is_wp_error($result)
            ? wp_send_json_error(['message' => $result->get_error_message()])
            : wp_send_json_success($result);
    }

    // ── AJAX: cancel ──────────────────────────────────────────────────────────
    public function ajax_cancel() : void {
        check_ajax_referer( 'tempmail_pro_nonce', 'nonce' );
        $user_id = get_current_user_id();
        if (!$user_id) wp_send_json_error(['message'=>'Login required.'],401);
        TempMail_Subscription::cancel( $user_id );
        wp_send_json_success(['message' => __('Subscription cancelled.','tempmail-pro')]);
    }

    // ══ Stripe ════════════════════════════════════════════════════════════════

    private function stripe_sk() : string {
        return get_option('tmpmp_settings',[])['stripe_sk'] ?? '';
    }

    private function stripe_create_session( int $user_id, object $plan, float $amount, string $cycle ) : array|WP_Error {
        $sk = $this->stripe_sk();
        if (!$sk) return new WP_Error('no_stripe','Stripe secret key not configured.');

        $res = wp_remote_post('https://api.stripe.com/v1/checkout/sessions', [
            'headers' => ['Authorization'=>'Bearer '.$sk,'Content-Type'=>'application/x-www-form-urlencoded'],
            'body'    => [
                'payment_method_types[]'                          => 'card',
                'mode'                                            => 'subscription',
                'line_items[0][price_data][currency]'             => 'usd',
                'line_items[0][price_data][unit_amount]'          => intval($amount*100),
                'line_items[0][price_data][recurring][interval]'  => $cycle==='yearly'?'year':'month',
                'line_items[0][price_data][product_data][name]'   => $plan->name.' Plan',
                'line_items[0][quantity]'                         => 1,
                'success_url' => add_query_arg(['tmpmp_success'=>1,'plan_id'=>$plan->id,'cycle'=>$cycle,'session_id'=>'{CHECKOUT_SESSION_ID}'],home_url('/dashboard/')),
                'cancel_url'  => home_url('/pricing/'),
                'client_reference_id'   => $user_id,
                'metadata[plan_id]'     => $plan->id,
                'metadata[user_id]'     => $user_id,
                'metadata[cycle]'       => $cycle,
            ],
        ]);
        $body = json_decode(wp_remote_retrieve_body($res), true);
        if (!empty($body['error'])) return new WP_Error('stripe_error',$body['error']['message']);
        return ['url'=>$body['url'],'session_id'=>$body['id']];
    }

    private function stripe_verify( string $session_id, int $user_id, int $plan_id, string $cycle ) : array|WP_Error {
        $sk  = $this->stripe_sk();
        $res = wp_remote_get("https://api.stripe.com/v1/checkout/sessions/{$session_id}", [
            'headers' => ['Authorization'=>'Bearer '.$sk],
        ]);
        $body = json_decode(wp_remote_retrieve_body($res), true);
        if (($body['payment_status']??'')!=='paid') return new WP_Error('not_paid','Payment not completed.');
        return $this->activate_plan($user_id,$plan_id,'stripe',$body['subscription']??$session_id,$cycle);
    }

    private function complete_stripe(int $user_id,string $session_id,int $plan_id,string $cycle) : void {
        if (!$session_id||!$plan_id) return;
        $this->stripe_verify($session_id,$user_id,$plan_id,$cycle);
    }

    // ══ PayPal ════════════════════════════════════════════════════════════════

    private function paypal_token() : string {
        $s = get_option('tmpmp_settings',[]);
        $res = wp_remote_post('https://api-m.paypal.com/v1/oauth2/token',[
            'headers'=>['Authorization'=>'Basic '.base64_encode($s['paypal_client_id'].':'.$s['paypal_secret']),'Content-Type'=>'application/x-www-form-urlencoded'],
            'body'=>'grant_type=client_credentials',
        ]);
        return json_decode(wp_remote_retrieve_body($res),true)['access_token']??'';
    }

    private function paypal_create_order(int $user_id,object $plan,float $amount,string $cycle) : array|WP_Error {
        $s = get_option('tmpmp_settings',[]);
        if (empty($s['paypal_client_id'])||empty($s['paypal_secret'])) return new WP_Error('no_paypal','PayPal not configured.');
        $token = $this->paypal_token();
        if (!$token) return new WP_Error('paypal_auth','PayPal auth failed.');

        $res = wp_remote_post('https://api-m.paypal.com/v2/checkout/orders',[
            'headers'=>['Authorization'=>"Bearer $token",'Content-Type'=>'application/json'],
            'body'=>json_encode([
                'intent'=>'CAPTURE',
                'purchase_units'=>[['amount'=>['currency_code'=>'USD','value'=>number_format($amount,2,'.','')],'custom_id'=>"{$user_id}|{$plan->id}|{$cycle}"]],
                'application_context'=>[
                    'return_url'=>add_query_arg(['tmpmp_paypal'=>1,'plan_id'=>$plan->id,'cycle'=>$cycle],home_url('/dashboard/')),
                    'cancel_url'=>home_url('/pricing/'),
                ],
            ]),
        ]);
        $body = json_decode(wp_remote_retrieve_body($res),true);
        if (empty($body['id'])) return new WP_Error('paypal_order','Order creation failed.');
        $url='';
        foreach($body['links']??[] as $l){ if($l['rel']==='approve'){$url=$l['href'];break;} }
        return ['url'=>$url,'order_id'=>$body['id']];
    }

    private function complete_paypal(int $user_id,string $order_id,int $plan_id,string $cycle) : void {
        $token = $this->paypal_token();
        $res   = wp_remote_post("https://api-m.paypal.com/v2/checkout/orders/{$order_id}/capture",[
            'headers'=>['Authorization'=>"Bearer $token",'Content-Type'=>'application/json'],
        ]);
        $body = json_decode(wp_remote_retrieve_body($res),true);
        if (($body['status']??'')==='COMPLETED') {
            $this->activate_plan($user_id,$plan_id,'paypal',$order_id,$cycle);
        }
    }

    // ══ WooCommerce ═══════════════════════════════════════════════════════════

    /**
     * Create a WooCommerce order programmatically and return checkout URL.
     * Requires WooCommerce to be active. Plan details stored as order meta.
     */
    private function wc_create_order( int $user_id, object $plan, float $amount, string $cycle ) : array|WP_Error {
        if ( ! function_exists( 'wc_create_order' ) ) {
            return new WP_Error( 'no_woo', 'WooCommerce is not installed or active.' );
        }
        $s = get_option( 'tmpmp_settings', [] );
        if ( empty( $s['wc_enabled'] ) ) {
            return new WP_Error( 'wc_disabled', 'WooCommerce gateway is disabled.' );
        }

        $order = wc_create_order( [ 'customer_id' => $user_id ] );
        if ( is_wp_error( $order ) ) return $order;

        // Add a fee line-item (no WC product needed)
        $item = new WC_Order_Item_Fee();
        $item->set_name( $plan->name . ' — ' . ucfirst( $cycle ) . ' Subscription' );
        $item->set_amount( $amount );
        $item->set_total( $amount );
        $order->add_item( $item );

        $order->set_currency( get_woocommerce_currency() );
        $order->update_meta_data( '_tmpmp_plan_id', $plan->id );
        $order->update_meta_data( '_tmpmp_user_id', $user_id );
        $order->update_meta_data( '_tmpmp_cycle',   $cycle );
        $order->calculate_totals();
        $order->save();

        $checkout_url = add_query_arg(
            [ 'pay_for_order' => 'true', 'key' => $order->get_order_key() ],
            $order->get_checkout_payment_url()
        );
        return [ 'url' => $checkout_url, 'order_id' => $order->get_id() ];
    }

    /**
     * Fires on woocommerce_order_status_completed / _processing.
     * Activates the TempMail plan once, using order meta.
     */
    public function wc_order_completed( int $order_id ) : void {
        $order = wc_get_order( $order_id );
        if ( ! $order ) return;

        $plan_id = (int) $order->get_meta( '_tmpmp_plan_id' );
        $user_id = (int) $order->get_meta( '_tmpmp_user_id' );
        $cycle   = (string) $order->get_meta( '_tmpmp_cycle' );
        if ( ! $plan_id || ! $user_id ) return;

        // Guard against double-activation
        if ( $order->get_meta( '_tmpmp_activated' ) ) return;
        $order->update_meta_data( '_tmpmp_activated', 1 );
        $order->save();

        $this->activate_plan( $user_id, $plan_id, 'woocommerce', (string) $order_id, $cycle ?: 'monthly' );
    }

    // ══ Custom API ════════════════════════════════════════════════════════════

    /**
     * POST to the admin-configured endpoint to obtain a hosted checkout URL.
     * The endpoint must respond with JSON: { "checkout_url": "https://...", "txn_id": "..." }
     */
    private function custom_api_create_checkout( int $user_id, object $plan, float $amount, string $cycle ) : array|WP_Error {
        $s = get_option( 'tmpmp_settings', [] );
        if ( empty( $s['custom_api_enabled'] ) )  return new WP_Error( 'ca_disabled',     'Custom API gateway is disabled.' );
        if ( empty( $s['custom_api_endpoint'] ) ) return new WP_Error( 'ca_no_endpoint',  'Custom API endpoint not configured.' );

        $dashboard_url = $s['dashboard_url'] ?? home_url( '/tempmail-dashboard/' );
        $callback_url  = add_query_arg( [ 'tmpmp_custom_api' => 1, 'plan_id' => $plan->id, 'cycle' => $cycle ], $dashboard_url );

        $res = wp_remote_post( esc_url_raw( $s['custom_api_endpoint'] ), [
            'timeout' => 20,
            'headers' => [
                'Content-Type'  => 'application/json',
                'Authorization' => 'Bearer ' . ( $s['custom_api_key'] ?? '' ),
                'X-Source'      => 'TempMailPro',
            ],
            'body' => wp_json_encode( [
                'user_id'      => $user_id,
                'plan_id'      => $plan->id,
                'plan_name'    => $plan->name,
                'amount'       => $amount,
                'currency'     => 'USD',
                'cycle'        => $cycle,
                'callback_url' => $callback_url,
                'webhook_url'  => rest_url( 'tempmail-pro/v1/webhook/custom-api' ),
            ] ),
        ] );

        if ( is_wp_error( $res ) ) return $res;
        $body = json_decode( wp_remote_retrieve_body( $res ), true );
        if ( empty( $body['checkout_url'] ) ) {
            return new WP_Error( 'ca_error', $body['error'] ?? $body['message'] ?? 'Custom API did not return a checkout_url.' );
        }
        return [ 'url' => $body['checkout_url'], 'txn_id' => $body['txn_id'] ?? '' ];
    }

    private function complete_custom_api( int $user_id, string $txn_id, int $plan_id, string $cycle ) : void {
        if ( ! $txn_id || ! $plan_id ) return;
        $this->activate_plan( $user_id, $plan_id, 'custom_api', $txn_id, $cycle );
    }

    /**
     * Inbound webhook from the custom API (server-to-server POST).
     * Body: { "status": "paid", "user_id": 1, "plan_id": 2, "cycle": "monthly", "txn_id": "abc", "signature": "..." }
     * Optionally verified with HMAC-SHA256 via X-Signature header.
     */
    public function custom_api_webhook( WP_REST_Request $req ) : WP_REST_Response {
        $s      = get_option( 'tmpmp_settings', [] );
        $secret = $s['custom_api_webhook_secret'] ?? '';

        if ( $secret ) {
            $received = $req->get_header( 'X-Signature' ) ?? '';
            $expected = hash_hmac( 'sha256', $req->get_body(), $secret );
            if ( ! hash_equals( $expected, $received ) ) {
                return new WP_REST_Response( [ 'error' => 'Invalid signature' ], 401 );
            }
        }

        $data    = $req->get_json_params() ?: [];
        $status  = $data['status'] ?? '';
        $user_id = intval( $data['user_id'] ?? 0 );
        $plan_id = intval( $data['plan_id'] ?? 0 );
        $cycle   = sanitize_key( $data['cycle'] ?? 'monthly' );
        $txn_id  = sanitize_text_field( $data['txn_id'] ?? '' );

        if ( in_array( $status, [ 'paid', 'completed', 'success' ], true ) && $user_id && $plan_id ) {
            $this->activate_plan( $user_id, $plan_id, 'custom_api', $txn_id, $cycle );
        }
        return new WP_REST_Response( [ 'received' => true ], 200 );
    }

    // ══ Common activate ═══════════════════════════════════════════════════════

    private function activate_plan(int $user_id,int $plan_id,string $gateway,string $txn,string $cycle) : array {
        $plan   = TempMail_Database::get_plan($plan_id);
        $amount = $cycle==='yearly' ? $plan->price_yearly : $plan->price_monthly;
        $sub_id = TempMail_Subscription::activate($user_id,$plan_id,$gateway,$txn,$cycle,$amount);
        global $wpdb;
        $wpdb->insert($wpdb->prefix.'tmpmp_payments',[
            'user_id'=>$user_id,'subscription_id'=>$sub_id,'gateway'=>$gateway,
            'gateway_txn_id'=>$txn,'amount'=>$amount,'currency'=>'USD',
            'status'=>'completed','invoice_number'=>'INV-'.strtoupper(substr(md5($txn),0,8)),
            'description'=>$plan->name.' '.$cycle,'created_at'=>gmdate('Y-m-d H:i:s'),
        ]);
        return ['success'=>true,'plan'=>$plan->slug];
    }

    // ══ Webhooks ══════════════════════════════════════════════════════════════

    public function stripe_webhook(WP_REST_Request $req) : WP_REST_Response {
        $payload = $req->get_body();
        $sig     = $req->get_header('Stripe-Signature')??'';
        $secret  = get_option('tmpmp_settings',[])['stripe_webhook_secret']??'';

        if ($secret) {
            $parts=[]; $ts=''; $sh='';
            foreach(explode(',',$sig) as $p){ [$k,$v]=explode('=',$p,2); if($k==='t')$ts=$v; if($k==='v1')$sh=$v; }
            if(!hash_equals(hash_hmac('sha256',"$ts.$payload",$secret),$sh))
                return new WP_REST_Response(['error'=>'Bad signature'],400);
        }

        $event = json_decode($payload,true);
        if (($event['type']??'')==='invoice.payment_succeeded') {
            $meta    = $event['data']['object']['metadata']??[];
            $user_id = intval($meta['user_id']??0);
            $plan_id = intval($meta['plan_id']??0);
            $cycle   = $meta['cycle']??'monthly';
            if ($user_id && $plan_id) {
                $this->activate_plan($user_id,$plan_id,'stripe',$event['id']??'',$cycle);
            }
        }
        return new WP_REST_Response(['received'=>true],200);
    }

    public function paypal_webhook(WP_REST_Request $req) : WP_REST_Response {
        return new WP_REST_Response(['received'=>true],200);
    }

    public function sslcommerz_webhook(WP_REST_Request $req) : WP_REST_Response {
        $data = $req->get_body_params();
        if (($data['status']??'')==='VALID') {
            $parts = explode('|',$data['custom_id']??'');
            if (count($parts)===3) {
                [$user_id,$plan_id,$cycle] = $parts;
                $this->activate_plan(intval($user_id),intval($plan_id),'sslcommerz',$data['val_id']??'',$cycle);
            }
        }
        return new WP_REST_Response(['received'=>true],200);
    }
}
