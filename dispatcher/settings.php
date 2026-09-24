<?php
declare(strict_types=1);
$pageTitle   = 'Notifications';
$dispSection = 'settings';
require __DIR__.'/partials/header.php';

$labels = [
    'Technicien' => [
        'notif_tech_push'  => ['Notification sur son téléphone', 'Nouvelle intervention attribuée, nouveau rappel. Le technicien doit avoir installé l\'application et accepté les notifications.'],
        'notif_tech_email' => ['E-mail', 'Même information, par e-mail.'],
        'notif_tech_sms'   => ['SMS', 'Même information, par SMS (payant, via le compte OVH).'],
    ],
    'Client' => [
        'notif_client_email'   => ['E-mail', 'Confirmation de la date et du technicien dès qu\'ils sont fixés.'],
        'notif_client_sms'     => ['SMS', 'Même confirmation par SMS (via le compte OVH).'],
        'notif_client_enroute' => ['« Votre technicien est en route »', 'Envoyé quand le technicien appuie sur « Je pars ».'],
    ],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    foreach ($labels as $group) {
        foreach (array_keys($group) as $k) notif_store_setting($k, !empty($_POST[$k]) ? '1' : '0');
    }
    flash('success', 'Réglages des notifications enregistrés.');
    redirect_to('dispatcher/settings.php');
}

$smsReady = global_setting('ovh_app_key') !== '' && global_setting('ovh_service_name') !== '';
try { $devices = db_fetch_all("SELECT t.name, COUNT(p.id) AS n FROM technicians t LEFT JOIN push_subscriptions p ON p.user_type = 'tech' AND p.user_id = t.id WHERE t.status = 'actif' GROUP BY t.id, t.name ORDER BY t.name"); }
catch (Throwable $e) { push_ensure_table(); $devices = []; }
?>
<div class="d-topbar">
  <div>
    <button class="d-menu-toggle" id="d-menu-toggle" aria-label="Menu">☰</button>
    <div>
      <div class="d-topbar-title">Notifications</div>
      <div class="d-topbar-sub">Qui est prévenu, et comment</div>
    </div>
  </div>
</div>

<div class="d-content">
  <?php if (!$smsReady): ?>
    <div class="d-flash d-flash--error">Aucun compte SMS OVH n'est configuré : les SMS ne partiront pas. Configurez-le dans l'administration (rubrique SMS).</div>
  <?php endif; ?>

  <div class="d-grid-2" style="align-items:start;">
    <form method="post" class="d-card">
      <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
      <?php foreach ($labels as $group => $items): ?>
        <div class="d-card-head"><div class="d-card-title"><?= e($group) ?></div></div>
        <div class="d-card-body">
          <?php foreach ($items as $k => [$l, $help]): ?>
            <label style="display:flex;gap:.75rem;align-items:flex-start;padding:.45rem 0;cursor:pointer;">
              <input type="checkbox" name="<?= e($k) ?>" value="1" <?= notif_enabled($k) ? 'checked' : '' ?> style="margin-top:.25rem;width:18px;height:18px;accent-color:var(--d-orange);">
              <span><span style="font-weight:600;"><?= e($l) ?></span><br><span style="font-size:.82rem;color:var(--d-t2);"><?= e($help) ?></span></span>
            </label>
          <?php endforeach; ?>
        </div>
      <?php endforeach; ?>
      <div class="d-card-body" style="border-top:1px solid var(--d-border);">
        <button type="submit" class="d-btn d-btn--primary">Enregistrer</button>
      </div>
    </form>

    <div class="d-card">
      <div class="d-card-head"><div class="d-card-title">Téléphones des techniciens</div></div>
      <div class="d-card-body">
        <?php if (!$devices): ?>
          <div class="d-empty" style="padding:1rem;">Aucun technicien actif.</div>
        <?php endif; ?>
        <?php foreach ($devices as $d): ?>
          <div class="d-info-row">
            <span><?= e($d['name']) ?></span>
            <span class="d-info-value" style="color:<?= (int)$d['n'] > 0 ? 'var(--d-success)' : 'var(--d-t3)' ?>;"><?= (int)$d['n'] > 0 ? 'Notifications actives' : 'Pas encore activées' ?></span>
          </div>
        <?php endforeach; ?>
        <p style="font-size:.82rem;color:var(--d-t2);margin-top:.9rem;">
          Pour les activer, le technicien ouvre son espace sur son téléphone, installe l'application
          (sur iPhone : Partager → « Sur l'écran d'accueil ») puis touche « Activer les notifications ».
        </p>
      </div>
    </div>
  </div>
</div>
<?php require __DIR__.'/partials/footer.php'; ?>
