#!/usr/bin/env bash
# Deploy atglance.live (production): pull branch, update PHP deps + CSS/JS, self-heal aaPanel config.
# The whole site (pages, dashboard, API, /admin) is Laravel; nginx sends every path to public/index.php.
# Usage: bash /www/deploy/atglance.live.sh [--force]   (--force rebuilds everything)
set -Eeuo pipefail

DOMAIN=atglance.live
APP_DIR=/www/wwwroot/$DOMAIN
BRANCH=Version2.0   # CHECK: the branch production should run
APP_USER=www
PHP=/www/server/php/85/bin/php
COMPOSER=/usr/bin/composer
COMPOSER_PHP=/www/server/php/84/bin/php   # composer.lock packages declare PHP <= 8.4
VHOST=/www/server/panel/vhost/nginx/$DOMAIN.conf
REWRITE=/www/server/panel/vhost/rewrite/$DOMAIN.conf   # aaPanel "URL rewrite" file, included by the vhost
PHP_INCLUDE=enable-php-85-atglance.conf   # CHECK: same name as in the prod vhost
USER_INI="$APP_DIR/public/.user.ini"
LOG_DIR=/www/wwwlogs/deploy
FORCE=0
[[ "${1:-}" == "--force" ]] && FORCE=1

mkdir -p "$LOG_DIR"
LOG="$LOG_DIR/$DOMAIN-$(date +%Y%m%d-%H%M%S).log"
exec > >(tee -a "$LOG") 2>&1
ls -1t "$LOG_DIR"/$DOMAIN-*.log 2>/dev/null | tail -n +21 | xargs -r rm -f || true

# One deploy at a time.
exec 9>/tmp/deploy-$DOMAIN.lock
flock -n 9 || { echo "Another deploy is running. Exiting."; exit 1; }

step() { echo; echo "==> $(date +%T) $*"; }
as_app() { sudo -u "$APP_USER" env HOME=/tmp/www-home COREPACK_HOME=/tmp/www-home/corepack \
    COMPOSER_HOME=/tmp/www-home/composer COREPACK_ENABLE_DOWNLOAD_PROMPT=0 \
    PATH=/usr/local/bin:/usr/bin:/bin "$@"; }
# error_log=/dev/null hides harmless duplicate-module startup warnings from php-cli.ini
artisan() { as_app "$PHP" -d error_log=/dev/null artisan --no-ansi "$@"; }
# curl the site through this server, bypassing DNS/CDN
local_curl() { curl -sk --resolve "$DOMAIN:443:127.0.0.1" "$@"; }

MAINTENANCE=0
on_exit() {
    local code=$?
    if [[ $MAINTENANCE == 1 ]]; then artisan up; fi
    if [[ $code != 0 ]]; then
        echo; echo "❌ Deploy FAILED (exit $code) at $(date). Log: $LOG"
    fi
}
trap on_exit EXIT

echo "Deploy started $(date) — branch $BRANCH"

step "Preflight: nginx must not point at the old React build"
# The React SPA (frontend/) is gone. A vhost that still serves frontend/build would break the site.
if grep -n "frontend/build" "$VHOST"; then
    echo "The vhost above still references frontend/build. Remove those lines in aaPanel"
    echo "(Website → $DOMAIN → Config) so only 'root $APP_DIR/public;' remains, then re-run."
    exit 1
fi

cd "$APP_DIR"
sudo -u "$APP_USER" mkdir -p /tmp/www-home

step "Fix file ownership"
chown -R "$APP_USER:$APP_USER" "$APP_DIR" --from=root:root 2>/dev/null || true
chown -R "$APP_USER:$APP_USER" "$APP_DIR/.git"

step "Pull latest code"
OLD=$(as_app git rev-parse HEAD)
as_app git fetch --prune origin "$BRANCH"
as_app git checkout -q "$BRANCH"
if ! as_app git merge --ff-only "origin/$BRANCH"; then
    echo "Local commits or edits block a fast-forward. Fix manually:"
    as_app git status --short
    exit 1
fi
NEW=$(as_app git rev-parse HEAD)
echo "$OLD -> $NEW"
as_app git log --oneline "$OLD..$NEW" | head -20 || true

changed() { [[ $FORCE == 1 ]] || ! as_app git diff --quiet "$OLD" "$NEW" -- "$@"; }

# Leftovers of the old React app (its build and node_modules are not in git).
if [[ -d frontend && ! -f frontend/package.json ]]; then rm -rf frontend; echo "Removed old frontend/ build files"; fi

step "PHP dependencies"
if changed composer.json composer.lock || [[ ! -f vendor/autoload.php ]]; then
    as_app "$COMPOSER_PHP" -d error_log=/dev/null "$COMPOSER" install --no-dev --optimize-autoloader --no-interaction --no-ansi
