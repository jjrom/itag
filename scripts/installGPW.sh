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
    echo "   Install GPW datasources "
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

if [ "${ITAG_DATABASE_HOST}" == "itagdb" ] || [ "${ITAG_DATABASE_HOST}" == "host.docker.internal" ]; then
    DATABASE_HOST_SEEN_FROM_DOCKERHOST=localhost
else
    DATABASE_HOST_SEEN_FROM_DOCKERHOST=${ITAG_DATABASE_HOST}
fi


# Prepare data directory
if [ -d "${DATA_DIR}" ];
then
    echo -e "[INFO] Using existing ${DATA_DIR} directory"
else
    echo -e "[INFO] Creating ${DATA_DIR} directory"
    mkdir -p ${DATA_DIR}
fi

if [ ! -f ${DATA_DIR}/itag_glp15ag.sql ]; then
    wget -O ${DATA_DIR}/itag_gpw.zip ${GPW_DATASOURCE_URL}
    unzip -q ${DATA_DIR}/itag_gpw.zip -d ${DATA_DIR}
    [ $? -eq 0 ] && rm ${DATA_DIR}/itag_gpw.zip
else
    echo -e "[INFO] Using existing ${GPW_DATASOURCE_URL} data" 
fi

echo -e "${YELLOW}[WARNING] Population grid insertion can take a loooong time - be patient :)${NC}"

# ===================================================================
TARGET=glp15ag60
echo -e "[INFO] Inserting Population grids 2015 1x1 degrees grid"

PGPASSWORD=${ITAG_DATABASE_USER_PASSWORD} psql -U ${ITAG_DATABASE_USER_NAME} -d ${ITAG_DATABASE_NAME} -h ${DATABASE_HOST_SEEN_FROM_DOCKERHOST} -p ${ITAG_DATABASE_EXPOSED_PORT}  > /dev/null 2>errors.log << EOF
DELETE FROM gpw.${TARGET};
DROP INDEX gpw.footprint_${TARGET}_idx;
DROP INDEX gpw.pcount_${TARGET}_idx;
EOF

cat ${DATA_DIR}/itag_${TARGET}.sql | PGPASSWORD=${ITAG_DATABASE_USER_PASSWORD} psql -U ${ITAG_DATABASE_USER_NAME} -d ${ITAG_DATABASE_NAME} -h ${DATABASE_HOST_SEEN_FROM_DOCKERHOST} -p ${ITAG_DATABASE_EXPOSED_PORT}  > /dev/null 2>errors.log
PGPASSWORD=${ITAG_DATABASE_USER_PASSWORD} psql -U ${ITAG_DATABASE_USER_NAME} -d ${ITAG_DATABASE_NAME} -h ${DATABASE_HOST_SEEN_FROM_DOCKERHOST} -p ${ITAG_DATABASE_EXPOSED_PORT}  > /dev/null 2>errors.log << EOF
CREATE INDEX footprint_${TARGET}_idx on gpw.${TARGET} USING GIST (footprint);
CREATE INDEX pcount_${TARGET}_idx on gpw.${TARGET} USING btree (pcount);
EOF

# ===================================================================
TARGET=glp15ag30
echo -e "[INFO] Inserting Population grids 2015 0.5x0.5 degrees grid"

PGPASSWORD=${ITAG_DATABASE_USER_PASSWORD} psql -U ${ITAG_DATABASE_USER_NAME} -d ${ITAG_DATABASE_NAME} -h ${DATABASE_HOST_SEEN_FROM_DOCKERHOST} -p ${ITAG_DATABASE_EXPOSED_PORT}  > /dev/null 2>errors.log << EOF
DELETE FROM gpw.${TARGET};
DROP INDEX gpw.footprint_${TARGET}_idx;
DROP INDEX gpw.pcount_${TARGET}_idx;
EOF

cat ${DATA_DIR}/itag_${TARGET}.sql | PGPASSWORD=${ITAG_DATABASE_USER_PASSWORD} psql -U ${ITAG_DATABASE_USER_NAME} -d ${ITAG_DATABASE_NAME} -h ${DATABASE_HOST_SEEN_FROM_DOCKERHOST} -p ${ITAG_DATABASE_EXPOSED_PORT}  > /dev/null 2>errors.log
PGPASSWORD=${ITAG_DATABASE_USER_PASSWORD} psql -U ${ITAG_DATABASE_USER_NAME} -d ${ITAG_DATABASE_NAME} -h ${DATABASE_HOST_SEEN_FROM_DOCKERHOST} -p ${ITAG_DATABASE_EXPOSED_PORT}  > /dev/null 2>errors.log << EOF
CREATE INDEX footprint_${TARGET}_idx on gpw.${TARGET} USING GIST (footprint);
CREATE INDEX pcount_${TARGET}_idx on gpw.${TARGET} USING btree (pcount);
EOF

