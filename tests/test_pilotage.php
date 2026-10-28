<?php
declare(strict_types=1);
/**
 * Page Pilotage (includes/pilotage.php) : reconnaissance des métiers et départements, lecture
 * des rapports Google Ads, entonnoir dépense → demandes → interventions → factures, verdicts
 * et recommandations. Jeux de données fictifs aux résultats connus. Ni base, ni réseau.
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
require $ROOT.'/includes/invoicing.php';
require $ROOT.'/includes/finance.php';
require $ROOT.'/includes/pilotage.php';

$pass = 0; $fail = 0;
function check(string $l, mixed $g, mixed $w): void { global $pass, $fail;
    if ($g === $w) { $pass++; echo "  ok   $l\n"; }
    else { $fail++; echo "  FAIL $l — obtenu ".var_export($g, true)." / attendu ".var_export($w, true)."\n"; } }

echo "\n1. Métier et département reconnus dans un nom de campagne\n";
check('« Électricité Doubs »', [pilotage_metier_of('Électricité Doubs'), pilotage_dept_of('Électricité Doubs')], ['electricite', '25']);
check('« Plombier urgence - 39 »', [pilotage_metier_of('Plombier urgence - 39'), pilotage_dept_of('Plombier urgence - 39')], ['plomberie', '39']);
check('« Clim Haute-Savoie » : Haute-Savoie, pas Savoie', [pilotage_metier_of('Clim Haute-Savoie'), pilotage_dept_of('Clim Haute-Savoie')], ['climatisation', '74']);
check('« Savoie » seule', pilotage_dept_of('Chauffage Savoie'), '73');
check('« Côte-d\'Or » avec apostrophe', pilotage_dept_of("PAC Côte-d'Or"), '21');
check('« pac » mot entier → chauffage', pilotage_metier_of('PAC Côte-d\'Or'), 'chauffage');
check('« espace » ne contient pas le métier chauffage', pilotage_metier_of('Espace client'), '');
check('année 2026 non prise pour un département', pilotage_dept_of('Campagne 2026'), '');
check('« JDC » : ni métier ni département', [pilotage_metier_of('JDC marque'), pilotage_dept_of('JDC marque')], ['', '']);
check('code postal → département', [pilotage_dept_of_cp('25000'), pilotage_dept_of_cp('2500'), pilotage_dept_of_cp('')], ['25', '', '']);

echo "\n2. Origine d'une demande\n";
check('page d\'atterrissage Google Ads', pilotage_lead_segment(['source' => 'landing_electricite_doubs', 'postal_code' => '39100']), ['ads' => true, 'metier' => 'electricite', 'dept' => '25']);
check('département composé', pilotage_lead_segment(['source' => 'landing_plomberie_cote-dor']), ['ads' => true, 'metier' => 'plomberie', 'dept' => '21']);
check('page métier du site', pilotage_lead_segment(['source' => 'service_chauffage', 'service_type' => '', 'postal_code' => '39000']), ['ads' => false, 'metier' => 'chauffage', 'dept' => '39']);
check('formulaire d\'accueil, service saisi', pilotage_lead_segment(['source' => 'hero', 'service_type' => 'Plomberie', 'postal_code' => '21000']), ['ads' => false, 'metier' => 'plomberie', 'dept' => '21']);

echo "\n3. Nombres et dates des exports\n";
check('« 1 234,56 € »', pilotage_number('1 234,56 €'), 1234.56);
check('« 1,234.56 »', pilotage_number('1,234.56'), 1234.56);
check('« 12.5 »', pilotage_number('12.5'), 12.5);
check('« -- » → 0', pilotage_number('--'), 0.0);
check('dates', [pilotage_date('2026-10-03'), pilotage_date('03/10/2026'), pilotage_date('3 oct. 2026'), pilotage_date('Oct 3, 2026'), pilotage_date('31/02/2026'), pilotage_date('bof')],
    ['2026-10-03', '2026-10-03', '2026-10-03', '2026-10-03', '', '']);

echo "\n4. Lecture d'un rapport Google Ads\n";
$csv = "Rapport des campagnes\n1 oct. 2026 - 3 oct. 2026\nJour;Campagne;Coût;Clics;Impr.;Conversions\n"
     . "2026-10-01;Électricité Doubs;12,50;5;100;1\n2026-10-02;Électricité Doubs;7,50;3;80;0\n2026-10-02;JDC marque;1 000,00;2;10;0\nTotal: compte;;1 020,00;10;190;1\n";
$p = pilotage_ads_parse_csv($csv);
check('titres ignorés, colonnes trouvées', [$p['error'], $p['has_date'], count($p['rows'])], [null, true, 4]);
$n = pilotage_ads_normalize($p['rows']);
check('ligne de total ignorée', count($n['rows']), 3);
check('première ligne', $n['rows'][0], ['stat_date' => '2026-10-01', 'campaign' => 'Électricité Doubs', 'metier' => 'electricite', 'dept' => '25', 'cost' => 12.5, 'clicks' => 5, 'impressions' => 100, 'conversions' => 1.0]);
check('séparateur de milliers', $n['rows'][2]['cost'], 1000.0);
$utf16 = "\xFF\xFE".mb_convert_encoding("Day\tCampaign\tCost\tClicks\n2026-10-01\tPlomberie Jura\t9.90\t4\n", 'UTF-16LE', 'UTF-8');
$p16 = pilotage_ads_parse_csv($utf16);
check('export anglais UTF-16 tabulé', pilotage_ads_normalize($p16['rows'])['rows'][0]['cost'] ?? null, 9.9);
$sansJour = pilotage_ads_parse_csv("Campagne,Coût,Clics\nChauffage Doubs,30,10\n");
check('sans colonne Jour : date par défaut', [$sansJour['has_date'], pilotage_ads_normalize($sansJour['rows'], '2026-10-15')['rows'][0]['stat_date']], [false, '2026-10-15']);
check('sans colonne Jour ni date : refusé, message clair', count(pilotage_ads_normalize($sansJour['rows'])['errors']), 1);
check('fichier sans en-tête reconnu', pilotage_ads_parse_csv("a;b\n1;2\n")['error'] !== null, true);
$doublon = pilotage_ads_normalize([['date' => '2026-10-01', 'campaign' => 'X', 'cost' => '10'], ['date' => '2026-10-01', 'campaign' => 'X', 'cost' => '5']]);
check('même jour et même campagne : additionnés', [count($doublon['rows']), $doublon['rows'][0]['cost']], [1, 15.0]);
check('métier et département imposés par l\'envoi', pilotage_ads_normalize([['date' => '2026-10-01', 'campaign' => 'JDC', 'cost' => 1, 'metier' => 'plomberie', 'dept' => 'Jura']])['rows'][0]['metier'].'/'.pilotage_ads_normalize([['date' => '2026-10-01', 'campaign' => 'JDC', 'cost' => 1, 'dept' => 'Jura']])['rows'][0]['dept'], 'plomberie/39');

echo "\n5. Entonnoir : dépense → demandes → interventions → factures\n";
$ads = [
    ['stat_date' => '2026-10-01', 'campaign' => 'Élec Doubs A', 'metier' => 'electricite', 'dept' => '25', 'cost' => 60, 'clicks' => 30, 'impressions' => 500, 'conversions' => 2],
    ['stat_date' => '2026-10-02', 'campaign' => 'Élec Doubs B', 'metier' => 'electricite', 'dept' => '25', 'cost' => 40, 'clicks' => 10, 'impressions' => 300, 'conversions' => 0],
    ['stat_date' => '2026-10-01', 'campaign' => 'Plomberie toutes zones', 'metier' => 'plomberie', 'dept' => '', 'cost' => 50, 'clicks' => 20, 'impressions' => 400, 'conversions' => 0],
    ['stat_date' => '2026-10-01', 'campaign' => 'Chauffage Jura', 'metier' => 'chauffage', 'dept' => '39', 'cost' => 80, 'clicks' => 25, 'impressions' => 400, 'conversions' => 0],
];
$leads = [
    ['id' => 1, 'source' => 'landing_electricite_doubs', 'service_type' => '', 'postal_code' => '25000', 'status' => 'traité', 'created_at' => '2026-10-01 10:00:00', 'full_name' => 'A', 'city' => 'Besançon'],
    ['id' => 2, 'source' => 'landing_electricite_doubs', 'service_type' => '', 'postal_code' => '25000', 'status' => 'nouveau', 'created_at' => '2026-10-02 10:00:00', 'full_name' => 'B', 'city' => 'Besançon'],
    ['id' => 3, 'source' => 'landing_plomberie_jura', 'service_type' => '', 'postal_code' => '39000', 'status' => 'traité', 'created_at' => '2026-10-02 11:00:00', 'full_name' => 'C', 'city' => 'Lons'],
    ['id' => 4, 'source' => 'service_plomberie', 'service_type' => '', 'postal_code' => '21000', 'status' => 'traité', 'created_at' => '2026-10-03 09:00:00', 'full_name' => 'D', 'city' => 'Dijon'],
    ['id' => 5, 'source' => 'landing_climatisation_ain', 'service_type' => '', 'postal_code' => '01000', 'status' => 'nouveau', 'created_at' => '2026-10-03 09:00:00', 'full_name' => 'E', 'city' => 'Bourg'],
];
$ivs = [['id' => 10, 'quote_id' => 1], ['id' => 11, 'quote_id' => 1], ['id' => 12, 'quote_id' => 3], ['id' => 13, 'quote_id' => 4]];
$invs = [
    ['id' => 100, 'intervention_id' => 10, 'status' => 'payee', 'total_ht' => 300.0],
    ['id' => 101, 'intervention_id' => 11, 'status' => 'envoyee', 'total_ht' => 100.0],
    ['id' => 102, 'intervention_id' => 12, 'status' => 'brouillon', 'total_ht' => 999.0],   // brouillon : pas encore facturé
    ['id' => 103, 'intervention_id' => 13, 'status' => 'validee', 'total_ht' => 250.0],
];
$F = pilotage_funnel($leads, $ivs, $invs, $ads);
$T = $F['totals'];
check('dépense totale', $T['cost'], 230.0);
check('demandes : 5 dont 4 Google Ads', [$T['leads'], $T['leads_ads']], [5, 4]);
check('interventions : une demande convertie compte une fois', [$T['interventions'], $T['interventions_ads']], [3, 2]);
check('facturé HT : brouillon exclu', [$T['ca_ht'], $T['ca_ht_ads']], [650.0, 400.0]);
check('coût par demande Ads', $T['cpl_ads'], 57.5);
check('1 € rapporte (400 / 230)', round($T['roas'], 3), 1.739);
check('demande Ain sans campagne : non attribuée', $T['unattributed_leads'], 1);
$C = array_column($F['campaigns'], null, 'campaign');
check('Élec Doubs A : 3/4 des 2 demandes (30 clics sur 40)', [$C['Élec Doubs A']['leads'], $C['Élec Doubs A']['ca_ht']], [1.5, 300.0]);
check('Élec Doubs B : le quart restant', [$C['Élec Doubs B']['leads'], $C['Élec Doubs B']['ca_ht']], [0.5, 100.0]);
check('Plomberie toutes zones capte la demande du Jura', [$C['Plomberie toutes zones']['leads'], $C['Plomberie toutes zones']['interventions']], [1.0, 1.0]);
check('Chauffage Jura : rien', [$C['Chauffage Jura']['leads'], $C['Chauffage Jura']['cpl']], [0.0, null]);
check('total des demandes réparties = demandes attribuées', round(array_sum(array_column($F['campaigns'], 'leads')), 6), 3.0);
$Sg = [];
foreach ($F['segments'] as $s) $Sg[$s['metier'].'|'.$s['dept']] = $s;
check('segment Électricité Doubs', [$Sg['electricite|25']['leads_ads'], $Sg['electricite|25']['interventions'], $Sg['electricite|25']['ca_ht'], $Sg['electricite|25']['cost']], [2, 1, 400.0, 100.0]);
check('segment Plomberie Côte-d\'Or : venu du site', [$Sg['plomberie|21']['leads_site'], $Sg['plomberie|21']['ca_ht'], $Sg['plomberie|21']['cost']], [1, 250.0, 0.0]);
check('la campagne « toutes zones » a son propre segment', isset($Sg['plomberie|']), true);

echo "\n6. Une campagne plus précise l'emporte\n";
$F2 = pilotage_funnel([$leads[0]], [], [], [
    ['stat_date' => '2026-10-01', 'campaign' => 'Tout', 'metier' => '', 'dept' => '', 'cost' => 10, 'clicks' => 100, 'impressions' => 0, 'conversions' => 0],
    ['stat_date' => '2026-10-01', 'campaign' => 'Doubs', 'metier' => '', 'dept' => '25', 'cost' => 10, 'clicks' => 1, 'impressions' => 0, 'conversions' => 0],
]);
$C2 = array_column($F2['campaigns'], 'leads', 'campaign');
check('département précis > campagne générale, malgré moins de clics', [$C2['Doubs'], $C2['Tout']], [1.0, 0.0]);
$F3 = pilotage_funnel([$leads[0]], [], [], [['stat_date' => '2026-10-01', 'campaign' => 'Z', 'metier' => '', 'dept' => '', 'cost' => 1, 'clicks' => 0, 'impressions' => 0, 'conversions' => 0],
                                             ['stat_date' => '2026-10-01', 'campaign' => 'Y', 'metier' => '', 'dept' => '', 'cost' => 1, 'clicks' => 0, 'impressions' => 0, 'conversions' => 0]]);
check('sans clics : partage à parts égales', array_column($F3['campaigns'], 'leads'), [0.5, 0.5]);
check('rien du tout : pas de division par zéro', pilotage_funnel([], [], [], [])['totals']['roas'], null);

echo "\n7. Verdicts\n";
$cpl = $T['cpl_ads'];
check('moins de 40 € : trop tôt', pilotage_verdict(['cost' => 39.0, 'leads' => 0.0, 'interventions' => 0.0, 'roas' => null, 'cpl' => null], $cpl), 'trop_tot');
check('Chauffage Jura : 80 € sans demande → pause', pilotage_verdict($C['Chauffage Jura'], $cpl), 'a_couper');
check('Élec Doubs A : 300 € pour 60 € → renforcer', pilotage_verdict($C['Élec Doubs A'], $cpl), 'a_renforcer');
check('Élec Doubs B : 100 € pour 40 € → correct', pilotage_verdict($C['Élec Doubs B'], $cpl), 'ok');
check('Plomberie : demande convertie, facture en brouillon → correct', pilotage_verdict($C['Plomberie toutes zones'], $cpl), 'ok');
check('100 € dépensés, demandes sans intervention → à revoir', pilotage_verdict(['cost' => 100.0, 'leads' => 3.0, 'interventions' => 0.0, 'roas' => 0.0, 'cpl' => 33.3], $cpl), 'a_revoir');
check('coût par demande > 2 × la moyenne → à revoir', pilotage_verdict(['cost' => 60.0, 'leads' => 2.0, 'interventions' => 1.0, 'roas' => 1.5, 'cpl' => 130.0], 50.0), 'a_revoir');

echo "\n8. Recommandations\n";
$ctx = ['today' => '2026-10-04', 'ads_last_date' => '2026-10-02', 'pending_leads' => [$leads[1], $leads[4]],
        'drafts' => ['count' => 1, 'total' => 1198.8], 'late' => ['count' => 2, 'total' => 730.0]];
$R = pilotage_recommendations($F, $ctx);
$titres = array_column($R, 'titre');
$has = static fn(string $debut) => (bool)array_filter($titres, static fn($t) => str_starts_with($t, $debut));
check('demandes sans réponse en tête', [$R[0]['niveau'], $R[0]['outil'], $R[0]['titre']], [1, 'Dispatcher', '2 demandes sans réponse depuis plus de 24 h']);
check('…avec le nombre venu de Google Ads', str_contains($R[0]['detail'], '2 viennent de Google Ads'), true);
check('pause de Chauffage Jura', $has('Mettre en pause « Chauffage Jura »'), true);
check('renforcer Élec Doubs A', $has('Augmenter le budget de « Élec Doubs A »'), true);
check('rien sur Élec Doubs B', (bool)array_filter($titres, static fn($t) => str_contains($t, 'Élec Doubs B')), false);
check('demande sans campagne signalée', $has('1 demande(s) Google Ads sans campagne'), true);
check('brouillons et retards Pennylane', [$has('Finaliser 1 facture en brouillon'), $has('Relancer 2 factures en retard')], [true, true]);
check('chiffres Ads récents : pas d\'alerte', $has('Chiffres Google Ads à mettre à jour'), false);
check('classées par urgence', array_column($R, 'niveau') === array_values(array_sort_copy(array_column($R, 'niveau'))), true);
$R2 = pilotage_recommendations(pilotage_funnel([], [], [], []), ['today' => '2026-10-04', 'ads_last_date' => '']);
check('aucun chiffre Ads : demander l\'import', [count($R2), $R2[0]['titre']], [1, 'Aucun chiffre Google Ads importé']);
$R3 = pilotage_recommendations(pilotage_funnel([], [], [], []), ['today' => '2026-10-10', 'ads_last_date' => '2026-10-02']);
check('chiffres de plus de 3 jours : à mettre à jour', $R3[0]['titre'] ?? '', 'Chiffres Google Ads à mettre à jour');
// Segment rentable sans publicité : 3 demandes du site, 2 interventions.
$site = [];
foreach ([1, 2, 3] as $i) $site[] = ['id' => 50 + $i, 'source' => 'service_chauffage', 'service_type' => '', 'postal_code' => '25000', 'status' => 'traité', 'created_at' => '2026-10-01', 'full_name' => '', 'city' => ''];
$R4 = pilotage_recommendations(pilotage_funnel($site, [['id' => 1, 'quote_id' => 51], ['id' => 2, 'quote_id' => 52]], [], []), ['today' => '2026-10-04', 'ads_last_date' => '2026-10-03']);
check('segment qui marche sans pub : tester une campagne', array_column($R4, 'titre'), ['Tester une campagne Chauffage — Doubs (25)']);
// Beaucoup de demandes, presque aucune intervention.
$beaucoup = [];
foreach (range(1, 6) as $i) $beaucoup[] = ['id' => 70 + $i, 'source' => 'service_plomberie', 'service_type' => '', 'postal_code' => '39000', 'status' => 'traité', 'created_at' => '2026-10-01', 'full_name' => '', 'city' => ''];
$R5 = pilotage_recommendations(pilotage_funnel($beaucoup, [], [], []), ['today' => '2026-10-04', 'ads_last_date' => '2026-10-03']);
check('6 demandes, 0 intervention : page à revoir', [$R5[0]['outil'] ?? '', $R5[0]['titre'] ?? ''], ['Site', 'Peu de demandes aboutissent : Plomberie — Jura (39)']);

function array_sort_copy(array $a): array { sort($a); return $a; }

echo "\n".($fail === 0 ? "TOUTES LES $pass ASSERTIONS PASSENT" : "$fail ÉCHEC(S) sur ".($pass + $fail))."\n";
exit($fail === 0 ? 0 : 1);
