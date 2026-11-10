<?php
declare(strict_types=1);
/**
 * Outils pour obtenir plus d'appels et de demandes : bandeau de disponibilité,
 * note Google, WhatsApp, SMS automatiques, demandes non terminées, rendez-vous.
 *
 * Tous ces réglages valent pour le site entier, zones comprises : ils se lisent
 * avec global_setting() et s'écrivent avec growth_save(), jamais par zone.
 * Écran : admin/growth.php.
 */

/**
 * Texte en minuscules sans accents (« Après-midi » → « apres-midi »). Même résultat
 * sur tous les serveurs, contrairement à iconv dont la translittération dépend du système.
 */
function text_ascii_lower(string $s): string
{
    $s = mb_strtolower($s, 'UTF-8');
    return strtr($s, ['à'=>'a','â'=>'a','ä'=>'a','á'=>'a','ã'=>'a','ç'=>'c','é'=>'e','è'=>'e','ê'=>'e','ë'=>'e','î'=>'i','ï'=>'i','í'=>'i',
                      'ô'=>'o','ö'=>'o','ó'=>'o','õ'=>'o','ù'=>'u','û'=>'u','ü'=>'u','ú'=>'u','ÿ'=>'y','ñ'=>'n','œ'=>'oe','æ'=>'ae','’'=>"'"]);
}

/** Écrit un réglage global, quelle que soit la zone choisie dans l'admin. */
function growth_save(string $key, string $value): void
{
    $value = mb_scrub($value, 'UTF-8');   // un caractère mal encodé ne doit pas faire échouer l'enregistrement
    db_execute('INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
                ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)', [$key, $value]);
    settings_cache(true);
}

/**
 * Retient l'adresse publique du site, lue quand un administrateur connecté ouvre
 * l'admin (jamais depuis une visite anonyme, dont l'en-tête Host pourrait être
 * falsifié). Sert aux liens des messages envoyés par la tâche planifiée.
 */
function growth_remember_origin(): void
{
    $host = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
    if ($host === '' || preg_match('/^(localhost|127\.|\[?::1)/', $host)) return;
    $origin = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http').'://'.$host;
    if (preg_match('/^https?:\/\/[a-z0-9.-]+(:\d+)?$/', $origin) && global_setting('site_public_origin', '') !== $origin) {
        try { growth_save('site_public_origin', $origin); } catch (Throwable $e) {}
    }
}

/** Valeurs par défaut de tous les réglages de l'écran « Conversion & avis ». */
function growth_defaults(): array
{
    return [
        // Bandeau de disponibilité
        'avail_enabled'      => '1',
        'avail_days'         => '1,2,3,4,5,6',
        'avail_start'        => '08:00',
        'avail_end'          => '20:00',
        // Le bandeau suit des horaires, pas la présence réelle d'un technicien : pas de « disponible aujourd'hui ».
        'avail_open_text'    => 'Ligne ouverte — appelez-nous : disponibilité et délai confirmés au téléphone',
        'avail_closed_text'  => 'Urgence ? Laissez-nous un message ou appelez : nous vous rappelons dès que possible',
        // Avis Google
        'google_reviews_url' => '',
        'google_place_id'    => '',
        'google_rating'      => '',
        'google_review_count'=> '',
        // WhatsApp
        'whatsapp_message'   => 'Bonjour, j\'ai besoin d\'un dépannage. Voici une photo de mon installation :',
        // Message automatique au client après sa demande
        'auto_sms_client_enabled' => '1',
        'auto_sms_client_text'    => '{entreprise} : bonjour {nom}, nous avons bien reçu votre demande. Nous vous rappelons au plus vite. En cas d\'urgence : {telephone}',
        'auto_sms_daily_cap'      => '40',
        // Demande d'avis après intervention
        'review_request_enabled'  => '1',
        'review_request_delay_h'  => '3',
        'review_request_text'     => '{entreprise} : merci pour votre confiance ! Votre avis compte beaucoup pour nous, il ne prend qu\'une minute : {lien}',
        // Alerte si une demande reste sans réponse
        'alert_unanswered_enabled' => '1',
        'alert_unanswered_minutes' => '30',
        // Conservation des photos envoyées par les clients (0 = sans limite)
        'quote_photos_keep_months' => '12',
        // Demandes non terminées (désactivé tant que vous ne l'activez pas)
        'partial_enabled'    => '0',
        // Suivi de l'intervention par le client
        'track_enabled'      => '1',
        // Rendez-vous en ligne
        'booking_enabled'    => '0',
        'booking_slots'      => "Matin (8h – 12h)\nAprès-midi (14h – 18h)",
        'booking_capacity'   => '2',
        'booking_days_ahead' => '14',
        'booking_weekdays'   => '1,2,3,4,5,6',
    ];
}

