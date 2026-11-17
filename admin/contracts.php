<?php
/**
 * Contrats d'entretien : clients sous contrat, prochaine échéance, rappels
 * automatiques (includes/contracts.php et includes/automations.php).
 */
$adminSection = 'contracts';
require __DIR__ . '/partials/header.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $act = (string)($_POST['act'] ?? '');
    $id  = (int)($_POST['id'] ?? 0);
    if ($act === 'save') {
        $saved = contract_save($_POST, $id);
        flash($saved ? 'success' : 'error', $saved ? 'Contrat enregistré.' : 'Indiquez au moins le nom du client et l\'appareil.');
        redirect_to('admin/contracts.php'.($saved ? '' : ($id ? '?edit='.$id : '?new=1')));
    }
    if ($act === 'visit' && $id > 0) {
        $ok = contract_visit_done($id, (string)($_POST['visit_date'] ?? date('Y-m-d')));
        flash($ok ? 'success' : 'error', $ok ? 'Visite enregistrée : prochaine échéance recalculée.' : 'Date invalide.');
        redirect_to('admin/contracts.php');
    }
    if ($act === 'delete' && $id > 0) {
        db_execute('DELETE FROM maintenance_contracts WHERE id = ?', [$id]);
        flash('success', 'Contrat supprimé.');
        redirect_to('admin/contracts.php');
    }
    if ($act === 'settings') {
        growth_save('contracts_remind_enabled', !empty($_POST['contracts_remind_enabled']) ? '1' : '0');
        growth_save('contracts_remind_days', (string)max(1, min(120, (int)($_POST['contracts_remind_days'] ?? 30))));
        growth_save('contracts_remind_text', trim((string)($_POST['contracts_remind_text'] ?? '')));
        flash('success', 'Réglages des rappels enregistrés.');
        redirect_to('admin/contracts.php#rappels');
    }
}

$filter = in_array($_GET['f'] ?? 'actif', ['actif', 'arrêté', 'tous'], true) ? (string)($_GET['f'] ?? 'actif') : 'actif';
$edit = isset($_GET['edit']) ? contract_get((int)$_GET['edit']) : null;
$new  = isset($_GET['new']);
// Depuis une demande « Entretien » : formulaire prérempli.
$fromQuote = null;
if (isset($_GET['from_quote'])) {
    try { $fromQuote = db_fetch('SELECT * FROM quotes WHERE id = ?', [(int)$_GET['from_quote']]); } catch (Throwable $e) {}
    if ($fromQuote) $new = true;
}
$c = $edit ?? ($fromQuote ? ['client_name' => $fromQuote['full_name'], 'phone' => $fromQuote['phone'], 'email' => $fromQuote['email'],
        'address' => trim($fromQuote['address'].' '.$fromQuote['postal_code'].' '.$fromQuote['city']), 'equipment' => $fromQuote['service_type'], 'notes' => $fromQuote['message'], 'quote_id' => $fromQuote['id']] : []);
$v = static fn(string $k): string => e((string)($c[$k] ?? ''));
$list = contracts_list($filter);
$soon = date('Y-m-d', time() + contracts_remind_days() * 86400);
try { $requests = db_fetch_all("SELECT id, full_name, phone, service_type, created_at FROM quotes WHERE request_type = 'entretien' AND status = 'nouveau' ORDER BY id DESC LIMIT 20"); } catch (Throwable $e) { $requests = []; }
?>
<div class="admin-page-toolbar">
  <div>
    <div class="admin-breadcrumb">Croissance</div>
    <h1 class="admin-page-title">Contrats d'entretien</h1>
    <p class="admin-page-subtitle">Vos clients sous contrat. Avant chaque échéance, le client reçoit un rappel et vous recevez la liste des entretiens à planifier.</p>
  </div>
  <a class="admin-btn admin-btn--primary" href="<?= e(url_for('admin/contracts.php?new=1')) ?>">➕ Nouveau contrat</a>
</div>

