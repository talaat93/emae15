<?php
declare(strict_types=1);

/**
 * Google : connexion OAuth du compte EMAE, avis de la fiche (mis en cache), contacts.
 *
 * Aucun identifiant dans le code : l'ID client, le secret client et le jeton de connexion sont
 * des secrets (integration_secret : config.local.php non versionné, ou saisis dans l'admin).
 * Écran : admin/google.php. Retour de connexion : admin/google_oauth.php.
 *
 * Avis : une fois par jour (tâche automatique « google »), jamais pendant une visite.
 *  1. API Google Business Profile (tous les avis) si le compte est connecté et la fiche choisie ;
 *  2. sinon Places API (5 avis au plus, choisis par Google) avec la clé et le Place ID.
 * Le cache est réécrit à chaque relève : un avis supprimé sur Google disparaît du site.
 */

/* ─── Connexion OAuth (un seul client pour les avis et les contacts) ─── */
function google_oauth_scopes(): array
{
    return [
        'https://www.googleapis.com/auth/business.manage',   // avis de la fiche (API Business Profile)
        'https://www.googleapis.com/auth/contacts',          // Google Contacts (People API)
    ];
}

function google_oauth_configured(): bool
{
    return integration_secret('google_oauth_client_id') !== '' && integration_secret('google_oauth_client_secret') !== '';
}

function google_oauth_connected(): bool
{
    return google_oauth_configured() && integration_secret('google_oauth_refresh_token') !== '';
}

/** Adresse de retour à déclarer dans Google Cloud (« URI de redirection autorisés »). */
function google_oauth_redirect_uri(): string
{
    return absolute_url(url_for('admin/google_oauth.php'));
}

function google_oauth_auth_url(string $state): string
{
    return 'https://accounts.google.com/o/oauth2/v2/auth?'.http_build_query([
        'client_id'     => integration_secret('google_oauth_client_id'),
        'redirect_uri'  => google_oauth_redirect_uri(),
        'response_type' => 'code',
        'scope'         => implode(' ', google_oauth_scopes()),
        'access_type'   => 'offline',
        'prompt'        => 'consent',          // redonne un jeton durable à chaque connexion
        'include_granted_scopes' => 'true',
        'state'         => $state,
    ]);
}

/** Échange le code reçu au retour de Google contre le jeton durable (enregistré comme secret). */
function google_oauth_exchange(string $code): array
{
    $r = integration_http('POST', 'https://oauth2.googleapis.com/token', ['Content-Type: application/x-www-form-urlencoded'], http_build_query([
        'code'          => $code,
        'client_id'     => integration_secret('google_oauth_client_id'),
        'client_secret' => integration_secret('google_oauth_client_secret'),
        'redirect_uri'  => google_oauth_redirect_uri(),
        'grant_type'    => 'authorization_code',
    ]));
    $refresh = (string)($r['json']['refresh_token'] ?? '');
    if ($r['status'] !== 200 || $refresh === '') {
        return ['ok' => false, 'error' => (string)($r['json']['error_description'] ?? $r['json']['error'] ?? $r['error'] ?? ('code '.$r['status']))];
    }
    set_integration_secret('google_oauth_refresh_token', $refresh);
    growth_save('google_oauth_scopes', (string)($r['json']['scope'] ?? ''));
    growth_save('google_oauth_connected_at', date('Y-m-d H:i:s'));
    return ['ok' => true];
}

