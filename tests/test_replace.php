<?php
declare(strict_types=1);

$DB = [
  'settings' => [
    'company_regions'        => 'Île-de-France et Occitanie',
    'reals_page_title'       => 'Nos chantiers en Occitanie',
    'z:jura:reals_page_title'=> 'Chantiers Occitanie côté Jura',
    'why_us_settings'        => '{"title":"Experts en Occitanie","items":[{"title":"Présence locale","text":"Toute l\'Occitanie"}]}',
    'ovh_app_secret'         => 'occitanie-secret-ne-pas-toucher',
    'color_navy'             => '#12204d',
  ],
  'zones' => [ 1 => ['id'=>1,'slug'=>'jura','name'=>'Jura','status'=>1,'sort_order'=>0] ],
  'pages' => [ 7 => ['id'=>7,'title'=>'Électricien Occitanie','excerpt'=>'Intervention en Occitanie et alentours','content_html'=>'','meta_title'=>'','meta_description'=>'','slug'=>'e','status'=>'published','page_type'=>'page','sort_order'=>0,'zone_id'=>null] ],
  'realisations' => [ 3 => ['id'=>3,'title'=>'Tableau Toulouse','description'=>'Chantier en Occitanie','city'=>'Toulouse','service_type'=>'','is_visible'=>1,'sort_order'=>0,'zone_id'=>null,'image_path'=>''] ],
  'reviews' => [ 5 => ['id'=>5,'author_name'=>'Nadia','content'=>'Super équipe, rapide en Occitanie !','city'=>'Nîmes','service_type'=>'','is_visible'=>1,'sort_order'=>0,'zone_id'=>null] ],
];

function db_fetch_all(string $s, array $p = []): array { global $DB;
  if (str_contains($s,'FROM settings')) { $o=[]; foreach($DB['settings'] as $k=>$v) $o[]=['setting_key'=>$k,'setting_value'=>$v]; return $o; }
  foreach (['zones','pages','realisations','reviews'] as $t) if (str_contains($s,'FROM '.$t)) return array_values($DB[$t]);
  return []; }
function db_fetch(string $s, array $p = []): ?array { global $DB;
  if (str_contains($s,'FROM settings')) return isset($DB['settings'][$p[0]]) ? ['id'=>1,'setting_key'=>$p[0],'setting_value'=>$DB['settings'][$p[0]]] : null;
  if (str_contains($s,'FROM zones')) { foreach($DB['zones'] as $z) if ((string)$z['id']===(string)$p[0]||$z['slug']===$p[0]) return $z; return null; }
  foreach (['pages','realisations','reviews'] as $t) if (str_contains($s,'FROM '.$t)) return $DB[$t][(int)$p[0]] ?? null;
  return null; }
function db_execute(string $s, array $p = []): void { global $DB;
  if (str_starts_with($s,'INSERT INTO settings')) { $DB['settings'][$p[0]]=$p[1]; return; }
  if (str_starts_with($s,'UPDATE settings'))      { $DB['settings'][$p[1]]=$p[0]; return; }
  if (str_starts_with($s,'DELETE FROM settings')) { unset($DB['settings'][$p[0]]); return; }
  if (preg_match('/^UPDATE (pages|realisations|reviews) SET (\w+) = \? WHERE id = \?$/',$s,$m)) { $DB[$m[1]][(int)$p[1]][$m[2]]=$p[0]; return; } }

$_SERVER['SCRIPT_NAME'] = '/admin/search.php';
$R = dirname(__DIR__);
require $R.'/includes/helpers.php';
require $R.'/includes/admin_fields.php';
require $R.'/includes/zone_vars.php';
require $R.'/includes/admin_replace.php';

$pass=0;$fail=0;
function check(string $l,mixed $g,mixed $w): void { global $pass,$fail;
  if($g===$w){$pass++;echo "  ok   $l\n";}
  else{$fail++;echo "  FAIL $l\n        obtenu  ".var_export($g,true)."\n        attendu ".var_export($w,true)."\n";} }
function refs(array $hits): array { return array_column($hits,'ref'); }
function findHit(array $hits, string $ref): ?array { foreach($hits as $h) if($h['ref']===$ref) return $h; return null; }

echo "\n1. La recherche trouve « Occitanie » dans toutes les sources\n";
$hits = bulk_scan('Occitanie');
$r = refs($hits);
check('réglage enregistré',             in_array('set:reals_page_title',$r,true) || in_array('cat:page_realisations:reals_page_title',$r,true), true);
check('surcharge de zone',              in_array('set:z:jura:reals_page_title',$r,true), true);
check('bloc JSON (Pourquoi nous)',      in_array('set:why_us_settings',$r,true), true);
check('identité — régions couvertes',   in_array('set:company_regions',$r,true), true);
check('page',                           in_array('row:pages:7:title',$r,true), true);
check('réalisation',                    in_array('row:realisations:3:description',$r,true), true);
check('avis client',                    in_array('row:reviews:5:content',$r,true), true);

