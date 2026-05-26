<?php
declare(strict_types=1);
require_once __DIR__.'/../includes/bootstrap.php';
boot_session();
if (!empty($_SESSION['tech_id'])) { header('Location: '.url_for('tech/index.php')); exit; }

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim((string)($_POST['email']    ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $tech = tech_login_check($email, $password);
    if ($tech) {
        $_SESSION['tech_id']   = (int)$tech['id'];
        $_SESSION['tech_name'] = $tech['name'];
        header('Location: '.url_for('tech/index.php')); exit;
    }
    $error = 'Email ou mot de passe incorrect.';
}
?><!DOCTYPE html>
<html lang="fr"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0,viewport-fit=cover">
<title>Espace Technicien — <?= htmlspecialchars(company_name(),ENT_QUOTES) ?></title>
<link rel="stylesheet" href="<?= htmlspecialchars(asset_url('assets/css/tech.css'),ENT_QUOTES) ?>">
</head><body>
<div class="tech-login-wrap">
  <div class="tech-login-box">
    <div class="tech-login-logo">EM<span>AE</span></div>
    <div class="tech-login-title">Espace technicien</div>
    <?php if ($error !== ''): ?><div class="tech-flash-err"><?= htmlspecialchars($error,ENT_QUOTES) ?></div><?php endif; ?>
    <form method="post">
      <div class="tech-field">
        <label>Email professionnel</label>
        <input type="email" name="email" required autocomplete="username" placeholder="prenom.nom@emae.fr" inputmode="email">
      </div>
      <div class="tech-field">
        <label>Mot de passe</label>
        <input type="password" name="password" required autocomplete="current-password">
      </div>
      <button class="tech-btn tech-btn-primary" type="submit" style="margin-top:1.1rem;">Se connecter →</button>
    </form>
  </div>
</div>
</body></html>
