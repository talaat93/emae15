<?php
declare(strict_types=1);
/**
 * Devis et signature électronique à distance avec Yousign (API v3).
 *   https://api.yousign.app/v3 (production) — https://api-sandbox.yousign.app/v3 (bac à sable)
 * Authentification : en-tête « Authorization: Bearer <clé API> ».
 * Parcours : création de la demande (delivery_mode email) → ajout du PDF (signable_document)
 *            → ajout du signataire avec son champ de signature → activation.
 * Webhook : signature HMAC-SHA256 du corps brut vérifiée avec le secret du webhook, puis le statut
 * est toujours relu auprès de Yousign avant toute mise à jour (le contenu reçu n'est jamais cru tel quel).
 *
 * Sans clé API : mode SIMULATION (aucun envoi, signature simulable depuis la fiche).
 */

require_once __DIR__.'/simple_pdf.php';

const YOUSIGN_PROD    = 'https://api.yousign.app/v3';
const YOUSIGN_SANDBOX = 'https://api-sandbox.yousign.app/v3';

function yousign_key(): string { return integration_secret('yousign_api_key'); }
function yousign_simulated(): bool { return yousign_key() === ''; }
function yousign_base(): string
{
    if (!empty(app_config()['yousign_base'])) return (string)app_config()['yousign_base'];   // tests locaux uniquement
    return integration_setting('yousign_sandbox', '1') === '1' ? YOUSIGN_SANDBOX : YOUSIGN_PROD;
}
function yousign_threshold(): float { return (float)str_replace(',', '.', integration_setting('yousign_quote_threshold', '500')); }

