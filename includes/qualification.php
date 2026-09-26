<?php
declare(strict_types=1);
/**
 * Qualification d'un appel, assistée par Claude.
 *
 * Le dispatcher a le client au téléphone. À chaque tour, Claude propose UNE question
 * courte (avec des réponses rapides), note ce qu'il a appris et signale tout danger.
 * Quand l'essentiel est connu, un second appel produit un résumé structuré ;
 * l'estimation est ensuite recalculée en PHP à partir de la grille tarifaire.
 *
 * Sans Claude (pas de clé, panne, refus), un questionnaire statique par métier prend
 * le relais avec les mêmes champs : le dispatcher n'est jamais bloqué.
 */

/** Champs recueillis pendant l'appel : clé => [libellé, obligatoire pour créer la fiche]. */
function qual_fields(): array
{
    return [
        'category'           => ['Métier', true],
        'fault_type'         => ['Type de panne', true],
        'diagnostic'         => ['Éléments de diagnostic', false],
        'description'        => ['Description', true],
        'urgency'            => ['Urgence', false],
        'lastname'           => ['Nom', true],
        'firstname'          => ['Prénom', false],
        'phone'              => ['Téléphone', true],
        'email'              => ['E-mail', false],
        'address'            => ['Adresse', true],
        'postal_code'        => ['Code postal', true],
        'city'               => ['Ville', true],
        'floor'              => ['Étage', false],
        'digicode'           => ['Digicode', false],
        'interphone'         => ['Interphone', false],
        'access_info'        => ['Accès', false],
        'client_type'        => ['Particulier ou professionnel', true],
        'occupant'           => ['Locataire ou propriétaire', false],
        'housing_over_2y'    => ['Logement de plus de 2 ans', false],
        'availability'       => ['Disponibilités', true],
    ];
}

function qual_danger_instructions(): array
{
    return [
        'Faites couper le compteur (disjoncteur général) si c\'est possible sans risque.',
        'Odeur de gaz : ne rien allumer ni éteindre, ne pas utiliser d\'appareil électrique, fermer le robinet de gaz, aérer.',
        'Faites sortir tout le monde du logement.',
        'Appelez les pompiers au 18 ou le 112.',
        'Urgence gaz GRDF : 0 800 47 33 33 (appel gratuit, 24 h/24).',
    ];
}

