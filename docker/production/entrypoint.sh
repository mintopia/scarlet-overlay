#!/bin/sh
set -e

php /app/artisan optimize
php /app/artisan migrate --force

exec "$@"
