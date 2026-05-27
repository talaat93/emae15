<?php
declare(strict_types=1);
require_once __DIR__.'/../includes/bootstrap.php';
$tech = require_tech_auth();

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { header('Location: '.url_for('tech/index.php')); exit; }

try { $q = db_fetch('SELECT * FROM quotes WHERE id = ? AND technician_id = ?', [$id, (int)$tech['id']]); }
catch (Throwable $ex) { $q = null; }
if (!$q) { header('Location: '.url_for('tech/index.php')); exit; }

$saved = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $report    = trim((string)($_POST['tech_report'] ?? ''));
    $complete  = !empty($_POST['mark_complete']);
    $realRaw   = $_POST['tech_realizable'] ?? '';
    $badRaw    = $_POST['tech_bad_use']    ?? '';
    $realVal   = $realRaw === '1' ? 1 : ($realRaw === '0' ? 0 : null);
    $badVal    = $badRaw  === '1' ? 1 : ($badRaw  === '0' ? 0 : null);

    $photos = json_decode((string)($q['tech_photos'] ?? '[]'), true);
    if (!is_array($photos)) $photos = [];

    if (!empty($_FILES['photos']['name'][0])) {
        $uploadDir = __DIR__.'/../storage/uploads/interventions/'.$id.'/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0775, true);
        $allowed = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
        foreach ($_FILES['photos']['tmp_name'] as $i => $tmp) {
            if (($_FILES['photos']['error'][$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) continue;
            $mime = mime_content_type($tmp) ?: '';
            if (!isset($allowed[$mime])) continue;
            $fname = 'tech_'.$id.'_'.date('YmdHis').'_'.bin2hex(random_bytes(3)).'.'.$allowed[$mime];
            if (move_uploaded_file($tmp, $uploadDir.$fname)) {
                $photos[] = 'storage/uploads/interventions/'.$id.'/'.$fname;
            }
        }
    }

    $newStatus = $complete ? 'terminé' : 'en cours';
    try {
        if ($complete) {
            db_execute('UPDATE quotes SET tech_report=?,tech_photos=?,tech_realizable=?,tech_bad_use=?,status=?,tech_completed_at=? WHERE id=? AND technician_id=?',
                [$report, json_encode($photos), $realVal, $badVal, 'terminé', date('Y-m-d H:i:s'), $id, (int)$tech['id']]);
        } else {
            db_execute('UPDATE quotes SET tech_report=?,tech_photos=?,tech_realizable=?,tech_bad_use=?,status=? WHERE id=? AND technician_id=?',
                [$report, json_encode($photos), $realVal, $badVal, 'en cours', $id, (int)$tech['id']]);
        }
    } catch (Throwable $ex) {
        if ($complete) {
            db_execute('UPDATE quotes SET tech_report=?,tech_photos=?,status=?,tech_completed_at=? WHERE id=? AND technician_id=?',
                [$report, json_encode($photos), 'terminé', date('Y-m-d H:i:s'), $id, (int)$tech['id']]);
        } else {
            db_execute('UPDATE quotes SET tech_report=?,tech_photos=?,status=? WHERE id=? AND technician_id=?',
                [$report, json_encode($photos), 'en cours', $id, (int)$tech['id']]);
        }
    }
    try { $q = db_fetch('SELECT * FROM quotes WHERE id = ?', [$id]); } catch (Throwable $ex) {}
    $saved = true;
}

$photos     = json_decode((string)($q['tech_photos'] ?? '[]'), true);
if (!is_array($photos)) $photos = [];
$isComplete = ($q['status'] ?? '') === 'terminé';
$realizable = array_key_exists('tech_realizable', $q) ? $q['tech_realizable'] : null;
$bad_use    = array_key_exists('tech_bad_use',    $q) ? $q['tech_bad_use']    : null;
$st         = (string)($q['status'] ?? 'nouveau');

$statusLabels = ['nouveau'=>'Nouveau','contacté'=>'Contacté','planifié'=>'Planifié','en cours'=>'En cours','terminé'=>'Terminé ✓','annulé'=>'Annulé'];
$statusBadge  = ['nouveau'=>'badge-nouveau','contacté'=>'badge-contact','planifié'=>'badge-planifié','en cours'=>'badge-en-cours','terminé'=>'badge-terminé','annulé'=>'badge-annulé'];
$e = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
?><!DOCTYPE html>
<html lang="fr"><head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0,viewport-fit=cover">
<meta name="robots" content="noindex,nofollow">
<title>Fiche #<?= $id ?> — <?= $e(company_name()) ?></title>
<link rel="stylesheet" href="<?= $e(asset_url('assets/css/tech.css')) ?>">
</head><body>

<div class="t-header">
  <a href="index.php" class="t-header-back">‹ Retour</a>
  <div class="t-brand">EM<span>AE</span></div>
  <div style="min-width:64px;text-align:right;">
    <span class="badge <?= $e($statusBadge[$st] ?? 'badge-nouveau') ?>"><?= $e($statusLabels[$st] ?? $st) ?></span>
  </div>
</div>

<div class="t-main" style="padding-bottom:<?= $isComplete ? '1.5rem' : '8rem' ?>;">

  <?php if ($saved): ?>
    <div class="t-flash-ok">✅ <?= $isComplete ? 'Intervention clôturée avec succès.' : 'Rapport enregistré.' ?></div>
  <?php endif; ?>

  <div style="margin-bottom:.85rem;">
    <div style="font-size:1.15rem;font-weight:800;color:var(--navy);">Fiche #<?= $id ?></div>
    <div style="font-size:.8rem;color:var(--t2);">Créée le <?= $e(date('d/m/Y', strtotime((string)$q['created_at']))) ?></div>
  </div>

  <!-- Client & Site -->
  <div class="t-card">
    <div class="t-card-body">
      <div class="t-section-title">👤 Client &amp; Site</div>
      <div class="t-info-row">
        <span class="t-info-label">Nom</span>
        <span class="t-info-value"><?= $e($q['full_name']) ?></span>
      </div>
      <?php if (!empty($q['address'])): ?>
      <div class="t-info-row">
        <span class="t-info-label">Adresse</span>
        <span class="t-info-value"><?= $e($q['address']) ?></span>
      </div>
      <?php endif; ?>
      <?php $loc = trim(($q['postal_code']??'').' '.($q['city']??'')); if ($loc): ?>
      <div class="t-info-row">
        <span class="t-info-label">Ville</span>
        <span class="t-info-value"><?= $e($loc) ?></span>
      </div>
      <?php endif; ?>
      <div style="margin-top:.85rem;">
        <a href="tel:<?= $e(preg_replace('/\s+/','',(string)$q['phone'])) ?>"
           class="t-btn t-btn-primary" style="display:inline-flex;width:auto;padding:.6rem 1.25rem;">
          📞 <?= $e($q['phone']) ?>
        </a>
      </div>
    </div>
  </div>

  <!-- Mission -->
  <?php if (!empty($q['service_type']) || !empty($q['intervention_date']) || !empty($q['duration']) || !empty($q['amount_ht'])): ?>
  <div class="t-card">
    <div class="t-card-body">
      <div class="t-section-title">⚙️ Mission</div>
      <?php if (!empty($q['service_type'])): ?>
      <div class="t-info-row"><span class="t-info-label">Service</span><span class="t-info-value"><?= $e($q['service_type']) ?></span></div>
      <?php endif; ?>
      <?php if (!empty($q['intervention_date'])): ?>
      <div class="t-info-row">
        <span class="t-info-label">Planifié le</span>
        <span class="t-info-value" style="color:var(--blue);font-weight:700;"><?= $e(date('d/m/Y à H:i', strtotime((string)$q['intervention_date']))) ?></span>
      </div>
      <?php endif; ?>
      <?php if (!empty($q['duration'])): ?>
      <div class="t-info-row"><span class="t-info-label">Durée prévue</span><span class="t-info-value"><?= $e($q['duration']) ?></span></div>
      <?php endif; ?>
      <?php if (!empty($q['amount_ht'])): ?>
      <div class="t-info-row"><span class="t-info-label">Montant HT</span><span class="t-info-value"><?= $e(number_format((float)$q['amount_ht'],2,',',' ')) ?> €</span></div>
      <?php endif; ?>
    </div>
  </div>
  <?php endif; ?>

  <!-- Demande client -->
  <div class="t-card">
    <div class="t-card-body">
      <div class="t-section-title">📝 Demande du client</div>
      <div class="t-report-box"><?= $e($q['message']) ?></div>
    </div>
  </div>

  <?php if ($isComplete): ?>
  <!-- Read-only photos -->
  <?php if (!empty($photos)): ?>
  <div class="t-card">
    <div class="t-card-body">
      <div class="t-section-title" style="justify-content:space-between;">
        <span>📸 Photos (<?= count($photos) ?>)</span>
        <a href="pdf.php?id=<?= $id ?>" target="_blank" style="font-size:.75rem;color:var(--blue);font-weight:600;text-transform:none;letter-spacing:0;">Voir rapport PDF →</a>
      </div>
      <div class="t-photo-grid">
        <?php foreach ($photos as $ph): ?>
          <div class="t-photo-item"><a href="<?= $e(asset_url($ph)) ?>" target="_blank"><img src="<?= $e(asset_url($ph)) ?>" alt="" loading="lazy"></a></div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <!-- Read-only rapport -->
  <div class="t-card">
    <div class="t-card-body">
      <div class="t-section-title">📋 Compte rendu</div>
      <?php if (!empty($q['tech_report'])): ?>
        <div class="t-report-box"><?= $e($q['tech_report']) ?></div>
      <?php else: ?><div style="color:var(--t3);">Aucun rapport texte.</div><?php endif; ?>

      <?php if ($realizable !== null || $bad_use !== null): ?>
      <div class="t-checklist" style="margin-top:1rem;">
        <?php if ($realizable !== null): ?>
        <div class="t-check-row">
          <span class="t-check-label">Intervention réalisable</span>
          <span class="badge <?= (int)$realizable ? 'badge-terminé' : 'badge-annulé' ?>"><?= (int)$realizable ? 'Oui ✓' : 'Non' ?></span>
        </div>
        <?php endif; ?>
        <?php if ($bad_use !== null): ?>
        <div class="t-check-row">
          <span class="t-check-label">Mauvaise utilisation</span>
          <span class="badge <?= (int)$bad_use ? 'badge-annulé' : 'badge-terminé' ?>"><?= (int)$bad_use ? 'Oui' : 'Non ✓' ?></span>
        </div>
        <?php endif; ?>
        <div class="t-check-row">
          <span class="t-check-label">Intervention terminée</span>
          <span class="badge badge-terminé">Oui ✓</span>
        </div>
      </div>
      <?php endif; ?>

      <?php if (!empty($q['tech_completed_at'])): ?>
        <div class="t-done-stamp">✅ Clôturée le <?= $e(date('d/m/Y à H:i', strtotime((string)$q['tech_completed_at']))) ?></div>
      <?php endif; ?>
    </div>
  </div>

  <a href="pdf.php?id=<?= $id ?>" target="_blank" class="t-btn t-btn-pdf" style="margin-top:.25rem;">📄 Rapport PDF</a>

  <?php else: ?>
  <!-- Editable rapport form -->
  <form method="post" enctype="multipart/form-data" id="form-rapport">
    <input type="hidden" name="action"          value="report">
    <input type="hidden" name="tech_realizable" id="inp-realizable" value="<?= $realizable !== null ? (int)$realizable : '' ?>">
    <input type="hidden" name="tech_bad_use"    id="inp-baduse"     value="<?= $bad_use    !== null ? (int)$bad_use    : '' ?>">

    <!-- Photos -->
    <div class="t-card">
      <div class="t-card-body">
        <div class="t-section-title">📸 Photos de l'intervention</div>
        <?php if (!empty($photos)): ?>
        <div class="t-photo-grid" style="margin-bottom:.75rem;">
          <?php foreach ($photos as $ph): ?>
            <div class="t-photo-item"><img src="<?= $e(asset_url($ph)) ?>" alt="" loading="lazy"></div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <input type="file" id="photo-input" name="photos[]" multiple accept="image/*" capture="environment"
               style="display:none;" onchange="updatePhotoLabel(this)">
        <div class="t-photo-add" onclick="document.getElementById('photo-input').click();">
          <span style="font-size:1.35rem;">📷</span>
          <span id="photo-label">Ajouter des photos</span>
        </div>
      </div>
    </div>

    <!-- Compte rendu -->
    <div class="t-card">
      <div class="t-card-body">
        <div class="t-section-title">📋 Compte rendu</div>
        <div class="t-field">
          <label>Panne constatée &amp; travaux réalisés</label>
          <textarea name="tech_report" placeholder="Décrivez la panne, les actions réalisées, les pièces remplacées…"><?= $e($q['tech_report'] ?? '') ?></textarea>
        </div>

        <div class="t-section-title" style="margin-top:1.1rem;">Checklist</div>
        <div class="t-checklist">
          <div class="t-check-row">
            <span class="t-check-label">Intervention réalisable ?</span>
            <div class="t-check-toggle">
              <button type="button" class="t-check-btn <?= ($realizable !== null && (int)$realizable===1) ? 'active-yes' : '' ?>" onclick="setCheck('realizable','1',this)">Oui</button>
              <button type="button" class="t-check-btn <?= ($realizable !== null && (int)$realizable===0) ? 'active-no'  : '' ?>" onclick="setCheck('realizable','0',this)">Non</button>
            </div>
          </div>
          <div class="t-check-row">
            <span class="t-check-label">Panne liée à une mauvaise utilisation ?</span>
            <div class="t-check-toggle">
              <button type="button" class="t-check-btn <?= ($bad_use !== null && (int)$bad_use===1) ? 'active-yes' : '' ?>" onclick="setCheck('baduse','1',this)">Oui</button>
              <button type="button" class="t-check-btn <?= ($bad_use !== null && (int)$bad_use===0) ? 'active-no'  : '' ?>" onclick="setCheck('baduse','0',this)">Non</button>
            </div>
          </div>
          <div class="t-check-row">
            <span class="t-check-label">Intervention terminée ?</span>
            <div class="t-check-toggle">
              <button type="button" class="t-check-btn" onclick="submitComplete()">Oui</button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </form>

  <!-- Action bar -->
  <div class="t-action-bar">
    <a href="pdf.php?id=<?= $id ?>" target="_blank" class="t-btn t-btn-pdf" style="flex:0 0 auto;width:auto;padding:.75rem 1rem;">📄</a>
    <button type="submit" form="form-rapport" class="t-btn t-btn-outline">💾 Enregistrer</button>
    <button type="button" class="t-btn t-btn-complete" onclick="submitComplete()" style="flex:1.3;">✅ Clôturer</button>
  </div>
  <?php endif; ?>

</div>

<script>
function setCheck(field, val, btn) {
  var inp = document.getElementById('inp-' + field);
  var btns = btn.closest('.t-check-toggle').querySelectorAll('.t-check-btn');
  btns.forEach(function(b){ b.classList.remove('active-yes','active-no'); });
  if (inp.value === val) { inp.value = ''; return; }
  inp.value = val;
  btn.classList.add(val === '1' ? 'active-yes' : 'active-no');
}
function submitComplete() {
  if (!confirm('Confirmer la clôture de l\'intervention ?')) return;
  var form = document.getElementById('form-rapport');
  var inp = document.createElement('input');
  inp.type = 'hidden'; inp.name = 'mark_complete'; inp.value = '1';
  form.appendChild(inp);
  form.submit();
}
function updatePhotoLabel(input) {
  var lbl = document.getElementById('photo-label');
  if (input.files && input.files.length > 0)
    lbl.textContent = input.files.length + ' photo(s) sélectionnée(s)';
}
</script>
</body></html>
