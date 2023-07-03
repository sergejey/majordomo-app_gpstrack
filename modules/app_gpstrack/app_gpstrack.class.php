<?php
/**
 * GPS Track
 *
 * App_gpstrack
 *
 * @package MajorDoMo
 * @author Serge Dzheigalo <jey@tut.by> http://smartliving.ru/
 * @version 0.2 (wizard, 14:07:59 [Jul 25, 2011])
 */
//
//
class app_gpstrack extends module
{
    /**
     * app_gpstrack
     *
     * Module class constructor
     *
     * @access private
     */
    function app_gpstrack()
    {
        $this->name = "app_gpstrack";
        $this->title = "<#LANG_APP_GPSTRACK#>";
        $this->module_category = "<#LANG_SECTION_APPLICATIONS#>";
        $this->checkInstalled();
    }

    /**
     * saveParams
     *
     * Saving module parameters
     *
     * @access public
     */
    function saveParams($data = 0)
    {
        $p = array();
        if (isset($this->id)) {
            $p["id"] = $this->id;
        }
        if (isset($this->view_mode)) {
            $p["view_mode"] = $this->view_mode;
        }
        if (isset($this->edit_mode)) {
            $p["edit_mode"] = $this->edit_mode;
        }
        if (isset($this->data_source)) {
            $p["data_source"] = $this->data_source;
        }
        if (isset($this->tab)) {
            $p["tab"] = $this->tab;
        }
        return parent::saveParams($p);
    }

    /**
     * getParams
     *
     * Getting module parameters from query string
     *
     * @access public
     */
    function getParams()
    {
        global $id;
        global $mode;
        global $view_mode;
        global $edit_mode;
        global $data_source;
        global $tab;
        if (isset($id)) {
            $this->id = $id;
        }
        if (isset($mode)) {
            $this->mode = $mode;
        }
        if (isset($view_mode)) {
            $this->view_mode = $view_mode;
        }
        if (isset($edit_mode)) {
            $this->edit_mode = $edit_mode;
        }
        if (isset($data_source)) {
            $this->data_source = $data_source;
        }
        if (isset($tab)) {
            $this->tab = $tab;
        }
    }

    /**
     * Run
     *
     * Description
     *
     * @access public
     */
    function run()
    {

        @Define('DEF_ACTION_TYPE_OPTIONS', '1=' . LANG_GPSTRACK_ACTION_ENTERING . '|0=' . LANG_GPSTRACK_ACTION_LEAVING . '|2=' . LANG_GPSTRACK_ACTION_ENTERING_OR_LEAVING); // options for 'ACTION_TYPE'

        global $session;
        $out = array();
        if ($this->action == 'admin') {
            $this->admin($out);
        } else {
            $this->usual($out);
        }
        if (isset($this->owner->action)) {
            $out['PARENT_ACTION'] = $this->owner->action;
        }
        if (isset($this->owner->name)) {
            $out['PARENT_NAME'] = $this->owner->name;
        }
        $out['VIEW_MODE'] = $this->view_mode;
        $out['EDIT_MODE'] = $this->edit_mode;
        $out['MODE'] = $this->mode;
        $out['ACTION'] = $this->action;
        $out['DATA_SOURCE'] = $this->data_source;
        $out['TAB'] = $this->tab;
        if (isset($this->device_id)) {
            $out['IS_SET_DEVICE_ID'] = 1;
        }
        if (isset($this->location_id)) {
            $out['IS_SET_LOCATION_ID'] = 1;
        }
        if (isset($this->user_id)) {
            $out['IS_SET_USER_ID'] = 1;
        }
        if (isset($this->location_id)) {
            $out['IS_SET_LOCATION_ID'] = 1;
        }
        if (isset($this->user_id)) {
            $out['IS_SET_USER_ID'] = 1;
        }
        if (isset($this->script_id)) {
            $out['IS_SET_SCRIPT_ID'] = 1;
        }
        if ($this->single_rec) {
            $out['SINGLE_REC'] = 1;
        }
        $this->data = $out;
        $p = new parser(DIR_TEMPLATES . $this->name . "/" . $this->name . ".html", $this->data, $this);
        $this->result = $p->result;
    }

