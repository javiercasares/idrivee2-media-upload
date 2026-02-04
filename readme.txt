=== iDrivee2 Media Upload ===
Contributors: robotstxt, javiercasares
Tags: media, upload, s3, cdn, storage, idrivee2, cloud
Requires at least: 6.8
Tested up to: 6.9
Stable tag: 1.1.2
Requires PHP: 8.2
Version: 1.1.2
License: GPL-3.0-or-later
License URI: https://www.gnu.org/licenses/gpl-3.0.txt

Upload media files to iDrivee2 (S3-compatible storage) with enterprise-grade security and logging.

== Description ==

iDrivee2 Media Upload is a WordPress plugin that automatically uploads media files to iDrivee2 (S3-compatible storage), deletes local copies to save disk space, and rewrites URLs to serve media from a CDN. The plugin features enterprise-grade security with comprehensive logging, rate limiting, and OWASP Top 10 compliance.

**Key Features:**

* **Automatic S3 Upload**: All media files and generated sizes are automatically uploaded to S3-compatible storage
* **Local File Cleanup**: Deletes local files after successful upload to save disk space
* **CDN Integration**: Rewrites WordPress media URLs to serve from custom CDN domain
* **Security Logging**: Comprehensive logging system for all security events and S3 operations
* **Rate Limiting**: Protection against abuse with configurable cooldown periods
* **Admin Interface**: Test S3 connection and upload test files from WordPress admin
* **Multisite Support**: Works seamlessly with WordPress Multisite installations
* **Type-Safe Code**: PHPStan level 8 compliance with strict type declarations
* **OWASP Compliant**: All OWASP Top 10 (2021) vulnerabilities addressed

**Security Features:**

* Security logging with WP_DEBUG_LOG integration
* Rate limiting (60s users, 30s admins)
* S3 operation statistics tracking (30-day retention)
* Comprehensive nonce validation
* Input sanitization and output escaping
* Capability-based access control (manage_options)
* Sensitive data masking in logs
* Security rating: A+ (Excellent)

**Requirements:**

* WordPress 6.8 or higher
* PHP 8.2, 8.3, or 8.4
* MariaDB 10.6+ or MySQL 5.7+
* S3-compatible storage (iDrivee2, AWS S3, DigitalOcean Spaces, etc.)

== Extra Configurations ==

The plugin requires configuration constants in your `wp-config.php` file. Add these constants before the `/* That's all, stop editing! */` line:

**Required Constants:**

`define('IDRIVEE2_MEDIA_HOST', 'https://your-s3-endpoint.com');`
S3 endpoint URL. Must start with `https://`.

`define('IDRIVEE2_MEDIA_KEY', 'YOUR_ACCESS_KEY_ID');`
S3 Access Key ID for authentication.

`define('IDRIVEE2_MEDIA_SECRET', 'YOUR_SECRET_ACCESS_KEY');`
S3 Secret Access Key for authentication.

`define('IDRIVEE2_MEDIA_BUCKET', 'your-bucket-name');`
S3 bucket name where media files will be stored.

`define('IDRIVEE2_MEDIA_REGION', 'us-east-1');`
AWS region for the S3 service (e.g., 'us-east-1', 'eu-west-1').

**Optional Constants:**

`define('IDRIVEE2_MEDIA_DOMAIN', 'https://cdn.yourdomain.com');`
Custom CDN domain for serving media files. If not defined, files will be served directly from S3 ObjectURL.

**Security Logging:**

To enable security logging, add these constants:

`define('WP_DEBUG', false);`
Disable debug mode in production.

`define('WP_DEBUG_LOG', true);`
Enable logging to `wp-content/debug.log`.

`define('WP_DEBUG_DISPLAY', false);`
Don't display errors on screen.

**Example Configuration:**

