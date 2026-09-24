<?php
declare(strict_types=1);
// Abonnement du téléphone du technicien aux notifications push.
require_once __DIR__.'/../includes/bootstrap.php';
$tech = require_tech_auth();
header('Content-Type: application/json; charset=utf-8');

$action = (string)($_GET['action'] ?? $_POST['action'] ?? '');
if ($action === 'key') {
    echo json_encode(['key' => push_vapid_public_key()]);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['error' => 'POST requis']); exit; }
verify_csrf();
$sub = json_decode((string)($_POST['subscription'] ?? ''), true);
if ($action === 'subscribe') {
    $ok = is_array($sub) && push_subscribe('tech', (int)$tech['id'], $sub);
    echo json_encode(['ok' => $ok]);
} elseif ($action === 'unsubscribe') {
    if (is_array($sub) && !empty($sub['endpoint'])) push_unsubscribe((string)$sub['endpoint']);
    echo json_encode(['ok' => true]);
} elseif ($action === 'test') {
    $n = push_send_to('tech', (int)$tech['id'], 'Notifications activées', 'Vous serez prévenu des nouvelles interventions et des rappels.', url_for('tech/dashboard.php'), 'test');
    echo json_encode(['ok' => $n > 0, 'sent' => $n]);
} else {
    http_response_code(400); echo json_encode(['error' => 'Action inconnue']);
}
