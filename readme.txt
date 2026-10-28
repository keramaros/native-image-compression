=== Native Image Compression ===
Contributors: keramaros
Donate link: https://keramaros.gr
Tags: image compression, compress images, optimize images, image optimizer, media library
Requires at least: 5.8
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.6
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Shrink image file sizes by automatically resizing dimensions and adjusting quality for faster web performance.

== Description ==

Native Image Compression is a powerful, lightweight native image optimization plugin for WordPress that helps you bulk compress images in your media library. It resizes oversized images, optimizes compression quality, strips unnecessary metadata, quantizes color palettes, generates modern WebP images, and provides granular compression controls.

Key Features:
* Bulk compress all images in your media library with real-time stats and savings analytics.
* Lossy and Lossless compression mode options.
* Next-Gen WebP image generation with optional original fallback retention.
* EXIF and metadata stripping with optional ICC color profile and copyright removal.
* Granular dimensional scaling with customizable maximum width and height.
* Thumbnail compression controls to selectively optimize registered image sizes.
* Format-specific optimizations: PNG quantization (PNG-8), Progressive JPEG encoding, and 4:2:0 Chroma subsampling.
* Automatic background compression upon uploading new media.
* Backup pristine copies of original images before compression.
* Error logging mechanism with log viewer and reset capabilities.
* Danger Zone feature with one-click cleanup of all plugin data and metadata from the database.
* Full WP-CLI support.

== Installation ==

1. Upload the `native-image-compression` folder to the `/wp-content/plugins/` directory, or install the plugin directly through the WordPress plugins screen.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Navigate to **Media > Bulk Compression** to start optimizing your images or **Settings > Native Image Compression** to adjust compression parameters.

== Frequently Asked Questions ==

= Does this plugin modify original images? =
The plugin resizes and optimizes image files in place if the compressed version is smaller than the original. Original files can be backed up automatically if the Backup Originals setting is enabled.

= Can I choose the compression quality and mode? =
Yes, you can select between Lossy and Lossless compression strategies and adjust quality levels under **Settings > Native Image Compression**.