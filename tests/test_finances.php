<?php
declare(strict_types=1);
/**
 * Calculs du tableau de bord financier (includes/finance.php) sur un jeu de factures fictives
 * dont les résultats sont connus d'avance. Aucune base de données, aucune requête réseau.
 */
define('EMAE_TESTS_SANS_RESEAU', true);

function db_fetch_all(string $s, array $p = []): array { return []; }
function db_fetch(string $s, array $p = []): ?array { return null; }
function db_execute(string $s, array $p = []): bool { return true; }
function db_last_id(): int { return 1; }

$ROOT = dirname(__DIR__);
require $ROOT.'/includes/helpers.php';
require $ROOT.'/includes/notifications.php';
require $ROOT.'/includes/integrations.php';
require $ROOT.'/includes/review.php';
require $ROOT.'/includes/pricing.php';
require $ROOT.'/includes/pennylane.php';
require $ROOT.'/includes/invoicing.php';
require $ROOT.'/includes/finance.php';

$pass = 0; $fail = 0;
function check(string $l, mixed $g, mixed $w): void { global $pass, $fail;
    if ($g === $w) { $pass++; echo "  ok   $l\n"; }
    else { $fail++; echo "  FAIL $l — obtenu ".var_export($g, true)." / attendu ".var_export($w, true)."\n"; } }

$today = '2026-10-15';
$f = static fn(int $id, string $status, float $ttc, ?float $rest, ?string $issue, ?string $due, ?string $paid = null, int $client = 1, string $cat = '', string $tech = '') => [
    'id' => $id, 'status' => $status, 'number' => 'F-'.$id, 'label' => '', 'client_id' => $client, 'intervention_id' => null,
    'total_ht' => round($ttc / 1.2, 2), 'total_ttc' => $ttc, 'remaining_ttc' => $rest, 'issue_date' => $issue, 'due_date' => $due,
    'paid_at' => $paid, 'created_at' => $issue, 'iv_category' => $cat, 'tech_name' => $tech, 'lastname' => 'Client'.$client, 'firstname' => ''];
$factures = [
    $f(1, 'payee',     120.0, 0.0,   '2026-10-02', '2026-10-02', '2026-10-05 10:00:00', 1, 'electricite', 'Thomas'),  // payée ce mois, 3 j
    $f(2, 'envoyee',   300.0, 300.0, '2026-10-10', '2026-11-09', null, 1, 'plomberie', 'Karim'),                       // émise ce mois, pas échue
    $f(3, 'envoyee',   200.0, 150.0, '2026-09-01', '2026-09-30', null, 2, 'electricite', 'Thomas'),                    // 15 j de retard, partiel
    $f(4, 'envoyee',   500.0, null,  '2026-07-01', '2026-08-01', null, 2),                                             // 75 j de retard, reste = total
    $f(5, 'validee',    80.0, 80.0,  '2026-08-15', '2026-09-01', null, 3),                                             // 44 j de retard
    $f(6, 'brouillon', 999.0, null,  null, null),                                                                       // à valider : ne compte pas
    $f(7, 'payee',     240.0, 0.0,   '2026-06-01', '2026-06-01', '2026-07-01 09:00:00', 3, 'chauffage', 'Julien'),  // payée en 30 j
    $f(8, 'annulee',   777.0, 777.0, '2026-10-01', '2026-10-01'),                                                       // annulée : ignorée
];

echo "\n1. Chiffres clés d'octobre 2026\n";
$k = finance_kpis($factures, $today);
check('facturé ce mois (F1 + F2)', $k['billed_month'], 420.0);
check('encaissé ce mois (F1)', $k['paid_month'], 120.0);
check('reste dû total (300 + 150 + 500 + 80)', $k['due_total'], 1030.0);
check('en retard (150 + 500 + 80)', $k['late_total'], 730.0);
check('nombre de factures en retard', $k['late_count'], 3);
check('brouillons à valider', $k['to_validate'], 1);

