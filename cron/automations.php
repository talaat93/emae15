<?php
declare(strict_types=1);
/**
 * Tâches automatiques (alertes, demandes d'avis, rappels d'entretien, ménage).
 * À programmer dans o2switch → cPanel → Tâches cron, toutes les 10 minutes :
 *   php /home/VOTRE_COMPTE/public_html/cron/automations.php
 * Sans cette tâche, elles se lancent quand même après les visites du site.
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
require_once __DIR__.'/../includes/bootstrap.php';

$res = automations_run('cron', true);
foreach ($res as $tache => $resultat) echo $tache.' : '.(is_string($resultat) ? $resultat : json_encode($resultat)).PHP_EOL;
