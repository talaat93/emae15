<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/render.php';

$route = trim((string)($_GET['route'] ?? ''), '/');

/* ── POST FORM ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form_type'] ?? '') === 'quote') {
    verify_csrf();
    if (!rate_limit_passed('quote_submit', 8)) { flash('error','Merci de patienter quelques secondes.'); redirect_to('index.php?route='.$route); }
    if (trim((string)($_POST['website'] ?? '')) !== '') { redirect_to('index.php?route='.$route); }
    $fn=$_POST['full_name']??''; $ph=$_POST['phone']??''; $em=$_POST['email']??'';
    $ci=$_POST['city']??''; $sv=$_POST['service_type']??''; $mg=$_POST['message']??'';
    $ur=$_POST['urgency']??'Normale'; $so=$_POST['source']??'';
    if (trim($fn)===''||trim($ph)===''||trim($mg)==='') { flash('error','Merci de remplir les champs obligatoires.'); redirect_to('index.php?route='.$route); }
    db_execute('INSERT INTO quotes (full_name,phone,email,city,service_type,message,urgency,status,source) VALUES (?,?,?,?,?,?,?,?,?)',
        [trim($fn),trim($ph),trim($em),trim($ci),trim($sv),trim($mg),trim($ur),'nouveau',trim($so)]);
    flash('success', quote_form_options()['success_message']);
    redirect_to('index.php?route='.($route ?: 'home'));
}

/* ════════════════════════════════
   ACCUEIL
════════════════════════════════ */
if ($route === '' || $route === 'home') {
    $cards  = service_cards_v14();
    $why    = why_us_settings();
    $banner = hero_banner_settings();
    $reals  = visible_realisations(3);
    $revs   = visible_reviews(3);
    $meta   = seo_defaults('home');

    $btn1url = setting('home_button1_url','quote');
    if (!preg_match('#^(https?:|tel:|mailto:|/)#i',$btn1url)) $btn1url = route_url($btn1url);
    $btn2url = setting('home_button2_url','') ?: company_phone_link();
    if ($btn2url !== '' && !preg_match('#^(https?:|tel:|mailto:|/)#i',$btn2url)) $btn2url = route_url($btn2url);

    render_head($meta);
    render_header(route_url(''));
?>

<!-- HERO -->
<section class="hero">
  <div class="hero-line"></div><div class="hero-line2"></div>
  <div class="wrap hero-grid">

    <div class="hero-content">
      <div class="hero-badge"><span class="hero-badge-dot"></span><?= e(setting('home_eyebrow','Disponible '.company_hours())) ?></div>
      <h1><?php
        $ht = setting('home_title','Votre expert multitechnique');
        $ht2 = setting('home_title_hl','en urgence');
        echo e($ht).' <span class="hl">'.e($ht2).'</span>';
      ?></h1>
      <p class="hero-lead"><?= e(setting('home_lead','Dépannage électrique, plomberie, chauffage, climatisation et pompes à chaleur en Île-de-France et Occitanie. Intervention rapide, devis gratuit, artisans qualifiés.')) ?></p>

      <div class="chips">
        <?php foreach (array_filter([
          setting('home_chip_1','Électricité'), setting('home_chip_2','Plomberie'),
          setting('home_chip_3','Chauffage'), setting('home_chip_4','PAC'),
          setting('home_chip_5','Climatisation'), setting('home_chip_6','CVC'),
        ], fn($v)=>trim($v)!=='') as $i=>$chip): ?>
        <span class="chip <?= $i<2?'on':'' ?>"><?= e($chip) ?></span>
        <?php endforeach; ?>
      </div>

      <div class="hero-actions">
        <a class="btn btn-p btn-lg" href="<?= e($btn1url) ?>"><?= e(setting('home_button1_label','Devis gratuit')) ?></a>
        <a class="btn btn-outline btn-lg" href="<?= e($btn2url) ?>">📞 <?= e(setting('home_button2_label','Appeler maintenant')) ?></a>
      </div>

      <div class="hero-trust">
        <?php foreach ([
          ['⚡', setting('trust_1_title','Intervention rapide'), setting('trust_1_sub','Moins de 2h en urgence')],
          ['🆓', setting('trust_2_title','Devis gratuit'),      setting('trust_2_sub','Sans engagement')],
          ['⭐', setting('trust_3_title',setting('schema_rating_value','4.9').'/5'), setting('trust_3_sub',setting('schema_review_count','120').' avis')],
          ['🔒', setting('trust_4_title','Certifiés'),          setting('trust_4_sub','Artisans qualifiés')],
        ] as [$ico,$t,$s]): ?>
        <div class="trust-pill">
          <span class="trust-pill-ico"><?= $ico ?></span>
          <span class="trust-pill-txt"><strong><?= e($t) ?></strong><span><?= e($s) ?></span></span>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="hero-form">
      <div class="hero-card">
        <div class="hero-card-tag"><?= e(setting('home_quote_eyebrow','Rappel gratuit sous 30 min')) ?></div>
        <h2><?= e(setting('home_quote_title','Obtenir un devis')) ?></h2>
        <?php render_quote_form($cards, 'hero'); ?>
      </div>
    </div>
  </div>
</section>

<!-- STRIP URGENCE -->
<div class="strip">
  <div class="wrap strip-in">
    <?php foreach (array_filter([
      setting('strip_1','⚡ Urgence électrique 24h/24'),
      setting('strip_2','💧 Fuite d\'eau — intervention immédiate'),
      setting('strip_3','🔥 Panne chauffage / PAC'),
      setting('strip_4','❄️ Climatisation en panne'),
      '📞 '.company_phone(),
    ], fn($v)=>trim($v)!=='') as $i=>$item): ?>
      <?php if ($i>0): ?><span class="strip-sep"></span><?php endif; ?>
      <span class="strip-item"><?= e($item) ?></span>
    <?php endforeach; ?>
  </div>
</div>

<!-- NOS SERVICES -->
<section class="svc-section">
  <div class="wrap">
    <div class="svc-header">
      <div class="svc-label"><?= e(setting('services_section_label','Nos pôles d\'intervention')) ?></div>
      <h2 class="svc-title"><?= e(setting('services_title','Tout ce dont vous avez')) ?> <em><?= e(setting('services_title_hl','besoin')) ?></em></h2>
      <p class="svc-lead"><?= e(setting('services_lead','Dépannage urgence, installation, entretien et mise aux normes — un seul interlocuteur pour tous vos besoins techniques.')) ?></p>
    </div>
    <div class="svc-grid">
      <?php foreach ($cards as $card):
        $link = trim($card['link'] ?? 'services');
        if (!preg_match('#^(https?:|/)#i',$link)) $link = route_url($link);
        $img  = trim($card['image'] ?? '');
        $hasImg = $img !== '' && file_exists(__DIR__.'/'.ltrim($img,'/'));
      ?>
      <a class="svc-card" href="<?= e($link) ?>">
        <?php if ($hasImg): ?>
          <div class="svc-bg" style="background-image:url(<?= e(asset_url($img)) ?>)"></div>
        <?php else: ?>
          <div class="svc-placeholder">
            <div class="svc-placeholder-lines"></div>
            <div class="svc-placeholder-icon"><?= e($card['placeholder_icon'] ?? '⚡') ?></div>
          </div>
        <?php endif; ?>
        <div class="svc-overlay"></div>
        <span class="svc-badge"><?= e($card['badge'] ?? 'Urgence') ?></span>
        <span class="svc-arrow">→</span>
        <div class="svc-body">
          <div class="svc-name"><?= e($card['title']) ?></div>
          <div class="svc-desc"><?= e($card['desc'] ?? '') ?></div>
          <div class="svc-tags">
            <?php foreach ((array)($card['tags'] ?? []) as $tag): ?>
              <span class="svc-tag"><?= e($tag) ?></span>
            <?php endforeach; ?>
          </div>
        </div>
      </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- POURQUOI NOUS -->
<section class="why-section">
  <div class="wrap">
    <div class="svc-label"><?= e($why['eyebrow'] ?? 'Pourquoi nous choisir') ?></div>
    <h2 class="section-title"><?= e($why['title'] ?? 'EMAE, votre expert de confiance') ?></h2>
    <?php if (trim($why['lead'] ?? '') !== ''): ?><p class="section-lead"><?= e($why['lead']) ?></p><?php endif; ?>
    <div class="why-grid">
      <?php foreach (($why['items'] ?? []) as $item): ?>
      <div class="why-card">
        <div class="why-icon">
          <?php if (($item['icon_type']??'emoji')==='image' && trim($item['icon_img']??'')!=='' && file_exists(__DIR__.'/'.ltrim($item['icon_img']??'','/'))) : ?>
            <img src="<?= e(asset_url($item['icon_img'])) ?>" alt="<?= e($item['title']??'') ?>">
          <?php else: ?>
            <?= e($item['icon'] ?? '✓') ?>
          <?php endif; ?>
        </div>
        <div>
          <div class="why-h"><?= e($item['title'] ?? '') ?></div>
          <p class="why-p"><?= e($item['text'] ?? '') ?></p>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- STATS -->
<div class="stats-band">
  <div class="wrap stats-grid">
    <?php foreach ($banner['stats'] as $s): ?>
    <div class="stat-item">
      <span class="stat-n"><?= e($s['number']) ?></span>
      <span class="stat-l"><?= e($s['label']) ?></span>
    </div>
    <?php endforeach; ?>
  </div>
</div>

<!-- PROCESSUS -->
<section class="process-section">
  <div class="wrap">
    <div class="svc-label"><?= e(setting('process_label','Comment ça marche')) ?></div>
    <h2 class="section-title"><?= e(setting('process_title','Votre intervention en')) ?> <em><?= e(setting('process_title_hl','3 étapes')) ?></em></h2>
    <div class="process-grid">
      <?php foreach ([
        [setting('process_1_num','01'), setting('process_1_title','Vous nous contactez'),        setting('process_1_text','Appelez ou remplissez le formulaire. Nous répondons immédiatement et qualifions votre besoin en moins de 5 minutes.')],
        [setting('process_2_num','02'), setting('process_2_title','Nous organisons l\'intervention'), setting('process_2_text','Un technicien qualifié est envoyé sur site. Délai et tarif estimé confirmés avant déplacement.')],
        [setting('process_3_num','03'), setting('process_3_title','Intervention & compte rendu'), setting('process_3_text','Diagnostic, réparation ou installation. Compte rendu clair et facture détaillée en fin de chantier.')],
      ] as [$num,$title,$text]): ?>
      <div class="process-step">
        <div class="process-num"><?= e($num) ?></div>
        <div><div class="process-h"><?= e($title) ?></div><p class="process-p"><?= e($text) ?></p></div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- RÉALISATIONS -->
<?php if (!empty($reals)): ?>
<div class="divider"></div>
<section class="sec sec-navy">
  <div class="wrap">
    <div class="svc-label"><?= e(setting('reals_label','Nos réalisations')) ?></div>
    <h2 class="section-title"><?= e(setting('reals_title','Interventions')) ?> <em><?= e(setting('reals_title_hl','récentes')) ?></em></h2>
    <div class="reals-grid" style="margin-top:2rem;">
      <?php foreach ($reals as $r): ?>
      <div class="real-card">
        <?php if (trim((string)($r['image_path']??''))!==''): ?>
          <div class="real-img"><img src="<?= e(asset_url($r['image_path'])) ?>" alt="<?= e($r['title']) ?>"></div>
        <?php else: ?>
          <div class="real-placeholder">🔧</div>
        <?php endif; ?>
        <div class="real-body">
          <?php if (trim((string)($r['service_type']??''))!==''): ?><div class="real-svc"><?= e($r['service_type']) ?></div><?php endif; ?>
          <div class="real-h"><?= e($r['title']) ?></div>
          <?php if (trim((string)($r['city']??''))!==''): ?><div class="real-city">📍 <?= e($r['city']) ?></div><?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <div style="text-align:center;margin-top:2.5rem;">
      <a class="btn btn-ghost-p" href="<?= e(route_url('realisations')) ?>"><?= e(setting('reals_btn','Voir toutes nos réalisations')) ?> →</a>
    </div>
  </div>
</section>
<?php endif; ?>

<!-- AVIS -->
<section class="reviews-section">
  <div class="wrap">
    <div class="reviews-header">
      <div>
        <div class="svc-label"><?= e(setting('reviews_label','Avis clients')) ?></div>
        <h2 class="section-title"><?= e(setting('reviews_title','Ce qu\'ils disent')) ?> <em><?= e(setting('reviews_title_hl','de nous')) ?></em></h2>
      </div>
      <div class="reviews-score">
        <div>
          <span class="rs-stars">★★★★★</span>
          <span class="rs-num"><?= e(setting('schema_rating_value','4.9')) ?></span>
          <span class="rs-info"><?= e(setting('schema_review_count','120')) ?> avis vérifiés</span>
        </div>
      </div>
    </div>
    <div class="reviews-grid">
      <?php foreach ($revs as $rev):
        $init = mb_strtoupper(mb_substr($rev['author_name'],0,1,'UTF-8'),'UTF-8');
      ?>
      <div class="review-card">
        <div class="rv-stars"><?= str_repeat('★',(int)$rev['rating']) ?></div>
        <p class="rv-text">"<?= e($rev['content']) ?>"</p>
        <div class="rv-author">
          <div class="rv-avatar"><?= e($init) ?></div>
          <div>
            <span class="rv-name"><?= e($rev['author_name']) ?></span>
            <?php if (trim((string)($rev['service_type']??''))!==''): ?>
            <span class="rv-svc"><?= e($rev['service_type']) ?><?php if(trim((string)($rev['city']??''))!==''): ?> — <?= e($rev['city']) ?><?php endif; ?></span>
            <?php endif; ?>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- DEVIS SECTION -->
<section class="quote-section">
  <div class="wrap qs-grid">
    <div class="qs-info">
      <div class="svc-label"><?= e(setting('qs_label','Devis gratuit')) ?></div>
      <h2><?= e(setting('qs_title','Besoin d\'un')) ?> <em><?= e(setting('qs_title_hl','technicien ?')) ?></em></h2>
      <p><?= e(setting('qs_lead','Décrivez votre besoin, nous vous rappelons sous 30 minutes avec un chiffrage clair. Aucune visite facturée sans votre accord.')) ?></p>
      <div class="qs-perks">
        <?php foreach ([
          setting('qs_perk_1','Devis 100% gratuit et sans engagement'),
          setting('qs_perk_2','Rappel sous 30 minutes garanti'),
          setting('qs_perk_3','Tarif annoncé avant toute intervention'),
          setting('qs_perk_4','Technicien qualifié et assuré'),
          setting('qs_perk_5','Disponible 7j/7, urgences 24h/24'),
          setting('qs_perk_6','Facture détaillée en fin de chantier'),
        ] as $perk): if (trim($perk)==='') continue; ?>
        <div class="qs-perk"><?= e($perk) ?></div>
        <?php endforeach; ?>
      </div>
      <div class="btn-row">
        <a class="btn btn-p" href="<?= e(company_phone_link()) ?>">📞 <?= e(company_phone()) ?></a>
        <a class="btn btn-outline" href="mailto:<?= e(company_email()) ?>">✉️ <?= e(company_email()) ?></a>
      </div>
    </div>
    <div class="qs-card">
      <div class="hero-card-tag"><?= e(setting('qs_form_label','Votre demande')) ?></div>
      <h2 style="font-family:var(--font-h);font-size:1.4rem;font-weight:800;color:#fff;margin-bottom:1.35rem;"><?= e(setting('qs_form_title','Décrivez votre besoin')) ?></h2>
      <?php render_quote_form($cards, 'home_section'); ?>
    </div>
  </div>
</section>

<!-- ZONES -->
<section class="zones-section">
  <div class="wrap">
    <div class="svc-label"><?= e(setting('zones_label','Zone d\'intervention')) ?></div>
    <h2 class="section-title"><?= e(setting('zones_title','Nous intervenons')) ?> <em><?= e(setting('zones_title_hl','près de chez vous')) ?></em></h2>
    <p class="section-lead"><?= e(setting('zones_lead','Île-de-France et Occitanie — délai moyen d\'intervention inférieur à 2 heures pour les urgences dans nos zones principales.')) ?></p>
    <div class="zones-grid">
      <?php $zoneCards = get_json_setting('home_zone_cards', [
        ['title'=>'🗺️ Île-de-France','text'=>setting('zone_idf_text','Paris, Meaux, Versailles, Évry, Nanterre, Saint-Denis, Créteil, Cergy et toute la région.'),'cities'=>setting('zone_idf_cities','Paris (75)|Meaux (77)|Versailles (78)|Évry (91)|Nanterre (92)|Saint-Denis (93)|Créteil (94)|Cergy (95)|Marne-la-Vallée|Melun|Pontoise')],
        ['title'=>'🗺️ Occitanie','text'=>setting('zone_occ_text','Toulouse, Montpellier, Nîmes, Perpignan et toute la région.'),'cities'=>setting('zone_occ_cities','Toulouse (31)|Montpellier (34)|Nîmes (30)|Perpignan (66)|Béziers (34)|Narbonne (11)|Carcassonne (11)|Albi (81)')],
      ]);
      foreach ($zoneCards as $zc):
        $cities = is_array($zc['cities']??null) ? $zc['cities'] : array_filter(array_map('trim', explode('|', (string)($zc['cities']??''))));
      ?>
      <div class="zone-card">
        <div class="zone-name"><?= e($zc['title']) ?></div>
        <p class="zone-text"><?= e($zc['text']) ?></p>
        <div class="zone-chips">
          <?php foreach ($cities as $city): ?><span class="zone-chip"><?= e($city) ?></span><?php endforeach; ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <div style="text-align:center;margin-top:2.5rem;">
      <a class="btn btn-p" href="<?= e(route_url('contact')) ?>"><?= e(setting('zones_btn','Vérifier ma zone')) ?> →</a>
    </div>
  </div>
</section>

<?php render_footer(); exit; }

