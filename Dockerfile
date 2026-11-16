
FROM php:8.4-cli-bookworm

RUN apt-get -o Acquire::Retries=10 \
        -o Acquire::http::Timeout=120 \
        -o Acquire::https::Timeout=120 update \
    && apt-get -o Acquire::Retries=10 \
        -o Acquire::http::Timeout=120 \
        -o Acquire::https::Timeout=120 install -y --no-install-recommends \
       git unzip libpq-dev libzip-dev libicu-dev libonig-dev \
    && docker-php-ext-install -j$(nproc) \
       pdo_pgsql pgsql pcntl zip intl mbstring \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

EXPOSE 8000

CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