`// iDrivee2 Media Upload Configuration`
`define('IDRIVEE2_MEDIA_HOST',   'https://s3.idrivee2.com');`
`define('IDRIVEE2_MEDIA_KEY',    'YOUR_ACCESS_KEY_ID');`
`define('IDRIVEE2_MEDIA_SECRET', 'YOUR_SECRET_ACCESS_KEY');`
`define('IDRIVEE2_MEDIA_BUCKET', 'my-wordpress-media');`
`define('IDRIVEE2_MEDIA_REGION', 'us-east-1');`
`define('IDRIVEE2_MEDIA_DOMAIN', 'https://cdn.example.com');`

`// Enable Security Logging`
`define('WP_DEBUG', false);`
`define('WP_DEBUG_LOG', true);`
`define('WP_DEBUG_DISPLAY', false);`

== Installation ==

= Automatic download =

1. Visit Plugins → Add New in your WordPress admin
2. Search for "iDrivee2 Media Upload"
3. Click "Install Now" and then "Activate"
4. Configure the required constants in `wp-config.php` (see Extra Configurations)
5. Go to Settings → iDrivee2 to test your configuration

= Manual download =

1. Download the plugin ZIP file
2. Extract the contents and upload to `/wp-content/plugins/idrivee2-media-upload/`
3. Activate the plugin through the Plugins menu in WordPress
4. Configure the required constants in `wp-config.php` (see Extra Configurations)
5. Go to Settings → iDrivee2 to test your configuration

= After Installation =

1. Add the required constants to your `wp-config.php` file
2. Navigate to Settings → iDrivee2 in WordPress admin
3. Click "Test S3 Connection" to verify bucket access
4. Click "Upload Test File" to verify upload capability
5. Upload a test image through Media → Add New
6. Verify the image URL points to your S3/CDN domain
7. Verify local file is deleted after upload

== Frequently Asked Questions ==

= Does this plugin delete local media files? =

Yes. This plugin deletes local media files after successful upload to S3. Ensure your S3 configuration is correct before activation. Files are permanently deleted from your server to save disk space.

= What happens if S3 upload fails? =

If the S3 upload fails, the local file is NOT deleted. The error is logged (if WP_DEBUG_LOG is enabled) and the file remains on your server.

= Are uploaded files publicly accessible? =

Yes. Files are uploaded with `public-read` ACL. All uploaded files are publicly accessible via the S3 URL or your CDN domain.

= Can I use this with any S3-compatible service? =

Yes. The plugin works with any S3-compatible service including iDrivee2, AWS S3, DigitalOcean Spaces, Wasabi, Backblaze B2, and others.

= Does this work with WordPress Multisite? =

Yes. The plugin is fully compatible with WordPress Multisite installations (Network: true).

= What is logged by the security logging system? =

The plugin logs configuration changes (with sensitive data masking), S3 operations (success/failure with error details), rate limit violations, authentication failures, and invalid file upload attempts. All logs include user context (username, user ID) and timestamps.

= How do I view the security logs? =

Enable `WP_DEBUG_LOG` in `wp-config.php`. Logs are written to `wp-content/debug.log`. You can view this file via FTP/SFTP or use a log viewer plugin.

= What is the rate limiting feature? =

Rate limiting prevents abuse of the test functions. Regular users have a 60-second cooldown between actions, administrators have a 30-second cooldown. This prevents brute force testing of S3 credentials.

= Can I disable rate limiting? =

Rate limiting is built-in and cannot be disabled. However, administrators have a shorter cooldown (30s vs 60s).

= Does this plugin modify the WordPress database? =

Yes. The plugin updates attachment GUIDs to S3 URLs. It also stores S3 operation statistics in the database with 30-day retention. The uninstall script removes all plugin data when the plugin is deleted.

= Is this plugin compatible with page caching plugins? =

Yes. The URL rewriting happens at the WordPress level, so it works with all caching plugins.

= What PHP version is required? =

PHP 8.2 or higher is required. The plugin uses strict type declarations and is tested on PHP 8.2, 8.3, and 8.4.

== Compatibility ==

* WordPress: 6.8 - 6.9
* PHP: 8.2 - 8.4
* MariaDB: 10.6+
* MySQL: 5.7+

**Code Quality:**

