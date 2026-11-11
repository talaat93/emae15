<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/render.php';

$route = trim((string)($_GET['route'] ?? ''), '/');

// ── Zone detection from clean URL path (e.g. /paris-ile-de-france/) ──
$currentZone = null;
$_zuri = ltrim(rtrim((string)parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), '/'), '/');
$_zbase = ltrim(rtrim(base_path(), '/'), '/');
if ($_zbase !== '' && str_starts_with($_zuri, $_zbase)) $_zuri = ltrim(substr($_zuri, strlen($_zbase)), '/');
$_zsegs = array_values(array_filter(explode('/', $_zuri)));
$_zs0   = $_zsegs[0] ?? '';
if ($_zs0 !== '' && !in_array($_zs0, ['admin','tech','dispatcher','api','assets','includes','config','storage','index.php'], true)
    && preg_match('/^[a-z][a-z0-9-]{1,78}$/', $_zs0)) {
    $_zobj = get_zone_by_slug($_zs0);
    if ($_zobj && (bool)$_zobj['status']) {
        $currentZone = $_zobj;
        if ($route === '' && isset($_zsegs[1]) && trim($_zsegs[1]) !== '') $route = trim($_zsegs[1]);
    }
    unset($_zobj);
}
// Pages par ville : /intervention/ (liste) et /intervention/{ville} (includes/city_pages.php).
if ($_zs0 === 'intervention' && $currentZone === null && count($_zsegs) <= 3) {
    $_cslug = $_zsegs[1] ?? '';
    $_tslug = $_zsegs[2] ?? '';
    if (($_cslug === '' || city_page($_cslug) !== null) && ($_tslug === '' || isset(city_trades()[$_tslug]))) {
        set_zone_context(null);
        require_once __DIR__ . '/includes/stats.php';
        stats_track_view(trim('intervention/'.$_cslug.'/'.$_tslug, '/'), '');
        if ($_cslug === '') render_city_index();
        elseif ($_tslug === '') render_city_page(city_page($_cslug));
        else render_city_trade_page(city_page($_cslug), $_tslug);
        exit;
    }
}
// Adresse qui ne correspond à rien (/tests/x.php, /ancienne-page…) : avant, l'accueil
// s'affichait avec un code 200, et Google voyait des doublons de l'accueil. Une
// ancienne adresse « propre » d'une page qui existe (/plomberie) est redirigée.
if ($_zs0 !== '' && $_zs0 !== 'index.php' && $currentZone === null && $route === '') {
    if (count($_zsegs) === 1 && public_route_exists($_zs0)) {
        header('Location: '.absolute_url(route_url($_zs0)), true, 301);
        exit;
    }
    $route = '__introuvable__';
}
unset($_zuri, $_zbase, $_zsegs, $_zs0);
// Active le contexte : tous les réglages et contenus deviennent ceux de la zone,
// avec repli automatique sur les valeurs globales.
set_zone_context($currentZone);

// Mesure d'audience interne : un compteur par jour et par page, sans
// aucune donnée personnelle. Ignorée pour les robots et pour vous-même.
require_once __DIR__ . '/includes/stats.php';
if ($route !== '__introuvable__') stats_track_view($route, $currentZone['slug'] ?? '');

/* ── POST FORM ── */
// Envoi plus lourd que la limite du serveur : PHP vide alors tout le formulaire.
// Sans ce test, le client croirait sa demande partie alors qu'elle est perdue.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($_POST) && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
    flash('error', 'Vos photos sont trop lourdes : votre demande n\'a pas été envoyée. Réessayez avec moins de photos, ou appelez-nous au '.company_phone().'.');
    redirect_to(route_url($route));
}
// Ancien envoi en tâche de fond d'une saisie non terminée (retiré en novembre 2026) :
// une page restée en cache ou une requête directe ne doit rien enregistrer.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form_type'] ?? '') === 'partial') {
    http_response_code(410);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false]);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form_type'] ?? '') === 'quote') {
    verify_csrf();
    // Un caractère mal encodé (vieux navigateur, copier-coller) faisait échouer l'enregistrement : la demande était perdue.
    array_walk($_POST, static function (&$v): void { if (is_string($v)) $v = mb_scrub($v, 'UTF-8'); });
    if (!rate_limit_passed('quote_submit', 8)) { flash('error','Merci de patienter quelques secondes.'); redirect_to(route_url($route)); }
    if (trim((string)($_POST['website'] ?? '')) !== '') { redirect_to(route_url($route)); }
    $fn=$_POST['full_name']??''; $ph=$_POST['phone']??''; $em=$_POST['email']??'';
    $ci=$_POST['city']??''; $ad=$_POST['address']??''; $pc=$_POST['postal_code']??'';
    $sv=$_POST['service_type']??''; $mg=$_POST['message']??'';
    $ur=$_POST['urgency']??'Normale'; $so=$_POST['source']??'';
    $qType = quote_request_type((string)($_POST['request_type'] ?? ''));
    // Rendez-vous en ligne : le créneau est revérifié ici (il a pu se remplir entre-temps).
    $bDate = null; $bSlot = null; $bLabel = '';
    if ($qType === 'rdv') {
        [$bDate, $bSlot] = array_pad(explode('|', (string)($_POST['booking'] ?? ''), 2), 2, '');
        $bLabel = (booking_enabled() || admin_logged_in()) ? (string)booking_validate((string)$bDate, (string)$bSlot) : '';
        if ($bLabel === '') { flash('error', 'Ce créneau n\'est plus disponible : choisissez-en un autre, ou appelez-nous au '.company_phone().'.'); redirect_to(route_url($route)); }
        $reason = mb_substr(trim((string)($_POST['booking_reason'] ?? '')), 0, 120);
        $mg = 'Rendez-vous souhaité : '.$bLabel."\nMotif : ".($reason !== '' ? $reason : 'non précisé').(trim((string)$mg) !== '' ? "\n\n".trim((string)$mg) : '');
        if ($sv === '' && $reason !== '') $sv = $reason;
    }
    // Rappel express : téléphone et code postal suffisent ; le besoin et le nom sont facultatifs.
    if ($qType === 'rappel' && trim((string)$mg) === '') $mg = 'Demande de rappel (formulaire express)';
    $phDigits = strlen((string)preg_replace('/\D/', '', (string)$ph));
    if (trim((string)$ph) === '' || $phDigits < 9 || $phDigits > 15) { flash('error','Indiquez un numéro de téléphone complet pour que nous puissions vous rappeler.'); redirect_to(route_url($route)); }
    if (trim((string)$pc) !== '' && !preg_match('/^\d{5}$/', trim((string)$pc))) { flash('error','Le code postal doit comporter 5 chiffres.'); redirect_to(route_url($route)); }
    if (($qType !== 'rappel' && trim($fn)==='') || trim($mg)==='') { flash('error','Merci de remplir les champs obligatoires.'); redirect_to(route_url($route)); }
    try {
        db_execute('INSERT INTO quotes (full_name,phone,email,city,address,postal_code,service_type,message,urgency,status,source) VALUES (?,?,?,?,?,?,?,?,?,?,?)',
            [trim($fn),trim($ph),trim($em),trim($ci),trim($ad),trim($pc),trim($sv),trim($mg),trim($ur),'nouveau',trim($so)]);
    } catch (Throwable $_qe) {
        try {
            db_execute('INSERT INTO quotes (full_name,phone,email,city,service_type,message,urgency,status,source) VALUES (?,?,?,?,?,?,?,?,?)',
                [trim($fn),trim($ph),trim($em),trim($ci),trim($sv),trim($mg),trim($ur),'nouveau',trim($so)]);
        } catch (Throwable $_qe2) {
            // Jamais de faux succès : la demande n'est pas enregistrée, le visiteur le sait et peut appeler.
            error_log('[EMAE] demande non enregistrée : '.$_qe2->getMessage());
            flash('error', 'Votre demande n\'a pas pu être enregistrée, désolé. Appelez-nous au '.company_phone().' ou réessayez dans un instant.');
            redirect_to(route_url($route));
        }
        unset($_qe);
    }
    $qId   = db_last_id();
    // La demande est déjà enregistrée : une photo refusée ne peut plus la faire perdre.
    $qPhotos = quote_store_photos($_FILES['photos'] ?? []);
    try {
        db_execute('UPDATE quotes SET request_type = ?, client_photos = ? WHERE id = ?',
            [$qType, $qPhotos['files'] ? json_encode($qPhotos['files']) : null, $qId]);
        if ($bLabel !== '') db_execute('UPDATE quotes SET preferred_date = ?, preferred_slot = ? WHERE id = ?', [$bDate, $bSlot, $qId]);
    } catch (Throwable $_qe) { error_log('[EMAE] demande #'.$qId.' : '.$_qe->getMessage()); unset($_qe); }
    $qData=['full_name'=>$fn,'phone'=>$ph,'email'=>$em,'city'=>$ci,'address'=>$ad,'postal_code'=>$pc,'service_type'=>$sv,'message'=>$mg,'urgency'=>$ur,'source'=>$so,
            'quote_id'=>$qId,'request_type'=>$qType,'photos'=>$qPhotos['files'],'photos_refused'=>$qPhotos['refused'],'slot_label'=>$bLabel];
    send_quote_notification($qData);
    if (trim($em) !== '') send_quote_confirmation_to_client($qData);
    quote_after_submit_messages($qData);
    partial_delete((string)($_POST['partial_token'] ?? ''));
    $_SESSION['quote_conversion'] = 1;
    flash('success', $bLabel !== '' ? 'Votre demande de rendez-vous ('.$bLabel.') est bien enregistrée. Nous vous rappelons pour confirmer l\'heure.' : quote_form_options()['success_message']);
    if ($qPhotos['refused'] > 0) {
        flash('error', ($qPhotos['refused'] > 1 ? $qPhotos['refused'].' photos n\'ont pas pu être jointes' : 'Une photo n\'a pas pu être jointe')
            .' (formats acceptés : JPG, PNG ou WebP, '.QUOTE_PHOTO_MAX_MB.' Mo maximum). Votre demande est bien partie.');
    }
    redirect_to(route_url($route));
}

