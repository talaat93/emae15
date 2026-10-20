<?php
declare(strict_types=1);

/**
 * Secteurs géographiques : qui couvre quoi, et quels textes citent le
 * mauvais secteur.
 *
 * Pourquoi ce fichier. Un sous-site reprend les textes du site global tant
 * qu'il n'a pas les siens. Un titre global écrit en dur « en Île-de-France »
 * s'affichait donc tel quel sur la zone Jura, et un texte recopié d'une zone
 * à l'autre gardait le nom de la première. Le remède tient en deux règles :
 *
 *  1. Les textes globaux s'écrivent avec des variables de lieu ({en_region},
 *     {ville}…). Chaque zone les renseigne : le même texte devient juste
 *     partout, sans recopie.
 *  2. Un texte propre à une zone qui cite un autre secteur est repéré, et
 *     retiré quand l'erreur est certaine : la zone reprend alors le texte
 *     global, qui s'adapte à elle.
 *
 * Toute écriture automatique est d'abord sauvegardée (table geo_sauvegarde)
 * et s'annule depuis l'écran « Diagnostic des zones ».
 */

/**
 * Départements reconnus : nom, forme avec préposition, région, villes.
 * La première ville est celle mise en avant : la plus peuplée, donc celle
 * qui génère le plus de demandes.
 */
function geo_departements(): array
{
    $bfc = 'Bourgogne-Franche-Comté'; $ara = 'Auvergne-Rhône-Alpes';
    $idf = 'Île-de-France';           $occ = 'Occitanie';
    return [
        '21' => ["Côte-d'Or",            "en Côte-d'Or",               $bfc, ['Dijon','Beaune','Chenôve','Talant','Auxonne','Montbard']],
        '25' => ['Doubs',                'dans le Doubs',              $bfc, ['Besançon','Montbéliard','Pontarlier','Audincourt','Morteau']],
        '39' => ['Jura',                 'dans le Jura',               $bfc, ['Dole','Lons-le-Saunier','Saint-Claude','Champagnole','Morez']],
        '58' => ['Nièvre',               'dans la Nièvre',             $bfc, ['Nevers']],
        '70' => ['Haute-Saône',          'en Haute-Saône',             $bfc, ['Vesoul']],
        '71' => ['Saône-et-Loire',       'en Saône-et-Loire',          $bfc, ['Chalon-sur-Saône','Mâcon']],
        '89' => ['Yonne',                "dans l'Yonne",               $bfc, ['Auxerre']],
        '90' => ['Territoire de Belfort','dans le Territoire de Belfort',$bfc, ['Belfort']],
        '01' => ['Ain',                  "dans l'Ain",                 $ara, ['Bourg-en-Bresse','Oyonnax']],
        '03' => ['Allier',               "dans l'Allier",              $ara, ['Montluçon','Vichy','Moulins']],
        '07' => ['Ardèche',              'en Ardèche',                 $ara, ['Annonay','Aubenas','Privas']],
        '15' => ['Cantal',               'dans le Cantal',             $ara, ['Aurillac']],
        '26' => ['Drôme',                'dans la Drôme',              $ara, ['Valence','Montélimar']],
        '38' => ['Isère',                'en Isère',                   $ara, ['Grenoble','Bourgoin-Jallieu','Échirolles']],
        '42' => ['Loire',                'dans la Loire',              $ara, ['Saint-Étienne','Roanne']],
        '43' => ['Haute-Loire',          'en Haute-Loire',             $ara, ['Le Puy-en-Velay']],
        '63' => ['Puy-de-Dôme',          'dans le Puy-de-Dôme',        $ara, ['Clermont-Ferrand']],
        '69' => ['Rhône',                'dans le Rhône',              $ara, ['Lyon','Villeurbanne','Vénissieux']],
        '73' => ['Savoie',               'en Savoie',                  $ara, ['Chambéry','Aix-les-Bains']],
        '74' => ['Haute-Savoie',         'en Haute-Savoie',            $ara, ['Annecy','Annemasse','Thonon-les-Bains']],
        '75' => ['Paris',                'à Paris',                    $idf, ['Paris']],
        '77' => ['Seine-et-Marne',       'en Seine-et-Marne',          $idf, ['Meaux','Melun','Chelles','Marne-la-Vallée']],
        '78' => ['Yvelines',             'dans les Yvelines',          $idf, ['Versailles','Saint-Germain-en-Laye']],
        '91' => ['Essonne',              "dans l'Essonne",             $idf, ['Évry-Courcouronnes','Évry','Massy']],
        '92' => ['Hauts-de-Seine',       'dans les Hauts-de-Seine',    $idf, ['Boulogne-Billancourt','Nanterre']],
        '93' => ['Seine-Saint-Denis',    'en Seine-Saint-Denis',       $idf, ['Saint-Denis','Montreuil','Bobigny']],
        '94' => ['Val-de-Marne',         'dans le Val-de-Marne',       $idf, ['Créteil','Vitry-sur-Seine']],
        '95' => ["Val-d'Oise",           "dans le Val-d'Oise",         $idf, ['Argenteuil','Cergy']],
        '11' => ['Aude',                 "dans l'Aude",                $occ, ['Narbonne','Carcassonne']],
        '30' => ['Gard',                 'dans le Gard',               $occ, ['Nîmes']],
        '31' => ['Haute-Garonne',        'en Haute-Garonne',           $occ, ['Toulouse']],
        '34' => ['Hérault',              "dans l'Hérault",             $occ, ['Montpellier','Béziers','Sète']],
        '66' => ['Pyrénées-Orientales',  'dans les Pyrénées-Orientales',$occ, ['Perpignan']],
    ];
}

