#!/bin/bash
# One-command deploy for AMS_APP (live)
# Usage:  ./deploy.sh
set -e

cd "$(dirname "$0")"

echo "==> Pulling latest code"
git pull origin main

echo "==> Installing dependencies"
composer install --no-dev --optimize-autoloader

echo "==> Running migrations"
php artisan migrate --force

echo "==> Rebuilding caches"
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "==> Done. Deploy finished successfully."
