<?php
declare(strict_types=1);
// Photos de terrain des pages ville × métier (23-24/11/2026) : trois emplacements sous la ligne de
// flottaison, photos envoyées depuis l'admin (une commune, une par métier prioritaire), rien sans
// photo, dimensions + ratio fixes, chargement différé, alt métier + ville.
$DB = ['settings' => []];
function db_fetch_all(string $s, array $p = []): array { global $DB;
    if (str_contains($s, 'FROM settings')) return array_map(fn($k, $v) => ['setting_key' => $k, 'setting_value' => $v], array_keys($DB['settings']), $DB['settings']);
    return []; }
function db_fetch(string $s, array $p = []): ?array { return null; }
function db_execute(string $s, array $p = []): void {}
function db_last_id(): int { return 1; }
define('EMAE_TESTS_SANS_RESEAU', true);
$_SERVER['SCRIPT_NAME'] = '/index.php';
$R = dirname(__DIR__);
require $R.'/includes/helpers.php';
require $R.'/includes/workflow.php';
require $R.'/includes/growth.php';
require $R.'/includes/content_pages.php';
require $R.'/includes/city_trade_pages.php';
require $R.'/includes/admin_team.php';

$pass = 0; $fail = 0;
function check(string $l, mixed $g, mixed $w): void { global $pass, $fail;
    if ($g === $w) { $pass++; echo "  ok   $l\n"; }
    else { $fail++; echo "  FAIL $l\n        obtenu  ".var_export($g, true)."\n        attendu ".var_export($w, true)."\n"; } }
function set(string $k, string $v): void { global $DB; $DB['settings'][$k] = $v; settings_cache(true); }

// Fichiers de test dans storage/uploads/terrain (là où l'admin les range), retirés à la fin.
$dir = $R.'/storage/uploads/terrain';
@mkdir($dir, 0775, true);
$tag = 'test-'.getmypid();
// En-tête PNG minimal (signature + IHDR) : getimagesize n'a besoin que de lui, pas de GD.
$png = static function (string $f, int $w, int $h): void {
    $ihdr = 'IHDR'.pack('NNCCCCC', $w, $h, 8, 2, 0, 0, 0);
    file_put_contents($f, chr(0x89).'PNG'.chr(13).chr(10).chr(26).chr(10).pack('N', 13).$ihdr.pack('N', crc32($ihdr)));
};
$c = ['slug' => 'beaune', 'nom' => 'Beaune', 'a' => 'à Beaune', 'dep_code' => '21'];

echo "\n1. Sans photo : aucun bloc\n";
foreach (array_keys(city_trade_photo_slots()) as $s) check("$s vide", render_city_trade_photo($s, $c, 'electricite', 'électricien'), '');
set('ct_photo_vehicule', 'storage/uploads/terrain/disparue.jpg');
check('fichier absent du serveur : aucun bloc', render_city_trade_photo('vehicule', $c, 'electricite', 'électricien'), '');
set('ct_photo_vehicule', 'storage/../../etc/passwd');
check('chemin hors du site refusé', city_trade_photo('vehicule', 'electricite'), null);

echo "\n2. Photo du métier prioritaire sur « Toutes les pages »\n";
$png("$dir/$tag-a.png", 1200, 750);
$png("$dir/$tag-b.png", 640, 400);
set('ct_photo_equipe', "storage/uploads/terrain/$tag-a.png");
check('toutes les pages', city_trade_photo('equipe', 'plomberie')['key'] ?? null, 'ct_photo_equipe');
set('ct_photo_equipe_electricite', "storage/uploads/terrain/$tag-b.png");
check('électricité : sa propre photo', city_trade_photo('equipe', 'electricite')['key'] ?? null, 'ct_photo_equipe_electricite');
check('plomberie : toujours la photo commune', city_trade_photo('equipe', 'plomberie')['key'] ?? null, 'ct_photo_equipe');

echo "\n3. Balisage : pas de décalage, chargement différé, alt métier + ville\n";
$h = render_city_trade_photo('equipe', $c, 'electricite', 'électricien');
check('figure + figcaption', str_starts_with($h, '<figure class="ct-photo">') && str_contains($h, '<figcaption>'), true);
check('largeur et hauteur réelles', str_contains($h, 'width="640" height="400"'), true);
check('lazy + async', str_contains($h, 'loading="lazy"') && str_contains($h, 'decoding="async"'), true);
check('alt métier + ville', str_contains($h, 'alt="L&#039;équipe EMAE, électricien à Beaune (21)"'), true);
array_map('unlink', glob("$dir/$tag-*"));

echo "\n4. Admin : écran au menu, envoi par emplacement et par métier\n";
check('menu Contenus', isset(admin_screens()['Contenus']['terrain_photos.php']), true);
$adm = (string)file_get_contents($R.'/admin/terrain_photos.php');
check('envoi via upload_image_field (types vérifiés)', str_contains($adm, "upload_image_field('up_'.\$k, 'terrain')"), true);
check('protégé CSRF', str_contains($adm, 'verify_csrf()'), true);

echo "\n5. Gabarit : emplacements sous la ligne de flottaison, aucune image dans l'en-tête\n";
$src = (string)file_get_contents($R.'/includes/city_trade_pages.php');
$hero = substr($src, (int)strpos($src, '<section class="page-hero hero-glow">'), (int)strpos($src, 'render_trust_band($trade)') - (int)strpos($src, '<section class="page-hero hero-glow">'));
check('aucune image dans l\'en-tête', str_contains($hero, '<img') || str_contains($hero, 'ct-photo') || str_contains($hero, '$photo'), false);
$pos = fn(string $n) => (int)strpos($src, $n);
check('A dans « {Ville} et ses environs » (ancrage local)', $pos('Proximité</div>') < $pos("render_city_trade_photo('vehicule'") && $pos("render_city_trade_photo('vehicule'") < $pos('Intervention en <em>3 étapes'), true);
check('B dans « Intervention en 3 étapes »', $pos('Intervention en <em>3 étapes') < $pos("render_city_trade_photo('intervention'") && $pos("render_city_trade_photo('intervention'") < $pos('8. Signes'), true);
check('C avant le formulaire final', $pos("render_city_trade_photo('equipe'") < $pos("render_quote_form(\$cards, 'service_'.\$trade); ?>"), true);
$css = (string)file_get_contents($R.'/assets/css/style.css');
check('ratio fixe 16/10 en CSS', str_contains($css, '.ct-photo img{display:block;width:100%;height:auto;aspect-ratio:16/10;'), true);

echo "\n".($fail === 0 ? "$pass assertions PASSENT" : "$fail ÉCHECS sur ".($pass + $fail))."\n";
exit($fail === 0 ? 0 : 1);
