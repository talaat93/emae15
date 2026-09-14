<?php
/**
 * Rendu d'un champ de type liste : des lignes qu'on ajoute, supprime et
 * réordonne. Attend $f (le champ du catalogue) fourni par l'appelant.
 */
declare(strict_types=1);
$lKey   = $f['key'];
$lCols  = admin_list_columns($f);
$lRows  = admin_list_rows($f);
$lMulti = count($lCols) > 1;
$lOwn   = admin_list_stored($f) !== null;
?>
<div class="admin-field fl" style="grid-column:1/-1" data-key="<?= e($lKey) ?>">
  <span class="fl__title">
    <?= e($f['label']) ?>
    <em class="fl__count"><?= count($lRows) ?> élément<?= count($lRows) > 1 ? 's' : '' ?></em>
    <?php if (!$lOwn): ?>
      <em class="pc-tag"><?= zone_ctx_id() > 0 ? 'hérité du global' : 'liste d\'origine' ?></em>
    <?php endif; ?>
  </span>
  <?php if (!empty($f['help'])): ?><small class="pc-help"><?= e($f['help']) ?></small><?php endif; ?>

  <?php if ($lMulti): ?>
  <div class="fl__head">
    <span class="fl__grip"></span>
    <?php foreach ($lCols as $c): ?>
      <span class="fl__col" style="<?= !empty($c['w']) ? 'flex:0 0 '.e($c['w']).';' : '' ?>"><?= e($c['label']) ?></span>
    <?php endforeach; ?>
    <span class="fl__del"></span>
  </div>
  <?php endif; ?>

  <div class="fl__rows">
    <?php foreach ($lRows as $i => $row):
        $vals = $lMulti ? (is_array($row) ? array_values($row) : []) : [is_array($row) ? (string)reset($row) : (string)$row];
    ?>
    <div class="fl__row">
      <span class="fl__grip" title="Glisser pour réordonner">⠿</span>
      <?php foreach ($lCols as $ci => $c):
          $name = $lKey.'['.$i.']['.$c['k'].']';
          $val  = (string)($vals[$ci] ?? '');
          $style = !empty($c['w']) ? 'flex:0 0 '.e($c['w']).';' : '';
      ?>
        <?php if (($c['type'] ?? 'text') === 'textarea'): ?>
          <textarea name="<?= e($name) ?>" rows="2" style="<?= $style ?>" placeholder="<?= e($c['label']) ?>"><?= e($val) ?></textarea>
        <?php else: ?>
          <input type="text" name="<?= e($name) ?>" value="<?= e($val) ?>" style="<?= $style ?>" placeholder="<?= e($c['label']) ?>">
        <?php endif; ?>
      <?php endforeach; ?>
      <button type="button" class="fl__del" title="Supprimer cette ligne">✕</button>
    </div>
    <?php endforeach; ?>
  </div>

  <div class="fl__actions">
    <button type="button" class="fl__add">+ Ajouter</button>
    <small>Videz toutes les lignes pour revenir à la liste <?= zone_ctx_id() > 0 ? 'du site global' : 'd\'origine' ?>.</small>
  </div>

  <template class="fl__tpl"><?php
    echo '<div class="fl__row"><span class="fl__grip" title="Glisser pour réordonner">⠿</span>';
    foreach ($lCols as $c) {
        $style = !empty($c['w']) ? 'flex:0 0 '.e($c['w']).';' : '';
        $nm    = $lKey.'[__I__]['.$c['k'].']';
        echo (($c['type'] ?? 'text') === 'textarea')
            ? '<textarea name="'.e($nm).'" rows="2" style="'.$style.'" placeholder="'.e($c['label']).'"></textarea>'
            : '<input type="text" name="'.e($nm).'" value="" style="'.$style.'" placeholder="'.e($c['label']).'">';
    }
    echo '<button type="button" class="fl__del" title="Supprimer cette ligne">✕</button></div>';
  ?></template>
</div>
