<?php
declare(strict_types=1);
// Manifeste de l'application technicien (installation sur l'écran d'accueil).
require_once __DIR__.'/../includes/helpers.php';
header('Content-Type: application/manifest+json; charset=utf-8');
header('Cache-Control: public, max-age=3600');
$icon = static fn(string $f) => url_for('assets/img/'.$f);
echo json_encode([
    'name'             => 'EMAE Technicien',
    'short_name'       => 'EMAE Tech',
    'description'      => 'Interventions du jour, rapports et signatures.',
    'lang'             => 'fr',
    'id'               => url_for('tech/dashboard.php'),
    'start_url'        => url_for('tech/dashboard.php'),
    'scope'            => url_for('tech/'),
    'display'          => 'standalone',
    'orientation'      => 'portrait',
    'background_color' => '#f2f4f7',
    'theme_color'      => '#16243f',
    'icons'            => [
        ['src' => $icon('icon-192.png'), 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
        ['src' => $icon('icon-512.png'), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
        ['src' => $icon('icon-512.png'), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
    ],
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