    /**
     * BackEnd
     *
     * Module backend
     *
     * @access public
     */
    function admin(&$out)
    {
        $this->getConfig();
        $out['MAPPROVIDER'] = $this->config['MAPPROVIDER'] ?? "";
        $out['MAPTYPE'] = $this->config['MAPTYPE'] ?? "";
        $out['MAX_ACCURACY'] = $this->config['MAX_ACCURACY'] ?? "";
        $out['AUTO_OPTIMIZE'] = (int)$this->config['AUTO_OPTIMIZE'] ?? "";
        $out['OPTIMIZE_DISTANCE'] = (float)$this->config['OPTIMIZE_DISTANCE'] ?? "";
        $out['KEEP_HISTORY'] = (int)$this->config['KEEP_HISTORY'] ?? "";
        $out['API_KEY'] = $this->config['API_KEY'] ?? "";
        if ($this->view_mode == 'update_settings') {
            global $mapprovider;
            $this->config['MAPPROVIDER'] = $mapprovider;
            global $maptype;
            $this->config['MAPTYPE'] = $maptype;
            global $max_accuracy;
            $this->config['MAX_ACCURACY'] = $max_accuracy;
            global $api_key;
            $this->config['API_KEY'] = $api_key;
            $this->config['AUTO_OPTIMIZE'] = gr('auto_optimize', 'int');
            $this->config['OPTIMIZE_DISTANCE'] = (float)gr('optimize_distance');
            $this->config['KEEP_HISTORY'] = gr('keep_history', 'int');
            $this->saveConfig();
            if ($this->config['AUTO_OPTIMIZE']) {
                subscribeToEvent($this->name, 'HOURLY');
            }
            $this->redirect("?data_source=gpsoptions&ok=1");
        }
        if ($_GET['ok']) {
            $out['OK'] = 1;
        }

        if ($this->data_source == 'preview' || gr('ajax') || $this->ajax) {
            $this->usual($out);
            return;
        }

        if (isset($this->data_source) && !$_GET['data_source'] && !$_POST['data_source']) {
            $out['SET_DATASOURCE'] = 1;
        }
        if ($this->data_source == 'gpslog' || $this->data_source == '') {
            if ($this->view_mode == '' || $this->view_mode == 'search_gpslog') {
                $this->search_gpslog($out);
            }
            if ($this->view_mode == 'edit_gpslog') {
                $this->edit_gpslog($out, $this->id);
            }
            if ($this->view_mode == 'delete_gpslog') {
                $this->delete_gpslog($this->id);
                $this->redirect("?data_source=gpslog");
            }
        }
        if (isset($this->data_source) && !$_GET['data_source'] && !$_POST['data_source']) {
            $out['SET_DATASOURCE'] = 1;
        }
        if ($this->data_source == 'gpslocations') {
            if ($this->view_mode == '' || $this->view_mode == 'search_gpslocations') {
                $this->search_gpslocations($out);
            }
            if ($this->view_mode == 'edit_gpslocations') {
                $this->edit_gpslocations($out, $this->id);
            }
            if ($this->view_mode == 'delete_gpslocations') {
                $this->delete_gpslocations($this->id);
                $this->redirect("?data_source=gpslocations");
            }
        }
        if (isset($this->data_source) && !$_GET['data_source'] && !$_POST['data_source']) {
            $out['SET_DATASOURCE'] = 1;
        }
        if ($this->data_source == 'gpsdevices') {
            if ($this->view_mode == '' || $this->view_mode == 'search_gpsdevices') {
                $this->search_gpsdevices($out);
            }
            if ($this->view_mode == 'edit_gpsdevices') {
                $this->edit_gpsdevices($out, $this->id);
            }
            if ($this->view_mode == 'delete_gpsdevices') {
                $this->delete_gpsdevices($this->id);
                $this->redirect("?data_source=gpsdevices");
            }
        }
        if (isset($this->data_source) && !$_GET['data_source'] && !$_POST['data_source']) {
            $out['SET_DATASOURCE'] = 1;
        }
        if ($this->data_source == 'gpsactions') {
            if ($this->view_mode == '' || $this->view_mode == 'search_gpsactions') {
                $this->search_gpsactions($out);
            }
            if ($this->view_mode == 'edit_gpsactions') {
                $this->edit_gpsactions($out, $this->id);
            }
            if ($this->view_mode == 'delete_gpsactions') {
                $this->delete_gpsactions($this->id);
                $this->redirect("?data_source=gpsactions");
            }
        }
    }

