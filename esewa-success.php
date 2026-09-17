<?php
// eSewa appends its response as ?data=... to this dedicated success URL.
// Keeping success/failure on separate URLs avoids query-string collisions.
$_GET['action'] = 'success';
require __DIR__ . '/esewa.php';
