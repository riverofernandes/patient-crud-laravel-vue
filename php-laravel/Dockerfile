# syntax=docker/dockerfile:1
#
# Targets:
#   dev  (default, last stage) — PHP CLI + `artisan serve`; source comes from the bind mount in docker-compose.yml
#        and the entrypoint installs Composer deps at runtime. Same behavior as before the multi-stage split.
#   prod — self-contained image: source copied in, `composer install --no-dev`, nginx + php-fpm (supervisord),
#        non-root user, APP_ENV=production / APP_DEBUG=false, config/route/view cache built at start.
#        docker build --target prod -t patients-api:prod php-laravel

# ---------------------------------------------------------------------------
# prod-base: runtime shared by the prod build and the final prod image
# ---------------------------------------------------------------------------
FROM php:8.2-fpm-alpine AS prod-base

RUN apk add --no-cache nginx supervisor \
    && docker-php-ext-install pdo_mysql opcache \
    && cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

COPY docker/prod/php.ini "$PHP_INI_DIR/conf.d/zz-app.ini"
COPY docker/prod/php-fpm.conf /usr/local/etc/php-fpm.d/zz-app.conf
COPY docker/prod/nginx.conf /etc/nginx/nginx.conf
COPY docker/prod/supervisord.conf /etc/supervisord.conf

WORKDIR /var/www/html

# ---------------------------------------------------------------------------
# prod-build: Composer install (no dev deps) + optimized autoloader
# ---------------------------------------------------------------------------
FROM prod-base AS prod-build

RUN apk add --no-cache unzip
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Dependencies first so this layer is cached while only app code changes.
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction --no-progress

COPY . .
# Runs post-autoload-dump (package:discover), regenerating bootstrap/cache without dev-only providers.
RUN composer dump-autoload --optimize --no-dev --no-interaction \
    && mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache

# ---------------------------------------------------------------------------
# prod: final production image
# ---------------------------------------------------------------------------
FROM prod-base AS prod

ENV APP_ENV=production \
    APP_DEBUG=false \
    LOG_CHANNEL=stderr

# Code is owned by root (read-only for the app); only the runtime-writable dirs belong to www-data.
COPY --from=prod-build /var/www/html /var/www/html
RUN chown -R www-data:www-data storage bootstrap/cache

COPY docker/entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN sed -i 's/\r$//' /usr/local/bin/docker-entrypoint.sh && chmod +x /usr/local/bin/docker-entrypoint.sh

USER www-data

EXPOSE 8000

HEALTHCHECK --interval=15s --timeout=5s --start-period=30s --retries=3 \
    CMD wget -qO- http://127.0.0.1:8000/api/health > /dev/null || exit 1

ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["supervisord", "-c", "/etc/supervisord.conf"]

# ---------------------------------------------------------------------------
# dev: default target (must stay the LAST stage so `build: ./php-laravel` keeps building it)
# ---------------------------------------------------------------------------
FROM php:8.2-cli AS dev

RUN apt-get update \
    && apt-get install -y --no-install-recommends git unzip libzip-dev libsqlite3-dev default-mysql-client \
    && docker-php-ext-install pdo_mysql pdo_sqlite zip \
    && rm -rf /var/lib/apt/lists/*

# Code coverage driver for `php artisan test --coverage` (dev only; never installed in the prod image).
RUN pecl install pcov-1.0.12 \
    && docker-php-ext-enable pcov \
    && rm -rf /tmp/pear \
    # The CLI default (128M) is too small for a cold Larastan run (`vendor/bin/phpstan analyse`).
    && echo 'memory_limit=512M' > "$PHP_INI_DIR/conf.d/zz-dev.ini"

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY docker/entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN sed -i 's/\r$//' /usr/local/bin/docker-entrypoint.sh && chmod +x /usr/local/bin/docker-entrypoint.sh

WORKDIR /var/www/html

EXPOSE 8000

ENTRYPOINT ["docker-entrypoint.sh"]
# --no-reload keeps the container's environment (compose `environment:`); without it
# `serve` drops those variables and re-reads only the .env file.
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000", "--no-reload"]
