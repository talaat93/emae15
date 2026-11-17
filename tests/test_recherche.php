<?php
declare(strict_types=1);
/**
 * Recherche dans les listes (assets/js/filtre.js) : chaque page de liste du menu l'active,
 * le script est chargé côté dispatcher et côté technicien. Contrôle des fichiers, sans navigateur
 * (le fonctionnement a été vérifié dans Chromium à la livraison).
 */
$ROOT = dirname(__DIR__);
$pass = 0; $fail = 0;
function check(string $l, mixed $g, mixed $w): void { global $pass, $fail;
    if ($g === $w) { $pass++; echo "  ok   $l\n"; }
    else { $fail++; echo "  FAIL $l — obtenu ".var_export($g, true)." / attendu ".var_export($w, true)."\n"; } }
$lire = static fn(string $f) => (string)@file_get_contents($ROOT.'/'.$f);

echo "\n1. Pages de liste du menu dispatcher\n";
foreach (['interventions', 'clients', 'factures', 'finances', 'techniciens', 'tasks', 'tarifs', 'presets'] as $p) {
    check("$p active la recherche", (bool)preg_match('/\$listFilter\s*=/', $lire("dispatcher/$p.php")), true);
}
check('l\'en-tête pose data-filtre sur la page', str_contains($lire('dispatcher/partials/header.php'), 'data-filtre'), true);
check('le pied de page charge le script', str_contains($lire('dispatcher/partials/footer.php'), 'assets/js/filtre.js'), true);

echo "\n2. Application technicien\n";
check('le script est chargé sur toutes les pages', str_contains($lire('tech/_ui.php'), 'assets/js/filtre.js'), true);
check('« Aujourd\'hui » filtre les interventions', str_contains($lire('tech/dashboard.php'), 'data-filtre-items=".ta-job"'), true);
check('recherche dans la liste des prestations', str_contains($lire('tech/disp_intervention.php'), 'emaeSelectRecherche(sel)'), true);

echo "\n3. Le script\n";
$js = $lire('assets/js/filtre.js');
check('masque par une classe (style d\'origine intact)', str_contains($js, "classList.toggle('emae-cache'") && !str_contains($js, "style.display = ok"), true);
check('insensible aux accents', str_contains($js, "normalize('NFD')"), true);
check('menus déroulants de plus de 15 choix', str_contains($js, 'options.length > 15'), true);
check('aucun appel au serveur', !preg_match('/fetch\(|XMLHttpRequest/', $js), true);

echo "\n".($fail === 0 ? "TOUTES LES $pass ASSERTIONS PASSENT" : "$fail ÉCHEC(S) sur ".($pass + $fail))."\n";
exit($fail === 0 ? 0 : 1);
