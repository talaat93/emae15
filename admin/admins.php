<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/render.php';
require_admin();

if (!admin_is_super()) { flash('error', 'Réservé au compte principal.'); redirect_to('admin/index.php'); }

$moi = current_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string)($_POST['action'] ?? '');
    $id     = (int)($_POST['id'] ?? 0);
    $cible  = $id > 0 ? get_admin($id) : null;

    if ($action === 'create') {
        $nom   = trim((string)($_POST['name'] ?? ''));
        $email = trim((string)($_POST['email'] ?? ''));
        $mdp   = (string)($_POST['password'] ?? '');
        if ($nom === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($mdp) < 10) {
            flash('error', 'Nom, e-mail valide et mot de passe d\'au moins 10 caractères sont requis.');
        } elseif (db_fetch('SELECT id FROM admins WHERE email = ?', [$email])) {
            flash('error', 'Un compte utilise déjà cet e-mail.');
        } else {
            $droits = array_values(array_intersect((array)($_POST['perms'] ?? []), admin_screen_files()));
            db_execute('INSERT INTO admins (name, email, password_hash, role, permissions, status) VALUES (?,?,?,?,?,1)',
                [$nom, $email, password_hash($mdp, PASSWORD_DEFAULT), 'admin',
                 json_encode($droits, JSON_UNESCAPED_UNICODE)]);
            admin_log('création', 'compte admin', $nom.' ('.count($droits).' écrans autorisés)');
            flash('success', 'Compte « '.$nom.' » créé.');
        }
        redirect_to('admin/admins.php');
    }

    if (!$cible) { flash('error', 'Compte introuvable.'); redirect_to('admin/admins.php'); }

    if ($action === 'perms') {
        $droits = array_values(array_intersect((array)($_POST['perms'] ?? []), admin_screen_files()));
        db_execute('UPDATE admins SET permissions = ? WHERE id = ?',
            [json_encode($droits, JSON_UNESCAPED_UNICODE), $id]);
        admin_log('modification', 'droits admin', $cible['name'].' — '.count($droits).' écrans autorisés');
        flash('success', 'Droits de « '.$cible['name'].' » mis à jour.');
    }

    if ($action === 'password') {
        $mdp = (string)($_POST['password'] ?? '');
        if (mb_strlen($mdp) < 10) {
            flash('error', 'Le mot de passe doit faire au moins 10 caractères.');
        } else {
            db_execute('UPDATE admins SET password_hash = ? WHERE id = ?', [password_hash($mdp, PASSWORD_DEFAULT), $id]);
            admin_log('modification', 'mot de passe admin', $cible['name']);
            flash('success', 'Mot de passe de « '.$cible['name'].' » modifié.');
        }
    }

    if ($action === 'toggle') {
        if ((int)$cible['id'] === (int)$moi['id']) {
            flash('error', 'Vous ne pouvez pas désactiver votre propre compte.');
        } elseif (($cible['role'] ?? '') === 'super' && super_admin_count() <= 1) {
            flash('error', 'Impossible de désactiver le dernier compte principal.');
        } else {
            db_execute('UPDATE admins SET status = 1 - status WHERE id = ?', [$id]);
            admin_log('modification', 'compte admin', $cible['name'].((int)$cible['status'] === 1 ? ' désactivé' : ' réactivé'));
            flash('success', 'Compte « '.$cible['name'].' » mis à jour.');
        }
    }

    if ($action === 'delete') {
        if ((int)$cible['id'] === (int)$moi['id']) {
            flash('error', 'Vous ne pouvez pas supprimer votre propre compte.');
        } elseif (($cible['role'] ?? '') === 'super' && super_admin_count() <= 1) {
            flash('error', 'Impossible de supprimer le dernier compte principal.');
        } else {
            db_execute('DELETE FROM admins WHERE id = ?', [$id]);
            admin_log('suppression', 'compte admin', (string)$cible['name']);
            flash('success', 'Compte « '.$cible['name'].' » supprimé.');
        }
    }
    redirect_to('admin/admins.php');
}

$comptes = all_admins();
$editId  = (int)($_GET['edit'] ?? 0);
$edite   = $editId > 0 ? get_admin($editId) : null;

$adminSection = 'admins';
require_once __DIR__ . '/partials/header.php';

/** Cases à cocher des écrans, groupées comme le menu. */
function rendre_droits(array $coches): void {
    foreach (admin_screens() as $groupe => $ecrans) { ?>
      <fieldset class="perm-group">
        <legend><?= e($groupe) ?></legend>
        <?php foreach ($ecrans as $fichier => $def):
            $toujours = !empty($def[1]); ?>
          <label class="perm<?= $toujours ? ' perm--fixe' : '' ?>">
            <input type="checkbox" name="perms[]" value="<?= e($fichier) ?>"
                   <?= ($toujours || in_array($fichier, $coches, true)) ? 'checked' : '' ?>
                   <?= $toujours ? 'disabled' : '' ?>>
            <span><?= e($def[0]) ?><?= $toujours ? ' — toujours accessible' : '' ?></span>
          </label>
        <?php endforeach; ?>
      </fieldset>
    <?php }
}
?>
<div class="admin-page-toolbar">
  <div>
    <div class="admin-breadcrumb">Administration</div>
    <h1 class="admin-page-title">👥 Comptes administrateurs</h1>
    <p class="admin-page-subtitle">Vous êtes le compte principal : vous seul voyez cet écran et le journal d'activité.
       Pour chaque collaborateur, cochez les écrans auxquels il a droit.</p>
  </div>
</div>

