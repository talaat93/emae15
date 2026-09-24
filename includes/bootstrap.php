<?php
declare(strict_types=1);
// Ne jamais exposer d'erreurs PHP (trace, chemins serveur) à un visiteur ;
// tout est tout de même journalisé côté serveur pour le débogage.
ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);
if (file_exists(__DIR__.'/../vendor/autoload.php')) {
    require_once __DIR__.'/../vendor/autoload.php';
}
require_once __DIR__ . '/helpers.php';
if (!app_installed() && basename($_SERVER['PHP_SELF'] ?? '') !== 'install.php') { redirect_to('install.php'); }
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/admin_team.php';
require_once __DIR__ . '/zone_vars.php';
require_once __DIR__ . '/zones_core.php';
require_once __DIR__ . '/notifications.php';
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
// Auto-migration v15.9 — champs compte rendu technicien (Praxedo-style)
$_mf9 = __DIR__.'/../storage/.mig_v15_cr';
if (!file_exists($_mf9)) {
    foreach ([
        "ALTER TABLE interventions ADD COLUMN tech_fault_label VARCHAR(255) NULL",
        "ALTER TABLE interventions ADD COLUMN tech_realizable TINYINT(1) NULL",
        "ALTER TABLE interventions ADD COLUMN tech_bad_use TINYINT(1) NULL",
        "ALTER TABLE interventions ADD COLUMN tech_device_number VARCHAR(120) NULL",
        "ALTER TABLE interventions ADD COLUMN tech_elevator_restored TINYINT(1) NULL",
        "ALTER TABLE interventions ADD COLUMN tech_ticket_time TIME NULL",
        "ALTER TABLE interventions ADD COLUMN tech_close_time TIME NULL",
        "ALTER TABLE interventions ADD COLUMN tech_notes_extra TEXT NULL",
    ] as $__sql) { try { db_execute($__sql); } catch (Throwable $_me) {} }
    @file_put_contents($_mf9, date('c'));
    unset($_me, $__sql);
}
unset($_mf9);
// Auto-migration v15.10 — table tasks (rappels/tâches dispatcher)
$_mf10 = __DIR__.'/../storage/.mig_v15_tasks';
if (!file_exists($_mf10)) {
    try {
        db_execute("CREATE TABLE IF NOT EXISTS tasks (
            id INT AUTO_INCREMENT PRIMARY KEY,
            dispatcher_id INT NULL,
            technician_id INT NULL,
            title VARCHAR(255) NOT NULL,
            description TEXT NULL,
            due_date DATE NULL,
            due_time TIME NULL,
            urgent TINYINT(1) NOT NULL DEFAULT 0,
            status VARCHAR(40) NOT NULL DEFAULT 'pending',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (Throwable $_me) {}
    @file_put_contents($_mf10, date('c'));
    unset($_me);
}
unset($_mf10);
// Auto-migration v15.11 — preset_items (types interventions, matériaux, photos)
$_mf11 = __DIR__.'/../storage/.mig_v15_presets';
if (!file_exists($_mf11)) {
    try {
        db_execute("CREATE TABLE IF NOT EXISTS preset_items (
            id INT AUTO_INCREMENT PRIMARY KEY,
            type VARCHAR(60) NOT NULL,
            category VARCHAR(60) NULL,
            label VARCHAR(255) NOT NULL,
            sort_order INT NOT NULL DEFAULT 0,
            active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_type_cat (type, category)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $seeds = [
            ['intervention_type','ascenseur','Dépannage urgent'],
            ['intervention_type','ascenseur','Maintenance préventive'],
            ['intervention_type','ascenseur','Remplacement pièces'],
            ['intervention_type','electricite','Remplacement disjoncteur'],
            ['intervention_type','electricite','Mise aux normes tableau'],
            ['intervention_type','electricite','Installation prise/éclairage'],
            ['intervention_type','plomberie','Fuite d\'eau'],
            ['intervention_type','plomberie','Débouchage canalisation'],
            ['intervention_type','chauffage','Entretien chaudière'],
            ['intervention_type','chauffage','Remplacement vanne'],
            ['material','','Câble électrique'],
            ['material','','Disjoncteur'],
            ['material','','Joint'],
            ['material','','Vanne'],
            ['material','','Courroi'],
            ['photo_type','','Photo plaque signalétique'],
            ['photo_type','','Photo avant intervention'],
            ['photo_type','','Photo après intervention'],
            ['photo_type','','Photo tableau électrique'],
            ['photo_type','','Photo pièce défectueuse'],
        ];
        foreach ($seeds as [$_st,$_sc,$_sl]) {
            try { db_execute("INSERT INTO preset_items (type,category,label) VALUES (?,?,?)", [$_st,$_sc,$_sl]); } catch (Throwable $_me) {}
        }
        unset($_st,$_sc,$_sl,$seeds);
    } catch (Throwable $_me) {}
    @file_put_contents($_mf11, date('c'));
    unset($_me);
}
unset($_mf11);
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
// Auto-migration v15.14 — table login_attempts (anti brute-force)
$_mf14 = __DIR__.'/../storage/.mig_v15_login_attempts';
if (!file_exists($_mf14)) {
    try {
        db_execute("CREATE TABLE IF NOT EXISTS login_attempts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            scope VARCHAR(20) NOT NULL,
            identifier VARCHAR(191) NOT NULL,
            ip VARCHAR(45) NOT NULL,
            success TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_scope_identifier (scope, identifier, created_at),
            INDEX idx_scope_ip (scope, ip, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (Throwable $_me) {}
    @file_put_contents($_mf14, date('c'));
    unset($_me);
}
unset($_mf14);
// Auto-migration v15.15 — comptes multiples, journal d'activité et statistiques
$_mf15 = __DIR__.'/../storage/.mig_v15_equipe_stats';
if (!file_exists($_mf15)) {
    foreach ([
        "ALTER TABLE admins ADD COLUMN role VARCHAR(20) NOT NULL DEFAULT 'admin'",
        "ALTER TABLE admins ADD COLUMN permissions TEXT NULL",
        "ALTER TABLE admins ADD COLUMN status TINYINT(1) NOT NULL DEFAULT 1",
        "ALTER TABLE admins ADD COLUMN last_login_at DATETIME NULL",
    ] as $__sql) { try { db_execute($__sql); } catch (Throwable $_me) {} }
    try {
        // Le plus ancien compte devient le compte principal.
        db_execute("UPDATE admins SET role = 'super' WHERE id = (SELECT * FROM (SELECT MIN(id) FROM admins) AS t)");
    } catch (Throwable $_me) {}
    try {
        db_execute("CREATE TABLE IF NOT EXISTS admin_activity (
            id INT AUTO_INCREMENT PRIMARY KEY,
            admin_id INT NULL,
            admin_name VARCHAR(120) NOT NULL,
            action VARCHAR(40) NOT NULL,
            resource VARCHAR(120) NULL,
            detail VARCHAR(255) NULL,
            ip VARCHAR(45) NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_date (created_at),
            INDEX idx_admin (admin_id, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (Throwable $_me) {}
    try {
        // Trafic agrégé : une ligne par jour et par page, jamais de donnée personnelle.
        db_execute("CREATE TABLE IF NOT EXISTS site_stats (
            id INT AUTO_INCREMENT PRIMARY KEY,
            stat_date DATE NOT NULL,
            zone_slug VARCHAR(80) NOT NULL DEFAULT '',
            page_key VARCHAR(120) NOT NULL DEFAULT '',
            views INT NOT NULL DEFAULT 0,
            UNIQUE KEY uniq_jour_page (stat_date, zone_slug, page_key),
            INDEX idx_date (stat_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (Throwable $_me) {}
    try {
        db_execute("CREATE TABLE IF NOT EXISTS site_events (
            id INT AUTO_INCREMENT PRIMARY KEY,
            stat_date DATE NOT NULL,
            zone_slug VARCHAR(80) NOT NULL DEFAULT '',
            event_key VARCHAR(60) NOT NULL,
            total INT NOT NULL DEFAULT 0,
            UNIQUE KEY uniq_jour_event (stat_date, zone_slug, event_key),
            INDEX idx_date (stat_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (Throwable $_me) {}
    @file_put_contents($_mf15, date('c'));
    unset($_me, $__sql);
}
unset($_mf15);
// Édition visuelle du site — réservée à un administrateur connecté, en consultation
// simple (jamais sur un envoi de formulaire, pour ne pas polluer les e-mails).
if (isset($_GET['admin_edit'])
    && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET'
    && !str_contains(str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? '')), '/admin/')
    && admin_logged_in()) {
    require_once __DIR__ . '/admin_fields.php';
    require_once __DIR__ . '/inline_edit.php';
    inline_edit_active(true);
    ob_start('inline_edit_postprocess');
}
// Auto-migration v15.16 — variables de lieu par zone
$_mf16 = __DIR__.'/../storage/.mig_v15_zone_vars';
if (!file_exists($_mf16)) {
    try {
        // Les anciens réglages de repli deviennent les valeurs globales.
        foreach ([
            'geo_default_region' => 'zvar_region',
            'geo_default_ville'  => 'zvar_ville',
            'geo_default_dept'   => 'zvar_departement',
        ] as $__ancien => $__nouveau) {
            $__v = db_fetch('SELECT setting_value FROM settings WHERE setting_key = ?', [$__ancien]);
            $__v = trim((string)($__v['setting_value'] ?? ''));
            if ($__v !== '') {
                db_execute('INSERT INTO settings (setting_key, setting_value) VALUES (?,?)
                            ON DUPLICATE KEY UPDATE setting_value = setting_value', [$__nouveau, $__v]);
            }
        }
        // Amorce chaque zone avec son nom : valeur modifiable, jamais imposée.
        foreach (db_fetch_all('SELECT slug, name, cities FROM zones') as $__z) {
            $__pre  = 'z:'.$__z['slug'].':zvar_';
            $__vill = trim(explode('|', (string)($__z['cities'] ?? ''))[0]);
            foreach (['region' => (string)$__z['name'], 'ville' => $__vill] as $__n => $__val) {
                if ($__val === '') continue;
                db_execute('INSERT INTO settings (setting_key, setting_value) VALUES (?,?)
                            ON DUPLICATE KEY UPDATE setting_value = setting_value', [$__pre.$__n, $__val]);
            }
        }
    } catch (Throwable $_me) {}
    @file_put_contents($_mf16, date('c'));
    unset($_me, $__ancien, $__nouveau, $__v, $__z, $__pre, $__vill, $__n, $__val);
}
unset($_mf16);
// Auto-migration v15.17 — la table zones devient la source unique des zones
// d'intervention. On lui ajoute les champs de présentation qui n'existaient
// que dans les réglages, puis on y rapatrie les anciennes saisies.
$_mf17 = __DIR__.'/../storage/.mig_v15_zones_unique';
if (!file_exists($_mf17)) {
    foreach ([
        "ALTER TABLE zones ADD COLUMN depts TEXT NULL",
        "ALTER TABLE zones ADD COLUMN `delay` VARCHAR(80) NULL",
        "ALTER TABLE zones ADD COLUMN color VARCHAR(16) NULL",
        "ALTER TABLE zones ADD COLUMN intro VARCHAR(255) NULL",
    ] as $__sql) { try { db_execute($__sql); } catch (Throwable $_me) {} }
    try {
        $__old = json_decode((string)(db_fetch('SELECT setting_value FROM settings WHERE setting_key = ?',
                    ['zones_page_settings'])['setting_value'] ?? ''), true);
        $__crd = json_decode((string)(db_fetch('SELECT setting_value FROM settings WHERE setting_key = ?',
                    ['home_zone_cards'])['setting_value'] ?? ''), true);
        zones_import_legacy(
            is_array($__old['regions'] ?? null) ? $__old['regions'] : [],
            is_array($__crd) ? $__crd : []
        );
        intervention_zones_flush();
    } catch (Throwable $_me) {}
    @file_put_contents($_mf17, date('c'));
    unset($_me, $__sql, $__old, $__crd);
}
unset($_mf17);
// Auto-migration v15.18 — colonnes du rapport technicien. Rejoue la v15.9
// (perdue un temps lors d'un import) : sans elles, le rapport ne s'enregistre pas.
$_mf18 = __DIR__.'/../storage/.mig_v15_rapport_tech';
if (!file_exists($_mf18)) {
    foreach ([
        "ALTER TABLE interventions ADD COLUMN tech_fault_label VARCHAR(255) NULL",
        "ALTER TABLE interventions ADD COLUMN tech_realizable TINYINT(1) NULL",
        "ALTER TABLE interventions ADD COLUMN tech_bad_use TINYINT(1) NULL",
        "ALTER TABLE interventions ADD COLUMN tech_device_number VARCHAR(120) NULL",
        "ALTER TABLE interventions ADD COLUMN tech_elevator_restored TINYINT(1) NULL",
        "ALTER TABLE interventions ADD COLUMN tech_ticket_time TIME NULL",
        "ALTER TABLE interventions ADD COLUMN tech_close_time TIME NULL",
        "ALTER TABLE interventions ADD COLUMN tech_notes_extra TEXT NULL",
        "ALTER TABLE interventions MODIFY COLUMN tech_signature MEDIUMTEXT NULL",
    ] as $__sql) { try { db_execute($__sql); } catch (Throwable $_me) {} }
    @file_put_contents($_mf18, date('c'));
    unset($_me, $__sql);
}
unset($_mf18);
// Auto-migration v15.19 — catalogue de matériel par métier (sans doublon).
$_mf19 = __DIR__.'/../storage/.mig_v15_materiaux';
if (!file_exists($_mf19)) {
    try {
        db_execute("UPDATE preset_items SET label = 'Courroie' WHERE type = 'material' AND label = 'Courroi'");
        $__order = 10;
        foreach ((array)require __DIR__.'/materials_catalog.php' as $__cat => $__labels) {
            foreach ($__labels as $__label) {
                $__order++;
                if (!db_fetch("SELECT id FROM preset_items WHERE type = 'material' AND label = ?", [$__label])) {
                    db_execute("INSERT INTO preset_items (type, category, label, sort_order) VALUES ('material', ?, ?, ?)", [$__cat, $__label, $__order]);
                }
            }
        }
    } catch (Throwable $_me) {}
    @file_put_contents($_mf19, date('c'));
    unset($_me, $__order, $__cat, $__labels, $__label);
}
unset($_mf19);
// Auto-migration v15.20 — signature du client distincte de celle du technicien,
// photos demandées par le dispatcher, statut de paiement.
$_mf20 = __DIR__.'/../storage/.mig_v15_rapport_v2';
if (!file_exists($_mf20)) {
    foreach ([
        "ALTER TABLE interventions ADD COLUMN client_signature MEDIUMTEXT NULL",
        "ALTER TABLE interventions ADD COLUMN photos_required TEXT NULL",
        "ALTER TABLE interventions ADD COLUMN payment_status VARCHAR(20) NULL",
        "ALTER TABLE interventions ADD COLUMN paid_at DATETIME NULL",
        "ALTER TABLE interventions MODIFY COLUMN tech_signature MEDIUMTEXT NULL",
    ] as $__sql) { try { db_execute($__sql); } catch (Throwable $_me) {} }
    // Jusqu'ici, la signature enregistrée avec un nom de signataire était celle du client.
    try {
        db_execute("UPDATE interventions SET client_signature = tech_signature, tech_signature = NULL
                    WHERE client_signature IS NULL AND tech_signature IS NOT NULL
                      AND tech_client_name IS NOT NULL AND tech_client_name <> ''");
    } catch (Throwable $_me) {}
    try { db_execute("UPDATE interventions SET payment_status = 'payé' WHERE status = 'payé' AND payment_status IS NULL"); } catch (Throwable $_me) {}
    @file_put_contents($_mf20, date('c'));
    unset($_me, $__sql);
}
unset($_mf20);
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