<?php
declare(strict_types=1);
/**
 * Facture Pennylane reliée après coup (includes/invoicing.php) : recherche par numéro, refus
 * (introuvable, brouillon, déjà reliée ailleurs), suivi du paiement, brouillon du site
 * remplacé, lien retiré. Remarques de paiement proposées. Base en mémoire, aucun réseau.
 */
define('EMAE_TESTS_SANS_RESEAU', true);

$DB = [];
function reset_db(): void {
    global $DB;
    $DB = ['iv' => ['id' => 8, 'status' => 'cloturee', 'client_id' => 3, 'paid_at' => null, 'tech_completed_at' => null, 'ref' => 'INT-8'],
           'inv' => [
               1 => ['id' => 1, 'number' => 'F-2026-0042', 'status' => 'envoyee', 'intervention_id' => null, 'review_id' => null, 'total_ttc' => 240.0, 'total_ht' => 200.0, 'line_items' => '[]'],
               2 => ['id' => 2, 'number' => 'F-2026-0043', 'status' => 'payee', 'intervention_id' => null, 'review_id' => null, 'total_ttc' => 120.0, 'total_ht' => 100.0, 'line_items' => '[]'],
               3 => ['id' => 3, 'number' => 'F-2026-0044', 'status' => 'brouillon', 'intervention_id' => null, 'review_id' => null, 'total_ttc' => 50.0, 'total_ht' => 41.67, 'line_items' => '[]'],
               4 => ['id' => 4, 'number' => 'F-2026-0045', 'status' => 'envoyee', 'intervention_id' => 99, 'review_id' => null, 'total_ttc' => 90.0, 'total_ht' => 75.0, 'line_items' => '[]'],
           ], 'exec' => []];
}
function db_fetch(string $s, array $p = []): ?array {
    global $DB;
    if (str_contains($s, "UPPER(REPLACE(number")) { foreach ($DB['inv'] as $i) if (strtoupper(str_replace(' ', '', $i['number'])) === $p[0] && $i['status'] !== 'annulee') return $i; return null; }
    if (str_contains($s, 'FROM invoices WHERE intervention_id')) { $f = array_filter($DB['inv'], static fn($i) => (int)$i['intervention_id'] === (int)$p[0] && $i['status'] !== 'annulee'); return $f ? end($f) : null; }
    if (str_contains($s, 'FROM invoices WHERE id')) return $DB['inv'][(int)$p[0]] ?? null;
    if (str_contains($s, 'SELECT ref FROM interventions')) return ['ref' => 'INT-99'];
    if (str_contains($s, 'FROM interventions')) return $DB['iv'];
    return null;
}
function db_fetch_all(string $s, array $p = []): array { return []; }
function db_execute(string $s, array $p = []): bool {
    global $DB; $DB['exec'][] = [$s, $p];
    if (preg_match('/^UPDATE invoices SET intervention_id = \?, client_id/', $s)) $DB['inv'][(int)$p[2]]['intervention_id'] = $p[0];
    if (str_starts_with($s, 'UPDATE invoices SET intervention_id = NULL')) $DB['inv'][(int)$p[0]]['intervention_id'] = null;
    if (str_contains($s, "SET status = 'annulee'")) $DB['inv'][(int)$p[0]]['status'] = 'annulee';
    if (str_starts_with($s, 'UPDATE interventions SET') && preg_match('/status = \?/', $s)) {
        preg_match_all('/(\w+) = \?/', $s, $m); $DB['iv'] = array_merge($DB['iv'], array_combine(array_slice($m[1], 0, count($p) - 1), array_slice($p, 0, count($p) - 1)));
    }
    return true;
}
function db_last_id(): int { return 1; }

$ROOT = dirname(__DIR__);
require $ROOT.'/includes/helpers.php';
require $ROOT.'/includes/workflow.php';
require $ROOT.'/includes/notifications.php';
require $ROOT.'/includes/integrations.php';
require $ROOT.'/includes/review.php';
require $ROOT.'/includes/pricing.php';
require $ROOT.'/includes/pennylane.php';
require $ROOT.'/includes/invoicing.php';

$pass = 0; $fail = 0;
function check(string $l, mixed $g, mixed $w): void { global $pass, $fail;
    if ($g === $w) { $pass++; echo "  ok   $l\n"; }
    else { $fail++; echo "  FAIL $l — obtenu ".var_export($g, true)." / attendu ".var_export($w, true)."\n"; } }
