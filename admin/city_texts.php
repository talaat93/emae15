<?php
/**
 * Textes des pages ville (/intervention/{ville}) et métier × ville (/intervention/{ville}/{métier}) :
 * titre Google, description, H1, texte, blocs locaux, questions fréquentes, temps de trajet.
 * Stockés en base (table city_texts, includes/city_texts.php). Les prix ne s'écrivent pas ici :
 * ils viennent de la page Tarifs.
 */
$adminSection = 'city_texts';
require __DIR__ . '/partials/header.php';

$villes  = city_pages();
$metiers = ['' => 'Page de la ville'] + array_map(static fn(array $t): string => $t[1], city_trades());
$v = (string)($_GET['ville'] ?? $_POST['ville'] ?? '');
$m = (string)($_GET['metier'] ?? $_POST['metier'] ?? '');
if ($v !== '' && (!isset($villes[$v]) || !array_key_exists($m, $metiers))) { flash('error', 'Page inconnue.'); redirect_to('admin/city_texts.php'); }
try { city_texts_table(); } catch (Throwable $e) {}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $v !== '') {
    verify_csrf();
    $back = 'admin/city_texts.php?ville='.rawurlencode($v).'&metier='.rawurlencode($m);
    if (($_POST['act'] ?? '') === 'origine') {
        $seed = city_texts_seed()[$v][$m === '' ? 'ville' : $m] ?? null;
        if ($seed === null) { db_execute('DELETE FROM city_texts WHERE ville = ? AND metier = ?', [$v, $m]); city_text_all(true); flash('success', 'Texte retiré : la page reprend sa version standard (non indexée par Google pour une page métier).'); }
        else { city_text_save($v, $m, $seed, 'site'); flash('success', 'Texte d\'origine rétabli.'); }
        redirect_to($back);
    }
    $pairs = static function (string $k, string $l): array {
        $o = [];
        foreach ((array)($_POST[$k] ?? []) as $i => $a) {
            $a = trim((string)$a); $b = trim((string)($_POST[$l][$i] ?? ''));
            if ($a !== '' && $b !== '') $o[] = [$a, $b];
        }
        return $o;
    };
    $t = [
        'title'  => (string)($_POST['title'] ?? ''),
        'meta'   => (string)($_POST['meta'] ?? ''),
        'h1'     => (string)($_POST['h1'] ?? ''),
        'intro'  => (string)($_POST['intro'] ?? ''),
        'local'  => $pairs('local_t', 'local_x'),
        'faq'    => $pairs('faq_q', 'faq_r'),
        'pannes' => array_values(array_filter(array_map('trim', preg_split('/\R/', (string)($_POST['pannes'] ?? '')) ?: []), 'strlen')),
        'delai'  => $m === '' ? (string)($_POST['delai'] ?? '') : '',
    ];
    // Filet de sécurité : une promesse non documentée (note, délai garanti, « certifié »…) n'est pas publiée.
    $alerte = [];
    foreach (['meta' => $t['meta'], 'intro' => $t['intro']] as $k => $x) if (claim_unsupported($x)) $alerte[] = $k;
    foreach (array_merge($t['local'], $t['faq']) as $pr) if (claim_unsupported(implode(' ', $pr))) $alerte[] = '« '.mb_substr($pr[0], 0, 40, 'UTF-8').' »';
    city_text_save($v, $m, $t, 'admin');
    flash($alerte ? 'error' : 'success', $alerte
        ? 'Enregistré, mais ces passages contiennent une promesse non documentée (note, délai garanti, certification…) et ne seront pas affichés : '.implode(', ', $alerte).'.'
        : 'Page enregistrée.');
    redirect_to($back);
}
?>
<div class="admin-page-toolbar"><h1>🏙️ Textes des pages ville</h1></div>

<?php if ($v === ''): ?>
<section class="admin-panel">
  <div class="admin-panel__head"><h2>Pages</h2>
    <p>Une page avec un texte est ouverte à Google ; une page métier sans texte reste en « noindex » (elle sert aux annonces sans faire doublon). Les prix viennent de la page Tarifs, le temps de trajet se règle sur la page de la ville.</p></div>
  <div class="admin-panel__body admin-table-wrap">
    <table class="admin-table"><thead><tr><th>Ville</th><th>Trajet estimé</th><?php foreach ($metiers as $lab): ?><th><?= e($lab) ?></th><?php endforeach; ?></tr></thead><tbody>
    <?php foreach ($villes as $slug => $row): ?>
      <tr><td style="font-weight:700;"><?= e($row[0]) ?> <small style="color:#7b8aa8;">(<?= e($row[2]) ?>)</small></td>
        <td style="font-size:.85rem;"><?= e(city_travel_label($slug)) ?></td>
        <?php foreach ($metiers as $mk => $lab): $t = city_text_all()[$slug.'|'.$mk] ?? null; $ok = $t !== null && trim((string)$t['intro']) !== ''; ?>
        <td><a href="<?= e(url_for('admin/city_texts.php?ville='.rawurlencode($slug).'&metier='.rawurlencode($mk))) ?>" style="font-weight:600;color:<?= $ok ? '#15803d' : '#b45309' ?>;"><?= $ok ? '✔ rédigée' : '✎ à rédiger' ?></a><?= $ok && ($t['updated_by'] ?? '') === 'admin' ? ' <small title="Modifiée dans l\'admin">✍️</small>' : '' ?></td>
        <?php endforeach; ?>
      </tr>
    <?php endforeach; ?>
    </tbody></table>
  </div>