/* ════════════════════════════════
   ACCUEIL
════════════════════════════════ */
if ($route === '' || $route === 'home') {
    $cards  = service_cards_v14();
    $why    = why_us_settings();
    $banner = hero_banner_settings();
    // Sur une page de zone, les chantiers du secteur passent devant.
    $reals  = $currentZone ? realisations_local(3) : visible_realisations(3);
    $revs   = visible_reviews(3);
    $meta   = seo_defaults('home');

    $btn1url = setting('home_button1_url','quote');
    if (!preg_match('#^(https?:|tel:|mailto:|/)#i',$btn1url)) $btn1url = route_url($btn1url);
    $btn2url = setting('home_button2_url','') ?: company_phone_link();
    if ($btn2url !== '' && !preg_match('#^(https?:|tel:|mailto:|/)#i',$btn2url)) $btn2url = route_url($btn2url);

    // ── Zone overrides ──
    if ($currentZone) {
        $_SESSION['geo_redirected'] = true; // pas de redirect geo sur pages zone
        // Sans titre propre à la fiche de zone, on garde celui de seo_defaults() :
        // le texte global, dont les variables de lieu prennent les valeurs de la zone.
        $zMT = geo_replace(zone_field($currentZone, 'meta_title'));
        $zMD = geo_replace(zone_field($currentZone, 'meta_description'));
        if ($zMT !== '') $meta['title']       = $zMT;
        if ($zMD !== '') $meta['description'] = $zMD;
        $meta['canonical']   = url_for($currentZone['slug'].'/');
        // Zone non listée (ni département ni ville, ex. /idf/ renommée « Bourgogne-Franche-Comté ») :
        // la page reste accessible mais n'est pas proposée à l'indexation tant qu'elle n'est pas tranchée.
        if (!in_array($currentZone['slug'], array_column(intervention_zones_listed(), 'slug'), true)) $meta['robots'] = 'noindex, follow';
        unset($zMT, $zMD);
    }

    render_head($meta);
    render_header($currentZone ? url_for($currentZone['slug'].'/') : route_url(''));
?>

<!-- HERO -->
<section class="hero">
  <div class="hero-line"></div><div class="hero-line2"></div>
  <div class="wrap hero-grid">

    <div class="hero-content">
      <div class="hero-badge"><span class="hero-badge-dot"></span><?= e(geo_replace(setting('home_eyebrow','Disponible '.company_hours()))) ?></div>
      <h1><?php
        if ($currentZone && zone_field($currentZone,'hero_h1') !== '') {
            $zH1 = geo_replace(zone_field($currentZone,'hero_h1'));
            // split on last word for highlight
            $zParts = explode(' ', $zH1, -1);
            $zLast  = array_pop(explode(' ', $zH1)) ?: '';
            $zFirst = implode(' ', array_slice(explode(' ',$zH1),0,-1));
            echo e($zFirst).' <span class="hl">'.e($zLast).'</span>';
            unset($zH1,$zParts,$zLast,$zFirst);
        } else {
            $ht = setting('home_title','Votre expert multitechnique');
            $ht2 = setting('home_title_hl','en urgence');
            echo e($ht).' <span class="hl">'.e($ht2).'</span>';
        }
      ?></h1>
      <p class="hero-lead"><?php
        if ($currentZone && zone_field($currentZone,'hero_subtitle') !== '') {
            echo e(geo_replace(zone_field($currentZone,'hero_subtitle')));
        } else {
            echo e(geo_replace(setting('home_lead','Dépannage électrique, plomberie, chauffage, climatisation et pompes à chaleur {en_region}. Intervention rapide, devis gratuit, artisans qualifiés.')));
        }
      ?></p>

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
        <?php // L'admin peut échanger les deux boutons : la couleur suit le lien, pas la position.
        foreach ([[$btn1url, setting('home_button1_label','Devis gratuit')], [$btn2url, setting('home_button2_label','Appeler maintenant')]] as [$_bUrl, $_bLabel]):
            $_bCall = str_starts_with($_bUrl, 'tel:'); ?>
        <a class="btn <?= $_bCall ? 'btn-p' : 'btn-outline' ?> btn-lg" href="<?= e($_bUrl) ?>"><?= $_bCall ? '📞 ' : '' ?><?= e($_bLabel) ?></a>
        <?php endforeach; unset($_bUrl, $_bLabel, $_bCall); ?>
      </div>
      <?= render_google_badge() ?>

    </div>

    <div class="hero-form">
      <div class="hero-card">
        <div class="hero-card-tag"><?= e(claim_clean(setting('home_quote_eyebrow', 'Rappel gratuit'), 'Rappel gratuit')) ?></div>
        <h2><?= e(setting('home_quote_title','Être rappelé')) ?></h2>
        <?php render_quote_form($cards, 'hero', 'rappel'); ?>
      </div>
    </div>
  </div>
</section>
<?= render_trust_band() ?>


<!-- NOS SERVICES -->
<section class="svc-section">
  <div class="wrap">
    <div class="svc-header">
      <div class="svc-label"><?= e(setting('services_section_label','Nos pôles d\'intervention')) ?></div>
      <h2 class="svc-title"><?= e(setting('services_title','Tout ce dont vous avez')) ?> <em><?= e(setting('services_title_hl','besoin')) ?></em></h2>
      <p class="svc-lead"><?= e(geo_replace(setting('services_lead','Dépannage urgence, installation, entretien et mise aux normes {en_region} — un seul interlocuteur pour tous vos besoins techniques.'))) ?></p>
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
      <?php if (count($cards) % 2 === 1): // Un nombre impair de cartes laisserait un trou dans la grille. ?>
      <a class="svc-card" href="<?= e(route_url('services')) ?>">
        <div class="svc-placeholder"><div class="svc-placeholder-lines"></div><div class="svc-placeholder-icon">🧰</div></div>
        <div class="svc-overlay"></div>
        <span class="svc-arrow">→</span>
        <div class="svc-body">
          <div class="svc-name"><?= e(setting('services_all_title','Tous nos services')) ?></div>
          <div class="svc-desc"><?= e(setting('services_all_desc','Découvrez l\'ensemble de nos métiers et de nos prestations.')) ?></div>
        </div>
      </a>
      <?php endif; ?>
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
      <?php foreach (($why['items'] ?? []) as $item):
        // Argument fondé sur un chiffre non documenté (note, délai, « 1000+ ») : non publié.
        if (claim_unsupported((string)($item['title'] ?? '').' '.(string)($item['text'] ?? ''))) continue; ?>
      <div class="why-card">
        <div class="why-icon">
          <?php if (($item['icon_type']??'emoji')==='image' && trim($item['icon_img']??'')!=='' && file_exists(__DIR__.'/'.ltrim($item['icon_img']??'','/'))) : ?>
            <?= picture_html($item['icon_img'], $item['title']??'', ['loading'=>'lazy','width'=>'50','height'=>'50']) ?>
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

<!-- ZONES -->
<section class="zones-section">
  <div class="wrap">
    <div class="svc-label"><?= e(setting('zones_label','Zone d\'intervention')) ?></div>
    <?php if ($currentZone): ?>
    <h2 class="section-title">Villes couvertes <em>en <?= e($currentZone['name']) ?></em></h2>
    <p class="section-lead">Nos techniciens interviennent dans les villes de la zone <?= e($currentZone['name']) ?>. <?= e(intervention_terms_text()) ?></p>
    <div class="zones-grid">
      <div class="zone-card">
        <div class="zone-name"><?= e($currentZone['name']) ?></div>
        <div class="zone-chips">
          <?php foreach (zone_cities($currentZone) as $_zc): ?><span class="zone-chip"><?= e($_zc) ?></span><?php endforeach; ?>
        </div>
      </div>
    </div>
    <?php else: ?>
    <?php
    // Accueil du site global : une seule carte régionale (choix du propriétaire, 10/11/2026) qui
    // regroupe les départements desservis ; les pages /jura/, /doubs/, /cote-d-or/ restent en ligne.
    $_reg = trim(setting('home_zone_region', 'Bourgogne-Franche-Comté'));
    $_listed = intervention_zones_listed();
    $_depts = []; foreach ($_listed as $_az) foreach ($_az['depts'] as $_d) $_depts[] = $_d;
    // Villes prises à tour de rôle dans chaque zone : Dijon, Besançon et Dole d'abord.
    $_villes = [];
    for ($_i = 0; $_i < 5; $_i++) foreach ($_listed as $_az) if (isset($_az['cities'][$_i]) && !in_array($_az['cities'][$_i], $_villes, true)) $_villes[] = $_az['cities'][$_i];
    usort($_villes, static fn($a, $b) => (int)!in_array($a, ['Dijon', 'Besançon', 'Dole'], true) <=> (int)!in_array($b, ['Dijon', 'Besançon', 'Dole'], true));
    unset($_i); ?>
    <h2 class="section-title"><?= e(claim_clean(setting('zones_title','Nos zones d\'intervention'), 'Nos zones d\'intervention')) ?> <em>en <?= e($_reg) ?></em></h2>
    <p class="section-lead"><?= e(geo_replace(claim_clean(setting('zones_lead', 'Nous intervenons {en_region}.'), 'Nous intervenons {en_region}.')).' '.intervention_terms_text()) ?></p>
    <div class="zones-grid zones-grid--one">
      <div class="zone-card zone-card--map">
        <?= render_zone_map() ?>
        <a href="<?= e(route_url('zones')) ?>" class="zone-card-txt" style="text-decoration:none;color:inherit;">
          <div class="zone-name"><?= e($_reg) ?></div>
          <?php if ($_depts): ?><p class="zone-text"><?= e(implode(' · ', array_values(array_unique($_depts)))) ?></p><?php endif; ?>
          <?php if ($_villes): ?>
          <div class="zone-chips" style="margin-top:.5rem;">
            <?php foreach ($_villes as $_c): ?><span class="zone-chip"><?= e($_c) ?></span><?php endforeach; ?>
          </div>
          <?php endif; ?>
        </a>
      </div>
    </div>
    <?php unset($_reg, $_listed, $_depts, $_villes, $_az, $_d, $_c); ?>
    <?php endif; ?>
    <?php if (!$currentZone): ?>
    <div class="px-chips" style="justify-content:center;margin-top:1.5rem;">
      <?php foreach (array_keys(city_pages()) as $_cs): $_cp = city_page($_cs); ?><a class="px-chip" href="<?= e($_cp['url']) ?>">📍 <?= e($_cp['nom']) ?></a><?php endforeach; unset($_cs, $_cp); ?>
    </div>
    <?php endif; ?>
    <div style="text-align:center;margin-top:2.5rem;">
      <a class="btn btn-p" href="<?= e(route_url('contact')) ?>"><?= e(setting('zones_btn','Demander une intervention')) ?> →</a>
    </div>
  </div>
</section>

<?= render_assurance_block() ?>

<!-- STATS -->
<?php $_stats = array_values(array_filter($banner['stats'], static fn(array $s): bool => trim((string)$s['number']) !== '' && !claim_unsupported($s['number'].' '.$s['label']) && !preg_match('/\d\s*\+|<|\d[.,]\d|note|interventions\s*\/\s*an/iu', $s['number'].' '.$s['label']))); if ($_stats): ?>
<div class="stats-band">
  <div class="wrap stats-grid">
    <?php foreach ($_stats as $s): ?>
    <div class="stat-item">
      <span class="stat-n"><?= e($s['number']) ?></span>
      <span class="stat-l"><?= e($s['label']) ?></span>
    </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; unset($_stats); ?>

<!-- PROCESSUS -->
<section class="process-section">
  <div class="wrap">
    <div class="svc-label"><?= e(setting('process_label','Comment ça marche')) ?></div>
    <h2 class="section-title"><?= e(setting('process_title','Votre intervention en')) ?> <em><?= e(setting('process_title_hl','3 étapes')) ?></em></h2>
    <div class="process-grid">
      <?php $_pn=0; foreach ([
        [setting('process_1_title','Vous nous contactez'),        claim_clean(setting('process_1_text','Appelez ou demandez à être rappelé : nous faisons le point sur votre besoin au téléphone.'), 'Appelez ou demandez à être rappelé : nous faisons le point sur votre besoin au téléphone.')],
        [setting('process_2_title','Nous organisons l\'intervention'), claim_clean(setting('process_2_text', intervention_terms_text()))],
        [setting('process_3_title','Intervention & compte rendu'), claim_clean(setting('process_3_text','Diagnostic, réparation ou installation. Compte rendu clair et facture détaillée en fin de chantier.'), 'Diagnostic, réparation ou installation. Compte rendu clair et facture détaillée en fin de chantier.')],
      ] as [$title,$text]): $_pn++; ?>
      <div class="process-step">
        <div class="process-num"><?= $_pn ?></div>
        <div><div class="process-h"><?= e($title) ?></div><p class="process-p"><?= e($text) ?></p></div>
      </div>
      <?php endforeach; unset($_pn); ?>
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
        <?php render_realisation_media($r); ?>
        <div class="real-body">
          <?php if (trim((string)($r['service_type']??''))!==''): ?><div class="real-svc"><?= e($r['service_type']) ?></div><?php endif; ?>
          <div class="real-h"><?= e($r['title']) ?></div>
          <?= realisation_meta($r) ?>
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

<!-- AVIS : seulement des témoignages dont la provenance est notée, ou le lien vers la fiche Google -->
<?php if ($revs || review_summary() || google_profile_url() !== ''): ?>
<section class="reviews-section">
  <div class="wrap">
    <div class="reviews-header">
      <div>
        <div class="svc-label"><?= e(setting('reviews_label','Avis clients')) ?></div>
        <h2 class="section-title"><?= e(setting('reviews_title','Ce qu\'ils disent')) ?> <em><?= e(setting('reviews_title_hl','de nous')) ?></em></h2>
      </div>
      <?php $_rs = review_summary(); if ($_rs): ?>
      <a class="reviews-score" href="<?= e($_rs['url']) ?>" target="_blank" rel="noopener">
        <div>
          <span class="rs-stars" aria-hidden="true">★★★★★</span>
          <span class="rs-num"><?= e(number_format($_rs['rating'], 1, ',', '')) ?>/5</span>
          <span class="rs-info"><?= e((string)$_rs['count']) ?> avis Google<?= $_rs['updated'] !== '' ? ' — au '.e($_rs['updated']) : '' ?></span>
        </div>
      </a>
      <?php elseif (($_gp = google_profile_url()) !== ''): ?>
      <a class="reviews-score" href="<?= e($_gp) ?>" target="_blank" rel="noopener"><div><span class="rs-info">Voir nos avis sur Google →</span></div></a>
      <?php endif; unset($_rs, $_gp); ?>
    </div>
    <div class="reviews-grid">
      <?php foreach ($revs as $rev):
        $init = mb_strtoupper(mb_substr($rev['author_name'],0,1,'UTF-8'),'UTF-8');
      ?>
      <a class="review-card" href="<?= e(route_url('avis')) ?>" aria-label="Lire tous les avis clients">
        <div class="rv-stars"><?= str_repeat('★',(int)$rev['rating']) ?></div>
        <p class="rv-text">"<?= e($rev['content']) ?>"</p>
        <div class="rv-author">
          <div class="rv-avatar"><?= e($init) ?></div>
          <div>
            <span class="rv-name"><?= e($rev['author_name']) ?></span>
            <?= date_tag($rev['review_date'] ?? '', true, 'rv-date') ?>
            <?php if (trim((string)($rev['service_type']??''))!==''): ?>
            <span class="rv-svc"><?= e($rev['service_type']) ?><?php if(trim((string)($rev['city']??''))!==''): ?> — <?= e($rev['city']) ?><?php endif; ?></span>
            <?php endif; ?>
          </div>
        </div>
      </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<!-- DEVIS SECTION -->
<section class="quote-section">
  <div class="wrap qs-grid">
    <div class="qs-info">
      <div class="svc-label"><?= e(setting('qs_label','Devis gratuit')) ?></div>
      <h2><?= e(setting('qs_title','Besoin d\'un')) ?> <em><?= e(setting('qs_title_hl','technicien ?')) ?></em></h2>
      <p><?= e(claim_clean(setting('qs_lead','Décrivez votre besoin : nous vous rappelons pour en parler et vous annoncer le prix avant tout déplacement. Aucune visite facturée sans votre accord.'), 'Décrivez votre besoin : nous vous rappelons pour en parler et vous annoncer le prix avant tout déplacement. Aucune visite facturée sans votre accord.')) ?></p>
      <div class="qs-perks">
        <?php foreach ([
          setting('qs_perk_1','Devis gratuit et sans engagement'),
          setting('qs_perk_2', callback_promise_short()),
          setting('qs_perk_3','Prix annoncé avant toute intervention'),
          setting('qs_perk_4','Technicien qualifié'),
          setting('qs_perk_5','Urgences 24h/24, interventions planifiées '.booking_weekdays_label()),
          setting('qs_perk_6','Facture détaillée en fin de chantier'),
        ] as $perk): if (trim($perk)==='' || claim_unsupported($perk)) continue; ?>
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

<?php if ($currentZone && !empty(zone_faq($currentZone))): ?>
<!-- FAQ ZONE -->
<section class="sec sec-navy"><div class="wrap" style="max-width:860px;">
  <div class="svc-label">FAQ</div>
  <h2 class="section-title">Questions <em>fréquentes</em></h2>
  <div class="sfaq-list" style="margin-top:1.75rem;">
    <?php foreach (zone_faq($currentZone) as [$_fq,$_fa]): ?>
    <details class="sfaq-item"><summary><?= e($_fq) ?></summary><p><?= e(faq_answer_fix((string)$_fq, (string)$_fa)) ?></p></details>
    <?php endforeach; ?>
  </div>
</div></section>
<?php endif; ?>

<?php render_footer(); exit; }

