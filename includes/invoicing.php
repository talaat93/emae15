<?php
declare(strict_types=1);
/**
 * Validation, envoi et relances des factures (phase 7).
 * Règle d'or : aucune facture ne part au client sans l'action explicite d'un dispatcher.
 * Les montants sont toujours recalculés par pricing_compute() à partir de la grille.
 */

function invoice_status_config(): array
{
    return [
        'brouillon' => ['label' => 'À valider',  'color' => '#c2410c', 'bg' => '#ffedd5'],
        'validee'   => ['label' => 'Validée, non envoyée', 'color' => '#a16207', 'bg' => '#fef9c3'],
        'envoyee'   => ['label' => 'Envoyée',    'color' => '#1d4ed8', 'bg' => '#dbeafe'],
        'payee'     => ['label' => 'Payée',      'color' => '#15803d', 'bg' => '#dcfce7'],
        'annulee'   => ['label' => 'Annulée',    'color' => '#64748b', 'bg' => '#f1f5f9'],
    ];
}

function invoice_badge(array $inv): string
{
    $cfg = invoice_status_config()[$inv['status']] ?? ['label' => $inv['status'], 'color' => '#334155', 'bg' => '#f1f5f9'];
    $late = invoice_is_late($inv);
    $label = $late ? 'En retard' : $cfg['label'];
    [$fg, $bg] = $late ? ['#b91c1c', '#fee2e2'] : [$cfg['color'], $cfg['bg']];
    return '<span style="display:inline-block;padding:.12rem .55rem;border-radius:99px;font-size:.74rem;font-weight:600;white-space:nowrap;color:'.$fg.';background:'.$bg.';">'.htmlspecialchars($label, ENT_QUOTES, 'UTF-8').'</span>';
}

function invoice_is_late(array $inv): bool
{
    return $inv['status'] === 'envoyee' && !empty($inv['due_date']) && $inv['due_date'] < date('Y-m-d')
        && (float)($inv['remaining_ttc'] ?? $inv['total_ttc']) > 0;
}

function invoice_days_late(array $inv): int
{
    return empty($inv['due_date']) ? 0 : max(0, (int)floor((time() - strtotime((string)$inv['due_date'])) / 86400));
}

/**
 * Enregistre les lignes modifiées par le dispatcher : codes de la grille (prix de la grille)
 * ou lignes libres (désignation, quantité, prix HT). Le total est recalculé ici.
 */
function invoice_save_draft(int $invoiceId, array $lines, float $vatRate, string $summary, array $actor): array
{
    $inv = invoice_by_id($invoiceId);
    if (!$inv || $inv['status'] !== 'brouillon') return ['ok' => false, 'error' => 'Seul un brouillon peut être modifié.'];
    $calc = pricing_compute($lines, $vatRate);
    if (!$calc['lines']) return ['ok' => false, 'error' => 'La facture doit contenir au moins une ligne.'];
    db_execute('UPDATE invoices SET line_items = ?, vat_rate = ?, total_ht = ?, total_tva = ?, total_ttc = ?, has_free = ?, summary = ?, updated_at = NOW() WHERE id = ?', [
        json_encode($calc['lines'], JSON_UNESCAPED_UNICODE), $calc['vat_rate'], $calc['total_ht'], $calc['total_tva'], $calc['total_ttc'],
        $calc['has_free'] ? 1 : 0, mb_substr(trim($summary), 0, 5000), $invoiceId,
    ]);
    if (!empty($inv['intervention_id'])) update_intervention((int)$inv['intervention_id'], ['amount_ht' => $calc['total_ht'], 'amount_ttc' => $calc['total_ttc'], 'vat_rate' => $calc['vat_rate']]);
    integration_log('audit', 'brouillon modifié #'.$invoiceId, ['dispatcher' => (int)$actor['id'], 'ttc' => $calc['total_ttc']]);
    // Le brouillon Pennylane est remplacé pour rester identique (un échec n'empêche pas l'enregistrement local).
    $push = pennylane_push_draft($invoiceId);
    return ['ok' => true, 'error' => $push['ok'] ? null : 'Brouillon enregistré, mais non transmis à Pennylane : '.$push['error'], 'warnings' => $calc['warnings']];
}

