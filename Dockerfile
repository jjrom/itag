FROM php:7.2-fpm-alpine

# Install PDO and PGSQL Drivers
RUN apk add --update --no-cache libcurl curl ssmtp \
    && apk add --virtual .dev curl-dev postgresql-dev pcre-dev \
    && docker-php-ext-configure opcache --enable-opcache \
    && docker-php-ext-install pdo pdo_pgsql pgsql opcache curl
