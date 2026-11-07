<?php
declare(strict_types=1);
/**
 * Affiche une photo envoyée par un client avec sa demande. Le dossier est fermé au
 * public : seuls passent ici un admin ayant accès aux demandes, un dispatcher actif et
 * le technicien à qui cette demande est confiée (pas les autres techniciens).
 */
require_once __DIR__.'/../includes/bootstrap.php';
boot_session();

$autorise = admin_logged_in() && admin_can_access('quotes.php');
if (!$autorise && !empty($_SESSION['disp_id'])) {
    try {
        $autorise = db_fetch("SELECT id FROM dispatchers WHERE id = ? AND status = 'actif'", [(int)$_SESSION['disp_id']]) !== null;
    } catch (Throwable $e) { $autorise = false; }
}
if (!$autorise) {
    $techId = (int)($_SESSION['tech_id'] ?? 0);
    if ($techId <= 0 && ($t = tech_remember_login())) $techId = (int)$t['id'];
    if ($techId > 0) {
        $qid = (int)($_GET['id'] ?? 0);
        try {
            $autorise = db_fetch(
                "SELECT t.id FROM technicians t
                 WHERE t.id = ? AND t.status = 'actif'
                   AND (EXISTS (SELECT 1 FROM quotes q WHERE q.id = ? AND q.technician_id = t.id)
                     OR EXISTS (SELECT 1 FROM interventions i WHERE i.quote_id = ? AND i.technician_id = t.id))",
                [$techId, $qid, $qid]
            ) !== null;
        } catch (Throwable $e) { $autorise = false; }
    }
}
if (!$autorise) { http_response_code(403); exit('Accès réservé.'); }

$q     = db_fetch('SELECT id, client_photos FROM quotes WHERE id = ?', [(int)($_GET['id'] ?? 0)]);
$liste = $q ? quote_client_photos($q) : [];
$nom   = $liste[(int)($_GET['n'] ?? -1)] ?? '';
$file  = $nom !== '' ? __DIR__.'/../storage/uploads/demandes/'.$nom : '';
if ($file === '' || !is_file($file)) { http_response_code(404); exit('Photo introuvable.'); }

header('Content-Type: '.['jpg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'][pathinfo($nom, PATHINFO_EXTENSION)]);
header('Content-Length: '.filesize($file));
header('Content-Disposition: inline; filename="photo-demande-'.(int)$q['id'].'.'.pathinfo($nom, PATHINFO_EXTENSION).'"');
header('Cache-Control: private, max-age=3600');
header('X-Content-Type-Options: nosniff');
readfile($file);
