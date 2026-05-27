<?php
$adminSection = 'quotes';
require __DIR__ . '/partials/header.php';

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { flash('error','Identifiant invalide.'); redirect_to('admin/quotes.php'); }

try { $q = db_fetch('SELECT q.*, t.name AS tech_name FROM quotes q LEFT JOIN technicians t ON t.id = q.technician_id WHERE q.id = ?', [$id]); }
catch (Throwable $e) { $q = db_fetch('SELECT * FROM quotes WHERE id = ?', [$id]); }
if (!$q) { flash('error','Demande introuvable.'); redirect_to('admin/quotes.php'); }

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
        redirect_to('admin/dossier.php?id='.$id);
    }

    // Sauvegarde
    $allowed_status = ['nouveau','contacté','planifié','en cours','terminé','annulé'];
    $status     = trim((string)($_POST['status'] ?? $q['status']));
    if (!in_array($status, $allowed_status, true)) $status = 'nouveau';

    $idate     = trim((string)($_POST['intervention_date'] ?? ''));
    $idate_sql = ($idate !== '') ? date('Y-m-d H:i:s', strtotime($idate)) : null;

    $tech_id   = (int)($_POST['technician_id'] ?? 0);
    $tech_id   = $tech_id > 0 ? $tech_id : null;

    $duration   = trim((string)($_POST['duration']   ?? ''));
    $amount_ht  = trim((string)($_POST['amount_ht']  ?? ''));
    $amount_val = ($amount_ht !== '') ? (float)str_replace(',', '.', $amount_ht) : null;
    $solution   = trim((string)($_POST['solution']   ?? ''));
    $materials  = trim((string)($_POST['materials']  ?? ''));
    $notes_admin= trim((string)($_POST['notes_admin']?? ''));

    $prev_tech_id  = (int)($q['technician_id'] ?? 0) ?: null;
    $prev_status   = (string)($q['status'] ?? '');

    db_execute('UPDATE quotes SET status=?,intervention_date=?,technician_id=?,duration=?,amount_ht=?,solution=?,materials=?,notes_admin=? WHERE id=?',
        [$status, $idate_sql, $tech_id, $duration ?: null, $amount_val, $solution ?: null, $materials ?: null, $notes_admin ?: null, $id]);

    // ── SMS technicien assigné ──
    if (setting_bool('sms_notify_tech') && $tech_id && $tech_id !== $prev_tech_id && $idate_sql) {
        $t = get_tech_by_id($tech_id);
        if ($t && !empty($t['phone'])) {
            $addr = trim(($q['address'] ?? '').', '.($q['postal_code'] ?? '').' '.($q['city'] ?? ''));
            $msg  = company_name()." - Intervention #$id planifiée le ".date('d/m à H:i', strtotime($idate_sql))."\nClient: ".mb_substr($q['full_name'],0,20)."\nAdresse: ".mb_substr($addr,0,60)."\nTél: ".$q['phone'];
            send_sms_ovh($t['phone'], $msg);
        }
    }

    // ── SMS client si planifié ──
    if (setting_bool('sms_notify_client') && $status === 'planifié' && $prev_status !== 'planifié' && !empty($q['phone']) && $idate_sql) {
        $techName = '';
        if ($tech_id) { $tt = get_tech_by_id($tech_id); $techName = $tt ? $tt['name'] : ''; }
        $msg = company_name()." - Votre intervention est planifiée le ".date('d/m à H:i', strtotime($idate_sql)).".".($techName !== '' ? " Technicien: $techName." : '')." Annulation: ".company_phone()." (2h min à l'avance)";
        send_sms_ovh($q['phone'], $msg);
    }

    flash('success', 'Fiche #'.$id.' enregistrée.');
    redirect_to('admin/dossier.php?id='.$id);
}

try { $q = db_fetch('SELECT q.*, t.name AS tech_name FROM quotes q LEFT JOIN technicians t ON t.id = q.technician_id WHERE q.id = ?', [$id]); }
catch (Throwable $e) { $q = db_fetch('SELECT * FROM quotes WHERE id = ?', [$id]); }

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
$techs = all_technicians();
$techPhotos = quote_tech_photos($q);
?>

<div class="admin-page-toolbar">
  <div>
    <div class="admin-breadcrumb"><a href="<?= e(url_for('admin/quotes.php')) ?>" style="color:#2351c5;text-decoration:none;">← Demandes</a></div>
    <h1 class="admin-page-title">Fiche #<?= (int)$q['id'] ?> — <?= e($q['full_name']) ?></h1>
    <p class="admin-page-subtitle">
      <?= e(date('d/m/Y à H:i', strtotime((string)$q['created_at']))) ?>
      &nbsp;·&nbsp;
      <span style="display:inline-block;padding:.2rem .75rem;border-radius:8px;background:<?= e($sm['bg']) ?>;color:<?= e($sm['color']) ?>;font-weight:700;font-size:.82rem;"><?= e($sm['label']) ?></span>
      <?php if ($isArchived): ?>&nbsp;·&nbsp;<span style="font-size:.82rem;color:#8a6000;font-weight:700;">🗄️ Archivé</span><?php endif; ?>
    </p>
  </div>
  <div style="display:flex;gap:.6rem;flex-wrap:wrap;align-self:flex-start;">
    <?php if ($isArchived): ?>
    <form method="post"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="unarchive"><button class="admin-btn admin-btn--secondary" type="submit">↩️ Restaurer</button></form>
    <?php else: ?>
    <form method="post"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="archive"><button class="admin-btn admin-btn--secondary" type="submit">🗄️ Archiver</button></form>
    <?php endif; ?>
    <form method="post" onsubmit="return confirm('Supprimer définitivement ?');"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="delete"><button class="admin-btn" type="submit" style="background:#fff0f0;color:#8c2424;border:1.5px solid #efc5c5;">🗑️ Supprimer</button></form>
  </div>
