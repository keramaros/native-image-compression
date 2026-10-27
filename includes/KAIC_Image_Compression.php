<?php

if (!defined('ABSPATH')) {
    exit;
}

class KAIC_Image_Compression
{
    public function __construct()
    {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_init', array($this, 'handle_activation_redirect'));
        add_action('admin_init', array($this, 'handle_clear_log'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
        add_action('wp_ajax_kaic_get_next_image', array($this, 'ajax_get_next_image'));
        add_action('wp_ajax_kaic_process_image', array($this, 'ajax_process_image'));

        $plugin_file = defined('KAIC_PLUGIN_FILE') ? KAIC_PLUGIN_FILE : dirname(__DIR__) . '/native-image-compression.php';
        add_filter('plugin_action_links_' . plugin_basename($plugin_file), array($this, 'add_plugin_action_links'));
        register_activation_hook($plugin_file, array($this, 'activate'));
        register_shutdown_function(array($this, 'handle_shutdown'));
    }

    /**
     * Handle shutdown to log fatal errors
     */
    public function handle_shutdown()
    {
        $error = error_get_last();
        if ($error && in_array($error['type'], array(E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR, E_RECOVERABLE_ERROR), true)) {
            $this->log_error(sprintf('PHP Fatal Error: %s in %s on line %d', $error['message'], $error['file'], $error['line']));
        }
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

            wp_safe_redirect(admin_url('upload.php?page=native-image-compression-bulk'));
            exit;
        }
    }

    /**
     * Add Action Links to Plugins Page
     */
    public function add_plugin_action_links($links)
    {
        $settings_link = '<a href="' . esc_url(admin_url('options-general.php?page=native-image-compression-settings')) . '">' . esc_html__('Settings', 'native-image-compression') . '</a>';
        $compression_link = '<a href="' . esc_url(admin_url('upload.php?page=native-image-compression-bulk')) . '">' . esc_html__('Bulk Compression', 'native-image-compression') . '</a>';

        array_unshift($links, $compression_link);
        array_unshift($links, $settings_link);

        return $links;
    }

    /**
     * Handle clearing the error log
     */
    public function handle_clear_log()
    {
        if (isset($_GET['page'], $_GET['action']) && sanitize_text_field(wp_unslash($_GET['page'])) === 'native-image-compression-settings' && sanitize_text_field(wp_unslash($_GET['action'])) === 'kaic_clear_log') {
            if (!current_user_can('manage_options')) {
                wp_die(esc_html__('Unauthorized user', 'native-image-compression'));
            }

            check_admin_referer('kaic_clear_log_nonce');

            $this->clear_log();

            wp_safe_redirect(add_query_arg(array('page' => 'native-image-compression-settings', 'log_cleared' => '1'), admin_url('options-general.php')));
            exit;
        }
    }

    /**
     * Clear the error log file
     */
    public function clear_log()
    {
        $log_path = $this->get_log_file_path();
        if (file_exists($log_path)) {
            @file_put_contents($log_path, '');
        }
    }

    /**
     * Get path to the error log file
     *
     * @return string
     */
    public function get_log_file_path()
    {
        $upload_dir = wp_upload_dir();
        return trailingslashit($upload_dir['basedir']) . 'kaic-error.log';
    }

    /**
     * Get URL to the error log file
     *
     * @return string
     */
    public function get_log_file_url()
    {
        $upload_dir = wp_upload_dir();
        return trailingslashit($upload_dir['baseurl']) . 'kaic-error.log';
    }

    /**
     * Log an error message to the error log file
     *
     * @param string $message
     */
    public function log_error($message)
    {
        $log_file = $this->get_log_file_path();
        $dir = dirname($log_file);

        if (!file_exists($dir)) {
            wp_mkdir_p($dir);
        }

        $formatted_message = sprintf("[%s] %s\n", current_time('mysql'), $message);
        @file_put_contents($log_file, $formatted_message, FILE_APPEND | LOCK_EX);
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
                __('Bulk Compression', 'native-image-compression'),
                __('Bulk Compression', 'native-image-compression'),
                'manage_options',
                'native-image-compression-bulk',
                array($this, 'render_dashboard')
        );

        add_options_page(
                __('Native Image Compression', 'native-image-compression'),
                __('Native Image Compression', 'native-image-compression'),
                'manage_options',
                'native-image-compression-settings',
                array($this, 'render_settings_page')
        );
    }

