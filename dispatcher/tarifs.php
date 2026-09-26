<?php
declare(strict_types=1);
$pageTitle   = 'Tarifs';
$dispSection = 'tarifs';
require __DIR__.'/partials/header.php';

$cats  = price_categories();
$units = price_units();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string)($_POST['action'] ?? '');
    $num = static fn($v) => round((float)str_replace([',', ' ', "\u{00A0}"], ['.', '', ''], (string)$v), 2);
    $cat  = array_key_exists($_POST['category'] ?? '', $cats) ? (string)$_POST['category'] : 'commun';
    $unit = array_key_exists($_POST['unit'] ?? '', $units) ? (string)$_POST['unit'] : 'forfait';
    $label = trim((string)($_POST['label'] ?? ''));
    $code  = strtoupper(preg_replace('/[^A-Za-z0-9-]/', '', (string)($_POST['code'] ?? '')));

    if ($action === 'add') {
        if ($code === '' || $label === '') {
            flash('error', 'Le code et la désignation sont obligatoires.');
        } elseif (db_fetch('SELECT id FROM price_grid WHERE code = ?', [$code])) {
            flash('error', 'Le code '.$code.' existe déjà.');
        } else {
            $max = (int)(db_fetch('SELECT COALESCE(MAX(sort_order),0) AS m FROM price_grid WHERE category = ?', [$cat])['m'] ?? 0);
            db_execute('INSERT INTO price_grid (category, code, label, unit, price_ht, is_percent, sort_order, updated_at) VALUES (?,?,?,?,?,?,?,NOW())',
                [$cat, $code, $label, $unit, $num($_POST['price_ht'] ?? 0), $unit === 'pourcent' ? 1 : 0, $max + 10]);
            integration_log('audit', 'tarif ajouté '.$code, ['dispatcher' => (int)$disp['id']]);
            flash('success', 'Tarif '.$code.' ajouté.');
        }
    } elseif ($action === 'update') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0 && $label !== '') {
            db_execute('UPDATE price_grid SET category = ?, label = ?, unit = ?, price_ht = ?, is_percent = ?, sort_order = ?, updated_at = NOW() WHERE id = ?',
                [$cat, $label, $unit, $num($_POST['price_ht'] ?? 0), $unit === 'pourcent' ? 1 : 0, (int)($_POST['sort_order'] ?? 0), $id]);
            integration_log('audit', 'tarif modifié #'.$id, ['dispatcher' => (int)$disp['id']]);
            flash('success', 'Tarif enregistré.');
        }
    } elseif ($action === 'toggle') {
        $id = (int)($_POST['id'] ?? 0);
        db_execute('UPDATE price_grid SET active = 1 - active, updated_at = NOW() WHERE id = ?', [$id]);
        flash('success', 'Tarif mis à jour.');
    }
    redirect_to('dispatcher/tarifs.php'.(isset($_POST['category']) ? '#cat-'.$cat : ''));
}

