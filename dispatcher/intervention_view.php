<?php
declare(strict_types=1);
$pageTitle   = 'Intervention';
$dispSection = 'interventions';
require __DIR__.'/partials/header.php';

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { flash('error','Identifiant invalide.'); redirect_to('dispatcher/interventions.php'); }
$iv = get_intervention_by_id($id);
if (!$iv) { flash('error','Intervention introuvable.'); redirect_to('dispatcher/interventions.php'); }

/* ─────────────────────────────────────────────────────
   POST — Actions
───────────────────────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = trim((string)($_POST['action'] ?? ''));

    if ($action === 'update') {
        $techId   = (int)($_POST['technician_id'] ?? 0);
        $newStatus = trim((string)($_POST['status'] ?? $iv['status']));
        $oldStatus = $iv['status'];

        $data = [
            'technician_id'     => $techId ?: null,
            'scheduled_date'    => trim((string)($_POST['scheduled_date']    ?? '')) ?: null,
            'scheduled_time'    => trim((string)($_POST['scheduled_time']    ?? '')) ?: null,
            'duration_estimate' => (int)($_POST['duration_estimate']         ?? 60),
            'urgency'           => !empty($_POST['urgency']) ? 1 : 0,
            'priority'          => trim((string)($_POST['priority']          ?? 'normale')),
            'category'          => trim((string)($_POST['category']          ?? '')),
            'type_label'        => trim((string)($_POST['type_label']        ?? '')),
            'installation_type' => trim((string)($_POST['installation_type'] ?? '')),
            'fault_reported'    => trim((string)($_POST['fault_reported']    ?? '')),
            'description'       => trim((string)($_POST['description']       ?? '')),
            'materials_needed'  => trim((string)($_POST['materials_needed']  ?? '')),
            'photos_required'   => disp_photo_request_value(),
            'notes_admin'       => trim((string)($_POST['notes_admin']       ?? '')),
            'quote_accepted'    => !empty($_POST['quote_accepted']) ? 1 : 0,
            'amount_ht'         => trim((string)($_POST['amount_ht']         ?? '')) !== '' ? (float)$_POST['amount_ht'] : null,
            'amount_ttc'        => trim((string)($_POST['amount_ttc']        ?? '')) !== '' ? (float)$_POST['amount_ttc'] : null,
            'deposit'           => trim((string)($_POST['deposit']           ?? '')) !== '' ? (float)$_POST['deposit'] : null,
            'remaining'         => trim((string)($_POST['remaining']         ?? '')) !== '' ? (float)$_POST['remaining'] : null,
            'payment_method'    => trim((string)($_POST['payment_method']    ?? '')),
            'status'            => $newStatus,
        ];
        $techChanged = $techId > 0 && $techId !== (int)($iv['technician_id'] ?? 0);
        if ($techChanged && in_array($newStatus, ['nouveau', 'a_assigner', 'confirmé'], true)) {
            $data['status'] = $newStatus = 'assigné';
        }
        [$vatFields, $clientType] = disp_vat_values();
        update_intervention($id, $data + $vatFields);
        if ($clientType !== null && !empty($iv['client_id'])) db_execute('UPDATE clients SET client_type = ? WHERE id = ?', [$clientType, (int)$iv['client_id']]);
        if ($techChanged) notify_intervention_assigned($id);

        if ($oldStatus !== $newStatus) {
            log_intervention_history($id, $oldStatus, $newStatus, 'dispatcher', (int)$disp['id'], (string)$disp['name'],
                trim((string)($_POST['note'] ?? 'Mise à jour fiche')));
        }
        flash('success', 'Intervention mise à jour.');
        redirect_to('dispatcher/intervention_view.php?id='.$id);
    }

    if ($action === 'assign') {
        // Assignation en un clic depuis les suggestions (le dispatcher choisit).
        $techId = (int)($_POST['technician_id'] ?? 0);
        $tech = $techId > 0 ? get_tech_by_id($techId) : null;
        $date = (string)($_POST['scheduled_date'] ?? '');
        $time = (string)($_POST['scheduled_time'] ?? '');
        if (!$tech || ($tech['status'] ?? '') !== 'actif') {
            flash('error', 'Technicien introuvable ou inactif.');
        } else {
            $upd = ['technician_id' => $techId];
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) $upd['scheduled_date'] = $date;
            if (preg_match('/^\d{2}:\d{2}$/', $time)) $upd['scheduled_time'] = $time.':00';
            $oldStatus = (string)$iv['status'];
            if (in_array($oldStatus, ['nouveau', 'a_assigner', 'confirmé'], true)) $upd['status'] = 'assigné';
            update_intervention($id, $upd);
            log_intervention_history($id, $oldStatus, (string)($upd['status'] ?? $oldStatus), 'dispatcher', (int)$disp['id'], (string)$disp['name'],
                'Assignée à '.$tech['name'].(isset($upd['scheduled_time']) ? ' ('.date('d/m', strtotime($upd['scheduled_date'] ?? (string)$iv['scheduled_date'])).' à '.$time.')' : ''));
            notify_intervention_assigned($id);
            flash('success', 'Intervention assignée à '.$tech['name'].' : il doit l\'accepter.');
        }
        redirect_to('dispatcher/intervention_view.php?id='.$id);
    }

    /* ── Relecture du rapport (Claude assiste, le dispatcher décide) ── */
    if ($action === 'review_run') {
        @set_time_limit(180);
        $rr = review_run($id, 'dispatcher', $disp);
        if (!$rr['ok']) flash('error', (string)$rr['notice']);
        else flash($rr['complete'] ? 'success' : 'error', ($rr['complete'] ? 'Rapport complet : brouillon de facture préparé.' : 'Rapport incomplet : renvoyé au technicien.')
            .($rr['notice'] ? ' '.$rr['notice'] : ''));
        redirect_to('dispatcher/intervention_view.php?id='.$id.'#relecture');
    }
    if ($action === 'review_accept' && in_array((string)$iv['status'], ['rapport_rendu', 'a_revoir', 'rapport_verifie', 'terminé'], true)) {
        $lr = review_latest($id);
        review_accept($id, $lr ? (int)$lr['id'] : null, 'dispatcher', (int)$disp['id'], (string)$disp['name'], 'Rapport validé par le dispatcher');
        flash('success', 'Rapport validé : brouillon de facture préparé.');
        redirect_to('dispatcher/intervention_view.php?id='.$id.'#facture');
    }
    if ($action === 'review_return' && in_array((string)$iv['status'], ['rapport_rendu', 'rapport_verifie', 'facture_brouillon', 'a_revoir'], true)) {
        $msg = mb_substr(trim((string)($_POST['message'] ?? '')), 0, 1000);
        if ($msg === '') {
            flash('error', 'Écrivez le message pour le technicien.');
        } else {
            wf_set_status($id, 'a_revoir', 'dispatcher', (int)$disp['id'], (string)$disp['name'], 'Renvoyé au technicien : '.mb_substr($msg, 0, 200), ['review_message' => $msg]);
            notify_tech_report_incomplete($id, $msg);
            flash('success', 'Rapport renvoyé au technicien.');
        }
        redirect_to('dispatcher/intervention_view.php?id='.$id.'#relecture');
    }

    /* ── Devis et signature Yousign ── */
    if ($action === 'devis_save') {
        $lines = [];
        foreach ((array)($_POST['dl_kind'] ?? []) as $i => $kind) {
            $qty = (float)str_replace(',', '.', (string)($_POST['dl_qty'][$i] ?? '0'));
            if ($qty <= 0) continue;
            if ($kind === 'grid') { $code = strtoupper(trim((string)($_POST['dl_code'][$i] ?? ''))); if ($code !== '') $lines[] = ['code' => $code, 'qty' => $qty]; }
            else {
                $label = trim((string)($_POST['dl_label'][$i] ?? ''));
                $price = (float)str_replace([',', ' '], ['.', ''], (string)($_POST['dl_price'][$i] ?? '0'));
                if ($label !== '' && $price > 0) $lines[] = ['label' => $label, 'qty' => $qty, 'unit_price_ht' => $price];
            }
        }
        $r = devis_save((int)($_POST['devis_id'] ?? 0) ?: null, $id, $lines, (float)str_replace(',', '.', (string)($_POST['dl_vat'] ?? '20')),
            (string)($_POST['dl_title'] ?? 'Devis'), (string)($_POST['dl_description'] ?? ''), $disp);
        flash($r['ok'] ? 'success' : 'error', $r['ok'] ? 'Devis enregistré.' : (string)$r['error']);
        redirect_to('dispatcher/intervention_view.php?id='.$id.'#devis');
    }
    if ($action === 'devis_send') {
        @set_time_limit(120);
        $r = devis_send_for_signature((int)($_POST['devis_id'] ?? 0), (string)($_POST['email'] ?? ''), (string)($_POST['phone'] ?? ''), $disp);
        flash($r['ok'] ? 'success' : 'error', $r['ok'] ? 'Devis envoyé au client pour signature'.($r['simulated'] ? ' (SIMULATION : aucun e-mail envoyé)' : '').'.' : 'Envoi impossible : '.$r['error']);
        redirect_to('dispatcher/intervention_view.php?id='.$id.'#devis');
    }
    if ($action === 'devis_refresh' || $action === 'devis_simulate') {
        $sim = $action === 'devis_simulate' ? (in_array($_POST['sim'] ?? '', ['done', 'declined'], true) ? (string)$_POST['sim'] : null) : null;
        $r = devis_refresh((int)($_POST['devis_id'] ?? 0), $sim);
        flash($r['ok'] ? 'success' : 'error', $r['ok'] ? 'Devis mis à jour.' : (string)$r['error']);
        redirect_to('dispatcher/intervention_view.php?id='.$id.'#devis');
    }

    if ($action === 'set_payment') {
        $ps = in_array($_POST['payment_status'] ?? '', ['payé', 'non_payé'], true) ? (string)$_POST['payment_status'] : null;
        update_intervention($id, ['payment_status' => $ps, 'paid_at' => $ps === 'payé' ? ($iv['paid_at'] ?: date('Y-m-d H:i:s')) : null]);
        log_intervention_history($id, $iv['status'], (string)$iv['status'], 'dispatcher', (int)$disp['id'], (string)$disp['name'],
            $ps === 'payé' ? 'Marquée payée' : 'Marquée non payée');
        flash('success', $ps === 'payé' ? 'Intervention marquée payée.' : 'Intervention marquée non payée.');
        redirect_to('dispatcher/intervention_view.php?id='.$id);
    }

    if ($action === 'quick_status') {
        $validStatuses = array_keys(intervention_status_config());
        $newStatus = trim((string)($_POST['new_status'] ?? ''));
        if ($newStatus === 'assigné' && empty($iv['technician_id'])) {
            flash('error', 'Choisissez d\'abord un technicien (bouton Modifier).');
            redirect_to('dispatcher/intervention_view.php?id='.$id);
        }
        if (in_array($newStatus, $validStatuses, true)) {
            $oldStatus = $iv['status'];
            $upd = ['status' => $newStatus];
            if (in_array($newStatus, ['terminé', 'rapport_rendu'], true) && empty($iv['tech_completed_at'])) $upd['tech_completed_at'] = date('Y-m-d H:i:s');
            if ($newStatus === 'payé') { $upd['payment_status'] = 'payé'; if (empty($iv['paid_at'])) $upd['paid_at'] = date('Y-m-d H:i:s'); }
            update_intervention($id, $upd);
            log_intervention_history($id, $oldStatus, $newStatus, 'dispatcher', (int)$disp['id'], (string)$disp['name'],
                trim((string)($_POST['note'] ?? '')));
            flash('success', 'Statut mis à jour.');
        }
        redirect_to('dispatcher/intervention_view.php?id='.$id);
    }

    if ($action === 'send_sms') {
        $target  = trim((string)($_POST['sms_target'] ?? 'client'));
        $msgText = trim((string)($_POST['sms_message'] ?? ''));
        $phone   = $target === 'tech' ? ($iv['tech_phone'] ?? '') : ($iv['client_phone'] ?? '');
        if ($phone !== '' && $msgText !== '') {
            $ok = send_sms_dispatcher($phone, mb_substr($msgText, 0, 160));
            $smsReady = global_setting('ovh_app_key') !== '' && global_setting('ovh_service_name') !== '';
            flash($ok ? 'success' : 'error', $ok ? 'SMS envoyé.' : ($smsReady ? 'Échec d\'envoi du SMS (vérifiez le numéro).' : 'SMS non envoyé : aucun compte SMS OVH n\'est configuré dans l\'administration.'));
        } else {
            flash('error', 'Numéro ou message manquant.');
        }
        redirect_to('dispatcher/intervention_view.php?id='.$id);
    }

    if ($action === 'delete') {
        try {
            db_execute('DELETE FROM intervention_history WHERE intervention_id = ?', [$id]);
            db_execute('DELETE FROM interventions WHERE id = ?', [$id]);
        } catch (Throwable $ex) { error_log('[EMAE] delete intervention: '.$ex->getMessage()); }
        flash('success', 'Intervention supprimée.');
        redirect_to('dispatcher/interventions.php');
    }
}

