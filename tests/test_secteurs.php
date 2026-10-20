<?php
declare(strict_types=1);

/*
 * Secteurs géographiques : repérage des lieux, variables de chaque zone,
 * mise en conformité du site et annulation.
 *
 * La base de départ reproduit les erreurs constatées : textes globaux écrits
 * pour l'Île-de-France et l'Occitanie, textes de zone recopiés d'une autre.
 */

$DB = ['settings' => [
    'company_name'                 => 'EMAE',
    'home_title'                   => 'Le partenaire technique de vos bâtiments en Île-de-France et en Occitanie',
    'reals_page_title'             => 'Nos chantiers en Occitanie',
    'ml_hebergeur'                 => 'o2switch — 63000 Clermont-Ferrand',
    'z:jura:home_title'            => 'Électricien à Paris',
    'z:jura:home_meta_title'       => 'Dépannage Île-de-France | EMAE',
    'z:doubs:home_lead'            => 'Plombier à Besançon et Belfort.',
    'z:doubs:faq_meta_title'       => 'FAQ — dépannage dans le Jura',
    'z:paris-ile-de-france:zvar_region' => 'Paris / Île-de-France',
    'z:paris-ile-de-france:home_title'  => 'Votre électricien à Paris',
], 'zones' => [
    1 => ['id'=>1,'slug'=>'paris-ile-de-france','name'=>'Paris / Île-de-France','status'=>1,'sort_order'=>0,
          'postal_codes'=>'75,77,78,91,92,93,94,95','cities'=>'Paris (75)|Meaux (77)','meta_title'=>'Électricien Paris','hero_h1'=>null],
    2 => ['id'=>2,'slug'=>'cote-dor','name'=>"Côte-d'Or",'status'=>1,'sort_order'=>1,'postal_codes'=>'21','cities'=>'Dijon|Beaune','meta_title'=>null],
    3 => ['id'=>3,'slug'=>'jura','name'=>'Jura','status'=>1,'sort_order'=>2,'postal_codes'=>'39','cities'=>'Lons-le-Saunier|Dole',
          'meta_title'=>'Électricien Paris — Devis gratuit','hero_h1'=>'Votre expert dans le Jura'],
    4 => ['id'=>4,'slug'=>'doubs','name'=>'Doubs','status'=>1,'sort_order'=>3,'postal_codes'=>'25','cities'=>'Besançon|Pontarlier','meta_title'=>null],
    5 => ['id'=>5,'slug'=>'seine-saint-denis','name'=>'Seine-Saint-Denis','status'=>1,'sort_order'=>4,'postal_codes'=>'93','cities'=>'Saint-Denis','meta_title'=>null],
    6 => ['id'=>6,'slug'=>'mystere','name'=>'Mystère','status'=>0,'sort_order'=>5,'postal_codes'=>'','cities'=>'','meta_title'=>null],
], 'geo_sauvegarde' => []];

function db_fetch_all(string $s, array $p = []): array { global $DB;
    if (str_contains($s, 'FROM settings')) { $o = []; foreach ($DB['settings'] as $k => $v) $o[] = ['setting_key'=>$k,'setting_value'=>$v]; return $o; }
    if (str_contains($s, 'FROM zones')) {
        $z = array_values($DB['zones']);
        return str_contains($s, 'status = 1') ? array_values(array_filter($z, fn($r) => (int)$r['status'] === 1)) : $z;
    }
    if (str_contains($s, 'FROM geo_sauvegarde')) {
        return array_reverse(array_values(array_filter($DB['geo_sauvegarde'], fn($r) => $r['lot'] === $p[0])));
    }
    return []; }
function db_fetch(string $s, array $p = []): ?array { global $DB;
    if (str_contains($s, 'FROM settings')) return isset($DB['settings'][$p[0]]) ? ['id'=>1,'setting_value'=>$DB['settings'][$p[0]]] : null;
    if (str_contains($s, 'FROM zones')) { foreach ($DB['zones'] as $z) if ((string)$z['id'] === (string)$p[0] || $z['slug'] === $p[0]) return $z; }
    return null; }
function db_execute(string $s, array $p = []): void { global $DB;
    if (str_starts_with($s, 'INSERT INTO settings'))   { $DB['settings'][$p[0]] = $p[1]; return; }
    if (str_starts_with($s, 'UPDATE settings'))        { $DB['settings'][$p[1]] = $p[0]; return; }
    if (str_starts_with($s, 'DELETE FROM settings'))   { unset($DB['settings'][$p[0]]); return; }
    if (str_starts_with($s, 'INSERT INTO geo_sauvegarde')) {
        $DB['geo_sauvegarde'][] = ['id'=>count($DB['geo_sauvegarde'])+1,'lot'=>$p[0],'cible'=>$p[1],'ancienne'=>$p[2]]; return; }
    if (str_starts_with($s, 'DELETE FROM geo_sauvegarde')) {
        $DB['geo_sauvegarde'] = array_values(array_filter($DB['geo_sauvegarde'], fn($r) => $r['lot'] !== $p[0])); return; }
    if (preg_match('/^UPDATE zones SET `(\w+)` = (NULL|\?) WHERE id = \?/', $s, $m)) {
        $id = (int)end($p);
        $DB['zones'][$id][$m[1]] = $m[2] === 'NULL' ? null : $p[0];
    } }

