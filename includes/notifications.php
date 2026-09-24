<?php
declare(strict_types=1);
/**
 * Notifications de l'activité terrain.
 *
 *  - Technicien : notification push sur son téléphone (application installée),
 *    doublée d'un e-mail, et d'un SMS si activé.
 *  - Client : e-mail et SMS (intervention planifiée, technicien en route).
 *
 * Les notifications push suivent la norme Web Push (RFC 8030 / 8291 / 8292) ;
 * elles sont chiffrées ici avec OpenSSL, sans bibliothèque externe.
 * Aucun envoi ne doit jamais bloquer l'action qui le déclenche : toutes les
 * erreurs sont journalisées puis ignorées.
 */

/* ═══════════════════════════════════════════════════
   RÉGLAGES
═══════════════════════════════════════════════════ */
function notif_settings_defaults(): array
{
    return [
        'notif_tech_push'     => '1',
        'notif_tech_email'    => '1',
        'notif_tech_sms'      => '0',
        'notif_client_email'  => '1',
        'notif_client_sms'    => '1',
        'notif_client_enroute'=> '1',
    ];
}

function notif_enabled(string $key): bool
{
    return global_setting($key, notif_settings_defaults()[$key] ?? '0') === '1';
}

function notif_store_setting(string $key, string $value): void
{
    try {
        db_execute('INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
                    ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)', [$key, $value]);
        settings_cache(true);
    } catch (Throwable $e) { error_log('[EMAE notif] réglage '.$key.' : '.$e->getMessage()); }
}

/* ═══════════════════════════════════════════════════
   E-MAIL ET SMS
═══════════════════════════════════════════════════ */
function notif_mail(string $to, string $subject, string $title, array $lines, ?string $ctaUrl = null, ?string $ctaLabel = null): bool
{
    if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) return false;
    $site = company_name();
    $h = static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    $rows = '';
    foreach ($lines as $l) $rows .= '<p style="margin:0 0 10px;font-size:15px;line-height:1.55;color:#1f2937;">'.$l.'</p>';
    $cta = $ctaUrl ? '<p style="margin:22px 0 0;"><a href="'.$h($ctaUrl).'" style="display:inline-block;background:#F07B1D;color:#fff;text-decoration:none;padding:12px 22px;border-radius:8px;font-weight:700;font-size:14px;">'.$h($ctaLabel ?: 'Ouvrir').'</a></p>' : '';
    $html = '<!DOCTYPE html><html lang="fr"><body style="margin:0;background:#f3f5f8;font-family:Arial,Helvetica,sans-serif;">'
        . '<table width="100%" cellpadding="0" cellspacing="0" style="background:#f3f5f8;padding:24px 12px;"><tr><td align="center">'
        . '<table width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background:#fff;border-radius:12px;overflow:hidden;">'
        . '<tr><td style="background:#16243f;padding:18px 24px;color:#fff;font-size:18px;font-weight:800;letter-spacing:.04em;">'.$h($site).'</td></tr>'
        . '<tr><td style="padding:24px;"><h1 style="margin:0 0 14px;font-size:19px;color:#16243f;">'.$h($title).'</h1>'.$rows.$cta.'</td></tr>'
        . '<tr><td style="padding:14px 24px;background:#f8fafc;color:#8c99ad;font-size:12px;">'.$h($site).' · '.$h(company_phone_safe()).'</td></tr>'
        . '</table></td></tr></table></body></html>';
    $headers  = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= 'From: =?UTF-8?B?'.base64_encode($site).'?= <'.company_email().">\r\n";
    $headers .= 'Reply-To: '.company_email()."\r\n";
    $ok = @mail($to, '=?UTF-8?B?'.base64_encode($subject).'?=', $html, $headers);
    if (!$ok) error_log('[EMAE notif] e-mail non envoyé à '.$to);
    return $ok;
}

function company_phone_safe(): string
{
    return global_setting('company_phone', '');
}

