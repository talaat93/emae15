<?php
$adminSection = 'faq_contact';
require __DIR__ . '/partials/header.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    // ── Simple string settings ──
    foreach ([
        'faq_meta_title','faq_meta_description',
        'contact_meta_title','contact_meta_description',
        'contact_hero_eyebrow','contact_hero_title','contact_hero_lead',
        'contact_form_title','contact_form_subtitle',
        'contact_info_title','contact_urgency_title','contact_urgency_text','contact_zones_title',
    ] as $f) {
        set_setting($f, trim((string) ($_POST[$f] ?? '')));
    }

    // ── FAQ hero & CTA ──
    $faqSaved = get_json_setting('faq_page_settings', []);
    $faqSaved['hero_eyebrow'] = trim((string) ($_POST['faq_hero_eyebrow'] ?? ''));
    $faqSaved['hero_title']   = trim((string) ($_POST['faq_hero_title']   ?? ''));
    $faqSaved['hero_lead']    = trim((string) ($_POST['faq_hero_lead']    ?? ''));
    $faqSaved['cta_title']    = trim((string) ($_POST['faq_cta_title']    ?? ''));
    $faqSaved['cta_lead']     = trim((string) ($_POST['faq_cta_lead']     ?? ''));
    $faqSaved['cta_button']   = trim((string) ($_POST['faq_cta_button']   ?? ''));
    $faqSaved['cta_url']      = trim((string) ($_POST['faq_cta_url']      ?? ''));

    // ── FAQ groups ──
    $newGroups = [];
    foreach (($_POST['faq_groups'] ?? []) as $group) {
        $cat = trim((string) ($group['category'] ?? ''));
        if ($cat === '') continue;
        $items = [];
        foreach (($group['items'] ?? []) as $item) {
            $q = trim((string) ($item['q'] ?? ''));
            $a = trim((string) ($item['a'] ?? ''));
            if ($q === '' && $a === '') continue;
            $items[] = ['q' => $q, 'a' => $a];
        }
        $newGroups[] = ['category' => $cat, 'items' => $items];
    }
    if ($newGroups) {
        $faqSaved['groups'] = $newGroups;
    }

    set_json_setting('faq_page_settings', $faqSaved);

    flash('success', 'FAQ et Contact enregistrés.');
    redirect_to('admin/faq_contact.php');
}

$faqCfg     = faq_page_settings();
$contactCfg = contact_page_settings();
?>

<div class="admin-page-toolbar">
  <div>
    <div class="admin-breadcrumb">Contenus</div>
    <h1 class="admin-page-title">FAQ & Contact</h1>
    <p class="admin-page-subtitle">Pilotage complet des pages FAQ et Contact depuis l'admin.</p>
  </div>
  <div class="admin-toolbar-actions">
    <a class="admin-btn admin-btn--secondary" href="<?= e(route_url('faq')) ?>" target="_blank">Voir la FAQ</a>
    <a class="admin-btn admin-btn--secondary" href="<?= e(route_url('contact')) ?>" target="_blank">Voir Contact</a>
  </div>
</div>

<form method="post" class="admin-stack admin-tabs" id="faq-contact-tabs">
<input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

<div class="admin-tabs__nav" role="tablist">
  <button class="admin-tab-btn is-active" type="button" data-admin-tab="faq-hero">FAQ – Hero</button>
  <button class="admin-tab-btn" type="button" data-admin-tab="faq-questions">FAQ – Questions</button>
  <button class="admin-tab-btn" type="button" data-admin-tab="faq-seo">FAQ – SEO</button>
  <button class="admin-tab-btn" type="button" data-admin-tab="contact-hero">Contact – Hero</button>
  <button class="admin-tab-btn" type="button" data-admin-tab="contact-form">Contact – Formulaire</button>
  <button class="admin-tab-btn" type="button" data-admin-tab="contact-seo">Contact – SEO</button>
</div>