/**
 * « Valider et envoyer » : brouillon à jour dans Pennylane → finalisation (numéro définitif)
 * → envoi par e-mail au client → copie au technicien. Chaque étape réussie est conservée :
 * en cas d'échec, on peut reprendre là où on s'est arrêté.
 */
function invoice_validate_and_send(int $invoiceId, string $email, array $actor): array
{
    $inv = invoice_by_id($invoiceId);
    if (!$inv) return ['ok' => false, 'error' => 'Facture introuvable.'];
    if (!in_array($inv['status'], ['brouillon', 'validee'], true)) return ['ok' => false, 'error' => 'Cette facture a déjà été envoyée.'];
    $email = trim($email);
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return ['ok' => false, 'error' => 'Adresse e-mail du client invalide.'];
    if (!empty($inv['client_id'])) {
        $c = get_client_by_id((int)$inv['client_id']);
        if ($c && trim((string)$c['email']) === '') db_execute('UPDATE clients SET email = ? WHERE id = ?', [$email, (int)$inv['client_id']]);
    }
    $ivId = (int)($inv['intervention_id'] ?? 0);
    $who = (string)$actor['name'];

    if ($inv['status'] === 'brouillon') {
        if (empty($inv['pennylane_id']) || (!pennylane_simulated() && str_starts_with((string)$inv['pennylane_id'], 'SIM-'))) {
            $p = pennylane_push_draft($invoiceId);
            if (!$p['ok']) return ['ok' => false, 'error' => 'Brouillon non transmis à Pennylane : '.$p['error']];
        }
        $f = pennylane_finalize($invoiceId);
        if (!$f['ok']) return ['ok' => false, 'error' => 'Finalisation refusée par Pennylane : '.$f['error']];
        db_execute("UPDATE invoices SET status = 'validee', validated_by = ?, validated_at = NOW(), issue_date = COALESCE(issue_date, CURDATE()), updated_at = NOW() WHERE id = ?", [(int)$actor['id'], $invoiceId]);
        if ($ivId) wf_set_status($ivId, 'facture_validee', 'dispatcher', (int)$actor['id'], $who, 'Facture '.$f['number'].' validée');
        integration_log('audit', 'facture validée #'.$invoiceId, ['numero' => $f['number'], 'dispatcher' => (int)$actor['id']]);
    }
    $s = pennylane_send_email($invoiceId, [$email]);
    if (!$s['ok']) return ['ok' => false, 'error' => 'Facture validée mais pas encore envoyée : '.$s['error'].' Utilisez « Envoyer » pour réessayer.', 'partial' => true];
    db_execute("UPDATE invoices SET status = 'envoyee', sent_at = NOW(), remaining_ttc = COALESCE(remaining_ttc, total_ttc), updated_at = NOW() WHERE id = ?", [$invoiceId]);
    $inv = invoice_by_id($invoiceId);
    if ($ivId) wf_set_status($ivId, 'facture_envoyee', 'dispatcher', (int)$actor['id'], $who, 'Facture '.($inv['number'] ?? '').' envoyée à '.$email.(!empty($s['simulated']) ? ' (SIMULATION)' : ''));
    integration_log('audit', 'facture envoyée #'.$invoiceId, ['numero' => $inv['number'], 'dispatcher' => (int)$actor['id'], 'simulation' => !empty($s['simulated'])]);
    invoice_copy_to_tech($inv);
    return ['ok' => true, 'error' => null, 'simulated' => !empty($s['simulated']), 'number' => $inv['number']];
}

