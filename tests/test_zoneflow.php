<?php
declare(strict_types=1);

$DB = ['settings'=>[], 'zones'=>[
    1 => ['id'=>1,'slug'=>'paris-ile-de-france','name'=>'Paris / Île-de-France','status'=>1,'sort_order'=>0],
    2 => ['id'=>2,'slug'=>'jura','name'=>'Jura','status'=>1,'sort_order'=>1],
    3 => ['id'=>3,'slug'=>'doubs','name'=>'Doubs','status'=>1,'sort_order'=>2],
]];
function db_fetch_all(string $s, array $p=[]): array { global $DB;
  if (str_contains($s,'FROM settings')) { $o=[]; foreach($DB['settings'] as $k=>$v) $o[]=['setting_key'=>$k,'setting_value'=>$v]; return $o; }
  if (str_contains($s,'FROM zones')) return array_values($DB['zones']);
  return []; }
function db_fetch(string $s, array $p=[]): ?array { global $DB;
  if (str_contains($s,'FROM settings')) return isset($DB['settings'][$p[0]]) ? ['id'=>1,'setting_key'=>$p[0],'setting_value'=>$DB['settings'][$p[0]]] : null;
  if (str_contains($s,'FROM zones')) { foreach($DB['zones'] as $z) if ((string)$z['id']===(string)$p[0]||$z['slug']===$p[0]) return $z; }
  return null; }
function db_execute(string $s, array $p=[]): void { global $DB;
  if (str_starts_with($s,'INSERT INTO settings')) { $DB['settings'][$p[0]]=$p[1]; return; }
  if (str_starts_with($s,'UPDATE settings SET setting_key')) { $DB['settings'][$p[0]] = $DB['settings'][$p[1]] ?? ''; unset($DB['settings'][$p[1]]); return; }
  if (str_starts_with($s,'UPDATE settings'))      { $DB['settings'][$p[1]]=$p[0]; return; }
  if (str_starts_with($s,'DELETE FROM settings')) { unset($DB['settings'][$p[0]]); return; }
  if (str_starts_with($s,'DELETE FROM zones')) { unset($DB['zones'][(int)$p[0]]); return; }
  if (preg_match('/^UPDATE (pages|realisations|reviews) SET zone_id = NULL/', $s, $m)) { $DB['detaches'][] = $m[1]; return; }
  if (str_starts_with($s,'UPDATE zones SET slug')) { $DB['zones'][(int)end($p)]['slug'] = $p[0]; $DB['zones'][(int)end($p)]['name'] = $p[1]; return; } }

$_SERVER['REQUEST_METHOD']='GET';
$R = dirname(__DIR__);
require $R.'/includes/helpers.php';
require $R.'/includes/admin_fields.php';

$pass=0;$fail=0;
function check(string $l,mixed $g,mixed $w): void { global $pass,$fail;
  if($g===$w){$pass++;echo "  ok   $l\n";}
  else{$fail++;echo "  FAIL $l\n        obtenu  ".var_export($g,true)."\n        attendu ".var_export($w,true)."\n";} }

/** Reproduit exactement le bloc « contexte zone de l'admin » de bootstrap.php. */
function bootstrap_admin_zone(string $script, array $get, array &$session): void {
    set_zone_context(null);
    if (!str_contains(str_replace('\\','/',$script), '/admin/')) return;
    if (isset($get['admin_zone'])) $session['admin_zone_id'] = max(0, (int)$get['admin_zone']);
    $id = (int)($session['admin_zone_id'] ?? 0);
    if ($id > 0) {
        $z = get_zone_by_id($id);
        if ($z) set_zone_context($z); else $session['admin_zone_id'] = 0;
    }
}

/** Reproduit le bloc de détection de zone en tête d'index.php. */
function frontend_route(string $uri): array {
    $route = '';
    $zone  = null;
    $u = ltrim(rtrim((string)parse_url($uri, PHP_URL_PATH), '/'), '/');
    $segs = array_values(array_filter(explode('/', $u)));
    $s0 = $segs[0] ?? '';
    if ($s0 !== '' && !in_array($s0, ['admin','tech','dispatcher','api','assets','includes','config','storage','index.php'], true)
        && preg_match('/^[a-z][a-z0-9-]{1,78}$/', $s0)) {
        $z = get_zone_by_slug($s0);
        if ($z && (bool)$z['status']) { $zone = $z; if (isset($segs[1])) $route = trim($segs[1]); }
    }
    set_zone_context($zone);
    return [$zone['slug'] ?? null, $route];
}

$session = [];
$champ = admin_catalog_fields('accueil')['home_title'];

