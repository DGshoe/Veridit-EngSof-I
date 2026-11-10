# syntax=docker/dockerfile:1
#
# Veridit Identity Service — application image (multi-stage)
#   base        PHP 8.3-FPM + extensions + Composer (used directly in development)
#   test        dependencies WITH dev packages; runs the test suite on deploy
#   production  optimized, code baked into the image, non-root user

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

############################
# test — usage: docker run --rm veridit-identity:test php artisan test
############################
FROM base AS test

# Dependencies first (cached while composer.* do not change)
COPY composer.json composer.lock ./
RUN composer install --no-interaction --no-progress --no-scripts --prefer-dist

COPY . .
# Empty .env: every test setting comes from phpunit.xml (no secrets in the image).
RUN composer dump-autoload --optimize \
    && mkdir -p storage/framework/cache/data storage/framework/views storage/framework/sessions storage/logs storage/api-docs \
    && touch .env

CMD ["php", "artisan", "test"]

############################
# production
############################
FROM base AS production

COPY docker/php/conf.d/production.ini /usr/local/etc/php/conf.d/zz-production.ini

COPY composer.json composer.lock ./
RUN composer install --no-dev --optimize-autoloader --classmap-authoritative \
        --no-interaction --no-progress --no-scripts --prefer-dist

COPY . .

# Code is owned by root (read-only for the app user); only runtime dirs are writable.
RUN rm -rf tests docker deploy \
    && composer dump-autoload --no-dev --optimize --classmap-authoritative \
    && mkdir -p storage/framework/cache/data storage/framework/views storage/framework/sessions \
                storage/logs storage/api-docs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod +x /var/www/html/artisan

COPY --chmod=755 docker/php/entrypoint.prod.sh /usr/local/bin/entrypoint

USER www-data

# Swagger generated at build time (L5_SWAGGER_GENERATE_ALWAYS=false in production)
RUN php artisan package:discover --ansi && php artisan l5-swagger:generate

ENTRYPOINT ["entrypoint"]
CMD ["php-fpm"]
