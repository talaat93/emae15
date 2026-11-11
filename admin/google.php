<?php
/**
 * Google : connexion du compte EMAE (OAuth), avis de la fiche mis en cache, Google Contacts.
 * Les identifiants sont des secrets : jamais affichés, jamais versionnés (includes/google_api.php).
 */
$adminSection = 'google';
require __DIR__ . '/partials/header.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $act = (string)($_POST['act'] ?? '');
    if ($act === 'oauth') {
        foreach (['google_oauth_client_id', 'google_oauth_client_secret'] as $k) {
            $v = trim((string)($_POST[$k] ?? ''));
            if ($v !== '') set_integration_secret($k, $v);
        }
        flash('success', 'Identifiants enregistrés. Cliquez maintenant sur « Connecter le compte Google ».');
    } elseif ($act === 'disconnect') {
        set_integration_secret('google_oauth_refresh_token', '');
        growth_save('google_oauth_connected_at', '');
        flash('success', 'Compte Google déconnecté. Le site n\'utilise plus l\'accès accordé (vous pouvez aussi le retirer dans votre compte Google, rubrique Sécurité).');
    } elseif ($act === 'location') {
        $loc = trim((string)($_POST['google_gbp_location'] ?? ''));
        if ($loc === '' || preg_match('#^accounts/\d+/locations/\d+$#', $loc)) {
            growth_save('google_gbp_location', $loc);
            flash('success', $loc === '' ? 'Fiche retirée : les avis viendront de Places API.' : 'Fiche enregistrée.');
        } else {
            flash('error', 'Format attendu : accounts/123…/locations/456…');
        }
    } elseif ($act === 'refresh') {
        $r = google_reviews_refresh();
        flash($r['ok'] ? 'success' : 'error', $r['ok']
            ? 'Avis relevés ('.($r['source'] === 'gbp' ? 'API Business Profile' : 'Places API').') : '.$r['count'].' avis avec texte en cache, note '.number_format((float)$r['rating'], 1, ',', '').'/5 sur '.$r['total'].' avis.'
            : 'Relève impossible : '.$r['error']);
    } elseif ($act === 'contacts') {
        growth_save('google_contacts_enabled', !empty($_POST['google_contacts_enabled']) ? '1' : '0');
        flash('success', 'Réglage enregistré.');
    }
    redirect_to('admin/google.php');
}

$locs = null;
if (isset($_GET['fiches']) && google_oauth_connected()) $locs = google_gbp_locations();
$cache   = google_reviews_cached();
$src     = growth_get('google_reviews_source');
$upd     = growth_get('google_reviews_updated_at');
$err     = growth_get('google_reviews_error');
$cronAt  = growth_get('google_cron_seen_at');
$ok = static fn(bool $b): string => $b ? '<b style="color:#15803d;">✔ oui</b>' : '<b style="color:#b45309;">✘ non</b>';
?>
<div class="admin-page-toolbar"><h1>🔗 Google : avis et contacts</h1></div>

