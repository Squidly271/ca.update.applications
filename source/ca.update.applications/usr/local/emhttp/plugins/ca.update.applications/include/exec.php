<?PHP

###############################################################
#                                                             #
# Community Applications copyright 2015-2016, Andrew Zawadzki #
#                                                             #
###############################################################

require_once("/usr/local/emhttp/plugins/ca.update.applications/include/helpers.php");

############################################
############################################
##                                        ##
## BEGIN MAIN ROUTINES CALLED BY THE HTML ##
##                                        ##
############################################
############################################


switch ($_POST['action']) {
#################################################
#                                               #
# Setup the json file for the cron autoupdating #
#                                               #
#################################################

case 'autoUpdatePlugins':
  $globalUpdate          = getPost("globalUpdate","no");
  $pluginList            = getPost("pluginList","");
  $updateArray['notify'] = getPost("notify","yes");
  $updateArray['delay']  = getPost("delay","3");
  $updateArray['Global'] = ( $globalUpdate == "yes" ) ? "true" : "false";


  $plugins = explode("*",$pluginList);
  if ( is_array($plugins) ) {
    foreach ($plugins as $plg) {
      if (is_file("/var/log/plugins/$plg") ) {
        $updateArray[$plg] = "true";
      }
    }
  }
  writeJsonFile("/boot/config/plugins/ca.update.applications/AutoUpdateSettings.json",$updateArray);
  break;

}
?>
