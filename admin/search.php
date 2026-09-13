<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/render.php';
require_once __DIR__ . '/../includes/admin_fields.php';
require_once __DIR__ . '/../includes/admin_replace.php';
require_admin();

$q       = trim((string)($_GET['q'] ?? ''));
$to      = (string)($_GET['to'] ?? '');
$applied = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    if (($_POST['action'] ?? '') === 'undo') {
        $n = bulk_undo();
        flash('success', $n > 0 ? $n.' texte'.($n>1?'s':'').' restauré'.($n>1?'s':'').'.' : 'Rien à restaurer.');
        redirect_to('admin/search.php');
    }
    $q  = trim((string)($_POST['q'] ?? ''));
    $to = (string)($_POST['to'] ?? '');
    $refs = $_POST['refs'] ?? [];
    if (!is_array($refs) || $refs === []) {
        flash('error', 'Aucune occurrence sélectionnée.');
    } elseif (mb_strlen($q) < 2) {
        flash('error', 'Le mot recherché est trop court.');
    } else {
        $applied = bulk_apply($refs, $q, $to);
        flash('success', $applied['done'].' texte'.($applied['done']>1?'s':'').' modifié'
            .($applied['done']>1?'s':'').($applied['skipped'] ? ' — '.$applied['skipped'].' ignoré(s)' : '').'.');
        redirect_to('admin/search.php?q='.rawurlencode($q).'&to='.rawurlencode($to).'&done=1');
    }
}

$hits    = $q !== '' ? bulk_scan($q) : [];
$total   = 0; foreach ($hits as $h) $total += $h['n'];
$undoable = bulk_last_undo();

/** Surligne le terme dans un extrait, en montrant le résultat s'il y a remplacement. */
function sr_preview(string $text, string $term, string $to, bool $after): string
{
    $pos = mb_stripos($text, $term);
    if ($pos === false) return e(mb_substr($text, 0, 160));
    $from = max(0, $pos - 60);
    $cut  = mb_substr($text, $from, 220);
    $pre  = $from > 0 ? '…' : '';
    $rep  = $after ? '<ins>'.e($to).'</ins>' : '<mark>$0</mark>';
    $out  = preg_replace('/'.preg_quote($term,'/').'/ui',
                $after ? ($to === '' ? '<ins class="sr-del">supprimé</ins>' : '<ins>'.e($to).'</ins>') : '<mark>$0</mark>',
                e($cut)) ?? e($cut);
    return $pre.$out.(mb_strlen($text) > $from + 220 ? '…' : '');
}

$adminSection = 'search';
require_once __DIR__ . '/partials/header.php';
?>
<div class="admin-page-toolbar">
  <div>
    <div class="admin-breadcrumb">Recherche</div>
    <h1 class="admin-page-title">🔎 Chercher et remplacer</h1>
    <p class="admin-page-subtitle">Tapez un mot lu sur votre site. Laissez le second champ vide pour seulement le retrouver, ou remplissez-le pour le remplacer partout.</p>
  </div>
</div>

<?php if ($undoable): ?>
<section class="admin-panel sr-undo">
  <div class="admin-panel__body">
    <form method="post" style="display:flex;align-items:center;gap:.8rem;flex-wrap:wrap;margin:0;"
          onsubmit="return confirm('Restaurer les textes tels qu\'ils étaient avant ce remplacement ?');">
      <?= csrf_field() ?><input type="hidden" name="action" value="undo">
      <span>Dernier remplacement : <strong><?= e($undoable['term']) ?></strong> → <strong><?= e($undoable['to'] !== '' ? $undoable['to'] : '(supprimé)') ?></strong>,
        <?= count($undoable['items']) ?> texte<?= count($undoable['items'])>1?'s':'' ?>.</span>
      <button class="admin-btn admin-btn--secondary" type="submit">↺ Annuler ce remplacement</button>
    </form>
  </div>
</section>
<?php endif; ?>

<section class="admin-panel">
  <div class="admin-panel__body">
    <form method="get" class="sr-form">
      <label class="sr-lab"><span>Chercher</span>
        <input type="text" name="q" value="<?= e($q) ?>" placeholder="Occitanie" autofocus>
      </label>
      <span class="sr-arrow">→</span>
      <label class="sr-lab"><span>Remplacer par <em>(facultatif)</em></span>
        <input type="text" name="to" value="<?= e($to) ?>" placeholder="Île-de-France">
      </label>
      <button class="admin-btn admin-btn--primary" type="submit">Rechercher</button>
    </form>
    <p class="sr-note">La recherche ne tient pas compte des majuscules. Rien n'est modifié tant que vous n'avez pas validé l'aperçu.</p>
  </div>
</section>

