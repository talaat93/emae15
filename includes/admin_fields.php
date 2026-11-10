<?php
declare(strict_types=1);

/**
 * Catalogue des champs éditables, décrit page par page et section par section,
 * dans l'ordre où le visiteur les voit sur le site.
 *
 * Sert à la fois à générer les écrans d'édition et à alimenter la recherche.
 * Ajouter une page ici suffit à la rendre éditable et trouvable.
 *
 * Chaque champ : key (identifiant du champ), label (nom parlant), type, default
 * (exactement la valeur de repli utilisée par le site), help (optionnel).
 * Un champ peut viser un sous-élément d'un bloc JSON via json => [clé, sous-clé].
 */
require_once __DIR__.'/content_pages.php';
function admin_page_catalog(): array
{
    $name = company_name();

    $pages = [
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
                        ['key'=>'home_eyebrow','label'=>'Pastille au-dessus du titre','type'=>'text','default'=>'Disponible '.company_hours(),'help'=>'La petite mention avec le point clignotant, tout en haut du bandeau.'],
                        ['key'=>'home_title','label'=>'Titre principal','type'=>'text','default'=>'Votre expert multitechnique','help'=>'Le grand titre. Le mot mis en couleur se règle juste en dessous.'],
                        ['key'=>'home_title_hl','label'=>'Fin du titre, en couleur','type'=>'text','default'=>'en urgence','help'=>'Affiché à la suite du titre, surligné dans la couleur d\'accent.'],
                        ['key'=>'home_lead','label'=>'Phrase d\'accroche','type'=>'textarea','default'=>'Dépannage électrique, plomberie, chauffage, climatisation et pompes à chaleur {en_region}. Intervention rapide, devis gratuit, artisans qualifiés.','help'=>'Vous pouvez écrire {ville}, {en_region} ou {departement} : chaque zone affichera sa propre valeur, réglée dans « Variables de lieu ». Un texte contenant une variable se modifie ici seulement, pas directement sur la page.'],
                        ['key'=>'home_button1_label','label'=>'Bouton principal — texte','type'=>'text','default'=>'Devis gratuit'],
                        ['key'=>'home_button1_url','label'=>'Bouton principal — destination','type'=>'text','default'=>'quote','help'=>'Nom d\'une page du site (ex : quote, contact) ou adresse complète.'],
                        ['key'=>'home_button2_label','label'=>'Bouton secondaire — texte','type'=>'text','default'=>'Appeler maintenant'],
                        ['key'=>'home_button2_url','label'=>'Bouton secondaire — destination','type'=>'text','default'=>'','help'=>'Laissez vide pour appeler votre numéro de téléphone.'],
                        ['key'=>'home_chip_1','label'=>'Étiquette 1','type'=>'text','default'=>'Électricité','help'=>'Les petites pastilles de métiers sous l\'accroche.'],
                        ['key'=>'home_chip_2','label'=>'Étiquette 2','type'=>'text','default'=>'Plomberie'],
                        ['key'=>'home_chip_3','label'=>'Étiquette 3','type'=>'text','default'=>'Chauffage'],
                        ['key'=>'home_chip_4','label'=>'Étiquette 4','type'=>'text','default'=>'PAC'],
                        ['key'=>'home_chip_5','label'=>'Étiquette 5','type'=>'text','default'=>'Climatisation'],
                        ['key'=>'home_chip_6','label'=>'Étiquette 6','type'=>'text','default'=>'CVC'],
                        ['key'=>'home_quote_eyebrow','label'=>'Encart devis — surtitre','type'=>'text','default'=>'Rappel gratuit','help'=>'Le petit bloc « être rappelé » posé dans le bandeau.'],
                        ['key'=>'home_quote_title','label'=>'Encart devis — titre','type'=>'text','default'=>'Être rappelé'],
                    ],
                ],
                [
                    'id'    => 'trust',
                    'label' => 'Les 4 garanties',
                    'seen'  => 'La bande de réassurance juste sous le bandeau',
                    'fields' => [
                        ['key'=>'trust_1_title','label'=>'Garantie 1 — titre','type'=>'text','default'=>'Intervention rapide'],
                        ['key'=>'trust_1_sub','label'=>'Garantie 1 — détail','type'=>'text','default'=>'Délai confirmé au téléphone'],
                        ['key'=>'trust_2_title','label'=>'Garantie 2 — titre','type'=>'text','default'=>'Devis gratuit'],
                        ['key'=>'trust_2_sub','label'=>'Garantie 2 — détail','type'=>'text','default'=>'Sans engagement'],
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
                        ['key'=>'services_all_title','label'=>'Tuile « Tous nos services » — titre','type'=>'text','default'=>'Tous nos services'],
                        ['key'=>'services_all_desc','label'=>'Tuile « Tous nos services » — texte','type'=>'text','default'=>'Découvrez l\'ensemble de nos métiers et de nos prestations.'],
                        ['key'=>'services_lead','label'=>'Phrase d\'accroche','type'=>'textarea','default'=>'Dépannage urgence, installation, entretien et mise aux normes {en_region} — un seul interlocuteur pour tous vos besoins techniques.','help'=>'Vous pouvez écrire {ville}, {en_region} ou {departement} : chaque zone affichera sa propre valeur, réglée dans « Variables de lieu ». Un texte contenant une variable se modifie ici seulement, pas directement sur la page.'],
                    ],
                ],
                [
                    'id'    => 'why',
                    'label' => 'Pourquoi nous choisir',
                    'seen'  => 'La section de réassurance, entre les services et les 3 étapes',
                    'link'  => ['admin/why_us.php', 'Modifier les 8 arguments et leurs icônes'],
                    'note'  => 'Ces trois textes sont stockés avec les 8 arguments. En zone, les personnaliser fige aussi les arguments pour cette zone.',
                    'fields' => [
                        ['key'=>'why_eyebrow','json'=>['why_us_settings','eyebrow'],'label'=>'Surtitre','type'=>'text','default'=>'Pourquoi nous choisir'],
                        ['key'=>'why_title','json'=>['why_us_settings','title'],'label'=>'Titre','type'=>'text','default'=>'EMAE, votre expert multitechnique de confiance'],
                        ['key'=>'why_lead','json'=>['why_us_settings','lead'],'label'=>'Phrase d\'accroche','type'=>'textarea','default'=>'Des artisans qualifiés, des délais respectés, des devis clairs. Chaque intervention est réalisée avec rigueur et professionnalisme.'],
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
                        ['key'=>'process_1_text','label'=>'Étape 1 — texte','type'=>'textarea','default'=>'Appelez ou demandez à être rappelé : nous faisons le point sur votre besoin au téléphone.'],
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
                        ['key'=>'qs_lead','label'=>'Phrase d\'accroche','type'=>'textarea','default'=>'Décrivez votre besoin, nous vous rappelons sous 5 minutes avec un chiffrage clair. Aucune visite facturée sans votre accord.'],
                        ['key'=>'qs_perk_1','label'=>'Argument 1','type'=>'text','default'=>'Devis 100% gratuit et sans engagement'],
                        ['key'=>'qs_perk_2','label'=>'Argument 2','type'=>'text','default'=>'Rappel sous 5 minutes'],
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
                        ['key'=>'zones_lead','label'=>'Phrase d\'accroche','type'=>'textarea','default'=>'{region} — délai moyen d\'intervention inférieur à 2 heures pour les urgences dans nos zones principales.','help'=>'Vous pouvez écrire {ville}, {en_region} ou {departement} : chaque zone affichera sa propre valeur, réglée dans « Variables de lieu ». Un texte contenant une variable se modifie ici seulement, pas directement sur la page.'],
                        ['key'=>'zones_btn','label'=>'Bouton','type'=>'text','default'=>'Demander une intervention'],
                    ],
                ],
            ],
        ],

        'entete' => [
            'label' => 'En-tête & pied de page',
            'icon'  => '🧭',
            'route' => '',
            'intro' => "Les éléments présents sur toutes les pages du site.",
            'sections' => [
                [
                    'id'    => 'menu',
                    'label' => 'Libellés du menu',
                    'seen'  => 'La navigation principale, en haut de chaque page',
                    'link'  => ['admin/header_menu.php', 'Modifier le reste du menu et le bandeau'],
                    'fields' => [
                        ['key'=>'nav_services','label'=>'Lien Services','type'=>'text','default'=>'Services'],
                        ['key'=>'nav_zones','label'=>'Lien Nos zones','type'=>'text','default'=>'Nos zones'],
                        ['key'=>'nav_avis','label'=>'Lien Avis clients','type'=>'text','default'=>'Avis clients'],
                        ['key'=>'nav_realisations','label'=>'Lien Réalisations','type'=>'text','default'=>'Réalisations'],
                        ['key'=>'nav_tarifs','label'=>'Lien Tarifs','type'=>'text','default'=>'Tarifs','help'=>'Visible seulement si la page Tarifs est activée.'],
                    ],
                ],
                [
                    'id'    => 'pied',
                    'label' => 'Pied de page',
                    'seen'  => 'Le bas de page, sous le contenu',
                    'link'  => ['admin/site_identity.php', 'Modifier téléphone, e-mail et adresse'],
                    'fields' => [
                        ['key'=>'company_slogan','label'=>'Slogan sous le logo','type'=>'text','default'=>'Dépannage & installation multitechnique','help'=>'Repris aussi au bas des e-mails envoyés par le site.'],
                    ],
                ],
                [
                    'id'    => 'formulaires',
                    'label' => 'Formulaires de contact',
                    'seen'  => 'Sur tous les formulaires de demande du site',
                    'fields' => [
                        ['key'=>'form_submit_label','label'=>'Texte du bouton d\'envoi','type'=>'text','default'=>'Envoyer ma demande'],
                        ['key'=>'form_success_message','label'=>'Message après envoi','type'=>'textarea','default'=>'Votre demande a bien été envoyée. Nous vous recontactons rapidement.'],
                    ],
                ],
                [
                    'id'    => 'partage',
                    'label' => 'Partage & moteurs de recherche',
                    'seen'  => 'Ce qui s\'affiche quand on partage un lien de votre site',
                    'link'  => ['admin/seo.php', 'Modifier le reste du référencement'],
                    'fields' => [
                        ['key'=>'company_description','label'=>'Description de l\'entreprise','type'=>'textarea','inline'=>false,'default'=>'Entreprise multitechnique — dépannage, installation, entretien en électricité, plomberie, chauffage et climatisation.','help'=>'Utilisée par Google pour décrire votre établissement. Invisible sur la page, donc modifiable seulement ici.'],
                        ['key'=>'og_default_image','label'=>'Image de partage','type'=>'text','default'=>'','help'=>'Chemin d\'une image, ex : storage/uploads/partage.jpg. Affichée sur Facebook, WhatsApp, LinkedIn.'],
                    ],
                ],
            ],
        ],

        'page_zones' => [
            'label' => 'Page Nos zones',
            'icon'  => '🗺️',
            'route' => 'zones',
            'sections' => [
                [
                    'id'    => 'hero',
                    'label' => 'Haut de page',
                    'seen'  => 'Le bandeau de titre de la page Nos zones',
                    'link'  => ['admin/zones.php', 'Modifier les zones, leurs villes et leurs départements'],
                    'fields' => [
                        ['key'=>'zp_eyebrow','json'=>['zones_page_settings','eyebrow'],'label'=>'Surtitre','type'=>'text','default'=>'Zones d\'intervention'],
                        ['key'=>'zp_title','json'=>['zones_page_settings','title'],'label'=>'Titre','type'=>'text','default'=>'Nous intervenons'],
                        ['key'=>'zp_title_hl','json'=>['zones_page_settings','title_hl'],'label'=>'Fin du titre, en couleur','type'=>'text','default'=>'{en_region}'],
                        ['key'=>'zp_lead','json'=>['zones_page_settings','lead'],'label'=>'Phrase d\'accroche','type'=>'textarea','default'=>'Électricité, plomberie, chauffage et climatisation dans nos zones. Disponibilité, délai estimé et conditions d\'intervention confirmés par téléphone avant déplacement.'],
                    ],
                ],
                [
                    'id'    => 'liste',
                    'label' => 'Titre au-dessus de la liste des zones',
                    'seen'  => 'Juste avant les cartes de zones',
                    'link'  => ['admin/zones.php', 'Modifier les zones elles-mêmes'],
                    'fields' => [
                        ['key'=>'zp_regions_label','label'=>'Surtitre','type'=>'text','default'=>'Nos régions'],
                        ['key'=>'zp_regions_title','label'=>'Titre','type'=>'text','default'=>'Nos'],
                        ['key'=>'zp_regions_title_hl','label'=>'Fin du titre, en couleur','type'=>'text','default'=>'zones couvertes'],
                    ],
                ],
                [
                    'id'    => 'seo',
                    'label' => 'Référencement de la page',
                    'seen'  => 'Le titre affiché dans Google',
                    'fields' => [
                        ['key'=>'zones_meta_title','label'=>'Titre Google','type'=>'text','default'=>'Zones d\'intervention | '.$name],
                        ['key'=>'zones_meta_desc','label'=>'Description Google','type'=>'textarea','default'=>'EMAE intervient {en_region} — électricité, plomberie, chauffage, climatisation. Urgence 24h/7j.'],
                    ],
                ],
            ],
        ],

        'page_avis' => [
            'label' => 'Page Avis clients',
            'icon'  => '⭐',
            'route' => 'avis',
            'sections' => [
                [
                    'id'    => 'contenu',
                    'label' => 'Contenu',
                    'seen'  => 'La page listant tous vos avis',
                    'link'  => ['admin/reviews.php', 'Gérer les avis affichés'],
                    'fields' => [
                        ['key'=>'avis_title','label'=>'Titre','type'=>'text','default'=>'Ce que disent'],
                        ['key'=>'avis_title_hl','label'=>'Fin du titre, en couleur','type'=>'text','default'=>'nos clients'],
                        ['key'=>'avis_lead','label'=>'Phrase d\'accroche','type'=>'textarea','default'=>'Des centaines de clients satisfaits. Voici leurs témoignages.'],
                        ['key'=>'google_mybusiness_url','label'=>'Lien vers votre fiche Google','type'=>'text','default'=>'','help'=>'Active le bouton « Laisser un avis sur Google ». Laissez vide pour le masquer.'],
                    ],
                ],
                [
                    'id'    => 'seo',
                    'label' => 'Référencement de la page',
                    'seen'  => 'Le titre affiché dans Google',
                    'fields' => [
                        ['key'=>'avis_meta_title','label'=>'Titre Google','type'=>'text','default'=>'Avis clients | '.$name],
                        ['key'=>'avis_meta_desc','label'=>'Description Google','type'=>'textarea','default'=>'Témoignages de clients sur nos interventions en électricité, plomberie, chauffage et climatisation.'],
                    ],
                ],
            ],
        ],

        'page_faq' => [
            'label' => 'Page FAQ',
            'icon'  => '❓',
            'route' => 'faq',
            'sections' => [
                [
                    'id'    => 'hero',
                    'label' => 'Haut de page',
                    'seen'  => 'Les pastilles sous le titre de la FAQ',
                    'link'  => ['admin/faq_contact.php', 'Modifier les questions et réponses'],
                    'fields' => [
                        ['key'=>'faq_badge_1','label'=>'Pastille 1','type'=>'text','default'=>'Réponse rapide'],
                        ['key'=>'faq_badge_2','label'=>'Pastille 2','type'=>'text','default'=>'Urgences 24h/7j'],
                        ['key'=>'faq_badge_3','label'=>'Pastille 3','type'=>'text','default'=>'Devis gratuit'],
                        ['key'=>'faq_cat_all','label'=>'Filtre « toutes les catégories »','type'=>'text','default'=>'Toutes'],
                    ],
                ],
                [
                    'id'    => 'seo',
                    'label' => 'Référencement de la page',
                    'seen'  => 'Le titre affiché dans Google',
                    'fields' => [
                        ['key'=>'faq_meta_title','label'=>'Titre Google','type'=>'text','default'=>'FAQ | '.$name],
                        ['key'=>'faq_meta_description','label'=>'Description Google','type'=>'textarea','default'=>'Questions fréquentes.'],
                    ],
                ],
            ],
        ],

        'page_contact' => [
            'label' => 'Page Contact',
            'icon'  => '✉️',
            'route' => 'contact',
            'sections' => [
                [
                    'id'    => 'hero',
                    'label' => 'Haut de page',
                    'seen'  => 'Les pastilles sous le titre de la page Contact',
                    'fields' => [
                        ['key'=>'contact_badge_1','label'=>'Pastille 1','type'=>'text','default'=>'Devis gratuit'],
                        ['key'=>'contact_badge_2','label'=>'Pastille 2','type'=>'text','default'=>'Réponse sous 5 min'],
                    ],
                ],
                [
                    'id'    => 'form',
                    'label' => 'Formulaire',
                    'seen'  => 'Le formulaire de la page Contact',
                    'fields' => [
                        ['key'=>'home_quote_city_placeholder','label'=>'Exemple dans le champ Ville','type'=>'text','default'=>'Ex : {ville}…'],
                    ],
                ],
                [
                    'id'    => 'seo',
                    'label' => 'Référencement de la page',
                    'seen'  => 'Le titre affiché dans Google',
                    'fields' => [
                        ['key'=>'contact_meta_title','label'=>'Titre Google','type'=>'text','default'=>'Contact | '.$name],
                        ['key'=>'contact_meta_description','label'=>'Description Google','type'=>'textarea','default'=>'Contactez EMAE.'],
                    ],
                ],
            ],
        ],

        'page_devis' => [
            'label' => 'Page Devis',
            'icon'  => '📋',
            'route' => 'quote',
            'sections' => [
                [
                    'id'    => 'contenu',
                    'label' => 'Contenu',
                    'seen'  => 'La page de demande de devis',
                    'fields' => [
                        ['key'=>'quote_hero_title','label'=>'Titre','type'=>'text','default'=>'Votre demande d\'intervention'],
                        ['key'=>'quote_hero_lead','label'=>'Phrase d\'accroche','type'=>'textarea','default'=>'Remplissez ce formulaire. Un technicien vous rappelle sous 5 minutes avec un chiffrage clair, sans engagement.'],
                        ['key'=>'quote_form_title','label'=>'Titre du formulaire','type'=>'text','default'=>'Décrivez votre besoin'],
                    ],
                ],
                [
                    'id'    => 'seo',
                    'label' => 'Référencement de la page',
                    'seen'  => 'Le titre affiché dans Google',
                    'fields' => [
                        ['key'=>'quote_meta_title','label'=>'Titre Google','type'=>'text','default'=>'Devis gratuit | '.$name],
                        ['key'=>'quote_meta_description','label'=>'Description Google','type'=>'textarea','default'=>'Devis gratuit et rapide.'],
                    ],
                ],
            ],
        ],

        'page_realisations' => [
            'label' => 'Page Réalisations',
            'icon'  => '📷',
            'route' => 'realisations',
            'sections' => [
                [
                    'id'    => 'contenu',
                    'label' => 'Contenu',
                    'seen'  => 'La page listant vos chantiers',
                    'link'  => ['admin/realisations.php', 'Gérer les photos de chantiers'],
                    'fields' => [
                        ['key'=>'reals_page_title','label'=>'Titre','type'=>'text','default'=>'Interventions & chantiers'],
                        ['key'=>'reals_page_lead','label'=>'Phrase d\'accroche','type'=>'textarea','default'=>'Des interventions propres et documentées {en_region}.'],
                        ['key'=>'reals_empty','label'=>'Message si aucune réalisation','type'=>'text','default'=>'Les réalisations seront publiées prochainement.'],
                        ['key'=>'reals_empty_btn','label'=>'Bouton si aucune réalisation','type'=>'text','default'=>'Nous contacter'],
                    ],
                ],
                [
                    'id'    => 'seo',
                    'label' => 'Référencement de la page',
                    'seen'  => 'Le titre affiché dans Google',
                    'note'  => 'L\'écran SEO comporte un champ Réalisations qui n\'a jamais eu d\'effet : utilisez celui-ci.',
                    'fields' => [
                        ['key'=>'reals_meta_title','label'=>'Titre Google','type'=>'text','default'=>'Réalisations | '.$name],
                        ['key'=>'reals_meta_description','label'=>'Description Google','type'=>'textarea','default'=>'Nos chantiers et interventions.'],
                    ],
                ],
            ],
        ],

        'page_nos_services' => [
            'label' => 'Page Nos services',
            'icon'  => '🧰',
            'route' => 'services',
            'intro' => 'La page qui présente tous les métiers. Le contenu de chaque carte se modifie dans l\'écran du métier concerné.',
            'sections' => [
                [
                    'id'    => 'textes',
                    'label' => 'Textes de la page',
                    'seen'  => 'Le haut de page, l\'introduction et l\'appel à l\'action',
                    'fields' => [
                        ['key'=>'services_eyebrow','label'=>'Surtitre','type'=>'text','default'=>'Nos services'],
                        ['key'=>'services_h1','label'=>'Grand titre','type'=>'text','default'=>'Services'],
                        ['key'=>'services_page_lead','label'=>'Phrase d\'accroche','type'=>'textarea','default'=>'Nos services multitechniques'],
                        ['key'=>'services_intro_title','label'=>'Introduction — titre','type'=>'text','default'=>'Nos '.count(service_trade_defaults()).' pôles d\'intervention'],
                        ['key'=>'services_intro_lead','label'=>'Introduction — texte','type'=>'textarea','default'=>'Un seul interlocuteur pour l\'ensemble de vos besoins techniques. EMAE intervient en urgence, installation, entretien et mise aux normes {en_region}.'],
                        ['key'=>'services_card_link','label'=>'Lien des cartes','type'=>'text','default'=>'Voir nos prestations'],
                        ['key'=>'services_cta_title','label'=>'Appel à l\'action — titre','type'=>'text','default'=>'Besoin d\'un technicien ?'],
                        ['key'=>'services_cta_lead','label'=>'Appel à l\'action — texte','type'=>'textarea','default'=>'Décrivez votre besoin, nous vous rappelons sous 5 minutes avec un chiffrage clair. Devis gratuit et sans engagement.'],
                        ['key'=>'services_cta_btn','label'=>'Appel à l\'action — bouton','type'=>'text','default'=>'Devis gratuit en 2 min'],
                    ],
                ],
            ],
        ],

        'page_services' => [
            'label' => 'Pages Service',
            'icon'  => '🔧',
            'route' => 'electricite',
            'intro' => "Ces textes sont communs aux quatre pages métier : Électricité, Plomberie, Chauffage et Climatisation.",
            'sections' => [
                [
                    'id'    => 'titres',
                    'label' => 'Titres des sections',
                    'seen'  => 'Les intertitres qui rythment chaque page métier',
                    'note'  => 'Les 3 étapes de ces pages utilisent les mêmes textes que « Comment ça marche » sur la page d\'accueil : les modifier change les deux.',
                    'fields' => [
                        ['key'=>'svc_quote_eyebrow','label'=>'Encart « Être rappelé » — surtitre','type'=>'text','default'=>'Rappel gratuit'],
                        ['key'=>'svc_quote_title','label'=>'Encart « Être rappelé » — titre','type'=>'text','default'=>'Être rappelé gratuitement'],
                        ['key'=>'svc_offer_label','label'=>'Surtitre de l\'offre','type'=>'text','default'=>'Notre offre'],
                        ['key'=>'svc_offer_lead','label'=>'Offre — texte sous le titre','type'=>'textarea','default'=>'Urgence, entretien ou projet : voici les prestations que nous réalisons le plus souvent. Pour toute autre demande, décrivez-nous votre besoin, nous vous disons rapidement si nous pouvons intervenir.'],
                        ['key'=>'svc_brands_note','label'=>'Bandeau des marques — mention sous les noms','type'=>'text','default'=>'Marques citées à titre indicatif : nous sommes un réparateur indépendant, sans lien avec ces fabricants.'],
                        ['key'=>'svc_reals_label','label'=>'Réalisations — surtitre','type'=>'text','default'=>'Nos réalisations'],
                        ['key'=>'svc_reals_title','label'=>'Réalisations — titre','type'=>'text','default'=>'Chantiers'],
                        ['key'=>'svc_reals_title_hl','label'=>'Réalisations — fin en couleur','type'=>'text','default'=>'réalisés'],
                        ['key'=>'svc_process_label','label'=>'Méthode — surtitre','type'=>'text','default'=>'Notre méthode'],
                        ['key'=>'svc_process_title','label'=>'Méthode — titre','type'=>'text','default'=>'Intervention en'],
                        ['key'=>'svc_process_title_hl','label'=>'Méthode — fin en couleur','type'=>'text','default'=>'3 étapes'],
                        ['key'=>'svc_process_lead','label'=>'Méthode — texte sous le titre','type'=>'textarea','default'=>'Un fonctionnement simple et transparent, du premier appel au compte rendu final.'],
                        ['key'=>'svc_method_1_title','label'=>'Méthode — étape 1, titre','type'=>'text','default'=>'Diagnostic sur place'],
                        ['key'=>'svc_method_1_text','label'=>'Méthode — étape 1, texte','type'=>'textarea','default'=>'Le technicien recherche la cause de la panne et vous l\'explique simplement.'],
                        ['key'=>'svc_method_2_title','label'=>'Méthode — étape 2, titre','type'=>'text','default'=>'Devis clair avant travaux'],
                        ['key'=>'svc_method_2_text','label'=>'Méthode — étape 2, texte','type'=>'textarea','default'=>'Vous connaissez le prix avant toute réparation et vous décidez : rien n\'est fait sans votre accord.'],
                        ['key'=>'svc_method_3_title','label'=>'Méthode — étape 3, titre','type'=>'text','default'=>'Réparation aux normes'],
                        ['key'=>'svc_method_3_text','label'=>'Méthode — étape 3, texte','type'=>'textarea','default'=>'Réparation ou installation dans les règles de l\'art, puis facture détaillée en fin d\'intervention.'],
                        ['key'=>'svc_signs_label','label'=>'Signes d\'alerte — surtitre','type'=>'text','default'=>'Signes d\'alerte'],
                        ['key'=>'svc_cta_title','label'=>'Bas de page — titre de l\'appel à l\'action','type'=>'text','default'=>'Un problème maintenant ?'],
                        ['key'=>'svc_cta_lead','label'=>'Bas de page — texte','type'=>'textarea','default'=>'Appelez-nous ou demandez à être rappelé : nous faisons le point avec vous et vous annonçons le prix avant tout déplacement.'],
                        ['key'=>'svc_faq_label','label'=>'FAQ — surtitre','type'=>'text','default'=>'FAQ'],
                        ['key'=>'svc_faq_title','label'=>'FAQ — titre','type'=>'text','default'=>'Questions'],
                        ['key'=>'svc_faq_title_hl','label'=>'FAQ — fin en couleur','type'=>'text','default'=>'fréquentes'],
                        ['key'=>'svc_faq_lead','label'=>'FAQ — texte sous le titre','type'=>'textarea','default'=>'Les réponses aux questions que nos clients nous posent le plus souvent. Une autre question ? Appelez-nous, nous vous répondons directement.'],
                        ['key'=>'svc_zones_label','label'=>'Zones — surtitre','type'=>'text','default'=>'Zone d\'intervention'],
                        ['key'=>'svc_zones_title','label'=>'Zones — titre','type'=>'text','default'=>'Zones couvertes'],
                        ['key'=>'svc_zones_lead','label'=>'Zones — texte sous le titre','type'=>'textarea','default'=>'Nos techniciens se déplacent dans les secteurs suivants. Votre ville n\'apparaît pas ? Appelez-nous pour vérifier votre adresse.'],
                        ['key'=>'svc_page_btn_devis','label'=>'Bouton devis','type'=>'text','default'=>'Devis gratuit'],
                        ['key'=>'svc_form_label','label'=>'Formulaire — surtitre','type'=>'text','default'=>'Devis gratuit'],
                        ['key'=>'svc_form_tag','label'=>'Formulaire — étiquette','type'=>'text','default'=>'Votre technicien'],
                        ['key'=>'svc_form_title','label'=>'Formulaire — titre','type'=>'text','default'=>'Devis gratuit'],
                    ],
                ],
            ],
        ],

        'page_tarifs' => [
            'label' => 'Page Tarifs',
            'icon'  => '💶',
            'route' => 'tarifs',
            'intro' => 'Tant que la page est désactivée, seul un administrateur connecté peut la voir : vous pouvez la préparer avant de la publier.',
            'sections' => [
                [
                    'id'    => 'statut',
                    'label' => 'Publication',
                    'seen'  => 'Active ou retire la page et son lien dans le menu',
                    'fields' => [
                        ['key'=>'tarifs_enabled','label'=>'Page Tarifs','type'=>'toggle','default'=>'0','check'=>'Afficher la page Tarifs sur le site','help'=>'Désactivée, la page disparaît du menu et du plan du site, et son adresse renvoie une erreur 404. Ce réglage vaut pour tout le site, zones comprises.'],
                    ],
                ],
                [
                    'id'    => 'grilles',
                    'label' => 'Grilles de prix',
                    'seen'  => 'Une grille par métier',
                    'note'  => 'Un prix laissé vide s\'affiche « Sur devis ». Précisez si vos prix sont HT ou TTC.',
                    'fields' => array_map(static function (string $trade, array $rows): array {
                        return ['key'=>'tarifs_'.$trade,'label'=>$trade === 'base' ? 'Déplacement et main-d\'œuvre (obligatoire pour le dépannage : prix TTC)' : service_trade_defaults()[$trade]['label'],'type'=>'list','default'=>$rows,
                            'columns'=>[
                                ['k'=>'label','label'=>'Prestation'],
                                ['k'=>'price','label'=>'Prix (ex : à partir de 90 € TTC)','w'=>'240px'],
                                ['k'=>'detail','label'=>'Précision (facultatif)'],
                            ]];
                    }, array_keys(tarifs_default_rows()), tarifs_default_rows()),
                ],
                [
                    'id'    => 'hero',
                    'label' => 'Textes de la page',
                    'seen'  => 'Le haut de page, la mention sous les grilles et l\'appel à l\'action',
                    'fields' => [
                        ['key'=>'tarifs_eyebrow','label'=>'Surtitre','type'=>'text','default'=>'Tarification'],
                        ['key'=>'tarifs_title','label'=>'Titre','type'=>'text','default'=>'Nos'],
                        ['key'=>'tarifs_title_hl','label'=>'Fin du titre, en couleur','type'=>'text','default'=>'tarifs'],
                        ['key'=>'tarifs_quote_label','label'=>'Texte si aucun prix','type'=>'text','default'=>'Sur devis'],
                        ['key'=>'tarifs_lead','label'=>'Phrase d\'accroche','type'=>'textarea','default'=>'Des prix clairs, confirmés avant toute intervention.'],
                        ['key'=>'tarifs_note','label'=>'Mention sous les grilles','type'=>'textarea','default'=>'Tarifs donnés à titre indicatif. Le prix définitif est confirmé par un devis gratuit avant toute intervention.'],
                        ['key'=>'tarifs_cta_title','label'=>'Appel à l\'action — titre','type'=>'text','default'=>'Besoin d\'un prix précis ?'],
                        ['key'=>'tarifs_cta_btn','label'=>'Appel à l\'action — bouton','type'=>'text','default'=>'Demander un devis gratuit'],
                        ['key'=>'tarifs_cta_lead','label'=>'Appel à l\'action — texte','type'=>'textarea','default'=>'Décrivez votre besoin, nous vous répondons avec un chiffrage clair et sans engagement.'],
                    ],
                ],
                [
                    'id'    => 'seo',
                    'label' => 'Référencement de la page',
                    'seen'  => 'Le titre affiché dans Google',
                    'fields' => [
                        ['key'=>'tarifs_meta_title','label'=>'Titre Google','type'=>'text','default'=>'Tarifs | '.$name],
                        ['key'=>'tarifs_meta_description','label'=>'Description Google','type'=>'textarea','default'=>'Nos tarifs en électricité, plomberie, chauffage, climatisation, ventilation, portail et automatismes. Devis gratuit avant toute intervention.'],
                    ],
                ],
            ],
        ],

        'page_mentions' => [
            'label' => 'Mentions légales',
            'icon'  => '⚖️',
            'route' => 'mentions-legales',
            'sections' => [
                [
                    'id'    => 'contenu',
                    'label' => 'Informations légales',
                    'seen'  => 'La page Mentions légales, liée depuis le pied de page',
                    'link'  => ['admin/site_identity.php#mentions', 'Dénomination, siège, SIREN, directeur de la publication, médiateur'],
                    'fields' => [
                        ['key'=>'ml_hebergeur','label'=>'Hébergeur du site','type'=>'textarea','default'=>'o2switch SAS — 222-224 boulevard Gustave Flaubert, 63000 Clermont-Ferrand — www.o2switch.fr'],
                    ],
                ],
                [
                    'id'    => 'seo',
                    'label' => 'Référencement de la page',
                    'seen'  => 'Le titre affiché dans Google',
                    'fields' => [
                        ['key'=>'ml_meta_title','label'=>'Titre Google','type'=>'text','default'=>'Mentions légales | '.$name],
                        ['key'=>'ml_meta_description','label'=>'Description Google','type'=>'textarea','default'=>'Mentions légales, informations légales et politique de confidentialité de '.$name.'.'],
                    ],
                ],
            ],
        ],
    ];

    // Les quatre pages métier sont générées à partir des textes d'origine,
    // et insérées juste après les éléments qu'elles ont en commun.
    $out = [];
    foreach ($pages as $id => $page) {
        $out[$id] = $page;
        if ($id === 'page_services') foreach (admin_service_pages() as $sid => $sp) $out[$sid] = $sp;
    }
    return $out;
}

