# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

First: Read AGENTS.md

## Project Overview

This is a WordPress plugin that uploads media files to iDrivee2 (S3-compatible storage). The plugin intercepts WordPress media uploads, pushes files to an S3-compatible bucket, deletes local copies, and rewrites URLs to serve media from the CDN.

## Architecture

### Single-File Architecture

This plugin uses a **functional, single-file architecture** with all code in `idrivee2-media-upload.php`. Functions are organized under the `iDrivee2Media` namespace. There are no classes, no `/src` directory structure, and no custom modules.

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
- Development tools: PHPCS, WPCS, PHPCompatibility

### Linting

Run PHPCS to validate code against WordPress Coding Standards:

```bash
vendor/bin/phpcs
```

The plugin follows WordPress-Core, WordPress-Docs, and WordPress-Extra standards. **Always run PHPCS after making code changes.**

Check PHP compatibility (PHP 8.2-8.4):

```bash
vendor/bin/phpcs --standard=PHPCompatibilityWP --runtime-set testVersion 8.2-
```

### Deployment

According to AGENTS.md, a `bin/deploy.sh` script should exist to package the plugin for distribution. **This script is currently missing and must be created.**

The deploy script should:
- Read version from plugin headers
- Generate a ZIP file in the parent directory (`wp-content/plugins/`)
- Exclude: `vendor/` dev dependencies, `.git`, `.gitignore`, `composer.json`, `composer.lock`, `docs/`, `.claude/`, `.codex/`
- Include: `vendor/` production dependencies (AWS SDK with `--no-dev`)

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

## Known Issues & TODO

1. **Missing `bin/deploy.sh`**: Deployment script required by AGENTS.md does not exist
2. **No automated tests**: No PHPUnit, integration tests, or CI configuration
3. **No uninstall.php**: Required by AGENTS.md for data cleanup on uninstall
4. **Hardcoded version in JS**: `admin.js` version is hardcoded as `'0.1.13'` in `enqueue_admin_scripts()` but plugin is at `0.3.0`

## Important Notes

- This plugin **deletes local media files** after uploading to S3. Ensure S3 configuration is correct before activation.
- The plugin sets `ACL` to `public-read` on all uploads. Files are publicly accessible.
- GUID is updated to the S3 `ObjectURL` in the database.
- Plugin preserves relative paths in `_wp_attached_file` for compatibility.
