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
HOSTNAME=localhost
usage="## iTag database installation\n\n  Usage $0 -d <PostGIS directory> -p [itag user password] [-s <database SUPERUSER> -F -H <server HOSTNAME>]\n\n  -d : absolute path to the directory containing postgis.sql\n  -s : database SUPERUSER (default "postgres")\n  -F : WARNING - suppress existing itag database\n  -H : postgres server hostname (default localhost)"
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
if [ "$ROOTDIR" = "" ]
then
    echo -e $usage
    exit 1
fi
if [ "$DROPFIRST" = "YES" ]
then
    dropdb -U $SUPERUSER $DB -h $HOSTNAME
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
createdb $DB -U $SUPERUSER -h $HOSTNAME
createlang -U $SUPERUSER plpgsql $DB -h $HOSTNAME
psql -d $DB -U $SUPERUSER -f $postgis -h $HOSTNAME
psql -d $DB -U $SUPERUSER -f $projections -h $HOSTNAME

###### ADMIN ACCOUNT CREATION ######
psql -U $SUPERUSER -d template1 -h $HOSTNAME << EOF
CREATE USER $USER WITH PASSWORD '$PASSWORD' NOCREATEDB
EOF

# Create unaccent function
psql -U $SUPERUSER -d template1 -h $HOSTNAME << EOF
CREATE OR REPLACE FUNCTION unaccent(text)
RETURNS text
IMMUTABLE
STRICT
LANGUAGE SQL
AS $$
SELECT translate(
    $1,
    'ææ̆áàâãäåāăąạắằẵÀÁÂÃÄÅĀĂĄÆəèééêëēĕėęěệÈÉÊĒĔĖĘĚıìíîïìĩīĭịÌÍÎÏÌĨĪĬİḩòóồôõöōŏőợộÒÓÔÕÖŌŎŐØùúûüũūŭůưửÙÚÛÜŨŪŬŮČÇçćĉčċøơßýÿñşšŠŞŚŒŻŽžźżœðÝŸ¥µđÐĐÑŁţğġħňĠĦ',
    'aaaaaaaaaaaaaaaAAAAAAAAAAeeeeeeeeeeeeEEEEEEEEiiiiiiiiiiIIIIIIIIIhoooooooooooOOOOOOOOOuuuuuuuuuuUUUUUUUUCCcccccoosyynssSSSOZZzzzooYYYudDDNLtgghnGH'
);
$$;
EOF
# Rights
psql -U $SUPERUSER -d $DB -h $HOSTNAME << EOF
GRANT ALL ON geometry_columns to $USER;
GRANT ALL ON geography_columns to $USER;
GRANT SELECT on spatial_ref_sys to $USER;
EOF



