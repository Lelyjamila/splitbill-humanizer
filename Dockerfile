FROM php:8.2-apache

# Install ekstensi cURL dan SQLite3
RUN apt-get update && apt-get install -y \
    libcurl4-openssl-dev \
    sqlite3 \
    libsqlite3-dev \
    && docker-php-ext-install pdo_sqlite curl \
    && a2enmod rewrite

# Salin semua berkas ke folder web server
COPY . /var/www/html/

# Atur hak akses folder data
RUN mkdir -p /var/www/html/data && chown -R www-data:www-data /var/www/html/

EXPOSE 80
