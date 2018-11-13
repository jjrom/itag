
# Set Data paths
EARTHQUAKES=$DATADIR/geology/earthquakes/MajorEarthquakes.shp
AIRPORTS=$DATADIR/amenities/airports/export_airports.shp

# ===== UNUSUED ======
## Insert glaciers
## DOWNLOAD THIS INSTEAD - http://nsidc.org/data/docs/noaa/g01130_glacier_inventory/#data_descriptions

## Major earthquakes since 1900
shp2pgsql -g geom -d -W UTF8 -s 4326 -I $EARTHQUAKES datasources.earthquakes | psql -d $DB -U $SUPERUSER $HOSTNAME
 
## Insert airport
shp2pgsql -g geom -d -W UTF8 -s 4326 -I $AIRPORTS datasources.airports | psql -d $DB -U $SUPERUSER $HOSTNAME

# GRANT RIGHTS TO itag USER
psql -U $SUPERUSER -d $DB $HOSTNAME << EOF
GRANT SELECT on datasources.airports to $USER;
GRANT SELECT on datasources.earthquakes to $USER;
EOF