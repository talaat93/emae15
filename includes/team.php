<?php
declare(strict_types=1);
/**
 * Plusieurs techniciens sur une intervention.
 *
 * Le technicien principal reste interventions.technician_id (acceptation, trajet, arrivée,
 * rapport, photos, signature, paiement : rien ne change pour lui). Les renforts sont rangés
 * dans intervention_team. Selon interventions.report_mode, choisi par le dispatcher :
 *   'principal' : un seul rapport, celui du technicien principal ;
 *   'chacun'    : chaque renfort rédige aussi son propre rapport (diagnostic, travaux,
 *                 heures, remarques), visible par le bureau et joint au rapport PDF.
 * Les prestations facturées restent celles du rapport principal.
 */

function team_table(): void
{
    static $done = false;
    if ($done) return;
    $done = true;
    try {
        db_execute("CREATE TABLE IF NOT EXISTS intervention_team (
            id INT AUTO_INCREMENT PRIMARY KEY,
            intervention_id INT NOT NULL,
            technician_id INT NOT NULL,
            diagnostic TEXT NULL,
            report TEXT NULL,
            arrived_time VARCHAR(5) NULL,
            close_time VARCHAR(5) NULL,
            notes TEXT NULL,
            submitted_at DATETIME NULL,
            updated_at DATETIME NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_iv_tech (intervention_id, technician_id),
            INDEX idx_tech (technician_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (Throwable $e) {}
}

function report_mode_options(): array
{
    return ['principal' => 'Un seul rapport (technicien principal)', 'chacun' => 'Un rapport par technicien'];
}

function report_mode(array $iv): string
{
    return ($iv['report_mode'] ?? '') === 'chacun' ? 'chacun' : 'principal';
}

/** Renforts d'une intervention, avec leur nom et leur rapport. */
function team_members(int $ivId): array
{
    team_table();
    try {
        return db_fetch_all('SELECT tm.*, t.name AS tech_name, t.phone AS tech_phone FROM intervention_team tm
            LEFT JOIN technicians t ON t.id = tm.technician_id WHERE tm.intervention_id = ? ORDER BY tm.id', [$ivId]);
    } catch (Throwable $e) { return []; }
}

/** Ligne du renfort, ou null si ce technicien n'est pas en renfort sur cette intervention. */
function team_member(int $ivId, int $techId): ?array
{
    team_table();
    try { return db_fetch('SELECT * FROM intervention_team WHERE intervention_id = ? AND technician_id = ?', [$ivId, $techId]); }
    catch (Throwable $e) { return null; }
}

/** Interventions où ce technicien est en renfort. */
function team_intervention_ids(int $techId): array
{
    team_table();
    try { return array_map('intval', array_column(db_fetch_all('SELECT intervention_id FROM intervention_team WHERE technician_id = ?', [$techId]), 'intervention_id')); }
    catch (Throwable $e) { return []; }
}

/**
 * État du rapport d'un renfort : 'non_demande' (un seul rapport), 'a_rediger', 'brouillon'
 * (commencé, pas envoyé) ou 'rendu'. Fonction pure.
 */
function team_report_state(array $member, string $mode): string
{
    if ($mode !== 'chacun') return 'non_demande';
    if (!empty($member['submitted_at'])) return 'rendu';
    return trim((string)($member['diagnostic'] ?? '').(string)($member['report'] ?? '')) !== '' ? 'brouillon' : 'a_rediger';
}

function team_report_state_label(string $state): string
{
    return ['non_demande' => 'Pas de rapport demandé', 'a_rediger' => 'Rapport à rédiger', 'brouillon' => 'Rapport commencé', 'rendu' => 'Rapport rendu'][$state] ?? $state;
}

/** Ajoute un renfort et le prévient. Refus : principal, technicien inactif, déjà présent. */
function team_add(int $ivId, int $techId, array $actor): array
{
    team_table();
    $iv = get_intervention_by_id($ivId);
    if (!$iv) return ['ok' => false, 'error' => 'Intervention introuvable.'];
    if ((int)($iv['technician_id'] ?? 0) === $techId) return ['ok' => false, 'error' => 'C\'est déjà le technicien principal.'];
    $tech = get_tech_by_id($techId);
    if (!$tech || ($tech['status'] ?? '') !== 'actif') return ['ok' => false, 'error' => 'Technicien introuvable ou inactif.'];
    if (team_member($ivId, $techId)) return ['ok' => false, 'error' => $tech['name'].' est déjà en renfort.'];
    db_execute('INSERT INTO intervention_team (intervention_id, technician_id) VALUES (?,?)', [$ivId, $techId]);
    log_intervention_history($ivId, (string)$iv['status'], (string)$iv['status'], 'dispatcher', (int)$actor['id'], (string)$actor['name'], 'Renfort ajouté : '.$tech['name']);
    team_notify_added($ivId, $tech, $iv);
    return ['ok' => true, 'error' => null, 'name' => (string)$tech['name']];
}

function team_remove(int $ivId, int $techId, array $actor): bool
{
    team_table();
    $m = team_member($ivId, $techId);
    if (!$m) return false;
    db_execute('DELETE FROM intervention_team WHERE id = ?', [(int)$m['id']]);
    $iv = get_intervention_by_id($ivId);
    $tech = get_tech_by_id($techId);
    log_intervention_history($ivId, (string)($iv['status'] ?? ''), (string)($iv['status'] ?? ''), 'dispatcher', (int)$actor['id'], (string)$actor['name'], 'Renfort retiré : '.($tech['name'] ?? '#'.$techId));
    return true;
}

/** Enregistre le rapport d'un renfort ; $submit = l'envoyer au bureau. */
function team_report_save(int $ivId, int $techId, array $data, bool $submit): bool
{
    $m = team_member($ivId, $techId);
    if (!$m) return false;
    $t = static fn($k, $max) => mb_substr(trim((string)($data[$k] ?? '')), 0, $max);
    $hm = static fn($k) => preg_match('/^\d{2}:\d{2}$/', (string)($data[$k] ?? '')) ? (string)$data[$k] : null;
    db_execute('UPDATE intervention_team SET diagnostic = ?, report = ?, arrived_time = ?, close_time = ?, notes = ?, updated_at = NOW()'
        .($submit ? ', submitted_at = NOW()' : '').' WHERE id = ?',
        [$t('diagnostic', 5000), $t('report', 5000), $hm('arrived_time'), $hm('close_time'), $t('notes', 2000), (int)$m['id']]);
    return true;
}

/** Prévient le renfort (notification et e-mail, selon les réglages des notifications). */
function team_notify_added(int $ivId, array $tech, array $iv): void
{
    try {
        $s = notif_iv_summary($iv);
        $url = notif_abs_url('tech/disp_intervention.php?id='.$ivId);
        $title = (!empty($iv['urgency']) ? 'URGENT — ' : '').'Vous êtes en renfort sur une intervention';
        $body = $s['client'].($s['city'] ? ' ('.$s['city'].')' : '').' · '.$s['what'].' · '.$s['when'];
        if (notif_enabled('notif_tech_push')) push_send_to('tech', (int)$tech['id'], $title, $body, $url, 'iv-'.$ivId.'-renfort');
        if (notif_enabled('notif_tech_email') && !empty($tech['email'])) {
            $h = static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
            notif_mail((string)$tech['email'], $title.' — '.$s['client'], $title, [$h($body)], $url, 'Voir l\'intervention');
        }
    } catch (Throwable $e) { error_log('[EMAE notif] renfort #'.$ivId.' : '.$e->getMessage()); }
}

/**
 * Le renfort peut encore rédiger ou corriger son rapport : jusqu'à la validation de la facture
 * (même après le rapport du technicien principal, pour ne pas le bloquer s'il est en retard).
 */
function team_report_open(string $status): bool
{
    return !in_array($status, ['facture_validee', 'facture_envoyee', 'payé', 'cloturee', 'annulé', 'facturé'], true);
}
