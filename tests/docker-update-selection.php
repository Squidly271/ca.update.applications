<?php
// Runs only the updater's real selection block; never pulls or changes containers.
set_exception_handler(function($e){fwrite(STDERR,$e->getMessage()."\n");exit(1);});
$source=file_get_contents($argv[1] ?? dirname(__DIR__).'/source/ca.update.applications/usr/local/emhttp/plugins/ca.update.applications/scripts/updateDocker.php');
$start=strpos($source,'# workaround for incorrect caching in dockerMan');
$end=strpos($source,'if ( ! isset($updateList) )');
if($start===false || $end===false || $end<=$start) throw new Exception('Selection block not found');
$selection=substr($source,$start,$end-$start);
function logger($message) {}
class FixtureDockerClient {
  public function __construct(private array $containers) {}
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
$live=[containerFixture('managed'),containerFixture('compose-service',false),containerFixture('stopped-third-party',false,false),containerFixture('current')];
$info=['managed'=>['updated'=>'false'],'compose-service'=>['updated'=>null],'stopped-third-party'=>['updated'=>null],'current'=>['updated'=>'true'],'deleted-service'=>['updated'=>'false','running'=>true]];
$all=['global'=>['dockerUpdateAll'=>'yes','dockerIgnoreThirdParty'=>'no']];
$thirdPartyIgnored=['global'=>['dockerUpdateAll'=>'yes','dockerIgnoreThirdParty'=>'yes']];
$optIn=['global'=>['dockerUpdateAll'=>'no','dockerIgnoreThirdParty'=>'no'],'containers'=>['compose-service'=>['name'=>'compose-service'],'deleted-service'=>['name'=>'deleted-service']]];
$cases=[
 ['stale cache excluded; live third-party and managed retained',$info,$live,$all,['managed','compose-service','stopped-third-party']],
 ['existing third-party exclusion setting respected',$info,$live,$thirdPartyIgnored,['managed']],
 ['per-container opt-in retained; deleted opt-in excluded',$info,$live,$optIn,['compose-service']],
 ['no live containers means no updates',$info,[],$all,[]],
 ['new third-party container with unknown update status retained',['new-compose'=>['updated'=>null]],[containerFixture('new-compose',false)],$all,['new-compose']],
 ['stopped genuine third-party container retained',$info,[$live[2]],$all,['stopped-third-party']],
];
$failures=0;
foreach($cases as [$label,$cache,$containers,$settings,$expected]) {
  $actual=selectUpdates($selection,$cache,$containers,$settings);sort($expected);
  if($actual!==$expected) {$failures++;echo 'FAIL '.$label.': '.json_encode($actual).' expected '.json_encode($expected)."\n";}
  else echo 'PASS '.$label."\n";
}
echo count($cases).' cases; '.$failures." failures\n";
exit($failures ? 1 : 0);
