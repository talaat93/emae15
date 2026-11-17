<?php
declare(strict_types=1);
// Outils de conversion (novembre 2026) : bandeau, note Google, WhatsApp, rendez-vous,
// SMS automatiques, pages panne, paiements Stripe, contrats d'entretien, espace client.

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
$R = dirname(__DIR__);
require $R.'/includes/helpers.php';
require $R.'/includes/workflow.php';
require $R.'/includes/growth.php';
boot_session();   // avant tout affichage (le formulaire lit le jeton de sécurité)
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

echo "\n1. WhatsApp : numéro au bon format, numéro incomplet signalé\n";
check('06 → 336', whatsapp_digits('06 12 34 56 78'), '33612345678');
check('+33 6 → 336', whatsapp_digits('+33 6 12 34 56 78'), '33612345678');
check('+33 0 6 → 336', whatsapp_digits('+33 (0)6 12 34 56 78'), '33612345678');
check('numéro complet : pas d\'alerte', whatsapp_number_problem('+33612345678'), '');
check('numéro à 8 chiffres : alerte', str_contains(whatsapp_number_problem('+3367830376'), '8 chiffres'), true);
set('company_whatsapp', '0612345678');
check('lien avec message prérempli', str_starts_with(whatsapp_url(), 'https://wa.me/33612345678?text=Bonjour'), true);

echo "\n2. Bandeau : ouvert pendant les horaires, message d'urgence sinon\n";
$tz = new DateTimeZone('Europe/Paris');
check('mardi 10 h : ouvert', availability_now(new DateTimeImmutable('2026-11-10 10:00', $tz))['open'], true);
check('mardi 21 h : fermé', availability_now(new DateTimeImmutable('2026-11-10 21:00', $tz))['open'], false);
check('dimanche 10 h : fermé', availability_now(new DateTimeImmutable('2026-11-08 10:00', $tz))['open'], false);
check('8 h pile : ouvert', availability_now(new DateTimeImmutable('2026-11-10 08:00', $tz))['open'], true);
check('20 h pile : fermé', availability_now(new DateTimeImmutable('2026-11-10 20:00', $tz))['open'], false);
set('avail_days', '1,2,3,4,5,6,7'); set('avail_start', '00:00'); set('avail_end', '23:59');
check('24h/24 si réglé ainsi', availability_now(new DateTimeImmutable('2026-11-08 03:00', $tz))['open'], true);
set('avail_days', '1,2,3,4,5,6'); set('avail_start', '08:00'); set('avail_end', '20:00');

echo "\n3. Note Google : jamais de chiffre inventé\n";
check('rien de saisi : pas de badge', render_google_badge(), '');
set('google_rating', '4,8'); set('google_review_count', '57');
check('note sans lien : pas de badge', google_rating(), null);
set('google_reviews_url', 'https://g.page/r/exemple/review');
check('lien + note + nombre : badge', str_contains(render_google_badge(), '4,8/5</strong> · 57 avis Google'), true);
set('google_rating', '7');
check('note impossible (7/5) : pas de badge', google_rating(), null);
set('google_reviews_url', 'javascript:alert(1)'); set('google_rating', '4.8');
check('lien non https : pas de badge', google_rating(), null);

echo "\n4. Messages automatiques\n";
check('variables remplacées', growth_fill('{entreprise} : bonjour {nom}, merci !', ['nom' => 'Jean', 'entreprise' => 'EMAE']), 'EMAE : bonjour Jean, merci !');
check('prénom vide : pas de « bonjour , »', growth_fill('Bonjour {nom}, merci', ['nom' => '']), 'Bonjour, merci');
check('prénom extrait', quote_first_name('  Jean   Dupont '), 'Jean');
check('06 = mobile', phone_is_mobile_fr('06 12 34 56 78'), true);
check('+33 7 = mobile', phone_is_mobile_fr('+33 7 12 34 56 78'), true);
check('fixe 03 : pas de SMS', phone_is_mobile_fr('03 81 00 00 00'), false);
check('mêmes 9 derniers chiffres', phone_last9('+33 6 12 34 56 78') === phone_last9('06.12.34.56.78'), true);
check('SMS non configuré par défaut', sms_configured(), false);

echo "\n5. Type de demande\n";
check('rendez-vous', quote_request_type('rdv'), 'rdv');
check('entretien', quote_request_type('entretien'), 'entretien');
check('valeur inconnue = devis', quote_request_type('<b>'), 'devis');
check('objet du mail rendez-vous', quote_type_info('rdv')[3], '📅 RENDEZ-VOUS — ');
check('expéditeur entretien', quote_type_info('entretien')[0], 'Entretien');

echo "\n6. Rendez-vous en ligne\n";
check('créneaux par défaut', array_keys(booking_slots()), ['matin-8h-12h', 'apres-midi-14h-18h']);
$days = booking_days(new DateTimeImmutable('2026-11-07', $tz));   // samedi
check('à partir du lendemain, sans le dimanche', $days[0], '2026-11-09');
check('aucun dimanche proposé', count(array_filter($days, fn($d) => date('N', strtotime($d)) === '7')), 0);
check('jour en toutes lettres', booking_day_label('2026-11-12'), 'jeudi 12 novembre');
check('créneau inventé refusé', booking_validate($days[0], 'minuit'), null);
check('date hors liste refusée', booking_validate('2020-01-01', 'matin-8h-12h'), null);

