#!/bin/sh
set -eu
cd /var/www/html
mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs public/uploads
chown -R www-data:www-data storage bootstrap/cache public/uploads
php artisan migrate --force
exec docker-php-entrypoint "$@"
