<?php
declare(strict_types=1);
/**
 * Connexion OAuth du compte Google d'EMAE : départ vers Google (?start=1), puis retour
 * avec le code à échanger. Le jeton obtenu est enregistré comme secret (jamais affiché).
 */
require_once __DIR__.'/../includes/bootstrap.php';
boot_session();
if (!admin_logged_in() || !admin_can_access('google.php')) {
    flash('error', "Connectez-vous pour accéder à l'administration.");
    redirect_to('admin/login.php');
}
if (!google_oauth_configured()) {
    flash('error', 'Enregistrez d\'abord l\'ID client et le code secret (étape 1).');
    redirect_to('admin/google.php');
}

if (isset($_GET['start'])) {
    $_SESSION['google_oauth_state'] = bin2hex(random_bytes(16));
    header('Location: '.google_oauth_auth_url($_SESSION['google_oauth_state']));
    exit;
}

$state = (string)($_GET['state'] ?? '');
$attendu = (string)($_SESSION['google_oauth_state'] ?? '');
unset($_SESSION['google_oauth_state']);
if ($attendu === '' || !hash_equals($attendu, $state)) {
    flash('error', 'Connexion Google interrompue (lien expiré). Recommencez depuis le bouton « Connecter ».');
    redirect_to('admin/google.php');
}
if (isset($_GET['error'])) {
    flash('error', 'Google a refusé la connexion : '.(string)$_GET['error']);
    redirect_to('admin/google.php');
}
$r = google_oauth_exchange((string)($_GET['code'] ?? ''));
flash($r['ok'] ? 'success' : 'error', $r['ok'] ? 'Compte Google connecté.' : 'Connexion Google impossible : '.$r['error']);
redirect_to('admin/google.php');
