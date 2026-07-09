<?php

class KAIC_Image_Compression
{
    public function __construct()
    {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_init', array($this, 'handle_activation_redirect'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
        add_action('wp_ajax_kaic_get_next_image', array($this, 'ajax_get_next_image'));
        add_action('wp_ajax_kaic_process_image', array($this, 'ajax_process_image'));
        add_filter('plugin_action_links_' . plugin_basename(__FILE__), array($this, 'add_plugin_action_links'));

        register_activation_hook(__FILE__, array($this, 'activate'));
    }

    /**
     * Handle Plugin Activation
     */
    public function activate()
    {
        set_transient('kaic_activation_redirect', true, 30);
    }

    /**
     * Redirect to Image Compression page on activation
     */
    public function handle_activation_redirect()
    {
        if (get_transient('kaic_activation_redirect')) {
            delete_transient('kaic_activation_redirect');

            // Do not redirect if we are already on the page or if it is a bulk activation
            if (isset($_GET['activate-multi'])) {
                return;
            }

            wp_safe_redirect(admin_url('upload.php?page=image-compression-stats'));
            exit;
        }
    }

    /**
     * Add Action Links to Plugins Page
     */
    public function add_plugin_action_links($links)
    {
        $settings_link = '<a href="options-general.php?page=image-compression-settings">' . __('Settings', 'kaic-image-compression') . '</a>';
        $compression_link = '<a href="upload.php?page=image-compression-stats">' . __('Image Compression', 'kaic-image-compression') . '</a>';

        array_unshift($links, $compression_link);
        array_unshift($links, $settings_link);

        return $links;
    }

    /**
     * Register Settings
     */
    public function register_settings()
    {
        register_setting('kaic_settings_group', 'kaic_max_dimension', array(
                'type' => 'integer',
                'sanitize_callback' => 'absint',
                'default' => 2048,
        ));
        register_setting('kaic_settings_group', 'kaic_quality', array(
                'type' => 'integer',
                'sanitize_callback' => 'absint',
                'default' => 70,
        ));
    }

    /**
     * Add Submenu under Media and Settings
     */
    public function add_admin_menu()
    {
        add_media_page(
                __('Bulk Compression', 'kaic-image-compression'),
                __('Bulk Compression', 'kaic-image-compression'),
                'manage_options',
                'kaic-image-compression-bulk',
                array($this, 'render_dashboard')
        );

        add_options_page(
                __('Image Compression', 'kaic-image-compression'),
                __('Image Compression', 'kaic-image-compression'),
                'manage_options',
                'kaic-image-compression-settings',
                array($this, 'render_settings_page')
        );
    }

    /**
     * Enqueue Admin Assets
     */
    public function enqueue_assets($hook)
    {
        if ('media_page_kaic-image-compression-bulk' !== $hook) {
            return;
        }

        wp_enqueue_style('kaic-admin-css', KAIC_PLUGIN_DIR_URL . 'assets/css/admin.css', array('dashicons'), '1.0.0');
        wp_enqueue_script('kaic-admin-js', KAIC_PLUGIN_DIR_URL . 'assets/js/admin.js', array('jquery'), '1.0.0', true);

        wp_localize_script('kaic-admin-js', 'kaic_vars', array(
                'nonce' => wp_create_nonce('kaic_nonce')
        ));
    }

    /**
     * Render Dashboard HTML
     */
    public function render_dashboard()
    {
        $stats = $this->get_stats();
        include KAIC_PLUGIN_DIR_PATH . 'templates/dashboard.php';
    }

