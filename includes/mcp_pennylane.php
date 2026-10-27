<?php
declare(strict_types=1);
/**
 * Serveur MCP « Pennylane » pour Claude (connecteur personnalisé claude.ai).
 * Point d'entrée HTTP : api/mcp_pennylane.php ; ici, seulement la logique (testable sans réseau).
 *
 * Ce que Claude peut faire :
 * - lire : société, factures clients, clients, produits, devis ;
 * - créer : une facture en BROUILLON (jamais finalisée ni envoyée) ou un devis.
 * Aucune suppression, finalisation, envoi d'e-mail ni marquage « payée » n'est exposé.
 *
 * Toutes les requêtes passent par pennylane_request() : jeton, réessais, journal et mode
 * simulation sont donc les mêmes que pour le reste d'EMAE.
 */

const MCP_PENNYLANE_VERSIONS = ['2025-06-18', '2025-03-26', '2024-11-05'];

/** Clé secrète qui protège l'adresse du connecteur ; vide = connecteur désactivé. */
function mcp_pennylane_key(): string { return integration_secret('mcp_pennylane_key'); }

/** Vérifie la clé reçue (paramètre « cle » de l'adresse, ou en-tête Authorization: Bearer). */
function mcp_pennylane_authorized(string $given): bool
{
    $key = mcp_pennylane_key();
    return $key !== '' && $given !== '' && hash_equals($key, $given);
}

/* ═══════════════════════════════════════════════════════════
   Déclaration des outils
═══════════════════════════════════════════════════════════ */

