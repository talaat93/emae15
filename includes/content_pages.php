<?php
declare(strict_types=1);
/**
 * Pages de contenu ajoutées en novembre 2026 : pannes fréquentes, aides et primes,
 * estimation de prix, rendez-vous en ligne, contrats d'entretien.
 *
 * Chaque texte a sa valeur par défaut ICI, et nulle part ailleurs : la page et
 * l'écran « Textes des pages » (catalogue, admin_page_catalog) lisent la même.
 * Les pages qui dépendent d'informations que seul le propriétaire connaît
 * (prix, certification RGE, créneaux) sont désactivées par défaut ; un admin
 * connecté peut les prévisualiser avant de les activer, comme la page Tarifs.
 */

/** Adresses publiques connues, pour rediriger une ancienne adresse propre (/plomberie). */
function public_route_exists(string $slug): bool
{
    $slug = strtolower(trim($slug, '/'));
    if (in_array($slug, ['zones', 'avis', 'faq', 'contact', 'quote', 'realisations', 'services', 'mentions-legales',
                         'tarifs', 'pannes', 'aides', 'estimation', 'rendez-vous', 'entretien', 'espace-client'], true)) return true;
    if (isset(service_trade_defaults()[$slug]) || isset(panne_defaults()[$slug])) return true;
    return function_exists('page_by_slug') && page_by_slug($slug) !== null;
}

/** Texte d'une page de contenu : valeur saisie dans l'admin, sinon celle du code. */
function cp_text(string $key, string $default): string { return setting($key, $default); }

/** Une ligne par élément (listes à puces saisies dans un champ de texte). */
function cp_lines(string $key, string $default): array
{
    return array_values(array_filter(array_map('trim', preg_split('/\R/', cp_text($key, $default)) ?: []), 'strlen'));
}

/* ═══════════════════════════════════════════════════
   7. PANNES FRÉQUENTES
═══════════════════════════════════════════════════ */
function pannes_enabled(): bool { return global_setting('pannes_enabled', '1') === '1'; }

/**
 * Textes d'origine de chaque page panne. Champs : trade, h1, lead, checks (ce que
 * le client peut vérifier sans risque), never (à ne pas faire), alert (danger
 * immédiat), call (quand nous appeler). Les listes : une ligne par élément.
 */