function growth_get(string $key): string
{
    return global_setting($key, growth_defaults()[$key] ?? '');
}

function growth_on(string $key): bool
{
    return growth_get($key) === '1';
}

/** Remplace {entreprise}, {nom}, {telephone}, {lien}… dans un texte de message. */
function growth_fill(string $tpl, array $vars): string
{
    $vars += ['entreprise' => company_name(), 'telephone' => global_setting('company_phone', '')];
    $out = $tpl;
    foreach ($vars as $k => $v) $out = str_replace('{'.$k.'}', trim((string)$v), $out);
    // Une variable vide ne doit pas laisser « bonjour , » dans le message.
    return trim((string)preg_replace(['/ +,/u', '/ {2,}/'], [',', ' '], $out));
}

/* ═══════════════════════════════════════════════════
   1. BANDEAU DE DISPONIBILITÉ
═══════════════════════════════════════════════════ */
/** Le bandeau à afficher maintenant : ouvert (dans les horaires) ou non. */
function availability_now(?DateTimeImmutable $now = null): array
{
    $now  = $now ?? new DateTimeImmutable('now', new DateTimeZone('Europe/Paris'));
    $days = array_map('intval', array_filter(explode(',', growth_get('avail_days')), 'strlen'));
    $hm   = $now->format('H:i');
    $open = in_array((int)$now->format('N'), $days, true)
         && $hm >= growth_get('avail_start') && $hm < growth_get('avail_end');
    return ['open' => $open, 'text' => growth_get($open ? 'avail_open_text' : 'avail_closed_text')];
}

function render_availability_bar(): void
{
    if (!growth_on('avail_enabled')) return;
    $a = availability_now();
    if (trim($a['text']) === '') return;
    echo '<a class="avail-bar'.($a['open'] ? ' is-open' : '').'" href="'.e(company_phone_link()).'">'
       . '<span class="avail-dot" aria-hidden="true"></span><span class="avail-txt">'.e($a['text']).'</span>'
       . '<span class="avail-cta">'.icon_svg('phone').' Appeler</span></a>';
}

/* ═══════════════════════════════════════════════════
   2. NOTE GOOGLE
   Affichée seulement quand vous avez donné le lien de votre fiche et une note :
   jamais de chiffre inventé présenté comme un avis Google.
═══════════════════════════════════════════════════ */
function google_rating(): ?array
{
    $url    = trim(growth_get('google_reviews_url'));
    $rating = (float)str_replace(',', '.', growth_get('google_rating'));
    $count  = (int)growth_get('google_review_count');
    if ($url === '' || !preg_match('#^https://#i', $url) || $rating <= 0 || $rating > 5 || $count <= 0) return null;
    return ['rating' => $rating, 'count' => $count, 'url' => $url];
}

