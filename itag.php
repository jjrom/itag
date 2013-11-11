<?php

/*
 * iTag
 *
 * Automatically tag a geographical footprint against every kind of things
 * (i.e. Land Cover, OSM data, population count, etc.)
 * Copyright 2013 Jérôme Gasperi <https://github.com/jjrom>
 * 
 * jerome[dot]gasperi[at]gmail[dot]com
 * 
 * 
 * This software is governed by the CeCILL-B license under French law and
 * abiding by the rules of distribution of free software.  You can  use,
 * modify and/ or redistribute the software under the terms of the CeCILL-B
 * license as circulated by CEA, CNRS and INRIA at the following URL
 * "http://www.cecill.info".
 *
 * As a counterpart to the access to the source code and  rights to copy,
 * modify and redistribute granted by the license, users are provided only
 * with a limited warranty  and the software's author,  the holder of the
 * economic rights,  and the successive licensors  have only  limited
 * liability.
 *
 * In this respect, the user's attention is drawn to the risks associated
 * with loading,  using,  modifying and/or developing or reproducing the
 * software by the user in light of its specific status of free software,
 * that may mean  that it is complicated to manipulate,  and  that  also
 * therefore means  that it is reserved for developers  and  experienced
 * professionals having in-depth computer knowledge. Users are therefore
 * encouraged to load and test the software's suitability as regards their
 * requirements in conditions enabling the security of their systems and/or
 * data to be ensured and,  more generally, to use and operate it in the
 * same conditions as regards security.
 *
 * The fact that you are presently reading this means that you have had
 * knowledge of the CeCILL-B license and that you accept its terms.
 * 
 */

// Remove PHP NOTICE
error_reporting(E_PARSE);

// Includes
include_once 'config/config.php';
include_once 'functions.php';

// This application can be called either from a shell or from an HTTP GET request
$isShell = !empty($_SERVER['SHELL']);

// What to compute
$output = 'json';
$hasCountries = false;
$citiesType = null;
$hasGeophysical = false;
$hasPopulation = false;
$hasLandCover = false;


// Case 1 - Shell command line parameters
if ($isShell) {
    $help  = "\nUSAGE : php itag.php [options] -f <footprint in WKT> (or -d <db connection info>)\n";
    $help .= "OPTIONS:\n";
    $help .= "   -o [type] : output (json|pretty|insert|copy|hstore) - Note : if -d is choosen only 'hstore', 'insert' and 'copy' are used \n";
    $help .= "   -c : Countries\n";
    $help .= "   -C : Cities (main|all)\n";
    $help .= "   -R : French Regions and departements\n";
    $help .= "   -p : Compute population\n";
    $help .= "   -g : Geophysical information (i.e. plates, volcanoes)\n";
    $help .= "   -l : compute land cover (i.e. Thematical content - forest, water, urban, etc.\n";
    $help .= "   -d : DB connection info - dbhost:dbname:dbuser:dbpassword:dbport:tableName:identifierColumnName:geometryColumnName\n";
    $help .= "\n\n";
    $options = getopt("cC:Rpgld:f:o:h");
    foreach ($options as $option => $value) {
        if ($option === "f") {
            $footprint = $value;
        }
        if ($option === "d") {
            $dbInfos = split(':', $value);
        }
        if ($option === "o") {
            $output = $value;
        }
        if ($option === "c") {
            $hasCountries = true;
        }
        if ($option === "C") {
            $citiesType = $value;
        }
        if ($option === "R") {
            $hasRegions = true;
        }
        if ($option === "g") {
            $hasGeophysical = true;
        }
        if ($option === "l") {
            $hasLandCover = true;
        }
        if ($option === "p") {
            $hasPopulation = true;
        }
        if ($option === "h") {
            echo $help;
            exit;
        }
    }

    // Footprint is mandatory
    if (!$footprint && !$dbInfos) {
        echo $help;
        exit;
    }
}
/*
 *  Case 2 - Webservice parameters
 * 
 *  Note : -d option is not possible from Webservice
 */