/** SMS via le compte OVH configuré dans l'administration (sinon, l'ancienne passerelle). */
function notif_sms(string $to, string $message): bool
{
    if (trim($to) === '') return false;
    try {
        if (function_exists('send_sms_ovh') && send_sms_ovh($to, $message)) return true;
    } catch (Throwable $e) { error_log('[EMAE notif] SMS OVH : '.$e->getMessage()); }
    return false;
}

function notif_abs_url(string $path): string
{
    $base = site_base_url();
    if ($base === '') {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $base = $scheme.'://'.($_SERVER['HTTP_HOST'] ?? 'localhost');
    }
    return rtrim($base, '/').url_for($path);
}

/* ═══════════════════════════════════════════════════
   WEB PUSH
═══════════════════════════════════════════════════ */
function push_b64u_encode(string $bin): string { return rtrim(strtr(base64_encode($bin), '+/', '-_'), '='); }
function push_b64u_decode(string $s): string { return (string)base64_decode(strtr($s, '-_', '+/').str_repeat('=', (4 - strlen($s) % 4) % 4)); }

/** Clé publique brute (65 octets, format non compressé) d'une clé EC P-256. */
function push_raw_public_key($key): string
{
    $d = openssl_pkey_get_details($key);
    return "\x04".str_pad($d['ec']['x'], 32, "\0", STR_PAD_LEFT).str_pad($d['ec']['y'], 32, "\0", STR_PAD_LEFT);
}

/** Clé publique P-256 brute → ressource OpenSSL. */
function push_public_key_from_raw(string $raw)
{
    $der = hex2bin('3059301306072a8648ce3d020106082a8648ce3d030107034200').$raw;
    $pem = "-----BEGIN PUBLIC KEY-----\n".chunk_split(base64_encode($der), 64, "\n")."-----END PUBLIC KEY-----\n";
    return openssl_pkey_get_public($pem);
}

/** Paire de clés VAPID du site, créée au premier besoin. */
function push_vapid(): ?array
{
    static $cache = null;
    if ($cache !== null) return $cache ?: null;
    $pem = global_setting('push_vapid_private', '');
    $key = $pem !== '' ? openssl_pkey_get_private($pem) : false;
    if (!$key) {
        $key = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
        if (!$key || !openssl_pkey_export($key, $pem)) { $cache = []; return null; }
        notif_store_setting('push_vapid_private', $pem);
    }
    $cache = ['key' => $key, 'public' => push_b64u_encode(push_raw_public_key($key))];
    return $cache;
}

function push_vapid_public_key(): string
{
    $v = push_vapid();
    return $v['public'] ?? '';
}

/** Signature ES256 (JWT) : conversion DER → r||s de 64 octets. */
function push_es256_sign(string $data, $key): ?string
{
    if (!openssl_sign($data, $der, $key, OPENSSL_ALGO_SHA256)) return null;
    $pos = 2; if (ord($der[1]) & 0x80) $pos += ord($der[1]) & 0x7f;
    $out = '';
    for ($i = 0; $i < 2; $i++) {
        $pos++; // 0x02
        $len = ord($der[$pos++]);
        $int = substr($der, $pos, $len); $pos += $len;
        $out .= str_pad(ltrim($int, "\0"), 32, "\0", STR_PAD_LEFT);
    }
    return $out;
}

/** Chiffrement aes128gcm d'un message pour un abonnement (RFC 8291). */
function push_encrypt(string $payload, string $uaPublicB64, string $authB64): ?string
{
    $uaPublic = push_b64u_decode($uaPublicB64);
    $auth     = push_b64u_decode($authB64);
    if (strlen($uaPublic) !== 65 || strlen($auth) < 16) return null;
    $uaKey = push_public_key_from_raw($uaPublic);
    $asKey = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
    if (!$uaKey || !$asKey) return null;
    $asPublic = push_raw_public_key($asKey);
    $shared = openssl_pkey_derive($uaKey, $asKey, 32);
    if ($shared === false) return null;

    $ikm   = hash_hkdf('sha256', $shared, 32, "WebPush: info\0".$uaPublic.$asPublic, $auth);
    $salt  = random_bytes(16);
    $cek   = hash_hkdf('sha256', $ikm, 16, "Content-Encoding: aes128gcm\0", $salt);
    $nonce = hash_hkdf('sha256', $ikm, 12, "Content-Encoding: nonce\0", $salt);
    $cipher = openssl_encrypt($payload."\x02", 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, $tag);
    if ($cipher === false) return null;
    return $salt.pack('N', 4096).chr(65).$asPublic.$cipher.$tag;
}

