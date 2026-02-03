# iDrivee2 Media Upload

WordPress plugin that uploads media files to iDrivee2 (S3-compatible storage), deletes local copies, and serves media from a CDN.

## Features

- **Automatic Upload**: Uploads all media files and generated sizes to S3-compatible storage
- **Local Cleanup**: Deletes local files after successful upload to save disk space
- **CDN Integration**: Rewrites media URLs to serve from custom CDN domain
- **Admin Interface**: Test S3 connection and upload test files from WordPress admin
- **Multisite Support**: Works with WordPress Multisite installations
- **Security First**: Uses WordPress security best practices (nonces, sanitization, WP_Filesystem)

## Requirements

- **WordPress**: 6.8 or higher
- **PHP**: 8.2, 8.3, or 8.4
- **Database**: MariaDB 10.6+ or MySQL 5.7+

## Installation

### Manual Installation

1. Download the latest release ZIP file
2. Upload to WordPress via Plugins → Add New → Upload Plugin
3. Activate the plugin
4. Configure constants in `wp-config.php` (see Configuration below)

### Via Composer

```bash
composer require javiercasares/idrivee2-media-upload
```

## Configuration

Add these constants to your `wp-config.php` file:

```php
// Required constants
define('IDRIVEE2_MEDIA_HOST',   'https://your-s3-endpoint.com');
define('IDRIVEE2_MEDIA_KEY',    'YOUR_ACCESS_KEY_ID');
define('IDRIVEE2_MEDIA_SECRET', 'YOUR_SECRET_ACCESS_KEY');
define('IDRIVEE2_MEDIA_BUCKET', 'your-bucket-name');
define('IDRIVEE2_MEDIA_REGION', 'us-east-1');

// Optional: Custom CDN domain
define('IDRIVEE2_MEDIA_DOMAIN', 'https://cdn.yourdomain.com');
```

**Important**: The `IDRIVEE2_MEDIA_HOST` must start with `https://`.

## Usage

### Testing Configuration

1. Navigate to **Settings → iDrivee2** in WordPress admin
2. Configure your S3 settings (or they will be read from wp-config.php)
3. Click **"Test S3 Connection"** to verify bucket access
4. Click **"Upload Test File"** to test file upload capability
   - Creates a file named `test-YYYYMMDDHHMMSS.txt` with timestamp
   - File remains in S3 and can be accessed via the provided URL
   - You can delete test files individually using the "Delete this file" button

### Uploading Media

1. Go to **Media → Add New**
2. Upload images as normal
3. Files are automatically uploaded to S3 and deleted locally
4. URLs are rewritten to use your CDN domain

## Architecture

### Directory Structure

```
idrivee2-media-upload/
├── assets/
│   └── js/
│       └── admin.js          # Admin page JavaScript
├── bin/
│   └── deploy.sh             # Deployment script
├── includes/
│   ├── class-config.php              # Configuration handler
│   ├── class-s3-client-factory.php   # S3 client factory
│   ├── class-url-rewriter.php        # URL rewriting
│   ├── class-media-uploader.php      # Media upload handler
│   ├── class-admin-page.php          # Admin interface
│   └── class-plugin.php              # Main plugin orchestrator
├── languages/                # Translation files
├── tests/
│   ├── unit/                 # Unit tests
│   ├── integration/          # Integration tests
│   └── bootstrap.php         # PHPUnit bootstrap
├── vendor/                   # Composer dependencies
├── idrivee2-media-upload.php # Main plugin file
├── uninstall.php             # Uninstallation cleanup
├── composer.json             # Composer configuration
├── phpunit.xml               # PHPUnit configuration
├── phpstan.neon              # PHPStan configuration
└── phpcs.xml                 # PHPCS configuration
```

### Classes

- **Config**: Validates and provides access to configuration constants
- **S3_Client_Factory**: Creates configured AWS S3 clients
- **URL_Rewriter**: Rewrites WordPress media URLs to CDN domain
- **Media_Uploader**: Handles file uploads to S3 and local deletion
- **Admin_Page**: Manages admin interface and AJAX handlers
- **Plugin**: Singleton orchestrator with dependency injection

## Development

### Setup Development Environment

```bash
# Install dependencies
composer install

# Run code quality checks
composer lint

# Run tests
composer test
```

### Code Quality Tools

#### PHPCS (WordPress Coding Standards)

```bash
vendor/bin/phpcs
```

#### PHPStan (Static Analysis)

```bash
vendor/bin/phpstan analyse
```

#### PHP Compatibility Check

```bash
vendor/bin/phpcs --standard=PHPCompatibilityWP --runtime-set testVersion 8.2-
```

#### PHPUnit Tests

```bash
vendor/bin/phpunit
```

### Deployment

```bash
./bin/deploy.sh
```

This creates a production-ready ZIP file with:
- Production dependencies only (`composer install --no-dev`)
- Excludes: `vendor/` dev dependencies, tests, docs, development files
- Ready for WordPress.org or manual installation

## Security

This plugin follows WordPress security best practices:

- **Nonces**: All AJAX requests validate nonces
- **Capabilities**: Admin functions require `manage_options` capability
- **Sanitization**: All input is sanitized using WordPress functions
- **Escaping**: All output is escaped properly
- **WP_Filesystem**: All file operations use WP_Filesystem API
- **Type Safety**: PHP 8.2+ strict types enabled

## Important Notes

⚠️ **Data Loss Warning**: This plugin **deletes local media files** after uploading to S3. Ensure your S3 configuration is correct before activation.

📌 **Public Access**: Files are uploaded with `public-read` ACL. All uploaded files are publicly accessible.

🔒 **GUID Updates**: The plugin updates attachment GUIDs to S3 URLs. This is permanent and cannot be easily reversed.

## Compatibility

- ✅ WordPress 6.7, 6.8, 6.9
- ✅ PHP 8.2, 8.3, 8.4
- ✅ MariaDB 10.6+
- ✅ WordPress Multisite

## Support

- **Issues**: https://github.com/javiercasares/idrivee2-media-upload/issues
- **Documentation**: See CLAUDE.md and AGENTS.md for developer documentation

## License

GPL-2.0-or-later

## Author

Javier Casares - https://www.javiercasares.com/

## Changelog

### 0.3.0 (2025-01-XX)
- Complete refactoring to class-based architecture
- Separated classes into individual files
- Added PHPUnit tests
- Added PHPStan static analysis
- Created deployment script
- Updated to WordPress coding standards
- Fixed hardcoded JS version

### 0.1.13
- Initial public release
