<?php
declare(strict_types=1);
require_once __DIR__.'/../includes/bootstrap.php';
boot_session();
unset($_SESSION['disp_id'], $_SESSION['disp_name']);
header('Location: '.url_for('dispatcher/login.php'));
exit;
