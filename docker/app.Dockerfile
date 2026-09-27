# Laravel API + Filament admin (php-fpm + nginx, listens on :8080)
FROM serversideup/php:8.3-fpm-nginx

USER root
# Filament needs intl
RUN install-php-extensions intl
COPY --chmod=755 docker/entrypoint.d/ /etc/entrypoint.d/
USER www-data

WORKDIR /var/www/html

# Install PHP deps first for better layer caching
COPY --chown=www-data:www-data composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction

COPY --chown=www-data:www-data . .
RUN composer dump-autoload --optimize --no-dev \
    && php artisan filament:assets

# serversideup automations: migrate, storage:link, config/route/view cache on boot
ENV AUTORUN_ENABLED=true \
    PHP_OPCACHE_ENABLE=1
