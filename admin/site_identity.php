<?php
$adminSection = 'site_identity';
require __DIR__ . '/partials/header.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    foreach ([
        'company_name','company_phone','company_phone_link','company_email','company_regions','company_hours','company_address','company_siret',
        'company_whatsapp','cancellation_fee','form_email_to','site_logo_width','site_logo_height','site_logo_position'
    ] as $field) {
        set_setting($field, trim((string) ($_POST[$field] ?? '')));
    }

    // Mentions légales et engagements publiés : réglages globaux (identiques sur toutes les zones).
    $legalKeys = ['ml_denomination','ml_forme','ml_capital','ml_siege','ml_siren','ml_rcs','ml_tva','ml_directeur','ml_base','ml_mediateur','ml_assurance',
                  'callback_delay','intervention_terms_text','hours_text'];
    if (isset($_POST['legal_sent'])) {
        $changed = false;
        foreach ($legalKeys as $k) {
            $v = mb_substr(trim((string)($_POST[$k] ?? '')), 0, 500);
            $cur = $k === 'callback_delay' ? callback_delay() : global_setting($k, '');
            if ($v !== $cur || ($k === 'callback_delay' && !array_key_exists('callback_delay', settings_cache()))) { growth_save($k, $v); $changed = $changed || str_starts_with($k, 'ml_'); }
        }
        if ($changed) growth_save('ml_updated_at', date('Y-m-d'));
    }

    $logo = upload_image_field('site_logo_file', 'logos');
    if ($logo) {
        set_setting('site_logo', $logo);
    }

    flash('success', 'Identité et coordonnées enregistrées.');
    redirect_to('admin/site_identity.php');
}
?>
<div class="admin-page-toolbar">
  <div><div class="admin-breadcrumb">Identité</div><h1 class="admin-page-title">Identité & coordonnées</h1><p class="admin-page-subtitle">Tout ce qui s'affiche dans la topbar, le footer et le logo.</p></div>
</div>

