<?php
declare(strict_types=1);
$pageTitle   = 'Gestion des presets';
$dispSection = 'presets';
require __DIR__.'/partials/header.php';

/* ─────────────────────────────────────────────────────
   POST — Ajout / Suppression
───────────────────────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $postAction = trim((string)($_POST['post_action'] ?? ''));

    if ($postAction === 'add') {
        $type  = trim((string)($_POST['type']     ?? ''));
        $label = trim((string)($_POST['label']    ?? ''));
        $cat   = trim((string)($_POST['category'] ?? ''));
        if ($type !== '' && $label !== '') {
            save_preset($type, $label, $cat);
            flash('success', 'Preset ajouté.');
        } else {
            flash('error', 'Type et libellé requis.');
        }
    } elseif ($postAction === 'delete') {
        $pid = (int)($_POST['preset_id'] ?? 0);
        if ($pid > 0) {
            delete_preset($pid);
            flash('success', 'Preset supprimé.');
        }
    }
    $tab = trim((string)($_POST['active_tab'] ?? 'intervention_type'));
    redirect_to('dispatcher/presets.php?tab='.urlencode($tab));
}

$activeTab = trim((string)($_GET['tab'] ?? 'intervention_type'));
if (!in_array($activeTab, ['intervention_type','material','photo_type'], true)) {
    $activeTab = 'intervention_type';
}

$allPresets = all_presets_grouped();
$catsCfg    = intervention_category_config();
?>

<!-- TOPBAR -->
<div class="d-topbar">
  <div style="display:flex;align-items:center;gap:.75rem;">
    <button class="d-menu-toggle" id="d-menu-toggle" aria-label="Menu">☰</button>
    <div class="d-topbar-title">⚙️ Gestion des presets</div>
  </div>
</div>

<div class="d-content">

  <!-- Onglets -->
  <div style="display:flex;gap:.5rem;margin-bottom:1.5rem;border-bottom:1px solid rgba(255,255,255,.1);padding-bottom:.75rem;flex-wrap:wrap;">
    <?php
    $tabs = [
        'intervention_type' => ['label'=>'Types d\'interventions', 'icon'=>'🔧'],
        'material'          => ['label'=>'Matériaux',              'icon'=>'🔩'],
        'photo_type'        => ['label'=>'Types de photos',        'icon'=>'📷'],
    ];
    foreach ($tabs as $tk => $tv): ?>
      <a href="?tab=<?= urlencode($tk) ?>"
         class="d-btn <?= $activeTab === $tk ? 'd-btn--primary' : 'd-btn--ghost' ?> d-btn--sm"
         style="text-decoration:none;">
        <?= $tv['icon'] ?> <?= e($tv['label']) ?>
      </a>
    <?php endforeach; ?>
  </div>

  <?php
  // ── Onglet Types d'interventions ──
  if ($activeTab === 'intervention_type'):
    $items = $allPresets['intervention_type'];
    // Grouper par catégorie
    $grouped = [];
    foreach ($items as $item) {
        $c = (string)($item['category'] ?? '');
        $grouped[$c][] = $item;
    }
  ?>
  <div class="d-card" style="margin-bottom:1.5rem;">
    <div class="d-card-head">
      <div class="d-card-title">🔧 Types d'interventions</div>
    </div>
    <div class="d-card-body">
      <?php if (empty($items)): ?>
        <p style="color:#8fa0c4;font-size:.88rem;">Aucun preset défini.</p>
      <?php else: ?>
        <?php foreach ($grouped as $cat => $catItems): ?>
          <?php $catLabel = $cat !== '' ? (($catsCfg[$cat]['icon'] ?? '') . ' ' . ($catsCfg[$cat]['label'] ?? ucfirst($cat))) : 'Général'; ?>
          <div style="margin-bottom:1.25rem;">
            <div style="font-size:.75rem;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:#F07B1D;margin-bottom:.5rem;"><?= e($catLabel) ?></div>
            <div style="display:flex;flex-wrap:wrap;gap:.5rem;">
              <?php foreach ($catItems as $item): ?>
                <form method="post" style="display:inline;">
                  <input type="hidden" name="csrf_token"   value="<?= e(csrf_token()) ?>">
                  <input type="hidden" name="post_action"  value="delete">
                  <input type="hidden" name="preset_id"    value="<?= (int)$item['id'] ?>">
                  <input type="hidden" name="active_tab"   value="intervention_type">
                  <span style="display:inline-flex;align-items:center;gap:.35rem;background:rgba(240,123,29,.1);border:1px solid rgba(240,123,29,.25);border-radius:20px;padding:.3rem .75rem;font-size:.83rem;color:#e8ecf5;">
                    <?= e($item['label']) ?>
                    <button type="submit" onclick="return confirm('Supprimer ce preset ?')" style="background:none;border:none;color:#8fa0c4;cursor:pointer;padding:0;font-size:.75rem;line-height:1;" title="Supprimer">✕</button>
                  </span>
                </form>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>

  <!-- Formulaire ajout -->
  <div class="d-card">
    <div class="d-card-head">
      <div class="d-card-title">➕ Ajouter un type d'intervention</div>
    </div>
    <div class="d-card-body">
      <form method="post">
        <input type="hidden" name="csrf_token"  value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="post_action" value="add">
        <input type="hidden" name="type"        value="intervention_type">
        <input type="hidden" name="active_tab"  value="intervention_type">
        <div class="d-grid-2" style="margin-bottom:1rem;">
          <div class="d-field">
            <label>Catégorie</label>
            <select name="category" class="d-input">
              <option value="">— Général —</option>
              <?php foreach ($catsCfg as $ck => $cv): ?>
                <option value="<?= e($ck) ?>"><?= e($cv['icon'].' '.$cv['label']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="d-field">
            <label>Libellé <span style="color:#ef4444">*</span></label>
            <input type="text" name="label" class="d-input" placeholder="Ex: Remplacement contacteur" required>
          </div>
        </div>
        <button type="submit" class="d-btn d-btn--primary d-btn--sm">✅ Ajouter</button>
      </form>
    </div>
  </div>

  <?php
  // ── Onglet Matériaux ──
  elseif ($activeTab === 'material'):
    $items = $allPresets['material'];
  ?>
  <div class="d-card" style="margin-bottom:1.5rem;">
    <div class="d-card-head">
      <div class="d-card-title">🔩 Matériaux</div>
    </div>
    <div class="d-card-body">
      <?php if (empty($items)): ?>
        <p style="color:#8fa0c4;font-size:.88rem;">Aucun matériau défini.</p>
      <?php else: ?>
        <div style="display:flex;flex-wrap:wrap;gap:.5rem;">
          <?php foreach ($items as $item): ?>
            <form method="post" style="display:inline;">
              <input type="hidden" name="csrf_token"  value="<?= e(csrf_token()) ?>">
              <input type="hidden" name="post_action" value="delete">
              <input type="hidden" name="preset_id"   value="<?= (int)$item['id'] ?>">
              <input type="hidden" name="active_tab"  value="material">
              <span style="display:inline-flex;align-items:center;gap:.35rem;background:rgba(139,92,246,.1);border:1px solid rgba(139,92,246,.25);border-radius:20px;padding:.3rem .75rem;font-size:.83rem;color:#e8ecf5;">
                <?= e($item['label']) ?>
                <button type="submit" onclick="return confirm('Supprimer ce matériau ?')" style="background:none;border:none;color:#8fa0c4;cursor:pointer;padding:0;font-size:.75rem;line-height:1;" title="Supprimer">✕</button>
              </span>
            </form>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Formulaire ajout -->
  <div class="d-card">
    <div class="d-card-head">
      <div class="d-card-title">➕ Ajouter un matériau</div>
    </div>
    <div class="d-card-body">
      <form method="post">
        <input type="hidden" name="csrf_token"  value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="post_action" value="add">
        <input type="hidden" name="type"        value="material">
        <input type="hidden" name="category"    value="">
        <input type="hidden" name="active_tab"  value="material">
        <div class="d-field" style="margin-bottom:1rem;">
          <label>Nom du matériau <span style="color:#ef4444">*</span></label>
          <input type="text" name="label" class="d-input" placeholder="Ex: Câble H07V-K 2.5mm²" required>
        </div>
        <button type="submit" class="d-btn d-btn--primary d-btn--sm">✅ Ajouter</button>
      </form>
    </div>
  </div>

  <?php
  // ── Onglet Types de photos ──
  else:
    $items = $allPresets['photo_type'];
  ?>
  <div class="d-card" style="margin-bottom:1.5rem;">
    <div class="d-card-head">
      <div class="d-card-title">📷 Types de photos</div>
    </div>
    <div class="d-card-body">
      <?php if (empty($items)): ?>
        <p style="color:#8fa0c4;font-size:.88rem;">Aucun type de photo défini.</p>
      <?php else: ?>
        <div style="display:flex;flex-wrap:wrap;gap:.5rem;">
          <?php foreach ($items as $item): ?>
            <form method="post" style="display:inline;">
              <input type="hidden" name="csrf_token"  value="<?= e(csrf_token()) ?>">
              <input type="hidden" name="post_action" value="delete">
              <input type="hidden" name="preset_id"   value="<?= (int)$item['id'] ?>">
              <input type="hidden" name="active_tab"  value="photo_type">
              <span style="display:inline-flex;align-items:center;gap:.35rem;background:rgba(6,182,212,.1);border:1px solid rgba(6,182,212,.25);border-radius:20px;padding:.3rem .75rem;font-size:.83rem;color:#e8ecf5;">
                <?= e($item['label']) ?>
                <button type="submit" onclick="return confirm('Supprimer ce type de photo ?')" style="background:none;border:none;color:#8fa0c4;cursor:pointer;padding:0;font-size:.75rem;line-height:1;" title="Supprimer">✕</button>
              </span>
            </form>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Formulaire ajout -->
  <div class="d-card">
    <div class="d-card-head">
      <div class="d-card-title">➕ Ajouter un type de photo</div>
    </div>
    <div class="d-card-body">
      <form method="post">
        <input type="hidden" name="csrf_token"  value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="post_action" value="add">
        <input type="hidden" name="type"        value="photo_type">
        <input type="hidden" name="category"    value="">
        <input type="hidden" name="active_tab"  value="photo_type">
        <div class="d-field" style="margin-bottom:1rem;">
          <label>Libellé <span style="color:#ef4444">*</span></label>
          <input type="text" name="label" class="d-input" placeholder="Ex: Photo avant intervention" required>
        </div>
        <button type="submit" class="d-btn d-btn--primary d-btn--sm">✅ Ajouter</button>
      </form>
    </div>
  </div>

  <?php endif; ?>

</div><!-- /.d-content -->

<?php require __DIR__.'/partials/footer.php'; ?>
