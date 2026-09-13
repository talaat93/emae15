<?php
declare(strict_types=1);
require_once __DIR__.'/../includes/bootstrap.php';
$disp = require_dispatcher_auth();

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { redirect_to('dispatcher/interventions.php'); }

$iv = get_intervention_by_id($id);
if (!$iv) { flash('error', 'Intervention introuvable.'); redirect_to('dispatcher/interventions.php'); }

$photos = [];
if (!empty($iv['tech_photos'])) {
    $p = json_decode((string)$iv['tech_photos'], true);
    if (is_array($p)) $photos = $p;
}

$catConf = intervention_category_config();
$stConf  = intervention_status_config();
$catC    = $catConf[$iv['category'] ?? ''] ?? ['label' => $iv['category'] ?? '—', 'icon' => '🔧', 'color' => '#1a7ab5'];
$stC     = $stConf[$iv['status'] ?? '']   ?? ['label' => $iv['status'] ?? '—', 'color' => '#8fa0c4', 'bg' => 'rgba(143,160,196,.15)'];

$co      = company_name();
$phone   = company_phone();
$email   = company_email();

$clientName = trim(($iv['lastname'] ?? '').' '.($iv['firstname'] ?? ''));
$clientAddr = array_filter([
    $iv['address'] ?? '',
    trim(($iv['postal_code'] ?? '').' '.($iv['client_city'] ?? '')),
]);
$clientAddrStr = implode(', ', $clientAddr);

$logoPath = site_logo_path();
$logoUrl  = $logoPath !== '' ? asset_url($logoPath) : '';

function e_pdf(mixed $v): string {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}
function row(string $label, mixed $value, string $extra = ''): string {
    if ($value === null || $value === '' || $value === '—') return '';
    return '<div class="row'.($extra !== '' ? ' '.$extra : '').'">'
         . '<span class="lbl">'.e_pdf($label).'</span>'
         . '<span class="val">'.e_pdf($value).'</span>'
         . '</div>';
}
function row_raw(string $label, string $html): string {
    return '<div class="row"><span class="lbl">'.e_pdf($label).'</span><span class="val">'.$html.'</span></div>';
}
function check_badge(?int $val, bool $positiveIsGood = true): string {
    if ($val === null) return '<span style="background:#f1f5f9;color:#94a3b8;padding:.15rem .55rem;border-radius:99px;font-size:.72rem;font-weight:700;">N/R</span>';
    $isPos  = (int)$val === 1;
    $isGood = $positiveIsGood ? $isPos : !$isPos;
    $c = $isGood ? ['#dcfce7','#16a34a'] : ['#fee2e2','#dc2626'];
    return '<span style="background:'.$c[0].';color:'.$c[1].';padding:.15rem .55rem;border-radius:99px;font-size:.72rem;font-weight:700;">'.($isPos ? 'Oui ✓' : 'Non').'</span>';
}
?><!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<meta name="robots" content="noindex,nofollow">
<title>Rapport <?= e_pdf($iv['ref'] ?? 'INT-'.$id) ?> — <?= e_pdf($co) ?></title>
<style>
/* ── Reset ── */
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI','Helvetica Neue',Arial,sans-serif;font-size:13px;color:#1e293b;background:#f1f5f9;}
a{color:inherit;text-decoration:none;}

/* ── Wrapper ── */
.wrap{max-width:860px;margin:0 auto;padding:2rem 1.5rem 4rem;}

