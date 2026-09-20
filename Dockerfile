FROM php:8.2-apache

# rewrite: routing. expires/headers: cache statis. deflate: kompresi.
RUN a2enmod rewrite expires headers deflate

WORKDIR /var/www/html

# Konfigurasi cache/kompresi dipasang sebagai conf Apache, bukan file web.
COPY apache-cache.conf /etc/apache2/conf-available/aihebat-cache.conf
RUN a2enconf aihebat-cache

# Copy the entire website source into Apache document root.
COPY . /var/www/html/

# File konfigurasi tidak boleh ikut tersaji di document root.
RUN rm -f /var/www/html/apache-cache.conf

# Tighten permissions for static web files.
RUN find /var/www/html -type d -exec chmod 755 {} \; \
    && find /var/www/html -type f -exec chmod 644 {} \;

EXPOSE 8080

# Cloud Run injects PORT dynamically. Apache defaults to 80, so switch at startup.
CMD sed -i "s/Listen 80/Listen ${PORT}/" /etc/apache2/ports.conf \
    && sed -i "s/:80/:${PORT}/g" /etc/apache2/sites-available/000-default.conf \
    && apache2-foreground
