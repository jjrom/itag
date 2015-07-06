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
## Always
COASTLINES=$DATADIR/ne_10m_coastline.shp
## Political
CONTINENTS=$DATADIR/hotspots/continent.shp
COUNTRIES=$DATADIR/ne_10m_admin_0_countries.shp
STATES=$DATADIR/ne_10m_admin_1_states_provinces.shp
## Geology
PLATES=$DATADIR/hotspots/plates.shp
FAULTS=$DATADIR/hotspots/FAULTS.SHP
VOLCANOES=$DATADIR/hotspots/VOLCANO.SHP
GLACIERS=$DATADIR/ne_10m_glaciated_areas.shp
# Hydrology
RIVERS=$DATADIR/ne_10m_rivers_lake_centerlines.shp
# Other
MARINEAREAS=$DATADIR/ne_10m_geography_marine_polys.shp

##### DROP SCHEMA FIRST ######
if [ "$DROPFIRST" = "YES" ]
then
psql -d $DB -U $SUPERUSER << EOF
DROP SCHEMA IF EXISTS datasources CASCADE;
DROP SCHEMA IF EXISTS gpw CASCADE;
EOF
fi

psql -d $DB -U $SUPERUSER << EOF
CREATE SCHEMA datasources;
EOF

# ================== ALWAYS =====================
## Insert Coastlines
shp2pgsql -g geom -d -W LATIN1 -s 4326 -I $COASTLINES datasources.coastlines | psql -d $DB -U $SUPERUSER $HOSTNAME
psql -d $DB  -U $SUPERUSER $HOSTNAME << EOF
CREATE INDEX idx_coastlines_geom ON datasources.coastlines USING gist(geom);
EOF

# ================== POLITICAL =====================

## Insert Continents
shp2pgsql -g geom -d -W LATIN1 -s 4326 -I $CONTINENTS datasources.continents | psql -d $DB -U $SUPERUSER $HOSTNAME

## Insert Countries
shp2pgsql -g geom -d -W LATIN1 -s 4326 -I $COUNTRIES datasources.countries | psql -d $DB -U $SUPERUSER $HOSTNAME
psql -d $DB  -U $SUPERUSER $HOSTNAME << EOF
ALTER TABLE datasources.countries ALTER COLUMN name TYPE TEXT;
UPDATE datasources.countries set name='Antigua and Barbuda' WHERE iso_a3 = 'ATG';
UPDATE datasources.countries set name='Ashmore and Cartier Islands' WHERE name = 'Ashmore and Cartier Is.';
UPDATE datasources.countries set name='Bosnia and Herzegovina' WHERE iso_a3 = 'BIH';
UPDATE datasources.countries set name='British Indian Ocean Territory' WHERE iso_a3 = 'IOT';
UPDATE datasources.countries set name='British Virgin Islands' WHERE iso_a3 = 'VGB';
UPDATE datasources.countries set name='Cayman Islands' WHERE iso_a3 = 'CYM';
UPDATE datasources.countries set name='Central African Republic' WHERE iso_a3 = 'CAF';
UPDATE datasources.countries set name='Clipperton Island' WHERE name = 'Clipperton I.';
UPDATE datasources.countries set name='Cook Islands' WHERE iso_a3 = 'COK';
UPDATE datasources.countries set name='Coral Sea Islands' WHERE name = 'Coral Sea Is.';
UPDATE datasources.countries set name='Czech Republic' WHERE iso_a3 = 'CZE';
UPDATE datasources.countries set name='Congo' WHERE iso_a3 = 'COD';
UPDATE datasources.countries set name='Curaçao' WHERE iso_a3 = 'CUW';
UPDATE datasources.countries set name='North Korea' WHERE iso_a3 = 'PRK';
UPDATE datasources.countries set name='Dominican Republic' WHERE iso_a3 = 'DOM';
UPDATE datasources.countries set name='Equatorial Guinea' WHERE iso_a3 = 'GNQ';
UPDATE datasources.countries set name='Falkland Islands' WHERE iso_a3 = 'FLK';
UPDATE datasources.countries set name='Faroe Islands' WHERE iso_a3 = 'FRO';
UPDATE datasources.countries set name='French Polynesia' WHERE iso_a3 = 'PYF';
UPDATE datasources.countries set name='French Southern and Antarctic Lands' WHERE iso_a3 = 'ATF';
UPDATE datasources.countries set name='Heard Island and McDonald Islands' WHERE iso_a3 = 'HMD';
UPDATE datasources.countries set name='Indian Ocean Territories' WHERE name = 'Indian Ocean Ter.';
UPDATE datasources.countries set name='Ivory Coast' WHERE iso_a3 = 'CIV';
UPDATE datasources.countries set name='Laos' WHERE iso_a3 = 'LAO';
UPDATE datasources.countries set name='Marshall Islands' WHERE iso_a3 = 'MHL';
UPDATE datasources.countries set name='Northern Mariana Islands' WHERE iso_a3 = 'MNP';
UPDATE datasources.countries set name='Pitcairn Islands' WHERE iso_a3 = 'PCN';
UPDATE datasources.countries set name='South Georgia and South Sandwich Islands' WHERE iso_a3 = 'SGS';
UPDATE datasources.countries set name='Spratly Islands' WHERE name = 'Spratly Is.';
UPDATE datasources.countries set name='Saint-Barthélemy' WHERE iso_a3 = 'BLM';
UPDATE datasources.countries set name='Saint Kitts and Nevis' WHERE iso_a3 = 'KNA';
UPDATE datasources.countries set name='Saint Pierre and Miquelon' WHERE iso_a3 = 'SPM';
UPDATE datasources.countries set name='Saint-Martin' WHERE iso_a3 = 'MAF';
UPDATE datasources.countries set name='Saint Vincent and the Grenadines' WHERE iso_a3 = 'VCT';
UPDATE datasources.countries set name='São Tomé and Príncipe' WHERE iso_a3 = 'STP';
UPDATE datasources.countries set name='South Sudan' WHERE iso_a3 = 'SSD';
UPDATE datasources.countries set name='Solomon Islands' WHERE iso_a3 = 'SLB';
UPDATE datasources.countries set name='Turks and Caicos Islands' WHERE iso_a3 = 'TCA';
UPDATE datasources.countries set name='United States Minor Outlying Islands' WHERE iso_a3 = 'UMI';
UPDATE datasources.countries set name='United States of America' WHERE name='United States';
UPDATE datasources.countries set name='United States Virgin Islands' WHERE iso_a3 = 'VIR';
UPDATE datasources.countries set name='Western Sahara' WHERE iso_a3 = 'ESH';
UPDATE datasources.countries set name='Wallis and Futuna' WHERE iso_a3 = 'WLF';

