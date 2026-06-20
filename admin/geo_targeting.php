<?php
declare(strict_types=1);
$adminSection = 'geo_targeting';
require __DIR__ . '/partials/header.php';

// Tous les départements français groupés par région
$allRegions = [
    'Bourgogne-Franche-Comté' => [
        '21'=>"Côte-d'Or (21)",'25'=>'Doubs (25)','39'=>'Jura (39)',
        '58'=>'Nièvre (58)','70'=>'Haute-Saône (70)','71'=>'Saône-et-Loire (71)',
        '89'=>'Yonne (89)','90'=>'Territoire de Belfort (90)',
    ],
    'Auvergne-Rhône-Alpes' => [
        '01'=>'Ain (01)','03'=>'Allier (03)','07'=>'Ardèche (07)','15'=>'Cantal (15)',
        '26'=>'Drôme (26)','38'=>'Isère (38)','42'=>'Loire (42)','43'=>'Haute-Loire (43)',
        '63'=>'Puy-de-Dôme (63)','69'=>'Rhône (69)','73'=>'Savoie (73)','74'=>'Haute-Savoie (74)',
    ],
    'Île-de-France' => [
        '75'=>'Paris (75)','77'=>'Seine-et-Marne (77)','78'=>'Yvelines (78)',
        '91'=>'Essonne (91)','92'=>'Hauts-de-Seine (92)','93'=>'Seine-Saint-Denis (93)',
        '94'=>'Val-de-Marne (94)','95'=>"Val-d'Oise (95)",
    ],
    'Grand Est' => [
        '08'=>'Ardennes (08)','10'=>'Aube (10)','51'=>'Marne (51)','52'=>'Haute-Marne (52)',
        '54'=>'Meurthe-et-Moselle (54)','55'=>'Meuse (55)','57'=>'Moselle (57)',
        '67'=>'Bas-Rhin (67)','68'=>'Haut-Rhin (68)','88'=>'Vosges (88)',
    ],
    'Hauts-de-France' => [
        '02'=>'Aisne (02)','59'=>'Nord (59)','60'=>'Oise (60)','62'=>'Pas-de-Calais (62)','80'=>'Somme (80)',
    ],
    'Normandie' => [
        '14'=>'Calvados (14)','27'=>'Eure (27)','50'=>'Manche (50)','61'=>'Orne (61)','76'=>'Seine-Maritime (76)',
    ],
    'Bretagne' => [
        '22'=>"Côtes-d'Armor (22)",'29'=>'Finistère (29)','35'=>'Ille-et-Vilaine (35)','56'=>'Morbihan (56)',
    ],
    'Pays de la Loire' => [
        '44'=>'Loire-Atlantique (44)','49'=>'Maine-et-Loire (49)','53'=>'Mayenne (53)',
        '72'=>'Sarthe (72)','85'=>'Vendée (85)',
    ],
    'Centre-Val de Loire' => [
        '18'=>'Cher (18)','28'=>'Eure-et-Loir (28)','36'=>'Indre (36)',
        '37'=>'Indre-et-Loire (37)','41'=>'Loir-et-Cher (41)','45'=>'Loiret (45)',
    ],
    'Nouvelle-Aquitaine' => [
        '16'=>'Charente (16)','17'=>'Charente-Maritime (17)','19'=>'Corrèze (19)','23'=>'Creuse (23)',
        '24'=>'Dordogne (24)','33'=>'Gironde (33)','40'=>'Landes (40)','47'=>'Lot-et-Garonne (47)',
        '64'=>'Pyrénées-Atlantiques (64)','79'=>'Deux-Sèvres (79)','86'=>'Vienne (86)','87'=>'Haute-Vienne (87)',
    ],
    'Occitanie' => [
        '09'=>'Ariège (09)','11'=>'Aude (11)','12'=>'Aveyron (12)','30'=>'Gard (30)',
        '31'=>'Haute-Garonne (31)','32'=>'Gers (32)','34'=>'Hérault (34)','46'=>'Lot (46)',
        '48'=>'Lozère (48)','65'=>'Hautes-Pyrénées (65)','66'=>'Pyrénées-Orientales (66)',
        '81'=>'Tarn (81)','82'=>'Tarn-et-Garonne (82)',
    ],
    "Provence-Alpes-Côte d'Azur" => [
        '04'=>'Alpes-de-Haute-Provence (04)','05'=>'Hautes-Alpes (05)','06'=>'Alpes-Maritimes (06)',
        '13'=>'Bouches-du-Rhône (13)','83'=>'Var (83)','84'=>'Vaucluse (84)',
    ],
];

