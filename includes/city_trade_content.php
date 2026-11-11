<?php
declare(strict_types=1);

/* ═══════════════════════════════════════════════════
   CONTENU PROPRE DES PAGES MÉTIER × VILLE
   Une page qui a son contenu ici est ouverte à Google (elle n'est plus un doublon).
   Règles (voir passation) : uniquement des faits vérifiables (communes, axes, type de
   bâti courant, chantiers réels) ; aucun délai chiffré, aucune note, aucune certification ;
   prix jamais écrits ici (la FAQ des prix est générée depuis la page Tarifs) ;
   pas de décennale sur la plomberie (non couverte par l'attestation).
   Clés : intro (texte du haut), pannes (liste), local ([titre, texte]…), faq ([q, r]…),
   meta (description Google, 155 caractères au plus).
═══════════════════════════════════════════════════ */
function city_trade_content(string $ville, string $tslug): ?array
{
    static $c = null;
    $c ??= [
        'beaune' => [
            'electricien' => [
                'meta'   => 'Panne de courant, disjoncteur qui saute, tableau à refaire à Beaune ? Électricien assuré, prix du déplacement annoncé avant de partir.',
                'intro'  => 'Plus de courant dans la cuisine, un disjoncteur qui saute dès que le four démarre, une prise qui chauffe : une panne électrique n\'attend pas, et vous voulez savoir qui va venir et combien ça va coûter. Appelez-nous : nous posons les bonnes questions pour comprendre la panne, puis nous vous disons quand nous pouvons être chez vous et combien coûte le déplacement, avant de partir. Nous venons de notre base de Dole par l\'A36, à Beaune comme à Pommard, Meursault ou Savigny-lès-Beaune. Sur place, le technicien établit le diagnostic, vous annonce le prix de la réparation et ne commence qu\'avec votre accord.',
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
                'meta'   => 'Fuite d\'eau, WC ou évier bouché, chauffe-eau en panne à Beaune ? Plombier à votre écoute 7j/7, prix du déplacement annoncé avant de partir.',
                'intro'  => 'Une fuite sous l\'évier, un WC qui déborde, plus d\'eau chaude au réveil : en plomberie, chaque heure compte, surtout quand l\'eau coule. Appelez-nous : nous vous expliquons comment couper l\'eau et limiter les dégâts en attendant, puis nous vous confirmons le créneau et le prix du déplacement avant de partir de Dole. Nous intervenons à Beaune et dans les villages autour, de Chorey-les-Beaune à Meursault. Sur place, nous trouvons l\'origine de la fuite ou du bouchon et vous annonçons le prix avant toute réparation.',
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
                'intro'  => 'Une chaudière qui se met en sécurité, une pompe à chaleur qui affiche un code d\'erreur, des radiateurs froids alors que l\'hiver arrive : sans chauffage, la maison se refroidit vite. Appelez-nous : décrivez-nous la panne et le code affiché, nous vous confirmons le créneau et le prix du déplacement avant de partir de Dole. Nous intervenons à Beaune et dans les villages de la Côte, où nous avons déjà dépanné une pompe à chaleur et remplacé ses thermostats d\'ambiance. Sur place, le diagnostic et le prix de la réparation vous sont annoncés avant tout travail.',
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
                'intro'  => 'Une climatisation qui souffle de l\'air tiède, qui goutte ou qui s\'arrête en pleine chaleur : en été, la maison ou le commerce deviennent vite étouffants. Appelez-nous : nous faisons le point sur l\'appareil et la panne, puis nous vous confirmons le créneau et le prix du déplacement avant de partir de Dole. Nous intervenons à Beaune et autour, pour le dépannage, l\'entretien et l\'installation de climatisation et de pompes à chaleur réversibles.',
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
            'electricien' => [
                'meta'  => 'Électricien à Dijon : panne de courant, disjoncteur, tableau, mise aux normes en maison ou en appartement. Prix du déplacement annoncé avant de partir.',
                'intro' => 'Un disjoncteur qui saute dans un appartement du centre, une coupure dans une maison de Chenôve ou de Talant, une prise qui grésille : appelez-nous et décrivez la panne. Nous vous disons s\'il faut couper le courant en attendant, puis nous confirmons le créneau et le prix du déplacement avant de partir de Dole par l\'A39. Nous intervenons à Dijon et dans la métropole, de Quetigny à Longvic. Sur place, le diagnostic et le prix de la réparation vous sont annoncés avant tout travail.',
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
                'intro' => 'Une fuite qui coule chez le voisin du dessous, un WC bouché, plus d\'eau chaude : en appartement à Dijon, une fuite devient vite l\'affaire de tout l\'immeuble. Appelez-nous : nous vous indiquons comment couper l\'eau de votre logement, puis nous confirmons le créneau et le prix du déplacement avant de partir de Dole. Nous intervenons à Dijon et dans la métropole, de Fontaine-lès-Dijon à Chevigny-Saint-Sauveur.',
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
                'intro' => 'Une chaudière gaz qui se met en sécurité, des radiateurs tièdes, une pompe à chaleur qui affiche un code d\'erreur : appelez-nous avec la marque de l\'appareil et le message affiché. Nous confirmons le créneau et le prix du déplacement avant de partir de Dole par l\'A39. Nous intervenons à Dijon et dans la métropole, en appartement comme en maison, sur chaudières, pompes à chaleur, radiateurs et thermostats.',
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
                'intro' => 'Un appartement sous les toits qui devient une étuve, un commerce où la clim s\'arrête en plein été : appelez-nous et décrivez l\'appareil et la panne. Nous confirmons le créneau et le prix du déplacement avant de partir de Dole par l\'A39. Nous intervenons à Dijon et dans la métropole pour le dépannage, l\'entretien et l\'installation de climatisations et de pompes à chaleur air-air réversibles.',
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
        'besancon' => [
            'electricien' => [
                'meta'  => 'Électricien à Besançon : panne de courant, disjoncteur, tableau, mise aux normes, de la Boucle à Planoise. Prix du déplacement annoncé avant de partir.',
                'intro' => 'Une panne de courant dans un appartement de la Boucle, un disjoncteur qui saute dans une maison de Thise ou d\'École-Valentin : appelez-nous et décrivez ce qui se passe. Nous vous disons s\'il faut couper le courant en attendant, puis nous confirmons le créneau et le prix du déplacement avant de partir de Dole par l\'A36. Nous intervenons à Besançon et dans le Grand Besançon. Sur place, le diagnostic et le prix de la réparation vous sont annoncés avant tout travail.',
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
                'intro' => 'Une fuite sous l\'évier, une canalisation bouchée, un tuyau qui a gelé dans un garage ou une cave : appelez-nous. Nous vous indiquons comment couper l\'eau et limiter les dégâts, puis nous confirmons le créneau et le prix du déplacement avant de partir de Dole par l\'A36. Nous intervenons à Besançon et dans le Grand Besançon, en appartement comme en maison.',
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
                'intro' => 'À Besançon, l\'hiver est long : une chaudière en sécurité ou une pompe à chaleur en défaut ne peut pas attendre. Appelez-nous avec la marque de l\'appareil et le message affiché, nous confirmons le créneau et le prix du déplacement avant de partir de Dole par l\'A36. Nous intervenons à Besançon et dans le Grand Besançon, où nous avons déjà remplacé une pompe à chaleur Atlantic, sur chaudières gaz et fioul, pompes à chaleur, radiateurs et thermostats.',
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
                'intro' => 'Dans la cuvette de Besançon, les journées d\'été peuvent être étouffantes, surtout sous les toits. Une climatisation qui ne refroidit plus ou qui goutte ? Appelez-nous : nous faisons le point sur l\'appareil, puis nous confirmons le créneau et le prix du déplacement avant de partir de Dole par l\'A36. Nous intervenons à Besançon et autour pour le dépannage, l\'entretien et l\'installation de climatisations et de pompes à chaleur air-air réversibles.',
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
            'electricien' => [
                'meta'  => 'Électricien à Lons-le-Saunier : panne de courant, disjoncteur, tableau, mise aux normes. Prix du déplacement annoncé avant de partir.',
                'intro' => 'Une panne de courant à Lons-le-Saunier, un disjoncteur qui saute dans une maison de Montmorot ou de Perrigny : appelez-nous et décrivez ce qui se passe. Nous vous disons s\'il faut couper le courant en attendant, puis nous confirmons le créneau et le prix du déplacement avant de partir de Dole par l\'A39. Nous intervenons à Lons et dans les communes autour, du Revermont à la Bresse jurassienne. Sur place, le diagnostic et le prix de la réparation vous sont annoncés avant tout travail.',
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
                'intro' => 'Une fuite d\'eau à Lons-le-Saunier, un WC bouché à Montmorot, un chauffe-eau qui ne chauffe plus à Perrigny : appelez-nous. Nous vous indiquons comment couper l\'eau et limiter les dégâts, puis nous confirmons le créneau et le prix du déplacement avant de partir de Dole par l\'A39. Sur place, nous trouvons l\'origine de la fuite ou du bouchon et vous annonçons le prix avant toute réparation.',
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
                'intro' => 'Une chaudière fioul qui ne redémarre plus, une pompe à chaleur en défaut, des radiateurs froids : à Lons-le-Saunier et dans les villages autour, le chauffage ne peut pas attendre l\'hiver. Appelez-nous avec la marque de l\'appareil et le message affiché ; nous confirmons le créneau et le prix du déplacement avant de partir de Dole par l\'A39. Nous intervenons sur chaudières gaz et fioul, pompes à chaleur, radiateurs et thermostats.',
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
                'intro' => 'Une climatisation qui souffle tiède, qui goutte ou qui ne démarre plus à Lons-le-Saunier : appelez-nous et décrivez l\'appareil et la panne. Nous confirmons le créneau et le prix du déplacement avant de partir de Dole par l\'A39. Nous intervenons à Lons et autour pour le dépannage, l\'entretien et l\'installation de climatisations et de pompes à chaleur air-air réversibles, en maison comme en commerce.',
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
    ];
    $t = $c[$ville][$tslug] ?? null;
    if ($t === null) return null;
    $t['pannes'] ??= city_trade_pannes_default($tslug);
    return $t;
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