<?php if ($requests): ?>
<section class="admin-panel">
  <div class="admin-panel__head"><h2>🔧 Demandes de contrat reçues par le site</h2></div>
  <div class="admin-panel__body admin-table-wrap"><table class="admin-table"><thead><tr><th>Date</th><th>Client</th><th>Téléphone</th><th>Appareil</th><th></th></tr></thead><tbody>
  <?php foreach ($requests as $r): ?>
    <tr><td><?= e(date('d/m/Y', strtotime((string)$r['created_at']))) ?></td><td><a href="<?= e(url_for('admin/dossier.php?id='.(int)$r['id'])) ?>"><?= e((string)$r['full_name']) ?></a></td><td><?= e((string)$r['phone']) ?></td><td><?= e((string)$r['service_type']) ?></td>
    <td><a class="admin-btn admin-btn--secondary" href="<?= e(url_for('admin/contracts.php?from_quote='.(int)$r['id'])) ?>">Créer le contrat</a></td></tr>
  <?php endforeach; ?>
  </tbody></table></div>
</section>
<?php endif; ?>

<?php if ($edit || $new): ?>
<form method="post" class="admin-panel">
  <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
  <input type="hidden" name="act" value="save">
  <input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>">
  <input type="hidden" name="quote_id" value="<?= $v('quote_id') ?>">
  <div class="admin-panel__head"><h2><?= $edit ? 'Modifier le contrat' : 'Nouveau contrat' ?></h2></div>
  <div class="admin-panel__body">
    <div class="admin-form-grid admin-form-grid--2">
      <label class="admin-field"><span>Client *</span><input type="text" name="client_name" value="<?= $v('client_name') ?>" required></label>
      <label class="admin-field"><span>Appareil *</span><input type="text" name="equipment" value="<?= $v('equipment') ?>" placeholder="Chaudière gaz Saunier Duval" required></label>
      <label class="admin-field"><span>Téléphone (mobile pour le SMS)</span><input type="tel" name="phone" value="<?= $v('phone') ?>"></label>
      <label class="admin-field"><span>E-mail</span><input type="email" name="email" value="<?= $v('email') ?>"></label>
    </div>
    <label class="admin-field"><span>Adresse</span><input type="text" name="address" value="<?= $v('address') ?>"></label>
    <div class="admin-form-grid admin-form-grid--3">
      <label class="admin-field"><span>Périodicité</span><select name="period_months">
        <?php foreach ([12 => 'Tous les ans', 24 => 'Tous les 2 ans', 6 => 'Tous les 6 mois'] as $m => $lab): ?><option value="<?= $m ?>" <?= (int)($c['period_months'] ?? 12) === $m ? 'selected' : '' ?>><?= e($lab) ?></option><?php endforeach; ?>
      </select></label>
      <label class="admin-field"><span>Prix</span><input type="text" name="price" value="<?= $v('price') ?>" placeholder="120 € TTC / an"></label>
      <label class="admin-field"><span>Statut</span><select name="status"><option value="actif">Actif</option><option value="arrêté" <?= ($c['status'] ?? '') === 'arrêté' ? 'selected' : '' ?>>Arrêté</option></select></label>
      <label class="admin-field"><span>Début du contrat</span><input type="date" name="start_date" value="<?= $v('start_date') ?>"></label>
      <label class="admin-field"><span>Dernière visite</span><input type="date" name="last_visit" value="<?= $v('last_visit') ?>"></label>
      <label class="admin-field"><span>Prochaine échéance</span><input type="date" name="next_visit" value="<?= $v('next_visit') ?>"><small style="color:#888;">Vide : calculée d'après la dernière visite (ou le début).</small></label>
    </div>
    <label class="admin-field"><span>Notes</span><textarea name="notes" rows="3"><?= $v('notes') ?></textarea></label>
  </div>
  <div class="admin-savebar" style="gap:.75rem;"><a class="admin-btn admin-btn--secondary" href="<?= e(url_for('admin/contracts.php')) ?>">Annuler</a><button class="admin-btn admin-btn--primary" type="submit">💾 Enregistrer</button></div>
</form>
<?php endif; ?>

