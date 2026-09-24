<?php
declare(strict_types=1);
require_once __DIR__.'/../includes/bootstrap.php';
$tech = require_tech_auth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_to('tech/dashboard.php');
}

verify_csrf();

$action = (string)($_POST['action'] ?? '');
$taskId = (int)($_POST['task_id'] ?? 0);

if ($action === 'complete' && $taskId > 0) {
    // Un technicien ne clôt que ses rappels ou les rappels adressés à tous.
    try { $task = db_fetch('SELECT technician_id FROM tasks WHERE id = ?', [$taskId]); } catch (Throwable $e) { $task = null; }
    if (!$task || (!empty($task['technician_id']) && (int)$task['technician_id'] !== (int)$tech['id'])) {
        redirect_to('tech/dashboard.php');
    }
    update_task($taskId, ['status' => 'done']);
    flash('success', 'Tâche marquée comme terminée.');
}

redirect_to('tech/dashboard.php');
