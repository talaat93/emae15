<?php
declare(strict_types=1);
/**
 * Fin estimée par le technicien sur place (includes/assignment.php) : texte affiché au
 * dispatcher, retard, et effet sur les créneaux libres proposés à l'assignation.
 * Heures fixes : aucune dépendance à l'heure réelle, aucune base, aucun réseau.
 */
define('EMAE_TESTS_SANS_RESEAU', true);

function db_fetch_all(string $s, array $p = []): array { return []; }
function db_fetch(string $s, array $p = []): ?array { return null; }
function db_execute(string $s, array $p = []): bool { return true; }
function db_last_id(): int { return 1; }

$ROOT = dirname(__DIR__);
require $ROOT.'/includes/helpers.php';
require $ROOT.'/includes/notifications.php';
require $ROOT.'/includes/assignment.php';

$pass = 0; $fail = 0;
function check(string $l, mixed $g, mixed $w): void { global $pass, $fail;
    if ($g === $w) { $pass++; echo "  ok   $l\n"; }
    else { $fail++; echo "  FAIL $l — obtenu ".var_export($g, true)." / attendu ".var_export($w, true)."\n"; } }

$now = strtotime('2026-10-30 14:00:00');
$job = ['status' => 'sur_place', 'scheduled_time' => '13:00:00', 'duration_estimate' => 180,
        'tech_eta_end' => '2026-10-30 15:40:00', 'tech_eta_at' => '2026-10-30 14:00:00', 'tech_progress' => 'milieu'];

echo "\n1. Choix proposés au technicien\n";
check('avancement : début, milieu, fin', array_values(eta_progress_options()), ['Début', 'Milieu', 'Fin']);
check('temps restants', eta_remaining_options(), [15, 30, 45, 60, 90, 120, 180]);
check('libellés', array_map('eta_remaining_label', eta_remaining_options()), ['15 min', '30 min', '45 min', '1 h', '1 h 30', '2 h', '3 h et +']);
check('heure au format français', eta_hm($now), "14\u{a0}h\u{a0}00");
check('l\'heure ne se coupe jamais en fin de ligne', str_contains(eta_hm($now), ' '), false);

echo "\n2. Ce que voit le dispatcher\n";
// Les heures utilisent des espaces insécables : on les remet en espaces simples pour comparer.
$t = static fn(?array $st) => $st === null ? null : str_replace("\u{a0}", ' ', $st['text']);
$s = eta_status($job, $now);
check('texte', $t($s), 'milieu — libre vers 15 h 40');
check('pas en retard', $s['late'] ?? null, false);
$s = eta_status($job, strtotime('2026-10-30 16:05:00'));
check('heure dépassée : signalée', $s['late'] ?? null, true);
check('texte du retard', $t($s), 'milieu — fin prévue 15 h 40, dépassée de 25 min');
check('retard de plus d\'une heure', $t(eta_status($job, strtotime('2026-10-30 16:45:00'))), 'milieu — fin prévue 15 h 40, dépassée de 1 h 05');
check('à la minute près : pas encore en retard', eta_status($job, strtotime('2026-10-30 15:40:30'))['late'] ?? null, false);
check('sans avancement : heure seule', $t(eta_status(['tech_progress' => null] + $job, $now)), 'libre vers 15 h 40');
check('pas sur place (rapport rendu) : rien', eta_status(['status' => 'rapport_rendu'] + $job, $now), null);
check('sur place sans estimation : rien', eta_status(['tech_eta_end' => null] + $job, $now), null);

echo "\n3. Effet sur les créneaux d'assignation\n";
check('fin occupée = fin estimée (15 h 40)', eta_busy_end($job, $now), 15 * 60 + 40);
check('estimation dépassée : encore 15 min à partir de maintenant', eta_busy_end($job, strtotime('2026-10-30 16:05:00')), 16 * 60 + 20);
check('estimation d\'un autre jour : ignorée', eta_busy_end($job, strtotime('2026-10-31 09:00:00')), null);
check('pas d\'estimation : durée prévue conservée', eta_busy_end(['tech_eta_end' => null] + $job, $now), null);
$prevu  = [[13 * 60, 13 * 60 + 180]];                               // 13 h → 16 h d'après la durée prévue
$estime = [[13 * 60, eta_busy_end($job, $now)]];                     // 13 h → 15 h 40 d'après le technicien
check('sans estimation : prochain créneau d\'1 h à 16 h 30', min_to_hm((int)assign_free_slot($prevu, 8 * 60, 18 * 60, 60, 14 * 60 + 30)), '16:30');
check('avec estimation : libéré dès 16 h 15', min_to_hm((int)assign_free_slot($estime, 8 * 60, 18 * 60, 60, 14 * 60 + 30)), '16:15');
$tot = [[13 * 60, eta_busy_end(['tech_eta_end' => '2026-10-30 14:15:00'] + $job, $now)]];
check('fin proche (14 h 15) : créneau à 14 h 45 (30 min de route)', min_to_hm((int)assign_free_slot($tot, 8 * 60, 18 * 60, 60, 14 * 60 + 30)), '14:45');

echo "\n".($fail === 0 ? "TOUTES LES $pass ASSERTIONS PASSENT" : "$fail ÉCHEC(S) sur ".($pass + $fail))."\n";
exit($fail === 0 ? 0 : 1);
