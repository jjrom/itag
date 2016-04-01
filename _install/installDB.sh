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

--
-- Copyright (C) 2016 Jerome Gasperi <jerome.gasperi@gmail.com>
-- With priceless contribution from Nicolas Ribot <nicky666@gmail.com>
-- 
-- This work is placed into the public domain.
--
-- SYNOPSYS:
--   ST_SplitDateLine(polygon)
--
-- DESCRIPTION:
--
--   This function split the input polygon geometry against the -180/180 date line
--   Returns the original geometry otherwise
--
--   WARNING ! Only work for SRID 4326
--
-- USAGE:
--
CREATE OR REPLACE FUNCTION ST_SplitDateLine(geom_in geometry)
RETURNS geometry AS \$\$
DECLARE
	geom_out geometry;
	blade geometry;
BEGIN

        blade := ST_SetSrid(ST_MakeLine(ST_MakePoint(180, -90), ST_MakePoint(180, 90)), 4326);

	-- Delta longitude is greater than 180 then return splitted geometry
	IF ST_XMin(geom_in) < -90 AND ST_XMax(geom_in) > 90 THEN

            -- Add 360 to all negative longitudes
            WITH tmp0 AS (
                SELECT geom_in AS geom
            ), tmp AS (
                SELECT st_dumppoints(geom) AS dmp FROM tmp0
            ), tmp1 AS (
                SELECT (dmp).path,
                CASE WHEN st_X((dmp).geom) < 0 THEN st_setSRID(st_MakePoint(st_X((dmp).geom) + 360, st_Y((dmp).geom)), 4326) 
                ELSE (dmp).geom END AS geom
                FROM tmp
                ORDER BY (dmp).path[2]
            ), tmp2 AS (
                SELECT st_dump(st_split(st_makePolygon(st_makeline(geom)), blade)) AS d
                FROM tmp1
            )
            SELECT ST_Union(
                (
                    CASE WHEN ST_Xmax((d).geom) > 180 THEN ST_Translate((d).geom, -360, 0, 0)
                    ELSE (d).geom END
                )
            )
            INTO geom_out
            FROM tmp2;
            
        -- Delta longitude < 180 degrees then return untouched input geometry
        ELSE
            RETURN geom_in;
	END IF;

	RETURN geom_out;
END
\$\$ LANGUAGE 'plpgsql' IMMUTABLE;
EOF

# Rights
psql -U $SUPERUSER -d $DB -h $HOSTNAME << EOF
ALTER DATABASE $DB OWNER TO $USER;
REVOKE CREATE ON SCHEMA public FROM PUBLIC;
GRANT ALL ON geometry_columns to $USER;
GRANT ALL ON geography_columns to $USER;
GRANT SELECT on spatial_ref_sys to $USER;
EOF





