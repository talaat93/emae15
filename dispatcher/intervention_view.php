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
            'scheduled_date'    => trim((string)($_POST['scheduled_date']    ?? '')),
            'scheduled_time'    => trim((string)($_POST['scheduled_time']    ?? '')),
            'duration_estimate' => (int)($_POST['duration_estimate']         ?? 60),
            'urgency'           => !empty($_POST['urgency']) ? 1 : 0,
            'priority'          => trim((string)($_POST['priority']          ?? 'normale')),
            'category'          => trim((string)($_POST['category']          ?? '')),
            'type_label'        => trim((string)($_POST['type_label']        ?? '')),
            'installation_type' => trim((string)($_POST['installation_type'] ?? '')),
            'fault_reported'    => trim((string)($_POST['fault_reported']    ?? '')),
            'description'       => trim((string)($_POST['description']       ?? '')),
            'materials_needed'  => trim((string)($_POST['materials_needed']  ?? '')),
            'notes_admin'       => trim((string)($_POST['notes_admin']       ?? '')),
            'quote_accepted'    => !empty($_POST['quote_accepted']) ? 1 : 0,
            'amount_ht'         => trim((string)($_POST['amount_ht']         ?? '')) !== '' ? (float)$_POST['amount_ht'] : null,
            'amount_ttc'        => trim((string)($_POST['amount_ttc']        ?? '')) !== '' ? (float)$_POST['amount_ttc'] : null,
            'deposit'           => trim((string)($_POST['deposit']           ?? '')) !== '' ? (float)$_POST['deposit'] : null,
            'remaining'         => trim((string)($_POST['remaining']         ?? '')) !== '' ? (float)$_POST['remaining'] : null,
            'payment_method'    => trim((string)($_POST['payment_method']    ?? '')),
            'status'            => $newStatus,
        ];
        update_intervention($id, $data);

        if ($oldStatus !== $newStatus) {
            log_intervention_history($id, $oldStatus, $newStatus, 'dispatcher', (int)$disp['id'], (string)$disp['name'],
                trim((string)($_POST['note'] ?? 'Mise à jour fiche')));
        }
        flash('success', 'Intervention mise à jour.');
        redirect_to('dispatcher/intervention_view.php?id='.$id);
    }

    if ($action === 'quick_status') {
        $validStatuses = ['nouveau','confirmé','assigné','en_route','sur_place','terminé','devis_envoyé','facturé','payé','annulé'];
        $newStatus = trim((string)($_POST['new_status'] ?? ''));
        if (in_array($newStatus, $validStatuses, true)) {
            $oldStatus = $iv['status'];
            update_intervention($id, ['status' => $newStatus]);
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
            flash($ok ? 'success' : 'error', $ok ? 'SMS envoyé.' : 'Échec d\'envoi du SMS.');
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
$statusCfg = intervention_status_config();
$catCfg    = intervention_category_config();
$techs     = all_technicians();
$pageTitle = 'INT #'.$id;

/* Status transitions */
$nextStatuses = [
    'nouveau'      => ['confirmé','annulé'],
    'confirmé'     => ['assigné','annulé'],
    'assigné'      => ['en_route','annulé'],
    'en_route'     => ['sur_place'],
    'sur_place'    => ['terminé'],
    'terminé'      => ['facturé'],
    'facturé'      => ['payé'],
    'devis_envoyé' => ['confirmé','annulé'],
    'payé'         => [],
    'annulé'       => [],
];
$currentStatus = (string)($iv['status'] ?? 'nouveau');
$nexts = $nextStatuses[$currentStatus] ?? [];

/* Terminal statuses */
$terminalStatuses = ['terminé','facturé','payé','annulé'];
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
      <div class="d-topbar-title">📋 <?= e($iv['ref'] ?? 'INT #'.$id) ?></div>
      <div class="d-topbar-sub">
        <?= e(trim(($iv['lastname']??'').' '.($iv['firstname']??''))) ?>
        <?php if (!empty($iv['client_city'])): ?> · <?= e($iv['client_city']) ?><?php endif; ?>
      </div>
    </div>
  </div>
  <div class="d-topbar-actions">
    <a href="<?= e(url_for('dispatcher/interventions.php')) ?>" class="d-btn d-btn--ghost d-btn--sm">← Retour</a>
    <a href="<?= e(url_for('dispatcher/rapport_pdf.php')).'?id='.$id ?>" target="_blank" class="d-btn d-btn--secondary d-btn--sm">📄 PDF</a>
    <button type="button" id="btn-toggle-edit" class="d-btn d-btn--primary d-btn--sm">✏️ Modifier</button>
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
            <div class="d-card-title">👤 Client</div>
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
                   style="color:#8fa0c4;text-decoration:none;"><?= e($iv['client_email']) ?></a>
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
              <span style="font-size:.84rem;color:#e8ecf5;line-height:1.5;"><?= nl2br(e($iv['access_info'])) ?></span>
            </div>
            <?php endif; ?>
          </div>
        </div>

        <!-- TECHNIQUE CARD -->
        <div class="d-card" style="margin-top:1.25rem;">
          <div class="d-card-head">
            <div class="d-card-title">🔧 Technique</div>
            <div><?= intervention_category_badge((string)($iv['category'] ?? '')) ?></div>
          </div>
          <div class="d-card-body">
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
              <span style="font-size:.84rem;color:#e8ecf5;line-height:1.5;background:rgba(239,68,68,.05);border:1px solid rgba(239,68,68,.15);border-radius:6px;padding:.6rem .75rem;"><?= nl2br(e($iv['fault_reported'])) ?></span>
            </div>
            <?php endif; ?>
            <?php if (!empty($iv['description'])): ?>
            <div class="d-info-row" style="flex-direction:column;gap:.35rem;">
              <span class="d-info-label">Description</span>
              <span style="font-size:.84rem;color:#e8ecf5;line-height:1.5;"><?= nl2br(e($iv['description'])) ?></span>
            </div>
            <?php endif; ?>
            <?php if (!empty($iv['materials_needed'])): ?>
            <div class="d-info-row" style="flex-direction:column;gap:.35rem;">
              <span class="d-info-label">Matériel nécessaire</span>
              <span style="font-size:.84rem;color:#8fa0c4;line-height:1.5;"><?= nl2br(e($iv['materials_needed'])) ?></span>
            </div>
            <?php endif; ?>
          </div>
        </div>

        <!-- RAPPORT TECHNICIEN (si terminal) -->
        <?php if ($isTerminal && (!empty($iv['tech_report']) || !empty($iv['tech_time_spent']))): ?>
        <div class="d-card" style="margin-top:1.25rem;">
          <div class="d-card-head">
            <div class="d-card-title">📝 Rapport technicien</div>
            <?php if (!empty($iv['tech_completed_at'])): ?>
              <span style="font-size:.76rem;color:#8fa0c4;">Terminé le <?= e(date('d/m/Y à H:i', strtotime($iv['tech_completed_at']))) ?></span>
            <?php endif; ?>
          </div>
          <div class="d-card-body">
            <?php if (!empty($iv['tech_report'])): ?>
            <div style="margin-bottom:1rem;">
              <div style="font-size:.75rem;font-weight:700;color:#8fa0c4;text-transform:uppercase;letter-spacing:.06em;margin-bottom:.4rem;">Rapport</div>
              <div style="font-size:.87rem;color:#e8ecf5;line-height:1.6;background:rgba(255,255,255,.03);border-radius:6px;padding:.75rem;"><?= nl2br(e($iv['tech_report'])) ?></div>
            </div>
            <?php endif; ?>
            <?php if (!empty($iv['tech_materials_used'])): ?>
            <div style="margin-bottom:1rem;">
              <div style="font-size:.75rem;font-weight:700;color:#8fa0c4;text-transform:uppercase;letter-spacing:.06em;margin-bottom:.4rem;">Matériaux utilisés</div>
              <div style="font-size:.87rem;color:#e8ecf5;line-height:1.6;"><?= nl2br(e($iv['tech_materials_used'])) ?></div>
            </div>
            <?php endif; ?>
            <?php if (!empty($iv['tech_time_spent'])): ?>
            <div class="d-info-row">
              <span class="d-info-label">Temps passé</span>
              <span class="d-info-value"><?= e(fmt_dur((int)$iv['tech_time_spent'])) ?></span>
            </div>
            <?php endif; ?>
            <?php if (!empty($iv['tech_client_name'])): ?>
            <div class="d-info-row">
              <span class="d-info-label">Signé par</span>
              <span class="d-info-value"><?= e($iv['tech_client_name']) ?></span>
            </div>
            <?php endif; ?>
            <?php if (!empty($iv['tech_signature'])): ?>
            <div style="margin-top:.75rem;">
              <div style="font-size:.75rem;font-weight:700;color:#8fa0c4;text-transform:uppercase;letter-spacing:.06em;margin-bottom:.4rem;">Signature client</div>
              <img src="<?= e($iv['tech_signature']) ?>" alt="Signature" style="max-width:200px;background:#fff;border-radius:4px;border:1px solid rgba(255,255,255,.1);">
            </div>
            <?php endif; ?>
            <?php
            $photos = [];
            if (!empty($iv['tech_photos'])) {
                $p = json_decode((string)$iv['tech_photos'], true);
                if (is_array($p)) $photos = $p;
            }
            if (!empty($photos)): ?>
            <div style="margin-top:1rem;">
              <div style="font-size:.75rem;font-weight:700;color:#8fa0c4;text-transform:uppercase;letter-spacing:.06em;margin-bottom:.6rem;">Photos (<?= count($photos) ?>)</div>
              <div style="display:flex;gap:.5rem;flex-wrap:wrap;">
                <?php foreach ($photos as $ph): ?>
                  <a href="<?= e(asset_url($ph)) ?>" target="_blank">
                    <img src="<?= e(asset_url($ph)) ?>" alt="Photo intervention"
                         style="width:80px;height:80px;object-fit:cover;border-radius:6px;border:1px solid rgba(255,255,255,.1);">
                  </a>
                <?php endforeach; ?>
              </div>
            </div>
            <?php endif; ?>
          </div>
        </div>
        <?php endif; ?>

        <!-- HISTORIQUE -->
        <div class="d-card" style="margin-top:1.25rem;">
          <div class="d-card-head">
            <div class="d-card-title">🕒 Historique</div>
          </div>
          <div class="d-card-body">
            <?php if (empty($history)): ?>
              <div class="d-empty" style="padding:1.5rem 0;">
                <div class="d-empty-icon" style="font-size:1.5rem;">📄</div>
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
                      <span style="color:#4a5f8a;font-size:.8rem;">→</span>
                    <?php endif; ?>
                    <?= intervention_status_badge((string)$h['status_to']) ?>
                  </div>
                  <div style="font-size:.78rem;color:#8fa0c4;margin-top:.3rem;">
                    <?= e($h['actor_name'] ?? 'Système') ?>
                    <?php if (!empty($h['created_at'])): ?>
                      · <?= e(date('d/m/Y à H:i', strtotime($h['created_at']))) ?>
                    <?php endif; ?>
                  </div>
                  <?php if (!empty($h['note'])): ?>
                    <div style="font-size:.8rem;color:#8fa0c4;font-style:italic;margin-top:.2rem;"><?= e($h['note']) ?></div>
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
              <div class="d-card-title">👤 Client (non modifiable ici)</div>
            </div>
            <div class="d-card-body" style="font-size:.87rem;color:#8fa0c4;">
              <?= e(trim(($iv['lastname']??'').' '.($iv['firstname']??''))) ?> · <?= e($iv['client_phone'] ?? '') ?>
            </div>
          </div>

          <!-- Section Planification -->
          <div class="d-card" style="margin-bottom:1.25rem;">
            <div class="d-card-head"><div class="d-card-title">📅 Planification</div></div>
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
                  <label style="display:flex;align-items:center;gap:.6rem;cursor:pointer;text-transform:none;letter-spacing:0;font-size:.9rem;color:#e8ecf5;margin-bottom:0;">
                    <input type="checkbox" name="urgency" value="1" <?=!empty($iv['urgency'])?'checked':''?> style="width:auto;accent-color:#ef4444;">
                    🚨 Urgence
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
            <div class="d-card-head"><div class="d-card-title">🔧 Technique</div></div>
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
                <textarea name="materials_needed" style="min-height:70px;"><?=e($iv['materials_needed']??'')?></textarea>
              </div>
            </div>
          </div>

          <!-- Section Financier -->
          <div class="d-card" style="margin-bottom:1.25rem;">
            <div class="d-card-head"><div class="d-card-title">💶 Financier</div></div>
            <div class="d-card-body">
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
                <label style="display:flex;align-items:center;gap:.6rem;cursor:pointer;text-transform:none;letter-spacing:0;font-size:.9rem;color:#e8ecf5;margin-bottom:0;">
                  <input type="checkbox" name="quote_accepted" value="1" <?=!empty($iv['quote_accepted'])?'checked':''?> style="width:auto;accent-color:#22c55e;">
                  ✅ Devis accepté
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
            <button type="submit" class="d-btn d-btn--primary">💾 Enregistrer</button>
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
        <div class="d-card-head"><div class="d-card-title">🚦 Statut</div></div>
        <div class="d-card-body">
          <div style="margin-bottom:1rem;">
            <?= intervention_status_badge($currentStatus) ?>
            <?php if (!empty($iv['urgency'])): ?>
              <span class="d-badge-urgency" style="margin-left:.4rem;">🚨 Urgence</span>
            <?php endif; ?>
          </div>

          <?php if (!empty($nexts)): ?>
          <div style="font-size:.75rem;font-weight:700;color:#8fa0c4;text-transform:uppercase;letter-spacing:.06em;margin-bottom:.5rem;">
            Faire passer à :
          </div>
          <div style="display:flex;flex-direction:column;gap:.5rem;">
            <?php foreach ($nexts as $ns): ?>
              <?php $nsCfg = $statusCfg[$ns] ?? ['label'=>$ns,'color'=>'#8fa0c4','bg'=>'rgba(143,160,196,.15)']; ?>
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
            <div style="font-size:.8rem;color:#4a5f8a;font-style:italic;">Aucune transition disponible</div>
          <?php endif; ?>
        </div>
      </div>

      <!-- PLANNING CARD -->
      <div class="d-card">
        <div class="d-card-head"><div class="d-card-title">📅 Planification</div></div>
        <div class="d-card-body">
          <div class="d-info-row">
            <span class="d-info-label">Date</span>
            <span class="d-info-value">
              <?= !empty($iv['scheduled_date']) ? e(date('d/m/Y', strtotime($iv['scheduled_date']))) : '<span style="color:#4a5f8a">—</span>' ?>
            </span>
          </div>
          <div class="d-info-row">
            <span class="d-info-label">Heure</span>
            <span class="d-info-value">
              <?= !empty($iv['scheduled_time']) ? e(substr($iv['scheduled_time'],0,5)) : '<span style="color:#4a5f8a">—</span>' ?>
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
                <?php if (!empty($assignedTech['phone'])): ?>
                  <a href="tel:<?= e(preg_replace('/\s+/','',$assignedTech['phone'])) ?>"
                     style="font-size:.77rem;color:#8fa0c4;text-decoration:none;"><?= e($assignedTech['phone']) ?></a>
                <?php endif; ?>
              <?php else: ?>
                <span style="color:#4a5f8a;">Non assigné</span>
              <?php endif; ?>
            </span>
          </div>
          <div class="d-info-row">
            <span class="d-info-label">Dispatcher</span>
            <span class="d-info-value" style="color:#8fa0c4;"><?= e($iv['disp_name'] ?? '—') ?></span>
          </div>
          <div class="d-info-row">
            <span class="d-info-label">Créée le</span>
            <span class="d-info-value" style="color:#8fa0c4;font-size:.8rem;">
              <?= !empty($iv['created_at']) ? e(date('d/m/Y', strtotime($iv['created_at']))) : '—' ?>
            </span>
          </div>
        </div>
      </div>

      <!-- FINANCIER CARD -->
      <div class="d-card">
        <div class="d-card-head">
          <div class="d-card-title">💶 Financier</div>
          <?php if (!empty($iv['quote_accepted'])): ?>
            <span style="font-size:.72rem;color:#22c55e;font-weight:700;">✅ Devis accepté</span>
          <?php endif; ?>
        </div>
        <div class="d-card-body">
          <div class="d-info-row">
            <span class="d-info-label">Montant HT</span>
            <span class="d-info-value" style="color:#e8ecf5;">
              <?= $amtHt > 0 ? e(fmt_money($amtHt)) : '<span style="color:#4a5f8a">—</span>' ?>
            </span>
          </div>
          <div class="d-info-row">
            <span class="d-info-label">Montant TTC</span>
            <span class="d-info-value" style="color:#F07B1D;font-weight:700;">
              <?= $amtTtc > 0 ? e(fmt_money($amtTtc)) : '<span style="color:#4a5f8a">—</span>' ?>
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
            <span class="d-info-label">Paiement</span>
            <span class="d-info-value" style="text-transform:capitalize;"><?= e($iv['payment_method']) ?></span>
          </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- ACTIONS CARD -->
      <div class="d-card">
        <div class="d-card-head"><div class="d-card-title">⚡ Actions</div></div>
        <div class="d-card-body" style="display:flex;flex-direction:column;gap:.6rem;">

          <!-- Modifier -->
          <button type="button" id="btn-edit-2" class="d-btn d-btn--primary d-btn--sm" style="width:100%;justify-content:center;">
            ✏️ Modifier la fiche
          </button>

          <!-- PDF -->
          <a href="<?= e(url_for('dispatcher/rapport_pdf.php')).'?id='.$id ?>" target="_blank"
             class="d-btn d-btn--secondary d-btn--sm" style="width:100%;justify-content:center;">
            📄 Télécharger PDF
          </a>

          <!-- SMS Client -->
          <?php if (!empty($iv['client_phone'])): ?>
          <button type="button" class="d-btn d-btn--ghost d-btn--sm" style="width:100%;justify-content:center;"
                  onclick="document.getElementById('sms-panel-client').style.display=document.getElementById('sms-panel-client').style.display==='none'?'block':'none';">
            📱 SMS Client
          </button>
          <div id="sms-panel-client" style="display:none;background:rgba(255,255,255,.03);border:1px solid rgba(255,255,255,.08);border-radius:8px;padding:.75rem;">
            <form method="post">
              <input type="hidden" name="csrf_token"  value="<?= e(csrf_token()) ?>">
              <input type="hidden" name="action"      value="send_sms">
              <input type="hidden" name="sms_target"  value="client">
              <div class="d-field" style="margin-bottom:.5rem;">
                <label>Message (max 160 car.)</label>
                <textarea name="sms_message" style="min-height:70px;" maxlength="160"
                          placeholder="Votre message SMS…"><?= e(company_name().' — Votre intervention est '.($statusCfg[$currentStatus]['label']??$currentStatus).'. Pour info: '.company_phone()) ?></textarea>
              </div>
              <button type="submit" class="d-btn d-btn--primary d-btn--sm" style="width:100%;justify-content:center;">Envoyer SMS</button>
            </form>
          </div>
          <?php endif; ?>

          <!-- SMS Tech -->
          <?php if (!empty($iv['tech_phone'])): ?>
          <button type="button" class="d-btn d-btn--ghost d-btn--sm" style="width:100%;justify-content:center;"
                  onclick="document.getElementById('sms-panel-tech').style.display=document.getElementById('sms-panel-tech').style.display==='none'?'block':'none';">
            📱 SMS Technicien
          </button>
          <div id="sms-panel-tech" style="display:none;background:rgba(255,255,255,.03);border:1px solid rgba(255,255,255,.08);border-radius:8px;padding:.75rem;">
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
          <div style="margin-top:.25rem;border-top:1px solid rgba(255,255,255,.06);padding-top:.75rem;">
            <form method="post" onsubmit="return confirm('Confirmer la suppression de cette intervention ? Cette action est irréversible.');">
              <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
              <input type="hidden" name="action"     value="delete">
              <button type="submit" class="d-btn d-btn--danger d-btn--sm" style="width:100%;justify-content:center;">
                🗑 Supprimer l'intervention
              </button>
            </form>
          </div>

          <?php if (!empty($iv['notes_admin'])): ?>
          <div style="margin-top:.5rem;background:rgba(240,123,29,.06);border:1px solid rgba(240,123,29,.15);border-radius:8px;padding:.65rem .75rem;">
            <div style="font-size:.7rem;font-weight:700;color:#F07B1D;text-transform:uppercase;letter-spacing:.06em;margin-bottom:.3rem;">Notes internes</div>
            <div style="font-size:.8rem;color:#e8ecf5;line-height:1.5;"><?= nl2br(e($iv['notes_admin'])) ?></div>
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
    if(btnToggle) btnToggle.textContent = '✏️ Modifier';
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
