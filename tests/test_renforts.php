<?php
declare(strict_types=1);
/**
 * Plusieurs techniciens sur une intervention (includes/team.php) : mode de rapport, état du
 * rapport d'un renfort, ajout refusé pour le principal ou un technicien inactif, rapport
 * enregistré ou envoyé. Base en mémoire, aucun réseau.
 */
define('EMAE_TESTS_SANS_RESEAU', true);

$DB = ['iv' => ['id' => 9, 'technician_id' => 1, 'status' => 'sur_place'], 'techs' => [1 => ['id' => 1, 'name' => 'Thomas', 'status' => 'actif', 'email' => '', 'phone' => ''], 2 => ['id' => 2, 'name' => 'Karim', 'status' => 'actif', 'email' => '', 'phone' => ''], 3 => ['id' => 3, 'name' => 'Julien', 'status' => 'inactif', 'email' => '', 'phone' => '']], 'team' => [], 'exec' => []];
function db_fetch(string $s, array $p = []): ?array {
    global $DB;
    if (str_contains($s, 'FROM technicians')) return $DB['techs'][(int)$p[0]] ?? null;
    if (str_contains($s, 'FROM intervention_team')) { foreach ($DB['team'] as $r) if ($r['intervention_id'] === (int)$p[0] && $r['technician_id'] === (int)$p[1]) return $r; return null; }
    if (str_contains($s, 'FROM interventions')) return $DB['iv'];
    return null;
}
function db_fetch_all(string $s, array $p = []): array { return []; }
function db_execute(string $s, array $p = []): bool {
    global $DB; $DB['exec'][] = [$s, $p];
    if (str_starts_with(trim($s), 'INSERT INTO intervention_team')) $DB['team'][] = ['id' => count($DB['team']) + 1, 'intervention_id' => (int)$p[0], 'technician_id' => (int)$p[1], 'submitted_at' => null];
    return true;
}
function db_last_id(): int { return 1; }

$ROOT = dirname(__DIR__);
require $ROOT.'/includes/helpers.php';
require $ROOT.'/includes/notifications.php';
require $ROOT.'/includes/team.php';

$pass = 0; $fail = 0;
function check(string $l, mixed $g, mixed $w): void { global $pass, $fail;
    if ($g === $w) { $pass++; echo "  ok   $l\n"; }
    else { $fail++; echo "  FAIL $l — obtenu ".var_export($g, true)." / attendu ".var_export($w, true)."\n"; } }

echo "\n1. Mode de rapport\n";
check('par défaut : un seul rapport', report_mode([]), 'principal');
check('un rapport par technicien', report_mode(['report_mode' => 'chacun']), 'chacun');
check('valeur inconnue : un seul rapport', report_mode(['report_mode' => 'x']), 'principal');

echo "\n2. État du rapport d'un renfort\n";
check('un seul rapport : rien à faire', team_report_state(['diagnostic' => 'x'], 'principal'), 'non_demande');
check('rien écrit', team_report_state(['diagnostic' => '', 'report' => ''], 'chacun'), 'a_rediger');
check('commencé', team_report_state(['diagnostic' => 'Joint usé', 'report' => ''], 'chacun'), 'brouillon');
check('envoyé', team_report_state(['submitted_at' => '2026-11-17 10:00:00'], 'chacun'), 'rendu');
check('modifiable après le rapport du principal', team_report_open('rapport_rendu'), true);
check('plus modifiable une fois la facture envoyée', team_report_open('facture_envoyee'), false);
check('plus modifiable une fois clôturée', team_report_open('cloturee'), false);

echo "\n3. Ajout d'un renfort\n";
$actor = ['id' => 5, 'name' => 'Léa'];
check('le principal ne peut pas être son propre renfort', team_add(9, 1, $actor)['ok'], false);
check('technicien inactif refusé', team_add(9, 3, $actor)['ok'], false);
check('technicien inconnu refusé', team_add(9, 42, $actor)['ok'], false);
$r = team_add(9, 2, $actor);
check('Karim ajouté', [$r['ok'], $r['name'] ?? null], [true, 'Karim']);
check('historique : « Renfort ajouté : Karim »', (bool)array_filter($DB['exec'], static fn($e) => str_contains($e[0], 'intervention_history') && in_array('Renfort ajouté : Karim', $e[1], true)), true);
check('pas deux fois', team_add(9, 2, $actor)['ok'], false);

echo "\n4. Rapport du renfort\n";
$DB['exec'] = [];
check('enregistré', team_report_save(9, 2, ['diagnostic' => ' Joint usé ', 'report' => 'Joint remplacé', 'arrived_time' => '09:15', 'close_time' => '9h'], false), true);
$u = $DB['exec'][0] ?? ['', []];
check('texte nettoyé', $u[1][0] ?? null, 'Joint usé');
check('heure valide gardée', $u[1][2] ?? null, '09:15');
check('heure mal écrite ignorée', array_key_exists(3, $u[1]) ? $u[1][3] : 'absent', null);
check('brouillon : pas de date d\'envoi', str_contains($u[0], 'submitted_at'), false);
$DB['exec'] = [];
team_report_save(9, 2, ['diagnostic' => 'a', 'report' => 'b'], true);
check('envoyé : date d\'envoi posée', str_contains($DB['exec'][0][0] ?? '', 'submitted_at = NOW()'), true);
check('un technicien qui n\'est pas en renfort ne peut rien enregistrer', team_report_save(9, 3, ['diagnostic' => 'a'], true), false);

echo "\n".($fail === 0 ? "TOUTES LES $pass ASSERTIONS PASSENT" : "$fail ÉCHEC(S) sur ".($pass + $fail))."\n";
exit($fail === 0 ? 0 : 1);
