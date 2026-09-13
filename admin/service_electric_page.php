<?php
$adminSection = 'service_electric_page';
require __DIR__ . '/partials/header.php';

$page = db_fetch('SELECT * FROM pages WHERE slug = ? LIMIT 1', ['electricien-meaux']) ?: ['title' => 'Électricien urgence'];
$config = service_electric_page_settings($page);

function electric_rows(array $rows): array
{
    return array_values(array_filter($rows, static fn($row) => is_array($row)));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $currentMapImage = trim((string) ($_POST['zones']['current_map_image'] ?? ''));
    $uploadedMapImage = upload_image_field('zones_map_image', 'services');

    $new = [
        'meta' => [
            'page_title' => trim((string) ($_POST['meta']['page_title'] ?? 'Électricien urgence')),
            'excerpt' => trim((string) ($_POST['meta']['excerpt'] ?? '')),
            'meta_title' => trim((string) ($_POST['meta']['meta_title'] ?? '')),
            'meta_description' => trim((string) ($_POST['meta']['meta_description'] ?? '')),
        ],
        'hero' => [
            'eyebrow' => trim((string) ($_POST['hero']['eyebrow'] ?? '')),
            'title' => trim((string) ($_POST['hero']['title'] ?? '')),
            'lead' => trim((string) ($_POST['hero']['lead'] ?? '')),
            'primary_label' => trim((string) ($_POST['hero']['primary_label'] ?? '')),
            'primary_url' => trim((string) ($_POST['hero']['primary_url'] ?? '')),
            'secondary_label' => trim((string) ($_POST['hero']['secondary_label'] ?? '')),
            'secondary_url' => trim((string) ($_POST['hero']['secondary_url'] ?? '')),
        ],
        'interventions' => [
            'title' => trim((string) ($_POST['interventions']['title'] ?? '')),
            'lead' => trim((string) ($_POST['interventions']['lead'] ?? '')),
            'items' => [],
        ],
        'trust' => [
            'title' => trim((string) ($_POST['trust']['title'] ?? '')),
            'lead' => trim((string) ($_POST['trust']['lead'] ?? '')),
            'benefits' => [],
            'price_label' => trim((string) ($_POST['trust']['price_label'] ?? '')),
            'price_value' => trim((string) ($_POST['trust']['price_value'] ?? '')),
            'price_note' => trim((string) ($_POST['trust']['price_note'] ?? '')),
            'footer_text' => trim((string) ($_POST['trust']['footer_text'] ?? '')),
            'button_label' => trim((string) ($_POST['trust']['button_label'] ?? '')),
            'button_url' => trim((string) ($_POST['trust']['button_url'] ?? '')),
        ],
        'steps' => [
            'title' => trim((string) ($_POST['steps']['title'] ?? '')),
            'lead' => trim((string) ($_POST['steps']['lead'] ?? '')),
            'items' => [],
        ],
        'zones' => [
            'title' => trim((string) ($_POST['zones']['title'] ?? '')),
            'lead' => trim((string) ($_POST['zones']['lead'] ?? '')),
            'map_image' => $uploadedMapImage ?: $currentMapImage,
            'items' => [],
        ],
        'faq' => [
            'title' => trim((string) ($_POST['faq']['title'] ?? '')),
            'items' => [],
        ],
        'contact' => [
            'title' => trim((string) ($_POST['contact']['title'] ?? '')),
            'lead' => trim((string) ($_POST['contact']['lead'] ?? '')),
            'form_title' => trim((string) ($_POST['contact']['form_title'] ?? '')),
            'info_title' => trim((string) ($_POST['contact']['info_title'] ?? '')),
            'reasons_title' => trim((string) ($_POST['contact']['reasons_title'] ?? '')),
            'reasons' => [],
        ],
    ];

    foreach ((array) ($_POST['interventions']['items'] ?? []) as $row) {
        if (!is_array($row)) {
            continue;
        }
        $item = [
            'icon' => trim((string) ($row['icon'] ?? '')),
            'title' => trim((string) ($row['title'] ?? '')),
            'text' => trim((string) ($row['text'] ?? '')),
        ];
        if ($item['icon'] === '' && $item['title'] === '' && $item['text'] === '') {
            continue;
        }
        $new['interventions']['items'][] = $item;
    }

    foreach ((array) ($_POST['trust']['benefits'] ?? []) as $row) {
        if (!is_array($row)) {
            continue;
        }
        $item = [
            'title' => trim((string) ($row['title'] ?? '')),
            'text' => trim((string) ($row['text'] ?? '')),
        ];
        if ($item['title'] === '' && $item['text'] === '') {
            continue;
        }
        $new['trust']['benefits'][] = $item;
    }

    foreach ((array) ($_POST['steps']['items'] ?? []) as $row) {
        if (!is_array($row)) {
            continue;
        }
        $item = [
            'number' => trim((string) ($row['number'] ?? '')),
            'title' => trim((string) ($row['title'] ?? '')),
            'text' => trim((string) ($row['text'] ?? '')),
        ];
        if ($item['number'] === '' && $item['title'] === '' && $item['text'] === '') {
            continue;
        }
        $new['steps']['items'][] = $item;
    }

    foreach ((array) ($_POST['zones']['items'] ?? []) as $row) {
        if (!is_array($row)) {
            continue;
        }
        $label = trim((string) ($row['label'] ?? ''));
        if ($label === '') {
            continue;
        }
        $new['zones']['items'][] = ['label' => $label];
    }

    foreach ((array) ($_POST['faq']['items'] ?? []) as $row) {
        if (!is_array($row)) {
            continue;
        }
        $item = [
            'question' => trim((string) ($row['question'] ?? '')),
            'answer' => trim((string) ($row['answer'] ?? '')),
        ];
        if ($item['question'] === '' && $item['answer'] === '') {
            continue;
        }
        $new['faq']['items'][] = $item;
    }

    foreach ((array) ($_POST['contact']['reasons'] ?? []) as $row) {
        if (!is_array($row)) {
            continue;
        }
        $text = trim((string) ($row['text'] ?? ''));
        if ($text === '') {
            continue;
        }
        $new['contact']['reasons'][] = ['text' => $text];
    }

    set_json_setting('service_electric_page', $new);
    service_electric_page_sync_page($new);
    flash('success', 'La page électricien-meaux a été enregistrée.');
    redirect_to('admin/service_electric_page.php');
}
?>
<style>
.electric-admin-stack{display:grid;gap:1rem}.electric-admin-panel{background:#fff;border:1px solid #e6ebf5;border-radius:22px;padding:1.2rem}.electric-admin-panel h2{margin:.2rem 0 1rem}.electric-admin-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:1rem}.electric-admin-grid .full{grid-column:1/-1}.electric-repeater{display:grid;gap:.8rem}.electric-repeater__item{border:1px solid #e8edf7;border-radius:18px;padding:1rem;background:#f8fbff}.electric-repeater__row{display:grid;grid-template-columns:160px 1fr 1fr auto;gap:.8rem}.electric-repeater__row--two{grid-template-columns:1fr 1fr auto}.electric-repeater__row--single{grid-template-columns:1fr auto}.electric-actions{display:flex;gap:.7rem;flex-wrap:wrap}.electric-thumb{max-width:260px;border-radius:14px;border:1px solid #e6ebf5;margin-top:.8rem}@media (max-width:900px){.electric-admin-grid,.electric-repeater__row,.electric-repeater__row--two,.electric-repeater__row--single{grid-template-columns:1fr}}</style>

<div class="admin-page-toolbar">
  <div>
    <div class="admin-breadcrumb">Landing pages</div>
    <h1 class="admin-page-title">Page électricien Meaux</h1>
    <p class="admin-page-subtitle">Édition dédiée de la page <strong>electricien-meaux</strong> avec le style EMAE.</p>
  </div>
  <div class="electric-actions">
    <a class="admin-btn admin-btn--secondary" href="<?= e(url_for('index.php?route=electricien-meaux')) ?>" target="_blank">Voir la page</a>
  </div>
</div>

<form method="post" enctype="multipart/form-data" class="electric-admin-stack" id="electric-page-form">
  <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

  <section class="electric-admin-panel">
    <h2>SEO & page</h2>
    <div class="electric-admin-grid">
      <label><span>Titre page</span><input type="text" name="meta[page_title]" value="<?= e($config['meta']['page_title']) ?>"></label>
      <label><span>Extrait</span><input type="text" name="meta[excerpt]" value="<?= e($config['meta']['excerpt']) ?>"></label>
      <label class="full"><span>Meta title</span><input type="text" name="meta[meta_title]" value="<?= e($config['meta']['meta_title']) ?>"></label>
      <label class="full"><span>Meta description</span><textarea name="meta[meta_description]" rows="3"><?= e($config['meta']['meta_description']) ?></textarea></label>
    </div>
  </section>

  <section class="electric-admin-panel">
    <h2>Hero</h2>
    <div class="electric-admin-grid">
      <label><span>Eyebrow</span><input type="text" name="hero[eyebrow]" value="<?= e($config['hero']['eyebrow']) ?>"></label>
      <label><span>Titre</span><input type="text" name="hero[title]" value="<?= e($config['hero']['title']) ?>"></label>
      <label class="full"><span>Texte</span><textarea name="hero[lead]" rows="3"><?= e($config['hero']['lead']) ?></textarea></label>
      <label><span>Bouton 1</span><input type="text" name="hero[primary_label]" value="<?= e($config['hero']['primary_label']) ?>"></label>
      <label><span>Lien bouton 1</span><input type="text" name="hero[primary_url]" value="<?= e($config['hero']['primary_url']) ?>"></label>
      <label><span>Bouton 2</span><input type="text" name="hero[secondary_label]" value="<?= e($config['hero']['secondary_label']) ?>"></label>
      <label><span>Lien bouton 2</span><input type="text" name="hero[secondary_url]" value="<?= e($config['hero']['secondary_url']) ?>"></label>
    </div>
  </section>

  <section class="electric-admin-panel">
    <h2>Bloc interventions</h2>
    <div class="electric-admin-grid">
      <label><span>Titre</span><input type="text" name="interventions[title]" value="<?= e($config['interventions']['title']) ?>"></label>
      <label><span>Texte</span><input type="text" name="interventions[lead]" value="<?= e($config['interventions']['lead']) ?>"></label>
    </div>
    <div class="electric-actions" style="margin:1rem 0;"><button type="button" class="admin-btn admin-btn--primary" data-add="intervention">Ajouter une carte</button></div>
    <div class="electric-repeater" data-host="intervention">
      <?php foreach ($config['interventions']['items'] as $i => $item): ?>
        <div class="electric-repeater__item" data-item>
          <div class="electric-repeater__row">
            <label><span>Icône</span><input type="text" name="interventions[items][<?= e((string) $i) ?>][icon]" value="<?= e($item['icon']) ?>"></label>
            <label><span>Titre</span><input type="text" name="interventions[items][<?= e((string) $i) ?>][title]" value="<?= e($item['title']) ?>"></label>
            <label><span>Texte</span><input type="text" name="interventions[items][<?= e((string) $i) ?>][text]" value="<?= e($item['text']) ?>"></label>
            <div class="electric-actions"><button type="button" class="admin-btn admin-btn--secondary" data-remove>Supprimer</button></div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </section>

  <section class="electric-admin-panel">
    <h2>Bloc confiance / prix</h2>
    <div class="electric-admin-grid">
      <label><span>Titre</span><input type="text" name="trust[title]" value="<?= e($config['trust']['title']) ?>"></label>
      <label><span>Texte</span><input type="text" name="trust[lead]" value="<?= e($config['trust']['lead']) ?>"></label>
      <label><span>Label prix</span><input type="text" name="trust[price_label]" value="<?= e($config['trust']['price_label']) ?>"></label>
      <label><span>Montant</span><input type="text" name="trust[price_value]" value="<?= e($config['trust']['price_value']) ?>"></label>
      <label class="full"><span>Note prix</span><input type="text" name="trust[price_note]" value="<?= e($config['trust']['price_note']) ?>"></label>
      <label><span>Texte footer</span><input type="text" name="trust[footer_text]" value="<?= e($config['trust']['footer_text']) ?>"></label>
      <label><span>Bouton CTA</span><input type="text" name="trust[button_label]" value="<?= e($config['trust']['button_label']) ?>"></label>
      <label class="full"><span>Lien CTA</span><input type="text" name="trust[button_url]" value="<?= e($config['trust']['button_url']) ?>"></label>
    </div>
    <div class="electric-actions" style="margin:1rem 0;"><button type="button" class="admin-btn admin-btn--primary" data-add="benefit">Ajouter un avantage</button></div>
    <div class="electric-repeater" data-host="benefit">
      <?php foreach ($config['trust']['benefits'] as $i => $item): ?>
        <div class="electric-repeater__item" data-item>
          <div class="electric-repeater__row--two electric-repeater__row">
            <label><span>Titre</span><input type="text" name="trust[benefits][<?= e((string) $i) ?>][title]" value="<?= e($item['title']) ?>"></label>
            <label><span>Texte</span><input type="text" name="trust[benefits][<?= e((string) $i) ?>][text]" value="<?= e($item['text']) ?>"></label>
            <div class="electric-actions"><button type="button" class="admin-btn admin-btn--secondary" data-remove>Supprimer</button></div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </section>

  <section class="electric-admin-panel">
    <h2>Process en 3 étapes</h2>
    <div class="electric-admin-grid">
      <label><span>Titre</span><input type="text" name="steps[title]" value="<?= e($config['steps']['title']) ?>"></label>
      <label><span>Texte</span><input type="text" name="steps[lead]" value="<?= e($config['steps']['lead']) ?>"></label>
    </div>
    <div class="electric-actions" style="margin:1rem 0;"><button type="button" class="admin-btn admin-btn--primary" data-add="step">Ajouter une étape</button></div>
    <div class="electric-repeater" data-host="step">
      <?php foreach ($config['steps']['items'] as $i => $item): ?>
        <div class="electric-repeater__item" data-item>
          <div class="electric-repeater__row">
            <label><span>N°</span><input type="text" name="steps[items][<?= e((string) $i) ?>][number]" value="<?= e($item['number']) ?>"></label>
            <label><span>Titre</span><input type="text" name="steps[items][<?= e((string) $i) ?>][title]" value="<?= e($item['title']) ?>"></label>
            <label><span>Texte</span><input type="text" name="steps[items][<?= e((string) $i) ?>][text]" value="<?= e($item['text']) ?>"></label>
            <div class="electric-actions"><button type="button" class="admin-btn admin-btn--secondary" data-remove>Supprimer</button></div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </section>

  <section class="electric-admin-panel">
    <h2>Zones d’intervention</h2>
    <div class="electric-admin-grid">
      <label><span>Titre</span><input type="text" name="zones[title]" value="<?= e($config['zones']['title']) ?>"></label>
      <label><span>Texte</span><input type="text" name="zones[lead]" value="<?= e($config['zones']['lead']) ?>"></label>
      <input type="hidden" name="zones[current_map_image]" value="<?= e($config['zones']['map_image']) ?>">
      <label class="full"><span>Image carte</span><input type="file" name="zones_map_image" accept=".png,.jpg,.jpeg,.webp,.svg"></label>
      <?php if (!empty($config['zones']['map_image'])): ?>
        <div class="full"><img class="electric-thumb" src="<?= e(asset_url($config['zones']['map_image'])) ?>" alt="Carte"></div>
      <?php endif; ?>
    </div>
    <div class="electric-actions" style="margin:1rem 0;"><button type="button" class="admin-btn admin-btn--primary" data-add="zone">Ajouter une zone</button></div>
    <div class="electric-repeater" data-host="zone">
      <?php foreach ($config['zones']['items'] as $i => $item): ?>
        <div class="electric-repeater__item" data-item>
          <div class="electric-repeater__row--single electric-repeater__row">
            <label><span>Zone</span><input type="text" name="zones[items][<?= e((string) $i) ?>][label]" value="<?= e($item['label']) ?>"></label>
            <div class="electric-actions"><button type="button" class="admin-btn admin-btn--secondary" data-remove>Supprimer</button></div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </section>

  <section class="electric-admin-panel">
    <h2>FAQ</h2>
    <label><span>Titre</span><input type="text" name="faq[title]" value="<?= e($config['faq']['title']) ?>"></label>
    <div class="electric-actions" style="margin:1rem 0;"><button type="button" class="admin-btn admin-btn--primary" data-add="faq">Ajouter une question</button></div>
    <div class="electric-repeater" data-host="faq">
      <?php foreach ($config['faq']['items'] as $i => $item): ?>
        <div class="electric-repeater__item" data-item>
          <div class="electric-admin-grid">
            <label class="full"><span>Question</span><input type="text" name="faq[items][<?= e((string) $i) ?>][question]" value="<?= e($item['question']) ?>"></label>
            <label class="full"><span>Réponse</span><textarea name="faq[items][<?= e((string) $i) ?>][answer]" rows="3"><?= e($item['answer']) ?></textarea></label>
          </div>
          <div class="electric-actions" style="margin-top:.8rem;"><button type="button" class="admin-btn admin-btn--secondary" data-remove>Supprimer</button></div>
        </div>
      <?php endforeach; ?>
    </div>
  </section>

  <section class="electric-admin-panel">
    <h2>Bloc contact</h2>
    <div class="electric-admin-grid">
      <label><span>Titre</span><input type="text" name="contact[title]" value="<?= e($config['contact']['title']) ?>"></label>
      <label><span>Texte</span><input type="text" name="contact[lead]" value="<?= e($config['contact']['lead']) ?>"></label>
      <label><span>Titre formulaire</span><input type="text" name="contact[form_title]" value="<?= e($config['contact']['form_title']) ?>"></label>
      <label><span>Titre infos</span><input type="text" name="contact[info_title]" value="<?= e($config['contact']['info_title']) ?>"></label>
      <label class="full"><span>Titre raisons</span><input type="text" name="contact[reasons_title]" value="<?= e($config['contact']['reasons_title']) ?>"></label>
    </div>
    <div class="electric-actions" style="margin:1rem 0;"><button type="button" class="admin-btn admin-btn--primary" data-add="reason">Ajouter une raison</button></div>
    <div class="electric-repeater" data-host="reason">
      <?php foreach ($config['contact']['reasons'] as $i => $item): ?>
        <div class="electric-repeater__item" data-item>
          <div class="electric-repeater__row--single electric-repeater__row">
            <label><span>Raison</span><input type="text" name="contact[reasons][<?= e((string) $i) ?>][text]" value="<?= e($item['text']) ?>"></label>
            <div class="electric-actions"><button type="button" class="admin-btn admin-btn--secondary" data-remove>Supprimer</button></div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </section>

  <div class="admin-savebar"><button type="submit" class="admin-btn admin-btn--primary">Enregistrer la page électricien-meaux</button></div>
</form>

<template id="tpl-intervention"><div class="electric-repeater__item" data-item><div class="electric-repeater__row"><label><span>Icône</span><input type="text" name="interventions[items][__INDEX__][icon]"></label><label><span>Titre</span><input type="text" name="interventions[items][__INDEX__][title]"></label><label><span>Texte</span><input type="text" name="interventions[items][__INDEX__][text]"></label><div class="electric-actions"><button type="button" class="admin-btn admin-btn--secondary" data-remove>Supprimer</button></div></div></div></template>
<template id="tpl-benefit"><div class="electric-repeater__item" data-item><div class="electric-repeater__row--two electric-repeater__row"><label><span>Titre</span><input type="text" name="trust[benefits][__INDEX__][title]"></label><label><span>Texte</span><input type="text" name="trust[benefits][__INDEX__][text]"></label><div class="electric-actions"><button type="button" class="admin-btn admin-btn--secondary" data-remove>Supprimer</button></div></div></div></template>
<template id="tpl-step"><div class="electric-repeater__item" data-item><div class="electric-repeater__row"><label><span>N°</span><input type="text" name="steps[items][__INDEX__][number]"></label><label><span>Titre</span><input type="text" name="steps[items][__INDEX__][title]"></label><label><span>Texte</span><input type="text" name="steps[items][__INDEX__][text]"></label><div class="electric-actions"><button type="button" class="admin-btn admin-btn--secondary" data-remove>Supprimer</button></div></div></div></template>
<template id="tpl-zone"><div class="electric-repeater__item" data-item><div class="electric-repeater__row--single electric-repeater__row"><label><span>Zone</span><input type="text" name="zones[items][__INDEX__][label]"></label><div class="electric-actions"><button type="button" class="admin-btn admin-btn--secondary" data-remove>Supprimer</button></div></div></div></template>
<template id="tpl-faq"><div class="electric-repeater__item" data-item><div class="electric-admin-grid"><label class="full"><span>Question</span><input type="text" name="faq[items][__INDEX__][question]"></label><label class="full"><span>Réponse</span><textarea name="faq[items][__INDEX__][answer]" rows="3"></textarea></label></div><div class="electric-actions" style="margin-top:.8rem;"><button type="button" class="admin-btn admin-btn--secondary" data-remove>Supprimer</button></div></div></template>
<template id="tpl-reason"><div class="electric-repeater__item" data-item><div class="electric-repeater__row--single electric-repeater__row"><label><span>Raison</span><input type="text" name="contact[reasons][__INDEX__][text]"></label><div class="electric-actions"><button type="button" class="admin-btn admin-btn--secondary" data-remove>Supprimer</button></div></div></div></template>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const hosts = {
    intervention: document.querySelector('[data-host="intervention"]'),
    benefit: document.querySelector('[data-host="benefit"]'),
    step: document.querySelector('[data-host="step"]'),
    zone: document.querySelector('[data-host="zone"]'),
    faq: document.querySelector('[data-host="faq"]'),
    reason: document.querySelector('[data-host="reason"]')
  };
  const templates = {
    intervention: document.getElementById('tpl-intervention'),
    benefit: document.getElementById('tpl-benefit'),
    step: document.getElementById('tpl-step'),
    zone: document.getElementById('tpl-zone'),
    faq: document.getElementById('tpl-faq'),
    reason: document.getElementById('tpl-reason')
  };
  document.querySelectorAll('[data-add]').forEach(btn => {
    btn.addEventListener('click', () => {
      const type = btn.getAttribute('data-add');
      const host = hosts[type];
      const tpl = templates[type];
      if (!host || !tpl) return;
      const index = host.querySelectorAll('[data-item]').length;
      const html = tpl.innerHTML.replaceAll('__INDEX__', String(index));
      const wrap = document.createElement('div');
      wrap.innerHTML = html.trim();
      const node = wrap.firstElementChild;
      if (node) host.appendChild(node);
    });
  });
  document.getElementById('electric-page-form').addEventListener('click', (e) => {
    const btn = e.target.closest('[data-remove]');
    if (!btn) return;
    const item = btn.closest('[data-item]');
    if (item) item.remove();
  });
});
</script>

<?php require __DIR__ . '/partials/footer.php'; ?>
