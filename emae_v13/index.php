<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/render.php';

$route = trim((string)($_GET['route'] ?? ''), '/');

/* ══ FORM SUBMISSION ══ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form_type'] ?? '') === 'quote') {
    verify_csrf();
    if (!rate_limit_passed('quote_submit', 8)) { flash('error','Merci de patienter quelques secondes.'); redirect_to('index.php?route='.$route); }
    if (trim((string)($_POST['website'] ?? '')) !== '') { redirect_to('index.php?route='.$route); }
    $fn = trim((string)($_POST['full_name'] ?? ''));
    $ph = trim((string)($_POST['phone'] ?? ''));
    $em = trim((string)($_POST['email'] ?? ''));
    $ci = trim((string)($_POST['city'] ?? ''));
    $sv = trim((string)($_POST['service_type'] ?? ''));
    $mg = trim((string)($_POST['message'] ?? ''));
    $ur = trim((string)($_POST['urgency'] ?? 'Normale'));
    $so = trim((string)($_POST['source'] ?? ''));
    if ($fn === '' || $ph === '' || $mg === '') { flash('error','Merci de remplir les champs obligatoires (nom, téléphone, besoin).'); redirect_to('index.php?route='.$route); }
    db_execute('INSERT INTO quotes (full_name,phone,email,city,service_type,message,urgency,status,source) VALUES (?,?,?,?,?,?,?,?,?)',
        [$fn,$ph,$em,$ci,$sv,$mg,$ur,'nouveau',$so]);
    flash('success', quote_form_options()['success_message']);
    redirect_to('index.php?route='.($route ?: 'home'));
}

/* ══════════════════════════════════════
   ACCUEIL
══════════════════════════════════════ */
if ($route === '' || $route === 'home') {
    $hero    = hero_settings();
    $cards   = home_cards();
    $reviews = visible_reviews(3);
    $exp     = home_expertise_settings();
    $banner  = hero_banner_settings();
    $zone    = home_zone_settings();
    $reals   = visible_realisations(3);
    $meta    = seo_defaults('home');

    $btn1 = trim($hero['button1_url']);
    if ($btn1 !== '' && !preg_match('#^(https?:|tel:|mailto:|/)#i',$btn1)) $btn1 = route_url($btn1);
    $btn2 = trim($hero['button2_url']) ?: company_phone_link();
    if ($btn2 !== '' && !preg_match('#^(https?:|tel:|mailto:|/)#i',$btn2)) $btn2 = route_url($btn2);

    render_head($meta);
    render_header(route_url(''));
?>

<!-- ══ HERO ══ -->
<section class="hero">
  <div class="container hero__grid">

    <!-- Contenu gauche -->
    <div class="hero__content">
      <div class="hero__urgency-badge">Disponible <?= e(company_hours()) ?></div>
      <h1><?= nl2br(e(setting('home_title','Votre expert\nmultitech en\n<span>urgence</span>'))) ?></h1>
      <p class="hero__lead"><?= e(setting('home_lead','Dépannage électrique, plomberie, chauffage, climatisation et pompes à chaleur en Île-de-France et Occitanie. Intervention rapide, devis gratuit, artisans qualifiés.')) ?></p>

      <!-- Services chips -->
      <div class="hero__services">
        <?php $chips = array_filter([setting('home_chip_1','Électricité'),setting('home_chip_2','Plomberie'),setting('home_chip_3','Chauffage'),setting('home_chip_4','PAC'),setting('home_chip_5','Climatisation'),setting('home_chip_6','CVC')], fn($v)=>trim($v)!==''); ?>
        <?php foreach ($chips as $i=>$chip): ?><span class="<?= $i < 2 ? 'active' : '' ?>"><?= e($chip) ?></span><?php endforeach; ?>
      </div>

      <!-- CTA buttons -->
      <div class="hero__actions">
        <a class="btn btn--primary btn--large" href="<?= e($btn1 ?: route_url('quote')) ?>">📋 <?= e(setting('home_button1_label','Devis gratuit en 2 min')) ?></a>
        <a class="btn btn--ghost btn--large" href="<?= e($btn2) ?>">📞 <?= e(setting('home_button2_label','Appeler maintenant')) ?></a>
      </div>

      <!-- Trust bar -->
      <div class="hero__trust">
        <?php $trusts = [
          ['⚡','Intervention rapide','Moins de 2h en urgence'],
          ['🆓','Devis gratuit','Sans engagement'],
          ['⭐','4.9/5','Plus de 120 avis'],
          ['🔒','Certifiés','Artisans qualifiés'],
        ]; foreach($trusts as $t): ?>
        <div class="hero__trust-item">
          <div class="hero__trust-icon"><?= $t[0] ?></div>
          <div class="hero__trust-text"><strong><?= e($t[1]) ?></strong><?= e($t[2]) ?></div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Formulaire droite -->
    <div class="hero__visual">
      <div class="quote-card">
        <div class="quote-card__badge">⚡ <?= e(setting('home_quote_eyebrow','Rappel gratuit sous 30 min')) ?></div>
        <h2><?= e(setting('home_quote_title','Obtenir un devis')) ?></h2>
        <?php render_quote_form($cards, 'hero'); ?>
      </div>
    </div>
  </div>
</section>

<!-- ══ BANDE URGENCE ══ -->
<div class="urgency-strip">
  <div class="container urgency-strip__inner">
    <div class="urgency-strip__item">⚡ Urgence électrique 24h/24</div>
    <div class="urgency-strip__sep"></div>
    <div class="urgency-strip__item">💧 Fuite d'eau — intervention immédiate</div>
    <div class="urgency-strip__sep"></div>
    <div class="urgency-strip__item">🔥 Panne chauffage / PAC</div>
    <div class="urgency-strip__sep"></div>
    <div class="urgency-strip__item">❄️ Climatisation en panne</div>
    <div class="urgency-strip__sep"></div>
    <div class="urgency-strip__item">📞 <?= e(company_phone()) ?></div>
  </div>
</div>

<!-- ══ NOS SERVICES ══ -->
<section class="services-section">
  <div class="container">
    <div class="section-label">Nos pôles d'intervention</div>
    <h2 class="services-heading">Tout ce dont vous avez <span>besoin</span></h2>
    <div class="services-grid">
      <?php foreach ($cards as $card):
        $link = trim($card['link'] ?? 'services');
        if (!preg_match('#^(https?:|/)#i',$link)) $link = route_url($link);
      ?>
      <a class="service-card" href="<?= e($link) ?>">
        <div class="service-card__arrow">→</div>
        <div class="service-card__icon"><?= e($card['icon'] ?? '🔧') ?></div>
        <div class="service-card__name"><?= e($card['title']) ?></div>
        <div class="service-card__desc"><?= e($card['desc'] ?? 'Dépannage • Installation • Entretien • Mise aux normes') ?></div>
        <div class="service-card__tags">
          <span>Urgence</span><span>Installation</span><span>Entretien</span>
        </div>
      </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ══ POURQUOI EMAE ══ -->
<section class="trust-section">
  <div class="container">
    <div class="eyebrow-line">Pourquoi nous choisir</div>
    <h2 class="section-title">EMAE, <span>la référence</span> du dépannage multitechnique</h2>
    <p class="section-sub">Fondée pour répondre aux urgences techniques avec rigueur et rapidité. Chaque intervention est documentée, chaque devis est clair, chaque technicien est qualifié.</p>

    <div class="trust-grid">
      <?php $trusts = [
        ['⚡','Réponse immédiate','Nous décrochez toujours. Délai moyen d\'intervention inférieur à 2h pour les urgences en Île-de-France.'],
        ['📋','Devis clair avant intervention','Le prix est annoncé avant toute action. Zéro surprise sur la facture finale.'],
        ['🔒','Techniciens qualifiés','Nos artisans sont formés, certifiés et expérimentés sur l\'ensemble de nos métiers.'],
        ['📍','Présence locale','Île-de-France et Occitanie. Nous connaissons votre secteur et ses spécificités.'],
        ['🛠️','Tous types d\'interventions','Urgence, installation, entretien préventif, mise aux normes — un seul interlocuteur.'],
        ['⭐','Clients satisfaits','Plus de 120 avis vérifiés, une note de 4.9/5. La confiance se construit chantier après chantier.'],
        ['🏗️','Particuliers & professionnels','Logements, commerces, bureaux, copropriétés — nous adaptons notre organisation à votre contexte.'],
        ['📞','Disponible 24h/7j','L\'urgence n\'attend pas. Notre astreinte est active en permanence pour les situations bloquantes.'],
      ]; foreach ($trusts as $t): ?>
      <div class="trust-item">
        <div class="trust-item__icon"><?= $t[0] ?></div>
        <div class="trust-item__body"><h3><?= e($t[1]) ?></h3><p><?= e($t[2]) ?></p></div>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- Stats -->
    <div class="stats-band">
      <div class="container stats-grid">
        <?php foreach ($banner['stats'] as $s): ?>
        <div class="stat-item">
          <strong class="stat-item__number"><?= e($s['number']) ?></strong>
          <span class="stat-item__label"><?= e($s['label']) ?></span>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</section>

<!-- ══ PROCESSUS ══ -->
<section class="process-section">
  <div class="container">
    <div class="eyebrow-line">Comment ça marche</div>
    <h2 class="section-title">Votre intervention en <span>3 étapes</span></h2>
    <div class="process-grid">
      <?php $steps = [
        ['01','Vous nous contactez','Appelez-nous ou remplissez le formulaire. Nous répondons immédiatement et qualifions votre besoin en moins de 5 minutes.'],
        ['02','Nous organisons l\'intervention','Un technicien qualifié est envoyé sur votre site. Le délai et le tarif estimé sont confirmés avant déplacement.'],
        ['03','Intervention & compte rendu','Diagnostic, réparation ou installation. Vous recevez un compte rendu clair et une facture détaillée.'],
      ]; foreach ($steps as $s): ?>
      <div class="process-step">
        <div class="process-step__num"><?= $s[0] ?></div>
        <div><h3><?= e($s[1]) ?></h3><p><?= e($s[2]) ?></p></div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ══ RÉALISATIONS ══ -->
<?php if (!empty($reals)): ?>
<div class="divider"></div>
<section class="trust-section">
  <div class="container">
    <div class="eyebrow-line">Nos réalisations</div>
    <h2 class="section-title">Interventions <span>récentes</span></h2>
    <div class="reals-grid">
      <?php foreach ($reals as $r): ?>
      <div class="real-card">
        <?php if (trim((string)($r['image_path']??'')) !== ''): ?>
          <div class="real-card__img"><img src="<?= e(asset_url($r['image_path'])) ?>" alt="<?= e($r['title']) ?>"></div>
        <?php else: ?>
          <div class="real-card__placeholder">🔧</div>
        <?php endif; ?>
        <div class="real-card__body">
          <?php if (trim((string)($r['service_type']??'')) !== ''): ?><div class="real-card__service"><?= e($r['service_type']) ?></div><?php endif; ?>
          <h3><?= e($r['title']) ?></h3>
          <?php if (trim((string)($r['city']??'')) !== ''): ?><div class="real-card__city">📍 <?= e($r['city']) ?></div><?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <div style="text-align:center;margin-top:2rem;">
      <a class="btn btn--ghost" href="<?= e(route_url('realisations')) ?>">Voir toutes nos réalisations →</a>
    </div>
  </div>
</section>
<?php endif; ?>

<!-- ══ AVIS CLIENTS ══ -->
<section class="reviews-section">
  <div class="container">
    <div class="reviews-header">
      <div>
        <div class="eyebrow-line">Avis clients</div>
        <h2 class="section-title">Ce qu'ils disent <span>de nous</span></h2>
      </div>
      <div class="reviews-score">
        <div>
          <div class="reviews-score__stars">★★★★★</div>
          <div class="reviews-score__num"><?= e(setting('schema_rating_value','4.9')) ?></div>
          <div class="reviews-score__info"><?= e(setting('schema_review_count','120')) ?> avis vérifiés</div>
        </div>
      </div>
    </div>
    <div class="reviews-grid">
      <?php foreach ($reviews as $rev):
        $initials = mb_strtoupper(mb_substr($rev['author_name'],0,1,'UTF-8'),'UTF-8');
      ?>
      <div class="review-card">
        <div class="review-card__stars"><?= str_repeat('★',(int)$rev['rating']) ?></div>
        <p class="review-card__text">"<?= e($rev['content']) ?>"</p>
        <div class="review-card__author">
          <div class="review-card__avatar"><?= e($initials) ?></div>
          <div>
            <span class="review-card__name"><?= e($rev['author_name']) ?></span>
            <?php if (trim((string)($rev['service_type']??'')) !== ''): ?><span class="review-card__service"><?= e($rev['service_type']) ?><?php if(trim((string)($rev['city']??''))!==''): ?> — <?= e($rev['city']) ?><?php endif; ?></span><?php endif; ?>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ══ DEVIS SECTION ══ -->
<section class="quote-section">
  <div class="container quote-section__grid">
    <div class="quote-section__info">
      <div class="eyebrow-line">Devis gratuit</div>
      <h2>Besoin d'un <span>technicien ?</span></h2>
      <p>Décrivez votre besoin, nous vous rappelons sous 30 minutes avec un chiffrage clair. Aucune visite facturée sans votre accord.</p>
      <div class="quote-perks">
        <?php foreach(['Devis 100% gratuit et sans engagement','Rappel sous 30 minutes garanti','Tarif annoncé avant toute intervention','Facture détaillée en fin de chantier','Technicien qualifié et assuré','Disponible 7j/7, urgences 24h/24'] as $p): ?>
        <div class="quote-perk"><?= e($p) ?></div>
        <?php endforeach; ?>
      </div>
      <div class="btn-row">
        <a class="btn btn--primary" href="<?= e(company_phone_link()) ?>">📞 <?= e(company_phone()) ?></a>
        <a class="btn btn--ghost" href="mailto:<?= e(company_email()) ?>">✉️ <?= e(company_email()) ?></a>
      </div>
    </div>
    <div class="quote-card">
      <div class="quote-card__badge">📋 Formulaire de contact</div>
      <h2>Votre demande</h2>
      <?php render_quote_form($cards, 'home_section'); ?>
    </div>
  </div>
</section>

<!-- ══ ZONES ══ -->
<section class="zones-section">
  <div class="container">
    <div class="eyebrow-line">Zones d'intervention</div>
    <h2 class="section-title">Nous intervenons <span>près de chez vous</span></h2>
    <p class="section-sub">EMAE est présent en Île-de-France et en Occitanie. Délai moyen d'intervention inférieur à 2 heures pour les urgences dans nos zones principales.</p>
    <div class="zones-grid">
      <div class="zone-card">
        <h3>🗺️ Île-de-France</h3>
        <p>Intervention rapide dans tout le Grand Paris et la petite couronne. Zone privilégiée pour les urgences avec délai garanti.</p>
        <div class="zone-cities">
          <?php foreach(['Paris (75)','Meaux (77)','Versailles (78)','Évry (91)','Nanterre (92)','Saint-Denis (93)','Créteil (94)','Cergy (95)','Marne-la-Vallée','Melun','Pontoise'] as $c): ?>
          <span><?= e($c) ?></span>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="zone-card">
        <h3>🗺️ Occitanie</h3>
        <p>Présent dans les principales villes d'Occitanie pour vos besoins planifiés et urgents.</p>
        <div class="zone-cities">
          <?php foreach(['Toulouse (31)','Montpellier (34)','Nîmes (30)','Perpignan (66)','Béziers (34)','Narbonne (11)','Carcassonne (11)','Albi (81)','Castres (81)'] as $c): ?>
          <span><?= e($c) ?></span>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
    <div style="text-align:center;margin-top:2.5rem;">
      <a class="btn btn--primary" href="<?= e(route_url('contact')) ?>">Vérifier ma zone →</a>
    </div>
  </div>
</section>

<?php render_footer(); exit;
}

