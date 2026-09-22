<?php
declare(strict_types=1);

/**
 * Recherche et remplacement d'un mot dans tout le contenu du site.
 *
 * Trois sources sont balayées, car un texte visible n'est pas forcément
 * enregistré en base : il peut encore être le texte d'origine du code.
 *   - cat : les champs du catalogue, valeur effectivement affichée
 *   - set : les réglages réellement enregistrés, y compris ceux propres
 *           à une zone et les blocs JSON
 *   - row : les pages, réalisations et avis
 *
 * Rien n'est jamais modifié sans aperçu préalable, et le dernier
 * remplacement peut être annulé.
 */

/** Colonnes de contenu balayées, par table. Sert aussi de liste blanche à l'écriture. */
function bulk_row_columns(): array
{
    return [
        'pages'        => ['title','excerpt','content_html','meta_title','meta_description'],
        'realisations' => ['title','description','city','service_type'],
        'reviews'      => ['author_name','content','city','service_type'],
    ];
}

/** Réglages à ne jamais exposer ni modifier ici. */
function bulk_is_sensitive(string $key): bool
{
    return (bool)preg_match('/pass|secret|token|api|ovh_|_key$|csrf/i', $key);
}

/** Réglages hors catalogue qu'il faut tout de même pouvoir corriger. */
function bulk_extra_settings(): array
{
    return [
        'company_regions' => ['Identité — Régions couvertes', company_regions()],
        'company_address' => ['Identité — Adresse',            company_address()],
    ];
}

function bulk_scope_label(string $key): array
{
    if (preg_match('/^z:([a-z0-9-]+):(.+)$/', $key, $m)) {
        $z = get_zone_by_slug($m[1]);
        return ['📍 '.($z['name'] ?? $m[1]), $m[2]];
    }
    return ['🌐 Site global', $key];
}

function bulk_count(string $haystack, string $needle): int
{
    return $needle === '' ? 0 : mb_substr_count(mb_strtolower($haystack), mb_strtolower($needle));
}

function bulk_replace_text(string $subject, string $search, string $replace): string
{
    if ($search === '') return $subject;
    return preg_replace('/'.preg_quote($search, '/').'/ui', str_replace('$', '\$', $replace), $subject) ?? $subject;
}

/**
 * Cherche le terme partout. Retourne une liste d'occurrences décrites,
 * chacune identifiée par une référence stable réutilisable à l'écriture.
 */
function bulk_scan(string $term): array
{
    $term = trim($term);
    if (mb_strlen($term) < 2) return [];

    return zone_ctx_id() > 0 ? bulk_scan_zone($term) : bulk_scan_global($term);
}

/**
 * Recherche depuis une zone : on balaie ce que la zone AFFICHE, qu'il lui
 * soit propre ou hérité du site global. Remplacer un texte hérité crée sa
 * version propre à la zone, sans toucher au global ni aux autres zones.
 */
function bulk_scan_zone(string $term): array
{
    $slug  = zone_ctx_slug();
    $nom   = '📍 '.zone_ctx_name();
    $hits  = [];
    $vus   = [];

    foreach (admin_page_catalog() as $pageId => $page) {
        foreach ($page['sections'] as $s) {
            foreach ($s['fields'] as $f) {
                if (bulk_is_sensitive($f['key'])) continue;
                if (!empty($f['json']) && isset(settings_cache()['z:'.$slug.':'.$f['json'][0]])) continue;
                $vus[$f['key']] = true;
                if (!empty($f['json'])) $vus[$f['json'][0]] = true;
                $val = admin_field_shown($f);
                if (bulk_count($val, $term) === 0) continue;
                $hits[] = [
                    'ref'   => 'cat:'.$pageId.':'.$f['key'],
                    'scope' => $nom,
                    'label' => $page['label'].' · '.$s['label'].' — '.$f['label'],
                    'value' => $val,
                    'n'     => bulk_count($val, $term),
                ];
            }
        }
    }

    foreach (bulk_extra_settings() as $key => [$label, $val]) {
        $vus[$key] = true;
        if (bulk_count($val, $term) === 0) continue;
        $hits[] = ['ref'=>'set:z:'.$slug.':'.$key, 'scope'=>$nom, 'label'=>$label,
                   'value'=>$val, 'n'=>bulk_count($val, $term)];
    }

    // Réglages hors catalogue : valeur propre à la zone si elle existe, sinon celle du global.
    $cache = settings_cache();
    $bases = [];
    foreach ($cache as $k => $v) {
        if (bulk_is_sensitive($k)) continue;
        if (preg_match('/^z:([a-z0-9-]+):(.+)$/', $k, $m)) {
            if ($m[1] === $slug) $bases[$m[2]] = $v;       // version de la zone
        } elseif (!isset($bases[$k])) {
            $bases[$k] = $v;                                // version globale, héritée
        }
    }
    foreach ($bases as $bare => $val) {
        if ($val === '' || isset($vus[$bare]) || bulk_count($val, $term) === 0) continue;
        $connu = admin_inline_keys()[$bare] ?? null;
        $hits[] = ['ref'=>'set:z:'.$slug.':'.$bare, 'scope'=>$nom,
                   'label'=>$connu['label'] ?? ('Réglage « '.$bare.' »'),
                   'value'=>$val, 'n'=>bulk_count($val, $term)];
    }

    $hits = array_merge($hits, bulk_scan_rows($term, zone_ctx_id()));
    return bulk_decorate($hits);
}

