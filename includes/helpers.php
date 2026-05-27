<?php
declare(strict_types=1);

/* ═══════════════════════════════════════════════════
   CORE UTILITIES
═══════════════════════════════════════════════════ */
function boot_session(): void
{
    if (session_status() === PHP_SESSION_NONE) session_start();
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
    foreach (['/admin', '/tech'] as $_strip) {
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

function route_url(string $slug = ''): string
{
    if ($slug === '' || $slug === 'home') return url_for('index.php');
    return url_for('index.php?route=' . rawurlencode($slug));
}

function current_year(): string { return date('Y'); }

function redirect_to(string $path): never
{
    if (!preg_match('#^(https?:|tel:|mailto:)#i', $path)) $path = url_for($path);
    header('Location: ' . $path);
    exit;
}

function csrf_token(): string
{
    boot_session();
    if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    return (string)$_SESSION['csrf_token'];
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
   SETTINGS
═══════════════════════════════════════════════════ */
function setting(string $key, ?string $fallback = null): string
{
    static $cache = null;
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
    $v = $cache[$key] ?? null;
    return ($v === null || $v === '') ? (string)($fallback ?? '') : $v;
}

function site_setting(string $key, ?string $fallback = null): string { return setting($key, $fallback); }

function set_setting(string $key, mixed $value): void
{
    $s = is_scalar($value) || $value === null ? (string)$value : json_encode($value, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    $exists = db_fetch('SELECT id FROM settings WHERE setting_key = ?', [$key]);
    if ($exists) db_execute('UPDATE settings SET setting_value = ? WHERE setting_key = ?', [$s, $key]);
    else db_execute('INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)', [$key, $s]);
}

function set_site_setting(string $key, mixed $value): void { set_setting($key, $value); }

function setting_bool(string $key, bool $fallback = false): bool
{
    return in_array(strtolower(setting($key, $fallback ? '1' : '0')), ['1','true','yes','on'], true);
}

function get_json_setting(string $key, array $fallback = []): array
{
    $d = json_decode(setting($key, ''), true);
    return is_array($d) ? $d : $fallback;
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
function company_regions(): string { return setting('company_regions', 'Île-de-France et Occitanie'); }
function company_hours(): string { return setting('company_hours', '24h/24 — 7j/7'); }
function company_address(): string { return setting('company_address', 'Île-de-France et Occitanie'); }
function company_siret(): string { return setting('company_siret', ''); }
function company_slogan(): string { return setting('company_slogan', 'Dépannage & installation multitechnique'); }
function company_whatsapp(): string { return setting('company_whatsapp', ''); }
function site_logo_path(): string { return setting('site_logo', 'storage/uploads/logos/logo-emae-default.svg'); }
function site_logo_url(): string { return asset_url(site_logo_path()); }
function site_logo_width(): string { return css_value(setting('site_logo_width', '180'), '180px'); }
function site_logo_height(): string { return css_value(setting('site_logo_height', 'auto'), 'auto'); }
function site_logo_position(): string { $p = setting('site_logo_position', 'left'); return in_array($p, ['left','center','right']) ? $p : 'left'; }

/* ═══════════════════════════════════════════════════
   THEME & STYLES
═══════════════════════════════════════════════════ */


function schema_local_business(): string
{
    $addr = company_address();
    $wa   = company_whatsapp();
    $data = [
        '@context'    => 'https://schema.org',
        '@type'       => 'ElectricalContractor',
        'name'        => company_name(),
        'telephone'   => company_phone(),
        'email'       => company_email(),
        'description' => setting('company_description', 'Entreprise multitechnique — dépannage, installation, entretien en électricité, plomberie, chauffage et climatisation.'),
        'areaServed'  => array_map('trim', explode(',', company_regions())),
        'openingHoursSpecification' => [[
            '@type'     => 'OpeningHoursSpecification',
            'dayOfWeek' => ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'],
            'opens'     => '00:00',
            'closes'    => '23:59',
        ]],
        'url'        => site_base_url() !== '' ? site_base_url() : route_url(''),
        'priceRange' => '€€',
    ];
    if ($addr !== '') {
        $data['address'] = ['@type'=>'PostalAddress','streetAddress'=>$addr,'addressCountry'=>'FR'];
    }
    if (company_siret() !== '') {
        $data['identifier'] = ['@type'=>'PropertyValue','name'=>'SIRET','value'=>company_siret()];
    }
    $rv = setting('schema_rating_value', '');
    $rc = setting('schema_review_count', '');
    if ($rv !== '' && $rc !== '') {
        $data['aggregateRating'] = ['@type'=>'AggregateRating','ratingValue'=>(float)$rv,'reviewCount'=>(int)$rc,'bestRating'=>5,'worstRating'=>1];
    }
    if ($wa !== '') {
        $data['sameAs'] = ['https://wa.me/'.preg_replace('/[^0-9]/', '', $wa)];
    }
    return json_encode($data, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
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
        ['label'=>setting('nav_faq','FAQ'),                'url'=>route_url('faq')],
        ['label'=>setting('nav_contact','Contact'),        'url'=>route_url('contact')],
    ];
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
        'description' => setting('home_meta_description', 'Dépannage urgence, électricité, plomberie, chauffage, climatisation en '.company_regions().'. Devis gratuit, intervention rapide.'),
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
function send_quote_notification(array $data): bool
{
    $to = setting('form_email_to', company_email());
    if (trim($to) === '') return false;

    $name       = trim($data['full_name']    ?? '');
    $phone      = trim($data['phone']        ?? '');
    $email      = trim($data['email']        ?? '');
    $city       = trim($data['city']         ?? '');
    $address    = trim($data['address']      ?? '');
    $postalCode = trim($data['postal_code']  ?? '');
    $service    = trim($data['service_type'] ?? '');
    $message    = trim($data['message']      ?? '');
    $urgency    = trim($data['urgency']      ?? 'Normale');
    $source     = trim($data['source']       ?? '');
    $site    = company_name();
    $now     = date('d/m/Y à H:i');

    $urgencyColor = ($urgency === 'Urgente' || $urgency === 'Urgence') ? '#c0392b' : '#1a7ab5';
    $urgencyBg    = ($urgency === 'Urgente' || $urgency === 'Urgence') ? '#fdf2f2' : '#f0f7ff';

    $subject = '=?UTF-8?B?'.base64_encode('🔔 Nouvelle demande — '.$name.' ('.$urgency.')').'?=';

    $html = '<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"></head><body style="margin:0;padding:0;background:#f4f6fb;font-family:Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f4f6fb;padding:32px 0;">
<tr><td align="center">
<table width="600" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,.08);">
  <tr><td style="background:#061029;padding:28px 36px;">
    <p style="margin:0;font-size:22px;font-weight:700;color:#ffffff;">'.$site.'</p>
    <p style="margin:6px 0 0;font-size:13px;color:#F07B1D;font-weight:600;text-transform:uppercase;letter-spacing:.08em;">Nouvelle demande de devis</p>
  </td></tr>
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
        <td style="padding:12px 18px;font-size:14px;font-weight:700;color:#F07B1D;border-bottom:1px solid #eef0f7;"><a href="tel:'.preg_replace('/\s+/','',$phone).'" style="color:#F07B1D;text-decoration:none;">'.$phone.'</a></td>
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
  <tr><td style="padding:0 36px 32px;text-align:center;">
    <a href="'.site_base_url().'/admin/quotes.php" style="display:inline-block;background:#061029;color:#ffffff;text-decoration:none;padding:14px 32px;border-radius:8px;font-size:14px;font-weight:700;">Voir dans l\'admin →</a>
  </td></tr>
  <tr><td style="background:#f0f2f8;padding:16px 36px;text-align:center;">
    <p style="margin:0;font-size:12px;color:#888;">Email automatique — '.$site.' · Ne pas répondre</p>
  </td></tr>
</table>
</td></tr></table>
</body></html>';

    $fromName  = '=?UTF-8?B?'.base64_encode($site.' — Notification').'?=';
    $fromEmail = company_email();
    $replyTo   = $email !== '' ? $email : $to;

    $headers  = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "From: {$fromName} <{$fromEmail}>\r\n";
    $headers .= "Reply-To: {$replyTo}\r\n";
    $headers .= "X-Mailer: PHP/".PHP_VERSION."\r\n";

    $ok = mail($to, $subject, $html, $headers);
    if (!$ok) error_log('[EMAE] send_quote_notification failed — to='.$to.' from='.$fromEmail);
    return $ok;
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
    try {
        $r = db_fetch("SELECT * FROM technicians WHERE email = ? AND status = 'actif'", [trim($email)]);
        if (!$r) return null;
        return password_verify($password, (string)$r['password_hash']) ? $r : null;
    } catch (Throwable $e) { return null; }
}

function require_tech_auth(): array
{
    boot_session();
    if (empty($_SESSION['tech_id'])) { header('Location: '.url_for('tech/login.php')); exit; }
    try {
        $t = db_fetch("SELECT * FROM technicians WHERE id = ? AND status = 'actif'", [(int)$_SESSION['tech_id']]);
    } catch (Throwable $e) { $t = null; }
    if (!$t) { unset($_SESSION['tech_id']); header('Location: '.url_for('tech/login.php')); exit; }
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
    if (!move_uploaded_file($tmp, $target)) return null;
    $path = 'storage/uploads/'.trim($dir,'/').'/'.$filename;
    db_execute('INSERT INTO media (file_path, alt_text, category) VALUES (?, ?, ?)', [$path, '', $dir]);
    return $path;
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
        'title'         => setting('home_title',         'Le partenaire technique de vos bâtiments en Île-de-France et en Occitanie'),
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
        'quote_eyebrow' => setting('home_quote_eyebrow', 'Rappel gratuit sous 30 min'),
        'quote_title'   => setting('home_quote_title',   'Obtenir un rappel rapide'),
        'quote_service_label'      => setting('home_quote_service_label',      'Service'),
        'quote_city_label'         => setting('home_quote_city_label',         'Ville'),
        'quote_city_placeholder'   => setting('home_quote_city_placeholder',   'Ex : Meaux, Paris, Toulouse'),
        'quote_button_label'       => setting('home_quote_button_label',       'Être rappelé gratuitement'),
        'quote_meta'               => setting('home_quote_meta',               '✓ Gratuit  ✓ Rapide  ✓ Sans engagement'),
        'trust_1_icon'  => setting('home_trust_1_icon',  '⚡'),
        'trust_1_label' => setting('home_trust_1_label', 'Intervention < 2h'),
        'trust_2_icon'  => setting('home_trust_2_icon',  '🆓'),
        'trust_2_label' => setting('home_trust_2_label', 'Devis gratuit'),
        'trust_3_icon'  => setting('home_trust_3_icon',  '⭐'),
        'trust_3_label' => setting('home_trust_3_label', '4.9/5 — 120+ avis'),
        'trust_4_icon'  => setting('home_trust_4_icon',  '🔒'),
        'trust_4_label' => setting('home_trust_4_label', 'Artisans certifiés'),
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
            ['number'=>setting('stat_1_number','500+'),  'label'=>setting('stat_1_label','Interventions/an')],
            ['number'=>setting('stat_2_number','4.9/5'), 'label'=>setting('stat_2_label','Note client')],
            ['number'=>setting('stat_3_number','< 2h'),  'label'=>setting('stat_3_label','Délai urgence')],
            ['number'=>setting('stat_4_number','24/7'),  'label'=>setting('stat_4_label','Disponibilité')],
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
        'lead'    => setting('home_reviews_lead',    'Plus de 120 clients satisfaits en Île-de-France et en Occitanie.'),
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
        'lead'         => setting('home_zone_lead',         'Île-de-France et Occitanie — délai moyen d\'intervention inférieur à 2h pour les urgences.'),
        'badges'       => array_values(array_filter([
            setting('home_zone_badge_1','Île-de-France'),
            setting('home_zone_badge_2','Occitanie'),
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
        'cta_lead'     => 'Contactez-nous directement. Nous répondons sous 30 minutes en heures ouvrées.',
        'cta_button'   => 'Nous contacter',
        'cta_url'      => 'contact',
        'groups'       => [
            ['category'=>'Général','items'=>[
                ['q'=>'Qui est EMAE ?','a'=>'EMAE est une entreprise multitechnique avancée spécialisée dans le dépannage, l\'installation, l\'entretien et la modernisation en électricité, plomberie, chauffage et climatisation en Île-de-France et en Occitanie.'],
                ['q'=>'Quelles sont vos zones d\'intervention ?','a'=>'Nous intervenons en Île-de-France (Paris, 77, 78, 91, 92, 93, 94, 95) et en Occitanie (Toulouse, Montpellier, Nîmes et leurs environs). Appelez-nous pour vérifier votre secteur.'],
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
                ['q'=>'Quel est le délai d\'intervention en urgence ?','a'=>'En Île-de-France, notre délai moyen d\'intervention urgence est de 1h à 2h selon votre zone. Nous vous confirmons le délai exact au téléphone lors de votre appel.'],
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
        'hero_lead'     => setting('contact_hero_lead',     'Devis gratuit, réponse sous 30 min, intervention rapide. Disponible 24h/24.'),
        'form_title'    => setting('contact_form_title',    'Envoyer une demande'),
        'form_subtitle' => setting('contact_form_subtitle', 'Un technicien vous recontacte sous 30 minutes.'),
        'info_title'    => setting('contact_info_title',    'Nos coordonnées'),
        'urgency_title' => setting('contact_urgency_title', 'Urgence ? Appelez directement'),
        'urgency_text'  => setting('contact_urgency_text',  'Pour toute panne bloquante, nous intervenons en priorité 24h/24.'),
        'zones_title'   => setting('contact_zones_title',   'Zones couvertes'),
    ];
}

function zones_page_settings(): array
{
    $default = [
        'eyebrow'  => 'Zones d\'intervention',
        'title'    => 'Nous intervenons partout en',
        'title_hl' => 'Île-de-France & Occitanie',
        'lead'     => 'Des techniciens qualifiés disponibles 24h/24 et 7j/7 sur l\'ensemble de nos zones. Délai d\'intervention garanti.',
        'regions'  => [
            [
                'name'  => 'Île-de-France',
                'icon'  => '🗼',
                'color' => '#1a7ab5',
                'depts' => ['Paris (75)','Hauts-de-Seine (92)','Seine-Saint-Denis (93)','Val-de-Marne (94)','Seine-et-Marne (77)','Yvelines (78)','Essonne (91)','Val-d\'Oise (95)'],
                'cities'=> ['Paris','Boulogne-Billancourt','Saint-Denis','Montreuil','Argenteuil','Créteil','Versailles','Nanterre','Colombes','Saint-Maur-des-Fossés','Champigny-sur-Marne','Meaux','Évry','Cergy'],
                'delay' => 'Moins de 2h en urgence',
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
    $saved = get_json_setting('zones_page_settings', []);
    return array_merge($default, array_filter($saved, fn($v) => $v !== '' && $v !== null && $v !== []));
}

function all_published_reviews(): array
{
    try { return db_fetch_all('SELECT * FROM reviews WHERE is_visible = 1 ORDER BY sort_order ASC, id DESC'); }
    catch (Throwable $e) { return []; }
}

/* ═══════════════════════════════════════════════════
   REALISATIONS
═══════════════════════════════════════════════════ */
function visible_realisations(int $limit = 6): array
{
    try {
        return db_fetch_all('SELECT * FROM realisations WHERE is_visible = 1 ORDER BY sort_order ASC, id DESC LIMIT '.max(1,(int)$limit));
    } catch (Throwable $e) { return []; }
}

function all_realisations(): array
{
    try { return db_fetch_all('SELECT * FROM realisations ORDER BY sort_order ASC, id DESC'); }
    catch (Throwable $e) { return []; }
}

/* ═══════════════════════════════════════════════════
   DB RENDER HELPERS (queries pour les vues)
═══════════════════════════════════════════════════ */
function page_by_slug(string $slug): ?array
{
    try { return db_fetch('SELECT * FROM pages WHERE slug = ? AND status = ? LIMIT 1', [$slug, 'published']); }
    catch (Throwable $e) { return null; }
}

function all_pages(): array
{
    try { return db_fetch_all('SELECT * FROM pages ORDER BY sort_order ASC, title ASC'); }
    catch (Throwable $e) { return []; }
}

function visible_reviews(int $limit = 6): array
{
    try { return db_fetch_all('SELECT * FROM reviews WHERE is_visible = 1 ORDER BY sort_order ASC, id DESC LIMIT ' . max(1, (int)$limit)); }
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
        ['icon_type'=>'emoji','icon_emoji'=>'⚡','icon_image'=>'','title'=>'Réponse immédiate 24h/24','text'=>'Nous décrochez toujours. Délai moyen inférieur à 2h pour les urgences en Île-de-France.'],
        ['icon_type'=>'emoji','icon_emoji'=>'📋','icon_image'=>'','title'=>'Devis clair avant intervention','text'=>'Le prix est annoncé avant toute action. Zéro surprise sur la facture finale.'],
        ['icon_type'=>'emoji','icon_emoji'=>'🔒','icon_image'=>'','title'=>'Techniciens qualifiés','text'=>'Nos artisans sont formés, certifiés et expérimentés sur l\'ensemble de nos métiers.'],
        ['icon_type'=>'emoji','icon_emoji'=>'📍','icon_image'=>'','title'=>'Présence locale','text'=>'Île-de-France et Occitanie. Nous connaissons votre secteur et ses spécificités.'],
        ['icon_type'=>'emoji','icon_emoji'=>'🛠️','icon_image'=>'','title'=>'Tous types d\'interventions','text'=>'Urgence, installation, entretien préventif, mise aux normes — un seul interlocuteur.'],
        ['icon_type'=>'emoji','icon_emoji'=>'⭐','icon_image'=>'','title'=>'Clients satisfaits','text'=>'Plus de 120 avis vérifiés, une note de 4.9/5. La confiance se construit chantier après chantier.'],
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
            ['icon_type'=>'emoji','icon'=>'⚡','icon_img'=>'','title'=>'Intervention rapide',    'text'=>'Moins de 2h en urgence en Île-de-France. Astreinte 24h/24, 7j/7 pour toutes les pannes bloquantes.'],
            ['icon_type'=>'emoji','icon'=>'📋','icon_img'=>'','title'=>'Devis gratuit et clair', 'text'=>'Le prix est annoncé avant toute intervention. Aucune surprise sur la facture finale. Devis sans engagement.'],
            ['icon_type'=>'emoji','icon'=>'🔒','icon_img'=>'','title'=>'Artisans certifiés',     'text'=>'Nos techniciens sont formés, qualifiés et assurés pour l\'ensemble de nos métiers techniques.'],
            ['icon_type'=>'emoji','icon'=>'📍','icon_img'=>'','title'=>'Présence locale',        'text'=>'Île-de-France et Occitanie. Nous connaissons votre secteur et intervenons rapidement sur site.'],
            ['icon_type'=>'emoji','icon'=>'🛠️','icon_img'=>'','title'=>'Tous types d\'interventions','text'=>'Urgence, installation, entretien préventif, mise aux normes — un seul interlocuteur pour tous vos besoins.'],
            ['icon_type'=>'emoji','icon'=>'⭐','icon_img'=>'','title'=>'Clients satisfaits',     'text'=>'Plus de 120 avis vérifiés, note de 4.9/5. La confiance se construit chantier après chantier.'],
            ['icon_type'=>'emoji','icon'=>'🏗️','icon_img'=>'','title'=>'Particuliers & pros',   'text'=>'Logements, commerces, bureaux, copropriétés — organisation adaptée à chaque contexte.'],
            ['icon_type'=>'emoji','icon'=>'📞','icon_img'=>'','title'=>'Disponible 24h/7j',      'text'=>'Notre astreinte est active en permanence pour les situations d\'urgence qui n\'attendent pas.'],
        ],
    ];
    $saved = get_json_setting('why_us_settings', []);
    $out = array_merge($default, array_filter($saved, fn($v) => $v !== '' && $v !== null && $v !== []));
    if (!isset($out['items']) || !is_array($out['items']) || empty($out['items'])) {
        $out['items'] = $default['items'];
    }
    return $out;
}

/* ═══════════════════════════════════════════════════
   SERVICE CARDS V14 (avec image + badge + desc)
═══════════════════════════════════════════════════ */
function service_cards_v14(): array
{
    $default = [
        ['title'=>'Électricité',          'image'=>'','link'=>'electricite',  'badge'=>'Urgence 24h/7j', 'desc'=>'Dépannage, installation, mise aux normes, rénovation électrique.',
         'tags'=>['Dépannage','Installation','Mise aux normes']],
        ['title'=>'Plomberie',            'image'=>'','link'=>'plomberie',    'badge'=>'Fuite & urgence','desc'=>'Fuite d\'eau, sanitaires, débouchage, entretien réseau.',
         'tags'=>['Fuite','Sanitaires','Entretien']],
        ['title'=>'Chauffage & PAC',      'image'=>'','link'=>'chauffage',    'badge'=>'Chaudière & PAC','desc'=>'Chaudière gaz/fioul, pompe à chaleur, entretien, dépannage.',
         'tags'=>['Chaudière','PAC','Entretien annuel']],
        ['title'=>'Climatisation CVC',    'image'=>'','link'=>'climatisation','badge'=>'CVC & clim',    'desc'=>'Installation, dépannage et entretien de climatisation.',
         'tags'=>['Clim','CVC','Installation']],
    ];
    $cards = get_json_setting('home_service_cards_v14', $default);
    if (!$cards) return $default;
    $out = [];
    foreach ($default as $i => $fallback) {
        $c = is_array($cards[$i] ?? null) ? $cards[$i] : [];
        $out[] = [
            'title' => trim((string)($c['title'] ?? '')) ?: $fallback['title'],
            'image' => trim((string)($c['image'] ?? '')),
            'link'  => trim((string)($c['link']  ?? '')) ?: $fallback['link'],
            'badge' => trim((string)($c['badge'] ?? '')) ?: $fallback['badge'],
            'desc'  => trim((string)($c['desc']  ?? '')) ?: $fallback['desc'],
            'tags'  => is_array($c['tags'] ?? null) ? $c['tags'] : $fallback['tags'],
        ];
    }
    return $out;
}

/* ═══════════════════════════════════════════════════
   GÉOLOCALISATION IP — Phase 3
═══════════════════════════════════════════════════ */

function geo_display(): array
{
    boot_session();
    static $map = [
        'jura'             =>['nom'=>'Jura',             'code'=>'39','region'=>'Bourgogne-Franche-Comté'],
        'doubs'            =>['nom'=>'Doubs',            'code'=>'25','region'=>'Bourgogne-Franche-Comté'],
        'cote-dor'         =>['nom'=>"Côte-d'Or",        'code'=>'21','region'=>'Bourgogne-Franche-Comté'],
        'ain'              =>['nom'=>'Ain',              'code'=>'01','region'=>'Auvergne-Rhône-Alpes'],
        'isere'            =>['nom'=>'Isère',            'code'=>'38','region'=>'Auvergne-Rhône-Alpes'],
        'rhone'            =>['nom'=>'Rhône',            'code'=>'69','region'=>'Auvergne-Rhône-Alpes'],
        'loire'            =>['nom'=>'Loire',            'code'=>'42','region'=>'Auvergne-Rhône-Alpes'],
        'savoie'           =>['nom'=>'Savoie',           'code'=>'73','region'=>'Auvergne-Rhône-Alpes'],
        'haute-savoie'     =>['nom'=>'Haute-Savoie',    'code'=>'74','region'=>'Auvergne-Rhône-Alpes'],
        'drome'            =>['nom'=>'Drôme',            'code'=>'26','region'=>'Auvergne-Rhône-Alpes'],
        'puy-de-dome'      =>['nom'=>'Puy-de-Dôme',     'code'=>'63','region'=>'Auvergne-Rhône-Alpes'],
        'haute-loire'      =>['nom'=>'Haute-Loire',     'code'=>'43','region'=>'Auvergne-Rhône-Alpes'],
        'allier'           =>['nom'=>'Allier',           'code'=>'03','region'=>'Auvergne-Rhône-Alpes'],
        'ardeche'          =>['nom'=>'Ardèche',          'code'=>'07','region'=>'Auvergne-Rhône-Alpes'],
        'cantal'           =>['nom'=>'Cantal',           'code'=>'15','region'=>'Auvergne-Rhône-Alpes'],
        'paris'            =>['nom'=>'Paris',            'code'=>'75','region'=>'Île-de-France'],
        'seine-et-marne'   =>['nom'=>'Seine-et-Marne',  'code'=>'77','region'=>'Île-de-France'],
        'yvelines'         =>['nom'=>'Yvelines',         'code'=>'78','region'=>'Île-de-France'],
        'essonne'          =>['nom'=>'Essonne',          'code'=>'91','region'=>'Île-de-France'],
        'hauts-de-seine'   =>['nom'=>'Hauts-de-Seine',  'code'=>'92','region'=>'Île-de-France'],
        'seine-saint-denis'=>['nom'=>'Seine-Saint-Denis','code'=>'93','region'=>'Île-de-France'],
        'val-de-marne'     =>['nom'=>'Val-de-Marne',    'code'=>'94','region'=>'Île-de-France'],
        'val-d-oise'       =>['nom'=>"Val-d'Oise",      'code'=>'95','region'=>'Île-de-France'],
    ];
    $key  = $_SESSION['geo_dept'] ?? '';
    $city = $_SESSION['geo_city'] ?? '';
    if ($key === '' || !isset($map[$key])) return [];
    $d = $map[$key];
    return [
        'ville'    => $city ?: $d['nom'],
        'dept_nom' => $d['nom'],
        'dept_code'=> $d['code'],
        'region'   => $d['region'],
    ];
}

function geo_replace(string $text): string
{
    // S'assurer que la session geo est initialisée avant de lire
    if (!array_key_exists('geo_dept', $_SESSION)) geo_detect_dept();

    $geo = geo_display();

    if (!empty($geo)) {
        // Geo détectée — remplacement par la zone visiteur
        $text = str_replace(
            ['{ville}', '{dept}', '{dept_code}', '{region}'],
            [$geo['ville'], $geo['dept_nom'], $geo['dept_code'], $geo['region']],
            $text
        );
        // Auto-remplacement de company_regions() dans le texte
        $zones = company_regions();
        if ($zones !== '' && mb_strpos($text, $zones) !== false) {
            $loc = ($geo['ville'] !== $geo['dept_nom'])
                ? $geo['ville'].' et alentours ('.$geo['region'].')'
                : $geo['dept_nom'].' ('.$geo['dept_code'].')';
            $text = str_replace($zones, $loc, $text);
        }
    } else {
        // Pas de géo — fallback sur les valeurs par défaut configurées en admin
        $defRegion = setting('geo_default_region', 'Bourgogne-Franche-Comté et Auvergne-Rhône-Alpes');
        $defVille  = setting('geo_default_ville',  'votre région');
        $defDept   = setting('geo_default_dept',   $defRegion);
        $text = str_replace(
            ['{ville}', '{dept}', '{dept_code}', '{region}'],
            [$defVille, $defDept, '', $defRegion],
            $text
        );
    }

    return $text;
}

function geo_detect_dept(): ?string
{
    boot_session();

    if (array_key_exists('geo_dept', $_SESSION)) return $_SESSION['geo_dept'] ?: null;

    $dept_map = [
        // Bourgogne-Franche-Comté
        '39' => 'jura',       '25' => 'doubs',       '21' => 'cote-dor',
        '70' => 'doubs',      '90' => 'doubs',        '71' => 'cote-dor', // BFC périphérie → depts proches
        // Auvergne-Rhône-Alpes (complet)
        '01' => 'ain',        '38' => 'isere',        '69' => 'rhone',
        '42' => 'loire',      '73' => 'savoie',       '74' => 'haute-savoie',
        '26' => 'drome',      '07' => 'ardeche',      '03' => 'allier',
        '15' => 'cantal',     '43' => 'haute-loire',  '63' => 'puy-de-dome',
        // Île-de-France
        '75' => 'paris',      '77' => 'seine-et-marne', '78' => 'yvelines',
        '91' => 'essonne',    '92' => 'hauts-de-seine',  '93' => 'seine-saint-denis',
        '94' => 'val-de-marne', '95' => 'val-d-oise',
    ];

    // IP réelle (Cloudflare > proxy > direct)
    $ip = '';
    foreach (['HTTP_CF_CONNECTING_IP','HTTP_X_FORWARDED_FOR','HTTP_X_REAL_IP','REMOTE_ADDR'] as $k) {
        if (!empty($_SERVER[$k])) { $ip = trim(explode(',', $_SERVER[$k])[0]); break; }
    }

    if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
        $_SESSION['geo_dept'] = ''; $_SESSION['geo_city'] = ''; return null;
    }

    // ip-api.com — ajout countryCode pour le fallback France
    $ctx  = stream_context_create(['http' => ['timeout' => 2, 'ignore_errors' => true]]);
    $json = @file_get_contents('http://ip-api.com/json/' . rawurlencode($ip) . '?fields=status,zip,city,countryCode&lang=fr', false, $ctx);

    if ($json === false) { $_SESSION['geo_dept'] = ''; $_SESSION['geo_city'] = ''; return null; }

    $data = json_decode($json, true);
    if (!is_array($data) || ($data['status'] ?? '') !== 'success') {
        $_SESSION['geo_dept'] = ''; $_SESSION['geo_city'] = ''; return null;
    }

    $zip     = (string)($data['zip']         ?? '');
    $city    = (string)($data['city']        ?? '');
    $country = (string)($data['countryCode'] ?? '');
    $code    = substr($zip, 0, 2);
    $result  = $dept_map[$code] ?? '';

    // Fallback : IP française hors zone → Paris (IDF)
    if ($result === '' && $country === 'FR') $result = 'paris';

    $_SESSION['geo_dept'] = $result;
    $_SESSION['geo_city'] = $city;

    return $result !== '' ? $result : null;
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