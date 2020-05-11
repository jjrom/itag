#!/bin/bash

# Force script to exit on error
RED='\033[0;31m'
set -e
err_report() {
    echo -e "${RED}[ERROR] Error on line $1 ${NC}"
}
trap 'err_report $LINENO' ERR

PGPASSWORD=${POSTGRES_PASSWORD} psql -v ON_ERROR_STOP=1 -U "$POSTGRES_USER" -d "$POSTGRES_DB" -f /sql/00_itag_extensions.sql > /dev/null 2>&1
PGPASSWORD=${POSTGRES_PASSWORD} psql -v ON_ERROR_STOP=1 -U "$POSTGRES_USER" -d "$POSTGRES_DB" -f /sql/01_itag_functions.sql > /dev/null 2>&1
PGPASSWORD=${POSTGRES_PASSWORD} psql -v ON_ERROR_STOP=1 -U "$POSTGRES_USER" -d "$POSTGRES_DB" -f /sql/01_tamn.sql > /dev/null 2>&1
PGPASSWORD=${POSTGRES_PASSWORD} psql -v ON_ERROR_STOP=1 -U "$POSTGRES_USER" -d "$POSTGRES_DB" -f /sql/02_itag_model.sql > /dev/null 2>&1

