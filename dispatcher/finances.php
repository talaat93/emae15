<?php
declare(strict_types=1);
/**
 * Tableau de bord financier : chiffres clés, retards, relances à faire, débiteurs, tendances.
 * Réservé aux comptes dispatcher ayant l'« Accès aux finances » (Administration → Dispatchers).
 * Lecture du cache local des factures (voir includes/finance.php) ; aucune relance n'est
 * envoyée d'ici : le bouton ouvre la facture, où le dispatcher relit puis envoie.
 */
$pageTitle   = 'Finances';
$dispSection = 'finances';
require __DIR__.'/partials/header.php';
require_finance_access($disp);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    if (($_POST['action'] ?? '') === 'sync') {
        @set_time_limit(600);
        $r = pennylane_sync_run('manuel');
        flash($r['ok'] ? 'success' : 'error', $r['message']);
    }
    redirect_to('dispatcher/finances.php');
}

$today    = date('Y-m-d');
$invoices = finance_load();
$k        = finance_kpis($invoices, $today);
$aging    = finance_aging($invoices, $today);
$todo     = finance_reminders_due($invoices, finance_reminders_map(), $today);
$debtors  = finance_top_debtors($invoices, $today, 10);
$monthly  = finance_monthly($invoices, $today, 12);
$delay    = finance_payment_delay($invoices, $today);
$byCat    = finance_breakdown($invoices, 'iv_category', $today);
$byTech   = finance_breakdown($invoices, 'tech_name', $today);
$lastSync = json_decode(integration_setting('pennylane_last_sync', ''), true);
$moisFr   = ['01' => 'janv.', '02' => 'févr.', '03' => 'mars', '04' => 'avr.', '05' => 'mai', '06' => 'juin', '07' => 'juil.', '08' => 'août', '09' => 'sept.', '10' => 'oct.', '11' => 'nov.', '12' => 'déc.'];
$moisLong = ['01' => 'janvier', '02' => 'février', '03' => 'mars', '04' => 'avril', '05' => 'mai', '06' => 'juin', '07' => 'juillet', '08' => 'août', '09' => 'septembre', '10' => 'octobre', '11' => 'novembre', '12' => 'décembre'];
$csrf = csrf_token();
?>
<div class="d-topbar">
  <div>
    <button class="d-menu-toggle" id="d-menu-toggle" aria-label="Menu">☰</button>
    <div>
      <div class="d-topbar-title">Finances</div>
      <div class="d-topbar-sub">
        <?= $lastSync ? 'Données Pennylane du '.e(date('d/m/Y à H:i', strtotime((string)$lastSync['at']))) : 'Aucune synchronisation Pennylane pour l\'instant' ?>
      </div>
    </div>
  </div>
  <div class="d-topbar-actions">
    <form method="post" onsubmit="this.querySelector('button').textContent='Synchronisation…';">
      <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="sync">
      <button type="submit" class="d-btn d-btn--sm">Synchroniser maintenant</button>
    </form>
  </div>
</div>

