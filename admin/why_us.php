<?php
$adminSection = 'why_us';
require __DIR__ . '/partials/header.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    // Infos générales
    set_setting('why_us_eyebrow', trim((string)($_POST['why_us_eyebrow'] ?? '')));
    set_setting('why_us_title',   trim((string)($_POST['why_us_title']   ?? '')));
    set_setting('why_us_lead',    trim((string)($_POST['why_us_lead']    ?? '')));

    // Items
    $items = [];
    $titles = $_POST['item_title']  ?? [];
    $texts  = $_POST['item_text']   ?? [];
    $icons  = $_POST['item_icon']   ?? [];
    $types  = $_POST['item_type']   ?? [];

    foreach ($titles as $i => $t) {
        if (trim($t) === '') continue;
        $iconImg = '';
        if (isset($_FILES['item_icon_img']['name'][$i]) && $_FILES['item_icon_img']['error'][$i] === UPLOAD_ERR_OK) {
            $iconImg = upload_file_from_array($_FILES['item_icon_img'], $i, 'storage/uploads/why_us');
        } else {
            $iconImg = trim((string)($_POST['item_icon_img_existing'][$i] ?? ''));
        }
        $items[] = [
            'title'     => trim((string)$t),
            'text'      => trim((string)($texts[$i] ?? '')),
            'icon'      => trim((string)($icons[$i] ?? '✓')),
            'icon_type' => in_array($types[$i] ?? '', ['emoji','image']) ? $types[$i] : 'emoji',
            'icon_img'  => $iconImg,
        ];
    }

    set_json_setting('why_us_settings', [
        'eyebrow' => trim((string)($_POST['why_us_eyebrow'] ?? '')),
        'title'   => trim((string)($_POST['why_us_title']   ?? '')),
        'lead'    => trim((string)($_POST['why_us_lead']    ?? '')),
        'items'   => $items,
    ]);

    flash('success', 'Section "Pourquoi nous choisir" enregistrée.');
    redirect_to('admin/why_us.php');
}

$why = why_us_settings();
$items = $why['items'] ?? [];

// Helper to upload file from multi-file array
function upload_file_from_array(array $files, int $idx, string $dir): string
{
    $name = $files['name'][$idx]   ?? '';
    $tmp  = $files['tmp_name'][$idx] ?? '';
    $err  = $files['error'][$idx]  ?? UPLOAD_ERR_NO_FILE;
    if ($err !== UPLOAD_ERR_OK || $tmp === '' || $name === '') return '';
    $ext  = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg','jpeg','png','gif','webp','svg'])) return '';
    $fullDir = __DIR__ . '/../' . $dir;
    if (!is_dir($fullDir)) mkdir($fullDir, 0755, true);
    $dest = $dir . '/' . uniqid('why_', true) . '.' . $ext;
    if (move_uploaded_file($tmp, __DIR__ . '/../' . $dest)) return $dest;
    return '';
}
?>

<div class="admin-page-toolbar">
  <div>
    <div class="admin-breadcrumb">Accueil</div>
    <h1 class="admin-page-title">Pourquoi nous choisir</h1>
    <p class="admin-page-subtitle">Contrôle total de la section arguments de confiance affichée sur l'accueil.</p>
  </div>
  <div class="admin-toolbar-actions">
    <a class="admin-btn admin-btn--secondary" href="<?= e(route_url('')) ?>" target="_blank">Voir l'accueil</a>
  </div>
</div>

<form method="post" enctype="multipart/form-data" class="admin-stack">
<input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

<!-- Infos générales -->
<section class="admin-panel">
  <div class="admin-panel__head"><h2>En-tête de la section</h2></div>
  <div class="admin-panel__body admin-stack">
    <label class="admin-field"><span>Accroche (petit texte orange au-dessus du titre)</span>
      <input type="text" name="why_us_eyebrow" value="<?= e($why['eyebrow'] ?? 'Pourquoi nous choisir') ?>" placeholder="Pourquoi nous choisir"></label>
    <label class="admin-field"><span>Titre principal</span>
      <input type="text" name="why_us_title" value="<?= e($why['title'] ?? '') ?>" placeholder="EMAE, votre expert multitechnique de confiance"></label>
    <label class="admin-field"><span>Sous-titre / description</span>
      <textarea name="why_us_lead" rows="2" placeholder="Des artisans qualifiés, des délais respectés…"><?= e($why['lead'] ?? '') ?></textarea></label>
  </div>
