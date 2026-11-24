<?php
declare(strict_types=1);
/**
 * Travaux confiés au PC du bureau (Claude Code local, mode réponse seule) : correction du
 * rapport et relecture du rapport. Même chemin que l'aide au technicien (api/assist.php, clé du
 * PC), pour économiser la clé API Claude.
 *
 * Le PC ne reçoit que du texte (jamais les photos, ni le nom, le téléphone ou l'adresse du
 * client). Il répond par un objet JSON, vérifié ici exactement comme une réponse de l'API.
 * Si le PC ne répond pas dans les 3 minutes, ou répond mal, le site fait le travail avec la clé
 * API (ou, sans clé, avec les contrôles standard) : rien ne reste bloqué.
 */

const PC_JOB_DELAY = 180;   // secondes avant le relais par l'API

function pc_jobs_table(): void
{
    static $done = false;
    if ($done) return;
    $done = true;
    try {
        db_execute("CREATE TABLE IF NOT EXISTS pc_jobs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            kind VARCHAR(20) NOT NULL,
            intervention_id INT NOT NULL,
            trigger_by VARCHAR(20) NOT NULL DEFAULT 'system',
            prompt MEDIUMTEXT NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'en_attente',
            result_note VARCHAR(255) NULL,
            relay_token VARCHAR(40) NULL,
            claimed_at DATETIME NULL,
            done_at DATETIME NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_status (status, created_at),
            INDEX idx_iv (intervention_id, kind)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (Throwable $e) {}
}

/** Le réglage « Confier aussi au PC la correction et la relecture » (Réglages → Aide). */
function pc_jobs_setting(): bool
{
    return integration_setting('assist_pc_jobs', '1') === '1';
}

/** Dernier passage d'un script du PC à jour (capable de corriger et relire). */
function pc_jobs_touch_poll(): void { set_integration_setting('assist_pc_jobs_poll', (string)time()); }
function pc_jobs_last_poll(): ?int { $v = (int)integration_setting('assist_pc_jobs_poll', '0'); return $v > 0 ? $v : null; }

/** Le PC prend-il les travaux maintenant ? Clé créée, réglage coché, script à jour passé il y a moins de 2 min. */
function pc_jobs_enabled(): bool
{
    if (!pc_jobs_setting() || integration_secret('assist_token') === '') return false;
    $last = pc_jobs_last_poll();
    return $last !== null && time() - $last < 120;
}

/** Ajoute un travail (un seul en attente par intervention et par type : l'ancien est remplacé). */
function pc_job_create(string $kind, int $ivId, string $prompt, string $trigger = 'system'): int
{
    pc_jobs_table();
    db_execute("UPDATE pc_jobs SET status = 'remplace', done_at = NOW() WHERE intervention_id = ? AND kind = ? AND status IN ('en_attente', 'en_cours')", [$ivId, $kind]);
    db_execute('INSERT INTO pc_jobs (kind, intervention_id, trigger_by, prompt) VALUES (?,?,?,?)', [$kind, $ivId, mb_substr($trigger, 0, 20), $prompt]);
    return db_last_id();
}

/** Un travail en cours pour cette intervention ? (pour l'afficher au dispatcher) */
function pc_job_pending(int $ivId, string $kind): ?array
{
    pc_jobs_table();
    try { return db_fetch("SELECT * FROM pc_jobs WHERE intervention_id = ? AND kind = ? AND status IN ('en_attente', 'en_cours') ORDER BY id DESC LIMIT 1", [$ivId, $kind]); }
    catch (Throwable $e) { return null; }
}

/** Le PC prend le plus ancien travail (en attente, ou pris depuis plus de 3 min sans réponse). */
function pc_job_claim_next(): ?array
{
    pc_jobs_table();
    $r = db_fetch("SELECT * FROM pc_jobs WHERE status = 'en_attente' OR (status = 'en_cours' AND claimed_at < ?) ORDER BY id LIMIT 1",
        [date('Y-m-d H:i:s', time() - PC_JOB_DELAY)]);
    if (!$r) return null;
    db_execute("UPDATE pc_jobs SET status = 'en_cours', claimed_at = NOW() WHERE id = ? AND status IN ('en_attente', 'en_cours')", [(int)$r['id']]);
    return $r;
}

/**
 * Objet JSON contenu dans une réponse texte (Claude Code peut l'entourer de ```json … ``` ou
 * d'une phrase). Renvoie null si aucun objet lisible.
 */
function pc_json_extract(string $text): ?array
{
    $t = trim($text);
    if (preg_match('/```(?:json)?\s*(\{.*\})\s*```/su', $t, $m)) $t = $m[1];
    $a = strpos($t, '{'); $b = strrpos($t, '}');
    if ($a === false || $b === false || $b < $a) return null;
    $d = json_decode(substr($t, $a, $b - $a + 1), true);
    return is_array($d) ? $d : null;
}

/** Schéma attendu pour chaque type de travail. */
function pc_job_schema(string $kind): array
{
    return $kind === 'correction' ? correction_schema() : review_schema();
}

/**
 * Verrou : passe le travail à un état final une seule fois (le PC et le relais peuvent arriver
 * en même temps). Renvoie true si c'est nous qui l'avons pris.
 */
function pc_job_lock(int $id, string $status): bool
{
    $tok = bin2hex(random_bytes(8));
    db_execute("UPDATE pc_jobs SET status = ?, relay_token = ?, done_at = NOW() WHERE id = ? AND status IN ('en_attente', 'en_cours')", [$status, $tok, $id]);
    $cur = db_fetch('SELECT status, relay_token FROM pc_jobs WHERE id = ?', [$id]);
    return $cur && $cur['status'] === $status && $cur['relay_token'] === $tok;
}

/**
 * Réponse du PC. Renvoie 'ok', 'deja' (déjà traité ou remplacé) ou 'invalide' (dans ce cas le
 * relais par l'API est lancé tout de suite).
 */
function pc_job_finish(int $id, string $answer): string
{
    pc_jobs_table();
    $job = db_fetch('SELECT * FROM pc_jobs WHERE id = ?', [$id]);
    if (!$job || !in_array($job['status'], ['en_attente', 'en_cours'], true)) return 'deja';
    $data = pc_json_extract($answer);
    // Écart sans importance toléré : un taux de TVA écrit en nombre (10) au lieu de texte (« 10 »).
    if (is_array($data) && $job['kind'] === 'relecture' && (is_int($data['vat_rate'] ?? null) || is_float($data['vat_rate'] ?? null))) {
        $data['vat_rate'] = rtrim(rtrim(number_format((float)$data['vat_rate'], 1, '.', ''), '0'), '.');
    }
    $errors = $data === null ? ['pas de JSON'] : json_schema_errors($data, pc_job_schema((string)$job['kind']));
    if ($errors) {
        integration_log('assist', 'réponse du PC illisible, relais par l\'API', ['travail' => $id, 'erreurs' => array_slice($errors, 0, 3)]);
        pc_job_fallback($job, 'Réponse du PC illisible');
        return 'invalide';
    }
    if (!pc_job_lock($id, 'fait')) return 'deja';
    pc_job_apply($job, ['ok' => true, 'simulated' => false, 'data' => $data, 'model' => 'PC du bureau']);
    return 'ok';
}

/** Applique un résultat (du PC ou du relais) au bon traitement. */
function pc_job_apply(array $job, array $r): void
{
    $ivId = (int)$job['intervention_id'];
    $trigger = (string)$job['trigger_by'];
    if ($job['kind'] === 'correction') {
        if ($r['ok'] && !$r['simulated']) correction_apply($ivId, $r['data'], 'claude_pc', $trigger);
        return;
    }
    review_finish($ivId, $r, $trigger);
}

/** Le PC n'a pas répondu (ou mal) : le site fait le travail lui-même, comme avant. */
function pc_job_fallback(array $job, string $why = 'PC sans réponse'): bool
{
    if (!pc_job_lock((int)$job['id'], 'relais')) return false;
    db_execute('UPDATE pc_jobs SET result_note = ? WHERE id = ?', [mb_substr($why, 0, 255), (int)$job['id']]);
    @set_time_limit(180);
    $ivId = (int)$job['intervention_id'];
    if ($job['kind'] === 'correction') correction_run($ivId, (string)$job['trigger_by'], false);
    else review_run($ivId, (string)$job['trigger_by'], null, false);
    return true;
}

/** Travaux en attente depuis plus de 3 minutes : relais. Appelée par les tâches automatiques et les pages du dispatcher. */
function pc_jobs_process_overdue(int $max = 2): int
{
    pc_jobs_table();
    try {
        $rows = db_fetch_all("SELECT * FROM pc_jobs WHERE status IN ('en_attente', 'en_cours') AND created_at < ? ORDER BY id LIMIT ".max(1, min(10, $max)),
            [date('Y-m-d H:i:s', time() - PC_JOB_DELAY)]);
    } catch (Throwable $e) { return 0; }
    $n = 0;
    foreach ($rows as $job) {
        // Une relecture déjà faite par le dispatcher entre-temps : rien à relayer.
        if ($job['kind'] === 'relecture') {
            $st = (string)(db_fetch('SELECT status FROM interventions WHERE id = ?', [(int)$job['intervention_id']])['status'] ?? '');
            if (!in_array($st, ['rapport_rendu', 'a_revoir'], true)) { pc_job_lock((int)$job['id'], 'remplace'); continue; }
        }
        if (pc_job_fallback($job)) $n++;
    }
    return $n;
}

/** Les travaux du PC et l'aide en retard, pour les tâches automatiques. */
function automation_pc_jobs(): string
{
    $a = pc_jobs_process_overdue(3);
    $b = function_exists('assist_process_overdue') ? assist_process_overdue() : 0;
    return ($a + $b) ? $a.' travail(aux) et '.$b.' question(s) relayés' : 'rien';
}
