FROM php:7.1-apache

ENV DEBIAN_FRONTEND noninteractive

RUN apt-get update -q \
  && apt-get install unzip git libicu-dev \
    -y --no-install-recommends

RUN docker-php-ext-install intl sockets \
	&& curl -sS https://getcomposer.org/installer | php \
	&& mv composer.phar /usr/local/bin/composer

RUN a2enmod rewrite

ENV PRODUCTION=true

COPY ./.docker/apache/docker-php-entrypoint /usr/local/bin/
COPY ./.docker/apache/web-prod.conf /etc/apache2/sites-available/000-default.conf
COPY ./.docker/apache/php-prod.ini /usr/local/etc/php/php.ini
COPY . /var/www/html

WORKDIR /var/www/html

RUN chown -R www-data:www-data /var/www/html
