#! /bin/bash
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

ENV_FILE=__NULL__
DATA_DIR=__NULL__
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

function showUsage {
    echo ""
    echo "   Install Landcover datasources "
    echo ""
    echo "   Usage $0 -e config.env"
    echo ""
    echo "      -e | --envfile Environnement file (see config.env example)"
    echo "      -d | --dataDir Directory to download data"
    echo "      -h | --help show this help"
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
        -d|--dataDir)
            DATA_DIR="$2"
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
    echo -e "${RED}[ERROR]${NC} Missing or invalid config file with -e option"
    echo ""
    exit 0
fi

if [ "${DATA_DIR}" == "__NULL__" ]; then
    showUsage
    echo -e "${RED}[ERROR]${NC} You must specify a data directory with -d option!"
    echo ""
    exit 0
fi

# Source config file
. ${ENV_FILE}

# Prepare data directory
if [ -d "${ITAG_DIR}" ];
then
    echo -e "[INFO] Using existing ${DATA_DIR} directory"
else
    echo -e "[INFO] Creating ${DATA_DIR} directory"
    mkdir -p ${DATA_DIR}
fi

if [ ! -f ${DATA_DIR}/itag_landcover.sql ]; then
    wget -O ${DATA_DIR}/itag_landcover.zip ${LANDCOVER_DATASOURCE_URL}
    unzip ${DATA_DIR}/itag_landcover.zip -d ${DATA_DIR}
    [ $? -eq 0 ] && rm ${DATA_DIR}/itag_landcover.zip
else
    echo -e "[INFO] Using existing ${DATA_DIR}/itag_landcover.sql data" 
fi

echo -e "${YELLOW}[WARNING] Landcover insertion can take a loooong time - be patient :)${NC}"

echo -e "[INFO] Inserting landcover data" 
cat ${DATA_DIR}/itag_landcover.sql | PGPASSWORD=${DATABASE_USER_PASSWORD} psql -U ${DATABASE_USER_NAME} -d ${DATABASE_NAME} -h localhost -p ${DATABASE_EXPOSED_PORT}  > /dev/null 2>&1

echo -e "[INFO] Preparing index data" 
PGPASSWORD=${DATABASE_USER_PASSWORD} psql -U ${DATABASE_USER_NAME} -d ${DATABASE_NAME} -h localhost -p ${DATABASE_EXPOSED_PORT}  > /dev/null 2>&1 << EOF
CREATE INDEX landcover_geometry_idx ON landcover.landcover USING gist (wkb_geometry);
CREATE INDEX landcover2009_geometry_idx ON landcover.landcover2009 USING gist (wkb_geometry);
EOF
