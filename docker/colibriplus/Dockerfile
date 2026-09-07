# syntax=docker/dockerfile:1.7
#
# Production image for Rebit (ColibriPlus / Laravel 12 + Vue 3).
#
# Built for Dokploy: code is baked in (no bind mounts), assets are compiled
# during the build, and the web stage serves plain HTTP on port 80 so Dokploy's
# Traefik can terminate TLS in front of it.
#
# Stages:
#   assets  -> npm ci + vite build + the five Tailwind dark-theme passes
#   vendor  -> composer install --no-dev
#   base    -> PHP 8.3 runtime + extensions + ffmpeg (shared by every service)
#   web     -> base + nginx + supervisor  (the only stage that exposes a port)
#   worker  -> base only; horizon / reverb / scheduler override the command

# ---------------------------------------------------------------- assets ----
FROM node:22-bookworm-slim AS assets
WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund

# Vite reads these at build time
COPY vite.config.js ./
COPY resources ./resources
COPY public ./public

# vite.config.js has a build-number plugin whose buildStart() writes
# ./storage/frontend/build.num. storage/ is not copied into this stage, so the
# directory must exist first or the build dies with ENOENT before it starts.
RUN mkdir -p storage/frontend

# Vite inlines import.meta.env.VITE_* at build time from .env. That file is
# excluded by .dockerignore (it holds secrets), so without this the bundle
# compiles every one of them to undefined - no WebSocket connection, no API
# base URL, no Giphy. Passed as build args and written to a throwaway .env
# that never leaves this stage.
ARG VITE_API_BASE_URL=
ARG VITE_APP_API_PREFIX=api
ARG VITE_REVERB_APP_KEY=
ARG VITE_REVERB_HOST=
ARG VITE_REVERB_PORT=443
ARG VITE_REVERB_SCHEME=https
ARG VITE_REVERB_CONNECTION_STATUS=on
ARG VITE_GIPHY_API_KEY=
ARG VITE_APP_NEWS_RESOURCE_URL=
ARG VITE_HIDE_AUTHOR_ATTRIBUTION=false
ARG PUSHER_DEBUG_CONSOLE=false
RUN printf '%s\n' \
      "VITE_API_BASE_URL=${VITE_API_BASE_URL}" \
      "VITE_APP_API_PREFIX=${VITE_APP_API_PREFIX}" \
      "VITE_REVERB_APP_KEY=${VITE_REVERB_APP_KEY}" \
      "VITE_REVERB_HOST=${VITE_REVERB_HOST}" \
      "VITE_REVERB_PORT=${VITE_REVERB_PORT}" \
      "VITE_REVERB_SCHEME=${VITE_REVERB_SCHEME}" \
      "VITE_REVERB_CONNECTION_STATUS=${VITE_REVERB_CONNECTION_STATUS}" \
      "VITE_GIPHY_API_KEY=${VITE_GIPHY_API_KEY}" \
      "VITE_APP_NEWS_RESOURCE_URL=${VITE_APP_NEWS_RESOURCE_URL}" \
      "VITE_HIDE_AUTHOR_ATTRIBUTION=${VITE_HIDE_AUTHOR_ATTRIBUTION}" \
      "PUSHER_DEBUG_CONSOLE=${PUSHER_DEBUG_CONSOLE}" \
      > .env

# `npm run build` = vite build + all five dark-theme Tailwind CLI passes.
# Running only `vite build` would ship stale dark CSS.
RUN npm run build


# ---------------------------------------------------------------- vendor ----
FROM composer:2 AS vendor
WORKDIR /app

COPY composer.json composer.lock ./
# Scripts/autoloader need the full tree, so defer both to the second pass.
RUN composer install \
      --no-dev --prefer-dist --no-progress --no-interaction \
      --ignore-platform-reqs --no-scripts --no-autoloader

COPY . .

# plugins/ is a PSR-4 root that is gitignored, so it is absent in a clone and
# composer warns on every dump without it.
#
# --no-scripts is required: post-autoload-dump runs `artisan package:discover`,
# which boots Laravel, and AppServiceProvider::boot() calls bcscale(). This
# stage is the Alpine composer image, whose PHP has no bcmath, so discovery
# dies with "Call to undefined function App\Providers\bcscale()". Discovery is
# deferred to the base stage, which has the full extension set.
RUN mkdir -p plugins \
 && composer dump-autoload --no-dev --optimize --classmap-authoritative --no-scripts


# ------------------------------------------------------------------ base ----
FROM php:8.3-fpm-bookworm AS base
WORKDIR /var/www/html

ENV COMPOSER_ALLOW_SUPERUSER=1 \
    PHP_MEMORY_LIMIT=512M

