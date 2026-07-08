<?php
/**
 * Dashboard Template
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap akic-dashboard">
    <h1><?php _e( 'Image Compression', 'media-optimizer-stats' ); ?></h1>
    
    <div class="akic-top-sections">
        <div class="akic-section">
            <h2><?php _e( 'Available Images', 'media-optimizer-stats' ); ?></h2>
            <p><?php _e( 'Here you can start optimizing your entire library. Press the big button to start improving your website speed instantly!', 'media-optimizer-stats' ); ?></p>
            
            <div class="akic-stats-box">
                <div class="akic-stat">
                    <span class="label"><?php _e( 'UPLOADED IMAGES', 'media-optimizer-stats' ); ?></span>
                    <span class="value" id="akic-total-attachments"><?php echo esc_html( $stats['total_attachments'] ); ?></span>
                </div>
                <div class="akic-stat">
                    <span class="label"><?php _e( 'UNCOMPRESSED IMAGE SIZES', 'media-optimizer-stats' ); ?></span>
                    <span class="value" id="akic-total-files"><?php echo esc_html( $stats['total_files'] ); ?></span>
                </div>
            </div>
            
            <p><span class="remember-label"><?php _e( 'Remember', 'media-optimizer-stats' ); ?></span> <?php _e( 'For the plugin to do the work, you need to keep this page open. But no worries: when stopped, you can continue where you left off!', 'media-optimizer-stats' ); ?></p>
        </div>

        <div class="akic-section">
            <h2><?php _e( 'Total Savings', 'media-optimizer-stats' ); ?></h2>
            <p><?php _e( 'Statistics based on all available JPEG, PNG, and WebP images in your media library.', 'media-optimizer-stats' ); ?></p>
            
            <div class="akic-savings-container">
                <div class="akic-savings-circle">
                    <div class="inner">
                        <span id="akic-total-saved-percent"><?php echo esc_html( $stats['total_saved_percent'] ); ?>%</span>
                        <span class="subtext"><?php _e( 'savings', 'media-optimizer-stats' ); ?></span>
                    </div>
                </div>
                <div class="akic-savings-stats">
                    <div class="stat-line">
                        <span class="value" id="akic-processed-count"><?php echo esc_html( $stats['processed_count'] ); ?></span>
                        <span class="label"><?php _e( 'image sizes optimized', 'media-optimizer-stats' ); ?></span>
                    </div>
                    <div class="stat-line">
                        <span class="value" id="akic-original-size"><?php echo esc_html( $stats['original_size_formatted'] ); ?></span>
                        <span class="label"><?php _e( 'initial size', 'media-optimizer-stats' ); ?></span>
                    </div>
                    <div class="stat-line highlighted">
                        <span class="value" id="akic-optimized-size"><?php echo esc_html( $stats['optimized_size_formatted'] ); ?></span>
                        <span class="label"><?php _e( 'current size', 'media-optimizer-stats' ); ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="akic-progress-section">
        <div class="akic-controls">
            <button id="akic-start" class="button button-primary"><?php _e( 'Start Compression', 'media-optimizer-stats' ); ?></button>
            <button id="akic-stop" class="button button-secondary" disabled style="display: none;"><?php _e( 'Stop', 'media-optimizer-stats' ); ?></button>
            <span class="akic-spinner spinner"></span>
        </div>

        <div id="akic-progress-table-container">
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th class="column-file"><?php _e( 'File', 'media-optimizer-stats' ); ?></th>
                        <th class="column-initial-size"><?php _e( 'Initial Size', 'media-optimizer-stats' ); ?></th>
                        <th class="column-current-size"><?php _e( 'Current Size', 'media-optimizer-stats' ); ?></th>
                        <th class="column-savings"><?php _e( 'Savings', 'media-optimizer-stats' ); ?></th>
                        <th class="column-status"><?php _e( 'Status', 'media-optimizer-stats' ); ?></th>
                    </tr>
                </thead>
                <tbody id="akic-progress-log">
                </tbody>
            </table>
        </div>
    </div>
</div>
