FROM composer:2.2 AS composer
FROM php:7.4-apache-bullseye

RUN sed -i 's|http://deb.debian.org/debian-security|http://archive.debian.org/debian-security|g; s|http://deb.debian.org/debian|http://archive.debian.org/debian|g' /etc/apt/sources.list \
    && apt-get -o Acquire::Check-Valid-Until=false update && apt-get install -y --no-install-recommends \
    git unzip libreoffice-writer fonts-dejavu-core libpng-dev libjpeg62-turbo-dev libfreetype6-dev libonig-dev libzip-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j2 gd mbstring pdo_mysql zip \
    && a2enmod rewrite \
    && rm -rf /var/lib/apt/lists/*
RUN printf 'upload_max_filesize=20M\npost_max_size=24M\nmemory_limit=256M\nmax_execution_time=120\n' > /usr/local/etc/php/conf.d/word-import.ini
COPY --from=composer /usr/bin/composer /usr/local/bin/composer
WORKDIR /var/www/html
COPY source/BookStack-21.10.1/ ./
RUN composer install --no-dev --prefer-dist --no-interaction --no-progress \
    && chown -R www-data:www-data storage bootstrap/cache public/uploads \
    && sed -i 's|/var/www/html|/var/www/html/public|g' /etc/apache2/sites-available/000-default.conf
COPY docker/entrypoint.sh /usr/local/bin/bookstack-entrypoint
RUN sed -i 's/\r$//' /usr/local/bin/bookstack-entrypoint && chmod +x /usr/local/bin/bookstack-entrypoint
ENTRYPOINT ["bookstack-entrypoint"]
CMD ["apache2-foreground"]
