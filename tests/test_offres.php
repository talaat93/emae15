<?php
declare(strict_types=1);
// Pages métier : cartes de prestations dessinées, bandeau des marques, logo lisible.

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

echo "\n1. Chaque dessin déclaré existe en PNG, et chaque PNG est déclaré\n";
$declared = array_keys(service_icons());
$files = array_map(fn($f) => basename($f, '.png'), glob($R.'/assets/img/icons/*.png'));
sort($declared); sort($files);
check('mêmes dessins des deux côtés', $files, $declared);
check('la coche par défaut existe', in_array('check', $declared, true), true);
foreach (glob($R.'/assets/img/icons/*.png') as $f) {
    [$w, $h] = getimagesize($f);
    if ($w !== 128 || $h !== 128 || filesize($f) > 12000) check(basename($f).' : 128 px et léger', [$w, $h, filesize($f) <= 12000], [128, 128, true]);
}
check('tous les dessins font 128 px et moins de 12 Ko', true, true);

echo "\n2. Les 7 métiers ont 8 cartes complètes, avec des dessins existants\n";
foreach (service_trade_defaults() as $t => $d) {
    $cards = service_offer_cards($d['offer_items']);
    $ok = count($cards) === 8;
    foreach ($cards as $c) $ok = $ok && $c['icon'] !== 'check' && $c['title'] !== '' && $c['text'] !== '' && mb_strlen($c['text']) <= 110;
    check("$t : 8 cartes, dessin + titre + texte court", $ok, true);
}

echo "\n3. Plus d'emoji dans les cartes propres à un métier\n";
$idx = file_get_contents($R.'/index.php');
check('volets, portails et chauffe-eau dessinés', [str_contains($idx, "['volet', setting("), str_contains($idx, "['portail', setting("), str_contains($idx, "['chauffe-eau', setting(")], [true, true, true]);
check('la section « Nos interventions » a disparu', str_contains($idx, "svc_interv_"), false);

echo "\n4. Bandeau des marques\n";
check('chauffage : des marques par défaut', count(service_template('chauffage')['brands']) >= 10, true);
check('climatisation : des marques par défaut', count(service_template('climatisation')['brands']) >= 10, true);
check('électricité : pas de bandeau', service_template('electricite')['brands'], []);
$DB['settings']['svc_vmc_brands'] = json_encode(['Aldes', 'Atlantic'], JSON_UNESCAPED_UNICODE);
settings_cache(true);
check('une liste saisie dans l\'admin s\'affiche', service_template('vmc')['brands'], ['Aldes', 'Atlantic']);

echo "\n5. Logo : version lisible pour le logo EMAE, logo envoyé affiché tel quel\n";
check('logo EMAE reconnu', site_logo_is_emae('storage/uploads/logos/20260412174717-7d1ee290.png'), true);
check('autre logo laissé tel quel', site_logo_is_emae('storage/uploads/logos/nouveau-logo.png'), false);
check('image du logo sans la mention présente', is_file($R.'/assets/img/logo-emae-mark.png'), true);

echo "\n".($fail===0 ? "TOUS LES TESTS PASSENT ($pass)" : "$fail ÉCHEC(S) sur ".($pass+$fail))."\n";
exit($fail===0?0:1);
