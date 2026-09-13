<?php
declare(strict_types=1);

/**
 * Catalogue des champs éditables, décrit page par page et section par section,
 * dans l'ordre où le visiteur les voit sur le site.
 *
 * Sert à la fois à générer les écrans d'édition et à alimenter la recherche.
 * Ajouter une page ici suffit à la rendre éditable et trouvable.
 *
 * Chaque champ : key (clé settings), label (nom parlant), type, default
 * (exactement la valeur de repli utilisée par le site), help (optionnel).
 */
function admin_page_catalog(): array
{
    $reg = company_regions();

    return [
        'accueil' => [
            'label' => "Page d'accueil",
            'icon'  => '🏠',
            'route' => '',
            'intro' => "Les sections défilent dans l'ordre exact où elles apparaissent sur votre site.",
            'sections' => [
                [
                    'id'    => 'hero',
                    'label' => 'Bandeau principal',
                    'seen'  => 'Tout en haut, la première chose que voit le visiteur',
                    'fields' => [
                        ['key'=>'home_title','label'=>'Titre principal','type'=>'text','default'=>'Votre expert multitechnique','help'=>'Le grand titre. Le mot mis en couleur se règle juste en dessous.'],
                        ['key'=>'home_title_hl','label'=>'Fin du titre, en couleur','type'=>'text','default'=>'en urgence','help'=>'Affiché à la suite du titre, surligné dans la couleur d\'accent.'],
                        ['key'=>'home_lead','label'=>'Phrase d\'accroche','type'=>'textarea','default'=>'Dépannage électrique, plomberie, chauffage, climatisation et pompes à chaleur en '.$reg.'. Intervention rapide, devis gratuit, artisans qualifiés.'],
                        ['key'=>'home_button1_label','label'=>'Bouton principal','type'=>'text','default'=>'Devis gratuit'],
                        ['key'=>'home_button2_label','label'=>'Bouton secondaire','type'=>'text','default'=>'Appeler maintenant'],
                        ['key'=>'home_chip_1','label'=>'Étiquette 1','type'=>'text','default'=>'Électricité','help'=>'Les petites pastilles de métiers sous l\'accroche.'],
                        ['key'=>'home_chip_2','label'=>'Étiquette 2','type'=>'text','default'=>'Plomberie'],
                        ['key'=>'home_chip_3','label'=>'Étiquette 3','type'=>'text','default'=>'Chauffage'],
                        ['key'=>'home_chip_4','label'=>'Étiquette 4','type'=>'text','default'=>'PAC'],
                        ['key'=>'home_chip_5','label'=>'Étiquette 5','type'=>'text','default'=>'Climatisation'],
                        ['key'=>'home_chip_6','label'=>'Étiquette 6','type'=>'text','default'=>'CVC'],
                        ['key'=>'home_quote_eyebrow','label'=>'Encart devis — surtitre','type'=>'text','default'=>'Rappel gratuit sous 30 min','help'=>'Le petit bloc de devis posé dans le bandeau.'],
                        ['key'=>'home_quote_title','label'=>'Encart devis — titre','type'=>'text','default'=>'Obtenir un devis'],
                    ],
                ],
                [
                    'id'    => 'trust',
                    'label' => 'Les 4 garanties',
                    'seen'  => 'La bande de réassurance juste sous le bandeau',
                    'fields' => [
                        ['key'=>'trust_1_title','label'=>'Garantie 1 — titre','type'=>'text','default'=>'Intervention rapide'],
                        ['key'=>'trust_1_sub','label'=>'Garantie 1 — détail','type'=>'text','default'=>'Moins de 2h en urgence'],
                        ['key'=>'trust_2_title','label'=>'Garantie 2 — titre','type'=>'text','default'=>'Devis gratuit'],
                        ['key'=>'trust_2_sub','label'=>'Garantie 2 — détail','type'=>'text','default'=>'Sans engagement'],
                        ['key'=>'trust_3_title','label'=>'Garantie 3 — titre','type'=>'text','default'=>setting('schema_rating_value','4.9').'/5','help'=>'Reprend votre note par défaut. Écrivez ici pour forcer un autre texte.'],
                        ['key'=>'trust_3_sub','label'=>'Garantie 3 — détail','type'=>'text','default'=>setting('schema_review_count','120').' avis'],
                        ['key'=>'trust_4_title','label'=>'Garantie 4 — titre','type'=>'text','default'=>'Certifiés'],
                        ['key'=>'trust_4_sub','label'=>'Garantie 4 — détail','type'=>'text','default'=>'Artisans qualifiés'],
                    ],
                ],
                [
                    'id'    => 'services',
                    'label' => 'Nos services',
                    'seen'  => 'La section des métiers, sous les garanties',
                    'link'  => ['admin/home_services.php', 'Modifier les cartes de services'],
                    'fields' => [
                        ['key'=>'services_section_label','label'=>'Surtitre','type'=>'text','default'=>'Nos pôles d\'intervention'],
                        ['key'=>'services_title','label'=>'Titre','type'=>'text','default'=>'Tout ce dont vous avez'],
                        ['key'=>'services_title_hl','label'=>'Fin du titre, en couleur','type'=>'text','default'=>'besoin'],
                        ['key'=>'services_lead','label'=>'Phrase d\'accroche','type'=>'textarea','default'=>'Dépannage urgence, installation, entretien et mise aux normes en '.$reg.' — un seul interlocuteur pour tous vos besoins techniques.'],
                    ],
                ],
                [
                    'id'    => 'process',
                    'label' => 'Comment ça marche',
                    'seen'  => 'Les 3 étapes de votre intervention',
                    'fields' => [
                        ['key'=>'process_label','label'=>'Surtitre','type'=>'text','default'=>'Comment ça marche'],
                        ['key'=>'process_title','label'=>'Titre','type'=>'text','default'=>'Votre intervention en'],
                        ['key'=>'process_title_hl','label'=>'Fin du titre, en couleur','type'=>'text','default'=>'3 étapes'],
                        ['key'=>'process_1_title','label'=>'Étape 1 — titre','type'=>'text','default'=>'Vous nous contactez'],
                        ['key'=>'process_1_text','label'=>'Étape 1 — texte','type'=>'textarea','default'=>'Appelez ou remplissez le formulaire. Nous répondons immédiatement et qualifions votre besoin en moins de 5 minutes.'],
                        ['key'=>'process_2_title','label'=>'Étape 2 — titre','type'=>'text','default'=>'Nous organisons l\'intervention'],
                        ['key'=>'process_2_text','label'=>'Étape 2 — texte','type'=>'textarea','default'=>'Un technicien qualifié est envoyé sur site. Délai et tarif estimé confirmés avant déplacement.'],
                        ['key'=>'process_3_title','label'=>'Étape 3 — titre','type'=>'text','default'=>'Intervention & compte rendu'],
                        ['key'=>'process_3_text','label'=>'Étape 3 — texte','type'=>'textarea','default'=>'Diagnostic, réparation ou installation. Compte rendu clair et facture détaillée en fin de chantier.'],
                    ],
                ],
                [
                    'id'    => 'reals',
                    'label' => 'Réalisations',
                    'seen'  => 'Vos derniers chantiers présentés sur l\'accueil',
                    'link'  => ['admin/realisations.php', 'Gérer les photos de chantiers'],
                    'fields' => [
                        ['key'=>'reals_label','label'=>'Surtitre','type'=>'text','default'=>'Nos réalisations'],
                        ['key'=>'reals_title','label'=>'Titre','type'=>'text','default'=>'Interventions'],
                        ['key'=>'reals_title_hl','label'=>'Fin du titre, en couleur','type'=>'text','default'=>'récentes'],
                        ['key'=>'reals_btn','label'=>'Bouton sous la section','type'=>'text','default'=>'Voir toutes nos réalisations'],
                    ],
                ],
                [
                    'id'    => 'reviews',
                    'label' => 'Avis clients',
                    'seen'  => 'Les témoignages affichés sur l\'accueil',
                    'link'  => ['admin/reviews.php', 'Gérer les avis'],
                    'fields' => [
                        ['key'=>'reviews_label','label'=>'Surtitre','type'=>'text','default'=>'Avis clients'],
                        ['key'=>'reviews_title','label'=>'Titre','type'=>'text','default'=>'Ce qu\'ils disent'],
                        ['key'=>'reviews_title_hl','label'=>'Fin du titre, en couleur','type'=>'text','default'=>'de nous'],
                    ],
                ],
                [
                    'id'    => 'quote',
                    'label' => 'Bloc devis',
                    'seen'  => 'Le grand encart de demande de devis',
                    'fields' => [
                        ['key'=>'qs_label','label'=>'Surtitre','type'=>'text','default'=>'Devis gratuit'],
                        ['key'=>'qs_title','label'=>'Titre','type'=>'text','default'=>'Besoin d\'un'],
                        ['key'=>'qs_title_hl','label'=>'Fin du titre, en couleur','type'=>'text','default'=>'technicien ?'],
                        ['key'=>'qs_lead','label'=>'Phrase d\'accroche','type'=>'textarea','default'=>'Décrivez votre besoin, nous vous rappelons sous 30 minutes avec un chiffrage clair. Aucune visite facturée sans votre accord.'],
                        ['key'=>'qs_perk_1','label'=>'Argument 1','type'=>'text','default'=>'Devis 100% gratuit et sans engagement'],
                        ['key'=>'qs_perk_2','label'=>'Argument 2','type'=>'text','default'=>'Rappel sous 30 minutes garanti'],
                        ['key'=>'qs_perk_3','label'=>'Argument 3','type'=>'text','default'=>'Tarif annoncé avant toute intervention'],
                        ['key'=>'qs_perk_4','label'=>'Argument 4','type'=>'text','default'=>'Technicien qualifié et assuré'],
                        ['key'=>'qs_perk_5','label'=>'Argument 5','type'=>'text','default'=>'Disponible 7j/7, urgences 24h/24'],
                        ['key'=>'qs_perk_6','label'=>'Argument 6','type'=>'text','default'=>'Facture détaillée en fin de chantier'],
                        ['key'=>'qs_form_label','label'=>'Formulaire — surtitre','type'=>'text','default'=>'Votre demande'],
                        ['key'=>'qs_form_title','label'=>'Formulaire — titre','type'=>'text','default'=>'Décrivez votre besoin'],
                    ],
                ],
                [
                    'id'    => 'zones',
                    'label' => 'Zones d\'intervention',
                    'seen'  => 'La section des territoires couverts, en bas de l\'accueil',
                    'fields' => [
                        ['key'=>'zones_label','label'=>'Surtitre','type'=>'text','default'=>'Zone d\'intervention'],
                        ['key'=>'zones_title','label'=>'Titre','type'=>'text','default'=>'Nos zones d\'intervention'],
                        ['key'=>'zones_title_hl','label'=>'Fin du titre, en couleur','type'=>'text','default'=>'partout en France'],
                        ['key'=>'zones_lead','label'=>'Phrase d\'accroche','type'=>'textarea','default'=>$reg.' — délai moyen d\'intervention inférieur à 2 heures pour les urgences dans nos zones principales.'],
                        ['key'=>'zones_btn','label'=>'Bouton','type'=>'text','default'=>'Demander une intervention'],
                    ],
                ],
            ],
        ],
    ];
}

