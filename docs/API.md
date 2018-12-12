---
title: iTag - Semantic enhancement of Earth Observation data
language_tabs:
  - shell: Shell
  - http: HTTP
  - javascript: JavaScript
  - javascript--nodejs: Node.JS
  - ruby: Ruby
  - python: Python
  - java: Java
  - go: Go
toc_footers: []
includes: []
search: false
highlight_theme: darkula
headingLevel: 2

---

<h1 id="itag-semantic-enhancement-of-earth-observation-data">iTag - Semantic enhancement of Earth Observation data v5.0</h1>

> Scroll down for code samples, example requests and responses. Select a language for code samples from the tabs above or the mobile navigation menu.

iTag is a web service for the semantic enhancement of Earth Observation products, i.e. the tagging of products with additional information about the covered area, regarding for example geology, water bodies, land use, population, countries, administrative units or names of major settlements.

Base URLs:

* <a href="http://localhost:1212/">http://localhost:1212/</a>

Email: <a href="mailto:jerome.gasperi@gmail.com">Support</a> 

<h1 id="itag-semantic-enhancement-of-earth-observation-data-default">Default</h1>

## Tag a geometry

<a id="opIdiTagLauncher::tag"></a>

> Code samples

```shell
# You can also use wget
curl -X GET http://localhost:1212/ \
  -H 'Accept: application/json'

```

```http
GET http://localhost:1212/ HTTP/1.1
Host: localhost:1212
Accept: application/json

```

```javascript
var headers = {
  'Accept':'application/json'

};

$.ajax({
  url: 'http://localhost:1212/',
  method: 'get',

  headers: headers,
  success: function(data) {
    console.log(JSON.stringify(data));
  }
})

```

```javascript--nodejs
const fetch = require('node-fetch');

const headers = {
  'Accept':'application/json'

};

fetch('http://localhost:1212/',
{
  method: 'GET',

  headers: headers
})
.then(function(res) {
    return res.json();
}).then(function(body) {
    console.log(body);
});

```

```ruby
require 'rest-client'
require 'json'

headers = {
  'Accept' => 'application/json'
}

result = RestClient.get 'http://localhost:1212/',
  params: {
  }, headers: headers

p JSON.parse(result)

```

```python
import requests
headers = {
  'Accept': 'application/json'
}

r = requests.get('http://localhost:1212/', params={

}, headers = headers)

print r.json()

```

```java
URL obj = new URL("http://localhost:1212/");
HttpURLConnection con = (HttpURLConnection) obj.openConnection();
con.setRequestMethod("GET");
int responseCode = con.getResponseCode();
BufferedReader in = new BufferedReader(
    new InputStreamReader(con.getInputStream()));
String inputLine;
StringBuffer response = new StringBuffer();
while ((inputLine = in.readLine()) != null) {
    response.append(inputLine);
}
in.close();
System.out.println(response.toString());

```

```go
package main

import (
       "bytes"
       "net/http"
)

func main() {

    headers := map[string][]string{
        "Accept": []string{"application/json"},
        
    }

    data := bytes.NewBuffer([]byte{jsonReq})
    req, err := http.NewRequest("GET", "http://localhost:1212/", data)
    req.Header = headers

    client := &http.Client{}
    resp, err := client.Do(req)
    // ...
}

```

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
      "location_northern",
      "season_winter"
    ],
    "political": {
      "continents": [
        {
          "name": "Europe",
          "id": "continent_europe_6255148",
          "countries": [
            {
              "name": "Italy",
              "id": "country_italy_3175395",
              "pcover": 37.02,
              "gcover": 0.42,
              "regions": [
                {
                  "name": "Valle d'Aosta",
                  "id": "region_valledaosta_3164857",
                  "pcover": 37.2,
                  "gcover": 39.19,
                  "states": [
                    {
                      "name": "Aoste",
                      "id": "state_aoste_3182996",
                      "pcover": 37.02,
                      "gcover": 39.13
                    }
                  ]
                }
              ]
            },
            {
              "name": "France",
              "id": "country_france_3017382",
              "pcover": 32.9,
              "gcover": 0.18,
              "regions": [
                {
                  "name": "Rh\\u00f4ne-Alpes",
                  "id": "region_rhonealpes_11071625",
                  "pcover": 32.94,
                  "gcover": 2.56,
                  "states": [
                    {
                      "name": "Haute-Savoie",
                      "id": "state_hautesavoie_3013736",
                      "pcover": 29.39,
                      "gcover": 21.86
                    }
                  ]
                },
                {
                  "name": "Rh\\u00f4ne-Alpes",
                  "id": "region_rhonealpes_11071625",
                  "pcover": 32.94,
                  "gcover": 2.56,
                  "states": [
                    {
                      "name": "Savoie",
                      "id": "state_savoie_2975517",
                      "pcover": 3.51,
                      "gcover": 1.98
                    }
                  ]
                }
              ]
            },
            {
              "name": "Switzerland",
              "id": "country_switzerland_2658434",
              "pcover": 30.04,
              "gcover": 2.53,
              "regions": [
                {
                  "states": [
                    {
                      "name": "Valais",
                      "id": "state_valais_2658205",
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

