#!/bin/bash
# One-command deploy for AMS_APP (live)
# Usage:  ./deploy.sh
set -e

cd "$(dirname "$0")"

echo "==> Pulling latest code"
git pull origin main

echo "==> Installing dependencies"
composer install --no-dev --optimize-autoloader

echo "==> Building frontend assets"
# Compiles resources/css + resources/js into public/build (which is gitignored,
# so it MUST be rebuilt here on every deploy — otherwise pulled CSS/JS changes
# never reach the browser). Requires Node.js + npm on the server.
npm ci
npm run build

echo "==> Running migrations"
php artisan migrate --force

echo "==> Rebuilding caches"
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "==> Done. Deploy finished successfully."