<section class="admin-panel">
  <div class="admin-panel__head"><h2>État</h2></div>
  <div class="admin-panel__body">
    <table class="admin-table" style="max-width:760px;"><tbody>
      <tr><td>Identifiants OAuth enregistrés</td><td><?= $ok(google_oauth_configured()) ?></td></tr>
      <tr><td>Compte Google connecté</td><td><?= $ok(google_oauth_connected()) ?><?= growth_get('google_oauth_connected_at') !== '' && google_oauth_connected() ? ' depuis le '.e(date('d/m/Y H:i', strtotime(growth_get('google_oauth_connected_at')))) : '' ?></td></tr>
      <tr><td>Fiche Business Profile (tous les avis)</td><td><?= google_gbp_location() !== '' ? e(google_gbp_location()) : '<span style="color:#b45309;">non choisie</span>' ?></td></tr>
      <tr><td>Places API (5 avis, solution de repli)</td><td><?= $ok(integration_secret('google_places_api_key') !== '' && trim(growth_get('google_place_id')) !== '') ?> <a href="<?= e(url_for('admin/growth.php#google')) ?>">clé et Place ID</a></td></tr>
      <tr><td>Dernière relève des avis</td><td><?= $upd !== '' ? e(date('d/m/Y H:i', strtotime($upd))).' — '.($src === 'gbp' ? 'API Business Profile' : 'Places API').' — '.count($cache).' avis en cache' : 'jamais' ?></td></tr>
      <?php if ($err !== ''): ?><tr><td>Dernière erreur</td><td style="color:#b91c1c;"><?= e($err) ?></td></tr><?php endif; ?>
      <tr><td>Tâche cron vue</td><td><?= $cronAt !== '' ? 'le '.e(date('d/m/Y H:i', strtotime($cronAt))) : '<span style="color:#b45309;">jamais — voir l\'étape 4</span>' ?></td></tr>
    </tbody></table>
  </div>
</section>

<section class="admin-panel">
  <div class="admin-panel__head"><h2>1. Identifiants OAuth (Google Cloud)</h2>
    <p>Créés une fois dans Google Cloud Console (API et services → Identifiants → ID client OAuth, type « Application Web »). Adresse de redirection à déclarer, à copier telle quelle :</p>
    <p><code style="user-select:all;background:#eef2fb;padding:.3rem .5rem;border-radius:6px;"><?= e(google_oauth_redirect_uri()) ?></code></p></div>
  <div class="admin-panel__body">
    <form method="post" class="admin-form-grid admin-form-grid--2"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="act" value="oauth">
      <label class="admin-field"><span>ID client <?= integration_secret('google_oauth_client_id') !== '' ? '— <b style="color:#15803d;">enregistré</b>' : '' ?></span><input type="password" name="google_oauth_client_id" autocomplete="off" placeholder="….apps.googleusercontent.com — laisser vide pour ne pas changer"<?= integration_secret_from_file('google_oauth_client_id') ? ' disabled' : '' ?>></label>
      <label class="admin-field"><span>Code secret du client <?= integration_secret('google_oauth_client_secret') !== '' ? '— <b style="color:#15803d;">enregistré</b>' : '' ?></span><input type="password" name="google_oauth_client_secret" autocomplete="new-password" placeholder="GOCSPX-… — laisser vide pour ne pas changer"<?= integration_secret_from_file('google_oauth_client_secret') ? ' disabled' : '' ?>></label>
      <div class="admin-savebar" style="grid-column:1/-1;justify-content:flex-start"><button class="admin-btn admin-btn--primary" type="submit">Enregistrer</button></div>
    </form>
  </div>
</section>

<section class="admin-panel">
  <div class="admin-panel__head"><h2>2. Connexion du compte Google d'EMAE</h2>
    <p>Connectez le compte qui gère la fiche Google et le carnet Google Contacts. Google vous demandera d'autoriser : la gestion de la fiche (lecture des avis) et l'accès aux contacts.</p></div>
  <div class="admin-panel__body" style="display:flex;gap:.75rem;flex-wrap:wrap;">
    <?php if (google_oauth_configured()): ?>
      <a class="admin-btn admin-btn--primary" href="<?= e(url_for('admin/google_oauth.php?start=1')) ?>"><?= google_oauth_connected() ? '🔄 Reconnecter' : '🔗 Connecter le compte Google' ?></a>
    <?php else: ?>
      <span style="color:#b45309;">Enregistrez d'abord les identifiants (étape 1).</span>
    <?php endif; ?>
    <?php if (google_oauth_connected()): ?>
      <form method="post" style="margin:0;"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="act" value="disconnect"><button class="admin-btn admin-btn--secondary" type="submit" onclick="return confirm('Déconnecter le compte Google ? Les avis ne seront plus relevés par l\'API Business Profile et les contacts ne seront plus créés.')">Déconnecter</button></form>
    <?php endif; ?>
  </div>
