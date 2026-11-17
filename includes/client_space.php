<?php
declare(strict_types=1);
/**
 * Côté client : suivi d'une intervention par lien (17) et espace client (16).
 *
 * Suivi : chaque intervention reçoit un jeton secret ; le lien envoyé par SMS
 * montre l'avancement (planifiée, en route, sur place, terminée), le prénom du
 * technicien et la date. Ni adresse ni montant : le lien peut être transféré.
 *
 * Espace client : connexion sans mot de passe, par un code à 6 chiffres envoyé
 * par e-mail (ou SMS si le compte SMS est configuré). Le client voit les
 * demandes, interventions, factures et paiements liés à son e-mail ou à son
 * numéro de téléphone. Désactivé par défaut.
 */

/* ═══════════════════════════════════════════════════
   17. SUIVI DE L'INTERVENTION
═══════════════════════════════════════════════════ */
function track_enabled(): bool { return function_exists('growth_on') ? growth_on('track_enabled') : true; }

/** Jeton de suivi de l'intervention, créé au premier besoin. '' si impossible. */
function intervention_track_token(int $ivId): string
{
    try {
        $r = db_fetch('SELECT track_token FROM interventions WHERE id = ?', [$ivId]);
        if (!$r) return '';
        $t = (string)($r['track_token'] ?? '');
        if (preg_match('/^[a-f0-9]{24}$/', $t)) return $t;
        $t = bin2hex(random_bytes(12));
        db_execute('UPDATE interventions SET track_token = ? WHERE id = ?', [$t, $ivId]);
        return $t;
    } catch (Throwable $e) { return ''; }
}

/** Lien de suivi complet, à mettre dans un SMS. '' si le suivi est désactivé. */
function intervention_track_url(int $ivId): string
{
    if (!track_enabled()) return '';
    $t = intervention_track_token($ivId);
    return $t === '' ? '' : notif_abs_url('index.php?route=suivi&t='.$t);
}

/** Étapes du suivi : [libellé, heure ou '', état (done|cur|'')]. */
function track_steps(array $iv): array
{
    $st   = (string)($iv['status'] ?? '');
    $done = function_exists('wf_field_done') ? wf_field_done() : ['terminé'];
    $cur  = in_array($st, $done, true) ? 3 : ($st === 'sur_place' ? 2 : ($st === 'en_route' ? 1 : 0));
    $hm   = static fn($v): string => $v ? date('H:i', strtotime((string)$v)) : '';
    $when = !empty($iv['scheduled_date']) ? booking_day_label((string)$iv['scheduled_date']).(!empty($iv['scheduled_time']) ? ' à '.substr((string)$iv['scheduled_time'], 0, 5) : '') : 'Date à confirmer';
    $labels = [
        ['Intervention planifiée', $when],
        ['Technicien en route', $hm($iv['tech_started_at'] ?? null)],
        ['Technicien sur place', $hm($iv['tech_arrived_at'] ?? null)],
        ['Intervention terminée', $hm($iv['tech_completed_at'] ?? null)],
    ];
    foreach ($labels as $i => &$l) $l[2] = $i < $cur || ($i === 3 && $cur === 3) ? 'done' : ($i === $cur ? 'cur' : '');
    return $labels;
}

