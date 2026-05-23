<?php if ( ! defined('ABSPATH') ) exit; ?>
<div class="tmpmp-page-section tmpmp-auth-wrap">

    <!-- Hero -->
    <div class="tmpmp-auth-hero">
        <span class="tmpmp-auth-icon">&#9993;</span>
        <h1>TempMail Pro</h1>
        <p><?php esc_html_e('Sign in or create your account to get started.','tempmail-pro'); ?></p>
    </div>

    <div class="tmpmp-auth-card">

        <!-- Magic Link -->
        <div id="tmpmp-magic-form">
            <p class="tmpmp-auth-section-title">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M10 13a5 5 0 007.54.54l3-3a5 5 0 00-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 00-7.54-.54l-3 3a5 5 0 007.07 7.07l1.71-1.71"/></svg>
                <?php esc_html_e('Magic Link (passwordless)','tempmail-pro'); ?>
            </p>
            <input type="email" id="tmpmp-magic-email" class="tmpmp-auth-input"
                placeholder="you@example.com" autocomplete="email">
            <button id="tmpmp-magic-btn" class="tmpmp-pub-btn tmpmp-pub-btn--primary" style="width:100%;justify-content:center;">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                <?php esc_html_e('Send Magic Link','tempmail-pro'); ?>
            </button>
            <div id="tmpmp-magic-msg" class="tmpmp-auth-msg"></div>
        </div>

        <!-- Divider -->
        <div class="tmpmp-auth-divider">
            <hr><span><?php esc_html_e('OR','tempmail-pro'); ?></span><hr>
        </div>

        <!-- WP Login Form -->
        <?php
        wp_login_form([
            'echo'           => true,
            'remember'       => true,
            'redirect'       => home_url('/tempmail-dashboard/'),
            'form_id'        => 'loginform',
            'label_username' => __('Email or Username','tempmail-pro'),
            'label_password' => __('Password','tempmail-pro'),
            'label_remember' => __('Remember Me','tempmail-pro'),
            'label_log_in'   => __('Sign In','tempmail-pro'),
        ]);
        ?>

        <!-- Google OAuth -->
        <?php
        $settings   = get_option('tmpmp_settings',[]);
        $google_url = TempMail_Auth::google_auth_url();
        if ( !empty($settings['google_login']) && $google_url ) :
        ?>
        <a href="<?php echo esc_url($google_url); ?>" class="tmpmp-auth-google">
            <img src="https://www.gstatic.com/firebasejs/ui/2.0.0/images/auth/google.svg" width="18" alt="Google">
            <?php esc_html_e('Continue with Google','tempmail-pro'); ?>
        </a>
        <?php endif; ?>

        <p class="tmpmp-auth-note">
            <?php esc_html_e("Don't have an account? Use the magic link above — it creates one automatically.", 'tempmail-pro'); ?>
        </p>
    </div>
</div>

<script>
(function(){
    /* Self-contained — no jQuery, no TempMailPro dependency */
    var AJAX_URL = '<?php echo esc_js( admin_url('admin-ajax.php') ); ?>';
    var NONCE    = '<?php echo esc_js( wp_create_nonce('tempmail_pro_nonce') ); ?>';

    var btn   = document.getElementById('tmpmp-magic-btn');
    var input = document.getElementById('tmpmp-magic-email');
    var msg   = document.getElementById('tmpmp-magic-msg');
    if (!btn || !input || !msg) return;

    var originalHTML = btn.innerHTML;

    function showMsg(text, type) {
        msg.className = 'tmpmp-auth-msg ' + type;
        msg.textContent = text;
        msg.style.display = 'block';
    }

    function resetBtn() {
        btn.disabled = false;
        btn.innerHTML = originalHTML;
    }

    function sendMagicLink() {
        var email = input.value.trim();
        if (!email) {
            showMsg('<?php echo esc_js( __('Please enter a valid email address.','tempmail-pro') ); ?>', 'error');
            return;
        }

        btn.disabled = true;
        btn.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="animation:spin .7s linear infinite;vertical-align:middle;margin-right:6px"><path d="M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0"/></svg><?php echo esc_js( __('Sending…','tempmail-pro') ); ?>';
        msg.style.display = 'none';

        var body = new URLSearchParams();
        body.append('action', 'tmpmp_magic_link_request');
        body.append('nonce',  NONCE);
        body.append('email',  email);

        fetch(AJAX_URL, {
            method:  'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body:    body.toString(),
        })
        .then(function(resp) {
            if (!resp.ok) throw new Error('HTTP ' + resp.status);
            return resp.json();
        })
        .then(function(r) {
            if (r.success) {
                showMsg(
                    (r.data && r.data.message) || '<?php echo esc_js( __('Magic link sent! Check your inbox.','tempmail-pro') ); ?>',
                    'success'
                );
                /* Keep button disabled for 5s then restore so user can resend */
                setTimeout(function(){ resetBtn(); }, 5000);
            } else {
                showMsg(
                    (r.data && r.data.message) || '<?php echo esc_js( __('Something went wrong. Please try again.','tempmail-pro') ); ?>',
                    'error'
                );
                resetBtn();
            }
        })
        .catch(function() {
            showMsg('<?php echo esc_js( __('Connection error. Please check your internet and try again.','tempmail-pro') ); ?>', 'error');
            resetBtn();
        });
    }

    btn.addEventListener('click', sendMagicLink);
    input.addEventListener('keypress', function(e){ if (e.key === 'Enter') sendMagicLink(); });
})();
</script>
<style>@keyframes spin{to{transform:rotate(360deg);}}</style>
