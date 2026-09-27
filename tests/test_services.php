<?php
declare(strict_types=1);

$DB = ['settings'=>[], 'zones'=>[1=>['id'=>1,'slug'=>'jura','name'=>'Jura','status'=>1,'sort_order'=>0]]];
function db_fetch_all(string $s, array $p=[]): array { global $DB;
  if (str_contains($s,'FROM settings')) { $o=[]; foreach($DB['settings'] as $k=>$v) $o[]=['setting_key'=>$k,'setting_value'=>$v]; return $o; }
  if (str_contains($s,'FROM zones')) return array_values($DB['zones']);
  return []; }
function db_fetch(string $s, array $p=[]): ?array { global $DB;
  if (str_contains($s,'FROM settings')) return isset($DB['settings'][$p[0]]) ? ['id'=>1,'setting_key'=>$p[0],'setting_value'=>$DB['settings'][$p[0]]] : null;
  if (str_contains($s,'FROM zones')) { foreach($DB['zones'] as $z) if ((string)$z['id']===(string)$p[0]||$z['slug']===$p[0]) return $z; }
  return null; }
function db_execute(string $s, array $p=[]): void { global $DB;
  if (str_starts_with($s,'INSERT INTO settings')) { $DB['settings'][$p[0]]=$p[1]; return; }
  if (str_starts_with($s,'UPDATE settings'))      { $DB['settings'][$p[1]]=$p[0]; return; }
  if (str_starts_with($s,'DELETE FROM settings')) { unset($DB['settings'][$p[0]]); return; } }

$_SERVER['SCRIPT_NAME']='/index.php';
$R = dirname(__DIR__);
$S = __DIR__;
require $R.'/includes/helpers.php';
require $R.'/includes/admin_fields.php';

$pass=0;$fail=0;
function check(string $l,mixed $g,mixed $w): void { global $pass,$fail;
  if($g===$w){$pass++;echo "  ok   $l\n";}
  else{$fail++;echo "  FAIL $l\n        obtenu  ".var_export($g,true)."\n        attendu ".var_export($w,true)."\n";} }

/** Reconstruit le tableau tel qu'il était écrit en dur dans index.php. */
function baseline(): array {
    global $S;
    $code = file_get_contents($S.'/tpls_baseline.txt');
    $code = preg_replace('/^\s*\$tpls = \[/', 'return [', $code, 1);
    return eval($code);
}

echo "\n1. Aucune régression : mêmes textes qu'avant la migration\n";
$base = baseline();
check('les 4 métiers sont reconnus', array_keys($base), ['electricite','plomberie','chauffage','climatisation']);
foreach ($base as $trade => $want) {
    $got = service_template($trade);
    check("« $trade » identique au code d'origine",
        [$got['label'],$got['desc'],$got['offer_title'],$got['badges'],$got['offer_items'],$got['interv'],$got['faq']],
        [$want['label'],$want['desc'],$want['offer_title'],$want['badges'],$want['offer_items'],$want['interv'],$want['faq']]);
}
check('métier inconnu → rien', service_template('maconnerie'), null);

echo "\n2. Volume réellement rendu modifiable\n";
$n = 0;
foreach ($base as $t => $d) $n += 3 + count($d['badges']) + count($d['offer_items']) + count($d['interv'])*3 + count($d['faq'])*2;
echo "  info  $n textes des pages métier passent en base\n";
check('chaque métier a 4 badges',        array_map(fn($d)=>count($d['badges']), $base), ['electricite'=>4,'plomberie'=>4,'chauffage'=>4,'climatisation'=>4]);
check('chaque métier a 8 prestations',   array_map(fn($d)=>count($d['offer_items']), $base), ['electricite'=>8,'plomberie'=>8,'chauffage'=>8,'climatisation'=>8]);
check('chaque métier a 8 interventions', array_map(fn($d)=>count($d['interv']), $base), ['electricite'=>8,'plomberie'=>8,'chauffage'=>8,'climatisation'=>8]);
check('chaque métier a 4 questions',     array_map(fn($d)=>count($d['faq']), $base), ['electricite'=>4,'plomberie'=>4,'chauffage'=>4,'climatisation'=>4]);

echo "\n3. Chaque métier se rédige séparément\n";
set_setting('svc_electricite_label', 'Électricité générale');
settings_cache(true);
check('l\'électricité est personnalisée', service_template('electricite')['label'], 'Électricité générale');
check('la plomberie n\'a pas bougé',      service_template('plomberie')['label'],  'Plomberie');

