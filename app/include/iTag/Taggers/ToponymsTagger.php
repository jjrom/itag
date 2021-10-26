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

class ToponymsTagger extends Tagger
{

    /*
     * This Tagger is specific to Earth
     */
    public $planet = 'earth';
    
    /*
     * Data references
     */

    public $references = array(
        array(
            'dataset' => 'Geonames',
            'author' => 'Geonames',
            'license' => 'Free of Charge',
            'url' => 'http://www.geonames.org/'
        )
    );

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
     * Return the closest toponym from centroid that is within the geometry
     *
     * @param string $geometry
     * @param array $options
     *
     */
    private function process($geometry, $options)
    {
        $toponyms = array();
        $codes = "('PPL', 'PPLC', 'PPLA', 'PPLA2', 'PPLA3', 'PPLA4', 'STLMT')";

        $prequery = 'WITH prequery AS (SELECT ' . $this->postgisGeomFromText($geometry) . ' AS corrected_geometry, ST_centroid(' . $this->postgisGeomFromText($geometry) . ') AS corrected_centroid)';
        $query = $prequery . ' SELECT geonameid, name, normalize_initcap(name) as normalized, country, countryname, longitude, latitude, fcode, population, ST_Distance(geom, corrected_centroid) as distance FROM prequery, gazetteer.geoname WHERE st_intersects(geom, corrected_geometry) AND fcode IN ' . $codes . ' ORDER BY distance ASC';
        $results = $this->query($query);
        if ($results) {
            while ($result = pg_fetch_assoc($results)) {
                $toponyms[] = array(
                  'id' => (integer) $result['geonameid'],
                  'name' => $result['name'],
                  'normalized' => $result['normalized'],
                  'country' => $result['countryname'],
                  'ccode' => $result['country'],
                  'geo:lon' => (float) $result['longitude'],
                  'geo:lat' => (float) $result['latitude'],
                  'fcode' => $result['fcode'],
                  'population' => (integer) $result['population'],
                  'distanceToCentroid' => (float) $result['distance']
              );
            }
        }
        return array(
            'toponyms' => $toponyms
        );
    }
}
