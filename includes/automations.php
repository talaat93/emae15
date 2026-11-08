<?php
declare(strict_types=1);
/**
 * Tâches automatiques, lancées au plus toutes les 5 minutes :
 *  - 9.  alerte quand une demande du site reste sans réponse ;
 *  - 3.  demande d'avis Google après une intervention terminée ;
 *  - 15. rappel au client avant l'échéance de son contrat d'entretien ;
 *  - 10. suppression des photos des clients après la durée choisie ;
 *  - ménage : saisies non terminées de plus de 30 jours, codes de connexion ;
 *  - 2.  note Google mise à jour une fois par jour (si la clé est donnée).
 *
 * Deux façons de les lancer, qui se complètent :
 *  - toute seule, après l'affichage d'une page du site (automations_schedule()) ;
 *  - par une tâche planifiée o2switch qui appelle cron/automations.php, plus
 *    régulière quand le site a peu de visites la nuit.
 * Un verrou empêche deux exécutions en même temps.
 */

const AUTOMATIONS_EVERY = 300;   // secondes entre deux passages

function automations_state_file(): string { return __DIR__.'/../storage/.automations_last'; }

/** À appeler en fin de page : lance les tâches après l'envoi de la page, si leur heure est venue. */
function automations_schedule(): void
{
    static $done = false;
    if ($done || PHP_SAPI === 'cli' || defined('EMAE_TESTS_SANS_RESEAU')) return;
    $done = true;
    $last = (int)@filemtime(automations_state_file());
    if (time() - $last < AUTOMATIONS_EVERY) return;
    register_shutdown_function(static function (): void {
        // La page part d'abord chez le visiteur : il n'attend jamais les tâches.
        while (ob_get_level() > 0) @ob_end_flush();
        @flush();
        if (function_exists('fastcgi_finish_request')) @fastcgi_finish_request();
        if (function_exists('litespeed_finish_request')) @litespeed_finish_request();
        @ignore_user_abort(true);
        @set_time_limit(60);
        automations_run('page');
    });
}

/** Exécute toutes les tâches. Retour : ce qui a été fait, tâche par tâche. */
function automations_run(string $origin = 'page', bool $force = false): array
{
    $state = automations_state_file();
    if (!$force && time() - (int)@filemtime($state) < AUTOMATIONS_EVERY) return ['skipped' => 'trop tôt'];
    $lock = @fopen(__DIR__.'/../storage/.automations_lock', 'c');
    if ($lock === false || !flock($lock, LOCK_EX | LOCK_NB)) return ['skipped' => 'déjà en cours'];
    @touch($state);
    $out = [];
    foreach ([
        'alertes'   => 'automation_alert_unanswered',
        'avis'      => 'automation_review_requests',
        'entretien' => 'automation_contract_reminders',
        'photos'    => 'automation_purge_photos',
        'menage'    => 'automation_housekeeping',
        'google'    => 'automation_google_daily',
        'images'    => 'automation_images',
    ] as $name => $fn) {
        try { $out[$name] = $fn(); }
        catch (Throwable $e) { $out[$name] = 'erreur : '.$e->getMessage(); error_log('[EMAE auto] '.$name.' : '.$e->getMessage()); }
    }
    growth_save('automations_last_run', date('Y-m-d H:i:s').' ('.$origin.')');
    flock($lock, LOCK_UN);
    fclose($lock);
    return $out;
}

/** Heure de Paris, entre 9 h et 20 h : on n'envoie pas de SMS commercial la nuit. */
function automation_daytime(): bool
{
    $h = (int)(new DateTimeImmutable('now', new DateTimeZone('Europe/Paris')))->format('G');
    return $h >= 9 && $h < 20;
}

