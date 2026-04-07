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
        <div style="font-size:1.4rem;font-weight:900;color:#fff;letter-spacing:.06em;">EM<span style="color:#ee7d1a;">AE</span></div>
      <?php endif; ?>
    </a>
  </div>
  <div class="admin-sidebar-card">
    <div class="admin-sidebar-card__title"><?= e(company_name()) ?></div>
    <div class="admin-sidebar-card__text">V13 — Prêt pour Google Ads</div>
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

    <div class="admin-menu__group-label">Contenus</div>
    <a class="<?= admin_is_active(['pages.php','page_edit.php']) ?>" href="<?= e(url_for('admin/pages.php')) ?>">📄 Pages</a>
    <a class="<?= admin_is_active(['realisations.php']) ?>" href="<?= e(url_for('admin/realisations.php')) ?>">📷 Réalisations</a>
    <a class="<?= admin_is_active(['reviews.php']) ?>" href="<?= e(url_for('admin/reviews.php')) ?>">⭐ Avis clients</a>
    <a class="<?= admin_is_active(['faq_contact.php'],'faq_contact') ?>" href="<?= e(url_for('admin/faq_contact.php')) ?>">❓ FAQ & Contact</a>

    <div class="admin-menu__group-label">Leads</div>
    <a class="<?= admin_is_active(['quotes.php']) ?>" href="<?= e(url_for('admin/quotes.php')) ?>">📋 Demandes de devis</a>

    <div class="admin-menu__group-label">Marketing</div>
    <a class="<?= admin_is_active(['seo.php']) ?>" href="<?= e(url_for('admin/seo.php')) ?>">🔍 SEO & Google Ads</a>
    <a class="<?= admin_is_active(['gallery.php']) ?>" href="<?= e(url_for('admin/gallery.php')) ?>">🖼️ Galerie médias</a>

    <div class="admin-menu__group-label">Compte</div>
    <a class="<?= admin_is_active(['profile.php']) ?>" href="<?= e(url_for('admin/profile.php')) ?>">👤 Mon profil</a>
    <a href="<?= e(route_url('')) ?>" target="_blank">🌐 Voir le site</a>
    <a href="<?= e(url_for('admin/logout.php')) ?>">🚪 Déconnexion</a>
  </nav>
</aside>
<main class="admin-main">
<?php if($m=flash('success')):?><div class="flash flash--success"><?=e($m)?></div><?php endif;?>
<?php if($m=flash('error')):?><div class="flash flash--error"><?=e($m)?></div><?php endif;?>
