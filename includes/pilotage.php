<?php
declare(strict_types=1);
/**
 * Pilotage : le site, Google Ads et la facturation sur une seule page.
 *
 * Le but est de répondre à une question simple : « chaque euro mis dans Google Ads,
 * combien de demandes, d'interventions et de chiffre d'affaires rapporte-t-il, et que
 * faut-il changer ? ». Les trois mondes ne se parlaient pas :
 *  - le site enregistre les demandes (table quotes) avec leur origine : une demande venue
 *    d'une page d'atterrissage Google Ads porte la source « landing_{métier}_{département} » ;
 *  - le dispatcher transforme une demande en intervention (interventions.quote_id), puis en
 *    facture (invoices.intervention_id), synchronisée avec Pennylane ;
 *  - les dépenses Google Ads arrivent par import (fichier exporté de Google Ads, ou envoi
 *    automatique depuis l'outil Ads du PC, voir api/pilotage.php) dans la table ads_daily.
 *
 * Organisation, comme includes/finance.php : pilotage_load() lit la base ; tous les calculs
 * sont « purs » (listes en entrée, tableau en sortie, sans base ni réseau) pour être
 * vérifiés par tests/test_pilotage.php.
 *
 * Attribution : une demande Google Ads est rattachée aux campagnes dont le métier et le
 * département correspondent (une campagne sans métier ou sans département les accepte tous).
 * Seules les campagnes les plus précises sont retenues ; si plusieurs restent, la demande
 * est partagée au prorata de leurs clics. C'est une estimation, affichée comme telle.
 *
 * Montants : HT, car la dépense Google Ads est HT ; « CA » = factures émises (validées,
 * envoyées ou payées) issues des demandes de la période, quelle que soit leur date.
 */

/* ─── Seuils des recommandations (en euros HT) ───────────── */
const PILOTAGE_DEPENSE_MIN   = 40.0;   // en dessous, trop tôt pour juger une campagne
const PILOTAGE_ROAS_BON      = 3.0;    // 3 € de CA par euro dépensé : à renforcer
const PILOTAGE_ROAS_MAUVAIS  = 1.0;    // moins d'un euro de CA par euro dépensé : à revoir
const PILOTAGE_JOURS_RETARD_IMPORT = 3;

