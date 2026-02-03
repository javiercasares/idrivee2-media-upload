#!/bin/bash
###############################################################################
# iDrivee2 Media Upload - Deployment Script
#
# This script packages the plugin for distribution, creating a ZIP file
# ready for WordPress.org or manual installation.
#
# Usage:
#   ./bin/deploy.sh
#
# @package iDrivee2Media
# @since   0.3.0
###############################################################################

set -e

# Colors for output.
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Get plugin directory.
PLUGIN_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PLUGIN_SLUG="idrivee2-media-upload"

echo -e "${GREEN}iDrivee2 Media Upload - Deployment Script${NC}"
echo "============================================"
echo ""

# Check if we're in the right directory.
if [ ! -f "$PLUGIN_DIR/idrivee2-media-upload.php" ]; then
    echo -e "${RED}Error: Plugin main file not found.${NC}"
    echo "Please run this script from the plugin directory."
    exit 1
fi

# Extract version from plugin file.
VERSION=$(grep "Version:" "$PLUGIN_DIR/idrivee2-media-upload.php" | awk '{print $3}' | tr -d '\r')

if [ -z "$VERSION" ]; then
    echo -e "${RED}Error: Could not extract version from plugin file.${NC}"
    exit 1
fi

echo "Plugin: $PLUGIN_SLUG"
echo "Version: $VERSION"
echo ""

# Confirm deployment.
read -p "Do you want to create a deployment package for version $VERSION? (y/n) " -n 1 -r
echo
if [[ ! $REPLY =~ ^[Yy]$ ]]; then
    echo -e "${YELLOW}Deployment cancelled.${NC}"
    exit 0
fi

# Clean up previous builds.
echo -e "${GREEN}Cleaning up previous builds...${NC}"
rm -rf "$PLUGIN_DIR/build"
mkdir -p "$PLUGIN_DIR/build/$PLUGIN_SLUG"

# Install production dependencies.
echo -e "${GREEN}Installing production dependencies...${NC}"
cd "$PLUGIN_DIR"
composer install --no-dev --optimize-autoloader --quiet

# Copy files to build directory.
echo -e "${GREEN}Copying files...${NC}"

# Main plugin file.
cp "$PLUGIN_DIR/idrivee2-media-upload.php" "$PLUGIN_DIR/build/$PLUGIN_SLUG/"

# Includes directory.
if [ -d "$PLUGIN_DIR/includes" ]; then
    cp -r "$PLUGIN_DIR/includes" "$PLUGIN_DIR/build/$PLUGIN_SLUG/"
fi

# Assets directory.
if [ -d "$PLUGIN_DIR/assets" ]; then
    cp -r "$PLUGIN_DIR/assets" "$PLUGIN_DIR/build/$PLUGIN_SLUG/"
fi

# Languages directory.
if [ -d "$PLUGIN_DIR/languages" ]; then
    cp -r "$PLUGIN_DIR/languages" "$PLUGIN_DIR/build/$PLUGIN_SLUG/"
fi

# Vendor directory (production only).
if [ -d "$PLUGIN_DIR/vendor" ]; then
    cp -r "$PLUGIN_DIR/vendor" "$PLUGIN_DIR/build/$PLUGIN_SLUG/"
fi

# Documentation files.
cp "$PLUGIN_DIR/README.md" "$PLUGIN_DIR/build/$PLUGIN_SLUG/" 2>/dev/null || true
cp "$PLUGIN_DIR/LICENSE" "$PLUGIN_DIR/build/$PLUGIN_SLUG/" 2>/dev/null || true
cp "$PLUGIN_DIR/uninstall.php" "$PLUGIN_DIR/build/$PLUGIN_SLUG/" 2>/dev/null || true

# Create ZIP file.
echo -e "${GREEN}Creating ZIP archive...${NC}"
cd "$PLUGIN_DIR/build"
ZIP_FILE="$PLUGIN_SLUG-$VERSION.zip"
zip -r -q "$ZIP_FILE" "$PLUGIN_SLUG"

# Move ZIP to parent directory.
mv "$ZIP_FILE" "$PLUGIN_DIR/../"

# Restore dev dependencies.
echo -e "${GREEN}Restoring development dependencies...${NC}"
cd "$PLUGIN_DIR"
composer install --quiet

# Clean up build directory.
echo -e "${GREEN}Cleaning up...${NC}"
rm -rf "$PLUGIN_DIR/build"

echo ""
echo -e "${GREEN}✓ Deployment package created successfully!${NC}"
echo "File: $PLUGIN_DIR/../$ZIP_FILE"
echo ""
echo "You can now upload this ZIP file to:"
echo "  - WordPress admin → Plugins → Add New → Upload Plugin"
echo "  - Your web server via FTP"
echo "  - WordPress.org SVN repository"
echo ""
