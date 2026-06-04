<?php
/**
 * TempMail Pro — Design / Appearance System
 *
 * Reads design settings and outputs CSS variable overrides via wp_head
 * so any theme or page builder can see the customised variables.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class TempMail_Design {

    private static ?self $instance = null;
    public static function instance() : self { return self::$instance ??= new self(); }

    /** CSS variable defaults (mirrors tempmail-app.css :root) */
    const DEFAULTS = [
        'design_theme'      => 'auto',
        'design_accent'     => '#6366f1',
        'design_radius'     => '14',
        'design_font'       => 'Inter',
        'design_max_width'  => '780',
        'design_custom_css' => '',
    ];

    private function __construct() {
        add_action( 'wp_head',    [ $this, 'output_css_variables' ], 99 );
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_google_font' ], 5 );
    }

    /* ── Helpers ─────────────────────────────────────────────────────────── */

    public static function get( string $key ) : string {
        $settings = get_option( 'tmpmp_settings', [] );
        return sanitize_text_field(
            $settings[ $key ] ?? self::DEFAULTS[ $key ] ?? ''
        );
    }

    /** Darken/lighten a hex colour by a percentage (-1 to 1) */
    private static function adjust_hex( string $hex, float $pct ) : string {
        $hex = ltrim( $hex, '#' );
        if ( strlen($hex) === 3 ) $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        [$r, $g, $b] = [ hexdec(substr($hex,0,2)), hexdec(substr($hex,2,2)), hexdec(substr($hex,4,2)) ];
        $r = max(0, min(255, (int)($r + 255 * $pct)));
        $g = max(0, min(255, (int)($g + 255 * $pct)));
        $b = max(0, min(255, (int)($b + 255 * $pct)));
        return sprintf('#%02x%02x%02x', $r, $g, $b);
    }

    /** Convert hex + opacity to rgba() string */
    private static function hex_to_rgba( string $hex, float $opacity ) : string {
        $hex = ltrim( $hex, '#' );
        if ( strlen($hex) === 3 ) $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        return sprintf('rgba(%d,%d,%d,%.2f)',
            hexdec(substr($hex,0,2)), hexdec(substr($hex,2,2)), hexdec(substr($hex,4,2)), $opacity
        );
    }

    /* ── Output CSS variables to <head> ──────────────────────────────────── */

    public function output_css_variables() : void {
        $accent    = self::get('design_accent')    ?: '#6366f1';
        $theme     = self::get('design_theme')     ?: 'auto';
        $radius    = (int) ( self::get('design_radius')    ?: 14 );
        $font      = self::get('design_font')      ?: 'Inter';
        $max_w     = (int) ( self::get('design_max_width') ?: 780 );
        $custom    = self::get('design_custom_css');

        $accent_h  = self::adjust_hex( $accent,  0.15 );   // lighter
        $accent_d  = self::adjust_hex( $accent, -0.10 );   // darker
        $accent_gl = self::hex_to_rgba( $accent, 0.18 );
        $radius_sm = max(4, $radius - 6);
        $font_stack = esc_attr($font) . ", system-ui, sans-serif";

        echo "<style id=\"tmpmp-design-vars\">\n";
        echo ".tmpmp-wrap {\n";
        echo "    --accent:      {$accent};\n";
        echo "    --accent-h:    {$accent_h};\n";
        echo "    --accent-d:    {$accent_d};\n";
        echo "    --accent-gl:   {$accent_gl};\n";
        echo "    --radius:      {$radius}px;\n";
        echo "    --radius-sm:   {$radius_sm}px;\n";
        echo "    --font:        {$font_stack};\n";
        echo "    --font-h:      {$font_stack};\n";
        echo "    max-width:     {$max_w}px;\n";
        echo "}\n";

        if ( $theme === 'light' ) {
            echo ".tmpmp-wrap[data-theme='light'] { --bg:#f0f4ff; --bg2:#ffffff; --bg3:#ffffff; --bgh:#f0f1f8; --border:rgba(0,0,0,.08); --border-a:" . self::hex_to_rgba($accent, .35) . "; --text:#1a1d2e; --text2:#5a5f7c; --text3:#9ea3bb; --shadow:0 4px 24px rgba(0,0,0,.1); }\n";
        } elseif ( $theme === 'auto' ) {
            echo "@media(prefers-color-scheme:light){\n";
            echo "  .tmpmp-wrap[data-theme='auto'] { --bg:#f0f4ff; --bg2:#ffffff; --bg3:#ffffff; --bgh:#f0f1f8; --border:rgba(0,0,0,.08); --border-a:" . self::hex_to_rgba($accent, .35) . "; --text:#1a1d2e; --text2:#5a5f7c; --text3:#9ea3bb; --shadow:0 4px 24px rgba(0,0,0,.1); }\n";
            // Address bar light gradient for auto mode
            echo "  .tmpmp-wrap[data-theme='auto'] .tmpmp-address-bar { background:linear-gradient(135deg,#f0f4ff 0%,#eef1ff 60%,#f5f7ff 100%); }\n";
            echo "  .tmpmp-wrap[data-theme='auto'] .tmpmp-addr-box { background:#fff; border-color:" . self::hex_to_rgba($accent, .35) . "; }\n";
            echo "}\n";
        }

        if ( $custom ) {
            echo "/* Custom CSS */\n";
            echo wp_strip_all_tags( $custom ) . "\n";
        }
        echo "</style>\n";
        // Note: data-theme attribute is now set directly in PHP by the shortcode renderer.
        // No JS needed here.
    }

    /* ── Enqueue Google Font if non-default ──────────────────────────────── */

    public function enqueue_google_font() : void {
        $font = self::get('design_font');
        $system_fonts = ['System UI', 'system-ui', ''];
        if ( in_array($font, $system_fonts, true) ) return;

        // Inter is already loaded by the plugin's CSS @import — skip it
        if ( $font === 'Inter' ) return;

        $slug = urlencode( $font );
        wp_enqueue_style(
            'tmpmp-google-font-' . sanitize_title($font),
            "https://fonts.googleapis.com/css2?family={$slug}:wght@300;400;500;600;700;800&display=swap",
            [],
            null
        );
    }
}