function render_google_badge(): string
{
    $g = google_rating();
    if ($g === null) return '';
    $full  = (int)round($g['rating']);
    $stars = str_repeat('★', $full).str_repeat('☆', 5 - $full);
    return '<a class="g-badge" href="'.e($g['url']).'" target="_blank" rel="noopener">'
         . '<span class="g-badge-g" aria-hidden="true">G</span>'
         . '<span class="g-badge-stars" aria-hidden="true">'.$stars.'</span>'
         . '<span class="g-badge-txt"><strong>'.e(number_format($g['rating'], 1, ',', '')).'/5</strong> · '.e((string)$g['count']).' avis Google</span></a>';
}

/**
 * Met à jour la note depuis Google (Places API). Ne fait rien sans clé ni identifiant
 * de fiche : la note saisie à la main reste alors affichée telle quelle.
 */
function google_rating_refresh(): array
{
    $key   = function_exists('integration_secret') ? integration_secret('google_places_api_key') : '';
    $place = trim(growth_get('google_place_id'));
    if ($key === '' || $place === '') return ['ok' => false, 'error' => 'Clé ou identifiant de fiche manquant.'];
    $r = integration_http('GET', 'https://places.googleapis.com/v1/places/'.rawurlencode($place), [
        'X-Goog-Api-Key: '.$key,
        'X-Goog-FieldMask: rating,userRatingCount',
    ], null, 15);
    $rating = (float)($r['json']['rating'] ?? 0);
    $count  = (int)($r['json']['userRatingCount'] ?? 0);
    if ($r['status'] !== 200 || $rating <= 0) {
        $err = (string)($r['json']['error']['message'] ?? $r['error'] ?? ('code '.$r['status']));
        if (function_exists('integration_log')) integration_log('google', 'Note Google non mise à jour : '.$err, []);
        return ['ok' => false, 'error' => $err];
    }
    growth_save('google_rating', (string)$rating);
    growth_save('google_review_count', (string)$count);
    growth_save('google_rating_updated_at', date('Y-m-d H:i:s'));
    return ['ok' => true, 'rating' => $rating, 'count' => $count];
}

/* ═══════════════════════════════════════════════════
   13. WHATSAPP
═══════════════════════════════════════════════════ */
/** Numéro au format attendu par WhatsApp : indicatif sans « + » ni zéro (33612345678). */
function whatsapp_digits(string $raw): string
{
    $d = (string)preg_replace('/\D/', '', $raw);
    if (str_starts_with($d, '00')) $d = substr($d, 2);
    if (strlen($d) === 10 && $d[0] === '0') $d = '33'.substr($d, 1);
    if (str_starts_with($d, '330')) $d = '33'.substr($d, 3);   // « +33 0 6… »
    return $d;
}

/** Message d'alerte si le numéro ne ressemble pas à un numéro français complet ('' si correct). */
function whatsapp_number_problem(string $raw): string
{
    $d = whatsapp_digits($raw);
    if ($d === '') return '';
    if (str_starts_with($d, '33') && strlen($d) !== 11) {
        return 'Ce numéro a '.(strlen($d) - 2).' chiffres après l\'indicatif +33 au lieu de 9 : il manque ou il y a un chiffre en trop. Le bouton WhatsApp ne fonctionnera pas.';
    }
    return '';
}

function whatsapp_url(): string
{
    $d = whatsapp_digits(company_whatsapp());
    // Numéro incomplet (ex. 3367830376 : 8 chiffres après +33) : pas de bouton qui mène
    // nulle part. Le numéro est signalé dans l'admin ; on ne devine jamais le chiffre manquant.
    if ($d === '' || whatsapp_number_problem(company_whatsapp()) !== '' || strlen($d) < 11) return '';
    $msg = trim(growth_get('whatsapp_message'));
    return 'https://wa.me/'.$d.($msg !== '' ? '?text='.rawurlencode($msg) : '');
}

/* ═══════════════════════════════════════════════════
   SMS AUTOMATIQUES : garde-fous communs
   Un formulaire public peut être rempli avec le numéro de n'importe qui : chaque
   message automatique est limité par numéro et par jour, et tracé.
═══════════════════════════════════════════════════ */
function phone_last9(string $phone): string
{
    $d = (string)preg_replace('/\D/', '', $phone);
    return strlen($d) >= 9 ? substr($d, -9) : $d;
}

