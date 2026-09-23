<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/render.php';
require_admin();

/**
 * Zones d'intervention — l'écran unique.
 *
 * Tout ce qui concerne la couverture géographique se règle ici. La liste
 * alimente l'accueil, la page « Nos zones », le bas des pages Service et la
 * page Contact : il n'y a plus qu'un seul endroit à tenir à jour.
 *
 * Un seul formulaire porte la page. Chaque bouton d'action envoie son propre
 * couple nom/valeur (op = save | toggle:ID | up:ID | down:ID | delete:ID |
 * create), ce qui évite des formulaires imbriqués — interdits en HTML — et
 * permet d'enregistrer les saisies en cours avant d'appliquer l'action.
 */

/** Une zone de saisie multiligne devient la liste « a | b | c » stockée en base. */
function zx_lines_to_list(string $raw): string
{
    $parts = preg_split('/[\r\n]+/', $raw) ?: [];
    return zone_join_list($parts);
}

/** Et inversement, pour l'affichage dans le formulaire. */
function zx_list_to_lines(array $items): string
{
    return implode("\n", $items);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $op = (string)($_POST['op'] ?? 'save');
    [$verb, $argRaw] = array_pad(explode(':', $op, 2), 2, '');
    $arg = (int)$argRaw;

    // 1. Les textes de la section, communs à toutes les zones.
    $saved = get_json_setting('zones_page_settings', []);
    foreach (['eyebrow','title','title_hl','lead'] as $k) {
        if (isset($_POST['zp'][$k])) $saved[$k] = trim((string)$_POST['zp'][$k]);
    }
    unset($saved['regions']);   // la table fait foi désormais
    set_json_setting('zones_page_settings', $saved);

    foreach (['zp_regions_label','zp_regions_title','zp_regions_title_hl',
              'zones_meta_title','zones_meta_desc'] as $k) {
        if (isset($_POST[$k])) set_setting($k, trim((string)$_POST[$k]));
    }

    // 2. Les zones elles-mêmes : on enregistre toujours les saisies en cours,
    //    même quand le clic portait sur « activer » ou « monter ».
    $nb = 0;
    foreach ((array)($_POST['zone'] ?? []) as $zid => $row) {
        $zid = (int)$zid;
        if ($zid <= 0) continue;
        $name = trim((string)($row['name'] ?? ''));
        if ($name === '') continue;               // un nom vide effacerait la zone de l'affichage
        zone_save_presentation($zid, [
            'name'         => $name,
            'color'        => trim((string)($row['color'] ?? '')),
            'delay'        => trim((string)($row['delay'] ?? '')),
            'intro'        => trim((string)($row['intro'] ?? '')),
            'depts'        => zx_lines_to_list((string)($row['depts'] ?? '')),
            'cities'       => zx_lines_to_list((string)($row['cities'] ?? '')),
            'postal_codes' => trim((string)($row['postal_codes'] ?? '')),
            'status'       => (int)($row['status'] ?? 0),
            'sort_order'   => (int)($row['sort_order'] ?? 0),
        ]);
        $nb++;
    }
    intervention_zones_flush();

    // 3. L'action demandée.
    $msg = $nb > 0 ? $nb.' zone'.($nb > 1 ? 's' : '').' enregistrée'.($nb > 1 ? 's' : '').'.' : 'Enregistré.';
    if ($verb === 'toggle' && $arg > 0) {
        toggle_zone_status($arg);
        $msg = 'Zone mise à jour.';
    } elseif (($verb === 'up' || $verb === 'down') && $arg > 0) {
        $ordered = intervention_zones(false, true);
        $ids     = array_map(fn($z) => $z['id'], $ordered);
        $i       = array_search($arg, $ids, true);
        $j       = $verb === 'up' ? $i - 1 : $i + 1;
        if ($i !== false && $j >= 0 && $j < count($ids)) {
            [$ids[$i], $ids[$j]] = [$ids[$j], $ids[$i]];
            foreach ($ids as $rank => $zid) db_execute('UPDATE zones SET sort_order = ? WHERE id = ?', [$rank, $zid]);
        }
        $msg = 'Ordre modifié.';
    } elseif ($verb === 'delete' && $arg > 0) {
        $gone = get_zone_by_id($arg);
        delete_zone($arg);
        $msg = 'Zone « '.(string)($gone['name'] ?? '').' » supprimée.';
    } elseif ($verb === 'create') {
        $newName = trim((string)($_POST['new_zone_name'] ?? ''));
        if ($newName === '') {
            flash('error', 'Donnez un nom à la nouvelle zone.');
            redirect_to('admin/zones.php#ajouter');
        }
        $order = count(intervention_zones(false, true));
        create_zone([
            'slug'       => zone_unique_slug(zone_slugify($newName)),
            'name'       => $newName,
            'status'     => 1,
            'sort_order' => $order,
        ]);
        $msg = 'Zone « '.$newName.' » créée. Complétez ses villes ci-dessous.';
    }

    intervention_zones_flush();
    if (function_exists('admin_log')) admin_log('modification', 'Zones d\'intervention', $msg);
    flash('success', $msg);
    redirect_to('admin/zones.php');
}

