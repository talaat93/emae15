<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/render.php';
require_once __DIR__ . '/../includes/admin_fields.php';
require_once __DIR__ . '/../includes/zone_content.php';
require_admin();

$zone = zone_context();
if (!$zone) {
    flash('error', 'Choisissez d\'abord une zone dans le bandeau en haut.');
    redirect_to('admin/zones.php');
}
$slug = (string)$zone['slug'];
$pack = zone_content_pack($slug);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $pack) {
    verify_csrf();
    $keys = $_POST['keys'] ?? [];
    $n = is_array($keys) ? zone_content_apply($slug, $keys) : 0;
    if ($n > 0) admin_log('modification', 'Contenu pré-rédigé — '.$zone['name'], $n.' texte(s) appliqué(s)');
    flash('success', $n === 0
        ? 'Aucun texte modifié.'
        : $n.' texte'.($n > 1 ? 's' : '').' appliqué'.($n > 1 ? 's' : '').' à '.$zone['name'].'.');
    redirect_to('admin/zone_content.php');
}

$lignes = $pack ? zone_content_preview($slug) : [];
$aFaire = array_filter($lignes, fn($l) => !$l['identique']);

$adminSection = 'zones';
require_once __DIR__ . '/partials/header.php';
?>
<div class="admin-page-toolbar">
  <div>
    <div class="admin-breadcrumb">Multi-zones</div>
    <h1 class="admin-page-title">Contenu pré-rédigé — <?= e($zone['name']) ?></h1>
    <p class="admin-page-subtitle"><?= e($pack['resume'] ?? '') ?></p>
  </div>
  <div class="admin-toolbar-actions">
    <a class="admin-btn admin-btn--secondary" href="<?= e(url_for($slug.'/')) ?>" target="_blank">Voir la page</a>
  </div>
</div>

<?php if (!$pack): ?>
<section class="admin-panel">
  <div class="admin-panel__body">
    <p>Aucun contenu pré-rédigé n'existe pour <strong><?= e($zone['name']) ?></strong> pour le moment.</p>
    <p style="color:#7b8aa8;font-size:.9rem;">Vous pouvez rédiger cette zone à la main : sélectionnez-la dans le bandeau
       du haut, puis modifiez les pages depuis « Contenu du site ». Chaque champ laissé vide reprend le texte du site global.</p>
  </div>
</section>

<?php elseif (!$aFaire): ?>
<section class="admin-panel">
  <div class="admin-panel__body">
    <p>✅ Cette zone affiche déjà l'intégralité du contenu pré-rédigé. Rien à appliquer.</p>
  </div>
</section>

<?php else: ?>
<form method="post">
  <?= csrf_field() ?>
  <section class="admin-panel">
    <div class="admin-panel__head">
      <h2><?= count($aFaire) ?> texte<?= count($aFaire) > 1 ? 's' : '' ?> à appliquer</h2>
      <p>Décochez ce que vous voulez garder tel quel. Les textes que vous avez déjà retouchés pour cette zone
         sont décochés par défaut, pour ne pas écraser votre travail. Rien n'est modifié sur le site global.</p>
    </div>
    <div class="admin-panel__body admin-table-wrap">
      <table class="admin-table">
        <thead><tr>
          <th style="width:34px;"><input type="checkbox" id="zc-all"></th>
          <th style="width:190px;">Emplacement</th>
          <th>Aujourd'hui, puis proposé</th>
        </tr></thead>
        <tbody>
        <?php $groupeVu = ''; foreach ($aFaire as $l): ?>
          <?php if ($l['groupe'] !== $groupeVu): $groupeVu = $l['groupe']; ?>
            <tr><td colspan="3" style="background:#f7faff;font-weight:700;font-size:.8rem;
                text-transform:uppercase;letter-spacing:.05em;color:#5b6b92;"><?= e($groupeVu) ?></td></tr>
          <?php endif; ?>
          <tr>
            <td><input type="checkbox" class="zc-cb" name="keys[]" value="<?= e($l['key']) ?>"
                       <?= $l['personnalise'] ? '' : 'checked' ?>></td>
            <td style="font-size:.85rem;"><?= e($l['label']) ?>
              <?php if ($l['personnalise']): ?>
                <br><small style="color:#b45309;font-weight:700;">déjà retouché</small>
              <?php endif; ?>
            </td>
            <td style="max-width:560px;font-size:.85rem;line-height:1.55;">
              <div style="color:#7b8aa8;"><?= e(mb_substr($l['actuel'], 0, 200)) ?></div>
              <div style="margin-top:.3rem;padding-top:.3rem;border-top:1px dashed #dde5f3;color:#14532d;font-weight:600;">
                <?= e(mb_substr($l['propose'], 0, 200)) ?>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <div class="admin-savebar" style="display:flex;gap:1rem;align-items:center;flex-wrap:wrap;">
      <span style="font-size:.82rem;color:#7b88a6;margin-right:auto;">
        S'applique uniquement à <strong><?= e($zone['name']) ?></strong>. Un texte se remodifie ensuite librement,
        et se vide pour revenir à la version globale.
      </span>
      <button class="admin-btn admin-btn--primary" type="submit">Appliquer à <?= e($zone['name']) ?></button>
    </div>
  </section>
</form>
<?php endif; ?>

<script>
document.getElementById('zc-all')?.addEventListener('change', function(){
  document.querySelectorAll('.zc-cb').forEach(function(c){ c.checked = this.checked; }, this);
});
</script>
<?php require __DIR__ . '/partials/footer.php'; ?>
