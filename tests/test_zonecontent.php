<?php
declare(strict_types=1);

$DB = ['settings'=>[], 'zones'=>[
    1 => ['id'=>1,'slug'=>'paris-ile-de-france','name'=>'Paris / Île-de-France','status'=>1,'sort_order'=>0],
    2 => ['id'=>2,'slug'=>'jura','name'=>'Jura','status'=>1,'sort_order'=>1],
]];
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

$_SERVER['SCRIPT_NAME']='/admin/zone_content.php';
$R = dirname(__DIR__);
require $R.'/includes/helpers.php';
require $R.'/includes/admin_fields.php';
require $R.'/includes/zone_content.php';

$pass=0;$fail=0;
function check(string $l,mixed $g,mixed $w): void { global $pass,$fail;
  if($g===$w){$pass++;echo "  ok   $l\n";}
  else{$fail++;echo "  FAIL $l\n        obtenu  ".var_export($g,true)."\n        attendu ".var_export($w,true)."\n";} }

$IDF = 'paris-ile-de-france';
$champs = zone_content_fields($IDF);

echo "\n1. Chaque texte proposé correspond à un champ réellement affiché\n";
$public = '';
foreach (['index.php','includes/render.php','includes/helpers.php'] as $f) $public .= file_get_contents($R.'/'.$f);
$fantomes = [];
foreach (array_keys($champs) as $key) {
    $def = zone_content_field_def($key);
    if ($def) continue;                                  // déclaré au catalogue : déjà garanti
    if (str_contains($public, "setting('".$key."'")) continue;
    if (preg_match('/^svc_(electricite|plomberie|chauffage|climatisation)_(\w+)$/', $key, $m)
        && array_key_exists($m[2], service_template($m[1]) ?? [])) continue;
    $fantomes[] = $key;
}
check('aucune clé sans effet', $fantomes, []);
echo "  info  ".count($champs)." textes dans le contenu Île-de-France\n";

echo "\n2. Rien n'est écrit tant qu'on n'applique pas\n";
set_zone_context(get_zone_by_slug($IDF));
$avant = $DB['settings'];
$apercu = zone_content_preview($IDF);
check('la base est intacte après un aperçu', $DB['settings'], $avant);
check('l\'aperçu couvre tous les textes', count($apercu), count($champs));
check('tout est signalé comme à appliquer', count(array_filter($apercu, fn($l)=>!$l['identique'])), count($champs));
check('rien n\'est marqué déjà retouché', count(array_filter($apercu, fn($l)=>$l['personnalise'])), 0);

echo "\n3. L'application n'écrit que dans la zone\n";
$n = zone_content_apply($IDF, array_keys($champs));
check('tous les textes appliqués', $n, count($champs));
set_zone_context(null);
check('le site global est inchangé', $DB['settings']['home_title'] ?? '(absent)', '(absent)');
check('  → il affiche toujours son texte', setting('home_title','Votre expert multitechnique'), 'Votre expert multitechnique');
set_zone_context(get_zone_by_slug($IDF));
check('la zone affiche le nouveau texte', setting('home_title','Votre expert multitechnique'), 'Votre expert multitechnique en');
check('  → et la fin en couleur',          setting('home_title_hl','en urgence'), 'Île-de-France');

echo "\n4. Les autres zones ne sont pas touchées\n";
set_zone_context(get_zone_by_slug('jura'));
check('le Jura garde le texte global', setting('home_title','Votre expert multitechnique'), 'Votre expert multitechnique');
check('  → et son accroche',             setting('services_lead','origine'), 'origine');

echo "\n5. Les pages métier reçoivent bien leur version francilienne\n";
set_zone_context(get_zone_by_slug($IDF));
check('électricité', service_template('electricite')['desc'], 'Dépannage, installation, mise aux normes et rénovation électrique à Paris et dans toute l\'Île-de-France.');
check('plomberie',   str_contains(service_template('plomberie')['desc'], 'Île-de-France'), true);
check('les listes du métier sont intactes', count(service_template('electricite')['badges']), 4);

echo "\n6. Bloc groupé : le titre change, les 8 arguments restent\n";
check('accroche « Pourquoi nous » appliquée', str_contains(why_us_settings()['lead'], 'bâti francilien'), true);
check('les 8 arguments sont préservés',       count(why_us_settings()['items']), 8);
set_zone_context(null);
check('le global garde son accroche', why_us_settings()['lead'], 'Des artisans qualifiés, des délais respectés, des devis clairs. Chaque intervention est réalisée avec rigueur et professionnalisme.');

echo "\n7. Réappliquer ne change plus rien\n";
set_zone_context(get_zone_by_slug($IDF));
check('seconde application sans effet', zone_content_apply($IDF, array_keys($champs)), 0);
check('l\'aperçu ne propose plus rien',  count(array_filter(zone_content_preview($IDF), fn($l)=>!$l['identique'])), 0);

echo "\n8. Un texte retouché à la main est protégé\n";
set_setting('home_lead', 'Ma propre accroche');
settings_cache(true);
$l = null; foreach (zone_content_preview($IDF) as $x) if ($x['key']==='home_lead') $l = $x;
check('signalé comme déjà retouché', $l['personnalise'] ?? null, true);
check('et proposé de nouveau',        $l['identique'] ?? null, false);
check('il reste tel quel si non coché', setting('home_lead',''), 'Ma propre accroche');

echo "\n9. Retour en arrière possible\n";
set_setting('home_title', '');
settings_cache(true);
check('champ vidé → texte global retrouvé', setting('home_title','Votre expert multitechnique'), 'Votre expert multitechnique');

echo "\n10. Refus des cas invalides\n";
check('clé étrangère ignorée', zone_content_apply($IDF, ['home_title','clef_inventee']), 1);
set_zone_context(null);
check('rien ne s\'applique hors zone', zone_content_apply($IDF, array_keys($champs)), 0);
check('zone sans contenu prêt',        zone_content_pack('jura'), null);

echo "\n".($fail===0 ? "TOUS LES TESTS PASSENT ($pass)" : "$fail ÉCHEC(S) sur ".($pass+$fail))."\n";
exit($fail===0?0:1);
