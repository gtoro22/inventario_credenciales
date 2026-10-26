# syntax=docker/dockerfile:1.7
#
# Etapas:
#   vendor  -> dependencias PHP de producción (composer)
#   assets  -> compila el frontend React/Ant Design (vite build)
#   base    -> PHP-FPM + extensiones
#   app     -> backend de producción (código + vendor + assets)   [target: app]
#   web     -> nginx con public/ y los assets compilados           [target: web]
#   dev     -> backend de desarrollo (código por bind mount)       [target: dev]

ARG PHP_VERSION=8.3
ARG NODE_VERSION=22

# ---------- vendor ----------
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction --ignore-platform-reqs
COPY . .
RUN composer dump-autoload --no-dev --optimize --no-scripts

# ---------- assets ----------
FROM node:${NODE_VERSION}-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY vite.config.js ./
COPY resources ./resources
COPY public ./public
RUN npm run build

# ---------- base ----------
FROM php:${PHP_VERSION}-fpm-alpine AS base
ADD --chmod=0755 https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/
RUN install-php-extensions pdo_mysql opcache bcmath \
    && apk add --no-cache tini
WORKDIR /var/www/html
COPY docker/php/php.ini /usr/local/etc/php/conf.d/zz-app.ini
COPY docker/php/entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint
ENTRYPOINT ["/sbin/tini", "--", "entrypoint"]
CMD ["php-fpm"]

# ---------- app (producción) ----------
FROM base AS app
COPY --from=vendor --chown=www-data:www-data /app /var/www/html
COPY --from=assets --chown=www-data:www-data /app/public/build /var/www/html/public/build
RUN mkdir -p storage/framework/{cache/data,sessions,views} storage/logs bootstrap/cache \
    && php artisan package:discover --ansi \
    && chown -R www-data:www-data storage bootstrap/cache
USER www-data

# ---------- web (nginx) ----------
FROM nginx:1.27-alpine AS web
COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf
COPY --from=app /var/www/html/public /var/www/html/public

# ---------- dev ----------
FROM base AS dev
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
ENV APP_ENV=local
