<?php
declare(strict_types=1);

$DB = ['settings'=>[], 'zones'=>[], 'admins'=>[], 'site_stats'=>[], 'site_events'=>[], 'admin_activity'=>[]];
$SESSION_ADMIN = 0;

function db_fetch_all(string $s, array $p=[]): array { global $DB;
  if (str_contains($s,'FROM settings')) { $o=[]; foreach($DB['settings'] as $k=>$v) $o[]=['setting_key'=>$k,'setting_value'=>$v]; return $o; }
  if (str_contains($s,'FROM admins'))   return array_values($DB['admins']);
  if (str_contains($s,'FROM admin_activity')) return array_values($DB['admin_activity']);
  return []; }
function db_fetch(string $s, array $p=[]): ?array { global $DB;
  if (str_contains($s,'FROM admins WHERE id')) return $DB['admins'][(int)$p[0]] ?? null;
  if (str_contains($s,'FROM admins WHERE email')) { foreach($DB['admins'] as $a) if($a['email']===$p[0]) return $a; return null; }
  if (str_contains($s,"COUNT(*) AS n FROM admins")) { $n=0; foreach($DB['admins'] as $a) if(($a['role']??'')==='super') $n++; return ['n'=>$n]; }
  return null; }
function db_execute(string $s, array $p=[]): void { global $DB;
  if (str_starts_with($s,'INSERT INTO admin_activity')) { $DB['admin_activity'][] = ['admin_id'=>$p[0],'admin_name'=>$p[1],'action'=>$p[2],'resource'=>$p[3]??'','detail'=>$p[4]??'','ip'=>$p[5]??'','created_at'=>date('Y-m-d H:i:s')]; return; }
  if (str_starts_with($s,'INSERT INTO site_stats'))  { $DB['site_stats'][]  = $p; return; }
  if (str_starts_with($s,'INSERT INTO site_events')) { $DB['site_events'][] = $p; return; } }

$_SERVER['SCRIPT_NAME']='/admin/index.php';
$_SERVER['REQUEST_METHOD']='GET';
$_SERVER['HTTP_USER_AGENT']='Mozilla/5.0 (iPhone)';
$R = dirname(__DIR__);
// On pilote la session depuis le test : auth.php n'est donc pas chargé.
function current_admin(): ?array { global $DB, $SESSION_ADMIN; return $DB['admins'][$SESSION_ADMIN] ?? null; }
function admin_logged_in(): bool { return current_admin() !== null; }

require $R.'/includes/helpers.php';
require $R.'/includes/admin_team.php';
require $R.'/includes/stats.php';

$pass=0;$fail=0;
function check(string $l,mixed $g,mixed $w): void { global $pass,$fail;
  if($g===$w){$pass++;echo "  ok   $l\n";}
  else{$fail++;echo "  FAIL $l\n        obtenu  ".var_export($g,true)."\n        attendu ".var_export($w,true)."\n";} }

$DB['admins'][1] = ['id'=>1,'name'=>'Patron','email'=>'p@x.fr','role'=>'super','permissions'=>null,'status'=>1];
$DB['admins'][2] = ['id'=>2,'name'=>'Marc','email'=>'m@x.fr','role'=>'admin','status'=>1,
                    'permissions'=>json_encode(['quotes.php','realisations.php'])];
$DB['admins'][3] = ['id'=>3,'name'=>'Sans droits','email'=>'s@x.fr','role'=>'admin','status'=>1,'permissions'=>'[]'];

echo "\n1. Le compte principal voit tout\n";
$SESSION_ADMIN = 1;
check('reconnu comme principal', admin_is_super(), true);
foreach (['quotes.php','seo.php','sms.php','admins.php','activity.php'] as $f)
    check("  accès à $f", admin_can_access($f), true);

echo "\n2. Un compte standard n'ouvre que ses écrans cochés\n";
$SESSION_ADMIN = 2;
check('pas principal',            admin_is_super(), false);
check('accès à Demandes',         admin_can_access('quotes.php'), true);
check('accès à Réalisations',     admin_can_access('realisations.php'), true);
check('SEO refusé',               admin_can_access('seo.php'), false);
check('SMS refusé',               admin_can_access('sms.php'), false);
check('Identité refusée',         admin_can_access('site_identity.php'), false);

echo "\n3. Les écrans du compte principal sont hors de portée\n";
check('gestion des comptes refusée', admin_can_access('admins.php'), false);
check('journal refusé',              admin_can_access('activity.php'), false);
check('  → même si cochés par erreur',
      (function(){ global $DB,$SESSION_ADMIN;
        $DB['admins'][2]['permissions'] = json_encode(['admins.php','activity.php','quotes.php']);
        $r = admin_can_access('admins.php');
        $DB['admins'][2]['permissions'] = json_encode(['quotes.php','realisations.php']);
        return $r; })(), false);