UPDATE datasources.countries set iso_a2='FR', iso_a3='FRA' WHERE name = 'France';
UPDATE datasources.countries set iso_a2='XK', iso_a3='UNK' WHERE name = 'Kosovo';
UPDATE datasources.countries set iso_a2='NO', iso_a3='NOR' WHERE name = 'Norway';
UPDATE datasources.countries set iso_a3='CPT' WHERE name = 'Clipperton Island';

CREATE INDEX idx_countries_name ON datasources.countries (normalize(name));
CREATE INDEX idx_countries_geom ON datasources.countries USING gist(geom);
EOF

## World administrative level 1 (i.e. states for USA, departements for France)
shp2pgsql -g geom -d -W UTF8 -s 4326 -I $STATES datasources.states | psql -d $DB -U $SUPERUSER $HOSTNAME
psql -d $DB -U $SUPERUSER  $HOSTNAME << EOF
UPDATE datasources.states SET name='Seine-et-Marne' WHERE name='Seien-et-Marne';
CREATE INDEX idx_states_geom ON datasources.states USING gist(geom);
CREATE INDEX idx_states_name ON datasources.states (normalize(name));
CREATE INDEX idx_states_region ON datasources.states (normalize(region));
EOF

## Regions created from states table
psql -d $DB -U $SUPERUSER $HOSTNAME << EOF
--
-- Some explanation here
--
--   Regions geometries are computed from the union of the geometries
--   of their respective states.
--   Since states boundaries do not fit perfectly, the geometry union is
--   simplified. A buffer is then applied to correct invalid geometries
--
CREATE TABLE datasources.regions AS (SELECT region as name, admin, iso_a2, st_buffer(st_simplify(st_union(geom), 0.01), 0) as geom from datasources.states where region is NOT NULL group by region,admin,iso_a2);
CREATE INDEX idx_regions_geom ON datasources.regions USING gist(geom);
CREATE INDEX idx_regions_name ON datasources.regions (normalize(name));
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
shp2pgsql -g geom -d -W LATIN1 -s 4326 -I $RIVERS datasources.rivers | psql -d $DB -U $SUPERUSER $HOSTNAME

