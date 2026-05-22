<?php if ( ! defined('ABSPATH') ) exit; ?>
<div class="wrap tmpmp-admin-wrap">
<h1 class="tmpmp-admin-title"><span class="dashicons dashicons-list-view"></span> <?php esc_html_e('Changelog','tempmail-pro'); ?></h1>
<div class="tmpmp-card">
<div class="tmpmp-changelog">
<?php
$i = 0;
foreach ( $log as $version => $entry ):
    $is_current = ($version === TMPMP_VERSION);
?>
<div class="tmpmp-cl-entry">
    <div class="tmpmp-cl-dot <?php echo $is_current ? 'current' : ''; ?>">v<?php echo esc_html($version); ?></div>
    <div class="tmpmp-cl-body">
        <div class="tmpmp-cl-header">
            <span class="tmpmp-cl-ver">v<?php echo esc_html($version); ?></span>
            <span class="tmpmp-cl-label"><?php echo esc_html($entry['label']??''); ?></span>
            <span class="tmpmp-cl-date"><?php echo esc_html($entry['date']??''); ?></span>
            <?php if($is_current): ?><span class="tmpmp-badge tmpmp-badge--green">Current</span><?php endif; ?>
        </div>
        <ul class="tmpmp-cl-items">
            <?php foreach(($entry['features']??[]) as $f): ?>
                <li>✨ <?php echo esc_html($f); ?></li>
            <?php endforeach; ?>
            <?php foreach(($entry['bugfixes']??[]) as $b): ?>
                <li>🐛 <?php echo esc_html($b); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
</div>
<?php $i++; endforeach; ?>
</div>
</div>
</div>
