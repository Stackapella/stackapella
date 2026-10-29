#!/bin/sh
# The web component: cache the config and routes in this container, then php-fpm behind nginx.
set -e
cd /app/server
php artisan optimize
php-fpm -D
exec nginx -g 'daemon off;'