/** Régions reconnues, avec leur préposition, et les autres noms sous lesquels on les écrit. */
function geo_regions(): array
{
    return [
        'Bourgogne-Franche-Comté' => ['en Bourgogne-Franche-Comté', ['Bourgogne', 'Franche-Comté']],
        'Auvergne-Rhône-Alpes'    => ['en Auvergne-Rhône-Alpes',    ['Auvergne']],
        'Île-de-France'           => ['en Île-de-France',           ['IDF', 'Idf']],
        'Occitanie'               => ['en Occitanie',               []],
    ];
}

/**
 * Ce que couvre le site global : le Doubs, le Jura et la Côte-d'Or.
 * Les valeurs des variables sont écrites à la main, une déduction
 * automatique donnerait « dans le Doubs, dans le Jura et en Côte-d'Or ».
 */
function geo_profil_global(): array
{
    return [
        'codes'   => ['25', '39', '21'],
        'regions' => ['Bourgogne-Franche-Comté'],
        'propres' => [],
        'vars'    => [
            'region'         => "Doubs, Jura, Côte-d'Or",
            'en_region'      => "dans le Doubs, le Jura et la Côte-d'Or",
            'departement'    => "Doubs, Jura et Côte-d'Or",
            'en_departement' => "dans le Doubs, le Jura et la Côte-d'Or",
            'ville'          => 'Besançon, Dijon et Dole',
            'en_ville'       => 'à Besançon, Dijon et Dole',
            'code_postal'    => '25, 39, 21',
        ],
    ];
}

/** « à Lyon », mais « au Puy-en-Velay ». */
function geo_a_ville(string $ville): string
{
    if (str_starts_with($ville, 'Le '))  return 'au '.substr($ville, 3);
    if (str_starts_with($ville, 'Les ')) return 'aux '.substr($ville, 4);
    return 'à '.$ville;
}

/** « A », « A et B », « A, B et C ». */
function geo_enumerer(array $items): string
{
    $items = array_values($items);
    if (count($items) <= 1) return (string)($items[0] ?? '');
    $dernier = array_pop($items);
    return implode(', ', $items).' et '.$dernier;
}

/**
 * Ce que couvre une zone, déduit de ses codes postaux, sinon de son nom.
 * null quand rien n'est reconnu : on ne touche alors à rien.
 * 'vars' est null quand la zone chevauche plusieurs régions.
 */
