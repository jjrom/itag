#!/bin/bash
#
# Copyright 2013 Jérôme Gasperi
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
#

# Paths are mandatory from command line
SUPERUSER=postgres
DROPFIRST=NO
DB=itag
USER=itag
HOSTNAME=localhost
usage="## iTag database installation\n\n  Usage $0 -p [itag user password] [-d <PostGIS directory> -s <database SUPERUSER> -F -H <server HOSTNAME>]\n\n  -d : absolute path to the directory containing postgis.sql - If not specified, EXTENSION mechanism will be used\n  -s : database SUPERUSER (default "postgres")\n  -F : WARNING - suppress existing itag database\n  -H : postgres server hostname (default localhost)"
while getopts "d:s:p:hFH:" options; do
    case $options in
        d ) ROOTDIR=`echo $OPTARG`;;
        s ) SUPERUSER=`echo $OPTARG`;;
        p ) PASSWORD=`echo $OPTARG`;;
        H ) HOSTNAME=`echo $OPTARG`;;
        F ) DROPFIRST=YES;;
        h ) echo -e $usage;;
        \? ) echo -e $usage
            exit 1;;
        * ) echo -e $usage
            exit 1;;
    esac
done
if [ "$DROPFIRST" = "YES" ]
then
    dropdb -U $SUPERUSER $DB -h $HOSTNAME
fi
if [ "$PASSWORD" = "" ]
then
    echo -e $usage
    exit 1
fi

# Create DB
createdb $DB -U $SUPERUSER -h $HOSTNAME
createlang -U $SUPERUSER plpgsql $DB -h $HOSTNAME

# Make db POSTGIS compliant
if [ "$ROOTDIR" = "" ]
then
    psql -d $DB -U $SUPERUSER -h $HOSTNAME -c "CREATE EXTENSION postgis; CREATE EXTENSION postgis_topology;"
else
    # Example : $ROOTDIR = /usr/local/pgsql/share/contrib/postgis-1.5/
    postgis=`echo $ROOTDIR/postgis.sql`
    projections=`echo $ROOTDIR/spatial_ref_sys.sql`
    psql -d $DB -U $SUPERUSER -f $postgis -h $HOSTNAME
    psql -d $DB -U $SUPERUSER -f $projections -h $HOSTNAME

fi


###### ADMIN ACCOUNT CREATION ######
psql -U $SUPERUSER -d $DB -h $HOSTNAME << EOF
CREATE USER $USER WITH PASSWORD '$PASSWORD' NOCREATEDB
EOF

# Create unaccent function
psql -U $SUPERUSER -d $DB -h $HOSTNAME << EOF

--
-- Use unaccent function from postgresql >= 9
-- Set it as IMMUTABLE to use it in index
--
CREATE EXTENSION unaccent;
ALTER FUNCTION unaccent(text) IMMUTABLE;

--
-- Create function normalize
-- This function will return input text
-- in lower case, without accents and with spaces replaced as '-'
--
CREATE OR REPLACE FUNCTION normalize(text) 
RETURNS text AS \$\$ 
SELECT replace(replace(lower(unaccent(\$1)),' ','-'), '''', '-')
\$\$ LANGUAGE sql;
EOF

# Rights
psql -U $SUPERUSER -d $DB -h $HOSTNAME << EOF
ALTER DATABASE $DB OWNER TO $USER;
REVOKE CREATE ON SCHEMA public FROM PUBLIC;
GRANT ALL ON geometry_columns to $USER;
GRANT ALL ON geography_columns to $USER;
GRANT SELECT on spatial_ref_sys to $USER;
EOF





