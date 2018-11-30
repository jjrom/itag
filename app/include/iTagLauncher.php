<?php
/*
 * Copyright 2013 Jérôme Gasperi
 *
 * Licensedunder the Apache License,
 * version 2.0 (the "License"); you may not use this file except in compliance
 * with the License. You may obtain a copy of the License at:
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS, WITHOUT
 * WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied. See the
 * License for the specific language governing permissions and limitations
 * under the License.
 */
class iTagLauncher {
    
    /**
     * Constructor
     * 
     * @param string $configFile
     */
    public function __construct($configFile) {
        $this->tag($configFile);
    }
    
    /**
     * Tag geometry
     *
     *  @OA\Get(
     *      path="/",
     *      summary="Tag a geometry",
     *      description="Returns a list of features intersecting input geometry",
     *      @OA\Parameter(
     *         name="geometry",
     *         in="path",
     *         required=true,
     *         description="Input geometry as a POLYGON WKT",
     *         @OA\Schema(
     *             type="string"
     *         )
     *      ),
     *      @OA\Parameter(
     *         name="taggers",
     *         in="path",
     *         required=true,
     *         description="List of tagger applied. You can specify multiple taggers comma separated
* geology : Return intersected geological features i.e. faults, glaciers, plates and volcanoes
* hydrology : Return intersected hydrological features i.e. Lakes and rivers
* landcover : Compute landcover (based on Global LandCover 2000)
* physical : Return physical intersected features i.e. marine regions 
* political : Return political intersected features i.e. continents, countries, regions and states
* population : Compute population count and density",
     *         @OA\Schema(
     *             type="enum",
     *             enum={"geology", "hydrology", "landcover", "physical", "political", "population"}
     *         )
     *      ),
     *      @OA\Parameter(
     *         name="timestamp",
     *         in="path",
     *         required=false,
     *         description="Input timestamp (to compute season based on geometry location) - format ISO 8601 YYYY-MM-DDTHH:MM:SS",
     *         @OA\Schema(
     *             type="string"
     *         )
     *      ),
     *      @OA\Parameter(
     *         name="_pretty",
     *         in="path",
     *         required=false,
     *         description="True to return pretty print response",
     *         @OA\Schema(
     *             type="string"
     *         )
     *      ),
     *      @OA\Parameter(
     *         name="_wkt",
     *         in="path",
     *         required=false,
     *         description="True to return intersected features geometries as WKT",
     *         @OA\Schema(
     *             type="string"
     *         )
     *      ),
     *      @OA\Response(
     *          response="200",
     *          description="List of features",
     *          @OA\JsonContent(
     *               example={
     *                   "geometry": "POLYGON((6.487426757812523 45.76081241294796,6.487426757812523 46.06798615804025,7.80578613281244 46.06798615804025,7.80578613281244 45.76081241294796,6.487426757812523 45.76081241294796))",
     *                   "timestamp": "2018-01-13",
     *                   "area_unit": "km2",
     *                   "cover_unit": "%",
     *                   "content": {
     *                       "area": 3483.53511,
     *                       "keywords": {
     *                           "location_northern",
     *                           "season_winter"
     *                       },
     *                       "political": {
     *                           "continents": {
     *                               {
     *                                   "name": "Europe",
     *                                   "id": "continent_europe_6255148",
     *                                   "countries": {
     *                                       {
     *                                           "name": "Italy",
     *                                           "id": "country_italy_3175395",
     *                                           "pcover": 37.02,
     *                                           "gcover": 0.42,
     *                                           "regions": {
     *                                               {
     *                                                   "name": "Valle d'Aosta",
     *                                                   "id": "region_valledaosta_3164857",
     *                                                   "pcover": 37.2,
     *                                                   "gcover": 39.19,
     *                                                   "states": {
     *                                                       {
     *                                                           "name": "Aoste",
     *                                                           "id": "state_aoste_3182996",
     *                                                           "pcover": 37.02,
     *                                                           "gcover": 39.13
     *                                                       }
     *                                                   }
     *                                               }
     *                                           }
     *                                       },
     *                                       {
     *                                           "name": "France",
     *                                           "id": "country_france_3017382",
     *                                           "pcover": 32.9,
     *                                           "gcover": 0.18,
     *                                           "regions": {
     *                                               {
     *                                                   "name": "Rh\u00f4ne-Alpes",
     *                                                   "id": "region_rhonealpes_11071625",
     *                                                   "pcover": 32.94,
     *                                                   "gcover": 2.56,
     *                                                   "states": {
     *                                                       {
     *                                                           "name": "Haute-Savoie",
     *                                                           "id": "state_hautesavoie_3013736",
     *                                                           "pcover": 29.39,
     *                                                           "gcover": 21.86
     *                                                       }
     *                                                   }
     *                                               },
     *                                               {
     *                                                   "name": "Rh\u00f4ne-Alpes",
     *                                                   "id": "region_rhonealpes_11071625",
     *                                                   "pcover": 32.94,
     *                                                   "gcover": 2.56,
     *                                                   "states": {
     *                                                       {
     *                                                           "name": "Savoie",
     *                                                           "id": "state_savoie_2975517",
     *                                                           "pcover": 3.51,
     *                                                           "gcover": 1.98
     *                                                       }
     *                                                   }
     *                                               }
     *                                           }
     *                                       },
     *                                       {
     *                                           "name": "Switzerland",
     *                                           "id": "country_switzerland_2658434",
     *                                           "pcover": 30.04,
     *                                           "gcover": 2.53,
     *                                           "regions": {
     *                                               {
     *                                                   "states": {
     *                                                       {
     *                                                           "name": "Valais",
     *                                                           "id": "state_valais_2658205",
     *                                                           "pcover": 30.04,
     *                                                           "gcover": 19.79
     *                                                       }
     *                                                   }
     *                                               }
     *                                           }
     *                                       }
     *                                   }
     *                               }
     *                           }
     *                       },
     *                       "geology": {
     *                           "glaciers": {
     *                               {
     *                                   "name": "La Vall\u00e9e Blanche"
     *                               },
     *                               {
     *                                   "name": "Zmuttgletscher",
     *                                   "geometry": "POLYGON((7.74563268097009 46.0679861580402,7.80578613281244 45.8917863902736,7.22527102956286 45.9083519552024,7.29786217539623 46.0250511739524,7.39771569102123 45.9386253927024,7.34168636250942 46.0679861580402,7.74563268097009 46.0679861580402))"
     *                               }
     *                           },
     *                           "faults": {
     *                               {
     *                                   "name": "Tectonic Contact",
     *                                   "geometry": "LINESTRING(6.89865119793091 45.760812412948,7.22627733871568 46.0679861580402)"
     *                               },
     *                               {
     *                                   "name": "Tectonic Contact",
     *                                   "geometry": "LINESTRING(7.31459581150975 45.760812412948,7.80578613281244 46.0036258690342)"
     *                               }
     *                           },
     *                           "plates": {
     *                               {
     *                                   "name": "Aoste",
     *                                   "geometry": "POLYGON((7.02208256000011 45.925259909,7.64302657100012 45.966342672,7.80578613281244 45.760812412948,6.78449660332847 45.760812412948,7.02208256000011 45.925259909))"
     *                               },
     *                               {
     *                                   "name": "Haute-Savoie",
     *                                   "geometry": "POLYGON((7.02208256000011 45.925259909,6.69589146278248 45.760812412948,6.48742675781252 45.8867125682433,6.48742675781252 46.0679861580402,7.02208256000011 45.925259909))"
     *                               },
     *                               {
     *                                   "name": "Savoie",
     *                                   "geometry": "MULTIPOLYGON(((6.48742675781252 45.8867125682433,6.69589146278248 45.760812412948,6.48742675781252 45.760812412948,6.48742675781252 45.8867125682433)))"
     *                               },
     *                               {
     *                                   "name": "Valais",
     *                                   "geometry": "POLYGON((7.80578613281244 45.9184668986572,7.09019209700011 45.8805081180001,6.85196374500009 46.0646829220001,7.80578613281244 46.0679861580402,7.80578613281244 45.9184668986572))"
     *                               }
     *                           }
     *                       },
     *                       "hydrology": {
     *                           "rivers": {
     *                               {
     *                                   "name": "Dora Baltea",
     *                                   "geometry": "MULTILINESTRING((6.88274173268792 45.8056094421815,7.07094960396669 45.760812412948),(7.47528042859518 45.760812412948,7.49574465227324 45.760812412948),(7.5077323410963 45.760812412948,7.55451062324413 45.760812412948))"
     *                               }
     *                           }
     *                       }
     *                   },
     *                   "references": {
     *                       {
     *                           "dataset": "Admin level 0 - Countries",
     *                           "author": "Natural Earth",
     *                           "license": "Free of Charge",
     *                           "url": "http:\/\/www.naturalearthdata.com\/downloads\/10m-cultural-vectors\/10m-admin-0-countries\/"
     *                       },
     *                       {
     *                           "dataset": "Admin level 1 - States, Provinces",
     *                           "author": "Natural Earth",
     *                           "license": "Free of Charge",
     *                           "url": "http:\/\/www.naturalearthdata.com\/downloads\/10m-cultural-vectors\/10m-admin-1-states-provinces\/"
     *                       },
     *                       {
     *                           "dataset": "World Glacier Inventory",
     *                           "author": "NSIDC",
     *                           "license": "Free of Charge",
     *                           "url": "http:\/\/nsidc.org\/data\/docs\/noaa\/g01130_glacier_inventory\/#data_descriptions"
     *                       },
     *                       {
     *                           "dataset": "Major world fault lines",
     *                           "author": "ESRI",
     *                           "license": "Access granted to Licensee only",
     *                           "url": "http:\/\/edcommunity.esri.com\/Resources\/Collections\/mapping-our-world"
     *                       },
     *                       {
     *                           "dataset": "Major world tectonic plates",
     *                           "author": "ESRI",
     *                           "license": "Access granted to Licensee only",
     *                           "url": "http:\/\/edcommunity.esri.com\/Resources\/Collections\/mapping-our-world"
     *                       },
     *                       {
     *                           "dataset": "Major volcanos of the world",
     *                           "author": "ESRI",
     *                           "license": "Access granted to Licensee only",
     *                           "url": "http:\/\/edcommunity.esri.com\/Resources\/Collections\/mapping-our-world"
     *                       },
     *                       {
     *                           "dataset": "Glaciated area",
     *                           "author": "Natural Earth",
     *                           "license": "Free of Charge",
     *                           "url": "http:\/\/www.naturalearthdata.com\/downloads\/10m-physical-vectors\/10m-glaciated-areas\/"
     *                       },
     *                       {
     *                           "dataset": "Rivers and lake centerlines",
     *                           "author": "Natural Earth",
     *                           "license": "Free of charge",
     *                           "url": "http:\/\/www.naturalearthdata.com\/downloads\/10m-physical-vectors\/10m-rivers-lake-centerlines\/"
     *                       },
     *                       {
     *                           "dataset": "Marine Regions",
     *                           "author": "Natural Earth",
     *                           "license": "Free of charge",
     *                           "url": "http:\/\/www.naturalearthdata.com\/downloads\/10m-physical-vectors\/10m-physical-labels\/"
     *                       }
     *                   }
     *               }
     *          )
     *      )
     *  )
     *
     * @param array params
     */
    private function tag($configFile) {

        $params = $this->getParams();

        // Initialize
        $this->initialize($configFile, $params['config']);
        
        $taggers = array();
        foreach (array_values($params['taggersList']) as $value) {
            $taggers[strtolower(trim($value))] = array();
        }
        try {
            $this->answer($this->json_format($this->itag->tag($params['metadata'], $taggers)), 200);
        } catch (Exception $e) {
            $this->answer($this->json_format(array('ErrorMessage' => $e->getMessage(), 'ErrorCode' =>  $e->getCode())), $e->getCode());
        }   
        
    }
    /**
     * Read configuration file and set up iTag object
     * 
     * @param string $configFile
     * @param array $configFromRequest
     */
    private function initialize($configFile, $configFromRequest) {
        try {
            if (!file_exists($configFile)) {
                throw new Exception(__METHOD__ . 'Missing mandatory configuration file', 500);
            }
            $config = include($configFile);
            
            /*
             * Superseed with input params
             */
            foreach (array_keys($configFromRequest) as $key) {
                $config['general'][$key] = $configFromRequest[$key];
            }

            /*
             * Instantiate iTag
             */
            $this->itag = new iTag($config['database'], $config['general']);
            
        } catch (Exception $e) {
            $this->answer($this->json_format(array('ErrorMessage' => $e->getMessage(), 'ErrorCode' =>  $e->getCode())), $e->getCode());
        }
    }
    
