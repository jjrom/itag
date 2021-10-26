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

class AlwaysTagger extends Tagger
{

    /*
     * Data references
     */
    public $references = array(
        array(
            'dataset' => 'Coastline',
            'author' => 'Natural Earth',
            'license' => 'Free of Charge',
            'url' => 'http://www.naturalearthdata.com/downloads/10m-physical-vectors/10m-coastline/'
        )
    );

    /*
     * Well known areas
     */
    private $areas = array(
        'equatorial' => array(
            'operator' => 'ST_Crosses',
            'geometry' => 'ST_GeomFromText(\'LINESTRING(-180 0,180 0)\', 4326)'
        ),
        'tropical' => array(
            'operator' => 'ST_Contains',
            'geometry' => 'ST_GeomFromText(\'POLYGON((-180 -23.43731,-180 23.43731,180 23.43731,180 -23.43731,-180 -23.43731))\', 4326)'
        ),
        'southern' => array(
            'operator' => 'ST_Contains',
            'geometry' => 'ST_GeomFromText(\'POLYGON((-180 0,-180 -90,180 -90,180 0,-180 0))\', 4326)'
        ),
        'northern' => array(
            'operator' => 'ST_Contains',
            'geometry' => 'ST_GeomFromText(\'POLYGON((-180 0,-180 90,180 90,180 0,-180 0))\', 4326)'
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
     * TODO Tag metadata
     *
     * @param array $metadata
     * @param array $options
     * @return array
     * @throws Exception
     */
    public function tag($metadata, $options = array())
    {

        /*
         * Relative location on earth
         */
        $locations = $this->getLocations($metadata['geometry'], $metadata['planet']);
        $keywords = $locations;

        /*
         * Coastal and seasons are Earth only
         */
        if ( $metadata['planet'] !== 'earth' ) {
            $this->references = array();
        }
        else {
        
            if ($this->isCoastal($metadata['geometry'])) {
                $keywords[] = 'location' . iTag::TAG_SEPARATOR . 'coastal';
            }

            /*
            * Season
            */
            if (isset($metadata['timestamp']) && $this->isValidTimeStamp($metadata['timestamp'])) {
                $keywords[] = $this->getSeason($metadata['timestamp'], in_array('location:southern', $locations));
            }
        }

        return array(
            'area' => $this->getArea($metadata['geometry']),
            'keywords' => $keywords
        );
    }

    /**
     * Return geometry area in square meters
     *
     * @param string $geometry
     */
    private function getArea($geometry)
    {
        $query = 'SELECT ' . $this->postgisArea($this->postgisGeomFromText($geometry)) . ' as area';
        $result = $this->query($query);
        if ($result) {
            $row = pg_fetch_assoc($result);
            return isset($row) && isset($row['area']) ? $this->toSquareKm($row['area']) : 0;
        }
        return 0;
    }

    /**
     * Return locations of geometry i.e.
     *  - location:equatorial
     *  - location:tropical
     *  - location:northern
     *  - location:southern
     *
     * @param string $geometry
     * @param string $planet
     */
    private function getLocations($geometry, $planet)
    {
        $locations = array();
        foreach ($this->areas as $key => $value) {

            // Tropical tag is for Earth only
            if ($key === 'tropical' && $planet !== 'earth') {
                continue;
            }

            if ($this->isETNS($geometry, $value)) {
                $locations[] = 'location'. iTag::TAG_SEPARATOR . $key;
            }
        }
        return $locations;
    }

    /**
     * Return true if geometry overlaps a coastline
     *
     * @param string $geometry
     */
    private function isCoastal($geometry)
    {
        $geom = $this->postgisGeomFromText($geometry);
        $query = 'SELECT gid FROM datasources.coastlines WHERE ST_Crosses(' . $geom . ', geom) OR ST_Contains(' . $geom . ', geom)';
        return $this->hasResults($query);
    }

    /**
     * Return true if geometry overlaps Equatorial, Tropical, Southern or Northern areas
     *
     * @param string $geometry
     * @param array $what
     */
    private function isETNS($geometry, $what)
    {
        $query = 'SELECT 1 WHERE ' . $what['operator'] . '(' . $what['geometry'] . ',' . $this->postgisGeomFromText($geometry) . ') LIMIT 1';
        return $this->hasResults($query);
    }

    /**
     * Return season keyword
     *
     * @param string $timestamp
     * @param boolean $southern
     */
    private function getSeason($timestamp, $southern = false)
    {

        /*
         * Get month and day
         */
        $month = intval(substr($timestamp, 5, 2));
        $day = intval(substr($timestamp, 8, 2));

        if ($this->isSpring($month, $day)) {
            return $southern ? 'season' . iTag::TAG_SEPARATOR . 'autumn' : 'season' . iTag::TAG_SEPARATOR . 'spring';
        } elseif ($this->isSummer($month, $day)) {
            return $southern ? 'season' . iTag::TAG_SEPARATOR . 'winter' : 'season' . iTag::TAG_SEPARATOR . 'summer';
        } elseif ($this->isAutumn($month, $day)) {
            return $southern ? 'season' . iTag::TAG_SEPARATOR . 'spring' : 'season' . iTag::TAG_SEPARATOR . 'autumn';
        } else {
            return $southern ? 'season' . iTag::TAG_SEPARATOR . 'summer' : 'season' . iTag::TAG_SEPARATOR . 'winter';
        }
    }

    /**
     * Return true if season is winter
     *
     * @param integer $month
     * @param integer $day
     * @return type
     */
    private function isSpring($month, $day)
    {
        return $this->isSeason($month, $day, array(3, 6));
    }

    /**
     * Return true if season is winter
     *
     * @param integer $month
     * @param integer $day
     * @return type
     */
    private function isSummer($month, $day)
    {
        return $this->isSeason($month, $day, array(6, 9));
    }

    /**
     * Return true if season is winter
     *
     * @param integer $month
     * @param integer $day
     * @return type
     */
    private function isAutumn($month, $day)
    {
        return $this->isSeason($month, $day, array(9, 12));
    }

    /**
     * Return true if month/day are inside magics bounds
     *
     * @param integer $month
     * @param integer $day
     * @return type
     */
    private function isSeason($month, $day, $magics)
    {
        if ($month > $magics[0] && $month < $magics[1]) {
            return true;
        }
        if ($month === $magics[0] && $day > 20) {
            return true;
        }
        if ($month === $magics[1] && $day < 21) {
            return true;
        }
        return false;
    }

    /**
     * Return true is query returns result.
     *
     * @param string $query
     * @return boolean
     */
    private function hasResults($query)
    {
        $result = $this->query($query);
        if (!isset($result) || !$result) {
            return false;
        }
        $rows = pg_fetch_all($result);
        if (empty($rows)) {
            return false;
        }
        return true;
    }
}