    /**
     * FrontEnd
     *
     * Module frontend
     *
     * @access public
     */
    function usual(&$out)
    {
        $this->getConfig();
        $out['MAPPROVIDER'] = $this->config['MAPPROVIDER'];
        $out['MAPTYPE'] = $this->config['MAPTYPE'];
        $out['MAX_ACCURACY'] = $this->config['MAX_ACCURACY'];
        $out['API_KEY'] = $this->config['API_KEY'];
        require(DIR_MODULES . $this->name . '/usual.inc.php');
    }

    /**
     * gpslog search
     *
     * @access public
     */
    function search_gpslog(&$out)
    {
        require(DIR_MODULES . $this->name . '/gpslog_search.inc.php');
    }

    /**
     * gpslog edit/add
     *
     * @access public
     */
    function edit_gpslog(&$out, $id)
    {
        require(DIR_MODULES . $this->name . '/gpslog_edit.inc.php');
    }

    /**
     * gpslog delete record
     *
     * @access public
     */
    function delete_gpslog($id)
    {
        $rec = SQLSelectOne("SELECT * FROM gpslog WHERE ID='$id'");
        // some action for related tables
        SQLExec("DELETE FROM gpslog WHERE ID='" . $rec['ID'] . "'");
    }

    /**
     * gpslocations search
     *
     * @access public
     */
    function search_gpslocations(&$out)
    {
        require(DIR_MODULES . $this->name . '/gpslocations_search.inc.php');
    }

    /**
     * gpslocations edit/add
     *
     * @access public
     */
    function edit_gpslocations(&$out, $id)
    {
        require(DIR_MODULES . $this->name . '/gpslocations_edit.inc.php');
    }

    /**
     * gpslocations delete record
     *
     * @access public
     */
    function delete_gpslocations($id)
    {
        $rec = SQLSelectOne("SELECT * FROM gpslocations WHERE ID='$id'");
        // some action for related tables
        SQLExec("DELETE FROM gpslocations WHERE ID='" . $rec['ID'] . "'");
    }

    /**
     * gpsdevices search
     *
     * @access public
     */
    function search_gpsdevices(&$out)
    {
        require(DIR_MODULES . $this->name . '/gpsdevices_search.inc.php');
    }

    /**
     * gpsdevices edit/add
     *
     * @access public
     */
    function edit_gpsdevices(&$out, $id)
    {
        require(DIR_MODULES . $this->name . '/gpsdevices_edit.inc.php');
    }

    /**
     * gpsdevices delete record
     *
     * @access public
     */
    function delete_gpsdevices($id)
    {
        $rec = SQLSelectOne("SELECT * FROM gpsdevices WHERE ID='$id'");
        // some action for related tables
        SQLExec("DELETE FROM gpslog WHERE DEVICE_ID='" . $rec['ID'] . "'");
        SQLExec("DELETE FROM gpsdevices WHERE ID='" . $rec['ID'] . "'");
    }

