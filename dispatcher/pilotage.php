<?php
declare(strict_types=1);
/**
 * Pilotage : site, Google Ads et factures réunis, avec les actions à mener.
 * Réservé, comme Finances, aux comptes ayant l'« Accès aux finances ».
 * Calculs : includes/pilotage.php. Les chiffres Google Ads arrivent par le formulaire en bas
 * de page (rapport exporté de Google Ads) ou automatiquement depuis le PC (api/pilotage.php).
 */
$pageTitle   = 'Pilotage';
$dispSection = 'pilotage';
require __DIR__.'/partials/header.php';
require_finance_access($disp);
require_once __DIR__.'/../includes/stats.php';

$jours = (int)($_GET['jours'] ?? 30);
if (!in_array($jours, [7, 30, 90, 365], true)) $jours = 30;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    if (($_POST['action'] ?? '') === 'import') {
        $f = $_FILES['rapport'] ?? null;
        if (!$f || ($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || (int)$f['size'] > 5_000_000) {
            flash('error', 'Fichier manquant ou trop gros (5 Mo maximum).');
        } else {
            $p = pilotage_ads_parse_csv((string)file_get_contents((string)$f['tmp_name']));
            if ($p['error']) {
                flash('error', $p['error']);
            } else {
                $defaut = $p['has_date'] ? '' : pilotage_date((string)($_POST['date_defaut'] ?? ''));
                if (!$p['has_date'] && $defaut === '') {
                    flash('error', 'Ce rapport n\'a pas de colonne « Jour » : indiquez la date à laquelle rattacher les chiffres.');
                } else {
                    $n = pilotage_ads_normalize($p['rows'], $defaut);
                    $w = pilotage_ads_store($n['rows']);
                    integration_log('pilotage', 'rapport Google Ads importé', ['lignes' => $w, 'dispatcher' => (int)$disp['id']]);
                    flash($w > 0 ? 'success' : 'error', $w.' ligne(s) Google Ads importée(s)'.($n['errors'] ? ' ; '.count($n['errors']).' ignorée(s) : '.implode(' ', array_slice($n['errors'], 0, 3)) : '.'));
                }
            }
        }
    }
    redirect_to('dispatcher/pilotage.php?jours='.$jours);
}

$today = date('Y-m-d');
$S = pilotage_summary($jours, $today);
$F = $S['funnel']; $T = $F['totals'];
$visites = stats_views_total($jours);
$appels = stats_event_total('appel', $jours);
$whatsapp = stats_event_total('whatsapp', $jours);
$lastImport = json_decode(integration_setting('pilotage_last_import', ''), true);
$ratio = static fn(?float $v, int $dec = 1) => $v === null ? '—' : number_format($v, $dec, ',', "\u{00A0}");
$pct = static fn(?float $v) => $v === null ? '—' : round($v * 100).'%';
$outils = [
    'Google Ads' => ['https://ads.google.com/', '#1a73e8', true],
    'Pennylane'  => [url_for('dispatcher/factures.php'), '#0f766e', false],
    'Dispatcher' => [url_for('dispatcher/qualify.php'), '#c2410c', false],
    'Site'       => [url_for('admin/index.php'), '#7c3aed', false],
];
$csrf = csrf_token();
?>
<div class="d-topbar">
  <div>
    <button class="d-menu-toggle" id="d-menu-toggle" aria-label="Menu">☰</button>
    <div>
      <div class="d-topbar-title">Pilotage</div>
      <div class="d-topbar-sub">Site · Google Ads · Factures — du <?= e(date('d/m', strtotime($S['periode']['du']))) ?> au <?= e(date('d/m/Y', strtotime($today))) ?></div>
    </div>
  </div>
  <div class="d-topbar-actions pil-periods">
    <?php foreach ([7 => '7 j', 30 => '30 j', 90 => '3 mois', 365 => '12 mois'] as $j => $l): ?>
      <a class="d-btn d-btn--sm <?= $j === $jours ? 'd-btn--primary' : '' ?>" href="?jours=<?= $j ?>"><?= e($l) ?></a>
    <?php endforeach; ?>
  </div>
</div>

