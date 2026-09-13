<?php
declare(strict_types=1);
$dispSection = 'tasks';
require_once __DIR__.'/../includes/bootstrap.php';
$disp = require_dispatcher_auth();
$dispId = (int)$disp['id'];

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string)($_POST['action'] ?? '');

    if ($action === 'create') {
        $title = trim((string)($_POST['title'] ?? ''));
        if ($title !== '') {
            create_task([
                'dispatcher_id' => $dispId,
                'technician_id' => !empty($_POST['technician_id']) ? (int)$_POST['technician_id'] : null,
                'title'         => $title,
                'description'   => trim((string)($_POST['description'] ?? '')) ?: null,
                'due_date'      => trim((string)($_POST['due_date'] ?? '')) ?: null,
                'due_time'      => trim((string)($_POST['due_time'] ?? '')) ?: null,
                'urgent'        => isset($_POST['urgent']) ? 1 : 0,
                'status'        => 'pending',
            ]);
            flash('success', 'Tâche créée avec succès.');
        } else {
            flash('error', 'Le titre est obligatoire.');
        }
        redirect_to('dispatcher/tasks.php');
    }

    if ($action === 'complete') {
        $id = (int)($_POST['task_id'] ?? 0);
        if ($id > 0) {
            update_task($id, ['status' => 'done']);
            flash('success', 'Tâche marquée comme terminée.');
        }
        redirect_to('dispatcher/tasks.php');
    }

    if ($action === 'delete') {
        $id = (int)($_POST['task_id'] ?? 0);
        if ($id > 0) {
            delete_task($id);
            flash('success', 'Tâche supprimée.');
        }
        redirect_to('dispatcher/tasks.php');
    }
}

// Filters
$filterUrgent    = isset($_GET['urgent']) && $_GET['urgent'] === '1';
$filterTechId    = !empty($_GET['technician_id']) ? (int)$_GET['technician_id'] : 0;
$filterStatus    = in_array($_GET['status'] ?? '', ['pending','done','all'], true) ? ($_GET['status'] ?? 'all') : 'all';

$taskFilters = [];
if ($filterUrgent)      $taskFilters['urgent'] = true;
if ($filterTechId > 0)  $taskFilters['technician_id'] = $filterTechId;
if ($filterStatus !== 'all') $taskFilters['status'] = $filterStatus;

$tasks    = all_tasks($taskFilters);
$techs    = all_technicians();

$pageTitle = 'Tâches & Rappels';
require_once __DIR__.'/partials/header.php';
?>

<div class="d-topbar">
  <div style="display:flex;align-items:center;gap:.75rem;">
    <button class="d-menu-btn" id="d-menu-toggle" style="background:none;border:none;cursor:pointer;padding:.35rem;color:var(--d-txt2);">
      <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
    </button>
    <h1 class="d-topbar-title">📋 Tâches &amp; Rappels</h1>
  </div>
  <div style="display:flex;align-items:center;gap:.65rem;">
    <a href="#create-form" class="d-btn d-btn--primary" style="font-size:.82rem;">➕ Nouvelle tâche</a>
  </div>
</div>

