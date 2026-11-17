<?php
declare(strict_types=1);
// Agent relation client (phase 1) : lecture des mails, tri sans IA, garde-fou des brouillons,
// coût, boutons Telegram, sécurités d'envoi. Sans réseau ni base de données.

$DB = ['settings' => [], 'integ' => []];
$ITEM = null;
function db_fetch_all(string $s, array $p = []): array { global $DB;
    if (str_contains($s, 'FROM integration_settings')) return array_values($DB['integ']);
    if (str_contains($s, 'FROM settings')) return array_map(fn($k, $v) => ['setting_key' => $k, 'setting_value' => $v], array_keys($DB['settings']), $DB['settings']);
    return []; }
function db_fetch(string $s, array $p = []): ?array { global $ITEM;
    if (str_contains($s, 'FROM agent_items')) return $ITEM;
    return null; }
function db_execute(string $s, array $p = []): void { global $DB, $ITEM;
    if (str_starts_with($s, 'INSERT INTO integration_settings')) $DB['integ'][$p[0]] = ['cle' => $p[0], 'valeur' => $p[1], 'secret' => $p[2]];
    if (str_contains($s, "SET status = 'sending'") && $ITEM && $ITEM['status'] === 'pending') $ITEM['status'] = 'sending'; }
function db_last_id(): int { return 1; }
define('EMAE_TESTS_SANS_RESEAU', true);

$_SERVER['SCRIPT_NAME'] = '/index.php';
$R = dirname(__DIR__);
require $R.'/includes/helpers.php';
require $R.'/includes/integrations.php';
require $R.'/includes/claude.php';
require $R.'/includes/mailbox.php';
require $R.'/includes/agent_client.php';

$pass = 0; $fail = 0;
function check(string $l, mixed $g, mixed $w): void { global $pass, $fail;
    if ($g === $w) { $pass++; echo "  ok   $l\n"; }
    else { $fail++; echo "  FAIL $l\n        obtenu  ".var_export($g, true)."\n        attendu ".var_export($w, true)."\n"; } }
function iset(string $k, string $v): void { agent_set($k, $v); }

echo "\n1. Lecture des mails : encodages, parties multiples, pièces jointes\n";
$m1 = "From: =?ISO-8859-1?Q?Ren=E9?= Dupont <Rene.Dupont@Exemple.fr>\r\n"
    . "Subject: =?UTF-8?B?".base64_encode('Fuite sous l’évier à Dole')."?=\r\n"
    . "Message-ID: <abc123@exemple.fr>\r\nDate: Mon, 09 Nov 2026 08:15:00 +0100\r\n"
    . "MIME-Version: 1.0\r\nContent-Type: multipart/alternative; boundary=\"b1\"\r\n\r\n"
    . "--b1\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: quoted-printable\r\n\r\n"
    . "Bonjour, j'ai une fuite sous l'=C3=A9vier depuis ce matin.\r\nMerci\r\n"
    . "--b1\r\nContent-Type: text/html; charset=UTF-8\r\n\r\n<p>Bonjour, <b>HTML</b></p>\r\n--b1--\r\n";
$p = mime_parse($m1);
check('expéditeur : nom décodé (ISO-8859-1)', $p['from_name'], 'René Dupont');
check('expéditeur : adresse en minuscules', $p['from_email'], 'rene.dupont@exemple.fr');
check('objet décodé (UTF-8, base64)', $p['subject'], 'Fuite sous l’évier à Dole');
check('Message-ID sans chevrons', $p['message_id'], 'abc123@exemple.fr');
check('texte préféré au HTML, quoted-printable décodé', str_contains($p['text'], "fuite sous l'évier depuis ce matin"), true);
check('date convertie', $p['date'], date('Y-m-d H:i:s', strtotime('Mon, 09 Nov 2026 08:15:00 +0100')));

$m2 = "From: client@exemple.fr\r\nSubject: chauffe-eau\r\nContent-Type: text/plain; charset=windows-1252\r\nContent-Transfer-Encoding: 8bit\r\n\r\n"
    . mb_convert_encoding("Mon chauffe-eau est cassé, c'est très urgent.", 'Windows-1252', 'UTF-8');
check('texte Windows-1252 converti en UTF-8', mime_parse($m2)['text'], "Mon chauffe-eau est cassé, c'est très urgent.");

$m3 = "From: \"Syndic Exemple\" <gestion@syndic-exemple.fr>\r\nSubject: Devis façade\r\nContent-Type: multipart/mixed; boundary=XYZ\r\n\r\n"
    . "--XYZ\r\nContent-Type: text/plain; charset=utf-8\r\nContent-Transfer-Encoding: base64\r\n\r\n".chunk_split(base64_encode("Merci de nous faire un devis pour l'immeuble."))."\r\n"
    . "--XYZ\r\nContent-Type: application/pdf; name=\"x.pdf\"\r\nContent-Disposition: attachment; filename*=UTF-8''cahier%20des%20charges%20fa%C3%A7ade.pdf\r\nContent-Transfer-Encoding: base64\r\n\r\nJVBERi0xLjQK\r\n"
    . "--XYZ\r\nContent-Type: image/jpeg\r\nContent-Transfer-Encoding: base64\r\n\r\n/9j/4AAQ\r\n--XYZ--\r\n";