echo "\n1. Le bandeau bascule bien le contexte\n";
bootstrap_admin_zone('/admin/index.php', ['admin_zone'=>'2'], $session);
check('clic sur la puce Jura',     zone_ctx_slug(), 'jura');
check('  → mémorisé en session',   $session['admin_zone_id'] ?? null, 2);
bootstrap_admin_zone('/admin/page_content.php', ['p'=>'accueil'], $session);
check('conservé en changeant d\'écran', zone_ctx_slug(), 'jura');
bootstrap_admin_zone('/admin/index.php', ['admin_zone'=>'0'], $session);
check('retour au global',          zone_ctx_slug(), '');

echo "\n2. Enregistrer dans le Jura n'écrit que dans le Jura\n";
bootstrap_admin_zone('/admin/page_content.php', ['p'=>'accueil','admin_zone'=>'2'], $session);
check('contexte Jura actif', zone_ctx_slug(), 'jura');
admin_field_save($champ, 'Votre électricien dans le Jura');
settings_cache(true);
check('clé écrite préfixée',   array_keys($DB['settings']), ['z:jura:home_title']);
check('  → rien en global',    isset($DB['settings']['home_title']), false);

echo "\n3. Chaque zone garde son propre titre\n";
bootstrap_admin_zone('/admin/page_content.php', ['admin_zone'=>'1'], $session);
admin_field_save($champ, "Votre électricien en Île-de-France");
bootstrap_admin_zone('/admin/page_content.php', ['admin_zone'=>'3'], $session);
admin_field_save($champ, 'Votre électricien dans le Doubs');
settings_cache(true);
check('trois clés distinctes', count($DB['settings']), 3);
foreach ([['jura','Votre électricien dans le Jura'],
          ['paris-ile-de-france',"Votre électricien en Île-de-France"],
          ['doubs','Votre électricien dans le Doubs']] as [$slug,$attendu]) {
    set_zone_context(get_zone_by_slug($slug));
    check("  $slug affiche le sien", setting('home_title','defaut'), $attendu);
}
set_zone_context(null);
check('le global garde son texte d\'origine', setting('home_title','Votre expert multitechnique'), 'Votre expert multitechnique');

echo "\n4. Côté site, chaque adresse montre sa page d'accueil\n";
foreach ([['/jura/','jura','Votre électricien dans le Jura'],
          ['/paris-ile-de-france/','paris-ile-de-france',"Votre électricien en Île-de-France"],
          ['/doubs/','doubs','Votre électricien dans le Doubs']] as [$uri,$slug,$attendu]) {
    [$z,$r] = frontend_route($uri);
    check("$uri → zone $slug", $z, $slug);
    check("  → son propre titre", setting('home_title','Votre expert multitechnique'), $attendu);
}
[$z,$r] = frontend_route('/');
check('/ → aucune zone',        $z, null);
check('  → titre global',       setting('home_title','Votre expert multitechnique'), 'Votre expert multitechnique');

echo "\n5. Une zone sans personnalisation hérite, sans contaminer les autres\n";
$DB['settings'] = []; settings_cache(true);
bootstrap_admin_zone('/admin/page_content.php', ['admin_zone'=>'2'], $session);
admin_field_save(admin_catalog_fields('accueil')['home_lead'], 'Accroche du Jura');
settings_cache(true);
frontend_route('/jura/');
check('le Jura a son accroche',  setting('home_lead','accroche globale'), 'Accroche du Jura');
frontend_route('/doubs/');
check('le Doubs hérite',         setting('home_lead','accroche globale'), 'accroche globale');
frontend_route('/');
check('le global inchangé',      setting('home_lead','accroche globale'), 'accroche globale');

echo "\n6. Le piège : oublier de sélectionner une zone\n";
$session = [];
bootstrap_admin_zone('/admin/page_content.php', ['p'=>'accueil'], $session);
check('aucune zone en session → global', zone_ctx_slug(), '');
admin_field_save($champ, 'Titre écrit sans zone');
settings_cache(true);
check('  → écrit bien en global', $DB['settings']['home_title'] ?? null, 'Titre écrit sans zone');
frontend_route('/jura/');
check('  → et le Jura le reprend', setting('home_title','x'), 'Titre écrit sans zone');

echo "\n7. Après « Dupliquer depuis Global » : la zone cesse d'hériter\n";
$DB['settings'] = []; settings_cache(true);
bootstrap_admin_zone('/admin/x.php', ['admin_zone'=>'0'], $session);
set_setting('home_title', 'Titre global');
set_setting('home_lead',  'Accroche globale');
set_setting('company_regions', 'Bourgogne');
settings_cache(true);

