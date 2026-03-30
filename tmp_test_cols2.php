<?php
require_once __DIR__ . '/includes/functions.php';

$o = db_query("SHOW COLUMNS FROM orders", "", []);
echo "Orders Cols: " . json_encode(array_column($o, 'Field')) . "\n";

$r = db_query("SHOW COLUMNS FROM ratings", "", []);
echo "Ratings Cols: " . json_encode(array_column($r, 'Field')) . "\n";
?>
