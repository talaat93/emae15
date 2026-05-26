<?php
declare(strict_types=1);
require_once __DIR__.'/../includes/bootstrap.php';
$tech = require_tech_auth();

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { header('Location: '.url_for('tech/index.php')); exit; }

try { $q = db_fetch('SELECT * FROM quotes WHERE id = ? AND technician_id = ?', [$id, (int)$tech['id']]); }
catch (Throwable $e) { $q = null; }
if (!$q) { header('Location: '.url_for('tech/index.php')); exit; }

$saved = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim((string)($_POST['action'] ?? 'report'));

    if ($action === 'report') {
        $report    = trim((string)($_POST['tech_report'] ?? ''));
        $complete  = !empty($_POST['mark_complete']);

        // Gestion photos
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

        if ($complete) {
            db_execute('UPDATE quotes SET tech_report=?, tech_photos=?, status=?, tech_completed_at=? WHERE id=? AND technician_id=?',
                [$report, json_encode($photos), 'terminé', date('Y-m-d H:i:s'), $id, (int)$tech['id']]);
        } else {
            db_execute('UPDATE quotes SET tech_report=?, tech_photos=?, status=? WHERE id=? AND technician_id=?',
                [$report, json_encode($photos), 'en cours', $id, (int)$tech['id']]);
        }

        try { $q = db_fetch('SELECT * FROM quotes WHERE id = ?', [$id]); } catch (Throwable $e) {}
        $saved = true;
    }
}

$photos     = json_decode((string)($q['tech_photos'] ?? '[]'), true);
if (!is_array($photos)) $photos = [];
$isComplete = ($q['status'] ?? '') === 'terminé';
$statusLabels = ['nouveau'=>'Nouveau','contacté'=>'Contacté','planifié'=>'Planifié','en cours'=>'En cours','terminé'=>'Terminé ✓','annulé'=>'Annulé'];
$statusClass  = ['nouveau'=>'badge-nouveau','contacté'=>'badge-contact','planifié'=>'badge-planifié','en cours'=>'badge-en-cours','terminé'=>'badge-terminé','annulé'=>'badge-annulé'];
$st  = (string)($q['status'] ?? 'nouveau');
$e   = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
?><!DOCTYPE html>
<html lang="fr"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0,viewport-fit=cover">
<title>Fiche #<?= $id ?> — <?= $e(company_name()) ?></title>
<link rel="stylesheet" href="<?= $e(asset_url('assets/css/tech.css')) ?>">
</head><body>

<div class="tech-header">
  <div><a href="index.php" style="color:#F07B1D;font-weight:700;font-size:1rem;">← Mes fiches</a></div>
  <div class="tech-header-brand">EM<span>AE</span></div>
  <div><a href="logout.php" class="tech-header-logout" style="font-size:.75rem;">Déco</a></div>
</div>

