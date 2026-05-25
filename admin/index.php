<?php
$adminSection='dashboard';
require __DIR__.'/partials/header.php';
$qCount=db_fetch('SELECT COUNT(*) AS c FROM quotes');
$newQ=db_fetch('SELECT COUNT(*) AS c FROM quotes WHERE status=?',['nouveau']);
$rCount=db_fetch('SELECT COUNT(*) AS c FROM reviews WHERE is_visible=1');
$realCount=db_fetch('SELECT COUNT(*) AS c FROM realisations WHERE is_visible=1');
?>
<div class="admin-page-toolbar">
  <div><div class="admin-breadcrumb">Vue d'ensemble</div><h1 class="admin-page-title">Dashboard EMAE V13</h1><p class="admin-page-subtitle">Site optimisé Google Ads — Mobile-first — Prêt à publier</p></div>
  <div class="admin-toolbar-actions">
    <a class="admin-btn admin-btn--secondary" href="<?= e(url_for('admin/home_hero.php')) ?>">Modifier l'accueil</a>
    <a class="admin-btn admin-btn--primary" href="<?= e(route_url('')) ?>" target="_blank">Voir le site</a>
  </div>
</div>
<div class="admin-card-grid" style="grid-template-columns:repeat(4,minmax(0,1fr));">
  <article class="admin-mini-card" style="border-left:4px solid #ee7d1a;">
    <h3 style="color:#ee7d1a;font-size:2rem;"><?= e((string)($newQ['c']??0)) ?></h3>
    <p>Nouveaux leads 🔴</p>
  </article>
  <article class="admin-mini-card"><h3><?= e((string)($qCount['c']??0)) ?></h3><p>Total demandes</p></article>
  <article class="admin-mini-card"><h3><?= e((string)($rCount['c']??0)) ?></h3><p>Avis visibles</p></article>
  <article class="admin-mini-card"><h3><?= e((string)($realCount['c']??0)) ?></h3><p>Réalisations publiées</p></article>
</div>
<div class="admin-stack" style="margin-top:1.5rem;">
<section class="admin-panel"><div class="admin-panel__head"><h2>🚀 Checklist de lancement</h2><p>Complétez ces étapes pour mettre votre site en ligne.</p></div>
<div class="admin-panel__body"><div class="admin-helper-links">
  <a href="<?= e(url_for('admin/site_identity.php')) ?>">① Renseignez vos coordonnées : téléphone, email, SIRET, zones</a>
  <a href="<?= e(url_for('admin/site_identity.php')) ?>">② Uploadez votre logo (PNG transparent recommandé)</a>
  <a href="<?= e(url_for('admin/home_hero.php')) ?>">③ Personnalisez le texte de la page d'accueil</a>
  <a href="<?= e(url_for('admin/home_services.php')) ?>">④ Mettez à jour les cartes services (titres, images, liens)</a>
  <a href="<?= e(url_for('admin/reviews.php')) ?>">⑤ Ajoutez vos vrais avis clients Google</a>
  <a href="<?= e(url_for('admin/realisations.php')) ?>">⑥ Publiez vos premières photos de chantiers</a>
  <a href="<?= e(url_for('admin/seo.php')) ?>">⑦ Configurez Google Analytics 4 et Google Ads (ID de conversion)</a>
  <a href="<?= e(url_for('admin/faq_contact.php')) ?>">⑧ Vérifiez les textes de la FAQ et de la page Contact</a>
</div></div></section>
<section class="admin-panel"><div class="admin-panel__head"><h2>💡 Landing pages Google Ads</h2><p>Envoyez le trafic de vos annonces vers ces URL.</p></div>
<div class="admin-panel__body"><div class="admin-helper-links">
  <a href="<?= e(route_url('electricite')) ?>" target="_blank">⚡ Électricité → <?= e(route_url('electricite')) ?></a>
  <a href="<?= e(route_url('plomberie')) ?>" target="_blank">💧 Plomberie → <?= e(route_url('plomberie')) ?></a>
  <a href="<?= e(route_url('chauffage')) ?>" target="_blank">🔥 Chauffage & PAC → <?= e(route_url('chauffage')) ?></a>
  <a href="<?= e(route_url('climatisation')) ?>" target="_blank">❄️ Climatisation → <?= e(route_url('climatisation')) ?></a>
  <a href="<?= e(route_url('quote')) ?>" target="_blank">📋 Devis gratuit → <?= e(route_url('quote')) ?> (recommandé)</a>
  <a href="<?= e(url_for('sitemap.php')) ?>" target="_blank">🗺️ Sitemap XML → à soumettre à Google Search Console</a>
</div></div></section>
</div>
<?php require __DIR__.'/partials/footer.php'; ?>
