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
WORKER_PID=$!

# Start scheduler (runs schedule:run every minute). Required for:
# - FetchNationalHighwaysIncidentsJob, ScrapeSomersetCouncilRoadworksJob (every 15 min)
# - flood-watch:warm-cache (every 15 min), flood-watch:prune-llm-requests (daily)
# Jobs use onOneServer(); CACHE_STORE must be database or Redis (not file) for schedule locks.
php artisan schedule:work &
SCHEDULER_PID=$!

# Start web server in background so we can wait and handle signals
php artisan serve --host=0.0.0.0 --port=${PORT:-80} &
SERVER_PID=$!

# On SIGTERM/SIGINT, kill all and exit
trap 'kill $WORKER_PID $SCHEDULER_PID $SERVER_PID 2>/dev/null; exit 0' TERM INT

# On exit, kill all (one may already be gone)
trap 'kill $WORKER_PID $SCHEDULER_PID $SERVER_PID 2>/dev/null' EXIT

# Wait for any process to exit (triggers container restart).
while kill -0 $WORKER_PID 2>/dev/null && kill -0 $SCHEDULER_PID 2>/dev/null && kill -0 $SERVER_PID 2>/dev/null; do
    sleep 1
done
