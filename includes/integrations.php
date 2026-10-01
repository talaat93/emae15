<?php
declare(strict_types=1);
/**
 * Socle commun des intégrations externes (Claude, Pennylane, Yousign) :
 *  - lecture des secrets (config/config.local.php en priorité, sinon réglages en base) ;
 *  - mode simulation quand une clé manque, pour tout tester sans compte ;
 *  - appels HTTP JSON en cURL avec délai d'attente ;
 *  - journal technique dans storage/logs, sans contenu client.
 * Les secrets ne sont jamais renvoyés au navigateur.
 */

/* ─── Réglages et secrets ────────────────────────────────────
 * Table integration_settings (clé, valeur, secret 0/1), créée par la migration v15.30.
 * Ordre de lecture d'un secret :
 *   1. config/config.local.php ('secrets' => [...]) — fichier non versionné ;
 *   2. table integration_settings ;
 *   3. ancien emplacement (table settings) — pour que les clés déjà saisies restent valables.
 * Un secret n'est jamais renvoyé au navigateur ni écrit dans un journal : les écrans
 * affichent seulement « configuré » ou « non configuré ».
 */

/** Lignes de integration_settings, lues une fois par requête. */
function integration_settings_rows(bool $flush = false): array
{
    static $rows = null;
    if ($flush) $rows = null;
    if ($rows === null) {
        $rows = [];
        try {
            foreach (db_fetch_all('SELECT cle, valeur, secret FROM integration_settings') as $r) $rows[(string)$r['cle']] = $r;
        } catch (Throwable $e) { /* table pas encore créée : on retombe sur l'ancien emplacement */ }
    }
    return $rows;
}

function integration_settings_put(string $key, string $value, bool $secret): void
{
    db_execute('INSERT INTO integration_settings (cle, valeur, secret, updated_at) VALUES (?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE valeur = VALUES(valeur), secret = VALUES(secret), updated_at = NOW()', [$key, $value, $secret ? 1 : 0]);
    integration_settings_rows(true);
}

/** Secret d'intégration ; chaîne vide s'il n'est configuré nulle part. */
function integration_secret(string $name): string
{
    $cfg = app_config()['secrets'][$name] ?? null;
    if (is_string($cfg) && trim($cfg) !== '') return trim($cfg);
    $row = integration_settings_rows()[$name] ?? null;
    if ($row !== null) return trim((string)$row['valeur']);
    return trim(global_setting($name, ''));
}

/** Le secret est-il configuré ? (seule information affichable à l'écran) */
function integration_secret_configured(string $name): bool
{
    return integration_secret($name) !== '';
}

/** Le secret vient-il de config.local.php ? (il ne se modifie alors pas depuis l'écran) */
function integration_secret_from_file(string $name): bool
{
    $cfg = app_config()['secrets'][$name] ?? null;
    return is_string($cfg) && trim($cfg) !== '';
}

/** Enregistre (ou efface avec une valeur vide) un secret. */
function set_integration_secret(string $name, string $value): void
{
    try {
        integration_settings_put($name, trim($value), true);
        // L'ancien emplacement est vidé pour qu'une ancienne valeur ne survive pas à un effacement.
        db_execute("UPDATE settings SET setting_value = '' WHERE setting_key = ?", [$name]);
        settings_cache(true);
        integration_log('reglages', trim($value) === '' ? 'secret effacé' : 'secret enregistré', ['cle' => $name]);
    } catch (Throwable $e) {
        integration_log('reglages', 'échec enregistrement du secret', ['cle' => $name, 'erreur' => $e->getMessage()]);
    }
}

/** Ancien nom, conservé pour les écrans existants. */
function integration_store_secret(string $name, string $value): void
{
    set_integration_secret($name, $value);
}

/** Réglage non secret (mode simulation, adresse de test…). */
function integration_setting(string $name, string $default = ''): string
{
    $row = integration_settings_rows()[$name] ?? null;
    if ($row !== null && (int)$row['secret'] === 0) return (string)$row['valeur'];
    return global_setting($name, $default);
}

