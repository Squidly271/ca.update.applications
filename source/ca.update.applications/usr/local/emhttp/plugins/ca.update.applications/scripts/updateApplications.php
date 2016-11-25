#!/usr/bin/php
<?PHP
require_once("/usr/local/emhttp/plugins/dynamix.plugin.manager/include/PluginHelpers.php");

$communityPaths['autoUpdateKillSwitch']          = "/usr/local/emhttp/plugins/ca.update.applications/autoUpdateKill";
$communityPaths['autoUpdateSettings']            = "/boot/config/plugins/ca.update.applications/AutoUpdateSettings.json";

function logger($string) {
  $string = escapeshellarg($string);
  shell_exec("logger $string");
}

function checkPluginUpdate($filename) {
  global $unRaidVersion;
  
  $filename = basename($filename);
  $installedVersion = plugin("version","/var/log/plugins/$filename");
  if ( is_file("/tmp/plugins/$filename") ) {
    $upgradeVersion = plugin("version","/tmp/plugins/$filename");
  } else {
    $upgradeVersion = "0";
  }
  if ( $installedVersion < $upgradeVersion ) {
    $unRaid = plugin("unRAID","/tmp/plugins/$filename");
    if ( $unRaid === false || version_compare($unRaidVersion['version'],$unRaid,">=") ) {
      return true;
    } else {
      return false;
    }
  }
  return false;
}

function notify($event,$subject,$description,$message="",$type="normal") {
  $command = '/usr/local/emhttp/plugins/dynamix/scripts/notify -e "'.$event.'" -s "'.$subject.'" -d "'.$description.'" -m "'.$message.'" -i "'.$type.'"';
  shell_exec($command);
}
$unRaidVersion = parse_ini_file("/etc/unraid-version");
$appList = json_decode(@file_get_contents($communityPaths['autoUpdateSettings']),true);
if ( ! $appList ) {
  $appList['community.applications.plg'] = "true";
  $appList['fix.common.problems.plg'] = "true";
  $appList['ca.update.applications.plg'] = "true";
}
if ( ! isset($appList['delay']) ) {
  $appList['delay'] = 3;
}

$pluginsInstalled = array("ca.update.applications.plg") + array_diff(scandir("/var/log/plugins"),array(".","..","ca.update.applications.plg"));
exec("logger Community Applications Auto Update Running");
exec("logger Checking for available plugin updates");
exec("/usr/local/emhttp/plugins/dynamix.plugin.manager/scripts/plugin checkall");
$currentDate = date_create(now);
foreach ($pluginsInstalled  as $plugin) {
  if ( is_file($communityPaths['autoUpdateKillSwitch']) ) {
    logger("Auto Update Kill Switch Activated.  Most likely details why on the forums");
    notify("Community Applications","AutoUpdate Kill Switch has been activated.  See Forum for details","","error");
    break;
  }
  if ( ! is_file("/boot/config/plugins/$plugin") ) {
    continue;
  }
  if ( $plugin == "unRAIDServer.plg" ) { continue; }
  if ( checkPluginUpdate($plugin) ) {
    if ( $appList['Global'] == "true" || $appList[$plugin] ) {
      $pluginVersion = plugin("version","/tmp/plugins/$plugin");
      $pluginVersionOriginal = $pluginVersion;
      $installedVersion = plugin("version","/var/log/plugins/$plugin");
      if ( $pluginVersion == $installedVersion) continue;
      if ( ! $pluginVersion ) continue;
      $pluginVersion = preg_replace('/[^0-9.]+/', "", $pluginVersion); # get rid of any alphabetic suffixes on the version date
      $pluginDate = date_create_from_format("Y.m.d",$pluginVersion);
      if ( ! $pluginDate ) {
        logger("$plugin has a non-conforming version string.  Cannot autoupdate");
        continue;
      }
      $interval = date_diff($currentDate,$pluginDate);
      $age = $interval->format("$R%a");
      if ( ($age >= $appList['delay']) || ($appList['delay'] == 0) ) {
        logger("Auto Updating $plugin");
        exec("mkdir -p /boot/config/plugins-old-versions/$plugin/$installedVersion");
        copy("/var/log/plugins/$plugin","/boot/config/plugins-old-versions/$plugin/$installedVersion/$plugin");
        unset($output);
        exec("/usr/local/emhttp/plugins/dynamix.plugin.manager/scripts/plugin update '$plugin'",$output,$error);
        if ( ! $error ) {
          if ( $appList['notify'] != "no" ) {
            notify("Community Applications","Application Auto Update",$plugin." Automatically Updated");
          }
        } else {
          foreach ($output as $line) {
            logger($line);
          }
        }
      } else {
        logger("$plugin version $pluginVersionOriginal does not meet age requirements to update");
      }
    } else {
      logger("Update available for $plugin (Not set to Auto Update)");
    }
  }
}
?>
