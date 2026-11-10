<?php
/**
 * Agent relation client : réglages, boîtes mail, Telegram, suivi et coût.
 * Voir includes/agent_client.php pour le fonctionnement.
 */
$adminSection = 'agent';
require __DIR__ . '/partials/header.php';

$d = agent_defaults();
$accounts = ['domaine', 'gmail'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $act = (string)($_POST['act'] ?? 'save');
    $back = 'admin/agent.php';

    // Les boutons de test sont dans le même formulaire : ce qui vient d'être saisi est enregistré d'abord.
    if (in_array($act, ['save', 'test_domaine', 'test_gmail', 'tg_setup', 'tg_test'], true)) {
        // Un caractère mal encodé faisait refuser le réglage par la base, sans message.
        array_walk($_POST, static function (&$v): void { if (is_string($v)) $v = mb_scrub($v, 'UTF-8'); });
        $wasOn = agent_on();
        agent_set('agent_enabled', !empty($_POST['agent_enabled']) ? '1' : '0');
        agent_set('agent_mode', ($_POST['agent_mode'] ?? '') === 'live' ? 'live' : 'test');
        agent_set('agent_filing', ($_POST['agent_filing'] ?? '') === 'move' ? 'move' : 'copy');
        foreach (['agent_daily_cap', 'agent_usd_eur'] as $k) {
            $v = str_replace(',', '.', trim((string)($_POST[$k] ?? '')));
            if (is_numeric($v) && (float)$v >= 0) agent_set($k, (string)(float)$v);
        }
        foreach (['agent_signature', 'agent_allow', 'agent_block', 'agent_parent_folder', 'agent_prices', 'agent_delays'] as $k) {
            if (array_key_exists($k, $_POST)) agent_set($k, mb_substr(trim((string)$_POST[$k]), 0, 4000));
        }
        if (array_key_exists('agent_history_days', $_POST)) {
            $hd = (string)max(0, min(3650, (int)$_POST['agent_history_days']));
            // Plus d'historique demandé : les boîtes sont relues depuis la nouvelle date (sans doublon : même Message-ID).
            if ((int)$hd > (int)agent_get('agent_history_days')) {
                foreach ($accounts as $a) { agent_set("agent_{$a}_uidvalidity", ''); agent_set("agent_{$a}_lastuid", ''); }
            }
            agent_set('agent_history_days', $hd);
        }
        if (array_key_exists('agent_history_reply_days', $_POST)) agent_set('agent_history_reply_days', (string)max(1, min(365, (int)$_POST['agent_history_reply_days'])));
        foreach (array_keys(agent_categories()) as $cat) {
            if (array_key_exists('folder_'.$cat, $_POST)) agent_set('agent_folder_'.$cat, mb_substr(trim((string)$_POST['folder_'.$cat]), 0, 60));
        }
        foreach ($accounts as $a) {
            agent_set("agent_{$a}_on", !empty($_POST["agent_{$a}_on"]) ? '1' : '0');
            agent_set("agent_{$a}_verify", !empty($_POST["agent_{$a}_verify"]) ? '1' : '0');
            foreach (['user', 'host', 'smtp_host'] as $f) {
                $v = trim((string)($_POST["agent_{$a}_{$f}"] ?? ''));
                if ($v !== '' && preg_match('/^[A-Za-z0-9.@_+-]+$/', $v)) agent_set("agent_{$a}_{$f}", $v);
            }
            foreach (['port', 'smtp_port'] as $f) {
                $v = (int)($_POST["agent_{$a}_{$f}"] ?? 0);
                if ($v > 0 && $v < 65536) agent_set("agent_{$a}_{$f}", (string)$v);
            }
            $pw = (string)($_POST["agent_{$a}_pass"] ?? '');
            if (trim($pw) !== '') set_integration_secret('agent_pass_'.$a, $a === 'gmail' ? str_replace(' ', '', trim($pw)) : trim($pw));
            if (!empty($_POST["agent_{$a}_pass_clear"])) set_integration_secret('agent_pass_'.$a, '');
            // Boîte changée : on repart des mails reçus à partir d'aujourd'hui.
            if (trim((string)($_POST["agent_{$a}_user"] ?? '')) !== '' && trim((string)$_POST["agent_{$a}_user"]) !== agent_get("agent_{$a}_user")) {
                agent_set("agent_{$a}_uidvalidity", ''); agent_set("agent_{$a}_lastuid", '');
            }
        }
        $tg = trim((string)($_POST['telegram_bot_token'] ?? ''));
        if ($tg !== '' && preg_match('/^\d+:[A-Za-z0-9_-]{30,}$/', $tg)) {
            // Nouveau bot : le téléphone doit être relié de nouveau.
            if ($tg !== agent_tg_token()) { set_integration_secret('telegram_bot_token', $tg); agent_set('telegram_chat_id', ''); }
        }
        elseif ($tg !== '') flash('error', 'Le jeton du bot Telegram n\'a pas le bon format (il ressemble à 123456789:AAH…).');
        $ck = trim((string)($_POST['claude_api_key'] ?? ''));
        if ($ck !== '') set_integration_secret('claude_api_key', $ck);
        if (agent_on() && !$wasOn) {
            agent_set('agent_start', date('Y-m-d'));
            agent_set('agent_site_lastid', '');
            agent_log('agent mis en marche', agent_live() ? 'envoi activé' : 'mode test');
        } elseif (!agent_on() && $wasOn) agent_log('agent arrêté');
        if ($act === 'save') { flash('success', 'Réglages enregistrés.'); redirect_to($back); }
    }
    if (preg_match('/^test_(domaine|gmail)$/', $act, $m)) {
        $r = agent_test_account($m[1]);
        flash($r['ok'] ? 'success' : 'error', agent_accounts()[$m[1]]['label'].' : '.$r['message']);
        redirect_to($back.'#boites');
    }
    if ($act === 'tg_setup') {
        $r = agent_tg_setup();
        if ($r['ok']) $_SESSION['agent_tg_link'] = ['username' => $r['username'], 'code' => $r['code']];
        else flash('error', $r['error']);
        redirect_to($back.'#telegram');
    }
    if ($act === 'tg_test') {
        agent_tg_text('✅ Test réussi : l\'agent relation client EMAE peut vous écrire ici.');
        flash(agent_tg_ready() ? 'success' : 'error', agent_tg_ready() ? 'Message de test envoyé sur Telegram.' : 'Le téléphone n\'est pas encore relié.');
        redirect_to($back.'#telegram');
    }
    if ($act === 'run') {
        if (!agent_on()) { flash('error', 'Mettez d\'abord l\'agent en marche.'); redirect_to($back); }
        @set_time_limit(90);
        flash('success', 'Passage terminé : '.agent_run());
        redirect_to($back.'#suivi');
    }
    if ($act === 'fix') {
        $id = (int)($_POST['id'] ?? 0); $cat = (string)($_POST['category'] ?? '');
        if (isset(agent_categories()[$cat]) && ($it = db_fetch('SELECT category FROM agent_items WHERE id = ?', [$id]))) {
            db_execute('UPDATE agent_items SET category_fixed = ?, filed = IF(filed = 1, 1, 0) WHERE id = ?', [$cat === $it['category'] ? null : $cat, $id]);
            agent_log('tri corrigé', agent_cat_label((string)$it['category']).' → '.agent_cat_label($cat), 'par le propriétaire', $id);
            flash('success', 'Catégorie corrigée. Ces corrections servent au bilan de la semaine de test.');
        }
        redirect_to($back.'#suivi');
    }
    if ($act === 'retry') {
        db_execute("UPDATE agent_items SET status = 'new', error = NULL WHERE id = ? AND status = 'error'", [(int)($_POST['id'] ?? 0)]);
        flash('success', 'Le message sera retraité au prochain passage.');
        redirect_to($back.'#suivi');
    }
    if ($act === 'link') {
        $pre = [];
        foreach (['full_name', 'phone', 'email'] as $k) if (trim((string)($_POST['l_'.$k] ?? '')) !== '') $pre[$k] = trim((string)$_POST['l_'.$k]);
        $_SESSION['agent_new_link'] = agent_followup_link([], $pre);
        redirect_to($back.'#lien');
    }
}