function phone_is_mobile_fr(string $phone): bool
{
    $d = (string)preg_replace('/\D/', '', $phone);
    if (str_starts_with($d, '0033')) $d = '0'.substr($d, 4);
    elseif (str_starts_with($d, '33') && strlen($d) === 11) $d = '0'.substr($d, 2);
    return (bool)preg_match('/^0[67]\d{8}$/', $d);
}

function sms_configured(): bool
{
    foreach (['ovh_app_key', 'ovh_app_secret', 'ovh_consumer_key', 'ovh_service_name'] as $k) {
        if (trim(global_setting($k, '')) === '') return false;
    }
    return true;
}

function auto_messages_table(): void
{
    static $done = false;
    if ($done) return;
    $done = true;
    try {
        db_execute("CREATE TABLE IF NOT EXISTS auto_messages (
            id INT AUTO_INCREMENT PRIMARY KEY,
            kind VARCHAR(40) NOT NULL,
            ref_id INT NULL,
            phone9 VARCHAR(12) NOT NULL DEFAULT '',
            channel VARCHAR(10) NOT NULL DEFAULT 'sms',
            ok TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_kind (kind, created_at),
            INDEX idx_phone (phone9, kind)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (Throwable $e) {}
}

function auto_message_log(string $kind, ?int $refId, string $phone, string $channel, bool $ok): void
{
    auto_messages_table();
    try {
        db_execute('INSERT INTO auto_messages (kind, ref_id, phone9, channel, ok) VALUES (?, ?, ?, ?, ?)',
            [$kind, $refId, phone_last9($phone), $channel, $ok ? 1 : 0]);
    } catch (Throwable $e) {}
}

/** Nombre de messages de ce type déjà envoyés à ce numéro dans les $hours dernières heures. */
function auto_message_recent(string $kind, string $phone, int $hours): int
{
    auto_messages_table();
    try {
        $r = db_fetch('SELECT COUNT(*) AS n FROM auto_messages WHERE kind = ? AND phone9 = ? AND created_at > ?',
            [$kind, phone_last9($phone), date('Y-m-d H:i:s', time() - $hours * 3600)]);
        return (int)($r['n'] ?? 0);
    } catch (Throwable $e) { return 0; }
}

function auto_message_today(string $kind): int
{
    auto_messages_table();
    try {
        $r = db_fetch('SELECT COUNT(*) AS n FROM auto_messages WHERE kind = ? AND created_at >= ?', [$kind, date('Y-m-d 00:00:00')]);
        return (int)($r['n'] ?? 0);
    } catch (Throwable $e) { return 0; }
}

/** Dernières traces, pour l'écran d'admin. */
function auto_message_recent_list(int $limit = 30): array
{
    auto_messages_table();
    try { return db_fetch_all('SELECT * FROM auto_messages ORDER BY id DESC LIMIT '.max(1, $limit)); }
    catch (Throwable $e) { return []; }
}

/* ═══════════════════════════════════════════════════
   4. JUSTE APRÈS UNE DEMANDE : SMS au client, SMS à l'entreprise
═══════════════════════════════════════════════════ */
function quote_after_submit_messages(array $q): void
{
    try {
        $phone = trim((string)($q['phone'] ?? ''));
        $id    = (int)($q['quote_id'] ?? 0);
        // SMS à l'entreprise : la case existait dans « Notifications SMS » mais n'envoyait rien.
        $admin = trim(global_setting('sms_admin_phone', ''));
        if (global_setting('sms_notify_admin', '') === '1' && $admin !== '') {
            $type = ['rappel' => 'RAPPEL', 'rdv' => 'RENDEZ-VOUS', 'entretien' => 'ENTRETIEN'][(string)($q['request_type'] ?? '')] ?? 'DEVIS';
            $txt  = 'Nouvelle demande ('.$type.') : '.trim((string)($q['full_name'] ?? '')).' — '.$phone
                  . (trim((string)($q['postal_code'] ?? '')) !== '' ? ' — '.trim((string)$q['postal_code']) : '')
                  . (trim((string)($q['service_type'] ?? '')) !== '' ? ' — '.trim((string)$q['service_type']) : '');
            auto_message_log('alerte_demande', $id, $admin, 'sms', notif_sms($admin, mb_substr($txt, 0, 300)));
        }
        // Accusé de réception au client : mobile seulement, une fois par jour et par numéro.
        if (growth_on('auto_sms_client_enabled') && sms_configured() && phone_is_mobile_fr($phone)
            && auto_message_recent('accuse', $phone, 24) === 0
            && auto_message_today('accuse') < max(1, (int)growth_get('auto_sms_daily_cap'))) {
            $txt = growth_fill(growth_get('auto_sms_client_text'), ['nom' => quote_first_name((string)($q['full_name'] ?? ''))]);
            auto_message_log('accuse', $id, $phone, 'sms', notif_sms($phone, $txt));
        }
    } catch (Throwable $e) { error_log('[EMAE] messages après demande : '.$e->getMessage()); }
}

/** « Jean Dupont » → « Jean ». Un nom d'un seul mot est gardé tel quel. */
function quote_first_name(string $full): string
{
    $parts = preg_split('/\s+/', trim($full)) ?: [];
    return mb_substr((string)($parts[0] ?? ''), 0, 30);
}

/* ═══════════════════════════════════════════════════
   14. DEMANDES NON TERMINÉES
   Le visiteur a saisi son nom et son téléphone mais n'a pas envoyé le formulaire.
   Désactivé par défaut ; une mention s'affiche sous le téléphone quand c'est actif.
═══════════════════════════════════════════════════ */
/**
 * Retiré (novembre 2026) : envoyer le nom et le téléphone d'un visiteur avant qu'il
 * clique sur « Envoyer » est une collecte qu'il n'a pas demandée. Plus aucun
 * formulaire n'envoie de saisie en cours, et le serveur refuse ces envois, même si
 * l'ancien réglage est encore coché ou qu'un vieux script les rejoue. Les saisies
 * déjà enregistrées restent consultables dans l'admin (aucune purge sans décision).
 */
function partial_enabled(): bool { return false; }

function partial_table(): void
{
    static $done = false;
    if ($done) return;
    $done = true;
    try {
        db_execute("CREATE TABLE IF NOT EXISTS quote_partials (
            id INT AUTO_INCREMENT PRIMARY KEY,
            token CHAR(32) NOT NULL,
            full_name VARCHAR(190) NULL,
            phone VARCHAR(80) NOT NULL,
            email VARCHAR(190) NULL,
            postal_code VARCHAR(10) NULL,
            service_type VARCHAR(190) NULL,
            source VARCHAR(120) NULL,
            handled_at DATETIME NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NULL,
            UNIQUE KEY uq_token (token),
            INDEX idx_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (Throwable $e) {}
}

/** Enregistre ou met à jour la saisie en cours. Retour : true si gardée. */
function partial_save(array $p): bool
{
    if (!partial_enabled()) return false;
    $token = (string)($p['partial_token'] ?? '');
    $phone = trim((string)($p['phone'] ?? ''));
    if (!preg_match('/^[a-f0-9]{32}$/', $token) || strlen((string)preg_replace('/\D/', '', $phone)) < 9) return false;
    $cut = static fn(string $k, int $n): string => mb_substr(trim((string)($p[$k] ?? '')), 0, $n);
    partial_table();
    try {
        db_execute('INSERT INTO quote_partials (token, full_name, phone, email, postal_code, service_type, source, updated_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
                    ON DUPLICATE KEY UPDATE full_name = VALUES(full_name), phone = VALUES(phone), email = VALUES(email),
                      postal_code = VALUES(postal_code), service_type = VALUES(service_type), updated_at = NOW()',
            [$token, $cut('full_name', 190), mb_substr($phone, 0, 80), $cut('email', 190), $cut('postal_code', 10), $cut('service_type', 190), $cut('source', 120)]);
        return true;
    } catch (Throwable $e) { return false; }
}

/** La demande a été envoyée : la saisie en cours n'a plus lieu d'être. */
function partial_delete(string $token): void
{
    if (!preg_match('/^[a-f0-9]{32}$/', $token)) return;
    partial_table();
    try { db_execute('DELETE FROM quote_partials WHERE token = ?', [$token]); } catch (Throwable $e) {}
}

/** Saisies non envoyées depuis plus de 10 minutes (le visiteur a vraiment abandonné). */
function partial_list(int $days = 30): array
{
    partial_table();
    try {
        return db_fetch_all('SELECT * FROM quote_partials WHERE COALESCE(updated_at, created_at) < ? AND created_at > ? ORDER BY id DESC LIMIT 200',
            [date('Y-m-d H:i:s', time() - 600), date('Y-m-d H:i:s', time() - $days * 86400)]);
    } catch (Throwable $e) { return []; }
}

/* ═══════════════════════════════════════════════════
   11. RENDEZ-VOUS EN LIGNE
   Le client choisit une demi-journée ; vous confirmez ensuite par téléphone ou SMS.
   Un créneau se ferme quand il a reçu autant de demandes que la capacité réglée.
═══════════════════════════════════════════════════ */
function booking_enabled(): bool { return growth_on('booking_enabled'); }

/** Créneaux proposés : clé courte => libellé. */
function booking_slots(): array
{
    $out = [];
    foreach (preg_split('/\R/', growth_get('booking_slots')) ?: [] as $line) {
        $line = trim($line);
        if ($line === '') continue;
        $key = substr(trim((string)preg_replace('/[^a-z0-9]+/', '-', text_ascii_lower($line)), '-'), 0, 30);
        if ($key !== '' && !isset($out[$key])) $out[$key] = $line;
    }
    return $out ?: ['matin' => 'Matin', 'apres-midi' => 'Après-midi'];
}

/**
 * Jours des interventions planifiées (1 = lundi … 7 = dimanche). Réglage propre aux
 * rendez-vous : le bandeau de disponibilité et les urgences 24h/24 n'en dépendent pas.
 * Par défaut lundi-samedi, comme l'annonce la FAQ.
 */
function booking_weekdays(): array
{
    $d = array_values(array_unique(array_filter(array_map('intval', explode(',', growth_get('booking_weekdays'))), static fn(int $n): bool => $n >= 1 && $n <= 7)));
    return $d ?: [1, 2, 3, 4, 5, 6];
}

/** Jours fériés en France métropolitaine pour une année (Y-m-d => nom). */
function french_public_holidays(int $year): array
{
    // Dimanche de Pâques (algorithme grégorien anonyme) : pas besoin de l'extension calendar.
    $a = $year % 19; $b = intdiv($year, 100); $c = $year % 100; $d = intdiv($b, 4); $e = $b % 4;
    $f = intdiv($b + 8, 25); $g = intdiv($b - $f + 1, 3); $h = (19 * $a + $b - $d - $g + 15) % 30;
    $i = intdiv($c, 4); $k = $c % 4; $l = (32 + 2 * $e + 2 * $i - $h - $k) % 7; $m = intdiv($a + 11 * $h + 22 * $l, 451);
    $month = intdiv($h + $l - 7 * $m + 114, 31); $day = (($h + $l - 7 * $m + 114) % 31) + 1;
    $easter = new DateTimeImmutable(sprintf('%04d-%02d-%02d', $year, $month, $day), new DateTimeZone('Europe/Paris'));
    $out = [];
    foreach (['01-01' => 'Jour de l\'an', '05-01' => 'Fête du Travail', '05-08' => 'Victoire 1945', '07-14' => 'Fête nationale',
              '08-15' => 'Assomption', '11-01' => 'Toussaint', '11-11' => 'Armistice', '12-25' => 'Noël'] as $md => $name) {
        $out[$year.'-'.$md] = $name;
    }
    $out[$easter->modify('+1 day')->format('Y-m-d')]  = 'Lundi de Pâques';
    $out[$easter->modify('+39 days')->format('Y-m-d')] = 'Ascension';
    $out[$easter->modify('+50 days')->format('Y-m-d')] = 'Lundi de Pentecôte';
    return $out;
}

/** Libellé lisible des jours planifiés : « du lundi au samedi », « lundi, mercredi et vendredi ». */
function booking_weekdays_label(): string
{
    $noms = [1 => 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi', 'dimanche'];
    $d = booking_weekdays(); sort($d);
    if (count($d) >= 3 && $d === range($d[0], end($d))) return 'du '.$noms[$d[0]].' au '.$noms[end($d)];
    $l = array_map(static fn(int $n): string => $noms[$n], $d);
    return count($l) > 1 ? implode(', ', array_slice($l, 0, -1)).' et '.end($l) : (string)($l[0] ?? '');
}

/** Jours proposés : à partir de demain (heure de Paris), jours planifiés, hors jours fériés. */
function booking_days(?DateTimeImmutable $today = null): array
{
    $today = $today ?? new DateTimeImmutable('today', new DateTimeZone('Europe/Paris'));
    $today = $today->setTime(0, 0);   // calcul par jour calendaire : le changement d'heure ne décale rien
    $open  = booking_weekdays();
    $ahead = max(3, min(60, (int)growth_get('booking_days_ahead')));
    $out = [];
    for ($i = 1; $i <= $ahead; $i++) {
        $d = $today->modify('+'.$i.' day');
        if (!in_array((int)$d->format('N'), $open, true)) continue;
        if (isset(french_public_holidays((int)$d->format('Y'))[$d->format('Y-m-d')])) continue;
        $out[] = $d->format('Y-m-d');
    }
    return $out;
}

function booking_capacity(): int { return max(1, (int)growth_get('booking_capacity')); }

/** Demandes déjà reçues par créneau : ['2026-11-12|matin' => 2, …]. */
function booking_taken(array $days): array
{
    if ($days === []) return [];
    try {
        $rows = db_fetch_all("SELECT preferred_date, preferred_slot, COUNT(*) AS n FROM quotes
                              WHERE request_type = 'rdv' AND status <> 'annulé' AND preferred_date BETWEEN ? AND ?
                              GROUP BY preferred_date, preferred_slot", [min($days), max($days)]);
    } catch (Throwable $e) { return []; }
    $out = [];
    foreach ($rows as $r) $out[(string)$r['preferred_date'].'|'.(string)$r['preferred_slot']] = (int)$r['n'];
    return $out;
}

/** Vérifie un créneau envoyé par le formulaire. Retour : libellé lisible, ou null s'il n'est pas valable. */
function booking_validate(string $date, string $slot): ?string
{
    $slots = booking_slots();
    if (!isset($slots[$slot]) || !in_array($date, booking_days(), true)) return null;
    if ((booking_taken([$date])[$date.'|'.$slot] ?? 0) >= booking_capacity()) return null;
    return booking_day_label($date).' — '.$slots[$slot];
}

function booking_day_label(string $ymd): string
{
    $t = strtotime($ymd);
    if ($t === false) return $ymd;
    $jours = ['', 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi', 'dimanche'];
    $mois  = ['', 'janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];
    return $jours[(int)date('N', $t)].' '.(int)date('j', $t).' '.$mois[(int)date('n', $t)];
}
