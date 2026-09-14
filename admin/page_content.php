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
    foreach (admin_catalog_fields($pageId) as $key => $f) {
        if (!array_key_exists($key, $_POST)) continue;
        if (admin_field_is_list($f)) {
            $rows = is_array($_POST[$key]) ? $_POST[$key] : [];
            if (admin_list_save($f, $rows)) $n++;
        } elseif (admin_field_save($f, (string)$_POST[$key])) {
            $n++;
        }
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
    <a class="admin-btn admin-btn--primary" href="<?= e(admin_visual_url($pageId)) ?>">✏️ Modifier sur la page</a>
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
      <?php if (!empty($s['note'])): ?>
        <p class="pc-note"><?= e($s['note']) ?></p>
      <?php endif; ?>
      <?php if (!empty($s['link'])): ?>
        <p style="margin:0 0 1rem;"><a class="admin-btn admin-btn--secondary" href="<?= e(url_for($s['link'][0])) ?>"><?= e($s['link'][1]) ?> →</a></p>
      <?php endif; ?>
      <div class="admin-form-grid admin-form-grid--2">
        <?php foreach ($s['fields'] as $f):
            if (admin_field_is_list($f)) { include __DIR__.'/partials/field_list.php'; continue; }
            $key     = $f['key'];
            $raw     = admin_field_raw($f);
            $shown   = admin_field_shown($f);   // ce que le visiteur voit aujourd'hui
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
.pc-note{background:#fffaf4;border-left:3px solid #F07B1D;border-radius:8px;padding:.6rem .8rem;margin:0 0 1rem;font-size:.82rem;color:#7a5a35;line-height:1.5;}
.fl__title{display:flex;align-items:center;gap:.5rem;flex-wrap:wrap;}
.fl__count{font-style:normal;font-size:.72rem;font-weight:700;color:#8494b4;}
.fl__head{display:flex;gap:.4rem;align-items:center;margin:.5rem 0 .25rem;padding:0 .1rem;}
.fl__head .fl__col{flex:1;font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#8494b4;}
.fl__rows{display:flex;flex-direction:column;gap:.4rem;}
.fl__row{display:flex;gap:.4rem;align-items:flex-start;background:#f7faff;border:1px solid #e4ebf7;border-radius:10px;padding:.4rem;}
.fl__row.is-drag{opacity:.4;}
.fl__row.is-over{border-color:#2f66d2;box-shadow:0 0 0 2px rgba(47,102,210,.18);}
.fl__row input,.fl__row textarea{flex:1;min-width:0;padding:.45rem .6rem;border:1px solid #dde5f3;border-radius:8px;font-size:.88rem;font-family:inherit;background:#fff;}
.fl__row input:focus,.fl__row textarea:focus{outline:2px solid #2f66d2;outline-offset:1px;}
.fl__grip{flex:0 0 22px;text-align:center;color:#a8b4cc;cursor:grab;user-select:none;padding-top:.45rem;font-size:.9rem;}
.fl__del{flex:0 0 30px;border:none;background:none;color:#c0392b;cursor:pointer;font-size:.95rem;border-radius:8px;padding:.35rem 0;}
.fl__del:hover{background:#fdecea;}
.fl__actions{display:flex;align-items:center;gap:.8rem;margin-top:.55rem;flex-wrap:wrap;}
.fl__add{border:1px dashed #b9c7e2;background:#fff;color:#2f66d2;border-radius:10px;padding:.42rem .85rem;font-size:.84rem;font-weight:700;cursor:pointer;}
.fl__add:hover{background:#eef4ff;}
.fl__actions small{color:#8494b4;font-size:.75rem;}
@media(max-width:700px){.fl__head{display:none;}.fl__row{flex-wrap:wrap;}.fl__row input,.fl__row textarea{flex:1 1 100%;}}
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
// Listes : ajout, suppression et réordonnancement.
document.querySelectorAll('.fl').forEach(function(box){
    const rows = box.querySelector('.fl__rows');
    const tpl  = box.querySelector('.fl__tpl');
    const key  = box.dataset.key;

    // Les noms de champs doivent rester numérotés dans l'ordre affiché.
    function renumber(){
        rows.querySelectorAll('.fl__row').forEach(function(row, i){
            row.querySelectorAll('input,textarea').forEach(function(el){
                el.name = el.name.replace(/\[(?:\d+|__I__)\]/, '[' + i + ']');
            });
        });
        const c = rows.querySelectorAll('.fl__row').length;
        const lbl = box.querySelector('.fl__count');
        if (lbl) lbl.textContent = c + (c > 1 ? ' éléments' : ' élément');
    }

    box.querySelector('.fl__add')?.addEventListener('click', function(){
        rows.insertAdjacentHTML('beforeend', tpl.innerHTML);
        renumber();
        rows.lastElementChild.querySelector('input,textarea')?.focus();
    });

    box.addEventListener('click', function(ev){
        const del = ev.target.closest('.fl__del');
        if (!del || !rows.contains(del)) return;
        del.closest('.fl__row').remove();
        renumber();
    });

    // Glisser-déposer par la poignée.
    let dragged = null;
    rows.addEventListener('pointerdown', function(ev){
        const grip = ev.target.closest('.fl__grip');
        if (!grip) return;
        const row = grip.closest('.fl__row');
        row.draggable = true;
        dragged = row;
    });
    rows.addEventListener('dragstart', function(ev){
        if (!dragged) { ev.preventDefault(); return; }
        dragged.classList.add('is-drag');
        ev.dataTransfer.effectAllowed = 'move';
    });
    rows.addEventListener('dragover', function(ev){
        if (!dragged) return;
        ev.preventDefault();
        const over = ev.target.closest('.fl__row');
        if (!over || over === dragged) return;
        rows.querySelectorAll('.is-over').forEach(function(r){ r.classList.remove('is-over'); });
        over.classList.add('is-over');
        const after = (ev.clientY - over.getBoundingClientRect().top) > over.offsetHeight / 2;
        rows.insertBefore(dragged, after ? over.nextSibling : over);
    });
    rows.addEventListener('dragend', function(){
        rows.querySelectorAll('.is-over').forEach(function(r){ r.classList.remove('is-over'); });
        if (dragged) { dragged.classList.remove('is-drag'); dragged.draggable = false; dragged = null; renumber(); }
    });
});

// Amener le champ ciblé sous les yeux quand on arrive depuis la recherche ou le site.
const focused = document.querySelector('.pc-field.is-focus');
if (focused) {
    focused.scrollIntoView({block:'center'});
    focused.querySelector('input,textarea')?.focus();
}
</script>
<?php require __DIR__ . '/partials/footer.php'; ?>