echo "\n2. Balance âgée\n";
$a = finance_aging($factures, $today);
check('pas encore échu : F2', [$a['non_echu']['amount'], $a['non_echu']['count']], [300.0, 1]);
check('1 à 30 jours : F3 (reste 150)', [$a['1_30']['amount'], $a['1_30']['count']], [150.0, 1]);
check('31 à 60 jours : F5', [$a['31_60']['amount'], $a['31_60']['count']], [80.0, 1]);
check('plus de 60 jours : F4', [$a['60_plus']['amount'], $a['60_plus']['count']], [500.0, 1]);
check('total de la balance = reste dû', round(array_sum(array_column($a, 'amount')), 2), $k['due_total']);

echo "\n3. Relances à faire\n";
$rem = [3 => ['count' => 1, 'last' => '2026-10-12 08:00:00'],   // relancée il y a 3 jours → pas encore
        4 => ['count' => 2, 'last' => '2026-09-20 08:00:00']];  // relancée il y a 25 jours → niveau 3
$todo = finance_reminders_due($factures, $rem, $today);
check('F3 exclue (relancée il y a moins de 7 jours)', in_array(3, array_column($todo, 'id'), true), false);
check('F4 puis F5 (plus gros retard d\'abord)', array_column($todo, 'id'), [4, 5]);
check('F4 : niveau suggéré 3', $todo[0]['suggested_level'], 3);
check('F5 : jamais relancée → niveau 1', $todo[1]['suggested_level'], 1);
check('F4 : 75 jours de retard', $todo[0]['days_late'], 75);
check('délai entre relances réglable (2 j → F3 revient)', in_array(3, array_column(finance_reminders_due($factures, $rem, $today, 2), 'id'), true), true);
check('niveau plafonné à 3', min(array_column(finance_reminders_due($factures, [4 => ['count' => 9, 'last' => '2026-01-01']], $today), 'suggested_level')) <= 3, true);

echo "\n4. Plus gros débiteurs\n";
$d = finance_top_debtors($factures, $today);
check('ordre : client 2 (650), client 1 (300), client 3 (80)', array_column($d, 'client_id'), [2, 1, 3]);
check('client 2 : tout en retard', [$d[0]['due'], $d[0]['late'], $d[0]['max_days_late']], [650.0, 650.0, 75]);
check('client 1 : rien en retard', $d[1]['late'], 0.0);

echo "\n5. Évolution mensuelle\n";
$m = finance_monthly($factures, $today);
check('12 mois, du plus ancien au plus récent', [count($m), $m[0]['month'], $m[11]['month']], [12, '2025-11', '2026-10']);
$byMonth = array_column($m, null, 'month');
check('octobre : facturé 420, encaissé 120', [$byMonth['2026-10']['billed'], $byMonth['2026-10']['paid']], [420.0, 120.0]);
check('juillet : facturé 500 (F4), encaissé 240 (F7)', [$byMonth['2026-07']['billed'], $byMonth['2026-07']['paid']], [500.0, 240.0]);
check('brouillon et annulée jamais comptés', array_sum(array_column($m, 'billed')), 1440.0);

echo "\n6. Délai moyen de paiement\n";
$dl = finance_payment_delay($factures, $today);
check('moyenne de 3 j (F1) et 30 j (F7) → 17 j', $dl, ['average' => 17, 'count' => 2]);
check('aucune facture payée → pas de moyenne inventée', finance_payment_delay([$factures[1]], $today), null);

echo "\n7. Répartition par métier et par technicien\n";
$cat = array_column(finance_breakdown($factures, 'iv_category', $today), null, 'label');
check('Électricité : F1 + F3', $cat['Électricité']['ttc'] ?? null, 320.0);
check('factures sans intervention : « Non rattaché »', $cat['Non rattaché']['ttc'] ?? null, 580.0);
$tech = array_column(finance_breakdown($factures, 'tech_name', $today), null, 'label');
check('Thomas : F1 + F3', $tech['Thomas']['ttc'] ?? null, 320.0);
check('Julien (F7, juin) compté sur 12 mois', $tech['Julien']['ttc'] ?? null, 240.0);

echo "\n".($fail === 0 ? "TOUTES LES $pass ASSERTIONS PASSENT" : "$fail ÉCHEC(S) sur ".($pass + $fail))."\n";
exit($fail === 0 ? 0 : 1);