/** URL publique d'une page du catalogue, en mode édition visuelle. */
function admin_visual_url(string $pageId): string
{
    $page = admin_catalog_page($pageId);
    if (!$page) return url_for('admin/index.php');
    $url = route_url((string)$page['route']);
    return $url . (str_contains($url, '?') ? '&' : '?') . 'admin_edit=1';
}

/**
 * Une page d'édition par métier, construite à partir des textes d'origine
 * définis dans helpers.php — une seule source de vérité pour le site et
 * pour l'administration.
 */
function admin_service_pages(): array
{
    $icones = ['electricite'=>'⚡','plomberie'=>'💧','chauffage'=>'🔥','climatisation'=>'❄️'];
    $pages  = [];

    foreach (service_trade_defaults() as $trade => $d) {
        $p = 'svc_'.$trade.'_';
        $seo = service_trade_seo_defaults()[$trade];
        $pages['metier_'.$trade] = [
            'label' => $d['label'],
            'icon'  => $icones[$trade] ?? '🔧',
            'route' => service_page_slug($trade),
            'intro' => 'Textes propres à la page '.$d['label'].'. Les trois autres métiers ont leurs propres écrans.',
            'sections' => [
                [
                    'id'    => 'intro',
                    'label' => 'Haut de page',
                    'seen'  => 'Le titre et la phrase d\'accroche de la page',
                    'fields' => [
                        ['key'=>$p.'label','label'=>'Nom du métier','type'=>'text','default'=>$d['label']],
                        ['key'=>$p.'h1','label'=>'Grand titre de la page','type'=>'text','default'=>$seo[0],
                         'help'=>'Avec {en_region} ou {ville}, chaque zone affiche son propre secteur.'],
                        ['key'=>$p.'desc','label'=>'Phrase d\'accroche','type'=>'textarea','default'=>$d['desc']],
                        ['key'=>$p.'intro','label'=>'Présentation du métier','type'=>'textarea','default'=>service_trade_intros()[$trade] ?? '',
                         'help'=>'Affichée sous le titre. Laissez une ligne vide entre deux paragraphes. {en_region} et {ville} prennent les valeurs de chaque zone.'],
                        ['key'=>$p.'badges','label'=>'Pastilles sous le titre','type'=>'list','default'=>$d['badges'],
                         'columns'=>[['k'=>'v','label'=>'Pastille']],
                         'help'=>'Les mots-clés affichés en pastilles. Ajoutez-en ou retirez-en librement.'],
                    ],
                ],
                [
                    'id'    => 'seo',
                    'label' => 'Référencement Google',
                    'seen'  => 'Le titre et la description affichés dans les résultats Google',
                    'fields' => [
                        ['key'=>$p.'meta_title','label'=>'Titre Google','type'=>'text','default'=>$seo[1]],
                        ['key'=>$p.'meta_desc','label'=>'Description Google','type'=>'textarea','default'=>$seo[2]],
                    ],
                ],
                [
                    'id'    => 'offre',
                    'label' => 'Notre offre',
                    'seen'  => 'Les cartes défilantes de vos prestations : un dessin, un titre, un texte court',
                    'fields' => [
                        ['key'=>$p.'offer_title','label'=>'Titre de la section','type'=>'text','default'=>$d['offer_title']],
                        ['key'=>$p.'offer_items','label'=>'Prestations','type'=>'list','default'=>$d['offer_items'],
                         'string_col'=>'title', 'row_upgrade'=>'service_offer_row_upgrade',
                         'columns'=>[
                            ['k'=>'icon','label'=>'Dessin','w'=>'190px','type'=>'select','options'=>service_icons()],
                            ['k'=>'title','label'=>'Titre'],
                            ['k'=>'text','label'=>'Texte court','type'=>'textarea'],
                         ],
                         'help'=>'Une carte par ligne. Gardez le texte court : une ou deux lignes suffisent.'],
                    ],
                ],
                [
                    'id'    => 'signes',
                    'label' => 'Quand nous appeler ?',
                    'seen'  => 'La liste des signes d\'alerte, sous l\'offre',
                    'fields' => [
                        ['key'=>$p.'signs_title','label'=>'Titre de la section','type'=>'text','default'=>'Quand nous appeler ?'],
                        ['key'=>$p.'signs','label'=>'Signes d\'alerte','type'=>'list','default'=>service_alert_signs()[$trade] ?? [],
                         'columns'=>[['k'=>'v','label'=>'Signe']],
                         'help'=>'Un symptôme par ligne, tel que le client le constate. Liste vide : section masquée.'],
                    ],
                ],
                [
                    'id'    => 'marques',
                    'label' => 'Marques',
                    'seen'  => 'Le bandeau défilant des marques, sous l\'en-tête de la page',
                    'fields' => [
                        ['key'=>$p.'brands_on','label'=>'Bandeau des marques','type'=>'toggle','default'=>'1','check'=>'Afficher le bandeau des marques sur cette page',
                         'help'=>'Il ne s\'affiche que si la liste ci-dessous contient au moins une marque.'],
                        ['key'=>$p.'brands_title','label'=>'Titre du bandeau','type'=>'text','default'=>service_brand_titles()[$trade] ?? 'Toutes marques'],
                        ['key'=>$p.'brands','label'=>'Marques','type'=>'list','default'=>service_brand_defaults()[$trade] ?? [],
                         'columns'=>[['k'=>'v','label'=>'Marque']],
                         'help'=>'Les noms défilent en texte. Ne citez que des marques sur lesquelles vous intervenez réellement.'],
                    ],
                ],
                [
                    'id'    => 'faq',
                    'label' => 'Questions fréquentes',
                    'seen'  => 'La FAQ en bas de la page',
                    'fields' => [
                        ['key'=>$p.'faq','label'=>'Questions et réponses','type'=>'list','default'=>$d['faq'],
                         'columns'=>[
                            ['k'=>'q','label'=>'Question'],
                            ['k'=>'a','label'=>'Réponse','type'=>'textarea'],
                         ],
                         'help'=>'Les questions sont désormais modifiables, pas seulement les réponses.'],
                    ],
                ],
            ],
        ];
    }
    // Pages panne, aides, estimation, rendez-vous, entretien (includes/content_pages.php).
    $pages += content_pages_catalog();
    return $pages;
}