function mcp_pennylane_tools(): array
{
    $ro = ['readOnlyHint' => true, 'destructiveHint' => false, 'idempotentHint' => true, 'openWorldHint' => true];
    $create = ['readOnlyHint' => false, 'destructiveHint' => false, 'idempotentHint' => false, 'openWorldHint' => true];
    $page = [
        'limit'  => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'description' => 'Nombre d\'éléments (20 par défaut, 100 au maximum).'],
        'cursor' => ['type' => 'string', 'description' => 'Curseur « next_cursor » renvoyé par l\'appel précédent, pour la page suivante.'],
    ];
    $id = static fn(string $what) => ['type' => 'object', 'properties' => ['id' => ['type' => 'integer', 'description' => 'Identifiant Pennylane '.$what.'.']], 'required' => ['id']];
    $doc = [
        'type' => 'object',
        'properties' => [
            'customer_id' => ['type' => 'integer', 'description' => 'Identifiant Pennylane du client (le trouver avec pennylane_list_customers).'],
            'lines' => [
                'type' => 'array', 'minItems' => 1, 'maxItems' => 100,
                'description' => 'Lignes du document.',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'label'         => ['type' => 'string', 'description' => 'Désignation.'],
                        'quantity'      => ['type' => 'number', 'exclusiveMinimum' => 0, 'description' => 'Quantité.'],
                        'unit_price_ht' => ['type' => 'number', 'description' => 'Prix unitaire HT en euros.'],
                        'vat_rate'      => ['type' => 'number', 'enum' => [20, 10, 5.5], 'description' => 'Taux de TVA en % (20 par défaut).'],
                        'unit'          => ['type' => 'string', 'description' => 'Unité affichée (« unité » par défaut, ex. « heure », « forfait », « m² »).'],
                        'product_id'    => ['type' => 'integer', 'description' => 'Produit Pennylane lié (facultatif).'],
                    ],
                    'required' => ['label', 'quantity', 'unit_price_ht'],
                ],
            ],
            'date'        => ['type' => 'string', 'description' => 'Date du document AAAA-MM-JJ (aujourd\'hui par défaut).'],
            'deadline'    => ['type' => 'string', 'description' => 'Échéance (facture) ou date de validité (devis), AAAA-MM-JJ. Par défaut : date + 30 jours.'],
            'subject'     => ['type' => 'string', 'description' => 'Objet affiché sur le PDF.'],
            'description' => ['type' => 'string', 'description' => 'Texte libre affiché sur le PDF (détail des travaux…).'],
        ],
        'required' => ['customer_id', 'lines'],
    ];

    return [
        ['name' => 'pennylane_company', 'title' => 'Société Pennylane',
         'description' => 'Renvoie la société rattachée au jeton Pennylane (permet de vérifier la connexion).',
         'inputSchema' => ['type' => 'object', 'properties' => new stdClass()], 'annotations' => $ro],
        ['name' => 'pennylane_list_customer_invoices', 'title' => 'Lister les factures clients',
         'description' => 'Liste les factures clients, les plus récentes d\'abord, avec filtres facultatifs.',
         'inputSchema' => ['type' => 'object', 'properties' => $page + [
             'customer_id'    => ['type' => 'integer', 'description' => 'Seulement les factures de ce client Pennylane.'],
             'invoice_number' => ['type' => 'string', 'description' => 'Numéro exact de facture.'],
             'date_from'      => ['type' => 'string', 'description' => 'Date de facture minimale AAAA-MM-JJ.'],
             'date_to'        => ['type' => 'string', 'description' => 'Date de facture maximale AAAA-MM-JJ.'],
         ]], 'annotations' => $ro],
        ['name' => 'pennylane_get_customer_invoice', 'title' => 'Détail d\'une facture',
         'description' => 'Renvoie le détail complet d\'une facture client (lignes, montants, statut, lien PDF).',
         'inputSchema' => $id('de la facture'), 'annotations' => $ro],
        ['name' => 'pennylane_list_customers', 'title' => 'Lister les clients',
         'description' => 'Liste les clients Pennylane ; filtre facultatif par e-mail ou référence externe (EMAE-C<n°> pour un client EMAE).',
         'inputSchema' => ['type' => 'object', 'properties' => $page + [
             'email'              => ['type' => 'string', 'description' => 'Adresse e-mail exacte du client.'],
             'external_reference' => ['type' => 'string', 'description' => 'Référence externe exacte.'],
         ]], 'annotations' => $ro],
        ['name' => 'pennylane_get_customer', 'title' => 'Détail d\'un client',
         'description' => 'Renvoie la fiche complète d\'un client Pennylane.',
         'inputSchema' => $id('du client'), 'annotations' => $ro],
        ['name' => 'pennylane_list_products', 'title' => 'Lister les produits',
         'description' => 'Liste les produits Pennylane (prix HT, TVA, unité).',
         'inputSchema' => ['type' => 'object', 'properties' => $page], 'annotations' => $ro],
        ['name' => 'pennylane_list_quotes', 'title' => 'Lister les devis',
         'description' => 'Liste les devis Pennylane, les plus récents d\'abord.',
         'inputSchema' => ['type' => 'object', 'properties' => $page], 'annotations' => $ro],
        ['name' => 'pennylane_get_quote', 'title' => 'Détail d\'un devis',
         'description' => 'Renvoie le détail complet d\'un devis Pennylane.',
         'inputSchema' => $id('du devis'), 'annotations' => $ro],
        ['name' => 'pennylane_create_draft_invoice', 'title' => 'Créer une facture en brouillon',
         'description' => 'Crée une facture client en BROUILLON dans Pennylane. Elle n\'est ni finalisée ni envoyée : '
             .'le numéro définitif et l\'envoi se font ensuite par une personne, dans Pennylane ou EMAE. '
             .'Récapitulez le client, les lignes et le total à l\'utilisateur et attendez son accord avant d\'appeler cet outil.',
         'inputSchema' => $doc, 'annotations' => $create],
        ['name' => 'pennylane_create_quote', 'title' => 'Créer un devis',
         'description' => 'Crée un devis dans Pennylane. Il n\'est pas envoyé au client. '
             .'Récapitulez le client, les lignes et le total à l\'utilisateur et attendez son accord avant d\'appeler cet outil.',
         'inputSchema' => $doc, 'annotations' => $create],
    ];
}

/* ═══════════════════════════════════════════════════════════
   Exécution des outils
═══════════════════════════════════════════════════════════ */

/** Exécute un outil. Retour : résultat MCP ['content' => [...], 'isError' => bool]. */
function mcp_pennylane_call(string $name, array $args): array
{
    try {
        $out = match ($name) {
            'pennylane_company'                => mcp_pl_get('/me'),
            'pennylane_list_customer_invoices' => mcp_pl_list('/customer_invoices', $args, mcp_pl_invoice_filters($args), 'mcp_pl_invoice_summary'),
            'pennylane_get_customer_invoice'   => mcp_pl_get('/customer_invoices/'.mcp_pl_id($args)),
            'pennylane_list_customers'         => mcp_pl_list('/customers', $args, mcp_pl_customer_filters($args), null),
            'pennylane_get_customer'           => mcp_pl_get('/customers/'.mcp_pl_id($args)),
            'pennylane_list_products'          => mcp_pl_list('/products', $args, [], null),
            'pennylane_list_quotes'            => mcp_pl_list('/quotes', $args, [], 'mcp_pl_invoice_summary'),
            'pennylane_get_quote'              => mcp_pl_get('/quotes/'.mcp_pl_id($args)),
            'pennylane_create_draft_invoice'   => mcp_pl_create('/customer_invoices', $args, true),
            'pennylane_create_quote'           => mcp_pl_create('/quotes', $args, false),
            default => throw new InvalidArgumentException('Outil inconnu : '.$name),
        };
    } catch (InvalidArgumentException $e) {
        return mcp_pl_result(['erreur' => $e->getMessage()], true);
    }
    return mcp_pl_result($out['data'], !$out['ok']);
}

