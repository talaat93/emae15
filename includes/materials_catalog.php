<?php
declare(strict_types=1);
// Catalogue de matériel proposé aux techniciens et aux dispatchers, par métier.
// Clé = catégorie d'intervention ('' = commun à tous). Modifiable ensuite
// depuis Dispatcher > Listes prédéfinies.
return [
    'electricite' => [
        'Disjoncteur 10A', 'Disjoncteur 16A', 'Disjoncteur 20A',
        'Disjoncteur 32A', 'Interrupteur différentiel 30mA 40A', 'Interrupteur différentiel 30mA 63A type A',
        'Disjoncteur de branchement', 'Contacteur jour/nuit', 'Télérupteur',
        'Minuterie', 'Parafoudre', 'Bornier / répartiteur',
        'Tableau électrique 1 rangée', 'Tableau électrique 2 rangées', 'Tableau électrique 3 rangées',
        'Câble R2V 3G1,5', 'Câble R2V 3G2,5', 'Câble R2V 3G6',
        'Fil H07V-U 1,5 mm²', 'Fil H07V-U 2,5 mm²', 'Gaine ICTA 20',
        'Gaine ICTA 25', 'Goulotte', 'Boîte de dérivation',
        'Boîte d\'encastrement', 'Prise de courant 16A', 'Prise 32A (cuisson)',
        'Interrupteur simple', 'Interrupteur va-et-vient', 'Bouton poussoir',
        'Douille / point lumineux', 'Ampoule LED', 'Spot LED encastrable',
        'Réglette LED', 'Détecteur de mouvement', 'Détecteur de fumée',
        'Barrette de terre', 'Piquet de terre', 'Connecteur Wago',
        'Domino',
    ],
    'plomberie' => [
        'Joint fibre', 'Joint torique', 'Joint caoutchouc',
        'Téflon', 'Filasse', 'Pâte à joint',
        'Flexible d\'alimentation', 'Robinet d\'arrêt 1/4 de tour', 'Vanne d\'arrêt',
        'Mitigeur évier', 'Mitigeur lavabo', 'Mitigeur douche',
        'Cartouche de mitigeur', 'Mécanisme de chasse d\'eau', 'Robinet flotteur',
        'Pipe WC', 'Abattant WC', 'Siphon lavabo',
        'Siphon évier', 'Bonde', 'Tube PER 16',
        'Tube multicouche 16', 'Tube cuivre 14', 'Raccord à sertir',
        'Raccord laiton', 'Tube PVC 32', 'Tube PVC 40',
        'Tube PVC 100', 'Coude PVC', 'Colle PVC',
        'Collier de fixation', 'Groupe de sécurité', 'Réducteur de pression',
        'Silicone sanitaire', 'Déboucheur / furet',
    ],
    'chauffage' => [
        'Thermocouple', 'Électrode d\'allumage', 'Vase d\'expansion',
        'Soupape de sécurité 3 bars', 'Circulateur', 'Vanne 3 voies',
        'Purgeur automatique', 'Robinet thermostatique', 'Tête thermostatique',
        'Thermostat d\'ambiance', 'Sonde de température', 'Carte électronique chaudière',
        'Échangeur', 'Pressostat', 'Kit d\'entretien chaudière',
        'Joint de brûleur', 'Filtre à boues', 'Désembouant',
        'Radiateur', 'Anode magnésium',
    ],
    'climatisation' => [
        'Gaz R32 (kg)', 'Gaz R410A (kg)', 'Liaison frigorifique 1/4-3/8',
        'Liaison frigorifique 1/4-1/2', 'Raccord flare', 'Pompe de relevage',
        'Tuyau d\'évacuation condensats', 'Filtre unité intérieure', 'Nettoyant évaporateur',
        'Support mural unité extérieure', 'Plots anti-vibratiles', 'Condensateur de démarrage',
        'Télécommande', 'Goulotte climatisation',
    ],
    'ascenseur' => [
        'Contact de porte palière', 'Serrure de porte palière', 'Galet de porte',
        'Patin de guidage', 'Courroie d\'opérateur de porte', 'Bouton de palier',
        'Bouton de cabine', 'Afficheur', 'Éclairage de cabine LED',
        'Fin de course', 'Relais', 'Fusible',
        'Contacteur de puissance', 'Câble de manœuvre', 'Huile hydraulique',
        'Barrière infrarouge', 'Carte de commande', 'Téléalarme',
        'Batterie de secours',
    ],
    '' => [
        'Cheville', 'Vis', 'Collier Rilsan',
        'Ruban adhésif isolant', 'Mastic', 'Mousse expansive',
        'Consommables divers', 'Déplacement',
    ],
];
