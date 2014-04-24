iTag
====

Automatically tag a geographical footprint against location, land cover, etc.

iTag can tag a footprint with the following information :
* continents
* countries
* cities
* french regions and departments
* geophysical plates
* volcanoes
* land cover (i.e. forest, water, urban, cultivated, herbaceous, desert, snow, flooded)
* population count

You can access an online instance [here] (http://mapshup.info/itag) as a web service.

See [video capture of itag applied to Pleiades HR and Spot5 images database] (http://vimeo.com/51045597) and access trough [mapshup] (http://mapshup.info)

iTag is extensively used by [RESTo - REstful Semantic search Tool for geOspatial] (http://github.com/jjrom/resto)

Installation
============

We suppose that $ITAG_HOME is the directory containing this file.

Prerequesites
-------------

* PHP (v5.3+) command line
* PostgreSQL (v9.0+)
* PostGIS (v1.5.1+)
* GDAL (v1.8+) with python support (for land cover preparation)

Note: iTag could work with lower version of the specified requirements.
However there is no guaranty of success and unwanted result may occured !

Configure postgresql
--------------------

Edit the PostreSQL postgresql.conf and be sure that postgres accept tcp_ip connection.

        # Uncomment these two lines within postgesql.conf
        listen_adresses = 'localhost'
        port = 5432

Step by step
------------

1. Unzip data
        
        # Note : $ITAG_HOME **must be** an absolute path (not relative !)
        cd $ITAG_HOME/installation
        unzip data.zip

2. Install database

        # Note : "password" must be the same as 
        # the value of DB_PASSWORD key in $ITAG_HOME/config/config.php
        
        cd $ITAG_HOME/installation
        ./itagInstallDB.sh -F -d <path_to_postgis_directory> -p password

3. Populate database
        
        # 
        # Note : Read this if you are using Fedora, Red Hat Enterprise Linux, CentOS,
        # Scientific Linux, or one of the other distros that enable SELinux by default.
        #
        # SELinux policies for PostgreSQL do not permit the server to read files outside
        # the PostgreSQL data directory, or the file was created by a service covered by
        # a targeted policy so it has a label that PostgreSQL isn't allowed to read from.
        #
        # To make the itagPopulateDB.sh, run the following command as root
        #
        #   setenforce 0
        # 
        # Then after a successful itagPopulateDB.sh relaunch the command
        #
        #   setenforce 1
        #
        cd $ITAG_HOME/installation/
        ./itagPopulateDB.sh -D data

4. Download Global Land Cover 2000

Go to ["Global Land Cover 2000" global product] (http://bioval.jrc.ec.europa.eu/products/glc2000/products.php) and download glc2000 GeoTIFF file.

5. Configure

Edit $ITAG_HOME/config/config.php (just follow the comments !)

5. Precompute landcover

        cd $ITAG_HOME/scripts/
        ./prepareLandCover.php

Note : depending on your server performance, the landcover computation can take a long time (more than two hours)


Using iTag
==========

From the command line
---------------------
    
        cd $ITAG_HOME

        php itag.php -h

        #
        #   USAGE : php itag.php [options] -f <footprint in WKT> (or -d <db connection info>)
        #   OPTIONS:
        #       -o [type] : output (json|pretty|insert|copy|hstore) - Note : if -d is choosen only 'hstore', 'insert' and 'copy' are used 
        #       -H : display hierarchical continents/countries/regions/cities (otherwise keywords are "flat") 
        #       -O : compute and order result by area of intersection
        #       -c : Countries
        #       -x : Continents
        #       -C : Cities (main|all)
        #       -R : French Regions and departements
        #       -p : Population
        #       -g : Geophysical information (i.e. plates, volcanoes)
        #       -l : Land Cover (i.e. Thematical content - forest, water, urban, etc.
        #       -d : DB connection info - dbhost:dbname:dbschema:dbuser:dbpassword:dbport:tableName:identifierColumnName:geometryColumnName
        #

        #
        # Tag footprint on Sicilia with countries, all cities and geophysical information
        #
        php itag.php -cg -C all -f "POLYGON((13.304225 37.47162,13.304225 38.433184,17.259303 38.433184,17.259303 37.47162,13.304225 37.47162))"
        
        #
        # Tag footprint on Toulouse with French region, land cover and population
        #
        php itag.php -Rgp -f "POLYGON((1.350360 43.532822,1.350360 43.668522,1.515350 43.668522,1.515350 43.532822,1.350360 43.532822))"

        #
        # Hierarchized Tag footprint intersecting France, Italy and Switzerland unordered and ordered 
        #
        php itag.php -c -H -f "POLYGON((6.487426757812523 45.76081241294796,6.487426757812523 46.06798615804025,7.80578613281244 46.06798615804025,7.80578613281244 45.76081241294796, 6.487426757812523 45.76081241294796))"
        php itag.php -c -H -O -f "POLYGON((6.487426757812523 45.76081241294796,6.487426757812523 46.06798615804025,7.80578613281244 46.06798615804025,7.80578613281244 45.76081241294796, 6.487426757812523 45.76081241294796))"


        #
        # Tag footprints from table "products" of database "test"
        #
        # With the following parameters:
        #       - dbhost : localhost
        #       - dbname : test
        #       - dbschema : public
        #       - dbuser : postgres
        #       - dbpassword : postgres
        #       - dbport : 5432
        #       - tableName : products
        #       - identifierColumnName : identifier
        #       - geometryColumnName : footprint
        #
        #
        # Note : Output is set to hstore and redirect to /tmp/hstore.sql
        #
        php itag.php -d localhost:test:public:postgres:postgres:5432:products:identifier:footprint -c -o hstore > /tmp/hstore.sql


From Web service
----------------
        
We suppose that $ITAG_HOME is accessible to http://localhost/itag/ in Apache.

To tag footprint on Toulouse with geophysical information and all cities with a pretty GeoJSON output, open this url within you browser
    
        http://localhost/itag/?geophysical=true&countries=true&cities=all&output=pretty&footprint=POLYGON((1.350360%2043.532822,1.350360%2043.668522,1.515350%2043.668522,1.515350%2043.532822,1.350360%2043.532822))

Available parameters for Web service are :
* &countries=true
* &cities=main (or &cities=all)
* &population=true
* &geophysical=true
* &regions=true
* &landcover=true

You can check this [running instance] (http://mapshup.info/itag/) - (note : landcover is disabled on this server)


Examples :

    Tag footprint on Toulouse with geophysical information and all cities with a pretty GeoJSON output
    
        http://mapshup.info/itag/?geophysical=true&countries=true&cities=all&output=pretty&footprint=POLYGON((1.350360%2043.532822,1.350360%2043.668522,1.515350%2043.668522,1.515350%2043.532822,1.350360%2043.532822))


    Tag footprint intersecting France, Italy and Switzerland with cities, France regions and France departments. Hierarchical result as pretty GeoJSON output
    
        http://mapshup.info/itag/?hierarchical=true&ordered=true&countries=true&cities=all&output=pretty&footprint=POLYGON((6.487426757812523%2045.76081241294796,6.487426757812523%2046.06798615804025,7.80578613281244%2046.06798615804025,7.80578613281244%2045.76081241294796,6.487426757812523%2045.76081241294796))