$p3 = mime_parse($m3);
check('texte en base64 décodé', $p3['text'], "Merci de nous faire un devis pour l'immeuble.");
check('nom de pièce jointe RFC 2231 décodé', $p3['attachments'][0] ?? '', 'cahier des charges façade.pdf');
check('image sans nom comptée comme pièce jointe', $p3['attachments'][1] ?? '', 'image/jpeg');
check('nom entre guillemets', $p3['from_name'], 'Syndic Exemple');

$m4 = "From: a@b.fr\r\nSubject: html\r\nContent-Type: text/html; charset=utf-8\r\n\r\n<html><head><style>p{}</style></head><body><p>Ligne&nbsp;1</p><p>Ligne 2 &amp; fin</p></body></html>";
check('HTML seul : converti en texte lisible', mime_parse($m4)['text'], "Ligne 1\nLigne 2 & fin");

$tronque = substr($m3, 0, (int)(strlen($m3) * 0.6));
check('message tronqué : pas d\'erreur, texte gardé', mime_parse($tronque)['text'], "Merci de nous faire un devis pour l'immeuble.");

echo "\n2. Historique cité retiré avant l'envoi à l'IA\n";
$rep = "Oui, mardi c'est possible.\n\nLe lun. 9 nov. 2026 à 10:02, EMAE <contact@emaee.fr> a écrit :\n> Bonjour, quand êtes-vous disponible ?\n> Cordialement";
check('« Le … a écrit : » coupe la suite', mime_strip_quoted($rep), 'Oui, mardi c\'est possible.');
check('lignes « > » retirées', mime_strip_quoted("Réponse\n> ancien\nsuite"), "Réponse\nsuite");
check('« Envoyé de mon iPhone » coupé', mime_strip_quoted("Ok merci\n\nEnvoyé de mon iPhone"), 'Ok merci');

echo "\n3. Message fabriqué par l'agent : relu à l'identique\n";
$built = mime_build(['from' => 'contact@emaee.fr', 'from_name' => 'EMAE', 'to' => 'rene.dupont@exemple.fr', 'subject' => 'Re: Fuite sous l’évier à Dole',
    'in_reply_to' => '<abc123@exemple.fr>', 'references' => '<abc123@exemple.fr>', 'extra' => ['X-EMAE-Item' => '42']], "Bonjour René,\nNous revenons vers vous très vite.\n\nL'équipe EMAE");
$rb = mime_parse($built);
check('objet accentué conservé', $rb['subject'], 'Re: Fuite sous l’évier à Dole');
check('corps accentué conservé', $rb['text'], "Bonjour René,\nNous revenons vers vous très vite.\n\nL'équipe EMAE");
check('réponse rattachée au fil (In-Reply-To)', $rb['in_reply_to'], '<abc123@exemple.fr>');
check('marque X-EMAE-Item présente', $rb['headers']['x-emae-item'] ?? '', '42');
check('aucune ligne ne commence par un point seul (protocole SMTP)', preg_match('/^\.\r?$/m', $built), 0);

echo "\n4. Noms de dossiers accentués (IMAP)\n";
check('« Client mécontent » encodé pour IMAP', ImapLite::utf7('Client mécontent'), 'Client m&AOk-content');
check('et relu à l\'identique', ImapLite::utf8('Client m&AOk-content'), 'Client mécontent');
check('nom simple inchangé', ImapLite::utf7('Devis'), 'Devis');
check('guillemets protégés dans les commandes', ImapLite::q('mot"de\\passe'), '"mot\\"de\\\\passe"');
check('« / » interdit dans un nom de dossier (créerait un sous-dossier)', agent_folder_name('pub_spam'), 'Pub-Spam');

