<?php
declare(strict_types=1);
require_once __DIR__ . '/helpers.php';
if (!app_installed() && basename($_SERVER['PHP_SELF'] ?? '') !== 'install.php') { redirect_to('install.php'); }
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
boot_session();
// Auto-migration v15.1 — address & postal_code on quotes
$_mf = __DIR__.'/../storage/.mig_v15_addr';
if (!file_exists($_mf)) {
    try { db_execute("ALTER TABLE quotes ADD COLUMN address VARCHAR(255) NULL AFTER city"); } catch (Throwable $_me) {}
    try { db_execute("ALTER TABLE quotes ADD COLUMN postal_code VARCHAR(10) NULL AFTER address"); } catch (Throwable $_me) {}
    @file_put_contents($_mf, date('c'));
    unset($_me);
}
unset($_mf);
