FROM php:8.4-fpm-alpine

# Install dependencies for PHP extensions and Composer package extraction
RUN apk add --no-cache \
    curl \
    git \
    libxml2-dev \
    linux-headers \
    oniguruma-dev \
    libzip-dev \
    unzip

# Install PHP extensions required by Laravel and Composer dependencies
RUN docker-php-ext-install \
    bcmath \
    dom \
    mbstring \
    pcntl \
    pdo \
    pdo_mysql \
    sockets \
    zip

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /app
COPY . .

# Install dependencies (production only)
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Set permissions for Laravel
RUN chown -R www-data:www-data /app/storage /app/bootstrap/cache

ENV APP_ENV=production
EXPOSE 8000

HEALTHCHECK --interval=30s --timeout=5s --start-period=5s --retries=3 \
    CMD php artisan --version || exit 1

ENTRYPOINT ["/app/run.sh"]