/** Copie au technicien : numéro et montant de la facture de son intervention. */
function invoice_copy_to_tech(array $inv): void
{
    if (empty($inv['intervention_id'])) return;
    try {
        $iv = get_intervention_by_id((int)$inv['intervention_id']);
        if (!$iv || empty($iv['tech_email'])) return;
        $s = notif_iv_summary($iv);
        $h = static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
        notif_mail((string)$iv['tech_email'], 'Facture envoyée — '.$s['client'], 'Facture envoyée au client', [
            '<b>'.$h($s['client']).'</b> · '.$h($s['what']),
            'Facture '.$h((string)$inv['number']).' : '.$h(money_fr((float)$inv['total_ttc'])).' TTC.',
        ]);
    } catch (Throwable $e) { error_log('[EMAE] copie facture technicien : '.$e->getMessage()); }
}

/** Paiement reçu hors Pennylane (espèces, chèque…) : facture payée, intervention clôturée. */
function invoice_mark_paid(int $invoiceId, array $actor): array
{
    $inv = invoice_by_id($invoiceId);
    if (!$inv || !in_array($inv['status'], ['validee', 'envoyee'], true)) return ['ok' => false, 'error' => 'Seule une facture validée ou envoyée peut être marquée payée.'];
    $r = pennylane_mark_paid($invoiceId, $actor);
    if (!$r['ok']) return $r;
    integration_log('audit', 'facture marquée payée #'.$invoiceId, ['dispatcher' => (int)$actor['id']]);
    return ['ok' => true, 'error' => null];
}

