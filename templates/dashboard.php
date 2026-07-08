<?php
/**
 * Dashboard Template
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap kaic-dashboard">
    <h1><?php _e( 'Image Compression', 'media-optimizer-stats' ); ?></h1>
    
    <div class="kaic-stats-cards">
        <div class="kaic-card">
            <h3><?php _e( 'Total Attachments', 'media-optimizer-stats' ); ?></h3>
            <div class="value" id="kaic-total-attachments"><?php echo esc_html( $stats['total_attachments'] ); ?></div>
        </div>
        <div class="kaic-card">
            <h3><?php _e( 'Total Image Files', 'media-optimizer-stats' ); ?></h3>
            <div class="value" id="kaic-total-files"><?php echo esc_html( $stats['total_files'] ); ?></div>
        </div>
        <div class="kaic-card">
            <h3><?php _e( 'Original Size', 'media-optimizer-stats' ); ?></h3>
            <div class="value" id="kaic-original-size"><?php echo esc_html( $stats['original_size_formatted'] ); ?></div>
        </div>
        <div class="kaic-card">
            <h3><?php _e( 'After compression', 'media-optimizer-stats' ); ?></h3>
            <div class="value" id="kaic-optimized-size"><?php echo esc_html( $stats['optimized_size_formatted'] ); ?></div>
        </div>
        <div class="kaic-card">
            <h3><?php _e( 'Total Saved', 'media-optimizer-stats' ); ?></h3>
            <div class="value" id="kaic-total-saved-percent"><?php echo esc_html( $stats['total_saved_percent'] ); ?>%</div>
        </div>
    </div>

    <div class="kaic-progress-section">
        <?php 
        $percent = $stats['total_attachments'] > 0 ? round( ($stats['processed_count'] / $stats['total_attachments']) * 100 ) : 0;
        ?>
        <div class="kaic-progress-text" style="display: none;">
            <?php printf( __( '%d%% (%d / %d Images Compressed)', 'media-optimizer-stats' ), $percent, $stats['processed_count'], $stats['total_attachments'] ); ?>
        </div>
        <div class="kaic-progress-bar-container" style="display: none;">
            <div class="kaic-progress-bar-fill" style="width: <?php echo esc_attr( $percent ); ?>%;"></div>
        </div>

        <div class="kaic-controls">
            <button id="kaic-start" class="button button-primary"><?php _e( 'Start Compression', 'media-optimizer-stats' ); ?></button>
            <button id="kaic-stop" class="button button-secondary" disabled style="display: none;"><?php _e( 'Stop', 'media-optimizer-stats' ); ?></button>
            <span class="kaic-spinner spinner"></span>
        </div>

        <div id="kaic-progress-table-container" style="display: none; margin-top: 20px;">
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th style="width: 50px;"><?php _e( 'Thumb', 'media-optimizer-stats' ); ?></th>
                        <th><?php _e( 'File', 'media-optimizer-stats' ); ?></th>
                        <th><?php _e( 'Original Size', 'media-optimizer-stats' ); ?></th>
                        <th><?php _e( 'Optimized Size', 'media-optimizer-stats' ); ?></th>
                        <th><?php _e( 'Savings', 'media-optimizer-stats' ); ?></th>
                        <th style="width: 100px;"><?php _e( 'Status', 'media-optimizer-stats' ); ?></th>
                    </tr>
                </thead>
                <tbody id="kaic-progress-log">
                </tbody>
            </table>
        </div>
    </div>
</div>
