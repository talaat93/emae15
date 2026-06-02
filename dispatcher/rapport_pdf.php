<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/pdf_generator.php';

// Allow dispatcher, admin (dispatcher with role=admin), or technician
$actor = null;
if (!empty($_SESSION['disp_id'])) {
    try {
        $actor = db_fetch("SELECT * FROM dispatchers WHERE id = ? AND status = 'actif'", [(int)$_SESSION['disp_id']]);
    } catch (Throwable $e) {}
} elseif (!empty($_SESSION['tech_id'])) {
    try {
        $actor = db_fetch("SELECT * FROM technicians WHERE id = ? AND status = 'actif'", [(int)$_SESSION['tech_id']]);
    } catch (Throwable $e) {}
}

if (!$actor) {
    // Try dispatcher login first, then tech
    if (!empty($_SESSION['disp_id'])) {
        redirect_to('dispatcher/login.php');
    }
    redirect_to('tech/login.php');
}

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    redirect_to('dispatcher/interventions.php');
}

// Fetch intervention with all related data
$iv = null;
try {
    $iv = db_fetch(
        "SELECT i.*, c.lastname, c.firstname, c.phone AS client_phone, c.address AS client_address,
                c.city AS client_city, c.postal_code AS client_postal,
                c.floor, c.digicode, c.access_info,
                t.name AS tech_name, t.phone AS tech_phone, t.email AS tech_email,
                d.name AS disp_name
         FROM interventions i
         LEFT JOIN clients c ON c.id = i.client_id
         LEFT JOIN technicians t ON t.id = i.technician_id
         LEFT JOIN dispatchers d ON d.id = i.dispatcher_id
         WHERE i.id = ?",
        [$id]
    );
} catch (Throwable $e) {}

if (!$iv) {
    if (!empty($_SESSION['disp_id'])) {
        flash('error', 'Intervention introuvable.');
        redirect_to('dispatcher/interventions.php');
    }
    redirect_to('tech/login.php');
}

// Decode photos
$photos = [];
if (!empty($iv['tech_photos'])) {
    $p = json_decode((string)$iv['tech_photos'], true);
    if (is_array($p)) $photos = $p;
}

// Decode materials
$materials = [];
if (!empty($iv['tech_materials_used'])) {
    $m = json_decode((string)$iv['tech_materials_used'], true);
    if (is_array($m)) $materials = $m;
}

// Fetch intervention history
$history = [];
try {
    $history = db_fetch_all(
        "SELECT * FROM intervention_history WHERE intervention_id = ? ORDER BY created_at ASC",
        [$id]
    );
} catch (Throwable $e) {}

// Merge address fields (the query aliases them, re-map to what the generator expects)
$iv['address']      = $iv['client_address'] ?? $iv['address'] ?? '';
$iv['postal_code']  = $iv['client_postal']  ?? $iv['postal_code'] ?? '';

// Generate PDF
$generator = new InterventionPdfGenerator();
$pdfBytes  = $generator->generateInterventionPDF($iv, [], $photos, $materials, $history);

$ref  = $iv['ref'] ?? 'rapport';
$safe = preg_replace('/[^A-Za-z0-9\-_]/', '-', $ref);

// If mPDF is not available $pdfBytes contains HTML — serve as HTML
if (!class_exists('\Mpdf\Mpdf')) {
    header('Content-Type: text/html; charset=utf-8');
    echo $pdfBytes;
    exit;
}

// Send PDF for download
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="rapport-' . $safe . '.pdf"');
header('Content-Length: ' . strlen($pdfBytes));
header('Cache-Control: private, max-age=0, must-revalidate');
header('Pragma: public');
echo $pdfBytes;
exit;
