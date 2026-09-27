<?php
declare(strict_types=1);

$DB = [
    'settings' => [1 => ['id'=>1,'setting_key'=>'site_base_url','setting_value'=>'']],
    'zones' => [
        1 => ['id'=>1,'slug'=>'paris-ile-de-france','name'=>'Paris / Île-de-France','status'=>1,'sort_order'=>0],
        2 => ['id'=>2,'slug'=>'jura','name'=>'Jura','status'=>1,'sort_order'=>1],
        3 => ['id'=>3,'slug'=>'doubs','name'=>'Doubs','status'=>0,'sort_order'=>2],
    ],
];
function db_fetch_all(string $sql, array $p = []): array {
    global $DB;
    if (str_contains($sql,'FROM settings')) return array_values($DB['settings']);
    if (str_contains($sql,'FROM zones'))    return array_values($DB['zones']);
    return [];
}
function db_fetch(string $sql, array $p = []): ?array {
    global $DB;
    if (str_contains($sql,'FROM settings')) { foreach ($DB['settings'] as $r) if ($r['setting_key']===$p[0]) return $r; return null; }
    if (str_contains($sql,'FROM zones'))    { foreach ($DB['zones'] as $r) if ((string)$r['id']===(string)$p[0] || $r['slug']===$p[0]) return $r; return null; }
    return null;
}
function db_execute(string $sql, array $p = []): void {}

$_SERVER['SCRIPT_NAME'] = '/index.php';
require dirname(__DIR__).'/includes/helpers.php';

/** Reproduit exactement le bloc de détection en tête d'index.php. */
function detect(string $requestUri): array {
    $route = '';
    $currentZone = null;
    $_zuri = ltrim(rtrim((string)parse_url($requestUri, PHP_URL_PATH), '/'), '/');
    $_zbase = ltrim(rtrim(base_path(), '/'), '/');
    if ($_zbase !== '' && str_starts_with($_zuri, $_zbase)) $_zuri = ltrim(substr($_zuri, strlen($_zbase)), '/');
    $_zsegs = array_values(array_filter(explode('/', $_zuri)));
    $_zs0 = $_zsegs[0] ?? '';
    if ($_zs0 !== '' && !in_array($_zs0, ['admin','tech','dispatcher','api','assets','includes','config','storage','index.php'], true)
        && preg_match('/^[a-z][a-z0-9-]{1,78}$/', $_zs0)) {
        $_zobj = get_zone_by_slug($_zs0);
        if ($_zobj && (bool)$_zobj['status']) {
            $currentZone = $_zobj;
            if ($route === '' && isset($_zsegs[1]) && trim($_zsegs[1]) !== '') $route = trim($_zsegs[1]);
        }
    }
    set_zone_context($currentZone);
    return [$currentZone ? $currentZone['slug'] : null, $route];
}

$pass=0; $fail=0;
function check(string $l, mixed $g, mixed $w): void {
    global $pass,$fail;
    if ($g === $w) { $pass++; echo "  ok   $l\n"; }
    else { $fail++; echo "  FAIL $l — obtenu ".var_export($g,true)." / attendu ".var_export($w,true)."\n"; }
}

echo "\n1. Génération des liens du menu\n";
set_zone_context(null);
check('global : accueil',  route_url(''),      '/index.php');
check('global : nos zones', route_url('zones'), '/index.php?route=zones');
set_zone_context(get_zone_by_slug('paris-ile-de-france'));
check('IDF : accueil',   route_url(''),      '/paris-ile-de-france/');
check('IDF : nos zones', route_url('zones'), '/paris-ile-de-france/zones');
check('IDF : devis',     route_url('quote'), '/paris-ile-de-france/quote');
check('IDF : mentions',  route_url('mentions-legales'), '/paris-ile-de-france/mentions-legales');

echo "\n2. Le visiteur clique : l'URL générée est-elle bien interprétée ?\n";
check('/paris-ile-de-france/',       detect('/paris-ile-de-france/'),       ['paris-ile-de-france','']);
check('/paris-ile-de-france/zones',  detect('/paris-ile-de-france/zones'),  ['paris-ile-de-france','zones']);
check('/paris-ile-de-france/quote',  detect('/paris-ile-de-france/quote'),  ['paris-ile-de-france','quote']);
check('/jura/contact',               detect('/jura/contact'),               ['jura','contact']);
check('/paris-ile-de-france/mentions-legales', detect('/paris-ile-de-france/mentions-legales'), ['paris-ile-de-france','mentions-legales']);

echo "\n3. Aller-retour complet sur toutes les routes du menu\n";
foreach (['zones','quote','contact','faq','services','electricite','plomberie','chauffage','climatisation','realisations','mentions-legales'] as $r) {
    set_zone_context(get_zone_by_slug('jura'));
    $url = route_url($r);
    check("jura/$r", detect($url), ['jura', $r]);
}

echo "\n4. Cas limites\n";
check('zone désactivée ignorée',   detect('/doubs/zones'),  [null,'']);
check('zone inconnue ignorée',     detect('/marseille/'),   [null,'']);
check('chemin admin non capté',    detect('/admin/index.php'), [null,'']);
check('racine du site',            detect('/'),             [null,'']);
check('index.php direct',          detect('/index.php'),    [null,'']);

echo "\n5. Redirections après envoi du formulaire\n";
set_zone_context(get_zone_by_slug('jura'));
$target = route_url('quote');
check('la redirection reste dans la zone', $target, '/jura/quote');
check('et se réinterprète correctement',   detect($target), ['jura','quote']);

echo "\n".($fail===0 ? "TOUS LES TESTS PASSENT ($pass)" : "$fail ÉCHEC(S) sur ".($pass+$fail))."\n";
exit($fail===0?0:1);
