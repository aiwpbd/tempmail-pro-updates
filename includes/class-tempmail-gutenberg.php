<?php
/**
 * TempMail Pro — Gutenberg Block Registration
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class TempMail_Gutenberg {

    private static ?self $instance = null;
    public static function instance() : self { return self::$instance ??= new self(); }

    private function __construct() {
        add_action( 'init', [ $this, 'register_block' ] );
    }

    public function register_block() : void {
        if ( ! function_exists( 'register_block_type' ) ) return;

        register_block_type( 'tempmail-pro/inbox', [
            'editor_script'   => 'tempmail-pro-block-editor',
            'render_callback' => [ $this, 'render_block' ],
            'attributes'      => [
                'theme'  => [ 'type' => 'string', 'default' => 'dark' ],
                'height' => [ 'type' => 'string', 'default' => 'auto' ],
            ],
        ] );

        wp_register_script(
            'tempmail-pro-block-editor',
            TMPMP_PLUGIN_URL . 'assets/js/block-editor.js',
            [ 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components' ],
            TMPMP_VERSION,
            true
        );
    }

    public function render_block( array $atts ) : string {
        return TempMail_Shortcode::instance() ? do_shortcode( '[tempmail_app theme="' . esc_attr($atts['theme'] ?? 'dark') . '"]' ) : '';
    }
}