/* ════ FAQ ════ */
if ($route === 'faq') {
    $faq  = faq_page_settings();
    $meta = ['title'=>setting('faq_meta_title','FAQ | '.company_name()),'description'=>setting('faq_meta_description','Questions fréquentes.'),'canonical'=>route_url('faq')];
    render_head($meta); render_header(route_url('faq'));
?>
<section class="page-hero"><div class="wrap"><div class="ph-eyebrow">Questions fréquentes</div>
  <h1 class="ph-h1"><?= e($faq['hero_title']) ?></h1>
  <p class="ph-lead"><?= e($faq['hero_lead']) ?></p>
  <div class="ph-badges">
    <span class="ph-badge hl">⚡ <?= e(setting('faq_badge_1','Réponse rapide')) ?></span>
    <span class="ph-badge"><?= e(setting('faq_badge_2','Urgences 24h/7j')) ?></span>
    <span class="ph-badge"><?= e(setting('faq_badge_3','Devis gratuit')) ?></span>
  </div>
</div></section>
<section class="sec sec-navy"><div class="wrap">
  <div class="faq-cats">
    <button class="faq-cat on" data-cat="all"><?= e(setting('faq_cat_all','Toutes')) ?></button>
    <?php foreach ($faq['groups'] as $g): ?><button class="faq-cat" data-cat="<?= e(mb_strtolower($g['category']??'','UTF-8')) ?>"><?= e($g['category']??'') ?></button><?php endforeach; ?>
  </div>
  <?php foreach ($faq['groups'] as $g): ?>
  <div class="faq-group" data-group="<?= e(mb_strtolower($g['category']??'','UTF-8')) ?>">
    <div class="faq-group-title">// <?= e($g['category']??'') ?></div>
    <div class="faq-list">
      <?php foreach (($g['items']??[]) as $item): ?>
      <div class="faq-item">
        <div class="faq-q"><?= e($item['q']) ?><span class="faq-plus">+</span></div>
        <div class="faq-a"><?= e($item['a']) ?></div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endforeach; ?>
  <div class="faq-cta">
    <h2><?= e($faq['cta_title']) ?></h2><p><?= e($faq['cta_lead']) ?></p>
    <div class="acts">
      <a class="btn btn-p btn-lg" href="<?= e(route_url($faq['cta_url'])) ?>"><?= e($faq['cta_button']) ?></a>
      <a class="btn btn-outline btn-lg" href="<?= e(company_phone_link()) ?>">📞 <?= e(company_phone()) ?></a>
    </div>
  </div>
</div></section>
<?php render_footer(); exit; }

