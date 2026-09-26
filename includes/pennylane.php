<?php
declare(strict_types=1);
/**
 * Client de l'API Pennylane v2 « external » (https://app.pennylane.com/api/external/v2).
 * Authentification par jeton Bearer (jeton d'API de l'entreprise, généré dans
 * Pennylane > Paramètres > Connectivité > Développeurs).
 * Chemins et champs vérifiés sur la spécification OpenAPI officielle (Accounting 2.0).
 *
 * Sans jeton, ou si le mode simulation est coché, toutes les fonctions renvoient des
 * données fictives marquées « SIMULATION » : le circuit reste testable de bout en bout.
 */

const PENNYLANE_BASE = 'https://app.pennylane.com/api/external/v2';

function pennylane_token(): string { return integration_secret('pennylane_api_key'); }

/** Simulation : pas de jeton, ou mode simulation forcé dans les réglages. */
function pennylane_simulated(): bool
{
    return pennylane_token() === '' || integration_setting('pennylane_simulation', '0') === '1';
}

/**
 * Requête Pennylane. Réessaie après une attente sur 429 (limite de requêtes) et sur
 * les erreurs serveur passagères. Retour : ['ok' => bool, 'status' => int, 'data' => ?array, 'error' => ?string].
 */
function pennylane_request(string $method, string $path, array $query = [], ?array $body = null, int $timeout = 25): array
{
    // Adresse remplaçable dans config.local.php (tests locaux uniquement).
    $url = (string)(app_config()['pennylane_base'] ?? PENNYLANE_BASE).$path.($query ? '?'.http_build_query($query) : '');
    // Nouvelle version de l'API (changements 2026, seule version disponible depuis juillet 2026).
    $headers = ['Authorization: Bearer '.pennylane_token(), 'Accept: application/json', 'X-Use-2026-API-Changes: true'];
    if ($body !== null) $headers[] = 'Content-Type: application/json';

    for ($attempt = 1; $attempt <= 4; $attempt++) {
        $res = integration_http($method, $url, $headers, $body, $timeout);
        $retryable = $res['status'] === 429 || in_array($res['status'], [502, 503, 504], true) || ($res['error'] !== null && $attempt < 2);
        if (!$retryable || $attempt === 4) break;
        // Attente demandée par l'API si elle est fournie, sinon attente progressive (1 s, 2 s, 4 s).
        $wait = (int)($res['headers']['retry-after'] ?? $res['headers']['ratelimit-reset'] ?? 0);
        $wait = $wait > 0 ? min($wait, 30) : (1 << ($attempt - 1));
        integration_log('pennylane', strtoupper($method).' '.$path.' : nouvel essai', ['http' => $res['status'], 'attente_s' => $wait]);
        sleep($wait);
    }

    $ok = $res['error'] === null && $res['status'] >= 200 && $res['status'] < 300;
    $ctx = ['http' => $res['status'], 'ms' => $res['ms']];
    if (!$ok) {
        $detail = $res['error'] ?? (string)($res['json']['message'] ?? $res['json']['error'] ?? '');
        integration_log('pennylane', strtoupper($method).' '.$path.' : échec', $ctx + ['detail' => mb_substr((string)$detail, 0, 300)]);
        $human = match (true) {
            $res['status'] === 401 => 'Jeton Pennylane refusé : vérifiez-le dans les réglages.',
            $res['status'] === 403 => 'Le jeton Pennylane n\'a pas les droits nécessaires pour cette action.',
            $res['status'] === 404 => 'Élément introuvable dans Pennylane.',
            $res['status'] === 409 => 'Pennylane n\'est pas encore prêt (document en cours de génération) : réessayez dans quelques minutes.',
            $res['status'] === 422 || $res['status'] === 400 => 'Pennylane a refusé les données : '.mb_substr((string)$detail, 0, 200),
            $res['status'] === 429 => 'Trop de requêtes vers Pennylane : réessayez dans une minute.',
            $res['error'] !== null => 'Connexion à Pennylane impossible (réseau ou délai dépassé).',
            default => 'Pennylane est momentanément indisponible (HTTP '.$res['status'].').',
        };
        return ['ok' => false, 'status' => $res['status'], 'data' => $res['json'], 'error' => $human];
    }
    integration_log('pennylane', strtoupper($method).' '.$path.' : ok', $ctx);
    return ['ok' => true, 'status' => $res['status'], 'data' => $res['json'] ?? [], 'error' => null];
}

