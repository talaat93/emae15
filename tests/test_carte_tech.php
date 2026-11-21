<?php
declare(strict_types=1);
/**
 * Carte de l'intervention sur la fiche du technicien (includes/zone_map.php) : position GPS
 * → carte, ville repère la plus proche, adresse hors zone. Aucune base, aucun réseau.
 */
define('EMAE_TESTS_SANS_RESEAU', true);

function db_fetch_all(string $s, array $p = []): array { return []; }
function db_fetch(string $s, array $p = []): ?array { return null; }
function db_execute(string $s, array $p = []): bool { return true; }
function db_last_id(): int { return 1; }

$ROOT = dirname(__DIR__);
require $ROOT.'/includes/helpers.php';
foreach (['geo_secteurs', 'assignment', 'city_pages', 'zone_map'] as $f) if (is_file("$ROOT/includes/$f.php")) require_once "$ROOT/includes/$f.php";

$pass = 0; $fail = 0;
function check(string $l, mixed $g, mixed $w): void { global $pass, $fail;
    if ($g === $w) { $pass++; echo "  ok   $l\n"; }
    else { $fail++; echo "  FAIL $l — obtenu ".var_export($g, true)." / attendu ".var_export($w, true)."\n"; } }

$cities = zone_map_data()['cities'];
echo "\n1. Position GPS → carte\n";
foreach (['besancon' => [47.2378, 6.0241], 'dijon' => [47.3220, 5.0415], 'lons-le-saunier' => [46.6744, 5.5547], 'montbeliard' => [47.5100, 6.7980]] as $slug => [$la, $lo]) {
    [$x, $y] = zone_map_project($la, $lo);
    check("$slug tombe sur sa ville (moins de 6 unités d'écart)", hypot($x - $cities[$slug][0], $y - $cities[$slug][1]) < 6, true);
}
[$la, $lo] = zone_map_unproject(...zone_map_project(47.0, 5.5));
check('aller-retour GPS → carte → GPS', [round($la, 2), round($lo, 2)], [47.0, 5.5]);

echo "\n2. Ville repère la plus proche\n";
$n = zone_map_nearest_city(47.0920, 5.4900);   // Dole
check('Dole', $n['slug'] ?? null, 'dole');
$n = zone_map_nearest_city(47.25, 6.10);       // un peu à l'est de Besançon
check('à l\'est de Besançon : Besançon, quelques km', [$n['slug'] ?? null, ($n['km'] ?? 99) <= 10], ['besancon', true]);

echo "\n3. Affichage\n";
$h = render_tech_zone_map(47.0920, 5.4900, '1 rue de Dole');
check('point rouge dessiné', str_contains($h, 'tz-pin'), true);
check('les 3 départements', substr_count($h, 'class="tz-dep"'), 3);
check('dans la zone : pas d\'alerte', str_contains($h, 'Hors zone'), false);
$p = render_tech_zone_map(48.8566, 2.3522, 'Paris');
check('Paris : hors zone habituelle', str_contains($p, 'Hors zone habituelle'), true);
check('Paris : le point reste sur la carte (bord)', (bool)preg_match('/cx="14(\.0)?" cy="14(\.0)?" r="7.5"/', $p), true);
check('position inconnue : rien', render_tech_zone_map(null, null, 'x'), '');
check('adresse introuvable (0, 0) : rien', render_tech_zone_map(0.0, 0.0, 'x'), '');
check('adresse échappée', str_contains(render_tech_zone_map(47.09, 5.49, '<b>x</b>'), '<b>x</b>'), false);

echo "\n".($fail === 0 ? "TOUTES LES $pass ASSERTIONS PASSENT" : "$fail ÉCHEC(S) sur ".($pass + $fail))."\n";
exit($fail === 0 ? 0 : 1);