echo "\n5. Tri sans IA\n";
check('newsletter écartée', agent_prefilter(['list-unsubscribe' => '<mailto:x>'], 'news@leroy.fr', 'Promos de novembre')['category'] ?? null, 'pub_spam');
check('mais facture avec désabonnement : confiée à l\'IA', agent_prefilter(['list-unsubscribe' => '<mailto:x>'], 'factures@fournisseur.fr', 'Votre facture n° 1234'), null);
check('réponse automatique : rangée sans réponse', agent_prefilter(['auto-submitted' => 'auto-replied'], 'x@y.fr', 'Absent')['status'] ?? null, 'classified');
check('« Réponse automatique » dans l\'objet', agent_prefilter([], 'x@y.fr', 'Réponse automatique : absent')['reason'] ?? null, 'réponse automatique');
check('spam signalé par le serveur', agent_prefilter(['x-spam-flag' => 'YES'], 'x@y.fr', 'Gagnez')['category'] ?? null, 'pub_spam');
check('mail envoyé par le site : ignoré', agent_prefilter(['x-emae-source' => 'site'], 'contact@emaee.fr', 'Nouvelle demande')['status'] ?? null, 'ignored');
check('non-distribution : rangée', agent_prefilter([], 'MAILER-DAEMON@mail.fr', 'Undelivered')['reason'] ?? null, 'avis de non-distribution');
check('no-reply : l\'IA classe, sans brouillon', agent_prefilter([], 'no-reply@banque.fr', 'Relevé')['no_reply'] ?? null, true);
check('client ordinaire : confié à l\'IA', agent_prefilter([], 'rene.dupont@exemple.fr', 'Fuite sous l\'évier'), null);
iset('agent_block', "prospection-web.com\nspam@x.fr");
iset('agent_allow', 'syndic-exemple.fr');
check('liste « à bloquer » (domaine)', agent_prefilter([], 'commercial@prospection-web.com', 'Boostez votre SEO')['category'] ?? null, 'pub_spam');
check('liste « à bloquer » : pas un domaine voisin', agent_prefilter([], 'a@monprospection-web.com', 'Bonjour'), null);
check('liste « à autoriser » passe avant le filtre newsletter', agent_prefilter(['list-id' => 'x'], 'info@syndic-exemple.fr', 'Lettre du syndic'), null);

echo "\n6. Prix, dates et délais : signalés pour relecture ; « [à compléter] » bloque l'envoi\n";
check('brouillon complet : rien ne bloque', agent_blocker('Le déplacement est à 44 € HT, nous pouvons passer mardi matin.'), '');
check('« [à compléter : …] » bloque', agent_blocker('Le remplacement coûte [à compléter : prix du mitigeur].') !== '', true);
check('« [mardi matin, à confirmer] » bloque', agent_blocker('Nous pouvons passer [mardi matin, à confirmer].') !== '', true);
check('crochets ordinaires : ne bloquent pas', agent_blocker('Référence [F-2026-118].'), '');
check('texte sans engagement : accepté', agent_guard("Bonjour,\nPouvez-vous nous envoyer des photos ? Appelez le 03 56 57 03 03, joignable 24h/24 et 7j/7."), '');
check('prix en euros détecté', str_contains(agent_guard('Le déplacement est à 44 € HT.'), 'montant'), true);
check('« 150 euros » détecté', str_contains(agent_guard('Comptez environ 150 euros.'), 'montant'), true);
check('heure détectée', str_contains(agent_guard('Le technicien passe à 14h30.'), 'heure'), true);
check('date détectée', str_contains(agent_guard('Rendez-vous le 12 novembre.'), 'date'), true);
check('date chiffrée détectée', str_contains(agent_guard('Intervention prévue le 12/11.'), 'date'), true);
check('délai détecté', str_contains(agent_guard('Nous intervenons sous 2 heures.'), 'délai'), true);
check('« demain » détecté', str_contains(agent_guard('Un technicien passera demain.'), 'délai'), true);
check('numéro de téléphone : pas pris pour une date ou une heure', agent_guard('Rappelez-nous au +33 3 56 57 03 03.'), '');

echo "\n7. Coût réel d'un appel\n";
check('Haiku : 1000 jetons lus, 200 écrits', agent_cost_usd('claude-haiku-5-5', ['input_tokens' => 1000, 'output_tokens' => 200]), 0.0002);
check('Sonnet avec consignes en cache (lecture à 10 %)', agent_cost_usd('claude-sonnet-5-5', ['input_tokens' => 100, 'cache_read_input_tokens' => 2000, 'output_tokens' => 500]), 0.0056);
check('écriture en cache comptée à 125 %', agent_cost_usd('claude-sonnet-5-5', ['cache_creation_input_tokens' => 1000]), 0.0025);
iset('agent_usd_eur', '0.9');
check('conversion en euros', round(agent_eur(1.0), 2), 0.9);

echo "\n8. Réponse : objet et adresse\n";
check('« Re: » ajouté', agent_subject_re('Fuite'), 'Re: Fuite');
check('pas de « Re: Re: »', agent_subject_re('RE: Fuite'), 'RE: Fuite');
check('objet vide', agent_subject_re(''), 'Re: votre demande');
check('Reply-To prioritaire', agent_reply_address(['reply_to' => 'Vrai@Client.fr', 'from_email' => 'noreply@plateforme.fr']), 'vrai@client.fr');