/* ════ ZONES D'INTERVENTION ════ */
if ($route === 'zones') {
    $zones = zones_page_settings();
    $cards = service_cards_v14();
    $meta  = [
        'title'       => setting('zones_meta_title', 'Zones d\'intervention | '.company_name()),
        'description' => setting('zones_meta_desc',  'EMAE intervient {en_region} — électricité, plomberie, chauffage, climatisation. Urgence 24h/7j.'),
        'canonical'   => route_url('zones'),
    ];
    render_head($meta); render_header(route_url('zones'));
?>
<section class="page-hero">
  <div class="wrap">
    <div class="ph-eyebrow">// <?= e($zones['eyebrow']) ?></div>
    <h1 class="ph-h1"><?= e($zones['title']) ?> <em><?= e($zones['title_hl']) ?></em></h1>
    <p class="ph-lead"><?= e(claim_clean($zones['lead'], 'Électricité, plomberie, chauffage et climatisation dans nos zones. '.intervention_terms_text())) ?></p>
    <div class="ph-badges">
      <span class="ph-badge hl">⚡ Urgence 24h/7j</span>
      <span class="ph-badge">🆓 Devis gratuit</span>
      <span class="ph-badge">🔒 Artisans qualifiés</span>
      <span class="ph-badge">📞 <?= e(company_phone()) ?></span>
    </div>
  </div>
</section>

<section class="sec sec-navy" style="padding:4rem 0;">
  <div class="wrap">
    <div class="svc-label"><?= e(setting('zp_regions_label','Nos régions')) ?></div>
    <h2 class="section-title"><?= e(setting('zp_regions_title','Nos')) ?> <em><?= e(setting('zp_regions_title_hl','zones couvertes')) ?></em></h2>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:1.5rem;margin-top:2.5rem;">
      <?php foreach ($zones['regions'] as $reg): ?>
      <div style="background:var(--card);border:1px solid rgba(255,255,255,.08);border-radius:var(--r4);overflow:hidden;">
        <div style="background:linear-gradient(135deg,<?= e($reg['color']) ?>22,<?= e($reg['color']) ?>08);padding:2rem 2rem 1.5rem;border-bottom:1px solid rgba(255,255,255,.06);">
          <div style="display:flex;align-items:center;gap:1rem;margin-bottom:.75rem;">
            <div>
              <div style="font-family:var(--font-h);font-size:1.4rem;font-weight:700;color:#fff;">
                <?php if (!empty($reg['slug'])): ?><a href="<?= e(url_for($reg['slug'].'/')) ?>" style="color:inherit;text-decoration:none;"><?= e($reg['name']) ?></a><?php else: ?><?= e($reg['name']) ?><?php endif; ?>
              </div>
              <?php if (trim((string)$reg['delay']) !== ''): ?>
              <div style="font-size:.78rem;font-weight:700;color:<?= e($reg['color']) ?>;text-transform:uppercase;letter-spacing:.1em;margin-top:.15rem;"><?= e($reg['delay']) ?></div>
              <?php endif; ?>
            </div>
          </div>
          <?php if (!empty($reg['intro'])): ?>
          <p style="font-size:.9rem;color:var(--t2);margin:.35rem 0 0;line-height:1.5;"><?= e($reg['intro']) ?></p>
          <?php endif; ?>
          <?php if (!empty($reg['depts'])): ?>
          <div style="display:flex;flex-wrap:wrap;gap:.4rem;margin-top:1rem;">
            <?php foreach ($reg['depts'] as $d): ?>
            <span style="font-size:.72rem;background:rgba(255,255,255,.06);color:var(--t1);border:1px solid rgba(255,255,255,.1);padding:.22rem .65rem;border-radius:4px;"><?= e($d) ?></span>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>
        </div>
        <?php if (!empty($reg['cities'])): ?>
        <div style="padding:1.25rem 2rem 1.75rem;">
          <div style="font-size:.72rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:var(--t2);margin-bottom:.75rem;">Principales villes</div>
          <div style="display:flex;flex-wrap:wrap;gap:.35rem;">
            <?php foreach ($reg['cities'] as $city): ?>
            <span style="font-size:.8rem;color:var(--t1);background:rgba(255,255,255,.04);padding:.28rem .7rem;border-radius:20px;border:1px solid rgba(255,255,255,.07);"><?= e($city) ?></span>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="sec sec-navy" style="padding:4rem 0;">
  <div class="wrap" style="max-width:760px;">
    <div class="svc-label">Intervention rapide</div>
    <h2 class="section-title">Demandez votre <em>devis gratuit</em></h2>
    <p class="section-lead" style="margin-bottom:2rem;">Précisez votre ville et votre besoin : nous vous rappelons pour confirmer la disponibilité dans votre secteur. <?= e(callback_promise_short()) ?>.</p>
    <div class="hero-card" style="background:var(--card);">
      <div class="hero-card-tag">Gratuit et sans engagement</div>
      <h2 style="font-family:var(--font-h);font-size:1.3rem;font-weight:700;color:#fff;margin-bottom:1.25rem;">Obtenir un devis</h2>
      <?php render_quote_form($cards, 'zones'); ?>
    </div>
  </div>
</section>
<?php render_footer(); exit; }

/* ════ AVIS CLIENTS ════ */
if ($route === 'avis') {
    $allReviews  = all_published_reviews();
    $rs          = review_summary();      // note Google sourcée (lien + date), sinon aucun chiffre
    $gmbUrl      = google_profile_url();
    $meta = [
        'title'       => setting('avis_meta_title', 'Avis clients | '.company_name()),
        'description' => claim_clean(setting('avis_meta_desc', 'Témoignages de clients de '.company_name().' sur nos interventions en électricité, plomberie, chauffage et climatisation.'),
                                     'Témoignages de clients de '.company_name().' sur nos interventions en électricité, plomberie, chauffage et climatisation.'),
        'canonical'   => route_url('avis'),
    ];
    render_head($meta); render_header(route_url('avis'));
?>
<section class="page-hero">
  <div class="wrap">
    <div class="ph-eyebrow">// Avis clients</div>
    <h1 class="ph-h1"><?= e(setting('avis_title','Ce que disent')) ?> <em><?= e(setting('avis_title_hl','nos clients')) ?></em></h1>
    <p class="ph-lead"><?= e(claim_clean(setting('avis_lead','Les témoignages de nos clients.'), 'Les témoignages de nos clients.')) ?></p>
    <?php if ($rs || $gmbUrl !== ''): ?>
    <div style="display:inline-flex;align-items:center;gap:1.25rem;background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.12);border-radius:var(--r4);padding:1rem 1.75rem;margin-top:1.5rem;flex-wrap:wrap;">
      <?php if ($rs): ?>
      <div style="text-align:center;">
        <div style="font-family:var(--font-h);font-size:3rem;font-weight:700;color:var(--p);line-height:1;"><?= e(number_format($rs['rating'], 1, ',', '')) ?></div>
        <div style="font-size:.75rem;color:var(--t2);margin-top:.2rem;">sur 5</div>
      </div>
      <?php endif; ?>
      <div>
        <?php if ($rs): ?>
        <div style="font-size:.85rem;color:var(--t1);font-weight:600;"><?= e((string)$rs['count']) ?> avis sur notre fiche Google<?= $rs['updated'] !== '' ? ' (relevé du '.e($rs['updated']).')' : '' ?></div>
        <?php endif; ?>
        <a href="<?= e($rs['url'] ?? $gmbUrl) ?>" target="_blank" rel="noopener noreferrer" style="display:inline-flex;align-items:center;gap:.35rem;font-size:.85rem;color:var(--p);margin-top:.4rem;font-weight:600;">Voir les avis sur Google →</a>
      </div>
    </div>
    <?php endif; ?>

  </div>
</section>

<section class="sec sec-navy">
  <div class="wrap">
    <?php if (empty($allReviews)): ?>
      <p style="text-align:center;color:var(--t2);padding:3rem 0;">Aucun avis pour le moment.</p>
    <?php else: ?>
    <div class="reviews-grid" style="margin-top:1rem;">
      <?php foreach ($allReviews as $rv):
        $stars = max(1,min(5,(int)($rv['rating'] ?? 5)));
        $starsHtml = str_repeat('★',$stars).str_repeat('☆',5-$stars);
      ?>
      <div class="review-card">
        <div class="rv-head">
          <div class="rv-avatar"><?= mb_strtoupper(mb_substr($rv['author_name']??'?',0,1,'UTF-8'),'UTF-8') ?></div>
          <div>
            <div class="rv-name"><?= e($rv['author_name'] ?? '') ?></div>
            <?= date_tag($rv['review_date'] ?? '', true, 'rv-date') ?>
            <?php if (trim($rv['service_type']??'') !== ''): ?>
            <div class="rv-svc"><?= e($rv['service_type']) ?></div>
            <?php endif; ?>
          </div>
          <div class="rv-stars" style="margin-left:auto;color:#f59e0b;font-size:1rem;"><?= $starsHtml ?></div>
        </div>
        <?php if (trim($rv['content']??'') !== ''): ?>
        <p class="rv-body"><?= e($rv['content']) ?></p>
        <?php endif; ?>
        <?php if (trim($rv['city']??'') !== ''): ?>
        <div style="font-size:.75rem;color:var(--t2);margin-top:.75rem;">📍 <?= e($rv['city']) ?></div>
        <?php endif; ?>
        <div style="font-size:.72rem;color:var(--t2);margin-top:.35rem;">Provenance : <?= e((string)($rv['source_note'] ?? '')) ?></div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <?php if ($gmbUrl !== ''): ?>
    <div style="text-align:center;margin-top:3rem;">
      <a class="btn btn-p btn-lg" href="<?= e($gmbUrl) ?>" target="_blank" rel="noopener noreferrer">⭐ Laisser un avis sur Google</a>
    </div>
    <?php endif; ?>
  </div>
</section>
<?php render_footer(); exit; }


