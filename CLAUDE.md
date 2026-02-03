# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

First: Read AGENTS.md

## Project Overview

This is a WordPress plugin that uploads media files to iDrivee2 (S3-compatible storage). The plugin intercepts WordPress media uploads, pushes files to an S3-compatible bucket, deletes local copies, and rewrites URLs to serve media from the CDN.

## Architecture

### Class-Based Modular Architecture

This plugin uses a **class-based modular architecture** following WordPress best practices. Classes are organized in separate files under the `includes/` directory with the `iDrivee2Media` namespace.

### File Structure

```
idrivee2-media-upload/
├── assets/js/              - JavaScript files
├── includes/               - PHP classes
│   ├── class-config.php
│   ├── class-s3-client-factory.php
│   ├── class-url-rewriter.php
│   ├── class-media-uploader.php
│   ├── class-admin-page.php
│   └── class-plugin.php
├── languages/              - Translation files
├── tests/                  - PHPUnit tests
│   ├── unit/
│   ├── integration/
│   └── bootstrap.php
└── vendor/                 - Composer dependencies
```

### Core Workflow

1. **Upload Interception**: Hooks into `wp_generate_attachment_metadata` and `wp_update_attachment_metadata`
2. **S3 Upload**: The `upload_attachment_to_idrivee2()` function uploads the original file and all generated sizes to S3
3. **Local Deletion**: Local files are deleted via `WP_Filesystem` after successful upload
4. **URL Rewriting**: Filters like `wp_get_attachment_url` and `pre_option_upload_url_path` rewrite URLs to the CDN domain

### Configuration

The plugin requires five constants defined in `wp-config.php`:
- `IDRIVEE2_MEDIA_HOST` - S3 endpoint URL (must start with `https://`)
- `IDRIVEE2_MEDIA_KEY` - Access key ID
- `IDRIVEE2_MEDIA_SECRET` - Secret access key
- `IDRIVEE2_MEDIA_BUCKET` - Bucket name
- `IDRIVEE2_MEDIA_REGION` - AWS region
- `IDRIVEE2_MEDIA_DOMAIN` (optional) - Custom CDN domain for serving files

### Admin Interface

Located under Media → iDrivee2 in WordPress admin. Displays configuration status and provides two test buttons:
- **Test S3 Connection**: Performs a `headBucket` call via AJAX
- **Upload Test File**: Uploads a test file via AJAX

JavaScript in `assets/admin.js` handles AJAX interactions.

## Development Commands

### Install Dependencies

```bash
composer install
```

This installs:
- `aws/aws-sdk-php` - For S3 operations
- Development tools: PHPCS, WPCS, PHPCompatibility, PHPUnit, PHPStan

### Code Quality

Run all code quality checks:

```bash
composer lint
```

Run PHPCS to validate code against WordPress Coding Standards:

```bash
vendor/bin/phpcs
# or
composer phpcs
```

Run PHPStan for static analysis:

```bash
vendor/bin/phpstan analyse
# or
composer phpstan
```

Check PHP compatibility (PHP 8.2-8.4):

```bash
vendor/bin/phpcs --standard=PHPCompatibilityWP --runtime-set testVersion 8.2-
```

### Testing

Run PHPUnit tests:

```bash
vendor/bin/phpunit
# or
composer test
```

### Deployment

Use the deployment script to package the plugin:

```bash
./bin/deploy.sh
```

The deploy script:
- Reads version from plugin headers
- Generates a ZIP file in the parent directory
- Excludes dev dependencies, tests, and development files
- Includes production dependencies only (`composer install --no-dev`)

## Key Constraints

### File System Operations

- **ALWAYS use `WP_Filesystem` for file operations**, never `fopen()`, `file_get_contents()`, `file_put_contents()`, or `unlink()`
- Initialize with `WP_Filesystem()` then use `global $wp_filesystem`
- Examples in code: `$wp_filesystem->get_contents()`, `$wp_filesystem->exists()`, `$wp_filesystem->delete()`

### Security

- All AJAX endpoints use `check_ajax_referer()` with `'idrivee2_test_nonce'`
- File uploads validate `$_FILES` and use `is_uploaded_file()`
- All user input is sanitized with `sanitize_text_field()`, `sanitize_file_name()`, `wp_unslash()`
- Output is escaped with `esc_html()`, `esc_attr()`, `esc_url()`
- Capability checks use `manage_options`

### WordPress APIs

- Uses WordPress database APIs (no direct SQL)
- Internationalization via `__()` and `esc_html_e()` with text domain `'idrivee2-media-upload'`
- Nonces for AJAX: `wp_create_nonce()` and `check_ajax_referer()`
- Options API: `update_post_meta()`, `wp_get_attachment_metadata()`

## Testing

### Manual Testing

1. Configure constants in `wp-config.php`
2. Navigate to Media → iDrivee2 in WordPress admin
3. Click "Test S3 Connection" to verify bucket access
4. Click "Upload Test File" to verify upload capability
5. Upload an image through Media → Add New
6. Verify the image URL points to the S3 domain
7. Verify local file is deleted after upload

### Compatibility Testing

Per AGENTS.md, test on:
- **WordPress**: 6.7, 6.8, 6.9
- **PHP**: 8.2, 8.3, 8.4
- **MariaDB**: 10.6+
- **Both single-site and Multisite** (plugin header declares `Network: true`)

Check `wp-content/debug.log` and browser console for errors. No PHP notices, warnings, or deprecated messages are allowed.

## Completed Improvements (v0.3.0)

1. ✅ **Class-based architecture**: Refactored from functional to OOP with dependency injection
2. ✅ **Modular file structure**: Classes separated into individual files under `includes/`
3. ✅ **Deployment script**: Created `bin/deploy.sh` for packaging releases
4. ✅ **Automated tests**: Added PHPUnit test structure with example unit tests
5. ✅ **Uninstall script**: Created `uninstall.php` for cleanup on uninstall
6. ✅ **Fixed JS version**: Updated from hardcoded `0.1.13` to dynamic `0.3.0`
7. ✅ **PHPStan integration**: Added static analysis configuration
8. ✅ **Composer scripts**: Added `test`, `phpcs`, `phpstan`, and `lint` commands

## TODO

1. **Expand test coverage**: Add more unit and integration tests
2. **CI/CD pipeline**: Add GitHub Actions for automated testing
3. **WordPress.org assets**: Create banner and icon images
4. **Performance testing**: Test with large media libraries

## Important Notes

- This plugin **deletes local media files** after uploading to S3. Ensure S3 configuration is correct before activation.
- The plugin sets `ACL` to `public-read` on all uploads. Files are publicly accessible.
- GUID is updated to the S3 `ObjectURL` in the database.
- Plugin preserves relative paths in `_wp_attached_file` for compatibility.
