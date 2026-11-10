<?php
declare(strict_types=1);

/* ═══════════════════════════════════════════════════
   CORE UTILITIES
═══════════════════════════════════════════════════ */
function boot_session(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_set_cookie_params([
            'secure'   => true,
            'httponly' => true,
            // Lax (et non Strict) : un lien ouvert depuis un e-mail, un SMS ou une
            // notification doit trouver l'utilisateur toujours connecté.
            // Les formulaires restent protégés par leur jeton CSRF.
            'samesite' => 'Lax',
        ]);
        session_start();
    }
}

function e(mixed $v): string
{
    return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
}

function app_config(): array
{
    static $c = null;
    if ($c === null) {
        $p = __DIR__ . '/../config/config.php';
        $c = file_exists($p) ? require $p : [];
    }
    return $c;
}

function app_installed(): bool { return (bool)(app_config()['installed'] ?? false); }

function site_base_url(): string { return rtrim((string)(app_config()['site']['base_url'] ?? ''), '/'); }

function base_path(): string
{
    $b = site_base_url();
    if ($b !== '') { $p = parse_url($b, PHP_URL_PATH) ?: ''; return rtrim((string)$p, '/'); }
    $s = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
    foreach (['/admin', '/tech', '/dispatcher'] as $_strip) {
        if (str_ends_with($s, $_strip)) { $s = substr($s, 0, -strlen($_strip)); break; }
    }
    return ($s === '/' || $s === '.' || $s === '\\') ? '' : rtrim($s, '/');
}

function url_for(string $path = ''): string
{
    $b = base_path();
    $c = '/' . ltrim($path, '/');
    return ($c === '/' || $c === '') ? ($b !== '' ? $b : '') . '/' : $b . $c;
}

function asset_url(string $path): string { return url_for($path); }

/** Adresse complète (https://hôte/…) à partir d'une adresse relative au site. */
function absolute_url(string $url): string
{
    if ($url === '' || preg_match('#^https?://#i', $url)) return $url;
    $b = site_base_url();
    $origin = $b !== ''
        ? (parse_url($b, PHP_URL_SCHEME) ?: 'https').'://'.parse_url($b, PHP_URL_HOST)
        : 'https://'.($_SERVER['HTTP_HOST'] ?? 'localhost');
    return $origin.'/'.ltrim($url, '/');
}

function route_url(string $slug = ''): string
{
    // En contexte zone, toute la navigation reste dans la zone : /paris-ile-de-france/zones
    $z = zone_ctx_slug();
    if ($z !== '') {
        return url_for($z . (($slug === '' || $slug === 'home') ? '/' : '/' . rawurlencode($slug)));
    }
    if ($slug === '' || $slug === 'home') return url_for('index.php');
    return url_for('index.php?route=' . rawurlencode($slug));
}

function current_year(): string { return date('Y'); }

function redirect_to(string $path): never
{
    if (!preg_match('#^(https?:|tel:|mailto:)#i', $path)) {
        // Ne pas re-préfixer un chemin déjà absolu depuis la racine du site.
        $b = base_path();
        $already = $b !== '' ? str_starts_with($path, $b.'/') : str_starts_with($path, '/');
        if (!$already) $path = url_for($path);
    }
    header('Location: ' . $path);
    exit;
}

function csrf_token(): string
{
    boot_session();
    if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    return (string)$_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="'.e(csrf_token()).'">';
}

function verify_csrf(): void
{
    boot_session();
    $t = (string)($_POST['csrf_token'] ?? '');
    if (!hash_equals((string)($_SESSION['csrf_token'] ?? ''), $t)) { http_response_code(419); exit('Token CSRF invalide'); }
}

function flash(string $key, ?string $msg = null): ?string
{
    boot_session();
    if ($msg !== null) { $_SESSION['flash'][$key] = $msg; return null; }
    $v = $_SESSION['flash'][$key] ?? null;
    unset($_SESSION['flash'][$key]);
    return $v;
}

function rate_limit_passed(string $name, int $seconds = 10): bool
{
    boot_session();
    $k = 'rl_' . $name;
    $l = (int)($_SESSION[$k] ?? 0);
    if ((time() - $l) < $seconds) return false;
    $_SESSION[$k] = time();
    return true;
}

/* ═══════════════════════════════════════════════════
   LOGIN RATE LIMITING (protection anti brute-force)
   Compte les échecs par identifiant ET par IP sur une
   fenêtre glissante, tous espaces de connexion confondus
   (admin / tech / dispatcher partagent le même mécanisme).
═══════════════════════════════════════════════════ */
const LOGIN_MAX_ATTEMPTS    = 5;
const LOGIN_LOCKOUT_SECONDS = 900; // 15 minutes

