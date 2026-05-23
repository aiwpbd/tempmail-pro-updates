<?php
/**
 * TempMail Pro — Ads system
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class TempMail_Ads {

    private static ?self $instance = null;
    public static function instance() : self { return self::$instance ??= new self(); }

    private function __construct() {
        add_shortcode( 'tempmail_ad', [ $this, 'render_ad_shortcode' ] );
    }

    /**
     * Render an ad for a given placement (only for free/non-premium users).
     */
    public static function render( string $placement ) : string {
        if ( TempMail_Subscription::is_premium_user() ) return '';
        $settings = get_option( 'tmpmp_settings', [] );

        global $wpdb;
        $ad = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}tmpmp_ads WHERE placement = %s AND is_active = 1 ORDER BY RAND() LIMIT 1",
            $placement
        ) );

        if ( ! $ad ) {
            // Fallback: Google AdSense
            if ( ! empty( $settings['adsense_code'] ) ) {
                return '<div class="tmpmp-ad tmpmp-ad--' . esc_attr($placement) . '">'
                    . $settings['adsense_code']
                    . '</div>';
            }
            return '';
        }

        // Track impression
        $wpdb->query( $wpdb->prepare(
            "UPDATE {$wpdb->prefix}tmpmp_ads SET impressions = impressions + 1 WHERE id = %d",
            $ad->id
        ) );

        return '<div class="tmpmp-ad tmpmp-ad--' . esc_attr($placement) . '" data-ad-id="' . intval($ad->id) . '">'
             . $ad->code
             . '</div>';
    }

    public function render_ad_shortcode( array $atts ) : string {
        $a = shortcode_atts(['placement' => 'sidebar'], $atts);
        return self::render( $a['placement'] );
    }

    public static function track_click( int $ad_id ) : void {
        global $wpdb;
        $wpdb->query( $wpdb->prepare(
            "UPDATE {$wpdb->prefix}tmpmp_ads SET clicks = clicks + 1 WHERE id = %d",
            $ad_id
        ) );
    }
}
