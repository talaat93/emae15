<?php
/**
 * Conversion & avis : bandeau de disponibilité, note Google, WhatsApp, SMS
 * automatiques, alertes, rendez-vous, suivi, espace client, automatisations.
 * Tous ces réglages valent pour le site entier (zones comprises).
 */
$adminSection = 'growth';
require __DIR__ . '/partials/header.php';

$d = growth_defaults();
$fields = array_keys($d);
$toggles = ['avail_enabled', 'auto_sms_client_enabled', 'review_request_enabled', 'alert_unanswered_enabled', 'track_enabled', 'booking_enabled'];
$fields = array_values(array_diff($fields, ['partial_enabled']));   // collecte sans clic retirée (voir partial_enabled())

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $act = (string)($_POST['act'] ?? 'save');
    if ($act === 'run') {
        $res = automations_run('admin', true);
        flash('success', 'Tâches lancées : '.implode(' · ', array_map(static fn($k, $v) => $k.' : '.(is_string($v) ? $v : json_encode($v)), array_keys($res), $res)));
        redirect_to('admin/growth.php#auto');
    }
    foreach ($fields as $k) {
        if (in_array($k, $toggles, true)) { growth_save($k, !empty($_POST[$k]) ? '1' : '0'); continue; }
        if (!array_key_exists($k, $_POST)) continue;
        $v = trim((string)$_POST[$k]);
        if ($k === 'avail_days') $v = implode(',', array_map('intval', (array)($_POST['avail_days_list'] ?? [])));
        if (in_array($k, ['avail_start', 'avail_end'], true) && !preg_match('/^\d{2}:\d{2}$/', $v)) $v = $d[$k];
        if (in_array($k, ['auto_sms_daily_cap', 'review_request_delay_h', 'alert_unanswered_minutes', 'quote_photos_keep_months', 'booking_capacity', 'booking_days_ahead'], true)) $v = (string)max(0, (int)$v);
        if ($k === 'google_rating') $v = str_replace(',', '.', $v);
        if ($k === 'google_reviews_url' && $v !== '' && !preg_match('#^https://#i', $v)) { flash('error', 'Le lien Google doit commencer par https://'); $v = ''; }
        growth_save($k, $v);
    }
    // avail_days arrive en cases à cocher (aucune case = tableau absent)
    growth_save('avail_days', implode(',', array_map('intval', (array)($_POST['avail_days_list'] ?? []))));
    if (isset($_POST['booking_weekdays_sent'])) {
        $bw = array_values(array_filter(array_map('intval', (array)($_POST['booking_weekdays_list'] ?? [])), static fn(int $n): bool => $n >= 1 && $n <= 7));
        growth_save('booking_weekdays', $bw ? implode(',', $bw) : '1,2,3,4,5,6');
    }
    growth_save('client_space_enabled', !empty($_POST['client_space_enabled']) ? '1' : '0');
    if (trim((string)($_POST['google_places_api_key'] ?? '')) !== '') set_integration_secret('google_places_api_key', trim((string)$_POST['google_places_api_key']));
    if (!empty($_POST['google_places_api_key_clear'])) set_integration_secret('google_places_api_key', '');
    if ($act === 'google') {
        $r = google_reviews_refresh();
        flash($r['ok'] ? 'success' : 'error', $r['ok'] ? 'Réglages enregistrés. Avis Google relevés : '.$r['rating'].'/5, '.$r['total'].' avis.' : 'Réglages enregistrés, mais les avis n\'ont pas pu être relevés : '.$r['error']);
        redirect_to('admin/growth.php#google');
    }
    flash('success', 'Réglages enregistrés.');
    redirect_to('admin/growth.php');
}

$g = static fn(string $k): string => growth_get($k);
$chk = static fn(string $k): string => growth_on($k) ? 'checked' : '';
$wa = company_whatsapp();
$waProblem = whatsapp_number_problem($wa);
$days = array_map('intval', array_filter(explode(',', $g('avail_days')), 'strlen'));
$now = availability_now();
$smsOk = sms_configured();
?>
<div class="admin-page-toolbar">
  <div>
    <div class="admin-breadcrumb">Croissance</div>
    <h1 class="admin-page-title">Conversion & avis</h1>
    <p class="admin-page-subtitle">Les outils qui transforment les visites en appels, et les clients satisfaits en avis Google. Réglages valables pour tout le site.</p>
  </div>
</div>

