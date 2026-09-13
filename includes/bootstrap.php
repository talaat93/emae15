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
// Auto-migration v15.5 — table dispatchers
$_mf5 = __DIR__.'/../storage/.mig_v15_disp';
if (!file_exists($_mf5)) {
    try {
        db_execute("CREATE TABLE IF NOT EXISTS dispatchers (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(120) NOT NULL,
            email VARCHAR(190) NOT NULL UNIQUE,
            phone VARCHAR(80) NULL,
            password_hash VARCHAR(255) NOT NULL,
            status VARCHAR(40) NOT NULL DEFAULT 'actif',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (Throwable $_me) {}
    @file_put_contents($_mf5, date('c'));
    unset($_me);
}
unset($_mf5);
// Auto-migration v15.6 — table clients
$_mf6 = __DIR__.'/../storage/.mig_v15_clients';
if (!file_exists($_mf6)) {
    try {
        db_execute("CREATE TABLE IF NOT EXISTS clients (
            id INT AUTO_INCREMENT PRIMARY KEY,
            lastname VARCHAR(120) NOT NULL,
            firstname VARCHAR(120) NULL,
            phone VARCHAR(80) NOT NULL,
            email VARCHAR(190) NULL,
            address VARCHAR(255) NULL,
            postal_code VARCHAR(10) NULL,
            city VARCHAR(190) NULL,
            floor VARCHAR(40) NULL,
            digicode VARCHAR(60) NULL,
            access_info TEXT NULL,
            notes TEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (Throwable $_me) {}
    @file_put_contents($_mf6, date('c'));
    unset($_me);
}
unset($_mf6);
// Auto-migration v15.7 — table interventions (système dispatcher)
$_mf7 = __DIR__.'/../storage/.mig_v15_interv';
if (!file_exists($_mf7)) {
    try {
        db_execute("CREATE TABLE IF NOT EXISTS interventions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            ref VARCHAR(20) NULL,
            client_id INT NOT NULL,
            dispatcher_id INT NULL,
            technician_id INT NULL,
            scheduled_date DATE NULL,
            scheduled_time TIME NULL,
            duration_estimate INT NULL DEFAULT 60,
            urgency TINYINT(1) NOT NULL DEFAULT 0,
            priority VARCHAR(40) NOT NULL DEFAULT 'normale',
            category VARCHAR(60) NULL,
            type_label VARCHAR(120) NULL,
            installation_type VARCHAR(120) NULL,
            description TEXT NULL,
            fault_reported TEXT NULL,
            materials_needed TEXT NULL,
            notes_admin TEXT NULL,
            quote_accepted TINYINT(1) NOT NULL DEFAULT 0,
            amount_ht DECIMAL(10,2) NULL,
            amount_ttc DECIMAL(10,2) NULL,
            deposit DECIMAL(10,2) NULL,
            remaining DECIMAL(10,2) NULL,
            payment_method VARCHAR(60) NULL,
            status VARCHAR(60) NOT NULL DEFAULT 'nouveau',
            tech_report TEXT NULL,
            tech_photos TEXT NULL,
            tech_materials_used TEXT NULL,
            tech_time_spent INT NULL,
            tech_signature TEXT NULL,
            tech_client_name VARCHAR(120) NULL,
            tech_started_at DATETIME NULL,
            tech_arrived_at DATETIME NULL,
            tech_completed_at DATETIME NULL,
            latitude DECIMAL(10,6) NULL,
            longitude DECIMAL(10,6) NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (Throwable $_me) {}
    @file_put_contents($_mf7, date('c'));
    unset($_me);
}
unset($_mf7);
// Auto-migration v15.8 — table intervention_history
$_mf8 = __DIR__.'/../storage/.mig_v15_hist';
if (!file_exists($_mf8)) {
    try {
        db_execute("CREATE TABLE IF NOT EXISTS intervention_history (
            id INT AUTO_INCREMENT PRIMARY KEY,
            intervention_id INT NOT NULL,
            status_from VARCHAR(60) NULL,
            status_to VARCHAR(60) NOT NULL,
            actor_type VARCHAR(20) NOT NULL DEFAULT 'system',
            actor_id INT NULL,
            actor_name VARCHAR(120) NULL,
            note TEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (Throwable $_me) {}
    @file_put_contents($_mf8, date('c'));
    unset($_me);
}
unset($_mf8);
// Auto-migration v15.12 — table zones (système multi-zones)
$_mf12 = __DIR__.'/../storage/.mig_v15_zones';
if (!file_exists($_mf12)) {
    try {
        db_execute("CREATE TABLE IF NOT EXISTS zones (
            id INT AUTO_INCREMENT PRIMARY KEY,
            slug VARCHAR(80) NOT NULL UNIQUE,
            name VARCHAR(120) NOT NULL,
            status TINYINT(1) NOT NULL DEFAULT 1,
            meta_title VARCHAR(160) NULL,
            meta_description VARCHAR(320) NULL,
            hero_h1 VARCHAR(255) NULL,
            hero_subtitle TEXT NULL,
            hero_cta_label VARCHAR(80) NULL,
            cities TEXT NULL,
            postal_codes TEXT NULL,
            faq JSON NULL,
            mentions_legales TEXT NULL,
            sort_order INT NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $__zd = [
            ['paris-ile-de-france','Paris / Île-de-France','Paris (75)|Meaux (77)|Versailles (78)|Évry (91)|Nanterre (92)|Saint-Denis (93)|Créteil (94)|Cergy (95)','75,77,78,91,92,93,94,95',0],
            ['cote-dor','Côte-d\'Or','Dijon|Beaune|Auxonne|Montbard|Nuits-Saint-Georges','21',1],
            ['jura','Jura','Lons-le-Saunier|Dole|Saint-Claude|Morez|Champagnole','39',2],
            ['doubs','Doubs','Besançon|Pontarlier|Montbéliard|Morteau|Baume-les-Dames','25',3],
            ['seine-saint-denis','Seine-Saint-Denis','Saint-Denis|Bobigny|Montreuil|Aubervilliers|Pantin|Noisy-le-Grand','93',4],
        ];
        foreach ($__zd as [$__sl,$__nm,$__ci,$__pc,$__so]) {
            try { db_execute("INSERT IGNORE INTO zones (slug,name,status,cities,postal_codes,sort_order) VALUES (?,?,1,?,?,?)",[$__sl,$__nm,$__ci,$__pc,$__so]); } catch (Throwable $_me) {}
        }
        unset($__zd,$__sl,$__nm,$__ci,$__pc,$__so);
    } catch (Throwable $_me) {}
    @file_put_contents($_mf12, date('c'));
    unset($_me);
}
unset($_mf12);
// Auto-migration v15.13 — rattachement des contenus à une zone (NULL = toutes les zones)
$_mf13 = __DIR__.'/../storage/.mig_v15_zone_content';
if (!file_exists($_mf13)) {
    foreach ([
        "ALTER TABLE pages ADD COLUMN zone_id INT NULL",
        "ALTER TABLE realisations ADD COLUMN zone_id INT NULL",
        "ALTER TABLE reviews ADD COLUMN zone_id INT NULL",
    ] as $__sql) { try { db_execute($__sql); } catch (Throwable $_me) {} }
    @file_put_contents($_mf13, date('c'));
    unset($_me, $__sql);
}
unset($_mf13);
// Contexte zone de l'admin — défini avant toute logique de page (traitements POST inclus)
if (str_contains(str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? '')), '/admin/')) {
    boot_session();
    if (isset($_GET['admin_zone'])) $_SESSION['admin_zone_id'] = max(0, (int)$_GET['admin_zone']);
    $_azid = (int)($_SESSION['admin_zone_id'] ?? 0);
    if ($_azid > 0) {
        $_az = get_zone_by_id($_azid);
        if ($_az) set_zone_context($_az); else $_SESSION['admin_zone_id'] = 0;
        unset($_az);
    }
    unset($_azid);
}