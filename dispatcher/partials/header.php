<?php
declare(strict_types=1);
require_once __DIR__.'/../../includes/bootstrap.php';
// Les pages traitent leurs formulaires après avoir inclus cet en-tête, puis
// redirigent : la sortie est mise en tampon pour que la redirection reste possible.
ob_start();
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
try { $urgentCount = (int)(db_fetch("SELECT COUNT(*) AS c FROM interventions WHERE urgency=1 AND status NOT IN (".wf_sql_list(wf_closed()).")")['c']??0); } catch(Throwable $e) { $urgentCount=0; }
try { $waitingCount = (int)(db_fetch("SELECT COUNT(*) AS c FROM interventions WHERE status IN ('nouveau','confirmé')")['c']??0); } catch(Throwable $e) { $waitingCount=0; }
$tasksBadge = dispatcher_pending_tasks_count();

/** Choix des photos à demander au technicien (cases à cocher + saisie libre). */
function disp_photo_request_field(array $selected): string
{
    $presets = array_map(static fn($p) => (string)$p['label'], get_presets('photo_type'));
    $others  = array_values(array_diff($selected, $presets));
    $h = '<div class="d-field"><label>Photos à demander au technicien</label><div class="d-chips">';
    foreach ($presets as $p) {
        $h .= '<label class="d-chip"><input type="checkbox" name="photos_required[]" value="'.e($p).'"'.(in_array($p, $selected, true) ? ' checked' : '').'><span>'.e($p).'</span></label>';
    }
    $h .= '</div><input type="text" name="photos_required_other" value="'.e(implode(', ', $others)).'" placeholder="Autres photos, séparées par des virgules (ex. : compteur, plaque chaudière)" style="margin-top:.5rem;">'
        . '<div style="font-size:.78rem;color:var(--d-t2);margin-top:.3rem;">Le technicien ne pourra pas clôturer sans ces photos.</div></div>';
    return $h;
}

