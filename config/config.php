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

return array(
    
    /*
     * General configuration
     */
    "general" => array(
        
        /*
         * Maximum area allowed (in square kilometers)
         * for LandCover computation 
         */
        "areaLimit" => ${APP_LANDCOVER_MAXIMUM_AREA},
        
        /*
         * Return WKT geometries
         */
        "returnGeometries" => ${APP_RETURN_GEOMETRIES},
        
        /*
         * Tolerance value for simplication (in degrees)
         */
        "geometryTolerance" => ${APP_SIMPLICATION_TOLERANCE}
        
    ),
    
    /*
     * Database configuration
     */
    "database" => array(
        
        /*
         * Database name
         */
        "dbname" => "${DATABASE_NAME}",
        
        /*
         * Host - if not specified socket connection
         */
        "host" => "itagdb",
        
        /*
         * Port
         */
        "port" => 5432,
        
        /*
         * Database user with READ privileges 
         */
        "user" => "${DATABASE_USER_NAME}",
        "password" => "${DATABASE_USER_PASSWORD}"
    
    )

);