function pennylane_test_connection(): array
{
    if (pennylane_token() === '') return ['ok' => false, 'message' => 'Aucun jeton : Pennylane fonctionne en mode SIMULATION.'];
    $r = pennylane_request('GET', '/me');
    if (!$r['ok']) return ['ok' => false, 'message' => $r['error']];
    $co = (string)($r['data']['company']['name'] ?? '');
    $mode = integration_setting('pennylane_simulation', '0') === '1' ? ' (le mode simulation reste activé : décochez-le pour travailler en réel)' : '';
    return ['ok' => true, 'message' => 'Connexion réussie'.($co !== '' ? ' — société « '.$co.' »' : '').$mode.'.'];
}

/* ═══════════════════════════════════════════════════════════
   Utilitaires
═══════════════════════════════════════════════════════════ */

/** Filtre au format Pennylane : [{"field":…,"operator":…,"value":…}]. */
function pennylane_filter(array $conds): string
{
    return json_encode(array_map(static fn($c) => ['field' => $c[0], 'operator' => $c[1], 'value' => $c[2]], $conds), JSON_UNESCAPED_UNICODE);
}

/** Parcourt toutes les pages d'une liste (curseur). $onItem reçoit chaque élément. */
function pennylane_each(string $path, array $query, callable $onItem, int $maxPages = 500): array
{
    $query['limit'] = $query['limit'] ?? 100;
    $n = 0;
    for ($page = 0; $page < $maxPages; $page++) {
        $r = pennylane_request('GET', $path, $query);
        if (!$r['ok']) return ['ok' => false, 'count' => $n, 'error' => $r['error']];
        foreach ((array)($r['data']['items'] ?? []) as $it) { $onItem($it); $n++; }
        if (empty($r['data']['has_more']) || empty($r['data']['next_cursor'])) break;
        $query['cursor'] = $r['data']['next_cursor'];
        usleep(250000);   // reste sous la limite de requêtes de Pennylane
    }
    return ['ok' => true, 'count' => $n, 'error' => null];
}

/** Code TVA Pennylane (FR_200 = 20 %, FR_100 = 10 %, FR_55 = 5,5 %). */
function pennylane_vat_code(float $rate): string
{
    return match (true) {
        abs($rate - 5.5) < 0.01 => 'FR_55',
        abs($rate - 10.0) < 0.01 => 'FR_100',
        default => 'FR_200',
    };
}

function pennylane_digits9(string $phone): string
{
    $d = preg_replace('/\D/', '', $phone);
    return strlen($d) >= 9 ? substr($d, -9) : '';
}

/** Statut local d'une facture à partir de la réponse Pennylane. */
function pennylane_local_status(array $pi): string
{
    $st = (string)($pi['status'] ?? '');
    return match (true) {
        !empty($pi['draft']) || $st === 'draft' => 'brouillon',
        !empty($pi['paid']) || $st === 'paid'  => 'payee',
        in_array($st, ['cancelled', 'archived'], true) => 'annulee',
        default => 'envoyee',
    };
}

/* ═══════════════════════════════════════════════════════════
   Clients
═══════════════════════════════════════════════════════════ */

/** Corps de création d'un client Pennylane (particulier ou entreprise). */
function pennylane_customer_body(array $c): array
{
    $addr = ['address' => trim((string)$c['address']) ?: '-', 'postal_code' => trim((string)$c['postal_code']) ?: '-',
             'city' => trim((string)$c['city']) ?: '-', 'country_alpha2' => 'FR'];
    $emails = filter_var(trim((string)($c['email'] ?? '')), FILTER_VALIDATE_EMAIL) ? [trim((string)$c['email'])] : [];
    $common = ['phone' => trim((string)($c['phone'] ?? '')), 'billing_address' => $addr, 'emails' => $emails,
               'external_reference' => 'EMAE-C'.(int)$c['id'], 'billing_language' => 'fr_FR'];
    if (($c['client_type'] ?? '') === 'professionnel') {
        return ['kind' => 'company', 'body' => $common + ['name' => trim($c['lastname'].' '.$c['firstname']) ?: 'Client '.(int)$c['id'],
            'recipient' => trim((string)$c['firstname']), 'payment_conditions' => '30_days']];
    }
    return ['kind' => 'individual', 'body' => $common + ['first_name' => trim((string)$c['firstname']) ?: '-', 'last_name' => trim((string)$c['lastname']) ?: 'Client',
        'payment_conditions' => 'upon_receipt']];
}

/**
 * Identifiant Pennylane du client local : déjà lié, sinon retrouvé (référence EMAE, e-mail),
 * sinon créé. Retour : ['ok' => bool, 'id' => ?string, 'error' => ?string]
 */
