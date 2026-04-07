<?php
declare(strict_types=1);

/* ── HEAD ── */
function render_head(array $meta): void
{
    $gaId   = setting('google_analytics_id','');
    $gAdsId = setting('google_ads_id','');
    $gAdsCv = setting('google_ads_conversion_label','');

    echo '<!DOCTYPE html><html lang="fr"><head>';
    echo '<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">';
    echo '<title>'.e($meta['title']).'</title>';
    echo '<meta name="description" content="'.e($meta['description']).'">';
    echo '<link rel="canonical" href="'.e($meta['canonical']).'">';
    echo '<meta name="theme-color" content="#0A0C10">';
    echo '<meta property="og:title" content="'.e($meta['title']).'">';
    echo '<meta property="og:description" content="'.e($meta['description']).'">';
    echo '<meta property="og:type" content="website">';
    echo '<meta property="og:url" content="'.e($meta['canonical']).'">';
    echo '<link rel="preconnect" href="https://fonts.googleapis.com">';
    echo '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>';
    
    echo '<link rel="stylesheet" href="'.e(asset_url('assets/css/style.css')).'">';
    echo theme_css_variables();
    // Schema.org
    echo '<script type="application/ld+json">'.schema_local_business().'</script>';
    // Google Analytics
    if ($gaId !== '') {
        echo '<script async src="https://www.googletagmanager.com/gtag/js?id='.e($gaId).'"></script>';
        echo '<script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag("js",new Date());gtag("config","'.e($gaId).'");</script>';
    }
    // Google Ads
    if ($gAdsId !== '') {
        echo '<script async src="https://www.googletagmanager.com/gtag/js?id='.e($gAdsId).'"></script>';
        echo '<script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag("js",new Date());gtag("config","'.e($gAdsId).'");</script>';
    }
    // Pass IDs to JS
    if ($gAdsId !== '' || $gAdsCv !== '') {
        echo '<script>window._gAdsId="'.e($gAdsId).'";window._gAdsCv="'.e($gAdsCv).'";</script>';
    }
    echo '<script defer src="'.e(asset_url('assets/js/site.js')).'"></script>';
    echo '</head><body>';
}

/* ── HEADER ── */
function render_header(string $active = ''): void
{
    $ctaLabel = setting('header_cta_label','Devis gratuit');
    $ctaUrl   = setting('header_cta_url','quote');
    if (!preg_match('#^(https?:|tel:|mailto:|/)#i',$ctaUrl)) $ctaUrl = route_url($ctaUrl);
    $showTopbar = setting_bool('topbar_visible',true);
    $phone     = company_phone();
    $phoneLink = company_phone_link();

    if ($showTopbar): ?>
<div class="topbar">
  <div class="wrap topbar-in">
    <div class="topbar-l">
      <a href="<?= e($phoneLink) ?>" class="topbar-phone">📞 <?= e($phone) ?></a>
      <span class="topbar-dot">•</span>
      <a href="mailto:<?= e(company_email()) ?>"><?= e(company_email()) ?></a>
    </div>
    <div class="topbar-r">
      <span>📍 <?= e(company_regions()) ?></span>
      <span class="topbar-dot">•</span>
      <span>🕐 <?= e(company_hours()) ?></span>
    </div>
  </div>
</div>
<?php endif; ?>

<header class="site-header">
  <div class="nav-wrap">
    <a class="brand" href="<?= e(route_url('')) ?>">
      <?php
        $logo = site_logo_path();
        // Auto-detect real PNG logo
        $pngLogo = 'storage/uploads/logos/logo-emae.png';
        if ($logo === '' || $logo === 'storage/uploads/logos/logo-emae-default.svg') {
            if (file_exists(__DIR__.'/../'.$pngLogo)) $logo = $pngLogo;
        }
        $logoExists = trim($logo) !== '' && file_exists(__DIR__.'/../'.$logo);
      ?>
      <?php if ($logoExists): ?>
        <img src="<?= e(asset_url($logo)) ?>" alt="<?= e(company_name()) ?>" style="height:48px;width:auto;max-width:220px;object-fit:contain;">
      <?php else: ?>
        <div>
          <div class="brand-name">EM<span>AE</span></div>
          <span class="brand-tagline">Entreprise Multitech Avancée</span>
        </div>
      <?php endif; ?>
    </a>

    <a class="header-call" href="<?= e($phoneLink) ?>">📞 <?= e($phone) ?></a>

    <button class="nav-toggle" type="button" aria-expanded="false" aria-label="Menu">
      <span></span><span></span><span></span>
    </button>

    <nav class="site-nav" id="main-nav" id="main-nav">
      <?php foreach (nav_items() as $item): ?>
        <a class="<?= $active === $item['url'] ? 'on' : '' ?>" href="<?= e($item['url']) ?>"><?= e($item['label']) ?></a>
      <?php endforeach; ?>
      <a class="btn--cta" href="<?= e($ctaUrl) ?>"><?= e($ctaLabel) ?></a>
    </nav>
  </div>
</header>

<!-- Barre CTA fixe mobile -->
<div class="mob-bar">
  <a href="<?= e($phoneLink) ?>" class="mob-bar-call">📞 Appeler</a>
  <a href="<?= e(route_url('quote')) ?>" class="mob-bar-devis">📋 Devis gratuit</a>
</div>

<?php
    if ($msg = flash('success')) echo '<div class="wrap"><div class="flash flash-ok">'.e($msg).'</div></div>';
    if ($msg = flash('error'))   echo '<div class="wrap"><div class="flash flash-err">'.e($msg).'</div></div>';
}

