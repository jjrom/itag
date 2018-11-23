---
title: iTag - Semantic enhancement of Earth Observation data
toc_footers: []
includes: []
search: false
highlight_theme: darkula
headingLevel: 2

---

<h1 id="itag-semantic-enhancement-of-earth-observation-data">iTag - Semantic enhancement of Earth Observation data v5.0</h1>

> Scroll down for example requests and responses.

iTag is a web service for the semantic enhancement of Earth Observation products, i.e. the tagging of products with additional information about the covered area, regarding for example geology, water bodies, land use, population, countries, administrative units or names of major settlements.

Base URLs:

* <a href="http://localhost:11211/">http://localhost:11211/</a>

Email: <a href="mailto:jerome.gasperi@gmail.com">Support</a> 

## Tag a geometry

<a id="opIdiTagLauncher::tag"></a>


`GET /`

Returns a list of features intersecting input geometry

<h3 id="tag-a-geometry-parameters">Parameters</h3>

|Parameter|In|Type|Required|Description|
|---|---|---|---|---|
|geometry|path|string|true|Input geometry as a POLYGON WKT|
|taggers|path|enum|true|List of tagger applied. You can specify multiple taggers comma separated|
|timestamp|path|string|false|Input timestamp (to compute season based on geometry location) - format ISO 8601 YYYY-MM-DDTHH:MM:SS|
|_pretty|path|string|false|True to return pretty print response|
|_wkt|path|string|false|True to return intersected features geometries as WKT|

#### Detailed descriptions

**taggers**: List of tagger applied. You can specify multiple taggers comma separated
* geology : Return intersected geological features i.e. faults, glaciers, plates and volcanoes
* hydrology : Return intersected hydrological features i.e. Lakes and rivers
* landcover : Compute landcover (based on Global LandCover 2000)
* physical : Return physical intersected features i.e. marine regions 
* political : Return political intersected features i.e. continents, countries, regions and states
* population : Compute population count and density

#### Enumerated Values

|Parameter|Value|
|---|---|
|taggers|geology|
|taggers|hydrology|
|taggers|landcover|
|taggers|physical|
|taggers|political|
|taggers|population|

> Example responses

> 200 Response

