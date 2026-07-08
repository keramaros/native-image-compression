<?php
/**
 * Plugin Name: Image Compression
 * Description: Adds a media compression dashboard with statistics, a progress bar, and asynchronous AJAX compression.
 * Version: 1.0.0
 * Author: Keramaros Antonios
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Main Plugin Class
 */
class Media_Compression_Stats
{

    public function __construct()
    {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
        add_action('wp_ajax_mosc_process_image', array($this, 'ajax_process_image'));
    }

    /**
     * Add Submenu under Media
     */
    public function add_admin_menu()
    {
        add_media_page(
            __('Image Compression', 'media-compression-stats'),
            __('Image Compression', 'media-compression-stats'),
            'manage_options',
            'image-compression-stats',
            array($this, 'render_dashboard')
        );
    }

    /**
     * Enqueue Admin Assets
     */
    public function enqueue_assets($hook)
    {
        if ('media_page_image-compression-stats' !== $hook) {
            return;
        }

        wp_enqueue_style('mosc-admin-css', plugin_dir_url(__FILE__) . 'assets/css/admin.css', array(), '1.0.0');
        wp_enqueue_script('mosc-admin-js', plugin_dir_url(__FILE__) . 'assets/js/admin.js', array('jquery'), '1.0.0', true);

        wp_localize_script('mosc-admin-js', 'mosc_vars', array(
            'nonce' => wp_create_nonce('mosc_nonce')
        ));
    }

    /**
     * Render Dashboard HTML
     */
    public function render_dashboard()
    {
        $stats = $this->get_stats();
        include plugin_dir_path(__FILE__) . 'templates/dashboard.php';
    }

    /**
     * Get Overall Stats
     */
    private function get_stats()
    {
        global $wpdb;

        // Total attachments (Images)
        $total_attachments = (int)$wpdb->get_var("SELECT COUNT(ID) FROM $wpdb->posts WHERE post_type = 'attachment' AND post_mime_type LIKE 'image/%'");

        // Processed count
        $processed_count = (int)$wpdb->get_var("SELECT COUNT(post_id) FROM $wpdb->postmeta WHERE meta_key = '_is_compressed' AND meta_value = '1'");

        // Original size sum
        $original_size = (float)$wpdb->get_var("SELECT SUM(meta_value) FROM $wpdb->postmeta WHERE meta_key = '_original_file_size'");

        // For images not yet processed, we should count their current size as original for the stats?
        // Actually, the requirement says \"Total original size of all images combined\".
        // This is tricky if not all are processed yet. Let's calculate for all images.

        $total_original_size = 0;
        $total_optimized_size = 0;
        $total_files_count = 0;

        $attachments = get_posts(array(
            'post_type' => 'attachment',
            'post_mime_type' => 'image/',
            'posts_per_page' => -1,
            'fields' => 'ids',
        ));

        foreach ($attachments as $id) {
            $is_compressed = get_post_meta($id, '_is_compressed', true);
            $orig = (float)get_post_meta($id, '_original_file_size', true);
            $opt = (float)get_post_meta($id, '_optimized_file_size', true);

            $meta = wp_get_attachment_metadata($id);
            $count = 1; // Main file
            if (!empty($meta['sizes'])) {
                $count += count($meta['sizes']);
            }
            $total_files_count += $count;

            if ($is_compressed) {
                $total_original_size += (float)$orig;
                $total_optimized_size += (float)$opt;
            } else {
                $size = $this->get_attachment_total_size($id);
                if (is_wp_error($size)) {
                    $size = 0; // Fallback for overall stats to avoid breaking, but maybe log it?
                }
                $total_original_size += (int)$size;
                $total_optimized_size += (int)$size; // Not optimized yet
            }
        }

        return array(
            'total_attachments' => $total_attachments,
            'processed_count' => $processed_count,
            'total_files' => $total_files_count,
            'original_size' => $total_original_size,
            'optimized_size' => $total_optimized_size,
            'original_size_formatted' => size_format((float)$total_original_size, 2),
            'optimized_size_formatted' => size_format((float)$total_optimized_size, 2),
            'total_saved_percent' => $total_original_size > 0 ? round((($total_original_size - $total_optimized_size) / $total_original_size) * 100, 2) : 0,
        );
    }

