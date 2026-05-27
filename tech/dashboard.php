<?php
declare(strict_types=1);
require_once __DIR__.'/../includes/bootstrap.php';
$tech = require_tech_auth();

$techId   = (int)$tech['id'];
$techName = (string)($tech['name'] ?? 'Technicien');
$today    = date('Y-m-d');
$monthStart = date('Y-m-01');

// Missions dispatcher aujourd'hui
$todayMissions = [];
try {
    $todayMissions = db_fetch_all(
        "SELECT i.*, c.lastname, c.firstname, c.phone AS client_phone, c.address, c.city AS client_city, c.postal_code, c.floor, c.digicode
         FROM interventions i
         LEFT JOIN clients c ON c.id = i.client_id
         WHERE i.technician_id = ? AND i.scheduled_date = ? AND i.status NOT IN ('terminé','annulé')
         ORDER BY i.urgency DESC, i.scheduled_time ASC",
        [$techId, $today]
    );
} catch (Throwable $e) { $todayMissions = []; }

// Toutes interventions dispatcher assignées à ce tech
$allInterventions = all_interventions(['technician_id' => $techId]);

// Demandes directes (quotes system)
$quoteMissions = [];
try {
    $quoteMissions = db_fetch_all(
        "SELECT * FROM quotes WHERE technician_id = ? AND archived = 0 ORDER BY created_at DESC LIMIT 20",
        [$techId]
    );
} catch (Throwable $e) { $quoteMissions = []; }

// Stats
$statToday = count($todayMissions);
$statInProgress = 0;
$statDoneMonth  = 0;
try {
    $statInProgress = (int)(db_fetch("SELECT COUNT(*) AS c FROM interventions WHERE technician_id=? AND status IN ('en_route','sur_place')", [$techId])['c'] ?? 0);
    $statDoneMonth  = (int)(db_fetch("SELECT COUNT(*) AS c FROM interventions WHERE technician_id=? AND status='terminé' AND tech_completed_at >= ?", [$techId, $monthStart])['c'] ?? 0);
} catch (Throwable $e) {}

$catConf = intervention_category_config();
$stConf  = intervention_status_config();

$initials = mb_strtoupper(mb_substr($techName, 0, 1, 'UTF-8').''.mb_substr($techName, (int)(mb_strpos($techName, ' ', 0, 'UTF-8') ?: mb_strlen($techName, 'UTF-8') - 1) + 1, 1, 'UTF-8'), 'UTF-8');

$statusBadgeClass = [
    'nouveau'=>'badge-nouveau','confirmé'=>'badge-nouveau','assigné'=>'badge-planifié',
    'en_route'=>'badge-en-cours','sur_place'=>'badge-en-cours','terminé'=>'badge-terminé',
    'devis_envoyé'=>'badge-contact','facturé'=>'badge-contact','payé'=>'badge-terminé','annulé'=>'badge-annulé',
];
$quoteStatusBadge = [
    'nouveau'=>'badge-nouveau','contacté'=>'badge-contact','planifié'=>'badge-planifié',
    'en cours'=>'badge-en-cours','terminé'=>'badge-terminé','annulé'=>'badge-annulé',
];

