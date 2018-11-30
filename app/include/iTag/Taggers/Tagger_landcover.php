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

class Tagger_landcover extends Tagger {

    /*
     * Data references
     */
    public $references = array(
        array(
            'dataset' => 'Global Land Cover 2000',
            'author' => 'JRC',
            'license' => 'Free of Charge for non-commercial use',
            'url' => 'http://bioval.jrc.ec.europa.eu/products/glc2000/data_access.php'
        )
    );

    /*
     * Corine Land Cover
     */
    private $clcClassNames = array(
        100 => 'Urban',
        200 => 'Cultivated',
        310 => 'Forest',
        320 => 'Herbaceous',
        330 => 'Desert',
        335 => 'Ice',
        400 => 'Flooded',
        500 => 'Water'
    );

    /*
     * Global Land Cover class names
     */
    private $glcClassNames = array(
        1 => 'Tree Cover, Broadleaved, Evergreen',
        2 => 'Tree Cover, Broadleaved, Deciduous, Closed',
        3 => 'Tree Cover, Broadleaved, Feciduous, Open',
        4 => 'Tree Cover, Needle-leaved, Evergreen',
        5 => 'Tree Cover, Needle-leaved, Deciduous',
        6 => 'Tree Cover, Mixed Leaf Type',
        7 => 'Tree Cover, Regularly Fooded, Fresh  Water',
        8 => 'Tree Cover, Regularly Flooded, Saline Water',
        9 => 'Mosaic - Tree Cover / Other Natural Vegetation',
        10 => 'Tree Cover, Burnt',
        11 => 'Shrub Cover, Closed-open, Evergreen',
        12 => 'Shrub Cover, Closed-open, Deciduous',
        13 => 'Herbaceous Cover, Closed-open',
        14 => 'Sparse Herbaceous Or Sparse Shrub Cover',
        15 => 'Regularly Flooded Shrub And/Or Herbaceous Cover',
        16 => 'Cultivated And Managed Areas',
        17 => 'Mosaic - Cropland / Tree Cover / Other Natural Vegetation',
        18 => 'Mosaic - Cropland / Shrub Or Grass Cover',
        19 => 'Bare Areas',
        20 => 'Water Bodies',
        21 => 'Snow And Ice',
        22 => 'Artificial Surfaces And Associated Areas'
    );

