<?php

const GPS_LOCATION_RANGE_DEFAULT = 500;

$latitude = gr('latitude');
$longitude = gr('longitude');
$deviceid = gr('deviceid');
$location = gr('location');
if ($location != '') {
    $tmp = explode(',', $location);
    $latitude = $tmp[0];
    $longitude = $tmp[1];
}

if ($latitude == '' || $longitude == '' || $latitude == '0' || $longitude == '0') return false;

$this->getConfig();
$max_accuracy = $this->config['MAX_ACCURACY'];

if ($deviceid) {
    $sqlQuery = "SELECT *
                     FROM gpsdevices
                    WHERE DEVICEID = '" . DBSafe($deviceid) . "'";

    $device = SQLSelectOne($sqlQuery);

    if (!$device['ID']) {
        $device = array();
        $device['DEVICEID'] = $deviceid;
        $device['TITLE'] = 'New GPS Device';
        $device['TOKEN'] = gr('token');
        $device['ID'] = SQLInsert('gpsdevices', $device);

        $sqlQuery = "UPDATE gpslog
                         SET DEVICE_ID = '" . $device['ID'] . "'
                       WHERE DEVICEID = '" . DBSafe(gr('deviceid')) . "'";

        SQLExec($sqlQuery);
    }

    $device['LAT'] = (float)$latitude;
    $device['LON'] = (float)$longitude;
    $device['SPEED'] = round(gr('speed', 'float'), 2);
    $device['BATTLEVEL'] = gr('battlevel', 'int');
    $device['UPDATED'] = date('Y-m-d H:i:s');

    SQLUpdate('gpsdevices', $device);
}

$rec = array();

//$rec['ADDED']     = ($time) ? $time : date('Y-m-d H:i:s');
$rec['ADDED'] = date('Y-m-d H:i:s');
$rec['LAT'] = gr('latitude');
$rec['LON'] = gr('longitude');
$rec['ALT'] = round(gr('altitude', 'float'), 2);
$rec['PROVIDER'] = gr('provider');
$rec['SPEED'] = round(gr('speed', 'float'), 2);
$rec['BATTLEVEL'] = gr('battlevel', 'int');
$rec['CHARGING'] = gr('charging', 'int');
$rec['DEVICEID'] = gr('deviceid');
$rec['ACCURACY'] = gr('accuracy', 'int');

if (($max_accuracy != 0) && ($rec['ACCURACY'] > $max_accuracy)) {
    DebMes("GPS Accuracy {$rec['ACCURACY']} > {$max_accuracy} exiting!", 'gps');
    return false;
}

if ($device['ID']) $rec['DEVICE_ID'] = $device['ID'];

$rec['ID'] = SQLInsert('gpslog', $rec);
$address = $this->updateLogAddress($rec['ID']);

$sqlQuery = "SELECT *
                        FROM gpslog
                       WHERE DEVICE_ID = '" . $device['ID'] . "'
                         AND ID        != '" . $rec['ID'] . "'
                       ORDER BY ID DESC
                       LIMIT 1";

$previous_log_record = SQLSelectOne($sqlQuery);
if ($device['USER_ID']) {
    $sqlQuery = "SELECT *
                     FROM users
                    WHERE ID = '" . $device['USER_ID'] . "'";

    $user = SQLSelectOne($sqlQuery);

    if (isset($user['LINKED_OBJECT'])) {
        setGlobal($user['LINKED_OBJECT'] . '.Coordinates', $rec['LAT'] . ',' . $rec['LON']);
        setGlobal($user['LINKED_OBJECT'] . '.CoordinatesUpdated', date('H:i'));
        setGlobal($user['LINKED_OBJECT'] . '.CoordinatesUpdatedTimestamp', time());
        setGlobal($user['LINKED_OBJECT'] . '.CurrentSpeed', $rec['SPEED']);

        if ($rec['BATTLEVEL'] > 0) {
            setGlobal($user['LINKED_OBJECT'] . '.BattLevel', $rec['BATTLEVEL']);
            setGlobal($user['LINKED_OBJECT'] . '.Charging', $rec['CHARGING']);
        }

        if ($previous_log_record['ID']) {
            $distance = $this->calculateTheDistance($rec['LAT'], $rec['LON'], $previous_log_record['LAT'], $previous_log_record['LON']);
            if ($distance > 100) {
                //we're moving
                $objectIsMoving = $user['LINKED_OBJECT'] . '.isMoving';
                setGlobal($objectIsMoving, 1);
                clearTimeOut($user['LINKED_OBJECT'] . '_moving');
                // stopped after 15 minutes of inactivity
                setTimeOut($user['LINKED_OBJECT'] . '_moving', "setGlobal('" . $objectIsMoving . "', 0);", 15 * 60);
            }
        }
    }
}