<form method="post" enctype="multipart/form-data" class="admin-stack">
<input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
<section class="admin-panel">
  <div class="admin-panel__head"><h2>Coordonnées</h2><p>Téléphone, email, zones et horaires.</p></div>
  <div class="admin-panel__body">
    <div class="admin-form-grid admin-form-grid--2">
      <label class="admin-field"><span>Nom entreprise</span><input type="text" name="company_name" value="<?= e(company_name()) ?>"></label>
      <label class="admin-field"><span>Téléphone</span><input type="text" name="company_phone" value="<?= e(company_phone()) ?>"></label>
    </div>
    <div class="admin-form-grid admin-form-grid--2">
      <label class="admin-field"><span>Lien téléphone</span><input type="text" name="company_phone_link" value="<?= e(company_phone_link()) ?>"></label>
      <label class="admin-field"><span>Email affiché sur le site</span><input type="email" name="company_email" value="<?= e(company_email()) ?>"></label>
    </div>
    <div class="admin-form-grid admin-form-grid--2">
      <label class="admin-field">
        <span>📬 Email de réception des devis <strong style="color:#e07b00;">(notifications)</strong></span>
        <input type="email" name="form_email_to" value="<?= e(setting('form_email_to', company_email())) ?>" placeholder="<?= e(company_email()) ?>">
        <small style="color:#888;font-size:.78rem;margin-top:.3rem;display:block;">Chaque demande de devis sera envoyée à cette adresse. Si vide = même email que ci-dessus.</small>
      </label>
      <div style="background:#fff8ec;border:1px solid #f0c060;border-radius:8px;padding:1rem;font-size:.82rem;color:#7a5200;line-height:1.6;align-self:end;">
        ℹ️ Fonctionne si votre hébergeur autorise <code>mail()</code> PHP. Sur XAMPP local, pas d'envoi réel.
      </div>
    </div>
    <div class="admin-form-grid admin-form-grid--2">
      <label class="admin-field"><span>Zones d'intervention</span><input type="text" name="company_regions" value="<?= e(setting_plain('company_regions')) ?>" placeholder="{region}"></label>
      <label class="admin-field"><span>Horaires</span><input type="text" name="company_hours" value="<?= e(company_hours()) ?>"></label>
    </div>
    <div class="admin-form-grid admin-form-grid--2">
      <label class="admin-field"><span>Adresse postale (facultatif)</span><input type="text" name="company_address" value="<?= e(setting_plain('company_address')) ?>" placeholder="Laisser vide si aucune adresse n'accueille de public"><small style="color:#888;font-size:.78rem;">N'y mettez pas une liste de départements : le siège légal se règle dans « Mentions légales » ci-dessous.</small></label>
      <label class="admin-field"><span>SIRET</span><input type="text" name="company_siret" value="<?= e(company_siret()) ?>"></label>
    </div>
    <div class="admin-form-grid admin-form-grid--2">
      <label class="admin-field">
        <span>💬 WhatsApp (numéro international)</span>
        <input type="text" name="company_whatsapp" value="<?= e(setting('company_whatsapp', '')) ?>" placeholder="Ex : +33612345678">
        <?php $_waP = whatsapp_number_problem(setting('company_whatsapp', '')); if ($_waP !== ''): ?><small style="color:#b91c1c;font-weight:600;display:block;margin-top:.3rem;">⚠️ <?= e($_waP) ?> Le bouton est masqué sur le site tant que le numéro est incomplet.</small><?php endif; unset($_waP); ?>
        <small style="color:#888;font-size:.78rem;margin-top:.3rem;display:block;">Active le bouton WhatsApp flottant sur le site. Laisser vide pour désactiver.</small>
      </label>
      <label class="admin-field">
        <span>💰 Frais d'annulation (montant en €)</span>
        <input type="text" name="cancellation_fee" value="<?= e(setting('cancellation_fee', '')) ?>" placeholder="Ex : 50">
        <small style="color:#888;font-size:.78rem;margin-top:.3rem;display:block;">Montant affiché dans l'email de confirmation d'annulation. Laisser vide pour ne pas préciser le montant.</small>
      </label>
    </div>
  </div>
</section>

<?php $L = legal_info(); $gv = static fn(string $k): string => global_setting($k, ''); ?>
<input type="hidden" name="legal_sent" value="1">
<section class="admin-panel" id="mentions">
  <div class="admin-panel__head"><h2>⚖️ Mentions légales</h2><p>Repris tels quels sur la page Mentions légales, pour tout le site. Vérifiez chaque ligne avec votre Kbis : les valeurs préremplies viennent de l'Annuaire des entreprises (10/11/2026). Un champ vide n'est pas affiché.</p></div>
  <div class="admin-panel__body">
    <div class="admin-form-grid admin-form-grid--2">
      <label class="admin-field"><span>Dénomination sociale</span><input type="text" name="ml_denomination" value="<?= e($L['denomination']) ?>"></label>
      <label class="admin-field"><span>Forme juridique</span><input type="text" name="ml_forme" value="<?= e($L['forme']) ?>" placeholder="SARL, EURL…"></label>
      <label class="admin-field"><span>Capital social</span><input type="text" name="ml_capital" value="<?= e($L['capital']) ?>"></label>
      <label class="admin-field"><span>Siège social (adresse légale)</span><input type="text" name="ml_siege" value="<?= e($L['siege']) ?>"></label>
      <label class="admin-field"><span>SIREN</span><input type="text" name="ml_siren" value="<?= e($L['siren']) ?>"></label>
      <label class="admin-field"><span>Immatriculation (ex : RCS Paris)</span><input type="text" name="ml_rcs" value="<?= e($gv('ml_rcs')) ?>" placeholder="À reprendre du Kbis"></label>
      <label class="admin-field"><span>N° TVA intracommunautaire</span><input type="text" name="ml_tva" value="<?= e($gv('ml_tva')) ?>" placeholder="Vide si non applicable"></label>
      <label class="admin-field"><span>Directeur de la publication (prénom et nom)</span><input type="text" name="ml_directeur" value="<?= e($gv('ml_directeur')) ?>" placeholder="Une personne physique, pas « EMAE »"></label>
      <label class="admin-field"><span>Base d'intervention</span><input type="text" name="ml_base" value="<?= e($L['base']) ?>" placeholder="Ex : Dole (Jura)"></label>
      <label class="admin-field"><span>Médiateur de la consommation (nom et site)</span><input type="text" name="ml_mediateur" value="<?= e($gv('ml_mediateur')) ?>" placeholder="Obligatoire pour les clients particuliers — à renseigner après adhésion"></label>
      <label class="admin-field"><span>Assurance professionnelle (assureur, couverture)</span><input type="text" name="ml_assurance" value="<?= e($gv('ml_assurance')) ?>" placeholder="Ex : décennale — assureur, zone couverte"></label>
    </div>
  </div>
