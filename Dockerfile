ARG VERSION=8-cli
FROM php:${VERSION}-alpine

COPY --from=mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/bin/
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

ENV LANG en_US.UTF-8
ENV LANGUAGE en_US:en
ENV LC_ALL en_US.UTF-8
ENV COMPOSER_ALLOW_SUPERUSER=1

RUN install-php-extensions \
        bcmath \
        sockets \
        pcntl \
        amqp

WORKDIR /var/www/rabbitevents
ADD . .

RUN composer install -o
