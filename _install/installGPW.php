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

//=============================================

// Includes
include_once '../include/config.php';

// Remove PHP NOTICE
error_reporting(E_PARSE);

// This application can only be called from a shell
if (!empty($_SERVER['SHELL'])) {
    $help = "\n## Usage : php gpw2pgsql.php -f <ascii_grid_file>\n\n    Note: the ascii_grid_file is one of glp15ag.asc/glp15ag15.asc/glp15ag30.asc/glp15ag60.asc files downloaded from http://sedac.ciesin.columbia.edu/data/set/gpw-v3-population-count-future-estimates/data-download\n\n";
    $options = getopt("f:h");
    foreach ($options as $option => $value) {
        if ($option === "f") {
            $gpwfile = $value;

            // Tablename is based on file name (e.g. "glp15ag15" for glp15ag15.asc file name)
            $tablename = substr(basename($gpwfile), 0, -4);
        }
        if ($option === "h") {
            echo $help;
            exit;
        }
    }

    // File is mandatory
    if (!file_exists($gpwfile)) {
        echo $help;
        exit;
    }
} else {
    exit(0);
}

// Connect to database
$dbh = getPgDB("host=" . DB_HOST . " dbname=" . DB_NAME . " user=" . DB_USER . " password=" . DB_PASSWORD);
if (!$dbh) {
    echo "\nFATAL : No connection to database\n\n";
    exit(0);
}

// Delete content of tablename
echo "### Delete table * " . $tablename . " * content\n";
pg_query($dbh, "CREATE SCHEMA IF NOT EXISTS gpw");
pg_query($dbh, "DELETE FROM gpw." . $tablename);
pg_query($dbh, "DROP INDEX gpw.footprint_" . $tablename . "_idx");
pg_query($dbh, "DROP INDEX gpw.pcount" . $tablename . "_idx");

// GPW ascii grid data format
// An ascii file - 6 first lines are header
// 
//      ncols         8640
//      nrows         3432
//      xllcorner     -180
//      yllcorner     -58
//      cellsize      0.0416666666667
//      NODATA_value  -9999
//      0 0 0 0 0 0 .....etc....     <--- Data start line 7
//          
// The first line of data is for (-180,-58) and the last one if for (180,85)
// (see http://sedac.uservoice.com/knowledgebase/articles/123459-i-downloaded-the-gpwv3-population-density-ascii-f)
//
$handle = fopen($gpwfile, "r");
if ($handle) {

    $count = -7;

    while (($line = fgets($handle)) !== false) {

        $count++;
        $rowpadded = str_pad($count, 4, "0", STR_PAD_LEFT);

        if ($count === -5) {
            $nrows = intval(rtrim(current(array_slice(preg_split('/\s+/', $line), 1, 1))));
            continue;
        }
        if ($count === -4) {
            $xllcorner = intval(rtrim(current(array_slice(preg_split('/\s+/', $line), 1, 1))));
            continue;
        }
        if ($count === -3) {
            $yllcorner = intval(rtrim(current(array_slice(preg_split('/\s+/', $line), 1, 1))));
            continue;
        }
        if ($count === -2) {
            $cellsize = floatval(rtrim(current(array_slice(preg_split('/\s+/', $line), 1, 1))));
            continue;
        }
        if ($count === -1) {
            $NODATA = intval(rtrim(current(array_slice(preg_split('/\s+/', $line), 1, 1))));
            continue;
        }
        
        // 
        // Data starts at line 7
        //  
        //   - They are strings of "ncols" floats
        //   - NODATA and 0 value are not processes
        //   - cells coordinates are built from column and row value
        //     assuming the first line start at (lat,lon) = yllcorner + (nrows * cellsize),
        // 
        //  +––––––+––––––+–––––+
        //  |      |      |     |
        //  |   0  | 23.3 | 456 |
        //  +––––––+––––––+–––––+
        //  |      |      |     |
        //  |   0  | 34.5 |-9999|
        //  +––––––+––––––+–––––+
        //
        $yulcorner = $yllcorner + (($nrows - 1) * $cellsize);
        if ($count > -1) {
            $values = explode(" ", $line);
            $ncols = count($values);
            $latmin = $yulcorner - ($count * $cellsize);
            $latmax = $latmin + $cellsize;
            echo "   Process row " . $count . "...\n";
            for ($i = 0; $i < $ncols; $i++) {
                $value = floatval(rtrim($values[$i]));
                if ($value <= 0.001 || $value == $NODATA) {
                    continue;
                }
                $lonmin = $xllcorner + ($i * $cellsize);
                $lonmax = $lonmin + $cellsize;
                $wkt = "POLYGON((" . implode(",", array($lonmin . " " . $latmin, $lonmin . " " . $latmax, $lonmax . " " . $latmax, $lonmax . " " . $latmin, $lonmin . " " . $latmin)) . "))";
                $gid = $rowpadded . str_pad($i, 4, "0", STR_PAD_LEFT);
                $query = "INSERT INTO gpw.$tablename (gid, pcount, footprint) VALUES ('" . $gid . "',floor(" . ($value + 0.5) . "), ST_GeometryFromText('" . $wkt . "', 4326));";
                pg_query($dbh, $query);
            }
        }
    }
} else {
    echo "\nFATAL : Cannot read file " . $gpwfile . "\n\n";
    exit(0);
}

echo "### Create spatial index on table * " . $tablename . " * \n";
pg_query("CREATE INDEX footprint_" . $tablename . "_idx ON gpw." . $tablename . " USING btree (footprint)");

echo "### Create population count index on table * " . $tablename . " * \n";
pg_query("CREATE INDEX pcount_" . $tablename . "_idx ON gpw." . $tablename . " (pcount)");

echo "\nData successfully stored within * " . $tablename . " * of " . DB_NAME . "database\n\n";
exit(0);