</div>

<form method="post" class="admin-stack">
<input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
<input type="hidden" name="action" value="save">

<!-- ── Coordonnées client ── -->
<section class="admin-panel">
  <div class="admin-panel__head"><h2>👤 Coordonnées du client</h2></div>
  <div class="admin-panel__body">
    <div class="admin-form-grid admin-form-grid--3">
      <div>
        <div style="font-size:.75rem;font-weight:700;text-transform:uppercase;color:#8a9ab8;margin-bottom:.3rem;">Nom</div>
        <div style="font-size:1.05rem;font-weight:700;"><?= e($q['full_name']) ?></div>
      </div>
      <div>
        <div style="font-size:.75rem;font-weight:700;text-transform:uppercase;color:#8a9ab8;margin-bottom:.3rem;">Téléphone</div>
        <a href="tel:<?= e(preg_replace('/\s+/','',(string)$q['phone'])) ?>" style="font-size:1.1rem;font-weight:700;color:#2351c5;"><?= e($q['phone']) ?></a>
      </div>
      <div>
        <div style="font-size:.75rem;font-weight:700;text-transform:uppercase;color:#8a9ab8;margin-bottom:.3rem;">Email</div>
        <?php if (!empty($q['email'])): ?><a href="mailto:<?= e($q['email']) ?>" style="color:#2351c5;"><?= e($q['email']) ?></a><?php else: ?><span style="color:#aaa;">—</span><?php endif; ?>
      </div>
    </div>
    <div style="margin-top:.5rem;padding:1rem;background:#f7faff;border-radius:12px;border:1px solid #dde5f3;">
      <div style="font-size:.75rem;font-weight:700;text-transform:uppercase;color:#8a9ab8;margin-bottom:.4rem;">📍 Adresse d'intervention</div>
      <div style="font-size:1rem;font-weight:600;line-height:1.7;">
        <?php if (!empty($q['address'])): ?><?= e($q['address']) ?><br><?php endif; ?>
        <?php $loc = trim(($q['postal_code']??'').' '.($q['city']??'')); echo $loc !== '' ? e($loc) : '<span style="color:#aaa;">Non renseignée</span>'; ?>
      </div>
    </div>
  </div>
</section>

<!-- ── Demande initiale ── -->
<section class="admin-panel">
  <div class="admin-panel__head"><h2>📝 Demande initiale</h2></div>
  <div class="admin-panel__body">
    <div class="admin-form-grid admin-form-grid--3">
      <div>
        <div style="font-size:.75rem;font-weight:700;text-transform:uppercase;color:#8a9ab8;margin-bottom:.3rem;">Service</div>
        <div style="font-weight:600;"><?= !empty($q['service_type']) ? e($q['service_type']) : '<span style="color:#aaa;">—</span>' ?></div>
      </div>
      <div>
        <div style="font-size:.75rem;font-weight:700;text-transform:uppercase;color:#8a9ab8;margin-bottom:.3rem;">Urgence</div>
        <?php $urg=$q['urgency']??''; $urgBg=$urg==='Normale'?'#e8f4ff':($urg==='Urgente'?'#fff3e0':'#fff0f0'); $urgCol=$urg==='Normale'?'#0f6298':($urg==='Urgente'?'#b84700':'#8c2424');
        echo '<span style="padding:.25rem .75rem;border-radius:8px;background:'.$urgBg.';color:'.$urgCol.';font-weight:700;font-size:.88rem;">'.e($urg).'</span>'; ?>
      </div>
      <div>
        <div style="font-size:.75rem;font-weight:700;text-transform:uppercase;color:#8a9ab8;margin-bottom:.3rem;">Source</div>
        <div style="color:#445;"><?= !empty($q['source']) ? e($q['source']) : '<span style="color:#aaa;">—</span>' ?></div>
      </div>
    </div>
    <div style="margin-top:.5rem;">
      <div style="font-size:.75rem;font-weight:700;text-transform:uppercase;color:#8a9ab8;margin-bottom:.4rem;">Message</div>
      <div style="background:#f8faff;border-left:4px solid #F07B1D;border-radius:8px;padding:1rem;font-size:.92rem;color:#222;line-height:1.75;white-space:pre-wrap;"><?= e($q['message']) ?></div>
    </div>
  </div>
