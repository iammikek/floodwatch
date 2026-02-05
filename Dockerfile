# Frontend build
FROM node:20-alpine AS frontend
WORKDIR /app
COPY package.json yarn.lock ./
RUN yarn install --frozen-lockfile
COPY . .
RUN yarn build

# Composer dependencies
FROM composer:2 AS composer
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader
COPY . .
RUN composer dump-autoload --optimize

# Runtime
FROM php:8.4-cli-alpine
RUN apk add --no-cache sqlite-dev sqlite-libs curl libzip-dev icu-dev \
    && docker-php-ext-configure intl \
    && docker-php-ext-install pdo_sqlite zip intl opcache

COPY --from=composer /app/vendor /app/vendor
COPY --from=frontend /app/public/build /app/public/build
COPY . /app
WORKDIR /app

RUN mkdir -p storage/framework/{sessions,views,cache} storage/logs bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

EXPOSE 80

CMD ["sh", "-c", "touch database/database.sqlite 2>/dev/null || true && php artisan migrate --force && php artisan config:cache && php artisan route:cache && php artisan view:cache && exec php artisan serve --host=0.0.0.0 --port=${PORT:-80}"]
