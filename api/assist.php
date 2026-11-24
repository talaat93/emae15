<?php
declare(strict_types=1);
/**
 * Aide au technicien — point d'accès du PC du propriétaire (Claude Code local).
 *
 * Le PC appelle cette adresse toutes les 20 à 30 secondes avec la clé secrète générée dans
 * Réglages → Assistant (en-tête « Authorization: Bearer <clé> ») :
 *   GET  ?action=next    → 204 s'il n'y a rien ; sinon le prochain travail : d'abord les questions
 *                          des techniciens (« aide-12 »), puis la correction et la relecture des
 *                          rapports (« travail-7 », includes/pc_jobs.php).
 *                          format=plain : 1re ligne = identifiant, puis le texte à donner à Claude.
 *                          (par défaut : JSON {id, prompt})
 *   POST ?action=answer  → champs id et answer : enregistre la réponse (409 si déjà traité).
 *                          Un identifiant fait seulement de chiffres est une question (anciens scripts).
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
if (($_GET['v'] ?? '') === '2') pc_jobs_touch_poll();   // script à jour : il sait faire la correction et la relecture
$action = (string)($_GET['action'] ?? '');

if ($action === 'next' && ($_SERVER['REQUEST_METHOD'] ?? '') === 'GET') {
    $id = null; $prompt = '';
    if ($r = assist_claim_next()) { $id = 'aide-'.(int)$r['id']; $prompt = assist_prompt_for($r); }
    // Travaux seulement pour un script à jour (v=2) : un ancien script prendrait « travail-7 » pour la question n° 7.
    elseif (($_GET['v'] ?? '') === '2' && pc_jobs_setting() && ($j = pc_job_claim_next())) { $id = 'travail-'.(int)$j['id']; $prompt = (string)$j['prompt']; }
    if ($id === null) { http_response_code(204); exit; }
    if (($_GET['format'] ?? '') === 'plain') {
        header('Content-Type: text/plain; charset=utf-8');
        echo $id, "\n", $prompt;
    } else {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['id' => $id, 'prompt' => $prompt], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

if ($action === 'answer' && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    $rawId = trim((string)($_POST['id'] ?? ''));
    $answer = (string)($_POST['answer'] ?? '');
    if (!preg_match('/^(?:(aide|travail)-)?([1-9][0-9]{0,9})$/', $rawId, $m) || trim($answer) === '') { http_response_code(400); exit('{"error":"id ou reponse manquant"}'); }
    $num = (int)$m[2];
    if (($m[1] ?: 'aide') === 'aide') {
        if (!assist_answer($num, $answer, 'claude_local')) { http_response_code(409); exit('{"error":"deja repondu ou introuvable"}'); }
        integration_log('assist', 'réponse du PC enregistrée', ['demande' => $num]);
        exit('{"ok":true}');
    }
    @set_time_limit(180);
    $res = pc_job_finish($num, $answer);
    if ($res === 'deja') { http_response_code(409); exit('{"error":"deja traite ou introuvable"}'); }
    if ($res === 'invalide') { http_response_code(422); exit('{"error":"reponse illisible, relais par l API"}'); }
    integration_log('assist', 'travail du PC enregistré', ['travail' => $num]);
    exit('{"ok":true}');
}

http_response_code(400);
header('Content-Type: application/json; charset=utf-8');
echo '{"error":"action"}';
