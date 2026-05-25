<?php
$adminSection = 'services_hero_images';
require __DIR__ . '/partials/header.php';

$services = [
    'electricite'   => ['label' => 'Électricité',       'icon' => '⚡'],
    'plomberie'     => ['label' => 'Plomberie',          'icon' => '💧'],
    'chauffage'     => ['label' => 'Chauffage & PAC',    'icon' => '🔥'],
    'climatisation' => ['label' => 'Climatisation & CVC','icon' => '❄️'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    foreach ($services as $sk => $svc) {
        $img = upload_image_field('hero_'.$sk, 'services');
        if ($img) {
            set_setting('svc_'.$sk.'_hero_image', $img);
        }
        if (!empty($_POST['clear_'.$sk])) {
            set_setting('svc_'.$sk.'_hero_image', '');
        }
    }

    flash('success', 'Images hero enregistrées.');
    redirect_to('admin/services_hero_images.php');
}
?>
<div class="admin-page-toolbar">
  <div>
    <div class="admin-breadcrumb">Pages services</div>
    <h1 class="admin-page-title">Images hero — Services</h1>
    <p class="admin-page-subtitle">Uploadez une photo de fond pour chaque page service. Un overlay marine est appliqué automatiquement.</p>
  </div>
</div>

<form method="post" enctype="multipart/form-data" class="admin-stack">
<input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

<?php foreach ($services as $sk => $svc):
    $current = setting('svc_'.$sk.'_hero_image', '');
    $hasImg  = $current !== '' && file_exists(__DIR__.'/../'.ltrim($current, '/'));
?>
<section class="admin-panel">
  <div class="admin-panel__head">
    <h2><?= e($svc['icon']) ?> <?= e($svc['label']) ?></h2>
    <p>Image de fond de la page <a href="<?= e(route_url($sk)) ?>" target="_blank">/<?= e($sk) ?></a>.</p>
  </div>
  <div class="admin-panel__body">
    <?php if ($hasImg): ?>
    <div style="margin-bottom:1rem;">
      <div style="position:relative;display:inline-block;border-radius:12px;overflow:hidden;max-width:480px;width:100%;">
        <img src="<?= e(asset_url($current)) ?>" alt="Hero <?= e($svc['label']) ?>" style="width:100%;max-height:220px;object-fit:cover;display:block;">
        <div style="position:absolute;inset:0;background:rgba(6,16,41,.80);display:flex;align-items:center;justify-content:center;">
          <span style="color:#fff;font-size:.8rem;font-weight:600;letter-spacing:.05em;opacity:.8;">Aperçu avec overlay</span>
        </div>
      </div>
      <div style="margin-top:.5rem;font-size:.78rem;color:var(--t2,#7B92CC);">Image actuelle : <?= e(basename($current)) ?></div>
    </div>
    <?php else: ?>
    <div style="margin-bottom:1rem;padding:1.25rem;background:rgba(255,255,255,.03);border:1px dashed rgba(255,255,255,.12);border-radius:12px;text-align:center;color:rgba(255,255,255,.3);font-size:.85rem;">
      Aucune image — le hero s'affiche en dégradé marine
    </div>
    <?php endif; ?>

    <div class="admin-form-grid admin-form-grid--2">
      <label class="admin-field">
        <span>Nouvelle image (JPG, PNG, WebP)</span>
        <input type="file" name="hero_<?= e($sk) ?>" accept=".jpg,.jpeg,.png,.webp">
      </label>
      <?php if ($hasImg): ?>
      <label class="admin-field" style="justify-content:flex-end;padding-top:1.5rem;">
        <label style="display:flex;align-items:center;gap:.5rem;cursor:pointer;font-size:.85rem;color:#f87171;">
          <input type="checkbox" name="clear_<?= e($sk) ?>" value="1">
          Supprimer l'image actuelle
        </label>
      </label>
      <?php endif; ?>
    </div>
    <p style="font-size:.75rem;color:rgba(255,255,255,.3);margin-top:.25rem;">Taille recommandée : 1920×600 px minimum.</p>
  </div>
</section>
<?php endforeach; ?>

<div class="admin-savebar">
  <button class="admin-btn admin-btn--primary" type="submit">Enregistrer les images</button>
</div>
</form>

<?php require __DIR__ . '/partials/footer.php'; ?>