<?php if ($q !== ''): ?>
<section class="admin-panel">
  <div class="admin-panel__head">
    <h2><?= count($hits) ?> texte<?= count($hits)>1?'s':'' ?> contenant « <?= e($q) ?> »<?= $total > count($hits) ? ' ('.$total.' occurrences)' : '' ?></h2>
    <?php if (!$hits): ?>
      <p>Aucun texte ne contient ce mot. Essayez une portion plus courte, ou vérifiez l'orthographe.</p>
    <?php elseif ($to !== ''): ?>
      <p>Décochez ce que vous ne voulez pas toucher, puis validez. Vous pourrez annuler après coup.</p>
    <?php else: ?>
      <p>Renseignez le champ « Remplacer par » ci-dessus pour tout corriger d'un coup, ou modifiez texte par texte.</p>
    <?php endif; ?>
  </div>

  <?php if ($hits): ?>
  <form method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="q" value="<?= e($q) ?>">
    <input type="hidden" name="to" value="<?= e($to) ?>">
    <div class="admin-panel__body admin-table-wrap">
      <table class="admin-table">
        <thead><tr>
          <?php if ($to !== ''): ?><th style="width:34px;"><input type="checkbox" id="sr-all" checked></th><?php endif; ?>
          <th style="width:150px;">Portée</th>
          <th>Emplacement</th>
          <th>Texte<?= $to !== '' ? ' — avant, puis après' : '' ?></th>
        </tr></thead>
        <tbody>
        <?php foreach ($hits as $h): ?>
          <tr>
            <?php if ($to !== ''): ?>
            <td><input type="checkbox" class="sr-cb" name="refs[]" value="<?= e($h['ref']) ?>" checked></td>
            <?php endif; ?>
            <td style="white-space:nowrap;font-size:.82rem;"><?= e($h['scope']) ?></td>
            <td style="font-size:.85rem;"><?= e($h['label']) ?>
              <?php if ($h['n'] > 1): ?><br><small style="color:#7b8aa8;"><?= $h['n'] ?> occurrences</small><?php endif; ?>
            </td>
            <td style="max-width:520px;font-size:.85rem;line-height:1.55;">
              <div class="sr-before"><?= sr_preview($h['value'], $q, $to, false) ?></div>
              <?php if ($to !== ''): ?>
                <div class="sr-after"><?= sr_preview($h['value'], $q, $to, true) ?></div>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php if ($to !== ''): ?>
    <div class="admin-savebar" style="display:flex;gap:1rem;align-items:center;flex-wrap:wrap;">
      <span style="font-size:.82rem;color:#7b88a6;margin-right:auto;">
        « <?= e($q) ?> » deviendra « <?= e($to) ?> » dans les textes cochés.
      </span>
      <button class="admin-btn admin-btn--primary" type="submit"
              onclick="return confirm('Appliquer le remplacement aux textes cochés ?');">Remplacer</button>
    </div>
    <?php endif; ?>
  </form>
  <?php endif; ?>
</section>
<?php endif; ?>

<style>
.sr-form{display:flex;gap:.7rem;align-items:flex-end;flex-wrap:wrap;}
.sr-lab{display:flex;flex-direction:column;gap:.25rem;flex:1;min-width:200px;}
.sr-lab span{font-size:.78rem;font-weight:700;color:#5b6b92;}
.sr-lab em{font-style:normal;font-weight:400;color:#8494b4;}
.sr-lab input{padding:.65rem .85rem;border:1px solid #dde5f3;border-radius:12px;font-size:1rem;}
.sr-lab input:focus{outline:2px solid #2f66d2;outline-offset:1px;}
.sr-arrow{padding-bottom:.7rem;color:#8494b4;font-weight:700;}
.sr-note{margin:.7rem 0 0;font-size:.78rem;color:#8494b4;}
.sr-undo{border-left:4px solid #F07B1D;background:#fffaf4;}
.sr-before{color:#7b8aa8;}
.sr-after{margin-top:.3rem;padding-top:.3rem;border-top:1px dashed #dde5f3;color:#1b2d6b;}
mark{background:#ffe0e0;color:#9b1c1c;padding:0 .12em;border-radius:3px;}
ins{background:#d7f5df;color:#14532d;text-decoration:none;padding:0 .12em;border-radius:3px;font-weight:600;}
ins.sr-del{background:#f1f5f9;color:#64748b;font-style:italic;font-weight:400;}
</style>
<script>
document.getElementById('sr-all')?.addEventListener('change', function(){
  document.querySelectorAll('.sr-cb').forEach(function(c){ c.checked = this.checked; }, this);
});
</script>
<?php require __DIR__ . '/partials/footer.php'; ?>