    /**
     * gpsactions search
     *
     * @access public
     */
    function search_gpsactions(&$out)
    {
        require(DIR_MODULES . $this->name . '/gpsactions_search.inc.php');
    }

    /**
     * gpsactions edit/add
     *
     * @access public
     */
    function edit_gpsactions(&$out, $id)
    {
        require(DIR_MODULES . $this->name . '/gpsactions_edit.inc.php');
    }

    /**
     * gpsactions delete record
     *
     * @access public
     */
    function delete_gpsactions($id)
    {
        $rec = SQLSelectOne("SELECT * FROM gpsactions WHERE ID='$id'");
        // some action for related tables
        SQLExec("DELETE FROM gpsactions WHERE ID='" . $rec['ID'] . "'");
    }

    function optimize_log($verbose = 0)
    {
        set_time_limit(6000);

        $optimize_distance = (float)($this->config['OPTIMIZE_DISTANCE'] ?? "");

        $tmp = SQLSelectOne("SELECT COUNT(*) as TOTAL FROM gpslog");
        $before = (int)$tmp['TOTAL'];

        $records = SQLSelect("SELECT gpslog.ID, gpslog.DEVICEID, gpslog.LAT, gpslog.LON, gpslog.LOCATION_ID, gpsdevices.ID AS GPS_DEVICE_ID FROM gpslog LEFT JOIN gpsdevices ON gpslog.DEVICE_ID=gpsdevices.ID ORDER BY gpslog.DEVICEID, gpslog.ADDED DESC");
        DebMes("Staring GPS data optimizing (total: $before)", 'gps');
        $total = count($records);
        for ($i = 1; $i < $total - 1; $i++) {

            if (!$records[$i]['GPS_DEVICE_ID']) {
                SQLExec("DELETE FROM gpslog WHERE ID=" . $records[$i]['ID']);
                continue;
            }

            //if (!$records[$i]['LOCATION_ID']) continue;

            $delete_record = false;
            $delete_reason = "";

            if ($records[$i]['GPS_DEVICE_ID'] == $records[$i + 1]['GPS_DEVICE_ID']
                && $records[$i]['GPS_DEVICE_ID'] == $records[$i - 1]['GPS_DEVICE_ID']) {
                // same device
                if (
                    $records[$i]['LOCATION_ID']
                    && $records[$i]['LOCATION_ID'] == $records[$i + 1]['LOCATION_ID']
                    && $records[$i]['LOCATION_ID'] == $records[$i - 1]['LOCATION_ID']) {
                    $delete_record = true;
                    $delete_reason = 'Same location.';
                } elseif (
                    $records[$i]['LAT'] == $records[$i + 1]['LAT']
                    && $records[$i]['LON'] == $records[$i + 1]['LON']
                    && $records[$i]['LAT'] == $records[$i - 1]['LAT']
                    && $records[$i]['LON'] == $records[$i - 1]['LON']
                ) {
                    $delete_record = true;
                    $delete_reason = 'Same coordinates.';
                } elseif ($optimize_distance > 0) {
                    $distance1 = $this->calculateTheDistance($records[$i]['LAT'],$records[$i]['LON'],$records[$i + 1]['LAT'],$records[$i + 1]['LON']);
                    $distance2 = $this->calculateTheDistance($records[$i]['LAT'],$records[$i]['LON'],$records[$i - 1]['LAT'],$records[$i - 1]['LON']);
                    if ($distance1<=$optimize_distance && $distance2<=$optimize_distance) {
                        $delete_record = true;
                        $delete_reason = 'Close coordinates.';
                    }
                }
            }


            if ($delete_record) {
                dprint($delete_reason, false);
                SQLExec("DELETE FROM gpslog WHERE ID=" . $records[$i]['ID']);
            }

            if ($verbose && $i % 200 == 0) {
                echo ".";
                echo str_repeat(' ', 1024);
                flush();
                flush();
            }
        }
        SQLExec("OPTIMIZE TABLE `gpslog`");

        $tmp = SQLSelectOne("SELECT COUNT(*) as TOTAL FROM gpslog");
        $after = (int)$tmp['TOTAL'];

        DebMes("Finished GPS data optimizing (total: $after)", 'gps');
    }

