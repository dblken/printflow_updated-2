<?php
require_once __DIR__ . '/includes/functions.php';

$r = db_query("SHOW COLUMNS FROM ratings", "", []);
echo "Ratings Cols: " . json_encode(array_column($r, 'Field')) . "\n";
?>