/* ══════════════════════════════════════
   FAQ
══════════════════════════════════════ */
if ($route === 'faq') {
    $faq  = faq_page_settings();
    $meta = ['title'=>setting('faq_meta_title','FAQ | '.company_name()),'description'=>setting('faq_meta_description','Questions fréquentes sur nos services EMAE.'),'canonical'=>route_url('faq')];
    render_head($meta); render_header(route_url('faq'));
?>
<section class="page-hero">
  <div class="container page-hero__content">
    <div class="page-hero__eyebrow">Questions fréquentes</div>
    <h1><?= e($faq['hero_title']) ?></h1>
    <p><?= e($faq['hero_lead']) ?></p>
    <div class="page-hero__badges">
      <span class="orange">⚡ Réponse rapide</span>
      <span>Urgences 24h/7j</span>
      <span>Devis gratuit</span>
    </div>
  </div>
</section>
<section style="background:var(--dark);padding:3rem 0;">
<div class="container">
  <div class="faq-cats" id="faq-cats">
    <button class="faq-cat active" data-cat="all">Toutes</button>
    <?php foreach ($faq['groups'] as $g): ?><button class="faq-cat" data-cat="<?= e(mb_strtolower($g['category']??'','UTF-8')) ?>"><?= e($g['category']??'') ?></button><?php endforeach; ?>
  </div>
  <?php foreach ($faq['groups'] as $g): ?>
  <div class="faq-group" data-group="<?= e(mb_strtolower($g['category']??'','UTF-8')) ?>">
    <div class="faq-group-title">// <?= e($g['category']??'') ?></div>
    <div class="faq-list">
      <?php foreach (($g['items']??[]) as $item): ?>
      <div class="faq-item">
        <div class="faq-q"><?= e($item['q']) ?><span class="faq-q__plus">+</span></div>
        <div class="faq-a"><?= e($item['a']) ?></div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endforeach; ?>
  <div class="faq-cta">
    <h2><?= e($faq['cta_title']) ?></h2>
    <p><?= e($faq['cta_lead']) ?></p>
    <div class="actions">
      <a class="btn btn--primary btn--large" href="<?= e(route_url($faq['cta_url'])) ?>"><?= e($faq['cta_button']) ?></a>
      <a class="btn btn--ghost btn--large" href="<?= e(company_phone_link()) ?>">📞 <?= e(company_phone()) ?></a>
    </div>
  </div>
</div>
</section>
<?php render_footer(); exit;
}

