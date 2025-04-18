<?php

global $ajax;
if ($this->ajax) {
    $ajax = 1;
}
global $op;
global $period;
global $to;
global $from;

if (gr('action')) {
    $this->action = gr('action');
}

$colors = array('#BF616A', '#D08770', '#EBCB8B', '#A3BE8C', '#B48EAD', 'red', 'blue', 'green', 'orange', 'brown', 'gray', 'yellow', 'white');

$qry = 1;

$toTM = 0;
if ($period == 'week') {
    $qry .= " AND ADDED>='" . date('Y-m-d H:i:s', time() - 7 * 24 * 60 * 60) . "'";
    $fromTM = time() - 7 * 24 * 60 * 60;
    $to = date('Y-m-d');
    $from = date('Y-m-d', $fromTM);
} elseif ($period == 'month') {
    $qry .= " AND ADDED>='" . date('Y-m-d H:i:s', time() - 31 * 24 * 60 * 60) . "'";
    $to = date('Y-m-d');
    $fromTM = time() - 31 * 24 * 60 * 60;
    $from = date('Y-m-d', $fromTM);
} elseif ($period == 'custom') {
    $fromTM = strtotime($from . ' 00:00:00');
    $qry .= " AND ADDED>='" . $from . " 00:00:00'";
    $toTM = strtotime($to . ' 23:59:59');
    $qry .= " AND ADDED<='" . $to . " 23:59:59'";
} elseif ($period == 'day') {
    $qry .= " AND ADDED>'" . date('Y-m-d H:i:s', time() - 1 * 24 * 60 * 60) . "'";
    $to = date('Y-m-d');
    $fromTM = time() - 1 * 24 * 60 * 60;
    $from = date('Y-m-d', $fromTM);
} else {
    $period = 'today';
    $fromTM = strtotime(date('Y-m-d') . ' 00:00:00');
    $qry .= " AND ADDED>='" . date('Y-m-d 00:00:00') . "'";
    $to = date('Y-m-d');
    $from = $to;
}
$device_id = gr('device_id', 'int');

$out['FROM_TM'] = $fromTM;
$out['TO_TM'] = $toTM;

$slider_value = gr('slider_value', 'int');
if ($slider_value > 0 && $slider_value < 100) {
    if ($toTM == 0) $toTM = time();
    $diff = round(($toTM - $fromTM) * $slider_value / 100);
    $endTM = $fromTM + $diff;
    $qry .= " AND ADDED<='" . date('Y-m-d H:i:s', $endTM) . "'";
}

if ($this->action == 'track') {
    $device_id = $this->device_id;
    $out['WIDTH'] = $this->width;
    if (!$out['WIDTH']) $out['WIDTH'] = '300px';
    $out['HEIGHT'] = $this->height;
    if (!$out['HEIGHT']) $out['HEIGHT'] = '300px';
}

$out['UNIQ'] = uniqid();

if ($device_id) {
    $out['DEVICE_ID'] = $device_id;
    $qry .= " AND DEVICE_ID=" . $device_id;
}

