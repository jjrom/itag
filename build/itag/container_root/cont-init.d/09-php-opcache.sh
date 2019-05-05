#!/usr/bin/with-contenv bash

if [[ $ITAG_DEBUG_MODE = 1 || $ITAG_DEBUG_MODE = '1' || $ITAG_DEBUG_MODE = 'true' ]]
then
  echo '[debug] Opcache WATCHING for file changes'
else
  echo '[debug] Opcache set to PERFORMANCE, NOT WATCHING for file changes'
fi
