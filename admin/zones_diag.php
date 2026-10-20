<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/render.php';
require_once __DIR__ . '/../includes/admin_fields.php';
require_once __DIR__ . '/../includes/admin_replace.php';
require_admin();

/**
 * État réel du système multi-zones : ce que la base contient vraiment,
 * quelle zone est active, et ce que chaque zone personnalise.
 * Sert à comprendre pourquoi une modification part au mauvais endroit.
 */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $slug = preg_replace('/[^a-z0-9-]/', '', (string)($_POST['slug'] ?? '')) ?? '';
    if (($_POST['action'] ?? '') === 'prune' && $slug !== '') {
        $n = zone_prune_identical($slug);
        admin_log('modification', 'Nettoyage zone — '.$slug, $n.' texte(s) remis en héritage');
        flash('success', $n === 0
            ? 'Rien à nettoyer : tous les textes de cette zone diffèrent déjà du site global.'
            : $n.' texte'.($n > 1 ? 's' : '').' remis en héritage. Cette zone suivra de nouveau le site global, sauf là où elle diffère vraiment.');
    }
    if (($_POST['action'] ?? '') === 'secteurs_annuler') {
        $lot = preg_replace('/[^a-z0-9-]/', '', (string)($_POST['lot'] ?? '')) ?? '';
        $n = $lot !== '' ? geo_annuler($lot) : 0;
        admin_log('modification', 'Secteurs — annulation', $n.' valeur(s) restaurée(s)');
        flash('success', $n.' valeur'.($n > 1 ? 's' : '').' remise'.($n > 1 ? 's' : '').' comme avant la mise à jour des secteurs.');
    }
    if (($_POST['action'] ?? '') === 'secteurs_appliquer') {
        $b = geo_appliquer();
        admin_log('modification', 'Secteurs — mise en conformité', ($b['ecrits'] + $b['retires'] + $b['variables']).' changement(s)');
        flash('success', 'Mise en conformité relancée : '.$b['ecrits'].' texte(s) écrit(s), '.$b['retires'].' retiré(s), '
            .$b['variables'].' variable(s) de lieu réglée(s).');
    }
    redirect_to('admin/zones_diag.php');
}

$bilan     = geo_dernier_bilan();
$controle  = geo_controle();
$parPorteeControle = [];
foreach ($controle as $r) $parPorteeControle[$r['portee']][] = $r;

$tableOk = true;
$zones   = [];
try { $zones = db_fetch_all('SELECT * FROM zones ORDER BY sort_order ASC, name ASC'); }
catch (Throwable $e) { $tableOk = false; }

$actives = array_values(array_filter($zones, fn($z) => (int)$z['status'] === 1));
$ctx     = zone_context();

// Répartition des réglages enregistrés : global d'un côté, chaque zone de l'autre.
$parPortee = ['__global' => 0];
try {
    foreach (db_fetch_all('SELECT setting_key FROM settings') as $r) {
        $k = (string)$r['setting_key'];
        if (preg_match('/^z:([a-z0-9-]+):/', $k, $m)) {
            $parPortee[$m[1]] = ($parPortee[$m[1]] ?? 0) + 1;
        } else {
            $parPortee['__global']++;
        }
    }
} catch (Throwable $e) {}

$adminSection = 'zones';
require_once __DIR__ . '/partials/header.php';
?>
<div class="admin-page-toolbar">
  <div>
    <div class="admin-breadcrumb">Multi-zones</div>
    <h1 class="admin-page-title">🩺 Diagnostic des zones</h1>
    <p class="admin-page-subtitle">L'état réel du système, pour comprendre où partent vos modifications.</p>
  </div>
  <div class="admin-toolbar-actions">
    <a class="admin-btn admin-btn--secondary" href="<?= e(url_for('admin/zones.php')) ?>">← Gérer les zones</a>
  </div>
</div>

