<?php
/**
 * Suivi des demandes : d'où viennent les demandes (page, métier, secteur),
 * combien deviennent des interventions, appels et clics WhatsApp, visites.
 * Plus la liste des demandes non terminées (formulaire abandonné).
 */
$adminSection = 'leads';
require __DIR__ . '/partials/header.php';
require_once __DIR__ . '/../includes/stats.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $id = (int)($_POST['id'] ?? 0);
    partial_table();
    if (($_POST['act'] ?? '') === 'done' && $id > 0) db_execute('UPDATE quote_partials SET handled_at = NOW() WHERE id = ?', [$id]);
    if (($_POST['act'] ?? '') === 'delete' && $id > 0) db_execute('DELETE FROM quote_partials WHERE id = ?', [$id]);
    redirect_to('admin/leads.php#partiels');
}

$days = in_array((int)($_GET['j'] ?? 30), [7, 30, 90, 365], true) ? (int)($_GET['j'] ?? 30) : 30;
$since = date('Y-m-d 00:00:00', time() - ($days - 1) * 86400);
try {
    $quotes = db_fetch_all("SELECT q.id, q.created_at, q.request_type, q.source, q.service_type, q.postal_code, q.status,
                                   (SELECT COUNT(*) FROM interventions i WHERE i.quote_id = q.id) AS nb_iv
                            FROM quotes q WHERE q.created_at >= ? ORDER BY q.id", [$since]);
} catch (Throwable $e) {
    $quotes = db_fetch_all('SELECT id, created_at, request_type, source, service_type, postal_code, status, 0 AS nb_iv FROM quotes WHERE created_at >= ?', [$since]);
}

/** Libellé lisible de la provenance d'une demande. */
function leads_source_label(string $s): string
{
    $s = trim($s);
    if ($s === '' ) return 'Non précisée';
    if ($s === 'hero') return 'Accueil — encart du haut';
    if ($s === 'quote_page') return 'Page Devis';
    if ($s === 'rendez-vous') return 'Rendez-vous en ligne';
    if ($s === 'estimation') return 'Estimation de prix';
    if ($s === 'entretien') return 'Contrats d\'entretien';
    if (str_starts_with($s, 'service_')) return 'Page métier — '.(service_trade_defaults()[substr($s, 8)]['label'] ?? substr($s, 8));
    if (str_starts_with($s, 'panne_')) return 'Page panne — '.(panne_defaults()[substr($s, 6)]['h1'] ?? substr($s, 6));
    if (str_starts_with($s, 'landing_')) return 'Publicité Google (page ville)';
    return ucfirst(str_replace('_', ' ', $s));
}

$total = count($quotes);
$converted = 0; $byType = []; $bySource = []; $byService = []; $byDept = []; $byDay = [];
$add = static function (array &$arr, string $key, bool $won): void {
    $arr[$key] ??= [0, 0];
    $arr[$key][0]++;
    if ($won) $arr[$key][1]++;
};
foreach ($quotes as $q) {
    $won = (int)$q['nb_iv'] > 0 || in_array((string)$q['status'], ['planifié', 'en cours', 'terminé', 'facturé', 'payé'], true);
    if ($won) $converted++;
    $add($byType, in_array($q['request_type'], ['rappel', 'devis', 'rdv', 'entretien'], true) ? quote_type_info((string)$q['request_type'])[4] : 'Non précisé (ancienne demande)', $won);
    $add($bySource, leads_source_label((string)$q['source']), $won);
    $add($byService, trim((string)$q['service_type']) ?: 'Non précisé', $won);
    $add($byDept, preg_match('/^(\d{2})/', trim((string)$q['postal_code']), $m) ? $m[1] : '—', $won);
    $day = substr((string)$q['created_at'], 0, 10);
    $byDay[$day] = ($byDay[$day] ?? 0) + 1;
}
$calls = stats_event_total('appel', $days);
$wa    = stats_event_total('whatsapp', $days);
$views = stats_views_total($days);
$prevSince = date('Y-m-d 00:00:00', time() - (2 * $days - 1) * 86400);
try { $prev = (int)(db_fetch('SELECT COUNT(*) AS n FROM quotes WHERE created_at >= ? AND created_at < ?', [$prevSince, $since])['n'] ?? 0); } catch (Throwable $e) { $prev = 0; }

$table = static function (string $title, array $rows, string $col = 'Provenance'): void {
    uasort($rows, static fn(array $a, array $b): int => $b[0] <=> $a[0]);
    $max = max(array_merge([1], array_column($rows, 0)));
    echo '<section class="admin-panel"><div class="admin-panel__head"><h2>'.e($title).'</h2></div><div class="admin-panel__body admin-table-wrap"><table class="admin-table"><thead><tr><th>'.e($col).'</th><th>Demandes</th><th style="width:40%;"></th><th>Devenues interventions</th></tr></thead><tbody>';
    if (!$rows) echo '<tr><td colspan="4" style="color:#888;">Aucune demande sur la période.</td></tr>';
    foreach ($rows as $k => [$n, $won]) {
        echo '<tr><td>'.e((string)$k).'</td><td><strong>'.$n.'</strong></td><td><div style="background:#eef2fb;border-radius:4px;height:10px;"><div style="background:#F07B1D;height:10px;border-radius:4px;width:'.round($n / $max * 100).'%;"></div></div></td><td>'.$won.' ('.round($won / max(1, $n) * 100).' %)</td></tr>';
    }
    echo '</tbody></table></div></section>';
};
$kpi = static fn(string $label, string $value, string $sub = ''): string =>
    '<div style="background:#fff;border:1px solid #e3e8f2;border-radius:12px;padding:1rem 1.1rem;"><div style="font-size:.78rem;color:#6b7a99;text-transform:uppercase;letter-spacing:.05em;">'.e($label).'</div><div style="font-size:1.7rem;font-weight:800;color:#16243f;margin:.2rem 0;">'.e($value).'</div><div style="font-size:.8rem;color:#6b7a99;">'.$sub.'</div></div>';
?>
<div class="admin-page-toolbar">
  <div>
    <div class="admin-breadcrumb">Croissance</div>
    <h1 class="admin-page-title">Suivi des demandes</h1>
    <p class="admin-page-subtitle">D'où viennent vos clients, et combien deviennent des interventions. Pour savoir où mettre votre budget publicitaire.</p>
  </div>
  <div style="display:flex;gap:.4rem;flex-wrap:wrap;">
    <?php foreach ([7 => '7 jours', 30 => '30 jours', 90 => '3 mois', 365 => '12 mois'] as $j => $lab): ?>
    <a class="admin-btn <?= $j === $days ? 'admin-btn--primary' : 'admin-btn--secondary' ?>" href="<?= e(url_for('admin/leads.php?j='.$j)) ?>"><?= e($lab) ?></a>
    <?php endforeach; ?>
  </div>
</div>

<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:.75rem;margin-bottom:1.25rem;">
  <?= $kpi('Demandes reçues', (string)$total, $prev > 0 ? 'période précédente : '.$prev : 'formulaires du site') ?>
  <?= $kpi('Devenues interventions', $total ? round($converted / $total * 100).' %' : '—', $converted.' demande(s) planifiée(s) ou réalisée(s)') ?>
  <?= $kpi('Clics sur « Appeler »', (string)$calls, 'appels lancés depuis le site') ?>
  <?= $kpi('Clics WhatsApp', (string)$wa, 'conversations ouvertes') ?>
  <?= $kpi('Pages vues', (string)$views, $views ? 'contacts / pages vues : '.round(($total + $calls + $wa) / $views * 100, 1).' %' : 'mesure interne, sans cookie') ?>
</div>
<p style="font-size:.82rem;color:#6b7a99;margin:-.5rem 0 1.25rem;">« Devenue intervention » : une fiche d'intervention a été créée depuis la demande, ou la demande est passée au statut planifié, en cours, terminé, facturé ou payé. Les appels directs ne sont comptés qu'en nombre de clics.</p>

<?php
$table('Par provenance (page du site)', $bySource);
$table('Par type de demande', $byType, 'Type');
$table('Par métier', $byService, 'Métier');
$table('Par département (code postal)', $byDept, 'Département');
?>

<section class="admin-panel">
  <div class="admin-panel__head"><h2>Jour par jour</h2></div>
  <div class="admin-panel__body" style="display:flex;align-items:flex-end;gap:2px;height:120px;overflow-x:auto;">
    <?php $maxD = max(array_merge([1], $byDay)); for ($i = $days - 1; $i >= 0; $i--): $dd = date('Y-m-d', time() - $i * 86400); $n = $byDay[$dd] ?? 0; ?>
    <div title="<?= e(date('d/m', strtotime($dd)).' : '.$n.' demande(s)') ?>" style="flex:1 0 6px;min-width:6px;background:<?= $n ? '#F07B1D' : '#eef2fb' ?>;height:<?= max(4, round($n / $maxD * 100)) ?>%;border-radius:2px 2px 0 0;"></div>
    <?php endfor; ?>
  </div>
</section>

<section class="admin-panel" id="partiels">
  <div class="admin-panel__head"><h2>📝 Demandes non terminées</h2>
    <p><?= partial_enabled() ? 'Visiteurs qui ont saisi leur nom et leur téléphone sans envoyer le formulaire (gardés 30 jours).' : 'Désactivé. Activez-le dans <a href="'.e(url_for('admin/growth.php#partiel')).'">Conversion & avis</a> pour rappeler les visiteurs qui abandonnent le formulaire.' ?></p></div>
  <div class="admin-panel__body admin-table-wrap">
    <?php $parts = partial_list(); if (!$parts): ?><p style="color:#888;">Aucune pour le moment.</p><?php else: ?>
    <table class="admin-table"><thead><tr><th>Date</th><th>Nom</th><th>Téléphone</th><th>Code postal</th><th>Service</th><th>Page</th><th></th></tr></thead><tbody>
    <?php foreach ($parts as $p): ?>
      <tr style="<?= $p['handled_at'] ? 'opacity:.5;' : '' ?>">
        <td><?= e(date('d/m H:i', strtotime((string)$p['created_at']))) ?></td><td><?= e((string)$p['full_name']) ?></td>
        <td><a href="tel:<?= e((string)preg_replace('/[^0-9+]/', '', (string)$p['phone'])) ?>" style="font-weight:700;color:#c2410c;"><?= e((string)$p['phone']) ?></a></td>
        <td><?= e((string)$p['postal_code']) ?></td><td><?= e((string)$p['service_type']) ?></td><td><?= e(leads_source_label((string)$p['source'])) ?></td>
        <td style="white-space:nowrap;"><form method="post" style="display:inline;"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
          <?php if (!$p['handled_at']): ?><button class="admin-btn admin-btn--secondary" name="act" value="done" type="submit">✓ Rappelé</button><?php endif; ?>
          <button class="admin-btn admin-btn--secondary" name="act" value="delete" type="submit" onclick="return confirm('Supprimer ?');">🗑</button></form></td>
      </tr>
    <?php endforeach; ?>
    </tbody></table>
    <?php endif; ?>
  </div>
</section>
<?php require __DIR__ . '/partials/footer.php'; ?>