/** Une page du catalogue, ou null. */
function admin_catalog_page(string $id): ?array
{
    return admin_page_catalog()[$id] ?? null;
}

/** Tous les champs d'une page, à plat. */
function admin_catalog_fields(string $pageId): array
{
    $page = admin_catalog_page($pageId);
    if (!$page) return [];
    $out = [];
    foreach ($page['sections'] as $s) foreach ($s['fields'] as $f) $out[$f['key']] = $f;
    return $out;
}

/** Noms des champs de formulaire d'une page. */
function admin_catalog_keys(string $pageId): array
{
    return array_keys(admin_catalog_fields($pageId));
}

/* ═══════════════════════════════════════════════════
   CHAMPS DE TYPE LISTE

   Une liste est un tableau de lignes enregistré en JSON dans un seul
   réglage. Une liste à une colonne stocke des textes simples ; à
   plusieurs colonnes, chaque ligne est un tableau de valeurs dans
   l'ordre des colonnes. Cette forme est exactement celle qu'attend le
   site à l'affichage, il n'y a donc aucune conversion au rendu.
═══════════════════════════════════════════════════ */

function admin_field_is_list(array $f): bool
{
    return ($f['type'] ?? '') === 'list';
}

/** Interrupteur oui/non, commun à tout le site : il ne se personnalise pas par zone. */
function admin_field_is_toggle(array $f): bool
{
    return ($f['type'] ?? '') === 'toggle';
}