function client_ip(): string
{
    return (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
}

/** true si l'identifiant OU l'IP a dépassé le nombre d'échecs autorisés récemment. */
function login_is_locked(string $scope, string $identifier): bool
{
    try {
        // La durée est une constante du code, jamais une saisie : on l'insère
        // directement. Passée en paramètre, elle casserait la requête si les
        // requêtes préparées natives étaient un jour activées — et l'échec
        // serait avalé par le catch, désactivant le blocage sans prévenir.
        $row = db_fetch(
            "SELECT COUNT(*) AS n FROM login_attempts
             WHERE scope = ? AND success = 0
             AND created_at > (NOW() - INTERVAL ".(int)LOGIN_LOCKOUT_SECONDS." SECOND)
             AND (identifier = ? OR ip = ?)",
            [$scope, $identifier, client_ip()]
        );
    } catch (Throwable $e) { return false; }
    return (int) ($row['n'] ?? 0) >= LOGIN_MAX_ATTEMPTS;
}

/** Journalise une tentative ; un succès efface l'historique d'échecs de cet identifiant/IP. */
function login_record_attempt(string $scope, string $identifier, bool $success): void
{
    try {
        db_execute(
            'INSERT INTO login_attempts (scope, identifier, ip, success) VALUES (?, ?, ?, ?)',
            [$scope, $identifier, client_ip(), $success ? 1 : 0]
        );
        if ($success) {
            db_execute(
                'DELETE FROM login_attempts WHERE scope = ? AND (identifier = ? OR ip = ?) AND success = 0',
                [$scope, $identifier, client_ip()]
            );
        }
        // Purge occasionnelle : sans elle, la table grossit indéfiniment sous
        // les tentatives de robots. Une fois sur cinquante suffit largement.
        if (random_int(1, 50) === 1) {
            db_execute('DELETE FROM login_attempts WHERE created_at < (NOW() - INTERVAL 7 DAY)');
        }
    } catch (Throwable $e) {}
}

/* ═══════════════════════════════════════════════════
   CONTEXTE ZONE
   Quand une zone est active, les réglages sont lus et
   écrits sous la clé "z:{slug}:{clé}". Si la zone n'a
   pas de valeur propre, on retombe sur la valeur
   globale : c'est le mécanisme d'héritage.
═══════════════════════════════════════════════════ */
function zone_context(?array $zone = null, bool $write = false): ?array
{
    static $ctx = null;
    if ($write) $ctx = $zone;
    return $ctx;
}

function set_zone_context(?array $zone): void { zone_context($zone, true); }
function zone_ctx_slug(): string { $z = zone_context(); return $z ? (string)($z['slug'] ?? '') : ''; }
function zone_ctx_id(): int      { $z = zone_context(); return $z ? (int)($z['id'] ?? 0) : 0; }
function zone_ctx_name(): string { $z = zone_context(); return $z ? (string)($z['name'] ?? '') : ''; }

function zone_setting_key(string $key): string
{
    $s = zone_ctx_slug();
    return $s === '' ? $key : 'z:'.$s.':'.$key;
}

/* ═══════════════════════════════════════════════════
   SETTINGS
═══════════════════════════════════════════════════ */
function settings_cache(bool $flush = false): array
{
    static $cache = null;
    if ($flush) { $cache = null; return []; }
    if ($cache === null) {
        $cache = [];
        try {
            if (function_exists('db_fetch_all')) {
                foreach (db_fetch_all('SELECT setting_key, setting_value FROM settings') as $row) {
                    $cache[(string)$row['setting_key']] = (string)($row['setting_value'] ?? '');
                }
            }
        } catch (Throwable $e) { $cache = []; }
    }
    return $cache;
}

function setting(string $key, ?string $fallback = null): string
{
    $cache = settings_cache();
    $zk = zone_setting_key($key);
    $out = null;
    if ($zk !== $key) {
        $zv = $cache[$zk] ?? null;
        if ($zv !== null && $zv !== '') $out = $zv;
    }
    if ($out === null) {
        $v = $cache[$key] ?? null;
        $out = ($v === null || $v === '') ? (string)($fallback ?? '') : $v;
    }
    // Les variables de lieu se résolvent ici, donc dans tous les textes du site.
    // Les valeurs des variables elles-mêmes en sont exclues : elles ne doivent
    // pas s'auto-substituer.
    if (function_exists('zone_vars_apply') && !str_starts_with($key, 'zvar_')) {
        $out = zone_vars_apply($out);
    }
    // En mode édition visuelle, le texte est balisé pour devenir cliquable.
    return function_exists('inline_edit_wrap') ? inline_edit_wrap($key, $out) : $out;
}

/**
 * Valeur réellement enregistrée pour le contexte courant, sans aucun repli.
 * Vide signifie « rien de saisi ici » : le site affichera la valeur héritée
 * (celle du global en zone, sinon le texte par défaut du code).
 */
function raw_setting(string $key): string
{
    return settings_cache()[zone_setting_key($key)] ?? '';
}

/** Valeur globale du réglage, en ignorant la zone active. */
function global_setting(string $key, ?string $fallback = null): string
{
    $v = settings_cache()[$key] ?? null;
    return ($v === null || $v === '') ? (string)($fallback ?? '') : $v;
}

/** true si la zone active n'a pas de valeur propre et hérite donc du global. */
function setting_is_inherited(string $key): bool
{
    $zk = zone_setting_key($key);
    if ($zk === $key) return false;
    $zv = settings_cache()[$zk] ?? null;
    return ($zv === null || $zv === '');
}

function site_setting(string $key, ?string $fallback = null): string { return setting($key, $fallback); }

function set_setting(string $key, mixed $value): void
{
    $s = is_scalar($value) || $value === null ? (string)$value : json_encode($value, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    $k = zone_setting_key($key);
    // En contexte zone, vider un champ supprime la surcharge : la zone hérite à nouveau du global.
    if ($k !== $key && $s === '') {
        db_execute('DELETE FROM settings WHERE setting_key = ?', [$k]);
        settings_cache(true);
        return;
    }
    $exists = db_fetch('SELECT id FROM settings WHERE setting_key = ?', [$k]);
    if ($exists) db_execute('UPDATE settings SET setting_value = ? WHERE setting_key = ?', [$s, $k]);
    else db_execute('INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)', [$k, $s]);
    settings_cache(true);
}

/** Recopie toutes les valeurs globales dans la zone active. Retourne le nombre de champs copiés. */
function zone_copy_from_global(): int
{
    $slug = zone_ctx_slug();
    if ($slug === '') return 0;
    $n = 0;
    foreach (settings_cache() as $k => $v) {
        if ($v === '' || str_starts_with($k, 'z:')) continue;
        $zk = 'z:'.$slug.':'.$k;
        $exists = db_fetch('SELECT id FROM settings WHERE setting_key = ?', [$zk]);
        if ($exists) db_execute('UPDATE settings SET setting_value = ? WHERE setting_key = ?', [$v, $zk]);
        else db_execute('INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)', [$zk, $v]);
        $n++;
    }
    settings_cache(true);
    return $n;
}

/**
 * Supprime les textes d'une zone qui sont identiques au site global.
 *
 * Après un « Dupliquer depuis Global », une zone détient une copie figée de
 * tout le site : elle lui ressemble en tout point et cesse d'hériter, si
 * bien qu'une correction faite ensuite en global ne l'atteint plus. Ce
 * nettoyage ne conserve que les vraies différences.
 */
function zone_prune_identical(string $slug): int
{
    if ($slug === '') return 0;
    $prefixe = 'z:'.$slug.':';
    $cache   = settings_cache();
    $n = 0;
    foreach ($cache as $k => $v) {
        if (!str_starts_with($k, $prefixe)) continue;
        $base = substr($k, strlen($prefixe));
        if (($cache[$base] ?? null) !== $v) continue;   // différence réelle : on garde
        db_execute('DELETE FROM settings WHERE setting_key = ?', [$k]);
        $n++;
    }
    if ($n > 0) { settings_cache(true); admin_inline_keys(true); }
    return $n;
}

/** Nombre de textes d'une zone réellement différents du site global. */
function zone_real_differences(string $slug): int
{
    if ($slug === '') return 0;
    $prefixe = 'z:'.$slug.':';
    $cache   = settings_cache();
    $n = 0;
    foreach ($cache as $k => $v) {
        if (!str_starts_with($k, $prefixe)) continue;
        if (($cache[substr($k, strlen($prefixe))] ?? null) !== $v) $n++;
    }
    return $n;
}

/** Supprime toutes les surcharges de la zone active : retour à l'héritage total. */
function zone_reset_overrides(): int
{
    $slug = zone_ctx_slug();
    if ($slug === '') return 0;
    $n = 0;
    foreach (array_keys(settings_cache()) as $k) {
        if (str_starts_with($k, 'z:'.$slug.':')) { db_execute('DELETE FROM settings WHERE setting_key = ?', [$k]); $n++; }
    }
    settings_cache(true);
    return $n;
}

/** Nombre de champs personnalisés par une zone donnée. */
function zone_override_count(string $slug): int
{
    if ($slug === '') return 0;
    $n = 0;
    foreach (array_keys(settings_cache()) as $k) { if (str_starts_with($k, 'z:'.$slug.':')) $n++; }
    return $n;
}

/* Filtrage des contenus (pages, réalisations, avis) selon la zone active. */
function zone_rows_ready(): bool
{
    static $ok = null;
    if ($ok === null) {
        $ok = true;
        foreach (['pages','realisations','reviews'] as $t) {
            try { db_fetch("SELECT zone_id FROM {$t} LIMIT 1"); }
            catch (Throwable $e) { $ok = false; break; }
        }
    }
    return $ok;
}

/** Clause SQL restreignant aux contenus de la zone active + ceux marqués « toutes les zones ». */
function zone_rows_where(string $col = 'zone_id'): string
{
    $id = zone_ctx_id();
    if ($id <= 0 || !zone_rows_ready()) return '';
    return " AND ({$col} IS NULL OR {$col} = {$id})";
}

function set_site_setting(string $key, mixed $value): void { set_setting($key, $value); }

function setting_bool(string $key, bool $fallback = false): bool
{
    return in_array(strtolower(setting($key, $fallback ? '1' : '0')), ['1','true','yes','on'], true);
}

function get_json_setting(string $key, array $fallback = []): array
{
    // Lecture brute : un bloc JSON contient des accolades, il ne doit pas
    // traverser la substitution de variables avant d'être décodé.
    $d = json_decode(setting_plain($key), true);
    $d = is_array($d) ? $d : $fallback;
    // Les variables s'appliquent ensuite à chaque texte du bloc.
    return function_exists('zone_vars_apply_deep') ? zone_vars_apply_deep($d) : $d;
}

function set_json_setting(string $key, array $value): void
{
    set_setting($key, json_encode($value, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
}

function css_value(string $value, string $fallback = ''): string
{
    $v = trim($value);
    if ($v === '') return $fallback;
    if ($v === 'auto') return 'auto';
    if (preg_match('/^-?\d+(\.\d+)?$/', $v)) return $v . 'px';
    if (preg_match('/^-?\d+(\.\d+)?(px|rem|em|%|vh|vw)$/', $v)) return $v;
    return $fallback;
}

/* ═══════════════════════════════════════════════════
   COMPANY INFO
═══════════════════════════════════════════════════ */
function company_name(): string { return setting('company_name', 'EMAE'); }
function company_phone(): string { return setting('company_phone', '06 67 83 03 76'); }
function company_phone_link(): string { return setting('company_phone_link', 'tel:+33667830376'); }
function company_email(): string { return setting('company_email', 'contact@emae.fr'); }
function company_regions(): string { return setting('company_regions', '{region}'); }
function company_hours(): string { return setting('company_hours', '24h/24 — 7j/7'); }
function company_address(): string { return setting('company_address', '{region}'); }
function company_siret(): string { return setting('company_siret', ''); }
function company_slogan(): string { return setting('company_slogan', 'Dépannage & installation multitechnique'); }
function company_whatsapp(): string { return setting('company_whatsapp', ''); }
/* ── Informations publiées qui doivent rester identiques partout ──
   Réglages globaux (jamais par zone), modifiables dans Identité & coordonnées. */

/** Délai de rappel annoncé (« 5 minutes », décision du propriétaire du 8 novembre 2026). Vide = aucun chiffre publié. */
function callback_delay(): string
{
    // Réglage absent : la valeur décidée ; réglage vidé dans l'admin : aucun chiffre.
    $c = settings_cache();
    return array_key_exists('callback_delay', $c) ? trim((string)$c['callback_delay']) : '5 minutes';
}

/** « Rappel sous 5 minutes », ou « Rappel rapide » si aucun délai n'est publié. */
function callback_promise_short(): string { $d = callback_delay(); return $d !== '' ? 'Rappel sous '.$d : 'Rappel rapide'; }

/** Phrase unique sur la disponibilité et le délai d'arrivée : aucun délai chiffré sans donnée réelle. */
function intervention_terms_text(): string
{
    $v = trim(global_setting('intervention_terms_text', ''));
    return $v !== '' ? $v : 'Disponibilité, délai estimé et conditions d\'intervention confirmés par téléphone avant déplacement.';
}

/** Identité légale (mentions légales, données structurées). Valeurs vérifiées à l'Annuaire des entreprises le 10/11/2026. */
function legal_info(): array
{
    $g = static fn(string $k, string $d = ''): string => trim(global_setting($k, $d));
    return [
        'denomination' => $g('ml_denomination', 'ENTREPRISE MULTI-TECHNIQUE AVANCEE (EMAE)'),
        'forme'        => $g('ml_forme', 'SARL'),
        'capital'      => $g('ml_capital', '1 000 €'),
        'siege'        => $g('ml_siege', '60 rue François Ier, 75008 Paris'),
        'siren'        => $g('ml_siren', '102 802 600'),
        'rcs'          => $g('ml_rcs'),          // ex. « RCS Paris » : à reprendre du Kbis
        'tva'          => $g('ml_tva'),          // numéro de TVA intracommunautaire, s'il existe
        'directeur'    => $g('ml_directeur'),    // personne physique (nom et prénom)
        'base'         => $g('ml_base', 'Dole (Jura)'),
        'mediateur'    => $g('ml_mediateur'),    // nom et site du médiateur de la consommation
        'assurance'    => $g('ml_assurance'),    // assureur et couverture géographique
        'updated_at'   => $g('ml_updated_at'),
    ];
}

/**
 * Note et nombre d'avis publiables : uniquement ceux de la fiche Google, avec son lien,
 * et la date de mise à jour. Sinon null : aucun chiffre affiché (ni « avis vérifiés »).
 * @return array{rating:float,count:int,url:string,updated:string}|null
 */
function review_summary(): ?array
{
    if (!function_exists('google_rating')) return null;
    $g = google_rating();
    if ($g === null) return null;
    $u = trim(global_setting('google_rating_updated_at', ''));
    return $g + ['updated' => $u !== '' && strtotime($u) ? date('d/m/Y', strtotime($u)) : ''];
}

/**
 * Une affirmation commerciale non documentée ? (note ou nombre d'avis sans source,
 * « 1000+ interventions », délai d'arrivée chiffré, « garanti », certification, « technicien
 * disponible »). Le délai de rappel réglé (callback_delay) reste permis quand le texte parle
 * de rappel ou de réponse. Sert de filet de sécurité à l'affichage : un ancien réglage
 * resté en base ne republie pas une promesse retirée.
 */
function claim_unsupported(string $t): bool
{
    $t = html_entity_decode(strip_tags($t), ENT_QUOTES, 'UTF-8');
    if (trim($t) === '') return false;
    $rx = [
        '/\b\d[.,]\d\s*(\/\s*5|sur\s*5|★|étoiles?)/iu',                         // 4,6/5
        '/\bavis\s+(clients?\s+)?v[ée]rifi/iu',                                   // « avis vérifiés »
        '/\b\d{3,}\s*\+|\bplus de\s+\d{2,}\s*(interventions|clients|avis|chantiers)/iu', // 1000+, plus de 120 avis
        '/(moins\s+d[\'’e ]\s*|sous\s+|en\s+|<\s*|inf[ée]rieur\s+[àa]\s+)(une?\s+|1\s*|2\s*|3\s*|\d+\s*)(h\b|heures?\b)/iu', // délai d'arrivée en heures
        '/\b(certifi[ée]e?s?|RGE|Qualifelec|Qualibat|QualiPAC|Qualigaz)\b/u',
        '/\b(rappel|d[ée]lai|intervention|r[ée]ponse)[^.!?]{0,40}\bgaranti/iu',
        '/technicien\s+disponible/iu',
        '/[ée]quipes?\s+r[ée]parties/iu',
    ];
    foreach ($rx as $r) if (preg_match($r, $t)) return true;
    // Minutes : seulement le délai de rappel réglé, dans une phrase sur le rappel ou la réponse.
    if (preg_match_all('/(\d+)\s*(min\b|mins\b|minutes?\b)/iu', $t, $m)) {
        $ok = (int)callback_delay() > 0 && preg_match('/rappel|rappelons|r[ée]pon/iu', $t);
        foreach ($m[1] as $n) if (!$ok || (int)$n !== (int)callback_delay()) return true;
    }
    return false;
}

/** Le texte, ou le texte de repli si le premier contient une promesse non documentée. */
function claim_clean(string $text, ?string $fallback = null): string
{
    return claim_unsupported($text) ? ($fallback ?? intervention_terms_text()) : $text;
}

/** Horaires publiés, distincts selon le type de demande (réglable dans Identité & coordonnées). */
function hours_text(): string
{
    $v = trim(global_setting('hours_text', ''));
    if ($v !== '') return $v;
    return 'Urgences : 24h/24 et 7j/7. Interventions planifiées : créneaux '.(function_exists('booking_weekdays_label') ? booking_weekdays_label() : 'du lundi au samedi')
         .', confirmés par téléphone.';
}

/**
 * Réponse de FAQ cohérente avec le reste du site :
 * - zones : un texte qui cite un autre secteur que celui de la page est remplacé par le secteur réel ;
 * - horaires : la phrase unique hours_text() ;
 * - délai : pas de délai chiffré sans donnée réelle.
 */
function faq_answer_fix(string $q, string $a): string
{
    $qn = function_exists('text_ascii_lower') ? text_ascii_lower($q) : mb_strtolower($q);
    $profil = function_exists('geo_profil_global') ? (zone_context() ? (geo_profil_zone(zone_context()) ?? geo_profil_global()) : geo_profil_global()) : null;
    if (preg_match('/zone|secteur|departement|ou intervenez|quelles villes/', $qn) && $profil
        && geo_hors_secteur($a, $profil)['etrangers']) {
        return geo_replace('Nous intervenons {en_region}, notamment autour de {ville}. Contactez-nous pour vérifier que votre adresse est dans notre secteur.');
    }
    if (preg_match('/horaire/', $qn)) return hours_text();
    if (preg_match('/delai/', $qn) && claim_unsupported($a)) {
        return intervention_terms_text().' En cas d\'urgence, appelez le '.company_phone().'.';
    }
    return claim_clean($a);
}

/**
 * Texte libre d'une page (saisi dans l'admin) sans les ressources tierces qu'un copier-coller
 * y a laissées : feuille Google Fonts, scripts externes. Elles transmettraient l'adresse IP du
 * visiteur à un tiers avant tout consentement. Le texte enregistré n'est pas modifié.
 */
function page_html_without_third_party(string $html): string
{
    return (string)preg_replace([
        '#<link\b[^>]*\bhref=["\']?https?://[^"\'>]*(fonts\.googleapis|fonts\.gstatic|googletagmanager|google-analytics|doubleclick)[^>]*>#i',
        '#<script\b[^>]*\bsrc=["\']?https?://[^>]*>\s*</script>#i',
    ], '', $html);
}

/** Lien vers la fiche Google publique (avis), s'il est renseigné. */
function google_profile_url(): string
{
    foreach ([global_setting('google_mybusiness_url', ''), function_exists('growth_get') ? growth_get('google_reviews_url') : ''] as $u) {
        $u = trim((string)$u);
        if (preg_match('#^https://#i', $u)) return $u;
    }
    return '';
}

function site_logo_path(): string { return setting('site_logo', 'storage/uploads/logos/logo-emae-default.svg'); }
function site_logo_url(): string { return asset_url(site_logo_path()); }
/** Le logo choisi est-il le logo EMAE d'origine ? Un autre logo envoyé dans l'admin s'affiche tel quel. */
function site_logo_is_emae(string $logo): bool
{
    return in_array(basename($logo), ['20260412174717-7d1ee290.png', '20260408005456-a088045a.png',
        '20260407130649-bf10931c.png', 'logo-emae.png', 'logo-emae-default.svg'], true);
}
function site_logo_width(): string { return css_value(setting('site_logo_width', '180'), '180px'); }
function site_logo_height(): string { return css_value(setting('site_logo_height', 'auto'), 'auto'); }
function site_logo_position(): string { $p = setting('site_logo_position', 'left'); return in_array($p, ['left','center','right']) ? $p : 'left'; }

/* ═══════════════════════════════════════════════════
   THEME & STYLES
═══════════════════════════════════════════════════ */


/**
 * Une seule entité pour tout le site (même @id sur chaque page, zones comprises).
 * - pas d'adresse postale : le siège parisien n'accueille pas de public et la base de
 *   Dole n'a pas d'adresse confirmée ; areaServed décrit le secteur couvert ;
 * - pas de note (aggregateRating) : des avis sur sa propre entreprise ne donnent pas
 *   droit aux étoiles dans Google, et aucune note sourcée n'est enregistrée ;
 * - sameAs : seulement la fiche Google publique, si elle est renseignée.
 */
function schema_local_business(): string
{
    $origin = rtrim(absolute_url('/'), '/');
    $legal  = legal_info();
    $tel    = preg_replace('/^tel:/i', '', company_phone_link());
    // Secteur réel : départements du site global, ou de la zone affichée (déduits de ses codes postaux).
    $profil = function_exists('geo_profil_global') ? (zone_context() && function_exists('geo_profil_zone') ? (geo_profil_zone(zone_context()) ?? geo_profil_global()) : geo_profil_global()) : null;
    $areas  = $profil ? array_values(array_map(static fn(string $c): string => geo_departements()[$c][0], $profil['codes'])) : [];
    $data = [
        '@context'    => 'https://schema.org',
        '@type'       => 'ElectricalContractor',
        '@id'         => $origin.'/#entreprise',
        'name'        => company_name(),
        'legalName'   => $legal['denomination'],
        'url'         => $origin.'/',
        'telephone'   => preg_match('/^\+?\d{9,15}$/', (string)$tel) ? $tel : company_phone(),
        'email'       => company_email(),
        'description' => setting('company_description', 'Entreprise multitechnique — dépannage, installation, entretien en électricité, plomberie, chauffage et climatisation.'),
        'areaServed'  => array_map(static fn(string $a): array => ['@type' => 'AdministrativeArea', 'name' => $a], $areas),
    ];
    // Urgences 24h/24 et 7j/7 : publié seulement si les horaires affichés le disent.
    if (preg_match('/24\s*h?\s*\/\s*24|24\/7/u', company_hours())) {
        $data['openingHoursSpecification'] = [[
            '@type' => 'OpeningHoursSpecification',
            'dayOfWeek' => ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'],
            'opens' => '00:00', 'closes' => '23:59',
        ]];
    }
    $siret = preg_replace('/\D/', '', company_siret());
    if (strlen((string)$siret) === 14) $data['identifier'] = ['@type' => 'PropertyValue', 'propertyID' => 'SIRET', 'value' => $siret];
    if (($g = google_profile_url()) !== '') $data['sameAs'] = [$g];
    if (($logo = site_logo_path()) !== '' && file_exists(__DIR__.'/../'.$logo)) $data['logo'] = absolute_url(asset_url($logo));
    return json_encode($data, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_HEX_TAG);
}

/* ═══════════════════════════════════════════════════
   NAV & SEO
═══════════════════════════════════════════════════ */
function nav_items(): array
{
    return [
        ['label'=>setting('nav_home','Accueil'),           'url'=>route_url('')],
        ['label'=>setting('nav_services','Services'),      'url'=>route_url('services')],
        ['label'=>setting('nav_zones','Nos zones'),        'url'=>route_url('zones')],
        ['label'=>setting('nav_avis','Avis clients'),      'url'=>route_url('avis')],
        ['label'=>setting('nav_realisations','Réalisations'), 'url'=>route_url('realisations')],
        ...(tarifs_enabled() ? [['label'=>setting('nav_tarifs','Tarifs'), 'url'=>route_url('tarifs')]] : []),
        ['label'=>setting('nav_faq','FAQ'),                'url'=>route_url('faq')],
        ['label'=>setting('nav_contact','Contact'),        'url'=>route_url('contact')],
    ];
}

/* ═══════════════════════════════════════════════════
   TARIFS
═══════════════════════════════════════════════════ */

/**
 * La page Tarifs est-elle publiée ? Réglage unique pour tout le site : on lit
 * la valeur globale, pour qu'une copie « Dupliquer depuis Global » faite
 * dans une zone ne fige pas l'état de la page dans cette zone.
 */
function tarifs_enabled(): bool
{
    return global_setting('tarifs_enabled', '0') === '1';
}

/**
 * Lignes d'origine de chaque grille : [prestation, prix, précision].
 * Aucun prix n'est prérempli : un prix vide s'affiche « Sur devis ».
 */
function tarifs_default_rows(): array
{
    $rows = [
        // Lignes exigées pour un dépannage (arrêté du 24 janvier 2017) : déplacement, taux horaire TTC
        // et son décompte, majorations avec leurs horaires. Vides = « Sur devis » tant que le prix
        // n'est pas validé : aucun montant n'est inventé.
        'base'          => ['Frais de déplacement','Diagnostic','Main-d\'œuvre : taux horaire TTC et mode de décompte','Majoration soir, dimanche et jours fériés (horaires et taux)','Établissement du devis'],
        'electricite'   => ['Dépannage électrique','Remplacement de tableau électrique','Ajout de prise ou de circuit','Mise aux normes NF C 15-100','Rénovation électrique complète'],
        'plomberie'     => ['Recherche et réparation de fuite','Débouchage de canalisation','Remplacement de robinetterie','Remplacement de chauffe-eau','Entretien préventif annuel'],
        'chauffage'     => ['Dépannage de chaudière','Entretien annuel de chaudière','Entretien de pompe à chaleur','Installation de chaudière ou de pompe à chaleur'],
        'climatisation' => ['Dépannage de climatiseur','Entretien saisonnier','Recharge en fluide frigorigène','Installation de climatisation'],
        'vmc'           => ['Dépannage de VMC','Nettoyage des bouches et des gaines','Entretien annuel et contrôle des débits','Installation de VMC simple ou double flux'],
        'portail'       => ['Dépannage de portail bloqué','Motorisation de portail','Installation de visiophone','Dépannage d\'interphone ou de digicode'],
        'automatisme'   => ['Dépannage de volet roulant','Motorisation de volet ou de store','Dépannage d\'électroménager'],
    ];
    return array_map(static fn(array $labels): array => array_map(static fn(string $l): array => [$l, '', ''], $labels), $rows);
}

/** Grilles à afficher, une par métier, avec les lignes propres à la zone si elle en a. */
function tarifs_groups(): array
{
    $defaults = tarifs_default_rows();
    $trades   = service_trade_defaults();
    $out = [];
    foreach (array_keys($defaults) as $trade) {
        $rows = json_decode(setting_plain('tarifs_'.$trade), true);
        $out[] = [
            'label' => $trade === 'base' ? 'Déplacement et main-d\'œuvre' : service_template($trade)['label'],
            'icon'  => $trade === 'base' ? '🧾' : $trades[$trade]['icon_svc'],
            'rows'  => (is_array($rows) && $rows !== []) ? $rows : $defaults[$trade],
        ];
    }
    return $out;
}

function seo_defaults(string $route = 'home', ?array $page = null): array
{
    if ($page) return [
        'title'       => $page['meta_title'] ?: ($page['title'].' | '.company_name()),
        'description' => $page['meta_description'] ?: ($page['excerpt'] ?: company_name()),
        'canonical'   => route_url($page['slug']),
    ];
    if ($route === 'home' || $route === '') return [
        'title'       => setting('home_meta_title',       company_name().' | Dépannage multitechnique 24h/24'),
        'description' => setting('home_meta_description', 'Dépannage urgence, électricité, plomberie, chauffage, climatisation {en_region}. Devis gratuit, intervention rapide.'),
        'canonical'   => route_url(''),
    ];
    return ['title'=>company_name(),'description'=>company_name(),'canonical'=>route_url($route)];
}

function quote_form_options(): array
{
    return [
        'submit_label'   => setting('form_submit_label',   'Envoyer ma demande'),
        'success_message'=> setting('form_success_message','Votre demande a bien été envoyée. Nous vous recontactons rapidement.'),
        'mail_to'        => setting('form_email_to',        company_email()),
    ];
}
/* ═══════════════════════════════════════════════════
   DEMANDES DU SITE : RAPPEL OU DEVIS, PHOTOS DU CLIENT
   Les photos montrent l'intérieur des logements : elles restent dans un dossier
   fermé au public et ne s'ouvrent que par admin/quote_photo.php, une fois connecté.
═══════════════════════════════════════════════════ */
const QUOTE_PHOTOS_MAX    = 3;
const QUOTE_PHOTO_MAX_MB  = 10;
const QUOTE_MAIL_ATTACH_MAX_MB = 10;

/** rappel, devis, rdv (rendez-vous en ligne) ou entretien (contrat d'entretien) ; tout le reste = devis. */
function quote_request_type(string $raw): string { return in_array($raw, ['rappel', 'rdv', 'entretien'], true) ? $raw : 'devis'; }

/** Présentation de chaque type : expéditeur du mail, couleur, bandeau, début d'objet, étiquette. */
function quote_type_info(string $type): array
{
    return [
        'rappel'    => ['Rappel',      '#F07B1D', '📞 DEMANDE DE RAPPEL — le client attend votre appel', '📞 À RAPPELER — ', '📞 Rappel',      '#fff3e0', '#b84700'],
        'rdv'       => ['Rendez-vous', '#0F766E', '📅 DEMANDE DE RENDEZ-VOUS — créneau à confirmer au client', '📅 RENDEZ-VOUS — ', '📅 Rendez-vous', '#e6f6f4', '#0f5e57'],
        'entretien' => ['Entretien',   '#7C3AED', '🔧 DEMANDE DE CONTRAT D\'ENTRETIEN', '🔧 ENTRETIEN — ', '🔧 Entretien',   '#f1eafe', '#5b21b6'],
        'devis'     => ['Devis',       '#1D4ED8', '📋 DEMANDE DE DEVIS', '📋 DEVIS — ', '📋 Devis',      '#e8f0ff', '#1a3baa'],
    ][quote_request_type($type)];
}

/** Dossier privé des photos, créé fermé au public. */
function quote_photo_dir(): string
{
    $dir = __DIR__.'/../storage/uploads/demandes';
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    if (!is_file($dir.'/.htaccess')) @file_put_contents($dir.'/.htaccess', "Require all denied\n");
    // Témoin sans donnée : permet de vérifier depuis l'extérieur que le dossier est bien fermé.
    if (!is_file($dir.'/ferme.txt')) @file_put_contents($dir.'/ferme.txt', "Ce dossier doit rester inaccessible depuis le web.\n");
    return $dir;
}

/** Extension à donner au fichier, ou null si ce n'est pas une vraie image acceptée. */
function quote_photo_ext(string $path): ?string
{
    $mime = @mime_content_type($path) ?: '';
    $ext  = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'][$mime] ?? null;
    return ($ext !== null && @getimagesize($path) !== false) ? $ext : null;
}

/** Range les photos envoyées ($_FILES['photos']). Retour : noms enregistrés, nombre de refus. */
function quote_store_photos(array $f): array
{
    $out = ['files' => [], 'refused' => 0];
    if (!is_array($f['name'] ?? null)) return $out;
    foreach (array_keys($f['name']) as $i) {
        $err = (int)($f['error'][$i] ?? UPLOAD_ERR_NO_FILE);
        if ($err === UPLOAD_ERR_NO_FILE) continue;
        $tmp = (string)($f['tmp_name'][$i] ?? '');
        $ext = ($err === UPLOAD_ERR_OK
                && count($out['files']) < QUOTE_PHOTOS_MAX
                && (int)($f['size'][$i] ?? 0) <= QUOTE_PHOTO_MAX_MB * 1048576
                && is_uploaded_file($tmp)) ? quote_photo_ext($tmp) : null;
        $name = $ext !== null ? bin2hex(random_bytes(16)).'.'.$ext : '';
        if ($ext !== null && move_uploaded_file($tmp, quote_photo_dir().'/'.$name)) $out['files'][] = $name;
        else $out['refused']++;
    }
    return $out;
}

/** Noms des photos d'une demande (rien d'autre qu'un nom généré par le site n'est accepté). */
function quote_client_photos(array $q): array
{
    $list = json_decode((string)($q['client_photos'] ?? ''), true);
    if (!is_array($list)) return [];
    return array_values(array_filter($list, static fn($n): bool => is_string($n) && preg_match('/^[a-f0-9]{32}\.(jpg|png|webp)$/', $n) === 1));
}

function quote_delete_photos(array $q): void
{
    foreach (quote_client_photos($q) as $name) @unlink(__DIR__.'/../storage/uploads/demandes/'.$name);
}

/** Photos du client pour une intervention créée à partir d'une demande du site. */
function intervention_client_photos_html(array $iv): string
{
    if (empty($iv['quote_id'])) return '';
    try { $q = db_fetch('SELECT id, client_photos FROM quotes WHERE id = ?', [(int)$iv['quote_id']]); }
    catch (Throwable $e) { return ''; }
    return $q ? quote_photos_html($q) : '';
}

/** Vignettes cliquables des photos du client (admin, dispatcher et technicien). */
function quote_photos_html(array $q): string
{
    $html = '';
    foreach (quote_client_photos($q) as $n => $_) {
        $url = e(url_for('admin/quote_photo.php?id='.(int)$q['id'].'&n='.$n));
        $html .= '<a href="'.$url.'" target="_blank" rel="noopener" style="display:block;width:110px;height:110px;border-radius:8px;overflow:hidden;background:#f0f4ff;">'
               . '<img src="'.$url.'" alt="Photo envoyée par le client" loading="lazy" style="width:100%;height:100%;object-fit:cover;"></a>';
    }
    return $html === '' ? '' : '<div style="display:flex;gap:.5rem;flex-wrap:wrap;">'.$html.'</div>';
}

/** Étiquette Rappel / Devis ; rien pour les demandes antérieures, dont le type est inconnu. */
function quote_type_badge(array $q): string
{
    $t = (string)($q['request_type'] ?? '');
    if (!in_array($t, ['rappel', 'devis', 'rdv', 'entretien'], true)) return '';
    $i = quote_type_info($t);
    $slot = $t === 'rdv' && !empty($q['preferred_date']) ? '<br><small style="color:#0f5e57;">'.e(booking_day_label((string)$q['preferred_date']).(isset(booking_slots()[(string)($q['preferred_slot'] ?? '')]) ? ' — '.booking_slots()[(string)$q['preferred_slot']] : '')).'</small>' : '';
    return '<span style="display:inline-block;padding:.15rem .55rem;border-radius:6px;background:'.$i[5].';color:'.$i[6].';font-weight:700;font-size:.78rem;white-space:nowrap;">'.$i[4].'</span>'.$slot;
}

/**
 * Enveloppe du message : un simple HTML sans pièce jointe, sinon du multipart.
 * $files : [nom affiché => chemin]. Retour : [en-têtes de contenu, corps].
 */
function quote_mail_message(string $html, array $files): array
{
    if ($files === []) return ["Content-Type: text/html; charset=UTF-8\r\n", $html];
    $b = 'emae-'.bin2hex(random_bytes(12));
    $body = "--$b\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
          . chunk_split(base64_encode($html))."\r\n";
    foreach ($files as $name => $path) {
        $mime = ['jpg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'][pathinfo($name, PATHINFO_EXTENSION)] ?? 'application/octet-stream';
        $body .= "--$b\r\nContent-Type: $mime; name=\"$name\"\r\nContent-Transfer-Encoding: base64\r\n"
               . "Content-Disposition: attachment; filename=\"$name\"\r\n\r\n"
               . chunk_split(base64_encode((string)file_get_contents($path)))."\r\n";
    }
    return ["Content-Type: multipart/mixed; boundary=\"$b\"\r\n", $body."--$b--\r\n"];
}

function send_quote_notification(array $data): bool
{
    $to = setting('form_email_to', company_email());
    if (trim($to) === '') return false;
    $m  = quote_notification_compose($data);
    $ok = mail($to, $m['subject'], $m['body'], $m['headers']);
    // Un mail refusé à cause de ses pièces jointes ne doit pas faire perdre la demande.
    if (!$ok && $m['attached'] > 0) {
        $m  = quote_notification_compose($data, false);
        $ok = mail($to, $m['subject'], $m['body'], $m['headers']);
    }
    if (!$ok) error_log('[EMAE] send_quote_notification failed — to='.$to.' from='.company_email());
    return $ok;
}

/** Prépare le mail reçu par l'entreprise. Retour : subject, body, headers, html, attached. */
function quote_notification_compose(array $data, bool $attach = true): array
{
    $to = setting('form_email_to', company_email());
    $esc = static fn(string $v): string => htmlspecialchars(trim($v), ENT_QUOTES, 'UTF-8');
    $rawName    = trim($data['full_name']    ?? '');
    $rawEmail   = trim($data['email']        ?? '');
    $name       = $esc($data['full_name']    ?? '');
    $phone      = $esc($data['phone']        ?? '');
    $email      = filter_var($rawEmail, FILTER_VALIDATE_EMAIL) ? $esc($rawEmail) : '';
    $city       = $esc($data['city']         ?? '');
    $address    = $esc($data['address']      ?? '');
    $postalCode = $esc($data['postal_code']  ?? '');
    $service    = $esc($data['service_type'] ?? '');
    $message    = trim($data['message']      ?? '');
    $rawUrgency = trim($data['urgency']      ?? 'Normale');
    $urgency    = $esc($rawUrgency);
    $source     = $esc($data['source']       ?? '');
    $site    = company_name();
    $now     = date('d/m/Y à H:i');
    $quoteId = (int)($data['quote_id'] ?? 0);

    // Le type se lit partout : nom de l'expéditeur et objet dans la liste des mails, bandeau dans le mail.
    [$typeSender, $typeColor, $typeBanner, $subjectStart] = quote_type_info((string)($data['request_type'] ?? ''));
    $subjectTxt = $subjectStart.str_replace(["\r", "\n"], ' ', $rawName).' ('.str_replace(["\r", "\n"], ' ', $rawUrgency).')';
    if (trim((string)($data['slot_label'] ?? '')) !== '') {
        $typeBanner .= '<br><span style="font-size:15px;font-weight:400;">Créneau souhaité : <strong>'.$esc((string)$data['slot_label']).'</strong></span>';
    }

    // Photos : jointes au mail tant que le total reste raisonnable, sinon à voir sur la fiche.
    $attachments = [];
    $tooHeavy = 0;
    $weight = 0;
    $photos = array_values(array_filter((array)($data['photos'] ?? []), 'is_string'));
    foreach ($photos as $i => $file) {
        $path = __DIR__.'/../storage/uploads/demandes/'.basename($file);
        $size = is_file($path) ? (int)filesize($path) : 0;
        if ($attach && $size > 0 && $weight + $size <= QUOTE_MAIL_ATTACH_MAX_MB * 1048576) {
            $weight += $size;
            $attachments['photo-'.($i + 1).'.'.pathinfo($file, PATHINFO_EXTENSION)] = $path;
        } else {
            $tooHeavy++;
        }
    }
    $photoLines = [];
    if ($attachments !== []) $photoLines[] = count($attachments) > 1 ? '📷 <strong>'.count($attachments).' photos jointes</strong> à ce mail.' : '📷 <strong>1 photo jointe</strong> à ce mail.';
    if ($tooHeavy > 0)       $photoLines[] = '📷 '.($tooHeavy > 1 ? $tooHeavy.' photos à voir' : '1 photo à voir').' sur la fiche de la demande.';
    $refused = (int)($data['photos_refused'] ?? 0);
    if ($refused > 0)        $photoLines[] = '⚠️ '.($refused > 1 ? $refused.' photos refusées' : '1 photo refusée').' (format ou taille), à redemander au client si besoin.';
    $photoBlock = $photoLines === [] ? '' : '<tr><td style="padding:0 36px 24px;"><div style="background:#f8faff;border:1px solid #dde5f3;border-radius:6px;padding:12px 16px;font-size:14px;color:#222;line-height:1.7;">'.implode('<br>', $photoLines).'</div></td></tr>';
    $adminLink = site_base_url().($quoteId > 0 ? '/admin/dossier.php?id='.$quoteId : '/admin/quotes.php');

    $urgencyColor = ($urgency === 'Urgente' || $urgency === 'Urgence') ? '#c0392b' : '#1a7ab5';
    $urgencyBg    = ($urgency === 'Urgente' || $urgency === 'Urgence') ? '#fdf2f2' : '#f0f7ff';

    $subject = '=?UTF-8?B?'.base64_encode($subjectTxt).'?=';

    $html = '<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"></head><body style="margin:0;padding:0;background:#f4f6fb;font-family:Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f4f6fb;padding:32px 0;">
<tr><td align="center">
<table width="600" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,.08);">
  <tr><td style="background:#061029;padding:28px 36px;">
    <p style="margin:0;font-size:22px;font-weight:700;color:#ffffff;">'.$site.'</p>
  </td></tr>
  <tr><td style="background:'.$typeColor.';padding:16px 36px;font-size:17px;font-weight:700;color:#ffffff;">'.$typeBanner.'</td></tr>
  <tr><td style="padding:20px 36px 0;">
    <span style="display:inline-block;background:'.$urgencyBg.';color:'.$urgencyColor.';border:1px solid '.$urgencyColor.';border-radius:6px;padding:6px 14px;font-size:13px;font-weight:700;">⚡ Urgence : '.$urgency.'</span>
    <span style="display:inline-block;margin-left:10px;color:#888;font-size:13px;">'.$now.'</span>
  </td></tr>
  <tr><td style="padding:20px 36px;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background:#f8faff;border-radius:8px;overflow:hidden;">
      <tr><td colspan="2" style="padding:14px 18px;background:#e8edf8;font-size:11px;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:#3d5a99;">Coordonnées du client</td></tr>
      <tr>
        <td style="padding:12px 18px;font-size:13px;color:#555;width:160px;border-bottom:1px solid #eef0f7;">👤 Nom complet</td>
        <td style="padding:12px 18px;font-size:14px;font-weight:700;color:#061029;border-bottom:1px solid #eef0f7;">'.$name.'</td>
      </tr>
      <tr style="background:#fff;">
        <td style="padding:12px 18px;font-size:13px;color:#555;border-bottom:1px solid #eef0f7;">📞 Téléphone</td>
        <td style="padding:12px 18px;font-size:14px;font-weight:700;color:#F07B1D;border-bottom:1px solid #eef0f7;"><a href="tel:'.preg_replace('/[^0-9+]/','',$phone).'" style="color:#F07B1D;text-decoration:none;">'.$phone.'</a></td>
      </tr>
      '.($email !== '' ? '<tr>
        <td style="padding:12px 18px;font-size:13px;color:#555;border-bottom:1px solid #eef0f7;">✉️ Email</td>
        <td style="padding:12px 18px;font-size:14px;color:#061029;border-bottom:1px solid #eef0f7;"><a href="mailto:'.$email.'" style="color:#1a7ab5;">'.$email.'</a></td>
      </tr>' : '').'
      '.($address !== '' || $postalCode !== '' || $city !== '' ? '<tr>
        <td style="padding:12px 18px;font-size:13px;color:#555;border-bottom:1px solid #eef0f7;">📍 Adresse</td>
        <td style="padding:12px 18px;font-size:14px;color:#061029;border-bottom:1px solid #eef0f7;">'.($address !== '' ? $address.'<br>' : '').($postalCode !== '' ? $postalCode.' ' : '').($city !== '' ? $city : '').'</td>
      </tr>' : '').'
      '.($service !== '' ? '<tr>
        <td style="padding:12px 18px;font-size:13px;color:#555;border-bottom:1px solid #eef0f7;">🔧 Service</td>
        <td style="padding:12px 18px;font-size:14px;color:#061029;border-bottom:1px solid #eef0f7;">'.$service.'</td>
      </tr>' : '').'
      '.($source !== '' ? '<tr>
        <td style="padding:12px 18px;font-size:13px;color:#555;">📣 Source</td>
        <td style="padding:12px 18px;font-size:14px;color:#061029;">'.$source.'</td>
      </tr>' : '').'
    </table>
  </td></tr>
  <tr><td style="padding:0 36px 28px;">
    <p style="margin:0 0 10px;font-size:11px;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:#3d5a99;">💬 Message du client</p>
    <div style="background:#f8faff;border-left:4px solid #F07B1D;border-radius:6px;padding:16px 18px;font-size:14px;color:#222;line-height:1.7;">'.nl2br(htmlspecialchars($message, ENT_QUOTES)).'</div>
  </td></tr>
  '.$photoBlock.'
  <tr><td style="padding:0 36px 32px;text-align:center;">
    <a href="'.$adminLink.'" style="display:inline-block;background:#061029;color:#ffffff;text-decoration:none;padding:14px 32px;border-radius:8px;font-size:14px;font-weight:700;">Voir la fiche dans l\'admin →</a>
  </td></tr>
  <tr><td style="background:#f0f2f8;padding:16px 36px;text-align:center;">
    <p style="margin:0;font-size:12px;color:#888;">Email automatique — '.$site.' · Ne pas répondre</p>
  </td></tr>
</table>
</td></tr></table>
</body></html>';

    $fromName  = '=?UTF-8?B?'.base64_encode($site.' — '.$typeSender).'?=';
    $fromEmail = company_email();
    // Adresse validée plus haut : une adresse piégée ne peut pas ajouter d'en-tête au mail.
    $replyTo   = $email !== '' ? $rawEmail : $to;

    [$contentHeader, $body] = quote_mail_message($html, $attachments);
    $headers  = "MIME-Version: 1.0\r\n";
    $headers .= $contentHeader;
    $headers .= "From: {$fromName} <{$fromEmail}>\r\n";
    $headers .= "Reply-To: {$replyTo}\r\n";
    $headers .= "X-Mailer: PHP/".PHP_VERSION."\r\n";
    $headers .= "X-EMAE-Source: site\r\n";   // l'agent relation client ignore les mails envoyés par le site

    return ['subject' => $subject, 'subject_text' => $subjectTxt, 'body' => $body, 'headers' => $headers, 'html' => $html, 'attached' => count($attachments)];
}

function send_quote_confirmation_to_client(array $data): bool
{
    $email = trim($data['email'] ?? '');
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) return false;

    $name       = trim($data['full_name']    ?? '');
    $phone      = trim($data['phone']        ?? '');
    $city       = trim($data['city']         ?? '');
    $address    = trim($data['address']      ?? '');
    $postalCode = trim($data['postal_code']  ?? '');
    $service    = trim($data['service_type'] ?? '');
    $urgency    = trim($data['urgency']      ?? 'Normale');
    $site       = company_name();
    $sitePhone  = company_phone();
    $sitePhoneLink = company_phone_link();
    $now        = date('d/m/Y à H:i');
    $fee        = setting('cancellation_fee', '');

    $locationParts = array_filter([$address, trim($postalCode.' '.$city)]);
    $locationLine  = implode(', ', $locationParts);
    $feeText = $fee !== '' ? 'de <strong>'.$fee.' €</strong>' : 'de déplacement';

    $subject = '=?UTF-8?B?'.base64_encode('✅ Confirmation de votre demande — '.$site).'?=';

    $html = '<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"></head><body style="margin:0;padding:0;background:#f4f6fb;font-family:Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f4f6fb;padding:32px 0;">
<tr><td align="center">
<table width="600" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,.08);">
  <tr><td style="background:#061029;padding:28px 36px;text-align:center;">
    <p style="margin:0;font-size:24px;font-weight:700;color:#ffffff;">'.$site.'</p>
    <p style="margin:6px 0 0;font-size:13px;color:#F07B1D;font-weight:600;text-transform:uppercase;letter-spacing:.08em;">Confirmation de votre demande</p>
  </td></tr>
  <tr><td style="padding:32px 36px 20px;">
    <p style="margin:0 0 10px;font-size:19px;font-weight:700;color:#061029;">Bonjour '.htmlspecialchars($name, ENT_QUOTES).' ✅</p>
    <p style="margin:0;font-size:15px;color:#444;line-height:1.7;">Nous avons bien reçu votre demande et vous recontacterons <strong>dans les plus brefs délais</strong>.</p>
  </td></tr>
  <tr><td style="padding:0 36px 24px;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background:#f8faff;border-radius:8px;overflow:hidden;">
      <tr><td colspan="2" style="padding:12px 18px;background:#e8edf8;font-size:11px;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:#3d5a99;">Récapitulatif de votre demande</td></tr>
      '.($service !== '' ? '<tr><td style="padding:10px 18px;font-size:13px;color:#555;width:160px;border-bottom:1px solid #eef0f7;">🔧 Service</td><td style="padding:10px 18px;font-size:14px;font-weight:600;color:#061029;border-bottom:1px solid #eef0f7;">'.htmlspecialchars($service, ENT_QUOTES).'</td></tr>' : '').'
      <tr><td style="padding:10px 18px;font-size:13px;color:#555;border-bottom:1px solid #eef0f7;">⚡ Urgence</td><td style="padding:10px 18px;font-size:14px;font-weight:600;color:#061029;border-bottom:1px solid #eef0f7;">'.htmlspecialchars($urgency, ENT_QUOTES).'</td></tr>
      '.($locationLine !== '' ? '<tr><td style="padding:10px 18px;font-size:13px;color:#555;border-bottom:1px solid #eef0f7;">📍 Adresse</td><td style="padding:10px 18px;font-size:14px;color:#061029;border-bottom:1px solid #eef0f7;">'.htmlspecialchars($locationLine, ENT_QUOTES).'</td></tr>' : '').'
      <tr><td style="padding:10px 18px;font-size:13px;color:#555;">📅 Envoyé le</td><td style="padding:10px 18px;font-size:14px;color:#555;">'.$now.'</td></tr>
    </table>
  </td></tr>
  <tr><td style="padding:0 36px 28px;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background:#fff8ec;border:2px solid #f0b429;border-radius:8px;overflow:hidden;">
      <tr><td style="padding:14px 18px;background:#fef3c7;border-bottom:1px solid #f0b429;">
        <p style="margin:0;font-size:13px;font-weight:700;color:#92400e;">⚠️ Politique d\'annulation — À lire attentivement</p>
      </td></tr>
      <tr><td style="padding:16px 18px;font-size:13px;color:#78350f;line-height:1.8;">
        <p style="margin:0 0 10px;">Toute annulation ou report d\'intervention communiqué <strong>moins de 2 heures avant</strong> le créneau confirmé entraînera la facturation des <strong>frais '.$feeText.'</strong>.</p>
        <p style="margin:0;">Pour annuler ou modifier votre rendez-vous, merci de nous contacter <strong>au moins 2 heures à l\'avance</strong> par téléphone.</p>
      </td></tr>
    </table>
  </td></tr>
  <tr><td style="padding:0 36px 32px;text-align:center;">
    <p style="margin:0 0 16px;font-size:14px;color:#555;">Notre équipe est disponible <strong>24h/24, 7j/7</strong> :</p>
    <a href="'.$sitePhoneLink.'" style="display:inline-block;background:#F07B1D;color:#ffffff;text-decoration:none;padding:14px 32px;border-radius:8px;font-size:16px;font-weight:700;letter-spacing:.02em;">📞 '.$sitePhone.'</a>
  </td></tr>
  <tr><td style="background:#f0f2f8;padding:16px 36px;text-align:center;">
    <p style="margin:0;font-size:12px;color:#888;">'.$site.' — '.htmlspecialchars(company_slogan(), ENT_QUOTES).' — '.htmlspecialchars(company_regions(), ENT_QUOTES).'</p>
    <p style="margin:4px 0 0;font-size:11px;color:#aaa;">Cet email est automatique, merci de ne pas y répondre directement.</p>
  </td></tr>
</table>
</td></tr></table>
</body></html>';

    $fromName  = '=?UTF-8?B?'.base64_encode($site.' — Confirmation').'?=';
    $fromEmail = company_email();
    $replyTo   = setting('form_email_to', company_email());

    $headers  = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "From: {$fromName} <{$fromEmail}>\r\n";
    $headers .= "Reply-To: {$replyTo}\r\n";
    $headers .= "X-Mailer: PHP/".PHP_VERSION."\r\n";
    $headers .= "X-EMAE-Source: site\r\n";   // l'agent relation client ignore les mails envoyés par le site

    $ok = mail($email, $subject, $html, $headers);
    if (!$ok) error_log('[EMAE] send_quote_confirmation_to_client failed — to='.$email.' from='.$fromEmail);
    return $ok;
}

/* ═══════════════════════════════════════════════════
   SMS OVH
═══════════════════════════════════════════════════ */
function send_sms_ovh(string $to, string $message): bool
{
    $appKey      = setting('ovh_app_key', '');
    $appSecret   = setting('ovh_app_secret', '');
    $consumerKey = setting('ovh_consumer_key', '');
    $serviceName = setting('ovh_service_name', '');
    if ($appKey === '' || $appSecret === '' || $consumerKey === '' || $serviceName === '') return false;

    $to = preg_replace('/[\s\.\-\(\)]/', '', $to);
    if (preg_match('/^0[67][0-9]{8}$/', $to)) $to = '+33'.substr($to, 1);
    if (!preg_match('/^\+[1-9][0-9]{6,14}$/', $to)) { error_log('[EMAE SMS] format invalide: '.$to); return false; }

    $url  = 'https://eu.api.ovh.com/1.0/sms/'.rawurlencode($serviceName).'/jobs/';
    $body = json_encode([
        'charset'           => 'UTF-8',
        'class'             => 'phoneDisplay',
        'coding'            => '7bit',
        'message'           => mb_substr($message, 0, 160),
        'noStopClause'      => false,
        'priority'          => 'high',
        'receivers'         => [$to],
        'senderForResponse' => true,
        'validityPeriod'    => 2880,
    ]);
    $ts  = time();
    $sig = '$1$'.sha1(implode('+', [$appSecret, $consumerKey, 'POST', $url, $body, $ts]));

    $ctx = stream_context_create(['http' => [
        'method'        => 'POST',
        'header'        => "Content-Type: application/json\r\nX-Ovh-Application: $appKey\r\nX-Ovh-Consumer: $consumerKey\r\nX-Ovh-Timestamp: $ts\r\nX-Ovh-Signature: $sig",
        'content'       => $body,
        'timeout'       => 8,
        'ignore_errors' => true,
    ]]);
    $res = @file_get_contents($url, false, $ctx);
    if ($res === false) { error_log('[EMAE SMS] OVH connexion échouée'); return false; }
    $data = json_decode($res, true);
    $ok = !empty($data['ids']);
    if (!$ok) error_log('[EMAE SMS] OVH: '.$res);
    return $ok;
}

/* ═══════════════════════════════════════════════════
   TECHNICIENS
═══════════════════════════════════════════════════ */
function all_technicians(): array
{
    try { return db_fetch_all("SELECT id, name, email, phone, status FROM technicians ORDER BY name"); }
    catch (Throwable $e) { return []; }
}

function get_tech_by_id(int $id): ?array
{
    try { $r = db_fetch("SELECT id, name, email, phone, status FROM technicians WHERE id = ?", [$id]); return $r ?: null; }
    catch (Throwable $e) { return null; }
}

function tech_login_check(string $email, string $password): ?array
{
    $email = trim($email);
    if (login_is_locked('tech', $email)) return null;
    try {
        $r = db_fetch("SELECT * FROM technicians WHERE email = ? AND status = 'actif'", [$email]);
        $ok = $r && password_verify($password, (string)$r['password_hash']);
    } catch (Throwable $e) { $r = null; $ok = false; }
    login_record_attempt('tech', $email, $ok);
    return $ok ? $r : null;
}

/* ── Connexion durable des techniciens (30 jours sur leur téléphone) ── */
const TECH_REMEMBER_COOKIE = 'emae_tech';
const TECH_REMEMBER_DAYS   = 30;

function tech_remember_table(): void
{
    static $done = false;
    if ($done) return;
    $done = true;
    try {
        db_execute("CREATE TABLE IF NOT EXISTS tech_remember_tokens (
            id INT AUTO_INCREMENT PRIMARY KEY,
            tech_id INT NOT NULL,
            selector CHAR(24) NOT NULL UNIQUE,
            token_hash CHAR(64) NOT NULL,
            pw_fingerprint CHAR(64) NOT NULL,
            user_agent VARCHAR(255) NULL,
            expires_at DATETIME NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_tech (tech_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (Throwable $e) { error_log('[EMAE] tech_remember_tokens : '.$e->getMessage()); }
}

function tech_remember_set_cookie(string $value, int $expires): void
{
    setcookie(TECH_REMEMBER_COOKIE, $value, [
        'expires'  => $expires,
        'path'     => '/',
        'secure'   => true,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

/** Émet un jeton de connexion durable pour ce technicien et ce téléphone. */
function tech_remember_issue(array $tech): void
{
    tech_remember_table();
    $selector  = bin2hex(random_bytes(12));
    $validator = bin2hex(random_bytes(32));
    $expires   = time() + TECH_REMEMBER_DAYS * 86400;
    try {
        db_execute("DELETE FROM tech_remember_tokens WHERE expires_at < NOW()");
        db_execute(
            "INSERT INTO tech_remember_tokens (tech_id, selector, token_hash, pw_fingerprint, user_agent, expires_at) VALUES (?,?,?,?,?,?)",
            [(int)$tech['id'], $selector, hash('sha256', $validator), hash('sha256', (string)$tech['password_hash']),
             mb_substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255), date('Y-m-d H:i:s', $expires)]
        );
        tech_remember_set_cookie($selector.':'.$validator, $expires);
    } catch (Throwable $e) { error_log('[EMAE] jeton de connexion : '.$e->getMessage()); }
}

/** Reconnecte le technicien à partir de son jeton. Le jeton est prolongé à chaque usage. */
function tech_remember_login(): ?array
{
    $raw = (string)($_COOKIE[TECH_REMEMBER_COOKIE] ?? '');
    if (!preg_match('/^([a-f0-9]{24}):([a-f0-9]{64})$/', $raw, $m)) return null;
    tech_remember_table();
    try {
        $row = db_fetch("SELECT * FROM tech_remember_tokens WHERE selector = ? AND expires_at > NOW()", [$m[1]]);
        if (!$row || !hash_equals((string)$row['token_hash'], hash('sha256', $m[2]))) { tech_remember_forget(); return null; }
        $t = db_fetch("SELECT * FROM technicians WHERE id = ? AND status = 'actif'", [(int)$row['tech_id']]);
        // Un changement de mot de passe ou un compte désactivé coupe toutes les connexions durables.
        if (!$t || !hash_equals((string)$row['pw_fingerprint'], hash('sha256', (string)$t['password_hash']))) { tech_remember_forget(); return null; }
        $expires = time() + TECH_REMEMBER_DAYS * 86400;
        db_execute("UPDATE tech_remember_tokens SET expires_at = ? WHERE id = ?", [date('Y-m-d H:i:s', $expires), (int)$row['id']]);
        tech_remember_set_cookie($raw, $expires);
        session_regenerate_id(true);
        $_SESSION['tech_id']   = (int)$t['id'];
        $_SESSION['tech_name'] = $t['name'];
        return $t;
    } catch (Throwable $e) { return null; }
}

/** Supprime le jeton de ce téléphone (déconnexion volontaire). */
function tech_remember_forget(): void
{
    $raw = (string)($_COOKIE[TECH_REMEMBER_COOKIE] ?? '');
    if (preg_match('/^([a-f0-9]{24}):/', $raw, $m)) {
        tech_remember_table();
        try { db_execute("DELETE FROM tech_remember_tokens WHERE selector = ?", [$m[1]]); } catch (Throwable $e) {}
    }
    if (isset($_COOKIE[TECH_REMEMBER_COOKIE])) tech_remember_set_cookie('', time() - 3600);
}

/** Adresse de retour après connexion : uniquement une page de l'espace technicien. */
function tech_safe_next(string $next): string
{
    $base = url_for('tech/');
    if ($next === '' || !str_starts_with($next, $base) || str_contains($next, '//') || preg_match('/[\r\n]/', $next)) return '';
    $path = (string)parse_url($next, PHP_URL_PATH);
    if (preg_match('#/(login|logout)\.php$#', $path)) return '';
    return $next;
}

function require_tech_auth(): array
{
    boot_session();
    if (empty($_SESSION['tech_id']) && ($t = tech_remember_login())) return $t;
    $toLogin = static function (): never {
        $next = tech_safe_next((string)($_SERVER['REQUEST_URI'] ?? ''));
        $isPage = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET';
        header('Location: '.url_for('tech/login.php').($next !== '' && $isPage ? '?next='.rawurlencode($next) : ''));
        exit;
    };
    if (empty($_SESSION['tech_id'])) $toLogin();
    try {
        $t = db_fetch("SELECT * FROM technicians WHERE id = ? AND status = 'actif'", [(int)$_SESSION['tech_id']]);
    } catch (Throwable $e) { $t = null; }
    if (!$t) { unset($_SESSION['tech_id']); tech_remember_forget(); $toLogin(); }
    return $t;
}

function quote_tech_photos(array $q): array
{
    if (empty($q['tech_photos'])) return [];
    $p = json_decode((string)$q['tech_photos'], true);
    return is_array($p) ? $p : [];
}

/* ═══════════════════════════════════════════════════
   IMAGE UPLOAD
═══════════════════════════════════════════════════ */
function public_asset_exists(string $path): bool
{
    if (trim($path) === '') return false;
    if (preg_match('#^(https?:)?//#i', $path)) return true;
    return is_file(__DIR__ . '/../' . ltrim($path, '/'));
}

/**
 * Nettoie un SVG uploadé : refuse tout DOCTYPE, retire les balises et
 * attributs capables d'exécuter du JavaScript (script, gestionnaires
 * on*, liens javascript:). Retourne null si le contenu n'est pas un SVG
 * valide ou est jugé dangereux.
 */
function sanitize_svg(string $svg): ?string
{
    if (stripos($svg, '<!doctype') !== false) return null;

    $prevErrors = libxml_use_internal_errors(true);
    $doc = new DOMDocument();
    $ok = $doc->loadXML($svg, LIBXML_NONET | LIBXML_NOCDATA);
    libxml_clear_errors();
    libxml_use_internal_errors($prevErrors);
    if (!$ok || !$doc->documentElement || strtolower($doc->documentElement->localName) !== 'svg') {
        return null;
    }

    $dangerousTags = ['script','foreignobject','iframe','embed','object','animate','animatetransform','animatemotion','animatecolor','set'];
    foreach ($dangerousTags as $tag) {
        $nodes = $doc->getElementsByTagName($tag);
        for ($i = $nodes->length - 1; $i >= 0; $i--) {
            $node = $nodes->item($i);
            $node?->parentNode?->removeChild($node);
        }
    }

    $xpath = new DOMXPath($doc);
    foreach ($xpath->query('//*') as $el) {
        if (!$el instanceof DOMElement) continue;
        foreach (iterator_to_array($el->attributes ?? []) as $attr) {
            $name = strtolower($attr->nodeName);
            $value = trim($attr->nodeValue ?? '');
            if (str_starts_with($name, 'on')) {          // gestionnaire d'événement
                $el->removeAttribute($attr->nodeName);
                continue;
            }
            if (!in_array($name, ['href', 'xlink:href'], true)) continue;

            // Un navigateur retire espaces et caractères de contrôle avant de lire
            // le schéma : « java&#9;script: » s'exécute bel et bien. On teste donc
            // la valeur nettoyée, et on n'autorise que des schémas connus sûrs
            // plutôt que d'essayer d'énumérer les dangereux.
            $probe     = strtolower((string)preg_replace('/[\s\x00-\x1F\x7F]+/', '', $value));
            $hasScheme = (bool)preg_match('/^[a-z][a-z0-9+.\-]*:/', $probe);
            $safe = !$hasScheme                                            // relatif ou #ancre
                || (bool)preg_match('#^(https?|mailto|tel):#', $probe)
                || (bool)preg_match('#^data:image/(png|jpe?g|gif|webp);base64,#', $probe);
            if (!$safe) $el->removeAttribute($attr->nodeName);
        }
    }

    return $doc->saveXML($doc->documentElement) ?: null;
}

function upload_image_field(string $field, string $dir = 'gallery'): ?string
{
    if (empty($_FILES[$field]['name'])) return null;
    if (($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) return null;
    $tmp = $_FILES[$field]['tmp_name'] ?? '';
    if ($tmp === '' || !is_uploaded_file($tmp)) return null;
    $mime = mime_content_type($tmp) ?: '';
    $allowed = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp','image/svg+xml'=>'svg'];
    if (!isset($allowed[$mime])) return null;
    $uploadDir = __DIR__ . '/../storage/uploads/' . trim($dir, '/');
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0775, true);
    $filename = date('YmdHis').'-'.bin2hex(random_bytes(4)).'.'.$allowed[$mime];
    $target = $uploadDir . '/' . $filename;
    if ($mime === 'image/svg+xml') {
        $raw = file_get_contents($tmp);
        $clean = $raw !== false ? sanitize_svg($raw) : null;
        if ($clean === null || file_put_contents($target, $clean) === false) return null;
    } elseif (!move_uploaded_file($tmp, $target)) {
        return null;
    }
    $path = 'storage/uploads/'.trim($dir,'/').'/'.$filename;
    db_execute('INSERT INTO media (file_path, alt_text, category) VALUES (?, ?, ?)', [$path, '', $dir]);
    if ($mime !== 'image/svg+xml') generate_webp_variant($target, $mime);
    return $path;
}

/**
 * Génère une copie .webp à côté de l'image originale (JPEG/PNG) pour que
 * les gabarits puissent proposer WebP avec repli sur le format d'origine.
 * N'échoue jamais l'upload : simple optimisation best-effort via GD.
 */
function generate_webp_variant(string $sourcePath, string $mime): void
{
    if (!function_exists('imagewebp')) return;
    $webpPath = preg_replace('/\.[a-z0-9]+$/i', '.webp', $sourcePath);
    if ($webpPath === null || $webpPath === $sourcePath || is_file($webpPath)) return;
    try {
        $image = match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($sourcePath),
            'image/png'  => @imagecreatefrompng($sourcePath),
            default      => false,
        };
        if ($image === false) return;
        if ($mime === 'image/png') imagepalettetotruecolor($image);
        imagewebp($image, $webpPath, 82);
        imagedestroy($image);
    } catch (Throwable $e) {}
}

/** Chemin de la variante .webp d'une image uploadée, si elle existe. */
function webp_variant_path(string $path): ?string
{
    $webp = preg_replace('/\.(jpe?g|png)$/i', '.webp', $path);
    if ($webp === null || $webp === $path) return null;
    return is_file(__DIR__ . '/../' . ltrim($webp, '/')) ? $webp : null;
}

/**
 * Rend une balise <picture> avec la variante .webp en priorité (si elle
 * existe) et l'original en repli, pour que les images uploadées bénéficient
 * réellement du webp déjà généré par generate_webp_variant().
 * $attrs est fusionné tel quel dans le <img> de repli (class, loading, width...).
 */
function picture_html(string $path, string $alt, array $attrs = []): string
{
    // Une photo de 4 Mo affichée sur une carte de 400 px : on sert une copie réduite.
    $small = image_resized_variant($path, (int)($attrs['data-max'] ?? 1200));
    unset($attrs['data-max']);
    if ($small !== null) {
        $path = $small['src'];
        $attrs += ['width' => $small['w'], 'height' => $small['h']];   // réserve la place : la page ne « saute » plus
    }
    $attrStr = '';
    foreach ($attrs as $k => $v) { $attrStr .= ' ' . e($k) . '="' . e((string)$v) . '"'; }
    $img = '<img src="' . e(asset_url($path)) . '" alt="' . e($alt) . '"' . $attrStr . '>';
    $webp = $small['webp'] ?? webp_variant_path($path);
    if ($webp === null) return $img;
    return '<picture><source srcset="' . e(asset_url($webp)) . '" type="image/webp">' . $img . '</picture>';
}

/**
 * Copie réduite d'une image envoyée sur le site (largeur maximale $maxW), créée à la
 * première demande puis réutilisée : « photo.jpg » → « photo-w1200.jpg » et
 * « photo-w1200.webp ». Retour : ['src', 'webp' (ou null), 'w', 'h'], ou null quand
 * l'image est déjà légère, n'est pas un JPEG/PNG du site, ou que le serveur ne sait
 * pas réduire les images (l'original est alors affiché, comme avant).
 */
function image_resized_variant(string $path, int $maxW = 1200): ?array
{
    $rel = ltrim($path, '/');
    if ($maxW < 64 || !preg_match('#^storage/uploads/[A-Za-z0-9_/.-]+\.(jpe?g|png)$#i', $rel) || str_contains($rel, '..')) return null;
    $src = __DIR__.'/../'.$rel;
    if (!is_file($src)) return null;
    $ext  = strtolower(pathinfo($rel, PATHINFO_EXTENSION)) === 'png' ? 'png' : 'jpg';
    $base = preg_replace('/\.[a-z]+$/i', '', $rel).'-w'.$maxW;
    $out  = $base.'.'.$ext;
    $webp = $base.'.webp';
    $skip = __DIR__.'/../'.$base.'.skip';
    if (is_file(__DIR__.'/../'.$out)) {
        $s = @getimagesize(__DIR__.'/../'.$out);
        return $s ? ['src' => $out, 'webp' => is_file(__DIR__.'/../'.$webp) ? $webp : null, 'w' => $s[0], 'h' => $s[1]] : null;
    }
    if (is_file($skip) || !function_exists('imagecreatetruecolor')) return null;
    $info = @getimagesize($src);
    // Déjà légère et pas trop large : rien à faire (et on ne recommence pas à chaque visite).
    if (!$info || ($info[0] <= $maxW && filesize($src) < 200 * 1024) || $info[0] * $info[1] > 40_000_000) { @touch($skip); return null; }
    try {
        @ini_set('memory_limit', '256M');
        $im = $ext === 'png' ? @imagecreatefrompng($src) : @imagecreatefromjpeg($src);
        if (!$im) { @touch($skip); return null; }
        // Photos de téléphone : la rotation est dans les métadonnées, pas dans les pixels.
        if ($ext === 'jpg' && function_exists('exif_read_data')) {
            $o = (int)((@exif_read_data($src)['Orientation'] ?? 1));
            $rot = [3 => 180, 6 => -90, 8 => 90][$o] ?? 0;
            if ($rot !== 0 && ($r = imagerotate($im, $rot, 0)) !== false) { imagedestroy($im); $im = $r; }
        }
        $w = imagesx($im); $h = imagesy($im);
        $nw = min($maxW, $w); $nh = max(1, (int)round($h * $nw / $w));
        $dst = imagecreatetruecolor($nw, $nh);
        if ($ext === 'png') { imagealphablending($dst, false); imagesavealpha($dst, true); }
        imagecopyresampled($dst, $im, 0, 0, 0, 0, $nw, $nh, $w, $h);
        $ok = $ext === 'png' ? imagepng($dst, __DIR__.'/../'.$out, 8) : imagejpeg($dst, __DIR__.'/../'.$out, 80);
        if ($ok && function_exists('imagewebp')) @imagewebp($dst, __DIR__.'/../'.$webp, 80);
        imagedestroy($im); imagedestroy($dst);
        if (!$ok) { @touch($skip); return null; }
        return ['src' => $out, 'webp' => is_file(__DIR__.'/../'.$webp) ? $webp : null, 'w' => $nw, 'h' => $nh];
    } catch (Throwable $e) { @touch($skip); return null; }
}

/* ═══════════════════════════════════════════════════
   SERVICE CARDS
═══════════════════════════════════════════════════ */


/* ═══════════════════════════════════════════════════
   HERO SETTINGS
═══════════════════════════════════════════════════ */
function hero_settings(): array
{
    return [
        'eyebrow'       => setting('home_eyebrow',       'Entreprise multitechnique avancée'),
        'title'         => setting('home_title',         'Le partenaire technique de vos bâtiments {en_region}'),
        'lead'          => setting('home_lead',          'Dépannage urgence, installation et entretien en électricité, plomberie, chauffage et climatisation. Réponse rapide, devis gratuit.'),
        'chips'         => array_values(array_filter([
            setting('home_chip_1','Électricité'), setting('home_chip_2','Plomberie'),
            setting('home_chip_3','CVC'),         setting('home_chip_4','Climatisation'),
            setting('home_chip_5','Chauffage'),   setting('home_chip_6','PAC'),
        ], fn($v) => trim((string)$v) !== '')),
        'button1_label' => setting('home_button1_label', 'Demander un devis gratuit'),
        'button1_url'   => setting('home_button1_url',   'quote'),
        'button2_label' => setting('home_button2_label', 'Appeler maintenant'),
        'button2_url'   => setting('home_button2_url',   ''),
        'quote_eyebrow' => setting('home_quote_eyebrow', 'Rappel gratuit sous 5 min'),
        'quote_title'   => setting('home_quote_title',   'Obtenir un rappel rapide'),
        'quote_service_label'      => setting('home_quote_service_label',      'Service'),
        'quote_city_label'         => setting('home_quote_city_label',         'Ville'),
        'quote_city_placeholder'   => setting('home_quote_city_placeholder',   'Ex : {ville}…'),
        'quote_button_label'       => setting('home_quote_button_label',       'Être rappelé gratuitement'),
        'quote_meta'               => setting('home_quote_meta',               '✓ Gratuit  ✓ Rapide  ✓ Sans engagement'),
        'trust_1_icon'  => setting('home_trust_1_icon',  '⚡'),
        'trust_1_label' => setting('home_trust_1_label', 'Urgences 24h/24'),
        'trust_2_icon'  => setting('home_trust_2_icon',  '🆓'),
        'trust_2_label' => setting('home_trust_2_label', 'Devis gratuit'),
        'trust_3_icon'  => setting('home_trust_3_icon',  '⭐'),
        'trust_3_label' => setting('home_trust_3_label', 'Prix annoncé avant déplacement'),
        'trust_4_icon'  => setting('home_trust_4_icon',  '🔒'),
        'trust_4_label' => setting('home_trust_4_label', 'Artisans qualifiés'),
    ];
}

function hero_feature_cards(array $hero): array
{
    $out = [];
    for ($i = 1; $i <= 3; $i++) {
        $t = trim((string)($hero['feature_'.$i.'_title'] ?? setting('home_feature_'.$i.'_title', '')));
        $x = trim((string)($hero['feature_'.$i.'_text']  ?? setting('home_feature_'.$i.'_text',  '')));
        if ($t !== '' || $x !== '') $out[] = ['title'=>$t,'text'=>$x];
    }
    return $out;
}

/* ═══════════════════════════════════════════════════
   HOME SECTIONS
═══════════════════════════════════════════════════ */
function hero_banner_settings(): array
{
    return [
        'eyebrow'       => setting('home_banner_eyebrow',       'Disponible 24h/24 — 7j/7'),
        'title'         => setting('home_banner_title',         'Une urgence ? Nous intervenons maintenant.'),
        'lead'          => setting('home_banner_lead',          'Panne électrique, fuite d\'eau, chauffage en panne : appelez-nous ou envoyez votre demande, un technicien vous répond immédiatement.'),
        'button1_label' => setting('home_banner_button1_label', 'Appeler maintenant'),
        'button1_url'   => setting('home_banner_button1_url',   company_phone_link()),
        'button2_label' => setting('home_banner_button2_label', 'Demander un devis'),
        'button2_url'   => setting('home_banner_button2_url',   'quote'),
        'logo_path'     => setting('home_banner_logo_path',     ''),
        'stats'         => [
            ['number'=>setting('stat_1_number','24/7'),  'label'=>setting('stat_1_label','Urgences')],
            ['number'=>setting('stat_2_number','0 €'), 'label'=>setting('stat_2_label','Devis')],
            ['number'=>setting('stat_3_number','7'),  'label'=>setting('stat_3_label','Métiers')],
            ['number'=>setting('stat_4_number','3'),  'label'=>setting('stat_4_label','Départements')],
        ],
    ];
}

function home_expertise_settings(): array
{
    $defaultCards = [
        ['icon'=>'⚡','title'=>'Électricité','lead'=>'Dépannage, tableaux, mise aux normes, rénovation.','item_1'=>'Panne & remise en service rapide','item_2'=>'Tableaux électriques & protections','item_3'=>'Éclairage & prises','link'=>'electricien-meaux'],
        ['icon'=>'🔧','title'=>'Plomberie','lead'=>'Fuites, sanitaires, débouchage, entretien.','item_1'=>'Recherche & réparation de fuite','item_2'=>'Remplacement équipements sanitaires','item_3'=>'Maintenance courante','link'=>'plombier-meaux'],
        ['icon'=>'❄️','title'=>'CVC & PAC','lead'=>'Climatisation, pompes à chaleur, CVC.','item_1'=>'Dépannage climatisation & PAC','item_2'=>'Entretien, nettoyage, réglages','item_3'=>'Installation & mise en service','link'=>'climatisation-meaux'],
        ['icon'=>'🛠️','title'=>'Maintenance','lead'=>'Modernisation, contrôle, contrats entretien.','item_1'=>'Contrats maintenance annuels','item_2'=>'Modernisation équipements','item_3'=>'Contrôle technique & sécurité','link'=>'services'],
    ];
    return [
        'eyebrow' => setting('home_expertise_eyebrow', 'Notre expertise'),
        'title'   => setting('home_expertise_title',   'Une maîtrise multitechnique complète'),
        'lead'    => setting('home_expertise_lead',    'Du dépannage d\'urgence à la modernisation, EMAE couvre tous vos besoins techniques.'),
        'cards'   => get_json_setting('home_expertise_cards', $defaultCards),
    ];
}

function home_reviews_block_settings(): array
{
    return [
        'eyebrow' => setting('home_reviews_eyebrow', 'Avis vérifiés'),
        'title'   => setting('home_reviews_title',   'Ils nous font confiance'),
        'lead'    => setting('home_reviews_lead',    'Plus de 120 clients satisfaits {en_region}.'),
        'rating'  => setting('schema_rating_value',  '4.9'),
        'count'   => setting('schema_review_count',  '120'),
    ];
}

function home_quote_panel_settings(): array
{
    return [
        'eyebrow'             => setting('home_quote_panel_eyebrow',             'Devis gratuit'),
        'title'               => setting('home_quote_panel_title',               'Demandez un devis'),
        'lead'                => setting('home_quote_panel_lead',                'Réponse sous 2h, sans engagement.'),
        'service_label'       => setting('home_quote_panel_service_label',       'Service'),
        'service_placeholder' => setting('home_quote_panel_service_placeholder', 'Choisir un service'),
        'message_label'       => setting('home_quote_panel_message_label',       'Votre besoin'),
        'urgency_label'       => setting('home_quote_panel_urgency_label',       'Urgence'),
        'button_label'        => setting('home_quote_panel_button_label',        quote_form_options()['submit_label']),
    ];
}

function home_zone_settings(): array
{
    $defaultCards = [
        ['title'=>'Île-de-France', 'text'=>'Paris, Seine-et-Marne (77), Yvelines (78), Essonne (91), Hauts-de-Seine (92), Seine-Saint-Denis (93), Val-de-Marne (94), Val-d\'Oise (95)'],
        ['title'=>'Occitanie',     'text'=>'Toulouse, Montpellier, Nîmes, Perpignan, Béziers, Narbonne, Carcassonne et toute la région'],
    ];
    return [
        'eyebrow'      => setting('home_zone_eyebrow',      'Zone d\'intervention'),
        'title'        => setting('home_zone_title',        'Nous intervenons près de chez vous'),
        'lead'         => setting('home_zone_lead',         '{region} — délai moyen d\'intervention inférieur à 2h pour les urgences.'),
        'badges'       => array_values(array_filter([
            setting('home_zone_badge_1','{region}'),
            setting('home_zone_badge_2',''),
            setting('home_zone_badge_3',''),
        ], fn($v) => trim($v) !== '')),
        'button_label' => setting('home_zone_button_label', 'Nous contacter'),
        'button_url'   => setting('home_zone_button_url',   'contact'),
        'cards'        => get_json_setting('home_zone_cards', $defaultCards),
        'cities'       => get_json_setting('home_zone_cities', [
            'Paris','Meaux','Marne-la-Vallée','Versailles','Évry','Nanterre','Saint-Denis','Créteil',
            'Toulouse','Montpellier','Nîmes','Perpignan'
        ]),
    ];
}

/* ═══════════════════════════════════════════════════
   FAQ & CONTACT PAGE SETTINGS
═══════════════════════════════════════════════════ */
function faq_page_settings(): array
{
    $default = [
        'hero_eyebrow' => 'Questions fréquentes',
        'hero_title'   => 'Toutes vos questions sur nos services',
        'hero_lead'    => 'Retrouvez les réponses aux questions les plus posées. Pour tout autre besoin, appelez-nous directement.',
        'cta_title'    => 'Vous ne trouvez pas votre réponse ?',
        'cta_lead'     => 'Contactez-nous directement. Nous répondons sous 5 minutes, 24h/24.',
        'cta_button'   => 'Nous contacter',
        'cta_url'      => 'contact',
        'groups'       => [
            ['category'=>'Général','items'=>[
                ['q'=>'Qui est EMAE ?','a'=>'EMAE est une entreprise multitechnique avancée spécialisée dans le dépannage, l\'installation, l\'entretien et la modernisation en électricité, plomberie, chauffage et climatisation.'],
                ['q'=>'Quelles sont vos zones d\'intervention ?','a'=>'La liste complète de nos secteurs figure sur la page Zones d\'intervention. En cas de doute, appelez-nous pour vérifier votre adresse.'],
                ['q'=>'Quels sont vos horaires ?','a'=>'EMAE est disponible 24h/24, 7j/7 pour les urgences. Pour les interventions planifiées, nous proposons des créneaux du lundi au samedi de 8h à 19h.'],
                ['q'=>'Intervenez-vous pour les particuliers et les professionnels ?','a'=>'Oui, nous intervenons pour les particuliers, syndics de copropriété, bailleurs, commerces, restaurants et gestionnaires de patrimoine.'],
            ]],
            ['category'=>'Tarifs & devis','items'=>[
                ['q'=>'Vos devis sont-ils gratuits ?','a'=>'Oui, l\'établissement d\'un devis est entièrement gratuit et sans engagement. Nous vous communiquons le tarif estimé avant toute intervention.'],
                ['q'=>'Les prix sont-ils annoncés avant intervention ?','a'=>'Absolument. Nous vous informons toujours du coût estimé avant de nous déplacer. Aucune mauvaise surprise : le prix annoncé est le prix facturé.'],
                ['q'=>'Quels moyens de paiement acceptez-vous ?','a'=>'Nous acceptons les virements bancaires, chèques et espèces. Une facture détaillée est remise à la fin de chaque intervention pour vos assurances ou remboursements.'],
                ['q'=>'Proposez-vous des contrats de maintenance ?','a'=>'Oui, nous proposons des contrats d\'entretien annuels pour vos équipements de chauffage, climatisation et installations techniques. Contactez-nous pour un devis personnalisé.'],
            ]],
            ['category'=>'Urgences','items'=>[
                ['q'=>'Intervenez-vous en urgence ?','a'=>'Oui, nous assurons une astreinte 24h/24 et 7j/7 pour les urgences bloquantes : panne électrique totale, fuite d\'eau importante, panne de chauffage en hiver, climatisation en panne en été.'],
                ['q'=>'Quel est le délai d\'intervention en urgence ?','a'=>'Notre délai moyen d\'intervention en urgence est de 1h à 2h selon votre secteur. Nous vous confirmons le délai exact au téléphone lors de votre appel.'],
                ['q'=>'Que faire en cas de panne électrique totale ?','a'=>'Coupez les appareils sensibles (ordinateurs, réfrigérateur), vérifiez le disjoncteur principal, puis appelez-nous au '.company_phone().'. Ne touchez pas aux installations si vous suspectez un danger.'],
                ['q'=>'Que faire en cas de fuite d\'eau importante ?','a'=>'Coupez l\'arrivée d\'eau principale (vanne sous l\'évier ou compteur), coupez l\'alimentation électrique dans la zone inondée, puis appelez-nous immédiatement.'],
            ]],
            ['category'=>'Nos services','items'=>[
                ['q'=>'Faites-vous les mises aux normes électriques ?','a'=>'Oui, nous intervenons pour la mise en sécurité, la remise en conformité et la modernisation des installations électriques (tableaux, disjoncteurs, mise à la terre, etc.).'],
                ['q'=>'Intervenez-vous sur les pompes à chaleur ?','a'=>'Oui, nous installons, entretenons et dépannons les pompes à chaleur air/air et air/eau pour particuliers et professionnels.'],
                ['q'=>'Faites-vous l\'installation de climatisation ?','a'=>'Oui, nous réalisons l\'installation, l\'entretien et le dépannage de climatisations (split, multi-split, gainable) pour tous types de bâtiments.'],
                ['q'=>'Proposez-vous des interventions sur chaudière ?','a'=>'Oui, nous diagnostiquons, dépannons et entretenons les chaudières gaz, fuel et électriques. Nous proposons également des contrats d\'entretien annuels.'],
            ]],
        ],
    ];
    $saved = get_json_setting('faq_page_settings', []);
    return array_merge($default, array_filter(is_array($saved) ? $saved : [], fn($v) => $v !== '' && $v !== null && $v !== []));
}

function contact_page_settings(): array
{
    return [
        'hero_eyebrow'  => setting('contact_hero_eyebrow',  'Contactez-nous'),
        'hero_title'    => setting('contact_hero_title',    'Parlez-nous de votre besoin'),
        'hero_lead'     => setting('contact_hero_lead',     'Devis gratuit, réponse sous 5 min, intervention rapide. Disponible 24h/24.'),
        'form_title'    => setting('contact_form_title',    'Envoyer une demande'),
        'form_subtitle' => setting('contact_form_subtitle', 'Un technicien vous recontacte sous 5 minutes.'),
        'info_title'    => setting('contact_info_title',    'Nos coordonnées'),
        'urgency_title' => setting('contact_urgency_title', 'Urgence ? Appelez directement'),
        'urgency_text'  => setting('contact_urgency_text',  'Pour toute panne bloquante, nous intervenons en priorité 24h/24.'),
        'zones_title'   => setting('contact_zones_title',   'Zones couvertes'),
    ];
}

/**
 * @param bool $brut true pour un formulaire d'administration : les variables de
 *                   lieu restent écrites {en_region}, sinon les réenregistrer
 *                   figerait la valeur d'une seule zone pour toutes.
 */
function zones_page_settings(bool $brut = false): array
{
    $default = [
        'eyebrow'  => 'Zones d\'intervention',
        'title'    => 'Nous intervenons',
        'title_hl' => '{en_region}',
        'lead'     => 'Électricité, plomberie, chauffage et climatisation dans nos zones. Disponibilité, délai estimé et conditions d\'intervention confirmés par téléphone avant déplacement.',
        'regions'  => [
            [
                'name'  => 'Île-de-France',
                'icon'  => '🗼',
                'color' => '#1a7ab5',
                'depts' => ['Paris (75)','Hauts-de-Seine (92)','Seine-Saint-Denis (93)','Val-de-Marne (94)','Seine-et-Marne (77)','Yvelines (78)','Essonne (91)','Val-d\'Oise (95)'],
                'cities'=> ['Paris','Boulogne-Billancourt','Saint-Denis','Montreuil','Argenteuil','Créteil','Versailles','Nanterre','Colombes','Saint-Maur-des-Fossés','Champigny-sur-Marne','Meaux','Évry','Cergy'],
                'delay' => '',
            ],
            [
                'name'  => 'Occitanie',
                'icon'  => '☀️',
                'color' => '#E8921A',
                'depts' => ['Haute-Garonne (31)','Hérault (34)','Gard (30)','Pyrénées-Orientales (66)','Aude (11)','Tarn (81)','Aveyron (12)','Gers (32)','Ariège (09)','Lot (46)'],
                'cities'=> ['Toulouse','Montpellier','Nîmes','Perpignan','Carcassonne','Albi','Rodez','Auch','Foix','Cahors','Montauban','Béziers','Sète'],
                'delay' => 'Intervention sous 4h',
            ],
        ],
    ];
    if ($brut) {
        $saved = json_decode(setting_plain('zones_page_settings'), true);
        $saved = is_array($saved) ? $saved : [];
    } else {
        // Le titre par défaut contient une variable : chaque zone y lit son secteur.
        if (function_exists('zone_vars_apply_deep')) $default = zone_vars_apply_deep($default);
        $saved = get_json_setting('zones_page_settings', []);
    }
    unset($saved['regions']);                    // plus stockées ici : voir ci-dessous
    $out   = array_merge($default, array_filter($saved, fn($v) => $v !== '' && $v !== null && $v !== []));
    if (!$brut) $out = json_block_inline($out, 'zones_page_settings');
    // Les régions viennent désormais de la table des zones, source unique du
    // site : un même changement se voit sur l'accueil, la page Nos zones, le
    // bas des pages Service et la page Contact.
    $live = function_exists('intervention_regions') ? intervention_regions() : [];
    $out['regions'] = $live ?: $default['regions'];   // repli si aucune zone active
    return $out;
}

/**
 * Témoignages publiés : visibles ET dont la provenance est notée (source_note : « avis Google
 * du 12/03/2026 », « message reçu par SMS »…). Sans provenance, un témoignage reste en attente
 * dans l'admin : on ne publie pas un avis dont on ne peut pas dire d'où il vient.
 */
function all_published_reviews(): array
{
    try { return db_fetch_all("SELECT * FROM reviews WHERE is_visible = 1 AND COALESCE(source_note, '') <> ''".zone_rows_where().' ORDER BY sort_order ASC, id DESC'); }
    catch (Throwable $e) { return []; }
}

/* ═══════════════════════════════════════════════════
   REALISATIONS
═══════════════════════════════════════════════════ */
function visible_realisations(int $limit = 6): array
{
    try {
        return db_fetch_all('SELECT * FROM realisations WHERE is_visible = 1'.zone_rows_where().' ORDER BY sort_order ASC, id DESC LIMIT '.max(1,(int)$limit));
    } catch (Throwable $e) { return []; }
}

/**
 * Réalisations, celles faites près du visiteur d'abord : même ville, puis villes
 * du secteur (la zone en cours, ou la liste donnée). Les autres suivent.
 * Chaque ligne reçoit 'local' : 2 = même ville, 1 = même secteur, 0 = ailleurs.
 */
function realisations_local(int $limit, string $city = '', array $areaCities = []): array
{
    $norm = static fn(string $s): string => trim((string)preg_replace('/[^a-z0-9]+/', ' ', function_exists('text_ascii_lower') ? text_ascii_lower($s) : strtolower($s)));
    if ($areaCities === [] && zone_ctx_slug() !== '' && function_exists('intervention_zones')) {
        foreach (intervention_zones() as $z) if ($z['slug'] === zone_ctx_slug()) $areaCities = $z['cities'];
    }
    $c    = $norm($city);
    $area = array_map($norm, $areaCities);
    $rows = array_map(static function (array $r) use ($norm, $c, $area): array {
        $rc = $norm((string)($r['city'] ?? ''));
        $r['local'] = $rc === '' ? 0 : (($c !== '' && $rc === $c) ? 2 : (in_array($rc, $area, true) ? 1 : 0));
        return $r;
    }, visible_realisations(1000));
    usort($rows, static fn(array $a, array $b): int => $b['local'] <=> $a['local']);   // tri stable : l'ordre choisi dans l'admin est gardé
    return array_slice($rows, 0, max(1, $limit));
}

/** Types de service proposés dans l'admin pour une réalisation ou un avis. */
function service_type_options(): array
{
    return ['Électricité','Plomberie','Chauffage','Climatisation','Chauffage & Climatisation','CVC','PAC','VMC & Ventilation','Portail & Visiophone','Automatismes & Électroménager','Maintenance','Dépannage'];
}

/** Métiers auxquels se rattache un type de service. « Chauffage & Climatisation » en donne deux. */
function realisation_trades(string $serviceType): array
{
    $motifs = [
        'electricite'   => '/[ée]lectri/iu',
        'plomberie'     => '/plomb/iu',
        'chauffage'     => '/chauff|chaudi|\bpac\b|pompe/iu',
        'climatisation' => '/clim|\bcvc\b/iu',
        'vmc'           => '/ventil|\bvmc\b/iu',
        'portail'       => '/portail|visiophone|interphone|digicode/iu',
        'automatisme'   => '/automatism|\bvolets?\b|\bstores?\b|domotique|[ée]lectrom[ée]nager/iu',
    ];
    return array_keys(array_filter($motifs, static fn(string $m): bool => preg_match($m, $serviceType) === 1));
}

/** Les réalisations publiées d'un métier, pour sa page service. */
function realisations_for_trade(string $trade, int $limit = 6): array
{
    $rows = array_filter(visible_realisations(1000), static fn(array $r): bool => in_array($trade, realisation_trades((string)($r['service_type'] ?? '')), true));
    return array_slice(array_values($rows), 0, $limit);
}

function all_realisations(): array
{
    try { return db_fetch_all('SELECT * FROM realisations WHERE 1=1'.zone_rows_where().' ORDER BY sort_order ASC, id DESC'); }
    catch (Throwable $e) { return []; }
}

/* ═══════════════════════════════════════════════════
   DB RENDER HELPERS (queries pour les vues)
═══════════════════════════════════════════════════ */
function page_by_slug(string $slug): ?array
{
    // Une page propre à la zone prime sur la page globale de même slug.
    try {
        return db_fetch('SELECT * FROM pages WHERE slug = ? AND status = ?'.zone_rows_where().' ORDER BY zone_id DESC LIMIT 1', [$slug, 'published'])
            ?: db_fetch('SELECT * FROM pages WHERE slug = ? AND status = ? LIMIT 1', [$slug, 'published']);
    } catch (Throwable $e) {
        try { return db_fetch('SELECT * FROM pages WHERE slug = ? AND status = ? LIMIT 1', [$slug, 'published']); }
        catch (Throwable $e2) { return null; }
    }
}

function all_pages(): array
{
    try { return db_fetch_all('SELECT * FROM pages WHERE 1=1'.zone_rows_where().' ORDER BY sort_order ASC, title ASC'); }
    catch (Throwable $e) { return []; }
}

function visible_reviews(int $limit = 6): array
{
    try { return db_fetch_all("SELECT * FROM reviews WHERE is_visible = 1 AND COALESCE(source_note, '') <> ''".zone_rows_where().' ORDER BY sort_order ASC, id DESC LIMIT ' . max(1, (int)$limit)); }
    catch (Throwable $e) { return []; }
}

function all_quotes(bool $archived = false): array
{
    try {
        return db_fetch_all('SELECT q.*, t.name AS tech_name FROM quotes q LEFT JOIN technicians t ON t.id = q.technician_id WHERE q.archived = ? ORDER BY q.created_at DESC', [(int)$archived]);
    } catch (Throwable $e) {
        try { return db_fetch_all('SELECT * FROM quotes WHERE archived = ? ORDER BY created_at DESC', [(int)$archived]); }
        catch (Throwable $e2) { return []; }
    }
}
function count_quotes_by_status(): array
{
    try {
        $rows = db_fetch_all('SELECT status, archived, COUNT(*) as n FROM quotes GROUP BY status, archived');
        $out = ['actifs'=>0,'archivés'=>0,'nouveaux'=>0,'en_cours'=>0];
        foreach ($rows as $r) {
            if (!(int)$r['archived']) {
                $out['actifs'] += (int)$r['n'];
                if ((string)$r['status'] === 'nouveau') $out['nouveaux'] += (int)$r['n'];
                if (in_array((string)$r['status'], ['planifié','en cours'])) $out['en_cours'] += (int)$r['n'];
            } else {
                $out['archivés'] += (int)$r['n'];
            }
        }
        return $out;
    } catch (Throwable $e) { return ['actifs'=>0,'archivés'=>0,'nouveaux'=>0,'en_cours'=>0]; }
}

/* ═══════════════════════════════════════════════════
   THEME CSS VARIABLES (admin-driven)
   Toutes les variables CSS pilotées depuis l'admin
═══════════════════════════════════════════════════ */
function theme_css_variables(): string
{
    $vars = [
        '--primary'              => setting('color_primary',         '#ee7d1a'),
        '--primary-dark'         => setting('color_primary_hover',   '#c95f0b'),
        '--navy'                 => setting('color_navy',            '#0b1641'),
        '--navy-2'               => setting('color_navy_2',          '#152555'),
        '--navy-3'               => setting('color_navy_3',          '#1e3370'),
        '--dark'                 => setting('color_dark',            '#07102a'),
        '--dark-card'            => setting('color_dark_card',       '#0f1e3d'),
        '--text'                 => setting('color_text',            '#e8ecf5'),
        '--text-2'               => setting('color_text_muted',      '#8fa0c4'),
        '--line'                 => setting('color_line',            'rgba(255,255,255,.09)'),
        '--font-heading'         => '"'.setting('font_heading','Montserrat').'", Arial, sans-serif',
        '--font-body'            => '"'.setting('font_body','Inter').'", Arial, sans-serif',
        '--hero-bg-from'         => setting('hero_bg_from',          '#07102a'),
        '--hero-bg-to'           => setting('hero_bg_to',            '#152555'),
        '--hero-glow-1'          => setting('hero_glow_1',           'rgba(238,125,26,.18)'),
        '--hero-glow-2'          => setting('hero_glow_2',           'rgba(21,37,85,.8)'),
        '--hero-overlay'         => setting('hero_overlay_opacity',  '0.5'),
        '--card-overlay'         => setting('card_overlay_opacity',  '0.45'),
    ];
    $css = ':root{';
    foreach ($vars as $k => $v) $css .= $k . ':' . $v . ';';
    $css .= '}';
    return '<style>' . $css . '</style>';
}

function _hex_to_rgb(string $hex): array
{
    $hex = ltrim($hex, '#');
    if (strlen($hex) === 3) $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
    return [hexdec(substr($hex,0,2)), hexdec(substr($hex,2,2)), hexdec(substr($hex,4,2))];
}

/* ═══════════════════════════════════════════════════
   WHY SECTION SETTINGS
═══════════════════════════════════════════════════ */
function why_section_settings(): array
{
    $defaultItems = [
        ['icon_type'=>'emoji','icon_emoji'=>'⚡','icon_image'=>'','title'=>'Réponse immédiate 24h/24','text'=>'Nous décrochons toujours. Délai moyen inférieur à 2h pour les urgences.'],
        ['icon_type'=>'emoji','icon_emoji'=>'📋','icon_image'=>'','title'=>'Devis clair avant intervention','text'=>'Le prix est annoncé avant toute action. Zéro surprise sur la facture finale.'],
        ['icon_type'=>'emoji','icon_emoji'=>'🔒','icon_image'=>'','title'=>'Techniciens qualifiés','text'=>'Nos artisans sont formés et expérimentés sur l\'ensemble de nos métiers.'],
        ['icon_type'=>'emoji','icon_emoji'=>'📍','icon_image'=>'','title'=>'Présence locale','text'=>'Des techniciens installés dans votre secteur, qui en connaissent le bâti et ses spécificités.'],
        ['icon_type'=>'emoji','icon_emoji'=>'🛠️','icon_image'=>'','title'=>'Tous types d\'interventions','text'=>'Urgence, installation, entretien préventif, mise aux normes — un seul interlocuteur.'],
        ['icon_type'=>'emoji','icon_emoji'=>'💬','icon_image'=>'','title'=>'Prix annoncé','text'=>'Le prix est annoncé avant tout déplacement : aucune visite facturée sans votre accord.'],
        ['icon_type'=>'emoji','icon_emoji'=>'🏗️','icon_image'=>'','title'=>'Particuliers & professionnels','text'=>'Logements, commerces, bureaux, copropriétés — nous adaptons notre organisation à votre contexte.'],
        ['icon_type'=>'emoji','icon_emoji'=>'📞','icon_image'=>'','title'=>'Disponible 24h/7j','text'=>'L\'urgence n\'attend pas. Notre astreinte est active en permanence pour les situations bloquantes.'],
    ];
    return [
        'eyebrow' => setting('why_eyebrow', 'Pourquoi nous choisir'),
        'title'   => setting('why_title',   'EMAE, <span>la référence</span> du dépannage multitechnique'),
        'lead'    => setting('why_lead',     'Fondée pour répondre aux urgences techniques avec rigueur et rapidité.'),
        'items'   => get_json_setting('why_items', $defaultItems),
    ];
}

/* ═══════════════════════════════════════════════════
   SERVICE CARDS — version étendue avec image PNG
═══════════════════════════════════════════════════ */
function service_cards_settings(): array
{
    $default = [
        ['title'=>'Électricité',          'icon'=>'','image'=>'','badge'=>'Urgence 24h/7j',    'link'=>'electricite',   'desc'=>'Dépannage, installation, mise aux normes, rénovation électrique.'],
        ['title'=>'Plomberie',            'icon'=>'','image'=>'','badge'=>'Fuite & dépannage', 'link'=>'plomberie',     'desc'=>'Fuite, sanitaires, débouchage, entretien réseau.'],
        ['title'=>'Chauffage & PAC',      'icon'=>'','image'=>'','badge'=>'Chaudière & PAC',   'link'=>'chauffage',     'desc'=>'Chaudière gaz/fioul, pompe à chaleur, entretien chauffage.'],
        ['title'=>'Climatisation CVC',    'icon'=>'','image'=>'','badge'=>'CVC & clim',        'link'=>'climatisation', 'desc'=>'Installation, dépannage et entretien climatisation.'],
    ];
    $cards = get_json_setting('home_service_cards', $default);
    if (!$cards) return $default;
    $out = [];
    foreach ($default as $i => $fallback) {
        $c = is_array($cards[$i] ?? null) ? $cards[$i] : [];
        $out[] = [
            'title' => trim((string)($c['title'] ?? '')) ?: $fallback['title'],
            'icon'  => trim((string)($c['icon']  ?? '')),
            'image' => trim((string)($c['image'] ?? '')),
            'badge' => trim((string)($c['badge'] ?? '')) ?: $fallback['badge'],
            'link'  => trim((string)($c['link']  ?? '')) ?: $fallback['link'],
            'desc'  => trim((string)($c['desc']  ?? '')) ?: $fallback['desc'],
        ];
    }
    return $out;
}

function home_cards(): array { return service_cards_settings(); }

/* ═══════════════════════════════════════════════════
   SERVICES PAGE — contenu complet
═══════════════════════════════════════════════════ */
function services_page_content(): array
{
    return [
        'eyebrow' => setting('services_page_eyebrow', 'Nos services'),
        'title'   => setting('services_page_title',   'Un pôle multitechnique complet'),
        'lead'    => setting('services_page_lead',    'EMAE intervient sur l\'ensemble de vos besoins techniques — dépannage urgence, installation neuve, entretien préventif et mise aux normes. Un seul interlocuteur pour tous vos corps de métier.'),
        'services' => [
            [
                'slug'  => 'electricite',
                'title' => 'Électricité',
                'icon'  => '⚡',
                'color' => '#E8921A',
                'desc'  => 'Du dépannage urgent au tableau électrique neuf, en passant par la mise aux normes et la rénovation complète.',
                'items' => ['Panne électrique & remise en service','Tableau électrique & protections','Prises, circuits & éclairage','Installation complète ou partielle','Mise aux normes NF C 15-100','Rénovation électrique','Domotique & automatismes','Urgence 24h/7j'],
            ],
            [
                'slug'  => 'plomberie',
                'title' => 'Plomberie',
                'icon'  => '💧',
                'color' => '#1a7ab5',
                'desc'  => 'Recherche de fuite, réparation sanitaire, remplacement d\'équipements et entretien des réseaux.',
                'items' => ['Recherche & réparation de fuite','Sanitaires & robinetterie','Débouchage & évacuations','Chauffe-eau & cumulus','Réseaux eau froide/chaude','Tuyauterie & raccordements','Entretien préventif','Urgence dégât des eaux'],
            ],
            [
                'slug'  => 'chauffage',
                'title' => 'Chauffage & PAC',
                'icon'  => '🔥',
                'color' => '#c0392b',
                'desc'  => 'Chaudière gaz, fioul, électrique, pompes à chaleur air/air et air/eau — dépannage, entretien et installation.',
                'items' => ['Dépannage chaudière gaz/fioul','Chaudière à condensation','Pompe à chaleur air/air','Pompe à chaleur air/eau','Entretien annuel réglementaire','Radiateurs & plancher chauffant','Régulation & thermostat','Urgence chauffage hiver'],
            ],
            [
                'slug'  => 'climatisation',
                'title' => 'Climatisation & CVC',
                'icon'  => '❄️',
                'color' => '#2980b9',
                'desc'  => 'Installation, dépannage et entretien de climatisation, ventilation et traitement d\'air.',
                'items' => ['Climatisation split & multi-split','Gainable & cassette','VMC & ventilation','Traitement d\'air','Entretien saisonnier','Dépannage urgence','Recharge fluide frigorigène','Systèmes réversibles'],
            ],
        ],
    ];
}

/* ═══════════════════════════════════════════════════
   POURQUOI NOUS CHOISIR (section éditable admin)
═══════════════════════════════════════════════════ */
function why_us_settings(): array
{
    $default = [
        'eyebrow' => 'Pourquoi nous choisir',
        'title'   => 'EMAE, votre expert multitechnique de confiance',
        'lead'    => 'Des artisans qualifiés, des délais respectés, des devis clairs. Chaque intervention est réalisée avec rigueur et professionnalisme.',
        'items'   => [
            ['icon_type'=>'emoji','icon'=>'⚡','icon_img'=>'','title'=>'Urgences 24h/24',        'text'=>'Astreinte 24h/24 et 7j/7 pour les pannes bloquantes. Disponibilité et délai confirmés par téléphone avant déplacement.'],
            ['icon_type'=>'emoji','icon'=>'📋','icon_img'=>'','title'=>'Devis gratuit et clair', 'text'=>'Le prix est annoncé avant toute intervention. Aucune surprise sur la facture finale. Devis sans engagement.'],
            ['icon_type'=>'emoji','icon'=>'🔒','icon_img'=>'','title'=>'Artisans qualifiés',     'text'=>'Nos techniciens sont formés et qualifiés pour l\'ensemble de nos métiers techniques.'],
            ['icon_type'=>'emoji','icon'=>'📍','icon_img'=>'','title'=>'Présence locale',        'text'=>'Des techniciens installés dans votre secteur, qui interviennent rapidement sur site.'],
            ['icon_type'=>'emoji','icon'=>'🛠️','icon_img'=>'','title'=>'Tous types d\'interventions','text'=>'Urgence, installation, entretien préventif, mise aux normes — un seul interlocuteur pour tous vos besoins.'],
            ['icon_type'=>'emoji','icon'=>'🧾','icon_img'=>'','title'=>'Facture détaillée',      'text'=>'Diagnostic, travaux et pièces détaillés sur la facture, remise en fin d\'intervention.'],
            ['icon_type'=>'emoji','icon'=>'🏗️','icon_img'=>'','title'=>'Particuliers & pros',   'text'=>'Logements, commerces, bureaux, copropriétés — organisation adaptée à chaque contexte.'],
            ['icon_type'=>'emoji','icon'=>'📞','icon_img'=>'','title'=>'Disponible 24h/7j',      'text'=>'Notre astreinte est active en permanence pour les situations d\'urgence qui n\'attendent pas.'],
        ],
    ];
    $saved = get_json_setting('why_us_settings', []);
    $out = array_merge($default, array_filter($saved, fn($v) => $v !== '' && $v !== null && $v !== []));
    if (!isset($out['items']) || !is_array($out['items']) || empty($out['items'])) {
        $out['items'] = $default['items'];
    }
    return json_block_inline($out, 'why_us_settings');
}

/* ═══════════════════════════════════════════════════
   SERVICE CARDS V14 (avec image + badge + desc)
═══════════════════════════════════════════════════ */
function service_cards_v14(): array
{
    $default = [
        ['title'=>'Électricité',          'image'=>'','link'=>'electricite',  'badge'=>'Urgence 24h/7j', 'desc'=>'Dépannage, installation, mise aux normes, rénovation électrique.',
         'tags'=>['Dépannage','Installation','Mise aux normes'], 'placeholder_icon'=>'⚡'],
        ['title'=>'Plomberie',            'image'=>'','link'=>'plomberie',    'badge'=>'Fuite & urgence','desc'=>'Fuite d\'eau, sanitaires, débouchage, entretien réseau.',
         'tags'=>['Fuite','Sanitaires','Entretien'], 'placeholder_icon'=>'💧'],
        ['title'=>'Chauffage & PAC',      'image'=>'','link'=>'chauffage',    'badge'=>'Chaudière & PAC','desc'=>'Chaudière gaz/fioul, pompe à chaleur, entretien, dépannage.',
         'tags'=>['Chaudière','PAC','Entretien annuel'], 'placeholder_icon'=>'🔥'],
        ['title'=>'Climatisation CVC',    'image'=>'','link'=>'climatisation','badge'=>'CVC & clim',    'desc'=>'Installation, dépannage et entretien de climatisation.',
         'tags'=>['Clim','CVC','Installation'], 'placeholder_icon'=>'❄️'],
        // Ces titres sont aussi ceux du formulaire de devis : les garder identiques évite les doublons.
        ['title'=>'VMC & Ventilation',    'image'=>'assets/img/services/vmc.jpg','link'=>'vmc',          'badge'=>'Qualité de l\'air','desc'=>'Installation, dépannage et entretien de VMC simple et double flux.',
         'tags'=>['VMC','Entretien','Humidité'], 'placeholder_icon'=>'🌬️'],
        ['title'=>'Portail & Visiophone', 'image'=>'assets/img/services/portail.jpg','link'=>'portail',      'badge'=>'Portail bloqué','desc'=>'Motorisation de portail, visiophone, interphone et contrôle d\'accès.',
         'tags'=>['Portail','Visiophone','Interphone'], 'placeholder_icon'=>'🚪'],
        ['title'=>'Automatismes & Électroménager','image'=>'assets/img/services/electromenager.jpg','link'=>'automatisme','badge'=>'Volets & domotique','desc'=>'Volets roulants, stores, domotique et dépannage du gros électroménager.',
         'tags'=>['Volets','Domotique','Électroménager'], 'placeholder_icon'=>'🤖'],
    ];
    $cards = get_json_setting('home_service_cards_v14', $default);
    if (!$cards) return $default;
    $out = [];
    foreach ($default as $i => $fallback) {
        $c = is_array($cards[$i] ?? null) ? $cards[$i] : [];
        $out[] = [
            'title' => trim((string)($c['title'] ?? '')) ?: $fallback['title'],
            // Sans image choisie dans l'admin : la photo fournie avec le site (VMC, portail, électroménager).
            'image' => trim((string)($c['image'] ?? '')) ?: $fallback['image'],
            'link'  => trim((string)($c['link']  ?? '')) ?: $fallback['link'],
            'badge' => trim((string)($c['badge'] ?? '')) ?: $fallback['badge'],
            'desc'  => trim((string)($c['desc']  ?? '')) ?: $fallback['desc'],
            'tags'  => is_array($c['tags'] ?? null) ? $c['tags'] : $fallback['tags'],
            'placeholder_icon' => trim((string)($c['placeholder_icon'] ?? '')) ?: $fallback['placeholder_icon'],
        ];
    }
    return $out;
}

/**
 * Photo de fond d'une page métier : celle choisie dans l'admin (Images hero services),
 * sinon la photo fournie avec le site pour les métiers qui en ont une.
 */
function service_hero_image(string $trade): string
{
    $img = trim((string)setting('svc_'.$trade.'_hero_image', ''));
    if ($img !== '') return $img;
    return [
        'vmc'         => 'assets/img/services/vmc.jpg',
        'portail'     => 'assets/img/services/visiophone.jpg',
        'automatisme' => 'assets/img/services/electromenager.jpg',
    ][$trade] ?? '';
}

/* ═══════════════════════════════════════════════════
   VARIABLES DE LOCALISATION DANS LES TEXTES

   La détection de localisation par adresse IP a été retirée :
   les VPN et les réseaux mobiles la rendaient fausse, et elle
   envoyait l'IP de chaque visiteur à un service tiers.

   Les variables restent utilisables dans les textes. Elles se
   résolvent désormais de façon fixe et prévisible : selon la
   zone consultée quand il y en a une, sinon selon les valeurs
   définies dans l'administration.
═══════════════════════════════════════════════════ */

/**
 * Conservée pour les gabarits qui l'appellent encore. Les variables sont
 * désormais résolues par setting() pour l'ensemble du site ; cet appel
 * reste sans effet sur un texte déjà traité.
 */
function geo_replace(string $text): string
{
    return function_exists('zone_vars_apply') ? zone_vars_apply($text) : $text;
}

/* ═══════════════════════════════════════════════════
   DESIGN SETTINGS (opacités, couleurs avancées)
═══════════════════════════════════════════════════ */
function design_settings(): array
{
    return [
        /* Hero */
        'hero_bg_from'          => setting('hero_bg_from',           '#07102a'),
        'hero_bg_to'            => setting('hero_bg_to',             '#152555'),
        'hero_glow_1'           => setting('hero_glow_1',            'rgba(238,125,26,.18)'),
        'hero_glow_2'           => setting('hero_glow_2',            'rgba(21,37,85,.8)'),
        'hero_overlay_opacity'  => setting('hero_overlay_opacity',   '0.5'),
        /* Cards services */
        'card_overlay_opacity'  => setting('card_overlay_opacity',   '0.45'),
        /* Couleurs foncées */
        'color_dark'            => setting('color_dark',             '#07102a'),
        'color_dark_card'       => setting('color_dark_card',        '#0f1e3d'),
        'color_navy'            => setting('color_navy',             '#0b1641'),
        'color_navy_2'          => setting('color_navy_2',           '#152555'),
        'color_navy_3'          => setting('color_navy_3',           '#1e3370'),
        'color_line'            => setting('color_line',             'rgba(255,255,255,.09)'),
        /* Textes */
        'color_text'            => setting('color_text',             '#e8ecf5'),
        'color_text_muted'      => setting('color_text_muted',       '#8fa0c4'),
        /* Primaires */
        'color_primary'         => setting('color_primary',          '#ee7d1a'),
        'color_primary_hover'   => setting('color_primary_hover',    '#c95f0b'),
        /* Typo */
        'font_heading'          => setting('font_heading',           'Montserrat'),
        'font_body'             => setting('font_body',              'Inter'),
    ];
}

/* ═══════════════════════════════════════════════════
   DISPATCHER AUTH
═══════════════════════════════════════════════════ */
function require_dispatcher_auth(): array
{
    boot_session();
    if (empty($_SESSION['disp_id'])) { redirect_to('dispatcher/login.php'); }
    try {
        $d = db_fetch("SELECT * FROM dispatchers WHERE id = ? AND status = 'actif'", [(int)$_SESSION['disp_id']]);
    } catch (Throwable $e) { $d = null; }
    if (!$d) { unset($_SESSION['disp_id'], $_SESSION['disp_name']); redirect_to('dispatcher/login.php'); }
    return $d;
}

function dispatcher_login_check(string $email, string $password): ?array
{
    $email = trim($email);
    if (login_is_locked('dispatcher', $email)) return null;
    try {
        $r = db_fetch("SELECT * FROM dispatchers WHERE email = ? AND status = 'actif'", [$email]);
        $ok = $r && password_verify($password, (string)$r['password_hash']);
    } catch (Throwable $e) { $r = null; $ok = false; }
    login_record_attempt('dispatcher', $email, $ok);
    return $ok ? $r : null;
}

/**
 * Le compte dispatcher a-t-il accès au tableau de bord financier ?
 * Droit accordé compte par compte dans Administration → Dispatchers (désactivé par défaut).
 */
function dispatcher_can_finance(array $disp): bool
{
    return (int)($disp['can_finance'] ?? 0) === 1;
}

/** Bloque la page (403) si le compte n'a pas l'accès aux finances. */
function require_finance_access(array $disp): void
{
    if (dispatcher_can_finance($disp)) return;
    http_response_code(403);
    while (ob_get_level() > 0) ob_end_clean();
    echo '<!DOCTYPE html><html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Accès refusé</title></head>'
        .'<body style="font-family:Arial,sans-serif;background:#f4f6fb;color:#16243f;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;">'
        .'<div style="background:#fff;border-radius:12px;padding:2rem;max-width:440px;box-shadow:0 4px 24px rgba(0,0,0,.08);">'
        .'<h1 style="font-size:1.2rem;margin:0 0 .6rem;">Accès réservé</h1>'
        .'<p style="margin:0 0 1rem;line-height:1.5;">Le tableau de bord financier n\'est pas ouvert à votre compte. Demandez à l\'administrateur de cocher « Accès aux finances » sur votre compte dispatcher.</p>'
        .'<a href="'.htmlspecialchars(url_for('dispatcher/index.php'), ENT_QUOTES, 'UTF-8').'" style="color:#F07B1D;font-weight:700;">← Retour au dispatcher</a></div></body></html>';
    exit;
}

function all_dispatchers(): array
{
    try { return db_fetch_all("SELECT id, name, email, phone, status, can_finance FROM dispatchers ORDER BY name"); }
    catch (Throwable $e) { return []; }
}

/* ═══════════════════════════════════════════════════
   CLIENTS
═══════════════════════════════════════════════════ */
function all_clients_list(int $limit = 200, int $offset = 0): array
{
    // Entiers insérés directement : liés en paramètre, MySQL les recevrait comme du texte et refuserait la requête.
    $limit = max(1, $limit); $offset = max(0, $offset);
    try { return db_fetch_all("SELECT * FROM clients ORDER BY lastname, firstname LIMIT {$limit} OFFSET {$offset}"); }
    catch (Throwable $e) { return []; }
}

function get_client_by_id(int $id): ?array
{
    try { $r = db_fetch("SELECT * FROM clients WHERE id = ?", [$id]); return $r ?: null; }
    catch (Throwable $e) { return null; }
}

function search_clients(string $q): array
{
    // Chaque mot doit se retrouver dans au moins un champ : « Jean Petit Dijon » fonctionne.
    // Un numéro de téléphone tapé avec espaces ou points est cherché en entier.
    if (preg_match('/^[\d\s.+-]{4,}$/', trim($q))) {
        $digits = preg_replace('/\D/', '', $q);
        try { return db_fetch_all("SELECT * FROM clients WHERE REPLACE(REPLACE(REPLACE(phone,' ',''),'.',''),'-','') LIKE ? ORDER BY lastname, firstname LIMIT 20", ['%'.$digits.'%']); }
        catch (Throwable $e) { return []; }
    }
    $words = array_slice(preg_split('/\s+/', trim($q), -1, PREG_SPLIT_NO_EMPTY) ?: [], 0, 5);
    if (!$words) return [];
    $where = []; $params = [];
    foreach ($words as $w) {
        $like = '%'.$w.'%';
        $digits = preg_replace('/\D/', '', $w);
        $cond = 'lastname LIKE ? OR firstname LIKE ? OR city LIKE ? OR postal_code LIKE ? OR address LIKE ? OR email LIKE ? OR phone LIKE ?';
        array_push($params, $like, $like, $like, $like, $like, $like, $like);
        if (strlen($digits) >= 3) { $cond .= " OR REPLACE(REPLACE(REPLACE(phone,' ',''),'.',''),'-','') LIKE ?"; $params[] = '%'.$digits.'%'; }
        $where[] = '('.$cond.')';
    }
    try { return db_fetch_all('SELECT * FROM clients WHERE '.implode(' AND ', $where).' ORDER BY lastname, firstname LIMIT 20', $params); }
    catch (Throwable $e) { return []; }
}

function create_client(array $data): int
{
    db_execute("INSERT INTO clients (lastname, firstname, phone, email, address, postal_code, city, floor, digicode, access_info, notes) VALUES (?,?,?,?,?,?,?,?,?,?,?)", [
        trim((string)($data['lastname'] ?? '')), trim((string)($data['firstname'] ?? '')),
        trim((string)($data['phone'] ?? '')), trim((string)($data['email'] ?? '')),
        trim((string)($data['address'] ?? '')), trim((string)($data['postal_code'] ?? '')),
        trim((string)($data['city'] ?? '')), trim((string)($data['floor'] ?? '')),
        trim((string)($data['digicode'] ?? '')), trim((string)($data['access_info'] ?? '')),
        trim((string)($data['notes'] ?? '')),
    ]);
    return db_last_id();
}

function update_client(int $id, array $data): void
{
    db_execute("UPDATE clients SET lastname=?,firstname=?,phone=?,email=?,address=?,postal_code=?,city=?,floor=?,digicode=?,access_info=?,notes=? WHERE id=?", [
        trim((string)($data['lastname'] ?? '')), trim((string)($data['firstname'] ?? '')),
        trim((string)($data['phone'] ?? '')), trim((string)($data['email'] ?? '')),
        trim((string)($data['address'] ?? '')), trim((string)($data['postal_code'] ?? '')),
        trim((string)($data['city'] ?? '')), trim((string)($data['floor'] ?? '')),
        trim((string)($data['digicode'] ?? '')), trim((string)($data['access_info'] ?? '')),
        trim((string)($data['notes'] ?? '')), $id,
    ]);
}

function client_intervention_count(int $client_id): int
{
    try { return (int)(db_fetch("SELECT COUNT(*) AS c FROM interventions WHERE client_id = ?", [$client_id])['c'] ?? 0); }
    catch (Throwable $e) { return 0; }
}

/* ═══════════════════════════════════════════════════
   INTERVENTION CONFIG
═══════════════════════════════════════════════════ */
function intervention_status_config(): array
{
    // Ordre = ordre du circuit (voir includes/workflow.php). Les anciens codes restent reconnus.
    return [
        'nouveau'           => ['label' => 'Nouveau',              'color' => '#3b82f6', 'bg' => 'rgba(59,130,246,.15)'],
        'a_assigner'        => ['label' => 'À assigner',           'color' => '#6366f1', 'bg' => 'rgba(99,102,241,.15)'],
        'confirmé'          => ['label' => 'Confirmé',             'color' => '#8b5cf6', 'bg' => 'rgba(139,92,246,.15)'],
        'assigné'           => ['label' => 'Assignée',             'color' => '#f59e0b', 'bg' => 'rgba(245,158,11,.15)'],
        'en_route'          => ['label' => 'En route',             'color' => '#06b6d4', 'bg' => 'rgba(6,182,212,.15)'],
        'sur_place'         => ['label' => 'Sur place',            'color' => '#10b981', 'bg' => 'rgba(16,185,129,.15)'],
        'a_revoir'          => ['label' => 'Rapport à revoir',     'color' => '#dc2626', 'bg' => 'rgba(220,38,38,.12)'],
        'rapport_rendu'     => ['label' => 'Rapport rendu',        'color' => '#22c55e', 'bg' => 'rgba(34,197,94,.15)'],
        'rapport_verifie'   => ['label' => 'Rapport vérifié',      'color' => '#16a34a', 'bg' => 'rgba(22,163,74,.15)'],
        'facture_brouillon' => ['label' => 'Facture à valider',    'color' => '#f97316', 'bg' => 'rgba(249,115,22,.15)'],
        'facture_validee'   => ['label' => 'Facture validée',      'color' => '#ea580c', 'bg' => 'rgba(234,88,12,.15)'],
        'facture_envoyee'   => ['label' => 'Facture envoyée',      'color' => '#ec4899', 'bg' => 'rgba(236,72,153,.15)'],
        'payé'              => ['label' => 'Payée',                'color' => '#14b8a6', 'bg' => 'rgba(20,184,166,.15)'],
        'cloturee'          => ['label' => 'Clôturée',             'color' => '#64748b', 'bg' => 'rgba(100,116,139,.15)'],
        'terminé'           => ['label' => 'Terminée',             'color' => '#22c55e', 'bg' => 'rgba(34,197,94,.15)'],
        'devis_envoyé'      => ['label' => 'Devis envoyé',         'color' => '#f97316', 'bg' => 'rgba(249,115,22,.15)'],
        'facturé'           => ['label' => 'Facturée',             'color' => '#ec4899', 'bg' => 'rgba(236,72,153,.15)'],
        'annulé'            => ['label' => 'Annulée',              'color' => '#ef4444', 'bg' => 'rgba(239,68,68,.15)'],
    ];
}

function intervention_category_config(): array
{
    return [
        'electricite'    => ['label' => 'Électricité',   'icon' => '', 'color' => '#fbbf24'],
        'debouchage'     => ['label' => 'Débouchage',    'icon' => '', 'color' => '#0ea5e9'],
        'plomberie'      => ['label' => 'Plomberie',     'icon' => '', 'color' => '#60a5fa'],
        'chauffage'      => ['label' => 'Chauffage',     'icon' => '', 'color' => '#f87171'],
        'climatisation'  => ['label' => 'Climatisation', 'icon' => '', 'color' => '#34d399'],
        'multitechnique' => ['label' => 'Multitechnique','icon' => '', 'color' => '#a78bfa'],
        'ascenseur'      => ['label' => 'Ascenseur',     'icon' => '', 'color' => '#fb923c'],
        'maintenance'    => ['label' => 'Maintenance',   'icon' => '', 'color' => '#94a3b8'],
        'depannage'      => ['label' => 'Dépannage',     'icon' => '', 'color' => '#f472b6'],
        'renovation'     => ['label' => 'Rénovation',    'icon' => '', 'color' => '#6ee7b7'],
    ];
}

function intervention_status_badge(string $status): string
{
    $cfg = intervention_status_config();
    $c = $cfg[$status] ?? ['label' => $status, 'color' => '#8c99ad', 'bg' => 'rgba(140,153,173,.15)'];
    return '<span style="display:inline-flex;align-items:center;gap:.35rem;padding:.15rem .6rem;border-radius:99px;font-size:.74rem;font-weight:600;white-space:nowrap;color:#17223b;background:'.$c['color'].'1f;">'
        . '<span style="width:7px;height:7px;border-radius:50%;background:'.$c['color'].';flex-shrink:0;"></span>'
        . e($c['label']) . '</span>';
}

function intervention_category_badge(string $cat): string
{
    $cfg = intervention_category_config();
    $c = $cfg[$cat] ?? ['label' => $cat, 'icon' => '', 'color' => '#8c99ad'];
    return '<span style="display:inline-flex;align-items:center;gap:.35rem;font-size:.8rem;font-weight:500;color:#5a6a82;white-space:nowrap;">'
        . '<span style="width:8px;height:8px;border-radius:2px;background:'.$c['color'].';flex-shrink:0;"></span>'
        . e($c['label']) . '</span>';
}

/* ═══════════════════════════════════════════════════
   INTERVENTIONS CRUD
═══════════════════════════════════════════════════ */
function all_interventions(array $filters = []): array
{
    $where = ['1=1']; $params = [];
    if (!empty($filters['status'])) { $where[] = 'i.status = ?'; $params[] = $filters['status']; }
    if (!empty($filters['technician_id'])) { $where[] = 'i.technician_id = ?'; $params[] = (int)$filters['technician_id']; }
    if (!empty($filters['dispatcher_id'])) { $where[] = 'i.dispatcher_id = ?'; $params[] = (int)$filters['dispatcher_id']; }
    if (!empty($filters['category'])) { $where[] = 'i.category = ?'; $params[] = $filters['category']; }
    if (!empty($filters['urgency'])) { $where[] = 'i.urgency = 1'; }
    if (!empty($filters['date'])) { $where[] = 'i.scheduled_date = ?'; $params[] = $filters['date']; }
    if (!empty($filters['search'])) {
        $like = '%' . $filters['search'] . '%';
        $where[] = '(c.lastname LIKE ? OR c.firstname LIKE ? OR c.phone LIKE ? OR c.city LIKE ? OR i.ref LIKE ? OR i.type_label LIKE ?)';
        array_push($params, $like, $like, $like, $like, $like, $like);
    }
    $sql = "SELECT i.*, c.lastname, c.firstname, c.phone AS client_phone, c.address, c.city AS client_city, c.postal_code,
                   t.name AS tech_name, t.phone AS tech_phone, d.name AS disp_name
            FROM interventions i
            LEFT JOIN clients c ON c.id = i.client_id
            LEFT JOIN technicians t ON t.id = i.technician_id
            LEFT JOIN dispatchers d ON d.id = i.dispatcher_id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY i.urgency DESC, COALESCE(i.scheduled_date,'9999-12-31') ASC, i.id DESC";
    try { return db_fetch_all($sql, $params); }
    catch (Throwable $e) { return []; }
}

function get_intervention_by_id(int $id): ?array
{
    try {
        $r = db_fetch(
            "SELECT i.*, c.lastname, c.firstname, c.phone AS client_phone, c.email AS client_email,
                    c.address, c.city AS client_city, c.postal_code, c.floor, c.digicode, c.access_info,
                    t.name AS tech_name, t.phone AS tech_phone, t.email AS tech_email,
                    d.name AS disp_name
             FROM interventions i
             LEFT JOIN clients c ON c.id = i.client_id
             LEFT JOIN technicians t ON t.id = i.technician_id
             LEFT JOIN dispatchers d ON d.id = i.dispatcher_id
             WHERE i.id = ?", [$id]);
        return $r ?: null;
    } catch (Throwable $e) { return null; }
}

function create_intervention(array $data): int
{
    $ref = 'INT-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
    db_execute(
        "INSERT INTO interventions (ref,client_id,dispatcher_id,technician_id,scheduled_date,scheduled_time,
         duration_estimate,urgency,priority,category,type_label,installation_type,description,fault_reported,
         materials_needed,notes_admin,quote_accepted,amount_ht,amount_ttc,deposit,remaining,payment_method,
         status,latitude,longitude) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)",
        [
            $ref,
            (int)($data['client_id'] ?? 0),
            !empty($data['dispatcher_id']) ? (int)$data['dispatcher_id'] : null,
            !empty($data['technician_id']) ? (int)$data['technician_id'] : null,
            $data['scheduled_date'] ?: null,
            $data['scheduled_time'] ?: null,
            (int)($data['duration_estimate'] ?? 60),
            (int)($data['urgency'] ?? 0),
            $data['priority'] ?? 'normale',
            $data['category'] ?: null,
            $data['type_label'] ?: null,
            $data['installation_type'] ?: null,
            $data['description'] ?: null,
            $data['fault_reported'] ?: null,
            $data['materials_needed'] ?: null,
            $data['notes_admin'] ?: null,
            (int)($data['quote_accepted'] ?? 0),
            !empty($data['amount_ht']) ? (float)$data['amount_ht'] : null,
            !empty($data['amount_ttc']) ? (float)$data['amount_ttc'] : null,
            !empty($data['deposit']) ? (float)$data['deposit'] : null,
            !empty($data['remaining']) ? (float)$data['remaining'] : null,
            $data['payment_method'] ?: null,
            $data['status'] ?? 'nouveau',
            !empty($data['latitude']) ? (float)$data['latitude'] : null,
            !empty($data['longitude']) ? (float)$data['longitude'] : null,
        ]
    );
    return db_last_id();
}

function update_intervention(int $id, array $data): void
{
    $allowed = ['technician_id','dispatcher_id','scheduled_date','scheduled_time','duration_estimate',
                'urgency','priority','category','type_label','installation_type','description','fault_reported',
                'materials_needed','notes_admin','quote_accepted','amount_ht','amount_ttc','deposit','remaining',
                'payment_method','status','tech_report','tech_photos','tech_materials_used','tech_time_spent',
                'tech_signature','tech_client_name','tech_started_at','tech_arrived_at','tech_completed_at',
                'tech_fault_label','tech_realizable','tech_bad_use','tech_device_number','tech_elevator_restored',
                'tech_ticket_time','tech_close_time','tech_notes_extra',
                'client_signature','photos_required','payment_status','paid_at',
                'housing_over_2y','vat_rate','tech_response','tech_response_at','tech_refusal_reason',
                'tech_diagnostic','tech_lines','tech_job_completed','tech_incomplete_reason','tech_return_visit','review_message',
                'latitude','longitude'];
    $sets = []; $params = [];
    foreach ($allowed as $f) {
        if (array_key_exists($f, $data)) { $sets[] = "$f = ?"; $params[] = $data[$f]; }
    }
    if (empty($sets)) return;
    $params[] = $id;
    db_execute("UPDATE interventions SET " . implode(', ', $sets) . " WHERE id = ?", $params);
}

/** Liste de matériel lisible : accepte du texte libre ou la liste JSON du formulaire. */
function materials_text(?string $raw): string
{
    $raw = trim((string)$raw);
    $list = json_decode($raw, true);
    if (!is_array($list)) return $raw;
    $out = [];
    foreach ($list as $m) {
        if (is_array($m)) {
            $name = trim((string)($m['name'] ?? $m['label'] ?? ''));
            $qty  = trim((string)($m['qty'] ?? '').' '.(string)($m['unit'] ?? ''));
            if ($name !== '') $out[] = $name.($qty !== '' ? ' ('.$qty.')' : '');
        } elseif (is_scalar($m) && trim((string)$m) !== '') {
            $out[] = trim((string)$m);
        }
    }
    return implode("\n", $out);
}

/** Pastille « Payé / Non payé / À renseigner ». */
function payment_badge(?string $status): string
{
    [$label, $fg, $bg] = match ((string)$status) {
        'payé'     => ['Payé', '#166534', '#dcfce7'],
        'non_payé' => ['Non payé', '#991b1b', '#fee2e2'],
        default    => ['À renseigner', '#5a6a82', '#eef1f5'],
    };
    return '<span style="display:inline-block;padding:.12rem .55rem;border-radius:99px;font-size:.74rem;font-weight:600;color:'.$fg.';background:'.$bg.';white-space:nowrap;">'.$label.'</span>';
}

/** Photos que le dispatcher demande au technicien (liste de libellés). */
function intervention_photos_required(array $iv): array
{
    $raw = json_decode((string)($iv['photos_required'] ?? ''), true);
    if (!is_array($raw)) return [];
    return array_values(array_unique(array_filter(array_map(static fn($v) => trim((string)$v), $raw), static fn($v) => $v !== '')));
}

/** Chemins des photos d'une intervention, quel que soit leur format d'enregistrement
 *  (ancienne liste de chemins ou nouvelle liste {type, path}). */
function intervention_photo_paths(array $iv): array
{
    $raw = json_decode((string)($iv['tech_photos'] ?? '[]'), true);
    $out = [];
    foreach (is_array($raw) ? $raw : [] as $ph) {
        $path = is_array($ph) ? (string)($ph['path'] ?? '') : (string)$ph;
        if ($path !== '') $out[] = $path;
    }
    return $out;
}

function log_intervention_history(int $id, ?string $from, string $to, string $actor_type, int $actor_id, string $actor_name, string $note = ''): void
{
    try {
        db_execute("INSERT INTO intervention_history (intervention_id,status_from,status_to,actor_type,actor_id,actor_name,note) VALUES (?,?,?,?,?,?,?)",
            [$id, $from, $to, $actor_type, $actor_id, $actor_name, $note]);
    } catch (Throwable $e) {}
}

function get_intervention_history(int $id): array
{
    try { return db_fetch_all("SELECT * FROM intervention_history WHERE intervention_id = ? ORDER BY created_at ASC", [$id]); }
    catch (Throwable $e) { return []; }
}

function get_client_interventions(int $client_id): array
{
    try { return db_fetch_all("SELECT i.*, t.name AS tech_name FROM interventions i LEFT JOIN technicians t ON t.id=i.technician_id WHERE i.client_id=? ORDER BY i.created_at DESC", [$client_id]); }
    catch (Throwable $e) { return []; }
}

/* ═══════════════════════════════════════════════════
   DISPATCHER KPIs
═══════════════════════════════════════════════════ */
function dispatcher_kpis(): array
{
    $today = date('Y-m-d');
    $ws    = date('Y-m-d', strtotime('monday this week'));
    $ms    = date('Y-m-01');
    $zero  = ['today_total'=>0,'waiting'=>0,'assigned'=>0,'in_progress'=>0,'done_today'=>0,'urgent'=>0,'late'=>0,'techs_active'=>0,'ca_today'=>0.0,'ca_week'=>0.0,'ca_month'=>0.0,'total'=>0];
    try {
        return [
            'today_total'  => (int)(db_fetch("SELECT COUNT(*) AS c FROM interventions WHERE scheduled_date=?",[$today])['c']??0),
            'waiting'      => (int)(db_fetch("SELECT COUNT(*) AS c FROM interventions WHERE status IN ('nouveau','confirmé')")['c']??0),
            'assigned'     => (int)(db_fetch("SELECT COUNT(*) AS c FROM interventions WHERE status='assigné'")['c']??0),
            'in_progress'  => (int)(db_fetch("SELECT COUNT(*) AS c FROM interventions WHERE status IN ('en_route','sur_place')")['c']??0),
            'done_today'   => (int)(db_fetch("SELECT COUNT(*) AS c FROM interventions WHERE status IN (".wf_sql_list(wf_field_done()).") AND DATE(tech_completed_at)=?",[$today])['c']??0),
            'urgent'       => (int)(db_fetch("SELECT COUNT(*) AS c FROM interventions WHERE urgency=1 AND status NOT IN (".wf_sql_list(wf_closed()).")")['c']??0),
            'late'         => (int)(db_fetch("SELECT COUNT(*) AS c FROM interventions WHERE scheduled_date<? AND scheduled_date IS NOT NULL AND status NOT IN (".wf_sql_list(wf_closed()).")",[$today])['c']??0),
            'techs_active' => (int)(db_fetch("SELECT COUNT(*) AS c FROM technicians WHERE status='actif'")['c']??0),
            'ca_today'     => (float)(db_fetch("SELECT COALESCE(SUM(amount_ht),0) AS s FROM interventions WHERE status IN (".wf_sql_list(wf_revenue()).") AND DATE(tech_completed_at)=?",[$today])['s']??0),
            'ca_week'      => (float)(db_fetch("SELECT COALESCE(SUM(amount_ht),0) AS s FROM interventions WHERE status IN (".wf_sql_list(wf_revenue()).") AND tech_completed_at>=?",[$ws])['s']??0),
            'ca_month'     => (float)(db_fetch("SELECT COALESCE(SUM(amount_ht),0) AS s FROM interventions WHERE status IN (".wf_sql_list(wf_revenue()).") AND tech_completed_at>=?",[$ms])['s']??0),
            'total'        => (int)(db_fetch("SELECT COUNT(*) AS c FROM interventions")['c']??0),
        ];
    } catch (Throwable $e) { return $zero; }
}

/* ═══════════════════════════════════════════════════
   SMS DISPATCHER
═══════════════════════════════════════════════════ */
function send_sms_dispatcher(string $to, string $message): bool
{
    // Le compte OVH configuré dans l'administration est la passerelle SMS du site.
    if (send_sms_ovh($to, $message)) return true;
    $apiUrl = setting('sms_api_url', '');
    $apiKey = setting('sms_api_key', '');
    $sender = setting('sms_sender',  'EMAE');
    if ($apiUrl === '' || $apiKey === '') return false;
    $to = preg_replace('/\s+/', '', $to);
    if (!preg_match('/^\+?[0-9]{8,15}$/', $to)) return false;
    try {
        $payload = json_encode(['to'=>$to,'message'=>$message,'sender'=>$sender,'api_key'=>$apiKey]);
        $ctx = stream_context_create(['http'=>[
            'method'=>'POST',
            'header'=>"Content-Type: application/json\r\nAuthorization: Bearer $apiKey\r\n",
            'content'=>$payload,'timeout'=>5,'ignore_errors'=>true,
        ]]);
        @file_get_contents($apiUrl, false, $ctx);
        return true;
    } catch (Throwable $e) { return false; }
}

/* ═══════════════════════════════════════════════════
   GEOCODING (Nominatim)
═══════════════════════════════════════════════════ */
function geocode_address(string $address, string $city = '', string $postal = ''): array
{
    $q = trim(preg_replace('/\s+/', ' ', $address . ' ' . $postal . ' ' . $city));
    if ($q === '') return ['lat' => null, 'lng' => null, 'reached' => false];
    $reached = false;
    $ctx = stream_context_create(['http' => ['timeout' => 3, 'ignore_errors' => true, 'header' => "User-Agent: EMAE-Dispatcher/1.0\r\n"]]);
    // Base Adresse Nationale (service public, France) : rapide et précis.
    try {
        $url  = 'https://api-adresse.data.gouv.fr/search/?limit=1&q=' . rawurlencode($q) . ($postal !== '' ? '&postcode=' . rawurlencode($postal) : '');
        $json = @file_get_contents($url, false, $ctx);
        $data = $json !== false ? json_decode($json, true) : null;
        if (is_array($data)) $reached = true;
        $c = $data['features'][0]['geometry']['coordinates'] ?? null;
        if (is_array($c) && count($c) === 2 && ($data['features'][0]['properties']['score'] ?? 0) > 0.3) {
            return ['lat' => (float)$c[1], 'lng' => (float)$c[0], 'reached' => true];
        }
    } catch (Throwable $e) {}
    // Même base d'adresses via la Géoplateforme de l'IGN (nouvelle adresse du service).
    try {
        $url  = 'https://data.geopf.fr/geocodage/search?limit=1&q=' . rawurlencode($q) . ($postal !== '' ? '&postcode=' . rawurlencode($postal) : '');
        $json = @file_get_contents($url, false, $ctx);
        $data = $json !== false ? json_decode($json, true) : null;
        if (is_array($data)) $reached = true;
        $c = $data['features'][0]['geometry']['coordinates'] ?? null;
        if (is_array($c) && count($c) === 2 && ($data['features'][0]['properties']['score'] ?? 0) > 0.3) {
            return ['lat' => (float)$c[1], 'lng' => (float)$c[0], 'reached' => true];
        }
    } catch (Throwable $e) {}
    // Repli : OpenStreetMap.
    try {
        $url  = 'https://nominatim.openstreetmap.org/search?q=' . rawurlencode($q . ' France') . '&format=json&limit=1&countrycodes=fr';
        $json = @file_get_contents($url, false, $ctx);
        if ($json !== false) {
            $data = json_decode($json, true);
            if (is_array($data)) $reached = true;
            if (is_array($data) && !empty($data[0])) return ['lat' => (float)$data[0]['lat'], 'lng' => (float)$data[0]['lon'], 'reached' => true];
        }
    } catch (Throwable $e) {}
    return ['lat' => null, 'lng' => null, 'reached' => $reached];
}

/** Complète les coordonnées GPS manquantes de quelques interventions (pour la carte). */
function geocode_missing_interventions(int $max = 8): int
{
    try {
        $rows = db_fetch_all(
            "SELECT i.id, c.address, c.city, c.postal_code FROM interventions i
             JOIN clients c ON c.id = i.client_id
             WHERE (i.latitude IS NULL OR i.longitude IS NULL)
               AND i.status NOT IN ('annulé','payé')
               AND (COALESCE(c.city,'') <> '' OR COALESCE(c.postal_code,'') <> '')
             ORDER BY i.id DESC LIMIT " . max(1, $max)
        );
    } catch (Throwable $e) { return 0; }
    $n = 0;
    foreach ($rows as $r) {
        $g = geocode_address((string)$r['address'], (string)$r['city'], (string)$r['postal_code']);
        if ($g['lat'] !== null) {
            update_intervention((int)$r['id'], ['latitude' => $g['lat'], 'longitude' => $g['lng']]);
            $n++;
        } elseif ($g['reached']) {
            // Service joint mais adresse introuvable : 0/0 évite de la rechercher à chaque affichage.
            update_intervention((int)$r['id'], ['latitude' => 0, 'longitude' => 0]);
        } else {
            break; // service injoignable : on réessaiera plus tard
        }
    }
    return $n;
}

/* ═══════════════════════════════════════════════════
   TASKS / REMINDERS
═══════════════════════════════════════════════════ */
function all_tasks(array $filters = []): array
{
    $where = ['1=1']; $params = [];
    if (!empty($filters['technician_id'])) { $where[] = 't2.id = ?'; $params[] = (int)$filters['technician_id']; }
    if (!empty($filters['urgent'])) { $where[] = 'tk.urgent = 1'; }
    if (!empty($filters['status'])) { $where[] = 'tk.status = ?'; $params[] = $filters['status']; }
    $sql = "SELECT tk.*, t2.name AS tech_name, d.name AS disp_name
            FROM tasks tk
            LEFT JOIN technicians t2 ON t2.id = tk.technician_id
            LEFT JOIN dispatchers d  ON d.id  = tk.dispatcher_id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY tk.urgent DESC, tk.due_date ASC, tk.due_time ASC, tk.id DESC";
    try { return db_fetch_all($sql, $params); }
    catch (Throwable $e) { return []; }
}

function create_task(array $data): int
{
    db_execute(
        "INSERT INTO tasks (dispatcher_id, technician_id, title, description, due_date, due_time, urgent, status)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
        [
            !empty($data['dispatcher_id']) ? (int)$data['dispatcher_id'] : null,
            !empty($data['technician_id']) ? (int)$data['technician_id'] : null,
            (string)$data['title'],
            $data['description'] ?: null,
            $data['due_date'] ?: null,
            $data['due_time'] ?: null,
            (int)($data['urgent'] ?? 0),
            $data['status'] ?? 'pending',
        ]
    );
    return db_last_id();
}

function update_task(int $id, array $data): void
{
    $allowed = ['technician_id','dispatcher_id','title','description','due_date','due_time','urgent','status'];
    $sets = []; $params = [];
    foreach ($allowed as $f) {
        if (array_key_exists($f, $data)) { $sets[] = "$f = ?"; $params[] = $data[$f]; }
    }
    if (empty($sets)) return;
    $params[] = $id;
    db_execute("UPDATE tasks SET " . implode(', ', $sets) . " WHERE id = ?", $params);
}

function delete_task(int $id): void
{
    try { db_execute("DELETE FROM tasks WHERE id = ?", [$id]); }
    catch (Throwable $e) {}
}

function get_pending_tasks_for_tech(int $techId): array
{
    try {
        return db_fetch_all(
            "SELECT tk.*, d.name AS disp_name FROM tasks tk
             LEFT JOIN dispatchers d ON d.id = tk.dispatcher_id
             WHERE tk.technician_id = ? AND tk.status = 'pending'
             ORDER BY tk.urgent DESC, tk.due_date ASC, tk.due_time ASC, tk.id DESC",
            [$techId]
        );
    } catch (Throwable $e) { return []; }
}

function dispatcher_pending_tasks_count(): int
{
    try {
        return (int)(db_fetch("SELECT COUNT(*) AS c FROM tasks WHERE status = 'pending'")['c'] ?? 0);
    } catch (Throwable $e) { return 0; }
}

/* ═══════════════════════════════════════════════════
   PRESET ITEMS
═══════════════════════════════════════════════════ */
function get_presets(string $type, string $category = ''): array
{
    try {
        if ($category !== '') {
            return db_fetch_all("SELECT * FROM preset_items WHERE type=? AND (category=? OR category='') AND active=1 ORDER BY sort_order,label", [$type, $category]);
        }
        return db_fetch_all("SELECT * FROM preset_items WHERE type=? AND active=1 ORDER BY COALESCE(category,''), sort_order, label", [$type]);
    } catch (Throwable $e) { return []; }
}

function save_preset(string $type, string $label, string $category = ''): int
{
    $existing = db_fetch("SELECT id FROM preset_items WHERE type=? AND label=? AND (category=? OR category='')", [$type, $label, $category]);
    if ($existing) return (int)$existing['id'];
    db_execute("INSERT INTO preset_items (type, category, label) VALUES (?,?,?)", [$type, $category, $label]);
    return (int)db_last_id();
}

function delete_preset(int $id): void
{
    try { db_execute("DELETE FROM preset_items WHERE id = ?", [$id]); }
    catch (Throwable $e) {}
}

function all_presets_grouped(): array
{
    $out = ['intervention_type' => [], 'material' => [], 'photo_type' => []];
    try {
        $rows = db_fetch_all("SELECT * FROM preset_items WHERE active=1 ORDER BY type, category, sort_order, label");
        foreach ($rows as $row) {
            $t = (string)($row['type'] ?? '');
            if (isset($out[$t])) $out[$t][] = $row;
        }
    } catch (Throwable $e) {}
    return $out;
}

/* ═══════════════════════════════════════════════════
   ZONES MULTI-ZONES
═══════════════════════════════════════════════════ */
function all_zones(): array
{
    try { return db_fetch_all("SELECT * FROM zones ORDER BY sort_order ASC, name ASC"); }
    catch (Throwable $e) { return []; }
}

function all_active_zones(): array
{
    try { return db_fetch_all("SELECT * FROM zones WHERE status=1 ORDER BY sort_order ASC, name ASC"); }
    catch (Throwable $e) { return []; }
}

function get_zone_by_slug(string $slug): ?array
{
    if ($slug === '') return null;
    try {
        $z = db_fetch("SELECT * FROM zones WHERE slug=? LIMIT 1", [$slug]);
        return $z ?: null;
    } catch (Throwable $e) { return null; }
}

function get_zone_by_id(int $id): ?array
{
    try {
        $z = db_fetch("SELECT * FROM zones WHERE id=? LIMIT 1", [$id]);
        return $z ?: null;
    } catch (Throwable $e) { return null; }
}

function zone_field(array $zone, string $field, string $default = ''): string
{
    $val = trim((string)($zone[$field] ?? ''));
    return $val !== '' ? $val : $default;
}

function zone_faq(array $zone): array
{
    $raw = $zone['faq'] ?? null;
    if ($raw === null || $raw === '') return [];
    $decoded = json_decode((string)$raw, true);
    return is_array($decoded) ? $decoded : [];
}

function zone_cities(array $zone): array
{
    $raw = trim((string)($zone['cities'] ?? ''));
    if ($raw === '') return [];
    return array_values(array_filter(array_map('trim', explode('|', $raw))));
}

function zone_url(string $slug, string $page = ''): string
{
    $base = url_for($slug . '/');
    if ($page === '' || $page === 'home') return $base;
    return rtrim($base, '/') . '/' . ltrim($page, '/');
}

function create_zone(array $data): int
{
    db_execute(
        "INSERT INTO zones (slug,name,status,meta_title,meta_description,hero_h1,hero_subtitle,hero_cta_label,cities,postal_codes,faq,mentions_legales,sort_order) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)",
        [$data['slug'],$data['name'],(int)($data['status']??1),
         $data['meta_title']??null,$data['meta_description']??null,
         $data['hero_h1']??null,$data['hero_subtitle']??null,$data['hero_cta_label']??null,
         $data['cities']??null,$data['postal_codes']??null,
         $data['faq']??null,$data['mentions_legales']??null,(int)($data['sort_order']??0)]
    );
    return db_last_id();
}

/**
 * Déplace les textes d'une zone lorsqu'elle change d'adresse.
 *
 * Les personnalisations sont rangées sous « z:{slug}:… ». Sans ce
 * déplacement, renommer le slug les laisserait orphelines : la zone
 * paraîtrait vidée de tout son contenu.
 */
function zone_move_settings(string $ancien, string $nouveau): int
{
    if ($ancien === '' || $nouveau === '' || $ancien === $nouveau) return 0;
    $prefixe = 'z:'.$ancien.':';
    $n = 0;
    foreach (settings_cache() as $k => $v) {
        if (!str_starts_with($k, $prefixe)) continue;
        $cible = 'z:'.$nouveau.':'.substr($k, strlen($prefixe));
        db_execute('DELETE FROM settings WHERE setting_key = ?', [$cible]);
        db_execute('UPDATE settings SET setting_key = ? WHERE setting_key = ?', [$cible, $k]);
        $n++;
    }
    if ($n > 0) { settings_cache(true); if (function_exists('admin_inline_keys')) admin_inline_keys(true); }
    return $n;
}

function update_zone(int $id, array $data): void
{
    // Un changement d'adresse doit emmener les textes de la zone avec lui.
    $avant = get_zone_by_id($id);
    if ($avant && (string)$avant['slug'] !== (string)$data['slug']) {
        zone_move_settings((string)$avant['slug'], (string)$data['slug']);
    }
    db_execute(
        "UPDATE zones SET slug=?,name=?,status=?,meta_title=?,meta_description=?,hero_h1=?,hero_subtitle=?,hero_cta_label=?,cities=?,postal_codes=?,faq=?,mentions_legales=?,sort_order=? WHERE id=?",
        [$data['slug'],$data['name'],(int)($data['status']??1),
         $data['meta_title']??null,$data['meta_description']??null,
         $data['hero_h1']??null,$data['hero_subtitle']??null,$data['hero_cta_label']??null,
         $data['cities']??null,$data['postal_codes']??null,
         $data['faq']??null,$data['mentions_legales']??null,(int)($data['sort_order']??0),$id]
    );
}

function toggle_zone_status(int $id): void
{
    db_execute("UPDATE zones SET status = 1 - status WHERE id=?", [$id]);
}

/**
 * Supprime une zone et ce qui n'a plus de sens sans elle.
 *
 * Ses textes personnalisés disparaissent — ils ne s'afficheraient nulle
 * part. Les contenus qui lui étaient rattachés (pages, réalisations,
 * avis) ne sont pas supprimés mais rendus visibles partout : rien de ce
 * que vous avez saisi ne doit se perdre avec la zone.
 */
function delete_zone(int $id): void
{
    $zone = get_zone_by_id($id);
    db_execute("DELETE FROM zones WHERE id=?", [$id]);
    if (!$zone) return;

    $prefixe = 'z:'.$zone['slug'].':';
    foreach (array_keys(settings_cache()) as $k) {
        if (str_starts_with($k, $prefixe)) db_execute('DELETE FROM settings WHERE setting_key = ?', [$k]);
    }
    settings_cache(true);
    if (function_exists('admin_inline_keys')) admin_inline_keys(true);

    foreach (['pages', 'realisations', 'reviews'] as $table) {
        try { db_execute('UPDATE '.$table.' SET zone_id = NULL WHERE zone_id = ?', [$id]); }
        catch (Throwable $e) { /* colonne absente : rien à détacher */ }
    }
}

/** Liste déroulante d'affectation d'un contenu à une zone (0 = toutes les zones). */
function zone_select_field(string $name, ?int $selected, string $attrs = ''): string
{
    $out = '<select name="'.e($name).'" '.$attrs.'>';
    $out .= '<option value="0"'.(!$selected ? ' selected' : '').'>🌐 Toutes les zones</option>';
    foreach (all_zones() as $z) {
        $sel = ((int)$z['id'] === (int)$selected) ? ' selected' : '';
        $out .= '<option value="'.(int)$z['id'].'"'.$sel.'>📍 '.e($z['name']).'</option>';
    }
    return $out.'</select>';
}

/** Libellé lisible de la zone d'un contenu. */
function zone_label(?int $zoneId): string
{
    if (!$zoneId) return '🌐 Toutes les zones';
    $z = get_zone_by_id((int)$zoneId);
    return $z ? '📍 '.$z['name'] : '⚠ Zone supprimée';
}

/* ═══════════════════════════════════════════════════
   PAGES MÉTIER

   Chaque métier a ses propres textes. Les valeurs ci-dessous sont
   celles d'origine ; l'administration peut les remplacer métier par
   métier, y compris zone par zone.
═══════════════════════════════════════════════════ */

/**
 * Rend cliquables les textes d'un bloc groupé, en mode édition visuelle.
 * Sans effet hors de ce mode, et si le moteur d'édition n'est pas chargé.
 */
function json_block_inline(array $data, string $blob): array
{
    return function_exists('inline_edit_json_wrap') ? inline_edit_json_wrap($data, $blob) : $data;
}

/** Lecture d'un réglage sans balisage d'édition visuelle — sûr pour du JSON. */
function setting_plain(string $key, string $fallback = ''): string
{
    $c  = settings_cache();
    $zk = zone_setting_key($key);
    if ($zk !== $key) { $v = $c[$zk] ?? null; if ($v !== null && $v !== '') return $v; }
    $v = $c[$key] ?? null;
    return ($v === null || $v === '') ? $fallback : $v;
}

/** Les quatre métiers, dans l'ordre d'affichage. */
function service_trades(): array
{
    return ['electricite', 'plomberie', 'chauffage', 'climatisation'];
}

function service_trade_defaults(): array
{
    return [
      'electricite' => [
        'label'=>'Électricité','icon_svc'=>'⚡',
        'desc'=>'Dépannage, installation, mise aux normes et rénovation électrique {en_region}.',
        'badges'=>['Dépannage urgent','Tableau électrique','Mise aux normes NF C 15-100','Rénovation'],
        'offer_title'=>'Ce que nous proposons en électricité',
        'offer_items'=>[
          ['urgence', 'Dépannage 24h/7j', 'Panne de courant ou disjoncteur qui saute : diagnostic et remise en service rapide, de jour comme de nuit.'],
          ['tableau', 'Tableau électrique', 'Remplacement et mise en conformité du tableau, des disjoncteurs et des différentiels.'],
          ['prise', 'Prises et circuits', 'Ajout ou réparation de prises, de circuits et de lignes dédiées.'],
          ['ampoule', 'Éclairage', 'Pose et dépannage de l\'éclairage intérieur et extérieur, LED et éclairage technique.'],
          ['renovation', 'Rénovation électrique', 'Remise à neuf complète de l\'installation d\'un logement ou d\'un commerce.'],
          ['conformite', 'Mise aux normes', 'Mise en conformité de votre installation selon la norme NF C 15-100.'],
          ['cablage', 'Câblage neuf', 'Câblage complet pour une construction, une extension ou une réfection.'],
          ['diagnostic', 'Diagnostic', 'Contrôle de l\'installation existante, avec un compte rendu détaillé.'],
        ],
        'faq'=>[['Quel est le délai d\'intervention pour une urgence ?',setting('faq_elec_1_a','Le délai dépend de votre secteur et de nos interventions en cours : il vous est confirmé au téléphone, avant tout déplacement.')],['Faites-vous les mises aux normes NF C 15-100 ?',setting('faq_elec_2_a','Oui, nous réalisons la mise en conformité complète selon les normes en vigueur.')],['Intervenez-vous sur les bâtiments professionnels ?',setting('faq_elec_3_a','Oui, logements, commerces, bureaux et bâtiments techniques.')],['Le devis est-il gratuit ?',setting('faq_elec_4_a','Oui, devis gratuit et sans engagement avant toute intervention.')]],
      ],
      'plomberie' => [
        'label'=>'Plomberie','icon_svc'=>'💧',
        'desc'=>'Dépannage fuite, réparation sanitaire et entretien réseau {en_region}.',
        'badges'=>['Fuite urgente','Débouchage','Sanitaires','Entretien réseau'],
        'offer_title'=>'Ce que nous proposons en plomberie',
        'offer_items'=>[
          ['fuite', 'Recherche de fuite', 'Fuite visible ou cachée : localisation précise, puis réparation.'],
          ['robinet', 'Robinetterie', 'Dépannage et remplacement de robinets et de mitigeurs.'],
          ['debouchage', 'Débouchage', 'Canalisations, WC, évier : débouchage et remise en écoulement.'],
          ['sanitaires', 'Sanitaires', 'Remplacement de WC, lavabo, baignoire ou douche.'],
          ['chauffe-eau', 'Chauffe-eau', 'Diagnostic, réparation ou remplacement, avec mise en service.'],
          ['entretien', 'Entretien préventif', 'Une visite annuelle pour éviter les fuites et les pannes.'],
          ['reseau', 'Réseaux', 'Tuyauteries d\'alimentation et d\'évacuation, raccordements.'],
          ['conformite', 'Mise en conformité', 'Mise aux normes de vos installations sanitaires.'],
        ],
        'faq'=>[['Intervenez-vous en urgence pour une fuite ?',setting('faq_plomb_1_a','Oui, disponible '.company_hours().'. Appelez-nous pour une intervention immédiate.')],['Faites-vous le remplacement de chauffe-eau ?',setting('faq_plomb_2_a','Oui, diagnostic, remplacement et mise en service de tous types de chauffe-eau.')],['Proposez-vous un contrat d\'entretien ?',setting('faq_plomb_3_a','Oui, contrats de maintenance préventive annuels disponibles.')],['Que faire en cas de fuite importante ?',setting('faq_plomb_4_a','Coupez l\'arrivée d\'eau principale et appelez-nous immédiatement.')]],
      ],
      'chauffage' => [
        'label'=>'Chauffage & PAC','icon_svc'=>'🔥',
        'desc'=>'Dépannage chaudière gaz/fioul/électrique, pompe à chaleur, entretien {en_region}.',
        'badges'=>['Panne chaudière','Pompe à chaleur','Chaudière gaz/fioul','Entretien annuel'],
        'offer_title'=>'Ce que nous proposons en chauffage',
        'offer_items'=>[
          ['chaudiere', 'Panne de chaudière', 'Gaz, fioul ou électrique : diagnostic et remise en service.'],
          ['entretien', 'Entretien annuel', 'L\'entretien réglementaire obligatoire, avec rapport d\'intervention.'],
          ['pac', 'Pompe à chaleur', 'Dépannage et entretien des PAC air/air et air/eau.'],
          ['installation', 'Installation neuve', 'Pose d\'une chaudière ou d\'une pompe à chaleur neuve, mise en service.'],
          ['economie', 'Économies d\'énergie', 'Diagnostic et optimisation de votre consommation.'],
          ['pieces', 'Pièces', 'Remplacement de corps de chauffe, brûleurs et circulateurs.'],
          ['radiateur', 'Régulation', 'Thermostat, programmation et vannes thermostatiques.'],
          ['froid', 'Urgence hiver', 'Astreinte renforcée pendant les périodes de grand froid.'],
        ],
        'faq'=>[['Intervenez-vous sur les chaudières gaz et fioul ?',setting('faq_chauf_1_a','Oui, sur tous types de chaudières : gaz, fioul, électrique et condensation.')],['Proposez-vous l\'installation de pompe à chaleur ?',setting('faq_chauf_2_a','Oui, PAC air/air, air/eau — installation, entretien et dépannage.')],['Faites-vous l\'entretien annuel de chaudière ?',setting('faq_chauf_3_a','Oui, contrat d\'entretien annuel réglementaire avec rapport d\'intervention.')],['Mon chauffage tombe en panne en hiver, que faire ?',setting('faq_chauf_4_a','Appelez-nous immédiatement — priorité absolue aux urgences de chauffage en hiver.')]],
      ],
      'climatisation' => [
        'label'=>'Climatisation & CVC','icon_svc'=>'❄️',
        'desc'=>'Installation, dépannage et entretien de climatisation et CVC {en_region}.',
        'badges'=>['Clim en panne','CVC','Installation split','Entretien saisonnier'],
        'offer_title'=>'Ce que nous proposons en climatisation',
        'offer_items'=>[
          ['clim', 'Panne de climatisation', 'Split, multi-split ou gainable : diagnostic et remise en service.'],
          ['installation', 'Installation', 'Pose de climatisation pour les particuliers et les professionnels.'],
          ['nettoyage', 'Entretien saisonnier', 'Nettoyage, vérifications et réglages avant chaque saison.'],
          ['fluide', 'Fluide frigorigène', 'Recharge du circuit en fluide frigorigène.'],
          ['performance', 'Performances', 'Contrôle du rendement et optimisation des réglages.'],
          ['batiment', 'Gainable et centralisé', 'Systèmes gainables et installations centralisées.'],
          ['ventilation', 'VMC et ventilation', 'Ventilation mécanique et qualité de l\'air intérieur.'],
          ['conseil', 'Étude et conseil', 'Étude de votre besoin et conseil avant toute installation.'],
        ],
        'faq'=>[['Intervenez-vous sur tous types de climatiseurs ?',setting('faq_clim_1_a','Oui, splits, multi-splits, gainables et systèmes CVC.')],['Proposez-vous l\'installation de climatisation ?',setting('faq_clim_2_a','Oui, fourniture, pose et mise en service avec conseil adapté.')],['Quand faire l\'entretien de sa climatisation ?',setting('faq_clim_3_a','Idéalement avant chaque saison (printemps et automne) pour garantir les performances.')],['Intervenez-vous pour les entreprises ?',setting('faq_clim_4_a','Oui, commerces, bureaux, restaurants — intervention compatible avec votre exploitation.')]],
      ],
      'vmc' => [
        'label'=>'VMC & Ventilation','icon_svc'=>'🌬️',
        'desc'=>'Installation, dépannage et entretien de VMC simple et double flux {en_region}.',
        'badges'=>['VMC simple flux','VMC double flux','Entretien annuel','Qualité de l\'air'],
        'offer_title'=>'Ce que nous proposons en ventilation',
        'offer_items'=>[
          ['vmc', 'Installation de VMC', 'Simple flux ou double flux, adaptée à votre logement.'],
          ['moteur', 'Moteur et caisson', 'Remplacement du moteur ou du caisson défaillant.'],
          ['nettoyage', 'Nettoyage', 'Bouches et gaines nettoyées et désinfectées.'],
          ['performance', 'Entretien et débits', 'Entretien annuel, mesure et réglage des débits.'],
          ['humidite', 'Humidité', 'Diagnostic de la condensation et des moisissures.'],
          ['conformite', 'Mise en conformité', 'Une ventilation conforme pour votre logement.'],
          ['commerce', 'Locaux professionnels', 'Ventilation des commerces, bureaux et restaurants.'],
          ['conseil', 'Conseil', 'Le système adapté à votre bâti, simple ou double flux.'],
        ],
        'faq'=>[['Faut-il entretenir sa VMC ?',setting('faq_vmc_1_a','Oui, un nettoyage annuel des bouches et un contrôle des débits garantissent son efficacité et la qualité de l\'air.')],['Simple ou double flux ?',setting('faq_vmc_2_a','Le double flux récupère la chaleur de l\'air extrait et convient aux logements bien isolés. Nous vous conseillons selon votre bâti.')],['Ma VMC est bruyante, est-ce grave ?',setting('faq_vmc_3_a','Souvent un encrassement ou un moteur en fin de vie. Un diagnostic permet de trancher rapidement.')],['Intervenez-vous en copropriété ?',setting('faq_vmc_4_a','Oui, sur les VMC individuelles comme sur les installations collectives.')]],
      ],
      'portail' => [
        'label'=>'Portail & Visiophone','icon_svc'=>'🚪',
        'desc'=>'Motorisation de portail, visiophone, interphone et contrôle d\'accès {en_region}.',
        'badges'=>['Portail bloqué','Motorisation','Visiophone','Interphone'],
        'offer_title'=>'Ce que nous proposons en contrôle d\'accès',
        'offer_items'=>[
          ['portail', 'Portail bloqué', 'Déblocage et remise en service de portails et portes de garage.'],
          ['motorisation', 'Motorisation', 'Motorisation de portail battant ou coulissant.'],
          ['visiophone', 'Visiophone', 'Installation et remplacement de visiophones.'],
          ['interphone', 'Interphone', 'Interphones individuels et collectifs.'],
          ['telecommande', 'Télécommandes et badges', 'Programmation de télécommandes et de badges, copies.'],
          ['batiment', 'Platine de rue', 'Remplacement de la platine de rue et du combiné.'],
          ['digicode', 'Digicode et gâche', 'Dépannage de digicode et de gâche électrique.'],
          ['outil', 'Entretien', 'Réglage et entretien des automatismes.'],
        ],
        'faq'=>[['Mon portail ne s\'ouvre plus, que faire ?',setting('faq_portail_1_a','Coupez l\'alimentation et appelez-nous. La panne vient souvent de la motorisation, des photocellules ou de la carte électronique.')],['Peut-on motoriser un portail existant ?',setting('faq_portail_2_a','Oui dans la plupart des cas, après vérification de l\'état des piliers et du portail.')],['Installez-vous des visiophones connectés ?',setting('faq_portail_3_a','Oui, y compris les modèles avec renvoi d\'appel sur téléphone.')],['Intervenez-vous en copropriété ?',setting('faq_portail_4_a','Oui, sur les interphones et portails collectifs, en coordination avec le syndic.')]],
      ],
      'automatisme' => [
        'label'=>'Automatismes & Électroménager','icon_svc'=>'🤖',
        'desc'=>'Volets roulants, stores, domotique et dépannage du gros électroménager {en_region}.',
        'badges'=>['Volet roulant','Store motorisé','Domotique','Électroménager'],
        'offer_title'=>'Ce que nous proposons en automatismes',
        'offer_items'=>[
          ['volet', 'Volets roulants', 'Dépannage et remplacement de volets roulants.'],
          ['motorisation', 'Motorisation', 'Motorisation de volets et de stores existants.'],
          ['store', 'Stores et brise-soleil', 'Installation de stores et brise-soleil motorisés.'],
          ['domotique', 'Domotique', 'Programmation de commandes et de scénarios.'],
          ['lave-linge', 'Électroménager', 'Dépannage de lave-linge, lave-vaisselle et sèche-linge.'],
          ['cablage', 'Raccordement', 'Raccordement et mise en service de vos appareils.'],
          ['carte', 'Pièces électroniques', 'Remplacement de moteurs, cartes et télécommandes.'],
          ['connecte', 'Équipements connectés', 'Conseil pour choisir et installer vos équipements connectés.'],
        ],
        'faq'=>[['Réparez-vous les volets roulants électriques ?',setting('faq_auto_1_a','Oui : moteur, lame, tablier, coffre et commande. Le diagnostic précise ce qui doit être remplacé.')],['Peut-on motoriser des volets manuels ?',setting('faq_auto_2_a','Oui dans la plupart des cas, sans changer le tablier ni le coffre.')],['Intervenez-vous sur l\'électroménager ?',setting('faq_auto_3_a','Oui, sur le gros électroménager : lave-linge, lave-vaisselle, sèche-linge, four et plaques.')],['Faut-il remplacer ou réparer ?',setting('faq_auto_4_a','Nous annonçons le coût de la réparation avant d\'intervenir : vous décidez en connaissance de cause.')]],
      ],
    ];
}

/**
 * Signes d'alerte de chaque métier (« Quand nous appeler ? ») : symptômes que le client
 * reconnaît lui-même, sans promesse de délai. Modifiables par métier dans Contenu du site.
 */
function service_alert_signs(): array
{
    return [
        'electricite'   => ['Le disjoncteur ou l\'interrupteur différentiel saute à répétition',
                            'Odeur de brûlé ou de plastique chaud près d\'une prise ou du tableau',
                            'Une prise ou un interrupteur chauffe, noircit ou grésille',
                            'Les lumières vacillent ou baissent quand un appareil démarre',
                            'Plus de courant dans une partie du logement seulement',
                            'Petite décharge en touchant un appareil ou un robinet : ne touchez plus à rien et coupez le courant au disjoncteur général'],
        'plomberie'     => ['Fuite visible, tache d\'humidité au plafond ou sur un mur',
                            'Le compteur d\'eau tourne alors que tous les robinets sont fermés',
                            'Évier, douche ou WC qui s\'évacue lentement ou déborde',
                            'Mauvaises odeurs qui remontent des canalisations',
                            'Eau marron ou trouble au robinet',
                            'Plus d\'eau chaude, ou chauffe-eau qui goutte en continu'],
        'chauffage'     => ['Radiateurs froids ou tièdes alors que le chauffage est allumé',
                            'Chaudière en sécurité ou code d\'erreur affiché',
                            'Pression de la chaudière qui baisse souvent',
                            'Bruits inhabituels : claquements, sifflements, gargouillis',
                            'Plus d\'eau chaude au robinet',
                            'Odeur de gaz : sortez, n\'actionnez aucun interrupteur et appelez Urgence Sécurité Gaz au 0 800 47 33 33'],
        'climatisation' => ['Le climatiseur souffle de l\'air tiède',
                            'De l\'eau coule de l\'unité intérieure',
                            'Bruit ou vibration anormale',
                            'Mauvaise odeur à la mise en marche',
                            'L\'appareil s\'arrête tout seul ou affiche un code d\'erreur'],
        'vmc'           => ['Les bouches n\'aspirent plus : une feuille de papier n\'y tient pas',
                            'Buée persistante, moisissures ou odeurs dans la salle de bains',
                            'Bruit anormal du caisson de ventilation',
                            'Le moteur ne tourne plus'],
        'portail'       => ['Le portail ne réagit plus à la télécommande',
                            'Le portail s\'arrête ou repart en sens inverse',
                            'Visiophone sans image ou sans son',
                            'Gâche électrique ou digicode qui n\'ouvre plus'],
        'automatisme'   => ['Volet roulant bloqué ou qui descend de travers',
                            'Le moteur du volet grogne sans faire bouger le tablier',
                            'Télécommande ou interrupteur sans effet',
                            'Lave-linge ou lave-vaisselle qui ne vidange plus ou affiche un code d\'erreur'],
    ];
}

/**
 * Présentation de chaque métier, affichée sous le grand titre : deux
 * paragraphes (séparés par une ligne vide) qui situent le besoin du client,
 * puis ce que nous faisons et où. Aucune promesse absente du reste du site.
 */
function service_trade_intros(): array
{
    return [
        'electricite' => "Une panne de courant, un disjoncteur qui saute, un tableau vieillissant ou une rénovation à prévoir : nos électriciens interviennent {en_region} pour les particuliers comme pour les professionnels. Chaque intervention commence par un diagnostic clair, et le prix vous est annoncé avant le début des travaux.\n\n"
                        ."Dépannage en urgence 24h/24, remplacement de tableau, ajout de prises et de circuits, éclairage intérieur et extérieur, mise aux normes NF C 15-100 : nous intervenons autour de {ville} et vous remettons un compte rendu détaillé après chaque intervention.",
        'plomberie'   => "Fuite sous l'évier, WC bouché, chauffe-eau en panne ou dégât des eaux : nos plombiers interviennent {en_region} pour mettre votre installation en sécurité, puis la réparer durablement. Le diagnostic et le prix vous sont annoncés avant toute intervention.\n\n"
                        ."Recherche de fuite, débouchage de canalisations, remplacement de robinetterie et de sanitaires, entretien des réseaux : nous intervenons autour de {ville}, chez les particuliers, en copropriété et dans les locaux professionnels.",
        'chauffage'   => "Chaudière en panne en plein hiver, radiateurs froids, pompe à chaleur en défaut ou entretien annuel à planifier : nos chauffagistes interviennent {en_region} sur les chaudières gaz, fioul et électriques comme sur les pompes à chaleur. En période de froid, les pannes de chauffage passent en priorité.\n\n"
                        ."Dépannage, entretien annuel obligatoire, réglage de la régulation, remplacement ou installation d'un équipement neuf : nous intervenons autour de {ville} et vous remettons un rapport après chaque passage.",
        'climatisation' => "Climatiseur qui ne refroidit plus, bruit anormal, fuite d'eau ou projet d'installation : nos techniciens interviennent {en_region} sur tous les systèmes de climatisation, du split mural au gainable, et vous conseillent sur l'équipement adapté à votre logement ou à vos locaux.\n\n"
                        ."Installation, dépannage, entretien saisonnier et contrôle des performances : nous intervenons autour de {ville} pour les particuliers, les commerces, les bureaux et les restaurants.",
        'vmc'         => "Humidité, condensation sur les vitres, moisissures ou VMC bruyante : une ventilation qui fonctionne mal se ressent vite dans tout le logement. Nos techniciens interviennent {en_region} pour diagnostiquer, réparer ou remplacer votre VMC simple ou double flux.\n\n"
                        ."Nettoyage des bouches et des gaines, remplacement de moteur, contrôle des débits, installation neuve : nous intervenons autour de {ville}, en maison individuelle, en copropriété et dans les locaux professionnels.",
        'portail'     => "Portail bloqué, télécommande qui ne répond plus, visiophone muet ou interphone collectif en panne : nos techniciens interviennent {en_region} pour remettre vos accès en service, en toute sécurité.\n\n"
                        ."Motorisation de portail battant ou coulissant, installation de visiophone, programmation de badges et de télécommandes, dépannage de digicode et de gâche électrique : nous intervenons autour de {ville}, chez les particuliers comme en copropriété, en lien avec votre syndic.",
        'automatisme' => "Volet roulant bloqué, store qui ne se déroule plus, lave-linge en panne ou domotique à programmer : nos techniciens interviennent {en_region} sur les automatismes et le gros électroménager de votre logement.\n\n"
                        ."Réparation et motorisation de volets et de stores, programmation de scénarios domotiques, diagnostic de lave-linge, lave-vaisselle et sèche-linge : nous intervenons autour de {ville} et vous annonçons le coût de la réparation avant d'intervenir.",
    ];
}

/**
 * Grand titre et référencement Google de chaque page métier. Écrits avec les
 * variables de lieu : la page Électricité du site global annonce le Doubs, le
 * Jura et la Côte-d'Or, la même page de la zone Jura annonce le Jura.
 */
function service_trade_seo_defaults(): array
{
    $n   = company_name();
    $fin = ' Intervention rapide autour de {ville}, devis gratuit.';
    return [
        'electricite'   => ['Électricien {en_region}', 'Électricien {en_ville} — Dépannage 24h/7j | '.$n,
                            'Électricien {en_region} : dépannage en urgence, tableau électrique, mise aux normes NF C 15-100, rénovation.'.$fin],
        'plomberie'     => ['Plombier {en_region}', 'Plombier {en_ville} — Fuite, débouchage, chauffe-eau | '.$n,
                            'Plombier {en_region} : recherche de fuite, débouchage, sanitaires et chauffe-eau.'.$fin],
        'chauffage'     => ['Chauffagiste {en_region}', 'Chauffagiste {en_ville} — Chaudière, pompe à chaleur | '.$n,
                            'Chauffagiste {en_region} : dépannage et entretien de chaudière gaz ou fioul, pompe à chaleur.'.$fin],
        'climatisation' => ['Climatisation {en_region}', 'Climatisation {en_ville} — Installation, dépannage | '.$n,
                            'Installation, dépannage et entretien de climatisation {en_region} : split, multi-split, gainable.'.$fin],
        'vmc'           => ['VMC et ventilation {en_region}', 'VMC {en_ville} — Installation, entretien, dépannage | '.$n,
                            'Installation, dépannage et entretien de VMC simple et double flux {en_region}.'.$fin],
        'portail'       => ['Portail et visiophone {en_region}', 'Portail, visiophone, interphone {en_ville} | '.$n,
                            'Motorisation et dépannage de portail, visiophone, interphone et contrôle d\'accès {en_region}.'.$fin],
        'automatisme'   => ['Volets roulants et automatismes {en_region}', 'Volets roulants, domotique, électroménager {en_ville} | '.$n,
                            'Dépannage de volets roulants, stores, domotique et gros électroménager {en_region}.'.$fin],
    ];
}

/**
 * Slug de la page publique d'un métier. index.php reconnaît le métier
 * d'après le slug ou le titre de la page ; on applique la même règle ici
 * pour que les liens « voir la page » tombent juste.
 */
function service_trade_patterns(): array
{
    return [
        'electricite'   => ['electric','electri'],
        'plomberie'     => ['plomb'],
        'chauffage'     => ['chauff','chaudiere','pompe-a-chaleur'],
        'climatisation' => ['clim','cvc'],
        'vmc'           => ['vmc','ventilation','aeration'],
        'portail'       => ['portail','visiophone','interphone','digicode','controle-acces'],
        'automatisme'   => ['automatisme','volet','store','domotique','electromenager'],
    ];
}

/**
 * Reconnaît le métier d'une adresse ou d'un titre de page.
 * Les motifs les plus longs passent d'abord, sinon « clim » capterait
 * « climatisation » avant que « ventilation » n'ait sa chance.
 */
function service_detect_trade(string $contexte): ?string
{
    // Sans accents : « Électricité » (ancienne page en base) est bien la page Électricité.
    $contexte = function_exists('text_ascii_lower') ? text_ascii_lower(rawurldecode($contexte)) : mb_strtolower($contexte, 'UTF-8');
    $trouve = null; $longueur = 0;
    foreach (service_trade_patterns() as $trade => $motifs) {
        foreach ($motifs as $m) {
            if (str_contains($contexte, $m) && mb_strlen($m) > $longueur) {
                $trouve = $trade; $longueur = mb_strlen($m);
            }
        }
    }
    return $trouve;
}

function service_page_slug(string $trade): string
{
    $needles = service_trade_patterns()[$trade] ?? [$trade];
    try {
        foreach (db_fetch_all('SELECT slug, title FROM pages') as $p) {
            $ctx = function_exists('text_ascii_lower') ? text_ascii_lower(($p['slug'] ?? '').' '.($p['title'] ?? '')) : mb_strtolower(($p['slug'] ?? '').' '.($p['title'] ?? ''), 'UTF-8');
            foreach ($needles as $n) if (str_contains($ctx, $n)) return (string)$p['slug'];
        }
    } catch (Throwable $e) { /* table absente */ }
    return $trade;
}

/** Textes effectifs d'un métier : personnalisés si définis, d'origine sinon. */
/**
 * Dessins des prestations (assets/img/icons/<clé>.png) : trait orange, fond transparent.
 * Clé => libellé affiché dans l'admin. Ajouter un dessin : déposer le PNG et l'inscrire ici.
 */
function service_icons(): array
{
    return [
        'eclair' => 'Éclair (dépannage électrique)',
        'prise' => 'Prise',
        'ampoule' => 'Ampoule (éclairage)',
        'tableau' => 'Tableau électrique',
        'cablage' => 'Câblage, raccordement',
        'renovation' => 'Maison électrique (rénovation)',
        'conformite' => 'Bouclier (mise aux normes)',
        'diagnostic' => 'Loupe (diagnostic)',
        'urgence' => '24h/24 (urgence)',
        'fuite' => 'Goutte et loupe (fuite)',
        'robinet' => 'Robinet',
        'debouchage' => 'Ventouse (débouchage)',
        'sanitaires' => 'Baignoire (sanitaires)',
        'chauffe-eau' => 'Chauffe-eau',
        'reseau' => 'Tuyau (réseaux)',
        'entretien' => 'Calendrier (entretien)',
        'chaudiere' => 'Chaudière',
        'radiateur' => 'Radiateur',
        'pac' => 'Pompe à chaleur',
        'flamme' => 'Flamme',
        'thermometre' => 'Thermomètre',
        'economie' => 'Maison verte (économies)',
        'pieces' => 'Engrenage (pièces)',
        'froid' => 'Flocon (urgence hiver, froid)',
        'clim' => 'Climatiseur',
        'nettoyage' => 'Pulvérisateur (nettoyage)',
        'fluide' => 'Thermomètre froid (fluide)',
        'performance' => 'Jauge (performances, débits)',
        'batiment' => 'Immeubles',
        'commerce' => 'Commerce',
        'ventilation' => 'Air (ventilation)',
        'vmc' => 'Bouche de VMC',
        'moteur' => 'Ventilateur (moteur)',
        'humidite' => 'Gouttes (humidité)',
        'conseil' => 'Bulle (conseil)',
        'installation' => 'Outils (installation)',
        'outil' => 'Clé (réparation)',
        'portail' => 'Portail',
        'motorisation' => 'Automatisme (motorisation)',
        'visiophone' => 'Visiophone',
        'interphone' => 'Sonnette (interphone)',
        'telecommande' => 'Télécommande',
        'digicode' => 'Digicode',
        'gache' => 'Cadenas (gâche, serrure)',
        'volet' => 'Volet roulant',
        'store' => 'Store',
        'domotique' => 'Maison connectée',
        'lave-linge' => 'Lave-linge',
        'lave-vaisselle' => 'Lave-vaisselle',
        'carte' => 'Carte électronique',
        'connecte' => 'Wi-Fi (connecté)',
        'check' => 'Coche (par défaut)',
    ];
}

function service_icon_url(string $key): string
{
    $key = isset(service_icons()[$key]) ? $key : 'check';
    return asset_url('assets/img/icons/'.$key.'.png');
}

/**
 * Prestations d'un métier, sous forme de cartes : dessin, titre, texte court.
 * Accepte aussi l'ancien format (une phrase par prestation) d'une liste déjà
 * enregistrée dans l'admin : la phrase devient le titre, avec la coche par défaut.
 */
function service_offer_cards(array $items): array
{
    $out = [];
    foreach ($items as $it) {
        if (is_array($it)) {
            $v = array_values($it);
            [$icon, $title, $text] = [trim((string)($v[0] ?? '')), trim((string)($v[1] ?? '')), trim((string)($v[2] ?? ''))];
        } else {
            // Une phrase de l'ancienne liste (enregistrée telle quelle en sauvegardant l'admin)
            // retrouve sa carte complète ; une phrase écrite à la main garde la coche.
            $phrase = trim((string)$it);
            [$icon, $title, $text] = service_offer_legacy()[$phrase] ?? ['check', $phrase, ''];
        }
        if ($title === '' && $text === '') continue;
        $out[] = ['icon' => isset(service_icons()[$icon]) ? $icon : 'check', 'title' => $title, 'text' => $text];
    }
    return $out;
}

/** Ligne d'admin au format d'avant (une phrase) convertie en carte [dessin, titre, texte]. */
function service_offer_row_upgrade(string $phrase): array
{
    $phrase = trim($phrase);
    return service_offer_legacy()[$phrase] ?? ['check', $phrase, ''];
}

/**
 * Anciennes prestations (une phrase chacune, jusqu'en novembre 2026) => carte qui les remplace.
 * L'admin enregistre toutes les listes d'une page dès qu'on la sauvegarde : le site garde donc
 * parfois l'ancienne liste en base. Cette table lui rend ses dessins et ses textes.
 */
function service_offer_legacy(): array
{
    static $map = null;
    return $map ??= [
        'Dépannage et remise en service (urgence 24h/7j)' => ['urgence', 'Dépannage 24h/7j', 'Panne de courant ou disjoncteur qui saute : diagnostic et remise en service rapide, de jour comme de nuit.'],
        'Remplacement et mise en conformité de tableaux électriques' => ['tableau', 'Tableau électrique', 'Remplacement et mise en conformité du tableau, des disjoncteurs et des différentiels.'],
        'Ajout ou réparation de circuits, prises, lignes dédiées' => ['prise', 'Prises et circuits', 'Ajout ou réparation de prises, de circuits et de lignes dédiées.'],
        'Installation et dépannage d\'éclairage intérieur et extérieur' => ['ampoule', 'Éclairage', 'Pose et dépannage de l\'éclairage intérieur et extérieur, LED et éclairage technique.'],
        'Rénovation électrique complète (logement, commerce)' => ['renovation', 'Rénovation électrique', 'Remise à neuf complète de l\'installation d\'un logement ou d\'un commerce.'],
        'Mise aux normes NF C 15-100 obligatoire' => ['conformite', 'Mise aux normes', 'Mise en conformité de votre installation selon la norme NF C 15-100.'],
        'Câblage neuf pour constructions et extensions' => ['cablage', 'Câblage neuf', 'Câblage complet pour une construction, une extension ou une réfection.'],
        'Contrôle et diagnostic de l\'installation existante' => ['diagnostic', 'Diagnostic', 'Contrôle de l\'installation existante, avec un compte rendu détaillé.'],
        'Recherche et réparation de fuites visibles ou cachées' => ['fuite', 'Recherche de fuite', 'Fuite visible ou cachée : localisation précise, puis réparation.'],
        'Dépannage et remplacement de robinetterie' => ['robinet', 'Robinetterie', 'Dépannage et remplacement de robinets et de mitigeurs.'],
        'Débouchage de canalisations et WC' => ['debouchage', 'Débouchage', 'Canalisations, WC, évier : débouchage et remise en écoulement.'],
        'Remplacement de WC, lavabo, baignoire, douche' => ['sanitaires', 'Sanitaires', 'Remplacement de WC, lavabo, baignoire ou douche.'],
        'Réparation et remplacement de chauffe-eau' => ['chauffe-eau', 'Chauffe-eau', 'Diagnostic, réparation ou remplacement, avec mise en service.'],
        'Entretien préventif annuel' => ['entretien', 'Entretien préventif', 'Une visite annuelle pour éviter les fuites et les pannes.'],
        'Intervention sur réseaux d\'alimentation et d\'évacuation' => ['reseau', 'Réseaux', 'Tuyauteries d\'alimentation et d\'évacuation, raccordements.'],
        'Mise en conformité des installations sanitaires' => ['conformite', 'Mise en conformité', 'Mise aux normes de vos installations sanitaires.'],
        'Dépannage et remise en service de chaudières gaz, fioul, électrique' => ['chaudiere', 'Panne de chaudière', 'Gaz, fioul ou électrique : diagnostic et remise en service.'],
        'Entretien annuel réglementaire de chaudière (obligatoire)' => ['entretien', 'Entretien annuel', 'L\'entretien réglementaire obligatoire, avec rapport d\'intervention.'],
        'Dépannage et entretien de pompes à chaleur air/air et air/eau' => ['pac', 'Pompe à chaleur', 'Dépannage et entretien des PAC air/air et air/eau.'],
        'Installation de PAC et chaudières neuves' => ['installation', 'Installation neuve', 'Pose d\'une chaudière ou d\'une pompe à chaleur neuve, mise en service.'],
        'Diagnostic et optimisation de la consommation énergétique' => ['economie', 'Économies d\'énergie', 'Diagnostic et optimisation de votre consommation.'],
        'Remplacement de corps de chauffe, brûleurs, circulateurs' => ['pieces', 'Pièces', 'Remplacement de corps de chauffe, brûleurs et circulateurs.'],
        'Réglage thermostat, programmation, vannes thermostatiques' => ['radiateur', 'Régulation', 'Thermostat, programmation et vannes thermostatiques.'],
        'Urgences hiver — astreinte renforcée en période froide' => ['froid', 'Urgence hiver', 'Astreinte renforcée pendant les périodes de grand froid.'],
        'Dépannage de tout type de climatiseur (split, multi-split, gainable)' => ['clim', 'Panne de climatisation', 'Split, multi-split ou gainable : diagnostic et remise en service.'],
        'Installation de climatisation pour particuliers et professionnels' => ['installation', 'Installation', 'Pose de climatisation pour les particuliers et les professionnels.'],
        'Entretien saisonnier (nettoyage, vérification, réglages)' => ['nettoyage', 'Entretien saisonnier', 'Nettoyage, vérifications et réglages avant chaque saison.'],
        'Recharge en fluide frigorigène' => ['fluide', 'Fluide frigorigène', 'Recharge du circuit en fluide frigorigène.'],
        'Contrôle des performances et optimisation' => ['performance', 'Performances', 'Contrôle du rendement et optimisation des réglages.'],
        'Systèmes gainables et centralisés' => ['batiment', 'Gainable et centralisé', 'Systèmes gainables et installations centralisées.'],
        'VMC et ventilation mécanique' => ['ventilation', 'VMC et ventilation', 'Ventilation mécanique et qualité de l\'air intérieur.'],
        'Étude et conseil avant installation' => ['conseil', 'Étude et conseil', 'Étude de votre besoin et conseil avant toute installation.'],
        'Installation de VMC simple flux et double flux' => ['vmc', 'Installation de VMC', 'Simple flux ou double flux, adaptée à votre logement.'],
        'Remplacement de moteur ou de caisson défaillant' => ['moteur', 'Moteur et caisson', 'Remplacement du moteur ou du caisson défaillant.'],
        'Nettoyage et désinfection des bouches et gaines' => ['nettoyage', 'Nettoyage', 'Bouches et gaines nettoyées et désinfectées.'],
        'Entretien annuel et contrôle des débits' => ['performance', 'Entretien et débits', 'Entretien annuel, mesure et réglage des débits.'],
        'Diagnostic humidité, condensation et moisissures' => ['humidite', 'Humidité', 'Diagnostic de la condensation et des moisissures.'],
        'Mise en conformité de la ventilation d\'un logement' => ['conformite', 'Mise en conformité', 'Une ventilation conforme pour votre logement.'],
        'Ventilation de locaux professionnels et commerces' => ['commerce', 'Locaux professionnels', 'Ventilation des commerces, bureaux et restaurants.'],
        'Conseil sur le choix du système adapté au bâti' => ['conseil', 'Conseil', 'Le système adapté à votre bâti, simple ou double flux.'],
        'Dépannage de portail et portail de garage bloqué' => ['portail', 'Portail bloqué', 'Déblocage et remise en service de portails et portes de garage.'],
        'Motorisation de portail battant ou coulissant' => ['motorisation', 'Motorisation', 'Motorisation de portail battant ou coulissant.'],
        'Installation et remplacement de visiophone' => ['visiophone', 'Visiophone', 'Installation et remplacement de visiophones.'],
        'Interphone individuel et collectif' => ['interphone', 'Interphone', 'Interphones individuels et collectifs.'],
        'Programmation de télécommandes et badges' => ['telecommande', 'Télécommandes et badges', 'Programmation de télécommandes et de badges, copies.'],
        'Remplacement de platine de rue et de combiné' => ['batiment', 'Platine de rue', 'Remplacement de la platine de rue et du combiné.'],
        'Dépannage de digicode et de gâche électrique' => ['digicode', 'Digicode et gâche', 'Dépannage de digicode et de gâche électrique.'],
        'Entretien et réglage des automatismes' => ['outil', 'Entretien', 'Réglage et entretien des automatismes.'],
        'Dépannage et remplacement de volets roulants' => ['volet', 'Volets roulants', 'Dépannage et remplacement de volets roulants.'],
        'Motorisation de volets et de stores existants' => ['motorisation', 'Motorisation', 'Motorisation de volets et de stores existants.'],
        'Installation de stores et brise-soleil motorisés' => ['store', 'Stores et brise-soleil', 'Installation de stores et brise-soleil motorisés.'],
        'Programmation de commandes et de scénarios domotiques' => ['domotique', 'Domotique', 'Programmation de commandes et de scénarios.'],
        'Dépannage de lave-linge, lave-vaisselle et sèche-linge' => ['lave-linge', 'Électroménager', 'Dépannage de lave-linge, lave-vaisselle et sèche-linge.'],
        'Raccordement et mise en service d\'électroménager' => ['cablage', 'Raccordement', 'Raccordement et mise en service de vos appareils.'],
        'Remplacement de moteurs, cartes et télécommandes' => ['carte', 'Pièces électroniques', 'Remplacement de moteurs, cartes et télécommandes.'],
        'Conseil sur les équipements connectés' => ['connecte', 'Équipements connectés', 'Conseil pour choisir et installer vos équipements connectés.'],
    ];
}

/** Dessin qui représente un métier (filtres des réalisations). */
function service_trade_icon(string $trade): string
{
    return ['electricite' => 'eclair', 'plomberie' => 'robinet', 'chauffage' => 'chaudiere', 'climatisation' => 'clim',
            'vmc' => 'vmc', 'portail' => 'portail', 'automatisme' => 'volet'][$trade] ?? 'check';
}

/** Ajoute des paramètres à une adresse, qu'elle en ait déjà ou non. */
function url_with_query(string $url, array $q): string
{
    $q = array_filter($q, static fn($v) => $v !== null && $v !== '');
    return $q ? $url.(str_contains($url, '?') ? '&' : '?').http_build_query($q) : $url;
}

/** Marques affichées en bandeau sur une page métier (modifiables dans l'admin). */
function service_brand_defaults(): array
{
    return [
        'chauffage'     => ['Saunier Duval', 'Frisquet', 'De Dietrich', 'Viessmann', 'Vaillant', 'Chaffoteaux', 'ELM Leblanc',
                            'Atlantic', 'Bosch', 'Ariston', 'Chappée', 'Buderus', 'Daikin', 'Mitsubishi Electric'],
        'climatisation' => ['Daikin', 'Mitsubishi Electric', 'Fujitsu', 'Hitachi', 'Toshiba', 'Panasonic', 'LG',
                            'Samsung', 'Gree', 'Midea', 'Carrier', 'Airwell', 'Atlantic'],
    ];
}

function service_brand_titles(): array
{
    return [
        'chauffage'     => 'Toutes marques de chaudières et de pompes à chaleur',
        'climatisation' => 'Toutes marques de climatisation',
    ];
}

function service_template(string $trade): ?array
{
    $d = service_trade_defaults()[$trade] ?? null;
    if ($d === null) return null;

    $seo = service_trade_seo_defaults()[$trade] ?? [$d['label'], $d['label'].' | '.company_name(), $d['desc']];
    $p = 'svc_' . $trade . '_';
    $list = static function (string $key, array $fallback): array {
        $raw = setting_plain($key);
        if ($raw === '') return $fallback;
        $v = json_decode($raw, true);
        return (is_array($v) && $v !== []) ? $v : $fallback;
    };

    return [
        'label'       => setting($p . 'label',       $d['label']),
        'h1'          => setting($p . 'h1',          $seo[0]),
        'meta_title'  => setting($p . 'meta_title',  $seo[1]),
        'meta_desc'   => setting($p . 'meta_desc',   $seo[2]),
        'desc'        => setting($p . 'desc',        $d['desc']),
        'intro'       => setting($p . 'intro',       service_trade_intros()[$trade] ?? ''),
        'offer_title' => setting($p . 'offer_title', $d['offer_title']),
        'badges'      => $list($p . 'badges',      $d['badges']),
        'offer_items' => $list($p . 'offer_items', $d['offer_items']),
        'brands'      => $list($p . 'brands',      service_brand_defaults()[$trade] ?? []),
        'brands_on'   => global_setting($p . 'brands_on', '1') === '1',   // interrupteur commun à toutes les zones
        'brands_title'=> setting($p . 'brands_title', service_brand_titles()[$trade] ?? 'Toutes marques'),
        'faq'         => $list($p . 'faq',         $d['faq']),
        'signs'       => $list($p . 'signs',       service_alert_signs()[$trade] ?? []),
        'signs_title' => setting($p . 'signs_title', 'Quand nous appeler ?'),
    ];
}