    /*
     * Corine Land Cover - Global Land Cover linkage
     */
    private $linkage = array(
        100 => array(22), // Urban
        200 => array(15, 16, 17, 18), // Cultivated
        310 => array(1, 2, 3, 4, 5, 6), // Forest
        320 => array(9, 11, 12, 13), // Herbaceous
        330 => array(10, 14, 19), // Desert
        335 => array(21), // Ice
        400 => array(7, 8), // Flooded
        500 => array(20) // Water
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
     * Tag metadata
     *
     * @param array $metadata
     * @param array $options
     * @return array
     * @throws Exception
     */
    public function tag($metadata, $options = array()) {
        parent::tag($metadata, $options);
        return $this->process($metadata['geometry'], $options);
    }

    /**
     *
     * Compute land cover from input WKT geometry
     *
     * @param string $geometry
     * @param array $options
     *
     */
    private function process($geometry, $options) {

        $output = array(
            'landcover' => array(
                'main' => array(),
                'details' => array()
            )
        );

        /*
         * Superseed areaLimit
         */
        if (isset($options['areaLimit']) && $this->area > $options['areaLimit']) {
            return $output;
        }

        /*
         * Do not process if geometry area is greater
         * than the maximum area allowed
         */
        if (!$this->isValidArea($this->area)) {
            return $output;
        }

        /*
         * Get raw landcover
         */
        $rawLandCover = $this->retrieveRawLandCover($geometry);

        /*
         * Return full land use description
         */
        return array(
            'landcover' => array(
                'main' => $this->getLandCover($rawLandCover),
                'details' => $this->getLandCoverDetails($rawLandCover)
            )
        );
    }

    /**
     * Returns main land use
     *
     * @param array $rawLandCover
     */
    private function getLandCover($rawLandCover) {
        $sums = array();
        foreach ($this->linkage as $key => $value) {
            $sums[$key] = $this->sum($rawLandCover, $value);
        }
        arsort($sums);
        $landCover = array();
        foreach ($sums as $key => $val) {
            $pcover = $this->percentage($this->toSquareKm($val), $this->area);
            if ($val !== 0 && $pcover > 0) {
                $name = isset($this->clcClassNames[$key]) ? $this->clcClassNames[$key] : 'unknown';
                array_push($landCover, array(
                    'name' => $name,
                    'id' => 'lc'. iTag::TAG_SEPARATOR . $name,
                    'area' => $this->toSquareKm($val),
                    'pcover' => $pcover
                ));
            }
        }

        return $landCover;

    }

    /**
     * Returns land use details
     *
     * @param array $rawLandCover
     */
    private function getLandCoverDetails($rawLandCover) {
        $landCoverDetails = array();
        foreach ($rawLandCover as $key => $val) {
            if ($val['area'] !== 0) {
                $name = isset($this->glcClassNames[$key]) ? $this->glcClassNames[$key] : 'unknown';
                $area = $this->toSquareKm($val['area']);
                $details = array(
                    'name' => $name,
                    'id' => 'lcd'. iTag::TAG_SEPARATOR . str_replace(array('/', ',', ' ', '-'), '', $name),
                    'parentId' => 'lc'. iTag::TAG_SEPARATOR . $this->getCLCParent($key),
                    'code' => $key,
                    'area' => $area,
                    'pcover' => $this->percentage($area, $this->area)
                );
                if ($this->config['returnGeometries'] && !empty($val['geometries'])) {
                    $details['geometry'] = 'MULTIPOLYGON(' . join(',', $val['geometries']) . ')';
                }
                array_push($landCoverDetails, $details);
            }
        }
        return $landCoverDetails;
    }

    /**
     * Retrieve landcover from iTag database
     *
     * @param string $geometry
     * @return array
     */
    private function retrieveRawLandCover($geometry) {
        $classes = array();
        $prequery = 'WITH prequery AS (SELECT ' . $this->postgisGeomFromText($geometry) . ' AS corrected_geometry)';
        if ($this->config['returnGeometries']) {
            $query = $prequery . ' SELECT dn as dn, ' . $this->postgisArea($this->postgisIntersection('wkb_geometry', 'corrected_geometry')) . ' as area, ' . $this->postgisAsWKT($this->postgisSimplify($this->postgisIntersection('wkb_geometry', 'corrected_geometry'))) . ' as wkt FROM prequery, landcover.landcover WHERE st_intersects(wkb_geometry, corrected_geometry)';
        }
        else {
            $query = $prequery . ' SELECT dn as dn, ' . $this->postgisArea($this->postgisIntersection('wkb_geometry', 'corrected_geometry')) . ' as area FROM prequery, landcover.landcover WHERE st_intersects(wkb_geometry, corrected_geometry)';
        }
        $results = $this->query($query);
        if (!$results) {
          return $classes;
        }
        while ($result = pg_fetch_assoc($results)) {
            if (!isset($classes[$result['dn']])) {
                $classes[$result['dn']] = array(
                    'area' => 0,
                    'geometries' => array()
                );
            }
            $classes[$result['dn']]['area'] += $result['area'];
            if (isset($result['wkt']) && substr($result['wkt'], 0, 4) === 'POLY') {
                $classes[$result['dn']]['geometries'][] = '(' . substr($result['wkt'], 8, count($result['wkt']) - 2) . ')';
            }
        }

        return $classes;
    }

    /**
     * Return the sum of classes[$keys] values
     *
     * @param array $classes
     * @param array $keys
     */
    private function sum($classes, $keys) {
        $sum = 0;
        for ($i = count($keys); $i--;) {
            if (isset($classes[$keys[$i]])) {
                $sum += $classes[$keys[$i]]['area'];
            }
        }
        return $sum;
    }

    /**
     * Return CLC class name from child GLC $code
     *
     * @param integer $code
     */
    private function getCLCParent($code) {
        foreach ($this->linkage as $key => $value) {
            if (in_array($code, $value) && isset($this->clcClassNames[$key])) {
                return $this->clcClassNames[$key];
            }
        }
        return 'unknown';
    }

}