/* ════════════════════════════════════════════════════════════════
   LANDING PAGE DYNAMIQUE — ?route=landing&ville=X&dept=Y&metier=Z
   Utilisée pour les campagnes Google Ads géolocalisées.
   Zones : Bourgogne-Franche-Comté, Auvergne-Rhône-Alpes, Île-de-France
════════════════════════════════════════════════════════════════ */
if ($route === 'landing') {

    /* ── Données départements ── */
    $landing_depts = [
        /* Bourgogne-Franche-Comté */
        'jura'     => ['nom'=>'Jura',       'code'=>'39','region'=>'Bourgogne-Franche-Comté','chef_lieu'=>'Lons-le-Saunier',
                       'villes'=>['Lons-le-Saunier','Dole','Saint-Claude','Champagnole','Morez','Poligny','Orgelet','Arbois']],
        'doubs'    => ['nom'=>'Doubs',      'code'=>'25','region'=>'Bourgogne-Franche-Comté','chef_lieu'=>'Besançon',
                       'villes'=>['Besançon','Pontarlier','Audincourt','Morteau','Valentigney','Baume-les-Dames','Maîche']],
        'cote-dor' => ['nom'=>"Côte-d'Or",  'code'=>'21','region'=>'Bourgogne-Franche-Comté','chef_lieu'=>'Dijon',
                       'villes'=>['Dijon','Beaune','Chenôve','Longvic','Quetigny','Talant','Auxonne','Montbard']],
        /* Auvergne-Rhône-Alpes */
        'ain'         => ['nom'=>'Ain',          'code'=>'01','region'=>'Auvergne-Rhône-Alpes','chef_lieu'=>'Bourg-en-Bresse',
                          'villes'=>['Bourg-en-Bresse','Oyonnax','Ambérieu-en-Bugey','Bellegarde-sur-Valserine','Gex','Miribel','Ferney-Voltaire','Pont-d\'Ain']],
        'isere'       => ['nom'=>'Isère',        'code'=>'38','region'=>'Auvergne-Rhône-Alpes','chef_lieu'=>'Grenoble',
                          'villes'=>['Grenoble','Vienne','Bourgoin-Jallieu','Échirolles','Voiron','Crolles','Vizille','Romans-sur-Isère']],
        'rhone'       => ['nom'=>'Rhône',        'code'=>'69','region'=>'Auvergne-Rhône-Alpes','chef_lieu'=>'Lyon',
                          'villes'=>['Lyon','Villeurbanne','Vénissieux','Caluire-et-Cuire','Bron','Saint-Priest','Décines-Charpieu','Rillieux-la-Pape']],
        'loire'       => ['nom'=>'Loire',        'code'=>'42','region'=>'Auvergne-Rhône-Alpes','chef_lieu'=>'Saint-Étienne',
                          'villes'=>['Saint-Étienne','Roanne','Andrézieux-Bouthéon','Saint-Chamond','Firminy','Montbrison','Le Chambon-Feugerolles']],
        'savoie'      => ['nom'=>'Savoie',       'code'=>'73','region'=>'Auvergne-Rhône-Alpes','chef_lieu'=>'Chambéry',
                          'villes'=>['Chambéry','Aix-les-Bains','Albertville','Moûtiers','Bourg-Saint-Maurice','Modane']],
        'haute-savoie'=> ['nom'=>'Haute-Savoie', 'code'=>'74','region'=>'Auvergne-Rhône-Alpes','chef_lieu'=>'Annecy',
                          'villes'=>['Annecy','Thonon-les-Bains','Annemasse','Sallanches','Cluses','Évian-les-Bains','Bonneville']],
        'drome'       => ['nom'=>'Drôme',        'code'=>'26','region'=>'Auvergne-Rhône-Alpes','chef_lieu'=>'Valence',
                          'villes'=>['Valence','Romans-sur-Isère','Montélimar','Bourg-lès-Valence','Pierrelatte','Die']],
        'puy-de-dome' => ['nom'=>'Puy-de-Dôme',  'code'=>'63','region'=>'Auvergne-Rhône-Alpes','chef_lieu'=>'Clermont-Ferrand',
                          'villes'=>['Clermont-Ferrand','Thiers','Riom','Issoire','Ambert','Vichy','Cusset']],
        'haute-loire' => ['nom'=>'Haute-Loire',  'code'=>'43','region'=>'Auvergne-Rhône-Alpes','chef_lieu'=>'Le Puy-en-Velay',
                          'villes'=>['Le Puy-en-Velay','Brioude','Yssingeaux','Monistrol-sur-Loire','La Chaise-Dieu']],
        'allier'      => ['nom'=>'Allier',       'code'=>'03','region'=>'Auvergne-Rhône-Alpes','chef_lieu'=>'Moulins',
                          'villes'=>['Moulins','Vichy','Montluçon','Cusset','Gannat','Thiers']],
        'ardeche'     => ['nom'=>'Ardèche',      'code'=>'07','region'=>'Auvergne-Rhône-Alpes','chef_lieu'=>'Privas',
                          'villes'=>['Privas','Aubenas','Annonay','Tournon-sur-Rhône','Guilherand-Granges']],
        'cantal'      => ['nom'=>'Cantal',       'code'=>'15','region'=>'Auvergne-Rhône-Alpes','chef_lieu'=>'Aurillac',
                          'villes'=>['Aurillac','Saint-Flour','Mauriac','Riom-ès-Montagnes']],
        /* Île-de-France */
        'paris'       => ['nom'=>'Paris',            'code'=>'75','region'=>'Île-de-France','chef_lieu'=>'Paris',
                          'villes'=>['Paris','Boulogne-Billancourt','Vincennes','Neuilly-sur-Seine','Montrouge','Ivry-sur-Seine']],
        'seine-et-marne' => ['nom'=>'Seine-et-Marne','code'=>'77','region'=>'Île-de-France','chef_lieu'=>'Melun',
                          'villes'=>['Melun','Meaux','Chelles','Pontault-Combault','Savigny-le-Temple','Marne-la-Vallée','Fontainebleau']],
        'yvelines'    => ['nom'=>'Yvelines',          'code'=>'78','region'=>'Île-de-France','chef_lieu'=>'Versailles',
                          'villes'=>['Versailles','Mantes-la-Jolie','Sartrouville','Trappes','Poissy','Saint-Germain-en-Laye']],
        'essonne'     => ['nom'=>'Essonne',            'code'=>'91','region'=>'Île-de-France','chef_lieu'=>'Évry-Courcouronnes',
                          'villes'=>['Évry','Corbeil-Essonnes','Massy','Palaiseau','Viry-Châtillon','Longjumeau']],
        'hauts-de-seine' => ['nom'=>'Hauts-de-Seine', 'code'=>'92','region'=>'Île-de-France','chef_lieu'=>'Nanterre',
                          'villes'=>['Nanterre','Boulogne-Billancourt','Colombes','Rueil-Malmaison','Levallois-Perret','Issy-les-Moulineaux']],
        'seine-saint-denis' => ['nom'=>'Seine-Saint-Denis','code'=>'93','region'=>'Île-de-France','chef_lieu'=>'Bobigny',
                          'villes'=>['Saint-Denis','Montreuil','Aubervilliers','Vitry-sur-Seine','Noisy-le-Grand','Bobigny']],
        'val-de-marne'  => ['nom'=>'Val-de-Marne',   'code'=>'94','region'=>'Île-de-France','chef_lieu'=>'Créteil',
                          'villes'=>['Créteil','Vitry-sur-Seine','Champigny-sur-Marne','Saint-Maur-des-Fossés','Vincennes','Ivry-sur-Seine']],
        'val-d-oise'    => ['nom'=>"Val-d'Oise",      'code'=>'95','region'=>'Île-de-France','chef_lieu'=>'Cergy',
                          'villes'=>['Cergy','Argenteuil','Sarcelles','Osny','Pontoise','Saint-Ouen-l\'Aumône']],
    ];

    /* ── Données métiers ── */
    $landing_metiers = [
        'electricite' => [
            'label'    => 'Électricité',
            'label_pro'=> 'Électricien',
            'icon'     => '⚡',
            'urgence'  => 'Panne électrique, disjoncteur, court-circuit',
            'desc'     => 'Dépannage électrique d\'urgence, mise aux normes NF C 15-100, installation tableau, prises et circuits.',
            'services' => ['Dépannage panne électrique','Remplacement tableau électrique','Mise aux normes NF C 15-100','Ajout prises et circuits','Éclairage intérieur/extérieur','Rénovation électrique complète'],
        ],
        'plomberie' => [
            'label'    => 'Plomberie',
            'label_pro'=> 'Plombier',
            'icon'     => '💧',
            'urgence'  => 'Fuite d\'eau, dégât des eaux, canalisation bouchée',
            'desc'     => 'Recherche et réparation de fuites, débouchage, remplacement sanitaires, chauffe-eau.',
            'services' => ['Recherche et réparation de fuites','Débouchage canalisations','Remplacement robinetterie','Chauffe-eau — diagnostic et remplacement','Installation sanitaires','Entretien préventif réseau'],
        ],
        'chauffage' => [
            'label'    => 'Chauffage & PAC',
            'label_pro'=> 'Technicien chauffage',
            'icon'     => '🔥',
            'urgence'  => 'Panne chaudière, chauffage en panne, PAC HS',
            'desc'     => 'Dépannage chaudière gaz/fioul/électrique, pompe à chaleur, entretien annuel réglementaire.',
            'services' => ['Dépannage chaudière gaz, fioul, électrique','Entretien annuel réglementaire','Pompe à chaleur air/air et air/eau','Installation chaudière ou PAC neuve','Réglage thermostat et programmation','Urgence chauffage hiver'],
        ],
        'climatisation' => [
            'label'    => 'Climatisation & CVC',
            'label_pro'=> 'Technicien climatisation',
            'icon'     => '❄️',
            'urgence'  => 'Climatisation en panne, entretien saisonnier',
            'desc'     => 'Installation, dépannage et entretien de climatisation split, multi-split, gainable et CVC.',
            'services' => ['Dépannage climatiseur (split, multi-split)','Installation climatisation neuve','Entretien saisonnier (nettoyage, réglages)','Recharge fluide frigorigène','VMC et ventilation mécanique','Étude et conseil avant installation'],
        ],
    ];

    /* ── Lecture et validation des paramètres GET ── */
    $p_dept   = strtolower(trim((string)($_GET['dept']   ?? '')));
    $p_metier = strtolower(trim((string)($_GET['metier'] ?? '')));
    $p_ville  = trim((string)($_GET['ville'] ?? ''));

    /* Ancienne page d'annonce d'une ville qui a sa page métier propre (/intervention/dole/electricien) :
       redirection permanente, en gardant les paramètres de campagne (gclid, utm…). */
    $_cp = $p_ville !== '' ? city_page_by_name($p_ville) : null;
    $_ts = city_trade_slug(['plombier'=>'plomberie','electricien'=>'electricite','depannage-chauffage'=>'chauffage'][$p_metier] ?? $p_metier);
    if ($_cp && $_ts !== '' && $_SERVER['REQUEST_METHOD'] === 'GET') {
        $_keep = array_diff_key($_GET, array_flip(['route', 'dept', 'metier', 'ville']));
        header('Location: '.absolute_url(url_for('intervention/'.$_cp['slug'].'/'.$_ts)).($_keep ? '?'.http_build_query($_keep) : ''), true, 301);
        exit;
    }
    unset($_cp, $_ts);

    /* Île-de-France : activité arrêtée pour l'instant (décision du 10/11/2026). Page retirée (410),
       plutôt qu'un contenu du Doubs affiché à une adresse « Melun » ou « Évry ». */
    if (in_array($p_dept, ['paris','seine-et-marne','yvelines','essonne','hauts-de-seine','seine-saint-denis','val-de-marne','val-d-oise'], true)) {
        http_response_code(410);
        render_head(['title' => 'Page retirée | '.company_name(), 'description' => 'Cette page n\'existe plus.', 'canonical' => route_url(''), 'robots' => 'noindex']);
        render_header('');
        echo '<section class="page-hero"><div class="wrap"><div class="ph-eyebrow">Page retirée</div><h1 class="ph-h1">Nous n\'intervenons pas dans ce secteur pour le moment</h1><p class="ph-lead">'.e(company_name()).' intervient en Côte-d\'Or, dans le Jura et le Doubs.</p><div class="btn-row" style="margin-top:1.5rem;"><a class="btn btn-p" href="'.e(url_for('intervention/')).'">Voir nos villes</a><a class="btn btn-outline" href="'.e(route_url('')).'">Accueil</a></div></div></section>';
        render_footer(); exit;
    }
    /* Valider dept contre la whitelist (zone principale par défaut) */
    if (!isset($landing_depts[$p_dept])) $p_dept = 'doubs';

    /* Valider métier contre la whitelist (anciens liens : « plombier »…) */
    $p_metier = ['plombier'=>'plomberie','electricien'=>'electricite','depannage-chauffage'=>'chauffage'][$p_metier] ?? $p_metier;
    if (!isset($landing_metiers[$p_metier])) $p_metier = 'electricite';

    /* Valider la ville — doit appartenir au département ou être vide */
    $dept_data   = $landing_depts[$p_dept];
    $metier_data = $landing_metiers[$p_metier];

    /* Nettoyer la ville : uniquement lettres, espaces, tirets, apostrophes */
    $p_ville = preg_replace('/[^a-zA-ZÀ-ÿ0-9 \'\-]/u', '', $p_ville);
    $p_ville = mb_convert_case(trim($p_ville), MB_CASE_TITLE, 'UTF-8');

    /* Fallback sur le chef-lieu si ville vide ou inconnue */
    /* Comparaison sans casse : « Savigny-le-Temple » devient « Savigny-Le-Temple » au-dessus */
    $villes_dept = $dept_data['villes'];
    $match = null;
    foreach ($villes_dept as $v) {
        if (mb_strtolower($v, 'UTF-8') === mb_strtolower($p_ville, 'UTF-8')) { $match = $v; break; }
    }
    $p_ville = $match ?? $dept_data['chef_lieu'];

    /* ── Construction des textes dynamiques ── */
    $h1      = $metier_data['label_pro'].' urgence à '.$p_ville.' ('.$dept_data['nom'].' '.$dept_data['code'].')';
    $meta_t  = $metier_data['label_pro'].' à '.$p_ville.' — Urgence 24h/7j | '.company_name();
    $meta_d  = company_name().' — '.$metier_data['label_pro'].' d\'urgence à '.$p_ville.' dans le '.$dept_data['nom'].' ('.$dept_data['code'].'). Intervention rapide, devis gratuit, disponible 24h/7j.';
    $canonical = route_url('landing').'&dept='.urlencode($p_dept).'&metier='.urlencode($p_metier).'&ville='.rawurlencode($p_ville);

    $cards = service_cards_v14();
    $meta  = ['title'=>$meta_t, 'description'=>$meta_d, 'canonical'=>$canonical];
    render_head($meta);
    render_header('');
?>

<!-- HERO LANDING -->
<section class="page-hero" style="padding:3rem 0 2.5rem;">
  <div class="wrap">
    <div class="ph-eyebrow">// <?= e($metier_data['icon']) ?> <?= e($metier_data['label']) ?> — <?= e($dept_data['region']) ?></div>
    <h1 class="ph-h1"><?= e($h1) ?></h1>
    <p class="ph-lead"><?= e($metier_data['desc']) ?> Disponible <?= e(company_hours()) ?>.</p>
    <div class="ph-badges">
      <span class="ph-badge hl">⚡ Urgence 24h/7j</span>
      <span class="ph-badge hl"><?= e($metier_data['urgence']) ?></span>
      <span class="ph-badge">🆓 Devis gratuit</span>
      <span class="ph-badge">🔒 Artisans qualifiés</span>
      <span class="ph-badge">📍 <?= e($p_ville) ?> et alentours</span>
    </div>
    <div class="btn-row" style="margin-top:1.75rem;">
      <a class="btn btn-p btn-lg" href="<?= e(company_phone_link()) ?>">📞 <?= e(company_phone()) ?></a>
      <a class="btn btn-outline btn-lg" href="#lp-form">📋 Devis gratuit</a>
    </div>
  </div>
</section>

<!-- SERVICES DU MÉTIER -->
<section class="sec sec-card">
  <div class="wrap">
    <div class="svc-label">Nos interventions</div>
    <h2 class="section-title"><?= e($metier_data['label_pro']) ?> à <em><?= e($p_ville) ?></em></h2>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:.6rem;margin-top:1.75rem;">
      <?php foreach ($metier_data['services'] as $svc): ?>
      <div class="qs-perk"><?= e($svc) ?></div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- VILLES DU DÉPARTEMENT -->
<section class="sec sec-navy">
  <div class="wrap">
    <div class="svc-label">Zone d'intervention</div>
    <h2 class="section-title">Interventions dans le <em><?= e($dept_data['nom']) ?> (<?= e($dept_data['code']) ?>)</em></h2>
    <p class="section-lead">Nous intervenons à <?= e($p_ville) ?> et dans toutes les communes du département <?= e($dept_data['nom']) ?> (<?= e($dept_data['region']) ?>).</p>
    <div style="display:flex;flex-wrap:wrap;gap:.4rem;margin-top:1.25rem;">
      <?php foreach ($villes_dept as $v): ?>
      <span class="zone-chip" style="<?= $v===$p_ville ? 'background:rgba(240,123,29,.15);border-color:rgba(240,123,29,.4);color:var(--p);font-weight:600;' : '' ?>">
        📍 <?= e($v) ?>
      </span>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- POURQUOI NOUS -->
<section class="sec sec-card">
  <div class="wrap">
    <div class="svc-label">Pourquoi EMAE</div>
    <h2 class="section-title">Votre <?= e(mb_strtolower($metier_data['label_pro'],'UTF-8')) ?> de confiance à <em><?= e($p_ville) ?></em></h2>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:.85rem;margin-top:1.75rem;">
      <?php foreach ([
        ['⚡','Urgences 24h/24',intervention_terms_text()],
        ['🆓','Devis gratuit','Sans engagement, prix annoncé avant intervention.'],
        ['🔒','Artisans qualifiés','Électricité, plomberie, chauffage et climatisation.'],
        ['📞',callback_promise_short(),'Laissez votre numéro, nous vous rappelons au sujet de votre demande.'],
      ] as [$ico,$t,$d]): ?>
      <div class="why-card">
        <div class="why-icon"><?= $ico ?></div>
        <div><div class="why-h"><?= e($t) ?></div><p class="why-p"><?= e($d) ?></p></div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<?php $lpReals = array_values(array_filter(realisations_local(3, $p_ville, $villes_dept), static fn(array $r): bool => $r['local'] > 0)); if ($lpReals): ?>
<!-- RÉALISATIONS PRÈS DE LA VILLE -->
<section class="sec sec-navy">
  <div class="wrap">
    <div class="svc-label">Nos réalisations</div>
    <h2 class="section-title">Chantiers réalisés près de <em><?= e($p_ville) ?></em></h2>
    <div class="reals-grid" style="margin-top:1.75rem;">
      <?php foreach ($lpReals as $r): ?>
      <div class="real-card">
        <?php render_realisation_media($r); ?>
        <div class="real-body">
          <?php if (trim((string)($r['service_type']??''))!==''): ?><div class="real-svc"><?= e($r['service_type']) ?></div><?php endif; ?>
          <div class="real-h"><?= e($r['title']) ?></div>
          <?= realisation_meta($r) ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; unset($lpReals); ?>

<!-- FORMULAIRE DEVIS -->
<section class="sec sec-navy" id="lp-form">
  <div class="wrap" style="max-width:700px;">
    <div class="svc-label">Devis gratuit — <?= e($metier_data['label']) ?> à <?= e($p_ville) ?></div>
    <div class="qs-card">
      <div class="hero-card-tag"><?= e($metier_data['icon']) ?> <?= e($metier_data['label_pro']) ?> à <?= e($p_ville) ?></div>
      <h2 style="font-family:var(--font-h);font-size:1.35rem;font-weight:700;color:#fff;margin-bottom:1.25rem;">
        Demande d'intervention — <?= e($dept_data['nom']) ?> (<?= e($dept_data['code']) ?>)
      </h2>
      <?php render_quote_form($cards, 'landing_'.$p_metier.'_'.$p_dept); ?>
    </div>
  </div>
</section>

<?php render_footer(); exit; }

