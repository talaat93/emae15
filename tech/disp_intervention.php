<?php
declare(strict_types=1);
require_once __DIR__.'/../includes/bootstrap.php';
require_once __DIR__.'/_ui.php';
$tech   = require_tech_auth();
$techId = (int)$tech['id'];

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { header('Location: '.url_for('tech/dashboard.php')); exit; }

function load_iv(int $id, int $techId): ?array {
    try {
        return db_fetch(
            "SELECT i.*, c.id AS c_id, c.lastname, c.firstname, c.phone AS client_phone,
                    c.address AS client_address, c.city AS client_city, c.postal_code AS client_postal,
                    c.floor AS client_floor, c.digicode AS client_digicode, c.access_info AS client_access,
                    c.email AS client_email, d.name AS disp_name
             FROM interventions i
             LEFT JOIN clients c ON c.id = i.client_id
             LEFT JOIN dispatchers d ON d.id = i.dispatcher_id
             WHERE i.id = ? AND i.technician_id = ?",
            [$id, $techId]
        );
    } catch (Throwable $e) { return null; }
}

/** Photos enregistrées, toujours sous la forme [{type, path}]. */
function iv_photos(array $iv): array {
    $raw = json_decode((string)($iv['tech_photos'] ?? '[]'), true);
    $out = [];
    foreach (is_array($raw) ? $raw : [] as $ph) {
        if (is_array($ph) && !empty($ph['path'])) $out[] = ['type' => (string)($ph['type'] ?? ''), 'path' => (string)$ph['path']];
        elseif (is_string($ph) && $ph !== '') $out[] = ['type' => '', 'path' => $ph];
    }
    return $out;
}

$iv = load_iv($id, $techId);
if (!$iv) { header('Location: '.url_for('tech/dashboard.php')); exit; }