function render_page_suivi(): void
{
    $t  = (string)($_GET['t'] ?? '');
    $iv = null;
    if (preg_match('/^[a-f0-9]{24}$/', $t)) {
        try {
            $iv = db_fetch('SELECT i.*, t.name AS tech_name FROM interventions i LEFT JOIN technicians t ON t.id = i.technician_id WHERE i.track_token = ?', [$t]);
        } catch (Throwable $e) { $iv = null; }
    }
    render_head(['title' => 'Suivi de votre intervention | '.company_name(), 'description' => 'Suivi de votre intervention.', 'canonical' => route_url('suivi'), 'robots' => 'noindex,nofollow']);
    render_header('');
    echo '<section class="page-hero"><div class="wrap"><div class="ph-eyebrow">Suivi en direct</div><h1 class="ph-h1">Votre intervention</h1>';
    if (!$iv) {
        echo '<p class="ph-lead">Ce lien de suivi n\'est pas valable ou a expiré. Pour toute question, appelez-nous.</p>'
           . '<div class="btn-row px-actions"><a class="btn btn-p btn-lg" href="'.e(company_phone_link()).'">📞 '.e(company_phone()).'</a></div></div></section>';
        render_footer();
        return;
    }
    $cancel = ($iv['status'] ?? '') === 'annulé';
    $what   = trim((string)($iv['type_label'] ?? '')) ?: (intervention_category_config()[$iv['category'] ?? '']['label'] ?? 'Intervention');
    $tech   = quote_first_name((string)($iv['tech_name'] ?? ''));
    echo '<p class="ph-lead">'.e($what).($tech !== '' ? ' · Votre technicien : '.e($tech) : '').'</p></div></section>';
    echo '<section class="sec sec-navy"><div class="wrap px-narrow">';
    if ($cancel) {
        echo '<div class="px-alert"><strong>Intervention annulée</strong><p>Cette intervention a été annulée. Appelez-nous si vous pensez qu\'il s\'agit d\'une erreur.</p></div>';
    } else {
        echo '<ol class="px-track">';
        foreach (track_steps($iv) as [$label, $when, $state]) {
            echo '<li class="'.$state.'"><span class="px-track-dot" aria-hidden="true"></span><span class="px-track-l">'.e($label).'</span>'.($when !== '' ? '<span class="px-track-w">'.e($when).'</span>' : '').'</li>';
        }
        echo '</ol><p class="tarif-note">Cette page se met à jour toute seule : rechargez-la pour voir l\'avancement.</p>';
    }
    echo '<div class="btn-row px-actions"><a class="btn btn-p btn-lg" href="'.e(company_phone_link()).'">📞 Nous appeler</a></div></div></section>';
    echo '<script>setTimeout(function(){location.reload();},60000);</script>';
    render_footer();
}

/* ═══════════════════════════════════════════════════
   16. ESPACE CLIENT
═══════════════════════════════════════════════════ */
function client_space_enabled(): bool { return global_setting('client_space_enabled', '0') === '1'; }

const CLIENT_CODE_TTL      = 600;   // un code vaut 10 minutes
const CLIENT_CODE_ATTEMPTS = 5;     // essais par code
const CLIENT_SESSION_TTL   = 7200;  // l'espace se referme après 2 heures

