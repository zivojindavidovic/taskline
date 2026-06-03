#!/bin/sh
set -e

cd /var/www/html

# -----------------------------------------------------------------------------
# 1. Storage skeleton — the storage/ named volume can be empty on first run.
# -----------------------------------------------------------------------------
mkdir -p \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/framework/testing \
    storage/logs \
    storage/app/public
chown -R www-data:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache

# -----------------------------------------------------------------------------
# 2. Publish baked assets into the shared public/ volume (nginx serves these).
#    Done on every start so a new image version ships new assets.
# -----------------------------------------------------------------------------
if [ -d /var/www/html/.public-template ]; then
    cp -a /var/www/html/.public-template/. /var/www/html/public/
    chown -R www-data:www-data /var/www/html/public
fi

# -----------------------------------------------------------------------------
# 3. APP_KEY — if none provided, generate once and persist in the storage volume.
# -----------------------------------------------------------------------------
if [ -z "${APP_KEY:-}" ]; then
    KEYFILE=storage/app/.appkey
    if [ ! -f "$KEYFILE" ]; then
        php artisan key:generate --show > "$KEYFILE"
    fi
    APP_KEY="$(cat "$KEYFILE")"
    export APP_KEY
    echo "entrypoint: using auto-generated APP_KEY from $KEYFILE"
fi

# -----------------------------------------------------------------------------
# 4. One-time init (migrations, storage:link). Runs only where TASKLINE_INIT=1
#    so reverb/queue containers don't race on it.
# -----------------------------------------------------------------------------
if [ "${TASKLINE_INIT:-1}" = "1" ]; then
    echo "entrypoint: waiting for database ${DB_HOST:-postgres}:${DB_PORT:-5432} ..."
    i=0
    until php -r '
        $h = getenv("DB_HOST") ?: "127.0.0.1";
        $p = getenv("DB_PORT") ?: "5432";
        $d = getenv("DB_DATABASE") ?: "taskline";
        try { new PDO("pgsql:host=$h;port=$p;dbname=$d", getenv("DB_USERNAME"), getenv("DB_PASSWORD")); exit(0); }
        catch (Throwable $e) { exit(1); }
    ' 2>/dev/null; do
        i=$((i + 1))
        if [ "$i" -ge 30 ]; then
            echo "entrypoint: database not reachable after 60s, continuing anyway" >&2
            break
        fi
        sleep 2
    done

    php artisan storage:link --force >/dev/null 2>&1 || true
    php artisan migrate --force
fi

# -----------------------------------------------------------------------------
# 5. Rebuild config/view caches each start so .env changes apply on restart.
#    (route:cache intentionally skipped — routes/web.php uses a closure route.)
# -----------------------------------------------------------------------------
php artisan config:clear >/dev/null 2>&1 || true
php artisan config:cache >/dev/null 2>&1 || true
php artisan view:cache   >/dev/null 2>&1 || true

exec "$@"