function pennylane_ensure_customer(int $clientId): array
{
    $c = get_client_by_id($clientId);
    if (!$c) return ['ok' => false, 'id' => null, 'error' => 'Client introuvable.'];
    if (!empty($c['pennylane_customer_id'])) return ['ok' => true, 'id' => (string)$c['pennylane_customer_id'], 'error' => null];
    if (pennylane_simulated()) {
        $id = 'SIM-C'.$clientId;
        db_execute('UPDATE clients SET pennylane_customer_id = ? WHERE id = ?', [$id, $clientId]);
        return ['ok' => true, 'id' => $id, 'error' => null];
    }
    $found = null;
    $r = pennylane_request('GET', '/customers', ['filter' => pennylane_filter([['external_reference', 'eq', 'EMAE-C'.$clientId]]), 'limit' => 1]);
    if ($r['ok'] && !empty($r['data']['items'][0]['id'])) $found = $r['data']['items'][0];
    $email = trim((string)$c['email']);
    if (!$found && filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $r = pennylane_request('GET', '/customers', ['filter' => pennylane_filter([['emails', 'in', $email]]), 'limit' => 1]);
        if ($r['ok'] && !empty($r['data']['items'][0]['id'])) $found = $r['data']['items'][0];
    }
    if (!$found) {
        $b = pennylane_customer_body($c);
        $r = pennylane_request('POST', $b['kind'] === 'company' ? '/company_customers' : '/individual_customers', [], $b['body']);
        if (!$r['ok']) return ['ok' => false, 'id' => null, 'error' => 'Client non créé dans Pennylane : '.$r['error']];
        $found = $r['data'];
    }
    $id = (string)$found['id'];
    db_execute('UPDATE clients SET pennylane_customer_id = ?, pennylane_synced_at = NOW() WHERE id = ?', [$id, $clientId]);
    integration_log('pennylane', 'client lié', ['client' => $clientId, 'pennylane' => $id]);
    return ['ok' => true, 'id' => $id, 'error' => null];
}

/**
 * Rapproche un client Pennylane d'un client local : lien existant, e-mail, téléphone ;
 * nom + code postal seulement → doublon douteux à confirmer ; rien → client importé.
 */
function pennylane_import_customer(array $pc, array &$stats): void
{
    $pid = (string)$pc['id'];
    if (db_fetch('SELECT id FROM clients WHERE pennylane_customer_id = ?', [$pid])) return;
    $isCompany = ($pc['customer_type'] ?? '') === 'company';
    $email = strtolower(trim((string)($pc['emails'][0] ?? '')));
    $phone9 = pennylane_digits9((string)($pc['phone'] ?? ''));
    $last = $isCompany ? trim((string)($pc['name'] ?? '')) : trim((string)($pc['last_name'] ?? ''));
    $first = $isCompany ? '' : trim((string)($pc['first_name'] ?? ''));
    $cp = trim((string)($pc['billing_address']['postal_code'] ?? ''));

    $match = null;
    if ($email !== '') $match = db_fetch('SELECT id FROM clients WHERE LOWER(email) = ? AND (pennylane_customer_id IS NULL OR pennylane_customer_id = \'\') ORDER BY id LIMIT 1', [$email]);
    if (!$match && $phone9 !== '') {
        $match = db_fetch("SELECT id FROM clients WHERE REPLACE(REPLACE(REPLACE(REPLACE(phone,' ',''),'.',''),'-',''),'+33','0') LIKE ? AND (pennylane_customer_id IS NULL OR pennylane_customer_id = '') ORDER BY id LIMIT 1", ['%'.$phone9]);
    }
    if ($match) {
        db_execute('UPDATE clients SET pennylane_customer_id = ?, pennylane_synced_at = NOW() WHERE id = ?', [$pid, (int)$match['id']]);
        $stats['linked']++;
        return;
    }
    if ($last !== '' && $cp !== '') {
        $doubt = db_fetch('SELECT id FROM clients WHERE LOWER(lastname) = LOWER(?) AND postal_code = ? AND (pennylane_customer_id IS NULL OR pennylane_customer_id = \'\') ORDER BY id LIMIT 1', [$last, $cp]);
        $pair = $doubt ? db_fetch('SELECT status FROM pennylane_duplicates WHERE pennylane_customer_id = ? AND client_id = ?', [$pid, (int)$doubt['id']]) : null;
        if ($doubt && ($pair['status'] ?? '') !== 'distinct') {
            db_execute('INSERT IGNORE INTO pennylane_duplicates (pennylane_customer_id, client_id, reason, data) VALUES (?,?,?,?)',
                [$pid, (int)$doubt['id'], 'Même nom et même code postal', json_encode(['name' => trim($first.' '.$last), 'email' => $email, 'phone' => (string)($pc['phone'] ?? ''), 'city' => (string)($pc['billing_address']['city'] ?? '')], JSON_UNESCAPED_UNICODE)]);
            $stats['doubtful']++;
            return;
        }
    }
    $newId = create_client([
        'lastname' => $last ?: 'Client Pennylane '.$pid, 'firstname' => $first, 'phone' => (string)($pc['phone'] ?? ''), 'email' => $email,
        'address' => (string)($pc['billing_address']['address'] ?? ''), 'postal_code' => $cp, 'city' => (string)($pc['billing_address']['city'] ?? ''),
        'notes' => 'Importé de Pennylane',
    ]);
    db_execute('UPDATE clients SET pennylane_customer_id = ?, pennylane_synced_at = NOW(), client_type = ? WHERE id = ?', [$pid, $isCompany ? 'professionnel' : 'particulier', $newId]);
    $stats['created']++;
}