function push_ensure_table(): void
{
    static $done = false;
    if ($done) return;
    $done = true;
    try {
        db_execute("CREATE TABLE IF NOT EXISTS push_subscriptions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_type VARCHAR(20) NOT NULL,
            user_id INT NOT NULL,
            endpoint TEXT NOT NULL,
            endpoint_hash CHAR(64) NOT NULL,
            p256dh VARCHAR(255) NOT NULL,
            auth VARCHAR(255) NOT NULL,
            user_agent VARCHAR(255) NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_endpoint (endpoint_hash),
            INDEX idx_user (user_type, user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (Throwable $e) { error_log('[EMAE push] table : '.$e->getMessage()); }
}

function push_subscribe(string $userType, int $userId, array $sub): bool
{
    $endpoint = (string)($sub['endpoint'] ?? '');
    $p256dh   = (string)($sub['keys']['p256dh'] ?? '');
    $auth     = (string)($sub['keys']['auth'] ?? '');
    if (!preg_match('#^https://#', $endpoint) || $p256dh === '' || $auth === '') return false;
    push_ensure_table();
    try {
        db_execute("INSERT INTO push_subscriptions (user_type, user_id, endpoint, endpoint_hash, p256dh, auth, user_agent)
                    VALUES (?,?,?,?,?,?,?)
                    ON DUPLICATE KEY UPDATE user_type = VALUES(user_type), user_id = VALUES(user_id),
                        p256dh = VALUES(p256dh), auth = VALUES(auth), user_agent = VALUES(user_agent)",
            [$userType, $userId, $endpoint, hash('sha256', $endpoint), $p256dh, $auth, mb_substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255)]);
        return true;
    } catch (Throwable $e) { error_log('[EMAE push] abonnement : '.$e->getMessage()); return false; }
}

function push_unsubscribe(string $endpoint): void
{
    push_ensure_table();
    try { db_execute('DELETE FROM push_subscriptions WHERE endpoint_hash = ?', [hash('sha256', $endpoint)]); } catch (Throwable $e) {}
}

/** Envoie une notification à tous les appareils d'un utilisateur. Retourne le nombre d'envois réussis. */
function push_send_to(string $userType, int $userId, string $title, string $body, string $url = '', string $tag = ''): int
{
    $vapid = push_vapid();
    if (!$vapid || !function_exists('curl_init')) return 0;
    push_ensure_table();
    try { $subs = db_fetch_all('SELECT * FROM push_subscriptions WHERE user_type = ? AND user_id = ?', [$userType, $userId]); }
    catch (Throwable $e) { return 0; }
    $payload = json_encode(['title' => $title, 'body' => $body, 'url' => $url, 'tag' => $tag], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $sent = 0;
    foreach ($subs as $s) {
        $parts = parse_url($s['endpoint']);
        if (empty($parts['scheme']) || empty($parts['host'])) continue;
        $aud = $parts['scheme'].'://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '');
        $jwtHead = push_b64u_encode(json_encode(['typ' => 'JWT', 'alg' => 'ES256']));
        $jwtBody = push_b64u_encode(json_encode(['aud' => $aud, 'exp' => time() + 12 * 3600, 'sub' => 'mailto:'.company_email()]));
        $sig = push_es256_sign($jwtHead.'.'.$jwtBody, $vapid['key']);
        $body = push_encrypt($payload, $s['p256dh'], $s['auth']);
        if ($sig === null || $body === null) continue;
        $ch = curl_init($s['endpoint']);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 6,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/octet-stream',
                'Content-Encoding: aes128gcm',
                'TTL: 86400',
                'Urgency: high',
                'Authorization: vapid t='.$jwtHead.'.'.$jwtBody.'.'.push_b64u_encode($sig).', k='.$vapid['public'],
            ],
        ]);
        curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($code >= 200 && $code < 300) $sent++;
        elseif (in_array($code, [404, 410], true)) push_unsubscribe($s['endpoint']);   // appareil désinscrit
        else error_log('[EMAE push] échec '.$code.' pour l\'abonnement #'.$s['id']);
    }
    return $sent;
}

