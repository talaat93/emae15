<?php
declare(strict_types=1);

/**
 * Mesure d'audience interne, volontairement agrégée.
 *
 * Une seule ligne par jour et par page : aucune adresse IP, aucun
 * identifiant de visiteur, rien qui permette de reconstituer un parcours.
 * La base reste donc minuscule et aucune obligation nouvelle ne pèse sur
 * le site côté données personnelles.
 */

/** Robots connus : ils ne doivent pas gonfler les chiffres. */
function stats_is_bot(string $ua): bool
{
    if ($ua === '') return true;
    return (bool)preg_match(
        '/bot|crawl|spider|slurp|bingpreview|facebookexternalhit|embedly|quora|pinterest|vkshare|whatsapp|telegram|headless|lighthouse|pagespeed|gtmetrix|uptime|monitor|curl|wget|python-requests|axios|postman/i',
        $ua
    );
}

/** Nom lisible d'une page, à partir de la route. */
function stats_page_key(string $route): string
{
    $route = trim($route, '/');
    if ($route === '' || $route === 'home') return 'accueil';
    return mb_substr(preg_replace('/[^a-z0-9_-]/i', '', $route) ?: 'autre', 0, 120);
}

/**
 * Compte une page vue. Silencieux en cas d'erreur : la mesure ne doit
 * jamais empêcher la page de s'afficher.
 */
function stats_track_view(string $route, string $zoneSlug = ''): void
{
    if (!stats_should_count()) return;
    try {
        db_execute(
            'INSERT INTO site_stats (stat_date, zone_slug, page_key, views) VALUES (CURDATE(), ?, ?, 1)
             ON DUPLICATE KEY UPDATE views = views + 1',
            [mb_substr($zoneSlug, 0, 80), stats_page_key($route)]
        );
    } catch (Throwable $e) {}
}

/**
 * Évènements mesurables. La liste vit ici et non dans le point d'entrée
 * public : la sécurité ne doit pas dépendre de la vigilance de l'appelant.
 */
function stats_allowed_events(): array
{
    return ['appel', 'whatsapp', 'devis_ouvert'];
}

/** Compte un évènement, par exemple un clic sur le bouton d'appel. */
function stats_track_event(string $event, string $zoneSlug = ''): void
{
    // Un nom inconnu est refusé, jamais « nettoyé » : sinon n'importe quelle
    // saisie finirait par créer un compteur.
    if (!in_array($event, stats_allowed_events(), true)) return;
    // Un évènement arrive par POST : contrairement aux pages vues, la méthode
    // n'est pas un critère ici.
    if (!stats_should_count(false)) return;
    try {
        db_execute(
            'INSERT INTO site_events (stat_date, zone_slug, event_key, total) VALUES (CURDATE(), ?, ?, 1)
             ON DUPLICATE KEY UPDATE total = total + 1',
            [mb_substr($zoneSlug, 0, 80), mb_substr($event, 0, 60)]
        );
    } catch (Throwable $e) {}
}

/**
 * Faut-il compter cette requête ? On écarte les robots, les requêtes
 * non-GET et les administrateurs connectés — sinon vos propres visites
 * fausseraient vos chiffres.
 */
function stats_should_count(bool $exigerGet = true): bool
{
    if ($exigerGet && ($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') return false;
    if (stats_is_bot((string)($_SERVER['HTTP_USER_AGENT'] ?? ''))) return false;
    if (function_exists('admin_logged_in') && admin_logged_in()) return false;
    return true;
}

/* ═══════════════════════════════════════════════════
   LECTURE DES CHIFFRES
═══════════════════════════════════════════════════ */

/** Total des pages vues sur une période, en jours révolus. */
function stats_views_total(int $jours = 30, int $decalage = 0): int
{
    try {
        $r = db_fetch(
            'SELECT COALESCE(SUM(views),0) AS n FROM site_stats
             WHERE stat_date > (CURDATE() - INTERVAL ? DAY) AND stat_date <= (CURDATE() - INTERVAL ? DAY)',
            [$jours + $decalage, $decalage]
        );
        return (int)($r['n'] ?? 0);
    } catch (Throwable $e) { return 0; }
}

function stats_event_total(string $event, int $jours = 30, int $decalage = 0): int
{
    try {
        $r = db_fetch(
            'SELECT COALESCE(SUM(total),0) AS n FROM site_events
             WHERE event_key = ? AND stat_date > (CURDATE() - INTERVAL ? DAY) AND stat_date <= (CURDATE() - INTERVAL ? DAY)',
            [$event, $jours + $decalage, $decalage]
        );
        return (int)($r['n'] ?? 0);
    } catch (Throwable $e) { return 0; }
}

/** Pages les plus consultées. */
function stats_top_pages(int $jours = 30, int $limit = 8): array
{
    $limit = max(1, min(50, $limit));
    try {
        return db_fetch_all(
            'SELECT page_key, SUM(views) AS n FROM site_stats
             WHERE stat_date > (CURDATE() - INTERVAL ? DAY)
             GROUP BY page_key ORDER BY n DESC LIMIT '.$limit,
            [$jours]
        );
    } catch (Throwable $e) { return []; }
}

/** Trafic réparti par zone géographique. */
function stats_by_zone(int $jours = 30): array
{
    try {
        return db_fetch_all(
            'SELECT zone_slug, SUM(views) AS n FROM site_stats
             WHERE stat_date > (CURDATE() - INTERVAL ? DAY)
             GROUP BY zone_slug ORDER BY n DESC',
            [$jours]
        );
    } catch (Throwable $e) { return []; }
}

/** Vues jour par jour, pour la courbe. */
function stats_daily(int $jours = 30): array
{
    try {
        return db_fetch_all(
            'SELECT stat_date, SUM(views) AS n FROM site_stats
             WHERE stat_date > (CURDATE() - INTERVAL ? DAY)
             GROUP BY stat_date ORDER BY stat_date ASC',
            [$jours]
        );
    } catch (Throwable $e) { return []; }
}

/** Évolution en pourcentage entre la période et la précédente. */
function stats_evolution(int $actuel, int $precedent): ?int
{
    if ($precedent <= 0) return null;          // pas de base de comparaison
    return (int)round((($actuel - $precedent) / $precedent) * 100);
}

/** Affichage lisible d'une évolution : « +18 % », « −4 % », ou rien. */
function stats_evolution_label(?int $evo): string
{
    if ($evo === null) return '';
    if ($evo === 0) return 'stable';
    return ($evo > 0 ? '+' : '−').abs($evo).' %';
}
