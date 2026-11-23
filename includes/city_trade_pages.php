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

/** Ouverte à Google : chantiers du métier dans le secteur, ou contenu rédigé pour la page. */
function city_trade_indexable(array $c, string $tslug): bool
{
    $t = city_trades()[$tslug][0] ?? '';
    return $t !== '' && (city_trade_content($c['slug'], $tslug) !== null || city_trade_reals($c, $t) !== []);
}

/** Lignes « Déplacement et main-d'œuvre » de la page Tarifs (vide si la page est désactivée). */
function city_trade_price_rows(): array
{
    if (!tarifs_enabled()) return [];
    $g = tarifs_groups()[0] ?? null;
    $rows = [];
    foreach ((array)($g['rows'] ?? []) as $r) {
        [$l, $p] = array_pad(array_values((array)$r), 2, '');
        if (trim((string)$p) !== '') $rows[] = [trim((string)$l), trim((string)$p)];
    }
    return $rows;
}

/** Réponse « combien ça coûte », construite depuis la page Tarifs : jamais un prix recopié à la main. */
function city_trade_price_answer(): string
{
    $num = static fn(string $p): ?float => preg_match('/(\d+(?:[.,]\d+)?)/', $p, $m) ? (float)str_replace(',', '.', $m[1]) : null;
    $fmt = static fn(float $v): string => number_format($v, 2, ',', ' ').' €';
    $dep = $diag = $mo = [];
    foreach (city_trade_price_rows() as [$l, $p]) {
        $v = $num($p); if ($v === null) continue;
        $ll = text_ascii_lower($l);
        // La ligne Déplacement est une fourchette « 52,80 € à 124,80 € TTC » : on garde les deux bornes.
        if (str_starts_with($ll, 'deplacement')) { preg_match_all('/\d+(?:[.,]\d+)?/', $p, $all); foreach ($all[0] as $x) $dep[] = (float)str_replace(',', '.', $x); }
        elseif (str_starts_with($ll, 'diagnostic')) $diag[] = $v;
        elseif (str_starts_with($ll, 'main')) $mo[] = $v;
    }
    if (!$dep) return 'Le prix du déplacement vous est annoncé au téléphone, avant tout départ ; les travaux font l\'objet d\'un devis gratuit.';
    $s = 'Le déplacement coûte de '.$fmt(min($dep)).' à '.$fmt(max($dep)).' TTC selon le moment (intervention programmée en semaine, urgence, dimanche, nuit)';
    if ($diag) $s .= ', le diagnostic '.$fmt(min($diag)).' TTC';
    $s .= '.';
    if ($mo) $s .= ' La main-d\'œuvre est comptée à la demi-heure, à partir de '.$fmt(min($mo)).' de l\'heure.';
    return $s.' Le montant pour votre cas vous est annoncé au téléphone, avant le départ ; les pièces et travaux font l\'objet d\'un devis gratuit.';
}

/**
 * Photos de terrain des pages ville × métier, trois emplacements sous la ligne de flottaison :
 *   vehicule     (A) après « Pourquoi EMAE à … »  : véhicule floqué, secteur ;
 *   intervention (B) dans « Intervention en 3 étapes » : technicien, équipement ;
 *   equipe       (C) avant le formulaire de devis final : équipe, chantier.
 * Photos envoyées depuis l'admin (Contenus › Photos des pages ville, admin/terrain_photos.php) :
 *   ct_photo_{emplacement}_{métier}  photo propre au métier, prioritaire ;
 *   ct_photo_{emplacement}           photo pour toutes les pages.
 * Sans photo : rien n'est affiché (jamais d'image cassée ni de photo d'illustration).
 * L'image servie est une copie réduite à 1200 px (+ WebP) quand le serveur sait la produire.
 */
function city_trade_photo_slots(): array
{
    return [
        'vehicule'     => ['A', 'Véhicule floqué, secteur', 'sous « Pourquoi EMAE à … »'],
        'intervention' => ['B', 'Technicien en intervention, équipement pro', 'sous « Intervention en 3 étapes »'],
        'equipe'       => ['C', 'Équipe, chantier', 'juste avant le formulaire de devis final'],
    ];
}