$fallbackOptions = [
    'bfc'       => 'Bourgogne-Franche-Comté (générique)',
    'doubs'     => 'Doubs',
    'jura'      => 'Jura',
    'cote-dor'  => "Côte-d'Or",
    'paris'     => 'Paris / Île-de-France',
    'rhone'     => 'Rhône / Lyon',
    'isere'     => 'Isère / Grenoble',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $activeDepts = array_keys(array_filter($_POST['dept'] ?? [], fn($v) => $v === '1'));
    $fallbackRedirect = !empty($_POST['fallback_redirect']);
    $fallbackSlug     = trim((string)($_POST['fallback_slug']   ?? 'bfc'));
    $redirectMetier   = trim((string)($_POST['redirect_metier'] ?? 'electricite'));

    set_json_setting('geo_targeting_config', [
        'active_depts'      => $activeDepts,
        'fallback_redirect' => $fallbackRedirect,
        'fallback_slug'     => $fallbackSlug,
        'redirect_metier'   => $redirectMetier ?: 'electricite',
    ]);

    flash('success', 'Configuration géo enregistrée.');
    redirect_to('admin/geo_targeting.php');
}

$cfg         = geo_targeting_config();
$activeDepts = $cfg['active_depts'] ?? ['25','39','21'];
?>

<div class="admin-page-toolbar">
  <div>
    <div class="admin-breadcrumb">Marketing</div>
    <h1 class="admin-page-title">🎯 Ciblage géographique</h1>
    <p class="admin-page-subtitle">Choisissez les départements que vous ciblez. Les visiteurs de ces zones voient leur ville personnalisée sur le site.</p>
  </div>
</div>