echo "\n9. Telegram : boutons selon le mode\n";
$it = ['id' => 7, 'draft_warning' => null];
iset('agent_mode', 'test');
$kb = agent_tg_keyboard($it)['inline_keyboard'][0];
check('mode test : « Bon tel quel », pas d\'« Envoyer »', array_column($kb, 'callback_data'), ['ag:g:7', 'ag:e:7', 'ag:r:7']);
iset('agent_mode', 'live');
check('envoi activé : « Envoyer »', array_column(agent_tg_keyboard($it)['inline_keyboard'][0], 'callback_data'), ['ag:s:7', 'ag:e:7', 'ag:r:7']);
check('brouillon avec prix : pas de bouton « Envoyer »', array_column(agent_tg_keyboard(['id' => 7, 'draft_warning' => 'Contient un montant'])['inline_keyboard'][0], 'callback_data'), ['ag:e:7', 'ag:r:7']);

echo "\n10. Sécurités d'envoi\n";
$base = ['id' => 9, 'status' => 'pending', 'draft_warning' => null, 'account' => 'gmail', 'from_email' => 'client@exemple.fr', 'reply_to' => '', 'draft_body' => 'Bonjour', 'source' => 'mail', 'message_id' => 'm@x', 'refs' => '', 'subject' => 'x'];
iset('agent_mode', 'test');
$ITEM = $base;
check('mode test : rien ne part', agent_send_reply(9), 'Mode test : aucun envoi.');
iset('agent_mode', 'live');
$ITEM = ['status' => 'sent'] + $base;
check('déjà envoyé : pas de second envoi', agent_send_reply(9), 'Déjà traité (sent).');
$ITEM = ['draft_warning' => 'Contient un montant'] + $base;
check('prix détecté : envoi bloqué', str_starts_with(agent_send_reply(9), 'Brouillon à corriger'), true);
$ITEM = ['from_email' => 'pas-une-adresse'] + $base;
check('adresse invalide : refus', agent_send_reply(9), 'Adresse du destinataire invalide.');
$ITEM = $base;
check('boîte sans mot de passe : refus', agent_send_reply(9), 'La boîte d\'envoi n\'est pas configurée.');
check('webhook : un autre téléphone ne peut rien faire', (function () { global $ITEM; $ITEM = ['id' => 9, 'status' => 'pending'] + $GLOBALS['base'];
    iset('telegram_chat_id', '111'); agent_tg_handle(['callback_query' => ['id' => 'c', 'data' => 'ag:r:9', 'message' => ['chat' => ['id' => 222]]]]); return $ITEM['status']; })(), 'pending');

echo "\n11. Schémas : catégories et consignes cohérentes\n";
check('les catégories du tri IA sont celles des dossiers', agent_triage_schema()['properties']['category']['enum'], array_keys(agent_categories()));
check('consignes de tri assez longues pour le cache (≥ 512 jetons, environ 2 000 caractères)', mb_strlen(agent_triage_system()) > 2000, true);
check('consignes de rédaction : la liste de prix du propriétaire est fournie', str_contains(agent_writer_system(), 'déplacement 44 €'), true);
check('consignes de rédaction : aucun autre prix autorisé', str_contains(agent_writer_system(), 'N\'annonce aucun autre prix'), true);
iset('agent_prices', 'Déplacement : 50 € HT');
check('prix modifiés dans l\'écran : repris par l\'agent', str_contains(agent_writer_system(), 'Déplacement : 50 € HT'), true);
check('mode par défaut : envoi activé (bouton Envoyer)', agent_defaults()['agent_mode'], 'live');

echo "\n12. Anciens mails\n";
iset('agent_start', '2026-11-10'); iset('agent_history_days', '0');
check('sans historique : depuis la mise en marche', agent_history_since(), '2026-11-10');
iset('agent_history_days', '30');
check('30 jours d\'historique', agent_history_since(), '2026-10-11');
iset('agent_history_reply_days', '7');
check('mail de 3 jours : brouillon', agent_too_old_to_reply(['source' => 'mail', 'received_at' => date('Y-m-d H:i:s', time() - 3 * 86400)]), false);
check('mail de 20 jours : rangé sans brouillon', agent_too_old_to_reply(['source' => 'mail', 'received_at' => date('Y-m-d H:i:s', time() - 20 * 86400)]), true);
check('demande du site : toujours une réponse', agent_too_old_to_reply(['source' => 'site', 'received_at' => '2020-01-01 00:00:00']), false);
check('modèle de tri : Haiku', AGENT_TRIAGE_MODEL, 'claude-haiku-5-5');
check('modèle de rédaction : Sonnet', AGENT_WRITER_MODEL, 'claude-sonnet-5-5');

echo "\n".($fail ? "ÉCHECS : $fail" : "TOUS LES TESTS PASSENT ($pass assertions)")."\n";
exit($fail ? 1 : 0);
