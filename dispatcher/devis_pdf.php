<?php
declare(strict_types=1);
/** Téléchargement du PDF d'un devis (original ou signé) : dispatcher connecté uniquement. */
require_once __DIR__.'/../includes/bootstrap.php';
require_dispatcher_auth();

$d = devis_by_id((int)($_GET['id'] ?? 0));
$name = $d ? (string)(!empty($_GET['signed']) ? $d['signed_pdf_path'] : $d['pdf_path']) : '';
if ($d && $name === '' && empty($_GET['signed'])) $name = basename(devis_pdf($d));
$file = $name !== '' ? realpath(__DIR__.'/../storage/uploads/devis/'.basename($name)) : false;
$base = realpath(__DIR__.'/../storage/uploads/devis');
if (!$d || !$file || !$base || !str_starts_with($file, $base) || !is_file($file)) { http_response_code(404); exit('PDF introuvable.'); }
header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="'.$d['number'].(!empty($_GET['signed']) ? '-signe' : '').'.pdf"');
header('X-Content-Type-Options: nosniff');
readfile($file);