/** Colonnes d'une liste. Une liste sans colonnes déclarées en a une seule. */
function admin_list_columns(array $f): array
{
    return $f['columns'] ?? [['k' => 'v', 'label' => $f['label'] ?? 'Valeur']];
}

/** Lignes réellement enregistrées, ou null si la liste n'a jamais été modifiée. */
function admin_list_stored(array $f): ?array
{
    $raw = raw_setting($f['key']);
    if ($raw === '') return null;
    $d = json_decode($raw, true);
    return is_array($d) ? $d : null;
}

/** Lignes affichées : celles enregistrées, sinon celles d'origine. */
function admin_list_rows(array $f): array
{
    $stored = admin_list_stored($f);
    if ($stored !== null) return $stored;
    // Hors zone la valeur d'origine vient du code ; en zone, du site global.
    if (zone_ctx_id() > 0) {
        $prev = zone_context();
        set_zone_context(null);
        $global = admin_list_stored($f);
        set_zone_context($prev);
        if ($global !== null) return $global;
    }
    return is_array($f['default'] ?? null) ? $f['default'] : [];
}

/** Enregistre des lignes. Une liste vidée revient à la valeur héritée. */
function admin_list_save(array $f, array $rows): bool
{
    $cols  = admin_list_columns($f);
    $multi = count($cols) > 1;
    $clean = [];
    foreach ($rows as $row) {
        if ($multi) {
            $vals = []; $typed = '';
            foreach ($cols as $c) {
                $v = trim((string)($row[$c['k']] ?? ''));
                $vals[] = $v;
                if (($c['type'] ?? 'text') !== 'select') $typed .= $v;   // une liste déroulante a toujours une valeur
            }
            if ($typed === '') continue;                      // ligne entièrement vide : ignorée
            $clean[] = $vals;
        } else {
            $v = trim((string)(is_array($row) ? reset($row) : $row));
            if ($v !== '') $clean[] = $v;
        }
    }
    $before = raw_setting($f['key']);
    $after  = $clean === [] ? '' : (string)json_encode($clean, JSON_UNESCAPED_UNICODE);
    if ($after === $before) return false;
    set_setting($f['key'], $after);
    return true;
}

