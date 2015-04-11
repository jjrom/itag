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
DB=itag
USER=itag
HOSTNAME=
usage="## iTag data sources installation\n\n  Usage $0 -D <data directory> [-d <database name> -s <database SUPERUSER> -u <database USER> -F -H <server HOSTNAME>]\n\n  -D : absolute path to the data directory containing countries,continents,etc.\n  -s : database SUPERUSER (default "postgres")\n  -u : database USER (default "itag")\n  -d : database name (default "itag")\n  -H : postgres server hostname (default localhost)\n  -F : drop schema datasources first\n"
while getopts "D:d:s:u:H:hF" options; do
    case $options in
        D ) DATADIR=`echo $OPTARG`;;
        d ) DB=`echo $OPTARG`;;
        u ) USER=`echo $OPTARG`;;
        s ) SUPERUSER=`echo $OPTARG`;;
        H ) HOSTNAME=`echo "-h "$OPTARG`;;
        h ) echo -e $usage;;
        F ) DROPFIRST=YES;;
        \? ) echo -e $usage
            exit 1;;
        * ) echo -e $usage
            exit 1;;
    esac
done
if [ "$DATADIR" = "" ]
then
    echo -e $usage
    exit 1
fi

# Set Data paths
## Political
COUNTRIES=$DATADIR/ne_10m_admin_0_countries.shp
WORLDADM1LEVEL=$DATADIR/ne_10m_admin_1_states_provinces.shp
## Geology
PLATES=$DATADIR/hotspots/plates.shp
FAULTS=$DATADIR/hotspots/FAULTS.SHP
VOLCANOS=$DATADIR/hotspots/VOLCANO.SHP
GLACIERS=$DATADIR/ne_10m_glaciated_areas.shp datasources.glaciers
# Hydrology
RIVERS=$DATADIR/ne_10m_rivers_lake_centerlines.shp

##### DROP SCHEMA FIRST ######
if [ "$DROPFIRST" = "YES" ]
then
psql -d $DB -U $SUPERUSER << EOF
DROP SCHEMA IF EXISTS datasources CASCADE;
EOF
fi

psql -d $DB -U $SUPERUSER << EOF
CREATE SCHEMA datasources;
EOF

# ================== POLITICAL =====================

## Insert Countries
shp2pgsql -g geom -d -W LATIN1 -s 4326 -I $COUNTRIES datasources.countries | psql -d $DB -U $SUPERUSER $HOSTNAME
psql -d $DB  -U $SUPERUSER $HOSTNAME << EOF
UPDATE datasources.countries set name='Bosnia and Herzegovina' WHERE iso_a3 = 'BIH';
UPDATE datasources.countries set name='Central African Republic' WHERE iso_a3 = 'CAF';
UPDATE datasources.countries set name='Czech Republic' WHERE iso_a3 = 'CZE';
UPDATE datasources.countries set name='Congo' WHERE iso_a3 = 'COD';
UPDATE datasources.countries set name='North Korea' WHERE iso_a3 = 'PRK';
UPDATE datasources.countries set name='Dominican Republic' WHERE iso_a3 = 'DOM';
UPDATE datasources.countries set name='Equatorial Guinea' WHERE iso_a3 = 'GNQ';
UPDATE datasources.countries set name='Falkland Islands' WHERE iso_a3 = 'FLK';
UPDATE datasources.countries set name='French Southern and Antarctic Lands' WHERE iso_a3 = 'ATF';
UPDATE datasources.countries set name='Northern Cyprus' WHERE name = 'N. Cyprus';
UPDATE datasources.countries set name='South Sudan' WHERE iso_a3 = 'SSD';
UPDATE datasources.countries set name='Solomon Islands' WHERE iso_a3 = 'SLB';
UPDATE datasources.countries set name='Western Sahara' WHERE iso_a3 = 'ESH';
UPDATE datasources.countries set name='Ivory Coast' WHERE iso_a3 = 'CIV';
UPDATE datasources.countries set name='Laos' WHERE iso_a3 = 'LAO';
UPDATE datasources.countries set name='United States of America' WHERE name='United States';
CREATE INDEX idx_countries_name ON datasources.countries (normalize(name));
CREATE INDEX idx_countries_geom ON datasources.countries USING gist(geom);
EOF

## World administrative level 1 (i.e. states for USA, departements for France)
shp2pgsql -g geom -d -W UTF8 -s 4326 -I $WORLDADM1LEVEL datasources.worldadm1level | psql -d $DB -U $SUPERUSER $HOSTNAME
psql -d $DB -U $SUPERUSER  $HOSTNAME << EOF
UPDATE datasources.worldadm1level SET name='Seine-et-Marne' WHERE name='Seien-et-Marne';
CREATE INDEX idx_worldadm1level_geom ON datasources.worldadm1level USING gist(geom);
CREATE INDEX idx_worldadm1level_name ON datasources.worldadm1level (normalize(name));
CREATE INDEX idx_worldadm1level_region ON datasources.worldadm1level (normalize(region));
EOF

