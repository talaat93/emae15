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

/** Secret d'intégration : config.local.php ('secrets' => [...]) puis réglage en base. */
function integration_secret(string $name): string
{
    $cfg = app_config()['secrets'][$name] ?? null;
    if (is_string($cfg) && trim($cfg) !== '') return trim($cfg);
    return trim(global_setting($name, ''));
}

/** Enregistre un secret en base (jamais affiché ensuite, seulement « configuré »). */
function integration_store_secret(string $name, string $value): void
{
    try {
        db_execute('INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
                    ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)', [$name, trim($value)]);
        settings_cache(true);
    } catch (Throwable $e) { integration_log('settings', 'échec enregistrement '.$name.' : '.$e->getMessage()); }
}

function integration_setting(string $name, string $default = ''): string
{
    return global_setting($name, $default);
}

/** Masque un secret pour l'affichage : « sk-a…9f3c ». */
function integration_mask(string $secret): string
{
    if ($secret === '') return '';
    return mb_strlen($secret) <= 10 ? '••••' : mb_substr($secret, 0, 5).'…'.mb_substr($secret, -4);
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

/** Une ligne par événement ; jamais de clé, de contenu client ni de corps de réponse. */
function integration_log(string $channel, string $message, array $context = []): void
{
    $channel = preg_replace('/[^a-z0-9_-]/', '', strtolower($channel)) ?: 'app';
    $line = date('c').' '.$message;
    if ($context) $line .= ' '.json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $file = integration_log_dir().'/'.$channel.'-'.date('Y-m').'.log';
    @file_put_contents($file, $line."\n", FILE_APPEND | LOCK_EX);
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