/* ════ CONTACT ════ */
if ($route === 'contact') {
    $cfg  = contact_page_settings();
    $cards = service_cards_v14();
    $meta = ['title'=>setting('contact_meta_title','Contact | '.company_name()),'description'=>setting('contact_meta_description','Contactez EMAE.'),'canonical'=>route_url('contact')];
    render_head($meta); render_header(route_url('contact'));
?>
<section class="page-hero"><div class="wrap"><div class="ph-eyebrow"><?= e($cfg['hero_eyebrow']) ?></div>
  <h1 class="ph-h1"><?= e($cfg['hero_title']) ?></h1>
  <p class="ph-lead"><?= e($cfg['hero_lead']) ?></p>
  <div class="ph-badges">
    <span class="ph-badge hl">✓ <?= e(setting('contact_badge_1','Devis gratuit')) ?></span>
    <span class="ph-badge">⚡ <?= e(setting('contact_badge_2','Réponse sous 30 min')) ?></span>
    <span class="ph-badge">📍 <?= e(company_regions()) ?></span>
    <span class="ph-badge">🕐 <?= e(company_hours()) ?></span>
  </div>
</div></section>
<section class="sec sec-navy"><div class="wrap contact-layout">
  <div class="contact-box">
    <div class="cbox-h"><?= e($cfg['form_title']) ?></div>
    <div class="cbox-sub"><?= e($cfg['form_subtitle']) ?></div>
    <form method="post" class="cf-form">
      <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="form_type" value="quote">
      <input type="hidden" name="source" value="contact_page">
      <input type="text" name="website" value="" class="hp" tabindex="-1" autocomplete="off">
      <div class="cf-row">
        <label class="cf-label">Nom complet *<input class="cf-input" type="text" name="full_name" placeholder="Votre nom" required></label>
        <label class="cf-label">Téléphone *<input class="cf-input" type="tel" name="phone" placeholder="06 00 00 00 00" required></label>
      </div>
      <div class="cf-row">
        <label class="cf-label">Email<input class="cf-input" type="email" name="email" placeholder="votre@email.fr"></label>
        <label class="cf-label">Ville<input class="cf-input" type="text" name="city" placeholder="<?= e(setting('home_quote_city_placeholder','Meaux, Paris…')) ?>"></label>
      </div>
      <label class="cf-label">Service<select class="cf-input" name="service_type"><option value="">Choisir</option><?php foreach($cards as $c):?><option value="<?=e($c['title'])?>"><?=e($c['title'])?></option><?php endforeach;?></select></label>
      <label class="cf-label">Urgence<select class="cf-input" name="urgency"><option value="Normale">Normale</option><option value="Urgente">Urgente — sous 24h</option><option value="Très urgente">Très urgente — immédiat</option></select></label>
      <label class="cf-label">Votre besoin *<textarea class="cf-input cf-textarea" name="message" placeholder="Décrivez votre demande…" required></textarea></label>
      <button class="btn btn-p btn-lg btn-block" type="submit"><?= e(quote_form_options()['submit_label']) ?></button>
      <p class="f-meta" style="margin-top:.5rem;">✓ Gratuit <span class="f-meta-dot"></span> ✓ Sans engagement <span class="f-meta-dot"></span> ✓ Réponse rapide</p>
    </form>
  </div>
  <div class="contact-sidebar">
    <div class="c-info-box">
      <div class="c-info-h"><?= e($cfg['info_title']) ?></div>
      <div class="c-info-item"><div class="c-info-ico">📞</div><div><strong class="c-info-strong">Téléphone</strong><span class="c-info-val"><a href="<?=e(company_phone_link())?>"><?=e(company_phone())?></a></span></div></div>
      <div class="c-info-item"><div class="c-info-ico">✉️</div><div><strong class="c-info-strong">Email</strong><span class="c-info-val"><a href="mailto:<?=e(company_email())?>"><?=e(company_email())?></a></span></div></div>
      <div class="c-info-item"><div class="c-info-ico">📍</div><div><strong class="c-info-strong">Zones</strong><span class="c-info-val"><?=e(company_regions())?></span></div></div>
      <div class="c-info-item"><div class="c-info-ico">🕐</div><div><strong class="c-info-strong">Disponibilité</strong><span class="c-info-val"><?=e(company_hours())?></span></div></div>
      <?php if(company_siret()!==''):?><div class="c-info-item"><div class="c-info-ico">🏢</div><div><strong class="c-info-strong">SIRET</strong><span class="c-info-val"><?=e(company_siret())?></span></div></div><?php endif;?>
    </div>
    <div class="c-urgency">
      <div class="c-urgency-h"><?= e($cfg['urgency_title']) ?></div>
      <p class="c-urgency-p"><?= e($cfg['urgency_text']) ?></p>
      <a class="btn btn-p btn-block" href="<?= e(company_phone_link()) ?>">📞 <?= e(company_phone()) ?></a>
    </div>
    <div class="c-info-box">
      <div class="c-info-h"><?= e($cfg['zones_title']) ?></div>
      <div class="zone-tags-grid">
        <?php $ztags = array_filter(array_map('trim', explode('|', setting('contact_zone_tags','Paris (75)|Meaux (77)|Versailles (78)|Évry (91)|Nanterre (92)|Saint-Denis (93)|Créteil (94)|Cergy (95)|Toulouse|Montpellier|Nîmes|Occitanie'))));
        foreach ($ztags as $z): ?><div class="zone-tag-sm"><?= e($z) ?></div><?php endforeach; ?>
      </div>
    </div>
  </div>
</div></section>
<?php render_footer(); exit; }