echo "\n7. Pages panne\n";
foreach (array_keys(panne_defaults()) as $slug) {
    $p = panne_page($slug);
    if (!$p || $p['h1'] === '' || !$p['checks'] || !$p['never'] || !service_template($p['trade'])) check('page '.$slug.' complète', false, true);
}
check('8 pages complètes, chacune rattachée à un métier', count(panne_defaults()), 8);
check('chaudière : numéro officiel Urgence Sécurité Gaz', str_contains(panne_page('chaudiere-en-panne')['alert'], '0 800 47 33 33'), true);
check('liens sur la page Plomberie', count(pannes_for_trade('plomberie')), 3);
set('pannes_enabled', '0');
check('pages désactivées : plus de liens', pannes_for_trade('plomberie'), []);
set('pannes_enabled', '1');

echo "\n8. Adresses : redirection des anciennes adresses, 404 pour le reste\n";
check('/plomberie existe', public_route_exists('plomberie'), true);
check('/chaudiere-en-panne existe', public_route_exists('chaudiere-en-panne'), true);
check('/tests inconnu', public_route_exists('tests'), false);
$src = (string)file_get_contents($R.'/index.php');
check('index.php : adresse inconnue = vraie 404', str_contains($src, "\$route = '__introuvable__';"), true);
check('index.php : page désactivée = 404, pas la page métier', str_contains($src, "if (\$_extra[\$route][0] || admin_logged_in())"), true);

echo "\n9. Paiements Stripe\n";
check('120 → 12000 centimes', payment_amount_cents('120'), 12000);
check('185,50 € → 18550', payment_amount_cents('185,50 €'), 18550);
check('texte refusé', payment_amount_cents('cent euros'), 0);
check('3 décimales refusées', payment_amount_cents('1,234'), 0);
check('désactivé sans clé', payments_enabled(), false);
$payload = '{"type":"checkout.session.completed"}'; $secret = 'whsec_test'; $t = 1_800_000_000;
$good = 't='.$t.',v1='.hash_hmac('sha256', $t.'.'.$payload, $secret);
check('signature correcte acceptée', stripe_signature_valid($payload, $good, $secret, $t + 10), true);
check('corps modifié refusé', stripe_signature_valid($payload.' ', $good, $secret, $t + 10), false);
check('mauvais secret refusé', stripe_signature_valid($payload, $good, 'whsec_autre', $t + 10), false);
check('signature trop ancienne refusée', stripe_signature_valid($payload, $good, $secret, $t + 3600), false);
check('sans secret configuré : refusé', stripe_signature_valid($payload, $good, '', $t), false);

echo "\n10. Contrats d'entretien\n";
check('+12 mois', contract_add_months('2026-03-15', 12), '2027-03-15');
check('31 janvier + 1 mois = 28 février', contract_add_months('2027-01-31', 1), '2027-02-28');
check('29 février + 12 mois = 28 février', contract_add_months('2028-02-29', 12), '2029-02-28');
check('+24 mois (pompe à chaleur)', contract_add_months('2026-11-08', 24), '2028-11-08');
$txt = contract_reminder_text(['client_name' => 'Marie Durand', 'equipment' => 'Chaudière gaz', 'next_visit' => '2026-12-01']);
check('rappel : prénom, appareil et date', str_contains($txt, 'bonjour Marie') && str_contains($txt, 'chaudière gaz') && str_contains($txt, '01/12/2026'), true);

echo "\n11. Espace client et suivi\n";
check('e-mail en minuscules', client_ident(' Jean@Exemple.FR '), 'jean@exemple.fr');
check('téléphone = 9 derniers chiffres', client_ident('06 12 34 56 78'), '612345678');
check('saisie invalide', client_ident('bonjour'), '');
check('espace fermé par défaut', client_space_enabled(), false);
$steps = track_steps(['status' => 'en_route', 'scheduled_date' => '2026-11-12', 'scheduled_time' => '14:00:00', 'tech_started_at' => '2026-11-12 13:40:00']);
check('suivi : planifiée faite, en route en cours', [$steps[0][2], $steps[1][2], $steps[2][2]], ['done', 'cur', '']);
check('suivi : heure de départ affichée', $steps[1][1], '13:40');
$steps = track_steps(['status' => 'rapport_rendu']);
check('suivi : intervention terminée', $steps[3][2], 'done');

echo "\n12. Formulaires\n";
ob_start(); render_quote_form([], 'test'); $f = (string)ob_get_clean();
check('demandes non terminées : rien sans accord', str_contains($f, 'data-partial'), false);
check('durée de conservation des photos annoncée', str_contains($f, 'supprimées automatiquement après 12 mois'), true);
set('partial_enabled', '1');   // ancien réglage resté coché : la collecte sans clic reste retirée
ob_start(); render_quote_form([], 'test'); $f = (string)ob_get_clean();
check('ancien réglage coché : rien n\'est envoyé avant le clic', str_contains($f, 'data-partial') || str_contains($f, 'partial_token'), false);
check('ancien réglage coché : le serveur refuse la saisie en cours', partial_save(['partial_token' => str_repeat('a', 32), 'phone' => '0612345678', 'full_name' => 'X']), false);
ob_start(); render_quote_form([], 'estimation', 'devis', ['message' => 'Débouchage : ']); $f = (string)ob_get_clean();
check('message prérempli', str_contains($f, '>Débouchage : </textarea>'), true);

echo "\n";
echo $fail === 0 ? "$pass assertions PASSENT\n" : "$fail ÉCHECS sur ".($pass + $fail)."\n";
exit($fail === 0 ? 0 : 1);
