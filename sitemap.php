<?php
declare(strict_types=1);
require_once __DIR__.'/includes/bootstrap.php';
require_once __DIR__.'/includes/render.php';
header('Content-Type: application/xml; charset=utf-8');

$today = date('Y-m-d');
$entries = [];

$add = function(string $url, string $freq, string $priority, string $lastmod = '') use (&$entries) {
    $entries[] = ['url'=>$url,'freq'=>$freq,'priority'=>$priority,'lastmod'=>$lastmod];
};

// Pages principales
$add(route_url(''),              'daily',   '1.0', $today);
$add(route_url('services'),      'weekly',  '0.9', $today);
foreach (['electricite','plomberie','chauffage','climatisation','vmc','portail'] as $svc) {
    $add(route_url($svc), 'weekly', '0.9', $today);
}
$add(route_url('realisations'),  'weekly',  '0.8', $today);
$add(route_url('avis'),          'weekly',  '0.7', $today);
$add(route_url('faq'),           'monthly', '0.7', $today);
$add(route_url('contact'),       'monthly', '0.8', $today);
$add(route_url('quote'),         'monthly', '0.8', $today);
$add(route_url('zones'),         'monthly', '0.8', $today);

// Pages CMS
foreach (all_pages() as $p) {
    // Les pages métier en base (« Électricité »…) doublonnent les pages ci-dessus.
    if (service_detect_trade($p['slug'].' '.$p['title']) !== null) continue;
    $u = route_url($p['slug']);
    if (!in_array($u, array_column($entries, 'url'))) {
        $add($u, 'monthly', '0.6', $today);
    }
}

// Pages localisation (landing pages) : seulement les zones réellement couvertes.
// Les clés doivent correspondre à celles de la route « landing » d'index.php,
// sinon la page retombe sur un autre département.
$landing = [
    'seine-et-marne' => ['Melun','Meaux','Chelles','Pontault-Combault','Savigny-le-Temple','Fontainebleau'],
    'essonne'        => ['Évry','Corbeil-Essonnes','Massy','Palaiseau','Viry-Châtillon','Longjumeau'],
];
$metiers = ['electricite','plomberie','chauffage','climatisation'];
foreach ($landing as $dept => $villes) {
    foreach ($villes as $ville) {
        foreach ($metiers as $m) {
            $add(route_url('landing').'&dept='.$dept.'&metier='.$m.'&ville='.rawurlencode($ville), 'monthly', '0.6', $today);
        }
    }
}

echo '<?xml version="1.0" encoding="UTF-8"?>';
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
<?php foreach ($entries as $e): ?>
<url>
  <loc><?= e(absolute_url($e['url'])) ?></loc>
  <?php if ($e['lastmod'] !== ''): ?><lastmod><?= e($e['lastmod']) ?></lastmod><?php endif; ?>
  <changefreq><?= e($e['freq']) ?></changefreq>
  <priority><?= e($e['priority']) ?></priority>
</url>
<?php endforeach; ?>
</urlset>