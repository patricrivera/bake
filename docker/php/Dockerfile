FROM php:7.3-fpm

# extensions
RUN apt-get update \
    && apt-get install -y zlib1g-dev libicu-dev g++ \
    && docker-php-ext-configure intl \
    && docker-php-ext-install intl \
    && docker-php-ext-install mysqli \
    && docker-php-ext-install pdo pdo_mysql

# make web server connect to this port
EXPOSE 9000

CMD ["php-fpm"]
