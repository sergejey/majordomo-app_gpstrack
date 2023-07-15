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
    $this->action=gr('action');
}

$colors = array('#BF616A','#D08770','#EBCB8B','#A3BE8C','#B48EAD','red', 'blue', 'green', 'orange', 'brown', 'gray', 'yellow', 'white');

$qry = 1;

if ($period == 'week') {
    $qry .= " AND ADDED>='" . date('Y-m-d H:i:s', time() - 7 * 24 * 60 * 60) . "'";
    $to = date('Y-m-d');
    $from = date('Y-m-d', time() - 7 * 24 * 60 * 60);
} elseif ($period == 'month') {
    $qry .= " AND ADDED>='" . date('Y-m-d H:i:s', time() - 31 * 24 * 60 * 60) . "'";
    $to = date('Y-m-d');
    $from = date('Y-m-d', time() - 31 * 24 * 60 * 60);
} elseif ($period == 'custom') {
    $qry .= " AND ADDED>=DATE('" . $from . " 00:00:00')";
    $qry .= " AND ADDED<=DATE('" . $to . " 23:59:59')";
} elseif ($period == 'day') {
    $qry .= " AND ADDED>'" . date('Y-m-d H:i:s', time() - 1 * 24 * 60 * 60) . "'";
    $to = date('Y-m-d');
    $from = date('Y-m-d', time() - 1 * 24 * 60 * 60);
} else {
    $period = 'today';
    $qry .= " AND ADDED>='" . date('Y-m-d 00:00:00') . "'";
    $to = date('Y-m-d');
    $from = $to;
}

$device_id = gr('device_id', 'int');

if ($this->action == 'track') {
    $device_id = $this->device_id;
    $out['WIDTH'] = $this->width;
    if (!$out['WIDTH']) $out['WIDTH'] = '300px';
    $out['HEIGHT'] = $this->height;
    if (!$out['HEIGHT']) $out['HEIGHT'] = '300px';
    $out['UNIQ']=uniqid();
} else {
    $out['UNIQ']='';
}

if ($device_id) {
    $out['DEVICE_ID'] = $device_id;
    $qry .= " AND DEVICE_ID=" . $device_id;
}

if ($ajax && $device_id) {
    $out['DEVICES'] = SQLSelect("SELECT gpsdevices.*, users.NAME, users.USERNAME, users.AVATAR, users.COLOR FROM gpsdevices LEFT JOIN users ON gpsdevices.USER_ID=users.ID WHERE gpsdevices.ID=$device_id ORDER BY users.NAME");
} else {
    $out['DEVICES'] = SQLSelect("SELECT gpsdevices.*, users.NAME, users.USERNAME, users.AVATAR, users.COLOR FROM gpsdevices LEFT JOIN users ON gpsdevices.USER_ID=users.ID WHERE 1 ORDER BY users.NAME");
}
$res_devices=array();
$total = count($out['DEVICES']);
for ($i = 0; $i < $total; $i++) {
        // some action for every record if required
    if (!checkAccess('gps_device', $out['DEVICES'][$i]['ID'])) {
		continue;// some action for every record if required
	}
    if (!$out['DEVICES'][$i]['COLOR'])
        $out['DEVICES'][$i]['COLOR'] = $colors[$i];

    $update_tm=strtotime($out['DEVICES'][$i]['UPDATED']);
    $out['DEVICES'][$i]['PASSED']=getPassedText($update_tm);

    if ($this->action=='track' && !$this->device_id) {
        if ((time()-$update_tm)<24*60*60) {
            $res_devices[]=$out['DEVICES'][$i];
        }
    } else {
        $res_devices[]=$out['DEVICES'][$i];
    }

}
$out['DEVICES']=$res_devices;

if ($ajax) {

    if (!headers_sent()) {
        header("HTTP/1.0: 200 OK\n");
        header('Content-Type: text/html; charset=utf-8');
    }

    if ($op == 'getmarkers') {

        $total = count($out['DEVICES']);
        for ($i = 0; $i < $total; $i++) {
            $latest_point = SQLSelectOne("SELECT LAT,LON FROM gpslog WHERE DEVICE_ID='" . $out['DEVICES'][$i]['ID'] . "' ORDER BY ID DESC LIMIT 1");
            $out['DEVICES'][$i]['LATEST_LAT'] = $latest_point['LAT'];
            $out['DEVICES'][$i]['LATEST_LON'] = $latest_point['LON'];
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
            $points[] = array('ID' => $log[$i]['ID'], 'LAT' => $log[$i]['LAT'], 'LON' => $log[$i]['LON'], 'TITLE' => $device['TITLE'] . ' (' . $log[$i]['ADDED'] . ')');
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
        SQLExec("DELETE FROM gpslog WHERE ID=".$id_log);
        echo "Ok";
    }

    exit;
}

$latest_point = SQLSelectOne("SELECT LAT,LON FROM gpslog WHERE ID=(SELECT MAX(ID) FROM gpslog)");
$out['LATEST_LAT'] = $latest_point['LAT'];
$out['LATEST_LON'] = $latest_point['LON'];

$out['TO'] = $to;
$out['FROM'] = $from;
$out['PERIOD'] = $period;