    /**
     * Calculate distance between two GPS coordinates
     * @param mixed $latA First coord latitude
     * @param mixed $lonA First coord longitude
     * @param mixed $latB Second coord latitude
     * @param mixed $lonB Second coord longitude
     * @return double
     */
    function calculateTheDistance($latA, $lonA, $latB, $lonB)
    {
        define('EARTH_RADIUS', 6372795);

        $lat1 = $latA * M_PI / 180;
        $lat2 = $latB * M_PI / 180;
        $long1 = $lonA * M_PI / 180;
        $long2 = $lonB * M_PI / 180;

        $cl1 = cos($lat1);
        $cl2 = cos($lat2);
        $sl1 = sin($lat1);
        $sl2 = sin($lat2);

        $delta = $long2 - $long1;
        $cdelta = cos($delta);
        $sdelta = sin($delta);

        $y = sqrt(pow($cl2 * $sdelta, 2) + pow($cl1 * $sl2 - $sl1 * $cl2 * $cdelta, 2));
        $x = $sl1 * $sl2 + $cl1 * $cl2 * $cdelta;

        $ad = atan2($y, $x);

        $dist = round($ad * EARTH_RADIUS);

        return $dist;
    }


    function processSubscription($event_name, $details = '')
    {
        if ($event_name == 'HOURLY') {
            //...
            $this->getConfig();
            if ($this->config['KEEP_HISTORY']) {
                SQLExec("DELETE FROM gpslog WHERE ADDED<'" . date('Y-m-d H:i:s', (time() - $this->config['KEEP_HISTORY'] * 24 * 60 * 60)) . "'");
            }
            if ($this->config['AUTO_OPTIMIZE'] && ((int)date('H')) == 3) {
                $this->optimize_log();
            }
        }
    }

    /**
     * Install
     *
     * Module installation routine
     *
     * @access private
     */
    function install($data = '')
    {
        parent::install();
        addClass('GPSLocations');
        addClassMethod('GPSLocations', 'userEntered', '//$params["USER_OBJECT"]' . "\n");
        addClassMethod('GPSLocations', 'userLeft', '//$params["USER_OBJECT"]' . "\n");
        addClassProperty('GPSLocations', 'locationTitle');
        addClassProperty('GPSLocations', 'latestVisit');

        addClassMethod('Users', 'enteredLocation', '//$params["LOCATION_OBJECT"], $params["LOCATION"]' . "\n");
        addClassMethod('Users', 'leftLocation', '//$params["LOCATION_OBJECT"], $params["LOCATION"]' . "\n");
    }

    /**
     * Uninstall
     *
     * Module uninstall routine
     *
     * @access public
     */
    function uninstall()
    {
        SQLExec('DROP TABLE IF EXISTS gpslog');
        SQLExec('DROP TABLE IF EXISTS gpslocations');
        SQLExec('DROP TABLE IF EXISTS gpsdevices');
        SQLExec('DROP TABLE IF EXISTS gpsactions');
        parent::uninstall();
    }


