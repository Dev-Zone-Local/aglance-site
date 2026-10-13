# AtGlance website + admin (Laravel, php-fpm + nginx, listens on :8080)

# 1) PHP dependencies (the CSS/JS build below also needs vendor/livewire).
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction --ignore-platform-reqs

# 2) CSS/JS with Vite + Tailwind -> public/build
FROM node:20-alpine AS assets
WORKDIR /app
COPY package.json yarn.lock ./
RUN yarn install --frozen-lockfile --non-interactive
COPY vite.config.js tailwind.config.js postcss.config.js ./
COPY resources ./resources
COPY app ./app
COPY --from=vendor /app/vendor/livewire ./vendor/livewire
RUN yarn build

# 3) Runtime
FROM serversideup/php:8.3-fpm-nginx

USER root
# Filament needs intl
RUN install-php-extensions intl
COPY --chmod=755 docker/entrypoint.d/ /etc/entrypoint.d/
USER www-data

WORKDIR /var/www/html

COPY --chown=www-data:www-data composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction

COPY --chown=www-data:www-data . .
COPY --chown=www-data:www-data --from=assets /app/public/build ./public/build
RUN composer dump-autoload --optimize --no-dev \
    && php artisan filament:assets

# serversideup automations: migrate, storage:link, config/route/view cache on boot
ENV AUTORUN_ENABLED=true \
    PHP_OPCACHE_ENABLE=1
