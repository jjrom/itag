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
usage="## iTag French data sources installation\n\n  Usage $0 -D <data directory> [-d <database name> -s <database SUPERUSER> -u <database USER> -F -H <server HOSTNAME>]\n\n  -D : absolute path to the data directory containing french datasources\n  -s : database SUPERUSER (default "postgres")\n  -u : database USER (default "itag")\n  -d : database name (default "itag")\n  -H : postgres server hostname (default localhost)\n  -F : drop schema datasources first\n"
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
DROP SCHEMA IF EXISTS france CASCADE;
EOF
fi

psql -d $DB -U $SUPERUSER << EOF
CREATE SCHEMA france;
EOF

# ================== France =====================

## French departments
shp2pgsql -g geom -d -W UTF8 -s 4326 -I $DATADIR/france/deptsfrance.shp france.deptsfrance | psql -d $DB -U $SUPERUSER $HOSTNAME
psql -d $DB -U $SUPERUSER $HOSTNAME << EOF
UPDATE france.deptsfrance set nom_dept=initcap(nom_dept);
UPDATE france.deptsfrance set nom_dept=replace(nom_dept, '-De-', '-de-');
UPDATE france.deptsfrance set nom_dept=replace(nom_dept, ' De ', ' de ');
UPDATE france.deptsfrance set nom_dept=replace(nom_dept, 'D''', 'd''');
UPDATE france.deptsfrance set nom_dept=replace(nom_dept, '-Et-', '-et-');
UPDATE france.deptsfrance set nom_region=initcap(nom_region);
UPDATE france.deptsfrance set nom_region='Ile-de-France' WHERE nom_region='Ile-De-France';
UPDATE france.deptsfrance set nom_region='Nord-Pas-de-Calais' WHERE nom_region='Nord-Pas-De-Calais';
UPDATE france.deptsfrance set nom_region='Pays de la Loire' WHERE nom_region='Pays De La Loire';
UPDATE france.deptsfrance set nom_region='Provence-Alpes-Cote d''Azur' WHERE nom_region='Provence-Alpes-Cote D''Azur';
CREATE INDEX idx_deptsfrance_dept ON france.deptsfrance (nom_dept);
CREATE INDEX idx_deptsfrance_region ON france.deptsfrance (nom_region);
EOF

## French communes
shp2pgsql -d -W UTF8 -s 4326 -I $DATADIR/france/commfrance.shp france.commfrance | psql -d $DB -U $SUPERUSER $HOSTNAME
psql -d $DB -U $SUPERUSER $HOSTNAME << EOF
UPDATE france.commfrance set nom_comm=initcap(nom_comm);
UPDATE france.commfrance set nom_comm=replace(nom_comm, '-Sur-', '-sur-');
UPDATE france.commfrance set nom_comm=replace(nom_comm, '-De-', '-de-');
UPDATE france.commfrance set nom_comm=replace(nom_comm, ' De ', ' de ');
UPDATE france.commfrance set nom_comm=replace(nom_comm, 'D''', 'd''');
UPDATE france.commfrance set nom_comm=replace(nom_comm, '-Et-', '-et-');
UPDATE france.commfrance set nom_comm=replace(nom_comm, '-Le-', '-le-');
UPDATE france.commfrance set nom_comm=replace(nom_comm, '-La-', '-la-');
UPDATE france.commfrance set nom_comm=replace(nom_comm, '-Les-', '-les-');
CREATE INDEX idx_commfrance_comm ON france.commfrance (nom_comm);
EOF

## French arrondissements
shp2pgsql -d -W UTF8 -s 4326 -I $DATADIR/france/arrsfrance.shp france.arrsfrance | psql -d $DB -U $SUPERUSER $HOSTNAME
psql -d $DB -U $SUPERUSER $HOSTNAME << EOF
UPDATE france.arrsfrance set nom_chf=initcap(nom_chf);
CREATE INDEX idx_arrsfrance_arrs ON france.arrsfrance (nom_chf);
EOF

# GRANT RIGHTS TO itag USER
psql -U $SUPERUSER -d $DB $HOSTNAME << EOF
GRANT SELECT on france.deptsfrance to $USER;
GRANT SELECT on france.commfrance to $USER;
GRANT SELECT on france.arrsfrance to $USER;
EOF
