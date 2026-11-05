<?PHP
# The settings page shares its process with other plugins, so these names must stay unique

# Manager is only reported from Unraid 7.0; before that a template marks a dockerMan container
function caAutoUpdateIsThirdParty($liveContainer,$cachedInfo) {
  return array_key_exists('Manager',$liveContainer) ? $liveContainer['Manager'] != "dockerman" : empty($cachedInfo['template']);
}

# CreateDocker.php cannot update a container without a template; only 'false' means a newer image exists
function caAutoUpdateHasUpdate($cachedInfo) {
  return $cachedInfo['updated'] === "false" && ! empty($cachedInfo['template']);
}

# A changed image ID is the evidence that an update happened
function caAutoUpdateChangedContainers($names,$info,$liveContainers) {
  $imagesAfter = array_column($liveContainers,'ImageId','Name');
  return array_values(array_filter($names,function($name) use ($info,$imagesAfter) {
    return isset($imagesAfter[$name]) && $imagesAfter[$name] != $info[$name]['ImageId'];
  }));
}
?>
