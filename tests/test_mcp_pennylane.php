<?php
declare(strict_types=1);
/**
 * Connecteur Claude « Pennylane » (serveur MCP) : protocole, clé d'accès, contrôles des
 * arguments, garde-fou « brouillon », et aucune requête réseau en mode simulation.
 * Aucune base de données : les fonctions db_* sont remplacées par une base en mémoire.
 */
define('EMAE_TESTS_SANS_RESEAU', true);

$DB = ['settings' => [], 'integration_settings' => [], 'integration_log' => []];
function db_fetch_all(string $s, array $p = []): array { global $DB;
    if (str_contains($s, 'FROM integration_settings')) return array_values($DB['integration_settings']);
    if (str_contains($s, 'FROM settings')) return array_values($DB['settings']);
    return []; }
function db_fetch(string $s, array $p = []): ?array { return null; }
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
require $ROOT.'/includes/mcp_pennylane.php';

$pass = 0; $fail = 0;
function check(string $l, mixed $g, mixed $w): void { global $pass, $fail;
    if ($g === $w) { $pass++; echo "  ok   $l\n"; }
    else { $fail++; echo "  FAIL $l — obtenu ".var_export($g, true)." / attendu ".var_export($w, true)."\n"; } }
function regler(string $cle, string $valeur, bool $secret = false): void { global $DB;
    $DB['integration_settings'][$cle] = ['cle' => $cle, 'valeur' => $valeur, 'secret' => $secret ? 1 : 0];
    integration_settings_rows(true); }
function rpc(string $method, array $params = [], int $id = 1): ?array {
    return mcp_pennylane_handle(['jsonrpc' => '2.0', 'id' => $id, 'method' => $method, 'params' => $params]); }
function appel(string $outil, array $args = []): array {
    $r = rpc('tools/call', ['name' => $outil, 'arguments' => $args]);
    return ['erreur' => $r['result']['isError'] ?? null, 'data' => json_decode($r['result']['content'][0]['text'] ?? 'null', true)]; }

$ligne = ['label' => 'Remplacement disjoncteur', 'quantity' => 2, 'unit_price_ht' => 45.5, 'vat_rate' => 10];

echo "\n1. Clé d'accès\n";
regler('mcp_pennylane_key', '', true);
check('sans clé configurée : tout est refusé', mcp_pennylane_authorized(''), false);
regler('mcp_pennylane_key', 'cle-secrete-de-test', true);
check('bonne clé acceptée', mcp_pennylane_authorized('cle-secrete-de-test'), true);
check('mauvaise clé refusée', mcp_pennylane_authorized('cle-secrete-de-tesT'), false);
check('clé vide refusée', mcp_pennylane_authorized(''), false);

echo "\n2. Protocole MCP\n";
regler('pennylane_api_key', 'jeton-de-test', true);
regler('mode_simulation', '1');
$r = rpc('initialize', ['protocolVersion' => '2025-03-26', 'capabilities' => new stdClass(), 'clientInfo' => ['name' => 'test', 'version' => '1']]);
check('initialize : version demandée reprise', $r['result']['protocolVersion'] ?? null, '2025-03-26');
check('initialize : outils annoncés', isset($r['result']['capabilities']['tools']), true);
check('initialize : simulation signalée à Claude', str_contains((string)$r['result']['instructions'], 'SIMULATION'), true);
check('initialize : version inconnue → la plus récente', rpc('initialize', ['protocolVersion' => '1999-01-01'])['result']['protocolVersion'], MCP_PENNYLANE_VERSIONS[0]);
check('notification : aucune réponse', mcp_pennylane_handle(['jsonrpc' => '2.0', 'method' => 'notifications/initialized']), null);
check('ping', rpc('ping')['result'] == new stdClass(), true);
check('méthode inconnue → -32601', rpc('resources/list')['error']['code'] ?? null, -32601);
check('message invalide → -32600', mcp_pennylane_handle(['id' => 1])['error']['code'] ?? null, -32600);
$noms = array_column(rpc('tools/list')['result']['tools'], 'name');
check('10 outils', count($noms), 10);
check('aucun outil de suppression, finalisation, envoi ou paiement',
    array_values(array_filter($noms, static fn($n) => preg_match('/delete|finaliz|send|paid|update/', $n))), []);
check('outil inconnu → -32602', rpc('tools/call', ['name' => 'pennylane_delete_invoice'])['error']['code'] ?? null, -32602);
$annot = array_column(rpc('tools/list')['result']['tools'], 'annotations', 'name');
check('les lectures sont marquées en lecture seule', $annot['pennylane_list_customer_invoices']['readOnlyHint'], true);
check('les créations ne le sont pas', $annot['pennylane_create_quote']['readOnlyHint'], false);

