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
  100=>Urban (22)
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
        9 => "Mosaic - Tree cover / Other natural vegetation",
        10 => "Tree Cover, burnt",
        11 => "Shrub Cover, closed-open, evergreen",
        12 => "Shrub Cover, closed-open, deciduous",
        13 => "Herbaceous Cover, closed-open",
        14 => "Sparse Herbaceous or sparse Shrub Cover",
        15 => "Regularly flooded Shrub and/or Herbaceous Cover",
        16 => "Cultivated and managed areas",
        17 => "Mosaic - Cropland / Tree Cover / Other natural vegetation",
        18 => "Mosaic - Cropland / Shrub or Grass Cover",
        19 => "Bare Areas",
        20 => "Water Bodies",
        21 => "Snow and Ice",
        22 => "Artificial surfaces and associated areas",
        100 => "Urban",
        200 => "Cultivated",
        310 => "Forest",
        320 => "Herbaceous",
        330 => "Desert",
        335 => "Snow and ice",
        400 => "Flooded",
        500 => "Water"
    );

    if (is_int($code) && $code > 0) {
        return $classNames[$code] ? $classNames[$code] : "";
    }

    return "";
}

function getCountryName($code) {

    $countryNames = array(
        'AF' => 'Afghanistan',
        'AX' => 'Aland Islands',
        'AL' => 'Albania',
        'DZ' => 'Algeria',
        'AS' => 'American Samoa',
        'AD' => 'Andorra',
        'AO' => 'Angola',
        'AI' => 'Anguilla',
        'AQ' => 'Antarctica',
        'AG' => 'Antigua and Barbuda',
        'AR' => 'Argentina',
        'AM' => 'Armenia',
        'AW' => 'Aruba',
        'AU' => 'Australia',
        'AT' => 'Austria',
        'AZ' => 'Azerbaijan',
        'BS' => 'Bahamas',
        'BH' => 'Bahrain',
        'BD' => 'Bangladesh',
        'BB' => 'Barbados',
        'BY' => 'Belarus',
        'BE' => 'Belgium',
        'BZ' => 'Belize',
        'BJ' => 'Benin',
        'BM' => 'Bermuda',
        'BT' => 'Bhutan',
        'BO' => 'Bolivia',
        'BQ' => 'Bonaire, Saint Eustatius and Saba ',
        'BA' => 'Bosnia and Herzegovina',
        'BW' => 'Botswana',
        'BV' => 'Bouvet Island',
        'BR' => 'Brazil',
        'IO' => 'British Indian Ocean Territory',
        'VG' => 'British Virgin Islands',
        'BN' => 'Brunei',
        'BG' => 'Bulgaria',
        'BF' => 'Burkina Faso',
        'BI' => 'Burundi',
        'KH' => 'Cambodia',
        'CM' => 'Cameroon',
        'CA' => 'Canada',
        'CV' => 'Cape Verde',
        'KY' => 'Cayman Islands',
        'CF' => 'Central African Republic',
        'TD' => 'Chad',
        'CL' => 'Chile',
        'CN' => 'China',
        'CX' => 'Christmas Island',
        'CC' => 'Cocos Islands',
        'CO' => 'Colombia',
        'KM' => 'Comoros',
        'CK' => 'Cook Islands',
        'CR' => 'Costa Rica',
        'HR' => 'Croatia',
        'CU' => 'Cuba',
        'CW' => 'Curacao',
        'CY' => 'Cyprus',
        'CZ' => 'Czech Republic',
        'CD' => 'Democratic Republic of the Congo',
        'DK' => 'Denmark',
        'DJ' => 'Djibouti',
        'DM' => 'Dominica',
        'DO' => 'Dominican Republic',
        'TL' => 'East Timor',
        'EC' => 'Ecuador',
        'EG' => 'Egypt',
        'SV' => 'El Salvador',
        'GQ' => 'Equatorial Guinea',
        'ER' => 'Eritrea',
        'EE' => 'Estonia',
        'ET' => 'Ethiopia',
        'FK' => 'Falkland Islands',
        'FO' => 'Faroe Islands',
        'FJ' => 'Fiji',
        'FI' => 'Finland',
        'FR' => 'France',
        'GF' => 'French Guiana',
        'PF' => 'French Polynesia',
        'TF' => 'French Southern Territories',
        'GA' => 'Gabon',
        'GM' => 'Gambia',
        'GE' => 'Georgia',
        'DE' => 'Germany',
        'GH' => 'Ghana',
        'GI' => 'Gibraltar',
        'GR' => 'Greece',
        'GL' => 'Greenland',
        'GD' => 'Grenada',
        'GP' => 'Guadeloupe',
        'GU' => 'Guam',
        'GT' => 'Guatemala',
        'GG' => 'Guernsey',
        'GN' => 'Guinea',
        'GW' => 'Guinea-Bissau',
        'GY' => 'Guyana',
        'HT' => 'Haiti',
        'HM' => 'Heard Island and McDonald Islands',
        'HN' => 'Honduras',
        'HK' => 'Hong Kong',
        'HU' => 'Hungary',
        'IS' => 'Iceland',
        'IN' => 'India',
        'ID' => 'Indonesia',
        'IR' => 'Iran',
        'IQ' => 'Iraq',
        'IE' => 'Ireland',
        'IM' => 'Isle of Man',
        'IL' => 'Israel',
        'IT' => 'Italy',
        'CI' => 'Ivory Coast',
        'JM' => 'Jamaica',
        'JP' => 'Japan',
        'JE' => 'Jersey',
        'JO' => 'Jordan',
        'KZ' => 'Kazakhstan',
        'KE' => 'Kenya',
        'KI' => 'Kiribati',
        'XK' => 'Kosovo',
        'KW' => 'Kuwait',
        'KG' => 'Kyrgyzstan',
        'LA' => 'Laos',
        'LV' => 'Latvia',
        'LB' => 'Lebanon',
        'LS' => 'Lesotho',
        'LR' => 'Liberia',
        'LY' => 'Libya',
        'LI' => 'Liechtenstein',
        'LT' => 'Lithuania',
        'LU' => 'Luxembourg',
        'MO' => 'Macao',
        'MK' => 'Macedonia',
        'MG' => 'Madagascar',
        'MW' => 'Malawi',
        'MY' => 'Malaysia',
        'MV' => 'Maldives',
        'ML' => 'Mali',
        'MT' => 'Malta',
        'MH' => 'Marshall Islands',
        'MQ' => 'Martinique',
        'MR' => 'Mauritania',
        'MU' => 'Mauritius',
        'YT' => 'Mayotte',
        'MX' => 'Mexico',
        'FM' => 'Micronesia',
        'MD' => 'Moldova',
        'MC' => 'Monaco',
        'MN' => 'Mongolia',
        'ME' => 'Montenegro',
        'MS' => 'Montserrat',
        'MA' => 'Morocco',
        'MZ' => 'Mozambique',
        'MM' => 'Myanmar',
        'NA' => 'Namibia',
        'NR' => 'Nauru',
        'NP' => 'Nepal',
        'NL' => 'Netherlands',
        'AN' => 'Netherlands Antilles',
        'NC' => 'New Caledonia',
        'NZ' => 'New Zealand',
        'NI' => 'Nicaragua',
        'NE' => 'Niger',
        'NG' => 'Nigeria',
        'NU' => 'Niue',
        'NF' => 'Norfolk Island',
        'KP' => 'North Korea',
        'MP' => 'Northern Mariana Islands',
        'NO' => 'Norway',
        'OM' => 'Oman',
        'PK' => 'Pakistan',
        'PW' => 'Palau',
        'PS' => 'Palestinian Territory',
        'PA' => 'Panama',
        'PG' => 'Papua New Guinea',
        'PY' => 'Paraguay',
        'PE' => 'Peru',
        'PH' => 'Philippines',
        'PN' => 'Pitcairn',
        'PL' => 'Poland',
        'PT' => 'Portugal',
        'PR' => 'Puerto Rico',
        'QA' => 'Qatar',
        'CG' => 'Republic of the Congo',
        'RE' => 'Reunion',
        'RO' => 'Romania',
        'RU' => 'Russia',
        'RW' => 'Rwanda',
        'BL' => 'Saint Barthelemy',
        'SH' => 'Saint Helena',
        'KN' => 'Saint Kitts and Nevis',
        'LC' => 'Saint Lucia',
        'MF' => 'Saint Martin',
        'PM' => 'Saint Pierre and Miquelon',
        'VC' => 'Saint Vincent and the Grenadines',
        'WS' => 'Samoa',
        'SM' => 'San Marino',
        'ST' => 'Sao Tome and Principe',
        'SA' => 'Saudi Arabia',
        'SN' => 'Senegal',
        'RS' => 'Serbia',
        'CS' => 'Serbia and Montenegro',
        'SC' => 'Seychelles',
        'SL' => 'Sierra Leone',
        'SG' => 'Singapore',
        'SX' => 'Sint Maarten',
        'SK' => 'Slovakia',
        'SI' => 'Slovenia',
        'SB' => 'Solomon Islands',
        'SO' => 'Somalia',
        'ZA' => 'South Africa',
        'GS' => 'South Georgia and the South Sandwich Islands',
        'KR' => 'South Korea',
        'SS' => 'South Sudan',
        'ES' => 'Spain',
        'LK' => 'Sri Lanka',
        'SD' => 'Sudan',
        'SR' => 'Suriname',
        'SJ' => 'Svalbard and Jan Mayen',
        'SZ' => 'Swaziland',
        'SE' => 'Sweden',
        'CH' => 'Switzerland',
        'SY' => 'Syria',
        'TW' => 'Taiwan',
        'TJ' => 'Tajikistan',
        'TZ' => 'Tanzania',
        'TH' => 'Thailand',
        'TG' => 'Togo',
        'TK' => 'Tokelau',
        'TO' => 'Tonga',
        'TT' => 'Trinidad and Tobago',
        'TN' => 'Tunisia',
        'TR' => 'Turkey',
        'TM' => 'Turkmenistan',
        'TC' => 'Turks and Caicos Islands',
        'TV' => 'Tuvalu',
        'VI' => 'U.S. Virgin Islands',
        'UG' => 'Uganda',
        'UA' => 'Ukraine',
        'AE' => 'United Arab Emirates',
        'GB' => 'United Kingdom',
        'US' => 'United States',
        'UM' => 'United States Minor Outlying Islands',
        'UY' => 'Uruguay',
        'UZ' => 'Uzbekistan',
        'VU' => 'Vanuatu',
        'VA' => 'Vatican',
        'VE' => 'Venezuela',
        'VN' => 'Vietnam',
        'WF' => 'Wallis and Futuna',
        'EH' => 'Western Sahara',
        'YE' => 'Yemen',
        'ZM' => 'Zambia',
        'ZW' => 'Zimbabwe'
    );
    
    return $countryNames[$code] ? $countryNames[$code] : $code;
}

