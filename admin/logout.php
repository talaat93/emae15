<?php declare(strict_types=1);require_once __DIR__.'/../includes/bootstrap.php';logout_admin();flash('success','Déconnexion effectuée.');redirect_to('admin/login.php');
