FROM php:8.2-apache

RUN a2enmod rewrite

RUN docker-php-ext-install mysqli pdo pdo_mysql

COPY . /var/www/html/

RUN mkdir -p /var/www/html/uploads /var/www/html/assets && \
    chown -R www-data:www-data /var/www/html/

EXPOSE 80