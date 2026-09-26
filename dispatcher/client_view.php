<?php
declare(strict_types=1);
$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { require_once __DIR__.'/../includes/bootstrap.php'; redirect_to('dispatcher/clients.php'); }

$pageTitle = 'Fiche client';
$dispSection = 'clients';
require __DIR__.'/partials/header.php';

$client = get_client_by_id($id);
if (!$client) {
    flash('error', 'Client introuvable.');
    redirect_to('dispatcher/clients.php');
}

// Handle POST: update client
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update') {
    verify_csrf();
    $data = [
        'lastname'    => trim((string)($_POST['lastname']    ?? '')),
        'firstname'   => trim((string)($_POST['firstname']   ?? '')),
        'phone'       => trim((string)($_POST['phone']       ?? '')),
        'email'       => trim((string)($_POST['email']       ?? '')),
        'address'     => trim((string)($_POST['address']     ?? '')),
        'postal_code' => trim((string)($_POST['postal_code'] ?? '')),
        'city'        => trim((string)($_POST['city']        ?? '')),
        'floor'       => trim((string)($_POST['floor']       ?? '')),
        'digicode'    => trim((string)($_POST['digicode']    ?? '')),
        'access_info' => trim((string)($_POST['access_info'] ?? '')),
        'notes'       => trim((string)($_POST['notes']       ?? '')),
    ];
    if ($data['lastname'] === '' || $data['phone'] === '') {
        flash('error', 'Le nom et le téléphone sont obligatoires.');
    } else {
        update_client($id, $data);
        flash('success', 'Fiche client mise à jour.');
        redirect_to('dispatcher/client_view.php?id=' . $id);
    }
    $client = array_merge($client, $data);
}

// Note datée (historique des échanges avec le client)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_note') {
    verify_csrf();
    if (client_add_note($id, (string)($_POST['note'] ?? ''), $disp)) flash('success', 'Note ajoutée.');
    redirect_to('dispatcher/client_view.php?id='.$id.'#notes');
}

$interventions = get_client_interventions($id);
$fin = client_finance($id);
$clientNotes = client_notes($id);
$clientDevis = [];
try { devis_table(); $clientDevis = db_fetch_all('SELECT * FROM devis WHERE client_id = ? ORDER BY id DESC', [$id]); } catch (Throwable $e) {}
$categoryConfig = intervention_category_config();
$statusConfig   = intervention_status_config();

// Stats
$totalInterventions = count($interventions);
$totalAmount = 0.0;
$firstDate = null;
$lastDate  = null;
foreach ($interventions as $interv) {
    if (!empty($interv['amount_ttc'])) $totalAmount += (float)$interv['amount_ttc'];
    $d = $interv['scheduled_date'] ?? ($interv['created_at'] ? substr($interv['created_at'], 0, 10) : null);
    if ($d) {
        if ($firstDate === null || $d < $firstDate) $firstDate = $d;
        if ($lastDate  === null || $d > $lastDate)  $lastDate  = $d;
    }
}

$fullName = trim(($client['lastname'] ?? '').' '.($client['firstname'] ?? ''));
$initials = mb_strtoupper(mb_substr($client['lastname'] ?? '?', 0, 1, 'UTF-8'), 'UTF-8');
if (!empty($client['firstname'])) $initials .= mb_strtoupper(mb_substr($client['firstname'], 0, 1, 'UTF-8'), 'UTF-8');
?>

<div class="d-topbar">
  <button class="d-menu-toggle" id="d-menu-toggle" aria-label="Menu">
    <span></span><span></span><span></span>
  </button>
  <div class="d-topbar-title">
    <a href="<?= e(url_for('dispatcher/clients.php')) ?>" style="color:var(--d-t2);text-decoration:none;font-size:.85rem;">Clients</a>
    <span style="color:var(--d-t2);margin:0 .4rem;">/</span>
    <span class="d-topbar-ico" style="margin:0;"><?= e($fullName) ?></span>
  </div>
  <div class="d-topbar-actions">
    <a href="<?= e(url_for('dispatcher/intervention_new.php?client_id='.$id)) ?>" class="d-btn d-btn-primary d-btn-sm">
      + Nouvelle intervention
    </a>
  </div>
