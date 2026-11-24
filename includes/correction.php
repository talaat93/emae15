<?php
declare(strict_types=1);
/**
 * Rapport corrigé par Claude : orthographe, français, vocabulaire technique du métier.
 *
 * Lancé dès que le technicien rend son rapport (en arrière-plan, avec la relecture :
 * review_run_after_response()). Le texte d'origine du technicien n'est jamais modifié : la
 * version corrigée est rangée à part (tech_diagnostic_corrige, tech_report_corrige) et
 * utilisée par le rapport PDF et les écrans. Le dispatcher peut la retoucher ou revenir au
 * texte d'origine.
 *
 * Garde-fous : Claude ne doit rien ajouter (pièce, mesure, travaux). Une correction est refusée
 * — le texte d'origine reste utilisé — si elle fait apparaître ou disparaître un nombre, ou si
 * sa longueur change trop (correction_acceptable()). Sans clé Claude : aucune correction.
 */

function correction_schema(): array
{
    return [
        'type' => 'object', 'additionalProperties' => false,
        'required' => ['diagnostic', 'travaux'],
        'properties' => [
            'diagnostic' => ['type' => 'string'],
            'travaux'    => ['type' => 'string'],
        ],
    ];
}

/** Nombres présents dans un texte (« 3 bars », « 1,5 m », « 230 V »), normalisés. */
function correction_numbers(string $t): array
{
    preg_match_all('/\d+(?:[.,]\d+)?/u', $t, $m);
    $n = array_map(static fn($x) => str_replace(',', '.', $x), $m[0]);
    sort($n);
    return $n;
}

/**
 * La correction peut-elle remplacer le texte d'origine ? Fonction pure.
 * Refus : correction vide, nombres ajoutés ou retirés, longueur trop différente.
 */
function correction_acceptable(string $orig, string $corr): bool
{
    $o = trim($orig); $c = trim($corr);
    if ($o === '') return $c === '';
    if ($c === '') return false;
    if (correction_numbers($o) !== correction_numbers($c)) return false;
    $lo = mb_strlen($o); $lc = mb_strlen($c);
    $max = max($lo * 2.2, $lo + 120);   // un texte très court peut être reformulé en phrase complète
    return $lc <= $max && $lc >= $lo * 0.4;
}

/** Texte à afficher et à mettre dans le PDF : la version corrigée si elle existe, sinon l'original. */
function correction_text(array $iv, string $field): string
{
    $corr = trim((string)($iv[$field.'_corrige'] ?? ''));
    return $corr !== '' ? $corr : trim((string)($iv[$field] ?? ''));
}

function correction_has(array $iv): bool
{
    return trim((string)($iv['tech_diagnostic_corrige'] ?? '').(string)($iv['tech_report_corrige'] ?? '')) !== '';
}

/** Consignes de correction (identiques pour la clé API et pour le PC du bureau). */
function correction_system_prompt(string $metier): string
{
    return "Tu relis le rapport d'un technicien en ".$metier." avant qu'il soit remis au client en PDF.\n"
        ."Corrige l'orthographe, la grammaire et la ponctuation, et remplace les mots familiers ou approximatifs par le vocabulaire technique exact du métier (ex. : « le truc qui chauffe l'eau » → « le corps de chauffe », « le disjoncteur a sauté » → « le disjoncteur a déclenché »).\n"
        ."Règles strictes :\n"
        ."- N'ajoute AUCUNE information : pas de pièce, de mesure, de cause, de travaux ou de conseil que le technicien n'a pas écrits. Ne supprime aucune information.\n"
        ."- Garde tous les nombres, unités, marques, modèles et références exactement comme écrits.\n"
        ."- Style professionnel, phrases courtes, à la forme impersonnelle ou à la première personne du pluriel ; pas de titre, pas de liste à puces sauf si le technicien en a fait une.\n"
        ."- Si une partie est vide, renvoie une chaîne vide pour cette partie.";
}

/**
 * Corrige le diagnostic et les travaux réalisés du rapport principal.
 * PC du bureau connecté ($allowPc) : la correction lui est confiée et arrive une à deux minutes
 * plus tard (includes/pc_jobs.php) ; sinon, clé API.
 * Retour : ['ok' => bool, 'notice' => string, 'fields' => [champ => 'corrige'|'refuse'|'vide'], 'pending' => bool].
 */
