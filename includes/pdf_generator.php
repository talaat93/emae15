<?php
declare(strict_types=1);

/**
 * EMAE — Professional PDF Report Generator for Interventions
 * Uses mPDF if available, otherwise falls back to a printable HTML page.
 */
class InterventionPdfGenerator
{
    private const NAVY  = '#16243F';
    private const ORANGE = '#C0942B';
    private const LIGHT_BG = '#F5F7FA';
    private const BORDER = '#D9DDE8';

    /** Root of the application (directory containing /includes) */
    private string $appRoot;

    public function __construct()
    {
        $this->appRoot = realpath(__DIR__ . '/..') ?: __DIR__ . '/..';
    }

    /* ------------------------------------------------------------------ */
    /*  Public API                                                          */
    /* ------------------------------------------------------------------ */

    /**
     * Generate an intervention PDF and return the raw bytes.
     * Also saves to storage/rapports/<ref>.pdf.
     *
     * @param array $iv        Intervention row (with joined client/tech fields)
     * @param array $client    Alias for client fields (or pass [] — they're in $iv)
     * @param array $photos    Array of relative/absolute photo paths
     * @param array $materials Array of ['name'=>..., 'qty'=>..., 'unit'=>...]
     * @param array $history   Intervention history rows
     */
    public function generateInterventionPDF(
        array $iv,
        array $client,
        array $photos,
        array $materials,
        array $history
    ): string {
        $html = $this->buildHtml($iv, $photos, $materials, $history);

        if (!class_exists('\Mpdf\Mpdf')) {
            // Graceful fallback: return HTML bytes (caller can stream as text/html)
            return $html;
        }

        $mpdf = new \Mpdf\Mpdf([
            'mode'              => 'utf-8',
            'format'            => 'A4',
            'margin_top'        => 28,
            'margin_bottom'     => 22,
            'margin_left'       => 15,
            'margin_right'      => 15,
            'setAutoTopMargin'  => false,
            'setAutoBottomMargin' => false,
            'tempDir'           => sys_get_temp_dir(),
        ]);

        $mpdf->SetTitle('Rapport d\'intervention — ' . ($iv['ref'] ?? ''));
        $mpdf->SetAuthor(company_name());
        $mpdf->SetCreator('EMAE Platform');
        $mpdf->WriteHTML($html);

        // Save to storage/rapports/
        $savePath = $this->getSavePath($iv['ref'] ?? 'rapport');
        $mpdf->Output($savePath, \Mpdf\Output\Destination::FILE);

        return $mpdf->Output('', \Mpdf\Output\Destination::STRING_RETURN);
    }

    /* ------------------------------------------------------------------ */
    /*  File path helpers                                                   */
    /* ------------------------------------------------------------------ */

    public function getSavePath(string $ref): string
    {
        $dir = $this->appRoot . '/storage/rapports';
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $safe = preg_replace('/[^A-Za-z0-9\-_]/', '-', $ref);
        return $dir . '/rapport-' . $safe . '.pdf';
    }

    /* ------------------------------------------------------------------ */
    /*  HTML builder                                                        */
    /* ------------------------------------------------------------------ */