</div>

<div class="d-content" style="padding:1.5rem 1.75rem;">
  <div class="cv-grid" style="display:grid;grid-template-columns:minmax(0,360px) minmax(0,1fr);gap:1.5rem;align-items:start;">

    <!-- Colonne gauche: fiche client -->
    <div>

      <!-- Carte identité -->
      <div class="d-card" style="margin-bottom:1.25rem;">
        <div class="d-card-header" style="align-items:flex-start;flex-wrap:wrap;gap:.5rem;">
          <div style="display:flex;align-items:center;gap:.85rem;">
            <div style="width:52px;height:52px;border-radius:50%;background:linear-gradient(135deg,rgba(238,125,26,.3),rgba(238,125,26,.1));color:#ee7d1a;display:flex;align-items:center;justify-content:center;font-size:1.1rem;font-weight:800;flex-shrink:0;border:2px solid rgba(238,125,26,.3);">
              <?= e($initials) ?>
            </div>
            <div>
              <div style="font-size:1rem;font-weight:800;color:var(--d-t1);"><?= e($fullName) ?></div>
              <div style="font-size:.75rem;color:var(--d-t2);margin-top:.15rem;">Client #<?= $id ?><?= ($client['client_type'] ?? '') !== '' ? ' · '.e(ucfirst((string)$client['client_type'])) : '' ?><?= !empty($client['pennylane_customer_id']) ? ' · lié à Pennylane' : '' ?></div>
              <?php if ($fin['bad_payer']): ?><div style="margin-top:.35rem;"><?= client_bad_payer_badge($id) ?></div><?php endif; ?>
            </div>
          </div>
          <button onclick="toggleEdit()" id="btn-edit" class="d-btn d-btn--sm" style="flex-shrink:0;">Modifier</button>
        </div>
        <div class="d-card-body">

          <!-- Vue lecture -->
          <div id="client-view">
            <?php $rows = [
              ['', 'Téléphone', '<a href="tel:'.e(preg_replace('/\s+/','',$client['phone']??'')).'" style="color:#ee7d1a;font-weight:700;text-decoration:none;">'.e($client['phone'] ?? '—').'</a>'],
              ['', 'Email', !empty($client['email']) ? '<a href="mailto:'.e($client['email']).'" style="color:#60a5fa;text-decoration:none;">'.e($client['email']).'</a>' : '—'],
              ['', 'Adresse', array_filter([
                  $client['address'] ?? '',
                  trim(($client['postal_code']??'').' '.($client['city']??''))
              ]) ? implode(', ', array_filter([
                  $client['address'] ?? '',
                  trim(($client['postal_code']??'').' '.($client['city']??''))
              ])) : '—'],
              ['', 'Étage / Bât.', $client['floor'] ?? '—'],
              ['', 'Digicode', $client['digicode'] ?? '—'],
              ['', 'Accès', $client['access_info'] ?? '—'],
              ['', 'Notes', $client['notes'] ?? '—'],
            ]; ?>
            <?php foreach ($rows as [$ico, $label, $val]): ?>
            <div style="display:flex;gap:.65rem;align-items:flex-start;padding:.55rem 0;border-bottom:1px solid var(--d-border);">
              <span style="font-size:.9rem;flex-shrink:0;width:1.2rem;"><?= $ico ?></span>
              <span style="font-size:.78rem;color:var(--d-t2);width:80px;flex-shrink:0;padding-top:.1rem;"><?= e($label) ?></span>
              <span style="font-size:.85rem;color:var(--d-t1);flex:1;"><?= $val ?></span>
            </div>
            <?php endforeach; ?>
            <?php if (!empty($client['created_at'])): ?>
            <div style="font-size:.72rem;color:var(--d-t2);padding-top:.65rem;">Client créé le <?= e(date('d/m/Y', strtotime($client['created_at']))) ?></div>
            <?php endif; ?>
          </div>

          <!-- Formulaire édition (masqué) -->
          <form method="post" id="form-edit" style="display:none;">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem;">
              <div class="d-field">
                <label class="d-label">Nom *</label>
                <input type="text" name="lastname" class="d-input" required value="<?= e($client['lastname'] ?? '') ?>">
              </div>
              <div class="d-field">
                <label class="d-label">Prénom</label>
                <input type="text" name="firstname" class="d-input" value="<?= e($client['firstname'] ?? '') ?>">
              </div>
              <div class="d-field">
                <label class="d-label">Téléphone *</label>
                <input type="tel" name="phone" class="d-input" required value="<?= e($client['phone'] ?? '') ?>">
              </div>
              <div class="d-field">
                <label class="d-label">Email</label>
                <input type="email" name="email" class="d-input" value="<?= e($client['email'] ?? '') ?>">
              </div>
            </div>
            <div class="d-field" style="margin-top:.65rem;">
              <label class="d-label">Adresse</label>
              <input type="text" name="address" class="d-input" value="<?= e($client['address'] ?? '') ?>">
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem;margin-top:.65rem;">
              <div class="d-field">
                <label class="d-label">Code postal</label>
                <input type="text" name="postal_code" class="d-input" value="<?= e($client['postal_code'] ?? '') ?>">
              </div>
              <div class="d-field">
                <label class="d-label">Ville</label>
                <input type="text" name="city" class="d-input" value="<?= e($client['city'] ?? '') ?>">
              </div>
              <div class="d-field">
                <label class="d-label">Étage / Bât.</label>
                <input type="text" name="floor" class="d-input" value="<?= e($client['floor'] ?? '') ?>">
              </div>
              <div class="d-field">
                <label class="d-label">Digicode</label>
                <input type="text" name="digicode" class="d-input" value="<?= e($client['digicode'] ?? '') ?>">
              </div>
            </div>
            <div class="d-field" style="margin-top:.65rem;">
              <label class="d-label">Infos d'accès</label>
              <input type="text" name="access_info" class="d-input" value="<?= e($client['access_info'] ?? '') ?>">
            </div>
            <div class="d-field" style="margin-top:.65rem;">
              <label class="d-label">Notes internes</label>
              <textarea name="notes" class="d-input d-textarea" rows="2"><?= e($client['notes'] ?? '') ?></textarea>
            </div>
            <div style="display:flex;gap:.65rem;margin-top:1rem;">
              <button type="submit" class="d-btn d-btn-primary">Enregistrer</button>
              <button type="button" onclick="toggleEdit()" class="d-btn d-btn-outline">Annuler</button>
            </div>
          </form>

        </div>
      </div>

      <!-- Stats client -->
      <div class="d-card">
        <div class="d-card-header"><span class="d-card-title">Statistiques</span></div>
        <div class="d-card-body">
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:.85rem;">
            <div style="background:rgba(59,130,246,.08);border:1px solid rgba(59,130,246,.2);border-radius:8px;padding:.85rem;text-align:center;">
              <div style="font-size:1.6rem;font-weight:800;color:#60a5fa;"><?= $totalInterventions ?></div>
              <div style="font-size:.72rem;color:var(--d-t2);margin-top:.15rem;">Intervention<?= $totalInterventions > 1 ? 's' : '' ?></div>
            </div>
            <div style="background:rgba(20,184,166,.08);border:1px solid rgba(20,184,166,.2);border-radius:8px;padding:.85rem;text-align:center;">
              <div style="font-size:1.6rem;font-weight:800;color:#14b8a6;"><?= $totalAmount > 0 ? number_format($totalAmount, 0, ',', ' ') : '—' ?></div>
              <div style="font-size:.72rem;color:var(--d-t2);margin-top:.15rem;"><?= $totalAmount > 0 ? '€ TTC total' : 'Montant N/D' ?></div>
            </div>
          </div>
          <?php if ($firstDate): ?>
          <div style="margin-top:.85rem;font-size:.8rem;color:var(--d-t2);">
            <div style="display:flex;justify-content:space-between;padding:.35rem 0;border-bottom:1px solid var(--d-border);">
              <span>Première intervention</span>
              <span style="color:var(--d-t1);font-weight:600;"><?= e(date('d/m/Y', strtotime($firstDate))) ?></span>
            </div>
            <div style="display:flex;justify-content:space-between;padding:.35rem 0;">
              <span>Dernière intervention</span>
              <span style="color:var(--d-t1);font-weight:600;"><?= e(date('d/m/Y', strtotime($lastDate))) ?></span>
            </div>
          </div>
          <?php endif; ?>

          <div style="margin-top:1rem;">
            <a href="<?= e(url_for('dispatcher/intervention_new.php?client_id='.$id)) ?>" class="d-btn d-btn-primary" style="width:100%;justify-content:center;">
              + Nouvelle intervention pour ce client
            </a>
          </div>
        </div>
      </div>

    </div>

    <!-- Colonne droite: finances, documents, historique -->
    <div>
      <!-- Situation financière -->
      <div class="d-card" style="margin-bottom:1.25rem;" id="finances">
        <div class="d-card-header"><span class="d-card-title">Situation financière</span><?= client_bad_payer_badge($id, false) ?></div>
        <div class="d-card-body">
          <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(130px,1fr));gap:.7rem;margin-bottom:.9rem;">
            <?php foreach ([['Facturé', $fin['billed'], '#1d4ed8'], ['Encaissé', $fin['paid'], '#15803d'], ['Reste dû', $fin['due'], $fin['due'] > 0 ? '#b91c1c' : '#475569'], ['En retard', $fin['late_amount'], $fin['late_amount'] > 0 ? '#b91c1c' : '#475569']] as [$l, $v, $c]): ?>
              <div style="border:1px solid var(--d-border);border-radius:8px;padding:.6rem .7rem;"><div style="font-size:1.1rem;font-weight:800;color:<?= $c ?>;"><?= e(money_fr((float)$v)) ?></div><div style="font-size:.74rem;color:var(--d-t2);"><?= e($l) ?></div></div>
            <?php endforeach; ?>
          </div>
          <?php if ($fin['bad_payer']): ?><div style="font-size:.84rem;color:#991b1b;margin-bottom:.7rem;">À surveiller : <?= e(implode(', ', $fin['bad_reasons'])) ?>. Demandez un acompte ou un paiement sur place avant d'intervenir.</div><?php endif; ?>
          <?php if ($fin['invoices']): ?>
          <table class="d-table" style="font-size:.84rem;">
            <thead><tr><th>Date</th><th>N°</th><th style="text-align:right;">TTC</th><th style="text-align:right;">Reste</th><th>Statut</th></tr></thead>
            <tbody>
            <?php foreach (array_slice($fin['invoices'], 0, 15) as $in): ?>
              <tr style="cursor:pointer;" onclick="location.href='<?= e(url_for('dispatcher/factures.php?id='.(int)$in['id'])) ?>'">
                <td><?= e(date('d/m/Y', strtotime((string)($in['issue_date'] ?: $in['created_at'])))) ?></td>
                <td style="font-weight:600;"><?= e((string)($in['number'] ?: '—')) ?></td>
                <td style="text-align:right;"><?= e(money_fr((float)$in['total_ttc'])) ?></td>
                <td style="text-align:right;"><?= in_array($in['status'], ['envoyee', 'validee'], true) ? e(money_fr((float)($in['remaining_ttc'] ?? $in['total_ttc']))) : '' ?></td>
                <td><?= invoice_badge($in) ?></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
          <?php else: ?><div style="color:var(--d-t2);font-size:.86rem;">Aucune facture pour ce client.</div><?php endif; ?>
        </div>
      </div>

      <!-- Documents -->
      <?php
      $docs = [];
      foreach ($clientDevis as $dv) {
          $docs[] = ['date' => $dv['created_at'], 'label' => 'Devis '.$dv['number'].' — '.money_fr((float)$dv['total_ttc']).' TTC', 'badge' => devis_badge((string)$dv['status']), 'url' => url_for('dispatcher/devis_pdf.php?id='.(int)$dv['id'])];
          if (!empty($dv['signed_pdf_path'])) $docs[] = ['date' => $dv['signed_at'], 'label' => 'Devis '.$dv['number'].' signé', 'badge' => '', 'url' => url_for('dispatcher/devis_pdf.php?signed=1&id='.(int)$dv['id'])];
      }
      foreach ($fin['invoices'] as $in) if (!empty($in['number'])) $docs[] = ['date' => $in['issue_date'] ?: $in['created_at'], 'label' => 'Facture '.$in['number'], 'badge' => '', 'url' => url_for('dispatcher/factures.php?id='.(int)$in['id'])];
      foreach ($interventions as $ivx) if (in_array($ivx['status'] ?? '', wf_field_done(), true)) $docs[] = ['date' => $ivx['tech_completed_at'] ?? $ivx['created_at'], 'label' => 'Rapport d\'intervention '.($ivx['ref'] ?? ''), 'badge' => '', 'url' => url_for('dispatcher/rapport_pdf.php?id='.(int)$ivx['id'])];
      usort($docs, static fn($a, $b) => strcmp((string)$b['date'], (string)$a['date']));
      ?>
      <div class="d-card" style="margin-bottom:1.25rem;" id="documents">
        <div class="d-card-header"><span class="d-card-title">Documents (<?= count($docs) ?>)</span></div>
        <div class="d-card-body">
          <?php if (!$docs): ?><div style="color:var(--d-t2);font-size:.86rem;">Aucun document.</div><?php endif; ?>
          <?php foreach (array_slice($docs, 0, 30) as $doc): ?>
            <div style="display:flex;justify-content:space-between;gap:.6rem;padding:.4rem 0;border-bottom:1px solid var(--d-border);font-size:.86rem;">
              <a href="<?= e($doc['url']) ?>" target="_blank" rel="noopener" style="color:var(--d-t1);font-weight:600;text-decoration:none;"><?= e($doc['label']) ?></a>
              <span style="white-space:nowrap;color:var(--d-t2);font-size:.8rem;"><?= $doc['badge'] ?> <?= $doc['date'] ? e(date('d/m/Y', strtotime((string)$doc['date']))) : '' ?></span>
            </div>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- Notes datées -->
      <div class="d-card" style="margin-bottom:1.25rem;" id="notes">
        <div class="d-card-header"><span class="d-card-title">Notes (<?= count($clientNotes) ?>)</span></div>
        <div class="d-card-body">
          <form method="post" style="display:flex;gap:.5rem;align-items:flex-start;margin-bottom:.8rem;">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="add_note">
            <textarea name="note" rows="2" required placeholder="Appel, promesse de paiement, préférence du client…" style="flex:1;padding:.5rem .6rem;border:1px solid var(--d-border-2);border-radius:6px;font:inherit;font-size:.86rem;"></textarea>
            <button type="submit" class="d-btn d-btn--sm d-btn--primary">Ajouter</button>
          </form>
          <?php foreach ($clientNotes as $n): ?>
            <div style="padding:.45rem 0;border-bottom:1px solid var(--d-border);">
              <div style="font-size:.86rem;white-space:pre-line;"><?= e((string)$n['body']) ?></div>
              <div style="font-size:.74rem;color:var(--d-t3);margin-top:.15rem;"><?= e(date('d/m/Y H:i', strtotime((string)$n['created_at']))) ?> · <?= e((string)$n['author_name']) ?></div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="d-card">
        <div class="d-card-header">
          <span class="d-card-title">Historique des interventions (<?= $totalInterventions ?>)</span>
        </div>
        <?php if (empty($interventions)): ?>
        <div style="padding:3rem;text-align:center;color:var(--d-t2);">
          <div style="font-size:2.5rem;margin-bottom:.75rem;"></div>
          <div style="font-size:.88rem;">Aucune intervention pour ce client.</div>
          <a href="<?= e(url_for('dispatcher/intervention_new.php?client_id='.$id)) ?>" class="d-btn d-btn-primary d-btn-sm" style="margin-top:1rem;">
            Créer la première intervention →
          </a>
        </div>
        <?php else: ?>
        <div style="overflow-x:auto;">
          <table class="d-table">
            <thead>
              <tr>
                <th>Réf.</th>
                <th>Date</th>
                <th>Catégorie</th>
                <th>Type</th>
                <th>Statut</th>
                <th>Technicien</th>
                <th style="text-align:right;">Montant</th>
                <th style="text-align:right;">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($interventions as $interv): ?>
              <?php
                $catConf = $categoryConfig[$interv['category'] ?? ''] ?? ['label'=>$interv['category']??'—','icon'=>'','color'=>'#8fa0c4'];
                $stConf  = $statusConfig[$interv['status'] ?? '']   ?? ['label'=>$interv['status']??'—','color'=>'#8fa0c4','bg'=>'var(--d-card-2)'];
                $schedDate = !empty($interv['scheduled_date']) ? date('d/m/Y', strtotime($interv['scheduled_date'])) : '—';
                $amountTtc = !empty($interv['amount_ttc']) ? number_format((float)$interv['amount_ttc'], 2, ',', ' ').' €' : '—';
              ?>
              <tr>
                <td>
                  <a href="<?= e(url_for('dispatcher/intervention_view.php?id='.(int)$interv['id'])) ?>" style="color:#ee7d1a;font-weight:700;font-size:.8rem;text-decoration:none;font-family:monospace;">
                    <?= e($interv['ref'] ?? '#'.$interv['id']) ?>
                  </a>
                  <?php if (!empty($interv['urgency'])): ?>
                  <span style="color:#ef4444;font-size:.65rem;font-weight:800;margin-left:.25rem;"></span>
                  <?php endif; ?>
                </td>
                <td style="color:var(--d-t2);font-size:.82rem;"><?= e($schedDate) ?></td>
                <td><?= intervention_category_badge($interv['category'] ?? '') ?></td>
                <td style="color:var(--d-t1);font-size:.82rem;"><?= e($interv['type_label'] ?? '—') ?></td>
                <td><?= intervention_status_badge($interv['status'] ?? '') ?></td>
                <td style="color:var(--d-t2);font-size:.82rem;"><?= e($interv['tech_name'] ?? '—') ?></td>
                <td style="text-align:right;color:var(--d-t1);font-size:.85rem;font-weight:600;"><?= e($amountTtc) ?></td>
                <td style="text-align:right;">
                  <a href="<?= e(url_for('dispatcher/intervention_view.php?id='.(int)$interv['id'])) ?>" class="d-btn d-btn-outline d-btn-xs">Voir</a>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php endif; ?>
      </div>
    </div>

  </div>
</div>

<script>
function toggleEdit() {
  var view = document.getElementById('client-view');
  var form = document.getElementById('form-edit');
  var btn  = document.getElementById('btn-edit');
  if (form.style.display === 'none') {
    view.style.display = 'none';
    form.style.display = 'block';
    btn.textContent = 'Annuler';
  } else {
    view.style.display = 'block';
    form.style.display = 'none';
    btn.textContent = 'Modifier';
  }
}
</script>

<style>@media (max-width: 980px) { .cv-grid { grid-template-columns: 1fr !important; } } .cv-grid .d-card { overflow-x: auto; }</style>
<?php require __DIR__.'/partials/footer.php'; ?>