/* ═══════════════════════════════════════════════════════════
   Produits (grille tarifaire)
═══════════════════════════════════════════════════════════ */

/** Crée ou met à jour dans Pennylane les produits correspondant à la grille (hors majorations). */
function pennylane_sync_products(): array
{
    $n = ['created' => 0, 'updated' => 0, 'errors' => 0];
    if (pennylane_simulated()) return $n;
    foreach (price_grid_rows(true) as $g) {
        if ((int)$g['is_percent'] === 1) continue;
        $price = number_format((float)$g['price_ht'], 2, '.', '');
        $body = ['label' => (string)$g['label'], 'price_before_tax' => $price, 'vat_rate' => pennylane_vat_code((float)($g['vat_rate'] ?? 20)),
                 'unit' => price_units()[$g['unit']] ?? (string)$g['unit'], 'currency' => 'EUR', 'reference' => (string)$g['code'], 'external_reference' => 'EMAE-P-'.$g['code']];
        if (empty($g['pennylane_product_id'])) {
            $r = pennylane_request('GET', '/products', ['filter' => pennylane_filter([['external_reference', 'eq', 'EMAE-P-'.$g['code']]]), 'limit' => 1]);
            $pid = $r['ok'] ? ($r['data']['items'][0]['id'] ?? null) : null;
            if (!$pid) {
                $r = pennylane_request('POST', '/products', [], $body);
                if (!$r['ok']) { $n['errors']++; continue; }
                $pid = $r['data']['id'] ?? null;
                $n['created']++;
            }
            if ($pid) db_execute('UPDATE price_grid SET pennylane_product_id = ?, pennylane_price = ? WHERE id = ?', [(string)$pid, $price, (int)$g['id']]);
        } elseif ((string)($g['pennylane_price'] ?? '') !== $price) {
            unset($body['external_reference']);
            $r = pennylane_request('PUT', '/products/'.rawurlencode((string)$g['pennylane_product_id']), [], $body);
            if ($r['ok']) { db_execute('UPDATE price_grid SET pennylane_price = ? WHERE id = ?', [$price, (int)$g['id']]); $n['updated']++; }
            else $n['errors']++;
        }
        usleep(200000);
    }
    return $n;
}

/* ═══════════════════════════════════════════════════════════
   Factures
═══════════════════════════════════════════════════════════ */

function invoice_by_id(int $id): ?array
{
    review_tables();
    $r = db_fetch('SELECT * FROM invoices WHERE id = ?', [$id]);
    if (!$r) return null;
    $r['lines'] = json_decode((string)$r['line_items'], true) ?: [];
    return $r;
}

/** Lignes au format Pennylane (prix HT unitaire, quantité, TVA ; produit lié si connu). */
function pennylane_invoice_lines(array $inv): array
{
    $vat = pennylane_vat_code((float)$inv['vat_rate']);
    $products = [];
    foreach (db_fetch_all("SELECT code, pennylane_product_id FROM price_grid WHERE pennylane_product_id IS NOT NULL") as $g) $products[$g['code']] = $g['pennylane_product_id'];
    $out = [];
    foreach ($inv['lines'] as $l) {
        if (empty($l['pennylane_product_id']) && !empty($l['code']) && isset($products[$l['code']])) $l['pennylane_product_id'] = $products[$l['code']];
        $line = [
            'label' => mb_substr((string)$l['label'], 0, 250),
            'raw_currency_unit_price' => number_format((float)$l['unit_price_ht'], 2, '.', ''),
            'unit' => ($l['unit'] ?? '') === 'pourcent' ? 'forfait' : (price_units()[$l['unit'] ?? 'unite'] ?? 'unité'),
            'vat_rate' => $vat,
            'quantity' => (float)$l['qty'],
        ];
        if (!empty($l['pennylane_product_id']) && ($l['unit'] ?? '') !== 'pourcent' && empty($l['free'])) $line['product_id'] = (int)$l['pennylane_product_id'];
        $out[] = $line;
    }
    return $out;
}

