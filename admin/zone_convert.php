<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/render.php';
require_once __DIR__ . '/../includes/admin_fields.php';
require_once __DIR__ . '/../includes/admin_replace.php';
require_admin();

/**
 * Conversion assistée : repère les noms de lieux écrits en dur dans vos
 * textes et propose de les remplacer par la variable correspondante.
 * Rien n'est appliqué sans validation, et l'annulation reste possible.
 */

/** Les valeurs de variables connues, avec la variable qui les remplacerait. */
function conv_candidats(): array
{
    $out = [];
    $prev = zone_context();

    foreach (array_merge([null], all_zones()) as $z) {
        set_zone_context($z);
        $portee = $z ? '📍 '.$z['name'] : '🌐 Site global';
        foreach (zone_vars_all() as $nom) {
            $valeur = zone_var_value($nom);
            // Un terme trop court provoquerait des remplacements absurdes.
            if (mb_strlen($valeur) < 4) continue;
            $cle = mb_strtolower($valeur);
            if (isset($out[$cle])) { $out[$cle]['portees'][] = $portee; continue; }
            $out[$cle] = ['valeur' => $valeur, 'variable' => $nom, 'portees' => [$portee]];
        }
    }
    set_zone_context($prev);

    // Le plus long d'abord : « Île-de-France » avant « France ».
    uasort($out, fn($a, $b) => mb_strlen($b['valeur']) <=> mb_strlen($a['valeur']));
    return $out;
}

$candidats = conv_candidats();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $cle  = mb_strtolower(trim((string)($_POST['terme'] ?? '')));
    $refs = $_POST['refs'] ?? [];
    $c    = $candidats[$cle] ?? null;

    if (!$c || !is_array($refs) || $refs === []) {
        flash('error', 'Aucune occurrence sélectionnée.');
    } else {
        $res = bulk_apply($refs, $c['valeur'], '{'.$c['variable'].'}');
        if ($res['done'] > 0) {
            admin_log('modification', 'Conversion en variable',
                      '« '.$c['valeur'].' » → {'.$c['variable'].'} sur '.$res['done'].' texte(s)');
        }
        flash('success', $res['done'].' texte'.($res['done'] > 1 ? 's' : '').' converti'
            .($res['done'] > 1 ? 's' : '').' en {'.$c['variable'].'}. Annulation possible depuis Chercher et remplacer.');
    }
    redirect_to('admin/zone_convert.php?terme='.rawurlencode($cle));
}

$terme    = mb_strtolower(trim((string)($_GET['terme'] ?? '')));
$choisi   = $candidats[$terme] ?? null;
$hits     = $choisi ? bulk_scan($choisi['valeur']) : [];
$enZone   = zone_ctx_id() > 0;

$adminSection = 'zone_convert';
require_once __DIR__ . '/partials/header.php';
?>
<div class="admin-page-toolbar">
  <div>
    <div class="admin-breadcrumb">Multi-zones</div>
    <h1 class="admin-page-title">✨ Convertir en variables
      <span class="cv-portee<?= $enZone ? ' cv-portee--zone' : '' ?>"><?= $enZone ? '📍 '.e(zone_ctx_name()) : '🌐 Site global' ?></span>
    </h1>
    <p class="admin-page-subtitle">Vos textes contiennent des noms de lieux écrits en dur. Les remplacer par une variable
       fait qu'ils s'adaptent tout seuls à chaque zone.</p>
  </div>
  <div class="admin-toolbar-actions">
    <a class="admin-btn admin-btn--secondary" href="<?= e(url_for('admin/zone_vars.php')) ?>">🏷️ Régler les variables</a>
  </div>
</div>

<?php if (!$candidats): ?>
<section class="admin-panel">
  <div class="admin-panel__body">
    <p>Aucune variable n'a encore de valeur renseignée.</p>
    <p style="color:#7b8aa8;font-size:.9rem;">Commencez par l'écran <strong>Variables de lieu</strong> :
       indiquez par exemple que <code>{region}</code> vaut « Île-de-France » pour une zone.
       Cet écran pourra alors repérer ce nom dans vos textes.</p>
  </div>
</section>
<?php else: ?>

