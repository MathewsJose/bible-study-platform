FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --prefer-dist --no-interaction --no-progress --optimize-autoloader --no-scripts

FROM php:8.5-fpm-alpine AS app
WORKDIR /var/www/html

RUN apk add --no-cache icu-dev libpq-dev libzip-dev oniguruma-dev zip \
    && docker-php-ext-install intl mbstring pcntl pdo_pgsql zip

COPY --from=vendor /app/vendor ./vendor
COPY . .

RUN chown -R www-data:www-data storage bootstrap/cache

USER www-data

EXPOSE 9000
CMD ["php-fpm"]
