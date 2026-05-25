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
        if ($title !== '') {
            $image = upload_image_field('image', 'realisations');
            db_execute('INSERT INTO realisations (title, description, service_type, city, image_path, is_visible, sort_order) VALUES (?,?,?,?,?,1,?)',
                [$title, $desc, $service, $city, $image ?? '', (int)time()]);
            flash('success', 'Réalisation ajoutée.');
        } else {
            flash('error', 'Le titre est obligatoire.');
        }
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
$serviceOptions = ['Électricité','Plomberie','Chauffage & Climatisation','Maintenance','CVC','PAC','Dépannage'];
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
    <div class="admin-panel__head"><h2>Ajouter une réalisation</h2><p>Photo + titre + service + ville = maximum de confiance client.</p></div>
    <div class="admin-panel__body">
      <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="add">
        <div class="admin-form-grid admin-form-grid--2">
          <label class="admin-field"><span>Titre *</span><input type="text" name="title" required placeholder="Ex : Remplacement tableau électrique Paris 15e"></label>
          <label class="admin-field"><span>Ville</span><input type="text" name="city" placeholder="Ex : Meaux, Paris, Toulouse"></label>
        </div>
        <div class="admin-form-grid admin-form-grid--2">
          <label class="admin-field"><span>Type de service</span>
            <select name="service_type">
              <option value="">Choisir</option>
              <?php foreach ($serviceOptions as $s): ?><option value="<?= e($s) ?>"><?= e($s) ?></option><?php endforeach; ?>
            </select>
          </label>
          <label class="admin-field"><span>Photo (jpg, png, webp)</span><input type="file" name="image" accept=".jpg,.jpeg,.png,.webp"></label>
        </div>
        <label class="admin-field"><span>Description courte</span><textarea name="description" rows="3" placeholder="Décrivez rapidement l'intervention réalisée..."></textarea></label>
        <div class="admin-savebar"><button class="admin-btn admin-btn--primary" type="submit">Ajouter</button></div>
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
          <thead><tr><th>Photo</th><th>Titre</th><th>Service</th><th>Ville</th><th>Visible</th><th>Actions</th></tr></thead>
          <tbody>
            <?php foreach ($reals as $r): ?>
              <tr>
                <td>
                  <?php if (trim((string)($r['image_path']??'')) !== ''): ?>
                    <img src="<?= e(asset_url($r['image_path'])) ?>" alt="" style="width:80px;height:56px;object-fit:cover;border-radius:8px;">
                  <?php else: ?>
                    <div style="width:80px;height:56px;background:#eef2fb;border-radius:8px;display:flex;align-items:center;justify-content:center;color:#7b8aa8;font-size:1.4rem;">📷</div>
                  <?php endif; ?>
                </td>
                <td style="font-weight:700;"><?= e($r['title']) ?></td>
                <td><?= e((string)($r['service_type']??'')) ?></td>
                <td><?= e((string)($r['city']??'')) ?></td>
                <td><?= (int)$r['is_visible'] === 1 ? '<span style="color:#16a34a;font-weight:700;">✓ Oui</span>' : '<span style="color:#dc2626;">Non</span>' ?></td>
                <td>
                  <div style="display:flex;gap:.5rem;flex-wrap:wrap;">
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