/* ── 9. Demandes sans réponse ── */
function automation_alert_unanswered(): string
{
    if (!growth_on('alert_unanswered_enabled')) return 'désactivé';
    $min = max(5, (int)growth_get('alert_unanswered_minutes'));
    try {
        // Seulement les demandes des dernières 24 h : à la mise en service, l'historique n'est pas réveillé.
        $rows = db_fetch_all("SELECT id, full_name, phone, postal_code, service_type, request_type, created_at FROM quotes
                              WHERE status = 'nouveau' AND alerted_at IS NULL AND created_at <= ? AND created_at >= ? ORDER BY id",
            [date('Y-m-d H:i:s', time() - $min * 60), date('Y-m-d H:i:s', time() - 86400)]);
    } catch (Throwable $e) { return 'colonne manquante'; }
    if (!$rows) return 'rien';
    $lines = [];
    foreach ($rows as $r) {
        $type = quote_type_info((string)($r['request_type'] ?? ''))[0];
        $lines[] = '<b>'.e((string)$r['full_name']).'</b> — <a href="tel:'.e((string)preg_replace('/[^0-9+]/', '', (string)$r['phone'])).'">'.e((string)$r['phone']).'</a> — '.e($type)
                 . ' — reçue à '.e(date('H:i', strtotime((string)$r['created_at']))).' — <a href="'.e(notif_abs_url('admin/dossier.php?id='.(int)$r['id'])).'">ouvrir</a>';
    }
    $n = count($rows);
    $to = global_setting('form_email_to', company_email());
    notif_mail($to, '⚠️ '.$n.' demande'.($n > 1 ? 's' : '').' sans réponse depuis '.$min.' min', 'Demande'.($n > 1 ? 's' : '').' en attente de réponse', array_merge(
        ['Ces clients attendent toujours votre appel. Une demande traitée change de statut dans l\'admin (elle n\'est alors plus signalée).'], $lines
    ), notif_abs_url('admin/quotes.php'), 'Voir les demandes');
    $admin = trim(global_setting('sms_admin_phone', ''));
    if ($admin !== '' && sms_configured()) {
        $first = $rows[0];
        $txt = '⚠️ '.$n.' demande'.($n > 1 ? 's' : '').' sans réponse depuis '.$min.' min. '.trim((string)$first['full_name']).' '.trim((string)$first['phone']).($n > 1 ? ' et '.($n - 1).' autre(s)' : '');
        auto_message_log('alerte_attente', (int)$first['id'], $admin, 'sms', notif_sms($admin, $txt));
    }
    $ids = implode(',', array_map(static fn(array $r): int => (int)$r['id'], $rows));
    db_execute('UPDATE quotes SET alerted_at = NOW() WHERE id IN ('.$ids.')');
    return $n.' signalée(s)';
}

