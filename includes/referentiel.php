<?php
declare(strict_types=1);
/**
 * Référentiel : appareils, marques, modèles, types de panne et phrases toutes prêtes.
 *
 * Tout est rangé dans preset_items (même table que les autres « Listes prédéfinies »),
 * pour que le dispatcher puisse ajouter ou retirer chaque élément :
 *   type 'equipement'      catégorie = métier (clé de intervention_category_config)
 *   type 'marque'          catégorie = appareil (« Chaudière gaz »)
 *   type 'modele'          catégorie = « appareil|marque »
 *   type 'panne'           catégorie = métier
 *   type 'phrase_diag'     catégorie = métier
 *   type 'phrase_travaux'  catégorie = métier
 * Liste de départ : includes/referentiel_seed.php, chargée une fois (migration v15.49).
 */

function ref_types(): array
{
    return [
        'equipement'     => 'Appareils',
        'marque'         => 'Marques',
        'modele'         => 'Modèles',
        'panne'          => 'Types de panne',
        'phrase_diag'    => 'Phrases de diagnostic',
        'phrase_travaux' => 'Phrases de travaux',
    ];
}

/** Lignes [type, catégorie, libellé] à partir du fichier de départ. Fonction pure. */
function ref_seed_rows(array $seed): array
{
    $rows = [];
    foreach (['equipement', 'panne', 'phrase_diag', 'phrase_travaux'] as $t) {
        foreach ($seed[$t] ?? [] as $cat => $labels) {
            foreach ($labels as $l) $rows[] = [$t, (string)$cat, (string)$l];
        }
    }
    foreach ($seed['marque'] ?? [] as $equip => $brands) {
        foreach ($brands as $brand => $models) {
            $rows[] = ['marque', (string)$equip, (string)$brand];
            foreach ($models as $m) $rows[] = ['modele', $equip.'|'.$brand, (string)$m];
        }
    }
    return $rows;
}

/**
 * Structure utilisée par les écrans, à partir de lignes de preset_items
 * (tableaux avec type, category, label). Fonction pure.
 *   equipement[métier][] ; marque[appareil][] ; modele["appareil|marque"][] ;
 *   panne / phrase_diag / phrase_travaux [métier][]
 */
function ref_build(array $rows): array
{
    $out = array_fill_keys(array_keys(ref_types()), []);
    foreach ($rows as $r) {
        $t = (string)($r['type'] ?? '');
        if (!isset($out[$t])) continue;
        $c = (string)($r['category'] ?? '');
        $l = trim((string)($r['label'] ?? ''));
        if ($l === '' || in_array($l, $out[$t][$c] ?? [], true)) continue;
        $out[$t][$c][] = $l;
    }
    return $out;
}

/** Référentiel actuel (base de données). */
function ref_load(): array
{
    static $cache = null;
    if ($cache !== null) return $cache;
    try {
        $in = implode(',', array_fill(0, count(ref_types()), '?'));
        $rows = db_fetch_all("SELECT type, category, label FROM preset_items WHERE active = 1 AND type IN ($in) ORDER BY type, category, sort_order, id",
            array_keys(ref_types()));
    } catch (Throwable $e) { $rows = []; }
    return $cache = ref_build($rows);
}

/** Charge la liste de départ dans preset_items, pour chaque type encore vide. */
function ref_seed_db(): int
{
    $seed = require __DIR__.'/referentiel_seed.php';
    $n = 0; $order = 0; $filled = [];
    foreach (array_keys(ref_types()) as $t) {
        try { $filled[$t] = (bool)db_fetch('SELECT id FROM preset_items WHERE type = ? LIMIT 1', [$t]); }
        catch (Throwable $e) { $filled[$t] = true; }
    }
    foreach (ref_seed_rows($seed) as [$t, $c, $l]) {
        if ($filled[$t]) continue;
        try { db_execute('INSERT INTO preset_items (type, category, label, sort_order) VALUES (?,?,?,?)', [$t, $c, $l, $order += 10]); $n++; }
        catch (Throwable $e) {}
    }
    return $n;
}

/** Libellé d'installation composé : « Chaudière gaz · Saunier Duval · ThemaPlus Condens ». */
function ref_installation_label(string $equip, string $brand, string $model): string
{
    return implode(' · ', array_values(array_filter(array_map('trim', [$equip, $brand, $model]), static fn($v) => $v !== '')));
}

/**
 * Sélecteur pour le dispatcher : appareil, marque, modèle (qui remplissent le champ
 * « Type d'installation »), et types de panne en un clic (qui remplissent la panne).
 * $o : cat = id du choix du métier, install = id du champ installation,
 *      fault = id du champ panne, fault_mode = 'append' (zone de texte) ou 'set' (champ court).
 */
