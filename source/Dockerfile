# Stage 1: Composer dependency installation
FROM composer:latest AS composer
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --optimize-autoloader

# Stage 2: Application image
FROM php:8.1-apache
WORKDIR /var/www/html

# Install necessary system dependencies
RUN apt-get update && apt-get install -y \
    libpq-dev \
    libzip-dev \
    zip \
    unzip \
    git \
    && rm -rf /var/lib/apt/lists/*

# Install Redis server (without php-redis package)
RUN apt-get update && apt-get install -y redis-server

# Install PECL and Redis extension
RUN apt-get install -y libssl-dev && \
    pecl install redis && \
    docker-php-ext-enable redis

# Install necessary PHP extensions
RUN docker-php-ext-install pdo pdo_pgsql zip

# Copy installed dependencies from the Composer stage
COPY --from=composer /app/vendor ./vendor

# Copy the rest of the application
COPY . .

# Expose port 80
EXPOSE 80

# Start Apache
CMD ["apache2-foreground"]