<?php
/*
* @version 0.1 (wizard)
*/
if ($this->owner->name == 'panel') {
    $out['CONTROLPANEL'] = 1;
}
$table_name = 'gpslocations';
$rec = SQLSelectOne("SELECT * FROM $table_name WHERE ID='$id'");
if ($this->mode == 'update') {
    $ok = 1;
    //updating 'TITLE' (varchar, required)
    global $title;
    $rec['TITLE'] = $title;
    if ($rec['TITLE'] == '') {
        $out['ERR_TITLE'] = 1;
        $ok = 0;
    }
    //updating 'LAT' (float, required)
    global $lat;
    $rec['LAT'] = (float)$lat;
    /*
    if (!$rec['LAT']) {
     $out['ERR_LAT']=1;
     $ok=0;
    }
    */
    //updating 'LON' (float, required)
    global $lon;
    $rec['LON'] = (float)$lon;
    /*
    if (!$rec['LON']) {
     $out['ERR_LON']=1;
     $ok=0;
    }
    */
    //updating 'RANGE' (float, required)
    global $range;
    $rec['RANGE'] = (float)$range;
    /*
    if (!$rec['RANGE']) {
     $out['ERR_RANGE']=1;
     $ok=0;
    }
    */

    global $is_home;
    $rec['IS_HOME'] = (int)$is_home;

    global $linked_object;
    $rec['LINKED_OBJECT'] = $linked_object;

    //updating 'VIRTUAL_USER_ID' (int)
    global $virtual_user_id;
    $rec['VIRTUAL_USER_ID'] = (int)$virtual_user_id;
    //UPDATING RECORD
    if ($ok) {
        if ($rec['ID']) {
            SQLUpdate($table_name, $rec); // update

        } else {
            $new_rec = 1;
            $rec['ID'] = SQLInsert($table_name, $rec); // adding new record

            // updating locations in the log
            if ($rec['LAT'] != 0 && $rec['LON'] != 0 && $rec['RANGE'] > 0) {
                $log = SQLSelect("SELECT * FROM gpslog WHERE LOCATION_ID=0");
                $total = count($log);
                for ($i = 0; $i < $total; $i++) {
                    $distance = $this->calculateTheDistance($log[$i]['LAT'], $log[$i]['LON'], $rec['LAT'], $rec['LON']);
                    if ($distance <= $rec['RANGE']) {
                        SQLExec("UPDATE gpslog SET LOCATION_ID=" . $rec['ID'] . " WHERE ID=" . $log[$i]['ID']);
                    }
                }
            }

        }

        if ($rec['IS_HOME']) {
            SQLExec("UPDATE gpslocations SET IS_HOME=0 WHERE IS_HOME=1 AND ID!=" . $rec['ID']);
        }


        $out['OK'] = 1;
        if (!$rec['LINKED_OBJECT']) {
            $object_title = 'Location' . $rec['ID'];
            addClassObject('GPSLocations', $object_title, 'location' . $rec['ID']);
            sg($object_title . '.locationTitle', $rec['TITLE']);
            $rec['LINKED_OBJECT'] = $object_title;
            SQLUpdate('gpslocations', $rec);
        }
    } else {
        $out['ERR'] = 1;
    }
}
if (is_array($rec)) {
    foreach ($rec as $k => $v) {
        if (!is_array($v)) {
            $rec[$k] = htmlspecialchars($v);
        }
    }
}
outHash($rec, $out);

if ($rec['ID']) {
    $devices = SQLSelect("SELECT gpsdevices.TITLE, DEVICE_ID, MAX(ADDED) as LAST_SEEN FROM gpslog LEFT JOIN gpsdevices ON gpslog.DEVICE_ID=gpsdevices.ID WHERE LOCATION_ID=" . $rec['ID'] . " GROUP BY DEVICE_ID ORDER BY LAST_SEEN DESC");
    if (isset($devices[0])) {
        $out['DEVICES'] = $devices;
    }
}