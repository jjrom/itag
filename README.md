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
Run the following script:

        ./deploy.sh -e config.env

*On first deployment, the script retrieves all the datasources from internet and populates the database. It can take some time.*

### Configuration
All configuration options are defined within the [config.env](https://github.com/jjrom/itag/blob/master/config.env) file.

For a local installation, you can leave it untouched. Otherwise, just make your own configuration. It's self explanatory (send me an email if not ;)

Note that each time you change the configuration file, you should undeploy then redeploy the service.

## Examples
*Note: The following example are based on the default service endpoint defined in (cf. [config.env](https://github.com/jjrom/itag/blob/master/config.env))*

Tag a geometry on Toulouse with "Political" information and all cities with a pretty GeoJSON output
```
curl "http://localhost:11211/?taggers=political&_pretty=true&geometry=POLYGON((1.350360%2043.532822,1.350360%2043.668522,1.515350%2043.668522,1.515350%2043.532822,1.350360%2043.532822))"
```

Tag geometry intersecting France, Italy and Switzerland with "Political,Geology,Hydrology,Physical" information.
```
curl "http://localhost:11211/?taggers=political,geology,hydrology,physical&geometry=POLYGON((6.487426757812523%2045.76081241294796,6.487426757812523%2046.06798615804025,7.80578613281244%2046.06798615804025,7.80578613281244%2045.76081241294796,6.487426757812523%2045.76081241294796))"
```

Tag geometry intersecting Chile for physical and geology info
```
curl "http://localhost:11211/?taggers=geology,physical&geometry=POLYGON((-74.39875248739082 -46.84194418662555,-72.14655522176582 -46.84194418662555,-72.14655522176582 -48.19957231818611,-74.39875248739082 -48.19957231818611,-74.39875248739082 -46.84194418662555))&_pretty=1"
```

## FAQ

### How do i undeploy the service ?

        ./undeploy.sh -s

### How do i check the logs of a running itag container ?
Use docker-compose, e.g.:

        docker-compose logs -f

### Where is the configuration of a running itag container ?
When deployed, all configurations file are stored under .run/config directory

### Why do i have empty result for landcover and population ?
The Landcover and Population data are available separately from this repository. If you need this data, send an email to jerome.gasperi@gmail.com

