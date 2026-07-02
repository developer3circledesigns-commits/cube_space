# Dockerfile
FROM php:8.2-apache

# Enable Apache modules
RUN apt-get update && apt-get install -y \
    git unzip curl libpng-dev libjpeg-dev \
    && a2enmod rewrite \
    && a2enmod headers

# PHP extensions
RUN docker-php-ext-install pdo_mysql mysqli gd exif

# Copy php.ini configuration
COPY php.ini /usr/local/etc/php/conf.d/custom.ini

# Copy project files
COPY public_html/ /var/www/html/
COPY src/ /var/www/html/src/

# Set proper permissions
RUN chown -R www-data:www-data /var/www/html
RUN chmod -R 755 /var/www/html

# Create uploads directory
RUN mkdir -p /var/www/html/uploads
RUN chown -R www-data:www-data /var/www/html/uploads

EXPOSE 80 8080

# Simple Apache configuration for php
COPY httpd.conf /etc/apache2/sites-available/cubespace.conf
RUN a2ensite cubespace.conf

# Enable 8080 port listening in ports.conf
RUN echo "Listen 8080" >> /etc/apache2/ports.conf

# Fix Apache runtime directory issue
RUN mkdir -p /var/run/apache2 && chown www-data:www-data /var/run/apache2

# Ensure temp directory exists and is writable
RUN mkdir -p /tmp && chmod 1777 /tmp

# Create entrypoint script to ensure Apache environment is set
RUN echo '#!/bin/bash\nset -e\nsource /etc/apache2/envvars\nexec "$@"' > /tmp/docker-entrypoint.sh && chmod +x /tmp/docker-entrypoint.sh

ENTRYPOINT ["/tmp/docker-entrypoint.sh"]
CMD ["apache2", "-D", "FOREGROUND"]