/** Recherche depuis le site global : toutes les portées, chacune étiquetée. */
function bulk_scan_global(string $term): array
{
    $hits = [];
    $vus  = [];

    foreach (admin_page_catalog() as $pageId => $page) {
        foreach ($page['sections'] as $s) {
            foreach ($s['fields'] as $f) {
                if (bulk_is_sensitive($f['key'])) continue;
                if (!empty($f['json']) && isset(settings_cache()[$f['json'][0]])) continue;
                $val = admin_field_shown($f);
                $vus[$f['key']] = true;
                if (bulk_count($val, $term) === 0) continue;
                $hits[] = [
                    'ref'   => 'cat:'.$pageId.':'.$f['key'],
                    'scope' => '🌐 Site global',
                    'label' => $page['label'].' · '.$s['label'].' — '.$f['label'],
                    'value' => $val,
                    'n'     => bulk_count($val, $term),
                ];
            }
        }
    }
    foreach (bulk_extra_settings() as $key => [$label, $val]) {
        $vus[$key] = true;
        if (bulk_count($val, $term) === 0) continue;
        $hits[] = ['ref'=>'set:'.$key, 'scope'=>'🌐 Site global', 'label'=>$label,
                   'value'=>$val, 'n'=>bulk_count($val, $term)];
    }

    try {
        foreach (db_fetch_all('SELECT setting_key, setting_value FROM settings') as $row) {
            $key = (string)$row['setting_key'];
            $val = (string)($row['setting_value'] ?? '');
            if ($val === '' || bulk_is_sensitive($key)) continue;
            [$scope, $bare] = bulk_scope_label($key);
            if ($scope === '🌐 Site global' && isset($vus[$bare])) continue;
            if (bulk_count($val, $term) === 0) continue;
            $connu = admin_inline_keys()[$bare] ?? null;
            $hits[] = ['ref'=>'set:'.$key, 'scope'=>$scope,
                       'label'=>$connu['label'] ?? ('Réglage « '.$bare.' »'),
                       'value'=>$val, 'n'=>bulk_count($val, $term)];
        }
    } catch (Throwable $e) {}

    $hits = array_merge($hits, bulk_scan_rows($term, 0));
    return bulk_decorate($hits);
}

/** Contenus : pages, réalisations, avis. Filtrés sur la zone le cas échéant. */
function bulk_scan_rows(string $term, int $zoneId): array
{
    $hits = [];
    foreach (bulk_row_columns() as $table => $cols) {
        try { $rows = db_fetch_all('SELECT * FROM '.$table); }
        catch (Throwable $e) { continue; }
        foreach ($rows as $r) {
            if ($zoneId > 0 && ($r['zone_id'] ?? null) !== null && (int)$r['zone_id'] !== $zoneId) continue;
            $nom = (string)($r['title'] ?? $r['author_name'] ?? ('n°'.(int)($r['id'] ?? 0)));
            foreach ($cols as $c) {
                $val = (string)($r[$c] ?? '');
                if ($val === '' || bulk_count($val, $term) === 0) continue;
                $hits[] = [
                    'ref'   => 'row:'.$table.':'.(int)$r['id'].':'.$c,
                    'scope' => '🗂 Contenu',
                    'label' => bulk_table_label($table).' « '.mb_substr($nom, 0, 40).' » — '.$c,
                    'value' => $val,
                    'n'     => bulk_count($val, $term),
                ];
            }
        }
    }
    return $hits;
}

