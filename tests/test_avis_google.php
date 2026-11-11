<?php
declare(strict_types=1);
// Avis Google (novembre 2026) : cache en base, liste unique triée par date, compteur réel
// (avis Google + avis du site), badge de source sur chaque avis.

$DB = ['settings' => [], 'google_reviews' => [], 'reviews' => []];
function db_fetch_all(string $s, array $p = []): array { global $DB;
    if (str_contains($s, 'FROM settings')) return array_map(fn($k, $v) => ['setting_key' => $k, 'setting_value' => $v], array_keys($DB['settings']), $DB['settings']);
    if (str_contains($s, 'FROM google_reviews')) { $r = array_values($DB['google_reviews']); usort($r, fn($a, $b) => strcmp((string)$b['review_date'], (string)$a['review_date'])); return $r; }
    if (str_contains($s, 'FROM reviews')) return array_values(array_filter($DB['reviews'], fn($r) => $r['is_visible'] === 1 && trim((string)$r['source_note']) !== ''));
    return []; }
function db_fetch(string $s, array $p = []): ?array { return null; }
function db_execute(string $s, array $p = []): void { global $DB;
    if (str_starts_with($s, 'INSERT INTO settings')) { $DB['settings'][$p[0]] = $p[1]; return; }
    if (str_starts_with($s, 'INSERT INTO google_reviews')) {
        $DB['google_reviews'][$p[1]] = ['source' => $p[0], 'ext_id' => $p[1], 'author_name' => $p[2], 'author_url' => $p[3], 'rating' => $p[4], 'content' => $p[5], 'review_date' => $p[6]];
        return; }
    if (str_starts_with($s, 'DELETE FROM google_reviews WHERE ext_id NOT IN')) { $DB['google_reviews'] = array_intersect_key($DB['google_reviews'], array_flip($p)); return; }
    if ($s === 'DELETE FROM google_reviews') $DB['google_reviews'] = []; }
function db_last_id(): int { return 1; }
define('EMAE_TESTS_SANS_RESEAU', true);

$_SERVER['SCRIPT_NAME'] = '/index.php';
$R = dirname(__DIR__);
require $R.'/includes/helpers.php';
require $R.'/includes/workflow.php';
require $R.'/includes/growth.php';
require $R.'/includes/integrations.php';
require $R.'/includes/google_api.php';
require $R.'/includes/render.php';

$pass = 0; $fail = 0;
function check(string $l, mixed $g, mixed $w): void { global $pass, $fail;
    if ($g === $w) { $pass++; echo "  ok   $l\n"; }
    else { $fail++; echo "  FAIL $l\n        obtenu  ".var_export($g, true)."\n        attendu ".var_export($w, true)."\n"; } }
function set(string $k, string $v): void { global $DB; $DB['settings'][$k] = $v; settings_cache(true); }

echo "\n1. Sans avis : aucun chiffre\n";
check('compteur vide', review_summary(), null);
check('badge absent', render_google_badge(), '');
check('liste vide', reviews_merged(), []);

echo "\n2. Note saisie à la main (fiche Google, 1 avis)\n";
set('google_reviews_url', 'https://g.page/r/test/review'); set('google_rating', '5'); set('google_review_count', '1');
$rs = review_summary();
check('1 avis Google', [$rs['count'], $rs['label'], $rs['google_count'], $rs['site_count']], [1, '1 avis Google', 1, 0]);
check('lien vers Google tant qu\'il n\'y a pas d\'avis du site', $rs['url'], 'https://g.page/r/test/review');

echo "\n3. Relève : le cache est écrit, la note et le total viennent de Google\n";
$r = google_reviews_store(['ok' => true, 'source' => 'places', 'rating' => 4.6, 'total' => 9, 'rows' => [
    ['ext_id' => 'places:a', 'author' => 'Julie M.', 'url' => 'https://www.google.com/maps/contrib/1', 'rating' => 5, 'text' => 'Rapide et propre.', 'date' => '2026-10-20'],
    ['ext_id' => 'places:b', 'author' => 'Marc T.', 'url' => '', 'rating' => 4, 'text' => 'Bon travail.', 'date' => '2026-11-02'],
    ['ext_id' => 'places:c', 'author' => 'Note seule', 'url' => '', 'rating' => 5, 'text' => '', 'date' => '2026-09-01'],
    ['ext_id' => 'places:x', 'author' => 'Invalide', 'url' => '', 'rating' => 0, 'text' => 'ignoré', 'date' => '2026-09-01'],
]]);
check('3 avis valides en cache', [$r['ok'], $r['count'], count($DB['google_reviews'])], [true, 3, 3]);
check('note et total de Google', [growth_get('google_rating'), growth_get('google_review_count')], ['4.6', '9']);
check('source notée', growth_get('google_reviews_source'), 'places');
// Nouvelle relève : un avis disparu de Google quitte le cache.
google_reviews_store(['ok' => true, 'source' => 'places', 'rating' => 4.6, 'total' => 9, 'rows' => [
    ['ext_id' => 'places:a', 'author' => 'Julie M.', 'url' => 'https://www.google.com/maps/contrib/1', 'rating' => 5, 'text' => 'Rapide et propre.', 'date' => '2026-10-20'],
    ['ext_id' => 'places:b', 'author' => 'Marc T.', 'url' => '', 'rating' => 4, 'text' => 'Bon travail.', 'date' => '2026-11-02'],
]]);
check('avis retiré de Google : retiré du cache', array_keys($DB['google_reviews']), ['places:a', 'places:b']);

