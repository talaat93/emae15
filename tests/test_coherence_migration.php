<?php
declare(strict_types=1);
/*
 * Mise en cohérence des contenus (migration v15.38) rejouée sur une base qui reproduit
 * ce qu'affichait le site en ligne le 10/11/2026 : arguments Île-de-France et Toulouse,
 * FAQ francilienne, note sans source, « 1000+ », délais chiffrés, « qasd », adresse en
 * liste de départements. Vérifie le résultat, puis l'annulation complète.
 */
$DB = ['settings' => [
    'company_name' => 'EMAE',
    'company_address' => '75-77-78-91-92-93-94-95',
    'ml_directeur' => 'EMAE',
    'schema_rating_value' => '4.6',
    'schema_review_count' => '128',
    'why_us_settings' => '{"items":[{"icon":"📍","title":"Présence locale","text":"Plusieurs équipes réparties en Île-de-France pour une arrivée en moins de 90 min sur vos urgences."},{"icon":"📋","title":"Devis gratuit et clair","text":"Le prix est annoncé avant toute intervention."},{"icon":"⭐","title":"Clients satisfaits","text":"Plus de 120 avis vérifiés, note de 4.9/5."}]}',
    'z:jura:why_us_settings' => '{"items":[{"icon":"📍","title":"Présence locale","text":"Nous couvrons toute la région Côte-d\'Or, notamment la Côte-d\'Or, Tarn et Aude, avec une présence sur les grandes villes comme Toulouse, Blagnac, Muret Albi, Carcassonne, Béziers ,Limoux."},{"icon":"🛠️","title":"Tous types d\'interventions","text":"Urgence, installation, entretien."}]}',
    'faq_page_settings' => '{"hero_title":"Toutes vos questions","groups":[{"category":"Général","items":[{"q":"Quelles sont vos zones d\'intervention ?","a":"Nous intervenons dans les départements du Paris (75) | Seine-et-Marne (77) | Essonne (91), notamment à Paris | Meaux | Melun."},{"q":"Quels sont vos horaires ?","a":"EMAE est disponible 24h/24, 7j/7 pour les urgences. Pour les interventions planifiées, nous proposons des créneaux du lundi au samedi de 8h à 19h."},{"q":"Quel est le délai d\'intervention en urgence ?","a":"Notre délai moyen d\'intervention urgence est de moins d\' 90 min à 90 min selon votre zone."},{"q":"Vos devis sont-ils gratuits ?","a":"Oui, l\'établissement d\'un devis est entièrement gratuit."}]}]}',
    'stat_1_number' => '1000+',
    'stat_1_label' => 'Interventions/an',
    'stat_4_number' => '24/7',
    'stat_4_label' => 'Disponibilité',
    'trust_3_title' => '4.3',
    'trust_3_sub' => '128 avis',
    'qs_lead' => 'Décrivez votre besoin, nous vous rappelons sous 10 minutes avec un chiffrage clair.',
    'qs_perk_2' => 'Rappel sous 10 minutes garanti',
    'process_1_text' => 'Appelez ou remplissez le formulaire. Nous répondons immédiatement et qualifions votre besoin en moins de 5 minutes.',
    'home_title' => 'Votre expert multitechnique',
    'svc_plomberie_badges' => '["Fuite urgente","Débouchage","Sanitaires","Entretien réseau","qasd"]',
    'zvar_ville' => 'Quetigny , Dijon et Genlis',
    'zvar_en_ville' => 'à Quetigny , Dijon et Genlis',
    'avail_open_text' => 'Technicien disponible aujourd\'hui — intervention rapide',
    'z:doubs:qs_lead' => 'Plusieurs équipes réparties en Île-de-France pour une arrivée en moins de 90 min.',
    'zvar_region' => 'Doubs , Côte-d\'Or, Jura',
    'zvar_en_region' => 'dans le Doubs , la Côte-d\'Or, le Jura et les alentours ',
    'home_lead' => 'Électricité, plomberie, chauffage, climatisation et pompes à chaleur dans le Doubs , la Côte-d\'Or, le Jura et les alentours . Intervention rapide autour de Quetigny , Dijon et Genlis , devis gratuit, artisans qualifiés.',
    'home_title_hl' => 'en Île-de-France sur mesure',
], 'zones' => [
    1 => ['id'=>1,'slug'=>'idf','name'=>'Bourgogne-Franche-Comté','status'=>1,'sort_order'=>0,'postal_codes'=>'','depts'=>'','cities'=>'','delay'=>'','meta_title'=>'Electricien Paris','hero_h1'=>null],
    2 => ['id'=>2,'slug'=>'jura','name'=>'Jura','status'=>1,'sort_order'=>1,'postal_codes'=>'39','depts'=>'Jura (39)','cities'=>'Dole|Tavaux','delay'=>"Moins d'1 h à Dole, sous 2 h ailleurs",'meta_title'=>null],
    3 => ['id'=>3,'slug'=>'doubs','name'=>'Doubs','status'=>1,'sort_order'=>2,'postal_codes'=>'25','depts'=>'Doubs (25)','cities'=>'Besançon|Pontarlier','delay'=>'1 h à 1 h 30 en urgence','meta_title'=>null],
    4 => ['id'=>4,'slug'=>'cote-d-or','name'=>"Côte-d'Or",'status'=>1,'sort_order'=>3,'postal_codes'=>'21','depts'=>"Côte-d'Or (21)",'cities'=>'Dijon|Quetigny','delay'=>'','meta_title'=>null],
], 'geo_sauvegarde' => []];
$DEPART = $DB;

