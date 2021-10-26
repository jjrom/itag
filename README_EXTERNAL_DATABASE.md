# iTag

## Installation on external database
The following extensions must be installed on the target database:
 * postgis
 * postgis_topology
 * unaccent

For instance suppose that the external database is "itag" :

        ITAG_DATABASE_NAME=itag

        PGPASSWORD=${ITAG_DATABASE_SUPERUSER_PASSWORD} createdb -X -v ON_ERROR_STOP=1 -h "${ITAG_DATABASE_HOST}" -p "${ITAG_DATABASE_PORT}" -U "${ITAG_DATABASE_SUPERUSER_NAME}" ${ITAG_DATABASE_NAME}

        PGPASSWORD=${ITAG_DATABASE_SUPERUSER_PASSWORD} psql -X -v ON_ERROR_STOP=1 -h "${ITAG_DATABASE_HOST}" -p "${ITAG_DATABASE_PORT}" -U "${ITAG_DATABASE_SUPERUSER_NAME}" -d "${ITAG_DATABASE_NAME}" -f ./build/itag-database/sql/00_itag_extensions.sql

Where ITAG_DATABASE_SUPERUSER_NAME is a database user with sufficient privileges to install extensions ("postgres" user for instance)

A normal PG user with `create schema` and `insert on spatial_ref_sys` rights is necessary in order for iTag to operate. To give a user the suitable rights, run the following sql commands:

        GRANT CREATE ON DATABASE ${ITAG_DATABASE_NAME} TO <dbuser>;
        GRANT INSERT ON TABLE spatial_ref_sys TO <dbuser>;

iTag tables, functions and triggers should be installed by running [scripts/installOnExternalDB.sh](https://github.com/jjrom/itag/blob/master/scripts/installExternalDB.sh):

        ./scripts/installExternalDB.sh -e <config file>
        
Note: The `insert on spatial_ref_sys` right can be revoked once the database is installed (first deploy) by running:
    
    REVOKE INSERT ON table spatial_ref_sys FROM <dbuser>; 

