=== PDF Compressor Pro ===
Contributors: yourname
Tags: pdf, compression, file-size, optimization
Requires at least: 5.0
Tested up to: 6.4
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Professional PDF compression plugin with multiple size options and concurrent user support.

== Description ==

PDF Compressor Pro is a powerful WordPress plugin that allows users to compress PDF files to specific target sizes directly from your website's frontend. Perfect for websites that need to offer PDF optimization services to their visitors.

**Key Features:**

* **Multiple Target Sizes**: Compress PDFs to 150KB, 400KB, 180KB, or custom sizes
* **Frontend Interface**: Beautiful drag-and-drop interface with responsive design
* **Multiple Compression Methods**: Ghostscript (primary), ImageMagick (fallback), PHP-based (backup)
* **Concurrent User Support**: Handle multiple users compressing files simultaneously
* **Security First**: Proper file validation, nonce security, and secure file handling
* **Admin Dashboard**: Track usage statistics and recent compressions
* **Automatic Cleanup**: Files are automatically cleaned up after 24 hours
* **Mobile Responsive**: Works perfectly on both desktop and mobile devices

**Technical Highlights:**

* Uses WordPress hooks and AJAX for seamless integration
* Proper security with nonce verification and file validation
* Database tracking with automatic cleanup
* Memory-efficient processing for large files
* Graceful fallback when compression tools are unavailable

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/pdf-compressor-pro` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Use the Settings->PDF Compressor Pro screen to view usage statistics.
4. Add the shortcode `[pdf_compressor]` to any page or post where you want the compression interface to appear.

== Frequently Asked Questions ==

= What file types are supported? =

Only PDF files are supported. The plugin validates both file extension and MIME type for security.

= What's the maximum file size? =

The plugin supports PDF files up to 50MB in size.

= Do I need special software installed? =

The plugin works best with Ghostscript installed on your server, but includes fallback methods using ImageMagick and PHP-based compression.

= How long are compressed files stored? =

Compressed files are automatically deleted after 24 hours for security and storage management.

= Can multiple users use the compressor simultaneously? =

Yes, the plugin is designed to handle multiple concurrent users without conflicts.

== Screenshots ==

1. Frontend compression interface with drag-and-drop functionality
2. Compression options and target size selection
3. Progress indicator during compression
4. Results display with download option
5. Admin dashboard with usage statistics

== Changelog ==

= 1.0.0 =
* Initial release
* Frontend drag-and-drop interface
* Multiple compression methods
* Admin dashboard with statistics
* Automatic file cleanup
* Mobile responsive design

== Upgrade Notice ==

= 1.0.0 =
Initial release of PDF Compressor Pro.
