FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --no-scripts \
    --no-autoloader \
    --prefer-dist \
    --ignore-platform-reqs

COPY . .
RUN composer dump-autoload --optimize --no-dev

# PHP 8.4 minimum : composer.lock embarque symfony/* 8.x et endroid/qr-code 6.x (php >= 8.4)
FROM php:8.4-fpm-alpine AS runtime

# gd : QR codes PNG + images des PDF (dompdf) — exif : spatie/laravel-medialibrary
# pcntl : arrêt propre de Reverb et du scheduler
RUN apk add --no-cache \
        nginx \
        supervisor \
        postgresql-dev \
        libzip-dev \
        icu-dev \
        libpng-dev \
        libjpeg-turbo-dev \
        freetype-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install \
        pdo_pgsql \
        pgsql \
        zip \
        bcmath \
        intl \
        gd \
        exif \
        pcntl \
        opcache \
    && mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

WORKDIR /var/www
COPY --from=vendor /app /var/www

COPY docker/php.ini /usr/local/etc/php/conf.d/zz-app.ini
COPY docker/nginx.conf /etc/nginx/http.d/default.conf
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

RUN chown -R www-data:www-data /var/www \
    && chmod -R 775 storage bootstrap/cache

ENV APP_ENV=production
EXPOSE 80

ENTRYPOINT ["/entrypoint.sh"]
CMD ["supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
