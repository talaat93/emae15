<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/render.php';
require_admin();

if (!admin_is_super()) { flash('error', 'Réservé au compte principal.'); redirect_to('admin/index.php'); }

$filtre  = (int)($_GET['admin'] ?? 0);
$entrees = admin_activity_recent(200, $filtre);
$comptes = all_admins();

$adminSection = 'activity';
require_once __DIR__ . '/partials/header.php';

function act_icone(string $a): string {
    return match ($a) {
        'connexion'    => '🔑',
        'création'     => '➕',
        'suppression'  => '🗑',
        'modification' => '✏️',
        default        => '•',
    };
}
?>
<div class="admin-page-toolbar">
  <div>
    <div class="admin-breadcrumb">Administration</div>
    <h1 class="admin-page-title">📜 Journal d'activité</h1>
    <p class="admin-page-subtitle">Qui s'est connecté, et qui a modifié quoi. Visible par vous seul.
       Une ligne par enregistrement, conservée un an.</p>
  </div>
</div>

<section class="admin-panel">
  <div class="admin-panel__body">
    <form method="get" style="display:flex;gap:.6rem;align-items:center;flex-wrap:wrap;">
      <label class="admin-field" style="margin:0;"><span>Filtrer par personne</span>
        <select name="admin" onchange="this.form.submit()">
          <option value="0">Tout le monde</option>
          <?php foreach ($comptes as $c): ?>
            <option value="<?= (int)$c['id'] ?>" <?= $filtre === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
    </form>
  </div>
</section>

<section class="admin-panel">
  <div class="admin-panel__head">
    <h2><?= count($entrees) ?> entrée<?= count($entrees) > 1 ? 's' : '' ?></h2>
    <?php if (!$entrees): ?><p>Rien pour l'instant. Le journal se remplit dès la prochaine connexion ou modification.</p><?php endif; ?>
  </div>
  <?php if ($entrees): ?>
  <div class="admin-panel__body admin-table-wrap">
    <table class="admin-table">
      <thead><tr><th style="width:150px;">Quand</th><th style="width:150px;">Qui</th><th style="width:130px;">Quoi</th><th>Sur</th><th style="width:130px;">Depuis</th></tr></thead>
      <tbody>
      <?php foreach ($entrees as $l): ?>
        <tr>
          <td style="font-size:.82rem;white-space:nowrap;"><?= e(date('d/m/Y H:i', strtotime((string)$l['created_at']))) ?></td>
          <td style="font-weight:600;font-size:.86rem;"><?= e($l['admin_name']) ?></td>
          <td style="font-size:.86rem;"><?= e(act_icone((string)$l['action']).' '.$l['action']) ?></td>
          <td style="font-size:.86rem;color:#5b6b92;">
            <?= e((string)($l['resource'] ?? '')) ?>
            <?php if (trim((string)($l['detail'] ?? '')) !== ''): ?>
              <br><small style="color:#8494b4;"><?= e($l['detail']) ?></small>
            <?php endif; ?>
          </td>
          <td style="font-size:.78rem;color:#a3aec4;"><?= e((string)($l['ip'] ?? '')) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</section>
<?php require __DIR__ . '/partials/footer.php'; ?>