if ($ajax && $device_id) {
    $out['DEVICES'] = SQLSelect("SELECT gpsdevices.*, users.NAME, users.USERNAME, users.AVATAR, users.COLOR FROM gpsdevices LEFT JOIN users ON gpsdevices.USER_ID=users.ID WHERE gpsdevices.ID=$device_id ORDER BY users.NAME");
} else {
    $out['DEVICES'] = SQLSelect("SELECT gpsdevices.*, users.NAME, users.USERNAME, users.AVATAR, users.COLOR FROM gpsdevices LEFT JOIN users ON gpsdevices.USER_ID=users.ID WHERE 1 ORDER BY users.NAME");
}
$res_devices = array();
$total = count($out['DEVICES']);
for ($i = 0; $i < $total; $i++) {
    // some action for every record if required
    if (!checkAccess('gps_device', $out['DEVICES'][$i]['ID'])) {
        continue;// some action for every record if required
    }
    if (!$out['DEVICES'][$i]['COLOR'])
        $out['DEVICES'][$i]['COLOR'] = $colors[$i];

    $update_tm = strtotime($out['DEVICES'][$i]['UPDATED']);
    $out['DEVICES'][$i]['PASSED'] = getPassedText($update_tm);

    if ($this->action == 'track' && !$this->device_id) {
        if ((time() - $update_tm) < 24 * 60 * 60) {
            $res_devices[] = $out['DEVICES'][$i];
        }
    } else {
        $res_devices[] = $out['DEVICES'][$i];
    }

}
$out['DEVICES'] = $res_devices;
if ($ajax) {

    if (!headers_sent()) {
        header("HTTP/1.0: 200 OK\n");
        header('Content-Type: text/html; charset=utf-8');
    }

    if ($op == 'getcard') {
        $res = $this->card($out);
        echo json_encode($res);
    }

    if ($op == 'getmarkers') {

        $total = count($out['DEVICES']);
        for ($i = 0; $i < $total; $i++) {
            $latest_point = SQLSelectOne("SELECT ID, LAT, LON, ADDED FROM gpslog WHERE DEVICE_ID='" . $out['DEVICES'][$i]['ID'] . "' AND $qry ORDER BY ID DESC LIMIT 1");
            if (isset($latest_point['ID'])) {
                $out['DEVICES'][$i]['LAT'] = $latest_point['LAT'];
                $out['DEVICES'][$i]['LON'] = $latest_point['LON'];
                $out['DEVICES'][$i]['LATEST_LAT'] = $latest_point['LAT'];
                $out['DEVICES'][$i]['LATEST_LON'] = $latest_point['LON'];
            } else {

            }
        }

        $data = array();
        $markers = $out['DEVICES'];
        $total = count($markers);
        for ($i = 0; $i < $total; $i++) {
            $markers[$i]['HTML'] = "<span style='color:black;'>" . $markers[$i]['NAME'] . " (" . $markers[$i]['TITLE'] . ")</span>";
            $data['MARKERS'][] = $markers[$i];
        }
        echo json_encode($data);
    }

    if ($op == 'getroute') {
        global $device_id;
        $device = SQLSelectOne("SELECT * FROM gpsdevices WHERE ID='" . (int)$device_id . "'");
        $log = SQLSelect("SELECT * FROM gpslog WHERE DEVICE_ID='" . (int)$device_id . "' AND " . $qry . " ORDER BY ADDED");
        $total = count($log);
        $coords = array();
        $points = array();
        for ($i = 0; $i < $total; $i++) {
            $coords[] = array($log[$i]['LAT'], $log[$i]['LON']);
            $points[] = array('ID' => $log[$i]['ID'], 'LAT' => $log[$i]['LAT'], 'LON' => $log[$i]['LON'], 'ALT' => $log[$i]['ALT'], 'SPEED' => $log[$i]['SPEED'], 'BATTLEVEL' => $log[$i]['BATTLEVEL'], 'ACCURACY' => $log[$i]['ACCURACY'], 'PROVIDER' => $log[$i]['PROVIDER'], 'ADDED' => $log[$i]['ADDED'], 'TITLE' => $device['TITLE'] . ' (' . $log[$i]['ADDED'] . ')');
            if ($i > 0) {
                $prev_lon = $log[$i - 1]['LON'];
                $prev_lat = $log[$i - 1]['LAT'];
                $angle = $this->getBearing($prev_lat, $prev_lon, $log[$i]['LAT'], $log[$i]['LON']);
                $points[$i - 1]['ANGLE'] = $angle;
            }
        }
        $res = array();
        if ($total) {
            $res['FIRST_POINT'] = $points[0];
            $res['LAST_POINT'] = $points[count($points) - 1];
            $res['PATH'] = $coords;
            $res['POINTS'] = $points;
        }
        echo json_encode($res);
    }

    if ($op == 'getlocations') {
        $res = array();
        $res['LOCATIONS'] = SQLSelect("SELECT * FROM gpslocations");
        echo json_encode($res);
    }

    if ($op == 'del_log') {
        global $id_log;
        SQLExec("DELETE FROM gpslog WHERE ID=" . $id_log);
        echo "Ok";
    }

    if ($op == 'edit_log') {
        global $id;
        global $lat;
        global $lon;
        $rec = SQLSelectOne("select * FROM gpslog WHERE ID=" . $id);
        if (isset($rec["ID"])) {
            $rec["LAT"] = $lat;
            $rec["LON"] = $lon;
            SQLUpdate("gpslog", $rec);
            echo "Ok";
        } else
            echo "Not found";
    }

    exit;
}

$latest_point = SQLSelectOne("SELECT LAT,LON FROM gpslog WHERE ID=(SELECT MAX(ID) FROM gpslog)");
$out['LATEST_LAT'] = $latest_point['LAT'];
$out['LATEST_LON'] = $latest_point['LON'];

$out['TO'] = $to;
$out['FROM'] = $from;
$out['PERIOD'] = $period;


