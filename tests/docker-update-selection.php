<?php
// Runs only the updater's real selection block; never pulls or changes containers.
set_exception_handler(function($e){fwrite(STDERR,$e->getMessage()."\n");exit(1);});
$base=dirname(__DIR__).'/source/ca.update.applications/usr/local/emhttp/plugins/ca.update.applications';
$source=file_get_contents($argv[1] ?? "$base/scripts/updateDocker.php");
require_once $argv[2] ?? "$base/include/dockerAutoUpdate.php";
$start=strpos($source,'# workaround for incorrect caching in dockerMan');
$end=strpos($source,'if ( ! isset($updateList) )');
if($start===false || $end===false || $end<=$start) throw new Exception('Selection block not found');
$selection=substr($source,$start,$end-$start);
function logger($message) {}
class FixtureDockerClient {
  private $containers;
  public function __construct(array $containers) {$this->containers=$containers;}
  public function getDockerContainers() {return $this->containers;}
}
function selectUpdates($selection,$info,$live,$settings) {
  $DockerClient=new FixtureDockerClient($live);
  eval($selection);
  $names=$updateList ?? [];sort($names);return $names;
}
function containerFixture($name,$manager='dockerman',$running=true) {
  return ['Name'=>$name,'Manager'=>$manager,'Running'=>$running,'Image'=>'fixture:latest','ImageId'=>'fixture-image','Id'=>'fixture-container'];
}
// Releases before 7.0 do not report Manager at all.
function legacyFixture($name) {
  $container=containerFixture($name);
  unset($container['Manager']);
  return $container;
}
$template='/boot/config/plugins/dockerMan/templates-user/my-fixture.xml';
$live=[containerFixture('managed'),containerFixture('compose-service',false),containerFixture('stopped-third-party',false,false),containerFixture('current')];
$info=['managed'=>['updated'=>'false','template'=>$template],'compose-service'=>['updated'=>null,'template'=>null],'stopped-third-party'=>['updated'=>null,'template'=>null],'current'=>['updated'=>'true','template'=>$template],'deleted-service'=>['updated'=>'false','running'=>true,'template'=>$template]];
$all=['global'=>['dockerUpdateAll'=>'yes','dockerIgnoreThirdParty'=>'no']];
$thirdPartyIgnored=['global'=>['dockerUpdateAll'=>'yes','dockerIgnoreThirdParty'=>'yes']];
$optIn=['global'=>['dockerUpdateAll'=>'no','dockerIgnoreThirdParty'=>'no'],'containers'=>['managed'=>['name'=>'managed'],'compose-service'=>['name'=>'compose-service'],'deleted-service'=>['name'=>'deleted-service']]];
$legacyInfo=['legacy-managed'=>['updated'=>'false','template'=>$template],'legacy-third-party'=>['updated'=>'false','template'=>false],'legacy-current'=>['updated'=>'true','template'=>$template]];
$legacyLive=[legacyFixture('legacy-managed'),legacyFixture('legacy-third-party'),legacyFixture('legacy-current')];
$cases=[
 ['stale cache excluded; third-party never selected',$info,$live,$all,['managed']],
 ['existing third-party exclusion setting respected',$info,$live,$thirdPartyIgnored,['managed']],
 ['per-container opt-in retained; deleted and third-party opt-ins excluded',$info,$live,$optIn,['managed']],
 ['no live containers means no updates',$info,[],$all,[]],
 ['third-party container with unknown update status not selected',['new-compose'=>['updated'=>null,'template'=>null]],[containerFixture('new-compose',false)],$all,[]],
 ['stopped third-party container not selected',$info,[$live[2]],$all,[]],
 ['managed container without a template not selected',['no-template'=>['updated'=>'false','template'=>false]],[containerFixture('no-template')],$all,[]],
 ['only the string false counts as an update',['a'=>['updated'=>'true','template'=>$template],'b'=>['updated'=>'NULL','template'=>$template],'c'=>['updated'=>'undef','template'=>$template],'d'=>['updated'=>null,'template'=>$template]],[containerFixture('a'),containerFixture('b'),containerFixture('c'),containerFixture('d')],$all,[]],
 ['no Manager key: a container with a template is updated',$legacyInfo,$legacyLive,$all,['legacy-managed']],
 ['no Manager key: ignoring third-party keeps a container with a template',$legacyInfo,$legacyLive,$thirdPartyIgnored,['legacy-managed']],
];
$failures=0;
foreach($cases as [$label,$cache,$containers,$settings,$expected]) {
  $actual=selectUpdates($selection,$cache,$containers,$settings);sort($expected);
  if($actual!==$expected) {$failures++;echo 'FAIL '.$label.': '.json_encode($actual).' expected '.json_encode($expected)."\n";}
  else echo 'PASS '.$label."\n";
}
$changed=[
 ['image changed: reported as updated',caAutoUpdateChangedContainers(['a'],['a'=>['ImageId'=>'old']],[['Name'=>'a','ImageId'=>'new']]),['a']],
 ['image unchanged: not reported as updated',caAutoUpdateChangedContainers(['a'],['a'=>['ImageId'=>'old']],[['Name'=>'a','ImageId'=>'old']]),[]],
 ['container gone after the update: not reported as updated',caAutoUpdateChangedContainers(['a'],['a'=>['ImageId'=>'old']],[]),[]],
 ['containers outside the update list are ignored',caAutoUpdateChangedContainers(['a'],['a'=>['ImageId'=>'old'],'b'=>['ImageId'=>'old']],[['Name'=>'a','ImageId'=>'old'],['Name'=>'b','ImageId'=>'new']]),[]],
];
foreach($changed as [$label,$actual,$expected]) {
  if($actual!==$expected) {$failures++;echo 'FAIL '.$label.': '.json_encode($actual).' expected '.json_encode($expected)."\n";}
  else echo 'PASS '.$label."\n";
}
echo (count($cases)+count($changed)).' cases; '.$failures." failures\n";
exit($failures ? 1 : 0);
