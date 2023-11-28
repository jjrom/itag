#!/command/with-contenv php

<?php

    /*
     * Read configuration from file...
     */
    $configFile = '/etc/itag/config.php';
    if ( !file_exists($configFile)) {
        exit(1);
    }

    $configFull = include($configFile);
    $config = $configFull['database'];

    if (! isset($config) )  {
        exit(1);
    }
    
    try {

        echo '[SQL] Connecting to ' . $config['dbname'];

        $dbInfo = array(
            'dbname=' . $config['dbname'],
            'user=' . $config['user'],
            'password=' . $config['password']
        );

        /*
         * If host is specified, then TCP/IP connection is used
         * Otherwise socket connection is used
         */
        if (isset($config['host'])) {
            $dbInfo[] = 'host=' . $config['host'];
            $dbInfo[] = 'port=' . ($config['port'] ?? '5432');
        }
        
        $dbh = @pg_connect(join(' ', $dbInfo));
        if (!$dbh) {
            echo "[WAIT] Database...";
            sleep(2);
            $dbh = @pg_connect(join(' ', $dbInfo));
        }
        
    } catch (Exception $e) {
        $dbh = false;
    }
    
    if ( !$dbh ) {
        echo '[ERROR] Cannot connect to database';
        exit(1);
    }

    try {

        // Handle core model
        $sqlFiles = glob('/itag-database-model/*.sql');
        for ($i = 0, $ii = count($sqlFiles); $i < $ii; $i++) {
            echo '[SQL] Process ' .  $sqlFiles[$i] . "\n";
            $results = pg_query($dbh, file_get_contents($sqlFiles[$i]));
            if (!$results) {
                throw new Exception();
            }
        }   

    } catch (Exception $e) {
        echo $e->getMessage();
    }