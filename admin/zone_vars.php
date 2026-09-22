<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/render.php';
require_admin();

$enZone = zone_ctx_id() > 0;
$portee = $enZone ? '📍 '.zone_ctx_name() : '🌐 Site global';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $n = 0;

    foreach ((array)($_POST['vars'] ?? []) as $nom => $valeur) {
        $nom = strtolower(preg_replace('/[^a-z0-9_]/i', '', (string)$nom) ?? '');
        if ($nom === '' || !is_string($valeur)) continue;
        if (zone_var_raw($nom) !== trim($valeur)) { zone_var_save($nom, $valeur); $n++; }
    }

    // Création d'une variable personnalisée
    $nouveau = strtolower(preg_replace('/[^a-z0-9_]/i', '', (string)($_POST['new_name'] ?? '')) ?? '');
    $nouvVal = trim((string)($_POST['new_value'] ?? ''));
    if ($nouveau !== '' && $nouvVal !== '') {
        if (isset(zone_vars_standard()[$nouveau])) {
            flash('error', '« '.$nouveau.' » existe déjà dans les variables de lieu.');
        } else {
            zone_var_save($nouveau, $nouvVal);
            $n++;
        }
    }

    if ($n > 0) admin_log('modification', 'Variables — '.($enZone ? zone_ctx_name() : 'Site global'), $n.' variable(s)');
    flash('success', $n === 0 ? 'Aucune modification.' : $n.' variable'.($n > 1 ? 's' : '').' enregistrée'.($n > 1 ? 's' : '').'.');
    redirect_to('admin/zone_vars.php');
}

$standards = zone_vars_standard();
$perso     = zone_vars_custom();

$adminSection = 'zone_vars';
require_once __DIR__ . '/partials/header.php';
?>
<div class="admin-page-toolbar">
  <div>
    <div class="admin-breadcrumb">Multi-zones</div>
    <h1 class="admin-page-title">🏷️ Variables de lieu
      <span class="zv-portee<?= $enZone ? ' zv-portee--zone' : '' ?>"><?= e($portee) ?></span>
    </h1>
    <p class="admin-page-subtitle">Écrivez <code>{ville}</code> ou <code>{en_region}</code> dans n'importe quel texte du site :
       chaque zone affichera sa propre valeur.</p>
  </div>
</div>

<?php if (!$enZone): ?>
<div class="zv-note">
  Vous définissez ici les <strong>valeurs par défaut</strong>. Chaque zone reprend ces valeurs
  tant qu'elle n'indique pas les siennes — choisissez une zone dans le bandeau du haut pour la personnaliser.
</div>
<?php else: ?>
<div class="zv-note zv-note--zone">
  Vous définissez les valeurs de <strong><?= e(zone_ctx_name()) ?></strong>.
  Un champ laissé vide reprend la valeur du site global, affichée en filigrane.
</div>
<?php endif; ?>

<form method="post">
  <?= csrf_field() ?>

  <section class="admin-panel">
    <div class="admin-panel__head">
      <h2>Variables de lieu</h2>
      <p>Les formes « avec préposition » existent parce que le français est irrégulier :
         on dit <em>en Île-de-France</em> mais <em>dans le Jura</em>. Les renseigner évite les tournures fautives.</p>
    </div>
    <div class="admin-panel__body">
      <?php foreach ($standards as $nom => [$label, $exemple, $aide]):
          $brut   = zone_var_raw($nom);
          $herite = $enZone ? setting_plain(ZVAR_PREFIX.$nom) : ''; ?>
        <div class="zv-ligne">
          <div class="zv-cle">
            <code>{<?= e($nom) ?>}</code>
            <span class="zv-label"><?= e($label) ?></span>
          </div>
          <div class="zv-saisie">
            <input type="text" name="vars[<?= e($nom) ?>]" value="<?= e($brut) ?>"
                   placeholder="<?= e($herite !== '' ? $herite.'  (hérité)' : $exemple) ?>">
            <small><?= e($aide) ?></small>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </section>

  <?php if ($perso): ?>
  <section class="admin-panel">
    <div class="admin-panel__head"><h2>Vos variables</h2></div>
    <div class="admin-panel__body">
      <?php foreach ($perso as $nom):
          $brut   = zone_var_raw($nom);
          $herite = $enZone ? setting_plain(ZVAR_PREFIX.$nom) : ''; ?>
        <div class="zv-ligne">
          <div class="zv-cle"><code>{<?= e($nom) ?>}</code></div>
          <div class="zv-saisie">
            <input type="text" name="vars[<?= e($nom) ?>]" value="<?= e($brut) ?>"
                   placeholder="<?= e($herite !== '' ? $herite.'  (hérité)' : 'Valeur pour cette portée') ?>">
            <small>Videz le champ pour que cette portée reprenne la valeur globale.</small>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endif; ?>

  <section class="admin-panel">
    <div class="admin-panel__head">
      <h2>Créer une variable</h2>
      <p>Pour tout ce qui change d'une zone à l'autre : délai annoncé, nom d'agence, numéro local…</p>
    </div>
    <div class="admin-panel__body">
      <div class="admin-form-grid admin-form-grid--2">
        <label class="admin-field"><span>Nom — lettres, chiffres et tirets bas</span>
          <input type="text" name="new_name" placeholder="delai_intervention" pattern="[A-Za-z0-9_]+"></label>
        <label class="admin-field"><span>Valeur pour <?= e($portee) ?></span>
          <input type="text" name="new_value" placeholder="moins de 2 heures"></label>
      </div>
      <p style="font-size:.85rem;color:#7b8aa8;margin:.6rem 0 0;">
        Vous l'utiliserez ensuite en écrivant <code>{delai_intervention}</code> dans vos textes.</p>
    </div>
  </section>

  <div class="admin-savebar">
    <span style="font-size:.82rem;color:#7b88a6;margin-right:auto;">
      Ces valeurs s'appliquent à tous les textes du site, y compris les titres destinés à Google.
    </span>
    <button class="admin-btn admin-btn--primary" type="submit">Enregistrer</button>
  </div>
