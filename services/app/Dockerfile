FROM composer:2.6 AS composer


FROM php:8.2-apache

RUN apt-get update && \
    apt-get install -y --no-install-recommends \
        libpq-dev \
        unzip \
        && \
    :

RUN a2enmod rewrite

ARG ALBATROSS_BASE_PATH

RUN { \
        echo "<VirtualHost *:80>"; \
        echo "    Alias $ALBATROSS_BASE_PATH/ /var/www/html/public/"; \
        echo "    DocumentRoot /var/www/html/public/"; \
        echo "</VirtualHost>"; \
    } > /etc/apache2/sites-available/000-default.conf

RUN docker-php-ext-install pdo_pgsql

COPY --from=composer /usr/bin/composer /usr/bin/

WORKDIR /var/www/html/

COPY composer.json composer.lock /var/www/html/

RUN composer install --no-dev --no-interaction

RUN mkdir /var/www/html/twig-cache

RUN mkdir /tmp/session && chown www-data:www-data /tmp/session