```json
{
  "geometry": "POLYGON((6.487426757812523 45.76081241294796,6.487426757812523 46.06798615804025,7.80578613281244 46.06798615804025,7.80578613281244 45.76081241294796,6.487426757812523 45.76081241294796))",
  "timestamp": "2018-01-13",
  "area_unit": "km2",
  "cover_unit": "%",
  "content": {
    "area": 3483.53511,
    "keywords": [
      "location:northern",
      "season:winter"
    ],
    "political": {
      "continents": [
        {
          "name": "Europe",
          "id": "continent:europe:6255148",
          "countries": [
            {
              "name": "Italy",
              "id": "country:italy:3175395",
              "pcover": 37.02,
              "gcover": 0.42,
              "regions": [
                {
                  "name": "Valle d'Aosta",
                  "id": "region:valledaosta:3164857",
                  "pcover": 37.2,
                  "gcover": 39.19,
                  "states": [
                    {
                      "name": "Aoste",
                      "id": "state:aoste:3182996",
                      "pcover": 37.02,
                      "gcover": 39.13
                    }
                  ]
                }
              ]
            },
            {
              "name": "France",
              "id": "country:france:3017382",
              "pcover": 32.9,
              "gcover": 0.18,
              "regions": [
                {
                  "name": "Rh\\u00f4ne-Alpes",
                  "id": "region:rhonealpes:11071625",
                  "pcover": 32.94,
                  "gcover": 2.56,
                  "states": [
                    {
                      "name": "Haute-Savoie",
                      "id": "state:hautesavoie:3013736",
                      "pcover": 29.39,
                      "gcover": 21.86
                    }
                  ]
                },
                {
                  "name": "Rh\\u00f4ne-Alpes",
                  "id": "region:rhonealpes:11071625",
                  "pcover": 32.94,
                  "gcover": 2.56,
                  "states": [
                    {
                      "name": "Savoie",
                      "id": "state:savoie:2975517",
                      "pcover": 3.51,
                      "gcover": 1.98
                    }
                  ]
                }
              ]
            },
            {
              "name": "Switzerland",
              "id": "country:switzerland:2658434",
              "pcover": 30.04,
              "gcover": 2.53,
              "regions": [
                {
                  "states": [
                    {
                      "name": "Valais",
                      "id": "state:valais:2658205",
                      "pcover": 30.04,
                      "gcover": 19.79
                    }
                  ]
                }
              ]
            }
          ]
        }
      ]
    },
    "geology": {
      "glaciers": [
        {
          "name": "La Vall\\u00e9e Blanche"
        },
        {
          "name": "Zmuttgletscher",
          "geometry": "POLYGON((7.74563268097009 46.0679861580402,7.80578613281244 45.8917863902736,7.22527102956286 45.9083519552024,7.29786217539623 46.0250511739524,7.39771569102123 45.9386253927024,7.34168636250942 46.0679861580402,7.74563268097009 46.0679861580402))"
        }
      ],
      "faults": [
        {
          "name": "Tectonic Contact",
          "geometry": "LINESTRING(6.89865119793091 45.760812412948,7.22627733871568 46.0679861580402)"
        },
        {
          "name": "Tectonic Contact",
          "geometry": "LINESTRING(7.31459581150975 45.760812412948,7.80578613281244 46.0036258690342)"
        }
      ],
      "plates": [
        {
          "name": "Aoste",
          "geometry": "POLYGON((7.02208256000011 45.925259909,7.64302657100012 45.966342672,7.80578613281244 45.760812412948,6.78449660332847 45.760812412948,7.02208256000011 45.925259909))"
        },
        {
          "name": "Haute-Savoie",
          "geometry": "POLYGON((7.02208256000011 45.925259909,6.69589146278248 45.760812412948,6.48742675781252 45.8867125682433,6.48742675781252 46.0679861580402,7.02208256000011 45.925259909))"
        },
        {
          "name": "Savoie",
          "geometry": "MULTIPOLYGON(((6.48742675781252 45.8867125682433,6.69589146278248 45.760812412948,6.48742675781252 45.760812412948,6.48742675781252 45.8867125682433)))"
        },
        {
          "name": "Valais",
          "geometry": "POLYGON((7.80578613281244 45.9184668986572,7.09019209700011 45.8805081180001,6.85196374500009 46.0646829220001,7.80578613281244 46.0679861580402,7.80578613281244 45.9184668986572))"
        }
      ]
    },
    "hydrology": {
      "rivers": [
        {
          "name": "Dora Baltea",
          "geometry": "MULTILINESTRING((6.88274173268792 45.8056094421815,7.07094960396669 45.760812412948),(7.47528042859518 45.760812412948,7.49574465227324 45.760812412948),(7.5077323410963 45.760812412948,7.55451062324413 45.760812412948))"
        }
      ]
    }
  },
  "references": [
    {
      "dataset": "Admin level 0 - Countries",
      "author": "Natural Earth",
      "license": "Free of Charge",
      "url": "http:\\/\\/www.naturalearthdata.com\\/downloads\\/10m-cultural-vectors\\/10m-admin-0-countries\\/"
    },
    {
      "dataset": "Admin level 1 - States, Provinces",
      "author": "Natural Earth",
      "license": "Free of Charge",
      "url": "http:\\/\\/www.naturalearthdata.com\\/downloads\\/10m-cultural-vectors\\/10m-admin-1-states-provinces\\/"
    },
    {
      "dataset": "World Glacier Inventory",
      "author": "NSIDC",
      "license": "Free of Charge",
      "url": "http:\\/\\/nsidc.org\\/data\\/docs\\/noaa\\/g01130_glacier_inventory\\/#data_descriptions"
    },
    {
      "dataset": "Major world fault lines",
      "author": "ESRI",
      "license": "Access granted to Licensee only",
      "url": "http:\\/\\/edcommunity.esri.com\\/Resources\\/Collections\\/mapping-our-world"
    },
    {
      "dataset": "Major world tectonic plates",
      "author": "ESRI",
      "license": "Access granted to Licensee only",
      "url": "http:\\/\\/edcommunity.esri.com\\/Resources\\/Collections\\/mapping-our-world"
    },
    {
      "dataset": "Major volcanos of the world",
      "author": "ESRI",
      "license": "Access granted to Licensee only",
      "url": "http:\\/\\/edcommunity.esri.com\\/Resources\\/Collections\\/mapping-our-world"
    },
    {
      "dataset": "Glaciated area",
      "author": "Natural Earth",
      "license": "Free of Charge",
      "url": "http:\\/\\/www.naturalearthdata.com\\/downloads\\/10m-physical-vectors\\/10m-glaciated-areas\\/"
    },
    {
      "dataset": "Rivers and lake centerlines",
      "author": "Natural Earth",
      "license": "Free of charge",
      "url": "http:\\/\\/www.naturalearthdata.com\\/downloads\\/10m-physical-vectors\\/10m-rivers-lake-centerlines\\/"
    },
    {
      "dataset": "Marine Regions",
      "author": "Natural Earth",
      "license": "Free of charge",
      "url": "http:\\/\\/www.naturalearthdata.com\\/downloads\\/10m-physical-vectors\\/10m-physical-labels\\/"
    }
  ]
}
```

<h3 id="tag-a-geometry-responses">Responses</h3>

|Status|Meaning|Description|Schema|
|---|---|---|---|
|200|[OK](https://tools.ietf.org/html/rfc7231#section-6.3.1)|List of features|Inline|

<h3 id="tag-a-geometry-responseschema">Response Schema</h3>

<aside class="success">
This operation does not require authentication
</aside>

