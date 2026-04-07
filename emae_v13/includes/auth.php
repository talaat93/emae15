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
        flash('error', 'Connectez-vous pour accéder à l’administration.');
        redirect_to('admin/login.php');
    }
}

function attempt_login(string $email, string $password): bool
{
    $admin = db_fetch('SELECT * FROM admins WHERE email = ?', [$email]);
    if (!$admin) {
        return false;
    }
    if (!password_verify($password, $admin['password_hash'])) {
        return false;
    }
    boot_session();
    $_SESSION['admin_id'] = (int) $admin['id'];
    return true;
}

function logout_admin(): void
{
    boot_session();
    unset($_SESSION['admin_id']);
}
