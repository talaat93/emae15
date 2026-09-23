<?php
declare(strict_types=1);

/**
 * Source unique des zones d'intervention.
 *
 * Avant : la même information était saisie à quatre endroits sans lien entre
 * eux — la table « zones » pour les sous-sites, le réglage
 * « zones_page_settings » pour la page Nos zones, « home_zone_cards » pour le
 * bas des pages Service et « contact_zone_tags » pour la page Contact.
 * Modifier une ville n'en changeait qu'un sur quatre.
 *
 * Désormais la table « zones » est la seule référence. Tout le site lit
 * intervention_zones(), et un seul écran d'administration l'alimente.
 */

/** Colonnes de présentation ajoutées à la table zones. */
function zones_presentation_columns(): array
{
    return ['depts', 'delay', 'color', 'intro'];
}

/** Découpe une liste saisie avec des « | » en tableau propre. */
function zone_split_list(?string $raw): array
{
    $raw = trim((string)$raw);
    if ($raw === '') return [];
    return array_values(array_filter(array_map('trim', explode('|', $raw)), fn($v) => $v !== ''));
}

/** Recompose une liste pour l'affichage dans un champ de saisie. */
function zone_join_list(array $items): string
{
    return implode(' | ', array_filter(array_map('trim', $items), fn($v) => $v !== ''));
}

/** Transforme un nom de zone en adresse : « Île-de-France » → « ile-de-france ». */
function zone_slugify(string $name): string
{
    $s = mb_strtolower(trim($name), 'UTF-8');
    $s = strtr($s, ['à'=>'a','á'=>'a','â'=>'a','ä'=>'a','ã'=>'a','é'=>'e','è'=>'e','ê'=>'e','ë'=>'e',
                    'í'=>'i','ì'=>'i','î'=>'i','ï'=>'i','ó'=>'o','ò'=>'o','ô'=>'o','ö'=>'o','õ'=>'o',
                    'ú'=>'u','ù'=>'u','û'=>'u','ü'=>'u','ç'=>'c','ñ'=>'n','œ'=>'oe','æ'=>'ae']);
    $s = (string)preg_replace('/[^a-z0-9]+/', '-', $s);
    return trim($s, '-');
}

/** Une adresse libre encore, en ajoutant -2, -3… si besoin. */
function zone_unique_slug(string $base, int $exceptId = 0): string
{
    $base = $base !== '' ? $base : 'zone';
    $slug = $base;
    for ($i = 2; $i < 100; $i++) {
        $found = get_zone_by_slug($slug);
        if (!$found || (int)$found['id'] === $exceptId) return $slug;
        $slug = $base.'-'.$i;
    }
    return $base.'-'.random_int(100, 999);
}

/** Couleur d'accent par défaut, dérivée du rang pour que les cartes se distinguent. */
function zone_default_color(int $index): string
{
    $palette = ['#1a7ab5', '#E8921A', '#4a9d5f', '#8b5cf6', '#d4536b', '#0f9b9b'];
    return $palette[$index % count($palette)];
}

/**
 * La liste unique.
 *
 * @param bool $onlyActive true = seulement les zones publiées (le cas du site).
 * @return array<int,array{id:int,slug:string,name:string,status:bool,color:string,
 *                         delay:string,intro:string,depts:array,cities:array,
 *                         postal_codes:string,sort_order:int}>
 */
function intervention_zones(bool $onlyActive = true, bool $refresh = false): array
{
    static $cache = [];
    if ($refresh) $cache = [];
    $ck = $onlyActive ? 'on' : 'all';
    if (isset($cache[$ck])) return $cache[$ck];

    try {
        $rows = db_fetch_all(
            'SELECT * FROM zones'.($onlyActive ? ' WHERE status = 1' : '').' ORDER BY sort_order ASC, id ASC'
        );
    } catch (Throwable $e) {
        return [];
    }

    $out = [];
    foreach (array_values($rows) as $i => $z) {
        $out[] = [
            'id'           => (int)($z['id'] ?? 0),
            'slug'         => (string)($z['slug'] ?? ''),
            'name'         => (string)($z['name'] ?? ''),
            'status'       => (bool)($z['status'] ?? 0),
            'color'        => trim((string)($z['color'] ?? '')) !== '' ? (string)$z['color'] : zone_default_color($i),
            'delay'        => trim((string)($z['delay'] ?? '')),
            'intro'        => trim((string)($z['intro'] ?? '')),
            'depts'        => zone_split_list($z['depts']  ?? ''),
            'cities'       => zone_split_list($z['cities'] ?? ''),
            'postal_codes' => trim((string)($z['postal_codes'] ?? '')),
            'sort_order'   => (int)($z['sort_order'] ?? 0),
        ];
    }
    return $cache[$ck] = $out;
}

/** Vide le cache : à appeler après toute écriture dans la table. */
function intervention_zones_flush(): void
{
    intervention_zones(true, true);
}

/**
 * Villes à afficher quand on veut un aperçu court et équilibré entre zones.
 *
 * @param int $perZone nombre maximum de villes reprises par zone
 * @param int $max     nombre total maximum de puces
 */
