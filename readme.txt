=== TempMail Pro ===
Contributors: tempmail-pro
Tags: temporary email, disposable email, temp mail, fake email, spam protection, saas, email privacy
Requires at least: 5.8
Tested up to: 6.7
Stable tag: 2.1.1
Requires PHP: 8.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A full-featured temporary/disposable email SaaS platform for WordPress — subscriptions, multi-domain, REST API, payments, and monetization.

== Description ==

**TempMail Pro** transforms your WordPress site into a fully functional **temporary email SaaS platform** — similar to Guerrilla Mail or 10 Minute Mail, but self-hosted and fully monetized.

= 🚀 Core Features =

* **One-click temp email generation** — No registration required
* **Multi-domain management** — Free, Premium, and VIP domain tiers
* **Real-time inbox** — Auto-refreshes every 10 seconds
* **HTML email viewer** — Sandboxed iframe rendering with plain-text fallback
* **QR code sharing** — Share your inbox URL instantly
* **Expiry countdown bar** — Visual timer with color warnings
* **Copy to clipboard** — One-click address copying

= 💎 Subscription System =

* **4 built-in plans**: Free, Starter, Pro, Business
* **Custom usernames** on paid plans
* **Extended inbox lifetime** (up to 3 days on Business)
* **Private inboxes** on Pro+
* **No ads** on premium plans

= 💳 Payment Gateways =

* **Stripe** — Credit/debit cards, subscriptions
* **PayPal** — Global payments
* **SSLCommerz** — Bangladesh local gateway
* **bKash** — (credentials required)
* Webhook-based auto-renewal handling

= 📡 Mail Delivery Methods =

* **Webhook** — Mailgun, SendGrid, SparkPost, cPanel
* **IMAP/POP3 polling** — Native PHP or socket fallback
* **WP-Cron** — Automated 1-minute polling
* **Server cron** — External crontab endpoint for reliable delivery

= 🔑 REST API =

Full developer API at `/wp-json/tempmail-pro/v1/`:
* `POST /generate` — Create inbox
* `GET /inbox/{address}` — List emails
* `GET /email/{id}` — Read email
* `DELETE /email/{id}` — Delete email
* `POST /receive` — Receive via webhook
* `GET /domains` — List domains

= 📢 Monetization =

* **Google AdSense** code injection
* **Ad placement manager** — Top banner, bottom banner, sidebar
* **Impression & CTR tracking**
* **Ads hidden for premium users**

= 🛡️ Security =

* All AJAX endpoints are nonce-protected
* Timing-safe webhook secret verification
* IP-based rate limiting
* IP blocking/banning
* Input sanitization throughout
* Sandboxed email viewer

= 📊 Analytics =

* Email volume chart (30 days)
* Revenue chart (30 days)
* Top domains by usage
* Live stats on dashboard

= 🔧 Shortcodes =

* `[tempmail_app]` — Main inbox widget
* `[tempmail_pricing]` — Pricing/plans page
* `[tempmail_login]` — Magic-link login
* `[tempmail_dashboard]` — User account dashboard

= 🧱 Gutenberg Block =

Native Gutenberg block: **TempMail Pro** available in the Widgets category.

== Installation ==

1. Upload the `tempmail-pro` folder to `/wp-content/plugins/`
2. Activate via **Plugins → Installed Plugins**
3. Navigate to **📧 TempMail Pro** in the admin sidebar
4. Go to **Settings → Mail Server** to configure your mail delivery method
5. Add the `[tempmail_app]` shortcode to any page

= Mail Server Setup (Webhook) =

In your Mailgun/SendGrid dashboard, add a webhook pointing to:
`https://yoursite.com/wp-json/tempmail-pro/v1/receive`

With header: `X-TempMail-Secret: YOUR_WEBHOOK_SECRET`

= Mail Server Setup (IMAP) =

1. Go to Settings → Mail Server → Protocol: IMAP
2. Enter your IMAP host, port, username, and password
3. WP-Cron will auto-poll every minute
4. For reliability, add a real server cron (shown in dashboard)

== Frequently Asked Questions ==

= Does this work without a paid plan? =
Yes. The plugin includes a Free tier allowing 3 inboxes with 30-minute lifetime.

= Which payment gateways are supported? =
Stripe, PayPal, and SSLCommerz are fully integrated. bKash requires API credentials from bKash directly.

= Can I add my own domains? =
Yes. Go to **Domains** in the admin menu to add, categorize, and manage your domains.

= Does it support Gutenberg? =
Yes — a native block is registered for the inbox widget.

= Is there an API? =
Yes. The full REST API is available at `/wp-json/tempmail-pro/v1/`. API keys are generated in the user dashboard (Pro/Business plans).

= How do I ensure email delivery? =
Set up a real server cron using the command shown in the admin dashboard (Settings → Mail Server).

== Changelog ==

= 2.0.0 =
* Complete SaaS platform rebuild
* Subscription system with 4 plans
* Stripe, PayPal, SSLCommerz payment gateways
* API key system for developer access
* Ad monetization with placement manager
* Magic link & Google OAuth authentication
* Full REST API v1
* Gutenberg block
* Analytics dashboard with Chart.js
* Dark/light mode premium UI
* QR code inbox sharing
* Real server cron endpoint
* Multi-header IMAP recipient detection
* UTC_TIMESTAMP() timezone-safe queries
* Sandboxed iframe HTML email rendering

== Upgrade Notice ==

= 2.0.0 =
Full platform rewrite. Deactivate any previous version before upgrading. Run activation hook to create new database tables.

== Screenshots ==

1. Main inbox widget — dark mode
2. Admin dashboard with stats
3. Pricing page with plan cards
4. Settings page with mail server config
5. Analytics dashboard with charts
6. User account dashboard

== Privacy Policy ==

TempMail Pro stores temporary email addresses and message content in your WordPress database. All data is automatically purged after the inbox lifetime expires. No data is shared with third parties except as required by payment gateway integration (Stripe, PayPal).
