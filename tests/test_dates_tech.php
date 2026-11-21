<?php
declare(strict_types=1);
/**
 * Dates lisibles de l'application technicien (includes/helpers.php) : séparations
 * « Aujourd'hui / Hier / Avant-hier / date » et « Créée hier à 18 h 12 ».
 * Dates fixes : aucune dépendance au jour réel, aucune base, aucun réseau.
 */
define('EMAE_TESTS_SANS_RESEAU', true);

function db_fetch_all(string $s, array $p = []): array { return []; }
function db_fetch(string $s, array $p = []): ?array { return null; }
function db_execute(string $s, array $p = []): bool { return true; }
function db_last_id(): int { return 1; }

require dirname(__DIR__).'/includes/helpers.php';

$pass = 0; $fail = 0;
function check(string $l, mixed $g, mixed $w): void { global $pass, $fail;
    if ($g === $w) { $pass++; echo "  ok   $l\n"; }
    else { $fail++; echo "  FAIL $l — obtenu ".var_export($g, true)." / attendu ".var_export($w, true)."\n"; } }
$sp = static fn(string $s) => str_replace("\u{a0}", ' ', $s);

$today = '2026-11-17';   // un mardi
echo "\n1. Séparations par journée\n";
check('aujourd\'hui, avec la date', day_section_label('2026-11-17', $today), 'Aujourd\'hui — mardi 17 novembre');
check('hier', day_section_label('2026-11-16', $today), 'Hier — lundi 16 novembre');
check('avant-hier', day_section_label('2026-11-15', $today), 'Avant-hier — dimanche 15 novembre');
check('plus ancien : la date seule', day_section_label('2026-11-12', $today), 'Jeudi 12 novembre');
check('sans date (déjà commencée) : avec aujourd\'hui', day_section_label(null, $today), 'Aujourd\'hui — mardi 17 novembre');
check('date future mais commencée : avec aujourd\'hui', day_section_label('2026-11-20', $today), 'Aujourd\'hui — mardi 17 novembre');
check('changement de mois', day_section_label('2026-10-31', '2026-11-01'), 'Hier — samedi 31 octobre');
check('ordre : aujourd\'hui, hier, avant-hier, plus ancien', array_map(static fn($d) => day_section_key($d, $today), ['2026-11-17', '2026-11-16', '2026-11-15', '2026-11-12', null]), [0, 1, 2, 5, 0]);

echo "\n2. Date de création\n";
$now = strtotime('2026-11-17 10:00:00');
check('aujourd\'hui', $sp(created_label('2026-11-17 09:05:00', $now)), 'Créée aujourd\'hui à 9 h 05');
check('hier', $sp(created_label('2026-11-16 18:12:00', $now)), 'Créée hier à 18 h 12');
check('avant-hier', $sp(created_label('2026-11-15 07:40:00', $now)), 'Créée avant-hier à 7 h 40');
check('plus ancien', $sp(created_label('2026-11-02 08:30:00', $now)), 'Créée le 02/11 à 8 h 30');
check('autre année : avec l\'année', $sp(created_label('2025-12-30 14:00:00', $now)), 'Créée le 30/12/2025 à 14 h 00');
check('hier à 23 h 59 reste « hier »', $sp(created_label('2026-11-16 23:59:00', strtotime('2026-11-17 00:01:00'))), 'Créée hier à 23 h 59');
check('pas de date : rien', created_label(null, $now), '');
check('l\'heure ne se coupe pas en fin de ligne', str_contains(created_label('2026-11-17 09:05:00', $now), 'à 9 h'), false);

echo "\n".($fail === 0 ? "TOUTES LES $pass ASSERTIONS PASSENT" : "$fail ÉCHEC(S) sur ".($pass + $fail))."\n";
exit($fail === 0 ? 0 : 1);