function set_integration_setting(string $name, string $value): void
{
    try {
        integration_settings_put($name, $value, false);
        integration_log('reglages', 'réglage modifié', ['cle' => $name]);
    } catch (Throwable $e) {
        integration_log('reglages', 'échec enregistrement du réglage', ['cle' => $name, 'erreur' => $e->getMessage()]);
    }
}

/** Masque un secret : n'en montre plus aucun caractère (seulement qu'il existe). */
function integration_mask(string $secret): string
{
    return $secret === '' ? '' : '••••••••';
}

/* ─── Journal ─────────────────────────────────────────────── */
function integration_log_dir(): string
{
    $dir = __DIR__.'/../storage/logs';
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    // Le dossier storage est servi par le web : on interdit explicitement la lecture des journaux.
    $ht = $dir.'/.htaccess';
    if (!is_file($ht)) {
        @file_put_contents($ht, "<IfModule mod_authz_core.c>\n    Require all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n    Order allow,deny\n    Deny from all\n</IfModule>\n");
    }
    return $dir;
}

/**
 * Retire d'un texte ou d'un contexte tout ce qui ressemble à un secret :
 * clés nommées token / key / secret / password / authorization, en-têtes « Bearer … »,
 * et toute valeur égale à un secret d'intégration configuré.
 */
function integration_redact(mixed $value, string $key = ''): mixed
{
    if (is_array($value)) {
        $out = [];
        foreach ($value as $k => $v) $out[$k] = integration_redact($v, (string)$k);
        return $out;
    }
    if (!is_string($value)) return $value;
    if ($key !== '' && preg_match('/token|secret|password|passwd|authorization|api_?key|jeton/i', $key)) return '[masqué]';
    $value = preg_replace('/(Bearer|Basic)\s+[A-Za-z0-9._~+\/=-]+/i', '$1 [masqué]', $value) ?? $value;
    static $known = null;
    if ($known === null) {
        $known = [];
        foreach (['pennylane_api_key', 'claude_api_key', 'yousign_api_key', 'yousign_webhook_secret'] as $n) {
            $cfg = app_config()['secrets'][$n] ?? null;
            if (is_string($cfg) && strlen(trim($cfg)) >= 8) $known[] = trim($cfg);
            $row = integration_settings_rows()[$n] ?? null;
            if ($row && strlen(trim((string)$row['valeur'])) >= 8) $known[] = trim((string)$row['valeur']);
        }
    }
    foreach ($known as $secretValue) $value = str_replace($secretValue, '[masqué]', $value);
    return $value;
}

/**
 * Journalise un événement : une ligne dans storage/logs/{canal}-AAAA-MM.log et une ligne dans
 * la table integration_log. Jamais de secret (masquage automatique), jamais de corps de réponse.
 */
function integration_log(string $channel, string $message, array $context = []): void
{
    $channel = preg_replace('/[^a-z0-9_-]/', '', strtolower($channel)) ?: 'app';
    $message = (string)integration_redact($message);
    $context = integration_redact($context);
    $detail = $context ? json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '';
    $file = integration_log_dir().'/'.$channel.'-'.date('Y-m').'.log';
    @file_put_contents($file, date('c').' '.$message.($detail !== '' ? ' '.$detail : '')."\n", FILE_APPEND | LOCK_EX);
    try {
        db_execute('INSERT INTO integration_log (canal, action, detail) VALUES (?, ?, ?)',
            [$channel, mb_substr($message, 0, 255), $detail !== '' ? mb_substr($detail, 0, 2000) : null]);
    } catch (Throwable $e) { /* table absente ou base indisponible : le fichier suffit */ }
}

/** Dernières lignes du journal en base (écran des réglages). */
function integration_log_recent(string $channel = '', int $limit = 20): array
{
    $limit = max(1, min(200, $limit));
    try {
        return $channel === ''
            ? db_fetch_all('SELECT * FROM integration_log ORDER BY id DESC LIMIT '.$limit)
            : db_fetch_all('SELECT * FROM integration_log WHERE canal = ? ORDER BY id DESC LIMIT '.$limit, [$channel]);
    } catch (Throwable $e) { return []; }
}