<section class="admin-panel">
  <div class="admin-panel__head"><h2>Contrats</h2>
    <p><?php foreach (['actif' => 'Actifs', 'arrêté' => 'Arrêtés', 'tous' => 'Tous'] as $k => $lab): ?><a href="<?= e(url_for('admin/contracts.php?f='.rawurlencode($k))) ?>" style="margin-right:.8rem;<?= $k === $filter ? 'font-weight:700;' : '' ?>"><?= e($lab) ?></a><?php endforeach; ?></p></div>
  <div class="admin-panel__body admin-table-wrap">
    <?php if (!$list): ?><p style="color:#888;">Aucun contrat. Ajoutez vos clients sous contrat pour qu'ils soient prévenus avant chaque entretien.</p><?php else: ?>
    <table class="admin-table"><thead><tr><th>Client</th><th>Appareil</th><th>Dernière visite</th><th>Prochaine échéance</th><th>Rappel</th><th>Visite faite le</th><th></th></tr></thead><tbody>
    <?php foreach ($list as $r): $late = $r['next_visit'] && $r['next_visit'] < date('Y-m-d'); $near = $r['next_visit'] && $r['next_visit'] <= $soon; ?>
      <tr>
        <td><strong><?= e((string)$r['client_name']) ?></strong><br><small><?= e((string)$r['phone']) ?></small></td>
        <td><?= e((string)$r['equipment']) ?><?= $r['price'] ? '<br><small>'.e((string)$r['price']).'</small>' : '' ?></td>
        <td><?= $r['last_visit'] ? e(date('d/m/Y', strtotime((string)$r['last_visit']))) : '—' ?></td>
        <td style="<?= $late ? 'color:#b91c1c;font-weight:700;' : ($near ? 'color:#b45309;font-weight:700;' : '') ?>"><?= $r['next_visit'] ? e(date('d/m/Y', strtotime((string)$r['next_visit']))).($late ? ' (dépassée)' : '') : '—' ?></td>
        <td><?= $r['reminded_for'] && $r['reminded_for'] === $r['next_visit'] ? '✅ envoyé' : ($near ? '⏳ bientôt' : '') ?></td>
        <td><form method="post" style="display:flex;gap:.3rem;"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="act" value="visit"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
          <input type="date" name="visit_date" value="<?= e(date('Y-m-d')) ?>" style="max-width:150px;"><button class="admin-btn admin-btn--secondary" type="submit" title="Enregistrer la visite">✓</button></form></td>
        <td style="white-space:nowrap;"><a class="admin-btn admin-btn--secondary" href="<?= e(url_for('admin/contracts.php?edit='.(int)$r['id'])) ?>">✏️</a>
          <form method="post" style="display:inline;" onsubmit="return confirm('Supprimer ce contrat ?');"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="act" value="delete"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="admin-btn admin-btn--secondary" type="submit">🗑</button></form></td>
      </tr>
    <?php endforeach; ?>
    </tbody></table>
    <?php endif; ?>
  </div>
</section>

<form method="post" class="admin-panel" id="rappels">
  <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="act" value="settings">
  <div class="admin-panel__head"><h2>⏰ Rappels automatiques</h2><p>Envoyés entre 9 h et 20 h, une fois par échéance : SMS si le client a un mobile et que le compte SMS est configuré, sinon e-mail.</p></div>
  <div class="admin-panel__body">
    <label class="admin-field admin-field--check"><div class="check-row"><input type="checkbox" name="contracts_remind_enabled" value="1" <?= global_setting('contracts_remind_enabled', '1') === '1' ? 'checked' : '' ?>> Envoyer les rappels</div></label>
    <label class="admin-field" style="max-width:260px;"><span>Combien de jours avant l'échéance</span><input type="number" name="contracts_remind_days" value="<?= contracts_remind_days() ?>" min="1" max="120"></label>
    <label class="admin-field"><span>Texte du rappel</span><textarea name="contracts_remind_text" rows="3"><?= e(global_setting('contracts_remind_text', '{entreprise} : bonjour {nom}, l\'entretien de votre {appareil} arrive à échéance le {date}. Appelez-nous au {telephone} pour fixer un rendez-vous.')) ?></textarea>
      <small style="color:#888;">Variables : {entreprise}, {nom}, {appareil}, {date}, {telephone}.</small></label>
  </div>
  <div class="admin-savebar"><button class="admin-btn admin-btn--primary" type="submit">💾 Enregistrer</button></div>
</form>
<?php require __DIR__ . '/partials/footer.php'; ?>
