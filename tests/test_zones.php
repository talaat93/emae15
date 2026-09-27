<?php
declare(strict_types=1);

// Base de données en mémoire simulant la table settings + zones
$DB = [
    'settings' => [
        1 => ['id'=>1,'setting_key'=>'company_name','setting_value'=>'EMAE'],
        2 => ['id'=>2,'setting_key'=>'company_phone','setting_value'=>'06 67 83 03 76'],
        3 => ['id'=>3,'setting_key'=>'hero_title','setting_value'=>'Titre global'],
    ],
    'zones' => [
        1 => ['id'=>1,'slug'=>'paris-ile-de-france','name'=>'Paris / Île-de-France','status'=>1,'sort_order'=>0,'cities'=>'Paris|Meaux','faq'=>null],
        2 => ['id'=>2,'slug'=>'jura','name'=>'Jura','status'=>1,'sort_order'=>1,'cities'=>'Dole','faq'=>null],
    ],
];
$NEXT = 100;

function db_fetch_all(string $sql, array $p = []): array {
    global $DB;
    if (str_contains($sql, 'FROM settings')) return array_values($DB['settings']);
    if (str_contains($sql, 'FROM zones'))    return array_values($DB['zones']);
    throw new RuntimeException('table inconnue: '.$sql);
}
function db_fetch(string $sql, array $p = []): ?array {
    global $DB;
    if (str_contains($sql, 'FROM settings')) {
        foreach ($DB['settings'] as $r) if ($r['setting_key'] === $p[0]) return $r;
        return null;
    }
    if (str_contains($sql, 'FROM zones')) {
        foreach ($DB['zones'] as $r) if ((string)$r['id'] === (string)$p[0] || $r['slug'] === $p[0]) return $r;
        return null;
    }
    // colonnes zone_id des tables de contenu : simulées comme présentes
    if (preg_match('/SELECT zone_id FROM/', $sql)) return null;
    throw new RuntimeException('table inconnue: '.$sql);
}
function db_execute(string $sql, array $p = []): void {
    global $DB, $NEXT;
    if (str_starts_with($sql, 'DELETE FROM settings')) {
        foreach ($DB['settings'] as $i => $r) if ($r['setting_key'] === $p[0]) unset($DB['settings'][$i]);
        return;
    }
    if (str_starts_with($sql, 'UPDATE settings')) {
        foreach ($DB['settings'] as $i => $r) if ($r['setting_key'] === $p[1]) $DB['settings'][$i]['setting_value'] = $p[0];
        return;
    }
    if (str_starts_with($sql, 'INSERT INTO settings')) {
        $DB['settings'][$NEXT] = ['id'=>$NEXT,'setting_key'=>$p[0],'setting_value'=>$p[1]]; $NEXT++;
        return;
    }
    throw new RuntimeException('sql inconnu: '.$sql);
}

require dirname(__DIR__).'/includes/helpers.php';

$pass = 0; $fail = 0;
function check(string $label, mixed $got, mixed $want): void {
    global $pass, $fail;
    if ($got === $want) { $pass++; echo "  ok   $label\n"; }
    else { $fail++; echo "  FAIL $label — obtenu ".var_export($got,true)." / attendu ".var_export($want,true)."\n"; }
}

echo "\n1. Contexte global\n";
set_zone_context(null);
check('lit la valeur globale', setting('company_phone'), '06 67 83 03 76');
check('aucun héritage en global', setting_is_inherited('company_phone'), false);

echo "\n2. Zone sans personnalisation : héritage\n";
set_zone_context(get_zone_by_id(1));
check('hérite du téléphone global', setting('company_phone'), '06 67 83 03 76');
check('marqué comme hérité', setting_is_inherited('company_phone'), true);
check('compteur de surcharges à 0', zone_override_count('paris-ile-de-france'), 0);

echo "\n3. Personnalisation d'une zone\n";
set_setting('company_phone', '01 23 45 67 89');
check('la zone renvoie sa valeur', setting('company_phone'), '01 23 45 67 89');
check('plus marqué comme hérité', setting_is_inherited('company_phone'), false);
check('la valeur globale est intacte', global_setting('company_phone'), '06 67 83 03 76');
check('un champ non touché hérite encore', setting('hero_title'), 'Titre global');

echo "\n4. Étanchéité entre zones\n";
set_zone_context(get_zone_by_id(2));
check('Jura ne voit pas la surcharge de Paris', setting('company_phone'), '06 67 83 03 76');

echo "\n5. Le site global reste intact\n";
set_zone_context(null);
check('global inchangé', setting('company_phone'), '06 67 83 03 76');

echo "\n6. Vider un champ = revenir à l'héritage\n";
set_zone_context(get_zone_by_id(1));
check('surcharge encore active', setting('company_phone'), '01 23 45 67 89');
set_setting('company_phone', '');
check('hérite de nouveau', setting('company_phone'), '06 67 83 03 76');
check('surcharge supprimée', zone_override_count('paris-ile-de-france'), 0);

echo "\n7. Dupliquer depuis Global\n";
set_zone_context(get_zone_by_id(1));
$n = zone_copy_from_global();
check('3 champs copiés', $n, 3);
check('valeur propre désormais', setting_is_inherited('company_phone'), false);
set_zone_context(null);
check('global toujours intact', setting('company_phone'), '06 67 83 03 76');
check('Jura non affecté par la copie', zone_override_count('jura'), 0);

echo "\n8. Réinitialiser une zone\n";
set_zone_context(get_zone_by_id(1));
check('3 surcharges avant reset', zone_override_count('paris-ile-de-france'), 3);
$n = zone_reset_overrides();
check('3 surcharges supprimées', $n, 3);
check('héritage total rétabli', setting_is_inherited('hero_title'), true);
check('valeur héritée correcte', setting('hero_title'), 'Titre global');

echo "\n9. Filtre SQL des contenus\n";
set_zone_context(null);
check('aucun filtre en global', zone_rows_where(), '');
set_zone_context(get_zone_by_id(2));
check('filtre zone + partagés', zone_rows_where(), ' AND (zone_id IS NULL OR zone_id = 2)');

echo "\n".($fail === 0 ? "TOUS LES TESTS PASSENT ($pass)" : "$fail ÉCHEC(S) sur ".($pass+$fail))."\n";
exit($fail === 0 ? 0 : 1);