$zones = intervention_zones(false, true);
$cfg   = zones_page_settings();
$total = count($zones);
$adminSection = 'zones';
require __DIR__ . '/partials/header.php';
?>
<div class="admin-page-toolbar">
  <div>
    <div class="admin-breadcrumb">Le site</div>
    <h1 class="admin-page-title">Zones d'intervention</h1>
    <p class="admin-page-subtitle">Une seule liste. Vous la modifiez ici, elle change partout sur le site.</p>
  </div>
  <div class="admin-toolbar-actions">
    <a class="admin-btn admin-btn--secondary" href="<?= e(route_url('zones')) ?>" target="_blank">Voir la page</a>
  </div>
</div>

<div class="zx-where">
  <span class="zx-where__label">Cette liste s'affiche sur</span>
  <a href="<?= e(route_url('')) ?>" target="_blank">l'accueil</a>
  <a href="<?= e(route_url('zones')) ?>" target="_blank">la page Nos zones</a>
  <a href="<?= e(route_url('electricite')) ?>" target="_blank">le bas des pages Service</a>
  <a href="<?= e(route_url('contact')) ?>" target="_blank">la page Contact</a>
</div>

<form method="post" class="admin-stack" id="zx-form">
<?= csrf_field() ?>

<section class="admin-panel">
  <div class="admin-panel__head">
    <h2>Les phrases autour de la liste</h2>
    <p>Le titre et l'accroche affichés au-dessus de vos zones.</p>
  </div>
  <div class="admin-panel__body">
    <div class="admin-form-grid admin-form-grid--2">
      <label class="admin-field"><span>Surtitre</span><input type="text" name="zp[eyebrow]" value="<?= e((string)($cfg['eyebrow'] ?? '')) ?>"></label>
      <label class="admin-field"><span>Titre</span><input type="text" name="zp[title]" value="<?= e((string)($cfg['title'] ?? '')) ?>"></label>
    </div>
    <div class="admin-form-grid admin-form-grid--2">
      <label class="admin-field"><span>Fin du titre, en couleur</span><input type="text" name="zp[title_hl]" value="<?= e((string)($cfg['title_hl'] ?? '')) ?>"></label>
      <label class="admin-field"><span>Petit titre juste avant les cartes</span><input type="text" name="zp_regions_label" value="<?= e(setting_plain('zp_regions_label') !== '' ? setting_plain('zp_regions_label') : 'Nos régions') ?>"></label>
    </div>
    <label class="admin-field"><span>Phrase d'accroche</span><textarea name="zp[lead]" rows="3"><?= e((string)($cfg['lead'] ?? '')) ?></textarea></label>
    <div class="admin-form-grid admin-form-grid--2">
      <label class="admin-field"><span>Titre au-dessus des cartes</span><input type="text" name="zp_regions_title" value="<?= e(setting_plain('zp_regions_title') !== '' ? setting_plain('zp_regions_title') : 'Nos') ?>"></label>
      <label class="admin-field"><span>Fin de ce titre, en couleur</span><input type="text" name="zp_regions_title_hl" value="<?= e(setting_plain('zp_regions_title_hl') !== '' ? setting_plain('zp_regions_title_hl') : 'zones couvertes') ?>"></label>
    </div>
  </div>
