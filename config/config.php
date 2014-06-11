<?php

/*
 * iTag database connection info
 */
define("DB_HOST", "localhost");
define("DB_PORT", "5432");
define("DB_USER", "jrom");
define("DB_PASSWORD", "postgres");
define("DB_NAME", "itag");

/*
 * In case of you use a proxy to connect to external services
 */
define("USE_PROXY", false);
define("PROXY_URL", "");
define("PROXY_PORT", "");
define("PROXY_USER", "");
define("PROXY_PASSWORD", "");
define("DOMAIN", "localhost");

/*
 * Mandatory for Land Cover computation
 */
define("GDAL_TRANSLATE_PATH", "/usr/local/bin/gdal_translate");
define("GDAL_POLYGONIZE_PATH", "/usr/local/bin/gdal_polygonize.py");
define("GLC2000_TIFF", "/Users/jrom/data/geography/glc2000/glc2000_v1_1.tif");

/*
 * Mandatory for Population computation count
 */
define("GPW2PGSQL_URL", "http://mapshup.info/gpw2pgsql/ws/population.php?format=txt&polygon=");
