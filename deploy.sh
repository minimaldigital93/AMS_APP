#!/bin/bash
# One-command deploy for AMS_APP (live)
# Usage:  ./deploy.sh
set -e

cd "$(dirname "$0")"

echo "==> Pulling latest code"
# The service-worker cache version is stamped per deploy (below), which leaves
# public/sw.js locally modified — restore it first so the pull applies cleanly.
git checkout -- public/sw.js 2>/dev/null || true
git pull origin main

echo "==> Installing dependencies"
composer install --no-dev --optimize-autoloader

echo "==> Stamping service-worker cache version"
# Each deploy invalidates the previous static cache on installed PWAs; without
# this, old fingerprinted build assets accumulate on devices forever.
SW_VERSION="ams-$(git rev-parse --short HEAD)"
perl -pi -e "s/^const CACHE_VERSION = .*/const CACHE_VERSION = '${SW_VERSION}';/" public/sw.js
echo "    CACHE_VERSION = ${SW_VERSION}"

# NOTE: assets are NOT built here. public/build is committed to git (built
# locally or in CI), so the 1GB droplet never runs Vite — building on the box
# OOMs and takes the site down. `git pull` above already brought the compiled
# CSS/JS. To ship frontend changes: run `npm run build` locally, commit
# public/build, push, then deploy.

echo "==> Running migrations"
php artisan migrate --force

echo "==> Rebuilding caches"
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "==> Fixing ownership"
chown -R www-data:www-data .

echo "==> Done. Deploy finished successfully."