/**
 * Envoie (ou remplace) le brouillon dans Pennylane. Un brouillon Pennylane déjà créé est supprimé
 * puis recréé pour refléter exactement les lignes locales. Ne touche jamais une facture finalisée.
 */
function pennylane_push_draft(int $invoiceId): array
{
    $inv = invoice_by_id($invoiceId);
    if (!$inv) return ['ok' => false, 'error' => 'Facture introuvable.'];
    if ($inv['status'] !== 'brouillon') return ['ok' => false, 'error' => 'Seul un brouillon peut être envoyé à Pennylane.'];
    if (empty($inv['client_id'])) return ['ok' => false, 'error' => 'Aucun client sur la facture.'];

    if (pennylane_simulated()) {
        db_execute("UPDATE invoices SET pennylane_id = ?, pennylane_status = 'draft', sync_error = NULL, last_sync_at = NOW() WHERE id = ?", ['SIM-'.$invoiceId, $invoiceId]);
        pennylane_ensure_customer((int)$inv['client_id']);
        return ['ok' => true, 'error' => null, 'simulated' => true];
    }
    $cust = pennylane_ensure_customer((int)$inv['client_id']);
    if (!$cust['ok']) { db_execute('UPDATE invoices SET sync_error = ? WHERE id = ?', [mb_substr((string)$cust['error'], 0, 500), $invoiceId]); return ['ok' => false, 'error' => $cust['error']]; }
    if (!empty($inv['pennylane_id']) && !str_starts_with((string)$inv['pennylane_id'], 'SIM-')) {
        $del = pennylane_request('DELETE', '/customer_invoices/'.rawurlencode((string)$inv['pennylane_id']));
        if (!$del['ok'] && $del['status'] !== 404) {
            db_execute('UPDATE invoices SET sync_error = ? WHERE id = ?', [mb_substr('Ancien brouillon non supprimé : '.$del['error'], 0, 500), $invoiceId]);
            return ['ok' => false, 'error' => $del['error']];
        }
    }
    $client = get_client_by_id((int)$inv['client_id']);
    $days = (($client['client_type'] ?? '') === 'professionnel') ? 30 : 0;
    $body = [
        'date' => date('Y-m-d'), 'deadline' => date('Y-m-d', strtotime('+'.$days.' days')),
        'customer_id' => (int)$cust['id'], 'draft' => true, 'currency' => 'EUR', 'language' => 'fr_FR',
        'label' => mb_substr((string)$inv['label'], 0, 250), 'pdf_invoice_subject' => mb_substr((string)$inv['label'], 0, 250),
        'pdf_description' => mb_substr((string)$inv['summary'], 0, 5000),
        'external_reference' => 'EMAE-F'.$invoiceId.'-'.date('YmdHis'),
        'invoice_lines' => pennylane_invoice_lines($inv),
    ];
    $r = pennylane_request('POST', '/customer_invoices', [], $body);
    if (!$r['ok']) {
        db_execute('UPDATE invoices SET pennylane_id = NULL, sync_error = ? WHERE id = ?', [mb_substr((string)$r['error'], 0, 500), $invoiceId]);
        return ['ok' => false, 'error' => $r['error']];
    }
    pennylane_store_invoice($invoiceId, $r['data']);
    // Contrôle : le total calculé par Pennylane doit être celui de la grille.
    $plTotal = (float)($r['data']['currency_amount'] ?? $r['data']['amount'] ?? 0);
    if ($plTotal > 0 && abs($plTotal - (float)$inv['total_ttc']) >= 0.02) {
        db_execute('UPDATE invoices SET sync_error = ? WHERE id = ?', ['Écart de total : Pennylane '.money_fr($plTotal).' / EMAE '.money_fr((float)$inv['total_ttc']).' (arrondis de TVA).', $invoiceId]);
    }
    return ['ok' => true, 'error' => null];
}

