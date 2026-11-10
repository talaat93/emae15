<?php
declare(strict_types=1);
require_once __DIR__.'/includes/bootstrap.php';
require_once __DIR__.'/includes/render.php';
header('Content-Type: application/xml; charset=utf-8');

// Pas de <lastmod> : le site ne garde pas la date de dernière modification de chaque page,
// et une date du jour identique partout n'apprend rien aux moteurs.
$entries = [];
$add = function(string $url, string $freq, string $priority) use (&$entries) {
    foreach ($entries as $e) if ($e['url'] === $url) return;   // pas de doublon
    $entries[] = ['url'=>$url,'freq'=>$freq,'priority'=>$priority,'lastmod'=>''];
};

// Pages principales (adresses de référence : celles qu'annoncent leurs balises canonical)
$add(route_url(''),              'daily',   '1.0');
$add(route_url('services'),      'weekly',  '0.9');
foreach (['electricite','plomberie','chauffage','climatisation','vmc','portail','automatisme'] as $svc) {
    $add(route_url($svc), 'weekly', '0.9');
}
$add(route_url('realisations'),  'weekly',  '0.8');
if (tarifs_enabled()) $add(route_url('tarifs'), 'monthly', '0.7');
// Pages ajoutées en novembre 2026 : seulement quand elles sont activées.
if (pannes_enabled()) {
    $add(route_url('pannes'), 'monthly', '0.7');
    foreach (array_keys(panne_defaults()) as $_pslug) $add(route_url($_pslug), 'monthly', '0.7');
}
if (aides_enabled())      $add(route_url('aides'),       'monthly', '0.6');
if (estimation_enabled()) $add(route_url('estimation'),  'monthly', '0.6');
if (booking_enabled())    $add(route_url('rendez-vous'), 'monthly', '0.6');
if (entretien_enabled())  $add(route_url('entretien'),   'monthly', '0.6');
$add(route_url('avis'),          'weekly',  '0.7');
$add(route_url('faq'),           'monthly', '0.7');
$add(route_url('contact'),       'monthly', '0.8');
$add(route_url('quote'),         'monthly', '0.8');
$add(route_url('zones'),         'monthly', '0.8');
$add(route_url('mentions-legales'), 'yearly', '0.3');

// Pages des départements (/jura/, /doubs/, /cote-d-or/) : les zones listées aux visiteurs.
// Une zone sans département ni ville (ex. /idf/) n'y figure pas, en attendant une décision.
foreach (intervention_zones_listed() as $_z) $add(url_for($_z['slug'].'/'), 'weekly', '0.8');

// Pages par ville (/intervention/…).
$add(url_for('intervention/'), 'monthly', '0.7');
foreach (array_keys(city_pages()) as $_cs) $add(url_for('intervention/'.$_cs), 'monthly', '0.8');

// Pages CMS : les anciennes pages métier (« Électricité »…) redirigent vers la page métier
// (index.php) : elles ne sont pas listées.
foreach (all_pages() as $p) {
    if (service_detect_trade($p['slug'].' '.$p['title']) !== null) continue;
    $add(route_url($p['slug']), 'monthly', '0.6');
}

// Pages localisation (landing pages). Seine-et-Marne et Essonne : gardées tant que l'activité en
// Île-de-France n'est pas tranchée (décision du propriétaire, voir passation) — aucun retrait automatique.
// Les clés doivent correspondre à celles de la route « landing » d'index.php,
// sinon la page retombe sur un autre département.
$landing = [
    // Secteur principal du site global
    'doubs'          => ['Besançon','Pontarlier','Audincourt','Morteau','Baume-les-Dames'],
    'jura'           => ['Dole','Lons-le-Saunier','Saint-Claude','Champagnole','Morez','Poligny'],
    'cote-dor'       => ['Dijon','Beaune','Chenôve','Longvic','Quetigny','Talant'],
    'seine-et-marne' => ['Melun','Meaux','Chelles','Pontault-Combault','Savigny-le-Temple','Fontainebleau'],
    'essonne'        => ['Évry','Corbeil-Essonnes','Massy','Palaiseau','Viry-Châtillon','Longjumeau'],
];
$metiers = ['electricite','plomberie','chauffage','climatisation'];
foreach ($landing as $dept => $villes) {
    foreach ($villes as $ville) {
        foreach ($metiers as $m) {
            $add(route_url('landing').'&dept='.$dept.'&metier='.$m.'&ville='.rawurlencode($ville), 'monthly', '0.6');
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