<section class="admin-panel">
  <div class="admin-panel__head"><h2>1. Zone actuellement sélectionnée</h2></div>
  <div class="admin-panel__body">
    <?php if ($ctx): ?>
      <p class="dg-ok">✅ Vous modifiez <strong>📍 <?= e($ctx['name']) ?></strong>.
         Tout enregistrement ira dans cette zone uniquement.</p>
    <?php else: ?>
      <p class="dg-warn">⚠️ Vous modifiez le <strong>🌐 Site global</strong>.
         <strong>C'est la cause la plus fréquente du problème :</strong> une modification faite ici
         se répercute sur toutes les zones qui n'ont pas leur propre version de ce texte.</p>
      <p style="font-size:.9rem;color:#5b6b92;">Pour modifier une seule zone, cliquez d'abord sur sa pastille
         dans le bandeau en haut de l'écran, puis vérifiez que ce bandeau affiche bien son nom en orange.</p>
    <?php endif; ?>
  </div>
</section>

<section class="admin-panel">
  <div class="admin-panel__head"><h2>2. Zones enregistrées en base</h2></div>
  <div class="admin-panel__body">
    <?php if (!$tableOk): ?>
      <p class="dg-err">❌ La table <code>zones</code> n'existe pas sur ce serveur.
         Le système multi-zones ne peut pas fonctionner.</p>
      <p style="font-size:.9rem;color:#5b6b92;">Correctif : supprimez le fichier
         <code>storage/.mig_v15_zones</code> par le gestionnaire de fichiers, puis rechargez cette page.
         La table sera recréée automatiquement avec les cinq zones de départ.</p>
    <?php elseif (!$zones): ?>
      <p class="dg-err">❌ La table existe mais ne contient <strong>aucune zone</strong>.</p>
      <p style="font-size:.9rem;color:#5b6b92;">Correctif : supprimez <code>storage/.mig_v15_zones</code>
         puis rechargez, ou créez vos zones depuis « Zones géographiques ».</p>
    <?php else: ?>
      <p class="dg-ok">✅ <?= count($zones) ?> zone<?= count($zones) > 1 ? 's' : '' ?>,
         dont <?= count($actives) ?> active<?= count($actives) > 1 ? 's' : '' ?>.</p>
      <div class="admin-table-wrap">
        <table class="admin-table">
          <thead><tr><th>Zone</th><th>Adresse publique</th><th>Statut</th><th>Textes enregistrés</th><th>Vraiment différents</th><th></th></tr></thead>
          <tbody>
          <?php foreach ($zones as $z):
              $slug = (string)$z['slug'];
              $n    = $parPortee[$slug] ?? 0;
              $diff = zone_real_differences($slug);
              $copies = $n - $diff; ?>
            <tr>
              <td style="font-weight:700;"><?= e($z['name']) ?></td>
              <td><a href="<?= e(url_for($slug.'/')) ?>" target="_blank"><code>/<?= e($slug) ?>/</code> ↗</a></td>
              <td><?= (int)$z['status'] === 1
                      ? '<span style="color:#15803d;font-weight:700;">active</span>'
                      : '<span style="color:#b91c1c;">inactive — page inaccessible</span>' ?></td>
              <td><?= $n > 0 ? '<strong>'.$n.'</strong>' : '<span style="color:#b45309;">aucun — affiche le site global</span>' ?></td>
              <td>
                <?php if ($n === 0): ?>
                  —
                <?php elseif ($diff === 0): ?>
                  <strong style="color:#b45309;">0</strong>
                  <br><small style="color:#b45309;">copie conforme du global</small>
                <?php else: ?>
                  <strong style="color:#15803d;"><?= $diff ?></strong>
                  <?php if ($copies > 0): ?><br><small style="color:#b45309;">+ <?= $copies ?> copies inutiles</small><?php endif; ?>
                <?php endif; ?>
              </td>
              <td style="text-align:right;">
                <?php if ($copies > 0): ?>
                  <form method="post" style="margin:0;"
                        onsubmit="return confirm('Remettre <?= $copies ?> texte(s) en héritage pour <?= e(addslashes($z['name'])) ?> ? Les <?= $diff ?> texte(s) réellement différents sont conservés.');">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="prune">
                    <input type="hidden" name="slug" value="<?= e($slug) ?>">
                    <button class="admin-btn admin-btn--secondary" style="min-height:34px;padding:0 .7rem;font-size:.8rem;" type="submit">🧹 Nettoyer</button>
                  </form>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</section>

