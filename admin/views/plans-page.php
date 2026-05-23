<?php if ( ! defined('ABSPATH') ) exit; ?>
<div class="wrap tmpmp-admin-wrap">
<h1 class="tmpmp-admin-title"><span class="dashicons dashicons-awards"></span> <?php esc_html_e('Subscription Plans','tempmail-pro'); ?> <span class="tmpmp-version-pill">v<?php echo esc_html(TMPMP_VERSION); ?></span></h1>

<style>
.tmpmp-page-card{background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:20px 24px;margin-bottom:20px;}
.tmpmp-page-section-title{font-size:11px;font-weight:800;letter-spacing:.8px;text-transform:uppercase;color:#6366f1;margin:0 0 16px;}
.tmpmp-page-field{display:grid;grid-template-columns:180px 1fr;gap:12px 20px;align-items:start;padding:14px 0;border-bottom:1px solid #f1f5f9;}
.tmpmp-page-field:last-child{border-bottom:none;}
.tmpmp-page-label{font-size:13px;font-weight:600;color:#334155;padding-top:9px;}
.tmpmp-page-input{width:100%;padding:8px 12px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:13px;font-family:inherit;outline:none;transition:border-color .15s;box-sizing:border-box;}
.tmpmp-page-input:focus{border-color:#6366f1;box-shadow:0 0 0 3px rgba(99,102,241,.1);}
.tmpmp-page-select{padding:8px 12px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:13px;font-family:inherit;outline:none;cursor:pointer;background:#fff;}
.tmpmp-page-select:focus{border-color:#6366f1;}
.tmpmp-page-hint{font-size:12px;color:#94a3b8;margin-top:6px;}
.tmpmp-styled-table{width:100%;border-collapse:collapse;font-size:13px;}
.tmpmp-styled-table th{background:#f8fafc;color:#475569;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;padding:10px 14px;text-align:left;border-bottom:2px solid #e2e8f0;}
.tmpmp-styled-table td{padding:12px 14px;border-bottom:1px solid #f1f5f9;color:#334155;vertical-align:middle;}
.tmpmp-styled-table tr:last-child td{border-bottom:none;}
.tmpmp-styled-table tr:hover td{background:#fafbff;}
.tmpmp-icon-btn{display:inline-flex;align-items:center;gap:5px;padding:5px 10px;border-radius:6px;font-size:12px;font-weight:600;cursor:pointer;border:1.5px solid #e2e8f0;background:#fff;transition:all .15s;text-decoration:none;}
.tmpmp-icon-btn:hover{border-color:#6366f1;color:#6366f1;}
.tmpmp-icon-btn--danger:hover{border-color:#ef4444;color:#ef4444;}
.tmpmp-add-btn{display:inline-flex;align-items:center;gap:8px;padding:9px 18px;background:linear-gradient(135deg,#6366f1,#8b5cf6);color:#fff;border:none;border-radius:8px;font-size:13px;font-weight:700;cursor:pointer;font-family:inherit;transition:all .15s;}
.tmpmp-add-btn:hover{opacity:.9;transform:translateY(-1px);box-shadow:0 4px 12px rgba(99,102,241,.3);}
.tmpmp-empty-row td{text-align:center;padding:32px!important;color:#94a3b8;}
/* Modal */
.tmpmp-modal-overlay{display:none;position:fixed;inset:0;background:rgba(15,23,42,.5);z-index:100000;align-items:center;justify-content:center;}
.tmpmp-modal-overlay.is-open{display:flex;}
.tmpmp-modal-box{background:#fff;border-radius:16px;max-width:760px;width:95%;max-height:92vh;overflow-y:auto;padding:28px 32px;box-shadow:0 25px 60px rgba(0,0,0,.25);position:relative;}
.tmpmp-modal-close{position:absolute;top:16px;right:18px;background:none;border:none;font-size:22px;cursor:pointer;color:#94a3b8;line-height:1;}
.tmpmp-modal-close:hover{color:#334155;}
.tmpmp-modal-title{margin:0 0 22px;font-size:17px;font-weight:700;color:#1e293b;}
.tmpmp-modal-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:14px;margin-bottom:14px;}
.tmpmp-modal-label{display:flex;flex-direction:column;gap:5px;font-size:12px;font-weight:700;color:#475569;text-transform:uppercase;letter-spacing:.4px;}
.tmpmp-modal-label input,.tmpmp-modal-label select,.tmpmp-modal-label textarea{font-size:13px;font-weight:400;color:#334155;padding:8px 10px;border:1.5px solid #e2e8f0;border-radius:8px;outline:none;font-family:inherit;transition:border-color .15s;}
.tmpmp-modal-label input:focus,.tmpmp-modal-label select:focus,.tmpmp-modal-label textarea:focus{border-color:#6366f1;box-shadow:0 0 0 3px rgba(99,102,241,.1);}
.tmpmp-modal-checks{display:flex;flex-wrap:wrap;gap:12px 20px;margin:14px 0;}
.tmpmp-modal-checks label{display:flex;align-items:center;gap:6px;font-size:13px;color:#475569;cursor:pointer;}
.tmpmp-modal-footer{display:flex;gap:10px;margin-top:20px;padding-top:18px;border-top:1px solid #f1f5f9;}
.tmpmp-modal-save-btn{display:inline-flex;align-items:center;gap:7px;padding:9px 20px;background:linear-gradient(135deg,#6366f1,#8b5cf6);color:#fff;border:none;border-radius:8px;font-size:13px;font-weight:700;cursor:pointer;font-family:inherit;transition:all .15s;}
.tmpmp-modal-save-btn:hover{opacity:.9;}
.tmpmp-modal-cancel-btn{display:inline-flex;align-items:center;gap:7px;padding:9px 18px;background:#f8fafc;color:#475569;border:1.5px solid #e2e8f0;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;font-family:inherit;transition:all .15s;}
.tmpmp-modal-cancel-btn:hover{border-color:#94a3b8;}
@media(max-width:600px){.tmpmp-page-field{grid-template-columns:1fr;}.tmpmp-page-card{padding:16px 14px;}.tmpmp-modal-grid{grid-template-columns:1fr 1fr;}}
</style>

<!-- Header action -->
<div style="margin-bottom:20px;">
    <button class="tmpmp-add-btn" id="tmpmp-add-plan-btn">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        <?php esc_html_e('Add New Plan','tempmail-pro'); ?>
    </button>
</div>

<!-- Plan Modal -->
<div id="tmpmp-plan-modal" class="tmpmp-modal-overlay">
<div class="tmpmp-modal-box">
    <button class="tmpmp-modal-close" id="tmpmp-plan-modal-close">&times;</button>
    <h2 class="tmpmp-modal-title" id="tmpmp-plan-modal-title"><?php esc_html_e('Add New Plan','tempmail-pro'); ?></h2>
    <input type="hidden" id="tmpmp-plan-id" name="id" value="0">
    <form id="tmpmp-plan-form">

    <!-- Basic Info -->
    <p class="tmpmp-page-section-title">📋 <?php esc_html_e('Basic Info','tempmail-pro'); ?></p>
    <div class="tmpmp-modal-grid">
        <label class="tmpmp-modal-label">Slug<input type="text" name="slug" id="pf-slug" placeholder="pro" required></label>
        <label class="tmpmp-modal-label">Name<input type="text" name="name" id="pf-name" placeholder="Pro" required></label>
        <label class="tmpmp-modal-label">Monthly ($)<input type="number" name="price_monthly" id="pf-price-monthly" value="9.99" step="0.01" min="0"></label>
        <label class="tmpmp-modal-label">Yearly ($)<input type="number" name="price_yearly" id="pf-price-yearly" value="79.99" step="0.01" min="0"></label>
        <label class="tmpmp-modal-label">Sort Order<input type="number" name="sort_order" value="0"></label>
    </div>

    <!-- Limits -->
    <p class="tmpmp-page-section-title" style="margin-top:18px;">⚙️ <?php esc_html_e('Limits','tempmail-pro'); ?></p>
    <div class="tmpmp-modal-grid">
        <label class="tmpmp-modal-label">Max Inboxes<input type="number" name="max_inboxes" id="pf-max-inboxes" value="10"></label>
        <label class="tmpmp-modal-label">Lifetime (min)<input type="number" name="inbox_lifetime" id="pf-lifetime" value="120"></label>
        <label class="tmpmp-modal-label">Refresh (sec)<input type="number" name="refresh_interval" value="10"></label>
        <label class="tmpmp-modal-label">Storage (MB)<input type="number" name="max_storage_mb" value="50"></label>
    </div>

    <!-- Domains & Features -->
    <p class="tmpmp-page-section-title" style="margin-top:18px;">🌐 <?php esc_html_e('Domains & Features','tempmail-pro'); ?></p>
    <div class="tmpmp-modal-grid" style="grid-template-columns:1fr 1fr;">
        <label class="tmpmp-modal-label">Allowed Domain Categories (JSON)
            <input type="text" name="domains_allowed" value='["free"]' placeholder='["free","premium","vip"]'>
        </label>
        <label class="tmpmp-modal-label">Features (one per line)
            <textarea name="features" rows="4" placeholder="10 inboxes&#10;2hr lifetime&#10;No ads"></textarea>
        </label>
    </div>

    <!-- Toggles -->
    <p class="tmpmp-page-section-title" style="margin-top:18px;">✅ <?php esc_html_e('Capabilities','tempmail-pro'); ?></p>
    <div class="tmpmp-modal-checks">
        <label><input type="checkbox" name="has_custom_user" value="1"> <?php esc_html_e('Custom Username','tempmail-pro'); ?></label>
        <label><input type="checkbox" name="has_api_access"  value="1"> <?php esc_html_e('API Access','tempmail-pro'); ?></label>
        <label><input type="checkbox" name="has_attachments" value="1"> <?php esc_html_e('Attachments','tempmail-pro'); ?></label>
        <label><input type="checkbox" name="no_ads"          value="1"> <?php esc_html_e('No Ads','tempmail-pro'); ?></label>
        <label><input type="checkbox" name="is_active"       value="1" checked> <?php esc_html_e('Active','tempmail-pro'); ?></label>
    </div>

    <div class="tmpmp-modal-footer">
        <button type="button" class="tmpmp-modal-save-btn" id="tmpmp-save-plan-btn">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21H5a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v11a2 2 0 01-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
            <?php esc_html_e('Save Plan','tempmail-pro'); ?>
        </button>
        <button type="button" class="tmpmp-modal-cancel-btn" id="tmpmp-plan-cancel"><?php esc_html_e('Cancel','tempmail-pro'); ?></button>
    </div>
    </form>
</div>
</div>

<!-- Plans Table -->
<div class="tmpmp-page-card">
    <p class="tmpmp-page-section-title">💎 <?php esc_html_e('All Plans','tempmail-pro'); ?></p>
    <div style="overflow-x:auto;">
    <table class="tmpmp-styled-table">
    <thead><tr>
        <th>ID</th>
        <th><?php esc_html_e('Plan','tempmail-pro'); ?></th>
        <th><?php esc_html_e('Monthly','tempmail-pro'); ?></th>
        <th><?php esc_html_e('Yearly','tempmail-pro'); ?></th>
        <th><?php esc_html_e('Inboxes','tempmail-pro'); ?></th>
        <th><?php esc_html_e('Lifetime','tempmail-pro'); ?></th>
        <th><?php esc_html_e('Features','tempmail-pro'); ?></th>
        <th><?php esc_html_e('Status','tempmail-pro'); ?></th>
        <th><?php esc_html_e('Actions','tempmail-pro'); ?></th>
    </tr></thead>
    <tbody>
    <?php foreach( $plans as $p ): ?>
    <tr id="plan-row-<?php echo intval($p->id); ?>"
        data-slug="<?php echo esc_attr($p->slug); ?>"
        data-monthly="<?php echo esc_attr($p->price_monthly); ?>"
        data-yearly="<?php echo esc_attr($p->price_yearly); ?>"
        data-max-inboxes="<?php echo esc_attr($p->max_inboxes); ?>"
        data-lifetime="<?php echo esc_attr($p->inbox_lifetime); ?>">
        <td style="color:#94a3b8;font-size:12px;">#<?php echo intval($p->id); ?></td>
        <td>
            <strong class="plan-name" style="display:block;"><?php echo esc_html($p->name); ?></strong>
            <code style="font-size:11px;background:#f1f5f9;padding:1px 5px;border-radius:3px;color:#6366f1;"><?php echo esc_html($p->slug); ?></code>
        </td>
        <td><strong>$<?php echo number_format($p->price_monthly,2); ?></strong><span style="color:#94a3b8;font-size:11px;">/mo</span></td>
        <td><strong>$<?php echo number_format($p->price_yearly,2); ?></strong><span style="color:#94a3b8;font-size:11px;">/yr</span></td>
        <td><?php echo $p->max_inboxes == -1 ? '∞' : intval($p->max_inboxes); ?></td>
        <td><?php echo intval($p->inbox_lifetime); ?> min</td>
        <td style="font-size:12px;max-width:180px;color:#64748b;">
            <?php
            $feats = json_decode($p->features ?? '[]', true) ?: [];
            echo esc_html(implode(', ', array_slice($feats, 0, 3)));
            if(count($feats) > 3) echo '…';
            ?>
        </td>
        <td><span class="tmpmp-badge <?php echo $p->is_active ? 'tmpmp-badge--green' : 'tmpmp-badge--red'; ?>"><?php echo $p->is_active ? 'Active' : 'Inactive'; ?></span></td>
        <td style="white-space:nowrap;">
            <button class="tmpmp-icon-btn tmpmp-edit-plan" data-id="<?php echo intval($p->id); ?>" data-plan='<?php echo esc_attr(json_encode($p)); ?>'>
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                Edit
            </button>
            <?php if($p->slug !== 'free'): ?>
            <button class="tmpmp-icon-btn tmpmp-icon-btn--danger tmpmp-delete-plan" data-id="<?php echo intval($p->id); ?>" style="margin-left:4px;">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/></svg>
                Delete
            </button>
            <?php endif; ?>
        </td>
    </tr>
    <?php endforeach; ?>
    <?php if(empty($plans)): ?><tr class="tmpmp-empty-row"><td colspan="9"><?php esc_html_e('No plans yet.','tempmail-pro'); ?></td></tr><?php endif; ?>
    </tbody>
    </table>
    </div>
</div>

</div><!-- /.wrap -->

<script>
jQuery(function($){
    // Open Add modal
    $('#tmpmp-add-plan-btn').on('click', function(){
        $('#tmpmp-plan-form')[0].reset();
        $('#tmpmp-plan-id').val('0');
        $('#tmpmp-plan-modal-title').text('<?php esc_html_e('Add New Plan','tempmail-pro'); ?>');
        $('#tmpmp-plan-modal').addClass('is-open');
    });
    // Close
    $('#tmpmp-plan-modal-close, #tmpmp-plan-cancel').on('click', function(){
        $('#tmpmp-plan-modal').removeClass('is-open');
    });
    // Close on overlay click
    $('#tmpmp-plan-modal').on('click', function(e){
        if($(e.target).is('#tmpmp-plan-modal')) $(this).removeClass('is-open');
    });
    // Edit plan
    $(document).on('click', '.tmpmp-edit-plan', function(){
        const p = $(this).data('plan');
        $('#tmpmp-plan-id').val(p.id);
        $('#tmpmp-plan-form [name="slug"]').val(p.slug);
        $('#tmpmp-plan-form [name="name"]').val(p.name);
        $('#tmpmp-plan-form [name="price_monthly"]').val(p.price_monthly);
        $('#tmpmp-plan-form [name="price_yearly"]').val(p.price_yearly);
        $('#tmpmp-plan-form [name="max_inboxes"]').val(p.max_inboxes);
        $('#tmpmp-plan-form [name="inbox_lifetime"]').val(p.inbox_lifetime);
        $('#tmpmp-plan-form [name="refresh_interval"]').val(p.refresh_interval);
        $('#tmpmp-plan-form [name="max_storage_mb"]').val(p.max_storage_mb);
        $('#tmpmp-plan-form [name="sort_order"]').val(p.sort_order);
        $('#tmpmp-plan-form [name="domains_allowed"]').val(p.domains_allowed);
        try { $('#tmpmp-plan-form [name="features"]').val(JSON.parse(p.features||'[]').join('\n')); } catch(e){}
        $('#tmpmp-plan-form [name="has_custom_user"]').prop('checked', !!+p.has_custom_user);
        $('#tmpmp-plan-form [name="has_api_access"]').prop('checked',  !!+p.has_api_access);
        $('#tmpmp-plan-form [name="has_attachments"]').prop('checked',  !!+p.has_attachments);
        $('#tmpmp-plan-form [name="no_ads"]').prop('checked',           !!+p.no_ads);
        $('#tmpmp-plan-form [name="is_active"]').prop('checked',        !!+p.is_active);
        $('#tmpmp-plan-modal-title').text('<?php esc_html_e('Edit Plan','tempmail-pro'); ?>: ' + p.name);
        $('#tmpmp-plan-modal').addClass('is-open');
    });
});
</script>
