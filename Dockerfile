FROM php:8.2-apache

# 1. Install ekstensi PHP untuk MySQL (PDO & Mysqli)
RUN docker-php-ext-install pdo pdo_mysql mysqli

# 2. Copy semua file project ke web root
COPY . /var/www/html/

# 3. Pastikan permission file rapi
RUN chown -R www-data:www-data /var/www/html

# 4. Trik Utama: Matikan mpm_event TEPAT sebelum Apache dinyalakan (Entrypoint override)
CMD ["sh", "-c", "a2dismod mpm_event || true; apache2-foreground"]