/* ── FOOTER ── */
function render_footer(): void
{
    $phone = company_phone();
    $phoneLink = company_phone_link();
    ?>
<footer class="site-footer">
  <div class="container footer-grid">
    <div class="ft-col">
      <div class="ft-brand-name">EM<span>AE</span></div>
      <div class="ft-tagline">Entreprise Multitech Avancée</div>
      <div class="ft-badges">
        <span class="ft-badge">✓ Devis gratuit</span>
        <span class="ft-badge">⚡ Urgences 24h/7j</span>
        <span class="ft-badge">🔒 Artisans qualifiés</span>
      </div>
    </div>
    <div class="ft-col">
      <h3>Nos services</h3>
      <ul>
        <li><a href="<?= e(route_url('electricite')) ?>">Électricité</a></li>
        <li><a href="<?= e(route_url('plomberie')) ?>">Plomberie</a></li>
        <li><a href="<?= e(route_url('chauffage')) ?>">Chauffage & PAC</a></li>
        <li><a href="<?= e(route_url('climatisation')) ?>">Climatisation CVC</a></li>
        <li><a href="<?= e(route_url('services')) ?>">Tous nos services</a></li>
      </ul>
    </div>
    <div class="ft-col">
      <h3>Navigation</h3>
      <ul>
        <?php foreach (nav_items() as $item): ?>
          <li><a href="<?= e($item['url']) ?>"><?= e($item['label']) ?></a></li>
        <?php endforeach; ?>
        <li><a href="<?= e(route_url('quote')) ?>">Devis gratuit</a></li>
        <li><a href="<?= e(url_for('sitemap.php')) ?>">Sitemap</a></li>
      </ul>
    </div>
    <div class="ft-col">
      <h3>Contact & urgences</h3>
      <a class="ft-phone" href="<?= e($phoneLink) ?>"><?= e($phone) ?></a>
      <div class="ft-hours"><?= e(company_hours()) ?></div>
      <a class="ft-email" href="mailto:<?= e(company_email()) ?>"><?= e(company_email()) ?></a>
      <?php if (company_siret() !== ''): ?>
        <div style="font-size:.75rem;color:rgba(255,255,255,.25);margin-top:.5rem;">SIRET : <?= e(company_siret()) ?></div>
      <?php endif; ?>
      <a class="btn btn--primary" href="<?= e(route_url('quote')) ?>" style="margin-top:1rem;display:inline-flex;">Demander un devis</a>
    </div>
  </div>
  <div class="footer-bottom">
    <div class="container footer-bottom__inner">
      <p>© <?= e(current_year()) ?> <?= e(company_name()) ?> — <?= e(company_slogan()) ?> — Tous droits réservés.</p>
      <div class="footer-bottom-links">
        <a href="<?= e(route_url('faq')) ?>">FAQ</a>
        <a href="<?= e(route_url('contact')) ?>">Contact</a>
      </div>
    </div>
  </div>
</footer>
</body></html>
<?php
}

/* ── FORMULAIRE RÉUTILISABLE ── */
function render_quote_form(array $cards, string $source = 'form'): void
{
    $placeholder = setting('home_quote_city_placeholder','Ex : Meaux, Paris, Toulouse');
    $submitLabel = quote_form_options()['submit_label'] ?? 'Envoyer ma demande';
    ?>
<form action="<?= e(route_url('quote')) ?>" method="post">
  <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
  <input type="hidden" name="form_type" value="quote">
  <input type="hidden" name="source" value="<?= e($source) ?>">
  <input type="text" name="website" value="" class="hp" tabindex="-1" autocomplete="off">
  <div class="f-row">
    <div class="f-field"><span class="f-label">Nom complet *</span><input class="f-input" type="text" name="full_name" placeholder="Votre nom" required></div>
    <div class="f-field"><span class="f-label">Téléphone *</span><input class="f-input" type="tel" name="phone" placeholder="06 00 00 00 00" required></div>
  </div>
  <div class="f-row">
    <div class="f-field"><span class="f-label">Email</span><input class="f-input" type="email" name="email" placeholder="votre@email.fr"></div>
    <div class="f-field"><span class="f-label">Ville</span><input class="f-input" type="text" name="city" placeholder="<?= e($placeholder) ?>"></div>
  </div>
  <div class="f-field">
    <span class="f-label">Service souhaité</span>
    <select class="f-input" name="service_type">
      <option value="">Choisir un service</option>
      <?php foreach ($cards as $c): ?><option value="<?= e($c['title']) ?>"><?= e($c['title']) ?></option><?php endforeach; ?>
    </select>
  </div>
  <div class="f-field">
    <span class="f-label">Urgence</span>
    <select class="f-input" name="urgency">
      <option value="Normale">Normale — intervention planifiée</option>
      <option value="Urgente">Urgente — sous 24h</option>
      <option value="Très urgente">Très urgente — dès maintenant</option>
    </select>
  </div>
  <div class="f-field">
    <span class="f-label">Votre besoin *</span>
    <textarea class="f-input f-textarea" name="message" placeholder="Décrivez votre panne ou votre demande..." required></textarea>
  </div>
  <button class="btn btn-p btn-lg btn-block" type="submit"><?= e($submitLabel) ?></button>
  <p class="f-meta">✓ Gratuit <span class="f-meta-dot"></span> ✓ Sans engagement <span class="f-meta-dot"></span> ✓ Réponse rapide</p>
</form>
<?php
}
