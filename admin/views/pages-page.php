<?php if ( ! defined('ABSPATH') ) exit; ?>
<style>
/* ── Pages admin panel ───────────────────────────────────────── */
.tmpmp-pages-header {
    display: flex;
    align-items: center;
    gap: 16px;
}
.tmpmp-pages-header p {
    margin: 0;
    color: #475569;
    font-size: 13.5px;
    line-height: 1.6;
    flex: 1 1 0%;
    min-width: 0;
}
.tmpmp-pages-header-right {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-shrink: 0;
}

/* ── Recreate button ─────────────────────────────────────────── */
#tmpmp-recreate-pages-btn {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    background: linear-gradient(135deg, #7c3aed 0%, #6366f1 100%);
    color: #fff;
    border: none;
    border-radius: 8px;
    padding: 9px 20px;
    font-size: 13.5px;
    font-weight: 600;
    font-family: inherit;
    cursor: pointer;
    transition: opacity .18s, transform .14s, box-shadow .18s;
    box-shadow: 0 2px 10px rgba(99,102,241,.35);
    white-space: nowrap;
    text-decoration: none;
    line-height: 1;
}
#tmpmp-recreate-pages-btn:hover:not(:disabled) {
    opacity: .92;
    transform: translateY(-1px);
    box-shadow: 0 4px 16px rgba(99,102,241,.45);
}
#tmpmp-recreate-pages-btn:active:not(:disabled) {
    transform: translateY(0);
    box-shadow: 0 2px 8px rgba(99,102,241,.3);
}
#tmpmp-recreate-pages-btn:disabled {
    opacity: .65;
    cursor: not-allowed;
}
#tmpmp-recreate-pages-btn .tmpmp-btn-icon {
    flex-shrink: 0;
    font-size: 16px;
    font-weight: 400;
    line-height: 1;
}
#tmpmp-recreate-status {
    font-size: 13px;
    font-weight: 500;
}

