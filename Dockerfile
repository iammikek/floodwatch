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
RUN apk add --no-cache sqlite-dev sqlite-libs postgresql-dev curl libzip-dev icu-dev \
    && docker-php-ext-configure intl \
    && docker-php-ext-install pdo_sqlite pdo_pgsql zip intl opcache

COPY --from=composer /app/vendor /app/vendor
COPY . /app
COPY --from=frontend /app/public/build /app/public/build
WORKDIR /app

RUN mkdir -p storage/framework/{sessions,views,cache} storage/logs bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache \
    && chmod +x /app/scripts/start.sh

EXPOSE 80

CMD ["/app/scripts/start.sh"]
