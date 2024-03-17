<?php
/*
* @version 0.2 (wizard)
*/

global $clear_log;
if ($clear_log) {
    SQLExec("DELETE FROM gpslog");
    $this->redirect("?");
}

global $optimize_log;
if ($optimize_log) {
    $this->getConfig();
    $this->optimize_log(1);
    echo " DONE";
    echo str_repeat(' ', 1024);
    flush();
    flush();
    exit;
}

global $session;
if ($this->owner->name == 'panel') {
    $out['CONTROLPANEL'] = 1;
}
$qry = "1";
// search filters
if (IsSet($this->device_id)) {
    $device_id = $this->device_id;
} else {
    global $device_id;
}
if ($device_id) {
    $qry .= " AND DEVICE_ID='" . $device_id . "'";
    $out['DEVICE_ID']=$device_id;
    $out['SEARCHING']=1;
}

if (IsSet($this->location_id)) {
    $location_id = $this->location_id;
} else {
    global $location_id;
}
if ($location_id) {
    $qry.=" AND LOCATION_ID='" . $location_id . "'";
    $out['LOCATION_ID']=$location_id;
    $out['SEARCHING']=1;
}

$user_id=gr('user_id','int');
if ($user_id) {
    $out['USER_ID']=$user_id;
    $qry.=" AND gpsdevices.USER_ID=".$user_id;
    $out['SEARCHING']=1;
}

$address = gr('address');
if ($address) {
    $qry.=" AND ADDRESS!='' AND ADDRESS LIKE '%".DBSafe($address)."%'";
    $out['ADDRESS'] = htmlspecialchars($address);
}

$date_to=gr('date_to','trim');
if ($date_to) {
    $out['DATE_TO']=$date_to;
    $qry.=" AND gpslog.ADDED<='".$date_to." 23:59:59'";
    $out['SEARCHING']=1;
}
$date_from=gr('date_from','trim');
if ($date_from) {
    $out['DATE_FROM']=$date_from;
    $qry.=" AND gpslog.ADDED>='".$date_from." 00:00:00'";
    $out['SEARCHING']=1;
}

// QUERY READY
global $save_qry;
if ($save_qry) {
    $qry = $session->data['gpslog_qry'];
} else {
    $session->data['gpslog_qry'] = $qry;
}
if (!$qry) $qry = "1";
// FIELDS ORDER
global $sortby_gpslog;
if (!$sortby_gpslog) {
    $sortby_gpslog = $session->data['gpslog_sort'];
} else {
    if ($session->data['gpslog_sort'] == $sortby_gpslog) {
        if (Is_Integer(strpos($sortby_gpslog, ' DESC'))) {
            $sortby_gpslog = str_replace(' DESC', '', $sortby_gpslog);
        } else {
            $sortby_gpslog = $sortby_gpslog . " DESC";
        }
    }
    $session->data['gpslog_sort'] = $sortby_gpslog;
}
if (!$sortby_gpslog) $sortby_gpslog = "gpslog.ID DESC";
$out['SORTBY'] = $sortby_gpslog;
// SEARCH RESULTS

$res_total = SQLSelectOne("SELECT COUNT(*) as TOTAL FROM gpslog LEFT JOIN gpsdevices ON gpsdevices.ID=gpslog.DEVICE_ID LEFT JOIN gpslocations ON gpslocations.ID=gpslog.LOCATION_ID WHERE $qry");
require(DIR_MODULES.$this->name.'/Paginator.php');
$page=gr('page','int');
$out['PAGE'] = $page;
if (!$page) $page=1;
$on_page=50;
$limit=(($page-1)*$on_page).','.$on_page;
$urlPattern='?page=(:num)&device_id='.(int)$device_id."&user_id=".(int)$user_id."&location_id=".(int)$location_id."&date_to=".urlencode($date_to)."&date_from=".urlencode($date_from);
$paginator = new JasonGrimes\Paginator($res_total['TOTAL'], $on_page, $page, $urlPattern);
$out['PAGINATOR']=$paginator;

$res = SQLSelect("SELECT gpslog.*, gpsdevices.ID as DEVICE_ID, gpsdevices.TITLE as DEVICE_TITLE, users.NAME as USER_NAME, gpslocations.TITLE as LOCATION_TITLE FROM gpslog LEFT JOIN gpsdevices ON gpsdevices.ID=gpslog.DEVICE_ID LEFT JOIN users ON gpsdevices.USER_ID=users.ID LEFT JOIN gpslocations ON gpslocations.ID=gpslog.LOCATION_ID WHERE $qry ORDER BY " . $sortby_gpslog." LIMIT $limit");
if ($res[0]['ID']) {
    $total = count($res);
    for ($i = 0; $i < $total; $i++) {
        if (!checkAccess('gps_device', $res[$i]['DEVICE_ID'])) {
            unset ($res[$i]);
	    continue;
	}
        $tmp = explode(' ', $res[$i]['ADDED']);
        $res[$i]['ADDED'] = fromDBDate($tmp[0]) . " " . $tmp[1];
    }
    $out['RESULT'] = $res;
}


$out['LOCATIONS']=SQLSelect("SELECT * FROM gpslocations ORDER BY TITLE");
$out['DEVICES']=SQLSelect("SELECT * FROM gpsdevices ORDER BY TITLE");
$out['USERS']=SQLSelect("SELECT * FROM users ORDER BY NAME");