else
    echo "composer.lock unchanged — skipped"
fi

step "CSS/JS (Vite + Tailwind -> public/build)"
# Tailwind scans the Blade views, so any view change needs a rebuild.
if changed package.json yarn.lock vite.config.js tailwind.config.js postcss.config.js resources app/Livewire app/View \
    || [[ ! -f public/build/manifest.json ]]; then
    as_app yarn install --frozen-lockfile --non-interactive
    as_app yarn build
    [[ -f public/build/manifest.json ]] || { echo "Vite build produced no manifest.json"; exit 1; }
else
    echo "assets unchanged — skipped"
fi

step "Database migrations"
if artisan migrate:status --pending 2>/dev/null | grep -qE "Pending\s*$"; then
    artisan down --retry=15
    MAINTENANCE=1
fi
artisan migrate --force
artisan db:seed --force

step "Laravel caches"
artisan filament:assets
[[ -L public/storage ]] || artisan storage:link
artisan optimize:clear
artisan optimize
if [[ $MAINTENANCE == 1 ]]; then artisan up; MAINTENANCE=0; fi

step "Self-heal aaPanel config"
NGINX_CHANGED=0
if ! grep -q "include $PHP_INCLUDE;" "$VHOST"; then
    sed -i -E "s#include enable-php-[0-9]+\.conf;#include $PHP_INCLUDE;#" "$VHOST"
    echo "Restored $PHP_INCLUDE in vhost"; NGINX_CHANGED=1
fi
if ! grep -qE "^\s*root $APP_DIR/public/?;" "$VHOST"; then
    sed -i -E "s#^(\s*)root [^;]+;#\1root $APP_DIR/public;#" "$VHOST"
    echo "Restored web root to public/"; NGINX_CHANGED=1
fi
# Laravel front controller: real files are served directly, everything else goes to index.php.
LARAVEL_REWRITE='location / {
    try_files $uri $uri/ /index.php?$query_string;
}'
if [[ "$(cat "$REWRITE" 2>/dev/null)" != "$LARAVEL_REWRITE" ]]; then
    [[ -f "$REWRITE" ]] && cp "$REWRITE" "$REWRITE.bak-$(date +%Y%m%d%H%M%S)"
    printf '%s\n' "$LARAVEL_REWRITE" > "$REWRITE"
    echo "Set Laravel rewrite rule in $REWRITE"; NGINX_CHANGED=1
fi
if ! grep -qx "open_basedir=$APP_DIR/:/tmp/" "$USER_INI" 2>/dev/null; then
    chattr -i "$USER_INI" 2>/dev/null || true
    printf 'open_basedir=%s/:/tmp/\ndisplay_errors=Off\n' "$APP_DIR" > "$USER_INI"
    chattr +i "$USER_INI"
    echo "Restored $USER_INI"
fi
if [[ $NGINX_CHANGED == 1 ]]; then nginx -t && nginx -s reload; fi

step "Reload PHP-FPM (clears OPcache)"
/etc/init.d/php-fpm-85 reload

step "Health check"
sleep 2
FAIL=0
for path in / /pricing /docs /login /api /admin/login /sitemap.xml /robots.txt; do
    code=$(local_curl -o /dev/null -w '%{http_code}' "https://$DOMAIN$path")
    echo "$path $code"
    [[ $code == 200 ]] || FAIL=1
done
HOME_HTML=$(local_curl "https://$DOMAIN/")
grep -q 'Operations at a glance' <<<"$HOME_HTML" || { echo "Homepage is not the Laravel site"; FAIL=1; }
CSS=$(grep -oE '/build/assets/app-[^"]+\.css' <<<"$HOME_HTML" | head -1)
[[ -n $CSS && $(local_curl -o /dev/null -w '%{http_code}' "https://$DOMAIN$CSS") == 200 ]] \
    || { echo "Built CSS not served ($CSS)"; FAIL=1; }
if local_curl "https://$DOMAIN/api" | grep -q '<b>Warning'; then
    echo "PHP warnings in API output"; FAIL=1
fi
local_curl "https://$DOMAIN/sitemap.xml" | grep -q '<urlset' || { echo "sitemap.xml is not the Laravel sitemap"; FAIL=1; }
local_curl "https://$DOMAIN/robots.txt" | grep -q "^Sitemap: https://$DOMAIN/sitemap.xml" \
    || { echo "robots.txt wrong (check FRONTEND_URL=https://$DOMAIN in .env)"; FAIL=1; }
[[ $FAIL == 0 ]] || { echo "Health check failed"; exit 1; }

echo
echo "🚀 Application deployed! $(as_app git log -1 --format='%h %s')"
echo "Deploy Completed $(date)"