</section>

<section class="admin-panel">
  <div class="admin-panel__head">
    <h2>Vos zones <span class="zx-count"><?= (int)$total ?></span></h2>
    <p>Une carte par zone. Les zones inactives disparaissent du site sans être supprimées.</p>
  </div>
  <div class="admin-panel__body">

    <?php if (!$zones): ?>
      <p class="zx-empty">Aucune zone pour l'instant. Ajoutez la première ci-dessous.</p>
    <?php endif; ?>

    <?php foreach ($zones as $i => $z): ?>
    <article class="zx-zone<?= $z['status'] ? '' : ' is-off' ?>" id="zone-<?= (int)$z['id'] ?>">
      <input type="hidden" name="zone[<?= (int)$z['id'] ?>][sort_order]" value="<?= (int)$i ?>">
      <input type="hidden" name="zone[<?= (int)$z['id'] ?>][status]" value="<?= $z['status'] ? 1 : 0 ?>">

      <header class="zx-zone__head">
        <span class="zx-dot" style="background:<?= e($z['color']) ?>;"></span>
        <input class="zx-name" type="text" name="zone[<?= (int)$z['id'] ?>][name]" value="<?= e($z['name']) ?>" aria-label="Nom de la zone">
        <code class="zx-slug">/<?= e($z['slug']) ?>/</code>
        <span class="zx-spacer"></span>
        <button class="zx-pill<?= $z['status'] ? ' is-on' : '' ?>" type="submit" name="op" value="toggle:<?= (int)$z['id'] ?>">
          <?= $z['status'] ? 'Visible' : 'Masquée' ?>
        </button>
        <button class="zx-icon" type="submit" name="op" value="up:<?= (int)$z['id'] ?>" title="Monter" <?= $i === 0 ? 'disabled' : '' ?>>&uarr;</button>
        <button class="zx-icon" type="submit" name="op" value="down:<?= (int)$z['id'] ?>" title="Descendre" <?= $i === $total - 1 ? 'disabled' : '' ?>>&darr;</button>
      </header>

      <div class="zx-zone__body">
        <div class="admin-form-grid admin-form-grid--2">
          <label class="admin-field">
            <span>Phrase de présentation</span>
            <input type="text" name="zone[<?= (int)$z['id'] ?>][intro]" value="<?= e($z['intro']) ?>" placeholder="Paris et toute la région.">
          </label>
          <label class="admin-field">
            <span>Délai annoncé</span>
            <input type="text" name="zone[<?= (int)$z['id'] ?>][delay]" value="<?= e($z['delay']) ?>" placeholder="Moins de 2h en urgence">
          </label>
        </div>

        <div class="zx-cols">
          <label class="admin-field">
            <span>Villes <small><?= count($z['cities']) ?></small></span>
            <textarea name="zone[<?= (int)$z['id'] ?>][cities]" rows="7" placeholder="Une ville par ligne"><?= e(zx_list_to_lines($z['cities'])) ?></textarea>
          </label>
          <label class="admin-field">
            <span>Départements <small><?= count($z['depts']) ?></small></span>
            <textarea name="zone[<?= (int)$z['id'] ?>][depts]" rows="7" placeholder="Un département par ligne&#10;ex : Paris (75)"><?= e(zx_list_to_lines($z['depts'])) ?></textarea>
          </label>
          <div class="zx-side">
            <label class="admin-field">
              <span>Couleur</span>
              <input type="color" name="zone[<?= (int)$z['id'] ?>][color]" value="<?= e($z['color']) ?>">
            </label>
            <label class="admin-field">
              <span>Codes postaux</span>
              <input type="text" name="zone[<?= (int)$z['id'] ?>][postal_codes]" value="<?= e($z['postal_codes']) ?>" placeholder="75,92,93">
            </label>
          </div>
        </div>
      </div>

      <footer class="zx-zone__foot">
        <a href="<?= e(url_for($z['slug'].'/')) ?>" target="_blank">Voir cette zone sur le site</a>
        <a href="<?= e(url_for('admin/zone_edit.php?id='.(int)$z['id'])) ?>">Réglages avancés</a>
        <a href="<?= e(url_for('admin/index.php?admin_zone='.(int)$z['id'])) ?>">Modifier les textes de cette zone</a>
        <span class="zx-spacer"></span>
        <button class="zx-del" type="submit" name="op" value="delete:<?= (int)$z['id'] ?>"
                onclick="return confirm('Supprimer la zone « <?= e(str_replace(['"',"'"],'',$z['name'])) ?> » ? Ses textes personnalisés seront perdus. Pour la retirer du site sans rien perdre, utilisez plutôt « Visible ».');">Supprimer</button>
      </footer>
    </article>
    <?php endforeach; ?>

    <div class="zx-add" id="ajouter">
      <label class="admin-field">
        <span>Ajouter une zone</span>
        <input type="text" name="new_zone_name" placeholder="Nom de la zone — ex : Côte-d'Or">
      </label>
      <button class="admin-btn admin-btn--secondary" type="submit" name="op" value="create">Ajouter</button>
    </div>
  </div>
