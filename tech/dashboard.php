<?php
declare(strict_types=1);
require_once __DIR__.'/../includes/bootstrap.php';
require_once __DIR__.'/_ui.php';
$tech = require_tech_auth();

$techId   = (int)$tech['id'];
$techName = (string)($tech['name'] ?? 'Technicien');
$firstName = explode(' ', trim($techName))[0] ?: $techName;
$today    = date('Y-m-d');
$view     = in_array($_GET['v'] ?? '', ['next', 'done'], true) ? (string)$_GET['v'] : 'today';

$closed = ['terminé', 'annulé', 'facturé', 'payé', 'devis_envoyé'];

$mine = [];
try {
    $mine = db_fetch_all(
        "SELECT i.*, c.lastname, c.firstname, c.phone AS client_phone, c.address, c.city AS client_city,
                c.postal_code, c.floor, c.digicode
         FROM interventions i
         LEFT JOIN clients c ON c.id = i.client_id
         WHERE i.technician_id = ?
         ORDER BY COALESCE(i.scheduled_date, '9999-12-31'), COALESCE(i.scheduled_time, '23:59'), i.urgency DESC, i.id",
        [$techId]
    );
} catch (Throwable $e) { $mine = []; }

$open = array_values(array_filter($mine, static fn($i) => !in_array($i['status'] ?? '', $closed, true)));
// Aujourd'hui = prévues aujourd'hui + tout ce qui est déjà commencé ou resté en retard.
$todayList = array_values(array_filter($open, static fn($i) =>
    ($i['scheduled_date'] ?? '') === $today
    || in_array($i['status'] ?? '', ['en_route', 'sur_place'], true)
    || (!empty($i['scheduled_date']) && $i['scheduled_date'] < $today)));
$todayIds = array_column($todayList, 'id');
$nextList = array_values(array_filter($open, static fn($i) => !in_array($i['id'], $todayIds, true)));
$doneList = array_reverse(array_values(array_filter($mine, static fn($i) => in_array($i['status'] ?? '', $closed, true))));
$doneList = array_slice($doneList, 0, 30);

$tasks = $view === 'today' ? get_pending_tasks_for_tech($techId) : [];

// Demandes directes issues du formulaire de devis (ancien circuit).
$quoteMissions = [];
if ($view === 'today') {
    try {
        $quoteMissions = db_fetch_all(
            "SELECT * FROM quotes WHERE technician_id = ? AND archived = 0 AND status <> 'terminé' ORDER BY created_at DESC LIMIT 20",
            [$techId]
        );
    } catch (Throwable $e) { $quoteMissions = []; }
}

/** Prochaine étape proposée sur la carte. */
function ta_next_step(array $iv): ?array
{
    return match ($iv['status'] ?? '') {
        'nouveau', 'confirmé', 'assigné' => ['en_route', 'Je pars', 'car'],
        'en_route'  => ['sur_place', 'Je suis arrivé', 'arrive'],
        'sur_place' => ['report', 'Rédiger le rapport', 'doc'],
        default     => null,
    };
}

