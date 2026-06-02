<?php
declare(strict_types=1);
require_once __DIR__.'/../includes/bootstrap.php';
$tech   = require_tech_auth();
$techId = (int)$tech['id'];

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { header('Location: '.url_for('tech/dashboard.php')); exit; }

function load_iv(int $id, int $techId): ?array {
    try {
        return db_fetch(
            "SELECT i.*, c.id AS c_id, c.lastname, c.firstname, c.phone AS client_phone,
                    c.address AS client_address, c.city AS client_city, c.postal_code AS client_postal,
                    c.floor AS client_floor, c.digicode AS client_digicode, c.email AS client_email,
                    d.name AS disp_name
             FROM interventions i
             LEFT JOIN clients c ON c.id = i.client_id
             LEFT JOIN dispatchers d ON d.id = i.dispatcher_id
             WHERE i.id = ? AND i.technician_id = ?",
            [$id, $techId]
        );
    } catch (Throwable $e) { return null; }
}

$iv = load_iv($id, $techId);
if (!$iv) { header('Location: '.url_for('tech/dashboard.php')); exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim((string)($_POST['action'] ?? ''));

    if ($action === 'status') {
        $ns = trim((string)($_POST['status'] ?? ''));
        if (in_array($ns, ['en_route','sur_place','terminé'], true)) {
            $upd = ['status' => $ns];
            if ($ns === 'en_route'  && empty($iv['tech_started_at']))   $upd['tech_started_at']   = date('Y-m-d H:i:s');
            if ($ns === 'sur_place' && empty($iv['tech_arrived_at']))   $upd['tech_arrived_at']   = date('Y-m-d H:i:s');
            if ($ns === 'terminé'   && empty($iv['tech_completed_at'])) $upd['tech_completed_at'] = date('Y-m-d H:i:s');
            update_intervention($id, $upd);
            log_intervention_history($id, $iv['status'], $ns, 'tech', $techId, (string)$tech['name']);
        }
        header('Location: ?id='.$id.'&saved=1'); exit;
    }

    if ($action === 'report') {
        $photos = json_decode((string)($iv['tech_photos'] ?? '[]'), true);
        if (!is_array($photos)) $photos = [];
        if (!empty($_FILES['photos']['name'][0])) {
            $dir = __DIR__.'/../storage/uploads/interventions/'.$id.'/';
            if (!is_dir($dir)) mkdir($dir, 0775, true);
            $mime_map = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
            foreach ($_FILES['photos']['tmp_name'] as $i => $tmp) {
                if (($_FILES['photos']['error'][$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) continue;
                $mt = mime_content_type($tmp) ?: '';
                if (!isset($mime_map[$mt])) continue;
                $fn = 'tech_'.$id.'_'.date('YmdHis').'_'.bin2hex(random_bytes(3)).'.'.$mime_map[$mt];
                if (move_uploaded_file($tmp, $dir.$fn)) $photos[] = 'storage/uploads/interventions/'.$id.'/'.$fn;
            }
        }
        $real  = ($_POST['tech_realizable']        ?? '') === '' ? null : (int)$_POST['tech_realizable'];
        $bad   = ($_POST['tech_bad_use']            ?? '') === '' ? null : (int)$_POST['tech_bad_use'];
        $elev  = ($_POST['tech_elevator_restored']  ?? '') === '' ? null : (int)$_POST['tech_elevator_restored'];
        $upd = [
            'tech_fault_label'       => trim((string)($_POST['tech_fault_label']    ?? '')),
            'tech_report'            => trim((string)($_POST['tech_report']          ?? '')),
            'tech_notes_extra'       => trim((string)($_POST['tech_notes_extra']     ?? '')),
            'tech_device_number'     => trim((string)($_POST['tech_device_number']   ?? '')),
            'tech_ticket_time'       => trim((string)($_POST['tech_ticket_time']     ?? '')) ?: null,
            'tech_close_time'        => trim((string)($_POST['tech_close_time']      ?? '')) ?: null,
            'tech_realizable'        => $real,
            'tech_bad_use'           => $bad,
            'tech_elevator_restored' => $elev,
            'tech_photos'            => json_encode($photos),
        ];
        if (!empty($_POST['mark_complete'])) {
            $upd['status'] = 'terminé';
            if (empty($iv['tech_completed_at'])) $upd['tech_completed_at'] = date('Y-m-d H:i:s');
            update_intervention($id, $upd);
            log_intervention_history($id, $iv['status'], 'terminé', 'tech', $techId, (string)$tech['name'], 'Clôturé via portail tech');
        } else {
            update_intervention($id, $upd);
        }
        header('Location: ?id='.$id.'&saved=1&tab=cr'); exit;
    }

    if ($action === 'update_client') {
        $cid = (int)($iv['c_id'] ?? 0);
        if ($cid > 0) {
            try {
                db_execute(
                    "UPDATE clients SET lastname=?, phone=?, address=?, city=?, postal_code=? WHERE id=?",
                    [
                        trim((string)($_POST['client_name']   ?? '')),
                        trim((string)($_POST['client_phone']  ?? '')),
                        trim((string)($_POST['client_address']?? '')),
                        trim((string)($_POST['client_city']   ?? '')),
                        trim((string)($_POST['client_postal'] ?? '')),
                        $cid
                    ]
                );
            } catch (Throwable $e) {}
        }
        header('Location: ?id='.$id.'&saved=1'); exit;
    }

    if ($action === 'update_financial') {
        $upd = [
            'amount_ht'      => (float)str_replace(',','.',(string)($_POST['amount_ht']      ?? 0)),
            'amount_ttc'     => (float)str_replace(',','.',(string)($_POST['amount_ttc']     ?? 0)),
            'deposit'        => (float)str_replace(',','.',(string)($_POST['deposit']        ?? 0)),
            'payment_method' => trim((string)($_POST['payment_method'] ?? '')),
        ];
        update_intervention($id, $upd);
        header('Location: ?id='.$id.'&saved=1'); exit;
    }
}

$iv     = load_iv($id, $techId) ?? $iv;
$photos = json_decode((string)($iv['tech_photos'] ?? '[]'), true);
if (!is_array($photos)) $photos = [];

$status   = (string)($iv['status'] ?? 'nouveau');
$isDone   = in_array($status, ['terminé','facturé','payé'], true);
$clientName = trim(($iv['firstname'] ?? '').' '.($iv['lastname'] ?? ''));
$activeTab  = (($_GET['tab'] ?? '') === 'cr') ? 'cr' : 'details';
$saved      = isset($_GET['saved']);

$statusLabels = [
    'nouveau'=>'Nouveau','confirmé'=>'Confirmé','assigné'=>'Assigné',
    'en_route'=>'En route','sur_place'=>'Sur place','terminé'=>'Réalisée',
    'devis_envoyé'=>'Devis envoyé','facturé'=>'Facturé','payé'=>'Payé','annulé'=>'Annulé',
];
$statusColors = [
    'nouveau'=>'#94a3b8','confirmé'=>'#3b82f6','assigné'=>'#f59e0b',
    'en_route'=>'#f97316','sur_place'=>'#8b5cf6','terminé'=>'#22c55e',
    'devis_envoyé'=>'#06b6d4','facturé'=>'#10b981','payé'=>'#16a34a','annulé'=>'#ef4444',
];

$history = [];
try {
    $history = db_fetch_all(
        "SELECT * FROM intervention_history WHERE intervention_id=? ORDER BY created_at DESC LIMIT 20",
        [$id]
    );
} catch (Throwable $e) {}

$e = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
?><!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0,viewport-fit=cover">
<meta name="robots" content="noindex,nofollow">
<title><?= $e($iv['ref'] ?? 'INT #'.$id) ?> — <?= $e(company_name()) ?></title>
<link rel="stylesheet" href="<?= $e(asset_url('assets/css/tech.css')) ?>">
<style>
/* ── Tabs ── */
.prx-tabs { display:grid; grid-template-columns:1fr 1fr; gap:0; background:#e2e8f0; padding:.5rem; gap:.4rem; border-radius:0; }
.prx-tab {
  display:flex; align-items:center; justify-content:center; gap:.5rem;
  padding:.75rem; border-radius:10px; font-size:.88rem; font-weight:600;
  background:transparent; color:#64748b; border:none; cursor:pointer; transition:all .15s;
}
.prx-tab.active { background:#fff; color:#1e293b; box-shadow:0 1px 4px rgba(0,0,0,.12); }
.prx-tab svg { width:20px; height:20px; }
/* ── Accordion ── */
.prx-section { background:#fff; border-radius:12px; margin-bottom:.75rem; overflow:hidden; box-shadow:0 1px 3px rgba(0,0,0,.06); }
.prx-section-hd {
  display:flex; align-items:center; justify-content:space-between;
  padding:.85rem 1.1rem; background:#dbeafe; border-bottom:1px solid #e2e8f0;
  cursor:pointer; user-select:none;
}
.prx-section-hd span { font-size:.78rem; font-weight:800; letter-spacing:.08em; color:#1e3a8a; text-transform:uppercase; }
.prx-section-hd .chevron { transition:transform .2s; color:#3b82f6; font-size:1.1rem; }
.prx-section-hd.closed .chevron { transform:rotate(-90deg); }
.prx-section-body { padding:0; }
.prx-section-body.hidden { display:none; }
/* ── Info rows ── */
.prx-row {
  display:flex; align-items:center; justify-content:space-between;
  padding:.8rem 1.1rem; border-bottom:1px solid #f1f5f9; gap:.5rem;
}
.prx-row:last-child { border-bottom:none; }
.prx-row-label { font-size:.83rem; color:#64748b; flex-shrink:0; min-width:100px; }
.prx-row-value { font-size:.83rem; font-weight:600; text-align:right; flex:1; }
.prx-row-actions { display:flex; gap:.4rem; flex-shrink:0; }
.prx-icon-btn {
  width:34px; height:34px; border-radius:50%; background:#dbeafe; border:none;
  display:flex; align-items:center; justify-content:center; cursor:pointer; font-size:.9rem;
}
/* ── Status badge ── */
.prx-status-badge {
  display:inline-flex; align-items:center; padding:.3rem .85rem;
  border-radius:20px; font-size:.78rem; font-weight:700; border:1.5px solid currentColor;
}
/* ── Compte rendu fields ── */
.cr-field { margin-bottom:0; }
.cr-field-hd {
  display:flex; align-items:center; gap:.55rem;
  padding:.85rem 1.1rem; border-bottom:1px solid #f1f5f9;
  background:#f8fafc;
}
.cr-field-icon { width:30px; height:30px; border-radius:8px; display:flex; align-items:center; justify-content:center; font-size:.88rem; flex-shrink:0; }
.cr-field-icon.grey  { background:#e2e8f0; }
.cr-field-icon.blue  { background:#dbeafe; }
.cr-field-icon.green { background:#dcfce7; }
.cr-field-icon.orange{ background:#ffedd5; }
.cr-field-icon.purple{ background:#ede9fe; }
.cr-field-label { font-size:.8rem; color:#64748b; font-weight:500; }
.cr-field-body { padding:.75rem 1.1rem; border-bottom:1px solid #f1f5f9; }
.cr-field-body:last-child { border-bottom:none; }
.cr-field-value { font-size:.9rem; font-weight:600; color:#1e293b; }
.cr-field-value.muted { color:#94a3b8; font-style:italic; font-weight:400; }
.cr-input { width:100%; padding:.6rem .85rem; border:1.5px solid #e2e8f0; border-radius:8px; font-size:.9rem; font-family:inherit; background:#fff; }
.cr-input:focus { border-color:#F07B1D; outline:none; }
.cr-textarea { width:100%; padding:.6rem .85rem; border:1.5px solid #e2e8f0; border-radius:8px; font-size:.9rem; font-family:inherit; background:#fff; min-height:80px; resize:vertical; }
.cr-textarea:focus { border-color:#F07B1D; outline:none; }
.cr-toggle { display:flex; gap:.4rem; justify-content:flex-end; }
.cr-toggle-btn {
  padding:.4rem 1rem; border-radius:20px; font-size:.82rem; font-weight:700;
  border:1.5px solid #e2e8f0; background:#f8fafc; color:#64748b; cursor:pointer;
  transition:all .15s;
}
.cr-toggle-btn.active-yes { background:#dcfce7; border-color:#16a34a; color:#16a34a; }
.cr-toggle-btn.active-no  { background:#fee2e2; border-color:#dc2626; color:#dc2626; }
/* ── Photo grid ── */
.prx-photos { display:grid; grid-template-columns:repeat(2,1fr); gap:.5rem; padding:.75rem 1.1rem; }
.prx-photo { aspect-ratio:1; border-radius:8px; overflow:hidden; background:#f1f5f9; }
.prx-photo img { width:100%; height:100%; object-fit:cover; }
.prx-photo-add {
  aspect-ratio:1; border-radius:8px; border:2px dashed #cbd5e1;
  display:flex; flex-direction:column; align-items:center; justify-content:center;
  gap:.3rem; cursor:pointer; color:#94a3b8; font-size:.78rem; background:#f8fafc;
}
/* ── History ── */
.prx-hist-item { padding:.75rem 1.1rem; border-bottom:1px solid #f1f5f9; }
.prx-hist-item:last-child { border-bottom:none; }
.prx-hist-date { font-size:.74rem; color:#94a3b8; margin-bottom:.2rem; }
.prx-hist-text { font-size:.83rem; font-weight:600; }
/* ── Action bar ── */
.prx-action-bar {
  position:fixed; bottom:0; left:0; right:0; z-index:400;
  padding:.75rem 1rem; background:#fff; border-top:1px solid #e2e8f0;
  display:flex; gap:.5rem;
  box-shadow:0 -4px 20px rgba(0,0,0,.08);
}
.prx-action-bar.with-safe { padding-bottom:calc(.75rem + env(safe-area-inset-bottom)); }
.prx-ab-btn {
  flex:1; padding:.75rem; border-radius:12px; font-size:.84rem; font-weight:700;
  border:none; cursor:pointer; display:flex; align-items:center; justify-content:center; gap:.4rem;
}
.prx-ab-route   { background:#fff3e0; color:#f97316; }
.prx-ab-place   { background:#ede9fe; color:#7c3aed; }
.prx-ab-done    { background:#dcfce7; color:#16a34a; }
.prx-ab-save    { background:#f1f5f9; color:#1e293b; }
.prx-ab-close   { background:#16a34a; color:#fff; }
/* ── Flash ── */
.prx-flash { padding:.75rem 1.1rem; background:#dcfce7; color:#14532d; font-size:.84rem; font-weight:600; text-align:center; }
/* ── Edit modal ── */
.prx-modal-overlay {
  position:fixed; inset:0; z-index:500; background:rgba(0,0,0,.5);
  display:flex; align-items:flex-end; opacity:0; pointer-events:none; transition:opacity .2s;
}
.prx-modal-overlay.open { opacity:1; pointer-events:all; }
.prx-modal {
  background:#fff; border-radius:20px 20px 0 0; width:100%; max-height:85vh;
  overflow-y:auto; transform:translateY(100%); transition:transform .25s;
  padding:1.25rem 1.1rem calc(1.25rem + env(safe-area-inset-bottom));
}
.prx-modal-overlay.open .prx-modal { transform:translateY(0); }
.prx-modal-title { font-size:1rem; font-weight:800; margin-bottom:1rem; color:#1e293b; }
.prx-modal-field { margin-bottom:.75rem; }
.prx-modal-field label { display:block; font-size:.78rem; color:#64748b; font-weight:600; margin-bottom:.3rem; }
.prx-modal-field input, .prx-modal-field select {
  width:100%; padding:.65rem .9rem; border:1.5px solid #e2e8f0; border-radius:10px;
  font-size:.9rem; font-family:inherit; background:#fff;
}
.prx-modal-field input:focus, .prx-modal-field select:focus { border-color:#F07B1D; outline:none; }
.tab-content { display:none; }
.tab-content.active { display:block; }
</style>
</head>
<body>

<!-- ── Header ── -->
<div class="t-header">
  <a href="<?= $e(url_for('tech/dashboard.php')) ?>" class="t-header-back">‹ Retour</a>
  <div class="t-brand">EM<span>AE</span></div>
  <div class="t-header-right">
    <span class="prx-status-badge" style="color:<?= $e($statusColors[$status] ?? '#94a3b8') ?>;border-color:<?= $e($statusColors[$status] ?? '#94a3b8') ?>;background:<?= $e($statusColors[$status] ?? '#94a3b8') ?>18;">
      <?= $e($statusLabels[$status] ?? $status) ?>
    </span>
  </div>
</div>

<?php if ($saved): ?>
<div class="prx-flash">✅ Enregistré avec succès</div>
<?php endif; ?>

<!-- ── Tabs ── -->
<div class="prx-tabs">
  <button class="prx-tab <?= $activeTab==='details'?'active':'' ?>" onclick="switchTab('details')">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
  </button>
  <button class="prx-tab <?= $activeTab==='cr'?'active':'' ?>" onclick="switchTab('cr')">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/></svg>
  </button>
</div>

<div style="padding-bottom:<?= $isDone ? '1.5rem' : '5.5rem' ?>;">

<!-- ════════════ TAB DÉTAILS ════════════ -->
<div id="tab-details" class="tab-content <?= $activeTab==='details'?'active':'' ?>" style="padding:.75rem .85rem 0;">

  <!-- CLIENT -->
  <div class="prx-section">
    <div class="prx-section-hd" onclick="toggleSection(this)">
      <span>CLIENT</span>
      <span class="chevron">⌄</span>
    </div>
    <div class="prx-section-body">
      <div class="prx-row">
        <span class="prx-row-label">Client</span>
        <span class="prx-row-value"><?= $e($clientName ?: '—') ?></span>
        <div class="prx-row-actions">
          <button class="prx-icon-btn" onclick="openModal('modal-client')" title="Modifier">✏️</button>
        </div>
      </div>
      <div class="prx-row">
        <span class="prx-row-label">Réf.</span>
        <span class="prx-row-value"><?= $e($iv['ref'] ?? 'INT #'.$id) ?></span>
      </div>
      <?php if (!empty($iv['client_address'])): ?>
      <div class="prx-row">
        <span class="prx-row-label">Adresse</span>
        <div style="display:flex;align-items:center;gap:.5rem;justify-content:flex-end;">
          <span class="prx-row-value"><?= $e($iv['client_address']) ?><?= !empty($iv['client_city']) ? ', '.$e($iv['client_city']) : '' ?></span>
          <div class="prx-row-actions">
            <a href="https://maps.google.com/?q=<?= urlencode(($iv['client_address']??'').' '.($iv['client_city']??'')) ?>" target="_blank" class="prx-icon-btn" title="Maps">📍</a>
            <button class="prx-icon-btn" onclick="copyText('<?= $e(($iv['client_address']??'').' '.($iv['client_city']??'')) ?>')" title="Copier">📋</button>
          </div>
        </div>
      </div>
      <?php endif; ?>
      <?php if (!empty($iv['client_phone'])): ?>
      <div class="prx-row">
        <span class="prx-row-label">Téléphone</span>
        <div style="display:flex;align-items:center;gap:.5rem;justify-content:flex-end;">
          <span class="prx-row-value" style="color:#2563eb;"><?= $e($iv['client_phone']) ?></span>
          <a href="tel:<?= $e(preg_replace('/\s+/','',(string)$iv['client_phone'])) ?>" class="prx-icon-btn" title="Appeler">📞</a>
        </div>
      </div>
      <?php endif; ?>
      <?php if (!empty($iv['client_floor']) || !empty($iv['client_digicode'])): ?>
      <div class="prx-row">
        <span class="prx-row-label">Accès</span>
        <span class="prx-row-value" style="color:#64748b;">
          <?= !empty($iv['client_floor'])   ? 'Étage '.$e($iv['client_floor']) : '' ?>
          <?= !empty($iv['client_digicode'])? ' — Code : '.$e($iv['client_digicode']) : '' ?>
        </span>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- INTERVENTION -->
  <div class="prx-section">
    <div class="prx-section-hd" onclick="toggleSection(this)">
      <span>INTERVENTION</span>
      <span class="chevron">⌄</span>
    </div>
    <div class="prx-section-body">
      <div class="prx-row">
        <span class="prx-row-label">Statut</span>
        <span class="prx-status-badge" style="color:<?= $e($statusColors[$status]??'#94a3b8') ?>;border-color:<?= $e($statusColors[$status]??'#94a3b8') ?>;background:<?= $e($statusColors[$status]??'#94a3b8') ?>18;">
          <?= $e($statusLabels[$status] ?? $status) ?>
        </span>
      </div>
      <?php if (!empty($iv['category'])): ?>
      <div class="prx-row">
        <span class="prx-row-label">Catégorie</span>
        <span class="prx-row-value"><?= $e(ucfirst($iv['category'])) ?></span>
      </div>
      <?php endif; ?>
      <?php if (!empty($iv['type_label'])): ?>
      <div class="prx-row">
        <span class="prx-row-label">Type</span>
        <span class="prx-row-value"><?= $e($iv['type_label']) ?></span>
      </div>
      <?php endif; ?>
      <?php if (!empty($iv['scheduled_date'])): ?>
      <div class="prx-row">
        <span class="prx-row-label">Planifié le</span>
        <span class="prx-row-value" style="color:#2563eb;">
          <?= $e(date('d/m/Y', strtotime((string)$iv['scheduled_date']))) ?>
          <?= !empty($iv['scheduled_time']) ? ' à '.substr((string)$iv['scheduled_time'],0,5) : '' ?>
        </span>
      </div>
      <?php endif; ?>
      <?php if (!empty($iv['tech_started_at'])): ?>
      <div class="prx-row">
        <span class="prx-row-label">Début</span>
        <span class="prx-row-value"><?= $e(date('d/m/Y à H:i', strtotime((string)$iv['tech_started_at']))) ?></span>
      </div>
      <?php endif; ?>
      <?php if (!empty($iv['tech_arrived_at'])): ?>
      <div class="prx-row">
        <span class="prx-row-label">Arrivée site</span>
        <span class="prx-row-value"><?= $e(date('d/m/Y à H:i', strtotime((string)$iv['tech_arrived_at']))) ?></span>
      </div>
      <?php endif; ?>
      <?php if (!empty($iv['tech_completed_at'])): ?>
      <div class="prx-row">
        <span class="prx-row-label">Fin</span>
        <span class="prx-row-value"><?= $e(date('d/m/Y à H:i', strtotime((string)$iv['tech_completed_at']))) ?></span>
      </div>
      <?php endif; ?>
      <?php if (!empty($iv['duration_estimate'])): ?>
      <div class="prx-row">
        <span class="prx-row-label">Durée prévue</span>
        <span class="prx-row-value"><?= $e((int)$iv['duration_estimate'] >= 60 ? intdiv((int)$iv['duration_estimate'],60).'h'.((int)$iv['duration_estimate']%60?sprintf('%02d',(int)$iv['duration_estimate']%60):'') : $iv['duration_estimate'].' min') ?></span>
      </div>
      <?php endif; ?>
      <?php if (!empty($iv['description'])): ?>
      <div class="prx-row" style="flex-direction:column;align-items:flex-start;gap:.3rem;">
        <span class="prx-row-label">Description</span>
        <span style="font-size:.84rem;color:#1e293b;line-height:1.5;"><?= $e($iv['description']) ?></span>
      </div>
      <?php endif; ?>
      <?php if (!empty($iv['notes_admin'])): ?>
      <div class="prx-row" style="flex-direction:column;align-items:flex-start;gap:.3rem;background:#fffbeb;">
        <span class="prx-row-label" style="color:#92400e;">⚠️ Instructions dispatcher</span>
        <span style="font-size:.84rem;color:#92400e;line-height:1.5;"><?= $e($iv['notes_admin']) ?></span>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- FINANCIER -->
  <?php if (!empty($iv['amount_ht']) || !empty($iv['amount_ttc'])): ?>
  <div class="prx-section">
    <div class="prx-section-hd" onclick="toggleSection(this)">
      <span>FINANCIER</span>
      <span class="chevron">⌄</span>
    </div>
    <div class="prx-section-body">
      <?php if (!empty($iv['amount_ht'])): ?>
      <div class="prx-row"><span class="prx-row-label">Montant HT</span><span class="prx-row-value"><?= $e(number_format((float)$iv['amount_ht'],2,',',' ')) ?> €</span></div>
      <?php endif; ?>
      <?php if (!empty($iv['amount_ttc'])): ?>
      <div class="prx-row"><span class="prx-row-label">Montant TTC</span><span class="prx-row-value"><?= $e(number_format((float)$iv['amount_ttc'],2,',',' ')) ?> €</span></div>
      <?php endif; ?>
      <?php if (!empty($iv['deposit'])): ?>
      <div class="prx-row"><span class="prx-row-label">Acompte</span><span class="prx-row-value"><?= $e(number_format((float)$iv['deposit'],2,',',' ')) ?> €</span></div>
      <?php endif; ?>
      <?php if (!empty($iv['payment_method'])): ?>
      <div class="prx-row"><span class="prx-row-label">Règlement</span><span class="prx-row-value"><?= $e($iv['payment_method']) ?></span></div>
      <?php endif; ?>
      <div class="prx-row" style="justify-content:center;">
        <button class="t-btn-sm t-btn-outline" onclick="openModal('modal-financial')" style="padding:.45rem 1rem;font-size:.8rem;border-radius:8px;border:1.5px solid #e2e8f0;background:#f8fafc;cursor:pointer;">✏️ Modifier</button>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <!-- HISTORIQUE -->
  <div class="prx-section">
    <div class="prx-section-hd closed" onclick="toggleSection(this)">
      <span>HISTORIQUE</span>
      <span class="chevron">⌄</span>
    </div>
    <div class="prx-section-body hidden">
      <?php if (empty($history)): ?>
        <div class="prx-row"><span style="color:#94a3b8;font-size:.83rem;">Aucun historique</span></div>
      <?php else: ?>
        <?php foreach ($history as $h): ?>
        <div class="prx-hist-item">
          <div class="prx-hist-date"><?= $e(date('d/m/Y à H:i', strtotime((string)$h['created_at']))) ?> — <?= $e($h['actor_name'] ?? $h['actor_type']) ?></div>
          <div class="prx-hist-text">
            <?php if ($h['status_from'] && $h['status_to']): ?>
              <?= $e($h['status_from']) ?> → <strong><?= $e($h['status_to']) ?></strong>
            <?php else: ?>
              <?= $e($h['status_to']) ?>
            <?php endif; ?>
            <?php if (!empty($h['note'])): ?> <span style="color:#64748b;font-weight:400;">— <?= $e($h['note']) ?></span><?php endif; ?>
          </div>
        </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>

  <a href="<?= $e(url_for('dispatcher/rapport_pdf.php?id=').$id) ?>" target="_blank" class="t-btn t-btn-pdf" style="margin:.25rem 0 1.5rem;display:flex;align-items:center;justify-content:center;gap:.4rem;">📄 Rapport PDF</a>

</div><!-- /tab-details -->

<!-- ════════════ TAB COMPTE RENDU ════════════ -->
<div id="tab-cr" class="tab-content <?= $activeTab==='cr'?'active':'' ?>" style="padding:.75rem .85rem 0;">

<?php if ($isDone): ?>
  <!-- READ-ONLY mode -->
  <div class="prx-section">
    <div class="prx-section-hd"><span>COMPTE RENDU</span><span class="chevron">⌄</span></div>
    <div class="prx-section-body">

      <?php if (!empty($iv['notes_admin'])): ?>
      <div class="cr-field-hd"><div class="cr-field-icon grey">ℹ️</div><div class="cr-field-label">Informations à destination du technicien</div></div>
      <div class="cr-field-body"><div class="cr-field-value"><?= $e($iv['notes_admin']) ?></div></div>
      <?php endif; ?>

      <div class="cr-field-hd"><div class="cr-field-icon grey">Abc</div><div class="cr-field-label">Intitulé de la panne</div></div>
      <div class="cr-field-body"><div class="cr-field-value <?= empty($iv['tech_fault_label'])?'muted':'' ?>"><?= $e($iv['tech_fault_label'] ?: '—') ?></div></div>

      <div class="cr-field-hd"><div class="cr-field-icon blue">🗓️</div><div class="cr-field-label">Date</div></div>
      <div class="cr-field-body"><div class="cr-field-value"><?= !empty($iv['scheduled_date']) ? $e(date('d/m/Y', strtotime((string)$iv['scheduled_date']))) : '—' ?></div></div>

      <div class="cr-field-hd"><div class="cr-field-icon blue">🕐</div><div class="cr-field-label">Heure de réception du ticket</div></div>
      <div class="cr-field-body"><div class="cr-field-value <?= empty($iv['tech_ticket_time'])?'muted':'' ?>"><?= $e($iv['tech_ticket_time'] ? substr((string)$iv['tech_ticket_time'],0,5) : '—') ?></div></div>

      <div class="cr-field-hd"><div class="cr-field-icon green">✅</div><div class="cr-field-label">Intervention réalisable ?</div></div>
      <div class="cr-field-body"><div class="cr-field-value <?= $iv['tech_realizable']===null?'muted':'' ?>"><?= $iv['tech_realizable']===null ? '—' : ($iv['tech_realizable'] ? 'Oui' : 'Non') ?></div></div>

      <div class="cr-field-hd"><div class="cr-field-icon blue">🕐</div><div class="cr-field-label">Heure arrivée sur site</div></div>
      <div class="cr-field-body"><div class="cr-field-value <?= empty($iv['tech_arrived_at'])?'muted':'' ?>"><?= !empty($iv['tech_arrived_at']) ? $e(date('H:i', strtotime((string)$iv['tech_arrived_at']))) : '—' ?></div></div>

      <div class="cr-field-hd"><div class="cr-field-icon orange">🔢</div><div class="cr-field-label">Numéro d'appareil</div></div>
      <div class="cr-field-body"><div class="cr-field-value <?= empty($iv['tech_device_number'])?'muted':'' ?>"><?= $e($iv['tech_device_number'] ?: '—') ?></div></div>

      <div class="cr-field-hd"><div class="cr-field-icon grey">📝</div><div class="cr-field-label">Descriptif de la panne</div></div>
      <div class="cr-field-body"><div class="cr-field-value <?= empty($iv['tech_report'])?'muted':'' ?>"><?= $e($iv['tech_report'] ?: '—') ?></div></div>

      <div class="cr-field-hd"><div class="cr-field-icon grey">📋</div><div class="cr-field-label">Informations complémentaires</div></div>
      <div class="cr-field-body"><div class="cr-field-value <?= empty($iv['tech_notes_extra'])?'muted':'' ?>"><?= $e($iv['tech_notes_extra'] ?: '—') ?></div></div>

      <?php if (!empty($photos)): ?>
      <div class="cr-field-hd"><div class="cr-field-icon purple">📷</div><div class="cr-field-label">Photos (<?= count($photos) ?>)</div></div>
      <div class="prx-photos">
        <?php foreach ($photos as $ph): ?>
          <div class="prx-photo"><a href="<?= $e(asset_url($ph)) ?>" target="_blank"><img src="<?= $e(asset_url($ph)) ?>" alt="" loading="lazy"></a></div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

      <div class="cr-field-hd"><div class="cr-field-icon green">🔧</div><div class="cr-field-label">Ascenseur remis en service ?</div></div>
      <div class="cr-field-body"><div class="cr-field-value <?= $iv['tech_elevator_restored']===null?'muted':'' ?>"><?= $iv['tech_elevator_restored']===null ? '—' : ($iv['tech_elevator_restored'] ? 'Oui' : 'Non') ?></div></div>

      <div class="cr-field-hd"><div class="cr-field-icon green">✅</div><div class="cr-field-label">Intervention terminée ?</div></div>
      <div class="cr-field-body"><div class="cr-field-value">Oui</div></div>

      <div class="cr-field-hd"><div class="cr-field-icon orange">⚠️</div><div class="cr-field-label">Panne liée à une mauvaise utilisation ?</div></div>
      <div class="cr-field-body"><div class="cr-field-value <?= $iv['tech_bad_use']===null?'muted':'' ?>"><?= $iv['tech_bad_use']===null ? '—' : ($iv['tech_bad_use'] ? 'Oui' : 'Non') ?></div></div>

      <div class="cr-field-hd"><div class="cr-field-icon blue">🕐</div><div class="cr-field-label">Heure de clôture</div></div>
      <div class="cr-field-body"><div class="cr-field-value <?= empty($iv['tech_close_time'])?'muted':'' ?>"><?= $e($iv['tech_close_time'] ? substr((string)$iv['tech_close_time'],0,5) : (empty($iv['tech_completed_at']) ? '—' : date('H:i', strtotime((string)$iv['tech_completed_at'])))) ?></div></div>

    </div>
  </div>

  <a href="<?= $e(url_for('dispatcher/rapport_pdf.php?id=').$id) ?>" target="_blank" class="t-btn t-btn-pdf" style="margin:.25rem 0 1.5rem;display:flex;align-items:center;justify-content:center;gap:.4rem;">📄 Rapport PDF</a>

<?php else: ?>
  <!-- EDITABLE mode -->
  <form method="post" enctype="multipart/form-data" id="form-cr">
    <input type="hidden" name="action"                value="report">
    <input type="hidden" name="tech_realizable"       id="inp-realizable"  value="<?= $iv['tech_realizable']       !== null ? (int)$iv['tech_realizable']       : '' ?>">
    <input type="hidden" name="tech_bad_use"          id="inp-baduse"      value="<?= $iv['tech_bad_use']          !== null ? (int)$iv['tech_bad_use']          : '' ?>">
    <input type="hidden" name="tech_elevator_restored" id="inp-elevator"   value="<?= $iv['tech_elevator_restored'] !== null ? (int)$iv['tech_elevator_restored'] : '' ?>">

    <div class="prx-section">
      <div class="prx-section-hd"><span>COMPTE RENDU</span><span class="chevron">⌄</span></div>
      <div class="prx-section-body">

        <?php if (!empty($iv['notes_admin'])): ?>
        <div class="cr-field-hd"><div class="cr-field-icon grey">ℹ️</div><div class="cr-field-label">Informations à destination du technicien</div></div>
        <div class="cr-field-body" style="background:#fffbeb;"><div style="font-size:.85rem;color:#92400e;line-height:1.5;"><?= $e($iv['notes_admin']) ?></div></div>
        <?php endif; ?>

        <div class="cr-field-hd"><div class="cr-field-icon grey">Abc</div><div class="cr-field-label">Intitulé de la panne</div></div>
        <div class="cr-field-body"><input type="text" name="tech_fault_label" class="cr-input" value="<?= $e($iv['tech_fault_label'] ?? '') ?>" placeholder="Ex: 06001477"></div>

        <div class="cr-field-hd"><div class="cr-field-icon blue">🗓️</div><div class="cr-field-label">Date</div></div>
        <div class="cr-field-body"><div class="cr-field-value" style="color:#94a3b8;"><?= !empty($iv['scheduled_date']) ? $e(date('d/m/Y', strtotime((string)$iv['scheduled_date']))) : date('d/m/Y') ?></div></div>

        <div class="cr-field-hd"><div class="cr-field-icon blue">🕐</div><div class="cr-field-label">Heure de réception du ticket</div></div>
        <div class="cr-field-body"><input type="time" name="tech_ticket_time" class="cr-input" value="<?= $e($iv['tech_ticket_time'] ? substr((string)$iv['tech_ticket_time'],0,5) : '') ?>"></div>

        <div class="cr-field-hd"><div class="cr-field-icon green">✅</div><div class="cr-field-label">Intervention réalisable ?</div></div>
        <div class="cr-field-body">
          <div class="cr-toggle">
            <button type="button" class="cr-toggle-btn <?= (string)$iv['tech_realizable']==='1'?'active-yes':'' ?>" onclick="setToggle('realizable','1',this)">Oui</button>
            <button type="button" class="cr-toggle-btn <?= (string)$iv['tech_realizable']==='0'?'active-no':'' ?>"  onclick="setToggle('realizable','0',this)">Non</button>
          </div>
        </div>

        <div class="cr-field-hd"><div class="cr-field-icon blue">🕐</div><div class="cr-field-label">Heure arrivée sur site</div></div>
        <div class="cr-field-body"><input type="time" name="tech_arrived_time" class="cr-input" value="<?= !empty($iv['tech_arrived_at']) ? $e(date('H:i', strtotime((string)$iv['tech_arrived_at']))) : '' ?>"></div>

        <div class="cr-field-hd"><div class="cr-field-icon orange">🔢</div><div class="cr-field-label">Numéro d'appareil</div></div>
        <div class="cr-field-body"><input type="text" name="tech_device_number" class="cr-input" value="<?= $e($iv['tech_device_number'] ?? '') ?>" placeholder="Ex: ASC-001"></div>

        <div class="cr-field-hd"><div class="cr-field-icon grey">📝</div><div class="cr-field-label">Descriptif de la panne</div></div>
        <div class="cr-field-body"><textarea name="tech_report" class="cr-textarea" placeholder="Décrivez la panne constatée et les travaux réalisés…"><?= $e($iv['tech_report'] ?? '') ?></textarea></div>

        <div class="cr-field-hd"><div class="cr-field-icon grey">📋</div><div class="cr-field-label">Informations complémentaires</div></div>
        <div class="cr-field-body"><textarea name="tech_notes_extra" class="cr-textarea" placeholder="Informations supplémentaires, matériaux utilisés…"><?= $e($iv['tech_notes_extra'] ?? '') ?></textarea></div>

        <!-- Photos -->
        <div class="cr-field-hd"><div class="cr-field-icon purple">📷</div><div class="cr-field-label">Photos complémentaires</div></div>
        <?php if (!empty($photos)): ?>
        <div class="prx-photos">
          <?php foreach ($photos as $ph): ?>
            <div class="prx-photo"><img src="<?= $e(asset_url($ph)) ?>" alt="" loading="lazy"></div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <div class="cr-field-body">
          <input type="file" id="photo-input" name="photos[]" multiple accept="image/*" capture="environment" style="display:none;" onchange="updatePhotoLabel(this)">
          <div class="prx-photo-add" onclick="document.getElementById('photo-input').click();" style="height:80px;border-radius:10px;aspect-ratio:unset;">
            <span style="font-size:1.4rem;">📷</span>
            <span id="photo-label">Ajouter des photos</span>
          </div>
        </div>

        <div class="cr-field-hd"><div class="cr-field-icon green">🔧</div><div class="cr-field-label">Ascenseur remis en service ?</div></div>
        <div class="cr-field-body">
          <div class="cr-toggle">
            <button type="button" class="cr-toggle-btn <?= (string)$iv['tech_elevator_restored']==='1'?'active-yes':'' ?>" onclick="setToggle('elevator','1',this)">Oui</button>
            <button type="button" class="cr-toggle-btn <?= (string)$iv['tech_elevator_restored']==='0'?'active-no':'' ?>"  onclick="setToggle('elevator','0',this)">Non</button>
          </div>
        </div>

        <div class="cr-field-hd"><div class="cr-field-icon green">✅</div><div class="cr-field-label">Intervention terminée ?</div></div>
        <div class="cr-field-body">
          <div class="cr-toggle">
            <button type="button" class="cr-toggle-btn" onclick="submitComplete()">Oui ✓</button>
          </div>
        </div>

        <div class="cr-field-hd"><div class="cr-field-icon orange">⚠️</div><div class="cr-field-label">Panne liée à une mauvaise utilisation ?</div></div>
        <div class="cr-field-body">
          <div class="cr-toggle">
            <button type="button" class="cr-toggle-btn <?= (string)$iv['tech_bad_use']==='1'?'active-yes':'' ?>" onclick="setToggle('baduse','1',this)">Oui</button>
            <button type="button" class="cr-toggle-btn <?= (string)$iv['tech_bad_use']==='0'?'active-no':'' ?>"  onclick="setToggle('baduse','0',this)">Non</button>
          </div>
        </div>

        <div class="cr-field-hd"><div class="cr-field-icon blue">🕐</div><div class="cr-field-label">Heure de clôture</div></div>
        <div class="cr-field-body"><input type="time" name="tech_close_time" class="cr-input" value="<?= $e($iv['tech_close_time'] ? substr((string)$iv['tech_close_time'],0,5) : '') ?>"></div>

      </div>
    </div>
  </form>
<?php endif; ?>

</div><!-- /tab-cr -->

</div><!-- /padding wrapper -->

<!-- ── Status Action Bar ── -->
<?php if (!$isDone): ?>
<div class="prx-action-bar with-safe" id="action-bar">
  <?php if ($status === 'assigné' || $status === 'confirmé' || $status === 'nouveau'): ?>
    <form method="post" style="flex:1;">
      <input type="hidden" name="action" value="status">
      <input type="hidden" name="status" value="en_route">
      <button type="submit" class="prx-ab-btn prx-ab-route" style="width:100%;">🚗 En route</button>
    </form>
  <?php elseif ($status === 'en_route'): ?>
    <form method="post" style="flex:1;">
      <input type="hidden" name="action" value="status">
      <input type="hidden" name="status" value="sur_place">
      <button type="submit" class="prx-ab-btn prx-ab-place" style="width:100%;">📍 Sur place</button>
    </form>
  <?php elseif ($status === 'sur_place'): ?>
    <button type="button" class="prx-ab-btn prx-ab-save" style="flex:1;" onclick="saveReport()">💾 Enregistrer</button>
    <button type="button" class="prx-ab-btn prx-ab-close" style="flex:1;" onclick="submitComplete()">✅ Clôturer</button>
  <?php else: ?>
    <button type="button" class="prx-ab-btn prx-ab-save" style="flex:1;" onclick="saveReport()">💾 Enregistrer</button>
    <button type="button" class="prx-ab-btn prx-ab-close" style="flex:1;" onclick="submitComplete()">✅ Clôturer</button>
  <?php endif; ?>
</div>
<?php endif; ?>

<!-- ── Modal Modifier Client ── -->
<div class="prx-modal-overlay" id="modal-client">
  <div class="prx-modal">
    <div class="prx-modal-title">✏️ Modifier les infos client</div>
    <form method="post">
      <input type="hidden" name="action" value="update_client">
      <div class="prx-modal-field">
        <label>Nom complet</label>
        <input type="text" name="client_name" value="<?= $e(trim(($iv['lastname']??'').' '.($iv['firstname']??''))) ?>">
      </div>
      <div class="prx-modal-field">
        <label>Téléphone</label>
        <input type="tel" name="client_phone" value="<?= $e($iv['client_phone'] ?? '') ?>">
      </div>
      <div class="prx-modal-field">
        <label>Adresse</label>
        <input type="text" name="client_address" value="<?= $e($iv['client_address'] ?? '') ?>">
      </div>
      <div class="prx-modal-field">
        <label>Ville</label>
        <input type="text" name="client_city" value="<?= $e($iv['client_city'] ?? '') ?>">
      </div>
      <div class="prx-modal-field">
        <label>Code postal</label>
        <input type="text" name="client_postal" value="<?= $e($iv['client_postal'] ?? '') ?>">
      </div>
      <button type="submit" class="t-btn t-btn-primary" style="margin-top:.5rem;">💾 Enregistrer</button>
      <button type="button" class="t-btn t-btn-outline" style="margin-top:.5rem;" onclick="closeModal('modal-client')">Annuler</button>
    </form>
  </div>
</div>

<!-- ── Modal Financier ── -->
<div class="prx-modal-overlay" id="modal-financial">
  <div class="prx-modal">
    <div class="prx-modal-title">💶 Modifier le financier</div>
    <form method="post">
      <input type="hidden" name="action" value="update_financial">
      <div class="prx-modal-field">
        <label>Montant HT (€)</label>
        <input type="number" name="amount_ht" step="0.01" value="<?= $e($iv['amount_ht'] ?? '') ?>">
      </div>
      <div class="prx-modal-field">
        <label>Montant TTC (€)</label>
        <input type="number" name="amount_ttc" step="0.01" value="<?= $e($iv['amount_ttc'] ?? '') ?>">
      </div>
      <div class="prx-modal-field">
        <label>Acompte (€)</label>
        <input type="number" name="deposit" step="0.01" value="<?= $e($iv['deposit'] ?? '') ?>">
      </div>
      <div class="prx-modal-field">
        <label>Mode de règlement</label>
        <select name="payment_method">
          <option value="">— Choisir —</option>
          <?php foreach (['Chèque','Virement','Espèces','Carte bancaire','Prélèvement'] as $pm): ?>
            <option value="<?= $e($pm) ?>" <?= ($iv['payment_method']??'')===$pm?'selected':'' ?>><?= $e($pm) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <button type="submit" class="t-btn t-btn-primary" style="margin-top:.5rem;">💾 Enregistrer</button>
      <button type="button" class="t-btn t-btn-outline" style="margin-top:.5rem;" onclick="closeModal('modal-financial')">Annuler</button>
    </form>
  </div>
</div>

<script>
function switchTab(tab) {
  document.querySelectorAll('.tab-content').forEach(function(el){ el.classList.remove('active'); });
  document.querySelectorAll('.prx-tab').forEach(function(el){ el.classList.remove('active'); });
  document.getElementById('tab-'+tab).classList.add('active');
  var idx = tab === 'details' ? 0 : 1;
  document.querySelectorAll('.prx-tab')[idx].classList.add('active');
  history.replaceState(null,'','?id=<?= $id ?>&tab='+tab);
}

function toggleSection(hd) {
  hd.classList.toggle('closed');
  var body = hd.nextElementSibling;
  body.classList.toggle('hidden');
}

function setToggle(field, val, btn) {
  var inp = document.getElementById('inp-'+field);
  var btns = btn.closest('.cr-toggle').querySelectorAll('.cr-toggle-btn');
  btns.forEach(function(b){ b.classList.remove('active-yes','active-no'); });
  if (inp.value === val) { inp.value = ''; return; }
  inp.value = val;
  btn.classList.add(val === '1' ? 'active-yes' : 'active-no');
}

function saveReport() {
  switchTab('cr');
  document.getElementById('form-cr').submit();
}

function submitComplete() {
  if (!confirm('Confirmer la clôture de cette intervention ?')) return;
  switchTab('cr');
  var form = document.getElementById('form-cr');
  if (!form) return;
  var inp = document.createElement('input');
  inp.type='hidden'; inp.name='mark_complete'; inp.value='1';
  form.appendChild(inp);
  form.submit();
}

function updatePhotoLabel(input) {
  var lbl = document.getElementById('photo-label');
  if (input.files && input.files.length > 0)
    lbl.textContent = input.files.length + ' photo(s) sélectionnée(s)';
}

function openModal(id) {
  document.getElementById(id).classList.add('open');
  document.body.style.overflow = 'hidden';
}
function closeModal(id) {
  document.getElementById(id).classList.remove('open');
  document.body.style.overflow = '';
}
document.querySelectorAll('.prx-modal-overlay').forEach(function(overlay){
  overlay.addEventListener('click', function(e){
    if (e.target === overlay) closeModal(overlay.id);
  });
});

function copyText(text) {
  if (navigator.clipboard) { navigator.clipboard.writeText(text); }
  else { var ta=document.createElement('textarea'); ta.value=text; document.body.appendChild(ta); ta.select(); document.execCommand('copy'); document.body.removeChild(ta); }
}

// Auto-scroll to top on tab switch
window.addEventListener('load', function(){
  if (window.location.search.indexOf('saved=1') > -1) {
    window.scrollTo(0,0);
  }
});
</script>
</body>
</html>
