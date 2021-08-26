#!/bin/bash

# Force script to exit on error
RED='\033[0;31m'
set -e
err_report() {
    echo -e "${RED}[ERROR] Error on line $1 ${NC}"
}
trap 'err_report $LINENO' ERR

PGPASSWORD=${POSTGRES_PASSWORD} psql -v ON_ERROR_STOP=1 -U "$POSTGRES_USER" -d "$POSTGRES_DB" -f /sql/00_itag_extensions.sql
PGPASSWORD=${POSTGRES_PASSWORD} psql -v ON_ERROR_STOP=1 -U "$POSTGRES_USER" -d "$POSTGRES_DB" -f /sql/01_itag_functions.sql
PGPASSWORD=${POSTGRES_PASSWORD} psql -v ON_ERROR_STOP=1 -U "$POSTGRES_USER" -d "$POSTGRES_DB" -f /sql/01_tamn.sql
PGPASSWORD=${POSTGRES_PASSWORD} psql -v ON_ERROR_STOP=1 -U "$POSTGRES_USER" -d "$POSTGRES_DB" -f /sql/02_itag_model.sql