<div class="d-content">

  <!-- Filters bar -->
  <div class="d-card" style="margin-bottom:1.25rem;">
    <form method="get" action="" style="display:flex;flex-wrap:wrap;gap:.75rem;align-items:flex-end;">
      <div class="d-field" style="min-width:150px;">
        <label class="d-label">Statut</label>
        <select name="status" class="d-select" onchange="this.form.submit()">
          <option value="all" <?= $filterStatus === 'all' ? 'selected' : '' ?>>Tous</option>
          <option value="pending" <?= $filterStatus === 'pending' ? 'selected' : '' ?>>En attente</option>
          <option value="done" <?= $filterStatus === 'done' ? 'selected' : '' ?>>Terminées</option>
        </select>
      </div>
      <div class="d-field" style="min-width:170px;">
        <label class="d-label">Technicien</label>
        <select name="technician_id" class="d-select" onchange="this.form.submit()">
          <option value="">Tous les techniciens</option>
          <?php foreach ($techs as $t): ?>
          <option value="<?= (int)$t['id'] ?>" <?= $filterTechId === (int)$t['id'] ? 'selected' : '' ?>><?= e($t['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="d-field" style="display:flex;align-items:flex-end;gap:.5rem;">
        <label style="display:flex;align-items:center;gap:.4rem;font-size:.8rem;font-weight:600;color:var(--d-txt2);cursor:pointer;padding-bottom:.1rem;">
          <input type="checkbox" name="urgent" value="1" <?= $filterUrgent ? 'checked' : '' ?> onchange="this.form.submit()" style="accent-color:#ef4444;width:15px;height:15px;">
          Urgents seulement
        </label>
      </div>
      <?php if ($filterUrgent || $filterTechId > 0 || $filterStatus !== 'all'): ?>
      <div style="display:flex;align-items:flex-end;">
        <a href="dispatcher/tasks.php" class="d-btn" style="font-size:.78rem;padding:.45rem .85rem;">✕ Réinitialiser</a>
      </div>
      <?php endif; ?>
    </form>
  </div>

  <div style="display:grid;grid-template-columns:1fr 380px;gap:1.5rem;align-items:start;">

    <!-- Tasks list -->
    <div>
      <?php if (empty($tasks)): ?>
      <div class="d-card" style="text-align:center;padding:3rem 1.5rem;color:var(--d-txt2);">
        <div style="font-size:2.5rem;margin-bottom:.75rem;">📋</div>
        <div style="font-weight:700;font-size:1rem;margin-bottom:.35rem;">Aucune tâche trouvée</div>
        <div style="font-size:.85rem;">Créez votre première tâche avec le formulaire →</div>
      </div>
      <?php else: ?>
      <?php foreach ($tasks as $tk): ?>
      <?php
        $isUrgent = !empty($tk['urgent']);
        $isDone   = ($tk['status'] ?? '') === 'done';
        $dueStr   = '';
        if (!empty($tk['due_date'])) {
            $dueStr = date('d/m/Y', strtotime($tk['due_date']));
            if (!empty($tk['due_time'])) $dueStr .= ' à ' . date('H:i', strtotime($tk['due_time']));
        }
      ?>
      <div class="d-card" style="margin-bottom:.75rem;<?= $isDone ? 'opacity:.6;' : '' ?><?= $isUrgent && !$isDone ? 'border-left:4px solid #ef4444;' : ($isDone ? 'border-left:4px solid #10b981;' : 'border-left:4px solid var(--d-orange);') ?>">
        <div class="d-card-head" style="display:flex;align-items:flex-start;justify-content:space-between;gap:.75rem;padding:1rem 1.25rem .65rem;">
          <div style="flex:1;min-width:0;">
            <div style="display:flex;align-items:center;gap:.5rem;flex-wrap:wrap;margin-bottom:.3rem;">
              <?php if ($isUrgent && !$isDone): ?>
              <span style="background:#ef4444;color:#fff;font-size:.62rem;font-weight:800;padding:.15rem .5rem;border-radius:20px;text-transform:uppercase;letter-spacing:.06em;">🚨 URGENT</span>
              <?php endif; ?>
              <?php if ($isDone): ?>
              <span style="background:#10b981;color:#fff;font-size:.62rem;font-weight:800;padding:.15rem .5rem;border-radius:20px;text-transform:uppercase;letter-spacing:.06em;">✅ TERMINÉ</span>
              <?php endif; ?>
              <span class="d-card-title" style="font-size:.97rem;<?= $isDone ? 'text-decoration:line-through;color:var(--d-txt2);' : '' ?>"><?= e($tk['title']) ?></span>
            </div>
            <div style="display:flex;flex-wrap:wrap;gap:.35rem .85rem;font-size:.77rem;color:var(--d-txt2);">
              <?php if (!empty($tk['tech_name'])): ?>
              <span>👷 <?= e($tk['tech_name']) ?></span>
              <?php else: ?>
              <span style="color:var(--d-txt3);">👷 Tous / non assigné</span>
              <?php endif; ?>
              <?php if ($dueStr !== ''): ?>
              <span>📅 <?= e($dueStr) ?></span>
              <?php endif; ?>
              <?php if (!empty($tk['disp_name'])): ?>
              <span>👤 <?= e($tk['disp_name']) ?></span>
              <?php endif; ?>
            </div>
            <?php if (!empty($tk['description'])): ?>
            <div style="font-size:.8rem;color:var(--d-txt2);margin-top:.5rem;padding:.45rem .65rem;background:var(--d-bg);border-radius:6px;">
              <?= e($tk['description']) ?>
            </div>
            <?php endif; ?>
          </div>
          <?php if (!$isDone): ?>
          <div style="display:flex;gap:.45rem;flex-shrink:0;">
            <form method="post" action="" style="display:inline;">
              <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
              <input type="hidden" name="action" value="complete">
              <input type="hidden" name="task_id" value="<?= (int)$tk['id'] ?>">
              <button type="submit" class="d-btn d-btn--primary" style="font-size:.75rem;padding:.4rem .75rem;" title="Marquer terminée">✅</button>
            </form>
            <form method="post" action="" style="display:inline;" onsubmit="return confirm('Supprimer cette tâche ?')">
              <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="task_id" value="<?= (int)$tk['id'] ?>">
              <button type="submit" class="d-btn" style="font-size:.75rem;padding:.4rem .75rem;color:#ef4444;border-color:#fecaca;" title="Supprimer">🗑</button>
            </form>
          </div>
          <?php else: ?>
          <form method="post" action="" style="display:inline;" onsubmit="return confirm('Supprimer cette tâche ?')">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="task_id" value="<?= (int)$tk['id'] ?>">
            <button type="submit" class="d-btn" style="font-size:.75rem;padding:.4rem .75rem;color:#ef4444;border-color:#fecaca;flex-shrink:0;" title="Supprimer">🗑</button>
          </form>
          <?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
      <?php endif; ?>
    </div>

    <!-- Create form -->
    <div id="create-form">
      <div class="d-card" style="position:sticky;top:1.25rem;">
        <div class="d-card-head" style="padding:1rem 1.25rem .75rem;">
          <h2 class="d-card-title">➕ Nouvelle tâche</h2>
        </div>
        <form method="post" action="" style="padding:0 1.25rem 1.25rem;">
          <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
          <input type="hidden" name="action" value="create">

          <div class="d-field">
            <label class="d-label">Titre <span style="color:#ef4444;">*</span></label>
            <input type="text" name="title" class="d-input" placeholder='Ex: Mettre du gasoil ce soir' required maxlength="255">
          </div>

          <div class="d-field">
            <label class="d-label">Technicien</label>
            <select name="technician_id" class="d-select">
              <option value="">— Général (tous) —</option>
              <?php foreach ($techs as $t): ?>
              <option value="<?= (int)$t['id'] ?>"><?= e($t['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem;">
            <div class="d-field">
              <label class="d-label">Date</label>
              <input type="date" name="due_date" class="d-input">
            </div>
            <div class="d-field">
              <label class="d-label">Heure</label>
              <input type="time" name="due_time" class="d-input">
            </div>
          </div>

          <div class="d-field">
            <label class="d-label">Description</label>
            <textarea name="description" class="d-input" rows="3" placeholder="Détails optionnels..." style="resize:vertical;"></textarea>
          </div>

          <div class="d-field" style="margin-bottom:1rem;">
            <label style="display:flex;align-items:center;gap:.6rem;cursor:pointer;font-size:.85rem;font-weight:600;color:var(--d-txt1);">
              <input type="checkbox" name="urgent" value="1" style="accent-color:#ef4444;width:16px;height:16px;">
              <span style="color:#ef4444;">🚨 Tâche URGENTE</span>
            </label>
          </div>

          <button type="submit" class="d-btn d-btn--primary" style="width:100%;justify-content:center;">
            ➕ Créer la tâche
          </button>
        </form>
      </div>
    </div>

  </div>
</div>

<?php require_once __DIR__.'/partials/footer.php'; ?>
