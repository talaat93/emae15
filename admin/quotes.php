<?php
$adminSection = 'quotes';
require __DIR__ . '/partials/header.php';

/* ── Actions POST ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $id     = (int)($_POST['id'] ?? 0);
    $action = trim((string)($_POST['action'] ?? ''));
    if ($id > 0) {
        if ($action === 'archive') {
            db_execute('UPDATE quotes SET archived = 1 WHERE id = ?', [$id]);
            flash('success', 'Demande archivée.');
        } elseif ($action === 'unarchive') {
            db_execute('UPDATE quotes SET archived = 0 WHERE id = ?', [$id]);
            flash('success', 'Demande restaurée.');
        } elseif ($action === 'delete') {
            db_execute('DELETE FROM quotes WHERE id = ?', [$id]);
            flash('success', 'Demande supprimée définitivement.');
        } elseif ($action === 'status') {
            $allowed = ['nouveau','contacté','planifié','en cours','terminé','annulé'];
            $st = trim((string)($_POST['status'] ?? ''));
            if (in_array($st, $allowed, true)) db_execute('UPDATE quotes SET status = ? WHERE id = ?', [$st, $id]);
        }
    }
    $qs = isset($_GET['filter']) ? '?filter='.urlencode($_GET['filter']) : '';
    redirect_to('admin/quotes.php'.$qs);
}

$filter = trim((string)($_GET['filter'] ?? 'actifs'));
$counts = count_quotes_by_status();
$quotes = all_quotes($filter === 'archivés');

$statusMeta = [
    'nouveau'  => ['bg'=>'#e8f4ff','color'=>'#0f6298','border'=>'#bcd8ee','label'=>'Nouveau'],
    'contacté' => ['bg'=>'#fff8e0','color'=>'#8a6000','border'=>'#e8c84a','label'=>'Contacté'],
    'planifié' => ['bg'=>'#edf0ff','color'=>'#1a3baa','border'=>'#9bb2f5','label'=>'Planifié'],
    'en cours' => ['bg'=>'#fff3e0','color'=>'#b84700','border'=>'#f0a060','label'=>'En cours'],
    'terminé'  => ['bg'=>'#e6fff2','color'=>'#14653a','border'=>'#70d49a','label'=>'Terminé ✓'],
    'annulé'   => ['bg'=>'#fff0f0','color'=>'#8c2424','border'=>'#efc5c5','label'=>'Annulé'],
];
?>
<div class="admin-page-toolbar">
  <div>
    <div class="admin-breadcrumb">Leads</div>
    <h1 class="admin-page-title">Demandes & Interventions</h1>
    <p class="admin-page-subtitle">Gestion complète des demandes de devis et interventions.</p>
  </div>
</div>

<!-- Tabs filtres -->
<div style="display:flex;gap:.5rem;margin-bottom:1.25rem;flex-wrap:wrap;">
  <?php foreach ([
    'actifs'   => '📋 Actifs ('.$counts['actifs'].')',
    'archivés' => '🗄️ Archivés ('.$counts['archivés'].')',
  ] as $k => $label): ?>
  <a href="?filter=<?= e($k) ?>" style="display:inline-flex;align-items:center;padding:.55rem 1.1rem;border-radius:12px;font-weight:700;font-size:.9rem;text-decoration:none;border:2px solid <?= $filter===$k ? '#2351c5' : '#dde5f3' ?>;background:<?= $filter===$k ? '#2351c5' : '#fff' ?>;color:<?= $filter===$k ? '#fff' : '#445' ?>;">
    <?= e($label) ?>
  </a>
  <?php endforeach; ?>
  <?php if ($counts['nouveaux'] > 0): ?>
  <span style="display:inline-flex;align-items:center;padding:.55rem 1rem;border-radius:12px;background:#fef3c7;border:2px solid #f0b429;color:#7a5800;font-weight:700;font-size:.85rem;">
    ⚡ <?= $counts['nouveaux'] ?> non traité<?= $counts['nouveaux']>1?'s':'' ?>
  </span>
  <?php endif; ?>
</div>

<section class="admin-panel">
  <div class="admin-panel__head">
    <h2><?= $filter === 'archivés' ? 'Demandes archivées' : 'Demandes actives' ?></h2>
  </div>
  <div class="admin-panel__body" style="padding:0;">
    <?php if (empty($quotes)): ?>
      <p style="padding:2rem;text-align:center;color:#8a9ab8;">Aucune demande dans cette catégorie.</p>
    <?php else: ?>
    <div class="admin-table-wrap">
    <table class="admin-table" style="font-size:.88rem;">
      <thead>
        <tr>
          <th>#</th>
          <th>Date</th>
          <th>Client</th>
          <th>Contact</th>
          <th>Adresse</th>
          <th>Service / Urgence</th>
          <th>Statut</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($quotes as $q):
          $st = $q['status'] ?? 'nouveau';
          $sm = $statusMeta[$st] ?? $statusMeta['nouveau'];
          $isArchived = (bool)($q['archived'] ?? 0);
          $location = trim(($q['postal_code'] ?? '').' '.($q['city'] ?? ''));
        ?>
        <tr>
          <td style="color:#8a9ab8;font-weight:700;">#<?= (int)$q['id'] ?></td>
          <td style="white-space:nowrap;color:#445;">
            <?= e(date('d/m/Y', strtotime((string)$q['created_at']))) ?><br>
            <small style="color:#8a9ab8;"><?= e(date('H:i', strtotime((string)$q['created_at']))) ?></small>
          </td>
          <td>
            <strong><?= e($q['full_name']) ?></strong>
            <?php if (!empty($q['source'])): ?><br><small style="color:#8a9ab8;">via <?= e($q['source']) ?></small><?php endif; ?>
          </td>
          <td>
            <a href="tel:<?= e(preg_replace('/\s+/','',(string)$q['phone'])) ?>" style="color:#2351c5;font-weight:600;"><?= e($q['phone']) ?></a>
            <?php if (!empty($q['email'])): ?><br><a href="mailto:<?= e($q['email']) ?>" style="color:#8a9ab8;font-size:.82rem;"><?= e($q['email']) ?></a><?php endif; ?>
          </td>
          <td>
            <?php if (!empty($q['address'])): ?><span style="font-size:.82rem;"><?= e($q['address']) ?></span><br><?php endif; ?>
            <?php if ($location !== ''): ?><small style="color:#445;"><?= e($location) ?></small><?php endif; ?>
          </td>
          <td>
            <?php if (!empty($q['service_type'])): ?><span style="font-weight:600;"><?= e($q['service_type']) ?></span><br><?php endif; ?>
            <?php $urg = $q['urgency'] ?? ''; if ($urg !== ''): ?>
              <span style="font-size:.78rem;padding:.2rem .55rem;border-radius:6px;background:<?= $urg==='Normale'?'#f0f4ff':'#fff0e6' ?>;color:<?= $urg==='Normale'?'#2351c5':'#b84700' ?>;font-weight:700;"><?= e($urg) ?></span>
            <?php endif; ?>
          </td>
          <td>
            <!-- Statut badge + changement rapide -->
            <form method="post" style="display:flex;gap:.35rem;align-items:center;">
              <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
              <input type="hidden" name="id" value="<?= (int)$q['id'] ?>">
              <input type="hidden" name="action" value="status">
              <select name="status" onchange="this.form.submit()" style="border:1.5px solid <?= e($sm['border']) ?>;background:<?= e($sm['bg']) ?>;color:<?= e($sm['color']) ?>;border-radius:8px;padding:.3rem .55rem;font-size:.82rem;font-weight:700;cursor:pointer;">
                <?php foreach ($statusMeta as $sv => $svm): ?>
                  <option value="<?= e($sv) ?>" <?= $st===$sv?'selected':'' ?>><?= e($svm['label']) ?></option>
                <?php endforeach; ?>
              </select>
            </form>
          </td>
          <td style="white-space:nowrap;">
            <a href="dossier.php?id=<?= (int)$q['id'] ?>" style="display:inline-flex;align-items:center;padding:.38rem .75rem;border-radius:8px;background:#edf0ff;color:#1a3baa;font-weight:700;font-size:.82rem;text-decoration:none;margin-right:.3rem;">🗂️ Fiche</a>
            <?php if ($isArchived): ?>
              <form method="post" style="display:inline;">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="id" value="<?= (int)$q['id'] ?>">
                <input type="hidden" name="action" value="unarchive">
                <button type="submit" style="padding:.38rem .75rem;border-radius:8px;background:#e6fff2;color:#14653a;border:none;font-weight:700;font-size:.82rem;cursor:pointer;">↩️ Restaurer</button>
              </form>
            <?php else: ?>
              <form method="post" style="display:inline;">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="id" value="<?= (int)$q['id'] ?>">
                <input type="hidden" name="action" value="archive">
                <button type="submit" style="padding:.38rem .75rem;border-radius:8px;background:#fff8e0;color:#8a6000;border:none;font-weight:700;font-size:.82rem;cursor:pointer;">🗄️ Archiver</button>
              </form>
            <?php endif; ?>
            <form method="post" style="display:inline;" onsubmit="return confirm('Supprimer définitivement cette demande ? Action irréversible.');">
              <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
              <input type="hidden" name="id" value="<?= (int)$q['id'] ?>">
              <input type="hidden" name="action" value="delete">
              <button type="submit" style="padding:.38rem .75rem;border-radius:8px;background:#fff0f0;color:#8c2424;border:none;font-weight:700;font-size:.82rem;cursor:pointer;margin-left:.3rem;">🗑️</button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    </div>
    <?php endif; ?>
  </div>
</section>
<?php require __DIR__ . '/partials/footer.php'; ?>
