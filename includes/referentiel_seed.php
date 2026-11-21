<?php
declare(strict_types=1);
/**
 * Référentiel de départ : appareils, marques, modèles, types de panne et phrases toutes prêtes.
 *
 * Chargé UNE fois dans la table preset_items (migration v15.49), puis modifiable par le
 * dispatcher dans « Listes prédéfinies ». Ce fichier n'est plus relu ensuite : le modifier ne
 * change pas les listes déjà en base.
 *
 * Marques et modèles : les plus répandus en France, d'après les gammes des fabricants. Les
 * modèles sont une base de départ, à compléter ; quand un modèle n'est pas certain, seule la
 * marque est listée.
 *
 * Clés de métier : celles de intervention_category_config().
 */
return [
    // Appareils par métier.
    'equipement' => [
        'electricite' => ['Tableau électrique', 'Prises et interrupteurs', 'Éclairage', 'Volet roulant électrique',
                          'Interphone / visiophone', 'Portail motorisé', 'Radiateur électrique', 'VMC'],
        'chauffage'   => ['Chaudière gaz', 'Chaudière fioul', 'Pompe à chaleur air/eau', 'Poêle à granulés',
                          'Radiateur électrique', 'Plancher chauffant', 'Thermostat'],
        'plomberie'   => ['Chauffe-eau électrique', 'Chauffe-eau thermodynamique', 'Chauffe-bain gaz',
                          'WC et mécanisme de chasse', 'Robinetterie', 'Broyeur WC', 'Adoucisseur'],
        'climatisation' => ['Climatisation / PAC air-air', 'Pompe à chaleur air/eau', 'VMC'],
        'debouchage'  => ['WC', 'Évier / lavabo', 'Douche / baignoire', 'Canalisation extérieure / regard'],
    ],

    // Marques par appareil, avec leurs modèles connus (liste vide = modèles à compléter).
    'marque' => [
        'Chaudière gaz' => [
            'Saunier Duval' => ['ThemaPlus Condens', 'Thema Condens', 'Isotwin Condens', 'SemiaCondens'],
            'Frisquet'      => ['Hydroconfort Évolution', 'Prestige Condensation', 'Hydromotrix Évolution'],
            'ELM Leblanc'   => ['Mégalis Condens', 'Égalis', 'Acléis'],
            'Chaffoteaux'   => ['Pigma Ultra', 'Talia Green', 'Serelia'],
            'De Dietrich'   => ['Naneo', 'MCR3', 'Vivadens', 'Innovens'],
            'Viessmann'     => ['Vitodens 050-W', 'Vitodens 100-W', 'Vitodens 111-W', 'Vitodens 200-W'],
            'Vaillant'      => ['ecoTEC plus', 'ecoTEC pure', 'ecoTEC exclusive'],
            'Atlantic'      => ['Naema 2', 'Idra Condens'],
            'Bosch'         => ['Condens 2300i', 'Condens 5300i', 'Condens 7000i'],
            'Ariston'       => ['Clas One', 'Genus One', 'Alteas One'],
            'Geminox'       => [],
            'Chappée'       => [],
            'Baxi'          => ['Luna'],
            'Ferroli'       => [],
            'Beretta'       => [],
        ],
        'Chaudière fioul' => [
            'Viessmann'   => ['Vitoladens 300-C', 'Vitorondens 200-T'],
            'De Dietrich' => ['GTU C'],
            'Atlantic'    => ['Perfinox Condens'],
            'Bosch'       => ['Olio Condens 7000 F'],
            'Buderus'     => ['Logano'],
            'Chappée'     => [],
            'Frisquet'    => [],
            'Geminox'     => [],
            'ELM Leblanc' => [],
        ],
        'Pompe à chaleur air/eau' => [
            'Atlantic'            => ['Alféa Excellia', 'Alféa Extensa'],
            'Daikin'              => ['Altherma 3'],
            'Mitsubishi Electric' => ['Ecodan'],
            'Panasonic'           => ['Aquarea'],
            'Hitachi'             => ['Yutaki'],
            'Viessmann'           => ['Vitocal 150-A', 'Vitocal 200-S'],
            'Saunier Duval'       => ['Genia Air'],
            'Vaillant'            => ['aroTHERM plus'],
            'De Dietrich'         => ['Alezio'],
            'Toshiba'             => ['Estia'],
            'LG'                  => ['Therma V'],
            'Samsung'             => ['EHS Mono'],
            'Bosch'               => ['Compress'],
        ],
        'Climatisation / PAC air-air' => [
            'Daikin'              => ['Perfera', 'Emura', 'Sensira', 'Comfora'],
            'Mitsubishi Electric' => ['MSZ-LN', 'MSZ-AP', 'MSZ-HR'],
            'Atlantic'            => [],
            'Fujitsu'             => ['Takao'],
            'Toshiba'             => ['Seiya', 'Shorai', 'Haori'],
            'Panasonic'           => ['Etherea'],
            'LG'                  => ['Artcool'],
            'Samsung'             => ['WindFree'],
            'Hitachi'             => [],
            'Gree'                => [],
            'Midea'               => [],
            'Airwell'             => [],
        ],
        'Poêle à granulés' => [
            'MCZ' => [], 'Edilkamin' => [], 'Palazzetti' => [], 'Invicta' => [], 'Jøtul' => [], 'Supra' => [], 'Godin' => [],
        ],
        'Radiateur électrique' => [
            'Atlantic' => ['Divali', 'Galapagos', 'Agilia'],
            'Thermor'  => ['Équateur', 'Ovation', 'Bilbao', 'Emotion'],
            'Noirot'   => ['Bellagio', 'Calidou'],
            'Acova'    => ['Atoll'],
            'Sauter'   => [], 'Campa' => [], 'Airelec' => [],
        ],
        'Thermostat' => [
            'Netatmo' => [], 'tado°' => [], 'Delta Dore' => ['Tybox'], 'Honeywell' => [], 'Somfy' => [],
        ],
        'Chauffe-eau électrique' => [
            'Atlantic'    => ['Chauffeo', 'Zeneo', 'Vizengo'],
            'Thermor'     => ['Duralis', 'Steatis'],
            'Ariston'     => ['Velis', 'Lydos'],
            'De Dietrich' => [],
            'Chaffoteaux' => [],
            'Sauter'      => [],
        ],
        'Chauffe-eau thermodynamique' => [
            'Atlantic'      => ['Explorer', 'Calypso'],
            'Thermor'       => ['Aéromax'],
            'Ariston'       => ['Nuos'],
            'De Dietrich'   => ['Kaliko'],
            'Saunier Duval' => ['Magna Aqua'],
            'Viessmann'     => ['Vitocal 060-A'],
        ],
        'Chauffe-bain gaz' => [
            'Saunier Duval' => ['Opalia'],
            'ELM Leblanc'   => ['Onéa'],
            'Vaillant'      => ['atmoMAG'],
            'Chaffoteaux'   => [],
        ],
        'WC et mécanisme de chasse' => [
            'Geberit' => [], 'Grohe' => [], 'Siamp' => [], 'Wirquin' => [], 'Jacob Delafon' => [], 'Porcher' => [], 'Ideal Standard' => [],
        ],
        'Robinetterie' => [
            'Grohe' => [], 'Hansgrohe' => [], 'Jacob Delafon' => [], 'Ideal Standard' => [], 'Roca' => [],
        ],
        'Broyeur WC' => [
            'SFA' => ['Sanibroyeur', 'Saniplus', 'Sanicompact'],
            'Watermatic' => [],
        ],
        'Adoucisseur' => [
            'Culligan' => [], 'BWT' => [], 'Permo' => [],
        ],
        'Tableau électrique' => [
            'Legrand'            => ['Drivia', 'XL3'],
            'Schneider Electric' => ['Resi9', 'Acti9'],
            'Hager'              => ['Gamma', 'Volta'],
            'ABB'                => [], 'Siemens' => [],
        ],
        'Interphone / visiophone' => [
            'Legrand' => [], 'Urmet' => [], 'Aiphone' => [], 'Comelit' => [], 'Somfy' => [], 'Extel' => [],
        ],
        'Portail motorisé' => [
            'Somfy' => [], 'Came' => [], 'Nice' => [], 'BFT' => [], 'Faac' => [],
        ],
        'Volet roulant électrique' => [
            'Somfy' => [], 'Bubendorff' => [], 'Nice' => [], 'Simu' => [],
        ],
        'VMC' => [
            'Aldes'     => ['EasyHome', 'Bahia'],
            'Atlantic'  => ['Duocosy', 'Autocosy'],
            'Unelvent'  => [],
            'France Air' => [],
            'Helios'    => [],
        ],
    ],

    // Types de panne fréquents, par métier.
    'panne' => [
        'electricite' => ['Disjoncteur général qui saute', 'Disjoncteur divisionnaire qui saute', 'Différentiel qui déclenche',
                          'Coupure totale de courant', 'Une partie du logement sans courant', 'Prise ou interrupteur hors service',
                          'Éclairage hors service', 'Odeur de brûlé / échauffement', 'Tableau vétuste à remplacer',
                          'Mise en sécurité / mise aux normes', 'Volet roulant bloqué', 'Interphone ou visiophone en panne',
                          'Portail motorisé bloqué'],
        'chauffage'   => ['Plus de chauffage', 'Plus d\'eau chaude', 'Chaudière en défaut (code erreur)',
                          'Pression basse / perte de pression', 'Chaudière qui se met en sécurité', 'Radiateurs froids ou à purger',
                          'Bruit anormal', 'Fuite sur la chaudière', 'Odeur de gaz (urgence)', 'Thermostat qui ne répond plus',
                          'Pompe à chaleur en défaut', 'Pompe à chaleur qui ne chauffe plus', 'Entretien annuel'],
        'plomberie'   => ['Fuite d\'eau', 'Fuite sous évier ou lavabo', 'Chasse d\'eau qui coule en continu', 'Robinet qui goutte',
                          'Plus d\'eau chaude (chauffe-eau)', 'Groupe de sécurité qui fuit', 'Chauffe-eau qui fuit',
                          'Dégât des eaux / recherche de fuite', 'Pression d\'eau faible', 'Canalisation gelée', 'Broyeur en panne'],
        'debouchage'  => ['WC bouché', 'Évier bouché', 'Douche ou baignoire bouchée', 'Canalisation principale bouchée',
                          'Remontée d\'eaux usées', 'Mauvaises odeurs'],
        'climatisation' => ['Ne refroidit plus', 'Ne chauffe plus (réversible)', 'Fuite d\'eau sur l\'unité intérieure',
                            'Code erreur affiché', 'Bruit anormal', 'Télécommande hors service', 'Mauvaises odeurs', 'Entretien annuel'],
    ],

    // Phrases de diagnostic, par métier (le technicien les ajoute d'un appui).
    'phrase_diag' => [
        'electricite' => ['Défaut d\'isolement sur un circuit.', 'Disjoncteur divisionnaire défectueux.', 'Interrupteur différentiel défectueux.',
                          'Surcharge du circuit.', 'Court-circuit sur une prise.', 'Appareil électroménager en défaut, à l\'origine du déclenchement.',
                          'Connexion desserrée ayant chauffé dans le tableau.', 'Installation vétuste, mise en sécurité conseillée.'],
        'chauffage'   => ['Pression du circuit trop basse.', 'Thermostat d\'ambiance défectueux.', 'Électrode d\'allumage encrassée.',
                          'Circulateur bloqué.', 'Vase d\'expansion dégonflé.', 'Carte électronique en défaut.', 'Sonde de température défectueuse.',
                          'Corps de chauffe entartré.', 'Radiateurs emboués, purge ou désembouage nécessaire.', 'Défaut d\'évacuation des fumées.'],
        'plomberie'   => ['Fuite sur raccord.', 'Joint usé.', 'Mécanisme de chasse défectueux.', 'Robinet flotteur défectueux.',
                          'Groupe de sécurité entartré.', 'Résistance du chauffe-eau hors service.', 'Thermostat du chauffe-eau défectueux.',
                          'Cuve du chauffe-eau percée, remplacement nécessaire.', 'Cartouche du mitigeur usée.'],
        'debouchage'  => ['Bouchon de papier / lingettes dans la canalisation.', 'Siphon obstrué.', 'Canalisation entartrée.',
                          'Racines dans la canalisation extérieure.', 'Pente insuffisante de l\'évacuation.'],
        'climatisation' => ['Filtres encrassés.', 'Manque de fluide frigorigène.', 'Évacuation des condensats bouchée.',
                            'Sonde défectueuse.', 'Carte électronique en défaut.', 'Unité extérieure encrassée.'],
    ],

    // Phrases de travaux réalisés, par métier.
    'phrase_travaux' => [
        'electricite' => ['Remplacement du disjoncteur divisionnaire.', 'Remplacement de l\'interrupteur différentiel.', 'Resserrage des connexions du tableau.',
                          'Remplacement de la prise.', 'Remplacement de l\'interrupteur.', 'Recherche de panne et remise en service.',
                          'Isolement du circuit défectueux en attendant les travaux.', 'Essais réalisés : installation fonctionnelle.'],
        'chauffage'   => ['Remise en pression du circuit.', 'Remplacement du thermostat d\'ambiance.', 'Nettoyage de l\'électrode d\'allumage.',
                          'Déblocage du circulateur.', 'Regonflage du vase d\'expansion.', 'Purge des radiateurs.', 'Entretien annuel réalisé, attestation remise.',
                          'Remplacement de la sonde.', 'Essais réalisés : chauffage et eau chaude fonctionnels.'],
        'plomberie'   => ['Resserrage du raccord, fuite stoppée.', 'Remplacement du joint.', 'Remplacement du mécanisme de chasse.',
                          'Remplacement du robinet flotteur.', 'Remplacement du groupe de sécurité.', 'Remplacement de la résistance.',
                          'Remplacement de la cartouche du mitigeur.', 'Essais réalisés : plus de fuite.'],
        'debouchage'  => ['Débouchage au furet.', 'Débouchage à la pompe manuelle.', 'Démontage et nettoyage du siphon.',
                          'Hydrocurage de la canalisation.', 'Écoulement rétabli et vérifié.'],
        'climatisation' => ['Nettoyage des filtres.', 'Débouchage de l\'évacuation des condensats.', 'Contrôle d\'étanchéité.',
                            'Recharge en fluide frigorigène.', 'Nettoyage de l\'unité extérieure.', 'Essais réalisés : climatisation fonctionnelle.'],
    ],
];
