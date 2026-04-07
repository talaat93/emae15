<?php
$adminSection = 'home_services';
require __DIR__ . '/partials/header.php';

$cards = service_cards_settings();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $newCards = [];
    $raw = $_POST['cards'] ?? [];
    foreach ($raw as $index => $row) {
        $title = trim((string) ($row['title'] ?? ''));
        $link = trim((string) ($row['link'] ?? ''));
        $current = trim((string) ($row['current_image'] ?? ''));
        $uploadField = 'card_image_' . $index;
        $uploaded = upload_image_field($uploadField, 'services');
        $image = $uploaded ?: $current;
        if ($title === '' || $image === '') {
            continue;
        }
        $newCards[] = ['title' => $title, 'image' => $image, 'link' => $link];
    }
    if ($newCards) {
        set_json_setting('home_service_cards', $newCards);
        flash('success', 'Cartes services enregistrées.');
    } else {
        flash('error', 'Merci de garder au moins une carte.');
    }
    redirect_to('admin/home_services.php');
}
?>
<div class="admin-page-toolbar">
  <div><div class="admin-breadcrumb">Accueil</div><h1 class="admin-page-title">Cartes services accueil</h1><p class="admin-page-subtitle">Les 4 grandes cartes blanches sous le hero.</p></div>
</div>
<form method="post" enctype="multipart/form-data" class="admin-stack">
<input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
<section class="admin-panel">
  <div class="admin-panel__head"><h2>Modifier les cartes</h2><p>Titre, image et lien de chaque carte.</p></div>
  <div class="admin-panel__body repeat-grid">
    <?php foreach ($cards as $i => $card): ?>
      <div class="repeat-card">
        <h3>Carte <?= e((string) ($i + 1)) ?></h3>
        <input type="hidden" name="cards[<?= e((string) $i) ?>][current_image]" value="<?= e($card['image']) ?>">
        <div class="admin-form-grid admin-form-grid--2">
          <label class="admin-field"><span>Titre</span><input type="text" name="cards[<?= e((string) $i) ?>][title]" value="<?= e($card['title']) ?>"></label>
          <label class="admin-field"><span>Lien</span><input type="text" name="cards[<?= e((string) $i) ?>][link]" value="<?= e($card['link']) ?>" placeholder="electricien-meaux"></label>
        </div>
        <img class="preview-thumb" src="<?= e(asset_url($card['image'])) ?>" alt="<?= e($card['title']) ?>">
        <label class="admin-field"><span>Nouvelle image</span><input type="file" name="card_image_<?= e((string) $i) ?>" accept=".png,.jpg,.jpeg,.webp,.svg"></label>
      </div>
    <?php endforeach; ?>
  </div>
</section>
<div class="admin-savebar"><button class="admin-btn admin-btn--primary" type="submit">Enregistrer</button></div>
</form>
<?php require __DIR__ . '/partials/footer.php'; ?>
