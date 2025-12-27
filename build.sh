#!/bin/bash
# Build script for WP Sync DB plugin

# Extract version from plugin file
VERSION=$(grep 'Version:' wp-sync-db.php | head -1 | sed 's/.*Version:[[:space:]]*\([0-9.]*\).*/\1/')

if [ -z "$VERSION" ]; then
    echo "Error: Could not extract version from wp-sync-db.php"
    exit 1
fi

echo "Building WP Sync DB v${VERSION}..."

# Clean up previous builds
rm -rf build
rm -f dist/wp-sync-db-*.zip

# Create build directories
mkdir -p build/wp-sync-db
mkdir -p dist

# Copy files to build directory, excluding development files
rsync -av \
    --exclude='vendor/' \
    --exclude='.git/' \
    --exclude='node_modules/' \
    --exclude='build/' \
    --exclude='dist/' \
    --exclude='.claude/' \
    --exclude='.env' \
    --exclude='*.env' \
    --exclude='composer.json' \
    --exclude='composer.lock' \
    --exclude='phpcs.xml*' \
    --exclude='.gitignore' \
    --exclude='.DS_Store' \
    --exclude='.gitattributes' \
    --exclude='CLAUDE.md' \
    --exclude='build.sh' \
    --exclude='*.zip' \
    . build/wp-sync-db/

# Create the ZIP file
cd build
zip -r "../dist/wp-sync-db-${VERSION}.zip" wp-sync-db
cd ..

# Clean up build directory
rm -rf build

echo "Build complete: dist/wp-sync-db-${VERSION}.zip"