<?php if (!$smsOk): ?>
<div style="background:#fff7ed;border:1px solid #fed7aa;border-radius:10px;padding:1rem 1.25rem;margin-bottom:1.25rem;font-size:.9rem;color:#9a3412;line-height:1.6;">
  <strong>📱 Le compte SMS n'est pas configuré.</strong> Les SMS automatiques (accusé de réception, demande d'avis, rappels d'entretien) partiront dès que vous aurez saisi vos identifiants OVH dans
  <a href="<?= e(url_for('admin/sms.php')) ?>" style="color:#9a3412;font-weight:700;">Notifications SMS</a>. En attendant, les e-mails fonctionnent.
</div>
<?php endif; ?>

<form method="post" class="admin-stack">
<input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

<section class="admin-panel" id="dispo">
  <div class="admin-panel__head"><h2>🟢 Bandeau « disponible maintenant »</h2><p>Bandeau cliquable tout en haut du site. Pendant vos horaires : message « disponible » ; en dehors : message pour les urgences. En ce moment, le site affiche : <strong><?= e($now['text']) ?></strong></p></div>
  <div class="admin-panel__body">
    <label class="admin-field admin-field--check"><div class="check-row"><input type="checkbox" name="avail_enabled" value="1" <?= $chk('avail_enabled') ?>> Afficher le bandeau</div></label>
    <div class="admin-field"><span>Jours d'ouverture</span><div class="check-row" style="flex-wrap:wrap;gap:.9rem;">
      <?php foreach ([1 => 'Lun', 2 => 'Mar', 3 => 'Mer', 4 => 'Jeu', 5 => 'Ven', 6 => 'Sam', 7 => 'Dim'] as $n => $lab): ?>
        <label style="display:inline-flex;gap:.3rem;align-items:center;"><input type="checkbox" name="avail_days_list[]" value="<?= $n ?>" <?= in_array($n, $days, true) ? 'checked' : '' ?>> <?= $lab ?></label>
      <?php endforeach; ?>
    </div></div>
    <div class="admin-form-grid admin-form-grid--2">
      <label class="admin-field"><span>Début</span><input type="time" name="avail_start" value="<?= e($g('avail_start')) ?>"></label>
      <label class="admin-field"><span>Fin</span><input type="time" name="avail_end" value="<?= e($g('avail_end')) ?>"></label>
    </div>
    <label class="admin-field"><span>Message pendant les horaires</span><input type="text" name="avail_open_text" value="<?= e($g('avail_open_text')) ?>"></label>
    <label class="admin-field"><span>Message en dehors des horaires</span><input type="text" name="avail_closed_text" value="<?= e($g('avail_closed_text')) ?>"></label>
  </div>
</section>

<section class="admin-panel" id="google">
  <div class="admin-panel__head"><h2>⭐ Note Google</h2><p>Affichée sous les boutons d'appel (accueil, pages métier, nouvelles pages) <strong>seulement quand le lien, la note et le nombre d'avis sont remplis</strong> : jamais de chiffre inventé.</p></div>
  <div class="admin-panel__body">
    <label class="admin-field"><span>Lien vers vos avis Google</span><input type="url" name="google_reviews_url" value="<?= e($g('google_reviews_url')) ?>" placeholder="https://g.page/r/…/review">
      <small style="color:#888;">Dans votre fiche Google Business Profile : « Demander des avis » → copier le lien. Ce même lien sert aux demandes d'avis automatiques.</small></label>
    <div class="admin-form-grid admin-form-grid--2">
      <label class="admin-field"><span>Note (ex : 4,8)</span><input type="text" name="google_rating" value="<?= e($g('google_rating')) ?>" inputmode="decimal"></label>
      <label class="admin-field"><span>Nombre d'avis</span><input type="number" name="google_review_count" value="<?= e($g('google_review_count')) ?>" min="0"></label>
    </div>
    <details style="margin-top:.5rem;"><summary style="cursor:pointer;font-weight:600;">Mise à jour automatique de la note (facultatif)</summary>
      <p style="font-size:.85rem;color:#555;line-height:1.6;">Avec une clé Google Places API et l'identifiant de votre fiche (« Place ID »), la note, le nombre d'avis et 5 avis (choisis par Google) se mettent à jour une fois par jour et s'affichent sur la page Avis. Pour <strong>tous</strong> les avis : <a href="<?= e(url_for('admin/google.php')) ?>">connexion Google (API Business Profile)</a>. Sans clé, gardez la saisie manuelle ci-dessus.</p>
      <div class="admin-form-grid admin-form-grid--2">
        <label class="admin-field"><span>Place ID</span><input type="text" name="google_place_id" value="<?= e($g('google_place_id')) ?>" placeholder="ChIJ…"></label>
        <label class="admin-field"><span>Clé API Google Places <?= integration_secret_configured('google_places_api_key') ? '— <b style="color:#15803d;">configurée</b>' : '— non configurée' ?></span><input type="password" name="google_places_api_key" value="" autocomplete="new-password" placeholder="Laisser vide pour ne pas changer"></label>
      </div>
      <?php if (integration_secret_configured('google_places_api_key')): ?><label class="check-row"><input type="checkbox" name="google_places_api_key_clear" value="1"> Supprimer la clé</label><?php endif; ?>
      <?php if (global_setting('google_rating_updated_at', '') !== ''): ?><p style="font-size:.82rem;color:#555;">Dernière mise à jour : <?= e(date('d/m/Y H:i', strtotime(global_setting('google_rating_updated_at', '')))) ?></p><?php endif; ?>
      <button class="admin-btn admin-btn--secondary" type="submit" name="act" value="google" formnovalidate>🔄 Relever les avis maintenant</button>
    </details>
  </div>
