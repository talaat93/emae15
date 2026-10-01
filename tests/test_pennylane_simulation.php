<?php
declare(strict_types=1);
/**
 * Le mode simulation de Pennylane est étanche : aucune requête HTTP ne part.
 *
 * Méthode : integration_http() est la seule porte vers le réseau ; elle compte chaque tentative
 * (integration_http_calls()). On active la simulation, on appelle toutes les fonctions qui
 * parlent à Pennylane, et on vérifie que le compteur reste à zéro. Pour prouver que le compteur
 * fonctionne, on désactive ensuite la simulation : le compteur doit alors bouger (le réseau est
 * coupé par EMAE_TESTS_SANS_RESEAU, donc rien ne part réellement, même dans ce cas).
 * Aucune base de données : les fonctions db_* sont remplacées par une base en mémoire.
 */
define('EMAE_TESTS_SANS_RESEAU', true);

$DB = ['settings' => [], 'integration_settings' => [], 'integration_log' => [], 'mails' => 0];
function db_fetch_all(string $s, array $p = []): array { global $DB;
    if (str_contains($s, 'FROM integration_settings')) return array_values($DB['integration_settings']);
    if (str_contains($s, 'FROM settings')) return array_values($DB['settings']);
    return []; }
function db_fetch(string $s, array $p = []): ?array { global $DB;
    if (str_contains($s, 'FROM invoices WHERE id')) return ['id' => (int)$p[0], 'intervention_id' => null, 'client_id' => null, 'status' => 'envoyee',
        'number' => 'F-TEST-1', 'pennylane_id' => '123456', 'line_items' => '[]', 'total_ttc' => '240.00', 'remaining_ttc' => '240.00',
        'due_date' => '2026-01-31', 'issue_date' => '2026-01-01', 'label' => 'Test'];
    if (str_contains($s, 'FROM settings')) { foreach ($DB['settings'] as $r) if ($r['setting_key'] === ($p[0] ?? null)) return $r; }
    return null; }
function db_execute(string $s, array $p = []): bool { global $DB;
    if (str_starts_with(ltrim($s), 'INSERT INTO integration_settings')) $DB['integration_settings'][$p[0]] = ['cle' => $p[0], 'valeur' => $p[1], 'secret' => $p[2]];
    if (str_starts_with(ltrim($s), 'INSERT INTO integration_log')) $DB['integration_log'][] = $p;
    return true; }
function db_last_id(): int { return 1; }

$ROOT = dirname(__DIR__);
require $ROOT.'/includes/helpers.php';
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
function regler(string $cle, string $valeur, bool $secret = false): void { global $DB;
    $DB['integration_settings'][$cle] = ['cle' => $cle, 'valeur' => $valeur, 'secret' => $secret ? 1 : 0];
    integration_settings_rows(true); }

// config.local.php peut contenir un vrai jeton sur une machine de développement : le test ne
// doit pas en dépendre. On vérifie donc la simulation par le réglage, pas par l'absence de jeton.
$jetonFichier = integration_secret_from_file('pennylane_api_key');

echo "\n1. Sans jeton, la simulation est automatique\n";
if (!$jetonFichier) {
    check('aucun jeton → simulation', pennylane_simulated(), true);
} else {
    echo "  info jeton présent dans config.local.php : cas ignoré\n";
}

echo "\n2. Jeton présent + mode_simulation = 1 : aucune requête réseau\n";
regler('pennylane_api_key', 'jeton-de-test-ne-doit-jamais-sortir', true);
regler('mode_simulation', '1');
check('simulation active', pennylane_simulated(), true);
$avant = integration_http_calls();

$r = pennylane_request('GET', '/me');
check('GET /me répond', $r['ok'], true);
check('réponse marquée simulation', $r['simulated'] ?? false, true);
check('société fictive marquée SIMULATION', str_contains((string)($r['data']['company']['name'] ?? ''), 'SIMULATION'), true);

$it = pennylane_each('/customer_invoices', ['limit' => 2]);
$nums = [];
foreach ($it as $f) $nums[] = $f['invoice_number'];
check('le générateur parcourt les factures fictives', count($nums) > 0, true);
check('numéros marqués SIM', array_filter($nums, static fn($n) => !str_starts_with((string)$n, 'SIM')), []);
check('bilan du générateur', $it->getReturn()['ok'], true);

$t = pennylane_test_connection();
check('test de connexion : simulation annoncée', $t['simulated'], true);
check('test de connexion : 6 routes, toutes « simulation »', array_unique(array_column($t['routes'], 'etat')), ['simulation']);

check('envoi par e-mail simulé', pennylane_send_email(1, ['client@example.invalid'])['ok'], true);
check('marquer payée simulé', pennylane_mark_paid(1)['ok'], true);
check('finalisation simulée', pennylane_finalize(1)['ok'], true);
check('synchronisation simulée', str_starts_with(pennylane_sync_run('tests')['message'], 'SIMULATION'), true);

check('AUCUNE requête HTTP pendant la simulation', integration_http_calls() - $avant, 0);

echo "\n3. Relance en simulation sans adresse de test : rien ne part\n";
$mailsAvant = integration_http_calls();
$inv = db_fetch('SELECT * FROM invoices WHERE id = ?', [1]);
check('relance non envoyée', reminder_send($inv, 1, 'client@example.invalid'), false);
check('toujours aucune requête HTTP', integration_http_calls() - $mailsAvant, 0);

echo "\n4. Contrôle du compteur : sans simulation, une requête est bien tentée\n";
regler('mode_simulation', '0');
check('simulation désactivée', pennylane_simulated(), false);
$avant = integration_http_calls();
$r = pennylane_request('GET', '/me');
check('la requête a été tentée (réseau coupé par les tests)', integration_http_calls() - $avant > 0, true);
check('erreur lisible, pas d\'exception', is_string($r['error']), true);

echo "\n5. Le jeton n'apparaît dans aucun journal\n";
$journal = json_encode($DB['integration_log'], JSON_UNESCAPED_UNICODE);
check('journal en base sans jeton', str_contains($journal, 'jeton-de-test-ne-doit-jamais-sortir'), false);

echo "\n".($fail === 0 ? "TOUTES LES $pass ASSERTIONS PASSENT" : "$fail ÉCHEC(S) sur ".($pass + $fail))."\n";
exit($fail === 0 ? 0 : 1);