/** Enregistre dans la facture locale ce que renvoie Pennylane (numéro, statut, reste à payer…). */
function pennylane_store_invoice(int $invoiceId, array $pi): void
{
    $status = pennylane_local_status($pi);
    $cur = db_fetch('SELECT status FROM invoices WHERE id = ?', [$invoiceId]);
    // « validée » (finalisée, pas encore envoyée) est une étape locale : l'envoi la fait passer à « envoyée ».
    if ($cur && in_array($cur['status'], ['brouillon', 'validee'], true) && $status === 'envoyee') $status = 'validee';
    if ($cur && $cur['status'] === 'validee' && $status === 'brouillon') $status = 'validee';
    db_execute('UPDATE invoices SET pennylane_id = ?, pennylane_status = ?, number = COALESCE(NULLIF(?, \'\'), number), status = ?,
        remaining_ttc = ?, pdf_url = COALESCE(?, pdf_url), issue_date = COALESCE(?, issue_date), due_date = COALESCE(?, due_date),
        paid_at = CASE WHEN ? = \'payee\' AND paid_at IS NULL THEN NOW() ELSE paid_at END,
        sync_error = NULL, last_sync_at = NOW(), updated_at = NOW() WHERE id = ?', [
        (string)$pi['id'], (string)($pi['status'] ?? ''), (string)($pi['invoice_number'] ?? ''), $status,
        isset($pi['remaining_amount_with_tax']) ? (float)$pi['remaining_amount_with_tax'] : null,
        $pi['public_file_url'] ?? null, $pi['date'] ?? null, $pi['deadline'] ?? null, $status, $invoiceId,
    ]);
    pennylane_sync_intervention_status($invoiceId);
}

/** Paiement constaté dans Pennylane : l'intervention passe « Payée » puis « Clôturée ». */
function pennylane_sync_intervention_status(int $invoiceId): void
{
    $inv = db_fetch('SELECT id, intervention_id, status, number FROM invoices WHERE id = ?', [$invoiceId]);
    if (!$inv || empty($inv['intervention_id']) || $inv['status'] !== 'payee') return;
    $iv = db_fetch('SELECT id, status FROM interventions WHERE id = ?', [(int)$inv['intervention_id']]);
    if (!$iv || !in_array($iv['status'], ['facture_validee', 'facture_envoyee', 'facture_brouillon'], true)) return;
    wf_set_status((int)$iv['id'], 'payé', 'system', 0, 'Pennylane', 'Paiement constaté dans Pennylane ('.($inv['number'] ?: 'facture').')',
        ['payment_status' => 'payé', 'paid_at' => date('Y-m-d H:i:s')]);
    wf_set_status((int)$iv['id'], 'cloturee', 'system', 0, 'Pennylane', 'Dossier clôturé automatiquement après paiement');
}

/** Finalise la facture (numéro définitif). Retour : ['ok', 'error', 'number']. */
function pennylane_finalize(int $invoiceId): array
{
    $inv = invoice_by_id($invoiceId);
    if (!$inv || empty($inv['pennylane_id'])) return ['ok' => false, 'error' => 'Brouillon absent de Pennylane.', 'number' => null];
    if (pennylane_simulated()) {
        $num = 'SIM-'.date('Y').'-'.str_pad((string)$invoiceId, 5, '0', STR_PAD_LEFT);
        db_execute("UPDATE invoices SET number = ?, pennylane_status = 'upcoming', issue_date = CURDATE(), remaining_ttc = total_ttc, updated_at = NOW() WHERE id = ?", [$num, $invoiceId]);
        return ['ok' => true, 'error' => null, 'number' => $num];
    }
    $r = pennylane_request('PUT', '/customer_invoices/'.rawurlencode((string)$inv['pennylane_id']).'/finalize');
    if (!$r['ok']) return ['ok' => false, 'error' => $r['error'], 'number' => null];
    pennylane_store_invoice($invoiceId, $r['data']);
    return ['ok' => true, 'error' => null, 'number' => (string)($r['data']['invoice_number'] ?? '')];
}

/**
 * Envoi par e-mail par Pennylane. Juste après la finalisation, Pennylane peut répondre 409
 * (PDF en cours de génération) : on réessaie quelques fois avant d'abandonner.
 */
