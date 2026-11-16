FROM composer:2 AS composer
FROM php:8.4-apache-bookworm

RUN apt-get update \
    && apt-get install -y --no-install-recommends libpq-dev unzip \
    && docker-php-ext-install -j"$(nproc)" pdo_pgsql \
    && rm -rf /var/lib/apt/lists/* \
    && a2dismod mpm_event mpm_worker \
    && a2enmod mpm_prefork rewrite \
    && sed -ri 's/^Listen 80$/Listen 8080/' /etc/apache2/ports.conf \
    && sed -ri 's!<VirtualHost \*:80>!<VirtualHost *:8080>!; s!/var/www/html!/var/www/html/public!g' /etc/apache2/sites-available/000-default.conf \
    && printf 'ServerName localhost\n<Directory /var/www/html/public>\n    AllowOverride All\n    Require all granted\n</Directory>\n' > /etc/apache2/conf-available/laravel.conf \
    && a2enconf laravel

COPY --from=composer /usr/bin/composer /usr/local/bin/composer
WORKDIR /var/www/html
COPY composer.json composer.lock ./
RUN COMPOSER_ALLOW_SUPERUSER=1 composer install --no-dev --no-interaction --prefer-dist --no-scripts --no-autoloader
COPY . .
RUN COMPOSER_ALLOW_SUPERUSER=1 composer dump-autoload --no-dev --no-interaction --optimize \
    && chown -R www-data:www-data storage bootstrap/cache

EXPOSE 8080