set_setting('svc_plomberie_badges', json_encode(['Fuite 24h/24','Débouchage'], JSON_UNESCAPED_UNICODE));
settings_cache(true);
check('badges plomberie remplacés', service_template('plomberie')['badges'], ['Fuite 24h/24','Débouchage']);
check('badges électricité intacts', count(service_template('electricite')['badges']), 4);

echo "\n4. Les listes à plusieurs colonnes gardent leur forme\n";
set_setting('svc_chauffage_interv', json_encode([['🔥','Panne','Diagnostic rapide.'],['♨️','PAC','Air/eau.']], JSON_UNESCAPED_UNICODE));
settings_cache(true);
$iv = service_template('chauffage')['interv'];
check('2 tuiles enregistrées', count($iv), 2);
check('icône, titre et texte préservés', $iv[0], ['🔥','Panne','Diagnostic rapide.']);

echo "\n5. Une liste vidée revient au texte d'origine\n";
set_setting('svc_plomberie_badges', '');
settings_cache(true);
check('retour aux 4 badges d\'origine', service_template('plomberie')['badges'], $base['plomberie']['badges']);

echo "\n6. Une zone peut avoir ses propres textes métier\n";
set_zone_context(get_zone_by_slug('jura'));
check('la zone hérite du global', service_template('electricite')['label'], 'Électricité générale');
set_setting('svc_electricite_label', 'Électricité Jura');
settings_cache(true);
check('la zone a sa version', service_template('electricite')['label'], 'Électricité Jura');
set_zone_context(null);
check('le global est intact',   service_template('electricite')['label'], 'Électricité générale');

echo "\n7. Une donnée abîmée ne casse pas la page\n";
set_setting('svc_climatisation_badges', 'ceci-nest-pas-du-json');
settings_cache(true);
check('repli sur le texte d\'origine', service_template('climatisation')['badges'], $base['climatisation']['badges']);
set_setting('svc_climatisation_badges', '[]');
settings_cache(true);
check('liste vide → texte d\'origine', service_template('climatisation')['badges'], $base['climatisation']['badges']);

echo "\n8. Les trois nouveaux métiers\n";
foreach (['vmc'=>'VMC & Ventilation','portail'=>'Portail & Visiophone','automatisme'=>'Automatismes & Électroménager'] as $t => $nom) {
    $d = service_template($t);
    check("« $t » existe",          $d !== null, true);
    check("  → son nom",            $d['label'], $nom);
    check("  → ses pastilles",      count($d['badges']) >= 3, true);
    check("  → ses prestations",    count($d['offer_items']) >= 6, true);
    check("  → ses tuiles",         count($d['interv']), 8);
    check("  → sa FAQ",             count($d['faq']), 4);
}
check('sept métiers au total', count(service_trade_defaults()), 7);

echo "\n9. Chaque adresse mène au bon métier\n";
foreach ([
    'vmc' => 'vmc', 'ventilation' => 'vmc', 'installation-vmc-double-flux' => 'vmc',
    'portail' => 'portail', 'visiophone' => 'portail', 'motorisation-portail' => 'portail',
    'interphone' => 'portail', 'digicode' => 'portail',
    'volet-roulant' => 'automatisme', 'domotique' => 'automatisme', 'electromenager' => 'automatisme',
    'electricite' => 'electricite', 'electricien-paris' => 'electricite',
    'plomberie' => 'plomberie', 'chauffage' => 'chauffage', 'climatisation' => 'climatisation',
] as $adresse => $attendu) {
    check("/$adresse/ → $attendu", service_detect_trade($adresse), $attendu);
}
check('une adresse quelconque ne déclenche rien', service_detect_trade('mentions-legales'), null);
check('« climatisation » n\'est pas capté par ventilation', service_detect_trade('climatisation'), 'climatisation');

echo "\n10. Chaque métier a son écran d'administration\n";
$pages = array_keys(admin_page_catalog());
foreach (['metier_vmc','metier_portail','metier_automatisme'] as $p) check("écran « $p »", in_array($p,$pages,true), true);

echo "\n".($fail===0 ? "TOUS LES TESTS PASSENT ($pass)" : "$fail ÉCHEC(S) sur ".($pass+$fail))."\n";
exit($fail===0?0:1);
