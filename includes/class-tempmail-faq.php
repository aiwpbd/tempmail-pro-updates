<?php
/**
 * TempMail Pro — FAQ Section
 *
 * Renders the FAQ accordion on the front-end inbox widget.
 * Fully controlled from Settings → FAQ tab.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class TempMail_FAQ {

    private static ?self $instance = null;
    public static function instance() : self { return self::$instance ??= new self(); }

    private function __construct() {
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue' ] );
        // Standalone shortcode — can be used independently of [tempmail_app]
        add_shortcode( 'tempmail_faq', [ __CLASS__, 'render' ] );
    }

    public function enqueue() : void {
        $s = get_option( 'tmpmp_settings', [] );
        if ( empty( $s['faq_enabled'] ) ) return;
        // Styles are inlined in render() to avoid extra HTTP requests
    }

    /* ── Default FAQ items ──────────────────────────────────────────────── */
    public static function default_items() : array {
        return [
            [
                'q' => 'What is a temporary email address?',
                'a' => 'A temporary email address is a disposable inbox that you can use to sign up for websites, apps, and services without exposing your real email. Emails sent to it appear here in real time.',
            ],
            [
                'q' => 'How long does my temporary email last?',
                'a' => 'By default, inboxes expire after the time set by the site administrator. You can extend the lifetime by upgrading to a premium plan.',
            ],
            [
                'q' => 'Can I receive attachments?',
                'a' => 'Yes — attachments are shown inside the email viewer. Download links appear directly in the email body.',
            ],
            [
                'q' => 'Is my temporary email private?',
                'a' => 'Yes. No personal information is required to generate an inbox. Emails are automatically deleted when the inbox expires.',
            ],
            [
                'q' => 'Can I choose a custom email address?',
                'a' => 'Premium users can set a custom prefix and choose from multiple domains. Free users receive a randomly generated address.',
            ],
        ];
    }

    /* ── Get configured FAQ items ────────────────────────────────────────── */
    public static function get_items() : array {
        $s    = get_option( 'tmpmp_settings', [] );
        $json = $s['faq_items'] ?? '';
        if ( $json ) {
            $items = json_decode( stripslashes( $json ), true );
            if ( is_array( $items ) && ! empty( $items ) ) return $items;
        }
        return self::default_items();
    }

    /* ── Render FAQ HTML ─────────────────────────────────────────────────── */
    public static function render() : string {
        $s = get_option( 'tmpmp_settings', [] );
        // Show FAQ by default; hide ONLY when explicitly set to 0
        if ( isset( $s['faq_enabled'] ) && ! $s['faq_enabled'] ) return '';

        $items     = self::get_items();
        $title     = $s['faq_title']     ?? 'Frequently Asked Questions';
        $mode      = $s['faq_accordion'] ?? 'single';   // single | multiple
        $icon_open = $s['faq_icon_open'] ?? '−';
        $icon_shut = $s['faq_icon_shut'] ?? '+';

        if ( empty( $items ) ) return '';

        ob_start();
        ?>
        <div class="tmpmp-faq" data-mode="<?php echo esc_attr($mode); ?>">
            <?php if ( $title ) : ?>
            <div class="tmpmp-faq-header">
                <h3 class="tmpmp-faq-title"><?php echo esc_html( $title ); ?></h3>
                <span class="tmpmp-faq-count"><?php echo count($items); ?> <?php esc_html_e('questions','tempmail-pro'); ?></span>
            </div>
            <?php endif; ?>

            <div class="tmpmp-faq-list" role="list">
                <?php foreach ( $items as $i => $item ) :
                    $q = sanitize_text_field( $item['q'] ?? '' );
                    $a = wp_kses( $item['a'] ?? '', [ 'a' => ['href'=>[],'target'=>[]], 'strong'=>[], 'em'=>[], 'br'=>[], 'p'=>[], 'ul'=>[], 'li'=>[], 'code'=>[] ] );
                    if ( ! $q ) continue;
                    $id = 'tmpmp-faq-' . $i;
                ?>
                <div class="tmpmp-faq-item" role="listitem">
                    <button class="tmpmp-faq-q"
                        id="<?php echo esc_attr($id); ?>-btn"
                        aria-expanded="false"
                        aria-controls="<?php echo esc_attr($id); ?>-panel">
                        <span class="tmpmp-faq-q-text"><?php echo esc_html($q); ?></span>
                        <span class="tmpmp-faq-icon" aria-hidden="true"><?php echo esc_html($icon_shut); ?></span>
                    </button>
                    <div class="tmpmp-faq-a"
                        id="<?php echo esc_attr($id); ?>-panel"
                        role="region"
                        aria-labelledby="<?php echo esc_attr($id); ?>-btn"
                        hidden>
                        <div class="tmpmp-faq-a-inner"><?php echo $a; ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <script>
        (function(){
            var faq  = document.querySelector('.tmpmp-faq');
            if(!faq) return;
            var mode = faq.dataset.mode || 'single';
            var iconOpen = <?php echo json_encode($icon_open); ?>;
            var iconShut = <?php echo json_encode($icon_shut); ?>;

            faq.querySelectorAll('.tmpmp-faq-q').forEach(function(btn){
                btn.addEventListener('click', function(){
                    var isOpen = this.getAttribute('aria-expanded') === 'true';

                    // In single mode close all others first
                    if(mode === 'single' && !isOpen){
                        faq.querySelectorAll('.tmpmp-faq-q[aria-expanded="true"]').forEach(function(b){
                            close_item(b);
                        });
                    }

                    if(isOpen){ close_item(this); } else { open_item(this); }
                });
            });

            function open_item(btn){
                var panel = document.getElementById(btn.getAttribute('aria-controls'));
                btn.setAttribute('aria-expanded','true');
                btn.classList.add('is-open');
                btn.querySelector('.tmpmp-faq-icon').textContent = iconOpen;
                panel.removeAttribute('hidden');
                panel.style.maxHeight = '0';
                panel.style.overflow  = 'hidden';
                panel.style.transition = 'max-height .35s ease';
                requestAnimationFrame(function(){
                    panel.style.maxHeight = panel.scrollHeight + 'px';
                });
                panel.addEventListener('transitionend', function h(){
                    panel.style.maxHeight = 'none';
                    panel.removeEventListener('transitionend', h);
                });
            }

            function close_item(btn){
                var panel = document.getElementById(btn.getAttribute('aria-controls'));
                btn.setAttribute('aria-expanded','false');
                btn.classList.remove('is-open');
                btn.querySelector('.tmpmp-faq-icon').textContent = iconShut;
                panel.style.maxHeight = panel.scrollHeight + 'px';
                panel.style.overflow  = 'hidden';
                panel.style.transition = 'max-height .3s ease';
                requestAnimationFrame(function(){
                    requestAnimationFrame(function(){
                        panel.style.maxHeight = '0';
                    });
                });
                panel.addEventListener('transitionend', function h(){
                    panel.setAttribute('hidden','');
                    panel.style.maxHeight = '';
                    panel.style.overflow  = '';
                    panel.removeEventListener('transitionend', h);
                });
            }
        })();
        </script>
        <?php
        return ob_get_clean();
    }
}
