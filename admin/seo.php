<?php
// Les champs affichent le texte tel qu'enregistré (setting_plain) : avec setting(),
// une variable comme {en_region} apparaîtrait déjà remplacée et l'enregistrement
// la figerait pour toutes les zones.
$adminSection = 'seo';
require __DIR__ . '/partials/header.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    foreach (['home_meta_title','home_meta_description','faq_meta_title','faq_meta_description','contact_meta_title','contact_meta_description','quote_meta_title','quote_meta_description','reals_meta_title','reals_meta_description','google_analytics_id','google_ads_id','google_ads_conversion_label','schema_rating_value','schema_review_count','stat_1_number','stat_1_label','stat_2_number','stat_2_label','stat_3_number','stat_3_label','stat_4_number','stat_4_label'] as $f)
        set_setting($f, trim((string)($_POST[$f] ?? '')));
    flash('success','SEO, Google Ads et statistiques enregistrés.');
    redirect_to('admin/seo.php');
}
?>
<div class="admin-page-toolbar"><div><div class="admin-breadcrumb">Marketing</div><h1 class="admin-page-title">SEO & Google Ads</h1><p class="admin-page-subtitle">Configurez vos balises SEO, Google Analytics 4, Google Ads et les statistiques du site.</p></div></div>
<form method="post" class="admin-stack"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
<section class="admin-panel"><div class="admin-panel__head"><h2>🎯 Google Ads & Analytics</h2></div><div class="admin-panel__body admin-form-grid admin-form-grid--2">
<label class="admin-field"><span>Google Analytics 4 ID (G-XXXXXXXXXX)</span><input type="text" name="google_analytics_id" value="<?= e(setting_plain('google_analytics_id','')) ?>" placeholder="G-XXXXXXXXXX"></label>
<label class="admin-field"><span>Google Ads ID (AW-XXXXXXXXXX)</span><input type="text" name="google_ads_id" value="<?= e(setting_plain('google_ads_id','')) ?>" placeholder="AW-XXXXXXXXXX"></label>
<label class="admin-field"><span>Label de conversion Google Ads</span><input type="text" name="google_ads_conversion_label" value="<?= e(setting_plain('google_ads_conversion_label','')) ?>"></label>
</div></section>

<section class="admin-panel"><div class="admin-panel__head"><h2>📊 Statistiques bandeau urgence</h2></div><div class="admin-panel__body"><div class="admin-form-grid admin-form-grid--2">
<?php for($i=1;$i<=4;$i++): ?>
<label class="admin-field"><span>Chiffre <?=$i?></span><input type="text" name="stat_<?=$i?>_number" value="<?= e(setting_plain('stat_'.$i.'_number',['','500+','4.9/5','< 2h','24/7'][$i]??'')) ?>"></label>
<label class="admin-field"><span>Libellé <?=$i?></span><input type="text" name="stat_<?=$i?>_label" value="<?= e(setting_plain('stat_'.$i.'_label',['','Interventions/an','Note client','Délai urgence','Disponibilité'][$i]??'')) ?>"></label>
<?php endfor; ?>
</div></div></section>

<section class="admin-panel"><div class="admin-panel__head"><h2>🔍 SEO par page</h2></div><div class="admin-panel__body">
<div class="admin-form-grid admin-form-grid--2" style="margin-bottom:1rem;">
<label class="admin-field"><span>Meta title Accueil</span><input type="text" name="home_meta_title" value="<?= e(setting_plain('home_meta_title','')) ?>"></label>
<label class="admin-field"><span>Meta description Accueil</span><input type="text" name="home_meta_description" value="<?= e(setting_plain('home_meta_description','')) ?>"></label>
</div>
<div class="admin-form-grid admin-form-grid--2" style="margin-bottom:1rem;">
<label class="admin-field"><span>Meta title FAQ</span><input type="text" name="faq_meta_title" value="<?= e(setting_plain('faq_meta_title','')) ?>"></label>
<label class="admin-field"><span>Meta description FAQ</span><input type="text" name="faq_meta_description" value="<?= e(setting_plain('faq_meta_description','')) ?>"></label>
</div>
<div class="admin-form-grid admin-form-grid--2" style="margin-bottom:1rem;">
<label class="admin-field"><span>Meta title Contact</span><input type="text" name="contact_meta_title" value="<?= e(setting_plain('contact_meta_title','')) ?>"></label>
<label class="admin-field"><span>Meta description Contact</span><input type="text" name="contact_meta_description" value="<?= e(setting_plain('contact_meta_description','')) ?>"></label>
</div>
<div class="admin-form-grid admin-form-grid--2" style="margin-bottom:1rem;">
<label class="admin-field"><span>Meta title Devis</span><input type="text" name="quote_meta_title" value="<?= e(setting_plain('quote_meta_title','')) ?>"></label>
<label class="admin-field"><span>Meta description Devis</span><input type="text" name="quote_meta_description" value="<?= e(setting_plain('quote_meta_description','')) ?>"></label>
</div>
<div class="admin-form-grid admin-form-grid--2">
<label class="admin-field"><span>Meta title Réalisations</span><input type="text" name="reals_meta_title" value="<?= e(setting_plain('reals_meta_title','')) ?>"></label>
<label class="admin-field"><span>Meta description Réalisations</span><input type="text" name="reals_meta_description" value="<?= e(setting_plain('reals_meta_description','')) ?>"></label>
</div>
</div></section>

<section class="admin-panel"><div class="admin-panel__head"><h2>⭐ Schema.org (étoiles Google)</h2></div><div class="admin-panel__body admin-form-grid admin-form-grid--2">
<label class="admin-field"><span>Note moyenne (ex: 4.9)</span><input type="text" name="schema_rating_value" value="<?= e(setting_plain('schema_rating_value','4.9')) ?>"></label>
<label class="admin-field"><span>Nombre d'avis (ex: 120)</span><input type="text" name="schema_review_count" value="<?= e(setting_plain('schema_review_count','120')) ?>"></label>
</div></section>

<div class="admin-savebar"><button class="admin-btn admin-btn--primary" type="submit">Enregistrer</button></div>
</form>
<?php require __DIR__ . '/partials/footer.php'; ?>
