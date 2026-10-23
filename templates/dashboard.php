<?php
/**
 * Dashboard Template
 */
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wrap kaic-dashboard">
    <h1><?php esc_html_e('Native Image Compression', 'native-image-compression'); ?></h1>

    <div class="kaic-top-sections">
        <div class="kaic-section">
            <h2><?php esc_html_e('Library overview', 'native-image-compression'); ?></h2>
            <p><?php esc_html_e('Compress your full image library in one click. Press below to instantly improve your site speed!', 'native-image-compression'); ?></p>

            <div class="kaic-stats-box">
                <div class="kaic-stat">
                    <span class="label"><?php esc_html_e('UPLOADED IMAGES', 'native-image-compression'); ?></span>
                    <span class="value" id="kaic-total-attachments"><?php echo esc_html($stats['total_attachments']); ?></span>
                </div>
                <div class="kaic-stat">
                    <span class="label"><?php esc_html_e('TOTAL FILES', 'native-image-compression'); ?></span>
                    <span class="value" id="kaic-total-files"><?php echo esc_html($stats['total_files']); ?></span>
                </div>
                <div class="kaic-stat">
                    <span class="label"><?php esc_html_e('COMPRESSED', 'native-image-compression'); ?></span>
                    <span class="value" id="kaic-processed-percent"><?php echo esc_html($stats['processed_percent']); ?>%</span>
                </div>
            </div>

            <p><span class="remember-label"><?php esc_html_e('Remember', 'native-image-compression'); ?></span> <?php esc_html_e('Keep this page open to run the plugin. If stopped, you can resume your progress at any time.', 'native-image-compression'); ?></p>
        </div>

        <div class="kaic-section">
            <h2><?php esc_html_e('Total Savings', 'native-image-compression'); ?></h2>
            <p><?php esc_html_e('Insights calculated from your entire media library.', 'native-image-compression'); ?></p>

            <div class="kaic-savings-container">
                <div class="kaic-savings-circle" style="--percentage: <?php echo esc_attr($stats['total_saved_percent']); ?>;">
                    <div class="inner">
                        <span id="kaic-total-saved-percent"><?php echo esc_html($stats['total_saved_percent']); ?>%</span>
                        <span class="subtext"><?php esc_html_e('savings', 'native-image-compression'); ?></span>
                    </div>
                </div>
                <div class="kaic-savings-stats">
                    <div class="stat-line">
                        <span class="value" id="kaic-processed-count"><?php echo esc_html($stats['processed_count']); ?></span>
                        <span class="label"><?php esc_html_e('images compressed', 'native-image-compression'); ?></span>
                    </div>
                    <div class="stat-line">
                        <span class="value" id="kaic-original-size"><?php echo esc_html($stats['original_size_formatted']); ?></span>
                        <span class="label"><?php esc_html_e('initial size', 'native-image-compression'); ?></span>
                    </div>
                    <div class="stat-line highlighted">
                        <span class="value" id="kaic-compressed-size"><?php echo esc_html($stats['compressed_size_formatted']); ?></span>
                        <span class="label"><?php esc_html_e('compressed', 'native-image-compression'); ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="kaic-progress-section">
        <div class="kaic-controls">
            <button id="kaic-start" class="button button-primary"><?php esc_html_e('Start Compression', 'native-image-compression'); ?></button>
            <div class="kaic-settings-link">
                <a href="<?php echo esc_url(admin_url('options-general.php?page=native-image-compression-settings')); ?>"><?php esc_html_e('Tune compression settings', 'native-image-compression'); ?></a>
            </div>
            <button id="kaic-stop" class="button button-secondary" disabled style="display: none;"><?php esc_html_e('Stop', 'native-image-compression'); ?></button>
            <span class="kaic-spinner spinner"></span>
        </div>

        <div id="kaic-progress-table-container">
            <table class="wp-list-table widefat fixed striped">
                <thead>
                <tr>
                    <th class="column-image"><?php esc_html_e('Image', 'native-image-compression'); ?></th>
                    <th class="column-initial-size"><?php esc_html_e('Initial Size', 'native-image-compression'); ?></th>
                    <th class="column-compressed-size"><?php esc_html_e('Compressed', 'native-image-compression'); ?></th>
                    <th class="column-savings"><?php esc_html_e('Savings', 'native-image-compression'); ?></th>
                    <th class="column-status"><?php esc_html_e('Status', 'native-image-compression'); ?></th>
                </tr>
                </thead>
                <tbody id="kaic-progress-log">
                </tbody>
            </table>
        </div>
    </div>
</div>
