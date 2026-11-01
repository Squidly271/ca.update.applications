#!/usr/bin/php
<?PHP
###############################################################
#                                                             #
# Community Applications copyright 2015-2026, Andrew Zawadzki #
#                                                             #
###############################################################
$docroot = $docroot ?? @$_SERVER['DOCUMENT_ROOT'] ?: '/usr/local/emhttp';
$_SERVER['DOCUMENT_ROOT'] = "/usr/local/emhttp";

require_once("/usr/local/emhttp/plugins/dynamix.docker.manager/include/DockerClient.php");
require_once("/usr/local/emhttp/plugins/ca.update.applications/include/dockerAutoUpdate.php");

function logger($string) {
  $string = escapeshellarg($string);
  shell_exec("logger -t 'Docker Auto Update' $string");
}

function notify($event,$subject,$description,$message="",$type="normal") {
  $command = '/usr/local/emhttp/plugins/dynamix/scripts/notify -e "'.$event.'" -s "'.$subject.'" -d "'.$description.'" -m "'.$message.'" -i "'.$type.'" -l "/Docker"';
  shell_exec($command);
}

function reportUpdates($settings,$updated,$notUpdated,$needsAttention) {
  if ( $notUpdated ) {
    logger("Not updated: ".implode(" ",$notUpdated));
  }
  if ( $needsAttention ) {
    logger("Need attention: ".implode(" ",$needsAttention));
  }
  if ( $settings['global']['dockerNotify'] == "yes" ) {
    if ( $updated ) {
      notify("Community Applications","Docker Auto Update",implode(" ",$updated)." Automatically Updated");
    }
    if ( $notUpdated ) {
      notify("Community Applications","Docker Auto Update",implode(" ",$notUpdated)." Not Updated","","warning");
    }
    if ( $needsAttention ) {
      notify("Community Applications","Docker Auto Update",implode(" ",$needsAttention)." Need Attention","","warning");
    }
  }
  logger("Community Applications Docker Autoupdate finished");
}

# Check for available docker updates

logger("Community Applications Docker Autoupdate running");
if ( ! is_dir("/var/lib/docker/tmp") ) {
  logger("Docker not running.  Exiting");
  exit;
}
logger("Checking for available updates");
$updateScript = is_file("/usr/local/emhttp/plugins/dynamix.docker.manager/scripts/dockerupdate") ? "dockerupdate" : "dockerupdate.php";
exec("/usr/local/emhttp/plugins/dynamix.docker.manager/scripts/$updateScript check nonotify &> /dev/null");
$settings = json_decode(@file_get_contents("/boot/config/plugins/ca.update.applications/DockerUpdateSettings.json"),true);
if ( ! $settings ) {
  logger("No settings file found");
  exit;
}
$DockerTemplates = new DockerTemplates();
$DockerClient = new DockerClient();

$info = $DockerTemplates->getAllInfo();
# workaround for incorrect caching in dockerMan
$containers = $DockerClient->getDockerContainers();
foreach ($containers as $container) {
	$info[$container['Name']]['running'] = $container['Running'];
	$info[$container['Name']]['repository'] = $container['Image'];
	$info[$container['Name']]['ImageId'] = $container['ImageId'];
	$info[$container['Name']]['Id'] = $container['Id'];
	$info[$container['Name']]['Name'] = $container['Name'];
}

$updateAll = $settings['global']['dockerUpdateAll'] == "yes";
$ignoreThirdParty = ($settings['global']['dockerIgnoreThirdParty'] ?? "no") == "yes";

# getAllInfo() keeps containers removed outside the Docker page, so loop over the live list
foreach($containers as $liveContainer) {
  $container = $liveContainer['Name'];
  if ( $ignoreThirdParty && caAutoUpdateIsThirdParty($liveContainer,$info[$container]) ) {
    logger("Skipping $container (not DockerMan-managed)");
    continue;
  }
  if ( caAutoUpdateHasUpdate($info[$container]) ) {
    if ( ($settings['containers'][$container]['name'] ?? false) || $updateAll ) {
      $updateList[] = $container;
    } else {
      logger("Found update for $container.  Not set to autoupdate");
    }
  }
}
if ( ! isset($updateList) ) {
  logger("No updates will be installed");
  exit;
}

