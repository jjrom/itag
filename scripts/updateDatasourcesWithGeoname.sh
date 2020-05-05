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
SQL_DIR=__NULL__
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

function showUsage {
    echo ""
    echo "   Update itag datasources with geoname"
    echo ""
    echo "   Usage $0 -e config.env -d sqlDir"
    echo ""
    echo "      -e | --envfile Environnement file (see config.env example)"
    echo "      -d | --sqlDir Directory containing SQL update scripts"
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
        -d|--sqlDir)
            SQL_DIR="$2"
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

if [ "${SQL_DIR}" == "__NULL__" ]; then
    showUsage
    echo -e "${RED}[ERROR]${NC} You must specify the directory containing SQL scripts !"
    echo ""
    exit 0
fi

# Source config file
. ${ENV_FILE}

if [[ "${ITAG_DATABASE_IS_EXTERNAL}" == "yes" ]]; then
    DATABASE_HOST_SEEN_FROM_DOCKERHOST=${ITAG_DATABASE_HOST}
else
    DATABASE_HOST_SEEN_FROM_DOCKERHOST=${DATABASE_HOST_SEEN_FROM_DOCKERHOST}
fi


echo -e "[INFO] Udpate datasources tables"
PGPASSWORD=${ITAG_DATABASE_USER_PASSWORD} psql -U ${ITAG_DATABASE_USER_NAME} -d ${ITAG_DATABASE_NAME} -h ${DATABASE_HOST_SEEN_FROM_DOCKERHOST} -p ${ITAG_DATABASE_EXPOSED_PORT}  > /dev/null 2>errors.log << EOF
ALTER TABLE datasources.states ADD COLUMN geonameid INTEGER;
ALTER TABLE datasources.regions ADD COLUMN geonameid INTEGER;
ALTER TABLE datasources.countries ADD COLUMN geonameid INTEGER;
ALTER TABLE datasources.continents ADD COLUMN geonameid INTEGER;
ALTER TABLE datasources.physical ADD COLUMN geonameid INTEGER;
ALTER TABLE datasources.volcanoes ADD COLUMN geonameid INTEGER;
ALTER TABLE datasources.volcanoes ALTER COLUMN name TYPE TEXT;
ALTER TABLE datasources.glaciers ADD COLUMN geonameid INTEGER;

UPDATE datasources.states SET geonameid = NULL;
UPDATE datasources.regions SET geonameid = NULL;
UPDATE datasources.countries SET geonameid = NULL;
UPDATE datasources.continents SET geonameid = NULL;
UPDATE datasources.physical SET geonameid = NULL;
UPDATE datasources.volcanoes SET geonameid = NULL;
UPDATE datasources.glaciers SET geonameid = NULL;

-- 2018-11-20 Not present in egg (Why ??)
UPDATE datasources.continents SET geonameid=2077456 WHERE continent='Australia';
UPDATE datasources.continents SET geonameid=6255146 WHERE continent='Africa';
UPDATE datasources.continents SET geonameid=6255147 WHERE continent='Asia';
UPDATE datasources.continents SET geonameid=6255148 WHERE continent='Europe';
UPDATE datasources.continents SET geonameid=6255149 WHERE continent='North America';
UPDATE datasources.continents SET geonameid=6255150 WHERE continent='South America';
UPDATE datasources.continents SET geonameid=6255151 WHERE continent='Oceania';
UPDATE datasources.continents SET geonameid=6255152 WHERE continent='Antarctica';

EOF

PGPASSWORD=${ITAG_DATABASE_USER_PASSWORD} psql -U ${ITAG_DATABASE_USER_NAME} -d ${ITAG_DATABASE_NAME} -h ${DATABASE_HOST_SEEN_FROM_DOCKERHOST} -p ${ITAG_DATABASE_EXPOSED_PORT} -f ${SQL_DIR}/geoplanet.sql > /dev/null 2>errors.log
PGPASSWORD=${ITAG_DATABASE_USER_PASSWORD} psql -U ${ITAG_DATABASE_USER_NAME} -d ${ITAG_DATABASE_NAME} -h ${DATABASE_HOST_SEEN_FROM_DOCKERHOST} -p ${ITAG_DATABASE_EXPOSED_PORT} -f ${SQL_DIR}/egg.sql > /dev/null 2>errors.log
PGPASSWORD=${ITAG_DATABASE_USER_PASSWORD} psql -U ${ITAG_DATABASE_USER_NAME} -d ${ITAG_DATABASE_NAME} -h ${DATABASE_HOST_SEEN_FROM_DOCKERHOST} -p ${ITAG_DATABASE_EXPOSED_PORT} -f ${SQL_DIR}/missing.sql > /dev/null 2>errors.log
PGPASSWORD=${ITAG_DATABASE_USER_PASSWORD} psql -U ${ITAG_DATABASE_USER_NAME} -d ${ITAG_DATABASE_NAME} -h ${DATABASE_HOST_SEEN_FROM_DOCKERHOST} -p ${ITAG_DATABASE_EXPOSED_PORT} -f ${SQL_DIR}/glaciers.sql > /dev/null 2>errors.log
PGPASSWORD=${ITAG_DATABASE_USER_PASSWORD} psql -U ${ITAG_DATABASE_USER_NAME} -d ${ITAG_DATABASE_NAME} -h ${DATABASE_HOST_SEEN_FROM_DOCKERHOST} -p ${ITAG_DATABASE_EXPOSED_PORT} -f ${SQL_DIR}/volcanoes.sql > /dev/null 2>errors.log


echo -e "[INFO] Vacuuming database"
PGPASSWORD=${ITAG_DATABASE_USER_PASSWORD} psql -U ${ITAG_DATABASE_USER_NAME} -d ${ITAG_DATABASE_NAME} -h ${DATABASE_HOST_SEEN_FROM_DOCKERHOST} -p ${ITAG_DATABASE_EXPOSED_PORT} > /dev/null 2>errors.log << EOF
vacuum analyze ${ITAG_DATABASE_NAME}
EOF

