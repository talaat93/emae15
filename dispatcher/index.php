<?php
declare(strict_types=1);
$pageTitle  = 'Dashboard';
$dispSection = 'dashboard';
require __DIR__.'/partials/header.php';

// ─── Data ───────────────────────────────────────────────────
$kpis        = dispatcher_kpis();
$todayIvs    = all_interventions(['date' => date('Y-m-d')]);
$urgentIvs   = all_interventions(['urgency' => true]);
// Limit urgent list to 5 for the panel
$urgentPanel = array_slice($urgentIvs, 0, 5);

// Helper: format a scheduled time
function fmt_time(?string $t): string {
    if ($t === null || $t === '') return '—';
    return substr($t, 0, 5);
}
// Helper: client full name
function client_name(array $iv): string {
    $n = trim(($iv['firstname'] ?? '').' '.($iv['lastname'] ?? ''));
    return $n !== '' ? $n : '(inconnu)';
}

$viewBase = url_for('dispatcher/intervention_view.php');
?>
<!-- ─── TOPBAR ─── -->
<div class="d-topbar">
  <div>
    <div class="d-topbar-title">🏠 Dashboard</div>
    <div class="d-topbar-sub">Vue en temps réel — <?= date('l d F Y') ?></div>
  </div>
  <div class="d-topbar-actions">
    <a href="<?= e(url_for('dispatcher/intervention_new.php')) ?>" class="d-btn d-btn--primary d-btn--sm">➕ Nouvelle intervention</a>
    <span style="font-size:.75rem;color:#4a5f8a;" id="refresh-timer">Actualisation dans 5:00</span>
  </div>
</div>

