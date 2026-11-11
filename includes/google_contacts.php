<?php
declare(strict_types=1);

/**
 * Google Contacts : chaque demande reçue par le site (rappel, devis, rendez-vous, suite d'appel)
 * crée un contact dans le carnet Google du compte EMAE connecté (People API) : nom, téléphone,
 * e-mail, adresse, et une note avec la prestation et la date de la demande.
 *
 * Pas de doublon : avant de créer, on cherche un contact au même téléphone (9 derniers chiffres)
 * ou au même e-mail, d'abord parmi les demandes déjà synchronisées, puis dans le carnet Google.
 * S'il existe, la nouvelle demande est ajoutée à sa note.
 *
 * Jamais bloquant : la demande est enregistrée et notifiée d'abord. Le contact est créé après
 * l'envoi de la page ; en cas d'échec, la tâche automatique réessaie (quotes.google_contact).
 * Valeurs de quotes.google_contact : 'pending' (à faire), 'people/…' (fait), 'err:n' (n échecs).
 */

function google_contacts_enabled(): bool
{
    return growth_get('google_contacts_enabled') === '1' && google_oauth_connected();
}

/** À appeler après l'enregistrement d'une demande du site. */
function google_contacts_queue(int $quoteId): void
{
    if ($quoteId <= 0 || !google_contacts_enabled()) return;
    try { db_execute("UPDATE quotes SET google_contact = 'pending' WHERE id = ?", [$quoteId]); }
    catch (Throwable $e) { error_log('[EMAE contacts] file : '.$e->getMessage()); return; }
    if (PHP_SAPI === 'cli' || defined('EMAE_TESTS_SANS_RESEAU')) return;
    register_shutdown_function(static function () use ($quoteId): void {
        // Le visiteur reçoit sa page d'abord : il n'attend jamais Google.
        while (ob_get_level() > 0) @ob_end_flush();
        @flush();
        if (function_exists('fastcgi_finish_request')) @fastcgi_finish_request();
        if (function_exists('litespeed_finish_request')) @litespeed_finish_request();
        @ignore_user_abort(true);
        @set_time_limit(30);
        google_contacts_process(1, $quoteId);
    });
}

/** Traite les demandes en attente (tâche automatique) ou une seule. Retour : résumé. */
function google_contacts_process(int $limit = 10, int $onlyId = 0): string
{
    if (!google_contacts_enabled()) return 'non configuré';
    try {
        $rows = $onlyId > 0
            ? db_fetch_all("SELECT * FROM quotes WHERE id = ? AND google_contact = 'pending'", [$onlyId])
            : db_fetch_all("SELECT * FROM quotes WHERE google_contact = 'pending' OR google_contact IN ('err:1','err:2') ORDER BY id LIMIT ".max(1, $limit));
    } catch (Throwable $e) { return 'erreur : '.$e->getMessage(); }
    $ok = 0; $ko = 0;
    foreach ($rows as $q) {
        $r = google_contact_sync($q);
        if ($r['ok']) { $ok++; db_execute('UPDATE quotes SET google_contact = ? WHERE id = ?', [$r['resource'], (int)$q['id']]); }
        else {
            $ko++;
            $n = preg_match('/^err:(\d+)$/', (string)$q['google_contact'], $m) ? (int)$m[1] + 1 : 1;   // 3 essais, puis abandon
            db_execute('UPDATE quotes SET google_contact = ? WHERE id = ?', ['err:'.$n, (int)$q['id']]);
            integration_log('google', 'Contact Google non créé (demande #'.(int)$q['id'].') : '.$r['error'], []);
        }
    }
    return $rows ? $ok.' contact(s) Google à jour'.($ko ? ', '.$ko.' échec(s)' : '') : 'rien à faire';
}

/** Neuf derniers chiffres d'un numéro (06 12 34 56 78 = +33 6 12 34 56 78). */
function google_contact_phone_key(string $phone): string
{
    $d = (string)preg_replace('/\D/', '', $phone);
    return strlen($d) >= 9 ? substr($d, -9) : '';
}

/** Ligne de note ajoutée au contact pour une demande. */
function google_contact_note(array $q): string
{
    $types = ['rappel' => 'Demande de rappel', 'devis' => 'Demande de devis', 'rdv' => 'Demande de rendez-vous', 'contact' => 'Message'];
    $t   = $types[(string)($q['request_type'] ?? '')] ?? ((string)($q['source'] ?? '') === 'suite-appel' ? 'Suite d\'appel' : 'Demande');
    $d   = strtotime((string)($q['created_at'] ?? '')) ?: time();
    $svc = trim((string)($q['service_type'] ?? ''));
    $msg = trim((string)preg_replace('/\s+/', ' ', (string)($q['message'] ?? '')));
    if (mb_strlen($msg, 'UTF-8') > 200) $msg = rtrim(mb_substr($msg, 0, 197, 'UTF-8')).'…';
    return $t.' EMAE du '.date('d/m/Y à H:i', $d).' (n° '.(int)($q['id'] ?? 0).')'.($svc !== '' ? ' — '.$svc : '').($msg !== '' ? ' : '.$msg : '');
}

