<?php
declare(strict_types=1);
/**
 * Vue client à 360° : factures et situation financière, badge « mauvais payeur »,
 * documents (devis, factures, rapports) et notes datées.
 */

function client_notes_table(): void
{
    static $done = false;
    if ($done) return;
    $done = true;
    try {
        db_execute("CREATE TABLE IF NOT EXISTS client_notes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            client_id INT NOT NULL,
            author_id INT NULL,
            author_name VARCHAR(120) NULL,
            body TEXT NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_client (client_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (Throwable $e) {}
}

function client_notes(int $clientId): array
{
    client_notes_table();
    try { return db_fetch_all('SELECT * FROM client_notes WHERE client_id = ? ORDER BY id DESC LIMIT 100', [$clientId]); } catch (Throwable $e) { return []; }
}

function client_add_note(int $clientId, string $body, array $actor): bool
{
    $body = trim($body);
    if ($body === '') return false;
    client_notes_table();
    db_execute('INSERT INTO client_notes (client_id, author_id, author_name, body) VALUES (?,?,?,?)', [$clientId, (int)$actor['id'], (string)$actor['name'], mb_substr($body, 0, 4000)]);
    return true;
}

function client_invoices(int $clientId): array
{
    review_tables();
    try { return db_fetch_all("SELECT * FROM invoices WHERE client_id = ? AND status <> 'annulee' ORDER BY COALESCE(issue_date, DATE(created_at)) DESC, id DESC", [$clientId]); }
    catch (Throwable $e) { return []; }
}

/**
 * Situation financière : facturé, encaissé, reste dû, retards.
 * Le badge « mauvais payeur » s'allume si une facture a plus de 30 jours de retard,
 * si deux factures sont en retard, ou si une facture reste impayée après 2 relances.
 */
function client_finance(int $clientId): array
{
    $inv = client_invoices($clientId);
    $f = ['invoices' => $inv, 'billed' => 0.0, 'paid' => 0.0, 'due' => 0.0, 'late_count' => 0, 'late_amount' => 0.0, 'max_late_days' => 0,
          'to_validate' => 0, 'bad_payer' => false, 'bad_reasons' => []];
    $remindersByInv = [];
    try {
        foreach (db_fetch_all('SELECT r.invoice_id, COUNT(*) AS n FROM invoice_reminders r JOIN invoices i ON i.id = r.invoice_id WHERE i.client_id = ? GROUP BY r.invoice_id', [$clientId]) as $r) {
            $remindersByInv[(int)$r['invoice_id']] = (int)$r['n'];
        }
    } catch (Throwable $e) {}
    foreach ($inv as $i) {
        if ($i['status'] === 'brouillon') { $f['to_validate']++; continue; }
        $f['billed'] += (float)$i['total_ttc'];
        if ($i['status'] === 'payee') { $f['paid'] += (float)$i['total_ttc']; continue; }
        $rest = (float)($i['remaining_ttc'] ?? $i['total_ttc']);
        $f['due'] += $rest;
        $f['paid'] += max(0, (float)$i['total_ttc'] - $rest);
        if (invoice_is_late($i)) {
            $days = invoice_days_late($i);
            $f['late_count']++;
            $f['late_amount'] += $rest;
            $f['max_late_days'] = max($f['max_late_days'], $days);
            if (($remindersByInv[(int)$i['id']] ?? 0) >= 2) $f['bad_reasons'][] = 'facture '.$i['number'].' impayée après '.$remindersByInv[(int)$i['id']].' relances';
        }
    }
    if ($f['max_late_days'] > 30) $f['bad_reasons'][] = 'retard de paiement de '.$f['max_late_days'].' jours';
    if ($f['late_count'] >= 2) $f['bad_reasons'][] = $f['late_count'].' factures en retard';
    $f['bad_reasons'] = array_values(array_unique($f['bad_reasons']));
    $f['bad_payer'] = (bool)$f['bad_reasons'];
    return $f;
}

/** Badge « mauvais payeur » (chaîne vide si le client paie normalement). */
function client_bad_payer_badge(?int $clientId, bool $withReason = true): string
{
    if (!$clientId) return '';
    static $cache = [];
    $f = $cache[$clientId] ??= client_finance($clientId);
    if (!$f['bad_payer']) return '';
    $title = 'Mauvais payeur : '.implode(', ', $f['bad_reasons']).' — reste dû '.money_fr($f['due']);
    return '<span title="'.htmlspecialchars($title, ENT_QUOTES, 'UTF-8').'" style="display:inline-block;padding:.12rem .55rem;border-radius:99px;font-size:.74rem;font-weight:700;color:#fff;background:#b91c1c;white-space:nowrap;">Mauvais payeur'
        .($withReason ? ' · '.htmlspecialchars(money_fr($f['due']), ENT_QUOTES, 'UTF-8').' dû' : '').'</span>';
}

/** Identifiants des clients ayant un reste dû (filtre « impayés » de la liste). */
function clients_with_unpaid(): array
{
    review_tables();
    try {
        return db_fetch_all("SELECT c.*, SUM(COALESCE(i.remaining_ttc, i.total_ttc)) AS due,
                SUM(i.due_date < CURDATE()) AS late
            FROM invoices i JOIN clients c ON c.id = i.client_id
            WHERE i.status IN ('envoyee','validee') AND COALESCE(i.remaining_ttc, i.total_ttc) > 0
            GROUP BY c.id ORDER BY due DESC LIMIT 300");
    } catch (Throwable $e) { return []; }
}

/** Recherche rapide : nom, téléphone, e-mail, ville… et aussi numéro de facture ou de devis. */
function clients_quick_search(string $q): array
{
    $rows = search_clients($q);
    $ids = array_column($rows, 'id');
    $like = '%'.trim($q).'%';
    try {
        $extra = db_fetch_all("SELECT DISTINCT c.* FROM clients c
            LEFT JOIN invoices i ON i.client_id = c.id
            LEFT JOIN devis d ON d.client_id = c.id
            LEFT JOIN interventions iv ON iv.client_id = c.id
            WHERE i.number LIKE ? OR d.number LIKE ? OR iv.ref LIKE ? LIMIT 20", [$like, $like, $like]);
        foreach ($extra as $c) if (!in_array($c['id'], $ids)) { $rows[] = $c; $ids[] = $c['id']; }
    } catch (Throwable $e) {}
    return $rows;
}