$e = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$apiUrl = htmlspecialchars(url_for('dispatcher/api.php'), ENT_QUOTES);
?><!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0,viewport-fit=cover">
<meta name="robots" content="noindex,nofollow">
<title>Mon espace — <?= $e($techName) ?></title>
<link rel="stylesheet" href="<?= $e(asset_url('assets/css/tech.css')) ?>">
<style>
.stat-cards { display: grid; grid-template-columns: repeat(3,1fr); gap: .65rem; margin-bottom: 1rem; }
.stat-card { background: var(--card); border-radius: var(--r-sm); box-shadow: var(--shadow); padding: .85rem .75rem; text-align: center; }
.stat-num { font-size: 1.8rem; font-weight: 800; color: var(--p); line-height: 1.1; }
.stat-lbl { font-size: .68rem; font-weight: 700; color: var(--t2); text-transform: uppercase; letter-spacing: .06em; margin-top: .25rem; }
.mission-card { background: var(--card); border-radius: var(--r); box-shadow: var(--shadow); overflow: hidden; margin-bottom: .75rem; border-left: 4px solid var(--border); }
.mission-card.urgent { border-left-color: var(--red); }
.mission-card.in-progress { border-left-color: #10b981; }
.mission-head { display: flex; align-items: flex-start; justify-content: space-between; gap: .65rem; padding: .85rem 1rem .55rem; }
.mission-client { font-size: .95rem; font-weight: 800; color: var(--t1); }
.mission-meta { font-size: .75rem; color: var(--t2); margin-top: .25rem; display: flex; flex-wrap: wrap; gap: .3rem .65rem; }
.mission-body { padding: 0 1rem .85rem; }
.mission-addr { font-size: .8rem; color: var(--t2); margin-bottom: .65rem; display: flex; gap: .35rem; align-items: flex-start; }
.mission-actions { display: flex; gap: .5rem; flex-wrap: wrap; }
.t-btn-sm { padding: .5rem .9rem; font-size: .78rem; border-radius: 8px; font-weight: 700; border: none; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; gap: .3rem; }
.t-btn-route { background: #0891b2; color: #fff; }
.t-btn-route:hover { background: #0e7490; }
.t-btn-surplace { background: #059669; color: #fff; }
.t-btn-surplace:hover { background: #047857; }
.t-btn-done { background: var(--p); color: #fff; }
.t-btn-done:hover { background: var(--p-dk); }
.t-btn-view { background: var(--navy2); color: #fff; }
.t-btn-view:hover { background: var(--navy); }
.section-title { font-size: .72rem; font-weight: 800; text-transform: uppercase; letter-spacing: .1em; color: var(--t2); margin: 1.25rem 0 .6rem; padding-bottom: .4rem; border-bottom: 1px solid var(--border); }
.cat-badge { display: inline-flex; align-items: center; gap: .25rem; font-size: .7rem; font-weight: 700; padding: .18rem .55rem; border-radius: 6px; }
.empty-state { text-align: center; padding: 2rem 1rem; color: var(--t3); font-size: .88rem; }
.empty-state .ico { font-size: 2rem; margin-bottom: .5rem; }
.flash-ok { background: #dcfce7; color: #16a34a; border: 1px solid #bbf7d0; border-radius: 10px; padding: .75rem 1rem; margin-bottom: .85rem; font-weight: 700; font-size: .88rem; }
.flash-err { background: var(--red-lt); color: var(--red); border: 1px solid #fecaca; border-radius: 10px; padding: .75rem 1rem; margin-bottom: .85rem; font-weight: 700; font-size: .88rem; }
.quote-card { background: var(--card); border-radius: var(--r); box-shadow: var(--shadow); padding: .85rem 1rem; margin-bottom: .65rem; }
.quote-name { font-weight: 800; color: var(--t1); font-size: .92rem; }
.quote-meta { font-size: .75rem; color: var(--t2); margin-top: .2rem; }
.all-missions-table { width: 100%; border-collapse: collapse; font-size: .8rem; }
.all-missions-table th { font-size: .67rem; font-weight: 700; text-transform: uppercase; letter-spacing: .08em; color: var(--t2); padding: .5rem .5rem; text-align: left; border-bottom: 2px solid var(--border); }
.all-missions-table td { padding: .55rem .5rem; border-bottom: 1px solid var(--border); vertical-align: middle; }
.all-missions-table tr:last-child td { border-bottom: none; }
.all-missions-table tr:hover td { background: #f8fafc; }
</style>
</head>
<body>

<!-- Header -->
<div class="t-header">
  <div class="t-brand">EM<span>AE</span></div>
  <div style="display:flex;align-items:center;gap:.5rem;">
    <div class="t-avatar"><?= $e(mb_substr($techName, 0, 1, 'UTF-8')) ?></div>
    <div style="font-size:.82rem;">
      <div style="font-weight:700;color:#fff;"><?= $e($techName) ?></div>
      <div style="font-size:.7rem;color:#94a3b8;">Technicien</div>
    </div>
  </div>
  <a href="<?= $e(url_for('tech/logout.php')) ?>" style="color:#94a3b8;font-size:.78rem;font-weight:600;">Déco</a>
</div>

<div class="t-main">

  <?php if ($m = flash('success')): ?><div class="flash-ok">✅ <?= $e($m) ?></div><?php endif; ?>
  <?php if ($m = flash('error')): ?><div class="flash-err">⚠️ <?= $e($m) ?></div><?php endif; ?>

  <!-- Stat cards -->
  <div class="stat-cards">
    <div class="stat-card">
      <div class="stat-num"><?= $statToday ?></div>
      <div class="stat-lbl">Missions<br>aujourd'hui</div>
    </div>
    <div class="stat-card">
      <div class="stat-num" style="color:#10b981;"><?= $statInProgress ?></div>
      <div class="stat-lbl">En cours</div>
    </div>
    <div class="stat-card">
      <div class="stat-num" style="color:#16a34a;"><?= $statDoneMonth ?></div>
      <div class="stat-lbl">Terminées<br>ce mois</div>
    </div>
  </div>

  <!-- Missions du jour -->
  <div class="section-title">📅 Mes missions aujourd'hui</div>

  <?php if (empty($todayMissions)): ?>
  <div class="empty-state"><div class="ico">☀️</div>Aucune mission planifiée aujourd'hui.</div>
  <?php else: ?>
  <?php foreach ($todayMissions as $iv): ?>
  <?php
    $clientName = trim(($iv['firstname']??'').' '.($iv['lastname']??'')) ?: 'Client';
    $catKey = (string)($iv['category'] ?? '');
    $catC   = $catConf[$catKey] ?? ['icon'=>'🔧','label'=>$catKey,'color'=>'#8fa0c4'];
    $stKey  = (string)($iv['status'] ?? '');
    $time   = !empty($iv['scheduled_time']) ? date('H:i', strtotime($iv['scheduled_time'])) : '';
    $addr   = array_filter([$iv['address']??'', trim(($iv['postal_code']??'').' '.($iv['client_city']??''))]);
    $addrStr = implode(', ', $addr);
    $isInProgress = in_array($stKey, ['en_route','sur_place']);
    $isUrgent = !empty($iv['urgency']);
  ?>
  <div class="mission-card <?= $isUrgent ? 'urgent' : ($isInProgress ? 'in-progress' : '') ?>">
    <div class="mission-head">
      <div>
        <div class="mission-client">
          <?= $isUrgent ? '🚨 ' : '' ?><?= $e($clientName) ?>
        </div>
        <div class="mission-meta">
          <span style="color:<?= $e($catC['color']) ?>;font-weight:700;"><?= $e($catC['icon'].' '.$catC['label']) ?></span>
          <?php if ($time): ?><span>🕐 <?= $e($time) ?></span><?php endif; ?>
          <?php if (!empty($iv['duration_estimate'])): ?><span>⏱ <?= (int)$iv['duration_estimate'] ?> min</span><?php endif; ?>
        </div>
      </div>
      <span class="badge <?= $e($statusBadgeClass[$stKey] ?? 'badge-nouveau') ?>">
        <?= $e($stConf[$stKey]['label'] ?? $stKey) ?>
      </span>
    </div>
    <div class="mission-body">
      <?php if ($addrStr !== ''): ?>
      <div class="mission-addr">
        <span>📍</span>
        <span><?= $e($addrStr) ?></span>
        <a href="https://maps.google.com/?q=<?= rawurlencode($addrStr) ?>" target="_blank" rel="noopener" style="color:var(--blue);font-size:.72rem;font-weight:700;white-space:nowrap;">Maps →</a>
      </div>
      <?php endif; ?>
      <?php if (!empty($iv['floor'])): ?><div style="font-size:.75rem;color:var(--t2);margin-bottom:.4rem;">🏢 Étage: <?= $e($iv['floor']) ?><?php if(!empty($iv['digicode'])): ?> · Code: <?= $e($iv['digicode']) ?><?php endif; ?></div><?php endif; ?>
      <?php if (!empty($iv['fault_reported'])): ?>
      <div style="font-size:.78rem;color:var(--t2);background:#f8fafc;border-radius:6px;padding:.45rem .6rem;margin-bottom:.6rem;border-left:3px solid var(--border);"><?= $e($iv['fault_reported']) ?></div>
      <?php endif; ?>

      <div class="mission-actions">
        <?php if (!empty($iv['client_phone'])): ?>
        <a href="tel:<?= $e(preg_replace('/\s+/','',$iv['client_phone'])) ?>" class="t-btn-sm t-btn-view">📞 Appeler</a>
        <?php endif; ?>
        <?php if (!in_array($stKey, ['en_route','sur_place','terminé'])): ?>
        <button class="t-btn-sm t-btn-route" onclick="updateStatus(<?= (int)$iv['id'] ?>, 'en_route', this)">🚗 En route</button>
        <?php endif; ?>
        <?php if ($stKey === 'en_route'): ?>
        <button class="t-btn-sm t-btn-surplace" onclick="updateStatus(<?= (int)$iv['id'] ?>, 'sur_place', this)">📍 Sur place</button>
        <?php endif; ?>
        <?php if (in_array($stKey, ['en_route','sur_place','assigné'])): ?>
        <a href="<?= $e(url_for('tech/disp_intervention.php?id='.(int)$iv['id'])) ?>" class="t-btn-sm t-btn-done">✅ Clôturer</a>
        <?php endif; ?>
        <a href="<?= $e(url_for('tech/disp_intervention.php?id='.(int)$iv['id'])) ?>" class="t-btn-sm t-btn-view">Fiche →</a>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
  <?php endif; ?>

  <!-- Toutes mes interventions dispatcher -->
  <div class="section-title">📋 Missions dispatcher (toutes)</div>

  <?php
  $pending = array_filter($allInterventions, fn($i) => !in_array($i['status'] ?? '', ['terminé','annulé','payé']));
  $done    = array_filter($allInterventions, fn($i) => in_array($i['status'] ?? '', ['terminé','annulé','payé']));
  ?>

  <?php if (empty($allInterventions)): ?>
  <div class="empty-state"><div class="ico">📋</div>Aucune mission dispatcher assignée.</div>
  <?php else: ?>
  <?php if (!empty($pending)): ?>
  <div style="font-size:.72rem;font-weight:700;color:var(--t2);margin-bottom:.5rem;text-transform:uppercase;letter-spacing:.06em;">En attente / en cours (<?= count($pending) ?>)</div>
  <div style="background:var(--card);border-radius:var(--r);box-shadow:var(--shadow);overflow:hidden;margin-bottom:.85rem;">
    <table class="all-missions-table">
      <thead>
        <tr><th>Réf.</th><th>Client</th><th>Catégorie</th><th>Date</th><th>Statut</th><th></th></tr>
      </thead>
      <tbody>
        <?php foreach ($pending as $iv): ?>
        <?php
          $cn = trim(($iv['firstname']??'').' '.($iv['lastname']??'')) ?: 'Client';
          $catK = $iv['category'] ?? '';
          $catC2 = $catConf[$catK] ?? ['icon'=>'🔧','color'=>'#8fa0c4','label'=>$catK];
          $stK  = $iv['status'] ?? '';
          $schedDate = !empty($iv['scheduled_date']) ? date('d/m/Y', strtotime($iv['scheduled_date'])) : '—';
        ?>
        <tr>
          <td style="font-family:monospace;font-weight:700;font-size:.72rem;color:var(--p);"><?= $e($iv['ref'] ?? '#'.$iv['id']) ?></td>
          <td style="font-weight:600;"><?= $e($cn) ?></td>
          <td><span style="color:<?= $e($catC2['color']) ?>;font-weight:700;font-size:.72rem;"><?= $e($catC2['icon'].' '.$catC2['label']) ?></span></td>
          <td style="color:var(--t2);font-size:.78rem;"><?= $e($schedDate) ?></td>
          <td>
            <span class="badge <?= $e($statusBadgeClass[$stK] ?? 'badge-nouveau') ?>" style="font-size:.68rem;">
              <?= $e($stConf[$stK]['label'] ?? $stK) ?>
            </span>
          </td>
          <td style="text-align:right;">
            <a href="<?= $e(url_for('tech/disp_intervention.php?id='.(int)$iv['id'])) ?>" class="t-btn-sm t-btn-view" style="font-size:.7rem;padding:.3rem .65rem;" target="_blank">Voir</a>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>

  <?php if (!empty($done)): ?>
  <details style="margin-bottom:.85rem;">
    <summary style="font-size:.72rem;font-weight:700;color:var(--t2);text-transform:uppercase;letter-spacing:.06em;cursor:pointer;padding:.4rem 0;">Terminées / annulées (<?= count($done) ?>)</summary>
    <div style="background:var(--card);border-radius:var(--r);box-shadow:var(--shadow);overflow:hidden;margin-top:.5rem;">
      <table class="all-missions-table">
        <thead>
          <tr><th>Réf.</th><th>Client</th><th>Catégorie</th><th>Date</th><th>Statut</th><th></th></tr>
        </thead>
        <tbody>
          <?php foreach (array_slice(array_values($done), 0, 10) as $iv): ?>
          <?php
            $cn = trim(($iv['firstname']??'').' '.($iv['lastname']??'')) ?: 'Client';
            $catK = $iv['category'] ?? '';
            $catC2 = $catConf[$catK] ?? ['icon'=>'🔧','color'=>'#8fa0c4','label'=>$catK];
            $stK  = $iv['status'] ?? '';
            $schedDate = !empty($iv['scheduled_date']) ? date('d/m/Y', strtotime($iv['scheduled_date'])) : '—';
          ?>
          <tr>
            <td style="font-family:monospace;font-weight:700;font-size:.72rem;color:var(--t3);"><?= $e($iv['ref'] ?? '#'.$iv['id']) ?></td>
            <td style="font-weight:600;color:var(--t2);"><?= $e($cn) ?></td>
            <td><span style="color:<?= $e($catC2['color']) ?>;font-weight:700;font-size:.72rem;"><?= $e($catC2['icon'].' '.$catC2['label']) ?></span></td>
            <td style="color:var(--t2);font-size:.78rem;"><?= $e($schedDate) ?></td>
            <td><span class="badge <?= $e($statusBadgeClass[$stK] ?? 'badge-annulé') ?>" style="font-size:.68rem;"><?= $e($stConf[$stK]['label'] ?? $stK) ?></span></td>
            <td style="text-align:right;"><a href="<?= $e(url_for('tech/disp_intervention.php?id='.(int)$iv['id'])) ?>" class="t-btn-sm t-btn-view" style="font-size:.7rem;padding:.3rem .65rem;" target="_blank">Voir</a></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </details>
  <?php endif; ?>
  <?php endif; ?>

  <!-- Demandes directes (système quotes) -->
  <?php if (!empty($quoteMissions)): ?>
  <div class="section-title">📩 Demandes directes (system devis)</div>
  <?php foreach ($quoteMissions as $q): ?>
  <?php
    $qSt = (string)($q['status'] ?? 'nouveau');
    $qDate = !empty($q['intervention_date']) ? date('d/m/Y H:i', strtotime($q['intervention_date'])) : (date('d/m/Y', strtotime($q['created_at']??'now')));
    $isComplete = $qSt === 'terminé';
  ?>
  <div class="quote-card">
    <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:.5rem;">
      <div>
        <div class="quote-name"><?= $e($q['full_name'] ?? '—') ?></div>
        <div class="quote-meta">
          <?php if (!empty($q['service_type'])): ?><?= $e($q['service_type']) ?> · <?php endif; ?>
          📅 <?= $e($qDate) ?>
          <?php if (!empty($q['city'])): ?> · 📍 <?= $e($q['city']) ?><?php endif; ?>
        </div>
      </div>
      <span class="badge <?= $e($quoteStatusBadge[$qSt] ?? 'badge-nouveau') ?>" style="font-size:.7rem;white-space:nowrap;"><?= $e($qSt) ?></span>
    </div>
    <div style="display:flex;gap:.5rem;margin-top:.65rem;flex-wrap:wrap;">
      <?php if (!empty($q['phone'])): ?>
      <a href="tel:<?= $e(preg_replace('/\s+/','',$q['phone'])) ?>" class="t-btn-sm t-btn-view">📞 <?= $e($q['phone']) ?></a>
      <?php endif; ?>
      <a href="<?= $e(url_for('tech/intervention.php?id='.(int)$q['id'])) ?>" class="t-btn-sm <?= $isComplete ? 't-btn-view' : 't-btn-done' ?>">
        <?= $isComplete ? '📄 Rapport' : '▶ Ouvrir la mission' ?>
      </a>
    </div>
  </div>
  <?php endforeach; ?>
  <?php endif; ?>


</div>

<!-- Bottom nav -->
<nav class="t-nav">
  <a class="t-nav-item active" href="<?= $e(url_for('tech/dashboard.php')) ?>">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
    Accueil
  </a>
  <a class="t-nav-item" href="<?= $e(url_for('tech/dashboard.php')) ?>">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
    Missions
  </a>
  <a class="t-nav-item" href="<?= $e(url_for('tech/logout.php')) ?>">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
    Déconnexion
  </a>
</nav>

<script>
var apiUrl = '<?= $apiUrl ?>';

function updateStatus(id, newStatus, btn) {
  if (!confirm('Confirmer le changement de statut ?')) return;
  btn.disabled = true;
  var origText = btn.textContent;
  btn.textContent = '…';

  var fd = new FormData();
  fd.append('action', 'update_status');
  fd.append('id', id);
  fd.append('status', newStatus);

  fetch(apiUrl, { method: 'POST', body: fd })
    .then(function(r) { return r.json(); })
    .then(function(data) {
      if (data.success) {
        // Reload to reflect new state
        window.location.reload();
      } else {
        alert('Erreur: ' + (data.error || 'Inconnue'));
        btn.disabled = false;
        btn.textContent = origText;
      }
    })
    .catch(function(err) {
      console.error(err);
      alert('Erreur réseau.');
      btn.disabled = false;
      btn.textContent = origText;
    });
}
</script>
</body>
</html>
