<?php
declare(strict_types=1);

/* ═══════════════════════════════════════════════════
   PAGES MÉTIER × VILLE : /intervention/{ville}/{métier}
   Pour la recherche « électricien Dole » : la page reprend le métier ET la ville.
   Destination des groupes d'annonces Google Ads (une page par groupe). Une page n'est
   indexée par Google que si elle a un contenu propre (chantiers du métier dans le
   secteur) ; sinon « noindex » : elle sert aux annonces sans faire doublon.
═══════════════════════════════════════════════════ */

/** Métiers déclinés par ville : slug d'adresse => [métier du site, intitulé]. */
function city_trades(): array
{
    return [
        'electricien'   => ['electricite',   'Électricien'],
        'plombier'      => ['plomberie',     'Plombier'],
        'chauffagiste'  => ['chauffage',     'Chauffagiste'],
        'climatisation' => ['climatisation', 'Climatisation'],
    ];
}

/** Slug d'adresse d'un métier du site (« electricite » → « electricien »), ou ''. */
function city_trade_slug(string $trade): string
{
    foreach (city_trades() as $slug => [$t]) if ($t === $trade) return $slug;
    return '';
}

/** Ville dont le nom correspond (« Lons-Le-Saunier », « Besançon »…), ou null. */
function city_page_by_name(string $nom): ?array
{
    return city_page(trim((string)preg_replace('/[^a-z0-9]+/', '-', text_ascii_lower($nom)), '-'));
}

/** Chantiers du métier faits dans la ville ou une commune voisine. */
function city_trade_reals(array $c, string $trade, int $limit = 3): array
{
    $rows = array_filter(realisations_local(200, $c['nom'], $c['voisines']), static fn(array $r): bool =>
        $r['local'] > 0 && in_array($trade, realisation_trades((string)($r['service_type'] ?? '')), true));
    return array_slice(array_values($rows), 0, $limit);
}

/** Intitulé de la page : « Électricien à Dole », « Climatisation à Dole ». */
function city_trade_title(array $c, string $tslug): string
{
    return city_trades()[$tslug][1].' '.$c['a'];
}

