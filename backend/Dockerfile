FROM php:8.2-cli

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libzip-dev \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    curl \
    && docker-php-ext-install \
        pdo_mysql \
        zip \
        mbstring \
        bcmath \
        xml \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Create Laravel 12 project
RUN composer create-project --prefer-dist laravel/laravel . "12.*" \
    && chown -R www-data:www-data storage bootstrap/cache \
    && rm -f .env .env.example

# Copy our custom application code (selective to preserve Laravel skeleton defaults)
COPY app/ ./app/
COPY bootstrap/ ./bootstrap/
COPY routes/ ./routes/
COPY database/ ./database/
COPY config/cors.php ./config/cors.php
COPY tests/ ./tests/
COPY entrypoint.sh /tmp/entrypoint.sh

# Set proper permissions
RUN chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

# Move entrypoint script to final location
RUN cp /tmp/entrypoint.sh /usr/local/bin/entrypoint.sh \
    && chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 8000

ENTRYPOINT ["entrypoint.sh"]
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
