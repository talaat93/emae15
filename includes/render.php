<?php
declare(strict_types=1);

/**
 * Icônes SVG inline (rendu identique sur toutes les plateformes, contrairement
 * aux emojis) pour les pictogrammes codés en dur dans les gabarits publics.
 */
function icon_svg(string $name): string
{
    $attrs = 'width="1em" height="1em" viewBox="0 0 24 24" aria-hidden="true" focusable="false" style="display:inline-block;vertical-align:-0.14em;flex-shrink:0;"';
    $paths = [
        'phone' => '<path fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/>',
        'clock' => '<circle cx="12" cy="12" r="9" fill="none" stroke="currentColor" stroke-width="2"/><path fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M12 7v5l3.5 2"/>',
        'pin'   => '<path fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0z"/><circle cx="12" cy="10" r="3" fill="none" stroke="currentColor" stroke-width="2"/>',
        'bolt'  => '<path fill="currentColor" d="M13 2 4 14h7l-1 8 9-12h-7l1-8z"/>',
        'lock'  => '<rect x="4" y="11" width="16" height="10" rx="2" fill="none" stroke="currentColor" stroke-width="2"/><path fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M8 11V7a4 4 0 0 1 8 0v4"/>',
    ];
    if (!isset($paths[$name])) return '';
    return '<svg '.$attrs.'>'.$paths[$name].'</svg>';
}

/* ── CONSENTEMENT AUX COOKIES ── */
/**
 * Choix du visiteur, gardé dans le cookie technique « emae_consent » (6 mois) :
 * « v1.stats=0.ads=1 ». Lu côté navigateur (consent_head_script) et ici, pour les tests.
 * @return array{set:bool, stats:bool, ads:bool}
 */
function consent_state(?string $raw = null): array
{
    $raw = $raw ?? (string)($_COOKIE['emae_consent'] ?? '');
    if (!str_starts_with($raw, 'v1')) return ['set' => false, 'stats' => false, 'ads' => false];
    return ['set' => true, 'stats' => str_contains($raw, 'stats=1'), 'ads' => str_contains($raw, 'ads=1')];
}

/** Traceurs soumis à consentement présents sur le site (sans eux, pas de bandeau). */
function consent_tags(): array
{
    $ga  = trim(setting_plain('google_analytics_id', ''));
    $ads = trim(setting_plain('google_ads_id', ''));
    return [
        'ga'  => preg_match('/^G-[A-Z0-9]+$/i', $ga) ? $ga : '',
        'ads' => preg_match('/^AW-\d+$/i', $ads) ? $ads : '',
    ];
}

/**
 * Script de tête : définit le chargeur des balises Google, et ne l'appelle que si le
 * visiteur a déjà accepté. Ordre exigé par le mode consentement v2 : « default »
 * (tout refusé), « update » (le choix), puis config et chargement de gtag.js.
 * Avant un accord, gtag n'existe pas : aucun événement n'est mis en file, donc rien
 * n'est rejoué à l'acceptation. La conversion « demande envoyée » n'est envoyée que
 * si l'accord publicité est déjà donné sur la page qui suit l'envoi.
 */