    /**
     * Enqueue Admin Assets
     */
    public function enqueue_assets($hook)
    {
        if ('media_page_native-image-compression-bulk' !== $hook) {
            return;
        }

        wp_enqueue_style('kaic-admin-css', KAIC_PLUGIN_DIR_URL . 'assets/css/admin.css', array('dashicons'), '1.0.2');
        wp_enqueue_script('kaic-admin-js', KAIC_PLUGIN_DIR_URL . 'assets/js/admin.js', array('jquery'), '1.0.2', true);

        wp_localize_script('kaic-admin-js', 'kaic_vars', array(
                'nonce' => wp_create_nonce('kaic_nonce')
        ));
    }

    /**
     * Render Dashboard HTML
     */
    public function render_dashboard()
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        $stats = $this->get_stats();
        include KAIC_PLUGIN_DIR_PATH . 'templates/dashboard.php';
    }

    /**
     * Render Settings Page HTML
     */
    public function render_settings_page()
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        $log_path = $this->get_log_file_path();
        $log_url = $this->get_log_file_url();
        $log_exists = file_exists($log_path) && filesize($log_path) > 0;
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <?php if (isset($_GET['log_cleared']) && sanitize_text_field(wp_unslash($_GET['log_cleared'])) === '1') : ?>
                <div class="notice notice-success is-dismissible">
                    <p><?php esc_html_e('Error log cleared successfully.', 'native-image-compression'); ?></p>
                </div>
            <?php endif; ?>
            <form method="post" action="options.php">
                <?php
                settings_fields('kaic_settings_group');
                do_settings_sections('kaic_settings_group');
                ?>
                <table class="form-table" role="presentation">
                    <tr valign="top">
                        <th scope="row"><?php esc_html_e('Max Dimension', 'native-image-compression'); ?></th>
                        <td>
                            <input type="number" name="kaic_max_dimension" value="<?php echo esc_attr(get_option('kaic_max_dimension', 2048)); ?>"/>
                            <p class="description"><?php esc_html_e('The maximum width or height for compressed images.', 'native-image-compression'); ?></p>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row"><?php esc_html_e('Quality', 'native-image-compression'); ?></th>
                        <td>
                            <input type="number" name="kaic_quality" value="<?php echo esc_attr(get_option('kaic_quality', 70)); ?>" min="1" max="100"/>
                            <p class="description"><?php esc_html_e('The quality level for compressed images (1-100).', 'native-image-compression'); ?></p>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row"><?php esc_html_e('Error Log', 'native-image-compression'); ?></th>
                        <td>
                            <p>
                                <a href="<?php echo esc_url($log_url); ?>" target="_blank" class="button button-secondary<?php echo !$log_exists ? ' disabled' : ''; ?>"<?php echo !$log_exists ? ' aria-disabled="true"' : ''; ?>>
                                    <?php esc_html_e('View Error Log', 'native-image-compression'); ?>
                                </a>
                                <a href="<?php echo esc_url(wp_nonce_url(admin_url('options-general.php?page=native-image-compression-settings&action=kaic_clear_log'), 'kaic_clear_log_nonce')); ?>" class="button button-secondary<?php echo !$log_exists ? ' disabled' : ''; ?>"<?php echo !$log_exists ? ' aria-disabled="true"' : ''; ?> onclick="<?php echo $log_exists ? 'return confirm(\'' . esc_js(__('Are you sure you want to clear the error log?', 'native-image-compression')) . '\');' : 'return false;'; ?>">
                                    <?php esc_html_e('Clear Log', 'native-image-compression'); ?>
                                </a>
                                <?php if ($log_exists) : ?>
                                    <span class="description" style="margin-left: 8px;">
                                        <?php
                                        /* translators: %s: Formatted file size. */
                                        printf(esc_html__('Log file size: %s', 'native-image-compression'), esc_html(size_format(filesize($log_path))));
                                        ?>
                                    </span>
                                <?php else : ?>
                                    <span class="description" style="margin-left: 8px;">
                                        <?php esc_html_e('No errors logged yet.', 'native-image-compression'); ?>
                                    </span>
                                <?php endif; ?>
                            </p>
                            <p class="description"><?php esc_html_e('View or clear the error log file.', 'native-image-compression'); ?></p>
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
            return new WP_Error('file_not_found', __('Attachment file path not found.', 'native-image-compression'));
        }

        if (!file_exists($file)) {
            /* translators: %s: File name. */
            return new WP_Error('file_not_found', sprintf(__('File does not exist: %s', 'native-image-compression'), basename($file)));
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
        try {
            check_ajax_referer('kaic_nonce', 'nonce');

            if (!current_user_can('manage_options')) {
                wp_send_json_error(array('message' => __('Unauthorized user', 'native-image-compression')));
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

            $attachment_id = (int)$results[0]->ID;
            $original_size = $this->get_attachment_total_size($attachment_id);
            $thumb_url = wp_get_attachment_image_url($attachment_id, 'thumbnail');
            $filename = basename(get_attached_file($attachment_id) ?: "attachment_{$attachment_id}");

            wp_send_json_success(array(
                    'image' => array(
                            'id' => $attachment_id,
                            'filename' => $filename,
                            'thumb' => $thumb_url ? $thumb_url : '',
                            'original_size' => is_wp_error($original_size) ? 'Error' : size_format($original_size),
                    ),
                    'finished' => false
            ));
        } catch (\Throwable $e) {
            $this->log_error(sprintf('Error in ajax_get_next_image: %s in %s on line %d', $e->getMessage(), $e->getFile(), $e->getLine()));
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }

    /**
     * AJAX Handler: Process specific image
     */
    public function ajax_process_image()
    {
        $attachment_id = 0;
        try {
            check_ajax_referer('kaic_nonce', 'nonce');

            if (!current_user_can('manage_options')) {
                wp_send_json_error(array('message' => __('Unauthorized user', 'native-image-compression')));
            }

            $attachment_id = isset($_POST['id']) ? absint($_POST['id']) : 0;
            if (!$attachment_id) {
                $this->log_error('Invalid attachment ID provided in ajax_process_image.');
                wp_send_json_error(array(
                    'message' => __('Invalid attachment ID', 'native-image-compression'),
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
                $filename = basename(get_attached_file($attachment_id) ?: "attachment_{$attachment_id}");
                $this->log_error(sprintf('Error processing attachment ID %d (%s): %s', $attachment_id, $filename, $error));
            }

            $orig_size_num = is_wp_error($original_size) ? 0 : (int)$original_size;
            $comp_size_num = (isset($compressed_size) && !is_wp_error($compressed_size)) ? (int)$compressed_size : $orig_size_num;
            $optimized_size_display = $comp_size_num > 0 ? size_format($comp_size_num) : '-';
            $saved_percent = ($orig_size_num > 0 && $orig_size_num >= $comp_size_num) ? round((($orig_size_num - $comp_size_num) / $orig_size_num) * 100, 2) : 0;

            wp_send_json_success(array(
                    'stats' => $this->get_stats(),
                    'image' => array(
                            'id' => $attachment_id,
                            'optimized_size' => $optimized_size_display,
                            'saved_percent' => $saved_percent,
                            'error' => $error
                    )
            ));
        } catch (\Throwable $e) {
            $filename = $attachment_id ? basename(get_attached_file($attachment_id) ?: "attachment_{$attachment_id}") : 'unknown';
            $error_message = sprintf('Critical error processing attachment ID %d (%s): %s in %s on line %d', $attachment_id, $filename, $e->getMessage(), $e->getFile(), $e->getLine());
            $this->log_error($error_message);

            if ($attachment_id) {
                update_post_meta($attachment_id, '_kaic_is_compressed', '1');
                update_post_meta($attachment_id, '_kaic_compression_error', $e->getMessage());
            }

            wp_send_json_error(array(
                'message' => $e->getMessage(),
                'stats' => $this->get_stats(),
                'image' => array(
                    'id' => $attachment_id,
                    'optimized_size' => '-',
                    'saved_percent' => 0,
                    'error' => $e->getMessage()
                )
            ));
        }
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
            return new WP_Error('file_not_found', __('Attachment file path not found.', 'native-image-compression'));
        }

        if (!file_exists($file)) {
            /* translators: %s: File name. */
            return new WP_Error('file_not_found', sprintf(__('File does not exist: %s', 'native-image-compression'), basename($file)));
        }

        $max_dimension = get_option('kaic_max_dimension', 2048);
        $quality = get_option('kaic_quality', 70);

        $editor = wp_get_image_editor($file);
        if (is_wp_error($editor)) {
            return $editor;
        }

        // Ensure it does not exceed max dimension in width or height
        $size = $editor->get_size();
        if (is_array($size) && isset($size['width'], $size['height'])) {
            if ($size['width'] > $max_dimension || $size['height'] > $max_dimension) {
                $resize_result = $editor->resize($max_dimension, $max_dimension, false);
                if (is_wp_error($resize_result)) {
                    $this->log_error(sprintf('Failed to resize attachment ID %d (%s): %s', $attachment_id, basename($file), $resize_result->get_error_message()));
                }
            }
        }

        $editor->set_quality($quality); // Reduce quality
        $save_result = $this->save_editor_if_smaller($editor, $file);
        if (is_wp_error($save_result)) {
            $this->log_error(sprintf('Failed to save compressed image for attachment ID %d (%s): %s', $attachment_id, basename($file), $save_result->get_error_message()));
            return $save_result;
        }

        // Also "compress" thumbnails
        $meta = wp_get_attachment_metadata($attachment_id);
        if (!empty($meta['sizes']) && is_array($meta['sizes'])) {
            $dirname = dirname($file);
            foreach ($meta['sizes'] as $thumb) {
                if (empty($thumb['file'])) {
                    continue;
                }
                $thumb_path = $dirname . '/' . $thumb['file'];
                if (file_exists($thumb_path)) {
                    $thumb_editor = wp_get_image_editor($thumb_path);
                    if (!is_wp_error($thumb_editor)) {
                        // Thumbnails are usually smaller than max dimension, but we apply the same logic for safety
                        $thumb_size = $thumb_editor->get_size();
                        if (is_array($thumb_size) && isset($thumb_size['width'], $thumb_size['height'])) {
                            if ($thumb_size['width'] > $max_dimension || $thumb_size['height'] > $max_dimension) {
                                $thumb_editor->resize($max_dimension, $max_dimension, false);
                            }
                        }

                        $thumb_editor->set_quality($quality);
                        $thumb_save = $this->save_editor_if_smaller($thumb_editor, $thumb_path);
                        if (is_wp_error($thumb_save)) {
                            $this->log_error(sprintf('Failed to save thumbnail for attachment ID %d (%s): %s', $attachment_id, basename($thumb_path), $thumb_save->get_error_message()));
                        }
                    } else {
                        $this->log_error(sprintf('Thumbnail editor error for attachment ID %d (%s): %s', $attachment_id, basename($thumb_path), $thumb_editor->get_error_message()));
                    }
                }
            }
        }

        return true;
    }

    private function save_editor_if_smaller($editor, $file)
    {
        $original_size = filesize($file);
        if ($original_size === false) {
            /* translators: %s: File name. */
            return new WP_Error('filesize_failed', sprintf(__('Failed to read file size: %s', 'native-image-compression'), basename($file)));
        }

        $temp_file = tempnam(dirname($file), 'kaic-');
        if ($temp_file === false) {
            return new WP_Error('tempnam_failed', __('Failed to create temporary file.', 'native-image-compression'));
        }

        $extension = pathinfo($file, PATHINFO_EXTENSION);
        if ($extension !== '') {
            $temp_file_with_extension = $temp_file . '.' . $extension;
            global $wp_filesystem;
            if (empty($wp_filesystem)) {
                require_once ABSPATH . '/wp-admin/includes/file.php';
                WP_Filesystem();
            }

            $moved = false;
            if ($wp_filesystem) {
                $moved = $wp_filesystem->move($temp_file, $temp_file_with_extension, true);
            }

            if (!$moved) {
                wp_delete_file($temp_file);
                return new WP_Error('rename_failed', __('Failed to rename temporary file.', 'native-image-compression'));
            }
            $temp_file = $temp_file_with_extension;
        }

        $result = $editor->save($temp_file);
        if (is_wp_error($result)) {
            if (file_exists($temp_file)) {
                wp_delete_file($temp_file);
            }
            return $result;
        }

        $compressed_file = isset($result['path']) ? $result['path'] : $temp_file;
        if (file_exists($compressed_file)) {
            $compressed_size = filesize($compressed_file);
            if ($compressed_size !== false && $compressed_size < $original_size) {
                copy($compressed_file, $file);
            }
        }

        if (file_exists($compressed_file)) {
            wp_delete_file($compressed_file);
        }
        if ($compressed_file !== $temp_file && file_exists($temp_file)) {
            wp_delete_file($temp_file);
        }

        return true;
    }
}