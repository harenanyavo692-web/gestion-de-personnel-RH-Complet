#!/bin/sh
set -e

if [ -f artisan ]; then
    php artisan config:clear
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
    php artisan migrate --force
fi

exec "$@"