function geo_profil_zone(array $zone): ?array
{
    $deps  = geo_departements();
    $codes = [];
    if (preg_match_all('/\b(\d{2})\d{0,3}\b/', (string)($zone['postal_codes'] ?? ''), $m)) {
        foreach ($m[1] as $c) if (isset($deps[$c]) && !in_array($c, $codes, true)) $codes[] = $c;
    }
    $regionsNommees = [];
    if (!$codes) {
        $nom = (string)($zone['name'] ?? '').' '.str_replace('-', ' ', (string)($zone['slug'] ?? ''));
        foreach (geo_lieux_cites($nom) as $t) {
            foreach ($t['codes'] as $c) if (!in_array($c, $codes, true)) $codes[] = $c;
            if (!$t['codes']) $regionsNommees[] = $t['region'];
        }
        // Une zone nommée d'après une région seule couvre toute la région.
        if (!$codes) foreach ($deps as $c => $d) if (in_array($d[2], $regionsNommees, true)) $codes[] = (string)$c;
    }
    if (!$codes) return null;

    $regions = array_values(array_unique(array_map(fn($c) => $deps[$c][2], $codes)));
    $propres = [(string)($zone['name'] ?? '')];
    foreach (zone_split_list($zone['cities'] ?? '') as $v) $propres[] = trim((string)preg_replace('/\s*\(.*\)$/', '', $v));

    $vars = null;
    if (count($regions) === 1) {
        $c1    = $codes[0];
        $ville = $deps[$c1][3][0];
        if (count($codes) === 1) {
            [$nom, $dans] = $deps[$c1];
            $vars = ['region' => $nom, 'en_region' => $dans, 'departement' => $nom, 'en_departement' => $dans];
        } elseif (count($codes) >= 3) {
            $r = $regions[0];
            $vars = ['region' => $r, 'en_region' => geo_regions()[$r][0], 'departement' => $r, 'en_departement' => geo_regions()[$r][0]];
        } else {
            $noms = geo_enumerer(array_map(fn($c) => $deps[$c][0], $codes));
            $dans = geo_enumerer(array_map(fn($c) => $deps[$c][1], $codes));
            $vars = ['region' => $noms, 'en_region' => $dans, 'departement' => $noms, 'en_departement' => $dans];
        }
        $vars += ['ville' => $ville, 'en_ville' => geo_a_ville($ville), 'code_postal' => implode(', ', $codes)];
    }
    return ['codes' => $codes, 'regions' => $regions, 'propres' => array_values(array_filter($propres)), 'vars' => $vars];
}

/* ═══════════════════════════════════════════════════
   REPÉRAGE DES LIEUX CITÉS DANS UN TEXTE
═══════════════════════════════════════════════════ */

/**
 * Forme comparable : sans accents (la casse est gardée, elle distingue
 * « Loire » du mot courant), apostrophes et tirets unifiés.
 */
function geo_normaliser(string $s): string
{
    $s = strtr($s, [
        'à'=>'a','â'=>'a','ä'=>'a','é'=>'e','è'=>'e','ê'=>'e','ë'=>'e','î'=>'i','ï'=>'i','ô'=>'o','ö'=>'o',
        'ù'=>'u','û'=>'u','ü'=>'u','ç'=>'c','À'=>'A','Â'=>'A','É'=>'E','È'=>'E','Ê'=>'E','Î'=>'I','Ï'=>'I',
        'Ô'=>'O','Ù'=>'U','Û'=>'U','Ç'=>'C','’'=>"'",'‘'=>"'",'-'=>' ','‑'=>' ','–'=>' ',
    ]);
    return trim((string)preg_replace('/\s+/u', ' ', $s));
}

