#!/bin/bash
#
# Copyright 2018 Jérôme Gasperi
#
# Licensed under the Apache License, version 2.0 (the "License");
# You may not use this file except in compliance with the License.
# You may obtain a copy of the License at:
#
#   http://www.apache.org/licenses/LICENSE-2.0
#
# Unless required by applicable law or agreed to in writing, software
# distributed under the License is distributed on an "AS IS" BASIS, WITHOUT
# WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied. See the
# License for the specific language governing permissions and limitations
# under the License.

# Force script to exit on error
set -e

PWD=`pwd`
ENV_FILE=__NULL__
FORCE_DATASOURCES_INSTALL=0
CONTACT="jerome.gasperi@gmail.com"
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'
function showUsage {
    echo ""
    echo "   Deploy an itag docker instance "
    echo ""
    echo "   Usage $0 -e config.env"
    echo ""
    echo "      -e | --envfile Environnement file (see config.env example)"
    echo "      -f | --force Force datasources ingestion"
    echo "      -h | --help show this help"
    echo ""
    echo "      !!! This script requires docker and docker-compose !!!"
    echo ""
}

# Parsing arguments
while [[ $# > 0 ]]
do
	key="$1"
	case $key in
        -e|--envfile)
            ENV_FILE="$2"
            shift # past argument
            ;;
        -f|--force)
            FORCE_DATASOURCES_INSTALL=1
            shift # past argument
            ;;
        -h|--help)
            showUsage
            exit 0
            shift # past argument
            ;;
            *)
        shift # past argument
        # unknown option
        ;;
	esac
done

if [ ! -f ${ENV_FILE} ]; then
    showUsage
    echo -e "${RED}[ERROR]${NC} Missing or invalid config file!"
    echo ""
    exit 0
fi

RNET_EXIST=$(docker network ls | grep rnet | wc | awk '{print $1}')
if [ "${RNET_EXIST}" == "0" ]; then
    echo -e "[INFO] Creating external network ${GREEN}rnet${NC}"
    docker network create rnet
else
    echo -e "[INFO] Using existing network ${GREEN}rnet${NC}"
fi

echo -e "[INFO] Sourcing ${ENV_FILE}"
. ${ENV_FILE}

echo -e "[INFO] Application status is ${GREEN}${ITAG_PRODUCTION_STATUS}${NC}"
echo -e "[INFO] Set up .run/config directory to store configuration files"
mkdir -p .run/config
cp config/${ITAG_PRODUCTION_STATUS}/nginx.conf config/${ITAG_PRODUCTION_STATUS}/php.ini .run/config/

# Extract exposed port from ITAG_ENDPOINT or 80 if not set
ITAG_EXPOSED_PORT="$(echo $ITAG_ENDPOINT | sed -e 's,^.*:,:,g' -e 's,.*:\([0-9]*\).*,\1,g' -e 's,[^0-9],,g')"
if [ ${ITAG_EXPOSED_PORT} == "" ]; then
    ITAG_EXPOSED_PORT=80
fi

echo -e "[INFO] Creating ${GREEN}.env${NC} file for docker-compose"
cat > .env <<EOL
ITAG_EXPOSED_PORT=${ITAG_EXPOSED_PORT}
ITAG_DATABASE_NAME=${ITAG_DATABASE_NAME}
ITAG_DATABASE_USER_NAME=${ITAG_DATABASE_USER_NAME}
ITAG_DATABASE_USER_PASSWORD=${ITAG_DATABASE_USER_PASSWORD}
ITAG_DATABASE_EXPOSED_PORT=${ITAG_DATABASE_EXPOSED_PORT}
EOL

echo -e "[INFO] Generating itag ${GREEN}.run/configure/config.php${NC} file"
eval "cat <<EOF
$(<config/config.php)
EOF
" 2> /dev/null > .run/config/config.php

echo -e "[INFO] Starting itag docker instance"

