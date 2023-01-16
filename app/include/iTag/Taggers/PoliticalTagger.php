<?php
/*
 * Copyright 2013 Jérôme Gasperi
 *
 * Licensed under the Apache License, version 2.0 (the "License");
 * You may not use this file except in compliance with the License.
 * You may obtain a copy of the License at:
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS, WITHOUT
 * WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied. See the
 * License for the specific language governing permissions and limitations
 * under the License.
 */
require 'CountryInfos.php';
class PoliticalTagger extends Tagger
{
    const COUNTRIES = 1;
    const REGIONS = 2;
    const CONTINENTS = 3;

    /*
     * This Tagger is specific to Earth
     */
    public $planet = 'earth';
    
    /*
     * Data references
     */
    public $references = array(
        array(
            'dataset' => 'Admin level 0 - Countries',
            'author' => 'Natural Earth',
            'license' => 'Free of Charge',
            'url' => 'http://www.naturalearthdata.com/downloads/10m-cultural-vectors/10m-admin-0-countries/'
        ),
        array(
            'dataset' => 'Admin level 1 - States, Provinces',
            'author' => 'Natural Earth',
            'license' => 'Free of Charge',
            'url' => 'http://www.naturalearthdata.com/downloads/10m-cultural-vectors/10m-admin-1-states-provinces/'
        )
    );

    /*
     * Geonameid for continents
     */
    private $geonameIdForContinents = array(
        'Australia' => 2077456,
        'Africa'=> 6255146,
        'Asia' => 6255147,
        'Europe' => 6255148,
        'NorthAmerica' => 6255149,
        'SouthAmerica' => 6255150,
        'Oceania'  => 6255151,
        'Antartica' => 6255152
    );

    /*
     * Compute toponyms : 'main', 'all', null
     */
    private $addToponyms = null;

    /**
     * Constructor
     *
     * @param DatabaseHandler $dbh
     * @param array $config
     */
    public function __construct($dbh, $config)
    {
        parent::__construct($dbh, $config);
    }

    /**
     * Tag metadata
     *
     * @param array $metadata
     * @param array $options
     * @return array
     * @throws Exception
     */
    public function tag($metadata, $options = array())
    {
        parent::tag($metadata, $options);
        return $this->process($metadata['geometry'], $options);
    }

    /**
     * Compute intersected information from input WKT geometry
     *
     * @param string $geometry
     * @param array $options
     *
     */
    private function process($geometry, $options)
    {

        /*
         * Superseed areaLimit
         *
        if (isset($options['areaLimit']) && $this->area > $options['areaLimit']) {
            return array(
                'political' => array()
            );
        }*/

        /*
         * Toponyms
         */
        if (isset($options['toponyms'])) {
            $this->addToponyms = $options['toponyms'];
        }

        $limitToCountries = isset($options['limitToCountries']) ? filter_var($options['limitToCountries'], FILTER_VALIDATE_BOOLEAN) : false;
        $limitToContinents = isset($options['limitToContinents']) ? filter_var($options['limitToContinents'], FILTER_VALIDATE_BOOLEAN) : false;

        /*
         * Initialize empty array
         */
        $continents = array();

        /*
         * Continents only
         */
        if ($limitToContinents) {
            $this->addContinents($continents, $geometry);
        }
        else {

            /*
             * Add continents and countries
             */
            $this->add($continents, $geometry, PoliticalTagger::COUNTRIES);

            /*
             * Add regions/states if requested
             */
            if ( !$limitToCountries ) {
                $this->add($continents, $geometry, PoliticalTagger::REGIONS);
            }

        }

        return array(
            'political' => array(
                'continents' => $continents
            )
        );

    }

    /**
     * Add continents/countries or regions/states to political array
     *
     * @param array $continents
     * @param string $geometry
     * @param integer $what
     * 
     */
    private function add(&$continents, $geometry, $what)
    {
        $prequery = 'WITH prequery AS (SELECT ' . $this->postgisGeomFromText($geometry) . ' AS corrected_geometry)';
        if ($what === PoliticalTagger::COUNTRIES) {
            $query = $prequery . ' SELECT name as name, concat(normalize_initcap(name), \'' . iTag::TAG_SEPARATOR . '\', geonameid) as id, continent as continent, normalize_initcap(continent) as continentid, ' . $this->postgisArea($this->postgisIntersection('geom', 'corrected_geometry')) . ' as area, ' . $this->postgisArea('geom') . ' as entityarea FROM prequery, datasources.countries WHERE st_intersects(geom, corrected_geometry) ORDER BY area DESC';
        } else {
            $query = $prequery . ' SELECT region, name as state, concat(normalize_initcap(name), \'' . iTag::TAG_SEPARATOR . '\', geonameid) as stateid, normalize_initcap(region) as regionid, adm0_a3 as isoa3, ' .  $this->postgisArea($this->postgisIntersection('geom', 'corrected_geometry')) . ' as area, ' . $this->postgisArea('geom') . ' as entityarea, ' . $this->postgisIntersection('geom', 'corrected_geometry') . ' as wkb_geom, iso_a2 FROM prequery, datasources.states WHERE st_intersects(geom, corrected_geometry) ORDER BY area DESC';
        }
        $results = $this->query($query);
        if ($results) {
            while ($element = pg_fetch_assoc($results)) {
                if ($what === PoliticalTagger::COUNTRIES) {
                    $this->addCountriesToContinents($continents, $element);
                    continue;
                }
            
                /*
                 * Get region info
                 */
                if (isset($element['regionid'])) {
                    $element = $this->updateRegionInfo($element, $geometry);
                }
                
                $this->addRegionsToCountries($continents, $element);
            }
        }
    }

