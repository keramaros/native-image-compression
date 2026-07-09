<?php
/**
 * Dashboard Template
 */
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wrap kaic-dashboard">
    <h1><?php _e('Image Compression', 'kaic-image-compression'); ?></h1>

    <div class="kaic-top-sections">
        <div class="kaic-section">
            <h2><?php _e('Library overview', 'kaic-image-compression'); ?></h2>
            <p><?php _e('Compress your full image library in one click. Press below to instantly improve your site speed!', 'kaic-image-compression'); ?></p>

            <div class="kaic-stats-box">
                <div class="kaic-stat">
                    <span class="label"><?php _e('UPLOADED IMAGES', 'kaic-image-compression'); ?></span>
                    <span class="value" id="kaic-total-attachments"><?php echo esc_html($stats['total_attachments']); ?></span>
                </div>
                <div class="kaic-stat">
                    <span class="label"><?php _e('TOTAL FILES', 'kaic-image-compression'); ?></span>
                    <span class="value">
                        <span id="kaic-total-files"><?php echo esc_html($stats['total_files']); ?></span>
                    </span>
                </div>
                <div class="kaic-stat">
                    <span class="label"><?php _e('COMPRESSED', 'kaic-image-compression'); ?></span>
                    <span class="value">
                        <span class="percentage"><span class="kaic-processed-percent"><?php echo esc_html($stats['processed_percent']); ?></span>%</span>
                    </span>
                </div>
            </div>

            <p><span class="remember-label"><?php _e('Remember', 'kaic-image-compression'); ?></span> <?php _e('Keep this page open to run the plugin. If stopped, you can resume your progress at any time.', 'kaic-image-compression'); ?></p>
        </div>

        <div class="kaic-section">
            <h2><?php _e('Total Savings', 'kaic-image-compression'); ?></h2>
            <p><?php _e('Insights calculated from your entire media library.', 'kaic-image-compression'); ?></p>

            <div class="kaic-savings-container">
                <div class="kaic-savings-circle" style="--percentage: <?php echo esc_attr($stats['total_saved_percent']); ?>;">
                    <div class="inner">
                        <span id="kaic-total-saved-percent"><?php echo esc_html($stats['total_saved_percent']); ?>%</span>
                        <span class="subtext"><?php _e('savings', 'kaic-image-compression'); ?></span>
                    </div>
                </div>
                <div class="kaic-savings-stats">
                    <div class="stat-line">
                        <span class="value" id="kaic-processed-count"><?php echo esc_html($stats['processed_count']); ?></span>
                        <span class="label"><?php _e('images compressed', 'kaic-image-compression'); ?></span>
                    </div>
                    <div class="stat-line">
                        <span class="value" id="kaic-original-size"><?php echo esc_html($stats['original_size_formatted']); ?></span>
                        <span class="label"><?php _e('initial size', 'kaic-image-compression'); ?></span>
                    </div>
                    <div class="stat-line highlighted">
                        <span class="value" id="kaic-compressed-size"><?php echo esc_html($stats['compressed_size_formatted']); ?></span>
                        <span class="label"><?php _e('compressed', 'kaic-image-compression'); ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="kaic-progress-section">
        <div class="kaic-controls">
            <button id="kaic-start" class="button button-primary"><?php _e('Start Compression', 'kaic-image-compression'); ?></button>
            <button id="kaic-stop" class="button button-secondary" disabled style="display: none;"><?php _e('Stop', 'kaic-image-compression'); ?></button>
            <span class="kaic-spinner spinner"></span>
        </div>

        <div id="kaic-progress-table-container">
            <table class="wp-list-table widefat fixed striped">
                <thead>
                <tr>
                    <th class="column-image"><?php _e('Image', 'kaic-image-compression'); ?></th>
                    <th class="column-initial-size"><?php _e('Initial Size', 'kaic-image-compression'); ?></th>
                    <th class="column-compressed-size"><?php _e('Compressed', 'kaic-image-compression'); ?></th>
                    <th class="column-savings"><?php _e('Savings', 'kaic-image-compression'); ?></th>
                    <th class="column-status"><?php _e('Status', 'kaic-image-compression'); ?></th>
                </tr>
                </thead>
                <tbody id="kaic-progress-log">
                </tbody>
            </table>
        </div>
    </div>
</div>
