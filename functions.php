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

/*
 * Includes
 */
include_once 'config/config.php';

/*
 * Stop program on error
 */
function error($dbh, $isShell, $message) {

    if ($dbh) {
        pg_close($dbh);
    }

    if ($isShell) {
        echo $message;
    } else {
        header($_SERVER['SERVER_PROTOCOL'] . ' 500 Internal Server Error', true, 500);
    }

    exit();
}

/**
 * Set the proxy if needed
 * @param <type> $url Input url to proxify
 */
function initCurl($url) {

    /**
     * Init curl
     */
    $curl = curl_init();

    /**
     * If url is on the same domain name server
     * as _msprowser application, it is accessed directly
     * (i.e. no use of CURL proxy)
     */
    if ((substr($url, 0, 16) != "http://localhost") && (stristr($url, DOMAIN) === FALSE)) {
        if (USE_PROXY) {
            curl_setopt($curl, CURLOPT_PROXY, PROXY_URL);
            curl_setopt($curl, CURLOPT_PROXYPORT, PROXY_PORT);
            curl_setopt($curl, CURLOPT_PROXYUSERPWD, PROXY_USER . ":" . PROXY_PASSWORD);
        }
    }

    return $curl;
}

/**
 * Get Remote data from url using curl
 * @param <String> $url : input url to send GET request
 * @param <String> $useragent : useragent modification
 *
 * @return either a stringarray containing data and info if $info is set to true
 */
function getRemoteData($url, $useragent) {
    if (!empty($url)) {
        $curl = initCurl($url);
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, TRUE);
        if ($useragent != null) {
            curl_setopt($curl, CURLOPT_USERAGENT, $useragent);
        }
        $theData = curl_exec($curl);
        curl_close($curl);
        return $theData;
    }
    return "";
}

/**
 *
 * Returns GeoJSON geometry from a WKT POLYGON
 *
 * Example of WKT POLYGON :
 *     POLYGON((-180.0044642857 89.9955356663,-180.0044642857 87.9955356727,-178.0044642921 87.9955356727,-178.0044642921 89.9955356663,-180.0044642857 89.9955356663))
 *
 * @param <string> $wkt : WKT
 *
 */
function wktPolygon2GeoJSONGeometry($wkt) {
    $rep = array("(", ")", "multi", "polygon");
    $pairs = preg_split('/,/', str_replace($rep, "", strtolower($wkt)));
    $linestring = array();
    for ($i = 0; $i < count($pairs); $i++) {
        $coords = preg_split('/ /', trim($pairs[$i]));
        $x = floatval($coords[0]);
        $y = floatval($coords[1]);
        array_push($linestring, array($x, $y));
    }

    return array(
        'type' => "Polygon",
        'coordinates' => array($linestring)
    );
}

/**
 * Return percentage of $part regarding $total
 * @param <float> $part
 * @param <float> $total
 * @return <float>
 */
function percentage($part, $total) {
    return floor(10000 * ($part / $total)) / 100;
}

/*
 * 22 landuse classes (GLC2000)
 *
  1=>"Tree Cover, broadleaved, evergreen",
  2=>"Tree Cover, broadleaved, deciduous, closed",
  3=>"Tree Cover, broadleaved, deciduous, open",
  4=>"Tree Cover, needle-leaved, evergreen",
  5=>"Tree Cover, needle-leaved, deciduous",
  6=>"Tree Cover, mixed leaf type",
  7=>"Tree Cover, regularly flooded, fresh  water (& brackish)",
  8=>"Tree Cover, regularly flooded, saline water, (daily variation of water level)",
  9=>"Mosaic: Tree cover / Other natural vegetation",
  10=>"Tree Cover, burnt",
  11=>"Shrub Cover, closed-open, evergreen",
  12=>"Shrub Cover, closed-open, deciduous",
  13=>"Herbaceous Cover, closed-open",
  14=>"Sparse Herbaceous or sparse Shrub Cover",
  15=>"Regularly flooded Shrub and/or Herbaceous Cover",
  16=>"Cultivated and managed areas",
  17=>"Mosaic: Cropland / Tree Cover / Other natural vegetation",
  18=>"Mosaic: Cropland / Shrub or Grass Cover",
  19=>"Bare Areas",
  20=>"Water Bodies (natural & artificial)",
  21=>"Snow and Ice (natural & artificial)",
  22=>"Artificial surfaces and associated areas"
 *
 * 8 Global landuse created from above GLC2000 classes
 *
  100=>Artificial (22)
  200=>Cultivated (15 + 16 + 17 + 18)
  310=>Forests (1 + 2 + 3 + 4 + 5 + 6)
  320=>Herbaceous (9 + 11 + 12 + 13)
  330=>Deserts (10 + 14 + 19)
  335=>Snow and ice (21)
  400=>Flooded (7 + 8)
  500=>Water (20);
 */

