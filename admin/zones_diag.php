<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/render.php';
require_admin();

/**
 * État réel du système multi-zones : ce que la base contient vraiment,
 * quelle zone est active, et ce que chaque zone personnalise.
 * Sert à comprendre pourquoi une modification part au mauvais endroit.
 */

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

$adminSection = 'zones_manager';
require_once __DIR__ . '/partials/header.php';
?>
<div class="admin-page-toolbar">
  <div>
    <div class="admin-breadcrumb">Multi-zones</div>
    <h1 class="admin-page-title">🩺 Diagnostic des zones</h1>
    <p class="admin-page-subtitle">L'état réel du système, pour comprendre où partent vos modifications.</p>
  </div>
  <div class="admin-toolbar-actions">
    <a class="admin-btn admin-btn--secondary" href="<?= e(url_for('admin/zones_manager.php')) ?>">← Gérer les zones</a>
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
          <thead><tr><th>Zone</th><th>Adresse publique</th><th>Statut</th><th>Textes personnalisés</th></tr></thead>
          <tbody>
          <?php foreach ($zones as $z):
              $n = $parPortee[(string)$z['slug']] ?? 0; ?>
            <tr>
              <td style="font-weight:700;"><?= e($z['name']) ?></td>
              <td><a href="<?= e(url_for($z['slug'].'/')) ?>" target="_blank"><code>/<?= e($z['slug']) ?>/</code> ↗</a></td>
              <td><?= (int)$z['status'] === 1
                      ? '<span style="color:#15803d;font-weight:700;">active</span>'
                      : '<span style="color:#b91c1c;">inactive — page inaccessible</span>' ?></td>
              <td>
                <?php if ($n > 0): ?>
                  <strong style="color:#15803d;"><?= $n ?></strong> texte<?= $n > 1 ? 's' : '' ?> propre<?= $n > 1 ? 's' : '' ?>
                <?php else: ?>
                  <span style="color:#b45309;">aucun — cette zone affiche exactement le site global</span>
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
  <div class="admin-panel__head"><h2>4. Comment vérifier vous-même</h2></div>
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
</style>
<?php require __DIR__ . '/partials/footer.php'; ?>
