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
// Auto-migration v15.2 — champs intervention & archivage sur quotes
$_mf2 = __DIR__.'/../storage/.mig_v15_intervention';
if (!file_exists($_mf2)) {
    $__sqls = [
        "ALTER TABLE quotes ADD COLUMN intervention_date DATETIME NULL",
        "ALTER TABLE quotes ADD COLUMN technician VARCHAR(120) NULL",
        "ALTER TABLE quotes ADD COLUMN duration VARCHAR(60) NULL",
        "ALTER TABLE quotes ADD COLUMN amount_ht DECIMAL(10,2) NULL",
        "ALTER TABLE quotes ADD COLUMN solution TEXT NULL",
        "ALTER TABLE quotes ADD COLUMN materials TEXT NULL",
        "ALTER TABLE quotes ADD COLUMN notes_admin TEXT NULL",
        "ALTER TABLE quotes ADD COLUMN archived TINYINT(1) NOT NULL DEFAULT 0",
    ];
    foreach ($__sqls as $__sql) { try { db_execute($__sql); } catch (Throwable $_me) {} }
    @file_put_contents($_mf2, date('c'));
    unset($_me, $__sqls, $__sql);
}
unset($_mf2);
// Auto-migration v15.3 — table techniciens + colonnes suivi
$_mf3 = __DIR__.'/../storage/.mig_v15_tech';
if (!file_exists($_mf3)) {
    try {
        db_execute("CREATE TABLE IF NOT EXISTS technicians (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(120) NOT NULL,
            email VARCHAR(190) NOT NULL UNIQUE,
            phone VARCHAR(80) NULL,
            password_hash VARCHAR(255) NOT NULL,
            status VARCHAR(40) NOT NULL DEFAULT 'actif',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (Throwable $_me) {}
    foreach ([
        "ALTER TABLE quotes ADD COLUMN technician_id INT NULL",
        "ALTER TABLE quotes ADD COLUMN tech_report TEXT NULL",
        "ALTER TABLE quotes ADD COLUMN tech_photos TEXT NULL",
        "ALTER TABLE quotes ADD COLUMN tech_completed_at DATETIME NULL",
    ] as $__sql) { try { db_execute($__sql); } catch (Throwable $_me) {} }
    @file_put_contents($_mf3, date('c'));
    unset($_me, $__sql);
}
unset($_mf3);
// Auto-migration v15.4 — checklist technicien (réalisable, mauvaise utilisation)
$_mf4 = __DIR__.'/../storage/.mig_v15_checklist';
if (!file_exists($_mf4)) {
    foreach ([
        "ALTER TABLE quotes ADD COLUMN tech_realizable TINYINT(1) NULL",
        "ALTER TABLE quotes ADD COLUMN tech_bad_use TINYINT(1) NULL",
    ] as $__sql) { try { db_execute($__sql); } catch (Throwable $_me) {} }
    @file_put_contents($_mf4, date('c'));
    unset($_me, $__sql);
}
unset($_mf4);