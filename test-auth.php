<?php
session_start();
require_once __DIR__ . '/db.php';

$count = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Baiku Authentication Test</title>
<style>body{font-family:Arial,sans-serif;max-width:700px;margin:50px auto;line-height:1.6}code{background:#eee;padding:3px 6px}a{color:#8a4c3c}</style>
</head>
<body>
<h1>Baiku Authentication Test</h1>
<p>Database: <strong>baiku2_db</strong></p>
<p>Registered users: <strong><?= $count ?></strong></p>
<p>Current session:
<strong><?= !empty($_SESSION['user_id']) ? 'Logged in (user ID '.(int)$_SESSION['user_id'].')' : 'Not logged in' ?></strong></p>
<p><a href="login.html">Open Login / Register</a></p>
<p><a href="api.php?action=session">Test Session API</a></p>
</body>
</html>
