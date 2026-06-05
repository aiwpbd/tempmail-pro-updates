/**
 * TempMail Pro — Frontend JS v2.0.1
 * Actions: tmpmp_generate_email, tmpmp_get_inbox, tmpmp_get_email,
 *          tmpmp_delete_email, tmpmp_delete_inbox
 */
(function ($) {
    'use strict';

    const cfg = window.TempMailPro || {};
    const AJAX       = cfg.ajax_url         || '';
    const NONCE      = cfg.nonce            || '';
    const NONCE_REST = cfg.rest_nonce       || '';
    const INTERVAL   = cfg.refresh_interval || 30000;
    const STRINGS    = cfg.strings          || {};

    const PROTOCOL     = cfg.mail_protocol   || 'webhook';  // imap | pop3 | webhook
    const BG_INTERVAL  = cfg.bg_poll_interval || 45000;     // ms between IMAP fetches

    // SSE — premium users only
    const USE_SSE = cfg.use_sse === 1 && typeof EventSource !== 'undefined';
    const SSE_URL = cfg.sse_url || '';

    /* ── State ── */
    const TMP = {
        address    : '',
        session_id : '',
        address_id : 0,
        expires_at : '',
        total_secs : 0,
        poll_timer : null,
        bg_timer   : null,        // background IMAP fetch timer
        expiry_timer: null,
        current_email_id: 0,
        sse_source      : null,   // EventSource (premium)
        sse_reconnect   : null,   // reconnect timeout
        sse_connected   : false,  // true while SSE stream is open
    };

    /* ── DOM refs ── */
    const $wrap       = $('#tmpmp-main');
    const $addrText   = $('#tmpmp-address');
    const $copyBtn    = $('#tmpmp-copy-btn');
    const $qrBtn      = $('#tmpmp-qr-btn');
    const $genBtn     = $('#tmpmp-generate-btn');
    const $delBtn     = $('#tmpmp-delete-btn');
    const $refreshBtn = $('#tmpmp-refresh-btn');
    const $domainSel  = $('#tmpmp-domain-select');
    const $customUser = $('#tmpmp-custom-username');
    const $emailList  = $('#tmpmp-email-list');
    const $emptyState = $('#tmpmp-empty-state');
    const $countdown  = $('#tmpmp-expiry-countdown');
    const $progFill   = $('#tmpmp-expiry-bar');
    const $qrModal    = $('#tmpmp-qr-modal');
    const $qrClose    = $('#tmpmp-qr-close');
    const $qrContainer= $('#tmpmp-qr-container');
    const $qrAddress  = $('#tmpmp-qr-address');
    const $viewerPanel= $('#tmpmp-viewer-panel');
    const $viewerBack = $('#tmpmp-viewer-back');
    const $viewerDel  = $('#tmpmp-viewer-delete');
    const $viewerSubj = $('#tmpmp-viewer-subject');
    const $viewerMeta = $('#tmpmp-viewer-meta');
    const $bodyHtml   = $('#tmpmp-view-body-html');
    const $bodyText   = $('#tmpmp-view-body-text');
    const $toasts     = $('#tmpmp-toast-container');
    const $rlBanner   = $('#tmpmp-rl-banner');
    const $rlMsg      = $('#tmpmp-rl-msg');
    const $rlClose    = $('#tmpmp-rl-close');
    const $soundBtn   = $('#tmpmp-sound-btn');

    /* ══════════════════════════════════════════════════════════════
       SOUND NOTIFICATION
    ══════════════════════════════════════════════════════════════ */
    let soundEnabled = true;
    try { soundEnabled = JSON.parse(localStorage.getItem('tmpmp_sound') ?? 'true'); } catch(_){}
    let lastEmailCount = -1; // -1 = first poll, skip chime

    function updateSoundBtn() {
        if (soundEnabled) {
            $soundBtn.attr('title', 'Sound On — click to mute').removeClass('sound-off').html(
                '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"/><path d="M19.07 4.93a10 10 0 010 14.14M15.54 8.46a5 5 0 010 7.07"/></svg>'
            );
        } else {
            $soundBtn.attr('title', 'Sound Off — click to enable').addClass('sound-off').html(
                '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"/><line x1="23" y1="9" x2="17" y2="15"/><line x1="17" y1="9" x2="23" y2="15"/></svg>'
            );
        }
    }

    $soundBtn.on('click', function () {
        soundEnabled = !soundEnabled;
        try { localStorage.setItem('tmpmp_sound', JSON.stringify(soundEnabled)); } catch(_){}
        updateSoundBtn();
        if (soundEnabled) playChime(); // preview
    });

    function playChime() {
        if (!soundEnabled) return;
        try {
            const ctx = new (window.AudioContext || window.webkitAudioContext)();
            // Two-tone soft bell: high note then lower note
            const notes = [
                { freq: 1046.5, start: 0,    dur: 0.25 }, // C6
                { freq: 880,    start: 0.15, dur: 0.35 }, // A5
            ];
            notes.forEach(function (n) {
                const osc  = ctx.createOscillator();
                const gain = ctx.createGain();
                osc.connect(gain);
                gain.connect(ctx.destination);
                osc.type      = 'sine';
                osc.frequency.value = n.freq;
                gain.gain.setValueAtTime(0, ctx.currentTime + n.start);
                gain.gain.linearRampToValueAtTime(0.22, ctx.currentTime + n.start + 0.02);
                gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + n.start + n.dur);
                osc.start(ctx.currentTime + n.start);
                osc.stop(ctx.currentTime + n.start + n.dur);
            });
            // Close context after sound finishes
            setTimeout(function () { ctx.close(); }, 700);
        } catch (_) {}
    }

    /* ══════════════════════════════════════════════════════════════
       RATE LIMIT BANNER
    ══════════════════════════════════════════════════════════════ */
    function showRateLimitBanner(msg) {
        $rlMsg.text(msg || 'You have reached the email generation limit. Please wait before generating a new address.');
        $rlBanner.slideDown(250);
        $addrText.text('—');
    }

    function hideRateLimitBanner() {
        $rlBanner.slideUp(200);
    }

    $rlClose.on('click', hideRateLimitBanner);

    /* ══════════════════════════════════════════════════════════════
       AJAX helper
    ══════════════════════════════════════════════════════════════ */
    function ajax(action, data, done, fail) {
        $.post(AJAX, $.extend({ action, nonce: NONCE }, data))
            .done(function (res) {
                if (res && res.success) {
                    if (typeof done === 'function') done(res.data);
                } else {
                    const msg  = res && res.data && res.data.message ? res.data.message : (STRINGS.error_generic || 'Something went wrong.');
                    const code = res && res.data && res.data.code   ? res.data.code   : '';
                    if (typeof fail === 'function') fail(msg, code); else toast(msg, 'error');
                }
            })
            .fail(function () {
                const msg = STRINGS.error_generic || 'Request failed.';
                if (typeof fail === 'function') fail(msg, ''); else toast(msg, 'error');
            });
    }

    /* ══════════════════════════════════════════════════════════════
       SESSION PERSISTENCE
    ══════════════════════════════════════════════════════════════ */
    function saveSession() {
        try {
            localStorage.setItem('tmpmp_session', JSON.stringify({
                address    : TMP.address,
                session_id : TMP.session_id,
                address_id : TMP.address_id,
                expires_at : TMP.expires_at,
            }));
        } catch (_) {}
    }

    function loadSession() {
        try {
            const s = JSON.parse(localStorage.getItem('tmpmp_session') || 'null');
            if (s && s.address && s.expires_at) {
                const exp = new Date(s.expires_at.replace(' ', 'T') + 'Z').getTime();
                if (exp > Date.now()) return s;
            }
        } catch (_) {}
        return null;
    }

    function clearSession() {
        try { localStorage.removeItem('tmpmp_session'); } catch (_) {}
        TMP.address = TMP.session_id = TMP.expires_at = '';
        TMP.address_id = 0;
    }

    /* ══════════════════════════════════════════════════════════════
       GENERATE EMAIL
    ══════════════════════════════════════════════════════════════ */
    function generateEmail(force) {
        const domain   = $domainSel.val() || '';
        const username = ($customUser.val() || '').trim();

        hideRateLimitBanner();
        $addrText.text(STRINGS.generating || 'Generating…');
        stopPolling();
        clearExpiryTimer();

        ajax('tmpmp_generate_email',
            { session_id: force ? '' : TMP.session_id, domain, username },
            function (data) {
                TMP.address    = data.address;
                TMP.session_id = data.session_id;
                TMP.address_id = data.address_id;
                TMP.expires_at = data.expires_at;
                saveSession();

                $addrText.text(TMP.address);
                startExpiryTimer();
                startPolling();  // also (re)starts SSE for the new address
                pollInbox();
            },
            function (msg, code) {
                const rateCodes = ['rate_limit', 'rate_limited', 'blocked', 'too_many_requests'];
                if (code && rateCodes.includes(code)) {
                    showRateLimitBanner(msg);
                } else {
                    $addrText.text('Error: ' + msg);
                    toast(msg, 'error');
                }
            }
        );
    }

    /* ══════════════════════════════════════════════════════════════
       EXPIRY TIMER
    ══════════════════════════════════════════════════════════════ */
    function startExpiryTimer() {
        clearExpiryTimer();
        const exp = new Date(TMP.expires_at.replace(' ', 'T') + 'Z').getTime();
        TMP.total_secs = Math.max(0, Math.round((exp - Date.now()) / 1000));
        tickExpiry();
        TMP.expiry_timer = setInterval(tickExpiry, 1000);
    }

    function clearExpiryTimer() {
        if (TMP.expiry_timer) { clearInterval(TMP.expiry_timer); TMP.expiry_timer = null; }
        $countdown.text('--:--').removeClass('warn danger');
        $progFill.css('width', '100%').removeClass('warn danger');
    }

    function tickExpiry() {
        const exp  = new Date(TMP.expires_at.replace(' ', 'T') + 'Z').getTime();
        const left = Math.max(0, Math.round((exp - Date.now()) / 1000));
        const pct  = TMP.total_secs > 0 ? Math.round((left / TMP.total_secs) * 100) : 0;

        const m = String(Math.floor(left / 60)).padStart(2, '0');
        const s = String(left % 60).padStart(2, '0');
        $countdown.text(m + ':' + s);
        $progFill.css('width', pct + '%');

        $countdown.removeClass('warn danger');
        $progFill.removeClass('warn danger');
        if (left <= 60)  { $countdown.addClass('danger'); $progFill.addClass('danger'); }
        else if (left <= 300) { $countdown.addClass('warn'); $progFill.addClass('warn'); }

        if (left <= 0) {
            clearExpiryTimer();
            stopPolling();
            toast(STRINGS.email_expired || 'Inbox has expired.', 'error');
            clearSession();
            $addrText.text('—');
        }
    }

    /* ══════════════════════════════════════════════════════════════
       INBOX POLLING  (two-tier: SSE for premium, interval for free)
    ══════════════════════════════════════════════════════════════ */
    function startPolling() {
        stopPolling();
        // DB refresh interval — premium: 30s fallback, free: 30-45s primary
        TMP.poll_timer = setInterval(pollInbox, INTERVAL);
        // IMAP background fetch
        if (PROTOCOL === 'imap' || PROTOCOL === 'pop3') {
            startBgPoll();
        }
        // Premium: open SSE connection for near-instant push
        if (USE_SSE && TMP.address) {
            startSSE();
        }
    }

    function stopPolling() {
        if (TMP.poll_timer) { clearInterval(TMP.poll_timer); TMP.poll_timer = null; }
        stopBgPoll();
        stopSSE();
    }

    /* ── Background IMAP/POP3 fetch (triggers server-side mail retrieval) ── */
    function startBgPoll() {
        stopBgPoll();
        bgPollImap(); // immediate first fetch
        TMP.bg_timer = setInterval(bgPollImap, BG_INTERVAL);
    }

    function stopBgPoll() {
        if (TMP.bg_timer) { clearInterval(TMP.bg_timer); TMP.bg_timer = null; }
    }

    /**
     * Ask the server to fetch new messages from the IMAP/POP3 mailbox.
     * Burst mode: after finding new mail, re-poll every 5s for 30s.
     */
    let burstTimer = null;
    function bgPollImap(isBurst) {
        if (!TMP.address) return;
        ajax('tmpmp_background_poll_imap', {}, function (data) {
            if (data && (data.stored > 0 || data.fetched > 0)) {
                pollInbox();
                if (!isBurst) {
                    if (burstTimer) clearInterval(burstTimer);
                    let burstCount = 0;
                    burstTimer = setInterval(function() {
                        burstCount++;
                        bgPollImap(true);
                        if (burstCount >= 6) {
                            clearInterval(burstTimer);
                            burstTimer = null;
                        }
                    }, 5000);
                } else {
                    pollInbox();
                }
            }
        });
    }

    /* ══════════════════════════════════════════════════════════════
       SSE CLIENT  (premium users — near-instant push, ~2s DB check)
    ══════════════════════════════════════════════════════════════ */
    function startSSE() {
        stopSSE();
        if (!TMP.address) return;
        const url = SSE_URL
            + '?address=' + encodeURIComponent(TMP.address)
            + '&nonce='   + encodeURIComponent(NONCE_REST);

        try {
            TMP.sse_source = new EventSource(url);
        } catch (e) {
            // EventSource not available — fall back to interval polling silently
            return;
        }

        TMP.sse_source.onopen = function () {
            TMP.sse_connected = true;
            updateSSEBadge('live');
        };

        TMP.sse_source.onmessage = function (e) {
            try {
                const d = JSON.parse(e.data);
                if (d.error) {
                    // premium_required / not_found — stop trying
                    stopSSE();
                    updateSSEBadge('off');
                    return;
                }
                if (d.new_emails > 0) {
                    pollInbox();      // instant DB refresh
                    bgPollImap(true); // also re-fetch IMAP to catch more
                }
                if (d.reconnect) {
                    // Server's 55s window ended — reconnect immediately
                    stopSSE();
                    updateSSEBadge('reconnecting');
                    TMP.sse_reconnect = setTimeout(startSSE, 300);
                }
            } catch (_) {}
        };

        TMP.sse_source.onerror = function () {
            TMP.sse_connected = false;
            stopSSE();
            updateSSEBadge('reconnecting');
            // Retry after 5s
            TMP.sse_reconnect = setTimeout(startSSE, 5000);
        };
    }

    function stopSSE() {
        if (TMP.sse_source) {
            TMP.sse_source.close();
            TMP.sse_source = null;
        }
        if (TMP.sse_reconnect) {
            clearTimeout(TMP.sse_reconnect);
            TMP.sse_reconnect = null;
        }
        TMP.sse_connected = false;
    }

    /* ── Live status badge (shown only for premium SSE users) ── */
    function updateSSEBadge(state) {
        if (!USE_SSE) return;
        let $badge = $('#tmpmp-sse-badge');
        if (!$badge.length) {
            // Inject badge next to the refresh button on first call
            $badge = $('<span id="tmpmp-sse-badge"></span>');
            $refreshBtn.after($badge);
        }
        $badge.removeClass('sse-live sse-reconnecting sse-off');
        if (state === 'live') {
            $badge.addClass('sse-live').html(
                '<span class="sse-dot"></span> Live'
            );
        } else if (state === 'reconnecting') {
            $badge.addClass('sse-reconnecting').text('↻ Reconnecting…');
        } else {
            $badge.addClass('sse-off').text('⏱ Polling');
        }
    }

    function pollInbox() {
        if (!TMP.address) {
            // No address yet — auto-generate instead of silently doing nothing
            generateEmail(false);
            return;
        }
        ajax('tmpmp_get_inbox', { address: TMP.address, _t: Date.now() }, function (data) {
            renderInbox(data.emails || []);
            // Sync expiry timer from server so client & server stay aligned
            if (data.expires_at) {
                TMP.expires_at = data.expires_at;
                saveSession();
            }
            if (typeof data.remaining === 'number' && data.remaining > 0) {
                TMP.total_secs = Math.max(TMP.total_secs, data.remaining);
            }
        }, function (msg, code) {
            // Expired inbox → clear state and auto-generate a fresh address
            if (code === 'expired' || code === 'not_found' || code === 'address_not_found' || code === 'no_inbox') {
                clearSession();
                stopPolling();
                clearExpiryTimer();
                toast(STRINGS.email_expired || 'Inbox expired. Generating new address…', 'error');
                setTimeout(function () { generateEmail(false); }, 1500);
            } else {
                toast(msg, 'error');
            }
        });
    }

    function renderInbox(emails) {
        const newCount = emails.length;

        // Play chime only when new emails arrive (skip first poll)
        if (lastEmailCount >= 0 && newCount > lastEmailCount) {
            playChime();
            toast('📬 New email received!', 'success');
            // Ring bell icon animation (visual feedback even if sound muted)
            $soundBtn.addClass('ringing');
            setTimeout(function () { $soundBtn.removeClass('ringing'); }, 600);
        }
        lastEmailCount = newCount;

        if (!newCount) {
            $emailList.html($emptyState[0] ? $emptyState[0].outerHTML : '<div class="tmpmp-empty-state"><div class="tmpmp-empty-icon">📭</div><p>No emails yet — waiting…</p></div>');
            return;
        }
        let html = '';
        emails.forEach(function (e) {
            const cls  = e.is_read ? 'read' : 'unread';
            const time = formatTime(e.received_at);
            html += `<div class="tmpmp-email-row ${cls}" data-id="${e.id}">
                <span class="tmpmp-email-row-sender">${escHtml(e.sender_name || e.sender)}</span>
                <span class="tmpmp-email-row-subject">${escHtml(e.subject || '(no subject)')}</span>
                <span class="tmpmp-email-row-time">${time}</span>
            </div>`;
        });
        $emailList.html(html);
        $emailList.find('.tmpmp-email-row').on('click', function () {
            openEmail($(this).data('id'));
        });
    }


    /* ══════════════════════════════════════════════════════════════
       EMAIL VIEWER
    ══════════════════════════════════════════════════════════════ */
    /* ── Prepare email HTML: move <style> from body→head using real DOM parsing ── */
    function prepareEmailHtml(raw) {
        try {
            // Parse the raw HTML into a proper DOM tree
            const parser = new DOMParser();
            const doc = parser.parseFromString(raw, 'text/html');

            // Move ALL <style> elements found inside <body> up to <head>
            const bodyStyles = doc.body ? doc.body.querySelectorAll('style') : [];
            bodyStyles.forEach(function(styleEl) {
                doc.head.appendChild(styleEl);
            });

            // Also move any stray <link rel="stylesheet"> in body to head
            const bodyLinks = doc.body ? doc.body.querySelectorAll('link[rel="stylesheet"]') : [];
            bodyLinks.forEach(function(linkEl) {
                doc.head.appendChild(linkEl);
            });

            // Add viewport meta if missing
            if (!doc.querySelector('meta[name="viewport"]')) {
                const meta = doc.createElement('meta');
                meta.name    = 'viewport';
                meta.content = 'width=device-width,initial-scale=1';
                doc.head.insertBefore(meta, doc.head.firstChild);
            }

            // ── Security: strip all scripts and inline event handlers ───────────
            // Prevents "Blocked script execution" console errors on live servers.
            // Legitimate HTML emails never need JS to display correctly.
            doc.querySelectorAll('script, noscript').forEach(function(el) { el.remove(); });
            doc.querySelectorAll('*').forEach(function(el) {
                Array.from(el.attributes).forEach(function(attr) {
                    if (/^on/i.test(attr.name)) el.removeAttribute(attr.name);
                });
                // Also block javascript: hrefs / src
                ['href','src','action'].forEach(function(a) {
                    const val = el.getAttribute(a);
                    if (val && /^\s*javascript:/i.test(val)) el.setAttribute(a, '#');
                });
            });

            // ── DOM pass: strip inline min-width and fixed px widths ──────────
            // CSS !important cannot override inline styles, so we must remove them
            const allEls = doc.querySelectorAll('*');
            allEls.forEach(function(el) {
                const s = el.style;
                if (!s) return;

                // Remove min-width (e.g. "min-width:600px!important")
                if (s.minWidth) s.minWidth = '';

                // Convert fixed pixel widths → 100% (but leave % and 'auto' alone)
                if (s.width && s.width.indexOf('px') !== -1) s.width = '100%';

                // Remove fixed pixel max-width that clamps layout
                if (s.maxWidth && s.maxWidth.indexOf('px') !== -1) s.maxWidth = '100%';
            });

            // Remove deprecated width/height attributes on <table>, <td>, <img>
            doc.querySelectorAll('[width]').forEach(function(el) {
                const w = parseInt(el.getAttribute('width'), 10);
                if (w > 0) el.removeAttribute('width'); // let CSS take over
            });

            // ── CSS reset: catch anything the DOM pass missed ─────────────────
            const resetStyle = doc.createElement('style');
            resetStyle.textContent =
                'html,body{width:100%!important;max-width:100%!important;overflow-x:hidden!important;margin:0!important;padding:0!important;}' +
                '*{max-width:100%!important;min-width:0!important;box-sizing:border-box!important;}' +
                'table{width:100%!important;}' +
                'td,th{word-break:break-word;}' +
                'img{height:auto!important;max-width:100%!important;display:block;}';
            doc.head.appendChild(resetStyle);

            return '<!DOCTYPE html>\n' + doc.documentElement.outerHTML;
        } catch(e) {
            // Fallback: return raw as-is
            return raw;
        }
    }

    function openEmail(emailId) {
        if (!TMP.address || !emailId) return;
        TMP.current_email_id = emailId;
        ajax('tmpmp_get_email', { email_id: emailId, address: TMP.address }, function (data) {
            $viewerSubj.text(data.subject || '(no subject)');
            $viewerMeta.html(
                `<strong>From:</strong> ${escHtml(data.sender_name || '')} &lt;${escHtml(data.sender)}&gt;<br>` +
                `<strong>Date:</strong> ${formatTime(data.received_at)}`
            );
            // HTML body — use srcdoc (no doc.write, no console errors)
            if (data.body_html) {
                const iframe = document.createElement('iframe');
                iframe.className = 'tmpmp-email-iframe';
                // allow-same-origin needed for height detection;
                // scripts are already stripped by prepareEmailHtml so nothing gets blocked
                iframe.setAttribute('sandbox', 'allow-same-origin allow-popups allow-popups-to-escape-sandbox');
                $bodyHtml.html('').append(iframe);
                iframe.srcdoc = prepareEmailHtml(data.body_html);
                // Auto-resize iframe to full email height after render
                const resize = function() {
                    try {
                        const h = iframe.contentDocument.documentElement.scrollHeight || 400;
                        iframe.style.minHeight = Math.max(h, 300) + 'px';
                    } catch(e) {}
                };
                iframe.onload = resize;
                setTimeout(resize, 300);
                setTimeout(resize, 900);
            } else {
                $bodyHtml.html('<p style="color:var(--text3);font-size:13px;padding:16px;">No HTML content.</p>');
            }
            $bodyText.text(data.body_text || '');
            $viewerPanel.removeAttr('hidden');
            // Mobile: switch to viewer-only mode (hides inbox panel)
            if (window.innerWidth <= 800) {
                $wrap.addClass('mobile-viewing');
                $wrap[0].scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
            // Mark row as read
            $emailList.find('.tmpmp-email-row[data-id="' + emailId + '"]').removeClass('unread').addClass('read');
        }, function (msg, code) {
            // Email no longer exists (deleted / expired) — silently refresh inbox
            if (code === 'not_found') {
                pollInbox();
                return;
            }
            // Inbox itself has expired — trigger session recovery
            if (code === 'expired' || code === 'no_inbox' || code === 'address_not_found') {
                clearSession();
                stopPolling();
                clearExpiryTimer();
                toast(STRINGS.email_expired || 'Inbox expired. Generating new address…', 'error');
                setTimeout(function () { generateEmail(false); }, 1500);
                return;
            }
            // Unexpected error — show briefly
            toast(msg || 'Could not load email.', 'error');
        });
    }


    /* ══════════════════════════════════════════════════════════════
       DELETE
    ══════════════════════════════════════════════════════════════ */
    function deleteInbox() {
        if (!TMP.address) return;
        if (!confirm('Delete this inbox and all its emails?')) return;
        ajax('tmpmp_delete_inbox', { address: TMP.address, session_id: TMP.session_id }, function () {
            stopPolling();
            clearExpiryTimer();
            clearSession();
            $addrText.text('—');
            $emailList.html($('<div class="tmpmp-empty-state"><div class="tmpmp-empty-icon">📭</div><p>No emails yet — waiting…</p></div>'));
            $viewerPanel.attr('hidden', '');
            $wrap.removeClass('mobile-viewing'); // restore inbox on mobile
            toast('Inbox deleted.', 'info');
        });
    }


    function deleteViewerEmail() {
        if (!TMP.current_email_id || !TMP.address) return;
        ajax('tmpmp_delete_email', { email_id: TMP.current_email_id, address: TMP.address }, function () {
            $viewerPanel.attr('hidden', '');
            $wrap.removeClass('mobile-viewing'); // restore inbox on mobile
            toast('Email deleted.', 'info');
            pollInbox();
        });
    }

    /* ══════════════════════════════════════════════════════════════
       COPY TO CLIPBOARD
    ══════════════════════════════════════════════════════════════ */
    function copyAddress() {
        if (!TMP.address) return;
        const success = STRINGS.copy_success || 'Copied!';
        const fail    = STRINGS.copy_fail    || 'Copy failed';

        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(TMP.address)
                .then(function () { toast(success, 'success'); })
                .catch(function () { fallbackCopy(fail, success); });
        } else {
            fallbackCopy(fail, success);
        }
    }

    function fallbackCopy(failMsg, successMsg) {
        const ta = document.createElement('textarea');
        ta.value = TMP.address;
        ta.style.cssText = 'position:fixed;top:-9999px;left:-9999px;opacity:0;';
        document.body.appendChild(ta);
        ta.focus(); ta.select();
        const ok = document.execCommand('copy');
        document.body.removeChild(ta);
        toast(ok ? successMsg : failMsg, ok ? 'success' : 'error');
    }

    /* ══════════════════════════════════════════════════════════════
       QR CODE
    ══════════════════════════════════════════════════════════════ */
    function showQr() {
        if (!TMP.address) return;
        $qrContainer.empty();
        $qrAddress.text(TMP.address);
        $qrModal.removeAttr('hidden');

        if (typeof QRCode !== 'undefined') {
            new QRCode($qrContainer[0], {
                text         : TMP.address,
                width        : 200,
                height       : 200,
                colorDark    : '#1e293b',
                colorLight   : '#ffffff',
                correctLevel : QRCode.CorrectLevel.M,
            });
        } else {
            $qrContainer.html('<p style="color:#ef4444;font-size:13px;">QR library not loaded.</p>');
        }
    }

    /* ══════════════════════════════════════════════════════════════
       TOAST NOTIFICATIONS
    ══════════════════════════════════════════════════════════════ */
    function toast(msg, type) {
        type = type || 'info';
        const $t = $('<div class="tmpmp-toast ' + type + '">' + escHtml(msg) + '</div>');
        $toasts.append($t);
        setTimeout(function () {
            $t.addClass('out');
            setTimeout(function () { $t.remove(); }, 350);
        }, 3000);
    }

    /* ══════════════════════════════════════════════════════════════
       TABS
    ══════════════════════════════════════════════════════════════ */
    $wrap.on('click', '.tmpmp-tab', function () {
        const tab = $(this).data('tab');
        $wrap.find('.tmpmp-tab').removeClass('active');
        $(this).addClass('active');
        $bodyHtml.toggleClass('active', tab === 'html');
        $bodyText.toggleClass('active', tab === 'text');
    });

    /* ══════════════════════════════════════════════════════════════
       UTILS
    ══════════════════════════════════════════════════════════════ */
    function escHtml(str) {
        return String(str || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }
    function escAttr(str) {
        return String(str || '').replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }
    function formatTime(dt) {
        if (!dt) return '';
        const d = new Date(dt.replace(' ', 'T') + 'Z');
        return d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    }

    /* ══════════════════════════════════════════════════════════════
       CUSTOM DOMAIN PICKER
    ══════════════════════════════════════════════════════════════ */
    var $domainPicker, $domainTriggerText, $domainTriggerCat, $domainPanel;

    function initDomainPicker() {
        var $wrap      = $domainSel.parent();
        var currentVal = $domainSel.val();

        // ─ Trigger button
        var $trigger = $(
            '<button class="tmpmp-domain-trigger" type="button" id="tmpmp-domain-trigger" aria-haspopup="listbox" aria-expanded="false">' +
                '<span class="tmpmp-domain-trigger-cat"></span>' +
                '<span class="tmpmp-domain-trigger-text">@' + (currentVal || '') + '</span>' +
                '<svg class="tmpmp-domain-trigger-chevron" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>' +
            '</button>'
        );

        // ─ Panel + inner scroll wrapper
        var $inner = $('<div class="tmpmp-domain-panel-inner" role="listbox">');
        var $panel = $('<div class="tmpmp-domain-panel" id="tmpmp-domain-panel">');

        $domainSel.find('optgroup').each(function () {
            var groupLabel = $(this).attr('label') || '';
            var $opts      = $(this).find('option');
            var groupCat   = $opts.first().data('cat') || 'free';
            var $group     = $('<div class="tmpmp-domain-group">');
            var $gl        = $('<div class="tmpmp-domain-group-label">').addClass(groupCat).text(groupLabel);
            $group.append($gl);

            $opts.each(function () {
                var val    = $(this).val();
                var cat    = $(this).data('cat')    || 'free';
                var locked = $(this).data('locked') == 1;
                var icon   = cat === 'premium' ? '⭐' : (cat === 'vip' ? '💎' : (cat === 'custom' ? '🌐' : ''));

                var $row = $('<button class="tmpmp-domain-opt" type="button" role="option">')
                    .attr('data-value', val)
                    .attr('data-cat', cat)
                    .attr('data-locked', locked ? '1' : '0');

                if (locked)        $row.addClass('locked');
                if (val === currentVal) $row.addClass('selected');

                if (icon) $row.append($('<span class="tmpmp-opt-icon">').text(icon));
                $row.append($('<span class="tmpmp-opt-name">').text('@' + val));
                $row.append($('<span class="tmpmp-opt-check">').text('✓'));
                if (locked) $row.append($('<span class="tmpmp-opt-lock">').text('🔒'));
                $group.append($row);
            });

            $inner.append($group);
        });

        // Fallback: flat options with no optgroups
        if (!$domainSel.find('optgroup').length) {
            var $group = $('<div class="tmpmp-domain-group">');
            $domainSel.find('option').each(function () {
                var val    = $(this).val();
                var cat    = $(this).data('cat')    || 'free';
                var locked = $(this).data('locked') == 1;
                var $row   = $('<button class="tmpmp-domain-opt" type="button" role="option">')
                    .attr('data-value', val).attr('data-cat', cat).attr('data-locked', locked ? '1' : '0');
                if (locked)        $row.addClass('locked');
                if (val === currentVal) $row.addClass('selected');
                $row.append($('<span class="tmpmp-opt-name">').text('@' + val));
                $row.append($('<span class="tmpmp-opt-check">').text('✓'));
                $group.append($row);
            });
            $inner.append($group);
        }

        $panel.append($inner);
        var $picker = $('<div class="tmpmp-domain-picker" id="tmpmp-domain-picker">');
        $picker.append($trigger, $panel);
        $wrap.prepend($picker);

        // Expose refs
        $domainPicker      = $picker;
        $domainTriggerText = $trigger.find('.tmpmp-domain-trigger-text');
        $domainTriggerCat  = $trigger.find('.tmpmp-domain-trigger-cat');
        $domainPanel       = $panel;
        _syncTrigger(currentVal);

        // ─ Toggle panel open/close
        $trigger.on('click', function (e) {
            e.stopPropagation();
            var isOpen = $panel.hasClass('open');
            _closePicker();
            if (!isOpen) {
                $panel.addClass('open');
                $trigger.addClass('open').attr('aria-expanded', 'true');
            }
        });

        // ─ Select an unlocked option → update hidden select → triggers existing change handler
        $panel.on('click', '.tmpmp-domain-opt:not(.locked)', function () {
            var val = $(this).data('value');
            $panel.find('.tmpmp-domain-opt').removeClass('selected');
            $(this).addClass('selected');
            _syncTrigger(val);
            _closePicker();
            $domainSel.val(val).trigger('change');
        });

        // ─ Click locked option → show upgrade modal
        $panel.on('click', '.tmpmp-domain-opt.locked', function (e) {
            e.stopPropagation();
            _closePicker();
            showUpgradeModal($(this).data('cat'));
        });

        // ─ Close on outside click / Escape
        $panel.on('click', function (e) { e.stopPropagation(); });
        $(document).on('click.domainPicker', _closePicker);
        $(document).on('keydown.domainPicker', function (e) {
            if (e.key === 'Escape') _closePicker();
        });

        // ─ Keep visual picker in sync when hidden select is changed externally
        $domainSel.on('change.picker', function () {
            var v = $(this).val();
            _syncTrigger(v);
            $panel.find('.tmpmp-domain-opt').removeClass('selected');
            $panel.find('.tmpmp-domain-opt[data-value="' + v + '"]').addClass('selected');
        });
    }

    function _syncTrigger(val) {
        if (!$domainTriggerText) return;
        var $opt = $domainPanel.find('.tmpmp-domain-opt[data-value="' + val + '"]');
        var cat  = $opt.data('cat') || 'free';
        var icon = cat === 'premium' ? '⭐' : (cat === 'vip' ? '💎' : (cat === 'custom' ? '🌐' : ''));
        $domainTriggerCat.text(icon);
        $domainTriggerText.text('@' + val);
    }

    function _closePicker() {
        if ($domainPanel) $domainPanel.removeClass('open');
        $('#tmpmp-domain-trigger').removeClass('open').attr('aria-expanded', 'false');
    }

    /* ══════════════════════════════════════════════════════════════
       EVENT BINDINGS
    ══════════════════════════════════════════════════════════════ */
    $genBtn.on('click',
       function () { generateEmail(true); });
    $delBtn.on('click',       deleteInbox);
    $refreshBtn.on('click', function () {
        if (!TMP.address) {
            toast('Generating a new email address…', 'info');
            generateEmail(false);
            return;
        }
        $refreshBtn.addClass('spinning');
        setTimeout(function () { $refreshBtn.removeClass('spinning'); }, 1200);
        if (PROTOCOL === 'imap' || PROTOCOL === 'pop3') {
            // For IMAP/POP3: fetch from mail server first, then update inbox
            bgPollImap();
        } else {
            pollInbox();
        }
    });
    $copyBtn.on('click',      copyAddress);
    $qrBtn.on('click',        showQr);
    $qrClose.on('click',      function () { $qrModal.attr('hidden', ''); $qrContainer.empty(); });
    $qrModal.on('click',      function (e) { if ($(e.target).is($qrModal)) { $qrModal.attr('hidden',''); $qrContainer.empty(); } });
    $viewerBack.on('click', function () {
        $viewerPanel.attr('hidden', '');
        $wrap.removeClass('mobile-viewing'); // restore inbox on mobile
    });
    $viewerDel.on('click',    deleteViewerEmail);

    // Domain change → check if locked → show upgrade prompt → otherwise regenerate
    $domainSel.on('change', function () {
        const $sel     = $(this);
        const $opt     = $sel.find('option:selected');
        const cat      = $opt.data('cat')  || 'free';
        const locked   = $opt.data('locked') == 1;

        if (locked) {
            // Reset back to last free domain so the locked domain isn't "used"
            const $firstFree = $sel.find('option[data-locked="0"]').first();
            $sel.val($firstFree.val());
            showUpgradeModal(cat);
            return;
        }

        if (TMP.address) generateEmail(true);
    });

    /* ─── Upgrade / Upsell Modal ─── */
    function showUpgradeModal(cat) {
        const isPremium  = cat === 'premium';
        const label      = isPremium ? 'Premium' : 'VIP';
        const icon       = isPremium ? '⭐' : '💎';
        const upgradeUrl = (TempMailPro.upgrade_url   || '').trim();
        const pricingUrl = (TempMailPro.pricing_url   || '').trim();
        const ctaText    = (TempMailPro.upgrade_box_cta_text    || '').trim() || ('Upgrade to ' + label + ' ' + icon);
        const priceLabel = (TempMailPro.upgrade_box_price_label || '').trim() || 'View Pricing';

        // Build features list (custom admin list, or built-in defaults)
        var customFeatures = TempMailPro.upgrade_box_features || [];
        var defaultFeatures = [
            'Exclusive ' + label + ' domains',
            'Extended inbox lifetime',
            'No ads, faster refresh',
        ];
        if (cat === 'vip' && !customFeatures.length) {
            defaultFeatures.push('Priority support & API access');
        }
        var featuresList = customFeatures.length ? customFeatures : defaultFeatures;
        var featuresHtml = featuresList.map(function(f) {
            return '<div class="tmpmp-upgrade-feature"><span>✓</span> ' + escHtml(f) + '</div>';
        }).join('');

        // Build action buttons
        var actionsHtml = '';
        if (upgradeUrl) {
            actionsHtml += '<a href="' + escAttr(upgradeUrl) + '" class="tmpmp-btn tmpmp-upgrade-cta" id="tmpmp-upgrade-cta">' + escHtml(ctaText) + '</a>';
        }
        if (pricingUrl) {
            actionsHtml += '<a href="' + escAttr(pricingUrl) + '" class="tmpmp-upgrade-pricing-btn" id="tmpmp-upgrade-pricing-btn">'
                + '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/></svg>'
                + escHtml(priceLabel)
                + '</a>';
        }
        if (!actionsHtml) {
            actionsHtml = '<p class="tmpmp-upgrade-noctx">Contact the administrator to upgrade your plan.</p>';
        }

        // Remove any existing modal
        $('#tmpmp-upgrade-modal').remove();

        const $modal = $(
            '<div id="tmpmp-upgrade-modal" role="dialog" aria-modal="true" aria-label="Upgrade required">' +
            '<div class="tmpmp-upgrade-box">' +
                '<button class="tmpmp-upgrade-close" id="tmpmp-upgrade-close" aria-label="Close">&times;</button>' +
                '<div class="tmpmp-upgrade-icon">' + icon + '</div>' +
                '<h3 class="tmpmp-upgrade-title">' + label + ' Domain</h3>' +
                '<p class="tmpmp-upgrade-desc">This domain is exclusive to <strong>' + label + '</strong> plan members. Upgrade your plan to unlock it and enjoy premium features.</p>' +
                '<div class="tmpmp-upgrade-features">' + featuresHtml + '</div>' +
                '<div class="tmpmp-upgrade-actions">' + actionsHtml + '</div>' +
            '</div>' +
            '</div>'
        );

        $wrap.append($modal);
        setTimeout(function () { $modal.addClass('tmpmp-upgrade-show'); }, 10);

        // Close handlers
        $modal.on('click', '#tmpmp-upgrade-close', closeUpgradeModal);
        $modal.on('click', function (e) { if ($(e.target).is($modal)) closeUpgradeModal(); });
        $(document).one('keydown.upgradeModal', function (e) { if (e.key === 'Escape') closeUpgradeModal(); });
    }

    function closeUpgradeModal() {
        const $m = $('#tmpmp-upgrade-modal');
        $m.removeClass('tmpmp-upgrade-show');
        $(document).off('keydown.upgradeModal');
        setTimeout(function () { $m.remove(); }, 300);
    }

    /* ══════════════════════════════════════════════════════════════
       INBOX HISTORY POPUP MODAL (Premium users only)
    ══════════════════════════════════════════════════════════════ */
    var $historyBtn     = $('#tmpmp-history-btn');
    var $historyModal   = $('#tmpmp-history-modal');   // outer wrapper
    var $historyDrawer  = $('#tmpmp-history-drawer');  // the modal box (legacy ID kept for tab events)
    var $historyOverlay = $('#tmpmp-history-overlay');
    var $historyClose   = $('#tmpmp-history-close');
    var $historyList    = $('#tmpmp-history-list');
    var $historyPag     = $('#tmpmp-history-pagination');
    var $historyCount   = $('#tmpmp-history-count');
    var $historyLimitTx = $('#tmpmp-history-limit-text');

    // Sub-view refs
    var $hListView      = $('#tmpmp-history-list-view');
    var $hEmailView     = $('#tmpmp-history-email-view');
    var $hBodyView      = $('#tmpmp-history-body-view');
    var $hEmailAddr     = $('#tmpmp-history-email-addr');
    var $hEmailList     = $('#tmpmp-history-email-list');
    var $hBodySubject   = $('#tmpmp-history-body-subject');
    var $hBodyMeta      = $('#tmpmp-history-body-meta');
    var $hHtml          = $('#tmpmp-history-html');
    var $hText          = $('#tmpmp-history-text');

    var historyState = {
        open          : false,
        page          : 1,
        perPage       : 10,
        total         : 0,
        currentAddrId : 0,
        currentAddr   : '',
        planMaxInboxes: -1,
    };

    /* ── Open / Close modal ── */
    function openHistoryDrawer() {
        historyState.open = true;
        $historyModal.addClass('open').attr('aria-hidden', 'false');
        $historyBtn.addClass('is-active');
        $('body').css('overflow', 'hidden');
        showHistoryListView();
        loadHistory(1);
    }

    function closeHistoryDrawer() {
        historyState.open = false;
        $historyModal.removeClass('open').attr('aria-hidden', 'true');
        $historyBtn.removeClass('is-active');
        $('body').css('overflow', '');
    }

    /* ── Show sub-views ── */
    function showHistoryListView() {
        $hListView.show();
        $hEmailView.hide();
        $hBodyView.hide();
    }

    function showHistoryEmailView(addrId, addr) {
        historyState.currentAddrId = addrId;
        historyState.currentAddr   = addr;
        $hEmailAddr.text(addr);
        $hListView.hide();
        $hEmailView.css('display', 'flex');
        $hBodyView.hide();
        loadHistoryEmails(addrId);
    }

    function showHistoryBodyView(emailId, addrId) {
        $hEmailView.hide();
        $hBodyView.css('display', 'flex');
        loadHistoryEmailBody(emailId, addrId);
    }

    /* ── Load address history list ── */
    function loadHistory(page) {
        historyState.page = page;
        // Show skeletons
        $historyList.html(
            '<div class="tmpmp-skeleton" style="height:52px;margin:4px 0"></div>' +
            '<div class="tmpmp-skeleton" style="height:52px;margin:4px 0"></div>' +
            '<div class="tmpmp-skeleton" style="height:52px;margin:4px 0"></div>'
        );
        $historyPag.empty();

        ajax('tmpmp_get_address_history', { page: page, per_page: historyState.perPage },
            function (data) {
                historyState.total = data.total || 0;
                renderHistoryList(data.rows || []);
                renderHistoryPagination(data.total, data.per_page, data.page);

                // Update count / plan info
                $historyCount.text(data.total + ' address' + (data.total !== 1 ? 'es' : ''));
                updateHistoryLimitBar(data.total);
            },
            function (msg) {
                $historyList.html('<div class="tmpmp-history-empty"><div class="tmpmp-history-empty-icon">🔒</div><p>' + escHtml(msg) + '</p></div>');
            }
        );
    }

    function updateHistoryLimitBar(total) {
        var max = historyState.planMaxInboxes;
        // Try to read plan limit from cfg if available
        if (cfg.plan_max_inboxes !== undefined) {
            max = parseInt(cfg.plan_max_inboxes, 10);
            historyState.planMaxInboxes = max;
        }
        var plan = (cfg.plan_name || '').trim();
        var keepDays = (cfg.plan_history_days || 90);

        if (max === -1 || max === 0) {
            $historyLimitTx.text(
                (plan ? plan + ' plan · ' : '') +
                'Unlimited inboxes · History kept for ' + keepDays + ' days'
            );
        } else {
            $historyLimitTx.text(
                (plan ? plan + ' plan · ' : '') +
                total + ' / ' + max + ' inboxes used · History kept for ' + keepDays + ' days'
            );
        }
    }

    /* ── Render list of address rows ── */
    function renderHistoryList(rows) {
        if (!rows.length) {
            $historyList.html(
                '<div class="tmpmp-history-empty">' +
                '<div class="tmpmp-history-empty-icon">' +
                '<svg width="44" height="44" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2" style="opacity:.3;color:var(--c-primary)"><rect x="2" y="4" width="20" height="16" rx="2"/><polyline points="2 7 12 13 22 7"/></svg>' +
                '</div>' +
                '<p>No address history yet.<br>Addresses you generate while logged in will appear here.</p>' +
                '</div>'
            );
            return;
        }
        var html = '';
        rows.forEach(function (r) {
            var isActive  = r.status_label === 'active';
            var emailCnt  = parseInt(r.email_count, 10) || 0;
            var created   = r.created_at ? formatDate(r.created_at) : '';

            // SVG icons — no broken emoji rendering
            var iconSvg = isActive
                ? '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="2" y="4" width="20" height="16" rx="2"/><polyline points="2 7 12 13 22 7"/></svg>'
                : '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="2" y="4" width="20" height="16" rx="2"/><line x1="2" y1="4" x2="22" y2="20"/></svg>';

            html +=
                '<div class="tmpmp-history-row" data-id="' + escAttr(String(r.id)) + '" data-addr="' + escAttr(r.address) + '">' +
                    '<div class="tmpmp-history-row-icon' + (isActive ? '' : ' expired') + '">' + iconSvg + '</div>' +
                    '<div class="tmpmp-history-row-info">' +
                        '<div class="tmpmp-history-row-addr">' + escHtml(r.address) + '</div>' +
                        '<div class="tmpmp-history-row-meta">' +
                            '<span class="tmpmp-history-row-status ' + (isActive ? 'active' : 'expired') + '">' + (isActive ? 'Active' : 'Expired') + '</span>' +
                            '<span class="tmpmp-history-row-meta-dot"></span>' +
                            '<span class="tmpmp-history-row-meta-text">' + emailCnt + ' email' + (emailCnt !== 1 ? 's' : '') + '</span>' +
                            '<span class="tmpmp-history-row-meta-dot"></span>' +
                            '<span class="tmpmp-history-row-meta-text">' + created + '</span>' +
                        '</div>' +
                    '</div>' +
                    '<button class="tmpmp-history-row-del" data-del-id="' + escAttr(String(r.id)) + '" title="Remove from history">' +
                        '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6M14 11v6"/><path d="M9 6V4h6v2"/></svg>' +
                    '</button>' +
                '</div>';
        });
        $historyList.html(html);

        // Drill-in to email list on row click
        $historyList.find('.tmpmp-history-row').on('click', function (e) {
            if ($(e.target).closest('.tmpmp-history-row-del').length) return;
            var id   = $(this).data('id');
            var addr = $(this).data('addr');
            showHistoryEmailView(id, addr);
        });

        // Delete address
        $historyList.find('.tmpmp-history-row-del').on('click', function (e) {
            e.stopPropagation();
            var id = $(this).data('del-id');
            if (!confirm('Delete this address and all its emails from history?')) return;
            ajax('tmpmp_delete_history_address', { address_id: id },
                function () {
                    toast('Address removed from history.', 'info');
                    loadHistory(historyState.page);
                },
                function (msg) { toast(msg, 'error'); }
            );
        });
    }

    /* ── Pagination ── */
    function renderHistoryPagination(total, perPage, currentPage) {
        $historyPag.empty();
        if (!total || !perPage || total <= perPage) return;
        var pages = Math.ceil(total / perPage);
        if (pages <= 1) return;

        if (currentPage > 1) {
            $historyPag.append(
                $('<button class="tmpmp-history-page-btn">‹ Prev</button>')
                    .on('click', function () { loadHistory(currentPage - 1); })
            );
        }
        for (var i = 1; i <= pages; i++) {
            (function(p) {
                var $b = $('<button class="tmpmp-history-page-btn">' + p + '</button>');
                if (p === currentPage) $b.addClass('current');
                $b.on('click', function () { loadHistory(p); });
                $historyPag.append($b);
            })(i);
        }
        if (currentPage < pages) {
            $historyPag.append(
                $('<button class="tmpmp-history-page-btn">Next ›</button>')
                    .on('click', function () { loadHistory(currentPage + 1); })
            );
        }
    }

    /* ── Load emails for a history address ── */
    function loadHistoryEmails(addrId) {
        $hEmailList.html(
            '<div class="tmpmp-skeleton" style="height:48px;margin:4px 0"></div>' +
            '<div class="tmpmp-skeleton" style="height:48px;margin:4px 0"></div>'
        );
        ajax('tmpmp_get_history_emails', { address_id: addrId },
            function (data) {
                renderHistoryEmails(data.emails || [], addrId);
            },
            function (msg) {
                $hEmailList.html('<div class="tmpmp-history-empty"><div class="tmpmp-history-empty-icon">❌</div><p>' + escHtml(msg) + '</p></div>');
            }
        );
    }

    function renderHistoryEmails(emails, addrId) {
        if (!emails.length) {
            $hEmailList.html(
                '<div class="tmpmp-history-empty">' +
                '<div class="tmpmp-history-empty-icon" style="font-size:32px;line-height:1;">✉️</div>' +
                '<p>No emails in this inbox.</p>' +
                '</div>'
            );
            return;
        }
        var html = '';
        emails.forEach(function (e) {
            var cls = e.is_read ? '' : ' unread';
            html +=
                '<div class="tmpmp-history-email-row' + cls + '" data-eid="' + escAttr(String(e.id)) + '" data-aid="' + escAttr(String(addrId)) + '">' +
                    '<div class="tmpmp-history-email-row-body">' +
                        '<div class="tmpmp-history-email-sender">' + escHtml(e.sender_name || e.sender) + '</div>' +
                        '<div class="tmpmp-history-email-subject">' + escHtml(e.subject || '(no subject)') + '</div>' +
                    '</div>' +
                    '<div class="tmpmp-history-email-time">' + formatTime(e.received_at) + '</div>' +
                '</div>';
        });
        $hEmailList.html(html);

        $hEmailList.find('.tmpmp-history-email-row').on('click', function () {
            var eid = $(this).data('eid');
            var aid = $(this).data('aid');
            showHistoryBodyView(eid, aid);
        });
    }

    /* ── Load & render single email body from history ── */
    function loadHistoryEmailBody(emailId, addrId) {
        $hBodySubject.text('Loading…');
        $hBodyMeta.empty();
        $hHtml.html('<div class="tmpmp-skeleton" style="height:200px;border-radius:10px;"></div>');
        $hText.empty();

        ajax('tmpmp_get_history_email_body', { email_id: emailId, address_id: addrId },
            function (data) {
                $hBodySubject.text(data.subject || '(no subject)');
                $hBodyMeta.html(
                    '<strong>From:</strong> ' + escHtml(data.sender_name || '') + ' &lt;' + escHtml(data.sender) + '&gt;<br>' +
                    '<strong>Date:</strong> ' + formatTime(data.received_at)
                );
                if (data.body_html) {
                    var hiframe = document.createElement('iframe');
                    hiframe.className = 'tmpmp-email-iframe';
                    hiframe.setAttribute('sandbox', 'allow-same-origin allow-popups allow-popups-to-escape-sandbox');
                    $hHtml.html('').append(hiframe);
                    hiframe.srcdoc = prepareEmailHtml(data.body_html);
                    var hresize = function() {
                        try {
                            var h = hiframe.contentDocument.documentElement.scrollHeight || 300;
                            hiframe.style.minHeight = Math.max(h, 200) + 'px';
                        } catch(ex) {}
                    };
                    hiframe.onload = hresize;
                    setTimeout(hresize, 300);
                    setTimeout(hresize, 900);
                } else {
                    $hHtml.html('<p style="color:var(--text3);font-size:13px;padding:16px;">No HTML content.</p>');
                }
                $hText.text(data.body_text || '');
            },
            function (msg) {
                $hBodySubject.text('Error');
                $hHtml.html('<p style="color:var(--danger);padding:16px;">' + escHtml(msg) + '</p>');
            }
        );
    }

    /* ── History body view tabs ── */
    $historyModal.on('click', '[data-htab]', function () {
        var tab = $(this).data('htab');
        $historyModal.find('[data-htab]').removeClass('active');
        $(this).addClass('active');
        $hHtml.toggleClass('active', tab === 'html');
        $hText.toggleClass('active', tab === 'text');
    });

    /* ── Back navigation ── */
    $('#tmpmp-history-back-btn').on('click', showHistoryListView);
    $('#tmpmp-history-body-back').on('click', function () {
        $hBodyView.hide();
        $hEmailView.css('display', 'flex');
    });

    /* ── Format date helper ── */
    function formatDate(dt) {
        if (!dt) return '';
        var d = new Date(dt.replace(' ', 'T') + 'Z');
        return d.toLocaleDateString([], { month: 'short', day: 'numeric', year: 'numeric' });
    }

    /* ── Event bindings ── */
    if ($historyBtn.length) {
        $historyBtn.on('click', function () {
            historyState.open ? closeHistoryDrawer() : openHistoryDrawer();
        });
        $historyClose.on('click', closeHistoryDrawer);
        // Click on overlay (not the box) closes modal
        $historyModal.on('click', function (e) {
            if ($(e.target).is($historyOverlay) || $(e.target).is($historyModal)) {
                closeHistoryDrawer();
            }
        });
        $(document).on('keydown.history', function (e) {
            if (e.key === 'Escape' && historyState.open) closeHistoryDrawer();
        });
    }

    /* ══════════════════════════════════════════════════════════════
       INIT
    ══════════════════════════════════════════════════════════════ */
    $(function () {
        updateSoundBtn();
        initDomainPicker();

        const saved = loadSession();
        if (saved) {
            TMP.address    = saved.address;
            TMP.session_id = saved.session_id;
            TMP.address_id = saved.address_id;
            TMP.expires_at = saved.expires_at;
            $addrText.text(TMP.address);
            // Sync domain dropdown
            if ($domainSel.find('option[value="' + TMP.address.split('@')[1] + '"]').length) {
                $domainSel.val(TMP.address.split('@')[1]);
            }
            startExpiryTimer();
            startPolling();
            pollInbox();
        } else {
            generateEmail(false);
        }
    });

    // Expose init helpers for AJAX-injected contexts (e.g. dashboard Inbox App tab)
    window.tmpmpInitDomainPicker = initDomainPicker;

}(jQuery));
