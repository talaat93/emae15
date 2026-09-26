<?php
declare(strict_types=1);
/**
 * Grille tarifaire et calcul des montants.
 * Règle : tous les montants sont calculés ici, à partir de price_grid. Une proposition
 * de Claude ou une saisie du navigateur ne fournit que des codes et des quantités.
 */

function price_units(): array
{
    return ['forfait' => 'forfait', 'heure' => 'heure', 'unite' => 'unité', 'kg' => 'kg', 'pourcent' => '%'];
}

function price_categories(): array
{
    $cats = ['commun' => 'Commun à tous les métiers'];
    foreach (intervention_category_config() as $k => $c) $cats[$k] = $c['label'];
    return $cats;
}

/** Lignes de la grille, triées par métier puis ordre. $category filtre un métier (+ commun). */
function price_grid_rows(bool $activeOnly = true, string $category = ''): array
{
    $where = [];
    $params = [];
    if ($activeOnly) $where[] = 'active = 1';
    if ($category !== '') { $where[] = "(category = ? OR category = 'commun')"; $params[] = $category; }
    try {
        return db_fetch_all('SELECT * FROM price_grid'.($where ? ' WHERE '.implode(' AND ', $where) : '')
            .' ORDER BY FIELD(category, \'commun\') DESC, category, sort_order, code', $params);
    } catch (Throwable $e) { return []; }
}

/** Grille indexée par code (lignes actives uniquement). */
function price_grid_map(): array
{
    static $map = null;
    if ($map === null) {
        $map = [];
        foreach (price_grid_rows(true) as $r) $map[(string)$r['code']] = $r;
    }
    return $map;
}

/**
 * Taux de TVA par défaut d'une intervention : 10 % pour un particulier dont le logement
 * a plus de 2 ans (attestation simplifiée), 20 % sinon. Un taux choisi sur la fiche prime.
 */
function iv_vat_rate(array $iv, ?array $client = null): float
{
    if (isset($iv['vat_rate']) && $iv['vat_rate'] !== null && $iv['vat_rate'] !== '') return (float)$iv['vat_rate'];
    $type = (string)($client['client_type'] ?? $iv['client_type'] ?? '');
    return ($type === 'particulier' && !empty($iv['housing_over_2y'])) ? 10.0 : 20.0;
}

/**
 * Calcule un devis ou une facture.
 * $lines : [['code' => 'ELEC-DISJ', 'qty' => 2], ['label' => 'Pièce spéciale', 'qty' => 1, 'unit_price_ht' => 35.5, 'free' => true], …]
 *   - une ligne de la grille ne prend que code + quantité : le prix vient toujours de price_grid ;
 *   - une ligne libre (matériel non listé) porte sa désignation et son prix HT, et est signalée ;
 *   - les majorations en pourcentage s'appliquent au total des autres lignes.
 * Retour : ['lines' => [...], 'total_ht', 'total_tva', 'total_ttc', 'vat_rate', 'warnings' => [...], 'has_free' => bool]
 */
