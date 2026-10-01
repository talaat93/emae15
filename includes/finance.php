<?php
declare(strict_types=1);
/**
 * Tableau de bord financier : calculs.
 *
 * Les données viennent du cache local des factures (table invoices), synchronisé avec
 * Pennylane toutes les 15 minutes (cron/pennylane_sync.php) : la page reste instantanée et
 * lisible même si Pennylane est indisponible (voir le commentaire de client_finance()).
 *
 * Organisation : finance_load() lit la base une fois ; toutes les autres fonctions sont des
 * calculs « purs » sur une liste de factures et une date du jour, sans base ni réseau, pour
 * pouvoir être vérifiés par tests/test_finances.php.
 *
 * Conventions :
 *  - montants TTC ; les brouillons (« à valider ») et les factures annulées ne comptent ni
 *    dans le facturé ni dans les impayés ;
 *  - « facturé » = date d'émission (issue_date) ; « encaissé » = date de paiement (paid_at)
 *    des factures payées. Un paiement partiel n'apparaît que dans le reste dû (Pennylane ne
 *    transmet pas la date de chaque versement dans le cache) ;
 *  - « en retard » = échéance dépassée et reste dû positif (même règle que invoice_is_late()).
 */

/** Factures utiles au tableau de bord, avec le métier, le technicien et le client. */
function finance_load(): array
{
    review_tables();
    try {
        return db_fetch_all("SELECT i.id, i.status, i.number, i.label, i.client_id, i.intervention_id, i.total_ht, i.total_ttc,
                i.remaining_ttc, i.issue_date, i.due_date, i.paid_at, i.created_at,
                iv.category AS iv_category, t.name AS tech_name, c.lastname, c.firstname
            FROM invoices i
            LEFT JOIN interventions iv ON iv.id = i.intervention_id
            LEFT JOIN technicians t ON t.id = iv.technician_id
            LEFT JOIN clients c ON c.id = i.client_id
            WHERE i.status <> 'annulee'");
    } catch (Throwable $e) { return []; }
}

/** Relances déjà envoyées : [facture_id => ['count' => n, 'last' => 'Y-m-d H:i:s']]. */
function finance_reminders_map(): array
{
    reminders_table();
    $map = [];
    try {
        foreach (db_fetch_all('SELECT invoice_id, COUNT(*) AS n, MAX(sent_at) AS last FROM invoice_reminders GROUP BY invoice_id') as $r) {
            $map[(int)$r['invoice_id']] = ['count' => (int)$r['n'], 'last' => (string)$r['last']];
        }
    } catch (Throwable $e) {}
    return $map;
}

/* ─── Petits outils ───────────────────────────────────────── */
function finance_is_issued(array $i): bool
{
    return in_array((string)$i['status'], ['validee', 'envoyee', 'payee'], true);
}

/** Reste dû d'une facture émise non payée (0 sinon). */
function finance_due(array $i): float
{
    if (!in_array((string)$i['status'], ['validee', 'envoyee'], true)) return 0.0;
    $r = $i['remaining_ttc'] ?? null;
    return max(0.0, (float)($r === null || $r === '' ? $i['total_ttc'] : $r));
}

/** Jours de retard à la date $today (0 si pas d'échéance ou pas encore échue). */
function finance_days_late(array $i, string $today): int
{
    if (empty($i['due_date'])) return 0;
    $d = (int)floor((strtotime($today) - strtotime((string)$i['due_date'])) / 86400);
    return max(0, $d);
}

function finance_is_late(array $i, string $today): bool
{
    return finance_due($i) > 0 && finance_days_late($i, $today) > 0;
}

/* ─── Calculs ─────────────────────────────────────────────── */

/** Chiffres clés du mois de $today. */
function finance_kpis(array $invoices, string $today): array
{
    $month = substr($today, 0, 7);
    $k = ['billed_month' => 0.0, 'paid_month' => 0.0, 'due_total' => 0.0, 'late_total' => 0.0, 'late_count' => 0, 'to_validate' => 0, 'billed_month_count' => 0];
    foreach ($invoices as $i) {
        if ($i['status'] === 'brouillon') { $k['to_validate']++; continue; }
        if (!finance_is_issued($i)) continue;
        if (!empty($i['issue_date']) && substr((string)$i['issue_date'], 0, 7) === $month) {
            $k['billed_month'] += (float)$i['total_ttc'];
            $k['billed_month_count']++;
        }
        if ($i['status'] === 'payee' && !empty($i['paid_at']) && substr((string)$i['paid_at'], 0, 7) === $month) $k['paid_month'] += (float)$i['total_ttc'];
        $due = finance_due($i);
        $k['due_total'] += $due;
        if (finance_is_late($i, $today)) { $k['late_total'] += $due; $k['late_count']++; }
    }
    foreach (['billed_month', 'paid_month', 'due_total', 'late_total'] as $f) $k[$f] = round($k[$f], 2);
    return $k;
}

/**
 * Balance âgée : reste dû réparti selon l'ancienneté du retard.
 * Tranches : non échu, 1 à 30 jours, 31 à 60 jours, plus de 60 jours.
 */
function finance_aging(array $invoices, string $today): array
{
    $b = [];
    foreach (['non_echu' => 'Pas encore échu', '1_30' => '1 à 30 jours', '31_60' => '31 à 60 jours', '60_plus' => 'Plus de 60 jours'] as $k => $l) {
        $b[$k] = ['label' => $l, 'amount' => 0.0, 'count' => 0];
    }
    foreach ($invoices as $i) {
        $due = finance_due($i);
        if ($due <= 0) continue;
        $d = finance_days_late($i, $today);
        $k = match (true) { $d <= 0 => 'non_echu', $d <= 30 => '1_30', $d <= 60 => '31_60', default => '60_plus' };
        $b[$k]['amount'] = round($b[$k]['amount'] + $due, 2);
        $b[$k]['count']++;
    }
    return $b;
}

/**
 * Relances à faire : factures en retard sans relance depuis $gapDays jours (ou jamais relancées),
 * avec le niveau suggéré (relances déjà faites + 1, plafonné à 3). Plus gros retards d'abord.
 * Aucune relance n'est envoyée ici : le dispatcher relit et décide.
 */
function finance_reminders_due(array $invoices, array $remindersMap, string $today, int $gapDays = 7): array
{
    $out = [];
    foreach ($invoices as $i) {
        if (!finance_is_late($i, $today)) continue;
        $r = $remindersMap[(int)$i['id']] ?? ['count' => 0, 'last' => ''];
        $since = $r['last'] !== '' ? (int)floor((strtotime($today) - strtotime(substr($r['last'], 0, 10))) / 86400) : null;
        if ($since !== null && $since < $gapDays) continue;
        $out[] = $i + ['days_late' => finance_days_late($i, $today), 'due' => finance_due($i), 'reminders' => $r['count'],
                       'last_reminder' => $r['last'] !== '' ? $r['last'] : null, 'suggested_level' => min(3, $r['count'] + 1)];
    }
    usort($out, static fn($a, $b) => [$b['days_late'], $b['due']] <=> [$a['days_late'], $a['due']]);
    return $out;
}

/** Plus gros débiteurs : reste dû total par client. */
function finance_top_debtors(array $invoices, string $today, int $limit = 10): array
{
    $c = [];
    foreach ($invoices as $i) {
        $due = finance_due($i);
        if ($due <= 0 || empty($i['client_id'])) continue;
        $id = (int)$i['client_id'];
        $c[$id] ??= ['client_id' => $id, 'name' => trim(($i['lastname'] ?? '').' '.($i['firstname'] ?? '')) ?: 'Client #'.$id,
                     'due' => 0.0, 'late' => 0.0, 'count' => 0, 'max_days_late' => 0];
        $c[$id]['due'] = round($c[$id]['due'] + $due, 2);
        $c[$id]['count']++;
        if (finance_is_late($i, $today)) {
            $c[$id]['late'] = round($c[$id]['late'] + $due, 2);
            $c[$id]['max_days_late'] = max($c[$id]['max_days_late'], finance_days_late($i, $today));
        }
    }
    usort($c, static fn($a, $b) => $b['due'] <=> $a['due']);
    return array_slice(array_values($c), 0, $limit);
}

/** Facturé et encaissé par mois, sur les $months derniers mois (le mois de $today inclus). */
function finance_monthly(array $invoices, string $today, int $months = 12): array
{
    $out = [];
    $start = strtotime(substr($today, 0, 7).'-01');
    for ($m = $months - 1; $m >= 0; $m--) {
        $key = date('Y-m', strtotime('-'.$m.' month', $start));
        $out[$key] = ['month' => $key, 'billed' => 0.0, 'paid' => 0.0];
    }
    foreach ($invoices as $i) {
        if (!finance_is_issued($i)) continue;
        $bk = !empty($i['issue_date']) ? substr((string)$i['issue_date'], 0, 7) : '';
        if (isset($out[$bk])) $out[$bk]['billed'] = round($out[$bk]['billed'] + (float)$i['total_ttc'], 2);
        if ($i['status'] === 'payee' && !empty($i['paid_at'])) {
            $pk = substr((string)$i['paid_at'], 0, 7);
            if (isset($out[$pk])) $out[$pk]['paid'] = round($out[$pk]['paid'] + (float)$i['total_ttc'], 2);
        }
    }
    return array_values($out);
}

/**
 * Délai moyen de paiement (jours entre émission et paiement) des factures payées
 * au cours des $months derniers mois. null s'il n'y a aucune facture mesurable.
 */
function finance_payment_delay(array $invoices, string $today, int $months = 12): ?array
{
    $from = date('Y-m-d', strtotime('-'.$months.' month', strtotime($today)));
    $days = [];
    foreach ($invoices as $i) {
        if ($i['status'] !== 'payee' || empty($i['issue_date']) || empty($i['paid_at'])) continue;
        if (substr((string)$i['paid_at'], 0, 10) < $from) continue;
        $days[] = max(0, (int)floor((strtotime(substr((string)$i['paid_at'], 0, 10)) - strtotime((string)$i['issue_date'])) / 86400));
    }
    if (!$days) return null;
    return ['average' => (int)round(array_sum($days) / count($days)), 'count' => count($days)];
}

/**
 * Facturé (HT et TTC) sur les $months derniers mois, réparti par $field :
 * 'iv_category' (métier) ou 'tech_name' (technicien). Les factures sans intervention liée
 * (importées de Pennylane) sont regroupées sous « Non rattaché ».
 */
function finance_breakdown(array $invoices, string $field, string $today, int $months = 12): array
{
    $from = date('Y-m-d', strtotime('-'.$months.' month', strtotime($today)));
    $labels = $field === 'iv_category' ? array_map(static fn($c) => $c['label'], intervention_category_config()) : [];
    $g = [];
    foreach ($invoices as $i) {
        if (!finance_is_issued($i) || empty($i['issue_date']) || (string)$i['issue_date'] < $from) continue;
        $raw = trim((string)($i[$field] ?? ''));
        $label = $raw === '' ? 'Non rattaché' : ($labels[$raw] ?? $raw);
        $g[$label] ??= ['label' => $label, 'ht' => 0.0, 'ttc' => 0.0, 'count' => 0];
        $g[$label]['ht'] = round($g[$label]['ht'] + (float)$i['total_ht'], 2);
        $g[$label]['ttc'] = round($g[$label]['ttc'] + (float)$i['total_ttc'], 2);
        $g[$label]['count']++;
    }
    usort($g, static fn($a, $b) => $b['ttc'] <=> $a['ttc']);
    return array_values($g);
}