function panne_defaults(): array
{
    return [
        'chaudiere-en-panne' => [
            'trade' => 'chauffage', 'icon' => '🔥', 'h1' => 'Chaudière en panne',
            'lead'  => 'Plus de chauffage ou plus d\'eau chaude ? Quelques vérifications simples peuvent vous dépanner, ou nous aider à venir avec la bonne pièce.',
            'checks'=> "La chaudière est bien alimentée : prise ou disjoncteur de la chaudière, interrupteur en façade.\nLa pression du circuit : sur la plupart des chaudières, elle se situe entre 1 et 1,5 bar à froid (voir la notice de votre appareil).\nLe thermostat d'ambiance : piles, température demandée, mode hiver activé.\nUn code d'erreur s'affiche : notez-le, il nous aide à préparer la bonne pièce.\nChaudière au gaz : le robinet de gaz est ouvert et vos autres appareils au gaz fonctionnent.",
            'never' => "N'ouvrez pas le capot de la chaudière : l'intérieur est réservé à un professionnel.\nNe réarmez pas la chaudière plus de deux fois de suite si elle se remet en sécurité.",
            'alert' => 'Odeur de gaz ? Ne touchez à aucun interrupteur, n\'allumez aucune flamme, ouvrez les fenêtres, fermez le robinet de gaz si vous pouvez le faire sans danger, sortez du logement et appelez Urgence Sécurité Gaz au 0 800 47 33 33 (appel gratuit, 24h/24).',
            'call'  => 'Appelez-nous si la chaudière reste en défaut après ces vérifications, si la pression baisse souvent, si elle fait un bruit inhabituel ou si l\'entretien annuel n\'a pas été fait.',
        ],
        'fuite-d-eau' => [
            'trade' => 'plomberie', 'icon' => '💧', 'h1' => 'Fuite d\'eau',
            'lead'  => 'Une fuite peut vite abîmer vos sols, vos murs ou le logement du dessous. Les bons gestes en attendant notre arrivée.',
            'checks'=> "Fermez le robinet d'arrêt général, souvent près du compteur d'eau ou sous l'évier.\nCoupez l'électricité de la pièce si l'eau s'approche de prises ou d'appareils électriques.\nÉpongez et placez un récipient sous la fuite.\nRepérez d'où vient l'eau : raccord, joint, tuyau, chauffe-eau, plafond.\nPrenez des photos : elles serviront pour votre assurance et pour nous.\nSi l'eau passe chez un voisin, prévenez-le, ainsi que le syndic en immeuble.",
            'never' => "N'utilisez pas d'appareil électrique les pieds dans l'eau.\nNe laissez pas durer un « goutte-à-goutte » : il abîme les plafonds et fait grimper la facture d'eau.",
            'alert' => 'L\'eau continue de couler alors que le robinet général est fermé, ou elle touche le tableau électrique ? Appelez-nous immédiatement.',
            'call'  => 'Appelez-nous pour toute fuite qui ne s\'arrête pas, une fuite sur le chauffe-eau, une tache d\'humidité qui s\'étend ou une facture d\'eau anormalement élevée.',
        ],
        'disjoncteur-qui-saute' => [
            'trade' => 'electricite', 'icon' => '⚡', 'h1' => 'Disjoncteur qui saute',
            'lead'  => 'Un disjoncteur qui saute vous protège : il signale un appareil défectueux, un circuit surchargé ou un défaut d\'isolement.',
            'checks'=> "Débranchez les appareils du circuit concerné, réarmez le disjoncteur, puis rebranchez-les un par un : celui qui fait sauter le courant est en cause.\nSi c'est l'interrupteur différentiel (30 mA) qui saute, coupez tous les disjoncteurs qu'il protège, réarmez-le, puis remettez-les un par un.\nSi c'est le disjoncteur général près du compteur, trop d'appareils fonctionnent peut-être en même temps pour votre abonnement.\nNotez quand cela arrive : par temps de pluie, avec un appareil précis, à une heure précise.",
            'never' => "Ne bloquez jamais un disjoncteur en position enclenchée.\nN'ouvrez pas le tableau électrique et ne touchez pas une prise noircie ou chaude.",
            'alert' => 'Odeur de brûlé, prise ou fil chaud ou noirci, étincelles : coupez le disjoncteur général et appelez-nous.',
            'call'  => 'Appelez-nous si le disjoncteur saute encore sans appareil branché, s\'il saute par temps humide, ou si votre installation est ancienne.',
        ],
        'plus-d-eau-chaude' => [
            'trade' => 'plomberie', 'icon' => '🚿', 'h1' => 'Plus d\'eau chaude',
            'lead'  => 'Chauffe-eau électrique, chaudière ou robinet : la cause dépend de votre installation. Voici comment s\'y retrouver.',
            'checks'=> "Chauffe-eau électrique : vérifiez son disjoncteur dans le tableau et le contacteur heures creuses (position « auto » ou « marche »).\nSi le ballon a été entièrement vidé dans la journée, il peut se réchauffer seulement pendant les heures creuses.\nEau chaude produite par la chaudière : vérifiez la pression et un éventuel code d'erreur.\nUn seul robinet sans eau chaude : la cartouche du mitigeur est probablement en cause.",
            'never' => "Ne vidangez pas vous-même un chauffe-eau.\nSi le groupe de sécurité du ballon fuit beaucoup, fermez l'arrivée d'eau froide du ballon et appelez-nous.",
            'alert' => '',
            'call'  => 'Appelez-nous si l\'eau reste froide ou tiède après ces vérifications, si le ballon fuit, ou s\'il a plus de 10 à 15 ans.',
        ],
        'climatisation-en-panne' => [
            'trade' => 'climatisation', 'icon' => '❄️', 'h1' => 'Climatisation en panne',
            'lead'  => 'Votre climatisation ne refroidit plus, coule ou fait du bruit ? Quelques contrôles simples avant de nous appeler.',
            'checks'=> "Télécommande : piles, mode froid, température demandée plus basse que celle de la pièce.\nFiltres de l'unité intérieure : un filtre encrassé réduit fortement le froid ; ils se nettoient à l'eau tiède.\nUnité extérieure : rien ne doit gêner la circulation de l'air autour.\nDisjoncteur de la climatisation dans le tableau électrique.",
            'never' => "N'ouvrez pas le circuit de fluide frigorigène : sa manipulation est réservée aux professionnels habilités.\nN'arrosez pas l'unité extérieure lorsqu'elle est sous tension.",
            'alert' => '',
            'call'  => 'Appelez-nous en cas de givre sur les tuyaux, d\'eau qui coule de l\'unité intérieure, d\'odeur, de bruit inhabituel ou de code d\'erreur.',
        ],
        'canalisation-bouchee' => [
            'trade' => 'plomberie', 'icon' => '🪠', 'h1' => 'Canalisation bouchée',
            'lead'  => 'Évier, douche ou WC qui s\'écoulent mal ? Les solutions simples à essayer, et les signes qui demandent un professionnel.',
            'checks'=> "Utilisez une ventouse, en bouchant le trop-plein du lavabo ou de l'évier.\nVersez de l'eau très chaude avec un peu de liquide vaisselle (pas d'eau bouillante sur une canalisation en PVC).\nNettoyez le siphon sous l'évier ou le lavabo, avec une bassine dessous.\nRegardez si un seul appareil est touché ou toute la maison : dans ce cas, le bouchon est plus loin sur l'évacuation.",
            'never' => "Ne mélangez jamais deux produits déboucheurs : le mélange peut dégager des vapeurs dangereuses.\nN'utilisez pas de produits chimiques agressifs à répétition : ils abîment les canalisations.",
            'alert' => '',
            'call'  => 'Appelez-nous si plusieurs évacuations sont bouchées, si l\'eau sale remonte, ou si une mauvaise odeur persiste.',
        ],
        'portail-bloque' => [
            'trade' => 'portail', 'icon' => '🚪', 'h1' => 'Portail bloqué',
            'lead'  => 'Votre portail motorisé ne s\'ouvre plus ? Avant de nous appeler, quelques vérifications qui règlent souvent le problème.',
            'checks'=> "Changez les piles de la télécommande et essayez une autre télécommande si vous en avez une.\nVérifiez le disjoncteur du portail dans le tableau électrique.\nNettoyez les cellules photoélectriques et vérifiez que rien ne les masque.\nPour sortir ou rentrer, utilisez le débrayage manuel décrit dans la notice (souvent une clé sur le moteur).",
            'never' => "Ne forcez pas un portail motorisé sans l'avoir débrayé.\nRestez à l'écart du portail pendant ses mouvements.",
            'alert' => '',
            'call'  => 'Appelez-nous si le moteur ne réagit plus, si le portail s\'arrête en cours de course ou fait un bruit anormal.',
        ],
        'volet-roulant-bloque' => [
            'trade' => 'automatisme', 'icon' => '🪟', 'h1' => 'Volet roulant bloqué',
            'lead'  => 'Volet électrique qui ne monte plus, qui descend de travers ou qui s\'arrête à mi-course : les causes les plus fréquentes.',
            'checks'=> "Vérifiez le disjoncteur des volets et les piles de la télécommande.\nAprès beaucoup de manœuvres, le moteur se protège en chauffant : attendez 20 à 30 minutes et réessayez.\nEn hiver, le volet peut être pris par le gel : attendez le dégel sans forcer.",
            'never' => "Ne forcez pas sur le tablier du volet.\nN'ouvrez pas le coffre du volet : le ressort et le moteur peuvent être dangereux.",
            'alert' => '',
            'call'  => 'Appelez-nous si le volet reste bloqué, si une lame est cassée ou sortie de son rail, ou si le moteur ne répond plus.',
        ],
    ];
}

