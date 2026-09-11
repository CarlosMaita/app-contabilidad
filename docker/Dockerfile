# ---------- Stage 1: assets (Vite + Tailwind) ----------
FROM node:22-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json vite.config.js tailwind.config.js postcss.config.js ./
RUN npm ci
COPY resources ./resources
COPY app/Modules ./app/Modules
RUN npm run build

# ---------- Stage 2: vendor (Composer, sin dev) ----------
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-scripts --prefer-dist \
    --optimize-autoloader --ignore-platform-reqs

# ---------- Stage 3: app (PHP-FPM) ----------
FROM php:8.4-fpm-alpine AS app

RUN apk add --no-cache icu-libs libzip libpng libjpeg-turbo freetype postgresql-libs \
 && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS \
        icu-dev libzip-dev libpng-dev libjpeg-turbo-dev freetype-dev postgresql-dev linux-headers \
 && docker-php-ext-configure gd --with-freetype --with-jpeg \
 && docker-php-ext-install -j"$(nproc)" pdo_pgsql bcmath intl gd zip opcache pcntl \
 && pecl install redis \
 && docker-php-ext-enable redis \
 && apk del .build-deps

WORKDIR /var/www/html

COPY docker/php.prod.ini /usr/local/etc/php/conf.d/99-app.ini

COPY --chown=www-data:www-data . .
COPY --from=vendor --chown=www-data:www-data /app/vendor ./vendor
COPY --from=assets --chown=www-data:www-data /app/public/build ./public/build

COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh \
 && chown -R www-data:www-data storage bootstrap/cache

USER www-data

ENTRYPOINT ["entrypoint.sh"]
CMD ["php-fpm"]

# ---------- Stage 4: web (nginx con los estáticos) ----------
FROM nginx:alpine AS web
COPY docker/nginx.conf /etc/nginx/conf.d/default.conf
COPY --from=app /var/www/html/public /var/www/html/public
