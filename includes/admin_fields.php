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
function admin_page_catalog(): array
{
    $reg  = company_regions();
    $name = company_name();

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
                    'link'  => ['admin/zones.php', 'Modifier les régions et leurs villes'],
                    'fields' => [
                        ['key'=>'zp_eyebrow','json'=>['zones_page_settings','eyebrow'],'label'=>'Surtitre','type'=>'text','default'=>'Zones d\'intervention'],
                        ['key'=>'zp_title','json'=>['zones_page_settings','title'],'label'=>'Titre','type'=>'text','default'=>'Nous intervenons partout en'],
                        ['key'=>'zp_title_hl','json'=>['zones_page_settings','title_hl'],'label'=>'Fin du titre, en couleur','type'=>'text','default'=>'Île-de-France & Occitanie'],
                        ['key'=>'zp_lead','json'=>['zones_page_settings','lead'],'label'=>'Phrase d\'accroche','type'=>'textarea','default'=>'Des techniciens qualifiés disponibles 24h/24 et 7j/7 sur l\'ensemble de nos zones. Délai d\'intervention garanti.'],
                    ],
                ],
                [
                    'id'    => 'seo',
                    'label' => 'Référencement de la page',
                    'seen'  => 'Le titre affiché dans Google',
                    'fields' => [
                        ['key'=>'zones_meta_title','label'=>'Titre Google','type'=>'text','default'=>'Zones d\'intervention | '.$name],
                        ['key'=>'zones_meta_desc','label'=>'Description Google','type'=>'textarea','default'=>'EMAE intervient en Île-de-France et Occitanie — électricité, plomberie, chauffage, climatisation. Urgence 24h/7j.'],
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
                        ['key'=>'google_mybusiness_url','label'=>'Lien vers votre fiche Google','type'=>'text','default'=>'','help'=>'Active le bouton « Laisser un avis sur Google ». Laissez vide pour le masquer.'],
                    ],
                ],
                [
                    'id'    => 'seo',
                    'label' => 'Référencement de la page',
                    'seen'  => 'Le titre affiché dans Google',
                    'fields' => [
                        ['key'=>'avis_meta_title','label'=>'Titre Google','type'=>'text','default'=>'Avis clients | '.$name],
                        ['key'=>'avis_meta_desc','label'=>'Description Google','type'=>'textarea','default'=>'Découvrez les avis de nos clients — note de '.setting('schema_rating_value','4.6').'/5 sur '.setting('schema_review_count','120').' avis vérifiés.'],
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
                        ['key'=>'contact_badge_2','label'=>'Pastille 2','type'=>'text','default'=>'Réponse sous 30 min'],
                    ],
                ],
                [
                    'id'    => 'form',
                    'label' => 'Formulaire',
                    'seen'  => 'Le formulaire de la page Contact',
                    'fields' => [
                        ['key'=>'home_quote_city_placeholder','label'=>'Exemple dans le champ Ville','type'=>'text','default'=>'Meaux, Paris…'],
                        ['key'=>'contact_zone_tags','label'=>'Villes listées sous le formulaire','type'=>'textarea','default'=>'Paris (75)|Meaux (77)|Versailles (78)|Évry (91)|Nanterre (92)|Saint-Denis (93)|Créteil (94)|Cergy (95)|Toulouse|Montpellier|Nîmes|Occitanie','help'=>'Séparez chaque ville par une barre verticale |'],
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
                        ['key'=>'quote_hero_lead','label'=>'Phrase d\'accroche','type'=>'textarea','default'=>'Remplissez ce formulaire. Un technicien vous rappelle sous 30 minutes avec un chiffrage clair, sans engagement.'],
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
                        ['key'=>'reals_page_lead','label'=>'Phrase d\'accroche','type'=>'textarea','default'=>'Des interventions propres et documentées en '.$reg.'.'],
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
                        ['key'=>'svc_offer_label','label'=>'Surtitre de l\'offre','type'=>'text','default'=>'Notre offre'],
                        ['key'=>'svc_interv_label','label'=>'Interventions — surtitre','type'=>'text','default'=>'Nos interventions'],
                        ['key'=>'svc_interv_title','label'=>'Interventions — titre','type'=>'text','default'=>'Ce que nous'],
                        ['key'=>'svc_interv_title_hl','label'=>'Interventions — fin en couleur','type'=>'text','default'=>'faisons'],
                        ['key'=>'svc_process_label','label'=>'Méthode — surtitre','type'=>'text','default'=>'Notre méthode'],
                        ['key'=>'svc_process_title','label'=>'Méthode — titre','type'=>'text','default'=>'Intervention en'],
                        ['key'=>'svc_process_title_hl','label'=>'Méthode — fin en couleur','type'=>'text','default'=>'3 étapes'],
                        ['key'=>'svc_faq_label','label'=>'FAQ — surtitre','type'=>'text','default'=>'FAQ'],
                        ['key'=>'svc_faq_title','label'=>'FAQ — titre','type'=>'text','default'=>'Questions'],
                        ['key'=>'svc_faq_title_hl','label'=>'FAQ — fin en couleur','type'=>'text','default'=>'fréquentes'],
                        ['key'=>'svc_zones_label','label'=>'Zones — surtitre','type'=>'text','default'=>'Zone d\'intervention'],
                        ['key'=>'svc_zones_title','label'=>'Zones — titre','type'=>'text','default'=>'Zones couvertes'],
                        ['key'=>'svc_page_btn_devis','label'=>'Bouton devis','type'=>'text','default'=>'Devis gratuit'],
                        ['key'=>'svc_form_label','label'=>'Formulaire — surtitre','type'=>'text','default'=>'Devis gratuit'],
                        ['key'=>'svc_form_tag','label'=>'Formulaire — étiquette','type'=>'text','default'=>'Votre technicien'],
                        ['key'=>'svc_form_title','label'=>'Formulaire — titre','type'=>'text','default'=>'Devis gratuit'],
                    ],
                ],
                [
                    'id'    => 'zones_svc',
                    'label' => 'Zones affichées sur ces pages',
                    'seen'  => 'Les deux encarts de couverture géographique',
                    'fields' => [
                        ['key'=>'zone_idf_text','label'=>'Île-de-France — texte','type'=>'text','default'=>'Paris et toute la région.'],
                        ['key'=>'zone_idf_cities','label'=>'Île-de-France — villes','type'=>'textarea','default'=>'Paris (75)|Meaux (77)|Versailles (78)|Évry (91)|Nanterre (92)|Saint-Denis (93)|Créteil (94)|Cergy (95)','help'=>'Séparez chaque ville par une barre verticale |'],
                        ['key'=>'zone_occ_text','label'=>'Occitanie — texte','type'=>'text','default'=>'Toulouse et toute la région.'],
                        ['key'=>'zone_occ_cities','label'=>'Occitanie — villes','type'=>'textarea','default'=>'Toulouse (31)|Montpellier (34)|Nîmes (30)|Perpignan (66)|Béziers (34)|Narbonne (11)'],
                    ],
                ],
                [
                    'id'    => 'faq_elec',
                    'label' => 'FAQ Électricité',
                    'seen'  => 'Les réponses affichées en bas de la page Électricité',
                    'note'  => 'Les questions elles-mêmes sont figées dans le code ; seules les réponses sont modifiables ici.',
                    'fields' => [
                        ['key'=>'faq_elec_1_a','label'=>'Délai d\'intervention en urgence','type'=>'textarea','default'=>'En urgence, nous intervenons en moins de 2h en Île-de-France. Le délai est confirmé au téléphone selon votre zone.'],
                        ['key'=>'faq_elec_2_a','label'=>'Mise aux normes','type'=>'textarea','default'=>'Oui, nous réalisons la mise en conformité complète selon les normes en vigueur.'],
                        ['key'=>'faq_elec_3_a','label'=>'Types de bâtiments','type'=>'textarea','default'=>'Oui, logements, commerces, bureaux et bâtiments techniques.'],
                        ['key'=>'faq_elec_4_a','label'=>'Devis gratuit','type'=>'textarea','default'=>'Oui, devis gratuit et sans engagement avant toute intervention.'],
                    ],
                ],
                [
                    'id'    => 'faq_plomb',
                    'label' => 'FAQ Plomberie',
                    'seen'  => 'Les réponses affichées en bas de la page Plomberie',
                    'fields' => [
                        ['key'=>'faq_plomb_1_a','label'=>'Urgence fuite','type'=>'textarea','default'=>'Oui, disponible '.company_hours().'. Appelez-nous pour une intervention immédiate.'],
                        ['key'=>'faq_plomb_2_a','label'=>'Chauffe-eau','type'=>'textarea','default'=>'Oui, diagnostic, remplacement et mise en service de tous types de chauffe-eau.'],
                        ['key'=>'faq_plomb_3_a','label'=>'Contrat d\'entretien','type'=>'textarea','default'=>'Oui, contrats de maintenance préventive annuels disponibles.'],
                        ['key'=>'faq_plomb_4_a','label'=>'Que faire en cas de dégât des eaux','type'=>'textarea','default'=>'Coupez l\'arrivée d\'eau principale et appelez-nous immédiatement.'],
                    ],
                ],
                [
                    'id'    => 'faq_chauf',
                    'label' => 'FAQ Chauffage',
                    'seen'  => 'Les réponses affichées en bas de la page Chauffage',
                    'fields' => [
                        ['key'=>'faq_chauf_1_a','label'=>'Types de chaudières','type'=>'textarea','default'=>'Oui, sur tous types de chaudières : gaz, fioul, électrique et condensation.'],
                        ['key'=>'faq_chauf_2_a','label'=>'Pompes à chaleur','type'=>'textarea','default'=>'Oui, PAC air/air, air/eau — installation, entretien et dépannage.'],
                        ['key'=>'faq_chauf_3_a','label'=>'Entretien annuel','type'=>'textarea','default'=>'Oui, contrat d\'entretien annuel réglementaire avec rapport d\'intervention.'],
                        ['key'=>'faq_chauf_4_a','label'=>'Panne en hiver','type'=>'textarea','default'=>'Appelez-nous immédiatement — priorité absolue aux urgences de chauffage en hiver.'],
                    ],
                ],
                [
                    'id'    => 'faq_clim',
                    'label' => 'FAQ Climatisation',
                    'seen'  => 'Les réponses affichées en bas de la page Climatisation',
                    'fields' => [
                        ['key'=>'faq_clim_1_a','label'=>'Types de systèmes','type'=>'textarea','default'=>'Oui, splits, multi-splits, gainables et systèmes CVC.'],
                        ['key'=>'faq_clim_2_a','label'=>'Fourniture et pose','type'=>'textarea','default'=>'Oui, fourniture, pose et mise en service avec conseil adapté.'],
                        ['key'=>'faq_clim_3_a','label'=>'Fréquence d\'entretien','type'=>'textarea','default'=>'Idéalement avant chaque saison (printemps et automne) pour garantir les performances.'],
                        ['key'=>'faq_clim_4_a','label'=>'Locaux professionnels','type'=>'textarea','default'=>'Oui, commerces, bureaux, restaurants — intervention compatible avec votre exploitation.'],
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
                    'link'  => ['admin/site_identity.php', 'Modifier raison sociale, SIRET et adresse'],
                    'fields' => [
                        ['key'=>'ml_directeur','label'=>'Directeur de la publication','type'=>'text','default'=>$name],
                        ['key'=>'ml_hebergeur','label'=>'Hébergeur du site','type'=>'textarea','default'=>'o2switch — 222-224 Boulevard Gustave Flaubert, 63000 Clermont-Ferrand — www.o2switch.fr'],
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
}

/** URL publique d'une page du catalogue, en mode édition visuelle. */
function admin_visual_url(string $pageId): string
{
    $page = admin_catalog_page($pageId);
    if (!$page) return url_for('admin/index.php');
    $url = route_url((string)$page['route']);
    return $url . (str_contains($url, '?') ? '&' : '?') . 'admin_edit=1';
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
    if (!empty($f['json'])) return false;
    $k = $f['key'];
    foreach (['meta_title','meta_desc','meta_description','og_','_url','_cities','_tags','_placeholder'] as $pat) {
        if (str_contains($k, $pat)) return false;
    }
    return true;
}

/** Les clés modifiables directement sur la page, tous écrans confondus. */
function admin_inline_keys(): array
{
    static $keys = null;
    if ($keys !== null) return $keys;
    $keys = [];
    foreach (admin_page_catalog() as $page) {
        foreach ($page['sections'] as $s) {
            foreach ($s['fields'] as $f) if (admin_field_is_inline($f)) $keys[$f['key']] = $f;
        }
    }
    return $keys;
}

/**
 * Valeur réellement saisie pour ce champ, sans repli.
 * Vide signifie que le site affiche la valeur héritée.
 */
function admin_field_raw(array $f): string
{
    if (!empty($f['json'])) {
        [$blob, $sub] = $f['json'];
        return trim((string)(get_json_setting($blob, [])[$sub] ?? ''));
    }
    return raw_setting($f['key']);
}

/** Ce que le visiteur voit aujourd'hui pour ce champ. */
function admin_field_shown(array $f): string
{
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
    $value = trim($value);
    if ($value === admin_field_raw($f)) return false;
    if (!empty($f['json'])) {
        [$blob, $sub] = $f['json'];
        $data = get_json_setting($blob, []);
        $data[$sub] = $value;
        set_json_setting($blob, $data);
        return true;
    }
    set_setting($f['key'], $value);
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