/** Tous les noms de lieux reconnus, les plus longs d'abord. */
function geo_termes(): array
{
    static $termes = null;
    if ($termes !== null) return $termes;
    $termes = [];
    foreach (geo_departements() as $code => [$nom, , $region, $villes]) {
        $code = (string)$code;
        foreach (array_merge([$nom], $villes) as $t) {
            $n = geo_normaliser($t);
            $termes[$n]['nom']     = $termes[$n]['nom'] ?? $t;
            $termes[$n]['region']  = $region;
            $termes[$n]['codes'][] = $code;
        }
    }
    foreach (geo_regions() as $region => [, $alias]) {
        foreach (array_merge([$region], $alias) as $t) {
            $termes[geo_normaliser($t)] = ['nom' => $t, 'region' => $region, 'codes' => []];
        }
    }
    uksort($termes, fn($a, $b) => mb_strlen($b) <=> mb_strlen($a));
    return $termes;
}

/**
 * Les lieux cités dans un texte, dans l'ordre de lecture. Les noms les plus
 * longs sont essayés d'abord à chaque position : « Saône-et-Loire » ne compte
 * pas aussi pour « Loire », ni « Haute-Savoie » pour « Savoie ».
 */
function geo_lieux_cites(string $texte): array
{
    static $motif = null;
    if (trim($texte) === '') return [];
    $termes = geo_termes();
    $motif ??= '/(?<![\p{L}\d])(?:'.implode('|', array_map(fn($n) => preg_quote((string)$n, '/'), array_keys($termes))).')(?![\p{L}\d])/u';
    if (!preg_match_all($motif, geo_normaliser($texte), $m)) return [];
    $trouves = [];
    foreach (array_unique($m[0]) as $n) $trouves[] = $termes[$n];
    return $trouves;
}

/**
 * Compare les lieux cités au secteur d'une zone.
 *
 * @return array{etrangers:string[], autre_region:bool, propre:bool}
 */
function geo_hors_secteur(string $texte, array $profil): array
{
    $propres = array_map('geo_normaliser', $profil['propres'] ?? []);
    $out = ['etrangers' => [], 'autre_region' => false, 'propre' => false];
    foreach (geo_lieux_cites($texte) as $t) {
        $chezSoi = $t['codes']
            ? (bool)array_intersect($t['codes'], $profil['codes'])
            : in_array($t['region'], $profil['regions'], true);
        if ($chezSoi || in_array(geo_normaliser($t['nom']), $propres, true)) { $out['propre'] = true; continue; }
        $out['etrangers'][] = $t['nom'];
        if (!in_array($t['region'], $profil['regions'], true)) $out['autre_region'] = true;
    }
    $out['etrangers'] = array_values(array_unique($out['etrangers']));
    return $out;
}

/**
 * L'erreur est-elle certaine ? Oui si le texte cite une autre région, ou
 * s'il ne cite que des départements voisins sans jamais nommer le sien.
 * « Besançon et Belfort » sur la zone Doubs reste en place : c'est peut-être
 * voulu, le diagnostic le signale simplement.
 */
function geo_erreur_certaine(array $verdict): bool
{
    return $verdict['autre_region'] || ($verdict['etrangers'] && !$verdict['propre']);
}

/* ═══════════════════════════════════════════════════
   TEXTES DU SITE GLOBAL
═══════════════════════════════════════════════════ */

/**
 * Titres et descriptions du site global, écrits avec des variables : le
 * global affiche le Doubs, le Jura et la Côte-d'Or, et chaque zone qui en
 * hérite affiche son propre secteur.
 */