    private function buildHtml(array $iv, array $photos, array $materials, array $history): string
    {
        $ref          = $iv['ref'] ?? '—';
        $co           = defined('__BOOTSTRAP_LOADED__') ? company_name() : 'EMAE';
        $coPhone      = defined('__BOOTSTRAP_LOADED__') ? company_phone() : '';
        $coEmail      = defined('__BOOTSTRAP_LOADED__') ? company_email() : '';
        $now          = date('d/m/Y H:i');

        // Client
        $clientName   = trim(($iv['lastname'] ?? '') . ' ' . ($iv['firstname'] ?? ''));
        if ($clientName === '') $clientName = '—';
        $clientPhone  = $iv['client_phone'] ?? $iv['phone'] ?? '—';
        $clientAddrParts = array_filter([
            $iv['address'] ?? $iv['client_address'] ?? '',
            trim(($iv['postal_code'] ?? $iv['client_postal'] ?? '') . ' ' . ($iv['client_city'] ?? $iv['city'] ?? '')),
        ]);
        $clientAddr   = implode(', ', $clientAddrParts) ?: '—';

        // Dates / times
        $scheduledDate = !empty($iv['scheduled_date'])
            ? date('d/m/Y', strtotime((string)$iv['scheduled_date']))
            : '—';
        $arrivedTime  = !empty($iv['tech_arrived_at'])
            ? date('H:i', strtotime((string)$iv['tech_arrived_at']))
            : ($iv['tech_ticket_time'] ?? '—');
        $closeTime    = !empty($iv['tech_completed_at'])
            ? date('H:i', strtotime((string)$iv['tech_completed_at']))
            : ($iv['tech_close_time'] ?? '—');

        // Duration (estimate in minutes → h min)
        $durStr = '—';
        if (!empty($iv['tech_time_spent'])) {
            $durStr = $this->formatMinutes((int)$iv['tech_time_spent']);
        } elseif (!empty($iv['duration_estimate'])) {
            $durStr = $this->formatMinutes((int)$iv['duration_estimate']) . ' (estimé)';
        }

        // Checklist
        $realizable = $this->tristate($iv['tech_realizable'] ?? null);
        $badUse     = $this->tristate($iv['tech_bad_use'] ?? null);
        $elevator   = $this->tristate($iv['tech_elevator_restored'] ?? null);

        // Logo
        $logoHtml = $this->buildLogoHtml();

        // Materials table
        $materialsHtml = $this->buildMaterialsTable($materials);

        // Photos grid
        $photosHtml = $this->buildPhotosGrid($photos);

        $statusLabel = $this->statusLabel($iv['status'] ?? '');
        $category    = $iv['category'] ?? '—';
        $typeLabel   = $iv['type_label'] ?? '—';
        $techName    = $iv['tech_name'] ?? '—';
        $deviceNo    = $iv['tech_device_number'] ?? '';

        return <<<HTML
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<style>
  * { margin:0; padding:0; box-sizing:border-box; }
  body { font-family: DejaVu Sans, Arial, sans-serif; font-size:9.5pt; color:#222; background:#fff; }

  /* ── Header ─────────────────────────────────────────────────── */
  .header-table { width:100%; border-collapse:collapse; margin-bottom:10px; }
  .header-logo  { width:44%; vertical-align:middle; }
  .header-title { width:56%; vertical-align:middle; text-align:right; }
  .header-title .main-title { font-size:15pt; font-weight:700; color:{$this->NAVY}; letter-spacing:.04em; text-transform:uppercase; }
  .header-title .ref-line   { font-size:9pt; color:{$this->ORANGE}; font-weight:700; margin-top:3px; }
  .header-title .status-pill {
      display:inline-block; padding:2px 8px; border-radius:10px;
      background:{$this->ORANGE}; color:#fff; font-size:7.5pt; font-weight:700;
      text-transform:uppercase; margin-top:3px; letter-spacing:.05em;
  }
  .header-divider { border:none; border-top:2.5px solid {$this->NAVY}; margin:6px 0 10px; }

  /* ── Section cards ───────────────────────────────────────────── */
  .section { margin-bottom:10px; page-break-inside:avoid; }
  .section-title {
      background:{$this->NAVY}; color:#fff; font-size:8.5pt; font-weight:700;
      padding:4px 9px; text-transform:uppercase; letter-spacing:.07em;
      border-radius:3px 3px 0 0;
  }
  .section-body { border:1px solid {$this->BORDER}; border-top:none; border-radius:0 0 3px 3px; padding:7px 9px; background:#fff; }

  /* ── Two-column layout ───────────────────────────────────────── */
  .two-col { width:100%; border-collapse:collapse; margin-bottom:10px; }
  .two-col td { vertical-align:top; padding-right:8px; }
  .two-col td:last-child { padding-right:0; }

  /* ── Field rows ──────────────────────────────────────────────── */
  .field-row { margin-bottom:4px; overflow:hidden; }
  .field-lbl { font-size:7.5pt; font-weight:700; color:#777; text-transform:uppercase; letter-spacing:.05em; }
  .field-val { font-size:9.5pt; color:#111; margin-top:1px; }

  /* ── Checklist ───────────────────────────────────────────────── */
  .check-row { display:table; width:100%; margin-bottom:4px; }
  .check-lbl { display:table-cell; font-size:8.5pt; color:#444; width:70%; }
  .check-box { display:table-cell; text-align:right; font-weight:700; font-size:8.5pt; }
  .check-yes { color:#16a34a; }
  .check-no  { color:#dc2626; }
  .check-na  { color:#9ca3af; }

  /* ── Materials table ─────────────────────────────────────────── */
  .mat-table { width:100%; border-collapse:collapse; margin-top:3px; }
  .mat-table th { background:{$this->NAVY}; color:#fff; font-size:8pt; font-weight:700;
      padding:4px 7px; text-align:left; }
  .mat-table td { font-size:8.5pt; padding:3px 7px; border-bottom:1px solid {$this->BORDER}; }
  .mat-table tr:nth-child(even) td { background:{$this->LIGHT_BG}; }

  /* ── Photos ──────────────────────────────────────────────────── */
  .photos-table { width:100%; border-collapse:collapse; }
  .photo-cell   { width:50%; padding:4px; vertical-align:top; }
  .photo-cell img { width:100%; max-height:160px; object-fit:cover; border:1px solid {$this->BORDER}; border-radius:3px; }

  /* ── Signature ───────────────────────────────────────────────── */
  .sig-table { width:100%; border-collapse:collapse; margin-top:6px; }
  .sig-cell  { width:50%; padding:5px; vertical-align:top; }
  .sig-box   {
      border:1px solid {$this->BORDER}; border-radius:4px; min-height:70px;
      padding:6px 8px; background:{$this->LIGHT_BG};
  }
  .sig-label { font-size:8pt; font-weight:700; color:{$this->NAVY}; margin-bottom:4px; text-transform:uppercase; letter-spacing:.05em; }
  .sig-img   { max-width:100%; max-height:55px; }

  /* ── Footer ──────────────────────────────────────────────────── */
  .footer { text-align:center; font-size:7.5pt; color:#888; border-top:1px solid {$this->BORDER}; padding-top:5px; margin-top:8px; }

  /* ── Text blocks ─────────────────────────────────────────────── */
  .text-block {
      font-size:9pt; color:#222; background:{$this->LIGHT_BG};
      border-left:3px solid {$this->ORANGE}; padding:5px 8px; border-radius:2px;
      white-space:pre-wrap; word-wrap:break-word;
  }

  /* ── Orange accent label ─────────────────────────────────────── */
  .accent { color:{$this->ORANGE}; font-weight:700; }
</style>
</head>
<body>

<!-- ===== HEADER ===== -->
<table class="header-table">
  <tr>
    <td class="header-logo">{$logoHtml}</td>
    <td class="header-title">
      <div class="main-title">Rapport d'intervention</div>
      <div class="ref-line">Réf. : {$this->e($ref)}</div>
      <div><span class="status-pill">{$this->e($statusLabel)}</span></div>
    </td>
  </tr>
</table>
<hr class="header-divider">

<!-- ===== CLIENT + INTERVENTION (2 columns) ===== -->
<table class="two-col">
  <tr>
    <td style="width:50%">
      <div class="section">
        <div class="section-title">Client</div>
        <div class="section-body">
          {$this->fieldRow('Nom', $clientName)}
          {$this->fieldRow('Adresse', $clientAddr)}
          {$this->fieldRow('Téléphone', $clientPhone)}
        </div>
      </div>
    </td>
    <td style="width:50%">
      <div class="section">
        <div class="section-title">Intervention</div>
        <div class="section-body">
          {$this->fieldRow('Catégorie', $category)}
          {$this->fieldRow('Type', $typeLabel)}
          {$this->fieldRow('Date planifiée', $scheduledDate)}
          {$this->fieldRow('Heure d\'arrivée', $arrivedTime)}
          {$this->fieldRow('Heure de clôture', $closeTime)}
          {$this->fieldRow('Durée', $durStr)}
          {$this->fieldRow('N° appareil', $deviceNo)}
        </div>
      </div>
    </td>
  </tr>
</table>

<!-- ===== TECHNICIEN + NOTES ===== -->
<table class="two-col">
  <tr>
    <td style="width:50%">
      <div class="section">
        <div class="section-title">Technicien</div>
        <div class="section-body">
          {$this->fieldRow('Technicien', $techName)}
          {$this->fieldRow('Observations', $iv['notes_admin'] ?? '')}
        </div>
      </div>
    </td>
    <td style="width:50%">
      <div class="section">
        <div class="section-title">Checklist</div>
        <div class="section-body">
          {$this->checkRow('Intervention réalisable', $realizable)}
          {$this->checkRow('Mauvaise utilisation', $badUse)}
          {$this->checkRow('Ascenseur remis en service', $elevator)}
        </div>
      </div>
    </td>
  </tr>
</table>

<!-- ===== COMPTE RENDU ===== -->
<div class="section">
  <div class="section-title">Compte rendu technicien</div>
  <div class="section-body">
    {$this->fieldRow('Intitulé panne', $iv['tech_fault_label'] ?? '')}
    <div class="field-row">
      <div class="field-lbl">Descriptif (rapport technicien)</div>
      <div class="field-val" style="margin-top:3px;">
        <div class="text-block">{$this->e($iv['tech_report'] ?? '—')}</div>
      </div>
    </div>
    <div class="field-row" style="margin-top:6px;">
      <div class="field-lbl">Informations complémentaires</div>
      <div class="field-val" style="margin-top:3px;">
        <div class="text-block">{$this->e($iv['tech_notes_extra'] ?? '—')}</div>
      </div>
    </div>
  </div>
</div>

{$materialsHtml}

{$photosHtml}

<!-- ===== SIGNATURES ===== -->
<div class="section">
  <div class="section-title">Signatures</div>
  <div class="section-body">
    <table class="sig-table">
      <tr>
        <td class="sig-cell">
          <div class="sig-box">
            <div class="sig-label">Signature technicien</div>
            {$this->buildTechSignature($iv)}
          </div>
        </td>
        <td class="sig-cell">
          <div class="sig-box">
            <div class="sig-label">Signature client</div>
            {$this->buildClientSignature($iv)}
          </div>
        </td>
      </tr>
    </table>
  </div>
</div>

<!-- ===== FOOTER ===== -->
<div class="footer">
  {$this->e($co)} &mdash; {$this->e($coPhone)} &mdash; {$this->e($coEmail)}
  &nbsp;|&nbsp; Généré le {$now}
</div>

</body>
</html>
HTML;
    }

    /* ------------------------------------------------------------------ */
    /*  Logo                                                                */
    /* ------------------------------------------------------------------ */

    private function buildLogoHtml(): string
    {
        $logoPath = '';
        if (function_exists('site_logo_path')) {
            $logoPath = site_logo_path();
        }

        if ($logoPath !== '') {
            $absPath = $this->appRoot . '/' . ltrim($logoPath, '/');
            if (is_file($absPath)) {
                $mime = $this->guessMime($absPath);
                if ($mime !== null) {
                    $b64 = base64_encode((string)file_get_contents($absPath));
                    $co  = function_exists('company_name') ? company_name() : 'EMAE';
                    return '<img src="data:' . $mime . ';base64,' . $b64 . '" alt="'
                         . htmlspecialchars($co, ENT_QUOTES, 'UTF-8')
                         . '" style="max-height:55px;max-width:180px;">';
                }
            }
        }

        // Fallback: stylised text
        $co = function_exists('company_name') ? company_name() : 'EMAE';
        return '<span style="font-size:20pt;font-weight:700;color:' . self::NAVY . ';letter-spacing:.05em;">'
             . htmlspecialchars($co, ENT_QUOTES, 'UTF-8') . '</span>';
    }

    private function guessMime(string $path): ?string
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        return match($ext) {
            'png'  => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'gif'  => 'image/gif',
            'svg'  => 'image/svg+xml',
            'webp' => 'image/webp',
            default => null,
        };
    }

    /* ------------------------------------------------------------------ */
    /*  Materials table                                                     */
    /* ------------------------------------------------------------------ */

    private function buildMaterialsTable(array $materials): string
    {
        if (empty($materials)) return '';

        $rows = '';
        foreach ($materials as $m) {
            $name = $m['name'] ?? $m['designation'] ?? '';
            $qty  = $m['qty']  ?? $m['quantite']    ?? '';
            $unit = $m['unit'] ?? $m['unite']        ?? '';
            $rows .= '<tr>'
                   . '<td>' . $this->e($name) . '</td>'
                   . '<td style="text-align:center;">' . $this->e((string)$qty) . '</td>'
                   . '<td>' . $this->e($unit) . '</td>'
                   . '</tr>';
        }

        return <<<HTML
<div class="section">
  <div class="section-title">Matériaux utilisés</div>
  <div class="section-body">
    <table class="mat-table">
      <thead>
        <tr>
          <th>Désignation</th>
          <th style="text-align:center;width:80px;">Quantité</th>
          <th style="width:80px;">Unité</th>
        </tr>
      </thead>
      <tbody>{$rows}</tbody>
    </table>
  </div>
</div>
HTML;
    }

    /* ------------------------------------------------------------------ */
    /*  Photos grid                                                         */
    /* ------------------------------------------------------------------ */

    private function buildPhotosGrid(array $photos): string
    {
        if (empty($photos)) return '';

        $cells = '';
        $count = 0;
        foreach ($photos as $photo) {
            $path = is_array($photo) ? ($photo['path'] ?? '') : (string)$photo;
            if ($path === '') continue;

            // Resolve to filesystem path
            $absPath = '';
            if (str_starts_with($path, '/') && is_file($path)) {
                $absPath = $path;
            } elseif (is_file($this->appRoot . '/' . ltrim($path, '/'))) {
                $absPath = $this->appRoot . '/' . ltrim($path, '/');
            }

            $imgHtml = '';
            if ($absPath !== '') {
                $mime = $this->guessMime($absPath);
                if ($mime !== null && $mime !== 'image/svg+xml') {
                    $b64 = base64_encode((string)file_get_contents($absPath));
                    $imgHtml = '<img src="data:' . $mime . ';base64,' . $b64 . '" alt="Photo">';
                }
            }

            if ($imgHtml === '') continue;

            if ($count % 2 === 0) {
                if ($count > 0) $cells .= '</tr>';
                $cells .= '<tr>';
            }
            $cells .= '<td class="photo-cell">' . $imgHtml . '</td>';
            $count++;
        }
        if ($count === 0) return '';

        // Close last row + pad odd row
        if ($count % 2 === 1) $cells .= '<td class="photo-cell"></td>';
        $cells .= '</tr>';

        return <<<HTML
<div class="section">
  <div class="section-title">Photos</div>
  <div class="section-body">
    <table class="photos-table">{$cells}</table>
  </div>
</div>
HTML;
    }

    /* ------------------------------------------------------------------ */
    /*  Signature helpers                                                   */
    /* ------------------------------------------------------------------ */

    private function buildTechSignature(array $iv): string
    {
        $html = '';
        if (!empty($iv['tech_name'])) {
            $html .= '<div style="font-size:8pt;color:#555;margin-bottom:4px;">'
                   . $this->e($iv['tech_name']) . '</div>';
        }
        if (!empty($iv['tech_signature'])) {
            $sigPath = $iv['tech_signature'];
            // Data URL already
            if (str_starts_with($sigPath, 'data:')) {
                $html .= '<img class="sig-img" src="' . htmlspecialchars($sigPath, ENT_QUOTES) . '" alt="Signature">';
            } else {
                $abs = is_file($sigPath) ? $sigPath : $this->appRoot . '/' . ltrim($sigPath, '/');
                if (is_file($abs)) {
                    $mime = $this->guessMime($abs);
                    if ($mime) {
                        $b64 = base64_encode((string)file_get_contents($abs));
                        $html .= '<img class="sig-img" src="data:' . $mime . ';base64,' . $b64 . '" alt="Signature">';
                    }
                }
            }
        }
        if (!empty($iv['tech_completed_at'])) {
            $html .= '<div style="font-size:7.5pt;color:#888;margin-top:4px;">Le '
                   . date('d/m/Y', strtotime((string)$iv['tech_completed_at'])) . '</div>';
        }
        return $html;
    }

    private function buildClientSignature(array $iv): string
    {
        $html = '';
        if (!empty($iv['tech_client_name'])) {
            $html .= '<div style="font-size:8pt;color:#555;margin-bottom:4px;">'
                   . $this->e($iv['tech_client_name']) . '</div>';
        }
        return $html;
    }

    /* ------------------------------------------------------------------ */
    /*  Tiny helpers                                                        */
    /* ------------------------------------------------------------------ */

    private function e(mixed $v): string
    {
        return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    }

    private function fieldRow(string $label, mixed $value): string
    {
        $val = (string)($value ?? '');
        if ($val === '') return '';
        return '<div class="field-row">'
             . '<div class="field-lbl">' . $this->e($label) . '</div>'
             . '<div class="field-val">' . $this->e($val) . '</div>'
             . '</div>';
    }

    private function checkRow(string $label, string $state): string
    {
        [$cls, $text] = match($state) {
            'yes' => ['check-yes', 'Oui ✓'],
            'no'  => ['check-no',  'Non ✗'],
            default => ['check-na', '—'],
        };
        return '<div class="check-row">'
             . '<div class="check-lbl">' . $this->e($label) . '</div>'
             . '<div class="check-box ' . $cls . '">' . $text . '</div>'
             . '</div>';
    }

    private function tristate(mixed $val): string
    {
        if ($val === null || $val === '') return 'na';
        return (int)$val === 1 ? 'yes' : 'no';
    }

    private function formatMinutes(int $minutes): string
    {
        if ($minutes <= 0) return '—';
        $h = intdiv($minutes, 60);
        $m = $minutes % 60;
        return $h > 0 ? "{$h}h" . ($m > 0 ? " {$m}min" : '') : "{$m} min";
    }

    private function statusLabel(string $status): string
    {
        return match($status) {
            'nouveau'       => 'Nouveau',
            'en_cours'      => 'En cours',
            'planifie'      => 'Planifié',
            'termine'       => 'Terminé',
            'cloture'       => 'Clôturé',
            'annule'        => 'Annulé',
            default         => $status,
        };
    }
}