function consent_head_script(string $gaId, string $adsId, string $callLabels, string $quoteLabel, bool $quoteConv): string
{
    $t = consent_tags();
    if ($t['ga'] === '' && $t['ads'] === '') return '';
    $cfg = [
        'ga'    => $t['ga'],
        'ads'   => $t['ads'],
        // Libellés de conversion « appel » (clic sur un numéro) — liste séparée par des virgules dans l'admin.
        'call'  => array_values(array_filter(array_map('trim', explode(',', $callLabels)), static fn($l) => preg_match('/^[A-Za-z0-9_-]+$/', $l))),
        'quote' => preg_match('/^[A-Za-z0-9_-]+$/', $quoteLabel) ? $quoteLabel : '',
        'conv'  => $quoteConv,
    ];
    $js = <<<'JS'
(function(){
  var T=__CFG__;window.emaeTags=T;window.emaeAdsOk=false;window.emaeStatsOk=false;
  function read(){var m=document.cookie.match(/(?:^|;\s*)emae_consent=([^;]*)/);if(!m)return null;var v=decodeURIComponent(m[1]);if(v.indexOf('v1')!==0)return null;return{stats:/stats=1/.test(v),ads:/ads=1/.test(v)};}
  window.emaeConsentRead=read;
  var loaded=false;
  window.emaeLoadTags=function(c){
    if(loaded||!c)return;
    var ids=[];if(c.stats&&T.ga)ids.push(T.ga);if(c.ads&&T.ads)ids.push(T.ads);
    if(!ids.length)return;
    loaded=true;
    window.dataLayer=window.dataLayer||[];window.gtag=function(){dataLayer.push(arguments);};
    gtag('consent','default',{ad_storage:'denied',ad_user_data:'denied',ad_personalization:'denied',analytics_storage:'denied'});
    gtag('consent','update',{ad_storage:c.ads?'granted':'denied',ad_user_data:c.ads?'granted':'denied',ad_personalization:c.ads?'granted':'denied',analytics_storage:c.stats?'granted':'denied'});
    gtag('js',new Date());
    ids.forEach(function(id){gtag('config',id);});
    window.emaeAdsOk=!!(c.ads&&T.ads);window.emaeStatsOk=!!(c.stats&&T.ga);
    if(T.conv){
      if(window.emaeAdsOk&&T.quote)gtag('event','conversion',{send_to:T.ads+'/'+T.quote});
      if(window.emaeStatsOk)gtag('event','generate_lead',{event_category:'lead',event_label:'demande_enregistree'});
    }
    T.conv=false;
    var s=document.createElement('script');s.async=true;s.src='https://www.googletagmanager.com/gtag/js?id='+encodeURIComponent(ids[0]);document.head.appendChild(s);
  };
  var c=read();
  if(c)window.emaeLoadTags(c);
  T.conv=false; /* sans accord déjà donné : pas de rattrapage de la conversion */
})();
JS;
    return '<script>'.str_replace('__CFG__', (string)json_encode($cfg, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP), $js).'</script>';
}

/** Bandeau de choix (Accepter / Refuser / Paramétrer), rouvrable depuis le pied de page. */
function render_consent_banner(): void
{
    $t = consent_tags();
    if ($t['ga'] === '' && $t['ads'] === '') return;
    ?>
<div class="ck-banner" id="ck-banner" role="dialog" aria-labelledby="ck-title" aria-describedby="ck-text" hidden>
  <div class="ck-in">
    <p class="ck-title" id="ck-title">Vos choix sur les cookies</p>
    <p class="ck-text" id="ck-text">Avec votre accord, nous utilisons des cookies Google<?= $t['ga'] !== '' ? ' pour mesurer l\'audience du site' : '' ?><?= $t['ga'] !== '' && $t['ads'] !== '' ? ' et' : '' ?><?= $t['ads'] !== '' ? ' pour mesurer l\'efficacité de nos annonces Google Ads' : '' ?>. Refuser n'empêche ni d'appeler ni d'envoyer une demande. Vous pouvez changer d'avis à tout moment (« Gestion des cookies » en bas de page). <a href="<?= e(route_url('mentions-legales')) ?>#cookies">En savoir plus</a></p>
    <div class="ck-opts" id="ck-opts" hidden>
      <?php if ($t['ga'] !== ''): ?><label class="ck-opt"><input type="checkbox" id="ck-stats"> <span><strong>Mesure d'audience</strong> (Google Analytics) : pages vues, parcours.</span></label><?php endif; ?>
      <?php if ($t['ads'] !== ''): ?><label class="ck-opt"><input type="checkbox" id="ck-ads"> <span><strong>Publicité</strong> (Google Ads) : savoir si une visite venue d'une annonce aboutit à un appel ou une demande.</span></label><?php endif; ?>
      <p class="ck-note">Toujours actifs, sans accord : la session et la protection des formulaires, et la mémorisation de ce choix.</p>
    </div>
    <div class="ck-btns">
      <button type="button" class="ck-btn" data-ck="refuse">Tout refuser</button>
      <button type="button" class="ck-btn" data-ck="params" aria-expanded="false" aria-controls="ck-opts">Paramétrer</button>
      <button type="button" class="ck-btn" data-ck="save" hidden>Enregistrer mes choix</button>
      <button type="button" class="ck-btn" data-ck="accept">Tout accepter</button>
    </div>
  </div>
</div>
<script>
(function(){
  var b=document.getElementById('ck-banner');if(!b)return;
  var opts=document.getElementById('ck-opts'),st=document.getElementById('ck-stats'),ad=document.getElementById('ck-ads');
  var save=b.querySelector('[data-ck="save"]'),par=b.querySelector('[data-ck="params"]');
  function write(c){
    var v='v1.stats='+(c.stats?1:0)+'.ads='+(c.ads?1:0);
    document.cookie='emae_consent='+encodeURIComponent(v)+';max-age='+(182*86400)+';path=/;SameSite=Lax'+(location.protocol==='https:'?';Secure':'');
  }
  function wipe(){ /* retrait : suppression des cookies Google déjà posés */
    var host=location.hostname,doms=['',host,'.'+host,'.'+host.replace(/^www\./,'')];
    document.cookie.split(';').forEach(function(p){var n=p.split('=')[0].trim();
      if(/^(_ga|_gid|_gat|_gcl_|_gac_|FPID|FPAU)/.test(n))doms.forEach(function(d){document.cookie=n+'=;max-age=0;path=/'+(d?';domain='+d:'');});});
  }
  function apply(c){
    var before=window.emaeConsentRead?window.emaeConsentRead():null;
    write(c);b.hidden=true;
    var withdrawn=before&&((before.ads&&!c.ads)||(before.stats&&!c.stats));
    if(withdrawn){
      if(window.gtag)gtag('consent','update',{ad_storage:c.ads?'granted':'denied',ad_user_data:c.ads?'granted':'denied',ad_personalization:c.ads?'granted':'denied',analytics_storage:c.stats?'granted':'denied'});
      wipe();location.reload();return;
    }
    if(window.emaeLoadTags)window.emaeLoadTags(c);
  }
  function open(){
    var c=window.emaeConsentRead?window.emaeConsentRead():null;
    if(st)st.checked=!!(c&&c.stats);if(ad)ad.checked=!!(c&&c.ads);
    b.hidden=false;var f=b.querySelector('.ck-btn');if(f)f.focus();
  }
  b.addEventListener('click',function(ev){
    var k=ev.target&&ev.target.getAttribute('data-ck');if(!k)return;
    if(k==='accept')apply({stats:true,ads:true});
    else if(k==='refuse')apply({stats:false,ads:false});
    else if(k==='params'){opts.hidden=!opts.hidden;save.hidden=opts.hidden;par.setAttribute('aria-expanded',opts.hidden?'false':'true');}
    else if(k==='save')apply({stats:!!(st&&st.checked),ads:!!(ad&&ad.checked)});
  });
  document.addEventListener('click',function(ev){var o=ev.target.closest&&ev.target.closest('[data-ck-open]');if(o){ev.preventDefault();open();}});
  if(!(window.emaeConsentRead&&window.emaeConsentRead()))b.hidden=false;
})();
</script>
<?php
}

