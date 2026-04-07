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
    if (str_ends_with($s, '/admin')) $s = substr($s, 0, -6);
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
function site_logo_path(): string { return setting('site_logo', 'storage/uploads/logos/logo-emae-default.svg'); }
function site_logo_url(): string { return asset_url(site_logo_path()); }
function site_logo_width(): string { return css_value(setting('site_logo_width', '180'), '180px'); }
function site_logo_height(): string { return css_value(setting('site_logo_height', 'auto'), 'auto'); }
function site_logo_position(): string { $p = setting('site_logo_position', 'left'); return in_array($p, ['left','center','right']) ? $p : 'left'; }

/* ═══════════════════════════════════════════════════
   THEME & STYLES
═══════════════════════════════════════════════════ */
function theme_css_variables(): string
{
    $vars = [
        '--primary'         => setting('color_primary',        '#ee7d1a'),
        '--primary-dark'    => setting('color_primary_hover',  '#c95f0b'),
        '--navy'            => setting('color_secondary',      '#0b1641'),
        '--navy-2'          => setting('color_secondary_2',    '#1a2e6b'),
        '--bg'              => setting('color_site_bg',        '#f5f7fc'),
        '--text'            => setting('color_text',           '#1b2440'),
        '--muted'           => setting('color_text_muted',     '#64708f'),
        '--surface'         => setting('color_surface',        '#ffffff'),
        '--font-heading'    => '"'.setting('font_heading','Montserrat').'", Arial, sans-serif',
        '--font-body'       => '"'.setting('font_body','Inter').'", Arial, sans-serif',
        '--hero-from'       => setting('hero_bg_from',         '#05112e'),
        '--hero-to'         => setting('hero_bg_to',           '#1a2f71'),
        '--hero-glow-1'     => setting('hero_glow_left',       '#ee7d1a'),
        '--hero-glow-2'     => setting('hero_glow_right',      '#3b5bff'),
    ];
    $css = ':root{';
    foreach ($vars as $k => $v) $css .= $k . ':' . $v . ';';
    $css .= '}';
    return '<style>' . $css . '</style>';
}

function schema_local_business(): string
{
    $data = [
        '@context' => 'https://schema.org',
        '@type'    => 'LocalBusiness',
        'name'     => company_name(),
        'telephone'=> company_phone(),
        'email'    => company_email(),
        'description' => setting('company_description', 'Entreprise multitechnique — dépannage, installation, entretien.'),
        'areaServed'  => array_map('trim', explode(',', company_regions())),
        'openingHours'=> company_hours(),
        'url'         => site_base_url() !== '' ? site_base_url() : route_url(''),
    ];
    if (company_siret() !== '') $data['identifier'] = company_siret();
    $rv = setting('schema_rating_value', '');
    $rc = setting('schema_review_count', '');
    if ($rv !== '' && $rc !== '') $data['aggregateRating'] = ['@type'=>'AggregateRating','ratingValue'=>(float)$rv,'reviewCount'=>(int)$rc];
    return json_encode($data, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
}

/* ═══════════════════════════════════════════════════
   NAV & SEO
═══════════════════════════════════════════════════ */
function nav_items(): array
{
    return [
        ['label'=>setting('nav_home','Accueil'),      'url'=>route_url('')],
        ['label'=>setting('nav_services','Services'), 'url'=>route_url('services')],
        ['label'=>setting('nav_realisations','Réalisations'), 'url'=>route_url('realisations')],
        ['label'=>setting('nav_faq','FAQ'),           'url'=>route_url('faq')],
        ['label'=>setting('nav_contact','Contact'),   'url'=>route_url('contact')],
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
function service_cards_settings(): array
{
    $default = [
        ['title'=>'Électricité',               'icon'=>'⚡','image'=>'storage/uploads/services/electricite.svg',   'link'=>'electricien-meaux',      'badge'=>'Urgence 24h/7j'],
        ['title'=>'Plomberie',                 'icon'=>'🔧','image'=>'storage/uploads/services/plomberie.svg',    'link'=>'plombier-meaux',          'badge'=>'Fuite & dépannage'],
        ['title'=>'Chauffage & Climatisation', 'icon'=>'❄️','image'=>'storage/uploads/services/cvc.svg',          'link'=>'climatisation-meaux',     'badge'=>'CVC & PAC'],
        ['title'=>'Maintenance',               'icon'=>'🛠️','image'=>'storage/uploads/services/maintenance.svg', 'link'=>'depannage-paris',         'badge'=>'Contrat entretien'],
    ];
    $cards = get_json_setting('home_service_cards', $default);
    if (!$cards) return $default;
    $normalized = [];
    foreach ($default as $i => $fallback) {
        $c = is_array($cards[$i] ?? null) ? $cards[$i] : [];
        $image = trim((string)($c['image'] ?? ''));
        if ($image === '' || !public_asset_exists($image)) $image = $fallback['image'];
        $normalized[] = [
            'title' => trim((string)($c['title'] ?? '')) ?: $fallback['title'],
            'icon'  => trim((string)($c['icon']  ?? '')) ?: $fallback['icon'],
            'image' => $image,
            'link'  => trim((string)($c['link']  ?? '')) ?: $fallback['link'],
            'badge' => trim((string)($c['badge'] ?? '')) ?: $fallback['badge'],
        ];
    }
    return $normalized;
}

function home_cards(): array { return service_cards_settings(); }

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

function all_quotes(): array
{
    try { return db_fetch_all('SELECT * FROM quotes ORDER BY created_at DESC'); }
    catch (Throwable $e) { return []; }
}