/** Contact People API pour une demande (création). */
function google_contact_payload(array $q): array
{
    $name = trim((string)($q['full_name'] ?? ''));
    $city = trim((string)($q['city'] ?? ''));
    $cp   = trim((string)($q['postal_code'] ?? ''));
    if ($name === '') $name = 'Client EMAE'.(($city ?: $cp) !== '' ? ' '.($city ?: $cp) : '');
    $p = [
        'names'        => [['unstructuredName' => $name]],
        'phoneNumbers' => [['value' => trim((string)$q['phone'])]],
        'biographies'  => [['value' => google_contact_note($q), 'contentType' => 'TEXT_PLAIN']],
    ];
    if (filter_var(trim((string)($q['email'] ?? '')), FILTER_VALIDATE_EMAIL)) $p['emailAddresses'] = [['value' => trim((string)$q['email'])]];
    $street = trim((string)($q['address'] ?? ''));
    if ($street !== '' || $city !== '' || $cp !== '') {
        $p['addresses'] = [array_filter(['type' => 'home', 'streetAddress' => $street, 'postalCode' => $cp, 'city' => $city, 'country' => 'France'], 'strlen')];
    }
    if (trim((string)($q['company_name'] ?? '')) !== '') $p['organizations'] = [['name' => trim((string)$q['company_name'])]];
    return $p;
}

/** Contact déjà créé pour un autre demande du même client ('' sinon). */
function google_contact_known(array $q): string
{
    $ph = google_contact_phone_key((string)$q['phone']);
    $em = mb_strtolower(trim((string)($q['email'] ?? '')), 'UTF-8');
    try {
        $rows = db_fetch_all("SELECT phone, email, google_contact FROM quotes WHERE google_contact LIKE 'people/%' AND id <> ? ORDER BY id DESC LIMIT 5000", [(int)$q['id']]);
    } catch (Throwable $e) { return ''; }
    foreach ($rows as $r) {
        if (($ph !== '' && google_contact_phone_key((string)$r['phone']) === $ph)
            || ($em !== '' && mb_strtolower(trim((string)$r['email']), 'UTF-8') === $em)) return (string)$r['google_contact'];
    }
    return '';
}

/** Recherche dans le carnet Google (contact saisi à la main, par exemple). */
function google_contact_search(array $q): string
{
    $ph = google_contact_phone_key((string)$q['phone']);
    $em = mb_strtolower(trim((string)($q['email'] ?? '')), 'UTF-8');
    $mask = 'names,phoneNumbers,emailAddresses';
    // Google demande une requête vide avant de chercher (mise à jour de son index).
    google_api('GET', 'https://people.googleapis.com/v1/people:searchContacts?query=&readMask='.$mask);
    foreach (array_filter([$em, $ph]) as $term) {
        $r = google_api('GET', 'https://people.googleapis.com/v1/people:searchContacts?pageSize=10&readMask='.$mask.'&query='.rawurlencode($term));
        foreach ((array)($r['json']['results'] ?? []) as $res) {
            $p = (array)($res['person'] ?? []);
            foreach ((array)($p['phoneNumbers'] ?? []) as $n) if ($ph !== '' && google_contact_phone_key((string)($n['value'] ?? '')) === $ph) return (string)$p['resourceName'];
            foreach ((array)($p['emailAddresses'] ?? []) as $m) if ($em !== '' && mb_strtolower((string)($m['value'] ?? ''), 'UTF-8') === $em) return (string)$p['resourceName'];
        }
    }
    return '';
}

/** Crée le contact, ou ajoute la demande à la note du contact existant. */
function google_contact_sync(array $q): array
{
    $known = google_contact_known($q) ?: google_contact_search($q);
    if ($known !== '') {
        $g = google_api('GET', 'https://people.googleapis.com/v1/'.$known.'?personFields=biographies');
        if ($g['status'] === 200) {
            $bio = trim((string)($g['json']['biographies'][0]['value'] ?? ''));
            $line = google_contact_note($q);
            if (!str_contains($bio, '(n° '.(int)$q['id'].')')) {
                $u = google_api('PATCH', 'https://people.googleapis.com/v1/'.$known.':updateContact?updatePersonFields=biographies', [
                    'etag' => (string)($g['json']['etag'] ?? ''),
                    'biographies' => [['value' => ($bio !== '' ? $bio."\n" : '').$line, 'contentType' => 'TEXT_PLAIN']],
                ]);
                if ($u['status'] !== 200) return ['ok' => false, 'error' => 'mise à jour : '.google_api_error($u)];
            }
            return ['ok' => true, 'resource' => $known, 'created' => false];
        }
        if ($g['status'] !== 404) return ['ok' => false, 'error' => 'lecture : '.google_api_error($g)];
        // 404 : contact supprimé dans Google entre-temps → on le recrée.
    }
    $c = google_api('POST', 'https://people.googleapis.com/v1/people:createContact?personFields=names', google_contact_payload($q));
    if ($c['status'] !== 200 || empty($c['json']['resourceName'])) return ['ok' => false, 'error' => 'création : '.google_api_error($c)];
    return ['ok' => true, 'resource' => (string)$c['json']['resourceName'], 'created' => true];
}