/**
 *
 * Return a random table name
 *
 * @param <integer> $length : length of the table name
 * @return string : random table name
 *
 */
function getTableName($length) {
    $chars = "abcdefghijkmnopqrstuvwxyz";
    $i = 0;
    $name = "";
    while ($i <= $length) {
        $name .= $chars{mt_rand(0, strlen($chars) - 1)};
        $i++;
    }
    return '_' . $name;
}

/**
 *
 * Returns bounding box [ulx, uly, lrx, lry] from a WKT
 *
 * ULx,ULy
 *    +------------------+
 *    |                  |
 *    |                  |
 *    |                  |
 *    |                  |
 *    +------------------+
 *                     LRx,LRy
 *
 * Example of WKT POLYGON :
 *     POLYGON((-180.0044642857 89.9955356663,-180.0044642857 87.9955356727,-178.0044642921 87.9955356727,-178.0044642921 89.9955356663,-180.0044642857 89.9955356663))
 *
 * @param <string> $wkt : WKT
 * @return string : random table name
 *
 */
function bbox($wkt) {
    $ulx = 180.0;
    $uly = -90.0;
    $lrx = -180.0;
    $lry = 90.0;
    $rep = array("(", ")", "multi", "polygon", "point", "linestring");
    $pairs = preg_split('/,/', str_replace($rep, "", strtolower($wkt)));
    for ($i = 0; $i < count($pairs); $i++) {
        $coords = preg_split('/ /', trim($pairs[$i]));
        $x = floatval($coords[0]);
        $y = floatval($coords[1]);
        if ($x < $ulx) {
            $ulx = $x;
        } else if ($x > $lrx) {
            $lrx = $x;
        }
        if ($y > $uly) {
            $uly = $y;
        } else if ($y < $lry) {
            $lry = $y;
        }
    }

    return array('ulx' => $ulx, 'uly' => $uly, 'lrx' => $lrx, 'lry' => $lry);
}

/**
 * Return crop origin from Bounding Box for GLC2000 raster
 * 
 * @param <Array> $bbox : ['ulx', 'uly', 'lrx', 'lry']
 * @return <Array> ['x','y','xsize','ysize']
 */
function cropOriginGLC2000($bbox) {

    // GLC2000 full raster info
    $dx = 0.0089285714;
    $dy = -0.0089285714;
    $lon0 = -180.0;
    $lat0 = 89.99107138060005;

    return cropOrigin($bbox, $lon0, $lat0, $dx, $dy);
}

/**
 * Return crop origin from Bounding Box
 * 
 * @param <Array> $bbox : ['ulx', 'uly', 'lrx', 'lry']
 * @param <Float> $lon0
 * @param <Float> $lat0
 * @param <Float> $dx
 * @param <Float> $dy
 * @return <Array> ['x','y','xsize','ysize']
 */
function cropOrigin($bbox, $lon0, $lat0, $dx, $dy) {
    $x = floor(($bbox['ulx'] - $lon0) / $dx);
    $y = floor(($bbox['uly'] - $lat0) / $dy);
    $xsize = ceil(($bbox['lrx'] - $bbox['ulx']) / $dx);
    $ysize = ceil(($bbox['lry'] - $bbox['uly']) / $dy);
    return array('x' => $x, 'y' => $y, 'xsize' => $xsize, 'ysize' => $ysize);
}

/**
 * Return a Postgresql connection object
 * 
 * @param <String> $connectionString
 * @return <Object> $db
 * 
 */
function getPgDB($connectionString) {
    try {
        $db = pg_connect($connectionString);
        return $db;
    } catch (Exception $e) {
        return null;
    }
}