    /**
     * dbInstall
     *
     * Database installation routine
     *
     * @access private
     */
    function dbInstall($data)
    {
        /*
        gpslog - Log
        gpslocations - Locations
        gpsdevices - Devices
        gpsactions - Actions
        */
        $data = <<<EOD
 gpslog: ID int(10) unsigned NOT NULL auto_increment
 gpslog: ADDED datetime
 gpslog: LAT float DEFAULT '0' NOT NULL
 gpslog: LON float DEFAULT '0' NOT NULL
 gpslog: ALT float DEFAULT '0' NOT NULL
 gpslog: PROVIDER varchar(30) NOT NULL DEFAULT ''
 gpslog: SPEED float DEFAULT '0' NOT NULL
 gpslog: BATTLEVEL int(3) NOT NULL DEFAULT '0'
 gpslog: CHARGING int(3) NOT NULL DEFAULT '0'
 gpslog: DEVICEID varchar(255) NOT NULL DEFAULT ''
 gpslog: DEVICE_ID int(10) NOT NULL DEFAULT '0'
 gpslog: LOCATION_ID int(10) NOT NULL DEFAULT '0'
 gpslog: ACCURACY float DEFAULT '0' NOT NULL
 gpslog: INDEX (DEVICE_ID)
 gpslog: INDEX (LOCATION_ID)

 gpslocations: ID int(10) unsigned NOT NULL auto_increment
 gpslocations: TITLE varchar(255) NOT NULL DEFAULT ''
 gpslocations: LINKED_OBJECT varchar(255) NOT NULL DEFAULT '' 
 gpslocations: LAT float DEFAULT '0' NOT NULL
 gpslocations: LON float DEFAULT '0' NOT NULL
 gpslocations: RANGE float DEFAULT '0' NOT NULL
 gpslocations: VIRTUAL_USER_ID int(10) NOT NULL DEFAULT '0'
 gpslocations: IS_HOME int(3) NOT NULL DEFAULT '0'

 gpsdevices: ID int(10) unsigned NOT NULL auto_increment
 gpsdevices: TITLE varchar(255) NOT NULL DEFAULT ''
 gpsdevices: USER_ID int(10) NOT NULL DEFAULT '0'
 gpsdevices: LAT varchar(255) NOT NULL DEFAULT ''
 gpsdevices: LON varchar(255) NOT NULL DEFAULT ''
 gpsdevices: UPDATED datetime
 gpsdevices: DEVICEID varchar(255) NOT NULL DEFAULT ''
 gpsdevices: TOKEN varchar(255) NOT NULL DEFAULT ''
 gpsdevices: HOME_DISTANCE int(10) NOT NULL DEFAULT '0'
 gpsdevices: INDEX (USER_ID)

 gpsactions: ID int(10) unsigned NOT NULL auto_increment
 gpsactions: LOCATION_ID int(10) NOT NULL DEFAULT '0'
 gpsactions: USER_ID int(10) NOT NULL DEFAULT '0'
 gpsactions: ACTION_TYPE int(255) NOT NULL DEFAULT '0'
 gpsactions: SCRIPT_ID int(10) NOT NULL DEFAULT '0'
 gpsactions: CODE text
 gpsactions: LOG text
 gpsactions: EXECUTED datetime
 gpsactions: INDEX (LOCATION_ID)
 gpsactions: INDEX (USER_ID)
EOD;
        parent::dbInstall($data);

        /*
        $indexes=array('gpslog'=>array('DEVICE_ID','LOCATION_ID'));
        foreach($indexes as $indexTable=>$v) {
         foreach($v as $indexColumn) {
          $indexCheck=SQLSelectOne("SELECT COUNT(1) IndexIsThere FROM INFORMATION_SCHEMA.STATISTICS WHERE table_schema=DATABASE() AND table_name='$indexTable' AND index_name='$indexColumn';");
          if (!$indexCheck['IndexIsThere']) {
           SQLExec("CREATE INDEX $indexColumn ON $indexTable($indexColumn);");
          }
         }
        }
        */

    }
// --------------------------------------------------------------------
}

/*
*
* TW9kdWxlIGNyZWF0ZWQgSnVsIDI1LCAyMDExIHVzaW5nIFNlcmdlIEouIHdpemFyZCAoQWN0aXZlVW5pdCBJbmMgd3d3LmFjdGl2ZXVuaXQuY29tKQ==
*
*/
?>
