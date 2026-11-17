<?php
declare(strict_types=1);
/**
 * « Devis à faire pour plus tard » demandé par le technicien (includes/yousign.php) : brouillon
 * créé sans prix avec les travaux à chiffrer, numéroté, mis à jour (pas de doublon) si le
 * rapport est rendu à nouveau, dispatcher prévenu. Base en mémoire, aucun réseau.
 */
define('EMAE_TESTS_SANS_RESEAU', true);

$DB = ['iv' => ['id' => 6, 'status' => 'sur_place', 'category' => 'plomberie', 'client_id' => null, 'dispatcher_id' => null, 'ref' => 'INT-6', 'vat_rate' => 10], 'existing' => null, 'exec' => []];
function db_fetch(string $s, array $p = []): ?array {
    global $DB;
    if (str_contains($s, "source = 'technicien'")) return $DB['existing'];
    if (str_contains($s, 'FROM interventions')) return $DB['iv'];
    return null;
}
function db_fetch_all(string $s, array $p = []): array { return []; }
function db_execute(string $s, array $p = []): bool { global $DB; $DB['exec'][] = [$s, $p]; return true; }
function db_last_id(): int { return 42; }
$MAILS = [];
function notif_mail(string $to, string $subject, string $title, array $lines, ?string $url = null, ?string $label = null): bool { global $MAILS; $MAILS[] = [$to, $subject, $lines, $url]; return true; }

$ROOT = dirname(__DIR__);
require $ROOT.'/includes/helpers.php';
require $ROOT.'/includes/integrations.php';
require $ROOT.'/includes/review.php';
require $ROOT.'/includes/pricing.php';
require $ROOT.'/includes/yousign.php';
if (!function_exists('notif_abs_url')) { function notif_abs_url(string $p): string { return 'https://emaee.fr/'.$p; } }

$pass = 0; $fail = 0;
function check(string $l, mixed $g, mixed $w): void { global $pass, $fail;
    if ($g === $w) { $pass++; echo "  ok   $l\n"; }
    else { $fail++; echo "  FAIL $l — obtenu ".var_export($g, true)." / attendu ".var_export($w, true)."\n"; } }
$find = static function (string $needle): array { global $DB; return array_values(array_filter($DB['exec'], static fn($e) => str_contains($e[0], $needle))); };
$tech = ['id' => 1, 'name' => 'Thomas'];

echo "\n1. Création du brouillon\n";
check('texte vide : rien', devis_from_tech(6, '  ', $tech), null);
$id = devis_from_tech(6, 'Remplacer le ballon 200 L, prévoir groupe de sécurité.', $tech);
check('brouillon créé', $id, 42);
$ins = $find('INSERT INTO devis')[0] ?? ['', []];
check('sans ligne ni prix (à chiffrer par le bureau)', [str_contains($ins[0], "'[]'"), str_contains($ins[0], ',0,0,0,')], [true, true]);
check('marqué « demandé par le technicien »', str_contains($ins[0], "'technicien'"), true);
check('titre : métier + début du texte', $ins[1][0], 'Plomberie — Remplacer le ballon 200 L, prévoir groupe de sécurité.');
check('numéro DEV-année-00042', ($find('UPDATE devis SET number')[0][1][0] ?? null), 'DEV-'.date('Y').'-00042');
check('historique de l\'intervention', str_contains((string)($find('intervention_history')[0][1][6] ?? ''), 'Devis à faire pour plus tard'), true);

echo "\n2. Dispatcher prévenu\n";
check('un e-mail', count($MAILS), 1);
check('objet', $MAILS[0][1] ?? null, 'Devis à faire — INT-6');
check('lien vers le devis de la fiche', str_ends_with((string)($MAILS[0][3] ?? ''), 'intervention_view.php?id=6#devis'), true);
check('le texte du technicien est échappé', str_contains(implode(' ', $MAILS[0][2]), '<script>'), false);

echo "\n3. Rapport rendu à nouveau : même brouillon mis à jour\n";
$DB['exec'] = []; $MAILS = []; $DB['existing'] = ['id' => 42];
check('même devis', devis_from_tech(6, 'Remplacer le ballon 150 L finalement.', $tech), 42);
check('mis à jour, pas recréé', [count($find('INSERT INTO devis')), count($find('UPDATE devis SET title'))], [0, 1]);
check('pas de second e-mail', count($MAILS), 0);

echo "\n".($fail === 0 ? "TOUTES LES $pass ASSERTIONS PASSENT" : "$fail ÉCHEC(S) sur ".($pass + $fail))."\n";
exit($fail === 0 ? 0 : 1);
