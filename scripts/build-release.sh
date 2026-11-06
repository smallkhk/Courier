#!/usr/bin/env bash
# Builds the installable package for cPanel and one-click updates:
#   dist/expressshipping-<version>.zip  and  dist/expressshipping-<version>.zip.sha256
# The ZIP contains production dependencies (vendor/) and compiled assets, so the server
# needs no Composer, Node or SSH. Demo data, tests and developer tooling are left out.
# Usage: scripts/build-release.sh <version>    (run from the repository root, after `npm run build`)
set -euo pipefail

VERSION="${1:?usage: build-release.sh <version>}"
[[ "$VERSION" =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]] || { echo "Version must look like 1.2.3" >&2; exit 1; }
[[ "$(tr -d '[:space:]' < VERSION)" == "$VERSION" ]] || { echo "VERSION file says $(cat VERSION), not $VERSION" >&2; exit 1; }
[[ -f public/build/manifest.json ]] || { echo "Run npm run build first" >&2; exit 1; }

ROOT="$(pwd)"
OUT="$ROOT/dist"
STAGE="$OUT/package"
NAME="expressshipping-$VERSION.zip"
rm -rf "$STAGE" "$OUT/$NAME" "$OUT/$NAME.sha256"
mkdir -p "$STAGE"

# Tracked files only (never .env, logs or local uploads).
git archive --format=tar HEAD | tar -x -C "$STAGE"
rm -rf "$STAGE/public/build" && cp -R public/build "$STAGE/public/build"

# Not shipped to the live site.
rm -rf "$STAGE"/{tests,.github,scripts,node_modules} \
       "$STAGE"/{phpunit.xml,playwright.config.ts,.styleci.yml,.editorconfig,vite.config.js,package.json,package-lock.json} \
       "$STAGE/database/seeders/DemoSeeder.php"

( cd "$STAGE" && COMPOSER_ALLOW_SUPERUSER=1 composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction --no-progress --no-scripts --quiet )
find "$STAGE/vendor" -name .git -type d -prune -exec rm -rf {} +
rm -f "$STAGE"/bootstrap/cache/*.php

# File list with SHA-256 hashes: the updater only replaces files that changed and removes
# files a release no longer contains.
php -r '
$root = $argv[1]; $files = [];
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
foreach ($it as $f) {
    if (! $f->isFile()) continue;
    $rel = str_replace("\\", "/", substr($f->getPathname(), strlen($root) + 1));
    if ($rel === "manifest.json") continue;
    $files[$rel] = hash_file("sha256", $f->getPathname());
}
ksort($files);
file_put_contents("$root/manifest.json", json_encode(["version" => $argv[2], "files" => $files], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
echo count($files), " files\n";
' "$STAGE" "$VERSION"

( cd "$STAGE" && zip -qrX "$OUT/$NAME" . )
( cd "$OUT" && sha256sum "$NAME" > "$NAME.sha256" )
rm -rf "$STAGE"
echo "Built $OUT/$NAME ($(du -h "$OUT/$NAME" | cut -f1))"
