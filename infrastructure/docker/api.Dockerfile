FROM php:8.4-cli-bookworm
RUN apt-get update && apt-get install -y --no-install-recommends libpq-dev libicu-dev libzip-dev libonig-dev unzip git \
    && docker-php-ext-install pdo_pgsql intl zip mbstring \
    && pecl install redis && docker-php-ext-enable redis \
    && rm -rf /var/lib/apt/lists/*
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
COPY infrastructure/docker/session.ini /usr/local/etc/php/conf.d/buildcore-session.ini
WORKDIR /app/apps/api
COPY apps/api/composer.json apps/api/composer.lock apps/api/symfony.lock ./
RUN composer install --no-interaction --prefer-dist --no-scripts
COPY apps/api/ ./
COPY infrastructure/catalog.json /app/infrastructure/catalog.json
COPY docs/openapi.json /app/docs/openapi.json
RUN mkdir -p var/uploads && composer dump-autoload --optimize --no-scripts
EXPOSE 8000
CMD ["php", "-S", "0.0.0.0:8000", "-t", "public", "public/index.php"]
