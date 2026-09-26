<?php
declare(strict_types=1);
$pageTitle   = 'Réglages';
$dispSection = 'settings';
require __DIR__.'/partials/header.php';

$tabs = [
    'notifications' => 'Notifications',
    'claude'        => 'Claude',
    'pennylane'     => 'Pennylane',
    'yousign'       => 'Yousign',
];
$tab = array_key_exists($_GET['tab'] ?? '', $tabs) ? (string)$_GET['tab'] : 'notifications';

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

/** Enregistre un secret saisi : vide = inchangé, case « supprimer » = effacé. */
function settings_save_secret(string $name): void
{
    if (!empty($_POST[$name.'_clear'])) { integration_store_secret($name, ''); return; }
    $v = trim((string)($_POST[$name] ?? ''));
    if ($v !== '') integration_store_secret($name, $v);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string)($_POST['action'] ?? 'save');
    $back = 'dispatcher/settings.php?tab='.$tab;

    if ($tab === 'notifications') {
        foreach ($labels as $group) {
            foreach (array_keys($group) as $k) notif_store_setting($k, !empty($_POST[$k]) ? '1' : '0');
        }
        flash('success', 'Réglages des notifications enregistrés.');
    } elseif ($tab === 'claude') {
        if ($action === 'test') {
            $t = claude_test_connection();
            flash($t['ok'] ? 'success' : 'error', $t['message']);
        } else {
            settings_save_secret('claude_api_key');
            $m = (string)($_POST['claude_model'] ?? '');
            if (array_key_exists($m, claude_models())) notif_store_setting('claude_model', $m);
            admin_log_safe('réglages', 'Claude mis à jour', (int)$disp['id'], (string)$disp['name']);
            flash('success', 'Réglages Claude enregistrés.');
        }
    } elseif ($tab === 'pennylane') {
        if ($action === 'test') {
            $t = pennylane_test_connection();
            flash($t['ok'] ? 'success' : 'error', $t['message']);
        } elseif ($action === 'sync' && function_exists('pennylane_sync_run')) {
            $r = pennylane_sync_run('manuel');
            flash($r['ok'] ? 'success' : 'error', $r['message']);
        } else {
            settings_save_secret('pennylane_api_key');
            notif_store_setting('pennylane_simulation', !empty($_POST['pennylane_simulation']) ? '1' : '0');
            admin_log_safe('réglages', 'Pennylane mis à jour', (int)$disp['id'], (string)$disp['name']);
            flash('success', 'Réglages Pennylane enregistrés.');
        }
    } elseif ($tab === 'yousign') {
        if ($action === 'test' && function_exists('yousign_test_connection')) {
            $t = yousign_test_connection();
            flash($t['ok'] ? 'success' : 'error', $t['message']);
        } else {
            settings_save_secret('yousign_api_key');
            settings_save_secret('yousign_webhook_secret');
            notif_store_setting('yousign_sandbox', !empty($_POST['yousign_sandbox']) ? '1' : '0');
            $seuil = (float)str_replace([',', ' '], ['.', ''], (string)($_POST['yousign_quote_threshold'] ?? '500'));
            notif_store_setting('yousign_quote_threshold', (string)max(0, $seuil));
            admin_log_safe('réglages', 'Yousign mis à jour', (int)$disp['id'], (string)$disp['name']);
            flash('success', 'Réglages Yousign enregistrés.');
        }
    }
    redirect_to($back);
}

/** Trace les changements de réglages sensibles (sans jamais la valeur). */
function admin_log_safe(string $what, string $detail, int $actorId, string $actorName): void
{
    integration_log('audit', $what.' : '.$detail, ['dispatcher' => $actorId.' '.$actorName]);
}

$smsReady = global_setting('ovh_app_key') !== '' && global_setting('ovh_service_name') !== '';
if ($tab === 'notifications') {
    try { $devices = db_fetch_all("SELECT t.name, COUNT(p.id) AS n FROM technicians t LEFT JOIN push_subscriptions p ON p.user_type = 'tech' AND p.user_id = t.id WHERE t.status = 'actif' GROUP BY t.id, t.name ORDER BY t.name"); }
    catch (Throwable $e) { push_ensure_table(); $devices = []; }
}

