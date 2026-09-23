<?php
declare(strict_types=1);

/**
 * Contenus pré-rédigés par zone.
 *
 * Ces textes ne remplacent rien tant qu'on ne les applique pas depuis
 * l'administration : ils deviennent alors des personnalisations de la zone,
 * modifiables ensuite comme n'importe quel autre texte, et annulables en
 * vidant le champ pour retrouver la version du site global.
 *
 * Règle de rédaction : on adapte la géographie et le vocabulaire local, on
 * n'invente aucune promesse commerciale absente du site global.
 */
function zone_content_packs(): array
{
    $tel = company_phone();

    return [
        'paris-ile-de-france' => [
            'label'  => 'Île-de-France',
            'resume' => 'Textes orientés Paris et les 8 départements franciliens, pour le référencement local.',
            'groupes' => [
                "Page d'accueil" => [
                    'home_title'        => 'Votre expert multitechnique en',
                    'home_title_hl'     => 'Île-de-France',
                    'home_lead'         => 'Électricité, plomberie, chauffage et climatisation à Paris et dans toute l\'Île-de-France. Intervention rapide en urgence, devis gratuit, artisans qualifiés.',
                    'home_button2_label'=> 'Appeler '.$tel,
                    'services_title'    => 'Tout ce dont vous avez besoin en',
                    'services_title_hl' => 'Île-de-France',
                    'services_lead'     => 'Dépannage d\'urgence, installation, entretien et mise aux normes dans les huit départements franciliens — un seul interlocuteur pour tous vos besoins techniques.',
                    'trust_1_sub'       => 'Moins de 2h en urgence en Île-de-France',
                    'qs_lead'           => 'Décrivez votre besoin, nous vous rappelons sous 30 minutes avec un chiffrage clair. Nos équipes couvrent Paris et la petite comme la grande couronne.',
                    'zones_title'       => 'Nos zones d\'intervention en',
                    'zones_title_hl'    => 'Île-de-France',
                    'zones_lead'        => 'Paris, Hauts-de-Seine, Seine-Saint-Denis, Val-de-Marne, Seine-et-Marne, Yvelines, Essonne et Val-d\'Oise — délai moyen d\'intervention inférieur à 2 heures pour les urgences.',
                    'why_lead'          => 'Des artisans qualifiés, des délais respectés, des devis clairs. Nos équipes connaissent le bâti francilien, des immeubles haussmanniens aux copropriétés récentes.',
                ],
                'Page Nos zones' => [
                    'zp_title_hl'     => 'Île-de-France',
                    'zp_lead'         => 'Des techniciens répartis sur les huit départements franciliens, disponibles 24h/24 et 7j/7. Délai d\'intervention garanti sur toute la région.',
                    'zones_meta_title'=> 'Zones d\'intervention en Île-de-France | Électricien, plombier, chauffagiste',
                    'zones_meta_desc' => 'Nous intervenons à Paris, dans les Hauts-de-Seine, la Seine-Saint-Denis, le Val-de-Marne, la Seine-et-Marne, les Yvelines, l\'Essonne et le Val-d\'Oise. Urgence 24h/7j.',
                ],
                'Pages métier' => [
                    'svc_electricite_desc'   => 'Dépannage, installation, mise aux normes et rénovation électrique à Paris et dans toute l\'Île-de-France.',
                    'svc_plomberie_desc'     => 'Recherche de fuite, dépannage sanitaire et entretien de réseau à Paris et en Île-de-France.',
                    'svc_chauffage_desc'     => 'Dépannage de chaudière gaz, fioul ou électrique, pompe à chaleur et entretien annuel en Île-de-France.',
                    'svc_climatisation_desc' => 'Installation, dépannage et entretien de climatisation et de CVC à Paris et en Île-de-France.',
                    'svc_zones_title'        => 'Zones couvertes en Île-de-France',
                ],
                'Référencement des autres pages' => [
                    'faq_meta_title'        => 'Questions fréquentes — Dépannage en Île-de-France',
                    'faq_meta_description'  => 'Délais, tarifs, garanties, zones desservies : les réponses à vos questions sur nos interventions en Île-de-France.',
                    'contact_meta_title'    => 'Contact — Électricien, plombier, chauffagiste en Île-de-France',
                    'contact_meta_description' => 'Contactez nos équipes pour une intervention à Paris ou en Île-de-France. Réponse sous 30 minutes, devis gratuit.',
                    'quote_meta_title'      => 'Devis gratuit en Île-de-France — Électricité, plomberie, chauffage',
                    'quote_meta_description'=> 'Demandez votre devis gratuit pour une intervention à Paris ou en Île-de-France. Rappel sous 30 minutes, sans engagement.',
                    'reals_meta_title'      => 'Nos chantiers en Île-de-France — Réalisations',
                    'reals_meta_description'=> 'Découvrez nos interventions récentes à Paris et en Île-de-France : électricité, plomberie, chauffage et climatisation.',
                    'reals_page_lead'       => 'Des interventions propres et documentées à Paris et dans toute l\'Île-de-France.',
                    'avis_meta_title'       => 'Avis clients en Île-de-France',
                ],
            ],
        ],
    ];
}

