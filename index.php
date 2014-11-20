<?php

/*
 * iTag
 *
 * iTag - Semantic enhancement of Earth Observation data
 *
 * Copyright 2013 Jérôme Gasperi <https://github.com/jjrom>
 * 
 * jerome[dot]gasperi[at]gmail[dot]com
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
 * Autoload controllers and modules
 */

function autoload($className) {
    foreach (array('include/', 'include/iTag/') as $current_dir) {
        $path = $current_dir . sprintf('%s.php', $className);
        if (file_exists($path)) {
            include $path;
            return;
        }
    }
}

/**
 * Return true if $str value is true, 1 or yes
 * Return false otherwise
 * 
 * @param string $str
 */
function trueOrFalse($str) {
    
    if (!$str) {
        return false;
    }
    
    if (strtolower($str) === 'true' || strtolower($str) === 'yes') {
        return true;
    }
    
    return false;
    
}

/**
 * Format a flat JSON string to make it more human-readable
 *
 * Code modified from https://github.com/GerHobbelt/nicejson-php
 * 
 * @param string $json The original JSON string to process
 *        When the input is not a string it is assumed the input is RAW
 *        and should be converted to JSON first of all.
 * @return string Indented version of the original JSON string
 */
function json_format($json, $pretty) {

    /*
     * No pretty print - easy part
     */
    if (!$pretty) {
        if (!is_string($json)) {
            return json_encode($json);
        }
        return $json;
    }

    if (!is_string($json)) {
        if (phpversion() && phpversion() >= 5.4) {
            return json_encode($json, JSON_PRETTY_PRINT);
        }
        $json = json_encode($json);
    }
    $result = '';
    $pos = 0;               // indentation level
    $strLen = strlen($json);
    $indentStr = "\t";
    $newLine = "\n";
    $prevChar = '';
    $outOfQuotes = true;

    for ($i = 0; $i < $strLen; $i++) {
        // Grab the next character in the string
        $char = substr($json, $i, 1);

        // Are we inside a quoted string?
        if ($char == '"' && $prevChar != '\\') {
            $outOfQuotes = !$outOfQuotes;
        }
        // If this character is the end of an element,
        // output a new line and indent the next line
        else if (($char == '}' || $char == ']') && $outOfQuotes) {
            $result .= $newLine;
            $pos--;
            for ($j = 0; $j < $pos; $j++) {
                $result .= $indentStr;
            }
        }
        // eat all non-essential whitespace in the input as we do our own here and it would only mess up our process
        else if ($outOfQuotes && false !== strpos(" \t\r\n", $char)) {
            continue;
        }

        // Add the character to the result string
        $result .= $char;
        // always add a space after a field colon:
        if ($char == ':' && $outOfQuotes) {
            $result .= ' ';
        }

        // If the last character was the beginning of an element,
        // output a new line and indent the next line
        if (($char == ',' || $char == '{' || $char == '[') && $outOfQuotes) {
            $result .= $newLine;
            if ($char == '{' || $char == '[') {
                $pos++;
            }
            for ($j = 0; $j < $pos; $j++) {
                $result .= $indentStr;
            }
        }
        $prevChar = $char;
    }

    return $result;
}

spl_autoload_register('autoload');

ob_start();
header('HTTP/1.1 OK 200');
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');
try {
    
    /*
     * Read config.php configuration file
     */
    $configFile = realpath(dirname(__FILE__)) . '/include/config.php';
    if (!file_exists($configFile)) {
        throw new Exception(__METHOD__ . 'Missing mandatory configuration file', 500);
    }
    $config = include($configFile);
    
    /*
     * User request
     */
    if($_SERVER['REQUEST_METHOD'] == 'GET') {
        $http_param = $_REQUEST;
    }
    else if ($_SERVER['REQUEST_METHOD'] == 'PUT') {
        parse_str(file_get_contents("php://input"), $http_param);
    }
    $options = array(
        'countries' => trueOrFalse($http_param['countries']),
        'continents' => trueOrFalse($http_param['continents']),
        'cities' => isset($http_param['cities']) ? $http_param['cities'] : null,
        'geophysical' => trueOrFalse($http_param['geophysical']),
        'population' => trueOrFalse($http_param['population']),
        'landcover' => trueOrFalse($http_param['landcover']),
        'regions' => trueOrFalse($http_param['regions']),
        'french' => trueOrFalse($http_param['french']),
        'hierarchical' => trueOrFalse($http_param['hierarchical']),
        'ordered' => trueOrFalse($http_param['ordered']),
    );
    $footprint = isset($http_param['footprint']) ? $http_param['footprint'] : null;
    if (!$footprint) {
        throw new Exception('Missing mandatory footprint', 500);
    }
    
    /*
     * Launch iTag
     */
    $itag = new iTag($config['database']);
    echo json_format($itag->tag($footprint, $options), trueOrFalse($http_param['pretty']));
    
} catch (Exception $e) {
    echo $e->getMessage();
}
ob_end_flush(); 