<form method="post" class="admin-stack">
  <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

  <!-- ── Départements actifs ── -->
  <section class="admin-panel">
    <div class="admin-panel__head">
      <h2>📍 Départements ciblés</h2>
      <p>Les visiteurs de ces départements voient leur ville dans les textes du site.</p>
    </div>
    <div class="admin-panel__body">
      <div style="display:flex;gap:.5rem;margin-bottom:1rem;flex-wrap:wrap;">
        <button type="button" onclick="toggleAll(true)"  style="padding:.35rem .85rem;border-radius:8px;background:#e0edff;color:#1a3baa;border:none;font-weight:700;font-size:.8rem;cursor:pointer;">✅ Tout cocher</button>
        <button type="button" onclick="toggleAll(false)" style="padding:.35rem .85rem;border-radius:8px;background:#f1f5f9;color:#64748b;border:none;font-weight:700;font-size:.8rem;cursor:pointer;">☐ Tout décocher</button>
        <span style="font-size:.78rem;color:#64748b;align-self:center;"><strong id="count-active"><?= count($activeDepts) ?></strong> département(s) sélectionné(s)</span>
      </div>

      <?php foreach ($allRegions as $region => $depts): ?>
      <div style="margin-bottom:1.25rem;">
        <div style="display:flex;align-items:center;gap:.5rem;margin-bottom:.5rem;">
          <div style="font-size:.72rem;font-weight:800;text-transform:uppercase;letter-spacing:.1em;color:#64748b;"><?= e($region) ?></div>
          <button type="button" onclick="toggleRegion(this)" data-region="<?= e($region) ?>"
                  style="font-size:.7rem;padding:.2rem .5rem;border-radius:6px;background:#f1f5f9;color:#475569;border:1px solid #e2e8f0;cursor:pointer;">Tout</button>
        </div>
        <div class="dept-grid" data-region="<?= e($region) ?>" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:.4rem;">
          <?php foreach ($depts as $code => $label): ?>
          <?php $checked = in_array($code, $activeDepts, true); ?>
          <label style="display:flex;align-items:center;gap:.5rem;padding:.45rem .7rem;border-radius:8px;border:1.5px solid <?= $checked ? '#2351c5' : '#e2e8f0' ?>;background:<?= $checked ? '#edf0ff' : '#f8fafc' ?>;cursor:pointer;font-size:.83rem;transition:all .15s;" class="dept-label">
            <input type="checkbox" name="dept[<?= e($code) ?>]" value="1" <?= $checked ? 'checked' : '' ?> onchange="updateCount();styleLabel(this)">
            <span><?= e($label) ?></span>
          </label>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </section>

  <!-- ── Comportement hors zone ── -->
  <section class="admin-panel">
    <div class="admin-panel__head">
      <h2>🌍 Visiteurs hors zone ciblée</h2>
      <p>Que faire quand un visiteur vient d'un département non coché ?</p>
    </div>
    <div class="admin-panel__body">
      <label style="display:flex;align-items:center;gap:.75rem;padding:.85rem;border-radius:10px;border:1.5px solid #e2e8f0;background:#f8fafc;cursor:pointer;margin-bottom:1rem;">
        <input type="checkbox" name="fallback_redirect" value="1" <?= ($cfg['fallback_redirect'] ?? false) ? 'checked' : '' ?> id="fallback-toggle" onchange="document.getElementById('fallback-options').style.display=this.checked?'block':'none'">
        <div>
          <div style="font-weight:700;font-size:.88rem;">Rediriger vers une zone de fallback</div>
          <div style="font-size:.78rem;color:#64748b;">Si décoché, les visiteurs hors zone voient la page d'accueil standard (contenu BFC générique).</div>
        </div>
      </label>

      <div id="fallback-options" style="display:<?= ($cfg['fallback_redirect'] ?? false) ? 'block' : 'none' ?>;">
        <div class="admin-form-grid admin-form-grid--2">
          <label class="admin-field">
            <span>Zone de fallback</span>
            <select name="fallback_slug">
              <?php foreach ($fallbackOptions as $slug => $label): ?>
                <option value="<?= e($slug) ?>" <?= ($cfg['fallback_slug'] ?? 'bfc') === $slug ? 'selected' : '' ?>><?= e($label) ?></option>
              <?php endforeach; ?>
            </select>
          </label>
          <label class="admin-field">
            <span>Métier de la redirection</span>
            <input type="text" name="redirect_metier" value="<?= e($cfg['redirect_metier'] ?? 'electricite') ?>" placeholder="electricite">
          </label>
        </div>
      </div>
    </div>
  </section>

  <!-- ── Aperçu ── -->
  <section class="admin-panel">
    <div class="admin-panel__head"><h2>👁️ Résumé du ciblage actuel</h2></div>
    <div class="admin-panel__body">
      <div style="display:flex;flex-wrap:wrap;gap:.4rem;" id="active-preview">
        <?php foreach ($activeDepts as $code): ?>
          <?php foreach ($allRegions as $depts): ?>
            <?php if (isset($depts[$code])): ?>
              <span style="padding:.25rem .65rem;border-radius:8px;background:#edf0ff;color:#1a3baa;font-size:.78rem;font-weight:700;"><?= e($depts[$code]) ?></span>
            <?php endif; ?>
          <?php endforeach; ?>
        <?php endforeach; ?>
        <?php if (empty($activeDepts)): ?>
          <span style="color:#94a3b8;font-size:.83rem;font-style:italic;">Aucun département sélectionné — page générique pour tous.</span>
        <?php endif; ?>
      </div>
    </div>
  </section>

  <div class="admin-savebar">
    <button class="admin-btn admin-btn--primary" type="submit">💾 Enregistrer la configuration</button>
  </div>
</form>

<script>
function updateCount() {
  var checked = document.querySelectorAll('input[name^="dept["]:checked').length;
  document.getElementById('count-active').textContent = checked;
}
function toggleAll(val) {
  document.querySelectorAll('input[name^="dept["]').forEach(function(cb) {
    cb.checked = val;
    styleLabel(cb);
  });
  updateCount();
}
function toggleRegion(btn) {
  var region = btn.dataset.region;
  var cbs = document.querySelectorAll('.dept-grid[data-region="'+region+'"] input[type="checkbox"]');
  var allChecked = Array.from(cbs).every(function(cb){ return cb.checked; });
  cbs.forEach(function(cb){ cb.checked = !allChecked; styleLabel(cb); });
  updateCount();
}
function styleLabel(cb) {
  var lbl = cb.closest('label');
  if (!lbl) return;
  if (cb.checked) {
    lbl.style.borderColor = '#2351c5';
    lbl.style.background  = '#edf0ff';
  } else {
    lbl.style.borderColor = '#e2e8f0';
    lbl.style.background  = '#f8fafc';
  }
}
</script>

<?php require __DIR__ . '/partials/footer.php'; ?>
