FROM php:8.2-apache

# Install ekstensi PHP yang dibutuhkan
RUN docker-php-ext-install mysqli pdo pdo_mysql

# Copy semua file project ke Apache
COPY . /var/www/html/

# Set permission folder uploads
RUN chmod -R 775 /var/www/html/uploads \
    && chmod -R 775 /var/www/html/assets \
    && chown -R www-data:www-data /var/www/html/

# Aktifkan mod_rewrite Apache (opsional, untuk .htaccess)
RUN a2enmod rewrite

EXPOSE 80