/* ════ DEVIS ════ */
if ($route === 'quote') {
    $cards = service_cards_v14();
    $meta  = ['title'=>setting('quote_meta_title','Devis gratuit | '.company_name()),'description'=>setting('quote_meta_description','Devis gratuit et rapide.'),'canonical'=>route_url('quote')];
    render_head($meta); render_header(route_url('quote'));
?>
<section class="page-hero"><div class="wrap"><div class="ph-eyebrow">Devis gratuit</div>
  <h1 class="ph-h1"><?= e(setting('quote_hero_title','Votre demande d\'intervention')) ?></h1>
  <p class="ph-lead"><?= e(setting('quote_hero_lead','Remplissez ce formulaire. Un technicien vous rappelle sous 30 minutes avec un chiffrage clair, sans engagement.')) ?></p>
  <div class="ph-badges"><span class="ph-badge hl">✓ 100% gratuit</span><span class="ph-badge">⚡ Réponse sous 30 min</span><span class="ph-badge">📍 <?=e(company_regions())?></span></div>
</div></section>
<section class="sec sec-navy"><div class="wrap" style="max-width:660px;">
  <div class="qs-card">
    <div class="hero-card-tag">📋 Formulaire de devis</div>
    <h2 style="font-family:var(--font-h);font-size:1.5rem;font-weight:800;color:#fff;margin-bottom:1.35rem;"><?= e(setting('quote_form_title','Décrivez votre besoin')) ?></h2>
    <?php render_quote_form($cards,'quote_page'); ?>
  </div>
</div></section>
<?php render_footer(); exit; }

