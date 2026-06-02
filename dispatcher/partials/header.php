<?php
declare(strict_types=1);
require_once __DIR__.'/../../includes/bootstrap.php';
$disp = require_dispatcher_auth();
$dispCurrent = basename($_SERVER['PHP_SELF'] ?? '');
$dispSection = $dispSection ?? '';
function disp_is_active(array $files, string $section = ''): string {
    global $dispCurrent, $dispSection;
    if (in_array($dispCurrent, $files, true)) return 'is-active';
    if ($section !== '' && $dispSection === $section) return 'is-active';
    return '';
}
// Count urgent interventions for badge
try { $urgentCount = (int)(db_fetch("SELECT COUNT(*) AS c FROM interventions WHERE urgency=1 AND status NOT IN ('terminé','annulé','payé')")['c']??0); } catch(Throwable $e) { $urgentCount=0; }
try { $waitingCount = (int)(db_fetch("SELECT COUNT(*) AS c FROM interventions WHERE status IN ('nouveau','confirmé')")['c']??0); } catch(Throwable $e) { $waitingCount=0; }
$tasksBadge = dispatcher_pending_tasks_count();
?><!DOCTYPE html>
<html lang="fr"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<meta name="robots" content="noindex,nofollow">
<title><?= e($pageTitle ?? 'Dispatcher') ?> — <?= e(company_name()) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800;900&family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= e(asset_url('assets/css/dispatcher.css')) ?>">
<?= $extraHead ?? '' ?>
</head>
<body class="d-body">
<div class="d-shell">
<!-- SIDEBAR -->
<aside class="d-sidebar" id="d-sidebar">
  <div class="d-sidebar-brand">
    <?php $logo=site_logo_path(); if(trim($logo)!==''&&file_exists(__DIR__.'/../../'.$logo)): ?>
      <a href="<?= e(url_for('dispatcher/index.php')) ?>"><img src="<?= e(asset_url($logo)) ?>" alt="<?= e(company_name()) ?>" style="max-width:140px;height:auto;display:block;"></a>
    <?php else: ?>
      <a href="<?= e(url_for('dispatcher/index.php')) ?>" style="text-decoration:none;">
        <div class="logo-text">EM<span>AE</span></div>
      </a>
    <?php endif; ?>
    <div class="d-sidebar-version">Dispatcher v15</div>
  </div>
  <div class="d-sidebar-user">
    <div class="avatar"><?= e(mb_strtoupper(mb_substr((string)($disp['name']??'?'),0,1,'UTF-8'),'UTF-8')) ?></div>
    <div>
      <div class="user-name"><?= e($disp['name']) ?></div>
      <div class="user-role">Dispatcher</div>
    </div>
  </div>
  <nav class="d-nav">
    <div class="d-nav-group">Vue d'ensemble</div>
    <a class="d-nav-item <?= disp_is_active(['index.php'],'dashboard') ?>" href="<?= e(url_for('dispatcher/index.php')) ?>">
      <span class="nav-ico">🏠</span> Dashboard
    </a>

    <div class="d-nav-group">Interventions</div>
    <a class="d-nav-item <?= disp_is_active(['interventions.php'],'interventions') ?>" href="<?= e(url_for('dispatcher/interventions.php')) ?>">
      <span class="nav-ico">📋</span> Toutes les interventions
      <?php if($waitingCount>0):?><span class="d-nav-badge"><?= $waitingCount ?></span><?php endif;?>
    </a>
    <a class="d-nav-item <?= disp_is_active(['intervention_new.php'],'intervention_new') ?>" href="<?= e(url_for('dispatcher/intervention_new.php')) ?>">
      <span class="nav-ico">➕</span> Nouvelle intervention
    </a>
    <a class="d-nav-item <?= disp_is_active(['calendar.php'],'calendar') ?>" href="<?= e(url_for('dispatcher/calendar.php')) ?>">
      <span class="nav-ico">📅</span> Calendrier
    </a>
    <a class="d-nav-item <?= disp_is_active(['map.php'],'map') ?>" href="<?= e(url_for('dispatcher/map.php')) ?>">
      <span class="nav-ico">🗺️</span> Carte GPS
    </a>

    <div class="d-nav-group">Clients</div>
    <a class="d-nav-item <?= disp_is_active(['clients.php'],'clients') ?>" href="<?= e(url_for('dispatcher/clients.php')) ?>">
      <span class="nav-ico">👥</span> Clients
    </a>

    <div class="d-nav-group">Tâches</div>
    <a class="d-nav-item <?= disp_is_active(['tasks.php'],'tasks') ?>" href="<?= e(url_for('dispatcher/tasks.php')) ?>">
      <span class="nav-ico">📋</span> Tâches &amp; Rappels
      <?php if ($tasksBadge > 0): ?>
        <span class="d-badge"><?= $tasksBadge ?></span>
      <?php endif; ?>
    </a>

    <div class="d-nav-group">Équipe</div>
    <a class="d-nav-item" href="<?= e(url_for('tech/dashboard.php')) ?>" target="_blank">
      <span class="nav-ico">👷</span> Espace Technicien
    </a>
    <?php if($urgentCount>0):?>
    <div style="margin:.5rem 1rem;padding:.65rem .9rem;background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.25);border-radius:8px;font-size:.78rem;color:#ef4444;font-weight:700;">
      🚨 <?= $urgentCount ?> urgence<?= $urgentCount>1?'s':'' ?> active<?= $urgentCount>1?'s':'' ?>
    </div>
    <?php endif;?>

    <div class="d-nav-group">Compte</div>
    <a class="d-nav-item" href="<?= e(url_for('')) ?>" target="_blank"><span class="nav-ico">🌐</span> Voir le site</a>
    <a class="d-nav-item" href="<?= e(url_for('dispatcher/logout.php')) ?>"><span class="nav-ico">🚪</span> Déconnexion</a>
  </nav>
</aside>
<!-- MAIN -->
<div class="d-main">
<?php if($m=flash('success')):?><div class="d-flash d-flash--success" style="margin:1rem 1.75rem 0;"><?=e($m)?></div><?php endif;?>
<?php if($m=flash('error')):?><div class="d-flash d-flash--error" style="margin:1rem 1.75rem 0;"><?=e($m)?></div><?php endif;?>
