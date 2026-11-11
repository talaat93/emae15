<?php
declare(strict_types=1);

/* ═══════════════════════════════════════════════════
   TEXTES DES PAGES VILLE ET VILLE × MÉTIER, EN BASE (table city_texts)
   Une ligne par page : ville + métier ('' = page de la ville). Modifiables dans l'admin
   (admin/city_texts.php). Les textes d'origine (includes/city_trade_content.php) ne sont
   recopiés en base que pour les pages qui n'y sont pas encore : une page modifiée dans
   l'admin n'est jamais écrasée par une mise à jour du site.
   Champs : title, meta, h1, intro, local [[titre, texte]…], faq [[q, r]…], pannes [..], delai.
═══════════════════════════════════════════════════ */

/** Version des textes d'origine : à augmenter quand on en ajoute (les pages absentes sont alors créées). */
const CITY_TEXTS_SEED_V = 2;

function city_texts_table(): void
{
    db_execute('CREATE TABLE IF NOT EXISTS city_texts (
        ville VARCHAR(60) NOT NULL,
        metier VARCHAR(30) NOT NULL DEFAULT \'\',
        title VARCHAR(190) NOT NULL DEFAULT \'\',
        meta VARCHAR(300) NOT NULL DEFAULT \'\',
        h1 VARCHAR(190) NOT NULL DEFAULT \'\',
        intro TEXT NULL,
        local_json TEXT NULL,
        faq_json TEXT NULL,
        pannes_json TEXT NULL,
        delai VARCHAR(40) NOT NULL DEFAULT \'\',
        updated_at DATETIME NULL,
        updated_by VARCHAR(40) NOT NULL DEFAULT \'site\',
        PRIMARY KEY (ville, metier)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
}

/** Crée en base les pages des textes d'origine qui n'y sont pas encore. Retour : nombre créé. */
function city_texts_seed_missing(): int
{
    city_texts_table();
    $n = 0;
    foreach (city_texts_seed() as $ville => $pages) {
        foreach ($pages as $metier => $t) {
            $metier = $metier === 'ville' ? '' : (string)$metier;
            if (db_fetch('SELECT ville FROM city_texts WHERE ville = ? AND metier = ?', [$ville, $metier]) !== null) continue;
            city_text_save($ville, $metier, $t, 'site');
            $n++;
        }
    }
    return $n;
}

/** Enregistre une page (admin ou textes d'origine). */
function city_text_save(string $ville, string $metier, array $t, string $by = 'admin'): void
{
    $j = static fn($v): string => json_encode(array_values((array)$v), JSON_UNESCAPED_UNICODE);
    db_execute('INSERT INTO city_texts (ville, metier, title, meta, h1, intro, local_json, faq_json, pannes_json, delai, updated_at, updated_by)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?)
                ON DUPLICATE KEY UPDATE title = VALUES(title), meta = VALUES(meta), h1 = VALUES(h1), intro = VALUES(intro),
                local_json = VALUES(local_json), faq_json = VALUES(faq_json), pannes_json = VALUES(pannes_json), delai = VALUES(delai),
                updated_at = VALUES(updated_at), updated_by = VALUES(updated_by)',
        [$ville, $metier, mb_substr(trim((string)($t['title'] ?? '')), 0, 190), mb_substr(trim((string)($t['meta'] ?? '')), 0, 300),
         mb_substr(trim((string)($t['h1'] ?? '')), 0, 190), trim((string)($t['intro'] ?? '')),
         $j($t['local'] ?? []), $j($t['faq'] ?? []), $j($t['pannes'] ?? []), mb_substr(trim((string)($t['delai'] ?? '')), 0, 40), date('Y-m-d H:i:s'), $by]);
    city_text_all(true);
}

/** Toutes les pages en base, indexées « ville|métier » (une lecture par page vue). */
function city_text_all(bool $reset = false): array
{
    static $all = null;
    if ($reset) { $all = null; return []; }
    if ($all !== null) return $all;
    $all = [];
    try {
        foreach (db_fetch_all('SELECT * FROM city_texts') as $r) {
            $all[$r['ville'].'|'.$r['metier']] = [
                'title' => (string)$r['title'], 'meta' => (string)$r['meta'], 'h1' => (string)$r['h1'], 'intro' => (string)$r['intro'],
                'local' => json_decode((string)$r['local_json'], true) ?: [], 'faq' => json_decode((string)$r['faq_json'], true) ?: [],
                'pannes' => json_decode((string)$r['pannes_json'], true) ?: [], 'delai' => (string)$r['delai'],
                'updated_at' => (string)$r['updated_at'], 'updated_by' => (string)$r['updated_by'],
            ];
        }
    } catch (Throwable $e) { $all = []; }
    // Table absente ou encore vide (base pas encore migrée) : textes d'origine.
    if ($all === []) foreach (city_texts_seed() as $v => $pages) foreach ($pages as $m => $t) $all[$v.'|'.($m === 'ville' ? '' : $m)] = $t + ['title' => '', 'h1' => '', 'delai' => ''];
    return $all;
}

/** Texte d'une page (null si la page n'a pas de texte rédigé : intro vide). */
function city_text(string $ville, string $metier = ''): ?array
{
    $t = city_text_all()[$ville.'|'.$metier] ?? null;
    if ($t === null || trim((string)($t['intro'] ?? '')) === '') return null;
    return $t;
}

/** Ancien nom, utilisé par les pages métier × ville. Pannes par défaut si la page n'a pas sa liste. */
function city_trade_content(string $ville, string $tslug): ?array
{
    $t = city_text($ville, $tslug);
    if ($t === null) return null;
    if (empty($t['pannes'])) $t['pannes'] = city_trade_pannes_default($tslug);
    return $t;
}

/* ─── Temps de trajet estimé et base de Dole ─── */
/**
 * Temps de route habituel depuis Dole, en minutes (estimation selon la distance, hors
 * circulation et interventions en cours). Sert à la fourchette affichée sur la page.
 */
function city_travel_minutes(string $slug): ?int
{
    return [
        'dole' => 5, 'tavaux' => 10, 'auxonne' => 20, 'saint-vit' => 25, 'genlis' => 30,
        'dijon' => 45, 'quetigny' => 45, 'besancon' => 45, 'beaune' => 50, 'lons-le-saunier' => 50,
        'montbeliard' => 75, 'pontarlier' => 80,
    ][$slug] ?? null;
}

/** Fourchette affichée : saisie dans l'admin (page de la ville), sinon calculée. '' si inconnue. */
function city_travel_label(string $slug): string
{
    $saisi = trim((string)(city_text_all()[$slug.'|']['delai'] ?? ''));
    if ($saisi !== '') return $saisi;
    $m = city_travel_minutes($slug);
    if ($m === null) return '';
    return $m < 30 ? 'moins de 30 min' : ($m <= 60 ? '30 min à 1 h' : 'plus d\'1 h');
}

/** Villes à une vingtaine de kilomètres de Dole au plus : la page peut citer notre base. */
function city_near_base(string $slug): bool
{
    return in_array($slug, ['dole', 'tavaux', 'auxonne', 'saint-vit'], true);
}

/** Encart « temps de trajet estimé » (pages ville et ville × métier). */
function render_city_travel(array $c): string
{
    $l = city_travel_label($c['slug']);
    if ($l === '') return '';
    $ou = $c['slug'] === 'dole' ? 'dans Dole'
        : (city_near_base($c['slug']) ? 'jusqu\'à '.$c['nom'].' depuis notre base de Dole' : 'jusqu\'à '.$c['nom']);
    return '<div class="city-travel">'.icon_svg('clock').'<div><strong>Temps de trajet estimé '.e($ou).' : '.e($l).'</strong>'
         . '<span>Estimation selon la distance, hors circulation. Le délai réel dépend des interventions en cours : nous vous le confirmons au téléphone avant de partir.</span></div></div>';
}
