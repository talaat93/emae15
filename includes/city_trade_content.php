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
    ];
    return $c[$ville][$tslug] ?? null;
}
