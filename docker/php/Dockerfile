# syntax=docker/dockerfile:1
#
# Veridit Identity Service — application image (multi-stage)
#   base        PHP 8.3-FPM + extensions + Composer (used directly in development)
#   test        (Phase 7) dependencies with dev packages, used to run tests on deploy
#   production  (Phase 7) optimized, code baked in, non-root

############################
# base
############################
FROM php:8.3-fpm-bookworm AS base

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        libpq-dev libicu-dev libzip-dev libsqlite3-dev unzip git \
    && docker-php-ext-install -j"$(nproc)" pdo_pgsql pdo_sqlite intl zip opcache \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

ENV COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_HOME=/tmp/composer

WORKDIR /var/www/html

EXPOSE 9000
CMD ["php-fpm"]
