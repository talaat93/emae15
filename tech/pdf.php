<?php
declare(strict_types=1);
require_once __DIR__.'/../includes/bootstrap.php';
$tech = require_tech_auth();

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { header('Location: '.url_for('tech/index.php')); exit; }

try { $q = db_fetch('SELECT * FROM quotes WHERE id = ? AND technician_id = ?', [$id, (int)$tech['id']]); }
catch (Throwable $ex) { $q = null; }
if (!$q) { header('Location: '.url_for('tech/index.php')); exit; }

$photos     = json_decode((string)($q['tech_photos'] ?? '[]'), true);
if (!is_array($photos)) $photos = [];
$realizable = array_key_exists('tech_realizable', $q) ? $q['tech_realizable'] : null;
$bad_use    = array_key_exists('tech_bad_use',    $q) ? $q['tech_bad_use']    : null;
$e  = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$co = company_name();

function checkBadge(?int $val, bool $positiveIsGreen = true): string {
    if ($val === null) return '<span style="background:#f1f5f9;color:#94a3b8;padding:.18rem .6rem;border-radius:12px;font-size:.75rem;font-weight:700;">N/R</span>';
    $isPos = (int)$val === 1;
    $isGreen = $positiveIsGreen ? $isPos : !$isPos;
    $color = $isGreen ? ['#dcfce7','#16a34a'] : ['#fee2e2','#dc2626'];
    $label = $isPos ? 'Oui' : 'Non';
    return '<span style="background:'.$color[0].';color:'.$color[1].';padding:.18rem .6rem;border-radius:12px;font-size:.75rem;font-weight:700;">'.$label.'</span>';
}
?><!DOCTYPE html>
<html lang="fr"><head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Rapport #<?= $id ?> — <?= $e($co) ?></title>
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif; color: #1e293b; font-size: 13px; background: #f8fafc; }
.wrap { max-width: 820px; margin: 0 auto; padding: 2rem 1.5rem; }

