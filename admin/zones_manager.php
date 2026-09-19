<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/render.php';
require_once __DIR__ . '/../includes/admin_fields.php';
require_once __DIR__ . '/../includes/zone_content.php';
require_admin();

// POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'toggle') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) toggle_zone_status($id);
        flash('success', 'Statut de la zone mis à jour.');
        redirect_to('admin/zones_manager.php');
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) delete_zone($id);
        flash('success', 'Zone supprimée.');
        redirect_to('admin/zones_manager.php');
    }

    if ($action === 'reorder') {
        $ids = array_map('intval', explode(',', (string)($_POST['order'] ?? '')));
        foreach ($ids as $i => $zid) {
            if ($zid > 0) db_execute("UPDATE zones SET sort_order=? WHERE id=?", [$i, $zid]);
        }
        echo json_encode(['ok' => true]);
        exit;
    }

    redirect_to('admin/zones_manager.php');
}

$zones = all_zones();
$adminSection = 'zones_manager';
require_once __DIR__ . '/partials/header.php';
?>
<div class="admin-page-header">
  <div>
    <h1 class="admin-page-title">Zones géographiques</h1>
    <p class="admin-page-sub">Gérez vos zones d'intervention. Chaque zone active obtient sa propre URL (ex: <code>/paris-ile-de-france/</code>).</p>
  </div>
  <a class="btn btn-p" href="<?= e(url_for('admin/zone_edit.php')) ?>">+ Nouvelle zone</a>
</div>

<?php if (empty($zones)): ?>
<div class="admin-empty">
  <div style="font-size:3rem;">🗺️</div>
  <p>Aucune zone configurée.</p>
  <a class="btn btn-p" href="<?= e(url_for('admin/zone_edit.php')) ?>">Créer la première zone</a>
</div>
<?php else: ?>

<div class="admin-card" style="padding:0;overflow:hidden;">
  <table class="admin-table" style="margin:0;">
    <thead>
      <tr>
        <th style="width:40px;"></th>
        <th>Zone</th>
        <th>Slug / URL</th>
        <th>Villes</th>
        <th style="width:120px;text-align:center;">Personnalisé</th>
        <th style="width:100px;text-align:center;">Statut</th>
        <th style="width:240px;text-align:right;">Actions</th>
      </tr>
    </thead>
    <tbody id="zones-tbody">
      <?php foreach ($zones as $z): ?>
      <tr data-id="<?= $z['id'] ?>" style="<?= !(bool)$z['status'] ? 'opacity:.55;' : '' ?>">
        <td style="cursor:grab;text-align:center;color:var(--t3);">⠿</td>
        <td>
          <strong><?= e($z['name']) ?></strong>
          <?php if (trim((string)($z['meta_title']??'')) !== ''): ?>
            <br><small style="color:var(--t2);font-size:.75rem;"><?= e(mb_substr($z['meta_title'],0,60)) ?></small>
          <?php endif; ?>
        </td>
        <td>
          <code style="font-size:.8rem;background:var(--bg2);padding:.15rem .4rem;border-radius:4px;">/<?= e($z['slug']) ?>/</code>
          <a href="<?= e(url_for($z['slug'].'/')) ?>" target="_blank" style="margin-left:.4rem;font-size:.75rem;color:var(--accent);">↗</a>
        </td>
        <td style="font-size:.8rem;color:var(--t2);"><?php
          $zcities = zone_cities($z);
          echo e(implode(', ', array_slice($zcities, 0, 4)));
          if (count($zcities) > 4) echo ' <em>+'.(count($zcities)-4).' autres</em>';
        ?></td>
        <td style="text-align:center;"><?php $zov = zone_override_count((string)$z['slug']); ?>
          <?php if ($zov > 0): ?>
            <span style="background:#dcfce7;color:#166534;border-radius:20px;padding:.2rem .6rem;font-size:.75rem;font-weight:700;"><?= $zov ?> champ<?= $zov > 1 ? 's' : '' ?></span>
          <?php else: ?>
            <span style="color:var(--t3);font-size:.78rem;">hérite du global</span>
          <?php endif; ?>
        </td>
        <td style="text-align:center;">
          <form method="post" style="display:inline;">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="toggle">
            <input type="hidden" name="id" value="<?= $z['id'] ?>">
            <button type="submit" class="zone-toggle <?= (bool)$z['status'] ? 'zone-toggle--on' : 'zone-toggle--off' ?>" title="Cliquer pour <?= (bool)$z['status'] ? 'désactiver' : 'activer' ?>">
              <?= (bool)$z['status'] ? '✅ Actif' : '⏸ Inactif' ?>
            </button>
          </form>
        </td>
        <td style="text-align:right;">
          <?php if (zone_content_pack((string)$z['slug'])): ?>
            <a class="btn btn-sm btn-outline" href="<?= e(url_for('admin/zone_content.php?admin_zone='.$z['id'])) ?>" title="Appliquer des textes déjà rédigés pour cette zone">✨ Contenu prêt</a>
          <?php endif; ?>
          <a class="btn btn-sm btn-p" href="<?= e(url_for('admin/index.php?admin_zone='.$z['id'])) ?>" title="Basculer l'admin sur cette zone pour modifier tout son site">🎛 Éditer le site</a>
          <a class="btn btn-sm btn-outline" href="<?= e(url_for('admin/zone_edit.php?id='.$z['id'])) ?>">✏️ Réglages</a>
          <form method="post" style="display:inline;" onsubmit="return confirm('Supprimer la zone <?= e(addslashes($z['name'])) ?> ?');">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="<?= $z['id'] ?>">
            <button type="submit" class="btn btn-sm" style="background:#dc2626;color:#fff;border:none;">🗑</button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<div class="admin-card" style="margin-top:1.5rem;padding:1rem 1.25rem;">
  <strong>💡 Comment ça marche ?</strong>
  <ul style="margin:.5rem 0 0 1.2rem;font-size:.875rem;color:var(--t2);line-height:1.8;">
    <li>Chaque zone active est accessible via <code>votre-site.fr/<em>slug-zone</em>/</code></li>
    <li><strong>🎛 Éditer le site</strong> bascule tout l'admin sur cette zone : identité, couleurs, accueil, services, pages, réalisations… Le bandeau orange en haut rappelle en permanence la zone en cours.</li>
    <li><strong>✏️ Réglages</strong> ne modifie que la fiche de la zone : slug, villes, mentions légales.</li>
    <li>Un champ laissé vide dans une zone <strong>hérite automatiquement du site global</strong> : vous ne saisissez que les différences.</li>
    <li>Les zones inactives ne sont pas accessibles aux visiteurs.</li>
  </ul>
</div>
<?php endif; ?>

<style>
.zone-toggle { cursor:pointer;border:none;padding:.3rem .8rem;border-radius:20px;font-size:.8rem;font-weight:600; }
.zone-toggle--on { background:#dcfce7;color:#166534; }
.zone-toggle--off { background:#f3f4f6;color:#6b7280; }
.admin-empty { text-align:center;padding:4rem 0;color:var(--t2); }
</style>

<?php require_once __DIR__ . '/partials/footer.php'; ?>
