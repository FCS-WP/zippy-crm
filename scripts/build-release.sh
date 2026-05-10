#!/usr/bin/env bash
# Build a production-ready zip of the Zippy CRM plugin.
#
# Output: dist/zippy-crm-<version>.zip
#   - Contains a single top-level folder `zippy-crm/` so it unpacks
#     correctly into wp-content/plugins/zippy-crm/.
#   - Excludes node_modules, source assets, dev tooling, tests, docs,
#     git metadata, and the dist folder itself.
#
# Usage:
#   ./scripts/build-release.sh           # version read from zippy-crm.php
#   ./scripts/build-release.sh 1.2.3     # override version
#
# Requires: node, npm, zip, rsync.

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PLUGIN_DIR="$(cd "$SCRIPT_DIR/.." && pwd)"
PLUGIN_SLUG="zippy-crm"

cd "$PLUGIN_DIR"

# --- Resolve version --------------------------------------------------------
if [[ "${1:-}" != "" ]]; then
	VERSION="$1"
else
	VERSION="$(grep -E '^[[:space:]]*\*[[:space:]]*Version:' zippy-crm.php \
		| head -1 | sed -E 's/.*Version:[[:space:]]*([0-9A-Za-z._-]+).*/\1/')"
fi

if [[ -z "$VERSION" ]]; then
	echo "ERROR: could not resolve plugin version." >&2
	exit 1
fi

echo "==> Building $PLUGIN_SLUG v$VERSION"

# --- Sanity checks ----------------------------------------------------------
for bin in node npm zip rsync; do
	command -v "$bin" >/dev/null 2>&1 || {
		echo "ERROR: '$bin' is required but not installed." >&2
		exit 1
	}
done

# --- Build assets -----------------------------------------------------------
echo "==> Installing npm dependencies (clean)"
npm ci --no-audit --no-fund

echo "==> Running production build"
npm run build

if [[ ! -d "assets/dist" ]]; then
	echo "ERROR: assets/dist not found after build." >&2
	exit 1
fi

# --- Stage release tree -----------------------------------------------------
DIST_DIR="$PLUGIN_DIR/dist"
STAGE_PARENT="$DIST_DIR/_stage"
STAGE_DIR="$STAGE_PARENT/$PLUGIN_SLUG"
ZIP_PATH="$DIST_DIR/${PLUGIN_SLUG}-${VERSION}.zip"

rm -rf "$STAGE_PARENT" "$ZIP_PATH"
mkdir -p "$STAGE_DIR"

echo "==> Staging files into $STAGE_DIR"
# rsync handles excludes better than find/cp combos.
rsync -a \
	--exclude '.git/' \
	--exclude '.github/' \
	--exclude '.gitignore' \
	--exclude '.gitattributes' \
	--exclude '.claude/' \
	--exclude '.vscode/' \
	--exclude '.idea/' \
	--exclude '.DS_Store' \
	--exclude '/node_modules/' \
	--exclude '/vendor/' \
	--exclude '/dist/' \
	--exclude '/tests/' \
	--exclude '/docs/' \
	--exclude '/scripts/' \
	--exclude '/assets/src/' \
	--exclude 'CLAUDE.md' \
	--exclude 'README.md' \
	--exclude 'package.json' \
	--exclude 'package-lock.json' \
	--exclude 'vite.config.js' \
	--exclude 'tailwind.config.js' \
	--exclude 'postcss.config.js' \
	--exclude 'composer.json' \
	--exclude 'composer.lock' \
	--exclude 'phpunit.xml' \
	--exclude 'phpcs.xml' \
	--exclude '*.log' \
	./ "$STAGE_DIR/"

# --- Sanity-check the staged tree -------------------------------------------
if [[ ! -f "$STAGE_DIR/zippy-crm.php" ]]; then
	echo "ERROR: zippy-crm.php missing from stage." >&2
	exit 1
fi
if [[ ! -d "$STAGE_DIR/assets/dist" ]]; then
	echo "ERROR: assets/dist missing from stage." >&2
	exit 1
fi
if [[ ! -d "$STAGE_DIR/src" ]]; then
	echo "ERROR: src/ missing from stage." >&2
	exit 1
fi

# --- Zip --------------------------------------------------------------------
echo "==> Creating $ZIP_PATH"
( cd "$STAGE_PARENT" && zip -rq "$ZIP_PATH" "$PLUGIN_SLUG" )

rm -rf "$STAGE_PARENT"

SIZE="$(du -h "$ZIP_PATH" | cut -f1)"
echo
echo "Done."
echo "  File: $ZIP_PATH"
echo "  Size: $SIZE"
echo
echo "Install on the production site:"
echo "  WP Admin -> Plugins -> Add New -> Upload Plugin -> choose the zip"