# Pull before anything stops, so a slow or failed download costs no downtime
$pullFailed = [];
foreach ($updateList as $container) {
  $image = caAutoUpdateTemplateImage($info[$container]['template']);
  logger("Pulling $image for $container");
  $pullOutput = [];
  exec("docker pull ".escapeshellarg($image)." 2>&1",$pullOutput,$pullError);
  if ( $pullError ) {
    logger("Could not pull $image: ".end($pullOutput));
    $pullFailed[] = $container;
  }
}
$networkParents = caAutoUpdateNetworkParents($containers);
$updateList = caAutoUpdateParentsFirst(array_diff($updateList,$pullFailed),$networkParents);
if ( ! $updateList ) {
  reportUpdates($settings,[],$pullFailed,[]);
  exit;
}

if ( is_file("/boot/config/plugins/ca.update.applications/scripts/stopping/stop_all") ) {
  logger("Executing custom stop script /boot/config/plugins/ca.update.applications/scripts/stopping/stop_all");
  exec("/bin/bash /boot/config/plugins/ca.update.applications/scripts/stopping/stop_all");
}
foreach ($updateList as $containerScript) {
  if ( is_file("/boot/config/plugins/ca.update.applications/scripts/stopping/$containerScript") ) {
    logger("Executing custom stop script /boot/config/plugins/ca.update.applications/scripts/stopping/$containerScript");
    exec("/bin/bash /boot/config/plugins/ca.update.applications/scripts/stopping/$containerScript");
  }
}
$delayTime = $settings['global']['dockerStopDelay'];
$delayTime = $delayTime ? $delayTime : 10;

foreach ($updateList as $container) {
  if ( $info[$container]['running'] ) {
    logger("Stopping $container");
		exec("docker stop -t $delayTime $container");
  }
}
logger("Installing Updates for ".implode(" ",$updateList));
$_GET['updateContainer'] = true;
$_GET['ct'] = $updateList;
include("/usr/local/emhttp/plugins/dynamix.docker.manager/include/CreateDocker.php");
foreach ($updateList as $containerScript) {
  if ($info[$containerScript]['running']) {
    logger("Restarting $containerScript");
		$DockerClient->startContainer($containerScript);
	}
  if ( is_file("/boot/config/plugins/ca.update.applications/scripts/starting/$containerScript") ) {
    logger("Executing custom start script /boot/config/plugins/ca.update.applications/scripts/starting/$containerScript");
    exec("/bin/bash /boot/config/plugins/ca.update.applications/scripts/starting/$containerScript");
  }
}
if ( is_file("/boot/config/plugins/ca.update.applications/scripts/starting/start_all") ) {
  logger("Executing custom start script /boot/config/plugins/ca.update.applications/scripts/starting/start_all");
  exec("/bin/bash /boot/config/plugins/ca.update.applications/scripts/starting/start_all");
}

$DockerClient->flushCaches();
$liveContainers = $DockerClient->getDockerContainers();
$updated = caAutoUpdateChangedContainers($updateList,$info,$liveContainers);
$stranded = caAutoUpdateStrandedChildren($updateList,$networkParents,$liveContainers);
$rebuild = array_filter($stranded,function($container) use ($info) {
  return ! empty($info[$container]['template']);
});
if ( $rebuild ) {
  logger("Rebuilding ".implode(" ",$rebuild)." (sharing the network of a recreated container)");
  exec("/usr/bin/php /usr/local/emhttp/plugins/ca.update.applications/scripts/rebuildContainers.php ".implode(" ",$rebuild)." >/dev/null 2>&1");
  foreach ($rebuild as $container) {
    if ( $info[$container]['running'] ) {
      $DockerClient->startContainer($container);
    }
  }
}
$DockerClient->flushCaches();
$needsAttention = caAutoUpdateNeedsAttention($updateList,$stranded,$info,$DockerClient->getDockerContainers());

# Pulling first hides the old image from CreateDocker.php; docker refuses while anything still uses it
foreach ($updated as $container) {
  exec("docker rmi ".$info[$container]['ImageId']." >/dev/null 2>&1");
}
reportUpdates($settings,$updated,array_merge($pullFailed,array_diff($updateList,$updated)),$needsAttention);

?>