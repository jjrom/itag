iTag
====

Automatically tag a geographical footprint against Land Cover and OSM data. Check running instance [here] (http://mapshup.info/itag).

Example : tag footprint on Toulouse with geophysical information and all cities with a pretty GeoJSON output
    
        http://mapshup/itag/?geophysical=true&countries=true&cities=all&output=pretty&footprint=POLYGON((1.350360%2043.532822,1.350360%2043.668522,1.515350%2043.668522,1.515350%2043.532822,1.350360%2043.532822))


Installation
============

We suppose that $ITAG_HOME is the directory containing this file

Prerequesites
-------------

* PHP (v5.3.6+) command line
* PostgreSQL (v8.4+)
* PostGIS (v1.5.1+)

Step by step
------------

1. Unzip data

        cd $ITAG_HOME/installation
        unzip data.zip

2. Install database

        # Note : "password" must be the same as 
        # the value of DB_PASSWORD key in $ITAG_HOME/config/config.php
        
        cd $ITAG_HOME/installation
        ./itagInstallDB.sh -F -d path_to_postgis_directory -p password

3. Populate database

        cd $ITAG_HOME/installation/
        ./itagPopulateDB.sh -D data

4. Configure

Edit $ITAG_HOME/config/config.php (just follow the comments !)

Note : "GLC2000_TIFF" constant should point to the ["Global Land Cover 2000" global product] (http://bioval.jrc.ec.europa.eu/products/glc2000/products.php)  in GeoTIFF format.

Using iTag
==========

From the command line
---------------------
    
        cd $ITAG_HOME

        # php itag.php -h
        #
        # USAGE : php itag.php [options] -f <footprint in WKT> (or -d <db connection info>)
        # OPTIONS:
        #    -o [type] : output (json|pretty|insert|copy|hstore) - Note : if -d is choosen only 'hstore', 'insert' and 'copy' are used 
        #    -c : Countries
        #    -C : Cities (main|all)
        #    -R : French Regions and departements
        #    -p : Compute population
        #    -g : Geophysical information (i.e. plates, volcanoes)
        #    -l : compute land cover (i.e. Thematical content - forest, water, urban, etc.
        #    -d : DB connection info - dbhost:dbname:dbuser:dbpassword:dbport:tableName:identifierColumnName:geometryColumnName
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
        # Tag footprints from table "products" of database "test"
        #
        # With the following parameters:
        #       - dbhost : localhost
        #       - dbname : test
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
        php itagTag.php -d localhost:test:postgres:postgres:5432:products:identifier:footprint -c -o hstore > /tmp/hstore.sql


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

You can check this [running instance] (http://mapshup/itag/) - (note : landcover is disabled on this server)

About data
==========

iTag can tag footprint with the following information :
* continents
* countries
* cities
* french regions and departments
* geophysical plates
* volcanoes
* land cover (i.e. forest, water, urban, cultivated, herbaceous, desert, snow, flooded)
* population count
