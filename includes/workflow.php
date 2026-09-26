<?php
declare(strict_types=1);
/**
 * Circuit d'une intervention, de l'appel au paiement.
 *
 *   nouveau → a_assigner → assigné → en_route → sur_place → rapport_rendu
 *   → rapport_verifie → facture_brouillon → facture_validee → facture_envoyee
 *   → payé → cloturee          (+ a_revoir si le rapport est incomplet, + annulé)
 *
 * Les codes déjà présents en base sont conservés tels quels (« assigné », « payé »,
 * « annulé »…) pour ne casser aucune fiche existante ; « confirmé », « terminé »,
 * « devis_envoyé » et « facturé » restent reconnus pour les anciennes fiches.
 */

const WF_ACTORS = ['dispatcher', 'technicien', 'claude', 'system'];

/** Terrain terminé : le rapport est rendu (ou l'intervention est plus avancée). */
function wf_field_done(): array
{
    return ['rapport_rendu', 'rapport_verifie', 'facture_brouillon', 'facture_validee', 'facture_envoyee',
            'payé', 'cloturee', 'terminé', 'facturé', 'devis_envoyé'];
}

/** Plus rien à faire sur le terrain ni en planification. */
function wf_closed(): array
{
    return array_merge(wf_field_done(), ['annulé']);
}

/** Étapes où l'intervention compte dans le chiffre d'affaires. */
function wf_revenue(): array
{
    return ['rapport_verifie', 'facture_brouillon', 'facture_validee', 'facture_envoyee', 'payé', 'cloturee', 'terminé', 'facturé'];
}

/** Le technicien peut encore modifier son rapport. */
function wf_tech_editable(string $status): bool
{
    return !in_array($status, wf_closed(), true);
}

/** Liste SQL sûre pour « status IN (…) » (valeurs constantes du circuit uniquement). */
function wf_sql_list(array $statuses): string
{
    return implode(',', array_map(static fn($s) => "'".str_replace("'", "''", (string)$s)."'", $statuses));
}

/** Transitions proposées au dispatcher sur la fiche (les étapes automatiques ont leurs propres écrans). */
function wf_dispatcher_transitions(): array
{
    return [
        'nouveau'           => ['a_assigner', 'annulé'],
        'a_assigner'        => ['assigné', 'annulé'],
        'confirmé'          => ['a_assigner', 'assigné', 'annulé'],
        'assigné'           => ['en_route', 'sur_place', 'a_assigner', 'annulé'],
        'en_route'          => ['sur_place', 'annulé'],
        'sur_place'         => ['annulé'],
        // Relecture : boutons dédiés sur la fiche (relancer, valider, renvoyer au technicien).
        'a_revoir'          => ['annulé'],
        'rapport_rendu'     => [],
        'rapport_verifie'   => [],
        'facture_brouillon' => ['a_revoir'],
        'facture_validee'   => ['facture_envoyee'],
        'facture_envoyee'   => ['payé'],
        'payé'              => ['cloturee'],
        'terminé'           => ['rapport_verifie', 'facturé', 'payé'],
        'facturé'           => ['payé'],
        'devis_envoyé'      => ['a_assigner', 'annulé'],
        'cloturee'          => [],
        'annulé'            => [],
    ];
}

/**
 * Change le statut d'une fiche et trace le changement dans intervention_history.
 * $extra : autres champs à mettre à jour dans la même opération.
 */
function wf_set_status(int $ivId, string $to, string $actorType, ?int $actorId, string $actorName, string $note = '', array $extra = []): bool
{
    if (!array_key_exists($to, intervention_status_config())) return false;
    if (!in_array($actorType, WF_ACTORS, true)) $actorType = 'system';
    try {
        $row = db_fetch('SELECT status, tech_completed_at FROM interventions WHERE id = ?', [$ivId]);
        if (!$row) return false;
        $from = (string)$row['status'];
        $upd = $extra + ['status' => $to];
        if (in_array($to, ['rapport_rendu', 'terminé'], true) && empty($row['tech_completed_at']) && !isset($upd['tech_completed_at'])) {
            $upd['tech_completed_at'] = date('Y-m-d H:i:s');
        }
        update_intervention($ivId, $upd);
        if ($from !== $to || $note !== '') {
            log_intervention_history($ivId, $from, $to, $actorType, (int)($actorId ?? 0), $actorName, $note);
        }
        return true;
    } catch (Throwable $e) {
        error_log('[EMAE] changement de statut #'.$ivId.' : '.$e->getMessage());
        return false;
    }
}

/** Libellé de l'auteur d'une ligne d'historique. */
function wf_actor_label(string $type): string
{
    return match ($type) {
        'dispatcher'        => 'Dispatcher',
        'technicien', 'tech' => 'Technicien',
        'claude'            => 'Claude',
        default             => 'Système',
    };
}