/* ─── Relances ───────────────────────────────────────────── */
function reminders_table(): void
{
    static $done = false;
    if ($done) return;
    $done = true;
    try {
        db_execute("CREATE TABLE IF NOT EXISTS invoice_reminders (
            id INT AUTO_INCREMENT PRIMARY KEY,
            invoice_id INT NOT NULL,
            level INT NOT NULL DEFAULT 1,
            channel VARCHAR(20) NOT NULL DEFAULT 'email',
            recipient VARCHAR(190) NULL,
            subject VARCHAR(255) NULL,
            body TEXT NULL,
            source VARCHAR(20) NULL,
            sent_by INT NULL,
            sent_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_inv (invoice_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (Throwable $e) {}
}

function invoice_reminders(int $invoiceId): array
{
    reminders_table();
    try { return db_fetch_all('SELECT * FROM invoice_reminders WHERE invoice_id = ? ORDER BY id', [$invoiceId]); } catch (Throwable $e) { return []; }
}

/**
 * Texte de relance selon le niveau, sans base de données, sans réseau, sans Claude
 * (donc testable). $invoice : ligne de la table invoices (number, total_ttc, remaining_ttc,
 * due_date, issue_date, label). Niveau 1 : rappel cordial ; 2 : relance ferme ;
 * 3 et plus : dernière relance avant recouvrement. Aucun montant ni pénalité inventés :
 * seuls le numéro, le reste dû et l'échéance de la facture sont repris.
 * « {{client}} » est remplacé par le nom du client au moment de l'envoi.
 * Retour : ['subject' => string, 'body' => string, 'niveau' => int]
 */
function reminder_draft(array $invoice, int $niveau): array
{
    $niveau = max(1, $niveau);
    $numero = trim((string)($invoice['number'] ?? '')) ?: 'sans numéro';
    $reste = (float)($invoice['remaining_ttc'] ?? $invoice['total_ttc'] ?? 0);
    $echeance = !empty($invoice['due_date']) ? date('d/m/Y', strtotime((string)$invoice['due_date'])) : '';
    $societe = company_name();
    $tel = company_phone_safe();
    $ton = match (true) {
        $niveau >= 3 => "Malgré nos précédentes relances, cette facture reste impayée à ce jour. Sans règlement de votre part sous 8 jours, nous serons contraints d'engager une procédure de recouvrement.",
        $niveau === 2 => "Sauf erreur de notre part, nous n'avons toujours pas reçu votre règlement malgré notre précédent message. Nous vous remercions de bien vouloir régulariser rapidement.",
        default => "Sauf erreur de notre part, cette facture n'a pas encore été réglée. Il s'agit peut-être d'un simple oubli.",
    };
    $body = "Bonjour {{client}},\n\n".$ton."\n\nFacture n° ".$numero.' — montant restant dû : '.money_fr($reste)
        .($echeance !== '' ? ' — échéance : '.$echeance : '').".\n\n"
        .'Pour toute question ou difficulté de paiement, '.($tel !== '' ? 'appelez-nous au '.$tel : 'contactez-nous').".\n\nCordialement,\n".$societe;
    $subject = match (true) {
        $niveau >= 3 => 'Dernière relance avant recouvrement — facture '.$numero,
        $niveau === 2 => 'Relance n° 2 — facture '.$numero,
        default => 'Rappel — facture '.$numero,
    };
    return ['subject' => $subject, 'body' => $body, 'niveau' => $niveau];
}

/**
 * Brouillon de relance rédigé par Claude (ton adapté au retard et au nombre de relances),
 * à relire par le dispatcher ; repli sur reminder_draft() si Claude est absent.
 * Seuls le numéro, le montant, les dates et l'historique des relances sont transmis ;
 * le nom du client est ajouté au moment de l'envoi, jamais envoyé à Claude.
 */
function reminder_draft_ia(int $invoiceId): array
{
    $inv = invoice_by_id($invoiceId);
    if (!$inv) return ['ok' => false, 'subject' => '', 'body' => '', 'source' => '', 'notice' => 'Facture introuvable.'];
    $prev = invoice_reminders($invoiceId);
    $level = count($prev) + 1;
    $remaining = (float)($inv['remaining_ttc'] ?? $inv['total_ttc']);
    $facts = [
        'numero' => (string)$inv['number'], 'montant_restant_ttc' => money_fr($remaining), 'date_facture' => $inv['issue_date'] ? date('d/m/Y', strtotime((string)$inv['issue_date'])) : null,
        'echeance' => $inv['due_date'] ? date('d/m/Y', strtotime((string)$inv['due_date'])) : null, 'jours_de_retard' => invoice_days_late($inv),
        'relance_numero' => $level, 'relances_precedentes' => array_map(static fn($r) => date('d/m/Y', strtotime((string)$r['sent_at'])), $prev),
        'objet_intervention' => (string)$inv['label'], 'entreprise' => company_name(), 'telephone_entreprise' => company_phone_safe(),
    ];
    $schema = ['type' => 'object', 'additionalProperties' => false, 'required' => ['subject', 'body'],
               'properties' => ['subject' => ['type' => 'string'], 'body' => ['type' => 'string']]];
    $r = claude_structured([
        'purpose'    => 'relance-facture',
        'system'     => "Tu rédiges un e-mail de relance de facture impayée pour une entreprise de dépannage, en français, vouvoiement. "
            ."Commence par « Bonjour {{client}}, » (le nom sera ajouté par le logiciel). Ton : 1re relance cordial (simple oubli possible), 2e relance plus ferme, "
            ."3e relance et plus : ferme et factuel, mentionne une possible mise en demeure sans menace excessive. Rappelle le numéro, le montant restant et l'échéance "
            ."exactement comme fournis, propose de régler ou d'appeler en cas de difficulté, signe au nom de l'entreprise. N'invente aucun montant, date ou pénalité. 120 mots maximum.",
        'messages'   => [['role' => 'user', 'content' => json_encode($facts, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)]],
        'schema'     => $schema,
        'effort'     => 'low',
        'max_tokens' => 1500,
        'timeout'    => 45,
    ]);
    if ($r['ok'] && !$r['simulated']) {
        return ['ok' => true, 'subject' => (string)$r['data']['subject'], 'body' => (string)$r['data']['body'], 'source' => 'claude', 'notice' => null, 'level' => $level];
    }
    $d = reminder_draft($inv, $level);
    return ['ok' => true, 'subject' => $d['subject'], 'body' => $d['body'], 'source' => 'modele',
            'notice' => ($r['error'] ?? 'Claude indisponible').' Modèle standard proposé.', 'level' => $level];
}

/**
 * Destinataire réel d'un e-mail de relance. En mode simulation, rien ne part au client :
 * l'e-mail va à l'« adresse de test » des réglages si elle existe, sinon nulle part (null).
 */
function reminder_recipient(string $destinataire): ?string
{
    if (integration_setting('mode_simulation', '0') !== '1') return $destinataire;
    $test = trim(integration_setting('adresse_test', ''));
    return filter_var($test, FILTER_VALIDATE_EMAIL) ? $test : null;
}

/** Envoi effectif (mail() du serveur, comme les autres notifications du site) et traces. */
function reminder_deliver(array $invoice, int $niveau, string $destinataire, string $subject, string $body, string $source, int $actorId): array
{
    if (!filter_var($destinataire, FILTER_VALIDATE_EMAIL)) return ['ok' => false, 'error' => 'Adresse e-mail invalide.'];
    $subject = trim($subject); $body = trim($body);
    if ($subject === '' || $body === '') return ['ok' => false, 'error' => 'Objet et message obligatoires.'];
    $invoiceId = (int)($invoice['id'] ?? 0);
    $to = reminder_recipient($destinataire);
    if ($to === null) {
        integration_log('relances', 'relance non envoyée (mode simulation, aucune adresse de test)', ['facture' => $invoiceId, 'niveau' => $niveau]);
        return ['ok' => false, 'error' => 'Mode simulation : la relance n\'est pas envoyée au client. Indiquez une adresse de test dans les réglages pour la recevoir à sa place.'];
    }
    $simu = $to !== $destinataire;
    $c = !empty($invoice['client_id']) ? get_client_by_id((int)$invoice['client_id']) : null;
    $name = $c ? trim(($c['firstname'] ?? '').' '.($c['lastname'] ?? '')) : '';
    $body = str_replace('{{client}}', $name !== '' ? $name : 'Madame, Monsieur', $body);
    if ($simu) $subject = '[SIMULATION — destinataire réel : '.$destinataire.'] '.$subject;
    $h = static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    $paras = array_map(static fn($p) => nl2br($h(trim($p))), array_filter(preg_split("/\n{2,}/", $body)));
    if (!notif_mail($to, $subject, $subject, array_values($paras))) {
        integration_log('relances', 'échec d\'envoi de la relance', ['facture' => $invoiceId, 'niveau' => $niveau]);
        return ['ok' => false, 'error' => 'L\'e-mail n\'a pas pu être envoyé par le serveur.'];
    }
    reminders_table();
    db_execute('INSERT INTO invoice_reminders (invoice_id, level, recipient, subject, body, source, sent_by) VALUES (?,?,?,?,?,?,?)',
        [$invoiceId, $niveau, $to, mb_substr($subject, 0, 255), $body, $source.($simu ? '-simulation' : ''), $actorId ?: null]);
    integration_log('relances', 'relance envoyée'.($simu ? ' (simulation, à l\'adresse de test)' : ''), ['facture' => $invoiceId, 'niveau' => $niveau]);
    return ['ok' => true, 'error' => null, 'simulated' => $simu];
}

/**
 * Envoie la relance standard de niveau $niveau à $destinataire, la journalise dans
 * invoice_reminders et integration_log. Renvoie true si l'e-mail est parti.
 */
function reminder_send(array $invoice, int $niveau, string $destinataire): bool
{
    $d = reminder_draft($invoice, $niveau);
    return reminder_deliver($invoice, $d['niveau'], $destinataire, $d['subject'], $d['body'], 'modele', 0)['ok'];
}

/** Envoi d'une relance dont le texte a été relu ou modifié par le dispatcher (écran Factures). */
function reminder_send_texte(int $invoiceId, string $to, string $subject, string $body, string $source, array $actor): array
{
    $inv = invoice_by_id($invoiceId);
    if (!$inv || !in_array($inv['status'], ['envoyee', 'validee'], true)) return ['ok' => false, 'error' => 'Cette facture ne peut pas être relancée.'];
    $r = reminder_deliver($inv, count(invoice_reminders($invoiceId)) + 1, $to, $subject, $body, $source, (int)$actor['id']);
    if ($r['ok']) integration_log('audit', 'relance envoyée facture #'.$invoiceId, ['dispatcher' => (int)$actor['id']]);
    return $r;
}