<section class="admin-panel">
  <div class="admin-panel__head">
    <h2>Que voulez-vous convertir ?</h2>
    <p>Chaque terme correspond à la valeur d'une de vos variables. Choisissez-en un pour voir où il apparaît.</p>
  </div>
  <div class="admin-panel__body">
    <div class="cv-choix">
      <?php foreach ($candidats as $cle => $c):
          $n = count(bulk_scan($c['valeur'])); ?>
        <a class="cv-terme<?= $terme === $cle ? ' is-on' : '' ?><?= $n === 0 ? ' is-vide' : '' ?>"
           href="<?= e(url_for('admin/zone_convert.php?terme='.rawurlencode($cle))) ?>">
          <strong><?= e($c['valeur']) ?></strong>
          <span>→ {<?= e($c['variable']) ?>}</span>
          <em><?= $n === 0 ? 'aucune occurrence' : $n.' texte'.($n > 1 ? 's' : '') ?></em>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<?php if ($choisi): ?>
  <?php if (!$hits): ?>
  <section class="admin-panel">
    <div class="admin-panel__body"><p>Aucun texte ne contient « <?= e($choisi['valeur']) ?> » dans cette portée.</p></div>
  </section>
  <?php else: ?>
  <form method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="terme" value="<?= e($terme) ?>">
    <section class="admin-panel">
      <div class="admin-panel__head">
        <h2><?= count($hits) ?> texte<?= count($hits) > 1 ? 's' : '' ?> à convertir</h2>
        <p>« <?= e($choisi['valeur']) ?> » deviendra <code>{<?= e($choisi['variable']) ?>}</code>.
           <strong>Décochez les textes qui désignent vraiment ce lieu précis</strong> — par exemple
           « notre agence historique de <?= e($choisi['valeur']) ?> », qui ne doit pas changer d'une zone à l'autre.</p>
      </div>
      <div class="admin-panel__body admin-table-wrap">
        <table class="admin-table">
          <thead><tr>
            <th style="width:34px;"><input type="checkbox" id="cv-all" checked></th>
            <th style="width:150px;">Portée</th><th>Emplacement</th><th>Avant, puis après</th>
          </tr></thead>
          <tbody>
          <?php foreach ($hits as $h):
              $apres = bulk_replace_text($h['value'], $choisi['valeur'], '{'.$choisi['variable'].'}'); ?>
            <tr>
              <td><input type="checkbox" class="cv-cb" name="refs[]" value="<?= e($h['ref']) ?>" checked></td>
              <td style="font-size:.82rem;white-space:nowrap;"><?= e($h['scope']) ?></td>
              <td style="font-size:.85rem;"><?= e($h['label']) ?></td>
              <td style="max-width:520px;font-size:.85rem;line-height:1.55;">
                <div style="color:#7b8aa8;"><?= e(mb_substr($h['value'], 0, 200)) ?></div>
                <div style="margin-top:.3rem;padding-top:.3rem;border-top:1px dashed #dde5f3;color:#14532d;font-weight:600;">
                  <?= e(mb_substr($apres, 0, 200)) ?>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <div class="admin-savebar" style="display:flex;gap:1rem;align-items:center;flex-wrap:wrap;">
        <span style="font-size:.82rem;color:#7b88a6;margin-right:auto;">
          Après conversion, ces textes afficheront la valeur propre à chaque zone.
        </span>
        <button class="admin-btn admin-btn--primary" type="submit"
                onclick="return confirm('Convertir les textes cochés en {<?= e($choisi['variable']) ?>} ?');">Convertir</button>
      </div>
    </section>
  </form>
  <?php endif; ?>
<?php endif; ?>
<?php endif; ?>

<style>
.cv-portee{display:inline-block;vertical-align:middle;margin-left:.6rem;font-size:.8rem;font-weight:800;
  border-radius:20px;padding:.2rem .7rem;background:#eef2fb;color:#4b5b7d;}
.cv-portee--zone{background:#F07B1D;color:#fff;}
.cv-choix{display:flex;flex-wrap:wrap;gap:.5rem;}
.cv-terme{display:flex;flex-direction:column;gap:.1rem;border:1px solid #dde5f3;background:#f7faff;border-radius:12px;
  padding:.5rem .85rem;text-decoration:none;color:#3b4a6b;min-width:9rem;}
.cv-terme:hover{background:#eaf1ff;border-color:#b9c7e2;}
.cv-terme.is-on{background:#1b2d6b;border-color:#1b2d6b;color:#fff;}
.cv-terme.is-on span,.cv-terme.is-on em{color:#c3cde0;}
.cv-terme.is-vide{opacity:.5;}
.cv-terme span{font-size:.78rem;color:#2f66d2;font-family:ui-monospace,monospace;}
.cv-terme em{font-style:normal;font-size:.72rem;color:#8494b4;}
</style>
<script>
document.getElementById('cv-all')?.addEventListener('change', function(){
  document.querySelectorAll('.cv-cb').forEach(function(c){ c.checked = this.checked; }, this);
});
</script>
<?php require __DIR__ . '/partials/footer.php'; ?>
