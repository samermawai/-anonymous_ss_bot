# Use official PHP image as base
FROM php:8.1-fpm

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-install \
    mbstring \
    pdo_mysql \
    bcmath \
    xml

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer
RUN composer --version

# Set working directory
WORKDIR /var/www/html

# Copy application files
COPY . /var/www/html

# Install PHP dependencies with retry logic
RUN composer install --no-dev --optimize-autoloader || (sleep 5 && composer install --no-dev --optimize-autoloader)

# Set permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html \
    && chmod 666 /var/www/html/users.json

# Expose port 80
EXPOSE 80

# Run PHP-FPM
CMD ["php", "-S", "0.0.0.0:80", "-t", "/var/www/html"]