# =================== OTHER ==================
## Insert Physicals data
shp2pgsql -g geom -d -W LATIN1 -s 4326 -I $MARINEAREAS datasources.physical | psql -d $DB -U $SUPERUSER $HOSTNAME
psql -d $DB  -U $SUPERUSER $HOSTNAME << EOF
DELETE FROM datasources.physical WHERE name IS NULL;
UPDATE datasources.physical set name='Arctic Ocean',name_fr='Océan Arctique' WHERE name='ARCTIC OCEAN';
UPDATE datasources.physical set name='Beaufort Sea' WHERE name='Beaufort  Sea';
UPDATE datasources.physical set name='Bransfield Strait' WHERE name='Bransfield Str.';
UPDATE datasources.physical set name='Davis Strait' WHERE name='Davis  Strait';
UPDATE datasources.physical set name='Cumberland Sd.' WHERE name='Cumberland Bay';
UPDATE datasources.physical set name='Denmark Strait' WHERE name='Denmark  Strait';
UPDATE datasources.physical set name='Gulf St Vincent' WHERE name='Gulf St. Vincent';
UPDATE datasources.physical set name='Gulf of Anadyr' WHERE name='Gulf of Anadyr''';
UPDATE datasources.physical set name='Gulf of Saint Lawrence' WHERE name='Gulf of St. Lawrence';
UPDATE datasources.physical set name='Indian Ocean',name_fr='Océan Indien' WHERE name='INDIAN OCEAN';
UPDATE datasources.physical set name='North Atlantic Ocean',name_fr='Océan Atlantique Nord' WHERE name='NORTH ATLANTIC OCEAN';
UPDATE datasources.physical set name='North Pacific Ocean',name_fr='Océan Pacifique Nord' WHERE name='NORTH PACIFIC OCEAN';
UPDATE datasources.physical set name='Ross Sea' WHERE name='Ross  Sea';
UPDATE datasources.physical set name='South Atlantic Ocean',name_fr='Océan Atlantique Sud' WHERE name='SOUTH ATLANTIC OCEAN';
UPDATE datasources.physical set name='South Pacific Ocean',name_fr='Océan Pacifique Sud' WHERE name='SOUTH PACIFIC OCEAN';
UPDATE datasources.physical set name='Southern Ocean',name_fr='Océan Antarctique' WHERE name='SOUTHERN OCEAN';
UPDATE datasources.physical set name='St Helena Bay' WHERE name='St. Helena Bay';
UPDATE datasources.physical set name='St Lawrence River' WHERE name='St. Lawrence River';
UPDATE datasources.physical set name='Tasman Sea' WHERE name='Tasman  Sea';
UPDATE datasources.physical set name='Weddell Sea' WHERE name='Weddell  Sea';
UPDATE datasources.physical set name='White Sea' WHERE name='White  Sea';
CREATE INDEX idx_physical_name ON datasources.physical (normalize(name));
EOF

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
CREATE SCHEMA gpw;
CREATE TABLE gpw.glp15ag60 (
    gid                 VARCHAR(8) PRIMARY KEY,
    pcount              INTEGER
);
SELECT AddGeometryColumn('gpw', 'glp15ag60','footprint','4326','POLYGON',2);

-- ===============================
-- Population 2015 0.5x0.5 degrees
-- ===============================
CREATE TABLE gpw.glp15ag30 (
    gid                 VARCHAR(8) PRIMARY KEY,
    pcount              INTEGER
);
SELECT AddGeometryColumn('gpw', 'glp15ag30','footprint','4326','POLYGON',2);

-- =================================
-- Population 2015 0.25x0.25 degrees
-- =================================
CREATE TABLE gpw.glp15ag15 (
    gid                 VARCHAR(8) PRIMARY KEY,
    pcount              INTEGER
);
SELECT AddGeometryColumn('gpw', 'glp15ag15','footprint','4326','POLYGON',2);

-- ===================================
-- Population 2015 2.5x2.5 arc minutes
-- ===================================
CREATE TABLE gpw.glp15ag (
    gid                 VARCHAR(8) PRIMARY KEY,
    pcount              INTEGER
);
SELECT AddGeometryColumn('gpw', 'glp15ag','footprint','4326','POLYGON',2);
EOF

# GRANT RIGHTS TO itag USER
psql -U $SUPERUSER -d $DB $HOSTNAME << EOF
GRANT ALL ON SCHEMA datasources to $USER;
GRANT SELECT on datasources.coastlines to $USER;
GRANT SELECT on datasources.states to $USER;
GRANT SELECT on datasources.regions to $USER;
GRANT SELECT on datasources.countries to $USER;
GRANT SELECT on datasources.continents to $USER;
GRANT SELECT on datasources.rivers to $USER;
GRANT SELECT on datasources.glaciers to $USER;
GRANT SELECT on datasources.plates to $USER;
GRANT SELECT on datasources.faults to $USER;
GRANT SELECT on datasources.volcanoes to $USER;
GRANT SELECT on datasources.landcover to $USER;
GRANT SELECT on datasources.physical to $USER;
GRANT ALL ON SCHEMA gpw TO $USER;
GRANT SELECT ON gpw.glp15ag to $USER;
GRANT SELECT ON gpw.glp15ag15 to $USER;
GRANT SELECT ON gpw.glp15ag30 to $USER;
GRANT SELECT ON gpw.glp15ag60 to $USER;
EOF