$actor = ['id' => 5, 'name' => 'Léa'];
$hist = static function (): array { global $DB; return array_values(array_map(static fn($e) => (string)($e[1][6] ?? ''), array_filter($DB['exec'], static fn($e) => str_contains($e[0], 'intervention_history')))); };

echo "\n1. Recherche par numéro\n";
reset_db();
check('numéro normalisé (espaces, minuscules)', invoice_number_key(' f-2026 0042 '), 'F-20260042');
check('trouvée malgré les minuscules', invoice_find_by_number('f-2026-0042')['id'] ?? null, 1);
check('introuvable', invoice_link_number(8, 'F-9999', $actor)['ok'], false);
check('brouillon refusé', str_contains((string)invoice_link_number(8, 'F-2026-0044', $actor)['error'], 'brouillon'), true);
check('déjà reliée ailleurs : refus avec la référence', str_contains((string)invoice_link_number(8, 'F-2026-0045', $actor)['error'], 'INT-99'), true);

echo "\n2. Facture non payée reliée à une intervention validée sans facture\n";
reset_db();
$r = invoice_link_number(8, 'F-2026-0042', $actor);
check('reliée', [$r['ok'], $r['number'] ?? null, $r['paid'] ?? null], [true, 'F-2026-0042', false]);
check('la facture pointe vers l\'intervention', $DB['inv'][1]['intervention_id'], 8);
check('la fiche passe en « Facture envoyée » (paiement suivi)', $DB['iv']['status'], 'facture_envoyee');
check('historique', in_array('Facture F-2026-0042 reliée : paiement suivi avec Pennylane', $hist(), true), true);

echo "\n3. Facture déjà payée\n";
reset_db(); $DB['iv']['status'] = 'rapport_verifie';
$r = invoice_link_number(8, 'F-2026-0043', $actor);
check('reliée, payée', [$r['ok'], $r['paid']], [true, true]);
check('fiche clôturée', $DB['iv']['status'], 'cloturee');
check('paiement noté', $DB['iv']['payment_status'] ?? null, 'payé');

echo "\n4. Brouillon préparé par le site remplacé\n";
reset_db(); $DB['iv']['status'] = 'facture_brouillon';
$DB['inv'][5] = ['id' => 5, 'number' => '', 'status' => 'brouillon', 'intervention_id' => 8, 'review_id' => 7, 'total_ttc' => 0.0, 'total_ht' => 0.0, 'line_items' => '[]'];
check('reliée', invoice_link_number(8, 'F-2026-0042', $actor)['ok'], true);
check('brouillon du site annulé', $DB['inv'][5]['status'], 'annulee');
reset_db();
$DB['inv'][6] = ['id' => 6, 'number' => 'F-2026-0010', 'status' => 'envoyee', 'intervention_id' => 8, 'review_id' => 7, 'total_ttc' => 10.0, 'total_ht' => 8.0, 'line_items' => '[]'];
check('une vraie facture déjà liée bloque une seconde', invoice_link_number(8, 'F-2026-0042', $actor)['ok'], false);

echo "\n5. Retirer le lien\n";
reset_db(); invoice_link_number(8, 'F-2026-0042', $actor);
check('facture reliée à la main : lien retiré', [invoice_unlink(8, 1, $actor), $DB['inv'][1]['intervention_id']], [true, null]);
$DB['inv'][6] = ['id' => 6, 'number' => 'F-2026-0010', 'status' => 'envoyee', 'intervention_id' => 8, 'review_id' => 7];
check('facture préparée par le site : jamais détachée', invoice_unlink(8, 6, $actor), false);
check('autre intervention : refus', invoice_unlink(8, 4, $actor), false);

echo "\n6. Remarques de paiement\n";
check('« Le propriétaire paie » proposé', in_array('Le propriétaire paie', payment_note_presets(), true), true);
check('7 remarques, toutes de moins de 500 caractères', [count(payment_note_presets()), max(array_map('mb_strlen', payment_note_presets())) < 500], [7, true]);

echo "\n".($fail === 0 ? "TOUTES LES $pass ASSERTIONS PASSENT" : "$fail ÉCHEC(S) sur ".($pass + $fail))."\n";
exit($fail === 0 ? 0 : 1);
