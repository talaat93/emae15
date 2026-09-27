<?php
declare(strict_types=1);

$DB = ['settings' => [], 'zones' => [
    1 => ['id'=>1,'slug'=>'jura','name'=>'Jura','status'=>1,'sort_order'=>0],
]];
function db_fetch_all(string $s, array $p = []): array { global $DB;
    if (str_contains($s,'FROM settings')) return array_values($DB['settings']);
    if (str_contains($s,'FROM zones')) return array_values($DB['zones']);
    return []; }
function db_fetch(string $s, array $p = []): ?array { global $DB;
    if (str_contains($s,'FROM settings')) { foreach ($DB['settings'] as $r) if ($r['setting_key']===$p[0]) return $r; return null; }
    if (str_contains($s,'FROM zones')) { foreach ($DB['zones'] as $r) if ((string)$r['id']===(string)$p[0]||$r['slug']===$p[0]) return $r; }
    return null; }
function db_execute(string $s, array $p = []): void { global $DB;
    if (str_starts_with($s,'INSERT INTO settings')) $DB['settings'][$p[0]] = ['setting_key'=>$p[0],'setting_value'=>$p[1]];
    if (str_starts_with($s,'UPDATE settings')) $DB['settings'][$p[1]] = ['setting_key'=>$p[1],'setting_value'=>$p[0]];
    if (str_starts_with($s,'DELETE FROM settings')) unset($DB['settings'][$p[0]]); }

$_SERVER['SCRIPT_NAME'] = '/index.php';
$R = dirname(__DIR__);
require $R.'/includes/helpers.php';
require $R.'/includes/admin_fields.php';
require $R.'/includes/zone_vars.php';
require $R.'/includes/inline_edit.php';

$pass=0; $fail=0;
function check(string $l, mixed $g, mixed $w): void { global $pass,$fail;
    if ($g===$w) { $pass++; echo "  ok   $l\n"; }
    else { $fail++; echo "  FAIL $l\n        obtenu  ".var_export($g,true)."\n        attendu ".var_export($w,true)."\n"; } }
function contains(string $l, string $hay, string $needle): void { global $pass,$fail;
    if (str_contains($hay,$needle)) { $pass++; echo "  ok   $l\n"; }
    else { $fail++; echo "  FAIL $l — introuvable : ".var_export($needle,true)."\n"; } }
function absent(string $l, string $hay, string $needle): void { global $pass,$fail;
    if (!str_contains($hay,$needle)) { $pass++; echo "  ok   $l\n"; }
    else { $fail++; echo "  FAIL $l — présent alors qu'interdit : ".var_export($needle,true)."\n"; } }

/** Rend une page factice reproduisant les contextes délicats du vrai site. */
function fake_page(): string {
    ob_start();
    echo '<html><head>';
    echo '<title>', e(setting('services_title','Tout ce dont vous avez')), '</title>';
    echo '<meta name="description" content="', e(setting('faq_meta_description','Questions fréquentes.')), '">';
    echo '<meta property="og:image" content="', e(setting('og_default_image','')), '">';
    echo '<script type="application/ld+json">', json_encode(['d'=>setting('company_description','Entreprise multitechnique.')]), '</script>';
    echo '</head><body>';
    echo '<h1>', e(setting('home_title','Votre expert multitechnique')), '</h1>';
    echo '<p class="lead">', e(setting('services_lead','Un seul interlocuteur.')), '</p>';
    echo '<p class="step">', e(setting('process_1_text','Appelez ou remplissez le formulaire.')), '</p>';
    echo '<a href="#" title="', e(setting('home_button1_label','Devis gratuit')), '">', e(setting('home_button1_label','Devis gratuit')), '</a>';
    echo '<input placeholder="', e(setting('home_quote_city_placeholder','Meaux, Paris…')), '">';
    foreach (explode('|', setting('zone_idf_cities','Paris (75)|Meaux (77)')) as $c) echo '<span class="city">', e($c), '</span>';
    echo '<script>var t = "ok";</script>';
    echo '</body></html>';
    return (string)ob_get_clean();
}

echo "\n1. Hors mode édition, la page est strictement inchangée\n";
inline_edit_active(false);
$plain = fake_page();
absent('aucun marqueur ouvrant', $plain, "\x02");
absent('aucun marqueur fermant', $plain, "\x04");
absent('aucun élément éditable', $plain, 'class="ie');
contains('le titre est intact', $plain, '<h1>Votre expert multitechnique</h1>');

