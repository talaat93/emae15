<?php
declare(strict_types=1);
// Mails rappel / devis, photos envoyées par les clients, photos avant / après.

function db_fetch_all(string $s, array $p=[]): array { return []; }
function db_fetch(string $s, array $p=[]): ?array { return null; }
function db_execute(string $s, array $p=[]): void {}

$_SERVER['SCRIPT_NAME']='/index.php';
$R = dirname(__DIR__);
require $R.'/includes/helpers.php';
require $R.'/includes/render.php';

$pass=0;$fail=0;
function check(string $l,mixed $g,mixed $w): void { global $pass,$fail;
  if($g===$w){$pass++;echo "  ok   $l\n";}
  else{$fail++;echo "  FAIL $l\n        obtenu  ".var_export($g,true)."\n        attendu ".var_export($w,true)."\n";} }

$client = ['full_name'=>'Jean <b>Test</b>','phone'=>'06 11 22 33 44','email'=>'jean@example.invalid','city'=>'Dole','message'=>'Panne','urgency'=>'Urgente','quote_id'=>42];

echo "\n1. Le type de demande ne prend que deux valeurs\n";
check('rappel', quote_request_type('rappel'), 'rappel');
check('devis', quote_request_type('devis'), 'devis');
check('valeur inconnue = devis', quote_request_type('<script>'), 'devis');

echo "\n2. Le mail distingue le rappel du devis\n";
$r = quote_notification_compose($client + ['request_type'=>'rappel']);
$d = quote_notification_compose($client + ['request_type'=>'devis']);
check('objet du rappel', str_starts_with($r['subject_text'], '📞 À RAPPELER'), true);
check('objet du devis', str_starts_with($d['subject_text'], '📋 DEVIS'), true);
check('bandeau du rappel', str_contains($r['html'], 'DEMANDE DE RAPPEL'), true);
check('bandeau du devis', str_contains($d['html'], 'DEMANDE DE DEVIS'), true);
check('pas de confusion dans le rappel', str_contains($r['html'], 'DEVIS'), false);
$from = static function (array $m): string { preg_match('/From: =\?UTF-8\?B\?([^?]+)\?=/', $m['headers'], $x); return (string)base64_decode($x[1]); };
check('expéditeur du rappel', str_ends_with($from($r), '— Rappel'), true);
check('expéditeur du devis', str_ends_with($from($d), '— Devis'), true);
check('lien direct vers la fiche', str_contains($r['html'], '/admin/dossier.php?id=42'), true);

echo "\n3. Ce que saisit un visiteur ne peut pas piéger le mail\n";
check('nom échappé', str_contains($r['html'], 'Jean <b>Test'), false);
$piege = quote_notification_compose(['full_name'=>'A','phone'=>'1','email'=>"x@example.invalid\r\nBcc: victime@example.invalid",'message'=>'m']);
check('adresse piégée : pas d\'en-tête ajouté', str_contains($piege['headers'], 'Bcc:'), false);
check('réponse au client si adresse valide', str_contains($d['headers'], 'Reply-To: jean@example.invalid'), true);

echo "\n4. Photos jointes au mail\n";
$png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==');
$dir = quote_photo_dir();
$nom = bin2hex(random_bytes(16)).'.png';
file_put_contents($dir.'/'.$nom, $png);
$avec = quote_notification_compose($client + ['request_type'=>'rappel','photos'=>[$nom]]);
check('une pièce jointe', $avec['attached'], 1);
check('message multipart', str_contains($avec['headers'], 'multipart/mixed'), true);
check('pièce jointe nommée', str_contains($avec['body'], 'filename="photo-1.png"'), true);
check('contenu de la photo intact', str_contains(str_replace("\r\n", '', $avec['body']), base64_encode($png)), true);
check('annoncée dans le mail', str_contains($avec['html'], '1 photo jointe'), true);
$sans = quote_notification_compose($client + ['request_type'=>'rappel','photos'=>[$nom]], false);
check('renvoi sans pièce jointe', [$sans['attached'], str_contains($sans['headers'], 'text/html')], [0, true]);
check('il renvoie vers la fiche', str_contains($sans['html'], '1 photo à voir'), true);
$refus = quote_notification_compose($client + ['photos_refused'=>2]);
check('photos refusées signalées', str_contains($refus['html'], '2 photos refusées'), true);
check('mail sans photo : simple HTML', [$d['attached'], str_contains($d['headers'], 'text/html')], [0, true]);