/* ── 3. Demandes d'avis ── */
function automation_review_requests(): string
{
    if (!growth_on('review_request_enabled')) return 'désactivé';
    $link = trim(growth_get('google_reviews_url'));
    if ($link === '') return 'lien Google manquant';
    if (!automation_daytime()) return 'hors horaires';
    $delay = max(1, (int)growth_get('review_request_delay_h'));
    $from  = date('Y-m-d H:i:s', time() - 7 * 86400);
    $until = date('Y-m-d H:i:s', time() - $delay * 3600);
    $todo = [];
    try {
        foreach (db_fetch_all('SELECT i.id, c.firstname, c.lastname, c.phone, c.email FROM interventions i JOIN clients c ON c.id = i.client_id
                               WHERE i.status IN ('.wf_sql_list(wf_field_done()).') AND i.tech_completed_at BETWEEN ? AND ? AND i.review_requested_at IS NULL LIMIT 20', [$from, $until]) as $r) {
            $todo[] = ['table' => 'interventions', 'id' => (int)$r['id'], 'name' => trim((string)($r['firstname'] ?: $r['lastname'])), 'phone' => (string)$r['phone'], 'email' => (string)$r['email']];
        }
    } catch (Throwable $e) {}
    try {
        foreach (db_fetch_all("SELECT id, full_name, phone, email FROM quotes WHERE status = 'terminé' AND tech_completed_at BETWEEN ? AND ? AND review_requested_at IS NULL LIMIT 20", [$from, $until]) as $r) {
            $todo[] = ['table' => 'quotes', 'id' => (int)$r['id'], 'name' => quote_first_name((string)$r['full_name']), 'phone' => (string)$r['phone'], 'email' => (string)$r['email']];
        }
    } catch (Throwable $e) {}
    $sent = 0;
    foreach ($todo as $t) {
        $channel = 'aucun';
        // Un même client n'est pas sollicité deux fois en 3 mois.
        if (auto_message_recent('avis', $t['phone'] !== '' ? $t['phone'] : $t['email'], 24 * 90) === 0) {
            $txt = growth_fill(growth_get('review_request_text'), ['nom' => $t['name'], 'lien' => $link]);
            $ok = false;
            if (sms_configured() && phone_is_mobile_fr($t['phone'])) { $ok = notif_sms($t['phone'], $txt); $channel = 'sms'; }
            elseif (filter_var($t['email'], FILTER_VALIDATE_EMAIL)) {
                $ok = notif_mail($t['email'], 'Votre avis nous aide — '.company_name(), 'Merci pour votre confiance',
                    ['Bonjour '.e($t['name']).',', 'Merci d\'avoir fait appel à nous. Votre avis aide d\'autres clients à nous choisir : il ne prend qu\'une minute.'], $link, 'Laisser un avis sur Google');
                $channel = 'email';
            }
            if ($channel !== 'aucun') { auto_message_log('avis', $t['id'], $t['phone'] !== '' ? $t['phone'] : $t['email'], $channel, $ok); if ($ok) $sent++; }
        }
        try { db_execute('UPDATE '.$t['table'].' SET review_requested_at = NOW() WHERE id = ?', [$t['id']]); } catch (Throwable $e) {}
    }
    return $todo ? $sent.' envoyée(s) sur '.count($todo) : 'rien';
}

/* ── 15. Rappels d'entretien ── */
function automation_contract_reminders(): string
{
    if (global_setting('contracts_remind_enabled', '1') !== '1') return 'désactivé';
    if (!automation_daytime()) return 'hors horaires';
    $due = contracts_due_for_reminder();
    if (!$due) return 'rien';
    $lines = [];
    foreach ($due as $c) {
        $txt = contract_reminder_text($c);
        $how = 'à appeler (ni mobile ni e-mail)';
        if (sms_configured() && phone_is_mobile_fr((string)$c['phone'])) {
            $ok = notif_sms((string)$c['phone'], $txt); $how = $ok ? 'SMS envoyé' : 'SMS en échec';
            auto_message_log('entretien', (int)$c['id'], (string)$c['phone'], 'sms', $ok);
        } elseif (filter_var((string)$c['email'], FILTER_VALIDATE_EMAIL)) {
            $ok = notif_mail((string)$c['email'], 'Votre entretien annuel approche — '.company_name(), 'Votre entretien approche', [e($txt)]);
            $how = $ok ? 'e-mail envoyé' : 'e-mail en échec';
            auto_message_log('entretien', (int)$c['id'], (string)$c['email'], 'email', $ok);
        }
        $lines[] = '<b>'.e((string)$c['client_name']).'</b> — '.e((string)$c['equipment']).' — échéance le '.e(date('d/m/Y', strtotime((string)$c['next_visit']))).' — '.e((string)$c['phone']).' — '.$how;
        db_execute('UPDATE maintenance_contracts SET reminded_for = next_visit WHERE id = ?', [(int)$c['id']]);
    }
    notif_mail(global_setting('form_email_to', company_email()), '🔧 '.count($due).' entretien(s) à planifier', 'Entretiens à planifier',
        array_merge(['Ces clients sous contrat arrivent à échéance. Ils ont été prévenus quand c\'était possible :'], $lines),
        notif_abs_url('admin/contracts.php'), 'Voir les contrats');
    return count($due).' rappel(s)';
}

/* ── 10. Photos des clients : suppression après la durée choisie ── */
function automation_purge_photos(): string
{
    $months = (int)growth_get('quote_photos_keep_months');
    if ($months <= 0) return 'conservation sans limite';
    try {
        $rows = db_fetch_all('SELECT id, client_photos FROM quotes WHERE client_photos IS NOT NULL AND created_at < ? LIMIT 200',
            [date('Y-m-d H:i:s', (int)strtotime('-'.$months.' months'))]);
    } catch (Throwable $e) { return 'rien'; }
    foreach ($rows as $r) {
        quote_delete_photos($r);
        db_execute('UPDATE quotes SET client_photos = NULL WHERE id = ?', [(int)$r['id']]);
    }
    return $rows ? count($rows).' demande(s) nettoyée(s)' : 'rien';
}

/* ── Ménage ── */
function automation_housekeeping(): string
{
    partial_table();
    client_codes_table();
    try { db_execute('DELETE FROM quote_partials WHERE created_at < ?', [date('Y-m-d H:i:s', time() - 30 * 86400)]); } catch (Throwable $e) {}
    try { db_execute('DELETE FROM client_login_codes WHERE created_at < ?', [date('Y-m-d H:i:s', time() - 86400)]); } catch (Throwable $e) {}
    return 'ok';
}

/* ── 20. Vitesse : copies réduites des photos préparées à l'avance (5 par passage) ── */
function automation_images(): string
{
    if (!function_exists('imagecreatetruecolor')) return 'réduction d\'images indisponible sur le serveur';
    $done = 0;
    try { $rows = db_fetch_all('SELECT image_path, before_image_path FROM realisations WHERE is_visible = 1'); } catch (Throwable $e) { $rows = []; }
    foreach ($rows as $r) {
        foreach ([(string)($r['image_path'] ?? ''), (string)($r['before_image_path'] ?? '')] as $p) {
            if ($p === '' || $done >= 5) continue;
            $base = preg_replace('/\.[a-z]+$/i', '', ltrim($p, '/')).'-w1200';
            if (is_file(__DIR__.'/../'.$base.'.jpg') || is_file(__DIR__.'/../'.$base.'.png') || is_file(__DIR__.'/../'.$base.'.skip')) continue;
            image_resized_variant($p, 1200);
            $done++;
        }
    }
    return $done ? $done.' photo(s) réduite(s)' : 'rien';
}

/* ── 2. Note Google, une fois par jour ── */
function automation_google_daily(): string
{
    if (integration_secret('google_places_api_key') === '' || trim(growth_get('google_place_id')) === '') return 'non configuré';
    $last = (string)global_setting('google_rating_updated_at', '');
    if ($last !== '' && strtotime($last) > time() - 86400) return 'déjà fait aujourd\'hui';
    $r = google_rating_refresh();
    return $r['ok'] ? 'note mise à jour' : 'échec : '.$r['error'];
}
