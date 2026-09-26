<?php
declare(strict_types=1);
$pageTitle   = "Aujourd'hui";
$dispSection = 'dashboard';
require __DIR__.'/partials/header.php';

// ─── Données ────────────────────────────────────────────────
$today    = date('Y-m-d');
$kpis     = dispatcher_kpis();
$todayIvs = all_interventions(['date' => $today]);
$statusCfg = intervention_status_config();

$closed = wf_closed();

// À planifier : tout ce qui n'a pas encore de technicien ou de date.
$toPlan = array_values(array_filter(all_interventions(), static function (array $iv) use ($closed): bool {
    if (in_array($iv['status'] ?? '', $closed, true)) return false;
    return empty($iv['technician_id']) || empty($iv['scheduled_date']) || in_array($iv['status'] ?? '', ['nouveau', 'a_assigner', 'confirmé'], true);
}));

try {
    $techs = db_fetch_all("SELECT id, name FROM technicians WHERE status = 'actif' ORDER BY name");
} catch (Throwable $e) { $techs = []; }

// Interventions du jour rangées par technicien (0 = non assigné).
$byTech = [];
foreach ($todayIvs as $iv) {
    if (($iv['status'] ?? '') === 'annulé') continue;
    $byTech[(int)($iv['technician_id'] ?? 0)][] = $iv;
}

$inProgress = count(array_filter($todayIvs, static fn($i) => in_array($i['status'] ?? '', ['en_route', 'sur_place'], true)));

// ─── Aide à l'affichage ─────────────────────────────────────
function fr_long_date(string $ymd): string {
    $j = ['dimanche','lundi','mardi','mercredi','jeudi','vendredi','samedi'];
    $m = ['','janvier','février','mars','avril','mai','juin','juillet','août','septembre','octobre','novembre','décembre'];
    $t = strtotime($ymd);
    return ucfirst($j[(int)date('w', $t)]).' '.(int)date('j', $t).' '.$m[(int)date('n', $t)].' '.date('Y', $t);
}
function client_name(array $iv): string {
    $n = trim(($iv['firstname'] ?? '').' '.($iv['lastname'] ?? ''));
    return $n !== '' ? $n : 'Client inconnu';
}
function minutes_of(?string $t): ?int {
    if ($t === null || $t === '') return null;
    return (int)substr($t, 0, 2) * 60 + (int)substr($t, 3, 2);
}

// Plage horaire du planning : 7h–19h, élargie si une intervention en sort.
$dayStart = 7 * 60; $dayEnd = 19 * 60;
foreach ($todayIvs as $iv) {
    $s = minutes_of($iv['scheduled_time'] ?? null);
    if ($s === null) continue;
    $dayStart = min($dayStart, intdiv($s, 60) * 60);
    $dayEnd   = max($dayEnd, (int)ceil(($s + max(30, (int)($iv['duration_estimate'] ?? 60))) / 60) * 60);
}
$span = max(60, $dayEnd - $dayStart);
$nowMin = (int)date('G') * 60 + (int)date('i');

