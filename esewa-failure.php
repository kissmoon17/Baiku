<?php
// eSewa appends its response as ?data=... to this dedicated failure URL.
// Keeping success/failure on separate URLs avoids query-string collisions.
$_GET['action'] = 'failure';
require __DIR__ . '/esewa.php';
