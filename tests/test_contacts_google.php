<?php
declare(strict_types=1);
// Google Contacts (novembre 2026) : contenu du contact, note de la demande, pas de doublon.

$DB = ['settings' => [], 'quotes' => []];
function db_fetch_all(string $s, array $p = []): array { global $DB;
    if (str_contains($s, 'FROM settings')) return array_map(fn($k, $v) => ['setting_key' => $k, 'setting_value' => $v], array_keys($DB['settings']), $DB['settings']);
    if (str_contains($s, "google_contact LIKE 'people/%'")) return array_values(array_filter($DB['quotes'], fn($q) => str_starts_with((string)$q['google_contact'], 'people/') && $q['id'] !== $p[0]));
    return []; }
function db_fetch(string $s, array $p = []): ?array { return null; }
function db_execute(string $s, array $p = []): void { global $DB;
    if (str_starts_with($s, 'INSERT INTO settings')) $DB['settings'][$p[0]] = $p[1];
    if (str_starts_with($s, "UPDATE quotes SET google_contact = 'pending'")) $DB['quotes'][$p[0]]['google_contact'] = 'pending'; }
function db_last_id(): int { return 1; }
define('EMAE_TESTS_SANS_RESEAU', true);

$_SERVER['SCRIPT_NAME'] = '/index.php';
$R = dirname(__DIR__);
require $R.'/includes/helpers.php';
require $R.'/includes/growth.php';
require $R.'/includes/integrations.php';
require $R.'/includes/google_api.php';
require $R.'/includes/google_contacts.php';

$pass = 0; $fail = 0;
function check(string $l, mixed $g, mixed $w): void { global $pass, $fail;
    if ($g === $w) { $pass++; echo "  ok   $l\n"; }
    else { $fail++; echo "  FAIL $l\n        obtenu  ".var_export($g, true)."\n        attendu ".var_export($w, true)."\n"; } }

echo "\n1. Téléphone : même clé quel que soit le format\n";
check('06 …', google_contact_phone_key('06 12 34 56 78'), '612345678');
check('+33 6 …', google_contact_phone_key('+33 6 12 34 56 78'), '612345678');
check('0033 …', google_contact_phone_key('0033612345678'), '612345678');
check('trop court : pas de clé', google_contact_phone_key('1234'), '');

echo "\n2. Contact : nom, téléphone, e-mail, adresse, note (prestation et date)\n";
$q = ['id' => 42, 'full_name' => 'Marie Durand', 'phone' => '06 12 34 56 78', 'email' => 'marie@example.fr', 'address' => '3 rue des Arènes',
      'postal_code' => '39100', 'city' => 'Dole', 'service_type' => 'Chauffage', 'message' => 'Entretien annuel de chaudière', 'request_type' => 'rdv',
      'source' => 'rendez-vous', 'created_at' => '2026-11-11 14:05:00'];
$p = google_contact_payload($q);
check('nom', $p['names'][0]['unstructuredName'], 'Marie Durand');
check('téléphone', $p['phoneNumbers'][0]['value'], '06 12 34 56 78');
check('e-mail', $p['emailAddresses'][0]['value'], 'marie@example.fr');
check('adresse', $p['addresses'][0], ['type' => 'home', 'streetAddress' => '3 rue des Arènes', 'postalCode' => '39100', 'city' => 'Dole', 'country' => 'France']);
check('note', $p['biographies'][0]['value'], 'Demande de rendez-vous EMAE du 11/11/2026 à 14:05 (n° 42) — Chauffage : Entretien annuel de chaudière');
$r = google_contact_payload(['id' => 7, 'full_name' => '', 'phone' => '0611111111', 'email' => 'pas-un-email', 'address' => '', 'postal_code' => '21000', 'city' => '',
      'service_type' => '', 'message' => 'Demande de rappel (formulaire express)', 'request_type' => 'rappel', 'created_at' => '2026-11-11 08:00:00']);
check('rappel express sans nom : nom de repli', $r['names'][0]['unstructuredName'], 'Client EMAE 21000');
check('e-mail invalide : non envoyé', isset($r['emailAddresses']), false);
check('adresse avec le seul code postal', $r['addresses'][0], ['type' => 'home', 'postalCode' => '21000', 'country' => 'France']);
check('société (suite d\'appel pro)', google_contact_payload($q + ['company_name' => 'SCI Les Tilleuls'])['organizations'][0]['name'], 'SCI Les Tilleuls');
check('message long coupé', mb_strlen(google_contact_note(['id' => 1, 'message' => str_repeat('a', 400), 'created_at' => '2026-11-11 08:00:00']), 'UTF-8') < 260, true);

echo "\n3. Pas de doublon : même téléphone ou même e-mail qu'une demande déjà synchronisée\n";
$DB['quotes'] = [
    10 => ['id' => 10, 'phone' => '+33 6 12 34 56 78', 'email' => '', 'google_contact' => 'people/c111'],
    11 => ['id' => 11, 'phone' => '07 00 00 00 01', 'email' => 'Paul@Example.fr', 'google_contact' => 'people/c222'],
    12 => ['id' => 12, 'phone' => '07 99 99 99 99', 'email' => '', 'google_contact' => 'err:3'],
];
check('même téléphone, autre format', google_contact_known($q), 'people/c111');
check('même e-mail, autre casse', google_contact_known(['id' => 50, 'phone' => '05 55 55 55 55', 'email' => 'paul@example.fr']), 'people/c222');
check('demande en échec : pas une référence', google_contact_known(['id' => 51, 'phone' => '07 99 99 99 99', 'email' => '']), '');
check('nouveau client', google_contact_known(['id' => 52, 'phone' => '06 00 00 00 00', 'email' => 'new@example.fr']), '');

echo "\n4. Désactivé ou compte non connecté : rien n'est mis en file\n";
$DB['quotes'][60] = ['id' => 60, 'phone' => '0600000000', 'email' => '', 'google_contact' => null];
google_contacts_queue(60);
check('non activé', $DB['quotes'][60]['google_contact'], null);
$DB['settings']['google_contacts_enabled'] = '1'; settings_cache(true);
google_contacts_queue(60);
check('activé mais compte non connecté', $DB['quotes'][60]['google_contact'], null);
check('tâche automatique : non configuré', google_contacts_process(), 'non configuré');

echo "\n";
echo $fail === 0 ? "$pass assertions PASSENT\n" : "$fail ÉCHECS sur ".($pass + $fail)."\n";
exit($fail === 0 ? 0 : 1);
