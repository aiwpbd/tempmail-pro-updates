/**
 * TempMail Pro -- Admin JavaScript
 * Handles: Settings save, Domain/Plan CRUD, test email, purge, IMAP poll, token regen
 */
/* global TempMailAdmin, jQuery */
(function ($) {
    'use strict';

    const url   = TempMailAdmin.ajax_url;
    const nonce = TempMailAdmin.nonce;

    // ---------------------------------------------------------------------------------------------------------
    function toast(msg, type = 'success') {
        const div = $('<div>')
            .addClass('notice notice-' + (type === 'error' ? 'error' : 'success') + ' is-dismissible tmpmp-admin-toast')
            .html('<p>' + msg + '</p>')
            .css({ position: 'fixed', top: '40px', right: '24px', zIndex: 9999, minWidth: '300px', boxShadow: '0 4px 20px rgba(0,0,0,.15)' });
        $('body').append(div);
        setTimeout(() => div.fadeOut(400, () => div.remove()), 4000);
    }

    function btn(el, loading) {
        if (loading) {
            $(el).data('orig', $(el).text()).text('Saving...').prop('disabled', true);
        } else {
            $(el).text($(el).data('orig') || 'Save').prop('disabled', false);
        }
    }

    // ---------------------------------------------------------------------------------------------------------
    $(document).on('click', '.tmpmp-regen-token', function () {
        const field = $(this).data('field');
        const input = $('#' + field);
        if (!confirm('Regenerate this token? Any existing webhooks/cron using the old token will break.')) return;
        $.post(url, { action: 'tmpmp_regen_token', nonce, field }, function (r) {
            if (r.success) {
                input.val(r.data.token);
                toast('Token regenerated!');
            }
        });
    });

    // ---------------------------------------------------------------------------------------------------------
    $(document).on('click', '#tmpmp-inject-test', function () {
        const address = $('#tmpmp-test-address').val().trim();
        if (!address) return alert('Enter a temp email address first.');
        $(this).text('Sending...').prop('disabled', true);
        const self = this;
        $.post(url, { action: 'tmpmp_inject_test_email', nonce, address }, function (r) {
            $(self).text('Send Test Email').prop('disabled', false);
            r.success ? toast(r.data.message) : toast(r.data?.message || 'Failed', 'error');
        });
    });

    // ---------------------------------------------------------------------------------------------------------
    $(document).on('click', '#tmpmp-purge-now', function () {
        const self = this;
        $(self).text('Purging...').prop('disabled', true);
        $.post(url, { action: 'tmpmp_purge_now', nonce }, function (r) {
            $(self).text('Purge Now').prop('disabled', false);
            r.success ? toast(r.data.message) : toast('Failed', 'error');
        });
    });

    // ---------------------------------------------------------------------------------------------------------
    $(document).on('click', '#tmpmp-poll-imap', function () {
        const self = this;
        $(self).text('Polling...').prop('disabled', true);
        $.post(url, { action: 'tmpmp_poll_imap', nonce }, function (r) {
            $(self).text('Poll Now').prop('disabled', false);
            if (r.success) {
                toast('Polled. Stored: ' + (r.data.stored ?? 0) + ' emails.');
            } else {
                toast('Poll failed', 'error');
            }
        });
    });

    // ---------------------------------------------------------------------------------------------------------
    $(document).on('click', '#tmpmp-add-domain-btn', function () {
        const domain   = $('#tmpmp-new-domain').val().trim();
        const category = $('#tmpmp-new-category').val();
        if (!domain) return toast('Enter a domain name', 'error');
        $.post(url, { action: 'tmpmp_add_domain', nonce, domain, category }, function (r) {
            if (r.success) { toast('Domain added!'); setTimeout(() => location.reload(), 800); }
            else toast(r.data?.message || 'Failed', 'error');
        });
    });

    $(document).on('change', '.tmpmp-domain-status', function () {
        const id = $(this).data('id');
        const is_active = $(this).is(':checked') ? 1 : 0;
        $.post(url, { action: 'tmpmp_update_domain', nonce, id, is_active });
    });

    $(document).on('change', '.tmpmp-domain-category', function () {
        const id       = $(this).data('id');
        const category = $(this).val();
        $.post(url, { action: 'tmpmp_update_domain', nonce, id, category }, function (r) {
            r.success ? toast('Category updated') : toast('Failed', 'error');
        });
    });

    $(document).on('click', '.tmpmp-delete-domain', function () {
        if (!confirm('Delete this domain? This cannot be undone.')) return;
        const id  = $(this).data('id');
        const row = $(this).closest('tr');
        $.post(url, { action: 'tmpmp_delete_domain', nonce, id }, function (r) {
            if (r.success) row.fadeOut(300, () => row.remove());
            else toast('Failed to delete', 'error');
        });
    });

    // ---------------------------------------------------------------------------------------------------------
    $(document).on('click', '#tmpmp-add-plan-btn', function () {
        $('#tmpmp-plan-modal').show();
        $('#tmpmp-plan-form')[0].reset();
        $('#tmpmp-plan-id').val('0');
        $('#tmpmp-plan-modal-title').text('Add New Plan');
    });

    $(document).on('click', '.tmpmp-edit-plan', function () {
        const row   = $(this).closest('tr');
        const id    = $(this).data('id');
        // Populate from data attributes
        $('#tmpmp-plan-id').val(id);
        $('#pf-slug').val(row.data('slug'));
        $('#pf-name').val(row.find('.plan-name').text().trim());
        $('#pf-price-monthly').val(row.data('monthly'));
        $('#pf-price-yearly').val(row.data('yearly'));
        $('#pf-max-inboxes').val(row.data('max-inboxes'));
        $('#pf-lifetime').val(row.data('lifetime'));
        $('#tmpmp-plan-modal-title').text('Edit Plan');
        $('#tmpmp-plan-modal').show();
    });

    $(document).on('click', '#tmpmp-plan-modal-close, #tmpmp-plan-cancel', function () {
        $('#tmpmp-plan-modal').hide();
    });

    $(document).on('click', '#tmpmp-save-plan-btn', function () {
        const self = this;
        btn(self, true);
        const data = $('#tmpmp-plan-form').serializeArray().reduce((o, f) => { o[f.name] = f.value; return o; }, {});
        data.action = 'tmpmp_save_plan';
        data.nonce  = nonce;
        data.id     = $('#tmpmp-plan-id').val();
        $.post(url, data, function (r) {
            btn(self, false);
            if (r.success) { toast('Plan saved!'); setTimeout(() => location.reload(), 800); }
            else toast(r.data?.message || 'Save failed', 'error');
        });
    });

    $(document).on('click', '.tmpmp-delete-plan', function () {
        if (!confirm('Delete this plan? Users on this plan will be moved to Free.')) return;
        const id  = $(this).data('id');
        const row = $(this).closest('tr');
        $.post(url, { action: 'tmpmp_delete_plan', nonce, id }, function (r) {
            if (r.success) row.fadeOut(300, () => row.remove());
            else toast(r.data?.message || 'Failed', 'error');
        });
    });

    // ---------------------------------------------------------------------------------------------------------
    $(document).on('click', '#tmpmp-save-ad-btn', function () {
        const self = this;
        btn(self, true);
        const data = $('#tmpmp-ad-form').serializeArray().reduce((o, f) => { o[f.name] = f.value; return o; }, {});
        data.action = 'tmpmp_save_ad';
        data.nonce  = nonce;
        $.post(url, data, function (r) {
            btn(self, false);
            if (r.success) { toast('Ad saved!'); setTimeout(() => location.reload(), 800); }
            else toast('Save failed', 'error');
        });
    });

    $(document).on('click', '.tmpmp-delete-ad', function () {
        if (!confirm('Delete this ad?')) return;
        const id  = $(this).data('id');
        const row = $(this).closest('tr');
        $.post(url, { action: 'tmpmp_delete_ad', nonce, id }, function (r) {
            if (r.success) row.fadeOut(300, () => row.remove());
        });
    });

    // ---------------------------------------------------------------------------------------------------------
    $(document).on('click', '.tmpmp-cancel-user-sub', function () {
        if (!confirm('Cancel this user\'s subscription?')) return;
        const user_id = $(this).data('uid');
        $.post(url, { action: 'tmpmp_cancel_user_sub', nonce, user_id }, function (r) {
            r.success ? toast('Subscription cancelled') : toast('Failed', 'error');
        });
    });

    $(document).on('click', '.tmpmp-ban-ip', function () {
        const ip = prompt('IP address to ban:');
        if (!ip) return;
        const reason = prompt('Reason (optional):') || '';
        $.post(url, { action: 'tmpmp_ban_ip', nonce, ip, reason }, function (r) {
            r.success ? toast('IP banned') : toast(r.data?.message || 'Failed', 'error');
        });
    });

    $(document).on('click', '.tmpmp-unban-ip', function () {
        if (!confirm('Unban this IP?')) return;
        const ip  = $(this).data('ip');
        const row = $(this).closest('tr');
        $.post(url, { action: 'tmpmp_unban_ip', nonce, ip }, function (r) {
            if (r.success) row.fadeOut(300, () => row.remove());
        });
    });

    // ---------------------------------------------------------------------------------------------------------
    $(document).on('click', '.tmpmp-copy-field', function () {
        const target = $($(this).data('target'));
        navigator.clipboard.writeText(target.val() || target.text())
            .then(() => toast('Copied to clipboard'))
            .catch(() => {
                target.select();
                document.execCommand('copy');
                toast('Copied');
            });
    });


    // ── Inbox Branding — WP Media Library pickers ──────────────────────────────
    if (typeof wp !== 'undefined' && wp.media) {
        function makePicker(o) {
            var frame;
            $(document).on('click', '#' + o.btnId, function(e) {
                e.preventDefault();
                if (frame) { frame.open(); return; }
                frame = wp.media({ title: o.title, button: { text: 'Use this image' }, multiple: false, library: { type: 'image' } });
                frame.on('select', function() {
                    var att = frame.state().get('selection').first().toJSON();
                    var url = (att.sizes && att.sizes.thumbnail) ? att.sizes.thumbnail.url : att.url;
                    $('#' + o.idField).val(att.id);
                    $('#' + o.urlField).val(att.url);
                    $('#' + o.previewId).css('background-image', 'url(' + url + ')').find('.tmpmp-branding-ph').hide();
                    $('#' + o.removeId).show();
                    toast('Image selected');
                });
                frame.open();
            });
            $(document).on('click', '#' + o.removeId, function(e) {
                e.preventDefault();
                $('#' + o.idField).val(''); $('#' + o.urlField).val('');
                $('#' + o.previewId).css('background-image', '').find('.tmpmp-branding-ph').show();
                $(this).hide(); toast('Image removed');
            });
        }
        makePicker({ btnId:'tmpmp-logo-pick', idField:'tmpmp-logo-id', urlField:'tmpmp-logo-url', previewId:'tmpmp-logo-preview', removeId:'tmpmp-logo-remove', title:'Choose Site Logo' });
        makePicker({ btnId:'tmpmp-empty-pick', idField:'tmpmp-empty-id', urlField:'tmpmp-empty-url', previewId:'tmpmp-empty-preview', removeId:'tmpmp-empty-remove', title:'Choose Empty Inbox Illustration' });
    }

    // ── Avatar radio ─────────────────────────────────────────────────────────
    $(document).on('change', '.tmpmp-branding-radio input[type="radio"]', function() {
        $('.tmpmp-branding-radio').removeClass('active');
        $(this).closest('.tmpmp-branding-radio').addClass('active');
    });

    // ── Address Prefix live preview ───────────────────────────────────────────
    $(document).on('input', '.tmpmp-prefix-input', function() {
        var safe = $(this).val().replace(/[^a-zA-Z0-9_\-]/g,'').substring(0,20);
        $(this).val(safe);
        var tier = $(this).data('tier'), type = $(this).data('type');
        var $pv = $('#tmpmp-' + tier + '-preview');
        if (type === 'prefix') $pv.find('.tmpmp-pv-prefix').text(safe);
        else $pv.find('.tmpmp-pv-suffix').text(safe);
    });

    // ── Emoji picker ─────────────────────────────────────────────────────────
    $(document).on('click', '.tmpmp-emoji-btn', function() {
        var emoji = $(this).data('emoji');
        $('.tmpmp-emoji-btn').removeClass('active');
        $(this).addClass('active');
        var $d = $('#tmpmp-emoji-display'), $i = $('#tmpmp-emoji-input');
        if (!emoji) { $d.text('—').css('opacity','.35'); $i.val(''); }
        else { $d.text(emoji).css('opacity','1'); $i.val(emoji); }
    });
    $(document).on('input', '#tmpmp-emoji-input', function() {
        var val = $(this).val().trim();
        $('#tmpmp-emoji-display').text(val||'—').css('opacity', val?'1':'.35');
        $('.tmpmp-emoji-btn').removeClass('active');
        if (val) $('.tmpmp-emoji-btn[data-emoji="'+val+'"]').addClass('active');
        else $('#tmpmp-emoji-none').addClass('active');
    });

    // ── Email Generation tab ──────────────────────────────────────────────────
    function tmpmpEgSync() {
        var fmt = $('#eg_format').val() || '';
        var isWord = ['adj_noun_num','adj_noun','noun_num'].indexOf(fmt) !== -1;
        var isRand = ['random_chars','short_uuid'].indexOf(fmt) !== -1;
        $('.tmpmp-eg-adj-noun-only').toggle(isWord);
        $('#tmpmp-eg-random-card').toggle(isRand);
    }
    function tmpmpEgNumRange() {
        $('#tmpmp-eg-num-range-row').toggle($('#eg_num_suffix').val() !== 'never');
    }
    $('#eg_format').on('change', tmpmpEgSync);
    $('#eg_num_suffix').on('change', tmpmpEgNumRange);
    tmpmpEgSync();
    tmpmpEgNumRange();

    $('#tmpmp-eg-generate-preview').on('click', function() {
        var $btn = $(this), $res = $('#tmpmp-eg-preview-result');
        $btn.prop('disabled', true).text('Generating...');
        $.post(TempMailAdmin.ajax_url, { action: 'tmpmp_eg_preview', nonce: TempMailAdmin.nonce }, function(r) {
            $btn.prop('disabled', false).text('Generate Preview');
            if (r.success) $res.html('<span style="color:#a5b4fc;">' + r.data.address + '</span>');
            else $res.html('<span style="color:#f87171;">Error — save settings first.</span>');
        }).fail(function() {
            $btn.prop('disabled', false).text('Generate Preview');
            $res.html('<span style="color:#f87171;">Network error.</span>');
        });
    });

})(jQuery);