else {
    $footprint = isset($_REQUEST['footprint']) ? $_REQUEST['footprint'] : null;
    $hasCountries = isset($_REQUEST['countries']) ? true : false;
    $citiesType = isset($_REQUEST['cities']) ? $_REQUEST['cities'] : null;
    $hasRegions = isset($_REQUEST['regions']) ? true : false;
    $hasGeophysical = isset($_REQUEST['geophysical']) ? true : false;
    $hasLandCover = isset($_REQUEST['landcover']) ? true : false;
    $hasPopulation = isset($_REQUEST['population']) ? true : false;
    $output = isset($_REQUEST['output']) ? $_REQUEST['output'] : $output;
    if (!$footprint) {
        echo "footprint is mandatory";
        exit;
    }
}

// Connect to database
$dbh = getPgDB("host=" . DB_HOST . " dbname=" . DB_NAME . " user=" . DB_USER . " password=" . DB_PASSWORD);
pg_set_client_encoding($dbh, "UTF8");
if (!$dbh) {
    error($dbh, $isShell, "\nFATAL : No connection to database\n\n");
}

/*
 * Case 1 : User entries of DB
 *   'dbhost':'dbname':'dbuser':'dbpassword':'dbport':'table':'identifier column name':'geometry column name'
 */
if ($dbInfos) {
            
    if (count($dbInfos) !== 8) {
        error($dbh, $isShell, "\nFATAL : -d option format is dbhost:dbname:dbuser:dbpassword:dbport:tableName:identifierColumnName:geometryColumnName\n\n");
    }

    $dbhSource = getPgDB("host=" . $dbInfos[0] . " dbname=" . $dbInfos[1] . " user=" . $dbInfos[2] . " password=" . $dbInfos[3]. " port=" . $dbInfos[4]);
    if (!$dbhSource) {
        error($dbhSource, $isShell, "\nFATAL : No connection to database $dbInfos[1]\n\n");
    }
  
    /*
     * Usefull constants !
     */
    $tableName = $dbInfos[5];
    $identifierColumn = $dbInfos[6];
    $geometryColumn = $dbInfos[7];
    $hstoreColumn = "keywords";
    
    /*
     * HSTORE
     */
    if (isset($output) && $output === 'hstore') {
        echo "-- Enable hstore in database \n";
        echo "CREATE EXTENSION hstore;\n\n";
        echo "-- Add keywords column to table " . $tableName . " \n";
        echo "ALTER TABLE " . $tableName . " ADD COLUMN keywords hstore;\n";
        echo "\n";
    }
    
    /*
     * Count number of elements to process
     */
    $query = "SELECT count(*) as total FROM " . $tableName;
    $results = pg_query($dbhSource, $query);
    if (!$results) {
        error($dbhSource, $isShell, "\nFATAL : $dbInfos[1] database connection error\n\n");
    }
    $result = pg_fetch_assoc($results);
    $total = $result['total'];
    $limit = 200;
    $pages = ceil($total / $limit);
    echo '-- Total number of elements to be processed : ' . $total . "\n";
 
    /*
     * COPY
     */
    if (isset($output) && $output === 'copy') {
        echo "COPY keywords (identifier, keyword, type) FROM stdin;\n";
    }
    
    /*
     * Seems like pagination is quicker !
     */
    $baseQuery = "SELECT " . $identifierColumn . " as identifier, st_AsText(" . $geometryColumn . ") as footprint FROM " . $tableName . " ORDER BY " . $identifierColumn . " LIMIT " . $limit;
    for ($j = 0; $j < $pages; $j++) {
        $offset = ($j * $limit);
        $query = $baseQuery . " OFFSET " . $offset;
        
        if (!isset($output) || $output !== 'copy') {
            echo "\n";
            echo '-- Process elements from ' . $offset . ' to ' . ($offset + $limit - 1);
            echo '-- ' . $query;
            echo "\n";
        }
        
        $results = pg_query($dbhSource, $query);
        if (!$results) {
            error($dbhSource, $isShell, "\nFATAL : $dbInfos[1] database connection error\n\n");
        }

        while ($result = pg_fetch_assoc($results)) {
            if ($hasCountries || $citiesType || $hasRegions) {
                
                $arr = getPolitical($dbh, $isShell, $result["footprint"], $citiesType, $hasRegions);
                
                // Continents
                tostdin($result["identifier"], $arr["continents"], "CONTINENT", $tableName, $identifierColumn, $hstoreColumn, $output);
                
                // Countries
                tostdin($result["identifier"], $arr["countries"], "COUNTRY", $tableName, $identifierColumn, $hstoreColumn, $output);
                
                // Cities
                if (isset($arr["cities"])) {
                    tostdin($result["identifier"], $arr["cities"], "CITY", $tableName, $identifierColumn, $hstoreColumn, $output);
                }
            }
            
            if ($hasGeophysical) {
                $arr = getGeophysical($dbh, $isShell, $result["footprint"]);
                tostdin($result["identifier"], $arr["volcanoes"], "VOLCANO", $tableName, $identifierColumn, $hstoreColumn, $output);
            }

            if ($hasLandCover) {
                $arr = getLandCover($dbh, $isShell, $result["footprint"]);
                $landUse = split(LIST_SEPARATOR, $arr["landUse"]);
                for ($i = 0, $l = count($landUse); $i < $l; $i++) {
                    
                    tostdin($result["identifier"], $landUse[$i], "LANDCOVER_".($i+1), $tableName, $identifierColumn, $hstoreColumn, $output);
                }
                
            }
        }
        
    }
    
    // Close COPY
    if (isset($output) && $output === 'copy') {
        echo "\.\n";
    }
    
    /*
     * HSTORE
     */
    if (isset($output) && $output === 'hstore') {
        echo "-- Create GIN index in database \n";
        echo "CREATE INDEX " . $tableName . "_" . $hstoreColumn . "_idx ON " . $tableName . " USING GIN (" . $hstoreColumn . ");\n";
        echo "\n";
    }
    
    
}
/*
 * Case 2 : use footprint
 */