bootstrap_admin_zone('/admin/x.php', ['admin_zone'=>'2'], $session);
check('duplication : 3 textes copiés', zone_copy_from_global(), 3);
settings_cache(true);
check('la zone a tout en propre',      zone_override_count('jura'), 3);
check('  → mais rien ne diffère',      zone_real_differences('jura'), 0);
frontend_route('/jura/');
check('  → elle ressemble au global',  setting('home_title','x'), 'Titre global');

// Le piège : une correction globale n'atteint plus la zone.
bootstrap_admin_zone('/admin/x.php', ['admin_zone'=>'0'], $session);
set_setting('home_lead', 'Nouvelle accroche globale');
settings_cache(true);
frontend_route('/jura/');
check('la zone ignore la correction globale', setting('home_lead','x'), 'Accroche globale');

echo "\n8. Le nettoyage rétablit l'héritage sans perdre les vraies différences\n";
// État net : 3 textes en global, la zone en détient une copie conforme.
$DB['settings'] = []; settings_cache(true);
bootstrap_admin_zone('/admin/x.php', ['admin_zone'=>'0'], $session);
set_setting('home_title', 'Titre global');
set_setting('home_lead',  'Accroche globale');
set_setting('company_regions', 'Bourgogne');
settings_cache(true);
bootstrap_admin_zone('/admin/x.php', ['admin_zone'=>'2'], $session);
zone_copy_from_global();
settings_cache(true);

admin_field_save($champ, 'Titre du Jura');          // une seule vraie différence
settings_cache(true);
check('3 textes enregistrés',   zone_override_count('jura'), 3);
check('dont 1 vraie différence', zone_real_differences('jura'), 1);
check('nettoyage : 2 copies retirées', zone_prune_identical('jura'), 2);
settings_cache(true);
check('  → il ne reste que la différence', zone_override_count('jura'), 1);

// Le global peut de nouveau atteindre la zone.
bootstrap_admin_zone('/admin/x.php', ['admin_zone'=>'0'], $session);
set_setting('home_lead', 'Nouvelle accroche globale');
settings_cache(true);
frontend_route('/jura/');
check('le titre propre est conservé',  setting('home_title','x'), 'Titre du Jura');
check('l\'accroche hérite de nouveau', setting('home_lead','x'), 'Nouvelle accroche globale');
frontend_route('/');
check('le global est intact',          setting('home_title','x'), 'Titre global');
check('nettoyer deux fois ne fait rien', zone_prune_identical('jura'), 0);
check('les autres zones non touchées',   zone_override_count('doubs'), 0);

echo "\n9. Renommer l'adresse d'une zone emmène ses textes\n";
$DB['settings'] = []; $DB['detaches'] = []; settings_cache(true);
bootstrap_admin_zone('/admin/x.php', ['admin_zone'=>'2'], $session);
admin_field_save($champ, 'Titre du Jura');
set_setting('company_regions', 'Jura');
settings_cache(true);
check('2 textes sous l\'ancienne adresse', zone_override_count('jura'), 2);

update_zone(2, ['slug'=>'jura-franche-comte','name'=>'Jura','status'=>1,'sort_order'=>0]);
settings_cache(true);
check('plus rien sous l\'ancienne',  zone_override_count('jura'), 0);
check('tout sous la nouvelle',        zone_override_count('jura-franche-comte'), 2);
frontend_route('/jura-franche-comte/');
check('la nouvelle adresse affiche les textes', setting('home_title','x'), 'Titre du Jura');

echo "\n10. Renommer seulement le nom ne touche à rien\n";
update_zone(2, ['slug'=>'jura-franche-comte','name'=>'Jura & Franche-Comté','status'=>1,'sort_order'=>0]);
settings_cache(true);
check('les textes restent en place', zone_override_count('jura-franche-comte'), 2);
check('le nom est changé',           get_zone_by_id(2)['name'] ?? '', 'Jura & Franche-Comté');

echo "\n11. Supprimer une zone ne laisse rien derrière\n";
$DB['detaches'] = [];
delete_zone(2);
settings_cache(true);
check('ses textes sont supprimés',    zone_override_count('jura-franche-comte'), 0);
check('la zone n\'existe plus',       get_zone_by_id(2), null);
check('les contenus sont détachés',   $DB['detaches'], ['pages','realisations','reviews']);
check('les autres zones intactes',    get_zone_by_slug('doubs')['name'] ?? '', 'Doubs');

echo "\n".($fail===0 ? "TOUS LES TESTS PASSENT ($pass)" : "$fail ÉCHEC(S) sur ".($pass+$fail))."\n";
exit($fail===0?0:1);