function render_city_trade_page(array $c, string $tslug): void
{
    [$trade, $pro] = city_trades()[$tslug];
    $clim  = $trade === 'climatisation';
    $metier = $clim ? 'technicien climatisation' : mb_strtolower($pro, 'UTF-8');
    $tpl   = service_template($trade);
    $reals = city_trade_reals($c, $trade);
    $nom   = $c['nom'];
    $titre = city_trade_title($c, $tslug);
    $meta = [
        'title'       => $titre.' ('.$c['cp'].') — Dépannage 7j/7 | '.company_name(),
        'description' => ($clim ? 'Dépannage, entretien et installation de climatisation' : $pro.' pour vos dépannages et travaux')
                         .' '.$c['a'].' et ses environs ('.$c['dep'].'). Prix annoncé avant déplacement, devis gratuit, urgences 7j/7.',
        'canonical'   => url_for('intervention/'.$c['slug'].'/'.$tslug),
    ];
    if (!$reals) $meta['robots'] = 'noindex, follow';
    render_head($meta);
    $origin = rtrim(absolute_url('/'), '/');
    echo '<script type="application/ld+json">'.json_encode([
        '@context' => 'https://schema.org', '@type' => 'Service',
        'name' => $titre, 'serviceType' => $tpl['label'],
        'provider' => ['@id' => $origin.'/#entreprise'],
        'areaServed' => array_merge([['@type' => 'City', 'name' => $nom, 'postalCode' => $c['cp']]],
                                    array_map(static fn(string $v): array => ['@type' => 'City', 'name' => $v], $c['voisines'])),
        'url' => absolute_url($meta['canonical']),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG).'</script>';
    render_header(route_url($trade));
    $cards  = service_cards_v14();
    $signs  = array_values(array_filter(array_map(static fn($s) => trim(is_array($s) ? (string)($s['v'] ?? reset($s)) : (string)$s), (array)$tpl['signs']), 'strlen'));
    $offres = array_slice(service_offer_cards((array)$tpl['offer_items']), 0, 6);
    $faq = [['Intervenez-vous '.$c['a'].' ?', 'Oui, '.$c['a'].' et dans les communes voisines : '.geo_enumerer(array_slice($c['voisines'], 0, 6)).'. Votre adresse n\'est pas citée ? Appelez-nous pour vérifier.']];
    foreach ($tpl['faq'] as [$q, $a]) $faq[] = [(string)$q, faq_answer_fix((string)$q, (string)$a)];
    $steps = [1 => ['Diagnostic sur place', 'Le technicien recherche la cause de la panne et vous l\'explique simplement.'],
              2 => ['Devis clair avant travaux', 'Vous connaissez le prix avant toute réparation et vous décidez : rien n\'est fait sans votre accord.'],
              3 => ['Réparation aux normes', 'Réparation ou installation dans les règles de l\'art, puis facture détaillée en fin d\'intervention.']];
    ?>
<section class="page-hero"><div class="wrap svc-hero-grid">
  <div class="svc-hero-content">
    <div class="ph-eyebrow">// <?= e($tpl['label']) ?> — <?= e($nom) ?> (<?= e($c['cp']) ?>)</div>
    <h1 class="ph-h1"><?= e($pro) ?> <em><?= e($c['a']) ?></em></h1>
    <p class="ph-lead"><?= e(claim_clean(geo_replace((string)$tpl['desc']), $tpl['label'].' : dépannage, entretien et installation.')) ?> Nous intervenons <?= e($c['a']) ?> et dans les communes voisines ; le prix vous est annoncé avant tout déplacement.</p>
    <div class="btn-row" style="margin-top:1.5rem;">
      <a class="btn btn-p btn-lg" href="<?= e(company_phone_link()) ?>">📞 <?= e(company_phone()) ?></a>
      <a class="btn btn-outline btn-lg" href="#etre-rappele">Être rappelé</a>
    </div>
  </div>
  <div class="hero-form" id="etre-rappele">
    <div class="hero-card">
      <div class="hero-card-tag">Rappel gratuit</div>
      <h2>Être rappelé</h2>
      <?= render_trust_badges($trade) ?>
      <?php render_quote_form($cards, 'service_'.$trade, 'rappel'); ?>
    </div>
  </div>
</div></section>

<?php if ($signs): ?>
<section class="sec sec-navy"><div class="wrap" style="max-width:960px;">
  <div class="svc-label"><?= e(setting('svc_signs_label', 'Signes d\'alerte')) ?></div>
  <h2 class="section-title"><?= e($tpl['signs_title']) ?></h2>
  <ul class="sign-list"><?php foreach ($signs as $s): ?><li><?= e($s) ?></li><?php endforeach; ?></ul>
</div></section>
<?php endif; ?>

<?php if ($offres): ?>
<section class="sec sec-card"><div class="wrap">
  <div class="svc-label">Nos interventions <?= e($c['a']) ?></div>
  <h2 class="section-title"><?= e($tpl['offer_title']) ?></h2>
  <div class="city-grid">
    <?php foreach ($offres as $o): ?>
    <div class="city-card">
      <img src="<?= e(service_icon_url((string)$o['icon'])) ?>" alt="" width="40" height="40" loading="lazy">
      <h3><?= e($o['title']) ?></h3>
      <?php if (trim((string)$o['text']) !== ''): ?><p style="margin:0;color:var(--t2);font-size:.9rem;"><?= e($o['text']) ?></p><?php endif; ?>
    </div>
    <?php endforeach; ?>
  </div>
  <p class="section-lead" style="margin-top:1.25rem;"><a href="<?= e(route_url($trade)) ?>">Toutes nos prestations en <?= e(mb_strtolower($tpl['label'], 'UTF-8')) ?> →</a><?php if (tarifs_enabled()): ?> · <a href="<?= e(route_url('tarifs')) ?>">Nos tarifs (déplacement, main-d'œuvre) →</a><?php endif; ?></p>
</div></section>
<?php endif; ?>

<section class="sec sec-navy"><div class="wrap" style="max-width:960px;">
  <div class="svc-label">Proximité</div>
  <h2 class="section-title"><?= e($nom) ?> <em>et ses environs</em></h2>
  <p class="section-lead">Notre <?= e($metier) ?> part de notre base de Dole et intervient <?= e($c['a']) ?> comme dans les communes voisines. <?= e(intervention_terms_text()) ?></p>
  <div class="zone-chips" style="margin-top:1rem;">
    <span class="zone-chip" style="font-weight:700;">📍 <?= e($nom) ?> (<?= e($c['cp']) ?>)</span>
    <?php foreach ($c['voisines'] as $v): ?><span class="zone-chip"><?= e($v) ?></span><?php endforeach; ?>
  </div>
</div></section>

<?php if ($reals): ?>
<section class="sec sec-card"><div class="wrap">
  <div class="svc-label">Nos réalisations</div>
  <h2 class="section-title"><?= e($tpl['label']) ?> : chantiers près de <em><?= e($nom) ?></em></h2>
  <div class="reals-grid" style="margin-top:1.75rem;">
    <?php foreach ($reals as $r): ?>
    <div class="real-card"><?php render_realisation_media($r); ?>
      <div class="real-body"><h3 class="real-h"><?= e($r['title']) ?></h3><?php if (trim((string)($r['city'] ?? '')) !== ''): ?><div class="real-city">📍 <?= e($r['city']) ?></div><?php endif; ?></div>
    </div>
    <?php endforeach; ?>
  </div>
</div></section>
<?php endif; ?>

<section class="sec sec-card"><div class="wrap">
  <div class="svc-label"><?= e(setting('svc_process_label', 'Notre méthode')) ?></div>
  <h2 class="section-title">Intervention en <em>3 étapes</em></h2>
  <div class="steps-grid" style="margin-top:1.5rem;">
    <?php foreach ($steps as $i => [$st, $sx]): ?>
    <div class="step-card"><div class="step-num"><?= $i ?></div><div><h3 class="step-h"><?= e(setting('svc_method_'.$i.'_title', $st)) ?></h3><p class="step-p"><?= e(claim_clean(setting('svc_method_'.$i.'_text', $sx))) ?></p></div></div>
    <?php endforeach; ?>
  </div>
</div></section>

<section class="cta-band"><div class="wrap cta-band-in">
  <div>
    <h2 class="cta-band-h">Besoin d'un <?= e($metier) ?> <?= e($c['a']) ?> ?</h2>
    <p class="cta-band-p">Appelez-nous ou demandez à être rappelé : nous faisons le point avec vous et vous annonçons le prix avant tout déplacement.</p>
  </div>
  <div class="cta-band-acts">
    <a class="btn btn-p btn-lg" href="<?= e(company_phone_link()) ?>">📞 <?= e(company_phone()) ?></a>
    <a class="btn btn-outline btn-lg" href="#etre-rappele">Être rappelé gratuitement</a>
  </div>
</div></section>

<section class="sec sec-navy"><div class="wrap" style="max-width:860px;">
  <div class="svc-label">FAQ</div>
  <h2 class="section-title">Questions <em>fréquentes</em></h2>
  <div class="sfaq-list" style="margin-top:1.5rem;">
    <?php foreach ($faq as [$q, $a]): ?><details class="sfaq-item"><summary><?= e($q) ?></summary><p><?= e($a) ?></p></details><?php endforeach; ?>
  </div>
  <script type="application/ld+json"><?= json_encode(['@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => array_map(static fn(array $f): array => ['@type' => 'Question', 'name' => $f[0], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f[1]]], $faq)], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?></script>
  <p style="margin-top:1.5rem;font-size:.9rem;color:var(--t2);line-height:1.8;">
    Aussi <?= e($c['a']) ?> : <?php $l = []; foreach (city_trades() as $ts => [, $pp]) if ($ts !== $tslug) $l[] = '<a href="'.e(url_for('intervention/'.$c['slug'].'/'.$ts)).'">'.e($pp).'</a>'; echo implode(' · ', $l); ?>
    — <a href="<?= e($c['url']) ?>">tous nos métiers <?= e($c['a']) ?></a><br>
    <?= e($pro) ?> ailleurs : <?php $l = []; foreach (array_keys(city_pages()) as $s) if ($s !== $c['slug']) { $x = city_page($s); $l[] = '<a href="'.e(url_for('intervention/'.$s.'/'.$tslug)).'">'.e($x['nom']).'</a>'; } echo implode(' · ', $l); ?>
  </p>
</div></section>
<?php
    render_footer();
}
