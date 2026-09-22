<?php
declare(strict_types=1);

/**
 * Variables de lieu utilisables dans n'importe quel texte du site.
 *
 * On écrit {ville} ou {en_region} dans un texte ; chaque zone définit ce
 * que valent ces variables chez elle. Une zone qui n'en définit pas une
 * reprend la valeur du site global : les variables héritent exactement
 * comme les textes, puisqu'elles sont stockées avec le même mécanisme.
 *
 * Les formes « avec préposition » existent parce que le français est
 * irrégulier : on dit « en Île-de-France » mais « dans le Jura ». Une
 * déduction automatique produirait des fautes visibles.
 */

/** Préfixe de stockage. Le contexte de zone ajoute « z:{slug}: » devant. */
const ZVAR_PREFIX = 'zvar_';

/** Variables proposées d'office, dans l'ordre de l'écran de réglage. */
function zone_vars_standard(): array
{
    return [
        'region'        => ['Région',                 'Île-de-France',        'Le nom de la région ou du grand secteur.'],
        'en_region'     => ['Région avec préposition','en Île-de-France',     'Tel quel dans la phrase : « Nous intervenons {en_region} ».'],
        'departement'   => ['Département',            'Seine-et-Marne',       'Le nom du département.'],
        'en_departement'=> ['Département avec préposition','dans le Jura',    'Évite « en Jura », qui est fautif.'],
        'ville'         => ['Ville principale',       'Paris',                'La ville que vous mettez en avant sur cette zone.'],
        'en_ville'      => ['Ville avec préposition', 'à Paris',              'Tel quel dans la phrase : « Dépannage {en_ville} ».'],
        'code_postal'   => ['Code postal',            '75',                   'Un code, ou plusieurs séparés par des virgules.'],
    ];
}

/** Anciens noms encore présents dans des textes, redirigés vers les nouveaux. */
function zone_vars_aliases(): array
{
    return ['dept' => 'departement', 'dept_code' => 'code_postal'];
}

/** Valeur d'une variable pour la portée courante, sans repli automatique. */
function zone_var_raw(string $nom): string
{
    return raw_setting(ZVAR_PREFIX.$nom);
}

/** Valeur effective : celle de la zone, sinon celle du site global. */
function zone_var_value(string $nom): string
{
    $nom = zone_vars_aliases()[$nom] ?? $nom;
    // setting_plain() ne réapplique pas les variables : pas de récursion possible.
    return setting_plain(ZVAR_PREFIX.$nom);
}

function zone_var_save(string $nom, string $valeur): void
{
    set_setting(ZVAR_PREFIX.$nom, trim($valeur));
}

/** Toutes les variables connues : les standards plus celles que vous avez créées. */
function zone_vars_all(): array
{
    $noms = array_keys(zone_vars_standard());
    foreach (array_keys(settings_cache()) as $k) {
        if (preg_match('/^(?:z:[a-z0-9-]+:)?'.ZVAR_PREFIX.'([a-z0-9_]+)$/', $k, $m)) $noms[] = $m[1];
    }
    return array_values(array_unique($noms));
}

/** Variables créées par l'utilisateur, hors liste standard. */
function zone_vars_custom(): array
{
    return array_values(array_diff(zone_vars_all(), array_keys(zone_vars_standard())));
}

/**
 * Remplace les variables d'un texte par leur valeur dans la portée courante.
 *
 * Une variable inconnue ou vide est laissée telle quelle : mieux vaut voir
 * « {ville} » sur la page et le corriger, qu'un trou silencieux dans une phrase.
 */
function zone_vars_apply(string $text): string
{
    if ($text === '' || !str_contains($text, '{')) return $text;
    return preg_replace_callback(
        '/\{([a-z][a-z0-9_]{1,39})\}/i',
        static function (array $m): string {
            $v = zone_var_value(strtolower($m[1]));
            return $v !== '' ? $v : $m[0];
        },
        $text
    ) ?? $text;
}

/** Applique les variables dans une structure décodée (blocs groupés, listes). */
function zone_vars_apply_deep(array $data): array
{
    foreach ($data as $k => $v) {
        if (is_string($v))      $data[$k] = zone_vars_apply($v);
        elseif (is_array($v))   $data[$k] = zone_vars_apply_deep($v);
    }
    return $data;
}

/** Les variables réellement employées dans un texte. */
function zone_vars_used(string $text): array
{
    if (!str_contains($text, '{')) return [];
    preg_match_all('/\{([a-z][a-z0-9_]{1,39})\}/i', $text, $m);
    return array_values(array_unique(array_map('strtolower', $m[1])));
}

/** Libellé lisible d'une variable. */
function zone_var_label(string $nom): string
{
    return zone_vars_standard()[$nom][0] ?? $nom;
}