$viewUrl = static fn(array $iv): string => url_for('dispatcher/intervention_view.php?id='.(int)$iv['id']);
?>
<style>
.dash-kpis { display:grid; grid-template-columns:repeat(4,1fr); gap:1rem; margin-bottom:1.25rem; }
.dash-alert { display:flex; gap:.6rem; flex-wrap:wrap; margin-bottom:1.25rem; }
.dash-alert a { display:inline-flex; align-items:center; gap:.4rem; padding:.45rem .8rem; border-radius:8px; font-size:.84rem; font-weight:600; text-decoration:none; }
.dash-alert .is-red { background:#fef2f2; color:#b91c1c; border:1px solid #f5c2c2; }
.dash-alert .is-amber { background:#fffbeb; color:#92400e; border:1px solid #f6dfa4; }
.dash-grid { display:grid; grid-template-columns:minmax(0,1fr); gap:1.25rem; }

/* Planning du jour */
.plan { overflow-x:auto; }
.plan-inner { min-width:720px; }
.plan-head, .plan-row { display:grid; grid-template-columns:170px 1fr; }
.plan-head { border-bottom:1px solid var(--d-border); background:var(--d-card-2); }
.plan-hours { position:relative; height:30px; }
.plan-hours span { position:absolute; top:7px; font-size:.72rem; color:var(--d-t3); transform:translateX(-50%); }
.plan-row { border-bottom:1px solid var(--d-border); min-height:58px; }
.plan-row:last-child { border-bottom:none; }
.plan-who { padding:.65rem 1rem; display:flex; align-items:center; gap:.6rem; border-right:1px solid var(--d-border); }
.plan-who .av { width:28px; height:28px; border-radius:50%; background:#e8edf5; color:var(--d-navy); font-size:.75rem; font-weight:700; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
.plan-who .nm { font-size:.85rem; font-weight:600; color:var(--d-t1); line-height:1.2; }
.plan-who .ct { font-size:.74rem; color:var(--d-t3); }
.plan-lane { position:relative; background-image:linear-gradient(to right, var(--d-border) 1px, transparent 1px); background-size:var(--hour) 100%; }
.plan-now { position:absolute; top:0; bottom:0; width:2px; background:var(--d-orange); z-index:2; }
.plan-job { position:absolute; top:8px; bottom:8px; border-radius:6px; padding:.25rem .5rem; overflow:hidden; text-decoration:none; background:#fff; border:1px solid var(--d-border-2); border-left-width:4px; z-index:1; min-width:36px; }
.plan-job:hover { box-shadow:0 2px 8px rgba(16,24,40,.12); z-index:3; }
.plan-job .t { font-size:.7rem; color:var(--d-t2); white-space:nowrap; }
.plan-job .c { font-size:.78rem; font-weight:600; color:var(--d-t1); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.plan-job.is-urgent { background:#fef2f2; border-color:#f5c2c2; }
.plan-untimed { padding:.5rem 1rem .75rem; display:flex; gap:.4rem; flex-wrap:wrap; border-top:1px dashed var(--d-border); }
.plan-untimed a { font-size:.78rem; text-decoration:none; color:var(--d-t1); background:var(--d-card-2); border:1px solid var(--d-border); border-radius:6px; padding:.2rem .5rem; }
.plan-legend { display:flex; gap:1rem; flex-wrap:wrap; font-size:.76rem; color:var(--d-t2); }
.plan-legend i { display:inline-block; width:9px; height:9px; border-radius:2px; margin-right:.3rem; vertical-align:-1px; }

/* À planifier */
.todo-list { display:grid; grid-template-columns:repeat(auto-fill,minmax(290px,1fr)); }
.todo-item { display:block; padding:.75rem 1.1rem; border-bottom:1px solid var(--d-border); border-right:1px solid var(--d-border); text-decoration:none; color:inherit; }
.todo-item:hover { background:var(--d-card-2); }
.todo-top { display:flex; justify-content:space-between; gap:.5rem; align-items:center; }
.todo-name { font-size:.88rem; font-weight:600; color:var(--d-t1); }
.todo-meta { font-size:.78rem; color:var(--d-t2); margin-top:.2rem; display:flex; gap:.6rem; flex-wrap:wrap; }
.todo-miss { color:var(--d-warning); }
.tag-urgent { font-size:.7rem; font-weight:700; color:#b91c1c; background:#fef2f2; border-radius:4px; padding:.05rem .4rem; }

@media (max-width:768px) { .d-topbar { flex-wrap:wrap; } .hide-sm { display:none !important; } }
@media (max-width:768px) { .dash-kpis { grid-template-columns:repeat(2,1fr); } }
</style>

<div class="d-topbar">
  <div>
    <button class="d-menu-toggle" id="d-menu-toggle" aria-label="Menu">☰</button>
    <div>
      <div class="d-topbar-title">Aujourd'hui</div>
      <div class="d-topbar-sub"><?= e(fr_long_date($today)) ?></div>
    </div>
  </div>
  <div class="d-topbar-actions">
    <a href="<?= e(url_for('dispatcher/calendar.php')) ?>" class="d-btn d-btn--sm hide-sm">Planning</a>
    <a href="<?= e(url_for('dispatcher/intervention_new.php')) ?>" class="d-btn d-btn--sm hide-sm">+ Saisie manuelle</a>
    <a href="<?= e(url_for('dispatcher/qualify.php')) ?>" class="d-btn d-btn--primary d-btn--sm">Qualifier un appel</a>
  </div>
</div>

<div class="d-content">

  <div class="dash-kpis">
    <a class="kpi-card orange" href="#a-planifier">
      <div class="kpi-value"><?= count($toPlan) ?></div>
      <div class="kpi-label">À planifier</div>
    </a>
    <a class="kpi-card blue" href="<?= e(url_for('dispatcher/interventions.php?date_from='.$today.'&date_to='.$today)) ?>">
      <div class="kpi-value"><?= (int)$kpis['today_total'] ?></div>
      <div class="kpi-label">Prévues aujourd'hui</div>
    </a>
    <a class="kpi-card cyan" href="<?= e(url_for('dispatcher/map.php')) ?>">
      <div class="kpi-value"><?= (int)$kpis['in_progress'] ?></div>
      <div class="kpi-label">En cours sur le terrain</div>
    </a>
    <div class="kpi-card green">
      <div class="kpi-value"><?= (int)$kpis['done_today'] ?></div>
      <div class="kpi-label">Terminées aujourd'hui</div>
    </div>
  </div>

  <?php if ($kpis['urgent'] > 0 || $kpis['late'] > 0): ?>
  <div class="dash-alert">
    <?php if ($kpis['urgent'] > 0): ?>
      <a class="is-red" href="<?= e(url_for('dispatcher/interventions.php?urgency=1')) ?>"><?= (int)$kpis['urgent'] ?> intervention<?= $kpis['urgent'] > 1 ? 's' : '' ?> urgente<?= $kpis['urgent'] > 1 ? 's' : '' ?> en cours de traitement</a>
    <?php endif; ?>
    <?php if ($kpis['late'] > 0): ?>
      <a class="is-amber" href="<?= e(url_for('dispatcher/interventions.php')) ?>"><?= (int)$kpis['late'] ?> intervention<?= $kpis['late'] > 1 ? 's' : '' ?> en retard (date dépassée, non terminée)</a>
    <?php endif; ?>
  </div>
  <?php endif; ?>

  <div class="dash-grid">

    <!-- Planning du jour -->
    <div class="d-card">
      <div class="d-card-head">
        <div class="d-card-title">Planning du jour</div>
        <div class="plan-legend">
          <?php foreach (['a_assigner', 'assigné', 'en_route', 'sur_place', 'rapport_rendu'] as $st): ?>
            <span><i style="background:<?= e($statusCfg[$st]['color']) ?>"></i><?= e($statusCfg[$st]['label']) ?></span>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="plan">
        <div class="plan-inner" style="--hour:calc(100% / <?= $span / 60 ?>);">
          <div class="plan-head">
            <div></div>
            <div class="plan-hours">
              <?php for ($m = $dayStart + 60; $m < $dayEnd; $m += 60): ?>
                <span style="left:<?= round(($m - $dayStart) / $span * 100, 3) ?>%"><?= intdiv($m, 60) ?>h</span>
              <?php endfor; ?>
            </div>
          </div>
          <?php
          $lanes = [];
          foreach ($techs as $t) $lanes[(int)$t['id']] = (string)$t['name'];
          foreach (array_keys($byTech) as $tid) if ($tid > 0 && !isset($lanes[$tid])) $lanes[$tid] = (string)($byTech[$tid][0]['tech_name'] ?? 'Technicien');
          if (!empty($byTech[0])) $lanes[0] = 'Non assigné';
          ?>
          <?php if (empty($lanes)): ?>
            <div class="d-empty">Aucun technicien actif. Ajoutez-en depuis l'administration.</div>
          <?php endif; ?>
          <?php foreach ($lanes as $tid => $tname): $jobs = $byTech[$tid] ?? []; $untimed = []; ?>
          <div class="plan-row">
            <div class="plan-who">
              <div class="av"><?= $tid === 0 ? '?' : e(mb_strtoupper(mb_substr($tname, 0, 1, 'UTF-8'), 'UTF-8')) ?></div>
              <div>
                <div class="nm"><?= e($tname) ?></div>
                <div class="ct"><?= count($jobs) ?> intervention<?= count($jobs) > 1 ? 's' : '' ?></div>
              </div>
            </div>
            <div class="plan-lane">
              <?php if ($nowMin > $dayStart && $nowMin < $dayEnd): ?>
                <div class="plan-now" style="left:<?= round(($nowMin - $dayStart) / $span * 100, 3) ?>%"></div>
              <?php endif; ?>
              <?php foreach ($jobs as $iv):
                $s = minutes_of($iv['scheduled_time'] ?? null);
                if ($s === null) { $untimed[] = $iv; continue; }
                $d = max(30, (int)($iv['duration_estimate'] ?? 60));
                $col = $statusCfg[$iv['status'] ?? '']['color'] ?? '#8c99ad';
              ?>
                <a class="plan-job<?= !empty($iv['urgency']) ? ' is-urgent' : '' ?>" href="<?= e($viewUrl($iv)) ?>"
                   style="left:<?= round(($s - $dayStart) / $span * 100, 3) ?>%;width:<?= round($d / $span * 100, 3) ?>%;border-left-color:<?= e($col) ?>;"
                   title="<?= e(substr((string)$iv['scheduled_time'], 0, 5).' · '.client_name($iv).' · '.($iv['type_label'] ?? '').' · '.($statusCfg[$iv['status'] ?? '']['label'] ?? '')) ?>">
                  <div class="t"><?= e(substr((string)$iv['scheduled_time'], 0, 5)) ?> · <?= e($iv['client_city'] ?? '') ?></div>
                  <div class="c"><?= e(client_name($iv)) ?></div>
                </a>
              <?php endforeach; ?>
            </div>
          </div>
          <?php if ($untimed): ?>
            <div class="plan-untimed">
              <span style="font-size:.76rem;color:var(--d-t3);">Sans heure :</span>
              <?php foreach ($untimed as $iv): ?><a href="<?= e($viewUrl($iv)) ?>"><?= e(client_name($iv)) ?></a><?php endforeach; ?>
            </div>
          <?php endif; ?>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <?php $siteQuotes = qual_pending_quotes(6); if ($siteQuotes): ?>
    <!-- Demandes du site à qualifier -->
    <div class="d-card" id="demandes-site">
      <div class="d-card-head">
        <div class="d-card-title">Demandes du site à rappeler</div>
        <a href="<?= e(url_for('dispatcher/qualify.php')) ?>" class="d-btn d-btn--ghost d-btn--sm">Tout voir</a>
      </div>
      <table class="d-table"><tbody>
      <?php foreach ($siteQuotes as $q): ?>
        <tr>
          <td><b><?= e($q['full_name']) ?></b> · <a href="tel:<?= e(preg_replace('/[^0-9+]/', '', (string)$q['phone'])) ?>"><?= e($q['phone']) ?></a>
            <div style="font-size:.8rem;color:var(--d-t2);"><?= e(trim((string)$q['service_type']) ?: 'Service non précisé') ?><?= $q['city'] ? ' · '.e((string)$q['city']) : '' ?> · reçue le <?= e(date('d/m à H:i', strtotime((string)$q['created_at']))) ?></div></td>
          <td style="text-align:right;"><a class="d-btn d-btn--sm d-btn--primary" href="<?= e(url_for('dispatcher/qualify.php?quote='.(int)$q['id'])) ?>">Qualifier</a></td>
        </tr>
      <?php endforeach; ?>
      </tbody></table>
    </div>
    <?php endif; ?>

    <!-- À planifier -->
    <div class="d-card" id="a-planifier">
      <div class="d-card-head">
        <div class="d-card-title">À planifier</div>
        <a href="<?= e(url_for('dispatcher/interventions.php')) ?>" class="d-btn d-btn--ghost d-btn--sm">Tout voir</a>
      </div>
      <?php if (empty($toPlan)): ?>
        <div class="d-empty">Tout est planifié.</div>
      <?php else: ?>
        <div class="todo-list">
        <?php foreach (array_slice($toPlan, 0, 12) as $iv):
          $miss = [];
          if (empty($iv['technician_id']))  $miss[] = 'technicien';
          if (empty($iv['scheduled_date'])) $miss[] = 'date';
        ?>
        <a class="todo-item" href="<?= e($viewUrl($iv)) ?>">
          <div class="todo-top">
            <span class="todo-name"><?= e(client_name($iv)) ?></span>
            <?php if (!empty($iv['urgency'])): ?><span class="tag-urgent">Urgent</span><?php endif; ?>
          </div>
          <div class="todo-meta">
            <?= intervention_category_badge((string)($iv['category'] ?? '')) ?>
            <?php if (!empty($iv['client_city'])): ?><span><?= e($iv['client_city']) ?></span><?php endif; ?>
            <?php if (!empty($iv['scheduled_date'])): ?><span><?= e(date('d/m', strtotime($iv['scheduled_date']))) ?></span><?php endif; ?>
            <?php if ($miss): ?><span class="todo-miss">Sans <?= e(implode(' ni ', $miss)) ?></span><?php endif; ?>
          </div>
        </a>
        <?php endforeach; ?>
        </div>
        <?php if (count($toPlan) > 12): ?>
          <div style="padding:.6rem 1.1rem;font-size:.8rem;color:var(--d-t2);">+ <?= count($toPlan) - 12 ?> autre(s)</div>
        <?php endif; ?>
      <?php endif; ?>
    </div>

  </div>
</div>

<script>
// Actualisation discrète toutes les 5 minutes (sauf si l'onglet est caché).
setInterval(function () { if (!document.hidden) location.reload(); }, 300000);
</script>
<?php require __DIR__.'/partials/footer.php'; ?>