    /**
     * Add continents
     *
     * @param array $continents
     * @param array $element
     */
    private function addContinents(&$continents, $geometry)
    {
        $prequery = 'WITH prequery AS (SELECT ' . $this->postgisGeomFromText($geometry) . ' AS corrected_geometry)';
        $geomColumn = 'geom_simple';
        $query = $prequery . ' SELECT continent, normalize_initcap(continent) as continentid, ' . $this->postgisArea($this->postgisIntersection($geomColumn, 'corrected_geometry')) . ' as area, ' . $this->postgisArea($geomColumn) . ' as entityarea FROM prequery, datasources.continents WHERE st_intersects(' . $geomColumn . ', corrected_geometry) ORDER BY area DESC';
        $results = $this->query($query);
        if ($results) {
            while ($element = pg_fetch_assoc($results)) {
                $continentGeoname = $this->geonameIdForContinents[$element['continentid']];
                array_push($continents, array(
                    'name' => $element['continent'],
                    'id' => 'continent'. iTag::TAG_SEPARATOR . $element['continentid'] . (isset($continentGeoname) ? iTag::TAG_SEPARATOR . $continentGeoname : iTag::TAG_SEPARATOR),
                    'countries' => array()
                ));
                continue;
            }
        }
    }


    /**
     * Add regions/states under countries
     *
     * @param array $continents
     * @param array $element
     */
    private function addRegionsToCountries(&$continents, $element)
    {
        for ($i = count($continents); $i--;) {
            for ($j = count($continents[$i]['countries']); $j--;) {
                $countryName = isset(CountryInfos::$countryNames[$element['isoa3']]) ? CountryInfos::$countryNames[$element['isoa3']] : null;
                if (isset($countryName) && ($continents[$i]['countries'][$j]['name'] === $countryName)) {
                    $this->addRegionsToCountry($continents[$i]['countries'][$j], $element);
                    break;
                }
            }
        }
    }


    /**
     * Add regions/states under countries
     *
     * @param array $country
     * @param array $element
     */
    private function addRegionsToCountry(&$country, $element)
    {
        if (!isset($country['regions'])) {
            $country['regions'] = array();
        }

        // No region => state instead
        isset($element['regionid']) ? $this->mergeRegionsAndStates($country, $element) : $this->mergeState($country['regions'], $element);
    }

    /**
     * Merge regions and states array
     *
     * @param array $country
     * @param array $element
     */
    private function mergeRegionsAndStates(&$country, $element)
    {
        $index = -1;
        for ($k = count($country['regions']); $k--;) {
            if (!$element['regionid'] && !isset($country['regions'][$k]['id'])) {
                $index = $k;
                break;
            } elseif (isset($country['regions'][$k]['id']) && $country['regions'][$k]['id'] === $element['regionid']) {
                $index = $k;
                break;
            }
        }

        /*
        * Add region
        */
        if ($index === -1) {
            $this->mergeRegion($country['regions'], $element);
            $index = count($country['regions']) - 1;
        }

        /*
        * Add state (and toponyms)
        */
        if (isset($country['regions'][$index]['states'])) {
            $this->mergeState($country['regions'][$index]['states'], $element);
        }
    }

    /**
     * Add countries under content
     *
     * @param array $continents
     * @param array $element
     */
    private function addCountriesToContinents(&$continents, $element)
    {
        $index = -1;
        for ($i = count($continents); $i--;) {
            if ($continents[$i]['name'] === $element['continent']) {
                $index = $i;
                break;
            }
        }
        if ($index === -1) {
            $continentGeoname = $this->geonameIdForContinents[$element['continentid']];
            array_push($continents, array(
                'name' => $element['continent'],
                'id' => 'continent'. iTag::TAG_SEPARATOR . $element['continentid'] . (isset($continentGeoname) ? iTag::TAG_SEPARATOR . $continentGeoname : iTag::TAG_SEPARATOR),
                'countries' => array()
            ));
            $index = count($continents) - 1;
        }
        
        $area = $this->toSquareKm($element['area']);
        $pcover = $this->percentage($area, $this->area);
        if ($pcover > 0) {
            array_push($continents[$index]['countries'], array(
                'name' => $element['name'],
                'id' => 'country'. iTag::TAG_SEPARATOR . $element['id'],
                'pcover' => $pcover,
                'gcover' => $this->percentage($area, $this->toSquareKm($element['entityarea']))
            ));
        }
        
    }

