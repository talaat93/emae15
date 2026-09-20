<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/render.php';
require_admin();
$adminCurrent = basename($_SERVER['PHP_SELF'] ?? '');
$adminSection = $adminSection ?? '';
function admin_is_active(array $files, string $section = ''): string {
    global $adminCurrent, $adminSection;
    if (in_array($adminCurrent, $files, true)) return 'is-active';
    if ($section !== '' && $adminSection === $section) return 'is-active';
    return '';
}
// Conserve la page courante et ses paramètres en changeant seulement de zone.
function admin_zone_url(int $zoneId): string {
    $q = $_GET; unset($q['admin_zone']); $q['admin_zone'] = $zoneId;
    $file = basename((string)($_SERVER['SCRIPT_NAME'] ?? 'index.php'));
    return url_for('admin/'.$file.'?'.http_build_query($q));
}
?><!DOCTYPE html>
<html lang="fr"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Admin EMAE V13</title>
<link rel="stylesheet" href="<?= e(asset_url('assets/css/admin.css')) ?>">
<script defer src="<?= e(asset_url('assets/js/admin.js')) ?>"></script>
</head><body class="admin-body">
<div class="admin-shell">
<aside class="admin-sidebar">
  <div class="admin-brand-wrap">
    <a href="<?= e(url_for('admin/index.php')) ?>" style="display:block;padding:.5rem;">
      <?php $logo=site_logo_path(); if(trim($logo)!==''&&file_exists(__DIR__.'/../../'.$logo)): ?>
        <img src="<?= e(asset_url($logo)) ?>" alt="<?= e(company_name()) ?>" style="max-width:160px;height:auto;" loading="lazy">
      <?php else: ?>
        <div style="font-family:'Syne',Arial,sans-serif;font-size:1.5rem;font-weight:800;color:#fff;letter-spacing:.05em;">EM<span style="color:#F07B1D;">AE</span></div>
      <?php endif; ?>
    </a>
  </div>
  <div class="admin-sidebar-card">
    <div class="admin-sidebar-card__title"><?= e(company_name()) ?></div>
    <div class="admin-sidebar-card__text">V15 — Premium</div>
  </div>
  <nav class="admin-menu">
    <?php
    // Le menu se construit depuis le registre des écrans : un compte standard
    // ne voit que ceux que le compte principal lui a ouverts.
    foreach (admin_screens() as $groupe => $ecrans):
        $visibles = array_filter(array_keys($ecrans), 'admin_can_access');
        if (!$visibles) continue; ?>
      <div class="admin-menu__group-label"><?= e($groupe) ?></div>
      <?php foreach ($visibles as $fichier): ?>
        <?php if ($fichier === 'page_content.php'):
                require_once __DIR__.'/../../includes/admin_fields.php';
                foreach (admin_page_catalog() as $cpId => $cp): ?>
          <span class="admin-menu__row">
            <a class="<?= admin_is_active([],'content_'.$cpId) ?>" href="<?= e(url_for('admin/page_content.php?p='.$cpId)) ?>"><?= e($cp['icon'].' '.$cp['label']) ?></a>
            <a class="admin-menu__pencil" href="<?= e(admin_visual_url($cpId)) ?>" title="Modifier directement sur la page">✏️</a>
          </span>
        <?php endforeach; else: ?>
          <a class="<?= admin_is_active([$fichier]) ?>" href="<?= e(url_for('admin/'.$fichier)) ?>"><?= e(admin_screen_label($fichier)) ?></a>
        <?php endif; ?>
      <?php endforeach; ?>
    <?php endforeach; ?>

    <?php if (admin_is_super()): ?>
      <div class="admin-menu__group-label">Administration</div>
      <a class="<?= admin_is_active(['admins.php']) ?>" href="<?= e(url_for('admin/admins.php')) ?>">👥 Comptes administrateurs</a>
      <a class="<?= admin_is_active(['activity.php']) ?>" href="<?= e(url_for('admin/activity.php')) ?>">📜 Journal d'activité</a>
    <?php endif; ?>

    <?php if (admin_can_access('dispatchers.php')): ?>
      <a href="<?= e(url_for('dispatcher/index.php')) ?>" target="_blank">🚀 Espace Dispatcher</a>
    <?php endif; ?>
    <a href="<?= e(route_url('')) ?>" target="_blank">🌐 Voir le site</a>
    <style>
    .admin-menu__row{display:flex;align-items:stretch;gap:2px;}
    .admin-menu__row > a:first-child{flex:1;min-width:0;}
    .admin-menu__pencil{flex:0 0 auto;display:flex;align-items:center;justify-content:center;
      width:38px;opacity:.45;text-decoration:none;border-radius:8px;}
    .admin-menu__pencil:hover{opacity:1;background:rgba(255,255,255,.12);}
    </style>
    <a href="<?= e(url_for('admin/logout.php')) ?>">🚪 Déconnexion</a>
  </nav>
</aside>
<main class="admin-main">
<form class="admin-search" method="get" action="<?= e(url_for('admin/search.php')) ?>" role="search">
  <input type="text" name="q" value="<?= e((string)($_GET['q'] ?? '')) ?>" placeholder="🔎 Chercher un texte du site — ex : un seul interlocuteur">
  <button type="submit">Chercher</button>
