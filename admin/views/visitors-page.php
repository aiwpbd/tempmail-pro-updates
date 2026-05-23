<?php defined('ABSPATH') || exit; ?>
<?php
// Ensure table exists (for already-installed instances)
TempMail_Visitors::maybe_create_table();

// Query args
$paged  = max(1, intval($_GET['paged'] ?? 1));
$limit  = 50;
$offset = ($paged - 1) * $limit;
$search = sanitize_text_field($_GET['s'] ?? '');
$filter = sanitize_text_field($_GET['filter'] ?? 'all');

$args    = ['limit'=>$limit,'offset'=>$offset,'search'=>$search,'filter'=>$filter];
$rows    = TempMail_Visitors::get_visitors($args);
$total   = TempMail_Visitors::get_total_count($args);
$stats   = TempMail_Visitors::get_stats();
$pages   = ceil($total / $limit);
$chart   = TempMail_Visitors::get_chart_data(14);
$top_pg  = TempMail_Visitors::get_top_pages(8);
$browsers = TempMail_Visitors::get_top_browsers();
$oses    = TempMail_Visitors::get_top_oses();

// Browser colours
$b_colors = ['Chrome'=>'#4285f4','Firefox'=>'#ff6d00','Safari'=>'#1c9be1','Edge'=>'#0078d4','Opera'=>'#ff1b2d','IE'=>'#1ebbee','Other'=>'#94a3b8'];
$os_colors = ['Windows'=>'#0078d4','macOS'=>'#555555','Android'=>'#3ddc84','iOS'=>'#007aff','Linux'=>'#f97316','Other'=>'#94a3b8'];
?>
<style>
.tmpmp-vis-page{font-family:'Inter',sans-serif;color:#0f172a;}
.tmpmp-vis-header{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:24px;}
.tmpmp-vis-title{font-size:22px;font-weight:800;color:#0f172a;display:flex;align-items:center;gap:10px;margin:0;}
.tmpmp-vis-title span{font-size:13px;font-weight:500;color:#64748b;margin-left:6px;}
.tmpmp-vis-stats{display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:14px;margin-bottom:24px;}
.tmpmp-vis-stat{background:#fff;border:1.5px solid #f1f5f9;border-radius:14px;padding:18px 20px;text-align:center;box-shadow:0 1px 3px rgba(0,0,0,.04);}
.tmpmp-vis-stat-val{font-size:28px;font-weight:800;color:#0f172a;line-height:1;}
.tmpmp-vis-stat-label{font-size:11.5px;color:#64748b;margin-top:6px;font-weight:600;text-transform:uppercase;letter-spacing:.5px;}
.tmpmp-vis-toolbar{display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:16px;}
.tmpmp-vis-search{padding:9px 14px;border:1.5px solid #e2e8f0;border-radius:9px;font-size:13px;min-width:220px;outline:none;}
.tmpmp-vis-search:focus{border-color:#6366f1;}
.tmpmp-vis-filter{display:flex;gap:6px;}
.tmpmp-vis-filter a{padding:7px 14px;border-radius:8px;font-size:12.5px;font-weight:700;text-decoration:none;color:#64748b;border:1.5px solid #e2e8f0;background:#fff;transition:all .2s;}
.tmpmp-vis-filter a.active,.tmpmp-vis-filter a:hover{border-color:#6366f1;color:#6366f1;background:#f5f3ff;}
.tmpmp-vis-table-wrap{background:#fff;border:1.5px solid #f1f5f9;border-radius:14px;overflow:hidden;box-shadow:0 1px 4px rgba(0,0,0,.04);margin-bottom:22px;}
.tmpmp-vis-table{width:100%;border-collapse:collapse;font-size:13px;}
.tmpmp-vis-table th{background:#f8fafc;padding:11px 14px;text-align:left;font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:.6px;color:#64748b;border-bottom:1.5px solid #f1f5f9;}
.tmpmp-vis-table td{padding:10px 14px;border-bottom:1px solid #f8fafc;vertical-align:middle;}
.tmpmp-vis-table tr:last-child td{border-bottom:none;}
.tmpmp-vis-table tr:hover td{background:#fafbff;}
.tmpmp-vis-ip{font-family:monospace;font-size:12.5px;color:#6366f1;font-weight:700;}
.tmpmp-vis-page-url{max-width:260px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:12px;color:#374151;}
.tmpmp-vis-badge{display:inline-block;padding:2px 8px;border-radius:20px;font-size:11px;font-weight:700;}
.tmpmp-vis-badge--bot{background:#fee2e2;color:#dc2626;}
.tmpmp-vis-badge--human{background:#dcfce7;color:#16a34a;}
.tmpmp-vis-pagination{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;margin-top:14px;}
.tmpmp-vis-pagination a,.tmpmp-vis-pagination span{padding:7px 13px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:13px;font-weight:600;text-decoration:none;color:#374151;}
.tmpmp-vis-pagination a:hover{border-color:#6366f1;color:#6366f1;}
.tmpmp-vis-pagination .current{background:#6366f1;color:#fff;border-color:#6366f1;}
.tmpmp-vis-charts{display:grid;grid-template-columns:2fr 1fr 1fr;gap:16px;margin-bottom:22px;}
.tmpmp-vis-chart-card{background:#fff;border:1.5px solid #f1f5f9;border-radius:14px;padding:20px;box-shadow:0 1px 3px rgba(0,0,0,.04);}
.tmpmp-vis-chart-card h3{margin:0 0 16px;font-size:13px;font-weight:800;text-transform:uppercase;letter-spacing:.5px;color:#64748b;}
.tmpmp-vis-top-pages{list-style:none;margin:0;padding:0;}
.tmpmp-vis-top-pages li{display:flex;align-items:center;justify-content:space-between;padding:7px 0;border-bottom:1px solid #f8fafc;font-size:12px;gap:8px;}
.tmpmp-vis-top-pages li:last-child{border-bottom:none;}
.tmpmp-vis-top-pages .url{overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:#374151;flex:1;}
.tmpmp-vis-top-pages .cnt{font-weight:800;color:#6366f1;white-space:nowrap;}
.tmpmp-vis-donut-list{list-style:none;margin:0;padding:0;}
.tmpmp-vis-donut-list li{display:flex;align-items:center;gap:8px;padding:5px 0;font-size:12.5px;}
.tmpmp-vis-donut-dot{width:10px;height:10px;border-radius:50%;flex-shrink:0;}
.tmpmp-vis-donut-label{flex:1;color:#374151;}
.tmpmp-vis-donut-val{font-weight:700;color:#0f172a;}
@media(max-width:900px){.tmpmp-vis-charts{grid-template-columns:1fr;}}
</style>

<div class="wrap tmpmp-vis-page">

    <div class="tmpmp-vis-header">
        <h1 class="tmpmp-vis-title">
            👁️ <?php esc_html_e('Visitors','tempmail-pro'); ?>
            <span><?php printf(esc_html__('%s total records','tempmail-pro'), number_format($stats['total'])); ?></span>
        </h1>
        <form method="get" style="display:flex;gap:8px;align-items:center;">
            <input type="hidden" name="page" value="tmpmp-visitors">
            <?php if($filter && $filter!=='all'): ?><input type="hidden" name="filter" value="<?php echo esc_attr($filter); ?>"><?php endif; ?>
            <input type="search" name="s" class="tmpmp-vis-search" placeholder="<?php esc_attr_e('Search IP, page, browser…','tempmail-pro'); ?>" value="<?php echo esc_attr($search); ?>">
            <button type="submit" style="padding:9px 16px;background:#6366f1;color:#fff;border:none;border-radius:9px;font-weight:700;cursor:pointer;">
                <?php esc_html_e('Search','tempmail-pro'); ?>
            </button>
            <?php if($search || $filter!=='all'): ?>
            <a href="<?php echo admin_url('admin.php?page=tmpmp-visitors'); ?>" style="padding:9px 14px;background:#f1f5f9;color:#374151;border-radius:9px;font-size:13px;font-weight:600;text-decoration:none;">
                ✕ <?php esc_html_e('Clear','tempmail-pro'); ?>
            </a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Stats row -->
    <div class="tmpmp-vis-stats">
        <div class="tmpmp-vis-stat">
            <div class="tmpmp-vis-stat-val"><?php echo number_format($stats['total']); ?></div>
            <div class="tmpmp-vis-stat-label"><?php esc_html_e('Total Visits','tempmail-pro'); ?></div>
        </div>
        <div class="tmpmp-vis-stat">
            <div class="tmpmp-vis-stat-val" style="color:#6366f1;"><?php echo number_format($stats['unique']); ?></div>
            <div class="tmpmp-vis-stat-label"><?php esc_html_e('Unique IPs','tempmail-pro'); ?></div>
        </div>
        <div class="tmpmp-vis-stat">
            <div class="tmpmp-vis-stat-val" style="color:#0ea5e9;"><?php echo number_format($stats['today']); ?></div>
            <div class="tmpmp-vis-stat-label"><?php esc_html_e('Today','tempmail-pro'); ?></div>
        </div>
        <div class="tmpmp-vis-stat">
            <div class="tmpmp-vis-stat-val" style="color:#10b981;"><?php echo number_format($stats['humans']); ?></div>
            <div class="tmpmp-vis-stat-label"><?php esc_html_e('Humans','tempmail-pro'); ?></div>
        </div>
        <div class="tmpmp-vis-stat">
            <div class="tmpmp-vis-stat-val" style="color:#f59e0b;"><?php echo number_format($stats['bots']); ?></div>
            <div class="tmpmp-vis-stat-label"><?php esc_html_e('Bots','tempmail-pro'); ?></div>
        </div>
    </div>

    <!-- Charts row -->
    <div class="tmpmp-vis-charts">
        <!-- Line chart: visits over 14 days -->
        <div class="tmpmp-vis-chart-card">
            <h3><?php esc_html_e('Visits — Last 14 Days','tempmail-pro'); ?></h3>
            <canvas id="tmpmp-vis-line" height="140"></canvas>
        </div>
        <!-- Top pages -->
        <div class="tmpmp-vis-chart-card">
            <h3><?php esc_html_e('Top Pages','tempmail-pro'); ?></h3>
            <ul class="tmpmp-vis-top-pages">
                <?php foreach($top_pg as $pg): ?>
                <li>
                    <span class="url" title="<?php echo esc_attr($pg['page_url']); ?>">
                        <?php echo esc_html( parse_url($pg['page_url'], PHP_URL_PATH) ?: '/' ); ?>
                    </span>
                    <span class="cnt"><?php echo number_format($pg['views']); ?></span>
                </li>
                <?php endforeach; ?>
                <?php if(empty($top_pg)): ?><li style="color:#94a3b8;"><?php esc_html_e('No data yet','tempmail-pro'); ?></li><?php endif; ?>
            </ul>
        </div>
        <!-- Browser + OS donuts -->
        <div class="tmpmp-vis-chart-card">
            <h3><?php esc_html_e('Browser / OS','tempmail-pro'); ?></h3>
            <canvas id="tmpmp-vis-donut" height="110" style="margin-bottom:14px;"></canvas>
            <ul class="tmpmp-vis-donut-list">
                <?php foreach($browsers as $b):
                    $color = $b_colors[$b['browser']] ?? '#94a3b8'; ?>
                <li>
                    <span class="tmpmp-vis-donut-dot" style="background:<?php echo esc_attr($color); ?>;"></span>
                    <span class="tmpmp-vis-donut-label"><?php echo esc_html($b['browser']); ?></span>
                    <span class="tmpmp-vis-donut-val"><?php echo number_format($b['cnt']); ?></span>
                </li>
                <?php endforeach; ?>
                <?php if(empty($browsers)): ?><li style="color:#94a3b8;"><?php esc_html_e('No data yet','tempmail-pro'); ?></li><?php endif; ?>
            </ul>
        </div>
    </div>

    <!-- Filter tabs -->
    <div class="tmpmp-vis-toolbar">
        <div class="tmpmp-vis-filter">
            <?php
            $base = admin_url('admin.php?page=tmpmp-visitors' . ($search ? '&s='.urlencode($search) : ''));
            $filters = ['all'=>__('All','tempmail-pro'),'humans'=>__('Humans','tempmail-pro'),'bots'=>__('Bots','tempmail-pro')];
            foreach($filters as $key=>$label):
                $active = ($filter===$key || ($key==='all' && !$filter)) ? 'active' : '';
                $href   = $key==='all' ? $base : $base.'&filter='.$key;
            ?>
            <a href="<?php echo esc_url($href); ?>" class="<?php echo $active; ?>"><?php echo esc_html($label); ?></a>
            <?php endforeach; ?>
        </div>
        <span style="font-size:12.5px;color:#94a3b8;margin-left:auto;">
            <?php printf(esc_html__('Showing %d–%d of %d','tempmail-pro'), $offset+1, min($offset+$limit,$total), $total); ?>
        </span>
    </div>

    <!-- Table -->
    <div class="tmpmp-vis-table-wrap">
        <table class="tmpmp-vis-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th><?php esc_html_e('IP Address','tempmail-pro'); ?></th>
                    <th><?php esc_html_e('Country','tempmail-pro'); ?></th>
                    <th><?php esc_html_e('Page','tempmail-pro'); ?></th>
                    <th><?php esc_html_e('Browser','tempmail-pro'); ?></th>
                    <th><?php esc_html_e('OS','tempmail-pro'); ?></th>
                    <th><?php esc_html_e('Referrer','tempmail-pro'); ?></th>
                    <th><?php esc_html_e('Type','tempmail-pro'); ?></th>
                    <th><?php esc_html_e('Time','tempmail-pro'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if(empty($rows)): ?>
                <tr><td colspan="9" style="text-align:center;padding:40px;color:#94a3b8;">
                    <?php esc_html_e('No visitor records found.','tempmail-pro'); ?>
                </td></tr>
                <?php else: ?>
                <?php foreach($rows as $i=>$row):
                    $path = parse_url($row['page_url'], PHP_URL_PATH) ?: '/';
                    $ref_host = $row['referrer'] ? (parse_url($row['referrer'], PHP_URL_HOST) ?: $row['referrer']) : '—';
                ?>
                <tr>
                    <td style="color:#94a3b8;font-size:12px;"><?php echo $offset+$i+1; ?></td>
                    <td><span class="tmpmp-vis-ip"><?php echo esc_html($row['ip']); ?></span></td>
                    <td style="font-size:13px;">
                        <?php if($row['country']): ?>
                        <span title="<?php echo esc_attr($row['country']); ?>"><?php echo esc_html($row['country']); ?></span>
                        <?php else: ?>—<?php endif; ?>
                    </td>
                    <td><span class="tmpmp-vis-page-url" title="<?php echo esc_attr($row['page_url']); ?>">
                        <?php echo esc_html($path); ?>
                    </span></td>
                    <td style="font-size:12.5px;"><?php echo esc_html($row['browser'] ?: '—'); ?></td>
                    <td style="font-size:12.5px;"><?php echo esc_html($row['os'] ?: '—'); ?></td>
                    <td style="font-size:12px;color:#64748b;" title="<?php echo esc_attr($row['referrer']); ?>">
                        <?php echo esc_html($ref_host); ?>
                    </td>
                    <td>
                        <?php if($row['is_bot']): ?>
                        <span class="tmpmp-vis-badge tmpmp-vis-badge--bot"><?php esc_html_e('Bot','tempmail-pro'); ?></span>
                        <?php else: ?>
                        <span class="tmpmp-vis-badge tmpmp-vis-badge--human"><?php esc_html_e('Human','tempmail-pro'); ?></span>
                        <?php endif; ?>
                    </td>
                    <td style="font-size:12px;color:#64748b;white-space:nowrap;">
                        <?php echo esc_html( get_date_from_gmt($row['visited_at'], 'Y-m-d H:i') ); ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if($pages > 1): ?>
    <div class="tmpmp-vis-pagination">
        <div style="font-size:13px;color:#64748b;">
            <?php printf(esc_html__('Page %d of %d','tempmail-pro'), $paged, $pages); ?>
        </div>
        <div style="display:flex;gap:6px;flex-wrap:wrap;">
            <?php if($paged>1): ?>
            <a href="<?php echo esc_url(add_query_arg('paged',$paged-1)); ?>">← <?php esc_html_e('Prev','tempmail-pro'); ?></a>
            <?php endif; ?>
            <?php
            $start = max(1,$paged-3); $end = min($pages,$paged+3);
            for($p=$start;$p<=$end;$p++):
                $cls = $p===$paged ? 'current' : '';
            ?>
            <a href="<?php echo esc_url(add_query_arg('paged',$p)); ?>" class="<?php echo $cls; ?>"><?php echo $p; ?></a>
            <?php endfor; ?>
            <?php if($paged<$pages): ?>
            <a href="<?php echo esc_url(add_query_arg('paged',$paged+1)); ?>"><?php esc_html_e('Next','tempmail-pro'); ?> →</a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

</div><!-- .tmpmp-vis-page -->

<!-- Chart.js via CDN (lightweight) -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
(function(){
    // Line chart — 14-day visits
    const chartData = <?php echo wp_json_encode($chart); ?>;
    const labels    = chartData.map(r=>r.d);
    const totals    = chartData.map(r=>parseInt(r.total)||0);
    const humans    = chartData.map(r=>parseInt(r.humans)||0);
    const bots      = totals.map((v,i)=>v-(humans[i]||0));

    if(document.getElementById('tmpmp-vis-line') && labels.length){
        new Chart(document.getElementById('tmpmp-vis-line'),{
            type:'line',
            data:{
                labels,
                datasets:[
                    {label:'<?php echo esc_js(__('Humans','tempmail-pro')); ?>',data:humans,borderColor:'#6366f1',backgroundColor:'rgba(99,102,241,.10)',fill:true,tension:.4,pointRadius:3},
                    {label:'<?php echo esc_js(__('Bots','tempmail-pro')); ?>',  data:bots,  borderColor:'#f59e0b',backgroundColor:'rgba(245,158,11,.07)',fill:true,tension:.4,pointRadius:3}
                ]
            },
            options:{responsive:true,plugins:{legend:{position:'bottom',labels:{font:{size:11}}}},scales:{y:{beginAtZero:true,ticks:{precision:0}}}}
        });
    }

    // Donut — browsers
    const bdata = <?php echo wp_json_encode(array_values($browsers)); ?>;
    const bColors = <?php echo wp_json_encode(array_values($b_colors)); ?>;
    if(document.getElementById('tmpmp-vis-donut') && bdata.length){
        new Chart(document.getElementById('tmpmp-vis-donut'),{
            type:'doughnut',
            data:{
                labels: bdata.map(b=>b.browser),
                datasets:[{data:bdata.map(b=>parseInt(b.cnt)||0),backgroundColor:bdata.map(b=>({'Chrome':'#4285f4','Firefox':'#ff6d00','Safari':'#1c9be1','Edge':'#0078d4','Opera':'#ff1b2d','IE':'#1ebbee','Other':'#94a3b8'})[b.browser]||'#94a3b8'),borderWidth:2}]
            },
            options:{responsive:true,cutout:'65%',plugins:{legend:{display:false}}}
        });
    }
})();
</script>
