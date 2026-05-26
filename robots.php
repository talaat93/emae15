<?php
declare(strict_types=1);
require_once __DIR__.'/includes/bootstrap.php';
header('Content-Type: text/plain; charset=utf-8');
$base = rtrim(site_base_url() ?: ('https://'.($_SERVER['HTTP_HOST'] ?? 'localhost')), '/');
echo "User-agent: *\n";
echo "Disallow: /admin/\n";
echo "Disallow: /api/\n";
echo "Disallow: /config/\n";
echo "Disallow: /includes/\n";
echo "Disallow: /storage/\n";
echo "Disallow: /install.php\n";
echo "Disallow: /robots.php\n";
echo "\n";
echo "Sitemap: {$base}/sitemap.php\n";