/* ── Header ── */
.pdf-header{display:flex;justify-content:space-between;align-items:flex-start;padding-bottom:1.5rem;border-bottom:3px solid #F07B1D;margin-bottom:1.75rem;}
.pdf-logo-img{max-height:56px;max-width:180px;object-fit:contain;}
.pdf-logo-text{font-size:2rem;font-weight:900;letter-spacing:.06em;color:#0f172a;}
.pdf-logo-text span{color:#F07B1D;}
.pdf-co-info{font-size:.75rem;color:#64748b;margin-top:.3rem;line-height:1.7;}
.pdf-title-block{text-align:right;}
.pdf-doc-title{font-size:1.15rem;font-weight:800;color:#0f172a;}
.pdf-ref{font-size:1.5rem;font-weight:900;color:#F07B1D;letter-spacing:.04em;margin:.2rem 0;}
.pdf-gen{font-size:.72rem;color:#94a3b8;}
.pdf-status-badge{display:inline-block;padding:.25rem .8rem;border-radius:99px;font-size:.75rem;font-weight:700;border:1px solid;}

/* ── Sections ── */
.section{background:#fff;border-radius:12px;border:1px solid #e2e8f0;overflow:hidden;margin-bottom:1rem;break-inside:avoid;}
.section-head{background:#f8fafc;padding:.6rem 1.1rem;font-size:.65rem;font-weight:800;text-transform:uppercase;letter-spacing:.12em;color:#64748b;border-bottom:1px solid #e2e8f0;display:flex;align-items:center;gap:.5rem;}
.section-head .ico{font-size:.9rem;}
.section-body{padding:.9rem 1.1rem;}

/* ── Rows ── */
.row{display:flex;justify-content:space-between;align-items:flex-start;gap:1rem;padding:.42rem 0;border-bottom:1px solid #f1f5f9;font-size:.88rem;}
.row:last-child{border-bottom:none;}
.lbl{color:#64748b;flex-shrink:0;min-width:140px;}
.val{font-weight:600;text-align:right;word-break:break-word;}
.two-col{display:grid;grid-template-columns:1fr 1fr;gap:1rem;}
.text-block{font-size:.9rem;line-height:1.8;white-space:pre-wrap;color:#1e293b;background:#f8fafc;border-radius:8px;padding:.85rem 1rem;border:1px solid #e2e8f0;}

/* ── Photos ── */
.photos-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:.6rem;margin-top:.5rem;}
.photo-item{aspect-ratio:1;overflow:hidden;border-radius:8px;border:1px solid #e2e8f0;}
.photo-item img{width:100%;height:100%;object-fit:cover;}

/* ── Finance ── */
.fin-row{display:flex;justify-content:space-between;padding:.5rem 0;border-bottom:1px solid #f1f5f9;font-size:.9rem;}
.fin-row:last-child{border-bottom:none;font-weight:700;font-size:1rem;color:#0f172a;padding-top:.65rem;}
.fin-label{color:#64748b;}
.fin-value{font-weight:600;}

/* ── Signature ── */
.sig-grid{display:grid;grid-template-columns:1fr 1fr;gap:2rem;margin-top:1rem;}
.sig-box{border:1px solid #e2e8f0;border-radius:10px;padding:1.1rem;min-height:100px;}
.sig-label{font-size:.65rem;font-weight:800;text-transform:uppercase;letter-spacing:.1em;color:#94a3b8;margin-bottom:.65rem;}
.sig-name{font-size:.9rem;font-weight:700;color:#0f172a;margin-bottom:.25rem;}
.sig-date{font-size:.75rem;color:#64748b;}
.sig-canvas{border:1px dashed #cbd5e1;border-radius:6px;height:60px;margin-top:.5rem;background:#fafafa;}

/* ── Print button ── */
.print-btn{position:fixed;bottom:1.5rem;right:1.5rem;z-index:100;background:#F07B1D;color:#fff;border:none;border-radius:12px;padding:.85rem 1.65rem;font-size:.92rem;font-weight:700;cursor:pointer;box-shadow:0 4px 20px rgba(240,123,29,.45);font-family:inherit;display:flex;align-items:center;gap:.5rem;}
.print-btn:hover{background:#d96a10;}
.back-link{display:inline-flex;align-items:center;gap:.35rem;color:#2563eb;font-weight:600;font-size:.85rem;margin-bottom:1.25rem;text-decoration:none;}
.back-link:hover{text-decoration:underline;}
.urgency-badge{display:inline-block;background:#fef2f2;color:#dc2626;border:1px solid #fecaca;border-radius:99px;padding:.2rem .7rem;font-size:.75rem;font-weight:800;letter-spacing:.04em;}

/* ── Print ── */
@media print{
  .print-btn,.back-link,.no-print{display:none!important;}
  body{background:#fff;}
  .wrap{padding:.5rem;max-width:100%;}
  .section{border:1px solid #ddd;page-break-inside:avoid;}
  .pdf-header{padding-bottom:1rem;margin-bottom:1rem;}
}
</style>
</head>
<body>
<div class="wrap">

  <a href="<?= e_pdf(url_for('dispatcher/intervention_view.php?id='.$id)) ?>" class="back-link no-print">‹ Retour à la fiche</a>

  <!-- ── Header ── -->
  <div class="pdf-header">
    <div>
      <?php if ($logoUrl !== '' && file_exists(__DIR__.'/../'.$logoPath)): ?>
        <img src="<?= e_pdf($logoUrl) ?>" alt="<?= e_pdf($co) ?>" class="pdf-logo-img">
      <?php else: ?>
        <div class="pdf-logo-text">EM<span>AE</span></div>
      <?php endif; ?>
      <div class="pdf-co-info">
        <?= e_pdf($co) ?><br>
        <?php if ($phone !== ''): ?>📞 <?= e_pdf($phone) ?><br><?php endif; ?>
        <?php if ($email !== ''): ?>✉️ <?= e_pdf($email) ?><?php endif; ?>
      </div>
    </div>
    <div class="pdf-title-block">
      <div class="pdf-doc-title">Rapport d'intervention</div>
      <div class="pdf-ref"><?= e_pdf($iv['ref'] ?? 'INT-'.$id) ?></div>
      <div class="pdf-gen">Généré le <?= e_pdf(date('d/m/Y à H:i')) ?></div>
      <div style="margin-top:.5rem;">
        <span class="pdf-status-badge" style="color:<?= e_pdf($stC['color']) ?>;background:<?= e_pdf($stC['bg']) ?>;border-color:<?= e_pdf($stC['color']) ?>55;">
          <?= e_pdf($stC['label'] ?? $iv['status']) ?>
        </span>
        <?php if (!empty($iv['urgency'])): ?> <span class="urgency-badge">🚨 URGENT</span><?php endif; ?>
      </div>
      <?php if (!empty($iv['tech_completed_at'])): ?>
      <div style="font-size:.72rem;color:#16a34a;font-weight:700;margin-top:.35rem;">✅ Clôturé le <?= e_pdf(date('d/m/Y à H:i', strtotime($iv['tech_completed_at']))) ?></div>
      <?php endif; ?>
    </div>
  </div>

  <!-- ── Client ── -->
  <div class="section">
    <div class="section-head"><span class="ico">👤</span> Client</div>
    <div class="section-body">
      <div class="two-col">
        <div>
          <?= row('Nom', $clientName) ?>
          <?= row('Téléphone', $iv['client_phone'] ?? '') ?>
          <?= row('Email', $iv['client_email'] ?? '') ?>
        </div>
        <div>
          <?= row('Adresse', $iv['address'] ?? '') ?>
          <?= row('Code postal / Ville', trim(($iv['postal_code']??'').' '.($iv['client_city']??''))) ?>
          <?= row('Étage / Bât.', $iv['floor'] ?? '') ?>
          <?= row('Digicode', $iv['digicode'] ?? '') ?>
          <?= row('Infos accès', $iv['access_info'] ?? '') ?>
        </div>
      </div>
    </div>
  </div>

  <!-- ── Intervention ── -->
  <div class="section">
    <div class="section-head"><span class="ico">⚙️</span> Intervention</div>
    <div class="section-body">
      <div class="two-col">
        <div>
          <?= row('Référence', $iv['ref'] ?? 'INT-'.$id) ?>
          <?= row('Catégorie', $catC['icon'].' '.$catC['label']) ?>
          <?= row('Type', $iv['type_label'] ?? '') ?>
          <?= row('Installation', $iv['installation_type'] ?? '') ?>
        </div>
        <div>
          <?php $schedDate = !empty($iv['scheduled_date']) ? date('d/m/Y', strtotime($iv['scheduled_date'])) : ''; ?>
          <?php $schedTime = !empty($iv['scheduled_time']) ? date('H:i', strtotime($iv['scheduled_time'])) : ''; ?>
          <?= row('Date planifiée', trim($schedDate.' '.$schedTime)) ?>
          <?= row('Durée estimée', $iv['duration_estimate'] ? $iv['duration_estimate'].' min' : '') ?>
          <?= row('Technicien', $iv['tech_name'] ?? '') ?>
          <?= row('Dispatcher', $iv['disp_name'] ?? '') ?>
          <?= row('Priorité', $iv['priority'] ?? '') ?>
          <?= row('Urgence', !empty($iv['urgency']) ? '🚨 Oui' : 'Non') ?>
        </div>
      </div>
    </div>
  </div>

  <!-- ── Description technique ── -->
  <?php if (!empty($iv['fault_reported']) || !empty($iv['description']) || !empty($iv['materials_needed'])): ?>
  <div class="section">
    <div class="section-head"><span class="ico">📋</span> Description technique</div>
    <div class="section-body">
      <?php if (!empty($iv['fault_reported'])): ?>
      <div style="margin-bottom:.75rem;">
        <div style="font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:#64748b;margin-bottom:.35rem;">Panne signalée</div>
        <div class="text-block"><?= e_pdf($iv['fault_reported']) ?></div>
      </div>
      <?php endif; ?>
      <?php if (!empty($iv['description'])): ?>
      <div style="margin-bottom:.75rem;">
        <div style="font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:#64748b;margin-bottom:.35rem;">Description</div>
        <div class="text-block"><?= e_pdf($iv['description']) ?></div>
      </div>
      <?php endif; ?>
      <?php if (!empty($iv['materials_needed'])): ?>
      <div>
        <div style="font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:#64748b;margin-bottom:.35rem;">Matériaux nécessaires</div>
        <div class="text-block"><?= e_pdf($iv['materials_needed']) ?></div>
      </div>
      <?php endif; ?>
    </div>
  </div>
  <?php endif; ?>

  <!-- ── Compte rendu technicien ── -->
  <div class="section">
    <div class="section-head"><span class="ico">🔧</span> Compte rendu technicien</div>
    <div class="section-body">
      <?php if (!empty($iv['tech_report'])): ?>
      <div style="margin-bottom:.85rem;">
        <div style="font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:#64748b;margin-bottom:.35rem;">Rapport d'intervention</div>
        <div class="text-block"><?= e_pdf($iv['tech_report']) ?></div>
      </div>
      <?php else: ?>
      <div style="color:#94a3b8;font-style:italic;font-size:.88rem;margin-bottom:.75rem;">Aucun rapport texte saisi.</div>
      <?php endif; ?>

      <div class="two-col">
        <div>
          <?= row('Matériaux utilisés', $iv['tech_materials_used'] ?? '') ?>
          <?= row('Temps passé', $iv['tech_time_spent'] ? $iv['tech_time_spent'].' min' : '') ?>
        </div>
        <div>
          <div class="row">
            <span class="lbl">Intervention réalisable</span>
            <span class="val"><?= check_badge(!empty($iv['tech_report']) ? 1 : null, true) ?></span>
          </div>
          <?php if (!empty($iv['tech_started_at'])): ?>
          <?= row('Départ', date('d/m/Y H:i', strtotime($iv['tech_started_at']))) ?>
          <?php endif; ?>
          <?php if (!empty($iv['tech_arrived_at'])): ?>
          <?= row('Arrivée sur place', date('d/m/Y H:i', strtotime($iv['tech_arrived_at']))) ?>
          <?php endif; ?>
          <?php if (!empty($iv['tech_completed_at'])): ?>
          <?= row('Clôturé le', date('d/m/Y H:i', strtotime($iv['tech_completed_at']))) ?>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  <!-- ── Photos ── -->
  <?php if (!empty($photos)): ?>
  <div class="section">
    <div class="section-head"><span class="ico">📸</span> Photos (<?= count($photos) ?>)</div>
    <div class="section-body">
      <div class="photos-grid">
        <?php foreach ($photos as $ph): ?>
          <div class="photo-item">
            <img src="<?= e_pdf(asset_url($ph)) ?>" alt="Photo intervention" loading="lazy">
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <!-- ── Financier ── -->
  <?php if (!empty($iv['amount_ht']) || !empty($iv['amount_ttc']) || !empty($iv['deposit']) || !empty($iv['payment_method'])): ?>
  <div class="section">
    <div class="section-head"><span class="ico">💶</span> Financier</div>
    <div class="section-body">
      <div style="max-width:380px;margin-left:auto;">
        <?php if (!empty($iv['amount_ht'])): ?>
        <div class="fin-row"><span class="fin-label">Montant HT</span><span class="fin-value"><?= e_pdf(number_format((float)$iv['amount_ht'], 2, ',', ' ')) ?> €</span></div>
        <?php endif; ?>
        <?php if (!empty($iv['amount_ttc'])): ?>
        <div class="fin-row"><span class="fin-label">Montant TTC (TVA 20%)</span><span class="fin-value"><?= e_pdf(number_format((float)$iv['amount_ttc'], 2, ',', ' ')) ?> €</span></div>
        <?php endif; ?>
        <?php if (!empty($iv['deposit'])): ?>
        <div class="fin-row"><span class="fin-label">Acompte versé</span><span class="fin-value">— <?= e_pdf(number_format((float)$iv['deposit'], 2, ',', ' ')) ?> €</span></div>
        <?php endif; ?>
        <?php if (!empty($iv['remaining'])): ?>
        <div class="fin-row"><span class="fin-label">Reste à régler</span><span class="fin-value"><?= e_pdf(number_format((float)$iv['remaining'], 2, ',', ' ')) ?> €</span></div>
        <?php endif; ?>
        <?php if (!empty($iv['payment_method'])): ?>
        <div class="fin-row" style="border-top:1px solid #e2e8f0;margin-top:.35rem;padding-top:.5rem;font-size:.85rem;">
          <span class="fin-label">Mode de paiement</span>
          <span class="fin-value"><?= e_pdf($iv['payment_method']) ?></span>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <!-- ── Notes admin ── -->
  <?php if (!empty($iv['notes_admin'])): ?>
  <div class="section">
    <div class="section-head"><span class="ico">📌</span> Notes internes</div>
    <div class="section-body">
      <div class="text-block"><?= e_pdf($iv['notes_admin']) ?></div>
    </div>
  </div>
  <?php endif; ?>

  <!-- ── Signatures ── -->
  <div class="section">
    <div class="section-head"><span class="ico">✍️</span> Signatures</div>
    <div class="section-body">
      <div class="sig-grid">
        <div class="sig-box">
          <div class="sig-label">Signature technicien</div>
          <?php if (!empty($iv['tech_name'])): ?>
          <div class="sig-name"><?= e_pdf($iv['tech_name']) ?></div>
          <?php endif; ?>
          <?php if (!empty($iv['tech_completed_at'])): ?>
          <div class="sig-date"><?= e_pdf(date('d/m/Y', strtotime($iv['tech_completed_at']))) ?></div>
          <?php endif; ?>
          <?php if (!empty($iv['tech_signature'])): ?>
            <img src="<?= e_pdf($iv['tech_signature']) ?>" alt="Signature technicien" style="max-width:100%;max-height:60px;margin-top:.5rem;">
          <?php else: ?>
            <div class="sig-canvas"></div>
          <?php endif; ?>
        </div>
        <div class="sig-box">
          <div class="sig-label">Signature client</div>
          <?php if (!empty($iv['tech_client_name'])): ?>
          <div class="sig-name"><?= e_pdf($iv['tech_client_name']) ?></div>
          <?php else: ?>
          <div class="sig-name"><?= e_pdf($clientName) ?></div>
          <?php endif; ?>
          <div class="sig-canvas"></div>
        </div>
      </div>
    </div>
  </div>

  <!-- Footer légal -->
  <div style="text-align:center;padding:1.5rem 0 0;font-size:.7rem;color:#94a3b8;border-top:1px solid #e2e8f0;margin-top:1.5rem;">
    <?= e_pdf($co) ?>
    <?php if (company_siret() !== ''): ?> — SIRET : <?= e_pdf(company_siret()) ?><?php endif; ?>
    <?php if ($phone !== ''): ?> — <?= e_pdf($phone) ?><?php endif; ?>
    <?php if ($email !== ''): ?> — <?= e_pdf($email) ?><?php endif; ?>
    <br>Document généré automatiquement le <?= e_pdf(date('d/m/Y à H:i')) ?>
  </div>

</div>

<button class="print-btn no-print" onclick="window.print()">🖨️ Imprimer / PDF</button>

</body>
</html>
