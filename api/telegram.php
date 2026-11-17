<?php
declare(strict_types=1);
/**
 * Webhook du bot Telegram de l'agent relation client.
 * Telegram appelle cette adresse à chaque message ou appui sur un bouton.
 * Seules les requêtes portant le secret convenu (en-tête X-Telegram-Bot-Api-Secret-Token)
 * sont traitées ; seul le téléphone relié dans l'admin peut agir.
 */
require_once __DIR__ . '/../includes/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo '{"ok":false}'; exit; }

$secret = integration_secret('telegram_webhook_secret');
$given  = (string)($_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? '');
if ($secret === '' || !hash_equals($secret, $given)) { http_response_code(403); echo '{"ok":false}'; exit; }

$update = json_decode((string)file_get_contents('php://input'), true);
// Réponse immédiate à Telegram, puis traitement (un envoi de mail ou une réécriture peut prendre quelques secondes).
echo '{"ok":true}';
if (function_exists('fastcgi_finish_request')) @fastcgi_finish_request();
elseif (function_exists('litespeed_finish_request')) @litespeed_finish_request();
@ignore_user_abort(true);
@set_time_limit(90);

if (is_array($update)) {
    try { agent_tg_handle($update); }
    catch (Throwable $e) { error_log('[EMAE telegram] '.$e->getMessage()); }
}
