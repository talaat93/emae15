<?php
declare(strict_types=1);
/**
 * Relecture du rapport technicien et préparation du brouillon de facture.
 *
 *   rapport_rendu ─ relecture ─┬─ incomplet → a_revoir (message au technicien)
 *                              └─ complet   → rapport_verifie → brouillon de facture → facture_brouillon
 *
 * Claude relit le rapport (texte + photos) et propose : complétude, incohérences, résumé
 * pour le client, lignes de la grille, taux de TVA. Il ne décide rien seul :
 *  - les montants sont toujours recalculés en PHP à partir de price_grid ;
 *  - le brouillon reprend les prestations déclarées et signées sur place ; la proposition
 *    de Claude est affichée à côté, le dispatcher choisit ;
 *  - aucune facture ne part sans validation du dispatcher (phase 7).
 * Sans Claude, des règles fixes font la même vérification de complétude.
 *
 * Données envoyées à Claude (RGPD) : aucune identité ni coordonnée du client, seulement
 * le métier, la demande, le rapport, les prestations, le type de client et les photos.
 */

/* ─── Tables ─────────────────────────────────────────────── */
function review_tables(): void
{
    static $done = false;
    if ($done) return;
    $done = true;
    try {
        db_execute("CREATE TABLE IF NOT EXISTS report_reviews (
            id INT AUTO_INCREMENT PRIMARY KEY,
            intervention_id INT NOT NULL,
            source VARCHAR(20) NOT NULL DEFAULT 'regles',
            model VARCHAR(60) NULL,
            complete TINYINT(1) NOT NULL DEFAULT 0,
            result MEDIUMTEXT NULL,
            notice VARCHAR(255) NULL,
            triggered_by VARCHAR(60) NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_iv (intervention_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        db_execute("CREATE TABLE IF NOT EXISTS invoices (
            id INT AUTO_INCREMENT PRIMARY KEY,
            intervention_id INT NULL,
            client_id INT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'brouillon',
            number VARCHAR(60) NULL,
            label VARCHAR(255) NULL,
            summary TEXT NULL,
            line_items MEDIUMTEXT NULL,
            vat_rate DECIMAL(5,2) NULL,
            total_ht DECIMAL(12,2) NOT NULL DEFAULT 0,
            total_tva DECIMAL(12,2) NOT NULL DEFAULT 0,
            total_ttc DECIMAL(12,2) NOT NULL DEFAULT 0,
            remaining_ttc DECIMAL(12,2) NULL,
            has_free TINYINT(1) NOT NULL DEFAULT 0,
            review_id INT NULL,
            pennylane_id VARCHAR(40) NULL,
            pennylane_status VARCHAR(40) NULL,
            pennylane_url VARCHAR(500) NULL,
            pdf_url VARCHAR(1000) NULL,
            issue_date DATE NULL,
            due_date DATE NULL,
            validated_by INT NULL,
            validated_at DATETIME NULL,
            sent_at DATETIME NULL,
            paid_at DATETIME NULL,
            last_sync_at DATETIME NULL,
            sync_error VARCHAR(500) NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NULL,
            INDEX idx_iv (intervention_id),
            INDEX idx_client (client_id),
            INDEX idx_status (status),
            UNIQUE KEY uq_pennylane (pennylane_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (Throwable $e) { integration_log('review', 'tables : '.$e->getMessage()); }
}

function review_latest(int $ivId): ?array
{
    review_tables();
    try { $r = db_fetch('SELECT * FROM report_reviews WHERE intervention_id = ? ORDER BY id DESC LIMIT 1', [$ivId]); }
    catch (Throwable $e) { return null; }
    if (!$r) return null;
    $r['result'] = json_decode((string)$r['result'], true) ?: [];
    return $r;
}

function invoice_for_intervention(int $ivId): ?array
{
    review_tables();
    try { $r = db_fetch("SELECT * FROM invoices WHERE intervention_id = ? AND status <> 'annulee' ORDER BY id DESC LIMIT 1", [$ivId]); }
    catch (Throwable $e) { return null; }
    if (!$r) return null;
    $r['lines'] = json_decode((string)$r['line_items'], true) ?: [];
    return $r;
}

/* ─── Contrôles fixes (toujours appliqués, avec ou sans Claude) ── */
function review_rule_missing(array $iv): array
{
    $miss = [];
    if (trim((string)($iv['tech_diagnostic'] ?? '')) === '' && trim((string)($iv['tech_report'] ?? '')) === '') $miss[] = 'Diagnostic manquant';
    if (trim((string)($iv['tech_report'] ?? '')) === '')   $miss[] = 'Travaux réalisés non décrits';
    if (!tech_report_lines($iv))                             $miss[] = 'Aucune prestation déclarée';
    if (count(intervention_photo_paths($iv)) < 2)            $miss[] = 'Moins de 2 photos';
    if (empty($iv['client_signature']))                      $miss[] = 'Signature du client absente';
    if (trim((string)($iv['tech_client_name'] ?? '')) === '') $miss[] = 'Nom du signataire absent';
    if (($iv['tech_job_completed'] ?? null) !== null && (int)$iv['tech_job_completed'] === 0 && trim((string)($iv['tech_incomplete_reason'] ?? '')) === '') {
        $miss[] = 'Raison de l\'intervention non terminée absente';
    }
    return $miss;
}

function review_schema(): array
{
    $str = ['type' => 'string'];
    return [
        'type' => 'object', 'additionalProperties' => false,
        'required' => ['complete', 'missing', 'inconsistencies', 'message_to_technician', 'client_summary', 'lines', 'vat_rate', 'vat_reason', 'total_ttc_estimate'],
        'properties' => [
            'complete'              => ['type' => 'boolean'],
            'missing'               => ['type' => 'array', 'items' => $str],
            'inconsistencies'       => ['type' => 'array', 'items' => $str],
            'message_to_technician' => $str,
            'client_summary'        => $str,
            'lines'                 => ['type' => 'array', 'items' => ['type' => 'object', 'additionalProperties' => false, 'required' => ['code', 'qty'],
                                        'properties' => ['code' => $str, 'qty' => ['type' => 'number']]]],
            'vat_rate'              => ['type' => 'string', 'enum' => ['5.5', '10', '20']],
            'vat_reason'            => $str,
            'total_ttc_estimate'    => ['type' => 'number'],
        ],
    ];
}

/** Données du rapport transmises à Claude : strictement ce qui sert à la relecture. */
function review_payload(array $iv, ?array $client): array
{
    $cat = (string)($iv['category'] ?? '');
    $mats = [];
    foreach ((array)json_decode((string)($iv['tech_materials_used'] ?? '[]'), true) as $m) {
        if (is_array($m) && !empty($m['name'])) $mats[] = array_filter(['nom' => $m['name'], 'quantite' => $m['qty'] ?? '', 'unite' => $m['unit'] ?? '', 'prix_ht_unitaire' => $m['price'] ?? '']);
    }
    $yn = static fn($v) => $v === null || $v === '' ? 'non renseigné' : ((int)$v ? 'oui' : 'non');
    return [
        'metier'               => intervention_category_config()[$cat]['label'] ?? $cat,
        'demande'              => ['panne_signalee' => (string)($iv['fault_reported'] ?? $iv['type_label'] ?? ''), 'description' => mb_substr((string)($iv['description'] ?? ''), 0, 2000), 'urgence' => !empty($iv['urgency'])],
        'client'               => ['type' => (string)($client['client_type'] ?? 'inconnu'), 'logement_plus_de_2_ans' => $yn($iv['housing_over_2y'] ?? null), 'taux_tva_de_la_fiche' => iv_vat_rate($iv, $client)],
        'horaires'             => ['arrivee' => !empty($iv['tech_arrived_at']) ? date('d/m/Y H:i', strtotime((string)$iv['tech_arrived_at'])) : null, 'fin' => $iv['tech_close_time'] ?? null],
        'intitule_panne'       => (string)($iv['tech_fault_label'] ?? ''),
        'diagnostic'           => (string)($iv['tech_diagnostic'] ?? ''),
        'travaux_realises'     => (string)($iv['tech_report'] ?? ''),
        'intervention_terminee'=> $yn($iv['tech_job_completed'] ?? null),
        'raison_non_terminee'  => (string)($iv['tech_incomplete_reason'] ?? ''),
        'retour_a_prevoir'     => $yn($iv['tech_return_visit'] ?? null),
        'prestations_declarees'=> (array)json_decode((string)($iv['tech_lines'] ?? '[]'), true),
        'materiel'             => $mats,
        'remarques'            => (string)($iv['tech_notes_extra'] ?? ''),
        'nombre_photos'        => count(intervention_photo_paths($iv)),
        'signature_client'     => !empty($iv['client_signature']),
        'nom_signataire_renseigne' => trim((string)($iv['tech_client_name'] ?? '')) !== '',
    ];
}

/**
 * Relit le rapport d'une intervention et fait avancer le circuit.
 * $trigger : 'technicien', 'dispatcher' ou 'system' (pour l'historique).
 * Retour : ['ok' => bool, 'complete' => bool, 'source' => 'claude'|'regles', 'notice' => ?string, 'status' => string]
 */
function review_run(int $ivId, string $trigger = 'system', ?array $actor = null): array
{
    review_tables();
    $iv = db_fetch('SELECT * FROM interventions WHERE id = ?', [$ivId]);
    if (!$iv) return ['ok' => false, 'complete' => false, 'source' => 'regles', 'notice' => 'Intervention introuvable.', 'status' => ''];
    if (!in_array((string)$iv['status'], ['rapport_rendu', 'a_revoir'], true)) {
        return ['ok' => false, 'complete' => false, 'source' => 'regles', 'notice' => 'Le rapport n\'est pas en attente de relecture.', 'status' => (string)$iv['status']];
    }
    $client = !empty($iv['client_id']) ? get_client_by_id((int)$iv['client_id']) : null;
    $vat = iv_vat_rate($iv, $client);
    $ruleMissing = review_rule_missing($iv);
    $cat = (string)($iv['category'] ?? '');
    $grid = array_map(static fn($r) => $r['code'].' — '.$r['label'].' — '.$r['unit'].' — '.number_format((float)$r['price_ht'], 2, ',', '').((int)$r['is_percent'] ? ' %' : ' € HT'),
        price_grid_rows(true, isset(intervention_category_config()[$cat]) ? $cat : ''));

    // Photos : 4 au plus, pour limiter le volume envoyé.
    $content = [];
    foreach (array_slice(intervention_photo_paths($iv), 0, 4) as $p) {
        $blk = claude_image_block(__DIR__.'/../'.ltrim((string)$p, '/'));
        if ($blk) $content[] = $blk;
    }
    $content[] = ['type' => 'text', 'text' =>
        "Rapport d'intervention à relire (JSON) :\n".json_encode(review_payload($iv, $client), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
        ."\n\nGrille tarifaire du métier (code — désignation — unité — prix) :\n".implode("\n", $grid)
        .($content ? "\n\nLes images ci-dessus sont les photos prises par le technicien." : '')];

    $r = claude_structured([
        'purpose'    => 'relecture-rapport',
        'system'     => "Tu relis le rapport d'un technicien de dépannage (électricité, plomberie, chauffage…) avant facturation. Tu assistes le dispatcher : tu ne décides rien seul.\n"
            ."- complete : false s'il manque une information indispensable pour facturer ou comprendre l'intervention (diagnostic, travaux, prestations, photos, signature) ; liste-les dans missing.\n"
            ."- inconsistencies : incohérences entre la demande, le diagnostic, les travaux, les prestations, le matériel et les photos (ex. : prestation déclarée sans travaux correspondants, quantité anormale, photo sans rapport, heure de fin avant l'arrivée).\n"
            ."- message_to_technician : si complete est false, message court et poli (tutoiement interdit) indiquant précisément quoi compléter ; sinon chaîne vide.\n"
            ."- client_summary : 2 à 4 phrases claires pour la facture du client, sans jargon ni prix.\n"
            ."- lines : prestations à facturer, UNIQUEMENT avec des codes exacts de la grille fournie et des quantités ; ne calcule aucun prix, n'invente aucun code. Le matériel hors grille est géré à part.\n"
            ."- vat_rate : 10 pour un particulier dont le logement a plus de 2 ans (travaux d'entretien/réparation), sinon 20 ; explique dans vat_reason.\n"
            ."- total_ttc_estimate : ton estimation indicative, qui sera recalculée par le logiciel.",
        'messages'   => [['role' => 'user', 'content' => $content]],
        'schema'     => review_schema(),
        'effort'     => 'medium',
        'max_tokens' => 6000,
        'timeout'    => 90,
    ]);

    $fromClaude = $r['ok'] && !$r['simulated'];
    if ($fromClaude) {
        $res = $r['data'];
        $map = price_grid_map();
        $res['lines'] = array_values(array_filter($res['lines'], static fn($l) => isset($map[strtoupper((string)$l['code'])]) && (float)$l['qty'] > 0));
        // Les contrôles fixes s'ajoutent à l'avis de Claude : un manque objectif bloque toujours.
        $res['missing'] = array_values(array_unique(array_merge($ruleMissing, array_map('strval', $res['missing']))));
        $complete = (bool)$res['complete'] && !$ruleMissing;
        if ((float)$res['vat_rate'] !== $vat) $res['inconsistencies'][] = 'Claude propose une TVA à '.$res['vat_rate'].' % alors que la fiche indique '.rtrim(rtrim(number_format($vat, 1, ',', ''), '0'), ',').' % ('.$res['vat_reason'].').';
    } else {
        $complete = !$ruleMissing;
        $res = [
            'complete' => $complete, 'missing' => $ruleMissing, 'inconsistencies' => [],
            'message_to_technician' => $ruleMissing ? 'Merci de compléter votre rapport : '.implode(', ', array_map('mb_strtolower', $ruleMissing)).'.' : '',
            'client_summary' => trim(implode(' ', array_filter([
                !empty($iv['tech_diagnostic']) ? 'Constat : '.rtrim(trim((string)$iv['tech_diagnostic']), '.').'.' : '',
                !empty($iv['tech_report']) ? 'Travaux réalisés : '.rtrim(trim((string)$iv['tech_report']), '.').'.' : '',
            ]))),
            'lines' => (array)json_decode((string)($iv['tech_lines'] ?? '[]'), true),
            'vat_rate' => rtrim(rtrim(number_format($vat, 1, '.', ''), '0'), '.'), 'vat_reason' => 'Taux de la fiche.', 'total_ttc_estimate' => 0,
        ];
        if (!empty($iv['tech_arrived_at']) && !empty($iv['tech_close_time']) && substr((string)$iv['tech_close_time'], 0, 5) <= date('H:i', strtotime((string)$iv['tech_arrived_at']))) {
            $res['inconsistencies'][] = 'Heure de fin antérieure à l\'arrivée.';
        }
    }
    // Chiffrage de la proposition de Claude (pour comparaison) : toujours recalculé ici.
    $declared = pricing_compute(tech_report_lines($iv), $vat);
    $freeLines = array_values(array_filter(tech_report_lines($iv), static fn($l) => empty($l['code'])));
    $proposed = pricing_compute(array_merge($res['lines'], $freeLines), $vat);
    $res['declared_total_ttc'] = $declared['total_ttc'];
    $res['proposed_total_ttc'] = $proposed['total_ttc'];
    if ($fromClaude && abs($declared['total_ttc'] - $proposed['total_ttc']) >= 0.01) {
        $res['inconsistencies'][] = 'Les prestations proposées par Claude ('.money_fr($proposed['total_ttc']).' TTC) diffèrent de celles déclarées par le technicien ('.money_fr($declared['total_ttc']).' TTC).';
    }
    if ($declared['has_free']) $res['inconsistencies'][] = 'Matériel hors grille facturé : prix saisis par le technicien, à vérifier.';

    $notice = $fromClaude ? null : ($r['error'] ?? 'Claude indisponible').' Relecture faite avec les contrôles standard.';
    db_execute('INSERT INTO report_reviews (intervention_id, source, model, complete, result, notice, triggered_by) VALUES (?,?,?,?,?,?,?)', [
        $ivId, $fromClaude ? 'claude' : 'regles', $r['model'] ?? null, $complete ? 1 : 0,
        json_encode($res, JSON_UNESCAPED_UNICODE), $notice !== null ? mb_substr($notice, 0, 255) : null, $trigger,
    ]);
    $reviewId = db_last_id();
    $actorType = $fromClaude ? 'claude' : 'system';
    $actorName = $fromClaude ? 'Claude' : 'Contrôle automatique';

    if (!$complete) {
        $msg = trim((string)$res['message_to_technician']) ?: 'Merci de compléter votre rapport : '.implode(', ', $res['missing']).'.';
        wf_set_status($ivId, 'a_revoir', $actorType, 0, $actorName, 'Rapport à compléter : '.implode(', ', array_slice($res['missing'], 0, 5)), ['review_message' => mb_substr($msg, 0, 1000)]);
        notify_tech_report_incomplete($ivId, $msg);
        return ['ok' => true, 'complete' => false, 'source' => $fromClaude ? 'claude' : 'regles', 'notice' => $notice, 'status' => 'a_revoir'];
    }
    review_accept($ivId, $reviewId, $actorType, 0, $actorName);
    return ['ok' => true, 'complete' => true, 'source' => $fromClaude ? 'claude' : 'regles', 'notice' => $notice, 'status' => 'facture_brouillon'];
}

/**
 * Rapport vérifié : prépare le brouillon de facture (prestations déclarées et signées sur place,
 * chiffrées par la grille) puis passe la fiche en « Facture à valider ».
 */
function review_accept(int $ivId, ?int $reviewId, string $actorType, int $actorId, string $actorName, string $note = ''): ?int
{
    review_tables();
    $iv = db_fetch('SELECT * FROM interventions WHERE id = ?', [$ivId]);
    if (!$iv) return null;
    wf_set_status($ivId, 'rapport_verifie', $actorType, $actorId, $actorName, $note !== '' ? $note : 'Rapport vérifié', ['review_message' => null]);

    $existing = invoice_for_intervention($ivId);
    if ($existing && $existing['status'] !== 'brouillon') {
        // Une facture validée ou envoyée n'est jamais remplacée automatiquement.
        return (int)$existing['id'];
    }
    $client = !empty($iv['client_id']) ? get_client_by_id((int)$iv['client_id']) : null;
    $calc = pricing_compute(tech_report_lines($iv), iv_vat_rate($iv, $client));
    $review = $reviewId ? db_fetch('SELECT result FROM report_reviews WHERE id = ?', [$reviewId]) : review_latest($ivId);
    $result = is_array($review['result'] ?? null) ? $review['result'] : (json_decode((string)($review['result'] ?? ''), true) ?: []);
    $cat = intervention_category_config()[$iv['category'] ?? '']['label'] ?? 'Intervention';
    $label = $cat.' — '.(trim((string)($iv['tech_fault_label'] ?? '')) ?: trim((string)($iv['type_label'] ?? '')) ?: 'intervention').' ('.($iv['ref'] ?? '#'.$ivId).')';
    $summary = trim((string)($result['client_summary'] ?? ''));
    $vals = [
        (int)($iv['client_id'] ?? 0) ?: null, mb_substr($label, 0, 255), $summary,
        json_encode($calc['lines'], JSON_UNESCAPED_UNICODE), $calc['vat_rate'], $calc['total_ht'], $calc['total_tva'], $calc['total_ttc'], $calc['has_free'] ? 1 : 0, $reviewId,
    ];
    if ($existing) {
        db_execute('UPDATE invoices SET client_id = ?, label = ?, summary = ?, line_items = ?, vat_rate = ?, total_ht = ?, total_tva = ?, total_ttc = ?, has_free = ?, review_id = ?, updated_at = NOW() WHERE id = ?',
            array_merge($vals, [(int)$existing['id']]));
        $invId = (int)$existing['id'];
    } else {
        db_execute('INSERT INTO invoices (client_id, label, summary, line_items, vat_rate, total_ht, total_tva, total_ttc, has_free, review_id, intervention_id, status, updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,\'brouillon\',NOW())',
            array_merge($vals, [$ivId]));
        $invId = db_last_id();
    }
    update_intervention($ivId, ['amount_ht' => $calc['total_ht'], 'amount_ttc' => $calc['total_ttc']]);
    // Brouillon dans Pennylane si le module est disponible (phase 6) ; un échec ne bloque rien.
    if (function_exists('pennylane_push_draft')) {
        try { pennylane_push_draft($invId); } catch (Throwable $e) { integration_log('pennylane', 'brouillon #'.$invId.' : '.$e->getMessage()); }
    }
    wf_set_status($ivId, 'facture_brouillon', $actorType, $actorId, $actorName, 'Brouillon de facture préparé ('.money_fr($calc['total_ttc']).' TTC)');
    integration_log('audit', 'brouillon de facture #'.$invId, ['intervention' => $ivId, 'acteur' => $actorType]);
    return $invId;
}

/** Relecture en arrière-plan, une fois la réponse envoyée au technicien (pas d'attente sur son téléphone). */
function review_run_after_response(int $ivId): void
{
    register_shutdown_function(static function () use ($ivId): void {
        // Réponse envoyée au technicien avant la relecture (PHP-FPM ou LiteSpeed chez o2switch).
        if (function_exists('fastcgi_finish_request')) @fastcgi_finish_request();
        elseif (function_exists('litespeed_finish_request')) @litespeed_finish_request();
        @ignore_user_abort(true);
        @set_time_limit(180);
        try { review_run($ivId, 'technicien'); }
        catch (Throwable $e) { integration_log('review', 'relecture #'.$ivId.' : '.$e->getMessage()); }
    });
}

/** Le rapport doit être complété : message au technicien (notification et e-mail). */
function notify_tech_report_incomplete(int $ivId, string $message): void
{
    try {
        $iv = get_intervention_by_id($ivId);
        if (!$iv || empty($iv['technician_id'])) return;
        $s = notif_iv_summary($iv);
        $url = notif_abs_url('tech/disp_intervention.php?id='.$ivId.'#rapport');
        push_send_to('tech', (int)$iv['technician_id'], 'Rapport à compléter', $s['client'].' — '.mb_substr($message, 0, 120), $url, 'rev-'.$ivId);
        if (!empty($iv['tech_email'])) {
            $h = static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
            notif_mail((string)$iv['tech_email'], 'Rapport à compléter — '.$s['client'], 'Rapport à compléter', [
                '<b>'.$h($s['client']).'</b> · '.$h($s['what']),
                nl2br($h($message)),
            ], $url, 'Compléter le rapport');
        }
    } catch (Throwable $e) { error_log('[EMAE notif] rapport à revoir #'.$ivId.' : '.$e->getMessage()); }
}