/** Une page panne avec ses textes actuels (modifiés dans l'admin ou d'origine). */
function panne_page(string $slug): ?array
{
    $d = panne_defaults()[$slug] ?? null;
    if ($d === null) return null;
    $k = 'panne_'.str_replace('-', '_', $slug).'_';
    return [
        'slug'   => $slug,
        'trade'  => $d['trade'],
        'icon'   => $d['icon'],
        'h1'     => cp_text($k.'h1', $d['h1']),
        'lead'   => cp_text($k.'lead', $d['lead']),
        'checks' => cp_lines($k.'checks', $d['checks']),
        'never'  => cp_lines($k.'never', $d['never']),
        'alert'  => cp_text($k.'alert', $d['alert']),
        'call'   => cp_text($k.'call', $d['call']),
        'meta_title' => cp_text($k.'meta_title', $d['h1'].' : que faire ? Dépannage {en_region} | '.company_name()),
        'meta_desc'  => cp_text($k.'meta_desc', $d['lead']),
    ];
}

/** Pannes rattachées à un métier, pour les liens en bas de sa page. */
function pannes_for_trade(string $trade): array
{
    if (!pannes_enabled()) return [];
    return array_values(array_filter(array_map('panne_page', array_keys(panne_defaults())), static fn(?array $p): bool => $p !== null && $p['trade'] === $trade));
}