function mcp_pl_result(mixed $data, bool $isError): array
{
    return ['content' => [['type' => 'text', 'text' => json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)]], 'isError' => $isError];
}

/** Identifiant numérique obligatoire (il est placé dans le chemin de l'URL). */
function mcp_pl_id(array $args): string
{
    $id = $args['id'] ?? null;
    if (!(is_int($id) || (is_string($id) && ctype_digit($id))) || (int)$id <= 0) throw new InvalidArgumentException('« id » doit être un identifiant numérique.');
    return (string)(int)$id;
}

function mcp_pl_date(array $args, string $key): ?string
{
    if (!isset($args[$key]) || $args[$key] === '') return null;
    $d = (string)$args[$key];
    $dt = DateTimeImmutable::createFromFormat('!Y-m-d', $d);
    if (!$dt || $dt->format('Y-m-d') !== $d) throw new InvalidArgumentException('« '.$key.' » doit être une date AAAA-MM-JJ.');
    return $d;
}

/** Ajoute la mention SIMULATION pour qu'une réponse fictive ne soit jamais prise pour vraie. */
function mcp_pl_wrap(array $r, array $data): array
{
    if (!empty($r['simulated'])) $data = ['simulation' => 'SIMULATION : aucune requête n\'a été envoyée à Pennylane, données fictives.'] + $data;
    return $data;
}

function mcp_pl_get(string $path): array
{
    $r = pennylane_request('GET', $path);
    if (!$r['ok']) return ['ok' => false, 'data' => ['erreur' => $r['error']]];
    return ['ok' => true, 'data' => mcp_pl_wrap($r, (array)$r['data'])];
}

function mcp_pl_list(string $path, array $args, array $filters, ?callable $summary): array
{
    $limit = (int)($args['limit'] ?? 20);
    $q = ['limit' => max(1, min(100, $limit)), 'sort' => '-id'];
    if (!empty($args['cursor'])) $q['cursor'] = (string)$args['cursor'];
    if ($filters) $q['filter'] = pennylane_filter($filters);
    $r = pennylane_request('GET', $path, $q);
    if (!$r['ok']) return ['ok' => false, 'data' => ['erreur' => $r['error']]];
    $items = (array)($r['data']['items'] ?? []);
    if ($summary) $items = array_map($summary, $items);
    return ['ok' => true, 'data' => mcp_pl_wrap($r, [
        'items' => $items, 'count' => count($items),
        'has_more' => (bool)($r['data']['has_more'] ?? false), 'next_cursor' => $r['data']['next_cursor'] ?? null,
    ])];
}

function mcp_pl_invoice_filters(array $args): array
{
    $f = [];
    if (isset($args['customer_id']) && $args['customer_id'] !== '') $f[] = ['customer_id', 'eq', (int)$args['customer_id']];
    if (!empty($args['invoice_number'])) $f[] = ['invoice_number', 'eq', (string)$args['invoice_number']];
    if ($d = mcp_pl_date($args, 'date_from')) $f[] = ['date', 'gteq', $d];
    if ($d = mcp_pl_date($args, 'date_to')) $f[] = ['date', 'lteq', $d];
    return $f;
}

function mcp_pl_customer_filters(array $args): array
{
    $f = [];
    if (!empty($args['email'])) $f[] = ['emails', 'in', (string)$args['email']];
    if (!empty($args['external_reference'])) $f[] = ['external_reference', 'eq', (string)$args['external_reference']];
    return $f;
}

/** Résumé compact d'une facture ou d'un devis pour les listes (le détail s'obtient par son id). */
function mcp_pl_invoice_summary(array $i): array
{
    return [
        'id' => $i['id'] ?? null, 'number' => $i['invoice_number'] ?? $i['quote_number'] ?? null, 'label' => $i['label'] ?? null,
        'status' => $i['status'] ?? null, 'draft' => $i['draft'] ?? null, 'paid' => $i['paid'] ?? null,
        'date' => $i['date'] ?? null, 'deadline' => $i['deadline'] ?? null, 'customer_id' => $i['customer']['id'] ?? null,
        'total_ht' => $i['currency_amount_before_tax'] ?? null, 'total_ttc' => $i['currency_amount'] ?? $i['amount'] ?? null,
        'remaining_ttc' => $i['remaining_amount_with_tax'] ?? null, 'pdf_url' => $i['public_file_url'] ?? null,
    ];
}

