<?php
declare(strict_types=1);
// Refonte lisibilité (24/11/2026, choix du propriétaire) : titres en Plus Jakarta Sans hébergée sur le
// site, sections de contenu sur fond clair, en-tête sur une seule ligne.
function db_fetch_all(string $s, array $p = []): array { return []; }
function db_fetch(string $s, array $p = []): ?array { return null; }
function db_execute(string $s, array $p = []): void {}
$_SERVER['SCRIPT_NAME'] = '/index.php';
$R = dirname(__DIR__);
require $R.'/includes/helpers.php';

$pass = 0; $fail = 0;
function check(string $l, mixed $g, mixed $w): void { global $pass, $fail;
    if ($g === $w) { $pass++; echo "  ok   $l\n"; }
    else { $fail++; echo "  FAIL $l\n        obtenu  ".var_export($g, true)."\n        attendu ".var_export($w, true)."\n"; } }
$css   = (string)file_get_contents($R.'/assets/css/style.css');
$fonts = (string)file_get_contents($R.'/assets/css/fonts.css');

echo "\n1. Police des titres hébergée sur le site\n";
check('titres en Plus Jakarta Sans', str_contains($css, "--font-h:'Plus Jakarta Sans'"), true);
preg_match_all("#url\(\.\./fonts/(plus-jakarta-sans-[a-z-]+-\d+\.woff2)\)#", $fonts, $m);
check('6 fichiers déclarés', count(array_unique($m[1])), 6);
check('tous présents sur le serveur', array_values(array_filter($m[1], fn($f) => !is_file($R.'/assets/fonts/'.$f))), []);
check('aucune police chargée chez Google', str_contains($fonts.$css, 'fonts.googleapis'), false);

echo "\n2. Sections de contenu claires, formulaires sombres\n";
check('section blanche', str_contains($css, '.sec-card{background:#FFFFFF;}'), true);
check('section gris doux', str_contains($css, '.sec-navy{background:#F8FAFC;}'), true);
check('formulaires et encarts d\'appel restent sombres', str_contains($css, ':is(.sec-card,.sec-navy) :is(.qs-card,.hero-card,.cta-block,.contact-box,.sp-cta){'), true);
check('alerte gaz lisible sur fond clair', str_contains($css, ':is(.sec-card,.sec-navy) .px-alert{background:#FEF2F2;'), true);
check('aucun « // » devant les titres', (bool)preg_match('#class="(ph-eyebrow|faq-group-title)">//#', implode('', array_map(fn($f) => (string)file_get_contents($R.'/'.$f), ['index.php', 'includes/city_pages.php', 'includes/city_trade_pages.php']))), false);

echo "\n3. En-tête sur une seule ligne\n";
$mob = array_column(array_filter(nav_items(), fn($i) => !empty($i['mob'])), 'url');
check('Accueil et Contact seulement dans le menu du téléphone', $mob, [route_url(''), route_url('contact')]);
check('liens de l\'ordinateur', count(nav_items()) - count($mob) >= 5, true);
$boot = (string)file_get_contents($R.'/includes/bootstrap.php');
check('migration : bandeau urgence et barre du haut coupés une fois', str_contains($boot, '.mig_v15_entete_une_ligne') && str_contains($boot, "['avail_enabled', 'topbar_visible']"), true);
check('bouton d\'appel visible sur ordinateur', str_contains($css, '@media(min-width:640px){ .header-call{display:inline-flex;} }'), true);

echo "\n".($fail === 0 ? "$pass assertions PASSENT" : "$fail ÉCHECS sur ".($pass + $fail))."\n";
exit($fail === 0 ? 0 : 1);