/**
 * Return GLC class name from input code
 * 
 * Note: GLC 2000 defines 22 landuse classes
 *
  1=>"Tree Cover, broadleaved, evergreen",
  2=>"Tree Cover, broadleaved, deciduous, closed",
  3=>"Tree Cover, broadleaved, deciduous, open",
  4=>"Tree Cover, needle-leaved, evergreen",
  5=>"Tree Cover, needle-leaved, deciduous",
  6=>"Tree Cover, mixed leaf type",
  7=>"Tree Cover, regularly flooded, fresh  water",
  8=>"Tree Cover, regularly flooded, saline water",
  9=>"Mosaic: Tree cover / Other natural vegetation",
  10=>"Tree Cover, burnt",
  11=>"Shrub Cover, closed-open, evergreen",
  12=>"Shrub Cover, closed-open, deciduous",
  13=>"Herbaceous Cover, closed-open",
  14=>"Sparse Herbaceous or sparse Shrub Cover",
  15=>"Regularly flooded Shrub and/or Herbaceous Cover",
  16=>"Cultivated and managed areas",
  17=>"Mosaic: Cropland / Tree Cover / Other natural vegetation",
  18=>"Mosaic: Cropland / Shrub or Grass Cover",
  19=>"Bare Areas",
  20=>"Water Bodies",
  21=>"Snow and Ice",
  22=>"Artificial surfaces and associated areas"
 * 
 * @param <Integer> $code
 * @return <String>
 * 
 */
function getGLCClassName($code) {

    // GLC has 22 landuse classes
    $classNames = array(
        1 => "Tree Cover, broadleaved, evergreen",
        2 => "Tree Cover, broadleaved, deciduous, closed",
        3 => "Tree Cover, broadleaved, deciduous, open",
        4 => "Tree Cover, needle-leaved, evergreen",
        5 => "Tree Cover, needle-leaved, deciduous",
        6 => "Tree Cover, mixed leaf type",
        7 => "Tree Cover, regularly flooded, fresh  water",
        8 => "Tree Cover, regularly flooded, saline water",
        9 => "Mosaic: Tree cover / Other natural vegetation",
        10 => "Tree Cover, burnt",
        11 => "Shrub Cover, closed-open, evergreen",
        12 => "Shrub Cover, closed-open, deciduous",
        13 => "Herbaceous Cover, closed-open",
        14 => "Sparse Herbaceous or sparse Shrub Cover",
        15 => "Regularly flooded Shrub and/or Herbaceous Cover",
        16 => "Cultivated and managed areas",
        17 => "Mosaic: Cropland / Tree Cover / Other natural vegetation",
        18 => "Mosaic: Cropland / Shrub or Grass Cover",
        19 => "Bare Areas",
        20 => "Water Bodies",
        21 => "Snow and Ice",
        22 => "Artificial surfaces and associated areas",
        100 => "Artificial",
        200 => "Cultivated",
        310 => "Forests",
        320 => "Herbaceous",
        330 => "Deserts",
        335 => "Snow and ice",
        400 => "Flooded",
        500 => "Water"
    );

    if (is_int($code) && $code > 0) {
        return $classNames[$code] ? $classNames[$code] : "";
    }

    return "";
}

/**
 * 
 * Compute land cover from input WKT footprint
 * 
 * @param {DatabaseConnection} $dbh
 * 
 */
