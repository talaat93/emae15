<?php
declare(strict_types=1);

/**
 * Comptes administrateurs, rôles et droits d'accès écran par écran.
 *
 * Le compte principal (rôle « super ») voit tout, y compris le journal
 * d'activité et la gestion des comptes. Un compte standard n'accède
 * qu'aux écrans que le principal lui a cochés.
 */

/** Écrans de l'administration, groupés comme dans le menu. */
function admin_screens(): array
{
    return [
        "Vue d'ensemble" => [
            'index.php'        => ['🏠 Tableau de bord', true],   // toujours accessible
        ],
        'Identité' => [
            'site_identity.php'=> ['🏢 Identité & coordonnées'],
            'appearance.php'   => ['🎨 Couleurs & polices'],
            'header_menu.php'  => ['🧭 Header & menu'],
        ],
        'Contenu du site' => [
            'page_content.php' => ['📝 Textes des pages'],
            'search.php'       => ['🔎 Chercher et remplacer'],
        ],
        'Anciens écrans' => [
            'home_hero.php'    => ['⭐ Accueil complet'],
            'home_services.php'=> ['🔧 Cartes services'],
            'why_us.php'       => ['⭐ Pourquoi nous choisir'],
        ],
        'Contenus' => [
            'pages.php'        => ['📄 Pages'],
            'realisations.php' => ['📷 Réalisations'],
            'reviews.php'      => ['⭐ Avis clients'],
            'faq_contact.php'  => ['❓ FAQ & Contact'],
            'gallery.php'      => ['🖼️ Galerie médias'],
        ],
        'Pages spéciales' => [
            'zones.php'                => ['🗺️ Zones d\'intervention'],
            'services_hero_images.php' => ['🖼️ Images hero services'],
        ],
        'Multi-zones' => [
            'zones_manager.php'=> ['🗺️ Zones géographiques'],
            'zones_diag.php'   => ['🩺 Diagnostic des zones'],
            'zone_vars.php'    => ['🏷️ Variables de lieu'],
        ],
        'Leads & Interventions' => [
            'quotes.php'       => ['📋 Demandes & Interventions'],
        ],
        'Équipe' => [
            'technicians.php'  => ['👷 Techniciens'],
            'dispatchers.php'  => ['🗂️ Dispatchers'],
        ],
        'Notifications' => [
            'sms.php'          => ['📱 SMS — OVH'],
        ],
        'Marketing' => [
            'seo.php'          => ['🔍 SEO & Google Ads'],
            'design.php'       => ['🎨 Design & Couleurs'],
            'chatbot.php'      => ['🤖 Chatbot IA'],
        ],
        'Compte' => [
            'profile.php'      => ['👤 Mon profil', true],        // toujours accessible
            'mail_test.php'    => ['📧 Test email'],
        ],
    ];
}

/** Écrans réservés au compte principal, jamais délégables. */
function admin_super_only(): array
{
    return ['admins.php', 'activity.php'];
}

/** Écrans accessibles à tout admin connecté, hors système de droits. */
function admin_always_allowed(): array
{
    $out = ['login.php', 'logout.php', 'zone_context.php', 'inline_save.php'];
    foreach (admin_screens() as $group) {
        foreach ($group as $file => $def) if (!empty($def[1])) $out[] = $file;
    }
    return $out;
}

/** Tous les fichiers d'écran déclarés, à plat. */
function admin_screen_files(): array
{
    $out = [];
    foreach (admin_screens() as $group) foreach ($group as $file => $def) $out[] = $file;
    return $out;
}

/** Libellé lisible d'un écran. */
function admin_screen_label(string $file): string
{
    foreach (admin_screens() as $group) {
        if (isset($group[$file])) return $group[$file][0];
    }
    return match ($file) {
        'admins.php'   => '👥 Comptes administrateurs',
        'activity.php' => '📜 Journal d\'activité',
        default        => $file,
    };
}

function admin_is_super(?array $admin = null): bool
{
    $admin ??= current_admin();
    return ($admin['role'] ?? '') === 'super';
}

