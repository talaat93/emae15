<?php
declare(strict_types=1);
/**
 * Factures : liste filtrable, écran de validation côte à côte (rapport ↔ facture),
 * « Valider et envoyer », paiement, relances rédigées par Claude.
 */
$pageTitle   = 'Factures';
$dispSection = 'factures';
require __DIR__.'/partials/header.php';

review_tables();
reminders_table();
$invId = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$self  = 'dispatcher/factures.php'.($invId ? '?id='.$invId : '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string)($_POST['action'] ?? '');
    $inv = $invId ? invoice_by_id($invId) : null;
    if (!$inv) { flash('error', 'Facture introuvable.'); redirect_to('dispatcher/factures.php'); }

    if ($action === 'save' || $action === 'apply_claude') {
        $lines = [];
        if ($action === 'apply_claude') {
            // Proposition de Claude (codes de la grille) + matériel hors grille du rapport.
            $rv = review_latest((int)$inv['intervention_id']);
            $iv = db_fetch('SELECT * FROM interventions WHERE id = ?', [(int)$inv['intervention_id']]);
            foreach ((array)($rv['result']['lines'] ?? []) as $l) $lines[] = ['code' => (string)$l['code'], 'qty' => (float)$l['qty']];
            if ($iv) foreach (tech_report_lines($iv) as $l) if (empty($l['code'])) $lines[] = $l;
            $vat = (float)$inv['vat_rate'];
            $summary = (string)$inv['summary'];
        } else {
            foreach ((array)($_POST['l_kind'] ?? []) as $i => $kind) {
                $qty = (float)str_replace(',', '.', (string)($_POST['l_qty'][$i] ?? '0'));
                if ($qty <= 0) continue;
                if ($kind === 'grid') {
                    $code = strtoupper(trim((string)($_POST['l_code'][$i] ?? '')));
                    if ($code !== '') $lines[] = ['code' => $code, 'qty' => $qty];
                } else {
                    $label = trim((string)($_POST['l_label'][$i] ?? ''));
                    $price = (float)str_replace([',', ' '], ['.', ''], (string)($_POST['l_price'][$i] ?? '0'));
                    if ($label !== '') $lines[] = ['label' => $label, 'qty' => $qty, 'unit_price_ht' => $price];
                }
            }
            $vat = (float)str_replace(',', '.', (string)($_POST['vat_rate'] ?? '20'));
            $summary = (string)($_POST['summary'] ?? '');
        }
        $r = invoice_save_draft($invId, $lines, $vat, $summary, $disp);
        flash($r['ok'] && !$r['error'] ? 'success' : 'error', $r['ok'] ? ($r['error'] ?? ($action === 'apply_claude' ? 'Proposition de Claude appliquée.' : 'Brouillon enregistré.')) : (string)$r['error']);
        redirect_to($self);
    }
    if ($action === 'send') {
        @set_time_limit(120);
        $r = invoice_validate_and_send($invId, (string)($_POST['email'] ?? ''), $disp);
        flash($r['ok'] ? 'success' : 'error', $r['ok'] ? 'Facture '.$r['number'].' validée et envoyée au client'.(!empty($r['simulated']) ? ' (SIMULATION : aucun e-mail réel envoyé par Pennylane)' : '').'.' : (string)$r['error']);
        redirect_to($self);
    }
    if ($action === 'paid') {
        $r = invoice_mark_paid($invId, $disp);
        flash($r['ok'] ? 'success' : 'error', $r['ok'] ? 'Facture marquée payée : le dossier est clôturé.' : (string)$r['error']);
        redirect_to($self);
    }
    if ($action === 'reminder_draft') {
        $d = reminder_draft($invId);
        $_SESSION['reminder_draft_'.$invId] = $d;
        if (!empty($d['notice'])) flash('error', (string)$d['notice']);
        redirect_to($self.'#relance');
    }
    if ($action === 'reminder_send') {
        $r = reminder_send($invId, (string)($_POST['to'] ?? ''), (string)($_POST['subject'] ?? ''), (string)($_POST['body'] ?? ''), (string)($_POST['source'] ?? ''), $disp);
        if ($r['ok']) unset($_SESSION['reminder_draft_'.$invId]);
        flash($r['ok'] ? 'success' : 'error', $r['ok'] ? 'Relance envoyée.' : (string)$r['error']);
        redirect_to($self.'#relance');
    }
    if ($action === 'pl_refresh' && !empty($inv['pennylane_id']) && !pennylane_simulated()) {
        $g = pennylane_request('GET', '/customer_invoices/'.rawurlencode((string)$inv['pennylane_id']));
        if ($g['ok']) { pennylane_store_invoice($invId, $g['data']); flash('success', 'Facture mise à jour depuis Pennylane.'); }
        else flash('error', (string)$g['error']);
        redirect_to($self);
    }
    redirect_to($self);
}