# This is where everything happens !
docker-compose up -d

echo -e "[INFO] Wait 10 seconds for database to be ready"
sleep 10

DATASOURCES_INSTALL=$(PGPASSWORD=${ITAG_DATABASE_USER_PASSWORD} psql -U ${ITAG_DATABASE_USER_NAME} -d ${ITAG_DATABASE_NAME} -h localhost -p ${ITAG_DATABASE_EXPOSED_PORT} -c "\dn" | paste -sd "," - | grep -v "datasources" | wc | awk '{print $1}')
GPW_INSTALL=$(PGPASSWORD=${ITAG_DATABASE_USER_PASSWORD} psql -U ${ITAG_DATABASE_USER_NAME} -d ${ITAG_DATABASE_NAME} -h localhost -p ${ITAG_DATABASE_EXPOSED_PORT} -c "\dn" | paste -sd "," - | grep -v "gpw" | wc | awk '{print $1}')
LANDCOVER_INSTALL=$(PGPASSWORD=${ITAG_DATABASE_USER_PASSWORD} psql -U ${ITAG_DATABASE_USER_NAME} -d ${ITAG_DATABASE_NAME} -h localhost -p ${ITAG_DATABASE_EXPOSED_PORT} -c "\dn" | paste -sd "," - | grep -v "landcover" | wc | awk '{print $1}')

if [ "${FORCE_DATASOURCES_INSTALL}" == "1" ]; then
    DATASOURCES_INSTALL="1"
    GPW_INSTALL="1"
    LANDCOVER_INSTALL="1"
fi

if [ "${DATASOURCES_INSTALL}" != "0" ]; then
    echo -e "${YELLOW}[INFO] Installing datasources${NC}"
    echo -e "[INFO] Building docker shp2pgsql"
    docker build -t jjrom/shp2pgsql ${PWD}/docker-shp2pgsql
    ./scripts/installDatasources.sh -e ${ENV_FILE} -d ${PWD}/data
    ./scripts/updateDatasourcesWithGeoname.sh -e ${ENV_FILE} -d ${PWD}/sql
else
    echo -e "[INFO] Datasources are installed"
fi

if [ "${GPW_INSTALL}" != "0" ]; then
    if [ "${GPW_DATASOURCE_URL}" != "" ]; then
        echo -e "${YELLOW}[INFO] Installing population grids${NC}"
        ./scripts/installGPW.sh -e ${ENV_FILE} -d ${PWD}/data -s ${PWD}/scripts/gpw2sql.php
    else
        echo -e "[INFO] Population density is not available - contact ${YELLOW}${CONTACT}${NC} if you need it"
    fi
else
    echo -e "[INFO] Population grids are installed"
fi

if [ "${LANDCOVER_INSTALL}" != "0" ]; then
    if [ "${LANDCOVER_DATASOURCE_URL}" != "" ]; then
        echo -e "${YELLOW}[INFO] Installing landcover${NC}"
        ./scripts/installLandcover.sh -e ${ENV_FILE} -d ${PWD}/data
    else
        echo -e "[INFO] Landcover is not available - contact ${YELLOW}${CONTACT}${NC} if you need it"
    fi
else
    echo -e "[INFO] Landcover is installed"
fi

# Mount point for database  
# MacOS X is a bit tricky - https://stackoverflow.com/questions/41273514/access-docker-volume-mountpoint-with-docker-for-mac
if [[ ! "$OSTYPE" == "darwin"* ]]; then
    MOUNT_POINT=$(docker volume inspect $(basename `pwd`)"_database_data"| grep Mountpoint | awk -F\" '{print $4}')
    echo -e "[INFO] Database mount point is ${GREEN}${MOUNT_POINT}${NC}"
else
    echo -e "[INFO] MacOS X - mount point not provided"
fi
echo -e "[INFO] itag up and running at ${GREEN}${ITAG_ENDPOINT}${NC}"
echo ""



