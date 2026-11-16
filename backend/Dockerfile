FROM composer:2 AS composer
FROM php:8.4-cli-bookworm

RUN apt-get update \
    && apt-get install -y --no-install-recommends libpq-dev unzip \
    && docker-php-ext-install -j"$(nproc)" pdo_pgsql \
    && rm -rf /var/lib/apt/lists/*
COPY --from=composer /usr/bin/composer /usr/local/bin/composer
WORKDIR /app
COPY composer.json composer.lock ./
RUN COMPOSER_ALLOW_SUPERUSER=1 composer install --no-dev --no-interaction --prefer-dist --no-scripts --no-autoloader
COPY . .
RUN COMPOSER_ALLOW_SUPERUSER=1 composer dump-autoload --no-dev --no-interaction --optimize

EXPOSE 8080
CMD ["sh","-ec","php artisan migrate --force && php -r \"$PILOT_BOOTSTRAP_PHP\" && cd public && exec php -S '[::]:8080' ../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php"]