function correction_run(int $ivId, string $trigger = 'system', bool $allowPc = true): array
{
    $iv = db_fetch('SELECT id, category, tech_diagnostic, tech_report FROM interventions WHERE id = ?', [$ivId]);
    if (!$iv) return ['ok' => false, 'notice' => 'Intervention introuvable.', 'fields' => []];
    $diag = trim((string)$iv['tech_diagnostic']); $rep = trim((string)$iv['tech_report']);
    if ($diag === '' && $rep === '') return ['ok' => false, 'notice' => 'Rapport vide : rien à corriger.', 'fields' => []];
    $metier = intervention_category_config()[$iv['category'] ?? '']['label'] ?? 'dépannage du bâtiment';
    $userText = "Diagnostic :\n".($diag !== '' ? $diag : '(vide)')."\n\nTravaux réalisés :\n".($rep !== '' ? $rep : '(vide)');

    if ($allowPc && function_exists('pc_jobs_enabled') && pc_jobs_enabled()) {
        pc_job_create('correction', $ivId, correction_system_prompt($metier)."\n\n".$userText
            ."\n\nRéponds UNIQUEMENT par un objet JSON, sans texte autour, avec exactement ces clés : "
            .'diagnostic (texte corrigé), travaux (texte corrigé).', $trigger);
        return ['ok' => true, 'pending' => true, 'notice' => 'Correction confiée au PC du bureau : elle arrive dans une à deux minutes.', 'fields' => []];
    }

    $r = claude_structured([
        'purpose'  => 'correction-rapport',
        'system'   => correction_system_prompt($metier),
        'messages' => [['role' => 'user', 'content' => $userText]],
        'schema'   => correction_schema(),
        'effort'   => 'low',
        'max_tokens' => 3000,
        'timeout'  => 60,
    ]);
    if (!$r['ok'] || $r['simulated']) {
        return ['ok' => false, 'notice' => (string)($r['error'] ?? 'Claude indisponible').' Le texte d\'origine est utilisé.', 'fields' => []];
    }
    return correction_apply($ivId, $r['data'], 'claude', $trigger);
}

/**
 * Enregistre une correction ($data = ['diagnostic' => …, 'travaux' => …]) après les garde-fous.
 * $source : 'claude' (clé API) ou 'claude_pc' (PC du bureau).
 */
function correction_apply(int $ivId, array $data, string $source = 'claude', string $trigger = 'system'): array
{
    $iv = db_fetch('SELECT id, status, tech_diagnostic, tech_report, technician_id, ref FROM interventions WHERE id = ?', [$ivId]);
    if (!$iv) return ['ok' => false, 'notice' => 'Intervention introuvable.', 'fields' => []];
    $diag = trim((string)$iv['tech_diagnostic']); $rep = trim((string)$iv['tech_report']);
    $res = [];
    $upd = [];
    foreach (['tech_diagnostic' => [$diag, (string)($data['diagnostic'] ?? '')], 'tech_report' => [$rep, (string)($data['travaux'] ?? '')]] as $f => [$o, $c]) {
        $c = trim(preg_replace("/^\\(vide\\)$/u", '', trim($c)));
        if ($o === '') { $res[$f] = 'vide'; $upd[$f.'_corrige'] = null; continue; }
        if (correction_acceptable($o, $c)) { $res[$f] = 'corrige'; $upd[$f.'_corrige'] = $c === $o ? null : $c; }
        else { $res[$f] = 'refuse'; $upd[$f.'_corrige'] = null; }
    }
    db_execute('UPDATE interventions SET tech_diagnostic_corrige = ?, tech_report_corrige = ?, correction_at = NOW(), correction_source = ? WHERE id = ?',
        [$upd['tech_diagnostic_corrige'], $upd['tech_report_corrige'], $source === 'claude_pc' ? 'claude_pc' : 'claude', $ivId]);
    $refused = array_keys(array_filter($res, static fn($v) => $v === 'refuse'));
    log_intervention_history($ivId, (string)$iv['status'], (string)$iv['status'], 'claude', 0, 'Claude',
        'Rapport corrigé (orthographe, vocabulaire technique)'.($source === 'claude_pc' ? ' par le PC du bureau' : '').($refused ? ' — correction écartée pour : '.implode(', ', array_map(static fn($f) => $f === 'tech_diagnostic' ? 'le diagnostic' : 'les travaux', $refused)).' (texte d\'origine gardé)' : ''));
    integration_log('claude', 'rapport corrigé #'.$ivId, ['declencheur' => $trigger, 'source' => $source, 'resultat' => $res]);
    // Le technicien est prévenu que la version corrigée est sur sa fiche (notification seulement).
    if (!empty($iv['technician_id']) && in_array('corrige', $res, true) && function_exists('push_send_to') && notif_enabled('notif_tech_push')) {
        try { push_send_to('tech', (int)$iv['technician_id'], 'Rapport corrigé', 'La version corrigée de votre rapport '.($iv['ref'] ?? '').' est disponible.', notif_abs_url('tech/disp_intervention.php?id='.$ivId.'#rapport'), 'iv-'.$ivId.'-correction'); }
        catch (Throwable $e) {}
    }
    return ['ok' => true, 'notice' => $refused ? 'Une partie de la correction a été écartée par sécurité : le texte d\'origine est gardé pour cette partie.' : '', 'fields' => $res];
}
