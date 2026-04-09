#!/bin/bash
# Packages the arc-qb-sync plugin for WordPress installation.
# Usage: ./build.sh (run from repo root)
# Output: arc-qb-sync.zip

set -e
PLUGIN_DIR="arc-qb-sync"
OUTPUT="arc-qb-sync.zip"

if [ ! -d "$PLUGIN_DIR" ]; then
  echo "Error: $PLUGIN_DIR directory not found. Run from repo root."
  exit 1
fi

echo "Building $OUTPUT..."
rm -f "$OUTPUT"
zip -r "$OUTPUT" "$PLUGIN_DIR/" \
  --exclude "*.git*" \
  --exclude "*/.DS_Store" \
  --exclude "*/Thumbs.db" \
  --exclude "*.map"

SIZE=$(du -sh "$OUTPUT" | cut -f1)
echo "Done: $OUTPUT ($SIZE)"
echo "Upload at: WP Admin > Plugins > Add New > Upload Plugin"
