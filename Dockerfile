FROM php:8.2-apache

# 1. Matikan modul MPM yang bentrok
RUN a2dismod mpm_event || true

# 2. Install ekstensi PHP untuk MySQL (PDO & Mysqli)
RUN docker-php-ext-install pdo pdo_mysql mysqli

# 3. Copy semua file project ke web root
COPY . /var/www/html/

RUN chown -R www-data:www-data /var/www/html

CMD ["apache2-foreground"]