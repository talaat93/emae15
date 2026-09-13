<?php
$adminSection = 'design';
require __DIR__ . '/partials/header.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    foreach ([
        'color_primary','color_primary_hover',
        'color_navy','color_navy_2','color_navy_3',
        'color_dark','color_dark_card',
        'color_text','color_text_muted','color_line',
        'font_heading','font_body',
        'hero_bg_from','hero_bg_to','hero_glow_1','hero_glow_2',
        'hero_overlay_opacity','card_overlay_opacity',
    ] as $f) set_setting($f, trim((string)($_POST[$f] ?? '')));
    flash('success','Design et couleurs enregistrés. Rechargez le site pour voir les changements.');
    redirect_to('admin/design.php');
}
$d = design_settings();
?>
<div class="admin-page-toolbar">
  <div><div class="admin-breadcrumb">Design</div>
    <h1 class="admin-page-title">Design & Couleurs</h1>
    <p class="admin-page-subtitle">Contrôle total de l'apparence : couleurs, typographie, hero, opacités des images.</p>
  </div>
  <div class="admin-toolbar-actions"><a class="admin-btn admin-btn--secondary" href="<?= e(route_url('')) ?>" target="_blank">Voir le site</a></div>
</div>

<form method="post" class="admin-stack">
<input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

<section class="admin-panel">
  <div class="admin-panel__head"><h2>🎨 Couleurs principales</h2><p>S'appliquent sur tout le site (boutons, accents, liens).</p></div>
  <div class="admin-panel__body admin-form-grid admin-form-grid--2">
    <label class="admin-field"><span>Couleur principale (boutons, accents)</span>
      <div style="display:flex;gap:.65rem;align-items:center;">
        <input type="color" name="color_primary" value="<?= e($d['color_primary']) ?>" style="width:56px;min-height:44px;cursor:pointer;">
        <input type="text" id="txt_color_primary" value="<?= e($d['color_primary']) ?>" placeholder="#ee7d1a" style="flex:1;" oninput="syncColorPicker('color_primary',this.value)">
      </div></label>
    <label class="admin-field"><span>Couleur principale au survol</span>
      <div style="display:flex;gap:.65rem;align-items:center;">
        <input type="color" name="color_primary_hover" value="<?= e($d['color_primary_hover']) ?>" style="width:56px;min-height:44px;cursor:pointer;">
        <input type="text" value="<?= e($d['color_primary_hover']) ?>" placeholder="#c95f0b" style="flex:1;">
      </div></label>
  </div>
</section>

<section class="admin-panel">
  <div class="admin-panel__head"><h2>🌑 Couleurs de fond (thème sombre)</h2><p>Fond du site, cartes, sections marines.</p></div>
  <div class="admin-panel__body admin-form-grid admin-form-grid--2">
    <label class="admin-field"><span>Fond principal (le plus sombre)</span>
      <div style="display:flex;gap:.65rem;align-items:center;"><input type="color" name="color_dark" value="<?= e($d['color_dark']) ?>" style="width:56px;min-height:44px;cursor:pointer;"><input type="text" value="<?= e($d['color_dark']) ?>" placeholder="#07102a" style="flex:1;"></div></label>
    <label class="admin-field"><span>Fond cartes et panneaux</span>
      <div style="display:flex;gap:.65rem;align-items:center;"><input type="color" name="color_dark_card" value="<?= e($d['color_dark_card']) ?>" style="width:56px;min-height:44px;cursor:pointer;"><input type="text" value="<?= e($d['color_dark_card']) ?>" placeholder="#0f1e3d" style="flex:1;"></div></label>
    <label class="admin-field"><span>Bleu marine principal (header, footer)</span>
      <div style="display:flex;gap:.65rem;align-items:center;"><input type="color" name="color_navy" value="<?= e($d['color_navy']) ?>" style="width:56px;min-height:44px;cursor:pointer;"><input type="text" value="<?= e($d['color_navy']) ?>" placeholder="#0b1641" style="flex:1;"></div></label>
    <label class="admin-field"><span>Bleu marine secondaire</span>
      <div style="display:flex;gap:.65rem;align-items:center;"><input type="color" name="color_navy_2" value="<?= e($d['color_navy_2']) ?>" style="width:56px;min-height:44px;cursor:pointer;"><input type="text" value="<?= e($d['color_navy_2']) ?>" placeholder="#152555" style="flex:1;"></div></label>
    <label class="admin-field"><span>Bleu marine tertiaire (dégradés)</span>
      <div style="display:flex;gap:.65rem;align-items:center;"><input type="color" name="color_navy_3" value="<?= e($d['color_navy_3']) ?>" style="width:56px;min-height:44px;cursor:pointer;"><input type="text" value="<?= e($d['color_navy_3']) ?>" placeholder="#1e3370" style="flex:1;"></div></label>
    <label class="admin-field"><span>Bordures / séparateurs (format rgba)</span>
      <input type="text" name="color_line" value="<?= e($d['color_line']) ?>" placeholder="rgba(255,255,255,.09)"></label>
  </div>
