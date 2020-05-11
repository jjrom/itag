[![Maintainability](https://api.codeclimate.com/v1/badges/367107bc47a1b1b2d58e/maintainability)](https://codeclimate.com/github/jjrom/itag/maintainability)
# iTag
Semantic enhancement of Earth Observation data

iTag is a web service for the semantic enhancement of Earth Observation products, i.e. the tagging of products with additional information about the covered area, regarding for example geology, water bodies, land use, population, countries, administrative units or names of major settlements.

See [video capture of itag applied to Pleiades HR and Spot5 images database] (http://vimeo.com/51045597)

Test the [service online]( https://itag.snapplanet.io?_pretty=1&taggers=political&geometry=POLYGON((1.350360%2043.532822,1.350360%2043.668522,1.515350%2043.668522,1.515350%2043.532822,1.350360%2043.532822)) )

**For official support to itag, please contact [jeobrowser](https://mapshup.com)**

## API
The API is available [here](https://github.com/jjrom/itag/blob/master/docs/API.md) 

## Installation

### Prerequesites
iTag installation and deployment is based on docker-compose. It can run on any OS as long as the following software are up and running:

* bash
* psql client
* docker engine
* docker-compose

### Building and deploying
On first launch, run the following script:

(for production)

        ./deploy prod -f

(for development)

        ./deploy dev -f

*Note* The "-f" option is to force initial datasources installation. This is only needed once. For subsequents deploys, just discard this option

### Configuration
All configuration options are defined within the [config.env](https://github.com/jjrom/itag/blob/master/config.env) file.

For a local installation, you can leave it untouched. Otherwise, just make your own configuration. It's self explanatory (send me an email if not ;)

Note that each time you change the configuration file, you should undeploy then redeploy the service.

### External Database
By default, iTag will create a local postgres docker image. However, it can also uses an external PostgreSQL database (version 11+). 

To use an external database, set the config.env `ITAG_DATABASE_IS_EXTERNAL` parameter to `yes`.

The following extensions must be installed on the target database:
 * postgis
 * postgis_topology
 * unaccent

For instance suppose that the external database is "itag" :

        ITAG_DATABASE_NAME=itag

        PGPASSWORD=${ITAG_DATABASE_SUPERUSER_PASSWORD} createdb -X -v ON_ERROR_STOP=1 -h "${ITAG_DATABASE_HOST}" -p "${ITAG_DATABASE_PORT}" -U "${ITAG_DATABASE_SUPERUSER_NAME}" ${ITAG_DATABASE_NAME}

        PGPASSWORD=${ITAG_DATABASE_SUPERUSER_PASSWORD} psql -X -v ON_ERROR_STOP=1 -h "${ITAG_DATABASE_HOST}" -p "${ITAG_DATABASE_PORT}" -U "${ITAG_DATABASE_SUPERUSER_NAME}" -d "${ITAG_DATABASE_NAME}" -f ./build/itag-database/sql/00_itag_extensions.sql

Where ITAG_DATABASE_SUPERUSER_NAME is a database user with sufficient privileges to install extensions ("postgres" user for instance)

A normal PG user with `create schema` and `insert on spatial_ref_sys` rights is necessary in order for iTag to operate. To give a user the suitable rights, run the following sql commands:

        GRANT CREATE ON DATABASE ${ITAG_DATABASE_NAME} TO <dbuser>;
        GRANT INSERT ON TABLE spatial_ref_sys TO <dbuser>;

iTag tables, functions and triggers should be installed by running [scripts/installOnExternalDB.sh](https://github.com/jjrom/itag/blob/master/scripts/installExternalDB.sh):

        ./scripts/installExternalDB.sh -e <config file>
        
Note: The `insert on spatial_ref_sys` right can be revoked once the database is installed (first deploy) by running:
    
    REVOKE INSERT ON table spatial_ref_sys FROM <dbuser>; 


## Examples
*Note: The following example are based on the default service endpoint defined in (cf. [config.env](https://github.com/jjrom/itag/blob/master/config.env))*

Tag a geometry on Toulouse with "Political" information and all cities with a pretty GeoJSON output
```
curl "http://localhost:1212/?taggers=political&_pretty=true&geometry=POLYGON((1.350360%2043.532822,1.350360%2043.668522,1.515350%2043.668522,1.515350%2043.532822,1.350360%2043.532822))"
```

Tag geometry intersecting France, Italy and Switzerland with "Political,Geology,Hydrology,Physical" information.
```
curl "http://localhost:1212/?taggers=political,geology,hydrology,physical&geometry=POLYGON((6.487426757812523%2045.76081241294796,6.487426757812523%2046.06798615804025,7.80578613281244%2046.06798615804025,7.80578613281244%2045.76081241294796,6.487426757812523%2045.76081241294796))"
```

Tag geometry intersecting Chile for physical and geology info
```
curl "http://localhost:1212/?taggers=geology,physical&geometry=POLYGON((-74.39875248739082 -46.84194418662555,-72.14655522176582 -46.84194418662555,-72.14655522176582 -48.19957231818611,-74.39875248739082 -48.19957231818611,-74.39875248739082 -46.84194418662555))&_pretty=1"
```

## FAQ

### How do i undeploy the service ?

        ./undeploy

### How do i check the logs of a running itag container ?
Use docker-compose, e.g.:

        docker-compose logs -f

### How to i build locally the docker images
Use docker-compose:

        # This will build the application server image (i.e. jjrom/itag)
        docker-compose -f docker-compose.yml build

        # This will build the database server image (i.e. jjrom/itag-database)
        docker-compose -f docker-compose-itagdb.yml build


### Where is the configuration of a running itag container ?
When deployed, all configurations file are stored under .run/config directory

### Why do i have empty result for landcover and population ?
The Landcover and Population data are available separately from this repository. If you need this data, send an email to jerome.gasperi@gmail.com

