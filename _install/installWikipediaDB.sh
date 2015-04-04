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
usage="## iTag geolocated wikipedia installation (part of Gazetteer module)\n\n  Usage $0 -D <data directory> [-d <database name> -s <database SUPERUSER>]\n\n  -D : absolute path to the data directory containing geolocated wikipedia data (i.e. ds.dmp, wikipedia.dmp, wk.dmp).\n  -s : database SUPERUSER ($SUPERUSER)\n  -d : database name ($DB)\n"
while getopts "D:d:s:u:h" options; do
    case $options in
        D ) DATADIR=`echo $OPTARG`;;
        d ) DB=`echo $OPTARG`;;
        s ) SUPERUSER=`echo $OPTARG`;;
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

# ======================================================
## Copy data
psql -d $DB -U $SUPERUSER -f $DATADIR/ds.dmp
psql -d $DB -U $SUPERUSER -f $DATADIR/wikipedia.dmp
psql -d $DB -U $SUPERUSER -f $DATADIR/wk.dmp

## Set indices and user rights
psql -d $DB -U $SUPERUSER << EOF
-- Add and Fill PostGIS geometry column to wikipedia table
SELECT AddGeometryColumn ('gazetteer','wikipedia','geom',4326,'POINT',2);
UPDATE gazetteer.wikipedia SET geom = ST_GeomFromText('POINT('|| longlat[0] || ' ' || longlat[1] || ')', 4326);
-- Indexes
CREATE INDEX idx_wk_lang ON gazetteer.wk (lang);
CREATE INDEX idx_wk_id ON gazetteer.wk (id);
CREATE INDEX idx_wikipedia_geom ON gazetteer.wikipedia USING GIST(geom);
CREATE INDEX idx_geonames_ds_nameorid ON gazetteer.geoname_ds (nameorid);
-- User rights
GRANT SELECT ON gazetteer.wikipedia TO $USER;
GRANT SELECT ON gazetteer.wk TO $USER;
EOF