$_SERVER['SCRIPT_NAME'] = '/admin/zones_diag.php';
$R = dirname(__DIR__);
require $R.'/includes/helpers.php';
require $R.'/includes/zone_vars.php';
require $R.'/includes/zones_core.php';
require $R.'/includes/admin_fields.php';
require $R.'/includes/admin_replace.php';
require $R.'/includes/geo_secteurs.php';

$pass = 0; $fail = 0;
function check(string $l, mixed $g, mixed $w): void { global $pass, $fail;
    if ($g === $w) { $pass++; echo "  ok   $l\n"; }
    else { $fail++; echo "  FAIL $l\n        obtenu  ".var_export($g, true)."\n        attendu ".var_export($w, true)."\n"; } }
$noms = fn(string $t): array => array_column(geo_lieux_cites($t), 'nom');
$zone = fn(string $slug): array => $GLOBALS['DB']['zones'][array_search($slug, array_column($GLOBALS['DB']['zones'], 'slug', 'id'), true)];

echo "\n1. Les lieux cités sont reconnus sans confusion\n";
check('Saône-et-Loire ne compte pas pour Loire',  $noms('Électricien en Saône-et-Loire'), ['Saône-et-Loire']);
check('Haute-Savoie ne compte pas pour Savoie',    $noms('Annecy, en Haute-Savoie'), ['Annecy', 'Haute-Savoie']);
check('Auvergne-Rhône-Alpes reste une région',     $noms('en Auvergne-Rhône-Alpes'), ['Auvergne-Rhône-Alpes']);
check('sans accents ni tirets',                    $noms('Depannage Ile de France'), ['Île-de-France']);
check('apostrophe typographique',                  $noms('Plombier en Côte d’Or'), ["Côte-d'Or"]);
check('« l\'Ain » est reconnu',                    $noms("Plombier dans l'Ain"), ['Ain']);
check('un mot courant n\'est pas un lieu',         $noms('Ainsi, le plus rapide est de nous appeler.'), []);
check('texte sans lieu',                           $noms('Devis gratuit sous 30 minutes'), []);

echo "\n2. Chaque zone reçoit ses propres variables\n";
$p = geo_profil_zone($zone('doubs'));
check('Doubs : préposition',     $p['vars']['en_region'], 'dans le Doubs');
check('Doubs : ville',           $p['vars']['en_ville'], 'à Besançon');
$p = geo_profil_zone($zone('jura'));
check('Jura : ville la plus peuplée', $p['vars']['ville'], 'Dole');
$p = geo_profil_zone($zone('cote-dor'));
check('Côte-d\'Or : « en »',     $p['vars']['en_region'], "en Côte-d'Or");
$p = geo_profil_zone($zone('paris-ile-de-france'));
check('8 départements → la région', $p['vars']['en_region'], 'en Île-de-France');
check('… et Paris en avant',     $p['vars']['en_ville'], 'à Paris');
check('zone déduite de son nom', geo_profil_zone(['name'=>'Haute-Loire','slug'=>'haute-loire'])['vars']['en_ville'] ?? '', 'au Puy-en-Velay');
check('deux départements',       geo_profil_zone(['name'=>'X','slug'=>'x','postal_codes'=>'75, 92'])['vars']['en_region'] ?? '',
                                 'à Paris et dans les Hauts-de-Seine');
check('zone inconnue : rien',    geo_profil_zone($zone('mystere')), null);

echo "\n3. Une erreur n'est certaine que si elle l'est vraiment\n";
$doubs = geo_profil_zone($zone('doubs'));
check('autre région → certaine',        geo_erreur_certaine(geo_hors_secteur('Électricien en Île-de-France', $doubs)), true);
check('seul le voisin cité → certaine', geo_erreur_certaine(geo_hors_secteur('Électricien dans le Jura', $doubs)), true);
$v = geo_hors_secteur('Plombier à Besançon et Belfort.', $doubs);
check('voisin + chez soi → gardé',      geo_erreur_certaine($v), false);
check('… mais signalé',                 $v['etrangers'], ['Belfort']);
check('région de la zone → chez soi',   geo_hors_secteur('en Bourgogne-Franche-Comté', $doubs)['etrangers'], []);

echo "\n4. Mise en conformité\n";
$avant = $DB['settings']; $zonesAvant = $DB['zones'];
$b = geo_appliquer();
settings_cache(true);
check('zone inconnue listée',    $b['non_reconnues'], ['Mystère']);
check('textes de zone retirés',  $b['retires'] >= 5, true);

