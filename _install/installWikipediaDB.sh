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