/* ── Responsive table ────────────────────────────────────────── */
.tmpmp-pages-table-wrap { overflow-x: auto; }
.tmpmp-pages-table { border-collapse: collapse; width: 100%; }
.tmpmp-pages-table th {
    padding: 10px 14px;
    text-align: left;
    font-size: 11.5px;
    color: #64748b;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .5px;
    border-bottom: 1px solid #e2e8f0;
    background: #f8fafc;
    
}
.tmpmp-pages-table td {
    padding: 12px 14px;
    border-bottom: 1px solid #f1f5f9;
    vertical-align: middle;
}
.tmpmp-pages-table tr:last-child td { border-bottom: none; }
.tmpmp-pages-table tr:hover td { background: #fafbff; }

/* ── Status badge ────────────────────────────────────────────── */
.tmpmp-page-status {
    display: inline-block;
    border-radius: 20px;
    padding: 3px 12px;
    font-size: 12px;
    font-weight: 600;
}
.tmpmp-page-status.active  { background:#f0fdf4; color:#16a34a; }
.tmpmp-page-status.missing { background:#fef2f2; color:#dc2626; }

/* ── Responsive: stack on narrow screens ─────────────────────── */
@media (max-width: 782px) {
    .tmpmp-pages-header {
        flex-direction: column;
        align-items: stretch;
        gap: 12px;
    }
    .tmpmp-pages-header p { flex: none; }
    .tmpmp-pages-header-right { justify-content: flex-start; }
    #tmpmp-recreate-pages-btn { width: 100%; justify-content: center; }
    #tmpmp-recreate-status { display: block !important; margin-top: 8px; }

    /* Table: hide URL column, allow content wrapping */
    .tmpmp-pages-table-wrap { overflow-x: visible; }
    .tmpmp-pages-table th { white-space: normal; font-size: 11px; padding: 8px 10px; }
    .tmpmp-pages-table td { padding: 10px 10px; font-size: 13px; }
    .tmpmp-pages-table th:nth-child(4),
    .tmpmp-pages-table td:nth-child(4) { display: none; }
    .tmpmp-pages-table code { word-break: break-all; white-space: normal; }
}
</style>

<div class="wrap tmpmp-admin-wrap">
<h1 class="tmpmp-admin-title">
    <span class="dashicons dashicons-admin-page"></span>
    <?php esc_html_e('Plugin Pages', 'tempmail-pro'); ?>
</h1>

<div class="tmpmp-card" style="margin-bottom:20px;">
    <div class="tmpmp-pages-header">
        <p>
            <?php esc_html_e('These pages are automatically created when the plugin is activated. Each page contains its matching shortcode. If a page was accidentally deleted, click the button to restore it.', 'tempmail-pro'); ?>
        </p>
        <div class="tmpmp-pages-header-right">
            <button type="button" id="tmpmp-recreate-pages-btn">
                <span class="tmpmp-btn-icon">+</span>
                <?php esc_html_e('Recreate Missing Pages', 'tempmail-pro'); ?>
            </button>
            <span id="tmpmp-recreate-status" style="display:none;"></span>
        </div>
    </div>
</div>

<div class="tmpmp-card">
    <div class="tmpmp-pages-table-wrap">
    <table class="tmpmp-pages-table">
        <thead>
            <tr>
                <th><?php esc_html_e('Page', 'tempmail-pro'); ?></th>
                <th><?php esc_html_e('Shortcode', 'tempmail-pro'); ?></th>
                <th><?php esc_html_e('Status', 'tempmail-pro'); ?></th>
                <th><?php esc_html_e('URL', 'tempmail-pro'); ?></th>
            </tr>
        </thead>
        <tbody id="tmpmp-pages-tbody">
        <?php foreach ( $pages as $key => $page ) :
            $exists = ! empty( $page['id'] ) && ! empty( $page['url'] );
        ?>
        <tr data-key="<?php echo esc_attr( $key ); ?>">
            <td style="font-weight:600;color:#1e293b;">
                <?php echo esc_html( $page['title'] ); ?>
                <?php if ( $exists ) : ?>
                    &nbsp;<a href="<?php echo esc_url( get_edit_post_link( $page['id'] ) ); ?>" target="_blank"
                       style="font-weight:400;font-size:12px;color:#6366f1;"><?php esc_html_e( 'Edit', 'tempmail-pro' ); ?></a>
                <?php endif; ?>
            </td>
            <td>
                <code style="background:#f1f5f9;border-radius:5px;padding:3px 9px;font-size:13px;color:#4338ca;">
                    <?php echo esc_html( $page['shortcode'] ); ?>
                </code>
            </td>
            <td>
                <span class="tmpmp-page-status <?php echo $exists ? 'active' : 'missing'; ?>">
                    <?php echo $exists ? esc_html__( 'Active', 'tempmail-pro' ) : esc_html__( 'Missing', 'tempmail-pro' ); ?>
                </span>
            </td>
            <td>
                <?php if ( $exists ) : ?>
                    <a href="<?php echo esc_url( $page['url'] ); ?>" target="_blank"
                       style="color:#6366f1;font-size:13px;word-break:break-all;">
                        <?php echo esc_html( $page['url'] ); ?>
                        <svg style="vertical-align:middle;margin-left:3px;" width="11" height="11" viewBox="0 0 24 24"
                             fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M18 13v6a2 2 0 01-2 2H5a2 2 0 01-2-2V8a2 2 0 012-2h6"/>
                            <polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/>
                        </svg>
                    </a>
                <?php else : ?>
                    <span style="color:#94a3b8;font-size:13px;"><?php esc_html_e( 'Not created yet', 'tempmail-pro' ); ?></span>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</div>

<div class="tmpmp-card" style="margin-top:20px;background:#fffbeb;border:1px solid #fde68a;">
    <p style="margin:0;font-size:13px;color:#92400e;line-height:1.6;">
        <strong>&#9888; <?php esc_html_e( 'Note:', 'tempmail-pro' ); ?></strong>
        <?php esc_html_e( '"Recreate Missing Pages" only creates pages that are missing or trashed — it will never overwrite existing live pages. To fully reset a page, delete it from WordPress Pages first, then click Recreate.', 'tempmail-pro' ); ?>
    </p>
</div>
</div>

<script>
jQuery(function($){
    var $btn    = $('#tmpmp-recreate-pages-btn');
    var $status = $('#tmpmp-recreate-status');
    var origHtml = $btn.html();

    $btn.on('click', function(){
        $btn.prop('disabled', true).html(
            '<span class="tmpmp-btn-icon" style="animation:tmpmp-spin .7s linear infinite;display:inline-block;">&#8635;</span> <?php echo esc_js( __('Recreating…','tempmail-pro') ); ?>'
        );
        $status.hide();

        $.post(TempMailAdmin.ajax_url, {
            action : 'tmpmp_recreate_pages',
            nonce  : TempMailAdmin.nonce
        })
        .done(function(res){
            $btn.prop('disabled', false).html(origHtml);
            if ( res.success ) {
                $status.text('<?php echo esc_js( __('Done! Pages updated.','tempmail-pro') ); ?>').css('color','#16a34a').show();
                setTimeout(function(){ location.reload(); }, 1200);
            } else {
                var msg = (res.data && res.data.message) ? res.data.message : '<?php echo esc_js( __('Something went wrong.','tempmail-pro') ); ?>';
                $status.text(msg).css('color','#dc2626').show();
            }
        })
        .fail(function(){
            $btn.prop('disabled', false).html(origHtml);
            $status.text('<?php echo esc_js( __('Request failed.','tempmail-pro') ); ?>').css('color','#dc2626').show();
        });
    });
});
</script>
<style>
@keyframes tmpmp-spin { to { transform: rotate(360deg); } }
</style>