# ===================================================================
TARGET=glp15ag15
echo -e "[INFO] Inserting Population grids 2015 0.25x0.25 degrees grid"

PGPASSWORD=${ITAG_DATABASE_USER_PASSWORD} psql -U ${ITAG_DATABASE_USER_NAME} -d ${ITAG_DATABASE_NAME} -h ${DATABASE_HOST_SEEN_FROM_DOCKERHOST} -p ${ITAG_DATABASE_EXPOSED_PORT}  > /dev/null 2>errors.log << EOF
DELETE FROM gpw.${TARGET};
DROP INDEX gpw.footprint_${TARGET}_idx;
DROP INDEX gpw.pcount_${TARGET}_idx;
EOF

cat ${DATA_DIR}/itag_${TARGET}.sql | PGPASSWORD=${ITAG_DATABASE_USER_PASSWORD} psql -U ${ITAG_DATABASE_USER_NAME} -d ${ITAG_DATABASE_NAME} -h ${DATABASE_HOST_SEEN_FROM_DOCKERHOST} -p ${ITAG_DATABASE_EXPOSED_PORT}  > /dev/null 2>errors.log
PGPASSWORD=${ITAG_DATABASE_USER_PASSWORD} psql -U ${ITAG_DATABASE_USER_NAME} -d ${ITAG_DATABASE_NAME} -h ${DATABASE_HOST_SEEN_FROM_DOCKERHOST} -p ${ITAG_DATABASE_EXPOSED_PORT}  > /dev/null 2>errors.log << EOF
CREATE INDEX footprint_${TARGET}_idx on gpw.${TARGET} USING GIST (footprint);
CREATE INDEX pcount_${TARGET}_idx on gpw.${TARGET} USING btree (pcount);
EOF

# ===================================================================
TARGET=glp15ag
echo -e "[INFO] Inserting Population grids 2015 2.5x2.5 arc minutes grid"

PGPASSWORD=${ITAG_DATABASE_USER_PASSWORD} psql -U ${ITAG_DATABASE_USER_NAME} -d ${ITAG_DATABASE_NAME} -h ${DATABASE_HOST_SEEN_FROM_DOCKERHOST} -p ${ITAG_DATABASE_EXPOSED_PORT}  > /dev/null 2>errors.log << EOF
DELETE FROM gpw.${TARGET};
DROP INDEX gpw.footprint_${TARGET}_idx;
DROP INDEX gpw.pcount_${TARGET}_idx;
EOF

cat ${DATA_DIR}/itag_${TARGET}.sql | PGPASSWORD=${ITAG_DATABASE_USER_PASSWORD} psql -U ${ITAG_DATABASE_USER_NAME} -d ${ITAG_DATABASE_NAME} -h ${DATABASE_HOST_SEEN_FROM_DOCKERHOST} -p ${ITAG_DATABASE_EXPOSED_PORT}  > /dev/null 2>errors.log
PGPASSWORD=${ITAG_DATABASE_USER_PASSWORD} psql -U ${ITAG_DATABASE_USER_NAME} -d ${ITAG_DATABASE_NAME} -h ${DATABASE_HOST_SEEN_FROM_DOCKERHOST} -p ${ITAG_DATABASE_EXPOSED_PORT}  > /dev/null 2>errors.log << EOF
CREATE INDEX footprint_${TARGET}_idx on gpw.${TARGET} USING GIST (footprint);
CREATE INDEX pcount_${TARGET}_idx on gpw.${TARGET} USING btree (pcount);
EOF

PGPASSWORD=${ITAG_DATABASE_USER_PASSWORD} psql -U ${ITAG_DATABASE_USER_NAME} -d ${ITAG_DATABASE_NAME} -h ${DATABASE_HOST_SEEN_FROM_DOCKERHOST} -p ${ITAG_DATABASE_EXPOSED_PORT}  > /dev/null 2>errors.log << EOF
vacuum analyze gpw.glp15ag;
vacuum analyze gpw.glp15ag15;
vacuum analyze gpw.glp15ag30;
vacuum analyze gpw.glp15ag60;
EOF
