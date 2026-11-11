<?php
declare(strict_types=1);

/**
 * Pages par ville : /intervention/{ville} (novembre 2026).
 *
 * Une page d'arrivée locale par grande ville du secteur (Côte-d'Or, Jura, Doubs), pour les
 * recherches « électricien Quetigny », « plombier Genlis »… et pour les annonces Google Ads.
 * Chaque page a son propre contenu : communes voisines réelles, chantiers faits près de la
 * ville (s'il y en a), FAQ locale. Aucune promesse de délai ni note sans source (claim_clean,
 * review_summary) ; une seule entreprise dans les données structurées (Service rattaché à
 * l'entité #entreprise, pas de fausse agence par ville).
 *
 * Ajouter une ville : une ligne dans city_pages() (et sa position dans zone_map_data() pour
 * la carte de l'accueil). Rester autour d'une douzaine : des pages presque identiques en grand
 * nombre sont mal vues par Google.
 */
function city_pages(): array
{
    return [
        'dijon'           => ['Dijon', '21000', '21', ['Chenôve', 'Talant', 'Fontaine-lès-Dijon', 'Longvic', 'Quetigny', 'Chevigny-Saint-Sauveur', 'Saint-Apollinaire', 'Marsannay-la-Côte']],
        'quetigny'        => ['Quetigny', '21800', '21', ['Dijon', 'Chevigny-Saint-Sauveur', 'Saint-Apollinaire', 'Sennecey-lès-Dijon', 'Couternon', 'Varois-et-Chaignot', 'Crimolois', 'Bressey-sur-Tille']],
        'genlis'          => ['Genlis', '21110', '21', ['Izeure', 'Longeault-Pluvault', 'Varanges', 'Tart', 'Aiserey', 'Fauverney', 'Collonges-lès-Premières', 'Champdôtre']],
        'beaune'          => ['Beaune', '21200', '21', ['Savigny-lès-Beaune', 'Chorey-les-Beaune', 'Pommard', 'Meursault', 'Ladoix-Serrigny', 'Bligny-lès-Beaune', 'Levernois', 'Nuits-Saint-Georges']],
        'auxonne'         => ['Auxonne', '21130', '21', ['Villers-les-Pots', 'Athée', 'Tillenay', 'Les Maillys', 'Flammerans', 'Poncey-lès-Athée', 'Labergement-lès-Auxonne', 'Champvans']],
        'dole'            => ['Dole', '39100', '39', ['Tavaux', 'Damparis', 'Foucherans', 'Choisey', 'Authume', 'Brevans', 'Crissey', 'Sampans']],
        'lons-le-saunier' => ['Lons-le-Saunier', '39000', '39', ['Montmorot', 'Perrigny', 'Courlaoux', 'Messia-sur-Sorne', 'Conliège', 'Macornay', 'Chilly-le-Vignoble', 'Revigny']],
        'tavaux'          => ['Tavaux', '39500', '39', ['Damparis', 'Abergement-la-Ronce', 'Saint-Aubin', 'Choisey', 'Gevry', 'Molay', 'Aumur', 'Champdivers']],
        'besancon'        => ['Besançon', '25000', '25', ['École-Valentin', 'Châtillon-le-Duc', 'Miserey-Salines', 'Thise', 'Chalezeule', 'Beure', 'Franois', 'Serre-les-Sapins']],
        'saint-vit'       => ['Saint-Vit', '25410', '25', ['Byans-sur-Doubs', 'Osselle-Routelle', 'Roset-Fluans', 'Villars-Saint-Georges', 'Dannemarie-sur-Crète', 'Boussières', 'Torpes', 'Velesmes-Essarts']],
        'pontarlier'      => ['Pontarlier', '25300', '25', ['Arçon', 'Houtaud', 'Dommartin', 'Vuillecin', 'Granges-Narboz', 'Sainte-Colombe', 'La Cluse-et-Mijoux', 'Les Verrières-de-Joux']],
        'montbeliard'     => ['Montbéliard', '25200', '25', ['Audincourt', 'Valentigney', 'Bethoncourt', 'Grand-Charmont', 'Sochaux', 'Exincourt', 'Courcelles-lès-Montbéliard', 'Hérimoncourt']],
    ];
}

