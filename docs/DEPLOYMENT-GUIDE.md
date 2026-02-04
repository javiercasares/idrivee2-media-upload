# Deployment Guide - v1.0.0

**Plugin:** iDrivee2 Media Upload
**Version:** 1.0.0
**Date:** 2026-02-03
**Status:** ✅ Production Ready

---

## Quick Start

### Generate Production Package

```bash
./bin/deploy.sh
```

This creates: `../idrivee2-media-upload-1.0.0.zip`

---

## What's Included in the ZIP

The deployment script creates a **clean, production-only** ZIP file containing:

### Core Files ✅
- `idrivee2-media-upload.php` - Main plugin file (v1.0.0)
- `uninstall.php` - Cleanup script
- `LICENSE` - GPL-2.0-or-later

### Directories ✅
- `includes/` - 8 PHP classes
  - `class-logger.php`
  - `class-rate-limiter.php`
  - `class-config.php`
  - `class-s3-client-factory.php`
  - `class-url-rewriter.php`
  - `class-media-uploader.php`
  - `class-admin-page.php`
  - `class-plugin.php`
- `assets/` - JavaScript, CSS
- `languages/` - Translation files (or empty directory)
- `vendor/` - AWS SDK (production only, no dev dependencies)

### Auto-Generated Files ✅
- `readme.txt` - WordPress.org format (generated automatically)

---

## What's Excluded from the ZIP

The deployment script **automatically excludes** all development files:

### Documentation ❌
- `*.md` files (README.md, CHANGELOG.md, AGENTS.md, CLAUDE.md)
- `docs/` directory (SECURITY-AUDIT.md, QUALITY-REPORT.md)

### Development Tools ❌
- `tests/` - Unit and integration tests
- `bin/` - Deployment and build scripts
- `phpunit.xml` - PHPUnit configuration
- `phpstan.neon` - PHPStan configuration
- `phpcs.xml` - PHPCS configuration
- `composer.json` - Composer configuration
- `composer.lock` - Composer lock file

### Version Control ❌
- `.git/` - Git repository
- `.gitignore`
- `.gitattributes`
- `.github/` - GitHub Actions workflows

### IDE Configurations ❌
- `.vscode/` - Visual Studio Code
- `.idea/` - PHPStorm/IntelliJ
- `.DS_Store` - macOS

---

## Deployment Script Features

### 1. Production Dependencies Only

```bash
composer install --no-dev --optimize-autoloader
```

- Installs only AWS SDK (production dependency)
- Excludes PHPUnit, PHPStan, PHPCS, etc.
- Optimizes autoloader for performance

### 2. Automatic Cleanup

- Creates temporary `build/` directory
- Copies only production files
- Generates WordPress.org compatible `readme.txt`
- Removes temporary files
- Restores development environment

### 3. Environment Restoration

After creating the ZIP, the script automatically:
- Restores `composer.lock`
- Reinstalls dev dependencies
- Returns environment to development state

### 4. Verification

```bash
# Verify the ZIP is clean (should return empty)
unzip -l ../idrivee2-media-upload-1.0.0.zip | grep -E "(\.md|phpcs|phpstan|composer\.json|tests/|docs/|bin/)"
```

If this command returns **no results**, your ZIP is clean ✅

---

## Installation Methods

### Method 1: WordPress Admin (Recommended)

1. Generate the ZIP: `./bin/deploy.sh`
2. Go to WordPress admin → Plugins → Add New
3. Click "Upload Plugin"
4. Select `idrivee2-media-upload-1.0.0.zip`
5. Click "Install Now"
6. Activate the plugin

### Method 2: FTP/SFTP

1. Generate the ZIP: `./bin/deploy.sh`
2. Unzip the file locally
3. Upload `idrivee2-media-upload/` directory to `/wp-content/plugins/`
4. Go to WordPress admin → Plugins
5. Activate the plugin

### Method 3: WP-CLI

```bash
wp plugin install /path/to/idrivee2-media-upload-1.0.0.zip --activate
```

---

## Post-Installation Configuration

### 1. Configure S3 Credentials

**Option A: wp-config.php (Recommended)**

```php
// Required
define('IDRIVEE2_MEDIA_HOST',   'https://your-endpoint.idrivee2.com');
define('IDRIVEE2_MEDIA_KEY',    'YOUR_ACCESS_KEY');
define('IDRIVEE2_MEDIA_SECRET', 'YOUR_SECRET_KEY');
define('IDRIVEE2_MEDIA_BUCKET', 'your-bucket-name');
define('IDRIVEE2_MEDIA_REGION', 'us-east-1');

// Optional
define('IDRIVEE2_MEDIA_DOMAIN', 'https://cdn.yourdomain.com');
```

**Option B: WordPress Admin**

1. Go to Settings → iDrivee2
2. Fill in the configuration form
3. Click "Save Changes"

### 2. Enable Security Logging (Optional)

```php
// Add to wp-config.php
define('WP_DEBUG', false);        // Disable debug display
define('WP_DEBUG_LOG', true);     // Enable logging
define('WP_DEBUG_DISPLAY', false); // Don't show errors
```

