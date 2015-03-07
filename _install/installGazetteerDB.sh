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
CREATE INDEX idx_geoname_countryname ON gazetteer.geoname ((lower(unaccent(countryname))));

-- Text search
CREATE INDEX idx_geoname_name ON gazetteer.geoname (lower(unaccent(name)));
CREATE INDEX idx_geoname_like_name ON gazetteer.geoname (lower(unaccent(name)) varchar_pattern_ops);
CREATE INDEX idx_geoname_country ON gazetteer.geoname (country);

CREATE INDEX idx_alternatename_isolanguage ON gazetteer.alternatename (isolanguage);
DELETE FROM gazetteer.alternatename WHERE isolanguage IS NULL;
CREATE INDEX idx_alternatename_alternatename ON gazetteer.alternatename (lower(unaccent(alternatename)));
CREATE INDEX idx_alternatename_like_alternatename ON gazetteer.alternatename (lower(unaccent(alternatename)) varchar_pattern_ops);

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
