FROM php:8.5-apache

# PHP extensions needed by Symfony, Doctrine (MariaDB), intl (Bulgarian), GD (WebP covers)
ADD --chmod=0755 https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/
RUN install-php-extensions intl pdo_mysql gd zip opcache \
    && apt-get update && apt-get install -y --no-install-recommends git unzip \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Same layout idea as on the hosting: the web root is public_html
ENV APACHE_DOCUMENT_ROOT=/app/public_html
COPY docker/apache/site.conf /etc/apache2/sites-available/000-default.conf
COPY docker/php/php.ini /usr/local/etc/php/conf.d/zz-app.ini
RUN a2enmod rewrite headers

WORKDIR /app