/* ════ RÉALISATIONS ════ */
if ($route === 'realisations') {
    $reals = all_realisations();
    $meta  = ['title'=>setting('reals_meta_title','Réalisations | '.company_name()),'description'=>setting('reals_meta_description','Nos chantiers et interventions.'),'canonical'=>route_url('realisations')];
    render_head($meta); render_header(route_url('realisations'));
?>
<section class="page-hero"><div class="wrap"><div class="ph-eyebrow">Nos réalisations</div>
  <h1 class="ph-h1"><?= e(setting('reals_page_title','Interventions & chantiers')) ?></h1>
  <p class="ph-lead"><?= e(setting('reals_page_lead','Des interventions propres et documentées en '.company_regions().'.')) ?></p>
</div></section>
<section class="sec sec-navy"><div class="wrap">
  <?php if (empty($reals)): ?>
    <div style="text-align:center;padding:4rem 0;color:var(--t2);">
      <div style="font-size:3rem;margin-bottom:1rem;">📷</div>
      <p><?= e(setting('reals_empty','Les réalisations seront publiées prochainement.')) ?></p>
      <a class="btn btn-p" href="<?= e(route_url('contact')) ?>" style="margin-top:1.5rem;display:inline-flex;"><?= e(setting('reals_empty_btn','Nous contacter')) ?></a>
    </div>
  <?php else: ?>
    <div class="reals-grid">
      <?php foreach ($reals as $r): ?>
      <div class="real-card">
        <?php if(trim((string)($r['image_path']??''))!==''):?><div class="real-img"><img src="<?=e(asset_url($r['image_path']))?>" alt="<?=e($r['title'])?>"></div><?php else:?><div class="real-placeholder">🔧</div><?php endif;?>
        <div class="real-body">
          <?php if(trim((string)($r['service_type']??''))!==''):?><div class="real-svc"><?=e($r['service_type'])?></div><?php endif;?>
          <div class="real-h"><?=e($r['title'])?></div>
          <?php if(trim((string)($r['description']??''))!==''):?><p style="font-size:.8rem;color:var(--t2);margin:.25rem 0 0;font-weight:300;"><?=e($r['description'])?></p><?php endif;?>
          <?php if(trim((string)($r['city']??''))!==''):?><div class="real-city">📍 <?=e($r['city'])?></div><?php endif;?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div></section>
<?php render_footer(); exit; }

