<?php
declare(strict_types=1);

/**
 * Mise en cohérence des contenus enregistrés (novembre 2026).
 *
 * Le code ne publie plus de promesse non documentée (claim_unsupported() filtre à
 * l'affichage), mais les anciens textes restent en base et réapparaîtraient dans les
 * écrans d'édition. Cette mise à jour les retire de la base, portée par portée (site
 * global puis chaque zone) :
 *
 *  1. relance geo_appliquer(false) : variables de lieu des zones créées depuis, textes de
 *     zone qui citent une autre région (les variables et titres du site global, choisis par
 *     le propriétaire, ne sont pas réécrits) ;
 *  2. textes simples (catalogue) contenant une promesse non documentée : remis au texte
 *     par défaut (global) ou à l'héritage (zone) ;
 *  3. listes : arguments « Pourquoi nous » qui citent un autre secteur ou une promesse
 *     (Toulouse, Tarn, « équipes en Île-de-France », « 4,9/5 ») retirés ; réponses de la
 *     FAQ mises en cohérence ; chiffres de l'accueil non sourcés retirés ;
 *  4. « qasd » retiré des étiquettes, espaces avant les virgules dans les variables de lieu ;
 *  5. note et nombre d'avis saisis sans source, adresse remplacée par une liste de
 *     départements, délais chiffrés des zones.
 *
 * Chaque valeur modifiée est d'abord sauvegardée (table geo_sauvegarde, lot
 * « coherence-… ») ; « Diagnostic des zones » permet de tout annuler. Aucune donnée
 * client n'est lue ni modifiée.
 */

function coherence_ecrire(string $lot, string $cle, string $valeur, array &$bilan): void
{
    $avant = settings_cache()[$cle] ?? null;
    if ($avant === null && $valeur === '') return;
    if ($avant === $valeur) return;
    geo_sauvegarder($lot, 'setting:'.$cle, $avant);
    geo_ecrire_brut($cle, $valeur);
    $bilan['textes']++;
}

