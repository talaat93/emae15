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
if (!in_array($activeTab, array_merge(['intervention_type','material','photo_type'], array_keys(ref_types())), true)) {
    $activeTab = 'intervention_type';
}

$allPresets = all_presets_grouped();
$catsCfg    = intervention_category_config();
?>

<!-- TOPBAR -->
<div class="d-topbar">
  <div style="display:flex;align-items:center;gap:.75rem;">
    <button class="d-menu-toggle" id="d-menu-toggle" aria-label="Menu">☰</button>
    <div class="d-topbar-title">Gestion des presets</div>
  </div>
</div>

<div class="d-content">

  <!-- Onglets -->
  <div style="display:flex;gap:.5rem;margin-bottom:1.5rem;border-bottom:1px solid var(--d-border);padding-bottom:.75rem;flex-wrap:wrap;">
    <?php
    $tabs = [
        'intervention_type' => ['label'=>'Types d\'interventions', 'icon'=>''],
        'material'          => ['label'=>'Matériaux',              'icon'=>''],
        'photo_type'        => ['label'=>'Types de photos',        'icon'=>''],
    ];
    foreach (ref_types() as $rk => $rl) $tabs[$rk] = ['label' => $rl, 'icon' => ''];
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
      <div class="d-card-title">Types d'interventions</div>
    </div>
    <div class="d-card-body">
      <?php if (empty($items)): ?>
        <p style="color:var(--d-t2);font-size:.88rem;">Aucun preset défini.</p>
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
                  <span style="display:inline-flex;align-items:center;gap:.35rem;background:rgba(240,123,29,.1);border:1px solid rgba(240,123,29,.25);border-radius:20px;padding:.3rem .75rem;font-size:.83rem;color:var(--d-t1);">
                    <?= e($item['label']) ?>
                    <button type="submit" onclick="return confirm('Supprimer ce preset ?')" style="background:none;border:none;color:var(--d-t2);cursor:pointer;padding:0;font-size:.75rem;line-height:1;" title="Supprimer">×</button>
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
      <div class="d-card-title">Ajouter un type d'intervention</div>
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
        <button type="submit" class="d-btn d-btn--primary d-btn--sm">Ajouter</button>
      </form>
    </div>
  </div>

  <?php
  // ── Onglet Matériaux ──
  elseif ($activeTab === 'material'):
    $items = $allPresets['material'];
    $grouped = [];
    foreach ($items as $item) $grouped[(string)($item['category'] ?? '')][] = $item;
    uksort($grouped, static fn($x, $y) => ($x === '') <=> ($y === '') ?: strcmp($x, $y));
  ?>
  <div class="d-card" style="margin-bottom:1.5rem;">
    <div class="d-card-head">
      <div class="d-card-title">Matériel</div>
      <span style="font-size:.8rem;color:var(--d-t2);"><?= count($items) ?> articles</span>
    </div>
    <div class="d-card-body">
      <?php if (empty($items)): ?>
        <p style="color:var(--d-t2);font-size:.88rem;">Aucun matériel défini.</p>
      <?php else: ?>
        <?php foreach ($grouped as $cat => $catItems): ?>
          <div style="margin-bottom:1.25rem;">
            <div style="font-size:.75rem;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:var(--d-t2);margin-bottom:.5rem;"><?= e($cat !== '' ? ($catsCfg[$cat]['label'] ?? ucfirst($cat)) : 'Divers') ?> · <?= count($catItems) ?></div>
            <div style="display:flex;flex-wrap:wrap;gap:.4rem;">
              <?php foreach ($catItems as $item): ?>
                <form method="post" style="display:inline;">
                  <input type="hidden" name="csrf_token"  value="<?= e(csrf_token()) ?>">
                  <input type="hidden" name="post_action" value="delete">
                  <input type="hidden" name="preset_id"   value="<?= (int)$item['id'] ?>">
                  <input type="hidden" name="active_tab"  value="material">
                  <span style="display:inline-flex;align-items:center;gap:.35rem;background:var(--d-card-2);border:1px solid var(--d-border);border-radius:20px;padding:.25rem .7rem;font-size:.83rem;color:var(--d-t1);">
                    <?= e($item['label']) ?>
                    <button type="submit" onclick="return confirm('Supprimer ce matériel ?')" style="background:none;border:none;color:var(--d-t2);cursor:pointer;padding:0;font-size:.85rem;line-height:1;" title="Supprimer">×</button>
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
      <div class="d-card-title">Ajouter un matériau</div>
    </div>
    <div class="d-card-body">
      <form method="post">
        <input type="hidden" name="csrf_token"  value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="post_action" value="add">
        <input type="hidden" name="type"        value="material">
        <input type="hidden" name="active_tab"  value="material">
        <div class="d-field">
          <label>Métier</label>
          <select name="category" class="d-select">
            <option value="">Divers (tous métiers)</option>
            <?php foreach ($catsCfg as $ck => $cv): ?><option value="<?= e($ck) ?>"><?= e($cv['label']) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="d-field" style="margin-bottom:1rem;">
          <label>Nom du matériau <span style="color:#ef4444">*</span></label>
          <input type="text" name="label" class="d-input" placeholder="Ex: Câble H07V-K 2.5mm²" required>
        </div>
        <button type="submit" class="d-btn d-btn--primary d-btn--sm">Ajouter</button>
      </form>
    </div>
  </div>

  <?php
  // ── Onglet Types de photos ──
  elseif ($activeTab === 'photo_type'):
    $items = $allPresets['photo_type'];
  ?>
  <div class="d-card" style="margin-bottom:1.5rem;">
    <div class="d-card-head">
      <div class="d-card-title">Types de photos</div>
    </div>
    <div class="d-card-body">
      <?php if (empty($items)): ?>
        <p style="color:var(--d-t2);font-size:.88rem;">Aucun type de photo défini.</p>
      <?php else: ?>
        <div style="display:flex;flex-wrap:wrap;gap:.5rem;">
          <?php foreach ($items as $item): ?>
            <form method="post" style="display:inline;">
              <input type="hidden" name="csrf_token"  value="<?= e(csrf_token()) ?>">
              <input type="hidden" name="post_action" value="delete">
              <input type="hidden" name="preset_id"   value="<?= (int)$item['id'] ?>">
              <input type="hidden" name="active_tab"  value="photo_type">
              <span style="display:inline-flex;align-items:center;gap:.35rem;background:rgba(6,182,212,.1);border:1px solid rgba(6,182,212,.25);border-radius:20px;padding:.3rem .75rem;font-size:.83rem;color:var(--d-t1);">
                <?= e($item['label']) ?>
                <button type="submit" onclick="return confirm('Supprimer ce type de photo ?')" style="background:none;border:none;color:var(--d-t2);cursor:pointer;padding:0;font-size:.75rem;line-height:1;" title="Supprimer">×</button>
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
      <div class="d-card-title">Ajouter un type de photo</div>
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
        <button type="submit" class="d-btn d-btn--primary d-btn--sm">Ajouter</button>
      </form>
    </div>
  </div>

  <?php
  // ── Référentiel : appareils, marques, modèles, pannes, phrases ──
  elseif (isset(ref_types()[$activeTab])):
    $rt = $activeTab;
    $ref = ref_load();
    $byMetier = in_array($rt, ['equipement', 'panne', 'phrase_diag', 'phrase_travaux'], true);
    try { $items = db_fetch_all("SELECT * FROM preset_items WHERE type = ? AND active = 1 ORDER BY category, sort_order, id", [$rt]); }
    catch (Throwable $e) { $items = []; }
    $grouped = [];
    foreach ($items as $item) $grouped[(string)($item['category'] ?? '')][] = $item;
    $groupLabel = static function (string $c) use ($byMetier, $catsCfg): string {
        if ($byMetier) return $c !== '' ? ($catsCfg[$c]['label'] ?? ucfirst($c)) : 'Tous métiers';
        return str_replace('|', ' — ', $c);
    };
    $help = [
        'equipement'     => 'Proposés au dispatcher selon le métier choisi. Ils remplissent le « Type d\'installation ».',
        'marque'         => 'Proposées selon l\'appareil choisi.',
        'modele'         => 'Proposés selon l\'appareil et la marque choisis ; le dispatcher peut aussi écrire un autre modèle.',
        'panne'          => 'Un clic du dispatcher ajoute la panne à la fiche.',
        'phrase_diag'    => 'Un appui du technicien ajoute la phrase à son diagnostic ; il peut toujours écrire la sienne.',
        'phrase_travaux' => 'Un appui du technicien ajoute la phrase à ses travaux réalisés.',
    ][$rt];
    // Suggestions de catégorie pour l'ajout.
    $catSuggest = [];
    if ($rt === 'marque') foreach ($ref['equipement'] as $l) $catSuggest = array_merge($catSuggest, $l);
    if ($rt === 'modele') foreach ($ref['marque'] as $eqp => $brands) foreach ($brands as $b) $catSuggest[] = $eqp.'|'.$b;
    $catSuggest = array_values(array_unique($catSuggest));
  ?>
  <div class="d-card" style="margin-bottom:1.5rem;">
    <div class="d-card-head">
      <div class="d-card-title"><?= e(ref_types()[$rt]) ?></div>
      <span style="font-size:.8rem;color:var(--d-t2);"><?= count($items) ?> éléments</span>
    </div>
    <div class="d-card-body">
      <p style="font-size:.84rem;color:var(--d-t2);margin:0 0 1rem;"><?= e($help) ?></p>
      <?php if (!$items): ?><p style="color:var(--d-t2);font-size:.88rem;">Aucun élément.</p><?php endif; ?>
      <?php foreach ($grouped as $cat => $catItems): ?>
        <div style="margin-bottom:1.1rem;">
          <div style="font-size:.75rem;font-weight:700;letter-spacing:.05em;text-transform:uppercase;color:#F07B1D;margin-bottom:.45rem;"><?= e($groupLabel($cat)) ?> · <?= count($catItems) ?></div>
          <div style="display:flex;flex-wrap:wrap;gap:.4rem;">
            <?php foreach ($catItems as $item): ?>
              <form method="post" style="display:inline;">
                <input type="hidden" name="csrf_token"  value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="post_action" value="delete">
                <input type="hidden" name="preset_id"   value="<?= (int)$item['id'] ?>">
                <input type="hidden" name="active_tab"  value="<?= e($rt) ?>">
                <span style="display:inline-flex;align-items:center;gap:.35rem;background:var(--d-card-2);border:1px solid var(--d-border);border-radius:20px;padding:.25rem .7rem;font-size:.83rem;color:var(--d-t1);">
                  <?= e($item['label']) ?>
                  <button type="submit" onclick="return confirm('Supprimer « <?= e(addslashes((string)$item['label'])) ?> » ?')" style="background:none;border:none;color:var(--d-t2);cursor:pointer;padding:0;font-size:.85rem;line-height:1;" title="Supprimer">×</button>
                </span>
              </form>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="d-card">
    <div class="d-card-head"><div class="d-card-title">Ajouter</div></div>
    <div class="d-card-body">
      <form method="post">
        <input type="hidden" name="csrf_token"  value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="post_action" value="add">
        <input type="hidden" name="type"        value="<?= e($rt) ?>">
        <input type="hidden" name="active_tab"  value="<?= e($rt) ?>">
        <div class="d-grid-2" style="margin-bottom:1rem;">
          <div class="d-field">
            <?php if ($byMetier): ?>
              <label>Métier</label>
              <select name="category" class="d-input">
                <option value="">— Tous métiers —</option>
                <?php foreach ($catsCfg as $ck => $cv): ?><option value="<?= e($ck) ?>"><?= e($cv['label']) ?></option><?php endforeach; ?>
              </select>
            <?php else: ?>
              <label><?= $rt === 'marque' ? 'Appareil' : 'Appareil|Marque' ?> <span style="color:#ef4444">*</span></label>
              <input type="text" name="category" class="d-input" list="ref-cats" required maxlength="60"
                     placeholder="<?= $rt === 'marque' ? 'Ex. : Chaudière gaz' : 'Ex. : Chaudière gaz|Saunier Duval' ?>">
              <datalist id="ref-cats"><?php foreach ($catSuggest as $cs): ?><option value="<?= e($cs) ?>"><?php endforeach; ?></datalist>
            <?php endif; ?>
          </div>
          <div class="d-field">
            <label>Libellé <span style="color:#ef4444">*</span></label>
            <input type="text" name="label" class="d-input" required maxlength="255">
          </div>
        </div>
        <button type="submit" class="d-btn d-btn--primary d-btn--sm">Ajouter</button>
      </form>
    </div>
  </div>

  <?php endif; ?>

</div><!-- /.d-content -->

<?php require __DIR__.'/partials/footer.php'; ?>
