#!/command/with-contenv bash

# Generate itag config.php from environment variables

# The file /tmp/config.php.template should exist !
CONFIG_TEMPLATE_FILE=/cfg/config.php.template
if [ ! -f ${CONFIG_TEMPLATE_FILE} ]; then
    showUsage
    echo "[GRAVE] Missing ${CONFIG_TEMPLATE_FILE} file - using default itag configuration";
    exit 1
fi

# From there we use environment variables passed during container startup
mkdir -v /etc/itag

# Awfull trick
eval "cat <<EOF
$(<${CONFIG_TEMPLATE_FILE})
EOF
" | sed s/\'\"/\'/g | sed s/\"\'/\'/g > /etc/itag/config.php
