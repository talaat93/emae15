<?php
declare(strict_types=1);
require_once __DIR__.'/../includes/bootstrap.php';
$tech = require_tech_auth();

try {
    $allInter = db_fetch_all(
        "SELECT * FROM quotes WHERE technician_id = ? AND archived = 0 ORDER BY intervention_date ASC, created_at DESC",
        [(int)$tech['id']]
    );
} catch (Throwable $e) { $allInter = []; }

$view    = in_array($_GET['view'] ?? '', ['calendrier','rapports','profil'], true) ? $_GET['view'] : 'calendrier';
$selDate = $_GET['date'] ?? date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $selDate)) $selDate = date('Y-m-d');

$selTs = strtotime($selDate);
$monTs = $selTs - (((int)date('N', $selTs) - 1) * 86400);
$weekDays = [];
$dayLetters = ['L','M','M','J','V','S','D'];
for ($i = 0; $i < 7; $i++) {
    $ts = $monTs + $i * 86400;
    $weekDays[] = ['ts'=>$ts, 'date'=>date('Y-m-d',$ts), 'lbl'=>$dayLetters[$i], 'num'=>(int)date('j',$ts)];
}

$byDate = [];
foreach ($allInter as $q) {
    if (!empty($q['intervention_date'])) {
        $d = date('Y-m-d', strtotime((string)$q['intervention_date']));
        $byDate[$d][] = $q;
    }
}
$dayInter = $byDate[$selDate] ?? [];

$frMonths = ['','Janvier','Février','Mars','Avril','Mai','Juin','Juillet','Août','Septembre','Octobre','Novembre','Décembre'];
$frDays   = ['','Lundi','Mardi','Mercredi','Jeudi','Vendredi','Samedi','Dimanche'];
$frMonG   = ['','jan.','fév.','mar.','avr.','mai','juin','juil.','août','sept.','oct.','nov.','déc.'];
$monthLabel = $frMonths[(int)date('n', $selTs)].' '.date('Y', $selTs);
$dayLabel   = $frDays[(int)date('N', $selTs)].' '.(int)date('j', $selTs).' '.$frMonG[(int)date('n', $selTs)].' '.date('Y', $selTs);

$statusLabels = ['nouveau'=>'Nouveau','contacté'=>'Contacté','planifié'=>'Planifié','en cours'=>'En cours','terminé'=>'Terminé ✓','annulé'=>'Annulé'];
$statusBadge  = ['nouveau'=>'badge-nouveau','contacté'=>'badge-contact','planifié'=>'badge-planifié','en cours'=>'badge-en-cours','terminé'=>'badge-terminé','annulé'=>'badge-annulé'];

$e        = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$techName = (string)$tech['name'];
$initials = mb_strtoupper(mb_substr($techName, 0, 1, 'UTF-8'), 'UTF-8');
$prevDate = date('Y-m-d', $monTs - 7*86400);
$nextDate = date('Y-m-d', $monTs + 7*86400);
?><!DOCTYPE html>
<html lang="fr"><head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0,viewport-fit=cover">
<meta name="robots" content="noindex,nofollow">
<title>Mes interventions — <?= $e(company_name()) ?></title>
<link rel="stylesheet" href="<?= $e(asset_url('assets/css/tech.css')) ?>">
</head><body>

<div class="t-header">
  <div class="t-brand">EM<span>AE</span></div>
  <div class="t-header-right">
    <span class="t-avatar" title="<?= $e($techName) ?>"><?= $e($initials) ?></span>
  </div>
</div>

<div class="t-main">

<?php if ($view === 'calendrier'): ?>

  <div class="t-cal-card">
    <div class="t-cal-head">
      <a href="?view=calendrier&date=<?= $e($prevDate) ?>" class="t-cal-nav-btn">‹</a>
      <span class="t-cal-month"><?= $e($monthLabel) ?></span>
      <a href="?view=calendrier&date=<?= $e($nextDate) ?>" class="t-cal-nav-btn">›</a>
    </div>
    <div class="t-week-strip">
      <?php foreach ($weekDays as $wd):
        $isSel   = $wd['date'] === $selDate;
        $isToday = $wd['date'] === date('Y-m-d');
        $hasDot  = !empty($byDate[$wd['date']]);
        $cls = 't-day-cell'.($isSel ? ' selected' : ($isToday ? ' today' : ''));
      ?>
      <a href="?view=calendrier&date=<?= $e($wd['date']) ?>" class="<?= $cls ?>">
        <span class="t-day-lbl"><?= $e($wd['lbl']) ?></span>
        <span class="t-day-num"><?= $wd['num'] ?></span>
        <?php if ($hasDot): ?><span class="t-day-dot"></span><?php else: ?><span class="t-day-dot-empty"></span><?php endif; ?>
      </a>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="t-section-hd"><?= $e($dayLabel) ?></div>

  <?php if (empty($dayInter)): ?>
    <div class="t-card">
      <div class="t-card-body">
        <div class="t-empty">
          <div class="t-empty-icon">📅</div>
          <div class="t-empty-title">Aucune intervention ce jour</div>
          <div class="t-empty-text">Aucune fiche planifiée · "Mes rapports" pour voir toutes vos fiches</div>
        </div>
      </div>
    </div>
  <?php else: ?>
    <?php foreach ($dayInter as $q):
      $st  = (string)($q['status'] ?? 'nouveau');
      $loc = trim(trim(($q['address']??'')).', '.trim(($q['postal_code']??'')).' '.trim(($q['city']??'')), ', ');
    ?>
    <a href="intervention.php?id=<?= (int)$q['id'] ?>" class="t-card" style="display:block;">
      <div class="t-card-head">
        <div>
          <div class="t-card-id">#<?= (int)$q['id'] ?> · <?= $e(!empty($q['intervention_date']) ? date('H:i', strtotime((string)$q['intervention_date'])) : date('d/m/Y', strtotime((string)$q['created_at']))) ?></div>
          <div class="t-card-name"><?= $e($q['full_name']) ?></div>
          <?php if ($loc): ?><div class="t-card-addr">📍 <?= $e($loc) ?></div><?php endif; ?>
          <?php if (!empty($q['service_type'])): ?><div class="t-card-meta">🔧 <?= $e($q['service_type']) ?></div><?php endif; ?>
        </div>
        <span class="badge <?= $e($statusBadge[$st] ?? 'badge-nouveau') ?>"><?= $e($statusLabels[$st] ?? $st) ?></span>
      </div>
    </a>
    <?php endforeach; ?>
  <?php endif; ?>

