#!/bin/sh
# Seed/refresh the admin user and default CMS content. Content seeders only fill empty tables.
set -e
php /var/www/html/artisan db:seed --force --no-interaction
