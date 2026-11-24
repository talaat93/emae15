<?php
declare(strict_types=1);
/**
 * Travaux confiés au PC du bureau (includes/pc_jobs.php) : lecture de la réponse du PC,
 * vérification contre le schéma, verrou (une seule application), relais par l'API quand le PC
 * répond mal ou trop tard, et correction confiée au PC seulement s'il est connecté et à jour.
 * Base en mémoire ; Claude remplacé : aucun réseau.
 */
define('EMAE_TESTS_SANS_RESEAU', true);

$DB = ['jobs' => [], 'iv' => ['id' => 3, 'status' => 'rapport_rendu', 'category' => 'plomberie', 'tech_diagnostic' => 'fuite au raccord sous l evier', 'tech_report' => 'joint changer plus de fuite', 'technician_id' => null, 'ref' => 'INT-3'], 'set' => [], 'exec' => []];
function db_fetch(string $s, array $p = []): ?array {
    global $DB;
    if (str_contains($s, 'FROM pc_jobs WHERE id')) return $DB['jobs'][(int)$p[0]] ?? null;
    if (str_contains($s, "FROM pc_jobs WHERE status = 'en_attente'")) { foreach ($DB['jobs'] as $j) if ($j['status'] === 'en_attente') return $j; return null; }
    if (str_contains($s, 'FROM pc_jobs WHERE intervention_id')) { foreach (array_reverse($DB['jobs']) as $j) if ($j['intervention_id'] === (int)$p[0] && $j['kind'] === $p[1] && in_array($j['status'], ['en_attente', 'en_cours'], true)) return $j; return null; }
    if (str_contains($s, 'FROM interventions')) return $DB['iv'];
    return null;
}
function db_fetch_all(string $s, array $p = []): array {
    global $DB;
    if (str_contains($s, 'integration_settings')) return [];
    if (str_contains($s, 'FROM pc_jobs')) return array_values(array_filter($DB['jobs'], static fn($j) => in_array($j['status'], ['en_attente', 'en_cours'], true) && $j['created_at'] < $p[0]));
    return [];
}
function db_execute(string $s, array $p = []): bool {
    global $DB; $DB['exec'][] = [$s, $p];
    if (str_starts_with($s, 'INSERT INTO pc_jobs')) { $id = count($DB['jobs']) + 1; $DB['jobs'][$id] = ['id' => $id, 'kind' => $p[0], 'intervention_id' => $p[1], 'trigger_by' => $p[2], 'prompt' => $p[3], 'status' => 'en_attente', 'relay_token' => null, 'claimed_at' => null, 'created_at' => date('Y-m-d H:i:s')]; }
    if (str_contains($s, "SET status = 'remplace', done_at = NOW() WHERE intervention_id")) foreach ($DB['jobs'] as &$j) if ($j['intervention_id'] === $p[0] && $j['kind'] === $p[1] && in_array($j['status'], ['en_attente', 'en_cours'], true)) $j['status'] = 'remplace';
    unset($j);
    if (str_contains($s, 'UPDATE pc_jobs SET status = ?, relay_token = ?')) { $j = &$DB['jobs'][(int)$p[2]]; if (in_array($j['status'], ['en_attente', 'en_cours'], true)) { $j['status'] = $p[0]; $j['relay_token'] = $p[1]; } unset($j); }
    if (str_contains($s, "SET status = 'en_cours', claimed_at")) $DB['jobs'][(int)$p[0]]['status'] = 'en_cours';
    if (str_starts_with($s, 'UPDATE interventions SET tech_diagnostic_corrige')) { $DB['iv']['tech_diagnostic_corrige'] = $p[0]; $DB['iv']['tech_report_corrige'] = $p[1]; $DB['iv']['correction_source'] = $p[2]; }
    return true;
}
function db_last_id(): int { global $DB; return count($DB['jobs']); }
$CLAUDE_CALLS = 0;
function claude_structured(array $req): array { global $CLAUDE_CALLS; $CLAUDE_CALLS++; return ['ok' => true, 'simulated' => false, 'refused' => false, 'model' => 'test', 'data' => ['diagnostic' => 'Fuite au raccord sous l’évier.', 'travaux' => 'Joint remplacé, plus de fuite.']]; }
// Réglages en mémoire (remplacent la table des réglages).
$SET = ['assist_pc_jobs' => '1'];
$SECRET = ['assist_token' => 'emae_aide_x'];
function integration_setting(string $n, string $d = ''): string { global $SET; return $SET[$n] ?? $d; }
function set_integration_setting(string $n, string $v): void { global $SET; $SET[$n] = $v; }
function integration_secret(string $n): string { global $SECRET; return $SECRET[$n] ?? ''; }
function integration_log(string $a, string $b, array $c = []): void {}
function notif_enabled(string $k): bool { return false; }
function assist_last_poll(): ?int { return time(); }
$REVIEWS = [];
function review_schema(): array { return ['type' => 'object', 'additionalProperties' => false, 'required' => ['complete', 'vat_rate'], 'properties' => ['complete' => ['type' => 'boolean'], 'vat_rate' => ['type' => 'string', 'enum' => ['5.5', '10', '20']]]]; }
function review_finish(int $ivId, array $r, string $t = 'system'): array { global $REVIEWS; $REVIEWS[] = $r; return ['ok' => true]; }
function review_run(int $ivId, string $t = 'system', ?array $a = null, bool $pc = true): array { global $REVIEWS; $REVIEWS[] = ['relais' => true, 'pc' => $pc]; return ['ok' => true]; }

