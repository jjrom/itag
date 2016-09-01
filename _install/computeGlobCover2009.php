#!/usr/bin/env php
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

/*
 * iTag - prepare landcover database from GlobCover2009 TIF image
 */

$help  = "## iTag GlobCover 2009 compute and installation\n\n Usage computeLandCover.php [options] -f <path to GlobCover TIF file>\n\n";
$help .= "OPTIONS:\n";
$help .= "   -H : postgres server hostname (default localhost)\n";
$help .= "   -d : iTag database name (default itag)\n";
$help .= "   -s : postgresql superuser (default postgres)\n";
$help .= "   -p : postgresql superuser password\n";
$help .= "\n";
$hostname = 'localhost';
$superuser = 'postgres';
$password = 'postgres';
$dbname = 'itag';
$options = getopt("f:hp:d:s:");
foreach ($options as $option => $value) {
    if ($option === "h") {
        echo $help;
        exit;
    }
    if ($option === "f") {
        $globcover = $value;
    }
    if ($option === "H") {
        $hostname = $value;
    }
    if ($option === "p") {
        $password = $value;
    }
    if ($option === "s") {
        $superuser = $value;
    }
    if ($option === "d") {
        $dbname = $value;
    }
}

$gdaltranslate = exec('which gdal_translate');
$gdalpolygonize = exec('which gdal_polygonize.py');
if (!isset($globcover)) {
    echo $help;
    exit;
}

// Get db connection
try {
    $dbh = pg_connect("host=" . $hostname . " dbname=" . $dbname . " user=" . $superuser . " password=" . $password . " port=5432");
} catch (Exception $e) {
    echo ' ERROR : no connection to database';
    exit;
}
pg_set_client_encoding($dbh, "UTF8");

$config = array(
    'translate' => $gdaltranslate,
    'polygonize' => $gdalpolygonize,
    'image' => $globcover,
    'dbname' => $dbname,
    'host' => $hostname,
    'user' => $superuser,
    'password' => $password
);

// Produce the grid
$gridSize = 2;
// Longitude from -180 to 180
for ($lon = -180; $lon <= 180; $lon = $lon + $gridSize) {
    // Latitude from -60 to 90
    for ($lat = -60; $lat <= 90; $lat = $lat + $gridSize) {
        $lat2 = $lat + $gridSize;
        $lon2 = $lon + $gridSize;
        $str = $lon . " " . $lat . "," . $lon . " " . $lat2 . "," . $lon2 . " " . $lat2 . "," . $lon2 . " " . $lat . "," . $lon . " " . $lat;
        polygonize("POLYGON((" . $str . "))", $dbh, $config, $config);
    }
}
echo ' --> done !';

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
 * Return crop origin from Bounding Box for GlobCover raster
 *
 * @param <Array> $bbox : ['ulx', 'uly', 'lrx', 'lry']
 * @return <Array> ['x','y','xsize','ysize']
 */
function cropOriginGlobCover($bbox) {

    // GlobCover full raster info
    $dx = 0.002777777777778;
    $dy = -0.002777777777778;
    $lon0 = -180.001388888888897;
    $lat0 = 90.001388888888883;

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

function polygonize($footprint, $dbh, $config) {

    echo ' --> Polygonize ' . $footprint . "\n";

    $tifName = '/tmp/' . md5(microtime()) . '.tif';
    $tmpTable = 'tmptable';

    // Crop GlobCover raster
    $cropOrigin = cropOriginGlobCover(bbox($footprint));
    $srcWin = $cropOrigin['x'] . ' ' . $cropOrigin['y'] . ' ' . $cropOrigin['xsize'] . ' ' . $cropOrigin['ysize'];

    // Crop GlobCover raster to $srcWin
    exec($config['translate'] . " -of GTiff -srcwin " . $srcWin . " -a_srs EPSG:4326 " . $config['image'] . ' ' . $tifName);

    // In case of crashing crop - polygon is outside bounds for example
    if (!file_exists($tifName)) {
        echo "  --> WARNING : globcover crop error\n";
        return;
    }

    // Polygonize extracted raster within temporary table $tmpTable
    exec($config['polygonize'] . ' ' . $tifName . ' -f "PostgreSQL" PG:"host=' . $config['host'] . ' user=' . $config['user'] . ' password=' . $config['password'] . ' dbname=' . $config['dbname'] . '" ' . $tmpTable . ' 2>&1');

    pg_query($dbh, 'INSERT INTO datasources.landcover2009(wkb_geometry,dn) SELECT wkb_geometry,dn FROM ' . $tmpTable);
    unlink($tifName);

    // Drop tmpTable - bug in gdla_polygonize.py ?
    pg_query($dbh, 'DROP TABLE ' . $tmpTable);

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