<?php elseif ($view === 'rapports'): ?>

  <div class="t-section-hd">Toutes mes fiches (<?= count($allInter) ?>)</div>
  <?php if (empty($allInter)): ?>
    <div class="t-card"><div class="t-card-body"><div class="t-empty"><div class="t-empty-icon">📋</div><div class="t-empty-title">Aucune fiche assignée</div></div></div></div>
  <?php else: ?>
    <?php foreach ($allInter as $q):
      $st  = (string)($q['status'] ?? 'nouveau');
      $loc = trim(trim(($q['address']??'')).', '.trim(($q['postal_code']??'')).' '.trim(($q['city']??'')), ', ');
    ?>
    <a href="intervention.php?id=<?= (int)$q['id'] ?>" class="t-card" style="display:block;">
      <div class="t-card-head">
        <div>
          <div class="t-card-id">#<?= (int)$q['id'] ?> · <?= $e(date('d/m/Y', strtotime((string)$q['created_at']))) ?></div>
          <div class="t-card-name"><?= $e($q['full_name']) ?></div>
          <?php if ($loc): ?><div class="t-card-addr">📍 <?= $e($loc) ?></div><?php endif; ?>
          <?php if (!empty($q['intervention_date'])): ?><div class="t-card-meta">📅 <?= $e(date('d/m/Y à H:i', strtotime((string)$q['intervention_date']))) ?></div><?php endif; ?>
        </div>
        <span class="badge <?= $e($statusBadge[$st] ?? 'badge-nouveau') ?>"><?= $e($statusLabels[$st] ?? $st) ?></span>
      </div>
    </a>
    <?php endforeach; ?>
  <?php endif; ?>

<?php elseif ($view === 'profil'): ?>

  <div class="t-section-hd">Mon profil</div>
  <div class="t-card">
    <div class="t-card-body">
      <div style="display:flex;align-items:center;gap:1rem;padding-bottom:1rem;border-bottom:1px solid var(--border);margin-bottom:1rem;">
        <div class="t-avatar" style="width:54px;height:54px;font-size:1.25rem;flex-shrink:0;"><?= $e($initials) ?></div>
        <div>
          <div style="font-size:1rem;font-weight:700;"><?= $e($techName) ?></div>
          <div style="font-size:.82rem;color:var(--t2);">Technicien EMAE</div>
        </div>
      </div>
      <?php
        $total  = count($allInter);
        $active = count(array_filter($allInter, fn($q) => !in_array($q['status']??'', ['terminé','annulé'])));
        $done   = count(array_filter($allInter, fn($q) => ($q['status']??'') === 'terminé'));
      ?>
      <div class="t-info-row"><span class="t-info-label">Fiches totales</span><span class="t-info-value"><?= $total ?></span></div>
      <div class="t-info-row"><span class="t-info-label">En cours / à faire</span><span class="t-info-value"><?= $active ?></span></div>
      <div class="t-info-row"><span class="t-info-label">Terminées</span><span class="t-info-value" style="color:var(--green);font-weight:700;"><?= $done ?></span></div>
    </div>
  </div>
  <a href="logout.php" class="t-btn t-btn-outline" style="margin-top:.5rem;">🚪 Déconnexion</a>

<?php endif; ?>
</div>

<nav class="t-nav">
  <a href="?view=calendrier&date=<?= $e($selDate) ?>" class="t-nav-item <?= $view==='calendrier'?'active':'' ?>">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
    Calendrier
  </a>
  <a href="?view=rapports" class="t-nav-item <?= $view==='rapports'?'active':'' ?>">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14,2 14,8 20,8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
    Mes rapports
  </a>
  <a href="?view=profil" class="t-nav-item <?= $view==='profil'?'active':'' ?>">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
    Profil
  </a>
</nav>

</body></html>
