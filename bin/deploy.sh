#!/bin/bash
###############################################################################
# iDrivee2 Media Upload - Deployment Script
#
# This script packages the plugin for distribution, creating a clean ZIP file
# ready for WordPress.org or manual installation.
#
# What gets included:
#   - Main plugin file (idrivee2-media-upload.php)
#   - includes/ directory (all PHP classes)
#   - assets/ directory (JS, CSS, images)
#   - languages/ directory (translation files)
#   - vendor/ directory (production dependencies ONLY)
#   - uninstall.php (cleanup script)
#   - LICENSE file
#   - readme.txt (WordPress.org documentation)
#   - changelog.txt (full changelog)
#   - update.json (auto-update system)
#   - robotstxt-updater.php (auto-update system)
#
# What gets excluded:
#   - All markdown files (*.md)
#   - Documentation (docs/, AGENTS.md, CLAUDE.md, README.md, CHANGELOG.md)
#   - Tests (tests/, phpunit.xml)
#   - Development configs (phpcs.xml, phpstan.neon, composer.json, composer.lock)
#   - Build scripts (bin/)
#   - Version control (.git, .gitignore, .gitattributes)
#   - IDE configs (.vscode, .idea)
#   - CI/CD configs (.github)
#
# Usage:
#   ./bin/deploy.sh
#
# @package iDrivee2Media
# @since   0.3.0
# @version 1.1.0
###############################################################################

set -e

# Colors for output.
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Get plugin directory.
PLUGIN_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PLUGIN_SLUG="idrivee2-media-upload"

echo -e "${BLUE}╔══════════════════════════════════════════════════════════════╗${NC}"
echo -e "${BLUE}║  iDrivee2 Media Upload - Production Deployment Script       ║${NC}"
echo -e "${BLUE}╚══════════════════════════════════════════════════════════════╝${NC}"
echo ""

# Check if we're in the right directory.
if [ ! -f "$PLUGIN_DIR/idrivee2-media-upload.php" ]; then
    echo -e "${RED}✗ Error: Plugin main file not found.${NC}"
    echo "  Please run this script from the plugin directory."
    exit 1
fi

# Check if composer is installed.
if ! command -v composer &> /dev/null; then
    echo -e "${RED}✗ Error: Composer is not installed.${NC}"
    echo "  Please install Composer: https://getcomposer.org/"
    exit 1
fi

# Check if zip is installed.
if ! command -v zip &> /dev/null; then
    echo -e "${RED}✗ Error: zip command is not installed.${NC}"
    echo "  Please install zip: apt-get install zip"
    exit 1
fi

# Extract version from plugin file.
VERSION=$(grep "Version:" "$PLUGIN_DIR/idrivee2-media-upload.php" | head -1 | awk '{print $3}' | tr -d '\r')

if [ -z "$VERSION" ]; then
    echo -e "${RED}✗ Error: Could not extract version from plugin file.${NC}"
    exit 1
fi

echo -e "${GREEN}Plugin:${NC}  $PLUGIN_SLUG"
echo -e "${GREEN}Version:${NC} $VERSION"
echo ""

# Confirm deployment.
read -p "Create production deployment package for v$VERSION? (y/n) " -n 1 -r
echo
if [[ ! $REPLY =~ ^[Yy]$ ]]; then
    echo -e "${YELLOW}✗ Deployment cancelled.${NC}"
    exit 0
fi

echo ""
echo -e "${BLUE}══════════════════════════════════════════════════════════════${NC}"
echo -e "${GREEN}Step 1/6: Cleaning up previous builds...${NC}"
echo -e "${BLUE}══════════════════════════════════════════════════════════════${NC}"
rm -rf "$PLUGIN_DIR/build"
mkdir -p "$PLUGIN_DIR/build/$PLUGIN_SLUG"
echo "✓ Build directory ready"

echo ""
echo -e "${BLUE}══════════════════════════════════════════════════════════════${NC}"
echo -e "${GREEN}Step 2/6: Installing production dependencies...${NC}"
echo -e "${BLUE}══════════════════════════════════════════════════════════════${NC}"
cd "$PLUGIN_DIR"

# Backup composer.lock if exists (we'll restore it later).
if [ -f "composer.lock" ]; then
    cp composer.lock composer.lock.backup
fi

# Set platform PHP version to 8.2 for consistent builds.
composer config platform.php 8.2 --quiet

# Update and install only production dependencies (--no-dev excludes dev dependencies).
# Using update ensures we get the latest compatible versions for PHP 8.2+.
composer update --no-dev --optimize-autoloader --no-interaction --quiet

if [ $? -eq 0 ]; then
    echo "✓ Production dependencies installed (PHP 8.2+ compatible)"
else
    echo -e "${RED}✗ Failed to install dependencies${NC}"
    exit 1
fi

echo ""
echo -e "${BLUE}══════════════════════════════════════════════════════════════${NC}"
echo -e "${GREEN}Step 3/6: Copying production files...${NC}"
echo -e "${BLUE}══════════════════════════════════════════════════════════════${NC}"