echo "\n2. En mode édition, les textes visibles deviennent modifiables\n";
inline_edit_active(true);
$html = inline_edit_postprocess(fake_page());
contains('le titre H1 est éditable', $html, 'data-k="home_title"');
contains('un paragraphe est éditable', $html, 'data-k="process_1_text"');
contains('l\'accroche redevient éditable', $html, 'data-k="services_lead"');
contains('le libellé du bouton est éditable', $html, 'data-k="home_button1_label"');
contains('l\'étiquette au survol est fournie', $html, 'data-l="Bandeau principal — Titre principal"');
contains('repère « texte par défaut »', $html, 'ie ie--def');
contains('la barre d\'outils est injectée', $html, 'id="ie-bar"');

absent('aucune forme échappée JSON', $html, '\\u0002');
absent('aucun \\x02 résiduel', $html, "\x02");
absent('aucun \\x03 résiduel', $html, "\x03");
absent('aucun \\x04 résiduel', $html, "\x04");
absent('aucune forme échappée JSON', $html, '\\u0002');

echo "\n4. Les contextes dangereux ne sont jamais transformés\n";
contains('le <title> reste du texte pur', $html, '<title>Tout ce dont vous avez</title>');
contains('l\'attribut meta description est propre', $html, 'content="Questions fréquentes."');
contains('l\'attribut title du lien est propre', $html, 'title="Devis gratuit"');
contains('le placeholder est propre', $html, 'placeholder="Meaux, Paris…"');
contains('les données Google restent valides', $html, '{"d":"Entreprise multitechnique."}');
check('le JSON-LD se décode toujours',
      json_decode(preg_match('#ld\+json">(.*?)</script>#s',$html,$m) ? $m[1] : '', true),
      ['d'=>'Entreprise multitechnique.']);
absent('aucun span dans une balise', $html, 'content="<span');

echo "\n5. Les listes découpées par le site ne sont pas cassées\n";
contains('première ville intacte', $html, '<span class="city">Paris (75)</span>');
contains('seconde ville intacte', $html, '<span class="city">Meaux (77)</span>');

echo "\n6. Seuls les champs sûrs sont proposés à l'édition\n";
$inline = admin_inline_keys();
foreach (['home_title','process_1_text','qs_perk_1','ml_hebergeur','svc_electricite_label','why_title'] as $k)
    check("« $k » éditable sur la page", isset($inline[$k]), true);
foreach (['faq_meta_description','zones_meta_title','og_default_image','contact_zone_tags',
          'zone_idf_cities','google_mybusiness_url','home_quote_city_placeholder',
          'company_description'] as $k)
    check("« $k » écarté à juste titre", isset($inline[$k]), false);

echo "\n6bis. La géolocalisation par IP est bien supprimée\n";
$hp = file_get_contents($R.'/includes/helpers.php');
check('plus de détection par IP',        str_contains($hp,'geo_detect_dept'), false);
check('plus de table des départements',  str_contains($hp,'geo_display'), false);
check('plus d\'appel au service tiers',  str_contains($hp,'ip-api.com'), false);
check('aucune lecture de session geo',   str_contains($hp,"SESSION['geo_dept']"), false);
$ix = file_get_contents($R.'/index.php');
check('index.php ne détecte plus rien',  str_contains($ix,'geo_detect_dept'), false);
check('le fichier de diagnostic est parti', file_exists($R.'/geo_test.php'), false);
check('l\'écran cassé est parti',        file_exists($R.'/admin/geo_targeting.php'), false);

echo "\n6ter. Les variables se résolvent selon la portée\n";
set_zone_context(null);
zone_var_save('ville', 'votre région');
zone_var_save('en_region', 'dans notre secteur');
settings_cache(true);
check('valeur globale',          zone_vars_apply('Nous intervenons à {ville}.'), 'Nous intervenons à votre région.');
check('texte sans variable intact', zone_vars_apply('Un seul interlocuteur.'), 'Un seul interlocuteur.');

set_zone_context(get_zone_by_slug('jura'));
check('la zone hérite du global',  zone_vars_apply('Électricien à {ville}'), 'Électricien à votre région');
zone_var_save('ville', 'Dole');
zone_var_save('en_region', 'dans le Jura');
settings_cache(true);
check('la zone a sa valeur',       zone_vars_apply('Électricien à {ville}'), 'Électricien à Dole');
check('préposition correcte',      zone_vars_apply('Nous intervenons {en_region}.'), 'Nous intervenons dans le Jura.');
set_zone_context(null);
check('le global est intact',      zone_vars_apply('Électricien à {ville}'), 'Électricien à votre région');
check('  → et sa préposition',     zone_vars_apply('Nous intervenons {en_region}.'), 'Nous intervenons dans notre secteur.');

