#!/command/with-contenv bash

if [[ $ITAG_DEBUG = 1 || $ITAG_DEBUG = '1' || $ITAG_DEBUG = 'true' ]]
then
  echo '[debug] Opcache WATCHING for file changes'
else
  echo '[debug] Opcache set to PERFORMANCE, NOT WATCHING for file changes'
  if [[ $PHP_OPCACHE_ENABLE_PRELOADING = 1 || $PHP_OPCACHE_ENABLE_PRELOADING = '1' || $PHP_OPCACHE_ENABLE_PRELOADING = 'true' ]]
  then
    echo '[debug] Opcache enable preloading'
    sed -i "s/;opcache.preload=/opcache.preload=/" /etc/php/${PHP_VERSION}/fpm/php.ini
  fi
fi