</section>

<section class="admin-panel">
  <div class="admin-panel__head">
    <h2>Référencement de la page</h2>
    <p>Ce que Google affiche pour la page « Nos zones ».</p>
  </div>
  <div class="admin-panel__body admin-form-grid admin-form-grid--2">
    <label class="admin-field"><span>Titre Google</span><input type="text" name="zones_meta_title" value="<?= e(setting_plain('zones_meta_title')) ?>" placeholder="Zones d'intervention | <?= e(company_name()) ?>"></label>
    <label class="admin-field"><span>Description Google</span><input type="text" name="zones_meta_desc" value="<?= e(setting_plain('zones_meta_desc')) ?>"></label>
  </div>
</section>

<details class="zx-more">
  <summary>Outils avancés</summary>
  <div class="zx-more__body">
    <a href="<?= e(url_for('admin/zone_vars.php')) ?>">Variables de lieu<small>Ce que {ville} ou {region} valent dans chaque zone</small></a>
    <a href="<?= e(url_for('admin/zones_diag.php')) ?>">Diagnostic<small>Vérifier ce qui diffère réellement d'une zone à l'autre</small></a>
    <a href="<?= e(url_for('admin/zone_convert.php')) ?>">Convertir en variables<small>Remplacer les noms de lieux écrits en dur par des variables</small></a>
    <a href="<?= e(url_for('admin/zone_content.php')) ?>">Contenus prêts à l'emploi<small>Appliquer des textes déjà rédigés à une zone</small></a>
  </div>
</details>

<div class="admin-savebar">
  <button class="admin-btn admin-btn--primary" type="submit" name="op" value="save">Enregistrer</button>
</div>
</form>