function ref_picker_html(array $o): string
{
    $ref = ref_load();
    $data = json_encode(['equipement' => $ref['equipement'], 'marque' => $ref['marque'], 'modele' => $ref['modele'], 'panne' => $ref['panne']],
        JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    $cfg = json_encode(['cat' => $o['cat'] ?? '', 'install' => $o['install'] ?? '', 'fault' => $o['fault'] ?? '', 'mode' => $o['fault_mode'] ?? 'append'],
        JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    $uid = 'ref'.substr(md5((string)($o['install'] ?? '').(string)($o['fault'] ?? '')), 0, 6);
    ob_start(); ?>
<div class="ref-picker" id="<?= $uid ?>">
  <div class="ref-row">
    <label>Appareil<select class="d-input" data-r="equip"><option value="">—</option></select></label>
    <label>Marque<select class="d-input" data-r="brand"><option value="">—</option></select></label>
    <label>Modèle<input class="d-input" data-r="model" list="<?= $uid ?>-models" placeholder="Choisir ou écrire"><datalist id="<?= $uid ?>-models"></datalist></label>
  </div>
  <div class="ref-pannes-k">Types de panne fréquents <small>(un clic pour l'ajouter)</small></div>
  <div class="ref-pannes" data-r="pannes"><span class="ref-empty">Choisissez d'abord le métier.</span></div>
</div>
<style>
.ref-picker { background: var(--d-card-2, #f6f8fb); border: 1px solid var(--d-border, #e4e8ee); border-radius: 8px; padding: .75rem; margin-bottom: 1rem; }
.ref-row { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: .5rem; }
.ref-row label { display: flex; flex-direction: column; gap: .25rem; font-size: .76rem; font-weight: 600; color: var(--d-t2, #5a6a82); }
.ref-row select, .ref-row input { width: 100%; font-size: .85rem; }
.ref-pannes-k { font-size: .76rem; font-weight: 600; color: var(--d-t2, #5a6a82); margin: .7rem 0 .35rem; }
.ref-pannes-k small { font-weight: 400; }
.ref-pannes { display: flex; flex-wrap: wrap; gap: .35rem; }
.ref-pannes button { border: 1px solid rgba(240,123,29,.35); background: #fff; color: var(--d-t1, #17223b); border-radius: 99px; padding: .25rem .65rem; font-size: .8rem; cursor: pointer; }
.ref-pannes button:hover, .ref-pannes button.on { background: rgba(240,123,29,.12); }
.ref-empty { font-size: .8rem; color: var(--d-t3, #8c99ad); }
@media (max-width: 640px) { .ref-row { grid-template-columns: 1fr; } }
</style>
<script>
// Lancé une fois la page chargée : les champs à remplir sont placés après ce sélecteur.
document.addEventListener('DOMContentLoaded', function () {
  var REF = <?= $data ?>, CFG = <?= $cfg ?>, box = document.getElementById(<?= json_encode($uid) ?>);
  var q = function (r) { return box.querySelector('[data-r="' + r + '"]'); };
  var cat = document.getElementById(CFG.cat), inst = document.getElementById(CFG.install), fault = document.getElementById(CFG.fault);
  var eq = q('equip'), br = q('brand'), mo = q('model'), dl = document.getElementById(<?= json_encode($uid.'-models') ?>), pa = q('pannes');
  function fill(sel, items, keep) {
    var v = keep || sel.value; sel.innerHTML = '<option value="">—</option>';
    items.forEach(function (l) { var o = document.createElement('option'); o.value = o.textContent = l; sel.appendChild(o); });
    if (v && items.indexOf(v) >= 0) sel.value = v;
  }
  function allEquip() { var s = []; Object.keys(REF.equipement).forEach(function (k) { REF.equipement[k].forEach(function (l) { if (s.indexOf(l) < 0) s.push(l); }); }); return s; }
  function onCat() {
    var c = cat ? cat.value : '';
    fill(eq, REF.equipement[c] || allEquip());
    onEquip();
    pa.innerHTML = '';
    var list = REF.panne[c] || [];
    if (!list.length) { pa.innerHTML = '<span class="ref-empty">' + (c ? 'Aucune panne listée pour ce métier (à ajouter dans Listes prédéfinies).' : 'Choisissez d\'abord le métier.') + '</span>'; return; }
    list.forEach(function (l) {
      var b = document.createElement('button'); b.type = 'button'; b.textContent = l;
      b.onclick = function () {
        if (!fault) return;
        if (CFG.mode === 'set') { fault.value = l; }
        else if (fault.value.indexOf(l) < 0) { fault.value = fault.value.trim() ? fault.value.replace(/\s+$/, '') + '\n' + l : l; }
        b.classList.add('on'); fault.dispatchEvent(new Event('input'));
      };
      pa.appendChild(b);
    });
  }
  function onEquip() { fill(br, REF.marque[eq.value] || []); onBrand(); }
  function onBrand() {
    dl.innerHTML = '';
    (REF.modele[eq.value + '|' + br.value] || []).forEach(function (l) { var o = document.createElement('option'); o.value = l; dl.appendChild(o); });
  }
  function compose() {
    if (!inst || !eq.value) return;
    inst.value = [eq.value, br.value, mo.value.trim()].filter(Boolean).join(' · ');
  }
  // Reprend une installation déjà saisie (« Appareil · Marque · Modèle »).
  function restore() {
    if (!inst || !inst.value) return;
    var p = inst.value.split(' · ');
    if (p[0]) { fill(eq, (REF.equipement[cat ? cat.value : ''] || allEquip()), p[0]); if (eq.value !== p[0]) return; }
    fill(br, REF.marque[eq.value] || [], p[1] || ''); onBrand(); if (p[2]) mo.value = p[2];
  }
  if (cat) cat.addEventListener('change', onCat);
  eq.addEventListener('change', function () { onEquip(); mo.value = ''; compose(); });
  br.addEventListener('change', function () { onBrand(); mo.value = ''; compose(); });
  mo.addEventListener('input', compose);
  onCat(); restore();
});
</script>
<?php
    return (string)ob_get_clean();
}

/**
 * Phrases toutes prêtes pour le rapport du technicien : un appui ajoute la phrase
 * à la zone de texte $targetId. Phrases du métier, sinon toutes.
 */
function ref_phrases_html(string $type, string $category, string $targetId): string
{
    $ref = ref_load();
    $list = $ref[$type][$category] ?? [];
    if (!$list) foreach ($ref[$type] as $l) $list = array_merge($list, $l);
    if (!$list) return '';
    $h = '<div class="ta-phrases" data-target="'.e($targetId).'">';
    foreach ($list as $p) $h .= '<button type="button">'.e($p).'</button>';
    return $h.'</div>';
}
