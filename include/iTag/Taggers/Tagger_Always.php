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

class Tagger_Always extends Tagger {

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
        'tropical' => 'ST_GeomFromText(\'POLYGON((-180 -23.43731,-180 23.43731,180 23.43731,180 -23.43731,-180 -23.43731))\', 4326)',
        'southern' => 'ST_GeomFromText(\'POLYGON((-180 -23.43731,-180 -90,180 -90,180 -23.43731,-180 -23.43731))\', 4326)',
    );
    
    /**
     * Constructor
     * 
     * @param DatabaseHandler $dbh
     * @param array $config
     */
    public function __construct($dbh, $config) {
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
    public function tag($metadata, $options = array()) {
        
        $keywords = array();
        
        /*
         * Season
         */
        if (isset($metadata['timestamp']) && $this->isValidTimeStamp($metadata['timestamp']) ) {
            $keywords[] = $this->getSeason($metadata['timestamp'], $metadata['footprint']);
        }
        
        /*
         * Coastal status
         */
        if ($this->isCoastal($metadata['footprint'])) {
            $keywords[] = 'location:coastal';
        }
       
        
        return array(
            'keywords' => array_merge($keywords, $this->getLocation($metadata['footprint']))
        );
        
    }
    
    /**
     * Return location of footprint i.e.
     *  - location:equatorial
     *  - location:northern
     *  - location:southern
     * 
     * @param string $footprint
     */
    private function getLocation($footprint) {
        $location = 'location:northern';
        foreach (array_values(array('tropical', 'southern')) as $value) {
            if ($this->isEquatorialOrSouthern($footprint, $value)) {
                $location = 'location:' . $value;
                break;
            }
        }
        return array(
            $location
        );
    }
    
    /**
     * Return true if footprint overlaps a coastline
     * 
     * @param string $footprint
     */
    private function isCoastal($footprint) {
        $query = 'SELECT gid FROM datasources.coastlines WHERE ST_Crosses(ST_GeomFromText(\'' . $footprint . '\', 4326), geom) OR ST_Contains(ST_GeomFromText(\'' . $footprint . '\', 4326), geom)';
        return $this->hasResults($query);
    }
    
    /**
     * Return true if footprint overlaps equatorial area
     * 
     * @param string $footprint
     * @param string $type
     */
    private function isEquatorialOrSouthern($footprint, $type) {
        $query = 'SELECT 1 WHERE ST_Crosses(ST_GeomFromText(\'' . $footprint . '\', 4326), ' . $this->areas[$type] . ') OR ST_Contains(' . $this->areas[$type] . ', ST_GeomFromText(\'' . $footprint . '\', 4326)) LIMIT 1';
        return $this->hasResults($query);
    }
    
    /**
     * Return season keyword
     * 
     * @param string $timestamp
     * @param string $footprint
     */
    private function getSeason($timestamp, $footprint) {
        
        /*
         * Get month and day
         */
        $month = intval(substr($timestamp, 5, 2));
        $day = intval(substr($timestamp, 8, 2));
        
        if ($this->isSpring($month, $day)) {
            return 'season:spring';
        }
        
        else if ($this->isSummer($month, $day)) {
            return 'season:summer';
        }
        
        else if ($this->isAutumn($month, $day)) {
            return 'season:autumn';
        }
        
        else {
            return 'season:winter';
        }
        
    }
    
    /**
     * Return true if season is winter
     * 
     * @param integer $month
     * @param integer $day
     * @return type
     */
    private function isSpring($month, $day) {
        return $this->isSeason($month, $day, array(3, 6));
    }
    
    /**
     * Return true if season is winter
     * 
     * @param integer $month
     * @param integer $day
     * @return type
     */
    private function isSummer($month, $day) {
        return $this->isSeason($month, $day, array(6, 9));
    }
    
    /**
     * Return true if season is winter
     * 
     * @param integer $month
     * @param integer $day
     * @return type
     */
    private function isAutumn($month, $day) {
        return $this->isSeason($month, $day, array(9, 12));
    }
    
    /**
     * Return true if month/day are inside magics bounds 
     * 
     * @param integer $month
     * @param integer $day
     * @return type
     */
    private function isSeason($month, $day, $magics) {
        if ($month > $magics[0] && $month < $magics[1]) {
            return true;
        }
        if ($month === $magics[0] && $day > 20) {
            return true;
        }
        if ($month === $magics[1] && $day < 21) {
            return true;
        }
        return true;
    }
    
    /**
     * Return true is query returns result.
     * 
     * @param string $query
     * @return boolean
     */
    private function hasResults($query) {
        $results = pg_fetch_all($this->query($query));
        if (empty($results)) {
            return false;
        }
        return true;
    }
}