<!-- ── FAQ HERO ── -->
<div class="admin-tab-panel is-active" data-admin-panel="faq-hero">
  <section class="admin-panel">
    <div class="admin-panel__head"><h2>Hero de la page FAQ</h2><p>Le bloc bleu marine en haut de page.</p></div>
    <div class="admin-panel__body">
      <label class="admin-field"><span>Petit texte (eyebrow)</span><input type="text" name="faq_hero_eyebrow" value="<?= e($faqCfg['hero_eyebrow']) ?>"></label>
      <label class="admin-field"><span>Titre</span><input type="text" name="faq_hero_title" value="<?= e($faqCfg['hero_title']) ?>"></label>
      <label class="admin-field"><span>Texte descriptif</span><textarea name="faq_hero_lead" rows="3"><?= e($faqCfg['hero_lead']) ?></textarea></label>
    </div>
  </section>
  <section class="admin-panel">
    <div class="admin-panel__head"><h2>Bloc CTA (bas de FAQ)</h2><p>Le bloc bleu en bas de page qui invite à contacter.</p></div>
    <div class="admin-panel__body">
      <div class="admin-form-grid admin-form-grid--2">
        <label class="admin-field"><span>Titre</span><input type="text" name="faq_cta_title" value="<?= e($faqCfg['cta_title']) ?>"></label>
        <label class="admin-field"><span>Texte bouton</span><input type="text" name="faq_cta_button" value="<?= e($faqCfg['cta_button']) ?>"></label>
      </div>
      <label class="admin-field"><span>Texte descriptif</span><textarea name="faq_cta_lead" rows="3"><?= e($faqCfg['cta_lead']) ?></textarea></label>
      <label class="admin-field"><span>Lien bouton (ex : contact)</span><input type="text" name="faq_cta_url" value="<?= e($faqCfg['cta_url']) ?>"></label>
    </div>
  </section>
</div>

<!-- ── FAQ QUESTIONS ── -->
<div class="admin-tab-panel" data-admin-panel="faq-questions">
  <?php foreach (($faqCfg['groups'] ?? []) as $gi => $group): ?>
    <section class="admin-panel">
      <div class="admin-panel__head">
        <h2>Catégorie : <?= e($group['category'] ?? '') ?></h2>
        <p>Les questions de cette catégorie.</p>
      </div>
      <div class="admin-panel__body">
        <label class="admin-field">
          <span>Nom de la catégorie</span>
          <input type="text" name="faq_groups[<?= e((string) $gi) ?>][category]" value="<?= e($group['category'] ?? '') ?>">
        </label>
        <div class="admin-form-grid" style="gap:1rem;">
          <?php foreach (($group['items'] ?? []) as $qi => $item): ?>
            <div class="repeat-card" style="grid-column:1/-1;">
              <h3>Question <?= e((string) ($qi + 1)) ?></h3>
              <label class="admin-field"><span>Question</span><input type="text" name="faq_groups[<?= e((string) $gi) ?>][items][<?= e((string) $qi) ?>][q]" value="<?= e($item['q'] ?? '') ?>"></label>
              <label class="admin-field"><span>Réponse</span><textarea name="faq_groups[<?= e((string) $gi) ?>][items][<?= e((string) $qi) ?>][a]" rows="4"><?= e($item['a'] ?? '') ?></textarea></label>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </section>
  <?php endforeach; ?>
  <div class="flash flash--success" style="margin-top:1rem;">💡 Pour ajouter des questions, modifiez les champs ci-dessus puis enregistrez. Les groupes vides sont supprimés automatiquement.</div>
</div>

<!-- ── FAQ SEO ── -->
<div class="admin-tab-panel" data-admin-panel="faq-seo">
  <section class="admin-panel">
    <div class="admin-panel__head"><h2>SEO de la page FAQ</h2></div>
    <div class="admin-panel__body admin-form-grid admin-form-grid--2">
      <label class="admin-field"><span>Meta title</span><input type="text" name="faq_meta_title" value="<?= e(setting('faq_meta_title', 'FAQ | ' . company_name())) ?>"></label>
      <label class="admin-field"><span>Meta description</span><input type="text" name="faq_meta_description" value="<?= e(setting('faq_meta_description', '')) ?>"></label>
    </div>
  </section>
</div>