$ROOT = dirname(__DIR__);
require $ROOT.'/includes/helpers.php';
// json_schema_errors() seule, sans le reste du fichier des intégrations.
$src = file_get_contents($ROOT.'/includes/integrations.php');
$a = strpos($src, 'function json_schema_errors');
$depth = 0; $i = strpos($src, '{', $a);
for ($k = $i; $k < strlen($src); $k++) { if ($src[$k] === '{') $depth++; if ($src[$k] === '}' && --$depth === 0) break; }
eval(substr($src, $a, $k - $a + 1));
require $ROOT.'/includes/correction.php';
require $ROOT.'/includes/pc_jobs.php';

$pass = 0; $fail = 0;
function check(string $l, mixed $g, mixed $w): void { global $pass, $fail;
    if ($g === $w) { $pass++; echo "  ok   $l\n"; }
    else { $fail++; echo "  FAIL $l — obtenu ".var_export($g, true)." / attendu ".var_export($w, true)."\n"; } }

echo "\n1. Lecture de la réponse du PC\n";
check('JSON seul', pc_json_extract('{"a":1}'), ['a' => 1]);
check('JSON entre ```json', pc_json_extract("Voici :\n```json\n{\"a\":2}\n```\nBonne journée"), ['a' => 2]);
check('JSON précédé d\'une phrase', pc_json_extract('Résultat : {"a":3}'), ['a' => 3]);
check('pas de JSON', pc_json_extract('Je ne peux pas répondre.'), null);
check('JSON cassé', pc_json_extract('{"a":'), null);

echo "\n2. PC connecté ou non\n";
check('script à jour jamais passé : pas de PC', pc_jobs_enabled(), false);
pc_jobs_touch_poll();
check('script à jour passé à l\'instant : PC', pc_jobs_enabled(), true);
$SET['assist_pc_jobs'] = '0';
check('case décochée : pas de PC', pc_jobs_enabled(), false);
$SET['assist_pc_jobs'] = '1';
$SECRET['assist_token'] = '';
check('clé supprimée : pas de PC', pc_jobs_enabled(), false);
$SECRET['assist_token'] = 'emae_aide_x';
$SET['assist_pc_jobs_poll'] = (string)(time() - 300);
check('PC silencieux depuis 5 min : pas de PC', pc_jobs_enabled(), false);
pc_jobs_touch_poll();