</section>

<section class="admin-panel" id="avis">
  <div class="admin-panel__head"><h2>💬 Demande d'avis après intervention</h2><p>Quelques heures après une intervention terminée, le client reçoit un SMS (ou, à défaut, un e-mail) avec le lien de vos avis Google. Entre 9 h et 20 h seulement, une seule fois tous les 3 mois par client.</p></div>
  <div class="admin-panel__body">
    <label class="admin-field admin-field--check"><div class="check-row"><input type="checkbox" name="review_request_enabled" value="1" <?= $chk('review_request_enabled') ?>> Envoyer les demandes d'avis</div></label>
    <?php if ($g('google_reviews_url') === ''): ?><p style="color:#b45309;font-size:.85rem;">⚠️ Renseignez d'abord le lien vers vos avis Google (section au-dessus) : sans lui, rien n'est envoyé.</p><?php endif; ?>
    <label class="admin-field" style="max-width:260px;"><span>Délai après la fin de l'intervention (heures)</span><input type="number" name="review_request_delay_h" value="<?= e($g('review_request_delay_h')) ?>" min="1" max="72"></label>
    <label class="admin-field"><span>Texte du SMS</span><textarea name="review_request_text" rows="3"><?= e($g('review_request_text')) ?></textarea>
      <small style="color:#888;">Variables : {entreprise}, {nom} (prénom du client), {lien} (vos avis Google), {telephone}.</small></label>
  </div>
</section>

<section class="admin-panel" id="accuse">
  <div class="admin-panel__head"><h2>📩 SMS automatique après une demande</h2><p>Le client reçoit tout de suite un SMS « demande bien reçue » : il a moins de raisons d'appeler un concurrent en attendant. Mobile seulement, une fois par jour et par numéro.</p></div>
  <div class="admin-panel__body">
    <label class="admin-field admin-field--check"><div class="check-row"><input type="checkbox" name="auto_sms_client_enabled" value="1" <?= $chk('auto_sms_client_enabled') ?>> Envoyer le SMS au client</div></label>
    <label class="admin-field"><span>Texte du SMS</span><textarea name="auto_sms_client_text" rows="3"><?= e($g('auto_sms_client_text')) ?></textarea>
      <small style="color:#888;">Variables : {entreprise}, {nom}, {telephone}. Le SMS « nouvelle demande » pour vous-même se règle dans <a href="<?= e(url_for('admin/sms.php')) ?>">Notifications SMS</a>.</small></label>
    <label class="admin-field" style="max-width:260px;"><span>Maximum par jour (sécurité)</span><input type="number" name="auto_sms_daily_cap" value="<?= e($g('auto_sms_daily_cap')) ?>" min="1"></label>
  </div>
</section>