echo "\n2. Un texte jamais enregistré est trouvé quand même\n";
// zones_page_settings n'est pas en base : la valeur vient du code.
$zp = findHit($hits,'cat:page_zones:zp_title_hl');
check('texte d\'origine du code détecté', $zp !== null, true);
check('  → valeur affichée lue',          $zp['value'] ?? '', 'Île-de-France & Occitanie');

echo "\n3. Les réglages sensibles sont écartés\n";
check('le secret OVH n\'apparaît pas', in_array('set:ovh_app_secret',$r,true), false);
check('bulk_is_sensitive le confirme',  bulk_is_sensitive('ovh_app_secret'), true);

echo "\n4. La portée de chaque occurrence est correcte\n";
check('surcharge étiquetée Jura', findHit($hits,'set:z:jura:reals_page_title')['scope'] ?? '', '📍 Jura');
check('page étiquetée Contenu',   findHit($hits,'row:pages:7:title')['scope'] ?? '', '🗂 Contenu');

echo "\n5. Aucun doublon d'occurrence\n";
check('références uniques', count($r), count(array_unique($r)));

echo "\n6. Le remplacement s'applique aux seules occurrences cochées\n";
$before = $DB['reviews'][5]['content'];
$res = bulk_apply(['set:company_regions','row:pages:7:title','set:z:jura:reals_page_title'], 'Occitanie', 'Île-de-France');
check('3 textes modifiés', $res['done'], 3);
check('identité corrigée',  $DB['settings']['company_regions'], 'Île-de-France et Île-de-France');
check('page corrigée',      $DB['pages'][7]['title'], 'Électricien Île-de-France');
check('zone corrigée',      $DB['settings']['z:jura:reals_page_title'], 'Chantiers Île-de-France côté Jura');
check('avis non coché : intact', $DB['reviews'][5]['content'], $before);

echo "\n7. Un texte du code jamais enregistré devient une valeur enregistrée\n";
settings_cache(true);
check('avant : rien en base', isset($DB['settings']['zones_page_settings']), false);
bulk_apply(['cat:page_zones:zp_title_hl'], 'Occitanie', 'Bourgogne');
settings_cache(true);
$saved = json_decode($DB['settings']['zones_page_settings'] ?? '{}', true);
check('après : enregistré',   $saved['title_hl'] ?? '', 'Île-de-France & Bourgogne');

echo "\n8. Le remplacement ignore la casse\n";
$DB['settings']['reals_page_lead'] = 'Partout en occitanie et en OCCITANIE';
settings_cache(true);
bulk_apply(['set:reals_page_lead'], 'occitanie', 'Jura');
check('les deux graphies remplacées', $DB['settings']['reals_page_lead'], 'Partout en Jura et en Jura');

echo "\n9. Le bloc JSON reste un JSON valide\n";
settings_cache(true);
bulk_apply(['set:why_us_settings'], 'Occitanie', 'Île-de-France');
$j = json_decode($DB['settings']['why_us_settings'], true);
check('toujours décodable',        is_array($j), true);
check('titre remplacé',            $j['title'] ?? '', 'Experts en Île-de-France');
check('texte imbriqué remplacé',   $j['items'][0]['text'] ?? '', 'Toute l\'Île-de-France');

echo "\n10. Annulation du dernier remplacement\n";
$avant = $DB['settings']['why_us_settings'];
check('un retour est proposé', bulk_last_undo() !== null, true);
$n = bulk_undo();
check('1 texte restauré', $n, 1);
check('valeur d\'origine retrouvée', $DB['settings']['why_us_settings'], '{"title":"Experts en Occitanie","items":[{"title":"Présence locale","text":"Toute l\'Occitanie"}]}');
check('plus rien à annuler', bulk_last_undo(), null);

echo "\n11. Refus des cas invalides\n";
settings_cache(true);
check('référence inconnue ignorée',  bulk_apply(['set:inexistant'],'Occitanie','X')['done'], 0);
check('colonne hors liste blanche',  bulk_write('row:pages:7:password','pirate'), false);
check('table hors liste blanche',    bulk_write('row:admins:1:email','pirate'), false);
check('écriture sur secret refusée', bulk_write('set:ovh_app_secret','pirate'), false);
check('le secret est intact',        $DB['settings']['ovh_app_secret'], 'occitanie-secret-ne-pas-toucher');
check('recherche trop courte',       bulk_scan('O'), []);

echo "\n12. Chaque résultat propose un lien direct vers le champ\n";
settings_cache(true); admin_inline_keys(true);
$h = bulk_scan('Occitanie');
$byRef = []; foreach ($h as $x) $byRef[$x['ref']] = $x;
check('chaque résultat porte un lien (ou explicitement aucun)',
      count(array_filter($h, fn($x) => array_key_exists('edit', $x))), count($h));

$u = bulk_edit_url('cat:accueil:services_title');
check('champ du catalogue → bon écran',   str_contains((string)$u, 'p=accueil'), true);
check('  → champ ciblé',                  str_contains((string)$u, 'field=services_title'), true);
check('  → ancre de section',             str_contains((string)$u, '#s-services'), true);

$uz = bulk_edit_url('set:z:jura:reals_page_title');
check('surcharge de zone → bon écran',    str_contains((string)$uz, 'p=page_realisations'), true);
check('  → bascule sur la zone Jura',     str_contains((string)$uz, 'admin_zone=1'), true);

