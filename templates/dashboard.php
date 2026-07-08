<?php
/**
 * Dashboard Template
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap mosc-dashboard">
    <h1><?php _e( 'Image Compression', 'media-optimizer-stats' ); ?></h1>
    
    <div class="mosc-stats-cards">
        <div class="mosc-card">
            <h3><?php _e( 'Total Attachments', 'media-optimizer-stats' ); ?></h3>
            <div class="value" id="mosc-total-attachments"><?php echo esc_html( $stats['total_attachments'] ); ?></div>
        </div>
        <div class="mosc-card">
            <h3><?php _e( 'Total Image Files', 'media-optimizer-stats' ); ?></h3>
            <div class="value" id="mosc-total-files"><?php echo esc_html( $stats['total_files'] ); ?></div>
        </div>
        <div class="mosc-card">
            <h3><?php _e( 'Original Size', 'media-optimizer-stats' ); ?></h3>
            <div class="value" id="mosc-original-size"><?php echo esc_html( $stats['original_size_formatted'] ); ?></div>
        </div>
        <div class="mosc-card">
            <h3><?php _e( 'After compression', 'media-optimizer-stats' ); ?></h3>
            <div class="value" id="mosc-optimized-size"><?php echo esc_html( $stats['optimized_size_formatted'] ); ?></div>
        </div>
        <div class="mosc-card">
            <h3><?php _e( 'Total Saved', 'media-optimizer-stats' ); ?></h3>
            <div class="value" id="mosc-total-saved-percent"><?php echo esc_html( $stats['total_saved_percent'] ); ?>%</div>
        </div>
    </div>

    <div class="mosc-progress-section">
        <?php 
        $percent = $stats['total_attachments'] > 0 ? round( ($stats['processed_count'] / $stats['total_attachments']) * 100 ) : 0;
        ?>
        <div class="mosc-progress-text" style="display: none;">
            <?php printf( __( '%d%% (%d / %d Images Compressed)', 'media-optimizer-stats' ), $percent, $stats['processed_count'], $stats['total_attachments'] ); ?>
        </div>
        <div class="mosc-progress-bar-container" style="display: none;">
            <div class="mosc-progress-bar-fill" style="width: <?php echo esc_attr( $percent ); ?>%;"></div>
        </div>

        <div class="mosc-controls">
            <button id="mosc-start" class="button button-primary"><?php _e( 'Start Compression', 'media-optimizer-stats' ); ?></button>
            <button id="mosc-stop" class="button button-secondary" disabled style="display: none;"><?php _e( 'Stop', 'media-optimizer-stats' ); ?></button>
            <span class="mosc-spinner spinner"></span>
        </div>
    </div>
</div>
