FROM php:8.2-apache

# Enable MySQL extensions for PHP
RUN docker-php-ext-install mysqli pdo pdo_mysql

# Copy your code into the Apache root folder
COPY . /var/www/html/

EXPOSE 80