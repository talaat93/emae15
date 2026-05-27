<?php
declare(strict_types=1);
$pageTitle   = 'Interventions';
$dispSection = 'interventions';
require __DIR__.'/partials/header.php';

/* ─────────────────────────────────────────────────────
   POST — actions rapides
───────────────────────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = trim((string)($_POST['action'] ?? ''));
    $pid    = (int)($_POST['id'] ?? 0);

    if ($action === 'update_status' && $pid > 0) {
        $validStatuses = ['nouveau','confirmé','assigné','en_route','sur_place','terminé','devis_envoyé','facturé','payé','annulé'];
        $newStatus = trim((string)($_POST['new_status'] ?? ''));
        if (in_array($newStatus, $validStatuses, true)) {
            $current = get_intervention_by_id($pid);
            if ($current) {
                update_intervention($pid, ['status' => $newStatus]);
                log_intervention_history($pid, $current['status'], $newStatus, 'dispatcher', (int)$disp['id'], (string)$disp['name'], 'Changement de statut rapide');
                flash('success', 'Statut mis à jour.');
            }
        }
    } elseif ($action === 'assign_tech' && $pid > 0) {
        $techId = (int)($_POST['technician_id'] ?? 0);
        $current = get_intervention_by_id($pid);
        if ($current) {
            $data = ['technician_id' => $techId ?: null];
            if ($techId > 0 && in_array($current['status'], ['nouveau','confirmé'], true)) {
                $data['status'] = 'assigné';
                log_intervention_history($pid, $current['status'], 'assigné', 'dispatcher', (int)$disp['id'], (string)$disp['name'], 'Technicien assigné');
            }
            update_intervention($pid, $data);
            flash('success', 'Technicien assigné.');
        }
    }

    // Redirect back preserving GET filters
    $qs = http_build_query(array_filter([
        'status'        => $_GET['status']       ?? '',
        'category'      => $_GET['category']     ?? '',
        'technician_id' => $_GET['technician_id']?? '',
        'urgency'       => $_GET['urgency']      ?? '',
        'search'        => $_GET['search']       ?? '',
        'date_from'     => $_GET['date_from']    ?? '',
        'date_to'       => $_GET['date_to']      ?? '',
    ]));
    redirect_to('dispatcher/interventions.php' . ($qs ? '?'.$qs : ''));
}

/* ─────────────────────────────────────────────────────
   GET — filtres
───────────────────────────────────────────────────── */
$fStatus   = trim((string)($_GET['status']        ?? ''));
$fCat      = trim((string)($_GET['category']      ?? ''));
$fTechId   = (int)($_GET['technician_id']         ?? 0);
$fUrgency  = !empty($_GET['urgency']);
$fSearch   = trim((string)($_GET['search']        ?? ''));
$fDateFrom = trim((string)($_GET['date_from']     ?? ''));
$fDateTo   = trim((string)($_GET['date_to']       ?? ''));

$filters = [];
if ($fStatus)   $filters['status']        = $fStatus;
if ($fCat)      $filters['category']      = $fCat;
if ($fTechId)   $filters['technician_id'] = $fTechId;
if ($fUrgency)  $filters['urgency']       = 1;
if ($fSearch)   $filters['search']        = $fSearch;

/* date_from / date_to need raw SQL; we'll post-filter in PHP for simplicity */
$interventions = all_interventions($filters);

/* Apply date range filter in PHP */
if ($fDateFrom !== '') {
    $interventions = array_filter($interventions, fn($iv) =>
        ($iv['scheduled_date'] ?? '') !== '' && $iv['scheduled_date'] >= $fDateFrom);
}
if ($fDateTo !== '') {
    $interventions = array_filter($interventions, fn($iv) =>
        ($iv['scheduled_date'] ?? '') !== '' && $iv['scheduled_date'] <= $fDateTo);
}
$interventions = array_values($interventions);

$techs      = all_technicians();
$statusCfg  = intervention_status_config();
$catCfg     = intervention_category_config();
$validStatuses = array_keys($statusCfg);
?>

