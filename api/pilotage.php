<?php
declare(strict_types=1);
/**
 * Porte d'échange avec l'outil « Claude EMAE Pilotage » du PC.
 *
 *  - POST : envoi des chiffres Google Ads, corps JSON {"rows": [{"date", "campaign", "cost",
 *    "clicks", "impressions", "conversions", "metier"?, "dept"?}, …]} ;
 *    Un rapport exporté de Google Ads peut aussi être envoyé tel quel (Content-Type: text/csv) ;
 *  - GET  : résumé de la page Pilotage (?jours=30), pour que Claude croise site, Google Ads
 *    et factures sans accès direct à la base.
 *
 * Accès : en-tête « Authorization: Bearer <jeton> ». Le jeton est choisi sur le PC puis
 * collé dans Dispatcher → Réglages → Pilotage ; il n'est jamais affiché par le site.
 * Sans jeton configuré, la porte est fermée.
 */
require_once __DIR__.'/../includes/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$attendu = integration_secret('pilotage_token');
$recu = '';
$auth = (string)($_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
if (preg_match('/^Bearer\s+(\S+)$/i', $auth, $m)) $recu = $m[1];
if ($recu === '') $recu = (string)($_SERVER['HTTP_X_PILOTAGE_TOKEN'] ?? '');   // certains hébergeurs retirent Authorization
if ($attendu === '' || strlen($attendu) < 24 || $recu === '' || !hash_equals($attendu, $recu)) {
    integration_log('pilotage', 'accès refusé');
    http_response_code(401);
    exit('{"error":"jeton"}');
}

$methode = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($methode === 'GET') {
    $jours = (int)($_GET['jours'] ?? 30);
    echo json_encode(pilotage_summary($jours, date('Y-m-d')), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}
if ($methode !== 'POST') { http_response_code(405); exit('{"error":"methode"}'); }

$raw = (string)file_get_contents('php://input');
if (strlen($raw) > 2_000_000) { http_response_code(413); exit('{"error":"trop volumineux"}'); }
// Rapport Google Ads envoyé tel quel (CSV) : même lecture que le formulaire de la page Pilotage.
$type = strtolower((string)($_SERVER['CONTENT_TYPE'] ?? ''));
if (str_contains($type, 'csv') || str_contains($type, 'text/plain')) {
    $csv = pilotage_ads_parse_csv($raw);
    if ($csv['error']) { http_response_code(400); exit(json_encode(['error' => $csv['error']], JSON_UNESCAPED_UNICODE)); }
    $defaut = $csv['has_date'] ? '' : pilotage_date((string)($_GET['date'] ?? date('Y-m-d')));
    $p = ['rows' => array_map(static fn($r) => $r + ['date' => $defaut], $csv['rows'])];
} else {
    $p = json_decode($raw, true);
}
if (!is_array($p) || !is_array($p['rows'] ?? null) || count($p['rows']) > 20000) {
    http_response_code(400);
    exit('{"error":"format : {\"rows\": [...]} attendu"}');
}
$n = pilotage_ads_normalize($p['rows']);
$ecrites = pilotage_ads_store($n['rows']);
integration_log('pilotage', 'chiffres Google Ads reçus', ['lignes' => $ecrites, 'erreurs' => count($n['errors'])]);
echo json_encode(['ok' => true, 'lignes' => $ecrites, 'erreurs' => array_slice($n['errors'], 0, 20)], JSON_UNESCAPED_UNICODE);