<!-- ── CONTACT HERO ── -->
<div class="admin-tab-panel" data-admin-panel="contact-hero">
  <section class="admin-panel">
    <div class="admin-panel__head"><h2>Hero de la page Contact</h2><p>Le bloc bleu marine en haut de la page Contact.</p></div>
    <div class="admin-panel__body">
      <label class="admin-field"><span>Petit texte (eyebrow)</span><input type="text" name="contact_hero_eyebrow" value="<?= e($contactCfg['hero_eyebrow']) ?>"></label>
      <label class="admin-field"><span>Titre</span><input type="text" name="contact_hero_title" value="<?= e($contactCfg['hero_title']) ?>"></label>
      <label class="admin-field"><span>Texte descriptif</span><textarea name="contact_hero_lead" rows="3"><?= e($contactCfg['hero_lead']) ?></textarea></label>
    </div>
  </section>
  <section class="admin-panel">
    <div class="admin-panel__head"><h2>Bloc urgence</h2><p>Le bloc orange "Appelez directement" dans la colonne de droite.</p></div>
    <div class="admin-panel__body">
      <div class="admin-form-grid admin-form-grid--2">
        <label class="admin-field"><span>Titre</span><input type="text" name="contact_urgency_title" value="<?= e($contactCfg['urgency_title']) ?>"></label>
        <label class="admin-field"><span>Titre colonnes zones</span><input type="text" name="contact_zones_title" value="<?= e($contactCfg['zones_title']) ?>"></label>
      </div>
      <label class="admin-field"><span>Texte urgence</span><textarea name="contact_urgency_text" rows="3"><?= e($contactCfg['urgency_text']) ?></textarea></label>
    </div>
  </section>
</div>

<!-- ── CONTACT FORM ── -->
<div class="admin-tab-panel" data-admin-panel="contact-form">
  <section class="admin-panel">
    <div class="admin-panel__head"><h2>Formulaire de contact</h2><p>Les textes du formulaire principal.</p></div>
    <div class="admin-panel__body">
      <div class="admin-form-grid admin-form-grid--2">
        <label class="admin-field"><span>Titre du formulaire</span><input type="text" name="contact_form_title" value="<?= e($contactCfg['form_title']) ?>"></label>
        <label class="admin-field"><span>Sous-titre</span><input type="text" name="contact_form_subtitle" value="<?= e($contactCfg['form_subtitle']) ?>"></label>
        <label class="admin-field"><span>Titre bloc coordonnées</span><input type="text" name="contact_info_title" value="<?= e($contactCfg['info_title']) ?>"></label>
      </div>
      <div class="admin-panel__helper">
        <p>Le téléphone, l'email, les zones et les horaires viennent de <strong>Identité & coordonnées</strong>.</p>
        <a class="admin-btn admin-btn--secondary" href="<?= e(url_for('admin/site_identity.php')) ?>">Modifier les coordonnées</a>
      </div>
    </div>
  </section>
</div>

<!-- ── CONTACT SEO ── -->
<div class="admin-tab-panel" data-admin-panel="contact-seo">
  <section class="admin-panel">
    <div class="admin-panel__head"><h2>SEO de la page Contact</h2></div>
    <div class="admin-panel__body admin-form-grid admin-form-grid--2">
      <label class="admin-field"><span>Meta title</span><input type="text" name="contact_meta_title" value="<?= e(setting('contact_meta_title', 'Contact | ' . company_name())) ?>"></label>
      <label class="admin-field"><span>Meta description</span><input type="text" name="contact_meta_description" value="<?= e(setting('contact_meta_description', '')) ?>"></label>
    </div>
  </section>
</div>

<div class="admin-savebar">
  <button class="admin-btn admin-btn--primary" type="submit">Enregistrer FAQ & Contact</button>
</div>
</form>

<script>
document.addEventListener('DOMContentLoaded', function () {
  const buttons = Array.from(document.querySelectorAll('#faq-contact-tabs [data-admin-tab]'));
  const panels  = Array.from(document.querySelectorAll('#faq-contact-tabs [data-admin-panel]'));
  const key     = 'emae-faq-contact-tab';
  function activate(tab) {
    buttons.forEach(function (b) { b.classList.toggle('is-active', b.dataset.adminTab === tab); });
    panels.forEach(function (p)  { p.classList.toggle('is-active', p.dataset.adminPanel === tab); });
    try { localStorage.setItem(key, tab); } catch (e) {}
  }
  buttons.forEach(function (btn) { btn.addEventListener('click', function () { activate(btn.dataset.adminTab); }); });
  let init = 'faq-hero';
  try { const s = localStorage.getItem(key); if (s && buttons.some(function (b) { return b.dataset.adminTab === s; })) init = s; } catch (e) {}
  activate(init);
});
</script>

<?php require __DIR__ . '/partials/footer.php'; ?>
