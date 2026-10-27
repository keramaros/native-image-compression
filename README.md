# Native Image Compression

**Native Image Compression** is a lightweight, efficient image optimization plugin for WordPress that helps you bulk compress and optimize images in your media library. It resizes oversized images to configured maximum dimensions and optimizes image quality using WordPress's built-in image editors for faster web performance.

---

## Features

- **Bulk Compression:** Optimize all existing images in your media library in batch.
- **Customizable Dimensions & Quality:** Define maximum width, height, and compression quality settings.
- **Live Progress & Stats:** Real-time progress bar with detailed statistics and storage savings breakdown.
- **Error Logging:** Built-in logging mechanism with dedicated log viewer and reset capabilities.
- **Native Processing:** Leverages WordPress core image editing libraries without relying on third-party cloud services or API keys.

---

## Requirements

- **WordPress:** 5.8 or higher
- **PHP:** 7.4 or higher

---

## Installation

1. Upload the `native-image-compression` folder to your `/wp-content/plugins/` directory (or install via the WordPress Plugins dashboard).
2. Activate the plugin through the **Plugins** menu in WordPress.
3. Configure settings under **Settings > Native Image Compression**.
4. Run bulk optimization from **Media > Bulk Compression**.

---

## Usage

### Adjusting Compression Settings
Navigate to **Settings > Native Image Compression** to configure:
- **Maximum Width / Height:** Set maximum dimensions for media images.
- **Image Quality:** Set JPEG/WebP compression quality (1–100).

### Bulk Optimizing Media
Navigate to **Media > Bulk Compression**:
- Click **Start Compression** to initiate optimization.
- View real-time progress, processed images count, and total space saved.
- Check the error log tab if any individual image fails to process.

---

## Frequently Asked Questions

#### Does this plugin modify original images?
Yes, the plugin resizes and optimizes image files in-place if the compressed version results in a smaller file size than the original.

#### Can I customize the compression quality?
Yes, you can adjust compression quality and maximum dimensions under **Settings > Native Image Compression**.

---

## License

This project is licensed under the GNU General Public License v2.0 or later - see the [LICENSE](LICENSE) file for details.
