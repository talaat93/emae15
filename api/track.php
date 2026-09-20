<?php
declare(strict_types=1);
/**
 * Comptage d'un évènement public, par exemple un clic sur « Appeler ».
 * Appelé par le navigateur en tâche de fond ; ne renvoie aucun contenu.
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/stats.php';

header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-store');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { http_response_code(405); exit; }

// La liste des évènements acceptés est portée par stats_track_event() ;
// on la consulte ici seulement pour répondre proprement.
$event = (string)($_POST['e'] ?? '');
if (!in_array($event, stats_allowed_events(), true)) { http_response_code(400); exit; }

$zone = preg_replace('/[^a-z0-9-]/', '', (string)($_POST['z'] ?? '')) ?? '';

// stats_track_event() écarte déjà robots et administrateurs connectés.
stats_track_event($event, mb_substr($zone, 0, 80));

http_response_code(204);
