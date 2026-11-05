<?PHP
###############################################################
#                                                             #
# Community Applications copyright 2015-2026, Andrew Zawadzki #
#                                                             #
###############################################################

########################################################
#                                                      #
# Avoids having to write this line over and over again #
#                                                      #
########################################################

function getPost($setting,$default) {
  return isset($_POST[$setting]) ? urldecode(($_POST[$setting])) : $default;
}
function getPostArray($setting) {
  return $_POST[$setting] ?? [];
}
##################################################################
#                                                                #
# 2 Functions to avoid typing the same lines over and over again #
#                                                                #
##################################################################

function readJsonFile($filename) {
  return json_decode(@file_get_contents($filename),true);
}

function writeJsonFile($filename,$jsonArray) {
  file_put_contents($filename,json_encode($jsonArray, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
}

# Only containers the page offered can change; hidden or unavailable ones keep their saved toggle
function mergeAutoUpdateContainers($saved,$selected,$offered) {
  foreach ($offered as $container) {
    unset($saved[$container]);
  }
  foreach ($selected as $container) {
    $saved[$container] = ['name'=>$container,'update'=>true];
  }
  return $saved;
}

# Whole calendar days since a version's date; a future date counts as 0
function pluginAgeDays($versionDate,$now) {
  $interval = date_diff(date_create($versionDate->format("Y-m-d")),date_create($now->format("Y-m-d")));
  return $interval->invert ? 0 : $interval->days;
}

?>