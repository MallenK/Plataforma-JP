FROM php:8.2-apache

# Instalar dependencias
RUN apt-get update && apt-get install -y \
    libicu-dev \
    && docker-php-ext-install intl

# Instalar MySQL
RUN docker-php-ext-install mysqli pdo pdo_mysql

# Activar mod_rewrite
RUN a2enmod rewrite

# Permitir .htaccess
RUN sed -i 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf

# Configurar DocumentRoot
ENV APACHE_DOCUMENT_ROOT /var/www/html/public

RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf