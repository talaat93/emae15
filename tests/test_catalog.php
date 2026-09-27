<?php
declare(strict_types=1);

$DB = ['settings' => [], 'zones' => []];
function db_fetch_all(string $s, array $p = []): array { global $DB;
    if (str_contains($s,'FROM settings')) return array_values($DB['settings']);
    if (str_contains($s,'FROM zones')) return array_values($DB['zones']);
    return []; }
function db_fetch(string $s, array $p = []): ?array { global $DB;
    if (str_contains($s,'FROM settings')) { foreach ($DB['settings'] as $r) if ($r['setting_key']===$p[0]) return $r; }
    return null; }
function db_execute(string $s, array $p = []): void { global $DB;
    if (str_starts_with($s,'INSERT INTO settings')) $DB['settings'][$p[0]] = ['setting_key'=>$p[0],'setting_value'=>$p[1]];
    if (str_starts_with($s,'UPDATE settings')) $DB['settings'][$p[1]] = ['setting_key'=>$p[1],'setting_value'=>$p[0]];
    if (str_starts_with($s,'DELETE FROM settings')) unset($DB['settings'][$p[0]]); }

$_SERVER['SCRIPT_NAME'] = '/admin/page_content.php';
$ROOT = dirname(__DIR__);
require $ROOT.'/includes/helpers.php';
require $ROOT.'/includes/admin_fields.php';

$pass=0; $fail=0;
function check(string $l, mixed $g, mixed $w): void { global $pass,$fail;
    if ($g===$w) { $pass++; echo "  ok   $l\n"; }
    else { $fail++; echo "  FAIL $l — obtenu ".var_export($g,true)." / attendu ".var_export($w,true)."\n"; } }

// Tout le code qui rend le site public.
$public = '';
foreach (['index.php','includes/render.php','includes/helpers.php'] as $f) $public .= file_get_contents($ROOT.'/'.$f);

echo "\n1. Aucun champ fantôme sur l'ensemble du site\n";
$ghosts = [];
foreach (admin_page_catalog() as $pid => $page) {
    foreach ($page['sections'] as $s) foreach ($s['fields'] as $f) {
        if (!empty($f['json'])) {
            [$blob,$sub] = $f['json'];
            // le bloc doit être lu, et la sous-clé utilisée au rendu
            if (!str_contains($public, "get_json_setting('".$blob."'")) $ghosts[] = $pid.'/'.$f['key'].' (bloc '.$blob.' jamais lu)';
            elseif (!preg_match("/'".preg_quote($sub,'/')."'\s*=>/", $public)) $ghosts[] = $pid.'/'.$f['key'].' (sous-clé '.$sub.' inconnue)';
        } elseif (str_starts_with($f['key'], 'svc_') && str_starts_with($pid, 'metier_')) {
            // clé construite à l'exécution : on vérifie que service_template() la consomme vraiment
            $trade = substr($pid, 7);
            $suf   = substr($f['key'], strlen('svc_'.$trade.'_'));
            if (!array_key_exists($suf, service_template($trade) ?? [])) $ghosts[] = $pid.'/'.$f['key'];
        } elseif (!str_contains($public, "setting('".$f['key']."'")) {
            $ghosts[] = $pid.'/'.$f['key'];
        }
    }
}
check('zéro champ sans effet', $ghosts, []);

echo "\n2. Page d'accueil : aucun texte oublié\n";
// Les bornes se déduisent du code, pas de numéros de ligne figés : un simple
// ajout ailleurs dans le fichier masquerait sinon un texte oublié.
$src   = file_get_contents($ROOT.'/index.php');
$debut = strpos($src, "if (\$route === '' || \$route === 'home') {");
$fin   = strpos($src, "if (\$route === 'zones') {");
if ($debut === false || $fin === false || $fin <= $debut) { echo "  FAIL bornes de la page d'accueil introuvables\n"; $fail++; }
$readByHome = [];
if (preg_match_all("/setting\('([a-z0-9_]+)'/", substr($src, (int)$debut, (int)$fin - (int)$debut), $m)) {
    foreach ($m[1] as $k) $readByHome[$k] = true;
}
// pilotés par l'écran SEO, pas par l'accueil
$expected = array_values(array_diff(array_keys($readByHome), ['schema_rating_value','schema_review_count']));
check('zéro texte non éditable', array_values(array_diff($expected, admin_catalog_keys('accueil'))), []);

echo "\n3. « Pourquoi nous choisir » est bien couvert\n";
$acc = admin_catalog_keys('accueil');
foreach (['why_eyebrow','why_title','why_lead'] as $k) check("« $k » présent", in_array($k,$acc,true), true);
$secIds = array_column(admin_catalog_page('accueil')['sections'], 'id');
check('placé entre Services et Comment ça marche',
      array_search('why',$secIds,true) === array_search('services',$secIds,true)+1
   && array_search('process',$secIds,true) === array_search('why',$secIds,true)+1, true);

echo "\n4. Toutes les pages du site sont couvertes\n";
$pages = array_keys(admin_page_catalog());
foreach (['accueil','entete','page_zones','page_avis','page_faq','page_contact','page_devis','page_realisations','page_services','page_mentions'] as $p) {
    check("page « $p »", in_array($p,$pages,true), true);
}

echo "\n5. Intégrité du catalogue\n";
$seen=[]; $dupes=[]; $bad=[]; $total=0;
foreach (admin_page_catalog() as $pid=>$page) {
    if (!isset($page['label'],$page['icon'],$page['sections'])) $bad[] = "$pid: en-tête incomplet";
    foreach ($page['sections'] as $s) {
        if (trim((string)($s['label'] ?? ''))==='' || trim((string)($s['id'] ?? ''))==='') $bad[] = "$pid: section sans nom";
        foreach ($s['fields'] as $f) {
            $total++;
            if (isset($seen[$f['key']])) $dupes[] = $f['key'];
            $seen[$f['key']] = true;
            if (trim((string)($f['label'] ?? ''))==='') $bad[] = $f['key'].': sans intitulé';
            if (!in_array($f['type'] ?? '', ['text','textarea','list'], true)) $bad[] = $f['key'].': type invalide';
        }
    }
}
check('aucune clé en double', $dupes, []);
check('aucune anomalie de structure', $bad, []);
echo "  info  $total champs éditables sur ".count($pages)." pages\n";

echo "\n6. La recherche couvre tout le site\n";
check('« un seul interlocuteur »', admin_search_fields('un seul interlocuteur')[0]['key'] ?? '', 'services_lead');
check('« Tout ce dont vous avez »', admin_search_fields('Tout ce dont vous avez')[0]['key'] ?? '', 'services_title');
check('« artisans qualifiés » (bloc JSON)', admin_search_fields('délais respectés')[0]['key'] ?? '', 'why_lead');
check('« o2switch » (mentions légales)', admin_search_fields('o2switch')[0]['key'] ?? '', 'ml_hebergeur');
$ce = admin_search_fields('chauffe-eau');
check('« chauffe-eau » trouvé côté plomberie', str_contains($ce[0]['key'] ?? '', 'svc_plomberie_'), true);
check('  → dans la page du métier',            $ce[0]['page_id'] ?? '', 'metier_plomberie');
check('recherche trop courte ignorée', admin_search_fields('a'), []);

echo "\n7. Enregistrement d'un champ simple\n";
$f = admin_catalog_fields('accueil')['services_title'];
check('rien de saisi au départ', admin_field_raw($f), '');
check('le site montre le défaut', admin_field_shown($f), 'Tout ce dont vous avez');
check('la saisie est enregistrée', admin_field_save($f,'Nos prestations'), true);
check('valeur relue', admin_field_raw($f), 'Nos prestations');
check('sauvegarde inutile évitée', admin_field_save($f,'Nos prestations'), false);
admin_field_save($f,'');
check('vidé → retour au défaut', admin_field_shown($f), 'Tout ce dont vous avez');

echo "\n8. Enregistrement dans un bloc JSON (Pourquoi nous choisir)\n";
$fw = admin_catalog_fields('accueil')['why_title'];
check('le site montre le défaut', admin_field_shown($fw), 'EMAE, votre expert multitechnique de confiance');
admin_field_save($fw, 'Pourquoi EMAE');
check('valeur enregistrée', admin_field_raw($fw), 'Pourquoi EMAE');
check('le site la reprend', why_us_settings()['title'], 'Pourquoi EMAE');
check('les 8 arguments sont préservés', count(why_us_settings()['items']), 8);
$fe = admin_catalog_fields('accueil')['why_eyebrow'];
check('le surtitre voisin reste au défaut', admin_field_shown($fe), 'Pourquoi nous choisir');
admin_field_save($fw, '');
check('vidé → retour au défaut', why_us_settings()['title'], 'EMAE, votre expert multitechnique de confiance');
check('arguments toujours intacts', count(why_us_settings()['items']), 8);

echo "\n9. Mécanique des listes (ajout, suppression, ordre)\n";
$fb = admin_catalog_fields('metier_electricite')['svc_electricite_badges'];
check('liste au départ : celle d\'origine', admin_list_rows($fb), ['Dépannage urgent','Tableau électrique','Mise aux normes NF C 15-100','Rénovation']);
check('rien d\'enregistré',                  admin_list_stored($fb), null);

// ce que le formulaire renvoie : lignes numérotées, une colonne
check('enregistrement de 2 lignes', admin_list_save($fb, [0=>['v'=>'Urgence'],1=>['v'=>'Tableau']]), true);
check('relecture', admin_list_rows($fb), ['Urgence','Tableau']);
check('sauvegarde inutile évitée', admin_list_save($fb, [0=>['v'=>'Urgence'],1=>['v'=>'Tableau']]), false);

check('lignes vides ignorées', admin_list_save($fb, [0=>['v'=>'Seule'],1=>['v'=>'  '],2=>['v'=>'']]), true);
check('  → une seule conservée', admin_list_rows($fb), ['Seule']);

check('ordre respecté', admin_list_save($fb, [0=>['v'=>'B'],1=>['v'=>'A'],2=>['v'=>'C']]), true);
check('  → ordre du formulaire', admin_list_rows($fb), ['B','A','C']);

admin_list_save($fb, []);
check('liste vidée → retour à l\'origine', admin_list_rows($fb), ['Dépannage urgent','Tableau électrique','Mise aux normes NF C 15-100','Rénovation']);

echo "\n10. Listes à plusieurs colonnes\n";
$fi = admin_catalog_fields('metier_chauffage')['svc_chauffage_interv'];
check('3 colonnes déclarées', count(admin_list_columns($fi)), 3);
admin_list_save($fi, [0=>['icon'=>'🔥','title'=>'Panne','text'=>'Diagnostic.'],
                      1=>['icon'=>'','title'=>'','text'=>'']]);
check('ligne vide écartée',   count(admin_list_rows($fi)), 1);
check('colonnes dans l\'ordre', admin_list_rows($fi)[0], ['🔥','Panne','Diagnostic.']);
check('le site reçoit la même chose', service_template('chauffage')['interv'][0], ['🔥','Panne','Diagnostic.']);

echo "\n11. Les listes ne sont pas éditables sur la page\n";
check('liste écartée de l\'édition en place', isset(admin_inline_keys()['svc_chauffage_interv']), false);
check('mais trouvable par la recherche', str_contains(admin_field_shown($fi), 'Diagnostic.'), true);

echo "\n".($fail===0 ? "TOUS LES TESTS PASSENT ($pass)" : "$fail ÉCHEC(S) sur ".($pass+$fail))."\n";
exit($fail===0?0:1);
