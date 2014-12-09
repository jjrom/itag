iTag
====

Semantic enhancement of Earth Observation data

iTag is a library to tag a footprint with the following information :
* continents
* countries
* cities
* regions and states
* geophysical plates
* volcanoes
* land cover (i.e. forest, water, urban, cultivated, herbaceous, desert, snow, flooded)
* population count

You can access an online instance [here] (http://mapshup.info/itag) as a web service.

See [video capture of itag applied to Pleiades HR and Spot5 images database] (http://vimeo.com/51045597) and access trough [mapshup] (http://mapshup.info)

iTag is used by [RESTo - REstful Semantic search Tool for geOspatial] (http://github.com/jjrom/resto)

Installation
============

We suppose that $ITAG_HOME is the directory containing this file.

Prerequesites
-------------

* PHP (v5.3+) command line
* PostgreSQL (v9.0+) with **unaccent** extension
* PostGIS (v1.5.1+)
* GDAL (v1.8+) with **python** support (for land cover preparation only)

Note: iTag could work with lower version of the specified requirements.
However there is no guaranty of success and unwanted result may occured !

Configure postgresql
--------------------

Edit the PostreSQL postgresql.conf and be sure that postgres accept tcp_ip connection.

        # Uncomment these two lines within postgesql.conf
        listen_addresses = 'localhost'
        port = 5432

Step by step
------------

1. Get data
        
        # Note : $ITAG_HOME **must be** an absolute path (not relative !)
        git clone https://github.com/jjrom/itag-data.git $ITAG_DATA
        cd $ITAG_HOME/_install
        unzip $ITAG_DATA/data.zip

2. Install database

        # Note : "password" must be the same as 
        # the value of 'password' parameter in $ITAG_HOME/include/config.php
        
        $ITAG_HOME/_install/installDB.sh -F -d <path_to_postgis_directory> -p password

3. Populate database
        
        # 
        # Note : Read this if you are using Fedora, Red Hat Enterprise Linux, CentOS,
        # Scientific Linux, or one of the other distros that enable SELinux by default.
        #
        # SELinux policies for PostgreSQL do not permit the server to read files outside
        # the PostgreSQL data directory, or the file was created by a service covered by
        # a targeted policy so it has a label that PostgreSQL isn't allowed to read from.
        #
        # To make the following scripts work run the following command as root
        #
        #   setenforce 0
        # 
        # Then after a successful execution relaunch the command
        #
        #   setenforce 1
        #
        # General datasources
        $ITAG_HOME/_install/installDataSources.sh -F -D $ITAG_HOME/_install/data
        
        # Geonames
        # First you need to download geonames data in $GEONAMES_DIR directory
        export GEONAMES_DIR=/a/temporary/directory
        cd $GEONAMES_DIR
        wget http://download.geonames.org/export/dump/allCountries.zip
        wget http://download.geonames.org/export/dump/alternateNames.zip
        wget http://download.geonames.org/export/dump/countryInfo.txt
        wget http://download.geonames.org/export/dump/iso-languagecodes.txt
        unzip allCountries.zip
        unzip alternateNames.zip
        # Remove unwanted comment from countryInfo.txt
        grep -v "^#" countryInfo.txt > tmp.txt
        mv tmp.txt countryInfo.txt
        $ITAG_HOME/_install/installGazetter.sh -F -D $GEONAMES_DIR

        # Wikipedia
        # This step is optional and can only be performed if you have the geolocated wikipedia data (which probably you don't have :)
        # In case of, these are the steps to follow in order to install this database within iTag
        #
        # Put the geolocated wikipedia data in $GEONAMES_DIR/wikipedia directory, then run the command
        #
        # $ITAG_HOME/_install/installWikipediaDB.sh -D $GEONAMES_DIR/wikipedia
    
4. Precompute landcover

        Go to ["Global Land Cover 2000" global product] (http://bioval.jrc.ec.europa.eu/products/glc2000/products.php) and download glc2000 GeoTIFF file

        $ITAG_HOME/_install/computeLandCover.php -I path_to_glc2000_tif_image

5. Deploy application

        $ITAG_HOME/_install/deploy.sh -s $ITAG_HOME> -t $ITAG_TARGET


Note : depending on your server performance, the landcover computation can take a long time (more than two hours)

Using iTag
==========

We suppose that $ITAG_TARGET is accessible to http://localhost/itag/ in Apache.

To tag footprint on Toulouse with geophysical information and all cities with a pretty GeoJSON output, open this url within you browser
    
        http://localhost/itag/?geophysical=true&countries=true&cities=all&output=pretty&footprint=POLYGON((1.350360%2043.532822,1.350360%2043.668522,1.515350%2043.668522,1.515350%2043.532822,1.350360%2043.532822))

Available parameters for Web service are :
* &continents=true
* &countries=true
* &cities=main (or &cities=all)
* &geophysical=true
* &regions=true
* &landcover=true
* &french=true
* &hierarchical=true
* &ordered=true
* &pretty=true

You can check this [running instance] (http://mapshup.info/itag/)


Examples :

    Tag footprint on Toulouse with geophysical information and all cities with a pretty GeoJSON output
    
        http://mapshup.info/itag/?geophysical=true&countries=true&cities=all&output=pretty&footprint=POLYGON((1.350360%2043.532822,1.350360%2043.668522,1.515350%2043.668522,1.515350%2043.532822,1.350360%2043.532822))


    Tag footprint intersecting France, Italy and Switzerland with cities, regions and states. Hierarchical result as pretty GeoJSON output
    
        http://mapshup.info/itag/?regions=true&hierarchical=true&ordered=true&countries=true&cities=all&output=pretty&footprint=POLYGON((6.487426757812523%2045.76081241294796,6.487426757812523%2046.06798615804025,7.80578613281244%2046.06798615804025,7.80578613281244%2045.76081241294796,6.487426757812523%2045.76081241294796))
