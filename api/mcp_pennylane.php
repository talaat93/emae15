<?php
declare(strict_types=1);
/**
 * Connecteur Claude « Pennylane » (serveur MCP, transport HTTP « streamable », réponses JSON).
 * Adresse à déclarer dans claude.ai → Paramètres → Connecteurs → Ajouter un connecteur personnalisé :
 *   https://www.<domaine>/api/mcp_pennylane.php?cle=<clé générée dans Réglages → Pennylane>
 * - sans clé configurée, le connecteur est désactivé (404) ; mauvaise clé : 401 ;
 * - la logique et la liste des outils sont dans includes/mcp_pennylane.php.
 */
require_once __DIR__.'/../includes/bootstrap.php';
require_once __DIR__.'/../includes/mcp_pennylane.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (mcp_pennylane_key() === '') { http_response_code(404); exit('{"error":"connecteur désactivé"}'); }

$auth = (string)($_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
$given = (string)($_GET['cle'] ?? (preg_match('/^Bearer\s+(\S+)$/i', $auth, $m) ? $m[1] : ''));
if (!mcp_pennylane_authorized($given)) {
    integration_log('mcp', 'accès refusé : clé absente ou invalide');
    http_response_code(401);
    exit('{"error":"clé invalide"}');
}

// Pas de flux SSE ni de session côté serveur : seules les requêtes POST sont acceptées.
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { header('Allow: POST'); http_response_code(405); exit('{"error":"method"}'); }

$raw = (string)file_get_contents('php://input');
if (strlen($raw) > 1_000_000) { http_response_code(413); exit('{"error":"trop volumineux"}'); }
$msg = json_decode($raw, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    exit(json_encode(['jsonrpc' => '2.0', 'id' => null, 'error' => ['code' => -32700, 'message' => 'JSON invalide.']]));
}

@set_time_limit(120);
$res = mcp_pennylane_handle($msg);
if ($res === null) { http_response_code(202); exit; }
echo json_encode($res, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
