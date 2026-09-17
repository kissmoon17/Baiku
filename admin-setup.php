<?php
/*
 * One-time local setup for the first Baiku administrator.
 * After creating the admin, DELETE this file from htdocs/baiku2.
 */
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
require_once __DIR__ . '/db.php';

$adminCount=(int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='admin'")->fetchColumn();
if($adminCount>0){
    http_response_code(403);
    ?><!doctype html><html><head><meta charset="utf-8"><title>Baiku Admin Setup</title><link rel="stylesheet" href="css/style.css"></head><body><main class="login-wrap"><div class="auth-card"><div class="section-kicker">Baiku Admin</div><h1 class="section-title">Setup already completed</h1><p>The first admin account already exists. Delete <code>admin-setup.php</code> and sign in with your admin account.</p><p><a class="btn" href="login.html">Go to sign in →</a></p></div></main></body></html><?php exit;
}
$message=''; $type='';
if($_SERVER['REQUEST_METHOD']==='POST'){
    $name=trim((string)($_POST['name']??''));
    $email=strtolower(trim((string)($_POST['email']??'')));
    $password=(string)($_POST['password']??'');
    $confirm=(string)($_POST['confirm']??'');
    if(mb_strlen($name)<2 || mb_strlen($name)>100) $message='Name must be between 2 and 100 characters.';
    elseif(!filter_var($email,FILTER_VALIDATE_EMAIL)) $message='Enter a valid email address.';
    elseif(strlen($password)<8) $message='Admin password must contain at least 8 characters.';
    elseif($password!==$confirm) $message='Passwords do not match.';
    else{
        $check=$pdo->prepare("SELECT id FROM users WHERE email=:email LIMIT 1");$check->execute(['email'=>$email]);$existing=$check->fetch();
        if($existing){
            $message='That email is already registered. Use a different email for the admin account.';
        }else{
            $stmt=$pdo->prepare("INSERT INTO users (name,email,password,role) VALUES (:name,:email,:password,'admin')");
            $stmt->execute(['name'=>$name,'email'=>$email,'password'=>password_hash($password,PASSWORD_DEFAULT)]);
            $id=(int)$pdo->lastInsertId(); session_regenerate_id(true); $_SESSION['user_id']=$id; $_SESSION['user_role']='admin';
            header('Location: admin.php'); exit;
        }
    }
    $type='error';
}
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Create Admin — Baiku</title><link rel="stylesheet" href="css/style.css"></head><body><div class="announcement">Baiku · One-time administrator setup</div><main class="login-wrap"><div class="auth-card"><div class="section-kicker">Baiku Admin</div><h1 class="section-title">Create administrator</h1><p class="admin-setup-note">This page can create the first admin account only. Use a strong password. Delete <code>admin-setup.php</code> after setup.</p><?php if($message): ?><div class="auth-message <?=htmlspecialchars($type)?>"><?=htmlspecialchars($message)?></div><?php endif; ?><form class="form-grid" method="post"><div class="field"><label>Name</label><input name="name" required maxlength="100" value="<?=htmlspecialchars($_POST['name']??'')?>"></div><div class="field"><label>Email</label><input type="email" name="email" required maxlength="190" value="<?=htmlspecialchars($_POST['email']??'')?>"></div><div class="field"><label>Password</label><input type="password" name="password" required minlength="8"></div><div class="field"><label>Confirm password</label><input type="password" name="confirm" required minlength="8"></div><button class="btn">Create admin →</button></form></div></main></body></html>
