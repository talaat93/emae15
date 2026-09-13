<?php
$adminSection = 'sms';
require __DIR__ . '/partials/header.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    foreach (['ovh_app_key','ovh_app_secret','ovh_consumer_key','ovh_service_name',
              'ovh_sms_sender','sms_admin_phone','sms_notify_admin','sms_notify_client','sms_notify_tech'] as $f) {
        set_setting($f, trim((string)($_POST[$f] ?? '')));
    }
    $testPhone = trim((string)($_POST['test_phone'] ?? ''));
    if ($testPhone !== '') {
        $ok = send_sms_ovh($testPhone, 'Test SMS '.company_name().' '.date('H:i'));
        flash($ok ? 'success' : 'error', $ok ? '✅ SMS de test envoyé sur '.$testPhone : '❌ Échec envoi SMS — vérifiez vos identifiants OVH et le format du numéro (+336...)');
    } else {
        flash('success', 'Paramètres SMS enregistrés.');
    }
    redirect_to('admin/sms.php');
}
?>
<div class="admin-page-toolbar">
  <div>
    <div class="admin-breadcrumb">Notifications</div>
    <h1 class="admin-page-title">SMS — OVH</h1>
    <p class="admin-page-subtitle">Notifications SMS automatiques via OVH SMS.</p>
  </div>
</div>

<div style="background:#e8f4ff;border:1px solid #bcd8ee;border-radius:10px;padding:1rem 1.25rem;margin-bottom:1.25rem;font-size:.88rem;color:#0f4c75;line-height:1.7;">
  <strong>Comment obtenir les clés API OVH ?</strong><br>
  1. Connecte-toi sur <a href="https://eu.api.ovh.com/createToken/" target="_blank" style="color:#1a7ab5;">eu.api.ovh.com/createToken</a><br>
  2. Nom app : <code>EMAE SMS</code> — Rights : <code>POST /sms/*</code><br>
  3. Copie les 3 clés générées ci-dessous.<br>
  4. Le nom du service SMS se trouve dans ton espace client OVH → SMS.
</div>

<form method="post" class="admin-stack">
<input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

<section class="admin-panel">
  <div class="admin-panel__head"><h2>🔑 Identifiants OVH API</h2></div>
  <div class="admin-panel__body">
    <div class="admin-form-grid admin-form-grid--2">
      <label class="admin-field"><span>Application Key</span><input type="text" name="ovh_app_key" value="<?= e(setting('ovh_app_key','')) ?>" placeholder="xxxxxxxxxxxxxxxx" autocomplete="off"></label>
      <label class="admin-field"><span>Application Secret</span><input type="password" name="ovh_app_secret" value="<?= e(setting('ovh_app_secret','')) ?>" autocomplete="new-password"></label>
    </div>
    <div class="admin-form-grid admin-form-grid--2">
      <label class="admin-field"><span>Consumer Key</span><input type="password" name="ovh_consumer_key" value="<?= e(setting('ovh_consumer_key','')) ?>" autocomplete="new-password"></label>
      <label class="admin-field"><span>Nom du service SMS</span><input type="text" name="ovh_service_name" value="<?= e(setting('ovh_service_name','')) ?>" placeholder="sms-xxxxxxxx-1"></label>
    </div>
    <div class="admin-form-grid admin-form-grid--2">
      <label class="admin-field">
        <span>Nom expéditeur (11 car. max)</span>
        <input type="text" name="ovh_sms_sender" value="<?= e(setting('ovh_sms_sender', mb_substr(company_name(),0,11))) ?>" maxlength="11">
        <small style="color:#888;font-size:.78rem;">Affiché à la place du numéro.</small>
      </label>
      <label class="admin-field">
        <span>📱 Mobile admin (alertes nouvelles demandes)</span>
        <input type="tel" name="sms_admin_phone" value="<?= e(setting('sms_admin_phone','')) ?>" placeholder="+33612345678">
      </label>
    </div>
  </div>
</section>

<section class="admin-panel">
  <div class="admin-panel__head"><h2>🔔 Déclencheurs SMS</h2></div>
  <div class="admin-panel__body">
    <div class="admin-form-grid admin-form-grid--3">
      <label class="admin-field admin-field--check">
        <span>Admin — nouvelle demande</span>
        <div class="check-row"><input type="checkbox" name="sms_notify_admin" value="1" <?= setting_bool('sms_notify_admin')?'checked':'' ?>> SMS à l'admin à chaque demande</div>
      </label>
      <label class="admin-field admin-field--check">
        <span>Client — confirmation</span>
        <div class="check-row"><input type="checkbox" name="sms_notify_client" value="1" <?= setting_bool('sms_notify_client')?'checked':'' ?>> SMS au client quand intervention planifiée</div>
      </label>
      <label class="admin-field admin-field--check">
        <span>Technicien — assignation</span>
        <div class="check-row"><input type="checkbox" name="sms_notify_tech" value="1" <?= setting_bool('sms_notify_tech')?'checked':'' ?>> SMS au tech quand une fiche lui est assignée</div>
      </label>
    </div>
  </div>
</section>

<section class="admin-panel">
  <div class="admin-panel__head"><h2>🧪 Test SMS</h2><p>Remplit et enregistre les identifiants d'abord, puis envoie un test.</p></div>
  <div class="admin-panel__body">
    <label class="admin-field">
      <span>Numéro de test</span>
      <input type="tel" name="test_phone" placeholder="+33612345678">
    </label>
  </div>
</section>

<div class="admin-savebar" style="gap:.75rem;">
  <button class="admin-btn admin-btn--secondary" type="submit">💾 Enregistrer</button>
  <button class="admin-btn admin-btn--primary" type="submit">💾 + 📱 Enregistrer et tester</button>
</div>
</form>
<?php require __DIR__ . '/partials/footer.php'; ?>
