<?php
declare(strict_types=1);
/**
 * Qualification d'un appel entrant, assistée par Claude (questionnaire standard en repli).
 * Étapes : conversation → résumé modifiable → création de la fiche « À assigner ».
 */
$pageTitle   = 'Qualifier un appel';
$dispSection = 'qualify';
require __DIR__.'/partials/header.php';

$fields  = qual_fields();
$catsCfg = intervention_category_config();
$sid     = (int)($_GET['s'] ?? $_POST['s'] ?? 0);
$isAjax  = ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'fetch';

/** Réponse JSON (appels fetch de la conversation). */
function qual_json(array $data): void
{
    while (ob_get_level() > 0) ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/** Session de qualification, limitée aux sessions encore modifiables. */
function qual_session_or_fail(int $sid, bool $ajax): array
{
    $s = $sid > 0 ? qual_load($sid) : null;
    if (!$s) {
        if ($ajax) qual_json(['ok' => false, 'error' => 'Qualification introuvable.']);
        flash('error', 'Qualification introuvable.');
        redirect_to('dispatcher/qualify.php');
    }
    return $s;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string)($_POST['action'] ?? '');

    if ($action === 'start') {
        $quoteId = (int)($_POST['quote_id'] ?? 0);
        $newId = qual_start((int)$disp['id'], $quoteId > 0 ? $quoteId : null);
        redirect_to('dispatcher/qualify.php?s='.$newId);
    }

    $s = qual_session_or_fail($sid, $isAjax);
    if ($s['status'] === 'convertie' && $action !== '') {
        flash('error', 'Cette qualification a déjà donné lieu à une fiche.');
        redirect_to('dispatcher/intervention_view.php?id='.(int)$s['intervention_id']);
    }

    if ($action === 'answer') {
        qual_answer($s, (string)($_POST['answer'] ?? ''));
        if ($isAjax) qual_json(['ok' => true]);
        redirect_to('dispatcher/qualify.php?s='.$sid.'#bas');
    }

    if ($action === 'fields') {
        // Correction directe des informations par le dispatcher.
        foreach (array_keys($fields) as $k) {
            if (array_key_exists('f_'.$k, $_POST)) {
                $v = mb_substr(trim((string)$_POST['f_'.$k]), 0, 1000);
                if ($v === '') unset($s['state']['answers'][$k]); else qual_set_answer($s['state'], $k, $v);
            }
        }
        qual_save($s);
        flash('success', 'Informations mises à jour.');
        redirect_to('dispatcher/qualify.php?s='.$sid);
    }

    if ($action === 'questionnaire') {
        $s['mode'] = 'questionnaire';
        $s['state']['notice'] = 'Questionnaire standard activé par le dispatcher.';
        qual_next_question($s);
        redirect_to('dispatcher/qualify.php?s='.$sid.'#bas');
    }

    if ($action === 'summarize') {
        qual_summarize($s);
        redirect_to('dispatcher/qualify.php?s='.$sid);
    }

    if ($action === 'back') {
        $s['status'] = 'en_cours';
        qual_save($s);
        redirect_to('dispatcher/qualify.php?s='.$sid.'#bas');
    }

    if ($action === 'abandon') {
        $s['status'] = 'abandonnee';
        qual_save($s);
        integration_log('audit', 'qualification abandonnée #'.$sid, ['dispatcher' => (int)$disp['id']]);
        flash('success', 'Qualification abandonnée.');
        redirect_to('dispatcher/qualify.php');
    }

    if ($action === 'recalc' || $action === 'create') {
        // Le résumé modifié par le dispatcher remplace celui proposé.
        $sum = $s['summary'] ?? qual_summary_from_answers($s['state'], qual_normalize_category((string)($s['state']['answers']['category'] ?? '')));
        foreach (array_keys($sum['client']) as $k) {
            if (isset($_POST['c_'.$k])) $sum['client'][$k] = mb_substr(trim((string)$_POST['c_'.$k]), 0, 500);
        }
        $cat = (string)($_POST['i_category'] ?? '');
        $sum['intervention']['category']         = isset($catsCfg[$cat]) ? $cat : (string)$sum['intervention']['category'];
        $sum['intervention']['fault_type']       = mb_substr(trim((string)($_POST['i_fault_type'] ?? '')), 0, 190);
        $sum['intervention']['description']      = mb_substr(trim((string)($_POST['i_description'] ?? '')), 0, 5000);
        $sum['intervention']['urgency']          = !empty($_POST['i_urgency']);
        $sum['intervention']['priority']         = in_array($_POST['i_priority'] ?? '', ['normale', 'haute', 'urgente'], true) ? (string)$_POST['i_priority'] : 'normale';
        $sum['intervention']['duration_minutes'] = max(15, min(600, (int)($_POST['i_duration'] ?? 60)));
        $sum['intervention']['skills']           = array_values(array_filter(array_map('trim', explode(',', (string)($_POST['i_skills'] ?? '')))));
        $sum['intervention']['materials']        = array_values(array_filter(array_map('trim', explode("\n", (string)($_POST['i_materials'] ?? '')))));
        $lines = [];
        foreach ((array)($_POST['l_code'] ?? []) as $i => $code) {
            $code = strtoupper(trim((string)$code));
            $qty  = (float)str_replace(',', '.', (string)($_POST['l_qty'][$i] ?? '1'));
            if ($code !== '' && $qty > 0) $lines[] = ['code' => $code, 'qty' => $qty];
        }
        $sum['estimate']['lines'] = $lines;
        $s['summary'] = $sum;
        $s['status']  = 'resume';
        qual_save($s);

        if ($action === 'recalc') redirect_to('dispatcher/qualify.php?s='.$sid.'#estimation');

        /* ── Création de la fiche ── */
        $c = $sum['client'];
        $errs = [];
        if ($c['lastname'] === '') $errs[] = 'le nom';
        if ($c['phone'] === '') $errs[] = 'le téléphone';
        if ($c['address'] === '' || $c['city'] === '') $errs[] = 'l\'adresse';
        if ($sum['intervention']['description'] === '') $errs[] = 'la description';
        if ($errs) {
            flash('error', 'Complétez '.implode(', ', $errs).' avant de créer la fiche.');
            redirect_to('dispatcher/qualify.php?s='.$sid);
        }
        $clientType = in_array($c['client_type'], ['particulier', 'professionnel'], true) ? $c['client_type'] : null;
        $access = trim(implode(' — ', array_filter([
            $c['interphone'] !== '' ? 'Interphone : '.$c['interphone'] : '',
            $c['access_info'],
        ])));

        // Client : réutilisation d'un client connu (jamais de doublon silencieux).
        $clientId = 0;
        if (($_POST['client_choice'] ?? '') === 'existing') {
            $clientId = (int)($_POST['existing_client_id'] ?? 0);
            $existing = $clientId > 0 ? db_fetch('SELECT * FROM clients WHERE id = ?', [$clientId]) : null;
            if (!$existing) $clientId = 0;
            else {
                // Complète uniquement les informations manquantes de la fiche client.
                $fill = ['firstname' => $c['firstname'], 'email' => $c['email'], 'address' => $c['address'], 'postal_code' => $c['postal_code'],
                         'city' => $c['city'], 'floor' => $c['floor'], 'digicode' => $c['digicode'], 'access_info' => $access];
                $sets = []; $params = [];
                foreach ($fill as $col => $val) {
                    if (trim((string)$val) !== '' && trim((string)($existing[$col] ?? '')) === '') { $sets[] = $col.' = ?'; $params[] = $val; }
                }
                if ($sets) { $params[] = $clientId; db_execute('UPDATE clients SET '.implode(', ', $sets).' WHERE id = ?', $params); }
            }
        }
        if ($clientId === 0) {
            $clientId = create_client([
                'lastname' => $c['lastname'], 'firstname' => $c['firstname'], 'phone' => $c['phone'], 'email' => $c['email'],
                'address' => $c['address'], 'postal_code' => $c['postal_code'], 'city' => $c['city'],
                'floor' => $c['floor'], 'digicode' => $c['digicode'], 'access_info' => $access, 'notes' => '',
            ]);
        }
        if ($clientType !== null) db_execute('UPDATE clients SET client_type = ? WHERE id = ?', [$clientType, $clientId]);

        $iv = $sum['intervention'];
        $housing = $c['housing_over_2y'] === 'oui' ? 1 : ($c['housing_over_2y'] === 'non' ? 0 : null);
        $vat = iv_vat_rate(['housing_over_2y' => $housing], ['client_type' => $clientType]);
        $est = qual_estimate($lines, $vat);
        $notes = array_filter([
            $c['availability'] !== '' ? 'Disponibilités : '.$c['availability'] : '',
            $c['occupant'] !== 'inconnu' && $c['occupant'] !== '' ? 'Occupant : '.($c['occupant'] === 'proprietaire' ? 'propriétaire' : 'locataire') : '',
            !empty($s['state']['danger']['detected']) ? 'DANGER signalé à l\'appel : '.$s['state']['danger']['kind'] : '',
            $iv['skills'] ? 'Compétences : '.implode(', ', $iv['skills']) : '',
            $est['lines'] ? 'Estimation indicative donnée au client : '.money_fr($est['range_min']).' à '.money_fr($est['range_max']).' TTC ('
                .implode(', ', array_map(static fn($l) => $l['code'].($l['qty'] != 1 ? ' ×'.rtrim(rtrim(number_format((float)$l['qty'], 2, ',', ''), '0'), ',') : ''), $est['lines'])).')' : '',
        ]);
        $ivId = create_intervention([
            'client_id'         => $clientId,
            'dispatcher_id'     => (int)$disp['id'],
            'technician_id'     => null,
            'scheduled_date'    => '',
            'scheduled_time'    => '',
            'duration_estimate' => (int)$iv['duration_minutes'],
            'urgency'           => $iv['urgency'] ? 1 : 0,
            'priority'          => $iv['priority'],
            'category'          => $iv['category'],
            'type_label'        => $iv['fault_type'],
            'installation_type' => '',
            'fault_reported'    => $iv['fault_type'],
            'description'       => $iv['description'],
            'materials_needed'  => $iv['materials'] ? json_encode(array_map(static fn($m) => ['name' => $m], $iv['materials']), JSON_UNESCAPED_UNICODE) : '',
            'notes_admin'       => implode("\n", $notes),
            'quote_accepted'    => 0,
            'amount_ht'         => null,
            'payment_method'    => '',
            'status'            => 'a_assigner',
            'latitude'          => '',
            'longitude'         => '',
        ]);
        update_intervention($ivId, ['housing_over_2y' => $housing]);
        db_execute('UPDATE interventions SET quote_id = ?, qualification_id = ? WHERE id = ?', [$s['quote_id'] ?: null, $sid, $ivId]);
        log_intervention_history($ivId, null, 'a_assigner', 'dispatcher', (int)$disp['id'], (string)$disp['name'],
            'Création depuis la qualification d\'appel'.($s['mode'] === 'claude' ? ' (assistée par Claude)' : ''));
        if (!empty($s['quote_id'])) {
            try { db_execute("UPDATE quotes SET status = 'planifié' WHERE id = ? AND status IN ('nouveau','contacté')", [(int)$s['quote_id']]); } catch (Throwable $e) {}
        }
        $s['status'] = 'convertie';
        $s['intervention_id'] = $ivId;
        $s['client_id'] = $clientId;
        qual_save($s);
        try { geocode_missing_interventions(); } catch (Throwable $e) {}
        integration_log('audit', 'fiche créée depuis la qualification #'.$sid, ['intervention' => $ivId, 'dispatcher' => (int)$disp['id']]);
        flash('success', 'Fiche créée : elle est « À assigner ».');
        redirect_to('dispatcher/intervention_view.php?id='.$ivId);
    }
    redirect_to('dispatcher/qualify.php'.($sid ? '?s='.$sid : ''));
}

