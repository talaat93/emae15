<?php
declare(strict_types=1);
require_once __DIR__.'/../includes/bootstrap.php';
boot_session();

// Auth check — allow admin, dispatcher, or tech sessions
if (empty($_SESSION['disp_id']) && empty($_SESSION['admin_id']) && empty($_SESSION['tech_id'])) {
    http_response_code(401);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'Non autorisé']);
    exit;
}

header('Content-Type: application/json; charset=utf-8');
$action = trim((string)($_GET['action'] ?? $_POST['action'] ?? ''));

try {
    switch ($action) {

        // ── Recherche clients ──────────────────────────────────
        case 'search_clients':
            $q = trim((string)($_GET['q'] ?? ''));
            $rows = search_clients($q);
            $out = [];
            foreach ($rows as $c) {
                $out[] = [
                    'id'          => (int)$c['id'],
                    'name'        => trim($c['lastname'].' '.($c['firstname'] ?? '')),
                    'phone'       => $c['phone'] ?? '',
                    'city'        => $c['city'] ?? '',
                    'address'     => $c['address'] ?? '',
                    'postal_code' => $c['postal_code'] ?? '',
                ];
            }
            echo json_encode($out);
            break;

        // ── Événements calendrier ──────────────────────────────
        case 'calendar_events':
            $start = $_GET['start'] ?? date('Y-m-01');
            $end   = $_GET['end']   ?? date('Y-m-t');
            $start = substr($start, 0, 10);
            $end   = substr($end,   0, 10);
            try {
                $rows = db_fetch_all(
                    "SELECT i.id, i.ref, i.scheduled_date, i.scheduled_time, i.duration_estimate,
                            i.urgency, i.status, i.category, i.type_label,
                            c.lastname, c.firstname, t.name AS tech_name
                     FROM interventions i
                     LEFT JOIN clients c ON c.id = i.client_id
                     LEFT JOIN technicians t ON t.id = i.technician_id
                     WHERE i.scheduled_date BETWEEN ? AND ?
                     ORDER BY i.scheduled_date, i.scheduled_time",
                    [$start, $end]
                );
            } catch (Throwable $e) { $rows = []; }
            $catCfg = intervention_category_config();
            $stCfg  = intervention_status_config();
            $events = [];
            foreach ($rows as $r) {
                $cat   = $r['category'] ?? '';
                $color = $catCfg[$cat]['color'] ?? '#8fa0c4';
                if ((int)($r['urgency'] ?? 0)) $color = '#ef4444';
                $date  = $r['scheduled_date'] ?? '';
                $time  = $r['scheduled_time'] ? substr($r['scheduled_time'], 0, 5) : '08:00';
                $dur   = max(30, (int)($r['duration_estimate'] ?? 60));
                $start_dt = $date . 'T' . $time . ':00';
                [$hh, $mm] = explode(':', $time);
                $endMin = (int)$hh * 60 + (int)$mm + $dur;
                $end_dt = $date . 'T' . sprintf('%02d', intdiv($endMin, 60)) . ':' . sprintf('%02d', $endMin % 60) . ':00';
                $clientName = trim(($r['firstname'] ?? '') . ' ' . ($r['lastname'] ?? ''));
                $events[] = [
                    'id'              => $r['id'],
                    'title'           => ($clientName ?: 'Client') . ($r['type_label'] ? ' – ' . $r['type_label'] : ''),
                    'start'           => $start_dt,
                    'end'             => $end_dt,
                    'backgroundColor' => $color . 'cc',
                    'borderColor'     => $color,
                    'textColor'       => '#fff',
                    'extendedProps'   => [
                        'status'     => $r['status'],
                        'category'   => $cat,
                        'technician' => $r['tech_name'] ?? '',
                        'urgency'    => (bool)$r['urgency'],
                        'client'     => $clientName,
                        'ref'        => $r['ref'] ?? '',
                    ],
                ];
            }
            echo json_encode($events);
            break;

        // ── Déplacer un événement (drag & drop) ───────────────
        case 'drag_event':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') { echo json_encode(['error'=>'POST requis']); break; }
            $id   = (int)($_POST['id'] ?? 0);
            $date = trim((string)($_POST['new_date'] ?? ''));
            $time = trim((string)($_POST['new_time'] ?? ''));
            if ($id <= 0) { echo json_encode(['error'=>'ID invalide']); break; }
            $upd = [];
            if ($date !== '') $upd['scheduled_date'] = $date;
            if ($time !== '') $upd['scheduled_time'] = $time;
            if (!empty($upd)) update_intervention($id, $upd);
            $actorId   = (int)($_SESSION['disp_id'] ?? $_SESSION['admin_id'] ?? 0);
            $actorName = (string)($_SESSION['disp_name'] ?? 'Admin');
            log_intervention_history($id, null, 'replanifié', 'dispatcher', $actorId, $actorName, "Déplacé au $date $time");
            echo json_encode(['success' => true]);
            break;

        // ── Mise à jour statut ─────────────────────────────────
        case 'update_status':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') { echo json_encode(['error'=>'POST requis']); break; }
            $id        = (int)($_POST['id'] ?? 0);
            $newStatus = trim((string)($_POST['status'] ?? ''));
            $allowed   = array_keys(intervention_status_config());
            if ($id <= 0 || !in_array($newStatus, $allowed, true)) {
                echo json_encode(['error' => 'Paramètres invalides']); break;
            }
            $iv = get_intervention_by_id($id);
            if (!$iv) { echo json_encode(['error' => 'Intervention introuvable']); break; }
            $oldStatus = $iv['status'] ?? '';
            $upd = ['status' => $newStatus];
            if ($newStatus === 'terminé' && empty($iv['tech_completed_at'])) {
                $upd['tech_completed_at'] = date('Y-m-d H:i:s');
            }
            update_intervention($id, $upd);
            $actorId   = (int)($_SESSION['disp_id'] ?? $_SESSION['tech_id'] ?? $_SESSION['admin_id'] ?? 0);
            $actorName = (string)($_SESSION['disp_name'] ?? $_SESSION['tech_name'] ?? 'Système');
            log_intervention_history($id, $oldStatus, $newStatus, 'dispatcher', $actorId, $actorName);
            echo json_encode(['success' => true, 'badge' => intervention_status_badge($newStatus)]);
            break;

        // ── Assigner technicien ────────────────────────────────
        case 'assign_tech':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') { echo json_encode(['error'=>'POST requis']); break; }
            $id     = (int)($_POST['id'] ?? 0);
            $techId = (int)($_POST['technician_id'] ?? 0);
            if ($id <= 0) { echo json_encode(['error'=>'ID invalide']); break; }
            $upd = ['technician_id' => $techId ?: null];
            if ($techId > 0) {
                $iv = get_intervention_by_id($id);
                if ($iv && in_array($iv['status'], ['nouveau','confirmé'], true)) {
                    $upd['status'] = 'assigné';
                }
            }
            update_intervention($id, $upd);
            $actorId   = (int)($_SESSION['disp_id'] ?? $_SESSION['admin_id'] ?? 0);
            $actorName = (string)($_SESSION['disp_name'] ?? 'Admin');
            // SMS to tech
            if ($techId > 0) {
                try {
                    $t = db_fetch("SELECT name, phone FROM technicians WHERE id=?", [$techId]);
                    if ($t && !empty($t['phone'])) {
                        $iv = get_intervention_by_id($id);
                        $msg = "EMAE — Nouvelle mission assignée : ".($iv['ref']??'INT #'.$id)
                             ." — ".trim(($iv['firstname']??'').'' .($iv['lastname']??''))
                             ." — ".($iv['client_city']??'')
                             ." — ".($iv['scheduled_date']??'');
                        send_sms_dispatcher($t['phone'], $msg);
                    }
                } catch (Throwable $e) {}
            }
            log_intervention_history($id, null, 'assigné', 'dispatcher', $actorId, $actorName, "Tech ID: $techId");
            echo json_encode(['success' => true]);
            break;

        // ── Marqueurs carte ────────────────────────────────────
        case 'map_markers':
            try {
                $rows = db_fetch_all(
                    "SELECT i.id, i.ref, i.status, i.category, i.urgency, i.latitude, i.longitude,
                            c.lastname, c.firstname, c.address, c.city AS client_city,
                            t.name AS tech_name
                     FROM interventions i
                     LEFT JOIN clients c ON c.id = i.client_id
                     LEFT JOIN technicians t ON t.id = i.technician_id
                     WHERE i.latitude IS NOT NULL AND i.longitude IS NOT NULL
                     AND i.status NOT IN ('annulé','payé')
                     ORDER BY i.urgency DESC, i.created_at DESC"
                );
            } catch (Throwable $e) { $rows = []; }
            $out = [];
            foreach ($rows as $r) {
                $out[] = [
                    'id'        => (int)$r['id'],
                    'ref'       => $r['ref'] ?? 'INT #'.$r['id'],
                    'lat'       => (float)$r['latitude'],
                    'lng'       => (float)$r['longitude'],
                    'client'    => trim(($r['firstname']??'').' '.($r['lastname']??'')),
                    'address'   => $r['address'] ?? '',
                    'city'      => $r['client_city'] ?? '',
                    'status'    => $r['status'] ?? '',
                    'category'  => $r['category'] ?? '',
                    'tech_name' => $r['tech_name'] ?? '',
                    'urgency'   => (bool)$r['urgency'],
                ];
            }
            echo json_encode($out);
            break;

        // ── Géocodage ──────────────────────────────────────────
        case 'geocode':
            $address = trim((string)($_GET['address'] ?? ''));
            $city    = trim((string)($_GET['city']    ?? ''));
            $postal  = trim((string)($_GET['postal']  ?? ''));
            $result  = geocode_address($address, $city, $postal);
            echo json_encode($result);
            break;

        // ── KPIs ───────────────────────────────────────────────
        case 'kpis':
            echo json_encode(dispatcher_kpis());
            break;

        default:
            http_response_code(400);
            echo json_encode(['error' => 'Action inconnue: ' . $action]);
            break;
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
