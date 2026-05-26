<?php
$adminSection = 'quotes';
require __DIR__ . '/partials/header.php';

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { flash('error','Identifiant invalide.'); redirect_to('admin/quotes.php'); }

$q = db_fetch('SELECT * FROM quotes WHERE id = ?', [$id]);
if (!$q) { flash('error','Demande introuvable.'); redirect_to('admin/quotes.php'); }

/* ── POST : enregistrement ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = trim((string)($_POST['action'] ?? 'save'));

    if ($action === 'delete') {
        db_execute('DELETE FROM quotes WHERE id = ?', [$id]);
        flash('success', 'Dossier #'.$id.' supprimé définitivement.');
        redirect_to('admin/quotes.php');
    }
    if ($action === 'archive') {
        db_execute('UPDATE quotes SET archived = 1 WHERE id = ?', [$id]);
        flash('success', 'Dossier archivé.');
        redirect_to('admin/quotes.php?filter=archivés');
    }
    if ($action === 'unarchive') {
        db_execute('UPDATE quotes SET archived = 0 WHERE id = ?', [$id]);
        flash('success', 'Dossier restauré.');
    }

    // Champs editables
    $allowed_status = ['nouveau','contacté','planifié','en cours','terminé','annulé'];
    $status      = trim((string)($_POST['status'] ?? $q['status']));
    if (!in_array($status, $allowed_status, true)) $status = 'nouveau';
    $idate       = trim((string)($_POST['intervention_date'] ?? ''));
    $idate_sql   = ($idate !== '') ? date('Y-m-d H:i:s', strtotime($idate)) : null;
    $technician  = trim((string)($_POST['technician'] ?? ''));
    $duration    = trim((string)($_POST['duration'] ?? ''));
    $amount_ht   = trim((string)($_POST['amount_ht'] ?? ''));
    $amount_val  = ($amount_ht !== '') ? (float)str_replace(',', '.', $amount_ht) : null;
    $solution    = trim((string)($_POST['solution'] ?? ''));
    $materials   = trim((string)($_POST['materials'] ?? ''));
    $notes_admin = trim((string)($_POST['notes_admin'] ?? ''));

    db_execute('UPDATE quotes SET status=?, intervention_date=?, technician=?, duration=?, amount_ht=?, solution=?, materials=?, notes_admin=? WHERE id=?',
        [$status, $idate_sql, $technician ?: null, $duration ?: null, $amount_val, $solution ?: null, $materials ?: null, $notes_admin ?: null, $id]);

    flash('success', 'Fiche #'.$id.' enregistrée.');
    redirect_to('admin/dossier.php?id='.$id);
}

// Reload after potential unarchive
$q = db_fetch('SELECT * FROM quotes WHERE id = ?', [$id]);

$statusMeta = [
    'nouveau'  => ['bg'=>'#e8f4ff','color'=>'#0f6298','label'=>'Nouveau'],
    'contacté' => ['bg'=>'#fff8e0','color'=>'#8a6000','label'=>'Contacté'],
    'planifié' => ['bg'=>'#edf0ff','color'=>'#1a3baa','label'=>'Planifié'],
    'en cours' => ['bg'=>'#fff3e0','color'=>'#b84700','label'=>'En cours'],
    'terminé'  => ['bg'=>'#e6fff2','color'=>'#14653a','label'=>'Terminé ✓'],
    'annulé'   => ['bg'=>'#fff0f0','color'=>'#8c2424','label'=>'Annulé'],
];
$st  = (string)($q['status'] ?? 'nouveau');
$sm  = $statusMeta[$st] ?? $statusMeta['nouveau'];
$isArchived = (bool)($q['archived'] ?? 0);
?>

<div class="admin-page-toolbar">
  <div>
    <div class="admin-breadcrumb"><a href="<?= e(url_for('admin/quotes.php')) ?>" style="color:#2351c5;text-decoration:none;">← Demandes</a></div>
    <h1 class="admin-page-title">Fiche #<?= (int)$q['id'] ?> — <?= e($q['full_name']) ?></h1>
    <p class="admin-page-subtitle">
      Créée le <?= e(date('d/m/Y à H:i', strtotime((string)$q['created_at']))) ?>
      &nbsp;·&nbsp; <span style="display:inline-block;padding:.2rem .75rem;border-radius:8px;background:<?= e($sm['bg']) ?>;color:<?= e($sm['color']) ?>;font-weight:700;font-size:.82rem;"><?= e($sm['label']) ?></span>
      <?php if ($isArchived): ?>&nbsp;·&nbsp;<span style="font-size:.82rem;color:#8a6000;font-weight:700;">🗄️ Archivé</span><?php endif; ?>
    </p>
  </div>
  <div style="display:flex;gap:.75rem;flex-wrap:wrap;align-self:flex-start;">
    <?php if ($isArchived): ?>
    <form method="post">
      <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="action" value="unarchive">
      <button class="admin-btn admin-btn--secondary" type="submit">↩️ Restaurer</button>
    </form>
    <?php else: ?>
    <form method="post">
      <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="action" value="archive">
      <button class="admin-btn admin-btn--secondary" type="submit">🗄️ Archiver</button>
    </form>
    <?php endif; ?>
    <form method="post" onsubmit="return confirm('Supprimer définitivement ce dossier ? Action irréversible.');">
      <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="action" value="delete">
      <button class="admin-btn" type="submit" style="background:#fff0f0;color:#8c2424;border:1.5px solid #efc5c5;">🗑️ Supprimer</button>
    </form>
  </div>
</div>

<form method="post" class="admin-stack">
<input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
<input type="hidden" name="action" value="save">

<!-- ── Section 1 : Coordonnées client ── -->
<section class="admin-panel">
  <div class="admin-panel__head"><h2>👤 Coordonnées du client</h2><p>Informations transmises par le client lors de la demande.</p></div>
  <div class="admin-panel__body">
    <div class="admin-form-grid admin-form-grid--3">
      <div>
        <div style="font-size:.78rem;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:#8a9ab8;margin-bottom:.3rem;">Nom complet</div>
        <div style="font-size:1.05rem;font-weight:700;color:#13254c;"><?= e($q['full_name']) ?></div>
      </div>
      <div>
        <div style="font-size:.78rem;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:#8a9ab8;margin-bottom:.3rem;">Téléphone</div>
        <div><a href="tel:<?= e(preg_replace('/\s+/','',(string)$q['phone'])) ?>" style="font-size:1.1rem;font-weight:700;color:#2351c5;"><?= e($q['phone']) ?></a></div>
      </div>
      <div>
        <div style="font-size:.78rem;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:#8a9ab8;margin-bottom:.3rem;">Email</div>
        <div>
          <?php if (!empty($q['email'])): ?>
            <a href="mailto:<?= e($q['email']) ?>" style="color:#2351c5;"><?= e($q['email']) ?></a>
          <?php else: ?><span style="color:#aaa;">—</span><?php endif; ?>
        </div>
      </div>
    </div>
    <div style="margin-top:.5rem;padding:1rem;background:#f7faff;border-radius:12px;border:1px solid #dde5f3;">
      <div style="font-size:.78rem;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:#8a9ab8;margin-bottom:.5rem;">📍 Adresse d'intervention</div>
      <div style="font-size:1rem;font-weight:600;color:#13254c;line-height:1.7;">
        <?php if (!empty($q['address'])): ?><?= e($q['address']) ?><br><?php endif; ?>
        <?php
          $loc = trim(($q['postal_code'] ?? '').' '.($q['city'] ?? ''));
          if ($loc !== '') echo e($loc);
          else echo '<span style="color:#aaa;">Adresse non renseignée</span>';
        ?>
      </div>
    </div>
  </div>
</section>

<!-- ── Section 2 : Demande initiale ── -->
<section class="admin-panel">
  <div class="admin-panel__head"><h2>📝 Demande initiale</h2><p>Tel que soumis par le client.</p></div>
  <div class="admin-panel__body">
    <div class="admin-form-grid admin-form-grid--3">
      <div>
        <div style="font-size:.78rem;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:#8a9ab8;margin-bottom:.3rem;">Service demandé</div>
        <div style="font-weight:600;"><?= !empty($q['service_type']) ? e($q['service_type']) : '<span style="color:#aaa;">—</span>' ?></div>
      </div>
      <div>
        <div style="font-size:.78rem;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:#8a9ab8;margin-bottom:.3rem;">Niveau d'urgence</div>
        <div><?php $urg=$q['urgency']??'';
          $urgBg  = $urg==='Normale'?'#e8f4ff':($urg==='Urgente'?'#fff3e0':'#fff0f0');
          $urgCol = $urg==='Normale'?'#0f6298':($urg==='Urgente'?'#b84700':'#8c2424');
          echo '<span style="padding:.25rem .75rem;border-radius:8px;background:'.$urgBg.';color:'.$urgCol.';font-weight:700;font-size:.88rem;">'.e($urg).'</span>';
        ?></div>
      </div>
      <div>
        <div style="font-size:.78rem;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:#8a9ab8;margin-bottom:.3rem;">Source</div>
        <div style="color:#445;"><?= !empty($q['source']) ? e($q['source']) : '<span style="color:#aaa;">—</span>' ?></div>
      </div>
    </div>
    <div style="margin-top:.5rem;">
      <div style="font-size:.78rem;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:#8a9ab8;margin-bottom:.5rem;">Message du client</div>
      <div style="background:#f8faff;border-left:4px solid #F07B1D;border-radius:8px;padding:1rem 1.1rem;font-size:.95rem;color:#222;line-height:1.75;white-space:pre-wrap;"><?= e($q['message']) ?></div>
    </div>
  </div>
</section>

<!-- ── Section 3 : Gestion de l'intervention ── -->
<section class="admin-panel">
  <div class="admin-panel__head"><h2>⚙️ Gestion de l'intervention</h2><p>Planification, technicien affecté, montant.</p></div>
  <div class="admin-panel__body">
    <div class="admin-form-grid admin-form-grid--2">
      <label class="admin-field">
        <span>Statut</span>
        <select name="status">
          <?php foreach ($statusMeta as $sv => $svm): ?>
            <option value="<?= e($sv) ?>" <?= $st===$sv?'selected':'' ?>><?= e($svm['label']) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label class="admin-field">
        <span>Date d'intervention prévue / réalisée</span>
        <input type="datetime-local" name="intervention_date" value="<?= !empty($q['intervention_date']) ? e(date('Y-m-d\TH:i', strtotime((string)$q['intervention_date']))) : '' ?>">
      </label>
    </div>
    <div class="admin-form-grid admin-form-grid--3">
      <label class="admin-field">
        <span>Technicien affecté</span>
        <input type="text" name="technician" value="<?= e((string)($q['technician'] ?? '')) ?>" placeholder="Nom du technicien">
      </label>
      <label class="admin-field">
        <span>Durée de l'intervention</span>
        <input type="text" name="duration" value="<?= e((string)($q['duration'] ?? '')) ?>" placeholder="Ex : 2h30">
      </label>
      <label class="admin-field">
        <span>Montant HT (€)</span>
        <input type="number" step="0.01" name="amount_ht" value="<?= !empty($q['amount_ht']) ? e(number_format((float)$q['amount_ht'], 2, '.', '')) : '' ?>" placeholder="Ex : 180.00">
      </label>
    </div>
  </div>
</section>

<!-- ── Section 4 : Rapport technique ── -->
<section class="admin-panel">
  <div class="admin-panel__head"><h2>🔧 Rapport technique</h2><p>Description de la panne et solution appliquée.</p></div>
  <div class="admin-panel__body">
    <label class="admin-field">
      <span>Nature et description de la panne</span>
      <textarea name="solution" placeholder="Décrivez la panne constatée, le diagnostic..." rows="4"><?= e((string)($q['solution'] ?? '')) ?></textarea>
    </label>
    <label class="admin-field">
      <span>Solution appliquée / travaux réalisés</span>
      <textarea name="solution" style="display:none"></textarea>
      <?php /* Utiliser un champ séparé pour solution vs diagnostic */ ?>
    </label>
    <label class="admin-field">
      <span>Matériaux et pièces utilisés</span>
      <textarea name="materials" placeholder="Ex : Disjoncteur 16A ref 4512, câble 2.5mm² (3m)..." rows="3"><?= e((string)($q['materials'] ?? '')) ?></textarea>
    </label>
  </div>
</section>

<!-- ── Section 5 : Notes internes ── -->
<section class="admin-panel">
  <div class="admin-panel__head"><h2>🔒 Notes internes</h2><p>Visible uniquement par l'admin, non envoyé au client.</p></div>
  <div class="admin-panel__body">
    <label class="admin-field">
      <span>Notes privées</span>
      <textarea name="notes_admin" placeholder="Remarques internes, informations confidentielles..." rows="4"><?= e((string)($q['notes_admin'] ?? '')) ?></textarea>
    </label>
  </div>
</section>

<div class="admin-savebar" style="gap:.75rem;">
  <a href="<?= e(url_for('admin/quotes.php')) ?>" class="admin-btn admin-btn--secondary">Annuler</a>
  <button class="admin-btn admin-btn--primary" type="submit">💾 Enregistrer la fiche</button>
</div>
</form>
<?php require __DIR__ . '/partials/footer.php'; ?>