</section>

<!-- ── Planification ── -->
<section class="admin-panel">
  <div class="admin-panel__head"><h2>⚙️ Planification &amp; suivi</h2><p>Un SMS est envoyé au technicien et au client à l'enregistrement si activé dans les paramètres SMS.</p></div>
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
        <span>Date &amp; heure d'intervention</span>
        <input type="datetime-local" name="intervention_date" value="<?= !empty($q['intervention_date']) ? e(date('Y-m-d\TH:i', strtotime((string)$q['intervention_date']))) : '' ?>">
      </label>
    </div>
    <div class="admin-form-grid admin-form-grid--3">
      <label class="admin-field">
        <span>Technicien assigné</span>
        <select name="technician_id">
          <option value="0">— Aucun technicien —</option>
          <?php foreach ($techs as $t): ?>
            <option value="<?= (int)$t['id'] ?>" <?= (int)($q['technician_id']??0)===(int)$t['id']?'selected':'' ?>>
              <?= e($t['name']) ?><?= $t['status']==='inactif' ? ' (inactif)' : '' ?>
            </option>
          <?php endforeach; ?>
          <?php if (empty($techs)): ?><option disabled>Aucun compte technicien créé</option><?php endif; ?>
        </select>
      </label>
      <label class="admin-field">
        <span>Durée prévue</span>
        <input type="text" name="duration" value="<?= e((string)($q['duration'] ?? '')) ?>" placeholder="Ex : 2h30">
      </label>
      <label class="admin-field">
        <span>Montant HT (€)</span>
        <input type="number" step="0.01" name="amount_ht" value="<?= !empty($q['amount_ht']) ? e(number_format((float)$q['amount_ht'],2,'.','')):'' ?>" placeholder="180.00">
      </label>
    </div>
  </div>
</section>

<!-- ── Rapport technique admin ── -->
<section class="admin-panel">
  <div class="admin-panel__head"><h2>🔧 Rapport technique</h2></div>
  <div class="admin-panel__body">
    <label class="admin-field">
      <span>Diagnostic &amp; solution appliquée</span>
      <textarea name="solution" rows="4" placeholder="Panne constatée, solution, observations..."><?= e((string)($q['solution'] ?? '')) ?></textarea>
    </label>
    <label class="admin-field">
      <span>Matériaux &amp; pièces utilisées</span>
      <textarea name="materials" rows="3" placeholder="Ex : Disjoncteur 16A, câble 2.5mm² (3m)..."><?= e((string)($q['materials'] ?? '')) ?></textarea>
    </label>
  </div>
</section>

<?php if (!empty($techPhotos) || !empty($q['tech_report'])): ?>
<!-- ── Rapport technicien (portail) ── -->
<section class="admin-panel">
  <div class="admin-panel__head">
    <h2>📱 Rapport du technicien <?= !empty($q['tech_name']) ? '— '.e($q['tech_name']) : '' ?></h2>
    <?php if (!empty($q['tech_completed_at'])): ?><p>✅ Terminé le <?= e(date('d/m/Y à H:i', strtotime((string)$q['tech_completed_at']))) ?></p><?php endif; ?>
  </div>
  <div class="admin-panel__body">
    <?php if (!empty($q['tech_report'])): ?>
      <div style="background:#f8faff;border-left:4px solid #14653a;border-radius:8px;padding:1rem;font-size:.92rem;color:#222;line-height:1.75;white-space:pre-wrap;margin-bottom:1rem;"><?= e($q['tech_report']) ?></div>
    <?php endif; ?>
    <?php if (!empty($techPhotos)): ?>
      <div style="font-size:.8rem;font-weight:700;text-transform:uppercase;color:#8a9ab8;margin-bottom:.5rem;">📸 Photos (<?= count($techPhotos) ?>)</div>
      <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:.5rem;">
        <?php foreach ($techPhotos as $ph): ?>
          <a href="<?= e(asset_url($ph)) ?>" target="_blank" style="aspect-ratio:1;border-radius:8px;overflow:hidden;display:block;background:#f0f4ff;">
            <img src="<?= e(asset_url($ph)) ?>" alt="photo" style="width:100%;height:100%;object-fit:cover;">
          </a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</section>
<?php endif; ?>

<!-- ── Notes internes ── -->
<section class="admin-panel">
  <div class="admin-panel__head"><h2>🔒 Notes internes</h2><p>Visibles uniquement par l'admin.</p></div>
  <div class="admin-panel__body">
    <label class="admin-field">
      <textarea name="notes_admin" rows="3" placeholder="Notes privées..."><?= e((string)($q['notes_admin'] ?? '')) ?></textarea>
    </label>
  </div>
</section>

<div class="admin-savebar" style="gap:.75rem;">
  <a href="<?= e(url_for('admin/quotes.php')) ?>" class="admin-btn admin-btn--secondary">Annuler</a>
  <button class="admin-btn admin-btn--primary" type="submit">💾 Enregistrer la fiche</button>
</div>
</form>
<?php require __DIR__ . '/partials/footer.php'; ?>
