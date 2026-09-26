<?php
declare(strict_types=1);
/** Profil d'assignation des techniciens : métiers, point de départ, capacité et horaires. */
$pageTitle   = 'Techniciens';
$dispSection = 'techniciens';
require __DIR__.'/partials/header.php';

$catsCfg = intervention_category_config();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $id = (int)($_POST['id'] ?? 0);
    $t = $id > 0 ? db_fetch('SELECT * FROM technicians WHERE id = ?', [$id]) : null;
    if ($t) {
        $skills = array_values(array_intersect(array_keys($catsCfg), (array)($_POST['skills'] ?? [])));
        $addr = mb_substr(trim((string)($_POST['base_address'] ?? '')), 0, 255);
        $lat = $t['base_lat']; $lng = $t['base_lng'];
        $geoMsg = '';
        if ($addr === '') { $lat = $lng = null; }
        elseif ($addr !== (string)$t['base_address'] || !geo_valid($lat, $lng)) {
            $g = geocode_address($addr);
            if ($g['lat'] !== null) { $lat = $g['lat']; $lng = $g['lng']; }
            else { $lat = $lng = null; $geoMsg = ' Adresse non localisée : vérifiez-la (la distance ne pourra pas être calculée).'; }
        }
        $time = static fn($v, $d) => preg_match('/^\d{1,2}:\d{2}$/', (string)$v) ? $v.':00' : $d;
        db_execute('UPDATE technicians SET skills = ?, base_address = ?, base_lat = ?, base_lng = ?, max_per_day = ?, work_start = ?, work_end = ? WHERE id = ?', [
            $skills ? implode(',', $skills) : null, $addr !== '' ? $addr : null, $lat, $lng,
            max(1, min(20, (int)($_POST['max_per_day'] ?? 6))),
            $time($_POST['work_start'] ?? '', '08:00:00'), $time($_POST['work_end'] ?? '', '18:00:00'), $id,
        ]);
        integration_log('audit', 'profil technicien modifié #'.$id, ['dispatcher' => (int)$disp['id']]);
        flash($geoMsg ? 'error' : 'success', 'Profil de '.$t['name'].' enregistré.'.$geoMsg);
    }
    redirect_to('dispatcher/techniciens.php');
}

$techs = assign_technicians();
$csrf = csrf_token();
?>
<div class="d-topbar">
  <div>
    <button class="d-menu-toggle" id="d-menu-toggle" aria-label="Menu">☰</button>
    <div>
      <div class="d-topbar-title">Techniciens</div>
      <div class="d-topbar-sub">Métiers, point de départ et capacité : utilisés pour suggérer le bon technicien</div>
    </div>
  </div>
</div>
<div class="d-content">
  <div class="d-flash" style="background:var(--d-card);border:1px solid var(--d-border);color:var(--d-t2);">
    Les comptes (création, mot de passe) se gèrent dans l'administration. Un technicien sans métier coché est considéré comme polyvalent.
  </div>
  <?php if (!$techs): ?><div class="d-empty">Aucun technicien actif.</div><?php endif; ?>
  <div class="tech-prof-grid">
  <?php foreach ($techs as $t): $sk = tech_skills($t); ?>
    <form method="post" class="d-card">
      <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
      <input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
      <div class="d-card-head"><div class="d-card-title"><?= e($t['name']) ?></div><span style="font-size:.8rem;color:var(--d-t2);"><?= e((string)$t['phone']) ?></span></div>
      <div class="d-card-body">
        <div class="d-field"><label>Métiers</label>
          <div style="display:flex;flex-wrap:wrap;gap:.35rem .9rem;">
            <?php foreach ($catsCfg as $ck => $cc): ?>
              <label style="font-weight:500;display:flex;gap:.3rem;align-items:center;"><input type="checkbox" name="skills[]" value="<?= e($ck) ?>" <?= in_array($ck, $sk, true) ? 'checked' : '' ?>> <?= e($cc['label']) ?></label>
            <?php endforeach; ?>
          </div>
        </div>
        <div class="d-field"><label for="ba<?= (int)$t['id'] ?>">Point de départ (adresse)</label>
          <input id="ba<?= (int)$t['id'] ?>" name="base_address" value="<?= e((string)$t['base_address']) ?>" placeholder="Ex. : 10 rue de Paris, 93100 Montreuil">
          <div style="font-size:.76rem;margin-top:.2rem;color:<?= geo_valid($t['base_lat'], $t['base_lng']) ? 'var(--d-success)' : 'var(--d-t3)' ?>;"><?= geo_valid($t['base_lat'], $t['base_lng']) ? 'Adresse localisée' : 'Non localisée' ?></div>
        </div>
        <div class="d-grid-2" style="grid-template-columns:repeat(3,1fr);">
          <div class="d-field"><label for="mx<?= (int)$t['id'] ?>">Interventions / jour</label><input id="mx<?= (int)$t['id'] ?>" name="max_per_day" type="number" min="1" max="20" value="<?= (int)$t['max_per_day'] ?>"></div>
          <div class="d-field"><label for="ws<?= (int)$t['id'] ?>">Début</label><input id="ws<?= (int)$t['id'] ?>" name="work_start" type="time" value="<?= e(substr((string)$t['work_start'], 0, 5)) ?>"></div>
          <div class="d-field"><label for="we<?= (int)$t['id'] ?>">Fin</label><input id="we<?= (int)$t['id'] ?>" name="work_end" type="time" value="<?= e(substr((string)$t['work_end'], 0, 5)) ?>"></div>
        </div>
        <button type="submit" class="d-btn d-btn--sm d-btn--primary">Enregistrer</button>
      </div>
    </form>
  <?php endforeach; ?>
  </div>
</div>
<style>.tech-prof-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(360px, 1fr)); gap: 1rem; } @media (max-width: 500px) { .tech-prof-grid { grid-template-columns: 1fr; } }</style>
<?php require __DIR__.'/partials/footer.php'; ?>