/* ─────────────────────────────────────────────────────
   DATA
───────────────────────────────────────────────────── */
$history   = get_intervention_history($id);
$ivClient  = !empty($iv['client_id']) ? get_client_by_id((int)$iv['client_id']) : null;
$statusCfg = intervention_status_config();
$catCfg    = intervention_category_config();
$techs     = all_technicians();
$pageTitle = 'INT #'.$id;

/* Status transitions */
$nextStatuses = wf_dispatcher_transitions();
$currentStatus = (string)($iv['status'] ?? 'nouveau');
$nexts = $nextStatuses[$currentStatus] ?? [];

/* Terminal statuses */
$terminalStatuses = wf_closed();
$isTerminal = in_array($currentStatus, $terminalStatuses, true);

/* Technician */
$assignedTech = !empty($iv['technician_id']) ? null : null;
foreach ($techs as $t) {
    if ((int)$t['id'] === (int)($iv['technician_id'] ?? 0)) { $assignedTech = $t; break; }
}

/* Amount TTC auto */
$amtHt  = (float)($iv['amount_ht']  ?? 0);
$amtTtc = (float)($iv['amount_ttc'] ?? ($amtHt * 1.2));

function fmt_money(float $v): string {
    return number_format($v, 2, ',', ' ').' €';
}
function fmt_dur(int $mins): string {
    if ($mins < 60) return $mins.' min';
    $h = intdiv($mins, 60); $m = $mins % 60;
    return $h.'h'.($m > 0 ? str_pad((string)$m, 2, '0', STR_PAD_LEFT) : '');
}
?>

<!-- TOPBAR -->
<div class="d-topbar">
  <div style="display:flex;align-items:center;gap:.75rem;">
    <button class="d-menu-toggle" id="d-menu-toggle" aria-label="Menu">☰</button>
    <div>
      <div class="d-topbar-title"><?= e($iv['ref'] ?? 'INT #'.$id) ?></div>
      <div class="d-topbar-sub">
        <?= e(trim(($iv['lastname']??'').' '.($iv['firstname']??''))) ?>
        <?php if (!empty($iv['client_city'])): ?> · <?= e($iv['client_city']) ?><?php endif; ?>
      </div>
    </div>
  </div>
  <div class="d-topbar-actions">
    <a href="<?= e(url_for('dispatcher/interventions.php')) ?>" class="d-btn d-btn--ghost d-btn--sm">← Retour</a>
    <a href="<?= e(url_for('dispatcher/rapport_pdf.php')).'?id='.$id ?>" target="_blank" class="d-btn d-btn--secondary d-btn--sm">PDF</a>
    <button type="button" id="btn-toggle-edit" class="d-btn d-btn--primary d-btn--sm">Modifier</button>
  </div>
</div>