/* ════ FAQ ════ */
if ($route === 'faq') {
    $faq  = faq_page_settings();
    // Réponses mises en cohérence (zones, horaires, délais) avant l'affichage ET les données structurées.
    foreach ($faq['groups'] as $_gi => $_g) foreach (($_g['items'] ?? []) as $_ii => $_it) {
        $faq['groups'][$_gi]['items'][$_ii]['a'] = faq_answer_fix((string)($_it['q'] ?? ''), (string)($_it['a'] ?? ''));
    }
    unset($_gi, $_g, $_ii, $_it);
    $meta = ['title'=>setting('faq_meta_title','FAQ | '.company_name()),'description'=>setting('faq_meta_description','Questions fréquentes.'),'canonical'=>route_url('faq')];
    render_head($meta);
    // FAQPage schema — rich snippets Google
    $faqSchema = [];
    foreach ($faq['groups'] as $g) {
        foreach ($g['items'] ?? [] as $item) {
            if (($item['q'] ?? '') !== '' && ($item['a'] ?? '') !== '')
                $faqSchema[] = ['@type'=>'Question','name'=>$item['q'],'acceptedAnswer'=>['@type'=>'Answer','text'=>$item['a']]];
        }
    }
    if (!empty($faqSchema)) echo '<script type="application/ld+json">'.json_encode(['@context'=>'https://schema.org','@type'=>'FAQPage','mainEntity'=>$faqSchema],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES).'</script>';
    render_header(route_url('faq'));
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
      <a class="btn btn-outline btn-lg" href="<?= e(route_url($faq['cta_url'])) ?>"><?= e($faq['cta_button']) ?></a>
      <a class="btn btn-p btn-lg" href="<?= e(company_phone_link()) ?>">📞 <?= e(company_phone()) ?></a>
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
    <span class="ph-badge">⚡ <?= e(claim_clean(setting('contact_badge_2', callback_promise_short()), callback_promise_short())) ?></span>
    <span class="ph-badge">📍 <?= e(company_regions()) ?></span>
    <span class="ph-badge">🕐 <?= e(company_hours()) ?></span>
  </div>
</div></section>
<section class="sec sec-navy"><div class="wrap contact-layout">
  <div class="contact-box">
    <div class="cbox-h"><?= e($cfg['form_title']) ?></div>
    <div class="cbox-sub"><?= e($cfg['form_subtitle']) ?></div>
    <form method="post" class="cf-form" enctype="multipart/form-data">
      <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="form_type" value="quote">
      <input type="hidden" name="request_type" value="devis">
      <input type="hidden" name="source" value="contact_page">
      <input type="text" name="website" value="" class="hp" tabindex="-1" autocomplete="off">
      <div class="cf-row">
        <label class="cf-label">Nom complet *<input class="cf-input" type="text" name="full_name" placeholder="Votre nom" required></label>
        <label class="cf-label">Téléphone *<input class="cf-input" type="tel" name="phone" placeholder="06 00 00 00 00" required></label>
      </div>
      <div class="cf-row">
        <label class="cf-label">Email<input class="cf-input" type="email" name="email" placeholder="votre@email.fr"></label>
        <label class="cf-label">Ville<input class="cf-input" type="text" name="city" placeholder="<?= e(setting('home_quote_city_placeholder','Ex : {ville}…')) ?>"></label>
      </div>
      <label class="cf-label">Service<select class="cf-input" name="service_type"><option value="">Choisir</option><?php foreach($cards as $c):?><option value="<?=e($c['title'])?>"><?=e($c['title'])?></option><?php endforeach;?></select></label>
      <label class="cf-label">Urgence<select class="cf-input" name="urgency"><option value="Normale">Normale</option><option value="Urgente">Urgente — sous 24h</option><option value="Très urgente">Très urgente — immédiat</option></select></label>
      <label class="cf-label">Votre besoin *<textarea class="cf-input cf-textarea" name="message" placeholder="Décrivez votre demande…" required></textarea></label>
      <?php render_quote_photo_field(); ?>
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
        <?php /* Reprend automatiquement les villes des zones actives. */
        foreach (intervention_cities() as $z): ?><div class="zone-tag-sm"><?= e($z) ?></div><?php endforeach; ?>
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
  <p class="ph-lead"><?= e(claim_clean(setting('quote_hero_lead','Remplissez ce formulaire : nous vous rappelons pour faire le point et vous annoncer le prix avant tout déplacement. Sans engagement.'), 'Remplissez ce formulaire : nous vous rappelons pour faire le point et vous annoncer le prix avant tout déplacement. Sans engagement.')) ?></p>
  <div class="ph-badges"><span class="ph-badge hl">✓ Devis gratuit</span><span class="ph-badge">⚡ <?= e(callback_promise_short()) ?></span><span class="ph-badge">📍 <?=e(company_regions())?></span></div>
</div></section>
<section class="sec sec-navy"><div class="wrap" style="max-width:660px;">
  <div class="qs-card">
    <div class="hero-card-tag">📋 Formulaire de devis</div>
    <h2 style="font-family:var(--font-h);font-size:1.5rem;font-weight:800;color:#fff;margin-bottom:1.35rem;"><?= e(setting('quote_form_title','Décrivez votre besoin')) ?></h2>
    <?php
    // Bouton « Prendre RDV entretien » quand la page Rendez-vous est fermée : prestation pré-remplie.
    $_rp = rdv_prestations()[(string)($_GET['prestation'] ?? '')] ?? null;
    render_quote_form($cards, 'quote_page', 'devis', $_rp ? ['trade' => $_rp['trade'], 'message' => $_rp['label']] : []); unset($_rp); ?>
  </div>
</div></section>
<?php render_footer(); exit; }

