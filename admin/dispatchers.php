<?php
$adminSection = 'dispatchers';
require __DIR__ . '/partials/header.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = trim((string)($_POST['action'] ?? ''));
    $id     = (int)($_POST['id'] ?? 0);

    if ($action === 'save') {
        $name   = trim((string)($_POST['name']   ?? ''));
        $email  = trim((string)($_POST['email']  ?? ''));
        $phone  = trim((string)($_POST['phone']  ?? ''));
        $status = in_array($_POST['status'] ?? '', ['actif','inactif'], true) ? $_POST['status'] : 'actif';
        $pw     = trim((string)($_POST['password'] ?? ''));

        if ($name === '' || $email === '') { flash('error','Nom et email requis.'); redirect_to('admin/dispatchers.php'); }

        if ($id > 0) {
            if ($pw !== '') {
                db_execute('UPDATE dispatchers SET name=?,email=?,phone=?,status=?,password_hash=? WHERE id=?',
                    [$name, $email, $phone, $status, password_hash($pw, PASSWORD_DEFAULT), $id]);
            } else {
                db_execute('UPDATE dispatchers SET name=?,email=?,phone=?,status=? WHERE id=?',
                    [$name, $email, $phone, $status, $id]);
            }
            flash('success', 'Dispatcher mis à jour.');
        } else {
            if ($pw === '') { flash('error','Mot de passe requis pour créer un compte.'); redirect_to('admin/dispatchers.php'); }
            try {
                db_execute('INSERT INTO dispatchers (name,email,phone,status,password_hash) VALUES (?,?,?,?,?)',
                    [$name, $email, $phone, $status, password_hash($pw, PASSWORD_DEFAULT)]);
                flash('success', 'Compte dispatcher créé — URL : '.url_for('dispatcher/login.php'));
            } catch (Throwable $e) {
                flash('error', 'Cet email existe déjà.');
            }
        }
    } elseif ($action === 'delete' && $id > 0) {
        db_execute('DELETE FROM dispatchers WHERE id = ?', [$id]);
        flash('success', 'Dispatcher supprimé.');
    }
    redirect_to('admin/dispatchers.php');
}

$dispatchers = all_dispatchers();
$edit        = null;
if (isset($_GET['edit'])) {
    try { $edit = db_fetch('SELECT * FROM dispatchers WHERE id = ?', [(int)$_GET['edit']]); } catch (Throwable $e) {}
}
?>
<div class="admin-page-toolbar">
  <div>
    <div class="admin-breadcrumb">Dispatchers</div>
    <h1 class="admin-page-title">Dispatchers</h1>
    <p class="admin-page-subtitle">
      Accès portail :
      <a href="<?= e(url_for('dispatcher/login.php')) ?>" target="_blank" style="color:#2351c5;font-weight:700;">
        <?= e(url_for('dispatcher/login.php')) ?>
      </a>
    </p>
  </div>
</div>

<div style="display:grid;grid-template-columns:1.2fr 1fr;gap:1.25rem;align-items:start;">

<!-- Liste dispatchers -->
<section class="admin-panel">
  <div class="admin-panel__head"><h2>🗂️ Dispatchers (<?= count($dispatchers) ?>)</h2></div>
  <div class="admin-panel__body" style="padding:0;">
    <?php if (empty($dispatchers)): ?>
      <p style="padding:1.5rem;color:#8a9ab8;text-align:center;">Aucun dispatcher. Créez le premier compte ci-contre.</p>
    <?php else: ?>
    <table class="admin-table" style="font-size:.88rem;">
      <thead><tr><th>Nom</th><th>Contact</th><th>Statut</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($dispatchers as $d): ?>
        <tr>
          <td><strong><?= e($d['name']) ?></strong></td>
          <td style="font-size:.82rem;">
            <?php if (!empty($d['phone'])): ?><a href="tel:<?= e(preg_replace('/\s+/','',(string)$d['phone'])) ?>" style="color:#2351c5;display:block;"><?= e($d['phone']) ?></a><?php endif; ?>
            <span style="color:#8a9ab8;"><?= e($d['email']) ?></span>
          </td>
          <td>
            <span style="padding:.2rem .65rem;border-radius:8px;font-size:.76rem;font-weight:700;background:<?= $d['status']==='actif'?'#e6fff2':'#fff0f0' ?>;color:<?= $d['status']==='actif'?'#14653a':'#8c2424' ?>;">
              <?= e($d['status']) ?>
            </span>
          </td>
          <td style="white-space:nowrap;">
            <a href="?edit=<?= (int)$d['id'] ?>" style="padding:.3rem .7rem;border-radius:8px;background:#edf0ff;color:#1a3baa;font-weight:700;font-size:.8rem;text-decoration:none;display:inline-block;margin-right:.3rem;">✏️</a>
            <form method="post" style="display:inline;" onsubmit="return confirm('Supprimer ce dispatcher ?');">
              <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
              <input type="hidden" name="id" value="<?= (int)$d['id'] ?>">
              <input type="hidden" name="action" value="delete">
              <button type="submit" style="padding:.3rem .7rem;border-radius:8px;background:#fff0f0;color:#8c2424;border:none;font-weight:700;font-size:.8rem;cursor:pointer;">🗑️</button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>
</section>

<!-- Formulaire création/édition -->
<form method="post" class="admin-stack">
  <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
  <input type="hidden" name="action" value="save">
  <input type="hidden" name="id" value="<?= $edit ? (int)$edit['id'] : 0 ?>">
  <section class="admin-panel">
    <div class="admin-panel__head">
      <h2><?= $edit ? '✏️ Modifier — '.e((string)($edit['name'] ?? '')) : '➕ Nouveau dispatcher' ?></h2>
      <?php if ($edit): ?><p><a href="admin/dispatchers.php" style="color:#2351c5;">Créer un nouveau compte</a></p><?php endif; ?>
    </div>
    <div class="admin-panel__body">
      <div class="admin-form-grid admin-form-grid--2">
        <label class="admin-field"><span>Nom complet *</span><input type="text" name="name" value="<?= e((string)($edit['name'] ?? '')) ?>" required placeholder="Prénom Nom"></label>
        <label class="admin-field"><span>Téléphone</span><input type="tel" name="phone" value="<?= e((string)($edit['phone'] ?? '')) ?>" placeholder="+33612345678"></label>
      </div>
      <label class="admin-field"><span>Email (identifiant connexion) *</span><input type="email" name="email" value="<?= e((string)($edit['email'] ?? '')) ?>" required></label>
      <label class="admin-field">
        <span>Mot de passe <?= $edit ? '<small style="font-weight:400;color:#888;">(laisser vide = inchangé)</small>' : '*' ?></span>
        <input type="password" name="password" autocomplete="new-password" placeholder="<?= $edit ? 'Nouveau mot de passe...' : 'Choisir un mot de passe' ?>">
      </label>
      <label class="admin-field"><span>Statut</span>
        <select name="status">
          <option value="actif"   <?= ($edit['status'] ?? 'actif') === 'actif'   ? 'selected' : '' ?>>✅ Actif</option>
          <option value="inactif" <?= ($edit['status'] ?? '') === 'inactif' ? 'selected' : '' ?>>❌ Inactif</option>
        </select>
      </label>
    </div>
  </section>
  <div class="admin-savebar">
    <button class="admin-btn admin-btn--primary" type="submit"><?= $edit ? '💾 Modifier' : '➕ Créer le compte' ?></button>
  </div>
</form>

</div>
<?php require __DIR__ . '/partials/footer.php'; ?>