</section>

<section class="admin-panel">
  <div class="admin-panel__head"><h2>✏️ Couleurs des textes</h2></div>
  <div class="admin-panel__body admin-form-grid admin-form-grid--2">
    <label class="admin-field"><span>Texte principal (clair sur fond sombre)</span>
      <div style="display:flex;gap:.65rem;align-items:center;"><input type="color" name="color_text" value="<?= e(preg_match('/^#[0-9a-fA-F]{6}$/',$d['color_text']) ? $d['color_text'] : '#e8ecf5') ?>" style="width:56px;min-height:44px;cursor:pointer;"><input type="text" value="<?= e($d['color_text']) ?>" placeholder="#e8ecf5" style="flex:1;"></div></label>
    <label class="admin-field"><span>Texte secondaire (sous-titres, descriptifs)</span>
      <div style="display:flex;gap:.65rem;align-items:center;"><input type="color" name="color_text_muted" value="<?= e(preg_match('/^#[0-9a-fA-F]{6}$/',$d['color_text_muted']) ? $d['color_text_muted'] : '#8fa0c4') ?>" style="width:56px;min-height:44px;cursor:pointer;"><input type="text" value="<?= e($d['color_text_muted']) ?>" placeholder="#8fa0c4" style="flex:1;"></div></label>
  </div>
</section>

<section class="admin-panel">
  <div class="admin-panel__head"><h2>🔤 Typographie</h2><p>Polices depuis <a href="https://fonts.google.com" target="_blank" style="color:#2f66d2;">Google Fonts</a>. Entrez le nom exact.</p></div>
  <div class="admin-panel__body admin-form-grid admin-form-grid--2">
    <label class="admin-field"><span>Police des titres (ex: Montserrat, Barlow Condensed, Oswald)</span><input type="text" name="font_heading" value="<?= e($d['font_heading']) ?>" placeholder="Montserrat"></label>
    <label class="admin-field"><span>Police du corps (ex: Inter, DM Sans, Roboto)</span><input type="text" name="font_body" value="<?= e($d['font_body']) ?>" placeholder="Inter"></label>
  </div>
</section>

<section class="admin-panel">
  <div class="admin-panel__head"><h2>🌄 Fond du hero (section principale accueil)</h2><p>Le grand bandeau bleu foncé en haut de la page d'accueil.</p></div>
  <div class="admin-panel__body">
    <div class="admin-form-grid admin-form-grid--2">
      <label class="admin-field"><span>Couleur de départ du dégradé (haut/gauche)</span>
        <div style="display:flex;gap:.65rem;align-items:center;"><input type="color" name="hero_bg_from" value="<?= e(setting('hero_bg_from','#07102a')) ?>" style="width:56px;min-height:44px;cursor:pointer;"><input type="text" value="<?= e(setting('hero_bg_from','#07102a')) ?>" placeholder="#07102a" style="flex:1;"></div></label>
      <label class="admin-field"><span>Couleur de fin du dégradé (bas/droite)</span>
        <div style="display:flex;gap:.65rem;align-items:center;"><input type="color" name="hero_bg_to" value="<?= e(setting('hero_bg_to','#152555')) ?>" style="width:56px;min-height:44px;cursor:pointer;"><input type="text" value="<?= e(setting('hero_bg_to','#152555')) ?>" placeholder="#152555" style="flex:1;"></div></label>
      <label class="admin-field"><span>Lueur décorative 1 (format rgba)</span><input type="text" name="hero_glow_1" value="<?= e(setting('hero_glow_1','rgba(238,125,26,.18)')) ?>" placeholder="rgba(238,125,26,.18)"></label>
      <label class="admin-field"><span>Lueur décorative 2 (format rgba)</span><input type="text" name="hero_glow_2" value="<?= e(setting('hero_glow_2','rgba(21,37,85,.8)')) ?>" placeholder="rgba(21,37,85,.8)"></label>
    </div>
    <label class="admin-field"><span>Opacité de l'overlay hero — valeur actuelle : <strong id="hero-ov-val"><?= e(setting('hero_overlay_opacity','0.5')) ?></strong> (0 = très transparent, 1 = très opaque)</span>
      <input type="range" name="hero_overlay_opacity" min="0" max="1" step="0.05" value="<?= e(setting('hero_overlay_opacity','0.5')) ?>" oninput="document.getElementById('hero-ov-val').textContent=this.value" style="width:100%;margin-top:.5rem;"></label>
  </div>
