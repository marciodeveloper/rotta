FROM node:24-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY resources ./resources
COPY vite.config.js ./
RUN npm run build

FROM php:8.3-fpm-alpine AS php-base
RUN apk add --no-cache icu-dev libpq-dev libzip-dev oniguruma-dev libpng-dev libjpeg-turbo-dev libwebp-dev freetype-dev postgresql-client \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install bcmath exif gd intl mbstring opcache pdo_pgsql zip \
    && addgroup -g 10001 -S rotta \
    && adduser -u 10001 -S -D -G rotta rotta
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /app

FROM php-base AS development
COPY composer.json composer.lock ./
RUN composer install --no-interaction --prefer-dist --no-scripts
COPY . .
COPY --from=assets /app/public/build ./public/build
RUN composer dump-autoload --optimize
RUN touch .env && chown rotta:rotta .env && chmod 400 .env \
    && chown -R rotta:rotta storage bootstrap/cache
USER rotta
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]

FROM php-base AS production
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist --no-scripts --optimize-autoloader
COPY . .
COPY --from=assets /app/public/build ./public/build
RUN composer dump-autoload --no-dev --optimize --classmap-authoritative
RUN touch .env && chown rotta:rotta .env && chmod 400 .env \
    && chown -R rotta:rotta storage bootstrap/cache
USER rotta
HEALTHCHECK --interval=30s --timeout=5s --start-period=15s --retries=3 CMD ["php-fpm", "-t"]
CMD ["php-fpm", "--nodaemonize"]