echo "\n4. Liste unique, du plus récent au plus ancien, avis sans date à la fin\n";
$DB['reviews'] = [
    ['id' => 1, 'author_name' => 'Nadia B.', 'rating' => 5, 'content' => 'Merci !', 'service_type' => 'Chauffage', 'city' => 'Dole', 'is_visible' => 1, 'source_note' => 'SMS du 25/10/2026', 'review_date' => '2026-10-25'],
    ['id' => 2, 'author_name' => 'Sans date', 'rating' => 3, 'content' => 'Correct.', 'service_type' => '', 'city' => '', 'is_visible' => 1, 'source_note' => 'courrier', 'review_date' => null],
    ['id' => 3, 'author_name' => 'Non publié', 'rating' => 1, 'content' => 'x', 'service_type' => '', 'city' => '', 'is_visible' => 1, 'source_note' => '', 'review_date' => '2026-11-10'],
];
$l = reviews_merged();
check('ordre', array_map(fn($v) => $v['author'], $l), ['Marc T.', 'Nadia B.', 'Julie M.', 'Sans date']);
check('sources', array_map(fn($v) => $v['source'], $l), ['google', 'site', 'google', 'site']);
check('limite (accueil)', count(reviews_merged(2)), 2);

echo "\n5. Compteur : avis Google + avis du site, moyenne pondérée\n";
$rs = review_summary();
// (4,6 × 9 + 5 + 3) / 11 = 4,49 → 4,5
check('total et moyenne', [$rs['count'], $rs['rating']], [11, 4.5]);
check('libellé', $rs['label'], '11 avis (Google et clients EMAE)');
check('lien vers la page Avis du site', $rs['url'], route_url('avis'));
check('badge : même compteur', str_contains(render_google_badge(), '4,5/5</strong> · 11 avis (Google et clients EMAE)'), true);
check('bandeau de réassurance : même compteur', in_array('11 avis (Google et clients EMAE)', array_column(trust_band_items(), 2), true), true);

echo "\n6. Carte d'avis : date, source, attribution Google\n";
$g = render_review_card($l[2]);
check('badge Google', str_contains($g, 'rv-src--google'), true);
check('auteur lié à son profil Google', str_contains($g, 'href="https://www.google.com/maps/contrib/1"'), true);
check('date en français', str_contains($g, '<time datetime="2026-10-20" class="rv-date">20 octobre 2026</time>'), true);
$s = render_review_card($l[1]);
check('badge avis client EMAE', str_contains($s, 'Avis client EMAE'), true);
check('ville affichée', str_contains($s, '📍 Dole'), true);
check('sans date : pas de date inventée', str_contains(render_review_card($l[3]), '<time'), false);
check('extrait sur l\'accueil', str_contains(render_review_card(['author' => 'A', 'rating' => 5, 'text' => str_repeat('mot ', 100), 'date' => '', 'source' => 'site', 'url' => '', 'city' => '', 'service' => ''], false), '…</p>'), true);

echo "\n7. Relève : jamais pendant une visite si la tâche cron tourne\n";
set('google_place_id', 'ChIJtest'); $GLOBALS['DB']['settings']['google_places_api_key'] = 'cle'; settings_cache(true);
set('google_cron_seen_at', date('Y-m-d H:i:s', time() - 3600));
check('visite : attend la tâche cron', automation_google_daily_test('page'), 'attend la tâche cron');
set('google_reviews_updated_at', date('Y-m-d H:i:s', time() - 3600));
check('cron : déjà fait aujourd\'hui', automation_google_daily_test('cron'), 'déjà fait aujourd\'hui');

echo "\n";
echo $fail === 0 ? "$pass assertions PASSENT\n" : "$fail ÉCHECS sur ".($pass + $fail)."\n";
exit($fail === 0 ? 0 : 1);

/** automation_google_daily vit dans automations.php (qui charge tout le site) : on lit sa source. */
function automation_google_daily_test(string $origin): string
{
    static $loaded = false;
    if (!$loaded) {
        $src = (string)file_get_contents(dirname(__DIR__).'/includes/automations.php');
        preg_match('/function automation_google_daily\(.*?\n}\n/s', str_replace("\r\n", "\n", $src), $m);
        eval(str_replace('function automation_google_daily(', 'function automation_google_daily_src(', $m[0]));
        $loaded = true;
    }
    return automation_google_daily_src($origin);
}
