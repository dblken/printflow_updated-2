<?php
require_once __DIR__ . '/includes/functions.php';
$so = db_query("SHOW COLUMNS FROM service_orders", "", []);
echo "service_orders Cols: " . json_encode(array_column($so, 'Field')) . "\n";
?>