<section class="admin-panel">
  <div class="admin-panel__head"><h2><?= count($comptes) ?> compte<?= count($comptes) > 1 ? 's' : '' ?></h2></div>
  <div class="admin-panel__body admin-table-wrap">
    <table class="admin-table">
      <thead><tr><th>Nom</th><th>E-mail</th><th>Rôle</th><th>Accès</th><th>Dernière connexion</th><th style="text-align:right;">Actions</th></tr></thead>
      <tbody>
      <?php foreach ($comptes as $c):
          $super = ($c['role'] ?? '') === 'super';
          $actif = (int)($c['status'] ?? 1) === 1; ?>
        <tr style="<?= $actif ? '' : 'opacity:.55;' ?>">
          <td style="font-weight:700;"><?= e($c['name']) ?><?= (int)$c['id'] === (int)$moi['id'] ? ' <small style="color:#7b8aa8;font-weight:400;">(vous)</small>' : '' ?></td>
          <td style="font-size:.85rem;"><?= e($c['email']) ?></td>
          <td><?= $super ? '<strong style="color:#F07B1D;">Principal</strong>' : 'Standard' ?></td>
          <td style="font-size:.85rem;"><?= $super ? 'Tous les écrans' : count(admin_permissions($c)).' écran'.(count(admin_permissions($c)) > 1 ? 's' : '') ?></td>
          <td style="font-size:.82rem;color:#7b8aa8;"><?= $c['last_login_at'] ? e(date('d/m/Y H:i', strtotime((string)$c['last_login_at']))) : 'jamais' ?></td>
          <td style="text-align:right;white-space:nowrap;">
            <?php if (!$super): ?>
              <a class="admin-btn admin-btn--secondary" style="min-height:34px;padding:0 .7rem;font-size:.8rem;"
                 href="<?= e(url_for('admin/admins.php?edit='.(int)$c['id'])) ?>">Droits</a>
            <?php endif; ?>
            <?php if ((int)$c['id'] !== (int)$moi['id']): ?>
              <form method="post" style="display:inline;">
                <?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                <button class="admin-btn admin-btn--secondary" style="min-height:34px;padding:0 .7rem;font-size:.8rem;" type="submit"><?= $actif ? 'Désactiver' : 'Réactiver' ?></button>
              </form>
              <form method="post" style="display:inline;" onsubmit="return confirm('Supprimer définitivement le compte <?= e(addslashes($c['name'])) ?> ?');">
                <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                <button class="admin-btn" style="min-height:34px;padding:0 .7rem;font-size:.8rem;background:#fff0f0;color:#dc2626;border:1px solid #fecaca;" type="submit">🗑</button>
              </form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>

<?php if ($edite && ($edite['role'] ?? '') !== 'super'): ?>
<form method="post">
  <?= csrf_field() ?><input type="hidden" name="action" value="perms"><input type="hidden" name="id" value="<?= (int)$edite['id'] ?>">
  <section class="admin-panel">
    <div class="admin-panel__head">
      <h2>Droits de <?= e($edite['name']) ?></h2>
      <p>Les écrans décochés disparaissent de son menu, et une tentative d'accès direct par l'adresse est refusée.</p>
    </div>
    <div class="admin-panel__body">
      <?php rendre_droits(admin_permissions($edite)); ?>
    </div>
    <div class="admin-savebar"><button class="admin-btn admin-btn--primary" type="submit">Enregistrer les droits</button></div>
  </section>
</form>

<form method="post">
  <?= csrf_field() ?><input type="hidden" name="action" value="password"><input type="hidden" name="id" value="<?= (int)$edite['id'] ?>">
  <section class="admin-panel">
    <div class="admin-panel__head"><h2>Changer son mot de passe</h2></div>
    <div class="admin-panel__body">
      <label class="admin-field" style="max-width:24rem;"><span>Nouveau mot de passe (10 caractères minimum)</span>
        <input type="password" name="password" minlength="10" required autocomplete="new-password"></label>
    </div>
    <div class="admin-savebar"><button class="admin-btn admin-btn--primary" type="submit">Modifier le mot de passe</button></div>
  </section>
</form>
<?php endif; ?>

<form method="post">
  <?= csrf_field() ?><input type="hidden" name="action" value="create">
  <section class="admin-panel">
    <div class="admin-panel__head">
      <h2>Ajouter un compte</h2>
      <p>Le nouveau compte est standard : il n'accède ni à cet écran, ni au journal d'activité.</p>
    </div>
    <div class="admin-panel__body">
      <div class="admin-form-grid admin-form-grid--3">
        <label class="admin-field"><span>Nom *</span><input type="text" name="name" required></label>
        <label class="admin-field"><span>E-mail *</span><input type="email" name="email" required autocomplete="off"></label>
        <label class="admin-field"><span>Mot de passe * (10 min.)</span><input type="password" name="password" minlength="10" required autocomplete="new-password"></label>
      </div>
      <p style="margin:1.2rem 0 .5rem;font-weight:700;color:#5b6b92;">Écrans autorisés</p>
      <?php rendre_droits([]); ?>
    </div>
    <div class="admin-savebar"><button class="admin-btn admin-btn--primary" type="submit">Créer le compte</button></div>
  </section>
</form>

<style>
.perm-group{border:1px solid #e4ebf7;border-radius:12px;padding:.75rem 1rem 1rem;margin:0 0 .8rem;}
.perm-group legend{font-size:.72rem;font-weight:800;text-transform:uppercase;letter-spacing:.06em;color:#8494b4;padding:0 .4rem;}
.perm{display:flex;align-items:center;gap:.5rem;padding:.25rem 0;font-size:.88rem;color:#3b4a6b;cursor:pointer;}
.perm--fixe{opacity:.6;cursor:default;}
.perm input{width:16px;height:16px;flex:0 0 auto;}
</style>
<?php require __DIR__ . '/partials/footer.php'; ?>
