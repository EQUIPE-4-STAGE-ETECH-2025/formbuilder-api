FROM php:8.2.12-cli

WORKDIR /app

RUN apt-get update && apt-get install -y \
    git unzip libpq-dev zip libzip-dev libicu-dev libonig-dev libxml2-dev \
    && docker-php-ext-install intl pdo pdo_pgsql zip

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

COPY . /app

# Créer le fichier .env pour la production
RUN echo "APP_ENV=prod" > .env && \
    echo "APP_DEBUG=false" >> .env && \
    echo "APP_SECRET=\${APP_SECRET}" >> .env && \
    echo "DATABASE_HOST=\${DATABASE_HOST}" >> .env && \
    echo "DATABASE_PORT=\${DATABASE_PORT}" >> .env && \
    echo "DATABASE_NAME=\${DATABASE_NAME}" >> .env && \
    echo "DATABASE_USER=\${DATABASE_USER}" >> .env && \
    echo "DATABASE_PASSWORD=\${DATABASE_PASSWORD}" >> .env && \
    echo "JWT_SECRET=\${JWT_SECRET}" >> .env && \
    echo "FRONTEND_URL=\${FRONTEND_URL}" >> .env && \
    echo "MAILER_DSN=\${MAILER_DSN}" >> .env && \
    echo "MESSENGER_TRANSPORT_DSN=doctrine://default?auto_setup=0" >> .env

# Autoriser les plugins Composer et installer les dépendances
ENV COMPOSER_ALLOW_SUPERUSER=1
RUN composer install --no-interaction --prefer-dist --optimize-autoloader --no-dev

# Optimisations pour la production
RUN composer dump-autoload --optimize --classmap-authoritative

# Nettoyer le cache et réchauffer
RUN php bin/console cache:clear --env=prod --no-debug
RUN php bin/console cache:warmup --env=prod --no-debug

CMD php -S 0.0.0.0:8080 -t public
