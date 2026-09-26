<?php
declare(strict_types=1);
// Grille tarifaire initiale (HT, Île-de-France). Modifiable ensuite dans Dispatcher > Tarifs.
// [métier, code, désignation, unité (forfait|heure|unite|kg|pourcent), prix HT ou pourcentage]
return [
    ['commun', 'DEPL', 'Déplacement + diagnostic (Paris / petite couronne), 1re demi-heure incluse', 'forfait', 49],
    ['commun', 'MO', 'Main d\'œuvre supplémentaire', 'heure', 65],
    ['commun', 'MAJ-URG', 'Majoration urgence (intervention en moins de 2 h)', 'pourcent', 30],
    ['commun', 'MAJ-SOIR', 'Majoration soir 19 h-22 h et samedi', 'pourcent', 50],
    ['commun', 'MAJ-NUIT', 'Majoration nuit 22 h-7 h, dimanche et jours fériés', 'pourcent', 100],
    ['commun', 'DEVIS', 'Devis sur place (déduit si accepté)', 'forfait', 0],

    ['electricite', 'ELEC-PANNE', 'Recherche de panne / remise en service', 'forfait', 89],
    ['electricite', 'ELEC-DISJ', 'Remplacement disjoncteur divisionnaire (fourni)', 'unite', 79],
    ['electricite', 'ELEC-DIFF', 'Remplacement interrupteur différentiel 30 mA (fourni)', 'unite', 149],
    ['electricite', 'ELEC-BRANCH', 'Remplacement disjoncteur de branchement (fourni)', 'unite', 189],
    ['electricite', 'ELEC-PRISE', 'Remplacement prise ou interrupteur (fourni)', 'unite', 45],
    ['electricite', 'ELEC-CIRCUIT', 'Création d\'une ligne / d\'un circuit', 'unite', 180],
    ['electricite', 'ELEC-TAB2', 'Tableau électrique complet 2 rangées (fourni, posé)', 'forfait', 890],
    ['electricite', 'ELEC-TAB3', 'Tableau électrique complet 3 rangées (fourni, posé)', 'forfait', 1190],

    ['plomberie', 'PLB-FUITE-RECH', 'Recherche de fuite apparente', 'forfait', 99],
    ['plomberie', 'PLB-FUITE', 'Réparation de fuite simple (joint, raccord)', 'forfait', 89],
    ['plomberie', 'PLB-CHASSE', 'Remplacement mécanisme de chasse d\'eau (fourni)', 'unite', 119],
    ['plomberie', 'PLB-FLOTTEUR', 'Remplacement robinet flotteur (fourni)', 'unite', 89],
    ['plomberie', 'PLB-MITIGEUR', 'Pose de mitigeur (hors fourniture)', 'unite', 95],
    ['plomberie', 'PLB-GS', 'Remplacement groupe de sécurité (fourni)', 'unite', 149],
    ['plomberie', 'PLB-CE200', 'Remplacement chauffe-eau 200 L (fourni, posé)', 'forfait', 1290],

    ['debouchage', 'DEB-MANUEL', 'Débouchage manuel (WC, évier, lavabo)', 'forfait', 99],
    ['debouchage', 'DEB-FURET', 'Débouchage au furet électrique', 'forfait', 159],
    ['debouchage', 'DEB-HYDRO', 'Hydrocurage canalisation', 'forfait', 290],

    ['chauffage', 'CH-DEPANNAGE', 'Dépannage chaudière gaz (hors pièces)', 'forfait', 119],
    ['chauffage', 'CH-ENTRETIEN', 'Entretien annuel chaudière gaz', 'forfait', 110],
    ['chauffage', 'CH-CIRCU', 'Remplacement circulateur (fourni)', 'unite', 390],
    ['chauffage', 'CH-VASE', 'Remplacement vase d\'expansion (fourni)', 'unite', 290],

    ['climatisation', 'CLIM-DEPANNAGE', 'Diagnostic / dépannage climatisation', 'forfait', 119],
    ['climatisation', 'CLIM-ENTRETIEN', 'Entretien climatisation monosplit', 'forfait', 129],
    ['climatisation', 'CLIM-GAZ', 'Recharge de gaz', 'kg', 90],
];
