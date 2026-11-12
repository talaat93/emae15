<?php
declare(strict_types=1);

/* ═══════════════════════════════════════════════════
   TEXTES D'ORIGINE DES PAGES VILLE ET MÉTIER × VILLE
   Recopiés en base (table city_texts, voir includes/city_texts.php) pour les pages qui n'y
   sont pas encore ; ensuite, c'est le texte en base, modifiable dans l'admin, qui s'affiche.
   Une page qui a un texte est ouverte à Google (elle n'est plus un doublon).
   Clé 'ville' : la page de la ville (/intervention/{ville}) ; les autres : un métier.
   « Notre base de Dole » : seulement pour les villes à une vingtaine de km au plus
   (city_near_base) ; ailleurs, le temps de trajet estimé s'affiche à part (render_city_travel).
   Règles (voir passation) : uniquement des faits vérifiables (communes, axes, type de
   bâti courant, chantiers réels) ; aucun délai chiffré, aucune note, aucune certification ;
   prix jamais écrits ici (la FAQ des prix est générée depuis la page Tarifs) ;
   pas de décennale sur la plomberie (non couverte par l'attestation).
   Clés : title et h1 (facultatifs), meta (description Google, 155 caractères au plus),
   intro (texte du haut), pannes (liste), local ([titre, texte]…), faq ([q, r]…).
═══════════════════════════════════════════════════ */
function city_texts_seed(): array
{
    static $c = null;
    return $c ??= [
        'beaune' => [
            'ville' => [
                'title' => 'Électricien, plombier, chauffagiste à Beaune (21) | EMAE',
                'h1' => 'Dépannage électricité, plomberie et chauffage à Beaune et sur la Côte',
                'meta' => 'Beaune, Pommard, Meursault, Savigny : panne de courant, fuite d\'eau, chaudière en panne. Électricien, plombier, chauffagiste, prix annoncé avant de partir.',
                'intro' => 'À Beaune, une panne tombe rarement au bon moment : un disjoncteur qui saute pendant les vendanges, une fuite dans une cave voûtée, une chaudière qui s\'arrête un soir de janvier dans une maison de vigneron. Que vous habitiez à l\'intérieur des remparts, dans un lotissement de la périphérie ou dans un village de la Côte, de Savigny-lès-Beaune à Meursault, un seul numéro suffit pour l\'électricité, la plomberie, le chauffage et la climatisation. Au téléphone, nous faisons le point avec vous, puis nous vous annonçons le créneau et le prix du déplacement avant de partir.',
                'local' => [
                    ['Centre ancien et maisons de vigneron', 'Dans le centre historique comme dans les villages viticoles, beaucoup de maisons ont des murs épais, des caves voûtées et des installations électriques ou des canalisations reprises par étapes au fil des années. L\'humidité des caves use plus vite les câbles et les raccords : nous vous disons ce qui doit être refait et ce qui peut attendre.'],
                    ['Domaines, caves et commerces', 'Caveaux de dégustation, hôtels, restaurants, boutiques : pour un professionnel, une panne de froid, de chauffage ou d\'électricité touche directement l\'activité. Nous intervenons aussi pour les entreprises, avec une facture à votre nom de société.'],
                ],
                'faq' => [
                    ['Intervenez-vous dans le centre de Beaune, à l\'intérieur des remparts ?', 'Oui. Indiquez-nous au téléphone l\'adresse exacte et les possibilités de stationnement : dans les rues étroites du centre, cela nous permet de préparer le matériel et d\'arriver au plus près.'],
                    ['Travaillez-vous pour les domaines viticoles ?', 'Oui, pour l\'électricité, la plomberie, le chauffage et la climatisation des bâtiments : bureaux, caveaux, logements. Le devis est gratuit et le prix du déplacement est annoncé avant de partir.'],
                ],
            ],
            'electricien' => [
                'meta'   => 'Panne de courant, disjoncteur qui saute, tableau à refaire à Beaune ? Électricien assuré, prix du déplacement annoncé avant de partir.',
                'intro'  => 'Plus de courant dans la cuisine, un disjoncteur qui saute dès que le four démarre, une prise qui chauffe : une panne électrique n\'attend pas, et vous voulez savoir qui va venir et combien ça va coûter. Appelez-nous : nous posons les bonnes questions pour comprendre la panne, puis nous vous disons quand nous pouvons être chez vous et combien coûte le déplacement, avant de partir. Nous intervenons à Beaune comme à Pommard, Meursault ou Savigny-lès-Beaune. Sur place, le technicien établit le diagnostic, vous annonce le prix de la réparation et ne commence qu\'avec votre accord.',
                'pannes' => ['Panne de courant totale ou dans une seule pièce', 'Disjoncteur ou différentiel qui saute sans raison apparente', 'Tableau électrique ancien, à fusibles ou sans différentiel 30 mA : remplacement', 'Prise ou interrupteur qui chauffe, grésille ou noircit', 'Court-circuit après un dégât des eaux ou une infiltration', 'Ajout de prises ou de circuits (plaque de cuisson, atelier, dépendance)', 'Mise aux normes NF C 15-100 avant une vente ou une location', 'Panne électrique d\'une pompe à chaleur ou d\'un chauffage électrique'],
                'local'  => [
                    ['Des maisons anciennes, des installations anciennes', 'Dans le centre de Beaune comme dans les maisons de vigneron de la Côte, beaucoup d\'installations datent d\'avant les normes actuelles, et une cave voûtée humide est un endroit où l\'électricité vieillit mal. Nous distinguons ce qui est dangereux de ce qui peut attendre, et nous vous le disons franchement.'],
                    ['Un seul interlocuteur', 'Si la panne vient de la pompe à chaleur, du chauffe-eau ou d\'une fuite, nous traitons l\'ensemble, sans vous renvoyer vers un autre artisan.'],
                ],
                'faq'    => [
                    ['Mon disjoncteur saute dès que j\'allume un appareil : que faire ?', 'Débranchez l\'appareil concerné et réarmez le disjoncteur. S\'il saute encore sans rien de branché, ou si une prise sent le brûlé, coupez le courant au tableau et appelez-nous : c\'est le signe d\'un défaut d\'isolement ou d\'un court-circuit.'],
                    ['Faut-il refaire tout le tableau électrique d\'une maison ancienne ?', 'Pas forcément. Nous vérifions d\'abord la présence d\'une protection différentielle 30 mA et l\'état des câbles, puis nous vous proposons soit une mise en sécurité, soit un remplacement complet, avec un devis gratuit pour chacune.'],
                ],
            ],
            'plombier' => [
                'title' => 'Plombier à Beaune (21) – fuite, WC bouché, chauffe-eau | EMAE',
                'h1' => 'Plombier à Beaune : fuites, débouchages et chauffe-eau',
                'meta'   => 'Fuite d\'eau, WC ou évier bouché, chauffe-eau en panne à Beaune ? Plombier à votre écoute 7j/7, prix du déplacement annoncé avant de partir.',
                'intro'  => 'Une fuite sous l\'évier, un WC qui déborde, plus d\'eau chaude au réveil : en plomberie, chaque heure compte, surtout quand l\'eau coule. Appelez-nous : nous vous expliquons comment couper l\'eau et limiter les dégâts en attendant, puis nous vous confirmons le créneau et le prix du déplacement avant de partir. Nous intervenons à Beaune et dans les villages autour, de Chorey-les-Beaune à Meursault. Sur place, nous trouvons l\'origine de la fuite ou du bouchon et vous annonçons le prix avant toute réparation.',
                'pannes' => ['Fuite d\'eau visible ou trace d\'humidité au plafond', 'WC, évier, douche ou canalisation bouchés', 'Chauffe-eau qui ne chauffe plus ou qui fuit', 'Robinet, mitigeur ou chasse d\'eau qui coule en continu', 'Groupe de sécurité du chauffe-eau qui goutte', 'Remplacement de robinetterie ou d\'un WC', 'Canalisations anciennes à remplacer (plomb, acier galvanisé)'],
                'local'  => [
                    ['Calcaire et canalisations anciennes', 'L\'eau du secteur est calcaire : elle entartre les chauffe-eau, les groupes de sécurité et les mitigeurs, à l\'origine de nombreuses fuites. Dans les maisons anciennes du centre et des villages, on trouve encore des canalisations en plomb ou en acier galvanisé : nous vous disons s\'il faut les remplacer, devis à l\'appui.'],
                    ['Le bon geste en attendant', 'Nous vous indiquons au téléphone où couper l\'eau et comment limiter les dégâts avant notre arrivée.'],
                ],
                'faq'    => [
                    ['J\'ai une fuite : que faire en attendant le plombier ?', 'Coupez l\'arrivée d\'eau générale (souvent près du compteur ou sous l\'évier), coupez l\'électricité si l\'eau approche d\'une prise ou du tableau, épongez et prenez des photos pour votre assurance. Puis appelez-nous.'],
                    ['Mon chauffe-eau ne chauffe plus : faut-il le changer ?', 'Pas toujours : la panne vient souvent de la résistance, du thermostat ou du groupe de sécurité, qui se remplacent. Nous faisons le diagnostic et vous proposons la réparation ou le remplacement, avec un devis gratuit.'],
                ],
            ],
            'chauffagiste' => [
                'meta'   => 'Chaudière en panne, pompe à chaleur en défaut, radiateurs froids à Beaune ? Chauffagiste assuré, prix du déplacement annoncé avant de partir.',
                'intro'  => 'Une chaudière qui se met en sécurité, une pompe à chaleur qui affiche un code d\'erreur, des radiateurs froids alors que l\'hiver arrive : sans chauffage, la maison se refroidit vite. Appelez-nous : décrivez-nous la panne et le code affiché, nous vous confirmons le créneau et le prix du déplacement avant de partir. Nous intervenons à Beaune et dans les villages de la Côte, où nous avons déjà dépanné une pompe à chaleur et remplacé ses thermostats d\'ambiance. Sur place, le diagnostic et le prix de la réparation vous sont annoncés avant tout travail.',
                'pannes' => ['Chaudière gaz ou fioul en sécurité ou qui ne démarre plus', 'Pompe à chaleur en défaut, code d\'erreur ou givrage', 'Radiateurs froids, chauffage qui chauffe mal ou de façon inégale', 'Thermostat d\'ambiance ou programmation à remplacer', 'Plus d\'eau chaude avec une chaudière mixte', 'Entretien annuel de chaudière ou de pompe à chaleur', 'Remplacement de chaudière ou passage à la pompe à chaleur'],
                'local'  => [
                    ['Fioul, gaz et pompes à chaleur', 'Entre le centre de Beaune, raccordé au gaz, et les villages autour, où le fioul reste courant, les équipements sont très variés. Les pompes à chaleur se multiplient aussi : nous intervenons sur ces trois types de chauffage.'],
                    ['Un chantier près de chez vous', 'À Beaune, nous avons dépanné une pompe à chaleur et remplacé ses thermostats d\'ambiance, pour une régulation de nouveau pièce par pièce.'],
                ],
                'faq'    => [
                    ['Ma chaudière s\'est mise en sécurité : puis-je la réarmer ?', 'Vous pouvez essayer une fois en suivant la notice. Si elle se remet en sécurité, si vous sentez une odeur de gaz ou de fumée, ne réessayez pas : coupez-la et appelez-nous. En cas d\'odeur de gaz, sortez et appelez d\'abord le numéro d\'urgence gaz.'],
                    ['Ma pompe à chaleur affiche un code d\'erreur : que faire ?', 'Notez le code et la marque, puis appelez-nous : ces informations nous permettent souvent de préparer la bonne pièce. Évitez les remises à zéro répétées, qui peuvent masquer la panne.'],
                ],
            ],
            'climatisation' => [
                'meta'   => 'Climatisation en panne, qui ne refroidit plus ou qui fuit à Beaune ? Dépannage, entretien et installation, prix du déplacement annoncé avant de partir.',
                'intro'  => 'Une climatisation qui souffle de l\'air tiède, qui goutte ou qui s\'arrête en pleine chaleur : en été, la maison ou le commerce deviennent vite étouffants. Appelez-nous : nous faisons le point sur l\'appareil et la panne, puis nous vous confirmons le créneau et le prix du déplacement avant de partir. Nous intervenons à Beaune et autour, pour le dépannage, l\'entretien et l\'installation de climatisation et de pompes à chaleur réversibles.',
                'pannes' => ['Climatisation qui ne refroidit plus ou souffle de l\'air tiède', 'Unité intérieure qui goutte ou fuit', 'Appareil qui ne démarre plus ou affiche un code d\'erreur', 'Bruit anormal ou mauvaise odeur à la mise en route', 'Entretien et nettoyage des filtres et des unités', 'Installation d\'une climatisation ou d\'une pompe à chaleur air-air réversible'],
                'local'  => [
                    ['Des étés chauds en Bourgogne', 'Les épisodes de chaleur sont de plus en plus fréquents en Côte-d\'Or : un appareil entretenu avant l\'été tombe moins souvent en panne au pire moment.'],
                    ['Climatisation et chauffage', 'Une pompe à chaleur air-air rafraîchit l\'été et chauffe l\'hiver : nous vous conseillons sur le bon choix pour votre logement, devis gratuit à l\'appui.'],
                ],
                'faq'    => [
                    ['Ma climatisation coule à l\'intérieur : est-ce grave ?', 'Souvent, c\'est l\'évacuation des condensats qui est bouchée. Coupez l\'appareil pour éviter les dégâts, essuyez et appelez-nous : le nettoyage de l\'évacuation suffit dans la plupart des cas.'],
                    ['À quelle fréquence faut-il entretenir une climatisation ?', 'Un entretien par an, idéalement au printemps : nettoyage des filtres et des unités, contrôle du fonctionnement. Pendant la saison, vous pouvez aussi dépoussiérer vous-même les filtres de l\'unité intérieure.'],
                ],
            ],
        ],
        'dole' => [
            'ville' => [
                'title' => 'Électricien, plombier, chauffagiste à Dole (39) – notre base | EMAE',
                'h1' => 'Électricité, plomberie, chauffage à Dole : notre base est ici',
                'meta' => 'EMAE est basée à Dole : électricien, plombier, chauffagiste du centre ancien aux Mesnils Pasteur et à Azans. Prix annoncé avant le déplacement.',
                'intro' => 'Dole est notre base : c\'est d\'ici que partent nos techniciens, pour le centre ancien autour de la collégiale comme pour les Mesnils Pasteur, Azans ou les lotissements de Foucherans et de Choisey. Électricité, plomberie, chauffage, climatisation : un seul numéro pour toutes les pannes de la maison. Au téléphone, nous vous disons quoi faire en attendant, puis nous vous annonçons le créneau et le prix du déplacement avant de partir. Sur place, le diagnostic et le prix de la réparation vous sont donnés avant tout travail.',
                'local' => [
                    ['Un centre ancien, des quartiers récents', 'Entre les maisons de pierre du quartier de la collégiale et du canal des Tanneurs, et les immeubles et pavillons plus récents, les installations n\'ont ni le même âge ni les mêmes pannes. Nous vous disons ce qui relève de la sécurité et ce qui peut attendre.'],
                    ['Vos techniciens habitent le secteur', 'Basés à Dole, nous pouvons repasser facilement pour un contrôle après une intervention, ou enchaîner l\'électricité et la plomberie d\'un même chantier.'],
                ],
                'faq' => [
                    ['Êtes-vous vraiment installés à Dole ?', 'Oui, notre base d\'intervention est à Dole : c\'est de là que partent les techniciens pour le Jura, la Côte-d\'Or et le Doubs. Le siège administratif de la société est une adresse de domiciliation.'],
                    ['Intervenez-vous dans tous les quartiers de Dole ?', 'Oui : centre ancien, Mesnils Pasteur, Azans, Goux, et les communes autour comme Tavaux, Damparis, Foucherans, Choisey, Authume ou Brevans.'],
                ],
            ],
            'electricien' => [
                'meta'  => 'Électricien à Dole : panne de courant, disjoncteur, tableau, mise aux normes. Basés à Dole, prix du déplacement annoncé avant de partir.',
                'intro' => 'Notre base est à Dole : quand le courant saute chez vous, à Dole, Tavaux, Foucherans ou Choisey, il n\'y a pas de longue route avant d\'arriver. Appelez-nous et décrivez ce qui se passe : un disjoncteur qui ne se réarme plus, une pièce sans courant, une odeur de brûlé près d\'une prise. Nous vous disons tout de suite s\'il faut couper le courant en attendant, puis nous confirmons le créneau et le prix du déplacement. Sur place, le diagnostic et le prix de la réparation vous sont annoncés avant tout travail.',
                'local' => [
                    ['Du centre ancien aux pavillons récents', 'Dans les maisons de pierre du centre de Dole, l\'installation a souvent été reprise par morceaux au fil des années ; dans les lotissements autour, ce sont plutôt des ajouts de circuits ou des tableaux à compléter. Nous vous disons ce qui relève de la sécurité et ce qui peut attendre.'],
                    ['Votre électricien est déjà sur place', 'Basés à Dole, nous connaissons le secteur, de Damparis à Authume, et nous pouvons repasser facilement si un contrôle est nécessaire après l\'intervention.'],
                ],
                'faq'   => [
                    ['Le courant a sauté dans une seule pièce : est-ce grave ?', 'Regardez au tableau quel disjoncteur est descendu et réarmez-le. S\'il redescend aussitôt, débranchez les appareils de la pièce et réessayez : s\'il saute encore, le défaut est dans le circuit lui-même, appelez-nous.'],
                    ['Faites-vous les diagnostics avant une vente à Dole ?', 'Le diagnostic électrique de vente est réalisé par un diagnostiqueur immobilier indépendant, pas par l\'électricien. En revanche, nous réalisons les travaux de mise en sécurité demandés après ce diagnostic, avec un devis gratuit.'],
                ],
            ],
            'plombier' => [
                'meta'  => 'Plombier à Dole : fuite, WC bouché, chauffe-eau en panne. Basés à Dole, nous intervenons aussi à Tavaux et Foucherans. Prix annoncé avant de partir.',
                'intro' => 'Une fuite qui ne s\'arrête pas, un WC bouché un dimanche, un chauffe-eau qui lâche : à Dole, notre base est à deux pas. Appelez-nous : nous vous indiquons d\'abord comment couper l\'eau et limiter les dégâts, puis nous confirmons le créneau et le prix du déplacement. Nous intervenons à Dole et dans les communes autour, de Tavaux à Brevans. Sur place, nous trouvons l\'origine de la fuite ou du bouchon et vous annonçons le prix avant toute réparation.',
                'local' => [
                    ['Une eau calcaire', 'L\'eau du secteur est calcaire : le tartre use les chauffe-eau, les groupes de sécurité et les mitigeurs, et c\'est une cause fréquente de fuites. Nous vous conseillons sur l\'entretien qui prolonge la durée de vie de vos équipements.'],
                    ['Maisons anciennes et pavillons', 'Dans le centre ancien, on trouve encore d\'anciennes canalisations à surveiller ; dans les pavillons plus récents, ce sont surtout les raccords et la robinetterie. Dans les deux cas, le devis est gratuit.'],
                ],
                'faq'   => [
                    ['Mon WC est bouché : faut-il appeler tout de suite ?', 'Évitez de tirer la chasse à répétition, qui ferait déborder. Une ventouse suffit parfois ; si l\'eau ne descend toujours pas, ou si plusieurs évacuations sont touchées, le bouchon est plus loin dans la canalisation : appelez-nous.'],
                    ['Remplacez-vous les chauffe-eau à Dole ?', 'Oui, électriques ou thermodynamiques. Nous vérifions d\'abord si une réparation suffit (résistance, thermostat, groupe de sécurité), puis nous vous remettons un devis gratuit si le remplacement est nécessaire.'],
                ],
            ],
            'chauffagiste' => [
                'meta'  => 'Chauffagiste à Dole : chaudière en panne, pompe à chaleur en défaut, entretien annuel. Basés à Dole, prix du déplacement annoncé avant de partir.',
                'intro' => 'Une chaudière en sécurité un soir de janvier, une pompe à chaleur qui affiche un code d\'erreur, des radiateurs qui restent froids : à Dole, nous sommes sur place. Appelez-nous avec la marque de l\'appareil et le message affiché, nous confirmons le créneau et le prix du déplacement avant de partir. Nous intervenons à Dole et dans les communes voisines, de Foucherans à Sampans, sur chaudières gaz et fioul, pompes à chaleur et radiateurs.',
                'local' => [
                    ['Gaz en ville, fioul et pompes à chaleur autour', 'Le centre de Dole est largement chauffé au gaz ; dans les villages alentour, le fioul reste présent et de nombreuses maisons passent à la pompe à chaleur. Nous intervenons sur ces trois types d\'équipements.'],
                    ['Entretien avant l\'hiver', 'L\'entretien annuel d\'une chaudière est obligatoire : le faire en automne, c\'est éviter la panne aux premiers froids, quand tout le monde appelle en même temps.'],
                ],
                'faq'   => [
                    ['L\'entretien de chaudière est-il obligatoire ?', 'Oui, une fois par an pour les chaudières gaz, fioul et bois de 4 à 400 kW. Une attestation vous est remise : votre assureur peut la demander en cas de sinistre.'],
                    ['Ma pompe à chaleur ne chauffe plus assez par grand froid : est-ce une panne ?', 'Pas forcément : son rendement baisse quand il gèle fort et un appoint peut prendre le relais. Si elle affiche un code d\'erreur, givre en permanence ou s\'arrête, appelez-nous avec la marque et le code.'],
                ],
            ],
            'climatisation' => [
                'meta'  => 'Climatisation à Dole : dépannage, entretien et installation de clim et de pompes à chaleur réversibles. Basés à Dole, prix annoncé avant de partir.',
                'intro' => 'Une climatisation qui souffle tiède en plein mois de juillet, une unité qui goutte sur le mur : à Dole, notre base est tout près. Appelez-nous : nous faisons le point sur l\'appareil et la panne, puis nous confirmons le créneau et le prix du déplacement. Nous intervenons à Dole et autour pour le dépannage, l\'entretien et l\'installation de climatisations et de pompes à chaleur air-air réversibles, en maison comme en commerce.',
                'local' => [
                    ['Commerces et logements', 'Boutiques du centre, bureaux, maisons : une climatisation en panne pendant une vague de chaleur pèse vite sur le travail comme sur le sommeil. Nous intervenons sur ces différents usages.'],
                    ['Réversible : chaud l\'hiver, frais l\'été', 'Une pompe à chaleur air-air remplace souvent d\'anciens convecteurs électriques : nous vous conseillons sur la puissance adaptée à votre logement, devis gratuit à l\'appui.'],
                ],
                'faq'   => [
                    ['Ma climatisation coule à l\'intérieur : que faire ?', 'Coupez l\'appareil et essuyez : le plus souvent, c\'est l\'évacuation des condensats qui est bouchée. Appelez-nous, le nettoyage suffit dans la plupart des cas.'],
                    ['Installez-vous des climatisations en appartement à Dole ?', 'Oui. En copropriété, l\'unité extérieure demande souvent l\'accord du syndic : nous vous aidons à préparer la demande avec le devis et les caractéristiques de l\'appareil.'],
                ],
            ],
        ],
        'dijon' => [
            'ville' => [
                'title' => 'Électricien, plombier, chauffagiste à Dijon (21) et métropole | EMAE',
                'h1' => 'Dépannage électricité, plomberie, chauffage à Dijon et dans la métropole',
                'meta' => 'Dijon et métropole : panne de courant, fuite, chaudière, climatisation. Du centre historique aux Grésilles, prix du déplacement annoncé avant de partir.',
                'intro' => 'À Dijon, la plupart de nos interventions se font en appartement : un immeuble ancien du centre historique, une résidence des Grésilles ou de la Fontaine d\'Ouche, un logement neuf le long du tramway. En copropriété, la première question est souvent de savoir si la panne vient de votre logement ou des parties communes : nous vous aidons à faire la différence. En maison, à Chenôve, Talant ou Saint-Apollinaire, nous intervenons sur toute l\'installation. Appelez-nous : nous faisons le point, puis nous vous annonçons le créneau et le prix du déplacement avant de partir.',
                'local' => [
                    ['Centre historique et secteur sauvegardé', 'Dans le centre ancien, les travaux visibles de l\'extérieur (unité de climatisation, ventouse de chaudière) demandent souvent une autorisation : nous vous fournissons les éléments du dossier avec le devis.'],
                    ['Locataires, propriétaires, syndic', 'Qui paie quoi ? L\'entretien courant revient au locataire, le remplacement d\'un équipement hors d\'usage au propriétaire, les colonnes et la chaufferie collective au syndic. Nous vous le disons dès l\'appel.'],
                ],
                'faq' => [
                    ['Intervenez-vous partout dans la métropole de Dijon ?', 'Oui : Dijon, Chenôve, Talant, Fontaine-lès-Dijon, Longvic, Quetigny, Chevigny-Saint-Sauveur, Saint-Apollinaire, Marsannay-la-Côte et les communes autour.'],
                    ['Faut-il être présent pendant l\'intervention en appartement ?', 'Oui, ou une personne de confiance : le technicien vous explique la panne et le prix de la réparation avant de commencer, et rien n\'est fait sans votre accord.'],
                ],
            ],
            'electricien' => [
                'meta'  => 'Électricien à Dijon : panne de courant, disjoncteur, tableau, mise aux normes en maison ou en appartement. Prix du déplacement annoncé avant de partir.',
                'intro' => 'Un disjoncteur qui saute dans un appartement du centre, une coupure dans une maison de Chenôve ou de Talant, une prise qui grésille : appelez-nous et décrivez la panne. Nous vous disons s\'il faut couper le courant en attendant, puis nous confirmons le créneau et le prix du déplacement avant de partir. Nous intervenons à Dijon et dans la métropole, de Quetigny à Longvic. Sur place, le diagnostic et le prix de la réparation vous sont annoncés avant tout travail.',
                'local' => [
                    ['Immeubles anciens, immeubles des années 60', 'Entre les immeubles anciens du centre et les grands ensembles comme les Grésilles ou la Fontaine d\'Ouche, beaucoup de tableaux électriques n\'ont pas été refaits. Le tableau de votre logement est à votre charge ; les parties communes relèvent du syndic : nous vous aidons à faire la différence.'],
                    ['Mise aux normes avant de louer', 'Un logement loué doit avoir une installation électrique sans danger : nous réalisons la mise en sécurité, avec un devis gratuit.'],
                ],
                'faq'   => [
                    ['Toute la cage d\'escalier est sans courant : est-ce à moi d\'appeler ?', 'Si le problème touche les parties communes ou plusieurs logements, c\'est au syndic ou au bailleur. Si seul votre logement est privé de courant, vérifiez votre disjoncteur général et votre tableau, puis appelez-nous.'],
                    ['Intervenez-vous dans toute la métropole de Dijon ?', 'Oui : Dijon, Chenôve, Talant, Fontaine-lès-Dijon, Longvic, Quetigny, Chevigny-Saint-Sauveur, Saint-Apollinaire, Marsannay-la-Côte et les communes autour.'],
                ],
            ],
            'plombier' => [
                'meta'  => 'Plombier à Dijon : fuite, dégât des eaux, WC bouché, chauffe-eau. Maison ou appartement, prix du déplacement annoncé avant de partir.',
                'intro' => 'Une fuite qui coule chez le voisin du dessous, un WC bouché, plus d\'eau chaude : en appartement à Dijon, une fuite devient vite l\'affaire de tout l\'immeuble. Appelez-nous : nous vous indiquons comment couper l\'eau de votre logement, puis nous confirmons le créneau et le prix du déplacement avant de partir. Nous intervenons à Dijon et dans la métropole, de Fontaine-lès-Dijon à Chevigny-Saint-Sauveur.',
                'local' => [
                    ['Dégât des eaux en copropriété', 'Quand une fuite touche le voisin, il faut trouver vite si elle vient de votre logement ou d\'une colonne commune. Nous recherchons l\'origine et vous remettons un compte rendu utile pour le constat amiable et votre assurance.'],
                    ['Une eau calcaire', 'L\'eau du secteur dépose du tartre dans les chauffe-eau et la robinetterie : c\'est une cause fréquente de fuites et de pannes d\'eau chaude.'],
                ],
                'faq'   => [
                    ['Une fuite coule chez mon voisin : que faire ?', 'Coupez l\'arrivée d\'eau de votre logement, prévenez votre voisin et votre syndic, puis appelez-nous pour trouver l\'origine. Remplissez ensuite un constat amiable de dégât des eaux avec votre voisin.'],
                    ['Qui paie la réparation d\'une colonne d\'eau en immeuble ?', 'Les colonnes communes relèvent de la copropriété (syndic) ; les canalisations après le robinet d\'arrêt de votre logement sont à votre charge. Nous vous aidons à déterminer d\'où vient la fuite.'],
                ],
            ],
            'chauffagiste' => [
                'meta'  => 'Chauffagiste à Dijon : chaudière gaz en panne, pompe à chaleur, radiateurs, entretien annuel. Prix du déplacement annoncé avant de partir.',
                'intro' => 'Une chaudière gaz qui se met en sécurité, des radiateurs tièdes, une pompe à chaleur qui affiche un code d\'erreur : appelez-nous avec la marque de l\'appareil et le message affiché. Nous confirmons le créneau et le prix du déplacement avant de partir. Nous intervenons à Dijon et dans la métropole, en appartement comme en maison, sur chaudières, pompes à chaleur, radiateurs et thermostats.',
                'local' => [
                    ['Chaudières individuelles et chauffage collectif', 'Beaucoup d\'appartements dijonnais ont une chaudière gaz individuelle ; d\'autres dépendent d\'un chauffage collectif. Dans ce cas, nous intervenons sur ce qui est à vous (radiateurs, robinets thermostatiques), la chaufferie relevant du syndic.'],
                    ['Entretien obligatoire', 'L\'entretien annuel de la chaudière est obligatoire, et c\'est au locataire de le faire faire en location : nous vous remettons l\'attestation.'],
                ],
                'faq'   => [
                    ['Je suis locataire : qui paie l\'entretien de la chaudière ?', 'L\'entretien annuel courant est à la charge du locataire. Le remplacement d\'une chaudière hors d\'usage est en principe à la charge du propriétaire.'],
                    ['Un radiateur reste froid en haut : que faire ?', 'Il a probablement besoin d\'être purgé. Si la purge ne suffit pas, ou si plusieurs radiateurs sont froids, appelez-nous : il peut s\'agir d\'un manque de pression ou d\'un problème de circulateur.'],
                ],
            ],
            'climatisation' => [
                'meta'  => 'Climatisation à Dijon : dépannage, entretien, installation en appartement, maison ou commerce. Prix du déplacement annoncé avant de partir.',
                'intro' => 'Un appartement sous les toits qui devient une étuve, un commerce où la clim s\'arrête en plein été : appelez-nous et décrivez l\'appareil et la panne. Nous confirmons le créneau et le prix du déplacement avant de partir. Nous intervenons à Dijon et dans la métropole pour le dépannage, l\'entretien et l\'installation de climatisations et de pompes à chaleur air-air réversibles.',
                'local' => [
                    ['Appartements sous les toits', 'Dans les immeubles anciens du centre, les derniers étages chauffent fort en été : une climatisation réversible bien dimensionnée change le quotidien, et chauffe aussi l\'hiver.'],
                    ['Copropriété et unité extérieure', 'Poser une unité extérieure en façade ou sur un balcon demande souvent l\'accord de la copropriété : nous vous fournissons le devis et les caractéristiques nécessaires à la demande.'],
                ],
                'faq'   => [
                    ['Faut-il une autorisation pour installer une clim en appartement à Dijon ?', 'Souvent, oui : l\'accord de la copropriété pour l\'unité extérieure, et une déclaration en mairie si elle modifie l\'aspect de la façade, en particulier dans le centre historique. Nous vous aidons à préparer le dossier.'],
                    ['Ma climatisation ne refroidit plus : est-ce une recharge de gaz ?', 'Pas toujours : un filtre encrassé ou une unité extérieure obstruée donnent les mêmes symptômes. Nous vérifions avant de parler de recharge, qui suppose de trouver et réparer une fuite.'],
                ],
            ],
        ],
        'tavaux' => [
            'climatisation' => [
                'title' => 'Climatisation à Tavaux (39) – dépannage, entretien, pose | EMAE',
                'h1' => 'Climatisation à Tavaux : dépannage, entretien et installation',
                'meta' => 'Clim en panne ou qui goutte à Tavaux, Damparis, Saint-Aubin : dépannage, entretien, pose de pompe à chaleur air-air. Base de Dole, prix annoncé.',
                'intro' => 'Une climatisation qui souffle tiède, une unité intérieure qui goutte, un appareil qui affiche un code d\'erreur en pleine chaleur : à Tavaux, notre base de Dole est à moins de 10 km. Appelez-nous avec la marque de l\'appareil : nous faisons le point, puis nous vous annonçons le créneau et le prix du déplacement avant de partir. Nous intervenons à Tavaux et autour pour le dépannage, l\'entretien et l\'installation de climatisations et de pompes à chaleur air-air réversibles, chez les particuliers comme dans les locaux professionnels.',
                'local' => [
                    ['Remplacer d\'anciens convecteurs', 'Dans beaucoup de maisons, une pompe à chaleur air-air réversible remplace des convecteurs électriques : elle chauffe l\'hiver et rafraîchit l\'été. Nous dimensionnons l\'appareil selon la pièce et l\'isolation.'],
                    ['Locaux professionnels', 'Bureaux, ateliers, locaux près de l\'aéroport : nous intervenons aussi pour les professionnels, avec une facture au nom de l\'entreprise.'],
                ],
                'faq' => [
                    ['Ma clim fait de la glace sur les tuyaux : que faire ?', 'Arrêtez-la et laissez fondre. Cela vient souvent d\'un filtre très encrassé ou d\'un manque de fluide. Si le problème revient après nettoyage des filtres, appelez-nous.'],
                    ['L\'installation d\'une clim demande-t-elle une autorisation ?', 'En maison, une déclaration en mairie peut être demandée si l\'unité extérieure modifie l\'aspect de la façade ; en copropriété, il faut l\'accord du syndic. Nous vous fournissons les caractéristiques nécessaires.'],
                ],
            ],
            'plombier' => [
                'title' => 'Plombier à Tavaux (39) – fuite, débouchage, chauffe-eau | EMAE',
                'h1' => 'Plombier à Tavaux : fuites, débouchages et chauffe-eau',
                'meta' => 'Fuite, WC bouché, chauffe-eau en panne à Tavaux, Damparis, Champdivers : plombier basé à Dole, à moins de 10 km. Prix du déplacement annoncé avant.',
                'intro' => 'Une fuite sous l\'évier, un WC qui déborde, plus d\'eau chaude un matin d\'hiver : à Tavaux, notre base de Dole est à moins de 10 km. Appelez-nous : nous vous expliquons d\'abord comment couper l\'eau et limiter les dégâts, puis nous vous annonçons le créneau et le prix du déplacement avant de partir. Nous intervenons à Tavaux et autour, de Damparis à Champdivers et Molay, pour les fuites, les débouchages, les chauffe-eau et le remplacement de robinetterie.',
                'local' => [
                    ['Calcaire et chauffe-eau', 'L\'eau du secteur est calcaire : le tartre s\'accumule dans les chauffe-eau et fait goutter les groupes de sécurité. Un groupe qui coule en permanence se remplace sans changer le chauffe-eau.'],
                    ['Maisons anciennes et pavillons', 'Dans les maisons des Cités, on trouve encore d\'anciennes canalisations ; dans les pavillons récents, ce sont plutôt les raccords et les flexibles. Dans les deux cas, nous vous annonçons le prix avant de réparer.'],
                ],
                'faq' => [
                    ['Mon groupe de sécurité goutte : est-ce grave ?', 'Quelques gouttes pendant la chauffe sont normales. S\'il coule en continu, il est entartré ou défectueux : il se remplace facilement, appelez-nous.'],
                    ['Que faire si l\'eau monte dans la douche quand je tire la chasse ?', 'Le bouchon est dans la canalisation commune aux deux évacuations. N\'utilisez plus d\'eau et appelez-nous : un débouchage de la canalisation est nécessaire.'],
                ],
            ],
            'electricien' => [
                'title' => 'Électricien à Tavaux (39) – panne, tableau, mise aux normes | EMAE',
                'h1' => 'Électricien à Tavaux : pannes et mises en sécurité',
                'meta' => 'Panne de courant, disjoncteur, tableau ancien à Tavaux, Damparis, Saint-Aubin : électricien basé à Dole, à moins de 10 km. Prix annoncé avant de partir.',
                'intro' => 'Un disjoncteur qui ne se réarme plus, une pièce sans courant, une prise qui noircit : à Tavaux, notre base de Dole est à moins de 10 km. Appelez-nous et décrivez ce qui se passe : nous vous disons s\'il faut couper le courant en attendant, puis nous vous annonçons le créneau et le prix du déplacement avant de partir. Dans les maisons des Cités comme dans les pavillons récents, et à Damparis, Abergement-la-Ronce ou Saint-Aubin, le technicien établit le diagnostic et vous donne le prix de la réparation avant de commencer.',
                'local' => [
                    ['Des installations reprises par étapes', 'Dans les maisons des Cités, l\'électricité a souvent été complétée au fil des années : tableau à fusibles conservé, circuits ajoutés sans protection différentielle. Nous vous proposons une mise en sécurité, poste par poste, avec un devis gratuit.'],
                    ['Ateliers, garages, dépendances', 'Beaucoup de maisons ont un garage ou un atelier : nous ajoutons ou remettons aux normes les circuits, avec une protection adaptée aux outils et aux appareils.'],
                ],
                'faq' => [
                    ['Mon tableau a encore des fusibles : faut-il le changer ?', 'Pas forcément tout de suite, mais un tableau sans protection différentielle 30 mA ne protège pas les personnes. Nous vérifions l\'installation et vous proposons une mise en sécurité ou un remplacement, avec un devis gratuit pour chacun.'],
                    ['Le courant saute quand il pleut : pourquoi ?', 'Souvent à cause d\'une humidité dans un circuit extérieur (prise de jardin, éclairage, garage). Coupez ce circuit au tableau si vous le repérez, puis appelez-nous pour trouver le défaut d\'isolement.'],
                ],
            ],
            'ville' => [
                'title' => 'Électricien, plombier, chauffagiste à Tavaux (39), près de Dole | EMAE',
                'h1' => 'Électricien, plombier et chauffagiste à Tavaux, à côté de Dole',
                'meta' => 'Tavaux, Damparis, Abergement-la-Ronce, Saint-Aubin : dépannage électricité, plomberie, chauffage. Notre base de Dole est à moins de 10 km.',
                'intro' => 'Tavaux est à moins de 10 km de notre base de Dole : quand le courant saute, qu\'une fuite inonde la salle de bains ou que la chaudière s\'arrête, la route est courte. Des maisons des Cités aux lotissements plus récents, et dans les villages autour, de Damparis à Saint-Aubin, nous intervenons pour l\'électricité, la plomberie, le chauffage et la climatisation. Au téléphone, nous vous disons quoi faire en attendant, puis nous vous annonçons le créneau et le prix du déplacement avant de partir.',
                'local' => [
                    ['Les maisons des Cités', 'Le quartier des Cités s\'est construit autour de l\'usine chimique, au milieu du XXe siècle. Dans ces maisons, l\'électricité et la plomberie ont souvent été refaites par morceaux : nous repérons ce qui doit être mis en sécurité et vous remettons un devis gratuit, poste par poste.'],
                    ['Particuliers et professionnels', 'Autour de l\'aéroport Dole-Jura et dans les zones d\'activités, nous intervenons aussi pour les locaux professionnels : électricité, chauffage, climatisation, avec une facture au nom de votre entreprise.'],
                ],
                'faq' => [
                    ['Êtes-vous loin de Tavaux ?', 'Non : notre base est à Dole, à moins de 10 km. Le délai réel dépend des interventions en cours ; nous vous le confirmons au téléphone avant de partir.'],
                    ['Faites-vous l\'entretien des chaudières à Tavaux ?', 'Oui, l\'entretien annuel des chaudières gaz et fioul et des pompes à chaleur. Le prix (hors déplacement) est affiché sur notre page Tarifs, et vous pouvez prendre rendez-vous en ligne depuis la page chauffagiste.'],
                ],
            ],
            'chauffagiste' => [
                'title' => 'Chauffagiste à Tavaux (39) – chaudière, pompe à chaleur, entretien | EMAE',
                'h1' => 'Chauffagiste à Tavaux : dépannage et entretien de chaudière',
                'meta' => 'Chaudière en panne ou entretien annuel à Tavaux, Damparis, Saint-Aubin : chauffagiste basé à Dole, à moins de 10 km. Prix annoncé avant de partir.',
                'intro' => 'Une chaudière qui se met en sécurité, une pression qui chute, une pompe à chaleur qui affiche un code d\'erreur : à Tavaux, notre base de Dole est à moins de 10 km. Appelez-nous avec la marque de l\'appareil et le message affiché : nous vous disons s\'il faut couper quelque chose en attendant, puis nous vous annonçons le créneau et le prix du déplacement avant de partir. Pour l\'entretien annuel, vous pouvez aussi choisir directement un créneau en ligne. Nous intervenons à Tavaux et autour, de Damparis à Champdivers, sur chaudières gaz et fioul, pompes à chaleur et radiateurs.',
                'local' => [
                    ['Gaz, fioul et pompes à chaleur', 'Dans les maisons de Tavaux, la chaudière gaz est courante ; dans les villages autour, comme Molay, Aumur ou Champdivers, on trouve davantage de chaudières fioul et de pompes à chaleur. Nous intervenons sur ces trois types d\'équipements.'],
                    ['L\'entretien avant l\'hiver', 'L\'entretien annuel d\'une chaudière est obligatoire. En le faisant en automne, vous évitez la panne aux premiers froids, quand les demandes s\'accumulent : prenez rendez-vous en ligne au moment qui vous arrange.'],
                ],
                'faq' => [
                    ['Ma chaudière perd de la pression : que faire ?', 'Regardez le manomètre : en dessous d\'environ 1 bar à froid, la plupart des chaudières se mettent en sécurité. Vous pouvez remettre de l\'eau avec le robinet de remplissage en suivant la notice. Si la pression rechute en quelques jours, il y a une fuite ou un vase d\'expansion à vérifier : appelez-nous.'],
                    ['Intervenez-vous sur les pompes à chaleur air-eau ?', 'Oui : dépannage, entretien et remplacement. Notez la marque, le modèle et le code d\'erreur éventuel avant d\'appeler, cela nous aide à venir avec la bonne pièce.'],
                ],
            ],
        ],
        'besancon' => [
            'ville' => [
                'title' => 'Électricien, plombier, chauffagiste à Besançon (25) | EMAE',
                'h1' => 'Électricien, plombier et chauffagiste à Besançon et dans le Grand Besançon',
                'meta' => 'Besançon et Grand Besançon : panne de courant, fuite, chaudière en panne. De la Boucle à Planoise, électricien, plombier, chauffagiste, prix annoncé.',
                'intro' => 'Battant, les Chaprais, Saint-Ferjeux, Palente, Planoise : à Besançon, on passe en quelques rues d\'un immeuble du XVIIIe siècle à une résidence des années 1970, et les pannes ne se ressemblent pas. Dans la Boucle, ce sont souvent des installations électriques anciennes et des canalisations d\'origine ; dans les quartiers d\'habitat collectif, la question est d\'abord de savoir ce qui relève de votre logement et ce qui relève du syndic. Appelez-nous : nous faisons le point, puis nous vous annonçons le créneau et le prix du déplacement avant de partir, à Besançon comme à École-Valentin, Thise ou Franois.',
                'local' => [
                    ['Des hivers qui sollicitent le chauffage', 'Les hivers du Doubs sont longs et froids : c\'est quand tout tourne à plein que les chaudières se mettent en sécurité, que les tableaux disjonctent et que les tuyaux exposés gèlent. Un entretien avant l\'hiver évite une bonne partie de ces pannes.'],
                    ['Logement ou parties communes ?', 'En immeuble, une coupure ou une fuite peut venir de votre logement ou d\'une installation commune. Nous vous aidons à faire la différence et vous remettons un compte rendu utile pour votre syndic ou votre assurance.'],
                ],
                'faq' => [
                    ['Intervenez-vous dans tous les quartiers de Besançon ?', 'Oui : la Boucle et Battant, les Chaprais, Saint-Ferjeux, Montrapon, Palente, Planoise, Saint-Claude, et les communes du Grand Besançon. Pour une commune plus éloignée, appelez-nous pour vérifier.'],
                    ['Le chauffage collectif de mon immeuble est en panne : pouvez-vous intervenir ?', 'La chaufferie collective relève du syndic ou du bailleur, qui a son propre prestataire. Nous intervenons sur ce qui est à vous : radiateurs, robinets thermostatiques, ou chaudière individuelle si votre logement en a une.'],
                ],
            ],
            'electricien' => [
                'title' => 'Électricien à Besançon (25) – panne, tableau, mise aux normes | EMAE',
                'h1' => 'Électricien à Besançon : pannes, tableaux et mises aux normes',
                'meta'  => 'Électricien à Besançon : panne de courant, disjoncteur, tableau, mise aux normes, de la Boucle à Planoise. Prix du déplacement annoncé avant de partir.',
                'intro' => 'Une panne de courant dans un appartement de la Boucle, un disjoncteur qui saute dans une maison de Thise ou d\'École-Valentin : appelez-nous et décrivez ce qui se passe. Nous vous disons s\'il faut couper le courant en attendant, puis nous confirmons le créneau et le prix du déplacement avant de partir. Nous intervenons à Besançon et dans le Grand Besançon. Sur place, le diagnostic et le prix de la réparation vous sont annoncés avant tout travail.',
                'local' => [
                    ['Immeubles anciens de la Boucle', 'Dans le centre historique, beaucoup d\'immeubles anciens ont des installations reprises par étapes, parfois sans protection différentielle : nous vous proposons une mise en sécurité adaptée, avec un devis gratuit.'],
                    ['Chauffage électrique et hivers froids', 'Quand les convecteurs tournent tous en même temps par grand froid, un tableau sous-dimensionné disjoncte : nous vérifions la puissance de l\'abonnement et la répartition des circuits.'],
                ],
                'faq'   => [
                    ['Mon disjoncteur général saute quand il fait très froid : pourquoi ?', 'Souvent parce que tous les chauffages électriques fonctionnent en même temps et dépassent la puissance de l\'abonnement ou d\'un circuit. Nous vérifions l\'installation et vous disons s\'il faut revoir les circuits ou l\'abonnement.'],
                    ['Intervenez-vous dans tout le Grand Besançon ?', 'Oui : Besançon, École-Valentin, Châtillon-le-Duc, Miserey-Salines, Thise, Chalezeule, Beure, Franois, Serre-les-Sapins et les communes autour.'],
                ],
            ],
            'plombier' => [
                'meta'  => 'Plombier à Besançon : fuite, canalisation bouchée, chauffe-eau, canalisations gelées. Prix du déplacement annoncé avant de partir.',
                'intro' => 'Une fuite sous l\'évier, une canalisation bouchée, un tuyau qui a gelé dans un garage ou une cave : appelez-nous. Nous vous indiquons comment couper l\'eau et limiter les dégâts, puis nous confirmons le créneau et le prix du déplacement avant de partir. Nous intervenons à Besançon et dans le Grand Besançon, en appartement comme en maison.',
                'local' => [
                    ['Gel l\'hiver', 'Les hivers sont rudes dans le Doubs : les canalisations qui passent dans un garage, une cave ou un vide sanitaire peuvent geler et éclater. Nous réparons et vous conseillons pour les protéger.'],
                    ['Une eau calcaire', 'L\'eau du massif jurassien est calcaire : le tartre use les chauffe-eau et la robinetterie. Un entretien régulier évite bien des fuites.'],
                ],
                'faq'   => [
                    ['Une canalisation a gelé : que faire ?', 'Coupez l\'arrivée d\'eau, ouvrez le robinet concerné et réchauffez doucement le tuyau (sèche-cheveux, linge chaud), jamais avec une flamme. Si le tuyau a éclaté ou fuit au dégel, appelez-nous.'],
                    ['Intervenez-vous en appartement dans le centre de Besançon ?', 'Oui. En immeuble, nous vérifions d\'abord si la fuite vient de votre logement ou d\'une colonne commune, qui relève alors du syndic.'],
                ],
            ],
            'chauffagiste' => [
                'meta'  => 'Chauffagiste à Besançon : chaudière en panne, pompe à chaleur, radiateurs froids, entretien. Hivers du Doubs : prix du déplacement annoncé avant de partir.',
                'intro' => 'À Besançon, l\'hiver est long : une chaudière en sécurité ou une pompe à chaleur en défaut ne peut pas attendre. Appelez-nous avec la marque de l\'appareil et le message affiché, nous confirmons le créneau et le prix du déplacement avant de partir. Nous intervenons à Besançon et dans le Grand Besançon, où nous avons déjà remplacé une pompe à chaleur Atlantic, sur chaudières gaz et fioul, pompes à chaleur, radiateurs et thermostats.',
                'local' => [
                    ['Chauffage individuel ou collectif', 'Dans les grands ensembles comme Planoise, le chauffage est souvent collectif : nous intervenons alors sur ce qui est à vous (radiateurs, robinets thermostatiques). En maison, nous dépannons et entretenons chaudières et pompes à chaleur.'],
                    ['Un chantier à Besançon', 'Nous y avons remplacé une pompe à chaleur Atlantic : dépose de l\'ancien appareil, pose et mise en service du nouveau.'],
                ],
                'faq'   => [
                    ['Ma pompe à chaleur givre beaucoup par grand froid : est-ce normal ?', 'Un peu de givre suivi de cycles de dégivrage est normal par temps froid et humide. Si le givre ne disparaît plus, si l\'appareil s\'arrête ou affiche un code, appelez-nous.'],
                    ['Faut-il remplacer une vieille chaudière fioul ?', 'Pas obligatoirement si elle fonctionne et est entretenue. Si elle tombe souvent en panne, nous vous présentons les options (chaudière récente ou pompe à chaleur), avec un devis gratuit pour chacune.'],
                ],
            ],
            'climatisation' => [
                'meta'  => 'Climatisation à Besançon : dépannage, entretien, installation de clim et de pompes à chaleur air-air réversibles. Prix annoncé avant de partir.',
                'intro' => 'Dans la cuvette de Besançon, les journées d\'été peuvent être étouffantes, surtout sous les toits. Une climatisation qui ne refroidit plus ou qui goutte ? Appelez-nous : nous faisons le point sur l\'appareil, puis nous confirmons le créneau et le prix du déplacement avant de partir. Nous intervenons à Besançon et autour pour le dépannage, l\'entretien et l\'installation de climatisations et de pompes à chaleur air-air réversibles.',
                'local' => [
                    ['Chaud l\'été, froid l\'hiver', 'Dans le Doubs, une pompe à chaleur air-air réversible a un double intérêt : rafraîchir en été et chauffer en hiver. Nous vous conseillons sur l\'appareil adapté, devis gratuit à l\'appui.'],
                    ['Centre ancien et copropriété', 'Dans le centre historique, l\'unité extérieure demande souvent l\'accord de la copropriété et parfois une déclaration en mairie : nous vous aidons à préparer le dossier.'],
                ],
                'faq'   => [
                    ['Une pompe à chaleur air-air chauffe-t-elle assez dans le Doubs ?', 'Les modèles récents chauffent par temps froid, avec un rendement qui baisse quand il gèle fort. Nous dimensionnons l\'appareil selon votre logement et vous disons franchement si un appoint reste utile.'],
                    ['Ma climatisation sent mauvais au démarrage : que faire ?', 'C\'est souvent le signe de filtres et d\'une unité intérieure encrassés. Un entretien avec nettoyage règle le problème dans la plupart des cas.'],
                ],
            ],
        ],
        'lons-le-saunier' => [
            'ville' => [
                'title' => 'Électricien, plombier, chauffagiste à Lons-le-Saunier (39) | EMAE',
                'h1' => 'Électricité, plomberie et chauffage à Lons-le-Saunier et dans le Revermont',
                'meta' => 'Lons-le-Saunier, Montmorot, Perrigny, Revermont : panne de courant, fuite, chaudière fioul ou gaz. Électricien, plombier, chauffagiste, prix annoncé.',
                'intro' => 'À Lons-le-Saunier, préfecture du Jura, on passe vite des maisons de ville du centre et des arcades de la rue du Commerce aux maisons de village du Revermont et de la Bresse jurassienne. Chaudière fioul qui ne redémarre pas, tableau électrique d\'une vieille maison, fuite sur une canalisation entartrée : chaque secteur a ses pannes typiques. Appelez-nous : nous faisons le point avec vous, puis nous vous annonçons le créneau et le prix du déplacement avant de partir, à Lons comme à Montmorot, Perrigny ou Conliège.',
                'local' => [
                    ['Ville et villages', 'En ville, le gaz et les immeubles ; dans les villages autour, le fioul, les maisons anciennes et les dépendances. Nous intervenons sur les deux, avec le même principe : diagnostic, prix annoncé, accord avant travaux.'],
                    ['Une eau très calcaire', 'Comme dans tout le Jura, le calcaire use les chauffe-eau, les mitigeurs et les échangeurs de chaudière : un détartrage au bon moment évite bien des pannes.'],
                ],
                'faq' => [
                    ['Intervenez-vous dans les villages autour de Lons ?', 'Oui : Montmorot, Perrigny, Courlaoux, Messia-sur-Sorne, Conliège, Macornay, Chilly-le-Vignoble, Revigny et les communes autour. Pour un village plus éloigné, appelez-nous pour vérifier.'],
                    ['Pouvez-vous intervenir pour un commerce du centre-ville ?', 'Oui, pour l\'électricité, la plomberie, le chauffage et la climatisation des locaux professionnels, avec une facture au nom de votre entreprise.'],
                ],
            ],
            'electricien' => [
                'meta'  => 'Électricien à Lons-le-Saunier : panne de courant, disjoncteur, tableau, mise aux normes. Prix du déplacement annoncé avant de partir.',
                'intro' => 'Une panne de courant à Lons-le-Saunier, un disjoncteur qui saute dans une maison de Montmorot ou de Perrigny : appelez-nous et décrivez ce qui se passe. Nous vous disons s\'il faut couper le courant en attendant, puis nous confirmons le créneau et le prix du déplacement avant de partir. Nous intervenons à Lons et dans les communes autour, du Revermont à la Bresse jurassienne. Sur place, le diagnostic et le prix de la réparation vous sont annoncés avant tout travail.',
                'local' => [
                    ['Maisons de ville et maisons de village', 'Entre les maisons de ville du centre de Lons et les maisons de village du Revermont, beaucoup d\'installations ont été reprises par morceaux : nous repérons ce qui est dangereux et vous proposons une mise en sécurité, devis gratuit à l\'appui.'],
                    ['Dépendances et ateliers', 'Garage, atelier, dépendance de maison de vigneron : nous ajoutons ou remettons aux normes les circuits, avec une protection adaptée.'],
                ],
                'faq'   => [
                    ['Intervenez-vous autour de Lons-le-Saunier ?', 'Oui : Montmorot, Perrigny, Courlaoux, Messia-sur-Sorne, Conliège, Macornay, Chilly-le-Vignoble, Revigny et les communes autour. Pour un village plus éloigné, appelez-nous pour vérifier.'],
                    ['Ma maison n\'a pas de prise de terre : est-ce dangereux ?', 'Sans terre, un défaut sur un appareil peut rendre sa carcasse dangereuse au toucher. Nous pouvons créer une prise de terre et ajouter une protection différentielle 30 mA, avec un devis gratuit.'],
                ],
            ],
            'plombier' => [
                'meta'  => 'Plombier à Lons-le-Saunier : fuite, WC bouché, chauffe-eau en panne, canalisations. Prix du déplacement annoncé avant de partir.',
                'intro' => 'Une fuite d\'eau à Lons-le-Saunier, un WC bouché à Montmorot, un chauffe-eau qui ne chauffe plus à Perrigny : appelez-nous. Nous vous indiquons comment couper l\'eau et limiter les dégâts, puis nous confirmons le créneau et le prix du déplacement avant de partir. Sur place, nous trouvons l\'origine de la fuite ou du bouchon et vous annonçons le prix avant toute réparation.',
                'local' => [
                    ['Une eau calcaire', 'Comme dans tout le Jura, l\'eau est calcaire : le tartre use les chauffe-eau, les groupes de sécurité et la robinetterie. Nous vous conseillons sur l\'entretien qui évite les pannes.'],
                    ['Maisons anciennes', 'Dans les maisons anciennes du centre et des villages, on rencontre encore d\'anciennes canalisations : nous vous disons si elles doivent être remplacées, devis gratuit à l\'appui.'],
                ],
                'faq'   => [
                    ['Mon chauffe-eau fait du bruit et chauffe mal : pourquoi ?', 'C\'est souvent le tartre accumulé au fond de la cuve et sur la résistance, fréquent avec l\'eau calcaire du Jura. Un détartrage ou le remplacement de la résistance règle souvent le problème.'],
                    ['Que faire en cas de fuite avant votre arrivée ?', 'Coupez l\'arrivée d\'eau générale, coupez l\'électricité si l\'eau approche d\'une prise ou du tableau, épongez et prenez des photos pour votre assurance.'],
                ],
            ],
            'chauffagiste' => [
                'meta'  => 'Chauffagiste à Lons-le-Saunier : chaudière fioul ou gaz, pompe à chaleur, radiateurs, entretien annuel. Prix du déplacement annoncé avant de partir.',
                'intro' => 'Une chaudière fioul qui ne redémarre plus, une pompe à chaleur en défaut, des radiateurs froids : à Lons-le-Saunier et dans les villages autour, le chauffage ne peut pas attendre l\'hiver. Appelez-nous avec la marque de l\'appareil et le message affiché ; nous confirmons le créneau et le prix du déplacement avant de partir. Nous intervenons sur chaudières gaz et fioul, pompes à chaleur, radiateurs et thermostats.',
                'local' => [
                    ['Fioul dans les villages, gaz en ville', 'Dans les villages du Revermont et de la Bresse jurassienne, beaucoup de maisons sont chauffées au fioul ; en ville, le gaz est plus répandu, et les pompes à chaleur se multiplient partout. Nous intervenons sur ces trois types d\'équipements.'],
                    ['Penser à l\'entretien', 'L\'entretien annuel est obligatoire pour les chaudières : le faire avant l\'hiver, c\'est éviter la panne au moment où le chauffage tourne le plus.'],
                ],
                'faq'   => [
                    ['Ma chaudière fioul s\'est arrêtée : est-ce la panne de fioul ?', 'Vérifiez d\'abord le niveau de la cuve. Si elle n\'est pas vide, ne réarmez pas plus d\'une fois : un brûleur qui se met en sécurité à répétition doit être contrôlé. Appelez-nous.'],
                    ['Peut-on remplacer une chaudière fioul par une pompe à chaleur ?', 'Souvent, oui, en gardant les radiateurs existants selon leur type. Nous étudions votre logement et vous remettons un devis gratuit pour chaque option.'],
                ],
            ],
            'climatisation' => [
                'meta'  => 'Climatisation à Lons-le-Saunier : dépannage, entretien, installation de clim et de pompes à chaleur air-air réversibles. Prix annoncé avant de partir.',
                'intro' => 'Une climatisation qui souffle tiède, qui goutte ou qui ne démarre plus à Lons-le-Saunier : appelez-nous et décrivez l\'appareil et la panne. Nous confirmons le créneau et le prix du déplacement avant de partir. Nous intervenons à Lons et autour pour le dépannage, l\'entretien et l\'installation de climatisations et de pompes à chaleur air-air réversibles, en maison comme en commerce.',
                'local' => [
                    ['Réversible pour toute l\'année', 'Une pompe à chaleur air-air rafraîchit en été et chauffe en hiver : une solution souvent choisie pour remplacer d\'anciens convecteurs électriques. Nous vous conseillons sur le bon dimensionnement.'],
                    ['Commerces du centre', 'Pour un commerce ou un bureau, une climatisation en panne pendant une vague de chaleur pèse vite sur l\'activité : nous intervenons aussi pour les professionnels.'],
                ],
                'faq'   => [
                    ['Combien de temps dure l\'installation d\'une climatisation ?', 'Pour un appareil mural simple, l\'installation se fait en général en une journée. Nous vous donnons la durée précise avec le devis, après avoir vu l\'emplacement des unités.'],
                    ['Ma climatisation ne démarre plus : que vérifier ?', 'Vérifiez les piles de la télécommande et le disjoncteur dédié au tableau. Si tout est en ordre, notez le code ou le voyant affiché et appelez-nous.'],
                ],
            ],
        ],
        'auxonne' => [
            'ville' => [
                'title' => 'Électricien, plombier, chauffagiste à Auxonne (21) | EMAE',
                'h1' => 'Électricité, plomberie et chauffage à Auxonne, au bord de la Saône',
                'meta' => 'Auxonne, Villers-les-Pots, Athée, Tillenay : électricien, plombier, chauffagiste. Notre base de Dole est à une quinzaine de km. Prix annoncé avant.',
                'intro' => 'Auxonne est à une quinzaine de kilomètres de notre base de Dole, de l\'autre côté de la limite entre le Jura et la Côte-d\'Or. Dans la ville ancienne, entre l\'arsenal, la citadelle et les remparts où le jeune Bonaparte a servi comme officier d\'artillerie, comme dans les lotissements et les villages autour, de Villers-les-Pots à Tillenay, nous intervenons pour l\'électricité, la plomberie, le chauffage et la climatisation. Au téléphone, nous vous disons quoi faire en attendant, puis nous vous annonçons le créneau et le prix du déplacement avant de partir.',
                'local' => [
                    ['Maisons anciennes de la ville fortifiée', 'Murs épais, pièces en enfilade, installations reprises au fil du temps : dans le centre d\'Auxonne, nous repérons ce qui doit être mis en sécurité et vous remettons un devis gratuit.'],
                    ['Près de la Saône', 'Dans les secteurs proches de la rivière, l\'humidité des caves et des sous-sols fatigue les câbles, les raccords et les chaudières : nous en tenons compte dans nos diagnostics.'],
                ],
                'faq' => [
                    ['Venez-vous de loin pour Auxonne ?', 'Non : notre base est à Dole, à une quinzaine de kilomètres. Le délai réel dépend des interventions en cours ; nous vous le confirmons au téléphone avant de partir.'],
                    ['Intervenez-vous dans les villages autour d\'Auxonne ?', 'Oui : Villers-les-Pots, Athée, Tillenay, Les Maillys, Flammerans, Poncey-lès-Athée, Labergement-lès-Auxonne, Champvans et les communes voisines.'],
                ],
            ],
            'electricien' => [
                'title' => 'Électricien à Auxonne (21) – panne de courant, tableau | EMAE',
                'h1' => 'Électricien à Auxonne : dépannage et mise en sécurité',
                'meta' => 'Panne de courant, disjoncteur qui saute, tableau ancien à Auxonne et autour : électricien basé à Dole, à une quinzaine de km. Prix annoncé avant.',
                'intro' => 'Plus de courant dans une partie de la maison, un différentiel qui saute sans raison, une odeur de chaud près d\'une prise : à Auxonne, notre base de Dole est à une quinzaine de kilomètres. Appelez-nous et décrivez la panne : nous vous disons s\'il faut couper le courant, puis nous vous annonçons le créneau et le prix du déplacement avant de partir. Dans la ville ancienne comme à Villers-les-Pots ou Athée, le technicien recherche le défaut et vous donne le prix de la réparation avant de commencer.',
                'local' => [
                    ['Caves et sous-sols humides', 'Près de la Saône, l\'humidité des caves use les câbles et les boîtes de dérivation : c\'est une cause fréquente de différentiel qui saute. Nous trouvons le circuit en cause et le remettons en état.'],
                    ['Mise aux normes avant de louer ou de vendre', 'Logement mis en location ou maison à vendre : nous réalisons les travaux de mise en sécurité demandés après le diagnostic électrique, avec un devis gratuit.'],
                ],
                'faq' => [
                    ['Mon différentiel saute la nuit : pourquoi ?', 'Un appareil qui se met en route la nuit (chauffe-eau en heures creuses, congélateur, pompe) peut avoir un défaut d\'isolement. Notez à quelle heure cela arrive : cela nous aide à trouver le circuit en cause.'],
                    ['Faites-vous l\'ajout de prises et de circuits ?', 'Oui : prises, circuit spécialisé pour une plaque de cuisson ou une borne, éclairage extérieur, avec un devis gratuit avant les travaux.'],
                ],
            ],
            'plombier' => [
                'title' => 'Plombier à Auxonne (21) – fuite, débouchage, chauffe-eau | EMAE',
                'h1' => 'Plombier à Auxonne : fuites, canalisations et chauffe-eau',
                'meta' => 'Fuite d\'eau, WC bouché, chauffe-eau en panne à Auxonne, Tillenay, Les Maillys : plombier basé à Dole, à une quinzaine de km. Prix annoncé avant.',
                'intro' => 'Une fuite au plafond, une évacuation bouchée, un chauffe-eau qui ne chauffe plus : à Auxonne, notre base de Dole est à une quinzaine de kilomètres. Appelez-nous : nous vous indiquons d\'abord comment couper l\'eau et limiter les dégâts, puis nous vous annonçons le créneau et le prix du déplacement avant de partir. Nous intervenons à Auxonne et dans les villages autour, de Tillenay aux Maillys, pour les fuites, les débouchages, les chauffe-eau et la robinetterie.',
                'local' => [
                    ['Canalisations anciennes', 'Dans les maisons de la ville fortifiée, on rencontre encore des canalisations anciennes, parfois en plomb : nous vous disons si elles doivent être remplacées, devis gratuit à l\'appui.'],
                    ['Sous-sols et remontées d\'eau', 'Dans les secteurs bas, près de la Saône, une évacuation qui refoule au sous-sol peut venir d\'un bouchon comme d\'une canalisation affaissée : nous recherchons la cause avant de proposer une réparation.'],
                ],
                'faq' => [
                    ['L\'eau refoule dans mon sous-sol : que faire ?', 'N\'utilisez plus les évacuations de la maison et appelez-nous : il faut trouver si le bouchon est dans votre canalisation ou plus loin, côté réseau public.'],
                    ['Remplacez-vous les anciennes canalisations en plomb ?', 'Oui. Nous repérons les tronçons concernés et vous remettons un devis gratuit pour les remplacer par du cuivre ou du multicouche.'],
                ],
            ],
            'chauffagiste' => [
                'title' => 'Chauffagiste à Auxonne (21) – chaudière, PAC, entretien | EMAE',
                'h1' => 'Chauffagiste à Auxonne : chaudières, pompes à chaleur, entretien',
                'meta' => 'Chaudière en panne, pompe à chaleur en défaut, entretien annuel à Auxonne et autour : chauffagiste basé à Dole, à une quinzaine de km. Prix annoncé.',
                'intro' => 'Une chaudière en sécurité, des radiateurs froids, une pompe à chaleur qui affiche un code d\'erreur : à Auxonne, notre base de Dole est à une quinzaine de kilomètres. Appelez-nous avec la marque de l\'appareil et le message affiché : nous vous annonçons le créneau et le prix du déplacement avant de partir. Pour l\'entretien annuel, vous pouvez aussi choisir un créneau en ligne. Nous intervenons à Auxonne et dans les villages autour sur chaudières gaz et fioul, pompes à chaleur, radiateurs et thermostats.',
                'local' => [
                    ['Fioul dans les villages, gaz en ville', 'En ville, le gaz est répandu ; dans les villages autour d\'Auxonne, beaucoup de maisons sont encore chauffées au fioul, et les pompes à chaleur se multiplient. Nous intervenons sur ces trois types d\'équipements.'],
                    ['Entretenir avant l\'hiver', 'L\'entretien annuel est obligatoire pour les chaudières : le faire en automne évite la panne aux premiers froids, au moment où tout le monde appelle.'],
                ],
                'faq' => [
                    ['Mes radiateurs chauffent en bas mais pas en haut : pourquoi ?', 'Il y a de l\'air dans le circuit : purgez les radiateurs, puis remettez de la pression à la chaudière si elle a baissé. Si le problème revient vite, appelez-nous.'],
                    ['Ma chaudière fioul fume ou sent fort : que faire ?', 'Arrêtez-la et aérez. Une odeur ou une fumée anormale signale un mauvais réglage ou un encrassement du brûleur : un contrôle est nécessaire avant de la remettre en route.'],
                ],
            ],
            'climatisation' => [
                'title' => 'Climatisation à Auxonne (21) – dépannage, entretien, pose | EMAE',
                'h1' => 'Climatisation à Auxonne : dépannage et installation',
                'meta' => 'Climatisation en panne ou à installer à Auxonne, Athée, Villers-les-Pots : dépannage, entretien, pompe à chaleur air-air. Base de Dole, prix annoncé.',
                'intro' => 'Un été chaud dans la vallée de la Saône, et la climatisation qui s\'arrête ou souffle de l\'air tiède : à Auxonne, notre base de Dole est à une quinzaine de kilomètres. Appelez-nous et décrivez l\'appareil et la panne : nous vous annonçons le créneau et le prix du déplacement avant de partir. Nous intervenons à Auxonne et autour pour le dépannage, l\'entretien et l\'installation de climatisations et de pompes à chaleur air-air réversibles.',
                'local' => [
                    ['Chaud l\'été, humide l\'hiver', 'Une pompe à chaleur air-air réversible rafraîchit en été, chauffe en hiver et assèche l\'air des pièces humides : un choix fréquent dans les maisons anciennes de la ville.'],
                    ['Façades anciennes', 'Dans le centre historique, l\'emplacement de l\'unité extérieure compte : nous vous proposons une pose discrète et vous fournissons les éléments pour une éventuelle déclaration en mairie.'],
                ],
                'faq' => [
                    ['Faut-il entretenir une clim tous les ans ?', 'Oui, idéalement une fois par an : nettoyage des filtres et des unités, contrôle du fonctionnement. Un appareil entretenu consomme moins et tombe moins souvent en panne.'],
                    ['Ma clim fait un bruit de claquement au démarrage : est-ce normal ?', 'Un léger bruit de dilatation peut l\'être ; un claquement fort ou un sifflement ne l\'est pas. Notez quand il se produit et appelez-nous.'],
                ],
            ],
        ],
        'saint-vit' => [
            'ville' => [
                'title' => 'Électricien, plombier, chauffagiste à Saint-Vit (25) | EMAE',
                'h1' => 'Électricité, plomberie et chauffage à Saint-Vit et dans la vallée du Doubs',
                'meta' => 'Saint-Vit, Byans-sur-Doubs, Boussières, Dannemarie-sur-Crète : électricien, plombier, chauffagiste. Base de Dole à une vingtaine de km, prix annoncé.',
                'intro' => 'Saint-Vit est à une vingtaine de kilomètres de notre base de Dole, sur la route de Besançon, au bord du Doubs. La commune s\'est beaucoup agrandie avec des lotissements récents, autour d\'un centre plus ancien et des villages de la vallée, de Byans-sur-Doubs à Boussières. Électricité, plomberie, chauffage, climatisation : un seul numéro pour toutes les pannes de la maison. Au téléphone, nous vous disons quoi faire en attendant, puis nous vous annonçons le créneau et le prix du déplacement avant de partir.',
                'local' => [
                    ['Maisons récentes, équipements récents', 'Dans les lotissements de Saint-Vit, ce sont souvent des pompes à chaleur, des chauffe-eau thermodynamiques et des tableaux électriques récents : des équipements que nous dépannons et entretenons.'],
                    ['Villages de la vallée du Doubs', 'Dans les maisons anciennes des villages, fioul, canalisations et installations électriques anciennes demandent un autre regard : nous adaptons le diagnostic au logement.'],
                ],
                'faq' => [
                    ['Venez-vous de Besançon ou de Dole ?', 'De Dole, où est notre base, à une vingtaine de kilomètres. Le délai réel dépend des interventions en cours ; nous vous le confirmons au téléphone avant de partir.'],
                    ['Intervenez-vous dans les villages autour de Saint-Vit ?', 'Oui : Byans-sur-Doubs, Osselle-Routelle, Roset-Fluans, Villars-Saint-Georges, Dannemarie-sur-Crète, Boussières, Torpes, Velesmes-Essarts et les communes voisines.'],
                ],
            ],
            'electricien' => [
                'title' => 'Électricien à Saint-Vit (25) – panne, tableau, ajout de circuits | EMAE',
                'h1' => 'Électricien à Saint-Vit : pannes, tableaux et nouveaux circuits',
                'meta' => 'Panne de courant, disjoncteur, borne de recharge, tableau à Saint-Vit et dans la vallée du Doubs : électricien basé à Dole, prix annoncé avant de partir.',
                'intro' => 'Un disjoncteur qui saute dès que la pompe à chaleur démarre, une prise extérieure qui fait tout couper, un circuit à ajouter pour une plaque de cuisson : à Saint-Vit, notre base de Dole est à une vingtaine de kilomètres. Appelez-nous et décrivez ce qui se passe : nous vous disons s\'il faut couper le courant, puis nous vous annonçons le créneau et le prix du déplacement avant de partir. Le technicien établit le diagnostic et vous donne le prix de la réparation avant de commencer.',
                'local' => [
                    ['Maisons récentes très équipées', 'Pompe à chaleur, chauffe-eau thermodynamique, plaque à induction, voiture électrique : dans les maisons récentes, c\'est souvent la répartition des circuits ou la puissance de l\'abonnement qu\'il faut revoir.'],
                    ['Maisons anciennes des villages', 'Dans les villages de la vallée, les installations anciennes sans différentiel 30 mA restent fréquentes : nous vous proposons une mise en sécurité, devis gratuit à l\'appui.'],
                ],
                'faq' => [
                    ['Le disjoncteur général saute quand tout fonctionne en même temps : pourquoi ?', 'La puissance appelée dépasse celle de votre abonnement. Nous vérifions l\'installation et vous disons s\'il vaut mieux augmenter la puissance ou mieux répartir les appareils.'],
                    ['Installez-vous des circuits pour une borne de recharge ?', 'Nous réalisons la ligne électrique dédiée et sa protection au tableau. Pour la pose de la borne elle-même, nous vous indiquons au devis ce qui est inclus.'],
                ],
            ],
            'plombier' => [
                'title' => 'Plombier à Saint-Vit (25) – fuite, débouchage, chauffe-eau | EMAE',
                'h1' => 'Plombier à Saint-Vit : fuites, débouchages, chauffe-eau',
                'meta' => 'Fuite, WC bouché, chauffe-eau en panne à Saint-Vit, Byans-sur-Doubs, Boussières : plombier basé à Dole, à une vingtaine de km. Prix annoncé avant.',
                'intro' => 'Une fuite sur un flexible, une douche qui ne s\'évacue plus, un chauffe-eau thermodynamique en défaut : à Saint-Vit, notre base de Dole est à une vingtaine de kilomètres. Appelez-nous : nous vous indiquons comment couper l\'eau et limiter les dégâts, puis nous vous annonçons le créneau et le prix du déplacement avant de partir. Nous intervenons à Saint-Vit et dans les villages de la vallée du Doubs pour les fuites, les débouchages, les chauffe-eau et la robinetterie.',
                'local' => [
                    ['Chauffe-eau thermodynamiques', 'Beaucoup de maisons récentes en sont équipées : quand l\'eau reste froide, la panne peut venir de la partie électrique comme de la pompe à chaleur intégrée. Nous faisons le diagnostic complet.'],
                    ['Gel des canalisations exposées', 'L\'hiver, les tuyaux qui passent dans un garage ou un vide sanitaire peuvent geler : nous réparons et vous conseillons pour les protéger.'],
                ],
                'faq' => [
                    ['Mon chauffe-eau thermodynamique affiche une erreur : que faire ?', 'Notez le code et la marque, vérifiez que le disjoncteur dédié n\'a pas sauté, puis appelez-nous. La plupart de ces appareils ont aussi une résistance d\'appoint qui permet d\'avoir de l\'eau chaude en attendant.'],
                    ['Une canalisation a gelé : comment éviter que ça recommence ?', 'En isolant les tuyaux exposés, en coupant et vidant les robinets extérieurs avant l\'hiver, et en gardant une température minimale dans les pièces non chauffées. Nous pouvons poser des gaines isolantes.'],
                ],
            ],
            'chauffagiste' => [
                'title' => 'Chauffagiste à Saint-Vit (25) – PAC, chaudière, entretien | EMAE',
                'h1' => 'Chauffagiste à Saint-Vit : pompes à chaleur et chaudières',
                'meta' => 'Pompe à chaleur en défaut, chaudière en panne, entretien annuel à Saint-Vit et autour : chauffagiste basé à Dole, à une vingtaine de km. Prix annoncé.',
                'intro' => 'Une pompe à chaleur qui givre et s\'arrête, une chaudière qui se met en sécurité, un plancher chauffant qui ne chauffe plus : à Saint-Vit, notre base de Dole est à une vingtaine de kilomètres. Appelez-nous avec la marque de l\'appareil et le code affiché : nous vous annonçons le créneau et le prix du déplacement avant de partir. Pour l\'entretien annuel, vous pouvez choisir un créneau en ligne. Nous intervenons à Saint-Vit et dans la vallée du Doubs sur pompes à chaleur, chaudières gaz et fioul, radiateurs et planchers chauffants.',
                'local' => [
                    ['Beaucoup de pompes à chaleur', 'Dans les lotissements récents, la pompe à chaleur air-eau est le chauffage le plus courant : un entretien régulier et des réglages adaptés évitent la plupart des arrêts par grand froid.'],
                    ['Fioul dans les villages', 'Dans les maisons plus anciennes de la vallée, les chaudières fioul restent nombreuses : entretien, dépannage, ou remplacement par une pompe à chaleur, devis gratuit à l\'appui.'],
                ],
                'faq' => [
                    ['L\'entretien d\'une pompe à chaleur est-il obligatoire ?', 'Pour une pompe à chaleur de 4 à 70 kW, un entretien est obligatoire tous les deux ans. Un contrôle annuel reste conseillé pour garder un bon rendement.'],
                    ['Mon plancher chauffant ne chauffe plus dans une pièce : pourquoi ?', 'Souvent une vanne ou un actionneur du collecteur qui ne s\'ouvre plus, ou un thermostat de pièce défaillant. Nous vérifions le collecteur et la régulation.'],
                ],
            ],
            'climatisation' => [
                'title' => 'Climatisation à Saint-Vit (25) – dépannage, entretien, pose | EMAE',
                'h1' => 'Climatisation à Saint-Vit : dépannage et installation',
                'meta' => 'Clim en panne, entretien ou installation de pompe à chaleur air-air à Saint-Vit, Boussières, Byans-sur-Doubs. Base de Dole, prix annoncé avant de partir.',
                'intro' => 'Une climatisation qui ne refroidit plus sous les combles, une unité qui goutte sur la terrasse, un appareil à installer avant l\'été : à Saint-Vit, notre base de Dole est à une vingtaine de kilomètres. Appelez-nous et décrivez l\'appareil et la panne : nous vous annonçons le créneau et le prix du déplacement avant de partir. Nous intervenons à Saint-Vit et autour pour le dépannage, l\'entretien et l\'installation de climatisations et de pompes à chaleur air-air réversibles.',
                'local' => [
                    ['Chambres sous les combles', 'Dans les maisons récentes à étage, les chambres sous les toits chauffent vite en été : une climatisation réversible bien dimensionnée y change le quotidien, et chauffe aussi l\'hiver.'],
                    ['En complément d\'une pompe à chaleur air-eau', 'Une pompe à chaleur air-eau chauffe mais ne rafraîchit pas toujours : un appareil air-air dans les pièces de vie peut la compléter. Nous vous conseillons avant le devis.'],
                ],
                'faq' => [
                    ['Quelle puissance de clim pour une chambre ?', 'Elle dépend de la surface, de l\'exposition et de l\'isolation : nous la calculons lors de la visite plutôt que de vous vendre un appareil trop gros ou trop petit.'],
                    ['Ma clim s\'arrête toute seule après quelques instants : pourquoi ?', 'Une protection se déclenche : filtre encrassé, unité extérieure obstruée ou défaut électrique. Nettoyez les filtres ; si le problème continue, notez le code affiché et appelez-nous.'],
                ],
            ],
        ],
        'genlis' => [
            'ville' => [
                'title' => 'Électricien, plombier, chauffagiste à Genlis (21) | EMAE',
                'h1' => 'Électricité, plomberie et chauffage à Genlis et dans la plaine de la Tille',
                'meta' => 'Genlis, Izeure, Tart, Aiserey, Fauverney : électricien, plombier, chauffagiste pour les pannes et l\'entretien. Prix du déplacement annoncé avant de partir.',
                'intro' => 'Genlis, traversée par la Tille et desservie par le train depuis Dijon, est entourée de villages de plaine : Izeure, Tart, Aiserey, Fauverney, Longeault-Pluvault. On y trouve des maisons de bourg anciennes, des pavillons des années 1970 à aujourd\'hui et des corps de ferme rénovés, avec des équipements très différents d\'une maison à l\'autre. Électricité, plomberie, chauffage, climatisation : un seul numéro. Au téléphone, nous faisons le point avec vous, puis nous vous annonçons le créneau et le prix du déplacement avant de partir.',
                'local' => [
                    ['Maisons de plaine, corps de ferme', 'Dans les villages autour de Genlis, beaucoup de maisons sont d\'anciennes fermes agrandies : installations électriques ajoutées par étapes, chaudières fioul, longues canalisations. Nous adaptons le diagnostic à ce type de logement.'],
                    ['Pavillons et lotissements', 'Dans les lotissements de Genlis, ce sont plutôt des tableaux à compléter, des chauffe-eau et des pompes à chaleur à entretenir : des interventions que nous faisons tous les jours.'],
                ],
                'faq' => [
                    ['Intervenez-vous dans les villages autour de Genlis ?', 'Oui : Izeure, Longeault-Pluvault, Varanges, Tart, Aiserey, Fauverney, Collonges-lès-Premières, Champdôtre et les communes voisines.'],
                    ['Pouvez-vous intervenir sur une exploitation ou un bâtiment agricole ?', 'Pour l\'électricité, la plomberie et le chauffage des bâtiments (logement, bureau, atelier), oui. Décrivez-nous l\'installation au téléphone : nous vous disons si elle entre dans nos interventions.'],
                ],
            ],
            'electricien' => [
                'title' => 'Électricien à Genlis (21) – panne, tableau, mise aux normes | EMAE',
                'h1' => 'Électricien à Genlis : pannes, tableaux et mises en sécurité',
                'meta' => 'Panne de courant, disjoncteur qui saute, tableau à refaire à Genlis, Izeure, Tart : électricien pour dépannage et mise aux normes. Prix annoncé avant.',
                'intro' => 'Une panne dans toute la maison, un différentiel qui saute à chaque orage, un tableau ancien à remplacer : à Genlis et dans les villages de la plaine, appelez-nous et décrivez ce qui se passe. Nous vous disons s\'il faut couper le courant en attendant, puis nous vous annonçons le créneau et le prix du déplacement avant de partir. Dans un pavillon comme dans un corps de ferme, le technicien recherche la panne et vous donne le prix de la réparation avant de commencer.',
                'local' => [
                    ['Orages et surtensions', 'Dans la plaine, les orages d\'été peuvent faire sauter les protections ou endommager des appareils : nous vérifions l\'installation et pouvons ajouter un parafoudre au tableau.'],
                    ['Dépendances et extérieurs', 'Granges, garages, éclairage de cour : les circuits extérieurs mal protégés sont une cause fréquente de coupures. Nous les remettons aux normes, devis gratuit à l\'appui.'],
                ],
                'faq' => [
                    ['Faut-il un parafoudre sur mon tableau ?', 'Il est conseillé dans les secteurs exposés aux orages et obligatoire dans certains cas (paratonnerre, zones très exposées). Nous vous disons s\'il est utile chez vous et l\'installons au tableau.'],
                    ['Le courant a sauté pendant l\'orage et ne revient pas : que faire ?', 'Vérifiez le disjoncteur général et les différentiels. S\'ils se réarment puis ressautent, ou si rien ne se passe, appelez-nous ; si tout le village est coupé, contactez d\'abord Enedis.'],
                ],
            ],
            'plombier' => [
                'title' => 'Plombier à Genlis (21) – fuite, débouchage, chauffe-eau | EMAE',
                'h1' => 'Plombier à Genlis : fuites, débouchages et chauffe-eau',
                'meta' => 'Fuite d\'eau, canalisation bouchée, chauffe-eau en panne à Genlis, Aiserey, Fauverney : plombier pour dépannage et remplacement. Prix annoncé avant.',
                'intro' => 'Une fuite sur une canalisation enterrée, une fosse ou une évacuation qui refoule, un chauffe-eau qui ne chauffe plus : à Genlis et dans les villages autour, appelez-nous. Nous vous expliquons comment couper l\'eau et limiter les dégâts, puis nous vous annonçons le créneau et le prix du déplacement avant de partir. Nous intervenons pour les fuites, les débouchages, les chauffe-eau et le remplacement de robinetterie ou de sanitaires.',
                'local' => [
                    ['Longues canalisations', 'Dans les anciennes fermes, l\'eau parcourt souvent de longues distances entre le compteur et la maison : une consommation qui augmente sans raison peut signaler une fuite enterrée. Nous la recherchons avant de casser quoi que ce soit.'],
                    ['Assainissement individuel', 'Dans les villages non raccordés au tout-à-l\'égout, une évacuation lente peut venir de la fosse : nous vous disons si c\'est la canalisation ou la vidange de la fosse qu\'il faut prévoir.'],
                ],
                'faq' => [
                    ['Ma facture d\'eau a augmenté sans raison : comment savoir s\'il y a une fuite ?', 'Fermez tous les robinets et relevez le compteur, puis relisez-le quelques heures plus tard sans avoir utilisé d\'eau. S\'il a tourné, il y a une fuite : appelez-nous pour la localiser.'],
                    ['Toutes les évacuations de la maison sont lentes : pourquoi ?', 'Le bouchon est dans la canalisation principale ou, en assainissement individuel, la fosse est peut-être pleine. Nous vérifions et vous disons quoi faire.'],
                ],
            ],
            'chauffagiste' => [
                'title' => 'Chauffagiste à Genlis (21) – chaudière fioul, PAC, entretien | EMAE',
                'h1' => 'Chauffagiste à Genlis : chaudières, pompes à chaleur, entretien',
                'meta' => 'Chaudière fioul ou gaz en panne, pompe à chaleur, entretien annuel à Genlis et dans la plaine de la Tille : chauffagiste, prix annoncé avant de partir.',
                'intro' => 'Une chaudière fioul qui ne redémarre plus, une pompe à chaleur en défaut, des radiateurs froids dans une grande maison de village : à Genlis et autour, appelez-nous avec la marque de l\'appareil et le message affiché. Nous vous annonçons le créneau et le prix du déplacement avant de partir. Pour l\'entretien annuel, vous pouvez aussi choisir un créneau en ligne. Nous intervenons sur chaudières fioul et gaz, pompes à chaleur, radiateurs et thermostats.',
                'local' => [
                    ['Grandes maisons à chauffer', 'Les anciennes fermes de la plaine ont de grands volumes : un chauffage mal équilibré laisse des pièces froides pendant que d\'autres surchauffent. Nous réglons l\'équilibrage et la régulation.'],
                    ['Du fioul à la pompe à chaleur', 'Beaucoup de foyers envisagent de remplacer une chaudière fioul : nous étudions votre maison et vous remettons un devis gratuit pour chaque solution, sans pousser à un choix.'],
                ],
                'faq' => [
                    ['Certaines pièces restent froides : est-ce la chaudière ?', 'Pas forcément : c\'est souvent l\'équilibrage du réseau de radiateurs ou un robinet thermostatique bloqué. Nous vérifions avant de toucher à la chaudière.'],
                    ['Peut-on garder ses radiateurs avec une pompe à chaleur ?', 'Souvent oui, selon leur type et leur taille. Nous vérifions s\'ils peuvent fonctionner à plus basse température avant de vous proposer une solution.'],
                ],
            ],
            'climatisation' => [
                'title' => 'Climatisation à Genlis (21) – dépannage, entretien, pose | EMAE',
                'h1' => 'Climatisation à Genlis : dépannage, entretien et installation',
                'meta' => 'Clim en panne, entretien ou pose de pompe à chaleur air-air à Genlis, Izeure, Tart : dépannage et installation, prix du déplacement annoncé avant.',
                'intro' => 'Un été chaud dans la plaine, et une climatisation qui souffle tiède ou qui goutte : à Genlis et dans les villages autour, appelez-nous et décrivez l\'appareil et la panne. Nous vous annonçons le créneau et le prix du déplacement avant de partir. Nous intervenons pour le dépannage, l\'entretien et l\'installation de climatisations et de pompes à chaleur air-air réversibles, en maison comme dans les locaux professionnels.',
                'local' => [
                    ['Remplacer les convecteurs', 'Dans les pavillons chauffés à l\'électricité, une pompe à chaleur air-air réversible remplace souvent les convecteurs : chauffage l\'hiver, fraîcheur l\'été. Nous calculons la puissance adaptée.'],
                    ['Bureaux et ateliers', 'Pour un local professionnel, une climatisation en panne pendant une vague de chaleur pèse vite sur le travail : nous intervenons aussi pour les entreprises.'],
                ],
                'faq' => [
                    ['Une pompe à chaleur air-air peut-elle chauffer toute la maison ?', 'Avec plusieurs unités intérieures, oui, selon la surface et l\'isolation. Nous vous disons franchement si un appoint reste utile dans certaines pièces.'],
                    ['Ma télécommande ne répond plus : la clim est-elle en panne ?', 'Vérifiez d\'abord les piles et le disjoncteur dédié. Si l\'appareil ne réagit toujours pas, notez les voyants allumés et appelez-nous.'],
                ],
            ],
        ],
        'quetigny' => [
            'ville' => [
                'title' => 'Électricien, plombier, chauffagiste à Quetigny (21) | EMAE',
                'h1' => 'Électricité, plomberie et chauffage à Quetigny et à l\'est de Dijon',
                'meta' => 'Quetigny, Chevigny-Saint-Sauveur, Saint-Apollinaire, Couternon : électricien, plombier, chauffagiste. Maisons et appartements, prix annoncé avant.',
                'intro' => 'Quetigny s\'est développée surtout à partir des années 1970, en quartiers de maisons individuelles et de petits immeubles, aujourd\'hui reliés au centre de Dijon par le tramway. Autour, Chevigny-Saint-Sauveur, Saint-Apollinaire, Couternon et Varois-et-Chaignot complètent l\'est de la métropole. Dans ces logements des années 1970 à 1990, ce sont souvent les tableaux électriques, les chauffe-eau et les chaudières d\'origine qui arrivent en fin de vie. Appelez-nous : nous faisons le point, puis nous vous annonçons le créneau et le prix du déplacement avant de partir.',
                'local' => [
                    ['Des logements qui ont quarante ans', 'Tableaux sans protection différentielle complète, chauffe-eau entartrés, chaudières anciennes : les équipements d\'origine des quartiers des années 1970 et 1980 demandent souvent une remise à niveau. Nous vous disons ce qui est urgent et ce qui peut attendre.'],
                    ['Maison ou copropriété', 'En petit collectif, une fuite ou une panne de chauffage peut concerner les parties communes : nous vous aidons à faire la différence et vous remettons un compte rendu pour le syndic.'],
                ],
                'faq' => [
                    ['Intervenez-vous dans tout l\'est de la métropole ?', 'Oui : Quetigny, Chevigny-Saint-Sauveur, Saint-Apollinaire, Sennecey-lès-Dijon, Couternon, Varois-et-Chaignot, Crimolois, Bressey-sur-Tille et Dijon.'],
                    ['Ma maison date des années 1970 : faut-il refaire l\'électricité ?', 'Pas forcément entièrement. Nous vérifions la protection différentielle, la terre et l\'état du tableau, puis nous vous proposons une mise en sécurité ou une rénovation, avec un devis gratuit pour chacune.'],
                ],
            ],
            'electricien' => [
                'title' => 'Électricien à Quetigny (21) – panne, tableau, rénovation | EMAE',
                'h1' => 'Électricien à Quetigny : pannes, tableaux et rénovation',
                'meta' => 'Panne de courant, tableau des années 70 à remplacer, prises et circuits à Quetigny, Chevigny, Saint-Apollinaire : électricien, prix annoncé avant.',
                'intro' => 'Un disjoncteur qui saute dans une maison des années 1970, une prise qui chauffe, un tableau d\'origine à remplacer avant de refaire la cuisine : à Quetigny et dans l\'est de la métropole, appelez-nous et décrivez la panne. Nous vous disons s\'il faut couper le courant en attendant, puis nous vous annonçons le créneau et le prix du déplacement avant de partir. Sur place, le technicien établit le diagnostic et vous donne le prix de la réparation avant de commencer.',
                'local' => [
                    ['Tableaux d\'origine', 'Dans beaucoup de maisons de Quetigny, le tableau est celui de la construction : peu de circuits, protection différentielle incomplète. Avant une rénovation de cuisine ou de salle de bains, c\'est souvent la première chose à reprendre.'],
                    ['Rénover sans tout casser', 'Ajouter des prises, créer un circuit pour une plaque à induction ou un sèche-linge, refaire l\'éclairage : nous passons par les gaines existantes quand c\'est possible, devis gratuit à l\'appui.'],
                ],
                'faq' => [
                    ['Je refais ma cuisine : faut-il changer le tableau ?', 'Une cuisine équipée demande plusieurs circuits dédiés (plaque, four, lave-vaisselle…). Si votre tableau n\'a pas la place ou la protection nécessaire, nous vous proposons de le compléter ou de le remplacer.'],
                    ['Une prise grésille : est-ce dangereux ?', 'Oui : coupez le circuit au tableau et n\'utilisez plus la prise. Un mauvais contact peut chauffer et provoquer un départ de feu. Appelez-nous pour la remplacer.'],
                ],
            ],
            'plombier' => [
                'title' => 'Plombier à Quetigny (21) – fuite, dégât des eaux, chauffe-eau | EMAE',
                'h1' => 'Plombier à Quetigny : fuites, dégâts des eaux et chauffe-eau',
                'meta' => 'Fuite, dégât des eaux, WC bouché, chauffe-eau en panne à Quetigny, Chevigny, Couternon : plombier en maison et en appartement, prix annoncé avant.',
                'intro' => 'Une fuite qui marque le plafond du voisin, un WC bouché, un chauffe-eau d\'origine qui rend l\'âme : à Quetigny et dans l\'est de la métropole, appelez-nous. Nous vous expliquons comment couper l\'eau et limiter les dégâts, puis nous vous annonçons le créneau et le prix du déplacement avant de partir. Nous intervenons en maison comme en petit collectif, pour les fuites, les débouchages, les chauffe-eau et la robinetterie.',
                'local' => [
                    ['Chauffe-eau et robinetterie d\'origine', 'Dans les logements des années 1970 à 1990, beaucoup de chauffe-eau et de mitigeurs arrivent en fin de vie, aidés par une eau calcaire : nous vous disons si une réparation suffit ou s\'il vaut mieux remplacer.'],
                    ['Dégât des eaux en petit collectif', 'Quand une fuite touche le voisin, il faut savoir vite d\'où elle vient : nous recherchons l\'origine et vous remettons un compte rendu utile pour le constat amiable.'],
                ],
                'faq' => [
                    ['Mon chauffe-eau a plus de quinze ans : faut-il le changer ?', 'Pas obligatoirement s\'il fonctionne bien. S\'il fuit par la cuve ou chauffe de plus en plus mal, le remplacement est souvent plus raisonnable qu\'une réparation : nous vous remettons un devis gratuit.'],
                    ['Une fuite vient du logement du dessus : que faire ?', 'Prévenez votre voisin et le syndic, coupez l\'électricité si l\'eau approche d\'un appareil, prenez des photos. Nous pouvons rechercher l\'origine si elle est chez vous ou si votre voisin nous mandate.'],
                ],
            ],
            'chauffagiste' => [
                'title' => 'Chauffagiste à Quetigny (21) – chaudière gaz, PAC, entretien | EMAE',
                'h1' => 'Chauffagiste à Quetigny : chaudières gaz, pompes à chaleur, entretien',
                'meta' => 'Chaudière gaz en panne, remplacement, pompe à chaleur, entretien annuel à Quetigny et dans l\'est de Dijon : chauffagiste, prix annoncé avant de partir.',
                'intro' => 'Une chaudière gaz qui se met en sécurité, une pression qui chute, des radiateurs qui chauffent mal : à Quetigny et dans l\'est de la métropole, appelez-nous avec la marque de l\'appareil et le message affiché. Nous vous annonçons le créneau et le prix du déplacement avant de partir. Pour l\'entretien annuel, vous pouvez aussi choisir un créneau en ligne. Nous intervenons sur chaudières gaz, pompes à chaleur, radiateurs et thermostats, en maison comme en appartement.',
                'local' => [
                    ['Chaudières gaz en fin de vie', 'Beaucoup de logements ont encore une chaudière gaz ancienne : quand les pannes se répètent, nous vous présentons les solutions de remplacement (chaudière récente ou pompe à chaleur), avec un devis gratuit pour chacune.'],
                    ['Thermostats et économies', 'Un thermostat programmable bien réglé évite de chauffer une maison vide : nous l\'installons et le réglons avec vous.'],
                ],
                'faq' => [
                    ['Ma chaudière gaz perd de la pression : que faire ?', 'Remettez de l\'eau jusqu\'à la pression indiquée dans la notice (souvent entre 1 et 1,5 bar à froid). Si elle rechute en quelques jours, il y a une fuite ou un vase d\'expansion à vérifier : appelez-nous.'],
                    ['Combien de temps dure une chaudière ?', 'Souvent quinze à vingt ans, selon l\'entretien. Au-delà, les pièces deviennent difficiles à trouver : nous vous disons franchement s\'il vaut mieux réparer ou remplacer.'],
                ],
            ],
            'climatisation' => [
                'title' => 'Climatisation à Quetigny (21) – dépannage, entretien, pose | EMAE',
                'h1' => 'Climatisation à Quetigny : dépannage, entretien et installation',
                'meta' => 'Climatisation en panne ou à installer à Quetigny, Chevigny-Saint-Sauveur, Saint-Apollinaire : dépannage, entretien, pompe à chaleur air-air. Prix annoncé.',
                'intro' => 'Une maison à étage où les chambres deviennent étouffantes l\'été, une climatisation qui goutte ou ne démarre plus : à Quetigny et dans l\'est de la métropole, appelez-nous et décrivez l\'appareil et la panne. Nous vous annonçons le créneau et le prix du déplacement avant de partir. Nous intervenons pour le dépannage, l\'entretien et l\'installation de climatisations et de pompes à chaleur air-air réversibles, en maison, en appartement et dans les commerces.',
                'local' => [
                    ['Maisons à étage', 'Dans les maisons des années 1970 et 1980, l\'étage chauffe vite en été : une climatisation réversible dans les chambres apporte du confort la nuit et chauffe aussi l\'hiver.'],
                    ['Commerces et bureaux', 'Pour un commerce ou un bureau, nous intervenons sur les climatisations existantes et installons de nouveaux appareils, avec une facture au nom de l\'entreprise.'],
                ],
                'faq' => [
                    ['Peut-on installer une clim en appartement à Quetigny ?', 'Oui, avec l\'accord de la copropriété pour l\'unité extérieure. Nous vous fournissons le devis et les caractéristiques de l\'appareil pour la demande.'],
                    ['Ma clim sent l\'humidité : que faire ?', 'C\'est souvent un filtre ou un bac de condensats encrassé. Un entretien avec nettoyage de l\'unité intérieure règle le problème dans la plupart des cas.'],
                ],
            ],
        ],
        'pontarlier' => [
            'ville' => [
                'title' => 'Électricien, plombier, chauffagiste à Pontarlier (25) | EMAE',
                'h1' => 'Électricité, plomberie et chauffage à Pontarlier et dans le Haut-Doubs',
                'meta' => 'Pontarlier, Doubs, Houtaud, La Cluse-et-Mijoux : chauffage, gel, panne de courant, fuite. Électricien, plombier, chauffagiste, prix annoncé avant.',
                'intro' => 'À plus de 800 mètres d\'altitude, Pontarlier compte parmi les villes les plus froides de France : ici, le chauffage tourne une grande partie de l\'année et le gel est une vraie menace pour les canalisations. Entre les immeubles du centre, les maisons du Haut-Doubs et les villages vers la frontière suisse, d\'Houtaud à La Cluse-et-Mijoux, chaudières fioul, poêles, pompes à chaleur et chauffages électriques se côtoient. Appelez-nous : nous faisons le point, puis nous vous annonçons le créneau et le prix du déplacement avant de partir.',
                'local' => [
                    ['Des hivers longs et froids', 'Les longues périodes de gel sollicitent les chaudières, les pompes à chaleur et les installations électriques : un entretien avant l\'hiver et des canalisations protégées évitent les pannes au pire moment.'],
                    ['Maisons du Haut-Doubs', 'Les fermes et maisons traditionnelles, avec leurs grands volumes et leurs dépendances, ont souvent des installations reprises au fil du temps : nous adaptons le diagnostic à ce type de bâti.'],
                ],
                'faq' => [
                    ['Intervenez-vous à Pontarlier même en hiver ?', 'Oui. En période de neige, prévenez-nous des conditions d\'accès à votre maison : le délai réel dépend de la route et des interventions en cours, et nous vous le confirmons au téléphone avant de partir.'],
                    ['Intervenez-vous dans les villages autour de Pontarlier ?', 'Oui : Doubs, Houtaud, Arçon, Dommartin, Vuillecin, Granges-Narboz, Sainte-Colombe, La Cluse-et-Mijoux, Les Verrières-de-Joux et les communes voisines. Pour un village plus éloigné, appelez-nous.'],
                ],
            ],
            'electricien' => [
                'title' => 'Électricien à Pontarlier (25) – panne, chauffage électrique | EMAE',
                'h1' => 'Électricien à Pontarlier : pannes, tableaux et chauffage électrique',
                'meta' => 'Panne de courant, disjoncteur qui saute par grand froid, tableau à Pontarlier et dans le Haut-Doubs : électricien, prix du déplacement annoncé avant.',
                'intro' => 'Par grand froid, quand tous les chauffages électriques tournent, le disjoncteur général saute ; une coupure dans une maison du Haut-Doubs, c\'est vite une maison qui se refroidit. À Pontarlier et dans les villages autour, appelez-nous et décrivez ce qui se passe : nous vous disons quoi vérifier en attendant, puis nous vous annonçons le créneau et le prix du déplacement avant de partir. Sur place, le technicien trouve la cause et vous donne le prix de la réparation avant de commencer.',
                'local' => [
                    ['Chauffage électrique et puissance', 'Beaucoup de logements sont chauffés à l\'électricité : quand tout fonctionne en même temps, l\'abonnement ou un circuit est dépassé. Nous vérifions la répartition et proposons des solutions, comme un délesteur.'],
                    ['Câbles chauffants et extérieurs', 'Câbles antigel sur les canalisations, éclairage extérieur, garages : ces circuits exposés au froid et à l\'humidité doivent être bien protégés. Nous les contrôlons et les remettons aux normes.'],
                ],
                'faq' => [
                    ['Qu\'est-ce qu\'un délesteur, et en ai-je besoin ?', 'C\'est un appareil qui coupe brièvement certains chauffages quand la consommation approche de la limite de l\'abonnement, pour éviter que le disjoncteur saute. Il est utile si vous avez beaucoup de chauffage électrique.'],
                    ['Mon câble chauffant antigel ne chauffe plus : que faire ?', 'Vérifiez sa protection au tableau et sa prise. S\'il est alimenté mais ne chauffe pas, il doit être remplacé avant les prochaines gelées : appelez-nous.'],
                ],
            ],
            'plombier' => [
                'title' => 'Plombier à Pontarlier (25) – gel, fuite, chauffe-eau | EMAE',
                'h1' => 'Plombier à Pontarlier : canalisations gelées, fuites, chauffe-eau',
                'meta' => 'Canalisation gelée ou éclatée, fuite, chauffe-eau en panne à Pontarlier et dans le Haut-Doubs : plombier, prix du déplacement annoncé avant de partir.',
                'intro' => 'Un tuyau gelé dans un garage, une canalisation qui éclate au dégel, plus d\'eau chaude un matin à moins dix : à Pontarlier, le froid fait partie des causes de panne les plus fréquentes. Appelez-nous : nous vous expliquons comment couper l\'eau et limiter les dégâts, puis nous vous annonçons le créneau et le prix du déplacement avant de partir. Nous intervenons à Pontarlier et dans les villages du Haut-Doubs pour les fuites, le gel, les chauffe-eau et la robinetterie.',
                'local' => [
                    ['Le gel, première cause de fuite', 'Robinets extérieurs, garages, vides sanitaires, maisons secondaires inoccupées : les canalisations exposées gèlent et éclatent. Nous réparons, puis nous isolons ou déplaçons ce qui doit l\'être.'],
                    ['Maisons inoccupées l\'hiver', 'Pour une maison qui reste vide, nous pouvons vidanger les canalisations ou vous conseiller une température hors gel : la meilleure façon d\'éviter un dégât des eaux au retour.'],
                ],
                'faq' => [
                    ['Comment protéger une maison secondaire du gel ?', 'Coupez l\'eau au compteur, vidangez les canalisations et le chauffe-eau, ou gardez un chauffage en mode hors gel. Nous pouvons faire la vidange et la remise en eau pour vous.'],
                    ['Mon robinet extérieur a gelé : que faire ?', 'Coupez son alimentation à l\'intérieur si une vanne existe, ouvrez-le et laissez dégeler doucement. S\'il fuit au dégel, la canalisation a peut-être éclaté : appelez-nous.'],
                ],
            ],
            'chauffagiste' => [
                'title' => 'Chauffagiste à Pontarlier (25) – chaudière, PAC, entretien | EMAE',
                'h1' => 'Chauffagiste à Pontarlier : chaudières, pompes à chaleur, entretien',
                'meta' => 'Chaudière fioul ou gaz en panne, pompe à chaleur par grand froid, entretien à Pontarlier et dans le Haut-Doubs : chauffagiste, prix annoncé avant.',
                'intro' => 'À Pontarlier, une panne de chauffage en hiver ne peut pas attendre : une chaudière fioul qui se met en sécurité, une pompe à chaleur qui givre et s\'arrête, des radiateurs froids dans une grande maison du Haut-Doubs. Appelez-nous avec la marque de l\'appareil et le message affiché : nous vous annonçons le créneau et le prix du déplacement avant de partir. Pour l\'entretien annuel, vous pouvez choisir un créneau en ligne, de préférence avant l\'hiver.',
                'local' => [
                    ['Fioul, bois et pompes à chaleur', 'Dans le Haut-Doubs, les chaudières fioul restent nombreuses, souvent avec un poêle à bois en complément, et les pompes à chaleur progressent. Nous intervenons sur les chaudières, les pompes à chaleur et les circuits de radiateurs.'],
                    ['Pompe à chaleur et grand froid', 'Par très grand froid, le rendement d\'une pompe à chaleur baisse : un bon dimensionnement et un appoint prévu dès l\'installation évitent d\'avoir froid. Nous vous le disons franchement avant le devis.'],
                ],
                'faq' => [
                    ['Une pompe à chaleur convient-elle à Pontarlier ?', 'Oui, à condition d\'être bien dimensionnée pour le climat et d\'avoir un appoint pour les jours les plus froids. Nous étudions votre maison avant de vous proposer un appareil.'],
                    ['Quand faire l\'entretien de ma chaudière ?', 'Avant l\'hiver, en septembre ou en octobre : vous évitez la panne au moment où le chauffage tourne en continu, et les créneaux sont plus faciles à trouver.'],
                ],
            ],
            'climatisation' => [
                'title' => 'Climatisation à Pontarlier (25) – PAC air-air, entretien | EMAE',
                'h1' => 'Climatisation et pompe à chaleur air-air à Pontarlier',
                'meta' => 'Pompe à chaleur air-air réversible, climatisation en panne, entretien à Pontarlier et dans le Haut-Doubs : dépannage et installation, prix annoncé avant.',
                'intro' => 'À Pontarlier, une climatisation sert surtout de chauffage : une pompe à chaleur air-air réversible chauffe pendant les longs hivers et rafraîchit pendant les journées chaudes de l\'été. Un appareil qui ne chauffe plus, qui givre ou qui affiche un code d\'erreur ? Appelez-nous et décrivez la panne : nous vous annonçons le créneau et le prix du déplacement avant de partir. Nous intervenons pour le dépannage, l\'entretien et l\'installation de pompes à chaleur air-air et de climatisations.',
                'local' => [
                    ['Choisir un appareil adapté au froid', 'Tous les modèles ne se valent pas par grand froid : nous vous proposons des appareils prévus pour fonctionner à basse température extérieure, et vous disons quand un appoint reste nécessaire.'],
                    ['Unité extérieure et neige', 'L\'unité extérieure doit être surélevée et protégée des chutes de neige du toit, pour que le dégivrage se fasse bien. Nous en tenons compte dès l\'installation.'],
                ],
                'faq' => [
                    ['Ma pompe à chaleur air-air dégivre souvent : est-ce normal ?', 'Oui, par temps froid et humide, des cycles de dégivrage réguliers sont normaux. Si l\'unité reste prise dans la glace ou s\'arrête, appelez-nous.'],
                    ['Faut-il entretenir une pompe à chaleur air-air ?', 'Oui : nettoyage des filtres par vous-même régulièrement, et un entretien complet par un professionnel une fois par an pour garder un bon rendement.'],
                ],
            ],
        ],
        'montbeliard' => [
            'ville' => [
                'title' => 'Électricien, plombier, chauffagiste à Montbéliard (25) | EMAE',
                'h1' => 'Électricité, plomberie et chauffage à Montbéliard et dans son pays',
                'meta' => 'Montbéliard, Audincourt, Valentigney, Sochaux, Bethoncourt : électricien, plombier, chauffagiste en maison et appartement. Prix annoncé avant de partir.',
                'intro' => 'Du centre de Montbéliard, au pied du château des ducs de Wurtemberg, aux quartiers comme la Petite-Hollande, et aux communes du pays de Montbéliard, d\'Audincourt à Sochaux et Valentigney, l\'habitat est très varié : maisons anciennes, cités ouvrières construites autour de l\'industrie automobile, grands ensembles des années 1960 et 1970, pavillons récents. Électricité, plomberie, chauffage, climatisation : un seul numéro. Appelez-nous : nous faisons le point, puis nous vous annonçons le créneau et le prix du déplacement avant de partir.',
                'local' => [
                    ['Cités ouvrières et maisons mitoyennes', 'Dans les cités construites pour les ouvriers de l\'industrie, beaucoup de maisons mitoyennes ont des installations électriques et des canalisations reprises par étapes : nous repérons ce qui doit être mis en sécurité, devis gratuit à l\'appui.'],
                    ['Grands ensembles', 'En immeuble, la panne peut venir de votre logement ou des installations communes : nous vous aidons à faire la différence et vous remettons un compte rendu pour le bailleur ou le syndic.'],
                ],
                'faq' => [
                    ['Intervenez-vous dans tout le pays de Montbéliard ?', 'Oui : Montbéliard, Audincourt, Valentigney, Bethoncourt, Grand-Charmont, Sochaux, Exincourt, Courcelles-lès-Montbéliard, Hérimoncourt et les communes voisines.'],
                    ['Je suis locataire d\'un logement social : qui appeler ?', 'Pour une panne sur un équipement à la charge du bailleur (chaudière, colonne, parties communes), contactez d\'abord votre bailleur. Pour ce qui est à votre charge, nous pouvons intervenir.'],
                ],
            ],
            'electricien' => [
                'title' => 'Électricien à Montbéliard (25) – panne, tableau, mise aux normes | EMAE',
                'h1' => 'Électricien à Montbéliard : pannes et mises en sécurité',
                'meta' => 'Panne de courant, disjoncteur, tableau ancien à Montbéliard, Audincourt, Valentigney : électricien en maison et appartement. Prix annoncé avant de partir.',
                'intro' => 'Un appartement sans courant, un tableau ancien dans une maison de cité, un disjoncteur qui saute dès qu\'on branche le four : à Montbéliard et dans les communes du pays, appelez-nous et décrivez ce qui se passe. Nous vous disons s\'il faut couper le courant en attendant, puis nous vous annonçons le créneau et le prix du déplacement avant de partir. Sur place, le technicien recherche la panne et vous donne le prix de la réparation avant de commencer.',
                'local' => [
                    ['Installations reprises au fil des années', 'Dans les maisons mitoyennes des cités et les appartements anciens, l\'électricité a souvent été complétée par morceaux, parfois sans terre ni différentiel 30 mA : nous vous proposons une mise en sécurité adaptée.'],
                    ['Avant de louer', 'Un logement mis en location doit avoir une installation sans danger : nous réalisons les travaux demandés après le diagnostic électrique, avec un devis gratuit.'],
                ],
                'faq' => [
                    ['Mon appartement n\'a pas de prise de terre : est-ce grave ?', 'Sans terre, un défaut sur un appareil peut le rendre dangereux au toucher. Une protection différentielle 30 mA limite le risque ; nous vérifions si la terre de l\'immeuble peut être raccordée.'],
                    ['Le courant est coupé dans tout l\'immeuble : que faire ?', 'Si plusieurs logements sont touchés, contactez le bailleur ou le syndic, ou Enedis si tout le quartier est coupé. Si seul votre logement est privé de courant, appelez-nous.'],
                ],
            ],
            'plombier' => [
                'title' => 'Plombier à Montbéliard (25) – fuite, débouchage, chauffe-eau | EMAE',
                'h1' => 'Plombier à Montbéliard : fuites, débouchages, chauffe-eau',
                'meta' => 'Fuite, WC bouché, dégât des eaux, chauffe-eau en panne à Montbéliard, Audincourt, Sochaux : plombier en maison et appartement, prix annoncé avant.',
                'intro' => 'Une fuite qui coule chez le voisin du dessous, une évacuation bouchée, un chauffe-eau qui ne chauffe plus : à Montbéliard et dans le pays de Montbéliard, appelez-nous. Nous vous expliquons comment couper l\'eau de votre logement, puis nous vous annonçons le créneau et le prix du déplacement avant de partir. Nous intervenons en maison comme en appartement pour les fuites, les débouchages, les chauffe-eau et la robinetterie.',
                'local' => [
                    ['Immeubles et colonnes communes', 'En immeuble, une fuite peut venir de votre logement ou d\'une colonne commune : nous recherchons l\'origine et vous remettons un compte rendu utile pour le bailleur, le syndic et l\'assurance.'],
                    ['Maisons mitoyennes', 'Dans les cités, une fuite peut passer d\'une maison à l\'autre par un mur commun : nous la localisons avant d\'ouvrir, pour limiter les travaux.'],
                ],
                'faq' => [
                    ['Qui paie la réparation d\'une fuite en logement locatif ?', 'Les petites réparations (joints, robinets, débouchage de l\'évier) sont en général à la charge du locataire ; les canalisations encastrées et les colonnes, au propriétaire ou à la copropriété. Nous vous aidons à y voir clair.'],
                    ['Que faire en cas de dégât des eaux ?', 'Coupez l\'eau, coupez l\'électricité si l\'eau approche d\'un appareil, prévenez votre voisin et votre assurance, prenez des photos. Puis appelez-nous pour trouver et réparer la fuite.'],
                ],
            ],
            'chauffagiste' => [
                'title' => 'Chauffagiste à Montbéliard (25) – chaudière, PAC, entretien | EMAE',
                'h1' => 'Chauffagiste à Montbéliard : chaudières, pompes à chaleur, entretien',
                'meta' => 'Chaudière gaz en panne, pompe à chaleur, radiateurs froids, entretien annuel à Montbéliard et dans son pays : chauffagiste, prix annoncé avant de partir.',
                'intro' => 'Une chaudière gaz en sécurité, des radiateurs tièdes, une pompe à chaleur en défaut : à Montbéliard et dans les communes du pays, appelez-nous avec la marque de l\'appareil et le message affiché. Nous vous annonçons le créneau et le prix du déplacement avant de partir. Pour l\'entretien annuel, vous pouvez choisir un créneau en ligne. Nous intervenons sur chaudières gaz et fioul, pompes à chaleur, radiateurs et thermostats, en maison comme en appartement.',
                'local' => [
                    ['Chauffage individuel ou collectif', 'Dans les grands ensembles, le chauffage est souvent collectif : nous intervenons alors sur ce qui est à vous (radiateurs, robinets thermostatiques). En maison, nous dépannons et entretenons chaudières et pompes à chaleur.'],
                    ['Remplacer une vieille chaudière', 'Dans les maisons des cités, beaucoup de chaudières arrivent en fin de vie : nous vous présentons les solutions de remplacement, avec un devis gratuit pour chacune.'],
                ],
                'faq' => [
                    ['Mon radiateur reste froid alors que les autres chauffent : pourquoi ?', 'Il peut être plein d\'air, ou son robinet thermostatique est bloqué. Purgez-le ; si cela ne suffit pas, appelez-nous pour débloquer le robinet ou équilibrer le circuit.'],
                    ['Je suis locataire : qui fait entretenir la chaudière ?', 'L\'entretien annuel d\'une chaudière individuelle est à la charge du locataire. Nous vous remettons l\'attestation à garder et à présenter à votre bailleur ou votre assureur.'],
                ],
            ],
            'climatisation' => [
                'title' => 'Climatisation à Montbéliard (25) – dépannage, entretien, pose | EMAE',
                'h1' => 'Climatisation à Montbéliard : dépannage, entretien et installation',
                'meta' => 'Clim en panne, entretien ou pose de pompe à chaleur air-air à Montbéliard, Audincourt, Valentigney : maisons, appartements, commerces. Prix annoncé.',
                'intro' => 'Un appartement en dernier étage qui devient une étuve, une climatisation de commerce qui s\'arrête en plein été, un appareil qui goutte sur le balcon : à Montbéliard et dans le pays de Montbéliard, appelez-nous et décrivez l\'appareil et la panne. Nous vous annonçons le créneau et le prix du déplacement avant de partir. Nous intervenons pour le dépannage, l\'entretien et l\'installation de climatisations et de pompes à chaleur air-air réversibles.',
                'local' => [
                    ['Appartements et copropriétés', 'Poser une unité extérieure sur un balcon ou en façade demande souvent l\'accord de la copropriété ou du bailleur : nous vous fournissons le devis et les caractéristiques nécessaires.'],
                    ['Commerces et entreprises', 'Pour un commerce, un bureau ou un atelier, nous dépannons et entretenons les climatisations existantes et installons de nouveaux appareils, avec une facture au nom de l\'entreprise.'],
                ],
                'faq' => [
                    ['Peut-on installer une clim dans un logement loué ?', 'Oui, avec l\'accord écrit du propriétaire, et de la copropriété pour l\'unité extérieure. Nous vous fournissons les éléments pour faire la demande.'],
                    ['Ma clim ne refroidit plus : faut-il recharger le gaz ?', 'Pas toujours : un filtre encrassé ou une unité extérieure obstruée donnent les mêmes symptômes. S\'il manque du fluide, il y a une fuite à trouver et réparer avant toute recharge.'],
                ],
            ],
        ],
    ];
}