$csrf = csrf_token();

/* ═════════════ Accueil : démarrer un appel ═════════════ */
if ($sid <= 0) {
    $quoteId = (int)($_GET['quote'] ?? 0);
    $quote = $quoteId > 0 ? db_fetch('SELECT * FROM quotes WHERE id = ?', [$quoteId]) : null;
    try {
        $openSessions = db_fetch_all("SELECT q.*, d.name AS disp_name FROM qualification_sessions q LEFT JOIN dispatchers d ON d.id = q.dispatcher_id
            WHERE q.status IN ('en_cours','resume') AND q.created_at >= DATE_SUB(NOW(), INTERVAL 3 DAY) ORDER BY q.id DESC LIMIT 10");
    } catch (Throwable $e) { $openSessions = []; }
    $recentQuotes = qual_pending_quotes(8);
    ?>
<div class="d-topbar">
  <div>
    <button class="d-menu-toggle" id="d-menu-toggle" aria-label="Menu">☰</button>
    <div>
      <div class="d-topbar-title">Qualifier un appel</div>
      <div class="d-topbar-sub"><?= claude_is_configured() ? 'Claude vous propose les questions une par une' : 'Questionnaire standard (Claude n\'est pas configuré)' ?></div>
    </div>
  </div>
</div>
<div class="d-content" style="max-width:900px;">
  <div class="d-card" style="margin-bottom:1.1rem;">
    <div class="d-card-body" style="display:flex;gap:1rem;align-items:center;flex-wrap:wrap;justify-content:space-between;">
      <div>
        <div style="font-weight:700;font-size:1.05rem;"><?= $quote ? 'Rappeler '.e($quote['full_name']) : 'Nouvel appel entrant' ?></div>
        <div style="color:var(--d-t2);font-size:.88rem;margin-top:.2rem;">
          <?= $quote ? 'Demande du site du '.e(date('d/m/Y à H:i', strtotime((string)$quote['created_at']))).' : les informations connues sont reprises.' : 'Lisez chaque question au client, cliquez une réponse rapide ou tapez la réponse.' ?>
        </div>
      </div>
      <form method="post">
        <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
        <input type="hidden" name="action" value="start">
        <?php if ($quote): ?><input type="hidden" name="quote_id" value="<?= (int)$quote['id'] ?>"><?php endif; ?>
        <button type="submit" class="d-btn d-btn--primary d-btn--lg">Démarrer la qualification</button>
      </form>
    </div>
  </div>

  <?php if ($openSessions): ?>
  <div class="d-card" style="margin-bottom:1.1rem;">
    <div class="d-card-head"><div class="d-card-title">Qualifications en cours</div></div>
    <table class="d-table">
      <tbody>
      <?php foreach ($openSessions as $o): $st = json_decode((string)$o['state'], true) ?: []; $a = $st['answers'] ?? []; ?>
        <tr>
          <td><b><?= e(trim(($a['lastname'] ?? '').' '.($a['firstname'] ?? '')) ?: 'Client non renseigné') ?></b>
            <div style="font-size:.8rem;color:var(--d-t2);"><?= e($catsCfg[$a['category'] ?? '']['label'] ?? 'Métier à préciser') ?> · commencée le <?= e(date('d/m à H:i', strtotime((string)$o['created_at']))) ?> par <?= e((string)($o['disp_name'] ?? '—')) ?></div></td>
          <td style="text-align:right;"><?php if ((int)$o['danger']): ?><span class="qual-pill qual-pill--red">Danger</span> <?php endif; ?>
            <a class="d-btn d-btn--sm" href="<?= e(url_for('dispatcher/qualify.php?s='.(int)$o['id'])) ?>">Reprendre</a></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>

  <?php if ($recentQuotes): ?>
  <div class="d-card">
    <div class="d-card-head"><div class="d-card-title">Demandes reçues par le site</div></div>
    <table class="d-table">
      <tbody>
      <?php foreach ($recentQuotes as $q): ?>
        <tr>
          <td><b><?= e($q['full_name']) ?></b> · <?= e($q['phone']) ?>
            <div style="font-size:.8rem;color:var(--d-t2);"><?= e(trim((string)$q['service_type']) ?: 'Service non précisé') ?> · <?= e((string)$q['city']) ?> · <?= e(date('d/m H:i', strtotime((string)$q['created_at']))) ?></div>
            <div style="font-size:.82rem;margin-top:.2rem;"><?= e(mb_strimwidth(trim((string)$q['message']), 0, 160, '…')) ?></div></td>
          <td style="text-align:right;"><a class="d-btn d-btn--sm d-btn--primary" href="<?= e(url_for('dispatcher/qualify.php?quote='.(int)$q['id'])) ?>">Qualifier</a></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>
<?php
    require __DIR__.'/partials/qualify_style.php';
    require __DIR__.'/partials/footer.php';
    return;
}

/* ═════════════ Session ═════════════ */
$s = qual_session_or_fail($sid, false);
if ($s['status'] === 'convertie' && $s['intervention_id']) redirect_to('dispatcher/intervention_view.php?id='.(int)$s['intervention_id']);
$state   = $s['state'];
$answers = $state['answers'] ?? [];
$missing = qual_missing($state);
$danger  = !empty($state['danger']['detected']) ? $state['danger'] : null;
$known   = qual_find_client((string)($s['summary']['client']['phone'] ?? $answers['phone'] ?? ''), (string)($s['summary']['client']['email'] ?? $answers['email'] ?? ''));
$last    = qual_last_assistant($state);
$quote   = $s['quote_id'] ? db_fetch('SELECT * FROM quotes WHERE id = ?', [(int)$s['quote_id']]) : null;
?>
<div class="d-topbar">
  <div>
    <button class="d-menu-toggle" id="d-menu-toggle" aria-label="Menu">☰</button>
    <div>
      <div class="d-topbar-title"><?= $s['status'] === 'resume' ? 'Résumé de l\'appel' : 'Qualification en cours' ?></div>
      <div class="d-topbar-sub">
        <?= $s['mode'] === 'claude' ? 'Questions proposées par Claude — vous gardez la main' : 'Questionnaire standard' ?>
        <?= $quote ? ' · demande du site de '.e($quote['full_name']) : '' ?>
      </div>
    </div>
  </div>
  <div class="d-topbar-actions">
    <form method="post" onsubmit="return confirm('Abandonner cette qualification ?');">
      <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>"><input type="hidden" name="s" value="<?= $sid ?>">
      <button type="submit" name="action" value="abandon" class="d-btn d-btn--ghost d-btn--sm">Abandonner</button>
    </form>
  </div>
</div>

<div class="d-content">
  <?php if ($danger): ?>
  <div class="qual-danger" role="alert">
    <div class="qual-danger-title">DANGER — <?= e(ucfirst((string)$danger['kind'])) ?> : lisez ces consignes au client maintenant</div>
    <ol><?php foreach ($danger['instructions'] as $ins): ?><li><?= e((string)$ins) ?></li><?php endforeach; ?></ol>
  </div>
  <?php endif; ?>

  <?php if (!empty($state['notice'])): ?>
  <div class="d-flash" style="background:#fff7ed;border:1px solid #fed7aa;color:#9a3412;"><?= e((string)$state['notice']) ?></div>
  <?php endif; ?>

  <?php if ($known): ?>
  <div class="qual-known">
    <b>Client déjà connu :</b> <?= e(trim($known['lastname'].' '.$known['firstname'])) ?> — <?= e((string)$known['phone']) ?><?= $known['city'] ? ' — '.e((string)$known['city']) : '' ?>
    · <?= client_intervention_count((int)$known['id']) ?> intervention(s)
    <a href="<?= e(url_for('dispatcher/client_view.php?id='.(int)$known['id'])) ?>" target="_blank" rel="noopener">voir la fiche</a>
  </div>
  <?php endif; ?>

<?php if ($s['status'] !== 'resume'): /* ─────────── Conversation ─────────── */ ?>
  <div class="qual-grid">
    <div class="d-card qual-chat">
      <div class="qual-msgs" id="qual-msgs">
        <?php foreach ($state['transcript'] ?? [] as $t): ?>
          <div class="qual-msg qual-msg--<?= $t['role'] === 'assistant' ? 'ai' : 'me' ?>"><?= nl2br(e((string)$t['text'])) ?></div>
        <?php endforeach; ?>
        <div id="bas"></div>
      </div>
      <form method="post" class="qual-answer" id="qual-form">
        <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
        <input type="hidden" name="s" value="<?= $sid ?>">
        <input type="hidden" name="action" value="answer">
        <?php if ($last && !empty($last['quick_replies'])): ?>
        <div class="qual-quick">
          <?php foreach ($last['quick_replies'] as $qr): ?><button type="button" class="qual-chip" data-reply="<?= e((string)$qr) ?>"><?= e((string)$qr) ?></button><?php endforeach; ?>
        </div>
        <?php endif; ?>
        <div class="qual-input">
          <textarea name="answer" id="qual-text" rows="2" placeholder="Réponse du client…" required></textarea>
          <button type="submit" class="d-btn d-btn--primary">Envoyer</button>
        </div>
        <div class="qual-wait" id="qual-wait" hidden><?= $s['mode'] === 'claude' ? 'Claude prépare la question suivante…' : 'Enregistrement…' ?></div>
      </form>
    </div>

    <div>
      <form method="post" class="d-card">
        <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
        <input type="hidden" name="s" value="<?= $sid ?>">
        <div class="d-card-head">
          <div class="d-card-title">Informations recueillies</div>
          <span style="font-size:.8rem;color:<?= $missing ? 'var(--d-danger)' : 'var(--d-success)' ?>;"><?= $missing ? count($missing).' obligatoire(s) manquant(s)' : 'Complet' ?></span>
        </div>
        <div class="d-card-body qual-fields">
          <?php foreach ($fields as $k => [$label, $req]): $v = (string)($answers[$k] ?? ''); ?>
            <label class="qual-field <?= $req && $v === '' ? 'is-missing' : '' ?>">
              <span class="<?= $req ? 'req' : '' ?>"><?= e($label) ?></span>
              <?php if (in_array($k, ['description', 'diagnostic', 'access_info', 'availability'], true)): ?>
                <textarea name="f_<?= e($k) ?>" rows="2"><?= e($v) ?></textarea>
              <?php elseif ($k === 'category'): ?>
                <select name="f_category"><option value="">—</option><?php foreach ($catsCfg as $ck => $cc): ?><option value="<?= e($ck) ?>" <?= $v === $ck ? 'selected' : '' ?>><?= e($cc['label']) ?></option><?php endforeach; ?></select>
              <?php else: ?>
                <input name="f_<?= e($k) ?>" value="<?= e($v) ?>">
              <?php endif; ?>
            </label>
          <?php endforeach; ?>
          <div style="display:flex;gap:.5rem;flex-wrap:wrap;margin-top:.4rem;">
            <button type="submit" name="action" value="fields" class="d-btn d-btn--sm">Enregistrer les corrections</button>
            <?php if ($s['mode'] === 'claude'): ?><button type="submit" name="action" value="questionnaire" class="d-btn d-btn--ghost d-btn--sm" formnovalidate>Passer au questionnaire standard</button><?php endif; ?>
          </div>
        </div>
      </form>
      <form method="post" style="margin-top:1rem;">
        <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
        <input type="hidden" name="s" value="<?= $sid ?>">
        <input type="hidden" name="action" value="summarize">
        <button type="submit" class="d-btn d-btn--primary" style="width:100%;justify-content:center;" id="qual-sum">Générer le résumé<?= $missing ? ' (informations incomplètes)' : '' ?></button>
      </form>
    </div>
  </div>

  <script>
  (function () {
    var form = document.getElementById('qual-form'), text = document.getElementById('qual-text'), wait = document.getElementById('qual-wait');
    var box = document.getElementById('qual-msgs');
    if (box) box.scrollTop = box.scrollHeight;
    function send() {
      if (!text.value.trim()) { text.focus(); return; }
      var fd = new FormData(form);
      form.querySelectorAll('button,textarea').forEach(function (el) { el.disabled = true; });
      wait.hidden = false;
      fetch(location.pathname, { method: 'POST', body: fd, headers: { 'X-Requested-With': 'fetch' }, credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (d) { if (!d.ok) alert(d.error || 'Erreur'); location.replace(location.pathname + '?s=<?= $sid ?>&t=' + Date.now() + '#bas'); })
        .catch(function () { form.submit(); });
    }
    form.addEventListener('submit', function (e) { e.preventDefault(); send(); });
    text.addEventListener('keydown', function (e) { if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); send(); } });
    form.querySelectorAll('.qual-chip').forEach(function (b) {
      b.addEventListener('click', function () {
        var r = b.getAttribute('data-reply');
        // Une réponse à préciser (« Oui (lequel ?) ») est placée dans la zone de saisie.
        if (/\(.*\?\)|\(préciser\)/.test(r)) { text.value = r.replace(/\s*\(.*\)\s*$/, '') + ' : '; text.focus(); return; }
        text.value = r; send();
      });
    });
    text.focus();
    document.getElementById('qual-sum').addEventListener('click', function () { this.textContent = 'Préparation du résumé…'; });
  })();
  </script>

