FROM php:8.2-apache

# Dependencias
RUN apt-get update && apt-get install -y \
    libicu-dev \
    && docker-php-ext-install intl

# Extensiones MySQL
RUN docker-php-ext-install mysqli pdo pdo_mysql

# Apache config
RUN a2enmod rewrite
RUN sed -i 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf

# DocumentRoot → public
ENV APACHE_DOCUMENT_ROOT /var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf

# Copiar proyecto
COPY . /var/www/html

# Permisos (CLAVE)
RUN chown -R www-data:www-data /var/www/html
RUN chmod -R 775 /var/www/html/writable