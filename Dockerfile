FROM php:8.2-apache

RUN a2dismod mpm_event mpm_worker 2>/dev/null; \
    a2enmod mpm_prefork rewrite

RUN docker-php-ext-install mysqli pdo pdo_mysql

COPY . /var/www/html/

RUN chmod -R 775 /var/www/html/uploads \
    && chmod -R 775 /var/www/html/assets \
    && chown -R www-data:www-data /var/www/html/

EXPOSE 80