function db_fetch_all(string $s, array $p = []): array { global $DB;
    if (str_contains($s, 'FROM settings')) { $o = []; foreach ($DB['settings'] as $k => $v) $o[] = ['setting_key'=>$k,'setting_value'=>$v]; return $o; }
    if (str_contains($s, 'FROM zones')) {
        $z = array_values($DB['zones']);
        return str_contains($s, 'status = 1') ? array_values(array_filter($z, fn($r) => (int)$r['status'] === 1)) : $z;
    }
    if (str_contains($s, 'FROM geo_sauvegarde')) {
        return array_reverse(array_values(array_filter($DB['geo_sauvegarde'], fn($r) => $r['lot'] === $p[0])));
    }
    return []; }
function db_fetch(string $s, array $p = []): ?array { global $DB;
    if (str_contains($s, 'FROM settings')) return isset($DB['settings'][$p[0]]) ? ['id'=>1,'setting_value'=>$DB['settings'][$p[0]]] : null;
    if (str_contains($s, 'FROM zones')) { foreach ($DB['zones'] as $z) if ((string)$z['id'] === (string)$p[0] || $z['slug'] === $p[0]) return $z; }
    return null; }
function db_execute(string $s, array $p = []): void { global $DB;
    if (str_starts_with($s, 'INSERT INTO settings'))   { $DB['settings'][$p[0]] = $p[1]; return; }
    if (str_starts_with($s, 'UPDATE settings'))        { $DB['settings'][$p[1]] = $p[0]; return; }
    if (str_starts_with($s, 'DELETE FROM settings'))   { unset($DB['settings'][$p[0]]); return; }
    if (str_starts_with($s, 'INSERT INTO geo_sauvegarde')) {
        $DB['geo_sauvegarde'][] = ['id'=>count($DB['geo_sauvegarde'])+1,'lot'=>$p[0],'cible'=>$p[1],'ancienne'=>$p[2]]; return; }
    if (str_starts_with($s, 'DELETE FROM geo_sauvegarde')) {
        $DB['geo_sauvegarde'] = array_values(array_filter($DB['geo_sauvegarde'], fn($r) => $r['lot'] !== $p[0])); return; }
    if (preg_match('/^UPDATE zones SET `(\w+)` = (NULL|\?) WHERE id = \?/', $s, $m)) {
        $id = (int)end($p);
        $DB['zones'][$id][$m[1]] = $m[2] === 'NULL' ? null : $p[0];
    } }

$_SERVER['SCRIPT_NAME'] = '/admin/zones_diag.php';
$R = dirname(__DIR__);
require $R.'/includes/helpers.php';
require $R.'/includes/zone_vars.php';
require $R.'/includes/zones_core.php';
require $R.'/includes/admin_fields.php';
require $R.'/includes/admin_replace.php';
require $R.'/includes/geo_secteurs.php';
require $R.'/includes/growth.php';
require $R.'/includes/coherence.php';

$pass = 0; $fail = 0;
function check(string $l, mixed $g, mixed $w): void { global $pass, $fail;
    if ($g === $w) { $pass++; echo "  ok   $l\n"; }
    else { $fail++; echo "  FAIL $l\n        obtenu  ".var_export($g, true)."\n        attendu ".var_export($w, true)."\n"; } }
$S = fn(string $k): ?string => $GLOBALS['DB']['settings'][$k] ?? null;