echo "\n4. Les écrans indispensables restent ouverts à tous\n";
$SESSION_ADMIN = 3;
check('aucun droit coché',     admin_permissions(current_admin()), []);
check('tableau de bord ouvert', admin_can_access('index.php'), true);
check('profil ouvert',          admin_can_access('profile.php'), true);
check('déconnexion ouverte',    admin_can_access('logout.php'), true);
check('tout le reste fermé',    admin_can_access('pages.php'), false);

echo "\n5. Sans session, rien n'est accessible\n";
$SESSION_ADMIN = 0;
check('non connecté',          admin_can_access('index.php'), false);
check('  ni les autres écrans', admin_can_access('quotes.php'), false);

echo "\n6. Le registre des écrans est cohérent\n";
$fichiers = admin_screen_files();
check('aucun doublon', count($fichiers), count(array_unique($fichiers)));
$manquants = array_values(array_filter($fichiers, fn($f) => !file_exists(dirname(__DIR__).'/admin/'.$f)));
check('chaque écran déclaré existe', $manquants, []);
check('les écrans réservés ne sont pas délégables',
      array_values(array_intersect(admin_super_only(), $fichiers)), []);
foreach (['admins.php','activity.php'] as $f) check("  « $f » existe", file_exists(dirname(__DIR__).'/admin/'.$f), true);

echo "\n7. Mesure d'audience : qui est compté\n";
$SESSION_ADMIN = 0;
check('un visiteur est compté', stats_should_count(), true);
$_SERVER['HTTP_USER_AGENT'] = 'Googlebot/2.1';
check('un robot ne l\'est pas', stats_should_count(), false);
$_SERVER['HTTP_USER_AGENT'] = '';
check('sans navigateur non plus', stats_should_count(), false);
$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (iPhone)';
$SESSION_ADMIN = 1;
check('vos propres visites non comptées', stats_should_count(), false);
$SESSION_ADMIN = 0;
$_SERVER['REQUEST_METHOD'] = 'POST';
check('un envoi de formulaire n\'est pas une page vue', stats_should_count(), false);
check('mais un évènement passe en POST',                stats_should_count(false), true);
$_SERVER['REQUEST_METHOD'] = 'GET';

echo "\n8. Les compteurs s'écrivent correctement\n";
$DB['site_stats'] = []; $DB['site_events'] = [];
stats_track_view('', 'paris-ile-de-france');
check('accueil de zone enregistré', $DB['site_stats'][0] ?? null, ['paris-ile-de-france','accueil']);
stats_track_view('zones');
check('page nommée par sa route',   $DB['site_stats'][1] ?? null, ['','zones']);
stats_track_event('appel', 'jura');
check('clic Appeler enregistré',    $DB['site_events'][0] ?? null, ['jura','appel']);
$DB['site_events'] = [];
stats_track_event('n<importe>quoi');
check('évènement inconnu ignoré',   $DB['site_events'], []);

echo "\n9. Les évolutions se calculent bien\n";
check('hausse',                 stats_evolution(118, 100), 18);
check('baisse',                 stats_evolution(80, 100), -20);
check('stable',                 stats_evolution(100, 100), 0);
check('sans base de comparaison', stats_evolution(50, 0), null);
check('libellé hausse',         stats_evolution_label(18), '+18 %');
check('libellé baisse',         stats_evolution_label(-4), '−4 %');
check('libellé stable',         stats_evolution_label(0), 'stable');
check('libellé absent',         stats_evolution_label(null), '');

echo "\n10. Le journal enregistre qui a fait quoi\n";
$SESSION_ADMIN = 2;
$DB['admin_activity'] = [];
admin_log('modification', "Page d'accueil", '4 textes');
$e = $DB['admin_activity'][0] ?? [];
check('une seule ligne',   count($DB['admin_activity']), 1);
check('  → la bonne personne', $e['admin_name'] ?? '', 'Marc');
check('  → l\'action',         $e['action'] ?? '', 'modification');
check('  → la ressource',      $e['resource'] ?? '', "Page d'accueil");
check('  → le détail groupé',  $e['detail'] ?? '', '4 textes');
$SESSION_ADMIN = 0;
admin_log('modification', 'x');
check('rien sans session connectée', count($DB['admin_activity']), 1);

echo "\n".($fail===0 ? "TOUS LES TESTS PASSENT ($pass)" : "$fail ÉCHEC(S) sur ".($pass+$fail))."\n";
exit($fail===0?0:1);