/** Jeton d'accès (1 h), obtenu depuis le jeton durable ; '' si la connexion est absente ou révoquée. */
function google_access_token(bool $reset = false): string
{
    static $tok = null, $exp = 0;
    if ($reset) { $tok = null; $exp = 0; }
    if ($tok !== null && time() < $exp - 60) return $tok;
    if (!google_oauth_connected()) return '';
    $r = integration_http('POST', 'https://oauth2.googleapis.com/token', ['Content-Type: application/x-www-form-urlencoded'], http_build_query([
        'client_id'     => integration_secret('google_oauth_client_id'),
        'client_secret' => integration_secret('google_oauth_client_secret'),
        'refresh_token' => integration_secret('google_oauth_refresh_token'),
        'grant_type'    => 'refresh_token',
    ]));
    $tok = (string)($r['json']['access_token'] ?? '');
    $exp = time() + (int)($r['json']['expires_in'] ?? 0);
    if ($tok === '') {
        integration_log('google', 'Jeton Google refusé : '.(string)($r['json']['error'] ?? $r['error'] ?? $r['status']), []);
        $tok = null;
        return '';
    }
    return $tok;
}

/** Appel d'une API Google avec le compte connecté. */
function google_api(string $method, string $url, ?array $body = null): array
{
    $t = google_access_token();
    if ($t === '') return ['status' => 0, 'json' => null, 'error' => 'Compte Google non connecté.', 'body' => ''];
    $h = ['Authorization: Bearer '.$t];
    if ($body !== null) $h[] = 'Content-Type: application/json';
    return integration_http($method, $url, $h, $body);
}

function google_api_error(array $r): string
{
    return (string)($r['json']['error']['message'] ?? $r['error'] ?? ('code '.$r['status']));
}

