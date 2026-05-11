FROM php:8.5-fpm

RUN apt-get update \
    && apt-get install -y --no-install-recommends git libicu-dev libpq-dev libzip-dev unzip zip \
    && docker-php-ext-install intl pdo_pgsql zip \
    && pecl install pcov \
    && docker-php-ext-enable pcov \
    && echo "pcov.enabled=1" > /usr/local/etc/php/conf.d/pcov.ini \
    && echo "pcov.directory=/app/src" >> /usr/local/etc/php/conf.d/pcov.ini \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /app