/* ══════════════════════════════════════
   CONTACT
══════════════════════════════════════ */
if ($route === 'contact') {
    $cfg   = contact_page_settings();
    $cards = home_cards();
    $meta  = ['title'=>setting('contact_meta_title','Contact | '.company_name()),'description'=>setting('contact_meta_description','Contactez EMAE pour un devis ou une urgence.'),'canonical'=>route_url('contact')];
    render_head($meta); render_header(route_url('contact'));
?>
<section class="page-hero">
  <div class="container page-hero__content">
    <div class="page-hero__eyebrow"><?= e($cfg['hero_eyebrow']) ?></div>
    <h1><?= e($cfg['hero_title']) ?></h1>
    <p><?= e($cfg['hero_lead']) ?></p>
    <div class="page-hero__badges">
      <span class="orange">✓ Devis gratuit</span>
      <span>⚡ Réponse sous 30 min</span>
      <span>📍 <?= e(company_regions()) ?></span>
      <span>🕐 <?= e(company_hours()) ?></span>
    </div>
  </div>
</section>
<section style="background:var(--dark);padding:3rem 0;">
<div class="container contact-wrap">
  <div class="contact-form-box">
    <h2><?= e($cfg['form_title']) ?></h2>
    <p><?= e($cfg['form_subtitle']) ?></p>
    <form action="<?= e(route_url('contact')) ?>" method="post" class="cform">
      <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="form_type" value="quote">
      <input type="hidden" name="source" value="contact_page">
      <input type="text" name="website" value="" class="hp-field" tabindex="-1" autocomplete="off">
      <div class="cform-row">
        <label>Nom complet *<input type="text" name="full_name" placeholder="Votre nom" required></label>
        <label>Téléphone *<input type="tel" name="phone" placeholder="06 00 00 00 00" required></label>
      </div>
      <div class="cform-row">
        <label>Email<input type="email" name="email" placeholder="votre@email.fr"></label>
        <label>Ville<input type="text" name="city" placeholder="Meaux, Paris, Toulouse…"></label>
      </div>
      <label>Service souhaité<select name="service_type"><option value="">Choisir</option><?php foreach($cards as $c):?><option value="<?=e($c['title'])?>"><?=e($c['title'])?></option><?php endforeach;?></select></label>
      <label>Urgence<select name="urgency"><option value="Normale">Normale</option><option value="Urgente">Urgente — sous 24h</option><option value="Très urgente">Très urgente — immédiat</option></select></label>
      <label>Votre besoin *<textarea name="message" rows="5" placeholder="Décrivez votre panne ou votre demande..." required></textarea></label>
      <button class="btn btn--primary btn--block btn--large" type="submit"><?= e(quote_form_options()['submit_label']) ?></button>
      <p class="quote-meta">✓ Gratuit <span></span> ✓ Sans engagement <span></span> ✓ Réponse rapide</p>
    </form>
  </div>
  <div class="contact-sidebar">
    <div class="contact-info-box">
      <h3><?= e($cfg['info_title']) ?></h3>
      <div class="info-item"><div class="info-icon">📞</div><div class="info-text"><strong>Téléphone</strong><a href="<?= e(company_phone_link()) ?>"><?= e(company_phone()) ?></a></div></div>
      <div class="info-item"><div class="info-icon">✉️</div><div class="info-text"><strong>Email</strong><a href="mailto:<?= e(company_email()) ?>"><?= e(company_email()) ?></a></div></div>
      <div class="info-item"><div class="info-icon">📍</div><div class="info-text"><strong>Zones</strong><span><?= e(company_regions()) ?></span></div></div>
      <div class="info-item"><div class="info-icon">🕐</div><div class="info-text"><strong>Disponibilité</strong><span><?= e(company_hours()) ?></span></div></div>
      <?php if(company_siret()!==''):?><div class="info-item"><div class="info-icon">🏢</div><div class="info-text"><strong>SIRET</strong><span><?= e(company_siret()) ?></span></div></div><?php endif;?>
    </div>
    <div class="urgency-box">
      <h3><?= e($cfg['urgency_title']) ?></h3>
      <p><?= e($cfg['urgency_text']) ?></p>
      <a class="btn btn--primary btn--block" href="<?= e(company_phone_link()) ?>">📞 <?= e(company_phone()) ?></a>
    </div>
    <div class="contact-info-box">
      <h3><?= e($cfg['zones_title']) ?></h3>
      <div class="zones-tags-box">
        <?php foreach(['Paris (75)','Meaux (77)','Versailles (78)','Évry (91)','Nanterre (92)','Saint-Denis (93)','Créteil (94)','Cergy (95)','Toulouse','Montpellier','Nîmes','Occitanie'] as $z):?>
        <div class="zone-tag"><?= e($z) ?></div>
        <?php endforeach;?>
      </div>
    </div>
  </div>
</div>
</section>
<?php render_footer(); exit;
}