/* ═══════════════════════════════════════════════════
   ÉVÉNEMENTS
═══════════════════════════════════════════════════ */
function notif_iv_summary(array $iv): array
{
    $client = trim(($iv['firstname'] ?? '').' '.($iv['lastname'] ?? '')) ?: 'Client';
    $when = !empty($iv['scheduled_date'])
        ? date('d/m/Y', strtotime($iv['scheduled_date'])).(!empty($iv['scheduled_time']) ? ' à '.substr((string)$iv['scheduled_time'], 0, 5) : '')
        : 'date à confirmer';
    $city = trim((string)($iv['client_city'] ?? ''));
    $what = trim((string)($iv['type_label'] ?? '')) ?: (intervention_category_config()[$iv['category'] ?? '']['label'] ?? 'Intervention');
    return compact('client', 'when', 'city', 'what');
}

/** Une intervention vient d'être attribuée (ou réattribuée) à un technicien. */
function notify_intervention_assigned(int $ivId): void
{
    try {
        $iv = get_intervention_by_id($ivId);
        if (!$iv || empty($iv['technician_id'])) return;
        $tech = get_tech_by_id((int)$iv['technician_id']);
        if (!$tech) return;
        $s = notif_iv_summary($iv);
        $url = notif_abs_url('tech/disp_intervention.php?id='.$ivId);
        $title = (!empty($iv['urgency']) ? 'URGENT — ' : '').'Nouvelle intervention';
        $body = $s['client'].($s['city'] ? ' ('.$s['city'].')' : '').' · '.$s['what'].' · '.$s['when'];

        if (notif_enabled('notif_tech_push')) push_send_to('tech', (int)$tech['id'], $title, $body, $url, 'iv-'.$ivId);
        if (notif_enabled('notif_tech_email') && !empty($tech['email'])) {
            notif_mail((string)$tech['email'], $title.' — '.$s['client'], $title, [
                '<b>'.htmlspecialchars($s['client'], ENT_QUOTES, 'UTF-8').'</b>'.($s['city'] ? ' — '.htmlspecialchars($s['city'], ENT_QUOTES, 'UTF-8') : ''),
                htmlspecialchars($s['what'], ENT_QUOTES, 'UTF-8'),
                'Prévue le '.htmlspecialchars($s['when'], ENT_QUOTES, 'UTF-8'),
            ], $url, 'Voir l\'intervention');
        }
        if (notif_enabled('notif_tech_sms') && !empty($tech['phone'])) {
            notif_sms((string)$tech['phone'], company_name().' : '.$title.' — '.$body);
        }
        notify_client_scheduled($iv, $tech);
    } catch (Throwable $e) { error_log('[EMAE notif] attribution #'.$ivId.' : '.$e->getMessage()); }
}

