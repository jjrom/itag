#!/bin/bash
#
# iTag
#
#  Automatically tag a geographical footprint against every kind of things
# (i.e. Land Cover, OSM data, population count, etc.)
#
# jerome[dot]gasperi[at]gmail[dot]com
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
usage="## iTag database installation\n\n  Usage $0 -d <PostGIS directory> -p [itag user password] [-s <database SUPERUSER> -F]\n\n  -d : absolute path to the directory containing postgis.sql\n  -s : dabase SUPERUSER (default "postgres")\n  -F : WARNING - suppress existing itag database\n"
while getopts "d:s:p:hF" options; do
    case $options in
        d ) ROOTDIR=`echo $OPTARG`;;
        s ) SUPERUSER=`echo $OPTARG`;;
        p ) PASSWORD=`echo $OPTARG`;;
        F ) DROPFIRST=YES;;
        h ) echo -e $usage;;
        \? ) echo -e $usage
            exit 1;;
        * ) echo -e $usage
            exit 1;;
    esac
done
if [ "$ROOTDIR" = "" ]
then
    echo -e $usage
    exit 1
fi
if [ "$DROPFIRST" = "YES" ]
then
    dropdb -U $SUPERUSER $DB
fi
if [ "$PASSWORD" = "" ]
then
    echo -e $usage
    exit 1
fi

# Example : $ROOTDIR = /usr/local/pgsql/share/contrib/postgis-1.5/
postgis=`echo $ROOTDIR/postgis.sql`
projections=`echo $ROOTDIR/spatial_ref_sys.sql`

# Make db POSTGIS compliant
createdb $DB -U $SUPERUSER
createlang -U $SUPERUSER plpgsql $DB
psql -d $DB -U $SUPERUSER -f $postgis
psql -d $DB -U $SUPERUSER -f $projections

###### ADMIN ACCOUNT CREATION ######
psql -U $SUPERUSER -d template1 << EOF
CREATE USER $USER WITH PASSWORD '$PASSWORD' NOCREATEDB
EOF

# Rights
psql -U $SUPERUSER -d $DB << EOF
GRANT ALL ON geometry_columns to $USER;
GRANT ALL ON geography_columns to $USER;
GRANT SELECT on spatial_ref_sys to $USER;
GRANT SELECT on airports to $USER;
GRANT SELECT on cities to $USER;
GRANT SELECT on geoname to $USER;
GRANT SELECT on deptsfrance to $USER;
GRANT SELECT on continents to $USER;
GRANT SELECT on countries to $USER;
GRANT SELECT on earthquakes to $USER;
GRANT SELECT on glaciers to $USER;
GRANT SELECT on plates to $USER;
GRANT SELECT on faults to $USER;
GRANT SELECT on volcanoes to $USER;
EOF



