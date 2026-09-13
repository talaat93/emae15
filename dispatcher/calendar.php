<?php
declare(strict_types=1);
$pageTitle = 'Calendrier';
$dispSection = 'calendar';
$extraHead = '<link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.css" rel="stylesheet" />
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/locales/fr.global.min.js"></script>
<style>
#calendar { height: 100%; }
.fc { height: 100%; font-family: inherit; }
.fc .fc-toolbar-title { font-size: 1.1rem; font-weight: 700; color: #e8ecf5; }
.fc .fc-button { background: rgba(255,255,255,.08); border: 1px solid rgba(255,255,255,.15); color: #e8ecf5; font-size: .78rem; font-weight: 600; border-radius: 6px; }
.fc .fc-button:hover { background: rgba(255,255,255,.15); border-color: rgba(255,255,255,.25); color: #fff; }
.fc .fc-button-primary:not(:disabled).fc-button-active,
.fc .fc-button-primary:not(:disabled):active { background: rgba(238,125,26,.3); border-color: #ee7d1a; color: #ee7d1a; }
.fc .fc-col-header-cell-cushion { color: #8fa0c4; font-size: .75rem; font-weight: 700; text-transform: uppercase; letter-spacing: .06em; }
.fc .fc-daygrid-day-number, .fc .fc-daygrid-day-top a { color: #8fa0c4; font-size: .8rem; }
.fc .fc-daygrid-day.fc-day-today, .fc .fc-timegrid-col.fc-day-today { background: rgba(238,125,26,.06) !important; }
.fc-theme-standard td, .fc-theme-standard th, .fc-theme-standard .fc-scrollgrid { border-color: rgba(255,255,255,.08); }
.fc .fc-timegrid-slot-label { color: #8fa0c4; font-size: .72rem; }
.fc-event { border-radius: 6px !important; border: none !important; padding: .1rem .3rem; font-size: .75rem; font-weight: 600; cursor: pointer; }
.fc-event:hover { filter: brightness(1.15); }
.fc-business-hours { background: rgba(255,255,255,.02); }
.legend-dot { display: inline-block; width: 10px; height: 10px; border-radius: 50%; margin-right: .35rem; }
.today-item { display: flex; gap: .65rem; align-items: flex-start; padding: .7rem .85rem; border-bottom: 1px solid rgba(255,255,255,.06); cursor: pointer; transition: background .15s; }
.today-item:hover { background: rgba(255,255,255,.04); }
.today-item:last-child { border-bottom: none; }
.today-dot { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; margin-top: .35rem; }
.today-title { font-size: .8rem; font-weight: 600; color: #e8ecf5; line-height: 1.35; }
.today-meta { font-size: .72rem; color: #8fa0c4; margin-top: .15rem; }
</style>';
require __DIR__.'/partials/header.php';

// Today's interventions for sidebar
$todayInterventions = all_interventions(['date' => date('Y-m-d')]);
$categoryConfig = intervention_category_config();
$statusConfig   = intervention_status_config();
?>

<div class="d-topbar">
  <button class="d-menu-toggle" id="d-menu-toggle" aria-label="Menu">
    <span></span><span></span><span></span>
  </button>
  <div class="d-topbar-title">
    <span class="d-topbar-ico">📅</span> Calendrier des interventions
  </div>
  <div class="d-topbar-actions">
    <a href="<?= e(url_for('dispatcher/intervention_new.php')) ?>" class="d-btn d-btn-primary d-btn-sm">
      + Nouvelle intervention
    </a>
  </div>
</div>

<div class="d-content" style="padding:1rem;">
  <div style="display:grid;grid-template-columns:1fr 280px;gap:1rem;height:calc(100vh - 120px);">

    <!-- Calendrier principal -->
    <div style="background:rgba(255,255,255,.03);border-radius:12px;padding:1rem;border:1px solid rgba(255,255,255,.08);display:flex;flex-direction:column;overflow:hidden;">
      <div id="calendar" style="flex:1;min-height:0;"></div>
      <!-- Légende catégories -->
      <div style="display:flex;flex-wrap:wrap;gap:.5rem 1rem;padding:.75rem 0 .25rem;border-top:1px solid rgba(255,255,255,.06);margin-top:.5rem;">
        <?php foreach ($categoryConfig as $key => $cat): ?>
        <span style="display:inline-flex;align-items:center;font-size:.72rem;color:#8fa0c4;">
          <span class="legend-dot" style="background:<?= e($cat['color']) ?>;"></span>
          <?= e($cat['icon']) ?> <?= e($cat['label']) ?>
        </span>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Sidebar aujourd'hui -->
    <div style="background:rgba(255,255,255,.03);border-radius:12px;border:1px solid rgba(255,255,255,.08);display:flex;flex-direction:column;overflow:hidden;">
      <div style="padding:.85rem 1rem;border-bottom:1px solid rgba(255,255,255,.08);flex-shrink:0;">
        <div style="font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:#8fa0c4;">Aujourd'hui</div>
        <div style="font-size:1rem;font-weight:700;color:#e8ecf5;margin-top:.15rem;"><?= date('l d MMMM', time()) ?><?= e(mb_ucfirst(strftime('%A %d %B'), 'UTF-8')) ?></div>
        <div style="font-size:.82rem;font-weight:700;color:#e8ecf5;margin-top:.15rem;"><?= e(mb_strtolower(strftime('%A %d %B %Y'), 'UTF-8')) ?></div>
        <?php
        // Format date in French
        $days = ['Lundi','Mardi','Mercredi','Jeudi','Vendredi','Samedi','Dimanche'];
        $months = ['','Janvier','Février','Mars','Avril','Mai','Juin','Juillet','Août','Septembre','Octobre','Novembre','Décembre'];
        $dow = (int)date('N') - 1;
        $d   = (int)date('j');
        $mo  = (int)date('n');
        $yr  = date('Y');
        ?>
      </div>
      <div style="padding:.5rem .5rem .35rem;border-bottom:1px solid rgba(255,255,255,.08);flex-shrink:0;">
        <div style="font-size:.78rem;font-weight:700;color:#e8ecf5;padding:.35rem .4rem;"><?= $days[$dow] ?> <?= $d ?> <?= $months[$mo] ?> <?= $yr ?></div>
        <div style="font-size:.72rem;color:#8fa0c4;padding:0 .4rem .35rem;"><?= count($todayInterventions) ?> intervention<?= count($todayInterventions) > 1 ? 's' : '' ?> planifiée<?= count($todayInterventions) > 1 ? 's' : '' ?></div>
      </div>
      <div style="flex:1;overflow-y:auto;">
        <?php if (empty($todayInterventions)): ?>
          <div style="padding:2rem 1rem;text-align:center;color:#8fa0c4;font-size:.82rem;">
            <div style="font-size:1.8rem;margin-bottom:.5rem;">📅</div>
            Aucune intervention<br>planifiée aujourd'hui
          </div>
        <?php else: ?>
          <?php foreach ($todayInterventions as $interv): ?>
          <?php
            $catKey  = (string)($interv['category'] ?? '');
            $catConf = $categoryConfig[$catKey] ?? ['color'=>'#8fa0c4','icon'=>'🔧','label'=>$catKey];
            $stKey   = (string)($interv['status'] ?? '');
            $stConf  = $statusConfig[$stKey] ?? ['color'=>'#8fa0c4','label'=>$stKey];
            $clientName = trim(($interv['firstname']??'').' '.($interv['lastname']??'')) ?: 'Client #'.$interv['client_id'];
            $time = $interv['scheduled_time'] ? date('H:i', strtotime($interv['scheduled_time'])) : '';
          ?>
          <a href="<?= e(url_for('dispatcher/intervention_view.php?id='.$interv['id'])) ?>" style="display:block;text-decoration:none;">
            <div class="today-item">
              <div class="today-dot" style="background:<?= e($catConf['color']) ?>;<?= $interv['urgency'] ? 'box-shadow:0 0 0 3px rgba(239,68,68,.4);' : '' ?>"></div>
              <div style="flex:1;min-width:0;">
                <div class="today-title"><?= e($catConf['icon']) ?> <?= e($clientName) ?></div>
                <?php if ($time): ?>
                <div class="today-meta">🕐 <?= e($time) ?><?= $interv['city'] ? ' · '.e($interv['client_city'] ?? '') : '' ?></div>
                <?php endif; ?>
                <div class="today-meta" style="margin-top:.2rem;">
                  <span style="color:<?= e($stConf['color']) ?>;font-weight:700;"><?= e($stConf['label']) ?></span>
                  <?php if ($interv['tech_name']): ?>
                   · <?= e($interv['tech_name']) ?>
                  <?php endif; ?>
                </div>
                <?php if ($interv['urgency']): ?>
                <div class="today-meta" style="color:#ef4444;font-weight:700;">🚨 URGENT</div>
                <?php endif; ?>
              </div>
            </div>
          </a>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
      <div style="padding:.75rem;border-top:1px solid rgba(255,255,255,.08);flex-shrink:0;">
        <a href="<?= e(url_for('dispatcher/interventions.php')) ?>" style="display:block;text-align:center;font-size:.75rem;color:#8fa0c4;text-decoration:none;padding:.4rem;border-radius:6px;background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.08);">Toutes les interventions →</a>
      </div>
    </div>

  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
  var categoryColors = <?= json_encode(array_map(fn($c) => $c['color'], $categoryConfig), JSON_UNESCAPED_SLASHES) ?>;
  var calendarEl = document.getElementById('calendar');
  var dispId = <?= (int)($_SESSION['disp_id'] ?? $_SESSION['admin_id'] ?? 0) ?>;

  var calendar = new FullCalendar.Calendar(calendarEl, {
    locale: 'fr',
    initialView: 'timeGridWeek',
    height: '100%',
    expandRows: true,
    headerToolbar: {
      left: 'prev,next today',
      center: 'title',
      right: 'dayGridMonth,timeGridWeek,timeGridDay'
    },
    slotDuration: '00:30:00',
    slotMinTime: '06:00:00',
    slotMaxTime: '22:00:00',
    businessHours: {
      daysOfWeek: [1, 2, 3, 4, 5, 6],
      startTime: '07:00',
      endTime: '20:00'
    },
    nowIndicator: true,
    editable: true,
    eventDurationEditable: false,
    allDaySlot: false,
    navLinks: true,
    weekNumbers: false,
    dayMaxEvents: true,
    eventTimeFormat: { hour: '2-digit', minute: '2-digit', hour12: false },
    events: function(info, successCallback, failureCallback) {
      var start = info.startStr.substring(0, 10);
      var end   = info.endStr.substring(0, 10);
      fetch('<?= e(url_for('dispatcher/api.php')) ?>?action=calendar_events&start=' + encodeURIComponent(start) + '&end=' + encodeURIComponent(end))
        .then(function(r) { return r.json(); })
        .then(function(data) {
          if (Array.isArray(data)) successCallback(data);
          else failureCallback('Erreur API');
        })
        .catch(function(err) { console.error(err); failureCallback(err); });
    },
    eventDrop: function(info) {
      var ev = info.event;
      var newDate = ev.startStr.substring(0, 10);
      var newTime = ev.startStr.length >= 16 ? ev.startStr.substring(11, 16) : '08:00';
      fetch('<?= e(url_for('dispatcher/api.php')) ?>', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=drag_event&id=' + encodeURIComponent(ev.id) + '&new_date=' + encodeURIComponent(newDate) + '&new_time=' + encodeURIComponent(newTime)
      })
        .then(function(r) { return r.json(); })
        .then(function(data) {
          if (!data.success) {
            alert('Erreur lors du déplacement.');
            info.revert();
          }
        })
        .catch(function() { info.revert(); });
    },
    eventClick: function(info) {
      var id = info.event.id;
      if (id) window.location.href = '<?= e(url_for('dispatcher/intervention_view.php')) ?>?id=' + id;
    },
    eventDidMount: function(info) {
      // Urgency pulsing ring
      var props = info.event.extendedProps;
      if (props && props.urgency) {
        info.el.style.boxShadow = '0 0 0 2px rgba(239,68,68,.6)';
        info.el.style.animation = 'pulse-red 1.5s infinite';
      }
      // Tooltip
      if (props) {
        info.el.title = [
          props.client || '',
          props.category || '',
          props.status || '',
          props.technician || ''
        ].filter(Boolean).join(' · ');
      }
    }
  });

  calendar.render();
});
</script>

<style>
@keyframes pulse-red {
  0%, 100% { box-shadow: 0 0 0 2px rgba(239,68,68,.6); }
  50% { box-shadow: 0 0 0 4px rgba(239,68,68,.3); }
}
</style>

<?php require __DIR__.'/partials/footer.php'; ?>