set_zone_context(null);
check('global : titre en couleur',  setting('home_title_hl'), "dans le Doubs, le Jura et la Côte-d'Or");
check('global : titre Google',      setting('home_meta_title'), 'Électricien, plombier, chauffagiste à Besançon, Dijon et Dole | EMAE');
check('global : grand titre Chauffage', service_template('chauffage')['h1'], "Chauffagiste dans le Doubs, le Jura et la Côte-d'Or");
check('global : zones couvertes',   company_regions(), "Doubs, Jura, Côte-d'Or");
check('global : Occitanie retirée', setting('reals_page_title', 'Interventions & chantiers'), 'Interventions & chantiers');
check('mentions légales intactes',  $DB['settings']['ml_hebergeur'], 'o2switch — 63000 Clermont-Ferrand');
check('page Nos zones',             zones_page_settings()['title_hl'], "dans le Doubs, le Jura et la Côte-d'Or");

set_zone_context($zone('jura'));
check('Jura : titre recopié de Paris retiré', setting('home_title'), 'Votre expert multitechnique');
check('Jura : son secteur',         setting('home_title_hl'), 'dans le Jura');
check('Jura : titre Google',        setting('home_meta_title'), 'Électricien, plombier, chauffagiste à Dole | EMAE');
check('Jura : fiche de zone vidée', $DB['zones'][3]['meta_title'], null);
check('Jura : fiche juste gardée',  $DB['zones'][3]['hero_h1'], 'Votre expert dans le Jura');
check('Jura : page Nos zones',      zones_page_settings()['title_hl'], 'dans le Jura');

set_zone_context($zone('doubs'));
check('Doubs : texte voisin gardé', setting('home_lead'), 'Plombier à Besançon et Belfort.');
check('Doubs : FAQ du Jura retirée', setting('faq_meta_title'), 'Questions fréquentes — Dépannage dans le Doubs');
set_zone_context($zone('jura'));
check('Jura : grand titre Électricité', service_template('electricite')['h1'], 'Électricien dans le Jura');
check('Jura : titre Google Plomberie', service_template('plomberie')['meta_title'], 'Plombier à Dole — Fuite, débouchage, chauffe-eau | EMAE');
check('Jura : accroche Portail', service_template('portail')['desc'], 'Motorisation de portail, visiophone, interphone et contrôle d\'accès dans le Jura.');
set_zone_context($zone('doubs'));
check('Doubs : métier',             service_template('electricite')['desc'], 'Dépannage, installation, mise aux normes et rénovation électrique dans le Doubs.');

set_zone_context($zone('paris-ile-de-france'));
check('Paris : variable corrigée',  setting('home_title_hl'), 'en Île-de-France');
check('Paris : son titre gardé',    setting('home_title'), 'Votre électricien à Paris');
check('Paris : fiche gardée',       $DB['zones'][1]['meta_title'], 'Électricien Paris');
set_zone_context($zone('seine-saint-denis'));
check('93 : accroche',              setting('home_lead'), 'Électricité, plomberie, chauffage, climatisation et pompes à chaleur en Seine-Saint-Denis. Intervention rapide autour de Saint-Denis, devis gratuit, artisans qualifiés.');
set_zone_context(null);

echo "\n5. Relancer ne change plus rien\n";
$b2 = geo_appliquer();
check('rien écrit',   [$b2['ecrits'], $b2['retires'], $b2['variables']], [0, 0, 0]);

echo "\n6. Le diagnostic ne signale que ce qui reste à voir\n";
$c = geo_controle();
$lignes = array_map(fn($r) => $r['portee'].' | '.$r['champ'], array_filter($c, fn($r) => !str_starts_with($r['champ'], 'Mentions')));
check('le texte voisin du Doubs est signalé',
      (bool)array_filter($c, fn($r) => $r['portee'] === '📍 Doubs' && $r['etrangers'] === ['Belfort']), true);
check('aucun texte global signalé',
      array_values(array_filter($lignes, fn($l) => str_starts_with($l, '🌐'))), []);
check('mentions légales jamais signalées',
      array_values(array_filter($c, fn($r) => str_contains($r['extrait'], 'o2switch'))), []);

echo "\n7. Tout s'annule\n";
$n = geo_annuler($b['lot']);
settings_cache(true);
check('valeurs restaurées',   $n > 0, true);
ksort($avant); $apres = $DB['settings']; ksort($apres);
check('réglages comme avant', $apres, $avant);
check('fiches comme avant',   $DB['zones'], $zonesAvant);
check('sauvegarde vidée',     array_values(array_filter($DB['geo_sauvegarde'], fn($r) => $r['lot'] === $b['lot'])), []);

echo "\n".($fail === 0 ? "TOUS LES $pass TESTS PASSENT" : "$fail ÉCHEC(S) sur ".($pass + $fail))."\n";
exit($fail === 0 ? 0 : 1);
