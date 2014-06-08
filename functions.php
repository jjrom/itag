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
        return isset($classNames[$code]) ? $classNames[$code] : "";
    }

    return "";
}

function getCountryName($code) {
    
    $countryNames = array(
        'AD' => 'Andorra',
        'AF' => 'Afghanistan',
        'AFG' => 'Afghanistan',
        'AG' => 'Antigua and Barbuda',
        'AI' => 'Anguilla',
        'AL' => 'Albania',
        'ALB' => 'Albania',
        'AN' => 'Netherlands Antilles',
        'AO' => 'Angola',
        'AGO' => 'Angola',
        'AQ' => 'Antarctica',
        'AR' => 'Argentina',
        'AE' => 'United Arab Emirates',
        'ARE' => 'United Arab Emirates',
        'ARG' => 'Argentina',
        'AM' => 'Armenia',
        'ARM' => 'Armenia',
        'AS' => 'American Samoa',
        'AT' => 'Austria',
        'ATA' => 'Antarctica',
        'ATF' => 'French Southern and Antarctic Lands',
        'AU' => 'Australia',
        'AUS' => 'Australia',
        'AUT' => 'Austria',
        'AW' => 'Aruba',
        'AX' => 'Aland Islands',
        'AZ' => 'Azerbaijan',
        'AZE' => 'Azerbaijan',
        'BA' => 'Bosnia and Herzegovina',
        'BB' => 'Barbados',
        'BD' => 'Bangladesh',
        'BDI' => 'Burundi',
        'BE' => 'Belgium',
        'BEL' => 'Belgium',
        'BEN' => 'Benin',
        'BF' => 'Burkina Faso',
        'BFA' => 'Burkina Faso',
        'BG' => 'Bulgaria',
        'BGD' => 'Bangladesh',
        'BGR' => 'Bulgaria',
        'BH' => 'Bahrain',
        'BHS' => 'Bahamas',
        'BI' => 'Burundi',
        'BIH' => 'Bosnia and Herzegovina',
        'BJ' => 'Benin',
        'BL' => 'Saint Barthelemy',
        'BLR' => 'Belarus',
        'BLZ' => 'Belize',
        'BM' => 'Bermuda',
        'BN' => 'Brunei',
        'BO' => 'Bolivia',
        'BOL' => 'Bolivia',
        'BQ' => 'Bonaire, Saint Eustatius and Saba ',
        'BR' => 'Brazil',
        'BRA' => 'Brazil',
        'BRN' => 'Brunei',
        'BS' => 'Bahamas',
        'BT' => 'Bhutan',
        'BTN' => 'Bhutan',
        'BV' => 'Bouvet Island',
        'BW' => 'Botswana',
        'BWA' => 'Botswana',
        'BY' => 'Belarus',
        'BZ' => 'Belize',
        'CA' => 'Canada',
        'CAF' => 'Central African Republic',
        'CAN' => 'Canada',
        'CC' => 'Cocos Islands',
        'CD' => 'Democratic Republic of the Congo',
        'CF' => 'Central African Republic',
        'CG' => 'Republic of the Congo',
        'CH' => 'Switzerland',
        'CHE' => 'Switzerland',
        'CHL' => 'Chile',
        'CHN' => 'China',
        'CI' => 'Ivory Coast',
        'CIV' => 'Ivory Coast',
        'CK' => 'Cook Islands',
        'CL' => 'Chile',
        'CM' => 'Cameroon',
        'CMR' => 'Cameroon',
        'CN' => 'China',
        'CO' => 'Colombia',
        'COD' => 'Democratic Republic of the Congo',
        'COG' => 'Republic of the Congo',
        'COL' => 'Colombia',
        'CR' => 'Costa Rica',
        'CRI' => 'Costa Rica',
        'CS' => 'Serbia and Montenegro',
        'CU' => 'Cuba',
        'CUB' => 'Cuba',
        'CV' => 'Cape Verde',
        'CW' => 'Curacao',
        'CX' => 'Christmas Island',
        'CY' => 'Cyprus',
        'CYN' => 'Northern Cyprus',
        'CYP' => 'Cyprus',
        'CZ' => 'Czech Republic',
        'CZE' => 'Czech Republic',
        'DE' => 'Germany',
        'DEU' => 'Germany',
        'DJ' => 'Djibouti',
        'DJI' => 'Djibouti',
        'DK' => 'Denmark',
        'DM' => 'Dominica',
        'DNK' => 'Denmark',
        'DO' => 'Dominican Republic',
        'DOM' => 'Dominican Republic',
        'DZ' => 'Algeria',
        'DZA' => 'Algeria',
        'EC' => 'Ecuador',
        'ECU' => 'Ecuador',
        'EE' => 'Estonia',
        'EG' => 'Egypt',
        'EGY' => 'Egypt',
        'EH' => 'Western Sahara',
        'ER' => 'Eritrea',
        'ERI' => 'Eritrea',
        'ES' => 'Spain',
        'ESP' => 'Spain',
        'EST' => 'Estonia',
        'ET' => 'Ethiopia',
        'ETH' => 'Ethiopia',
        'FI' => 'Finland',
        'FIN' => 'Finland',
        'FJ' => 'Fiji',
        'FJI' => 'Fiji',
        'FK' => 'Falkland Islands',
        'FLK' => 'Falkland Islands',
        'FM' => 'Micronesia',
        'FO' => 'Faroe Islands',
        'FR' => 'France',
        'FRA' => 'France',
        'GA' => 'Gabon',
        'GAB' => 'Gabon',
        'GB' => 'United Kingdom',
        'GBR' => 'United Kingdom',
        'GD' => 'Grenada',
        'GE' => 'Georgia',
        'GEO' => 'Georgia',
        'GF' => 'French Guiana',
        'GG' => 'Guernsey',
        'GH' => 'Ghana',
        'GHA' => 'Ghana',
        'GI' => 'Gibraltar',
        'GIN' => 'Guinea',
        'GL' => 'Greenland',
        'GM' => 'Gambia',
        'GMB' => 'Gambia',
        'GN' => 'Guinea',
        'GNB' => 'Guinea-Bissau',
        'GNQ' => 'Equatorial Guinea',
        'GP' => 'Guadeloupe',
        'GQ' => 'Equatorial Guinea',
        'GR' => 'Greece',
        'GRC' => 'Greece',
        'GRL' => 'Greenland',
        'GS' => 'South Georgia and the South Sandwich Islands',
        'GT' => 'Guatemala',
        'GTM' => 'Guatemala',
        'GU' => 'Guam',
        'GUY' => 'Guyana',
        'GW' => 'Guinea-Bissau',
        'GY' => 'Guyana',
        'HK' => 'Hong Kong',
        'HM' => 'Heard Island and McDonald Islands',
        'HN' => 'Honduras',
        'HND' => 'Honduras',
        'HR' => 'Croatia',
        'HRV' => 'Croatia',
        'HT' => 'Haiti',
        'HTI' => 'Haiti',
        'HU' => 'Hungary',
        'HUN' => 'Hungary',
        'ID' => 'Indonesia',
        'IDN' => 'Indonesia',
        'IE' => 'Ireland',
        'IL' => 'Israel',
        'IM' => 'Isle of Man',
        'IN' => 'India',
        'IND' => 'India',
        'IO' => 'British Indian Ocean Territory',
        'IQ' => 'Iraq',
        'IR' => 'Iran',
        'IRL' => 'Ireland',
        'IRN' => 'Iran',
        'IRQ' => 'Iraq',
        'IS' => 'Iceland',
        'ISL' => 'Iceland',
        'ISR' => 'Israel',
        'IT' => 'Italy',
        'ITA' => 'Italy',
        'JAM' => 'Jamaica',
        'JE' => 'Jersey',
        'JM' => 'Jamaica',
        'JO' => 'Jordan',
        'JOR' => 'Jordan',
        'JP' => 'Japan',
        'JPN' => 'Japan',
        'KAZ' => 'Kazakhstan',
        'KE' => 'Kenya',
        'KEN' => 'Kenya',
        'KG' => 'Kyrgyzstan',
        'KGZ' => 'Kyrgyzstan',
        'KH' => 'Cambodia',
        'KHM' => 'Cambodia',
        'KI' => 'Kiribati',
        'KM' => 'Comoros',
        'KN' => 'Saint Kitts and Nevis',
        'KOR' => 'Korea',
        'KOS' => 'Kosovo',
        'KP' => 'North Korea',
        'KR' => 'South Korea',
        'KW' => 'Kuwait',
        'KWT' => 'Kuwait',
        'KY' => 'Cayman Islands',
        'KZ' => 'Kazakhstan',
        'LA' => 'Laos',
        'LAO' => 'Laos',
        'LB' => 'Lebanon',
        'LBN' => 'Lebanon',
        'LBR' => 'Liberia',
        'LBY' => 'Libya',
        'LC' => 'Saint Lucia',
        'LI' => 'Liechtenstein',
        'LK' => 'Sri Lanka',
        'LKA' => 'Sri Lanka',
        'LR' => 'Liberia',
        'LS' => 'Lesotho',
        'LSO' => 'Lesotho',
        'LT' => 'Lithuania',
        'LTU' => 'Lithuania',
        'LU' => 'Luxembourg',
        'LUX' => 'Luxembourg',
        'LV' => 'Latvia',
        'LVA' => 'Latvia',
        'LY' => 'Libya',
        'MA' => 'Morocco',
        'MAR' => 'Morocco',
        'MC' => 'Monaco',
        'MD' => 'Moldova',
        'MDA' => 'Moldova',
        'MDG' => 'Madagascar',
        'ME' => 'Montenegro',
        'MEX' => 'Mexico',
        'MF' => 'Saint Martin',
        'MG' => 'Madagascar',
        'MH' => 'Marshall Islands',
        'MK' => 'Macedonia',
        'MKD' => 'Macedonia',
        'ML' => 'Mali',
        'MLI' => 'Mali',
        'MM' => 'Myanmar',
        'MMR' => 'Myanmar',
        'MN' => 'Mongolia',
        'MNE' => 'Montenegro',
        'MNG' => 'Mongolia',
        'MO' => 'Macao',
        'MOZ' => 'Mozambique',
        'MP' => 'Northern Mariana Islands',
        'MQ' => 'Martinique',
        'MR' => 'Mauritania',
        'MRT' => 'Mauritania',
        'MS' => 'Montserrat',
        'MT' => 'Malta',
        'MU' => 'Mauritius',
        'MV' => 'Maldives',
        'MW' => 'Malawi',
        'MWI' => 'Malawi',
        'MX' => 'Mexico',
        'MY' => 'Malaysia',
        'MYS' => 'Malaysia',
        'MZ' => 'Mozambique',
        'NA' => 'Namibia',
        'NAM' => 'Namibia',
        'NC' => 'New Caledonia',
        'NCL' => 'New Caledonia',
        'NE' => 'Niger',
        'NER' => 'Niger',
        'NF' => 'Norfolk Island',
        'NG' => 'Nigeria',
        'NGA' => 'Nigeria',
        'NI' => 'Nicaragua',
        'NIC' => 'Nicaragua',
        'NL' => 'Netherlands',
        'NLD' => 'Netherlands',
        'NO' => 'Norway',
        'NOR' => 'Norway',
        'NP' => 'Nepal',
        'NPL' => 'Nepal',
        'NR' => 'Nauru',
        'NU' => 'Niue',
        'NZ' => 'New Zealand',
        'NZL' => 'New Zealand',
        'OM' => 'Oman',
        'OMN' => 'Oman',
        'PA' => 'Panama',
        'PAK' => 'Pakistan',
        'PAN' => 'Panama',
        'PE' => 'Peru',
        'PER' => 'Peru',
        'PF' => 'French Polynesia',
        'PG' => 'Papua New Guinea',
        'PH' => 'Philippines',
        'PHL' => 'Philippines',
        'PK' => 'Pakistan',
        'PL' => 'Poland',
        'PM' => 'Saint Pierre and Miquelon',
        'PN' => 'Pitcairn',
        'PNG' => 'Papua New Guinea',
        'POL' => 'Poland',
        'PR' => 'Puerto Rico',
        'PRI' => 'Puerto Rico',
        'PRK' => 'North Korea',
        'PRT' => 'Portugal',
        'PRY' => 'Paraguay',
        'PS' => 'Palestinian Territory',
        'PSX' => 'Palestine',
        'PT' => 'Portugal',
        'PW' => 'Palau',
        'PY' => 'Paraguay',
        'QA' => 'Qatar',
        'QAT' => 'Qatar',
        'RE' => 'Reunion',
        'RO' => 'Romania',
        'ROU' => 'Romania',
        'RS' => 'Serbia',
        'RU' => 'Russia',
        'RUS' => 'Russia',
        'RW' => 'Rwanda',
        'RWA' => 'Rwanda',
        'SA' => 'Saudi Arabia',
        'SAH' => 'Western Sahara',
        'SAU' => 'Saudi Arabia',
        'SB' => 'Solomon Islands',
        'SC' => 'Seychelles',
        'SD' => 'Sudan',
        'SDN' => 'Sudan',
        'SDS' => 'South Sudan',
        'SE' => 'Sweden',
        'SEN' => 'Senegal',
        'SG' => 'Singapore',
        'SH' => 'Saint Helena',
        'SI' => 'Slovenia',
        'SJ' => 'Svalbard and Jan Mayen',
        'SK' => 'Slovakia',
        'SL' => 'Sierra Leone',
        'SLB' => 'Solomon Islands',
        'SLE' => 'Sierra Leone',
        'SLV' => 'El Salvador',
        'SM' => 'San Marino',
        'SN' => 'Senegal',
        'SO' => 'Somalia',
        'SOL' => 'Somaliland',
        'SOM' => 'Somalia',
        'SR' => 'Suriname',
        'SRB' => 'Serbia',
        'SS' => 'South Sudan',
        'ST' => 'Sao Tome and Principe',
        'SUR' => 'Suriname',
        'SV' => 'El Salvador',
        'SVK' => 'Slovakia',
        'SVN' => 'Slovenia',
        'SWE' => 'Sweden',
        'SWZ' => 'Swaziland',
        'SX' => 'Sint Maarten',
        'SY' => 'Syria',
        'SYR' => 'Syria',
        'SZ' => 'Swaziland',
        'TC' => 'Turks and Caicos Islands',
        'TCD' => 'Chad',
        'TD' => 'Chad',
        'TF' => 'French Southern Territories',
        'TG' => 'Togo',
        'TGO' => 'Togo',
        'TH' => 'Thailand',
        'THA' => 'Thailand',
        'TJ' => 'Tajikistan',
        'TJK' => 'Tajikistan',
        'TK' => 'Tokelau',
        'TKM' => 'Turkmenistan',
        'TL' => 'East Timor',
        'TLS' => 'Timor-Leste',
        'TM' => 'Turkmenistan',
        'TN' => 'Tunisia',
        'TO' => 'Tonga',
        'TR' => 'Turkey',
        'TT' => 'Trinidad and Tobago',
        'TTO' => 'Trinidad and Tobago',
        'TUN' => 'Tunisia',
        'TUR' => 'Turkey',
        'TV' => 'Tuvalu',
        'TW' => 'Taiwan',
        'TWN' => 'Taiwan',
        'TZ' => 'Tanzania',
        'TZA' => 'Tanzania',
        'UA' => 'Ukraine',
        'UG' => 'Uganda',
        'UGA' => 'Uganda',
        'UKR' => 'Ukraine',
        'UM' => 'United States Minor Outlying Islands',
        'URY' => 'Uruguay',
        'US' => 'United States',
        'USA' => 'United States',
        'UY' => 'Uruguay',
        'UZ' => 'Uzbekistan',
        'UZB' => 'Uzbekistan',
        'VA' => 'Vatican',
        'VC' => 'Saint Vincent and the Grenadines',
        'VE' => 'Venezuela',
        'VEN' => 'Venezuela',
        'VG' => 'British Virgin Islands',
        'VI' => 'U.S. Virgin Islands',
        'VN' => 'Vietnam',
        'VNM' => 'Vietnam',
        'VU' => 'Vanuatu',
        'VUT' => 'Vanuatu',
        'WF' => 'Wallis and Futuna',
        'WS' => 'Samoa',
        'XK' => 'Kosovo',
        'YE' => 'Yemen',
        'YEM' => 'Yemen',
        'YT' => 'Mayotte',
        'ZA' => 'South Africa',
        'ZAF' => 'South Africa',
        'ZM' => 'Zambia',
        'ZMB' => 'Zambia',
        'ZW' => 'Zimbabwe',
        'ZWE' => 'Zimbabwe'
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

    /*
     * Do not process if $footprint is more than 2x2 degrees
     */
    $cropOrigin = cropOriginGLC2000(bbox($footprint));
    if ($cropOrigin['xsize'] * $cropOrigin['ysize'] > 50176) {
        return null;
    }
    
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
        if (isset($out[$product['dn']])) {
            $out[$product['dn']] += $product['area'];
        }
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
        try {
            $results = pg_query($dbh, $query);
        }
        catch (Exception $e) {
            echo '-- ' . $e->getMessage() . "\n";
            return array();
        }
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
            $query = "SELECT region, name as state, adm0_a3 as isoa3, st_area(st_intersection(geom, ST_GeomFromText('" . $footprint . "', 4326))) as area, st_area(ST_GeomFromText('" . $footprint . "', 4326)) as totalarea FROM worldadm1level WHERE st_intersects(geom, ST_GeomFromText('" . $footprint . "', 4326)) ORDER BY area DESC";
        } else {
            $query = "SELECT region, name as state, adm0_a3 as isoa3 FROM worldadm1level WHERE st_intersects(geom, ST_GeomFromText('" . $footprint . "', 4326)) ORDER BY region";
        }
        $results = pg_query($dbh, $query);
        $regions = array();
        $states = array();
        if (!$results) {
            error($dbh, $isShell, "\nFATAL : database connection error\n\n");
        }
        while ($element = pg_fetch_assoc($results)) {
            
            if ($options['hierarchical']) {
                
                /*
                 * Set regions under countries
                 */
                if ($keywords['countries']) {
                    foreach (array_keys($result['continents']['Europe']['countries']) as $country) {
                        if ($result['continents']['Europe']['countries'][$country]['name'] === getCountryName($element['isoa3'])) {
                            
                            if (!$result['continents']['Europe']['countries'][$country]['regions']) {
                                $result['continents']['Europe']['countries'][$country]['regions'] = array();
                            }
                            
                            if (!$result['continents']['Europe']['countries'][$country]['regions'][$element['region']]) {
                                $result['continents']['Europe']['countries'][$country]['regions'][$element['region']] = array(
                                    'states' => array()
                                );
                            }
                            if ($options['ordered']) {
                                array_push($result['continents']['Europe']['countries'][$country]['regions'][$element['region']]['states'], array('name' => $element['state'], 'pcover' => percentage($element['area'], $element['totalarea'])));
                            }
                            else {
                                array_push($result['continents']['Europe']['countries'][$country]['regions'][$element['region']]['states'], array('name' => $element['state']));
                            }
                            
                            break;
                        }
                    }
                }
              
            } else {
                if ($element['region']) {
                    $regions[$element['region']] = $element['region'];
                }
                array_push($states, $element['state']);
            }
        }
        if (count($regions) > 0) {
            $result['regions'] = array_keys($regions);
        }
        if (count($states) > 0) {
            $result['states'] = $states;
        }
    }

    // Cities
    if ($keywords['cities']) {
        if ($keywords['cities'] === "all") {

            /*
             * Do not process if $footprint is more than 2x2 degrees
             */
            $cropOrigin = cropOriginGLC2000(bbox($footprint));
            if ($cropOrigin['xsize'] * $cropOrigin['ysize'] > 50176) {
                return $result;
            }
            $query = "SELECT g.name, g.countryname as country, d.region as region, d.name as state, d.adm0_a3 as isoa3 FROM geoname g LEFT OUTER JOIN worldadm1level d ON g.country || '.' || g.admin2 = d.gn_a1_code WHERE st_intersects(g.geom, ST_GeomFromText('" . $footprint . "', 4326)) ORDER BY g.name";
            } else {
            $query = "SELECT g.name, g.countryname as country, d.region as region, d.name as state, d.adm0_a3 as isoa3 FROM geoname g LEFT OUTER JOIN worldadm1level d ON g.country || '.' || g.admin2 = d.gn_a1_code WHERE st_intersects(g.geom, ST_GeomFromText('" . $footprint . "', 4326)) and g.fcode in ('PPLA','PPLC') ORDER BY g.name";
        }
        $results = pg_query($dbh, $query);
        $cities = array();
        if (!$results) {
            error($dbh, $isShell, "\nFATAL : database connection error\n\n");
        }
        while ($element = pg_fetch_assoc($results)) {
            if ($keywords['countries'] && $options['hierarchical']) {
                if (!$keywords['regions']) {
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
                    foreach (array_keys($result['continents']) as $continent) {
                        foreach (array_keys($result['continents'][$continent]['countries']) as $country) {
                            if ($result['continents'][$continent]['countries'][$country]['name'] === $element['country']) {
                                foreach (array_keys($result['continents'][$continent]['countries'][$country]['regions'][$element['region']]['states']) as $state) {
                                    if ($result['continents'][$continent]['countries'][$country]['regions'][$element['region']]['states'][$state]['name'] === $element['state']) {
                                        if (!$result['continents'][$continent]['countries'][$country]['regions'][$element['region']]['states'][$state]['cities']) {
                                            $result['continents'][$continent]['countries'][$country]['regions'][$element['region']]['states'][$state]['cities'] = array();
                                        }
                                        array_push($result['continents'][$continent]['countries'][$country]['regions'][$element['region']]['states'][$state]['cities'], $element['name']);
                                    }
                                }
                            }
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
        $val = "NULL";
        if ($name) {
            if (!is_string($name)) {
                if ($name['pcover']) {
                    $val = $name['pcover'];
                }
                $name = $name['name'];
            }
            if (isset($output) && $output === 'copy') {
                echo $identifier . "\t" . $name . "\t" . $type . "\n";
            }
            else if (isset($output) && $output === 'hstore') {
                $key = trim($name);
                $splitted = split(' ', $key);
                $quote = count($splitted) > 1 ? '"' : '';
                $hstore = "'" . $quote . $type . ':' . strtolower($key) . $quote . " => " . $val . "'";
                echo "UPDATE " . $tableName . " SET " . $hstoreColumn . " = " . $hstoreColumn . " || " . $hstore . " WHERE " . $identifierColumn . "='" . $identifier . "';\n";
            }
            else {
                echo "INSERT INTO " . $hstoreColumn . " VALUES ('" . $identifier . "','" . $name . "','" . $type . "');\n";
            }
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