/** Requête JSON (ou multipart si $multipart est fourni). Réessais sur 429 / 5xx. */
function yousign_request(string $method, string $path, ?array $body = null, ?array $multipart = null, int $timeout = 30, bool $raw = false): array
{
    $url = yousign_base().$path;
    for ($attempt = 1; $attempt <= 3; $attempt++) {
        $t0 = microtime(true);
        $ch = curl_init($url);
        $headers = ['Authorization: Bearer '.yousign_key(), 'Accept: '.($raw ? 'application/pdf, application/zip' : 'application/json')];
        $opts = [CURLOPT_CUSTOMREQUEST => $method, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => $timeout, CURLOPT_CONNECTTIMEOUT => 10, CURLOPT_HEADER => true];
        if ($multipart !== null) {
            $opts[CURLOPT_POSTFIELDS] = $multipart;           // curl construit le multipart/form-data
        } elseif ($body !== null) {
            $headers[] = 'Content-Type: application/json';
            $opts[CURLOPT_POSTFIELDS] = $body === [] ? '{}' : json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        $opts[CURLOPT_HTTPHEADER] = $headers;
        curl_setopt_array($ch, $opts);
        $resp = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $hsize = (int)curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $err = $resp === false ? curl_error($ch) : null;
        curl_close($ch);
        $respBody = $resp === false ? '' : substr((string)$resp, $hsize);
        $ms = (int)round((microtime(true) - $t0) * 1000);
        if (($status === 429 || $status >= 500) && $attempt < 3) { sleep($attempt * 2); continue; }
        break;
    }
    $ok = $err === null && $status >= 200 && $status < 300;
    $json = $raw ? null : json_decode($respBody, true);
    integration_log('yousign', $method.' '.preg_replace('/[0-9a-f-]{36}/', '{id}', $path).' : '.($ok ? 'ok' : 'échec'), ['http' => $status, 'ms' => $ms]);
    if ($ok) return ['ok' => true, 'status' => $status, 'data' => $json ?? [], 'raw' => $raw ? $respBody : null, 'error' => null];
    $detail = $err ?? (string)($json['detail'] ?? $json['message'] ?? $json['title'] ?? '');
    $human = match (true) {
        $status === 401 => 'Clé API Yousign refusée : vérifiez-la (et le choix bac à sable / production) dans les réglages.',
        $status === 403 => 'La clé Yousign n\'a pas les droits nécessaires.',
        $status === 404 => 'Demande de signature introuvable chez Yousign.',
        $status === 400 || $status === 422 => 'Yousign a refusé les données : '.mb_substr($detail, 0, 200),
        $status === 429 => 'Trop de requêtes vers Yousign : réessayez dans une minute.',
        $err !== null => 'Connexion à Yousign impossible (réseau ou délai dépassé).',
        default => 'Yousign est momentanément indisponible (HTTP '.$status.').',
    };
    return ['ok' => false, 'status' => $status, 'data' => $json, 'raw' => null, 'error' => $human];
}

function yousign_test_connection(): array
{
    if (yousign_simulated()) return ['ok' => false, 'message' => 'Aucune clé : Yousign fonctionne en mode SIMULATION.'];
    $r = yousign_request('GET', '/signature_requests?limit=1');
    return $r['ok'] ? ['ok' => true, 'message' => 'Connexion réussie ('.(yousign_base() === YOUSIGN_PROD ? 'production' : 'bac à sable').').']
                    : ['ok' => false, 'message' => (string)$r['error']];
}

/* ─── Devis ─────────────────────────────────────────────── */
function devis_table(): void
{
    static $done = false;
    if ($done) return;
    $done = true;
    try {
        db_execute("CREATE TABLE IF NOT EXISTS devis (
            id INT AUTO_INCREMENT PRIMARY KEY,
            intervention_id INT NULL,
            client_id INT NULL,
            number VARCHAR(40) NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'brouillon',
            title VARCHAR(255) NULL,
            description TEXT NULL,
            line_items MEDIUMTEXT NULL,
            vat_rate DECIMAL(5,2) NULL,
            total_ht DECIMAL(12,2) NOT NULL DEFAULT 0,
            total_tva DECIMAL(12,2) NOT NULL DEFAULT 0,
            total_ttc DECIMAL(12,2) NOT NULL DEFAULT 0,
            valid_until DATE NULL,
            pdf_path VARCHAR(255) NULL,
            signed_pdf_path VARCHAR(255) NULL,
            yousign_request_id VARCHAR(60) NULL,
            yousign_signer_id VARCHAR(60) NULL,
            yousign_status VARCHAR(30) NULL,
            signer_email VARCHAR(190) NULL,
            sent_at DATETIME NULL,
            signed_at DATETIME NULL,
            error VARCHAR(500) NULL,
            created_by INT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NULL,
            INDEX idx_iv (intervention_id),
            UNIQUE KEY uq_yousign (yousign_request_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (Throwable $e) { integration_log('yousign', 'table devis : '.$e->getMessage()); }
}

function devis_status_config(): array
{
    return [
        'brouillon' => ['Brouillon', '#475569', '#f1f5f9'],
        'envoye'    => ['Envoyé pour signature', '#1d4ed8', '#dbeafe'],
        'signe'     => ['Signé', '#15803d', '#dcfce7'],
        'refuse'    => ['Refusé', '#b91c1c', '#fee2e2'],
        'expire'    => ['Expiré', '#92400e', '#fef3c7'],
        'annule'    => ['Annulé', '#64748b', '#f1f5f9'],
    ];
}

function devis_badge(string $status): string
{
    [$l, $fg, $bg] = devis_status_config()[$status] ?? [$status, '#334155', '#f1f5f9'];
    return '<span style="display:inline-block;padding:.12rem .55rem;border-radius:99px;font-size:.74rem;font-weight:600;color:'.$fg.';background:'.$bg.';">'.htmlspecialchars($l, ENT_QUOTES, 'UTF-8').'</span>';
}

function devis_for_intervention(int $ivId): array
{
    devis_table();
    try { $rows = db_fetch_all('SELECT * FROM devis WHERE intervention_id = ? ORDER BY id DESC', [$ivId]); } catch (Throwable $e) { return []; }
    foreach ($rows as &$r) $r['lines'] = json_decode((string)$r['line_items'], true) ?: [];
    return $rows;
}

function devis_by_id(int $id): ?array
{
    devis_table();
    $r = db_fetch('SELECT * FROM devis WHERE id = ?', [$id]);
    if (!$r) return null;
    $r['lines'] = json_decode((string)$r['line_items'], true) ?: [];
    return $r;
}

/** Crée ou met à jour un devis brouillon ; montants recalculés à partir de la grille. */
function devis_save(?int $devisId, int $ivId, array $lines, float $vatRate, string $title, string $description, array $actor): array
{
    devis_table();
    $iv = db_fetch('SELECT * FROM interventions WHERE id = ?', [$ivId]);
    if (!$iv) return ['ok' => false, 'error' => 'Intervention introuvable.', 'id' => null];
    $calc = pricing_compute($lines, $vatRate);
    if (!$calc['lines']) return ['ok' => false, 'error' => 'Le devis doit contenir au moins une ligne.', 'id' => null];
    $vals = [mb_substr(trim($title), 0, 255), mb_substr(trim($description), 0, 4000), json_encode($calc['lines'], JSON_UNESCAPED_UNICODE),
             $calc['vat_rate'], $calc['total_ht'], $calc['total_tva'], $calc['total_ttc']];
    if ($devisId) {
        $d = devis_by_id($devisId);
        if (!$d || $d['status'] !== 'brouillon') return ['ok' => false, 'error' => 'Seul un devis brouillon peut être modifié.', 'id' => null];
        db_execute('UPDATE devis SET title = ?, description = ?, line_items = ?, vat_rate = ?, total_ht = ?, total_tva = ?, total_ttc = ?, pdf_path = NULL, updated_at = NOW() WHERE id = ?', array_merge($vals, [$devisId]));
    } else {
        db_execute('INSERT INTO devis (title, description, line_items, vat_rate, total_ht, total_tva, total_ttc, intervention_id, client_id, valid_until, created_by, updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,NOW())',
            array_merge($vals, [$ivId, $iv['client_id'] ?: null, date('Y-m-d', strtotime('+30 days')), (int)$actor['id']]));
        $devisId = db_last_id();
        db_execute('UPDATE devis SET number = ? WHERE id = ?', ['DEV-'.date('Y').'-'.str_pad((string)$devisId, 5, '0', STR_PAD_LEFT), $devisId]);
    }
    integration_log('audit', 'devis enregistré #'.$devisId, ['intervention' => $ivId, 'ttc' => $calc['total_ttc'], 'dispatcher' => (int)$actor['id']]);
    return ['ok' => true, 'error' => null, 'id' => $devisId, 'warnings' => $calc['warnings']];
}

/** Produit le PDF du devis (storage/uploads/devis, protégé) ; renvoie le chemin absolu. */
function devis_pdf(array $d): string
{
    $iv = !empty($d['intervention_id']) ? get_intervention_by_id((int)$d['intervention_id']) : null;
    $c = !empty($d['client_id']) ? get_client_by_id((int)$d['client_id']) : null;
    $pdf = new SimplePdf();
    $pdf->addPage();
    $navy = [0.09, 0.14, 0.25]; $grey = [0.36, 0.40, 0.47]; $orange = [0.94, 0.48, 0.11];
    $pdf->rect(0, 0, SimplePdf::W, 6, $orange);
    $pdf->text(40, 50, company_name(), 18, true, $navy);
    $y = 66;
    foreach (array_filter([setting('company_address', ''), trim(setting('company_postal_code', '').' '.setting('company_city', '')), company_phone_safe(), company_email(), setting('company_siret', '') ? 'SIRET '.setting('company_siret', '') : '']) as $l) {
        $pdf->text(40, $y, (string)$l, 9, false, $grey); $y += 12;
    }
    $pdf->text(555, 50, 'DEVIS', 20, true, $orange, 'R');
    $pdf->text(555, 68, 'N° '.$d['number'], 10, true, $navy, 'R');
    $pdf->text(555, 82, 'Date : '.date('d/m/Y', strtotime((string)$d['created_at'])), 9, false, $grey, 'R');
    $pdf->text(555, 94, 'Valable jusqu\'au '.date('d/m/Y', strtotime((string)($d['valid_until'] ?: '+30 days'))), 9, false, $grey, 'R');
    // Client
    $pdf->rect(330, 112, 225, 78, [0.97, 0.98, 0.99], [0.88, 0.90, 0.93]);
    $cy = 128;
    $pdf->text(340, $cy, 'Client', 8, true, $grey); $cy += 14;
    foreach (array_filter([trim(($c['firstname'] ?? '').' '.($c['lastname'] ?? '')), (string)($c['address'] ?? ''), trim(($c['postal_code'] ?? '').' '.($c['city'] ?? '')), (string)($c['phone'] ?? '')]) as $l) {
        $pdf->text(340, $cy, $l, 9.5, $cy === 142, $navy); $cy += 12;
    }
    $y = 215;
    $pdf->text(40, $y, (string)$d['title'], 12, true, $navy); $y += 18;
    if (trim((string)$d['description']) !== '') $y = $pdf->paragraph(40, $y, 515, (string)$d['description'], 9.5, 1.4, $grey) + 6;
    if ($iv) { $pdf->text(40, $y, 'Référence intervention : '.$iv['ref'], 8.5, false, $grey); $y += 16; }
    // Lignes
    $pdf->rect(40, $y, 515, 20, $navy);
    $pdf->text(48, $y + 13.5, 'Désignation', 9, true, [1, 1, 1]);
    $pdf->text(380, $y + 13.5, 'Qté', 9, true, [1, 1, 1], 'R');
    $pdf->text(465, $y + 13.5, 'P.U. HT', 9, true, [1, 1, 1], 'R');
    $pdf->text(548, $y + 13.5, 'Total HT', 9, true, [1, 1, 1], 'R');
    $y += 20;
    foreach ($d['lines'] as $l) {
        if ($y > 690) { $pdf->addPage(); $y = 50; }
        $isPct = ($l['unit'] ?? '') === 'pourcent';
        $label = (string)$l['label'];
        $yy = $pdf->paragraph(48, $y + 14, 290, $label, 9, 1.3, $navy);
        $pdf->text(380, $y + 14, $isPct ? '' : rtrim(rtrim(number_format((float)$l['qty'], 2, ',', ''), '0'), ','), 9, false, $navy, 'R');
        $pdf->text(465, $y + 14, $isPct ? '' : money_fr((float)$l['unit_price_ht']), 9, false, $navy, 'R');
        $pdf->text(548, $y + 14, money_fr((float)$l['total_ht']), 9, false, $navy, 'R');
        $y = max($y + 22, $yy + 4);
        $pdf->line(40, $y, 555, $y);
    }
    $y += 12;
    foreach ([['Total HT', money_fr((float)$d['total_ht']), false], ['TVA '.rtrim(rtrim(number_format((float)$d['vat_rate'], 1, ',', ''), '0'), ',').' %', money_fr((float)$d['total_tva']), false], ['Total TTC', money_fr((float)$d['total_ttc']), true]] as [$k, $v, $b]) {
        $pdf->text(465, $y, $k, $b ? 11 : 9.5, $b, $navy, 'R');
        $pdf->text(548, $y, $v, $b ? 11 : 9.5, $b, $b ? $orange : $navy, 'R');
        $y += $b ? 18 : 14;
    }
    if ((float)$d['vat_rate'] === 10.0) {
        $pdf->paragraph(40, $y + 4, 515, 'TVA à taux réduit (10 %) : travaux d\'amélioration, de transformation, d\'aménagement ou d\'entretien sur un local à usage d\'habitation achevé depuis plus de deux ans ; attestation simplifiée à fournir par le client.', 8, 1.35, $grey);
    }
    // Zone de signature (le champ Yousign est positionné dans ce cadre, page 1)
    if ($y > 640) { $pdf->addPage(); }
    $pdf->rect(330, 690, 225, 110, null, [0.80, 0.82, 0.86]);
    $pdf->text(340, 706, 'Bon pour accord — date et signature du client', 8.5, true, $grey);
    $pdf->paragraph(40, 700, 270, 'En signant ce devis, le client accepte les travaux et les prix indiqués. Les prestations sont chiffrées selon la grille tarifaire en vigueur ; tout travail supplémentaire fera l\'objet d\'un nouvel accord.', 8, 1.35, $grey);

    $dir = __DIR__.'/../storage/uploads/devis';
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    if (!is_file($dir.'/.htaccess')) @file_put_contents($dir.'/.htaccess', "Require all denied\n");
    $file = $dir.'/'.$d['number'].'-'.bin2hex(random_bytes(4)).'.pdf';
    file_put_contents($file, $pdf->output());
    db_execute('UPDATE devis SET pdf_path = ? WHERE id = ?', [basename($file), (int)$d['id']]);
    return $file;
}

/** Nombre de pages du PDF produit (le champ de signature va sur la dernière page). */
function devis_pdf_pages(string $file): int
{
    return max(1, preg_match_all('#/Type /Page\b(?!s)#', (string)file_get_contents($file)));
}

/**
 * Envoie le devis au client pour signature électronique (e-mail Yousign).
 * Retour : ['ok' => bool, 'error' => ?string, 'simulated' => bool]
 */
function devis_send_for_signature(int $devisId, string $email, string $phone, array $actor): array
{
    $d = devis_by_id($devisId);
    if (!$d || $d['status'] !== 'brouillon') return ['ok' => false, 'error' => 'Seul un devis brouillon peut être envoyé.', 'simulated' => false];
    $email = trim($email);
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return ['ok' => false, 'error' => 'Adresse e-mail du client invalide.', 'simulated' => false];
    $c = !empty($d['client_id']) ? get_client_by_id((int)$d['client_id']) : null;
    if ($c && trim((string)$c['email']) === '') db_execute('UPDATE clients SET email = ? WHERE id = ?', [$email, (int)$c['id']]);
    $file = devis_pdf($d);
    $pages = devis_pdf_pages($file);

    if (yousign_simulated()) {
        db_execute("UPDATE devis SET status = 'envoye', yousign_request_id = ?, yousign_status = 'ongoing', signer_email = ?, sent_at = NOW(), error = NULL, updated_at = NOW() WHERE id = ?",
            ['SIM-'.$devisId.'-'.bin2hex(random_bytes(3)), $email, $devisId]);
        devis_log($d, 'Devis '.$d['number'].' envoyé pour signature à '.$email.' (SIMULATION)', $actor);
        return ['ok' => true, 'error' => null, 'simulated' => true];
    }
    $first = trim((string)($c['firstname'] ?? '')) ?: 'Client';
    $last = trim((string)($c['lastname'] ?? '')) ?: 'Client';
    // 1. Demande de signature (brouillon côté Yousign)
    $sr = yousign_request('POST', '/signature_requests', [
        'name' => mb_substr('Devis '.$d['number'].' — '.company_name(), 0, 128), 'delivery_mode' => 'email', 'timezone' => 'Europe/Paris',
        'signers_allowed_to_decline' => true, 'external_id' => 'EMAE-DEVIS-'.$devisId,
        'expiration_date' => date('Y-m-d', strtotime((string)($d['valid_until'] ?: '+30 days'))), 'audit_trail_locale' => 'fr',
    ]);
    if (!$sr['ok']) return devis_fail($devisId, $sr['error']);
    $srId = (string)$sr['data']['id'];
    db_execute('UPDATE devis SET yousign_request_id = ?, yousign_status = ? WHERE id = ?', [$srId, 'draft', $devisId]);
    // 2. Document à signer
    $doc = yousign_request('POST', '/signature_requests/'.$srId.'/documents', null, [
        'file' => new CURLFile($file, 'application/pdf', 'Devis-'.$d['number'].'.pdf'), 'nature' => 'signable_document',
    ], 60);
    if (!$doc['ok']) return devis_fail($devisId, $doc['error']);
    // 3. Signataire : signature électronique simple, code par e-mail (ou SMS si mobile fourni)
    $e164 = devis_phone_e164($phone);
    $signer = yousign_request('POST', '/signature_requests/'.$srId.'/signers', [
        'info' => array_filter(['first_name' => $first, 'last_name' => $last, 'email' => $email, 'phone_number' => $e164, 'locale' => 'fr'], static fn($v) => $v !== null && $v !== ''),
        'signature_level' => 'electronic_signature',
        'signature_authentication_mode' => $e164 ? 'otp_sms' : 'otp_email',
        'fields' => [['document_id' => (string)$doc['data']['id'], 'type' => 'signature', 'page' => $pages, 'x' => 345, 'y' => 725, 'width' => 190, 'height' => 60]],
    ]);
    if (!$signer['ok']) return devis_fail($devisId, $signer['error']);
    // 4. Activation : Yousign envoie l'e-mail au client
    $act = yousign_request('POST', '/signature_requests/'.$srId.'/activate', []);
    if (!$act['ok']) return devis_fail($devisId, $act['error']);
    db_execute("UPDATE devis SET status = 'envoye', yousign_signer_id = ?, yousign_status = ?, signer_email = ?, sent_at = NOW(), error = NULL, updated_at = NOW() WHERE id = ?",
        [(string)($signer['data']['id'] ?? ''), (string)($act['data']['status'] ?? 'ongoing'), $email, $devisId]);
    devis_log($d, 'Devis '.$d['number'].' envoyé pour signature à '.$email, $actor);
    return ['ok' => true, 'error' => null, 'simulated' => false];
}

function devis_fail(int $devisId, ?string $error): array
{
    db_execute('UPDATE devis SET error = ?, updated_at = NOW() WHERE id = ?', [mb_substr((string)$error, 0, 500), $devisId]);
    return ['ok' => false, 'error' => $error, 'simulated' => false];
}

function devis_phone_e164(string $phone): ?string
{
    $d = preg_replace('/\D/', '', $phone);
    if (preg_match('/^0[67]\d{8}$/', $d)) return '+33'.substr($d, 1);
    if (preg_match('/^33[67]\d{8}$/', $d)) return '+'.$d;
    return null;   // pas un mobile français : code envoyé par e-mail
}

function devis_log(array $d, string $note, ?array $actor, string $actorType = 'dispatcher'): void
{
    if (!empty($d['intervention_id'])) {
        $st = (string)(db_fetch('SELECT status FROM interventions WHERE id = ?', [(int)$d['intervention_id']])['status'] ?? '');
        log_intervention_history((int)$d['intervention_id'], $st, $st, $actorType, (int)($actor['id'] ?? 0), (string)($actor['name'] ?? 'Yousign'), $note);
    }
    integration_log('audit', $note, ['devis' => (int)$d['id']]);
}

/**
 * Relit l'état de la demande chez Yousign et met à jour le devis (appelé par le webhook,
 * le bouton « Actualiser » et la simulation). Signé → devis accepté sur la fiche, PDF signé conservé.
 */
function devis_refresh(int $devisId, ?string $simulateStatus = null): array
{
    $d = devis_by_id($devisId);
    if (!$d || empty($d['yousign_request_id'])) return ['ok' => false, 'error' => 'Devis non envoyé.'];
    if ($simulateStatus !== null) {
        if (!yousign_simulated()) return ['ok' => false, 'error' => 'Simulation indisponible : une clé Yousign est configurée.'];
        $status = $simulateStatus;
    } else {
        if (yousign_simulated()) return ['ok' => true, 'error' => null];
        $r = yousign_request('GET', '/signature_requests/'.rawurlencode((string)$d['yousign_request_id']));
        if (!$r['ok']) return ['ok' => false, 'error' => $r['error']];
        $status = (string)($r['data']['status'] ?? '');
    }
    $map = ['done' => 'signe', 'declined' => 'refuse', 'expired' => 'expire', 'canceled' => 'annule', 'deleted' => 'annule', 'rejected' => 'refuse'];
    $local = $map[$status] ?? $d['status'];
    if ($local === $d['status'] && $status === $d['yousign_status']) return ['ok' => true, 'error' => null];
    db_execute('UPDATE devis SET yousign_status = ?, status = ?, updated_at = NOW() WHERE id = ?', [$status, $local, $devisId]);
    if ($local !== $d['status']) {
        if ($local === 'signe') {
            $signedName = null;
            if ($simulateStatus === null) {
                $dl = yousign_request('GET', '/signature_requests/'.rawurlencode((string)$d['yousign_request_id']).'/documents/download?version=completed', null, null, 60, true);
                if ($dl['ok'] && str_starts_with((string)$dl['raw'], '%PDF')) {
                    $signedName = $d['number'].'-signe-'.bin2hex(random_bytes(4)).'.pdf';
                    file_put_contents(__DIR__.'/../storage/uploads/devis/'.$signedName, $dl['raw']);
                }
            }
            db_execute('UPDATE devis SET signed_at = NOW(), signed_pdf_path = ? WHERE id = ?', [$signedName, $devisId]);
            if (!empty($d['intervention_id'])) update_intervention((int)$d['intervention_id'], ['quote_accepted' => 1]);
        }
        $labels = ['signe' => 'signé par le client', 'refuse' => 'refusé par le client', 'expire' => 'expiré', 'annule' => 'annulé'];
        devis_log($d, 'Devis '.$d['number'].' '.($labels[$local] ?? $local).($simulateStatus !== null ? ' (SIMULATION)' : ''), null, 'system');
        devis_notify_dispatcher($d, $labels[$local] ?? $local);
    }
    return ['ok' => true, 'error' => null];
}

function devis_notify_dispatcher(array $d, string $what): void
{
    try {
        $iv = !empty($d['intervention_id']) ? get_intervention_by_id((int)$d['intervention_id']) : null;
        $to = '';
        if ($iv && !empty($iv['dispatcher_id'])) $to = (string)(db_fetch('SELECT email FROM dispatchers WHERE id = ?', [(int)$iv['dispatcher_id']])['email'] ?? '');
        if ($to === '') $to = company_email();
        notif_mail($to, 'Devis '.$d['number'].' '.$what, 'Devis '.$d['number'].' '.$what, [
            htmlspecialchars(money_fr((float)$d['total_ttc']).' TTC — '.(string)$d['title'], ENT_QUOTES, 'UTF-8'),
        ], $iv ? notif_abs_url('dispatcher/intervention_view.php?id='.(int)$iv['id'].'#devis') : null, 'Voir la fiche');
    } catch (Throwable $e) {}
}

/**
 * Vérifie la signature d'un webhook Yousign : HMAC-SHA256 du corps brut avec le secret du webhook,
 * transmis dans l'en-tête X-Yousign-Signature-256 (« sha256=<hex> »). Comparaison à temps constant.
 */
function yousign_webhook_verify(string $rawBody, string $header): bool
{
    $secret = integration_secret('yousign_webhook_secret');
    if ($secret === '' || $header === '') return false;
    $given = strtolower(trim(str_starts_with($header, 'sha256=') ? substr($header, 7) : $header));
    return hash_equals(hash_hmac('sha256', $rawBody, $secret), $given);
}

/** Filet de sécurité si un webhook est perdu : relit les devis en attente de signature (cron). */
function devis_refresh_pending(int $max = 20): int
{
    if (yousign_simulated()) return 0;
    devis_table();
    $n = 0;
    foreach (db_fetch_all("SELECT id FROM devis WHERE status = 'envoye' AND yousign_request_id IS NOT NULL ORDER BY id LIMIT ".max(1, min(100, $max))) as $d) {
        if (devis_refresh((int)$d['id'])['ok']) $n++;
        usleep(300000);
    }
    return $n;
}