    /**
     * Merge region to country array
     *
     * @param array $country
     * @param array $element
     */
    private function mergeRegion(&$regions, $element)
    {
        if (!isset($element['regionid']) || !$element['regionid']) {
            array_push($regions, array(
                'states' => array()
            ));
        } else {
            if (isset($element['regionarea']) && isset($element['regionentityarea'])) {
                $area = $this->toSquareKm($element['regionarea']);
                $pcover = $this->percentage($area, $this->area);
                if ($pcover > 0) {
                    array_push($regions, array(
                        'name' => $element['region'],
                        'id' => 'region'. iTag::TAG_SEPARATOR . $element['regionid'],
                        'pcover' => $pcover,
                        'gcover' => $this->percentage($area, $this->toSquareKm($element['regionentityarea'])),
                        'states' => array()
                    ));
                }
            } else {
                array_push($regions, array(
                    'name' => $element['region'],
                    'id' => 'region'. iTag::TAG_SEPARATOR . $element['regionid'],
                    'pcover' => -1,
                    'gcover' => -1,
                    'states' => array()
                ));
            }
        }
    }

    /**
     * Merge state to region array
     *
     * @param array $country
     * @param array $element
     */
    private function mergeState(&$states, $element)
    {
        $area = $this->toSquareKm($element['area']);
        $pcover = $this->percentage($area, $this->area);
        if ($pcover > 0) {
            $state = array(
                'name' => $element['state'],
                'id' => 'state'. iTag::TAG_SEPARATOR . $element['stateid'],
                'pcover' => $pcover,
                'gcover' => $this->percentage($area, $this->toSquareKm($element['entityarea']))
            );
    
            if ($this->addToponyms) {
                $state['toponyms'] = $this->getToponyms($element['wkb_geom']);
            }
    
            array_push($states, $state);
        }
    }

    /**
     * Add toponyms to political array
     *
     * @param string $wkb geometry as wkb
     */
    private function getToponyms($wkb)
    {
        $toponyms = array();
        $codes = $this->addToponyms === 'all' && $this->isValidArea($this->area) ? "('PPL', 'PPLC', 'PPLA', 'PPLA2', 'PPLA3', 'PPLA4', 'STLMT')" : "('PPLA','PPLC')";
        $query = 'SELECT name, longitude, latitude, fcode, population FROM gazetteer.geoname WHERE st_intersects(geom, \'' . $wkb .  '\') AND fcode IN ' . $codes . ' ORDER BY CASE fcode WHEN \'PPLC\' then 1 WHEN \'PPLG\' then 2 WHEN \'PPLA\' then 3 WHEN \'PPLA2\' then 4 WHEN \'PPLA4\' then 5 WHEN \'PPL\' then 6 ELSE 7 END ASC, population DESC';
        $results = $this->query($query);
        if ($results) {
            while ($result = pg_fetch_assoc($results)) {
                $toponyms[] = array(
                  'name' => $result['name'],
                  'geo:lon' => (float) $result['longitude'],
                  'geo:lat' => (float) $result['latitude'],
                  'fcode' => $result['fcode'],
                  'population' => (integer) $result['population']
              );
            }
        }
        return $toponyms;
    }

    /**
     * Compute region info and add it to input element
     */
    private function updateRegionInfo($element, $geometry)
    {
        $results = $this->query('WITH prequery AS (SELECT ' . $this->postgisGeomFromText($geometry) . ' AS corrected_geometry) SELECT concat(normalize_initcap(name), \'' . iTag::TAG_SEPARATOR . '\', geonameid) as regionid2, ' . $this->postgisArea($this->postgisIntersection('geom', 'corrected_geometry')) . ' as regionarea, ' . $this->postgisArea('geom') . ' as regionentityarea FROM prequery, datasources.regions WHERE normalize_initcap(name)=\'' . $element['regionid'] . '\' AND iso_a2=\'' . $element['iso_a2'] . '\' LIMIT 1');
        if ($results) {
            while ($element2 = pg_fetch_assoc($results)) {
                $element['regionid'] = $element2['regionid2'];
                $element['regionarea'] = $element2['regionarea'];
                $element['regionentityarea'] = $element2['regionentityarea'];
            }
        }
        return $element;
    }
}
