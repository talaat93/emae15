<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/render.php';
require_once __DIR__ . '/../includes/admin_fields.php';
require_admin();

$q       = trim((string)($_GET['q'] ?? ''));
$results = $q !== '' ? admin_search_fields($q) : [];

/** Met en évidence la portion trouvée, sur un texte déjà échappé. */
function pcs_mark(string $text, string $needle): string
{
    $text = mb_substr($text, 0, 220);
    $pos  = mb_stripos($text, $needle);
    if ($needle === '' || $pos === false) return e($text);
    $len = mb_strlen($needle);
    return e(mb_substr($text, 0, $pos))
         . '<mark>' . e(mb_substr($text, $pos, $len)) . '</mark>'
         . e(mb_substr($text, $pos + $len));
}

$adminSection = 'search';
require_once __DIR__ . '/partials/header.php';
?>
<div class="admin-page-toolbar">
  <div>
    <div class="admin-breadcrumb">Recherche</div>
    <h1 class="admin-page-title">🔎 Trouver un texte</h1>
    <p class="admin-page-subtitle">Tapez quelques mots lus sur votre site : vous arrivez directement sur le champ qui les contient.</p>
  </div>
</div>

<section class="admin-panel">
  <div class="admin-panel__body">
    <form method="get" class="sr-form">
      <input type="text" name="q" value="<?= e($q) ?>" placeholder="Ex : un seul interlocuteur" autofocus>
      <button class="admin-btn admin-btn--primary" type="submit">Rechercher</button>
    </form>
  </div>
</section>

<?php if ($q !== ''): ?>
<section class="admin-panel">
  <div class="admin-panel__head">
    <h2><?= count($results) ?> résultat<?= count($results) > 1 ? 's' : '' ?></h2>
    <?php if (empty($results)): ?>
      <p>Aucun champ ne contient « <?= e($q) ?> ». Essayez moins de mots, ou une portion exacte du texte affiché sur le site.</p>
    <?php endif; ?>
  </div>
  <?php if (!empty($results)): ?>
  <div class="admin-panel__body admin-table-wrap">
    <table class="admin-table">
      <thead><tr><th>Emplacement</th><th>Champ</th><th>Texte actuel</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($results as $r): ?>
        <tr>
          <td style="white-space:nowrap;"><strong><?= e($r['page_label']) ?></strong><br><small style="color:#7b8aa8;"><?= e($r['section']) ?></small></td>
          <td><?= e($r['label']) ?></td>
          <td style="max-width:420px;font-size:.86rem;color:#5b6b92;"><?= pcs_mark($r['value'], $q) ?></td>
          <td style="text-align:right;">
            <a class="admin-btn admin-btn--primary" style="min-height:36px;padding:0 .8rem;font-size:.82rem;"
               href="<?= e(url_for('admin/page_content.php?p='.$r['page_id'].'&field='.$r['key'].'#s-'.$r['section_id'])) ?>">Modifier</a>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</section>
<?php endif; ?>

<style>
.sr-form{display:flex;gap:.6rem;flex-wrap:wrap;}
.sr-form input{flex:1;min-width:240px;padding:.7rem .9rem;border:1px solid #dde5f3;border-radius:12px;font-size:1rem;}
.sr-form input:focus{outline:2px solid #2f66d2;outline-offset:1px;}
mark{background:#ffe8c9;color:#7a3d00;padding:0 .1em;border-radius:3px;}
</style>
<?php require __DIR__ . '/partials/footer.php'; ?>
