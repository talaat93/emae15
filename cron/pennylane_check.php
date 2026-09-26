<?php
declare(strict_types=1);
/**
 * Vérification de l'intégration Pennylane contre le VRAI compte, en LECTURE SEULE.
 * Aucune création, modification ni suppression : uniquement des requêtes GET.
 * Les données personnelles sont masquées dans l'affichage.
 *
 * Utilisation (ligne de commande uniquement) :
 *   PENNYLANE_TOKEN=xxxxx php cron/pennylane_check.php
 * (ou sans variable : le jeton des réglages / de config.local.php est utilisé)
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Accès refusé.'); }
require_once __DIR__.'/../includes/bootstrap.php';

// Jeton fourni pour ce test (jamais enregistré), sinon celui des réglages.
$tok = (string)getenv('PENNYLANE_TOKEN');
$token = $tok !== '' ? $tok : pennylane_token();
$base = (string)(app_config()['pennylane_base'] ?? PENNYLANE_BASE);
if ($token === '') { echo "Aucun jeton : définissez PENNYLANE_TOKEN ou saisissez-le dans les réglages.\n"; exit(1); }

$ok = 0; $ko = 0;
$get = static function (string $path, array $q = []) use ($token, $base): array {
    $url = $base.$path.($q ? '?'.http_build_query($q) : '');
    $r = integration_http('GET', $url, ['Authorization: Bearer '.$token, 'Accept: application/json', 'X-Use-2026-API-Changes: true'], null, 30);
    return $r;
};
$check = static function (string $label, bool $cond, string $detail = '') use (&$ok, &$ko): void {
    if ($cond) $ok++; else $ko++;
    echo ($cond ? '  [OK]   ' : '  [ÉCHEC] ').$label.($detail !== '' ? ' — '.$detail : '').PHP_EOL;
};
$mask = static fn(string $s) => $s === '' ? '' : mb_substr($s, 0, 2).str_repeat('*', max(0, mb_strlen($s) - 2));
$has = static function (array $item, array $keys): array { return array_values(array_filter($keys, static fn($k) => !array_key_exists($k, $item))); };

echo "Vérification Pennylane (lecture seule) — ".$base.PHP_EOL;

$r = $get('/me');
$check('GET /me', $r['status'] === 200, 'HTTP '.$r['status'].(isset($r['json']['company']['name']) ? ', société « '.$r['json']['company']['name'].' »' : ''));
if ($r['status'] === 401) { echo "Jeton refusé : arrêt.\n"; exit(1); }

$r = $get('/customers', ['limit' => 3]);
$items = (array)($r['json']['items'] ?? []);
$check('GET /customers (pagination has_more / next_cursor)', $r['status'] === 200 && array_key_exists('has_more', (array)$r['json']) && array_key_exists('next_cursor', (array)$r['json']), count($items).' client(s) lus');
foreach (array_slice($items, 0, 3) as $c) {
    $miss = $has($c, ['id', 'customer_type', 'emails', 'phone', 'billing_address', 'external_reference']);
    $check('  champs client #'.$c['id'].' ('.($c['customer_type'] ?? '?').', '.$mask((string)($c['name'] ?? '')).')', !$miss, $miss ? 'manquants : '.implode(', ', $miss) : 'emails, téléphone, adresse présents');
}
if ($items && !empty($items[0]['emails'][0])) {
    $r = $get('/customers', ['filter' => pennylane_filter([['emails', 'in', (string)$items[0]['emails'][0]]]), 'limit' => 1]);
    $check('filtre clients par e-mail (format JSON [{field,operator,value}])', $r['status'] === 200 && !empty($r['json']['items']), 'HTTP '.$r['status']);
}
$r = $get('/customers', ['filter' => pennylane_filter([['external_reference', 'eq', 'EMAE-C0']]), 'limit' => 1]);
$check('filtre clients par external_reference', $r['status'] === 200, 'HTTP '.$r['status']);

$r = $get('/customer_invoices', ['limit' => 5]);
$inv = (array)($r['json']['items'] ?? []);
$check('GET /customer_invoices', $r['status'] === 200, count($inv).' facture(s) lues');
foreach (array_slice($inv, 0, 3) as $i) {
    $miss = $has($i, ['id', 'invoice_number', 'status', 'paid', 'draft', 'amount', 'currency_amount_before_tax', 'remaining_amount_with_tax', 'date', 'deadline', 'customer', 'external_reference']);
    $check('  champs facture '.($i['invoice_number'] ?? '#'.$i['id']).' (statut '.($i['status'] ?? '?').' → EMAE « '.pennylane_local_status($i).' »)', !$miss, $miss ? 'manquants : '.implode(', ', $miss) : 'montant '.($i['amount'] ?? '?').' €');
}
if ($inv) {
    $r = $get('/customer_invoices/'.(int)$inv[0]['id']);
    $check('GET /customer_invoices/{id}', $r['status'] === 200 && array_key_exists('public_file_url', (array)$r['json']), 'public_file_url '.(!empty($r['json']['public_file_url']) ? 'présent' : 'vide'));
}

$r = $get('/products', ['limit' => 3]);
$check('GET /products', $r['status'] === 200, count((array)($r['json']['items'] ?? [])).' produit(s)');
$r = $get('/products', ['filter' => pennylane_filter([['external_reference', 'eq', 'EMAE-P-DEPL']]), 'limit' => 1]);
$check('filtre produits par external_reference', $r['status'] === 200, 'HTTP '.$r['status']);

$since = date('c', strtotime('-7 days'));
foreach (['/changelogs/customer_invoices', '/changelogs/customers'] as $p) {
    $r = $get($p, ['start_date' => $since, 'limit' => 5]);
    $check('GET '.$p.' (start_date RFC3339)', $r['status'] === 200 && isset($r['json']['items']), 'HTTP '.$r['status'].', '.count((array)($r['json']['items'] ?? [])).' changement(s) sur 7 jours');
}
$rl = array_filter($r['headers'] ?? [], static fn($k) => str_contains(strtolower((string)$k), 'ratelimit') || strtolower((string)$k) === 'retry-after', ARRAY_FILTER_USE_KEY);
echo '  En-têtes de limite de requêtes : '.($rl ? json_encode($rl) : 'aucun').PHP_EOL;

echo PHP_EOL.$ok.' vérification(s) réussie(s), '.$ko.' échec(s).'.PHP_EOL;
exit($ko ? 1 : 0);