/** Contenu d'une liste sous forme de texte, pour la recherche et l'aperçu. */
function admin_list_flatten(array $f): string
{
    $out = [];
    foreach (admin_list_rows($f) as $row) {
        $out[] = is_array($row) ? implode(' · ', array_map('strval', $row)) : (string)$row;
    }
    return implode(' · ', $out);
}

/**
 * Ce champ peut-il être modifié directement sur la page rendue ?
 *
 * Non pour les valeurs qui ne sortent pas en texte visible : celles placées
 * dans un attribut ou une balise (titres Google, image de partage, liens,
 * textes d'exemple), celles que le site découpe avant affichage (listes
 * séparées par des barres verticales), et celles rangées dans un bloc JSON.
 * Ces champs restent modifiables depuis les écrans de formulaire.
 */
function admin_field_is_inline(array $f): bool
{
    if (array_key_exists('inline', $f)) return (bool)$f['inline'];
    if (admin_field_is_list($f) || admin_field_is_toggle($f)) return false;
    $k = $f['key'];
    foreach (['meta_title','meta_desc','meta_description','og_','_url','_cities','_tags','_placeholder'] as $pat) {
        if (str_contains($k, $pat)) return false;
    }
    return true;
}

/**
 * Les clés modifiables directement sur la page, tous écrans confondus.
 * Le résultat dépend des valeurs enregistrées : purger après une écriture.
 */
