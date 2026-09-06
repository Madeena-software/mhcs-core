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

# ── Python / AI PACS adapter runtime ────────────────────────────────────────
# Install Python 3 and the pinned AI PACS adapter dependencies at image-build
# time so nothing is downloaded when a queue job executes. A virtualenv under
# /opt/pacs-venv is used to avoid system-package conflicts on Debian Bookworm.
# Playwright's Chromium browser is installed into /ms-playwright (world-readable)
# so the non-root www-data queue-worker user can access it at runtime.
# PLAYWRIGHT_BROWSERS_PATH is exported as an image-level ENV so the Python
# process launched by Laravel inherits it without additional configuration.
ENV PLAYWRIGHT_BROWSERS_PATH=/ms-playwright

RUN apt-get update -qq \
    && apt-get install -yqq --no-install-recommends \
        python3 python3-pip python3-venv \
        libzip-dev libicu-dev libonig-dev \
    && rm -rf /var/lib/apt/lists/*

COPY requirements-pacs.txt /tmp/requirements-pacs.txt
RUN python3 -m venv /opt/pacs-venv \
    && /opt/pacs-venv/bin/pip install --no-cache-dir -r /tmp/requirements-pacs.txt \
    && rm /tmp/requirements-pacs.txt

# Prepend the virtualenv bin so `python3` resolves to the venv Python for all
# processes in the container, including queue workers launched by Laravel.
ENV PATH="/opt/pacs-venv/bin:$PATH"

# Install Playwright Chromium browser and its OS-level library dependencies.
# --with-deps installs required apt packages (libnss3, libatk1.0-0, etc.).
# chmod makes the browser directory readable and executable by all users (o+rX)
# so www-data can locate and launch the binary at queue-job runtime.
RUN /opt/pacs-venv/bin/playwright install chromium --with-deps \
    && chmod -R o+rX /ms-playwright

# ── PHP extensions ────────────────────────────────────────────────────────────
RUN docker-php-ext-install bcmath intl mbstring opcache pcntl pdo pdo_mysql zip

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
