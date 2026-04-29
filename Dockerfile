FROM php:8.2-apache

RUN apt-get update && apt-get install -y libicu-dev \
    && docker-php-ext-install intl

RUN docker-php-ext-install mysqli pdo pdo_mysql

# Fix Apache MPM
RUN a2dismod mpm_event mpm_worker 2>/dev/null || true
RUN a2enmod mpm_prefork rewrite

# Point Apache document root to CodeIgniter's public folder
RUN sed -i 's|/var/www/html|/var/www/html/public|g' /etc/apache2/sites-available/000-default.conf
RUN sed -i 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf

COPY . /var/www/html

RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 775 /var/www/html/writable \
    && chmod +x /var/www/html/docker/start.sh

EXPOSE 80

CMD ["/var/www/html/docker/start.sh"]
