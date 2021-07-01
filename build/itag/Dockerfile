FROM jjrom/nginx-fpm:7.4
LABEL maintainer="jerome.gasperi@gmail.com"

ENV BUILD_DIR=./build/itag \
    PHP_VERSION=7.4 \
    ITAG_DEBUG=1

# Copy NGINX configuration
COPY ${BUILD_DIR}/container_root/etc/nginx /etc/nginx

# Copy PHP-FPM configuration
COPY ${BUILD_DIR}/container_root/etc/fpm /etc/php/${PHP_VERSION}/fpm

# Copy PHP mods available
COPY ${BUILD_DIR}/container_root/etc/php/mods-available /etc/php/${PHP_VERSION}/mods-available

# Copy run.d configuration
COPY ${BUILD_DIR}/container_root/cont-init.d /etc/cont-init.d

# Create app directory
RUN mkdir /cfg
COPY ${BUILD_DIR}/config.php.template /cfg/config.php.template
RUN mkdir /app
COPY ./app /app