/** Photo d'un emplacement : ['path', 'key'] (métier d'abord, sinon toutes les pages), ou null. */
function city_trade_photo(string $slot, string $trade): ?array
{
    foreach (['ct_photo_'.$slot.'_'.$trade, 'ct_photo_'.$slot] as $k) {
        $path = ltrim(setting_plain($k), '/');
        if ($path !== '' && !str_contains($path, '..') && is_file(dirname(__DIR__).'/'.$path)) return ['path' => $path, 'key' => $k];
    }
    return null;
}

/** <figure> d'un emplacement photo ('' sans photo) : dimensions réelles + ratio fixe (pas de CLS), chargement différé. */
function render_city_trade_photo(string $slot, array $c, string $trade, string $metier): string
{
    $p = city_trade_photo($slot, $trade);
    if (!$p) return '';
    $small = image_resized_variant($p['path'], 1200);
    $sz = $small ? [$small['w'], $small['h']] : @getimagesize(dirname(__DIR__).'/'.$p['path']);
    if (!$sz || $sz[0] < 1 || $sz[1] < 1) return '';
    $label = mb_strtolower(service_template($trade)['label'], 'UTF-8');
    [$alt, $cap] = match ($slot) {
        'vehicule'     => ['Véhicule d\'intervention EMAE, '.$metier.' '.$c['a'].' et dans les communes voisines', 'Véhicule d\'intervention EMAE'],
        'intervention' => ['Technicien EMAE en intervention ('.$label.'), dépannage et installation '.$c['a'], 'Technicien EMAE en intervention'],
        default        => ['L\'équipe EMAE, '.$metier.' '.$c['a'].' ('.$c['dep_code'].')', 'L\'équipe EMAE'],
    };
    return '<figure class="ct-photo">'.picture_html($p['path'], $alt, ['width' => $sz[0], 'height' => $sz[1], 'loading' => 'lazy', 'decoding' => 'async'])
         .'<figcaption>'.e($cap).'</figcaption></figure>';
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
    $txt   = city_trade_content($c['slug'], $tslug) ?? [];
    $meta = [
        'title'       => trim((string)($txt['title'] ?? '')) ?: $titre.' ('.$c['dep_code'].') – Dépannage 7j/7 | '.company_name(),
        'description' => claim_clean((string)($txt['meta'] ?? ''), '') ?: (($clim ? 'Dépannage, entretien et installation de climatisation' : $pro.' pour vos dépannages et travaux')
                         .' '.$c['a'].' et ses environs ('.$c['dep'].'). Prix annoncé avant déplacement, devis gratuit, urgences 7j/7.'),
        'canonical'   => url_for('intervention/'.$c['slug'].'/'.$tslug),
    ];
    if (!city_trade_indexable($c, $tslug)) $meta['robots'] = 'noindex, follow';
    $prix = city_trade_price_rows();
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
    $faq = [];
    if ($txt) {
        // Page rédigée : prix (depuis la page Tarifs), délai, questions propres au métier et à la ville, assurance.
        $faq[] = ['Combien coûte '.($clim ? 'un dépannage de climatisation' : 'l\'intervention d\'un '.$metier).' '.$c['a'].' ?', city_trade_price_answer()];
        // Entretien de chaudière : prix de la page Tarifs (fourchette), jamais recopié ici.
        if ($trade === 'chauffage' && ($_pe = tarif_price('chauffage', 'Entretien annuel de chaudière')) !== '')
            $faq[] = ['Combien coûte l\'entretien annuel d\'une chaudière '.$c['a'].' ?', $_pe.', hors déplacement, selon l\'appareil (gaz, fioul, condensation…) et son accès. Le prix exact, déplacement compris, vous est annoncé avant le rendez-vous, que vous pouvez prendre en ligne.'];
        $_tr = city_travel_label($c['slug']);
        $faq[] = ['Pouvez-vous venir aujourd\'hui '.$c['a'].' ?', 'Les urgences sont prises 24h/24 et 7j/7.'.($_tr !== '' ? ' Le temps de trajet estimé jusqu\'à '.$nom.' est de '.$_tr.', selon la distance et hors circulation.' : '').' Le délai réel dépend des interventions en cours : nous vous le confirmons au téléphone avant de partir, pour que vous ne restiez pas à attendre sans savoir.'];
        unset($_pe, $_tr);
        foreach ((array)($txt['faq'] ?? []) as [$q, $a]) if (!claim_unsupported($q.' '.$a)) $faq[] = [(string)$q, (string)$a];
        $L = legal_info();
        if (in_array($trade, $L['assurance_metiers'], true) && ($L['assurance_fin'] === '' || date('Y-m-d') <= $L['assurance_fin']))
            $faq[] = ['Êtes-vous assurés pour ces travaux ?', 'Oui : '.$L['assurance'].' L\'attestation est envoyée sur simple demande.'];
    }
    $faq[] = ['Intervenez-vous '.$c['a'].' ?', 'Oui, '.$c['a'].' et dans les communes voisines : '.geo_enumerer(array_slice($c['voisines'], 0, 6)).'. Votre adresse n\'est pas citée ? Appelez-nous pour vérifier.'];
    // FAQ générale du métier (la même que sur la page métier), ajoutée à toutes les pages ville.
    $_seen = array_map(static fn(array $f): string => mb_strtolower(trim($f[0]), 'UTF-8'), $faq);
    foreach ($tpl['faq'] as [$q, $a]) {
        $q = trim((string)$q); $a = faq_answer_fix($q, (string)$a);
        if ($q === '' || trim($a) === '' || in_array(mb_strtolower($q, 'UTF-8'), $_seen, true)) continue;
        $faq[] = [$q, $a]; $_seen[] = mb_strtolower($q, 'UTF-8');
    }
    unset($_seen);
    $photoA = render_city_trade_photo('vehicule', $c, $trade, $metier);
    $pannes = array_values(array_filter((array)($txt['pannes'] ?? []), static fn($p) => !claim_unsupported((string)$p)));
    $local  = array_values(array_filter((array)($txt['local'] ?? []), static fn($l) => !claim_unsupported(implode(' ', (array)$l))));
    $steps = [1 => ['Diagnostic sur place', 'Le technicien recherche la cause de la panne et vous l\'explique simplement.'],
              2 => ['Devis clair avant travaux', 'Vous connaissez le prix avant toute réparation et vous décidez : rien n\'est fait sans votre accord.'],
              3 => ['Réparation aux normes', 'Réparation ou installation dans les règles de l\'art, puis facture détaillée en fin d\'intervention.']];
    ?>
<section class="page-hero"><div class="wrap svc-hero-grid">
  <div class="svc-hero-content">
    <div class="ph-eyebrow">// <?= e($tpl['label']) ?> — <?= e($nom) ?> (<?= e($c['cp']) ?>)</div>
    <?php if (trim((string)($txt['h1'] ?? '')) !== ''): ?>
    <h1 class="ph-h1"><?= e((string)$txt['h1']) ?></h1>
    <?php else: ?>
    <h1 class="ph-h1"><?= e($pro) ?> <em><?= e($c['a']) ?> (<?= e($c['dep_code']) ?>)</em></h1>
    <?php endif; ?>
    <?php if ($txt): ?>
    <p class="ph-lead"><?= e($clim ? 'Dépannage, entretien et installation' : 'Dépannage et installation') ?> <?= e($c['a']) ?> et dans les communes voisines. <?= e(intervention_terms_text()) ?></p>
    <?php else: ?>
    <p class="ph-lead"><?= e(claim_clean(geo_replace((string)$tpl['desc']), $tpl['label'].' : dépannage, entretien et installation.')) ?> Nous intervenons <?= e($c['a']) ?> et dans les communes voisines ; le prix vous est annoncé avant tout déplacement.</p>
    <?php endif; ?>
    <p class="ph-lead" style="font-size:.92rem;opacity:.85;"><?= e(hours_text()) ?></p>
    <div class="ph-badges">
      <?php foreach ((array)$tpl['badges'] as $b): ?><span class="ph-badge hl"><?= e($b) ?></span><?php endforeach; ?>
      <span class="ph-badge"><?= e(company_hours()) ?></span>
    </div>
    <div class="btn-row" style="margin-top:1.5rem;">
      <a class="btn btn-p btn-lg" href="<?= e(company_phone_link()) ?>">📞 <?= e(company_phone()) ?></a>
      <a class="btn btn-outline btn-lg" href="#etre-rappele">Être rappelé</a>
    </div>
    <?= render_google_badge() ?>
  </div>
  <div class="hero-form" id="etre-rappele">
    <div class="hero-card">
      <div class="hero-card-tag">Rappel gratuit</div>
      <h2>Être rappelé</h2>
      <?php render_quote_form($cards, 'service_'.$trade, 'rappel'); ?>
    </div>
  </div>
</div></section>
<?= render_trust_band($trade) ?>
<?= render_service_brands($tpl) ?>

<?php if ($txt && claim_clean((string)($txt['intro'] ?? ''), '') !== ''): ?>
<section class="sec sec-card"><div class="wrap" style="max-width:860px;">
  <p class="section-lead" style="margin:0;"><?= e((string)$txt['intro']) ?></p>
</div></section>
<?php endif; ?>

<?= render_service_equipment($trade) ?>

<?php if ($prix): ?>
<section class="sec sec-navy"><div class="wrap" style="max-width:860px;">
  <div class="svc-label">Prix annoncés</div>
  <h2 class="section-title">Ce que vous payez, <em>annoncé avant de partir</em></h2>
  <ul class="ct-prix">
    <?php foreach ($prix as [$l, $p]): ?><li><span><?= e($l) ?></span><strong><?= e($p) ?></strong></li><?php endforeach; ?>
  </ul>
  <?php if ($trade === 'chauffage') echo render_rdv_prestation_cta('entretien-chaudiere'); ?>
  <p class="section-lead" style="margin-top:1rem;font-size:.92rem;">Main-d'œuvre comptée à la demi-heure. Pièces et travaux : devis gratuit et écrit, rien n'est fait sans votre accord. <a href="<?= e(route_url('tarifs')) ?>">Détail des tarifs →</a></p>
</div></section>
<?php endif; ?>

<?php if ($pannes): ?>
<section class="sec sec-card"><div class="wrap" style="max-width:960px;">
  <div class="svc-label">Nos interventions <?= e($c['a']) ?></div>
  <h2 class="section-title"><?= e($pro) ?> <?= e($c['a']) ?> : <em>les pannes que nous traitons</em></h2>
  <ul class="sign-list"><?php foreach ($pannes as $p): ?><li><?= e((string)$p) ?></li><?php endforeach; ?></ul>
  <p class="section-lead" style="margin-top:1.25rem;"><a href="<?= e(route_url($trade)) ?>">Toutes nos prestations en <?= e(mb_strtolower($tpl['label'], 'UTF-8')) ?> →</a></p>
</div></section>
<?php endif; ?>

<?php if ($local): ?>
<section class="sec sec-navy"><div class="wrap">
  <div class="svc-label">Pourquoi EMAE <?= e($c['a']) ?></div>
  <h2 class="section-title">Ce que nous savons <em>de <?= e($nom) ?></em></h2>
  <div class="assur-grid">
    <?php foreach ($local as [$lt, $lx]): ?><div class="assur-card"><h3><?= e((string)$lt) ?></h3><p><?= e((string)$lx) ?></p></div><?php endforeach; ?>
  </div>
  <?= $photoA /* A : ancrage local */ ?>
</div></section>
<?php elseif ($photoA !== ''): ?>
<section class="sec sec-navy"><div class="wrap"><?= $photoA ?></div></section>
<?php endif; ?>

<?php if ($signs): ?>
<section class="sec sec-navy"><div class="wrap" style="max-width:960px;">
  <div class="svc-label"><?= e(setting('svc_signs_label', 'Signes d\'alerte')) ?></div>
  <h2 class="section-title"><?= e($tpl['signs_title']) ?></h2>
  <ul class="sign-list"><?php foreach ($signs as $s): ?><li><?= e($s) ?></li><?php endforeach; ?></ul>
  <div class="btn-row" style="margin-top:1.5rem;">
    <a class="btn btn-p btn-lg" href="<?= e(company_phone_link()) ?>">📞 <?= e(company_phone()) ?></a>
    <a class="btn btn-outline btn-lg" href="#etre-rappele">Être rappelé</a>
  </div>
</div></section>
<?php endif; ?>

<section class="sec sec-card"><div class="wrap">
  <?php render_service_offer_block($tpl, $trade, !$prix); ?>
  <p class="section-lead" style="margin-top:1.25rem;"><a href="<?= e(route_url($trade)) ?>">Toutes nos prestations en <?= e(mb_strtolower($tpl['label'], 'UTF-8')) ?> →</a><?php if (tarifs_enabled()): ?> · <a href="<?= e(route_url('tarifs')) ?>">Nos tarifs (déplacement, main-d'œuvre) →</a><?php endif; ?></p>
</div></section>

<section class="sec sec-navy"><div class="wrap" style="max-width:960px;">
  <div class="prox-grid"><div>
  <div class="svc-label">Proximité</div>
  <h2 class="section-title"><?= e($nom) ?> <em>et ses environs</em></h2>
  <p class="section-lead">Notre <?= e($metier) ?> <?= city_near_base($c['slug']) ? 'part de notre base de Dole et intervient' : 'intervient' ?> <?= e($c['a']) ?> comme dans les communes voisines. <?= e(intervention_terms_text()) ?></p>
  <?= render_city_travel($c) ?>
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
  <h2 class="section-title"><?= e($tpl['label']) ?> : chantiers près de <em><?= e($nom) ?></em></h2>
  <div class="reals-grid" style="margin-top:1.75rem;">
    <?php foreach ($reals as $r): ?>
    <div class="real-card"><?php render_realisation_media($r); ?>
      <div class="real-body"><h3 class="real-h"><?= e($r['title']) ?></h3><?= realisation_meta($r) ?></div>
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
  <?= render_city_trade_photo('intervention', $c, $trade, $metier) /* B : preuve technique */ ?>
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
  <?php $_pn_list = pannes_for_trade($trade); if ($_pn_list): ?>
  <div class="svc-label" style="margin-top:2.25rem;">Pannes fréquentes : que faire ?</div>
  <div class="px-chips">
    <?php foreach ($_pn_list as $_pn): ?><a class="px-chip" href="<?= e(route_url($_pn['slug'])) ?>"><?= e($_pn['icon'].' '.$_pn['h1']) ?></a><?php endforeach; ?>
  </div>
  <?php endif; unset($_pn_list, $_pn); ?>
  <script type="application/ld+json"><?= json_encode(['@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => array_map(static fn(array $f): array => ['@type' => 'Question', 'name' => $f[0], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f[1]]], $faq)], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?></script>
  <p style="margin-top:1.5rem;font-size:.9rem;color:var(--t2);line-height:1.8;">
    Aussi <?= e($c['a']) ?> : <?php $l = []; foreach (city_trades() as $ts => [, $pp]) if ($ts !== $tslug) $l[] = '<a href="'.e(url_for('intervention/'.$c['slug'].'/'.$ts)).'">'.e($pp).'</a>'; echo implode(' · ', $l); ?>
    — <a href="<?= e($c['url']) ?>">tous nos métiers <?= e($c['a']) ?></a><br>
    <?= e($pro) ?> ailleurs : <?php $l = []; foreach (array_keys(city_pages()) as $s) if ($s !== $c['slug']) { $x = city_page($s); $l[] = '<a href="'.e(url_for('intervention/'.$s.'/'.$tslug)).'">'.e($x['nom']).'</a>'; } echo implode(' · ', $l); ?>
  </p>
</div></section>

<section class="sec sec-card"><div class="wrap" style="max-width:700px;">
  <?= render_city_trade_photo('equipe', $c, $trade, $metier) /* C : confiance finale, avant le contact */ ?>
  <div class="svc-label"><?= e(setting('svc_form_label', 'Devis gratuit')) ?> <?= e(mb_strtolower($tpl['label'], 'UTF-8')) ?> <?= e($c['a']) ?></div>
  <div class="qs-card">
    <div class="hero-card-tag">📋 <?= e(setting('svc_form_tag', 'Votre technicien')) ?> <?= e(mb_strtolower($tpl['label'], 'UTF-8')) ?></div>
    <h2 style="font-family:var(--font-h);font-size:1.4rem;font-weight:800;color:#fff;margin-bottom:1.35rem;"><?= e(setting('svc_form_title', 'Devis gratuit')) ?></h2>
    <?php render_quote_form($cards, 'service_'.$trade); ?>
  </div>
</div></section>
<?php
    render_footer();
}

/** Offre du métier (cartes en plus, encadré Enedis, prestations, RDV entretien) : commune à la page métier et aux pages ville × métier. */
function render_service_offer_block(array $tpl, string $sk, bool $rdv = true): void
{
    ?>
  <?php
  // Bloc propre à un métier : prestation recherchée sur Google (chauffe-eau).
  // et filtre des coupures réseau, qui relèvent d'Enedis et non d'un électricien.
  $svcExtras = [
      'chauffage' => [
          ['chauffe-eau', setting('svc_chauffage_ecs_title', 'Chauffe-eau et ballon d\'eau chaude'),
                 setting('svc_chauffage_ecs_text', 'Plus d\'eau chaude ? Nous dépannons et remplaçons les chauffe-eau électriques, les ballons d\'eau chaude et la production d\'eau chaude des chaudières : résistance, thermostat, groupe de sécurité, fuite du ballon. Remplacement complet si l\'appareil est hors d\'usage, avec mise en service le jour même quand c\'est possible.')],
      ],
  ][$sk] ?? [];
  ?>
  <?php if ($svcExtras): ?>
  <div class="ofr-extras">
    <?php foreach ($svcExtras as [$ico, $t, $d]): ?>
    <div class="ofr-card"><span class="ofr-ico"><img src="<?= e(service_icon_url($ico)) ?>" alt="" width="64" height="64" loading="lazy"></span><h3 class="ofr-h"><?= e($t) ?></h3><p class="ofr-p"><?= e($d) ?></p></div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
  <?php if ($sk === 'electricite'): ?>
  <div class="svc-intro" style="border-left:4px solid var(--primary);padding:.9rem 1.1rem;margin:0 0 2rem;">
    <p style="margin:0;"><strong><?= e(setting('svc_electricite_enedis_title', 'Coupure dans tout le quartier ou le village ?')) ?></strong>
    <?= e(setting('svc_electricite_enedis_text', 'Si vos voisins n\'ont plus de courant non plus, la panne vient du réseau public : appelez Enedis au 09 72 67 50 suivi du numéro de votre département (21, 25, 39…), gratuit 24h/24. Si vous êtes le seul sans électricité, c\'est votre installation : appelez-nous.')) ?></p>
  </div>
  <?php endif; ?>
  <div class="svc-label"><?= e(setting('svc_offer_label','Notre offre')) ?></div>
  <h2 class="section-title"><?= e($tpl['offer_title']) ?></h2>
  <p class="section-lead svc-lead"><?= e(setting('svc_offer_lead','Urgence, entretien ou projet : voici les prestations que nous réalisons le plus souvent. Pour toute autre demande, décrivez-nous votre besoin, nous vous disons rapidement si nous pouvons intervenir.')) ?></p>
  <?php $svcOffers = service_offer_cards((array)$tpl['offer_items']); ?>
  <div class="ofr" data-ofr>
    <div class="ofr-scroller" tabindex="0" role="region" aria-label="<?= e($tpl['offer_title']) ?>">
      <?php foreach ($svcOffers as $o): ?>
      <article class="ofr-card">
        <span class="ofr-ico"><img src="<?= e(service_icon_url($o['icon'])) ?>" alt="" width="64" height="64" loading="lazy"></span>
        <h3 class="ofr-h"><?= e($o['title']) ?></h3>
        <?php if ($o['text'] !== ''): ?><p class="ofr-p"><?= e($o['text']) ?></p><?php endif; ?>
      </article>
      <?php endforeach; unset($o); ?>
    </div>
    <?php if (count($svcOffers) > 1): ?>
    <div class="ofr-nav">
      <span class="ofr-hint">Faites défiler <span aria-hidden="true">→</span></span>
      <button type="button" class="ofr-btn" data-ofr-prev aria-label="Prestations précédentes">←</button>
      <button type="button" class="ofr-btn" data-ofr-next aria-label="Prestations suivantes">→</button>
    </div>
    <?php endif; ?>
  </div>
  <?php if ($rdv && $sk === 'chauffage') echo render_rdv_prestation_cta('entretien-chaudiere'); ?>
<?php
}

/** Bandeau défilant des marques du métier (page métier et pages ville × métier). */
function render_service_brands(array $tpl): string
{
    $brands = array_values(array_filter(array_map(fn($b) => trim(is_array($b) ? (string)reset($b) : (string)$b), (array)$tpl['brands']), 'strlen'));
    if (!$brands || !$tpl['brands_on']) return '';
    ob_start(); ?>
<!-- Marques : bandeau défilant (texte, pas de logos de fabricants) -->
<section class="brands" aria-label="<?= e($tpl['brands_title']) ?>">
  <div class="wrap"><p class="brands-h"><?= e($tpl['brands_title']) ?></p></div>
  <div class="brands-band">
    <div class="brands-track">
      <?php for ($i = 0; $i < 2; $i++): ?>
      <ul class="brands-list"<?= $i ? ' aria-hidden="true"' : '' ?>>
        <?php foreach ($brands as $b): ?><li><?= e($b) ?></li><?php endforeach; ?>
      </ul>
      <?php endfor; unset($i, $b); ?>
    </div>
  </div>
  <div class="wrap"><p class="brands-note"><?= e(setting('svc_brands_note', 'Marques citées à titre indicatif : nous sommes un réparateur indépendant, sans lien avec ces fabricants.')) ?></p></div>
</section>
<?php
    return (string)ob_get_clean();
}

/** Appareils pris en charge, par métier : [dessin, nom]. */
function service_equipment_items(string $sk): array
{
    return [
        'chauffage' => [
            ['pac', 'Pompe à chaleur air/eau'], ['clim', 'Pompe à chaleur air/air'],
            ['chaudiere', 'Chaudière gaz'], ['chaudiere', 'Chaudière à condensation'],
            ['chaudiere', 'Chaudière fioul'], ['flamme', 'Chaudière bois (bûches)'],
            ['flamme', 'Chaudière à granulés (pellets)'], ['eclair', 'Chaudière électrique'],
            ['chauffe-eau', 'Chauffe-eau électrique (cumulus)'], ['chauffe-eau', "Ballon d'eau chaude"],
            ['pac', 'Chauffe-eau thermodynamique'], ['flamme', 'Chauffe-eau gaz'],
            ['radiateur', 'Radiateurs et plancher chauffant'], ['thermometre', 'Thermostat et régulation'],
        ],
    ][$sk] ?? [];
}

/** Bandeau défilant lent des appareils pris en charge (dessin + nom). */
function render_service_equipment(string $sk): string
{
    $items = service_equipment_items($sk);
    if (!$items) return '';
    ob_start(); ?>
<section class="brands equip" aria-label="Appareils pris en charge">
  <div class="wrap"><p class="brands-h">Les appareils que nous dépannons et entretenons</p></div>
  <div class="brands-band">
    <div class="brands-track equip-track">
      <?php for ($i = 0; $i < 2; $i++): ?>
      <ul class="brands-list equip-list"<?= $i ? ' aria-hidden="true"' : '' ?>>
        <?php foreach ($items as [$ico, $nom]): ?><li><img src="<?= e(service_icon_url($ico)) ?>" alt="" width="44" height="44" loading="lazy"><span><?= e($nom) ?></span></li><?php endforeach; ?>
      </ul>
      <?php endfor; ?>
    </div>
  </div>
</section>
<?php
    return (string)ob_get_clean();
}