</section>
<?php else:
    $c = city_page($v);
    $t = city_text_all()[$v.'|'.$m] ?? ['title' => '', 'meta' => '', 'h1' => '', 'intro' => '', 'local' => [], 'faq' => [], 'pannes' => [], 'delai' => ''];
    $url = $m === '' ? $c['url'] : url_for('intervention/'.$v.'/'.$m);
    $local = array_pad((array)$t['local'], 3, ['', '']);
    $faq   = array_pad((array)$t['faq'], 4, ['', '']);
?>
<p><a href="<?= e(url_for('admin/city_texts.php')) ?>">← Toutes les pages</a> · <a href="<?= e($url) ?>" target="_blank" rel="noopener">Voir la page en ligne ↗</a></p>
<form method="post" class="admin-panel">
  <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="ville" value="<?= e($v) ?>"><input type="hidden" name="metier" value="<?= e($m) ?>">
  <div class="admin-panel__head"><h2><?= e($metiers[$m]) ?> — <?= e($c['nom']) ?> (<?= e($c['dep_code']) ?>)</h2>
    <p>Règles : uniquement des faits vérifiables sur la ville (quartiers, communes, type de logements, chantiers réels). Pas de délai garanti, pas de note, pas de « certifié ». « Notre base de Dole » seulement pour les villes proches de Dole (Dole, Tavaux, Auxonne, Saint-Vit).<?= $m === 'plombier' ? ' Plomberie : ne pas citer la décennale (non couverte par l\'attestation).' : '' ?></p></div>
  <div class="admin-panel__body">
    <label class="admin-field"><span>Titre Google (title) — vide = titre standard · <b data-count="title">0</b> caractères (60 à 70 conseillés)</span><input type="text" name="title" maxlength="190" value="<?= e((string)$t['title']) ?>" data-counter="title"></label>
    <label class="admin-field"><span>Description Google (meta) · <b data-count="meta">0</b>/155</span><textarea name="meta" rows="2" maxlength="300" data-counter="meta"><?= e((string)$t['meta']) ?></textarea></label>
    <label class="admin-field"><span>Titre de la page (H1) — vide = titre standard</span><input type="text" name="h1" maxlength="190" value="<?= e((string)$t['h1']) ?>"></label>
    <label class="admin-field"><span>Texte d'introduction (vide = page sans texte propre)</span><textarea name="intro" rows="7"><?= e((string)$t['intro']) ?></textarea></label>
    <h3 style="margin:1.25rem 0 .5rem;">Ce que nous savons de la ville (blocs)</h3>
    <?php foreach ($local as $i => [$a, $b]): ?>
    <div class="admin-form-grid admin-form-grid--2" style="align-items:start;">
      <label class="admin-field"><span>Bloc <?= $i + 1 ?> — titre</span><input type="text" name="local_t[]" value="<?= e((string)$a) ?>"></label>
      <label class="admin-field"><span>Texte</span><textarea name="local_x[]" rows="3"><?= e((string)$b) ?></textarea></label>
    </div>
    <?php endforeach; ?>
    <h3 style="margin:1.25rem 0 .5rem;">Questions fréquentes propres à la page</h3>
    <p style="font-size:.85rem;color:#555;">S'y ajoutent d'office : le prix (depuis la page Tarifs), le délai, l'assurance (métiers couverts) et les communes desservies.</p>
    <?php foreach ($faq as $i => [$a, $b]): ?>
    <div class="admin-form-grid admin-form-grid--2" style="align-items:start;">
      <label class="admin-field"><span>Question <?= $i + 1 ?></span><input type="text" name="faq_q[]" value="<?= e((string)$a) ?>"></label>
      <label class="admin-field"><span>Réponse</span><textarea name="faq_r[]" rows="3"><?= e((string)$b) ?></textarea></label>
    </div>
    <?php endforeach; ?>
    <?php if ($m !== ''): ?>
    <label class="admin-field"><span>Pannes traitées (une par ligne ; vide = liste standard du métier)</span><textarea name="pannes" rows="6"><?= e(implode("\n", (array)$t['pannes'])) ?></textarea></label>
    <?php else: ?>
    <label class="admin-field"><span>Temps de trajet estimé (affiché sur la page de la ville et ses pages métier) — vide = calcul selon la distance : « <?= e(city_travel_minutes($v) === null ? '?' : (city_travel_minutes($v) < 30 ? 'moins de 30 min' : (city_travel_minutes($v) <= 60 ? '30 min à 1 h' : 'plus d\'1 h'))) ?> »</span>
      <select name="delai"><?php foreach (['' => 'Calcul automatique', 'moins de 30 min' => 'moins de 30 min', '30 min à 1 h' => '30 min à 1 h', 'plus d\'1 h' => 'plus d\'1 h'] as $k => $lab): ?><option value="<?= e($k) ?>"<?= (string)$t['delai'] === $k ? ' selected' : '' ?>><?= e($lab) ?></option><?php endforeach; ?></select></label>
    <?php endif; ?>
    <div class="admin-savebar" style="justify-content:flex-start;gap:.75rem;">
      <button class="admin-btn admin-btn--primary" type="submit">Enregistrer</button>
      <button class="admin-btn admin-btn--secondary" type="submit" name="act" value="origine" formnovalidate onclick="return confirm('Remplacer le texte de cette page par sa version d\'origine ?')">Rétablir le texte d'origine</button>
    </div>
  </div>
</form>
<script>
document.querySelectorAll('[data-counter]').forEach(function (f) {
  var out = document.querySelector('[data-count="' + f.dataset.counter + '"]');
  var up = function () { out.textContent = f.value.length; out.style.color = (f.dataset.counter === 'meta' && f.value.length > 155) ? '#b91c1c' : ''; };
  f.addEventListener('input', up); up();
});
</script>
<?php endif; ?>
<?php require __DIR__ . '/partials/footer.php'; ?>
