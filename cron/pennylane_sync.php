<?php
declare(strict_types=1);
/**
 * Synchronisation Pennylane, à lancer par une tâche cron toutes les 15 minutes :
 *   php /home/UTILISATEUR/public_html/cron/pennylane_sync.php
 * Idempotente et protégée par un verrou : deux exécutions simultanées ne se chevauchent pas.
 * Uniquement en ligne de commande (jamais depuis le navigateur).
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Accès refusé.');
}
require_once __DIR__.'/../includes/bootstrap.php';

$r = pennylane_sync_run('cron');
echo date('Y-m-d H:i:s').' '.($r['ok'] ? 'OK' : 'ERREUR').' — '.$r['message'].PHP_EOL;
// Même tâche : devis en attente de signature relus chez Yousign (au cas où un webhook serait perdu).
try { $n = devis_refresh_pending(); if ($n) echo 'Yousign : '.$n.' devis relu(s).'.PHP_EOL; } catch (Throwable $e) { echo 'Yousign : '.$e->getMessage().PHP_EOL; }
exit($r['ok'] ? 0 : 1);