    /**
     * Return GET params
     */
    private function getParams() {
        $this->pretty = filter_input(INPUT_GET, '_pretty', FILTER_VALIDATE_BOOLEAN);
        $params = array(
            'metadata' => array(
                'geometry' => rawurldecode(filter_input(INPUT_GET, 'geometry', FILTER_SANITIZE_STRING)),
                'timestamp' => rawurldecode(filter_input(INPUT_GET, 'timestamp', FILTER_SANITIZE_STRING))
            ),
            'taggersList' => explode(',', rawurldecode(filter_input(INPUT_GET, 'taggers', FILTER_SANITIZE_STRING))),
            'config' => array()
        );

        // Input query
        $query = $this->sanitize($_GET);
        
        if (isset($query['_wkt'])) {
            $params['config']['returnGeometries'] = filter_input(INPUT_GET, '_wkt', FILTER_VALIDATE_BOOLEAN);
        }

        return $params;
    }
    
    /**
     * Stream HTTP result and exit
     */
    private function answer($response, $responseStatus) {
        
        /*
         * HTTP 1.1 headers
         */
        header('HTTP/1.1 ' . $responseStatus . ' ' . ($responseStatus === 200 ? 'OK' : 'Internal Server Error'));
        header('Cache-Control:  no-cache');
        header('Content-Type: application/json');
        
        /*
         * Set headers including cross-origin resource sharing (CORS)
         * http://en.wikipedia.org/wiki/Cross-origin_resource_sharing
         */
        $this->setCORSHeaders();
        
        /*
         * Stream data
         */
        echo $response;
        
    }
    