/** Lignes reçues de Claude → lignes au format Pennylane, avec contrôles. Taux de TVA : 20, 10 ou 5,5 seulement. */
function mcp_pl_lines(array $lines): array
{
    if (!$lines || !array_is_list($lines) || count($lines) > 100) throw new InvalidArgumentException('« lines » doit contenir entre 1 et 100 lignes.');
    $out = [];
    foreach ($lines as $n => $l) {
        $pos = 'ligne '.($n + 1);
        if (!is_array($l)) throw new InvalidArgumentException($pos.' : format invalide.');
        $label = trim((string)($l['label'] ?? ''));
        if ($label === '') throw new InvalidArgumentException($pos.' : désignation manquante.');
        if (!is_numeric($l['quantity'] ?? null) || (float)$l['quantity'] <= 0) throw new InvalidArgumentException($pos.' : quantité invalide.');
        if (!is_numeric($l['unit_price_ht'] ?? null)) throw new InvalidArgumentException($pos.' : prix unitaire HT invalide.');
        $rate = (float)($l['vat_rate'] ?? 20);
        if (!in_array($rate, [20.0, 10.0, 5.5], true)) throw new InvalidArgumentException($pos.' : TVA de 20, 10 ou 5,5 % seulement.');
        $line = [
            'label' => mb_substr($label, 0, 250),
            'raw_currency_unit_price' => number_format((float)$l['unit_price_ht'], 2, '.', ''),
            'unit' => mb_substr(trim((string)($l['unit'] ?? '')) ?: 'unité', 0, 50),
            'vat_rate' => pennylane_vat_code($rate),
            'quantity' => (float)$l['quantity'],
        ];
        if (!empty($l['product_id'])) $line['product_id'] = (int)$l['product_id'];
        $out[] = $line;
    }
    return $out;
}

/** Total TTC indicatif (arrondi ligne par ligne), pour contrôler le total calculé par Pennylane. */
function mcp_pl_expected_ttc(array $lines): float
{
    $rates = ['FR_200' => 0.20, 'FR_100' => 0.10, 'FR_55' => 0.055];
    $t = 0.0;
    foreach ($lines as $l) {
        $ht = round((float)$l['raw_currency_unit_price'] * (float)$l['quantity'], 2);
        $t += $ht + round($ht * $rates[$l['vat_rate']], 2);
    }
    return round($t, 2);
}

/** Corps de création d'une facture (brouillon forcé) ou d'un devis, après contrôle des arguments. */
function mcp_pl_document_body(array $args, bool $isInvoice): array
{
    $cust = $args['customer_id'] ?? null;
    if (!(is_int($cust) || (is_string($cust) && ctype_digit($cust))) || (int)$cust <= 0) throw new InvalidArgumentException('« customer_id » doit être l\'identifiant numérique d\'un client Pennylane.');
    $lines = mcp_pl_lines((array)($args['lines'] ?? []));
    $date = mcp_pl_date($args, 'date') ?? date('Y-m-d');
    $deadline = mcp_pl_date($args, 'deadline') ?? date('Y-m-d', strtotime($date.' +30 days'));
    if ($deadline < $date) throw new InvalidArgumentException('« deadline » ne peut pas précéder « date ».');
    $subject = mb_substr(trim((string)($args['subject'] ?? '')), 0, 250);

    $body = [
        'date' => $date, 'deadline' => $deadline, 'customer_id' => (int)$cust,
        'currency' => 'EUR', 'language' => 'fr_FR',
        'pdf_invoice_subject' => $subject, 'pdf_description' => mb_substr((string)($args['description'] ?? ''), 0, 5000),
        'external_reference' => 'EMAE-MCP-'.($isInvoice ? 'F' : 'D').'-'.date('YmdHis').'-'.bin2hex(random_bytes(2)),
        'invoice_lines' => $lines,
    ];
    if ($isInvoice) $body = ['draft' => true, 'label' => $subject] + $body;   // garde-fou : une facture créée ici reste un brouillon
    return $body;
}

