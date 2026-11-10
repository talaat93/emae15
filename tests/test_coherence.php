<?php
declare(strict_types=1);
// Mise en cohérence (novembre 2026) : promesses non documentées, consentement aux cookies,
// collecte sans clic, rappel express, jours de rendez-vous, WhatsApp, données structurées, FAQ.

$DB = ['settings' => []];
function db_fetch_all(string $s, array $p = []): array { global $DB;
    if (str_contains($s, 'FROM settings')) return array_map(fn($k, $v) => ['setting_key' => $k, 'setting_value' => $v], array_keys($DB['settings']), $DB['settings']);
    return []; }
function db_fetch(string $s, array $p = []): ?array { return null; }
function db_execute(string $s, array $p = []): void { global $DB;
    if (str_starts_with($s, 'INSERT INTO settings')) $DB['settings'][$p[0]] = $p[1]; }
function db_last_id(): int { return 1; }
define('EMAE_TESTS_SANS_RESEAU', true);

$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['HTTP_HOST'] = 'www.emaee.fr';
$R = dirname(__DIR__);
require $R.'/includes/helpers.php';
require $R.'/includes/zones_core.php';
require $R.'/includes/geo_secteurs.php';
require $R.'/includes/workflow.php';
require $R.'/includes/growth.php';
boot_session();
require $R.'/includes/content_pages.php';
require $R.'/includes/client_space.php';
require $R.'/includes/payments.php';
require $R.'/includes/contracts.php';
require $R.'/includes/render.php';

$pass = 0; $fail = 0;
function check(string $l, mixed $g, mixed $w): void { global $pass, $fail;
    if ($g === $w) { $pass++; echo "  ok   $l\n"; }
    else { $fail++; echo "  FAIL $l\n        obtenu  ".var_export($g, true)."\n        attendu ".var_export($w, true)."\n"; } }
function set(string $k, string $v): void { global $DB; $DB['settings'][$k] = $v; settings_cache(true); }

echo "\n1. Promesses non documentées\n";
foreach ([
    'note sans source'            => ['4,6/5 sur 128 avis', true],
    'avis vérifiés'               => ['128 avis vérifiés', true],
    '1000+'                       => ['1000+', true],
    'arrivée en moins de 90 min'  => ['Plusieurs équipes réparties en Île-de-France pour une arrivée en moins de 90 min', true],
    'moins de 2h'                 => ['Moins de 2h en urgence', true],
    'moins d\'1 h'                => ["Moins d'1 h à Dole, sous 2 h ailleurs", true],
    'rappel 10 min garanti'       => ['Rappel sous 10 minutes garanti', true],
    'délai garanti'               => ['Délai d\'intervention garanti.', true],
    'certifié RGE'                => ['Technicien certifié RGE', true],
    'rappel 5 min (réglé)'        => ['Nous vous rappelons sous 5 minutes.', false],
    '5 min hors rappel'           => ['Arrivée en 5 minutes', true],
    '24h/24'                      => ['Urgences 24h/24 et 7j/7', false],
    'horaires 8h à 19h'           => ['du lundi au samedi de 8h à 19h', false],
    'texte neutre'                => ['Devis gratuit et sans engagement', false],
] as $l => [$t, $want]) check($l, claim_unsupported($t), $want);
set('callback_delay', '');
check('aucun délai publié : « 5 minutes » devient une promesse', claim_unsupported('Nous vous rappelons sous 5 minutes.'), true);
check('aucun délai publié : « Rappel rapide »', callback_promise_short(), 'Rappel rapide');
set('callback_delay', '5 minutes');
check('repli neutre', claim_clean('Moins de 2h en urgence'), intervention_terms_text());

