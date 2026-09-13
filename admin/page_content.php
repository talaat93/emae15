<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/render.php';
require_once __DIR__ . '/../includes/admin_fields.php';
require_admin();

$pageId = preg_replace('/[^a-z0-9_-]/', '', (string)($_GET['p'] ?? 'accueil'));
$page   = admin_catalog_page($pageId);
if (!$page) { flash('error', 'Page inconnue.'); redirect_to('admin/index.php'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $n = 0;
    foreach (admin_catalog_keys($pageId) as $key) {
        if (!array_key_exists($key, $_POST)) continue;
        $new = trim((string)$_POST[$key]);
        if ($new !== raw_setting($key)) { set_setting($key, $new); $n++; }
    }
    flash('success', $n === 0 ? 'Aucune modification.' : $n.' texte'.($n > 1 ? 's' : '').' enregistré'.($n > 1 ? 's' : '').'.');
    redirect_to('admin/page_content.php?p='.$pageId.(isset($_POST['_anchor']) && $_POST['_anchor'] !== '' ? '#s-'.preg_replace('/[^a-z0-9_-]/','',(string)$_POST['_anchor']) : ''));
}

$adminSection = 'content_'.$pageId;
$focusKey     = preg_replace('/[^a-z0-9_]/', '', (string)($_GET['field'] ?? ''));
$inZone       = zone_ctx_id() > 0;
require_once __DIR__ . '/partials/header.php';
?>
<div class="admin-page-toolbar">
  <div>
    <div class="admin-breadcrumb">Contenu du site</div>
    <h1 class="admin-page-title"><?= e($page['icon'].' '.$page['label']) ?></h1>
    <p class="admin-page-subtitle"><?= e($page['intro'] ?? '') ?></p>
  </div>
  <div class="admin-toolbar-actions">
    <a class="admin-btn admin-btn--secondary" href="<?= e(route_url((string)$page['route'])) ?>" target="_blank">Voir la page</a>
  </div>
</div>

<div class="pc-jump">
  <span class="pc-jump__label">Aller à</span>
  <?php foreach ($page['sections'] as $s): ?>
    <a href="#s-<?= e($s['id']) ?>"><?= e($s['label']) ?></a>
  <?php endforeach; ?>
</div>

<form method="post" class="admin-stack" id="pc-form">
  <?= csrf_field() ?>
  <input type="hidden" name="_anchor" id="pc-anchor" value="">

  <?php foreach ($page['sections'] as $s): ?>
  <section class="admin-panel" id="s-<?= e($s['id']) ?>">
    <div class="admin-panel__head">
      <h2><?= e($s['label']) ?></h2>
      <p><?= e($s['seen'] ?? '') ?></p>
    </div>
    <div class="admin-panel__body">
      <?php if (!empty($s['link'])): ?>
        <p style="margin:0 0 1rem;"><a class="admin-btn admin-btn--secondary" href="<?= e(url_for($s['link'][0])) ?>"><?= e($s['link'][1]) ?> →</a></p>
      <?php endif; ?>
      <div class="admin-form-grid admin-form-grid--2">
        <?php foreach ($s['fields'] as $f):
            $key     = $f['key'];
            $raw     = raw_setting($key);
            $default = (string)($f['default'] ?? '');
            $shown   = setting($key, $default);   // ce que le visiteur voit aujourd'hui
            $isFocus = ($focusKey !== '' && $focusKey === $key);
            $wide    = ($f['type'] ?? 'text') === 'textarea';
        ?>
        <label class="admin-field pc-field<?= $isFocus ? ' is-focus' : '' ?>"<?= $wide ? ' style="grid-column:1/-1"' : '' ?> data-key="<?= e($key) ?>">
          <span>
            <?= e($f['label']) ?>
            <?php if ($raw === ''): ?>
              <em class="pc-tag"><?= $inZone ? 'hérité du global' : 'texte par défaut' ?></em>
            <?php endif; ?>
          </span>
          <?php if ($wide): ?>
            <textarea name="<?= e($key) ?>" rows="3" placeholder="<?= e($shown) ?>"><?= e($raw) ?></textarea>
          <?php else: ?>
            <input type="text" name="<?= e($key) ?>" value="<?= e($raw) ?>" placeholder="<?= e($shown) ?>">
          <?php endif; ?>
          <?php if (!empty($f['help'])): ?><small class="pc-help"><?= e($f['help']) ?></small><?php endif; ?>
        </label>
        <?php endforeach; ?>
      </div>
    </div>
  </section>
  <?php endforeach; ?>

  <div class="admin-savebar pc-savebar">
    <span class="pc-savebar__note">
      <?= $inZone
          ? 'Un champ laissé vide reprend le texte du site global.'
          : 'Un champ laissé vide affiche le texte par défaut indiqué en filigrane.' ?>
    </span>
    <button class="admin-btn admin-btn--primary" type="submit">Enregistrer</button>
  </div>
</form>

<style>
.pc-jump{display:flex;flex-wrap:wrap;align-items:center;gap:.4rem;background:#fff;border:1px solid #dde5f3;border-radius:14px;padding:.7rem .9rem;margin-bottom:1.25rem;}
.pc-jump__label{font-size:.7rem;letter-spacing:.09em;text-transform:uppercase;color:#8494b4;font-weight:700;margin-right:.2rem;}
.pc-jump a{font-size:.82rem;font-weight:600;color:#4b5b7d;text-decoration:none;background:#f7faff;border:1px solid #dde5f3;border-radius:20px;padding:.3rem .75rem;}
.pc-jump a:hover{background:#eaf1ff;color:#1b2d6b;}
.pc-tag{font-style:normal;font-size:.68rem;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:#8494b4;background:#f0f4ff;border-radius:20px;padding:.1rem .45rem;margin-left:.35rem;}
.pc-help{display:block;margin-top:.3rem;color:#8494b4;font-size:.76rem;line-height:1.45;}
.pc-field.is-focus{outline:3px solid #F07B1D;outline-offset:6px;border-radius:8px;}
.pc-savebar{position:sticky;bottom:0;background:#fff;border-top:1px solid #dde5f3;padding:.85rem 1rem;display:flex;align-items:center;gap:1rem;flex-wrap:wrap;z-index:5;}
.pc-savebar__note{font-size:.78rem;color:#7b88a6;margin-right:auto;}
</style>
<script>
// Rouvrir la page sur la section qu'on vient d'enregistrer.
document.getElementById('pc-form')?.addEventListener('submit', () => {
    let best = '';
    document.querySelectorAll('.admin-panel[id^="s-"]').forEach(sec => {
        if (sec.getBoundingClientRect().top < 120) best = sec.id.replace(/^s-/, '');
    });
    document.getElementById('pc-anchor').value = best;
});
// Amener le champ ciblé sous les yeux quand on arrive depuis la recherche ou le site.
const focused = document.querySelector('.pc-field.is-focus');
if (focused) {
    focused.scrollIntoView({block:'center'});
    focused.querySelector('input,textarea')?.focus();
}
</script>
<?php require __DIR__ . '/partials/footer.php'; ?>
