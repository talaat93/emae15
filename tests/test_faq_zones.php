<?php
declare(strict_types=1);
// Page FAQ (24/11/2026) : la question sur les zones cite la base (Dole) et les villes réellement
// desservies ; espaces avant les virgules retirés ; rien n'est enregistré en base.
$DB = ['settings' => []];
function db_fetch_all(string $s, array $p = []): array { return []; }
function db_fetch(string $s, array $p = []): ?array { return null; }
function db_execute(string $s, array $p = []): void {}
function db_last_id(): int { return 1; }
define('EMAE_TESTS_SANS_RESEAU', true);
$_SERVER['SCRIPT_NAME'] = '/index.php';
$R = dirname(__DIR__);
require $R.'/includes/helpers.php';
require_once $R.'/includes/geo_secteurs.php';
require_once $R.'/includes/city_pages.php';

$pass = 0; $fail = 0;
function check(string $l, mixed $g, mixed $w): void { global $pass, $fail;
    if ($g === $w) { $pass++; echo "  ok   $l\n"; }
    else { $fail++; echo "  FAIL $l\n        obtenu  ".var_export($g, true)."\n        attendu ".var_export($w, true)."\n"; } }

echo "\n1. Question sur les zones\n";
$z = faq_answer_display('Quelles sont vos zones d\'intervention ?', 'Nous intervenons dans le Doubs , la Côte-d\'Or, le Jura et les alentours , notamment autour de Quetigny , Dijon et Genlis');
check('base de Dole citée', str_contains($z, 'depuis notre base de Dole (Jura)'), true);
foreach (['Dole', 'Lons-le-Saunier', 'Besançon', 'Dijon'] as $v) check("$v cité", str_contains($z, $v), true);
check('aucun espace avant une virgule', str_contains($z, ' ,'), false);

echo "\n2. Autres questions : texte gardé, ponctuation corrigée\n";
check('espaces avant virgule et point retirés', faq_answer_display('Qui êtes-vous ?', 'Dole , Jura .'), 'Dole, Jura.');
check('« Intervenez-vous à Dole ? » non remplacée', faq_answer_display('Intervenez-vous à Dole ?', 'Oui.'), 'Oui.');

echo "\n3. Seulement à l'affichage\n";
check('la routine de cohérence n\'utilise pas l\'affichage', str_contains((string)file_get_contents($R.'/includes/coherence.php'), 'faq_answer_display'), false);
check('la page FAQ l\'utilise', str_contains((string)file_get_contents($R.'/index.php'), 'faq_answer_display('), true);

echo "\n".($fail === 0 ? "$pass assertions PASSENT" : "$fail ÉCHECS sur ".($pass + $fail))."\n";
exit($fail === 0 ? 0 : 1);