check('page → son écran d\'édition',      str_contains((string)bulk_edit_url('row:pages:7:title'), 'page_edit.php?id=7'), true);
check('réalisation → son écran',          str_contains((string)bulk_edit_url('row:realisations:3:description'), 'realisations.php'), true);
check('avis → son écran',                 str_contains((string)bulk_edit_url('row:reviews:5:content'), 'reviews.php'), true);
check('réglage hors catalogue → pas de lien trompeur', bulk_edit_url('set:un_reglage_inconnu'), null);
check('table inconnue → pas de lien',     bulk_edit_url('row:admins:1:email'), null);

echo "\n13. Le remplacement respecte la portée où l'on travaille\n";
settings_cache(true); admin_inline_keys(true);
$DB['settings']['z:jura:home_title']  = 'Titre Occitanie du Jura';
$DB['settings']['company_regions']    = 'Occitanie';
settings_cache(true);

set_zone_context(null);
$h = bulk_scan('Occitanie');
$g = array_values(array_filter($h, fn($x) => $x['dans_portee']));
$z = array_values(array_filter($h, fn($x) => !$x['dans_portee']));
check('en global : les lignes globales sont cochées', count($g) > 0, true);
check('  → la ligne du Jura ne l\'est pas',
      in_array('set:z:jura:home_title', array_column($z,'ref'), true), true);
check('  → aucune ligne de zone cochée',
      count(array_filter($g, fn($x) => str_starts_with($x['ref'],'set:z:'))), 0);

set_zone_context(get_zone_by_slug('jura'));
$h = bulk_scan('Occitanie');
$coches = array_column(array_filter($h, fn($x) => $x['dans_portee']), 'ref');
// En zone, la recherche porte sur ce que la zone affiche : les champs du
// catalogue sont référencés en « cat: », qui écrit dans la portée courante.
check('dans le Jura : tout est actionnable', count(array_filter($h, fn($x) => !$x['dans_portee'])), 0);
check('  → aucune ligne d\'une autre zone',
      count(array_filter($h, fn($x) => str_starts_with($x['ref'],'set:z:') && !str_contains($x['ref'],':jura:'))), 0);
check('  → le texte propre du Jura est vu',
      count(array_filter($h, fn($x) => str_contains(mb_strtolower($x['value']), 'jura'))) > 0, true);
set_zone_context(null);

check('portée d\'une clé de zone',   bulk_ref_scope('set:z:jura:home_title'), 'jura');
check('portée d\'une clé globale',   bulk_ref_scope('set:home_title'), '');
check('portée d\'un contenu',        bulk_ref_scope('row:pages:7:title'), '');

echo "\n14. Conversion d'un nom de lieu en variable\n";
$DB['settings'] = []; settings_cache(true); admin_inline_keys(true);
set_zone_context(null);
zone_var_save('region', 'Occitanie');
set_setting('reals_page_title', 'Nos chantiers en Occitanie');
set_setting('reals_page_lead',  "L'Occitanie et ses environs");
settings_cache(true);

$avant = $DB['settings']['reals_page_title'];
check('le texte contient le nom en dur', str_contains($avant, 'Occitanie'), true);

// Ce que fait l'écran de conversion : remplacer la valeur par sa variable.
$h = bulk_scan('Occitanie');
$refs = array_column(array_filter($h, fn($x) => str_contains($x['ref'], 'reals_page_title')), 'ref');
check('le texte est repéré', count($refs), 1);
$res = bulk_apply($refs, 'Occitanie', '{region}');
settings_cache(true);
check('converti',            $res['done'], 1);
check('la variable est en base', $DB['settings']['reals_page_title'], 'Nos chantiers en {region}');
check('le texte non coché est intact', $DB['settings']['reals_page_lead'], "L'Occitanie et ses environs");

echo "\n15. Le texte converti s'adapte à chaque zone\n";
set_zone_context(null);
check('en global',        setting('reals_page_title','x'), 'Nos chantiers en Occitanie');
set_zone_context(get_zone_by_slug('jura'));
check('la zone hérite',   setting('reals_page_title','x'), 'Nos chantiers en Occitanie');
zone_var_save('region', 'Jura');
settings_cache(true);
check('la zone a sa valeur', setting('reals_page_title','x'), 'Nos chantiers en Jura');
set_zone_context(null);
check('le global inchangé',  setting('reals_page_title','x'), 'Nos chantiers en Occitanie');
check('un seul texte en base, réutilisé par toutes les zones',
      $DB['settings']['reals_page_title'], 'Nos chantiers en {region}');

echo "\n16. Annulation possible après conversion\n";
check('un retour est proposé', bulk_last_undo() !== null, true);
bulk_undo();
settings_cache(true);
check('texte d\'origine retrouvé', $DB['settings']['reals_page_title'], 'Nos chantiers en Occitanie');

echo "\n".($fail===0 ? "TOUS LES TESTS PASSENT ($pass)" : "$fail ÉCHEC(S) sur ".($pass+$fail))."\n";
exit($fail===0?0:1);
