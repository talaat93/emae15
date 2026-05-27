<?php
declare(strict_types=1);
require_once __DIR__.'/../includes/bootstrap.php';
boot_session();
if (!empty($_SESSION['disp_id'])) { header('Location: '.url_for('dispatcher/index.php')); exit; }
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim((string)($_POST['email'] ?? ''));
    $pass  = (string)($_POST['password'] ?? '');
    $d = dispatcher_login_check($email, $pass);
    if ($d) {
        $_SESSION['disp_id']   = (int)$d['id'];
        $_SESSION['disp_name'] = $d['name'];
        header('Location: '.url_for('dispatcher/index.php')); exit;
    }
    $error = 'Email ou mot de passe incorrect.';
}
$co = htmlspecialchars(company_name(), ENT_QUOTES, 'UTF-8');
?><!DOCTYPE html>
<html lang="fr"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<meta name="robots" content="noindex,nofollow">
<title>Espace Dispatcher — <?= $co ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800;900&family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= htmlspecialchars(asset_url('assets/css/dispatcher.css'),ENT_QUOTES,'UTF-8') ?>">
<style>
.login-page{min-height:100vh;display:flex;align-items:center;justify-content:center;padding:2rem;background:radial-gradient(ellipse at 20% 50%,rgba(240,123,29,.08) 0%,transparent 60%),radial-gradient(ellipse at 80% 20%,rgba(59,130,246,.06) 0%,transparent 55%),var(--d-bg,#040d1f);}
.login-card{width:100%;max-width:420px;}
.login-logo{font-family:'Syne',Arial,sans-serif;font-size:2rem;font-weight:900;color:#fff;letter-spacing:.04em;margin-bottom:.25rem;}
.login-logo span{color:#F07B1D;}
.login-badge{display:inline-block;background:rgba(240,123,29,.12);color:#F07B1D;border:1px solid rgba(240,123,29,.3);border-radius:99px;font-size:.72rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;padding:.2rem .75rem;margin-bottom:1.5rem;}
.login-box{background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.09);border-radius:16px;padding:2rem;}
.login-title{font-family:'Syne',Arial,sans-serif;font-size:1.3rem;font-weight:800;color:#e8ecf5;margin-bottom:.35rem;}
.login-sub{font-size:.84rem;color:#8fa0c4;margin-bottom:1.75rem;}
.login-field{margin-bottom:1.1rem;}
.login-field label{display:block;font-size:.76rem;font-weight:700;color:#8fa0c4;text-transform:uppercase;letter-spacing:.08em;margin-bottom:.4rem;}
.login-field input{width:100%;background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.12);border-radius:8px;color:#e8ecf5;padding:.7rem 1rem;font-size:.9rem;outline:none;transition:border-color .2s;font-family:inherit;box-sizing:border-box;}
.login-field input:focus{border-color:#F07B1D;}
.login-submit{width:100%;background:#F07B1D;color:#fff;border:none;border-radius:8px;padding:.8rem 1rem;font-size:.95rem;font-weight:800;cursor:pointer;font-family:inherit;margin-top:.5rem;transition:background .18s;}
.login-submit:hover{background:#c95f0b;}
.login-error{background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.3);color:#ef4444;border-radius:8px;padding:.7rem 1rem;font-size:.85rem;margin-bottom:1rem;}
.login-back{display:block;text-align:center;margin-top:1.25rem;color:#8fa0c4;font-size:.8rem;text-decoration:none;}
.login-back:hover{color:#e8ecf5;}
</style>
</head><body class="d-body">
<div class="login-page">
  <div class="login-card">
    <div class="login-logo">EM<span>AE</span></div>
    <div class="login-badge">Espace Dispatcher</div>
    <div class="login-box">
      <div class="login-title">Connexion</div>
      <div class="login-sub">Accès réservé aux dispatchers EMAE</div>
      <?php if ($error !== ''): ?><div class="login-error">⚠️ <?= htmlspecialchars($error,ENT_QUOTES,'UTF-8') ?></div><?php endif; ?>
      <form method="post" autocomplete="on">
        <div class="login-field">
          <label for="f-email">Adresse email</label>
          <input id="f-email" type="email" name="email" required autocomplete="username" placeholder="dispatcher@emae.fr">
        </div>
        <div class="login-field">
          <label for="f-pw">Mot de passe</label>
          <input id="f-pw" type="password" name="password" required autocomplete="current-password" placeholder="••••••••">
        </div>
        <button class="login-submit" type="submit">Se connecter →</button>
      </form>
    </div>
    <a class="login-back" href="<?= htmlspecialchars(url_for(''),ENT_QUOTES,'UTF-8') ?>">← Retour au site</a>
  </div>
</div>
</body></html>
