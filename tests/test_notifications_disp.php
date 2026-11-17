<?php
declare(strict_types=1);
/**
 * Notifications du dispatcher (includes/disp_notify.php) : ajout, dédoublonnage, liens sûrs,
 * alerte « fin estimée dépassée ». Base en mémoire, aucun réseau.
 */
define('EMAE_TESTS_SANS_RESEAU', true);

$DB = ['notifs' => [], 'eta' => []];
function db_fetch(string $s, array $p = []): ?array {
    global $DB;
    if (str_contains($s, 'WHERE dedupe_key')) { foreach ($DB['notifs'] as $n) if ($n['dedupe_key'] === $p[0]) return $n; return null; }
    if (str_contains($s, 'COUNT(*)')) return ['n' => count(array_filter($DB['notifs'], static fn($n) => $n['read_at'] === null))];
    return null;
}
function db_fetch_all(string $s, array $p = []): array { global $DB; return str_contains($s, "status = 'sur_place'") ? $DB['eta'] : $DB['notifs']; }
function db_execute(string $s, array $p = []): bool {
    global $DB;
    if (str_starts_with($s, 'INSERT INTO disp_notifications')) $DB['notifs'][] = ['id' => count($DB['notifs']) + 1, 'kind' => $p[0], 'title' => $p[1], 'body' => $p[2], 'url' => $p[3], 'intervention_id' => $p[4], 'dedupe_key' => $p[5], 'read_at' => null];
    if (str_contains($s, 'SET read_at = NOW() WHERE read_at IS NULL')) foreach ($DB['notifs'] as &$n) $n['read_at'] = 'x';
    return true;
}
function db_last_id(): int { return 1; }

require dirname(__DIR__).'/includes/disp_notify.php';

$pass = 0; $fail = 0;
function check(string $l, mixed $g, mixed $w): void { global $pass, $fail;
    if ($g === $w) { $pass++; echo "  ok   $l\n"; }
    else { $fail++; echo "  FAIL $l — obtenu ".var_export($g, true)." / attendu ".var_export($w, true)."\n"; } }

echo "\n1. Ajout et compteur\n";
check('7 types d\'alerte', count(disp_notif_kinds()), 7);
check('urgentes : devis à faire, refus, aide', array_keys(array_filter(disp_notif_kinds(), static fn($k) => $k['urgent'])), ['devis_a_faire', 'refus', 'aide']);
check('type inconnu refusé', disp_notify('inconnu', 'x'), false);
check('alerte ajoutée', disp_notify('rapport_rendu', 'Rapport rendu — INT-1', 'Thomas', 'dispatcher/intervention_view.php?id=1', 1), true);
check('compteur', disp_notif_unread_count(), 1);
check('dédoublonnage : la même alerte une seule fois', [disp_notify('paiement', 'P', '', '', 2, 'paiement-9'), disp_notify('paiement', 'P', '', '', 2, 'paiement-9')], [true, false]);
check('titre tronqué à 255 caractères', mb_strlen($DB['notifs'][0]['title']) <= 255 && mb_strlen((string)array_column($DB['notifs'], 'title')[1]) <= 255, true);
disp_notif_mark_read();
check('tout marquer comme lu', disp_notif_unread_count(), 0);

echo "\n2. Liens sûrs\n";
check('lien interne gardé', disp_notif_safe_url('dispatcher/intervention_view.php?id=3#devis'), 'dispatcher/intervention_view.php?id=3#devis');
check('adresse extérieure refusée', disp_notif_safe_url('https://exemple.com/piege'), 'dispatcher/index.php');
check('//domaine refusé', disp_notif_safe_url('//exemple.com'), 'dispatcher/index.php');
check('remontée de dossier refusée', disp_notif_safe_url('../config/config.local.php'), 'dispatcher/index.php');

echo "\n3. Fin estimée dépassée\n";
$end = time() - 45 * 60;
$DB['eta'] = [['id' => 7, 'ref' => 'INT-7', 'tech_eta_end' => date('Y-m-d H:i:s', $end), 'tech_name' => 'Thomas']];
check('une alerte créée', disp_notif_check_eta(), 1);
$last = end($DB['notifs']);
check('type et lien', [$last['kind'], $last['url']], ['eta_depassee', 'dispatcher/intervention_view.php?id=7']);
check('retard indiqué', str_contains($last['body'], 'retard : 45 min'), true);
check('clé : une alerte par estimation', $last['dedupe_key'], 'eta-7-'.$end);

echo "\n".($fail === 0 ? "TOUTES LES $pass ASSERTIONS PASSENT" : "$fail ÉCHEC(S) sur ".($pass + $fail))."\n";
exit($fail === 0 ? 0 : 1);
