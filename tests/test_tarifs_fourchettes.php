<?php
declare(strict_types=1);
// Fourchettes de prix (21/11/2026) : climatisation et CVC, VMC et ventilation, portail et visiophone,
// automatismes ; migration v15.48 qui ne remplit que les grilles sans aucun prix saisi.
$DB = ['settings' => []];
$SAUV = [];
function db_fetch_all(string $s, array $p = []): array { global $DB;
    if (str_contains($s, 'FROM settings')) return array_map(fn($k, $v) => ['setting_key' => $k, 'setting_value' => $v], array_keys($DB['settings']), $DB['settings']);
    return []; }
function db_fetch(string $s, array $p = []): ?array { return null; }
function db_execute(string $s, array $p = []): void { global $DB;
    if (str_starts_with($s, 'INSERT INTO settings')) $DB['settings'][$p[0]] = $p[1]; }
function db_last_id(): int { return 1; }
function geo_sauvegarde_table(): void {}
function geo_sauvegarder(string $lot, string $cible, ?string $a): void { global $SAUV; $SAUV[$cible] = $a; }
function geo_ecrire_brut(string $k, string $v): void { global $DB; $DB['settings'][$k] = $v; settings_cache(true); }
define('EMAE_TESTS_SANS_RESEAU', true);
$_SERVER['SCRIPT_NAME'] = '/index.php';
$R = dirname(__DIR__);
require $R.'/includes/helpers.php';
require $R.'/includes/workflow.php';
require $R.'/includes/growth.php';
boot_session();
require $R.'/includes/content_pages.php';

$pass = 0; $fail = 0;
function check(string $l, mixed $g, mixed $w): void { global $pass, $fail;
    if ($g === $w) { $pass++; echo "  ok   $l\n"; }
    else { $fail++; echo "  FAIL $l\n        obtenu  ".var_export($g, true)."\n        attendu ".var_export($w, true)."\n"; } }
function set(string $k, string $v): void { global $DB; $DB['settings'][$k] = $v; settings_cache(true); }

echo "\n1. Grilles par défaut : chaque ligne a une fourchette TTC\n";
$def = tarifs_default_rows();
foreach (['climatisation', 'vmc', 'portail', 'automatisme'] as $t) {
    $vides = array_filter($def[$t], fn($r) => !preg_match('/€ TTC$/u', $r[1]));
    check("$t : aucun prix vide", count($vides), 0);
    check("$t : précision sur chaque ligne", count(array_filter($def[$t], fn($r) => $r[2] === '')), 0);
}
check('visiophone', array_values(array_filter($def['portail'], fn($r) => $r[0] === 'Installation de visiophone'))[0][1], '250 € à 600 € TTC');
check('chauffage garde sa fourchette', $def['chauffage'][1][1], '109 € à 239 € TTC');
check('plomberie inchangée', $def['plomberie'][0], ['Recherche et réparation de fuite', '', '']);

echo "\n2. Page Tarifs et estimation\n";
set('tarifs_enabled', '1');
$g = array_values(array_filter(tarifs_groups(), fn($x) => str_contains($x['label'], 'VMC')));
check('groupe VMC chiffré', $g[0]['rows'][0][1] ?? null, '120 € à 250 € TTC');
check('estimation reprend la grille', estimation_trades()['climatisation']['rows'][2]['price'], '120 € à 200 € TTC');
check('tarif_price', tarif_price('portail', 'Motorisation de portail'), '800 € à 2 000 € TTC');

echo "\n3. Migration v15.48 : seulement les grilles sans aucun prix\n";
$vide = json_encode([['Dépannage de VMC', '', ''], ['Installation de VMC', '', '']], JSON_UNESCAPED_UNICODE);
$saisie = json_encode([['Dépannage de portail bloqué', '99 € TTC', ''], ['Motorisation', '', '']], JSON_UNESCAPED_UNICODE);
$elec = json_encode([['Dépannage électrique', '', '']], JSON_UNESCAPED_UNICODE);
$DB['settings'] = ['tarifs_vmc' => $vide, 'z:doubs:tarifs_vmc' => $vide, 'tarifs_portail' => $saisie, 'tarifs_electricite' => $elec, 'tarifs_enabled' => '1'];
settings_cache(true);
$n = tarifs_fill_empty_grids(tarifs_fourchettes_rows());
$neuf = json_encode(tarifs_fourchettes_rows()['vmc'], JSON_UNESCAPED_UNICODE);
check('2 grilles remplacées', $n, 2);
check('grille globale vide remplacée', $DB['settings']['tarifs_vmc'], $neuf);
check('grille de zone vide remplacée', $DB['settings']['z:doubs:tarifs_vmc'], $neuf);
check('grille avec un prix saisi gardée', $DB['settings']['tarifs_portail'], $saisie);
check('électricité non concernée', $DB['settings']['tarifs_electricite'], $elec);
check('réglage tarifs_enabled intact', $DB['settings']['tarifs_enabled'], '1');
check('ancienne valeur sauvegardée', $SAUV['setting:tarifs_vmc'] ?? null, $vide);
check('relancée : plus rien à remplacer', tarifs_fill_empty_grids(tarifs_fourchettes_rows()), 0);
check('migration branchée dans bootstrap', str_contains((string)file_get_contents($R.'/includes/bootstrap.php'), 'tarifs_fill_empty_grids(tarifs_fourchettes_rows())'), true);

echo "\n".($fail === 0 ? "$pass assertions PASSENT" : "$fail ÉCHECS sur ".($pass + $fail))."\n";
exit($fail === 0 ? 0 : 1);
