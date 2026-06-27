#!/bin/bash
# One-command deploy for AMS_APP (live)
# Usage:  ./deploy.sh
set -e

cd "$(dirname "$0")"

echo "==> Pulling latest code"
git pull origin main

echo "==> Installing dependencies"
composer install --no-dev --optimize-autoloader

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
