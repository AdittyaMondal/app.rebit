#!/usr/bin/env bash
# Runtime preparation, shared by every service built from this image.
# Anything that depends on env vars or the database belongs here, not in the
# Dockerfile — the image is built once, but these must run on every deploy.
set -euo pipefail

cd /var/www/html

log() { echo "[entrypoint] $*"; }

# --- storage layout ---------------------------------------------------------
# Recreated each start because Dokploy may mount an empty volume over storage/.
mkdir -p \
  storage/app/public/uploads/posts/images \
  storage/app/public/uploads/posts/videos \
  storage/app/tmp/images storage/app/tmp/videos \
  storage/framework/cache/data storage/framework/sessions \
  storage/framework/views storage/logs storage/frontend \
  bootstrap/cache

[ -f storage/frontend/build.num ] || echo 1 > storage/frontend/build.num
chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true

# --- APP_KEY ----------------------------------------------------------------
# Must be set in Dokploy. Generating one here would differ per container and
# silently break sessions and every encrypted column.
if [ -z "${APP_KEY:-}" ]; then
    log "FATAL: APP_KEY is empty. Generate one with 'php colibri key:generate --show'"
    log "       and set it in Dokploy -> Environment. Refusing to start."
    exit 1
fi

# --- wait for the database --------------------------------------------------
if [ "${WAIT_FOR_DB:-true}" = "true" ]; then
    log "waiting for ${DB_HOST:-mysql}:${DB_PORT:-3306} ..."
    for i in $(seq 1 60); do
        if php -r '
            $h=getenv("DB_HOST")?:"mysql"; $p=getenv("DB_PORT")?:3306;
            exit(@fsockopen($h,(int)$p,$e,$s,2) ? 0 : 1);
        '; then log "database reachable"; break; fi
        [ "$i" = "60" ] && { log "FATAL: database unreachable after 120s"; exit 1; }
        sleep 2
    done
fi

# --- storage symlink --------------------------------------------------------
# public/storage is gitignored, so it never exists in a fresh image.
[ -L public/storage ] || php colibri storage:link --force >/dev/null 2>&1 || true

# --- migrations -------------------------------------------------------------
# Opt-in. Enable on exactly one service (the web one) so parallel workers do not
# race the same migration.
if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
    log "running migrations"
    php colibri migrate --force --no-interaction
fi

# --- caches -----------------------------------------------------------------
# Rebuilt per deploy: they embed env values, so a stale cache silently serves
# the previous deploy's config.
php colibri optimize:clear >/dev/null 2>&1 || true
if [ "${APP_ENV:-production}" = "production" ]; then
    php colibri config:cache >/dev/null 2>&1 || log "warn: config:cache failed"
    php colibri route:cache  >/dev/null 2>&1 || log "warn: route:cache failed"
    php colibri view:cache   >/dev/null 2>&1 || log "warn: view:cache failed"
fi

log "ready: $*"
exec "$@"
