<?php
/**
 * Photos de terrain des pages métier × ville (/intervention/{ville}/{métier}) : trois emplacements
 * (véhicule, intervention, équipe), une photo pour toutes les pages et, si besoin, une par métier
 * (prioritaire). Affichage : includes/city_trade_pages.php, render_city_trade_photo().
 */
$adminSection = 'terrain_photos';
require __DIR__ . '/partials/header.php';

$slots   = city_trade_photo_slots();
$metiers = ['' => 'Toutes les pages'];
foreach (city_trades() as [$t, $pro]) $metiers[$t] = $pro;
$key = static fn(string $s, string $t): string => 'ct_photo_'.$s.($t !== '' ? '_'.$t : '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $n = 0; $refus = 0;
    foreach ($slots as $s => $_) foreach ($metiers as $t => $_l) {
        $k = $key($s, $t);
        if (!empty($_POST['clear_'.$k])) { set_setting($k, ''); $n++; }
        if (!empty($_FILES['up_'.$k]['name'])) {
            $img = upload_image_field('up_'.$k, 'terrain');
            if ($img && !str_ends_with($img, '.svg')) { set_setting($k, $img); $n++; }
            else $refus++;
        }
    }
    if ($refus) flash('error', $refus.' fichier(s) refusé(s) : envoyez une photo JPG, PNG ou WebP.');
    flash('success', $n ? 'Photos enregistrées ('.$n.' modification'.($n > 1 ? 's' : '').').' : 'Aucune modification.');
    redirect_to('admin/terrain_photos.php');
}
$exemple = null;
foreach (city_pages() as $cs => $_c) { $exemple = $cs; break; }
?>
<div class="admin-page-toolbar">
  <div>
    <div class="admin-breadcrumb">Contenus</div>
    <h1 class="admin-page-title">Photos des pages ville</h1>
    <p class="admin-page-subtitle">Vraies photos EMAE affichées sur les pages « métier + ville » (ex. Électricien à Beaune), plus bas que le haut de page :
      le titre et le bouton d'appel restent visibles tout de suite sur mobile. Sans photo, l'emplacement n'apparaît pas.</p>
  </div>
</div>

<form method="post" enctype="multipart/form-data" class="admin-stack">
<input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

<?php foreach ($slots as $s => [$lettre, $quoi, $ou]): ?>
<section class="admin-panel">
  <div class="admin-panel__head">
    <h2>Emplacement <?= e($lettre) ?> — <?= e($quoi) ?></h2>
    <p>Sur la page : <?= e($ou) ?>. Une photo propre à un métier remplace celle de « Toutes les pages » pour ce métier.</p>
  </div>
  <div class="admin-panel__body">
    <div class="tp-grid">
    <?php foreach ($metiers as $t => $lib):
        $k = $key($s, $t);
        $cur = ltrim(setting_plain($k), '/');
        $ok = $cur !== '' && is_file(__DIR__.'/../'.$cur);
        $voir = $exemple ? url_for('intervention/'.$exemple.'/'.($t !== '' ? city_trade_slug($t) : 'electricien')) : '';
    ?>
      <div class="tp-card">
        <div class="tp-name"><?= e($lib) ?></div>
        <?php if ($ok): ?>
          <img class="tp-thumb" src="<?= e(asset_url($cur)) ?>" alt="" loading="lazy">
          <label class="tp-del"><input type="checkbox" name="clear_<?= e($k) ?>" value="1"> Retirer cette photo</label>
        <?php else: ?>
          <div class="tp-thumb tp-empty"><?= $t === '' ? 'Aucune photo' : 'Photo « Toutes les pages »' ?></div>
        <?php endif; ?>
        <label class="admin-field"><span><?= $ok ? 'Remplacer' : 'Ajouter' ?> (JPG, PNG, WebP)</span>
          <input type="file" name="up_<?= e($k) ?>" accept=".jpg,.jpeg,.png,.webp"></label>
        <?php if ($voir !== ''): ?><a class="tp-see" href="<?= e($voir) ?>" target="_blank" rel="noopener">Voir une page →</a><?php endif; ?>
      </div>
    <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endforeach; ?>

<p style="font-size:.8rem;color:#6b7a99;">Conseils : format paysage (idéal 1200 × 750 px), photo nette sans texte incrusté ; évitez les plaques d'immatriculation et les visages de clients.
  Les photos trop lourdes sont réduites automatiquement pour le mobile.</p>

<div class="admin-savebar">
  <button class="admin-btn admin-btn--primary" type="submit">Enregistrer les photos</button>
</div>
</form>
<style>
.tp-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(210px,1fr));gap:1rem;}
.tp-card{border:1px solid #dde5f3;border-radius:14px;padding:.85rem;display:flex;flex-direction:column;gap:.55rem;}
.tp-name{font-weight:800;color:#13254c;}
.tp-thumb{width:100%;aspect-ratio:16/10;object-fit:cover;border-radius:10px;display:block;}
.tp-empty{display:flex;align-items:center;justify-content:center;background:#f4f7fc;border:1px dashed #c9d4ea;color:#8494b4;font-size:.8rem;text-align:center;padding:.5rem;}
.tp-del{font-size:.8rem;color:#c0392b;display:flex;gap:.4rem;align-items:center;cursor:pointer;}
.tp-see{font-size:.8rem;}
</style>

<?php require __DIR__ . '/partials/footer.php'; ?>