/** Ajoute le lien de modification et marque ce qui relève de la portée courante. */
function bulk_decorate(array $hits): array
{
    $portee = zone_ctx_slug();
    foreach ($hits as &$h) {
        $h['edit'] = bulk_edit_url($h['ref']);
        // En zone, tout ce qui est listé relève de la zone : tout est actionnable.
        $h['dans_portee'] = $portee !== '' ? true : (bulk_ref_scope($h['ref']) === '');
    }
    unset($h);
    return $hits;
}

function bulk_table_label(string $t): string
{
    return ['pages'=>'Page','realisations'=>'Réalisation','reviews'=>'Avis'][$t] ?? $t;
}

/**
 * Portée d'une occurrence : '' pour le site global et les contenus,
 * sinon le slug de la zone à laquelle elle appartient.
 */
function bulk_ref_scope(string $ref): string
{
    if (str_starts_with($ref, 'set:z:') && preg_match('/^set:z:([a-z0-9-]+):/', $ref, $m)) return $m[1];
    return '';
}

/** Index clé de réglage → page et section du catalogue, pour les liens de modification. */
function bulk_catalog_index(): array
{
    static $idx = null;
    if ($idx !== null) return $idx;
    $idx = [];
    foreach (admin_page_catalog() as $pageId => $page) {
        foreach ($page['sections'] as $s) {
            foreach ($s['fields'] as $f) {
                $idx[$f['key']] = ['page' => $pageId, 'section' => $s['id']];
                if (!empty($f['json'])) $idx[$f['json'][0]] = ['page' => $pageId, 'section' => $s['id']];
            }
        }
    }
    return $idx;
}

/**
 * Lien menant droit au champ à modifier. Pour une surcharge de zone, il
 * bascule aussi l'administration sur cette zone, sinon on éditerait le global.
 */
function bulk_edit_url(string $ref): ?string
{
    $p = explode(':', $ref, 4);

    if ($p[0] === 'row' && count($p) === 4) {
        return match ($p[1]) {
            'pages'        => url_for('admin/page_edit.php?id='.(int)$p[2]),
            'realisations' => url_for('admin/realisations.php'),
            'reviews'      => url_for('admin/reviews.php'),
            default        => null,
        };
    }

    $zoneId = 0;
    if ($p[0] === 'cat' && count($p) === 3) {
        $field = $p[2];
        $loc   = ['page' => $p[1], 'section' => bulk_catalog_index()[$field]['section'] ?? ''];
    } elseif ($p[0] === 'set') {
        $key = substr($ref, 4);
        if (preg_match('/^z:([a-z0-9-]+):(.+)$/', $key, $m)) {
            $zoneId = (int)(get_zone_by_slug($m[1])['id'] ?? 0);
            $key    = $m[2];
        }
        $loc   = bulk_catalog_index()[$key] ?? null;
        $field = $key;
    } else {
        return null;
    }
    if (!$loc || ($loc['page'] ?? '') === '') return null;

    return url_for('admin/page_content.php?p='.$loc['page']
        .'&field='.rawurlencode($field)
        .($zoneId > 0 ? '&admin_zone='.$zoneId : '')
        .'#s-'.$loc['section']);
}

/** Valeur actuelle d'une occurrence, à partir de sa référence. */
function bulk_read(string $ref): ?string
{
    $p = explode(':', $ref, 4);
    if ($p[0] === 'cat' && count($p) === 3) {
        $f = admin_catalog_fields($p[1])[$p[2]] ?? null;
        if (!$f) return null;
        // Lu dans la portée courante : en zone, on voit ce que la zone affiche.
        return admin_field_is_list($f)
            ? (string)json_encode(admin_list_rows($f), JSON_UNESCAPED_UNICODE)
            : admin_field_shown($f);
    }
    if ($p[0] === 'set' && count($p) >= 2) {
        $key = substr($ref, 4);
        if (bulk_is_sensitive($key)) return null;
        [, $bare] = bulk_scope_label($key);
        $cache = settings_cache();
        $raw   = $cache[$key] ?? '';
        // Une clé de zone encore vide hérite : on lit la valeur globale, que
        // le remplacement transformera en version propre à la zone.
        if ($raw === '' && $key !== $bare) $raw = $cache[$bare] ?? '';
        if ($raw === '') { $extra = bulk_extra_settings(); return $extra[$bare][1] ?? null; }
        return $raw;
    }
    if ($p[0] === 'row' && count($p) === 4) {
        [$t, $id, $c] = [$p[1], (int)$p[2], $p[3]];
        $cols = bulk_row_columns();
        if (!isset($cols[$t]) || !in_array($c, $cols[$t], true)) return null;
        $row = db_fetch('SELECT '.$c.' FROM '.$t.' WHERE id = ?', [$id]);
        return $row ? (string)($row[$c] ?? '') : null;
    }
    return null;
}

