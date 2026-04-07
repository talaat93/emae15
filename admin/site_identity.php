<?php
$adminSection = 'site_identity';
require __DIR__ . '/partials/header.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    foreach ([
        'company_name','company_phone','company_phone_link','company_email','company_regions','company_hours','company_address','company_siret',
        'site_logo_width','site_logo_height','site_logo_position'
    ] as $field) {
        set_setting($field, trim((string) ($_POST[$field] ?? '')));
    }

    $logo = upload_image_field('site_logo_file', 'logos');
    if ($logo) {
        set_setting('site_logo', $logo);
    }

    flash('success', 'Identité et coordonnées enregistrées.');
    redirect_to('admin/site_identity.php');
}
?>
<div class="admin-page-toolbar">
  <div><div class="admin-breadcrumb">Identité</div><h1 class="admin-page-title">Identité & coordonnées</h1><p class="admin-page-subtitle">Tout ce qui s’affiche dans la topbar, le footer et le logo.</p></div>
</div>

<form method="post" enctype="multipart/form-data" class="admin-stack">
<input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
<section class="admin-panel">
  <div class="admin-panel__head"><h2>Coordonnées</h2><p>Téléphone, email, zones et horaires.</p></div>
  <div class="admin-panel__body">
    <div class="admin-form-grid admin-form-grid--2">
      <label class="admin-field"><span>Nom entreprise</span><input type="text" name="company_name" value="<?= e(company_name()) ?>"></label>
      <label class="admin-field"><span>Téléphone</span><input type="text" name="company_phone" value="<?= e(company_phone()) ?>"></label>
    </div>
    <div class="admin-form-grid admin-form-grid--2">
      <label class="admin-field"><span>Lien téléphone</span><input type="text" name="company_phone_link" value="<?= e(company_phone_link()) ?>"></label>
      <label class="admin-field"><span>Email</span><input type="email" name="company_email" value="<?= e(company_email()) ?>"></label>
    </div>
    <div class="admin-form-grid admin-form-grid--2">
      <label class="admin-field"><span>Zones d’intervention</span><input type="text" name="company_regions" value="<?= e(company_regions()) ?>"></label>
      <label class="admin-field"><span>Horaires</span><input type="text" name="company_hours" value="<?= e(company_hours()) ?>"></label>
    </div>
    <div class="admin-form-grid admin-form-grid--2">
      <label class="admin-field"><span>Adresse / zone</span><input type="text" name="company_address" value="<?= e(company_address()) ?>"></label>
      <label class="admin-field"><span>SIRET</span><input type="text" name="company_siret" value="<?= e(company_siret()) ?>"></label>
    </div>
  </div>
</section>

<section class="admin-panel">
  <div class="admin-panel__head"><h2>Logo</h2><p>Upload, taille et position.</p></div>
  <div class="admin-panel__body">
    <img class="preview-thumb" src="<?= e(site_logo_url()) ?>" alt="Logo actuel">
    <div class="admin-form-grid admin-form-grid--2">
      <label class="admin-field"><span>Nouveau logo</span><input type="file" name="site_logo_file" accept=".png,.jpg,.jpeg,.webp,.svg"></label>
      <label class="admin-field"><span>Position logo</span>
        <select name="site_logo_position">
          <?php foreach (['left'=>'Gauche','center'=>'Centre','right'=>'Droite'] as $k=>$v): ?>
            <option value="<?= e($k) ?>" <?= site_logo_position()===$k?'selected':'' ?>><?= e($v) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
    </div>
    <div class="admin-form-grid admin-form-grid--2">
      <label class="admin-field"><span>Largeur logo</span><input type="text" name="site_logo_width" value="<?= e(setting('site_logo_width','180')) ?>"></label>
      <label class="admin-field"><span>Hauteur logo</span><input type="text" name="site_logo_height" value="<?= e(setting('site_logo_height','auto')) ?>"></label>
    </div>
  </div>
</section>
<div class="admin-savebar"><button class="admin-btn admin-btn--primary" type="submit">Enregistrer</button></div>
</form>
<?php require __DIR__ . '/partials/footer.php'; ?>