BUILD_DIR="$PLUGIN_DIR/build/$PLUGIN_SLUG"

# Copy main plugin file.
cp "$PLUGIN_DIR/idrivee2-media-upload.php" "$BUILD_DIR/"
echo "✓ Main plugin file"

# Copy includes directory (PHP classes).
if [ -d "$PLUGIN_DIR/includes" ]; then
    cp -r "$PLUGIN_DIR/includes" "$BUILD_DIR/"
    echo "✓ includes/ (PHP classes)"
fi

# Copy assets directory (JS, CSS, images).
if [ -d "$PLUGIN_DIR/assets" ]; then
    cp -r "$PLUGIN_DIR/assets" "$BUILD_DIR/"
    echo "✓ assets/ (JavaScript, CSS)"
fi

# Copy languages directory (translation files).
if [ -d "$PLUGIN_DIR/languages" ]; then
    cp -r "$PLUGIN_DIR/languages" "$BUILD_DIR/"
    echo "✓ languages/ (translations)"
else
    # Create empty languages directory for WordPress.org.
    mkdir -p "$BUILD_DIR/languages"
    echo "✓ languages/ (empty directory created)"
fi

# Copy vendor directory (production dependencies ONLY).
if [ -d "$PLUGIN_DIR/vendor" ]; then
    # Remove any dev dependencies that might have sneaked in.
    rm -rf "$PLUGIN_DIR/vendor/bin" 2>/dev/null || true
    cp -r "$PLUGIN_DIR/vendor" "$BUILD_DIR/"
    echo "✓ vendor/ (AWS SDK production only)"
fi

# Copy uninstall script.
if [ -f "$PLUGIN_DIR/uninstall.php" ]; then
    cp "$PLUGIN_DIR/uninstall.php" "$BUILD_DIR/"
    echo "✓ uninstall.php"
fi

# Copy LICENSE file (required for GPL compliance).
if [ -f "$PLUGIN_DIR/LICENSE" ]; then
    cp "$PLUGIN_DIR/LICENSE" "$BUILD_DIR/"
    echo "✓ LICENSE"
fi

# Copy update.json (required for auto-updates).
if [ -f "$PLUGIN_DIR/update.json" ]; then
    cp "$PLUGIN_DIR/update.json" "$BUILD_DIR/"
    echo "✓ update.json (auto-update system)"
fi

# Copy robotstxt-updater.php (required for auto-updates).
if [ -f "$PLUGIN_DIR/robotstxt-updater.php" ]; then
    cp "$PLUGIN_DIR/robotstxt-updater.php" "$BUILD_DIR/"
    echo "✓ robotstxt-updater.php (auto-update system)"
fi

# Copy readme.txt (WordPress.org documentation).
if [ -f "$PLUGIN_DIR/readme.txt" ]; then
    cp "$PLUGIN_DIR/readme.txt" "$BUILD_DIR/"
    echo "✓ readme.txt (WordPress.org documentation)"
fi

# Copy changelog.txt (full changelog).
if [ -f "$PLUGIN_DIR/changelog.txt" ]; then
    cp "$PLUGIN_DIR/changelog.txt" "$BUILD_DIR/"
    echo "✓ changelog.txt (full changelog)"
fi

# Create a production-only readme.txt for WordPress.org (if doesn't exist).
if [ ! -f "$BUILD_DIR/readme.txt" ]; then
    cat > "$BUILD_DIR/readme.txt" << 'READMETXT'
=== iDrivee2 Media Upload ===
Contributors: robotstxt, javiercasares
Tags: s3, cdn, media, upload, storage
Requires at least: 6.8
Tested up to: 6.9
Requires PHP: 8.2
Stable tag: 1.0.0
License: GPL-3.0-or-later
License URI: https://www.gnu.org/licenses/gpl-3.0.txt

Uploads media files to iDrivee2 (S3-compatible storage) with enterprise-grade security and logging.

== Description ==

WordPress plugin that uploads media files to iDrivee2 (S3-compatible storage), deletes local copies, and serves media from a CDN. Enterprise-grade security with comprehensive logging and rate limiting.

**Features:**

* Automatic upload to S3-compatible storage
* Local file cleanup to save disk space
* CDN integration for faster media delivery
* Security logging with WP_DEBUG_LOG integration
* Rate limiting to prevent abuse
* OWASP Top 10 (2021) compliant
* PHPStan level 8 type-safe code
* WordPress.org coding standards compliant

**Security Rating: A+ (Excellent)**

== Installation ==

1. Upload the plugin files to `/wp-content/plugins/idrivee2-media-upload/`
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Configure S3 credentials in Settings → iDrivee2
4. Test the connection and start uploading

== Frequently Asked Questions ==

= Does this plugin delete my local files? =

Yes. After successfully uploading to S3, local files are deleted to save disk space. Make sure your S3 configuration is correct before using.

= Can I use my own CDN domain? =

Yes. Set the IDRIVEE2_MEDIA_DOMAIN constant in wp-config.php or configure it in Settings → iDrivee2.

