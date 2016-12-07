<?php


$dictionary=array(

'GPSTRACK_OPTIONS'=>'Settings',
'GPSTRACK_MAPPROVIDER'=>'Map provider:',
'GPSTRACK_MAPTYPE'=>'Map type:',
'GPSTRACK_MAPTYPE_MAP'=>'Map',
'GPSTRACK_MAPTYPE_SATELLITE'=>'Satellite',
'GPSTRACK_MAPTYPE_HYBRID'=>'Hybrid',
'GPSTRACK_MAX_ACCURACY'=>'Maximum accuracy:',
'GPSTRACK_MAX_ACCURACY_DESC'=>'If the accuracy exceeds limit GPS value will not be saved. The default is 0 (disabled).'
/* end module names */


);

foreach ($dictionary as $k=>$v) {
 if (!defined('LANG_'.$k)) {
  define('LANG_'.$k, $v);
 }
}