function client_codes_table(): void
{
    static $done = false;
    if ($done) return;
    $done = true;
    try {
        db_execute("CREATE TABLE IF NOT EXISTS client_login_codes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            ident VARCHAR(190) NOT NULL,
            code_hash CHAR(64) NOT NULL,
            attempts INT NOT NULL DEFAULT 0,
            expires_at DATETIME NOT NULL,
            used_at DATETIME NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_ident (ident, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (Throwable $e) {}
}

/** E-mail en minuscules, ou les 9 derniers chiffres d'un téléphone. '' si ni l'un ni l'autre. */
function client_ident(string $raw): string
{
    $raw = trim($raw);
    if (filter_var($raw, FILTER_VALIDATE_EMAIL)) return mb_strtolower($raw, 'UTF-8');
    $d = phone_last9($raw);
    return strlen($d) === 9 ? $d : '';
}

function client_ident_is_phone(string $ident): bool { return (bool)preg_match('/^\d{9}$/', $ident); }

/** Le client existe-t-il (fiche client, ou demande envoyée depuis le site) ? */
function client_known(string $ident): bool
{
    return client_matching_ids($ident)['clients'] !== [] || client_matching_ids($ident)['quotes'] !== [];
}

/** Fiches clients et demandes correspondant à l'identifiant. */
function client_matching_ids(string $ident): array
{
    static $cache = [];
    if (isset($cache[$ident])) return $cache[$ident];
    $out = ['clients' => [], 'quotes' => []];
    if ($ident === '') return $out;
    $phone = client_ident_is_phone($ident);
    foreach (['clients' => 'SELECT id, phone, email FROM clients', 'quotes' => 'SELECT id, phone, email FROM quotes'] as $k => $sql) {
        try { $rows = db_fetch_all($sql); } catch (Throwable $e) { $rows = []; }
        foreach ($rows as $r) {
            $hit = $phone ? phone_last9((string)($r['phone'] ?? '')) === $ident
                          : mb_strtolower(trim((string)($r['email'] ?? '')), 'UTF-8') === $ident;
            if ($hit) $out[$k][] = (int)$r['id'];
        }
    }
    return $cache[$ident] = $out;
}

/**
 * Envoie un code de connexion. Retourne toujours un message neutre : on ne dit
 * jamais à un inconnu si un e-mail ou un numéro est client chez vous.
 */
function client_send_code(string $raw): string
{
    $neutral = 'Si ce compte existe, un code à 6 chiffres vient d\'être envoyé. Il est valable 10 minutes.';
    $ident = client_ident($raw);
    if ($ident === '') return 'Indiquez une adresse e-mail ou un numéro de téléphone valide.';
    client_codes_table();
    try {
        $n = db_fetch('SELECT COUNT(*) AS n FROM client_login_codes WHERE ident = ? AND created_at > ?', [$ident, date('Y-m-d H:i:s', time() - 3600)]);
        if ((int)($n['n'] ?? 0) >= 3) return 'Trop de codes demandés : réessayez dans une heure, ou appelez-nous.';
    } catch (Throwable $e) {}
    if (!client_known($ident)) return $neutral;
    $code = (string)random_int(100000, 999999);
    try {
        db_execute('INSERT INTO client_login_codes (ident, code_hash, expires_at) VALUES (?, ?, ?)',
            [$ident, hash('sha256', $ident.'|'.$code), date('Y-m-d H:i:s', time() + CLIENT_CODE_TTL)]);
    } catch (Throwable $e) { return 'Service momentanément indisponible. Réessayez plus tard.'; }
    $txt = company_name().' : votre code de connexion à l\'espace client est '.$code.'. Il est valable 10 minutes. Ne le communiquez à personne.';
    if (client_ident_is_phone($ident)) {
        if (sms_configured()) notif_sms('0'.$ident, $txt);
    } else {
        notif_mail($ident, 'Votre code de connexion — '.company_name(), 'Votre code de connexion',
            ['Voici votre code pour ouvrir votre espace client :', '<span style="font-size:28px;font-weight:800;letter-spacing:.2em;">'.$code.'</span>', 'Il est valable 10 minutes. Si vous n\'êtes pas à l\'origine de cette demande, ignorez ce message.']);
    }
    return $neutral;
}

/** Vérifie le code ; ouvre l'espace si c'est le bon. */
function client_verify_code(string $raw, string $code): bool
{
    $ident = client_ident($raw);
    $code  = (string)preg_replace('/\D/', '', $code);
    if ($ident === '' || strlen($code) !== 6) return false;
    client_codes_table();
    try {
        $row = db_fetch('SELECT * FROM client_login_codes WHERE ident = ? AND used_at IS NULL AND expires_at > ? ORDER BY id DESC LIMIT 1',
            [$ident, date('Y-m-d H:i:s')]);
        if (!$row || (int)$row['attempts'] >= CLIENT_CODE_ATTEMPTS) return false;
        if (!hash_equals((string)$row['code_hash'], hash('sha256', $ident.'|'.$code))) {
            db_execute('UPDATE client_login_codes SET attempts = attempts + 1 WHERE id = ?', [(int)$row['id']]);
            return false;
        }
        db_execute('UPDATE client_login_codes SET used_at = NOW() WHERE id = ?', [(int)$row['id']]);
    } catch (Throwable $e) { return false; }
    session_regenerate_id(true);
    $_SESSION['client_space'] = ['ident' => $ident, 'at' => time()];
    return true;
}

/** Identifiant du client connecté, ou '' (espace fermé ou expiré). */
function client_current(): string
{
    $s = $_SESSION['client_space'] ?? null;
    if (!is_array($s) || empty($s['ident']) || time() - (int)($s['at'] ?? 0) > CLIENT_SESSION_TTL) {
        unset($_SESSION['client_space']);
        return '';
    }
    return (string)$s['ident'];
}

/** Tout ce que le client a le droit de voir. */
function client_space_data(string $ident): array
{
    $ids = client_matching_ids($ident);
    $in  = static fn(array $l): string => implode(',', array_map('intval', $l ?: [0]));
    $q = static function (string $sql): array { try { return db_fetch_all($sql); } catch (Throwable $e) { return []; } };
    $data = [
        'quotes'        => $q('SELECT id, created_at, request_type, service_type, status FROM quotes WHERE id IN ('.$in($ids['quotes']).') ORDER BY id DESC LIMIT 30'),
        'interventions' => $q('SELECT i.id, i.ref, i.status, i.scheduled_date, i.scheduled_time, i.type_label, i.category, i.track_token, t.name AS tech_name
                               FROM interventions i LEFT JOIN technicians t ON t.id = i.technician_id
                               WHERE i.client_id IN ('.$in($ids['clients']).') OR i.quote_id IN ('.$in($ids['quotes']).') ORDER BY i.id DESC LIMIT 30'),
        'invoices'      => $q("SELECT id, number, label, issue_date, total_ttc, remaining_ttc, status, pdf_url FROM invoices
                               WHERE client_id IN (".$in($ids['clients']).") AND status NOT IN ('brouillon','annulée') ORDER BY id DESC LIMIT 30"),
        'payments'      => [],
    ];
    if (function_exists('payments_for_client')) $data['payments'] = payments_for_client($ident);
    return $data;
}

function render_page_espace_client(): void
{
    boot_session();
    $enabled = client_space_enabled() || admin_logged_in();
    $msg = ''; $err = '';
    $step = 'ident';
    $rawIdent = (string)($_SESSION['client_space_pending'] ?? '');

    if ($enabled && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        verify_csrf();
        $act = (string)($_POST['act'] ?? '');
        if ($act === 'logout') { unset($_SESSION['client_space'], $_SESSION['client_space_pending']); redirect_to(route_url('espace-client')); }
        if ($act === 'send') {
            if (!rate_limit_passed('client_code', 20)) { $err = 'Patientez quelques secondes avant de redemander un code.'; }
            else {
                $rawIdent = trim((string)($_POST['ident'] ?? ''));
                $msg = client_send_code($rawIdent);
                if (client_ident($rawIdent) !== '') { $_SESSION['client_space_pending'] = $rawIdent; $step = 'code'; }
            }
        }
        if ($act === 'verify') {
            if (!rate_limit_passed('client_verify', 2)) { $err = 'Patientez quelques secondes.'; $step = 'code'; }
            elseif (client_verify_code($rawIdent, (string)($_POST['code'] ?? ''))) { unset($_SESSION['client_space_pending']); redirect_to(route_url('espace-client')); }
            else { $err = 'Code incorrect ou expiré.'; $step = 'code'; }
        }
    } elseif ($rawIdent !== '' && isset($_GET['code'])) {
        $step = 'code';
    }

    render_head(['title' => 'Espace client | '.company_name(), 'description' => 'Retrouvez vos demandes, interventions et factures.', 'canonical' => route_url('espace-client'), 'robots' => 'noindex,nofollow']);
    render_header(route_url('espace-client'));
    if (!client_space_enabled() && admin_logged_in()) echo '<div class="tarif-draft"><div class="wrap">👁️ Aperçu réservé à l\'administrateur : l\'espace client est désactivé. <a href="'.e(url_for('admin/growth.php#client')).'">L\'activer</a></div></div>';
    $ident = $enabled ? client_current() : '';
    px_hero('Espace client', $ident !== '' ? 'Votre espace client' : 'Accéder à votre espace client',
        $ident !== '' ? 'Vos demandes, vos interventions et vos factures, au même endroit.' : 'Recevez un code par e-mail ou par SMS : pas de mot de passe à retenir.', false);
    echo '<section class="sec sec-navy"><div class="wrap px-narrow">';
    if (!$enabled) {
        echo '<div class="px-note">L\'espace client n\'est pas encore ouvert. Pour toute question sur une intervention ou une facture, appelez-nous au <a href="'.e(company_phone_link()).'" style="color:var(--p);">'.e(company_phone()).'</a>.</div>';
    } elseif ($ident === '') {
        if ($msg !== '') echo '<div class="flash flash-ok">'.e($msg).'</div>';
        if ($err !== '') echo '<div class="flash flash-err">'.e($err).'</div>';
        echo '<form class="qs-card px-login" method="post"><input type="hidden" name="csrf_token" value="'.e(csrf_token()).'">';
        if ($step === 'code') {
            echo '<input type="hidden" name="act" value="verify"><div class="f-field"><span class="f-label">Code reçu</span>'
               . '<input class="f-input px-code" type="text" name="code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9 ]{6,7}" maxlength="7" required autofocus></div>'
               . '<button class="btn btn-p btn-lg btn-block" type="submit">Ouvrir mon espace</button>'
               . '<p class="f-meta"><a href="'.e(route_url('espace-client')).'" style="color:var(--t2);">Changer d\'adresse ou de numéro</a></p>';
        } else {
            echo '<input type="hidden" name="act" value="send"><div class="f-field"><span class="f-label">Votre e-mail ou votre téléphone</span>'
               . '<input class="f-input" type="text" name="ident" autocomplete="email" required placeholder="votre@email.fr ou 06 00 00 00 00"></div>'
               . '<button class="btn btn-p btn-lg btn-block" type="submit">Recevoir mon code</button>'
               . '<p class="f-meta">Utilisez l\'e-mail ou le numéro donné lors de votre demande.'.(sms_configured() ? '' : ' Le code arrive par e-mail.').'</p>';
        }
        echo '</form>';
    } else {
        render_client_space_content(client_space_data($ident));
        echo '<form method="post" style="text-align:center;margin-top:2rem;"><input type="hidden" name="csrf_token" value="'.e(csrf_token()).'"><input type="hidden" name="act" value="logout"><button class="btn btn-outline" type="submit">Se déconnecter</button></form>';
    }
    echo '</div></section>';
    render_footer();
}

function render_client_space_content(array $d): void
{
    $status = function_exists('intervention_status_config') ? intervention_status_config() : [];
    $date = static fn($v): string => $v ? date('d/m/Y', strtotime((string)$v)) : '';
    if ($d['payments']) {
        echo '<div class="px-box px-box--call"><h2 class="px-h2">💳 Paiements en attente</h2><ul class="px-rows">';
        foreach ($d['payments'] as $p) echo '<li><span>'.e($p['description']).'</span><strong>'.e(money_fr($p['amount_cents'] / 100)).'</strong><a class="btn btn-p btn-sm" href="'.e(payment_url((string)$p['token'])).'">Payer</a></li>';
        echo '</ul></div>';
    }
    echo '<div class="px-box"><h2 class="px-h2">🛠️ Vos interventions</h2>';
    if (!$d['interventions']) echo '<p>Aucune intervention pour le moment.</p>';
    else {
        echo '<ul class="px-rows">';
        foreach ($d['interventions'] as $iv) {
            $what = trim((string)($iv['type_label'] ?? '')) ?: (intervention_category_config()[$iv['category'] ?? '']['label'] ?? 'Intervention');
            $lab  = $status[(string)$iv['status']]['label'] ?? (string)$iv['status'];
            $trk  = preg_match('/^[a-f0-9]{24}$/', (string)($iv['track_token'] ?? '')) ? '<a class="btn btn-ghost-p btn-sm" href="'.e(url_for('index.php?route=suivi&t='.$iv['track_token'])).'">Suivre</a>' : '';
            echo '<li><span>'.e($what).'<small>'.e(trim($date($iv['scheduled_date']).' · '.$lab, ' ·')).'</small></span>'.$trk.'</li>';
        }
        echo '</ul>';
    }
    echo '</div><div class="px-box"><h2 class="px-h2">🧾 Vos factures</h2>';
    if (!$d['invoices']) echo '<p>Aucune facture pour le moment.</p>';
    else {
        echo '<ul class="px-rows">';
        foreach ($d['invoices'] as $f) {
            $rest = $f['remaining_ttc'] !== null ? (float)$f['remaining_ttc'] : null;
            $state = $f['status'] === 'payée' || ($rest !== null && $rest <= 0) ? '✅ Payée' : ($rest !== null ? 'Reste à payer : '.money_fr($rest) : '');
            $pdf = preg_match('#^https://#i', (string)($f['pdf_url'] ?? '')) ? '<a class="btn btn-ghost-p btn-sm" href="'.e((string)$f['pdf_url']).'" target="_blank" rel="noopener">PDF</a>' : '';
            echo '<li><span>'.e(trim(($f['number'] ?: 'Facture').' — '.money_fr((float)$f['total_ttc']))).'<small>'.e(trim($date($f['issue_date']).' · '.$state, ' ·')).'</small></span>'.$pdf.'</li>';
        }
        echo '</ul>';
    }
    echo '</div><div class="px-box"><h2 class="px-h2">📋 Vos demandes</h2>';
    if (!$d['quotes']) echo '<p>Aucune demande envoyée depuis le site.</p>';
    else {
        echo '<ul class="px-rows">';
        foreach ($d['quotes'] as $q) echo '<li><span>'.e(trim((string)$q['service_type']) ?: 'Demande').'<small>'.e($date($q['created_at']).' · '.ucfirst((string)$q['status'])).'</small></span>'.quote_type_badge($q).'</li>';
        echo '</ul>';
    }
    echo '</div>';
}