<!-- ─── CONTENT ─── -->
<div class="d-content">

  <!-- ═══════════ KPI GRID ═══════════ -->
  <div class="d-grid-4" style="margin-bottom:1.75rem;">

    <!-- Aujourd'hui -->
    <div class="kpi-card blue">
      <div class="kpi-icon">📅</div>
      <div class="kpi-value"><?= $kpis['today_total'] ?></div>
      <div class="kpi-label">Interventions aujourd'hui</div>
    </div>

    <!-- En attente / Confirmées -->
    <div class="kpi-card orange">
      <div class="kpi-icon">⏳</div>
      <div class="kpi-value"><?= $kpis['waiting'] ?></div>
      <div class="kpi-label">En attente / Confirmées</div>
    </div>

    <!-- En cours -->
    <div class="kpi-card cyan">
      <div class="kpi-icon">🚗</div>
      <div class="kpi-value"><?= $kpis['in_progress'] ?></div>
      <div class="kpi-label">En cours (en route + sur place)</div>
    </div>

    <!-- Terminées aujourd'hui -->
    <div class="kpi-card green">
      <div class="kpi-icon">✅</div>
      <div class="kpi-value"><?= $kpis['done_today'] ?></div>
      <div class="kpi-label">Terminées aujourd'hui</div>
    </div>

    <!-- Urgences actives -->
    <div class="kpi-card red">
      <div class="kpi-icon">🚨</div>
      <div class="kpi-value"><?= $kpis['urgent'] ?></div>
      <div class="kpi-label">Urgences actives</div>
    </div>

    <!-- En retard -->
    <div class="kpi-card red">
      <div class="kpi-icon">⚠️</div>
      <div class="kpi-value"><?= $kpis['late'] ?></div>
      <div class="kpi-label">En retard</div>
    </div>

    <!-- Techniciens actifs -->
    <div class="kpi-card">
      <div class="kpi-icon">👷</div>
      <div class="kpi-value"><?= $kpis['techs_active'] ?></div>
      <div class="kpi-label">Techniciens actifs</div>
    </div>

    <!-- CA du mois -->
    <div class="kpi-card green">
      <div class="kpi-icon">💶</div>
      <div class="kpi-value"><?= number_format($kpis['ca_month'], 0, ',', ' ') ?> €</div>
      <div class="kpi-label">CA du mois (HT)</div>
    </div>

  </div><!-- /.d-grid-4 -->

  <!-- ═══════════ TWO-COLUMN LAYOUT ═══════════ -->
  <div style="display:grid;grid-template-columns:1fr 380px;gap:1.5rem;align-items:start;">

    <!-- ─── TODAY'S INTERVENTIONS ─── -->
    <div class="d-card">
      <div class="d-card-head">
        <span class="d-card-title">📋 Interventions du jour</span>
        <div style="display:flex;align-items:center;gap:.6rem;">
          <span style="font-size:.75rem;color:#8fa0c4;"><?= count($todayIvs) ?> intervention<?= count($todayIvs) !== 1 ? 's' : '' ?></span>
          <a href="<?= e(url_for('dispatcher/interventions.php')) ?>" class="d-btn d-btn--ghost d-btn--sm">Tout voir →</a>
        </div>
      </div>

      <?php if (empty($todayIvs)): ?>
        <div class="d-empty">
          <div class="d-empty-icon">📭</div>
          <div>Aucune intervention planifiée aujourd'hui</div>
          <a href="<?= e(url_for('dispatcher/intervention_new.php')) ?>" class="d-btn d-btn--primary d-btn--sm" style="margin-top:1rem;">➕ Créer une intervention</a>
        </div>
      <?php else: ?>
        <div style="overflow-x:auto;">
          <table class="d-table">
            <thead>
              <tr>
                <th>Réf.</th>
                <th>Client</th>
                <th>Catégorie</th>
                <th>Statut</th>
                <th>Heure</th>
                <th>Technicien</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($todayIvs as $iv): ?>
              <tr>
                <td>
                  <?php if ((int)($iv['urgency'] ?? 0)): ?>
                    <span class="urgency-dot" title="Urgence"></span>
                  <?php endif; ?>
                  <a href="<?= e($viewBase.'?id='.(int)$iv['id']) ?>" style="color:#F07B1D;text-decoration:none;font-weight:700;font-size:.82rem;">
                    <?= e($iv['ref'] ?? '#'.(int)$iv['id']) ?>
                  </a>
                </td>
                <td>
                  <a href="<?= e($viewBase.'?id='.(int)$iv['id']) ?>" style="color:#e8ecf5;text-decoration:none;font-weight:600;">
                    <?= e(client_name($iv)) ?>
                  </a>
                  <?php if (!empty($iv['client_city'])): ?>
                    <div style="font-size:.72rem;color:#4a5f8a;"><?= e($iv['client_city']) ?></div>
                  <?php endif; ?>
                </td>
                <td>
                  <?php if (!empty($iv['category'])): ?>
                    <?= intervention_category_badge((string)$iv['category']) ?>
                  <?php else: ?>
                    <span style="color:#4a5f8a;font-size:.78rem;">—</span>
                  <?php endif; ?>
                </td>
                <td><?= intervention_status_badge((string)($iv['status'] ?? 'nouveau')) ?></td>
                <td style="color:#8fa0c4;font-size:.82rem;white-space:nowrap;"><?= e(fmt_time($iv['scheduled_time'] ?? null)) ?></td>
                <td style="font-size:.82rem;color:#8fa0c4;">
                  <?= e($iv['tech_name'] ?? '—') ?>
                </td>
                <td>
                  <a href="<?= e($viewBase.'?id='.(int)$iv['id']) ?>" class="d-btn d-btn--ghost d-btn--sm">Voir</a>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div><!-- /.d-card today -->

    <!-- ─── URGENCES PANEL ─── -->
    <div class="d-card" style="border-color:rgba(239,68,68,.2);background:rgba(239,68,68,.03);">
      <div class="d-card-head" style="border-color:rgba(239,68,68,.15);">
        <span class="d-card-title" style="color:#ef4444;">🚨 Urgences actives</span>
        <span style="font-size:.75rem;color:#8fa0c4;"><?= count($urgentIvs) ?> au total</span>
      </div>

      <?php if (empty($urgentIvs)): ?>
        <div class="d-empty" style="padding:2rem 1rem;">
          <div style="font-size:1.5rem;margin-bottom:.5rem;">✅</div>
          <div style="font-size:.82rem;color:#4a5f8a;">Aucune urgence active</div>
        </div>
      <?php else: ?>
        <div style="padding:.5rem 0;">
          <?php foreach ($urgentPanel as $iv): ?>
          <a href="<?= e($viewBase.'?id='.(int)$iv['id']) ?>" style="display:block;padding:.9rem 1.25rem;border-bottom:1px solid rgba(239,68,68,.08);text-decoration:none;transition:background .15s;"
             onmouseover="this.style.background='rgba(239,68,68,.06)'" onmouseout="this.style.background='transparent'">
            <div style="display:flex;align-items:center;justify-content:space-between;gap:.5rem;margin-bottom:.35rem;">
              <span style="font-size:.78rem;font-weight:800;color:#F07B1D;"><?= e($iv['ref'] ?? '#'.(int)$iv['id']) ?></span>
              <?= intervention_status_badge((string)($iv['status'] ?? 'nouveau')) ?>
            </div>
            <div style="font-size:.84rem;font-weight:700;color:#e8ecf5;margin-bottom:.2rem;"><?= e(client_name($iv)) ?></div>
            <div style="display:flex;align-items:center;gap:.5rem;flex-wrap:wrap;">
              <?php if (!empty($iv['category'])): ?>
                <?= intervention_category_badge((string)$iv['category']) ?>
              <?php endif; ?>
              <?php if (!empty($iv['scheduled_date'])): ?>
                <span style="font-size:.72rem;color:#8fa0c4;">📅 <?= e(date('d/m', strtotime((string)$iv['scheduled_date']))) ?><?= !empty($iv['scheduled_time']) ? ' '.e(fmt_time($iv['scheduled_time'])) : '' ?></span>
              <?php else: ?>
                <span style="font-size:.72rem;color:#ef4444;">Non planifiée</span>
              <?php endif; ?>
              <?php if (!empty($iv['tech_name'])): ?>
                <span style="font-size:.72rem;color:#8fa0c4;">👷 <?= e($iv['tech_name']) ?></span>
              <?php else: ?>
                <span style="font-size:.72rem;color:#f59e0b;">⚠️ Non assignée</span>
              <?php endif; ?>
            </div>
          </a>
          <?php endforeach; ?>
          <?php if (count($urgentIvs) > 5): ?>
          <div style="padding:.75rem 1.25rem;text-align:center;">
            <a href="<?= e(url_for('dispatcher/interventions.php').'?urgency=1') ?>" class="d-btn d-btn--danger d-btn--sm">
              Voir toutes les <?= count($urgentIvs) ?> urgences
            </a>
          </div>
          <?php endif; ?>
        </div>
      <?php endif; ?>
    </div><!-- /.d-card urgences -->

  </div><!-- /.two-col -->

  <!-- ─── STATS ROW ─── -->
  <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:1rem;margin-top:1.5rem;">
    <div class="d-card">
      <div class="d-card-body" style="display:flex;align-items:center;gap:1rem;">
        <div style="font-size:1.8rem;">💶</div>
        <div>
          <div style="font-size:.72rem;color:#8fa0c4;text-transform:uppercase;letter-spacing:.08em;font-weight:700;margin-bottom:.15rem;">CA aujourd'hui</div>
          <div style="font-family:'Syne',Arial,sans-serif;font-size:1.3rem;font-weight:800;color:#22c55e;"><?= number_format($kpis['ca_today'], 0, ',', ' ') ?> €</div>
        </div>
      </div>
    </div>
    <div class="d-card">
      <div class="d-card-body" style="display:flex;align-items:center;gap:1rem;">
        <div style="font-size:1.8rem;">📊</div>
        <div>
          <div style="font-size:.72rem;color:#8fa0c4;text-transform:uppercase;letter-spacing:.08em;font-weight:700;margin-bottom:.15rem;">CA cette semaine</div>
          <div style="font-family:'Syne',Arial,sans-serif;font-size:1.3rem;font-weight:800;color:#3b82f6;"><?= number_format($kpis['ca_week'], 0, ',', ' ') ?> €</div>
        </div>
      </div>
    </div>
    <div class="d-card">
      <div class="d-card-body" style="display:flex;align-items:center;gap:1rem;">
        <div style="font-size:1.8rem;">📋</div>
        <div>
          <div style="font-size:.72rem;color:#8fa0c4;text-transform:uppercase;letter-spacing:.08em;font-weight:700;margin-bottom:.15rem;">Total interventions</div>
          <div style="font-family:'Syne',Arial,sans-serif;font-size:1.3rem;font-weight:800;color:#e8ecf5;"><?= $kpis['total'] ?></div>
        </div>
      </div>
    </div>
  </div><!-- /.stats row -->

</div><!-- /.d-content -->

<script>
// ─── Countdown auto-refresh (5 minutes) ───
(function(){
  var secs = 300;
  var el   = document.getElementById('refresh-timer');
  if (!el) return;
  var iv = setInterval(function(){
    secs--;
    if (secs <= 0) { clearInterval(iv); location.reload(); return; }
    var m = Math.floor(secs / 60);
    var s = secs % 60;
    el.textContent = 'Actualisation dans ' + m + ':' + (s < 10 ? '0' : '') + s;
  }, 1000);
})();
</script>

<?php require __DIR__.'/partials/footer.php'; ?>