function geo_textes_globaux(): array
{
    $n = company_name();
    return [
        'company_regions'          => '{region}',
        'home_meta_title'          => 'Électricien, plombier, chauffagiste {en_ville} | '.$n,
        'home_meta_description'    => 'Dépannage, installation et entretien en électricité, plomberie, chauffage et climatisation {en_region}. Intervention rapide {en_ville}, devis gratuit.',
        'home_title'               => 'Votre expert multitechnique',
        'home_title_hl'            => '{en_region}',
        'home_lead'                => 'Électricité, plomberie, chauffage, climatisation et pompes à chaleur {en_region}. Intervention rapide autour de {ville}, devis gratuit, artisans qualifiés.',
        'home_quote_city_placeholder' => 'Ex : {ville}…',
        'services_lead'            => 'Dépannage d\'urgence, installation, entretien et mise aux normes {en_region} — un seul interlocuteur pour tous vos besoins techniques.',
        'zp_title'                 => 'Nous intervenons',
        'zp_title_hl'              => '{en_region}',
        'zones_meta_title'         => 'Zones d\'intervention {en_region} | Électricien, plombier, chauffagiste',
        'zones_meta_desc'          => 'Nous intervenons {en_region}, autour de {ville} : électricité, plomberie, chauffage, climatisation. Urgence 24h/7j.',
        'faq_meta_title'           => 'Questions fréquentes — Dépannage {en_region}',
        'faq_meta_description'     => 'Délais, tarifs, garanties, secteurs desservis : les réponses à vos questions sur nos interventions {en_region}.',
        'contact_meta_title'       => 'Contact — Électricien, plombier, chauffagiste {en_region}',
        'contact_meta_description' => 'Contactez nos équipes pour une intervention {en_region}, autour de {ville}. Réponse sous 30 minutes, devis gratuit.',
        'quote_meta_title'         => 'Devis gratuit {en_region} — Électricité, plomberie, chauffage',
        'quote_meta_description'   => 'Demandez votre devis gratuit pour une intervention {en_region}, autour de {ville}. Rappel sous 30 minutes, sans engagement.',
        'reals_meta_title'         => 'Nos chantiers {en_region} — Réalisations',
        'reals_meta_description'   => 'Découvrez nos interventions récentes {en_region} : électricité, plomberie, chauffage et climatisation.',
        'reals_page_lead'          => 'Des interventions propres et documentées {en_region}.',
        'avis_meta_title'          => 'Avis clients {en_region} | '.$n,
        'svc_electricite_desc'     => 'Dépannage, installation, mise aux normes et rénovation électrique {en_region}.',
        'svc_plomberie_desc'       => 'Recherche de fuite, dépannage sanitaire et entretien de réseau {en_region}.',
        'svc_chauffage_desc'       => 'Dépannage de chaudière gaz, fioul ou électrique, pompe à chaleur et entretien {en_region}.',
        'svc_climatisation_desc'   => 'Installation, dépannage et entretien de climatisation et de CVC {en_region}.',
    ];
}

/** Réglages hors catalogue que le diagnostic contrôle aussi, avec l'écran où les modifier. */
function geo_reglages_hors_catalogue(): array
{
    return [
        'company_regions'       => ['Identité — Zones d\'intervention', 'admin/site_identity.php'],
        'home_meta_title'       => ['Référencement — Titre Google de l\'accueil', 'admin/seo.php'],
        'home_meta_description' => ['Référencement — Description Google de l\'accueil', 'admin/seo.php'],
    ];
}

/* ═══════════════════════════════════════════════════
   ÉCRITURE, SAUVEGARDE, ANNULATION
═══════════════════════════════════════════════════ */

/**
 * Textes où un lieu étranger au secteur est normal : mentions légales
 * (l'hébergeur est à Clermont-Ferrand), adresses et coordonnées.
 */
function geo_cle_exclue(string $cle): bool
{
    return (bool)preg_match('/^ml_|hebergeur|address|adresse|siret|email|phone|whatsapp/', $cle);
}

/** Index clé → définition du catalogue. */
function geo_catalogue(): array
{
    static $index = null;
    if ($index !== null) return $index;
    $index = [];
    if (!function_exists('admin_page_catalog')) return $index;
    foreach (admin_page_catalog() as $page) {
        foreach ($page['sections'] as $s) foreach ($s['fields'] as $f) $index[$f['key']] = $f;
    }
    return $index;
}