/** Table des dépenses Google Ads, une ligne par jour et par campagne. */
function pilotage_tables(): void
{
    static $done = false;
    if ($done) return;
    $done = true;
    try {
        db_execute("CREATE TABLE IF NOT EXISTS ads_daily (
            id INT AUTO_INCREMENT PRIMARY KEY,
            stat_date DATE NOT NULL,
            campaign VARCHAR(190) NOT NULL,
            metier VARCHAR(40) NOT NULL DEFAULT '',
            dept VARCHAR(3) NOT NULL DEFAULT '',
            cost DECIMAL(12,2) NOT NULL DEFAULT 0,
            clicks INT NOT NULL DEFAULT 0,
            impressions INT NOT NULL DEFAULT 0,
            conversions DECIMAL(10,2) NOT NULL DEFAULT 0,
            imported_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_jour_campagne (stat_date, campaign),
            INDEX idx_date (stat_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (Throwable $e) {}
}

/* ─── Référentiels ────────────────────────────────────────── */

/** Départements des pages d'atterrissage : code => [slug, nom]. Même liste que index.php. */
function pilotage_depts(): array
{
    return [
        '39' => ['jura', 'Jura'], '25' => ['doubs', 'Doubs'], '21' => ['cote-dor', "Côte-d'Or"],
        '01' => ['ain', 'Ain'], '38' => ['isere', 'Isère'], '69' => ['rhone', 'Rhône'], '42' => ['loire', 'Loire'],
        '73' => ['savoie', 'Savoie'], '74' => ['haute-savoie', 'Haute-Savoie'], '26' => ['drome', 'Drôme'],
        '63' => ['puy-de-dome', 'Puy-de-Dôme'], '43' => ['haute-loire', 'Haute-Loire'], '03' => ['allier', 'Allier'],
        '07' => ['ardeche', 'Ardèche'], '15' => ['cantal', 'Cantal'], '75' => ['paris', 'Paris'],
        '77' => ['seine-et-marne', 'Seine-et-Marne'], '78' => ['yvelines', 'Yvelines'], '91' => ['essonne', 'Essonne'],
        '92' => ['hauts-de-seine', 'Hauts-de-Seine'], '93' => ['seine-saint-denis', 'Seine-Saint-Denis'],
        '94' => ['val-de-marne', 'Val-de-Marne'], '95' => ['val-d-oise', "Val-d'Oise"],
    ];
}

/** Libellé d'un métier (slug), d'un département (code) ou d'un segment. */
function pilotage_metier_label(string $m): string
{
    $cfg = intervention_category_config();
    $extra = ['vmc' => 'VMC', 'portail' => 'Portail / visiophone', 'automatisme' => 'Automatismes'];
    return $m === '' ? 'Tous métiers' : ($cfg[$m]['label'] ?? $extra[$m] ?? ucfirst($m));
}

function pilotage_dept_label(string $d): string
{
    if ($d === '') return 'Tous départements';
    $n = pilotage_depts()[$d][1] ?? '';
    return $n !== '' ? $n.' ('.$d.')' : 'Dépt '.$d;
}

/** Minuscules sans accents, tirets et apostrophes changés en espaces. */
function pilotage_plain(string $s): string
{
    $s = mb_strtolower($s, 'UTF-8');
    $s = strtr($s, ['à' => 'a', 'â' => 'a', 'ä' => 'a', 'ç' => 'c', 'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
                    'î' => 'i', 'ï' => 'i', 'ô' => 'o', 'ö' => 'o', 'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'ÿ' => 'y']);
    return trim((string)preg_replace('/[^a-z0-9]+/', ' ', $s));
}

/** Métier reconnu dans un texte libre (nom de campagne, type de service), '' sinon. */
function pilotage_metier_of(string $text): string
{
    $t = pilotage_plain($text);
    if ($t === '') return '';
    $motifs = service_trade_patterns() + ['electricite' => []];
    $motifs['electricite'][] = 'electricien';
    $motifs['plomberie'][] = 'plombier';
    $motifs['plomberie'][] = 'fuite';
    $motifs['plomberie'][] = 'debouch';
    $motifs['chauffage'][] = 'pac';
    // Motifs les plus longs d'abord, comme service_trade_of().
    $liste = [];
    foreach ($motifs as $m => $ps) foreach ($ps as $p) $liste[] = [$m, pilotage_plain($p)];
    usort($liste, static fn($a, $b) => strlen($b[1]) <=> strlen($a[1]));
    foreach ($liste as [$m, $p]) {
        if ($p === '') continue;
        // « pac » seul doit être un mot entier (sinon « espace » serait du chauffage).
        if (strlen($p) <= 3 ? preg_match('/\b'.preg_quote($p, '/').'\b/', $t) : str_contains($t, $p)) return $m;
    }
    return '';
}

/** Département reconnu dans un texte (nom ou code à 2 chiffres), '' sinon. */
function pilotage_dept_of(string $text): string
{
    $t = ' '.pilotage_plain($text).' ';
    $noms = [];
    foreach (pilotage_depts() as $code => [$slug, $nom]) $noms[] = [(string)$code, pilotage_plain($nom)];
    usort($noms, static fn($a, $b) => strlen($b[1]) <=> strlen($a[1]));   // « haute savoie » avant « savoie »
    foreach ($noms as [$code, $nom]) {
        if (str_contains($t, ' '.$nom.' ')) return $code;
    }
    if (preg_match_all('/\b(\d{2})\b/', $t, $m)) {
        foreach ($m[1] as $c) if (isset(pilotage_depts()[$c])) return $c;
    }
    return '';
}

/** Code département d'un code postal (« 25000 » → « 25 »). */
function pilotage_dept_of_cp(string $cp): string
{
    $cp = preg_replace('/\D/', '', $cp);
    return strlen($cp) === 5 ? substr($cp, 0, 2) : '';
}

/**
 * Origine et segment d'une demande du site.
 * Retour : ['ads' => bool, 'metier' => slug|'', 'dept' => code|''].
 */
function pilotage_lead_segment(array $q): array
{
    $src = (string)($q['source'] ?? '');
    if (preg_match('/^landing_([a-z]+)_([a-z-]+)$/', $src, $m)) {
        $dept = '';
        foreach (pilotage_depts() as $code => [$slug]) if ($slug === $m[2]) { $dept = (string)$code; break; }
        if ($dept === '') $dept = pilotage_dept_of_cp((string)($q['postal_code'] ?? ''));
        return ['ads' => true, 'metier' => $m[1], 'dept' => $dept];
    }
    $metier = pilotage_metier_of((string)($q['service_type'] ?? ''));
    if ($metier === '' && str_starts_with($src, 'service_')) $metier = substr($src, 8);
    return ['ads' => false, 'metier' => $metier, 'dept' => pilotage_dept_of_cp((string)($q['postal_code'] ?? ''))];
}

/* ─── Import des chiffres Google Ads ──────────────────────── */

/** Nombre saisi à la française ou à l'anglaise (« 1 234,56 », « 1,234.56 », « 12.5 € »). */
function pilotage_number(mixed $v): float
{
    if (is_int($v) || is_float($v)) return (float)$v;
    $s = preg_replace('/[^\d,.\-]/', '', str_replace(["\xc2\xa0", "\xe2\x80\xaf", ' '], '', (string)$v));
    if ($s === '' || $s === '-' || $s === null) return 0.0;
    $lastComma = strrpos($s, ','); $lastDot = strrpos($s, '.');
    if ($lastComma !== false && ($lastDot === false || $lastComma > $lastDot)) {
        $s = str_replace('.', '', $s); $s = str_replace(',', '.', $s);   // virgule décimale
    } else {
        $s = str_replace(',', '', $s);                                    // point décimal
    }
    return (float)$s;
}

/** Date reconnue (AAAA-MM-JJ, JJ/MM/AAAA, « 3 oct. 2026 », « Oct 3, 2026 ») en AAAA-MM-JJ, '' sinon. */
function pilotage_date(string $s): string
{
    $s = trim($s);
    if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $s, $m)) return checkdate((int)$m[2], (int)$m[3], (int)$m[1]) ? "$m[1]-$m[2]-$m[3]" : '';
    if (preg_match('#^(\d{1,2})/(\d{1,2})/(\d{4})$#', $s, $m)) return checkdate((int)$m[2], (int)$m[1], (int)$m[3]) ? sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]) : '';
    $mois = ['janv' => 1, 'jan' => 1, 'fevr' => 2, 'feb' => 2, 'fev' => 2, 'mars' => 3, 'mar' => 3, 'avr' => 4, 'apr' => 4, 'mai' => 5, 'may' => 5,
             'juin' => 6, 'jun' => 6, 'juil' => 7, 'jul' => 7, 'aout' => 8, 'aug' => 8, 'sept' => 9, 'sep' => 9, 'oct' => 10, 'nov' => 11, 'dec' => 12];
    $p = pilotage_plain($s);
    $m2 = [];
    if (preg_match('/^(\d{1,2}) ([a-z]+) (\d{4})$/', $p, $m) || preg_match('/^([a-z]+) (\d{1,2}) (\d{4})$/', $p, $m2)) {
        if (!empty($m2)) $m = [0, $m2[2], $m2[1], $m2[3]];
        foreach ($mois as $k => $n) if (str_starts_with($m[2], $k)) return checkdate($n, (int)$m[1], (int)$m[3]) ? sprintf('%04d-%02d-%02d', $m[3], $n, $m[1]) : '';
    }
    return '';
}

/**
 * Vérifie et complète des lignes reçues (fichier ou envoi automatique).
 * Entrée : [['date', 'campaign', 'cost', 'clicks', 'impressions', 'conversions', 'metier'?, 'dept'?], …]
 * Retour : ['rows' => lignes propres, 'errors' => messages lisibles].
 */
function pilotage_ads_normalize(array $rows, string $dateParDefaut = ''): array
{
    $out = []; $errors = [];
    foreach ($rows as $i => $r) {
        if (!is_array($r)) { $errors[] = 'Ligne '.($i + 1).' : format illisible.'; continue; }
        $camp = trim((string)($r['campaign'] ?? ''));
        if ($camp === '' || preg_match('/^(total|totaux)\b/i', pilotage_plain($camp))) continue;   // lignes de total des exports
        $date = pilotage_date((string)($r['date'] ?? '')) ?: $dateParDefaut;
        if ($date === '') { $errors[] = 'Ligne '.($i + 1).' (« '.mb_substr($camp, 0, 60).' ») : date manquante ou illisible.'; continue; }
        $metier = (string)($r['metier'] ?? '');
        $metier = preg_match('/^[a-z]{2,40}$/', $metier) ? $metier : pilotage_metier_of($camp);
        $deptIn = trim((string)($r['dept'] ?? ''));
        $dept = isset(pilotage_depts()[$deptIn]) ? $deptIn : ($deptIn !== '' ? pilotage_dept_of($deptIn) : '');
        if ($dept === '') $dept = pilotage_dept_of($camp);
        $key = $date.'|'.$camp;
        $prev = $out[$key] ?? null;
        $out[$key] = [
            'stat_date' => $date, 'campaign' => mb_substr($camp, 0, 190), 'metier' => $metier, 'dept' => $dept,
            'cost' => round(pilotage_number($r['cost'] ?? 0) + ($prev['cost'] ?? 0), 2),
            'clicks' => (int)round(pilotage_number($r['clicks'] ?? 0)) + ($prev['clicks'] ?? 0),
            'impressions' => (int)round(pilotage_number($r['impressions'] ?? 0)) + ($prev['impressions'] ?? 0),
            'conversions' => round(pilotage_number($r['conversions'] ?? 0) + ($prev['conversions'] ?? 0), 2),
        ];
    }
    return ['rows' => array_values($out), 'errors' => $errors];
}

/**
 * Lit un rapport exporté de Google Ads (CSV ou TSV, UTF-8 ou UTF-16, en français ou en anglais).
 * Les lignes de titre avant l'en-tête et les lignes de total sont ignorées.
 * Retour : ['rows' => lignes brutes pour pilotage_ads_normalize(), 'error' => ?string].
 */
function pilotage_ads_parse_csv(string $raw): array
{
    if (str_starts_with($raw, "\xFF\xFE") || str_starts_with($raw, "\xFE\xFF")) {
        $raw = (string)mb_convert_encoding(substr($raw, 2), 'UTF-8', str_starts_with($raw, "\xFF\xFE") ? 'UTF-16LE' : 'UTF-16BE');
    }
    $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw);
    if (!mb_check_encoding($raw, 'UTF-8')) $raw = (string)mb_convert_encoding($raw, 'UTF-8', 'Windows-1252');
    $lines = preg_split('/\r\n|\r|\n/', (string)$raw);
    $cols = [
        'date' => ['jour', 'day', 'date'],
        'campaign' => ['campagne', 'campaign', 'nom de la campagne', 'campaign name'],
        'cost' => ['cout', 'cost', 'depenses', 'montant depense'],
        'clicks' => ['clics', 'clicks'],
        'impressions' => ['impressions', 'impr'],
        'conversions' => ['conversions', 'conv'],
    ];
    foreach ($lines as $n => $line) {
        if (trim($line) === '') continue;
        $sep = substr_count($line, "\t") ? "\t" : (substr_count($line, ';') > substr_count($line, ',') ? ';' : ',');
        $head = array_map(static fn($h) => pilotage_plain((string)$h), str_getcsv($line, $sep, '"', ''));
        $map = [];
        foreach ($cols as $k => $names) {
            foreach ($head as $idx => $h) if (in_array($h, $names, true) && !isset($map[$k])) $map[$k] = $idx;
        }
        if (!isset($map['campaign'], $map['cost'])) continue;   // pas encore l'en-tête
        $rows = [];
        foreach (array_slice($lines, $n + 1) as $l) {
            if (trim($l) === '') continue;
            $c = str_getcsv($l, $sep, '"', '');
            $row = [];
            foreach ($map as $k => $idx) $row[$k] = $c[$idx] ?? '';
            $rows[] = $row;
        }
        return ['rows' => $rows, 'error' => null, 'has_date' => isset($map['date'])];
    }
    return ['rows' => [], 'error' => 'Colonnes « Campagne » et « Coût » introuvables : exportez le rapport Campagnes de Google Ads en CSV.', 'has_date' => false];
}

