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

class HydrologyTagger extends GenericTagger
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
            'dataset' => 'Rivers and lake centerlines',
            'author' => 'Natural Earth',
            'license' => 'Free of charge',
            'url' => 'http://www.naturalearthdata.com/downloads/10m-physical-vectors/10m-rivers-lake-centerlines/'
        )
    );
    
    /*
     * Columns mapping per table
     */
    protected $columnsMapping = array(
        'rivers' => array(
            'name' => 'name'
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
        return array(
            'hydrology' => parent::tag($metadata, array_merge($options, array('schema' => 'datasources')))
        );
    }
}