function getLandCover($dbh, $isShell, $footprint) {

    // Create temporary name for processing
    $tmpTable = getTableName(6);

    // Crop GLC2000 raster
    $cropOrigin = cropOriginGLC2000(bbox($footprint));
       
    $srcWin = $cropOrigin['x'] . ' ' . $cropOrigin['y'] . ' ' . $cropOrigin['xsize'] . ' ' . $cropOrigin['ysize'];

    // Avoid crashing the machine with big crops (2x2 square degrees)
    if (!isShell) {
        if ($cropOrigin['xsize'] * $cropOrigin['ysize'] > 50176) {
            error($dbh, $isShell, "\nFATAL : input footprint should be smaller than 2x2 square degrees\n\n");
        }
    }
    
    // Crop GLC2000 raster to $srcWin 
    exec(GDAL_TRANSLATE_PATH . " -of GTiff -srcwin " . $srcWin . " -a_srs EPSG:4326 " . GLC2000_TIFF . " /tmp/" . $tmpTable . ".tif");

    // In case of crashing crop - polygon is outside bounds for example
    if (!file_exists("/tmp/" . $tmpTable . ".tif")) {
        error($dbh, $isShell, "\nFATAL : glc2000 crop error\n\n");
    }

    // Polygonize extracted raster within temporary table $tmpTable
    exec(GDAL_POLYGONIZE_PATH . ' /tmp/' . $tmpTable . '.tif -f "PostgreSQL" PG:"host=' . DB_HOST . ' user=' . DB_USER . ' password=' . DB_PASSWORD . ' dbname=' . DB_NAME . '" ' . $tmpTable . ' 2>&1');
    unlink("/tmp/" . $tmpTable . ".tif");

    // Crop data
    $geom = "ST_GeomFromText('" . $footprint . "', 4326)";
    $query = "SELECT dn as dn, st_area($geom) as totalarea, st_area(st_intersection(wkb_geometry, $geom)) as area FROM $tmpTable WHERE st_intersects(wkb_geometry, $geom)";
    $results = pg_query($dbh, $query);
    if (!$results) {
        error($dbh, $isShell, "\nFATAL : database connection error\n\n");
    }

    // Store results in $out array
    $out = array();
    for ($i = 1; $i <= 22; $i++) {
        $out[$i] = 0;
    }
    while ($product = pg_fetch_assoc($results)) {
        $out[$product['dn']] += $product['area'];
        $totalarea = $product['totalarea'];
    }

    // Remove temporary table
    pg_query($dbh, "DROP TABLE $tmpTable");

    // Compute parent classes
    $parent = array();
    $parent[100] = $out[22];
    $parent[200] = $out[15] + $out[16] + $out[17] + $out[18];
    $parent[310] = $out[1] + $out[2] + $out[3] + $out[4] + $out[5] + $out[6];
    $parent[320] = $out[9] + $out[11] + $out[12] + $out[13];
    $parent[330] = $out[10] + $out[14] + $out[19];
    $parent[335] = $out[21];
    $parent[400] = $out[7] + $out[8];
    $parent[500] = $out[20];

    // Get the 3 main landuse
    arsort($parent);
    $landUse = array();
    $count = 0;
    foreach ($parent as $key => $val) {
        $count++;
        if ($val !== 0 && percentage($val, $totalarea) > 20) {
            array_push($landUse, getGLCClassName($key));
        }
        if ($count > 2) {
            break;
        }
    }

    /*
     * Add feature
     */
    $result = array(
        'area' => $totalarea,
        'landUse' => join(LIST_SEPARATOR, $landUse),
        'landUseDetails' => array()
    );

    foreach ($out as $key => $val) {
        if ($val !== 0) {
            array_push($result['landUseDetails'], array('type' => getGLCClassName($key), 'code' => $key, 'percentage' => percentage($val, $totalarea)));
        }
    }

    return $result;
}

/**
 * 
 * Generic keywords returning function
 * 
 * @param {DatabaseConnection} $dbh
 * 
 */
function getKeywords($dbh, $isShell, $tableName, $columnName, $footprint, $order = null) {
    $orderBy = "";
    if (isset($order)) {
        $orderBy = " ORDER BY " . $order;
    }
    
    $query = "SELECT distinct(" . $columnName . ") FROM " . $tableName . " WHERE st_intersects(geom, ST_GeomFromText('" . $footprint . "', 4326))" . $orderBy;
    $results = pg_query($dbh, $query);
    $keywords = array();
    if (!$results) {
        error($dbh, $isShell, "\nFATAL : database connection error\n\n");
    }
    while ($result = pg_fetch_assoc($results)) {
        array_push($keywords, $result[$columnName]);
    }
    
    return $keywords;
}

/**
 * 
 * Compute intersected politicals information (i.e. continent, countries, cities)
 * from input WKT footprint
 * 
 * @param {DatabaseConnection} $dbh
 * 
 */
