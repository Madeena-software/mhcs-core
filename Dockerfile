# syntax=docker/dockerfile:1
# Source: Madeena-software/deploy-templates/templates/prod/standard-dockerfile
# @ 569a30d4a089b0ee404ed6e963fdd2dfd96d3787
# MHCS specialization: PHP 8.4, Vite-compatible inputs, least-privilege
# application runtime, and separate process roles in docker-compose.prod.yml.

FROM php:8.4-cli AS composer-deps

RUN apt-get update -qq \
    && apt-get install -yqq --no-install-recommends unzip git libzip-dev libicu-dev libonig-dev ca-certificates \
    && rm -rf /var/lib/apt/lists/* \
    && docker-php-ext-install intl mbstring zip

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist --no-scripts --optimize-autoloader

FROM node:24-alpine AS node-builder

ENV NPM_CONFIG_FETCH_RETRIES=5 \
    NPM_CONFIG_FETCH_RETRY_FACTOR=2 \
    NPM_CONFIG_FETCH_RETRY_MINTIMEOUT=20000 \
    NPM_CONFIG_FETCH_RETRY_MAXTIMEOUT=120000 \
    NPM_CONFIG_FETCH_TIMEOUT=300000

WORKDIR /app
COPY package.json package-lock.json vite.config.js ./
COPY resources/ ./resources/
COPY tests/JavaScript/ ./tests/JavaScript/
RUN --mount=type=cache,target=/root/.npm \
    npm ci --no-audit --no-fund --prefer-offline \
    && npm run build

FROM php:8.4-fpm AS app

RUN apt-get update -qq \
    && apt-get install -yqq --no-install-recommends libzip-dev libicu-dev libonig-dev \
    && rm -rf /var/lib/apt/lists/* \
    && docker-php-ext-install bcmath intl mbstring opcache pcntl pdo pdo_mysql zip

WORKDIR /var/www/html
COPY --chown=www-data:www-data . .
COPY --from=composer-deps --chown=www-data:www-data /app/vendor ./vendor
COPY --from=node-builder --chown=www-data:www-data /app/public/build ./public/build
COPY docker/php.ini "$PHP_INI_DIR/conf.d/99-mhcs.ini"

RUN mkdir -p storage/app/private storage/framework/cache/data storage/framework/sessions \
    storage/framework/views storage/logs bootstrap/cache \
    /var/www/public-files \
    && chown -R www-data:www-data storage bootstrap/cache /var/www/public-files

RUN php artisan package:discover --ansi \
    && php artisan filament:assets --ansi \
    && chown -R www-data:www-data bootstrap/cache public storage

COPY docker/entrypoint.sh /usr/local/bin/mhcs-entrypoint
RUN chmod 0755 /usr/local/bin/mhcs-entrypoint

USER www-data
HEALTHCHECK --interval=30s --timeout=5s --start-period=60s --retries=3 \
  CMD php -r '$s=@fsockopen("127.0.0.1",9000);exit($s===false?1:0);'

ENTRYPOINT ["/usr/local/bin/mhcs-entrypoint"]
CMD ["php-fpm", "--nodaemonize"]