<?php else: /* ─────────── Résumé modifiable ─────────── */
    $sum = $s['summary'];
    $c   = $sum['client'];
    $ivs = $sum['intervention'];
    $vat = iv_vat_rate(['housing_over_2y' => $c['housing_over_2y'] === 'oui' ? 1 : 0], ['client_type' => $c['client_type']]);
    $est = qual_estimate($sum['estimate']['lines'], $vat);
    $grid = price_grid_rows(true, isset($catsCfg[$ivs['category']]) ? (string)$ivs['category'] : '');
    $materials = get_presets('material', (string)$ivs['category']);
    $clientLabels = ['lastname' => 'Nom', 'firstname' => 'Prénom', 'phone' => 'Téléphone', 'email' => 'E-mail', 'address' => 'Adresse',
        'postal_code' => 'Code postal', 'city' => 'Ville', 'floor' => 'Étage', 'digicode' => 'Digicode', 'interphone' => 'Interphone',
        'access_info' => 'Accès', 'availability' => 'Disponibilités'];
    $required = ['lastname', 'phone', 'address', 'city'];
?>
  <form method="post" id="qual-summary">
    <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
    <input type="hidden" name="s" value="<?= $sid ?>">
    <div class="d-flash" style="background:var(--d-card);border:1px solid var(--d-border);color:var(--d-t2);">
      <?= ($sum['source'] ?? '') === 'claude' ? 'Résumé proposé par Claude.' : 'Résumé construit à partir des réponses.' ?>
      Vérifiez et corrigez chaque champ : rien n'est enregistré sur une fiche tant que vous n'avez pas cliqué « Créer la fiche ».
    </div>
    <div class="qual-grid">
      <div class="d-card">
        <div class="d-card-head"><div class="d-card-title">Client</div></div>
        <div class="d-card-body">
          <?php if ($known): ?>
          <div class="d-field">
            <label><input type="radio" name="client_choice" value="existing" checked> Utiliser le client existant : <b><?= e(trim($known['lastname'].' '.$known['firstname'])) ?></b> (<?= e((string)$known['phone']) ?>)</label>
            <label><input type="radio" name="client_choice" value="new"> Créer un nouveau client</label>
            <input type="hidden" name="existing_client_id" value="<?= (int)$known['id'] ?>">
          </div>
          <?php endif; ?>
          <div class="d-grid-2">
            <?php foreach ($clientLabels as $k => $label): ?>
              <div class="d-field" <?= in_array($k, ['address', 'access_info', 'availability'], true) ? 'style="grid-column:1/-1;"' : '' ?>>
                <label class="<?= in_array($k, $required, true) ? 'req' : '' ?>" for="c_<?= $k ?>"><?= e($label) ?></label>
                <input id="c_<?= $k ?>" name="c_<?= $k ?>" value="<?= e((string)($c[$k] ?? '')) ?>" <?= in_array($k, $required, true) ? 'required' : '' ?> <?= $k === 'email' ? 'type="email"' : '' ?>>
              </div>
            <?php endforeach; ?>
            <div class="d-field"><label for="c_client_type">Type de client</label>
              <select id="c_client_type" name="c_client_type"><?php foreach (['particulier' => 'Particulier', 'professionnel' => 'Professionnel', 'inconnu' => 'Non précisé'] as $k => $l): ?><option value="<?= $k ?>" <?= $c['client_type'] === $k ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select></div>
            <div class="d-field"><label for="c_occupant">Occupant</label>
              <select id="c_occupant" name="c_occupant"><?php foreach (['locataire' => 'Locataire', 'proprietaire' => 'Propriétaire', 'inconnu' => 'Non précisé'] as $k => $l): ?><option value="<?= $k ?>" <?= $c['occupant'] === $k ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select></div>
            <div class="d-field"><label for="c_housing">Logement de plus de 2 ans</label>
              <select id="c_housing" name="c_housing_over_2y"><?php foreach (['oui' => 'Oui', 'non' => 'Non', 'inconnu' => 'Ne sait pas'] as $k => $l): ?><option value="<?= $k ?>" <?= $c['housing_over_2y'] === $k ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select></div>
          </div>
        </div>
      </div>

      <div>
        <div class="d-card" style="margin-bottom:1rem;">
          <div class="d-card-head"><div class="d-card-title">Intervention</div></div>
          <div class="d-card-body">
            <div class="d-grid-2">
              <div class="d-field"><label class="req" for="i_category">Métier</label>
                <select id="i_category" name="i_category" required><?php foreach ($catsCfg as $ck => $cc): ?><option value="<?= e($ck) ?>" <?= $ivs['category'] === $ck ? 'selected' : '' ?>><?= e($cc['label']) ?></option><?php endforeach; ?></select></div>
              <div class="d-field"><label for="i_fault_type">Type de panne</label><input id="i_fault_type" name="i_fault_type" value="<?= e((string)$ivs['fault_type']) ?>"></div>
              <div class="d-field"><label for="i_priority">Priorité</label>
                <select id="i_priority" name="i_priority"><?php foreach (['normale' => 'Normale', 'haute' => 'Haute', 'urgente' => 'Urgente'] as $k => $l): ?><option value="<?= $k ?>" <?= $ivs['priority'] === $k ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select></div>
              <div class="d-field"><label for="i_duration">Durée estimée (min)</label><input id="i_duration" name="i_duration" inputmode="numeric" value="<?= (int)$ivs['duration_minutes'] ?>"></div>
            </div>
            <div class="d-field"><label><input type="checkbox" name="i_urgency" value="1" <?= $ivs['urgency'] ? 'checked' : '' ?>> Intervention urgente</label></div>
            <div class="d-field"><label class="req" for="i_description">Description pour le technicien</label><textarea id="i_description" name="i_description" rows="6" required><?= e((string)$ivs['description']) ?></textarea></div>
            <div class="d-field"><label for="i_skills">Compétences requises (séparées par des virgules)</label><input id="i_skills" name="i_skills" value="<?= e(implode(', ', $ivs['skills'])) ?>"></div>
            <div class="d-field"><label for="i_materials">Matériel probable (un par ligne)</label>
              <textarea id="i_materials" name="i_materials" rows="3"><?= e(implode("\n", $ivs['materials'])) ?></textarea>
              <?php if ($materials): ?><div style="font-size:.76rem;color:var(--d-t3);margin-top:.25rem;">Catalogue : <?= e(implode(' · ', array_slice(array_column($materials, 'label'), 0, 20))) ?></div><?php endif; ?>
            </div>
          </div>
        </div>

        <div class="d-card" id="estimation">
          <div class="d-card-head"><div class="d-card-title">Estimation indicative</div><span style="font-size:.8rem;color:var(--d-t2);">TVA <?= e(rtrim(rtrim(number_format($est['vat_rate'], 1, ',', ''), '0'), ',')) ?> %</span></div>
          <div class="d-card-body">
            <table class="d-table" style="margin-bottom:.6rem;">
              <thead><tr><th>Prestation (grille tarifaire)</th><th style="width:80px;">Qté</th></tr></thead>
              <tbody>
              <?php foreach (array_merge($sum['estimate']['lines'], [['code' => '', 'qty' => 1], ['code' => '', 'qty' => 1]]) as $l): ?>
                <tr>
                  <td><select name="l_code[]" style="width:100%;"><option value="">—</option><?php foreach ($grid as $g): ?><option value="<?= e($g['code']) ?>" <?= strtoupper((string)$l['code']) === $g['code'] ? 'selected' : '' ?>><?= e($g['code'].' — '.$g['label']) ?></option><?php endforeach; ?></select></td>
                  <td><input name="l_qty[]" inputmode="decimal" value="<?= e(rtrim(rtrim(number_format((float)$l['qty'], 2, ',', ''), '0'), ',')) ?>" style="width:100%;"></td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
            <?php if ($est['lines']): ?>
            <div class="qual-range">Entre <b><?= e(money_fr($est['range_min'])) ?></b> et <b><?= e(money_fr($est['range_max'])) ?></b> TTC</div>
            <div style="font-size:.8rem;color:var(--d-t2);margin-top:.3rem;">Base <?= e(money_fr($est['total_ht'])) ?> HT calculée sur la grille ; la fourchette haute ajoute une heure de main d'œuvre. Le prix définitif dépend du diagnostic sur place.</div>
            <?php else: ?>
            <div style="color:var(--d-t2);">Aucune prestation choisie.</div>
            <?php endif; ?>
            <?php if (!empty($sum['estimate']['note'])): ?><div style="font-size:.82rem;margin-top:.5rem;"><?= e((string)$sum['estimate']['note']) ?></div><?php endif; ?>
            <?php foreach ($est['warnings'] as $w): ?><div style="font-size:.8rem;color:var(--d-danger);"><?= e($w) ?></div><?php endforeach; ?>
            <button type="submit" name="action" value="recalc" class="d-btn d-btn--sm" style="margin-top:.6rem;" formnovalidate>Recalculer</button>
          </div>
        </div>
      </div>
    </div>

    <div class="qual-actions">
      <button type="submit" name="action" value="back" class="d-btn d-btn--ghost" formnovalidate>← Revenir à l'appel</button>
      <button type="submit" name="action" value="create" class="d-btn d-btn--primary d-btn--lg">Créer la fiche</button>
    </div>
  </form>

  <details class="d-card" style="margin-top:1rem;">
    <summary class="d-card-head" style="cursor:pointer;"><span class="d-card-title">Conversation (<?= count($state['transcript'] ?? []) ?> messages)</span></summary>
    <div class="qual-msgs" style="max-height:none;">
      <?php foreach ($state['transcript'] ?? [] as $t): ?><div class="qual-msg qual-msg--<?= $t['role'] === 'assistant' ? 'ai' : 'me' ?>"><?= nl2br(e((string)$t['text'])) ?></div><?php endforeach; ?>
    </div>
  </details>
<?php endif; ?>
</div>
<?php
require __DIR__.'/partials/qualify_style.php';
require __DIR__.'/partials/footer.php';