<!-- TOPBAR -->
<div class="d-topbar">
  <div style="display:flex;align-items:center;gap:.75rem;">
    <button class="d-menu-toggle" id="d-menu-toggle" aria-label="Menu">☰</button>
    <div>
      <div class="d-topbar-title">📋 Interventions</div>
    </div>
  </div>
  <div class="d-topbar-actions">
    <a href="<?= e(url_for('dispatcher/intervention_new.php')) ?>" class="d-btn d-btn--primary">➕ Nouvelle</a>
  </div>
</div>

<div class="d-content">

  <!-- PAGE HEADER -->
  <div class="d-page-header">
    <div>
      <div class="d-page-title">Interventions (<?= count($interventions) ?>)</div>
      <div class="d-page-sub">Gestion et suivi de toutes les interventions</div>
    </div>
    <a href="<?= e(url_for('dispatcher/intervention_new.php')) ?>" class="d-btn d-btn--primary">➕ Nouvelle intervention</a>
  </div>

  <!-- FILTRES -->
  <form method="get" action="" id="form-filters">
    <div class="d-filters">
      <input type="search" name="search" placeholder="🔍 Rechercher réf, client, ville…"
             value="<?= e($fSearch) ?>" style="min-width:220px;">
      <select name="status">
        <option value="">Tous les statuts</option>
        <?php foreach ($statusCfg as $sk => $sv): ?>
          <option value="<?= e($sk) ?>" <?= $fStatus === $sk ? 'selected' : '' ?>><?= e($sv['label']) ?></option>
        <?php endforeach; ?>
      </select>
      <select name="category">
        <option value="">Toutes catégories</option>
        <?php foreach ($catCfg as $ck => $cv): ?>
          <option value="<?= e($ck) ?>" <?= $fCat === $ck ? 'selected' : '' ?>><?= e($cv['icon'].' '.$cv['label']) ?></option>
        <?php endforeach; ?>
      </select>
      <select name="technician_id">
        <option value="">Tous les techs</option>
        <?php foreach ($techs as $t): ?>
          <option value="<?= (int)$t['id'] ?>" <?= $fTechId === (int)$t['id'] ? 'selected' : '' ?>><?= e($t['name']) ?></option>
        <?php endforeach; ?>
      </select>
      <label style="display:flex;align-items:center;gap:.4rem;color:#8fa0c4;font-size:.84rem;cursor:pointer;white-space:nowrap;">
        <input type="checkbox" name="urgency" value="1" <?= $fUrgency ? 'checked' : '' ?> style="width:auto;accent-color:#ef4444;">
        Urgence uniquement
      </label>
      <input type="date" name="date_from" value="<?= e($fDateFrom) ?>" title="Date début" style="width:auto;">
      <input type="date" name="date_to"   value="<?= e($fDateTo) ?>"   title="Date fin"   style="width:auto;">
      <button type="submit" class="d-btn d-btn--secondary d-btn--sm">Filtrer</button>
      <?php if ($fStatus || $fCat || $fTechId || $fUrgency || $fSearch || $fDateFrom || $fDateTo): ?>
        <a href="<?= e(url_for('dispatcher/interventions.php')) ?>" class="d-btn d-btn--ghost d-btn--sm">✕ Réinitialiser</a>
      <?php endif; ?>
    </div>
  </form>

  <!-- TABLE -->
  <div class="d-card">
    <?php if (empty($interventions)): ?>
      <div class="d-empty">
        <div class="d-empty-icon">📭</div>
        <div style="font-size:1rem;font-weight:700;color:#8fa0c4;margin-bottom:.5rem;">Aucune intervention trouvée</div>
        <div style="font-size:.84rem;color:#4a5f8a;">Modifiez vos filtres ou créez une nouvelle intervention.</div>
        <a href="<?= e(url_for('dispatcher/intervention_new.php')) ?>" class="d-btn d-btn--primary" style="margin-top:1.25rem;">➕ Nouvelle intervention</a>
      </div>
    <?php else: ?>
    <div style="overflow-x:auto;">
      <table class="d-table">
        <thead>
          <tr>
            <th>Réf</th>
            <th>Client</th>
            <th>Ville</th>
            <th>Catégorie</th>
            <th>Type</th>
            <th>Urg.</th>
            <th>Date / Heure</th>
            <th>Technicien</th>
            <th>Statut</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($interventions as $iv): ?>
          <tr>
            <!-- Réf -->
            <td>
              <a href="<?= e(url_for('dispatcher/intervention_view.php').'?id='.(int)$iv['id']) ?>"
                 style="font-weight:700;color:#F07B1D;text-decoration:none;font-size:.82rem;font-family:'Syne',sans-serif;">
                <?= e($iv['ref'] ?? 'INT #'.(int)$iv['id']) ?>
              </a>
            </td>

            <!-- Client -->
            <td>
              <div style="font-weight:600;color:#e8ecf5;">
                <?= e(trim(($iv['lastname']??'').' '.($iv['firstname']??''))) ?>
              </div>
              <?php if (!empty($iv['client_phone'])): ?>
                <div style="font-size:.75rem;color:#8fa0c4;">
                  <a href="tel:<?= e(preg_replace('/\s+/','',$iv['client_phone'])) ?>" style="color:#8fa0c4;text-decoration:none;"><?= e($iv['client_phone']) ?></a>
                </div>
              <?php endif; ?>
            </td>

            <!-- Ville -->
            <td style="color:#8fa0c4;font-size:.84rem;"><?= e($iv['client_city'] ?? '—') ?></td>

            <!-- Catégorie -->
            <td><?= intervention_category_badge((string)($iv['category'] ?? '')) ?></td>

            <!-- Type -->
            <td style="color:#8fa0c4;font-size:.82rem;max-width:130px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
              <?= e($iv['type_label'] ?? '—') ?>
            </td>

            <!-- Urgence -->
            <td>
              <?php if (!empty($iv['urgency'])): ?>
                <span class="urgency-dot" title="Urgence"></span>
              <?php else: ?>
                <span style="color:#4a5f8a;font-size:.8rem;">—</span>
              <?php endif; ?>
            </td>

            <!-- Date / Heure -->
            <td style="font-size:.83rem;white-space:nowrap;">
              <?php if (!empty($iv['scheduled_date'])): ?>
                <div style="color:#e8ecf5;"><?= e(date('d/m/Y', strtotime($iv['scheduled_date']))) ?></div>
                <?php if (!empty($iv['scheduled_time'])): ?>
                  <div style="color:#8fa0c4;"><?= e(substr($iv['scheduled_time'],0,5)) ?></div>
                <?php endif; ?>
              <?php else: ?>
                <span style="color:#4a5f8a;">Non planifiée</span>
              <?php endif; ?>
            </td>

            <!-- Technicien -->
            <td style="font-size:.84rem;color:<?= !empty($iv['tech_name']) ? '#e8ecf5' : '#4a5f8a' ?>;">
              <?= e($iv['tech_name'] ?? 'Non assigné') ?>
            </td>

            <!-- Statut -->
            <td><?= intervention_status_badge((string)($iv['status'] ?? 'nouveau')) ?></td>

            <!-- Actions -->
            <td>
              <div style="display:flex;align-items:center;gap:.4rem;flex-wrap:nowrap;">
                <a href="<?= e(url_for('dispatcher/intervention_view.php').'?id='.(int)$iv['id']) ?>"
                   class="d-btn d-btn--secondary d-btn--sm">Voir</a>

                <!-- Quick status update -->
                <form method="post" action="" style="display:inline;">
                  <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                  <input type="hidden" name="action" value="update_status">
                  <input type="hidden" name="id" value="<?= (int)$iv['id'] ?>">
                  <select name="new_status"
                          onchange="this.form.submit()"
                          style="background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.1);border-radius:6px;color:#e8ecf5;padding:.3rem .5rem;font-size:.75rem;font-family:inherit;cursor:pointer;outline:none;">
                    <option value="">— Statut</option>
                    <?php foreach ($statusCfg as $sk => $sv): ?>
                      <option value="<?= e($sk) ?>" <?= ($iv['status'] ?? '') === $sk ? 'selected' : '' ?>>
                        <?= e($sv['label']) ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </form>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>

  <!-- PAGINATION / COUNT -->
  <div style="margin-top:.75rem;font-size:.8rem;color:#4a5f8a;text-align:right;">
    <?= count($interventions) ?> résultat<?= count($interventions) > 1 ? 's' : '' ?>
  </div>

</div><!-- /.d-content -->

<?php require __DIR__.'/partials/footer.php'; ?>
