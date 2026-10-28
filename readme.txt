=== Native Image Compression ===
Contributors: keramaros
Donate link: https://keramaros.gr
Tags: image compression, compress images, optimize images, image optimizer, media library
Requires at least: 5.8
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.5
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Shrink image file sizes by automatically resizing dimensions and adjusting quality for faster web performance.

== Description ==

Native Image Compression is an image optimization plugin for WordPress that helps you bulk compress images in your media library. It resizes oversized images to configured maximum dimensions and optimizes image quality using WordPress built-in image editors.

Key Features:
* Bulk compress all images in your media library.
* Customizable maximum dimensions and image quality settings.
* Live progress indicator with detailed compression statistics and savings.
* Error logging mechanism with log viewer and reset capabilities.

== Installation ==

1. Upload the `native-image-compression` folder to the `/wp-content/plugins/` directory, or install the plugin directly through the WordPress plugins screen.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Navigate to **Media > Bulk Compression** to start optimizing your images or **Settings > Native Image Compression** to adjust compression parameters.

== Frequently Asked Questions ==

= Does this plugin modify original images? =
The plugin resizes and optimizes image files in place if the compressed version is smaller than the original.

= Can I choose the compression quality? =
Yes, you can adjust the compression quality and maximum dimensions under **Settings > Native Image Compression**.