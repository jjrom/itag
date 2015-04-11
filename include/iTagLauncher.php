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
class iTagLauncher {
    
    /**
     * Constructor
     * 
     * @param string $configFile
     */
    public function __construct($configFile) {
        $this->initialize($configFile);
        $this->tag();
    }
    
    /**
     * Tag metadata with taggers
     */
    private function tag() {
        $params = $this->getParams();
        $taggers = array();
        foreach (array_values($params['taggersList']) as $value) {
            $taggers[trim($value)] = array();
        }
        try {
            $this->answer($this->json_format($this->itag->tag($params['metadata'], $taggers)), 200);
        } catch (Exception $e) {
            $this->answer($this->json_format(array('ErrorMessage' => $e->getMessage(), 'ErrorCode' =>  $e->getCode())), $e->getCode());
        }   
        
    }
    /**
     * Read configuration file and set up iTag object
     * 
     * @param string $configFile
     */
    private function initialize($configFile) {
        try {
            if (!file_exists($configFile)) {
                throw new Exception(__METHOD__ . 'Missing mandatory configuration file', 500);
            }
            $config = include($configFile);
            
            /*
             * Instantiate iTag
             */
            $this->itag = new iTag($config['database'], $config['general']);
            
        } catch (Exception $e) {
            $this->answer($this->json_format(array('ErrorMessage' => $e->getMessage(), 'ErrorCode' =>  $e->getCode())), $e->getCode());
        }
    }
    
    /**
     * Return GET params
     */
    private function getParams() {
        $this->pretty = filter_input(INPUT_GET, '_pretty', FILTER_VALIDATE_BOOLEAN);
        return array(
            'metadata' => array(
                'footprint' => filter_input(INPUT_GET, 'footprint', FILTER_SANITIZE_STRING),
                'timestamp' => filter_input(INPUT_GET, 'timestamp', FILTER_SANITIZE_STRING)
            ),
            'taggersList' => explode(',', filter_input(INPUT_GET, 'taggers', FILTER_SANITIZE_STRING))
        );
    }
    
    /**
     * Stream HTTP result and exit
     */
    private function answer($response, $responseStatus) {
        
        /*
         * HTTP 1.1 headers
         */
        header('HTTP/1.1 ' . $responseStatus . ' ' . ($responseStatus === 200 ? 'OK' : 'Internal Server Error'));
        header('Cache-Control:  no-cache');
        header('Content-Type: application/json');
        
        /*
         * Set headers including cross-origin resource sharing (CORS)
         * http://en.wikipedia.org/wiki/Cross-origin_resource_sharing
         */
        $this->setCORSHeaders();
        
        /*
         * Stream data
         */
        echo $response;
        
    }
    
    /**
     * Set CORS headers (HTTP OPTIONS request)
     */
    private function setCORSHeaders() {

        $httpOrigin = filter_input(INPUT_SERVER, 'HTTP_ORIGIN', FILTER_SANITIZE_STRING);
        $httpRequestMethod = filter_input(INPUT_SERVER, 'HTTP_ACCESS_CONTROL_REQUEST_METHOD', FILTER_SANITIZE_STRING);
        $httpRequestHeaders = filter_input(INPUT_SERVER, 'HTTP_ACCESS_CONTROL_REQUEST_HEADERS', FILTER_SANITIZE_STRING);
        
        /*
         * Only set access to known servers
         */
        if (isset($httpOrigin)) {
            header('Access-Control-Allow-Origin: ' . $httpOrigin);
            header('Access-Control-Allow-Credentials: true');
            header('Access-Control-Max-Age: 3600');
        }

        /*
         * Control header are received during OPTIONS requests
         */
        if (isset($httpRequestMethod)) {
            header('Access-Control-Allow-Methods: GET, OPTIONS');
        }
        if (isset($httpRequestHeaders)) {
            header('Access-Control-Allow-Headers: ' . $httpRequestHeaders);
        }
    }
 
    /**
     * Format a flat JSON string to make it more human-readable
     *
     * @param array $json JSON as an array
     * 
     * @return string Indented version of the original JSON string
     */
    private function json_format($json) {

        /*
         * No pretty print - easy part
         */
        if (!$this->pretty) {
            return json_encode($json);
        }
        
        /*
         * Pretty print only works for PHP >= 5.4
         * Home made pretty print otherwise
         */
        if (phpversion() && phpversion() >= 5.4) {
            return json_encode($json, JSON_PRETTY_PRINT);
        }
        else {
             return $this->prettyPrintJsonString(json_encode($json));
        }
     
    }
    
    /**
     * Pretty print a json string
     * Code from https://github.com/ryanuber/projects/blob/master/PHP/JSON/jsonpp.php
     * 
     * @param string $json
     */
    private function prettyPrintJsonString($json, $istr = '   ') {
        $result = '';
        for ($p = $q = $i = 0; isset($json[$p]); $p++) {
            $json[$p] == '"' && ($p > 0 ? $json[$p - 1] : '') != '\\' && $q = !$q;
            if (!$q && strchr(" \t\n\r", $json[$p])) {
                continue;
            }
            if (strchr('}]', $json[$p]) && !$q && $i--) {
                strchr('{[', $json[$p - 1]) || $result .= "\n" . str_repeat($istr, $i);
            }
            $result .= $json[$p];
            if (strchr(',{[', $json[$p]) && !$q) {
                $i += strchr('{[', $json[$p]) === FALSE ? 0 : 1;
                strchr('}]', $json[$p + 1]) || $result .= "\n" . str_repeat($istr, $i);
            }
        }
        return $result;
    }

}