/** Enregistre (ou remplace) les lignes normalisées. Retour : nombre de lignes écrites. */
function pilotage_ads_store(array $rows): int
{
    pilotage_tables();
    $n = 0;
    foreach ($rows as $r) {
        try {
            db_execute('INSERT INTO ads_daily (stat_date, campaign, metier, dept, cost, clicks, impressions, conversions, imported_at)
                VALUES (?,?,?,?,?,?,?,?,NOW())
                ON DUPLICATE KEY UPDATE metier = VALUES(metier), dept = VALUES(dept), cost = VALUES(cost), clicks = VALUES(clicks),
                    impressions = VALUES(impressions), conversions = VALUES(conversions), imported_at = NOW()',
                [$r['stat_date'], $r['campaign'], $r['metier'], $r['dept'], $r['cost'], $r['clicks'], $r['impressions'], $r['conversions']]);
            $n++;
        } catch (Throwable $e) {}
    }
    if ($n > 0) set_integration_setting('pilotage_last_import', json_encode(['at' => date('c'), 'rows' => $n]));
    return $n;
}

/* ─── Lecture de la base ──────────────────────────────────── */

/** Tout ce que la page croise, pour la période [$from ; $to] (dates incluses). */
function pilotage_load(string $from, string $to): array
{
    pilotage_tables();
    review_tables();
    $q = static function (string $sql, array $p = []): array { try { return db_fetch_all($sql, $p); } catch (Throwable $e) { return []; } };
    $leads = $q("SELECT id, full_name, source, service_type, postal_code, city, status, created_at FROM quotes
                 WHERE created_at >= ? AND created_at < DATE_ADD(?, INTERVAL 1 DAY) AND COALESCE(archived, 0) = 0", [$from, $to]);
    if (!$leads) $leads = $q("SELECT id, full_name, source, service_type, postal_code, city, status, created_at FROM quotes
                 WHERE created_at >= ? AND created_at < DATE_ADD(?, INTERVAL 1 DAY)", [$from, $to]);   // base sans colonne archived
    return [
        'leads' => $leads,
        'interventions' => $q("SELECT id, quote_id, category, status FROM interventions WHERE quote_id IS NOT NULL"),
        'invoices' => $q("SELECT id, intervention_id, status, total_ht, total_ttc, remaining_ttc, due_date FROM invoices
                          WHERE intervention_id IS NOT NULL AND status <> 'annulee'"),
        'ads' => $q("SELECT stat_date, campaign, metier, dept, cost, clicks, impressions, conversions FROM ads_daily
                     WHERE stat_date >= ? AND stat_date <= ?", [$from, $to]),
        'ads_last_date' => (string)($q("SELECT MAX(stat_date) AS d FROM ads_daily")[0]['d'] ?? ''),
    ];
}

/* ─── Calculs ─────────────────────────────────────────────── */

function pilotage_div(float $a, float $b): ?float { return $b > 0 ? $a / $b : null; }

/**
 * Entonnoir complet : campagnes, segments (métier × département) et totaux.
 * $leads, $interventions, $invoices, $ads : lignes telles que pilotage_load() les renvoie.
 */
function pilotage_funnel(array $leads, array $interventions, array $invoices, array $ads): array
{
    // Facturé HT par intervention (factures émises seulement).
    $caByIv = [];
    foreach ($invoices as $f) {
        if (!in_array((string)$f['status'], ['validee', 'envoyee', 'payee'], true)) continue;
        $caByIv[(int)$f['intervention_id']] = ($caByIv[(int)$f['intervention_id']] ?? 0) + (float)$f['total_ht'];
    }
    $ivByQuote = [];
    foreach ($interventions as $iv) $ivByQuote[(int)$iv['quote_id']][] = (int)$iv['id'];

    // Campagnes de la période.
    $camps = [];
    foreach ($ads as $a) {
        $k = (string)$a['campaign'];
        $c = $camps[$k] ?? ['campaign' => $k, 'metier' => (string)$a['metier'], 'dept' => (string)$a['dept'],
                            'cost' => 0.0, 'clicks' => 0, 'impressions' => 0, 'conversions' => 0.0,
                            'leads' => 0.0, 'interventions' => 0.0, 'ca_ht' => 0.0];
        $c['cost'] += (float)$a['cost']; $c['clicks'] += (int)$a['clicks'];
        $c['impressions'] += (int)$a['impressions']; $c['conversions'] += (float)$a['conversions'];
        $camps[$k] = $c;
    }

    $segs = []; $tot = ['leads' => 0, 'leads_ads' => 0, 'interventions' => 0, 'interventions_ads' => 0, 'ca_ht' => 0.0, 'ca_ht_ads' => 0.0,
                        'cost' => 0.0, 'clicks' => 0, 'unattributed_leads' => 0];
    $seg = static function (array &$segs, string $m, string $d): string {
        $k = $m.'|'.$d;
        $segs[$k] ??= ['metier' => $m, 'dept' => $d, 'leads_ads' => 0, 'leads_site' => 0, 'interventions' => 0, 'ca_ht' => 0.0, 'cost' => 0.0, 'clicks' => 0];
        return $k;
    };
    foreach ($camps as $c) {
        $k = $seg($segs, $c['metier'], $c['dept']);
        $segs[$k]['cost'] += $c['cost']; $segs[$k]['clicks'] += $c['clicks'];
        $tot['cost'] += $c['cost']; $tot['clicks'] += $c['clicks'];
    }

    foreach ($leads as $l) {
        $s = pilotage_lead_segment($l);
        $ivs = $ivByQuote[(int)$l['id']] ?? [];
        $nIv = $ivs ? 1 : 0;                                   // une demande convertie compte une fois
        $ca = 0.0; foreach ($ivs as $id) $ca += $caByIv[$id] ?? 0.0;
        $k = $seg($segs, $s['metier'], $s['dept']);
        $segs[$k][$s['ads'] ? 'leads_ads' : 'leads_site']++;
        $segs[$k]['interventions'] += $nIv; $segs[$k]['ca_ht'] += $ca;
        $tot['leads']++; $tot['interventions'] += $nIv; $tot['ca_ht'] += $ca;
        if (!$s['ads']) continue;
        $tot['leads_ads']++; $tot['interventions_ads'] += $nIv; $tot['ca_ht_ads'] += $ca;

        // Rattachement aux campagnes les plus précises.
        $best = -1; $cands = [];
        foreach ($camps as $ck => $c) {
            if (($c['metier'] !== '' && $c['metier'] !== $s['metier']) || ($c['dept'] !== '' && $c['dept'] !== $s['dept'])) continue;
            $score = ($c['metier'] !== '' ? 1 : 0) + ($c['dept'] !== '' ? 1 : 0);
            if ($score > $best) { $best = $score; $cands = []; }
            if ($score === $best) $cands[] = $ck;
        }
        if (!$cands) { $tot['unattributed_leads']++; continue; }
        $clics = array_sum(array_map(static fn($ck) => $camps[$ck]['clicks'], $cands));
        foreach ($cands as $ck) {
            $part = $clics > 0 ? $camps[$ck]['clicks'] / $clics : 1 / count($cands);
            $camps[$ck]['leads'] += $part; $camps[$ck]['interventions'] += $nIv * $part; $camps[$ck]['ca_ht'] += $ca * $part;
        }
    }

    foreach ($camps as &$c) {
        $c['cpl'] = pilotage_div($c['cost'], $c['leads']);
        $c['roas'] = pilotage_div($c['ca_ht'], $c['cost']);
        foreach (['leads', 'interventions', 'ca_ht', 'cost', 'conversions'] as $f) $c[$f] = round($c[$f], 2);
    }
    unset($c);
    foreach ($segs as &$s) {
        $l = $s['leads_ads'] + $s['leads_site'];
        $s['leads'] = $l;
        $s['conversion'] = pilotage_div((float)$s['interventions'], (float)$l);
        $s['roas'] = pilotage_div($s['ca_ht'], $s['cost']);
        $s['ca_ht'] = round($s['ca_ht'], 2); $s['cost'] = round($s['cost'], 2);
    }
    unset($s);
    $camps = array_values($camps);
    usort($camps, static fn($a, $b) => $b['cost'] <=> $a['cost']);
    $segs = array_values($segs);
    usort($segs, static fn($a, $b) => [$b['ca_ht'], $b['leads']] <=> [$a['ca_ht'], $a['leads']]);
    $tot['ca_ht'] = round($tot['ca_ht'], 2); $tot['ca_ht_ads'] = round($tot['ca_ht_ads'], 2); $tot['cost'] = round($tot['cost'], 2);
    $tot['cpl_ads'] = pilotage_div($tot['cost'], (float)$tot['leads_ads']);
    $tot['roas'] = pilotage_div($tot['ca_ht_ads'], $tot['cost']);
    $tot['conversion'] = pilotage_div((float)$tot['interventions'], (float)$tot['leads']);
    return ['campaigns' => $camps, 'segments' => $segs, 'totals' => $tot];
}

/**
 * Verdict d'une campagne : 'trop_tot', 'a_couper', 'a_revoir', 'a_renforcer' ou 'ok'.
 * $cplMoyen : coût moyen d'une demande Google Ads sur la période (null si aucune).
 */
function pilotage_verdict(array $c, ?float $cplMoyen): string
{
    if ($c['cost'] < PILOTAGE_DEPENSE_MIN) return 'trop_tot';
    if ($c['leads'] < 0.5) return 'a_couper';
    if ($c['roas'] !== null && $c['roas'] >= PILOTAGE_ROAS_BON) return 'a_renforcer';
    if ($c['cost'] >= 2 * PILOTAGE_DEPENSE_MIN && $c['interventions'] < 0.5) return 'a_revoir';
    if ($c['cost'] >= 2 * PILOTAGE_DEPENSE_MIN && $c['roas'] !== null && $c['roas'] < PILOTAGE_ROAS_MAUVAIS && $c['interventions'] >= 1) return 'a_revoir';
    if ($cplMoyen !== null && $c['cpl'] !== null && $c['leads'] >= 2 && $c['cpl'] > 2 * $cplMoyen) return 'a_revoir';
    return 'ok';
}

function pilotage_verdict_label(string $v): array
{
    return [
        'trop_tot'    => ['Trop tôt', 'Moins de '.(int)PILOTAGE_DEPENSE_MIN.' € dépensés : attendre avant de juger.'],
        'a_couper'    => ['À mettre en pause', 'Des euros dépensés, aucune demande reçue.'],
        'a_revoir'    => ['À revoir', 'Des demandes mais peu ou pas de chiffre d\'affaires, ou un coût par demande trop élevé.'],
        'a_renforcer' => ['À renforcer', 'Rapporte au moins '.(int)PILOTAGE_ROAS_BON.' € de chiffre d\'affaires par euro dépensé.'],
        'ok'          => ['Correct', 'Rien à signaler.'],
    ][$v] ?? [$v, ''];
}

/**
 * Recommandations classées par urgence, chacune rattachée à l'outil où agir.
 * $ctx : ['today' => 'Y-m-d', 'ads_last_date' => ?string, 'pending_leads' => [demandes],
 *         'drafts' => ['count', 'total'], 'late' => ['count', 'total']]
 * Retour : [['niveau' => 1|2|3, 'outil' => 'Google Ads'|'Site'|'Pennylane'|'Dispatcher', 'titre', 'detail'], …]
 */
function pilotage_recommendations(array $funnel, array $ctx): array
{
    $r = [];
    $add = static function (int $niv, string $outil, string $titre, string $detail) use (&$r) {
        $r[] = ['niveau' => $niv, 'outil' => $outil, 'titre' => $titre, 'detail' => $detail];
    };
    $eur = static fn(float $v) => number_format($v, 0, ',', ' ').' €';
    $tot = $funnel['totals'];

    // 1. Demandes sans réponse : chaque heure compte, un client rappelé tard appelle un concurrent.
    $pend = $ctx['pending_leads'] ?? [];
    if ($pend) {
        $ads = count(array_filter($pend, static fn($q) => pilotage_lead_segment($q)['ads']));
        $add(1, 'Dispatcher', count($pend).' demande'.(count($pend) > 1 ? 's' : '').' sans réponse depuis plus de 24 h',
            ($ads ? $ads.($ads > 1 ? ' viennent' : ' vient').' de Google Ads (déjà payée'.($ads > 1 ? 's' : '').'). ' : '').'Rappeler aujourd\'hui : '.implode(', ', array_map(
                static fn($q) => trim((string)$q['full_name']).($q['city'] ? ' ('.$q['city'].')' : ''), array_slice($pend, 0, 5))).(count($pend) > 5 ? '…' : '').'.');
    }

    // 2. Chiffres Google Ads absents ou anciens : les conseils Ads seraient faux.
    $last = (string)($ctx['ads_last_date'] ?? '');
    if ($last === '') {
        $add(1, 'Google Ads', 'Aucun chiffre Google Ads importé', 'Importez le rapport des campagnes (bas de page) ou lancez l\'envoi automatique depuis « Claude EMAE Pilotage » : sans lui, impossible de calculer ce que rapporte chaque euro.');
    } elseif ((strtotime((string)$ctx['today']) - strtotime($last)) / 86400 > PILOTAGE_JOURS_RETARD_IMPORT) {
        $add(2, 'Google Ads', 'Chiffres Google Ads à mettre à jour', 'Dernier jour importé : '.date('d/m/Y', strtotime($last)).'. Les conseils sur les campagnes s\'appuient sur des chiffres anciens.');
    }

    // 3. Campagnes.
    foreach ($funnel['campaigns'] as $c) {
        $v = pilotage_verdict($c, $tot['cpl_ads']);
        $nom = '« '.$c['campaign'].' »';
        if ($v === 'a_couper') {
            $add(1, 'Google Ads', 'Mettre en pause '.$nom, $eur($c['cost']).' dépensés, '.$c['clicks'].' clics, aucune demande reçue sur le site. Vérifier les mots-clés et la page d\'arrivée avant de relancer.');
        } elseif ($v === 'a_revoir') {
            $add(2, 'Google Ads', 'Revoir '.$nom, $eur($c['cost']).' dépensés pour '.round($c['leads'], 1).' demande(s), '.round($c['interventions'], 1).' intervention(s) et '.$eur($c['ca_ht']).' HT facturés. Resserrer les mots-clés ou baisser les enchères.');
        } elseif ($v === 'a_renforcer') {
            $add(2, 'Google Ads', 'Augmenter le budget de '.$nom, 'Chaque euro rapporte '.number_format((float)$c['roas'], 1, ',', ' ').' € de chiffre d\'affaires ('.$eur($c['ca_ht']).' HT pour '.$eur($c['cost']).'). Monter le budget par paliers de 20 % et surveiller le coût par demande.');
        }
    }
    if ($tot['unattributed_leads'] > 0 && $funnel['campaigns']) {
        $add(3, 'Google Ads', $tot['unattributed_leads'].' demande(s) Google Ads sans campagne correspondante',
            'Le métier ou le département de la page d\'arrivée ne correspond à aucune campagne importée : nommer les campagnes avec le métier et le département (ex. « Électricité Doubs ») pour un calcul exact.');
    }

    // 4. Segments : là où le site rapporte sans publicité, ou convertit mal.
    foreach ($funnel['segments'] as $s) {
        if ($s['metier'] === '' || $s['dept'] === '') continue;
        $nom = pilotage_metier_label($s['metier']).' — '.pilotage_dept_label($s['dept']);
        if ($s['cost'] == 0.0 && $s['leads_site'] >= 3 && $s['interventions'] >= 2) {
            $add(3, 'Google Ads', 'Tester une campagne '.$nom, $s['leads_site'].' demandes arrivées sans publicité, '.$s['interventions'].' interventions, '.$eur($s['ca_ht']).' HT : la demande existe, une petite campagne pourrait l\'amplifier.');
        }
        if ($s['leads'] >= 5 && $s['conversion'] !== null && $s['conversion'] < 0.2) {
            $add(2, 'Site', 'Peu de demandes aboutissent : '.$nom, $s['leads'].' demandes, seulement '.$s['interventions'].' intervention(s). Vérifier que la page annonce clairement les tarifs et la zone, et que les rappels sont faits vite.');
        }
    }

    // 5. Facturation.
    if (($ctx['drafts']['count'] ?? 0) > 0) {
        $add(2, 'Pennylane', 'Finaliser '.$ctx['drafts']['count'].' facture'.($ctx['drafts']['count'] > 1 ? 's' : '').' en brouillon', $eur((float)$ctx['drafts']['total']).' TTC pas encore facturés : tant qu\'une facture est en brouillon, le délai de paiement ne court pas.');
    }
    if (($ctx['late']['count'] ?? 0) > 0) {
        $add(2, 'Pennylane', 'Relancer '.$ctx['late']['count'].' facture'.($ctx['late']['count'] > 1 ? 's' : '').' en retard', $eur((float)$ctx['late']['total']).' TTC échus. Les relances se font depuis Factures (ou Finances → Relances à faire).');
    }

    usort($r, static fn($a, $b) => $a['niveau'] <=> $b['niveau']);
    return $r;
}

/**
 * Résumé complet pour une période, prêt à afficher ou à envoyer en JSON
 * (page Pilotage et outil « Claude EMAE Pilotage » du PC).
 */
function pilotage_summary(int $jours, string $today): array
{
    $jours = max(1, min(366, $jours));
    $from = date('Y-m-d', strtotime($today.' -'.($jours - 1).' days'));
    $d = pilotage_load($from, $today);
    $funnel = pilotage_funnel($d['leads'], $d['interventions'], $d['invoices'], $d['ads']);

    $hasIv = [];
    foreach ($d['interventions'] as $iv) $hasIv[(int)$iv['quote_id']] = true;
    $limite = date('Y-m-d H:i:s', time() - 86400);
    $pending = array_values(array_filter($d['leads'], static fn($q) => (string)$q['status'] === 'nouveau'
        && !isset($hasIv[(int)$q['id']]) && (string)$q['created_at'] < $limite));

    $inv = finance_load();
    $k = finance_kpis($inv, $today);
    $drafts = array_filter($inv, static fn($i) => (string)$i['status'] === 'brouillon');
    $ctx = [
        'today' => $today, 'ads_last_date' => $d['ads_last_date'], 'pending_leads' => $pending,
        'drafts' => ['count' => count($drafts), 'total' => array_sum(array_map(static fn($i) => (float)$i['total_ttc'], $drafts))],
        'late' => ['count' => (int)$k['late_count'], 'total' => (float)$k['late_total']],
    ];
    return [
        'periode' => ['du' => $from, 'au' => $today, 'jours' => $jours],
        'funnel' => $funnel,
        'recommandations' => pilotage_recommendations($funnel, $ctx),
        'finances' => $k,
        'contexte' => ['ads_dernier_jour' => $d['ads_last_date'], 'demandes_en_attente' => count($pending),
                       'brouillons' => $ctx['drafts'], 'retards' => $ctx['late']],
    ];
}