</form>

<section class="admin-panel">
  <div class="admin-panel__head"><h2>Aperçu</h2><p>Ce que donnent vos valeurs dans une phrase, pour <?= e($portee) ?>.</p></div>
  <div class="admin-panel__body">
    <?php foreach ([
        'Dépannage électrique {en_ville}, intervention rapide.',
        'Nous intervenons {en_region} et {en_departement}.',
        'Électricien {ville} ({code_postal}) — Devis gratuit',
    ] as $exemple): ?>
      <div class="zv-apercu">
        <span class="zv-avant"><?= e($exemple) ?></span>
        <span class="zv-fleche">→</span>
        <strong class="zv-apres"><?= e(zone_vars_apply($exemple)) ?></strong>
      </div>
    <?php endforeach; ?>
    <p style="font-size:.84rem;color:#8494b4;margin:.8rem 0 0;">
      Une variable non renseignée reste affichée telle quelle, par exemple <code>{code_postal}</code> :
      c'est volontaire, pour que vous la repériez plutôt que de voir un trou dans la phrase.</p>
  </div>
</section>

<style>
.zv-portee{display:inline-block;vertical-align:middle;margin-left:.6rem;font-size:.8rem;font-weight:800;
  border-radius:20px;padding:.2rem .7rem;background:#eef2fb;color:#4b5b7d;}
.zv-portee--zone{background:#F07B1D;color:#fff;}
.zv-note{background:#f0f4ff;border:1px solid #dde5f3;border-radius:12px;padding:.8rem 1rem;margin-bottom:1.3rem;
  font-size:.88rem;color:#4b5b7d;line-height:1.6;}
.zv-note--zone{background:#fffaf4;border-color:#f5d5b0;color:#7a4a12;}
.zv-ligne{display:flex;gap:1rem;align-items:flex-start;padding:.6rem 0;border-bottom:1px solid #f1f5fb;}
.zv-ligne:last-child{border-bottom:none;}
.zv-cle{flex:0 0 220px;display:flex;flex-direction:column;gap:.15rem;}
.zv-cle code{background:#eef2fb;color:#1b2d6b;border-radius:6px;padding:.15rem .4rem;font-size:.85rem;width:max-content;}
.zv-label{font-size:.78rem;color:#8494b4;}
.zv-saisie{flex:1;min-width:0;display:flex;flex-direction:column;gap:.2rem;}
.zv-saisie input{padding:.55rem .75rem;border:1px solid #dde5f3;border-radius:10px;font-size:.92rem;}
.zv-saisie input:focus{outline:2px solid #2f66d2;outline-offset:1px;}
.zv-saisie small{color:#8494b4;font-size:.76rem;}
.zv-apercu{display:flex;gap:.6rem;align-items:baseline;flex-wrap:wrap;padding:.35rem 0;font-size:.88rem;}
.zv-avant{color:#8494b4;} .zv-fleche{color:#c3cde0;} .zv-apres{color:#14532d;}
@media(max-width:700px){.zv-ligne{flex-direction:column;gap:.35rem;}.zv-cle{flex:none;}}
</style>
<?php require __DIR__ . '/partials/footer.php'; ?>
