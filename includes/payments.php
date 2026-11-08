<?php
declare(strict_types=1);
/**
 * 18. Paiement en ligne par carte, via Stripe Checkout.
 *
 * Le bureau crée un lien de paiement (montant + libellé) dans l'admin et l'envoie
 * au client par SMS ou e-mail. Le client paie sur la page sécurisée de Stripe :
 * aucune donnée de carte ne passe par ce site.
 *
 * Le paiement est confirmé deux fois : au retour du client (lecture de la session
 * chez Stripe) et par le webhook (api/stripe_webhook.php), signé avec un secret.
 * Rien n'est modifié dans Pennylane : le bureau marque la facture payée lui-même.
 *
 * Désactivé tant que la clé secrète Stripe n'est pas saisie (admin/payments.php).
 */

function payments_configured(): bool
{
    return function_exists('integration_secret') && str_starts_with(integration_secret('stripe_secret_key'), 'sk_');
}

function payments_enabled(): bool
{
    return global_setting('payments_enabled', '0') === '1' && payments_configured();
}

function payments_table(): void
{
    static $done = false;
    if ($done) return;
    $done = true;
    try {
        db_execute("CREATE TABLE IF NOT EXISTS online_payments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            token CHAR(32) NOT NULL,
            description VARCHAR(255) NOT NULL,
            amount_cents INT NOT NULL,
            client_name VARCHAR(190) NULL,
            phone VARCHAR(80) NULL,
            email VARCHAR(190) NULL,
            invoice_id INT NULL,
            intervention_id INT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'en_attente',
            stripe_session_id VARCHAR(255) NULL,
            created_by VARCHAR(120) NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            paid_at DATETIME NULL,
            UNIQUE KEY uq_token (token),
            INDEX idx_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (Throwable $e) {}
}

/** Montant saisi (« 120 », « 120,50 € ») en centimes ; 0 si invalide. */
function payment_amount_cents(string $raw): int
{
    $v = str_replace([' ', "\u{00A0}", '€'], '', trim($raw));
    $v = str_replace(',', '.', $v);
    if (!preg_match('/^\d{1,6}(\.\d{1,2})?$/', $v)) return 0;
    return (int)round((float)$v * 100);
}

/** Crée un lien de paiement. Retour : le jeton, ou '' si les données ne vont pas. */
function payment_create(array $p): string
{
    $cents = (int)($p['amount_cents'] ?? 0);
    $desc  = mb_substr(trim((string)($p['description'] ?? '')), 0, 255);
    if ($cents < 100 || $cents > 10000000 || $desc === '') return '';
    payments_table();
    $token = bin2hex(random_bytes(16));
    try {
        db_execute('INSERT INTO online_payments (token, description, amount_cents, client_name, phone, email, invoice_id, intervention_id, created_by)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)', [
            $token, $desc, $cents,
            mb_substr(trim((string)($p['client_name'] ?? '')), 0, 190) ?: null,
            mb_substr(trim((string)($p['phone'] ?? '')), 0, 80) ?: null,
            filter_var(trim((string)($p['email'] ?? '')), FILTER_VALIDATE_EMAIL) ? trim((string)$p['email']) : null,
            !empty($p['invoice_id']) ? (int)$p['invoice_id'] : null,
            !empty($p['intervention_id']) ? (int)$p['intervention_id'] : null,
            mb_substr((string)($p['created_by'] ?? ''), 0, 120) ?: null,
        ]);
    } catch (Throwable $e) { return ''; }
    return $token;
}

function payment_by_token(string $token): ?array
{
    if (!preg_match('/^[a-f0-9]{32}$/', $token)) return null;
    payments_table();
    try { return db_fetch('SELECT * FROM online_payments WHERE token = ?', [$token]); } catch (Throwable $e) { return null; }
}

function payment_url(string $token): string
{
    return notif_abs_url('index.php?route=paiement&t='.$token);
}

function payments_list(int $limit = 100): array
{
    payments_table();
    try { return db_fetch_all('SELECT * FROM online_payments ORDER BY id DESC LIMIT '.max(1, $limit)); } catch (Throwable $e) { return []; }
}

/** Paiements en attente d'un client de l'espace client (même e-mail ou même numéro). */
function payments_for_client(string $ident): array
{
    if (!payments_enabled()) return [];
    return array_values(array_filter(payments_list(500), static function (array $p) use ($ident): bool {
        if ($p['status'] !== 'en_attente') return false;
        return client_ident_is_phone($ident) ? phone_last9((string)$p['phone']) === $ident : mb_strtolower((string)$p['email'], 'UTF-8') === $ident;
    }));
}

/** Appel à l'API Stripe (formulaire encodé, comme Stripe l'attend). */
function stripe_request(string $method, string $path, array $params = []): array
{
    $key = integration_secret('stripe_secret_key');
    $headers = ['Authorization: Bearer '.$key, 'Content-Type: application/x-www-form-urlencoded'];
    $url = 'https://api.stripe.com/v1/'.ltrim($path, '/');
    $body = null;
    if ($method === 'GET' && $params) $url .= '?'.http_build_query($params);
    elseif ($params) $body = http_build_query($params);
    return integration_http($method, $url, $headers, $body, 20);
}

/** Ouvre une session de paiement Stripe. Retour : adresse de la page Stripe, ou ''. */
function payment_checkout(array $pay): string
{
    if (!payments_enabled() || $pay['status'] !== 'en_attente') return '';
    $back = payment_url((string)$pay['token']);
    $params = [
        'mode' => 'payment',
        'client_reference_id' => $pay['token'],
        'metadata' => ['token' => $pay['token']],
        'success_url' => $back.'&retour=ok&session_id={CHECKOUT_SESSION_ID}',
        'cancel_url'  => $back.'&retour=annule',
        'line_items' => [[
            'quantity' => 1,
            'price_data' => ['currency' => 'eur', 'unit_amount' => (int)$pay['amount_cents'], 'product_data' => ['name' => mb_substr((string)$pay['description'], 0, 250)]],
        ]],
        'locale' => 'fr',
    ];
    if (!empty($pay['email'])) $params['customer_email'] = $pay['email'];
    $r = stripe_request('POST', 'checkout/sessions', $params);
    $url = (string)($r['json']['url'] ?? '');
    if ($r['status'] !== 200 || !str_starts_with($url, 'https://')) {
        integration_log('stripe', 'Session de paiement refusée : '.(string)($r['json']['error']['message'] ?? $r['error'] ?? $r['status']), ['paiement' => (int)$pay['id']]);
        return '';
    }
    try { db_execute('UPDATE online_payments SET stripe_session_id = ? WHERE id = ?', [(string)$r['json']['id'], (int)$pay['id']]); } catch (Throwable $e) {}
    return $url;
}

/** Vérifie chez Stripe qu'une session est bien payée, puis enregistre le paiement. */
function payment_confirm_session(array $pay, string $sessionId): bool
{
    if (!preg_match('/^cs_[A-Za-z0-9_]+$/', $sessionId)) return false;
    $r = stripe_request('GET', 'checkout/sessions/'.rawurlencode($sessionId));
    $s = $r['json'] ?? [];
    return $r['status'] === 200 && payment_apply_session($s);
}

/**
 * Enregistre le paiement décrit par une session Stripe (retour client ou webhook).
 * Contrôles : session payée, bon jeton, montant et devise identiques au lien.
 */
function payment_apply_session(array $s): bool
{
    $token = (string)($s['client_reference_id'] ?? ($s['metadata']['token'] ?? ''));
    $pay = payment_by_token($token);
    if (!$pay || ($s['payment_status'] ?? '') !== 'paid') return false;
    if ((int)($s['amount_total'] ?? -1) !== (int)$pay['amount_cents'] || strtolower((string)($s['currency'] ?? '')) !== 'eur') {
        integration_log('stripe', 'Montant différent du lien : paiement non enregistré.', ['paiement' => (int)$pay['id']]);
        return false;
    }
    if ($pay['status'] === 'payé') return true;
    try {
        db_execute("UPDATE online_payments SET status = 'payé', paid_at = NOW(), stripe_session_id = ? WHERE id = ? AND status <> 'payé'", [(string)($s['id'] ?? ''), (int)$pay['id']]);
    } catch (Throwable $e) { return false; }
    integration_log('stripe', 'Paiement reçu : '.money_fr($pay['amount_cents'] / 100).' — '.$pay['description'], ['paiement' => (int)$pay['id']]);
    $to = global_setting('form_email_to', company_email());
    notif_mail($to, '💳 Paiement reçu — '.money_fr($pay['amount_cents'] / 100), 'Paiement en ligne reçu', [
        '<b>'.e(money_fr($pay['amount_cents'] / 100)).'</b> — '.e((string)$pay['description']),
        e(trim((string)$pay['client_name'].' '.(string)$pay['phone'])),
        'Pensez à marquer la facture correspondante comme payée.',
    ], notif_abs_url('admin/payments.php'), 'Voir les paiements');
    return true;
}

/** Signature du webhook Stripe (en-tête Stripe-Signature : t=…,v1=…). Tolérance : 5 minutes. */
function stripe_signature_valid(string $payload, string $header, string $secret, ?int $now = null): bool
{
    if ($secret === '' || $header === '') return false;
    $t = null; $sigs = [];
    foreach (explode(',', $header) as $part) {
        [$k, $v] = array_pad(explode('=', trim($part), 2), 2, '');
        if ($k === 't') $t = (int)$v;
        if ($k === 'v1') $sigs[] = $v;
    }
    if ($t === null || !$sigs || abs(($now ?? time()) - $t) > 300) return false;
    $expected = hash_hmac('sha256', $t.'.'.$payload, $secret);
    foreach ($sigs as $s) if (hash_equals($expected, $s)) return true;
    return false;
}

function render_page_paiement(): void
{
    $pay = payment_by_token((string)($_GET['t'] ?? ''));
    $msg = ''; $err = '';
    if ($pay && ($_GET['go'] ?? '') === '1' && $pay['status'] === 'en_attente') {
        $url = payment_checkout($pay);
        if ($url !== '') { header('Location: '.$url, true, 303); exit; }
        $err = 'Le paiement en ligne est momentanément indisponible. Réessayez plus tard ou appelez-nous.';
    }
    if ($pay && ($_GET['retour'] ?? '') === 'ok' && $pay['status'] === 'en_attente') {
        if (payment_confirm_session($pay, (string)($_GET['session_id'] ?? ''))) $pay = payment_by_token((string)$pay['token']);
        else $msg = 'Votre paiement est en cours de confirmation. Vous recevrez une confirmation de Stripe par e-mail.';
    }
    if ($pay && ($_GET['retour'] ?? '') === 'annule') $msg = 'Paiement annulé : rien n\'a été débité.';

    render_head(['title' => 'Paiement | '.company_name(), 'description' => 'Paiement sécurisé.', 'canonical' => route_url('paiement'), 'robots' => 'noindex,nofollow']);
    render_header('');
    px_hero('Paiement sécurisé', $pay ? 'Régler votre facture' : 'Lien de paiement', $pay ? 'Paiement par carte bancaire sur la page sécurisée de notre prestataire Stripe.' : 'Ce lien de paiement n\'est pas valable.', false);
    echo '<section class="sec sec-navy"><div class="wrap px-narrow">';
    if ($msg !== '') echo '<div class="flash flash-ok">'.e($msg).'</div>';
    if ($err !== '') echo '<div class="flash flash-err">'.e($err).'</div>';
    if (!$pay) {
        echo '<div class="px-note">Vérifiez le lien reçu, ou appelez-nous au <a href="'.e(company_phone_link()).'" style="color:var(--p);">'.e(company_phone()).'</a>.</div>';
    } else {
        echo '<div class="px-result"><div class="px-result-what">'.e((string)$pay['description']).'</div><div class="px-result-price">'.e(money_fr($pay['amount_cents'] / 100)).'</div>';
        if ($pay['status'] === 'payé') {
            echo '<p class="px-result-note">✅ Paiement reçu le '.e(date('d/m/Y', strtotime((string)$pay['paid_at']))).'. Merci !</p>';
        } elseif ($pay['status'] !== 'en_attente') {
            echo '<p class="px-result-note">Ce lien de paiement a été annulé. Appelez-nous si besoin.</p>';
        } elseif (!payments_enabled()) {
            echo '<p class="px-result-note">Le paiement en ligne n\'est pas disponible pour le moment. Appelez-nous pour régler autrement.</p>';
        } else {
            echo '<div class="btn-row px-actions"><a class="btn btn-p btn-lg" href="'.e(payment_url((string)$pay['token']).'&go=1').'">💳 Payer par carte</a></div>'
               . '<p class="px-result-note">🔒 Vos coordonnées bancaires sont saisies chez Stripe, jamais sur notre site.</p>';
        }
        echo '</div>';
    }
    echo '</div></section>';
    render_footer();
}
