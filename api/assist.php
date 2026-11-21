<?php
declare(strict_types=1);
/**
 * Aide au technicien — point d'accès du PC du propriétaire (Claude Code local).
 *
 * Le PC appelle cette adresse toutes les 20 à 30 secondes avec la clé secrète générée dans
 * Réglages → Assistant (en-tête « Authorization: Bearer <clé> ») :
 *   GET  ?action=next    → 204 s'il n'y a rien ; sinon la prochaine question à traiter.
 *                          format=plain : 1re ligne = numéro, puis le texte à donner à Claude.
 *                          (par défaut : JSON {id, prompt})
 *   POST ?action=answer  → champs id et answer : enregistre la réponse (409 si déjà répondu).
 * Clé absente ou fausse : 401, sans autre détail. Aucune donnée personnelle du client n'est
 * transmise (ni nom, ni téléphone, ni adresse).
 */
require_once __DIR__.'/../includes/bootstrap.php';

header('Cache-Control: no-store');
$auth = (string)($_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
$token = preg_match('/^Bearer\s+(\S+)$/i', $auth, $m) ? $m[1] : '';
if (!assist_token_ok($token)) {
    usleep(300000);   // ralentit les essais au hasard
    http_response_code(401);
    header('Content-Type: application/json; charset=utf-8');
    exit('{"error":"cle"}');
}
assist_touch_poll();
$action = (string)($_GET['action'] ?? '');

if ($action === 'next' && ($_SERVER['REQUEST_METHOD'] ?? '') === 'GET') {
    $r = assist_claim_next();
    if (!$r) { http_response_code(204); exit; }
    $prompt = assist_prompt_for($r);
    if (($_GET['format'] ?? '') === 'plain') {
        header('Content-Type: text/plain; charset=utf-8');
        echo (int)$r['id'], "\n", $prompt;
    } else {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['id' => (int)$r['id'], 'prompt' => $prompt], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

if ($action === 'answer' && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    $id = (int)($_POST['id'] ?? 0);
    $answer = (string)($_POST['answer'] ?? '');
    if ($id <= 0 || trim($answer) === '') { http_response_code(400); exit('{"error":"id ou reponse manquant"}'); }
    if (!assist_answer($id, $answer, 'claude_local')) { http_response_code(409); exit('{"error":"deja repondu ou introuvable"}'); }
    integration_log('assist', 'réponse du PC enregistrée', ['demande' => $id]);
    exit('{"ok":true}');
}

http_response_code(400);
header('Content-Type: application/json; charset=utf-8');
echo '{"error":"action"}';