/** Champs du catalogue pour une page panne. */
function panne_catalog_fields(string $slug, array $d): array
{
    $k = 'panne_'.str_replace('-', '_', $slug).'_';
    $list = 'Une ligne par point.';
    return [
        ['key'=>$k.'h1','label'=>'Titre','type'=>'text','default'=>$d['h1']],
        ['key'=>$k.'lead','label'=>'Phrase d\'accroche','type'=>'textarea','default'=>$d['lead']],
        ['key'=>$k.'checks','label'=>'Ce que le client peut vérifier','type'=>'textarea','default'=>$d['checks'],'help'=>$list],
        ['key'=>$k.'never','label'=>'À ne pas faire','type'=>'textarea','default'=>$d['never'],'help'=>$list],
        ['key'=>$k.'alert','label'=>'Encadré danger (facultatif)','type'=>'textarea','default'=>$d['alert'],'help'=>'Laissé vide, l\'encadré rouge n\'apparaît pas.'],
        ['key'=>$k.'call','label'=>'Quand nous appeler','type'=>'textarea','default'=>$d['call']],
        ['key'=>$k.'meta_title','label'=>'Titre Google','type'=>'text','default'=>$d['h1'].' : que faire ? Dépannage {en_region} | '.company_name()],
        ['key'=>$k.'meta_desc','label'=>'Description Google','type'=>'textarea','default'=>$d['lead']],
    ];
}

/* ═══════════════════════════════════════════════════
   19. AIDES ET PRIMES
   Les aides de l'État exigent presque toutes une entreprise certifiée RGE : la
   page s'adapte à la case « Nous sommes certifiés RGE ». Aucun montant n'est
   écrit en dur : ils changent souvent, la page renvoie au site officiel.
═══════════════════════════════════════════════════ */
function aides_enabled(): bool { return global_setting('aides_enabled', '0') === '1'; }
function aides_rge(): bool { return global_setting('aides_rge', '0') === '1'; }

function aides_defaults(): array
{
    return [
        'aides_h1'   => 'Aides et primes pour vos travaux',
        'aides_lead' => 'Pompe à chaleur, chaudière, chauffe-eau, isolation : plusieurs aides peuvent réduire le prix de vos travaux. Voici l\'essentiel pour vous y retrouver.',
        'aides_rge_yes' => 'Notre entreprise est certifiée RGE : les travaux concernés peuvent ouvrir droit à MaPrimeRénov\' et aux primes énergie.',
        'aides_rge_no'  => 'MaPrimeRénov\' et les primes énergie ne sont versées que si les travaux sont réalisés par une entreprise certifiée RGE. Demandez-nous lesquels de nos travaux y donnent droit.',
        'aides_cards' => "MaPrimeRénov'|Aide de l'État pour les travaux de rénovation énergétique de votre résidence principale : pompe à chaleur, chauffe-eau thermodynamique ou solaire, chaudière biomasse, isolation… Le montant dépend de vos revenus et des travaux.\n"
                       . "Primes énergie (CEE)|Versées par les fournisseurs d'énergie pour des travaux qui font baisser la consommation. Elles se cumulent souvent avec MaPrimeRénov'.\n"
                       . "TVA réduite|Dans un logement achevé depuis plus de 2 ans : 5,5 % pour les travaux d'amélioration énergétique, 10 % pour la plupart des autres travaux d'entretien et de rénovation. Une attestation simple est à remplir.\n"
                       . "Éco-prêt à taux zéro|Un prêt sans intérêts, accordé par les banques partenaires, pour financer des travaux de rénovation énergétique.",
        'aides_steps' => "Vous nous décrivez votre projet : nous vous disons s'il peut être aidé.\nNous vous remettons un devis détaillé.\nVous faites votre demande d'aide AVANT de signer le devis et de démarrer les travaux : c'est obligatoire pour la plupart des aides.\nNous réalisons les travaux.\nL'aide vous est versée une fois les travaux terminés et la facture transmise.",
        'aides_note'  => 'Les montants et les conditions sont fixés par l\'État et changent régulièrement. Vérifiez votre situation sur le site officiel France Rénov\'.',
        'aides_meta_title' => 'Aides et primes travaux {en_region} : MaPrimeRénov\', CEE, TVA réduite | '.company_name(),
        'aides_meta_desc'  => 'MaPrimeRénov\', primes énergie, TVA réduite, éco-prêt à taux zéro : les aides pour vos travaux de chauffage et de rénovation, et comment les obtenir.',
    ];
}

