# Stage 1: PHP dependencies
FROM composer:2 AS composer
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --optimize-autoloader --no-scripts --no-interaction

# Stage 2: Node/Vite build
FROM node:20-alpine AS node
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci --ignore-scripts
COPY resources/ resources/
COPY vite.config.js ./
COPY public/ public/
RUN npm run build

# Stage 3: Runtime
FROM php:8.4-fpm-alpine

RUN apk add --no-cache \
        nginx \
        supervisor \
        sqlite \
        sqlite-dev \
    && docker-php-ext-install pdo_sqlite bcmath opcache \
    && rm -rf /var/cache/apk/*

WORKDIR /var/www/html

COPY --from=composer /app/vendor vendor/
COPY --from=node /app/public/build public/build/
COPY . .

COPY docker/nginx.conf /etc/nginx/nginx.conf
COPY docker/supervisord.conf /etc/supervisord.conf
COPY docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

RUN mkdir -p /var/www/html/database/sqlite \
    && chown -R www-data:www-data /var/www/html/storage \
    && chown -R www-data:www-data /var/www/html/bootstrap/cache \
    && chmod 775 /var/www/html/storage /var/www/html/bootstrap/cache

ENV APP_ENV=production \
    APP_DEBUG=false \
    DB_CONNECTION=sqlite \
    DB_DATABASE=/var/www/html/database/sqlite/cut.sqlite

EXPOSE 8080
HEALTHCHECK --interval=30s --timeout=5s --start-period=15s \
    CMD wget -qO- http://localhost:8080/api/health || exit 1

ENTRYPOINT ["/entrypoint.sh"]