/** Ligne « statut de la clé » + champ de saisie masqué. */
function settings_secret_field(string $name, string $label, string $placeholder, string $help = ''): string
{
    $cur = integration_secret($name);
    $inConfig = is_string(app_config()['secrets'][$name] ?? null) && trim((string)app_config()['secrets'][$name]) !== '';
    $h = '<div class="d-field"><label for="f-'.e($name).'">'.e($label).'</label>';
    if ($cur !== '') {
        $h .= '<div style="font-size:.84rem;margin-bottom:.35rem;color:var(--d-success);">Configurée ('.e(integration_mask($cur)).')'
            . ($inConfig ? ' — définie dans config.local.php' : '').'</div>';
    } else {
        $h .= '<div style="font-size:.84rem;margin-bottom:.35rem;color:var(--d-warning);">Non configurée : mode SIMULATION</div>';
    }
    if (!$inConfig) {
        $h .= '<input type="password" id="f-'.e($name).'" name="'.e($name).'" autocomplete="off" placeholder="'.e($cur !== '' ? 'Laisser vide pour ne pas changer' : $placeholder).'">';
        if ($cur !== '') $h .= '<label style="display:flex;gap:.4rem;align-items:center;margin-top:.4rem;font-weight:400;"><input type="checkbox" name="'.e($name).'_clear" value="1" style="width:auto;"> Supprimer cette clé</label>';
    }
    if ($help !== '') $h .= '<div style="font-size:.8rem;color:var(--d-t2);margin-top:.3rem;">'.$help.'</div>';
    return $h.'</div>';
}
?>
<div class="d-topbar">
  <div>
    <button class="d-menu-toggle" id="d-menu-toggle" aria-label="Menu">☰</button>
    <div>
      <div class="d-topbar-title">Réglages</div>
      <div class="d-topbar-sub">Notifications et services connectés</div>
    </div>
  </div>
</div>

<div class="d-content">
  <div style="display:flex;gap:.4rem;flex-wrap:wrap;margin-bottom:1.1rem;">
    <?php foreach ($tabs as $k => $l): ?>
      <a href="?tab=<?= e($k) ?>" class="d-btn d-btn--sm <?= $k === $tab ? 'd-btn--primary' : '' ?>"><?= e($l) ?></a>
    <?php endforeach; ?>
  </div>

<?php if ($tab === 'notifications'): ?>
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
        <?php if (!$devices): ?><div class="d-empty" style="padding:1rem;">Aucun technicien actif.</div><?php endif; ?>
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

