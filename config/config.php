<?php
declare(strict_types=1);

// Config loader — les identifiants réels vivent dans config.local.php
// (ignoré par git, jamais versionné) ou dans des variables d'environnement.
// Copie config.php.example en config.local.php pour démarrer en local.

$local = __DIR__ . '/config.local.php';
if (is_file($local)) {
    return require $local;
}

$env = static fn (string $key, string $default = ''): string
    => (getenv($key) !== false) ? getenv($key) : $default;

return [
    'installed' => true,
    'db' => [
        'host'    => $env('DB_HOST', 'localhost'),
        'port'    => $env('DB_PORT', '3306'),
        'name'    => $env('DB_NAME'),
        'user'    => $env('DB_USER'),
        'pass'    => $env('DB_PASS'),
        'charset' => $env('DB_CHARSET', 'utf8mb4'),
    ],
    'site' => [
        'base_url' => $env('SITE_BASE_URL'),
    ],
];
