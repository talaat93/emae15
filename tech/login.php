<?php
declare(strict_types=1);
require_once __DIR__.'/../includes/bootstrap.php';
if (!empty($_SESSION['tech_id'])) { header('Location: '.url_for('tech/dashboard.php')); exit; }

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim((string)($_POST['email']    ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $tech = tech_login_check($email, $password);
    if ($tech) {
        boot_session();
        session_regenerate_id(true);
        $_SESSION['tech_id']   = (int)$tech['id'];
        $_SESSION['tech_name'] = $tech['name'];
        header('Location: '.url_for('tech/dashboard.php')); exit;
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
<link rel="manifest" href="<?= htmlspecialchars(url_for('tech/manifest.php'), ENT_QUOTES) ?>">
<link rel="icon" href="<?= htmlspecialchars(asset_url('assets/img/icon-192.png'), ENT_QUOTES) ?>">
<link rel="apple-touch-icon" href="<?= htmlspecialchars(asset_url('assets/img/apple-touch-icon.png'), ENT_QUOTES) ?>">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-title" content="EMAE Tech">
<meta name="theme-color" content="#16243f">
</head><body>

<div class="t-login-page">
  <div class="t-login-hero">
    <div class="t-login-logo-wrap">
      <img src="<?= htmlspecialchars(asset_url('assets/img/logo-emae-clair.png'), ENT_QUOTES) ?>" alt="EMAE" style="display:block;width:230px;max-width:75vw;height:auto;margin:0 auto;">
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