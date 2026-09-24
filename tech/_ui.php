<?php
declare(strict_types=1);
// Petits éléments d'interface partagés par l'application technicien.
// Ne produit aucune sortie lorsqu'il est appelé directement.

function ta_icon(string $name): string
{
    static $paths = [
        'phone'  => '<path d="M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2"/>',
        'route'  => '<path d="M3 11l19-9-9 19-2-8-8-2z"/>',
        'pin'    => '<path d="M12 21s-7-6.2-7-11.5A7 7 0 0 1 19 9.5C19 14.8 12 21 12 21z"/><circle cx="12" cy="9.5" r="2.5"/>',
        'back'   => '<path d="M15 18l-6-6 6-6"/>',
        'logout' => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/>',
        'today'  => '<rect x="3" y="4.5" width="18" height="16.5" rx="2"/><path d="M16 2.5v4M8 2.5v4M3 10h18"/><circle cx="12" cy="15" r="1.6" fill="currentColor"/>',
        'next'   => '<rect x="3" y="4.5" width="18" height="16.5" rx="2"/><path d="M16 2.5v4M8 2.5v4M3 10h18M10 14l3 2.5-3 2.5"/>',
        'done'   => '<circle cx="12" cy="12" r="9"/><path d="m8 12 3 3 5-6"/>',
        'check'  => '<path d="m5 12 5 5L20 7"/>',
        'camera' => '<path d="M4 8h3l2-3h6l2 3h3a1 1 0 0 1 1 1v10a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V9a1 1 0 0 1 1-1z"/><circle cx="12" cy="13.5" r="3.5"/>',
        'doc'    => '<path d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"/><path d="M14 3v6h6M8 13h8M8 17h5"/>',
        'car'    => '<path d="M5 16V11l2-5h10l2 5v5M5 16h14M5 16v2M19 16v2"/><circle cx="8" cy="13.5" r="1"/><circle cx="16" cy="13.5" r="1"/>',
        'arrive' => '<path d="M12 21s-7-6.2-7-11.5A7 7 0 0 1 19 9.5C19 14.8 12 21 12 21z"/><path d="m9 9.5 2 2 4-4"/>',
        'save'   => '<path d="M5 3h11l3 3v13a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2z"/><path d="M7 3v5h8V3M7 21v-7h10v7"/>',
    ];
    return '<svg class="i" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
        . ($paths[$name] ?? '') . '</svg>';
}

/** Libellés et couleurs de statut vus par le technicien. */
function ta_status(string $status): array
{
    $cfg = intervention_status_config();
    $label = $cfg[$status]['label'] ?? ucfirst($status);
    if ($status === 'terminé') $label = 'Terminée';
    if (in_array($status, ['nouveau', 'confirmé', 'assigné'], true)) $label = 'Planifiée';
    return ['label' => $label, 'color' => $cfg[$status]['color'] ?? '#8c99ad'];
}

function ta_pill(string $status): string
{
    $s = ta_status($status);
    return '<span class="ta-pill" style="--dot:'.htmlspecialchars($s['color'], ENT_QUOTES, 'UTF-8').'">'
        . htmlspecialchars($s['label'], ENT_QUOTES, 'UTF-8') . '</span>';
}

/** Adresse lisible et lien d'itinéraire (ouvre l'appli GPS du téléphone). */
function ta_address(?string $street, ?string $postal, ?string $city): string
{
    return trim(implode(', ', array_filter([trim((string)$street), trim(trim((string)$postal).' '.trim((string)$city))])));
}
function ta_route_url(string $address): string
{
    return 'https://www.google.com/maps/dir/?api=1&destination='.rawurlencode($address);
}
function ta_tel(?string $phone): string
{
    return 'tel:'.preg_replace('/[^0-9+]/', '', (string)$phone);
}

function ta_fr_date(string $ymd, bool $withYear = false): string
{
    $j = ['dimanche','lundi','mardi','mercredi','jeudi','vendredi','samedi'];
    $m = ['','janvier','février','mars','avril','mai','juin','juillet','août','septembre','octobre','novembre','décembre'];
    $t = strtotime($ymd);
    return ucfirst($j[(int)date('w', $t)]).' '.(int)date('j', $t).' '.$m[(int)date('n', $t)].($withYear ? ' '.date('Y', $t) : '');
}

function ta_duration(int $min): string
{
    if ($min <= 0) return '';
    return $min >= 60 ? intdiv($min, 60).'h'.($min % 60 ? sprintf('%02d', $min % 60) : '') : $min.' min';
}

/** Navigation basse commune. $active : today | next | done */
function ta_nav(string $active, int $todayCount = 0): string
{
    $items = [
        'today' => ['Aujourd\'hui', 'today'],
        'next'  => ['À venir', 'next'],
        'done'  => ['Terminées', 'done'],
    ];
    $h = '<nav class="ta-nav">';
    foreach ($items as $k => [$label, $ico]) {
        $url = url_for('tech/dashboard.php'.($k === 'today' ? '' : '?v='.$k));
        $badge = ($k === 'today' && $todayCount > 0) ? '<em>'.$todayCount.'</em>' : '';
        $h .= '<a href="'.htmlspecialchars($url, ENT_QUOTES, 'UTF-8').'"'.($active === $k ? ' class="on"' : '').'>'
            . ta_icon($ico).$badge.$label.'</a>';
    }
    return $h.'</nav>';
}

function ta_head(string $title): void
{
    ?><!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0,viewport-fit=cover">
<meta name="robots" content="noindex,nofollow">
<meta name="theme-color" content="#16243f">
<title><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="preload" as="style" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" onload="this.onload=null;this.rel='stylesheet'">
<link rel="stylesheet" href="<?= htmlspecialchars(asset_url('assets/css/tech-app.css'), ENT_QUOTES, 'UTF-8') ?>">
</head>
<body>
<?php
}