echo "\n1. Mise en cohérence\n";
$b = coherence_appliquer();
settings_cache(true);
$why = json_decode((string)$S('why_us_settings'), true);
check('accueil : argument « équipes en Île-de-France » retiré', str_contains((string)$S('why_us_settings'), 'Île-de-France'), false);
check('accueil : argument « 4.9/5 » retiré', str_contains((string)$S('why_us_settings'), '4.9'), false);
check('accueil : argument neutre conservé', array_column($why['items'] ?? [], 'title'), ['Devis gratuit et clair']);
check('Jura : bloc Toulouse / Tarn / Aude retiré', str_contains((string)$S('z:jura:why_us_settings'), 'Toulouse'), false);
check('Jura : autre argument conservé', str_contains((string)$S('z:jura:why_us_settings'), 'Tous types'), true);
$faq = json_decode((string)$S('faq_page_settings'), true)['groups'][0]['items'];
check('FAQ : plus de liste francilienne', str_contains($faq[0]['a'], 'Seine-et-Marne') || str_contains($faq[0]['a'], 'Meaux'), false);
check('FAQ : horaires = phrase unique', $faq[1]['a'], hours_text());
check('FAQ : délai chiffré retiré', str_contains($faq[2]['a'], '90 min'), false);
check('FAQ : réponse sans problème intacte', $faq[3]['a'], "Oui, l'établissement d'un devis est entièrement gratuit.");
check('« 1000+ » retiré (chiffre et libellé)', [$S('stat_1_number'), $S('stat_1_label')], [null, null]);
check('« 24/7 » conservé', $S('stat_4_number'), '24/7');
check('note saisie sans source retirée', [$S('trust_3_title'), $S('trust_3_sub'), $S('schema_rating_value'), $S('schema_review_count')], [null, null, null, null]);
check('« sous 10 minutes » retiré', [$S('qs_lead'), $S('qs_perk_2')], [null, null]);
check('« répondons… en moins de 5 minutes » gardé : c\'est le délai de rappel réglé', $S('process_1_text'), 'Appelez ou remplissez le formulaire. Nous répondons immédiatement et qualifions votre besoin en moins de 5 minutes.');
check('zone Doubs : texte « Île-de-France » retiré', $S('z:doubs:qs_lead'), null);
check('« qasd » retiré des étiquettes Plomberie', json_decode((string)$S('svc_plomberie_badges'), true), ['Fuite urgente', 'Débouchage', 'Sanitaires', 'Entretien réseau']);
check('variables du site global gardées (choix du propriétaire), virgule recollée', $S('zvar_en_ville'), 'à Quetigny, Dijon et Genlis');
check('Jura : ses propres variables de lieu (plus « Quetigny » sur /jura/)', $S('z:jura:zvar_ville'), 'Dole');
check('adresse « 75-77-78… » retirée', $S('company_address'), null);
check('directeur « EMAE » retiré (personne physique attendue)', $S('ml_directeur'), null);
check('bandeau « technicien disponible » remis par défaut', $S('avail_open_text'), null);
check('délai de la zone Jura retiré', $GLOBALS['DB']['zones'][2]['delay'], null);
check('texte neutre intact', $S('home_title'), 'Votre expert multitechnique');
check('bilan enregistré', coherence_dernier_bilan()['lot'] ?? '', $b['lot']);
check('zone /idf/ sans ville ni département : non listée', array_column(intervention_zones_listed(), 'slug'), ['jura', 'doubs', 'cote-d-or']);

echo "\n1b. Textes globaux figés (v15.39)\n";
$b3 = coherence_degeler(); settings_cache(true);
check('accroche de l\'accueil : modèle à variables remis', $S('home_lead'), geo_textes_globaux()['home_lead']);
check('variable de lieu sans espace en trop', $S('zvar_en_region'), "dans le Doubs, la Côte-d'Or, le Jura et les alentours");
set_zone_context(null);
check('accueil : même texte qu\'avant (espaces corrigés)', zone_vars_apply((string)$S('home_lead')), "Électricité, plomberie, chauffage, climatisation et pompes à chaleur dans le Doubs, la Côte-d'Or, le Jura et les alentours. Intervention rapide autour de Quetigny, Dijon et Genlis, devis gratuit, artisans qualifiés.");
set_zone_context($GLOBALS['DB']['zones'][2]);
check('Jura : son propre secteur dans l\'accroche', str_contains(zone_vars_apply((string)$S('home_lead')), 'autour de Dole'), true);
set_zone_context(null);
check('un seul bilan, lot suivant rattaché', in_array($b3['lot'], coherence_dernier_bilan()['lots_suivants'] ?? [], true), true);

echo "\n2. Rejouée : rien de plus\n";
$b2 = coherence_appliquer(); settings_cache(true);
check('aucun texte modifié la deuxième fois', $b2['textes'], 0);

echo "\n3. Annulation complète\n";
geo_annuler($b2['lot']);
if (!empty($b2['secteurs']['lot'])) geo_annuler($b2['secteurs']['lot']);
geo_annuler($b3['lot']);
$n = geo_annuler($b['lot']);
if (!empty($b['secteurs']['lot'])) geo_annuler($b['secteurs']['lot']);
settings_cache(true);
$diff = [];
foreach ($DEPART['settings'] as $k => $v) if (($GLOBALS['DB']['settings'][$k] ?? null) !== $v) $diff[] = $k;
check('valeurs restaurées : '.$n, $n > 10, true);
check('chaque réglage de départ retrouvé', $diff, []);
check('délai de zone restauré', $GLOBALS['DB']['zones'][2]['delay'], "Moins d'1 h à Dole, sous 2 h ailleurs");

echo "\n".($fail ? "ÉCHECS : $fail" : "TOUS LES TESTS PASSENT ($pass assertions)")."\n";
exit($fail ? 1 : 0);