function pennylane_send_email(int $invoiceId, array $recipients = []): array
{
    $inv = invoice_by_id($invoiceId);
    if (!$inv || empty($inv['pennylane_id'])) return ['ok' => false, 'error' => 'Facture absente de Pennylane.'];
    if (pennylane_simulated()) return ['ok' => true, 'error' => null, 'simulated' => true];
    // Liste vide : Pennylane utilise les adresses e-mail de la fiche client.
    $body = ['recipients' => array_values($recipients)];
    for ($i = 0; $i < 4; $i++) {
        $r = pennylane_request('POST', '/customer_invoices/'.rawurlencode((string)$inv['pennylane_id']).'/send_by_email', [], $body);
        if ($r['ok']) return ['ok' => true, 'error' => null];
        if ($r['status'] !== 409) return ['ok' => false, 'error' => $r['error']];
        sleep(3 + 2 * $i);
    }
    return ['ok' => false, 'error' => 'Le PDF de la facture est encore en préparation chez Pennylane : réessayez l\'envoi dans quelques minutes.'];
}

function pennylane_mark_paid(int $invoiceId): array
{
    $inv = invoice_by_id($invoiceId);
    if (!$inv || empty($inv['pennylane_id'])) return ['ok' => false, 'error' => 'Facture absente de Pennylane.'];
    if (!pennylane_simulated()) {
        $r = pennylane_request('PUT', '/customer_invoices/'.rawurlencode((string)$inv['pennylane_id']).'/mark_as_paid');
        if (!$r['ok']) return ['ok' => false, 'error' => $r['error']];
    }
    db_execute("UPDATE invoices SET status = 'payee', pennylane_status = 'paid', remaining_ttc = 0, paid_at = COALESCE(paid_at, NOW()), updated_at = NOW() WHERE id = ?", [$invoiceId]);
    pennylane_sync_intervention_status($invoiceId);
    return ['ok' => true, 'error' => null];
}

/** Importe ou met à jour une facture Pennylane dans le cache local. */
function pennylane_upsert_invoice(array $pi, array &$stats): void
{
    $pid = (string)$pi['id'];
    $local = db_fetch('SELECT id FROM invoices WHERE pennylane_id = ?', [$pid]);
    if (!$local) {
        // Facture créée par EMAE : retrouvée par sa référence externe.
        if (preg_match('/^EMAE-F(\d+)-/', (string)($pi['external_reference'] ?? ''), $m)) $local = db_fetch('SELECT id FROM invoices WHERE id = ?', [(int)$m[1]]);
    }
    if ($local) { pennylane_store_invoice((int)$local['id'], $pi); $stats['updated']++; return; }
    if (!empty($pi['credited_invoice'])) return;   // avoirs : non repris dans le cache
    $client = !empty($pi['customer']['id']) ? db_fetch('SELECT id FROM clients WHERE pennylane_customer_id = ?', [(string)$pi['customer']['id']]) : null;
    $ttc = (float)($pi['currency_amount'] ?? $pi['amount'] ?? 0);
    $ht = (float)($pi['currency_amount_before_tax'] ?? 0);
    db_execute('INSERT INTO invoices (client_id, status, number, label, total_ht, total_tva, total_ttc, remaining_ttc, pennylane_id, pennylane_status, issue_date, due_date, paid_at, last_sync_at, updated_at, created_at)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW(),?)', [
        $client ? (int)$client['id'] : null, pennylane_local_status($pi), (string)($pi['invoice_number'] ?? ''), mb_substr((string)($pi['label'] ?? ''), 0, 255),
        $ht, round($ttc - $ht, 2), $ttc, isset($pi['remaining_amount_with_tax']) ? (float)$pi['remaining_amount_with_tax'] : null,
        $pid, (string)($pi['status'] ?? ''), $pi['date'] ?? null, $pi['deadline'] ?? null, !empty($pi['paid']) ? date('Y-m-d H:i:s', strtotime((string)($pi['updated_at'] ?? 'now'))) : null,
        !empty($pi['created_at']) ? date('Y-m-d H:i:s', strtotime((string)$pi['created_at'])) : date('Y-m-d H:i:s'),
    ]);
    $stats['imported']++;
}

/* ═══════════════════════════════════════════════════════════
   Synchronisation (cron toutes les 15 min + bouton des réglages)
═══════════════════════════════════════════════════════════ */

/**
 * Synchronisation idempotente, protégée par un verrou (une seule à la fois).
 * - 1re fois (ou plus de 25 jours sans synchronisation) : import complet des clients et factures ;
 * - ensuite : journaux de modifications (changelogs) depuis la dernière synchronisation ;
 * - produits de la grille, brouillons en attente d'envoi.
 */
