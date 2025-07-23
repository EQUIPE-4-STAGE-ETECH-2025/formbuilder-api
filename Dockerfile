FROM php:8.2.12-cli

WORKDIR /app

RUN apt-get update && apt-get install -y \
    git unzip libpq-dev zip libzip-dev libicu-dev libonig-dev libxml2-dev \
    && docker-php-ext-install intl pdo pdo_pgsql zip

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

COPY . /app

# Autoriser les plugins Composer et installer les dépendances
ENV COMPOSER_ALLOW_SUPERUSER=1
RUN composer install --no-interaction --prefer-dist --optimize-autoloader

CMD php -S 0.0.0.0:8080 -t public