/** Pannes courantes par métier, quand la page n'a pas sa propre liste. */
function city_trade_pannes_default(string $tslug): array
{
    return [
        'electricien'   => ['Panne de courant totale ou dans une seule pièce', 'Disjoncteur ou différentiel qui saute', 'Tableau électrique ancien ou sans différentiel 30 mA : remplacement', 'Prise ou interrupteur qui chauffe, grésille ou noircit', 'Court-circuit après un dégât des eaux', 'Ajout de prises ou de circuits', 'Mise aux normes NF C 15-100 avant une vente ou une location'],
        'plombier'      => ['Fuite d\'eau visible ou trace d\'humidité au plafond', 'WC, évier, douche ou canalisation bouchés', 'Chauffe-eau qui ne chauffe plus ou qui fuit', 'Robinet, mitigeur ou chasse d\'eau qui coule en continu', 'Groupe de sécurité du chauffe-eau qui goutte', 'Remplacement de robinetterie, de WC ou de chauffe-eau'],
        'chauffagiste'  => ['Chaudière gaz ou fioul en sécurité ou qui ne démarre plus', 'Pompe à chaleur en défaut, code d\'erreur ou givrage', 'Radiateurs froids ou chauffage inégal', 'Thermostat d\'ambiance ou programmation à remplacer', 'Entretien annuel de chaudière ou de pompe à chaleur', 'Remplacement de chaudière ou passage à la pompe à chaleur'],
        'climatisation' => ['Climatisation qui ne refroidit plus', 'Unité intérieure qui goutte ou fuit', 'Appareil qui ne démarre plus ou affiche un code d\'erreur', 'Bruit anormal ou mauvaise odeur', 'Entretien et nettoyage des unités', 'Installation de climatisation ou de pompe à chaleur air-air réversible'],
    ][$tslug] ?? [];
}