/** Données d'une ville, ou null. */
function city_page(string $slug): ?array
{
    $c = city_pages()[$slug] ?? null;
    if ($c === null) return null;
    [$nom, $cp, $dep, $voisines] = $c;
    $d = geo_departements()[$dep] ?? [$dep, '', '', []];
    return ['slug' => $slug, 'nom' => $nom, 'cp' => $cp, 'dep_code' => $dep, 'dep' => $d[0], 'en_dep' => $d[1],
            'voisines' => $voisines, 'a' => geo_a_ville($nom), 'url' => url_for('intervention/'.$slug)];
}

/** Page /intervention/ : la liste des villes, par département. */
function render_city_index(): void
{
    $meta = ['title' => 'Nos villes d\'intervention — Côte-d\'Or, Jura, Doubs | '.company_name(),
             'description' => 'Électricien, plombier et chauffagiste en Côte-d\'Or (21), dans le Jura (39) et le Doubs (25) : '
                              .implode(', ', array_column(city_pages(), 0)).' et leurs environs.',
             'canonical' => url_for('intervention/')];
    render_head($meta); render_header(route_url('zones'));
    $parDep = [];
    foreach (array_keys(city_pages()) as $s) { $c = city_page($s); $parDep[$c['dep'].' ('.$c['dep_code'].')'][] = $c; }
    ?>
<section class="page-hero"><div class="wrap">
  <div class="ph-eyebrow">// Nos villes</div>
  <h1 class="ph-h1">Électricien, plombier, chauffagiste <em>près de chez vous</em></h1>
  <p class="ph-lead">Nous intervenons en Côte-d'Or, dans le Jura et le Doubs, depuis notre base de Dole. Choisissez votre ville.</p>
</div></section>
<section class="sec sec-navy"><div class="wrap">
  <?php foreach ($parDep as $dep => $villes): ?>
  <h2 class="section-title" style="margin-top:1.5rem;"><?= e($dep) ?></h2>
  <div class="px-chips" style="margin-top:1rem;">
    <?php foreach ($villes as $c): ?><a class="px-chip" href="<?= e($c['url']) ?>">📍 <?= e($c['nom']) ?> (<?= e($c['cp']) ?>)</a><?php endforeach; ?>
  </div>
  <?php endforeach; ?>
  <p class="section-lead" style="margin-top:2rem;">Votre commune n'est pas listée ? Nous couvrons aussi les villages alentour : appelez le <a href="<?= e(company_phone_link()) ?>"><?= e(company_phone()) ?></a> pour vérifier.</p>
</div></section>
<?php
    render_footer();
}