</section>

<section class="admin-panel" id="engagements">
  <div class="admin-panel__head"><h2>🕐 Engagements publiés</h2><p>Une seule valeur pour tout le site (formulaires, accueil, FAQ, pages métier). Ne publiez un délai chiffré que si vous le tenez réellement.</p></div>
  <div class="admin-panel__body">
    <div class="admin-form-grid admin-form-grid--2">
      <label class="admin-field"><span>Délai de rappel annoncé</span><input type="text" name="callback_delay" value="<?= e(callback_delay()) ?>" placeholder="Ex : 5 minutes — vide = « Rappel rapide », sans chiffre"></label>
      <label class="admin-field"><span>Horaires publiés</span><input type="text" name="hours_text" value="<?= e($gv('hours_text')) ?>" placeholder="<?= e(hours_text()) ?>"></label>
    </div>
    <label class="admin-field"><span>Phrase sur la disponibilité et le délai d'arrivée</span><input type="text" name="intervention_terms_text" value="<?= e($gv('intervention_terms_text')) ?>" placeholder="<?= e(intervention_terms_text()) ?>"></label>
    <small style="color:#888;">Les textes du site qui annonceraient une note sans source, « 1000+ », un délai d'arrivée chiffré, « garanti » ou une certification sont remplacés à l'affichage par ces phrases.</small>
  </div>
</section>

<section class="admin-panel">
  <div class="admin-panel__head"><h2>Logo</h2><p>Upload, taille et position.</p></div>
  <div class="admin-panel__body">
    <img class="preview-thumb" src="<?= e(site_logo_url()) ?>" alt="Logo actuel" loading="lazy">
    <div class="admin-form-grid admin-form-grid--2">
      <label class="admin-field"><span>Nouveau logo</span><input type="file" name="site_logo_file" accept=".png,.jpg,.jpeg,.webp,.svg"></label>
      <label class="admin-field"><span>Position logo</span>
        <select name="site_logo_position">
          <?php foreach (['left'=>'Gauche','center'=>'Centre','right'=>'Droite'] as $k=>$v): ?>
            <option value="<?= e($k) ?>" <?= site_logo_position()===$k?'selected':'' ?>><?= e($v) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
    </div>
    <div class="admin-form-grid admin-form-grid--2">
      <label class="admin-field"><span>Largeur logo</span><input type="text" name="site_logo_width" value="<?= e(setting('site_logo_width','180')) ?>"></label>
      <label class="admin-field"><span>Hauteur logo</span><input type="text" name="site_logo_height" value="<?= e(setting('site_logo_height','auto')) ?>"></label>
    </div>
  </div>
</section>
<div class="admin-savebar"><button class="admin-btn admin-btn--primary" type="submit">Enregistrer</button></div>
</form>
<?php require __DIR__ . '/partials/footer.php'; ?>