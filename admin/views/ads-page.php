<?php if ( ! defined('ABSPATH') ) exit; ?>
<div class="wrap tmpmp-admin-wrap">
<h1 class="tmpmp-admin-title"><span class="dashicons dashicons-megaphone"></span> <?php esc_html_e('Ad Placement Manager','tempmail-pro'); ?> <span class="tmpmp-version-pill">v<?php echo esc_html(TMPMP_VERSION); ?></span></h1>

<style>
.tmpmp-page-card{background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:20px 24px;margin-bottom:20px;}
.tmpmp-page-section-title{font-size:11px;font-weight:800;letter-spacing:.8px;text-transform:uppercase;color:#6366f1;margin:0 0 16px;}
.tmpmp-page-field{display:grid;grid-template-columns:180px 1fr;gap:12px 20px;align-items:start;padding:14px 0;border-bottom:1px solid #f1f5f9;}
.tmpmp-page-field:last-child{border-bottom:none;}
.tmpmp-page-label{font-size:13px;font-weight:600;color:#334155;padding-top:9px;}
.tmpmp-page-input{width:100%;padding:8px 12px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:13px;font-family:inherit;outline:none;transition:border-color .15s;box-sizing:border-box;}
.tmpmp-page-input:focus{border-color:#6366f1;box-shadow:0 0 0 3px rgba(99,102,241,.1);}
.tmpmp-page-select{padding:8px 12px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:13px;font-family:inherit;outline:none;cursor:pointer;background:#fff;min-width:180px;}
.tmpmp-page-select:focus{border-color:#6366f1;}
.tmpmp-page-hint{font-size:12px;color:#94a3b8;margin-top:6px;}
.tmpmp-styled-table{width:100%;border-collapse:collapse;font-size:13px;}
.tmpmp-styled-table th{background:#f8fafc;color:#475569;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;padding:10px 14px;text-align:left;border-bottom:2px solid #e2e8f0;}
.tmpmp-styled-table td{padding:12px 14px;border-bottom:1px solid #f1f5f9;color:#334155;vertical-align:middle;}
.tmpmp-styled-table tr:last-child td{border-bottom:none;}
.tmpmp-styled-table tr:hover td{background:#fafbff;}
.tmpmp-icon-btn{display:inline-flex;align-items:center;gap:5px;padding:5px 10px;border-radius:6px;font-size:12px;font-weight:600;cursor:pointer;border:1.5px solid #e2e8f0;background:#fff;transition:all .15s;}
.tmpmp-icon-btn:hover{border-color:#6366f1;color:#6366f1;}
.tmpmp-icon-btn--danger:hover{border-color:#ef4444;color:#ef4444;}
.tmpmp-save-btn{display:inline-flex;align-items:center;gap:8px;padding:9px 20px;background:linear-gradient(135deg,#6366f1,#8b5cf6);color:#fff;border:none;border-radius:8px;font-size:13px;font-weight:700;cursor:pointer;font-family:inherit;transition:all .15s;}
.tmpmp-save-btn:hover{opacity:.9;transform:translateY(-1px);box-shadow:0 4px 12px rgba(99,102,241,.3);}
.tmpmp-reset-btn{display:inline-flex;align-items:center;gap:7px;padding:9px 16px;background:#f8fafc;color:#475569;border:1.5px solid #e2e8f0;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;font-family:inherit;transition:all .15s;margin-left:8px;}
.tmpmp-reset-btn:hover{border-color:#94a3b8;}
.tmpmp-empty-row td{text-align:center;padding:32px!important;color:#94a3b8;}
@media(max-width:600px){.tmpmp-page-field{grid-template-columns:1fr;gap:6px;}.tmpmp-page-label{padding-top:0;}.tmpmp-page-card{padding:16px 14px;}}
</style>

<!-- ① Add / Edit Ad Form -->
<div class="tmpmp-page-card">
    <p class="tmpmp-page-section-title">✏️ <?php esc_html_e('Add / Edit Ad','tempmail-pro'); ?></p>
    <form id="tmpmp-ad-form">
    <input type="hidden" name="id" id="tmpmp-ad-id" value="0">

    <div class="tmpmp-page-field">
        <label class="tmpmp-page-label" for="ad-name"><?php esc_html_e('Ad Name','tempmail-pro'); ?></label>
        <div>
            <input type="text" id="ad-name" name="name" class="tmpmp-page-input"
                placeholder="<?php esc_attr_e('e.g. Top Banner AdSense','tempmail-pro'); ?>" required>
        </div>
    </div>

    <div class="tmpmp-page-field">
        <label class="tmpmp-page-label" for="ad-placement"><?php esc_html_e('Placement','tempmail-pro'); ?></label>
        <div>
            <select id="ad-placement" name="placement" class="tmpmp-page-select">
                <option value="top_banner">📢 <?php esc_html_e('Top Banner','tempmail-pro'); ?></option>
                <option value="bottom_banner">📢 <?php esc_html_e('Bottom Banner','tempmail-pro'); ?></option>
                <option value="inbox_sidebar">📌 <?php esc_html_e('Inbox Sidebar','tempmail-pro'); ?></option>
                <option value="between_emails">📧 <?php esc_html_e('Between Emails','tempmail-pro'); ?></option>
            </select>
        </div>
    </div>

    <div class="tmpmp-page-field">
        <label class="tmpmp-page-label" for="ad-type"><?php esc_html_e('Type','tempmail-pro'); ?></label>
        <div>
            <select id="ad-type" name="type" class="tmpmp-page-select">
                <option value="banner">Banner</option>
                <option value="native">Native</option>
                <option value="adsense">AdSense</option>
            </select>
        </div>
    </div>

    <div class="tmpmp-page-field">
        <label class="tmpmp-page-label" for="ad-code"><?php esc_html_e('Ad Code / HTML','tempmail-pro'); ?></label>
        <div>
            <textarea id="ad-code" name="code" rows="6" class="tmpmp-page-input"
                style="height:auto;resize:vertical;font-family:monospace;font-size:12px;"
                placeholder="<script async src='...'></script>&#10;<ins class='adsbygoogle'...></ins>"></textarea>
            <p class="tmpmp-page-hint"><?php esc_html_e('Paste full AdSense code or custom HTML. Ads are never shown to premium users.','tempmail-pro'); ?></p>
        </div>
    </div>

    <div class="tmpmp-page-field">
        <label class="tmpmp-page-label"><?php esc_html_e('Status','tempmail-pro'); ?></label>
        <div style="padding-top:6px;">
            <label class="tmpmp-toggle-label">
                <input type="checkbox" name="is_active" value="1" checked>
                <span class="tmpmp-toggle-slider"></span>
            </label>
            <span style="margin-left:10px;font-size:13px;color:#475569;vertical-align:middle;"><?php esc_html_e('Active','tempmail-pro'); ?></span>
        </div>
    </div>

    <div style="padding-top:16px;display:flex;gap:8px;flex-wrap:wrap;">
        <button type="button" class="tmpmp-save-btn" id="tmpmp-save-ad-btn">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21H5a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v11a2 2 0 01-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
            <?php esc_html_e('Save Ad','tempmail-pro'); ?>
        </button>
        <button type="button" class="tmpmp-reset-btn" onclick="document.getElementById('tmpmp-ad-form').reset();document.getElementById('tmpmp-ad-id').value='0';">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 102.13-9.36L1 10"/></svg>
            <?php esc_html_e('Reset','tempmail-pro'); ?>
        </button>
    </div>
    </form>
</div>

<!-- ② Ads Table -->
<div class="tmpmp-page-card">
    <p class="tmpmp-page-section-title">📊 <?php esc_html_e('Ad Placements','tempmail-pro'); ?></p>
    <div style="overflow-x:auto;">
    <table class="tmpmp-styled-table">
    <thead><tr>
        <th><?php esc_html_e('Name','tempmail-pro'); ?></th>
        <th><?php esc_html_e('Placement','tempmail-pro'); ?></th>
        <th><?php esc_html_e('Type','tempmail-pro'); ?></th>
        <th><?php esc_html_e('Impressions','tempmail-pro'); ?></th>
        <th><?php esc_html_e('Clicks','tempmail-pro'); ?></th>
        <th><?php esc_html_e('CTR','tempmail-pro'); ?></th>
        <th><?php esc_html_e('Status','tempmail-pro'); ?></th>
        <th><?php esc_html_e('Actions','tempmail-pro'); ?></th>
    </tr></thead>
    <tbody>
    <?php if(empty($ads)): ?>
    <tr class="tmpmp-empty-row"><td colspan="8"><?php esc_html_e('No ads yet. Create your first ad placement above.','tempmail-pro'); ?></td></tr>
    <?php else: foreach($ads as $ad):
        $ctr = $ad->impressions > 0 ? round(($ad->clicks / $ad->impressions) * 100, 2) : 0;
    ?>
    <tr id="ad-row-<?php echo intval($ad->id); ?>">
        <td><strong><?php echo esc_html($ad->name); ?></strong></td>
        <td><code style="background:#f1f5f9;padding:2px 6px;border-radius:4px;font-size:11px;"><?php echo esc_html($ad->placement); ?></code></td>
        <td><?php echo esc_html(ucfirst($ad->type)); ?></td>
        <td><?php echo number_format($ad->impressions); ?></td>
        <td><?php echo number_format($ad->clicks); ?></td>
        <td><strong style="color:<?php echo $ctr > 2 ? '#10b981' : '#64748b'; ?>"><?php echo $ctr; ?>%</strong></td>
        <td><span class="tmpmp-badge <?php echo $ad->is_active ? 'tmpmp-badge--green' : 'tmpmp-badge--red'; ?>">
            <?php echo $ad->is_active ? esc_html__('Active','tempmail-pro') : esc_html__('Paused','tempmail-pro'); ?>
        </span></td>
        <td style="white-space:nowrap;">
            <button class="tmpmp-icon-btn tmpmp-edit-ad-btn" data-id="<?php echo intval($ad->id); ?>"
                data-ad='<?php echo esc_attr(json_encode(['id'=>$ad->id,'name'=>$ad->name,'placement'=>$ad->placement,'type'=>$ad->type,'code'=>$ad->code,'is_active'=>$ad->is_active])); ?>'>
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                Edit
            </button>
            <button class="tmpmp-icon-btn tmpmp-icon-btn--danger tmpmp-delete-ad" data-id="<?php echo intval($ad->id); ?>" style="margin-left:4px;">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/></svg>
                Delete
            </button>
        </td>
    </tr>
    <?php endforeach; endif; ?>
    </tbody>
    </table>
    </div>
</div>

</div><!-- /.wrap -->

<script>
jQuery(function($){
    $(document).on('click', '.tmpmp-edit-ad-btn', function(){
        const a = $(this).data('ad');
        $('#tmpmp-ad-id').val(a.id);
        $('#tmpmp-ad-form [name="name"]').val(a.name);
        $('#tmpmp-ad-form [name="placement"]').val(a.placement);
        $('#tmpmp-ad-form [name="type"]').val(a.type);
        $('#tmpmp-ad-form [name="code"]').val(a.code);
        $('#tmpmp-ad-form [name="is_active"]').prop('checked', !!+a.is_active);
        $('html,body').animate({scrollTop: 0}, 300);
    });
});
</script>