else {
    
    // Initialize GeoJSON output
    $geojson = array(
        'type' => 'FeatureCollection',
        'features' => array()
    );

    // Initialize Feature
    $feature = array(
        'type' => 'Feature',
        'geometry' => wktPolygon2GeoJSONGeometry($footprint),
        'properties' => array()
    );

    if ($hasCountries || $citiesType || $hasRegions) {
        $feature['properties']['political'] = getPolitical($dbh, $isShell, $footprint, $citiesType, $hasRegions);
    }

    if ($hasGeophysical) {
        $feature['properties']['geophysical'] = getGeophysical($dbh, $isShell, $footprint);
    }

    if ($hasLandCover) {
        $feature['properties']['landCover'] = getLandCover($dbh, $isShell, $footprint);
    }

    if ($hasPopulation && GPW2PGSQL_URL) {
        $gpwResult = getRemoteData(GPW2PGSQL_URL . urlencode($footprint), null);
        if ($gpwResult !== "") {
            $feature['properties']['population'] = trim($gpwResult);
        }

    }

    // Add feature array to feature collection array
    array_push($geojson['features'], $feature);

    // Set HTTP header if no shell
    if (!$isShell) {
        header("Pragma: no-cache");
        header("Expires: 0");
        header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
        header("Cache-Control: no-cache, must-revalidate");
        header("Content-type: application/json; charset=utf-8");
    }

    if ($output === 'pretty') {
        print_r($geojson);
    }
    else if ($output === 'sql') {
        echo "SQL output is not yet implemented ! Use 'pretty' or 'json' instead\n";
    }
    else {
        echo json_encode($geojson);
    }

}

// Clean exit
if ($dbh) {
    pg_close($dbh);
}
if ($dbhSource) {
    pg_close($dbhSource);
}

exit(0);