function admin_inline_keys(bool $flush = false): array
{
    static $keys = null;
    if ($flush) { $keys = null; return []; }
    if ($keys !== null) return $keys;
    $keys = [];
    foreach (admin_page_catalog() as $page) {
        foreach ($page['sections'] as $s) {
            foreach ($s['fields'] as $f) {
                if (!admin_field_is_inline($f)) continue;
                // Un texte contenant une variable comme {ville} ne s'affiche pas tel
                // qu'il est stocké : l'éditer sur la page figerait la valeur résolue.
                if (str_contains(admin_field_raw($f).(string)($f['default'] ?? ''), '{')) continue;
                $keys[$f['key']] = $f;
            }
        }
    }
    return $keys;
}

/**
 * Bloc JSON tel qu'enregistré, variables de lieu comprises. get_json_setting()
 * les remplace par leur valeur : relire puis réenregistrer par ce biais
 * figerait « dans le Jura » à la place de {en_region} pour toutes les zones.
 */
function admin_json_block_plain(string $blob): array
{
    $d = json_decode(setting_plain($blob), true);
    return is_array($d) ? $d : [];
}

/**
 * Valeur réellement saisie pour ce champ, sans repli.
 * Vide signifie que le site affiche la valeur héritée.
 */
function admin_field_raw(array $f): string
{
    if (admin_field_is_list($f)) return raw_setting($f['key']);
    if (admin_field_is_toggle($f)) return global_setting($f['key'], (string)($f['default'] ?? '0'));
    if (!empty($f['json'])) {
        [$blob, $sub] = $f['json'];
        return trim((string)(admin_json_block_plain($blob)[$sub] ?? ''));
    }
    return raw_setting($f['key']);
}

