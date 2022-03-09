FROM php:8.0-cli-alpine3.13

RUN apk update \
    && apk add --no-cache $PHPIZE_DEPS libzip-dev openssl-dev \
    && docker-php-ext-install -j$(nproc) zip \
    && pecl install xdebug-3.1.3 && docker-php-ext-enable xdebug

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/bin/ --filename=composer

ENV PATH /var/app/bin:/var/app/vendor/bin:$PATH

WORKDIR /var/app