echo "\n2. Consentement : rien de Google avant l'accord\n";
check('sans identifiant Google : pas de script', consent_head_script('', '', '', '', false), '');
set('google_ads_id', 'AW-18088003287'); set('google_ads_conversion_label', 'A6nGCOCx4bIcENeNhLFD');
$h = consent_head_script('', 'AW-18088003287', 'A6nGCOCx4bIcENeNhLFD', 'dpCcCNG397IcENeNhLFD', true);
check('pas de balise gtag.js écrite dans la page', str_contains($h, '<script async src="https://www.googletagmanager.com'), false);
check('gtag n\'est défini que dans le chargeur', substr_count($h, 'window.gtag=function'), 1);
check('chargé seulement si un choix est lu', str_contains($h, 'var c=read();') && str_contains($h, 'if(c)window.emaeLoadTags(c);'), true);
$iDef = strpos($h, "'consent','default'"); $iUpd = strpos($h, "'consent','update'"); $iCfg = strpos($h, "gtag('config'"); $iLoad = strpos($h, 's.src=');
check('ordre : default → update → config → chargement', $iDef !== false && $iDef < $iUpd && $iUpd < $iCfg && $iCfg < $iLoad, true);
check('consentement v2 : quatre signaux refusés par défaut', str_contains($h, "ad_storage:'denied',ad_user_data:'denied',ad_personalization:'denied',analytics_storage:'denied'"), true);
check('pas de rattrapage de conversion sans accord préalable', str_contains($h, 'T.conv=false; /* sans accord'), true);
check('identifiant invalide refusé', consent_tags()['ads'], 'AW-18088003287');
set('google_ads_id', 'AW-<script>');
check('identifiant douteux : rien', consent_head_script('', '', '', '', false), '');
set('google_ads_id', 'AW-18088003287');
check('cookie absent : pas de choix', consent_state('')['set'], false);
check('refus lu', consent_state('v1.stats=0.ads=0'), ['set' => true, 'stats' => false, 'ads' => false]);
check('accord publicité lu', consent_state('v1.stats=0.ads=1')['ads'], true);
ob_start(); render_consent_banner(); $b = (string)ob_get_clean();
foreach (['refuse' => 'Tout refuser', 'accept' => 'Tout accepter', 'params' => 'Paramétrer'] as $k => $lab) {
    check("bouton « $lab »", str_contains($b, 'data-ck="'.$k.'"') && str_contains($b, $lab), true);
}
check('bandeau : refuser et accepter ont le même style', substr_count($b, 'class="ck-btn"'), 4);
check('retrait : cookies Google supprimés', str_contains($b, '_gcl_') && str_contains($b, 'max-age=0'), true);
check('choix gardé 6 mois', str_contains($b, '182*86400'), true);

echo "\n3. Formulaires : rien avant le clic, rappel express\n";
set('partial_enabled', '1');
ob_start(); render_quote_form([], 'hero', 'rappel'); $f = (string)ob_get_clean();
check('pas d\'envoi en tâche de fond', str_contains($f, 'data-partial') || str_contains($f, 'partial_token'), false);
check('bouton explicite', str_contains($f, 'Demander à être rappelé'), true);
check('téléphone obligatoire, saisie téléphone', (bool)preg_match('/name="phone"[^>]*autocomplete="tel"[^>]*inputmode="tel"[^>]*required/', $f), true);
check('code postal obligatoire, clavier numérique', (bool)preg_match('/name="postal_code"[^>]*inputmode="numeric"[^>]*required/', $f), true);
check('nom facultatif', (bool)preg_match('/name="full_name"[^>]*required/', $f), false);
check('chaque champ dans son libellé', substr_count($f, '<label class="f-field">'), 4);
check('aucun identifiant (pas de doublon possible)', (bool)preg_match('/\sid="/', $f), false);
check('pas de case marketing imposée', str_contains($f, 'type="checkbox"'), false);
ob_start(); render_quote_form([], 'quote_page'); $f2 = (string)ob_get_clean();
check('devis détaillé : nom obligatoire', (bool)preg_match('/name="full_name"[^>]*required/', $f2), true);
check('devis détaillé : e-mail avec autocomplete', str_contains($f2, 'autocomplete="email"'), true);
check('deux formulaires sur une page : aucun id', (bool)preg_match('/\sid="/', $f.$f2), false);
check('serveur : saisie en cours refusée', partial_save(['partial_token' => str_repeat('b', 32), 'phone' => '0612345678']), false);
check('site.js : plus d\'envoi au masquage de la page', str_contains((string)file_get_contents($R.'/assets/js/site.js'), 'visibilitychange'), false);
check('site.js : plus de sendBeacon de formulaire', str_contains((string)file_get_contents($R.'/assets/js/site.js'), 'sendBeacon'), false);

