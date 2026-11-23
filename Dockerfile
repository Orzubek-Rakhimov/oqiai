# syntax=docker/dockerfile:1

# Two images come out of this file (see compose.production.yaml):
# - `app`: O'qi AI itself, run by PHP-FPM.
# - `web`: nginx in front of it, with the built JavaScript, CSS and fonts,
#   and the saved voice clips under /storage.
#
# Versions are exact, so the same commit always builds the same images.

FROM composer:2.10.3 AS composer

# Node only builds the JavaScript and CSS; nothing of it reaches the images
# that run. Vite+ needs Node 22.18 or newer, so it comes from Node's own
# image, not from Alpine's packages.
FROM node:24.14.0-alpine AS node

FROM php:8.5.12-fpm-alpine AS php-base
WORKDIR /var/www/html

# The database is SQLite, whose PHP driver (pdo_sqlite) the image already
# has, along with mbstring, ctype, curl, dom, fileinfo, openssl and the
# rest. On top of that:
# - intl: Laravel's number and locale helpers, for the Uzbek screens
# OPcache is built into PHP 8.5; its settings are in docker/php/php.ini.
# Nothing runs on a schedule and nothing draws images, so there is no
# pcntl and no gd. The build fails here if an extension the app relies on
# is missing.
RUN apk add --no-cache icu-libs \
    && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS icu-dev \
    && docker-php-ext-install -j"$(nproc)" intl \
    && apk del .build-deps \
    && php -r 'foreach (["ctype", "curl", "dom", "fileinfo", "filter", "iconv", "intl", "mbstring", "openssl", "pdo_sqlite", "session", "tokenizer", "xml", "Zend OPcache"] as $extension) { if (! extension_loaded($extension)) { fwrite(STDERR, "Missing PHP extension: ".$extension.PHP_EOL); exit(1); } }'
RUN cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

# ---------------------------------------------------------------------------

FROM php-base AS dependencies
COPY --from=composer /usr/bin/composer /usr/local/bin/composer
ENV COMPOSER_ALLOW_SUPERUSER=1
# unzip unpacks the downloaded packages; it stays in this build stage only.
RUN apk add --no-cache unzip

# The dependencies first, so a code change doesn't reinstall them.
COPY composer.json composer.lock ./
RUN composer install --no-dev --prefer-dist --no-interaction --no-progress --no-scripts --no-autoloader

COPY . .
# storage/database holds the SQLite file and its safety copies on the
# server. It is a volume there, which starts out owned like this folder.
RUN mkdir -p storage/app/private storage/app/public storage/database storage/framework/cache/data \
        storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && composer dump-autoload --no-dev --classmap-authoritative --no-interaction --no-scripts \
    && php artisan package:discover --ansi \
    && composer check-platform-reqs --no-dev

# ---------------------------------------------------------------------------

# The JavaScript, CSS and fonts. The build needs PHP as well as Node: the
# Wayfinder plugin asks Laravel for the routes (php artisan
# wayfinder:generate) before Vite bundles the pages. It also downloads the
# fonts (Inter and Nunito) from Bunny Fonts, so the build needs the network.
FROM dependencies AS frontend
COPY --from=node /usr/local/bin/node /usr/local/bin/node
COPY --from=node /usr/local/lib/node_modules /usr/local/lib/node_modules
RUN apk add --no-cache libstdc++ \
    && ln -s ../lib/node_modules/npm/bin/npm-cli.js /usr/local/bin/npm \
    && node --version && npm --version
RUN --mount=type=cache,target=/root/.npm npm ci --no-audit --no-fund
RUN npm run build \
    && test -f public/build/manifest.json

# ---------------------------------------------------------------------------

FROM php-base AS app
COPY --from=dependencies --chown=www-data:www-data /var/www/html /var/www/html
COPY --from=frontend --chown=www-data:www-data /var/www/html/public/build /var/www/html/public/build
COPY docker/php/php.ini /usr/local/etc/php/conf.d/zz-production.ini
COPY docker/php/fpm-pool.conf /usr/local/etc/php-fpm.d/zz-production.conf
COPY --chmod=0755 docker/start-container.sh /usr/local/bin/oqi-entrypoint

USER www-data

# PHP-FPM answers on 9000 (FastCGI, for nginx).
EXPOSE 9000

ENTRYPOINT ["oqi-entrypoint"]
CMD ["php-fpm"]

# ---------------------------------------------------------------------------

# nginx, not as root: it listens on 8080 and keeps its files in /tmp.
# It serves the files of public/ itself; /storage is the public disk, where
# the voice clips for Syllable help are saved. It arrives as a volume.
FROM nginx:1.30.6-alpine3.24 AS web
COPY docker/nginx/nginx.conf /etc/nginx/nginx.conf
COPY --from=frontend /var/www/html/public /var/www/html/public
RUN ln -s /var/www/html/storage/app/public /var/www/html/public/storage
USER nginx
EXPOSE 8080
ENTRYPOINT ["nginx", "-g", "daemon off;"]
