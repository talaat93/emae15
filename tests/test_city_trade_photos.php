<?php
declare(strict_types=1);
// Photos de terrain des pages ville × métier (23/11/2026) : trois emplacements sous la ligne de
// flottaison, rien sans fichier, dimensions + ratio fixes, chargement différé, alt métier + ville.
$DB = ['settings' => []];
function db_fetch_all(string $s, array $p = []): array { return []; }
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

$pass = 0; $fail = 0;
function check(string $l, mixed $g, mixed $w): void { global $pass, $fail;
    if ($g === $w) { $pass++; echo "  ok   $l\n"; }
    else { $fail++; echo "  FAIL $l\n        obtenu  ".var_export($g, true)."\n        attendu ".var_export($w, true)."\n"; } }

$dir = sys_get_temp_dir().'/emae_terrain_'.getmypid();
@mkdir($dir);
// En-tête PNG minimal (signature + IHDR) : getimagesize n'a besoin que de lui, pas de GD.
$png = static function (string $f, int $w, int $h): void {
    $ihdr = 'IHDR'.pack('NNCCCCC', $w, $h, 8, 2, 0, 0, 0);
    file_put_contents($f, chr(0x89).'PNG'.chr(13).chr(10).chr(26).chr(10).pack('N', 13).$ihdr.pack('N', crc32($ihdr)));
};
$c = ['slug' => 'beaune', 'nom' => 'Beaune', 'a' => 'à Beaune', 'dep_code' => '21'];

echo "\n1. Sans fichier : aucun bloc\n";
foreach (['vehicule', 'intervention', 'equipe'] as $s) check("$s vide", render_city_trade_photo($s, $c, 'electricite', 'électricien', $dir), '');

echo "\n2. Du plus précis au plus général\n";
{
    $png($dir.'/equipe.jpg', 1200, 750);
    check('photo commune', city_trade_photo('equipe', 'electricite', 'beaune', $dir)['w'] ?? null, 1200);
    $png($dir.'/equipe-electricite.jpg', 800, 500);
    check('photo du métier prioritaire', city_trade_photo('equipe', 'electricite', 'beaune', $dir)['w'] ?? null, 800);
    $png($dir.'/equipe-electricite-beaune.jpg', 640, 400);
    check('photo de la ville prioritaire', city_trade_photo('equipe', 'electricite', 'beaune', $dir)['w'] ?? null, 640);
    check('autre ville : photo du métier', city_trade_photo('equipe', 'electricite', 'dole', $dir)['w'] ?? null, 800);
    file_put_contents($dir.'/vehicule.webp', 'pas une image');
    check('fichier illisible : ignoré', city_trade_photo('vehicule', 'electricite', 'beaune', $dir), null);

    echo "\n3. Balisage : pas de décalage, chargement différé, alt métier + ville\n";
    $h = render_city_trade_photo('equipe', $c, 'electricite', 'électricien', $dir);
    check('figure + figcaption', str_starts_with($h, '<figure class="ct-photo"><img ') && str_contains($h, '<figcaption>'), true);
    check('largeur et hauteur réelles', str_contains($h, 'width="640" height="400"'), true);
    check('lazy + async', str_contains($h, 'loading="lazy" decoding="async"'), true);
    check('alt métier + ville', str_contains($h, 'alt="L&#039;équipe EMAE, électricien à Beaune (21)"') || str_contains($h, 'alt="L\'équipe EMAE, électricien à Beaune (21)"'), true);
    array_map('unlink', glob($dir.'/*'));
}
@rmdir($dir);

echo "\n4. Gabarit : emplacements sous la ligne de flottaison, aucune image dans l'en-tête\n";
$src = (string)file_get_contents($R.'/includes/city_trade_pages.php');
$hero = substr($src, (int)strpos($src, '<section class="page-hero">'), (int)strpos($src, 'render_trust_band($trade)') - (int)strpos($src, '<section class="page-hero">'));
check('aucune image dans l\'en-tête', str_contains($hero, '<img') || str_contains($hero, 'ct-photo') || str_contains($hero, '$photo'), false);
$pos = fn(string $n) => (int)strpos($src, $n);
check('A après « Pourquoi EMAE »', $pos('Pourquoi EMAE') < $pos('$photoA /* A') , true);
check('B dans « Intervention en 3 étapes »', $pos('Intervention en <em>3 étapes') < $pos("render_city_trade_photo('intervention'") && $pos("render_city_trade_photo('intervention'") < $pos('<section class="cta-band">'), true);
check('C avant le formulaire final', $pos("render_city_trade_photo('equipe'") < $pos("render_quote_form(\$cards, 'service_'.\$trade); ?>"), true);
$css = (string)file_get_contents($R.'/assets/css/style.css');
check('ratio fixe 16/10 en CSS', str_contains($css, '.ct-photo img{display:block;width:100%;height:auto;aspect-ratio:16/10;'), true);

echo "\n".($fail === 0 ? "$pass assertions PASSENT" : "$fail ÉCHECS sur ".($pass + $fail))."\n";
exit($fail === 0 ? 0 : 1);