/** Page d'une ville. */
function render_city_page(array $c): void
{
    $nom = $c['nom'];
    $meta = [
        'title'       => 'Dépannage Urgence Plomberie & Électricité '.$c['a'].' ('.$c['dep_code'].') | '.company_name(),
        'description' => 'Plombier, électricien, chauffagiste '.$c['a'].' ('.$c['cp'].') et environs, '.$c['dep'].' ('.$c['dep_code'].') : dépannage urgent 7j/7, installation. Prix annoncé avant déplacement, devis gratuit.',
        'canonical'   => $c['url'],
    ];
    render_head($meta);
    // Données structurées : un Service de l'entreprise unique (#entreprise) pour cette ville.
    $origin = rtrim(absolute_url('/'), '/');
    echo '<script type="application/ld+json">'.json_encode([
        '@context' => 'https://schema.org', '@type' => 'Service',
        'name' => 'Dépannage et installation en électricité, plomberie et chauffage '.$c['a'],
        'serviceType' => ['Électricien', 'Plombier', 'Chauffagiste'],
        'provider' => ['@id' => $origin.'/#entreprise'],
        'areaServed' => array_merge([['@type' => 'City', 'name' => $nom, 'postalCode' => $c['cp']]],
                                    array_map(static fn(string $v): array => ['@type' => 'City', 'name' => $v], $c['voisines'])),
        'url' => absolute_url($c['url']),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG).'</script>';
    render_header(route_url('zones'));
    $cards = service_cards_v14();
    $metiers = [
        ['plomberie',   'robinet.png', 'Plomberie',   ['Recherche et réparation de fuite', 'Débouchage de canalisation', 'Chauffe-eau qui fuit ou ne chauffe plus']],
        ['electricite', 'eclair.png',  'Électricité', ['Panne de courant, disjoncteur qui saute', 'Tableau électrique et mise aux normes', 'Court-circuit, prise qui chauffe']],
        ['chauffage',   'flamme.png',  'Chauffage',   ['Dépannage de chaudière gaz ou fioul', 'Entretien annuel', 'Pompe à chaleur et radiateurs']],
    ];
    $faq = [
        ['Intervenez-vous '.$c['a'].' ?', 'Oui, '.$c['a'].' et dans les communes voisines : '.geo_enumerer(array_slice($c['voisines'], 0, 6)).'. Votre adresse n\'est pas citée ? Appelez-nous pour vérifier.'],
        ['Combien de temps pour intervenir '.$c['a'].' ?', intervention_terms_text().' En cas d\'urgence, appelez le '.company_phone().'.'],
        ['Combien coûte un dépannage ?', tarifs_enabled() ? 'Le déplacement, le diagnostic et la main-d\'œuvre sont affichés sur notre page Tarifs. Le prix vous est annoncé avant tout déplacement ; les travaux font l\'objet d\'un devis gratuit.' : 'Le prix vous est annoncé avant tout déplacement ; les travaux font l\'objet d\'un devis gratuit.'],
        ['Vos devis sont-ils payants ?', 'Non, le devis est gratuit et sans engagement. Rien n\'est fait sans votre accord.'],
    ];
    $reals = array_values(array_filter(realisations_local(3, $nom, $c['voisines']), static fn(array $r): bool => $r['local'] > 0));
    ?>
<section class="page-hero"><div class="wrap svc-hero-grid">
  <div class="svc-hero-content">
    <div class="ph-eyebrow">// <?= e($c['dep']) ?> (<?= e($c['dep_code']) ?>) — urgences 7j/7</div>
    <h1 class="ph-h1">Dépannage urgence plomberie, électricité, chauffage <em><?= e($c['a']) ?> (<?= e($c['dep_code']) ?>)</em></h1>
    <p class="ph-lead">Panne de courant, fuite d'eau, chaudière en panne : nous intervenons <?= e($c['a']) ?> (<?= e($c['cp']) ?>) et dans les communes voisines. Prix annoncé avant déplacement, devis gratuit.</p>
    <div class="btn-row" style="margin-top:1.5rem;">
      <a class="btn btn-p btn-lg" href="<?= e(company_phone_link()) ?>">📞 <?= e(company_phone()) ?></a>
      <a class="btn btn-outline btn-lg" href="#etre-rappele">Être rappelé</a>
    </div>
  </div>
  <div class="hero-form" id="etre-rappele">
    <div class="hero-card">
      <div class="hero-card-tag">Rappel gratuit</div>
      <h2>Être rappelé</h2>
      <?php render_quote_form($cards, 'ville_'.$c['slug'], 'rappel'); ?>
    </div>
  </div>
</div></section>
<?= render_trust_band() ?>

<section class="sec sec-card"><div class="wrap">
  <div class="svc-label">Nos métiers <?= e($c['a']) ?></div>
  <h2 class="section-title">Dépannage et installation</h2>
  <div class="city-grid">
    <?php foreach ($metiers as [$route, $icon, $label, $points]): ?>
    <a class="city-card" href="<?= e(url_for('intervention/'.$c['slug'].'/'.city_trade_slug($route))) ?>">
      <img src="<?= e(asset_url('assets/img/icons/'.$icon)) ?>" alt="" width="44" height="44" loading="lazy">
      <h3><?= e($label) ?> <?= e($c['a']) ?></h3>
      <ul><?php foreach ($points as $p): ?><li><?= e($p) ?></li><?php endforeach; ?></ul>
      <span class="city-card-more"><?= e(city_trades()[city_trade_slug($route)][1]) ?> <?= e($c['a']) ?> →</span>
    </a>
    <?php endforeach; ?>
  </div>
  <p class="section-lead" style="margin-top:1.25rem;">Aussi : <a href="<?= e(url_for('intervention/'.$c['slug'].'/climatisation')) ?>">climatisation <?= e($c['a']) ?></a>, <a href="<?= e(route_url('vmc')) ?>">VMC</a>, <a href="<?= e(route_url('portail')) ?>">portail et visiophone</a>, <a href="<?= e(route_url('automatisme')) ?>">volets roulants et électroménager</a>.</p>
</div></section>

<section class="sec sec-navy"><div class="wrap" style="max-width:960px;">
  <div class="prox-grid"><div>
  <div class="svc-label">Proximité</div>
  <h2 class="section-title"><?= e($nom) ?> <em>et ses environs</em></h2>
  <p class="section-lead">Nos techniciens partent de notre base de Dole et interviennent <?= e($c['a']) ?> comme dans les communes voisines. <?= e(intervention_terms_text()) ?></p>
  <div class="zone-chips" style="margin-top:1rem;">
    <span class="zone-chip" style="font-weight:700;">📍 <?= e($nom) ?> (<?= e($c['cp']) ?>)</span>
    <?php foreach ($c['voisines'] as $v): ?><span class="zone-chip"><?= e($v) ?></span><?php endforeach; ?>
  </div>
  </div>
  <?= render_zone_map(['depts' => [$c['dep_code']], 'focus' => $c['slug']]) ?>
  </div>
</div></section>

<?php if ($reals): ?>
<section class="sec sec-card"><div class="wrap">
  <div class="svc-label">Nos réalisations</div>
  <h2 class="section-title">Chantiers près de <em><?= e($nom) ?></em></h2>
  <div class="reals-grid" style="margin-top:1.75rem;">
    <?php foreach ($reals as $r): ?>
    <div class="real-card"><?php render_realisation_media($r); ?>
      <div class="real-body">
        <?php if (trim((string)($r['service_type'] ?? '')) !== ''): ?><div class="real-svc"><?= e($r['service_type']) ?></div><?php endif; ?>
        <h3 class="real-h"><?= e($r['title']) ?></h3>
        <?= realisation_meta($r) ?>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</div></section>
<?php endif; ?>

<section class="cta-band"><div class="wrap cta-band-in">
  <div>
    <h2 class="cta-band-h">Une panne <?= e($c['a']) ?> ?</h2>
    <p class="cta-band-p">Appelez-nous ou demandez à être rappelé : nous faisons le point avec vous et vous annonçons le prix avant tout déplacement.</p>
  </div>
  <div class="cta-band-acts">
    <a class="btn btn-p btn-lg" href="<?= e(company_phone_link()) ?>">📞 <?= e(company_phone()) ?></a>
    <a class="btn btn-outline btn-lg" href="<?= e(route_url('quote')) ?>">Demander un devis</a>
  </div>
</div></section>

<section class="sec sec-navy"><div class="wrap" style="max-width:860px;">
  <div class="svc-label">FAQ</div>
  <h2 class="section-title">Questions <em>fréquentes</em></h2>
  <div class="sfaq-list" style="margin-top:1.5rem;">
    <?php foreach ($faq as [$q, $a]): ?><details class="sfaq-item"><summary><?= e($q) ?></summary><p><?= e($a) ?></p></details><?php endforeach; ?>
  </div>
  <script type="application/ld+json"><?= json_encode(['@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => array_map(static fn(array $f): array => ['@type' => 'Question', 'name' => $f[0], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f[1]]], $faq)], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?></script>
  <p style="margin-top:1.5rem;font-size:.9rem;color:var(--t2);">Autres villes : <?php $o = []; foreach (array_keys(city_pages()) as $s) if ($s !== $c['slug']) { $x = city_page($s); $o[] = '<a href="'.e($x['url']).'">'.e($x['nom']).'</a>'; } echo implode(' · ', $o); ?></p>
</div></section>
<?php
    render_footer();
}
