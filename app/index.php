<?php
/*
 * Copyright 2013 Jérôme Gasperi
 *
 * Licensedunder the Apache License,
 * version 2.0 (the "License"); you may not use this file except in compliance
 * with the License. You may obtain a copy of the License at:
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS, WITHOUT
 * WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied. See the
 * License for the specific language governing permissions and limitations
 * under the License.
 */

spl_autoload_register(function ($class) {

    $dirs = array(
        'include/',
        'include/iTag/',
        'include/iTag/Taggers/'
    );

    foreach ($dirs as $dir) {
        $src = $dir . $class . '.php';
        if (file_exists($src)) {
            return include $src;
        }
    }
});

/*
 * Read configuration from file...
 */
$configFile = '/etc/itag/config.php';
if (file_exists($configFile)) {
    return new iTagLauncher(include($configFile));
}

/*
 * ...or use default if not exist
 */
error_log('[WARNING] Config file ' . $configFile . ' not found - using default configuration');
return new iTagLauncher(array(
    "general" => array(
        "areaLimit" => 200000,
        "returnGeometries" => false,
        "geometryTolerance" => 0.1,
        "planet" => "earth"
    ),
    "database" => array(
        "dbname" => "itag",
        "host" => "itagdb",
        "port" => 5432,
        "user" => "itag",
        "password" => "itag"
    )
));