/* ══════════════════════════════════════
   DEVIS
══════════════════════════════════════ */
if ($route === 'quote') {
    $cards = home_cards();
    $meta  = ['title'=>setting('quote_meta_title','Devis gratuit | '.company_name()),'description'=>setting('quote_meta_description','Devis gratuit électricité, plomberie, chauffage, climatisation.'),'canonical'=>route_url('quote')];
    render_head($meta); render_header(route_url('quote'));
?>
<section class="page-hero">
  <div class="container page-hero__content">
    <div class="page-hero__eyebrow">Devis gratuit</div>
    <h1>Votre demande d'intervention</h1>
    <p>Remplissez ce formulaire. Un technicien vous rappelle sous 30 minutes avec un chiffrage clair, sans engagement.</p>
    <div class="page-hero__badges"><span class="orange">✓ 100% gratuit</span><span>⚡ Réponse sous 30 min</span><span>📍 <?= e(company_regions()) ?></span></div>
  </div>
</section>
<section style="background:var(--dark);padding:3rem 0;">
<div class="container" style="max-width:640px;">
  <div class="quote-card">
    <div class="quote-card__badge">📋 Formulaire de devis</div>
    <h2>Décrivez votre besoin</h2>
    <?php render_quote_form($cards,'quote_page'); ?>
  </div>
</div>
</section>
<?php render_footer(); exit;
}

