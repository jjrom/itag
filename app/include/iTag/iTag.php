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
class iTag {

    /**
     * @OA\OpenApi(
     *  @OA\Info(
     *      title="iTag - Semantic enhancement of Earth Observation data",
     *      description="iTag is a web service for the semantic enhancement of Earth Observation products, i.e. the tagging of products with additional information about the covered area, regarding for example geology, water bodies, land use, population, countries, administrative units or names of major settlements.",
     *      version=API_VERSION,
     *      @OA\Contact(
     *          email="jerome.gasperi@gmail.com"
     *      )
     *  ),
     *  @OA\Server(
     *      description=API_HOST_DESCRIPTION,
     *      url=API_HOST_URL
     *  )
     * )
     */
    const VERSION = '5.0.1';
    
    /*
     * Character separator
     */
    const TAG_SEPARATOR = '_';

    /*
     * Database handler
     */
    private $dbh;

    /*
     * Configuration
     */
    private $config = array(

        /*
         * Maximum area allowed (in square kilometers)
         * for LandCover computation
         */
        'areaLimit' => 200000,

        /*
         * Return WKT geometries
         */
        'returnGeometries' => false,

        /*
         * Tolerance value for simplication (in degrees)
         */
        'geometryTolerance' => 0.1
    );

    /**
     * Constructor
     *
     * @param array $database : database configuration array
     * @param array $config : configuration
     */
    public function __construct($database, $config = array()) {
        if (isset($database['dbh'])) {
            $this->dbh = $database['dbh'];
        }
        else if (isset($database['dbname'])) {
            $this->setDatabaseHandler($database);
        }
        else {
            throw new Exception('Database connection error', 500);
        }
        $this->setConfig($config);
    }

    /**
     * Tag a polygon using taggers
     *
     * @param array $metadata // must include a 'geometry' in WKT format
     *                        // and optionnaly a 'timestamp' ISO8601 date
     * @param array $taggers
     * @return array
     * @throws Exception
     */
    public function tag($metadata, $taggers = array()) {

        if (!isset($metadata['geometry'])) {
            throw new Exception('Missing mandatory geometry', 500);
        }

        /*
         * Throws exception if geometry is invalid
         */
        $topologyAnalysis = $this->getTopologyAnalysis($metadata['geometry']);
        if ( !$topologyAnalysis['isValid'] ) {
            throw new Exception($topologyAnalysis['error'], 400);
        }

        /*
         * Datasources reference information
         */
        $references = array();

        /*
         * These tag are always performed
         */
        $content = $this->always($metadata);

        /*
         * Add geometry area to metadata
         */
        $metadata['area'] = $content['area'];

        /*
         * Call the 'tag' function of all input taggers
         */
        foreach ($taggers as $name => $options) {
            $tagger = $this->instantiateTagger($name);
            if (isset($tagger)) {
                $content = array_merge($content, $tagger->tag($metadata, $options));
                $references = array_merge($references, $tagger->references);
            }
        }

        /*
         * Close database handler
         */
        pg_close($this->dbh);

        return array(
            'geometry' => $metadata['geometry'],
            'timestamp' => isset($metadata['timestamp']) ? $metadata['timestamp'] : null,
            'area_unit' => 'km2',
            'cover_unit' => '%',
            'content' => $content,
            'references' => $references
        );

    }

    /**
     * Always performed tags
     *
     * @param array $metadata
     */
    private function always($metadata) {
        $tagger = new Tagger_always($this->dbh, $this->config);
        return $tagger->tag($metadata);
    }

    /**
     * Set configuration
     *
     * @param array $config
     */
    private function setConfig($config) {
        $this->config = array_merge($this->config, $config);
    }

    /**
     * Return PostgreSQL database handler
     *
     * @param array $options
     * @throws Exception
     */
    private function setDatabaseHandler($options) {
        try {
            $dbInfo = array(
                'dbname=' . $options['dbname'],
                'user=' . $options['user'],
                'password=' . $options['password']
            );
            /*
             * If host is specified, then TCP/IP connection is used
             * Otherwise socket connection is used
             */
            if (isset($options['host'])) {
                $dbInfo[] = 'host=' . $options['host'];
                $dbInfo[] = 'port=' . (isset($options['port']) ? $options['port'] : '5432');
            }
            $dbh = pg_connect(join(' ', $dbInfo));
            if (!$dbh) {
                throw new Exception();
            }
        } catch (Exception $e) {
            throw new Exception('Database connection error', 500);
        }
        $this->dbh = $dbh;
    }

