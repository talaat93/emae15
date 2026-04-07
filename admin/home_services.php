<?php
$adminSection = 'home_services';
require __DIR__ . '/partials/header.php';

/* Charge les cartes V14 — celles affichées sur la homepage */
$cards = service_cards_v14();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $newCards = [];
    $raw = $_POST['cards'] ?? [];
    foreach ($raw as $index => $row) {
        $title = trim((string) ($row['title'] ?? ''));
        if ($title === '') continue;

        $current = trim((string) ($row['current_image'] ?? ''));
        $uploadField = 'card_image_' . $index;
        $uploaded = upload_image_field($uploadField, 'services');
        $image = $uploaded ?: $current;

        /* Tags : chaîne séparée par virgule → tableau */
        $tagsRaw = trim((string) ($row['tags'] ?? ''));
        $tags = $tagsRaw !== ''
            ? array_values(array_filter(array_map('trim', explode(',', $tagsRaw))))
            : [];

        $newCards[] = [
            'title'            => $title,
            'image'            => $image,
            'link'             => trim((string) ($row['link'] ?? '')),
            'badge'            => trim((string) ($row['badge'] ?? '')),
            'desc'             => trim((string) ($row['desc'] ?? '')),
            'tags'             => $tags,
            'placeholder_icon' => trim((string) ($row['placeholder_icon'] ?? '')),
        ];
    }
    if (count($newCards) > 0) {
        set_json_setting('home_service_cards_v14', $newCards);
        flash('success', 'Cartes services enregistrées.');
    } else {
        flash('error', 'Merci de conserver au moins une carte.');
    }
    redirect_to('admin/home_services.php');
}
?>
<div class="admin-page-toolbar">
  <div>
    <div class="admin-breadcrumb">Accueil</div>
    <h1 class="admin-page-title">Pôles d'intervention — cartes accueil</h1>
    <p class="admin-page-subtitle">Les 4 cartes « Nos pôles d'intervention » visibles sur la page d'accueil. L'image s'affiche en fond de chaque carte.</p>
  </div>
</div>

<form method="post" enctype="multipart/form-data" class="admin-stack">
<input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

<section class="admin-panel">
  <div class="admin-panel__head">
    <h2>Modifier les 4 cartes</h2>
    <p>Titre, image de fond, lien, badge, description courte et tags (séparés par des virgules).</p>
  </div>
  <div class="admin-panel__body repeat-grid">
    <?php foreach ($cards as $i => $card):
      $tagsStr = is_array($card['tags'] ?? null) ? implode(', ', $card['tags']) : (string)($card['tags'] ?? '');
    ?>
    <div class="repeat-card">
      <h3>Carte <?= e((string) ($i + 1)) ?> — <?= e($card['title']) ?></h3>

      <input type="hidden" name="cards[<?= $i ?>][current_image]" value="<?= e($card['image']) ?>">

      <div class="admin-form-grid admin-form-grid--2">
        <label class="admin-field">
          <span>Titre *</span>
          <input type="text" name="cards[<?= $i ?>][title]" value="<?= e($card['title']) ?>" required>
        </label>
        <label class="admin-field">
          <span>Lien (slug ou URL)</span>
          <input type="text" name="cards[<?= $i ?>][link]" value="<?= e($card['link'] ?? '') ?>" placeholder="electricite">
        </label>
      </div>

      <div class="admin-form-grid admin-form-grid--2">
        <label class="admin-field">
          <span>Badge (ex : Urgence 24h/7j)</span>
          <input type="text" name="cards[<?= $i ?>][badge]" value="<?= e($card['badge'] ?? '') ?>">
        </label>
        <label class="admin-field">
          <span>Icône placeholder (emoji — sans image)</span>
          <input type="text" name="cards[<?= $i ?>][placeholder_icon]" value="<?= e($card['placeholder_icon'] ?? '') ?>" placeholder="⚡">
        </label>
      </div>

      <label class="admin-field">
        <span>Description courte</span>
        <input type="text" name="cards[<?= $i ?>][desc]" value="<?= e($card['desc'] ?? '') ?>" placeholder="Dépannage, installation, mise aux normes…">
      </label>

      <label class="admin-field">
        <span>Tags (séparés par des virgules)</span>
        <input type="text" name="cards[<?= $i ?>][tags]" value="<?= e($tagsStr) ?>" placeholder="Dépannage, Installation, Mise aux normes">
      </label>

      <?php if (trim($card['image']) !== ''): ?>
        <div style="margin:.5rem 0;">
          <p style="font-size:.75rem;color:#888;margin-bottom:.35rem;">Image actuelle :</p>
          <img class="preview-thumb" src="<?= e(asset_url($card['image'])) ?>" alt="<?= e($card['title']) ?>"
               style="max-height:120px;border-radius:8px;border:1px solid rgba(0,0,0,.1);">
        </div>
      <?php endif; ?>

      <label class="admin-field">
        <span>Nouvelle image de fond (JPEG, PNG, WebP — recommandé 800×600 px min)</span>
        <input type="file" name="card_image_<?= $i ?>" accept=".png,.jpg,.jpeg,.webp">
      </label>
    </div>
    <?php endforeach; ?>
  </div>
</section>

<div class="admin-savebar">
  <button class="admin-btn admin-btn--primary" type="submit">Enregistrer les cartes</button>
</div>
</form>

<?php require __DIR__ . '/partials/footer.php'; ?>