# =================== GEOLOGY ==================
## Insert plates
shp2pgsql -g geom -d -W UTF8 -s 4326 -I $PLATES datasources.plates | psql -d $DB -U $SUPERUSER $HOSTNAME

## Insert faults
shp2pgsql -g geom -d -W UTF8 -s 4326 -I $FAULTS datasources.faults | psql -d $DB -U $SUPERUSER $HOSTNAME
psql -d $DB  -U $SUPERUSER $HOSTNAME << EOF
DELETE FROM datasources.faults WHERE type IS NULL;
UPDATE datasources.faults set type='Thrust fault' WHERE type='thrust-fault';
UPDATE datasources.faults set type='step' WHERE type='Step';
UPDATE datasources.faults set type='Tectonic Contact' WHERE type='tectonic contact';
UPDATE datasources.faults set type='Rift' WHERE type='rift';
EOF

## Insert volcanoes
shp2pgsql -g geom -d -W UTF8 -s 4326 -I $VOLCANOES datasources.volcanoes | psql -d $DB -U $SUPERUSER $HOSTNAME

## Insert glaciers
shp2pgsql -g geom -d -W LATIN1 -s 4326 -I $GLACIERS datasources.glaciers | psql -d $DB -U $SUPERUSER $HOSTNAME

# =================== HYDROLOGY ==================
## Insert Rivers
shp2pgsql -g geom -d -W UTF8 -s 4326 -I $RIVERS datasources.rivers | psql -d $DB -U $SUPERUSER $HOSTNAME

# ==================== LANDCOVER =====================
psql -U $SUPERUSER -d $DB $HOSTNAME << EOF
CREATE TABLE datasources.landcover (
    ogc_fid         SERIAL,
    dn              INTEGER
);
SELECT AddGeometryColumn ('datasources','landcover','wkb_geometry',4326,'POLYGON',2);
CREATE INDEX landcover_geometry_idx ON datasources.landcover USING gist (wkb_geometry);
EOF

# =================== GPW ============================
psql -U $SUPERUSER -d $DB $HOSTNAME << EOF
CREATE TABLE glp15ag60 (
    gid                 VARCHAR(8) PRIMARY KEY,
    pcount              INTEGER
);
SELECT AddGeometryColumn('glp15ag60','footprint','4326','POLYGON',2);

-- ===============================
-- Population 2015 0.5x0.5 degrees
-- ===============================
CREATE TABLE glp15ag30 (
    gid                 VARCHAR(8) PRIMARY KEY,
    pcount              INTEGER
);
SELECT AddGeometryColumn('glp15ag30','footprint','4326','POLYGON',2);

-- =================================
-- Population 2015 0.25x0.25 degrees
-- =================================
CREATE TABLE glp15ag15 (
    gid                 VARCHAR(8) PRIMARY KEY,
    pcount              INTEGER
);
SELECT AddGeometryColumn('glp15ag15','footprint','4326','POLYGON',2);

-- ===================================
-- Population 2015 2.5x2.5 arc minutes
-- ===================================
CREATE TABLE glp15ag (
    gid                 VARCHAR(8) PRIMARY KEY,
    pcount              INTEGER
);
SELECT AddGeometryColumn('glp15ag','footprint','4326','POLYGON',2);
EOF

# GRANT RIGHTS TO itag USER
psql -U $SUPERUSER -d $DB $HOSTNAME << EOF
GRANT ALL ON SCHEMA datasources to $USER;
GRANT SELECT on datasources.worldadm1level to $USER;
GRANT SELECT on datasources.continents to $USER;
GRANT SELECT on datasources.countries to $USER;
GRANT SELECT on datasources.rivers to $USER;
GRANT SELECT on datasources.glaciers to $USER;
GRANT SELECT on datasources.plates to $USER;
GRANT SELECT on datasources.faults to $USER;
GRANT SELECT on datasources.volcanoes to $USER;
GRANT SELECT on datasources.landcover to $USER;
GRANT ALL ON SCHEMA gpw TO itag;
GRANT SELECT ON gpw.glp15ag to itag;
GRANT SELECT ON gpw.glp15ag15 to itag;
GRANT SELECT ON gpw.glp15ag30 to itag;
GRANT SELECT ON gpw.glp15ag60 to itag;
EOF