= Is this compatible with Multisite? =

Yes. The plugin fully supports WordPress Multisite installations.

== Changelog ==

= 1.0.0 =
* First stable release with enterprise-grade security
* Added security logging system
* Added rate limiting protection
* PHPStan level 8 compliance
* OWASP Top 10 compliant
* Complete security audit (A+ rating)

See CHANGELOG.md on GitHub for detailed version history.

== Upgrade Notice ==

= 1.0.0 =
First production-ready release with comprehensive security features.
READMETXT
    echo "✓ readme.txt (WordPress.org format)"
fi

echo ""
echo -e "${BLUE}══════════════════════════════════════════════════════════════${NC}"
echo -e "${GREEN}Step 4/6: Creating ZIP archive...${NC}"
echo -e "${BLUE}══════════════════════════════════════════════════════════════${NC}"

cd "$PLUGIN_DIR/build"
ZIP_FILE="$PLUGIN_SLUG-$VERSION.zip"

# Create ZIP excluding unwanted files.
zip -r -q "$ZIP_FILE" "$PLUGIN_SLUG" \
    -x "*.git*" \
    -x "*.DS_Store" \
    -x "*__MACOSX*" \
    -x "*.md" \
    -x "*/.*"

if [ $? -eq 0 ]; then
    echo "✓ ZIP archive created: $ZIP_FILE"
else
    echo -e "${RED}✗ Failed to create ZIP archive${NC}"
    exit 1
fi

# Move ZIP to parent directory (outside plugin directory).
DEST_DIR="$PLUGIN_DIR/../"
mv "$ZIP_FILE" "$DEST_DIR"
ZIP_PATH="$DEST_DIR$ZIP_FILE"

echo ""
echo -e "${BLUE}══════════════════════════════════════════════════════════════${NC}"
echo -e "${GREEN}Step 5/6: Restoring development environment...${NC}"
echo -e "${BLUE}══════════════════════════════════════════════════════════════${NC}"

cd "$PLUGIN_DIR"

# Remove temporary platform PHP configuration.
composer config --unset platform.php --quiet

# Restore composer.lock if we backed it up.
if [ -f "composer.lock.backup" ]; then
    mv composer.lock.backup composer.lock
fi

# Reinstall with dev dependencies.
composer install --no-interaction --quiet

if [ $? -eq 0 ]; then
    echo "✓ Development dependencies restored"
else
    echo -e "${YELLOW}⚠ Warning: Failed to restore dev dependencies${NC}"
fi

echo ""
echo -e "${BLUE}══════════════════════════════════════════════════════════════${NC}"
echo -e "${GREEN}Step 6/6: Cleaning up...${NC}"
echo -e "${BLUE}══════════════════════════════════════════════════════════════${NC}"

# Clean up build directory.
rm -rf "$PLUGIN_DIR/build"
echo "✓ Temporary files removed"

echo ""
echo -e "${BLUE}╔══════════════════════════════════════════════════════════════╗${NC}"
echo -e "${GREEN}║  ✓ DEPLOYMENT SUCCESSFUL!                                    ║${NC}"
echo -e "${BLUE}╚══════════════════════════════════════════════════════════════╝${NC}"
echo ""
echo -e "${GREEN}Package Details:${NC}"
echo -e "  File:    ${BLUE}$ZIP_FILE${NC}"
echo -e "  Version: ${BLUE}$VERSION${NC}"
echo -e "  Size:    ${BLUE}$(du -h "$ZIP_PATH" | cut -f1)${NC}"
echo -e "  Path:    ${YELLOW}$ZIP_PATH${NC}"
echo ""
echo -e "${GREEN}What's included:${NC}"
echo "  ✓ Main plugin file"
echo "  ✓ PHP classes (includes/)"
echo "  ✓ Assets (JavaScript, CSS)"
echo "  ✓ Production dependencies (vendor/)"
echo "  ✓ Uninstall script"
echo "  ✓ LICENSE file"
echo "  ✓ readme.txt (WordPress.org format)"
echo "  ✓ changelog.txt (full changelog)"
echo "  ✓ update.json (auto-update system)"
echo "  ✓ robotstxt-updater.php (auto-update system)"
echo ""
echo -e "${GREEN}What's excluded:${NC}"
echo "  ✗ Documentation files (*.md, docs/)"
echo "  ✗ Development tools (tests/, bin/, phpunit.xml, phpstan.neon, phpcs.xml)"
echo "  ✗ Composer files (composer.json, composer.lock)"
echo "  ✗ Version control (.git, .gitignore)"
echo "  ✗ IDE configs (.vscode, .idea)"
echo ""
echo -e "${GREEN}You can now upload to:${NC}"
echo "  • WordPress admin → Plugins → Add New → Upload Plugin"
echo "  • Your web server via FTP"
echo "  • WordPress.org SVN repository"
echo ""
echo -e "${BLUE}═══════════════════════════════════════════════════════════════${NC}"
echo ""
