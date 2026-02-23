FROM php:8.4-fpm

# System dependencies
RUN apt-get update && apt-get install -y --no-install-recommends \
    libfreetype6-dev \
    libjpeg62-turbo-dev \
    libpng-dev \
    libicu-dev \
    libcurl4-openssl-dev \
    libzip-dev \
    unzip \
    git \
    cron \
    default-mysql-client \
    redis-tools \
    && rm -rf /var/lib/apt/lists/*

# PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        mysqli \
        pdo_mysql \
        gd \
        intl \
        curl \
        opcache \
        zip

# Redis extension via PECL
RUN pecl install redis && docker-php-ext-enable redis

# Composer from official image
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Custom PHP config
COPY php/php.ini /usr/local/etc/php/conf.d/99-travium.ini

# Crontab
COPY crontab /etc/cron.d/travium
RUN chmod 0644 /etc/cron.d/travium && crontab /etc/cron.d/travium

# Entrypoint
COPY entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# Config generator
COPY generate-config.php /usr/local/bin/generate-config.php

WORKDIR /var/www/html

ENTRYPOINT ["entrypoint.sh"]
CMD ["php-fpm"]