/* ─── Session ─────────────────────────────────────────────── */
function qual_table(): void
{
    static $done = false;
    if ($done) return;
    $done = true;
    try {
        db_execute("CREATE TABLE IF NOT EXISTS qualification_sessions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            dispatcher_id INT NULL,
            quote_id INT NULL,
            intervention_id INT NULL,
            client_id INT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'en_cours',
            mode VARCHAR(20) NOT NULL DEFAULT 'claude',
            state MEDIUMTEXT NULL,
            summary MEDIUMTEXT NULL,
            danger TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NULL,
            INDEX idx_iv (intervention_id),
            INDEX idx_quote (quote_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (Throwable $e) { integration_log('qualification', 'table : '.$e->getMessage()); }
}

function qual_load(int $id): ?array
{
    qual_table();
    try { $row = db_fetch('SELECT * FROM qualification_sessions WHERE id = ?', [$id]); } catch (Throwable $e) { return null; }
    if (!$row) return null;
    $row['state']   = json_decode((string)($row['state'] ?? ''), true) ?: ['answers' => [], 'transcript' => [], 'danger' => null];
    $row['summary'] = json_decode((string)($row['summary'] ?? ''), true) ?: null;
    return $row;
}

function qual_save(array $s): void
{
    db_execute('UPDATE qualification_sessions SET status = ?, mode = ?, state = ?, summary = ?, danger = ?, client_id = ?, intervention_id = ?, updated_at = NOW() WHERE id = ?', [
        $s['status'], $s['mode'],
        json_encode($s['state'], JSON_UNESCAPED_UNICODE),
        $s['summary'] !== null ? json_encode($s['summary'], JSON_UNESCAPED_UNICODE) : null,
        !empty($s['state']['danger']['detected']) ? 1 : 0,
        $s['client_id'] ?? null, $s['intervention_id'] ?? null, (int)$s['id'],
    ]);
}

/** Crée une session, pré-remplie depuis une demande du site si $quoteId est fourni. */
function qual_start(int $dispatcherId, ?int $quoteId = null): int
{
    qual_table();
    $answers = [];
    $context = 'Nouvel appel entrant. Le client est au téléphone avec le dispatcher.';
    if ($quoteId) {
        $q = db_fetch('SELECT * FROM quotes WHERE id = ?', [$quoteId]);
        if ($q) {
            $name = trim((string)$q['full_name']);
            $parts = preg_split('/\s+/', $name, 2) ?: [];
            $answers = array_filter([
                'lastname' => $parts[1] ?? $parts[0] ?? '', 'firstname' => isset($parts[1]) ? $parts[0] : '',
                'phone' => (string)$q['phone'], 'email' => (string)$q['email'], 'address' => (string)($q['address'] ?? ''),
                'postal_code' => (string)($q['postal_code'] ?? ''), 'city' => (string)$q['city'],
                'description' => trim((string)($q['message'] ?? '')),
            ], static fn($v) => trim((string)$v) !== '');
            $context = 'Demande reçue par le site le '.date('d/m/Y à H:i', strtotime((string)$q['created_at'])).' : service « '
                .trim((string)$q['service_type']).' »'.($q['urgency'] ? ', urgence : '.$q['urgency'] : '')
                .'. Message du client : « '.mb_substr(trim((string)$q['message']), 0, 800).' ». Le dispatcher rappelle le client.';
        }
    }
    $state = ['answers' => $answers, 'transcript' => [], 'danger' => null, 'context' => $context];
    qual_detect_danger($state, (string)($answers['description'] ?? ''));
    db_execute('INSERT INTO qualification_sessions (dispatcher_id, quote_id, state, mode, updated_at) VALUES (?,?,?,?,NOW())', [
        $dispatcherId, $quoteId,
        json_encode($state, JSON_UNESCAPED_UNICODE),
        claude_is_configured() ? 'claude' : 'questionnaire',
    ]);
    $id = db_last_id();
    $s = qual_load($id);
    qual_next_question($s);   // première question
    return $id;
}

/* ─── Tour de conversation ───────────────────────────────── */
function qual_field_keys(): array { return array_keys(qual_fields()); }

function qual_turn_schema(): array
{
    return [
        'type' => 'object',
        'additionalProperties' => false,
        'required' => ['message', 'quick_replies', 'updates', 'danger', 'done'],
        'properties' => [
            'message'       => ['type' => 'string'],
            'quick_replies' => ['type' => 'array', 'items' => ['type' => 'string']],
            'updates'       => ['type' => 'array', 'items' => [
                'type' => 'object', 'additionalProperties' => false, 'required' => ['key', 'value'],
                'properties' => ['key' => ['type' => 'string', 'enum' => qual_field_keys()], 'value' => ['type' => 'string']],
            ]],
            'danger' => ['type' => 'object', 'additionalProperties' => false, 'required' => ['detected', 'kind', 'instructions'],
                'properties' => ['detected' => ['type' => 'boolean'], 'kind' => ['type' => 'string'], 'instructions' => ['type' => 'array', 'items' => ['type' => 'string']]]],
            'done' => ['type' => 'boolean'],
        ],
    ];
}

function qual_system_prompt(): string
{
    $cats = implode(', ', array_map(static fn($k, $c) => $k.' ('.$c['label'].')', array_keys(intervention_category_config()), intervention_category_config()));
    $fields = implode("\n", array_map(static fn($k, $f) => '- '.$k.' : '.$f[0].($f[1] ? ' (obligatoire)' : ''), array_keys(qual_fields()), qual_fields()));
    return <<<TXT
Tu assistes le dispatcher d'une entreprise de dépannage en Île-de-France (électricité, plomberie, débouchage, chauffage, climatisation). Le dispatcher a le client au téléphone et te transmet ses réponses.

À chaque tour :
- Pose UNE seule question courte, formulée pour que le dispatcher la lise au client (vouvoiement). Propose 2 à 5 réponses rapides quand c'est possible, sinon une liste vide.
- Dans « updates », renvoie uniquement les champs appris ou corrigés grâce à la dernière réponse (clé + valeur en texte). N'invente jamais une information.
- Adapte les questions au métier. Exemples en électricité : tout le logement ou une partie ? le disjoncteur général saute-t-il au réarmement ? les voisins sont-ils aussi coupés (si oui, c'est probablement une panne du réseau : conseille au dispatcher d'orienter le client vers le dépannage Enedis, dont le numéro figure sur la facture d'électricité et sur enedis.fr) ? odeur de brûlé, fumée, bruit ? âge du tableau ? appareil branché au moment de la coupure ? Ajoute ces éléments dans « diagnostic ».
- SÉCURITÉ D'ABORD : en cas d'odeur de gaz, de fumée, d'étincelles, de fils dénudés, d'eau sur une installation électrique ou de suspicion de monoxyde de carbone, mets danger.detected à true, précise le type et liste les consignes à lire immédiatement au client (couper le compteur, aérer, sortir, appeler le 18 ou le 112, urgence gaz GRDF 0 800 47 33 33).
- Tu dois obtenir : le métier, le type de panne, une description claire, puis nom, prénom, téléphone, e-mail, adresse complète, étage, digicode, interphone, accès, particulier ou professionnel, locataire ou propriétaire, logement de plus de 2 ans, disponibilités. Regroupe les coordonnées sans poser plus d'une question par tour.
- Ne donne jamais de prix : l'estimation sera calculée à partir de la grille tarifaire.
- Quand tous les champs obligatoires sont connus, mets done à true et, dans « message », indique au dispatcher qu'il peut générer le résumé.

Valeurs attendues : category parmi {$cats} ; client_type « particulier » ou « professionnel » ; occupant « locataire » ou « propriétaire » ; housing_over_2y et urgency « oui » ou « non ».

Champs :
{$fields}
TXT;
}

/** Messages au format de l'API, rejoués depuis la transcription. */
function qual_api_messages(array $state): array
{
    $msgs = [['role' => 'user', 'content' => $state['context'] ?? 'Nouvel appel entrant.'
        .(($state['answers'] ?? []) ? "\nInformations déjà connues : ".json_encode($state['answers'], JSON_UNESCAPED_UNICODE) : '')]];
    foreach ($state['transcript'] ?? [] as $t) {
        if ($t['role'] === 'assistant') {
            $msgs[] = ['role' => 'assistant', 'content' => (string)($t['raw'] ?? json_encode(['message' => $t['text']], JSON_UNESCAPED_UNICODE))];
        } else {
            $msgs[] = ['role' => 'user', 'content' => 'Réponse du client : '.$t['text']];
        }
    }
    return $msgs;
}

/** Applique une réponse du dispatcher puis prépare la question suivante. */
function qual_answer(array &$s, string $answer): void
{
    $answer = mb_substr(trim($answer), 0, 1000);
    if ($answer === '') return;
    $s['state']['transcript'][] = ['role' => 'user', 'text' => $answer, 'at' => date('c')];
    // Le questionnaire range la réponse dans le champ qu'il venait de demander.
    $last = qual_last_assistant($s['state']);
    if ($last && !empty($last['field'])) {
        // Une réponse de diagnostic n'a de sens qu'avec sa question (« Voisins coupés ? Non »).
        $value = $last['field'] === 'diagnostic'
            ? trim((string)preg_replace('/\s*\(.*\)\s*/u', ' ', (string)$last['text'])).' '.$answer
            : $answer;
        qual_set_answer($s['state'], (string)$last['field'], $value);
    }
    qual_detect_danger($s['state'], $answer);
    qual_next_question($s);
}

function qual_last_assistant(array $state): ?array
{
    for ($i = count($state['transcript'] ?? []) - 1; $i >= 0; $i--) {
        if ($state['transcript'][$i]['role'] === 'assistant') return $state['transcript'][$i];
    }
    return null;
}

function qual_set_answer(array &$state, string $key, string $value): void
{
    if (!array_key_exists($key, qual_fields())) return;
    $value = trim($value);
    if ($key === 'diagnostic' && !empty($state['answers']['diagnostic'])) {
        if (str_contains((string)$state['answers']['diagnostic'], $value)) return;
        $value = $state['answers']['diagnostic'].' ; '.$value;
    }
    if ($key === 'category') $value = qual_normalize_category($value);
    $state['answers'][$key] = $value;
}

function qual_normalize_category(string $v): string
{
    $v = mb_strtolower(trim($v));
    foreach (intervention_category_config() as $k => $c) {
        if ($v === $k || $v === mb_strtolower($c['label']) || str_contains($v, mb_strtolower($c['label']))) return $k;
    }
    return match (true) {
        str_contains($v, 'élec') || str_contains($v, 'elec') => 'electricite',
        str_contains($v, 'plomb') || str_contains($v, 'fuite') || str_contains($v, 'eau') => 'plomberie',
        str_contains($v, 'bouch') || str_contains($v, 'wc') => 'debouchage',
        str_contains($v, 'chaud') || str_contains($v, 'chauff') => 'chauffage',
        str_contains($v, 'clim') => 'climatisation',
        default => $v,
    };
}

/** Repérage des dangers par mots-clés (toujours actif, même avec Claude). */
function qual_detect_danger(array &$state, string $text): void
{
    $t = mb_strtolower($text);
    $kinds = [
        'odeur de gaz'          => '/odeur de gaz|sent le gaz|fuite de gaz/u',
        'fumée ou brûlé'        => '/fum[ée]e|odeur de br[ûu]l|ça br[ûu]le|flamme|feu\b/u',
        'étincelles'            => '/[ée]tincel|arc [ée]lectrique|claquement/u',
        'fils dénudés'          => '/fils? d[ée]nud|c[âa]bles? d[ée]nud/u',
        'eau et électricité'    => '/eau .*(prise|tableau|[ée]lectri)|(prise|tableau|[ée]lectri).* eau|inond.*(tableau|prise)/u',
        'monoxyde de carbone'   => '/monoxyde|\bco\b|d[ée]tecteur de co|maux de t[êe]te.*chaudi/u',
    ];
    foreach ($kinds as $kind => $re) {
        if (preg_match($re, $t)) {
            $state['danger'] = ['detected' => true, 'kind' => $kind, 'instructions' => qual_danger_instructions()];
            return;
        }
    }
}

/** Champs obligatoires encore inconnus. */
function qual_missing(array $state): array
{
    $miss = [];
    foreach (qual_fields() as $k => [$label, $req]) {
        if ($req && trim((string)($state['answers'][$k] ?? '')) === '') $miss[$k] = $label;
    }
    return $miss;
}

/** Calcule la question suivante (Claude, sinon questionnaire). */
function qual_next_question(array &$s): void
{
    $state = &$s['state'];
    $turn = null;
    if ($s['mode'] === 'claude') {
        $r = claude_structured([
            'purpose'    => 'qualification',
            'system'     => qual_system_prompt(),
            'messages'   => qual_api_messages($state),
            'schema'     => qual_turn_schema(),
            'effort'     => 'low',
            'max_tokens' => 2000,
            'timeout'    => 45,
        ]);
        if ($r['ok'] && !$r['simulated']) {
            $turn = $r['data'];
            foreach ($turn['updates'] as $u) qual_set_answer($state, (string)$u['key'], (string)$u['value']);
            if (!empty($turn['danger']['detected'])) {
                $state['danger'] = ['detected' => true, 'kind' => (string)$turn['danger']['kind'],
                    'instructions' => array_values(array_unique(array_merge(array_map('strval', $turn['danger']['instructions']), qual_danger_instructions())))];
            }
            $state['transcript'][] = ['role' => 'assistant', 'text' => (string)$turn['message'], 'quick_replies' => array_slice(array_map('strval', $turn['quick_replies']), 0, 6),
                                      'raw' => json_encode($turn, JSON_UNESCAPED_UNICODE), 'done' => (bool)$turn['done'], 'at' => date('c')];
        } else {
            // Claude indisponible : on bascule sur le questionnaire sans perdre ce qui est déjà recueilli.
            $s['mode'] = 'questionnaire';
            $state['notice'] = ($r['error'] ?? 'Claude indisponible').' Le questionnaire standard prend le relais.';
        }
    }
    if ($turn === null) {
        $q = qual_questionnaire_next($state);
        $state['transcript'][] = $q + ['role' => 'assistant', 'at' => date('c')];
    }
    qual_save($s);
}

/* ─── Questionnaire statique (repli sans Claude) ─────────── */
function qual_trade_questions(): array
{
    return [
        'electricite' => [
            ['diagnostic', 'La coupure concerne-t-elle tout le logement ou seulement une partie ?', ['Tout le logement', 'Une partie seulement', 'Un seul appareil']],
            ['diagnostic', 'Le disjoncteur général saute-t-il quand on le réarme ?', ['Oui, il saute aussitôt', 'Non, il tient', 'Impossible à réarmer', 'Ne sait pas']],
            ['diagnostic', 'Les voisins sont-ils aussi coupés ? (si oui : panne réseau, orienter vers le dépannage Enedis, numéro sur la facture d\'électricité ou enedis.fr)', ['Non', 'Oui, les voisins aussi', 'Ne sait pas']],
            ['diagnostic', 'Y a-t-il eu une odeur de brûlé, de la fumée ou un bruit ?', ['Non', 'Odeur de brûlé', 'Fumée', 'Claquement / étincelle']],
            ['diagnostic', 'Quel âge a le tableau électrique ?', ['Moins de 10 ans', 'Plus de 10 ans', 'Très ancien (fusibles)', 'Ne sait pas']],
            ['diagnostic', 'Un appareil était-il branché ou en marche au moment de la coupure ?', ['Non', 'Oui (lequel ?)', 'Ne sait pas']],
        ],
        'plomberie' => [
            ['fault_type', 'De quel problème s\'agit-il ?', ['Fuite d\'eau', 'WC qui coule', 'Chauffe-eau', 'Robinetterie', 'Autre']],
            ['diagnostic', 'La fuite est-elle active en ce moment ? L\'eau a-t-elle pu être coupée ?', ['Fuite active, eau non coupée', 'Eau coupée', 'Goutte à goutte', 'Pas de fuite']],
            ['diagnostic', 'Où se situe le problème (cuisine, salle de bain, WC, sous un meuble, au plafond…) ?', []],
        ],
        'debouchage' => [
            ['fault_type', 'Qu\'est-ce qui est bouché ?', ['WC', 'Évier', 'Lavabo', 'Douche / baignoire', 'Canalisation générale']],
            ['diagnostic', 'L\'écoulement est-il complètement bloqué ou seulement lent ?', ['Complètement bloqué', 'Écoulement lent', 'Refoulement / débordement']],
            ['diagnostic', 'Le client a-t-il déjà essayé quelque chose (ventouse, produit) ?', ['Non', 'Ventouse', 'Produit déboucheur', 'Furet']],
        ],
        'chauffage' => [
            ['fault_type', 'Quel est le problème ?', ['Plus de chauffage', 'Plus d\'eau chaude', 'Chaudière en panne / en défaut', 'Entretien annuel', 'Fuite']],
            ['diagnostic', 'Quel type d\'appareil ?', ['Chaudière gaz', 'Chaudière fioul', 'Pompe à chaleur', 'Radiateurs électriques', 'Ne sait pas']],
            ['diagnostic', 'Un code d\'erreur s\'affiche-t-il ? Si oui, lequel ?', ['Non', 'Oui (préciser)']],
        ],
        'climatisation' => [
            ['fault_type', 'Quel est le problème ?', ['Ne refroidit plus', 'Ne démarre plus', 'Fuite d\'eau', 'Bruit anormal', 'Entretien']],
            ['diagnostic', 'Quel type d\'installation ?', ['Monosplit', 'Multisplit', 'Gainable', 'Climatiseur mobile', 'Ne sait pas']],
        ],
    ];
}

function qual_questionnaire_next(array $state): array
{
    $a = $state['answers'] ?? [];
    $asked = array_map(static fn($t) => (string)($t['text'] ?? ''), array_filter($state['transcript'] ?? [], static fn($t) => $t['role'] === 'assistant'));
    $ask = static fn(string $field, string $text, array $qr = []) => ['text' => $text, 'field' => $field, 'quick_replies' => $qr, 'done' => false];

    if (empty($a['category'])) {
        return $ask('category', 'Quel est le type de problème ?', array_map(static fn($c) => $c['label'], array_intersect_key(intervention_category_config(), array_flip(['electricite', 'plomberie', 'debouchage', 'chauffage', 'climatisation']))));
    }
    $safetyQ = 'Y a-t-il un danger immédiat : odeur de gaz, fumée, étincelles, fils dénudés, eau sur une installation électrique ?';
    if (!in_array($safetyQ, $asked, true)) {
        return $ask('diagnostic', $safetyQ, ['Non, aucun danger', 'Odeur de gaz', 'Fumée / odeur de brûlé', 'Étincelles / fils dénudés', 'Eau sur l\'électricité']);
    }
    foreach (qual_trade_questions()[$a['category']] ?? [] as [$field, $text, $qr]) {
        if (!in_array($text, $asked, true)) return $ask($field, $text, $qr);
    }
    $generic = [
        ['fault_type', 'En quelques mots, quel est le type de panne ?', []],
        ['description', 'Décrivez la situation telle que le client l\'explique.', []],
        ['urgency', 'Est-ce urgent (intervention dans les 2 heures) ?', ['Oui', 'Non']],
        ['lastname', 'Quel est le nom du client ?', []],
        ['firstname', 'Et son prénom ?', []],
        ['phone', 'Quel numéro de téléphone pour le joindre ?', []],
        ['email', 'Son adresse e-mail (pour la confirmation et la facture) ?', ['Pas d\'e-mail']],
        ['address', 'Quelle est l\'adresse de l\'intervention (numéro et rue) ?', []],
        ['postal_code', 'Code postal ?', []],
        ['city', 'Ville ?', []],
        ['floor', 'Étage et bâtiment ?', ['Rez-de-chaussée', 'Maison individuelle']],
        ['digicode', 'Digicode ?', ['Pas de digicode']],
        ['interphone', 'Nom sur l\'interphone ?', ['Pas d\'interphone']],
        ['access_info', 'Autres informations d\'accès (gardien, parking, clés) ?', ['Rien de particulier']],
        ['client_type', 'Le client est-il un particulier ou un professionnel ?', ['Particulier', 'Professionnel']],
        ['occupant', 'Est-il locataire ou propriétaire ?', ['Locataire', 'Propriétaire']],
        ['housing_over_2y', 'Le logement a-t-il plus de 2 ans ?', ['Oui', 'Non', 'Ne sait pas']],
        ['availability', 'Quelles sont ses disponibilités ?', ['Dès que possible', 'Aujourd\'hui', 'Demain matin', 'Demain après-midi']],
    ];
    foreach ($generic as [$field, $text, $qr]) {
        if (trim((string)($a[$field] ?? '')) === '' && !in_array($text, $asked, true)) return $ask($field, $text, $qr);
    }
    return ['text' => 'Toutes les informations nécessaires sont recueillies. Vous pouvez générer le résumé.', 'field' => '', 'quick_replies' => [], 'done' => true];
}

/* ─── Résumé ──────────────────────────────────────────────── */
function qual_summary_schema(): array
{
    $str = ['type' => 'string'];
    $cats = array_keys(intervention_category_config());
    return [
        'type' => 'object', 'additionalProperties' => false, 'required' => ['client', 'intervention', 'estimate'],
        'properties' => [
            'client' => ['type' => 'object', 'additionalProperties' => false,
                'required' => ['lastname', 'firstname', 'phone', 'email', 'address', 'postal_code', 'city', 'floor', 'digicode', 'interphone', 'access_info', 'client_type', 'occupant', 'housing_over_2y', 'availability'],
                'properties' => [
                    'lastname' => $str, 'firstname' => $str, 'phone' => $str, 'email' => $str, 'address' => $str, 'postal_code' => $str,
                    'city' => $str, 'floor' => $str, 'digicode' => $str, 'interphone' => $str, 'access_info' => $str,
                    'client_type' => ['type' => 'string', 'enum' => ['particulier', 'professionnel', 'inconnu']],
                    'occupant' => ['type' => 'string', 'enum' => ['locataire', 'proprietaire', 'inconnu']],
                    'housing_over_2y' => ['type' => 'string', 'enum' => ['oui', 'non', 'inconnu']],
                    'availability' => $str,
                ]],
            'intervention' => ['type' => 'object', 'additionalProperties' => false,
                'required' => ['category', 'fault_type', 'urgency', 'priority', 'description', 'skills', 'duration_minutes', 'materials'],
                'properties' => [
                    'category' => ['type' => 'string', 'enum' => $cats],
                    'fault_type' => $str, 'urgency' => ['type' => 'boolean'],
                    'priority' => ['type' => 'string', 'enum' => ['normale', 'haute', 'urgente']],
                    'description' => $str,
                    'skills' => ['type' => 'array', 'items' => $str],
                    'duration_minutes' => ['type' => 'integer'],
                    'materials' => ['type' => 'array', 'items' => $str],
                ]],
            'estimate' => ['type' => 'object', 'additionalProperties' => false, 'required' => ['lines', 'note'],
                'properties' => [
                    'lines' => ['type' => 'array', 'items' => ['type' => 'object', 'additionalProperties' => false, 'required' => ['code', 'qty'],
                        'properties' => ['code' => $str, 'qty' => ['type' => 'number']]]],
                    'note' => $str,
                ]],
        ],
    ];
}

/** Produit le résumé (Claude, sinon construction directe à partir des réponses). */
function qual_summarize(array &$s): array
{
    $state = $s['state'];
    $cat = qual_normalize_category((string)($state['answers']['category'] ?? ''));
    $grid = array_map(static fn($r) => $r['code'].' — '.$r['label'].' — '.$r['unit'].' — '.number_format((float)$r['price_ht'], 2, ',', '').($r['is_percent'] ? ' %' : ' € HT'),
        price_grid_rows(true, isset(intervention_category_config()[$cat]) ? $cat : ''));
    $materials = array_map(static fn($p) => (string)$p['label'], get_presets('material', isset(intervention_category_config()[$cat]) ? $cat : ''));

    $r = ['ok' => false, 'simulated' => true];
    if ($s['mode'] === 'claude') {
        $r = claude_structured([
            'purpose'    => 'qualification-resume',
            'system'     => "Tu prépares la fiche d'intervention à partir d'un appel qualifié par le dispatcher. N'invente rien : laisse une chaîne vide ou « inconnu » si l'information manque.\n"
                ."- description : texte clair et factuel pour le technicien (symptômes, contexte, éléments de diagnostic, danger éventuel).\n"
                ."- materials : uniquement des libellés exacts de la liste de matériel fournie.\n"
                ."- estimate.lines : uniquement des codes exacts de la grille tarifaire fournie, avec des quantités ; ajoute la majoration adaptée si l'urgence ou l'horaire le justifie (MAJ-URG, MAJ-SOIR, MAJ-NUIT). Ne calcule aucun montant.\n"
                ."- estimate.note : une phrase d'explication de l'estimation pour le dispatcher.",
            'messages'   => [['role' => 'user', 'content' =>
                "Informations recueillies :\n".json_encode($state['answers'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
                ."\n\nDanger signalé : ".(!empty($state['danger']['detected']) ? (string)$state['danger']['kind'] : 'aucun')
                ."\n\nGrille tarifaire (code — désignation — unité — prix) :\n".implode("\n", $grid)
                ."\n\nMatériel disponible :\n".implode(' | ', $materials)]],
            'schema'     => qual_summary_schema(),
            'effort'     => 'medium',
            'max_tokens' => 4000,
            'timeout'    => 60,
        ]);
    }
    $summary = ($r['ok'] && !$r['simulated']) ? $r['data'] : qual_summary_from_answers($state, $cat);
    // Garde-fous : matériel et codes limités aux listes existantes.
    $summary['intervention']['materials'] = array_values(array_intersect(array_map('strval', $summary['intervention']['materials']), $materials));
    $map = price_grid_map();
    $summary['estimate']['lines'] = array_values(array_filter($summary['estimate']['lines'], static fn($l) => isset($map[strtoupper((string)$l['code'])]) && (float)$l['qty'] > 0));
    $summary['source'] = ($r['ok'] && !$r['simulated']) ? 'claude' : 'questionnaire';
    $s['summary'] = $summary;
    $s['status'] = 'resume';
    qual_save($s);
    return $summary;
}

function qual_summary_from_answers(array $state, string $cat): array
{
    $a = $state['answers'];
    $yn = static fn($v) => preg_match('/^(oui|yes)/i', trim((string)$v)) ? 'oui' : (preg_match('/^non/i', trim((string)$v)) ? 'non' : 'inconnu');
    $clean = static fn($v) => preg_match('/^pas d|^rien de particulier/i', trim((string)$v)) ? '' : trim((string)$v);
    $urgent = $yn($a['urgency'] ?? '') === 'oui';
    $lines = [['code' => 'DEPL', 'qty' => 1]];
    $defaults = ['electricite' => 'ELEC-PANNE', 'plomberie' => 'PLB-FUITE-RECH', 'debouchage' => 'DEB-MANUEL', 'chauffage' => 'CH-DEPANNAGE', 'climatisation' => 'CLIM-DEPANNAGE'];
    if (isset($defaults[$cat])) $lines[] = ['code' => $defaults[$cat], 'qty' => 1];
    if ($urgent) $lines[] = ['code' => 'MAJ-URG', 'qty' => 1];
    $type = mb_strtolower((string)($a['client_type'] ?? ''));
    return [
        'client' => [
            'lastname' => trim((string)($a['lastname'] ?? '')), 'firstname' => trim((string)($a['firstname'] ?? '')),
            'phone' => trim((string)($a['phone'] ?? '')), 'email' => filter_var(trim((string)($a['email'] ?? '')), FILTER_VALIDATE_EMAIL) ? trim((string)$a['email']) : '',
            'address' => trim((string)($a['address'] ?? '')), 'postal_code' => trim((string)($a['postal_code'] ?? '')), 'city' => trim((string)($a['city'] ?? '')),
            'floor' => $clean($a['floor'] ?? ''), 'digicode' => $clean($a['digicode'] ?? ''), 'interphone' => $clean($a['interphone'] ?? ''),
            'access_info' => $clean($a['access_info'] ?? ''),
            'client_type' => str_starts_with($type, 'pro') ? 'professionnel' : (str_starts_with($type, 'part') ? 'particulier' : 'inconnu'),
            'occupant' => str_starts_with(mb_strtolower((string)($a['occupant'] ?? '')), 'loc') ? 'locataire' : (str_starts_with(mb_strtolower((string)($a['occupant'] ?? '')), 'prop') ? 'proprietaire' : 'inconnu'),
            'housing_over_2y' => $yn($a['housing_over_2y'] ?? ''),
            'availability' => trim((string)($a['availability'] ?? '')),
        ],
        'intervention' => [
            'category' => isset(intervention_category_config()[$cat]) ? $cat : 'depannage',
            'fault_type' => trim((string)($a['fault_type'] ?? '')),
            'urgency' => $urgent,
            'priority' => !empty($state['danger']['detected']) ? 'urgente' : ($urgent ? 'haute' : 'normale'),
            'description' => trim(implode("\n", array_filter([
                trim((string)($a['description'] ?? '')),
                !empty($a['diagnostic']) ? 'Diagnostic téléphonique : '.$a['diagnostic'] : '',
                !empty($state['danger']['detected']) ? 'DANGER signalé : '.$state['danger']['kind'] : '',
            ]))),
            'skills' => array_filter([intervention_category_config()[$cat]['label'] ?? '']),
            'duration_minutes' => 60,
            'materials' => [],
        ],
        'estimate' => ['lines' => $lines, 'note' => 'Estimation standard (déplacement + prestation de base du métier), à ajuster sur place.'],
    ];
}

/** Estimation recalculée à partir de la grille : total et fourchette indicative (+1 h de main d'œuvre). */
function qual_estimate(array $lines, float $vatRate): array
{
    $calc = pricing_compute($lines, $vatRate);
    $mo = price_grid_map()['MO']['price_ht'] ?? 0;
    $max = round($calc['total_ttc'] + (float)$mo * (1 + $calc['vat_rate'] / 100), 2);
    return $calc + ['range_min' => $calc['total_ttc'], 'range_max' => $max];
}

/* ─── Client existant ─────────────────────────────────────── */
/** Recherche un client déjà connu par téléphone ou e-mail (clients locaux et importés de Pennylane). */
function qual_find_client(string $phone, string $email): ?array
{
    $digits = preg_replace('/\D/', '', $phone);
    if (strlen($digits) >= 9) {
        $tail = substr($digits, -9);
        $c = db_fetch("SELECT * FROM clients WHERE REPLACE(REPLACE(REPLACE(REPLACE(phone,' ',''),'.',''),'-',''),'+33','0') LIKE ? ORDER BY id LIMIT 1", ['%'.$tail]);
        if ($c) return $c;
    }
    $email = trim($email);
    if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $c = db_fetch('SELECT * FROM clients WHERE LOWER(email) = LOWER(?) ORDER BY id LIMIT 1', [$email]);
        if ($c) return $c;
    }
    return null;
}

/* ─── Demandes du site à qualifier ───────────────────────── */
/** Demandes récentes du site pas encore transformées en fiche. */
function qual_pending_quotes(int $limit = 8): array
{
    $limit = max(1, min(50, $limit));
    try {
        return db_fetch_all("SELECT q.* FROM quotes q
            WHERE COALESCE(q.archived, 0) = 0 AND q.status IN ('nouveau','contacté')
              AND q.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
              AND NOT EXISTS (SELECT 1 FROM interventions i WHERE i.quote_id = q.id)
            ORDER BY q.created_at DESC LIMIT ".$limit);
    } catch (Throwable $e) { return []; }
}

/** Qualification liée à une fiche (pour l'afficher sur l'intervention). */
function qual_for_intervention(int $ivId): ?array
{
    qual_table();
    try { $row = db_fetch('SELECT id FROM qualification_sessions WHERE intervention_id = ? ORDER BY id DESC LIMIT 1', [$ivId]); }
    catch (Throwable $e) { return null; }
    return $row ? qual_load((int)$row['id']) : null;
}
