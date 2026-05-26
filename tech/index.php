<?php
declare(strict_types=1);
require_once __DIR__.'/../includes/bootstrap.php';
$tech = require_tech_auth();

$statusLabels = ['nouveau'=>'Nouveau','contacté'=>'Contacté','planifié'=>'Planifié','en cours'=>'En cours','terminé'=>'Terminé ✓','annulé'=>'Annulé'];
$statusClass  = ['nouveau'=>'badge-nouveau','contacté'=>'badge-contact','planifié'=>'badge-planifié','en cours'=>'badge-en-cours','terminé'=>'badge-terminé','annulé'=>'badge-annulé'];

try {
    $interventions = db_fetch_all(
        "SELECT * FROM quotes WHERE technician_id = ? AND archived = 0 ORDER BY CASE WHEN status='en cours' THEN 0 WHEN status='planifié' THEN 1 WHEN status='contacté' THEN 2 WHEN status='nouveau' THEN 3 ELSE 4 END, intervention_date ASC, created_at DESC",
        [(int)$tech['id']]
    );
} catch (Throwable $e) { $interventions = []; }
?><!DOCTYPE html>
<html lang="fr"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0,viewport-fit=cover">
<title>Mes interventions — <?= htmlspecialchars(company_name(),ENT_QUOTES) ?></title>
<link rel="stylesheet" href="<?= htmlspecialchars(asset_url('assets/css/tech.css'),ENT_QUOTES) ?>">
</head><body>

<div class="tech-header">
  <div class="tech-header-brand">EM<span>AE</span></div>
  <div>
    <div class="tech-header-user">👷 <?= htmlspecialchars($tech['name'],ENT_QUOTES) ?></div>
    <a href="logout.php" class="tech-header-logout">Déconnexion</a>
  </div>
</div>

<div class="tech-main">
  <div class="tech-title">Mes interventions (<?= count($interventions) ?>)</div>

  <?php if (empty($interventions)): ?>
    <div class="tech-card">
      <div class="tech-card-body tech-empty">
        <div class="tech-empty-icon">📋</div>
        <div>Aucune intervention assignée pour le moment.</div>
      </div>
    </div>
  <?php endif; ?>

  <?php foreach ($interventions as $q):
    $st    = (string)($q['status'] ?? 'nouveau');
    $cls   = $statusClass[$st] ?? 'badge-nouveau';
    $label = $statusLabels[$st] ?? $st;
    $loc   = trim(($q['address'] ?? '').', '.($q['postal_code'] ?? '').' '.($q['city'] ?? ''));
    $loc   = trim($loc, ', ');
  ?>
  <div class="tech-card">
    <div class="tech-card-head">
      <div>
        <div class="tech-card-id">#<?= (int)$q['id'] ?> · <?= htmlspecialchars(date('d/m/Y', strtotime((string)$q['created_at'])),ENT_QUOTES) ?></div>
        <div class="tech-card-name"><?= htmlspecialchars($q['full_name'],ENT_QUOTES) ?></div>
        <div class="tech-card-addr"><?= htmlspecialchars($loc,ENT_QUOTES) ?></div>
      </div>
      <span class="badge <?= htmlspecialchars($cls,ENT_QUOTES) ?>"><?= htmlspecialchars($label,ENT_QUOTES) ?></span>
    </div>
    <div class="tech-card-body">
      <?php if (!empty($q['intervention_date'])): ?>
        <div style="background:#f0f7ff;border-radius:8px;padding:.6rem .85rem;font-size:.88rem;margin-bottom:.65rem;font-weight:600;">
          📅 <?= htmlspecialchars(date('d/m/Y à H:i', strtotime((string)$q['intervention_date'])),ENT_QUOTES) ?>
          <?php if (!empty($q['duration'])): ?> · ⏱️ <?= htmlspecialchars($q['duration'],ENT_QUOTES) ?><?php endif; ?>
        </div>
      <?php endif; ?>
      <?php if (!empty($q['service_type'])): ?>
        <div style="font-size:.83rem;color:#445;margin-bottom:.45rem;">🔧 <?= htmlspecialchars($q['service_type'],ENT_QUOTES) ?></div>
      <?php endif; ?>
      <a href="intervention.php?id=<?= (int)$q['id'] ?>" class="tech-card-link">Ouvrir la fiche →</a>
    </div>
  </div>
  <?php endforeach; ?>
</div>
</body></html>
