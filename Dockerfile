# Use an official PHP image with FPM for production
FROM php:8.2-fpm-alpine AS base

# Install system dependencies and Nginx
RUN apk add --no-cache \
    bash \
    curl \
    git \
    libjpeg-turbo-dev \
    libpng-dev \
    libwebp-dev \
    libzip-dev \
    nginx \
    oniguruma-dev \
    && docker-php-ext-install pdo pdo_mysql mbstring zip gd

# Install Composer for PHP dependency management (using the Composer image)
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Set the working directory in the container
WORKDIR /var/www/html

# Copy the application files into the container
COPY . .

# Install Laravel dependencies for production (without dev dependencies)
RUN composer install --no-dev --optimize-autoloader --prefer-dist && \
    chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache && \
    chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# Copy custom Nginx config into the container
COPY ./nginx.conf /etc/nginx/nginx.conf

# Expose port 80 for Nginx
EXPOSE 80

# Build the application cache and config cache for production
RUN php artisan config:cache && \
    php artisan route:cache && \
    php artisan view:cache

# Run PHP-FPM and Nginx in the foreground (without daemonizing Nginx)
CMD ["sh", "-c", "php-fpm -D && nginx -g 'daemon off;'"]