$self = url_for('tech/disp_intervention.php?id='.$id);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = trim((string)($_POST['action'] ?? ''));

    // Réponse à l'attribution : accepter, ou refuser avec un motif (la fiche repart au dispatcher).
    if ($action === 'accept' && ($iv['tech_response'] ?? '') === 'en_attente') {
        assign_accept($iv, $tech);
        flash('success', 'Intervention acceptée. Le dispatcher est informé.');
        header('Location: '.$self); exit;
    }
    if ($action === 'refuse' && ($iv['tech_response'] ?? '') === 'en_attente') {
        $reason = trim((string)($_POST['reason'] ?? ''));
        $detail = trim((string)($_POST['reason_detail'] ?? ''));
        if (!in_array($reason, assign_refusal_reasons(), true)) $reason = 'Autre';
        if ($reason === 'Autre' && $detail === '') {
            flash('error', 'Précisez le motif du refus.');
            header('Location: '.$self); exit;
        }
        assign_refuse($iv, $tech, $reason.($detail !== '' ? ' — '.$detail : ''));
        flash('success', 'Refus transmis au dispatcher.');
        redirect_to('tech/dashboard.php');
    }

    if ($action === 'status') {
        if (($iv['tech_response'] ?? '') === 'en_attente') {
            flash('error', 'Acceptez d\'abord l\'intervention.');
            header('Location: '.$self); exit;
        }
        $ns = trim((string)($_POST['status'] ?? ''));
        if (in_array($ns, ['en_route', 'sur_place'], true) && $ns !== ($iv['status'] ?? '')) {
            $upd = ['status' => $ns];
            if ($ns === 'en_route'  && empty($iv['tech_started_at'])) $upd['tech_started_at'] = date('Y-m-d H:i:s');
            if ($ns === 'sur_place' && empty($iv['tech_arrived_at'])) $upd['tech_arrived_at'] = date('Y-m-d H:i:s');
            update_intervention($id, $upd);
            log_intervention_history($id, $iv['status'], $ns, 'technicien', $techId, (string)$tech['name']);
            if ($ns === 'en_route') notify_client_en_route($id);
            flash('success', $ns === 'en_route' ? 'Trajet démarré. Le dispatcher est informé.' : 'Arrivée enregistrée.');
        }
        if (($_POST['back'] ?? '') === 'list') redirect_to('tech/dashboard.php');
        header('Location: '.$self); exit;
    }

    $ajax = ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'fetch';
    $isOpen = wf_tech_editable((string)($iv['status'] ?? ''));

    // Retirer une photo déjà envoyée (tant que l'intervention n'est pas terminée).
    if ($action === 'delete_photo' && $isOpen) {
        $photos = iv_photos($iv);
        $idx = (int)($_POST['idx'] ?? -1);
        if (isset($photos[$idx])) {
            $file = realpath(__DIR__.'/../'.$photos[$idx]['path']);
            $base = realpath(__DIR__.'/../storage/uploads/interventions/'.$id);
            if ($file && $base && str_starts_with($file, $base)) @unlink($file);
            array_splice($photos, $idx, 1);
            update_intervention($id, ['tech_photos' => json_encode($photos)]);
        }
        header('Content-Type: application/json'); echo json_encode(['ok' => true]); exit;
    }

    if ($action === 'report' && $isOpen) {
        $photos = iv_photos($iv);
        if (!empty($_FILES['photos']['name'][0])) {
            $dir = __DIR__.'/../storage/uploads/interventions/'.$id.'/';
            if (!is_dir($dir)) mkdir($dir, 0775, true);
            $mimeMap = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
            $types   = (array)($_POST['photo_types'] ?? []);
            foreach ($_FILES['photos']['tmp_name'] as $i => $tmp) {
                if (($_FILES['photos']['error'][$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) continue;
                $mt = mime_content_type($tmp) ?: '';
                if (!isset($mimeMap[$mt])) continue;
                $fn = 'tech_'.$id.'_'.date('YmdHis').'_'.bin2hex(random_bytes(3)).'.'.$mimeMap[$mt];
                if (move_uploaded_file($tmp, $dir.$fn)) {
                    $photos[] = ['type' => mb_substr(trim((string)($types[$i] ?? '')), 0, 120), 'path' => 'storage/uploads/interventions/'.$id.'/'.$fn];
                }
            }
        }

        $mats = json_decode(trim((string)($_POST['tech_materials_used'] ?? '[]')), true);
        $mats = is_array($mats) ? array_values(array_filter($mats, static fn($m) => is_array($m) && trim((string)($m['name'] ?? '')) !== '')) : [];

        $yn  = static fn(string $k) => ($_POST[$k] ?? '') === '' ? null : (int)$_POST[$k];
        $num = static fn(string $k) => ($v = trim(str_replace([',', ' '], ['.', ''], (string)($_POST[$k] ?? '')))) === '' ? null : (float)$v;
        $pay = in_array($_POST['payment_status'] ?? '', ['payé', 'non_payé'], true) ? (string)$_POST['payment_status'] : null;
        $upd = [
            'tech_fault_label'       => trim((string)($_POST['tech_fault_label'] ?? '')),
            'tech_report'            => trim((string)($_POST['tech_report'] ?? '')),
            'tech_notes_extra'       => trim((string)($_POST['tech_notes_extra'] ?? '')),
            'tech_device_number'     => trim((string)($_POST['tech_device_number'] ?? '')),
            'tech_ticket_time'       => trim((string)($_POST['tech_ticket_time'] ?? '')) ?: null,
            'tech_realizable'        => $yn('tech_realizable'),
            'tech_bad_use'           => $yn('tech_bad_use'),
            'tech_elevator_restored' => $yn('tech_elevator_restored'),
            'tech_photos'            => json_encode($photos),
            'tech_materials_used'    => json_encode($mats, JSON_UNESCAPED_UNICODE),
            'tech_client_name'       => trim((string)($_POST['tech_client_name'] ?? '')) ?: null,
            'payment_status'         => $pay,
        ];
        if ($pay === 'payé') {
            $upd['payment_method'] = trim((string)($_POST['payment_method'] ?? '')) ?: null;
            $upd['amount_ttc']     = $num('amount_ttc') ?? ($iv['amount_ttc'] !== null ? (float)$iv['amount_ttc'] : null);
            if (empty($iv['paid_at'])) $upd['paid_at'] = date('Y-m-d H:i:s');
        } elseif ($pay === 'non_payé') {
            $upd['paid_at'] = null;
        }
        // Signatures : images PNG dessinées au doigt, conservées telles quelles.
        foreach (['client_signature', 'tech_signature'] as $sk) {
            $sig = (string)($_POST[$sk] ?? '');
            if ($sig === 'clear') {
                $upd[$sk] = null;
            } elseif (str_starts_with($sig, 'data:image/png;base64,') && strlen($sig) < 600000
                      && base64_decode(substr($sig, 22), true) !== false) {
                $upd[$sk] = $sig;
            }
        }

        $message = 'Rapport enregistré.';
        $done = false;
        if (!empty($_POST['mark_complete'])) {
            // Tout ce qui est marqué d'une * doit être rempli pour clôturer.
            $after = array_merge($iv, $upd);
            $missing = [];
            if (trim((string)$after['tech_report']) === '')      $missing[] = 'le constat et les travaux réalisés';
            $haveTypes = array_column(iv_photos($after), 'type');
            $lackPhotos = array_diff(intervention_photos_required($iv), $haveTypes);
            if ($lackPhotos)                                      $missing[] = 'les photos demandées ('.implode(', ', $lackPhotos).')';
            if (empty($after['payment_status']))                  $missing[] = 'le paiement (payé ou non)';
            if (trim((string)$after['tech_client_name']) === '')  $missing[] = 'le nom du client signataire';
            if (empty($after['client_signature']))                $missing[] = 'la signature du client';
            if (empty($after['tech_signature']))                  $missing[] = 'votre signature';
            if ($missing) {
                update_intervention($id, $upd);
                flash('error', 'Rapport enregistré, mais pour terminer il manque : '.implode(', ', $missing).'.');
                if ($ajax) { header('Content-Type: application/json'); echo json_encode(['redirect' => $self.'#rapport']); exit; }
                header('Location: '.$self.'#rapport'); exit;
            }
            $now = date('Y-m-d H:i:s');
            $upd['status'] = 'rapport_rendu';
            if (empty($iv['tech_completed_at'])) $upd['tech_completed_at'] = $now;
            if (empty($iv['tech_arrived_at']))   $upd['tech_arrived_at']   = $now;
            if (empty($iv['tech_close_time']))   $upd['tech_close_time']   = date('H:i');
            $done = true;
            $message = 'Intervention terminée. Le rapport est disponible.';
        }
        update_intervention($id, $upd);
        if ($done) {
            log_intervention_history($id, $iv['status'], 'rapport_rendu', 'technicien', $techId, (string)$tech['name'], 'Rapport rendu depuis l\'application technicien');
        }
        flash('success', $message);
        $to = $self.($done ? '' : '#rapport');
        if ($ajax) { header('Content-Type: application/json'); echo json_encode(['redirect' => $to]); exit; }
        header('Location: '.$to); exit;
    }

    if ($action === 'update_client') {
        $cid = (int)($iv['c_id'] ?? 0);
        if ($cid > 0) {
            try {
                db_execute(
                    "UPDATE clients SET phone = ?, address = ?, postal_code = ?, city = ?, floor = ?, digicode = ? WHERE id = ?",
                    [
                        trim((string)($_POST['client_phone'] ?? '')),
                        trim((string)($_POST['client_address'] ?? '')),
                        trim((string)($_POST['client_postal'] ?? '')),
                        trim((string)($_POST['client_city'] ?? '')),
                        trim((string)($_POST['client_floor'] ?? '')),
                        trim((string)($_POST['client_digicode'] ?? '')),
                        $cid,
                    ]
                );
                flash('success', 'Coordonnées du client mises à jour.');
            } catch (Throwable $e) {}
        }
        header('Location: '.$self); exit;
    }

    if ($action === 'update_financial') {
        $num = static fn(string $k) => ($v = trim(str_replace(',', '.', (string)($_POST[$k] ?? '')))) === '' ? null : (float)$v;
        update_intervention($id, [
            'amount_ht'      => $num('amount_ht'),
            'amount_ttc'     => $num('amount_ttc'),
            'deposit'        => $num('deposit'),
            'payment_method' => trim((string)($_POST['payment_method'] ?? '')) ?: null,
        ]);
        flash('success', 'Paiement mis à jour.');
        header('Location: '.$self); exit;
    }

    header('Location: '.$self); exit;
}

// ─── Affichage ───────────────────────────────────────────────
$status   = (string)($iv['status'] ?? 'nouveau');
$isDone   = in_array($status, wf_field_done(), true);
$isCancel = $status === 'annulé';
$locked   = $isDone || $isCancel;
$photos   = iv_photos($iv);
$mats     = json_decode((string)($iv['tech_materials_used'] ?? '[]'), true);
$mats     = is_array($mats) ? $mats : [];
$clientName = trim(($iv['firstname'] ?? '').' '.($iv['lastname'] ?? '')) ?: 'Client';
$addr     = ta_address($iv['client_address'] ?? '', $iv['client_postal'] ?? '', $iv['client_city'] ?? '');
$catLabel = intervention_category_config()[$iv['category'] ?? '']['label'] ?? '';
$isLift   = ($iv['category'] ?? '') === 'ascenseur';
$hm       = static fn(?string $dt) => $dt ? date('H:i', strtotime($dt)) : '';

try {
    $history = db_fetch_all("SELECT * FROM intervention_history WHERE intervention_id = ? ORDER BY created_at DESC LIMIT 20", [$id]);
} catch (Throwable $e) { $history = []; }

$cur = match (true) {
    $isDone               => 3,
    $status === 'sur_place' => 2,
    $status === 'en_route'  => 1,
    default               => 0,
};
$steps = [
    ['Planifiée', !empty($iv['scheduled_time']) ? substr((string)$iv['scheduled_time'], 0, 5) : ''],
    ['En route',  $hm($iv['tech_started_at'] ?? null)],
    ['Sur place', $hm($iv['tech_arrived_at'] ?? null)],
    ['Terminée',  $hm($iv['tech_completed_at'] ?? null)],
];

$csrf = csrf_token();
ta_head(($iv['ref'] ?? 'Intervention').' — '.$clientName);
?>
<header class="ta-top">
  <a class="ta-icon-btn" href="<?= e(url_for('tech/dashboard.php')) ?>" aria-label="Retour"><?= ta_icon('back') ?></a>
  <div class="grow">
    <h1><?= e($clientName) ?></h1>
    <div class="sub"><?= e(trim(($iv['ref'] ?? '').' · '.($catLabel ?: 'Intervention'), ' ·')) ?></div>
  </div>
  <?= ta_pill($status) ?>
</header>

<?php if (!$isCancel): ?>
<div class="ta-steps" aria-label="Avancement">
  <?php foreach ($steps as $i => [$label, $when]): ?>
    <div class="ta-step <?= $i < $cur || ($i === 3 && $isDone) ? 'done' : ($i === $cur ? 'cur' : '') ?>">
      <i></i><?= e($label) ?><small><?= e($when ?: ' ') ?></small>
    </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<main class="ta-main <?= $locked ? '' : 'has-bar' ?>">
  <?php if ($m = flash('success')): ?><div class="ta-flash ok"><?= e($m) ?></div><?php endif; ?>
  <?php if ($m = flash('error')): ?><div class="ta-flash err"><?= e($m) ?></div><?php endif; ?>
  <?php if ($isCancel): ?><div class="ta-flash err">Cette intervention a été annulée par le dispatcher.</div><?php endif; ?>
  <?php $pending = ($iv['tech_response'] ?? '') === 'en_attente' && !$locked; ?>
  <?php if ($pending): ?>
  <section class="ta-card" id="reponse" style="border-color:#f59e0b;">
    <div class="ta-card-h" style="background:#fffbeb;"><h3>Nouvelle intervention : l'acceptez-vous ?</h3></div>
    <div class="ta-card-b">
      <div class="ta-text" style="margin-bottom:.8rem;">
        <?= !empty($iv['scheduled_date']) ? 'Prévue le '.e(date('d/m/Y', strtotime((string)$iv['scheduled_date']))).(!empty($iv['scheduled_time']) ? ' à '.e(substr((string)$iv['scheduled_time'], 0, 5)) : '') : 'Date à confirmer' ?>
        <?= !empty($iv['duration_estimate']) ? ' · environ '.(int)$iv['duration_estimate'].' min' : '' ?>
        <?= !empty($iv['urgency']) ? ' · <b style="color:#dc2626;">URGENT</b>' : '' ?>
      </div>
      <details id="refus">
        <summary class="ta-btn" style="list-style:none;justify-content:center;">Refuser…</summary>
        <form method="post" style="margin-top:.7rem;display:flex;flex-direction:column;gap:.5rem;">
          <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
          <input type="hidden" name="action" value="refuse">
          <label class="req" for="reason" style="font-weight:600;font-size:.9rem;">Motif du refus</label>
          <select id="reason" name="reason" required style="padding:.65rem;border:1px solid var(--line);border-radius:10px;font:inherit;">
            <?php foreach (assign_refusal_reasons() as $r): ?><option><?= e($r) ?></option><?php endforeach; ?>
          </select>
          <textarea name="reason_detail" rows="2" placeholder="Précision (obligatoire pour « Autre »)" style="padding:.65rem;border:1px solid var(--line);border-radius:10px;font:inherit;"></textarea>
          <button type="submit" class="ta-btn" style="color:#991b1b;border-color:#fecaca;background:#fef2f2;">Confirmer le refus</button>
        </form>
      </details>
    </div>
  </section>
  <?php endif; ?>

  <!-- Client & accès -->
  <section class="ta-card">
    <div class="ta-card-h">
      <h3>Client et accès</h3>
      <?php if (!$locked): ?><button type="button" onclick="openSheet('sheet-client')">Modifier</button><?php endif; ?>
    </div>
    <div class="ta-card-b">
      <div style="font-weight:600;"><?= e($clientName) ?></div>
      <?php if ($addr !== ''): ?><div class="ta-text" style="margin-top:.15rem;"><?= e($addr) ?></div><?php endif; ?>
      <?php
      $access = array_filter([
          !empty($iv['client_floor']) ? 'Étage '.$iv['client_floor'] : '',
          !empty($iv['client_digicode']) ? 'Code '.$iv['client_digicode'] : '',
          (string)($iv['client_access'] ?? ''),
      ]);
      ?>
      <?php if ($access): ?><div class="ta-muted" style="font-size:.88rem;margin-top:.25rem;"><?= e(implode(' · ', $access)) ?></div><?php endif; ?>
      <div style="display:flex;gap:.5rem;margin-top:.8rem;">
        <?php if (!empty($iv['client_phone'])): ?>
          <a class="ta-btn grow" href="<?= e(ta_tel($iv['client_phone'])) ?>"><?= ta_icon('phone') ?>Appeler</a>
        <?php endif; ?>
        <?php if ($addr !== ''): ?>
          <a class="ta-btn grow waze" href="<?= e(ta_waze_url($addr)) ?>" target="_blank" rel="noopener"><?= ta_icon('waze') ?>Waze</a>
          <a class="ta-btn grow" href="<?= e(ta_route_url($addr)) ?>" target="_blank" rel="noopener"><?= ta_icon('route') ?>Maps</a>
        <?php endif; ?>
      </div>
    </div>
  </section>

  <!-- Demande -->
  <section class="ta-card">
    <div class="ta-card-h"><h3>Demande</h3></div>
    <div class="ta-card-b">
      <?php if (!empty($iv['type_label']) || $catLabel): ?>
        <div style="font-weight:600;"><?= e($iv['type_label'] ?: $catLabel) ?></div>
      <?php endif; ?>
      <div class="ta-row"><span>Date</span><span><?= !empty($iv['scheduled_date']) ? e(ta_fr_date($iv['scheduled_date'])).(!empty($iv['scheduled_time']) ? ' à '.e(substr((string)$iv['scheduled_time'], 0, 5)) : '') : 'À confirmer' ?></span></div>
      <?php if (!empty($iv['duration_estimate'])): ?><div class="ta-row"><span>Durée prévue</span><span><?= e(ta_duration((int)$iv['duration_estimate'])) ?></span></div><?php endif; ?>
      <?php if (!empty($iv['urgency'])): ?><div class="ta-row"><span>Priorité</span><span><span class="ta-urgent">Urgent</span></span></div><?php endif; ?>
      <?php if (!empty($iv['installation_type'])): ?><div class="ta-row"><span>Installation</span><span><?= e($iv['installation_type']) ?></span></div><?php endif; ?>
      <?php if (!empty($iv['description'])): ?><div class="ta-text" style="margin-top:.6rem;"><?= e($iv['description']) ?></div><?php endif; ?>
      <?php if (!empty($iv['fault_reported'])): ?><div class="ta-text ta-muted" style="margin-top:.4rem;">Panne signalée : <?= e($iv['fault_reported']) ?></div><?php endif; ?>
      <?php if (($mn = materials_text($iv['materials_needed'] ?? '')) !== ''): ?><div class="ta-text ta-muted" style="margin-top:.4rem;">Matériel à prévoir :
<?= e($mn) ?></div><?php endif; ?>
      <?php if (!empty($iv['notes_admin'])): ?><div class="ta-note"><b>Consignes du dispatcher</b><?= e($iv['notes_admin']) ?></div><?php endif; ?>
    </div>
  </section>

  <!-- Rapport -->
  <?php if ($locked): ?>
  <section class="ta-card" id="rapport">
    <div class="ta-card-h">
      <h3>Rapport d'intervention</h3>
      <a href="<?= e(url_for('dispatcher/rapport_pdf.php?id='.$id)) ?>" target="_blank" rel="noopener">PDF</a>
    </div>
    <div class="ta-card-b">
      <?php
      $yesNo = static fn($v) => $v === null || $v === '' ? '—' : ((int)$v ? 'Oui' : 'Non');
      $rows = [
          'Intitulé de la panne' => $iv['tech_fault_label'] ?? '',
          'N° d\'appareil'       => $iv['tech_device_number'] ?? '',
          'Réalisable'           => $yesNo($iv['tech_realizable'] ?? null),
          'Mauvaise utilisation' => $yesNo($iv['tech_bad_use'] ?? null),
      ];
      if ($isLift) $rows['Ascenseur remis en service'] = $yesNo($iv['tech_elevator_restored'] ?? null);
      $rows['Arrivée'] = $hm($iv['tech_arrived_at'] ?? null);
      $rows['Fin'] = !empty($iv['tech_close_time']) ? substr((string)$iv['tech_close_time'], 0, 5) : $hm($iv['tech_completed_at'] ?? null);
      foreach ($rows as $k => $v): if ((string)$v === '') continue; ?>
        <div class="ta-row"><span><?= e($k) ?></span><span><?= e($v) ?></span></div>
      <?php endforeach; ?>
      <?php if (!empty($iv['tech_report'])): ?><div class="ta-label" style="margin-top:.7rem;">Travaux réalisés</div><div class="ta-text"><?= e($iv['tech_report']) ?></div><?php endif; ?>
      <?php if (!empty($iv['tech_notes_extra'])): ?><div class="ta-label" style="margin-top:.7rem;">Remarques</div><div class="ta-text"><?= e($iv['tech_notes_extra']) ?></div><?php endif; ?>
      <?php if ($mats): ?>
        <div class="ta-label" style="margin-top:.7rem;">Matériel utilisé</div>
        <?php foreach ($mats as $m): if (!is_array($m)) continue; ?>
          <div class="ta-row"><span><?= e($m['name'] ?? '') ?></span><span><?= e(trim(($m['qty'] ?? '').' '.($m['unit'] ?? ''))) ?></span></div>
        <?php endforeach; ?>
      <?php endif; ?>
      <?php if ($photos): ?>
        <div class="ta-label" style="margin-top:.7rem;">Photos</div>
        <div class="ta-photos">
          <?php foreach ($photos as $ph): ?>
            <a class="ta-photo" href="<?= e(asset_url($ph['path'])) ?>" target="_blank"><img src="<?= e(asset_url($ph['path'])) ?>" alt="" loading="lazy"><?php if ($ph['type']): ?><span><?= e($ph['type']) ?></span><?php endif; ?></a>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
      <?php if (!empty($iv['payment_status'])): ?>
        <div class="ta-label" style="margin-top:.7rem;">Paiement</div>
        <div class="ta-row"><span>Statut</span><span><?= $iv['payment_status'] === 'payé' ? 'Payé' : 'Non payé' ?></span></div>
        <?php if (!empty($iv['payment_method'])): ?><div class="ta-row"><span>Règlement</span><span><?= e($iv['payment_method']) ?></span></div><?php endif; ?>
        <?php if (!empty($iv['amount_ttc'])): ?><div class="ta-row"><span>Montant TTC</span><span><?= e(number_format((float)$iv['amount_ttc'], 2, ',', ' ')) ?> €</span></div><?php endif; ?>
      <?php endif; ?>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:.6rem;margin-top:.7rem;">
        <?php foreach (['client_signature' => 'Client'.(!empty($iv['tech_client_name']) ? ' — '.$iv['tech_client_name'] : ''), 'tech_signature' => 'Technicien'] as $sk => $sl): if (empty($iv[$sk])) continue; ?>
          <div><div class="ta-label"><?= e($sl) ?></div><div class="ta-sig"><img src="<?= e($iv[$sk]) ?>" alt="Signature <?= e($sl) ?>" style="height:90px;"></div></div>
        <?php endforeach; ?>
      </div>
    </div>
  </section>
  <?php else: $early = !in_array($status, ['sur_place', 'a_revoir'], true); ?>
  <?php if ($early): ?>
  <section class="ta-card" id="report-teaser">
    <div class="ta-card-b" style="display:flex;align-items:center;gap:.75rem;">
      <div style="flex:1;">
        <div style="font-weight:600;">Rapport d'intervention</div>
        <div class="ta-muted" style="font-size:.88rem;">Il s'ouvre automatiquement à votre arrivée sur place.</div>
      </div>
      <button type="button" class="ta-btn" onclick="document.getElementById('form-cr').hidden=false;this.closest('section').remove();initPads();">Remplir</button>
    </div>
  </section>
  <?php endif; ?>
  <form method="post" enctype="multipart/form-data" id="form-cr" <?= $early ? 'hidden' : '' ?>>
    <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
    <input type="hidden" name="action" value="report">
    <input type="hidden" name="tech_materials_used" id="mats-json" value="<?= e(json_encode($mats, JSON_UNESCAPED_UNICODE)) ?>">
    <input type="hidden" name="client_signature" id="sig-client-data" value="">
    <input type="hidden" name="tech_signature" id="sig-tech-data" value="">

    <section class="ta-card" id="rapport">
      <div class="ta-card-h"><h3>Rapport d'intervention</h3></div>
      <div class="ta-card-b">
        <div class="ta-field">
          <label for="f-fault">Intitulé de la panne</label>
          <input class="ta-input" id="f-fault" type="text" name="tech_fault_label" value="<?= e($iv['tech_fault_label'] ?? '') ?>" placeholder="Ex. : disjoncteur différentiel défectueux">
        </div>
        <div class="ta-field">
          <label for="f-report" class="req">Constat et travaux réalisés</label>
          <textarea class="ta-textarea" id="f-report" name="tech_report" data-req="le constat et les travaux réalisés" placeholder="Ce que vous avez constaté, ce que vous avez fait…"><?= e($iv['tech_report'] ?? '') ?></textarea>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:.6rem;" class="ta-field">
          <div>
            <label class="ta-label" for="f-dev">N° d'appareil</label>
            <input class="ta-input" id="f-dev" type="text" name="tech_device_number" value="<?= e($iv['tech_device_number'] ?? '') ?>" placeholder="Facultatif">
          </div>
          <div>
            <label class="ta-label" for="f-ticket">Heure du ticket</label>
            <input class="ta-input" id="f-ticket" type="time" name="tech_ticket_time" value="<?= e(!empty($iv['tech_ticket_time']) ? substr((string)$iv['tech_ticket_time'], 0, 5) : '') ?>">
          </div>
        </div>
      </div>
    </section>

    <section class="ta-card">
      <div class="ta-card-h"><h3>Contrôles</h3></div>
      <div class="ta-card-b" style="padding-top:.4rem;padding-bottom:.4rem;">
        <?php
        $checks = ['tech_realizable' => 'Intervention réalisable', 'tech_bad_use' => 'Panne due à une mauvaise utilisation'];
        if ($isLift) $checks['tech_elevator_restored'] = 'Ascenseur remis en service';
        foreach ($checks as $name => $label):
          $v = $iv[$name] ?? null; $v = ($v === null || $v === '') ? '' : (string)(int)$v; ?>
          <div class="ta-yn">
            <span><?= e($label) ?></span>
            <div class="ta-seg" data-for="<?= e($name) ?>">
              <input type="hidden" name="<?= e($name) ?>" value="<?= e($v) ?>">
              <button type="button" data-v="1" class="<?= $v === '1' ? 'on-yes' : '' ?>">Oui</button>
              <button type="button" data-v="0" class="<?= $v === '0' ? 'on-no' : '' ?>">Non</button>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </section>

    <section class="ta-card">
      <div class="ta-card-h"><h3>Matériel utilisé</h3><button type="button" onclick="addMat()">+ Ajouter</button></div>
      <div class="ta-card-b">
        <div id="mats"></div>
        <div id="mats-empty" class="ta-muted" style="font-size:.9rem;">Aucun matériel saisi.</div>
      </div>
    </section>

    <?php $reqPhotos = intervention_photos_required($iv); $photoTypes = get_presets('photo_type'); ?>
    <section class="ta-card" id="photos">
      <div class="ta-card-h"><h3 class="<?= $reqPhotos ? 'req' : '' ?>">Photos</h3><span class="ta-muted" style="font-size:.85rem;" id="photo-count"><?= count($photos) ?></span></div>
      <div class="ta-card-b">
        <?php if ($reqPhotos): ?>
          <div class="ta-label">Demandées par le dispatcher</div>
          <div id="req-photos" style="margin-bottom:.8rem;">
            <?php foreach ($reqPhotos as $rp): ?>
              <div class="ta-req-photo" data-type="<?= e($rp) ?>">
                <i></i><span><?= e($rp) ?></span>
                <button type="button" class="ta-btn" onclick="pickPhoto(<?= e(json_encode($rp)) ?>)"><?= ta_icon('camera') ?>Photo</button>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
        <div class="ta-photos" id="photo-grid">
          <?php foreach ($photos as $i => $ph): ?>
            <div class="ta-photo" data-type="<?= e($ph['type']) ?>">
              <a href="<?= e(asset_url($ph['path'])) ?>" target="_blank"><img src="<?= e(asset_url($ph['path'])) ?>" alt="" loading="lazy"></a>
              <?php if ($ph['type']): ?><span><?= e($ph['type']) ?></span><?php endif; ?>
              <button type="button" class="ta-photo-del" onclick="deleteSaved(this, <?= (int)$i ?>)" aria-label="Retirer la photo">×</button>
            </div>
          <?php endforeach; ?>
        </div>
        <div style="display:flex;gap:.5rem;">
          <?php if ($photoTypes): ?>
            <select class="ta-select" id="photo-type" aria-label="Type de photo" style="flex:1;">
              <option value="">Type (facultatif)</option>
              <?php foreach ($photoTypes as $pt): ?><option value="<?= e($pt['label']) ?>"><?= e($pt['label']) ?></option><?php endforeach; ?>
            </select>
          <?php endif; ?>
          <button type="button" class="ta-btn primary" style="flex:1;" onclick="pickPhoto(null)"><?= ta_icon('camera') ?>Ajouter</button>
        </div>
        <input type="file" id="photo-input" multiple accept="image/*" hidden>
        <div class="ta-muted" style="font-size:.8rem;margin-top:.4rem;">Vous pouvez en ajouter autant que nécessaire, en plusieurs fois.</div>
      </div>
    </section>

    <section class="ta-card">
      <div class="ta-card-h"><h3>Remarques</h3></div>
      <div class="ta-card-b">
        <textarea class="ta-textarea" name="tech_notes_extra" placeholder="Informations utiles pour le bureau ou la prochaine visite (facultatif)"><?= e($iv['tech_notes_extra'] ?? '') ?></textarea>
      </div>
    </section>

    <section class="ta-card" id="paiement">
      <div class="ta-card-h"><h3 class="req">Paiement</h3></div>
      <div class="ta-card-b">
        <?php $ps = (string)($iv['payment_status'] ?? ''); ?>
        <div class="ta-yn">
          <span>Le client a-t-il payé ?</span>
          <div class="ta-seg" id="pay-seg">
            <input type="hidden" name="payment_status" value="<?= e($ps) ?>" data-req="le paiement (payé ou non)">
            <button type="button" data-v="payé" class="<?= $ps === 'payé' ? 'on-yes' : '' ?>">Oui</button>
            <button type="button" data-v="non_payé" class="<?= $ps === 'non_payé' ? 'on-no' : '' ?>">Non</button>
          </div>
        </div>
        <div id="pay-details" <?= $ps === 'payé' ? '' : 'hidden' ?> style="display:grid;grid-template-columns:1fr 1fr;gap:.6rem;margin-top:.6rem;">
          <div>
            <label class="ta-label">Règlement</label>
            <select class="ta-select" name="payment_method">
              <option value="">—</option>
              <?php foreach (['Carte bancaire', 'Chèque', 'Espèces', 'Virement'] as $pm): ?>
                <option value="<?= e($pm) ?>" <?= ($iv['payment_method'] ?? '') === $pm ? 'selected' : '' ?>><?= e($pm) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label class="ta-label">Montant TTC (€)</label>
            <input class="ta-input" type="text" inputmode="decimal" name="amount_ttc" value="<?= $iv['amount_ttc'] !== null ? e(number_format((float)$iv['amount_ttc'], 2, ',', '')) : '' ?>" placeholder="0,00">
          </div>
        </div>
      </div>
    </section>

    <section class="ta-card" id="signatures">
      <div class="ta-card-h"><h3>Signatures</h3></div>
      <div class="ta-card-b">
        <div class="ta-field">
          <label class="req" for="f-signer">Nom du client signataire</label>
          <input class="ta-input" id="f-signer" type="text" name="tech_client_name" value="<?= e($iv['tech_client_name'] ?? '') ?>" placeholder="Ex. : M. Dupont" data-req="le nom du client signataire">
        </div>
        <?php foreach (['client' => ['client_signature', 'Signature du client'], 'tech' => ['tech_signature', 'Votre signature ('.$tech['name'].')']] as $pk => [$col, $plabel]): ?>
          <div class="ta-field">
            <div class="ta-label req"><?= e($plabel) ?></div>
            <div class="ta-sig" data-pad="<?= $pk ?>" data-saved="<?= !empty($iv[$col]) ? '1' : '' ?>" data-req="<?= $pk === 'client' ? 'la signature du client' : 'votre signature' ?>">
              <?php if (!empty($iv[$col])): ?><img src="<?= e($iv[$col]) ?>" alt="Signature enregistrée"><?php endif; ?>
              <canvas <?= !empty($iv[$col]) ? 'hidden' : '' ?>></canvas>
              <button type="button" class="ta-sig-clear">Effacer</button>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </section>
    <?php if ($early): ?>
      <button type="button" class="ta-btn" style="width:100%;margin-bottom:.8rem;" onclick="saveReport()"><?= ta_icon('save') ?>Enregistrer le rapport</button>
    <?php endif; ?>
  </form>
  <?php endif; ?>

  <?php if ($history): ?>
  <details class="ta-card">
    <summary class="ta-card-h"><h3>Historique</h3></summary>
    <div class="ta-card-b">
      <?php $stc = intervention_status_config(); foreach ($history as $h): ?>
        <div class="ta-hist">
          <?= e($stc[$h['status_to']]['label'] ?? $h['status_to']) ?><?= !empty($h['note']) ? ' — '.e($h['note']) : '' ?>
          <small><?= e(date('d/m/Y H:i', strtotime((string)$h['created_at']))) ?> · <?= e($h['actor_name'] ?: $h['actor_type']) ?></small>
        </div>
      <?php endforeach; ?>
    </div>
  </details>
  <?php endif; ?>
</main>

<?php if (!$locked): ?>
<div class="ta-bar"><div class="ta-bar-in">
  <?php if ($pending): ?>
    <form method="post" style="flex:1;display:flex;">
      <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
      <input type="hidden" name="action" value="accept">
      <button type="submit" class="ta-btn ok grow"><?= ta_icon('check') ?>Accepter l'intervention</button>
    </form>
  <?php elseif (in_array($status, ['nouveau', 'a_assigner', 'confirmé', 'assigné'], true) || $status === 'en_route'):
    [$next, $label, $ico] = $status === 'en_route' ? ['sur_place', 'Je suis arrivé', 'arrive'] : ['en_route', 'Je pars', 'car']; ?>
    <form method="post" style="flex:1;display:flex;">
      <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
      <input type="hidden" name="action" value="status">
      <input type="hidden" name="status" value="<?= e($next) ?>">
      <button type="submit" class="ta-btn <?= $next === 'en_route' ? 'dark' : 'primary' ?> grow"><?= ta_icon($ico) ?><?= e($label) ?></button>
    </form>
  <?php else: ?>
    <button type="button" class="ta-btn grow" onclick="saveReport()"><?= ta_icon('save') ?>Enregistrer</button>
    <button type="button" class="ta-btn ok grow" onclick="finish()"><?= ta_icon('check') ?>Terminer</button>
  <?php endif; ?>
</div></div>

<!-- Modifier les coordonnées -->
<div class="ta-sheet" id="sheet-client" onclick="if(event.target===this)closeSheet(this.id)">
  <form class="ta-sheet-in" method="post">
    <h3>Coordonnées du client</h3>
    <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
    <input type="hidden" name="action" value="update_client">
    <div class="ta-field"><label>Téléphone</label><input class="ta-input" type="tel" name="client_phone" value="<?= e($iv['client_phone'] ?? '') ?>"></div>
    <div class="ta-field"><label>Adresse</label><input class="ta-input" type="text" name="client_address" value="<?= e($iv['client_address'] ?? '') ?>"></div>
    <div style="display:grid;grid-template-columns:110px 1fr;gap:.6rem;" class="ta-field">
      <div><label class="ta-label">Code postal</label><input class="ta-input" type="text" name="client_postal" value="<?= e($iv['client_postal'] ?? '') ?>" inputmode="numeric"></div>
      <div><label class="ta-label">Ville</label><input class="ta-input" type="text" name="client_city" value="<?= e($iv['client_city'] ?? '') ?>"></div>
    </div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:.6rem;" class="ta-field">
      <div><label class="ta-label">Étage</label><input class="ta-input" type="text" name="client_floor" value="<?= e($iv['client_floor'] ?? '') ?>"></div>
      <div><label class="ta-label">Digicode</label><input class="ta-input" type="text" name="client_digicode" value="<?= e($iv['client_digicode'] ?? '') ?>"></div>
    </div>
    <div class="ta-sheet-actions">
      <button type="button" class="ta-btn grow" onclick="closeSheet('sheet-client')">Annuler</button>
      <button type="submit" class="ta-btn primary grow">Enregistrer</button>
    </div>
  </form>
</div>

<script>
function openSheet(id){ document.getElementById(id).classList.add('open'); }
function closeSheet(id){ document.getElementById(id).classList.remove('open'); }

// Oui / Non
document.querySelectorAll('.ta-seg').forEach(function (seg) {
  var input = seg.querySelector('input');
  seg.querySelectorAll('button').forEach(function (b) {
    b.addEventListener('click', function () {
      var v = b.dataset.v === input.value ? '' : b.dataset.v;
      input.value = v;
      seg.querySelectorAll('button').forEach(function (x) { x.className = ''; });
      if (v !== '') b.className = (v === '1' || v === 'payé') ? 'on-yes' : 'on-no';
      if (seg.id === 'pay-seg') document.getElementById('pay-details').hidden = v !== 'payé';
    });
  });
});

// Photos : ajoutées en plusieurs fois, réduites avant l'envoi, envoyées à l'enregistrement.
var pending = [];               // [{blob, type, url}]
var photoInput = document.getElementById('photo-input');
var pickType = null;
function pickPhoto(type) {
  var sel = document.getElementById('photo-type');
  pickType = type !== null ? type : (sel ? sel.value : '');
  photoInput.value = '';
  photoInput.click();
}
function shrink(file) {
  return new Promise(function (resolve) {
    if (!/^image\/(jpeg|png|webp)$/.test(file.type)) { resolve(file); return; }
    var img = new Image(), url = URL.createObjectURL(file);
    img.onload = function () {
      var max = 1800, w = img.naturalWidth, h = img.naturalHeight, r = Math.min(1, max / Math.max(w, h));
      var c = document.createElement('canvas'); c.width = Math.round(w * r); c.height = Math.round(h * r);
      c.getContext('2d').drawImage(img, 0, 0, c.width, c.height);
      URL.revokeObjectURL(url);
      c.toBlob(function (b) { resolve(b && b.size < file.size ? b : file); }, 'image/jpeg', 0.82);
    };
    img.onerror = function () { URL.revokeObjectURL(url); resolve(file); };
    img.src = url;
  });
}
function refreshPhotos() {
  var grid = document.getElementById('photo-grid');
  grid.querySelectorAll('.is-pending').forEach(function (n) { n.remove(); });
  pending.forEach(function (p, i) {
    var d = el('div', { className: 'ta-photo is-pending' });
    d.dataset.type = p.type;
    d.appendChild(el('img', { src: p.url, alt: '' }));
    if (p.type) d.appendChild(el('span', { textContent: p.type }));
    d.appendChild(el('em', { textContent: 'À envoyer' }));
    var x = el('button', { type: 'button', className: 'ta-photo-del', textContent: '×' });
    x.onclick = function () { URL.revokeObjectURL(p.url); pending.splice(i, 1); refreshPhotos(); };
    d.appendChild(x);
    grid.appendChild(d);
  });
  var types = Array.prototype.map.call(grid.querySelectorAll('.ta-photo'), function (n) { return n.dataset.type; });
  document.getElementById('photo-count').textContent = grid.querySelectorAll('.ta-photo').length;
  document.querySelectorAll('.ta-req-photo').forEach(function (r) { r.classList.toggle('ok', types.indexOf(r.dataset.type) > -1); });
}
if (photoInput) photoInput.addEventListener('change', function () {
  var files = Array.prototype.slice.call(this.files || []), type = pickType || '';
  Promise.all(files.map(shrink)).then(function (blobs) {
    blobs.forEach(function (b) { pending.push({ blob: b, type: type, url: URL.createObjectURL(b) }); });
    refreshPhotos();
  });
});
function deleteSaved(btn, idx) {
  if (!confirm('Retirer cette photo ?')) return;
  var fd = new FormData();
  fd.append('csrf_token', <?= json_encode($csrf) ?>); fd.append('action', 'delete_photo'); fd.append('idx', idx);
  fetch(location.href, { method: 'POST', body: fd, headers: { 'X-Requested-With': 'fetch' } })
    .then(function () { location.reload(); });
}
if (document.getElementById('photo-grid')) refreshPhotos();

// Matériel utilisé
var matPresets = <?= json_encode(array_values(array_map(static fn($p) => ['label' => (string)$p['label'], 'cat' => (string)($p['category'] ?? '')], get_presets('material'))), JSON_UNESCAPED_UNICODE) ?>;
var matGroups = <?= json_encode(array_map(static fn($c) => $c['label'], intervention_category_config()) + ['' => 'Divers'], JSON_UNESCAPED_UNICODE) ?>;
var ivCat = <?= json_encode((string)($iv['category'] ?? '')) ?>;
var matLabels = matPresets.map(function (p) { return p.label; });
var mats = [];
try { mats = JSON.parse(document.getElementById('mats-json').value) || []; } catch (e) { mats = []; }
var units = ['pièce', 'm', 'ml', 'kg', 'L', 'boîte'];
function el(tag, attrs) { var n = document.createElement(tag); for (var k in attrs) n[k] = attrs[k]; return n; }
function syncMats() {
  document.getElementById('mats-json').value = JSON.stringify(mats.filter(function (m) { return m.name; }));
  document.getElementById('mats-empty').style.display = mats.length ? 'none' : '';
}
function renderMats() {
  var box = document.getElementById('mats'); if (!box) return;
  box.innerHTML = '';
  mats.forEach(function (m, i) {
    var row = el('div', { className: 'ta-mat' });
    var known = !m.name || matLabels.indexOf(m.name) > -1;
    var sel = el('select', { className: 'ta-select' });
    sel.appendChild(el('option', { value: '', textContent: 'Choisir…' }));
    // Le métier de l'intervention d'abord, puis le reste.
    var cats = Object.keys(matGroups).sort(function (a, b) { return (b === ivCat) - (a === ivCat); });
    cats.forEach(function (c) {
      var items = matPresets.filter(function (p) { return p.cat === c; });
      if (!items.length) return;
      var og = el('optgroup', { label: matGroups[c] });
      items.forEach(function (p) { og.appendChild(el('option', { value: p.label, textContent: p.label, selected: p.label === m.name })); });
      sel.appendChild(og);
    });
    sel.appendChild(el('option', { value: '__autre__', textContent: 'Autre…', selected: !known }));
    var qty = el('input', { className: 'ta-input', type: 'number', min: '0', step: '0.1', inputMode: 'decimal', value: m.qty || '', placeholder: 'Qté' });
    var unit = el('select', { className: 'ta-select' });
    units.forEach(function (u) { unit.appendChild(el('option', { value: u, textContent: u, selected: u === (m.unit || 'pièce') })); });
    var del = el('button', { type: 'button', className: 'ta-mat-del', textContent: '×', title: 'Retirer' });
    var other = el('input', { className: 'ta-input other', type: 'text', placeholder: 'Nom du matériel', value: known ? '' : m.name });
    other.hidden = known;
    sel.onchange = function () {
      if (sel.value === '__autre__') { other.hidden = false; other.focus(); m.name = other.value; }
      else { other.hidden = true; m.name = sel.value; }
      syncMats();
    };
    other.oninput = function () { m.name = other.value; syncMats(); };
    qty.oninput = function () { m.qty = qty.value; syncMats(); };
    unit.onchange = function () { m.unit = unit.value; syncMats(); };
    del.onclick = function () { mats.splice(i, 1); renderMats(); };
    row.append(sel, qty, unit, del, other);
    box.appendChild(row);
  });
  syncMats();
}
function addMat() { mats.push({ name: '', qty: '1', unit: 'pièce' }); renderMats(); }
renderMats();

// Signatures au doigt (client et technicien)
var pads = {};
function initPads() {
  document.querySelectorAll('.ta-sig[data-pad]').forEach(function (box) {
    var key = box.dataset.pad;
    if (pads[key] && pads[key].ready) return;
    var canvas = box.querySelector('canvas'), pad = pads[key] = { dirty: false, cleared: false, ready: false, box: box, canvas: canvas };
    box.querySelector('.ta-sig-clear').onclick = function () {
      var img = box.querySelector('img');
      if (img) { img.remove(); pad.cleared = true; box.dataset.saved = ''; }
      canvas.hidden = false; pad.dirty = false; pad.ready = false; setup();
    };
    function setup() {
      if (canvas.hidden || box.offsetParent === null) return;
      var r = canvas.getBoundingClientRect(), dpr = window.devicePixelRatio || 1;
      canvas.width = r.width * dpr; canvas.height = r.height * dpr;
      var ctx = canvas.getContext('2d'); ctx.scale(dpr, dpr);
      ctx.lineWidth = 2.2; ctx.lineCap = 'round'; ctx.lineJoin = 'round'; ctx.strokeStyle = '#17223b';
      if (pad.ready) return; pad.ready = true;
      var drawing = false;
      function pos(e) { var b = canvas.getBoundingClientRect(); return [e.clientX - b.left, e.clientY - b.top]; }
      canvas.addEventListener('pointerdown', function (e) { drawing = true; pad.dirty = true; canvas.setPointerCapture(e.pointerId); var p = pos(e); ctx.beginPath(); ctx.moveTo(p[0], p[1]); });
      canvas.addEventListener('pointermove', function (e) { if (!drawing) return; var p = pos(e); ctx.lineTo(p[0], p[1]); ctx.stroke(); });
      canvas.addEventListener('pointerup', function () { drawing = false; });
      canvas.addEventListener('pointercancel', function () { drawing = false; });
    }
    setup();
  });
}
initPads();

function collectSignatures() {
  [['client', 'sig-client-data'], ['tech', 'sig-tech-data']].forEach(function (k) {
    var pad = pads[k[0]], inp = document.getElementById(k[1]);
    if (!pad || !inp) return;
    inp.value = pad.dirty ? pad.canvas.toDataURL('image/png') : (pad.cleared ? 'clear' : '');
  });
}
function missingFields() {
  var miss = [];
  var f = document.getElementById('form-cr');
  f.querySelectorAll('[data-req]').forEach(function (n) {
    if (n.classList.contains('ta-sig')) {
      var pad = pads[n.dataset.pad];
      if (!(n.dataset.saved || (pad && pad.dirty))) miss.push(n.dataset.req);
    } else if (!n.value.trim()) miss.push(n.dataset.req);
  });
  var lack = [];
  document.querySelectorAll('.ta-req-photo:not(.ok)').forEach(function (r) { lack.push(r.dataset.type); });
  if (lack.length) miss.push('les photos demandées (' + lack.join(', ') + ')');
  return miss;
}
var sending = false;
function send(complete) {
  var f = document.getElementById('form-cr'); if (!f || sending) return;
  collectSignatures();
  var fd = new FormData(f);
  pending.forEach(function (p, i) { fd.append('photos[]', p.blob, 'photo-' + (i + 1) + '.jpg'); fd.append('photo_types[]', p.type); });
  if (complete) fd.append('mark_complete', '1');
  sending = true;
  document.querySelectorAll('.ta-bar button').forEach(function (b) { b.disabled = true; });
  var label = document.querySelector('.ta-bar .ta-btn.ok, .ta-bar .ta-btn:last-child');
  if (label) label.dataset.txt = label.innerHTML, label.innerHTML = pending.length ? 'Envoi des photos…' : 'Enregistrement…';
  fetch(location.pathname + location.search, { method: 'POST', body: fd, headers: { 'X-Requested-With': 'fetch' } })
    .then(function (r) { return r.json(); })
    .then(function (d) {
      var to = new URL(d.redirect, location.href);
      if (to.pathname + to.search === location.pathname + location.search) {
        history.replaceState(null, '', to.hash || location.pathname + location.search);
        location.reload();
      } else {
        location.href = to.href;
      }
    })
    .catch(function () {
      sending = false;
      document.querySelectorAll('.ta-bar button').forEach(function (b) { b.disabled = false; });
      if (label && label.dataset.txt) label.innerHTML = label.dataset.txt;
      alert('Envoi impossible : vérifiez votre connexion puis réessayez. Rien n\'a été perdu sur cet écran.');
    });
}
function saveReport() { var f = document.getElementById('form-cr'); if (f) { f.hidden = false; send(false); } }
function finish() {
  var miss = missingFields();
  if (miss.length) {
    alert('Pour terminer, complétez :\n• ' + miss.join('\n• '));
    return;
  }
  if (!confirm('Terminer l\'intervention ? Le rapport ne sera plus modifiable.')) return;
  send(true);
}
</script>
<?php endif; ?>
</body>
</html>
