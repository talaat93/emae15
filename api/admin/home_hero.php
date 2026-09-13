<?php
$adminSection = 'home-hero';
require __DIR__ . '/partials/header.php';

function hero_admin_setting(string $key, string $fallback = ''): string
{
    return setting($key, $fallback);
}

$cards = service_cards_settings();
$expertiseSettings = function_exists('home_expertise_settings') ? home_expertise_settings() : ['eyebrow' => 'Expertise', 'title' => 'Notre expertise multitechnique', 'lead' => '', 'cards' => []];
$reviewsBlock = function_exists('home_reviews_block_settings') ? home_reviews_block_settings() : ['eyebrow' => 'Avis clients', 'title' => 'Des témoignages qui rassurent', 'lead' => ''];
$quotePanelBlock = function_exists('home_quote_panel_settings') ? home_quote_panel_settings() : ['eyebrow' => 'Demande de devis', 'title' => 'Demande de devis', 'lead' => '', 'service_label' => 'Service', 'service_placeholder' => 'Choisir', 'message_label' => 'Votre besoin', 'urgency_label' => 'Urgence', 'button_label' => 'Envoyer ma demande'];
$zoneSection = function_exists('home_zone_settings') ? home_zone_settings() : ['eyebrow' => 'Zone d’intervention', 'title' => 'Une zone d’intervention claire et rassurante', 'lead' => '', 'badges' => [], 'button_label' => 'Voir nos zones', 'button_url' => 'contact', 'cards' => []];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $fields = [
        'hero_bg_from','hero_bg_to','hero_glow_left','hero_glow_right',
        'home_eyebrow','home_title','home_lead','home_bullets',
        'home_eyebrow_color','home_title_color','home_lead_color',
        'home_eyebrow_size','home_title_size','home_lead_size','home_chip_size','home_button_size','home_feature_size',
        'home_chip_1','home_chip_2','home_chip_3','home_chip_4','home_chip_5','home_chip_6',
        'home_button1_label','home_button1_url','home_button2_label','home_button2_url',
        'home_feature_1_title','home_feature_1_text','home_feature_2_title','home_feature_2_text','home_feature_3_title','home_feature_3_text',
        'home_quote_eyebrow','home_quote_title','home_quote_title_size','home_quote_service_label','home_quote_city_label','home_quote_city_placeholder','home_quote_button_label','home_quote_meta',
        'home_banner_eyebrow','home_banner_title','home_banner_lead','home_banner_button1_label','home_banner_button1_url','home_banner_button2_label','home_banner_button2_url','home_banner_logo_path',
        'home_expertise_eyebrow','home_expertise_title','home_expertise_lead',
        'home_reviews_eyebrow','home_reviews_title','home_reviews_lead',
        'home_quote_panel_eyebrow','home_quote_panel_title','home_quote_panel_lead','home_quote_panel_service_label','home_quote_panel_service_placeholder','home_quote_panel_message_label','home_quote_panel_urgency_label','home_quote_panel_button_label',
        'home_zone_eyebrow','home_zone_title','home_zone_lead','home_zone_badge_1','home_zone_badge_2','home_zone_badge_3','home_zone_button_label','home_zone_button_url',
    ];

    foreach ($fields as $field) {
        set_setting($field, trim((string) ($_POST[$field] ?? '')));
    }

    if (function_exists('upload_image_field')) {
        $uploadedLogo = upload_image_field('home_banner_logo_file', 'hero');
        if ($uploadedLogo) {
            set_setting('home_banner_logo_path', $uploadedLogo);
        }
    }

    $newCards = [];
    $rawCards = $_POST['cards'] ?? [];
    foreach ($rawCards as $index => $row) {
        $title = trim((string) ($row['title'] ?? ''));
        $link = trim((string) ($row['link'] ?? ''));
        $current = trim((string) ($row['current_image'] ?? ''));
        $uploaded = function_exists('upload_image_field') ? upload_image_field('card_image_' . $index, 'services') : null;
        $image = $uploaded ?: $current;
        if ($title === '' || $image === '') {
            continue;
        }
        $newCards[] = ['title' => $title, 'image' => $image, 'link' => $link];
    }
    if ($newCards) {
        set_json_setting('home_service_cards', $newCards);
    }

    $expertiseCards = [];
    foreach (($_POST['expertise_cards'] ?? []) as $card) {
        $title = trim((string) ($card['title'] ?? ''));
        $lead = trim((string) ($card['lead'] ?? ''));
        if ($title === '' && $lead === '') {
            continue;
        }
        $expertiseCards[] = [
            'icon' => trim((string) ($card['icon'] ?? '🔧')) ?: '🔧',
            'title' => $title,
            'lead' => $lead,
            'item_1' => trim((string) ($card['item_1'] ?? '')),
            'item_2' => trim((string) ($card['item_2'] ?? '')),
            'item_3' => trim((string) ($card['item_3'] ?? '')),
            'link' => trim((string) ($card['link'] ?? 'services')),
        ];
    }
    if ($expertiseCards) {
        set_json_setting('home_expertise_cards', $expertiseCards);
    }

    $zoneCards = [];
    foreach (($_POST['zone_cards'] ?? []) as $card) {
        $title = trim((string) ($card['title'] ?? ''));
        $text = trim((string) ($card['text'] ?? ''));
        if ($title === '' && $text === '') {
            continue;
        }
        $zoneCards[] = ['title' => $title, 'text' => $text];
    }
    if ($zoneCards) {
        set_json_setting('home_zone_cards', $zoneCards);
    }

    flash('success', 'Accueil enregistré avec succès.');
    redirect_to('admin/home_hero.php');
}

