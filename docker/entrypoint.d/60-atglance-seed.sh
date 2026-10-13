#!/bin/sh
# Seed default CMS content (only fills empty tables). The admin is created with `php artisan atglance:make-admin`.
set -e
php /var/www/html/artisan db:seed --force --no-interaction