/** Retire le mot parasite « qasd » d'un texte ou d'une liste JSON. */
function coherence_sans_qasd(string $v): string
{
    if (!preg_match('/\bqasd\b/i', $v)) return $v;
    $d = json_decode($v, true);
    if (is_array($d)) {
        array_walk_recursive($d, static function (&$x): void {
            if (is_string($x)) $x = trim((string)preg_replace(['/\bqasd\b/i', '/\s{2,}/'], ['', ' '], $x));
        });
        if (array_is_list($d)) $d = array_values(array_filter($d, static fn($x) => $x !== ''));
        return (string)json_encode($d, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
    return trim((string)preg_replace(['/\s*[|,;]?\s*\bqasd\b\s*/i', '/\s{2,}/'], [' ', ' '], $v));
}

function coherence_appliquer(): array
{
    $lot   = 'coherence-'.date('Ymd-His').'-'.bin2hex(random_bytes(2));
    $bilan = ['lot' => $lot, 'date' => date('c'), 'textes' => 0, 'listes' => 0, 'zones' => 0, 'secteurs' => null];
    try { geo_sauvegarde_table(); } catch (Throwable $e) {}

    // 1. Secteurs (lot à part, annulable séparément).
    try { $s = geo_appliquer(false); $bilan['secteurs'] = ['lot' => $s['lot'], 'changements' => $s['ecrits'] + $s['retires'] + $s['variables']]; }
    catch (Throwable $e) { error_log('[EMAE coherence] secteurs : '.$e->getMessage()); }
    settings_cache(true);

    $avant = zone_context();
    try { $zones = db_fetch_all('SELECT * FROM zones ORDER BY sort_order ASC, id ASC'); } catch (Throwable $e) { $zones = []; }
    $portees = [null];
    foreach ($zones as $z) $portees[] = $z;

    foreach ($portees as $z) {
        set_zone_context($z);
        $pref   = $z ? 'z:'.$z['slug'].':' : '';
        $profil = $z ? (geo_profil_zone($z) ?? geo_profil_global()) : geo_profil_global();

        // 2. Textes simples du catalogue.
        foreach (geo_catalogue() as $cle => $f) {
            if (admin_field_is_list($f) || geo_cle_exclue($cle) || !empty($f['json'])) continue;
            $raw = settings_cache()[$pref.$cle] ?? null;
            if ($raw === null || $raw === '' || in_array($raw[0], ['[', '{'], true)) continue;
            if (claim_unsupported($raw)) coherence_ecrire($lot, $pref.$cle, '', $bilan);
        }
        // Champs rangés dans un bloc JSON (accroche de la page Nos zones…).
        foreach (['zones_page_settings', 'faq_page_settings', 'contact_page_settings', 'why_us_settings'] as $blob) {
            $raw = settings_cache()[$pref.$blob] ?? null;
            if ($raw === null || $raw === '') continue;
            $d = json_decode($raw, true);
            if (!is_array($d)) continue;
            $orig = $d;
            foreach (['lead', 'hero_lead', 'subtitle', 'title_hl'] as $k) {
                if (isset($d[$k]) && is_string($d[$k]) && (claim_unsupported($d[$k]) || geo_erreur_certaine(geo_hors_secteur($d[$k], $profil)))) unset($d[$k]);
            }
            // 3a. Arguments « Pourquoi nous ».
            if ($blob === 'why_us_settings' && isset($d['items']) && is_array($d['items'])) {
                $d['items'] = array_values(array_filter($d['items'], static function ($it) use ($profil): bool {
                    $t = (string)($it['title'] ?? '').' '.(string)($it['text'] ?? '');
                    return !claim_unsupported($t) && !geo_erreur_certaine(geo_hors_secteur($t, $profil));
                }));
                if ($d['items'] === []) unset($d['items']);
            }
            // 3b. FAQ : zones, horaires, délais.
            if ($blob === 'faq_page_settings' && isset($d['groups']) && is_array($d['groups'])) {
                foreach ($d['groups'] as $gi => $g) foreach ((array)($g['items'] ?? []) as $ii => $it) {
                    $q = (string)($it['q'] ?? ''); $a = (string)($it['a'] ?? '');
                    $fix = faq_answer_fix($q, $a);
                    if ($fix !== $a) $d['groups'][$gi]['items'][$ii]['a'] = $fix;
                }
            }
            if ($d !== $orig) {
                coherence_ecrire($lot, $pref.$blob, $d === [] ? '' : (string)json_encode($d, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $bilan);
                $bilan['listes']++;
            }
        }
        // 3c. Chiffres de l'accueil et garanties : par paire (chiffre + libellé).
        for ($i = 1; $i <= 4; $i++) {
            $n = (string)(settings_cache()[$pref.'stat_'.$i.'_number'] ?? '');
            $l = (string)(settings_cache()[$pref.'stat_'.$i.'_label'] ?? '');
            if (($n !== '' || $l !== '') && (claim_unsupported($n.' '.$l) || preg_match('/\d\s*\+|<|\d[.,]\d|note|interventions\s*\/\s*an/iu', $n.' '.$l))) {
                coherence_ecrire($lot, $pref.'stat_'.$i.'_number', '', $bilan);
                coherence_ecrire($lot, $pref.'stat_'.$i.'_label', '', $bilan);
            }
        }
        // Garantie 3 : la note est désormais celle de la fiche Google, jamais un texte saisi.
        foreach (['trust_3_title', 'trust_3_sub', 'home_trust_3_label'] as $k) coherence_ecrire($lot, $pref.$k, '', $bilan);
        // Étiquettes et listes des pages métier : « qasd », promesses.
        foreach (settings_cache() as $k => $v) {
            if ($pref !== '' ? !str_starts_with($k, $pref) : str_starts_with($k, 'z:')) continue;
            $court = substr($k, strlen($pref));
            if (!preg_match('/^svc_[a-z]+_(badges|offer_items|tags)$/', $court)) continue;
            $nv = coherence_sans_qasd((string)$v);
            $d = json_decode($nv, true);
            if (is_array($d) && array_is_list($d)) {
                $d = array_values(array_filter($d, static fn($x) => !is_string($x) || !claim_unsupported($x)));
                $nv = (string)json_encode($d, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }
            if ($nv !== $v && trim((string)$v) !== $nv) { coherence_ecrire($lot, $k, $nv, $bilan); $bilan['listes']++; }
        }
    }
    set_zone_context(null);

    // 4. « qasd » partout ailleurs ; espaces avant les virgules dans les variables de lieu.
    foreach (settings_cache() as $k => $v) {
        $v = (string)$v;
        $nv = coherence_sans_qasd($v);
        if (str_contains($k, ZVAR_PREFIX)) $nv = (string)preg_replace('/\s+,/u', ',', $nv);
        if ($nv !== $v) coherence_ecrire($lot, $k, $nv, $bilan);
    }

    // 5. Valeurs globales sans source.
    foreach (['schema_rating_value', 'schema_review_count'] as $k) coherence_ecrire($lot, $k, '', $bilan);
    // Directeur de la publication : une personne physique, pas le nom de la société.
    $dir = trim((string)(settings_cache()['ml_directeur'] ?? ''));
    if ($dir !== '' && (strcasecmp($dir, company_name()) === 0 || stripos($dir, 'EMAE') !== false || !str_contains($dir, ' '))) coherence_ecrire($lot, 'ml_directeur', '', $bilan);
    $addr = (string)(settings_cache()['company_address'] ?? '');
    if ($addr !== '' && !preg_match('/\d+.*\b(rue|avenue|av\.|boulevard|bd|chemin|route|place|impasse|allée|quai)\b.*\b\d{5}\b/iu', $addr)) {
        coherence_ecrire($lot, 'company_address', '', $bilan);   // ex. « 75-77-78-91-92-93-94-95 »
    }
    foreach (settings_cache() as $k => $v) {
        if (preg_match('/^(z:[a-z0-9-]+:)?avail_open_text$/', $k) && claim_unsupported((string)$v)) coherence_ecrire($lot, $k, '', $bilan);
    }
    foreach ($zones as $z) {
        $d = trim((string)($z['delay'] ?? ''));
        if ($d !== '' && claim_unsupported($d)) {
            geo_sauvegarder($lot, 'zone:'.(int)$z['id'].':delay', $d);
            try { db_execute('UPDATE zones SET `delay` = NULL WHERE id = ?', [(int)$z['id']]); $bilan['zones']++; } catch (Throwable $e) {}
        }
    }

    set_zone_context($avant);
    settings_cache(true);
    if (function_exists('admin_inline_keys')) admin_inline_keys(true);
    if (function_exists('intervention_zones_flush')) intervention_zones_flush();
    geo_ecrire_brut('coherence_bilan', (string)json_encode($bilan, JSON_UNESCAPED_UNICODE));
    return $bilan;
}

function coherence_dernier_bilan(): ?array
{
    $d = json_decode((string)(settings_cache()['coherence_bilan'] ?? ''), true);
    return is_array($d) ? $d : null;
}
