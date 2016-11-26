#!/usr/bin/php
<?PHP
###############################################################
#                                                             #
# Community Applications copyright 2015-2016, Andrew Zawadzki #
#                                                             #
###############################################################

require_once("/usr/local/emhttp/plugins/dynamix.docker.manager/include/DockerClient.php");

function logger($string) {
  $string = escapeshellarg($string);
  shell_exec("logger -t 'Docker Auto Update' $string");
}

function notify($event,$subject,$description,$message="",$type="normal") {
  $command = '/usr/local/emhttp/plugins/dynamix/scripts/notify -e "'.$event.'" -s "'.$subject.'" -d "'.$description.'" -m "'.$message.'" -i "'.$type.'"';
  shell_exec($command);
}

# Check for available docker updates

logger("Community Applications Docker Autoupdate running");
if ( ! is_dir("/var/lib/docker/tmp") ) {
  logger("Docker not running.  Exiting");
  exit;
}
logger("Checking for available updates");
exec("/usr/local/emhttp/plugins/dynamix.docker.manager/scripts/dockerupdate.php check &> /dev/null");
$settings = json_decode(@file_get_contents("/boot/config/plugins/ca.update.applications/DockerUpdateSettings.json"),true);
if ( ! $settings ) {
  logger("No settings file found");
  exit;
}
$DockerTemplates = new DockerTemplates();
$info = $DockerTemplates->getAllInfo();
$allContainers = array_keys($info);

$updateAll = $settings['global']['dockerUpdateAll'] == "yes";

foreach($allContainers as $container) {
  if ( ! $info[$container]['updated'] || $info[$container]['updated'] == "false" ) {
    if ( $settings['containers'][$container]['name'] || $updateAll ) {
      $updateList[] = $container;
    } else {
      logger("Found update for $container.  Not set to autoupdate");
    }
  }
}
if ( ! $updateList ) {
  logger("No updates will be installed");
  exit;
}
logger("Installing Updates for ".implode(" ",$updateList));
$_GET['updateContainer'] = true;
$_GET['ct'] = $updateList;
include("/usr/local/emhttp/plugins/dynamix.docker.manager/include/CreateDocker.php");
if ( $settings['global']['dockerNotify'] == "yes" ) {
  notify("Community Applications","Docker Auto Update",implode(" ",$updateList)." Automatically Updated");
}
logger("Community Applications Docker Autoupdate finished");

?>