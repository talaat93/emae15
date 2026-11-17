<?php
declare(strict_types=1);
/**
 * Rapport corrigé par Claude (includes/correction.php) : garde-fous contre toute invention
 * (nombres ajoutés ou retirés, longueur), texte d'origine toujours gardé, version corrigée
 * utilisée par le PDF. Claude est remplacé par une réponse fixe : aucun réseau, aucune base.
 */
define('EMAE_TESTS_SANS_RESEAU', true);

$DB = ['iv' => null, 'exec' => []];
function db_fetch(string $s, array $p = []): ?array { global $DB; return $DB['iv']; }
function db_fetch_all(string $s, array $p = []): array { return []; }
function db_execute(string $s, array $p = []): bool { global $DB; $DB['exec'][] = [$s, $p]; return true; }
function db_last_id(): int { return 1; }
// Réponse de Claude choisie par chaque test.
$CLAUDE = ['ok' => false, 'simulated' => true, 'data' => null, 'error' => 'SIMULATION : aucune clé API Claude n\'est configurée.'];
function claude_structured(array $req): array { global $CLAUDE, $LAST; $LAST = $req; return $CLAUDE + ['refused' => false, 'model' => 'test']; }

$ROOT = dirname(__DIR__);
require $ROOT.'/includes/helpers.php';
require $ROOT.'/includes/notifications.php';
require $ROOT.'/includes/integrations.php';
require $ROOT.'/includes/correction.php';

$pass = 0; $fail = 0;
function check(string $l, mixed $g, mixed $w): void { global $pass, $fail;
    if ($g === $w) { $pass++; echo "  ok   $l\n"; }
    else { $fail++; echo "  FAIL $l — obtenu ".var_export($g, true)." / attendu ".var_export($w, true)."\n"; } }

echo "\n1. Garde-fous\n";
$o = 'le disjoncteur a sauter, pression a 0,8 bar';
check('correction propre acceptée', correction_acceptable($o, 'Le disjoncteur a déclenché ; pression relevée à 0,8 bar.'), true);
check('nombre modifié : refusée', correction_acceptable($o, 'Le disjoncteur a déclenché ; pression relevée à 1,2 bar.'), false);
check('mesure inventée : refusée', correction_acceptable('joint changé', 'Joint remplacé, pression contrôlée à 3 bars.'), false);
check('nombre supprimé : refusé', correction_acceptable($o, 'Le disjoncteur a déclenché.'), false);
check('texte court reformulé en phrase : accepté', correction_acceptable('fuite raccord', 'Fuite constatée sur un raccord.'), true);
check('texte beaucoup trop long : refusé', correction_acceptable('joint changé', str_repeat('Remplacement du joint défectueux et vérifications complètes. ', 5)), false);
check('correction vide : refusée', correction_acceptable('joint changé', ''), false);
check('original vide : rien à corriger', correction_acceptable('', ''), true);
check('nombres repérés (triés)', correction_numbers('230 V, 1,5 mm² et 3 bars'), ['1.5', '3', '230']);

echo "\n2. Texte affiché\n";
check('version corrigée si elle existe', correction_text(['tech_report' => 'joint changer', 'tech_report_corrige' => 'Joint remplacé.'], 'tech_report'), 'Joint remplacé.');
check('sinon le texte du technicien', correction_text(['tech_report' => 'joint changer', 'tech_report_corrige' => null], 'tech_report'), 'joint changer');
check('rapport corrigé détecté', [correction_has(['tech_diagnostic_corrige' => 'x']), correction_has([])], [true, false]);

echo "\n3. Correction d'un rapport\n";
$DB['iv'] = ['id' => 4, 'category' => 'plomberie', 'tech_diagnostic' => 'fuite au niveau du raccord sous l evier', 'tech_report' => 'joint changer, plus de fuite', 'status' => 'rapport_rendu', 'technician_id' => 1, 'ref' => 'INT-4', 'tech_fault_label' => '', 'type_label' => ''];
$r = correction_run(4);
check('sans clé Claude : pas de correction, texte d\'origine gardé', [$r['ok'], $DB['exec']], [false, []]);
$CLAUDE = ['ok' => true, 'simulated' => false, 'error' => null, 'data' => ['diagnostic' => 'Fuite constatée au niveau du raccord sous l\'évier.', 'travaux' => 'Remplacement du joint ; plus de fuite constatée.']];
$r = correction_run(4);
check('corrigé', [$r['ok'], $r['fields']], [true, ['tech_diagnostic' => 'corrige', 'tech_report' => 'corrige']]);
$upd = array_values(array_filter($DB['exec'], static fn($e) => str_contains($e[0], 'tech_diagnostic_corrige')))[0] ?? ['', []];
check('versions corrigées rangées à part', [$upd[1][0], $upd[1][1]], ['Fuite constatée au niveau du raccord sous l\'évier.', 'Remplacement du joint ; plus de fuite constatée.']);
check('le texte du technicien n\'est jamais réécrit', (bool)array_filter($DB['exec'], static fn($e) => preg_match('/SET tech_diagnostic = |SET tech_report = /', $e[0])), false);
check('consigne : ne rien ajouter', str_contains($LAST['system'], "N'ajoute AUCUNE information"), true);
check('consigne : vocabulaire du métier', str_contains($LAST['system'], 'Plomberie'), true);
$DB['exec'] = [];
$CLAUDE['data'] = ['diagnostic' => 'Fuite constatée au niveau du raccord sous l\'évier.', 'travaux' => 'Remplacement du joint, pression contrôlée à 3 bars.'];
$r = correction_run(4);
check('travaux avec une mesure inventée : correction écartée', $r['fields']['tech_report'] ?? null, 'refuse');
$upd = array_values(array_filter($DB['exec'], static fn($e) => str_contains($e[0], 'tech_diagnostic_corrige')))[0] ?? ['', []];
check('… le diagnostic corrigé reste, les travaux gardent le texte d\'origine', [$upd[1][0] !== null, $upd[1][1]], [true, null]);
check('historique : correction écartée signalée', (bool)array_filter($DB['exec'], static fn($e) => str_contains($e[0], 'intervention_history') && str_contains((string)($e[1][6] ?? ''), 'écartée')), true);
$DB['iv']['tech_diagnostic'] = ''; $DB['iv']['tech_report'] = '';
check('rapport vide : rien à corriger', correction_run(4)['ok'], false);

echo "\n".($fail === 0 ? "TOUTES LES $pass ASSERTIONS PASSENT" : "$fail ÉCHEC(S) sur ".($pass + $fail))."\n";
exit($fail === 0 ? 0 : 1);
