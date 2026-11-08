<?php
declare(strict_types=1);
/**
 * 15. Contrats d'entretien : suivi des clients sous contrat et rappel automatique
 * avant chaque échéance (SMS ou e-mail au client, récapitulatif à l'entreprise).
 *
 * Une visite enregistrée repousse la prochaine échéance d'une période (12 mois
 * par défaut, 24 pour une pompe à chaleur si vous le choisissez).
 * Écran : admin/contracts.php. Rappels : automations.php.
 */

function contracts_table(): void
{
    static $done = false;
    if ($done) return;
    $done = true;
    try {
        db_execute("CREATE TABLE IF NOT EXISTS maintenance_contracts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            client_name VARCHAR(190) NOT NULL,
            phone VARCHAR(80) NULL,
            email VARCHAR(190) NULL,
            address VARCHAR(255) NULL,
            equipment VARCHAR(190) NOT NULL,
            period_months INT NOT NULL DEFAULT 12,
            price VARCHAR(60) NULL,
            start_date DATE NULL,
            last_visit DATE NULL,
            next_visit DATE NULL,
            reminded_for DATE NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'actif',
            notes TEXT NULL,
            quote_id INT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_next (status, next_visit)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (Throwable $e) {}
}

function contracts_remind_days(): int { return max(1, min(120, (int)global_setting('contracts_remind_days', '30'))); }

function contracts_list(string $filter = 'actif'): array
{
    contracts_table();
    try {
        return $filter === 'tous'
            ? db_fetch_all('SELECT * FROM maintenance_contracts ORDER BY (next_visit IS NULL), next_visit, id DESC')
            : db_fetch_all('SELECT * FROM maintenance_contracts WHERE status = ? ORDER BY (next_visit IS NULL), next_visit, id DESC', [$filter]);
    } catch (Throwable $e) { return []; }
}

function contract_get(int $id): ?array
{
    contracts_table();
    try { return db_fetch('SELECT * FROM maintenance_contracts WHERE id = ?', [$id]); } catch (Throwable $e) { return null; }
}

/** Date + n mois, sans déborder (31 janvier + 1 mois = 28 ou 29 février). */
function contract_add_months(string $ymd, int $months): string
{
    $d = DateTimeImmutable::createFromFormat('!Y-m-d', $ymd);
    if (!$d) return '';
    $target = $d->modify('first day of +'.$months.' month');
    $day = min((int)$d->format('j'), (int)$target->format('t'));
    return $target->setDate((int)$target->format('Y'), (int)$target->format('n'), $day)->format('Y-m-d');
}

/** Enregistre un contrat (création si id = 0). Retour : id, ou 0 si incomplet. */
function contract_save(array $p, int $id = 0): int
{
    $name  = mb_substr(trim((string)($p['client_name'] ?? '')), 0, 190);
    $equip = mb_substr(trim((string)($p['equipment'] ?? '')), 0, 190);
    if ($name === '' || $equip === '') return 0;
    $date = static fn(string $k): ?string => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($p[$k] ?? '')) ? (string)$p[$k] : null;
    $period = in_array((int)($p['period_months'] ?? 12), [6, 12, 24], true) ? (int)$p['period_months'] : 12;
    $last = $date('last_visit');
    $next = $date('next_visit') ?? ($last !== null ? contract_add_months($last, $period) : ($date('start_date') !== null ? contract_add_months((string)$date('start_date'), $period) : null));
    $vals = [
        $name, mb_substr(trim((string)($p['phone'] ?? '')), 0, 80) ?: null,
        filter_var(trim((string)($p['email'] ?? '')), FILTER_VALIDATE_EMAIL) ? trim((string)$p['email']) : null,
        mb_substr(trim((string)($p['address'] ?? '')), 0, 255) ?: null, $equip, $period,
        mb_substr(trim((string)($p['price'] ?? '')), 0, 60) ?: null, $date('start_date'), $last, $next,
        in_array($p['status'] ?? 'actif', ['actif', 'arrêté'], true) ? (string)($p['status'] ?? 'actif') : 'actif',
        mb_substr(trim((string)($p['notes'] ?? '')), 0, 2000) ?: null,
    ];
    contracts_table();
    try {
        if ($id > 0) {
            db_execute('UPDATE maintenance_contracts SET client_name=?, phone=?, email=?, address=?, equipment=?, period_months=?, price=?, start_date=?, last_visit=?, next_visit=?, status=?, notes=? WHERE id=?', array_merge($vals, [$id]));
            return $id;
        }
        db_execute('INSERT INTO maintenance_contracts (client_name, phone, email, address, equipment, period_months, price, start_date, last_visit, next_visit, status, notes, quote_id) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)',
            array_merge($vals, [!empty($p['quote_id']) ? (int)$p['quote_id'] : null]));
        return db_last_id();
    } catch (Throwable $e) { return 0; }
}

/** La visite d'entretien est faite : prochaine échéance dans une période. */
function contract_visit_done(int $id, string $ymd): bool
{
    $c = contract_get($id);
    if (!$c || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $ymd)) return false;
    try {
        db_execute('UPDATE maintenance_contracts SET last_visit = ?, next_visit = ?, reminded_for = NULL WHERE id = ?',
            [$ymd, contract_add_months($ymd, (int)$c['period_months']), $id]);
        return true;
    } catch (Throwable $e) { return false; }
}

/** Contrats dont l'échéance approche (ou est dépassée) et qui n'ont pas encore reçu leur rappel. */
function contracts_due_for_reminder(?string $today = null): array
{
    $today = $today ?? date('Y-m-d');
    $limit = date('Y-m-d', (int)strtotime($today.' +'.contracts_remind_days().' days'));
    contracts_table();
    try {
        return db_fetch_all("SELECT * FROM maintenance_contracts WHERE status = 'actif' AND next_visit IS NOT NULL AND next_visit <= ?
                             AND (reminded_for IS NULL OR reminded_for <> next_visit) ORDER BY next_visit", [$limit]);
    } catch (Throwable $e) { return []; }
}

function contract_reminder_text(array $c): string
{
    $tpl = global_setting('contracts_remind_text', '{entreprise} : bonjour {nom}, l\'entretien de votre {appareil} arrive à échéance le {date}. Appelez-nous au {telephone} pour fixer un rendez-vous.');
    return growth_fill($tpl, [
        'nom' => quote_first_name((string)$c['client_name']),
        'appareil' => mb_strtolower((string)$c['equipment'], 'UTF-8'),
        'date' => date('d/m/Y', strtotime((string)$c['next_visit'])),
    ]);
}