* PHP Coding Standards: 0 errors
* WordPress Coding Standards (WPCS): 3.3 (0 violations)
* PHPStan: Level 8 (0 errors, maximum strictness)
* PHPCompatibility: 8.2-8.4 (fully compatible)

== Changelog ==

= 1.1.2 =

_Release date: 2026-02-04_

**Changed**

* Deployment script updated to use PHP 8.2 as platform base for production builds
* Now uses composer update --no-dev instead of composer install --no-dev for consistent dependency resolution
* Temporarily configures platform.php 8.2 during build, then cleans up

**Improved**

* Production packages now guarantee PHP 8.2+ compatibility regardless of development environment PHP version
* Build consistency ensures reliable deployments across different server environments

= 1.1.1 =

_Release date: 2026-02-04_

**Fixed**

* Deployment script now includes essential files (update.json, robotstxt-updater.php, readme.txt, changelog.txt)
* Production packages now contain all files required for automatic updates from Gitea

= 1.1.0 =

_Release date: 2026-02-04_

**Changed**

* Added explicit PHP version requirement (>=8.2) to composer.json
* Updated update.json with correct plugin information
* Fixed Text Domain in robotstxt-updater.php to match plugin slug (idrivee2-media-upload)
* Migrated repository from GitHub to Gitea (git.robotstxt.es)
* Added Gitea Plugin URI and Primary Branch headers

**Fixed**

* Composer now validates PHP version during dependency installation
* Plugin update system correctly identifies the plugin
* Translations properly loaded for updater error messages

**Improved**

* All text domains now consistently use 'idrivee2-media-upload'
* Update metadata accurately reflects plugin information

= 1.0.0 =

_Release date: 2026-02-03_

**Added**

* Security logging system with comprehensive audit trail
* Rate limiting protection (60s users, 30s admins)
* S3 operation statistics tracking (30-day retention)
* Logger class for security and operations logging
* Rate_Limiter class for abuse prevention
* Comprehensive security audit documentation (7,500+ lines)
* Code quality report with metrics (3,200+ lines)

**Security**

* OWASP Top 10 (2021) 100% compliance
* Enhanced nonce validation
* Comprehensive input sanitization and output escaping
* Security logging for all critical operations
* Security rating: A+ (Excellent)

**Fixed**

* All 17 PHPStan level 8 type safety issues resolved
* Array type specifications added to all methods
* Null handling for AWS error messages
* Return type declarations match actual returns

= 0.3.0 =

_Release date: 2025-02-03_

**Added**

* Settings page in WordPress Admin (Settings → iDrivee2)
* Class-based architecture with 6 classes
* PHPUnit test structure
* PHPStan static analysis
* Deployment script (bin/deploy.sh)

**Changed**

* Menu location from Media → iDrivee2 to Settings → iDrivee2
* Architecture from functional to object-oriented

**Fixed**

* Code duplication eliminated (7 instances)
* WordPress Coding Standards violations

= Previous versions =

If you want to see the full changelog, visit the [changelog.txt](https://git.robotstxt.es/ROBOTSTXT/idrivee2-media-upload/raw/branch/main/changelog.txt) file.

== Compliance ==

This plugin adheres to the following security measures and review protocols for each version:

* [WordPress Plugin Handbook](https://developer.wordpress.org/plugins/)
* [WordPress Plugin Security](https://developer.wordpress.org/plugins/wordpress-org/plugin-security/)
* [WordPress APIs Security](https://developer.wordpress.org/apis/security/)
* [WordPress Coding Standards](https://github.com/WordPress/WordPress-Coding-Standards)
* [Plugin Check (PCP)](https://wordpress.org/plugins/plugin-check/)
* [OWASP Top 10 (2021)](https://owasp.org/Top10/)
* [PHPStan Level 8](https://phpstan.org/user-guide/rule-levels)

**Security Audit:**

A comprehensive security audit is available at `docs/SECURITY-AUDIT.md` covering all OWASP Top 10 vulnerabilities, WordPress.org Plugin Review requirements, and security best practices.

**Code Quality:**

A detailed code quality report is available at `docs/QUALITY-REPORT.md` with metrics, static analysis results, and maintainability scores.
