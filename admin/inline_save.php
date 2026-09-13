<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/admin_fields.php';

header('Content-Type: application/json; charset=utf-8');

function ie_fail(string $msg, int $code = 400): never
{
    http_response_code($code);
    echo json_encode(['ok' => false, 'error' => $msg], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!admin_logged_in())                          ie_fail('Session expirée, reconnectez-vous.', 403);
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') ie_fail('Méthode non autorisée.', 405);

$body = json_decode((string)file_get_contents('php://input'), true);
if (!is_array($body)) ie_fail('Requête illisible.');

if (!hash_equals((string)($_SESSION['csrf_token'] ?? ''), (string)($body['csrf_token'] ?? ''))) {
    ie_fail('Jeton de sécurité invalide, rechargez la page.', 419);
}

// La zone provient de la page éditée, pas de la sélection en session :
// on enregistre là où l'administrateur voit le texte.
$slug = trim((string)($body['zone'] ?? ''));
if ($slug !== '') {
    $zone = get_zone_by_slug($slug);
    if (!$zone) ie_fail('Zone inconnue.');
    set_zone_context($zone);
} else {
    set_zone_context(null);
}

$fields = $body['fields'] ?? null;
if (!is_array($fields) || $fields === []) ie_fail('Aucune modification reçue.');
if (count($fields) > 200)                 ie_fail('Trop de modifications en une fois.');

$known = admin_inline_keys();
$saved = 0;
$unknown = [];

foreach ($fields as $key => $value) {
    if (!is_string($key) || !is_string($value)) continue;
    if (!isset($known[$key])) { $unknown[] = $key; continue; }
    if (admin_field_save($known[$key], $value)) $saved++;
}

if ($unknown !== []) ie_fail('Champ non reconnu : '.implode(', ', array_slice($unknown, 0, 3)));

echo json_encode(['ok' => true, 'saved' => $saved], JSON_UNESCAPED_UNICODE);