/* ─── Avis : cache en base ─── */
function google_reviews_table(): void
{
    static $ok = false;
    if ($ok) return;
    db_execute('CREATE TABLE IF NOT EXISTS google_reviews (
        id INT AUTO_INCREMENT PRIMARY KEY,
        source VARCHAR(20) NOT NULL,
        ext_id VARCHAR(190) NOT NULL,
        author_name VARCHAR(190) NOT NULL,
        author_url VARCHAR(500) NULL,
        rating TINYINT NOT NULL DEFAULT 5,
        content TEXT NULL,
        review_date DATE NULL,
        fetched_at DATETIME NOT NULL,
        UNIQUE KEY uq_ext (ext_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    $ok = true;
}

/** Fiche à lire avec l'API Business Profile : « accounts/123/locations/456 ». */
function google_gbp_location(): string
{
    $v = trim(growth_get('google_gbp_location'));
    return preg_match('#^accounts/\d+/locations/\d+$#', $v) ? $v : '';
}

/** Fiches du compte connecté : [['name' => 'accounts/…/locations/…', 'title' => …], …]. */
function google_gbp_locations(): array
{
    $acc = google_api('GET', 'https://mybusinessaccountmanagement.googleapis.com/v1/accounts');
    if ($acc['status'] !== 200) return ['error' => google_api_error($acc)];
    $out = [];
    foreach ((array)($acc['json']['accounts'] ?? []) as $a) {
        $an = (string)($a['name'] ?? '');
        $l = google_api('GET', 'https://mybusinessbusinessinformation.googleapis.com/v1/'.$an.'/locations?readMask=name,title&pageSize=100');
        foreach ((array)($l['json']['locations'] ?? []) as $loc) {
            $out[] = ['name' => $an.'/'.(string)($loc['name'] ?? ''), 'title' => (string)($loc['title'] ?? '')];
        }
    }
    return $out;
}

/** Tous les avis via l'API Business Profile. */
function google_reviews_from_gbp(): array
{
    $loc = google_gbp_location();
    if ($loc === '' || !google_oauth_connected()) return ['ok' => false, 'error' => 'Fiche ou connexion Google absente.'];
    $stars = ['ONE' => 1, 'TWO' => 2, 'THREE' => 3, 'FOUR' => 4, 'FIVE' => 5];
    $rows = []; $page = ''; $avg = 0.0; $total = 0;
    for ($i = 0; $i < 20; $i++) {   // 20 pages de 50 : bien au-delà du besoin
        $r = google_api('GET', 'https://mybusiness.googleapis.com/v4/'.$loc.'/reviews?pageSize=50'.($page !== '' ? '&pageToken='.rawurlencode($page) : ''));
        if ($r['status'] !== 200) return ['ok' => false, 'error' => google_api_error($r)];
        $avg   = (float)($r['json']['averageRating'] ?? $avg);
        $total = (int)($r['json']['totalReviewCount'] ?? $total);
        foreach ((array)($r['json']['reviews'] ?? []) as $v) {
            $rv = (array)($v['reviewer'] ?? []);
            $rows[] = [
                'ext_id'  => 'gbp:'.(string)($v['reviewId'] ?? $v['name'] ?? md5(json_encode($v))),
                'author'  => !empty($rv['isAnonymous']) ? 'Client Google' : (string)($rv['displayName'] ?? 'Client Google'),
                'url'     => '',
                'rating'  => $stars[(string)($v['starRating'] ?? '')] ?? 0,
                // Google ajoute parfois la traduction : on garde le texte d'origine.
                'text'    => trim((string)preg_replace('/\s*\(Translated by Google\).*$/s', '', (string)preg_replace('/^\(Original\)\s*/', '', (string)($v['comment'] ?? '')))),
                'date'    => substr((string)($v['createTime'] ?? ''), 0, 10),
            ];
        }
        $page = (string)($r['json']['nextPageToken'] ?? '');
        if ($page === '') break;
    }
    return ['ok' => true, 'source' => 'gbp', 'rows' => $rows, 'rating' => $avg, 'total' => $total];
}

/** Jusqu'à 5 avis via Places API (New). */
function google_reviews_from_places(): array
{
    $key = integration_secret('google_places_api_key');
    $place = trim(growth_get('google_place_id'));
    if ($key === '' || $place === '') return ['ok' => false, 'error' => 'Clé Places ou Place ID manquant.'];
    $r = integration_http('GET', 'https://places.googleapis.com/v1/places/'.rawurlencode($place).'?languageCode=fr', [
        'X-Goog-Api-Key: '.$key,
        'X-Goog-FieldMask: rating,userRatingCount,reviews',
    ], null, 15);
    if ($r['status'] !== 200) return ['ok' => false, 'error' => google_api_error($r)];
    $rows = [];
    foreach ((array)($r['json']['reviews'] ?? []) as $v) {
        $a = (array)($v['authorAttribution'] ?? []);
        $t = (array)($v['originalText'] ?? $v['text'] ?? []);
        $rows[] = [
            'ext_id' => 'places:'.(string)($v['name'] ?? md5(json_encode($v))),
            'author' => (string)($a['displayName'] ?? 'Client Google'),
            'url'    => (string)($a['uri'] ?? ''),
            'rating' => (int)($v['rating'] ?? 0),
            'text'   => trim((string)($t['text'] ?? '')),
            'date'   => substr((string)($v['publishTime'] ?? ''), 0, 10),
        ];
    }
    return ['ok' => true, 'source' => 'places', 'rows' => $rows, 'rating' => (float)($r['json']['rating'] ?? 0), 'total' => (int)($r['json']['userRatingCount'] ?? 0)];
}

/** Relève les avis (Business Profile, sinon Places) et met à jour le cache, la note et le nombre d'avis. */
function google_reviews_refresh(): array
{
    $res = google_gbp_location() !== '' && google_oauth_connected() ? google_reviews_from_gbp() : ['ok' => false, 'error' => ''];
    if (!$res['ok']) {
        $gbpErr = $res['error'];
        $res = google_reviews_from_places();
        if (!$res['ok'] && $gbpErr !== '') $res['error'] = 'Business Profile : '.$gbpErr.' · Places : '.$res['error'];
    }
    if (!$res['ok']) {
        growth_save('google_reviews_error', date('d/m/Y H:i').' — '.$res['error']);
        integration_log('google', 'Avis Google non relevés : '.$res['error'], []);
        return $res;
    }
    return google_reviews_store($res);
}

/** Écrit une relève dans le cache (remplace l'ancienne) et met à jour la note et le nombre d'avis. */
function google_reviews_store(array $res): array
{
    google_reviews_table();
    $now = date('Y-m-d H:i:s');
    $ids = [];
    foreach ($res['rows'] as $v) {
        if ($v['rating'] < 1 || $v['rating'] > 5) continue;
        $d = preg_match('/^\d{4}-\d{2}-\d{2}$/', $v['date']) ? $v['date'] : null;
        db_execute('INSERT INTO google_reviews (source, ext_id, author_name, author_url, rating, content, review_date, fetched_at) VALUES (?,?,?,?,?,?,?,?)
                    ON DUPLICATE KEY UPDATE source = VALUES(source), author_name = VALUES(author_name), author_url = VALUES(author_url), rating = VALUES(rating),
                    content = VALUES(content), review_date = VALUES(review_date), fetched_at = VALUES(fetched_at)',
            [$res['source'], mb_substr($v['ext_id'], 0, 190), mb_substr($v['author'], 0, 190), mb_substr($v['url'], 0, 500), $v['rating'], $v['text'], $d, $now]);
        $ids[] = mb_substr($v['ext_id'], 0, 190);
    }
    // Ce que Google ne renvoie plus (avis supprimé, ou sorti des 5 de Places) quitte le site.
    if ($ids) db_execute('DELETE FROM google_reviews WHERE ext_id NOT IN ('.implode(',', array_fill(0, count($ids), '?')).')', $ids);
    else db_execute('DELETE FROM google_reviews');
    if ($res['rating'] > 0 && $res['total'] > 0) {
        growth_save('google_rating', (string)round($res['rating'], 2));
        growth_save('google_review_count', (string)$res['total']);
        growth_save('google_rating_updated_at', $now);
    }
    growth_save('google_reviews_source', $res['source']);
    growth_save('google_reviews_updated_at', $now);
    growth_save('google_reviews_error', '');
    return ['ok' => true, 'source' => $res['source'], 'count' => count($ids), 'rating' => $res['rating'], 'total' => $res['total']];
}

/** Avis Google en cache (aucun appel à Google). */
function google_reviews_cached(): array
{
    try { google_reviews_table(); return db_fetch_all('SELECT * FROM google_reviews ORDER BY review_date DESC, id DESC'); }
    catch (Throwable $e) { return []; }
}

/* ─── Liste unique et compteur ─── */
/**
 * Avis Google en cache et avis saisis dans l'admin (provenance notée), du plus récent au plus
 * ancien ; les avis sans date à la fin. Chaque avis : author, rating, text, date (AAAA-MM-JJ ou ''),
 * source ('google' | 'site'), url (profil de l'auteur sur Google), city, service.
 */
function reviews_merged(int $limit = 0): array
{
    $all = [];
    foreach (google_reviews_cached() as $g) {
        if (trim((string)$g['content']) === '') continue;   // note sans texte : comptée, pas affichée
        $all[] = ['author' => (string)$g['author_name'], 'rating' => (int)$g['rating'], 'text' => (string)$g['content'],
                  'date' => (string)($g['review_date'] ?? ''), 'source' => 'google', 'url' => (string)($g['author_url'] ?? ''), 'city' => '', 'service' => ''];
    }
    foreach (all_published_reviews() as $s) {
        $all[] = ['author' => (string)$s['author_name'], 'rating' => max(1, min(5, (int)$s['rating'])), 'text' => (string)$s['content'],
                  'date' => (string)($s['review_date'] ?? ''), 'source' => 'site', 'url' => '', 'city' => (string)($s['city'] ?? ''), 'service' => (string)($s['service_type'] ?? '')];
    }
    usort($all, static fn(array $a, array $b): int => [$a['date'] === '' ? 1 : 0, $b['date']] <=> [$b['date'] === '' ? 1 : 0, $a['date']]);
    return $limit > 0 ? array_slice($all, 0, $limit) : $all;
}
