<?php
declare(strict_types=1);
// Photos fournies avec le site : cartes de l'accueil et fond des pages métier.

$DB = ['settings'=>[]];
function db_fetch_all(string $s, array $p=[]): array { global $DB;
  if (str_contains($s,'FROM settings')) { $o=[]; foreach($DB['settings'] as $k=>$v) $o[]=['setting_key'=>$k,'setting_value'=>$v]; return $o; }
  return []; }
function db_fetch(string $s, array $p=[]): ?array { global $DB;
  if (str_contains($s,'FROM settings')) return isset($DB['settings'][$p[0]]) ? ['id'=>1,'setting_key'=>$p[0],'setting_value'=>$DB['settings'][$p[0]]] : null;
  return null; }
function db_execute(string $s, array $p=[]): void {}

$_SERVER['SCRIPT_NAME']='/index.php';
$R = dirname(__DIR__);
require $R.'/includes/helpers.php';

$pass=0;$fail=0;
function check(string $l,mixed $g,mixed $w): void { global $pass,$fail;
  if($g===$w){$pass++;echo "  ok   $l\n";}
  else{$fail++;echo "  FAIL $l\n        obtenu  ".var_export($g,true)."\n        attendu ".var_export($w,true)."\n";} }

echo "\n1. Les cartes VMC, Portail et Électroménager ont une photo par défaut\n";
$cards = array_column(service_cards_v14(), 'image', 'link');
check('VMC', $cards['vmc'], 'assets/img/services/vmc.jpg');
check('Portail & Visiophone', $cards['portail'], 'assets/img/services/portail.jpg');
check('Automatismes & Électroménager', $cards['automatisme'], 'assets/img/services/electromenager.jpg');
check('Électricité inchangée (image de l\'admin)', $cards['electricite'], '');

echo "\n2. Les photos existent et restent légères\n";
foreach (['vmc','portail','visiophone','electromenager'] as $f) {
    $p = $R.'/assets/img/services/'.$f.'.jpg';
    check($f.'.jpg présent', is_file($p), true);
    check($f.'.jpg < 400 Ko', is_file($p) && filesize($p) < 400*1024, true);
}

echo "\n3. Une image choisie dans l'admin reste prioritaire\n";
$DB['settings']['home_service_cards_v14'] = json_encode([[], [], [], [], ['image'=>'storage/uploads/services/ma-vmc.jpg'], [], []]);
settings_cache(true);
$cards = array_column(service_cards_v14(), 'image', 'link');
check('carte VMC = image de l\'admin', $cards['vmc'], 'storage/uploads/services/ma-vmc.jpg');
check('carte Portail = photo par défaut', $cards['portail'], 'assets/img/services/portail.jpg');

echo "\n4. Fond des pages métier\n";
check('vmc', service_hero_image('vmc'), 'assets/img/services/vmc.jpg');
check('portail', service_hero_image('portail'), 'assets/img/services/visiophone.jpg');
check('automatisme', service_hero_image('automatisme'), 'assets/img/services/electromenager.jpg');
check('électricité sans image', service_hero_image('electricite'), '');
$DB['settings']['svc_portail_hero_image'] = 'storage/uploads/services/mon-portail.jpg';
settings_cache(true);
check('portail = image de l\'admin', service_hero_image('portail'), 'storage/uploads/services/mon-portail.jpg');

echo "\n".($fail===0 ? "TOUS LES TESTS PASSENT ($pass)" : "$fail ÉCHEC(S) sur ".($pass+$fail))."\n";
exit($fail===0?0:1);
