FROM php:7.2-fpm-alpine

# Install PDO and PGSQL Drivers
RUN apk add --update --no-cache libcurl curl ssmtp \
    && apk add --virtual .dev curl-dev postgresql-dev pcre-dev \
    && docker-php-ext-configure opcache --enable-opcache \
    && docker-php-ext-install pdo pdo_pgsql pgsql opcache curl

# Install geos
RUN apk add --no-cache -X http://dl-cdn.alpinelinux.org/alpine/edge/testing geos geos-dev

# Compile and install php-geos
RUN curl -fsSL 'https://github.com/libgeos/php-geos/archive/master.zip' -o php-geos.zip \
	&& unzip php-geos.zip -d /tmp \
	&& rm php-geos.zip \
	&& docker-php-ext-configure /tmp/php-geos-master --enable-geos \
	&& docker-php-ext-install /tmp/php-geos-master \
	&& rm -r /tmp/php-geos-master

# PHP default ini with opcache activated
COPY ./build/phpfpm/php.ini /opt/bitnami/nginx/conf/vhosts/nginx.conf

# resto default configuration and code
RUN mkdir /conf
COPY ./build/phpfpm/config.php /conf/config.php
COPY ./app /app