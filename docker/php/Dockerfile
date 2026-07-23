FROM mysql:8.4 AS mysql-client

FROM php:8.4-fpm-alpine AS base

RUN apk add --no-cache \
    bash \
    curl \
    git \
    icu-dev \
    libzip-dev \
    oniguruma-dev \
    unzip \
    zip \
    tar \
    gzip \
    # RC1-4C — Oracle MySQL 8.4 client for mysqldump (matches mysql:8.4 server auth)
    gcompat \
    libstdc++ \
    && docker-php-ext-configure intl \
    && docker-php-ext-install \
    bcmath \
    intl \
    mbstring \
    opcache \
    pdo_mysql \
    zip

COPY --from=mysql-client /usr/bin/mysqldump /usr/bin/mysql /usr/bin/
COPY --from=mysql-client /usr/lib64/ /usr/lib64/
COPY --from=mysql-client /lib64/ /lib64/

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

FROM base AS development

COPY docker/php/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN sed -i 's/\r$//' /usr/local/bin/entrypoint.sh \
    && chmod +x /usr/local/bin/entrypoint.sh

ENTRYPOINT ["entrypoint.sh"]
CMD ["php-fpm"]

FROM base AS production

COPY apps/api/composer.json apps/api/composer.lock* ./
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-scripts

COPY apps/api/ ./

COPY docker/php/entrypoint.prod.sh /usr/local/bin/entrypoint.prod.sh
RUN chmod +x /usr/local/bin/entrypoint.prod.sh \
    && composer dump-autoload --optimize \
    && php artisan package:discover --ansi \
    && mkdir -p storage/app/public storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

# Config/route/view caches are built at deploy/start (after runtime env is available).
# php-fpm master runs as root and workers drop to www-data.
ENTRYPOINT ["entrypoint.prod.sh"]
CMD ["php-fpm"]
