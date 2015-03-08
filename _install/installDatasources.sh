#!/bin/bash
#
#  iTag
#
#  iTag - Semantic enhancement of Earth Observation data
#  
#  Copyright 2013 Jérôme Gasperi <https://github.com/jjrom>
# 
#  jerome[dot]gasperi[at]gmail[dot]com
#  
#  
#  This software is governed by the CeCILL-B license under French law and
#  abiding by the rules of distribution of free software.  You can  use,
#  modify and/ or redistribute the software under the terms of the CeCILL-B
#  license as circulated by CEA, CNRS and INRIA at the following URL
#  "http://www.cecill.info".
# 
#  As a counterpart to the access to the source code and  rights to copy,
#  modify and redistribute granted by the license, users are provided only
#  with a limited warranty  and the software's author,  the holder of the
#  economic rights,  and the successive licensors  have only  limited
#  liability.
# 
#  In this respect, the user's attention is drawn to the risks associated
#  with loading,  using,  modifying and/or developing or reproducing the
#  software by the user in light of its specific status of free software,
#  that may mean  that it is complicated to manipulate,  and  that  also
#  therefore means  that it is reserved for developers  and  experienced
#  professionals having in-depth computer knowledge. Users are therefore
#  encouraged to load and test the software's suitability as regards their
#  requirements in conditions enabling the security of their systems and/or
#  data to be ensured and,  more generally, to use and operate it in the
#  same conditions as regards security.
# 
#  The fact that you are presently reading this means that you have had
#  knowledge of the CeCILL-B license and that you accept its terms.
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

## Insert Continents
shp2pgsql -g geom -d -W UTF8 -s 4326 -I $DATADIR/political/continents/continent.shp datasources.continents | psql -d $DB -U $SUPERUSER $HOSTNAME
psql -d itag  -U $SUPERUSER $HOSTNAME<< EOF
CREATE INDEX idx_continents_name ON datasources.continents (continent);
EOF

## Insert Countries
shp2pgsql -g geom -d -W LATIN1 -s 4326 -I $DATADIR/political/countries/ne_110m_admin_0_countries.shp datasources.countries | psql -d $DB -U $SUPERUSER $HOSTNAME
psql -d $DB  -U $SUPERUSER $HOSTNAME << EOF
--
-- This is for WorldCountries not for ne_110m_admin_0_countries
-- UPDATE datasources.countries set name='Congo' WHERE iso_3166_3='ZAR';
-- UPDATE datasources.countries set name='Macedonia' WHERE iso_3166_3='MKD';
--
-- Optimisation : predetermine continent for each country
-- ALTER TABLE datasources.countries ADD COLUMN continent VARCHAR(13);
-- UPDATE datasources.countries SET continent = (SELECT continent FROM datasources.continents WHERE st_intersects(continents.geom, countries.geom) LIMIT 1);
--
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
UPDATE  datasources.countries set name='United States of America' WHERE name='United States';

CREATE INDEX idx_countries_name ON datasources.countries (name);
CREATE INDEX idx_countries_geom ON datasources.countries USING gist(geom);
EOF

## Insert Cities
shp2pgsql -g geom -d -W UTF8 -s 4326 -I $DATADIR/political/cities/cities.shp datasources.cities | psql -d $DB -U $SUPERUSER $HOSTNAME
psql -d $DB -U $SUPERUSER $HOSTNAME << EOF
UPDATE datasources.cities set country='The Gambia' WHERE country='Gambia';
CREATE INDEX idx_cities_name ON datasources.cities (name);
EOF