/* ════ RÉALISATIONS ════ */
if ($route === 'realisations') {
    $realsAll = visible_realisations(1000);
    // Filtres par métier : seuls les métiers qui ont au moins une réalisation publiée.
    $realTrades = [];
    foreach ($realsAll as $r) foreach (realisation_trades((string)($r['service_type'] ?? '')) as $t) $realTrades[$t] = ($realTrades[$t] ?? 0) + 1;
    $realTrades = array_intersect_key(array_merge(array_fill_keys(array_keys(service_trade_defaults()), 0), $realTrades), $realTrades);
    $realFilter = (string)($_GET['metier'] ?? '');
    if (!isset(service_trade_defaults()[$realFilter])) $realFilter = '';
    $reals = $realFilter === '' ? $realsAll
           : array_values(array_filter($realsAll, static fn(array $r): bool => in_array($realFilter, realisation_trades((string)($r['service_type'] ?? '')), true)));
    $meta  = ['title'=>setting('reals_meta_title','Réalisations | '.company_name()),'description'=>setting('reals_meta_description','Nos chantiers et interventions.'),'canonical'=>route_url('realisations')];
    render_head($meta); render_header(route_url('realisations'));
?>
<section class="page-hero"><div class="wrap"><div class="ph-eyebrow">Nos réalisations</div>
  <h1 class="ph-h1"><?= e(setting('reals_page_title','Interventions & chantiers')) ?></h1>
  <p class="ph-lead"><?= e(setting('reals_page_lead','Des interventions propres et documentées {en_region}.')) ?></p>
</div></section>
<section class="sec sec-navy"><div class="wrap">
  <?php if (count($realTrades) > 1 || $realFilter !== ''): ?>
  <nav class="rf" aria-label="Filtrer par métier">
    <a class="rf-chip<?= $realFilter === '' ? ' is-on' : '' ?>" href="<?= e(route_url('realisations')) ?>"<?= $realFilter === '' ? ' aria-current="page"' : '' ?>>Toutes <span class="rf-n"><?= count($realsAll) ?></span></a>
    <?php foreach ($realTrades as $_t => $_n): ?>
    <a class="rf-chip<?= $realFilter === $_t ? ' is-on' : '' ?>" href="<?= e(url_with_query(route_url('realisations'), ['metier' => $_t])) ?>"<?= $realFilter === $_t ? ' aria-current="page"' : '' ?>><img src="<?= e(service_icon_url(service_trade_icon($_t))) ?>" alt="" width="20" height="20"><?= e(service_template($_t)['label']) ?> <span class="rf-n"><?= (int)$_n ?></span></a>
    <?php endforeach; unset($_t, $_n); ?>
  </nav>
  <?php endif; ?>
  <?php if (empty($reals) && $realFilter !== ''): ?>
    <div style="text-align:center;padding:3rem 0;color:var(--t2);">
      <p>Pas encore de réalisation publiée en <?= e(mb_strtolower(service_template($realFilter)['label'], 'UTF-8')) ?>.</p>
      <a class="btn btn-ghost-p" href="<?= e(route_url('realisations')) ?>" style="margin-top:1rem;display:inline-flex;">Voir toutes les réalisations</a>
    </div>
  <?php elseif (empty($reals)): ?>
    <div style="text-align:center;padding:4rem 0;color:var(--t2);">
      <div style="font-size:3rem;margin-bottom:1rem;">📷</div>
      <p><?= e(setting('reals_empty','Les réalisations seront publiées prochainement.')) ?></p>
      <a class="btn btn-p" href="<?= e(route_url('contact')) ?>" style="margin-top:1.5rem;display:inline-flex;"><?= e(setting('reals_empty_btn','Nous contacter')) ?></a>
    </div>
  <?php else: ?>
    <div class="reals-grid">
      <?php foreach ($reals as $r): ?>
      <div class="real-card">
        <?php render_realisation_media($r); ?>
        <div class="real-body">
          <?php if(trim((string)($r['service_type']??''))!==''):?><div class="real-svc"><?=e($r['service_type'])?></div><?php endif;?>
          <div class="real-h"><?=e($r['title'])?></div>
          <?php if(trim((string)($r['description']??''))!==''):?><p style="font-size:.8rem;color:var(--t2);margin:.25rem 0 0;font-weight:300;"><?=e($r['description'])?></p><?php endif;?>
          <?= realisation_meta($r) ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div></section>
<?php render_footer(); exit; }

/* ════ TARIFS ════ */
// Désactivée, la page n'existe que pour l'admin connecté, qui peut la préparer avant de la publier.
if ($route === 'tarifs' && (tarifs_enabled() || admin_logged_in())) {
    $meta = ['title'=>setting('tarifs_meta_title','Tarifs | '.company_name()),'description'=>setting('tarifs_meta_description','Nos tarifs en électricité, plomberie, chauffage et climatisation. Devis gratuit avant toute intervention.'),'canonical'=>route_url('tarifs')];
    render_head($meta); render_header(route_url('tarifs'));
    $quoteLabel = setting('tarifs_quote_label','Sur devis');
?>
<?php if (!tarifs_enabled()): ?>
<div class="tarif-draft"><div class="wrap">👁️ Aperçu réservé à l'administrateur : cette page est désactivée et invisible pour vos visiteurs. <a href="<?= e(url_for('admin/page_content.php?p=page_tarifs')) ?>">L'activer</a></div></div>
<?php endif; ?>
<section class="page-hero"><div class="wrap"><div class="ph-eyebrow"><?= e(setting('tarifs_eyebrow','Tarification')) ?></div>
  <h1 class="ph-h1"><?= e(setting('tarifs_title','Nos')) ?> <em><?= e(setting('tarifs_title_hl','tarifs')) ?></em></h1>
  <p class="ph-lead"><?= e(setting('tarifs_lead','Des prix clairs, confirmés avant toute intervention.')) ?></p>
</div></section>
<section class="sec sec-navy"><div class="wrap">
  <div class="tarif-grid">
    <?php foreach (tarifs_groups() as $g): ?>
    <div class="tarif-card">
      <h2 class="tarif-card-h"><span aria-hidden="true"><?= e($g['icon']) ?></span> <?= e($g['label']) ?></h2>
      <ul class="tarif-list">
        <?php foreach ($g['rows'] as $row): [$label,$price,$detail] = array_pad(array_values((array)$row), 3, ''); ?>
        <li class="tarif-row">
          <div class="tarif-row-txt">
            <span class="tarif-label"><?= e((string)$label) ?></span>
            <?php if (trim((string)$detail)!==''): ?><span class="tarif-detail"><?= e((string)$detail) ?></span><?php endif; ?>
          </div>
          <span class="tarif-price<?= trim((string)$price)==='' ? ' is-quote' : '' ?>"><?= e(trim((string)$price)!=='' ? (string)$price : $quoteLabel) ?></span>
        </li>
        <?php endforeach; ?>
      </ul>
    </div>
    <?php endforeach; ?>
  </div>
  <p class="tarif-note"><?= e(setting('tarifs_note','Tarifs donnés à titre indicatif. Le prix définitif est confirmé par un devis gratuit avant toute intervention.')) ?></p>
  <div class="faq-cta">
    <h2><?= e(setting('tarifs_cta_title','Besoin d\'un prix précis ?')) ?></h2>
    <p><?= e(setting('tarifs_cta_lead','Décrivez votre besoin, nous vous répondons avec un chiffrage clair et sans engagement.')) ?></p>
    <div class="acts">
      <a class="btn btn-outline btn-lg" href="<?= e(route_url('quote')) ?>"><?= e(setting('tarifs_cta_btn','Demander un devis gratuit')) ?></a>
      <a class="btn btn-p btn-lg" href="<?= e(company_phone_link()) ?>">📞 <?= e(company_phone()) ?></a>
    </div>
  </div>
</div></section>
<?php render_footer(); exit; }

/* ════ NOS SERVICES ════ */
// Composée à partir des fiches métier : un métier ajouté apparaît ici tout seul,
// avec les textes modifiés dans son écran d'administration.
if ($route === 'services') {
    $page  = page_by_slug('services');
    $meta  = $page ? seo_defaults('services', $page) : ['title'=>'Nos services | '.company_name(),'description'=>'Nos services multitechniques.','canonical'=>route_url('services')];
    $trades = array_keys(service_trade_defaults());
    $icones = service_trade_defaults();
    $teintes = ['electricite'=>'#E8921A','plomberie'=>'#1a7ab5','chauffage'=>'#c0392b','climatisation'=>'#2980b9','vmc'=>'#16a085','portail'=>'#8e44ad','automatisme'=>'#d4a017'];
    render_head($meta); render_header(route_url('services'));
?>
<section class="page-hero"><div class="wrap"><div class="ph-eyebrow"><?= e(setting('services_eyebrow','Nos services')) ?></div>
  <h1 class="ph-h1"><?= e(setting('services_h1','Services')) ?></h1>
  <p class="ph-lead"><?= e(setting('services_page_lead','Nos services multitechniques')) ?></p>
</div></section>
<section class="sec sec-navy"><div class="wrap">
  <div class="sp-intro">
    <h2><?= e(setting('services_intro_title','Nos '.count($trades).' pôles d\'intervention')) ?></h2>
    <p><?= e(geo_replace(setting('services_intro_lead','Un seul interlocuteur pour l\'ensemble de vos besoins techniques. EMAE intervient en urgence, installation, entretien et mise aux normes {en_region}.'))) ?></p>
  </div>
  <div class="sp-grid">
    <?php foreach ($trades as $trade): $tpl = service_template($trade); ?>
    <div class="sp-card" style="--sp-teinte:<?= e($teintes[$trade] ?? '#F07B1D') ?>">
      <span class="sp-icon" aria-hidden="true"><?= e($icones[$trade]['icon_svc']) ?></span>
      <h3 class="sp-title"><?= e($tpl['label']) ?></h3>
      <p class="sp-desc"><?= e(geo_replace($tpl['desc'])) ?></p>
      <div class="sp-tags">
        <?php foreach ((array)$tpl['badges'] as $b): ?><span class="sp-tag"><?= e(is_array($b) ? (string)reset($b) : (string)$b) ?></span><?php endforeach; ?>
      </div>
      <ul class="sp-list">
        <?php foreach (array_slice(service_offer_cards((array)$tpl['offer_items']), 0, 5) as $it): ?><li><?= e($it['title']) ?></li><?php endforeach; ?>
      </ul>
      <a class="sp-link" href="<?= e(route_url($trade)) ?>"><?= e(setting('services_card_link','Voir nos prestations')) ?> →</a>
    </div>
    <?php endforeach; ?>
  </div>
  <div class="sp-cta">
    <h3><?= e(setting('services_cta_title','Besoin d\'un technicien ?')) ?></h3>
    <p><?= e(setting('services_cta_lead','Décrivez votre besoin, nous vous rappelons sous 5 minutes avec un chiffrage clair. Devis gratuit et sans engagement.')) ?></p>
    <div class="sp-acts">
      <a class="btn btn-outline btn-lg" href="<?= e(route_url('quote')) ?>"><?= e(setting('services_cta_btn','Devis gratuit en 2 min')) ?></a>
      <a class="btn btn-p btn-lg" href="<?= e(company_phone_link()) ?>">📞 <?= e(company_phone()) ?></a>
    </div>
  </div>
</div></section>
<?php render_footer(); exit; }

/* ════ PAGES AJOUTÉES EN NOVEMBRE 2026 (gabarits : includes/pages_extra.php) ════ */
// Une page désactivée n'existe pas pour les visiteurs (404) ; un admin connecté la
// prévisualise. Sans ce garde-fou, « chaudiere-en-panne » tomberait sur la page Chauffage.
require_once __DIR__ . '/includes/pages_extra.php';
$_extra = [
    'pannes'        => [pannes_enabled(),     'render_page_pannes_index'],
    'aides'         => [aides_enabled(),      'render_page_aides'],
    'estimation'    => [estimation_enabled(), 'render_page_estimation'],
    'rendez-vous'   => [booking_enabled(),    'render_page_rdv'],
    'entretien'     => [entretien_enabled(),  'render_page_entretien'],
    'suivi'         => [true,                 'render_page_suivi'],
    'espace-client' => [true,                 'render_page_espace_client'],
    'paiement'      => [true,                 'render_page_paiement'],
    'suite-appel'   => [true,                 'render_page_suite_appel'],
];
if (isset(panne_defaults()[$route])) $_extra[$route] = [pannes_enabled(), static fn() => render_page_panne($route)];
if (isset($_extra[$route])) {
    if ($_extra[$route][0] || admin_logged_in()) { ($_extra[$route][1])(); exit; }
    $route = '__introuvable__';
}
unset($_extra);