echo "\n4. Rendez-vous : lundi-samedi, jours fériés, heure de Paris\n";
$tz = new DateTimeZone('Europe/Paris');
$d = booking_days(new DateTimeImmutable('2026-11-05', $tz));   // jeudi
check('jamais de dimanche par défaut', count(array_filter($d, fn($x) => date('N', strtotime($x)) === '7')), 0);
check('11 novembre (férié) exclu', in_array('2026-11-11', $d, true), false);
check('samedi proposé', in_array('2026-11-07', $d, true), true);
check('jamais le jour même', in_array('2026-11-05', $d, true), false);
check('Pâques 2027 : lundi 29 mars férié', isset(french_public_holidays(2027)['2027-03-29']), true);
check('Ascension 2026 : 14 mai', isset(french_public_holidays(2026)['2026-05-14']), true);
$dst = booking_days(new DateTimeImmutable('2026-10-23 23:30', $tz));   // passage à l'heure d'hiver le 25/10
check('changement d\'heure : aucun jour sauté ni doublé', count($dst), count(array_unique($dst)));
check('changement d\'heure : lundi 26 octobre présent', in_array('2026-10-26', $dst, true), true);
check('libellé FAQ', booking_weekdays_label(), 'du lundi au samedi');
set('booking_weekdays', '1,2,3,4,5,6,7');
check('dimanche si l\'équipe l\'a réglé', in_array('2026-11-08', booking_days(new DateTimeImmutable('2026-11-05', $tz)), true), true);
set('booking_weekdays', '1,2,3,4,5,6');
check('horaires : urgences et planifié distincts', hours_text(), 'Urgences : 24h/24 et 7j/7. Interventions planifiées : créneaux du lundi au samedi, confirmés par téléphone.');

echo "\n5. WhatsApp : pas de bouton vers un numéro incomplet\n";
set('company_whatsapp', '+3367830376');
check('numéro à 8 chiffres : bouton masqué', whatsapp_url(), '');
set('company_whatsapp', '+33612345678');
check('numéro complet : bouton', str_starts_with(whatsapp_url(), 'https://wa.me/33612345678'), true);

echo "\n6. Données structurées : une seule entité, rien d'inventé\n";
set('company_whatsapp', '+3367830376');
set('schema_rating_value', '4.6'); set('schema_review_count', '128'); set('company_address', '75-77-78-91-92-93-94-95');
set('company_siret', '102 802 600 00019'); set('company_phone_link', 'tel:+33356570303'); set('company_hours', '24h/24 — 7j/7');
$j = json_decode(schema_local_business(), true);
check('type conservé', $j['@type'], 'ElectricalContractor');
check('identifiant stable', $j['@id'], 'https://www.emaee.fr/#entreprise');
check('pas de note auto-attribuée', isset($j['aggregateRating']), false);
check('pas d\'adresse inventée', isset($j['address']), false);
check('pas de WhatsApp incomplet', isset($j['sameAs']), false);
check('dénomination légale (Kbis)', $j['legalName'], 'Entreprise Multi-technique Avancée (EMAE)');
check('secteur réel', array_column($j['areaServed'], 'name'), ['Doubs', 'Jura', "Côte-d'Or"]);
check('téléphone international', $j['telephone'], '+33356570303');
check('SIRET sans espaces', $j['identifier']['value'], '10280260000019');
check('URL absolue', $j['url'], 'https://www.emaee.fr/');

echo "\n7. FAQ cohérente avec le secteur\n";
$a = faq_answer_fix('Quelles sont vos zones d\'intervention ?', 'Nous intervenons dans les départements du Paris (75) | Seine-et-Marne (77), notamment à Meaux.');
check('zones : plus de liste francilienne', str_contains($a, 'Paris') || str_contains($a, 'Meaux'), false);
check('zones : réponse sur le vrai secteur', str_starts_with($a, 'Nous intervenons'), true);
check('zones déjà justes : inchangées', faq_answer_fix('Quelles sont vos zones ?', 'Le Jura, le Doubs et la Côte-d\'Or.'), 'Le Jura, le Doubs et la Côte-d\'Or.');
check('délai chiffré remplacé', str_starts_with(faq_answer_fix('Quel est le délai en urgence ?', 'Moins d\' 90 min à 90 min selon votre zone.'), intervention_terms_text()), true);
check('horaires : phrase unique', faq_answer_fix('Quels sont vos horaires ?', 'Lundi-samedi 8h-19h.'), hours_text());

echo "\n8. Avis : une note seulement avec sa source\n";
check('rien de renseigné : aucun chiffre', review_summary(), null);
set('google_reviews_url', 'https://g.page/r/exemple/review'); set('google_rating', '4.7'); set('google_review_count', '40'); set('google_rating_updated_at', '2026-11-09 08:00:00');
check('fiche Google : note avec date', review_summary(), ['rating' => 4.7, 'count' => 40, 'url' => 'https://g.page/r/exemple/review', 'updated' => '09/11/2026']);

echo "\n".($fail ? "ÉCHECS : $fail" : "TOUS LES TESTS PASSENT ($pass assertions)")."\n";
exit($fail ? 1 : 0);