/* ═══════════════════════════════════════════════════
   12. ESTIMATION DE PRIX
   S'appuie sur les grilles de la page Tarifs : sans prix saisis, la page
   annonce « sur devis » et pousse à l'appel. Désactivée par défaut.
═══════════════════════════════════════════════════ */
function estimation_enabled(): bool { return global_setting('estimation_enabled', '0') === '1'; }

function estimation_defaults(): array
{
    return [
        'estim_h1'   => 'Estimez le prix de votre intervention',
        'estim_lead' => 'Trois questions, et vous obtenez un prix indicatif. Le prix définitif vous est toujours confirmé avant que nous intervenions.',
        'estim_urgent_note' => 'Une intervention le soir, le week-end ou un jour férié peut être majorée : nous vous l\'indiquons toujours avant de nous déplacer.',
        'estim_result_note' => 'Prix indicatif, confirmé par un devis gratuit après diagnostic.',
        'estim_meta_title'  => 'Estimation de prix : dépannage et travaux {en_region} | '.company_name(),
        'estim_meta_desc'   => 'Obtenez en quelques clics un prix indicatif pour votre dépannage ou vos travaux d\'électricité, plomberie, chauffage ou climatisation.',
    ];
}

/** Métiers et prestations proposés à l'estimation : ceux de la page Tarifs. */
function estimation_trades(): array
{
    $out = [];
    $keys = array_keys(tarifs_default_rows());
    foreach (tarifs_groups() as $i => $g) {
        $rows = [];
        foreach ($g['rows'] as $row) {
            [$label, $price, $detail] = array_pad(array_values((array)$row), 3, '');
            if (trim((string)$label) !== '') $rows[] = ['label' => (string)$label, 'price' => trim((string)$price), 'detail' => trim((string)$detail)];
        }
        if ($rows) $out[$keys[$i]] = ['label' => $g['label'], 'icon' => $g['icon'], 'rows' => $rows];
    }
    return $out;
}

/* ═══════════════════════════════════════════════════
   11 et 15. RENDEZ-VOUS ET CONTRATS D'ENTRETIEN : textes des pages
═══════════════════════════════════════════════════ */
function booking_page_defaults(): array
{
    return [
        'rdv_h1'   => 'Prendre rendez-vous en ligne',
        'rdv_lead' => 'Choisissez le jour et la demi-journée qui vous conviennent. Nous vous rappelons pour confirmer l\'heure exacte.',
        'rdv_reasons' => "Entretien de chaudière\nEntretien de pompe à chaleur ou de climatisation\nVisite pour un devis\nPetits travaux\nAutre",
        'rdv_urgent'  => 'Pour une panne urgente, n\'attendez pas un rendez-vous : appelez-nous directement.',
        'rdv_meta_title' => 'Prendre rendez-vous en ligne | '.company_name(),
        'rdv_meta_desc'  => 'Réservez en ligne un créneau pour un entretien, une visite de devis ou des petits travaux. Confirmation rapide par téléphone.',
    ];
}

function entretien_enabled(): bool { return global_setting('entretien_enabled', '0') === '1'; }

