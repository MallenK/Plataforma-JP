FROM php:8.2-apache

RUN apt-get update && apt-get install -y \
    unzip \
    git \
    curl \
    libzip-dev \
    libicu-dev \
    && docker-php-ext-install pdo pdo_mysql mysqli intl

# Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

RUN a2enmod rewrite