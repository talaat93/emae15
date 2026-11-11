<?php
$adminSection = 'realisations';
require __DIR__ . '/partials/header.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    // Add new
    if (isset($_POST['action']) && $_POST['action'] === 'add') {
        $title       = trim((string)($_POST['title']   ?? ''));
        $desc        = trim((string)($_POST['description'] ?? ''));
        $service     = trim((string)($_POST['service_type'] ?? ''));
        $city        = trim((string)($_POST['city']    ?? ''));
        $zid         = (int)($_POST['zone_id'] ?? 0) ?: null;
        $doneAt      = date_input_value($_POST['done_at'] ?? '');
        if ($title !== '') {
            $image = upload_image_field('image', 'realisations');
            db_execute('INSERT INTO realisations (title, description, service_type, city, image_path, is_visible, sort_order, zone_id, done_at) VALUES (?,?,?,?,?,1,?,?,?)',
                [$title, $desc, $service, $city, $image ?? '', (int)time(), $zid, $doneAt]);
            $newId  = db_last_id();
            $before = upload_image_field('before_image', 'realisations');
            if ($before !== null) db_execute('UPDATE realisations SET before_image_path = ? WHERE id = ?', [$before, $newId]);
            if (($image === null && !empty($_FILES['image']['name'])) || ($before === null && !empty($_FILES['before_image']['name']))) {
                flash('error', 'Réalisation ajoutée, mais une photo a été refusée (format ou taille). Utilisez « Modifier » pour la renvoyer.');
            } else {
                flash('success', 'Réalisation ajoutée.');
            }
        } else {
            flash('error', 'Le titre est obligatoire.');
        }
        redirect_to('admin/realisations.php');
    }

    // Modification d'une réalisation existante (la photo actuelle reste si aucune n'est envoyée)
    if (isset($_POST['action']) && $_POST['action'] === 'edit') {
        $id    = (int)($_POST['id'] ?? 0);
        $title = trim((string)($_POST['title'] ?? ''));
        if ($title === '') {
            flash('error', 'Le titre est obligatoire.');
            redirect_to('admin/realisations.php?edit='.$id);
        }
        db_execute('UPDATE realisations SET title = ?, description = ?, service_type = ?, city = ?, zone_id = ?, done_at = ? WHERE id = ?', [
            $title,
            trim((string)($_POST['description'] ?? '')),
            trim((string)($_POST['service_type'] ?? '')),
            trim((string)($_POST['city'] ?? '')),
            ((int)($_POST['zone_id'] ?? 0)) ?: null,
            date_input_value($_POST['done_at'] ?? ''),
            $id,
        ]);
        $image = upload_image_field('image', 'realisations');
        if ($image !== null) {
            db_execute('UPDATE realisations SET image_path = ? WHERE id = ?', [$image, $id]);
        }
        $before = upload_image_field('before_image', 'realisations');
        if ($before !== null) {
            db_execute('UPDATE realisations SET before_image_path = ? WHERE id = ?', [$before, $id]);
        } elseif (!empty($_POST['remove_before_image'])) {
            db_execute('UPDATE realisations SET before_image_path = NULL WHERE id = ?', [$id]);
        }
        if (($image === null && !empty($_FILES['image']['name'])) || ($before === null && !empty($_FILES['before_image']['name']))) {
            flash('error', 'Textes enregistrés, mais une photo a été refusée (format ou taille). L\'ancienne est conservée.');
        } else {
            flash('success', 'Réalisation modifiée.');
        }
        redirect_to('admin/realisations.php');
    }

    // Réaffectation de zone
    if (isset($_POST['action']) && $_POST['action'] === 'setzone') {
        db_execute('UPDATE realisations SET zone_id = ? WHERE id = ?',
            [((int)($_POST['zone_id'] ?? 0)) ?: null, (int)($_POST['id'] ?? 0)]);
        flash('success', 'Zone de la réalisation mise à jour.');
        redirect_to('admin/realisations.php');
    }

    // Changement du type de service, qui décide de la page métier d'affichage
    if (isset($_POST['action']) && $_POST['action'] === 'setservice') {
        db_execute('UPDATE realisations SET service_type = ? WHERE id = ?',
            [trim((string)($_POST['service_type'] ?? '')), (int)($_POST['id'] ?? 0)]);
        flash('success', 'Type de service mis à jour.');
        redirect_to('admin/realisations.php');
    }

    // Toggle visibility
    if (isset($_GET['toggle'])) {
        $id = (int)$_GET['toggle'];
        $row = db_fetch('SELECT is_visible FROM realisations WHERE id = ?', [$id]);
        if ($row) db_execute('UPDATE realisations SET is_visible = ? WHERE id = ?', [(int)!$row['is_visible'], $id]);
        redirect_to('admin/realisations.php');
    }

    // Delete
    if (isset($_GET['delete'])) {
        $id = (int)$_GET['delete'];
        db_execute('DELETE FROM realisations WHERE id = ?', [$id]);
        flash('success', 'Réalisation supprimée.');
        redirect_to('admin/realisations.php');
    }
}