/** Crée une facture en brouillon ou un devis. */
function mcp_pl_create(string $path, array $args, bool $isInvoice): array
{
    $body = mcp_pl_document_body($args, $isInvoice);
    $lines = $body['invoice_lines'];
    $cust = $body['customer_id'];
    $r = pennylane_request('POST', $path, [], $body);
    integration_log('mcp', ($isInvoice ? 'brouillon de facture' : 'devis').' créé par Claude : '.($r['ok'] ? 'ok' : 'échec'),
        ['client' => (int)$cust, 'lignes' => count($lines), 'pennylane' => $r['data']['id'] ?? null]);
    if (!$r['ok']) return ['ok' => false, 'data' => ['erreur' => $r['error']]];

    $d = (array)$r['data'];
    $expected = mcp_pl_expected_ttc($lines);
    $got = (float)($d['currency_amount'] ?? $d['amount'] ?? 0);
    $res = [
        'cree' => $isInvoice ? 'facture en brouillon (non finalisée, non envoyée)' : 'devis (non envoyé)',
        'id' => $d['id'] ?? null, 'number' => $d['invoice_number'] ?? $d['quote_number'] ?? null, 'status' => $d['status'] ?? null,
        'total_ttc_pennylane' => $d['currency_amount'] ?? $d['amount'] ?? null, 'total_ttc_attendu' => number_format($expected, 2, '.', ''),
        'pdf_url' => $d['public_file_url'] ?? null,
    ];
    if ($got > 0 && abs($got - $expected) >= 0.02) $res['attention'] = 'Le total calculé par Pennylane diffère du total attendu : vérifiez le document.';
    return ['ok' => true, 'data' => mcp_pl_wrap($r, $res)];
}

/* ═══════════════════════════════════════════════════════════
   Protocole MCP (JSON-RPC 2.0)
═══════════════════════════════════════════════════════════ */

/**
 * Traite un message JSON-RPC. Retour : la réponse à renvoyer, ou null pour une
 * notification (le point d'entrée répond alors 202 sans corps).
 */
function mcp_pennylane_handle(mixed $msg): ?array
{
    if (!is_array($msg) || ($msg['jsonrpc'] ?? null) !== '2.0' || !is_string($msg['method'] ?? null)) {
        return ['jsonrpc' => '2.0', 'id' => null, 'error' => ['code' => -32600, 'message' => 'Requête JSON-RPC invalide.']];
    }
    if (!array_key_exists('id', $msg)) return null;   // notification (notifications/initialized…)
    $id = $msg['id'];
    $params = is_array($msg['params'] ?? null) ? $msg['params'] : [];
    $ok = static fn(mixed $result) => ['jsonrpc' => '2.0', 'id' => $id, 'result' => $result];
    $err = static fn(int $code, string $m) => ['jsonrpc' => '2.0', 'id' => $id, 'error' => ['code' => $code, 'message' => $m]];

    switch ($msg['method']) {
        case 'initialize':
            $asked = (string)($params['protocolVersion'] ?? '');
            return $ok([
                'protocolVersion' => in_array($asked, MCP_PENNYLANE_VERSIONS, true) ? $asked : MCP_PENNYLANE_VERSIONS[0],
                'capabilities' => ['tools' => ['listChanged' => false]],
                'serverInfo' => ['name' => 'emae-pennylane', 'title' => 'Pennylane (EMAE)', 'version' => '1.0.0'],
                'instructions' => 'Accès à la comptabilité Pennylane de l\'entreprise. Lecture : factures, clients, produits, devis. '
                    .'Création : factures en brouillon et devis uniquement ; rien n\'est finalisé, envoyé ni supprimé. '
                    .'Avant toute création, récapitulez le document et attendez l\'accord explicite de l\'utilisateur. '
                    .'Montants en euros ; prix unitaires HT.'
                    .(pennylane_simulated() ? ' ATTENTION : EMAE est en mode SIMULATION, les données sont fictives.' : ''),
            ]);
        case 'ping':
            return $ok(new stdClass());
        case 'tools/list':
            return $ok(['tools' => mcp_pennylane_tools()]);
        case 'tools/call':
            $name = (string)($params['name'] ?? '');
            if (!in_array($name, array_column(mcp_pennylane_tools(), 'name'), true)) return $err(-32602, 'Outil inconnu : '.$name);
            $args = is_array($params['arguments'] ?? null) ? $params['arguments'] : [];
            integration_log('mcp', 'outil appelé : '.$name);
            return $ok(mcp_pennylane_call($name, $args));
        default:
            return $err(-32601, 'Méthode non prise en charge : '.$msg['method']);
    }
}