/* Header */
.pdf-header { display: flex; justify-content: space-between; align-items: flex-start; padding-bottom: 1.25rem; border-bottom: 3px solid #F07B1D; margin-bottom: 1.5rem; }
.pdf-logo { font-size: 1.9rem; font-weight: 900; letter-spacing: .07em; color: #0f172a; }
.pdf-logo span { color: #F07B1D; }
.pdf-title { font-size: 1.05rem; font-weight: 700; }
.pdf-sub   { font-size: .78rem; color: #64748b; margin-top: .2rem; }

/* Sections */
.pdf-section { margin-bottom: 1rem; background: #fff; border-radius: 10px; border: 1px solid #e2e8f0; overflow: hidden; }
.pdf-section-head { background: #f8fafc; padding: .55rem 1rem; font-size: .68rem; font-weight: 800; text-transform: uppercase; letter-spacing: .1em; color: #64748b; border-bottom: 1px solid #e2e8f0; }
.pdf-section-body { padding: .85rem 1rem; }

/* Rows */
.pdf-row { display: flex; justify-content: space-between; align-items: flex-start; gap: 1rem; padding: .42rem 0; border-bottom: 1px solid #f1f5f9; font-size: .88rem; }
.pdf-row:last-child { border-bottom: none; }
.pdf-label { color: #64748b; flex-shrink: 0; }
.pdf-value { font-weight: 600; text-align: right; }
.pdf-text  { font-size: .9rem; line-height: 1.7; white-space: pre-wrap; color: #1e293b; }

/* Check rows */
.pdf-check { display: flex; align-items: center; justify-content: space-between; padding: .45rem 0; border-bottom: 1px solid #f1f5f9; font-size: .88rem; gap: 1rem; }
.pdf-check:last-child { border-bottom: none; }

/* Photos */
.pdf-photos { display: grid; grid-template-columns: repeat(3, 1fr); gap: .5rem; margin-top: .5rem; }
.pdf-photo  { aspect-ratio: 1; overflow: hidden; border-radius: 8px; border: 1px solid #e2e8f0; }
.pdf-photo img { width: 100%; height: 100%; object-fit: cover; }

/* Signature */
.pdf-sig { display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; margin-top: 1.5rem; }
.sig-box { border: 1px solid #e2e8f0; border-radius: 8px; padding: 1rem; min-height: 80px; }
.sig-label { font-size: .68rem; font-weight: 800; text-transform: uppercase; letter-spacing: .08em; color: #94a3b8; margin-bottom: .5rem; }

/* Print button */
.print-btn {
  position: fixed; bottom: 1.5rem; right: 1.5rem; z-index: 100;
  background: #F07B1D; color: #fff; border: none; border-radius: 12px;
  padding: .85rem 1.5rem; font-size: .92rem; font-weight: 700; cursor: pointer;
  box-shadow: 0 4px 16px rgba(240,123,29,.4); font-family: inherit;
}
.back-link { display: inline-flex; align-items: center; gap: .4rem; color: #2563eb; font-weight: 600; font-size: .85rem; margin-bottom: 1.25rem; }

@media print {
  .print-btn, .back-link, .no-print { display: none !important; }
  body { background: #fff; }
  .wrap { padding: 1rem; }
  .pdf-section { border: 1px solid #ddd; page-break-inside: avoid; }
}
</style>
</head><body>
<div class="wrap">

  <a href="intervention.php?id=<?= $id ?>" class="back-link no-print">‹ Retour à la fiche</a>

  <div class="pdf-header">
    <div>
      <div class="pdf-logo">EM<span>AE</span></div>
      <div style="font-size:.75rem;color:#64748b;margin-top:.3rem;"><?= $e($co) ?></div>
    </div>
    <div style="text-align:right;">
      <div class="pdf-title">Rapport d'intervention #<?= $id ?></div>
      <div class="pdf-sub">Généré le <?= $e(date('d/m/Y à H:i')) ?></div>
      <?php if (!empty($q['tech_completed_at'])): ?>
      <div class="pdf-sub" style="color:#16a34a;font-weight:700;margin-top:.25rem;">✅ Clôturé le <?= $e(date('d/m/Y', strtotime((string)$q['tech_completed_at']))) ?></div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Client & Site -->
  <div class="pdf-section">
    <div class="pdf-section-head">Client &amp; Site</div>
    <div class="pdf-section-body">
      <div class="pdf-row"><span class="pdf-label">Nom</span><span class="pdf-value"><?= $e($q['full_name']) ?></span></div>
      <div class="pdf-row"><span class="pdf-label">Téléphone</span><span class="pdf-value"><?= $e($q['phone']) ?></span></div>
      <?php if (!empty($q['email'])): ?>
      <div class="pdf-row"><span class="pdf-label">Email</span><span class="pdf-value"><?= $e($q['email']) ?></span></div>
      <?php endif; ?>
      <?php if (!empty($q['address'])): ?>
      <div class="pdf-row"><span class="pdf-label">Adresse</span><span class="pdf-value"><?= $e($q['address']) ?></span></div>
      <?php endif; ?>
      <?php $loc = trim(($q['postal_code']??'').' '.($q['city']??'')); if ($loc): ?>
      <div class="pdf-row"><span class="pdf-label">Ville</span><span class="pdf-value"><?= $e($loc) ?></span></div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Mission -->
  <div class="pdf-section">
    <div class="pdf-section-head">Mission</div>
    <div class="pdf-section-body">
      <?php if (!empty($q['service_type'])): ?>
      <div class="pdf-row"><span class="pdf-label">Service</span><span class="pdf-value"><?= $e($q['service_type']) ?></span></div>
      <?php endif; ?>
      <?php if (!empty($q['intervention_date'])): ?>
      <div class="pdf-row"><span class="pdf-label">Date planifiée</span><span class="pdf-value"><?= $e(date('d/m/Y à H:i', strtotime((string)$q['intervention_date']))) ?></span></div>
      <?php endif; ?>
      <?php if (!empty($q['duration'])): ?>
      <div class="pdf-row"><span class="pdf-label">Durée prévue</span><span class="pdf-value"><?= $e($q['duration']) ?></span></div>
      <?php endif; ?>
      <div class="pdf-row"><span class="pdf-label">Technicien</span><span class="pdf-value"><?= $e($tech['name']) ?></span></div>
      <?php if (!empty($q['amount_ht'])): ?>
      <div class="pdf-row"><span class="pdf-label">Montant HT</span><span class="pdf-value"><?= $e(number_format((float)$q['amount_ht'],2,',',' ')) ?> €</span></div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Demande -->
  <div class="pdf-section">
    <div class="pdf-section-head">Demande initiale du client</div>
    <div class="pdf-section-body"><div class="pdf-text"><?= $e($q['message']) ?></div></div>
  </div>

  <!-- Compte rendu -->
  <div class="pdf-section">
    <div class="pdf-section-head">Compte rendu technique</div>
    <div class="pdf-section-body">
      <?php if (!empty($q['tech_report'])): ?>
        <div class="pdf-text" style="margin-bottom:1rem;"><?= $e($q['tech_report']) ?></div>
      <?php else: ?>
        <div style="color:#94a3b8;font-style:italic;">Aucun rapport texte.</div>
      <?php endif; ?>

      <div style="margin-top:1rem;">
        <div class="pdf-check">
          <span>Intervention réalisable ?</span>
          <?= checkBadge($realizable !== null ? (int)$realizable : null, true) ?>
        </div>
        <div class="pdf-check">
          <span>Panne liée à une mauvaise utilisation ?</span>
          <?= checkBadge($bad_use !== null ? (int)$bad_use : null, false) ?>
        </div>
        <div class="pdf-check">
          <span>Intervention terminée ?</span>
          <?php echo ($q['status'] ?? '') === 'terminé'
            ? '<span style="background:#dcfce7;color:#16a34a;padding:.18rem .6rem;border-radius:12px;font-size:.75rem;font-weight:700;">Oui ✓</span>'
            : '<span style="background:#fef3c7;color:#92400e;padding:.18rem .6rem;border-radius:12px;font-size:.75rem;font-weight:700;">En cours</span>';
          ?>
        </div>
      </div>
    </div>
  </div>

  <!-- Photos -->
  <?php if (!empty($photos)): ?>
  <div class="pdf-section">
    <div class="pdf-section-head">Photos de l'intervention (<?= count($photos) ?>)</div>
    <div class="pdf-section-body">
      <div class="pdf-photos">
        <?php foreach ($photos as $ph): ?>
          <div class="pdf-photo"><img src="<?= $e(asset_url($ph)) ?>" alt="" loading="lazy"></div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <!-- Signatures -->
  <div class="pdf-sig">
    <div class="sig-box">
      <div class="sig-label">Signature technicien</div>
      <div style="font-size:.88rem;font-weight:600;color:#1e293b;margin-top:.5rem;"><?= $e($tech['name']) ?></div>
      <?php if (!empty($q['tech_completed_at'])): ?>
      <div style="font-size:.75rem;color:#64748b;margin-top:.25rem;"><?= $e(date('d/m/Y', strtotime((string)$q['tech_completed_at']))) ?></div>
      <?php endif; ?>
    </div>
    <div class="sig-box">
      <div class="sig-label">Signature client</div>
    </div>
  </div>

</div>

<button class="print-btn no-print" onclick="window.print()">🖨️ Imprimer / PDF</button>

</body></html>
