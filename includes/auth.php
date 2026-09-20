<?php
declare(strict_types=1);

function current_admin(): ?array
{
    boot_session();
    if (empty($_SESSION['admin_id'])) {
        return null;
    }
    return db_fetch('SELECT * FROM admins WHERE id = ?', [(int) $_SESSION['admin_id']]);
}

function admin_logged_in(): bool
{
    return current_admin() !== null;
}

function require_admin(): void
{
    if (!admin_logged_in()) {
        flash('error', "Connectez-vous pour accéder à l'administration.");
        redirect_to('admin/login.php');
    }
    // Un compte standard n'ouvre que les écrans que le compte principal lui a ouverts.
    if (function_exists('require_admin_access')) require_admin_access();
}

function attempt_login(string $email, string $password): bool
{
    if (login_is_locked('admin', $email)) {
        return false;
    }
    $admin = db_fetch('SELECT * FROM admins WHERE email = ?', [$email]);
    // Un compte désactivé se comporte comme un identifiant inconnu : on ne
    // révèle pas qu'il existe, et la tentative compte pour le blocage.
    $actif = $admin && (!array_key_exists('status', $admin) || (int)$admin['status'] === 1);
    $ok = $actif && password_verify($password, $admin['password_hash']);
    login_record_attempt('admin', $email, $ok);
    if (!$ok) {
        return false;
    }
    boot_session();
    session_regenerate_id(true);
    $_SESSION['admin_id'] = (int) $admin['id'];
    if (function_exists('admin_log_login')) admin_log_login($admin);
    return true;
}

function logout_admin(): void
{
    boot_session();
    unset($_SESSION['admin_id']);
}
