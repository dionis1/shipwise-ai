FROM php:8.4-fpm-bookworm
RUN apt-get update && apt-get install -y --no-install-recommends libpq-dev libicu-dev libzip-dev unzip git && docker-php-ext-install pdo_pgsql intl zip && pecl install redis && docker-php-ext-enable redis && rm -rf /var/lib/apt/lists/*
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
ARG LOCAL_UID=1000
ARG LOCAL_GID=1000
RUN groupmod -g ${LOCAL_GID} www-data && usermod -u ${LOCAL_UID} www-data
WORKDIR /app