<?php elseif ($tab === 'claude'): ?>
  <div class="d-grid-2" style="align-items:start;">
    <form method="post" class="d-card">
      <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
      <div class="d-card-head"><div class="d-card-title">Assistant Claude</div></div>
      <div class="d-card-body">
        <?= settings_secret_field('claude_api_key', 'Clé API Anthropic', 'sk-ant-…', 'Créée sur console.anthropic.com. Elle est aussi utilisée par le chatbot du site.') ?>
        <div class="d-field">
          <label for="f-model">Modèle</label>
          <select id="f-model" name="claude_model">
            <?php foreach (claude_models() as $id => $l): ?><option value="<?= e($id) ?>" <?= claude_model() === $id ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div style="display:flex;gap:.5rem;">
          <button type="submit" class="d-btn d-btn--primary">Enregistrer</button>
          <button type="submit" name="action" value="test" class="d-btn">Tester la connexion</button>
        </div>
      </div>
    </form>
    <div class="d-card">
      <div class="d-card-head"><div class="d-card-title">Ce que fait Claude</div></div>
      <div class="d-card-body" style="font-size:.88rem;line-height:1.6;">
        <p>Claude <b>assiste</b>, il ne décide jamais seul :</p>
        <ul style="margin:.5rem 0 .5rem 1.1rem;">
          <li>il guide la qualification des appels (une question à la fois) ;</li>
          <li>il relit les rapports des techniciens et signale les incohérences ;</li>
          <li>il propose les lignes de facture, recalculées ensuite à partir de la grille tarifaire.</li>
        </ul>
        <p>Aucune facture ne part sans votre validation. Sans clé, tout fonctionne en <b>mode simulation</b> (réponses d'exemple) ou avec un questionnaire classique.</p>
      </div>
    </div>
  </div>

<?php elseif ($tab === 'pennylane'): ?>
  <?php $lastSync = json_decode(integration_setting('pennylane_last_sync', ''), true); ?>
  <div class="d-grid-2" style="align-items:start;">
    <form method="post" class="d-card">
      <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
      <div class="d-card-head"><div class="d-card-title">Pennylane</div></div>
      <div class="d-card-body">
        <?= settings_secret_field('pennylane_api_key', 'Jeton d\'API Pennylane', 'Jeton de l\'entreprise', 'Pennylane → Paramètres → Connectivité → Développeurs → Générer un jeton. Droits nécessaires : clients, produits, factures clients (lecture et écriture), pièces jointes.') ?>
        <label style="display:flex;gap:.5rem;align-items:flex-start;margin-bottom:1rem;cursor:pointer;">
          <input type="checkbox" name="pennylane_simulation" value="1" <?= integration_setting('pennylane_simulation', '0') === '1' ? 'checked' : '' ?> style="width:auto;margin-top:.2rem;">
          <span><b>Mode simulation</b><br><span style="font-size:.82rem;color:var(--d-t2);">Aucune donnée n'est envoyée à Pennylane ; les factures sont fictives et marquées « SIMULATION ». Automatique tant qu'aucun jeton n'est saisi.</span></span>
        </label>
        <div style="display:flex;gap:.5rem;flex-wrap:wrap;">
          <button type="submit" class="d-btn d-btn--primary">Enregistrer</button>
          <button type="submit" name="action" value="test" class="d-btn">Tester la connexion</button>
          <?php if (function_exists('pennylane_sync_run')): ?>
            <button type="submit" name="action" value="sync" class="d-btn">Synchroniser maintenant</button>
          <?php endif; ?>
        </div>
      </div>
    </form>
    <div class="d-card">
      <div class="d-card-head"><div class="d-card-title">Dernière synchronisation</div></div>
      <div class="d-card-body" style="font-size:.88rem;">
        <?php if (!$lastSync): ?>
          <p style="color:var(--d-t2);">Aucune synchronisation pour l'instant.</p>
        <?php else: ?>
          <div class="d-info-row"><span class="d-info-label">Date</span><span class="d-info-value"><?= e(date('d/m/Y H:i', strtotime((string)$lastSync['at']))) ?></span></div>
          <div class="d-info-row"><span class="d-info-label">Résultat</span><span class="d-info-value"><?= e((string)$lastSync['message']) ?></span></div>
        <?php endif; ?>
        <p style="color:var(--d-t2);margin-top:.8rem;">La synchronisation automatique tourne toutes les 15 minutes via une tâche cron (voir la documentation d'installation).</p>
      </div>
    </div>
  </div>

<?php else: ?>
  <form method="post" class="d-card" style="max-width:720px;">
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
    <div class="d-card-head"><div class="d-card-title">Yousign — signature électronique</div></div>
    <div class="d-card-body">
      <?= settings_secret_field('yousign_api_key', 'Clé API Yousign', 'Clé API', 'Yousign → Paramètres → Développeurs → Clés API.') ?>
      <?= settings_secret_field('yousign_webhook_secret', 'Secret du webhook Yousign', 'Secret de signature du webhook', 'Affiché par Yousign à la création du webhook ; il sert à vérifier que les notifications viennent bien de Yousign.') ?>
      <label style="display:flex;gap:.5rem;align-items:flex-start;margin-bottom:1rem;cursor:pointer;">
        <input type="checkbox" name="yousign_sandbox" value="1" <?= integration_setting('yousign_sandbox', '1') === '1' ? 'checked' : '' ?> style="width:auto;margin-top:.2rem;">
        <span><b>Environnement de test (sandbox)</b><br><span style="font-size:.82rem;color:var(--d-t2);">Les signatures n'ont pas de valeur légale. À décocher pour la production, avec une clé de production.</span></span>
      </label>
      <div class="d-field" style="max-width:260px;">
        <label for="f-seuil">Signature à distance obligatoire au-delà de (€ TTC)</label>
        <input id="f-seuil" type="text" inputmode="decimal" name="yousign_quote_threshold" value="<?= e(integration_setting('yousign_quote_threshold', '500')) ?>">
      </div>
      <div style="display:flex;gap:.5rem;">
        <button type="submit" class="d-btn d-btn--primary">Enregistrer</button>
        <?php if (function_exists('yousign_test_connection')): ?>
          <button type="submit" name="action" value="test" class="d-btn">Tester la connexion</button>
        <?php endif; ?>
      </div>
    </div>
  </form>
<?php endif; ?>
</div>
<?php require __DIR__.'/partials/footer.php'; ?>
