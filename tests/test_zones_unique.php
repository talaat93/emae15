<?php
declare(strict_types=1);

/**
 * La liste unique des zones : une modification doit se voir partout.
 */

$DB = ['settings' => [], 'zones' => []];

function db_fetch_all(string $s, array $p = []): array {
    global $DB;
    if (str_contains($s, 'FROM zones')) {
        $rows = array_values($DB['zones']);
        if (str_contains($s, 'status = 1')) $rows = array_values(array_filter($rows, fn($z) => (int)$z['status'] === 1));
        usort($rows, fn($a, $b) => [$a['sort_order'], $a['id']] <=> [$b['sort_order'], $b['id']]);
        return $rows;
    }
    if (str_contains($s, 'FROM settings')) {
        $o = []; foreach ($DB['settings'] as $k => $v) $o[] = ['setting_key' => $k, 'setting_value' => $v];
        return $o;
    }
    return [];
}
function db_fetch(string $s, array $p = []): ?array {
    global $DB;
    if (str_contains($s, 'FROM settings')) return isset($DB['settings'][$p[0]]) ? ['id'=>1,'setting_key'=>$p[0],'setting_value'=>$DB['settings'][$p[0]]] : null;
    if (str_contains($s, 'FROM zones')) { foreach ($DB['zones'] as $z) if ((string)$z['id'] === (string)$p[0] || $z['slug'] === $p[0]) return $z; }
    return null;
}
function db_execute(string $s, array $p = []): void {
    global $DB;
    if (str_starts_with($s, 'INSERT INTO settings')) { $DB['settings'][$p[0]] = $p[1]; return; }
    if (str_starts_with($s, 'UPDATE settings'))      { $DB['settings'][$p[1]] = $p[0]; return; }
    if (str_starts_with($s, 'DELETE FROM settings')) { unset($DB['settings'][$p[0]]); return; }
    if (str_starts_with($s, 'UPDATE zones SET name')) {
        [$n,$c,$d,$in,$de,$ci,$pc,$st,$so,$id] = $p;
        $DB['zones'][$id] = array_merge($DB['zones'][$id], ['name'=>$n,'color'=>$c,'delay'=>$d,'intro'=>$in,
            'depts'=>$de,'cities'=>$ci,'postal_codes'=>$pc,'status'=>$st,'sort_order'=>$so]);
        return;
    }
    if (preg_match('/^UPDATE zones SET (.+) WHERE id = \?$/', $s, $m)) {
        $cols = array_map(fn($c) => trim(str_replace(['`','= ?'], '', $c)), explode(',', $m[1]));
        $id = array_pop($p);
        foreach ($cols as $i => $c) $DB['zones'][$id][$c] = $p[$i];
        return;
    }
}

$_SERVER['SCRIPT_NAME'] = '/index.php';
$R = dirname(__DIR__);
require $R.'/includes/helpers.php';
require $R.'/includes/zones_core.php';

$pass = 0; $fail = 0;
function check(string $l, mixed $g, mixed $w): void {
    global $pass, $fail;
    if ($g === $w) { $pass++; echo "  ok   $l\n"; }
    else { $fail++; echo "  FAIL $l\n        obtenu  ".var_export($g,true)."\n        attendu ".var_export($w,true)."\n"; }
}
function seed(array $zones): void {
    global $DB;
    $DB['zones'] = [];
    foreach ($zones as $z) $DB['zones'][$z['id']] = $z + ['status'=>1,'sort_order'=>0,'color'=>null,
        'delay'=>null,'intro'=>null,'depts'=>null,'cities'=>null,'postal_codes'=>null];
    intervention_zones_flush();
}

echo "\n1. Une seule écriture, quatre emplacements servis\n";
seed([
    ['id'=>1,'slug'=>'idf','name'=>'Île-de-France','cities'=>'Paris|Meaux|Versailles','depts'=>'Paris (75)|Essonne (91)','delay'=>'Moins de 2h','intro'=>'Paris et la région.','sort_order'=>0],
    ['id'=>2,'slug'=>'jura','name'=>'Jura','cities'=>'Lons-le-Saunier|Dole','sort_order'=>1],
]);
$z = intervention_zones();
check('les deux zones sont là', count($z), 2);
check('villes de l\'Île-de-France', $z[0]['cities'], ['Paris','Meaux','Versailles']);
check('départements repris',       $z[0]['depts'],  ['Paris (75)','Essonne (91)']);
check('accueil et pages service : même source', array_map(fn($x)=>$x['name'], $z), ['Île-de-France','Jura']);
check('page Nos zones : même source', array_map(fn($x)=>$x['name'], intervention_regions()), ['Île-de-France','Jura']);
check('page Contact : villes des deux zones', intervention_cities(), ['Paris','Meaux','Versailles','Lons-le-Saunier','Dole']);

echo "\n2. Je change une ville : elle change partout\n";
zone_save_presentation(1, ['name'=>'Île-de-France','color'=>'#1a7ab5','delay'=>'Moins de 2h',
    'intro'=>'Paris et la région.','depts'=>'Paris (75)','cities'=>'Paris|Meaux|Melun',
    'postal_codes'=>'75,77','status'=>1,'sort_order'=>0]);
