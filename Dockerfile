FROM node:24-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY resources ./resources
COPY vite.config.js ./
RUN npm run build

FROM php:8.3-cli-alpine AS php-base
RUN apk add --no-cache icu-dev libpq-dev libzip-dev oniguruma-dev \
    && docker-php-ext-install bcmath intl mbstring opcache pdo_pgsql zip
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /app

FROM php-base AS development
COPY composer.json composer.lock ./
RUN composer install --no-interaction --prefer-dist --no-scripts
COPY . .
COPY --from=assets /app/public/build ./public/build
RUN composer dump-autoload --optimize
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]

FROM php-base AS production
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist --no-scripts --optimize-autoloader
COPY . .
COPY --from=assets /app/public/build ./public/build
RUN composer dump-autoload --no-dev --optimize --classmap-authoritative
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
