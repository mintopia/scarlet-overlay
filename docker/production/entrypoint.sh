#!/bin/sh
set -e

php /app/artisan optimize
php /app/artisan migrate --force
php /app/artisan mediamtx:sync || true

exec "$@"