<div class="d-content fin">
  <?php if (pennylane_simulated()): ?>
    <div class="d-flash" style="background:#eff6ff;border:1px solid #bfdbfe;color:#1e3a8a;">Mode SIMULATION : les chiffres reflètent les factures déjà présentes dans le site, sans échange réel avec Pennylane.</div>
  <?php endif; ?>

  <!-- Chiffres clés -->
  <div class="fin-kpis">
    <a class="fin-kpi" href="<?= e(url_for('dispatcher/factures.php?from='.date('Y-m-01').'&to='.$today)) ?>">
      <div class="fin-kpi-l">Facturé en <?= e($moisLong[date('m')]) ?></div>
      <div class="fin-kpi-v"><?= e(money_fr($k['billed_month'])) ?></div>
      <div class="fin-kpi-s"><?= (int)$k['billed_month_count'] ?> facture<?= $k['billed_month_count'] > 1 ? 's' : '' ?> TTC</div>
    </a>
    <div class="fin-kpi">
      <div class="fin-kpi-l">Encaissé en <?= e($moisLong[date('m')]) ?></div>
      <div class="fin-kpi-v"><?= e(money_fr($k['paid_month'])) ?></div>
      <div class="fin-kpi-s">factures soldées ce mois</div>
    </div>
    <a class="fin-kpi" href="<?= e(url_for('dispatcher/factures.php?status=impayees')) ?>">
      <div class="fin-kpi-l">Reste dû</div>
      <div class="fin-kpi-v"><?= e(money_fr($k['due_total'])) ?></div>
      <div class="fin-kpi-s">toutes factures émises non soldées</div>
    </a>
    <a class="fin-kpi <?= $k['late_total'] > 0 ? 'is-alert' : '' ?>" href="<?= e(url_for('dispatcher/factures.php?status=retard')) ?>">
      <div class="fin-kpi-l">En retard</div>
      <div class="fin-kpi-v"><?= e(money_fr($k['late_total'])) ?></div>
      <div class="fin-kpi-s"><?= (int)$k['late_count'] ?> facture<?= $k['late_count'] > 1 ? 's' : '' ?> à échéance dépassée</div>
    </a>
  </div>
  <div class="fin-sub">
    <?php if ($k['to_validate'] > 0): ?><a href="<?= e(url_for('dispatcher/factures.php?status=brouillon')) ?>"><?= (int)$k['to_validate'] ?> facture<?= $k['to_validate'] > 1 ? 's' : '' ?> à valider</a> · <?php endif; ?>
    Délai moyen de paiement : <b><?= $delay ? (int)$delay['average'].' jours' : '—' ?></b><?= $delay ? ' <span>(sur '.(int)$delay['count'].' facture'.($delay['count'] > 1 ? 's' : '').' payée'.($delay['count'] > 1 ? 's' : '').' en 12 mois)</span>' : ' <span>(aucune facture payée mesurable)</span>' ?>
  </div>

  <div class="fin-grid">
    <!-- Retards par ancienneté -->
    <section class="d-card">
      <div class="d-card-head"><div class="d-card-title">Reste dû par ancienneté</div><span class="fin-muted">TTC</span></div>
      <div class="d-card-body">
        <?php $maxAge = max([1.0, ...array_values(array_map(static fn($b) => $b['amount'], $aging))]); ?>
        <?php foreach ($aging as $key => $b): ?>
          <div class="fin-row">
            <div class="fin-row-l"><?= e($b['label']) ?><span><?= (int)$b['count'] ?> fact.</span></div>
            <div class="fin-row-bar"><i class="<?= $key === '60_plus' && $b['amount'] > 0 ? 'is-old' : '' ?>" style="width:<?= round($b['amount'] / $maxAge * 100, 1) ?>%"></i></div>
            <div class="fin-row-v"><?= e(money_fr($b['amount'])) ?></div>
          </div>
        <?php endforeach; ?>
        <p class="fin-note">Plus une facture est ancienne, plus le risque de ne jamais être payé augmente : traitez d'abord la ligne du bas.</p>
      </div>
    </section>

    <!-- Relances à faire -->
    <section class="d-card">
      <div class="d-card-head"><div class="d-card-title">Relances à faire (<?= count($todo) ?>)</div><span class="fin-muted">sans relance depuis 7 jours</span></div>
      <?php if (!$todo): ?>
        <div class="d-card-body fin-muted">Aucune relance à faire : toutes les factures en retard ont été relancées il y a moins de 7 jours.</div>
      <?php else: ?>
      <div style="overflow-x:auto;">
        <table class="d-table fin-table">
          <thead><tr><th>Client</th><th>Facture</th><th class="r">Retard</th><th class="r">Reste dû</th><th>Relance</th><th></th></tr></thead>
          <tbody>
          <?php foreach (array_slice($todo, 0, 15) as $t): ?>
            <tr>
              <td><?= e(trim(($t['lastname'] ?? '').' '.($t['firstname'] ?? '')) ?: '—') ?></td>
              <td class="nowrap"><?= e((string)($t['number'] ?: '—')) ?></td>
              <td class="r nowrap"><?= (int)$t['days_late'] ?> j</td>
              <td class="r nowrap"><?= e(money_fr((float)$t['due'])) ?></td>
              <td class="nowrap"><?= ['', 'Rappel (n° 1)', 'Relance n° 2', 'Dernière (n° 3)'][$t['suggested_level']] ?><?= $t['last_reminder'] ? '<div class="fin-muted" style="font-size:.74rem;">dernière le '.e(date('d/m', strtotime((string)$t['last_reminder']))).'</div>' : '' ?></td>
              <td class="r"><a class="d-btn d-btn--sm d-btn--primary" href="<?= e(url_for('dispatcher/factures.php?id='.(int)$t['id'].'#relance')) ?>">Préparer</a></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php if (count($todo) > 15): ?><div class="d-card-body fin-muted" style="padding-top:.5rem;">… et <?= count($todo) - 15 ?> autre(s) : voir Factures → En retard.</div><?php endif; ?>
      <?php endif; ?>
    </section>
  </div>

  <!-- Évolution sur 12 mois -->
  <?php $maxM = max([1.0, ...array_map(static fn($m) => max($m['billed'], $m['paid']), $monthly)]); $step = fin_nice_step($maxM); $top = $step * (int)ceil($maxM / $step); ?>
  <section class="d-card fin-chart-card">
    <div class="d-card-head">
      <div class="d-card-title">Facturé et encaissé sur 12 mois</div>
      <div class="fin-legend"><span><i style="background:var(--fin-s1)"></i>Facturé</span><span><i style="background:var(--fin-s2)"></i>Encaissé</span></div>
    </div>
    <div class="d-card-body">
      <div class="fin-chart" role="img" aria-label="Facturé et encaissé par mois, TTC, sur les 12 derniers mois. Détail chiffré dans le tableau sous le graphique.">
        <div class="fin-gridlines" aria-hidden="true">
          <?php for ($v = $top; $v >= 0; $v -= $step): ?><div style="bottom:<?= round($v / $top * 100, 2) ?>%"><span><?= e(number_format($v, 0, ',', "\u{202F}")) ?> €</span></div><?php endfor; ?>
        </div>
        <div class="fin-cols">
          <?php foreach ($monthly as $m): [$y, $mm] = explode('-', $m['month']); ?>
            <div class="fin-col" tabindex="0" data-tip="<?= e(ucfirst($moisLong[$mm]).' '.$y.' — facturé '.money_fr($m['billed']).' · encaissé '.money_fr($m['paid'])) ?>">
              <div class="fin-bars">
                <i style="height:<?= round($m['billed'] / $top * 100, 2) ?>%;background:var(--fin-s1)"></i>
                <i style="height:<?= round($m['paid'] / $top * 100, 2) ?>%;background:var(--fin-s2)"></i>
              </div>
              <div class="fin-xlabel"><?= e($moisFr[$mm]) ?></div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
      <details class="fin-details">
        <summary>Voir les chiffres en tableau</summary>
        <table class="d-table fin-table">
          <thead><tr><th>Mois</th><th class="r">Facturé TTC</th><th class="r">Encaissé TTC</th></tr></thead>
          <tbody><?php foreach (array_reverse($monthly) as $m): [$y, $mm] = explode('-', $m['month']); ?>
            <tr><td><?= e(ucfirst($moisLong[$mm]).' '.$y) ?></td><td class="r"><?= e(money_fr($m['billed'])) ?></td><td class="r"><?= e(money_fr($m['paid'])) ?></td></tr>
          <?php endforeach; ?></tbody>
        </table>
      </details>
    </div>
  </section>

  <div class="fin-grid">
    <!-- Plus gros débiteurs -->
    <section class="d-card">
      <div class="d-card-head"><div class="d-card-title">Plus gros débiteurs</div><span class="fin-muted">reste dû TTC</span></div>
      <?php if (!$debtors): ?>
        <div class="d-card-body fin-muted">Aucun impayé.</div>
      <?php else: ?>
      <div style="overflow-x:auto;">
      <table class="d-table fin-table">
        <thead><tr><th>Client</th><th class="r">Reste dû</th><th class="r">Dont en retard</th></tr></thead>
        <tbody>
        <?php foreach ($debtors as $dt): ?>
          <tr>
            <td><a href="<?= e(url_for('dispatcher/client_view.php?id='.(int)$dt['client_id'].'#finances')) ?>"><?= e($dt['name']) ?></a> <?= client_bad_payer_badge((int)$dt['client_id'], false) ?>
              <div class="fin-muted" style="font-size:.74rem;"><?= (int)$dt['count'] ?> facture<?= $dt['count'] > 1 ? 's' : '' ?><?= $dt['max_days_late'] ? ' · jusqu\'à '.(int)$dt['max_days_late'].' j de retard' : '' ?></div></td>
            <td class="r nowrap"><b><?= e(money_fr($dt['due'])) ?></b></td>
            <td class="r nowrap"><?= $dt['late'] > 0 ? e(money_fr($dt['late'])) : '—' ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      </div>
      <?php endif; ?>
    </section>

    <!-- Répartition -->
    <section class="d-card">
      <div class="d-card-head"><div class="d-card-title">Facturé sur 12 mois</div><span class="fin-muted">par métier et par technicien, TTC</span></div>
      <div class="d-card-body">
        <?php foreach (['Par métier' => $byCat, 'Par technicien' => $byTech] as $titre => $rows): $maxR = max([1.0, ...array_map(static fn($r) => $r['ttc'], $rows)]); ?>
          <div class="d-label" style="margin:.2rem 0 .4rem;"><?= e($titre) ?></div>
          <?php if (!$rows): ?><div class="fin-muted" style="margin-bottom:.8rem;">Aucune facture émise sur la période.</div><?php endif; ?>
          <?php foreach ($rows as $r): ?>
            <div class="fin-row">
              <div class="fin-row-l"><?= e($r['label']) ?><span><?= (int)$r['count'] ?> fact.</span></div>
              <div class="fin-row-bar"><i style="width:<?= round($r['ttc'] / $maxR * 100, 1) ?>%"></i></div>
              <div class="fin-row-v"><?= e(money_fr($r['ttc'])) ?></div>
            </div>
          <?php endforeach; ?>
          <div style="height:.6rem;"></div>
        <?php endforeach; ?>
        <p class="fin-note">« Non rattaché » : factures importées de Pennylane sans intervention liée dans le site.</p>
      </div>
    </section>
  </div>
