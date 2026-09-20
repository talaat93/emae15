<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/render.php';
require_once __DIR__ . '/../includes/stats.php';
require_admin();

$J = 30;   // période de référence, en jours

/** Un chiffre sur la période, et son évolution face aux 30 jours précédents. */
function tdb_compte(string $sql, array $p = []): int
{
    try { return (int)(db_fetch($sql, $p)['n'] ?? 0); }
    catch (Throwable $e) { return 0; }
}

$demandesPeriode = tdb_compte('SELECT COUNT(*) AS n FROM quotes WHERE created_at > (NOW() - INTERVAL ? DAY)', [$J]);
$demandesAvant   = tdb_compte('SELECT COUNT(*) AS n FROM quotes WHERE created_at > (NOW() - INTERVAL ? DAY) AND created_at <= (NOW() - INTERVAL ? DAY)', [$J * 2, $J]);
$nouvelles       = tdb_compte("SELECT COUNT(*) AS n FROM quotes WHERE status = 'nouveau'");
$urgentes        = tdb_compte("SELECT COUNT(*) AS n FROM quotes WHERE status = 'nouveau' AND urgency <> 'Normale'");

$vues      = stats_views_total($J);
$vuesAvant = stats_views_total($J, $J);
$appels      = stats_event_total('appel', $J);
$appelsAvant = stats_event_total('appel', $J, $J);

$tauxDemandes = $vues > 0 ? round(($demandesPeriode / $vues) * 100, 1) : null;
$tauxAppels   = $vues > 0 ? round(($appels / $vues) * 100, 1) : null;

try { $dernieres = db_fetch_all('SELECT * FROM quotes ORDER BY created_at DESC LIMIT 8'); }
catch (Throwable $e) { $dernieres = []; }