    /**
     * Helper: Get total size of an attachment (original + thumbnails)
     *
     * @return int|WP_Error
     */
    private function get_attachment_total_size($attachment_id)
    {
        $file = get_attached_file($attachment_id);

        if (!$file) {
            return new WP_Error('file_not_found', __('Attachment file path not found.', 'media-compression-stats'));
        }

        if (!file_exists($file)) {
            return new WP_Error('file_not_found', sprintf(__('File does not exist: %s', 'media-compression-stats'), basename($file)));
        }

        $size = 0;

        // Clear stat cache for the original file specifically
        clearstatcache(false, $file);
        $size += (int)filesize($file);

        $meta = wp_get_attachment_metadata($attachment_id);
        if (!empty($meta['sizes'])) {
            $dirname = dirname($file);
            foreach ($meta['sizes'] as $thumb) {
                if (empty($thumb['file'])) {
                    continue;
                }

                $thumb_path = $dirname . '/' . $thumb['file'];

                // Clear stat cache for the specific thumbnail path
                clearstatcache(false, $thumb_path);

                if (file_exists($thumb_path)) {
                    $size += (int)filesize($thumb_path);
                }
            }
        }

        return (int)$size;
    }

    /**
     * AJAX Handler: Process next image
     */
    public function ajax_process_image()
    {
        check_ajax_referer('mosc_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        global $wpdb;
        // Find next uncompressed image
        $results = $wpdb->get_results("
			SELECT p.ID 
			FROM $wpdb->posts p 
			LEFT JOIN $wpdb->postmeta pm ON p.ID = pm.post_id AND pm.meta_key = '_is_compressed' 
			WHERE p.post_type = 'attachment' 
			AND p.post_mime_type LIKE 'image/%' 
			AND (pm.meta_value IS NULL OR pm.meta_value = '0')
			ORDER BY p.ID ASC
			LIMIT 2
		");

        if (empty($results)) {
            wp_send_json_success(array('finished' => true));
        }

        $attachment_id = $results[0]->ID;

        // 1. Save original size
        $original_size = $this->get_attachment_total_size($attachment_id);
        $error = null;

        if (is_wp_error($original_size)) {
            $error = $original_size->get_error_message();
        } else {
            update_post_meta($attachment_id, '_original_file_size', (int)$original_size);

            // 2. Simulate Compression
            $result = $this->simulate_media_compression($attachment_id);
            if (is_wp_error($result)) {
                $error = $result->get_error_message();
            } else {
                // 3. Save optimized size
                $optimized_size = $this->get_attachment_total_size($attachment_id);
                if (is_wp_error($optimized_size)) {
                    $error = $optimized_size->get_error_message();
                } else {
                    update_post_meta($attachment_id, '_optimized_file_size', (int)$optimized_size);
                }
            }
        }

        // Always mark as compressed (or failed) to prevent infinite loops
        update_post_meta($attachment_id, '_is_compressed', '1');
        if ($error) {
            update_post_meta($attachment_id, '_compression_error', $error);
        }

        $optimized_size = isset($optimized_size) && !is_wp_error($optimized_size) ? $optimized_size : $original_size;

        wp_send_json_success(array(
            'stats' => $this->get_stats(),
            'finished' => false
        ));
    }

    /**
     * Compression Engine Placeholder
     * Simulates reduction by 10-30% using standard WP image editors if possible,
     * or just meta tracking for simulation purposes.
     */
    private function simulate_media_compression($attachment_id)
    {
        // In a real scenario, we'd use WP_Image_Editor here.
        // For simulation, we'll just simulate the fact that it was "processed".
        // To make it more realistic, we could actually re-save the image with lower quality.

        $file = get_attached_file($attachment_id);
        if (!$file) {
            return new WP_Error('file_not_found', __('Attachment file path not found.', 'media-compression-stats'));
        }

        if (!file_exists($file)) {
            return new WP_Error('file_not_found', sprintf(__('File does not exist: %s', 'media-compression-stats'), basename($file)));
        }

        $max_dimension = 2048;

        $editor = wp_get_image_editor($file);
        if (!is_wp_error($editor)) {
            // Ensure it does not exceed 2048 in width or height
            $size = $editor->get_size();
            if ($size['width'] > $max_dimension || $size['height'] > $max_dimension) {
                $editor->resize($max_dimension, $max_dimension, false);
            }

            $editor->set_quality(70); // Reduce quality to 70
            $editor->save($file);
        }

        // Also "compress" thumbnails
        $meta = wp_get_attachment_metadata($attachment_id);
        if (!empty($meta['sizes'])) {
            $dirname = dirname($file);
            foreach ($meta['sizes'] as $thumb) {
                $thumb_path = $dirname . '/' . $thumb['file'];
                if (file_exists($thumb_path)) {
                    $thumb_editor = wp_get_image_editor($thumb_path);
                    if (!is_wp_error($thumb_editor)) {
                        // Thumbnails are usually smaller than 2048, but we apply the same logic for safety
                        $thumb_size = $thumb_editor->get_size();
                        if ($thumb_size['width'] > $max_dimension || $thumb_size['height'] > $max_dimension) {
                            $thumb_editor->resize($max_dimension, $max_dimension, false);
                        }

                        $thumb_editor->set_quality(70);
                        $thumb_editor->save($thumb_path);
                    }
                }
            }
        }
    }
}

new Media_Compression_Stats();