/* ─── HTTP ────────────────────────────────────────────────── */
/**
 * Requête HTTP en cURL. Retourne ['status' => int, 'body' => string, 'json' => ?array,
 * 'headers' => array, 'error' => ?string, 'ms' => int]. Ne lève jamais d'exception.
 * $body : tableau (envoyé en JSON), chaîne brute, ou null.
 */
function integration_http(string $method, string $url, array $headers = [], array|string|null $body = null, int $timeout = 20): array
{
    $out = ['status' => 0, 'body' => '', 'json' => null, 'headers' => [], 'error' => null, 'ms' => 0];
    if (!function_exists('curl_init')) { $out['error'] = 'Extension cURL absente sur le serveur.'; return $out; }
    $ch = curl_init($url);
    $respHeaders = [];
    $opts = [
        CURLOPT_CUSTOMREQUEST  => strtoupper($method),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_CONNECTTIMEOUT => min(10, $timeout),
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_HEADERFUNCTION => static function ($c, string $h) use (&$respHeaders): int {
            $p = strpos($h, ':');
            if ($p !== false) $respHeaders[strtolower(trim(substr($h, 0, $p)))] = trim(substr($h, $p + 1));
            return strlen($h);
        },
    ];
    if ($body !== null) {
        $opts[CURLOPT_POSTFIELDS] = is_array($body) ? json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : $body;
    }
    curl_setopt_array($ch, $opts);
    $t0 = microtime(true);
    $resp = curl_exec($ch);
    $out['ms'] = (int)round((microtime(true) - $t0) * 1000);
    if ($resp === false) {
        $out['error'] = curl_error($ch) ?: 'Connexion impossible';
    } else {
        $out['body'] = (string)$resp;
        $decoded = json_decode($out['body'], true);
        $out['json'] = is_array($decoded) ? $decoded : null;
    }
    $out['status']  = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $out['headers'] = $respHeaders;
    curl_close($ch);
    return $out;
}

/* ─── Validation de JSON selon un schéma (sous-ensemble utilisé ici) ─── */
/** Retourne la liste des erreurs (vide si conforme) : type, required, enum, additionalProperties, items. */
function json_schema_errors(mixed $value, array $schema, string $path = '$'): array
{
    $errors = [];
    $types = (array)($schema['type'] ?? []);
    if ($types) {
        $ok = false;
        foreach ($types as $t) {
            $ok = $ok || match ($t) {
                'object'  => is_array($value) && ($value === [] || !array_is_list($value)),
                'array'   => is_array($value) && array_is_list($value),
                'string'  => is_string($value),
                'integer' => is_int($value),
                'number'  => is_int($value) || is_float($value),
                'boolean' => is_bool($value),
                'null'    => $value === null,
                default   => true,
            };
        }
        if (!$ok) return [$path.' : type attendu '.implode('|', $types)];
    }
    if (isset($schema['enum']) && !in_array($value, $schema['enum'], true)) $errors[] = $path.' : valeur hors liste';
    if (is_array($value) && in_array('object', $types, true)) {
        foreach ((array)($schema['required'] ?? []) as $req) {
            if (!array_key_exists($req, $value)) $errors[] = $path.'.'.$req.' : manquant';
        }
        $props = (array)($schema['properties'] ?? []);
        foreach ($value as $k => $v) {
            if (isset($props[$k])) $errors = array_merge($errors, json_schema_errors($v, $props[$k], $path.'.'.$k));
            elseif (($schema['additionalProperties'] ?? true) === false) $errors[] = $path.'.'.$k.' : champ inattendu';
        }
    }
    if (is_array($value) && in_array('array', $types, true) && isset($schema['items'])) {
        foreach ($value as $i => $v) $errors = array_merge($errors, json_schema_errors($v, $schema['items'], $path.'['.$i.']'));
    }
    return $errors;
}

/* ─── Montants ────────────────────────────────────────────── */
/** 1234.5 → « 1 234,50 € » */
function money_fr(float|int|string|null $amount): string
{
    return number_format((float)$amount, 2, ',', "\u{00A0}")."\u{00A0}€";
}