$stCfg = invoice_status_config();
$csrf  = csrf_token();

/* ═════════════ Liste ═════════════ */
if (!$invId) {
    $f = ['status' => (string)($_GET['status'] ?? ''), 'q' => trim((string)($_GET['q'] ?? '')), 'from' => (string)($_GET['from'] ?? ''), 'to' => (string)($_GET['to'] ?? '')];
    $where = ['1=1']; $params = [];
    if ($f['status'] === 'impayees')   $where[] = "i.status = 'envoyee' AND COALESCE(i.remaining_ttc, i.total_ttc) > 0";
    elseif ($f['status'] === 'retard') $where[] = "i.status = 'envoyee' AND i.due_date < CURDATE() AND COALESCE(i.remaining_ttc, i.total_ttc) > 0";
    elseif (isset($stCfg[$f['status']])) { $where[] = 'i.status = ?'; $params[] = $f['status']; }
    if ($f['q'] !== '') {
        $where[] = '(i.number LIKE ? OR i.label LIKE ? OR c.lastname LIKE ? OR c.firstname LIKE ? OR iv.ref LIKE ?)';
        array_push($params, '%'.$f['q'].'%', '%'.$f['q'].'%', '%'.$f['q'].'%', '%'.$f['q'].'%', '%'.$f['q'].'%');
    }
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $f['from'])) { $where[] = 'COALESCE(i.issue_date, DATE(i.created_at)) >= ?'; $params[] = $f['from']; }
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $f['to']))   { $where[] = 'COALESCE(i.issue_date, DATE(i.created_at)) <= ?'; $params[] = $f['to']; }
    try {
        $rows = db_fetch_all('SELECT i.*, c.lastname, c.firstname, iv.ref AS iv_ref FROM invoices i
            LEFT JOIN clients c ON c.id = i.client_id LEFT JOIN interventions iv ON iv.id = i.intervention_id
            WHERE '.implode(' AND ', $where).' ORDER BY (i.status = \'brouillon\') DESC, COALESCE(i.issue_date, DATE(i.created_at)) DESC, i.id DESC LIMIT 300', $params);
        $k = db_fetch("SELECT
            SUM(status = 'brouillon') AS a_valider,
            SUM(CASE WHEN status = 'envoyee' THEN COALESCE(remaining_ttc, total_ttc) ELSE 0 END) AS impaye,
            SUM(status = 'envoyee' AND due_date < CURDATE() AND COALESCE(remaining_ttc, total_ttc) > 0) AS retard,
            SUM(CASE WHEN status = 'payee' AND paid_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01') THEN total_ttc ELSE 0 END) AS encaisse
            FROM invoices");
    } catch (Throwable $e) { $rows = []; $k = []; }
    ?>
<div class="d-topbar">
  <div>
    <button class="d-menu-toggle" id="d-menu-toggle" aria-label="Menu">☰</button>
    <div><div class="d-topbar-title">Factures</div><div class="d-topbar-sub">Aucune facture ne part sans votre validation</div></div>
  </div>
</div>
<div class="d-content">
  <div class="dash-kpis" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:.8rem;margin-bottom:1rem;">
    <a class="d-card" style="padding:.9rem 1rem;text-decoration:none;" href="?status=brouillon"><div style="font-size:1.5rem;font-weight:800;color:#c2410c;"><?= (int)($k['a_valider'] ?? 0) ?></div><div style="color:var(--d-t2);font-size:.85rem;">À valider</div></a>
    <a class="d-card" style="padding:.9rem 1rem;text-decoration:none;" href="?status=impayees"><div style="font-size:1.5rem;font-weight:800;color:#1d4ed8;"><?= e(money_fr((float)($k['impaye'] ?? 0))) ?></div><div style="color:var(--d-t2);font-size:.85rem;">Impayés</div></a>
    <a class="d-card" style="padding:.9rem 1rem;text-decoration:none;" href="?status=retard"><div style="font-size:1.5rem;font-weight:800;color:#b91c1c;"><?= (int)($k['retard'] ?? 0) ?></div><div style="color:var(--d-t2);font-size:.85rem;">En retard</div></a>
    <div class="d-card" style="padding:.9rem 1rem;"><div style="font-size:1.5rem;font-weight:800;color:#15803d;"><?= e(money_fr((float)($k['encaisse'] ?? 0))) ?></div><div style="color:var(--d-t2);font-size:.85rem;">Encaissé ce mois</div></div>
  </div>
  <form class="d-card" method="get" style="padding:.8rem 1rem;display:flex;gap:.6rem;flex-wrap:wrap;align-items:flex-end;margin-bottom:1rem;">
    <div class="d-field" style="margin:0;flex:2;min-width:180px;"><label for="q">Recherche</label><input id="q" name="q" value="<?= e($f['q']) ?>" placeholder="N° de facture, client, référence"></div>
    <div class="d-field" style="margin:0;min-width:150px;"><label for="st">Statut</label>
      <select id="st" name="status"><option value="">Tous</option>
        <?php foreach ($stCfg as $sk => $sc): ?><option value="<?= e($sk) ?>" <?= $f['status'] === $sk ? 'selected' : '' ?>><?= e($sc['label']) ?></option><?php endforeach; ?>
        <option value="impayees" <?= $f['status'] === 'impayees' ? 'selected' : '' ?>>Impayées</option>
        <option value="retard" <?= $f['status'] === 'retard' ? 'selected' : '' ?>>En retard</option>
      </select></div>
    <div class="d-field" style="margin:0;"><label for="from">Du</label><input id="from" type="date" name="from" value="<?= e($f['from']) ?>"></div>
    <div class="d-field" style="margin:0;"><label for="to">Au</label><input id="to" type="date" name="to" value="<?= e($f['to']) ?>"></div>
    <button class="d-btn d-btn--primary" type="submit">Filtrer</button>
    <?php if (array_filter($f)): ?><a class="d-btn d-btn--ghost" href="<?= e(url_for('dispatcher/factures.php')) ?>">Effacer</a><?php endif; ?>
  </form>
  <div class="d-card" style="overflow-x:auto;">
    <table class="d-table">
      <thead><tr><th>Date</th><th>N°</th><th>Client</th><th>Objet</th><th style="text-align:right;">TTC</th><th style="text-align:right;">Reste dû</th><th>Statut</th><th></th></tr></thead>
      <tbody>
      <?php if (!$rows): ?><tr><td colspan="8" style="text-align:center;color:var(--d-t3);padding:1.5rem;">Aucune facture.</td></tr><?php endif; ?>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td style="white-space:nowrap;"><?= e(date('d/m/Y', strtotime((string)($r['issue_date'] ?: $r['created_at'])))) ?></td>
          <td style="white-space:nowrap;font-weight:600;"><?= e((string)($r['number'] ?: '—')) ?></td>
          <td><?= e(trim(($r['lastname'] ?? '').' '.($r['firstname'] ?? '')) ?: '—') ?></td>
          <td style="font-size:.84rem;color:var(--d-t2);"><?= e(mb_strimwidth((string)$r['label'], 0, 70, '…')) ?></td>
          <td style="text-align:right;white-space:nowrap;"><?= e(money_fr((float)$r['total_ttc'])) ?></td>
          <td style="text-align:right;white-space:nowrap;"><?= in_array($r['status'], ['envoyee', 'validee'], true) ? e(money_fr((float)($r['remaining_ttc'] ?? $r['total_ttc']))) : '' ?></td>
          <td><?= invoice_badge($r) ?></td>
          <td><a class="d-btn d-btn--sm <?= $r['status'] === 'brouillon' ? 'd-btn--primary' : '' ?>" href="<?= e(url_for('dispatcher/factures.php?id='.(int)$r['id'])) ?>"><?= $r['status'] === 'brouillon' ? 'Valider' : 'Voir' ?></a></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php
    require __DIR__.'/partials/footer.php';
    return;
}

/* ═════════════ Écran de validation ═════════════ */
$inv = invoice_by_id($invId);
if (!$inv) { flash('error', 'Facture introuvable.'); redirect_to('dispatcher/factures.php'); }
$iv = !empty($inv['intervention_id']) ? get_intervention_by_id((int)$inv['intervention_id']) : null;
$client = !empty($inv['client_id']) ? get_client_by_id((int)$inv['client_id']) : null;
$review = $iv ? review_latest((int)$iv['id']) : null;
$rv = $review['result'] ?? [];
$isDraft = $inv['status'] === 'brouillon';
$grid = price_grid_rows(true, (string)($iv['category'] ?? ''));
$reminders = invoice_reminders($invId);
$draftRem = $_SESSION['reminder_draft_'.$invId] ?? null;
$photos = $iv ? intervention_photo_paths($iv) : [];
?>
<div class="d-topbar">
  <div>
    <button class="d-menu-toggle" id="d-menu-toggle" aria-label="Menu">☰</button>
    <div>
      <div class="d-topbar-title"><?= $isDraft ? 'Valider la facture' : 'Facture '.e((string)$inv['number']) ?> <?= invoice_badge($inv) ?></div>
      <div class="d-topbar-sub"><?= e(trim(($client['lastname'] ?? '').' '.($client['firstname'] ?? ''))) ?><?= $iv ? ' · '.e((string)$iv['ref']) : '' ?></div>
    </div>
  </div>
  <div class="d-topbar-actions">
    <?php if ($iv): ?><a class="d-btn d-btn--sm" href="<?= e(url_for('dispatcher/intervention_view.php?id='.(int)$iv['id'])) ?>">Fiche intervention</a><?php endif; ?>
    <a class="d-btn d-btn--sm d-btn--ghost" href="<?= e(url_for('dispatcher/factures.php')) ?>">← Factures</a>
  </div>
</div>

<div class="d-content">
  <?php if (!empty($inv['sync_error'])): ?><div class="d-flash" style="background:#fff7ed;border:1px solid #fed7aa;color:#9a3412;">Pennylane : <?= e((string)$inv['sync_error']) ?></div><?php endif; ?>
  <?php if (pennylane_simulated()): ?><div class="d-flash" style="background:#eff6ff;border:1px solid #bfdbfe;color:#1e3a8a;">Mode SIMULATION : rien n'est transmis à Pennylane et aucun e-mail de facture n'est envoyé au client.</div><?php endif; ?>

  <div class="fac-grid">
    <!-- Rapport -->
    <div class="d-card">
      <div class="d-card-head"><div class="d-card-title">Rapport du technicien</div><?php if ($iv): ?><span style="font-size:.8rem;color:var(--d-t2);"><?= e((string)($iv['tech_name'] ?? '')) ?></span><?php endif; ?></div>
      <div class="d-card-body" style="font-size:.88rem;">
        <?php if (!$iv): ?>
          <div style="color:var(--d-t2);">Facture importée de Pennylane, sans intervention liée.</div>
        <?php else: ?>
          <div class="d-info-row"><span class="d-info-label">Intervention</span><span class="d-info-value"><?= e(intervention_category_config()[$iv['category']]['label'] ?? '') ?> · <?= !empty($iv['tech_arrived_at']) ? e(date('d/m/Y H:i', strtotime((string)$iv['tech_arrived_at']))) : '' ?><?= !empty($iv['tech_close_time']) ? ' → '.e(substr((string)$iv['tech_close_time'], 0, 5)) : '' ?></span></div>
          <div class="d-info-row"><span class="d-info-label">Terminée</span><span class="d-info-value"><?= ($iv['tech_job_completed'] ?? null) === null ? '—' : ((int)$iv['tech_job_completed'] ? 'Oui' : 'Non — '.e((string)$iv['tech_incomplete_reason'])) ?></span></div>
          <?php if (!empty($iv['tech_diagnostic'])): ?><div class="d-label" style="margin-top:.6rem;">Diagnostic</div><div class="fac-text"><?= nl2br(e((string)$iv['tech_diagnostic'])) ?></div><?php endif; ?>
          <?php if (!empty($iv['tech_report'])): ?><div class="d-label" style="margin-top:.6rem;">Travaux réalisés</div><div class="fac-text"><?= nl2br(e((string)$iv['tech_report'])) ?></div><?php endif; ?>
          <?php $decl = tech_report_pricing($iv, $client); if ($decl['lines']): ?>
            <div class="d-label" style="margin-top:.6rem;">Déclaré et signé sur place</div>
            <?php foreach ($decl['lines'] as $l): ?><div class="d-info-row"><span><?= e($l['label']) ?><?= $l['unit'] !== 'pourcent' ? ' × '.e(rtrim(rtrim(number_format((float)$l['qty'], 2, ',', ''), '0'), ',')) : '' ?></span><span class="d-info-value"><?= e(money_fr((float)$l['total_ht'])) ?> HT</span></div><?php endforeach; ?>
            <div class="d-info-row"><b>Total</b><b class="d-info-value"><?= e(money_fr($decl['total_ttc'])) ?> TTC</b></div>
          <?php endif; ?>
          <?php if ($photos): ?>
            <div class="d-label" style="margin-top:.6rem;">Photos (<?= count($photos) ?>)</div>
            <div style="display:flex;gap:.4rem;flex-wrap:wrap;"><?php foreach (array_slice($photos, 0, 8) as $p): ?><a href="<?= e(asset_url($p)) ?>" target="_blank" rel="noopener"><img src="<?= e(asset_url($p)) ?>" alt="" style="width:78px;height:78px;object-fit:cover;border-radius:6px;border:1px solid var(--d-border);" loading="lazy"></a><?php endforeach; ?></div>
          <?php endif; ?>
          <?php if (!empty($iv['client_signature'])): ?>
            <div class="d-label" style="margin-top:.6rem;">Signature du client<?= !empty($iv['tech_client_name']) ? ' — '.e((string)$iv['tech_client_name']) : '' ?></div>
            <img src="<?= e((string)$iv['client_signature']) ?>" alt="Signature" style="height:70px;background:#fff;border:1px solid var(--d-border);border-radius:6px;">
          <?php endif; ?>
          <?php if ($review): ?>
            <div class="d-label" style="margin-top:.8rem;">Relecture <?= $review['source'] === 'claude' ? 'par Claude' : '(contrôles standard)' ?></div>
            <?php foreach ((array)($rv['inconsistencies'] ?? []) as $it): ?><div style="color:#92400e;font-size:.84rem;">• <?= e((string)$it) ?></div><?php endforeach; ?>
            <?php if ($review['source'] === 'claude' && !empty($rv['lines']) && $isDraft): ?>
              <div style="font-size:.84rem;margin-top:.4rem;">Proposition : <?= e(implode(', ', array_map(static fn($l) => $l['code'].' ×'.rtrim(rtrim(number_format((float)$l['qty'], 2, ',', ''), '0'), ','), $rv['lines']))) ?> → <?= e(money_fr((float)($rv['proposed_total_ttc'] ?? 0))) ?> TTC</div>
              <form method="post" style="margin-top:.4rem;" onsubmit="return confirm('Remplacer les lignes du brouillon par la proposition de Claude ?');">
                <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>"><input type="hidden" name="id" value="<?= $invId ?>">
                <button type="submit" name="action" value="apply_claude" class="d-btn d-btn--sm">Appliquer la proposition de Claude</button>
              </form>
            <?php endif; ?>
          <?php endif; ?>
        <?php endif; ?>
      </div>
    </div>

    <!-- Facture -->
    <div>
      <form method="post" class="d-card" id="fac-form">
        <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>"><input type="hidden" name="id" value="<?= $invId ?>">
        <div class="d-card-head"><div class="d-card-title">Facture</div><span style="font-size:.8rem;color:var(--d-t2);"><?= !empty($inv['pennylane_id']) ? 'Pennylane n° '.e((string)$inv['pennylane_id']) : 'Pas encore dans Pennylane' ?></span></div>
        <div class="d-card-body">
          <div class="d-field"><label for="summary">Description pour le client</label>
            <textarea id="summary" name="summary" rows="3" <?= $isDraft ? '' : 'readonly' ?>><?= e((string)$inv['summary']) ?></textarea></div>
          <table class="d-table fac-lines" style="font-size:.85rem;">
            <thead><tr><th>Désignation</th><th style="width:70px;">Qté</th><th style="width:95px;text-align:right;">P.U. HT</th><th style="width:95px;text-align:right;">Total HT</th></tr></thead>
            <tbody id="fac-body">
            <?php
            $editLines = $inv['lines'];
            if ($isDraft) { $editLines[] = ['code' => '', 'qty' => 1, 'free' => false, 'label' => '', 'unit' => '', 'unit_price_ht' => 0, 'total_ht' => 0, '_new' => true]; $editLines[] = ['code' => '', 'qty' => 1, 'free' => true, 'label' => '', 'unit' => 'unite', 'unit_price_ht' => 0, 'total_ht' => 0, '_new' => true]; }
            foreach ($editLines as $l):
              $isPct = ($l['unit'] ?? '') === 'pourcent';
              $kind = !empty($l['free']) ? 'free' : 'grid'; ?>
              <tr class="<?= !empty($l['_new']) ? 'fac-new' : '' ?>">
                <td>
                  <?php if (!$isDraft): ?>
                    <?= e((string)$l['label']) ?>
                  <?php else: ?>
                    <input type="hidden" name="l_kind[]" value="<?= $kind ?>">
                    <?php if ($kind === 'grid'): ?>
                      <select name="l_code[]" style="width:100%;"><option value=""><?= !empty($l['_new']) ? '+ Prestation de la grille…' : '—' ?></option>
                        <?php foreach ($grid as $g): ?><option value="<?= e($g['code']) ?>" <?= ($l['code'] ?? '') === $g['code'] ? 'selected' : '' ?>><?= e($g['label']) ?></option><?php endforeach; ?></select>
                      <input type="hidden" name="l_label[]" value=""><input type="hidden" name="l_price[]" value="">
                    <?php else: ?>
                      <input type="hidden" name="l_code[]" value="">
                      <input name="l_label[]" value="<?= e((string)$l['label']) ?>" placeholder="+ Ligne libre (matériel hors grille)" style="width:100%;">
                    <?php endif; ?>
                  <?php endif; ?>
                  <?php if (!empty($l['free']) && empty($l['_new'])): ?><span class="fac-tag">hors grille</span><?php endif; ?>
                </td>
                <td><?php if ($isDraft && !$isPct): ?><input name="l_qty[]" inputmode="decimal" value="<?= e(rtrim(rtrim(number_format((float)$l['qty'], 2, ',', ''), '0'), ',')) ?>" style="width:100%;"><?php elseif ($isDraft): ?><input type="hidden" name="l_qty[]" value="1">—<?php else: ?><?= $isPct ? '' : e(rtrim(rtrim(number_format((float)$l['qty'], 2, ',', ''), '0'), ',')) ?><?php endif; ?></td>
                <td style="text-align:right;"><?php if ($isDraft && $kind === 'free'): ?><input name="l_price[]" inputmode="decimal" value="<?= !empty($l['_new']) ? '' : e(number_format((float)$l['unit_price_ht'], 2, ',', '')) ?>" style="width:100%;text-align:right;" placeholder="0,00"><?php else: ?><?= $isPct || !empty($l['_new']) ? '' : e(money_fr((float)$l['unit_price_ht'])) ?><?php endif; ?></td>
                <td style="text-align:right;white-space:nowrap;"><?= !empty($l['_new']) ? '' : e(money_fr((float)$l['total_ht'])) ?></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot>
              <tr><td colspan="3" style="text-align:right;">Total HT</td><td style="text-align:right;"><?= e(money_fr((float)$inv['total_ht'])) ?></td></tr>
              <tr><td colspan="3" style="text-align:right;">TVA
                <?php if ($isDraft): ?><select name="vat_rate"><?php foreach ([10.0, 20.0, 5.5] as $vr): ?><option value="<?= $vr ?>" <?= abs((float)$inv['vat_rate'] - $vr) < .01 ? 'selected' : '' ?>><?= str_replace('.', ',', (string)$vr) ?> %</option><?php endforeach; ?></select>
                <?php else: ?><?= e(rtrim(rtrim(number_format((float)$inv['vat_rate'], 1, ',', ''), '0'), ',')) ?> %<?php endif; ?></td>
                <td style="text-align:right;"><?= e(money_fr((float)$inv['total_tva'])) ?></td></tr>
              <tr><td colspan="3" style="text-align:right;font-weight:700;">Total TTC</td><td style="text-align:right;font-weight:800;font-size:1rem;"><?= e(money_fr((float)$inv['total_ttc'])) ?></td></tr>
            </tfoot>
          </table>
          <?php if ($isDraft): ?>
            <div style="font-size:.78rem;color:var(--d-t3);margin:.4rem 0 .7rem;">Les prix des prestations viennent de la grille tarifaire ; les totaux sont recalculés à l'enregistrement. TVA 10 % : particulier, logement de plus de 2 ans.</div>
            <button type="submit" name="action" value="save" class="d-btn">Enregistrer et recalculer</button>
          <?php endif; ?>
        </div>
      </form>

      <?php if (in_array($inv['status'], ['brouillon', 'validee'], true)): ?>
      <form method="post" class="d-card" style="margin-top:1rem;" onsubmit="return confirm('Valider définitivement cette facture de ' + <?= e(json_encode(money_fr((float)$inv['total_ttc']))) ?> + ' TTC et l\'envoyer à ' + this.email.value + ' ?\n\nUne facture validée reçoit un numéro définitif et ne peut plus être modifiée.');">
        <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>"><input type="hidden" name="id" value="<?= $invId ?>"><input type="hidden" name="action" value="send">
        <div class="d-card-body">
          <div class="d-field"><label class="req" for="em">E-mail du client</label><input id="em" type="email" name="email" required value="<?= e((string)($client['email'] ?? '')) ?>"></div>
          <button type="submit" class="d-btn d-btn--primary d-btn--lg" style="width:100%;justify-content:center;"><?= $inv['status'] === 'validee' ? 'Envoyer la facture' : 'Valider et envoyer' ?> — <?= e(money_fr((float)$inv['total_ttc'])) ?> TTC</button>
          <div style="font-size:.78rem;color:var(--d-t3);margin-top:.4rem;">Finalisation et envoi par Pennylane ; une copie est adressée au technicien.</div>
        </div>
      </form>
      <?php endif; ?>

      <?php if (in_array($inv['status'], ['validee', 'envoyee', 'payee'], true)): ?>
      <div class="d-card" style="margin-top:1rem;">
        <div class="d-card-body">
          <div class="d-info-row"><span class="d-info-label">Numéro</span><span class="d-info-value"><?= e((string)$inv['number']) ?></span></div>
          <div class="d-info-row"><span class="d-info-label">Date / échéance</span><span class="d-info-value"><?= $inv['issue_date'] ? e(date('d/m/Y', strtotime((string)$inv['issue_date']))) : '—' ?> / <?= $inv['due_date'] ? e(date('d/m/Y', strtotime((string)$inv['due_date']))) : '—' ?></span></div>
          <?php if (!empty($inv['sent_at'])): ?><div class="d-info-row"><span class="d-info-label">Envoyée le</span><span class="d-info-value"><?= e(date('d/m/Y H:i', strtotime((string)$inv['sent_at']))) ?></span></div><?php endif; ?>
          <div class="d-info-row"><span class="d-info-label">Reste dû</span><span class="d-info-value"><b><?= e(money_fr($inv['status'] === 'payee' ? 0 : (float)($inv['remaining_ttc'] ?? $inv['total_ttc']))) ?></b><?= invoice_is_late($inv) ? ' · '.invoice_days_late($inv).' j de retard' : '' ?></span></div>
          <div style="display:flex;gap:.5rem;flex-wrap:wrap;margin-top:.7rem;">
            <?php if (!empty($inv['pdf_url']) && !str_contains((string)$inv['pdf_url'], 'example.')): ?><a class="d-btn d-btn--sm" href="<?= e((string)$inv['pdf_url']) ?>" target="_blank" rel="noopener">PDF (Pennylane)</a><?php endif; ?>
            <?php if (!empty($inv['pennylane_id']) && !pennylane_simulated()): ?>
              <form method="post"><input type="hidden" name="csrf_token" value="<?= e($csrf) ?>"><input type="hidden" name="id" value="<?= $invId ?>"><button type="submit" name="action" value="pl_refresh" class="d-btn d-btn--sm">Actualiser depuis Pennylane</button></form>
            <?php endif; ?>
            <?php if (in_array($inv['status'], ['validee', 'envoyee'], true)): ?>
              <form method="post" onsubmit="return confirm('Confirmer le paiement complet de cette facture ?');"><input type="hidden" name="csrf_token" value="<?= e($csrf) ?>"><input type="hidden" name="id" value="<?= $invId ?>"><button type="submit" name="action" value="paid" class="d-btn d-btn--sm d-btn--success">Marquer payée</button></form>
            <?php endif; ?>
          </div>
        </div>
      </div>
      <?php endif; ?>

      <?php if (in_array($inv['status'], ['envoyee', 'validee'], true)): ?>
      <div class="d-card" id="relance" style="margin-top:1rem;">
        <div class="d-card-head"><div class="d-card-title">Relances</div><span style="font-size:.8rem;color:var(--d-t2);"><?= count($reminders) ?> envoyée(s)</span></div>
        <div class="d-card-body">
          <?php foreach ($reminders as $rm): ?>
            <div style="font-size:.84rem;margin-bottom:.35rem;">Relance n° <?= (int)$rm['level'] ?> — <?= e(date('d/m/Y H:i', strtotime((string)$rm['sent_at']))) ?> à <?= e((string)$rm['recipient']) ?></div>
          <?php endforeach; ?>
          <?php if ($draftRem): ?>
            <form method="post">
              <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>"><input type="hidden" name="id" value="<?= $invId ?>"><input type="hidden" name="action" value="reminder_send">
              <input type="hidden" name="source" value="<?= e((string)$draftRem['source']) ?>">
              <div style="font-size:.78rem;color:var(--d-t2);margin-bottom:.4rem;"><?= $draftRem['source'] === 'claude' ? 'Rédigée par Claude' : 'Modèle standard' ?> — relisez avant d'envoyer. {{client}} sera remplacé par le nom du client.</div>
              <div class="d-field"><label class="req" for="rto">Destinataire</label><input id="rto" type="email" name="to" required value="<?= e((string)($client['email'] ?? '')) ?>"></div>
              <div class="d-field"><label class="req" for="rsub">Objet</label><input id="rsub" name="subject" required value="<?= e((string)$draftRem['subject']) ?>"></div>
              <div class="d-field"><label class="req" for="rbody">Message</label><textarea id="rbody" name="body" rows="9" required><?= e((string)$draftRem['body']) ?></textarea></div>
              <button type="submit" class="d-btn d-btn--primary" onclick="return confirm('Envoyer cette relance au client ?');">Envoyer la relance</button>
            </form>
          <?php else: ?>
            <form method="post">
              <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>"><input type="hidden" name="id" value="<?= $invId ?>"><input type="hidden" name="action" value="reminder_draft">
              <button type="submit" class="d-btn d-btn--sm" onclick="this.textContent='Rédaction…';">Préparer une relance<?= claude_is_configured() ? ' avec Claude' : '' ?></button>
            </form>
          <?php endif; ?>
        </div>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>
<style>
.fac-grid { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1.15fr); gap: 1rem; align-items: start; }
@media (max-width: 1000px) { .fac-grid { grid-template-columns: 1fr; } }
.fac-text { background: var(--d-card-2); border-radius: 6px; padding: .55rem .7rem; line-height: 1.55; }
.fac-lines input, .fac-lines select { padding: .3rem .45rem; border: 1px solid var(--d-border-2); border-radius: 6px; font: inherit; font-size: .84rem; background: #fff; }
.fac-new td { background: #fafbfc; }
.fac-tag { font-size: .68rem; background: #fef3c7; color: #92400e; border-radius: 4px; padding: .05rem .35rem; margin-left: .3rem; }
</style>
<?php require __DIR__.'/partials/footer.php'; ?>