</form>
<style>
.admin-search{display:flex;gap:.5rem;margin-bottom:1.1rem;}
.admin-search input{flex:1;min-width:0;padding:.6rem .85rem;border:1px solid #dde5f3;border-radius:12px;font-size:.9rem;background:#fff;}
.admin-search input:focus{outline:2px solid #2f66d2;outline-offset:1px;}
.admin-search button{border:1px solid #dde5f3;background:#f7faff;color:#4b5b7d;border-radius:12px;padding:0 .95rem;font-weight:600;font-size:.85rem;cursor:pointer;white-space:nowrap;}
.admin-search button:hover{background:#eaf1ff;color:#1b2d6b;}
</style>
<?php
$zsList = all_zones();
$zsCur  = zone_context();
if (!empty($zsList)):
  $zsOv = $zsCur ? zone_override_count((string)$zsCur['slug']) : 0;
?>
<div class="zonebar<?= $zsCur ? ' zonebar--zone' : '' ?>">
  <div class="zonebar__head">
    <span class="zonebar__eyebrow">Vous modifiez</span>
    <strong class="zonebar__now"><?= $zsCur ? '📍 '.e($zsCur['name']) : '🌐 Site global' ?></strong>
    <?php if ($zsCur): ?>
      <span class="zonebar__badge"><?= $zsOv ?> champ<?= $zsOv > 1 ? 's' : '' ?> personnalisé<?= $zsOv > 1 ? 's' : '' ?></span>
    <?php else: ?>
      <span class="zonebar__hint">Les valeurs saisies ici servent de base à toutes les zones.</span>
    <?php endif; ?>
  </div>
  <div class="zonebar__chips">
    <a class="zonechip<?= $zsCur ? '' : ' is-on' ?>" href="<?= e(admin_zone_url(0)) ?>">🌐 Global</a>
    <?php foreach ($zsList as $zsZ): ?>
      <a class="zonechip<?= $zsCur && (int)$zsCur['id'] === (int)$zsZ['id'] ? ' is-on' : '' ?><?= (bool)$zsZ['status'] ? '' : ' is-off' ?>"
         href="<?= e(admin_zone_url((int)$zsZ['id'])) ?>"><?= e($zsZ['name']) ?></a>
    <?php endforeach; ?>
  </div>
  <?php if ($zsCur): ?>
  <div class="zonebar__tools">
    <span class="zonebar__note">Un champ laissé vide hérite automatiquement du site global.</span>
    <form method="post" action="<?= e(url_for('admin/zone_context.php')) ?>" style="display:inline;"
          onsubmit="return confirm('Copier toutes les valeurs du site global dans cette zone ?');">
      <?= csrf_field() ?><input type="hidden" name="action" value="copy">
      <button type="submit" class="zonebar__btn">⧉ Dupliquer depuis Global</button>
    </form>
    <form method="post" action="<?= e(url_for('admin/zone_context.php')) ?>" style="display:inline;"
          onsubmit="return confirm('Supprimer les <?= $zsOv ?> personnalisations de cette zone ? Elle héritera de nouveau entièrement du site global.');">
      <?= csrf_field() ?><input type="hidden" name="action" value="reset">
      <button type="submit" class="zonebar__btn zonebar__btn--danger">↺ Tout réinitialiser</button>
    </form>
    <a class="zonebar__btn" href="<?= e(url_for($zsCur['slug'].'/')) ?>" target="_blank">↗ Voir la page</a>
  </div>
  <?php endif; ?>
</div>
<style>
.zonebar{background:#fff;border:1px solid #dde5f3;border-left:5px solid #6b7a99;border-radius:14px;padding:.9rem 1.1rem;margin-bottom:1.5rem;}
.zonebar--zone{border-left-color:#F07B1D;background:#fffaf4;}
.zonebar__head{display:flex;align-items:center;gap:.7rem;flex-wrap:wrap;}
.zonebar__eyebrow{font-size:.7rem;letter-spacing:.09em;text-transform:uppercase;color:#8494b4;font-weight:700;}
.zonebar__now{font-size:1.05rem;color:#1b2d6b;}
.zonebar__badge{background:#F07B1D;color:#fff;border-radius:20px;padding:.15rem .6rem;font-size:.72rem;font-weight:700;}
.zonebar__hint,.zonebar__note{font-size:.78rem;color:#7b88a6;}
.zonebar__chips{display:flex;flex-wrap:wrap;gap:.4rem;margin-top:.7rem;}
.zonechip{padding:.35rem .8rem;border-radius:20px;border:1px solid #dde5f3;background:#f7faff;color:#4b5b7d;font-size:.82rem;font-weight:600;text-decoration:none;transition:all .15s;}
.zonechip:hover{background:#eaf1ff;color:#1b2d6b;}
.zonechip.is-on{background:linear-gradient(135deg,#2f66d2,#1e4fa8);border-color:transparent;color:#fff;}
.zonechip.is-off{opacity:.5;}
.zonechip.is-off.is-on{opacity:1;background:linear-gradient(135deg,#8a94a8,#6b7a99);}
.zonebar__tools{display:flex;flex-wrap:wrap;align-items:center;gap:.5rem;margin-top:.75rem;padding-top:.7rem;border-top:1px dashed #e6d6c2;}
.zonebar__note{margin-right:auto;}
.zonebar__btn{background:#fff;border:1px solid #dde5f3;border-radius:10px;padding:.35rem .7rem;font-size:.78rem;font-weight:600;color:#4b5b7d;cursor:pointer;text-decoration:none;display:inline-block;}
.zonebar__btn:hover{background:#f0f4ff;color:#1b2d6b;}
.zonebar__btn--danger{color:#b91c1c;border-color:#f0c9c9;}
.zonebar__btn--danger:hover{background:#fef2f2;color:#991b1b;}
</style>
<?php endif; ?>
<?php if($m=flash('success')):?><div class="flash flash--success"><?=e($m)?></div><?php endif;?>
<?php if($m=flash('error')):?><div class="flash flash--error"><?=e($m)?></div><?php endif;?>