## French departments
shp2pgsql -g geom -d -W UTF8 -s 4326 -I $DATADIR/political/france/deptsfrance.shp datasources.deptsfrance | psql -d $DB -U $SUPERUSER $HOSTNAME
psql -d $DB -U $SUPERUSER $HOSTNAME << EOF
UPDATE datasources.deptsfrance set nom_dept=initcap(nom_dept);
UPDATE datasources.deptsfrance set nom_dept=replace(nom_dept, '-De-', '-de-');
UPDATE datasources.deptsfrance set nom_dept=replace(nom_dept, ' De ', ' de ');
UPDATE datasources.deptsfrance set nom_dept=replace(nom_dept, 'D''', 'd''');
UPDATE datasources.deptsfrance set nom_dept=replace(nom_dept, '-Et-', '-et-');
UPDATE datasources.deptsfrance set nom_region=initcap(nom_region);
UPDATE datasources.deptsfrance set nom_region='Ile-de-France' WHERE nom_region='Ile-De-France';
UPDATE datasources.deptsfrance set nom_region='Nord-Pas-de-Calais' WHERE nom_region='Nord-Pas-De-Calais';
UPDATE datasources.deptsfrance set nom_region='Pays de la Loire' WHERE nom_region='Pays De La Loire';
UPDATE datasources.deptsfrance set nom_region='Provence-Alpes-Cote d''Azur' WHERE nom_region='Provence-Alpes-Cote D''Azur';
CREATE INDEX idx_deptsfrance_dept ON datasources.deptsfrance (nom_dept);
CREATE INDEX idx_deptsfrance_region ON datasources.deptsfrance (nom_region);
EOF

## French communes
shp2pgsql -d -W UTF8 -s 4326 -I $DATADIR/political/france/commfrance.shp datasources.commfrance | psql -d $DB -U $SUPERUSER $HOSTNAME
psql -d $DB -U $SUPERUSER $HOSTNAME << EOF
UPDATE datasources.commfrance set nom_comm=initcap(nom_comm);
UPDATE datasources.commfrance set nom_comm=replace(nom_comm, '-Sur-', '-sur-');
UPDATE datasources.commfrance set nom_comm=replace(nom_comm, '-De-', '-de-');
UPDATE datasources.commfrance set nom_comm=replace(nom_comm, ' De ', ' de ');
UPDATE datasources.commfrance set nom_comm=replace(nom_comm, 'D''', 'd''');
UPDATE datasources.commfrance set nom_comm=replace(nom_comm, '-Et-', '-et-');
UPDATE datasources.commfrance set nom_comm=replace(nom_comm, '-Le-', '-le-');
UPDATE datasources.commfrance set nom_comm=replace(nom_comm, '-La-', '-la-');
UPDATE datasources.commfrance set nom_comm=replace(nom_comm, '-Les-', '-les-');
CREATE INDEX idx_commfrance_comm ON datasources.commfrance (nom_comm);
EOF

## French arrondissements
shp2pgsql -d -W UTF8 -s 4326 -I $DATADIR/political/france/arrsfrance.shp datasources.arrsfrance | psql -d $DB -U $SUPERUSER $HOSTNAME
psql -d $DB -U $SUPERUSER $HOSTNAME << EOF
UPDATE datasources.arrsfrance set nom_chf=initcap(nom_chf);
CREATE INDEX idx_arrsfrance_arrs ON datasources.arrsfrance (nom_chf);
EOF

## World administrative level 1 (i.e. states for USA, departements for France)
shp2pgsql -g geom -d -W UTF8 -s 4326 -I $DATADIR/political/ne_10m_admin_1_states_provinces/ne_10m_admin_1_states_provinces.shp datasources.worldadm1level | psql -d $DB -U $SUPERUSER $HOSTNAME
psql -d $DB -U $SUPERUSER  $HOSTNAME << EOF
UPDATE datasources.worldadm1level SET name='Seine-et-Marne' WHERE name='Seien-et-Marne';
CREATE INDEX idx_worldadm1level_geom ON datasources.worldadm1level USING gist(geom);
CREATE INDEX idx_worldadm1level_name ON datasources.worldadm1level (normalize(name));
CREATE INDEX idx_worldadm1level_region ON datasources.worldadm1level (normalize(region));
EOF


