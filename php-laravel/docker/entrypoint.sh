#!/bin/sh
# Container bootstrap for both Dockerfile targets.
#   dev  (APP_ENV unset/local): source is bind-mounted; installs Composer deps, creates .env + APP_KEY if missing.
#   prod (APP_ENV=production, baked into the prod target): code and vendor are in the image; configuration comes
#        only from environment variables (no .env), APP_KEY is required, config/route/view caches are built here.
# Both: wait for the database, run migrations, then recompute the CPF blind index if its key changed (CPF_HASH_KEY,
#   or APP_KEY when it is empty): `patients:rehash-cpf --if-key-changed` compares a fingerprint of the key (a hash,
#   never the key) stored in storage/app/private/, so changing the key and restarting the api is enough. Seeding:
#   dev  -> on by default (the seeder only fills an empty table); SEED_ON_START=false disables it.
#   prod -> only when SEED_ON_START=true (e.g. a public demo).
set -e

cd /var/www/html

wait_for_database() {
    echo "[entrypoint] Waiting for the database..."
    tries=0
    until php -r 'require "vendor/autoload.php"; $app = require "bootstrap/app.php"; $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap(); Illuminate\Support\Facades\DB::connection()->getPdo();' >/dev/null 2>&1; do
        tries=$((tries + 1))
        if [ "$tries" -ge 60 ]; then
            echo "[entrypoint] Database not reachable, giving up." >&2
            exit 1
        fi
        sleep 2
    done
}

if [ "${APP_ENV:-}" = "production" ]; then
    if [ -z "${APP_KEY:-}" ]; then
        echo "[entrypoint] APP_KEY is not set. Generate one with: php artisan key:generate --show" >&2
        exit 1
    fi

    echo "[entrypoint] Caching config, routes and views"
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache

    wait_for_database
    php artisan migrate --force
    php artisan patients:rehash-cpf --if-key-changed

    if [ "${SEED_ON_START:-false}" = "true" ]; then
        php artisan db:seed --force
    fi
else
    if [ ! -f vendor/autoload.php ]; then
        echo "[entrypoint] Installing composer dependencies..."
        composer install --no-interaction --prefer-dist --no-progress
    fi

    if [ ! -f .env ]; then
        echo "[entrypoint] Creating .env from .env.example"
        cp .env.example .env
    fi

    if ! grep -qE '^APP_KEY=.+' .env; then
        echo "[entrypoint] Generating APP_KEY"
        php artisan key:generate --force
    fi

    mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache
    chmod -R ug+rwX,o+rwX storage bootstrap/cache

    wait_for_database
    php artisan migrate --force
    php artisan patients:rehash-cpf --if-key-changed

    if [ "${SEED_ON_START:-true}" != "false" ]; then
        php artisan db:seed --force
    fi
fi

exec "$@"
