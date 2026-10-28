<?php

if (!defined('ABSPATH')) {
    exit;
}

class KAIC_Image_Compression
{
    /**
     * Singleton instance
     *
     * @var KAIC_Image_Compression|null
     */
    private static $instance = null;

    /**
     * Get singleton instance
     *
     * @return KAIC_Image_Compression
     */
    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct()
    {
        self::$instance = $this;

        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_init', array($this, 'handle_activation_redirect'));
        add_action('admin_init', array($this, 'handle_clear_log'));
        add_action('admin_init', array($this, 'handle_clear_all_data'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
        add_action('wp_ajax_kaic_get_next_image', array($this, 'ajax_get_next_image'));
        add_action('wp_ajax_kaic_process_image', array($this, 'ajax_process_image'));

        // Automatic compression on upload
        add_filter('wp_generate_attachment_metadata', array($this, 'auto_compress_on_upload'), 10, 2);

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
     * Handle clearing all plugin data from the database
     */
    public function handle_clear_all_data()
    {
        if (isset($_GET['page'], $_GET['action']) && sanitize_text_field(wp_unslash($_GET['page'])) === 'native-image-compression-settings' && sanitize_text_field(wp_unslash($_GET['action'])) === 'kaic_clear_all_data') {
            if (!current_user_can('manage_options')) {
                wp_die(esc_html__('Unauthorized user', 'native-image-compression'));
            }

            check_admin_referer('kaic_clear_all_data_nonce');

            $this->clear_all_plugin_data();

            wp_safe_redirect(add_query_arg(array('page' => 'native-image-compression-settings', 'data_cleared' => '1'), admin_url('options-general.php')));
            exit;
        }
    }

    /**
     * Clear all plugin settings, transients, and compression postmeta from the database.
     */
    public function clear_all_plugin_data()
    {
        global $wpdb;

        // 1. Delete all plugin postmeta from media attachments
        $meta_keys = array(
            '_kaic_is_compressed',
            '_kaic_original_file_size',
            '_kaic_compressed_file_size',
            '_kaic_compression_error',
        );

        foreach ($meta_keys as $meta_key) {
            delete_post_meta_by_key($meta_key);
        }

        $wpdb->query("DELETE FROM $wpdb->postmeta WHERE meta_key LIKE '_kaic_%' OR meta_key LIKE 'kaic_%'");

        // 2. Delete all plugin options
        $options = array(
            'kaic_compression_mode',
            'kaic_quality',
            'kaic_enable_webp',
            'kaic_keep_original_formats',
            'kaic_strip_exif',
            'kaic_remove_icc_profile',
            'kaic_preserve_icc_profile',
            'kaic_enable_resize',
            'kaic_max_width',
            'kaic_max_height',
            'kaic_max_dimension',
            'kaic_compressed_sizes',
            'kaic_excluded_sizes',
            'kaic_enable_png_quant',
            'kaic_progressive_jpeg',
            'kaic_chroma_subsampling',
            'kaic_auto_compress',
            'kaic_enable_backup',
        );

        foreach ($options as $option) {
            delete_option($option);
        }

        $wpdb->query("DELETE FROM $wpdb->options WHERE option_name LIKE 'kaic_%'");

        // 3. Delete transients
        delete_transient('kaic_activation_redirect');
        $wpdb->query("DELETE FROM $wpdb->options WHERE option_name LIKE '_transient_kaic_%' OR option_name LIKE '_transient_timeout_kaic_%'");

        // 4. Flush object cache
        if (function_exists('wp_cache_flush')) {
            wp_cache_flush();
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
        // 1. General & Compression Mode
        register_setting('kaic_settings_group', 'kaic_compression_mode', array(
                'type' => 'string',
                'sanitize_callback' => array($this, 'sanitize_compression_mode'),
                'default' => 'lossy',
        ));
        register_setting('kaic_settings_group', 'kaic_quality', array(
                'type' => 'integer',
                'sanitize_callback' => 'absint',
                'default' => 70,
        ));

        // 2. Next-Gen Formats (WebP)
        register_setting('kaic_settings_group', 'kaic_enable_webp', array(
                'type' => 'integer',
                'sanitize_callback' => 'absint',
                'default' => 0,
        ));
        register_setting('kaic_settings_group', 'kaic_keep_original_formats', array(
                'type' => 'integer',
                'sanitize_callback' => 'absint',
                'default' => 1,
        ));

        // 3. Metadata & Privacy
        register_setting('kaic_settings_group', 'kaic_strip_exif', array(
                'type' => 'integer',
                'sanitize_callback' => 'absint',
                'default' => 0,
        ));
        register_setting('kaic_settings_group', 'kaic_remove_icc_profile', array(
                'type' => 'integer',
                'sanitize_callback' => 'absint',
                'default' => 0,
        ));

        // 4. Dimensional Scaling & Thumbnails
        register_setting('kaic_settings_group', 'kaic_enable_resize', array(
                'type' => 'integer',
                'sanitize_callback' => 'absint',
                'default' => 1,
        ));
        register_setting('kaic_settings_group', 'kaic_max_width', array(
                'type' => 'integer',
                'sanitize_callback' => 'absint',
                'default' => 2048,
        ));
        register_setting('kaic_settings_group', 'kaic_max_height', array(
                'type' => 'integer',
                'sanitize_callback' => 'absint',
                'default' => 2048,
        ));
        register_setting('kaic_settings_group', 'kaic_max_dimension', array(
                'type' => 'integer',
                'sanitize_callback' => 'absint',
                'default' => 2048,
        ));
        register_setting('kaic_settings_group', 'kaic_compressed_sizes', array(
                'type' => 'array',
                'sanitize_callback' => array($this, 'sanitize_compressed_sizes'),
                'default' => array(),
        ));

        // 5. Format-Specific Optimizations
        register_setting('kaic_settings_group', 'kaic_enable_png_quant', array(
                'type' => 'integer',
                'sanitize_callback' => 'absint',
                'default' => 1,
        ));
        register_setting('kaic_settings_group', 'kaic_progressive_jpeg', array(
                'type' => 'integer',
                'sanitize_callback' => 'absint',
                'default' => 1,
        ));
        register_setting('kaic_settings_group', 'kaic_chroma_subsampling', array(
                'type' => 'integer',
                'sanitize_callback' => 'absint',
                'default' => 1,
        ));

        // 6. Automation & Backup
        register_setting('kaic_settings_group', 'kaic_auto_compress', array(
                'type' => 'integer',
                'sanitize_callback' => 'absint',
                'default' => 0,
        ));
        register_setting('kaic_settings_group', 'kaic_enable_backup', array(
                'type' => 'integer',
                'sanitize_callback' => 'absint',
                'default' => 0,
        ));
    }

    /**
     * Sanitize compression mode setting
     *
     * @param string $value
     * @return string
     */
    public function sanitize_compression_mode($value)
    {
        $value = sanitize_text_field($value);
        return in_array($value, array('lossy', 'lossless'), true) ? $value : 'lossy';
    }

    /**
     * Sanitize compressed thumbnail sizes setting
     *
     * @param array $value
     * @return array
     */
    public function sanitize_compressed_sizes($value)
    {
        if (!is_array($value)) {
            return array();
        }
        return array_values(array_map('sanitize_key', $value));
    }

    /**
     * Automatically compress media upon upload if enabled
     *
     * @param array $metadata
     * @param int $attachment_id
     * @return array
     */
    public function auto_compress_on_upload($metadata, $attachment_id)
    {
        if (!get_option('kaic_auto_compress', 0)) {
            return $metadata;
        }

        if ($this->is_svg($attachment_id)) {
            return $metadata;
        }

        $mime_type = get_post_mime_type($attachment_id);
        if (!$mime_type || stripos($mime_type, 'image/') !== 0) {
            return $metadata;
        }

        // Avoid processing twice if already marked
        if (get_post_meta($attachment_id, '_kaic_is_compressed', true)) {
            return $metadata;
        }

        $this->process_image($attachment_id);

        $updated_meta = wp_get_attachment_metadata($attachment_id);
        return is_array($updated_meta) ? $updated_meta : $metadata;
    }

    /**
     * Get path to backup directory
     *
     * @return string
     */
    public function get_backup_dir()
    {
        $upload_dir = wp_upload_dir();
        return trailingslashit($upload_dir['basedir']) . 'kaic-backups';
    }

    /**
     * Backup an original image file before compression
     *
     * @param string $file
     * @param int $attachment_id
     * @return bool
     */
    public function backup_file($file, $attachment_id)
    {
        if (!get_option('kaic_enable_backup', 0)) {
            return true;
        }

        if (!file_exists($file)) {
            return false;
        }

        $backup_dir = $this->get_backup_dir();
        $target_dir = $backup_dir . '/' . absint($attachment_id);

        if (!file_exists($target_dir)) {
            wp_mkdir_p($target_dir);
        }

        $dest_file = $target_dir . '/' . basename($file);
        if (!file_exists($dest_file)) {
            return @copy($file, $dest_file);
        }

        return true;
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
        if ('media_page_native-image-compression-bulk' !== $hook && 'settings_page_native-image-compression-settings' !== $hook) {
            return;
        }

        wp_enqueue_style('kaic-admin-css', KAIC_PLUGIN_DIR_URL . 'assets/css/admin.css', array('dashicons'), '1.0.7');

        if ('media_page_native-image-compression-bulk' === $hook) {
            wp_enqueue_script('kaic-admin-js', KAIC_PLUGIN_DIR_URL . 'assets/js/admin.js', array('jquery'), '1.0.7', true);

            wp_localize_script('kaic-admin-js', 'kaic_vars', array(
                    'nonce' => wp_create_nonce('kaic_nonce')
            ));
        }
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

        $compression_mode = get_option('kaic_compression_mode', 'lossy');
        $quality = get_option('kaic_quality', 70);

        $enable_webp = get_option('kaic_enable_webp', 0);
        $keep_original_formats = get_option('kaic_keep_original_formats', 1);

        $strip_exif = get_option('kaic_strip_exif', 0);
        $remove_icc_profile = get_option('kaic_remove_icc_profile', 0);

        $enable_resize = get_option('kaic_enable_resize', 1);
        $max_width = get_option('kaic_max_width', get_option('kaic_max_dimension', 2048));
        $max_height = get_option('kaic_max_height', get_option('kaic_max_dimension', 2048));
        $available_sizes = function_exists('get_intermediate_image_sizes') ? get_intermediate_image_sizes() : array('thumbnail', 'medium', 'medium_large', 'large');
        $compressed_sizes = get_option('kaic_compressed_sizes', null);
        if ($compressed_sizes === null) {
            $legacy_excluded = get_option('kaic_excluded_sizes', null);
            if ($legacy_excluded !== null && is_array($legacy_excluded)) {
                $compressed_sizes = array_values(array_diff($available_sizes, $legacy_excluded));
            } else {
                $compressed_sizes = $available_sizes;
            }
        } elseif (!is_array($compressed_sizes)) {
            $compressed_sizes = array();
        }

        $enable_png_quant = get_option('kaic_enable_png_quant', 1);
        $progressive_jpeg = get_option('kaic_progressive_jpeg', 1);
        $chroma_subsampling = get_option('kaic_chroma_subsampling', 1);

        $auto_compress = get_option('kaic_auto_compress', 0);
        $enable_backup = get_option('kaic_enable_backup', 0);
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <?php if (isset($_GET['log_cleared']) && sanitize_text_field(wp_unslash($_GET['log_cleared'])) === '1') : ?>
                <div class="notice notice-success is-dismissible">
                    <p><?php esc_html_e('Error log cleared successfully.', 'native-image-compression'); ?></p>
                </div>
            <?php endif; ?>
            <?php if (isset($_GET['data_cleared']) && sanitize_text_field(wp_unslash($_GET['data_cleared'])) === '1') : ?>
                <div class="notice notice-success is-dismissible">
                    <p><?php esc_html_e('All plugin data and compression metadata have been successfully cleared from the database.', 'native-image-compression'); ?></p>
                </div>
            <?php endif; ?>
            <form method="post" action="options.php">
                <?php
                settings_fields('kaic_settings_group');
                do_settings_sections('kaic_settings_group');
                ?>

                <h2><?php esc_html_e('1. Compression Mode & Quality', 'native-image-compression'); ?></h2>
                <table class="form-table" role="presentation">
                    <tr valign="top">
                        <th scope="row"><?php esc_html_e('Compression Strategy', 'native-image-compression'); ?></th>
                        <td>
                            <select name="kaic_compression_mode" id="kaic_compression_mode">
                                <option value="lossy" <?php selected($compression_mode, 'lossy'); ?>><?php esc_html_e('Lossy Compression', 'native-image-compression'); ?></option>
                                <option value="lossless" <?php selected($compression_mode, 'lossless'); ?>><?php esc_html_e('Lossless Compression', 'native-image-compression'); ?></option>
                            </select>
                            <p class="description">
                                <?php esc_html_e('Lossy provides maximum file size reduction with imperceptible visual adjustment, optimal for standard web delivery. Lossless preserves 100% exact pixel data and is ideal for logos, technical diagrams, and pixel-critical imagery.', 'native-image-compression'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row"><?php esc_html_e('Image Quality Level', 'native-image-compression'); ?></th>
                        <td>
                            <input type="number" name="kaic_quality" value="<?php echo esc_attr($quality); ?>" min="1" max="100" class="small-text"/>
                            <p class="description"><?php esc_html_e('The quality level for compressed JPEG, WebP, and lossy images (1-100). Higher values produce higher visual fidelity at larger file sizes. Recommended default is 70-80 (applies to Lossy mode).', 'native-image-compression'); ?></p>
                        </td>
                    </tr>
                </table>

                <h2><?php esc_html_e('2. Next-Generation Format Conversion (WebP)', 'native-image-compression'); ?></h2>
                <table class="form-table" role="presentation">
                    <tr valign="top">
                        <th scope="row"><?php esc_html_e('WebP Generation', 'native-image-compression'); ?></th>
                        <td>
                            <label>
                                <input type="hidden" name="kaic_enable_webp" value="0"/>
                                <input type="checkbox" name="kaic_enable_webp" value="1" <?php checked($enable_webp, 1); ?> />
                                <?php esc_html_e('Enable Next-Generation WebP conversion', 'native-image-compression'); ?>
                            </label>
                            <p class="description"><?php esc_html_e('Automatically converts and generates modern WebP copies of uploaded JPEG and PNG images. WebP delivers 25–35% smaller file sizes than JPEG at equivalent visual quality for faster browser loading.', 'native-image-compression'); ?></p>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row"><?php esc_html_e('Preserve Original Formats', 'native-image-compression'); ?></th>
                        <td>
                            <label>
                                <input type="hidden" name="kaic_keep_original_formats" value="0"/>
                                <input type="checkbox" name="kaic_keep_original_formats" value="1" <?php checked($keep_original_formats, 1); ?> />
                                <?php esc_html_e('Keep original JPEG/PNG files alongside WebP', 'native-image-compression'); ?>
                            </label>
                            <p class="description"><?php esc_html_e('Keeps original JPEG and PNG files on disk to serve as fallbacks for legacy browsers and administrative archiving. Disable to replace original files and minimize server disk usage.', 'native-image-compression'); ?></p>
                        </td>
                    </tr>
                </table>

                <h2><?php esc_html_e('3. Metadata & EXIF Stripping', 'native-image-compression'); ?></h2>
                <table class="form-table" role="presentation">
                    <tr valign="top">
                        <th scope="row"><?php esc_html_e('Strip EXIF & Metadata', 'native-image-compression'); ?></th>
                        <td>
                            <label>
                                <input type="hidden" name="kaic_strip_exif" value="0"/>
                                <input type="checkbox" name="kaic_strip_exif" value="1" <?php checked($strip_exif, 1); ?> />
                                <?php esc_html_e('Remove non-visual metadata from images', 'native-image-compression'); ?>
                            </label>
                            <p class="description"><?php esc_html_e('Removes non-visual EXIF metadata (camera model, GPS coordinates, timestamps, shutter speeds, and embedded thumbnails). Trims several kilobytes per image and protects user privacy.', 'native-image-compression'); ?></p>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row"><?php esc_html_e('Color Profiles & Copyright', 'native-image-compression'); ?></th>
                        <td>
                            <label>
                                <input type="hidden" name="kaic_remove_icc_profile" value="0"/>
                                <input type="checkbox" name="kaic_remove_icc_profile" value="1" <?php checked($remove_icc_profile, 1); ?> />
                                <?php esc_html_e('Remove ICC color profiles and copyright tags', 'native-image-compression'); ?>
                            </label>
                            <p class="description"><?php esc_html_e('Removes embedded ICC color profiles and creator copyright tags for additional file size reduction. Keep unchecked to maintain exact color accuracy across different displays and preserve copyright attribution.', 'native-image-compression'); ?></p>
                        </td>
                    </tr>
                </table>

                <h2><?php esc_html_e('4. Dimensional Scaling & Thumbnail Compression', 'native-image-compression'); ?></h2>
                <table class="form-table" role="presentation">
                    <tr valign="top">
                        <th scope="row"><?php esc_html_e('Dimensional Scaling', 'native-image-compression'); ?></th>
                        <td>
                            <label>
                                <input type="hidden" name="kaic_enable_resize" value="0"/>
                                <input type="checkbox" name="kaic_enable_resize" value="1" <?php checked($enable_resize, 1); ?> />
                                <?php esc_html_e('Enable automatic dimensional downscaling for oversized uploads', 'native-image-compression'); ?>
                            </label>
                            <p class="description"><?php esc_html_e('Proportionally resizes large uploaded images exceeding the maximum width or height thresholds down to practical display dimensions prior to compression.', 'native-image-compression'); ?></p>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row"><?php esc_html_e('Maximum Width', 'native-image-compression'); ?></th>
                        <td>
                            <input type="number" name="kaic_max_width" value="<?php echo esc_attr($max_width); ?>" class="regular-text"/> px
                            <p class="description"><?php esc_html_e('The maximum allowed width in pixels for compressed images. Images wider than this will be proportionally downscaled.', 'native-image-compression'); ?></p>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row"><?php esc_html_e('Maximum Height', 'native-image-compression'); ?></th>
                        <td>
                            <input type="number" name="kaic_max_height" value="<?php echo esc_attr($max_height); ?>" class="regular-text"/> px
                            <p class="description"><?php esc_html_e('The maximum allowed height in pixels for compressed images. Images taller than this will be proportionally downscaled.', 'native-image-compression'); ?></p>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row"><?php esc_html_e('Compress Thumbnail Sizes', 'native-image-compression'); ?></th>
                        <td>
                            <fieldset>
                                <input type="hidden" name="kaic_compressed_sizes" value="" />
                                <?php if (!empty($available_sizes)) : ?>
                                    <?php foreach ($available_sizes as $size_slug) : ?>
                                        <label style="display: block; margin-bottom: 6px;">
                                            <input type="checkbox" name="kaic_compressed_sizes[]" value="<?php echo esc_attr($size_slug); ?>" <?php checked(in_array($size_slug, $compressed_sizes, true)); ?> />
                                            <code><?php echo esc_html($size_slug); ?></code>
                                        </label>
                                    <?php endforeach; ?>
                                <?php else : ?>
                                    <p><?php esc_html_e('No registered intermediate thumbnail sizes found.', 'native-image-compression'); ?></p>
                                <?php endif; ?>
                                <p class="description"><?php esc_html_e('Select which registered intermediate thumbnail sizes should be compressed. All thumbnail sizes are included by default. Uncheck any sizes you wish to skip to save processing time.', 'native-image-compression'); ?></p>
                            </fieldset>
                        </td>
                    </tr>
                </table>

                <h2><?php esc_html_e('5. Format-Specific Optimizations', 'native-image-compression'); ?></h2>
                <table class="form-table" role="presentation">
                    <tr valign="top">
                        <th scope="row"><?php esc_html_e('PNG Quantization (PNG-8)', 'native-image-compression'); ?></th>
                        <td>
                            <label>
                                <input type="hidden" name="kaic_enable_png_quant" value="0"/>
                                <input type="checkbox" name="kaic_enable_png_quant" value="1" <?php checked($enable_png_quant, 1); ?> />
                                <?php esc_html_e('Enable PNG color palette quantization', 'native-image-compression'); ?>
                            </label>
                            <p class="description"><?php esc_html_e('Converts 24/32-bit PNG images to 8-bit indexed palettes when possible while preserving transparency, yielding 60–80% size savings on illustrations, logos, and UI graphics.', 'native-image-compression'); ?></p>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row"><?php esc_html_e('Progressive JPEG Encoding', 'native-image-compression'); ?></th>
                        <td>
                            <label>
                                <input type="hidden" name="kaic_progressive_jpeg" value="0"/>
                                <input type="checkbox" name="kaic_progressive_jpeg" value="1" <?php checked($progressive_jpeg, 1); ?> />
                                <?php esc_html_e('Enable progressive JPEG rendering', 'native-image-compression'); ?>
                            </label>
                            <p class="description"><?php esc_html_e('Encodes JPEG images progressively so visitors see an immediate full preview that sharpens as data streams in, improving perceived speed on mobile and slow connections.', 'native-image-compression'); ?></p>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row"><?php esc_html_e('Chroma Subsampling (4:2:0)', 'native-image-compression'); ?></th>
                        <td>
                            <label>
                                <input type="hidden" name="kaic_chroma_subsampling" value="0"/>
                                <input type="checkbox" name="kaic_chroma_subsampling" value="1" <?php checked($chroma_subsampling, 1); ?> />
                                <?php esc_html_e('Enable 4:2:0 chroma subsampling for JPEG compression', 'native-image-compression'); ?>
                            </label>
                            <p class="description"><?php esc_html_e('Reduces color resolution where human vision is least sensitive, producing notable byte savings on photographic JPEGs with negligible visual loss.', 'native-image-compression'); ?></p>
                        </td>
                    </tr>
                </table>

                <h2><?php esc_html_e('6. Automation & Backup Controls', 'native-image-compression'); ?></h2>
                <table class="form-table" role="presentation">
                    <tr valign="top">
                        <th scope="row"><?php esc_html_e('Auto-Compress on Upload', 'native-image-compression'); ?></th>
                        <td>
                            <label>
                                <input type="hidden" name="kaic_auto_compress" value="0"/>
                                <input type="checkbox" name="kaic_auto_compress" value="1" <?php checked($auto_compress, 1); ?> />
                                <?php esc_html_e('Automatically compress newly uploaded images', 'native-image-compression'); ?>
                            </label>
                            <p class="description"><?php esc_html_e('Hooks into WordPress image generation to automatically optimize images immediately upon upload, ensuring all new media is compressed without manual intervention.', 'native-image-compression'); ?></p>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row"><?php esc_html_e('Backup Originals', 'native-image-compression'); ?></th>
                        <td>
                            <label>
                                <input type="hidden" name="kaic_enable_backup" value="0"/>
                                <input type="checkbox" name="kaic_enable_backup" value="1" <?php checked($enable_backup, 1); ?> />
                                <?php esc_html_e('Create backups of original uncompressed images', 'native-image-compression'); ?>
                            </label>
                            <p class="description"><?php esc_html_e('Safely stores untouched copies of original image files in a dedicated backup directory (wp-content/uploads/kaic-backups/) prior to compression, enabling safe rollbacks.', 'native-image-compression'); ?></p>
                        </td>
                    </tr>
                </table>

                <h2><?php esc_html_e('7. Error Log & Maintenance', 'native-image-compression'); ?></h2>
                <table class="form-table" role="presentation">
                    <tr valign="top">
                        <th scope="row"><?php esc_html_e('Error Log File', 'native-image-compression'); ?></th>
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
                            <p class="description"><?php esc_html_e('View or clear the error log file to troubleshoot image processing issues.', 'native-image-compression'); ?></p>
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>

            <div class="kaic-danger-zone">
                <h2><?php esc_html_e('Danger Zone', 'native-image-compression'); ?></h2>
                <p><?php esc_html_e('Actions in this section are irreversible. Please use caution when resetting or clearing database records.', 'native-image-compression'); ?></p>
                <table class="form-table" role="presentation">
                    <tr valign="top">
                        <th scope="row"><?php esc_html_e('Clear Plugin Database Data', 'native-image-compression'); ?></th>
                        <td>
                            <p>
                                <a href="<?php echo esc_url(wp_nonce_url(admin_url('options-general.php?page=native-image-compression-settings&action=kaic_clear_all_data'), 'kaic_clear_all_data_nonce')); ?>"
                                   class="button button-danger"
                                   onclick="return confirm('<?php echo esc_js(__('WARNING: This will permanently delete all plugin settings, compression history, and metadata from the database. Are you sure you want to proceed?', 'native-image-compression')); ?>');">
                                    <?php esc_html_e('Clear All Plugin Data', 'native-image-compression'); ?>
                                </a>
                            </p>
                            <p class="description">
                                <?php esc_html_e('Permanently deletes all plugin configuration options and removes compression metadata (initial/compressed file sizes, and error records) from all media attachments in the database. Image files on disk will not be deleted.', 'native-image-compression'); ?>
                            </p>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
        <?php
    }

    /**
     * Check if an attachment is an SVG image
     *
     * @param int|WP_Post $attachment
     * @return bool
     */
    public function is_svg($attachment)
    {
        $attachment_id = is_object($attachment) ? $attachment->ID : absint($attachment);
        if (!$attachment_id) {
            return false;
        }

        $mime_type = get_post_mime_type($attachment_id);
        if ($mime_type && stripos($mime_type, 'svg') !== false) {
            return true;
        }

        $file = get_attached_file($attachment_id);
        if ($file) {
            $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
            if ($extension === 'svg') {
                return true;
            }
        }

        return false;
    }

    /**
     * Get Overall Stats
     */
    public function get_stats()
    {
        global $wpdb;

        $total_original_size = 0;
        $total_compressed_size = 0;
        $total_files_count = 0;
        $total_attachments = 0;
        $processed_count = 0;

        $attachments = get_posts(array(
                'post_type' => 'attachment',
                'post_mime_type' => 'image',
                'posts_per_page' => -1,
                'fields' => 'ids',
        ));

        foreach ($attachments as $id) {
            if ($this->is_svg($id)) {
                continue;
            }

            $total_attachments++;
            $is_compressed = get_post_meta($id, '_kaic_is_compressed', true);
            $orig = (float)get_post_meta($id, '_kaic_original_file_size', true);
            $opt = (float)get_post_meta($id, '_kaic_compressed_file_size', true);

            if ($is_compressed) {
                $processed_count++;
            }

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
    public function get_attachment_total_size($attachment_id)
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
     * Get next uncompressed image ID
     *
     * @return int|null
     */
    public function get_next_image_id()
    {
        global $wpdb;
        $offset = 0;
        $limit = 20;

        while (true) {
            $results = $wpdb->get_results($wpdb->prepare("
                SELECT p.ID 
                FROM $wpdb->posts p 
                LEFT JOIN $wpdb->postmeta pm ON p.ID = pm.post_id AND pm.meta_key = '_kaic_is_compressed' 
                WHERE p.post_type = 'attachment' 
                AND p.post_mime_type LIKE 'image/%' 
                AND p.post_mime_type NOT LIKE 'image/svg%'
                AND (pm.meta_value IS NULL OR pm.meta_value = '0')
                ORDER BY p.ID ASC
                LIMIT %d OFFSET %d
            ", $limit, $offset));

            if (empty($results)) {
                return null;
            }

            foreach ($results as $row) {
                $id = (int)$row->ID;
                if (!$this->is_svg($id)) {
                    return $id;
                }
            }

            $offset += $limit;
        }
    }

    /**
     * Get next uncompressed image details
     *
     * @return array
     */
    public function get_next_image_data()
    {
        $attachment_id = $this->get_next_image_id();
        if (!$attachment_id) {
            return array('finished' => true);
        }

        $original_size = $this->get_attachment_total_size($attachment_id);
        $thumb_url = wp_get_attachment_image_url($attachment_id, 'thumbnail');
        $image_url = wp_get_attachment_url($attachment_id);
        $filename = basename(get_attached_file($attachment_id) ?: "attachment_{$attachment_id}");

        return array(
                'finished' => false,
                'image' => array(
                        'id' => $attachment_id,
                        'filename' => $filename,
                        'thumb' => $thumb_url ? $thumb_url : '',
                        'url' => $image_url ? $image_url : '',
                        'original_size' => is_wp_error($original_size) ? 'Error' : size_format($original_size),
                        'original_size_bytes' => is_wp_error($original_size) ? 0 : (int)$original_size,
                ),
        );
    }

    /**
     * Process / Compress specific attachment image
     *
     * @param int $attachment_id
     * @return array|WP_Error
     */
    public function process_image($attachment_id)
    {
        $attachment_id = absint($attachment_id);
        if (!$attachment_id) {
            $this->log_error('Invalid attachment ID provided in process_image.');
            return new WP_Error('invalid_attachment_id', __('Invalid attachment ID', 'native-image-compression'));
        }

        if ($this->is_svg($attachment_id)) {
            return new WP_Error('svg_skipped', __('SVG images are skipped as they cannot be compressed.', 'native-image-compression'));
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
        $saved_bytes = ($orig_size_num > 0 && $orig_size_num >= $comp_size_num) ? ($orig_size_num - $comp_size_num) : 0;

        $image_url = wp_get_attachment_url($attachment_id);
        if (empty($image_url)) {
            $attached_file = get_attached_file($attachment_id);
            $image_url = $attached_file ? $attached_file : "attachment_{$attachment_id}";
        }

        return array(
                'id' => $attachment_id,
                'url' => $image_url,
                'initial_size' => $orig_size_num > 0 ? size_format($orig_size_num) : 'Error',
                'initial_size_bytes' => $orig_size_num,
                'compressed' => $optimized_size_display,
                'compressed_bytes' => $comp_size_num,
                'savings' => $saved_percent . '%',
                'savings_percent' => $saved_percent,
                'savings_bytes' => $saved_bytes,
                'savings_formatted' => $saved_bytes > 0 ? size_format($saved_bytes) . " ({$saved_percent}%)" : '0%',
                'optimized_size' => $optimized_size_display,
                'saved_percent' => $saved_percent,
                'error' => $error,
        );
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

            $data = $this->get_next_image_data();
            if (isset($data['finished']) && $data['finished']) {
                wp_send_json_success(array('finished' => true));
            }

            wp_send_json_success(array(
                    'image' => array(
                            'id' => $data['image']['id'],
                            'filename' => $data['image']['filename'],
                            'thumb' => $data['image']['thumb'],
                            'original_size' => $data['image']['original_size'],
                    ),
                    'finished' => false,
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

            $result = $this->process_image($attachment_id);
            if (is_wp_error($result)) {
                wp_send_json_error(array(
                        'message' => $result->get_error_message(),
                        'stats' => $this->get_stats(),
                        'image' => array(
                                'id' => $attachment_id,
                                'optimized_size' => '-',
                                'saved_percent' => 0,
                                'error' => $result->get_error_message(),
                        )
                ));
            }

            wp_send_json_success(array(
                    'stats' => $this->get_stats(),
                    'image' => array(
                            'id' => $attachment_id,
                            'optimized_size' => $result['optimized_size'],
                            'saved_percent' => $result['saved_percent'],
                            'error' => $result['error'],
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
     * Compresses an image attachment including its main file and intermediate thumbnail sizes.
     *
     * @param int $attachment_id
     * @return bool|WP_Error
     */
    public function simulate_media_compression($attachment_id)
    {
        if ($this->is_svg($attachment_id)) {
            return new WP_Error('svg_skipped', __('SVG images are skipped as they cannot be compressed.', 'native-image-compression'));
        }

        $file = get_attached_file($attachment_id);
        if (!$file) {
            return new WP_Error('file_not_found', __('Attachment file path not found.', 'native-image-compression'));
        }

        if (!file_exists($file)) {
            /* translators: %s: File name. */
            return new WP_Error('file_not_found', sprintf(__('File does not exist: %s', 'native-image-compression'), basename($file)));
        }

        // Load compression settings
        $mode = get_option('kaic_compression_mode', 'lossy');
        $quality = ($mode === 'lossless') ? 100 : (int)get_option('kaic_quality', 70);
        $enable_resize = (bool)get_option('kaic_enable_resize', 1);
        $max_width = (int)get_option('kaic_max_width', get_option('kaic_max_dimension', 2048));
        $max_height = (int)get_option('kaic_max_height', get_option('kaic_max_dimension', 2048));
        if ($max_width <= 0) {
            $max_width = 2048;
        }
        if ($max_height <= 0) {
            $max_height = 2048;
        }

        $options = array(
                'mode' => $mode,
                'quality' => $quality,
                'enable_resize' => $enable_resize,
                'max_width' => $max_width,
                'max_height' => $max_height,
                'enable_webp' => (bool)get_option('kaic_enable_webp', 0),
                'keep_original_formats' => (bool)get_option('kaic_keep_original_formats', 1),
                'strip_exif' => (bool)get_option('kaic_strip_exif', 0),
                'remove_icc_profile' => (bool)get_option('kaic_remove_icc_profile', 0),
                'enable_png_quant' => (bool)get_option('kaic_enable_png_quant', 1),
                'progressive_jpeg' => (bool)get_option('kaic_progressive_jpeg', 1),
                'chroma_subsampling' => (bool)get_option('kaic_chroma_subsampling', 1),
                'enable_backup' => (bool)get_option('kaic_enable_backup', 0),
        );

        // 1. Compress main image file
        $main_save = $this->compress_single_file($file, $attachment_id, $options, true);
        if (is_wp_error($main_save)) {
            $this->log_error(sprintf('Failed to compress main image for attachment ID %d (%s): %s', $attachment_id, basename($file), $main_save->get_error_message()));
            return $main_save;
        }

        // 2. Compress registered intermediate sizes (thumbnails)
        $available_sizes = function_exists('get_intermediate_image_sizes') ? get_intermediate_image_sizes() : array('thumbnail', 'medium', 'medium_large', 'large');
        $compressed_sizes = get_option('kaic_compressed_sizes', null);
        if ($compressed_sizes === null) {
            $legacy_excluded = get_option('kaic_excluded_sizes', null);
            if ($legacy_excluded !== null && is_array($legacy_excluded)) {
                $compressed_sizes = array_values(array_diff($available_sizes, $legacy_excluded));
            } else {
                $compressed_sizes = $available_sizes;
            }
        } elseif (!is_array($compressed_sizes)) {
            $compressed_sizes = array();
        }

        $meta = wp_get_attachment_metadata($attachment_id);
        if (!empty($meta['sizes']) && is_array($meta['sizes'])) {
            $dirname = dirname($file);
            foreach ($meta['sizes'] as $size_name => $thumb) {
                if (empty($thumb['file'])) {
                    continue;
                }

                // Check thumbnail inclusion
                if (!in_array($size_name, $compressed_sizes, true)) {
                    continue;
                }

                $thumb_path = $dirname . '/' . $thumb['file'];
                if (file_exists($thumb_path)) {
                    $thumb_save = $this->compress_single_file($thumb_path, $attachment_id, $options, false);
                    if (is_wp_error($thumb_save)) {
                        $this->log_error(sprintf('Failed to compress thumbnail %s for attachment ID %d (%s): %s', basename($thumb_path), $attachment_id, basename($file), $thumb_save->get_error_message()));
                    }
                }
            }
        }

        return true;
    }

    /**
     * Compress a single image file with the specified options
     *
     * @param string $file
     * @param int $attachment_id
     * @param array $options
     * @param bool $is_main_file
     * @return bool|WP_Error
     */
    public function compress_single_file($file, $attachment_id, $options, $is_main_file = true)
    {
        // 1. Backup original file if enabled
        if (!empty($options['enable_backup'])) {
            $this->backup_file($file, $attachment_id);
        }

        $editor = wp_get_image_editor($file);
        if (is_wp_error($editor)) {
            return $editor;
        }

        // 2. Dimensional scaling
        if (!empty($options['enable_resize'])) {
            $size = $editor->get_size();
            if (is_array($size) && isset($size['width'], $size['height'])) {
                $max_w = (int)$options['max_width'];
                $max_h = (int)$options['max_height'];
                if ($size['width'] > $max_w || $size['height'] > $max_h) {
                    $resize_result = $editor->resize($max_w, $max_h, false);
                    if (is_wp_error($resize_result)) {
                        $this->log_error(sprintf('Failed to resize image %s (ID %d): %s', basename($file), $attachment_id, $resize_result->get_error_message()));
                    }
                }
            }
        }

        // 3. Set quality
        $quality = isset($options['quality']) ? (int)$options['quality'] : 70;
        $editor->set_quality($quality);

        // 4. Format-specific optimizations
        $mime_type = function_exists('wp_check_filetype') ? wp_check_filetype($file)['type'] : null;
        if (!$mime_type) {
            $mime_type = get_post_mime_type($attachment_id);
        }
        $this->apply_advanced_editor_settings($editor, $mime_type, $options);

        // 5. Save compressed file if smaller
        $save_result = $this->save_editor_if_smaller($editor, $file);
        if (is_wp_error($save_result)) {
            return $save_result;
        }

        // 6. Next-Gen WebP generation
        if (!empty($options['enable_webp'])) {
            $this->generate_webp_version($editor, $file, $options);
        }

        return true;
    }

    /**
     * Generate WebP version of an image
     *
     * @param WP_Image_Editor $editor
     * @param string $file
     * @param array $options
     * @return bool|WP_Error
     */
    public function generate_webp_version($editor, $file, $options)
    {
        if (!method_exists($editor, 'supports_mime_type') || !$editor->supports_mime_type('image/webp')) {
            return false;
        }

        $extension = pathinfo($file, PATHINFO_EXTENSION);
        if (strtolower($extension) === 'webp') {
            return true;
        }

        $webp_file = preg_replace('/\.(jpe?g|png)$/i', '.webp', $file);
        if ($webp_file === $file) {
            $webp_file = $file . '.webp';
        }

        $saved = $editor->save($webp_file, 'image/webp');
        if (is_wp_error($saved)) {
            return $saved;
        }

        return true;
    }

    /**
     * Apply advanced optimizations (EXIF, ICC, Progressive JPEG, Chroma Subsampling, PNG quantization)
     *
     * @param WP_Image_Editor $editor
     * @param string $mime_type
     * @param array $options
     */
    public function apply_advanced_editor_settings($editor, $mime_type, $options)
    {
        $is_jpeg = in_array($mime_type, array('image/jpeg', 'image/jpg', 'image/pjpeg'), true);
        $is_png = in_array($mime_type, array('image/png', 'image/x-png'), true);

        if (is_a($editor, 'WP_Image_Editor_Imagick')) {
            try {
                $reflection = new \ReflectionClass($editor);
                if ($reflection->hasProperty('image')) {
                    $prop = $reflection->getProperty('image');
                    $prop->setAccessible(true);
                    $imagick = $prop->getValue($editor);

                    if ($imagick instanceof \Imagick) {
                        // EXIF Stripping & ICC Profile Handling
                        if (!empty($options['strip_exif'])) {
                            $icc = null;
                            if (empty($options['remove_icc_profile'])) {
                                try {
                                    $icc = $imagick->getImageProfiles('icc', true);
                                } catch (\Throwable $e) {
                                }
                            }

                            $imagick->stripImage();

                            if ($icc && !empty($icc['icc'])) {
                                try {
                                    $imagick->profileImage('icc', $icc['icc']);
                                } catch (\Throwable $e) {
                                }
                            }
                        } elseif (!empty($options['remove_icc_profile'])) {
                            try {
                                $imagick->profileImage('icc', null);
                            } catch (\Throwable $e) {
                            }
                        }

                        // Progressive JPEG
                        if ($is_jpeg && !empty($options['progressive_jpeg']) && defined('\Imagick::INTERLACE_PLANE')) {
                            $imagick->setInterlaceScheme(\Imagick::INTERLACE_PLANE);
                        }

                        // Chroma Subsampling 4:2:0 for JPEGs
                        if ($is_jpeg && !empty($options['chroma_subsampling'])) {
                            $imagick->setSamplingFactors(array('2x2', '1x1', '1x1'));
                        }

                        // PNG Palette Quantization (PNG8)
                        if ($is_png && !empty($options['enable_png_quant'])) {
                            if (method_exists($imagick, 'quantizeImage') && defined('\Imagick::COLORSPACE_SRGB')) {
                                $imagick->quantizeImage(256, \Imagick::COLORSPACE_SRGB, 0, false, false);
                            }
                        }
                    }
                }
            } catch (\Throwable $e) {
                // Silently fall back if reflection/properties not accessible
            }
        } elseif (is_a($editor, 'WP_Image_Editor_GD')) {
            try {
                $reflection = new \ReflectionClass($editor);
                if ($reflection->hasProperty('image')) {
                    $prop = $reflection->getProperty('image');
                    $prop->setAccessible(true);
                    $gd_image = $prop->getValue($editor);

                    if (is_resource($gd_image) || (is_object($gd_image) && $gd_image instanceof \GdImage)) {
                        // Progressive JPEG
                        if ($is_jpeg && !empty($options['progressive_jpeg']) && function_exists('imageinterlace')) {
                            @imageinterlace($gd_image, 1);
                        }

                        // PNG Quantization
                        if ($is_png && !empty($options['enable_png_quant']) && function_exists('imagetruecolortopalette') && function_exists('imageistruecolor')) {
                            if (imageistruecolor($gd_image)) {
                                @imagetruecolortopalette($gd_image, false, 256);
                            }
                        }
                    }
                }
            } catch (\Throwable $e) {
                // Silently fall back
            }
        }
    }

    public function save_editor_if_smaller($editor, $file)
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
                if (file_exists(ABSPATH . 'wp-admin/includes/file.php')) {
                    require_once ABSPATH . 'wp-admin/includes/file.php';
                }
                if (function_exists('WP_Filesystem')) {
                    WP_Filesystem();
                }
            }

            $moved = false;
            if ($wp_filesystem) {
                $moved = $wp_filesystem->move($temp_file, $temp_file_with_extension, true);
            }
            if (!$moved && file_exists($temp_file)) {
                $moved = @rename($temp_file, $temp_file_with_extension);
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