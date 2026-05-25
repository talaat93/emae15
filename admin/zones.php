<?php
$adminSection = 'zones';
require __DIR__ . '/partials/header.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    // SEO
    set_setting('zones_meta_title', trim((string) ($_POST['zones_meta_title'] ?? '')));
    set_setting('zones_meta_desc',  trim((string) ($_POST['zones_meta_desc']  ?? '')));

    // Hero
    $saved = get_json_setting('zones_page_settings', []);
    $saved['eyebrow']  = trim((string) ($_POST['eyebrow']  ?? ''));
    $saved['title']    = trim((string) ($_POST['title']    ?? ''));
    $saved['title_hl'] = trim((string) ($_POST['title_hl'] ?? ''));
    $saved['lead']     = trim((string) ($_POST['lead']     ?? ''));

    // Regions
    $regions = [];
    foreach (($_POST['regions'] ?? []) as $r) {
        $name  = trim((string) ($r['name']  ?? ''));
        if ($name === '') continue;
        $depts  = array_values(array_filter(array_map('trim', explode('|', (string) ($r['depts']  ?? '')))));
        $cities = array_values(array_filter(array_map('trim', explode('|', (string) ($r['cities'] ?? '')))));
        $regions[] = [
            'name'  => $name,
            'icon'  => trim((string) ($r['icon']  ?? '🗺️')),
            'color' => trim((string) ($r['color'] ?? '#F07B1D')),
            'delay' => trim((string) ($r['delay'] ?? '')),
            'depts' => $depts,
            'cities'=> $cities,
        ];
    }
    if ($regions) $saved['regions'] = $regions;

    set_json_setting('zones_page_settings', $saved);
    flash('success', 'Page Zones enregistrée.');
    redirect_to('admin/zones.php');
}

$cfg = zones_page_settings();
?>
<div class="admin-page-toolbar">
  <div>
    <div class="admin-breadcrumb">Pages</div>
    <h1 class="admin-page-title">Zones d'intervention</h1>
    <p class="admin-page-subtitle">Hero, régions et SEO de la page /zones.</p>
  </div>
  <div class="admin-toolbar-actions">
    <a class="admin-btn admin-btn--secondary" href="<?= e(route_url('zones')) ?>" target="_blank">Voir la page</a>
  </div>
</div>

<form method="post" class="admin-stack">
<input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

<!-- HERO -->
<section class="admin-panel">
  <div class="admin-panel__head"><h2>Hero</h2><p>Le bloc en haut de la page /zones.</p></div>
  <div class="admin-panel__body">
    <div class="admin-form-grid admin-form-grid--2">
      <label class="admin-field"><span>Petit texte (eyebrow)</span><input type="text" name="eyebrow" value="<?= e($cfg['eyebrow'] ?? 'Zones d\'intervention') ?>"></label>
      <label class="admin-field"><span>Titre (partie normale)</span><input type="text" name="title" value="<?= e($cfg['title'] ?? 'Nous intervenons') ?>"></label>
    </div>
    <div class="admin-form-grid admin-form-grid--2">
      <label class="admin-field"><span>Titre mis en valeur (orange)</span><input type="text" name="title_hl" value="<?= e($cfg['title_hl'] ?? 'près de chez vous') ?>"></label>
    </div>
    <label class="admin-field"><span>Texte descriptif (lead)</span><textarea name="lead" rows="3"><?= e($cfg['lead'] ?? '') ?></textarea></label>
  </div>
</section>

<!-- RÉGIONS -->
<?php foreach (($cfg['regions'] ?? []) as $ri => $reg): ?>
<section class="admin-panel">
  <div class="admin-panel__head">
    <h2>Région <?= e((string)($ri+1)) ?> — <?= e($reg['name'] ?? '') ?></h2>
    <p>Départements et villes affichés sur la page.</p>
  </div>
  <div class="admin-panel__body">
    <div class="admin-form-grid admin-form-grid--2">
      <label class="admin-field"><span>Nom de la région</span><input type="text" name="regions[<?= e((string)$ri) ?>][name]" value="<?= e($reg['name'] ?? '') ?>"></label>
      <label class="admin-field"><span>Icône (emoji)</span><input type="text" name="regions[<?= e((string)$ri) ?>][icon]" value="<?= e($reg['icon'] ?? '🗺️') ?>"></label>
    </div>
    <div class="admin-form-grid admin-form-grid--2">
      <label class="admin-field"><span>Couleur accent (ex : #F07B1D)</span><input type="text" name="regions[<?= e((string)$ri) ?>][color]" value="<?= e($reg['color'] ?? '#F07B1D') ?>"></label>
      <label class="admin-field"><span>Délai d'intervention</span><input type="text" name="regions[<?= e((string)$ri) ?>][delay]" value="<?= e($reg['delay'] ?? 'Intervention < 2h') ?>"></label>
    </div>
    <label class="admin-field">
      <span>Départements <small style="font-weight:400;color:#7B92CC;">(séparés par |)</small></span>
      <input type="text" name="regions[<?= e((string)$ri) ?>][depts]" value="<?= e(implode(' | ', $reg['depts'] ?? [])) ?>">
    </label>
    <label class="admin-field">
      <span>Villes principales <small style="font-weight:400;color:#7B92CC;">(séparées par |)</small></span>
      <input type="text" name="regions[<?= e((string)$ri) ?>][cities]" value="<?= e(implode(' | ', $reg['cities'] ?? [])) ?>">
    </label>
  </div>
</section>
<?php endforeach; ?>

<!-- SEO -->
<section class="admin-panel">
  <div class="admin-panel__head"><h2>SEO</h2><p>Balises meta pour la page /zones.</p></div>
  <div class="admin-panel__body admin-form-grid admin-form-grid--2">
    <label class="admin-field"><span>Meta title</span><input type="text" name="zones_meta_title" value="<?= e(setting('zones_meta_title', 'Zones d\'intervention | '.company_name())) ?>"></label>
    <label class="admin-field"><span>Meta description</span><input type="text" name="zones_meta_desc" value="<?= e(setting('zones_meta_desc', '')) ?>"></label>
  </div>
</section>

<div class="admin-savebar">
  <button class="admin-btn admin-btn--primary" type="submit">Enregistrer</button>
</div>
</form>

<?php require __DIR__ . '/partials/footer.php'; ?>