/* ══════════════════════════════════════
   RÉALISATIONS
══════════════════════════════════════ */
if ($route === 'realisations') {
    $reals = all_realisations();
    $meta  = ['title'=>setting('realisations_meta_title','Réalisations | '.company_name()),'description'=>setting('realisations_meta_description','Nos interventions et chantiers en Île-de-France et Occitanie.'),'canonical'=>route_url('realisations')];
    render_head($meta); render_header(route_url('realisations'));
?>
<section class="page-hero">
  <div class="container page-hero__content">
    <div class="page-hero__eyebrow">Nos réalisations</div>
    <h1>Interventions & chantiers</h1>
    <p>Des interventions propres et documentées en <?= e(company_regions()) ?>. Chaque chantier est réalisé par des techniciens qualifiés.</p>
  </div>
</section>
<section style="background:var(--dark);padding:3rem 0;">
<div class="container">
  <?php if (empty($reals)): ?>
  <div style="text-align:center;padding:4rem 0;color:var(--text-2);">
    <div style="font-size:3rem;margin-bottom:1rem;">📷</div>
    <p>Les réalisations seront publiées prochainement.</p>
    <a class="btn btn--primary" href="<?= e(route_url('contact')) ?>" style="margin-top:1.5rem;display:inline-flex;">Nous contacter</a>
  </div>
  <?php else: ?>
  <div class="reals-grid">
    <?php foreach ($reals as $r): ?>
    <div class="real-card">
      <?php if(trim((string)($r['image_path']??''))!==''):?><div class="real-card__img"><img src="<?= e(asset_url($r['image_path'])) ?>" alt="<?= e($r['title']) ?>"></div><?php else:?><div class="real-card__placeholder">🔧</div><?php endif;?>
      <div class="real-card__body">
        <?php if(trim((string)($r['service_type']??''))!==''):?><div class="real-card__service"><?= e($r['service_type']) ?></div><?php endif;?>
        <h3><?= e($r['title']) ?></h3>
        <?php if(trim((string)($r['description']??''))!==''):?><p style="font-size:.82rem;color:var(--text-2);margin:.3rem 0 0;"><?= e($r['description']) ?></p><?php endif;?>
        <?php if(trim((string)($r['city']??''))!==''):?><div class="real-card__city">📍 <?= e($r['city']) ?></div><?php endif;?>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>
</section>
<?php render_footer(); exit;
}