/** Lecture du champ ci-dessus : JSON prêt à enregistrer (null si aucune photo demandée). */
function disp_photo_request_value(): ?string
{
    $list = array_map('trim', (array)($_POST['photos_required'] ?? []));
    foreach (explode(',', (string)($_POST['photos_required_other'] ?? '')) as $o) $list[] = trim($o);
    $list = array_values(array_unique(array_filter($list, static fn($v) => $v !== '' && mb_strlen($v) <= 120)));
    return $list ? json_encode($list, JSON_UNESCAPED_UNICODE) : null;
}
?><!DOCTYPE html>
<html lang="fr"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<meta name="robots" content="noindex,nofollow">
<title><?= e($pageTitle ?? 'Dispatcher') ?> — <?= e(company_name()) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="preload" as="style" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" onload="this.onload=null;this.rel='stylesheet'">
<noscript><link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap"></noscript>
<link rel="icon" href="<?= e(asset_url('assets/img/icon-192.png')) ?>">
<link rel="stylesheet" href="<?= e(asset_url('assets/css/dispatcher.css')) ?>">
<?= $extraHead ?? '' ?>
</head>
<body class="d-body">
<div class="d-shell">
<!-- SIDEBAR -->
<aside class="d-sidebar" id="d-sidebar">
  <div class="d-sidebar-brand">
    <a href="<?= e(url_for('dispatcher/index.php')) ?>"><img src="<?= e(asset_url('assets/img/logo-emae-clair.png')) ?>" alt="<?= e(company_name()) ?>" class="d-logo"></a>
    <div class="d-sidebar-version">Planification</div>
  </div>
  <div class="d-sidebar-cta">
    <a class="d-btn d-btn--primary" href="<?= e(url_for('dispatcher/intervention_new.php')) ?>">+ Nouvelle intervention</a>
  </div>
  <nav class="d-nav">
    <a class="d-nav-item <?= disp_is_active(['index.php'],'dashboard') ?>" href="<?= e(url_for('dispatcher/index.php')) ?>">
      <span class="nav-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 10.5 12 3l9 7.5V20a1 1 0 0 1-1 1h-5v-6h-6v6H4a1 1 0 0 1-1-1z"/></svg></span> Aujourd'hui<?php if($urgentCount>0):?><span class="d-nav-badge red" title="Urgences"><?= $urgentCount ?></span><?php endif;?>
    </a>
    <a class="d-nav-item <?= disp_is_active(['interventions.php','intervention_view.php','intervention_new.php'],'interventions') ?>" href="<?= e(url_for('dispatcher/interventions.php')) ?>">
      <span class="nav-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M8 6h13M8 12h13M8 18h13"/><circle cx="3.5" cy="6" r="1"/><circle cx="3.5" cy="12" r="1"/><circle cx="3.5" cy="18" r="1"/></svg></span> Interventions<?php if($waitingCount>0):?><span class="d-nav-badge" title="À traiter"><?= $waitingCount ?></span><?php endif;?>
    </a>
    <a class="d-nav-item <?= disp_is_active(['calendar.php'],'calendar') ?>" href="<?= e(url_for('dispatcher/calendar.php')) ?>">
      <span class="nav-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4.5" width="18" height="16.5" rx="2"/><path d="M16 2.5v4M8 2.5v4M3 10h18"/></svg></span> Planning
    </a>
    <a class="d-nav-item <?= disp_is_active(['map.php'],'map') ?>" href="<?= e(url_for('dispatcher/map.php')) ?>">
      <span class="nav-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M9 4 3 6.5v13.5l6-2.5 6 2.5 6-2.5V4l-6 2.5z"/><path d="M9 4v13.5M15 6.5V20"/></svg></span> Carte
    </a>
    <a class="d-nav-item <?= disp_is_active(['clients.php','client_view.php'],'clients') ?>" href="<?= e(url_for('dispatcher/clients.php')) ?>">
      <span class="nav-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="8" r="3.5"/><path d="M2.5 20c.6-3.3 3.3-5.5 6.5-5.5s5.9 2.2 6.5 5.5"/><path d="M16 4.8a3.5 3.5 0 0 1 0 6.4M18 14.8c1.8.8 3.1 2.7 3.5 5.2"/></svg></span> Clients
    </a>
    <a class="d-nav-item <?= disp_is_active(['tasks.php'],'tasks') ?>" href="<?= e(url_for('dispatcher/tasks.php')) ?>">
      <span class="nav-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3.5" y="3.5" width="17" height="17" rx="2"/><path d="m8 12 3 3 5-6"/></svg></span> Tâches<?php if ($tasksBadge > 0): ?><span class="d-nav-badge"><?= $tasksBadge ?></span><?php endif; ?>
    </a>
    <div class="d-nav-group">Réglages</div>
    <a class="d-nav-item <?= disp_is_active(['presets.php'],'presets') ?>" href="<?= e(url_for('dispatcher/presets.php')) ?>">
      <span class="nav-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.8-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1.1-1.5 1.7 1.7 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.8 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.5-1.1 1.7 1.7 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.8.3H9a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.8V9a1.7 1.7 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1z"/></svg></span> Listes prédéfinies
    </a>
    <a class="d-nav-item <?= disp_is_active(['settings.php'],'settings') ?>" href="<?= e(url_for('dispatcher/settings.php')) ?>">
      <span class="nav-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M6 8a6 6 0 1 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.9 1.9 0 0 0 3.4 0"/></svg></span> Réglages
    </a>
    <a class="d-nav-item" href="<?= e(url_for('')) ?>" target="_blank" rel="noopener">
      <span class="nav-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a14 14 0 0 1 0 18M12 3a14 14 0 0 0 0 18"/></svg></span> Voir le site
    </a>
  </nav>
  <div class="d-sidebar-user">
    <div class="avatar"><?= e(mb_strtoupper(mb_substr((string)($disp['name']??'?'),0,1,'UTF-8'),'UTF-8')) ?></div>
    <div>
      <div class="user-name"><?= e($disp['name']) ?></div>
      <div class="user-role">Dispatcher</div>
    </div>
    <a class="user-out" href="<?= e(url_for('dispatcher/logout.php')) ?>" title="Déconnexion" aria-label="Déconnexion"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/></svg></a>
  </div>
</aside>
<!-- MAIN -->
<div class="d-main">
<?php if($m=flash('success')):?><div class="d-flash d-flash--success" style="margin:1rem 1.75rem 0;"><?=e($m)?></div><?php endif;?>
<?php if($m=flash('error')):?><div class="d-flash d-flash--error" style="margin:1rem 1.75rem 0;"><?=e($m)?></div><?php endif;?>
