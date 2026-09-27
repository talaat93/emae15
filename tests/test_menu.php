<?php
declare(strict_types=1);
function db_fetch(string $s, array $p=[]): ?array { return null; }
function db_fetch_all(string $s, array $p=[]): array { return []; }
function db_execute(string $s, array $p=[]): void {}
function current_admin(): ?array { return null; }
function flash(string $a, string $b=''): string { return ''; }
function redirect_to(string $p): void {}
function client_ip(): string { return '0.0.0.0'; }
require dirname(__DIR__).'/includes/admin_team.php';

$pass=0;$fail=0;
function check(string $l, mixed $g, mixed $w): void { global $pass,$fail;
  if($g===$w){$pass++;echo "  ok   $l\n";}
  else{$fail++;echo "  FAIL $l\n        obtenu  ".var_export($g,true)."\n        attendu ".var_export($w,true)."\n";} }

echo "\n1. Tout écran d'administration est déclaré quelque part\n";
$declares = array_merge(
    admin_screen_files(),
    array_keys(admin_hidden_screens()),
    admin_super_only(),
    ['login.php','logout.php','zone_context.php','inline_save.php']
);
$sur_disque = array_map('basename', glob(dirname(__DIR__).'/admin/*.php'));
$orphelins  = array_values(array_diff($sur_disque, $declares));
check('aucun écran orphelin', $orphelins, []);

echo "\n2. Le menu est court et sans doublon\n";
$groupes = admin_screens();
check('5 rubriques + vue d\'ensemble', count($groupes), 5);
$liens = admin_screen_files();
check('liens du menu', count($liens), count(array_unique($liens)));
echo "  info  ".count($liens)." liens dans le menu (contre 28 écrans + 10 pages du catalogue avant)\n";

echo "\n3. Un écran masqué suit les droits de son parent\n";
$std = ['id'=>2,'role'=>'editor','permissions'=>json_encode(['zones.php','page_content.php'])];
check('zones.php autorisé',        admin_can_access('zones.php', $std), true);
check('zones_diag.php suit zones', admin_can_access('zones_diag.php', $std), true);
check('zone_edit.php suit zones',  admin_can_access('zone_edit.php', $std), true);
check('home_hero.php suit les textes', admin_can_access('home_hero.php', $std), true);
check('dossier.php refusé sans quotes.php', admin_can_access('dossier.php', $std), false);
check('reviews.php refusé',        admin_can_access('reviews.php', $std), false);
check('admins.php toujours refusé', admin_can_access('admins.php', $std), false);

$avecDevis = ['id'=>3,'role'=>'editor','permissions'=>json_encode(['quotes.php'])];
check('dossier.php suit les demandes', admin_can_access('dossier.php', $avecDevis), true);

echo "\n4. Un droit accordé avant le rangement reste valable\n";
$ancien = ['id'=>4,'role'=>'editor','permissions'=>json_encode(['zones_manager.php','why_us.php'])];
check('ancien droit zones_manager', admin_can_access('zones_manager.php', $ancien), true);
check('ancien droit why_us',        admin_can_access('why_us.php', $ancien), true);

echo "\n5. Le compte principal voit tout\n";
$sup = ['id'=>1,'role'=>'super','permissions'=>''];
foreach (['zones.php','zones_diag.php','dossier.php','admins.php','activity.php'] as $f) {
    check("super → $f", admin_can_access($f, $sup), true);
}

echo "\n6. Chaque écran masqué pointe vers un parent qui existe\n";
foreach (admin_hidden_screens() as $f => [$lib, $parent]) {
    check("$f → $parent", in_array($parent, admin_screen_files(), true), true);
    check("  libellé de $f", $lib !== '' && admin_screen_label($f) === $lib, true);
}

echo "\n".($fail===0 ? "TOUS LES TESTS PASSENT ($pass)" : "$fail ÉCHEC(S) sur ".($pass+$fail))."\n";
exit($fail===0?0:1);
