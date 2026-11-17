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

/* ─── Ajout de prestations à la grille ─────────────────────── */

/** Ajoute les lignes [métier, code, désignation, unité, prix HT] dont le code est absent. */
function price_grid_add_missing(array $rows): int
{
    $n = 0;
    foreach ($rows as [$cat, $code, $label, $unit, $price]) {
        try {
            if (db_fetch('SELECT id FROM price_grid WHERE code = ?', [$code])) continue;
            $max = (int)(db_fetch('SELECT COALESCE(MAX(sort_order),0) AS m FROM price_grid WHERE category = ?', [$cat])['m'] ?? 0);
            db_execute('INSERT INTO price_grid (category, code, label, unit, price_ht, is_percent, sort_order, updated_at) VALUES (?,?,?,?,?,?,?,NOW())',
                [$cat, $code, $label, $unit, (float)$price, $unit === 'pourcent' ? 1 : 0, $max + 10]);
            $n++;
        } catch (Throwable $e) {}
    }
    return $n;
}

/* ─── Import des produits Pennylane dans la grille ─────────────
 * Lecture seule côté Pennylane (GET /products, champs label, price_before_tax, vat_rate,
 * unit, reference — ceux déjà utilisés par pennylane_sync_products()). Le dispatcher coche
 * ce qu'il garde ; les produits dont le nom contient « VVV » sont décochés d'office. */

/** Taux de TVA à partir du code Pennylane (FR_200 → 20). Code inconnu : 20. */
function pennylane_vat_rate_from_code(string $code): float
{
    return match (strtoupper($code)) { 'FR_55' => 5.5, 'FR_100' => 10.0, 'FR_200' => 20.0, default => 20.0 };
}

/** Unité de la grille à partir du texte d'unité Pennylane. */
function price_unit_from_text(string $u): string
{
    $u = mb_strtolower(trim($u), 'UTF-8');
    return match (true) {
        $u === '' => 'forfait',
        str_contains($u, 'heure') || $u === 'h' || $u === 'hour' => 'heure',
        str_contains($u, 'kg') || str_contains($u, 'kilo') => 'kg',
        str_contains($u, 'unit') || str_contains($u, 'pièce') || str_contains($u, 'piece') || $u === 'u' || $u === 'pce' => 'unite',
        default => 'forfait',
    };
}

/** Métier deviné d'après la désignation ; « commun » si rien ne correspond. */
function price_guess_category(string $label): string
{
    $l = mb_strtolower($label, 'UTF-8');
    $map = [
        'debouchage'    => ['débouch', 'debouch', 'hydrocur', 'curage', 'furet', 'canalisation bouch', 'caméra'],
        'climatisation' => ['clim', 'split', 'frigorig', 'condensat', 'air-air', 'air/air'],
        'chauffage'     => ['chaudi', 'radiateur', 'pompe à chaleur', 'pac ', 'circulateur', 'vase d', 'thermostat d\'ambiance', 'désembou', 'ramonage', 'poêle', 'fioul', 'brûleur'],
        'plomberie'     => ['plomb', 'fuite', 'wc', 'chasse', 'robinet', 'mitigeur', 'chauffe-eau', 'cumulus', 'ballon', 'siphon', 'évier', 'lavabo', 'douche', 'groupe de sécurité', 'broyeur'],
        'electricite'   => ['électri', 'electri', 'disjonct', 'différentiel', 'tableau', 'prise', 'interrupteur', 'luminaire', 'éclairage', 'câbl', 'volet', 'interphone', 'visiophone', 'vmc'],
    ];
    foreach ($map as $cat => $words) foreach ($words as $w) if (str_contains($l, $w)) return $cat;
    return 'commun';
}

/**
 * Produit Pennylane mis au format de la grille, avec son état : déjà dans la grille
 * (même identifiant Pennylane ou même code) et écarté d'office (nom contenant « VVV »).
 * Fonction pure : $gridPids = identifiants Pennylane déjà liés, $gridCodes = codes existants.
 */
function pennylane_product_for_grid(array $p, array $gridPids, array $gridCodes): array
{
    $id = (string)($p['id'] ?? '');
    $label = trim((string)($p['label'] ?? ''));
    $ref = strtoupper(preg_replace('/[^A-Za-z0-9-]/', '', (string)($p['reference'] ?? '')));
    $code = mb_substr($ref !== '' ? $ref : 'PL-'.$id, 0, 40);
    $inGrid = in_array($id, $gridPids, true) || in_array($code, $gridCodes, true);
    return [
        'id' => $id, 'label' => $label, 'code' => $code,
        'price_ht' => round((float)str_replace(',', '.', (string)($p['price_before_tax'] ?? '0')), 2),
        'vat_rate' => pennylane_vat_rate_from_code((string)($p['vat_rate'] ?? '')),
        'unit' => price_unit_from_text((string)($p['unit'] ?? '')),
        'category' => price_guess_category($label),
        'vvv' => stripos($label, 'vvv') !== false,
        'in_grid' => $inGrid,
        'checked' => !$inGrid && stripos($label, 'vvv') === false && $label !== '',
    ];
}

/** Lit les produits Pennylane (lecture seule) et les prépare pour l'import. */
function pennylane_products_for_import(): array
{
    $gridPids = []; $gridCodes = [];
    foreach (price_grid_rows(false) as $g) {
        if (!empty($g['pennylane_product_id'])) $gridPids[] = (string)$g['pennylane_product_id'];
        $gridCodes[] = (string)$g['code'];
    }
    $out = [];
    $gen = pennylane_each('/products', ['limit' => 100], 30);
    foreach ($gen as $p) $out[] = pennylane_product_for_grid((array)$p, $gridPids, $gridCodes);
    $sum = $gen->getReturn();
    usort($out, static fn($a, $b) => [$a['in_grid'], $a['vvv'], $a['category'], $a['label']] <=> [$b['in_grid'], $b['vvv'], $b['category'], $b['label']]);
    return ['items' => $out, 'ok' => (bool)($sum['ok'] ?? true), 'error' => (string)($sum['error'] ?? ''), 'simulated' => pennylane_simulated()];
}

/** Ajoute à la grille les produits choisis, liés à leur produit Pennylane. */
function price_grid_import_products(array $products): int
{
    $n = 0;
    foreach ($products as $p) {
        try {
            if (db_fetch('SELECT id FROM price_grid WHERE code = ? OR (pennylane_product_id IS NOT NULL AND pennylane_product_id = ?)', [$p['code'], $p['id']])) continue;
            $max = (int)(db_fetch('SELECT COALESCE(MAX(sort_order),0) AS m FROM price_grid WHERE category = ?', [$p['category']])['m'] ?? 0);
            $price = number_format((float)$p['price_ht'], 2, '.', '');
            db_execute('INSERT INTO price_grid (category, code, label, unit, price_ht, is_percent, vat_rate, pennylane_product_id, pennylane_price, sort_order, updated_at) VALUES (?,?,?,?,?,0,?,?,?,?,NOW())',
                [$p['category'], $p['code'], mb_substr($p['label'], 0, 255), $p['unit'], (float)$p['price_ht'], (float)$p['vat_rate'], $p['id'], $price, $max + 10]);
            $n++;
        } catch (Throwable $e) {}
    }
    return $n;
}
