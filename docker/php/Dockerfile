FROM php:8.5.12-fpm

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        git \
        unzip \
        libpq-dev \
        libicu-dev \
        libzip-dev \
    && rm -rf /var/lib/apt/lists/*

RUN docker-php-ext-install -j$(nproc) pdo_pgsql intl zip bcmath

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

RUN mv "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini"

RUN sed -i 's/pm.max_children = [0-9]*/pm.max_children = 50/' /usr/local/etc/php-fpm.d/www.conf

WORKDIR /app
