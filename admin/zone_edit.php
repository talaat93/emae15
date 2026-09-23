<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/render.php';
require_admin();

$id   = (int)($_GET['id'] ?? 0);
$zone = $id > 0 ? get_zone_by_id($id) : null;
$isNew = ($zone === null);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $slug  = preg_replace('/[^a-z0-9-]/', '', strtolower(trim((string)($_POST['slug'] ?? ''))));
    $name  = trim((string)($_POST['name'] ?? ''));
    if ($slug === '' || $name === '') { flash('error','Le nom et le slug sont obligatoires.'); redirect_to('admin/zone_edit.php'.($id?'?id='.$id:'')); }

    // Build FAQ JSON from dynamic rows
    $faqQs = $_POST['faq_q'] ?? [];
    $faqAs = $_POST['faq_a'] ?? [];
    $faqArr = [];
    foreach ($faqQs as $i => $q) {
        $q = trim((string)$q); $a = trim((string)($faqAs[$i]??''));
        if ($q !== '' && $a !== '') $faqArr[] = [$q,$a];
    }
    $faqJson = !empty($faqArr) ? json_encode($faqArr, JSON_UNESCAPED_UNICODE) : null;

    $data = [
        'slug'              => $slug,
        'name'              => $name,
        'status'            => isset($_POST['status']) ? 1 : 0,
        'meta_title'        => trim((string)($_POST['meta_title']??'')),
        'meta_description'  => trim((string)($_POST['meta_description']??'')),
        'hero_h1'           => trim((string)($_POST['hero_h1']??'')),
        'hero_subtitle'     => trim((string)($_POST['hero_subtitle']??'')),
        'hero_cta_label'    => trim((string)($_POST['hero_cta_label']??'')),
        'cities'            => trim((string)($_POST['cities']??'')),
        'postal_codes'      => trim((string)($_POST['postal_codes']??'')),
        'faq'               => $faqJson,
        'mentions_legales'  => trim((string)($_POST['mentions_legales']??'')),
        'sort_order'        => (int)($_POST['sort_order']??0),
    ];

    if ($isNew) {
        create_zone($data);
        flash('success', 'Zone créée avec succès.');
    } else {
        update_zone($id, $data);
        flash('success', 'Zone mise à jour.');
    }
    redirect_to('admin/zones.php');
}

$faqRows = $zone ? zone_faq($zone) : [];
$adminSection = 'zones';
require_once __DIR__ . '/partials/header.php';
?>
<div class="admin-page-header">
  <div>
    <h1 class="admin-page-title"><?= $isNew ? 'Nouvelle zone' : 'Éditer : '.e($zone['name']) ?></h1>
    <p class="admin-page-sub"><?= $isNew ? 'Créer une nouvelle zone géographique.' : 'Modifier le contenu et les paramètres de cette zone.' ?></p>
  </div>
  <a class="btn btn-outline" href="<?= e(url_for('admin/zones.php')) ?>">← Retour</a>
</div>

<?php if (!$isNew): ?>
<div class="admin-card" style="border-left:4px solid #F07B1D;background:#fffaf4;margin-bottom:1.25rem;">
  <strong>Cette fiche ne couvre que les réglages de base de la zone.</strong>
  <p style="margin:.4rem 0 .75rem;color:var(--t2);font-size:.875rem;">
    Pour modifier tout le site de cette zone — identité, coordonnées, couleurs, accueil, services, pages, réalisations, SEO —
    basculez l'admin sur la zone. Les champs SEO et Hero ci-dessous restent prioritaires sur ceux de l'admin de zone.
  </p>
  <a class="btn btn-p" href="<?= e(url_for('admin/index.php?admin_zone='.$id)) ?>">🎛 Éditer tout le site de <?= e($zone['name']) ?></a>
</div>
<?php endif; ?>

