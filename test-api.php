<?php
/*
 * Temporary Phase 3 database/API test.
 * Open:
 * http://localhost/baiku2/test-api.php
 *
 * Delete this file after testing if you want.
 */

require_once __DIR__ . '/db.php';

echo '<h2>Baiku Phase 3 Test</h2>';

try {
    $count = $pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();

    echo '<p style="font-family:Arial">Database connection: <strong>OK</strong></p>';
    echo '<p style="font-family:Arial">Products in database: <strong>' . (int)$count . '</strong></p>';

    echo '<p><a href="api.php?action=products">Test Products API</a></p>';
    echo '<p><a href="api.php?action=products&category=Helmets">Test Helmet Filter</a></p>';
    echo '<p><a href="api.php?action=product&id=1">Test Single Product</a></p>';

} catch (Throwable $e) {
    http_response_code(500);
    echo '<p>Database test failed.</p>';
}
?>