</section>

<section class="admin-panel">
  <div class="admin-panel__head"><h2>3. Fiche dont on relève les avis</h2>
    <p>L'API Business Profile donne tous les avis, mais Google doit d'abord accorder l'accès à votre projet (demande à faire une fois, voir les instructions). En attendant, ou en cas d'échec, le site utilise Places API (5 avis au plus, choisis par Google).</p></div>
  <div class="admin-panel__body">
    <?php if (is_array($locs) && isset($locs['error'])): ?>
      <p style="color:#b91c1c;">Google refuse la lecture des fiches : <?= e($locs['error']) ?><br><small>Le plus souvent : accès à l'API Business Profile pas encore accordé à votre projet Google Cloud.</small></p>
    <?php elseif (is_array($locs)): ?>
      <?php if (!$locs): ?><p>Aucune fiche trouvée sur ce compte.</p><?php endif; ?>
      <?php foreach ($locs as $l): ?>
      <form method="post" style="margin:.35rem 0;"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="act" value="location"><input type="hidden" name="google_gbp_location" value="<?= e($l['name']) ?>">
        <button class="admin-btn admin-btn--secondary" type="submit">Choisir « <?= e($l['title']) ?> »</button> <small><?= e($l['name']) ?></small></form>
      <?php endforeach; ?>
    <?php endif; ?>
    <div style="display:flex;gap:.75rem;flex-wrap:wrap;align-items:flex-end;margin-top:.5rem;">
      <?php if (google_oauth_connected()): ?><a class="admin-btn admin-btn--secondary" href="<?= e(url_for('admin/google.php?fiches=1')) ?>">🔎 Chercher mes fiches</a><?php endif; ?>
      <form method="post" style="margin:0;display:flex;gap:.5rem;align-items:flex-end;"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="act" value="location">
        <label class="admin-field" style="margin:0;"><span>Ou saisie directe</span><input type="text" name="google_gbp_location" value="<?= e(google_gbp_location()) ?>" placeholder="accounts/…/locations/…" style="min-width:300px;"></label>
        <button class="admin-btn admin-btn--secondary" type="submit">Enregistrer</button></form>
    </div>
  </div>
</section>

<section class="admin-panel">
  <div class="admin-panel__head"><h2>4. Relève quotidienne des avis</h2>
    <p>Les avis sont relevés une fois par jour par la tâche cron et gardés en base : aucune visite du site n'interroge Google. À programmer dans o2switch → cPanel → Tâches cron, toutes les 10 minutes :</p>
    <p><code style="user-select:all;background:#eef2fb;padding:.3rem .5rem;border-radius:6px;">php <?= e(realpath(__DIR__.'/../cron/automations.php') ?: '/home/VOTRE_COMPTE/public_html/cron/automations.php') ?></code></p></div>
  <div class="admin-panel__body">
    <form method="post"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="act" value="refresh"><button class="admin-btn admin-btn--primary" type="submit">🔄 Relever les avis maintenant</button></form>
    <?php if ($cache): ?>
    <table class="admin-table" style="margin-top:1rem;"><thead><tr><th>Date</th><th>Auteur</th><th>Note</th><th>Texte</th></tr></thead><tbody>
      <?php foreach (array_slice($cache, 0, 50) as $c): ?>
      <tr><td style="white-space:nowrap;"><?= e(date_fr($c['review_date'] ?? '')) ?></td><td><?= e($c['author_name']) ?></td><td><?= str_repeat('★', (int)$c['rating']) ?></td><td style="font-size:.85rem;color:#5b6b92;"><?= e(mb_substr((string)$c['content'], 0, 120, 'UTF-8')) ?></td></tr>
      <?php endforeach; ?>
    </tbody></table>
    <?php endif; ?>
  </div>
</section>
<?php require __DIR__ . '/partials/footer.php'; ?>