    /**
     * Set CORS headers (HTTP OPTIONS request)
     */
    private function setCORSHeaders() {

        $httpOrigin = filter_input(INPUT_SERVER, 'HTTP_ORIGIN', FILTER_SANITIZE_STRING);
        $httpRequestMethod = filter_input(INPUT_SERVER, 'HTTP_ACCESS_CONTROL_REQUEST_METHOD', FILTER_SANITIZE_STRING);
        $httpRequestHeaders = filter_input(INPUT_SERVER, 'HTTP_ACCESS_CONTROL_REQUEST_HEADERS', FILTER_SANITIZE_STRING);
        
        /*
         * Only set access to known servers
         */
        if (isset($httpOrigin)) {
            header('Access-Control-Allow-Origin: ' . $httpOrigin);
            header('Access-Control-Allow-Credentials: true');
            header('Access-Control-Max-Age: 3600');
        }

        /*
         * Control header are received during OPTIONS requests
         */
        if (isset($httpRequestMethod)) {
            header('Access-Control-Allow-Methods: GET, OPTIONS');
        }
        if (isset($httpRequestHeaders)) {
            header('Access-Control-Allow-Headers: ' . $httpRequestHeaders);
        }
    }
 
    /**
     * Format a flat JSON string to make it more human-readable
     *
     * @param array $json JSON as an array
     * 
     * @return string Indented version of the original JSON string
     */
    private function json_format($json) {

        /*
         * No pretty print - easy part
         */
        if (!$this->pretty) {
            return json_encode($json);
        }
        
        return json_encode($json, JSON_PRETTY_PRINT);
     
    }

    /**
     * Sanitize input parameter to avoid code injection
     *   - remove html tags
     *
     * @param {String or Array} $strOrArray
     */
    private function sanitize($strOrArray) {

        if (!isset($strOrArray)) {
            return null;
        }

        if (is_array($strOrArray)) {
            $result = array();
            foreach ($strOrArray as $key => $value) {
                $result[$key] = $this->sanitizeString($value);
            }
            return $result;
        }

        return $this->sanitizeString($strOrArray);

    }

    /**
     * Sanitize string
     *
     * @param string $str
     * @return string
     */
    private function sanitizeString($str) {

        /*
         * Remove html tags and NULL (i.e. \0)
         */
        if (is_string($str)) {

            /*
             * No Hexadecimal allowed i.e. nothing that starts with 0x
             */
            if (strlen($str) > 1 && substr($str, 0, 2) === '0x') {
                return null;
            }

            return strip_tags(str_replace(chr(0), '', $str));
        }

        /*
         * Let value untouched
         */
        return $str;
    }

}