function pricing_compute(array $lines, float $vatRate): array
{
    $map = price_grid_map();
    $out = [];
    $warnings = [];
    $percent = [];
    $base = 0.0;
    $hasFree = false;
    foreach ($lines as $l) {
        $qty = round((float)str_replace(',', '.', (string)($l['qty'] ?? 1)), 2);
        if ($qty <= 0) continue;
        $code = strtoupper(trim((string)($l['code'] ?? '')));
        if ($code !== '') {
            if (!isset($map[$code])) { $warnings[] = 'Code inconnu ou désactivé ignoré : '.$code; continue; }
            $g = $map[$code];
            if ((int)$g['is_percent'] === 1) { $percent[] = ['g' => $g, 'qty' => $qty]; continue; }
            $unit = round((float)$g['price_ht'], 2);
            $total = round($unit * $qty, 2);
            $out[] = ['code' => $code, 'label' => (string)$g['label'], 'unit' => (string)$g['unit'], 'qty' => $qty,
                      'unit_price_ht' => $unit, 'total_ht' => $total, 'free' => false, 'pennylane_product_id' => $g['pennylane_product_id'] ?? null];
            $base += $total;
            continue;
        }
        $label = trim((string)($l['label'] ?? ''));
        $unit = round((float)str_replace(',', '.', (string)($l['unit_price_ht'] ?? 0)), 2);
        if ($label === '' || $unit < 0) { $warnings[] = 'Ligne libre incomplète ignorée.'; continue; }
        $total = round($unit * $qty, 2);
        $out[] = ['code' => '', 'label' => mb_substr($label, 0, 200), 'unit' => 'unite', 'qty' => $qty,
                  'unit_price_ht' => $unit, 'total_ht' => $total, 'free' => true, 'pennylane_product_id' => null];
        $base += $total;
        $hasFree = true;
    }
    // Majorations : un pourcentage du total des prestations et du matériel.
    foreach ($percent as $p) {
        $rate = (float)$p['g']['price_ht'];
        $total = round($base * $rate / 100, 2);
        $out[] = ['code' => (string)$p['g']['code'], 'label' => (string)$p['g']['label'].' (+'.rtrim(rtrim(number_format($rate, 2, ',', ''), '0'), ',').' %)',
                  'unit' => 'pourcent', 'qty' => 1, 'unit_price_ht' => $total, 'total_ht' => $total, 'free' => false,
                  'pennylane_product_id' => $p['g']['pennylane_product_id'] ?? null];
    }
    $totalHt = round(array_sum(array_column($out, 'total_ht')), 2);
    $vatRate = in_array($vatRate, [5.5, 10.0, 20.0], true) ? $vatRate : 20.0;
    $tva = round($totalHt * $vatRate / 100, 2);
    return ['lines' => $out, 'total_ht' => $totalHt, 'total_tva' => $tva, 'total_ttc' => round($totalHt + $tva, 2),
            'vat_rate' => $vatRate, 'warnings' => $warnings, 'has_free' => $hasFree];
}

/** Grille en JSON pour les calculs en direct côté navigateur (affichage seulement). */
function price_grid_public(string $category = ''): array
{
    return array_map(static fn($r) => [
        'code' => (string)$r['code'], 'label' => (string)$r['label'], 'unit' => (string)$r['unit'],
        'price' => (float)$r['price_ht'], 'percent' => (int)$r['is_percent'] === 1, 'category' => (string)$r['category'],
    ], price_grid_rows(true, $category));
}

/**
 * Lignes chiffrables d'un rapport technicien : prestations de la grille (code + quantité)
 * et matériel hors grille avec un prix saisi (lignes libres, signalées pour contrôle).
 */
function tech_report_lines(array $iv): array
{
    $lines = [];
    foreach ((array)json_decode((string)($iv['tech_lines'] ?? '[]'), true) as $l) {
        if (is_array($l) && !empty($l['code'])) $lines[] = ['code' => (string)$l['code'], 'qty' => (float)($l['qty'] ?? 1)];
    }
    foreach ((array)json_decode((string)($iv['tech_materials_used'] ?? '[]'), true) as $m) {
        $price = is_array($m) ? (float)str_replace(',', '.', (string)($m['price'] ?? '')) : 0.0;
        if ($price > 0 && trim((string)($m['name'] ?? '')) !== '') {
            $lines[] = ['label' => trim((string)$m['name']), 'qty' => (float)str_replace(',', '.', (string)($m['qty'] ?? 1)) ?: 1, 'unit_price_ht' => $price];
        }
    }
    return $lines;
}

/** Chiffrage d'un rapport technicien, toujours recalculé à partir de la grille. */
function tech_report_pricing(array $iv, ?array $client = null): array
{
    if ($client === null && !empty($iv['client_id'])) $client = get_client_by_id((int)$iv['client_id']);
    return pricing_compute(tech_report_lines($iv), iv_vat_rate($iv, $client));
}
