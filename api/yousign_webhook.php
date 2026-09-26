<?php
declare(strict_types=1);
/**
 * Webhook Yousign (à déclarer dans Yousign → Paramètres → Webhooks, avec l'URL
 * https://emaee.fr/api/yousign_webhook.php et le secret copié dans les réglages EMAE).
 * - la signature HMAC du corps brut est obligatoire (sinon 401) ;
 * - le contenu reçu sert seulement à identifier le devis : l'état est relu auprès de Yousign.
 */
require_once __DIR__.'/../includes/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { http_response_code(405); exit('{"error":"method"}'); }

$raw = (string)file_get_contents('php://input');
$sig = (string)($_SERVER['HTTP_X_YOUSIGN_SIGNATURE_256'] ?? '');
if (strlen($raw) > 1_000_000 || !yousign_webhook_verify($raw, $sig)) {
    integration_log('yousign', 'webhook refusé : signature invalide ou absente');
    http_response_code(401);
    exit('{"error":"signature"}');
}

$p = json_decode($raw, true) ?: [];
$event = (string)($p['event_name'] ?? '');
$srId = (string)($p['data']['signature_request']['id'] ?? '');
$ext = (string)($p['data']['signature_request']['external_id'] ?? '');
devis_table();
$d = null;
if ($srId !== '') $d = db_fetch('SELECT id FROM devis WHERE yousign_request_id = ?', [$srId]);
if (!$d && preg_match('/^EMAE-DEVIS-(\d+)$/', $ext, $m)) $d = db_fetch('SELECT id FROM devis WHERE id = ?', [(int)$m[1]]);
integration_log('yousign', 'webhook reçu', ['evenement' => mb_substr($event, 0, 80), 'devis' => $d['id'] ?? null]);

if ($d) {
    $r = devis_refresh((int)$d['id']);
    if (!$r['ok']) { http_response_code(503); exit('{"error":"refresh"}'); }   // Yousign réessaiera plus tard
}
http_response_code(200);
echo '{"ok":true}';
