# Native Image Compression

**Native Image Compression** is a high-performance, native image optimization and compression plugin for WordPress. It shrinks media file sizes by resizing oversized dimensions, adjusting compression quality, stripping unnecessary metadata, quantizing color palettes, generating next-gen WebP formats, and providing fine-grained optimization controls without relying on third-party cloud services or external API keys.

---

## Key Features

- **Lossy & Lossless Compression Modes:** Choose between maximum byte reduction (Lossy) or pixel-perfect preservation (Lossless).
- **Next-Gen WebP Conversion:** Automatically generate lightweight WebP copies of JPEG and PNG uploads with optional fallback retention.
- **EXIF & Metadata Stripping:** Remove non-visual metadata (GPS tags, camera profiles, timestamps) with granular ICC color profile and copyright removal controls.
- **Granular Dimensional Scaling:** Downscale oversized uploads exceeding configurable maximum width or height limits before compression.
- **Thumbnail Compression:** Selectively choose which registered thumbnail sizes should be compressed (all included by default).
- **Format-Specific Optimizations:**
  - **PNG Palette Quantization (PNG-8):** Reduce 24/32-bit PNGs to 8-bit indexed palettes for 60–80% size savings on graphics.
  - **Progressive JPEG Encoding:** Render JPEGs progressively for faster perceived load times on mobile connections.
  - **Chroma Subsampling (4:2:0):** Subsample color channels on photographic JPEGs for extra compression savings.
- **Automatic Compression on Upload:** Automatically compress newly uploaded media in the background upon upload.
- **Original File Backups:** Store pristine backup copies of original files in `wp-content/uploads/kaic-backups/` before compression.
- **Bulk Compression Dashboard:** Optimize existing media library images with real-time progress, live metrics, and savings analytics.
- **WP-CLI Support:** Full command-line interface for headless automation, cron jobs, and server scripts.
- **Error Logging:** Built-in error log viewer and diagnostic tools for troubleshooting.

---

## Requirements

- **WordPress:** 5.8 or higher
- **PHP:** 7.4 or higher (supports PHP 8.0, 8.1, 8.2, 8.3, 8.4)
- **Image Editor:** GD Library or Imagick PHP extension

---

## Installation

1. Upload the `native-image-compression` folder to your `/wp-content/plugins/` directory (or install via the WordPress Plugins dashboard).
2. Activate the plugin through the **Plugins** menu in WordPress.
3. Configure compression options under **Settings > Native Image Compression**.
4. Run bulk compression from **Media > Bulk Compression** (or via WP-CLI).

---

## Compression Settings & Configuration

Navigate to **Settings > Native Image Compression** in your WordPress admin dashboard to configure the following settings:

### 1. Compression Mode & Quality
- **Compression Strategy (`kaic_compression_mode`):**
  - **Lossy Compression (Recommended):** Delivers maximum file size reduction with minor, imperceptible quality adjustments tailored for fast web delivery.
  - **Lossless Compression:** Preserves 100% exact pixel fidelity while optimizing file structures and internal metadata. Ideal for logos, diagrams, and print assets.
- **Image Quality Level (`kaic_quality`):**
  - Set compression quality from 1 to 100 (default: `70`). Higher values yield greater visual fidelity with larger file sizes. Applied when Lossy mode is selected.

### 2. Next-Generation Format Conversion (WebP)
- **WebP Generation (`kaic_enable_webp`):**
  - Turn On/Off (default: `Off`). Automatically converts and generates modern WebP copies of uploaded JPEG and PNG files. WebP files are typically 25–35% smaller than standard JPEGs at equivalent quality.
- **Preserve Original Formats (`kaic_keep_original_formats`):**
  - Turn On/Off (default: `On`). Keeps original JPEG and PNG files on disk alongside WebP images to maintain compatibility with older browsers. Uncheck to replace originals and reduce server disk usage.

### 3. Metadata & EXIF Stripping
- **Strip EXIF & Metadata (`kaic_strip_exif`):**
  - Turn On/Off (default: `Off`). Removes non-visual metadata (camera models, GPS coordinates, timestamps, and embedded thumbnails) from image files, trimming file sizes and protecting user privacy.