/** Écrans cochés pour un compte. Le principal a tout. */
function admin_permissions(array $admin): array
{
    if (admin_is_super($admin)) return admin_screen_files();
    $d = json_decode((string)($admin['permissions'] ?? ''), true);
    return is_array($d) ? array_values(array_filter($d, 'is_string')) : [];
}

/** Le compte connecté peut-il ouvrir cet écran ? */
function admin_can_access(string $file, ?array $admin = null): bool
{
    $admin ??= current_admin();
    if (!$admin) return false;
    if (admin_is_super($admin)) return true;
    if (in_array($file, admin_super_only(), true)) return false;
    if (in_array($file, admin_always_allowed(), true)) return true;
    return in_array($file, admin_permissions($admin), true);
}

/**
 * Refuse l'accès à l'écran courant si le compte n'y a pas droit.
 * Appelée au chargement de chaque page d'administration.
 */
function require_admin_access(?string $file = null): void
{
    $file ??= basename((string)($_SERVER['SCRIPT_NAME'] ?? ''));
    if (admin_can_access($file)) return;
    flash('error', 'Vous n\'avez pas accès à « '.admin_screen_label($file).' ». Demandez le droit au compte principal.');
    redirect_to('admin/index.php');
}

/* ═══════════════════════════════════════════════════
   JOURNAL D'ACTIVITÉ
═══════════════════════════════════════════════════ */

/**
 * Enregistre une action. Une seule ligne par enregistrement, même
 * lorsqu'il touche plusieurs champs : le détail reste lisible.
 */
function admin_log(string $action, string $resource = '', string $detail = ''): void
{
    $a = current_admin();
    if (!$a) return;
    try {
        db_execute(
            'INSERT INTO admin_activity (admin_id, admin_name, action, resource, detail, ip) VALUES (?,?,?,?,?,?)',
            [(int)$a['id'], (string)($a['name'] ?? $a['email'] ?? '?'),
             mb_substr($action, 0, 40), mb_substr($resource, 0, 120), mb_substr($detail, 0, 255), client_ip()]
        );
        if (random_int(1, 100) === 1) {
            db_execute('DELETE FROM admin_activity WHERE created_at < (NOW() - INTERVAL 1 YEAR)');
        }
    } catch (Throwable $e) { /* le journal ne doit jamais bloquer une action */ }
}

/** Journalise une connexion réussie et mémorise sa date sur le compte. */
function admin_log_login(array $admin): void
{
    try {
        db_execute('INSERT INTO admin_activity (admin_id, admin_name, action, resource, ip) VALUES (?,?,?,?,?)',
            [(int)$admin['id'], (string)($admin['name'] ?? $admin['email'] ?? '?'), 'connexion', '', client_ip()]);
        db_execute('UPDATE admins SET last_login_at = NOW() WHERE id = ?', [(int)$admin['id']]);
    } catch (Throwable $e) {}
}

/** Dernières entrées du journal. */
function admin_activity_recent(int $limit = 50, int $adminId = 0): array
{
    $limit = max(1, min(500, $limit));
    try {
        return $adminId > 0
            ? db_fetch_all('SELECT * FROM admin_activity WHERE admin_id = ? ORDER BY created_at DESC LIMIT '.$limit, [$adminId])
            : db_fetch_all('SELECT * FROM admin_activity ORDER BY created_at DESC LIMIT '.$limit);
    } catch (Throwable $e) { return []; }
}

/* ═══════════════════════════════════════════════════
   COMPTES
═══════════════════════════════════════════════════ */

function all_admins(): array
{
    try { return db_fetch_all('SELECT * FROM admins ORDER BY role DESC, name ASC'); }
    catch (Throwable $e) { return []; }
}

function get_admin(int $id): ?array
{
    try { return db_fetch('SELECT * FROM admins WHERE id = ?', [$id]); }
    catch (Throwable $e) { return null; }
}

/** Nombre de comptes principaux : sert à ne jamais supprimer le dernier. */
function super_admin_count(): int
{
    try { return (int)(db_fetch("SELECT COUNT(*) AS n FROM admins WHERE role = 'super'")['n'] ?? 0); }
    catch (Throwable $e) { return 1; }
}