function pennylane_sync_run(string $origin = 'cron'): array
{
    review_tables();
    $lockPath = __DIR__.'/../storage/pennylane_sync.lock';
    $fh = @fopen($lockPath, 'c');
    if (!$fh || !flock($fh, LOCK_EX | LOCK_NB)) return ['ok' => false, 'message' => 'Une synchronisation est déjà en cours.'];
    @set_time_limit(600);
    $started = date('c');
    $save = static function (bool $ok, string $msg) use ($origin, $fh): array {
        notif_store_setting('pennylane_last_sync', json_encode(['at' => date('c'), 'message' => $msg, 'origin' => $origin, 'ok' => $ok], JSON_UNESCAPED_UNICODE));
        integration_log('pennylane', 'synchronisation '.$origin.' : '.$msg);
        flock($fh, LOCK_UN); fclose($fh);
        return ['ok' => $ok, 'message' => $msg];
    };
    if (pennylane_simulated()) return $save(true, 'SIMULATION : aucune donnée échangée avec Pennylane (aucun jeton ou mode simulation activé).');

    $cs = ['linked' => 0, 'doubtful' => 0, 'created' => 0];
    $is = ['imported' => 0, 'updated' => 0];
    $errors = [];
    $state = json_decode(integration_setting('pennylane_sync_state', ''), true) ?: [];
    $since = (string)($state['since'] ?? '');
    $full = $since === '' || strtotime($since) < strtotime('-25 days');

    if ($full) {
        $r = pennylane_each('/customers', [], static function ($pc) use (&$cs) { pennylane_import_customer($pc, $cs); });
        if (!$r['ok']) $errors[] = 'clients : '.$r['error'];
        $r = pennylane_each('/customer_invoices', [], static function ($pi) use (&$is) { pennylane_upsert_invoice($pi, $is); });
        if (!$r['ok']) $errors[] = 'factures : '.$r['error'];
    } else {
        $custIds = []; $invIds = []; $deleted = [];
        $r = pennylane_each('/changelogs/customers', ['start_date' => $since, 'limit' => 1000], static function ($ch) use (&$custIds) { if (($ch['operation'] ?? '') !== 'delete') $custIds[(string)$ch['id']] = true; });
        if (!$r['ok']) $errors[] = 'journal clients : '.$r['error'];
        $r = pennylane_each('/changelogs/customer_invoices', ['start_date' => $since, 'limit' => 1000], static function ($ch) use (&$invIds, &$deleted) {
            if (($ch['operation'] ?? '') === 'delete') $deleted[(string)$ch['id']] = true; else $invIds[(string)$ch['id']] = true;
        });
        if (!$r['ok']) $errors[] = 'journal factures : '.$r['error'];
        foreach (array_keys($custIds) as $cid) {
            $g = pennylane_request('GET', '/customers/'.rawurlencode((string)$cid));
            if ($g['ok']) pennylane_import_customer($g['data'], $cs);
            usleep(200000);
        }
        foreach (array_keys($invIds) as $iid) {
            $g = pennylane_request('GET', '/customer_invoices/'.rawurlencode((string)$iid));
            if ($g['ok']) pennylane_upsert_invoice($g['data'], $is);
            usleep(200000);
        }
        foreach (array_keys($deleted) as $iid) {
            // Brouillon supprimé côté Pennylane : on garde la facture locale, sans lien.
            db_execute("UPDATE invoices SET pennylane_id = NULL, pennylane_status = 'deleted' WHERE pennylane_id = ? AND status = 'brouillon'", [(string)$iid]);
        }
    }
    $ps = pennylane_sync_products();
    // Brouillons locaux jamais arrivés dans Pennylane (panne lors de la création) : nouvel essai.
    $retried = 0;
    foreach (db_fetch_all("SELECT id FROM invoices WHERE status = 'brouillon' AND intervention_id IS NOT NULL AND (pennylane_id IS NULL OR pennylane_id LIKE 'SIM-%') LIMIT 20") as $d) {
        if (pennylane_push_draft((int)$d['id'])['ok']) $retried++;
    }
    if (!$errors) notif_store_setting('pennylane_sync_state', json_encode(['since' => $started, 'full' => $full ? $started : ($state['full'] ?? null)]));
    $msg = ($full ? 'Import complet' : 'Mise à jour').' — clients : '.$cs['linked'].' liés, '.$cs['created'].' importés, '.$cs['doubtful'].' à vérifier ; factures : '
        .$is['imported'].' importées, '.$is['updated'].' mises à jour ; produits : '.$ps['created'].' créés, '.$ps['updated'].' mis à jour'
        .($retried ? ' ; '.$retried.' brouillon(s) renvoyé(s)' : '').($errors ? '. Erreurs : '.implode(' ; ', $errors) : '.');
    return $save(!$errors, $msg);
}
