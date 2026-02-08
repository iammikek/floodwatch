#!/bin/sh
set -e

# Create SQLite DB if needed
touch database/database.sqlite 2>/dev/null || true

# Migrations and cache
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Start queue worker in background (for LLM request recording, etc.)
php artisan queue:work --tries=3 --timeout=60 &

# Start web server (foreground)
exec php artisan serve --host=0.0.0.0 --port=${PORT:-80}