$cards = service_cards_settings();
$expertiseSettings = function_exists('home_expertise_settings') ? home_expertise_settings() : $expertiseSettings;
$reviewsBlock = function_exists('home_reviews_block_settings') ? home_reviews_block_settings() : $reviewsBlock;
$quotePanelBlock = function_exists('home_quote_panel_settings') ? home_quote_panel_settings() : $quotePanelBlock;
$zoneSection = function_exists('home_zone_settings') ? home_zone_settings() : $zoneSection;
?>
<div class="admin-page-toolbar">
  <div>
    <div class="admin-breadcrumb">Accueil / Pilotage des sections</div>
    <h1 class="admin-page-title">Accueil complet</h1>
    <p class="admin-page-subtitle">Une seule page pour piloter le hero, les images, l’urgence technique, l’expertise, les avis, le devis et la zone d’intervention.</p>
  </div>
  <div class="admin-toolbar-actions">
    <a class="admin-btn admin-btn--secondary" href="<?= e(route_url('')) ?>" target="_blank">Voir le site</a>
  </div>
</div>

<form method="post" enctype="multipart/form-data" class="admin-stack admin-tabs" id="home-admin-tabs-form">
  <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

  <div class="admin-tabs__nav" role="tablist" aria-label="Sections de l’accueil">
    <button class="admin-tab-btn is-active" type="button" data-admin-tab="hero">Hero</button>
    <button class="admin-tab-btn" type="button" data-admin-tab="images">Bloc des images</button>
    <button class="admin-tab-btn" type="button" data-admin-tab="urgency">Urgence technique</button>
    <button class="admin-tab-btn" type="button" data-admin-tab="expertise">Expertise</button>
    <button class="admin-tab-btn" type="button" data-admin-tab="reviews">Avis clients</button>
    <button class="admin-tab-btn" type="button" data-admin-tab="quote">Demande de devis</button>
    <button class="admin-tab-btn" type="button" data-admin-tab="zone">Zone d’intervention</button>
  </div>

  <div class="admin-tab-panel is-active" data-admin-panel="hero">
    <section class="admin-panel">
      <div class="admin-panel__head">
        <h2>Fond</h2>
        <p>Le fond du hero et ses lueurs.</p>
      </div>
      <div class="admin-panel__body">
        <div class="admin-form-grid admin-form-grid--2">
          <label class="admin-field"><span>Fond gauche</span><input type="color" name="hero_bg_from" value="<?= e(hero_admin_setting('hero_bg_from', '#04113b')) ?>"></label>
          <label class="admin-field"><span>Fond droite</span><input type="color" name="hero_bg_to" value="<?= e(hero_admin_setting('hero_bg_to', '#18357f')) ?>"></label>
        </div>
        <div class="admin-form-grid admin-form-grid--2">
          <label class="admin-field"><span>Lueur gauche</span><input type="color" name="hero_glow_left" value="<?= e(hero_admin_setting('hero_glow_left', '#4a233f')) ?>"></label>
          <label class="admin-field"><span>Lueur droite</span><input type="color" name="hero_glow_right" value="<?= e(hero_admin_setting('hero_glow_right', '#335dff')) ?>"></label>
        </div>
      </div>
    </section>

    <section class="admin-panel">
      <div class="admin-panel__head">
        <h2>Texte principal</h2>
        <p>Titre, descriptif, puces, couleurs et tailles.</p>
      </div>
      <div class="admin-panel__body">
        <label class="admin-field"><span>Petit texte du haut</span><input type="text" name="home_eyebrow" value="<?= e(hero_admin_setting('home_eyebrow', 'Entreprise multitechnique avancée')) ?>"></label>
        <label class="admin-field"><span>Grand titre</span><textarea name="home_title" rows="4"><?= e(hero_admin_setting('home_title', 'Le partenaire technique de vos bâtiments en Île-de-France et en Occitanie')) ?></textarea></label>
        <label class="admin-field"><span>Texte descriptif</span><textarea name="home_lead" rows="4"><?= e(hero_admin_setting('home_lead', 'EMAE aide à capter des clients pour le dépannage, l’entretien et les besoins techniques en électricité, plomberie, chauffage, climatisation, CVC et pompes à chaleur.')) ?></textarea></label>
        <label class="admin-field"><span>Puces (une ligne = une puce)</span><textarea name="home_bullets" rows="5"><?= e(hero_admin_setting('home_bullets', "Intervention moyenne sous 2h\nDevis gratuit et sans engagement\nZone couverte : Île-de-France et Occitanie")) ?></textarea></label>

        <div class="admin-form-grid admin-form-grid--3">
          <label class="admin-field"><span>Couleur petit texte</span><input type="color" name="home_eyebrow_color" value="<?= e(hero_admin_setting('home_eyebrow_color', '#83a6ff')) ?>"></label>
          <label class="admin-field"><span>Couleur titre</span><input type="color" name="home_title_color" value="<?= e(hero_admin_setting('home_title_color', '#ffffff')) ?>"></label>
          <label class="admin-field"><span>Couleur descriptif</span><input type="color" name="home_lead_color" value="<?= e(hero_admin_setting('home_lead_color', '#dbe6ff')) ?>"></label>
        </div>

        <div class="admin-form-grid admin-form-grid--3">
          <label class="admin-field"><span>Taille petit texte</span><input type="text" name="home_eyebrow_size" placeholder="14px ou 0.92rem" value="<?= e(hero_admin_setting('home_eyebrow_size', '0.95rem')) ?>"></label>
          <label class="admin-field"><span>Taille titre</span><input type="text" name="home_title_size" placeholder="clamp(...)" value="<?= e(hero_admin_setting('home_title_size', 'clamp(3.9rem, 7vw, 6.15rem)')) ?>"></label>
          <label class="admin-field"><span>Taille descriptif</span><input type="text" name="home_lead_size" placeholder="20px ou 1.2rem" value="<?= e(hero_admin_setting('home_lead_size', '1.18rem')) ?>"></label>
        </div>
      </div>
    </section>

    <section class="admin-panel">
      <div class="admin-panel__head">
        <h2>Boutons & onglets</h2>
        <p>Les boutons principaux, les puces services et le long bandeau d’informations.</p>
      </div>
      <div class="admin-panel__body">
        <div class="admin-form-grid admin-form-grid--2">
          <label class="admin-field"><span>Texte bouton 1</span><input type="text" name="home_button1_label" value="<?= e(hero_admin_setting('home_button1_label', 'Demander un devis')) ?>"></label>
          <label class="admin-field"><span>Lien bouton 1</span><input type="text" name="home_button1_url" value="<?= e(hero_admin_setting('home_button1_url', 'quote')) ?>"></label>
        </div>
        <div class="admin-form-grid admin-form-grid--2">
          <label class="admin-field"><span>Texte bouton 2</span><input type="text" name="home_button2_label" value="<?= e(hero_admin_setting('home_button2_label', 'Domaines d’intervention')) ?>"></label>
          <label class="admin-field"><span>Lien bouton 2</span><input type="text" name="home_button2_url" value="<?= e(hero_admin_setting('home_button2_url', 'services')) ?>"></label>
        </div>

        <div class="admin-form-grid admin-form-grid--3">
          <?php for ($i = 1; $i <= 6; $i++): ?>
            <label class="admin-field"><span>Onglet <?= e((string) $i) ?></span><input type="text" name="home_chip_<?= e((string) $i) ?>" value="<?= e(hero_admin_setting('home_chip_' . $i, '')) ?>"></label>
          <?php endfor; ?>
        </div>

        <div class="admin-form-grid admin-form-grid--3">
          <label class="admin-field"><span>Taille onglets services</span><input type="text" name="home_chip_size" value="<?= e(hero_admin_setting('home_chip_size', '1rem')) ?>"></label>
          <label class="admin-field"><span>Taille boutons hero</span><input type="text" name="home_button_size" value="<?= e(hero_admin_setting('home_button_size', '1rem')) ?>"></label>
          <label class="admin-field"><span>Taille bandeau infos</span><input type="text" name="home_feature_size" value="<?= e(hero_admin_setting('home_feature_size', '0.98rem')) ?>"></label>
        </div>
      </div>
    </section>

    <section class="admin-panel">
      <div class="admin-panel__head">
        <h2>Cartes infos</h2>
        <p>Les 3 blocs d’infos sous les boutons.</p>
      </div>
      <div class="admin-panel__body">
        <div class="admin-form-grid admin-form-grid--3">
          <label class="admin-field"><span>Titre carte 1</span><input type="text" name="home_feature_1_title" value="<?= e(hero_admin_setting('home_feature_1_title', 'Mobile first')) ?>"></label>
          <label class="admin-field"><span>Titre carte 2</span><input type="text" name="home_feature_2_title" value="<?= e(hero_admin_setting('home_feature_2_title', 'Conversion rapide')) ?>"></label>
          <label class="admin-field"><span>Titre carte 3</span><input type="text" name="home_feature_3_title" value="<?= e(hero_admin_setting('home_feature_3_title', 'Image premium')) ?>"></label>

          <label class="admin-field"><span>Texte carte 1</span><textarea name="home_feature_1_text" rows="3"><?= e(hero_admin_setting('home_feature_1_text', 'Prêt pour Google Ads local')) ?></textarea></label>
          <label class="admin-field"><span>Texte carte 2</span><textarea name="home_feature_2_text" rows="3"><?= e(hero_admin_setting('home_feature_2_text', 'Appel, devis, contact, espace client')) ?></textarea></label>
          <label class="admin-field"><span>Texte carte 3</span><textarea name="home_feature_3_text" rows="3"><?= e(hero_admin_setting('home_feature_3_text', 'Corporate, claire et rassurante')) ?></textarea></label>
        </div>
      </div>
    </section>

    <section class="admin-panel">
      <div class="admin-panel__head">
        <h2>Bloc demande d’appel rapide</h2>
        <p>Le formulaire à droite du hero.</p>
      </div>
      <div class="admin-panel__body">
        <div class="admin-form-grid admin-form-grid--2">
          <label class="admin-field"><span>Petit texte du bloc</span><input type="text" name="home_quote_eyebrow" value="<?= e(hero_admin_setting('home_quote_eyebrow', "Demande d'appel rapide")) ?>"></label>
          <label class="admin-field"><span>Titre du bloc</span><input type="text" name="home_quote_title" value="<?= e(hero_admin_setting('home_quote_title', 'Obtenir un rappel rapide')) ?>"></label>
        </div>
        <label class="admin-field"><span>Taille du titre du bloc</span><input type="text" name="home_quote_title_size" placeholder="Ex : 2.2rem ou clamp(1.95rem, 3vw, 2.7rem)" value="<?= e(hero_admin_setting('home_quote_title_size', 'clamp(1.95rem, 3vw, 2.7rem)')) ?>"></label>
        <div class="admin-form-grid admin-form-grid--2">
          <label class="admin-field"><span>Libellé service</span><input type="text" name="home_quote_service_label" value="<?= e(hero_admin_setting('home_quote_service_label', 'Service')) ?>"></label>
          <label class="admin-field"><span>Libellé ville</span><input type="text" name="home_quote_city_label" value="<?= e(hero_admin_setting('home_quote_city_label', 'Ville')) ?>"></label>
        </div>
        <label class="admin-field"><span>Placeholder ville</span><input type="text" name="home_quote_city_placeholder" value="<?= e(hero_admin_setting('home_quote_city_placeholder', 'Ex : Meaux, Paris, Toulouse')) ?>"></label>
        <div class="admin-form-grid admin-form-grid--2">
          <label class="admin-field"><span>Texte bouton</span><input type="text" name="home_quote_button_label" value="<?= e(hero_admin_setting('home_quote_button_label', 'Envoyer ma demande')) ?>"></label>
          <label class="admin-field"><span>Texte bas</span><input type="text" name="home_quote_meta" value="<?= e(hero_admin_setting('home_quote_meta', 'Artisans disponibles • devis gratuit • réponse rapide')) ?>"></label>
        </div>
      </div>
    </section>
  </div>

  <div class="admin-tab-panel" data-admin-panel="images">
    <section class="admin-panel">
      <div class="admin-panel__head">
        <h2>Bloc des images</h2>
        <p>Les 4 grandes cartes images sous le hero.</p>
      </div>
      <div class="admin-panel__body repeat-grid">
        <?php foreach ($cards as $i => $card): ?>
          <div class="repeat-card">
            <h3>Carte <?= e((string) ($i + 1)) ?></h3>
            <input type="hidden" name="cards[<?= e((string) $i) ?>][current_image]" value="<?= e($card['image']) ?>">
            <div class="admin-form-grid admin-form-grid--2">
              <label class="admin-field"><span>Titre</span><input type="text" name="cards[<?= e((string) $i) ?>][title]" value="<?= e($card['title']) ?>"></label>
              <label class="admin-field"><span>Lien</span><input type="text" name="cards[<?= e((string) $i) ?>][link]" value="<?= e($card['link']) ?>" placeholder="electricien-meaux"></label>
            </div>
            <img class="preview-thumb" src="<?= e(asset_url($card['image'])) ?>" alt="<?= e($card['title']) ?>">
            <label class="admin-field"><span>Nouvelle image</span><input type="file" name="card_image_<?= e((string) $i) ?>" accept=".png,.jpg,.jpeg,.webp,.svg"></label>
          </div>
        <?php endforeach; ?>
      </div>
    </section>
  </div>

  <div class="admin-tab-panel" data-admin-panel="urgency">
    <section class="admin-panel">
      <div class="admin-panel__head">
        <h2>Bandeau urgence technique</h2>
        <p>Grand bloc horizontal sous les cartes images, avec logo personnalisable.</p>
      </div>
      <div class="admin-panel__body">
        <div class="admin-form-grid admin-form-grid--2">
          <label class="admin-field"><span>Petit texte</span><input type="text" name="home_banner_eyebrow" value="<?= e(hero_admin_setting('home_banner_eyebrow', 'Urgence technique')) ?>"></label>
          <label class="admin-field"><span>Titre</span><input type="text" name="home_banner_title" value="<?= e(hero_admin_setting('home_banner_title', 'Astreinte visible, message clair, conversion immédiate')) ?>"></label>
        </div>
        <label class="admin-field"><span>Description</span><textarea name="home_banner_lead" rows="4"><?= e(hero_admin_setting('home_banner_lead', 'Le site met en avant votre numéro, le devis en ligne et des pages métier dédiées pour capter les demandes utiles dès l’arrivée sur la page d’accueil.')) ?></textarea></label>
        <div class="admin-form-grid admin-form-grid--2">
          <label class="admin-field"><span>Bouton 1</span><input type="text" name="home_banner_button1_label" value="<?= e(hero_admin_setting('home_banner_button1_label', 'Appel urgent')) ?>"></label>
          <label class="admin-field"><span>Lien bouton 1</span><input type="text" name="home_banner_button1_url" value="<?= e(hero_admin_setting('home_banner_button1_url', company_phone_link())) ?>"></label>
        </div>
        <div class="admin-form-grid admin-form-grid--2">
          <label class="admin-field"><span>Bouton 2</span><input type="text" name="home_banner_button2_label" value="<?= e(hero_admin_setting('home_banner_button2_label', 'Envoyer un message')) ?>"></label>
          <label class="admin-field"><span>Lien bouton 2</span><input type="text" name="home_banner_button2_url" value="<?= e(hero_admin_setting('home_banner_button2_url', 'quote')) ?>"></label>
        </div>
        <div class="admin-form-grid admin-form-grid--2">
          <label class="admin-field"><span>Chemin logo actuel</span><input type="text" name="home_banner_logo_path" value="<?= e(hero_admin_setting('home_banner_logo_path', '')) ?>" placeholder="storage/uploads/hero/mon-logo.png"></label>
          <label class="admin-field"><span>Uploader un logo</span><input type="file" name="home_banner_logo_file" accept="image/*"></label>
        </div>
        <?php if (trim(hero_admin_setting('home_banner_logo_path', '')) !== ''): ?>
          <div style="margin-top:1rem;display:flex;align-items:center;gap:1rem;">
            <img src="<?= e(asset_url(hero_admin_setting('home_banner_logo_path', ''))) ?>" alt="Logo bandeau" style="width:120px;height:auto;object-fit:contain;border-radius:0;padding:0;background:transparent;border:0;box-shadow:none;">
            <p style="margin:0;color:#5d6b92;">Seul le PNG apparaîtra sur le site, sans cercle automatique.</p>
          </div>
        <?php endif; ?>
      </div>
    </section>
  </div>

  <div class="admin-tab-panel" data-admin-panel="expertise">
    <section class="admin-panel">
      <div class="admin-panel__head">
        <h2>Bloc Expertise</h2>
        <p>Le titre et les 4 cartes “Notre expertise multitechnique”.</p>
      </div>
      <div class="admin-panel__body">
        <div class="admin-form-grid admin-form-grid--3">
          <label class="admin-field"><span>Petit texte</span><input type="text" name="home_expertise_eyebrow" value="<?= e($expertiseSettings['eyebrow'] ?? 'Expertise') ?>"></label>
          <label class="admin-field" style="grid-column: span 2;"><span>Titre</span><input type="text" name="home_expertise_title" value="<?= e($expertiseSettings['title'] ?? 'Notre expertise multitechnique') ?>"></label>
        </div>
        <label class="admin-field"><span>Description</span><textarea name="home_expertise_lead" rows="4"><?= e($expertiseSettings['lead'] ?? '') ?></textarea></label>

        <div class="admin-repeat-4">
          <?php foreach ((array) ($expertiseSettings['cards'] ?? []) as $i => $card): ?>
            <div class="repeat-card">
              <h3>Carte expertise <?= e((string) ($i + 1)) ?></h3>
              <div class="admin-form-grid admin-form-grid--2">
                <label class="admin-field"><span>Icône</span><input type="text" name="expertise_cards[<?= e((string) $i) ?>][icon]" value="<?= e($card['icon'] ?? '') ?>" placeholder="⚡"></label>
                <label class="admin-field"><span>Titre</span><input type="text" name="expertise_cards[<?= e((string) $i) ?>][title]" value="<?= e($card['title'] ?? '') ?>"></label>
              </div>
              <label class="admin-field"><span>Description</span><textarea name="expertise_cards[<?= e((string) $i) ?>][lead]" rows="3"><?= e($card['lead'] ?? '') ?></textarea></label>
              <label class="admin-field"><span>Ligne 1</span><input type="text" name="expertise_cards[<?= e((string) $i) ?>][item_1]" value="<?= e($card['item_1'] ?? '') ?>"></label>
              <label class="admin-field"><span>Ligne 2</span><input type="text" name="expertise_cards[<?= e((string) $i) ?>][item_2]" value="<?= e($card['item_2'] ?? '') ?>"></label>
              <label class="admin-field"><span>Ligne 3</span><input type="text" name="expertise_cards[<?= e((string) $i) ?>][item_3]" value="<?= e($card['item_3'] ?? '') ?>"></label>
              <label class="admin-field"><span>Lien</span><input type="text" name="expertise_cards[<?= e((string) $i) ?>][link]" value="<?= e($card['link'] ?? '') ?>" placeholder="services ou electricien-meaux"></label>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </section>
  </div>

  <div class="admin-tab-panel" data-admin-panel="reviews">
    <section class="admin-panel">
      <div class="admin-panel__head">
        <h2>Bloc Avis clients</h2>
        <p>Le texte du bloc, sans toucher à la liste détaillée des avis.</p>
      </div>
      <div class="admin-panel__body">
        <div class="admin-form-grid admin-form-grid--2">
          <label class="admin-field"><span>Petit texte</span><input type="text" name="home_reviews_eyebrow" value="<?= e($reviewsBlock['eyebrow'] ?? 'Avis clients') ?>"></label>
          <label class="admin-field"><span>Titre</span><input type="text" name="home_reviews_title" value="<?= e($reviewsBlock['title'] ?? 'Des témoignages qui rassurent') ?>"></label>
        </div>
        <label class="admin-field"><span>Description</span><textarea name="home_reviews_lead" rows="4"><?= e($reviewsBlock['lead'] ?? '') ?></textarea></label>

        <div class="admin-panel__helper">
          <p>Pour ajouter, modifier ou masquer un avis précis, utilise la page dédiée.</p>
          <a class="admin-btn admin-btn--secondary" href="<?= e(url_for('admin/reviews.php')) ?>">Gérer les avis</a>
        </div>
      </div>
    </section>
  </div>

  <div class="admin-tab-panel" data-admin-panel="quote">
    <section class="admin-panel">
      <div class="admin-panel__head">
        <h2>Bloc Demande de devis</h2>
        <p>Le bloc à côté des avis clients.</p>
      </div>
      <div class="admin-panel__body">
        <div class="admin-form-grid admin-form-grid--2">
          <label class="admin-field"><span>Petit texte</span><input type="text" name="home_quote_panel_eyebrow" value="<?= e($quotePanelBlock['eyebrow'] ?? 'Demande de devis') ?>"></label>
          <label class="admin-field"><span>Titre</span><input type="text" name="home_quote_panel_title" value="<?= e($quotePanelBlock['title'] ?? 'Demande de devis') ?>"></label>
        </div>
        <label class="admin-field"><span>Description</span><textarea name="home_quote_panel_lead" rows="4"><?= e($quotePanelBlock['lead'] ?? '') ?></textarea></label>
        <div class="admin-form-grid admin-form-grid--2">
          <label class="admin-field"><span>Libellé service</span><input type="text" name="home_quote_panel_service_label" value="<?= e($quotePanelBlock['service_label'] ?? 'Service') ?>"></label>
          <label class="admin-field"><span>Texte option vide</span><input type="text" name="home_quote_panel_service_placeholder" value="<?= e($quotePanelBlock['service_placeholder'] ?? 'Choisir') ?>"></label>
        </div>
        <div class="admin-form-grid admin-form-grid--2">
          <label class="admin-field"><span>Libellé message</span><input type="text" name="home_quote_panel_message_label" value="<?= e($quotePanelBlock['message_label'] ?? 'Votre besoin') ?>"></label>
          <label class="admin-field"><span>Libellé urgence</span><input type="text" name="home_quote_panel_urgency_label" value="<?= e($quotePanelBlock['urgency_label'] ?? 'Urgence') ?>"></label>
        </div>
        <label class="admin-field"><span>Texte bouton</span><input type="text" name="home_quote_panel_button_label" value="<?= e($quotePanelBlock['button_label'] ?? 'Envoyer ma demande') ?>"></label>

        <div class="admin-panel__helper">
          <p>Pour voir les demandes reçues depuis le front, ouvre la liste dédiée.</p>
          <a class="admin-btn admin-btn--secondary" href="<?= e(url_for('admin/quotes.php')) ?>">Voir les demandes</a>
        </div>
      </div>
    </section>
  </div>

  <div class="admin-tab-panel" data-admin-panel="zone">
    <section class="admin-panel">
      <div class="admin-panel__head">
        <h2>Bloc Zone d’intervention</h2>
        <p>La section de fin de page avec badges, bouton et cartes.</p>
      </div>
      <div class="admin-panel__body">
        <div class="admin-form-grid admin-form-grid--2">
          <label class="admin-field"><span>Petit texte</span><input type="text" name="home_zone_eyebrow" value="<?= e($zoneSection['eyebrow'] ?? 'Zone d’intervention') ?>"></label>
          <label class="admin-field"><span>Titre</span><input type="text" name="home_zone_title" value="<?= e($zoneSection['title'] ?? 'Une zone d’intervention claire et rassurante') ?>"></label>
        </div>
        <label class="admin-field"><span>Description</span><textarea name="home_zone_lead" rows="4"><?= e($zoneSection['lead'] ?? '') ?></textarea></label>

        <div class="admin-form-grid admin-form-grid--3">
          <label class="admin-field"><span>Badge 1</span><input type="text" name="home_zone_badge_1" value="<?= e(hero_admin_setting('home_zone_badge_1', 'Île-de-France')) ?>"></label>
          <label class="admin-field"><span>Badge 2</span><input type="text" name="home_zone_badge_2" value="<?= e(hero_admin_setting('home_zone_badge_2', 'Occitanie')) ?>"></label>
          <label class="admin-field"><span>Badge 3</span><input type="text" name="home_zone_badge_3" value="<?= e(hero_admin_setting('home_zone_badge_3', 'Intervention planifiée & urgence')) ?>"></label>
        </div>

        <div class="admin-form-grid admin-form-grid--2">
          <label class="admin-field"><span>Texte bouton</span><input type="text" name="home_zone_button_label" value="<?= e($zoneSection['button_label'] ?? 'Voir nos zones') ?>"></label>
          <label class="admin-field"><span>Lien bouton</span><input type="text" name="home_zone_button_url" value="<?= e($zoneSection['button_url'] ?? 'contact') ?>"></label>
        </div>

        <div class="admin-repeat-4">
          <?php foreach ((array) ($zoneSection['cards'] ?? []) as $i => $card): ?>
            <div class="repeat-card">
              <h3>Carte zone <?= e((string) ($i + 1)) ?></h3>
              <label class="admin-field"><span>Titre</span><input type="text" name="zone_cards[<?= e((string) $i) ?>][title]" value="<?= e($card['title'] ?? '') ?>"></label>
              <label class="admin-field"><span>Texte</span><textarea name="zone_cards[<?= e((string) $i) ?>][text]" rows="4"><?= e($card['text'] ?? '') ?></textarea></label>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </section>
  </div>

  <div class="admin-savebar">
    <button class="admin-btn admin-btn--primary" type="submit">Enregistrer toute la page d’accueil</button>
  </div>
</form>

<script>
document.addEventListener('DOMContentLoaded', function () {
  const buttons = Array.from(document.querySelectorAll('[data-admin-tab]'));
  const panels = Array.from(document.querySelectorAll('[data-admin-panel]'));
  const storageKey = 'emae-home-admin-active-tab';

  function activate(tab) {
    buttons.forEach((btn) => btn.classList.toggle('is-active', btn.dataset.adminTab === tab));
    panels.forEach((panel) => panel.classList.toggle('is-active', panel.dataset.adminPanel === tab));
    try { localStorage.setItem(storageKey, tab); } catch (e) {}
  }

  buttons.forEach((button) => {
    button.addEventListener('click', function () {
      activate(button.dataset.adminTab);
    });
  });

  let initial = 'hero';
  try {
    const saved = localStorage.getItem(storageKey);
    if (saved && buttons.some((btn) => btn.dataset.adminTab === saved)) {
      initial = saved;
    }
  } catch (e) {}
  activate(initial);
});
</script>

<?php require __DIR__ . '/partials/footer.php'; ?>
