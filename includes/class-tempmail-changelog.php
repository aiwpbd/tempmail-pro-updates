<?php
/**
 * TempMail Pro — Changelog & Update Notification System
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class TempMail_Changelog {

    private static ?self $instance = null;
    public static function instance() : self { return self::$instance ??= new self(); }

    private function __construct() {
        add_action( 'admin_init', [ $this, 'detect_update' ] );
        add_action( 'wp_ajax_tmpmp_dismiss_changelog', [ $this, 'ajax_dismiss' ] );
    }

    public function detect_update() : void {
        $stored = get_option( 'tmpmp_last_seen_version', '0.0.0' );
        if ( version_compare( $stored, TMPMP_VERSION, '<' ) ) {
            update_option( 'tmpmp_last_seen_version', TMPMP_VERSION );
            delete_option( 'tmpmp_changelog_dismissed' );
        }
    }

    public function ajax_dismiss() : void {
        check_ajax_referer( 'tempmail_pro_nonce', 'nonce' );
        update_option( 'tmpmp_changelog_dismissed', TMPMP_VERSION );
        wp_send_json_success();
    }

    public static function render_banner() : void {
        if ( get_option('tmpmp_changelog_dismissed') === TMPMP_VERSION ) return;
        $log     = self::get_changelog();
        $current = $log[ TMPMP_VERSION ] ?? null;
        if ( ! $current ) return;

        $features = $current['features'] ?? [];
        $fixes    = $current['bugfixes'] ?? [];
        $label    = $current['label']    ?? '';
        $subtitle = $current['subtitle'] ?? strtoupper( $label );
        ?>
        <style>
        #tmpmp-changelog-banner{background:#fff;border:1px solid #e0e7ff;border-left:4px solid #6366f1;border-radius:12px;padding:20px 22px;margin:20px 0;box-shadow:0 4px 20px rgba(99,102,241,.08);position:relative;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;max-width:960px;}
        #tmpmp-changelog-banner .cl-head{display:flex;align-items:flex-start;gap:12px;margin-bottom:14px;}
        #tmpmp-changelog-banner .cl-icon{font-size:26px;line-height:1;flex-shrink:0;margin-top:2px;}
        #tmpmp-changelog-banner .cl-title{font-size:15px;font-weight:700;color:#1e293b;margin:0 0 6px;}
        #tmpmp-changelog-banner .cl-subtitle{display:inline-block;background:#ede9fe;color:#5b21b6;font-size:10px;font-weight:800;letter-spacing:.8px;padding:3px 9px;border-radius:4px;text-transform:uppercase;}
        #tmpmp-changelog-banner .cl-close{position:absolute;top:14px;right:16px;background:none;border:none;font-size:20px;color:#94a3b8;cursor:pointer;line-height:1;padding:4px;border-radius:4px;transition:color .15s,background .15s;}
        #tmpmp-changelog-banner .cl-close:hover{color:#475569;background:#f1f5f9;}
        #tmpmp-changelog-banner .cl-items{display:flex;flex-direction:column;gap:8px;margin-top:14px;}
        #tmpmp-changelog-banner .cl-item{display:flex;align-items:flex-start;gap:10px;font-size:13px;color:#334155;line-height:1.55;}
        #tmpmp-changelog-banner .cl-badge{flex-shrink:0;display:inline-flex;align-items:center;gap:4px;padding:2px 8px;border-radius:5px;font-size:10px;font-weight:800;letter-spacing:.5px;text-transform:uppercase;margin-top:1px;}
        #tmpmp-changelog-banner .cl-badge--feature{background:#dcfce7;color:#15803d;}
        #tmpmp-changelog-banner .cl-badge--fix{background:#fee2e2;color:#b91c1c;}
        #tmpmp-changelog-banner .cl-footer{margin-top:16px;padding-top:14px;border-top:1px solid #f1f5f9;}
        #tmpmp-changelog-banner .cl-view-btn{display:inline-flex;align-items:center;gap:6px;padding:7px 14px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:12px;font-weight:600;color:#475569;text-decoration:none;background:#f8fafc;transition:all .15s;}
        #tmpmp-changelog-banner .cl-view-btn:hover{border-color:#6366f1;color:#6366f1;background:#eff6ff;}
        #tmpmp-changelog-banner .cl-dismiss-link{font-size:12px;color:#94a3b8;margin-left:14px;cursor:pointer;background:none;border:none;text-decoration:underline;}
        #tmpmp-changelog-banner .cl-dismiss-link:hover{color:#475569;}
        </style>

        <div id="tmpmp-changelog-banner">
            <button class="cl-close" onclick="tmpmpDismissChangelog()" title="<?php esc_attr_e('Dismiss','tempmail-pro'); ?>">&times;</button>

            <!-- Header -->
            <div class="cl-head">
                <span class="cl-icon">🎉</span>
                <div>
                    <p class="cl-title">
                        <?php printf(
                            /* translators: %s = version number */
                            esc_html__( 'TempMail Pro has been updated to v%s!', 'tempmail-pro' ),
                            esc_html( TMPMP_VERSION )
                        ); ?>
                    </p>
                    <span class="cl-subtitle"><?php echo esc_html( $subtitle ); ?></span>
                </div>
            </div>

            <!-- Items -->
            <div class="cl-items">
                <?php foreach ( $features as $f ) : ?>
                <div class="cl-item">
                    <span class="cl-badge cl-badge--feature">
                        <svg width="9" height="9" viewBox="0 0 24 24" fill="currentColor"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                        <?php esc_html_e('Feature','tempmail-pro'); ?>
                    </span>
                    <span><?php echo wp_kses( $f, [ 'code' => [], 'strong' => [], 'em' => [], 'a' => [ 'href' => [], 'target' => [] ] ] ); ?></span>
                </div>
                <?php endforeach; ?>

                <?php foreach ( $fixes as $f ) : ?>
                <div class="cl-item">
                    <span class="cl-badge cl-badge--fix">
                        <svg width="9" height="9" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                        <?php esc_html_e('Fix','tempmail-pro'); ?>
                    </span>
                    <span><?php echo wp_kses( $f, [ 'code' => [], 'strong' => [], 'em' => [], 'a' => [ 'href' => [], 'target' => [] ] ] ); ?></span>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Footer -->
            <div class="cl-footer">
                <a href="<?php echo esc_url( admin_url('admin.php?page=tmpmp-changelog') ); ?>" class="cl-view-btn">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                    <?php esc_html_e('View Full Changelog','tempmail-pro'); ?>
                </a>
                <button class="cl-dismiss-link" onclick="tmpmpDismissChangelog()"><?php esc_html_e('Dismiss','tempmail-pro'); ?></button>
            </div>
        </div>

        <script>
        function tmpmpDismissChangelog(){
            const el = document.getElementById('tmpmp-changelog-banner');
            if(el){ el.style.opacity='0'; el.style.transition='opacity .2s'; setTimeout(()=>el.remove(), 220); }
            fetch('<?php echo esc_url( admin_url('admin-ajax.php') ); ?>', {
                method:'POST',
                headers:{'Content-Type':'application/x-www-form-urlencoded'},
                body:'action=tmpmp_dismiss_changelog&nonce=<?php echo esc_js( wp_create_nonce('tempmail_pro_nonce') ); ?>'
            });
        }
        // Auto-dismiss on first view: mark as seen immediately so it won't
        // appear again on the next page load. Banner stays visible this session.
        (function(){
            fetch('<?php echo esc_url( admin_url('admin-ajax.php') ); ?>', {
                method:'POST',
                headers:{'Content-Type':'application/x-www-form-urlencoded'},
                body:'action=tmpmp_dismiss_changelog&nonce=<?php echo esc_js( wp_create_nonce('tempmail_pro_nonce') ); ?>'
            });
        })();
        </script>
        <?php
    }

    public static function get_changelog() : array {
        return [
            '2.0.4' => [
                'date'     => '2026-05-24',
                'label'    => 'Security & Admin Release',
                'subtitle' => 'EXPORT · IMPORT · PASSWORD GEN · ADMIN USERS · BUG FIXES',
                'features' => [
                    'New: <strong>Export / Import User Data</strong> — full JSON backup & restore of users, subscriptions, payments, API keys, inbox addresses, blocked IPs, and plan config. Supports drag-and-drop file upload with animated progress bar.',
                    'New: <strong>Password Generator</strong> in admin Edit User modal — cryptographically secure 18-char passwords via <code>crypto.getRandomValues()</code> with strength meter (5-segment colour bar), show/hide toggle, and clipboard copy.',
                    'New: <strong>Password Generator</strong> on user dashboard Security tab — same generator fills both New Password and Confirm Password fields simultaneously.',
                    'New: Admin Users &amp; Subscriptions page — full user list with Free/Premium badges, tab filter (All / Premium / Free / Blocked), inline quick-info sidebar, and Edit User modal with profile, plan, and security tabs.',
                    'New: Admin Edit User modal — update display name, email, role, plan/subscription, and password from one place.',
                    'New: Import merge mode — existing users are updated (not duplicated), new users auto-created with temporary password; old user IDs remapped to new IDs across all tables.',
                ],
                'bugfixes' => [
                    'Fixed: Admin modal password generator not firing — <code>document.addEventListener</code> blocked by <code>stopPropagation()</code> on modal; moved to <code>$(\'#tmpmp-user-modal\').on()</code> jQuery delegation.',
                    'Fixed: Import dropzone click not opening file picker — replaced <code>display:none</code> + JS trigger with <code>opacity:0; position:absolute</code> overlay input that browsers handle natively.',
                    'Fixed: Import button not working after drag-and-drop — dragged file never assigned to <code>fileInput.files</code>; fixed by storing file in <code>selectedFile</code> variable used by both drag-drop and picker paths.',
                    'Fixed: All Users tab not showing user list on page load — added explicit <code>display:block</code> + JS initialisation call.',
                    'Fixed: Password generator modal UI not resetting on re-open — added reset logic for eye icon, button label, strength bar, and copy button each time the modal opens.',
                ],
            ],
            '2.0.3' => [
                'date'     => '2026-05-20',
                'label'    => 'Profile & Dashboard Release',
                'subtitle' => 'AVATAR · ACCOUNT TAB · PASSWORD · BUG FIXES',
                'features' => [
                    'New: Profile picture upload in user dashboard (JPG, PNG, GIF, WebP up to 2 MB) with live preview and avatar persistence.',
                    'New: Account tab in user dashboard — My Profile section (display name, email, avatar) and Change Password section.',
                    'New: Reset Password link generation from the dashboard.',
                ],
                'bugfixes' => [
                    'Fixed: Avatar not persisting on live servers — switched from <code>update_user_meta()</code> cache path to direct DB write; added cache-buster query string to avatar URL.',
                    'Fixed: Profile save not persisting — explicit meta writes bypass object-cache layers (Redis/Memcached).',
                    'Fixed: Encoding fix: garbled ellipsis in address-generating text.',
                    'Fixed: FAQ accordion not clickable when used as standalone shortcode.',
                    'Fixed: <code>faq_enabled</code> toggle always saved as enabled due to <code>isset()</code> check not handling explicit <code>\'0\'</code> value.',
                    'Fixed: Garbled emoji mojibake removed from all admin toast notifications.',
                ],
            ],

            '2.0.1' => [
                'date'     => '2026-05-22',
                'label'    => 'Patch Release',
                'subtitle' => 'IMAP · MIME · RESPONSIVE · SPEED · GUIDE',
                'features' => [
                    'New: <code>parse_mime_parts()</code> recursive MIME parser — correctly extracts HTML and plain-text from <code>multipart/alternative</code> and <code>multipart/mixed</code> emails (fixes "No HTML content" for n8n, ImprovMX, Gmail emails).',
                    'New: Burst polling mode — after a new email is detected, the frontend re-polls every 5 seconds for 30 seconds to catch follow-up emails instantly.',
                    'New: Refresh button now triggers a live IMAP server fetch (not just a DB read), so clicking Refresh immediately checks for new mail.',
                    'New: Background IMAP poll interval reduced from 30 s to 15 s for faster automatic email delivery.',
                    'New: Responsive email rendering — inline <code>min-width</code> and fixed pixel widths stripped via JS DOM pass before rendering, eliminating horizontal scrollbars on mobile.',
                    'New: Mail Server Setup Guide card added to admin Setup Guide page with 4 hosting-type tabs (Shared Hosting, VPS/Dedicated, ImprovMX, Mailgun Webhook).',
                    'New: WP-Cron Setup step (Step 8) added to the admin Setup Guide with interactive tabs for aaPanel, cPanel, and SSH — commands auto-filled with your site URL.',
                    'New: Setup Guide progress bar updated to 8 milestones; WP-Cron step shows live status of <code>DISABLE_WP_CRON</code>.',
                ],
                'bugfixes' => [
                    'Fixed: <code>poll_socket_imap()</code> completely rewritten to use universal RFC822 fetch instead of server-specific <code>BODY[HEADER]</code>/<code>BODY[TEXT]</code> — now works on all IMAP servers including shared hosting.',
                    'Fixed: Orphaned duplicate code block (old poll body) removed from <code>class-tempmail-imap.php</code> that caused a fatal parse error on live servers.',
                    'Fixed: Domain-targeted IMAP SEARCH (<code>SEARCH TO "yourdomain.com"</code>) added to reduce polling load and improve message matching accuracy.',
                    'Fixed: Email regex changed from <code>.+?@</code> to <code>.*?@</code> — bare addresses (no display name) now matched correctly.',
                    'Fixed: <code>rsort()</code> applied to IMAP sequence numbers so newest 50 messages are always processed (previously kept oldest).',
                    'Fixed: HTML email tab showing "No HTML content" for multipart emails — caused by flat body decode that ignored MIME boundaries.',
                    'Fixed: Email iframe horizontal scrollbar — email templates with hardcoded <code>width="600"</code> and <code>min-width:600px</code> now scale to fit the viewer panel.',
                    'Fixed: Refresh button spinner duration increased to 1.2 s to match actual IMAP fetch time.',
                ],
            ],
            '2.0.0' => [
                'date'     => '2026-05-17',
                'label'    => 'Full SaaS Platform',
                'subtitle' => 'SUBSCRIPTIONS · PAYMENTS · ANALYTICS · API · OAUTH',
                'features' => [
                    'New: Subscription plans (Free / Starter / Pro / Business) with per-plan limits on inboxes, lifetime, storage, and domain access.',
                    'New: Multi-domain management — assign domains to categories (Free / Premium / VIP) and control access by plan.',
                    'New: Stripe & PayPal payment gateways with webhook verification and subscription lifecycle handling.',
                    'New: bKash & SSLCommerz Bangladesh local payment gateways.',
                    'New: API key system — developers can generate personal access tokens and query the REST API.',
                    'New: Ad monetization manager with placements (Top Banner, Inbox Sidebar, Between Emails) and CTR tracking.',
                    'New: Magic link authentication + Google OAuth social login.',
                    'New: User dashboard with full inbox history, plan status, and subscription management.',
                    'New: REST API v1 — addresses, emails, plans, domains, and webhook receiver endpoints.',
                    'New: Real server-cron endpoint for background IMAP polling on shared hosting.',
                    'New: Gutenberg block and Elementor widget support.',
                    'New: Dark / light mode toggle with premium gradient UI.',
                    'New: QR code generator for easy inbox sharing.',
                    'New: Analytics dashboard with revenue tracking and Chart.js graphs.',
                    'New: Configure Mail Server UI — sectioned IMAP/POP3/Webhook settings with live connection test.',
                    'New: Admin settings redesigned as card-based sections matching the Mail Server design system.',
                ],
                'bugfixes' => [
                    'Fixed: UTC_TIMESTAMP() used consistently for all MySQL expiry comparisons to avoid timezone drift.',
                    'Fixed: Sandboxed iframe for HTML email rendering prevents CSS/JS injection from email content.',
                    'Fixed: Plain-text fallback now correctly extracted from HTML body when text part is absent.',
                    'Fixed: imap_pass and spam_filter fields now properly persisted in the settings save handler.',
                    'Fixed: Save All Settings button $(this) scope bug — button now correctly re-enables after AJAX completes.',
                ],
            ],
        ];
    }
}
