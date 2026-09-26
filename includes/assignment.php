<?php
declare(strict_types=1);
/**
 * Assignation : suggestion du technicien le plus adapté, acceptation ou refus par le technicien.
 *
 * Le classement est calculé en PHP (règles simples, explicables) : métier, distance depuis
 * la dernière intervention de la journée ou le point de départ, charge du jour, créneau libre.
 * Le dispatcher choisit toujours : la suggestion n'assigne rien toute seule.
 */

/** Profil d'assignation des techniciens actifs (métiers, point de départ, horaires). */
function assign_technicians(): array
{
    try {
        return db_fetch_all("SELECT id, name, email, phone, status, skills, base_address, base_lat, base_lng,
            COALESCE(max_per_day, 6) AS max_per_day, COALESCE(work_start, '08:00:00') AS work_start, COALESCE(work_end, '18:00:00') AS work_end
            FROM technicians WHERE status = 'actif' ORDER BY name");
    } catch (Throwable $e) { return []; }
}

/** Métiers d'un technicien ; vide = polyvalent (profil non renseigné). */
function tech_skills(array $t): array
{
    return array_values(array_filter(array_map('trim', explode(',', (string)($t['skills'] ?? '')))));
}

function geo_valid($lat, $lng): bool
{
    return $lat !== null && $lng !== null && $lat !== '' && $lng !== '' && !((float)$lat == 0.0 && (float)$lng == 0.0);
}

/** Distance à vol d'oiseau en km. */
function geo_km(float $lat1, float $lng1, float $lat2, float $lng2): float
{
    $r = 6371.0;
    $dLat = deg2rad($lat2 - $lat1);
    $dLng = deg2rad($lng2 - $lng1);
    $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
    return $r * 2 * atan2(sqrt($a), sqrt(1 - $a));
}

function hm_to_min(string $t): int
{
    $p = explode(':', $t);
    return (int)($p[0] ?? 0) * 60 + (int)($p[1] ?? 0);
}

function min_to_hm(int $m): string
{
    return sprintf('%02d:%02d', intdiv($m, 60), $m % 60);
}

/**
 * Premier créneau libre d'un technicien un jour donné, pour une durée donnée.
 * $busy : [[début, fin], ...] en minutes. 30 min de trajet sont réservées entre deux interventions.
 */
function assign_free_slot(array $busy, int $dayStart, int $dayEnd, int $duration, int $notBefore = 0, ?int $wanted = null): ?int
{
    $travel = 30;
    usort($busy, static fn($a, $b) => $a[0] <=> $b[0]);
    $fits = static function (int $start) use ($busy, $duration, $travel, $dayEnd): bool {
        if ($start + $duration > $dayEnd) return false;
        foreach ($busy as [$s, $e]) {
            if ($start < $e + $travel && $start + $duration + $travel > $s) return false;
        }
        return true;
    };
    if ($wanted !== null) return $fits($wanted) ? $wanted : null;
    $start = max($dayStart, $notBefore);
    $start = (int)(ceil($start / 15) * 15);
    for (; $start + $duration <= $dayEnd; $start += 15) {
        if ($fits($start)) return $start;
    }
    return null;
}

/**
 * Classement des techniciens pour une intervention.
 * Retour : [['tech' => [...], 'score' => int, 'reason' => string, 'date' => 'Y-m-d', 'slot' => 'HH:MM'|null, 'km' => ?float, 'load' => int, 'skill' => bool], ...]
 */
function assign_suggestions(array $iv, int $limit = 3): array
{
    $today = date('Y-m-d');
    $fixed = !empty($iv['scheduled_date']) && (string)$iv['scheduled_date'] >= $today;
    // Date imposée sur la fiche, sinon aujourd'hui puis les jours ouvrés suivants.
    $dates = [$fixed ? (string)$iv['scheduled_date'] : $today];
    if (!$fixed) {
        for ($d = 1; count($dates) < 4 && $d < 10; $d++) {
            $ts = strtotime($today.' +'.$d.' day');
            if ((int)date('N', $ts) !== 7) $dates[] = date('Y-m-d', $ts);
        }
    }
    $duration = max(15, (int)($iv['duration_estimate'] ?? 60));
    $wanted = !empty($iv['scheduled_time']) ? hm_to_min((string)$iv['scheduled_time']) : null;
    $cat = (string)($iv['category'] ?? '');
    $catLabel = intervention_category_config()[$cat]['label'] ?? '';
    $hasGeo = geo_valid($iv['latitude'] ?? null, $iv['longitude'] ?? null);

    try {
        $in = implode(',', array_fill(0, count($dates), '?'));
        $jobsAll = db_fetch_all("SELECT id, technician_id, scheduled_date, scheduled_time, duration_estimate, latitude, longitude, status
            FROM interventions WHERE scheduled_date IN ($in) AND technician_id IS NOT NULL AND id <> ? AND status <> 'annulé'",
            array_merge($dates, [(int)($iv['id'] ?? 0)]));
    } catch (Throwable $e) { $jobsAll = []; }
    $byTech = [];
    foreach ($jobsAll as $j) $byTech[(int)$j['technician_id']][(string)$j['scheduled_date']][] = $j;

    // Techniciens ayant déjà refusé cette intervention : classés en dernier.
    $refused = [];
    if (!empty($iv['id'])) {
        try {
            foreach (db_fetch_all("SELECT actor_id FROM intervention_history WHERE intervention_id = ? AND actor_type = 'technicien' AND note LIKE 'Refus%'", [(int)$iv['id']]) as $r) $refused[(int)$r['actor_id']] = true;
        } catch (Throwable $e) {}
    }
    $dayWord = static fn(string $d) => $d === $today ? 'aujourd\'hui' : ($d === date('Y-m-d', strtotime($today.' +1 day')) ? 'demain' : 'le '.date('d/m', strtotime($d)));

    $out = [];
    foreach (assign_technicians() as $t) {
        $tid = (int)$t['id'];
        $skills = tech_skills($t);
        $skillOk = $skills === [] || $cat === '' || in_array($cat, $skills, true);
        $max = max(1, (int)$t['max_per_day']);

        // Premier jour avec un créneau libre (et sous la capacité journalière).
        $date = $dates[0]; $slot = null; $jobs = $byTech[$tid][$date] ?? [];
        foreach ($dates as $d) {
            $dj = $byTech[$tid][$d] ?? [];
            if (count($dj) >= $max) continue;
            $busy = [];
            foreach ($dj as $j) {
                if (empty($j['scheduled_time'])) continue;
                $st = hm_to_min((string)$j['scheduled_time']);
                $busy[] = [$st, $st + max(15, (int)$j['duration_estimate'])];
            }
            $notBefore = $d === $today ? (int)date('G') * 60 + (int)date('i') + 30 : 0;   // délai de route minimal
            $sl = assign_free_slot($busy, hm_to_min((string)$t['work_start']), hm_to_min((string)$t['work_end']), $duration, $notBefore, $wanted);
            if ($sl !== null) { $date = $d; $slot = $sl; $jobs = $dj; break; }
        }
        $load = count($jobs);

        // Position de départ : dernière intervention située avant le créneau, sinon point de départ.
        $from = null; $fromLabel = 'point de départ';
        $ref = $slot ?? $wanted ?? 24 * 60;
        $best = -1;
        foreach ($jobs as $j) {
            if (empty($j['scheduled_time']) || !geo_valid($j['latitude'], $j['longitude'])) continue;
            $st = hm_to_min((string)$j['scheduled_time']);
            if ($st <= $ref && $st > $best) { $best = $st; $from = [(float)$j['latitude'], (float)$j['longitude']]; $fromLabel = 'intervention de '.min_to_hm($st); }
        }
        if ($from === null && geo_valid($t['base_lat'], $t['base_lng'])) $from = [(float)$t['base_lat'], (float)$t['base_lng']];
        $km = ($hasGeo && $from) ? round(geo_km($from[0], $from[1], (float)$iv['latitude'], (float)$iv['longitude']), 1) : null;

        // Score sur 100 : métier 40, distance 30, charge 20, créneau 10 (plus tôt = mieux).
        $score = 0;
        $score += $skillOk ? ($skills === [] ? 25 : 40) : 0;
        $score += $km === null ? 12 : (int)max(0, round(30 - $km * 1.5));
        $score += (int)max(0, round(20 * (1 - $load / $max)));
        // Une urgence doit partir aujourd'hui : chaque jour de report pèse beaucoup plus lourd.
        $dayPenalty = !empty($iv['urgency']) ? 25 : 4;
        $score += $slot === null ? 0 : 10 - $dayPenalty * (int)array_search($date, $dates, true);
        if (isset($refused[$tid])) $score -= 60;

        $parts = [];
        $parts[] = !$skillOk ? 'hors métier ('.($catLabel ?: $cat).')' : ($skills === [] ? 'métiers non renseignés' : ($catLabel !== '' ? $catLabel : 'métier ok'));
        $parts[] = $km !== null ? number_format($km, 1, ',', '').' km depuis '.$fromLabel : 'distance inconnue';
        $parts[] = ($load === 0 ? 'aucune intervention' : $load.' intervention'.($load > 1 ? 's' : '')).' '.$dayWord($date);
        $parts[] = $slot !== null ? ($wanted !== null ? 'disponible à '.min_to_hm($slot) : 'libre '.$dayWord($date).' dès '.min_to_hm($slot))
                                  : ($wanted !== null ? 'pas libre à '.min_to_hm($wanted) : 'pas de créneau libre');
        if (isset($refused[$tid])) $parts[] = 'a déjà refusé';

        $out[] = ['tech' => $t, 'score' => $score, 'reason' => implode(' · ', $parts), 'date' => $date,
                  'slot' => $slot !== null ? min_to_hm($slot) : null, 'km' => $km, 'load' => $load, 'skill' => $skillOk];
    }
    usort($out, static fn($a, $b) => $b['score'] <=> $a['score']);
    return array_slice($out, 0, $limit);
}

/* ─── Réponse du technicien ─────────────────────────────── */
function assign_refusal_reasons(): array
{
    return ['Indisponible sur ce créneau', 'Trop loin', 'Hors de mes compétences', 'Matériel manquant', 'Autre'];
}

/** Le technicien accepte l'intervention. */
function assign_accept(array $iv, array $tech): void
{
    db_execute("UPDATE interventions SET tech_response = 'acceptee', tech_response_at = NOW() WHERE id = ? AND technician_id = ?", [(int)$iv['id'], (int)$tech['id']]);
    log_intervention_history((int)$iv['id'], (string)$iv['status'], (string)$iv['status'], 'technicien', (int)$tech['id'], (string)$tech['name'], 'Intervention acceptée');
}

/** Le technicien refuse : la fiche repart « À assigner » et le dispatcher est prévenu. */
function assign_refuse(array $iv, array $tech, string $reason): void
{
    $reason = mb_substr(trim($reason), 0, 250);
    db_execute("UPDATE interventions SET technician_id = NULL, status = 'a_assigner', tech_response = 'refusee', tech_response_at = NOW(), tech_refusal_reason = ?
        WHERE id = ? AND technician_id = ?", [$reason, (int)$iv['id'], (int)$tech['id']]);
    log_intervention_history((int)$iv['id'], (string)$iv['status'], 'a_assigner', 'technicien', (int)$tech['id'], (string)$tech['name'], 'Refus : '.$reason);
    notify_dispatcher_refusal((int)$iv['id'], $tech, $reason);
}

/** Prévient le dispatcher d'un refus (e-mail au dispatcher de la fiche, sinon à l'adresse de l'entreprise). */
function notify_dispatcher_refusal(int $ivId, array $tech, string $reason): void
{
    try {
        $iv = get_intervention_by_id($ivId);
        if (!$iv) return;
        $s = notif_iv_summary($iv);
        $to = '';
        if (!empty($iv['dispatcher_id'])) {
            $d = db_fetch('SELECT email FROM dispatchers WHERE id = ?', [(int)$iv['dispatcher_id']]);
            $to = (string)($d['email'] ?? '');
        }
        if ($to === '') $to = company_email();
        if ($to === '') return;
        $h = static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
        notif_mail($to, 'Intervention refusée — '.$s['client'], 'Intervention refusée par '.$tech['name'], [
            '<b>'.$h($s['client']).'</b>'.($s['city'] ? ' — '.$h($s['city']) : '').' · '.$h($s['what']),
            'Motif : '.$h($reason),
            'La fiche est de nouveau « À assigner ».',
        ], notif_abs_url('dispatcher/intervention_view.php?id='.$ivId), 'Réassigner');
    } catch (Throwable $e) { error_log('[EMAE notif] refus #'.$ivId.' : '.$e->getMessage()); }
}
