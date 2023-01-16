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
class iTag
{

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
    const VERSION = '5.3.6';
    
    /*
     * Character separator
     */
    const TAG_SEPARATOR = ':';

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
        'geometryTolerance' => 0.1,

        /*
         * Default planet
         */
        'planet' => 'earth'
    );

    /**
     * Constructor
     *
     * @param array $database : database configuration array
     * @param array $config : configuration
     */
    public function __construct($database, $config = array())
    {
        if (isset($database['dbh'])) {
            $this->dbh = $database['dbh'];
        } elseif (isset($database['dbname'])) {
            $this->setDatabaseHandler($database);
        } else {
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
    public function tag($metadata, $taggers = array())
    {
        if (!isset($metadata['geometry'])) {
            throw new Exception('Missing mandatory geometry', 500);
        }

        /*
         * Convert input geometry to 4326
         */
        $originalGeometry = $metadata['geometry'];
        
        try {
            $metadata['geometry'] = $this->wktTo4326($metadata['geometry']);
        }
        catch (Exception $e) {
            throw new Exception('WKT transformation error', 500);
        }
        
        /*
         * Throws exception if geometry is invalid
         */
        $topologyAnalysis = $this->getTopologyAnalysis($metadata['geometry']);
        if (!$topologyAnalysis['isValid']) {
            throw new Exception($topologyAnalysis['error'], 400);
        }

        /*
         * Initialize Always tags and datasources reference information
         */
        $tagger = new AlwaysTagger($this->dbh, $this->config);
        $content = $tagger->tag($metadata);
        $references = $tagger->references;

        /*
         * Add geometry area to metadata
         */
        $metadata['area'] = $content['area'];

        /*
         * Call the 'tag' function of all input taggers
         */
        foreach ($taggers as $name => $options) {

            $tagger = $this->instantiateTagger(ucfirst(strtolower(trim($name))) . 'Tagger');

            if (isset($tagger)) {

                // Try to apply a Tagger specific to a planet to another planet - silently do nothing
                if ( isset($tagger->planet) && strtolower($tagger->planet) !== strtolower($this->config['planet']) ) {
                    continue;
                }

                $content = array_merge($content, $tagger->tag($metadata, $options));
                $references = array_merge($references, $tagger->references);
            }

        }

        /*
         * Close database handler
         */
        pg_close($this->dbh);

        $output = array();
        if ($originalGeometry !== $metadata['geometry']) {
            $output['originalGeometry'] = $originalGeometry;
        }
        return array_merge($output, array(
            'geometry' => $metadata['geometry'],
            'planet' => $this->config['planet'],
            'timestamp' => $metadata['timestamp'] ?? null,
            'area_unit' => 'km2',
            'cover_unit' => '%',
            'content' => $content,
            'references' => $references
        ));
    }

    /**
     * Set configuration
     *
     * @param array $config
     */
    private function setConfig($config)
    {
        $this->config = array_merge($this->config, $config);
    }

    /**
     * Return PostgreSQL database handler
     *
     * @param array $options
     * @throws Exception
     */
    private function setDatabaseHandler($options)
    {
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
                $dbInfo[] = 'port=' . ($options['port'] ?? '5432');
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
    private function instantiateTagger($className)
    {
        if (!$className) {
            return null;
        }

        try {
            $class = new ReflectionClass($className);
            if (!$class->isInstantiable()) {
                throw new Exception();
            }
        } catch (Exception $e) {
            return null;
        }

        return $class->newInstance($this->dbh, $this->config);
    }
    
    /**
     * Return geometry topology analysis
     *
     * @param string $geometry
     * @param string $srid
     */
    private function getTopologyAnalysis($geometry, $srid = '4326')
    {
        if (!isset($geometry) || $geometry === '') {
            return array(
                'isValid' => false,
                'error' => 'Empty geometry'
            );
        }

        $check = '[GEOMETRY]';
        try {

            // Check input geometry
            $this->isTopologyValid('ST_isValid(ST_GeomFromText($1, ' . $srid . '))', $geometry);
            
            // Check split geometry
            $check = '[SPLITTED]';
            if ($this->isTopologyValid('ST_isValid(ST_SplitDateLine(ST_GeomFromText($1, ' . $srid . ')))', $geometry)) {
                return array(
                    'isValid' => true
                );
            }
        } catch (Exception $e) {
            return array(
                'isValid' => false,
                'error' => $check . ' ' . pg_last_error($this->dbh)
            );
        }

        return array(
            'isValid' => false,
            'error' => 'Invalid geometry'
        );
    }

    /*
     * Check $what query against geometry
     */
    private function isTopologyValid($what, $geometry)
    {
        $results = @pg_query_params($this->dbh, 'SELECT ' . $what . ' as valid', array(
            $geometry
        ));
        if (!isset($results) || $results === false) {
            throw new Exception();
        }
        
        return pg_fetch_result($results, 0, 'valid');
    }

    /**
     * Convert input wkt with an explicit SRID to EPSG:4326
     * 
     * @param string $wkt
     */
    private function wktTo4326($wkt) {

        $exploded = explode(';', $wkt);
        
        // No SRID - return untouched WKT
        if (strrpos(strtolower($exploded[0]), 'srid') === false) {
            return $wkt;
        }

        $srid = (integer) substr($exploded[0], 5);

        // SRID is 4326 - return untouched WKT
        if ($srid === 4326) {
            return $wkt;
        }

        $results = @pg_query_params($this->dbh, 'SELECT ST_AsText(ST_Transform(ST_GeomFromText($1, ' . $srid . '), 4326)) as wkt', array(
            $exploded[1]
        ));

        if (!isset($results) || $results === false) {
            throw new Exception();
        }
        
        return pg_fetch_result($results, 0, 'wkt');

    }

}