echo "\n5. Seules les vraies images sont acceptées\n";
$t = tempnam(sys_get_temp_dir(), 'emae');
file_put_contents($t, $png);
check('une image PNG', quote_photo_ext($t), 'png');
file_put_contents($t, '<?php echo "pirate"; ?>');
check('un script', quote_photo_ext($t), null);
file_put_contents($t, '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');
check('un SVG', quote_photo_ext($t), null);
file_put_contents($t, "\xFF\xD8\xFF\xE0 faux jpeg");
check('un faux JPEG', quote_photo_ext($t), null);
unlink($t);
check('fichier non envoyé par le formulaire : refusé', quote_store_photos(['name'=>['a.png'],'error'=>[0],'tmp_name'=>[$dir.'/'.$nom],'size'=>[10]]), ['files'=>[],'refused'=>1]);
check('champ vide : ni photo ni refus', quote_store_photos(['name'=>[''],'error'=>[UPLOAD_ERR_NO_FILE]]), ['files'=>[],'refused'=>0]);
check('champ mal formé', quote_store_photos(['name'=>'a.png','error'=>0]), ['files'=>[],'refused'=>0]);
check('trop gros pour le serveur : refusé', quote_store_photos(['name'=>['a.png'],'error'=>[UPLOAD_ERR_INI_SIZE],'tmp_name'=>[''],'size'=>[0]])['refused'], 1);

echo "\n6. La liste des photos ne laisse passer aucun autre fichier\n";
check('nom valide', quote_client_photos(['client_photos'=>json_encode([$nom])]), [$nom]);
check('sortie du dossier refusée', quote_client_photos(['client_photos'=>json_encode(['../../config/config.local.php', 'x.php'])]), []);
check('pas de photo', quote_client_photos(['client_photos'=>null]), []);
quote_delete_photos(['client_photos'=>json_encode([$nom])]);
check('photo effacée avec la demande', is_file($dir.'/'.$nom), false);
check('dossier fermé au public', trim((string)file_get_contents($dir.'/.htaccess')), 'Require all denied');

echo "\n7. Étiquettes\n";
check('rappel', str_contains(quote_type_badge(['request_type'=>'rappel']), 'Rappel'), true);
check('devis', str_contains(quote_type_badge(['request_type'=>'devis']), 'Devis'), true);
check('demande ancienne : rien', quote_type_badge(['request_type'=>null]), '');

echo "\n8. Quels formulaires sont des rappels\n";
$src = (string)file_get_contents($R.'/index.php');
check('encart de l\'accueil', str_contains($src, "render_quote_form(\$cards, 'hero', 'rappel')"), true);
check('encart des pages métier', str_contains($src, "render_quote_form(\$cards, 'service_'.\$sk, 'rappel')"), true);
check('page devis', str_contains($src, "render_quote_form(\$cards, 'quote_page', 'devis'"), true);

echo "\n9. Photos avant / après d'une réalisation\n";
$media = static function (array $r): string { ob_start(); render_realisation_media($r); return (string)ob_get_clean(); };
$both = $media(['title'=>'T','image_path'=>'a.jpg','before_image_path'=>'b.jpg']);
check('les deux côte à côte', str_contains($both, 'real-ba') && str_contains($both, 'Avant') && str_contains($both, 'Après'), true);
check('avant placée en premier', strpos($both, 'b.jpg') < strpos($both, 'a.jpg'), true);
check('photo unique : sans étiquette', str_contains($media(['title'=>'T','image_path'=>'a.jpg']), 'real-tag'), false);
check('avant seule : étiquetée', str_contains($media(['title'=>'T','before_image_path'=>'b.jpg']), 'Avant'), true);
check('aucune photo', str_contains($media(['title'=>'T']), 'real-placeholder'), true);

echo "\n10. Boutons : l'appel en orange, le devis dans l'ancienne couleur de l'appel\n";
preg_match_all('#class="btn (btn-[a-z-]+) btn-lg" href="<\?= e\(company_phone_link\(\)\) \?>"#', $src, $m);
check('tous les appels sont orange', array_unique($m[1]), ['btn-p']);
check('accueil : la couleur suit le lien d\'appel', str_contains($src, "<?= \$_bCall ? 'btn-p' : 'btn-outline' ?>"), true);
$css = (string)file_get_contents($R.'/assets/css/style.css');
check('barre mobile : appel orange', preg_match('/\.mob-bar-call\{[^}]*background:var\(--p\)/', $css), 1);
check('barre mobile : devis bleu', preg_match('/\.mob-bar-devis\{[^}]*background:var\(--navy-2\)/', $css), 1);

echo "\n11. Photos du client : qui peut les voir\n";
$acces = (string)file_get_contents($R.'/admin/quote_photo.php');
check('technicien : seulement ses demandes', str_contains($acces, 'q.technician_id = t.id') && str_contains($acces, 'i.technician_id = t.id'), true);
check('fiche technicien (demande directe)', str_contains((string)file_get_contents($R.'/tech/intervention.php'), 'quote_photos_html($q)'), true);
check('fiche technicien (via dispatcher)', str_contains((string)file_get_contents($R.'/tech/disp_intervention.php'), 'intervention_client_photos_html($iv)'), true);
check('fiche dispatcher', str_contains((string)file_get_contents($R.'/dispatcher/intervention_view.php'), 'intervention_client_photos_html($iv)'), true);
check('qualification', str_contains((string)file_get_contents($R.'/dispatcher/qualify.php'), 'quote_photos_html($quote)'), true);
check('intervention sans demande : rien', intervention_client_photos_html(['quote_id'=>null]), '');

echo "\n";
echo $fail === 0 ? "$pass assertions PASSENT\n" : "$fail ÉCHECS sur ".($pass+$fail)."\n";
exit($fail === 0 ? 0 : 1);
