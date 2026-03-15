FROM php:8.3-fpm-bookworm

# ----- System packages + PHP extensions -----
RUN apt-get update && apt-get install -y --no-install-recommends \
      curl unzip git \
      libpng-dev libjpeg62-turbo-dev libfreetype6-dev libwebp-dev \
      libzip-dev libicu-dev libxml2-dev libonig-dev \
      mariadb-client \
    && docker-php-ext-configure gd \
         --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j$(nproc) \
         gd opcache pdo_mysql zip intl bcmath mbstring xml \
    && pecl install apcu && docker-php-ext-enable apcu \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# ----- PHP production tuning -----
RUN cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
COPY docker/php-overrides.ini "$PHP_INI_DIR/conf.d/99-overrides.ini"

# ----- Composer -----
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# ----- Drush available on PATH -----
ENV PATH="/var/www/html/vendor/bin:${PATH}"

# ----- Entrypoint -----
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
COPY docker/settings.docker.php /usr/local/share/settings.docker.php
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 9000

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