// checking locations
$lat = gr('latitude', 'float');;
$lon = gr('longitude', 'float');

$locations = SQLSelect("SELECT * FROM gpslocations");
$total = count($locations);

$location_found = 0;

for ($i = 0; $i < $total; $i++) {
    if (!$locations[$i]['RANGE']) $locations[$i]['RANGE'] = GPS_LOCATION_RANGE_DEFAULT;

    $distance = $this->calculateTheDistance($lat, $lon, $locations[$i]['LAT'], $locations[$i]['LON']);

    if ($locations[$i]['IS_HOME'] && $device['ID']) {
        $device['HOME_DISTANCE'] = (int)$distance;
        $direction = $this->getBearing($lat, $lon, $locations[$i]['LAT'], $locations[$i]['LON']);
        $device['HOME_DIRECTION'] = (int)$direction;
        SQLUpdate('gpsdevices', $device);
        if (isset($user['LINKED_OBJECT'])) {
            setGlobal($user['LINKED_OBJECT'] . '.HomeDistance', $device['HOME_DISTANCE']);
            setGlobal($user['LINKED_OBJECT'] . '.HomeDistanceKm', round($device['HOME_DISTANCE'] / 1000, 1));
            setGlobal($user['LINKED_OBJECT'] . '.HomeDirectionDegrees', $direction);
        }
    }


    $params = array();
    $params['LOCATION'] = $locations[$i]['TITLE'];
    $params['USER'] = $user['NAME'];
    $params['USER_OBJECT'] = isset($user['LINKED_OBJECT']) ? $user['LINKED_OBJECT'] : '';

    if ($distance <= $locations[$i]['RANGE']) {

        // we are at location
        $rec['LOCATION_ID'] = $locations[$i]['ID'];
        SQLUpdate('gpslog', $rec);

        //Debmes("Device (" . $device['TITLE'] . ") NEAR location " . $locations[$i]['TITLE']." (".json_encode($rec).")",'gps');
        $location_found = 1;

        if (isset($user['LINKED_OBJECT']))
            setGlobal($user['LINKED_OBJECT'] . '.seenAt', $locations[$i]['TITLE']);

        $device['LOCATION'] = $locations[$i]['TITLE'];
        SQLUPdate('gpsdevices', $device);

        if ($previous_log_record['LOCATION_ID'] != $locations[$i]['ID']) {
            // entered location
            Debmes("Device (" . $device['TITLE'] . ") ENTERED location " . $locations[$i]['TITLE'] . ' (prev record: ' . json_encode($previous_log_record) . ')', 'gps');
            if ($locations[$i]['LINKED_OBJECT']) {
                setGlobal($locations[$i]['LINKED_OBJECT'] . '.latestVisit', date('Y-m-d H:i:s'));
                callMethodSafe($locations[$i]['LINKED_OBJECT'] . '.userEntered', $params);
            }
            if ($params['USER_OBJECT']) {
                callMethodSafe($params['USER_OBJECT'] . '.enteredLocation', array('LOCATION_OBJECT' => $locations[$i]['LINKED_OBJECT'], 'LOCATION' => $locations[$i]['TITLE']));
            }

            // running actions if defined
            $sqlQuery = "SELECT *
                           FROM gpsactions
                          WHERE (LOCATION_ID = '" . $locations[$i]['ID'] . "' OR LOCATION_ID=0)
                            AND (ACTION_TYPE = 1 OR ACTION_TYPE = 2)
                            AND (USER_ID     = '" . $device['USER_ID'] . "' OR USER_ID=0)";

            $gpsactions = SQLSelect($sqlQuery);
            for ($i_action = 0; $i_action < count($gpsactions); $i_action++) {
                $gpsaction = $gpsactions[$i_action];
                $gpsaction['EXECUTED'] = date('Y-m-d H:i:s');
                $gpsaction['LOG'] = $gpsaction['EXECUTED'] . " Executed\n" . $gpsaction['LOG'];
                while (substr_count($gpsaction['LOG'], "\n") > 30) { //очищаем самые давние события, если их более 30
                    $gpsaction['LOG'] = substr($gpsaction['LOG'], 0, strrpos(trim($gpsaction['LOG']), "\n"));
                }
                SQLUpdate('gpsactions', $gpsaction);
                $params['ENTERING'] = 1;
                $params['LEAVING'] = 0;
                if ($gpsaction['SAY_TEXT'] != '' && ($params['USER'] != '' || !is_integer(strpos($gpsaction['SAY_TEXT'], '%USER%')))) {
                    $say_text = $gpsaction['SAY_TEXT'];
                    foreach ($params as $k => $v) {
                        $say_text = str_replace('%' . $k . '%', $v, $say_text);
                    }
                    say($say_text, (int)$gpsaction['SAY_LEVEL']);
                }
                if ($gpsaction['SCRIPT_ID']) {
                    runScript($gpsaction['SCRIPT_ID'], $params);
                } elseif ($gpsaction['CODE']) {
                    try {
                        $code = $gpsaction['CODE'];
                        $success = eval($code);
                        if ($success === false) {
                            DebMes("Error in GPS action code: " . $code);
                            registerError('gps_action', "Code execution error: " . $code);
                        }
                    } catch (Exception $e) {
                        DebMes('Error: exception ' . get_class($e) . ', ' . $e->getMessage() . '.');
                        registerError('gps_action', get_class($e) . ', ' . $e->getMessage());
                    }
                }
            }
        }
    } else {
        if ($previous_log_record['LOCATION_ID'] == $locations[$i]['ID']) {
            // left location
            Debmes("Device (" . $device['TITLE'] . ") LEFT location " . $locations[$i]['TITLE'] . ' (prev record: ' . json_encode($previous_log_record) . ')', 'gps');
            if ($locations[$i]['LINKED_OBJECT']) {
                callMethodSafe($locations[$i]['LINKED_OBJECT'] . '.userLeft', $params);
            }
            if ($params['USER_OBJECT']) {
                callMethodSafe($params['USER_OBJECT'] . '.leftLocation', array('LOCATION_OBJECT' => $locations[$i]['LINKED_OBJECT'], 'LOCATION' => $locations[$i]['TITLE']));
            }
            $params['LEAVING'] = 1;

            // running actions if defined
            $sqlQuery = "SELECT *
                           FROM gpsactions
                          WHERE (LOCATION_ID = '" . $locations[$i]['ID'] . "' OR LOCATION_ID=0)
                            AND (ACTION_TYPE = 0  OR ACTION_TYPE = 2)
                            AND (USER_ID = '" . $device['USER_ID'] . "' OR USER_ID=0)";
            $gpsactions = SQLSelect($sqlQuery);

            for ($i_action = 0; $i_action < count($gpsactions); $i_action++) {
                $gpsaction = $gpsactions[$i_action];
                $gpsaction['EXECUTED'] = date('Y-m-d H:i:s');
                $gpsaction['LOG'] = $gpsaction['EXECUTED'] . " Executed\n" . $gpsaction['LOG'];
                while (substr_count($gpsaction['LOG'], "\n") > 30) { //очищаем самые давние события, если их более 30
                    $gpsaction['LOG'] = substr($gpsaction['LOG'], 0, strrpos(trim($gpsaction['LOG']), "\n"));
                }
                SQLUpdate('gpsactions', $gpsaction);

                $params['ENTERING'] = 0;
                $params['LEAVING'] = 1;
                if ($gpsaction['SAY_TEXT'] != '' && ($params['USER'] != '' || !is_integer(strpos($gpsaction['SAY_TEXT'], '%USER%')))) {
                    $say_text = $gpsaction['SAY_TEXT'];
                    foreach ($params as $k => $v) {
                        $say_text = str_replace('%' . $k . '%', $v, $say_text);
                    }
                    say($say_text, (int)$gpsaction['SAY_LEVEL']);
                }

                if ($gpsaction['SCRIPT_ID']) {
                    runScript($gpsaction['SCRIPT_ID'], $params);
                } elseif ($gpsaction['CODE']) {
                    try {
                        $code = $gpsaction['CODE'];
                        $success = eval($code);

                        if ($success === false)
                            DebMes("Error in GPS action code: " . $code);
                    } catch (Exception $e) {
                        DebMes('Error: exception ' . get_class($e) . ', ' . $e->getMessage() . '.');
                    }
                }
            }

        }
    }
}

if (isset($user['LINKED_OBJECT']) && !$location_found) {
    $address = gr('address');
    if ($address)
        setGlobal($user['LINKED_OBJECT'] . '.seenAt', $address);
    else
        setGlobal($user['LINKED_OBJECT'] . '.seenAt', '');
}

if (!$location_found && $device['LOCATION'] != '') {
    $device['LOCATION'] = '';
    SQLUPdate('gpsdevices', $device);
}