$rows = price_grid_rows(false);
$byCat = [];
foreach ($rows as $r) $byCat[(string)$r['category']][] = $r;
$csrf = csrf_token();
?>
<style>
.tarif-table { table-layout: fixed; min-width: 860px; }
.tarif-table td { padding: .45rem .6rem; }
.tarif-table input, .tarif-table select { width: 100%; padding: .35rem .5rem; font-size: .84rem; border: 1px solid var(--d-border-2); border-radius: 6px; background: #fff; font-family: inherit; }
.tarif-off td { opacity: .5; }
.tarif-code { font-family: ui-monospace, Menlo, monospace; font-size: .8rem; font-weight: 600; }
</style>
<div class="d-topbar">
  <div>
    <button class="d-menu-toggle" id="d-menu-toggle" aria-label="Menu">☰</button>
    <div>
      <div class="d-topbar-title">Grille tarifaire</div>
      <div class="d-topbar-sub">Prix HT utilisés pour les estimations, les rapports et les factures</div>
    </div>
  </div>
  <div class="d-topbar-actions"><a href="#ajout" class="d-btn d-btn--primary d-btn--sm">+ Nouveau tarif</a></div>
</div>

<div class="d-content">
  <div class="d-flash" style="background:var(--d-card);border:1px solid var(--d-border);color:var(--d-t2);">
    Ces prix sont la seule source des montants : Claude et les techniciens choisissent des <b>codes</b> et des quantités,
    les totaux sont toujours recalculés ici. Les majorations (%) s'appliquent au total des autres lignes.
    TVA : 10 % pour un particulier dont le logement a plus de 2 ans (attestation simplifiée), sinon 20 % — modifiable sur chaque fiche.
  </div>

  <?php foreach ($cats as $ck => $cl): if (empty($byCat[$ck])) continue; ?>
  <div class="d-card" id="cat-<?= e($ck) ?>" style="margin-bottom:1.1rem;overflow-x:auto;">
    <div class="d-card-head"><div class="d-card-title"><?= e($cl) ?></div><span style="font-size:.8rem;color:var(--d-t2);"><?= count($byCat[$ck]) ?> tarif<?= count($byCat[$ck]) > 1 ? 's' : '' ?></span></div>
    <table class="d-table tarif-table">
      <thead><tr><th style="width:140px;">Code</th><th>Désignation</th><th style="width:110px;">Unité</th><th style="width:100px;">Prix HT</th><th style="width:72px;">Ordre</th><th style="width:235px;"></th></tr></thead>
      <tbody>
      <?php foreach ($byCat[$ck] as $r): $fid = 'f'.(int)$r['id']; ?>
        <tr class="<?= (int)$r['active'] ? '' : 'tarif-off' ?>">
          <td class="tarif-code"><?= e($r['code']) ?><?php if (!(int)$r['active']): ?><div style="font-family:inherit;font-weight:400;color:var(--d-danger);font-size:.72rem;">désactivé</div><?php endif; ?></td>
          <td><input form="<?= $fid ?>" name="label" value="<?= e($r['label']) ?>" required></td>
          <td><select form="<?= $fid ?>" name="unit"><?php foreach ($units as $uk => $ul): ?><option value="<?= e($uk) ?>" <?= $r['unit'] === $uk ? 'selected' : '' ?>><?= e($ul) ?></option><?php endforeach; ?></select></td>
          <td><input form="<?= $fid ?>" name="price_ht" inputmode="decimal" value="<?= e(number_format((float)$r['price_ht'], 2, ',', '')) ?>"></td>
          <td><input form="<?= $fid ?>" name="sort_order" inputmode="numeric" value="<?= (int)$r['sort_order'] ?>"></td>
          <td style="white-space:nowrap;">
            <form id="<?= $fid ?>" method="post" style="display:inline;">
              <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
              <input type="hidden" name="action" value="update">
              <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
              <input type="hidden" name="category" value="<?= e($r['category']) ?>">
              <button type="submit" class="d-btn d-btn--sm">Enregistrer</button>
            </form>
            <form method="post" style="display:inline;">
              <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
              <input type="hidden" name="action" value="toggle">
              <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
              <input type="hidden" name="category" value="<?= e($r['category']) ?>">
              <button type="submit" class="d-btn d-btn--ghost d-btn--sm"><?= (int)$r['active'] ? 'Désactiver' : 'Réactiver' ?></button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endforeach; ?>

  <form method="post" class="d-card" id="ajout" style="max-width:820px;">
    <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
    <input type="hidden" name="action" value="add">
    <div class="d-card-head"><div class="d-card-title">Ajouter un tarif</div></div>
    <div class="d-card-body">
      <div class="d-grid-2">
        <div class="d-field"><label class="req" for="n-cat">Métier</label>
          <select id="n-cat" name="category"><?php foreach ($cats as $ck => $cl): ?><option value="<?= e($ck) ?>"><?= e($cl) ?></option><?php endforeach; ?></select></div>
        <div class="d-field"><label class="req" for="n-code">Code</label><input id="n-code" name="code" required placeholder="Ex. : PLB-WC" style="text-transform:uppercase;"></div>
      </div>
      <div class="d-field"><label class="req" for="n-label">Désignation</label><input id="n-label" name="label" required placeholder="Ex. : Remplacement cuvette WC (fournie)"></div>
      <div class="d-grid-2">
        <div class="d-field"><label for="n-unit">Unité</label>
          <select id="n-unit" name="unit"><?php foreach ($units as $uk => $ul): ?><option value="<?= e($uk) ?>"><?= e($ul) ?></option><?php endforeach; ?></select></div>
        <div class="d-field"><label class="req" for="n-price">Prix HT (ou pourcentage pour une majoration)</label><input id="n-price" name="price_ht" inputmode="decimal" required placeholder="0,00"></div>
      </div>
      <button type="submit" class="d-btn d-btn--primary">Ajouter</button>
    </div>
  </form>
</div>
<?php require __DIR__.'/partials/footer.php'; ?>
