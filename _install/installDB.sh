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
RETURNS text AS $$ 
SELECT replace(lower(unaccent($1)),' ','-') 
$$ LANGUAGE sql;
EOF

# Rights
psql -U $SUPERUSER -d $DB -h $HOSTNAME << EOF
ALTER DATABASE $DB OWNER TO $USER;
REVOKE CREATE ON SCHEMA public FROM PUBLIC;
GRANT ALL ON geometry_columns to $USER;
GRANT ALL ON geography_columns to $USER;
GRANT SELECT on spatial_ref_sys to $USER;
EOF





