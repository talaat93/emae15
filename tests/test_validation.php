<?php
declare(strict_types=1);
/**
 * Validation d'une intervention (includes/review.php) : sans prestation, ce n'est plus un manque
 * bloquant ; « valider sans facture » clôture la fiche sans rien préparer ni envoyer ;
 * la facture automatique est activée par défaut. Base en mémoire, aucun réseau.
 */
define('EMAE_TESTS_SANS_RESEAU', true);

$DB = ['iv' => null, 'invoice' => null, 'exec' => [], 'settings' => []];
function db_fetch(string $s, array $p = []): ?array {
    global $DB;
    if (str_contains($s, 'FROM interventions')) return $DB['iv'];
    if (str_contains($s, 'FROM invoices')) return $DB['invoice'];
    return null;
}
function db_fetch_all(string $s, array $p = []): array { global $DB; return str_contains($s, 'integration_settings') ? $DB['settings'] : []; }
function db_execute(string $s, array $p = []): bool { global $DB; $DB['exec'][] = [$s, $p]; return true; }
function db_last_id(): int { return 1; }

$ROOT = dirname(__DIR__);
require $ROOT.'/includes/helpers.php';
require $ROOT.'/includes/workflow.php';
require $ROOT.'/includes/notifications.php';
require $ROOT.'/includes/integrations.php';
require $ROOT.'/includes/review.php';
require $ROOT.'/includes/pricing.php';
require $ROOT.'/includes/pennylane.php';

$pass = 0; $fail = 0;
function check(string $l, mixed $g, mixed $w): void { global $pass, $fail;
    if ($g === $w) { $pass++; echo "  ok   $l\n"; }
    else { $fail++; echo "  FAIL $l — obtenu ".var_export($g, true)." / attendu ".var_export($w, true)."\n"; } }
$sql = static function (string $needle) use (&$DB): array {
    global $DB; return array_values(array_filter($DB['exec'], static fn($e) => str_contains($e[0], $needle)));
};

$rapport = ['id' => 7, 'status' => 'rapport_verifie', 'tech_completed_at' => '2026-11-17 10:00:00', 'category' => 'plomberie', 'ref' => 'INT-7',
    'tech_diagnostic' => 'Joint usé.', 'tech_report' => 'Remplacement du joint.', 'tech_lines' => '[]',
    'tech_photos' => json_encode(['a.jpg', 'b.jpg']), 'client_signature' => 'data:image/png;base64,xx', 'tech_client_name' => 'M. Martin', 'tech_job_completed' => 1];

echo "\n1. Une intervention sans prestation n'est plus bloquée\n";
check('aucun manque si tout est rempli sauf les prestations', review_rule_missing($rapport), []);
check('les vrais manques restent bloquants', review_rule_missing(['tech_report' => ''] + $rapport), ['Travaux réalisés non décrits']);

echo "\n2. Facture automatique\n";
check('activée par défaut', review_invoice_auto(), true);
$DB['settings'] = [['cle' => 'facture_auto', 'valeur' => '0', 'secret' => 0]];
integration_settings_rows(true);
check('désactivée dans les réglages', review_invoice_auto(), false);
$DB['settings'] = []; integration_settings_rows(true);

echo "\n3. Valider sans facture\n";
$DB['iv'] = $rapport; $DB['invoice'] = null; $DB['exec'] = [];
$r = review_accept(7, null, 'dispatcher', 1, 'Léa', 'Rapport validé par le dispatcher', false);
check('aucune facture renvoyée', $r, null);
$upd = $sql('UPDATE interventions SET');
check('la fiche passe en « Clôturée »', in_array('cloturee', $upd[0][1] ?? [], true), true);
check('aucun brouillon de facture créé', $sql('INSERT INTO invoices'), []);
$hist = $sql('INSERT INTO intervention_history');
check('historique : « Validée sans facture »', str_starts_with((string)($hist[0][1][6] ?? ''), 'Validée sans facture'), true);

echo "\n4. Un brouillon existant est annulé, une facture partie n'est jamais touchée\n";
$DB['exec'] = []; $DB['invoice'] = ['id' => 55, 'status' => 'brouillon', 'line_items' => '[]'];
review_accept(7, null, 'dispatcher', 1, 'Léa', '', false);
check('brouillon local annulé', (bool)$sql("SET status = 'annulee'"), true);
$DB['exec'] = []; $DB['invoice'] = ['id' => 56, 'status' => 'envoyee', 'line_items' => '[]'];
check('facture envoyée : renvoyée telle quelle', review_accept(7, null, 'dispatcher', 1, 'Léa', '', false), 56);
check('facture envoyée : aucune modification', $DB['exec'], []);

echo "\n".($fail === 0 ? "TOUTES LES $pass ASSERTIONS PASSENT" : "$fail ÉCHEC(S) sur ".($pass + $fail))."\n";
exit($fail === 0 ? 0 : 1);