</section>

<section class="admin-panel">
  <div class="admin-panel__head"><h2>🃏 Opacité des cartes services</h2><p>L'overlay sombre sur les images (électricité, plomberie, chauffage, climatisation).</p></div>
  <div class="admin-panel__body">
    <label class="admin-field"><span>Opacité de l'overlay image — valeur actuelle : <strong id="card-ov-val"><?= e(setting('card_overlay_opacity','0.45')) ?></strong> (0 = image bien visible, 1 = très sombre)</span>
      <input type="range" name="card_overlay_opacity" min="0" max="1" step="0.05" value="<?= e(setting('card_overlay_opacity','0.45')) ?>" oninput="document.getElementById('card-ov-val').textContent=this.value" style="width:100%;margin-top:.5rem;"></label>
    <p style="font-size:.85rem;color:#7b8aa8;margin-top:.75rem;">💡 Valeur recommandée entre 0.3 et 0.55 pour que les textes restent lisibles sur les images.</p>
  </div>
</section>

<section class="admin-panel">
  <div class="admin-panel__head"><h2>👁️ Aperçu des couleurs actuelles</h2></div>
  <div class="admin-panel__body">
    <div style="display:flex;flex-wrap:wrap;gap:1rem;">
      <?php foreach([
        ['Principale',  $d['color_primary']],
        ['Survol',      $d['color_primary_hover']],
        ['Fond sombre', $d['color_dark']],
        ['Carte',       $d['color_dark_card']],
        ['Marine',      $d['color_navy']],
        ['Marine 2',    $d['color_navy_2']],
        ['Hero départ', setting('hero_bg_from','#07102a')],
        ['Hero fin',    setting('hero_bg_to','#152555')],
      ] as [$name,$color]):
        $isHex = preg_match('/^#[0-9a-fA-F]{3,6}$/',$color);
      ?>
      <div style="text-align:center;">
        <?php if($isHex): ?>
          <div style="width:56px;height:56px;border-radius:10px;background:<?= e($color) ?>;border:2px solid #dde5f3;box-shadow:0 2px 8px rgba(0,0,0,.1);"></div>
        <?php else: ?>
          <div style="width:56px;height:56px;border-radius:10px;border:2px dashed #dde5f3;display:flex;align-items:center;justify-content:center;font-size:.6rem;color:#7b8aa8;">rgba</div>
        <?php endif; ?>
        <div style="font-size:.7rem;margin-top:.3rem;color:#7b8aa8;"><?= e($name) ?></div>
        <div style="font-size:.6rem;color:#aaa;font-family:monospace;"><?= e(substr($color,0,10)) ?></div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<div class="admin-savebar"><button class="admin-btn admin-btn--primary" type="submit">💾 Enregistrer le design</button></div>
</form>

<script>
function syncColorPicker(name, value) {
  var picker = document.querySelector('[name="' + name + '"]');
  if (picker && /^#[0-9a-fA-F]{6}$/.test(value)) picker.value = value;
}
// Sync all color pickers with adjacent text inputs
document.querySelectorAll('input[type="color"]').forEach(function(picker) {
  var row = picker.parentElement;
  var text = row.querySelector('input[type="text"]');
  if (!text) return;
  picker.addEventListener('input', function() { text.value = picker.value; });
  text.addEventListener('input', function() {
    if (/^#[0-9a-fA-F]{6}$/.test(text.value)) picker.value = text.value;
  });
});
</script>
<?php require __DIR__ . '/partials/footer.php'; ?>
