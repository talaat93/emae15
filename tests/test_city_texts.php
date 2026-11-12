<?php
declare(strict_types=1);
// Textes des pages ville et métier × ville (novembre 2026) : règles de contenu, temps de trajet,
// « base de Dole » réservée aux villes proches, titres et H1 uniques.

$DB = ['settings' => []];
function db_fetch_all(string $s, array $p = []): array { global $DB;
    if (str_contains($s, 'FROM settings')) return array_map(fn($k, $v) => ['setting_key' => $k, 'setting_value' => $v], array_keys($DB['settings']), $DB['settings']);
    return []; }   // table city_texts vide : les textes d'origine s'appliquent
function db_fetch(string $s, array $p = []): ?array { return null; }
function db_execute(string $s, array $p = []): void {}
function db_last_id(): int { return 1; }
define('EMAE_TESTS_SANS_RESEAU', true);

$_SERVER['SCRIPT_NAME'] = '/index.php';
$R = dirname(__DIR__);
require $R.'/includes/helpers.php';
require $R.'/includes/zone_vars.php';
require $R.'/includes/zones_core.php';
require $R.'/includes/geo_secteurs.php';
require $R.'/includes/render.php';
require $R.'/includes/city_pages.php';
require $R.'/includes/city_trade_pages.php';
require $R.'/includes/city_trade_content.php';
require $R.'/includes/city_texts.php';

$pass = 0; $fail = 0;
function check(string $l, mixed $g, mixed $w): void { global $pass, $fail;
    if ($g === $w) { $pass++; echo "  ok   $l\n"; }
    else { $fail++; echo "  FAIL $l\n        obtenu  ".var_export($g, true)."\n        attendu ".var_export($w, true)."\n"; } }

echo "\n1. Chaque texte respecte les règles\n";
$titles = $h1s = $intros = []; $bad = [];
foreach (city_texts_seed() as $ville => $pages) {
    if (city_page($ville) === null) $bad[] = "$ville : ville inconnue";
    foreach ($pages as $m => $t) {
        $id = $ville.'/'.$m;
        if ($m !== 'ville' && !isset(city_trades()[$m])) $bad[] = "$id : métier inconnu";
        $all = implode(' ', array_merge([(string)($t['meta'] ?? ''), (string)$t['intro']], array_merge(...array_map(fn($x) => (array)$x, array_merge((array)($t['local'] ?? []), (array)($t['faq'] ?? []))))));
        if (claim_unsupported($all)) $bad[] = "$id : promesse non documentée";
        if (mb_strlen((string)($t['meta'] ?? ''), 'UTF-8') > 155) $bad[] = "$id : meta > 155";
        if (mb_strlen((string)$t['intro'], 'UTF-8') < 300) $bad[] = "$id : texte trop court";
        // Notre base de Dole : seulement pour les villes proches (l'aéroport Dole-Jura reste un nom de lieu).
        if (!city_near_base($ville) && preg_match('/\bDole\b(?!-Jura)/u', $all)) $bad[] = "$id : cite Dole";
        if ($m === 'plombier' && preg_match('/d[ée]cennale/iu', $all)) $bad[] = "$id : décennale en plomberie";
        if (preg_match('/\d+\s*(?:€|euros?)/u', $all)) $bad[] = "$id : prix écrit en dur";
        if (($t['title'] ?? '') !== '') $titles[] = $t['title'];
        if (($t['h1'] ?? '') !== '') $h1s[] = $t['h1'];
        $intros[] = $t['intro'];
    }
}
check('aucune infraction', $bad, []);
check('titres uniques', count($titles), count(array_unique($titles)));
check('H1 uniques', count($h1s), count(array_unique($h1s)));
check('textes uniques', count($intros), count(array_unique($intros)));

echo "\n2. Les 3 exemples : une ville proche de Dole, une du Doubs, une de Côte-d'Or\n";
foreach (['tavaux' => '39', 'besancon' => '25', 'beaune' => '21'] as $v => $dep) {
    $t = city_text($v);
    check("$v : page de la ville rédigée", $t !== null && $t['title'] !== '' && $t['h1'] !== '' && $t['meta'] !== '', true);
    check("$v : département", city_page($v)['dep_code'], $dep);
}
check('Tavaux chauffagiste rédigée (indexable)', city_trade_indexable(city_page('tavaux'), 'chauffagiste'), true);
$_manque = [];
foreach (array_keys(city_pages()) as $_v) { if (city_text($_v) === null) $_manque[] = $_v; foreach (array_keys(city_trades()) as $_m) if (city_text($_v, $_m) === null) $_manque[] = "$_v/$_m"; }
check('les 60 pages sont rédigées', $_manque, []);

echo "\n3. Temps de trajet estimé depuis Dole\n";
check('Tavaux : moins de 30 min', city_travel_label('tavaux'), 'moins de 30 min');
check('Besançon : 30 min à 1 h', city_travel_label('besancon'), '30 min à 1 h');
check('Pontarlier : plus d\'1 h', city_travel_label('pontarlier'), 'plus d\'1 h');
check('toutes les villes ont une estimation', array_values(array_filter(array_keys(city_pages()), fn($s) => city_travel_minutes($s) === null)), []);
$tv = render_city_travel(city_page('tavaux'));
check('ville proche : « depuis notre base de Dole »', str_contains($tv, 'jusqu&#039;à Tavaux depuis notre base de Dole : moins de 30 min'), true);
$bs = render_city_travel(city_page('besancon'));
check('ville éloignée : sans Dole', [str_contains($bs, 'jusqu&#039;à Besançon : 30 min à 1 h'), str_contains($bs, 'Dole')], [true, false]);
check('présenté comme une estimation', str_contains($bs, 'Estimation selon la distance') && str_contains($bs, 'confirmons au téléphone'), true);

echo "\n4. Base de Dole : seulement les villes à une vingtaine de km\n";
check('villes proches', array_values(array_filter(array_keys(city_pages()), 'city_near_base')), ['auxonne', 'dole', 'tavaux', 'saint-vit']);

echo "\n";
echo $fail === 0 ? "$pass assertions PASSENT\n" : "$fail ÉCHECS sur ".($pass + $fail)."\n";
exit($fail === 0 ? 0 : 1);
