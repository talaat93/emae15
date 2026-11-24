<?php
declare(strict_types=1);
// Pages ville × métier (24/11/2026) : l'ordre en 10 blocs voulu par le propriétaire, pannes + appareils +
// marques réunis, FAQ limitée à 6 questions, encadré de sécurité, effet orange de l'accueil sur le hero.
function db_fetch_all(string $s, array $p = []): array { return []; }
function db_fetch(string $s, array $p = []): ?array { return null; }
function db_execute(string $s, array $p = []): void {}
$_SERVER['SCRIPT_NAME'] = '/index.php';
$R = dirname(__DIR__);
require $R.'/includes/helpers.php';
require $R.'/includes/city_trade_pages.php';

$pass = 0; $fail = 0;
function check(string $l, mixed $g, mixed $w): void { global $pass, $fail;
    if ($g === $w) { $pass++; echo "  ok   $l\n"; }
    else { $fail++; echo "  FAIL $l\n        obtenu  ".var_export($g, true)."\n        attendu ".var_export($w, true)."\n"; } }

$src = str_replace("\r\n", "\n", (string)file_get_contents($R.'/includes/city_trade_pages.php'));
$f = substr($src, (int)strpos($src, 'function render_city_trade_page('), (int)strpos($src, 'function render_service_offer_block(') - (int)strpos($src, 'function render_city_trade_page('));

echo "\n1. Les 10 blocs, dans l'ordre\n";
$reperes = ['<section class="page-hero hero-glow">', 'render_trust_band($trade)', 'Ce que vous payez', 'Pannes, appareils et marques',
            'et ses environs</em>', 'Intervention en <em>3 étapes', "signs_title']", 'Questions <em>fréquentes', "render_quote_form(\$cards, 'service_'.\$trade); ?>", 'ct-links'];
$pos = array_map(fn($r) => strpos($f, $r), $reperes);
check('tous les blocs présents', array_values(array_filter(array_combine($reperes, $pos), fn($p) => $p === false)), []);
$tri = $pos; sort($tri);
check('dans l\'ordre voulu', $pos, $tri);

echo "\n2. Ce qui a été fusionné ou retiré\n";
check('plus de bandeau défilant des marques', str_contains($f, 'render_service_brands('), false);
check('plus de bandeau défilant des appareils', str_contains($f, 'render_service_equipment('), false);
check('plus de carrousel « Notre offre »', str_contains($f, 'render_service_offer_block('), false);
check('plus de bandeau d\'appel intermédiaire', str_contains($f, 'cta-band'), false);
check('FAQ : 6 questions', str_contains($f, '$faqTop = array_slice($faq, 0, 6);'), true);
check('données structurées = questions affichées', str_contains($f, "'mainEntity' => array_map(static fn(array \$f): array => ['@type' => 'Question', 'name' => \$f[0], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => \$f[1]]], \$faqTop)"), true);
check('lien vers la FAQ complète', str_contains($f, "route_url('faq')"), true);
check('prix : lien vers l\'onglet du métier', str_contains($f, "route_url('tarifs').'#tarifs-'.\$trade"), true);

echo "\n3. Appareils et sécurité\n";
foreach (['electricite', 'plomberie', 'chauffage', 'climatisation'] as $t) {
    $eq = city_trade_equipment($t);
    check("$t : appareils listés", count($eq) >= 6, true);
    check("$t : dessins présents", array_values(array_filter($eq, fn($x) => !is_file($R.'/assets/img/icons/'.$x[0].'.png'))), []);
}
check('chauffage : réflexe gaz', str_contains(city_trade_safety('chauffage')[1], '0 800 47 33 33'), true);
check('électricité : Enedis', str_contains(city_trade_safety('electricite')[1], 'Enedis'), true);
check('climatisation : pas d\'encadré', city_trade_safety('climatisation'), ['', '']);

echo "\n4. Marques : texte, ou logo quand le fichier existe\n";
check('sans fichier : texte', render_brand_list(['Marque Test Inexistante']), '<ul class="ct-brand-list"><li>Marque Test Inexistante</li></ul>');

echo "\n5. Effet orange de l'accueil sur les pages métier et ville\n";
check('page ville', str_contains((string)file_get_contents($R.'/includes/city_pages.php'), 'page-hero hero-glow'), true);
check('page métier', str_contains((string)file_get_contents($R.'/index.php'), 'page-hero hero-glow'), true);
check('lueur orange en CSS', str_contains((string)file_get_contents($R.'/assets/css/style.css'), '.page-hero.hero-glow::after{'), true);

echo "\n".($fail === 0 ? "$pass assertions PASSENT" : "$fail ÉCHECS sur ".($pass + $fail))."\n";
exit($fail === 0 ? 0 : 1);