<div class="d-content pil">
  <!-- De l'euro dépensé à l'euro facturé -->
  <div class="pil-funnel">
    <div class="pil-step">
      <div class="pil-l">Dépensé Google Ads</div>
      <div class="pil-v"><?= e(money_fr($T['cost'])) ?></div>
      <div class="pil-s"><?= (int)$T['clicks'] ?> clics · HT</div>
    </div>
    <div class="pil-step">
      <div class="pil-l">Demandes reçues</div>
      <div class="pil-v"><?= (int)$T['leads'] ?></div>
      <div class="pil-s">dont <?= (int)$T['leads_ads'] ?> via Google Ads · <?= $T['cpl_ads'] !== null ? e(money_fr($T['cpl_ads'])).' la demande' : 'coût par demande —' ?></div>
    </div>
    <div class="pil-step">
      <div class="pil-l">Interventions</div>
      <div class="pil-v"><?= (int)$T['interventions'] ?></div>
      <div class="pil-s"><?= $pct($T['conversion']) ?> des demandes · <?= (int)$T['interventions_ads'] ?> via Google Ads</div>
    </div>
    <div class="pil-step">
      <div class="pil-l">Facturé (HT)</div>
      <div class="pil-v"><?= e(money_fr($T['ca_ht'])) ?></div>
      <div class="pil-s">dont <?= e(money_fr($T['ca_ht_ads'])) ?> via Google Ads</div>
    </div>
    <div class="pil-step <?= $T['roas'] !== null && $T['roas'] < PILOTAGE_ROAS_MAUVAIS ? 'is-alert' : ($T['roas'] !== null && $T['roas'] >= PILOTAGE_ROAS_BON ? 'is-good' : '') ?>">
      <div class="pil-l">1 € de pub rapporte</div>
      <div class="pil-v"><?= $T['roas'] !== null ? e($ratio($T['roas'])).' €' : '—' ?></div>
      <div class="pil-s">de chiffre d'affaires HT facturé</div>
    </div>
  </div>
  <div class="pil-sub">
    Site : <b><?= number_format($visites, 0, ',', "\u{00A0}") ?></b> pages vues · <b><?= (int)$appels ?></b> clics « Appeler » · <b><?= (int)$whatsapp ?></b> clics WhatsApp
    <span>— les factures comptées sont celles des demandes de la période, même émises plus tard.</span>
  </div>

  <!-- À faire -->
  <section class="d-card pil-reco">
    <div class="d-card-head"><div class="d-card-title">À faire maintenant (<?= count($S['recommandations']) ?>)</div><span class="pil-muted">classé par urgence</span></div>
    <div class="d-card-body">
      <?php if (!$S['recommandations']): ?>
        <p class="pil-muted" style="margin:0;">Rien d'urgent : les campagnes, les demandes et la facturation sont à jour.</p>
      <?php endif; ?>
      <?php foreach ($S['recommandations'] as $r): [$lien, $coul, $ext] = $outils[$r['outil']] ?? ['#', '#475569', false]; ?>
        <div class="pil-item pil-n<?= (int)$r['niveau'] ?>">
          <div class="pil-item-main">
            <div class="pil-item-t"><?= e($r['titre']) ?></div>
            <div class="pil-item-d"><?= e($r['detail']) ?></div>
          </div>
          <a class="pil-tool" style="--c:<?= e($coul) ?>" href="<?= e($lien) ?>"<?= $ext ? ' target="_blank" rel="noopener"' : '' ?>><?= e($r['outil']) ?> →</a>
        </div>
      <?php endforeach; ?>
    </div>
  </section>

  <!-- Campagnes -->
  <section class="d-card">
    <div class="d-card-head"><div class="d-card-title">Campagnes Google Ads</div><span class="pil-muted">demandes estimées : partagées au prorata des clics quand plusieurs campagnes visent le même métier et département</span></div>
    <?php if (!$F['campaigns']): ?>
      <div class="d-card-body pil-muted">Aucun chiffre Google Ads sur la période. Importez le rapport des campagnes en bas de page.</div>
    <?php else: ?>
    <div style="overflow-x:auto;">
      <table class="d-table pil-table">
        <thead><tr><th>Campagne</th><th>Cible</th><th class="r">Dépensé</th><th class="r">Clics</th><th class="r">Demandes</th><th class="r">Coût / demande</th><th class="r">Interv.</th><th class="r">Facturé HT</th><th class="r">1 € rapporte</th><th>Verdict</th></tr></thead>
        <tbody>
        <?php foreach ($F['campaigns'] as $c): $v = pilotage_verdict($c, $T['cpl_ads']); [$vl, $vd] = pilotage_verdict_label($v); ?>
          <tr>
            <td><b><?= e($c['campaign']) ?></b></td>
            <td class="pil-muted"><?= e(pilotage_metier_label($c['metier'])) ?><br><?= e(pilotage_dept_label($c['dept'])) ?></td>
            <td class="r nowrap"><?= e(money_fr($c['cost'])) ?></td>
            <td class="r"><?= (int)$c['clicks'] ?></td>
            <td class="r"><?= e($ratio($c['leads'])) ?></td>
            <td class="r nowrap"><?= $c['cpl'] !== null ? e(money_fr($c['cpl'])) : '—' ?></td>
            <td class="r"><?= e($ratio($c['interventions'])) ?></td>
            <td class="r nowrap"><?= e(money_fr($c['ca_ht'])) ?></td>
            <td class="r nowrap"><?= $c['roas'] !== null ? e($ratio($c['roas'])).' €' : '—' ?></td>
            <td><span class="pil-verdict pil-v-<?= e($v) ?>" title="<?= e($vd) ?>"><?= e($vl) ?></span></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </section>

  <!-- Métiers × départements -->
  <section class="d-card">
    <div class="d-card-head"><div class="d-card-title">Par métier et par département</div><span class="pil-muted">toutes origines : Google Ads et site</span></div>
    <?php if (!$F['segments']): ?>
      <div class="d-card-body pil-muted">Aucune demande ni dépense sur la période.</div>
    <?php else: ?>
    <div style="overflow-x:auto;">
      <table class="d-table pil-table">
        <thead><tr><th>Métier</th><th>Département</th><th class="r">Demandes Ads</th><th class="r">Demandes site</th><th class="r">Interv.</th><th class="r">Taux</th><th class="r">Facturé HT</th><th class="r">Dépensé</th><th class="r">1 € rapporte</th></tr></thead>
        <tbody>
        <?php foreach ($F['segments'] as $s): ?>
          <tr>
            <td><?= e($s['metier'] !== '' ? pilotage_metier_label($s['metier']) : 'Non précisé') ?></td>
            <td><?= e($s['dept'] !== '' ? pilotage_dept_label($s['dept']) : 'Non précisé') ?></td>
            <td class="r"><?= (int)$s['leads_ads'] ?></td>
            <td class="r"><?= (int)$s['leads_site'] ?></td>
            <td class="r"><?= (int)$s['interventions'] ?></td>
            <td class="r"><?= $pct($s['conversion']) ?></td>
            <td class="r nowrap"><?= e(money_fr($s['ca_ht'])) ?></td>
            <td class="r nowrap"><?= $s['cost'] > 0 ? e(money_fr($s['cost'])) : '—' ?></td>
            <td class="r nowrap"><?= $s['roas'] !== null ? e($ratio($s['roas'])).' €' : '—' ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </section>

  <!-- Import Google Ads -->
  <section class="d-card">
    <div class="d-card-head"><div class="d-card-title">Chiffres Google Ads</div>
      <span class="pil-muted"><?= $S['contexte']['ads_dernier_jour'] ? 'dernier jour reçu : '.e(date('d/m/Y', strtotime($S['contexte']['ads_dernier_jour']))) : 'aucun chiffre reçu' ?><?= $lastImport ? ' · dernier envoi le '.e(date('d/m à H:i', strtotime((string)$lastImport['at']))) : '' ?></span></div>
    <div class="d-card-body">
      <form method="post" enctype="multipart/form-data" class="pil-import">
        <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="import">
        <div class="d-field"><label for="f-rapport">Rapport des campagnes (CSV exporté de Google Ads)</label><input id="f-rapport" type="file" name="rapport" accept=".csv,.tsv,.txt" required></div>
        <div class="d-field"><label for="f-date">Date des chiffres <span class="pil-muted">(seulement si le rapport n'a pas de colonne « Jour »)</span></label><input id="f-date" type="date" name="date_defaut" value="<?= e($today) ?>"></div>
        <button type="submit" class="d-btn d-btn--primary">Importer</button>
      </form>
      <p class="pil-note">Dans Google Ads : Campagnes → Télécharger (CSV), avec les colonnes Jour, Campagne, Coût, Clics, Impressions, Conversions. Réimporter la même période remplace les chiffres, sans doublon.
        Automatique : l'outil « Claude EMAE Pilotage » du PC envoie ces chiffres tout seul (réglage : Réglages → Pilotage).
        Nommer chaque campagne avec son métier et son département (ex. « Électricité Doubs ») rend le calcul exact.</p>
    </div>
  </section>
</div>
<style>
.pil-periods { display: flex; gap: .3rem; flex-wrap: wrap; }
.pil-muted { color: var(--d-t2); font-size: .8rem; }
.pil-funnel { display: grid; grid-template-columns: repeat(5, minmax(0, 1fr)); gap: .6rem; margin-bottom: .6rem; }
@media (max-width: 1100px) { .pil-funnel { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
.pil-step { background: var(--d-card); border: 1px solid var(--d-border); border-radius: var(--d-radius, 10px); padding: .85rem 1rem; position: relative; }
.pil-step.is-alert { border-left: 4px solid #b91c1c; }
.pil-step.is-good { border-left: 4px solid #15803d; }
.pil-l { font-size: .8rem; color: var(--d-t2); font-weight: 600; }
.pil-v { font-size: 1.45rem; font-weight: 800; color: var(--d-t1); margin: .15rem 0; font-variant-numeric: tabular-nums; }
.pil-s { font-size: .74rem; color: var(--d-t3); }
.pil-sub { font-size: .85rem; margin: 0 0 1rem; }
.pil-sub span { color: var(--d-t3); }
.pil .d-card { margin-bottom: 1rem; }
.pil-item { display: flex; gap: .9rem; align-items: center; padding: .7rem .2rem .7rem .8rem; border-left: 4px solid #94a3b8; border-bottom: 1px solid var(--d-border); }
.pil-item:last-child { border-bottom: 0; }
.pil-n1 { border-left-color: #b91c1c; }
.pil-n2 { border-left-color: #d97706; }
.pil-n3 { border-left-color: #2a78d6; }
.pil-item-main { flex: 1; min-width: 0; }
.pil-item-t { font-weight: 700; font-size: .92rem; }
.pil-item-d { font-size: .83rem; color: var(--d-t2); margin-top: .15rem; }
.pil-tool { flex: none; font-size: .78rem; font-weight: 700; color: var(--c); border: 1px solid var(--c); border-radius: 99px; padding: .25rem .7rem; text-decoration: none; white-space: nowrap; }
.pil-table { font-size: .84rem; }
.pil-table .r { text-align: right; font-variant-numeric: tabular-nums; }
.pil-table .nowrap { white-space: nowrap; }
.pil-verdict { display: inline-block; font-size: .74rem; font-weight: 700; padding: .12rem .55rem; border-radius: 99px; white-space: nowrap; }
.pil-v-a_couper { background: #fee2e2; color: #991b1b; }
.pil-v-a_revoir { background: #fef3c7; color: #92400e; }
.pil-v-a_renforcer { background: #dcfce7; color: #166534; }
.pil-v-ok { background: #e0f2fe; color: #075985; }
.pil-v-trop_tot { background: #f1f5f9; color: #475569; }
.pil-import { display: grid; grid-template-columns: 2fr 1fr auto; gap: .8rem; align-items: end; }
.pil-import .d-field { margin: 0; }
@media (max-width: 760px) { .pil-import { grid-template-columns: 1fr; } .pil-item { flex-direction: column; align-items: flex-start; } }
.pil-note { font-size: .78rem; color: var(--d-t3); margin: .8rem 0 0; line-height: 1.5; }
</style>
<?php require __DIR__.'/partials/footer.php'; ?>