function ta_job_card(array $iv, string $today, bool $showDate): void
{
    $id     = (int)$iv['id'];
    $url    = url_for('tech/disp_intervention.php?id='.$id);
    $name   = trim(($iv['firstname'] ?? '').' '.($iv['lastname'] ?? '')) ?: 'Client';
    $addr   = ta_address($iv['address'] ?? '', $iv['postal_code'] ?? '', $iv['client_city'] ?? '');
    $status = (string)($iv['status'] ?? '');
    $cat    = intervention_category_config()[$iv['category'] ?? '']['label'] ?? '';
    $what   = trim($cat.($cat && !empty($iv['type_label']) ? ' · ' : '').($iv['type_label'] ?? ''));
    $time   = !empty($iv['scheduled_time']) ? substr((string)$iv['scheduled_time'], 0, 5) : '—';
    $step   = ta_next_step($iv);
    $late   = $step !== null && !empty($iv['scheduled_date']) && $iv['scheduled_date'] < $today;
    $cls    = 'ta-job'.(!empty($iv['urgency']) ? ' is-urgent' : '').(in_array($status, ['en_route', 'sur_place'], true) ? ' is-now' : '');
    ?>
    <div class="<?= $cls ?>">
      <a class="ta-job-time" href="<?= e($url) ?>">
        <?php if ($showDate && !empty($iv['scheduled_date'])): ?>
          <small><?= e(date('d/m', strtotime($iv['scheduled_date']))) ?></small>
        <?php endif; ?>
        <b><?= e($time) ?></b>
        <small><?= e(ta_duration((int)($iv['duration_estimate'] ?? 0))) ?></small>
      </a>
      <div class="ta-job-body">
        <a href="<?= e($url) ?>" style="display:block;">
          <div class="ta-job-top">
            <div class="ta-job-name"><?= e($name) ?></div>
            <?= ta_pill($status) ?>
          </div>
          <?php if ($what !== ''): ?><div class="ta-job-what"><?= e($what) ?></div><?php endif; ?>
          <?php if (!empty($iv['urgency']) || $late): ?>
            <div style="margin-top:.35rem;display:flex;gap:.35rem;">
              <?php if (!empty($iv['urgency'])): ?><span class="ta-urgent">Urgent</span><?php endif; ?>
              <?php if ($late): ?><span class="ta-urgent" style="background:var(--amber-lt);color:#8a5300;">Prévue le <?= e(date('d/m', strtotime($iv['scheduled_date']))) ?></span><?php endif; ?>
            </div>
          <?php endif; ?>
          <?php if ($addr !== ''): ?><div class="ta-job-addr"><?= ta_icon('pin') ?><span><?= e($addr) ?></span></div><?php endif; ?>
        </a>
        <?php if ($step): ?>
        <div class="ta-job-actions">
          <?php if (!empty($iv['client_phone'])): ?>
            <a class="ta-btn sq" href="<?= e(ta_tel($iv['client_phone'])) ?>" aria-label="Appeler le client"><?= ta_icon('phone') ?></a>
          <?php endif; ?>
          <?php if ($addr !== ''): ?>
            <a class="ta-btn sq" href="<?= e(ta_route_url($addr)) ?>" target="_blank" rel="noopener" aria-label="Itinéraire"><?= ta_icon('route') ?></a>
          <?php endif; ?>
          <?php if ($step[0] === 'report'): ?>
            <a class="ta-btn primary grow" href="<?= e($url.'#rapport') ?>"><?= ta_icon($step[2]) ?><?= e($step[1]) ?></a>
          <?php else: ?>
            <form method="post" action="<?= e($url) ?>" style="flex:1;display:flex;">
              <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
              <input type="hidden" name="action" value="status">
              <input type="hidden" name="status" value="<?= e($step[0]) ?>">
              <input type="hidden" name="back" value="list">
              <button type="submit" class="ta-btn <?= $step[0] === 'en_route' ? 'dark' : 'primary' ?> grow"><?= ta_icon($step[2]) ?><?= e($step[1]) ?></button>
            </form>
          <?php endif; ?>
        </div>
        <?php endif; ?>
      </div>
    </div>
    <?php
}

ta_head('Ma journée — '.company_name());
?>
<header class="ta-top">
  <div class="grow">
    <h1><?= $view === 'today' ? 'Bonjour '.e($firstName) : ($view === 'next' ? 'À venir' : 'Terminées') ?></h1>
    <div class="sub"><?= $view === 'today' ? e(ta_fr_date($today)) : e($techName) ?></div>
  </div>
  <a class="ta-icon-btn" href="<?= e(url_for('tech/logout.php')) ?>" aria-label="Déconnexion" title="Déconnexion"><?= ta_icon('logout') ?></a>
</header>

