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

class GenericTagger extends Tagger
{

    /*
     * Columns mapping per table
     */
    protected $columnsMapping = array();
    
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
     * @param string geometry
     * @param array $options
     *
     */
    protected function process($geometry, $options)
    {
        $result = array();

        /*
         * Superseed areaLimit
         */
        if (isset($options['areaLimit']) && $this->area > $options['areaLimit']) {
            return $result;
        }
        
        /*
         * Process required classes
         */
        foreach ($this->columnsMapping as $tableName => $mapping) {
            $content = $this->retrieveContent( (isset($options['schema']) ? $options['schema'] . '.' : '') . $tableName, $mapping, $geometry, $options);
            if (count($content) > 0) {
                $result[$tableName] = $content;
            }
        }
        
        return $result;
    }

    /**
     * Retrieve content from table that intersects $geometry
     *
     * @param String $tableName
     * @param Array $mapping
     * @param String $geometry
     * @param Array $options
     *
     */
    private function retrieveContent($tableName, $mapping, $geometry, $options = array())
    {
        
        /*
         * Return WKT if specified in config file
         */
        if ($this->config['returnGeometries']) {
            $mapping['geometry'] = 'geom';
        }
        
        $content = array();
        $results = $this->getResults($tableName, $mapping, $geometry, $options);
        while ($result = pg_fetch_assoc($results)) {
            
            /*
             * Compute id from normalized
             */
            if (isset($result['type'])) {
                $geonameid = '';
                if (isset($result['geonameid'])) {
                    $geonameid = iTag::TAG_SEPARATOR . $result['geonameid'];
                }
                $result['id'] = strtolower($result['type']) . iTag::TAG_SEPARATOR . $result['normalized'] . $geonameid;
            }
            
            if (isset($result['area'])) {
                $area = $this->toSquareKm($result['area']);
                $result['pcover'] = $this->percentage($area, $this->area);

                // Break if there is no significative coverage
                if ($result['pcover'] <= 0) {
                    continue;
                }

                if (isset($result['entityarea'])) {
                    $result['gcover'] = $this->percentage($area, $this->toSquareKm($result['entityarea']));
                }
            }

            if (!isset($result['name'])) {
                unset($result['name']);
            }

            if (!isset($result['geometry'])) {
                unset($result['geometry']);
            }
            unset($result['geonameid'], $result['area'], $result['entityarea'], $result['normalized'], $result['type']);
            
            if (count(array_keys($result)) > 0) {
                $content[] = $result;
            }

        }

        return $content;
    }
    
    /**
     * Return structured results from database
     *
     * @param String $tableName
     * @param Array $mapping
     * @param String $geometry
     * @param Array $options
     */
    private function getResults($tableName, $mapping, $geometry, $options)
    {
        $propertyList = array();
        $geom = $this->postgisGeomFromText($geometry);
        $orderBy = '';
        foreach ($mapping as $asName => $columnName) {
            if ($asName === 'name') {
                $propertyList[] = 'distinct(' . $columnName . ') as name';
                $propertyList[] = 'normalize_initcap(' . $columnName . ') as normalized';
            } elseif ($asName === 'geometry') {
                $propertyList[] = $this->postgisAsWKT($this->postgisSimplify($this->postgisIntersection('geom', $geom))) . ' as geometry';
            } else {
                $propertyList[] = $columnName . ' as ' . $asName;
            }
        }
        
        /*
         * Return area
         */
        if (isset($options['computeArea']) && $options['computeArea'] === true) {
            $propertyList[] = $this->postgisArea($this->postgisIntersection('geom', $geom)) . ' as area';
            $propertyList[] = $this->postgisArea('geom') . ' as entityarea';
            $orderBy = ' ORDER BY area DESC';
        }
        
        return $this->query('WITH prequery AS (SELECT ' . $this->postgisGeomFromText($geometry) . ' AS corrected_geometry) SELECT ' . join(',', $propertyList) .  ' FROM prequery,' . $tableName . ' WHERE st_intersects(geom, corrected_geometry)' . $orderBy);
    }
}