if (isset($_GET['toggle'])) {
    $id = (int)$_GET['toggle'];
    $row = db_fetch('SELECT is_visible FROM realisations WHERE id = ?', [$id]);
    if ($row) db_execute('UPDATE realisations SET is_visible = ? WHERE id = ?', [(int)!$row['is_visible'], $id]);
    redirect_to('admin/realisations.php');
}

if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    db_execute('DELETE FROM realisations WHERE id = ?', [$id]);
    flash('success', 'Réalisation supprimée.');
    redirect_to('admin/realisations.php');
}

$reals = all_realisations();
$serviceOptions = service_type_options();
// Le même formulaire sert à ajouter et, avec ?edit=ID, à modifier.
$edit = isset($_GET['edit']) ? db_fetch('SELECT * FROM realisations WHERE id = ?', [(int)$_GET['edit']]) : null;
$editService = (string)($edit['service_type'] ?? '');
?>

<div class="admin-page-toolbar">
  <div>
    <div class="admin-breadcrumb">Contenus</div>
    <h1 class="admin-page-title">Réalisations & chantiers</h1>
    <p class="admin-page-subtitle">Ajoutez vos photos de chantiers pour rassurer vos clients et améliorer votre crédibilité Google Ads.</p>
  </div>
  <div class="admin-toolbar-actions">
    <a class="admin-btn admin-btn--secondary" href="<?= e(route_url('realisations')) ?>" target="_blank">Voir sur le site</a>
  </div>
</div>

