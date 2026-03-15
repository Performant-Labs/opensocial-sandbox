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

# ----- PHP-FPM: listen on TCP port 9000 (not unix socket) -----
RUN sed -i 's|listen = /run/php-fpm.sock|listen = 9000|' /usr/local/etc/php-fpm.d/zz-docker.conf 2>/dev/null || true

# ----- Composer -----
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# ----- Application code -----
WORKDIR /var/www/html
COPY composer.json composer.lock ./
COPY patches/ patches/

# Install dependencies (no dev)
RUN composer install --no-dev --no-interaction --optimize-autoloader --prefer-dist

# Copy the rest of the project
COPY config/ config/
COPY web/modules/custom/ web/modules/custom/
COPY web/sites/default/settings.php web/sites/default/settings.php
COPY web/sites/default/default.services.yml web/sites/default/default.services.yml
COPY web/.htaccess web/.htaccess
COPY docker/settings.docker.php web/sites/default/settings.docker.php

# ----- Directories & permissions -----
RUN mkdir -p /var/www/html/private \
             /var/www/html/web/sites/default/files \
    && chown -R www-data:www-data \
         /var/www/html/web/sites/default/files \
         /var/www/html/private \
         /var/www/html/web/sites/default/settings.php \
    && chmod 755 /var/www/html/web/sites/default \
    && chmod 644 /var/www/html/web/sites/default/settings.php

# ----- Drush available on PATH -----
ENV PATH="/var/www/html/vendor/bin:${PATH}"

# ----- Entrypoint -----
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 9000

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