function intervention_cities(int $perZone = 4, int $max = 18): array
{
    $out = [];
    foreach (intervention_zones() as $z) {
        foreach (array_slice($z['cities'], 0, max(1, $perZone)) as $c) {
            if (!in_array($c, $out, true)) $out[] = $c;
            if (count($out) >= $max) return $out;
        }
    }
    return $out;
}

/** Noms des zones actives, pour les phrases du type « Île-de-France et Jura ». */
function intervention_zone_names(): array
{
    return array_values(array_filter(array_map(fn($z) => $z['name'], intervention_zones())));
}

/**
 * Les mêmes zones, mises en forme comme l'attendait l'ancien bloc « regions »
 * de la page Nos zones. Évite de réécrire le gabarit d'affichage.
 */
function intervention_regions(): array
{
    $out = [];
    foreach (intervention_zones() as $z) {
        $out[] = [
            'name'   => $z['name'],
            'slug'   => $z['slug'],
            'icon'   => '',
            'color'  => $z['color'],
            'delay'  => $z['delay'],
            'intro'  => $z['intro'],
            'depts'  => $z['depts'],
            'cities' => $z['cities'],
        ];
    }
    return $out;
}

/* ═══════════════════════════════════════════════════
   ÉCRITURE — utilisée par l'écran d'administration
═══════════════════════════════════════════════════ */

/** Met à jour les champs de présentation d'une zone. */
function zone_save_presentation(int $id, array $data): void
{
    db_execute(
        'UPDATE zones SET name = ?, color = ?, `delay` = ?, intro = ?, depts = ?, cities = ?,
                          postal_codes = ?, status = ?, sort_order = ? WHERE id = ?',
        [
            (string)$data['name'],
            (string)$data['color'],
            (string)$data['delay'],
            (string)$data['intro'],
            (string)$data['depts'],
            (string)$data['cities'],
            (string)$data['postal_codes'],
            (int)$data['status'],
            (int)$data['sort_order'],
            $id,
        ]
    );
}

/* ═══════════════════════════════════════════════════
   REPRISE DES ANCIENNES DONNÉES
═══════════════════════════════════════════════════ */

/**
 * Rapproche une région de l'ancien réglage d'une zone de la table, par nom.
 * La comparaison ignore casse, accents et ponctuation : « Île-de-France »,
 * « ile de france » et « Idf » saisis différemment doivent se retrouver.
 */
function zone_match_name(string $a, string $b): bool
{
    $n = static function (string $s): string {
        $s = mb_strtolower(trim($s), 'UTF-8');
        $s = strtr($s, ['à'=>'a','â'=>'a','ä'=>'a','é'=>'e','è'=>'e','ê'=>'e','ë'=>'e',
                        'î'=>'i','ï'=>'i','ô'=>'o','ö'=>'o','ù'=>'u','û'=>'u','ü'=>'u','ç'=>'c']);
        return (string)preg_replace('/[^a-z0-9]/', '', $s);
    };
    return $n($a) !== '' && $n($a) === $n($b);
}

/**
 * Importe dans la table les informations qui n'existaient que dans les anciens
 * réglages. N'écrase jamais une valeur déjà renseignée dans la table.
 *
 * @return int nombre de zones enrichies
 */
function zones_import_legacy(array $legacyRegions, array $legacyCards): int
{
    $zones = intervention_zones(false);
    if (!$zones) return 0;

    $touched = 0;
    foreach ($zones as $i => $z) {
        $patch = [];

        // 1. Les régions de l'ancienne page Nos zones : départements et délai.
        foreach ($legacyRegions as $reg) {
            if (!zone_match_name($z['name'], (string)($reg['name'] ?? ''))) continue;
            if (!$z['depts'] && !empty($reg['depts']))   $patch['depts'] = zone_join_list((array)$reg['depts']);
            if ($z['delay'] === '' && !empty($reg['delay'])) $patch['delay'] = (string)$reg['delay'];
            if ($z['color'] === zone_default_color($i) && !empty($reg['color'])) $patch['color'] = (string)$reg['color'];
            if (!$z['cities'] && !empty($reg['cities'])) $patch['cities'] = zone_join_list((array)$reg['cities']);
            break;
        }

        // 2. Les cartes du bas des pages Service : la phrase de présentation.
        foreach ($legacyCards as $card) {
            $title = trim(preg_replace('/^[^\p{L}]+/u', '', (string)($card['title'] ?? '')) ?? '');
            if (!zone_match_name($z['name'], $title)) continue;
            if ($z['intro'] === '' && !empty($card['text'])) $patch['intro'] = (string)$card['text'];
            break;
        }

        if (!$patch) continue;
        $sets = []; $args = [];
        foreach ($patch as $col => $val) { $sets[] = "`$col` = ?"; $args[] = $val; }
        $args[] = $z['id'];
        try {
            db_execute('UPDATE zones SET '.implode(', ', $sets).' WHERE id = ?', $args);
            $touched++;
        } catch (Throwable $e) { /* une zone récalcitrante ne bloque pas les autres */ }
    }
    return $touched;
}