/** Écrit une nouvelle valeur pour une occurrence. */
function bulk_write(string $ref, string $value): bool
{
    $p = explode(':', $ref, 4);
    if ($p[0] === 'cat' && count($p) === 3) {
        $f = admin_catalog_fields($p[1])[$p[2]] ?? null;
        if (!$f) return false;
        // Écrit dans la portée courante : en zone, cela crée sa version propre.
        if (admin_field_is_list($f)) {
            $rows = json_decode($value, true);
            if (!is_array($rows)) return false;
            set_setting($f['key'], (string)json_encode($rows, JSON_UNESCAPED_UNICODE));
            return true;
        }
        return admin_field_save($f, $value);
    }
    if ($p[0] === 'set' && count($p) >= 2) {
        $key = substr($ref, 4);
        if (bulk_is_sensitive($key)) return false;
        // Écriture directe : la clé porte déjà sa portée (préfixe de zone éventuel).
        $exists = db_fetch('SELECT id FROM settings WHERE setting_key = ?', [$key]);
        if ($exists) db_execute('UPDATE settings SET setting_value = ? WHERE setting_key = ?', [$value, $key]);
        else         db_execute('INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)', [$key, $value]);
        settings_cache(true);
        admin_inline_keys(true);
        return true;
    }
    if ($p[0] === 'row' && count($p) === 4) {
        [$t, $id, $c] = [$p[1], (int)$p[2], $p[3]];
        $cols = bulk_row_columns();
        if (!isset($cols[$t]) || !in_array($c, $cols[$t], true)) return false;
        db_execute('UPDATE '.$t.' SET '.$c.' = ? WHERE id = ?', [$value, $id]);
        return true;
    }
    return false;
}

/**
 * Applique le remplacement aux occurrences choisies et mémorise les
 * valeurs précédentes pour permettre l'annulation.
 */
function bulk_apply(array $refs, string $term, string $replacement): array
{
    $done = 0; $skipped = 0; $undo = [];
    foreach ($refs as $ref) {
        if (!is_string($ref)) { $skipped++; continue; }
        $old = bulk_read($ref);
        if ($old === null || bulk_count($old, $term) === 0) { $skipped++; continue; }
        $new = bulk_replace_text($old, $term, $replacement);
        if ($new === $old) { $skipped++; continue; }
        if (!bulk_write($ref, $new)) { $skipped++; continue; }
        $undo[] = ['ref' => $ref, 'old' => $old];
        $done++;
    }
    if ($undo !== []) {
        bulk_store_undo(['at' => date('c'), 'term' => $term, 'to' => $replacement, 'items' => $undo]);
    }
    return ['done' => $done, 'skipped' => $skipped];
}

/** Le journal d'annulation est global : écriture directe, hors contexte de zone. */
function bulk_store_undo(array $data): void
{
    $json = json_encode($data, JSON_UNESCAPED_UNICODE);
    $exists = db_fetch('SELECT id FROM settings WHERE setting_key = ?', ['_bulk_replace_undo']);
    if ($exists) db_execute('UPDATE settings SET setting_value = ? WHERE setting_key = ?', [$json, '_bulk_replace_undo']);
    else         db_execute('INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)', ['_bulk_replace_undo', $json]);
    settings_cache(true);
}

function bulk_last_undo(): ?array
{
    $raw = settings_cache()['_bulk_replace_undo'] ?? '';
    if ($raw === '') return null;
    $d = json_decode($raw, true);
    return (is_array($d) && !empty($d['items'])) ? $d : null;
}

/** Restaure les valeurs d'avant le dernier remplacement. */
function bulk_undo(): int
{
    $d = bulk_last_undo();
    if (!$d) return 0;
    $n = 0;
    foreach ($d['items'] as $it) {
        if (!isset($it['ref'], $it['old'])) continue;
        if (bulk_write((string)$it['ref'], (string)$it['old'])) $n++;
    }
    db_execute('DELETE FROM settings WHERE setting_key = ?', ['_bulk_replace_undo']);
    settings_cache(true);
    return $n;
}