/* ── HEAD ── */
function render_head(array $meta): void
{
    $gaId   = setting('google_analytics_id','');
    $gAdsId = setting('google_ads_id','');
    $gAdsCv = setting('google_ads_conversion_label','');
    $gAdsQuote = setting('google_ads_quote_label','dpCcCNG397IcENeNhLFD');
    boot_session(); // avant toute sortie HTML
    // Google exige une adresse complète pour la page de référence.
    $meta['canonical'] = absolute_url((string)$meta['canonical']);

    echo '<!DOCTYPE html><html lang="fr"><head>';
    echo '<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">';
    echo '<title>'.e($meta['title']).'</title>';
    echo '<meta name="description" content="'.e($meta['description']).'">';
    echo '<link rel="canonical" href="'.e($meta['canonical']).'">';
    // Pages personnelles (suivi, espace client, paiement) : jamais dans Google.
    if (!empty($meta['robots'])) echo '<meta name="robots" content="'.e((string)$meta['robots']).'">';
    echo '<meta name="theme-color" content="#0A0C10">';
    echo '<meta property="og:title" content="'.e($meta['title']).'">';
    echo '<meta property="og:description" content="'.e($meta['description']).'">';
    echo '<meta property="og:type" content="website">';
    echo '<meta property="og:url" content="'.e($meta['canonical']).'">';
    $ogImg = $meta['og_image'] ?? setting('og_default_image','');
    if ($ogImg !== '') echo '<meta property="og:image" content="'.e(asset_url($ogImg)).'">';
    // Favicons
    // Google affiche dans ses résultats une icône carrée multiple de 48 px : la 192 px.
    if (file_exists(__DIR__.'/../assets/favicon-192.png'))
        echo '<link rel="icon" type="image/png" sizes="192x192" href="'.e(asset_url('assets/favicon-192.png')).'">';
    if (file_exists(__DIR__.'/../assets/favicon.png'))
        echo '<link rel="icon" type="image/png" sizes="32x32" href="'.e(asset_url('assets/favicon.png')).'">';
    if (file_exists(__DIR__.'/../assets/apple-touch-icon.png'))
        echo '<link rel="apple-touch-icon" href="'.e(asset_url('assets/apple-touch-icon.png')).'">';
    // Polices hébergées sur le site (assets/fonts) : aucune requête vers Google avant consentement.
    $fontsV = @filemtime(__DIR__.'/../assets/css/fonts.css') ?: 1;
    echo '<link rel="stylesheet" href="'.e(asset_url('assets/css/fonts.css')).'?v='.$fontsV.'">';

    $cssV = @filemtime(__DIR__.'/../assets/css/style.css') ?: time();
    echo '<link rel="stylesheet" href="'.e(asset_url('assets/css/style.css')).'?v='.$cssV.'">';
    echo theme_css_variables();
    // Schema.org
    echo '<script type="application/ld+json">'.schema_local_business().'</script>';
    // Google Analytics + Ads : rien n'est chargé avant le choix du visiteur (bandeau cookies).
    // Voir consent_head_script() : mode consentement v2 « refusé » par défaut, puis mise à jour
    // selon le choix, puis seulement chargement de gtag.js. Aucun événement n'est mis en file
    // pendant un refus : rien n'est rejoué si le visiteur accepte plus tard.
    $quoteConv = !empty($_SESSION['quote_conversion']);
    unset($_SESSION['quote_conversion']);   // une seule fois : ni rechargement ni retour arrière ne la renvoient
    echo consent_head_script($gaId, $gAdsId, $gAdsCv, $gAdsQuote, $quoteConv);
    // Numéro de version : le navigateur garde ce fichier un mois, le formulaire photos en dépend.
    $jsV = @filemtime(__DIR__.'/../assets/js/site.js') ?: time();
    echo '<script defer src="'.e(asset_url('assets/js/site.js')).'?v='.$jsV.'"></script>';
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

    render_availability_bar();
    if ($showTopbar): ?>
<div class="topbar">
  <div class="wrap topbar-in">
    <div class="topbar-l">
      <a href="<?= e($phoneLink) ?>" class="topbar-phone"><?= icon_svg('phone') ?> <?= e($phone) ?></a>
      <span class="topbar-dot">•</span>
      <a href="mailto:<?= e(company_email()) ?>"><?= e(company_email()) ?></a>
    </div>
    <div class="topbar-r">
      <span><?= icon_svg('pin') ?> <?= e(company_regions()) ?></span>
      <span class="topbar-dot">•</span>
      <span><?= icon_svg('clock') ?> <?= e(company_hours()) ?></span>
    </div>
  </div>
</div>
<?php endif; ?>

<header class="site-header">
  <div class="nav-wrap">
    <a class="brand" href="<?= e(route_url('')) ?>">
      <?php
        $logo = site_logo_path();
        $pngLogo = 'storage/uploads/logos/logo-emae.png';
        if ($logo === '' || $logo === 'storage/uploads/logos/logo-emae-default.svg') {
            if (file_exists(__DIR__.'/../'.$pngLogo)) $logo = $pngLogo;
        }
        $logoExists = trim($logo) !== '' && file_exists(__DIR__.'/../'.$logo);
      ?>
      <?php if ($logoExists && site_logo_is_emae($logo)): ?>
        <?php /* Logo EMAE : l'emblème et « EMAE » en image, la mention en vrai texte,
                 plus grande et nette que dans l'image où elle devenait illisible sur téléphone. */ ?>
        <span class="brand-emae">
          <img class="brand-mark" src="<?= e(asset_url('assets/img/logo-emae-mark.png')) ?>" alt="<?= e(company_name()) ?>" width="516" height="150" loading="eager" fetchpriority="high">
          <span class="brand-tag">Entreprise multitechnique<br>avancée</span>
        </span>
      <?php elseif ($logoExists): ?>
        <?php $logoSmall = image_resized_variant($logo, 440); /* affiché sur 220 px au plus : inutile d'envoyer plus grand */ ?>
        <img src="<?= e(asset_url($logoSmall['src'] ?? $logo)) ?>" alt="<?= e(company_name()) ?>"<?= $logoSmall ? ' width="'.(int)$logoSmall['w'].'" height="'.(int)$logoSmall['h'].'"' : '' ?> style="height:48px;width:auto;max-width:220px;object-fit:contain;" loading="eager" fetchpriority="high">
      <?php else: ?>
        <div>
          <div class="brand-name">EM<span>AE</span></div>
          <span class="brand-tagline">Entreprise Multitech Avancée</span>
        </div>
      <?php endif; ?>
    </a>

    <a class="header-call" href="<?= e($phoneLink) ?>"><?= icon_svg('phone') ?> <?= e($phone) ?></a>

    <button class="nav-toggle" type="button" aria-expanded="false" aria-label="Menu">
      <span></span><span></span><span></span>
    </button>

    <nav class="site-nav" id="main-nav">
      <?php foreach (nav_items() as $item): ?>
        <a class="<?= $active === $item['url'] ? 'on' : '' ?>" href="<?= e($item['url']) ?>"><?= e($item['label']) ?></a>
      <?php endforeach; ?>
      <a class="btn--cta" href="<?= e($ctaUrl) ?>"><?= e($ctaLabel) ?></a>
    </nav>
  </div>
</header>

<!-- Barre CTA fixe mobile -->
<div class="mob-bar">
  <a href="<?= e($phoneLink) ?>" class="mob-bar-call" aria-label="Appeler le <?= e($phone) ?>"><?= icon_svg('phone') ?> Appeler</a>
  <a href="<?= e(route_url('quote')) ?>" class="mob-bar-devis">📋 Devis &amp; intervention</a>
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
        <span class="ft-badge"><?= icon_svg('bolt') ?> Urgences 24h/7j</span>
        <span class="ft-badge"><?= icon_svg('lock') ?> Artisans qualifiés</span>
      </div>
    </div>
    <div class="ft-col">
      <h3>Nos services</h3>
      <ul>
        <li><a href="<?= e(route_url('electricite')) ?>">Électricité</a></li>
        <li><a href="<?= e(route_url('plomberie')) ?>">Plomberie</a></li>
        <li><a href="<?= e(route_url('chauffage')) ?>">Chauffage & PAC</a></li>
        <li><a href="<?= e(route_url('climatisation')) ?>">Climatisation CVC</a></li>
        <li><a href="<?= e(route_url('vmc')) ?>">VMC & Ventilation</a></li>
        <li><a href="<?= e(route_url('portail')) ?>">Portail & Visiophone</a></li>
        <li><a href="<?= e(route_url('automatisme')) ?>">Automatismes & Électroménager</a></li>
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
        <?php foreach ([
            ['pannes', 'Pannes fréquentes', pannes_enabled()], ['estimation', 'Estimer un prix', estimation_enabled()],
            ['rendez-vous', 'Prendre rendez-vous', booking_enabled()], ['entretien', 'Contrats d\'entretien', entretien_enabled()],
            ['aides', 'Aides et primes', aides_enabled()], ['espace-client', 'Espace client', client_space_enabled()],
        ] as [$_r, $_l, $_on]): if ($_on): ?>
        <li><a href="<?= e(route_url($_r)) ?>"><?= e($_l) ?></a></li>
        <?php endif; endforeach; unset($_r, $_l, $_on); ?>
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
        <a href="<?= e(route_url('mentions-legales')) ?>">Mentions légales</a>
        <?php $_ckt = consent_tags(); if ($_ckt['ga'] !== '' || $_ckt['ads'] !== ''): ?><a href="#" data-ck-open>Gestion des cookies</a><?php endif; unset($_ckt); ?>
      </div>
    </div>
  </div>
</footer>

<?php if (setting_bool('chatbot_enabled', false)): ?>
<div class="chat-widget" id="chat-widget"
     data-endpoint="<?= e(url_for('api/chat.php')) ?>"
     data-welcome="<?= e(setting('chatbot_welcome','Bonjour ! Je suis l\'assistant EMAE. Comment puis-je vous aider ?')) ?>">
  <div class="chat-box" id="chat-box">
    <div class="chat-head">
      <div class="chat-head-info">
        <span class="chat-head-dot"></span>
        <span class="chat-head-name">Assistant <?= e(company_name()) ?></span>
      </div>
      <button class="chat-close" id="chat-close" aria-label="Fermer">✕</button>
    </div>
    <div class="chat-msgs" id="chat-msgs"></div>
    <div class="chat-input-row">
      <input class="chat-input" id="chat-input" type="text" placeholder="Votre question…" maxlength="500" autocomplete="off">
      <button class="chat-send" id="chat-send">→</button>
    </div>
  </div>
  <button class="chat-btn" id="chat-btn" aria-label="Ouvrir le chat">
    <?= e(setting('chatbot_btn_label','💬')) ?>
  </button>
</div>
<?php endif; ?>

<?php $waUrl = whatsapp_url(); if ($waUrl !== ''): ?>
<a class="wa-btn" href="<?= e($waUrl) ?>" target="_blank" rel="noopener noreferrer" aria-label="Contacter par WhatsApp">
  <svg width="30" height="30" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
</a>
<?php endif; ?>
<?php render_consent_banner(); ?>
<script>
// Comptage interne des clics sur les liens d'appel et WhatsApp (compteur par jour,
// sans cookie ni donnée personnelle). sendBeacon part en tâche de fond : le
// téléphone s'ouvre immédiatement, sans attendre. Un clic n'est pas un appel abouti.
(function(){
  var zone = <?= json_encode(zone_ctx_slug(), JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?>;
  var url  = <?= json_encode(url_for('api/track.php'), JSON_UNESCAPED_SLASHES) ?>;
  var vus  = Object.create(null);
  document.addEventListener('click', function(ev){
    var a = ev.target.closest && ev.target.closest('a[href]');
    if (!a) return;
    var href = a.getAttribute('href') || '';
    var e = href.indexOf('tel:') === 0 ? 'appel'
          : (href.indexOf('wa.me') > -1 || href.indexOf('whatsapp') > -1) ? 'whatsapp' : '';
    if (!e || vus[e]) return;          // une fois par page, pas de double-clic compté
    vus[e] = 1;
    try {
      var d = new FormData(); d.append('e', e); d.append('z', zone);
      if (navigator.sendBeacon) navigator.sendBeacon(url, d);
      else fetch(url, {method:'POST', body:d, keepalive:true, credentials:'same-origin'});
    } catch (err) {}
  }, true);
})();
</script>
</body></html>
<?php
    // Alertes, demandes d'avis, rappels : lancés après l'envoi de la page (includes/automations.php).
    if (function_exists('automations_schedule')) automations_schedule();
}

/**
 * Badges de réassurance, posés au-dessus des formulaires de rappel. Seuls les engagements
 * documentés s'affichent : « Assurance décennale » et « RC professionnelle » n'apparaissent
 * que si l'assurance est renseignée (Identité & coordonnées → Mentions légales), et la note
 * Google seulement depuis la fiche (review_summary).
 */
function render_trust_badges(): string
{
    $b = [];
    if (preg_match('/7\s*j?\s*\/\s*7|7j\/7|24\s*h?\s*\/\s*24/u', company_hours())) $b[] = ['🕐', 'Urgences 7j/7'];
    $b[] = ['🆓', 'Devis gratuit'];
    $b[] = ['📋', 'Prix annoncé avant déplacement'];
    $L = legal_info();
    // Assurance : seulement pendant la validité de l'attestation (date de fin réglable dans l'admin).
    $ass = ($L['assurance_fin'] === '' || date('Y-m-d') <= $L['assurance_fin']) ? mb_strtolower($L['assurance'], 'UTF-8') : '';
    if (str_contains($ass, 'décennale') || str_contains($ass, 'decennale')) $b[] = ['🛡️', 'Assurance décennale'];
    if (preg_match('/responsabilit[ée] civile|\brc\s*pro/u', $ass)) $b[] = ['🛡️', 'RC professionnelle'];
    $out = '<ul class="trust-badges" aria-label="Nos engagements">';
    foreach ($b as [$i, $t]) $out .= '<li><span aria-hidden="true">'.$i.'</span> '.e($t).'</li>';
    $out .= '</ul>';
    if (($rs = review_summary()) !== null) {
        $out = '<a class="trust-rating" href="'.e($rs['url']).'" target="_blank" rel="noopener"><span aria-hidden="true">★</span> <strong>'
             .e(number_format($rs['rating'], 1, ',', '')).'/5</strong> · '.e((string)$rs['count']).' avis Google</a>'.$out;
    }
    return $out;
}

/* ── FORMULAIRE RÉUTILISABLE ── */
/** Champ d'envoi de photos, commun à tous les formulaires de demande (voir assets/js/site.js). */
function render_quote_photo_field(): void
{ ?>
  <div class="f-field f-photos" data-quote-photos data-max="<?= QUOTE_PHOTOS_MAX ?>">
    <span class="f-label">Photos de votre installation (facultatif)</span>
    <label class="f-photo-btn">
      <input class="f-photo-input" type="file" name="photos[]" accept="image/*" multiple>
      <span>📷 Ajouter des photos (<?= QUOTE_PHOTOS_MAX ?> maximum)</span>
    </label>
    <div class="f-photo-list" aria-live="polite"></div>
    <p class="f-photo-msg" hidden></p>
    <?php $keep = (int)growth_get('quote_photos_keep_months'); ?>
    <p class="f-hint">Vos photos servent uniquement à préparer l'intervention<?= $keep > 0 ? ' et sont supprimées automatiquement après '.$keep.' mois' : '' ?>.</p>
  </div>
<?php
}

/** Photo(s) d'une réalisation : avant et après côte à côte quand les deux existent. */
function render_realisation_media(array $r): void
{
    $title  = (string)($r['title'] ?? '');
    $after  = trim((string)($r['image_path'] ?? ''));
    $before = trim((string)($r['before_image_path'] ?? ''));
    $alt    = ['loading' => 'lazy'];
    if ($before !== '' && $after !== '') {
        echo '<div class="real-ba">'
            .'<div class="real-img">'.picture_html($before, $title.' — avant', $alt).'<span class="real-tag">Avant</span></div>'
            .'<div class="real-img">'.picture_html($after, $title.' — après', $alt).'<span class="real-tag real-tag--after">Après</span></div>'
            .'</div>';
    } elseif ($after !== '') {
        echo '<div class="real-img">'.picture_html($after, $title, $alt).'</div>';
    } elseif ($before !== '') {
        echo '<div class="real-img">'.picture_html($before, $title.' — avant', $alt).'<span class="real-tag">Avant</span></div>';
    } else {
        echo '<div class="real-placeholder">🔧</div>';
    }
}

/**
 * $type : « rappel » pour les encarts « Être rappelé », « devis » pour les autres.
 * $prefill : service et message déjà remplis (page Estimation, pages panne).
 */
function render_quote_form(array $cards, string $source = 'form', string $type = 'devis', array $prefill = []): void
{
    $placeholder = geo_replace(setting('home_quote_city_placeholder','Ex : {ville}…'));
    $submitLabel = quote_form_options()['submit_label'] ?? 'Envoyer ma demande';
    // Exemple de code postal pris dans le secteur de la page (25000, 39100…), pas à Paris.
    $cp = preg_match('/\d{2}/', function_exists('zone_var_value') ? zone_var_value('code_postal') : '', $m) ? $m[0] : '';
    $cpExemple = $cp !== '' ? 'Ex : '.$cp.'000' : 'Ex : 25000';
    $pageTrade = str_starts_with($source, 'service_') ? substr($source, 8) : (string)($prefill['trade'] ?? '');

    // Rappel express : téléphone et code postal suffisent, le reste se dit au téléphone.
    // Les champs sont dans des <label> : chaque libellé est lié à son champ sans identifiant
    // (deux formulaires sur la même page ne peuvent donc pas avoir d'id en double).
    if ($type === 'rappel') { ?>
<form action="<?= e(route_url('quote')) ?>" method="post" class="f-express">
  <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
  <input type="hidden" name="form_type" value="quote">
  <input type="hidden" name="request_type" value="rappel">
  <input type="hidden" name="source" value="<?= e($source) ?>">
  <?php if ($pageTrade !== '' && isset(service_trade_defaults()[$pageTrade])): ?><input type="hidden" name="service_type" value="<?= e(service_trade_defaults()[$pageTrade]['label']) ?>"><?php endif; ?>
  <input type="text" name="website" value="" class="hp" tabindex="-1" autocomplete="off" aria-hidden="true">
  <div class="f-row">
    <label class="f-field"><span class="f-label">Téléphone *</span><input class="f-input" type="tel" name="phone" placeholder="06 00 00 00 00" autocomplete="tel" inputmode="tel" pattern="[0-9 +.()-]{9,20}" required></label>
    <label class="f-field"><span class="f-label">Code postal *</span><input class="f-input" type="text" name="postal_code" placeholder="<?= e($cpExemple) ?>" autocomplete="postal-code" inputmode="numeric" pattern="[0-9]{5}" maxlength="5" required></label>
  </div>
  <label class="f-field"><span class="f-label">Votre besoin en quelques mots <span style="font-weight:400;opacity:.7;">(facultatif)</span></span><input class="f-input" type="text" name="message" maxlength="300" placeholder="Ex : plus de courant dans la cuisine" value="<?= e((string)($prefill['message'] ?? '')) ?>"></label>
  <label class="f-field"><span class="f-label">Nom <span style="font-weight:400;opacity:.7;">(facultatif)</span></span><input class="f-input" type="text" name="full_name" placeholder="Votre nom" autocomplete="name" maxlength="120"></label>
  <button class="btn btn-p btn-lg btn-block" type="submit">Demander à être rappelé</button>
  <p class="f-hint">Votre numéro sert uniquement à vous rappeler au sujet de cette demande. <?= e(callback_promise_short()) ?></p>
</form>
<?php return; }
    ?>
<form action="<?= e(route_url('quote')) ?>" method="post" enctype="multipart/form-data">
  <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
  <input type="hidden" name="form_type" value="quote">
  <input type="hidden" name="request_type" value="<?= e(quote_request_type($type)) ?>">
  <input type="hidden" name="source" value="<?= e($source) ?>">
  <input type="text" name="website" value="" class="hp" tabindex="-1" autocomplete="off" aria-hidden="true">
  <div class="f-row">
    <label class="f-field"><span class="f-label">Nom complet *</span><input class="f-input" type="text" name="full_name" placeholder="Votre nom" autocomplete="name" required></label>
    <label class="f-field"><span class="f-label">Téléphone *</span><input class="f-input" type="tel" name="phone" placeholder="06 00 00 00 00" autocomplete="tel" inputmode="tel" required></label>
  </div>
  <div class="f-row">
    <label class="f-field"><span class="f-label">Email</span><input class="f-input" type="email" name="email" placeholder="votre@email.fr" autocomplete="email" inputmode="email"></label>
    <label class="f-field"><span class="f-label">Code postal *</span><input class="f-input" type="text" name="postal_code" placeholder="<?= e($cpExemple) ?>" autocomplete="postal-code" inputmode="numeric" pattern="[0-9]{5}" maxlength="5" required></label>
  </div>
  <?php /* Adresse facultative : sur téléphone, chaque champ obligatoire en plus fait abandonner des visiteurs ; elle est demandée au rappel. */ ?>
  <label class="f-field"><span class="f-label">Adresse <span style="font-weight:400;opacity:.7;">(facultatif)</span></span><input class="f-input" type="text" name="address" placeholder="Ex : 12 rue de la République" autocomplete="street-address"></label>
  <label class="f-field"><span class="f-label">Ville</span><input class="f-input" type="text" name="city" placeholder="<?= e($placeholder) ?>" autocomplete="address-level2"></label>
  <label class="f-field">
    <span class="f-label">Service souhaité</span>
    <select class="f-input" name="service_type">
      <option value="">Choisir un service</option>
      <?php
      // Les cartes de l'accueil ne couvrent pas tous les métiers : on complète,
      // et on présélectionne le métier de la page (source « service_vmc »…).
      $titles = array_column($cards, 'title');
      foreach (['VMC & Ventilation', 'Portail & Visiophone', 'Automatismes & Électroménager'] as $extra) {
          if (!in_array($extra, $titles, true)) $titles[] = $extra;
      }
      foreach ($titles as $t):
          $sel = $pageTrade !== '' && service_detect_trade(text_ascii_lower($t)) === $pageTrade; ?>
      <option value="<?= e($t) ?>"<?= $sel ? ' selected' : '' ?>><?= e($t) ?></option><?php endforeach; ?>
    </select>
  </label>
  <label class="f-field">
    <span class="f-label">Urgence</span>
    <select class="f-input" name="urgency">
      <option value="Normale">Normale — intervention planifiée</option>
      <option value="Urgente">Urgente — dans les 24 h si possible</option>
      <option value="Très urgente">Très urgente — dès que possible</option>
    </select>
  </label>
  <label class="f-field">
    <span class="f-label">Votre besoin *</span>
    <textarea class="f-input f-textarea" name="message" placeholder="Décrivez votre panne ou votre demande..." required><?= e((string)($prefill['message'] ?? '')) ?></textarea>
  </label>
  <?php render_quote_photo_field(); ?>
  <button class="btn btn-p btn-lg btn-block" type="submit"><?= e($submitLabel) ?></button>
  <p class="f-meta">✓ Devis gratuit <span class="f-meta-dot"></span> ✓ Sans engagement <span class="f-meta-dot"></span> ✓ <?= e(callback_promise_short()) ?></p>
</form>
<?php
}