/* ══════════════════════════════════════
   PAGES SERVICE (dynamiques)
══════════════════════════════════════ */
$page = page_by_slug($route);
if ($page) {
    $ctx = mb_strtolower($route.' '.($page['slug']??'').' '.($page['title']??''),'UTF-8');
    $sk = null;
    if (str_contains($ctx,'electric')||str_contains($ctx,'electri')) $sk='electricite';
    elseif (str_contains($ctx,'plomb')) $sk='plomberie';
    elseif (str_contains($ctx,'chauff')||str_contains($ctx,'chaudiere')||str_contains($ctx,'pac')||str_contains($ctx,'pompe')) $sk='chauffage';
    elseif (str_contains($ctx,'clim')||str_contains($ctx,'cvc')||str_contains($ctx,'ventil')) $sk='climatisation';

    $tpls = [
        'electricite'=>['label'=>'Électricité','icon'=>'⚡','desc'=>'Dépannage, installation, mise aux normes et rénovation électrique en '.company_regions().'.','badges'=>['Dépannage urgent','Tableau électrique','Mise aux normes','Rénovation'],
            'interventions'=>[['⚡','Panne électrique','Diagnostic et remise en service rapide. Intervention prioritaire pour les coupures totales ou partielles.'],['🧰','Tableau électrique','Remplacement, sécurisation et mise en conformité de tableaux et protections.'],['🔌','Prises & circuits','Ajout, réparation ou remplacement de prises, lignes dédiées et circuits défaillants.'],['💡','Éclairage','Dépannage, installation LED, éclairage intérieur, extérieur et technique.'],['🏗️','Installation complète','Câblage neuf ou réfection pour logement, commerce ou bâtiment professionnel.'],['✅','Mise aux normes','Remise en conformité réglementaire et sécurisation des installations existantes.'],['🔄','Rénovation','Modernisation de l\'installation électrique pour plus de sécurité et d\'efficacité.'],['🚨','Urgence 24h/7j','Astreinte permanente pour limiter l\'impact des pannes bloquantes.']],
            'faq'=>[['Quel est le délai d\'intervention ?','En urgence, nous intervenons en moins de 2h en Île-de-France. Le délai est confirmé au téléphone selon votre zone.'],['Faites-vous les mises aux normes NF C 15-100 ?','Oui, nous réalisons la mise en conformité complète selon les normes en vigueur.'],['Intervenez-vous sur les bâtiments professionnels ?','Oui, logements, commerces, bureaux et bâtiments techniques.'],['Le devis est-il gratuit ?','Oui, devis gratuit et sans engagement avant toute intervention.']]],
        'plomberie'=>['label'=>'Plomberie','icon'=>'💧','desc'=>'Dépannage fuite, réparation sanitaire et entretien réseau en '.company_regions().'.','badges'=>['Fuite urgente','Débouchage','Sanitaires','Entretien'],
            'interventions'=>[['💧','Recherche de fuite','Localisation précise et réparation de fuites visibles ou cachées.'],['🚿','Sanitaires','WC, robinetterie, évacuations, siphons — réparation et remplacement.'],['🧯','Urgence plomberie','Mise en sécurité immédiate pour limiter les dégâts d\'eau.'],['🔩','Réseaux','Intervention sur tuyauteries, alimentations et raccordements.'],['🛁','Équipements','Remplacement d\'évier, baignoire, douche, robinet, chauffe-eau.'],['🧼','Entretien préventif','Maintenance courante pour prévenir les fuites et dysfonctionnements.'],['🏢','Locaux professionnels','Organisation adaptée pour intervenir sans bloquer votre activité.'],['📋','Rapport d\'intervention','Compte rendu détaillé et facturation claire en fin de chantier.']],
            'faq'=>[['Intervenez-vous en urgence pour une fuite ?','Oui, disponible '.company_hours().'. Appelez le '.company_phone().' pour une intervention immédiate.'],['Faites-vous le remplacement de chauffe-eau ?','Oui, diagnostic, remplacement et mise en service de tous types de chauffe-eau.'],['Proposez-vous un contrat d\'entretien ?','Oui, nous proposons des contrats de maintenance préventive annuels.'],['Que faire en cas de fuite importante ?','Coupez l\'arrivée d\'eau principale et appelez-nous immédiatement.']]],
        'chauffage'=>['label'=>'Chauffage & PAC','icon'=>'🔥','desc'=>'Dépannage chaudière, pompe à chaleur et entretien chauffage en '.company_regions().'.','badges'=>['Panne chaudière','PAC','Dépannage','Entretien'],
            'interventions'=>[['🔥','Panne chaudière','Diagnostic et remise en service de chaudières gaz, fioul ou électriques.'],['♨️','Pompe à chaleur','Dépannage, entretien et installation de PAC air/air et air/eau.'],['🌡️','Régulation','Contrôle et réglage de thermostats, sondes et organes de commande.'],['🛠️','Réglages & optimisation','Amélioration des performances et du confort thermique.'],['🏗️','Installation','Pose et mise en service d\'équipements de chauffage neufs.'],['📊','Entretien annuel','Contrat d\'entretien pour prévenir les pannes et optimiser la consommation.'],['🚨','Urgence hiver','Astreinte renforcée en période froide pour éviter toute coupure de chauffage.'],['📋','Compte rendu','Rapport d\'intervention et recommandations systématiques.']],
            'faq'=>[['Intervenez-vous sur les chaudières gaz et fioul ?','Oui, sur tous types de chaudières : gaz, fioul, électrique et condensation.'],['Proposez-vous l\'installation de pompe à chaleur ?','Oui, PAC air/air, air/eau — installation, entretien et dépannage.'],['Faites-vous l\'entretien annuel de chaudière ?','Oui, contrat d\'entretien annuel réglementaire avec rapport d\'intervention.'],['Que faire si mon chauffage tombe en panne en hiver ?','Appelez-nous immédiatement au '.company_phone().' — priorité aux urgences de chauffage.']]],
        'climatisation'=>['label'=>'Climatisation & CVC','icon'=>'❄️','desc'=>'Dépannage, installation et entretien de climatisation et CVC en '.company_regions().'.','badges'=>['Clim en panne','CVC','Installation','Entretien saisonnier'],
            'interventions'=>[['❄️','Panne climatisation','Diagnostic et remise en service de tout type de climatiseur.'],['🌬️','Qualité de soufflage','Contrôle du fonctionnement et optimisation de la diffusion d\'air.'],['🧼','Entretien saisonnier','Nettoyage, vérifications et réglages avant saison estivale ou hivernale.'],['🏗️','Installation','Pose de splits, multi-splits et systèmes gainables.'],['📈','Contrôle performances','Vérification des performances après intervention ou installation.'],['🛠️','Réglages','Optimisation des paramètres pour plus de confort et d\'économies.'],['🚨','Urgence','Prise en charge prioritaire des pannes bloquantes.'],['📋','Compte rendu','Rapport systématique et recommandations d\'utilisation.']],
            'faq'=>[['Intervenez-vous sur tous types de climatiseurs ?','Oui, splits, multi-splits, gainables et systèmes CVC pour particuliers et professionnels.'],['Proposez-vous l\'installation de climatisation ?','Oui, fourniture, pose et mise en service avec conseil adapté à votre logement.'],['Quand faire l\'entretien de sa climatisation ?','Idéalement avant chaque saison (printemps et automne) pour garantir les performances.'],['Intervenez-vous pour les entreprises ?','Oui, commerces, bureaux, restaurants — intervention compatible avec votre exploitation.']]],
    ];

    if ($sk && isset($tpls[$sk])) {
        $tpl   = $tpls[$sk];
        $meta  = seo_defaults($route,$page);
        $cards = home_cards();
        render_head($meta); render_header(route_url($route));
?>
<section class="page-hero">
  <div class="container page-hero__content">
    <div class="page-hero__eyebrow">// <?= e($tpl['label']) ?></div>
    <h1><?= e($page['title']) ?></h1>
    <p><?= e($page['excerpt'] ?: $tpl['desc']) ?></p>
    <div class="page-hero__badges">
      <?php foreach ($tpl['badges'] as $b): ?><span class="orange"><?= e($b) ?></span><?php endforeach; ?>
      <span><?= e(company_hours()) ?></span>
    </div>
    <div class="btn-row" style="margin-top:1.5rem;">
      <a class="btn btn--primary btn--large" href="<?= e(route_url('quote')) ?>">Devis gratuit</a>
      <a class="btn btn--ghost btn--large" href="<?= e(company_phone_link()) ?>">📞 <?= e(company_phone()) ?></a>
    </div>
  </div>
</section>

<section style="background:var(--dark-2);padding:3rem 0;border-bottom:1px solid var(--line);">
<div class="container">
  <div class="eyebrow-line">Nos interventions</div>
  <h2 class="section-title">Ce que nous <span>faisons</span></h2>
  <div class="interv-grid">
    <?php foreach ($tpl['interventions'] as [$ico,$titre,$desc]): ?>
    <div class="interv-card">
      <div class="interv-card__icon"><?= $ico ?></div>
      <h3><?= e($titre) ?></h3>
      <p><?= e($desc) ?></p>
    </div>
    <?php endforeach; ?>
  </div>
</div>
</section>

<section style="background:var(--dark);padding:3rem 0;border-bottom:1px solid var(--line);">
<div class="container">
  <div class="eyebrow-line">Process</div>
  <h2 class="section-title">Intervention en <span>3 étapes</span></h2>
  <div class="steps-grid">
    <div class="step-card"><div class="step-num">01</div><div><h3>Contact immédiat</h3><p>Appelez ou envoyez votre demande. Nous répondons immédiatement et organisons l'intervention.</p></div></div>
    <div class="step-card"><div class="step-num">02</div><div><h3>Déplacement rapide</h3><p>Technicien qualifié dépêché sur place. Délai et tarif confirmés avant déplacement.</p></div></div>
    <div class="step-card"><div class="step-num">03</div><div><h3>Intervention & rapport</h3><p>Diagnostic, réparation ou installation. Compte rendu et facture détaillée remis en fin de chantier.</p></div></div>
  </div>
</div>
</section>

<section style="background:var(--dark-2);padding:3rem 0;border-bottom:1px solid var(--line);">
<div class="container" style="max-width:860px;">
  <div class="eyebrow-line">FAQ <?= e(mb_strtolower($tpl['label'],'UTF-8')) ?></div>
  <h2 class="section-title">Questions <span>fréquentes</span></h2>
  <div class="service-faq">
    <?php foreach ($tpl['faq'] as [$q,$a]): ?>
    <details class="sfaq"><summary><?= e($q) ?></summary><p><?= e($a) ?></p></details>
    <?php endforeach; ?>
  </div>
</div>
</section>

<section style="background:var(--dark);padding:3rem 0;">
<div class="container" style="max-width:700px;">
  <div class="eyebrow-line">Demande d'intervention</div>
  <div class="quote-card">
    <div class="quote-card__badge">📋 Votre technicien <?= e(mb_strtolower($tpl['label'],'UTF-8')) ?></div>
    <h2>Devis gratuit</h2>
    <?php render_quote_form($cards,'service_'.$sk); ?>
  </div>
</div>
</section>
<?php
        if (trim((string)($page['content_html']??'')) !== '') {
            echo '<section style="background:var(--dark-2);padding:3rem 0;"><div class="container"><div class="rich-content" style="max-width:860px;">'.$page['content_html'].'</div></div></section>';
        }
        render_footer(); exit;
    }

    // Page générique
    $meta = seo_defaults($route,$page);
    render_head($meta); render_header(route_url($route));
    echo '<section class="page-hero"><div class="container page-hero__content"><div class="page-hero__eyebrow">'.e($page['page_type']).'</div><h1>'.e($page['title']).'</h1><p>'.e($page['excerpt']??'').'</p></div></section>';
    echo '<section style="background:var(--dark);padding:3rem 0;"><div class="container"><div class="rich-content">'.($page['content_html']?:'<p style="color:var(--text-2)">Contenu à venir.</p>').'</div></div></section>';
    render_footer(); exit;
}

// 404
$meta = ['title'=>'Page introuvable | '.company_name(),'description'=>'Cette page n\'existe pas.','canonical'=>route_url($route)];
render_head($meta); render_header('');
echo '<section class="page-hero"><div class="container page-hero__content"><div class="page-hero__eyebrow">Erreur 404</div><h1>Page introuvable</h1><p>La page demandée n\'existe pas ou a été déplacée.</p><div class="btn-row" style="margin-top:1.5rem;"><a class="btn btn--primary" href="'.e(route_url('')).'">Retour à l\'accueil</a></div></div></section>';
render_footer();