try {
    $parService = db_fetch_all(
        'SELECT COALESCE(NULLIF(service_type,\'\'),\'Non précisé\') AS s, COUNT(*) AS n FROM quotes
         WHERE created_at > (NOW() - INTERVAL ? DAY) GROUP BY s ORDER BY n DESC LIMIT 6', [$J]);
} catch (Throwable $e) { $parService = []; }

$topPages   = stats_top_pages($J, 8);
$parZone    = stats_by_zone($J);
$journal    = admin_is_super() ? admin_activity_recent(8) : [];
$aucuneData = ($vues === 0 && $appels === 0);

$adminSection = 'dashboard';
require_once __DIR__ . '/partials/header.php';

function tdb_evo(?int $evo): string
{
    if ($evo === null) return '<span class="tdb-evo tdb-evo--rien">pas de comparaison</span>';
    $cls = $evo > 0 ? 'up' : ($evo < 0 ? 'down' : 'flat');
    return '<span class="tdb-evo tdb-evo--'.$cls.'">'.e(stats_evolution_label($evo)).'</span>';
}
?>
<div class="admin-page-toolbar">
  <div>
    <div class="admin-breadcrumb">Vue d'ensemble</div>
    <h1 class="admin-page-title">Bonjour <?= e(current_admin()['name'] ?? '') ?></h1>
    <p class="admin-page-subtitle">Activité des <?= $J ?> derniers jours, comparée aux <?= $J ?> jours précédents.</p>
  </div>
  <div class="admin-toolbar-actions">
    <a class="admin-btn admin-btn--secondary" href="<?= e(url_for('admin/quotes.php')) ?>">Toutes les demandes</a>
    <a class="admin-btn admin-btn--primary" href="<?= e(route_url('')) ?>" target="_blank">Voir le site</a>
  </div>
</div>

<?php if ($nouvelles > 0): ?>
<div class="tdb-alerte">
  <strong><?= $nouvelles ?> demande<?= $nouvelles > 1 ? 's' : '' ?> en attente</strong>
  <?php if ($urgentes > 0): ?>
    <span class="tdb-urgent">dont <?= $urgentes ?> urgente<?= $urgentes > 1 ? 's' : '' ?></span>
  <?php endif; ?>
  <a class="admin-btn admin-btn--primary" href="<?= e(url_for('admin/quotes.php')) ?>">Traiter maintenant</a>
</div>
<?php endif; ?>

<div class="tdb-chiffres">
  <article class="tdb-carte">
    <span class="tdb-label">Demandes reçues</span>
    <strong class="tdb-nombre"><?= $demandesPeriode ?></strong>
    <?= tdb_evo(stats_evolution($demandesPeriode, $demandesAvant)) ?>
  </article>
  <article class="tdb-carte">
    <span class="tdb-label">Visiteurs (pages vues)</span>
    <strong class="tdb-nombre"><?= number_format($vues, 0, ',', ' ') ?></strong>
    <?= tdb_evo(stats_evolution($vues, $vuesAvant)) ?>
  </article>
  <article class="tdb-carte">
    <span class="tdb-label">Clics sur « Appeler »</span>
    <strong class="tdb-nombre"><?= number_format($appels, 0, ',', ' ') ?></strong>
    <?= tdb_evo(stats_evolution($appels, $appelsAvant)) ?>
  </article>
  <article class="tdb-carte">
    <span class="tdb-label">Visiteurs qui contactent</span>
    <strong class="tdb-nombre"><?= $tauxDemandes === null ? '—' : e((string)$tauxDemandes).' %' ?></strong>
    <span class="tdb-evo tdb-evo--rien"><?= $tauxAppels === null ? '' : e((string)$tauxAppels).' % appellent' ?></span>
  </article>
</div>

<?php if ($aucuneData): ?>
<div class="tdb-info">
  Les compteurs de visites démarrent aujourd'hui : ils se rempliront au fil des prochaines visites.
  Vos propres passages ne sont pas comptés, ni ceux des robots.
</div>
<?php endif; ?>

<div class="tdb-colonnes">
  <section class="admin-panel">
    <div class="admin-panel__head"><h2>Dernières demandes</h2></div>
    <div class="admin-panel__body admin-table-wrap">
      <?php if (!$dernieres): ?>
        <p style="color:#7b8aa8;">Aucune demande pour le moment.</p>
      <?php else: ?>
      <table class="admin-table">
        <thead><tr><th>Reçue</th><th>Client</th><th>Besoin</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($dernieres as $q): ?>
          <tr>
            <td style="font-size:.8rem;white-space:nowrap;color:#7b8aa8;"><?= e(date('d/m H:i', strtotime((string)$q['created_at']))) ?></td>
            <td style="font-size:.86rem;">
              <strong><?= e($q['full_name']) ?></strong><br>
              <small style="color:#7b8aa8;"><?= e($q['phone']) ?><?= trim((string)($q['city'] ?? '')) !== '' ? ' — '.e($q['city']) : '' ?></small>
            </td>
            <td style="font-size:.84rem;color:#5b6b92;">
              <?= e((string)($q['service_type'] ?? '—')) ?>
              <?php if (($q['urgency'] ?? 'Normale') !== 'Normale'): ?>
                <br><small style="color:#c2410c;font-weight:700;"><?= e($q['urgency']) ?></small>
              <?php endif; ?>
            </td>
            <td style="text-align:right;">
              <a class="admin-btn admin-btn--secondary" style="min-height:32px;padding:0 .65rem;font-size:.78rem;"
                 href="<?= e(url_for('admin/dossier.php?id='.(int)$q['id'])) ?>">Ouvrir</a>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      <?php endif; ?>
    </div>
  </section>

  <div class="tdb-pile">
    <section class="admin-panel">
      <div class="admin-panel__head"><h2>Pages les plus vues</h2></div>
      <div class="admin-panel__body">
        <?php if (!$topPages): ?><p style="color:#7b8aa8;font-size:.88rem;">Pas encore de données.</p><?php else: ?>
          <?php $maxP = max(array_map(fn($p) => (int)$p['n'], $topPages)); foreach ($topPages as $p): ?>
            <div class="tdb-barre">
              <span class="tdb-barre__nom"><?= e($p['page_key']) ?></span>
              <span class="tdb-barre__piste"><i style="width:<?= max(3, (int)round((int)$p['n'] / $maxP * 100)) ?>%"></i></span>
              <span class="tdb-barre__val"><?= number_format((int)$p['n'], 0, ',', ' ') ?></span>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </section>

    <section class="admin-panel">
      <div class="admin-panel__head"><h2>Trafic par zone</h2></div>
      <div class="admin-panel__body">
        <?php if (!$parZone): ?><p style="color:#7b8aa8;font-size:.88rem;">Pas encore de données.</p><?php else: ?>
          <?php $maxZ = max(array_map(fn($z) => (int)$z['n'], $parZone)); foreach ($parZone as $z):
              $nom = trim((string)$z['zone_slug']) === '' ? '🌐 Site global' : '📍 '.((get_zone_by_slug((string)$z['zone_slug'])['name'] ?? $z['zone_slug'])); ?>
            <div class="tdb-barre">
              <span class="tdb-barre__nom"><?= e($nom) ?></span>
              <span class="tdb-barre__piste"><i style="width:<?= max(3, (int)round((int)$z['n'] / $maxZ * 100)) ?>%"></i></span>
              <span class="tdb-barre__val"><?= number_format((int)$z['n'], 0, ',', ' ') ?></span>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </section>

    <?php if ($parService): ?>
    <section class="admin-panel">
      <div class="admin-panel__head"><h2>Demandes par métier</h2></div>
      <div class="admin-panel__body">
        <?php $maxS = max(array_map(fn($s) => (int)$s['n'], $parService)); foreach ($parService as $s): ?>
          <div class="tdb-barre">
            <span class="tdb-barre__nom"><?= e($s['s']) ?></span>
            <span class="tdb-barre__piste"><i style="width:<?= max(3, (int)round((int)$s['n'] / $maxS * 100)) ?>%"></i></span>
            <span class="tdb-barre__val"><?= (int)$s['n'] ?></span>
          </div>
        <?php endforeach; ?>
      </div>
    </section>
    <?php endif; ?>

    <?php if (admin_is_super() && $journal): ?>
    <section class="admin-panel">
      <div class="admin-panel__head">
        <h2>Dernières actions</h2>
        <p><a href="<?= e(url_for('admin/activity.php')) ?>">Voir tout le journal →</a></p>
      </div>
      <div class="admin-panel__body">
        <?php foreach ($journal as $l): ?>
          <div class="tdb-ligne">
            <strong><?= e($l['admin_name']) ?></strong>
            <?= e($l['action']) ?>
            <?php if (trim((string)($l['resource'] ?? '')) !== ''): ?><em><?= e($l['resource']) ?></em><?php endif; ?>
            <span><?= e(date('d/m H:i', strtotime((string)$l['created_at']))) ?></span>
          </div>
        <?php endforeach; ?>
      </div>
    </section>
    <?php endif; ?>
  </div>
</div>

<style>
.tdb-alerte{display:flex;align-items:center;gap:.9rem;flex-wrap:wrap;background:#fffaf4;border:1px solid #f5d5b0;
  border-left:5px solid #F07B1D;border-radius:14px;padding:.85rem 1.1rem;margin-bottom:1.3rem;color:#7a4a12;}
.tdb-urgent{background:#fee2e2;color:#991b1b;border-radius:20px;padding:.15rem .6rem;font-size:.8rem;font-weight:700;}
.tdb-alerte .admin-btn{margin-left:auto;}
.tdb-info{background:#f0f4ff;border:1px solid #dde5f3;border-radius:12px;padding:.8rem 1rem;margin-bottom:1.3rem;
  font-size:.86rem;color:#4b5b7d;}
.tdb-chiffres{display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:1rem;margin-bottom:1.5rem;}
.tdb-carte{background:#fff;border:1px solid #dde5f3;border-radius:16px;padding:1rem 1.1rem;display:flex;flex-direction:column;gap:.25rem;}
.tdb-label{font-size:.75rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#8494b4;}
.tdb-nombre{font-size:2rem;font-weight:800;color:#1b2d6b;line-height:1.1;}
.tdb-evo{font-size:.78rem;font-weight:700;}
.tdb-evo--up{color:#15803d;} .tdb-evo--down{color:#b91c1c;} .tdb-evo--flat{color:#8494b4;}
.tdb-evo--rien{color:#a3aec4;font-weight:400;}
.tdb-colonnes{display:grid;grid-template-columns:minmax(0,1.3fr) minmax(0,1fr);gap:1.25rem;align-items:start;}
.tdb-pile{display:flex;flex-direction:column;gap:1.25rem;}
.tdb-barre{display:flex;align-items:center;gap:.6rem;padding:.3rem 0;font-size:.84rem;}
.tdb-barre__nom{flex:0 0 38%;color:#3b4a6b;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
.tdb-barre__piste{flex:1;height:8px;background:#eef2fb;border-radius:20px;overflow:hidden;}
.tdb-barre__piste i{display:block;height:100%;background:linear-gradient(90deg,#2f66d2,#5b8ef0);border-radius:20px;}
.tdb-barre__val{flex:0 0 auto;font-weight:700;color:#1b2d6b;font-variant-numeric:tabular-nums;}
.tdb-ligne{display:flex;gap:.45rem;flex-wrap:wrap;align-items:baseline;padding:.35rem 0;font-size:.84rem;color:#5b6b92;
  border-bottom:1px solid #f1f5fb;}
.tdb-ligne em{font-style:normal;color:#1b2d6b;}
.tdb-ligne span{margin-left:auto;color:#a3aec4;font-size:.78rem;white-space:nowrap;}
@media(max-width:900px){.tdb-colonnes{grid-template-columns:1fr;}}
</style>
<?php require __DIR__ . '/partials/footer.php'; ?>