<form method="post" id="zone-form">
  <?= csrf_field() ?>

  <div class="admin-tabs" style="margin-bottom:1.5rem;">
    <button type="button" class="admin-tab active" data-tab="general">Général</button>
    <button type="button" class="admin-tab" data-tab="seo">SEO</button>
    <button type="button" class="admin-tab" data-tab="hero">Hero</button>
    <button type="button" class="admin-tab" data-tab="faq">FAQ</button>
    <button type="button" class="admin-tab" data-tab="legal">Mentions légales</button>
  </div>

  <!-- ONGLET GÉNÉRAL -->
  <div class="admin-tab-content active" id="tab-general">
    <div class="admin-card">
      <div class="admin-card-title">Identité de la zone</div>
      <div class="form-grid2">
        <label class="form-label">Nom de la zone *
          <input type="text" name="name" class="form-input" value="<?= e($zone['name']??'') ?>" placeholder="Paris / Île-de-France" required id="zone-name">
        </label>
        <label class="form-label">Slug (URL) *
          <div style="position:relative;">
            <span style="position:absolute;left:.75rem;top:50%;transform:translateY(-50%);color:var(--t2);font-size:.85rem;">/</span>
            <input type="text" name="slug" class="form-input" style="padding-left:1.5rem;" value="<?= e($zone['slug']??'') ?>" placeholder="paris-ile-de-france" required id="zone-slug">
          </div>
          <small style="color:var(--t2);">URL : votre-site.fr/<strong id="slug-preview"><?= e($zone['slug']??'votre-slug') ?></strong>/</small>
          <?php if (!$isNew && ($nbTextes = zone_override_count((string)$zone['slug'])) > 0): ?>
            <small style="display:block;margin-top:.35rem;color:#b45309;">
              ⚠️ Changer l'adresse déplacera les <?= $nbTextes ?> textes de cette zone et modifiera son lien public.
              Le nom ci-contre se change sans aucun risque.
            </small>
          <?php endif; ?>
        </label>
      </div>
      <div class="form-grid2" style="margin-top:1rem;">
        <label class="form-label">Villes couvertes <small style="color:var(--t2);">(séparées par |)</small>
          <textarea name="cities" class="form-input" rows="3" placeholder="Paris (75)|Meaux (77)|Versailles (78)"><?= e($zone['cities']??'') ?></textarea>
        </label>
        <label class="form-label">Codes postaux / départements
          <input type="text" name="postal_codes" class="form-input" value="<?= e($zone['postal_codes']??'') ?>" placeholder="75,77,78,91,92,93,94,95">
        </label>
      </div>
      <div class="form-grid2" style="margin-top:1rem;">
        <label class="form-label">Ordre d'affichage
          <input type="number" name="sort_order" class="form-input" value="<?= (int)($zone['sort_order']??0) ?>" min="0" max="999">
        </label>
        <div class="form-label" style="display:flex;align-items:center;gap:.75rem;padding-top:1.5rem;">
          <label class="toggle-switch">
            <input type="checkbox" name="status" <?= ($zone ? (bool)$zone['status'] : true) ? 'checked' : '' ?>>
            <span class="toggle-slider"></span>
          </label>
          <span>Zone <strong>active</strong> (visible sur le site)</span>
        </div>
      </div>
    </div>
  </div>

  <!-- ONGLET SEO -->
  <div class="admin-tab-content" id="tab-seo">
    <div class="admin-card">
      <div class="admin-card-title">SEO — Méta balises</div>
      <p style="color:var(--t2);font-size:.875rem;margin-bottom:1.25rem;">Laissez vide pour utiliser les valeurs par défaut du site.</p>
      <label class="form-label">Meta Title <small>(max 160 car.)</small>
        <input type="text" name="meta_title" class="form-input" maxlength="160" value="<?= e($zone['meta_title']??'') ?>" placeholder="Électricien <?= e($zone['name']??'Paris') ?> — Devis gratuit | <?= e(company_name()) ?>">
        <small class="form-char-count" data-max="160" data-input="meta_title"></small>
      </label>
      <label class="form-label" style="margin-top:1rem;">Meta Description <small>(max 320 car.)</small>
        <textarea name="meta_description" class="form-input" maxlength="320" rows="3" placeholder="Électricien, plombier et chauffagiste à <?= e($zone['name']??'Paris') ?>. Intervention rapide, devis gratuit. Appelez le <?= e(company_phone()) ?>."><?= e($zone['meta_description']??'') ?></textarea>
        <small class="form-char-count" data-max="320" data-input="meta_description"></small>
      </label>
    </div>
  </div>

  <!-- ONGLET HERO -->
  <div class="admin-tab-content" id="tab-hero">
    <div class="admin-card">
      <div class="admin-card-title">Section Hero (page d'accueil de la zone)</div>
      <p style="color:var(--t2);font-size:.875rem;margin-bottom:1.25rem;">Laissez vide pour utiliser les textes génériques du site.</p>
      <label class="form-label">H1 — Titre principal
        <input type="text" name="hero_h1" class="form-input" value="<?= e($zone['hero_h1']??'') ?>" placeholder="Votre expert électricité à <?= e($zone['name']??'Paris') ?>">
        <small style="color:var(--t2);">Le dernier mot sera mis en surbrillance automatiquement.</small>
      </label>
      <label class="form-label" style="margin-top:1rem;">Sous-titre / Lead
        <textarea name="hero_subtitle" class="form-input" rows="3" placeholder="Dépannage électrique, plomberie et chauffage à <?= e($zone['name']??'Paris') ?>. Intervention rapide sous 2h, devis gratuit, artisans qualifiés."><?= e($zone['hero_subtitle']??'') ?></textarea>
      </label>
      <label class="form-label" style="margin-top:1rem;">Label bouton principal
        <input type="text" name="hero_cta_label" class="form-input" value="<?= e($zone['hero_cta_label']??'') ?>" placeholder="Devis gratuit">
      </label>
    </div>
  </div>

  <!-- ONGLET FAQ -->
  <div class="admin-tab-content" id="tab-faq">
    <div class="admin-card">
      <div class="admin-card-title">FAQ — Questions fréquentes de la zone</div>
      <p style="color:var(--t2);font-size:.875rem;margin-bottom:1.25rem;">Si vous ajoutez des FAQ ici, elles remplaceront la FAQ générique sur la page de cette zone.</p>
      <div id="faq-rows">
        <?php foreach ($faqRows as $i => [$fq,$fa]): ?>
        <div class="faq-row" style="border:1px solid var(--border);border-radius:8px;padding:1rem;margin-bottom:.75rem;">
          <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.5rem;">
            <strong style="font-size:.85rem;">Question <?= $i+1 ?></strong>
            <button type="button" class="btn btn-sm" style="background:#dc2626;color:#fff;border:none;" onclick="this.closest('.faq-row').remove()">✕</button>
          </div>
          <input type="text" name="faq_q[]" class="form-input" value="<?= e($fq) ?>" placeholder="Question…" style="margin-bottom:.5rem;">
          <textarea name="faq_a[]" class="form-input" rows="2" placeholder="Réponse…"><?= e($fa) ?></textarea>
        </div>
        <?php endforeach; ?>
      </div>
      <button type="button" class="btn btn-outline" onclick="addFaqRow()">+ Ajouter une question</button>
    </div>
  </div>

  <!-- ONGLET MENTIONS LÉGALES -->
  <div class="admin-tab-content" id="tab-legal">
    <div class="admin-card">
      <div class="admin-card-title">Mentions légales de la zone</div>
      <p style="color:var(--t2);font-size:.875rem;margin-bottom:1.25rem;">
        Laissez vide pour afficher les mentions légales génériques (recommandé si c'est la même entité juridique pour toutes les zones).
        Si vous remplissez ce champ, son contenu sera affiché à la place pour cette zone.
      </p>
      <label class="form-label">Contenu des mentions légales <small>(texte brut, retours à la ligne conservés)</small>
        <textarea name="mentions_legales" class="form-input" rows="20" placeholder="Laissez vide pour utiliser les mentions légales génériques du site."><?= e($zone['mentions_legales']??'') ?></textarea>
      </label>
    </div>
  </div>

  <div style="margin-top:1.5rem;display:flex;gap:.75rem;">
    <button type="submit" class="btn btn-p btn-lg"><?= $isNew ? '✅ Créer la zone' : '💾 Enregistrer' ?></button>
    <?php if (!$isNew && (bool)($zone['status']??0)): ?>
    <a class="btn btn-outline btn-lg" href="<?= e(url_for($zone['slug'].'/')) ?>" target="_blank">↗ Voir la page</a>
    <?php endif; ?>
    <a class="btn btn-outline" href="<?= e(url_for('admin/zones.php')) ?>" style="margin-left:auto;">Annuler</a>
  </div>
</form>

<style>
.admin-tabs { display:flex;gap:.5rem;border-bottom:2px solid var(--border);padding-bottom:0; }
.admin-tab { background:none;border:none;cursor:pointer;padding:.6rem 1.1rem;font-size:.875rem;font-weight:600;color:var(--t2);border-bottom:2px solid transparent;margin-bottom:-2px;transition:all .15s; }
.admin-tab.active { color:var(--accent);border-bottom-color:var(--accent); }
.admin-tab-content { display:none; }
.admin-tab-content.active { display:block; }
.form-char-count { display:block;margin-top:.25rem;color:var(--t2);font-size:.75rem; }
.toggle-switch { position:relative;display:inline-block;width:44px;height:24px; }
.toggle-switch input { opacity:0;width:0;height:0; }
.toggle-slider { position:absolute;inset:0;background:#d1d5db;border-radius:24px;transition:.2s; }
.toggle-slider:before { content:'';position:absolute;width:18px;height:18px;background:#fff;border-radius:50%;left:3px;top:3px;transition:.2s; }
.toggle-switch input:checked + .toggle-slider { background:var(--accent); }
.toggle-switch input:checked + .toggle-slider:before { transform:translateX(20px); }
</style>

<script>
// Tabs
document.querySelectorAll('.admin-tab').forEach(btn => {
    btn.addEventListener('click', () => {
        document.querySelectorAll('.admin-tab,.admin-tab-content').forEach(el=>el.classList.remove('active'));
        btn.classList.add('active');
        document.getElementById('tab-'+btn.dataset.tab).classList.add('active');
    });
});

// Slug auto-gen from name
const nameInput = document.getElementById('zone-name');
const slugInput = document.getElementById('zone-slug');
const slugPreview = document.getElementById('slug-preview');
if (nameInput && slugInput && <?= $isNew ? 'true' : 'false' ?>) {
    nameInput.addEventListener('input', () => {
        const s = nameInput.value.toLowerCase()
            .normalize('NFD').replace(/[̀-ͯ]/g,'')
            .replace(/[^a-z0-9]+/g,'-').replace(/^-+|-+$/g,'');
        slugInput.value = s;
        if (slugPreview) slugPreview.textContent = s || 'votre-slug';
    });
}
slugInput?.addEventListener('input', () => {
    if (slugPreview) slugPreview.textContent = slugInput.value || 'votre-slug';
});

// FAQ rows
let faqCount = <?= count($faqRows) ?>;
function addFaqRow() {
    faqCount++;
    const div = document.createElement('div');
    div.className = 'faq-row';
    div.style.cssText = 'border:1px solid var(--border);border-radius:8px;padding:1rem;margin-bottom:.75rem;';
    div.innerHTML = `<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.5rem;">
        <strong style="font-size:.85rem;">Question ${faqCount}</strong>
        <button type="button" class="btn btn-sm" style="background:#dc2626;color:#fff;border:none;" onclick="this.closest('.faq-row').remove()">✕</button>
    </div>
    <input type="text" name="faq_q[]" class="form-input" placeholder="Question…" style="margin-bottom:.5rem;">
    <textarea name="faq_a[]" class="form-input" rows="2" placeholder="Réponse…"></textarea>`;
    document.getElementById('faq-rows').appendChild(div);
}

// Char counters
document.querySelectorAll('.form-char-count').forEach(el => {
    const inp = document.querySelector(`[name="${el.dataset.input}"]`);
    if (!inp) return;
    const update = () => { el.textContent = `${inp.value.length} / ${el.dataset.max} caractères`; };
    inp.addEventListener('input', update); update();
});
</script>

<?php require_once __DIR__ . '/partials/footer.php'; ?>
