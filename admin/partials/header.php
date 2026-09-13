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
        <img src="<?= e(asset_url($logo)) ?>" alt="<?= e(company_name()) ?>" style="max-width:160px;height:auto;">
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
    <div class="admin-menu__group-label">Vue d'ensemble</div>
    <a class="<?= admin_is_active(['index.php']) ?>" href="<?= e(url_for('admin/index.php')) ?>">🏠 Dashboard</a>

    <div class="admin-menu__group-label">Identité</div>
    <a class="<?= admin_is_active(['site_identity.php']) ?>" href="<?= e(url_for('admin/site_identity.php')) ?>">🏢 Identité & coordonnées</a>
    <a class="<?= admin_is_active(['appearance.php']) ?>" href="<?= e(url_for('admin/appearance.php')) ?>">🎨 Couleurs & polices</a>
    <a class="<?= admin_is_active(['header_menu.php']) ?>" href="<?= e(url_for('admin/header_menu.php')) ?>">🧭 Header & menu</a>

    <div class="admin-menu__group-label">Accueil</div>
    <a class="<?= admin_is_active(['home_hero.php']) ?>" href="<?= e(url_for('admin/home_hero.php')) ?>">⭐ Accueil complet</a>
    <a class="<?= admin_is_active(['home_services.php']) ?>" href="<?= e(url_for('admin/home_services.php')) ?>">🔧 Cartes services</a>
    <a class="<?= admin_is_active(['why_us.php'],'why_us') ?>" href="<?= e(url_for('admin/why_us.php')) ?>">⭐ Pourquoi nous choisir</a>

    <div class="admin-menu__group-label">Contenus</div>
    <a class="<?= admin_is_active(['pages.php','page_edit.php']) ?>" href="<?= e(url_for('admin/pages.php')) ?>">📄 Pages</a>
    <a class="<?= admin_is_active(['realisations.php']) ?>" href="<?= e(url_for('admin/realisations.php')) ?>">📷 Réalisations</a>
    <a class="<?= admin_is_active(['reviews.php']) ?>" href="<?= e(url_for('admin/reviews.php')) ?>">⭐ Avis clients</a>
    <a class="<?= admin_is_active(['faq_contact.php'],'faq_contact') ?>" href="<?= e(url_for('admin/faq_contact.php')) ?>">❓ FAQ & Contact</a>

    <div class="admin-menu__group-label">Pages spéciales</div>
    <a class="<?= admin_is_active(['zones.php'],'zones') ?>" href="<?= e(url_for('admin/zones.php')) ?>">🗺️ Zones d'intervention</a>
    <a class="<?= admin_is_active(['services_hero_images.php'],'services_hero_images') ?>" href="<?= e(url_for('admin/services_hero_images.php')) ?>">🖼️ Images hero services</a>

    <div class="admin-menu__group-label">Multi-zones</div>
    <a class="<?= admin_is_active(['zones_manager.php','zone_edit.php'],'zones_manager') ?>" href="<?= e(url_for('admin/zones_manager.php')) ?>">🗺️ Zones géographiques</a>

    <div class="admin-menu__group-label">Leads & Interventions</div>
    <a class="<?= admin_is_active(['quotes.php','dossier.php']) ?>" href="<?= e(url_for('admin/quotes.php')) ?>">📋 Demandes & Interventions</a>

    <div class="admin-menu__group-label">Équipe</div>
    <a class="<?= admin_is_active(['technicians.php'],'technicians') ?>" href="<?= e(url_for('admin/technicians.php')) ?>">👷 Techniciens</a>

    <div class="admin-menu__group-label">Dispatchers</div>
    <a class="<?= admin_is_active(['dispatchers.php'],'dispatchers') ?>" href="<?= e(url_for('admin/dispatchers.php')) ?>">🗂️ Dispatchers</a>
    <a href="<?= e(url_for('dispatcher/index.php')) ?>" target="_blank">🚀 Espace Dispatcher</a>

    <div class="admin-menu__group-label">Notifications</div>
    <a class="<?= admin_is_active(['sms.php'],'sms') ?>" href="<?= e(url_for('admin/sms.php')) ?>">📱 SMS — OVH</a>

    <div class="admin-menu__group-label">Marketing</div>
    <a class="<?= admin_is_active(['seo.php']) ?>" href="<?= e(url_for('admin/seo.php')) ?>">🔍 SEO & Google Ads</a>
    <a class="<?= admin_is_active(['design.php'],'design') ?>" href="<?= e(url_for('admin/design.php')) ?>">🎨 Design & Couleurs</a>
    <a class="<?= admin_is_active(['gallery.php']) ?>" href="<?= e(url_for('admin/gallery.php')) ?>">🖼️ Galerie médias</a>
    <a class="<?= admin_is_active(['chatbot.php'],'chatbot') ?>" href="<?= e(url_for('admin/chatbot.php')) ?>">🤖 Chatbot IA</a>

    <div class="admin-menu__group-label">Compte</div>
    <a class="<?= admin_is_active(['profile.php']) ?>" href="<?= e(url_for('admin/profile.php')) ?>">👤 Mon profil</a>
    <a class="<?= admin_is_active(['mail_test.php']) ?>" href="<?= e(url_for('admin/mail_test.php')) ?>">📧 Test email</a>
    <a href="<?= e(route_url('')) ?>" target="_blank">🌐 Voir le site</a>
    <a href="<?= e(url_for('admin/logout.php')) ?>">🚪 Déconnexion</a>
  </nav>
</aside>
<main class="admin-main">
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