/* ════ PAGES SERVICE ════ */
$page = page_by_slug($route);
if ($page) {
    $ctx = mb_strtolower($route.' '.($page['slug']??'').' '.($page['title']??''),'UTF-8');
    $sk = null;
    if (str_contains($ctx,'electric')||str_contains($ctx,'electri')) $sk='electricite';
    elseif (str_contains($ctx,'plomb')) $sk='plomberie';
    elseif (str_contains($ctx,'chauff')||str_contains($ctx,'chaudiere')||str_contains($ctx,'pac')||str_contains($ctx,'pompe')) $sk='chauffage';
    elseif (str_contains($ctx,'clim')||str_contains($ctx,'cvc')||str_contains($ctx,'ventil')) $sk='climatisation';

    $tpls = [
      'electricite' => [
        'label'=>'Électricité','icon_svc'=>'⚡',
        'desc'=>'Dépannage, installation, mise aux normes et rénovation électrique en '.company_regions().'.',
        'badges'=>['Dépannage urgent','Tableau électrique','Mise aux normes NF C 15-100','Rénovation'],
        'offer_title'=>'Ce que nous proposons en électricité',
        'offer_items'=>['Dépannage et remise en service (urgence 24h/7j)','Remplacement et mise en conformité de tableaux électriques','Ajout ou réparation de circuits, prises, lignes dédiées','Installation et dépannage d\'éclairage intérieur et extérieur','Rénovation électrique complète (logement, commerce)','Mise aux normes NF C 15-100 obligatoire','Câblage neuf pour constructions et extensions','Contrôle et diagnostic de l\'installation existante'],
        'interv'=>[['⚡','Panne électrique','Diagnostic et remise en service rapide.'],['🧰','Tableau électrique','Remplacement et sécurisation.'],['🔌','Prises & circuits','Ajout, réparation, remplacement.'],['💡','Éclairage','LED, intérieur, extérieur, technique.'],['🏗️','Installation neuve','Câblage neuf ou réfection.'],['✅','Mise aux normes','NF C 15-100.'],['🔄','Rénovation','Modernisation de l\'installation.'],['🚨','Urgence 24h/7j','Astreinte permanente.']],
        'faq'=>[['Quel est le délai d\'intervention pour une urgence ?',setting('faq_elec_1_a','En urgence, nous intervenons en moins de 2h en Île-de-France. Le délai est confirmé au téléphone selon votre zone.')],['Faites-vous les mises aux normes NF C 15-100 ?',setting('faq_elec_2_a','Oui, nous réalisons la mise en conformité complète selon les normes en vigueur.')],['Intervenez-vous sur les bâtiments professionnels ?',setting('faq_elec_3_a','Oui, logements, commerces, bureaux et bâtiments techniques.')],['Le devis est-il gratuit ?',setting('faq_elec_4_a','Oui, devis gratuit et sans engagement avant toute intervention.')]],
      ],
      'plomberie' => [
        'label'=>'Plomberie','icon_svc'=>'💧',
        'desc'=>'Dépannage fuite, réparation sanitaire et entretien réseau en '.company_regions().'.',
        'badges'=>['Fuite urgente','Débouchage','Sanitaires','Entretien réseau'],
        'offer_title'=>'Ce que nous proposons en plomberie',
        'offer_items'=>['Recherche et réparation de fuites visibles ou cachées','Dépannage et remplacement de robinetterie','Débouchage de canalisations et WC','Remplacement de WC, lavabo, baignoire, douche','Réparation et remplacement de chauffe-eau','Entretien préventif annuel','Intervention sur réseaux d\'alimentation et d\'évacuation','Mise en conformité des installations sanitaires'],
        'interv'=>[['💧','Recherche de fuite','Localisation précise et réparation.'],['🚿','Sanitaires','WC, robinetterie, évacuations.'],['🧯','Urgence plomberie','Mise en sécurité immédiate.'],['🔩','Réseaux','Tuyauteries, alimentations, raccordements.'],['🛁','Équipements','Baignoire, douche, évier, robinet.'],['🌡️','Chauffe-eau','Diagnostic, remplacement, mise en service.'],['🧼','Entretien','Maintenance courante préventive.'],['📋','Rapport','Compte rendu détaillé systématique.']],
        'faq'=>[['Intervenez-vous en urgence pour une fuite ?',setting('faq_plomb_1_a','Oui, disponible '.company_hours().'. Appelez-nous pour une intervention immédiate.')],['Faites-vous le remplacement de chauffe-eau ?',setting('faq_plomb_2_a','Oui, diagnostic, remplacement et mise en service de tous types de chauffe-eau.')],['Proposez-vous un contrat d\'entretien ?',setting('faq_plomb_3_a','Oui, contrats de maintenance préventive annuels disponibles.')],['Que faire en cas de fuite importante ?',setting('faq_plomb_4_a','Coupez l\'arrivée d\'eau principale et appelez-nous immédiatement.')]],
      ],
      'chauffage' => [
        'label'=>'Chauffage & PAC','icon_svc'=>'🔥',
        'desc'=>'Dépannage chaudière gaz/fioul/électrique, pompe à chaleur, entretien en '.company_regions().'.',
        'badges'=>['Panne chaudière','Pompe à chaleur','Chaudière gaz/fioul','Entretien annuel'],
        'offer_title'=>'Ce que nous proposons en chauffage',
        'offer_items'=>['Dépannage et remise en service de chaudières gaz, fioul, électrique','Entretien annuel réglementaire de chaudière (obligatoire)','Dépannage et entretien de pompes à chaleur air/air et air/eau','Installation de PAC et chaudières neuves','Diagnostic et optimisation de la consommation énergétique','Remplacement de corps de chauffe, brûleurs, circulateurs','Réglage thermostat, programmation, vannes thermostatiques','Urgences hiver — astreinte renforcée en période froide'],
        'interv'=>[['🔥','Panne chaudière','Gaz, fioul, électrique.'],['♨️','Pompe à chaleur','Air/air et air/eau.'],['🌡️','Régulation','Thermostats, sondes, programmation.'],['🛠️','Optimisation','Amélioration performances et confort.'],['🏗️','Installation','Chaudière ou PAC neuve.'],['📊','Entretien annuel','Contrat réglementaire.'],['🚨','Urgence hiver','Astreinte renforcée.'],['📋','Rapport','Compte rendu et recommandations.']],
        'faq'=>[['Intervenez-vous sur les chaudières gaz et fioul ?',setting('faq_chauf_1_a','Oui, sur tous types de chaudières : gaz, fioul, électrique et condensation.')],['Proposez-vous l\'installation de pompe à chaleur ?',setting('faq_chauf_2_a','Oui, PAC air/air, air/eau — installation, entretien et dépannage.')],['Faites-vous l\'entretien annuel de chaudière ?',setting('faq_chauf_3_a','Oui, contrat d\'entretien annuel réglementaire avec rapport d\'intervention.')],['Mon chauffage tombe en panne en hiver, que faire ?',setting('faq_chauf_4_a','Appelez-nous immédiatement — priorité absolue aux urgences de chauffage en hiver.')]],
      ],
      'climatisation' => [
        'label'=>'Climatisation & CVC','icon_svc'=>'❄️',
        'desc'=>'Installation, dépannage et entretien de climatisation et CVC en '.company_regions().'.',
        'badges'=>['Clim en panne','CVC','Installation split','Entretien saisonnier'],
        'offer_title'=>'Ce que nous proposons en climatisation',
        'offer_items'=>['Dépannage de tout type de climatiseur (split, multi-split, gainable)','Installation de climatisation pour particuliers et professionnels','Entretien saisonnier (nettoyage, vérification, réglages)','Recharge en fluide frigorigène','Contrôle des performances et optimisation','Systèmes gainables et centralisés','VMC et ventilation mécanique','Étude et conseil avant installation'],
        'interv'=>[['❄️','Panne climatisation','Diagnostic et remise en service.'],['🌬️','Qualité air','Contrôle du soufflage et diffusion.'],['🧼','Entretien saisonnier','Nettoyage et réglages.'],['🏗️','Installation','Splits, multi-splits, gainables.'],['📈','Performances','Contrôle et optimisation.'],['💨','VMC','Ventilation mécanique.'],['🚨','Urgence','Prise en charge prioritaire.'],['📋','Rapport','Compte rendu systématique.']],
        'faq'=>[['Intervenez-vous sur tous types de climatiseurs ?',setting('faq_clim_1_a','Oui, splits, multi-splits, gainables et systèmes CVC.')],['Proposez-vous l\'installation de climatisation ?',setting('faq_clim_2_a','Oui, fourniture, pose et mise en service avec conseil adapté.')],['Quand faire l\'entretien de sa climatisation ?',setting('faq_clim_3_a','Idéalement avant chaque saison (printemps et automne) pour garantir les performances.')],['Intervenez-vous pour les entreprises ?',setting('faq_clim_4_a','Oui, commerces, bureaux, restaurants — intervention compatible avec votre exploitation.')]],
      ],
    ];

    if ($sk && isset($tpls[$sk])) {
        $tpl   = $tpls[$sk];
        $meta  = seo_defaults($route, $page);
        $cards = service_cards_v14();
        render_head($meta); render_header(route_url($route));
?>
<section class="page-hero"><div class="wrap">
  <div class="ph-eyebrow">// <?= e($tpl['label']) ?></div>
  <h1 class="ph-h1"><?= e($page['title']) ?></h1>
  <p class="ph-lead"><?= e($page['excerpt'] ?: $tpl['desc']) ?></p>
  <div class="ph-badges">
    <?php foreach ($tpl['badges'] as $b): ?><span class="ph-badge hl"><?= e($b) ?></span><?php endforeach; ?>
    <span class="ph-badge"><?= e(company_hours()) ?></span>
  </div>
  <div class="btn-row" style="margin-top:1.75rem;">
    <a class="btn btn-p btn-lg" href="<?= e(route_url('quote')) ?>"><?= e(setting('svc_page_btn_devis','Devis gratuit')) ?></a>
    <a class="btn btn-outline btn-lg" href="<?= e(company_phone_link()) ?>">📞 <?= e(company_phone()) ?></a>
  </div>
</div></section>

<!-- Ce que nous proposons -->
<section class="sec sec-card"><div class="wrap">
  <div class="svc-label"><?= e(setting('svc_offer_label','Notre offre')) ?></div>
  <h2 class="section-title"><?= e($tpl['offer_title']) ?></h2>
  <div style="display:grid;grid-template-columns:1fr 1fr;gap:.55rem;margin-top:1.5rem;">
    <?php foreach ($tpl['offer_items'] as $oi): ?>
    <div class="qs-perk"><?= e($oi) ?></div>
    <?php endforeach; ?>
  </div>
</div></section>

<!-- Interventions -->
<section class="sec sec-navy"><div class="wrap">
  <div class="svc-label"><?= e(setting('svc_interv_label','Nos interventions')) ?></div>
  <h2 class="section-title"><?= e(setting('svc_interv_title','Ce que nous')) ?> <em><?= e(setting('svc_interv_title_hl','faisons')) ?></em></h2>
  <div class="interv-grid" style="margin-top:1.75rem;">
    <?php foreach ($tpl['interv'] as [$ico,$t,$d]): ?>
    <div class="interv-card"><div class="interv-ico"><?= $ico ?></div><div class="interv-h"><?= e($t) ?></div><p class="interv-p"><?= e($d) ?></p></div>
    <?php endforeach; ?>
  </div>
</div></section>

<!-- Processus -->
<section class="sec sec-card"><div class="wrap">
  <div class="svc-label"><?= e(setting('svc_process_label','Notre méthode')) ?></div>
  <h2 class="section-title"><?= e(setting('svc_process_title','Intervention en')) ?> <em><?= e(setting('svc_process_title_hl','3 étapes')) ?></em></h2>
  <div class="steps-grid" style="margin-top:1.75rem;">
    <div class="step-card"><div class="step-num"><?= e(setting('process_1_num','01')) ?></div><div><div class="step-h"><?= e(setting('process_1_title','Contact immédiat')) ?></div><p class="step-p"><?= e(setting('process_1_text','Appelez ou envoyez votre demande. Nous répondons immédiatement.')) ?></p></div></div>
    <div class="step-card"><div class="step-num"><?= e(setting('process_2_num','02')) ?></div><div><div class="step-h"><?= e(setting('process_2_title','Déplacement rapide')) ?></div><p class="step-p"><?= e(setting('process_2_text','Technicien qualifié dépêché sur place. Délai et tarif confirmés avant déplacement.')) ?></p></div></div>
    <div class="step-card"><div class="step-num"><?= e(setting('process_3_num','03')) ?></div><div><div class="step-h"><?= e(setting('process_3_title','Intervention & rapport')) ?></div><p class="step-p"><?= e(setting('process_3_text','Diagnostic, réparation ou installation. Compte rendu et facture détaillés.')) ?></p></div></div>
  </div>
</div></section>

<!-- FAQ service -->
<section class="sec sec-navy"><div class="wrap" style="max-width:860px;">
  <div class="svc-label"><?= e(setting('svc_faq_label','FAQ')) ?> <?= e(mb_strtolower($tpl['label'],'UTF-8')) ?></div>
  <h2 class="section-title"><?= e(setting('svc_faq_title','Questions')) ?> <em><?= e(setting('svc_faq_title_hl','fréquentes')) ?></em></h2>
  <div class="sfaq-list" style="margin-top:1.75rem;">
    <?php foreach ($tpl['faq'] as [$q,$a]): ?>
    <details class="sfaq-item"><summary><?= e($q) ?></summary><p><?= e($a) ?></p></details>
    <?php endforeach; ?>
  </div>
</div></section>

<!-- Zones couvertes -->
<section class="sec sec-card"><div class="wrap">
  <div class="svc-label"><?= e(setting('svc_zones_label','Zone d\'intervention')) ?></div>
  <h2 class="section-title"><?= e(setting('svc_zones_title','Zones couvertes')) ?></h2>
  <div class="zones-grid" style="margin-top:1.75rem;">
    <?php $zc = get_json_setting('home_zone_cards', [
      ['title'=>'🗺️ Île-de-France','text'=>setting('zone_idf_text','Paris et toute la région.'),'cities'=>setting('zone_idf_cities','Paris (75)|Meaux (77)|Versailles (78)|Évry (91)|Nanterre (92)|Saint-Denis (93)|Créteil (94)|Cergy (95)')],
      ['title'=>'🗺️ Occitanie','text'=>setting('zone_occ_text','Toulouse et toute la région.'),'cities'=>setting('zone_occ_cities','Toulouse (31)|Montpellier (34)|Nîmes (30)|Perpignan (66)|Béziers (34)|Narbonne (11)')],
    ]);
    foreach ($zc as $z):
      $cit = is_array($z['cities']??null) ? $z['cities'] : array_filter(array_map('trim', explode('|', (string)($z['cities']??''))));
    ?>
    <div class="zone-card">
      <div class="zone-name"><?= e($z['title']) ?></div>
      <p class="zone-text"><?= e($z['text']) ?></p>
      <div class="zone-chips"><?php foreach($cit as $c):?><span class="zone-chip"><?=e($c)?></span><?php endforeach;?></div>
    </div>
    <?php endforeach; ?>
  </div>
</div></section>

<!-- Formulaire devis -->
<section class="sec sec-navy"><div class="wrap" style="max-width:700px;">
  <div class="svc-label"><?= e(setting('svc_form_label','Devis gratuit')) ?> <?= e(mb_strtolower($tpl['label'],'UTF-8')) ?></div>
  <div class="qs-card">
    <div class="hero-card-tag">📋 <?= e(setting('svc_form_tag','Votre technicien')) ?> <?= e(mb_strtolower($tpl['label'],'UTF-8')) ?></div>
    <h2 style="font-family:var(--font-h);font-size:1.4rem;font-weight:800;color:#fff;margin-bottom:1.35rem;"><?= e(setting('svc_form_title','Devis gratuit')) ?></h2>
    <?php render_quote_form($cards, 'service_'.$sk); ?>
  </div>
</div></section>

<?php if (trim((string)($page['content_html']??''))!==''): ?>
<section class="sec sec-card"><div class="wrap"><div class="rich" style="max-width:860px;"><?= $page['content_html'] ?></div></div></section>
<?php endif;
        render_footer(); exit;
    }

    // Page générique
    $meta = seo_defaults($route, $page);
    render_head($meta); render_header(route_url($route));
    echo '<section class="page-hero"><div class="wrap"><div class="ph-eyebrow">'.e($page['page_type']).'</div><h1 class="ph-h1">'.e($page['title']).'</h1><p class="ph-lead">'.e($page['excerpt']??'').'</p></div></section>';
    echo '<section class="sec sec-navy"><div class="wrap"><div class="rich">'.($page['content_html'] ?: '<p style="color:var(--t2)">Contenu à venir.</p>').'</div></div></section>';
    render_footer(); exit;
}

// 404
$meta = ['title'=>'Page introuvable | '.company_name(),'description'=>'404.','canonical'=>route_url($route)];
render_head($meta); render_header('');
echo '<section class="page-hero"><div class="wrap"><div class="ph-eyebrow">Erreur 404</div><h1 class="ph-h1">Page introuvable</h1><p class="ph-lead">La page demandée n\'existe pas ou a été déplacée.</p><div class="btn-row" style="margin-top:1.75rem;"><a class="btn btn-p" href="'.e(route_url('')).'">Retour à l\'accueil</a></div></div></section>';
render_footer();