    /**
     * Instantiate a class with params
     *
     * @param string $className : class name to instantiate
     */
    private function instantiateTagger($className) {

        if (!$className) {
            return null;
        }

        try {
            $class = new ReflectionClass('Tagger_' . $className);
            if (!$class->isInstantiable()) {
                throw new Exception();
            }
        } catch (Exception $e) {
            return null;
        }

        return $class->newInstance($this->dbh, $this->config);

    }

    /**
     * Correct input polygon WKT from -180/180 crossing problem
     *
     * @param String $geometry
     */
    private function correctWrapDateLine($geometry) {

        /*
         * Convert WKT POLYGON to array of coordinates
         */
        $coordinates = $this->wktToCoordinates($geometry);

        /*
         * If Delta(lon(i) - lon(i - 1)) is greater than 180 degrees then add 360 to lon
         */
        $add360 = false;
        $lonPrev = $coordinates[0][0];
        $latPrev = $coordinates[0][1];
        $newCoordinates = array(array($lonPrev, $latPrev));
        for ($i = 1, $ii = count($coordinates); $i < $ii; $i++) {
            $lon = $coordinates[$i][0];
            if ($lon - $lonPrev >= 180) {
                $lon = $lon - 360;
                $add360 = true;
            }
            else if ($lon - $lonPrev <= -180) {
                $lon = $lon + 360;
                $add360 = true;
            }
            $lonPrev = $lon;
            $latPrev = $coordinates[$i][1];
            $newCoordinates[] = array($lon, $coordinates[$i][1]);
        }

        return $this->coordinatesToWkt($newCoordinates, $add360);
    }

    /**
     * Convert WKT into an array of coordinates
     *
     * @param string $geometry
     * @return array
     */
    private function wktToCoordinates($geometry) {
        $pairs = explode(',', str_replace('POLYGON((', ' ', str_replace('))', ' ', strtoupper($geometry))));
        $coordinates = array();
        for ($i = 0, $ii = count($pairs); $i < $ii; $i++) {
            $lonlat = explode(' ', trim($pairs[$i]));
            $coordinates[] = array(floatval($lonlat[0]), floatval($lonlat[1]));
        }
        return $coordinates;
    }

    /**
     * Convert an array of coordinates into a WKT string
     *
     * @param array $coordinates
     * @param boolean $add360
     * @return string
     */
    private function coordinatesToWkt($coordinates, $add360 = false) {
        $pairs = array();
        for ($i = 0, $ii = count($coordinates); $i < $ii; $i++) {
            if ($add360) {
                $coordinates[$i][0] = $coordinates[$i][0] + 360;
            }
            $pairs[] = join(' ', $coordinates[$i]);
        }
        return 'POLYGON((' . join(',', $pairs) . '))';
    }

    /**
     * Return geometry topology analysis
     *
     * @param string $geometry
     * @param string $srid
     */
    private function getTopologyAnalysis($geometry, $srid = '4326') {
        
        if ( !isset($geometry) || $geometry === '') {
            return array(
                'isValid' => false,
                'error' => 'Empty geometry'
            );
        }

        $geometryFromText = 'ST_GeomFromText(\'' . $geometry . '\', ' . $srid . ')';

        try {
            $results = @pg_query($this->dbh, 'SELECT ST_isValid(' . $geometryFromText . ') as valid');
            if (!isset($results) || $results === false) {
                throw new Exception();
            }
        }
        catch (Exception $e) {
            return array(
                'isValid' => false,
                'error' => '[GEOMETRY] ' . pg_last_error($this->dbh)
            );
        }

        try {
            $results = @pg_query($this->dbh, 'SELECT ST_isValid(ST_SplitDateLine(' . $geometryFromText . ')) as valid');
            if (!isset($results) || $results === false) {
                throw new Exception();
            }
        }
        catch (Exception $e) {
            return array(
                'isValid' => false,
                'error' => '[GEOMETRY][SPLITTED] ' . pg_last_error($this->dbh)
            );
        }

        $result = pg_fetch_result($results, 0, 'valid');
        if ($result === false || $result === 'f') {
            return array(
                'isValid' => false,
                'error' => 'Invalid geometry'
            );
        }
        
        return array(
            'isValid' => true
        );

    }

}