/** Le contenu pré-rédigé disponible pour une zone, ou null. */
function zone_content_pack(string $slug): ?array
{
    return zone_content_packs()[$slug] ?? null;
}

/** Tous les couples clé => texte proposé, à plat. */
function zone_content_fields(string $slug): array
{
    $pack = zone_content_pack($slug);
    if (!$pack) return [];
    $out = [];
    foreach ($pack['groupes'] as $champs) foreach ($champs as $k => $v) $out[$k] = $v;
    return $out;
}

/**
 * Compare le contenu proposé à ce que la zone affiche aujourd'hui.
 * Doit être appelé avec le contexte de la zone déjà actif.
 */
function zone_content_preview(string $slug): array
{
    $pack = zone_content_pack($slug);
    if (!$pack) return [];

    $lignes = [];
    foreach ($pack['groupes'] as $groupe => $champs) {
        foreach ($champs as $key => $propose) {
            $champ   = zone_content_field_def($key);
            $actuel  = $champ ? admin_field_shown($champ) : setting($key, '');
            $perso   = $champ ? (admin_field_raw($champ) !== '') : (raw_setting($key) !== '');
            $lignes[] = [
                'groupe'      => $groupe,
                'key'         => $key,
                'label'       => $champ['label'] ?? $key,
                'actuel'      => $actuel,
                'propose'     => $propose,
                'personnalise'=> $perso,                    // déjà retouché pour cette zone
                'identique'   => trim($actuel) === trim($propose),
            ];
        }
    }
    return $lignes;
}

/** Définition catalogue d'une clé, pour écrire au bon endroit (bloc JSON compris). */
function zone_content_field_def(string $key): ?array
{
    static $index = null;
    if ($index === null) {
        $index = [];
        foreach (admin_page_catalog() as $page) {
            foreach ($page['sections'] as $s) foreach ($s['fields'] as $f) $index[$f['key']] = $f;
        }
    }
    return $index[$key] ?? null;
}

/**
 * Applique les textes choisis à la zone active. Retourne le nombre de
 * textes réellement modifiés.
 */
function zone_content_apply(string $slug, array $keys): int
{
    if (zone_ctx_id() <= 0) return 0;
    $propose = zone_content_fields($slug);
    $n = 0;
    foreach ($keys as $key) {
        if (!is_string($key) || !isset($propose[$key])) continue;
        $champ = zone_content_field_def($key);
        if ($champ && !admin_field_is_list($champ)) {
            if (admin_field_save($champ, $propose[$key])) $n++;
        } else {
            if (raw_setting($key) !== $propose[$key]) { set_setting($key, $propose[$key]); $n++; }
        }
    }
    return $n;
}