<section class="admin-panel">
  <div class="admin-panel__head"><h2>3. Où sont enregistrés vos textes</h2></div>
  <div class="admin-panel__body">
    <p style="font-size:.9rem;color:#5b6b92;">Un texte enregistré en global sert de base à toutes les zones.
       Une zone ne s'en écarte que pour les textes qu'elle possède en propre.</p>
    <p class="dg-warn" style="margin:.6rem 0 0;">À savoir : le bouton « ⧉ Dupliquer depuis Global » recopie
       <em>tout</em> le site dans la zone. Celle-ci cesse alors d'hériter, et une correction faite ensuite en global
       ne l'atteint plus. C'est pourquoi la colonne « Vraiment différents » compte ce qui s'écarte réellement —
       le bouton 🧹 Nettoyer remet le reste en héritage.</p>
    <div class="dg-barres">
      <div class="dg-ligne">
        <span class="dg-nom">🌐 Site global</span>
        <strong class="dg-val"><?= (int)($parPortee['__global'] ?? 0) ?></strong>
      </div>
      <?php foreach ($zones as $z): $n = $parPortee[(string)$z['slug']] ?? 0; ?>
        <div class="dg-ligne">
          <span class="dg-nom">📍 <?= e($z['name']) ?></span>
          <strong class="dg-val<?= $n === 0 ? ' dg-val--zero' : '' ?>"><?= $n ?></strong>
        </div>
      <?php endforeach; ?>
    </div>
    <?php
    $orphelines = array_diff(array_keys($parPortee), array_merge(['__global'], array_column($zones, 'slug')));
    if ($orphelines): ?>
      <p class="dg-warn" style="margin-top:1rem;">⚠️ Des textes sont enregistrés pour des zones qui n'existent plus :
         <?= e(implode(', ', $orphelines)) ?>. Ils ne s'affichent nulle part.</p>
    <?php endif; ?>
  </div>
</section>

<section class="admin-panel">
  <div class="admin-panel__head"><h2>4. Textes qui citent un autre secteur</h2></div>
  <div class="admin-panel__body">
    <p style="font-size:.9rem;color:#5b6b92;margin-top:0;">Le site global couvre le Doubs, le Jura et la Côte-d'Or ;
       chaque zone couvre son propre secteur. Cette liste compare ce que le visiteur lit réellement sur chaque page
       aux lieux de son secteur.</p>
    <?php if ($bilan): ?>
      <div class="dg-bilan">
        <p style="margin:0;">✅ Mise en conformité du <?= e(date('d/m/Y à H:i', strtotime((string)$bilan['date']))) ?> :
           <strong><?= (int)$bilan['ecrits'] ?></strong> titre(s) et description(s) réécrits avec les variables de lieu,
           <strong><?= (int)$bilan['retires'] ?></strong> texte(s) de zone qui citaient un autre secteur retirés
           (la zone reprend alors le texte global adapté à elle),
           <strong><?= (int)$bilan['variables'] ?></strong> variable(s) de lieu réglée(s).</p>
        <?php if (!empty($bilan['non_reconnues'])): ?>
          <p class="dg-warn" style="margin:.5rem 0 0;">Zones dont le secteur n'a pas été reconnu, laissées telles quelles :
             <?= e(implode(', ', $bilan['non_reconnues'])) ?>. Renseignez leurs codes postaux dans « Gérer les zones ».</p>
        <?php endif; ?>
        <form method="post" style="margin:.7rem 0 0;"
              onsubmit="return confirm('Remettre tous les textes exactement comme avant cette mise à jour ?');">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="secteurs_annuler">
          <input type="hidden" name="lot" value="<?= e((string)$bilan['lot']) ?>">
          <button class="admin-btn admin-btn--secondary" style="min-height:34px;padding:0 .7rem;font-size:.8rem;" type="submit">↩ Annuler cette mise à jour</button>
        </form>
      </div>
    <?php endif; ?>

    <?php if (!$controle): ?>
      <p class="dg-ok">✅ Aucun texte ne cite un lieu hors de son secteur.</p>
    <?php else: ?>
      <p class="dg-warn"><?= count($controle) ?> texte(s) à relire. Certains sont peut-être voulus
         (une ville voisine citée exprès) : seul vous pouvez en juger.</p>
      <?php foreach ($parPorteeControle as $portee => $lignes): ?>
        <h3 class="dg-portee"><?= e($portee) ?> <small>(<?= count($lignes) ?>)</small></h3>
        <div class="admin-table-wrap">
          <table class="admin-table">
            <thead><tr><th>Texte</th><th>Ce que lit le visiteur</th><th>Lieu hors secteur</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($lignes as $r): ?>
              <tr>
                <td style="font-weight:600;"><?= e($r['champ']) ?></td>
                <td style="font-size:.85rem;color:#4b5b7d;"><?= e($r['extrait']) ?></td>
                <td><strong style="color:#b45309;"><?= e(implode(', ', $r['etrangers'])) ?></strong></td>
                <td style="text-align:right;white-space:nowrap;">
                  <?php if ($r['lien']): ?><a class="admin-btn admin-btn--secondary" style="min-height:30px;padding:0 .6rem;font-size:.78rem;" href="<?= e($r['lien']) ?>">Corriger →</a><?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>

    <form method="post" style="margin-top:1rem;"
          onsubmit="return confirm('Relancer la mise en conformité ? Les textes de zone qui citent clairement un autre secteur seront retirés (avec sauvegarde).');">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="secteurs_appliquer">
      <button class="admin-btn admin-btn--secondary" type="submit">🔄 Relancer la mise en conformité</button>
    </form>
  </div>