</div>

<?php
/** Pas de graduation « rond » (1, 2, 2,5, 5 × 10ⁿ) pour 4 à 5 lignes d'axe. */
function fin_nice_step(float $max): float
{
    $raw = $max / 4;
    $p = 10 ** floor(log10(max($raw, 1)));
    foreach ([1, 2, 2.5, 5, 10] as $m) if ($raw <= $m * $p) return $m * $p;
    return 10 * $p;
}
?>
<style>
.fin { --fin-s1: #2a78d6; --fin-s2: #eb6834; --fin-grid: #e6e9ef; }
.fin-muted { color: var(--d-t2); font-size: .8rem; }
.fin-kpis { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: .8rem; margin-bottom: .6rem; }
.fin-kpi { background: var(--d-card); border: 1px solid var(--d-border); border-radius: var(--d-radius, 10px); padding: .9rem 1rem; text-decoration: none; color: inherit; display: block; }
a.fin-kpi:hover { border-color: var(--d-border-2); }
.fin-kpi-l { font-size: .8rem; color: var(--d-t2); font-weight: 600; }
.fin-kpi-v { font-size: 1.55rem; font-weight: 800; color: var(--d-t1); margin: .15rem 0; font-variant-numeric: tabular-nums; }
.fin-kpi-s { font-size: .76rem; color: var(--d-t3); }
.fin-kpi.is-alert { border-left: 4px solid #b91c1c; }
.fin-kpi.is-alert .fin-kpi-l::before { content: '⚠ '; color: #b91c1c; }
.fin-sub { font-size: .86rem; color: var(--d-t1); margin: 0 0 1.1rem; }
.fin-sub span { color: var(--d-t3); }
.fin-sub a { font-weight: 700; color: #c2410c; }
.fin-grid { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1.25fr); gap: 1rem; margin-bottom: 1rem; align-items: start; }
@media (max-width: 1000px) { .fin-grid { grid-template-columns: 1fr; } }
.fin-row { display: grid; grid-template-columns: minmax(110px, 1.1fr) 2fr minmax(90px, auto); gap: .6rem; align-items: center; padding: .3rem 0; font-size: .86rem; }
.fin-row-l span { display: block; font-size: .72rem; color: var(--d-t3); }
.fin-row-bar { height: 12px; background: transparent; border-radius: 0 4px 4px 0; }
.fin-row-bar i { display: block; height: 100%; background: var(--fin-s1); border-radius: 0 4px 4px 0; }
.fin-row-bar i.is-old { background: #b91c1c; }
.fin-row-v { text-align: right; font-variant-numeric: tabular-nums; font-weight: 600; white-space: nowrap; }
.fin-note { font-size: .76rem; color: var(--d-t3); margin: .6rem 0 0; }
.fin-table .r { text-align: right; }
.fin-table .nowrap { white-space: nowrap; }
.fin-table { font-size: .85rem; }
.fin-legend { display: flex; gap: 1rem; font-size: .8rem; color: var(--d-t2); }
.fin-legend i { display: inline-block; width: 10px; height: 10px; border-radius: 3px; margin-right: .35rem; vertical-align: -1px; }
.fin-chart-card { margin-bottom: 1rem; }
.fin-chart { position: relative; height: 240px; margin-bottom: 1.6rem; padding-left: 72px; }
.fin-gridlines { position: absolute; inset: 0; }
.fin-gridlines div { position: absolute; left: 72px; right: 0; border-top: 1px solid var(--fin-grid); }
.fin-gridlines span { position: absolute; right: calc(100% + 8px); transform: translateY(-50%); font-size: .72rem; color: var(--d-t3); white-space: nowrap; font-variant-numeric: tabular-nums; }
.fin-cols { display: grid; grid-template-columns: repeat(12, minmax(0, 1fr)); align-items: end; height: 100%; position: relative; z-index: 1; }
.fin-col { position: relative; height: 100%; display: flex; flex-direction: column; justify-content: flex-end; outline: none; cursor: default; }
.fin-col:hover, .fin-col:focus-visible { background: rgba(42, 120, 214, .06); }
.fin-bars { display: flex; justify-content: center; align-items: flex-end; gap: 2px; height: 100%; }
.fin-bars i { display: block; width: min(16px, 38%); border-radius: 4px 4px 0 0; min-height: 0; }
.fin-xlabel { position: absolute; bottom: -1.4rem; left: 0; right: 0; text-align: center; font-size: .72rem; color: var(--d-t3); }
.fin-col[data-tip]:hover::after, .fin-col[data-tip]:focus-visible::after {
  content: attr(data-tip); position: absolute; bottom: calc(100% + 6px); left: 50%; transform: translateX(-50%);
  background: #16243f; color: #fff; font-size: .76rem; padding: .4rem .6rem; border-radius: 6px; white-space: nowrap; z-index: 5; pointer-events: none;
}
/* Infobulle gardée dans la carte : calée à gauche sur les premiers mois, à droite sur les derniers. */
.fin-col:nth-child(-n+3)[data-tip]:hover::after, .fin-col:nth-child(-n+3)[data-tip]:focus-visible::after { left: 0; transform: none; }
.fin-col:nth-child(n+9)[data-tip]:hover::after, .fin-col:nth-child(n+9)[data-tip]:focus-visible::after { left: auto; right: 0; transform: none; }
.fin-details summary { cursor: pointer; font-size: .82rem; color: var(--d-t2); margin-top: .2rem; }
@media (max-width: 640px) {
  .fin-chart { height: 200px; padding-left: 60px; }
  .fin-gridlines div { left: 60px; }
  .fin-xlabel { font-size: .66rem; }
  .fin-col:nth-child(odd) .fin-xlabel { visibility: hidden; }   /* un mois sur deux : sinon les noms se chevauchent */
  .fin-row { grid-template-columns: 1fr 1fr; }
  .fin-row-bar { grid-column: 1 / -1; order: 3; }
}
</style>
<?php require __DIR__.'/partials/footer.php'; ?>
