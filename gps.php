<?php

include_once("./config.php");
include_once("./lib/loader.php");

startMeasure('TOTAL');

include_once(DIR_MODULES . "application.class.php");
$db = new mysql(DB_HOST, '', DB_USER, DB_PASSWORD, DB_NAME);
include_once("./load_settings.php");

$latitude = gr('latitude');
$longitude = gr('longitude');
$deviceid = gr('deviceid');
$location = gr('location');
if ($location != '') {
    $tmp = explode(',', $location);
    $latitude = $tmp[0];
    $longitude = $tmp[1];
}

$op = gr('op');
if ($op != '') {
    $ok = 0;
    if ($op == 'zones') {
        $zones = SQLSelect("SELECT * FROM gpslocations");
        echo json_encode(array('RESULT' => array('ZONES' => $zones, 'STATUS' => 'OK')));
        $ok = 1;
    }
    $title = gr('title');
    if ($op == 'add_zone' && $latitude != '' && $longitude != '' && $title) {
        $range = gr('range', 'int');

        $sqlQuery = "SELECT *
                     FROM gpslocations
                    WHERE TITLE LIKE '" . DBSafe($title) . "'";

        $old_location = SQLSelect($sqlQuery);

        if ($old_location['ID'])
            $title .= ' (1)';

        if (!$range)
            $range = 200;

        $rec = array();
        $rec['TITLE'] = $title;
        $rec['LAT'] = $latitude;
        $rec['LON'] = $longitude;
        $rec['RANGE'] = $range;
        $rec['ID'] = SQLInsert('gpslocations', $rec);

        echo json_encode(array('RESULT' => array('STATUS' => 'OK')));
        $ok = 1;
    }

    $token = gr('token');
    if ($op == 'set_token' && $token && $deviceid) {
        $sqlQuery = "SELECT *
                     FROM gpsdevices
                    WHERE DEVICEID = '" . DBSafe($deviceid) . "'";

        $device = SQLSelectOne($sqlQuery);

        if (!$device['ID']) {
            $device = array();
            $device['DEVICEID'] = $deviceid;
            $device['TITLE'] = 'New GPS Device';
            $device['ID'] = SQLInsert('gpsdevices', $device);
        }

        $device['TOKEN'] = $token;
        SQLUpdate('gpsdevices', $device);
        $ok = 1;
    }

    if (!$ok)
        echo json_encode(array('RESULT' => array('STATUS' => 'FAIL')));

    $db->Disconnect();
    exit;
}

include_once("./modules/app_gpstrack/app_gpstrack.class.php");
$gpstrack = new app_gpstrack();
$gpstrack->processIncomingLogData();

$sqlQuery = "SELECT *, DATE_FORMAT(ADDED, '%H:%i') AS DAT
               FROM shouts
              ORDER BY ADDED DESC
              LIMIT 1";
$tmp = SQLSelectOne($sqlQuery);

if (!headers_sent()) {
    header("HTTP/1.0: 200 OK\n");
    header('Content-Type: text/html; charset=utf-8');
}

if (defined('BTRACED')) {
    echo "OK";
} elseif ($tmp['MESSAGE'] != '') {
    echo ' ' . $tmp['DAT'] . ' ' . transliterate($tmp['MESSAGE']);
}

// closing database connection
$db->Disconnect();
endMeasure('TOTAL'); // end calculation of execution time