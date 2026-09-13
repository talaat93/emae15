<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/render.php';
require_admin();

$back = $_SERVER['HTTP_REFERER'] ?? url_for('admin/index.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: '.$back); exit; }
verify_csrf();

if (zone_ctx_id() <= 0) {
    flash('error', 'Sélectionnez d\'abord une zone.');
    header('Location: '.$back);
    exit;
}

switch ($_POST['action'] ?? '') {
    case 'copy':
        $n = zone_copy_from_global();
        flash('success', $n.' champ'.($n > 1 ? 's' : '').' copié'.($n > 1 ? 's' : '').' depuis le site global vers '.zone_ctx_name().'.');
        break;
    case 'reset':
        $n = zone_reset_overrides();
        flash('success', $n.' personnalisation'.($n > 1 ? 's' : '').' supprimée'.($n > 1 ? 's' : '').'. '.zone_ctx_name().' hérite de nouveau entièrement du site global.');
        break;
}

header('Location: '.$back);
exit;