<style>
.zx-where{display:flex;flex-wrap:wrap;align-items:center;gap:.5rem;margin:-.4rem 0 1.4rem;}
.zx-where__label{font-size:.82rem;color:#7b8aa8;font-weight:700;}
.zx-where a{font-size:.82rem;font-weight:700;color:#2f66d2;text-decoration:none;background:#fff;
  border:1px solid #dde5f3;border-radius:20px;padding:.3rem .8rem;}
.zx-where a:hover{background:#eff4ff;}
.zx-count{display:inline-block;margin-left:.35rem;background:#eef3fc;color:#4b5b7d;border-radius:20px;
  padding:.05rem .55rem;font-size:.82rem;font-weight:700;vertical-align:middle;}
.zx-empty{margin:0;color:#7b8aa8;}
.zx-zone{border:1px solid #e3eaf6;border-radius:18px;background:#fff;overflow:hidden;}
.zx-zone.is-off{background:#fbfcfe;}
.zx-zone.is-off .zx-zone__body{opacity:.5;}
.zx-zone__head{display:flex;align-items:center;gap:.7rem;padding:.9rem 1.1rem;border-bottom:1px solid #eef2f8;
  background:#fcfdff;flex-wrap:wrap;}
.zx-dot{width:12px;height:12px;border-radius:50%;flex:0 0 auto;}
.zx-name{flex:0 1 auto;min-width:180px;font-size:1.08rem;font-weight:800;color:#13254c;border:1px solid transparent;
  background:transparent;border-radius:10px;padding:.35rem .5rem;font-family:inherit;}
.zx-name:hover{border-color:#e3eaf6;}
.zx-name:focus{outline:none;border-color:#2f66d2;background:#fff;}
.zx-slug{font-size:.78rem;color:#8494b4;background:#f2f6fc;border-radius:6px;padding:.15rem .45rem;}
.zx-spacer{flex:1 1 auto;}
.zx-pill{border:1px solid #dde5f3;background:#f4f6fa;color:#6b7a99;border-radius:20px;padding:.3rem .85rem;
  font-size:.78rem;font-weight:700;cursor:pointer;font-family:inherit;}
.zx-pill.is-on{background:#e9fff0;border-color:#bdecc8;color:#14653a;}
.zx-icon{width:34px;height:34px;border:1px solid #dde5f3;background:#fff;border-radius:10px;color:#4b5b7d;
  cursor:pointer;font-size:.95rem;line-height:1;}
.zx-icon:disabled{opacity:.3;cursor:default;}
.zx-zone__body{padding:1.1rem;display:grid;gap:1rem;}
.zx-cols{display:grid;grid-template-columns:1fr 1fr 210px;gap:1rem;}
.zx-side{display:grid;gap:1rem;align-content:start;}
.zx-cols .admin-field > span small{font-weight:600;color:#9aa8c4;margin-left:.3rem;}
.zx-cols textarea{min-height:0;font-size:.9rem;line-height:1.5;}
.zx-zone__foot{display:flex;flex-wrap:wrap;align-items:center;gap:1rem;padding:.75rem 1.1rem;
  border-top:1px solid #eef2f8;background:#fcfdff;}
.zx-zone__foot a{font-size:.82rem;font-weight:700;color:#2f66d2;text-decoration:none;}
.zx-zone__foot a:hover{text-decoration:underline;}
.zx-del{border:0;background:none;color:#b91c1c;font-size:.82rem;font-weight:700;cursor:pointer;font-family:inherit;padding:0;}
.zx-del:hover{text-decoration:underline;}
.zx-add{display:flex;align-items:flex-end;gap:.8rem;border-top:1px dashed #dde5f3;padding-top:1.1rem;}
.zx-add .admin-field{flex:1;}
.zx-more{background:#fff;border:1px solid #dde5f3;border-radius:22px;padding:.35rem .4rem;}
.zx-more > summary{cursor:pointer;padding:1rem 1.1rem;font-weight:800;color:#13254c;list-style:none;}
.zx-more > summary::-webkit-details-marker{display:none;}
.zx-more > summary::before{content:'›';display:inline-block;margin-right:.6rem;color:#8494b4;transition:transform .15s;}
.zx-more[open] > summary::before{transform:rotate(90deg);}
.zx-more__body{display:grid;grid-template-columns:repeat(auto-fit,minmax(230px,1fr));gap:.6rem;padding:0 1.1rem 1.1rem;}
.zx-more__body a{display:grid;gap:.2rem;padding:.8rem .9rem;border:1px solid #e3eaf6;border-radius:14px;
  text-decoration:none;color:#13254c;font-weight:700;font-size:.9rem;}
.zx-more__body a:hover{background:#f7faff;border-color:#c9d8f2;}
.zx-more__body small{font-weight:500;color:#7b8aa8;font-size:.78rem;line-height:1.4;}
@media(max-width:1100px){.zx-cols{grid-template-columns:1fr;}}
</style>

<?php require __DIR__ . '/partials/footer.php'; ?>
