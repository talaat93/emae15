<?php
declare(strict_types=1);
require_once __DIR__.'/../includes/bootstrap.php';
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
$co = htmlspecialchars(company_name(), ENT_QUOTES);
?><!DOCTYPE html>
<html lang="fr"><head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0,viewport-fit=cover">
<meta name="robots" content="noindex,nofollow">
<title>Espace Technicien — <?= $co ?></title>
<link rel="stylesheet" href="<?= htmlspecialchars(asset_url('assets/css/tech.css'), ENT_QUOTES) ?>">
</head><body>

<div class="t-login-page">
  <div class="t-login-hero">
    <div class="t-login-logo-wrap">
      <div class="t-login-logo-text">EM<span>AE</span></div>
    </div>
    <div class="t-login-welcome">Bienvenue Technicien&nbsp;!</div>
    <div class="t-login-sub">Connectez-vous à votre espace de travail</div>
  </div>

  <div class="t-login-card">
    <?php if ($error !== ''): ?>
      <div class="t-flash-err" style="margin-bottom:1.25rem;">⚠️ <?= htmlspecialchars($error, ENT_QUOTES) ?></div>
    <?php endif; ?>

    <form method="post" autocomplete="on">
      <div class="t-login-field">
        <label for="f-email">Adresse email</label>
        <input id="f-email" type="email" name="email" required autocomplete="username"
               placeholder="prenom.nom@emae.fr" inputmode="email">
      </div>
      <div class="t-login-field">
        <label for="f-pw">Mot de passe</label>
        <input id="f-pw" type="password" name="password" required autocomplete="current-password"
               placeholder="••••••••">
      </div>
      <button class="t-btn t-btn-primary" type="submit" style="margin-top:1.5rem;">
        Se connecter →
      </button>
    </form>
  </div>
</div>

</body></html>