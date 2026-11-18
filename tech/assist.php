<?php
declare(strict_types=1);
/**
 * Aide au technicien — côté téléphone (JSON) :
 *   POST action=ask   (csrf, iv, question) → enregistre la question ;
 *   GET  ?iv=ID       → questions et réponses de l'intervention ; déclenche le relais si le PC
 *                       n'a pas répondu à temps (includes/assist.php).
 * Le technicien principal et les renforts de l'intervention y ont accès.
 */
require_once __DIR__.'/../includes/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
boot_session();
if (empty($_SESSION['tech_id'])) tech_remember_login();
$tech = !empty($_SESSION['tech_id']) ? db_fetch("SELECT * FROM technicians WHERE id = ? AND status = 'actif'", [(int)$_SESSION['tech_id']]) : null;
if (!$tech) { http_response_code(401); exit('{"error":"connexion"}'); }
$techId = (int)$tech['id'];
$ivId = (int)($_REQUEST['iv'] ?? 0);
$iv = $ivId > 0 ? db_fetch('SELECT id, technician_id FROM interventions WHERE id = ?', [$ivId]) : null;
if (!$iv || ((int)$iv['technician_id'] !== $techId && !team_member($ivId, $techId))) { http_response_code(404); exit('{"error":"intervention"}'); }

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    verify_csrf();
    $q = trim((string)($_POST['question'] ?? ''));
    if ($q === '') { http_response_code(400); exit('{"error":"question vide"}'); }
    assist_ask($ivId, $techId, $q);
}

try { assist_process_overdue($ivId); } catch (Throwable $e) { integration_log('assist', 'relais #'.$ivId.' : '.$e->getMessage()); }
$items = array_map(static fn($r) => [
    'id' => (int)$r['id'], 'question' => (string)$r['question'], 'status' => (string)$r['status'],
    'status_label' => assist_status_label((string)$r['status']), 'answer' => (string)($r['answer'] ?? ''),
    'by' => assist_author_label($r['answered_by'] ?? null), 'mine' => (int)$r['technician_id'] === $techId,
    'at' => date('H:i', strtotime((string)$r['created_at'])),
], assist_for_intervention($ivId));
$last = assist_last_poll();
echo json_encode(['items' => $items, 'pc_online' => $last !== null && time() - $last < 120], JSON_UNESCAPED_UNICODE);
