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

    if ($action === 'status') {
        $ns = trim((string)($_POST['status'] ?? ''));
        if (in_array($ns, ['en_route', 'sur_place'], true) && $ns !== ($iv['status'] ?? '')) {
            $upd = ['status' => $ns];
            if ($ns === 'en_route'  && empty($iv['tech_started_at'])) $upd['tech_started_at'] = date('Y-m-d H:i:s');
            if ($ns === 'sur_place' && empty($iv['tech_arrived_at'])) $upd['tech_arrived_at'] = date('Y-m-d H:i:s');
            update_intervention($id, $upd);
            log_intervention_history($id, $iv['status'], $ns, 'tech', $techId, (string)$tech['name']);
            flash('success', $ns === 'en_route' ? 'Trajet démarré. Le dispatcher est informé.' : 'Arrivée enregistrée.');
        }
        if (($_POST['back'] ?? '') === 'list') redirect_to('tech/dashboard.php');
        header('Location: '.$self); exit;
    }

    if ($action === 'report' && !in_array($iv['status'] ?? '', ['terminé', 'facturé', 'payé', 'annulé'], true)) {
        $photos = iv_photos($iv);
        if (!empty($_FILES['photos']['name'][0])) {
            $dir = __DIR__.'/../storage/uploads/interventions/'.$id.'/';
            if (!is_dir($dir)) mkdir($dir, 0775, true);
            $mimeMap   = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
            $photoType = trim((string)($_POST['photo_type_label'] ?? ''));
            foreach ($_FILES['photos']['tmp_name'] as $i => $tmp) {
                if (($_FILES['photos']['error'][$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) continue;
                $mt = mime_content_type($tmp) ?: '';
                if (!isset($mimeMap[$mt])) continue;
                $fn = 'tech_'.$id.'_'.date('YmdHis').'_'.bin2hex(random_bytes(3)).'.'.$mimeMap[$mt];
                if (move_uploaded_file($tmp, $dir.$fn)) {
                    $photos[] = ['type' => $photoType, 'path' => 'storage/uploads/interventions/'.$id.'/'.$fn];
                }
            }
        }

        $mats = json_decode(trim((string)($_POST['tech_materials_used'] ?? '[]')), true);
        $mats = is_array($mats) ? array_values(array_filter($mats, static fn($m) => is_array($m) && trim((string)($m['name'] ?? '')) !== '')) : [];

        $yn = static fn(string $k) => ($_POST[$k] ?? '') === '' ? null : (int)$_POST[$k];
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
        ];
        // Signature du client : image PNG dessinée à l'écran, conservée telle quelle.
        $sig = (string)($_POST['tech_signature'] ?? '');
        if ($sig === 'clear') {
            $upd['tech_signature'] = null;
        } elseif (str_starts_with($sig, 'data:image/png;base64,') && strlen($sig) < 600000
                  && base64_decode(substr($sig, 22), true) !== false) {
            $upd['tech_signature'] = $sig;
        }

        if (!empty($_POST['mark_complete'])) {
            $now = date('Y-m-d H:i:s');
            $upd['status'] = 'terminé';
            if (empty($iv['tech_completed_at'])) $upd['tech_completed_at'] = $now;
            if (empty($iv['tech_arrived_at']))   $upd['tech_arrived_at']   = $now;
            if (empty($iv['tech_close_time']))   $upd['tech_close_time']   = date('H:i');
            update_intervention($id, $upd);
            log_intervention_history($id, $iv['status'], 'terminé', 'tech', $techId, (string)$tech['name'], 'Clôturée depuis l\'application technicien');
            flash('success', 'Intervention terminée. Le rapport est disponible.');
        } else {
            update_intervention($id, $upd);
            flash('success', 'Rapport enregistré.');
        }
        header('Location: '.$self.(empty($_POST['mark_complete']) ? '#rapport' : '')); exit;
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
$isDone   = in_array($status, ['terminé', 'facturé', 'payé', 'devis_envoyé'], true);
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
          <a class="ta-btn grow" href="<?= e(ta_route_url($addr)) ?>" target="_blank" rel="noopener"><?= ta_icon('route') ?>Itinéraire</a>
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
      <?php if (!empty($iv['tech_signature'])): ?>
        <div class="ta-label" style="margin-top:.7rem;">Signature<?= !empty($iv['tech_client_name']) ? ' — '.e($iv['tech_client_name']) : '' ?></div>
        <div class="ta-sig"><img src="<?= e($iv['tech_signature']) ?>" alt="Signature du client"></div>
      <?php endif; ?>
    </div>
  </section>
  <?php else: $early = $status !== 'sur_place'; ?>
  <?php if ($early): ?>
  <section class="ta-card" id="report-teaser">
    <div class="ta-card-b" style="display:flex;align-items:center;gap:.75rem;">
      <div style="flex:1;">
        <div style="font-weight:600;">Rapport d'intervention</div>
        <div class="ta-muted" style="font-size:.88rem;">Il s'ouvre automatiquement à votre arrivée sur place.</div>
      </div>
      <button type="button" class="ta-btn" onclick="document.getElementById('form-cr').hidden=false;this.closest('section').remove();setupCanvas();">Remplir</button>
    </div>
  </section>
  <?php endif; ?>
  <form method="post" enctype="multipart/form-data" id="form-cr" <?= $early ? 'hidden' : '' ?>>
    <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
    <input type="hidden" name="action" value="report">
    <input type="hidden" name="tech_materials_used" id="mats-json" value="<?= e(json_encode($mats, JSON_UNESCAPED_UNICODE)) ?>">
    <input type="hidden" name="tech_signature" id="sig-data" value="">

    <section class="ta-card" id="rapport">
      <div class="ta-card-h"><h3>Rapport d'intervention</h3></div>
      <div class="ta-card-b">
        <div class="ta-field">
          <label for="f-fault">Intitulé de la panne</label>
          <input class="ta-input" id="f-fault" type="text" name="tech_fault_label" value="<?= e($iv['tech_fault_label'] ?? '') ?>" placeholder="Ex. : disjoncteur différentiel défectueux">
        </div>
        <div class="ta-field">
          <label for="f-report">Constat et travaux réalisés</label>
          <textarea class="ta-textarea" id="f-report" name="tech_report" placeholder="Ce que vous avez constaté, ce que vous avez fait…"><?= e($iv['tech_report'] ?? '') ?></textarea>
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

    <section class="ta-card">
      <div class="ta-card-h"><h3>Photos</h3><span class="ta-muted" style="font-size:.85rem;"><?= count($photos) ?></span></div>
      <div class="ta-card-b">
        <?php if ($photos): ?>
          <div class="ta-photos">
            <?php foreach ($photos as $ph): ?>
              <a class="ta-photo" href="<?= e(asset_url($ph['path'])) ?>" target="_blank"><img src="<?= e(asset_url($ph['path'])) ?>" alt="" loading="lazy"><?php if ($ph['type']): ?><span><?= e($ph['type']) ?></span><?php endif; ?></a>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
        <?php $photoTypes = get_presets('photo_type'); if ($photoTypes): ?>
          <div class="ta-field">
            <select class="ta-select" name="photo_type_label" aria-label="Type de photo">
              <option value="">Type de photo (facultatif)</option>
              <?php foreach ($photoTypes as $pt): ?><option value="<?= e($pt['label']) ?>"><?= e($pt['label']) ?></option><?php endforeach; ?>
            </select>
          </div>
        <?php endif; ?>
        <input type="file" id="photo-input" name="photos[]" multiple accept="image/*" capture="environment" hidden>
        <button type="button" class="ta-drop" onclick="document.getElementById('photo-input').click()"><?= ta_icon('camera') ?><span id="photo-label">Prendre ou ajouter des photos</span></button>
      </div>
    </section>

    <section class="ta-card">
      <div class="ta-card-h"><h3>Remarques</h3></div>
      <div class="ta-card-b">
        <textarea class="ta-textarea" name="tech_notes_extra" placeholder="Informations utiles pour le bureau ou la prochaine visite (facultatif)"><?= e($iv['tech_notes_extra'] ?? '') ?></textarea>
      </div>
    </section>

    <section class="ta-card">
      <div class="ta-card-h"><h3>Signature du client</h3></div>
      <div class="ta-card-b">
        <div class="ta-field">
          <input class="ta-input" type="text" name="tech_client_name" value="<?= e($iv['tech_client_name'] ?? '') ?>" placeholder="Nom du signataire">
        </div>
        <div class="ta-sig" id="sig-box">
          <?php if (!empty($iv['tech_signature'])): ?>
            <img src="<?= e($iv['tech_signature']) ?>" alt="Signature enregistrée" id="sig-saved">
          <?php endif; ?>
          <canvas id="sig-canvas" <?= !empty($iv['tech_signature']) ? 'hidden' : '' ?>></canvas>
          <button type="button" class="ta-sig-clear" onclick="clearSig()">Effacer</button>
        </div>
        <div class="ta-muted" style="font-size:.8rem;margin-top:.35rem;">Faites signer le client avec le doigt.</div>
      </div>
    </section>
    <?php if ($early): ?>
      <button type="button" class="ta-btn" style="width:100%;margin-bottom:.8rem;" onclick="saveReport()"><?= ta_icon('save') ?>Enregistrer le rapport</button>
    <?php endif; ?>
  </form>
  <?php endif; ?>

  <?php if (!empty($iv['amount_ht']) || !empty($iv['amount_ttc']) || !empty($iv['deposit'])): ?>
  <details class="ta-card">
    <summary class="ta-card-h"><h3>Paiement</h3></summary>
    <div class="ta-card-b">
      <?php foreach (['amount_ht' => 'Montant HT', 'amount_ttc' => 'Montant TTC', 'deposit' => 'Acompte'] as $k => $l): if (empty($iv[$k])) continue; ?>
        <div class="ta-row"><span><?= $l ?></span><span><?= e(number_format((float)$iv[$k], 2, ',', ' ')) ?> €</span></div>
      <?php endforeach; ?>
      <?php if (!empty($iv['payment_method'])): ?><div class="ta-row"><span>Règlement</span><span><?= e($iv['payment_method']) ?></span></div><?php endif; ?>
      <?php if (!$locked): ?><button type="button" class="ta-btn" style="width:100%;margin-top:.6rem;" onclick="openSheet('sheet-pay')">Modifier le paiement</button><?php endif; ?>
    </div>
  </details>
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
  <?php if (in_array($status, ['nouveau', 'confirmé', 'assigné'], true) || $status === 'en_route'):
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

<!-- Modifier le paiement -->
<div class="ta-sheet" id="sheet-pay" onclick="if(event.target===this)closeSheet(this.id)">
  <form class="ta-sheet-in" method="post">
    <h3>Paiement</h3>
    <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
    <input type="hidden" name="action" value="update_financial">
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:.6rem;" class="ta-field">
      <div><label class="ta-label">Montant HT (€)</label><input class="ta-input" type="number" step="0.01" inputmode="decimal" name="amount_ht" value="<?= e($iv['amount_ht'] ?? '') ?>"></div>
      <div><label class="ta-label">Montant TTC (€)</label><input class="ta-input" type="number" step="0.01" inputmode="decimal" name="amount_ttc" value="<?= e($iv['amount_ttc'] ?? '') ?>"></div>
    </div>
    <div class="ta-field"><label>Acompte (€)</label><input class="ta-input" type="number" step="0.01" inputmode="decimal" name="deposit" value="<?= e($iv['deposit'] ?? '') ?>"></div>
    <div class="ta-field"><label>Mode de règlement</label>
      <select class="ta-select" name="payment_method">
        <option value="">—</option>
        <?php foreach (['Carte bancaire', 'Chèque', 'Espèces', 'Virement', 'Prélèvement'] as $pm): ?>
          <option value="<?= e($pm) ?>" <?= ($iv['payment_method'] ?? '') === $pm ? 'selected' : '' ?>><?= e($pm) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="ta-sheet-actions">
      <button type="button" class="ta-btn grow" onclick="closeSheet('sheet-pay')">Annuler</button>
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
      if (v !== '') b.className = v === '1' ? 'on-yes' : 'on-no';
    });
  });
});