check('variable non renseignée laissée visible', zone_vars_apply('Code {code_postal} ici'), 'Code {code_postal} ici');
check('variable inconnue laissée telle quelle',  zone_vars_apply('Bonjour {inconnue}'), 'Bonjour {inconnue}');
check('ancien nom encore accepté',
      (function(){ zone_var_save('departement','Jura'); settings_cache(true);
                   return zone_vars_apply('Le {dept}'); })(), 'Le Jura');

echo "\n6quater. Un texte contenant une variable n'est pas éditable sur la page\n";
admin_inline_keys(true);
check('sans variable : éditable',  isset(admin_inline_keys()['services_lead']), true);
set_setting('services_lead', 'Dépannage à {ville} et alentours.'); admin_inline_keys(true);
check('avec variable : écarté',    isset(admin_inline_keys()['services_lead']), false);
set_setting('services_lead', ''); admin_inline_keys(true);
check('variable retirée : éditable de nouveau', isset(admin_inline_keys()['services_lead']), true);

echo "\n7. Un texte personnalisé change de repère\n";
set_setting('home_title', 'Notre entreprise');
$html2 = inline_edit_postprocess(fake_page());
contains('la nouvelle valeur est affichée', $html2, '>Notre entreprise<');
absent('le repère « défaut » a disparu', $html2, 'data-k="home_title"'.'" class="ie ie--def"');

echo "\n8. En zone, un texte propre à la zone est distingué\n";
set_zone_context(get_zone_by_slug('jura'));
set_setting('process_1_text', 'Étape du Jura');
$html3 = inline_edit_postprocess(fake_page());
contains('valeur propre à la zone affichée', $html3, '>Étape du Jura<');
contains('repère « propre à la zone »', $html3, 'ie--zone');
set_zone_context(null);

echo "\n9. Le texte saisi ne peut pas injecter de HTML\n";
set_setting('home_title', '<script>alert(1)</script>');
$html4 = inline_edit_postprocess(fake_page());
absent('la balise script saisie est neutralisée', $html4, '<script>alert(1)</script>');
contains('elle est affichée en texte', $html4, '&lt;script&gt;alert(1)&lt;/script&gt;');

echo "\n12. Les blocs groupés deviennent cliquables sans être abîmés\n";
inline_edit_active(false); settings_cache(true); admin_inline_keys(true);
$plain = why_us_settings();
absent('hors édition : aucun marqueur', $plain['title'], "\x02");
check('hors édition : 8 arguments',      count($plain['items']), 8);

inline_edit_active(true);
$w = why_us_settings();
contains('le titre est balisé',   $w['title'], "\x02why_title\x03");
contains('le surtitre est balisé', $w['eyebrow'], "\x02why_eyebrow\x03");
contains('l\'accroche est balisée', $w['lead'], "\x02why_lead\x03");
check('les 8 arguments sont intacts', count($w['items']), 8);
absent('un argument n\'est pas balisé', (string)($w['items'][0]['title'] ?? ''), "\x02");

$page = inline_edit_postprocess('<html><body><h2>'.e($w['title']).'</h2><p>'.e($w['items'][0]['text']).'</p></body></html>');
contains('le titre devient cliquable', $page, 'data-k="why_title"');
absent('aucun marqueur résiduel',      $page, "\x02");
contains('le texte de l\'argument reste du texte', $page, 'Moins de 2h en urgence');

echo "\n13. Enregistrer depuis la page écrit bien dans le bloc\n";
inline_edit_active(false); settings_cache(true); admin_inline_keys(true);
$known = admin_inline_keys();
check('le champ est accepté par l\'enregistrement', isset($known['why_title']), true);
admin_field_save($known['why_title'], 'Pourquoi choisir EMAE');
settings_cache(true);
check('le site reprend la valeur',   why_us_settings()['title'], 'Pourquoi choisir EMAE');
check('les 8 arguments survivent',   count(why_us_settings()['items']), 8);
check('le surtitre voisin est intact', why_us_settings()['eyebrow'], 'Pourquoi nous choisir');

echo "\n".($fail===0 ? "TOUS LES TESTS PASSENT ($pass)" : "$fail ÉCHEC(S) sur ".($pass+$fail))."\n";
exit($fail===0?0:1);
