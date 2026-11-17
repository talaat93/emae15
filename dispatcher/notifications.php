<?php
declare(strict_types=1);
/** Notifications du dispatcher : les évènements qui demandent une action (includes/disp_notify.php). */
$pageTitle   = 'Notifications';
$listFilter  = ['items' => '.notif-item', 'groups' => '.none'];
$dispSection = 'notifications';
require __DIR__.'/partials/header.php';

// Ouvrir une notification : marquée lue, puis renvoi vers la fiche concernée.
if (isset($_GET['go'])) {
    $n = db_fetch('SELECT id, url FROM disp_notifications WHERE id = ?', [(int)$_GET['go']]);
    if ($n) { disp_notif_mark_read((int)$n['id']); redirect_to(disp_notif_safe_url((string)$n['url'])); }
    redirect_to('dispatcher/notifications.php');
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    if (($_POST['action'] ?? '') === 'all_read') { disp_notif_mark_read(); flash('success', 'Toutes les notifications sont marquées comme lues.'); }
    if (($_POST['action'] ?? '') === 'read' && (int)($_POST['id'] ?? 0) > 0) disp_notif_mark_read((int)$_POST['id']);
    redirect_to('dispatcher/notifications.php');
}

$items = disp_notif_list(150);
$kinds = disp_notif_kinds();
$unread = count(array_filter($items, static fn($n) => $n['read_at'] === null));
$ago = static function (string $dt): string {
    $s = time() - strtotime($dt);
    if ($s < 60) return 'à l\'instant';
    if ($s < 3600) return 'il y a '.intdiv($s, 60).' min';
    if ($s < 86400) return 'il y a '.intdiv($s, 3600).' h';
    return date('d/m à H:i', strtotime($dt));
};
?>
<style>
.notif-item { display:flex; gap:.8rem; align-items:flex-start; padding:.8rem 1rem; border-bottom:1px solid var(--d-border); text-decoration:none; color:inherit; }
.notif-item:last-child { border-bottom:0; }
.notif-item:hover { background:var(--d-card-2); }
.notif-item.is-unread { background:#fff8f1; }
.notif-dot { width:10px; height:10px; border-radius:50%; margin-top:.35rem; flex-shrink:0; }
.notif-kind { font-size:.72rem; font-weight:700; text-transform:uppercase; letter-spacing:.04em; }
.notif-title { font-weight:600; font-size:.92rem; color:var(--d-t1); margin-top:.1rem; }
.notif-body { font-size:.84rem; color:var(--d-t2); margin-top:.15rem; }
.notif-when { font-size:.76rem; color:var(--d-t3); white-space:nowrap; margin-left:auto; padding-left:.6rem; }
</style>
<div class="d-topbar">
  <div>
    <button class="d-menu-toggle" id="d-menu-toggle" aria-label="Menu">☰</button>
    <div>
      <div class="d-topbar-title">Notifications</div>
      <div class="d-topbar-sub"><?= $unread ? $unread.' non lue'.($unread > 1 ? 's' : '') : 'Tout est lu' ?></div>
    </div>
  </div>
  <?php if ($unread): ?>
  <div class="d-topbar-actions">
    <form method="post" style="margin:0;"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="all_read">
      <button type="submit" class="d-btn d-btn--sm">Tout marquer comme lu</button></form>
  </div>
  <?php endif; ?>
</div>
<div class="d-content">
  <?php if ($m = flash('success')): ?><div class="d-flash"><?= e($m) ?></div><?php endif; ?>
  <div class="d-card">
    <?php if (!$items): ?>
      <div class="d-empty" style="padding:2rem;">Aucune notification pour l'instant. Vous serez prévenu ici des devis à faire, rapports à valider, refus, retards, demandes d'aide, réponses aux devis et paiements.</div>
    <?php endif; ?>
    <?php foreach ($items as $n): $k = $kinds[$n['kind']] ?? ['label' => $n['kind'], 'color' => '#8c99ad']; ?>
      <a class="notif-item<?= $n['read_at'] === null ? ' is-unread' : '' ?>" href="<?= e(url_for('dispatcher/notifications.php?go='.(int)$n['id'])) ?>">
        <span class="notif-dot" style="background:<?= $n['read_at'] === null ? e($k['color']) : 'var(--d-border)' ?>;"></span>
        <span style="min-width:0;">
          <span class="notif-kind" style="color:<?= e($k['color']) ?>;"><?= e($k['label']) ?></span>
          <span class="notif-title" style="display:block;"><?= e((string)$n['title']) ?></span>
          <?php if (!empty($n['body'])): ?><span class="notif-body" style="display:block;"><?= e((string)$n['body']) ?></span><?php endif; ?>
        </span>
        <span class="notif-when"><?= e($ago((string)$n['created_at'])) ?></span>
      </a>
    <?php endforeach; ?>
  </div>
</div>
<?php require __DIR__.'/partials/footer.php'; ?>
