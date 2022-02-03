FROM php:7.4.27-fpm

RUN usermod -u 1000 www-data
WORKDIR /var/www
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

# Update system core
RUN apt-get update -y && apt-get upgrade -y
RUN docker-php-ext-install mysqli && docker-php-ext-enable mysqli
RUN apt-get install -y git unzip

COPY --from=mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/bin/
RUN install-php-extensions imagick