/** Le client est prévenu dès qu'une date et un technicien sont fixés. */
function notify_client_scheduled(array $iv, array $tech): void
{
    if (empty($iv['scheduled_date'])) return;
    $s = notif_iv_summary($iv);
    $co = company_name();
    $msg = $co.' : votre intervention ('.$s['what'].') est planifiée le '.$s['when'].'. Technicien : '.$tech['name'].'.';
    $client = get_client_by_id((int)$iv['client_id']);
    if (!$client) return;
    if (notif_enabled('notif_client_email') && !empty($client['email'])) {
        notif_mail((string)$client['email'], 'Votre intervention est planifiée', 'Votre intervention est planifiée', [
            'Bonjour '.htmlspecialchars(trim(($client['firstname'] ?? '').' '.($client['lastname'] ?? '')), ENT_QUOTES, 'UTF-8').',',
            'Votre intervention <b>'.htmlspecialchars($s['what'], ENT_QUOTES, 'UTF-8').'</b> est planifiée le <b>'.htmlspecialchars($s['when'], ENT_QUOTES, 'UTF-8').'</b>.',
            'Votre technicien : '.htmlspecialchars((string)$tech['name'], ENT_QUOTES, 'UTF-8').'. Il vous préviendra lorsqu\'il sera en route.',
            'Pour toute question, répondez simplement à cet e-mail.',
        ]);
    }
    if (notif_enabled('notif_client_sms') && !empty($client['phone'])) notif_sms((string)$client['phone'], $msg);
}

/** Le technicien vient de partir : le client est prévenu. */
function notify_client_en_route(int $ivId): void
{
    if (!notif_enabled('notif_client_enroute')) return;
    try {
        $iv = get_intervention_by_id($ivId);
        if (!$iv) return;
        $client = get_client_by_id((int)$iv['client_id']);
        if (!$client) return;
        $techName = (string)($iv['tech_name'] ?? 'Votre technicien');
        $msg = company_name().' : '.$techName.' est en route pour votre intervention. Il arrive bientôt.';
        if (notif_enabled('notif_client_sms') && !empty($client['phone'])) notif_sms((string)$client['phone'], $msg);
        if (notif_enabled('notif_client_email') && !empty($client['email'])) {
            notif_mail((string)$client['email'], 'Votre technicien est en route', 'Votre technicien est en route', [
                htmlspecialchars($techName, ENT_QUOTES, 'UTF-8').' vient de partir pour votre intervention et arrive bientôt.',
            ]);
        }
    } catch (Throwable $e) { error_log('[EMAE notif] en route #'.$ivId.' : '.$e->getMessage()); }
}

/** Nouvelle tâche ou nouveau rappel pour un technicien (ou pour tous). */
function notify_task_created(int $taskId): void
{
    try {
        $task = db_fetch('SELECT * FROM tasks WHERE id = ?', [$taskId]);
        if (!$task) return;
        $techs = !empty($task['technician_id'])
            ? array_filter([get_tech_by_id((int)$task['technician_id'])])
            : db_fetch_all("SELECT * FROM technicians WHERE status = 'actif'");
        $title = (!empty($task['urgent']) ? 'URGENT — ' : '').'Nouveau rappel';
        $body = (string)$task['title'].(!empty($task['due_date']) ? ' · pour le '.date('d/m', strtotime($task['due_date'])) : '');
        $url = notif_abs_url('tech/dashboard.php');
        foreach ($techs as $t) {
            if (notif_enabled('notif_tech_push')) push_send_to('tech', (int)$t['id'], $title, $body, $url, 'task-'.$taskId);
            if (notif_enabled('notif_tech_email') && !empty($t['email'])) {
                notif_mail((string)$t['email'], $title.' — '.$task['title'], $title, array_filter([
                    '<b>'.htmlspecialchars((string)$task['title'], ENT_QUOTES, 'UTF-8').'</b>',
                    !empty($task['description']) ? nl2br(htmlspecialchars((string)$task['description'], ENT_QUOTES, 'UTF-8')) : '',
                ]), $url, 'Ouvrir mon espace');
            }
            if (notif_enabled('notif_tech_sms') && !empty($t['phone'])) notif_sms((string)$t['phone'], company_name().' : '.$title.' — '.$body);
        }
    } catch (Throwable $e) { error_log('[EMAE notif] tâche #'.$taskId.' : '.$e->getMessage()); }
}
