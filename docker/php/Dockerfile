# =============================================
# WebMon Web 컨테이너 — PHP + Apache
# =============================================
FROM php:8.2-apache

RUN apt-get update && apt-get install -y \
    lynx \
    curl \
    whois \
    libssl-dev \
    && docker-php-ext-install mysqli \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

COPY docker/php/php.ini  /usr/local/etc/php/conf.d/webmon.ini
RUN a2enmod rewrite
COPY docker/php/apache.conf /etc/apache2/sites-available/000-default.conf
RUN mkdir -p /var/lib/webmon && chown www-data:www-data /var/lib/webmon && chmod 700 /var/lib/webmon

WORKDIR /var/www/html
COPY webmon/ .
RUN chown -R www-data:www-data /var/www/html && chmod -R 755 /var/www/html

EXPOSE 80
CMD ["apache2-foreground"]