intervention_zones_flush();
check('la liste unique est à jour',   intervention_zones()[0]['cities'], ['Paris','Meaux','Melun']);
check('la page Nos zones suit',       intervention_regions()[0]['cities'], ['Paris','Meaux','Melun']);
check('la page Contact suit',         in_array('Melun', intervention_cities(), true), true);
check('« Versailles » a disparu partout', in_array('Versailles', intervention_cities(), true), false);

echo "\n3. Masquer une zone la retire du site sans rien effacer\n";
zone_save_presentation(2, ['name'=>'Jura','color'=>'#E8921A','delay'=>'','intro'=>'',
    'depts'=>'','cities'=>'Lons-le-Saunier|Dole','postal_codes'=>'39','status'=>0,'sort_order'=>1]);
intervention_zones_flush();
check('le site ne voit plus qu\'une zone', count(intervention_zones()), 1);
check('l\'admin en voit toujours deux',    count(intervention_zones(false)), 2);
check('ses villes sont conservées',        intervention_zones(false)[1]['cities'], ['Lons-le-Saunier','Dole']);
check('elles ne s\'affichent plus',        in_array('Dole', intervention_cities(), true), false);

echo "\n4. La page Nos zones n'a plus sa liste séparée\n";
$DB['settings']['zones_page_settings'] = json_encode([
    'title' => 'Nous intervenons en',
    'regions' => [['name'=>'Occitanie','cities'=>['Toulouse'],'depts'=>[],'delay'=>'','color'=>'#000']],
], JSON_UNESCAPED_UNICODE);
settings_cache(true);
$cfg = zones_page_settings();
check('le titre saisi est conservé', $cfg['title'], 'Nous intervenons en');
check('l\'ancienne région fantôme a disparu', array_map(fn($r)=>$r['name'], $cfg['regions']), ['Île-de-France']);

echo "\n5. Aucune zone active : le site ne se retrouve pas vide\n";
zone_save_presentation(1, ['name'=>'Île-de-France','color'=>'#1a7ab5','delay'=>'','intro'=>'',
    'depts'=>'','cities'=>'Paris','postal_codes'=>'','status'=>0,'sort_order'=>0]);
intervention_zones_flush();
settings_cache(true);
check('repli sur les régions d\'origine', count(zones_page_settings()['regions']) > 0, true);

echo "\n6. Reprise des anciennes saisies, sans écraser la table\n";
seed([
    ['id'=>1,'slug'=>'idf','name'=>'Île-de-France','cities'=>'Paris|Meaux','sort_order'=>0],
    ['id'=>2,'slug'=>'jura','name'=>'Jura','cities'=>'Dole','delay'=>'Déjà saisi','sort_order'=>1],
]);
$n = zones_import_legacy(
    [['name'=>'ile de france','depts'=>['Paris (75)','Yvelines (78)'],'delay'=>'Moins de 2h','cities'=>['Ignoré'],'color'=>'#1a7ab5'],
     ['name'=>'Jura','depts'=>['Jura (39)'],'delay'=>'Sous 4h']],
    [['title'=>'🗺️ Île-de-France','text'=>'Paris et toute la région.']]
);
intervention_zones_flush();
$z = intervention_zones(false);
check('2 zones enrichies', $n, 2);
check('départements importés malgré l\'accent et la casse', $z[0]['depts'], ['Paris (75)','Yvelines (78)']);
check('phrase de présentation importée (emoji retiré du titre)', $z[0]['intro'], 'Paris et toute la région.');
check('les villes déjà en base ne sont pas écrasées', $z[0]['cities'], ['Paris','Meaux']);
check('un délai déjà saisi est respecté', $z[1]['delay'], 'Déjà saisi');
check('le Jura reçoit quand même ses départements', $z[1]['depts'], ['Jura (39)']);

echo "\n7. Adresses de zone\n";
check('accents et espaces', zone_slugify('Côte-d\'Or'), 'cote-d-or');
check('casse et ponctuation', zone_slugify('Paris / Île-de-France'), 'paris-ile-de-france');
check('nom vide', zone_slugify('   '), '');
seed([['id'=>1,'slug'=>'jura','name'=>'Jura','sort_order'=>0]]);
check('adresse libre', zone_unique_slug('doubs'), 'doubs');
check('adresse déjà prise', zone_unique_slug('jura'), 'jura-2');
check('la zone garde la sienne', zone_unique_slug('jura', 1), 'jura');

echo "\n8. Listes saisies ligne par ligne\n";
check('lignes vides ignorées', zone_split_list('Paris||Meaux| '), ['Paris','Meaux']);
check('recomposition',        zone_join_list([' Paris ','','Meaux']), 'Paris | Meaux');

echo "\n".($fail === 0 ? "TOUS LES TESTS PASSENT ($pass)" : "$fail ÉCHEC(S) sur ".($pass+$fail))."\n";
exit($fail === 0 ? 0 : 1);
