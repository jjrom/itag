# iTag

[![Code Climate](https://codeclimate.com/github/jjrom/itag/badges/gpa.svg)](https://codeclimate.com/github/jjrom/itag)

Semantic enhancement of Earth Observation data

iTag is a library to tag a footprint with the following information :
* political informations (i.e continents/countries/regions/states)
* geological information (i.e. faults/plates/glaciers/volcanoes)
* hydrological information (i.e. rivers)
* land cover (i.e. forest, water, urban, cultivated, herbaceous, desert, snow, flooded)
* population count

You can access an online instance [here] (http://mapshup.com/projects/itag) as a web service.

See [video capture of itag applied to Pleiades HR and Spot5 images database] (http://vimeo.com/51045597) and access trough [mapshup] (http://mapshup.com/projects/mapshup)

iTag is used by [RESTo - REstful Semantic search Tool for geOspatial] (http://github.com/jjrom/resto)

## Prerequesites

* PHP (v5.3+) command line
* PostgreSQL (v9.0+) with **unaccent** extension
* PostGIS (v2.0+)
* GDAL (v1.8+) with **python** support (for land cover preparation only)

Note: iTag could work with lower version of the specified requirements.
However there is no guaranty of success and unwanted result may occured !

## Installation

We suppose that : 

* $ITAG_HOME is the directory containing this file
* $ITAG_DATA is the directory containing the datasources file (see below)

### Get datasources

First create the $ITAG_DATA directory

    export ITAG_DATA=$ITAG_HOME/data
    mkdir $ITAG_DATA

#### General data

Retrieve coastlines from [Natural Earth](http://www.naturalearthdata.com/downloads/10m-cultural-vectors/10m-admin-0-countries/)
        
        cd $ITAG_DATA
        wget http://www.naturalearthdata.com/http//www.naturalearthdata.com/download/10m/physical/ne_10m_coastline.zip
        unzip ne_10m_coastline.zip

#### Political data

Retrieve countries from [Natural Earth](http://www.naturalearthdata.com/downloads/10m-cultural-vectors/10m-admin-0-countries/)
        
        cd $ITAG_DATA
        wget http://www.naturalearthdata.com/http//www.naturalearthdata.com/download/10m/cultural/ne_10m_admin_0_countries.zip
        unzip ne_10m_admin_0_countries.zip
        
Retrieve World Administrative Level 1 data from [Natural Earth](http://www.naturalearthdata.com/downloads/10m-cultural-vectors/10m-admin-1-states-provinces/)
        
        cd $ITAG_DATA
        wget http://www.naturalearthdata.com/http//www.naturalearthdata.com/download/10m/cultural/ne_10m_admin_1_states_provinces.zip
        unzip ne_10m_admin_1_states_provinces.zip
        
Retrieve toponyms from [geonames](http://geonames.org)

        cd $ITAG_DATA
        wget http://download.geonames.org/export/dump/allCountries.zip
        wget http://download.geonames.org/export/dump/alternateNames.zip
        unzip allCountries.zip
        unzip alternateNames.zip
        
#### Geological data

Retrieve geophysical data from [Mapping Tectonic Hot Spots](http://www.colorado.edu/geography/foote/maps/assign/hotspots/hotspots.html)

        cd $ITAG_DATA
        wget http://www.colorado.edu/geography/foote/maps/assign/hotspots/download/hotspots.zip
        unzip hotspots.zip

Retrieve glaciers from [Natural Earth](http://www.naturalearthdata.com/downloads/10m-physical-vectors/10m-glaciated-areas/)
        
        cd $ITAG_DATA
        wget http://www.naturalearthdata.com/http//www.naturalearthdata.com/download/10m/physical/ne_10m_glaciated_areas.zip
        unzip ne_10m_glaciated_areas.zip
        
#### Hydrological data

Retrieve rivers data from [Natural Earth](http://www.naturalearthdata.com/downloads/10m-physical-vectors/10m-rivers-lake-centerlines/)
        
        cd $ITAG_DATA
        wget http://www.naturalearthdata.com/http//www.naturalearthdata.com/download/10m/physical/ne_10m_rivers_lake_centerlines.zip
        unzip ne_10m_rivers_lake_centerlines.zip
        
### Install database

        # Note : "password" must be the same as 
        # the value of 'password' parameter in $ITAG_HOME/include/config.php
        
        $ITAG_HOME/_install/installDB.sh -F -d <path_to_postgis_directory> -p password

### Populate database
        
**Note** : If you are using Fedora, Red Hat Enterprise Linux, CentOS, scientific Linux, or one of the other
distros that enable SELinux by default you should run the following commands as root :

        setenforce 0

Run the following commands 

        # General datasources
        $ITAG_HOME/_install/installDatasources.sh -F -D $ITAG_DATA
        
        # Gazetteer
        $ITAG_HOME/_install/installGazetteerDB.sh -F -D $ITAG_DATA
        
        # Wikipedia
        # This step is optional and can only be performed if you have the geolocated wikipedia data (which probably you don't have :)
        # In case of, these are the steps to follow in order to install this database within iTag
        #
        # Put the geolocated wikipedia data in $ITAG_DATA/wikipedia directory, then run the command
        #
        $ITAG_HOME/_install/installWikipediaDB.sh -D $ITAG_DATA/wikipedia
    
### Install landcover database

Download the world glc2000 GeoTIFF file from ["Global Land Cover 2000" global product](http://bioval.jrc.ec.europa.eu/products/glc2000/products.php)

Then run the following :

        $ITAG_HOME/_install/computeLandCover.php -I path_to_glc2000_tif_image

**Note** : depending on your server performance, the landcover computation can take a long time (more than two hours)

### Install Gridded Population of the World database

Download the Gridded Population of the World from [SEDAC](http://sedac.ciesin.columbia.edu/data/set/gpw-v3-population-count-future-estimates/metadata)
Then run the following :

        $ITAG_HOME/_install/installGPW.php -f asciigridfile

**Note** : this take a loooooong time

### Deploy application

        $ITAG_HOME/_install/deploy.sh -s $ITAG_HOME -t $ITAG_TARGET

## Using iTag

We suppose that $ITAG_TARGET is accessible to http://localhost/itag/ in Apache.

To tag footprint on Toulouse with geological information and all cities with a pretty GeoJSON output, open this url within you browser
    
        http://localhost/itag/?taggers=Political&_pretty=true&footprint=POLYGON((1.350360%2043.532822,1.350360%2043.668522,1.515350%2043.668522,1.515350%2043.532822,1.350360%2043.532822))

Available parameters for Web service are :
* &taggers=Political,Geology,Hydrology,Landcover
* &pretty=true

You can check this [running instance] (http://mapshup.com/projects/itag/)

Examples :

    Tag footprint on Toulouse with political and geological information and with a pretty GeoJSON output
    
        http://mapshup.com/projects/itag/?taggers=Political,Geology&_pretty=true&footprint=POLYGON((1.350360%2043.532822,1.350360%2043.668522,1.515350%2043.668522,1.515350%2043.532822,1.350360%2043.532822))


    Tag footprint intersecting France, Italy and Switzerland with political information. Hierarchical result as pretty GeoJSON output
    
        http://mapshup.com/projects/itag/?taggers=Political&footprint=POLYGON((6.487426757812523%2045.76081241294796,6.487426757812523%2046.06798615804025,7.80578613281244%2046.06798615804025,7.80578613281244%2045.76081241294796,6.487426757812523%2045.76081241294796))