/**
 * 
 * Compute land cover from input WKT footprint
 * 
 * @param {DatabaseConnection} $dbh
 * 
 */
function getLandCover($dbh, $isShell, $footprint, $options) {

    // Crop data
    $geom = "ST_GeomFromText('" . $footprint . "', 4326)";
    $query = "SELECT dn as dn, st_area($geom) as totalarea, st_area(st_intersection(wkb_geometry, $geom)) as area FROM landcover WHERE st_intersects(wkb_geometry, $geom)";
    $results = pg_query($dbh, $query);
    if (!$results) {
        echo "-- Error for $footprint - skip\n";
        return null;
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
        $pcover = percentage($val, $totalarea);
        if ($val !== 0 && $pcover > 20) {
            if ($options['ordered']) {
                array_push($landUse, array('name' => getGLCClassName($key), 'pcover' => $pcover));
            } else {
                array_push($landUse, getGLCClassName($key));
            }
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
        'landUse' => $landUse,
        'landUseDetails' => array()
    );

    foreach ($out as $key => $val) {
        if ($val !== 0) {
            array_push($result['landUseDetails'], array('name' => getGLCClassName($key), 'code' => $key, 'pcover' => min(100, percentage($val, $totalarea))));
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
 * @param {boolean} $isShell - true if launch by shell script, false is launch from webserver
 * @param {string} $footprint - WKT POLYGON
 * @param {array} $keywords - list of keywords class to produce
 * @param {array} $options - processing options
 *                  {
 *                      'hierarchical' => // if true return keywords by descending area of intersection
 *                  }
 * 
 */
function getPolitical($dbh, $isShell, $footprint, $keywords, $options) {

    $result = array();

    // Continents
    if ($keywords['continents'] && !$keywords['countries']) {
        if ($options['ordered']) {
            $query = "SELECT continent as continent, st_area(st_intersection(geom, ST_GeomFromText('" . $footprint . "', 4326))) as area FROM continents WHERE st_intersects(geom, ST_GeomFromText('" . $footprint . "', 4326)) ORDER BY area DESC";
            $results = pg_query($dbh, $query);
            $continents = array();
            if (!$results) {
                error($dbh, $isShell, "\nFATAL : database connection error\n\n");
            }
            while ($element = pg_fetch_assoc($results)) {
                array_push($continents, $element['continent']);
            }
        } else {
            $continents = getKeywords($dbh, $isShell, "continents", "continent", $footprint, "continent");
        }
        if (count($continents) > 0) {
            $result['continents'] = $continents;
        }
    }

    // Countries
    if ($keywords['countries']) {

        // Continents and countries
        if ($options['ordered']) {
            $query = "SELECT name as name, continent as continent, st_area(st_intersection(geom, ST_GeomFromText('" . $footprint . "', 4326))) as area, st_area(ST_GeomFromText('" . $footprint . "', 4326)) as totalarea FROM countries WHERE st_intersects(geom, ST_GeomFromText('" . $footprint . "', 4326)) ORDER BY area DESC";
        } else {
            $query = "SELECT name as name, continent as continent FROM countries WHERE st_intersects(geom, ST_GeomFromText('" . $footprint . "', 4326))";
        }
        $results = pg_query($dbh, $query);
        $countries = array();
        $continents = array();
        if (!$results) {
            error($dbh, $isShell, "\nFATAL : database connection error\n\n");
        }
        while ($element = pg_fetch_assoc($results)) {
            if ($options['hierarchical']) {
                if (!$continents[$element['continent']]) {
                    $continents[$element['continent']] = array(
                        'countries' => array()
                    );
                }
                if ($options['ordered']) {
                    array_push($continents[$element['continent']]['countries'], array('name' => $element['name'], 'pcover' => percentage($element['area'], $element['totalarea'])));
                } else {
                    array_push($continents[$element['continent']]['countries'], array('name' => $element['name']));
                }
            } else {
                $continents[$element['continent']] = $element['continent'];
                if ($options['ordered']) {
                    array_push($countries, array('name' => $element['name'], 'pcover' => percentage($element['area'], $element['totalarea'])));
                } else {
                    array_push($countries, array('name' => $element['name']));
                }
            }
        }
        if (count($continents) > 0) {
            if ($options['hierarchical']) {
                $result['continents'] = $continents;
            } else {
                $result['countries'] = $countries;
                $result['continents'] = array_keys($continents);
            }
        }
    }

    // Regions
    if ($keywords['regions']) {
        if ($options['ordered']) {
            $query = "SELECT nom_region as region, nom_dept as departement, st_area(st_intersection(geom, ST_GeomFromText('" . $footprint . "', 4326))) as area, st_area(ST_GeomFromText('" . $footprint . "', 4326)) as totalarea FROM deptsfrance WHERE st_intersects(geom, ST_GeomFromText('" . $footprint . "', 4326)) ORDER BY area DESC";
        } else {
            $query = "SELECT nom_region as region, nom_dept as departement FROM deptsfrance WHERE st_intersects(geom, ST_GeomFromText('" . $footprint . "', 4326)) ORDER BY nom_region";
        }
        $results = pg_query($dbh, $query);
        $regions = array();
        $departements = array();
        if (!$results) {
            error($dbh, $isShell, "\nFATAL : database connection error\n\n");
        }
        while ($element = pg_fetch_assoc($results)) {
            if ($options['hierarchical']) {
                if (!$regions[$element['region']]) {
                    $regions[$element['region']] = array(
                        'departements' => array()
                    );
                }
                if ($options['ordered']) {
                    array_push($regions[$element['region']]['departements'], array('name' => $element['departement'], 'pcover' => percentage($element['area'], $element['totalarea'])));
                }
                else {
                    array_push($regions[$element['region']]['departements'], array('name' => $element['departement']));
                }
            } else {
                $regions[$element['region']] = $element['region'];
                array_push($departements, $element['departement']);
            }
        }
        if (count($regions) > 0) {
            if ($options['hierarchical']) {
                
                // Set regions under France
                if ($keywords['countries']) {
                    foreach (array_keys($result['continents']['Europe']['countries']) as $country) {
                        if ($result['continents']['Europe']['countries'][$country]['name'] === 'France') {
                            $result['continents']['Europe']['countries'][$country]['regions'] = $regions;
                            break;
                        }
                    }
                }
                else {
                    $result['regions'] = $regions;
                }
            }
            else {
                $result['regions'] = array_keys($regions);
                $result['departements'] = $departements;
            }
        }
    }

    // Cities
    if ($keywords['cities']) {
        if ($keywords['cities'] === "all") {
            $query = "SELECT name, countryname as country FROM geoname WHERE st_intersects(geom, ST_GeomFromText('" . $footprint . "', 4326)) ORDER BY name";
        } else {
            $query = "SELECT name, country FROM cities WHERE st_intersects(geom, ST_GeomFromText('" . $footprint . "', 4326)) ORDER BY name";
        }
        $results = pg_query($dbh, $query);
        $cities = array();
        if (!$results) {
            error($dbh, $isShell, "\nFATAL : database connection error\n\n");
        }
        while ($element = pg_fetch_assoc($results)) {
            if ($keywords['countries'] && $options['hierarchical']) {
                foreach (array_keys($result['continents']) as $continent) {
                    foreach (array_keys($result['continents'][$continent]['countries']) as $country) {
                        if ($result['continents'][$continent]['countries'][$country]['name'] === $element['country']) {
                            if (!$result['continents'][$continent]['countries'][$country]['cities']) {
                                $result['continents'][$continent]['countries'][$country]['cities'] = array();
                            }
                            array_push($result['continents'][$continent]['countries'][$country]['cities'], $element['name']);
                        }
                    }
                }
            } else {
                array_push($cities, $element['name']);
            }
        }

        if (count($cities) > 0) {
            $result['cities'] = $cities;
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
        $result['plates'] = $plates;
    }

    // Faults
    $faults = getKeywords($dbh, $isShell, "faults", "type", $footprint);
    if (count($faults) > 0) {
        $result['faults'] = $faults;
    }

    // Volcanoes
    $volcanoes = getKeywords($dbh, $isShell, "volcanoes", "name", $footprint);
    if (count($volcanoes) > 0) {
        $result['volcanoes'] = $volcanoes;
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

    for ($i = 0, $l = count($properties); $i < $l; $i++) {

        $name = $properties[$i];

        if ($name) {
            if (!is_string($name)) {
                if ($name['pcover']) {
                    $mod = ';' . $name['pcover'];
                }
                $name = $name['name'];
            }
            if (isset($output) && $output === 'copy') {
                echo $identifier . "\t" . $name . "\t" . $type . $mod . "\n";
            } else if (isset($output) && $output === 'hstore') {
                $key = trim($name);
                $splitted = split(' ', $key);
                $quote = count($splitted) > 1 ? '"' : '';
                $hstore = "'" . $quote . strtolower($key) . $quote . " => " . $type . $mod . "'";
                echo "UPDATE " . $tableName . " SET " . $hstoreColumn . " = " . $hstoreColumn . " || " . $hstore . " WHERE " . $identifierColumn . "='" . $identifier . "';\n";
            } else {
                echo "INSERT INTO " . $hstoreColumn . " VALUES ('" . $identifier . "','" . $name . "','" . $type . $mod . "');\n";
            }
        }
    }
}