/** Ce que le visiteur voit aujourd'hui pour ce champ. */
function admin_field_shown(array $f): string
{
    if (admin_field_is_list($f)) return admin_list_flatten($f);
    if (admin_field_is_toggle($f)) return admin_field_raw($f);
    $default = (string)($f['default'] ?? '');
    if (!empty($f['json'])) {
        $raw = admin_field_raw($f);
        return $raw !== '' ? $raw : $default;
    }
    return setting($f['key'], $default);
}

/** Enregistre le champ. Retourne true si quelque chose a changé. */
function admin_field_save(array $f, string $value): bool
{
    // Une liste s'enregistre par admin_list_save(), qui reçoit des lignes.
    if (admin_field_is_list($f)) return false;
    $value = trim($value);
    if ($value === admin_field_raw($f)) return false;
    if (admin_field_is_toggle($f)) {
        // Écrit la valeur globale, quelle que soit la zone en cours d'édition.
        $zone = zone_context();
        set_zone_context(null);
        set_setting($f['key'], $value === '1' ? '1' : '0');
        set_zone_context($zone);
        return true;
    }
    if (!empty($f['json'])) {
        [$blob, $sub] = $f['json'];
        $data = admin_json_block_plain($blob);
        $data[$sub] = $value;
        set_json_setting($blob, $data);
    } else {
        set_setting($f['key'], $value);
    }
    admin_inline_keys(true);   // une variable a pu apparaître ou disparaître
    return true;
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
                $value = admin_field_shown($f);
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
