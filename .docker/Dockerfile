FROM php:7.3-fpm-alpine

RUN apk add iputils

RUN docker-php-ext-install sockets

RUN chmod 4755 /usr/bin/traceroute