    /**
     * Render Settings Page HTML
     */
    public function render_settings_page()
    {
        ?>
        <div class="wrap">
            <h1><?php _e('Image Compression Settings', 'kaic-image-compression'); ?></h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('kaic_settings_group');
                do_settings_sections('kaic_settings_group');
                ?>
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row"><?php _e('Max Dimension', 'kaic-image-compression'); ?></th>
                        <td>
                            <input type="number" name="kaic_max_dimension" value="<?php echo esc_attr(get_option('kaic_max_dimension', 2048)); ?>"/>
                            <p class="description"><?php _e('The maximum width or height for compressed images.', 'kaic-image-compression'); ?></p>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row"><?php _e('Quality', 'kaic-image-compression'); ?></th>
                        <td>
                            <input type="number" name="kaic_quality" value="<?php echo esc_attr(get_option('kaic_quality', 70)); ?>" min="1" max="100"/>
                            <p class="description"><?php _e('The quality level for compressed images (1-100).', 'kaic-image-compression'); ?></p>
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
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
        $processed_count = (int)$wpdb->get_var("SELECT COUNT(post_id) FROM $wpdb->postmeta WHERE meta_key = '_kaic_is_compressed' AND meta_value = '1'");

        // Original size sum
        $original_size = (float)$wpdb->get_var("SELECT SUM(meta_value) FROM $wpdb->postmeta WHERE meta_key = '_kaic_original_file_size'");

        // For images not yet processed, we should count their current size as original for the stats?
        // Actually, the requirement says \"Total original size of all images combined\".
        // This is tricky if not all are processed yet. Let's calculate for all images.

        $total_original_size = 0;
        $total_compressed_size = 0;
        $total_files_count = 0;

        $attachments = get_posts(array(
                'post_type' => 'attachment',
                'post_mime_type' => 'image',
                'posts_per_page' => -1,
                'fields' => 'ids',
        ));

        foreach ($attachments as $id) {
            $is_compressed = get_post_meta($id, '_kaic_is_compressed', true);
            $orig = (float)get_post_meta($id, '_kaic_original_file_size', true);
            $opt = (float)get_post_meta($id, '_kaic_compressed_file_size', true);

            $meta = wp_get_attachment_metadata($id);
            $count = 1; // Main file
            if (!empty($meta['sizes'])) {
                $count += count($meta['sizes']);
            }
            $total_files_count += $count;

            if ($is_compressed) {
                $total_original_size += (float)$orig;
                $total_compressed_size += (float)$opt;
            } else {
                $size = $this->get_attachment_total_size($id);
                if (is_wp_error($size)) {
                    $size = 0; // Fallback for overall stats to avoid breaking, but maybe log it?
                }
                $total_original_size += (int)$size;
                $total_compressed_size += (int)$size; // Not compressed yet
            }
        }

        return array(
                'total_attachments' => $total_attachments,
                'processed_count' => $processed_count,
                'total_files' => $total_files_count,
                'original_size' => $total_original_size,
                'compressed_size' => $total_compressed_size,
                'original_size_formatted' => size_format((float)$total_original_size, 2),
                'compressed_size_formatted' => size_format((float)$total_compressed_size, 2),
                'total_saved_percent' => $total_original_size > 0 ? round((($total_original_size - $total_compressed_size) / $total_original_size) * 100, 1) : 0,
                'processed_percent' => $total_attachments > 0 ? round(($processed_count / $total_attachments) * 100, 1) : 0,
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
            return new WP_Error('file_not_found', __('Attachment file path not found.', 'kaic-image-compression'));
        }

        if (!file_exists($file)) {
            return new WP_Error('file_not_found', sprintf(__('File does not exist: %s', 'kaic-image-compression'), basename($file)));
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
     * AJAX Handler: Get next image data without processing
     */
    public function ajax_get_next_image()
    {
        check_ajax_referer('kaic_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        global $wpdb;
        $results = $wpdb->get_results("
			SELECT p.ID 
			FROM $wpdb->posts p 
			LEFT JOIN $wpdb->postmeta pm ON p.ID = pm.post_id AND pm.meta_key = '_kaic_is_compressed' 
			WHERE p.post_type = 'attachment' 
			AND p.post_mime_type LIKE 'image/%' 
			AND (pm.meta_value IS NULL OR pm.meta_value = '0')
			ORDER BY p.ID ASC
			LIMIT 1
		");

        if (empty($results)) {
            wp_send_json_success(array('finished' => true));
        }

        $attachment_id = $results[0]->ID;
        $original_size = $this->get_attachment_total_size($attachment_id);
        $thumb_url = wp_get_attachment_image_url($attachment_id, 'thumbnail');
        $filename = basename(get_attached_file($attachment_id));

        wp_send_json_success(array(
                'image' => array(
                        'id' => $attachment_id,
                        'filename' => $filename,
                        'thumb' => $thumb_url,
                        'original_size' => is_wp_error($original_size) ? 'Error' : size_format($original_size),
                ),
                'finished' => false
        ));
    }

    /**
     * AJAX Handler: Process specific image
     */
    public function ajax_process_image()
    {
        check_ajax_referer('kaic_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        $attachment_id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        if (!$attachment_id) {
            wp_send_json_error(array(
                'message' => 'Invalid ID',
                'stats' => $this->get_stats()
            ));
        }

        // 1. Save original size
        $original_size = $this->get_attachment_total_size($attachment_id);
        $error = null;

        if (is_wp_error($original_size)) {
            $error = $original_size->get_error_message();
        } else {
            update_post_meta($attachment_id, '_kaic_original_file_size', (int)$original_size);

            // 2. Simulate Compression
            $result = $this->simulate_media_compression($attachment_id);
            if (is_wp_error($result)) {
                $error = $result->get_error_message();
            } else {
                // 3. Save compressed size
                $compressed_size = $this->get_attachment_total_size($attachment_id);
                if (is_wp_error($compressed_size)) {
                    $error = $compressed_size->get_error_message();
                } else {
                    update_post_meta($attachment_id, '_kaic_compressed_file_size', (int)$compressed_size);
                }
            }
        }

        // Always mark as compressed (or failed) to prevent infinite loops
        update_post_meta($attachment_id, '_kaic_is_compressed', '1');
        if ($error) {
            update_post_meta($attachment_id, '_kaic_compression_error', $error);
        }

        $compressed_size = isset($compressed_size) && !is_wp_error($compressed_size) ? $compressed_size : $original_size;

        wp_send_json_success(array(
                'stats' => $this->get_stats(),
                'image' => array(
                        'id' => $attachment_id,
                        'optimized_size' => size_format($compressed_size),
                        'saved_percent' => $original_size > 0 ? round((($original_size - $compressed_size) / $original_size) * 100, 2) : 0,
                        'error' => $error
                )
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
            return new WP_Error('file_not_found', __('Attachment file path not found.', 'kaic-image-compression'));
        }

        if (!file_exists($file)) {
            return new WP_Error('file_not_found', sprintf(__('File does not exist: %s', 'kaic-image-compression'), basename($file)));
        }

        $max_dimension = get_option('kaic_max_dimension', 2048);
        $quality = get_option('kaic_quality', 70);

        $editor = wp_get_image_editor($file);
        if (!is_wp_error($editor)) {
            // Ensure it does not exceed max dimension in width or height
            $size = $editor->get_size();
            if ($size['width'] > $max_dimension || $size['height'] > $max_dimension) {
                $editor->resize($max_dimension, $max_dimension, false);
            }

            $editor->set_quality($quality); // Reduce quality
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
                        // Thumbnails are usually smaller than max dimension, but we apply the same logic for safety
                        $thumb_size = $thumb_editor->get_size();
                        if ($thumb_size['width'] > $max_dimension || $thumb_size['height'] > $max_dimension) {
                            $thumb_editor->resize($max_dimension, $max_dimension, false);
                        }

                        $thumb_editor->set_quality($quality);
                        $thumb_editor->save($thumb_path);
                    }
                }
            }
        }
    }
}