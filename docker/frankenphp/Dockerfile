FROM dunglas/frankenphp:1.11-php8.5

RUN install-php-extensions \
    pdo_pgsql \
    intl \
    zip \
    bcmath

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

RUN mv "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini"

WORKDIR /app
