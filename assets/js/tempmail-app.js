/**
 * TempMail Pro — Frontend JS v2.0.0
 * Actions: tmpmp_generate_email, tmpmp_get_inbox, tmpmp_get_email,
 *          tmpmp_delete_email, tmpmp_delete_inbox
 */
(function ($) {
    'use strict';

    const cfg = window.TempMailPro || {};
    const AJAX      = cfg.ajax_url        || '';
    const NONCE     = cfg.nonce           || '';
    const INTERVAL  = cfg.refresh_interval || 10000;
    const STRINGS   = cfg.strings         || {};

    const PROTOCOL     = cfg.mail_protocol    || 'webhook';   // imap | pop3 | webhook
    const BG_INTERVAL  = cfg.bg_poll_interval  || 15000;       // ms between IMAP fetches (default 15s)

    /* ── State ── */
    const TMP = {
        address    : '',
        session_id : '',
        address_id : 0,
        expires_at : '',
        total_secs : 0,
        poll_timer : null,
        bg_timer   : null,   // background IMAP fetch timer
        expiry_timer: null,
        current_email_id: 0,
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
                startPolling();
                pollInbox();
            },
            function (msg, code) {
                // Rate-limit / blocked errors get the styled banner
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
       INBOX POLLING
    ══════════════════════════════════════════════════════════════ */
    function startPolling() {
        stopPolling();
        TMP.poll_timer = setInterval(pollInbox, INTERVAL);
        // For IMAP/POP3: also run a background server fetch on its own timer
        if (PROTOCOL === 'imap' || PROTOCOL === 'pop3') {
            startBgPoll();
        }
    }

    function stopPolling() {
        if (TMP.poll_timer) { clearInterval(TMP.poll_timer); TMP.poll_timer = null; }
        stopBgPoll();
    }

    /* ── Background IMAP/POP3 fetch (triggers server-side mail retrieval) ── */
    function startBgPoll() {
        stopBgPoll();
        // Immediate first fetch, then on interval
        bgPollImap();
        TMP.bg_timer = setInterval(bgPollImap, BG_INTERVAL);
    }

    function stopBgPoll() {
        if (TMP.bg_timer) { clearInterval(TMP.bg_timer); TMP.bg_timer = null; }
    }

    /**
     * Ask the server to fetch new messages from the IMAP/POP3 mailbox,
     * then immediately refresh the inbox list so new emails appear.
     * Burst mode: after finding new mail, re-poll every 5s for 30s.
     */
    let burstTimer = null;
    function bgPollImap(isBurst) {
        if (!TMP.address) return;
        ajax('tmpmp_background_poll_imap', {}, function (data) {
            if (data && (data.stored > 0 || data.fetched > 0)) {
                // New emails found — refresh inbox immediately
                pollInbox();
                // Start burst polling: check every 5s for 30s
                if (!isBurst) {
                    if (burstTimer) clearInterval(burstTimer);
                    let burstCount = 0;
                    burstTimer = setInterval(function() {
                        burstCount++;
                        bgPollImap(true);
                        if (burstCount >= 6) { // 6 × 5s = 30s
                            clearInterval(burstTimer);
                            burstTimer = null;
                        }
                    }, 5000);
                } else {
                    pollInbox(); // keep inbox fresh during burst
                }
            }
        });
    }

    function pollInbox() {
        if (!TMP.address) {
            // No address yet — auto-generate instead of silently doing nothing
            generateEmail(false);
            return;
        }
        ajax('tmpmp_get_inbox', { address: TMP.address }, function (data) {
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
            if (code === 'expired' || code === 'not_found') {
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
        if (!TMP.address) return;
        TMP.current_email_id = emailId;
        ajax('tmpmp_get_email', { email_id: emailId, address: TMP.address }, function (data) {
            $viewerSubj.text(data.subject || '(no subject)');
            $viewerMeta.html(
                `<strong>From:</strong> ${escHtml(data.sender_name || '')} &lt;${escHtml(data.sender)}&gt;<br>` +
                `<strong>Date:</strong> ${formatTime(data.received_at)}`
            );
            // HTML body — extract & move styles to <head>, then write to iframe
            if (data.body_html) {
                $bodyHtml.html('<iframe class="tmpmp-email-iframe" sandbox="allow-same-origin allow-popups"></iframe>');
                const iframe = $bodyHtml.find('iframe')[0];
                try {
                    const doc = iframe.contentDocument || iframe.contentWindow.document;
                    doc.open();
                    doc.write(prepareEmailHtml(data.body_html));
                    doc.close();
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
                } catch(e) {
                    $bodyHtml.html('<p style="color:var(--text3);font-size:13px;padding:16px;">Could not render HTML email.</p>');
                }
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
                var icon   = cat === 'premium' ? '⭐' : (cat === 'vip' ? '💎' : '');

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
        var icon = cat === 'premium' ? '⭐' : (cat === 'vip' ? '💎' : '');
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

}(jQuery));