Logs will be written to: `wp-content/debug.log`

### 3. Test the Configuration

1. Go to Settings → iDrivee2
2. Click "Test S3 Connection"
3. If successful, click "Upload Test File"
4. Verify the test file URL works

---

## Testing the Installation

### Manual Testing Checklist

- [ ] Plugin activates without errors
- [ ] Settings page appears under Settings → iDrivee2
- [ ] Configuration displays correctly (or shows form if not configured)
- [ ] "Test S3 Connection" succeeds
- [ ] "Upload Test File" creates a file in S3
- [ ] Test file URL is accessible
- [ ] Upload an image to Media Library
- [ ] Image appears in S3 bucket
- [ ] Local file is deleted
- [ ] Image displays on frontend with CDN URL
- [ ] Check `wp-content/debug.log` for security events (if enabled)

### Verify Security Features

1. **Rate Limiting**: Click "Test S3 Connection" twice quickly
   - Should show "Please wait X seconds" message

2. **Security Logging**: Upload a test file
   - Check `wp-content/debug.log` for entries like:
   ```
   [iDrivee2] [INFO] S3 putObject succeeded | file: test-20260203120000.txt
   ```

3. **Configuration Tracking**: Change a setting
   - Log should show configuration change with masked value

---

## WordPress.org Submission (Optional)

### Prerequisites

- SVN access to WordPress.org repository
- Plugin approved by WordPress.org Plugin Review Team

### Steps

1. **Generate Production ZIP**
   ```bash
   ./bin/deploy.sh
   ```

2. **Checkout SVN Repository**
   ```bash
   svn co https://plugins.svn.wordpress.org/idrivee2-media-upload
   cd idrivee2-media-upload
   ```

3. **Extract ZIP to trunk/**
   ```bash
   unzip ../idrivee2-media-upload-1.0.0.zip
   mv idrivee2-media-upload/* trunk/
   ```

4. **Copy to tags/1.0.0/**
   ```bash
   svn cp trunk tags/1.0.0
   ```

5. **Commit Changes**
   ```bash
   svn add --force * --auto-props --parents --depth infinity -q
   svn commit -m "Release version 1.0.0 - First stable release"
   ```

---

## GitHub Release (Optional)

### Create Git Tag

```bash
git tag -a v1.0.0 -m "Version 1.0.0 - First stable release"
git push origin v1.0.0
```

### Create GitHub Release

1. Go to: https://git.robotstxt.es/ROBOTSTXT/idrivee2-media-upload/releases/new
2. Tag version: `v1.0.0`
3. Release title: `v1.0.0 - First Stable Release`
4. Description: Copy from `CHANGELOG.md` → v1.0.0 section
5. Attach file: `idrivee2-media-upload-1.0.0.zip`
6. Click "Publish release"

---

## Rollback Plan

### If Issues Occur

1. **Deactivate Plugin**
   ```bash
   wp plugin deactivate idrivee2-media-upload
   ```

2. **Restore Previous Version**
   - Install previous version ZIP
   - Or rollback via WordPress admin → Plugins

3. **Files in S3 Are Safe**
   - S3 files are not affected by plugin updates
   - No data loss occurs

### Database Cleanup

If you need to completely remove the plugin:

```bash
wp plugin uninstall idrivee2-media-upload
```

This runs `uninstall.php` which removes:
- WordPress options (`idrivee2_media_settings`)
- S3 operation statistics (`idrivee2_s3_operations`)
- All transients

---

## Troubleshooting

### ZIP File Too Large

The production ZIP should be ~500KB. If larger:

1. Check `vendor/` directory
2. Ensure `composer install --no-dev` was used
3. Manually remove dev dependencies

### Missing Vendor Directory

If AWS SDK is missing:

```bash
composer install --no-dev
./bin/deploy.sh
```

### Permission Denied on deploy.sh

```bash
chmod +x bin/deploy.sh
./bin/deploy.sh
```

### Plugin Not Found After Installation

- Verify ZIP contains `idrivee2-media-upload.php` at root level
- Check file permissions: `644` for files, `755` for directories

---

## Support

- **Issues**: https://git.robotstxt.es/ROBOTSTXT/idrivee2-media-upload/issues
- **Documentation**: See `README.md` in the repository
- **Security**: See `docs/SECURITY-AUDIT.md`
- **Quality**: See `docs/QUALITY-REPORT.md`

---

## Version History

### v1.0.0 (2026-02-03) - First Stable Release
- ✅ Enterprise-grade security (A+ rating)
- ✅ Security logging system
- ✅ Rate limiting protection
- ✅ PHPStan Level 8 compliance
- ✅ OWASP Top 10 compliant
- ✅ WordPress.org ready
- ✅ Production-ready deployment script

See `CHANGELOG.md` for complete version history.

---

**Last Updated:** 2026-02-03
**Deployment Script:** `bin/deploy.sh`
**Production Package:** `idrivee2-media-upload-1.0.0.zip`