function geo_sauvegarde_table(): void
{
    db_execute("CREATE TABLE IF NOT EXISTS geo_sauvegarde (
        id INT AUTO_INCREMENT PRIMARY KEY,
        lot VARCHAR(40) NOT NULL,
        cible VARCHAR(255) NOT NULL,
        ancienne MEDIUMTEXT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_lot (lot)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

/** Garde l'ancienne valeur. null = la ligne n'existait pas. */
function geo_sauvegarder(string $lot, string $cible, ?string $ancienne): void
{
    db_execute('INSERT INTO geo_sauvegarde (lot, cible, ancienne) VALUES (?, ?, ?)', [$lot, $cible, $ancienne]);
}

/** Écrit un réglage sous sa clé complète, sans passer par le contexte de zone. */
function geo_ecrire_brut(string $cle, string $valeur): void
{
    if ($valeur === '') {
        db_execute('DELETE FROM settings WHERE setting_key = ?', [$cle]);
    } elseif (db_fetch('SELECT id FROM settings WHERE setting_key = ?', [$cle])) {
        db_execute('UPDATE settings SET setting_value = ? WHERE setting_key = ?', [$valeur, $cle]);
    } else {
        db_execute('INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)', [$cle, $valeur]);
    }
    settings_cache(true);
}

/**
 * Écrit un texte dans la portée courante (global ou zone active), après
 * l'avoir sauvegardé. Un champ rangé dans un bloc JSON sauvegarde le bloc
 * entier. Retourne true si quelque chose a changé.
 */
function geo_ecrire(string $lot, string $cle, string $valeur): bool
{
    $f = geo_catalogue()[$cle] ?? null;
    $stockage = $f && !empty($f['json']) ? $f['json'][0] : $cle;
    $avant    = $f ? admin_field_raw($f) : raw_setting($cle);
    if (trim($valeur) === $avant) return false;

    $complete = zone_setting_key($stockage);
    $ancienne = settings_cache()[$complete] ?? null;
    geo_sauvegarder($lot, 'setting:'.$complete, $ancienne);

    if ($f && !admin_field_is_list($f)) {
        admin_field_save($f, $valeur);
    } else {
        geo_ecrire_brut($complete, trim($valeur));
    }
    return true;
}

/**
 * Met le site en conformité avec ses secteurs. Exécutée une fois par la
 * migration v15.32 ; peut être relancée sans risque, elle n'écrit que ce
 * qui diffère.
 *
 * @return array bilan lisible : textes écrits, retirés, zones non reconnues
 */
function geo_appliquer(): array
{
    $lot   = 'secteurs-'.date('Ymd-His');
    $bilan = ['lot' => $lot, 'date' => date('c'), 'ecrits' => 0, 'retires' => 0, 'variables' => 0, 'non_reconnues' => []];
    try { geo_sauvegarde_table(); } catch (Throwable $e) { /* déjà là */ }

    $avant = zone_context();
    set_zone_context(null);

    // 1. Le site global : ses variables, puis ses titres et descriptions.
    $global = geo_profil_global();
    foreach ($global['vars'] as $nom => $v) if (geo_ecrire($lot, ZVAR_PREFIX.$nom, $v)) $bilan['variables']++;
    $textes = geo_textes_globaux();
    foreach ($textes as $cle => $v) if (geo_ecrire($lot, $cle, $v)) $bilan['ecrits']++;

    // 2. Les autres textes globaux qui citent un autre secteur reviennent au
    //    texte d'origine, désormais écrit sans lieu ou avec des variables.
    foreach (geo_catalogue() as $cle => $f) {
        if (isset($textes[$cle]) || admin_field_is_list($f) || geo_cle_exclue($cle)) continue;
        $raw = admin_field_raw($f);
        if ($raw !== '' && geo_erreur_certaine(geo_hors_secteur($raw, $global))) {
            if (geo_ecrire($lot, $cle, '')) $bilan['retires']++;
        }
    }

    // 3. Chaque zone : ses variables, puis ses propres textes erronés.
    try { $zones = db_fetch_all('SELECT * FROM zones ORDER BY sort_order ASC, id ASC'); }
    catch (Throwable $e) { $zones = []; }
    foreach ($zones as $z) {
        $profil = geo_profil_zone($z);
        if (!$profil) { $bilan['non_reconnues'][] = (string)$z['name']; continue; }
        set_zone_context($z);
        foreach ((array)$profil['vars'] as $nom => $v) if (geo_ecrire($lot, ZVAR_PREFIX.$nom, $v)) $bilan['variables']++;

        $prefixe = 'z:'.$z['slug'].':';
        $vus = [];
        foreach (geo_catalogue() as $cle => $f) {
            $vus[!empty($f['json']) ? $f['json'][0] : $cle] = true;
            if (admin_field_is_list($f) || geo_cle_exclue($cle)) continue;
            $raw = admin_field_raw($f);
            if ($raw !== '' && geo_erreur_certaine(geo_hors_secteur($raw, $profil))) {
                if (geo_ecrire($lot, $cle, '')) $bilan['retires']++;
            }
        }
        // Réglages de la zone absents du catalogue (titre Google de l'accueil…).
        foreach (settings_cache() as $k => $v) {
            if (!str_starts_with($k, $prefixe)) continue;
            $cle = substr($k, strlen($prefixe));
            if (isset($vus[$cle]) || str_starts_with($cle, ZVAR_PREFIX) || geo_cle_exclue($cle)
                || $v === '' || in_array($v[0], ['[', '{'], true)) continue;
            if (geo_erreur_certaine(geo_hors_secteur($v, $profil))) {
                geo_sauvegarder($lot, 'setting:'.$k, $v);
                geo_ecrire_brut($k, '');
                $bilan['retires']++;
            }
        }
        // Les champs propres à la zone dans la table zones.
        foreach (['meta_title', 'meta_description', 'hero_h1', 'hero_subtitle', 'intro'] as $col) {
            $v = trim((string)($z[$col] ?? ''));
            if ($v === '' || !geo_erreur_certaine(geo_hors_secteur($v, $profil))) continue;
            geo_sauvegarder($lot, 'zone:'.(int)$z['id'].':'.$col, $v);
            try { db_execute('UPDATE zones SET `'.$col.'` = NULL WHERE id = ?', [(int)$z['id']]); $bilan['retires']++; }
            catch (Throwable $e) { /* colonne absente sur ce serveur */ }
        }
    }

    set_zone_context($avant);
    settings_cache(true);
    if (function_exists('admin_inline_keys')) admin_inline_keys(true);
    if (function_exists('intervention_zones_flush')) intervention_zones_flush();
    geo_ecrire_brut('geo_secteurs_bilan', (string)json_encode($bilan, JSON_UNESCAPED_UNICODE));
    return $bilan;
}

/** Le dernier bilan, ou null. */
function geo_dernier_bilan(): ?array
{
    $d = json_decode((string)(settings_cache()['geo_secteurs_bilan'] ?? ''), true);
    return is_array($d) ? $d : null;
}

/**
 * Remet chaque valeur sauvegardée d'un lot, dans l'ordre inverse.
 * Retourne le nombre de valeurs restaurées.
 */
function geo_annuler(string $lot): int
{
    try { $lignes = db_fetch_all('SELECT * FROM geo_sauvegarde WHERE lot = ? ORDER BY id DESC', [$lot]); }
    catch (Throwable $e) { return 0; }
    $n = 0;
    foreach ($lignes as $l) {
        $cible = (string)$l['cible'];
        $old   = $l['ancienne'] ?? null;
        if (str_starts_with($cible, 'setting:')) {
            geo_ecrire_brut(substr($cible, 8), $old === null ? '' : (string)$old);
            $n++;
        } elseif (preg_match('/^zone:(\d+):(meta_title|meta_description|hero_h1|hero_subtitle|intro)$/', $cible, $m)) {
            try { db_execute('UPDATE zones SET `'.$m[2].'` = ? WHERE id = ?', [$old, (int)$m[1]]); $n++; }
            catch (Throwable $e) {}
        }
    }
    try { db_execute('DELETE FROM geo_sauvegarde WHERE lot = ?', [$lot]); } catch (Throwable $e) {}
    geo_ecrire_brut('geo_secteurs_bilan', '');
    if (function_exists('admin_inline_keys')) admin_inline_keys(true);
    if (function_exists('intervention_zones_flush')) intervention_zones_flush();
    return $n;
}

/* ═══════════════════════════════════════════════════
   DIAGNOSTIC — CE QUE LE VISITEUR VOIT RÉELLEMENT
═══════════════════════════════════════════════════ */

/**
 * Tous les textes affichés qui citent un lieu hors du secteur de la page,
 * pour le site global et chaque zone active. Compare ce que le visiteur
 * voit (valeur héritée comprise), pas seulement ce qui est enregistré.
 */
function geo_controle(): array
{
    $portees = [['zone' => null, 'nom' => '🌐 Site global', 'profil' => geo_profil_global()]];
    try { $zones = db_fetch_all('SELECT * FROM zones WHERE status = 1 ORDER BY sort_order ASC, id ASC'); }
    catch (Throwable $e) { $zones = []; }
    foreach ($zones as $z) {
        $p = geo_profil_zone($z);
        if ($p) $portees[] = ['zone' => $z, 'nom' => '📍 '.$z['name'], 'profil' => $p];
    }

    $avant = zone_context();
    $out   = [];
    $signaler = static function (array $portee, string $champ, string $texte, ?string $lien) use (&$out): void {
        $v = geo_hors_secteur($texte, $portee['profil']);
        if (!$v['etrangers']) return;
        $out[] = [
            'portee'    => $portee['nom'],
            'champ'     => $champ,
            'extrait'   => mb_strimwidth(trim((string)preg_replace('/\s+/u', ' ', $texte)), 0, 160, '…'),
            'etrangers' => $v['etrangers'],
            'lien'      => $lien,
        ];
    };

    foreach ($portees as $portee) {
        $z = $portee['zone'];
        set_zone_context($z);
        $ref = static fn(string $cle): string => $z ? 'set:z:'.$z['slug'].':'.$cle : 'set:'.$cle;
        foreach (admin_page_catalog() as $page) {
            foreach ($page['sections'] as $s) {
                foreach ($s['fields'] as $f) {
                    if (geo_cle_exclue($f['key'])) continue;
                    $lien = function_exists('bulk_edit_url') ? bulk_edit_url($ref($f['key'])) : null;
                    $signaler($portee, $page['label'].' — '.$f['label'], admin_field_shown($f), $lien);
                }
            }
        }
        foreach (geo_reglages_hors_catalogue() as $cle => [$label, $ecran]) {
            $lien = url_for($ecran.($z ? '?admin_zone='.(int)$z['id'] : ''));
            $signaler($portee, $label, setting_plain($cle), $lien);
        }
        // Le texte libre en bas des pages métier, rédigé page par page.
        foreach (array_keys(service_trade_defaults()) as $trade) {
            $page = page_by_slug(service_page_slug($trade));
            if (!$page || trim((string)($page['content_html'] ?? '')) === '') continue;
            $signaler($portee, 'Page '.service_trade_defaults()[$trade]['label'].' — Texte en bas de page',
                      html_entity_decode(strip_tags((string)$page['content_html']), ENT_QUOTES, 'UTF-8'),
                      url_for('admin/page_edit.php?id='.(int)$page['id']));
        }
        if ($z) {
            foreach (['meta_title' => 'Titre Google', 'meta_description' => 'Description Google',
                      'hero_h1' => 'Grand titre', 'hero_subtitle' => 'Sous-titre', 'intro' => 'Présentation'] as $col => $label) {
                $signaler($portee, 'Fiche de la zone — '.$label, (string)($z[$col] ?? ''),
                          url_for('admin/zone_edit.php?id='.(int)$z['id']));
            }
        }
    }
    set_zone_context($avant);
    return $out;
}
