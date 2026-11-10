<?php
declare(strict_types=1);
// Rattachement des réalisations aux pages métier, grilles de prix, et
// chantiers masqués absents de la page publique.

function db_fetch_all(string $s, array $p=[]): array { return []; }
function db_fetch(string $s, array $p=[]): ?array { return null; }
function db_execute(string $s, array $p=[]): void {}

$_SERVER['SCRIPT_NAME']='/index.php';
$R = dirname(__DIR__);
require $R.'/includes/helpers.php';

$pass=0;$fail=0;
function check(string $l,mixed $g,mixed $w): void { global $pass,$fail;
  if($g===$w){$pass++;echo "  ok   $l\n";}
  else{$fail++;echo "  FAIL $l\n        obtenu  ".var_export($g,true)."\n        attendu ".var_export($w,true)."\n";} }

echo "\n1. Chaque type de service mène à la bonne page métier\n";
foreach ([
    'Électricité'                   => ['electricite'],
    'electricite'                   => ['electricite'],
    'Plomberie'                     => ['plomberie'],
    'Chauffage'                     => ['chauffage'],
    'PAC'                           => ['chauffage'],
    'Climatisation'                 => ['climatisation'],
    'CVC'                           => ['climatisation'],
    'Chauffage & Climatisation'     => ['chauffage','climatisation'],
    'VMC & Ventilation'             => ['vmc'],
    'VMC'                           => ['vmc'],
    'Portail & Visiophone'          => ['portail'],
    'Automatismes & Électroménager' => ['automatisme'],
    'Maintenance'                   => [],
    'Dépannage'                     => [],
    ''                              => [],
] as $type => $attendu) check("« $type »", realisation_trades((string)$type), $attendu);

echo "\n2. Tous les types proposés dans l'admin sont compris\n";
foreach (service_type_options() as $t) {
    if (in_array($t, ['Maintenance','Dépannage'], true)) continue;
    check("« $t » a une page métier", realisation_trades($t) !== [], true);
}

echo "\n3. Une grille de prix par métier du site, sans prix inventé\n";
check('mêmes métiers que le site (plus la grille de base)', array_values(array_diff(array_keys(tarifs_default_rows()), ['base'])), array_keys(service_trade_defaults()));
$prix = [];
foreach (tarifs_default_rows() as $_t => $rows) if ($_t !== 'base') foreach ($rows as $r) if ($r[1] !== '') $prix[] = $r[0];
check('aucun prix prérempli par métier (seule la grille de base reprend Pennylane)', $prix, []);
check('grille de base : prix TTC repris de Pennylane', tarifs_default_rows()['base'][0][1], '52,80 € TTC');

echo "\n4. La page publique ne montre que les chantiers visibles\n";
$src = file_get_contents($R.'/index.php');
$d = strpos($src, "if (\$route === 'realisations') {");
check('elle lit les réalisations visibles', str_contains(substr($src, (int)$d, 120), 'visible_realisations('), true);

echo "\n5. La page Nos services présente tous les métiers\n";
$d = strpos($src, "if (\$route === 'services') {");
check('la page a son gabarit', $d !== false, true);
check('elle parcourt les fiches métier', str_contains(substr($src, (int)$d, 500), 'array_keys(service_trade_defaults())'), true);

echo "\n6. Les cartes de l'accueil couvrent tous les métiers\n";
$cartes = service_cards_v14();
check('une carte par métier', array_column($cartes, 'link'), array_keys(service_trade_defaults()));
check('chaque carte a son icône', count(array_filter(array_column($cartes, 'placeholder_icon'))), count($cartes));

echo "\n";
echo $fail === 0 ? "$pass assertions PASSENT\n" : "$fail ÉCHECS sur ".($pass+$fail)."\n";
exit($fail === 0 ? 0 : 1);