function entretien_defaults(): array
{
    return [
        'entretien_h1'   => 'Contrats d\'entretien',
        'entretien_lead' => 'Un appareil bien entretenu tombe moins en panne, consomme moins et dure plus longtemps. Avec un contrat, nous vous prévenons quand l\'entretien approche : vous n\'avez plus à y penser.',
        'entretien_perks' => "Nous vous prévenons chaque année avant la date d'entretien\nUne attestation d'entretien remise après chaque visite\nUn interlocuteur qui connaît votre installation",
        'entretien_legal' => 'L\'entretien est obligatoire chaque année pour les chaudières (gaz, fioul, bois) de 4 à 400 kW, et tous les 2 ans pour les pompes à chaleur de 4 à 70 kW.',
        'entretien_meta_title' => 'Contrat d\'entretien chaudière, pompe à chaleur, climatisation {en_region} | '.company_name(),
        'entretien_meta_desc'  => 'Entretien annuel de chaudière, entretien de pompe à chaleur et de climatisation : nous vous prévenons avant chaque échéance.',
    ];
}

/** Offres affichées : [nom, prix, détails séparés par « ; »]. Un prix vide = « Sur devis ». */
function entretien_offers(): array
{
    $default = [
        ['Chaudière gaz ou fioul', '', 'Entretien annuel obligatoire;Nettoyage et réglages;Attestation remise'],
        ['Pompe à chaleur', '', 'Entretien obligatoire tous les 2 ans;Contrôle du circuit et des performances;Attestation remise'],
        ['Climatisation', '', 'Nettoyage des filtres et des unités;Contrôle du fonctionnement;Conseils d\'utilisation'],
        ['VMC', '', 'Nettoyage des bouches;Contrôle des débits;Vérification du moteur'],
    ];
    $rows = json_decode(setting_plain('entretien_offres'), true);
    return (is_array($rows) && $rows !== []) ? $rows : $default;
}