<div class="d-content">

  <!-- 2-COLUMN LAYOUT -->
  <div style="display:grid;grid-template-columns:1fr 320px;gap:1.25rem;align-items:start;">

    <!-- ═══════════════════════════════════════
         LEFT COLUMN — details
    ════════════════════════════════════════════ -->
    <div style="display:flex;flex-direction:column;gap:1.25rem;">

      <!-- ── READ MODE ── -->
      <div id="view-mode">

        <!-- CLIENT CARD -->
        <div class="d-card">
          <div class="d-card-head">
            <div class="d-card-title">Client</div>
            <?php if (!empty($iv['client_id'])): ?>
              <a href="<?= e(url_for('dispatcher/intervention_new.php').'?client_id='.(int)$iv['client_id']) ?>"
                 class="d-btn d-btn--ghost d-btn--sm" style="font-size:.74rem;">+ Nouvelle interv.</a>
            <?php endif; ?>
          </div>
          <div class="d-card-body">
            <div class="d-info-row">
              <span class="d-info-label">Nom</span>
              <span class="d-info-value"><?= e(trim(($iv['lastname']??'').' '.($iv['firstname']??''))) ?></span>
            </div>
            <?php if (!empty($iv['client_phone'])): ?>
            <div class="d-info-row">
              <span class="d-info-label">Téléphone</span>
              <span class="d-info-value">
                <a href="tel:<?= e(preg_replace('/\s+/','',$iv['client_phone'])) ?>"
                   style="color:#F07B1D;text-decoration:none;"><?= e($iv['client_phone']) ?></a>
              </span>
            </div>
            <?php endif; ?>
            <?php if (!empty($iv['client_email'])): ?>
            <div class="d-info-row">
              <span class="d-info-label">Email</span>
              <span class="d-info-value">
                <a href="mailto:<?= e($iv['client_email']) ?>"
                   style="color:var(--d-t2);text-decoration:none;"><?= e($iv['client_email']) ?></a>
              </span>
            </div>
            <?php endif; ?>
            <?php if (!empty($iv['address'])): ?>
            <div class="d-info-row">
              <span class="d-info-label">Adresse</span>
              <span class="d-info-value" style="text-align:right;">
                <?= e($iv['address']) ?><br>
                <?= e(trim(($iv['postal_code']??'').' '.($iv['client_city']??''))) ?>
              </span>
            </div>
            <?php endif; ?>
            <?php if (!empty($iv['floor'])): ?>
            <div class="d-info-row">
              <span class="d-info-label">Étage</span>
              <span class="d-info-value"><?= e($iv['floor']) ?></span>
            </div>
            <?php endif; ?>
            <?php if (!empty($iv['digicode'])): ?>
            <div class="d-info-row">
              <span class="d-info-label">Digicode</span>
              <span class="d-info-value" style="font-family:monospace;color:#F07B1D;"><?= e($iv['digicode']) ?></span>
            </div>
            <?php endif; ?>
            <?php if (!empty($iv['access_info'])): ?>
            <div class="d-info-row" style="flex-direction:column;gap:.35rem;">
              <span class="d-info-label">Accès</span>
              <span style="font-size:.84rem;color:var(--d-t1);line-height:1.5;"><?= nl2br(e($iv['access_info'])) ?></span>
            </div>
            <?php endif; ?>
          </div>
        </div>

        <!-- TECHNIQUE CARD -->
        <div class="d-card" style="margin-top:1.25rem;">
          <div class="d-card-head">
            <div class="d-card-title">Technique</div>
            <div><?= intervention_category_badge((string)($iv['category'] ?? '')) ?></div>
          </div>
          <div class="d-card-body">
            <?php $reqP = intervention_photos_required($iv);
            if ($reqP):
              $gotTypes = array_map(static fn($p) => is_array($p) ? (string)($p['type'] ?? '') : '', (array)(json_decode((string)($iv['tech_photos'] ?? '[]'), true) ?: [])); ?>
            <div class="d-info-row" style="flex-direction:column;gap:.35rem;">
              <span class="d-info-label">Photos demandées au technicien</span>
              <div class="d-chips">
                <?php foreach ($reqP as $rp): $got = in_array($rp, $gotTypes, true); ?>
                  <span class="d-pill" style="color:<?= $got ? 'var(--d-success)' : 'var(--d-t2)' ?>;background:<?= $got ? '#ecfdf3' : 'var(--d-card-2)' ?>;"><?= e($rp) ?><?= $got ? ' · reçue' : '' ?></span>
                <?php endforeach; ?>
              </div>
            </div>
            <?php endif; ?>
            <?php if (!empty($iv['type_label'])): ?>
            <div class="d-info-row">
              <span class="d-info-label">Type</span>
              <span class="d-info-value"><?= e($iv['type_label']) ?></span>
            </div>
            <?php endif; ?>
            <?php if (!empty($iv['installation_type'])): ?>
            <div class="d-info-row">
              <span class="d-info-label">Installation</span>
              <span class="d-info-value"><?= e($iv['installation_type']) ?></span>
            </div>
            <?php endif; ?>
            <?php if (!empty($iv['fault_reported'])): ?>
            <div class="d-info-row" style="flex-direction:column;gap:.35rem;">
              <span class="d-info-label">Panne signalée</span>
              <span style="font-size:.84rem;color:var(--d-t1);line-height:1.5;background:rgba(239,68,68,.05);border:1px solid rgba(239,68,68,.15);border-radius:6px;padding:.6rem .75rem;"><?= nl2br(e($iv['fault_reported'])) ?></span>
            </div>
            <?php endif; ?>
            <?php if (!empty($iv['description'])): ?>
            <div class="d-info-row" style="flex-direction:column;gap:.35rem;">
              <span class="d-info-label">Description</span>
              <span style="font-size:.84rem;color:var(--d-t1);line-height:1.5;"><?= nl2br(e($iv['description'])) ?></span>
            </div>
            <?php endif; ?>
            <?php if (materials_text($iv['materials_needed'] ?? '') !== ''): ?>
            <div class="d-info-row" style="flex-direction:column;gap:.35rem;">
              <span class="d-info-label">Matériel nécessaire</span>
              <span style="font-size:.84rem;color:var(--d-t2);line-height:1.5;"><?= nl2br(e(materials_text($iv['materials_needed']))) ?></span>
            </div>
            <?php endif; ?>
          </div>
        </div>

        <!-- RAPPORT TECHNICIEN (si terminal) -->
        <?php if ($isTerminal && (!empty($iv['tech_report']) || !empty($iv['tech_time_spent']) || !empty($iv['tech_fault_label']) || !empty($iv['tech_signature']) || !empty($iv['client_signature']) || intervention_photo_paths($iv))): ?>
        <div class="d-card" style="margin-top:1.25rem;">
          <div class="d-card-head">
            <div class="d-card-title">Rapport technicien</div>
            <?php if (!empty($iv['tech_completed_at'])): ?>
              <span style="font-size:.76rem;color:var(--d-t2);">Terminé le <?= e(date('d/m/Y à H:i', strtotime($iv['tech_completed_at']))) ?></span>
            <?php endif; ?>
          </div>
          <div class="d-card-body">
            <?php
            $yn = static fn($v) => $v === null || $v === '' ? null : ((int)$v ? 'Oui' : 'Non');
            $facts = array_filter([
                'Intitulé de la panne'  => $iv['tech_fault_label'] ?? null,
                'N° d\'appareil'        => $iv['tech_device_number'] ?? null,
                'Réalisable'            => $yn($iv['tech_realizable'] ?? null),
                'Mauvaise utilisation'  => $yn($iv['tech_bad_use'] ?? null),
                'Ascenseur remis en service' => $yn($iv['tech_elevator_restored'] ?? null),
                'Intervention terminée' => $yn($iv['tech_job_completed'] ?? null),
                'Raison (non terminée)' => $iv['tech_incomplete_reason'] ?? null,
                'Retour à prévoir'      => ($iv['tech_job_completed'] ?? null) !== null && (int)$iv['tech_job_completed'] === 0 ? $yn($iv['tech_return_visit'] ?? null) : null,
                'Arrivée sur place'     => !empty($iv['tech_arrived_at']) ? date('d/m/Y H:i', strtotime($iv['tech_arrived_at'])) : null,
                'Fin'                   => !empty($iv['tech_close_time']) ? substr((string)$iv['tech_close_time'], 0, 5) : null,
            ], static fn($v) => $v !== null && $v !== '');
            $repCalc = tech_report_pricing($iv, $ivClient);
            ?>
            <?php foreach ($facts as $label => $val): ?>
            <div class="d-info-row"><span class="d-info-label"><?= e($label) ?></span><span class="d-info-value"><?= e($val) ?></span></div>
            <?php endforeach; ?>
            <?php if (!empty($iv['tech_diagnostic'])): ?>
            <div style="margin:1rem 0;">
              <div class="d-label">Diagnostic</div>
              <div style="font-size:.87rem;color:var(--d-t1);line-height:1.6;background:var(--d-card-2);border-radius:6px;padding:.75rem;"><?= nl2br(e($iv['tech_diagnostic'])) ?></div>
            </div>
            <?php endif; ?>
            <?php if ($repCalc['lines']): ?>
            <div style="margin:1rem 0;">
              <div class="d-label">Prestations déclarées (prix de la grille)</div>
              <table class="d-table" style="font-size:.84rem;">
                <thead><tr><th>Désignation</th><th style="text-align:right;">Qté</th><th style="text-align:right;">P.U. HT</th><th style="text-align:right;">Total HT</th></tr></thead>
                <tbody>
                <?php foreach ($repCalc['lines'] as $l): ?>
                  <tr><td><?= e($l['label']) ?><?php if ($l['free']): ?> <span style="font-size:.7rem;background:#fef3c7;color:#92400e;border-radius:4px;padding:.05rem .35rem;">hors grille — à vérifier</span><?php endif; ?></td>
                    <td style="text-align:right;"><?= $l['unit'] === 'pourcent' ? '' : e(rtrim(rtrim(number_format((float)$l['qty'], 2, ',', ''), '0'), ',')) ?></td>
                    <td style="text-align:right;"><?= $l['unit'] === 'pourcent' ? '' : e(money_fr((float)$l['unit_price_ht'])) ?></td>
                    <td style="text-align:right;"><?= e(money_fr((float)$l['total_ht'])) ?></td></tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot>
                  <tr><td colspan="3" style="text-align:right;">Total HT</td><td style="text-align:right;"><?= e(money_fr($repCalc['total_ht'])) ?></td></tr>
                  <tr><td colspan="3" style="text-align:right;">TVA <?= e(str_replace('.', ',', (string)$repCalc['vat_rate'])) ?> %</td><td style="text-align:right;"><?= e(money_fr($repCalc['total_tva'])) ?></td></tr>
                  <tr><td colspan="3" style="text-align:right;font-weight:700;">Total TTC</td><td style="text-align:right;font-weight:700;"><?= e(money_fr($repCalc['total_ttc'])) ?></td></tr>
                </tfoot>
              </table>
            </div>
            <?php endif; ?>
            <?php if (!empty($iv['tech_report'])): ?>
            <div style="margin:1rem 0;">
              <div class="d-label"><?= !empty($iv['tech_diagnostic']) ? 'Travaux réalisés' : 'Constat et travaux réalisés' ?></div>
              <div style="font-size:.87rem;color:var(--d-t1);line-height:1.6;background:var(--d-card-2);border-radius:6px;padding:.75rem;"><?= nl2br(e($iv['tech_report'])) ?></div>
            </div>
            <?php endif; ?>
            <?php if (!empty($iv['tech_notes_extra'])): ?>
            <div style="margin-bottom:1rem;">
              <div class="d-label">Remarques du technicien</div>
              <div style="font-size:.87rem;color:var(--d-t1);line-height:1.6;"><?= nl2br(e($iv['tech_notes_extra'])) ?></div>
            </div>
            <?php endif; ?>
            <?php
            $usedMats = json_decode((string)($iv['tech_materials_used'] ?? ''), true);
            if (is_array($usedMats) && $usedMats): ?>
            <div style="margin-bottom:1rem;">
              <div class="d-label">Matériel utilisé</div>
              <?php foreach ($usedMats as $um): if (!is_array($um) || empty($um['name'])) continue; ?>
                <div class="d-info-row"><span><?= e($um['name']) ?></span><span class="d-info-value"><?= e(trim(($um['qty'] ?? '').' '.($um['unit'] ?? ''))) ?><?= !empty($um['price']) ? ' · '.e(money_fr((float)str_replace(',', '.', (string)$um['price']))).' HT/u' : '' ?></span></div>
              <?php endforeach; ?>
            </div>
            <?php elseif (!empty($iv['tech_materials_used']) && !is_array($usedMats)): ?>
            <div style="margin-bottom:1rem;">
              <div class="d-label">Matériel utilisé</div>
              <div style="font-size:.87rem;color:var(--d-t1);line-height:1.6;"><?= nl2br(e($iv['tech_materials_used'])) ?></div>
            </div>
            <?php endif; ?>
            <?php if (!empty($iv['tech_time_spent'])): ?>
            <div class="d-info-row">
              <span class="d-info-label">Temps passé</span>
              <span class="d-info-value"><?= e(fmt_dur((int)$iv['tech_time_spent'])) ?></span>
            </div>
            <?php endif; ?>
            <?php if (!empty($iv['client_signature']) || !empty($iv['tech_signature'])): ?>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-top:.75rem;">
              <?php foreach (['client_signature' => 'Signature client'.(!empty($iv['tech_client_name']) ? ' — '.$iv['tech_client_name'] : ''), 'tech_signature' => 'Signature technicien'.(!empty($iv['tech_name']) ? ' — '.$iv['tech_name'] : '')] as $sk => $sl): ?>
                <div>
                  <div class="d-label"><?= e($sl) ?></div>
                  <?php if (!empty($iv[$sk])): ?>
                    <img src="<?= e($iv[$sk]) ?>" alt="<?= e($sl) ?>" style="width:100%;max-width:260px;height:110px;object-fit:contain;background:#fff;border-radius:6px;border:1px solid var(--d-border);" loading="lazy">
                  <?php else: ?>
                    <div style="font-size:.84rem;color:var(--d-t3);">Non signée</div>
                  <?php endif; ?>
                </div>
              <?php endforeach; ?>
            </div>
            <?php endif; ?>
            <?php
            $photos = intervention_photo_paths($iv);
            if (!empty($photos)): ?>
            <div style="margin-top:1rem;">
              <div style="font-size:.75rem;font-weight:700;color:var(--d-t2);text-transform:uppercase;letter-spacing:.06em;margin-bottom:.6rem;">Photos (<?= count($photos) ?>)</div>
              <div style="display:flex;gap:.5rem;flex-wrap:wrap;">
                <?php foreach ($photos as $ph): ?>
                  <a href="<?= e(asset_url($ph)) ?>" target="_blank">
                    <img src="<?= e(asset_url($ph)) ?>" alt="Photo intervention" width="80" height="80"
                         style="width:80px;height:80px;object-fit:cover;border-radius:6px;border:1px solid var(--d-border);" loading="lazy">
                  </a>
                <?php endforeach; ?>
              </div>
            </div>
            <?php endif; ?>
          </div>
        </div>
        <?php endif; ?>

        <?php
        $review  = review_latest((int)$iv['id']);
        $invoice = invoice_for_intervention((int)$iv['id']);
        $canReview = in_array($currentStatus, ['rapport_rendu', 'a_revoir', 'rapport_verifie', 'facture_brouillon', 'terminé'], true);
        if ($review || $canReview): $rv = $review['result'] ?? []; ?>
        <!-- RELECTURE DU RAPPORT -->
        <div class="d-card" id="relecture" style="margin-top:1.25rem;">
          <div class="d-card-head">
            <div class="d-card-title">Relecture du rapport</div>
            <?php if ($review): ?>
              <span style="font-size:.76rem;color:var(--d-t2);"><?= $review['source'] === 'claude' ? 'par Claude' : 'contrôles standard' ?> · <?= e(date('d/m H:i', strtotime((string)$review['created_at']))) ?></span>
            <?php endif; ?>
          </div>
          <div class="d-card-body">
            <?php if (!$review): ?>
              <div style="color:var(--d-t2);font-size:.88rem;">Relecture pas encore faite<?= $currentStatus === 'rapport_rendu' ? ' (elle démarre automatiquement à la remise du rapport)' : '' ?>.</div>
            <?php else: ?>
              <?php if (!empty($review['notice'])): ?><div class="d-flash" style="background:#fff7ed;border:1px solid #fed7aa;color:#9a3412;margin-bottom:.7rem;"><?= e((string)$review['notice']) ?></div><?php endif; ?>
              <div style="font-weight:700;color:<?= (int)$review['complete'] ? 'var(--d-success)' : 'var(--d-danger)' ?>;margin-bottom:.5rem;"><?= (int)$review['complete'] ? 'Rapport complet' : 'Rapport incomplet' ?></div>
              <?php foreach (['missing' => ['Manques', '#991b1b'], 'inconsistencies' => ['Points à vérifier', '#92400e']] as $rk => [$rl, $rc]): if (empty($rv[$rk])) continue; ?>
                <div class="d-label" style="margin-top:.4rem;"><?= e($rl) ?></div>
                <ul style="margin:.2rem 0 .6rem 1.1rem;font-size:.86rem;color:<?= $rc ?>;"><?php foreach ($rv[$rk] as $it): ?><li><?= e((string)$it) ?></li><?php endforeach; ?></ul>
              <?php endforeach; ?>
              <?php if (!empty($rv['client_summary'])): ?>
                <div class="d-label">Résumé proposé pour le client</div>
                <div style="font-size:.87rem;background:var(--d-card-2);border-radius:6px;padding:.6rem .75rem;margin-bottom:.6rem;"><?= nl2br(e((string)$rv['client_summary'])) ?></div>
              <?php endif; ?>
              <?php if ($review['source'] === 'claude' && isset($rv['proposed_total_ttc'])): ?>
                <div style="font-size:.84rem;color:var(--d-t2);">Proposition de Claude : <b><?= e(implode(', ', array_map(static fn($l) => $l['code'].' ×'.rtrim(rtrim(number_format((float)$l['qty'], 2, ',', ''), '0'), ','), $rv['lines'] ?? []))) ?: '—' ?></b>
                  → <?= e(money_fr((float)$rv['proposed_total_ttc'])) ?> TTC (recalculé par la grille) · déclaré par le technicien : <?= e(money_fr((float)($rv['declared_total_ttc'] ?? 0))) ?> TTC</div>
              <?php endif; ?>
            <?php endif; ?>
            <?php if ($canReview): ?>
            <div style="display:flex;gap:.5rem;flex-wrap:wrap;margin-top:.9rem;">
              <?php if (in_array($currentStatus, ['rapport_rendu', 'a_revoir'], true)): ?>
              <form method="post" onsubmit="this.querySelector('button').textContent='Relecture en cours…';">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="review_run">
                <button type="submit" class="d-btn d-btn--sm"><?= $review ? 'Relancer la relecture' : 'Lancer la relecture' ?></button>
              </form>
              <?php endif; ?>
              <?php if (in_array($currentStatus, ['rapport_rendu', 'a_revoir', 'rapport_verifie', 'terminé'], true)): ?>
              <form method="post" onsubmit="return confirm('Valider le rapport et préparer le brouillon de facture ?');">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="review_accept">
                <button type="submit" class="d-btn d-btn--sm d-btn--primary">Valider le rapport et préparer la facture</button>
              </form>
              <?php endif; ?>
            </div>
            <?php if ($currentStatus !== 'a_revoir'): ?>
            <details style="margin-top:.7rem;">
              <summary style="cursor:pointer;font-size:.85rem;color:var(--d-t2);">Renvoyer au technicien…</summary>
              <form method="post" style="margin-top:.5rem;">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="review_return">
                <div class="d-field"><label class="req" for="rv-msg">Message au technicien</label>
                  <textarea id="rv-msg" name="message" rows="3" required><?= e((string)($rv['message_to_technician'] ?? '')) ?></textarea></div>
                <button type="submit" class="d-btn d-btn--sm d-btn--danger">Renvoyer</button>
              </form>
            </details>
            <?php else: ?>
              <div style="font-size:.84rem;color:var(--d-t2);margin-top:.6rem;">Message envoyé au technicien : « <?= e((string)($iv['review_message'] ?? '')) ?> »</div>
            <?php endif; ?>
            <?php endif; ?>
          </div>
        </div>
        <?php endif; ?>

        <?php if ($invoice): ?>
        <!-- FACTURE -->
        <div class="d-card" id="facture" style="margin-top:1.25rem;">
          <div class="d-card-head">
            <div class="d-card-title">Facture <?= $invoice['status'] === 'brouillon' ? '(brouillon à valider)' : e($invoice['number'] ? '— '.$invoice['number'] : '') ?></div>
            <span style="font-size:.8rem;font-weight:700;"><?= e(money_fr((float)$invoice['total_ttc'])) ?> TTC</span>
          </div>
          <div class="d-card-body">
            <?php if (!empty($invoice['summary'])): ?><div style="font-size:.87rem;margin-bottom:.6rem;"><?= nl2br(e((string)$invoice['summary'])) ?></div><?php endif; ?>
            <table class="d-table" style="font-size:.84rem;">
              <tbody>
              <?php foreach ($invoice['lines'] as $l): ?>
                <tr><td><?= e((string)$l['label']) ?><?= !empty($l['free']) ? ' <span style="font-size:.7rem;background:#fef3c7;color:#92400e;border-radius:4px;padding:.05rem .35rem;">hors grille</span>' : '' ?></td>
                  <td style="text-align:right;white-space:nowrap;"><?= ($l['unit'] ?? '') === 'pourcent' ? '' : e(rtrim(rtrim(number_format((float)$l['qty'], 2, ',', ''), '0'), ',')).' × '.e(money_fr((float)$l['unit_price_ht'])) ?></td>
                  <td style="text-align:right;white-space:nowrap;"><?= e(money_fr((float)$l['total_ht'])) ?></td></tr>
              <?php endforeach; ?>
              </tbody>
              <tfoot>
                <tr><td colspan="2" style="text-align:right;">Total HT</td><td style="text-align:right;"><?= e(money_fr((float)$invoice['total_ht'])) ?></td></tr>
                <tr><td colspan="2" style="text-align:right;">TVA <?= e(rtrim(rtrim(number_format((float)$invoice['vat_rate'], 1, ',', ''), '0'), ',')) ?> %</td><td style="text-align:right;"><?= e(money_fr((float)$invoice['total_tva'])) ?></td></tr>
                <tr><td colspan="2" style="text-align:right;font-weight:700;">Total TTC</td><td style="text-align:right;font-weight:700;"><?= e(money_fr((float)$invoice['total_ttc'])) ?></td></tr>
              </tfoot>
            </table>
            <div style="font-size:.78rem;color:var(--d-t3);margin-top:.5rem;">Montants calculés à partir de la grille tarifaire. Aucune facture n'est envoyée sans votre validation.</div>
            <a class="d-btn d-btn--sm <?= $invoice['status'] === 'brouillon' ? 'd-btn--primary' : '' ?>" style="margin-top:.6rem;" href="<?= e(url_for('dispatcher/factures.php?id='.(int)$invoice['id'])) ?>"><?= $invoice['status'] === 'brouillon' ? 'Vérifier et valider la facture' : 'Ouvrir la facture' ?></a>
          </div>
        </div>
        <?php endif; ?>

        <?php
        $devisList = devis_for_intervention((int)$iv['id']);
        $threshold = yousign_threshold();
        $estTtc = (float)($iv['amount_ttc'] ?? 0);
        $hasSigned = (bool)array_filter($devisList, static fn($d) => $d['status'] === 'signe');
        $showDevis = $devisList || !$isTerminal || in_array($currentStatus, ['a_revoir', 'rapport_rendu'], true) || (($iv['tech_return_visit'] ?? null) !== null && (int)$iv['tech_return_visit'] === 1);
        if ($showDevis):
          $dGrid = price_grid_rows(true, (string)($iv['category'] ?? ''));
          $dVat = iv_vat_rate($iv, $ivClient); ?>
        <!-- DEVIS -->
        <div class="d-card" id="devis" style="margin-top:1.25rem;">
          <div class="d-card-head"><div class="d-card-title">Devis</div><span style="font-size:.76rem;color:var(--d-t2);">Signature électronique Yousign<?= yousign_simulated() ? ' (SIMULATION)' : '' ?></span></div>
          <div class="d-card-body">
            <?php if (!$hasSigned && $estTtc > $threshold): ?>
              <div class="d-flash" style="background:#fff7ed;border:1px solid #fed7aa;color:#9a3412;margin-bottom:.8rem;">Montant estimé <?= e(money_fr($estTtc)) ?> TTC : au-delà de <?= e(money_fr($threshold)) ?>, faites signer un devis au client avant les travaux.</div>
            <?php endif; ?>
            <?php foreach ($devisList as $dv): ?>
              <div style="border:1px solid var(--d-border);border-radius:8px;padding:.7rem .8rem;margin-bottom:.7rem;">
                <div style="display:flex;justify-content:space-between;gap:.5rem;align-items:center;flex-wrap:wrap;">
                  <div><b><?= e((string)$dv['number']) ?></b> — <?= e((string)$dv['title']) ?> <?= devis_badge((string)$dv['status']) ?></div>
                  <b><?= e(money_fr((float)$dv['total_ttc'])) ?> TTC</b>
                </div>
                <div style="font-size:.8rem;color:var(--d-t2);margin-top:.25rem;">
                  <?= e(implode(' · ', array_map(static fn($l) => $l['label'].(($l['unit'] ?? '') !== 'pourcent' ? ' ×'.rtrim(rtrim(number_format((float)$l['qty'], 2, ',', ''), '0'), ',') : ''), $dv['lines']))) ?>
                </div>
                <?php if (!empty($dv['sent_at'])): ?><div style="font-size:.78rem;color:var(--d-t3);margin-top:.2rem;">Envoyé le <?= e(date('d/m/Y H:i', strtotime((string)$dv['sent_at']))) ?> à <?= e((string)$dv['signer_email']) ?><?= !empty($dv['signed_at']) ? ' · signé le '.e(date('d/m/Y H:i', strtotime((string)$dv['signed_at']))) : '' ?></div><?php endif; ?>
                <?php if (!empty($dv['error'])): ?><div style="font-size:.8rem;color:var(--d-danger);margin-top:.25rem;"><?= e((string)$dv['error']) ?></div><?php endif; ?>
                <div style="display:flex;gap:.4rem;flex-wrap:wrap;margin-top:.55rem;">
                  <a class="d-btn d-btn--sm" href="<?= e(url_for('dispatcher/devis_pdf.php?id='.(int)$dv['id'])) ?>" target="_blank" rel="noopener">PDF</a>
                  <?php if (!empty($dv['signed_pdf_path'])): ?><a class="d-btn d-btn--sm" href="<?= e(url_for('dispatcher/devis_pdf.php?signed=1&id='.(int)$dv['id'])) ?>" target="_blank" rel="noopener">PDF signé</a><?php endif; ?>
                  <?php if ($dv['status'] === 'envoye'): ?>
                    <form method="post"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="devis_id" value="<?= (int)$dv['id'] ?>">
                      <?php if (yousign_simulated()): ?>
                        <button name="action" value="devis_simulate" class="d-btn d-btn--sm" onclick="this.form.sim.value='done'">Simuler la signature</button>
                        <button name="action" value="devis_simulate" class="d-btn d-btn--sm d-btn--ghost" onclick="this.form.sim.value='declined'">Simuler un refus</button>
                        <input type="hidden" name="sim" value="">
                      <?php else: ?>
                        <button name="action" value="devis_refresh" class="d-btn d-btn--sm">Actualiser le statut</button>
                      <?php endif; ?>
                    </form>
                  <?php endif; ?>
                </div>
                <?php if ($dv['status'] === 'brouillon'): ?>
                  <form method="post" style="display:flex;gap:.4rem;flex-wrap:wrap;align-items:flex-end;margin-top:.6rem;" onsubmit="return confirm('Envoyer ce devis de <?= e(money_fr((float)$dv['total_ttc'])) ?> TTC au client pour signature ?');">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="devis_send"><input type="hidden" name="devis_id" value="<?= (int)$dv['id'] ?>">
                    <div class="d-field" style="margin:0;flex:1;min-width:180px;"><label class="req">E-mail du client</label><input type="email" name="email" required value="<?= e((string)($ivClient['email'] ?? '')) ?>"></div>
                    <div class="d-field" style="margin:0;min-width:140px;"><label>Mobile (code par SMS)</label><input name="phone" value="<?= e((string)($ivClient['phone'] ?? '')) ?>"></div>
                    <button type="submit" class="d-btn d-btn--primary d-btn--sm">Envoyer pour signature</button>
                  </form>
                <?php endif; ?>
              </div>
            <?php endforeach; ?>
            <details <?= $devisList ? '' : 'open' ?>>
              <summary style="cursor:pointer;font-size:.88rem;font-weight:600;">+ Nouveau devis</summary>
              <form method="post" style="margin-top:.6rem;">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="devis_save">
                <div class="d-field"><label class="req">Titre</label><input name="dl_title" required value="<?= e((intervention_category_config()[$iv['category'] ?? '']['label'] ?? 'Travaux').' — '.(trim((string)($iv['tech_fault_label'] ?? '')) ?: trim((string)($iv['type_label'] ?? '')) ?: 'devis')) ?>"></div>
                <div class="d-field"><label>Description des travaux</label><textarea name="dl_description" rows="3"><?= e(trim((string)($iv['tech_incomplete_reason'] ?? '')) ?: trim((string)($iv['tech_diagnostic'] ?? ''))) ?></textarea></div>
                <table class="d-table" style="font-size:.84rem;">
                  <thead><tr><th>Désignation</th><th style="width:70px;">Qté</th><th style="width:110px;">P.U. HT</th></tr></thead>
                  <tbody>
                  <?php for ($i = 0; $i < 4; $i++): ?>
                    <tr><td><input type="hidden" name="dl_kind[]" value="grid"><select name="dl_code[]" style="width:100%;"><option value="">— Prestation de la grille —</option><?php foreach ($dGrid as $g): ?><option value="<?= e($g['code']) ?>"><?= e($g['label']) ?> (<?= (int)$g['is_percent'] ? '+'.e(rtrim(rtrim(number_format((float)$g['price_ht'], 2, ',', ''), '0'), ',')).' %' : e(money_fr((float)$g['price_ht'])) ?>)</option><?php endforeach; ?></select><input type="hidden" name="dl_label[]" value=""></td>
                      <td><input name="dl_qty[]" value="1" inputmode="decimal" style="width:100%;"></td><td><input type="hidden" name="dl_price[]" value="">grille</td></tr>
                  <?php endfor; ?>
                  <?php for ($i = 0; $i < 2; $i++): ?>
                    <tr><td><input type="hidden" name="dl_kind[]" value="free"><input type="hidden" name="dl_code[]" value=""><input name="dl_label[]" placeholder="Fourniture ou travaux hors grille" style="width:100%;"></td>
                      <td><input name="dl_qty[]" value="1" inputmode="decimal" style="width:100%;"></td><td><input name="dl_price[]" inputmode="decimal" placeholder="0,00" style="width:100%;"></td></tr>
                  <?php endfor; ?>
                  </tbody>
                </table>
                <div style="display:flex;gap:.6rem;align-items:center;margin-top:.6rem;flex-wrap:wrap;">
                  <label style="font-size:.85rem;">TVA <select name="dl_vat"><?php foreach ([10.0, 20.0, 5.5] as $vr): ?><option value="<?= $vr ?>" <?= abs($dVat - $vr) < .01 ? 'selected' : '' ?>><?= str_replace('.', ',', (string)$vr) ?> %</option><?php endforeach; ?></select></label>
                  <button type="submit" class="d-btn d-btn--sm d-btn--primary">Enregistrer le devis</button>
                  <span style="font-size:.78rem;color:var(--d-t3);">Totaux calculés à partir de la grille tarifaire.</span>
                </div>
              </form>
            </details>
          </div>
        </div>
        <?php endif; ?>

        <?php $qualS = qual_for_intervention((int)$iv['id']); if ($qualS): $qd = $qualS['state']['danger'] ?? null; ?>
        <!-- QUALIFICATION DE L'APPEL -->
        <details class="d-card" style="margin-top:1.25rem;">
          <summary class="d-card-head" style="cursor:pointer;">
            <span class="d-card-title">Qualification de l'appel</span>
            <span style="font-size:.8rem;color:var(--d-t2);"><?= $qualS['mode'] === 'claude' ? 'assistée par Claude' : 'questionnaire standard' ?> · <?= e(date('d/m/Y H:i', strtotime((string)$qualS['created_at']))) ?></span>
          </summary>
          <div class="d-card-body" style="display:flex;flex-direction:column;gap:.45rem;">
            <?php if (!empty($qd['detected'])): ?><div style="background:#fee2e2;color:#991b1b;border-radius:8px;padding:.5rem .75rem;font-weight:600;">Danger signalé : <?= e((string)$qd['kind']) ?></div><?php endif; ?>
            <?php foreach ($qualS['state']['transcript'] ?? [] as $t): ?>
              <div style="font-size:.86rem;<?= $t['role'] === 'assistant' ? 'color:var(--d-t2);' : 'font-weight:600;padding-left:1rem;' ?>"><?= $t['role'] === 'assistant' ? 'Q : ' : 'R : ' ?><?= e((string)$t['text']) ?></div>
            <?php endforeach; ?>
          </div>
        </details>
        <?php endif; ?>

        <!-- HISTORIQUE -->
        <div class="d-card" style="margin-top:1.25rem;">
          <div class="d-card-head">
            <div class="d-card-title">Historique</div>
          </div>
          <div class="d-card-body">
            <?php if (empty($history)): ?>
              <div class="d-empty" style="padding:1.5rem 0;">
                <div class="d-empty-icon" style="font-size:1.5rem;"></div>
                <div>Aucun historique disponible.</div>
              </div>
            <?php else: ?>
            <ul class="d-status-timeline">
              <?php foreach ($history as $i => $h): ?>
              <li class="<?= $i === count($history)-1 ? 'active' : '' ?>">
                <div style="flex:1;min-width:0;">
                  <div style="display:flex;align-items:center;gap:.5rem;flex-wrap:wrap;">
                    <?php if (!empty($h['status_from'])): ?>
                      <?= intervention_status_badge((string)$h['status_from']) ?>
                      <span style="color:var(--d-t3);font-size:.8rem;">→</span>
                    <?php endif; ?>
                    <?= intervention_status_badge((string)$h['status_to']) ?>
                  </div>
                  <div style="font-size:.78rem;color:var(--d-t2);margin-top:.3rem;">
                    <?= e($h['actor_name'] ?? 'Système') ?>
                    <?php if (!empty($h['created_at'])): ?>
                      · <?= e(date('d/m/Y à H:i', strtotime($h['created_at']))) ?>
                    <?php endif; ?>
                  </div>
                  <?php if (!empty($h['note'])): ?>
                    <div style="font-size:.8rem;color:var(--d-t2);font-style:italic;margin-top:.2rem;"><?= e($h['note']) ?></div>
                  <?php endif; ?>
                </div>
              </li>
              <?php endforeach; ?>
            </ul>
            <?php endif; ?>
          </div>
        </div>

      </div><!-- /#view-mode -->

      <!-- ── EDIT MODE ── -->
      <div id="edit-mode" style="display:none;">
        <form method="post" id="form-edit">
          <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
          <input type="hidden" name="action"     value="update">

          <!-- Client info (read-only in edit mode) -->
          <div class="d-card" style="margin-bottom:1.25rem;">
            <div class="d-card-head">
              <div class="d-card-title">Client (non modifiable ici)</div>
            </div>
            <div class="d-card-body" style="font-size:.87rem;color:var(--d-t2);">
              <?= e(trim(($iv['lastname']??'').' '.($iv['firstname']??''))) ?> · <?= e($iv['client_phone'] ?? '') ?>
            </div>
          </div>

          <!-- Section Planification -->
          <div class="d-card" style="margin-bottom:1.25rem;">
            <div class="d-card-head"><div class="d-card-title">Planification</div></div>
            <div class="d-card-body">
              <div class="d-grid-3">
                <div class="d-field">
                  <label>Date planifiée</label>
                  <input type="date" name="scheduled_date" value="<?= e($iv['scheduled_date'] ?? '') ?>">
                </div>
                <div class="d-field">
                  <label>Heure</label>
                  <input type="time" name="scheduled_time" step="900" value="<?= e(substr((string)($iv['scheduled_time']??''),0,5)) ?>">
                </div>
                <div class="d-field">
                  <label>Durée estimée</label>
                  <select name="duration_estimate">
                    <?php $durSel = (int)($iv['duration_estimate'] ?? 60);
                    foreach ([30=>'30 min',60=>'1 heure',90=>'1h30',120=>'2 heures',180=>'3 heures',240=>'Demi-journée',480=>'Journée'] as $dv=>$dl): ?>
                      <option value="<?= $dv ?>" <?= $durSel===$dv?'selected':''?>><?= e($dl) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
              </div>
              <div class="d-grid-3">
                <div class="d-field">
                  <label>Priorité</label>
                  <select name="priority">
                    <?php foreach(['basse'=>'Basse','normale'=>'Normale','haute'=>'Haute','urgente'=>'Urgente'] as $pv=>$pl): ?>
                      <option value="<?=e($pv)?>" <?=($iv['priority']??'')===$pv?'selected':''?>><?=e($pl)?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="d-field">
                  <label>Technicien</label>
                  <select name="technician_id">
                    <option value="">Non assigné</option>
                    <?php foreach ($techs as $t): ?>
                      <option value="<?=(int)$t['id']?>" <?=(int)($iv['technician_id']??0)===(int)$t['id']?'selected':''?>>
                        <?=e($t['name'])?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="d-field" style="display:flex;align-items:center;gap:.6rem;padding-top:1.8rem;">
                  <label style="display:flex;align-items:center;gap:.6rem;cursor:pointer;text-transform:none;letter-spacing:0;font-size:.9rem;color:var(--d-t1);margin-bottom:0;">
                    <input type="checkbox" name="urgency" value="1" <?=!empty($iv['urgency'])?'checked':''?> style="width:auto;accent-color:#ef4444;">
                    Urgence
                  </label>
                </div>
              </div>
              <div class="d-field">
                <label>Statut</label>
                <select name="status">
                  <?php foreach ($statusCfg as $sk=>$sv): ?>
                    <option value="<?=e($sk)?>" <?=$currentStatus===$sk?'selected':''?>><?=e($sv['label'])?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="d-field">
                <label>Note de changement</label>
                <input type="text" name="note" placeholder="Raison du changement (optionnel)">
              </div>
            </div>
          </div>

          <!-- Section Technique -->
          <div class="d-card" style="margin-bottom:1.25rem;">
            <div class="d-card-head"><div class="d-card-title">Technique</div></div>
            <div class="d-card-body">
              <div class="d-grid-2">
                <div class="d-field">
                  <label>Catégorie</label>
                  <select name="category">
                    <option value="">Choisir</option>
                    <?php foreach ($catCfg as $ck=>$cv): ?>
                      <option value="<?=e($ck)?>" <?=($iv['category']??'')===$ck?'selected':''?>><?=e($cv['icon'].' '.$cv['label'])?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="d-field">
                  <label>Type d'intervention</label>
                  <input type="text" name="type_label" value="<?=e($iv['type_label']??'')?>">
                </div>
              </div>
              <div class="d-field">
                <label>Type d'installation</label>
                <input type="text" name="installation_type" value="<?=e($iv['installation_type']??'')?>">
              </div>
              <div class="d-field">
                <label>Panne signalée</label>
                <textarea name="fault_reported"><?=e($iv['fault_reported']??'')?></textarea>
              </div>
              <div class="d-field">
                <label>Description</label>
                <textarea name="description"><?=e($iv['description']??'')?></textarea>
              </div>
              <div class="d-field">
                <label>Matériel nécessaire</label>
                <textarea name="materials_needed" style="min-height:70px;"><?=e(materials_text($iv['materials_needed']??''))?></textarea>
              </div>
              <?= disp_photo_request_field(intervention_photos_required($iv)) ?>
            </div>
          </div>

          <!-- Section Financier -->
          <div class="d-card" style="margin-bottom:1.25rem;">
            <div class="d-card-head"><div class="d-card-title">Financier</div></div>
            <div class="d-card-body">
              <?= disp_vat_fields($iv, $ivClient) ?>
              <div class="d-grid-3">
                <div class="d-field">
                  <label>Montant HT (€)</label>
                  <input type="number" name="amount_ht" step="0.01" min="0" value="<?=e($iv['amount_ht']??'')?>">
                </div>
                <div class="d-field">
                  <label>Montant TTC (€)</label>
                  <input type="number" name="amount_ttc" step="0.01" min="0" value="<?=e($iv['amount_ttc']??'')?>">
                </div>
                <div class="d-field">
                  <label>Acompte (€)</label>
                  <input type="number" name="deposit" step="0.01" min="0" value="<?=e($iv['deposit']??'')?>">
                </div>
              </div>
              <div class="d-grid-2">
                <div class="d-field">
                  <label>Restant dû (€)</label>
                  <input type="number" name="remaining" step="0.01" min="0" value="<?=e($iv['remaining']??'')?>">
                </div>
                <div class="d-field">
                  <label>Moyen de paiement</label>
                  <select name="payment_method">
                    <?php foreach([''  =>'À définir','carte'=>'Carte bancaire','espèces'=>'Espèces','virement'=>'Virement','chèque'=>'Chèque'] as $pv=>$pl): ?>
                      <option value="<?=e($pv)?>" <?=($iv['payment_method']??'')===$pv?'selected':''?>><?=e($pl)?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
              </div>
              <div class="d-field" style="display:flex;align-items:center;gap:.6rem;">
                <label style="display:flex;align-items:center;gap:.6rem;cursor:pointer;text-transform:none;letter-spacing:0;font-size:.9rem;color:var(--d-t1);margin-bottom:0;">
                  <input type="checkbox" name="quote_accepted" value="1" <?=!empty($iv['quote_accepted'])?'checked':''?> style="width:auto;accent-color:#22c55e;">
                  Devis accepté
                </label>
              </div>
              <div class="d-field" style="margin-top:1rem;">
                <label>Notes internes</label>
                <textarea name="notes_admin" style="min-height:70px;"><?=e($iv['notes_admin']??'')?></textarea>
              </div>
            </div>
          </div>

          <div style="display:flex;gap:.75rem;justify-content:flex-end;padding-bottom:1.5rem;">
            <button type="button" id="btn-cancel-edit" class="d-btn d-btn--secondary">Annuler</button>
            <button type="submit" class="d-btn d-btn--primary">Enregistrer</button>
          </div>
        </form>
      </div><!-- /#edit-mode -->

    </div>
    <!-- /LEFT COLUMN -->

    <!-- ═══════════════════════════════════════
         RIGHT COLUMN — statut, planning, finances, actions
    ════════════════════════════════════════════ -->
    <div style="display:flex;flex-direction:column;gap:1rem;position:sticky;top:1rem;">

      <!-- STATUT CARD -->
      <div class="d-card">
        <div class="d-card-head"><div class="d-card-title">Statut</div></div>
        <div class="d-card-body">
          <div style="margin-bottom:1rem;">
            <?= intervention_status_badge($currentStatus) ?>
            <?php if (!empty($iv['urgency'])): ?>
              <span class="d-badge-urgency" style="margin-left:.4rem;">Urgence</span>
            <?php endif; ?>
          </div>

          <?php if (!empty($nexts)): ?>
          <div style="font-size:.75rem;font-weight:700;color:var(--d-t2);text-transform:uppercase;letter-spacing:.06em;margin-bottom:.5rem;">
            Faire passer à :
          </div>
          <div style="display:flex;flex-direction:column;gap:.5rem;">
            <?php foreach ($nexts as $ns): ?>
              <?php $nsCfg = $statusCfg[$ns] ?? ['label'=>$ns,'color'=>'#8fa0c4','bg'=>'var(--d-card-2)']; ?>
              <form method="post">
                <input type="hidden" name="csrf_token"  value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action"      value="quick_status">
                <input type="hidden" name="new_status"  value="<?= e($ns) ?>">
                <button type="submit" class="d-btn d-btn--sm" style="width:100%;justify-content:center;background:<?= e($nsCfg['bg']) ?>;color:<?= e($nsCfg['color']) ?>;border:1px solid <?= e($nsCfg['color']) ?>44;">
                  → <?= e($nsCfg['label']) ?>
                </button>
              </form>
            <?php endforeach; ?>
          </div>
          <?php else: ?>
            <div style="font-size:.8rem;color:var(--d-t3);font-style:italic;">Aucune transition disponible</div>
          <?php endif; ?>
        </div>
      </div>

      <?php $needsTech = empty($iv['technician_id']) && !$isTerminal && in_array($currentStatus, ['nouveau', 'a_assigner', 'confirmé'], true); ?>
      <?php if ($needsTech): $sugg = assign_suggestions($iv, 3); ?>
      <!-- SUGGESTIONS D'ASSIGNATION -->
      <div class="d-card" id="assigner">
        <div class="d-card-head"><div class="d-card-title">Technicien suggéré</div></div>
        <div class="d-card-body" style="display:flex;flex-direction:column;gap:.7rem;">
          <?php if (($iv['tech_response'] ?? '') === 'refusee' && !empty($iv['tech_refusal_reason'])): ?>
            <div style="background:#fef2f2;border:1px solid #fecaca;color:#991b1b;border-radius:8px;padding:.5rem .7rem;font-size:.82rem;">Refusée précédemment : <?= e((string)$iv['tech_refusal_reason']) ?></div>
          <?php endif; ?>
          <?php if (!$sugg): ?><div style="color:var(--d-t3);font-size:.85rem;">Aucun technicien actif.</div><?php endif; ?>
          <?php foreach ($sugg as $i => $sg): ?>
          <form method="post" class="assign-sugg <?= $i === 0 ? 'is-best' : '' ?>">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="assign">
            <input type="hidden" name="technician_id" value="<?= (int)$sg['tech']['id'] ?>">
            <input type="hidden" name="scheduled_date" value="<?= e($sg['date']) ?>">
            <input type="hidden" name="scheduled_time" value="<?= e((string)$sg['slot']) ?>">
            <div style="display:flex;justify-content:space-between;gap:.5rem;align-items:baseline;">
              <b><?= e($sg['tech']['name']) ?></b>
              <span style="font-size:.72rem;color:var(--d-t3);"><?= $i === 0 ? 'Meilleur choix' : 'Option '.($i + 1) ?></span>
            </div>
            <div style="font-size:.78rem;color:var(--d-t2);margin:.2rem 0 .45rem;"><?= e($sg['reason']) ?></div>
            <button type="submit" class="d-btn d-btn--sm <?= $i === 0 ? 'd-btn--primary' : '' ?>" style="width:100%;justify-content:center;">
              Assigner<?= $sg['slot'] ? ' — '.e(date('d/m', strtotime($sg['date']))).' à '.e($sg['slot']) : '' ?>
            </button>
          </form>
          <?php endforeach; ?>
          <div style="font-size:.74rem;color:var(--d-t3);">Classement : métier, distance, charge du jour et créneau libre. Le technicien devra accepter.</div>
        </div>
      </div>
      <style>.assign-sugg { border: 1px solid var(--d-border); border-radius: 10px; padding: .6rem .7rem; } .assign-sugg.is-best { border-color: var(--d-orange); background: var(--d-orange-lt, #fff7ed); }</style>
      <?php endif; ?>

      <!-- PLANNING CARD -->
      <div class="d-card">
        <div class="d-card-head"><div class="d-card-title">Planification</div></div>
        <div class="d-card-body">
          <div class="d-info-row">
            <span class="d-info-label">Date</span>
            <span class="d-info-value">
              <?= !empty($iv['scheduled_date']) ? e(date('d/m/Y', strtotime($iv['scheduled_date']))) : '<span style="color:var(--d-t3)">—</span>' ?>
            </span>
          </div>
          <div class="d-info-row">
            <span class="d-info-label">Heure</span>
            <span class="d-info-value">
              <?= !empty($iv['scheduled_time']) ? e(substr($iv['scheduled_time'],0,5)) : '<span style="color:var(--d-t3)">—</span>' ?>
            </span>
          </div>
          <div class="d-info-row">
            <span class="d-info-label">Durée</span>
            <span class="d-info-value"><?= e(fmt_dur((int)($iv['duration_estimate'] ?? 60))) ?></span>
          </div>
          <div class="d-info-row">
            <span class="d-info-label">Priorité</span>
            <span class="d-info-value" style="text-transform:capitalize;"><?= e($iv['priority'] ?? '—') ?></span>
          </div>
          <div class="d-info-row">
            <span class="d-info-label">Technicien</span>
            <span class="d-info-value">
              <?php if ($assignedTech): ?>
                <div><?= e($assignedTech['name']) ?></div>
                <?php $tr = (string)($iv['tech_response'] ?? ''); if ($tr === 'en_attente'): ?>
                  <div style="font-size:.74rem;color:#b45309;font-weight:600;">En attente d'acceptation</div>
                <?php elseif ($tr === 'acceptee'): ?>
                  <div style="font-size:.74rem;color:var(--d-success);font-weight:600;">Acceptée<?= !empty($iv['tech_response_at']) ? ' le '.e(date('d/m à H:i', strtotime((string)$iv['tech_response_at']))) : '' ?></div>
                <?php endif; ?>
                <?php if (!empty($assignedTech['phone'])): ?>
                  <a href="tel:<?= e(preg_replace('/\s+/','',$assignedTech['phone'])) ?>"
                     style="font-size:.77rem;color:var(--d-t2);text-decoration:none;"><?= e($assignedTech['phone']) ?></a>
                <?php endif; ?>
              <?php else: ?>
                <span style="color:var(--d-t3);">Non assigné</span>
              <?php endif; ?>
            </span>
          </div>
          <div class="d-info-row">
            <span class="d-info-label">Dispatcher</span>
            <span class="d-info-value" style="color:var(--d-t2);"><?= e($iv['disp_name'] ?? '—') ?></span>
          </div>
          <div class="d-info-row">
            <span class="d-info-label">Créée le</span>
            <span class="d-info-value" style="color:var(--d-t2);font-size:.8rem;">
              <?= !empty($iv['created_at']) ? e(date('d/m/Y', strtotime($iv['created_at']))) : '—' ?>
            </span>
          </div>
        </div>
      </div>

      <!-- FINANCIER CARD -->
      <div class="d-card">
        <div class="d-card-head">
          <div class="d-card-title">Financier</div>
          <?php if (!empty($iv['quote_accepted'])): ?>
            <span style="font-size:.72rem;color:#22c55e;font-weight:700;">Devis accepté</span>
          <?php endif; ?>
        </div>
        <div class="d-card-body">
          <div class="d-info-row">
            <span class="d-info-label">Montant HT</span>
            <span class="d-info-value" style="color:var(--d-t1);">
              <?= $amtHt > 0 ? e(fmt_money($amtHt)) : '<span style="color:var(--d-t3)">—</span>' ?>
            </span>
          </div>
          <div class="d-info-row">
            <span class="d-info-label">Montant TTC</span>
            <span class="d-info-value" style="color:#F07B1D;font-weight:700;">
              <?= $amtTtc > 0 ? e(fmt_money($amtTtc)) : '<span style="color:var(--d-t3)">—</span>' ?>
            </span>
          </div>
          <?php if ((float)($iv['deposit'] ?? 0) > 0): ?>
          <div class="d-info-row">
            <span class="d-info-label">Acompte</span>
            <span class="d-info-value"><?= e(fmt_money((float)$iv['deposit'])) ?></span>
          </div>
          <?php endif; ?>
          <?php if ((float)($iv['remaining'] ?? 0) > 0): ?>
          <div class="d-info-row">
            <span class="d-info-label">Restant dû</span>
            <span class="d-info-value" style="color:#f59e0b;"><?= e(fmt_money((float)$iv['remaining'])) ?></span>
          </div>
          <?php endif; ?>
          <?php if (!empty($iv['payment_method'])): ?>
          <div class="d-info-row">
            <span class="d-info-label">Règlement</span>
            <span class="d-info-value" style="text-transform:capitalize;"><?= e($iv['payment_method']) ?></span>
          </div>
          <?php endif; ?>
          <div class="d-info-row">
            <span class="d-info-label">TVA</span>
            <span class="d-info-value"><?= e(rtrim(rtrim(number_format(iv_vat_rate($iv, $ivClient), 2, ',', ''), '0'), ',')) ?> %<?= ($iv['vat_rate'] ?? null) === null ? ' (automatique)' : '' ?><?= !empty($ivClient['client_type']) ? ' · '.e($ivClient['client_type'] === 'particulier' ? 'particulier' : 'professionnel') : '' ?></span>
          </div>
          <?php $ps = (string)($iv['payment_status'] ?? ''); ?>
          <div class="d-info-row" style="align-items:center;">
            <span class="d-info-label">Paiement</span>
            <span class="d-info-value"><?= payment_badge($ps) ?></span>
          </div>
          <form method="post" style="display:flex;gap:.5rem;margin-top:.6rem;">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="set_payment">
            <?php if ($ps !== 'payé'): ?><button type="submit" name="payment_status" value="payé" class="d-btn d-btn--success d-btn--sm">Marquer payé</button><?php endif; ?>
            <?php if ($ps !== 'non_payé'): ?><button type="submit" name="payment_status" value="non_payé" class="d-btn d-btn--sm">Marquer non payé</button><?php endif; ?>
          </form>
        </div>
      </div>

      <!-- ACTIONS CARD -->
      <div class="d-card">
        <div class="d-card-head"><div class="d-card-title">Actions</div></div>
        <div class="d-card-body" style="display:flex;flex-direction:column;gap:.6rem;">

          <!-- Modifier -->
          <button type="button" id="btn-edit-2" class="d-btn d-btn--primary d-btn--sm" style="width:100%;justify-content:center;">
            Modifier la fiche
          </button>

          <!-- PDF -->
          <a href="<?= e(url_for('dispatcher/rapport_pdf.php')).'?id='.$id ?>" target="_blank"
             class="d-btn d-btn--secondary d-btn--sm" style="width:100%;justify-content:center;">
            Télécharger PDF
          </a>

          <!-- SMS Client -->
          <?php if (!empty($iv['client_phone'])): ?>
          <button type="button" class="d-btn d-btn--ghost d-btn--sm" style="width:100%;justify-content:center;"
                  onclick="document.getElementById('sms-panel-client').style.display=document.getElementById('sms-panel-client').style.display==='none'?'block':'none';">
            SMS Client
          </button>
          <div id="sms-panel-client" style="display:none;background:var(--d-card-2);border:1px solid var(--d-border);border-radius:8px;padding:.75rem;">
            <form method="post">
              <input type="hidden" name="csrf_token"  value="<?= e(csrf_token()) ?>">
              <input type="hidden" name="action"      value="send_sms">
              <input type="hidden" name="sms_target"  value="client">
              <div class="d-field" style="margin-bottom:.5rem;">
                <label>Message (max 160 car.)</label>
                <textarea name="sms_message" style="min-height:70px;" maxlength="160"
                          placeholder="Votre message SMS…"><?= e(company_name().' : bonjour, concernant votre intervention'.(!empty($iv['scheduled_date']) ? ' du '.date('d/m', strtotime($iv['scheduled_date'])) : '').'. Contact : '.global_setting('company_phone', '')) ?></textarea>
              </div>
              <button type="submit" class="d-btn d-btn--primary d-btn--sm" style="width:100%;justify-content:center;">Envoyer SMS</button>
            </form>
          </div>
          <?php endif; ?>

          <!-- SMS Tech -->
          <?php if (!empty($iv['tech_phone'])): ?>
          <button type="button" class="d-btn d-btn--ghost d-btn--sm" style="width:100%;justify-content:center;"
                  onclick="document.getElementById('sms-panel-tech').style.display=document.getElementById('sms-panel-tech').style.display==='none'?'block':'none';">
            SMS Technicien
          </button>
          <div id="sms-panel-tech" style="display:none;background:var(--d-card-2);border:1px solid var(--d-border);border-radius:8px;padding:.75rem;">
            <form method="post">
              <input type="hidden" name="csrf_token"  value="<?= e(csrf_token()) ?>">
              <input type="hidden" name="action"      value="send_sms">
              <input type="hidden" name="sms_target"  value="tech">
              <div class="d-field" style="margin-bottom:.5rem;">
                <label>Message technicien (max 160 car.)</label>
                <textarea name="sms_message" style="min-height:70px;" maxlength="160"
                          placeholder="Message pour le technicien…"><?= e(company_name().' — INT '.(string)($iv['ref']??'#'.$id).'. Client: '.trim(($iv['lastname']??'').' '.($iv['firstname']??'')).'. Tél: '.($iv['client_phone']??'').(($iv['address']??'')!==''?'. '.$iv['address'].' '.($iv['client_city']??''):'')) ?></textarea>
              </div>
              <button type="submit" class="d-btn d-btn--primary d-btn--sm" style="width:100%;justify-content:center;">Envoyer SMS</button>
            </form>
          </div>
          <?php endif; ?>

          <!-- Supprimer -->
          <div style="margin-top:.25rem;border-top:1px solid var(--d-border);padding-top:.75rem;">
            <form method="post" onsubmit="return confirm('Confirmer la suppression de cette intervention ? Cette action est irréversible.');">
              <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
              <input type="hidden" name="action"     value="delete">
              <button type="submit" class="d-btn d-btn--danger d-btn--sm" style="width:100%;justify-content:center;">
                Supprimer l'intervention
              </button>
            </form>
          </div>

          <?php if (!empty($iv['notes_admin'])): ?>
          <div style="margin-top:.5rem;background:rgba(240,123,29,.06);border:1px solid rgba(240,123,29,.15);border-radius:8px;padding:.65rem .75rem;">
            <div style="font-size:.7rem;font-weight:700;color:#F07B1D;text-transform:uppercase;letter-spacing:.06em;margin-bottom:.3rem;">Notes internes</div>
            <div style="font-size:.8rem;color:var(--d-t1);line-height:1.5;"><?= nl2br(e($iv['notes_admin'])) ?></div>
          </div>
          <?php endif; ?>

        </div>
      </div>

    </div>
    <!-- /RIGHT COLUMN -->

  </div>

</div><!-- /.d-content -->

<script>
(function(){
  var viewMode  = document.getElementById('view-mode');
  var editMode  = document.getElementById('edit-mode');
  var btnToggle = document.getElementById('btn-toggle-edit');
  var btnEdit2  = document.getElementById('btn-edit-2');
  var btnCancel = document.getElementById('btn-cancel-edit');

  function showEdit(){
    if(viewMode) viewMode.style.display = 'none';
    if(editMode) editMode.style.display = 'block';
    if(btnToggle) btnToggle.textContent = '← Vue fiche';
    window.scrollTo({top:0,behavior:'smooth'});
  }
  function showView(){
    if(editMode) editMode.style.display = 'none';
    if(viewMode) viewMode.style.display = 'block';
    if(btnToggle) btnToggle.textContent = 'Modifier';
    window.scrollTo({top:0,behavior:'smooth'});
  }

  if(btnToggle) btnToggle.addEventListener('click', function(){
    if(editMode && editMode.style.display === 'block') showView();
    else showEdit();
  });
  if(btnEdit2)  btnEdit2.addEventListener('click',  showEdit);
  if(btnCancel) btnCancel.addEventListener('click', showView);
})();
</script>

<?php require __DIR__.'/partials/footer.php'; ?>
