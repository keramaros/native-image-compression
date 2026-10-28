<?php
/**
 * Plugin Name: Native Image Compression
 * Plugin URI: https://keramaros.gr
 * Description: An image compression plugin that shrinks file sizes by automatically resizing dimensions and adjusting quality for faster web performance.
 * Version: 1.0.5
 * Author: Keramaros Antonios
 * Author URI: https://keramaros.gr
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: native-image-compression
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

if (!defined('ABSPATH')) {
    exit;
}

define('KAIC_PLUGIN_FILE', __FILE__);
define('KAIC_PLUGIN_DIR_PATH', plugin_dir_path(__FILE__));
define('KAIC_PLUGIN_DIR_URL', plugin_dir_url(__FILE__));

require_once KAIC_PLUGIN_DIR_PATH . 'includes/KAIC_Image_Compression.php';
require_once KAIC_PLUGIN_DIR_PATH . 'includes/GitHub_Updater.php';

new GitHub_Updater(
    __FILE__,
    'keramaros',
    'native-image-compression'
);

new KAIC_Image_Compression();

if (defined('WP_CLI') && WP_CLI) {
    require_once KAIC_PLUGIN_DIR_PATH . 'includes/KAIC_CLI.php';
    WP_CLI::add_command('kaic', 'KAIC_CLI');
    WP_CLI::add_command('native-image-compression', 'KAIC_CLI');
}