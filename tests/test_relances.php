<?php
declare(strict_types=1);
/**
 * Texte des relances (reminder_draft) : bon ton selon le niveau, bons chiffres, rien d'inventé.
 * reminder_draft() n'utilise ni base de données, ni réseau, ni Claude : il suffit de lui donner
 * une facture et un niveau, puis de lire le texte produit.
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

$pass = 0; $fail = 0;
function check(string $l, mixed $g, mixed $w): void { global $pass, $fail;
    if ($g === $w) { $pass++; echo "  ok   $l\n"; }
    else { $fail++; echo "  FAIL $l — obtenu ".var_export($g, true)." / attendu ".var_export($w, true)."\n"; } }

$facture = ['id' => 7, 'number' => 'F-2026-0042', 'total_ttc' => '540.00', 'remaining_ttc' => '240.00',
            'due_date' => '2026-08-31', 'issue_date' => '2026-08-01', 'label' => 'Remplacement chauffe-eau'];

echo "\n1. Niveau 1 : rappel cordial\n";
$d1 = reminder_draft($facture, 1);
check('objet', $d1['subject'], 'Rappel — facture F-2026-0042');
check('ton cordial (simple oubli)', str_contains($d1['body'], 'simple oubli'), true);
check('pas de menace de recouvrement', str_contains($d1['body'], 'recouvrement'), false);
check('niveau renvoyé', $d1['niveau'], 1);

echo "\n2. Niveau 2 : relance ferme\n";
$d2 = reminder_draft($facture, 2);
check('objet', $d2['subject'], 'Relance n° 2 — facture F-2026-0042');
check('rappelle le précédent message', str_contains($d2['body'], 'précédent message'), true);
check('pas encore de recouvrement', str_contains($d2['body'], 'recouvrement'), false);

echo "\n3. Niveau 3 et plus : dernière relance\n";
$d3 = reminder_draft($facture, 3);
check('objet', $d3['subject'], 'Dernière relance avant recouvrement — facture F-2026-0042');
check('annonce le recouvrement', str_contains($d3['body'], 'procédure de recouvrement'), true);
check('niveau 5 = même texte que niveau 3', reminder_draft($facture, 5)['body'], $d3['body']);
check('niveau 0 ramené au niveau 1', reminder_draft($facture, 0)['subject'], $d1['subject']);

echo "\n4. Les chiffres viennent de la facture, rien n'est inventé\n";
foreach ([1 => $d1, 2 => $d2, 3 => $d3] as $n => $d) {
    check("niveau $n : numéro de facture", str_contains($d['body'], 'F-2026-0042'), true);
    check("niveau $n : reste dû (240,00 €, pas le total de 540)", str_contains($d['body'], money_fr(240)), true);
    check("niveau $n : pas le total", str_contains($d['body'], money_fr(540)), false);
    check("niveau $n : échéance au format français", str_contains($d['body'], '31/08/2026'), true);
    check("niveau $n : aucune pénalité inventée", (bool)preg_match('/pénalit|intérêts|indemnit|frais de/i', $d['body']), false);
    check("niveau $n : un seul montant cité", preg_match_all('/\d+,\d{2}/u', $d['body']), 1);
}

echo "\n5. Formules et cas particuliers\n";
check('le nom du client reste à remplacer à l\'envoi', str_starts_with($d1['body'], 'Bonjour {{client}},'), true);
check('signé au nom de l\'entreprise', str_ends_with($d1['body'], company_name()), true);
$sansReste = $facture; $sansReste['remaining_ttc'] = null;
check('reste dû inconnu → total TTC', str_contains(reminder_draft($sansReste, 1)['body'], money_fr(540)), true);
$sansEcheance = $facture; $sansEcheance['due_date'] = null;
check('sans échéance : pas de date inventée', str_contains(reminder_draft($sansEcheance, 1)['body'], 'échéance'), false);
$sansNumero = $facture; $sansNumero['number'] = '';
check('sans numéro : mention explicite', reminder_draft($sansNumero, 1)['subject'], 'Rappel — facture sans numéro');

echo "\n".($fail === 0 ? "TOUTES LES $pass ASSERTIONS PASSENT" : "$fail ÉCHEC(S) sur ".($pass + $fail))."\n";
exit($fail === 0 ? 0 : 1);