/* ═══════════════════════════════════════════════════
   Catalogue : les sections ajoutées à « Textes des pages »
═══════════════════════════════════════════════════ */
function content_pages_catalog(): array
{
    $fields = static function (array $defaults, array $labels): array {
        $out = [];
        foreach ($labels as $key => [$label, $type]) $out[] = ['key' => $key, 'label' => $label, 'type' => $type, 'default' => $defaults[$key]];
        return $out;
    };
    $seo = [['Titre Google', 'text'], ['Description Google', 'textarea']];

    $pannes = [[
        'id' => 'statut', 'label' => 'Publication', 'seen' => 'Active ou retire toutes les pages panne',
        'fields' => [['key'=>'pannes_enabled','label'=>'Pages panne','type'=>'toggle','default'=>'1','check'=>'Afficher les pages « pannes fréquentes »','help'=>'Huit pages de conseils (chaudière en panne, fuite d\'eau…), reliées aux pages métier. Relisez-les avant tout.']],
    ]];
    foreach (panne_defaults() as $slug => $d) {
        $pannes[] = ['id' => 'p_'.str_replace('-', '_', $slug), 'label' => $d['icon'].' '.$d['h1'], 'seen' => 'Page '.route_url($slug), 'fields' => panne_catalog_fields($slug, $d)];
    }

    $a = aides_defaults();
    $e = estimation_defaults();
    $r = booking_page_defaults();
    $t = entretien_defaults();

    return [
        'page_pannes' => ['label' => 'Pages panne', 'icon' => '🛠️', 'route' => 'pannes',
            'intro' => 'Des conseils simples à suivre en attendant le technicien, puis un formulaire de rappel. Ces pages répondent aux recherches Google faites en pleine panne.',
            'sections' => $pannes],
        'page_aides' => ['label' => 'Aides et primes', 'icon' => '💶', 'route' => 'aides',
            'intro' => 'Page désactivée tant que vous ne l\'activez pas : indiquez d\'abord si votre entreprise est certifiée RGE.',
            'sections' => [
                ['id' => 'statut', 'label' => 'Publication', 'seen' => 'Active la page et indique votre certification', 'fields' => [
                    ['key'=>'aides_enabled','label'=>'Page Aides','type'=>'toggle','default'=>'0','check'=>'Afficher la page Aides et primes'],
                    ['key'=>'aides_rge','label'=>'Certification RGE','type'=>'toggle','default'=>'0','check'=>'Notre entreprise est certifiée RGE','help'=>'Change le message affiché en haut de la page.'],
                ]],
                ['id' => 'textes', 'label' => 'Textes de la page', 'seen' => 'Le contenu de la page', 'fields' => $fields($a, [
                    'aides_h1' => ['Titre', 'text'], 'aides_lead' => ['Phrase d\'accroche', 'textarea'],
                    'aides_rge_yes' => ['Message si certifié RGE', 'textarea'], 'aides_rge_no' => ['Message si non certifié', 'textarea'],
                    'aides_cards' => ['Les aides (une par ligne : nom|explication)', 'textarea'],
                    'aides_steps' => ['Les étapes (une par ligne)', 'textarea'], 'aides_note' => ['Mention en bas de page', 'textarea'],
                    'aides_meta_title' => $seo[0], 'aides_meta_desc' => $seo[1],
                ])],
            ]],
        'page_estimation' => ['label' => 'Estimation de prix', 'icon' => '🧮', 'route' => 'estimation',
            'intro' => 'L\'estimation reprend les prix de vos grilles de la page Tarifs. Remplissez-les avant d\'activer la page.',
            'sections' => [
                ['id' => 'statut', 'label' => 'Publication', 'seen' => 'Active la page', 'fields' => [
                    ['key'=>'estimation_enabled','label'=>'Page Estimation','type'=>'toggle','default'=>'0','check'=>'Afficher la page Estimation de prix'],
                ]],
                ['id' => 'textes', 'label' => 'Textes de la page', 'seen' => 'Le contenu de la page', 'link' => ['admin/page_content.php?p=page_tarifs', 'Remplir les prix (page Tarifs)'], 'fields' => $fields($e, [
                    'estim_h1' => ['Titre', 'text'], 'estim_lead' => ['Phrase d\'accroche', 'textarea'],
                    'estim_urgent_note' => ['Mention sur les urgences', 'textarea'], 'estim_result_note' => ['Mention sous le prix', 'textarea'],
                    'estim_meta_title' => $seo[0], 'estim_meta_desc' => $seo[1],
                ])],
            ]],
        'page_rdv' => ['label' => 'Rendez-vous en ligne', 'icon' => '📅', 'route' => 'rendez-vous',
            'intro' => 'Les jours, les créneaux et le nombre de rendez-vous par créneau se règlent dans « Conversion & avis ».',
            'sections' => [
                ['id' => 'textes', 'label' => 'Textes de la page', 'seen' => 'Le contenu de la page', 'link' => ['admin/growth.php#rdv', 'Régler les créneaux et activer la page'], 'fields' => $fields($r, [
                    'rdv_h1' => ['Titre', 'text'], 'rdv_lead' => ['Phrase d\'accroche', 'textarea'],
                    'rdv_reasons' => ['Motifs proposés (un par ligne)', 'textarea'], 'rdv_urgent' => ['Mention pour les urgences', 'textarea'],
                    'rdv_meta_title' => $seo[0], 'rdv_meta_desc' => $seo[1],
                ])],
            ]],
        'page_entretien' => ['label' => 'Contrats d\'entretien', 'icon' => '🔧', 'route' => 'entretien',
            'intro' => 'Indiquez vos prix avant d\'activer la page. Les contrats signés se suivent dans « Contrats d\'entretien ».',
            'sections' => [
                ['id' => 'statut', 'label' => 'Publication', 'seen' => 'Active la page', 'fields' => [
                    ['key'=>'entretien_enabled','label'=>'Page Entretien','type'=>'toggle','default'=>'0','check'=>'Afficher la page Contrats d\'entretien'],
                ]],
                ['id' => 'offres', 'label' => 'Les offres', 'seen' => 'Les cartes d\'offres', 'note' => 'Un prix laissé vide s\'affiche « Sur devis ». Séparez les détails par un point-virgule.', 'fields' => [
                    ['key'=>'entretien_offres','label'=>'Offres','type'=>'list','default'=>entretien_offers(),'columns'=>[
                        ['k'=>'label','label'=>'Offre'], ['k'=>'price','label'=>'Prix (ex : 120 € TTC / an)','w'=>'220px'], ['k'=>'detail','label'=>'Détails (séparés par ;)'],
                    ]],
                ]],
                ['id' => 'textes', 'label' => 'Textes de la page', 'seen' => 'Le reste de la page', 'fields' => $fields($t, [
                    'entretien_h1' => ['Titre', 'text'], 'entretien_lead' => ['Phrase d\'accroche', 'textarea'],
                    'entretien_perks' => ['Avantages (un par ligne)', 'textarea'], 'entretien_legal' => ['Rappel des obligations', 'textarea'],
                    'entretien_meta_title' => $seo[0], 'entretien_meta_desc' => $seo[1],
                ])],
            ]],
    ];
}