function getPolitical($dbh, $isShell, $footprint, $citiesType, $hasRegions) {

    $result = array();
    
    // Continents and countries
    $query = "SELECT name as name, continent as continent FROM countries WHERE st_intersects(geom, ST_GeomFromText('" . $footprint . "', 4326))";
    $results = pg_query($dbh, $query);
    $countries = array();
    $continents = array();
    if (!$results) {
        error($dbh, $isShell, "\nFATAL : database connection error\n\n");
    }
    while ($element = pg_fetch_assoc($results)) {
        if (isset($element['continent']) && $element['continent']) {
            array_push($continents, $element['continent']);
        }
        if (isset($element['continent']) && $element['continent']) {
            array_push($countries, $element['name']);
        }
    }
    if (count($countries) > 0) {
        $result['countries'] = join(LIST_SEPARATOR, $countries);
    }
    if (count($continents) > 0) {
        $result['continents'] = join(LIST_SEPARATOR, $continents);
    }
    
    // Regions
    if ($hasRegions) {
        $regions = getKeywords($dbh, $isShell, "deptsfrance", "nom_region", $footprint, "nom_region");
        if (count($regions) > 0) {
            $result['regions'] = join(LIST_SEPARATOR, $regions);
        }
        $depts = getKeywords($dbh, $isShell, "deptsfrance", "nom_dept", $footprint, "nom_dept");
        if (count($depts) > 0) {
            $result['departements'] = join(LIST_SEPARATOR, $depts);
        }
    }
    
    // Cities
    if ($citiesType) {
        $cities = getKeywords($dbh, $isShell, $citiesType === "all" ? "geoname" : "cities", "name", $footprint, "name");
        if (count($cities) > 0) {
            $result['cities'] = join(LIST_SEPARATOR, $cities);
        }
    }
    
    return $result;
}

/**
 * 
 * Compute intersected geophysical information (i.e. plates, faults, volcanoes, etc.)
 * from input WKT footprint
 * 
 * @param {DatabaseConnection} $dbh
 * 
 */
function getGeophysical($dbh, $isShell, $footprint) {

    $result = array();
    
    // Plates
    $plates = getKeywords($dbh, $isShell, "plates", "name", $footprint);
    if (count($plates) > 0) {
        $result['plates'] = join(LIST_SEPARATOR, $plates);
    }
    
    // Faults
    $faults = getKeywords($dbh, $isShell, "faults", "type", $footprint);
    if (count($faults) > 0) {
        $result['faults'] = join(LIST_SEPARATOR, $faults);
    }
    
    // Volcanoes
    $volcanoes = getKeywords($dbh, $isShell, "volcanoes", "name", $footprint);
    if (count($volcanoes) > 0) {
       $result['volcanoes'] = join(LIST_SEPARATOR, $volcanoes);
    }
    
    // Glaciers
    $glaciers = getKeywords($dbh, $isShell, "glaciers", "objectid", $footprint);
    if (count($glaciers) > 0) {
       $result['hasGlaciers'] = true;
    }
    
    return $result;
}

function json_encode_utf8($struct) {
   return preg_replace("/\\\\u([a-f0-9]{4})/e", "iconv('UCS-4LE','UTF-8',pack('V', hexdec('U$1')))", json_encode($struct));
}

/*
 * Output result to stdin
 */
function tostdin($identifier, $properties, $type, $tableName, $identifierColumn, $hstoreColumn, $output) {
    
    $arr2 = split(LIST_SEPARATOR, $properties);
    
    for ($i = 0, $l = count($arr2); $i < $l; $i++) {
        if ($arr2[$i]) {
            if (isset($output) && $output === 'copy') {
                echo $identifier . "\t" . $arr2[$i] . "\t" . $type ."\n";
            } else if (isset($output) && $output === 'hstore') {
                $key = trim($arr2[$i]);
                $splitted = split(' ', $key);
                $quote = count($splitted) > 1 ? '"' : '';
                $hstore = "'" . $quote . strtolower($key) . $quote . " => " . $type . "'";
                echo "UPDATE " . $tableName . " SET " . $hstoreColumn . " = " . $hstoreColumn . " || " . $hstore . " WHERE " . $identifierColumn . "='" . $identifier . "';\n";
            } else {
                echo "INSERT INTO " . $hstoreColumn . " VALUES ('" . $identifier . "','" . $arr2[$i] . "','" . $type . "');\n";
            }
        }
    }
}