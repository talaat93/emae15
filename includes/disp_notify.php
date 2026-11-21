<?php
declare(strict_types=1);
/**
 * Notifications du dispatcher : la cloche de l'en-tête et la page dispatcher/notifications.php.
 *
 * Un seul fil pour le bureau (lu par l'un = lu pour tous). Chaque évènement important appelle
 * disp_notify() ; une clé de dédoublonnage évite d'empiler deux fois la même alerte (fin
 * estimée dépassée vérifiée à chaque page). Les alertes urgentes partent en plus par e-mail
 * là où elles sont créées (refus, devis à faire, aide sans réponse).
 */

/** Types d'alerte : libellé, couleur, urgence. */
function disp_notif_kinds(): array
{
    return [
        'devis_a_faire'  => ['label' => 'Devis à faire',          'color' => '#ea580c', 'urgent' => true],
        'refus'          => ['label' => 'Intervention refusée',   'color' => '#dc2626', 'urgent' => true],
        'aide'           => ['label' => 'Demande d\'aide',        'color' => '#dc2626', 'urgent' => true],
        'eta_depassee'   => ['label' => 'Fin estimée dépassée',   'color' => '#b45309', 'urgent' => false],
        'rapport_rendu'  => ['label' => 'Rapport à valider',      'color' => '#16a34a', 'urgent' => false],
        'devis_reponse'  => ['label' => 'Devis : réponse client', 'color' => '#2563eb', 'urgent' => false],
        'paiement'       => ['label' => 'Paiement reçu',          'color' => '#0d9488', 'urgent' => false],
    ];
}

function disp_notif_table(): void
{
    static $done = false;
    if ($done) return;
    $done = true;
    try {
        db_execute("CREATE TABLE IF NOT EXISTS disp_notifications (
            id INT AUTO_INCREMENT PRIMARY KEY,
            kind VARCHAR(30) NOT NULL,
            title VARCHAR(255) NOT NULL,
            body VARCHAR(500) NULL,
            url VARCHAR(255) NULL,
            intervention_id INT NULL,
            dedupe_key VARCHAR(120) NULL,
            read_at DATETIME NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_dedupe (dedupe_key),
            INDEX idx_unread (read_at, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (Throwable $e) {}
}

/**
 * Ajoute une alerte. $url est un chemin du site (« dispatcher/intervention_view.php?id=3 »).
 * $dedupe : même clé = même alerte, ajoutée une seule fois. Renvoie false si elle existait déjà.
 */
function disp_notify(string $kind, string $title, string $body = '', string $url = '', ?int $ivId = null, ?string $dedupe = null): bool
{
    if (!isset(disp_notif_kinds()[$kind])) return false;
    disp_notif_table();
    try {
        if ($dedupe !== null && db_fetch('SELECT id FROM disp_notifications WHERE dedupe_key = ?', [$dedupe])) return false;
        db_execute('INSERT INTO disp_notifications (kind, title, body, url, intervention_id, dedupe_key) VALUES (?,?,?,?,?,?)',
            [$kind, mb_substr($title, 0, 255), mb_substr($body, 0, 500), mb_substr($url, 0, 255), $ivId, $dedupe]);
        return true;
    } catch (Throwable $e) { return false; }
}

function disp_notif_unread_count(): int
{
    disp_notif_table();
    try { return (int)(db_fetch('SELECT COUNT(*) AS n FROM disp_notifications WHERE read_at IS NULL')['n'] ?? 0); }
    catch (Throwable $e) { return 0; }
}

function disp_notif_list(int $limit = 100): array
{
    disp_notif_table();
    try { return db_fetch_all('SELECT * FROM disp_notifications ORDER BY (read_at IS NULL) DESC, created_at DESC, id DESC LIMIT '.max(1, min(500, $limit))); }
    catch (Throwable $e) { return []; }
}

function disp_notif_mark_read(?int $id = null): void
{
    disp_notif_table();
    try {
        if ($id === null) db_execute('UPDATE disp_notifications SET read_at = NOW() WHERE read_at IS NULL');
        else db_execute('UPDATE disp_notifications SET read_at = COALESCE(read_at, NOW()) WHERE id = ?', [$id]);
    } catch (Throwable $e) {}
}

/** Lien sûr : uniquement un chemin interne du site (jamais une adresse extérieure). */
function disp_notif_safe_url(string $url): string
{
    return preg_match('#^(dispatcher|tech|admin)/[A-Za-z0-9_./?=&%\#-]*$#', $url) ? $url : 'dispatcher/index.php';
}

/**
 * Fins estimées dépassées de plus de $minutes : une alerte par estimation (une nouvelle
 * estimation du technicien peut en redéclencher une). Appelée à l'affichage des pages dispatcher.
 */
function disp_notif_check_eta(int $minutes = 30): int
{
    static $ran = false;
    if ($ran) return 0;
    $ran = true;
    try {
        $rows = db_fetch_all("SELECT i.id, i.ref, i.tech_eta_end, t.name AS tech_name FROM interventions i LEFT JOIN technicians t ON t.id = i.technician_id
            WHERE i.status = 'sur_place' AND i.tech_eta_end IS NOT NULL AND i.tech_eta_end < ? LIMIT 50", [date('Y-m-d H:i:s', time() - $minutes * 60)]);
    } catch (Throwable $e) { return 0; }
    $n = 0;
    foreach ($rows as $r) {
        $end = strtotime((string)$r['tech_eta_end']);
        $late = (int)floor((time() - $end) / 60);
        if (disp_notify('eta_depassee', 'Fin estimée dépassée — '.($r['ref'] ?? '#'.$r['id']),
            ($r['tech_name'] ?? 'Le technicien').' devait finir vers '.date('G\hi', $end).' (retard : '.$late.' min). Le planning suivant peut glisser.',
            'dispatcher/intervention_view.php?id='.(int)$r['id'], (int)$r['id'], 'eta-'.(int)$r['id'].'-'.$end)) $n++;
    }
    return $n;
}
