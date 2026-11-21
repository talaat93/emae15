<?php
declare(strict_types=1);
/**
 * Référentiel (includes/referentiel.php et referentiel_seed.php) : la liste de départ tient
 * dans la table preset_items, chaque marque se rattache à un appareil connu, et la structure
 * envoyée aux écrans est correcte. Aucune base, aucun réseau.
 */
define('EMAE_TESTS_SANS_RESEAU', true);

function db_fetch_all(string $s, array $p = []): array { return []; }
function db_fetch(string $s, array $p = []): ?array { return null; }
function db_execute(string $s, array $p = []): bool { return true; }
function db_last_id(): int { return 1; }

$ROOT = dirname(__DIR__);
require $ROOT.'/includes/helpers.php';
require $ROOT.'/includes/referentiel.php';

$pass = 0; $fail = 0;
function check(string $l, mixed $g, mixed $w): void { global $pass, $fail;
    if ($g === $w) { $pass++; echo "  ok   $l\n"; }
    else { $fail++; echo "  FAIL $l — obtenu ".var_export($g, true)." / attendu ".var_export($w, true)."\n"; } }

$seed = require $ROOT.'/includes/referentiel_seed.php';
$rows = ref_seed_rows($seed);
$metiers = array_keys(intervention_category_config());

echo "\n1. La liste de départ tient dans la table\n";
check('plusieurs centaines d\'éléments', count($rows) > 300, true);
check('catégories de 60 caractères au plus', array_values(array_filter($rows, static fn($r) => mb_strlen($r[1]) > 60)), []);
check('libellés de 255 caractères au plus', array_values(array_filter($rows, static fn($r) => mb_strlen($r[2]) > 255 || trim($r[2]) === '')), []);
check('aucun doublon', count($rows), count(array_unique(array_map(static fn($r) => implode("\t", $r), $rows))));
check('types connus uniquement', array_values(array_diff(array_unique(array_column($rows, 0)), array_keys(ref_types()))), []);

echo "\n2. Rattachements cohérents\n";
$byMetier = array_filter($rows, static fn($r) => in_array($r[0], ['equipement', 'panne', 'phrase_diag', 'phrase_travaux'], true));
check('métiers existants', array_values(array_diff(array_unique(array_column($byMetier, 1)), $metiers)), []);
$equipements = array_unique(array_column(array_filter($rows, static fn($r) => $r[0] === 'equipement'), 2));
$marques = array_filter($rows, static fn($r) => $r[0] === 'marque');
check('chaque marque se rattache à un appareil proposé', array_values(array_diff(array_unique(array_column($marques, 1)), $equipements)), []);
$couples = array_map(static fn($r) => $r[1].'|'.$r[2], $marques);
$modeles = array_filter($rows, static fn($r) => $r[0] === 'modele');
check('chaque modèle se rattache à un couple appareil|marque', array_values(array_diff(array_unique(array_column($modeles, 1)), $couples)), []);
foreach (['electricite', 'chauffage', 'plomberie', 'climatisation', 'debouchage'] as $m) {
    foreach (['equipement', 'panne', 'phrase_diag', 'phrase_travaux'] as $t) {
        check("$m : des « ".ref_types()[$t]." »", count($seed[$t][$m] ?? []) >= 3, true);
    }
}

echo "\n3. Structure envoyée aux écrans\n";
$ref = ref_build(array_map(static fn($r) => ['type' => $r[0], 'category' => $r[1], 'label' => $r[2]], $rows));
check('appareils du chauffage', in_array('Chaudière gaz', $ref['equipement']['chauffage'], true), true);
check('marques d\'une chaudière gaz', in_array('Frisquet', $ref['marque']['Chaudière gaz'], true), true);
check('modèles Saunier Duval (chaudière gaz)', in_array('ThemaPlus Condens', $ref['modele']['Chaudière gaz|Saunier Duval'], true), true);
check('une même marque peut exister pour deux appareils', isset($ref['modele']['Pompe à chaleur air/eau|Atlantic'], $ref['modele']['Chaudière gaz|Atlantic']), true);
check('doublon ignoré', ref_build([['type' => 'panne', 'category' => 'x', 'label' => 'A'], ['type' => 'panne', 'category' => 'x', 'label' => 'A']])['panne']['x'], ['A']);
check('type inconnu ignoré', array_key_exists('autre', ref_build([['type' => 'autre', 'category' => '', 'label' => 'A']])), false);
check('installation composée', ref_installation_label('Chaudière gaz', 'Saunier Duval', 'ThemaPlus Condens'), 'Chaudière gaz · Saunier Duval · ThemaPlus Condens');
check('installation sans modèle', ref_installation_label('Chaudière gaz', 'Frisquet', ' '), 'Chaudière gaz · Frisquet');
check('sans liste en base : aucune phrase affichée', ref_phrases_html('phrase_diag', 'chauffage', 'f-diag'), '');

echo "\n".($fail === 0 ? "TOUTES LES $pass ASSERTIONS PASSENT" : "$fail ÉCHEC(S) sur ".($pass + $fail))."\n";
exit($fail === 0 ? 0 : 1);