$g = static fn(string $k): string => agent_get($k);
$acc = agent_accounts();
$tgLink = $_SESSION['agent_tg_link'] ?? null; unset($_SESSION['agent_tg_link']);
$newLink = $_SESSION['agent_new_link'] ?? ''; unset($_SESSION['agent_new_link']);
$today = date('Y-m-d 00:00:00');
$spentToday = agent_spent_eur($today);
$spentMonth = agent_spent_eur(date('Y-m-01 00:00:00'));
try {
    $items = db_fetch_all('SELECT * FROM agent_items ORDER BY id DESC LIMIT 40');
    $log   = db_fetch_all('SELECT * FROM agent_log ORDER BY id DESC LIMIT 30');
    $since = agent_get('agent_start') ?: date('Y-m-d');
    $st = db_fetch("SELECT COUNT(*) AS n,
            SUM(category = 'pub_spam') AS spam,
            SUM(draft_body IS NOT NULL) AS drafts,
            SUM(verdict = 'bon') AS bon, SUM(verdict = 'retouche') AS retouche, SUM(verdict = 'refuse') AS refuse,
            SUM(category_fixed IS NOT NULL) AS fixed,
            SUM(status = 'sent') AS sent,
            SUM(cost_usd) AS cost, SUM(source = 'mail' OR source = 'site') AS total
        FROM agent_items WHERE created_at >= ?", [$since.' 00:00:00']) ?? [];
} catch (Throwable $e) { $items = []; $log = []; $st = []; $since = date('Y-m-d'); }
$statusLabels = ['new' => '⏳ à traiter', 'filtered' => '🗑️ écarté', 'ignored' => '— ignoré', 'classified' => '📁 rangé', 'pending' => '✉️ brouillon en attente',
                 'approved' => '👍 jugé bon (test)', 'sending' => '📤 envoi…', 'sent' => '✅ envoyé', 'refused' => '❌ refusé', 'error' => '⚠️ erreur'];
$claudeOk = claude_is_configured();
?>
<div class="admin-page-toolbar">
  <div>
    <div class="admin-breadcrumb">Croissance</div>
    <h1 class="admin-page-title">🤖 Agent relation client</h1>
    <p class="admin-page-subtitle">Trie vos deux boîtes mail et les demandes du site, range chaque mail dans un dossier, prépare des brouillons de réponse et vous les envoie sur Telegram. <strong>Rien ne part sans votre accord.</strong></p>
  </div>
</div>

<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:.75rem;margin-bottom:1.25rem;">
  <?php foreach ([
      ['État', agent_on() ? '🟢 En marche' : '⚪ Arrêté'],
      ['Mode', agent_live() ? '📤 Envoi activé' : '🧪 Test : rien ne part'],
      ['Coût IA aujourd\'hui', number_format($spentToday, 2, ',', ' ').' € / '.e($g('agent_daily_cap')).' €'],
      ['Coût IA ce mois', number_format($spentMonth, 2, ',', ' ').' €'],
      ['Telegram', agent_tg_ready() ? '🟢 Relié' : '⚪ Non relié'],
  ] as [$l, $v]): ?>
  <div class="admin-panel" style="padding:.8rem 1rem;margin:0;"><div style="font-size:.78rem;color:#666;"><?= e($l) ?></div><div style="font-weight:700;margin-top:.2rem;"><?= $v ?></div></div>
  <?php endforeach; ?>
</div>
<?php if (!$claudeOk): ?>
<div style="background:#fff7ed;border:1px solid #fed7aa;border-radius:10px;padding:1rem 1.25rem;margin-bottom:1.25rem;font-size:.9rem;color:#9a3412;">
  <strong>Clé de l'API Claude absente.</strong> Sans elle, l'agent ne fait que le tri sans IA (newsletters, spam signalé). Saisissez-la plus bas, dans « Fonctionnement ».
</div>
<?php endif; ?>
<p style="font-size:.85rem;color:#555;">Dernier passage : <strong><?= e(integration_setting('agent_last_run', 'jamais')) ?></strong></p>

<form method="post" class="admin-stack" autocomplete="off">
<input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

<section class="admin-panel" id="marche">
  <div class="admin-panel__head"><h2>⚙️ Fonctionnement</h2><p>En « envoi activé », chaque brouillon arrive sur Telegram avec un bouton <strong>Envoyer</strong> : rien ne part sans votre appui. Le mode test sert à essayer sans aucun risque d'envoi.</p></div>
  <div class="admin-panel__body">
    <label class="admin-field admin-field--check"><div class="check-row"><input type="checkbox" name="agent_enabled" value="1" <?= agent_on() ? 'checked' : '' ?>> Agent en marche (passage toutes les 5 minutes environ)</div></label>
    <div class="admin-form-grid admin-form-grid--2">
      <label class="admin-field"><span>Mode</span><select name="agent_mode">
        <option value="test" <?= !agent_live() ? 'selected' : '' ?>>🧪 Test : brouillons seulement, rien ne part</option>
        <option value="live" <?= agent_live() ? 'selected' : '' ?>>📤 Envoi activé : bouton « Envoyer » dans Telegram</option>
      </select></label>
      <label class="admin-field"><span>Rangement des mails</span><select name="agent_filing">
        <option value="copy" <?= $g('agent_filing') !== 'move' ? 'selected' : '' ?>>Copier dans le dossier (le mail reste aussi dans la boîte de réception)</option>
        <option value="move" <?= $g('agent_filing') === 'move' ? 'selected' : '' ?>>Déplacer dans le dossier (la boîte de réception se vide)</option>
      </select></label>
      <label class="admin-field"><span>Plafond de dépense IA par jour (€, 0 = sans plafond)</span><input type="text" name="agent_daily_cap" value="<?= e($g('agent_daily_cap')) ?>" inputmode="decimal"></label>
      <label class="admin-field"><span>Taux dollar → euro (les prix de l'API sont en dollars)</span><input type="text" name="agent_usd_eur" value="<?= e($g('agent_usd_eur')) ?>" inputmode="decimal"></label>
    </div>
    <label class="admin-field"><span>Clé de l'API Claude <?= $claudeOk ? '— <b style="color:#15803d;">configurée</b>' : '— non configurée' ?></span><input type="password" name="claude_api_key" value="" autocomplete="new-password" placeholder="Laisser vide pour ne pas changer (sk-ant-…)">
      <small style="color:#888;">Pensez aussi à fixer une limite de dépense mensuelle sur console.anthropic.com (Billing → Limits).</small></label>
    <label class="admin-field"><span>Signature ajoutée sous chaque réponse</span><textarea name="agent_signature" rows="3"><?= e($g('agent_signature')) ?></textarea></label>
  </div>
</section>

<section class="admin-panel" id="prix">
  <div class="admin-panel__head"><h2>💶 Prix, délais et disponibilités</h2><p>L'agent peut annoncer <strong>ces prix et ces délais, et seulement eux</strong>. Les brouillons qui en contiennent sont signalés sur Telegram pour que vous les relisiez avant d'appuyer sur Envoyer. Quand une information lui manque, il écrit <b>[à compléter : …]</b> ou <b>[…, à confirmer]</b> : le bouton Envoyer n'apparaît qu'une fois ce passage corrigé avec « Modifier ».</p></div>
  <div class="admin-panel__body">
    <label class="admin-field"><span>Tarifs que l'agent peut annoncer</span><textarea name="agent_prices" rows="9"><?= e($g('agent_prices')) ?></textarea>
      <small style="color:#b45309;">⚠️ Pré-rempli avec les « tarifs habituels » de votre outil Pennylane. Vérifiez-les : la grille de l'espace dispatcher (Tarifs) contient d'autres montants.</small></label>
    <label class="admin-field"><span>Délais et disponibilités que l'agent peut annoncer</span><textarea name="agent_delays" rows="4" placeholder="Exemple : urgence à Dole et alentours : technicien sur place en général sous 1 h 30"><?= e($g('agent_delays')) ?></textarea></label>
  </div>
</section>

<section class="admin-panel" id="historique">
  <div class="admin-panel__head"><h2>🗂️ Anciens mails</h2><p>Par défaut, l'agent lit les mails reçus après sa mise en marche. Il peut aussi trier et ranger vos anciens mails : ceux des derniers jours reçoivent un brouillon de réponse, les plus anciens sont seulement rangés (pas de message Telegram pour eux).</p></div>
  <div class="admin-panel__body">
    <div class="admin-form-grid admin-form-grid--2">
      <label class="admin-field"><span>Lire aussi les mails des … derniers jours (0 = aucun)</span><input type="number" name="agent_history_days" min="0" max="3650" value="<?= (int)$g('agent_history_days') ?>"></label>
      <label class="admin-field"><span>Brouillon de réponse pour les mails de moins de … jours</span><input type="number" name="agent_history_reply_days" min="1" max="365" value="<?= (int)$g('agent_history_reply_days') ?>"></label>
    </div>
    <p style="font-size:.82rem;color:#666;">Environ 70 à 120 mails par heure sont traités (davantage avec la tâche planifiée). Le tri d'un mail coûte une fraction de centime ; le plafond par jour s'applique aussi aux anciens mails.</p>
  </div>
</section>

<section class="admin-panel" id="boites">
  <div class="admin-panel__head"><h2>📬 Boîtes mail</h2><p>L'agent lit seulement les mails reçus <strong>après sa mise en marche</strong>, sans les marquer comme lus. Les mots de passe restent sur le serveur, comme la clé Pennylane : ils ne s'affichent plus jamais et n'apparaissent dans aucun journal.</p></div>
  <div class="admin-panel__body">
  <?php foreach ($accounts as $a): $x = $acc[$a]; $err = integration_setting('agent_'.$a.'_error', ''); ?>
    <fieldset style="border:1px solid #e5e7eb;border-radius:10px;padding:1rem;margin-bottom:1rem;">
      <legend style="font-weight:700;padding:0 .4rem;"><?= e($x['label']) ?> <?= $x['has_pass'] ? '<span style="color:#15803d;font-weight:600;">— mot de passe enregistré</span>' : '<span style="color:#b45309;font-weight:600;">— mot de passe à saisir</span>' ?></legend>
      <?php if ($err !== ''): ?><p style="color:#b91c1c;font-size:.85rem;">⚠️ Dernière erreur : <?= e($err) ?></p><?php endif; ?>
      <label class="admin-field admin-field--check"><div class="check-row"><input type="checkbox" name="agent_<?= $a ?>_on" value="1" <?= $x['on'] ? 'checked' : '' ?>> Lire cette boîte</div></label>
      <div class="admin-form-grid admin-form-grid--2">
        <label class="admin-field"><span>Adresse</span><input type="text" name="agent_<?= $a ?>_user" value="<?= e($x['user']) ?>"></label>
        <label class="admin-field"><span><?= $a === 'gmail' ? 'Mot de passe d\'application (16 lettres)' : 'Mot de passe de la boîte' ?></span><input type="password" name="agent_<?= $a ?>_pass" value="" autocomplete="new-password" placeholder="<?= $x['has_pass'] ? 'Laisser vide pour ne pas changer' : '' ?>"></label>
      </div>
      <?php if ($a === 'gmail'): ?>
      <details style="margin:.25rem 0 .75rem;"><summary style="cursor:pointer;font-weight:600;">Comment obtenir le mot de passe d'application Gmail</summary>
        <ol style="font-size:.85rem;color:#444;line-height:1.7;">
          <li>Ouvrez <b>myaccount.google.com</b> avec le compte <?= e($x['user']) ?>.</li>
          <li>Menu <b>Sécurité</b> : activez la <b>Validation en deux étapes</b> si ce n'est pas déjà fait (obligatoire).</li>
          <li>Cherchez <b>« Mots de passe des applications »</b> dans la barre de recherche du compte.</li>
          <li>Nom de l'application : <b>Agent EMAE</b>, puis <b>Créer</b>. Google affiche 16 lettres : collez-les ici (les espaces sont retirés tout seuls).</li>
          <li>Vérifiez que l'accès IMAP est autorisé : Gmail → ⚙️ → Voir tous les paramètres → Transfert et POP/IMAP → <b>Activer IMAP</b>.</li>
        </ol>
        <p style="font-size:.82rem;color:#666;">Vous pouvez supprimer ce mot de passe à tout moment depuis la même page Google : l'agent perd alors l'accès, votre mot de passe habituel ne change pas.</p>
      </details>
      <?php else: ?>
      <p style="font-size:.82rem;color:#666;margin:.25rem 0 .75rem;">C'est le mot de passe de la boîte <?= e($x['user']) ?> (celui de votre messagerie, pas celui de cPanel). Vous pouvez le changer dans cPanel → Comptes de messagerie.</p>
      <?php endif; ?>
      <details><summary style="cursor:pointer;font-size:.85rem;">Réglages des serveurs (à ne changer qu'en cas de problème)</summary>
        <div class="admin-form-grid admin-form-grid--2" style="margin-top:.5rem;">
          <label class="admin-field"><span>Serveur de lecture (IMAP)</span><input type="text" name="agent_<?= $a ?>_host" value="<?= e($x['host']) ?>"></label>
          <label class="admin-field"><span>Port</span><input type="number" name="agent_<?= $a ?>_port" value="<?= (int)$x['port'] ?>"></label>
          <label class="admin-field"><span>Serveur d'envoi (SMTP)</span><input type="text" name="agent_<?= $a ?>_smtp_host" value="<?= e($x['smtp_host']) ?>"></label>
          <label class="admin-field"><span>Port</span><input type="number" name="agent_<?= $a ?>_smtp_port" value="<?= (int)$x['smtp_port'] ?>"></label>
        </div>
        <label class="check-row" style="font-size:.85rem;"><input type="checkbox" name="agent_<?= $a ?>_verify" value="1" <?= $x['verify'] ? 'checked' : '' ?>> Vérifier le certificat du serveur (à décocher seulement si le test l'exige)</label>
        <?php if ($x['has_pass']): ?><label class="check-row" style="font-size:.85rem;"><input type="checkbox" name="agent_<?= $a ?>_pass_clear" value="1"> Effacer le mot de passe enregistré</label><?php endif; ?>
      </details>
      <button class="admin-btn admin-btn--secondary" type="submit" name="act" value="test_<?= $a ?>" formnovalidate style="margin-top:.75rem;" <?= $x['has_pass'] ? '' : 'disabled title="Enregistrez d\'abord le mot de passe"' ?>>🔌 Tester la connexion (rien n'est envoyé)</button>
    </fieldset>
  <?php endforeach; ?>
  </div>
</section>

<section class="admin-panel" id="telegram">
  <div class="admin-panel__head"><h2>📱 Telegram (validation depuis votre téléphone)</h2><p>Chaque brouillon arrive sur Telegram avec trois boutons : <b>Envoyer</b> (ou « Bon tel quel » en mode test), <b>Modifier</b>, <b>Refuser</b>. Sans réponse de votre part, rien ne part.</p></div>
  <div class="admin-panel__body">
    <details <?= agent_tg_token() === '' ? 'open' : '' ?>><summary style="cursor:pointer;font-weight:600;">Créer le bot (2 minutes, depuis votre téléphone)</summary>
      <ol style="font-size:.85rem;color:#444;line-height:1.7;">
        <li>Installez Telegram et ouvrez la conversation avec <b>@BotFather</b> (le compte officiel, avec la coche bleue).</li>
        <li>Envoyez <b>/newbot</b>, donnez un nom (par exemple <i>EMAE Agent</i>), puis un identifiant qui finit par <i>bot</i> (par exemple <i>emae_agent_bot</i>).</li>
        <li>BotFather répond avec un <b>jeton</b> qui ressemble à <code>123456789:AAH…</code> : collez-le ci-dessous et enregistrez.</li>
        <li>Revenez ici et appuyez sur <b>« Relier mon téléphone »</b>.</li>
      </ol>
    </details>
    <label class="admin-field"><span>Jeton du bot <?= agent_tg_token() !== '' ? '— <b style="color:#15803d;">enregistré</b>' : '' ?></span><input type="password" name="telegram_bot_token" value="" autocomplete="new-password" placeholder="<?= agent_tg_token() !== '' ? 'Laisser vide pour ne pas changer' : '123456789:AAH…' ?>"></label>
    <?php if ($tgLink): ?>
      <div style="background:#ecfdf5;border:1px solid #a7f3d0;border-radius:10px;padding:1rem;margin:.5rem 0;">
        <strong>Dernière étape :</strong> sur votre téléphone, ouvrez ce lien puis appuyez sur <b>Démarrer</b> :<br>
        <a href="https://t.me/<?= e($tgLink['username']) ?>?start=<?= e($tgLink['code']) ?>" target="_blank" rel="noopener" style="font-weight:700;">t.me/<?= e($tgLink['username']) ?>?start=<?= e($tgLink['code']) ?></a><br>
        <small>Ou envoyez au bot le message : <code>/start <?= e($tgLink['code']) ?></code>. Le bot répond « C'est relié ». Rechargez ensuite cette page.</small>
      </div>
    <?php endif; ?>
    <div style="display:flex;gap:.5rem;flex-wrap:wrap;margin-top:.5rem;">
      <button class="admin-btn admin-btn--secondary" type="submit" name="act" value="tg_setup" formnovalidate <?= agent_tg_token() === '' ? 'disabled title="Enregistrez d\'abord le jeton"' : '' ?>>🔗 <?= agent_tg_ready() ? 'Relier un autre téléphone' : 'Relier mon téléphone' ?></button>
      <button class="admin-btn admin-btn--secondary" type="submit" name="act" value="tg_test" formnovalidate <?= agent_tg_ready() ? '' : 'disabled' ?>>📨 Envoyer un message de test</button>
    </div>
  </div>
</section>

<section class="admin-panel" id="dossiers">
  <div class="admin-panel__head"><h2>📁 Dossiers de tri</h2><p>Chaque mail est rangé dans un sous-dossier de « <?= e($g('agent_parent_folder')) ?> », créé tout seul dans la boîte. Seuls Urgences, Client mécontent, Devis et Autre reçoivent un brouillon de réponse.</p></div>
  <div class="admin-panel__body">
    <label class="admin-field" style="max-width:320px;"><span>Dossier principal</span><input type="text" name="agent_parent_folder" value="<?= e($g('agent_parent_folder')) ?>"></label>
    <div class="admin-form-grid admin-form-grid--2">
      <?php foreach (agent_categories() as $cat => [$label, $reply]): ?>
      <label class="admin-field"><span><?= e($label) ?><?= $reply ? ' (brouillon de réponse)' : '' ?></span><input type="text" name="folder_<?= e($cat) ?>" value="<?= e(agent_folder_name($cat)) ?>"></label>
      <?php endforeach; ?>
    </div>
    <div class="admin-form-grid admin-form-grid--2">
      <label class="admin-field"><span>Expéditeurs à ne jamais écarter (une adresse ou un domaine par ligne)</span><textarea name="agent_allow" rows="3" placeholder="syndic-exemple.fr"><?= e($g('agent_allow')) ?></textarea></label>
      <label class="admin-field"><span>Expéditeurs à classer d'office en Pub-Spam</span><textarea name="agent_block" rows="3" placeholder="prospection-exemple.com"><?= e($g('agent_block')) ?></textarea></label>
    </div>
  </div>
</section>

<div class="admin-savebar"><button class="admin-btn admin-btn--primary" type="submit" name="act" value="save">💾 Enregistrer</button></div>
</form>

<section class="admin-panel" id="lien" style="margin-top:1.5rem;">
  <div class="admin-panel__head"><h2>🔗 Lien « suite à votre appel »</h2><p>Page aux couleurs EMAE où le client complète ses coordonnées, l'adresse, la panne, des photos et ses disponibilités. L'agent glisse ce lien dans ses brouillons ; vous pouvez aussi en créer un ici pour l'envoyer par SMS après un appel.</p></div>
  <div class="admin-panel__body">
    <form method="post" class="admin-form-grid admin-form-grid--2" style="align-items:end;">
      <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
      <label class="admin-field"><span>Nom du client (facultatif, prérempli)</span><input type="text" name="l_full_name"></label>
      <label class="admin-field"><span>Téléphone (facultatif)</span><input type="text" inputmode="tel" name="l_phone"></label>
      <label class="admin-field"><span>E-mail (facultatif)</span><input type="email" name="l_email"></label>
      <div><button class="admin-btn admin-btn--secondary" type="submit" name="act" value="link">Créer un lien personnel</button></div>
    </form>
    <?php if ($newLink !== ''): ?><p style="margin-top:.75rem;">Lien à copier : <input type="text" value="<?= e($newLink) ?>" readonly onclick="this.select()" style="width:100%;font-family:monospace;"></p><?php endif; ?>
    <p style="font-size:.85rem;color:#555;margin-top:.5rem;">Lien sans préremplissage, valable pour tous : <code><?= e(notif_abs_url(route_url('suite-appel'))) ?></code></p>
  </div>
</section>

<section class="admin-panel" id="bilan" style="margin-top:1.5rem;">
  <div class="admin-panel__head"><h2>🧪 Bilan depuis la mise en marche (<?= e(date('d/m/Y', strtotime($since))) ?>)</h2><p>Les chiffres qui permettent de décider s'il faut passer en « envoi activé ».</p></div>
  <div class="admin-panel__body">
    <?php $n = (int)($st['total'] ?? 0); $dr = (int)($st['drafts'] ?? 0); $judged = (int)($st['bon'] ?? 0) + (int)($st['retouche'] ?? 0) + (int)($st['refuse'] ?? 0); ?>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:.75rem;">
      <?php foreach ([
          ['Messages traités', (string)$n],
          ['Pub / spam écartés', (string)(int)($st['spam'] ?? 0)],
          ['Tri corrigé par vous', (string)(int)($st['fixed'] ?? 0).($n ? ' ('.round(100 * (int)($st['fixed'] ?? 0) / $n).' %)' : '')],
          ['Brouillons rédigés', (string)$dr],
          ['Bons tels quels', (string)(int)($st['bon'] ?? 0).($judged ? ' ('.round(100 * (int)($st['bon'] ?? 0) / $judged).' %)' : '')],
          ['Retouchés / refusés', (int)($st['retouche'] ?? 0).' / '.(int)($st['refuse'] ?? 0)],
          ['Réponses envoyées', (string)(int)($st['sent'] ?? 0)],
          ['Coût moyen par message', $n ? number_format(agent_eur((float)($st['cost'] ?? 0)) / $n * 100, 2, ',', ' ').' centimes' : '—'],
      ] as [$l, $v]): ?>
      <div style="border:1px solid #e5e7eb;border-radius:10px;padding:.7rem .9rem;"><div style="font-size:.78rem;color:#666;"><?= e($l) ?></div><div style="font-weight:700;font-size:1.1rem;"><?= e($v) ?></div></div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="admin-panel" id="suivi" style="margin-top:1.5rem;">
  <div class="admin-panel__head"><h2>📋 Derniers messages traités</h2><p>Une catégorie fausse ? Corrigez-la ici : le mail sera rangé dans le bon dossier au prochain passage s'il ne l'est pas encore, et la correction compte dans le bilan.</p></div>
  <div class="admin-panel__body">
    <form method="post" style="margin-bottom:.75rem;"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><button class="admin-btn admin-btn--secondary" type="submit" name="act" value="run">▶️ Lancer un passage maintenant</button></form>
    <?php if (!$items): ?><p style="color:#666;">Aucun message pour l'instant.</p><?php else: ?>
    <div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Reçu</th><th>Boîte</th><th>De / objet</th><th>Catégorie</th><th>État</th><th>Coût</th></tr></thead><tbody>
    <?php foreach ($items as $it): $cat = (string)($it['category_fixed'] ?: $it['category']); ?>
      <tr>
        <td style="white-space:nowrap;"><?= e(date('d/m H:i', strtotime((string)($it['received_at'] ?: $it['created_at'])))) ?></td>
        <td><?= e($it['source'] === 'site' ? 'Site' : ($acc[$it['account']]['label'] ?? $it['account'])) ?></td>
        <td style="max-width:360px;"><strong><?= e($it['from_name'] ?: $it['from_email']) ?></strong><br><span style="color:#555;"><?= e(mb_strimwidth((string)$it['subject'], 0, 80, '…')) ?></span>
          <?php if (trim((string)$it['summary']) !== ''): ?><br><small style="color:#666;"><?= e($it['summary']) ?></small><?php endif; ?>
          <?php if (trim((string)$it['filter_reason']) !== ''): ?><br><small style="color:#888;">Tri sans IA : <?= e($it['filter_reason']) ?></small><?php endif; ?>
          <?php if ($it['draft_body']): ?><details><summary style="cursor:pointer;font-size:.82rem;">Voir le brouillon</summary><pre style="white-space:pre-wrap;font-size:.82rem;background:#f8fafc;padding:.6rem;border-radius:8px;"><?= e(agent_full_body($it)) ?></pre>
            <?php if ($it['draft_note']): ?><small>📝 <?= e($it['draft_note']) ?></small><?php endif; ?>
            <?php if ($it['draft_warning']): ?><br><small style="color:#b45309;">⚠️ <?= e($it['draft_warning']) ?></small><?php endif; ?></details><?php endif; ?>
          <?php if ($it['error']): ?><br><small style="color:#b91c1c;">⚠️ <?= e($it['error']) ?></small><?php endif; ?>
        </td>
        <td><?php if ($cat !== ''): ?>
          <form method="post" style="display:flex;gap:.3rem;"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="act" value="fix"><input type="hidden" name="id" value="<?= (int)$it['id'] ?>">
            <select name="category" onchange="this.form.submit()" style="font-size:.82rem;">
              <?php foreach (agent_categories() as $ck => [$cl]): ?><option value="<?= e($ck) ?>" <?= $ck === $cat ? 'selected' : '' ?>><?= e($cl) ?></option><?php endforeach; ?>
            </select></form>
          <?php if ($it['category_fixed']): ?><small style="color:#888;">corrigé (IA : <?= e(agent_cat_label((string)$it['category'])) ?>)</small><?php endif; ?>
        <?php else: ?>—<?php endif; ?></td>
        <td style="white-space:nowrap;"><?= e($statusLabels[$it['status']] ?? $it['status']) ?>
          <?php if ($it['status'] === 'error'): ?><form method="post"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="act" value="retry"><input type="hidden" name="id" value="<?= (int)$it['id'] ?>"><button class="admin-btn admin-btn--secondary" style="font-size:.75rem;padding:.2rem .5rem;">Réessayer</button></form><?php endif; ?></td>
        <td style="white-space:nowrap;"><?= (float)$it['cost_usd'] > 0 ? e(number_format(agent_eur((float)$it['cost_usd']) * 100, 2, ',', ' ')).' c' : '—' ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody></table></div>
    <?php endif; ?>
  </div>
</section>

<section class="admin-panel" id="journal" style="margin-top:1.5rem;">
  <div class="admin-panel__head"><h2>🧾 Journal des actions</h2><p>Chaque action de l'agent, avec sa raison, son résultat et son coût réel.</p></div>
  <div class="admin-panel__body">
    <?php if (!$log): ?><p style="color:#666;">Rien pour l'instant.</p><?php else: ?>
    <div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Date</th><th>Action</th><th>Raison</th><th>Résultat</th><th>Modèle</th><th>Coût</th></tr></thead><tbody>
      <?php foreach ($log as $l): ?>
      <tr><td style="white-space:nowrap;"><?= e(date('d/m H:i:s', strtotime((string)$l['created_at']))) ?></td><td><?= e($l['action']) ?><?= $l['item_id'] ? ' <small style="color:#888;">#'.(int)$l['item_id'].'</small>' : '' ?></td>
        <td><?= e((string)$l['reason']) ?></td><td><?= e((string)$l['result']) ?></td><td><small><?= e(str_replace('claude-', '', (string)$l['model'])) ?></small></td>
        <td style="white-space:nowrap;"><?= (float)$l['cost_usd'] > 0 ? e(number_format(agent_eur((float)$l['cost_usd']) * 100, 3, ',', ' ')).' c' : '—' ?></td></tr>
      <?php endforeach; ?>
    </tbody></table></div>
    <?php endif; ?>
  </div>
</section>
<?php require __DIR__ . '/partials/footer.php'; ?>