echo "\n3. Outils en simulation : aucune requête réseau\n";
$avant = integration_http_calls();
$f = appel('pennylane_list_customer_invoices', ['limit' => 2]);
check('factures listées', $f['erreur'], false);
check('réponse marquée SIMULATION', isset($f['data']['simulation']), true);
check('résumé compact (numéro, total TTC)', isset($f['data']['items'][0]['number'], $f['data']['items'][0]['total_ttc']), true);
check('détail d\'une facture', appel('pennylane_get_customer_invoice', ['id' => 910002])['data']['invoice_number'] ?? null, 'SIM-F-0002');
check('clients listés', appel('pennylane_list_customers')['erreur'], false);
check('devis listés', appel('pennylane_list_quotes')['erreur'], false);
check('société', str_contains((string)(appel('pennylane_company')['data']['company']['name'] ?? ''), 'SIMULATION'), true);
$c = appel('pennylane_create_draft_invoice', ['customer_id' => 900001, 'lines' => [$ligne], 'subject' => 'Dépannage']);
check('brouillon de facture créé', $c['erreur'], false);
check('total attendu calculé (2 × 45,50 HT à 10 %)', $c['data']['total_ttc_attendu'] ?? null, '100.10');
check('devis créé', appel('pennylane_create_quote', ['customer_id' => 900001, 'lines' => [$ligne]])['erreur'], false);
check('AUCUNE requête HTTP pendant la simulation', integration_http_calls() - $avant, 0);

echo "\n4. Garde-fous sur les arguments\n";
check('id non numérique refusé (pas d\'injection dans le chemin)', appel('pennylane_get_customer', ['id' => '12/../../me'])['erreur'], true);
check('id manquant refusé', appel('pennylane_get_quote')['erreur'], true);
check('client manquant refusé', appel('pennylane_create_quote', ['lines' => [$ligne]])['erreur'], true);
check('aucune ligne refusée', appel('pennylane_create_draft_invoice', ['customer_id' => 1, 'lines' => []])['erreur'], true);
check('TVA hors 20/10/5,5 refusée', appel('pennylane_create_quote', ['customer_id' => 1, 'lines' => [['vat_rate' => 7] + $ligne]])['erreur'], true);
check('quantité nulle refusée', appel('pennylane_create_quote', ['customer_id' => 1, 'lines' => [['quantity' => 0] + $ligne]])['erreur'], true);
check('date invalide refusée', appel('pennylane_create_quote', ['customer_id' => 1, 'lines' => [$ligne], 'date' => '2026-02-30'])['erreur'], true);
check('échéance avant la date refusée', appel('pennylane_create_quote', ['customer_id' => 1, 'lines' => [$ligne], 'date' => '2026-05-10', 'deadline' => '2026-05-01'])['erreur'], true);
check('filtre de date invalide refusé', appel('pennylane_list_customer_invoices', ['date_from' => 'hier'])['erreur'], true);

echo "\n5. Corps envoyé à Pennylane\n";
$b = mcp_pl_document_body(['customer_id' => '42', 'lines' => [$ligne], 'date' => '2026-05-10'], true);
check('facture : draft forcé à true', $b['draft'] ?? null, true);
check('facture : client numérique', $b['customer_id'], 42);
check('échéance par défaut : +30 jours', $b['deadline'], '2026-06-09');
check('ligne : TVA 10 % → FR_100', $b['invoice_lines'][0]['vat_rate'], 'FR_100');
check('ligne : prix HT à 2 décimales', $b['invoice_lines'][0]['raw_currency_unit_price'], '45.50');
check('ligne : unité par défaut', $b['invoice_lines'][0]['unit'], 'unité');
check('référence externe EMAE-MCP-F', str_starts_with($b['external_reference'], 'EMAE-MCP-F-'), true);
$b = mcp_pl_document_body(['customer_id' => 42, 'lines' => [$ligne]], false);
check('devis : pas de champ draft', array_key_exists('draft', $b), false);
check('devis : référence externe EMAE-MCP-D', str_starts_with($b['external_reference'], 'EMAE-MCP-D-'), true);

echo "\n6. Sans simulation, les erreurs restent lisibles\n";
regler('mode_simulation', '0');
$avant = integration_http_calls();
$e = appel('pennylane_list_customer_invoices');
check('requête tentée (réseau coupé par les tests)', integration_http_calls() - $avant > 0, true);
check('erreur renvoyée à Claude sans exception', $e['erreur'] === true && is_string($e['data']['erreur'] ?? null), true);

echo "\n7. Aucun secret dans les journaux\n";
$journal = json_encode($DB['integration_log'], JSON_UNESCAPED_UNICODE);
check('ni la clé du connecteur ni le jeton', str_contains($journal, 'cle-secrete-de-test') || str_contains($journal, 'jeton-de-test'), false);

echo "\n".($fail === 0 ? "TOUTES LES $pass ASSERTIONS PASSENT" : "$fail ÉCHEC(S) sur ".($pass + $fail))."\n";
exit($fail === 0 ? 0 : 1);
