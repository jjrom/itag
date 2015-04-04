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
usage="## iTag Gazetteer installation\n\n  Usage $0 -D <data directory> [-d <database name> -s <database SUPERUSER> -F]\n\n  -D : absolute path to the data directory containing geonames data (i.e. allCountries.zip, alternateNames.zip, countryInfo.txt, iso-languagecodes.txt).\n  -s : database SUPERUSER ($SUPERUSER)\n  -d : database name ($DB)\n  -F : drop schema gazetteer first\n"
while getopts "D:d:s:u:hF" options; do
    case $options in
        D ) DATADIR=`echo $OPTARG`;;
        d ) DB=`echo $OPTARG`;;
        s ) SUPERUSER=`echo $OPTARG`;;
        F ) DROPFIRST=YES;;
        h ) echo -e $usage;;
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
DROP SCHEMA IF EXISTS gazetteer CASCADE;
EOF
fi

# ======================================================
## Insert Cities from Geonames
# See https://github.com/colemanm/gazetteer/blob/master/docs/geonames_postgis_import.md
psql -d $DB -U $SUPERUSER << EOF
CREATE schema gazetteer;
CREATE TABLE gazetteer.geoname (
    geonameid int,
    name text,
    asciiname text,
    alternatenames text,
    latitude float,
    longitude float,
    fclass char(1),
    fcode text,
    country text,
    cc2 text,
    admin1 text,
    admin2 text,
    admin3 text,
    admin4 text,
    population bigint,
    elevation int,
    gtopo30 int,
    timezone text,
    moddate date
);
CREATE TABLE gazetteer.alternatename (
    alternatenameId int,
    geonameid int,
    isoLanguage text,
    alternateName text,
    isPreferredName boolean,
    isShortName boolean,
    isColloquial boolean,
    isHistoric boolean
 );
CREATE TABLE gazetteer.countryinfo (
    iso_alpha2 char(2),
    iso_alpha3 char(3),
    iso_numeric integer,
    fips_code text,
    name text,
    capital text,
    areainsqkm double precision,
    population integer,
    continent text,
    tld text,
    currencycode text,
    currencyname text,
    phone text,
    postalcode text,
    postalcoderegex text,
    languages text,
    geonameId int,
    neighbors text,
    equivfipscode text
);

-- Toponyms (english)
COPY gazetteer.geoname (geonameid,name,asciiname,alternatenames,latitude,longitude,fclass,fcode,country,cc2,admin1,admin2,admin3,admin4,population,elevation,gtopo30,timezone,moddate) FROM '$DATADIR/allCountries.txt' NULL AS '' ENCODING 'UTF8';

-- Toponyms (other languages) 
COPY gazetteer.alternatename (alternatenameid,geonameid,isolanguage,alternatename,ispreferredname,isshortname,iscolloquial,ishistoric) from '$DATADIR/alternateNames.txt' NULL AS '' ENCODING 'UTF8';

-- Countries
COPY gazetteer.countryinfo (iso_alpha2,iso_alpha3,iso_numeric,fips_code,name,capital,areainsqkm,population,continent,tld,currencycode,currencyname,phone,postalcode,postalcoderegex,languages,geonameid,neighbors,equivfipscode) from '$DATADIR/countryInfo.txt' NULL AS '' ENCODING 'UTF8';

-- We only need Populated place and administrative areas
CREATE INDEX idx_fclass_country ON gazetteer.geoname (fclass);
DELETE FROM gazetteer.geoname WHERE fclass NOT IN ('P', 'A');

-- PostGIS
SELECT AddGeometryColumn ('gazetteer','geoname','geom',4326,'POINT',2);
UPDATE gazetteer.geoname SET geom = ST_PointFromText('POINT(' || longitude || ' ' || latitude || ')', 4326);
CREATE INDEX idx_geoname_geom ON gazetteer.geoname USING gist(geom);

-- Add countryname to speed up iTag
ALTER TABLE gazetteer.geoname ADD COLUMN countryname VARCHAR(200);
UPDATE gazetteer.geoname SET countryname=(SELECT name FROM datasources.countries WHERE gazetteer.geoname.country = countries.iso_a2 LIMIT 1);
CREATE INDEX idx_geoname_countryname ON gazetteer.geoname (normalize(countryname));

-- Text search
CREATE INDEX idx_geoname_name ON gazetteer.geoname (normalize(name));
CREATE INDEX idx_geoname_like_name ON gazetteer.geoname (normalize(name) varchar_pattern_ops);
CREATE INDEX idx_geoname_country ON gazetteer.geoname (country);

CREATE INDEX idx_alternatename_isolanguage ON gazetteer.alternatename (isolanguage);
DELETE FROM gazetteer.alternatename WHERE isolanguage IS NULL;
CREATE INDEX idx_alternatename_alternatename ON gazetteer.alternatename (normalize(alternatename));
CREATE INDEX idx_alternatename_like_alternatename ON gazetteer.alternatename (normalize(alternatename) varchar_pattern_ops);

-- Constraints
ALTER TABLE ONLY gazetteer.alternatename ADD CONSTRAINT pk_alternatenameid PRIMARY KEY (alternatenameid);
ALTER TABLE ONLY gazetteer.geoname ADD CONSTRAINT pk_geonameid PRIMARY KEY (geonameid);
ALTER TABLE ONLY gazetteer.countryinfo ADD CONSTRAINT pk_iso_alpha2 PRIMARY KEY (iso_alpha2);

-- User rights
GRANT ALL ON SCHEMA gazetteer TO $USER;
GRANT SELECT ON gazetteer.geoname to $USER;
GRANT SELECT ON gazetteer.alternatename to $USER;
GRANT SELECT ON gazetteer.countryinfo to $USER;

EOF
