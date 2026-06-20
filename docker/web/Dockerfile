FROM php:8.2-apache

# Install system dependencies
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libzip-dev \
    libicu-dev \
    libonig-dev \
    unzip \
    git \
    curl \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
    mysqli \
    pdo_mysql \
    gd \
    zip \
    intl \
    mbstring \
    opcache \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Enable Apache mod_rewrite
RUN a2enmod rewrite
RUN a2enmod headers

# Set working directory
WORKDIR /var/www/html

# Copy custom Apache configuration (DocumentRoot -> ./public)
COPY docker/web/apache.conf /etc/apache2/sites-available/000-default.conf

# Expose port 80
EXPOSE 80
