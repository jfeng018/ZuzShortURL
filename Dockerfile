FROM php:8.3-apache
RUN apt-get update && apt-get install -y \
    libpq-dev \
    && docker-php-ext-install pdo_pgsql \
    && apt-get clean && rm -rf /var/lib/apt/lists/*
RUN a2enmod rewrite env
COPY 000-default.conf /etc/apache2/sites-available/000-default.conf
RUN echo "Listen 8437" > /etc/apache2/ports.conf
WORKDIR /var/www/html
COPY . /var/www/html
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html
EXPOSE 8437
CMD ["apache2-foreground"]