# =================== GEOPHYSICAL ==================
## Insert plates
shp2pgsql -g geom -d -W UTF8 -s 4326 -I $DATADIR/geophysical/plates/plates.shp datasources.plates | psql -d $DB -U $SUPERUSER $HOSTNAME

## Insert faults
shp2pgsql -g geom -d -W UTF8 -s 4326 -I $DATADIR/geophysical/faults/FAULTS.SHP datasources.faults | psql -d $DB -U $SUPERUSER $HOSTNAME
psql -d $DB  -U $SUPERUSER $HOSTNAME << EOF
DELETE FROM datasources.faults WHERE type IS NULL;
UPDATE datasources.faults set type='Thrust fault' WHERE type='thrust-fault';
UPDATE datasources.faults set type='step' WHERE type='Step';
UPDATE datasources.faults set type='Tectonic Contact' WHERE type='tectonic contact';
UPDATE datasources.faults set type='Rift' WHERE type='rift';
EOF

## Insert volcanoes
shp2pgsql -g geom -d -W UTF8 -s 4326 -I $DATADIR/geophysical/volcanoes/VOLCANO.SHP datasources.volcanoes | psql -d $DB -U $SUPERUSER $HOSTNAME

## Insert Rivers
shp2pgsql -g geom -d -W UTF8 -s 4326 -I $DATADIR/geophysical/ne_50m_rivers_lake_centerlines/ne_50m_rivers_lake_centerlines.shp datasources.rivers | psql -d $DB -U $SUPERUSER $HOSTNAME

# ==================== LANDCOVER =====================
psql -U $SUPERUSER -d $DB $HOSTNAME << EOF
CREATE TABLE datasources.landcover (
    ogc_fid         SERIAL,
    dn              INTEGER
);
SELECT AddGeometryColumn ('datasources','landcover','wkb_geometry',4326,'POLYGON',2);
CREATE INDEX landcover_geometry_idx ON datasources.landcover USING gist (wkb_geometry);
EOF

# ===== UNUSUED ======
## Insert glaciers
shp2pgsql -g geom -d -W UTF8 -s 4326 -I $DATADIR/geophysical/glaciers/Glacier.shp datasources.glaciers | psql -d $DB -U $SUPERUSER $HOSTNAME
## DOWNLOAD THIS INSTEAD - http://nsidc.org/data/docs/noaa/g01130_glacier_inventory/#data_descriptions

## Major earthquakes since 1900
#shp2pgsql -g geom -d -W UTF8 -s 4326 -I $DATADIR/geophysical/earthquakes/MajorEarthquakes.shp datasources.earthquakes | psql -d $DB -U $SUPERUSER $HOSTNAME
 
## Insert airport
#shp2pgsql -g geom -d -W UTF8 -s 4326 -I $DATADIR/amenities/airports/export_airports.shp datasources.airports | psql -d $DB -U $SUPERUSER $HOSTNAME


# GRANT RIGHTS TO itag USER
psql -U $SUPERUSER -d $DB $HOSTNAME << EOF
GRANT ALL ON SCHEMA datasources to $USER;
--GRANT SELECT on datasources.airports to $USER;
GRANT SELECT on datasources.cities to $USER;
GRANT SELECT on datasources.deptsfrance to $USER;
GRANT SELECT on datasources.commfrance to $USER;
GRANT SELECT on datasources.worldadm1level to $USER;
GRANT SELECT on datasources.continents to $USER;
GRANT SELECT on datasources.countries to $USER;
--GRANT SELECT on datasources.earthquakes to $USER;
GRANT SELECT on datasources.rivers to $USER;
GRANT SELECT on datasources.glaciers to $USER;
GRANT SELECT on datasources.plates to $USER;
GRANT SELECT on datasources.faults to $USER;
GRANT SELECT on datasources.volcanoes to $USER;
GRANT SELECT on datasources.landcover to $USER;
EOF
