#!/usr/bin/php
<?PHP
# Same request as the Docker page's rebuild; its own process because CreateDocker.php can only load once
$docroot = "/usr/local/emhttp";
$_SERVER['DOCUMENT_ROOT'] = $docroot;
$_GET = ['updateContainer'=>true,'mute'=>true,'ct'=>array_slice($argv,1)];
include("$docroot/plugins/dynamix.docker.manager/include/CreateDocker.php");
?>
