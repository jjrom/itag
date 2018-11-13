# iTag

[![Code Climate](https://codeclimate.com/github/jjrom/itag/badges/gpa.svg)](https://codeclimate.com/github/jjrom/itag)
[![Average time to resolve an issue](http://isitmaintained.com/badge/resolution/jjrom/itag.svg)](http://isitmaintained.com/project/jjrom/itag "Average time to resolve an issue")
[![Percentage of issues still open](http://isitmaintained.com/badge/open/jjrom/itag.svg)](http://isitmaintained.com/project/jjrom/itag "Percentage of issues still open")

Semantic enhancement of Earth Observation data

iTag is a library to tag a footprint with the following information :
* political informations (i.e continents/countries/regions/states)
* geological information (i.e. faults/plates/glaciers/volcanoes)
* hydrological information (i.e. rivers)
* land cover (i.e. forest, water, urban, cultivated, herbaceous, desert, snow, flooded)
* population count

You can access an online instance [here] (http://mapshup.com/projects/itag) as a web service.

See [video capture of itag applied to Pleiades HR and Spot5 images database] (http://vimeo.com/51045597) and access trough [mapshup] (http://mapshup.com/projects/mapshup)

iTag is used by [resto - REstful Semantic search Tool for geOspatial] (http://github.com/jjrom/resto)

## Prerequesites

* PHP (v5.3+) command line
* PostgreSQL (v9.0+) with **unaccent** extension
* PostGIS (v2.0+)
* GDAL (v1.8+) with **python** support (for land cover preparation only)

Note: iTag could work with lower version of the specified requirements.
However there is no guaranty of success and unwanted result may occured !

## Installation

Create a home directory for the installation files and clone the itag GIT repository:

```
export ITAG_HOME=/opt/src/itag
cd $ITAG_HOME
git clone https://github.com/jjrom/itag.git
```

Install the database schema
```
$ITAG_HOME/_install/installDB.sh -F -H localhost -p itag
```

### Get data sources

Create a directory for data sources and make it the current directory:
```
export ITAG_DATA=$ITAG_HOME/data
mkdir $ITAG_DATA
cd $ITAG_DATA
```
Execute the following commands to download and uncompress the data sources:
```
# Retrieve coastlines from [Natural Earth]
wget http://www.naturalearthdata.com/http//www.naturalearthdata.com/download/10m/physical/ne_10m_coastline.zip
unzip ne_10m_coastline.zip
[ $? -eq 0 ] && rm ne_10m_coastline.zip

# Retrieve World Administrative Level 1 data from [Natural Earth]
wget http://www.naturalearthdata.com/http//www.naturalearthdata.com/download/10m/cultural/ne_10m_admin_0_countries.zip
unzip ne_10m_admin_0_countries.zip
[ $? -eq 0 ] && rm ne_10m_admin_0_countries.zip

# Retrieve World Administrative Level 1 data from [Natural Earth]
wget http://www.naturalearthdata.com/http//www.naturalearthdata.com/download/10m/cultural/ne_10m_admin_1_states_provinces.zip
unzip ne_10m_admin_1_states_provinces.zip
[ $? -eq 0 ] && rm ne_10m_admin_1_states_provinces.zip

# Retrieve toponyms from [geonames](http://geonames.org)
wget http://download.geonames.org/export/dump/allCountries.zip
wget http://download.geonames.org/export/dump/alternateNames.zip
unzip allCountries.zip
unzip alternateNames.zip
[ $? -eq 0 ] && rm allCountries.zip
[ $? -eq 0 ] && rm alternateNames.zip

# Retrieve geophysical data from [Mapping Tectonic Hot Spots]
wget http://www.colorado.edu/geography/foote/maps/assign/hotspots/download/hotspots.zip
unzip hotspots.zip
[ $? -eq 0 ] && rm hotspots.zip

# Retrieve glaciers from [Natural Earth]
wget http://www.naturalearthdata.com/http//www.naturalearthdata.com/download/10m/physical/ne_10m_glaciated_areas.zip
unzip ne_10m_glaciated_areas.zip
[ $? -eq 0 ] && rm ne_10m_glaciated_areas.zip

# Retrieve rivers data from [Natural Earth]
wget http://www.naturalearthdata.com/http//www.naturalearthdata.com/download/10m/physical/ne_10m_rivers_lake_centerlines.zip
unzip ne_10m_rivers_lake_centerlines.zip
[ $? -eq 0 ] && rm ne_10m_rivers_lake_centerlines.zip

# Retrieve other data (i.e. marine areas, mountains area, etc.) from [Natural Earth]
wget http://www.naturalearthdata.com/http//www.naturalearthdata.com/download/10m/physical/ne_10m_geography_marine_polys.zip
unzip ne_10m_geography_marine_polys.zip
[ $? -eq 0 ] && rm ne_10m_geography_marine_polys.zip
```

Install the database schema by executing the following commands (gazetteer creation could take several hours):
```
$ITAG_HOME/_install/installDatasources.sh -F -D $ITAG_DATA
$ITAG_HOME/_install/installGazetteerDB.sh -F -D $ITAG_DATA
```

### Install landcover database

Install one of GlobCover2009 or GLC2000. GlobCover2009 is more recent and 300 meters resolution. GLC2000 is older and 1 kilometer resolution

**Note** Process GlobCover2009 would take more time and take more disk space

#### GLC2000
Download the world glc2000 GeoTIFF file from ["Global Land Cover 2000" - global product](http://forobs.jrc.ec.europa.eu/products/glc2000/products.php)

Then run the following :

**Warning** : this PHP script gets postgres superuser password as command line argument, change password after iTag installation !

        $ITAG_HOME/_install/computeLandCover.php -p postgres_user_pass -I path_to_glc2000_tif_image

**Tip** : maybe you have to indicate the location of gdal tools (check -T and -P swich)

**Note** : depending on your server performance, the landcover computation can take a long time (more than two hours)

#### GlobCover2009
Download the world GlobCover 2009 GeoTIFF file from ["European Space Agency GlobCover Portal"](http://due.esrin.esa.int/files/GLOBCOVER_L4_200901_200912_V2.3.color.tif)

Then run the following :

**Warning** : this PHP script gets postgres superuser password as command line argument, change password after iTag installation !

        $ITAG_HOME/_install/computeGlobCover2009.php -p postgres_user_pass -f GLOBCOVER_L4_200901_200912_V2.3.color.tif

### Install Gridded Population of the World database

Download most recent "Population Count Grid Future" (or "Population Count Grid") product of the whole World from [SEDAC](http://sedac.ciesin.columbia.edu/data/set/gpw-v3-population-count-future-estimates/data-download) in ASCII Grid format (*.ascii or *.asc). All four resolutions (1°, 1/2°, 1/4° and 2.5′) are needed!

Then run the following :

**Warning** : this PHP script gets postgres superuser password as command line argument, change password after iTag installation !

        $ITAG_HOME/_install/installGPW.php -p postgres_user_pass -f glp15ag60.asc
        $ITAG_HOME/_install/installGPW.php -p postgres_user_pass -f glp15ag30.asc
        $ITAG_HOME/_install/installGPW.php -p postgres_user_pass -f glp15ag15.asc
        $ITAG_HOME/_install/installGPW.php -p postgres_user_pass -f glp15ag.asc

**Note** : this take a loooooong time (more than four hours)

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
