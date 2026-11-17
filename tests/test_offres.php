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

echo "\n3. Cartes propres à un métier\n";
$idx = file_get_contents($R.'/index.php');
check('électricité : cartes volets et portails retirées', [str_contains($idx, "svc_electricite_volets"), str_contains($idx, "svc_electricite_acces")], [false, false]);
check('chauffage : carte chauffe-eau dessinée', str_contains($idx, "['chauffe-eau', setting("), true);
check('la section « Nos interventions » a disparu', str_contains($idx, "svc_interv_"), false);

echo "\n3b. Ancienne liste enregistrée par l'admin : elle retrouve dessins et textes\n";
$DB['settings']['svc_electricite_offer_items'] = json_encode(['Dépannage et remise en service (urgence 24h/7j)', 'Mise aux normes NF C 15-100 obligatoire', 'Ma prestation à moi'], JSON_UNESCAPED_UNICODE);
settings_cache(true);
$cards = service_offer_cards(service_template('electricite')['offer_items']);
check('phrase d\'origine → carte complète', $cards[0]['icon'].' | '.$cards[0]['title'].' | '.($cards[0]['text'] !== '' ? 'texte' : 'vide'), 'urgence | Dépannage 24h/7j | texte');
check('deuxième phrase reconnue', $cards[1]['title'], 'Mise aux normes');
check('phrase écrite à la main gardée, avec la coche', $cards[2], ['icon'=>'check','title'=>'Ma prestation à moi','text'=>'']);
check('les 56 phrases d\'origine sont reconnues', count(service_offer_legacy()), 56);
check('l\'admin affiche la ligne convertie', service_offer_row_upgrade('Débouchage de canalisations et WC')[1], 'Débouchage');
unset($DB['settings']['svc_electricite_offer_items']); settings_cache(true);

echo "\n3c. Réalisations filtrées par métier\n";
check('adresse sans paramètre', url_with_query('/index.php?route=realisations', ['metier' => 'electricite']), '/index.php?route=realisations&metier=electricite');
check('adresse propre', url_with_query('/jura/realisations', ['metier' => 'plomberie']), '/jura/realisations?metier=plomberie');
check('paramètre vide ignoré', url_with_query('/realisations', ['metier' => '']), '/realisations');
foreach (array_keys(service_trade_defaults()) as $t) check("$t : un dessin de métier", service_trade_icon($t) !== 'check' && isset(service_icons()[service_trade_icon($t)]), true);
check('le bouton de la page métier mène au filtre', str_contains($idx, 'url_with_query(route_url(\'realisations\'), [\'metier\' => $sk])'), true);

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