</section>

<!-- Arguments -->
<section class="admin-panel">
  <div class="admin-panel__head">
    <h2>Arguments (jusqu'à 8)</h2>
    <p>Chaque argument affiche une icône (emoji ou image PNG), un titre et un texte explicatif.</p>
  </div>
  <div class="admin-panel__body admin-stack">
    <?php for ($i = 0; $i < 8; $i++):
      $item = $items[$i] ?? ['title'=>'','text'=>'','icon'=>'✓','icon_type'=>'emoji','icon_img'=>''];
    ?>
    <div class="admin-panel" style="border:1.5px solid #e8edf8;border-radius:12px;padding:1.25rem;background:#fafbff;">
      <div style="display:flex;align-items:center;gap:.75rem;margin-bottom:1rem;">
        <span style="width:28px;height:28px;background:var(--admin-primary,#2f66d2);color:#fff;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:.88rem;flex-shrink:0;"><?= $i+1 ?></span>
        <strong>Argument <?= $i+1 ?></strong>
      </div>

      <div class="admin-form-grid admin-form-grid--2">
        <label class="admin-field"><span>Titre</span>
          <input type="text" name="item_title[<?= $i ?>]" value="<?= e($item['title']) ?>" placeholder="Ex : Intervention rapide"></label>
        <label class="admin-field"><span>Icône emoji (si type Emoji)</span>
          <input type="text" name="item_icon[<?= $i ?>]" value="<?= e($item['icon'] ?? '✓') ?>" placeholder="⚡ 🔒 📋 ⭐ etc." style="font-size:1.2rem;"></label>
      </div>

      <label class="admin-field"><span>Texte descriptif</span>
        <textarea name="item_text[<?= $i ?>]" rows="2" placeholder="Description de l'argument…"><?= e($item['text']) ?></textarea></label>

      <div class="admin-form-grid admin-form-grid--2">
        <label class="admin-field">
          <span>Type d'icône</span>
          <select name="item_type[<?= $i ?>]" onchange="toggleIconImg(this, <?= $i ?>)">
            <option value="emoji" <?= ($item['icon_type']??'emoji')==='emoji' ? 'selected' : '' ?>>Emoji (texte)</option>
            <option value="image" <?= ($item['icon_type']??'emoji')==='image' ? 'selected' : '' ?>>Image PNG/SVG</option>
          </select>
        </label>
        <div class="admin-field icon-img-field-<?= $i ?>" style="<?= ($item['icon_type']??'emoji')==='emoji' ? 'display:none;' : '' ?>">
          <span>Image PNG/SVG (52×52px recommandé)</span>
          <?php if (trim($item['icon_img']??'') !== '' && file_exists(__DIR__.'/../'.$item['icon_img'])): ?>
            <div style="margin-bottom:.5rem;"><img src="<?= e(asset_url($item['icon_img'])) ?>" alt="" style="width:52px;height:52px;object-fit:cover;border-radius:8px;border:1px solid #dde5f3;"></div>
          <?php endif; ?>
          <input type="hidden" name="item_icon_img_existing[<?= $i ?>]" value="<?= e($item['icon_img'] ?? '') ?>">
          <input type="file" name="item_icon_img[<?= $i ?>]" accept="image/png,image/jpeg,image/webp,image/svg+xml">
        </div>
      </div>
    </div>
    <?php endfor; ?>
  </div>
</section>

<div class="admin-savebar"><button class="admin-btn admin-btn--primary" type="submit">💾 Enregistrer</button></div>
</form>

<script>
function toggleIconImg(select, idx) {
  var field = document.querySelector('.icon-img-field-' + idx);
  if (field) field.style.display = select.value === 'image' ? '' : 'none';
}
</script>

<?php require __DIR__ . '/partials/footer.php'; ?>