<main class="ta-main">
  <?php if ($m = flash('success')): ?><div class="ta-flash ok"><?= e($m) ?></div><?php endif; ?>
  <?php if ($m = flash('error')): ?><div class="ta-flash err"><?= e($m) ?></div><?php endif; ?>

  <?php if ($view === 'today'): ?>

    <div class="ta-h2">Mes interventions <span><?= count($todayList) ?></span></div>
    <?php if (!$todayList): ?>
      <div class="ta-empty"><b>Rien de prévu aujourd'hui</b>Les nouvelles interventions apparaîtront ici dès que le dispatcher vous les attribue.</div>
    <?php endif; ?>
    <?php foreach ($todayList as $iv) ta_job_card($iv, $today, false); ?>

    <?php if ($tasks): ?>
      <div class="ta-h2">Rappels du dispatcher <span><?= count($tasks) ?></span></div>
      <?php foreach ($tasks as $tk): ?>
        <div class="ta-task">
          <form method="post" action="<?= e(url_for('tech/tasks_action.php')) ?>">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="complete">
            <input type="hidden" name="task_id" value="<?= (int)$tk['id'] ?>">
            <button type="submit" class="ta-check" aria-label="Marquer comme fait" title="Marquer comme fait"><?= ta_icon('check') ?></button>
          </form>
          <div class="grow">
            <b><?= e($tk['title']) ?><?php if (!empty($tk['urgent'])): ?> <span class="ta-urgent">Urgent</span><?php endif; ?></b>
            <?php if (!empty($tk['description'])): ?><small><?= e($tk['description']) ?></small><br><?php endif; ?>
            <?php if (!empty($tk['due_date'])): ?>
              <small>Pour le <?= e(date('d/m', strtotime($tk['due_date']))) ?><?= !empty($tk['due_time']) ? ' à '.e(substr((string)$tk['due_time'], 0, 5)) : '' ?></small>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>

    <?php if ($quoteMissions): ?>
      <div class="ta-h2">Demandes du site <span><?= count($quoteMissions) ?></span></div>
      <?php foreach ($quoteMissions as $q):
        $qDate = !empty($q['intervention_date']) ? date('d/m H:i', strtotime($q['intervention_date'])) : '';
      ?>
        <a class="ta-job" href="<?= e(url_for('tech/intervention.php?id='.(int)$q['id'])) ?>">
          <div class="ta-job-body" style="padding-left:.9rem;">
            <div class="ta-job-top">
              <div class="ta-job-name"><?= e($q['full_name'] ?? '—') ?></div>
              <span class="ta-pill"><?= e(ucfirst((string)($q['status'] ?? 'nouveau'))) ?></span>
            </div>
            <div class="ta-job-what"><?= e(trim(($q['service_type'] ?? '').($qDate ? ' · '.$qDate : ''), ' ·')) ?></div>
            <?php if (!empty($q['city'])): ?><div class="ta-job-addr"><?= ta_icon('pin') ?><span><?= e($q['city']) ?></span></div><?php endif; ?>
          </div>
        </a>
      <?php endforeach; ?>
    <?php endif; ?>

  <?php elseif ($view === 'next'): ?>

    <?php if (!$nextList): ?>
      <div class="ta-empty"><b>Aucune intervention à venir</b>Votre planning des prochains jours est vide pour l'instant.</div>
    <?php endif; ?>
    <?php
    $lastDay = null;
    foreach ($nextList as $iv):
      $day = $iv['scheduled_date'] ?? '';
      if ($day !== $lastDay):
        $lastDay = $day; ?>
        <div class="ta-h2"><?= $day ? e(ta_fr_date($day)) : 'Date à confirmer' ?></div>
      <?php endif;
      ta_job_card($iv, $today, false);
    endforeach; ?>

  <?php else: ?>

    <?php if (!$doneList): ?>
      <div class="ta-empty"><b>Aucune intervention terminée</b>Vos rapports apparaîtront ici.</div>
    <?php endif; ?>
    <?php foreach ($doneList as $iv) ta_job_card($iv, $today, true); ?>

  <?php endif; ?>
</main>

<?= ta_nav($view, count($todayList)) ?>
</body>
</html>