/* ════ PAGES SERVICE ════ */
// La page peut exister en base — on reprend alors son titre et son contenu —
// ou ne pas exister : l'adresse du métier suffit désormais à afficher la page.
$page = page_by_slug($route);
$sk   = service_detect_trade($route.' '.($page['slug'] ?? '').' '.($page['title'] ?? ''));
// Ancienne adresse d'une page métier (« ?route=Électricité », « electricien-meaux »…) : doublon
// de la page métier conservée, avec parfois un ancien texte d'un autre secteur. Redirection
// permanente vers l'adresse de référence ; la fiche en base n'est pas supprimée.
if ($sk !== null && $route !== $sk && $_SERVER['REQUEST_METHOD'] === 'GET') {
    header('Location: '.absolute_url(route_url($sk)), true, 301);
    exit;
}
$tpl  = $sk ? service_template($sk) : null;
if ($tpl !== null || $page) {
    if ($tpl !== null) {
        // Sans page en base, l'adresse du métier suffit à composer la page.
        $page = $page ?: ['title' => $tpl['label'], 'slug' => $route, 'excerpt' => '',
                          'content_html' => '', 'page_type' => 'Service'];
        // Titre et description propres au métier, adaptés au secteur de la page
        // (la fiche de page en base ne connaît pas la zone).
        $meta  = ['title' => $tpl['meta_title'], 'description' => $tpl['meta_desc']];
        // Une seule page de référence par métier (la page en base peut porter
        // un autre slug, ex. « Électricité », qui créait un doublon).
        $meta['canonical'] = route_url($sk);
        // Page métier d'une zone (/jura/electricite) : page propre seulement si son contenu
        // diffère réellement de la page métier du site (titre, titre Google, présentation).
        // Sinon, référence = la page métier du site, sans envoyer la zone vers l'accueil.
        if ($currentZone) {
            set_zone_context(null);
            $_gTpl = service_template($sk);
            $_gUrl = route_url($sk);
            set_zone_context($currentZone);
            if ($_gTpl && $_gTpl['h1'] === $tpl['h1'] && $_gTpl['meta_title'] === $tpl['meta_title'] && $_gTpl['intro'] === $tpl['intro']) {
                $meta['canonical'] = $_gUrl;
            }
            unset($_gTpl, $_gUrl);
        }
        $cards = service_cards_v14();
        render_head($meta); render_header(route_url($route));
        $heroImg = service_hero_image($sk);
        $hasHeroImg = $heroImg !== '' && file_exists(__DIR__.'/'.ltrim($heroImg,'/'));
?>
<section class="page-hero<?= $hasHeroImg ? ' svc-hero-img' : '' ?>"<?= $hasHeroImg ? ' style="background-image:url('.e(asset_url($heroImg)).');background-size:cover;background-position:center;"' : '' ?>>
  <?php if ($hasHeroImg): ?><div class="svc-hero-overlay"></div><?php endif; ?>
  <div class="wrap svc-hero-grid" style="position:relative;z-index:1;">
  <div class="svc-hero-content">
  <div class="ph-eyebrow">// <?= e($tpl['label']) ?></div>
  <h1 class="ph-h1"><?= e($tpl['h1']) ?></h1>
  <p class="ph-lead"><?= e($tpl['desc']) ?></p>
  <div class="ph-badges">
    <?php foreach ($tpl['badges'] as $b): ?><span class="ph-badge hl"><?= e($b) ?></span><?php endforeach; ?>
    <span class="ph-badge"><?= e(company_hours()) ?></span>
  </div>
  <div class="btn-row" style="margin-top:1.75rem;">
    <a class="btn btn-outline btn-lg" href="#etre-rappele"><?= e(setting('svc_page_btn_devis','Devis gratuit')) ?></a>
    <a class="btn btn-p btn-lg" href="<?= e(company_phone_link()) ?>">📞 <?= e(company_phone()) ?></a>
  </div>
  <?= render_google_badge() ?>
  </div>

  <!-- Être rappelé : le même encart que sur l'accueil, métier présélectionné -->
  <div class="hero-form" id="etre-rappele">
    <div class="hero-card">
      <div class="hero-card-tag"><?= e(claim_clean(setting('svc_quote_eyebrow','Rappel gratuit'), 'Rappel gratuit')) ?></div>
      <h2><?= e(setting('svc_quote_title','Être rappelé gratuitement')) ?></h2>
      <?php render_quote_form($cards, 'service_'.$sk, 'rappel'); ?>
    </div>
  </div>
  </div>
</section>
<?= render_trust_band($sk) ?>

<?php $svcBrands = array_values(array_filter(array_map(fn($b) => trim(is_array($b) ? (string)reset($b) : (string)$b), (array)$tpl['brands']), 'strlen'));
      if ($svcBrands && $tpl['brands_on']): ?>
<!-- Marques : bandeau défilant (texte, pas de logos de fabricants) -->
<section class="brands" aria-label="<?= e($tpl['brands_title']) ?>">
  <div class="wrap"><p class="brands-h"><?= e($tpl['brands_title']) ?></p></div>
  <div class="brands-band">
    <div class="brands-track">
      <?php for ($_bi = 0; $_bi < 2; $_bi++): ?>
      <ul class="brands-list"<?= $_bi ? ' aria-hidden="true"' : '' ?>>
        <?php foreach ($svcBrands as $_b): ?><li><?= e($_b) ?></li><?php endforeach; ?>
      </ul>
      <?php endfor; unset($_bi, $_b); ?>
    </div>
  </div>
  <div class="wrap"><p class="brands-note"><?= e(setting('svc_brands_note', 'Marques citées à titre indicatif : nous sommes un réparateur indépendant, sans lien avec ces fabricants.')) ?></p></div>
</section>
<?php endif; ?>

<!-- Ce que nous proposons -->
<section class="sec sec-navy"><div class="wrap">
  <?php if (trim($tpl['intro']) !== ''): ?>
  <div class="svc-intro">
    <?php foreach (preg_split('/\R\s*\R/', trim($tpl['intro'])) ?: [] as $para): ?><p><?= e(trim($para)) ?></p><?php endforeach; ?>
  </div>
  <?php endif; ?>
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
  <?php if ($sk === 'chauffage') echo render_rdv_prestation_cta('entretien-chaudiere'); ?>
</div></section>

<?php $_signs = array_values(array_filter(array_map(static fn($s) => trim(is_array($s) ? (string)($s['v'] ?? reset($s)) : (string)$s), (array)$tpl['signs']), 'strlen')); if ($_signs): ?>
<!-- Signes d'alerte : quand appeler -->
<section class="sec sec-navy"><div class="wrap" style="max-width:960px;">
  <div class="svc-label"><?= e(setting('svc_signs_label','Signes d\'alerte')) ?></div>
  <h2 class="section-title"><?= e($tpl['signs_title']) ?></h2>
  <ul class="sign-list">
    <?php foreach ($_signs as $_s): ?><li><?= e($_s) ?></li><?php endforeach; ?>
  </ul>
  <div class="btn-row" style="margin-top:1.5rem;">
    <a class="btn btn-p btn-lg" href="<?= e(company_phone_link()) ?>">📞 <?= e(company_phone()) ?></a>
    <a class="btn btn-outline btn-lg" href="#etre-rappele">Être rappelé</a>
  </div>
</div></section>
<?php endif; unset($_signs, $_s); ?>

<?php $svcReals = realisations_for_trade($sk); if (!empty($svcReals)): ?>
<!-- Réalisations du métier -->
<div class="divider"></div>
<section class="sec sec-navy"><div class="wrap">
  <div class="svc-label"><?= e(setting('svc_reals_label','Nos réalisations')) ?></div>
  <h2 class="section-title"><?= e(setting('svc_reals_title','Chantiers')) ?> <em><?= e(setting('svc_reals_title_hl','réalisés')) ?></em></h2>
  <div class="reals-grid" style="margin-top:1.75rem;">
    <?php foreach ($svcReals as $r): ?>
    <div class="real-card">
      <?php render_realisation_media($r); ?>
      <div class="real-body">
        <div class="real-h"><?=e($r['title'])?></div>
        <?php if(trim((string)($r['description']??''))!==''):?><p style="font-size:.8rem;color:var(--t2);margin:.25rem 0 0;font-weight:300;"><?=e($r['description'])?></p><?php endif;?>
        <?= realisation_meta($r) ?>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <div style="text-align:center;margin-top:2rem;">
    <a class="btn btn-ghost-p" href="<?= e(url_with_query(route_url('realisations'), ['metier' => $sk])) ?>"><?= e(setting('reals_btn','Voir toutes nos réalisations')) ?> →</a>
  </div>
</div></section>
<?php endif; ?>

<!-- Processus -->
<section class="sec sec-card"><div class="wrap">
  <div class="svc-label"><?= e(setting('svc_process_label','Notre méthode')) ?></div>
  <h2 class="section-title"><?= e(setting('svc_process_title','Intervention en')) ?> <em><?= e(setting('svc_process_title_hl','3 étapes')) ?></em></h2>
  <p class="section-lead svc-lead"><?= e(setting('svc_process_lead','Un fonctionnement simple et transparent, du premier appel au compte rendu final.')) ?></p>
  <div class="steps-grid" style="margin-top:1.75rem;">
    <?php /* Étapes propres aux pages métier : diagnostic, devis, réparation (textes communs aux métiers dans Contenu du site). */ ?>
    <div class="step-card"><div class="step-num">1</div><div><h3 class="step-h"><?= e(setting('svc_method_1_title','Diagnostic sur place')) ?></h3><p class="step-p"><?= e(claim_clean(setting('svc_method_1_text','Le technicien recherche la cause de la panne et vous l\'explique simplement.'))) ?></p></div></div>
    <div class="step-card"><div class="step-num">2</div><div><h3 class="step-h"><?= e(setting('svc_method_2_title','Devis clair avant travaux')) ?></h3><p class="step-p"><?= e(claim_clean(setting('svc_method_2_text','Vous connaissez le prix avant toute réparation et vous décidez : rien n\'est fait sans votre accord.'))) ?></p></div></div>
    <div class="step-card"><div class="step-num">3</div><div><h3 class="step-h"><?= e(setting('svc_method_3_title','Réparation aux normes')) ?></h3><p class="step-p"><?= e(claim_clean(setting('svc_method_3_text','Réparation ou installation dans les règles de l\'art, puis facture détaillée en fin d\'intervention.'))) ?></p></div></div>
  </div>
</div></section>

<!-- FAQ service -->
<section class="sec sec-navy"><div class="wrap" style="max-width:860px;">
  <div class="svc-label"><?= e(setting('svc_faq_label','FAQ')) ?> <?= e(mb_strtolower($tpl['label'],'UTF-8')) ?></div>
  <h2 class="section-title"><?= e(setting('svc_faq_title','Questions')) ?> <em><?= e(setting('svc_faq_title_hl','fréquentes')) ?></em></h2>
  <p class="section-lead svc-lead"><?= e(setting('svc_faq_lead','Les réponses aux questions que nos clients nous posent le plus souvent. Une autre question ? Appelez-nous, nous vous répondons directement.')) ?></p>
  <div class="sfaq-list" style="margin-top:1.75rem;">
    <?php $_faqLd = [];
    foreach ($tpl['faq'] as [$q,$a]): $_a = faq_answer_fix((string)$q, (string)$a);
        if (trim((string)$q) !== '' && trim($_a) !== '') $_faqLd[] = ['@type'=>'Question','name'=>(string)$q,'acceptedAnswer'=>['@type'=>'Answer','text'=>$_a]]; ?>
    <details class="sfaq-item"><summary><?= e($q) ?></summary><p><?= e($_a) ?></p></details>
    <?php endforeach; ?>
  </div>
  <?php /* Balisage FAQPage : exactement les questions et réponses affichées. */
  if ($_faqLd): ?><script type="application/ld+json"><?= json_encode(['@context'=>'https://schema.org','@type'=>'FAQPage','mainEntity'=>$_faqLd], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_HEX_TAG) ?></script><?php endif; unset($_faqLd, $_a); ?>
  <?php $svcPannes = pannes_for_trade($sk); if ($svcPannes): ?>
  <div class="svc-label" style="margin-top:2.25rem;">Pannes fréquentes : que faire ?</div>
  <div class="px-chips">
    <?php foreach ($svcPannes as $_pn): ?><a class="px-chip" href="<?= e(route_url($_pn['slug'])) ?>"><?= e($_pn['icon'].' '.$_pn['h1']) ?></a><?php endforeach; unset($_pn); ?>
  </div>
  <?php endif; ?>
</div></section>

<!-- Appel à l'action -->
<section class="cta-band"><div class="wrap cta-band-in">
  <div>
    <h2 class="cta-band-h"><?= e(setting('svc_cta_title','Un problème maintenant ?')) ?></h2>
    <p class="cta-band-p"><?= e(claim_clean(setting('svc_cta_lead','Appelez-nous ou demandez à être rappelé : nous faisons le point avec vous et vous annonçons le prix avant tout déplacement.'))) ?></p>
  </div>
  <div class="cta-band-acts">
    <a class="btn btn-p btn-lg" href="<?= e(company_phone_link()) ?>">📞 <?= e(company_phone()) ?></a>
    <a class="btn btn-outline btn-lg" href="#etre-rappele">Être rappelé gratuitement</a>
  </div>
</div></section>

<!-- Zones couvertes -->
<section class="sec sec-card"><div class="wrap">
  <div class="svc-label"><?= e(setting('svc_zones_label','Zone d\'intervention')) ?></div>
  <h2 class="section-title"><?= e(setting('svc_zones_title','Zones couvertes')) ?></h2>
  <p class="section-lead svc-lead"><?= e(setting('svc_zones_lead','Nos techniciens se déplacent dans les secteurs suivants. Votre ville n\'apparaît pas ? Appelez-nous pour vérifier votre adresse.')) ?></p>
  <div class="zones-grid" style="margin-top:1.75rem;">
    <?php /* Même source que l'accueil et la page Nos zones : la table des zones. */
          // Sur un sous-site, seule sa zone : la page Jura ne liste pas Paris.
          foreach (array_filter(intervention_zones_listed(), fn($z) => !$currentZone || $z['slug'] === $currentZone['slug']) as $z): ?>
    <div class="zone-card">
      <div class="zone-name"><?= e($z['name']) ?></div>
      <?php if ($z['intro'] !== ''): ?><p class="zone-text"><?= e($z['intro']) ?></p><?php endif; ?>
      <?php if (!empty($z['cities'])): ?>
      <div class="zone-chips"><?php foreach(array_slice($z['cities'],0,8) as $c):?><span class="zone-chip"><?=e($c)?></span><?php endforeach;?></div>
      <?php endif; ?>
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
<section class="sec sec-card"><div class="wrap"><div class="rich" style="max-width:860px;"><?= page_html_without_third_party((string)$page['content_html']) ?></div></div></section>
<?php endif;
        render_footer(); exit;
    }

    // Page générique
    $meta = seo_defaults($route, $page);
    render_head($meta); render_header(route_url($route));
    echo '<section class="page-hero"><div class="wrap"><div class="ph-eyebrow">'.e($page['page_type']).'</div><h1 class="ph-h1">'.e($page['title']).'</h1><p class="ph-lead">'.e($page['excerpt']??'').'</p></div></section>';
    echo '<section class="sec sec-navy"><div class="wrap"><div class="rich">'.(page_html_without_third_party((string)$page['content_html']) ?: '<p style="color:var(--t2)">Contenu à venir.</p>').'</div></div></section>';
    render_footer(); exit;
}

