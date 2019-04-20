FROM ubuntu:bionic

ENV BUILD_DIR ./build/itag
ENV PHP_VERSION 7.2
ENV ARCH amd64
ENV JUST_CONTAINERS_VERSION 1.22.1.0

# Add wait-for-it
ADD https://raw.githubusercontent.com/vishnubob/wait-for-it/master/wait-for-it.sh /bin/wait-for-it.sh
RUN chmod +x /bin/wait-for-it.sh

# Add S6 supervisor (for graceful stop)
ADD https://github.com/just-containers/s6-overlay/releases/download/v${JUST_CONTAINERS_VERSION}/s6-overlay-${ARCH}.tar.gz /tmp/
RUN tar xzf /tmp/s6-overlay-${ARCH}.tar.gz -C /
ENTRYPOINT ["/init"]
CMD []

# Disable frontend dialogs
#ENV DEBIAN_FRONTEND noninteractive

# Add ppa, curl and syslogd
RUN apt-get update && apt-get install -y software-properties-common curl inetutils-syslogd && \
    apt-add-repository ppa:nginx/stable -y && \
    #LC_ALL=C.UTF-8 apt-add-repository ppa:ondrej/php -y && \
    apt-get update && apt-get install -y \
    php${PHP_VERSION}-fpm \
    php${PHP_VERSION}-curl \
    php${PHP_VERSION}-cli \
    php${PHP_VERSION}-intl \
    php${PHP_VERSION}-json \
    php${PHP_VERSION}-pgsql \
    php-geos \
    php${PHP_VERSION}-opcache \
    php${PHP_VERSION}-mysql \
    php-gettext \
    php${PHP_VERSION}-xml \
    php${PHP_VERSION}-bcmath \
    php${PHP_VERSION}-mbstring \
    #php-ast \
    php${PHP_VERSION}-zip \
    php${PHP_VERSION}-sqlite3 \
    #php${PHP_VERSION}-apcu \
    zip \
    unzip \
    nginx && \
    apt-get autoremove -y && apt-get clean && rm -rf /var/lib/apt/lists/* && \
    mkdir -p /run/php && chmod -R 755 /run/php && \
    sed -i 's|.*listen =.*|listen=9000|g' /etc/php/${PHP_VERSION}/fpm/pool.d/www.conf && \
    sed -i 's|.*error_log =.*|error_log=/proc/self/fd/2|g' /etc/php/${PHP_VERSION}/fpm/php-fpm.conf && \
    sed -i 's|.*access.log =.*|access.log=/proc/self/fd/2|g' /etc/php/${PHP_VERSION}/fpm/pool.d/www.conf && \
    sed -i 's|.*user =.*|user=root|g' /etc/php/${PHP_VERSION}/fpm/pool.d/www.conf && \
    sed -i 's|.*group =.*|group=root|g' /etc/php/${PHP_VERSION}/fpm/pool.d/www.conf && \
    sed -i -e "s/;catch_workers_output\s*=\s*yes/catch_workers_output = yes/g" /etc/php/${PHP_VERSION}/fpm/pool.d/www.conf && \
    sed -i 's#.*variables_order.*#variables_order=GPCS#g' /etc/php/${PHP_VERSION}/fpm/php.ini && \
    sed -i 's#.*date.timezone.*#date.timezone=UTC#g' /etc/php/${PHP_VERSION}/fpm/pool.d/www.conf && \
    sed -i 's#.*clear_env.*#clear_env=no#g' /etc/php/${PHP_VERSION}/fpm/pool.d/www.conf

# Copy NGINX service script
COPY ${BUILD_DIR}/start-nginx.sh /etc/services.d/nginx/run
RUN chmod 755 /etc/services.d/nginx/run

# Copy PHP-FPM service script
COPY ${BUILD_DIR}/start-fpm.sh /etc/services.d/php_fpm/run
RUN chmod 755 /etc/services.d/php_fpm/run

# Copy NGINX configuration
COPY ${BUILD_DIR}/nginx /etc/nginx

# Create app directory
RUN mkdir /conf
COPY ${BUILD_DIR}/config.php /conf/config.php
RUN mkdir /app
COPY ./app /app