- **Remove ICC Profiles & Copyright (`kaic_remove_icc_profile`):**
  - Turn On/Off (default: `Off`). Removes embedded ICC color profiles and creator copyright tags for additional file size reduction. Keep unchecked to maintain exact color accuracy across varied screens and preserve creator copyright attribution.

### 4. Dimensional Scaling & Thumbnail Compression
- **Dimensional Scaling (`kaic_enable_resize`):**
  - Turn On/Off (default: `On`). Automatically downscales oversized uploaded images exceeding the maximum width or height thresholds prior to compression.
- **Maximum Width (`kaic_max_width`):**
  - The maximum allowed width in pixels (default: `2048`). Images wider than this threshold are proportionally scaled down.
- **Maximum Height (`kaic_max_height`):**
  - The maximum allowed height in pixels (default: `2048`). Images taller than this threshold are proportionally scaled down.
- **Compress Thumbnail Sizes (`kaic_compressed_sizes`):**
  - Select which registered intermediate WordPress thumbnail sizes (e.g., `thumbnail`, `medium`, `large`, custom theme sizes) should be compressed (all included by default). Uncheck sizes to skip them and save server processing time.

### 5. Format-Specific Optimizations
- **PNG Color Quantization (`kaic_enable_png_quant`):**
  - Turn On/Off (default: `On`). Converts 24/32-bit PNG images to 8-bit indexed palettes when possible while preserving transparency, yielding 60–80% size savings on illustrations, logos, and UI graphics.
- **Progressive JPEG Encoding (`kaic_progressive_jpeg`):**
  - Turn On/Off (default: `On`). Encodes JPEGs progressively so visitors see an immediate low-resolution preview that sharpens as data streams in, improving perceived page speed on mobile networks.
- **Chroma Subsampling (`kaic_chroma_subsampling`):**
  - Turn On/Off (default: `On`). Enables 4:2:0 chroma subsampling for JPEG compression, reducing color resolution where the human eye is least sensitive for extra byte savings with no perceptible visual loss.

### 6. Automation & Backup Controls
- **Auto-Compress on Upload (`kaic_auto_compress`):**
  - Turn On/Off (default: `Off`). Hooks into WordPress media upload generation (`wp_generate_attachment_metadata`) to automatically optimize images immediately when uploaded.
- **Backup Originals (`kaic_enable_backup`):**
  - Turn On/Off (default: `Off`). Saves an untouched backup copy of original uncompressed files in `wp-content/uploads/kaic-backups/` before compression, enabling safe rollbacks if needed.

### 7. Error Log & Maintenance
- **View Error Log:** Open the diagnostic error log to inspect any issues during image processing.
- **Clear Log:** Reset and clear the error log file.

### 8. Danger Zone
- **Clear All Plugin Data from Database:** Permanently deletes all plugin options and attachment compression metadata (`_kaic_is_compressed`, file sizes, and error records) from the database, allowing complete reconfiguration and fresh re-compression of the media library. Image files on disk remain untouched.

---

## WP-CLI Commands

Native Image Compression includes full WP-CLI command integration:

```bash
# Bulk compress all uncompressed media library images
wp kaic compress

# Compress a specific number of images
wp kaic compress --limit=25

# Output compression results in table or JSON format
wp kaic compress --format=table
wp kaic compress --format=json

# Fetch next uncompressed image metadata
wp kaic get-next-image

# Compress a single image attachment by ID
wp kaic process-image 123

# Display media library compression statistics and total savings
wp kaic stats

# Clear all plugin options and compression metadata from the database
wp kaic clear-data --yes
```

---

## Frequently Asked Questions

#### Does this plugin modify original image files?
The plugin optimizes images in-place only when the compressed version results in a smaller file size than the original. If **Backup Originals** is enabled, a copy of the original file is preserved in `wp-content/uploads/kaic-backups/`.

#### Are SVG files compressed?
No, vector SVG images are automatically detected and safely skipped from raster compression routines.

#### Can I switch between Lossy and Lossless compression?
Yes, under **Settings > Native Image Compression**, you can choose your compression strategy (Lossy for highest bandwidth savings, Lossless for pixel-perfect fidelity).

---

## License

This plugin is licensed under the GNU General Public License v2.0 or later - see the [LICENSE](LICENSE) file for details.
