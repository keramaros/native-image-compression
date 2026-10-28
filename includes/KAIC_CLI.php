<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Manages native image compression via WP-CLI.
 */
class KAIC_CLI
{
    /**
     * Compresses images in the WordPress media library.
     *
     * Iterates through uncompressed images, compresses them, and logs the results.
     *
     * ## OPTIONS
     *
     * [--limit=<number>]
     * : Number of images to compress. Default: all uncompressed images.
     *
     * [--format=<format>]
     * : Render output in a particular format.
     * ---
     * default: log
     * options:
     *   - log
     *   - table
     *   - json
     *   - csv
     *   - yaml
     * ---
     *
     * ## EXAMPLES
     *
     *     # Compress all uncompressed images
     *     wp kaic compress
     *
     *     # Compress next 10 images
     *     wp kaic compress --limit=10
     *
     * @when after_wp_load
     */
    public function compress($args = array(), $assoc_args = array())
    {
        $limit = isset($assoc_args['limit']) ? absint($assoc_args['limit']) : 0;
        $format = isset($assoc_args['format']) ? sanitize_key($assoc_args['format']) : 'log';

        $compression = KAIC_Image_Compression::get_instance();
        $processed_items = array();
        $count = 0;

        if ($format === 'log') {
            WP_CLI::line("Image url\tInitial Size\tCompressed\tSavings");
        }

        while (true) {
            if ($limit > 0 && $count >= $limit) {
                break;
            }

            $attachment_id = $compression->get_next_image_id();
            if (!$attachment_id) {
                if ($count === 0 && $format === 'log') {
                    WP_CLI::success(__('No uncompressed images found. All images are already compressed.', 'native-image-compression'));
                    return;
                }
                break;
            }

            $result = $compression->process_image($attachment_id);
            if (is_wp_error($result)) {
                $error_msg = $result->get_error_message();
                $image_url = wp_get_attachment_url($attachment_id) ?: "attachment_{$attachment_id}";
                if ($format === 'log') {
                    WP_CLI::warning(sprintf("Failed processing %s (ID %d): %s", $image_url, $attachment_id, $error_msg));
                }
                $processed_items[] = array(
                    'ID' => $attachment_id,
                    'Image url' => $image_url,
                    'Initial Size' => 'Error',
                    'Compressed' => '-',
                    'Savings' => '0%',
                    'Status' => 'Error: ' . $error_msg,
                );
                $count++;
                continue;
            }

            $item = array(
                'ID' => $result['id'],
                'Image url' => $result['url'],
                'Initial Size' => $result['initial_size'],
                'Compressed' => $result['compressed'],
                'Savings' => $result['savings'],
                'Status' => $result['error'] ? 'Warning: ' . $result['error'] : 'Success',
            );

            if ($format === 'log') {
                WP_CLI::line(sprintf("%s\t%s\t%s\t%s", $item['Image url'], $item['Initial Size'], $item['Compressed'], $item['Savings']));
            }

            $processed_items[] = $item;
            $count++;
        }

        if ($format !== 'log') {
            WP_CLI\Utils\format_items($format, $processed_items, array('Image url', 'Initial Size', 'Compressed', 'Savings'));
        }

        if ($count > 0) {
            WP_CLI::success(sprintf(__('Successfully processed %d image(s).', 'native-image-compression'), $count));
        } else {
            WP_CLI::log(__('No images were processed.', 'native-image-compression'));
        }
    }

    /**
     * Alias for compress command.
     *
     * ## OPTIONS
     *
     * [--limit=<number>]
     * : Number of images to process.
     *
     * [--format=<format>]
     * : Output format.
     */
    public function process($args = array(), $assoc_args = array())
    {
        $this->compress($args, $assoc_args);
    }

    /**
     * Get next uncompressed image metadata.
     *
     * ## EXAMPLES
     *
     *     wp kaic get-next-image
     *
     * @subcommand get-next-image
     */
    public function get_next_image($args = array(), $assoc_args = array())
    {
        $compression = KAIC_Image_Compression::get_instance();
        $data = $compression->get_next_image_data();

        if (isset($data['finished']) && $data['finished']) {
            WP_CLI::success(__('No uncompressed images found.', 'native-image-compression'));
            return;
        }

        $image = $data['image'];
        WP_CLI::line(sprintf("ID: %d", $image['id']));
        WP_CLI::line(sprintf("Filename: %s", $image['filename']));
        WP_CLI::line(sprintf("Image url: %s", $image['url']));
        WP_CLI::line(sprintf("Initial Size: %s", $image['original_size']));
    }

    /**
     * Process a specific image attachment by ID.
     *
     * ## OPTIONS
     *
     * <id>
     * : The attachment ID to process.
     *
     * ## EXAMPLES
     *
     *     wp kaic process-image 123
     *
     * @subcommand process-image
     */
    public function process_image($args = array(), $assoc_args = array())
    {
        if (empty($args[0])) {
            WP_CLI::error(__('Please provide an attachment ID.', 'native-image-compression'));
        }

        $attachment_id = absint($args[0]);
        $compression = KAIC_Image_Compression::get_instance();

        WP_CLI::line("Image url\tInitial Size\tCompressed\tSavings");
        $result = $compression->process_image($attachment_id);

        if (is_wp_error($result)) {
            WP_CLI::error($result->get_error_message());
        }

        WP_CLI::line(sprintf("%s\t%s\t%s\t%s", $result['url'], $result['initial_size'], $result['compressed'], $result['savings']));

        if ($result['error']) {
            WP_CLI::warning(sprintf(__('Processed with warning: %s', 'native-image-compression'), $result['error']));
        } else {
            WP_CLI::success(sprintf(__('Attachment %d processed successfully.', 'native-image-compression'), $attachment_id));
        }
    }

    /**
     * Show compression statistics for the media library.
     *
     * ## EXAMPLES
     *
     *     wp kaic stats
     */
    public function stats($args = array(), $assoc_args = array())
    {
        $compression = KAIC_Image_Compression::get_instance();
        $stats = $compression->get_stats();

        WP_CLI::line(sprintf("Total Images: %d", $stats['total_attachments']));
        WP_CLI::line(sprintf("Total Files (with thumbnails): %d", $stats['total_files']));
        WP_CLI::line(sprintf("Compressed Images: %d (%s%%)", $stats['processed_count'], $stats['processed_percent']));
        WP_CLI::line(sprintf("Original Size: %s", $stats['original_size_formatted']));
        WP_CLI::line(sprintf("Compressed Size: %s", $stats['compressed_size_formatted']));
        WP_CLI::line(sprintf("Total Saved: %s%%", $stats['total_saved_percent']));
    }

    /**
     * Clears all plugin settings, transients, and compression metadata from the database.
     *
     * ## OPTIONS
     *
     * [--yes]
     * : Answer yes to the confirmation prompt.
     *
     * ## EXAMPLES
     *
     *     wp kaic clear-data --yes
     *
     * @subcommand clear-data
     */
    public function clear_data($args = array(), $assoc_args = array())
    {
        WP_CLI::confirm(__('Are you sure you want to clear all plugin data and compression metadata from the database?', 'native-image-compression'), $assoc_args);

        $compression = KAIC_Image_Compression::get_instance();
        $compression->clear_all_plugin_data();

        WP_CLI::success(__('All plugin data and compression metadata have been cleared from the database.', 'native-image-compression'));
    }

    /**
     * Alias for clear-data command.
     *
     * @subcommand reset
     */
    public function reset($args = array(), $assoc_args = array())
    {
        $this->clear_data($args, $assoc_args);
    }
}
