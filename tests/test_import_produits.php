<?php
declare(strict_types=1);
/**
 * Import des produits Pennylane dans la grille (includes/pricing.php) : TVA, unité, métier
 * deviné, produits « VVV » écartés d'office, produits déjà présents non proposés.
 * Et la liste de prestations ajoutée pour tous les métiers. Aucune base, aucun réseau.
 */
define('EMAE_TESTS_SANS_RESEAU', true);

function db_fetch_all(string $s, array $p = []): array { return []; }
function db_fetch(string $s, array $p = []): ?array { return null; }
function db_execute(string $s, array $p = []): bool { return true; }
function db_last_id(): int { return 1; }

$ROOT = dirname(__DIR__);
require $ROOT.'/includes/helpers.php';
require $ROOT.'/includes/notifications.php';
require $ROOT.'/includes/integrations.php';
require $ROOT.'/includes/review.php';
require $ROOT.'/includes/pricing.php';
require $ROOT.'/includes/pennylane.php';

$pass = 0; $fail = 0;
function check(string $l, mixed $g, mixed $w): void { global $pass, $fail;
    if ($g === $w) { $pass++; echo "  ok   $l\n"; }
    else { $fail++; echo "  FAIL $l — obtenu ".var_export($g, true)." / attendu ".var_export($w, true)."\n"; } }

echo "\n1. Un produit Pennylane mis au format de la grille\n";
$p = pennylane_product_for_grid(['id' => 123, 'label' => 'Remplacement mitigeur', 'price_before_tax' => '95.50', 'vat_rate' => 'FR_100', 'unit' => 'unité', 'reference' => 'plb mitig 2'], [], []);
check('code tiré de la référence', $p['code'], 'PLBMITIG2');
check('prix HT', $p['price_ht'], 95.5);
check('TVA 10 %', $p['vat_rate'], 10.0);
check('unité', $p['unit'], 'unite');
check('métier deviné : plomberie', $p['category'], 'plomberie');
check('coché d\'office', $p['checked'], true);
$sansRef = pennylane_product_for_grid(['id' => 77, 'label' => 'Déplacement', 'price_before_tax' => '49', 'vat_rate' => 'FR_200'], [], []);
check('sans référence : code PL-<id>', $sansRef['code'], 'PL-77');
check('sans unité : forfait', $sansRef['unit'], 'forfait');
check('rien de reconnu : commun', $sansRef['category'], 'commun');

echo "\n2. Écartés ou déjà présents\n";
$vvv = pennylane_product_for_grid(['id' => 5, 'label' => 'VVV Pack test', 'price_before_tax' => '10'], [], []);
check('nom contenant VVV : signalé', $vvv['vvv'], true);
check('nom contenant VVV : décoché', $vvv['checked'], false);
check('vvv en minuscules aussi', pennylane_product_for_grid(['id' => 6, 'label' => 'prestation vvv'], [], [])['checked'], false);
check('« VV » seul n\'est pas écarté', pennylane_product_for_grid(['id' => 7, 'label' => 'Pose VV double'], [], [])['vvv'], false);
$deja = pennylane_product_for_grid(['id' => 123, 'label' => 'Remplacement mitigeur'], ['123'], []);
check('déjà lié dans la grille : signalé et décoché', [$deja['in_grid'], $deja['checked']], [true, false]);
check('même code déjà dans la grille', pennylane_product_for_grid(['id' => 9, 'label' => 'X', 'reference' => 'DEPL'], [], ['DEPL'])['in_grid'], true);

echo "\n3. Petits outils\n";
check('TVA 5,5 %', pennylane_vat_rate_from_code('FR_55'), 5.5);
check('TVA inconnue → 20 %', pennylane_vat_rate_from_code('XX'), 20.0);
check('unité heure', price_unit_from_text('Heure'), 'heure');
check('unité kg', price_unit_from_text('kg'), 'kg');
foreach (['Débouchage WC' => 'debouchage', 'Entretien climatisation' => 'climatisation', 'Remplacement circulateur chaudière' => 'chauffage',
          'Remplacement disjoncteur' => 'electricite', 'Recherche de fuite' => 'plomberie'] as $lab => $want) {
    check("métier de « $lab »", price_guess_category($lab), $want);
}

echo "\n4. Prestations ajoutées pour tous les métiers\n";
$extra = require $ROOT.'/includes/price_grid_extra_seed.php';
$base  = require $ROOT.'/includes/price_grid_seed.php';
$codes = array_column($extra, 1);
check('aucun code en double', count($codes), count(array_unique($codes)));
check('aucun code déjà dans la grille de départ', array_values(array_intersect($codes, array_column($base, 1))), []);
check('codes de 40 caractères au plus, lettres, chiffres et tirets', array_values(array_filter($codes, static fn($c) => strlen($c) > 40 || !preg_match('/^[A-Z0-9-]+$/', $c))), []);
check('métiers connus', array_values(array_diff(array_unique(array_column($extra, 0)), array_keys(price_categories()))), []);
check('unités connues', array_values(array_diff(array_unique(array_column($extra, 3)), array_keys(price_units()))), []);
check('prix positifs', array_values(array_filter($extra, static fn($r) => !is_numeric($r[4]) || $r[4] <= 0)), []);
foreach (['electricite', 'plomberie', 'chauffage', 'climatisation', 'debouchage'] as $m) {
    check("$m : au moins 5 prestations ajoutées", count(array_filter($extra, static fn($r) => $r[0] === $m)) >= 5, true);
}

echo "\n".($fail === 0 ? "TOUTES LES $pass ASSERTIONS PASSENT" : "$fail ÉCHEC(S) sur ".($pass + $fail))."\n";
exit($fail === 0 ? 0 : 1);