</section>

<section class="admin-panel">
  <div class="admin-panel__head"><h2>5. Comment vérifier vous-même</h2></div>
  <div class="admin-panel__body">
    <ol style="margin:0 0 0 1.2rem;font-size:.9rem;color:#4b5b7d;line-height:1.9;">
      <li>Cliquez sur la pastille <strong>Jura</strong> dans le bandeau du haut. Le bandeau doit devenir orange et afficher « 📍 Jura ».</li>
      <li>Allez dans <strong>Contenu du site → Page d'accueil</strong> et changez le titre principal, puis enregistrez.</li>
      <li>Revenez sur cet écran : la ligne Jura du tableau ci-dessus doit afficher au moins <strong>1 texte propre</strong>.</li>
      <li>Ouvrez <code>/jura/</code> et la page d'accueil <code>/</code> : seule la première doit avoir changé.</li>
    </ol>
    <p style="margin-top:1rem;font-size:.88rem;color:#7b8aa8;">Si l'étape 3 reste à zéro, la zone n'était pas sélectionnée au moment d'enregistrer.</p>
  </div>
</section>

<style>
.dg-ok{color:#15803d;font-weight:600;} .dg-warn{color:#b45309;font-weight:600;} .dg-err{color:#b91c1c;font-weight:700;}
.dg-barres{display:flex;flex-direction:column;gap:.3rem;margin-top:.8rem;}
.dg-ligne{display:flex;align-items:center;gap:.8rem;padding:.4rem .7rem;background:#f7faff;border-radius:10px;}
.dg-nom{flex:1;font-size:.9rem;color:#3b4a6b;}
.dg-val{font-weight:800;color:#1b2d6b;font-variant-numeric:tabular-nums;}
.dg-val--zero{color:#b45309;}
.dg-bilan{background:#f3fbf5;border:1px solid #cdebd6;border-radius:12px;padding:.8rem 1rem;margin-bottom:1rem;font-size:.9rem;color:#24553a;}
.dg-portee{font-size:1rem;margin:1.2rem 0 .5rem;color:#1b2d6b;} .dg-portee small{color:#8494b4;font-weight:600;}
</style>
<?php require __DIR__ . '/partials/footer.php'; ?>
