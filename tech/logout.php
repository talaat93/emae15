<?php
declare(strict_types=1);
require_once __DIR__.'/../includes/bootstrap.php';
boot_session();
tech_remember_forget();   // déconnexion volontaire : ce téléphone ne reste plus connecté
unset($_SESSION['tech_id'], $_SESSION['tech_name']);
header('Location: '.url_for('tech/login.php')); exit;