/** Une page du catalogue, ou null. */
function admin_catalog_page(string $id): ?array
{
    return admin_page_catalog()[$id] ?? null;
}

/** Toutes les clés pilotées par une page du catalogue. */
function admin_catalog_keys(string $pageId): array
{
    $page = admin_catalog_page($pageId);
    if (!$page) return [];
    $keys = [];
    foreach ($page['sections'] as $s) foreach ($s['fields'] as $f) $keys[] = $f['key'];
    return $keys;
}

/**
 * Recherche un texte parmi tous les champs du catalogue.
 * Compare le nom du champ, la valeur enregistrée et le texte par défaut,
 * pour qu'on retrouve un champ à partir de ce qu'on lit sur le site.
 */
function admin_search_fields(string $needle): array
{
    $needle = trim($needle);
    if (mb_strlen($needle) < 2) return [];
    $hay = static fn(string $s): string => mb_strtolower(
        preg_replace('/\s+/', ' ', strtr($s, ['œ'=>'oe','æ'=>'ae'])) ?? ''
    );
    $n = $hay($needle);
    $out = [];
    foreach (admin_page_catalog() as $pageId => $page) {
        foreach ($page['sections'] as $section) {
            foreach ($section['fields'] as $f) {
                $value = setting($f['key'], (string)($f['default'] ?? ''));
                $pool  = $hay($f['label'].' '.$f['key'].' '.$value.' '.((string)($f['default'] ?? '')).' '.$section['label']);
                if (!str_contains($pool, $n)) continue;
                $out[] = [
                    'page_id'    => $pageId,
                    'page_label' => $page['label'],
                    'section'    => $section['label'],
                    'section_id' => $section['id'],
                    'key'        => $f['key'],
                    'label'      => $f['label'],
                    'value'      => $value,
                ];
                if (count($out) >= 60) return $out;
            }
        }
    }
    return $out;
}