<section class="admin-panel" id="alerte">
  <div class="admin-panel__head"><h2>⏰ Alerte si une demande reste sans réponse</h2><p>Une demande toujours au statut « nouveau » après ce délai vous est signalée par e-mail (et par SMS sur le mobile d'alerte, si le compte SMS est configuré).</p></div>
  <div class="admin-panel__body">
    <label class="admin-field admin-field--check"><div class="check-row"><input type="checkbox" name="alert_unanswered_enabled" value="1" <?= $chk('alert_unanswered_enabled') ?>> Activer l'alerte</div></label>
    <label class="admin-field" style="max-width:260px;"><span>Délai (minutes)</span><input type="number" name="alert_unanswered_minutes" value="<?= e($g('alert_unanswered_minutes')) ?>" min="5" max="1440"></label>
  </div>
</section>

<section class="admin-panel" id="whatsapp">
  <div class="admin-panel__head"><h2>💬 WhatsApp</h2><p>Le bouton vert en bas à droite du site. Le numéro se règle dans <a href="<?= e(url_for('admin/site_identity.php')) ?>">Identité & coordonnées</a>.</p></div>
  <div class="admin-panel__body">
    <p style="font-size:.9rem;">Numéro actuel : <strong><?= $wa !== '' ? e($wa) : 'aucun (bouton masqué)' ?></strong><?= $wa !== '' && $waProblem === '' ? ' ✅' : '' ?></p>
    <?php if ($waProblem !== ''): ?><p style="color:#b91c1c;font-weight:600;font-size:.9rem;">⚠️ <?= e($waProblem) ?></p><?php endif; ?>
    <label class="admin-field"><span>Message déjà écrit quand le client ouvre WhatsApp</span><input type="text" name="whatsapp_message" value="<?= e($g('whatsapp_message')) ?>"></label>
  </div>
</section>

<section class="admin-panel" id="rdv">
  <div class="admin-panel__head"><h2>📅 Rendez-vous en ligne</h2><p>Page <a href="<?= e(route_url('rendez-vous')) ?>" target="_blank">Prendre rendez-vous</a> : le client choisit un jour et une demi-journée, vous le rappelez pour confirmer l'heure. Les textes se modifient dans <a href="<?= e(url_for('admin/page_content.php?p=page_rdv')) ?>">Textes des pages</a>.</p></div>
  <div class="admin-panel__body">
    <label class="admin-field admin-field--check"><div class="check-row"><input type="checkbox" name="booking_enabled" value="1" <?= $chk('booking_enabled') ?>> Activer la prise de rendez-vous en ligne</div></label>
    <label class="admin-field"><span>Créneaux proposés (un par ligne)</span><textarea name="booking_slots" rows="3"><?= e($g('booking_slots')) ?></textarea></label>
    <div class="admin-form-grid admin-form-grid--2">
      <label class="admin-field"><span>Rendez-vous maximum par créneau</span><input type="number" name="booking_capacity" value="<?= e($g('booking_capacity')) ?>" min="1" max="20"></label>
      <label class="admin-field"><span>Jours proposés à l'avance</span><input type="number" name="booking_days_ahead" value="<?= e($g('booking_days_ahead')) ?>" min="3" max="60"></label>
    </div>
    <input type="hidden" name="booking_weekdays_sent" value="1">
    <div class="admin-field"><span>Jours des interventions planifiées</span><div class="check-row" style="flex-wrap:wrap;gap:.9rem;">
      <?php $bwd = booking_weekdays(); foreach ([1 => 'Lun', 2 => 'Mar', 3 => 'Mer', 4 => 'Jeu', 5 => 'Ven', 6 => 'Sam', 7 => 'Dim'] as $n => $lab): ?>
        <label style="display:inline-flex;gap:.3rem;align-items:center;"><input type="checkbox" name="booking_weekdays_list[]" value="<?= $n ?>" <?= in_array($n, $bwd, true) ? 'checked' : '' ?>> <?= $lab ?></label>
      <?php endforeach; ?>
    </div></div>
    <small style="color:#888;">À partir du lendemain (heure de Paris), jours fériés exclus. Ces jours sont repris par la FAQ (« créneaux <?= e(booking_weekdays_label()) ?> ») ; les urgences 24h/24 n'en dépendent pas. Le créneau reste à confirmer par téléphone.</small>
  </div>
</section>

<section class="admin-panel" id="client">
  <div class="admin-panel__head"><h2>👤 Espace client et suivi d'intervention</h2></div>
  <div class="admin-panel__body">
    <label class="admin-field admin-field--check"><div class="check-row"><input type="checkbox" name="client_space_enabled" value="1" <?= client_space_enabled() ? 'checked' : '' ?>> Ouvrir l'<a href="<?= e(route_url('espace-client')) ?>" target="_blank">espace client</a></div>
      <small style="color:#888;">Le client se connecte avec l'e-mail ou le numéro de sa demande et reçoit un code à 6 chiffres. Il voit ses demandes, ses interventions, ses factures (et les liens de paiement en attente).</small></label>
    <label class="admin-field admin-field--check"><div class="check-row"><input type="checkbox" name="track_enabled" value="1" <?= $chk('track_enabled') ?>> Ajouter un lien de suivi aux SMS « intervention planifiée » et « technicien en route »</div>
      <small style="color:#888;">Le lien montre l'avancement (planifiée, en route, sur place, terminée) et le prénom du technicien, sans adresse ni montant.</small></label>
  </div>
</section>

<section class="admin-panel" id="partiel">
  <div class="admin-panel__head"><h2>📝 Demandes non terminées — retiré</h2><p>Les formulaires n'envoient plus rien avant le clic sur « Envoyer » : garder le nom et le téléphone d'un visiteur qui n'a rien demandé est une collecte qu'il n'a pas choisie. Le serveur refuse aussi ces envois s'ils arrivent encore d'une ancienne page.</p></div>
  <div class="admin-panel__body">
    <p style="font-size:.88rem;color:#555;">Les saisies déjà enregistrées restent visibles dans <a href="<?= e(url_for('admin/leads.php#partiels')) ?>">Suivi des demandes</a> : elles n'ont pas été effacées automatiquement. Décidez de leur sort (rappel, puis suppression) ; elles ne doivent pas servir à de la prospection.</p>
  </div>
</section>

<section class="admin-panel" id="photos">
  <div class="admin-panel__head"><h2>📷 Conservation des photos des clients</h2><p>Les photos envoyées avec les demandes sont supprimées automatiquement après cette durée. La durée est indiquée au client sous le champ photos.</p></div>
  <div class="admin-panel__body">
    <label class="admin-field" style="max-width:260px;"><span>Durée de conservation (mois, 0 = sans limite)</span><input type="number" name="quote_photos_keep_months" value="<?= e($g('quote_photos_keep_months')) ?>" min="0" max="120"></label>
  </div>
</section>

<div class="admin-savebar"><button class="admin-btn admin-btn--primary" type="submit">💾 Enregistrer</button></div>
</form>

<section class="admin-panel" id="auto" style="margin-top:1.5rem;">
  <div class="admin-panel__head"><h2>⚙️ Tâches automatiques</h2><p>Alertes, demandes d'avis, rappels d'entretien et suppression des vieilles photos tournent toutes les 5 minutes environ, après les visites du site et avec la tâche planifiée o2switch.</p></div>
  <div class="admin-panel__body">
    <p style="font-size:.9rem;">Dernier passage : <strong><?= e(global_setting('automations_last_run', 'jamais')) ?></strong></p>
    <details><summary style="cursor:pointer;font-weight:600;">Rendre les tâches plus régulières (recommandé)</summary>
      <p style="font-size:.85rem;color:#555;line-height:1.7;">Si la synchronisation Pennylane est déjà programmée dans o2switch, rien à faire : elle lance aussi ces tâches. Sinon, dans cPanel → Tâches cron, ajoutez toutes les 10 minutes :<br>
      <code>php <?= e(realpath(__DIR__.'/../cron/automations.php') ?: '/home/VOTRE_COMPTE/public_html/cron/automations.php') ?></code></p></details>
    <form method="post" style="margin-top:.75rem;"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><button class="admin-btn admin-btn--secondary" type="submit" name="act" value="run">▶️ Lancer les tâches maintenant</button></form>
    <?php $log = auto_message_recent_list(15); if ($log): ?>
    <h3 style="margin-top:1.25rem;font-size:.95rem;">Derniers messages automatiques</h3>
    <div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Date</th><th>Type</th><th>Canal</th><th>Destinataire</th><th>Résultat</th></tr></thead><tbody>
      <?php $kinds = ['accuse' => 'Accusé de réception', 'avis' => 'Demande d\'avis', 'entretien' => 'Rappel d\'entretien', 'alerte_demande' => 'Nouvelle demande (vous)', 'alerte_attente' => 'Demande sans réponse (vous)'];
      foreach ($log as $l): ?>
      <tr><td><?= e(date('d/m H:i', strtotime((string)$l['created_at']))) ?></td><td><?= e($kinds[$l['kind']] ?? $l['kind']) ?></td><td><?= e(strtoupper((string)$l['channel'])) ?></td><td>…<?= e(substr((string)$l['phone9'], -4)) ?></td><td><?= (int)$l['ok'] ? '✅ envoyé' : '❌ échec' ?></td></tr>
      <?php endforeach; ?>
    </tbody></table></div>
    <?php endif; ?>
  </div>
</section>
<?php require __DIR__ . '/partials/footer.php'; ?>