RUN set -eux; \
    apt-get update; \
    apt-get install -y --no-install-recommends \
      git curl unzip zip ca-certificates \
      libicu-dev libzip-dev libonig-dev libxml2-dev \
      libpng-dev libjpeg62-turbo-dev libwebp-dev libfreetype6-dev \
      ffmpeg \
      default-mysql-client \
      $PHPIZE_DEPS; \
    docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp; \
    # NOTE on opcache: --disable-opcache-jit lowers the build's memory ceiling
    # (zend_jit.c is one huge translation unit that OOM-kills small builders),
    # but on PHP 8.3 it produces an opcache.so that fails to load at runtime
    # with "undefined symbol: jit_globals". So build it normally and give the
    # builder enough RAM instead - roughly 4 GB. A 2 GB builder will die with
    # "cc: fatal error: Killed signal terminated program cc1".
    docker-php-ext-install -j"$(nproc)" \
      gd intl mbstring pdo_mysql mysqli bcmath pcntl zip opcache exif; \
    pecl install redis; \
    docker-php-ext-enable redis; \
    apt-get purge -y --auto-remove $PHPIZE_DEPS; \
    rm -rf /var/lib/apt/lists/* /tmp/pear

COPY docker/colibriplus/php.ini     /usr/local/etc/php/conf.d/zz-colibriplus.ini
COPY docker/colibriplus/opcache.ini /usr/local/etc/php/conf.d/zz-opcache.ini

# Application code, then the two build outputs on top
COPY --chown=www-data:www-data . .
COPY --from=vendor --chown=www-data:www-data /app/vendor ./vendor
COPY --from=assets --chown=www-data:www-data /app/public/build ./public/build

# ffmpeg comes from apt above; point the app at it rather than the gitignored
# services/ffmpeg/bin/ copies, which do not exist in a git-based build.
ENV FFMPEG_BINARIES=/usr/bin/ffmpeg \
    FFPROBE_BINARIES=/usr/bin/ffprobe

# Directories Laravel needs at runtime, plus the frontend build number the
# AppServiceProvider reads. Both are gitignored, so they must be created here.
RUN set -eux; \
    mkdir -p \
      storage/app/public/uploads/posts/images \
      storage/app/public/uploads/posts/videos \
      storage/app/tmp/images storage/app/tmp/videos \
      storage/framework/cache/data storage/framework/sessions \
      storage/framework/views storage/logs storage/frontend \
      bootstrap/cache plugins; \
    echo 1 > storage/frontend/build.num; \
    chown -R www-data:www-data storage bootstrap/cache; \
    chmod -R ug+rwx storage bootstrap/cache; \
    # database/settings seeds ffmpeg.temporary_directory = /var/ffmpeg-tmp.
    # It is read from the DB, not .env, so the path must exist in the image.
    mkdir -p /var/ffmpeg-tmp; \
    chown www-data:www-data /var/ffmpeg-tmp

# Take the build number Vite actually generated. Blade cache-busts the
# dark-theme CSS with ?v={{ $buildNumber }}; shipping a constant would let
# browsers serve stale dark CSS after a redeploy.
COPY --from=assets --chown=www-data:www-data /app/storage/frontend/build.num ./storage/frontend/build.num

# Package discovery is deliberately NOT run at build time. It boots the whole
# framework, and there is no .env in the image, so providers that need real
# config blow up - first bcscale() without bcmath, then:
#
#   BroadcastManager: Failed to create broadcaster for connection "reverb":
#   Pusher::__construct(): Argument #1 ($auth_key) must be of type string,
#   null given
#
# Laravel's PackageManifest writes bootstrap/cache/packages.php on first boot
# when it is missing and the directory is writable, which it is (chowned
# www-data above). That first boot happens in the entrypoint, with the real
# environment present.

# Plain COPY + chmod rather than COPY --chmod=, which requires BuildKit and
# fails on the legacy builder with "the --chmod option requires BuildKit".
COPY docker/colibriplus/entrypoint.sh /usr/local/bin/entrypoint
RUN chmod 0755 /usr/local/bin/entrypoint
ENTRYPOINT ["entrypoint"]


# ------------------------------------------------------------------- web ----
FROM base AS web

RUN set -eux; \
    apt-get update; \
    apt-get install -y --no-install-recommends nginx supervisor; \
    rm -rf /var/lib/apt/lists/*; \
    rm -f /etc/nginx/sites-enabled/default

COPY docker/nginx/default.conf            /etc/nginx/conf.d/default.conf
COPY docker/colibriplus/supervisord.conf  /etc/supervisor/conf.d/colibriplus.conf

# Traefik (Dokploy) terminates TLS and forwards plain HTTP here.
# Override APP_PORT at runtime; entrypoint rewrites the nginx listen directive.
ARG APP_PORT=80
ENV APP_PORT=${APP_PORT}
EXPOSE ${APP_PORT}

# Shell form so ${APP_PORT} resolves from the container env at check time.
HEALTHCHECK --interval=30s --timeout=5s --start-period=60s --retries=3 \
  CMD curl -fsS "http://127.0.0.1:${APP_PORT}/up" || exit 1

CMD ["supervisord", "-c", "/etc/supervisor/conf.d/colibriplus.conf", "-n"]


# ---------------------------------------------------------------- worker ----
# horizon / reverb / scheduler all run from this stage with their own command.
FROM base AS worker
USER www-data
CMD ["php", "colibri", "horizon"]