echo "\n3. Correction confiée au PC\n";
$r = correction_run(3, 'technicien');
check('en attente du PC', $r['pending'] ?? false, true);
check('aucun appel à l\'API', $CLAUDE_CALLS, 0);
check('travail créé', $DB['jobs'][1]['kind'] ?? '', 'correction');
check('consigne JSON dans le texte', str_contains($DB['jobs'][1]['prompt'], 'UNIQUEMENT par un objet JSON'), true);
check('pas de photo ni de client dans le texte', !str_contains($DB['jobs'][1]['prompt'], 'image') && str_contains($DB['jobs'][1]['prompt'], 'fuite au raccord'), true);
correction_run(3, 'technicien');
check('une seule correction en attente (l\'ancienne remplacée)', [$DB['jobs'][1]['status'], $DB['jobs'][2]['status']], ['remplace', 'en_attente']);
check('travail remplacé refusé', pc_job_finish(1, '{"diagnostic":"x","travaux":"y"}'), 'deja');
check('réponse du PC acceptée', pc_job_finish(2, "```json\n{\"diagnostic\":\"Fuite au raccord sous l’évier.\",\"travaux\":\"Joint changé, plus de fuite.\"}\n```"), 'ok');
check('correction rangée, source PC', [$DB['iv']['tech_report_corrige'] ?? null, $DB['iv']['correction_source'] ?? null], ['Joint changé, plus de fuite.', 'claude_pc']);
check('seconde réponse refusée (verrou)', pc_job_finish(2, '{"diagnostic":"a","travaux":"b"}'), 'deja');

echo "\n4. Réponse illisible ou trop tardive : relais\n";
$id = pc_job_create('correction', 3, 'texte', 'technicien');
check('réponse sans JSON : relais tout de suite', pc_job_finish($id, 'Désolé, je ne sais pas.'), 'invalide');
check('relais par l\'API (sans repasser par le PC)', [$DB['jobs'][$id]['status'], $CLAUDE_CALLS], ['relais', 1]);
$id = pc_job_create('relecture', 3, 'texte', 'technicien');
check('relecture : TVA écrite en nombre tolérée', pc_job_finish($id, '{"complete":true,"vat_rate":10}'), 'ok');
check('relecture appliquée avec le résultat du PC', [end($REVIEWS)['data']['vat_rate'] ?? null, end($REVIEWS)['model'] ?? null], ['10', 'PC du bureau']);
$id = pc_job_create('relecture', 3, 'texte', 'technicien');
check('relecture hors schéma : relais', pc_job_finish($id, '{"complete":"oui","vat_rate":"10"}'), 'invalide');
check('relecture relancée sans le PC', end($REVIEWS), ['relais' => true, 'pc' => false]);
$id = pc_job_create('relecture', 3, 'texte', 'technicien');
$DB['jobs'][$id]['created_at'] = date('Y-m-d H:i:s', time() - 400);
check('en retard de plus de 3 min : relayé', pc_jobs_process_overdue(), 1);
check('travail marqué relais', $DB['jobs'][$id]['status'], 'relais');
$id = pc_job_create('relecture', 3, 'texte', 'technicien');
$DB['jobs'][$id]['created_at'] = date('Y-m-d H:i:s', time() - 400);
$DB['iv']['status'] = 'rapport_verifie';
check('rapport déjà validé par le bureau : pas de relais', pc_jobs_process_overdue(), 0);
check('travail abandonné', $DB['jobs'][$id]['status'], 'remplace');

echo "\n5. Sans PC : clé API comme avant\n";
$SET['assist_pc_jobs'] = '0';
$before = $CLAUDE_CALLS;
$r = correction_run(3, 'dispatcher');
check('correction directe par l\'API', [$r['ok'], $r['pending'] ?? false, $CLAUDE_CALLS - $before], [true, false, 1]);

echo "\n".($fail === 0 ? "TOUTES LES $pass ASSERTIONS PASSENT" : "$fail ÉCHEC(S) sur ".($pass + $fail))."\n";
exit($fail === 0 ? 0 : 1);
