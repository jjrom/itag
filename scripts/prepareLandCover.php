<?php

/*
 * iTag - prepare landcover database from GLC2000 TIF image
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
//error_reporting(E_PARSE);
// Includes
include_once realpath(dirname(__FILE__)) . '/../config/config.php';
include_once realpath(dirname(__FILE__)) . '/../functions.php';

function polygonize($footprint, $dbh) {
    
    echo ' --> Polygonize ' . $footprint . "\n";
        
    $tifName = '/tmp/' . md5(microtime()) . '.tif';
    $tmpTable = 'tmptable';
    
    // Drop tmpTable - bug in gdla_polygonize.py ?
    pg_query($dbh, 'DROP TABLE ' . $tmpTable);    

    // Crop GLC2000 raster
    $cropOrigin = cropOriginGLC2000(bbox($footprint));
    $srcWin = $cropOrigin['x'] . ' ' . $cropOrigin['y'] . ' ' . $cropOrigin['xsize'] . ' ' . $cropOrigin['ysize'];

    // Crop GLC2000 raster to $srcWin 
    exec(GDAL_TRANSLATE_PATH . " -of GTiff -srcwin " . $srcWin . " -a_srs EPSG:4326 " . GLC2000_TIFF . ' ' . $tifName);

    // In case of crashing crop - polygon is outside bounds for example
    if (!file_exists($tifName)) {
        echo "  --> WARNING : glc2000 crop error\n";
        continue;
    }

    // Polygonize extracted raster within temporary table $tmpTable
    exec(GDAL_POLYGONIZE_PATH . ' ' . $tifName . ' -f "PostgreSQL" PG:"host=' . DB_HOST . ' user=' . DB_USER . ' password=' . DB_PASSWORD . ' dbname=' . DB_NAME . '" ' . $tmpTable . ' 2>&1');
    
    pg_query($dbh, 'INSERT INTO landcover(wkb_geometry,dn) SELECT wkb_geometry,dn FROM ' . $tmpTable);
    unlink($tifName);
    
}

// Get db connection
$dbh = getPgDB("host=" . DB_HOST . " dbname=" . DB_NAME . " user=" . DB_USER . " password=" . DB_PASSWORD);
pg_set_client_encoding($dbh, "UTF8");

// Produce the grid
$gridSize = 2;
// Longitude from -180 to 180
for ($lon = -180; $lon <= 180; $lon = $lon + $gridSize) {
    // Latitude from -60 to 90
    for ($lat = -60; $lat <= 90; $lat = $lat + $gridSize) {
        $lat2 = $lat + $gridSize;
        $lon2 = $lon + $gridSize;
        $str = $lon . " " . $lat . "," . $lon . " " . $lat2 . "," . $lon2 . " " . $lat2 . "," . $lon2 . " " . $lat . "," . $lon . " " . $lat;
        polygonize("POLYGON((" . $str . "))", $dbh);
    }
}