<div class="tech-main">

  <?php if ($saved): ?><div class="tech-flash-ok">✅ <?= $isComplete ? 'Intervention marquée comme terminée.' : 'Rapport enregistré.' ?></div><?php endif; ?>

  <!-- En-tête fiche -->
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.75rem;">
    <div class="tech-title" style="margin-bottom:0;">Fiche #<?= $id ?></div>
    <span class="badge <?= $e($statusClass[$st] ?? 'badge-nouveau') ?>"><?= $e($statusLabels[$st] ?? $st) ?></span>
  </div>

  <!-- Infos intervention -->
  <div class="tech-card">
    <div class="tech-card-body">
      <div class="tech-section-title">📍 Lieu d'intervention</div>
      <?php if (!empty($q['address'])): ?><div style="font-weight:700;font-size:.95rem;"><?= $e($q['address']) ?></div><?php endif; ?>
      <?php $loc = trim(($q['postal_code']??'').' '.($q['city']??'')); if ($loc): ?><div style="color:#445;"><?= $e($loc) ?></div><?php endif; ?>

      <div class="tech-section-title" style="margin-top:.85rem;">👤 Contact client</div>
      <div style="font-weight:700;margin-bottom:.3rem;"><?= $e($q['full_name']) ?></div>
      <a href="tel:<?= $e(preg_replace('/\s+/','',(string)$q['phone'])) ?>" style="display:inline-flex;align-items:center;gap:.4rem;background:#F07B1D;color:#fff;padding:.55rem 1.1rem;border-radius:10px;font-weight:700;font-size:1rem;">📞 <?= $e($q['phone']) ?></a>

      <?php if (!empty($q['intervention_date']) || !empty($q['service_type']) || !empty($q['duration'])): ?>
      <div class="tech-section-title" style="margin-top:.85rem;">⚙️ Mission</div>
      <?php if (!empty($q['service_type'])): ?>
        <div class="tech-info-row"><div class="tech-info-label">Service</div><div class="tech-info-value"><?= $e($q['service_type']) ?></div></div>
      <?php endif; ?>
      <?php if (!empty($q['intervention_date'])): ?>
        <div class="tech-info-row"><div class="tech-info-label">Date</div><div class="tech-info-value" style="color:#1a3baa;"><?= $e(date('d/m/Y à H:i', strtotime((string)$q['intervention_date']))) ?></div></div>
      <?php endif; ?>
      <?php if (!empty($q['duration'])): ?>
        <div class="tech-info-row"><div class="tech-info-label">Durée prévue</div><div class="tech-info-value"><?= $e($q['duration']) ?></div></div>
      <?php endif; ?>
      <?php endif; ?>

      <div class="tech-section-title" style="margin-top:.85rem;">📝 Demande du client</div>
      <div style="background:#f8faff;border-left:3px solid #F07B1D;border-radius:6px;padding:.75rem;font-size:.88rem;color:#333;line-height:1.7;white-space:pre-wrap;"><?= $e($q['message']) ?></div>
    </div>
  </div>

  <!-- Rapport technicien -->
  <?php if (!$isComplete): ?>
  <form method="post" enctype="multipart/form-data">
    <input type="hidden" name="action" value="report">
    <div class="tech-card">
      <div class="tech-card-body">
        <div class="tech-section-title">🔧 Mon rapport</div>
        <div class="tech-field">
          <label>Panne constatée &amp; travaux réalisés</label>
          <textarea name="tech_report" placeholder="Décrivez la panne, les actions réalisées, les pièces remplacées..."><?= $e($q['tech_report'] ?? '') ?></textarea>
        </div>

        <div class="tech-section-title">📸 Photos de l'intervention</div>
        <?php if (!empty($photos)): ?>
        <div class="tech-photo-grid" style="margin-bottom:.75rem;">
          <?php foreach ($photos as $ph): ?>
            <div class="tech-photo-item"><img src="<?= $e(asset_url($ph)) ?>" alt="photo" loading="lazy"></div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <label style="display:block;" onclick="document.getElementById('pi').click();">
          <div class="tech-upload-area">
            <input type="file" id="pi" name="photos[]" multiple accept="image/*" capture="environment" onchange="updateLabel(this)" style="position:absolute;opacity:0;width:0;height:0;">
            <div class="tech-upload-label" id="ul">
              📷 Appuyer pour ajouter des photos<br><strong>Appareil photo ou galerie</strong>
            </div>
          </div>
        </label>

        <button class="tech-btn tech-btn-primary" type="submit">💾 Enregistrer le rapport</button>
        <button class="tech-btn tech-btn-complete" type="submit" name="mark_complete" value="1"
                onclick="return confirm('Confirmer la fin de l\'intervention ?');">✅ Terminer l'intervention</button>
      </div>
    </div>
  </form>
  <?php else: ?>
  <!-- Lecture seule si terminé -->
  <div class="tech-card">
    <div class="tech-card-body">
      <div class="tech-section-title">🔧 Rapport déposé</div>
      <?php if (!empty($q['tech_report'])): ?>
        <div style="background:#f8faff;border-radius:8px;padding:.85rem;font-size:.9rem;line-height:1.7;white-space:pre-wrap;"><?= $e($q['tech_report']) ?></div>
      <?php else: ?><div style="color:#8a9ab8;">Aucun rapport.</div><?php endif; ?>

      <?php if (!empty($photos)): ?>
        <div class="tech-section-title" style="margin-top:.85rem;">📸 Photos</div>
        <div class="tech-photo-grid">
          <?php foreach ($photos as $ph): ?>
            <div class="tech-photo-item"><a href="<?= $e(asset_url($ph)) ?>" target="_blank"><img src="<?= $e(asset_url($ph)) ?>" alt="photo" loading="lazy"></a></div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <?php if (!empty($q['tech_completed_at'])): ?>
        <div style="margin-top:.85rem;color:#14653a;font-size:.82rem;font-weight:700;">
          ✅ Terminée le <?= $e(date('d/m/Y à H:i', strtotime((string)$q['tech_completed_at']))) ?>
        </div>
      <?php endif; ?>
    </div>
  </div>
  <?php endif; ?>

</div>
<script>
function updateLabel(input){
  var l=document.getElementById('ul');
  if(input.files&&input.files.length>0) l.textContent=input.files.length+' photo(s) sélectionnée(s)';
}
</script>
</body></html>