/* ════ MENTIONS LÉGALES ════ */
if ($route === 'mentions-legales' || $route === 'mentions_legales') {
    $zML = $currentZone ? trim((string)($currentZone['mentions_legales'] ?? '')) : '';
    $zName = $currentZone ? $currentZone['name'] : '';
    $meta = [
        'title'       => ($zName ? $zName.' — ' : '').setting('ml_meta_title','Mentions légales | '.company_name()),
        'description' => setting('ml_meta_description','Mentions légales, informations légales et politique de confidentialité de '.company_name().'.'),
        'canonical'   => $currentZone ? url_for($currentZone['slug'].'/mentions-legales') : route_url('mentions-legales'),
    ];
    render_head($meta);
    render_header($currentZone ? url_for($currentZone['slug'].'/') : route_url(''));
?>
<section class="page-hero"><div class="wrap">
  <div class="ph-eyebrow">Informations légales</div>
  <h1 class="ph-h1">Mentions légales<?= $zName ? ' — '.e($zName) : '' ?></h1>
  <p class="ph-lead">Conformément aux dispositions de la loi n°2004-575 du 21 juin 2004 pour la Confiance en l'Économie Numérique.</p>
</div></section>
<section class="sec sec-navy"><div class="wrap"><div class="rich" style="max-width:800px;color:var(--t1);line-height:1.8;">
<?php if ($zML !== ''): ?>
  <?= nl2br(e($zML)) ?>
<?php else: ?>
  <?php $L = legal_info(); /* Réglages globaux : Identité & coordonnées → Mentions légales */ ?>
  <h2>Éditeur du site</h2>
  <p>Le site <?= e(preg_replace('#^https?://#', '', rtrim(absolute_url('/'), '/'))) ?> est édité par
  <strong><?= e($L['denomination']) ?></strong><?= company_name() !== '' && stripos($L['denomination'], company_name()) === false ? ' (nom commercial : '.e(company_name()).')' : '' ?>,
  <?= e($L['forme']) ?><?= $L['capital'] !== '' ? ' au capital de '.e($L['capital']) : '' ?>.<br>
  Siège social : <?= e($L['siege']) ?><br>
  <?php if ($L['siren'] !== ''): ?>SIREN : <?= e($L['siren']) ?><?= $L['rcs'] !== '' ? ' — '.e($L['rcs']) : '' ?><br><?php endif; ?>
  <?php $siret = company_siret(); if ($siret): ?>SIRET du siège : <?= e($siret) ?><br><?php endif; ?>
  <?php if ($L['tva'] !== ''): ?>N° de TVA intracommunautaire : <?= e($L['tva']) ?><br><?php endif; ?>
  Téléphone : <a href="<?= e(company_phone_link()) ?>"><?= e(company_phone()) ?></a><br>
  Email : <a href="mailto:<?= e(company_email()) ?>"><?= e(company_email()) ?></a></p>
  <?php if ($L['base'] !== ''): ?>
  <p>Base d'intervention : <?= e($L['base']) ?>. Zones desservies : <?= e(geo_replace('{region}')) ?>. Le siège social est une adresse administrative ; les interventions ont lieu chez les clients, sur rendez-vous ou en urgence.</p>
  <?php endif; ?>

  <h2>Directeur de la publication</h2>
  <p><?= $L['directeur'] !== '' ? e($L['directeur']).', gérant' : 'Le gérant de la société, en sa qualité de représentant légal' ?>.</p>

  <h2>Hébergeur</h2>
  <p><?= e(setting('ml_hebergeur', 'o2switch SAS — 222-224 boulevard Gustave Flaubert, 63000 Clermont-Ferrand — www.o2switch.fr')) ?></p>

  <?php if ($L['assurance'] !== '' && ($L['assurance_fin'] === '' || date('Y-m-d') <= $L['assurance_fin'])): ?>
  <h2>Assurance professionnelle</h2>
  <p><?= e($L['assurance']) ?></p>
  <?php endif; ?>

  <?php if ($L['mediateur'] !== ''): ?>
  <h2>Médiation de la consommation</h2>
  <p>En cas de litige non résolu avec notre service client, vous pouvez recourir gratuitement au médiateur de la consommation : <?= e($L['mediateur']) ?>.</p>
  <?php endif; ?>

  <h2>Prix et devis</h2>
  <p>Le prix de l'intervention (déplacement, diagnostic, main-d'œuvre et, le cas échéant, pièces) est annoncé avant tout déplacement. Pour les travaux, un devis écrit est remis avant leur réalisation ; l'établissement du devis est gratuit. Une facture détaillée est remise en fin d'intervention.<?= tarifs_enabled() ? ' Nos tarifs sont consultables sur la page <a href="'.e(route_url('tarifs')).'">Tarifs</a>.' : '' ?></p>

  <h2>Propriété intellectuelle</h2>
  <p>L'ensemble de ce site relève de la législation française et internationale sur le droit d'auteur et la propriété intellectuelle. Tous les droits de reproduction sont réservés, y compris pour les documents téléchargeables et les représentations iconographiques et photographiques.</p>

  <h2>Données personnelles</h2>
  <p>Les informations recueillies via les formulaires de ce site sont enregistrées dans un fichier informatisé par <?= e(company_name()) ?> pour permettre la gestion des demandes d'intervention. Conformément au règlement général sur la protection des données (RGPD) et à la loi Informatique et Libertés, vous disposez d'un droit d'accès, de rectification et de suppression des données vous concernant. Pour exercer ce droit, contactez-nous à : <a href="mailto:<?= e(company_email()) ?>"><?= e(company_email()) ?></a>.</p>
  <?php $_keep = (int)growth_get('quote_photos_keep_months'); ?>
  <p>Les photos jointes à une demande servent uniquement à préparer l'intervention<?= $_keep > 0 ? ' et sont supprimées automatiquement '.$_keep.' mois après la demande' : '' ?>. Votre numéro de téléphone peut être utilisé pour vous envoyer des SMS liés à votre demande : accusé de réception, suivi de l'intervention, rappel d'entretien si vous avez un contrat, et une demande d'avis après l'intervention. Il n'est pas utilisé pour de la prospection commerciale. Rien n'est transmis tant que vous n'avez pas cliqué sur le bouton d'envoi d'un formulaire.<?= payments_enabled() ? ' Si vous réglez une facture par le lien de paiement que nous vous envoyons, le paiement est traité par Stripe : aucune donnée de carte bancaire n\'est conservée par '.e(company_name()).'.' : '' ?></p>
  <p>Vous pouvez aussi vous inscrire gratuitement sur la liste d'opposition au démarchage téléphonique Bloctel (www.bloctel.gouv.fr).</p>

  <h2 id="cookies">Cookies</h2>
  <?php $_ck = consent_tags(); ?>
  <p>Ce site utilise des cookies techniques nécessaires à son fonctionnement (session, protection des formulaires) et un cookie qui mémorise votre choix sur les cookies pendant 6 mois. Ils ne demandent pas votre accord.</p>
  <?php if ($_ck['ga'] !== '' || $_ck['ads'] !== ''): ?>
  <p>Avec votre accord seulement, des cookies Google sont déposés<?= $_ck['ga'] !== '' ? ' pour mesurer l\'audience (Google Analytics)' : '' ?><?= $_ck['ga'] !== '' && $_ck['ads'] !== '' ? ' et' : '' ?><?= $_ck['ads'] !== '' ? ' pour mesurer l\'efficacité de nos annonces (Google Ads : appel ou demande après un clic sur une annonce)' : '' ?>. Tant que vous n'avez pas accepté, aucune balise Google n'est chargée. Refuser n'empêche ni d'appeler ni d'envoyer une demande. <a href="#" data-ck-open>Modifier mes choix</a> à tout moment ; un retrait supprime les cookies Google déjà déposés.</p>
  <?php else: ?>
  <p>Aucun cookie publicitaire ou de mesure d'audience tiers n'est utilisé.</p>
  <?php endif; unset($_ck); ?>
  <p>Nous comptons aussi les pages vues et les clics sur nos numéros, sans cookie et sans donnée personnelle (un compteur par jour et par page).</p>

  <h2>Responsabilité</h2>
  <p><?= e(company_name()) ?> s'efforce d'assurer l'exactitude et la mise à jour des informations diffusées sur ce site. Toutefois, <?= e(company_name()) ?> ne peut garantir l'exactitude, la précision ou l'exhaustivité des informations mises à disposition sur ce site.</p>

  <?php if ($L['updated_at'] !== '' && strtotime($L['updated_at'])): ?><p style="margin-top:2rem;color:var(--t2);font-size:.85rem;">Dernière mise à jour : <?= e(date('d/m/Y', strtotime($L['updated_at']))) ?></p><?php endif; ?>
<?php endif; ?>
</div></div></section>
<?php render_footer(); exit; }

// 404
http_response_code(404);
$meta = ['title'=>'Page introuvable | '.company_name(),'description'=>'404.','canonical'=>route_url($route)];
render_head($meta); render_header('');
echo '<section class="page-hero"><div class="wrap"><div class="ph-eyebrow">Erreur 404</div><h1 class="ph-h1">Page introuvable</h1><p class="ph-lead">La page demandée n\'existe pas ou a été déplacée.</p><div class="btn-row" style="margin-top:1.75rem;"><a class="btn btn-p" href="'.e(company_phone_link()).'">📞 '.e(company_phone()).'</a><a class="btn btn-outline" href="'.e(route_url('')).'">Retour à l\'accueil</a><a class="btn btn-outline" href="'.e(route_url('services')).'">Nos services</a></div></div></section>';
render_footer();