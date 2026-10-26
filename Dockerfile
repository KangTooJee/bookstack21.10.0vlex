FROM composer:2.2@sha256:269232678e5ecbe8cb13c4e416e1effab4fe9cb5f72e112de5050d1de05abf5f AS composer
FROM php:8.0.30-apache-bullseye AS runtime-base

RUN sed -i 's|http://deb.debian.org/debian-security|http://archive.debian.org/debian-security|g; s|http://deb.debian.org/debian|http://archive.debian.org/debian|g' /etc/apt/sources.list \
    && apt-get -o Acquire::Check-Valid-Until=false update \
    && apt-get install -y --no-install-recommends \
       unzip libreoffice-writer libreoffice-calc fonts-dejavu-core libpng-dev libjpeg62-turbo-dev libfreetype6-dev libonig-dev libzip-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j2 gd mbstring pdo_mysql zip \
    && a2enmod rewrite \
    && rm -rf /var/lib/apt/lists/*
RUN cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
    && printf 'upload_max_filesize=20M\npost_max_size=24M\nmemory_limit=256M\nmax_execution_time=120\n' > /usr/local/etc/php/conf.d/word-import.ini \
    && sed -i 's|/var/www/html|/var/www/html/public|g' /etc/apache2/sites-available/000-default.conf \
    && printf 'ServerName localhost\n' > /etc/apache2/conf-available/server-name.conf \
    && a2enconf server-name
WORKDIR /var/www/html

# Install exact locked dependencies in the build stage; Composer is absent at runtime.
FROM runtime-base AS dependencies
COPY --from=composer /usr/bin/composer /usr/local/bin/composer
COPY source/BookStack-21.11.1/ ./
RUN sha256sum composer.lock > /tmp/composer-lock.sha256 \
    && composer install --no-dev --prefer-dist --no-interaction --no-progress --no-scripts \
    && composer check-platform-reqs --no-dev \
    && sha256sum -c /tmp/composer-lock.sha256 \
    && php -r 'require "vendor/autoload.php"; if (Illuminate\Foundation\Application::VERSION !== "8.70.2") { exit(1); }'

FROM runtime-base AS app
COPY source/BookStack-21.11.1/ ./
COPY --from=dependencies /var/www/html/vendor/ ./vendor/
RUN php artisan package:discover --ansi \
    && chown -R www-data:www-data storage bootstrap/cache public/uploads
COPY docker/entrypoint.sh /usr/local/bin/bookstack-entrypoint
RUN sed -i 's/\r$//' /usr/local/bin/bookstack-entrypoint && chmod +x /usr/local/bin/bookstack-entrypoint
ENTRYPOINT ["bookstack-entrypoint"]
CMD ["apache2-foreground"]