<div class="admin-stack">
  <!-- Add form -->
  <section class="admin-panel">
    <div class="admin-panel__head"><h2><?= $edit ? 'Modifier : '.e($edit['title']) : 'Ajouter une réalisation' ?></h2><p>Photo + titre + service + ville = maximum de confiance client. Avec une photo avant et une photo après, le site les affiche côte à côte.</p></div>
    <div class="admin-panel__body">
      <form method="post" enctype="multipart/form-data" action="<?= e(url_for('admin/realisations.php')) ?>">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="<?= $edit ? 'edit' : 'add' ?>">
        <?php if ($edit): ?><input type="hidden" name="id" value="<?= (int)$edit['id'] ?>"><?php endif; ?>
        <div class="admin-form-grid admin-form-grid--2">
          <label class="admin-field"><span>Titre *</span><input type="text" name="title" required value="<?= e((string)($edit['title'] ?? '')) ?>" placeholder="Ex : Remplacement tableau électrique Paris 15e"></label>
          <label class="admin-field"><span>Ville</span><input type="text" name="city" value="<?= e((string)($edit['city'] ?? '')) ?>" placeholder="Ex : Dole, Besançon, Beaune"></label>
          <label class="admin-field"><span>Date du chantier (affichée sur le site)</span><input type="date" name="done_at" max="<?= e(date('Y-m-d')) ?>" value="<?= e((string)($edit['done_at'] ?? '')) ?>"></label>
        </div>
        <div class="admin-form-grid admin-form-grid--2">
          <label class="admin-field"><span>Type de service</span>
            <select name="service_type">
              <option value="">Choisir</option>
              <?php foreach (array_unique(array_merge($serviceOptions, $editService !== '' ? [$editService] : [])) as $s): ?><option value="<?= e($s) ?>" <?= $s === $editService ? 'selected' : '' ?>><?= e($s) ?></option><?php endforeach; ?>
            </select>
            <small style="color:#7b8aa8;">Affiche aussi le chantier sur la page du métier. « Maintenance » et « Dépannage » ne sont rattachés à aucun métier.</small>
          </label>
        </div>
        <div class="admin-form-grid admin-form-grid--2">
          <div class="admin-field"><label for="before_image"><span><?= $edit ? 'Photo AVANT : ajouter ou remplacer (facultatif)' : 'Photo AVANT (facultatif)' ?></span></label><input id="before_image" type="file" name="before_image" accept=".jpg,.jpeg,.png,.webp">
            <?php if ($edit && trim((string)($edit['before_image_path'] ?? '')) !== ''): ?><img src="<?= e(asset_url($edit['before_image_path'])) ?>" alt="Photo avant actuelle" style="width:120px;height:84px;object-fit:cover;border-radius:8px;margin-top:.4rem;">
            <label style="display:flex;gap:.4rem;align-items:center;font-size:.85rem;margin-top:.3rem;"><input type="checkbox" name="remove_before_image" value="1" style="width:auto;min-height:0;"> Retirer la photo avant</label><?php endif; ?>
          </div>
          <div class="admin-field"><label for="image"><span><?= $edit ? 'Photo APRÈS : remplacer (facultatif)' : 'Photo APRÈS, ou photo unique (jpg, png, webp)' ?></span></label><input id="image" type="file" name="image" accept=".jpg,.jpeg,.png,.webp">
            <?php if ($edit && trim((string)($edit['image_path'] ?? '')) !== ''): ?><img src="<?= e(asset_url($edit['image_path'])) ?>" alt="Photo après actuelle" style="width:120px;height:84px;object-fit:cover;border-radius:8px;margin-top:.4rem;"><?php endif; ?>
          </div>
        </div>
        <label class="admin-field"><span>Zone d'affichage</span>
          <?= zone_select_field('zone_id', $edit ? (int)($edit['zone_id'] ?? 0) : zone_ctx_id()) ?>
          <small style="color:#7b8aa8;">« Toutes les zones » affiche ce chantier sur l'ensemble du site.</small>
        </label>
        <label class="admin-field"><span>Description courte</span><textarea name="description" rows="3" placeholder="Décrivez rapidement l'intervention réalisée..."><?= e((string)($edit['description'] ?? '')) ?></textarea></label>
        <div class="admin-savebar">
          <?php if ($edit): ?><a class="admin-btn admin-btn--secondary" href="<?= e(url_for('admin/realisations.php')) ?>">Annuler</a><?php endif; ?>
          <button class="admin-btn admin-btn--primary" type="submit"><?= $edit ? 'Enregistrer' : 'Ajouter' ?></button>
        </div>
      </form>
    </div>
  </section>

  <!-- List -->
  <section class="admin-panel">
    <div class="admin-panel__head"><h2>Liste des réalisations (<?= e((string)count($reals)) ?>)</h2></div>
    <div class="admin-panel__body admin-table-wrap">
      <?php if (empty($reals)): ?>
        <p style="color:#7b8aa8;padding:1rem 0;">Aucune réalisation pour le moment. Ajoutez vos premières photos de chantiers !</p>
      <?php else: ?>
        <table class="admin-table">
          <thead><tr><th>Photo</th><th>Titre</th><th>Service</th><th>Ville</th><th>Date</th><th>Zone</th><th>Visible</th><th>Actions</th></tr></thead>
          <tbody>
            <?php foreach ($reals as $r): ?>
              <tr>
                <td>
                  <?php if (trim((string)($r['before_image_path']??'')) !== ''): ?>
                    <img src="<?= e(asset_url($r['before_image_path'])) ?>" alt="Avant" title="Avant" width="80" height="56" style="width:80px;height:56px;object-fit:cover;border-radius:8px;display:block;margin-bottom:.25rem;" loading="lazy">
                  <?php endif; ?>
                  <?php if (trim((string)($r['image_path']??'')) !== ''): ?>
                    <img src="<?= e(asset_url($r['image_path'])) ?>" alt="" width="80" height="56" style="width:80px;height:56px;object-fit:cover;border-radius:8px;" loading="lazy">
                  <?php else: ?>
                    <div style="width:80px;height:56px;background:#eef2fb;border-radius:8px;display:flex;align-items:center;justify-content:center;color:#7b8aa8;font-size:1.4rem;">📷</div>
                  <?php endif; ?>
                </td>
                <td style="font-weight:700;"><?= e($r['title']) ?></td>
                <td>
                  <?php $curService = (string)($r['service_type'] ?? ''); ?>
                  <form method="post" style="margin:0;">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="setservice">
                    <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                    <select name="service_type" onchange="this.form.submit()" style="font-size:.8rem;padding:.25rem;">
                      <option value="">Choisir</option>
                      <?php foreach (array_unique(array_merge($serviceOptions, $curService !== '' ? [$curService] : [])) as $s): ?>
                        <option value="<?= e($s) ?>" <?= $s === $curService ? 'selected' : '' ?>><?= e($s) ?></option>
                      <?php endforeach; ?>
                    </select>
                  </form>
                  <?php if (realisation_trades($curService) === []): ?><small style="color:#b45309;display:block;margin-top:.2rem;">Sur aucune page métier</small><?php endif; ?>
                </td>
                <td><?= e((string)($r['city']??'')) ?></td>
                <td style="font-size:.82rem;white-space:nowrap;"><?= date_fr($r['done_at'] ?? '') !== '' ? e(date_fr($r['done_at'])) : '<span style="color:#b45309;">à saisir</span>' ?></td>
                <td>
                  <form method="post" style="margin:0;">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="setzone">
                    <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                    <?= zone_select_field('zone_id', (int)($r['zone_id'] ?? 0), 'onchange="this.form.submit()" style="font-size:.8rem;padding:.25rem;"') ?>
                  </form>
                </td>
                <td><?= (int)$r['is_visible'] === 1 ? '<span style="color:#16a34a;font-weight:700;">✓ Oui</span>' : '<span style="color:#dc2626;">Non</span>' ?></td>
                <td>
                  <div style="display:flex;gap:.5rem;flex-wrap:wrap;">
                    <a class="admin-btn admin-btn--primary" href="<?= e(url_for('admin/realisations.php?edit='.(int)$r['id'])) ?>" style="min-height:36px;padding:0 .75rem;font-size:.82rem;">Modifier</a>
                    <a class="admin-btn admin-btn--secondary" href="<?= e(url_for('admin/realisations.php?toggle='.(int)$r['id'])) ?>" style="min-height:36px;padding:0 .75rem;font-size:.82rem;">
                      <?= (int)$r['is_visible'] === 1 ? 'Masquer' : 'Afficher' ?>
                    </a>
                    <a class="admin-btn" href="<?= e(url_for('admin/realisations.php?delete='.(int)$r['id'])) ?>"
                       onclick="return confirm('Supprimer cette réalisation ?')"
                       style="min-height:36px;padding:0 .75rem;font-size:.82rem;background:#fff0f0;color:#dc2626;border:1px solid #fecaca;">
                      Supprimer
                    </a>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>
  </section>
</div>

<?php require __DIR__ . '/partials/footer.php'; ?>