// Photos
var photoInput = document.getElementById('photo-input');
if (photoInput) photoInput.addEventListener('change', function () {
  var n = this.files ? this.files.length : 0;
  document.getElementById('photo-label').textContent = n ? n + ' photo' + (n > 1 ? 's' : '') + ' prête' + (n > 1 ? 's' : '') + ' — enregistrez pour les envoyer' : 'Prendre ou ajouter des photos';
});

// Matériel utilisé
var matPresets = <?= json_encode(array_values(array_map(static fn($p) => (string)$p['label'], get_presets('material'))), JSON_UNESCAPED_UNICODE) ?>;
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
    var known = !m.name || matPresets.indexOf(m.name) > -1;
    var sel = el('select', { className: 'ta-select' });
    sel.appendChild(el('option', { value: '', textContent: 'Choisir…' }));
    matPresets.forEach(function (p) { sel.appendChild(el('option', { value: p, textContent: p, selected: p === m.name })); });
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

// Signature
var canvas = document.getElementById('sig-canvas'), sigDirty = false;
function setupCanvas() {
  if (!canvas || canvas.hidden) return;
  var r = canvas.getBoundingClientRect(), dpr = window.devicePixelRatio || 1;
  canvas.width = r.width * dpr; canvas.height = r.height * dpr;
  var ctx = canvas.getContext('2d'); ctx.scale(dpr, dpr);
  ctx.lineWidth = 2.2; ctx.lineCap = 'round'; ctx.lineJoin = 'round'; ctx.strokeStyle = '#17223b';
  var drawing = false;
  function pos(e) { var b = canvas.getBoundingClientRect(); return [e.clientX - b.left, e.clientY - b.top]; }
  canvas.addEventListener('pointerdown', function (e) { drawing = true; sigDirty = true; canvas.setPointerCapture(e.pointerId); var p = pos(e); ctx.beginPath(); ctx.moveTo(p[0], p[1]); });
  canvas.addEventListener('pointermove', function (e) { if (!drawing) return; var p = pos(e); ctx.lineTo(p[0], p[1]); ctx.stroke(); });
  canvas.addEventListener('pointerup', function () { drawing = false; });
  canvas.addEventListener('pointercancel', function () { drawing = false; });
}
function clearSig() {
  var saved = document.getElementById('sig-saved');
  if (saved) { saved.remove(); document.getElementById('sig-data').value = 'clear'; }
  if (canvas) {
    var wasHidden = canvas.hidden; canvas.hidden = false;
    if (wasHidden) setupCanvas(); else canvas.getContext('2d').clearRect(0, 0, canvas.width, canvas.height);
  }
  sigDirty = false;
}
setupCanvas();

function beforeSubmit() {
  if (sigDirty && canvas) document.getElementById('sig-data').value = canvas.toDataURL('image/png');
}
function saveReport() {
  var f = document.getElementById('form-cr'); if (!f) return;
  beforeSubmit(); f.submit();
}
function finish() {
  var f = document.getElementById('form-cr'); if (!f) return;
  if (!confirm('Terminer l\'intervention ? Le rapport ne sera plus modifiable.')) return;
  beforeSubmit();
  f.appendChild(el('input', { type: 'hidden', name: 'mark_complete', value: '1' }));
  f.submit();
}
</script>
<?php endif; ?>
</body>
</html>
