<?php
declare(strict_types=1);
require_once __DIR__.'/../includes/bootstrap.php';
if(admin_logged_in()) redirect_to('admin/index.php');
if($_SERVER['REQUEST_METHOD']==='POST'){verify_csrf();if(attempt_login(trim((string)($_POST['email']??'')),(string)($_POST['password']??''))){flash('success','Connexion réussie.');redirect_to('admin/index.php');}flash('error','Email ou mot de passe incorrect.');redirect_to('admin/login.php');}
?><!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Admin EMAE V13</title><link rel="stylesheet" href="<?=e(asset_url('assets/css/admin.css'))?>"></head><body class="admin-body"><div class="login-wrap"><div class="login-card">
<?php if($m=flash('error')):?><div class="flash flash--error"><?=e($m)?></div><?php endif;?>
<?php if($m=flash('success')):?><div class="flash flash--success"><?=e($m)?></div><?php endif;?>
<h1 style="font-size:1.6rem;margin-bottom:.3rem;">EMAE V13</h1><p style="margin-bottom:1.5rem;color:#7b8aa8;">Espace d'administration</p>
<form method="post" class="admin-stack"><input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>">
<label class="admin-field"><span>Email</span><input type="email" name="email" required autofocus></label>
<label class="admin-field"><span>Mot de passe</span><input type="password" name="password" required></label>
<button class="admin-btn admin-btn--primary" type="submit">Se connecter</button>
</form></div></div></body></html>
