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
ENCODING=UTF8

function showUsage {
    echo ""
    echo "   Install itag datasources "
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
    echo -e "${RED}[ERROR]${NC} Missing or invalid config file!"
    echo ""
    exit 0
fi

if [ "${DATA_DIR}" == "__NULL__" ]; then
    showUsage
    echo -e "${RED}[ERROR]${NC} You must specify a data directory!"
    echo ""
    exit 0
fi

# Source config file
. ${ENV_FILE}

echo -e "[INFO] Create datasources schema"
PGPASSWORD=${ITAG_DATABASE_USER_PASSWORD} psql -U ${ITAG_DATABASE_USER_NAME} -d ${ITAG_DATABASE_NAME} -h localhost -p ${ITAG_DATABASE_EXPOSED_PORT} > /dev/null 2>errors.log << EOF
CREATE EXTENSION postgis;
CREATE EXTENSION postgis_topology;

CREATE EXTENSION unaccent SCHEMA public;

--
-- Create IMMUTABLE unaccent function 
--
CREATE OR REPLACE FUNCTION public.f_unaccent(text) RETURNS text AS
\$func\$
SELECT public.unaccent('public.unaccent', \$1)  -- schema-qualify function and dictionary
\$func\$  LANGUAGE sql IMMUTABLE;

--
-- Return a transliterated version of input string in lowercase
--
CREATE OR REPLACE FUNCTION normalize(input TEXT, separator text DEFAULT '') 
RETURNS TEXT AS \$\$
BEGIN
    RETURN translate(lower(public.f_unaccent(input)), ' '',:-\`\´\‘\’_' , separator);
END
\$\$ LANGUAGE 'plpgsql' IMMUTABLE;

--
-- Return a transliterated version of input string which the first letter of each word
-- is in uppercase and the remaining characters in lowercase
--
CREATE OR REPLACE FUNCTION normalize_initcap(input TEXT, separator text DEFAULT '') 
RETURNS TEXT AS \$\$
BEGIN
    RETURN translate(initcap(public.f_unaccent(input)), ' '',:-\`\´\‘\’_' , separator);
END
\$\$ LANGUAGE 'plpgsql' IMMUTABLE;

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

CREATE SCHEMA gpw;

CREATE TABLE gpw.glp15ag60 (
    gid                 VARCHAR(8) PRIMARY KEY,
    pcount              NUMERIC
);
SELECT AddGeometryColumn('gpw', 'glp15ag60','footprint','4326','POLYGON',2);

-- ===============================
-- Population 2015 0.5x0.5 degrees
-- ===============================
CREATE TABLE gpw.glp15ag30 (
    gid                 VARCHAR(8) PRIMARY KEY,
    pcount              NUMERIC
);
SELECT AddGeometryColumn('gpw', 'glp15ag30','footprint','4326','POLYGON',2);

-- =================================
-- Population 2015 0.25x0.25 degrees
-- =================================
CREATE TABLE gpw.glp15ag15 (
    gid                 VARCHAR(8) PRIMARY KEY,
    pcount              NUMERIC
);
SELECT AddGeometryColumn('gpw', 'glp15ag15','footprint','4326','POLYGON',2);

-- ===================================
-- Population 2015 2.5x2.5 arc minutes
-- ===================================
CREATE TABLE gpw.glp15ag (
    gid                 VARCHAR(8) PRIMARY KEY,
    pcount              NUMERIC
);
SELECT AddGeometryColumn('gpw', 'glp15ag','footprint','4326','POLYGON',2);

CREATE SCHEMA landcover;
DROP TABLE landcover.landcover CASCADE;
CREATE TABLE landcover.landcover (
    ogc_fid         SERIAL,
    dn              NUMERIC
);
SELECT AddGeometryColumn ('landcover','landcover','wkb_geometry',4326,'POLYGON',2);

DROP TABLE landcover.landcover2009 CASCADE;
CREATE TABLE landcover.landcover2009 (
    ogc_fid         SERIAL,
    dn              INTEGER
);
SELECT AddGeometryColumn ('landcover','landcover2009','wkb_geometry',4326,'POLYGON',2);

CREATE SCHEMA datasources

EOF

# Prepare data directory
if [ -d "${DATA_DIR}" ];
then
    echo -e "[INFO] Using existing ${DATA_DIR} directory"
else
    echo -e "[INFO] Creating ${DATA_DIR} directory"
    mkdir -p ${DATA_DIR}
fi

SHP2PGSQL="docker run --rm -v ${DATA_DIR}:/data:ro jjrom/shp2pgsql"

# ================================================================================
COASTLINES=ne_10m_coastline.shp
echo -e "[INFO] Retrieve coastlines from [Natural Earth]"
if [ ! -f ${DATA_DIR}/${COASTLINES} ]; then
    wget -O ${DATA_DIR}/ne_10m_coastline.zip http://www.naturalearthdata.com/http//www.naturalearthdata.com/download/10m/physical/ne_10m_coastline.zip
    unzip -q ${DATA_DIR}/ne_10m_coastline.zip -d ${DATA_DIR}
    [ $? -eq 0 ] && rm ${DATA_DIR}/ne_10m_coastline.zip
else
    echo -e "[INFO] Using existing ${COASTLINES} data" 
fi
${SHP2PGSQL} -g geom -d -W ${ENCODING} -s 4326 -I /data/${COASTLINES} datasources.coastlines 2> /dev/null | PGPASSWORD=${ITAG_DATABASE_USER_PASSWORD} psql -U ${ITAG_DATABASE_USER_NAME} -d ${ITAG_DATABASE_NAME} -h localhost -p ${ITAG_DATABASE_EXPOSED_PORT} > /dev/null 2>errors.log
PGPASSWORD=${ITAG_DATABASE_USER_PASSWORD} psql -U ${ITAG_DATABASE_USER_NAME} -d ${ITAG_DATABASE_NAME} -h localhost -p ${ITAG_DATABASE_EXPOSED_PORT} > /dev/null 2>errors.log << EOF
CREATE INDEX idx_coastlines_geom ON datasources.coastlines USING gist(geom);
EOF
echo -e "${GREEN}[INFO] Coastlines installed${NC}"


# ================================================================================
CONTINENTS=hotspots/continent.shp
echo -e "[INFO] Retrieve geophysical data from [Mapping Tectonic Hot Spots]"
if [ ! -f ${DATA_DIR}/${CONTINENTS} ]; then
    #wget http://www.colorado.edu/geography/foote/maps/assign/hotspots/download/hotspots.zip
    wget -O ${DATA_DIR}/hotspots.zip https://www.dropbox.com/s/v43nbjgbhw8i2i5/hotspots.zip
    unzip -q ${DATA_DIR}/hotspots.zip -d ${DATA_DIR}
    [ $? -eq 0 ] && rm ${DATA_DIR}/hotspots.zip
else
    echo -e "[INFO] Using existing ${CONTINENTS} data" 
fi
${SHP2PGSQL} -g geom -d -W ${ENCODING} -s 4326 -I /data/${CONTINENTS} datasources.continents 2> /dev/null | PGPASSWORD=${ITAG_DATABASE_USER_PASSWORD} psql -U ${ITAG_DATABASE_USER_NAME} -d ${ITAG_DATABASE_NAME} -h localhost -p ${ITAG_DATABASE_EXPOSED_PORT} > /dev/null 2>errors.log
echo -e "${GREEN}[INFO] Continents installed${NC}"


# ================================================================================
COUNTRIES=ne_10m_admin_0_countries.shp
echo -e "[INFO] Installing World Administrative Level 1 data from [Natural Earth]"
if [ ! -f ${DATA_DIR}/${COUNTRIES} ]; then
    wget -O ${DATA_DIR}/ne_10m_admin_0_countries.zip http://www.naturalearthdata.com/http//www.naturalearthdata.com/download/10m/cultural/ne_10m_admin_0_countries.zip
    unzip -q ${DATA_DIR}/ne_10m_admin_0_countries.zip -d ${DATA_DIR}
    [ $? -eq 0 ] && rm ${DATA_DIR}/ne_10m_admin_0_countries.zip
else
    echo -e "[INFO] Using existing ${COUNTRIES} data" 
fi
${SHP2PGSQL} -g geom -d -W ${ENCODING} -s 4326 -I /data/${COUNTRIES} datasources.countries 2> /dev/null | PGPASSWORD=${ITAG_DATABASE_USER_PASSWORD} psql -U ${ITAG_DATABASE_USER_NAME} -d ${ITAG_DATABASE_NAME} -h localhost -p ${ITAG_DATABASE_EXPOSED_PORT} > /dev/null 2>errors.log
PGPASSWORD=${ITAG_DATABASE_USER_PASSWORD} psql -U ${ITAG_DATABASE_USER_NAME} -d ${ITAG_DATABASE_NAME} -h localhost -p ${ITAG_DATABASE_EXPOSED_PORT} > /dev/null 2>errors.log << EOF
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

-- See http://blog.cleverelephant.ca/2018/09/postgis-external-storage.html
ALTER TABLE datasources.countries ALTER COLUMN geom SET STORAGE EXTERNAL;
UPDATE datasources.countries SET geom = ST_SetSRID(geom, 4326);

CREATE INDEX idx_countries_name ON datasources.countries (normalize(name));
CREATE INDEX idx_countries_geom ON datasources.countries USING gist(geom);
EOF
echo -e "${GREEN}[INFO] Countries installed${NC}"

# ================================================================================
STATES=ne_10m_admin_1_states_provinces.shp
echo -e "[INFO] World administrative level 1 (i.e. states for USA, departements for France)"
if [ ! -f ${DATA_DIR}/${STATES} ]; then
    wget -O ${DATA_DIR}/ne_10m_admin_1_states_provinces.zip http://www.naturalearthdata.com/http//www.naturalearthdata.com/download/10m/cultural/ne_10m_admin_1_states_provinces.zip
    unzip -q ${DATA_DIR}/ne_10m_admin_1_states_provinces.zip -d ${DATA_DIR}
    [ $? -eq 0 ] && rm ${DATA_DIR}/ne_10m_admin_1_states_provinces.zip
else
    echo -e "[INFO] Using existing ${STATES} data" 
fi

${SHP2PGSQL} -g geom -d -W ${ENCODING} -s 4326 -I /data/${STATES} datasources.states 2> /dev/null | PGPASSWORD=${ITAG_DATABASE_USER_PASSWORD} psql -U ${ITAG_DATABASE_USER_NAME} -d ${ITAG_DATABASE_NAME} -h localhost -p ${ITAG_DATABASE_EXPOSED_PORT} > /dev/null 2>errors.log
PGPASSWORD=${ITAG_DATABASE_USER_PASSWORD} psql -U ${ITAG_DATABASE_USER_NAME} -d ${ITAG_DATABASE_NAME} -h localhost -p ${ITAG_DATABASE_EXPOSED_PORT} > /dev/null 2>errors.log << EOF
UPDATE datasources.states SET name='Seine-et-Marne' WHERE name='Seien-et-Marne';
UPDATE datasources.states SET iso_a2='GF' WHERe gid=52;
UPDATE datasources.states SET iso_a2='MQ' WHERe gid=2613;
UPDATE datasources.states SET iso_a2='YT' WHERe gid=2769;
CREATE INDEX idx_states_geom ON datasources.states USING gist(geom);
CREATE INDEX idx_states_name ON datasources.states (normalize(name));
CREATE INDEX idx_states_region ON datasources.states (normalize(region));
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
echo -e "${GREEN}[INFO] Regions and States installed${NC}"

# ================================================================================
PLATES=hotspots/plates.shp
FAULTS=hotspots/FAULTS.shp
VOLCANOES=hotspots/VOLCANO.SHP
${SHP2PGSQL} -g geom -d -W ${ENCODING} -s 4326 -I /data/${STATES} datasources.plates 2> /dev/null | PGPASSWORD=${ITAG_DATABASE_USER_PASSWORD} psql -U ${ITAG_DATABASE_USER_NAME} -d ${ITAG_DATABASE_NAME} -h localhost -p ${ITAG_DATABASE_EXPOSED_PORT} > /dev/null 2>errors.log
${SHP2PGSQL} -g geom -d -W ${ENCODING} -s 4326 -I /data/${VOLCANOES} datasources.volcanoes 2> /dev/null | PGPASSWORD=${ITAG_DATABASE_USER_PASSWORD} psql -U ${ITAG_DATABASE_USER_NAME} -d ${ITAG_DATABASE_NAME} -h localhost -p ${ITAG_DATABASE_EXPOSED_PORT} > /dev/null 2>errors.log
${SHP2PGSQL} -g geom -d -W ${ENCODING} -s 4326 -I /data/${FAULTS} datasources.faults 2> /dev/null | PGPASSWORD=${ITAG_DATABASE_USER_PASSWORD} psql -U ${ITAG_DATABASE_USER_NAME} -d ${ITAG_DATABASE_NAME} -h localhost -p ${ITAG_DATABASE_EXPOSED_PORT} > /dev/null 2>errors.log
PGPASSWORD=${ITAG_DATABASE_USER_PASSWORD} psql -U ${ITAG_DATABASE_USER_NAME} -d ${ITAG_DATABASE_NAME} -h localhost -p ${ITAG_DATABASE_EXPOSED_PORT} > /dev/null 2>errors.log << EOF
DELETE FROM datasources.faults WHERE type IS NULL;
UPDATE datasources.faults set type='Thrust fault' WHERE type='thrust-fault';
UPDATE datasources.faults set type='step' WHERE type='Step';
UPDATE datasources.faults set type='Tectonic Contact' WHERE type='tectonic contact';
UPDATE datasources.faults set type='Rift' WHERE type='rift';
CREATE INDEX idx_faults_geom ON datasources.faults USING gist(geom);
CREATE INDEX idx_volcanoes_geom ON datasources.volcanoes USING gist(geom);
CREATE INDEX idx_plates_geom ON datasources.plates USING gist(geom);
EOF
echo -e "${GREEN}[INFO] Plates, faults and volcanoes installed${NC}"

# ================================================================================
GLACIERS=ne_10m_glaciated_areas.shp
echo -e "[INFO] Retrieve glaciers from [Natural Earth]"
if [ ! -f ${DATA_DIR}/${GLACIERS} ]; then
    wget -O ${DATA_DIR}/ne_10m_glaciated_areas.zip http://www.naturalearthdata.com/http//www.naturalearthdata.com/download/10m/physical/ne_10m_glaciated_areas.zip
    unzip -q ${DATA_DIR}/ne_10m_glaciated_areas.zip -d ${DATA_DIR}
    [ $? -eq 0 ] && rm ${DATA_DIR}/ne_10m_glaciated_areas.zip
else
    echo -e "[INFO] Using existing ${GLACIERS} data" 
fi
${SHP2PGSQL} -g geom -d -W ${ENCODING} -s 4326 -I /data/${GLACIERS} datasources.glaciers 2> /dev/null | PGPASSWORD=${ITAG_DATABASE_USER_PASSWORD} psql -U ${ITAG_DATABASE_USER_NAME} -d ${ITAG_DATABASE_NAME} -h localhost -p ${ITAG_DATABASE_EXPOSED_PORT} > /dev/null 2>errors.log
echo -e "${GREEN}[INFO] Glaciers installed${NC}"

# ================================================================================
RIVERS=ne_10m_rivers_lake_centerlines.shp
echo -e "[INFO] Retrieve rivers from [Natural Earth]"
if [ ! -f ${DATA_DIR}/${RIVERS} ]; then
    wget -O ${DATA_DIR}/ne_10m_rivers_lake_centerlines.zip http://www.naturalearthdata.com/http//www.naturalearthdata.com/download/10m/physical/ne_10m_rivers_lake_centerlines.zip
    unzip -q ${DATA_DIR}/ne_10m_rivers_lake_centerlines.zip -d ${DATA_DIR}
    [ $? -eq 0 ] && rm ${DATA_DIR}/ne_10m_rivers_lake_centerlines.zip
else
    echo -e "[INFO] Using existing ${RIVERS} data" 
fi
${SHP2PGSQL} -g geom -d -W ${ENCODING} -s 4326 -I /data/${RIVERS} datasources.rivers 2> /dev/null | PGPASSWORD=${ITAG_DATABASE_USER_PASSWORD} psql -U ${ITAG_DATABASE_USER_NAME} -d ${ITAG_DATABASE_NAME} -h localhost -p ${ITAG_DATABASE_EXPOSED_PORT} > /dev/null 2>errors.log
echo -e "${GREEN}[INFO] Rivers installed${NC}"

# ================================================================================
MARINEAREAS=ne_10m_geography_marine_polys.shp
echo -e "[INFO] Retrieve other data (i.e. marine areas, mountains area, etc.) from [Natural Earth]"
if [ ! -f ${DATA_DIR}/${MARINEAREAS} ]; then
    wget -O ${DATA_DIR}/ne_10m_geography_marine_polys.zip http://www.naturalearthdata.com/http//www.naturalearthdata.com/download/10m/physical/ne_10m_geography_marine_polys.zip
    unzip -q ${DATA_DIR}/ne_10m_geography_marine_polys.zip -d ${DATA_DIR}
    [ $? -eq 0 ] && rm ${DATA_DIR}/ne_10m_geography_marine_polys.zip
else
    echo -e "[INFO] Using existing ${MARINEAREAS} data" 
fi
${SHP2PGSQL} -g geom -d -W ${ENCODING} -s 4326 -I /data/${MARINEAREAS} datasources.physical 2> /dev/null | PGPASSWORD=${ITAG_DATABASE_USER_PASSWORD} psql -U ${ITAG_DATABASE_USER_NAME} -d ${ITAG_DATABASE_NAME} -h localhost -p ${ITAG_DATABASE_EXPOSED_PORT} > /dev/null 2>errors.log
PGPASSWORD=${ITAG_DATABASE_USER_PASSWORD} psql -U ${ITAG_DATABASE_USER_NAME} -d ${ITAG_DATABASE_NAME} -h localhost -p ${ITAG_DATABASE_EXPOSED_PORT} > /dev/null 2>errors.log << EOF
DELETE FROM datasources.physical WHERE name IS NULL;
UPDATE datasources.physical set name='Arctic Ocean',name_fr='Océan Arctique' WHERE name='ARCTIC OCEAN';
UPDATE datasources.physical set name='Beaufort Sea' WHERE name='Beaufort  Sea';
UPDATE datasources.physical set name='Bransfield Strait' WHERE name='Bransfield Str.';
UPDATE datasources.physical set name='Davis Strait' WHERE name='Davis  Strait';
UPDATE datasources.physical set name='Caribbean Sea' WHERE name='Caribbean  Sea';
UPDATE datasources.physical set name='Cumberland Sd.' WHERE name='Cumberland Bay';
UPDATE datasources.physical set name='Denmark Strait' WHERE name='Denmark  Strait';
UPDATE datasources.physical set name='Gulf St Vincent' WHERE name='Gulf St. Vincent';
UPDATE datasources.physical set name='Gulf of Anadyr' WHERE name='Gulf of Anadyr''';
UPDATE datasources.physical set name='Gulf of Saint Lawrence' WHERE name='Gulf of St. Lawrence';
UPDATE datasources.physical set name='Gulf of Suez' WHERE name='Gulf of  Suez';
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
-- Merge polygons
UPDATE datasources.physical SET geom=(select st_multi(st_union(geom)) FROM datasources.physical WHERE name='Gulf of Anadyr') WHERE gid=159;
DELETE FROM datasources.physical WHERE gid=271;
UPDATE datasources.physical SET geom=(select st_multi(st_union(geom)) FROM datasources.physical WHERE name='Mediterranean Sea') WHERE gid=15;
DELETE FROM datasources.physical WHERE gid=63;
UPDATE datasources.physical SET geom=(select st_multi(st_union(geom)) FROM datasources.physical where name='Ross Sea') WHERE gid=27;
DELETE FROM datasources.physical WHERE gid=30;
CREATE INDEX idx_physical_name ON datasources.physical (normalize(name));
EOF
echo -e "${GREEN}[INFO] Rivers installed${NC}"
