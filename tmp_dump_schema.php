<?php
require_once __DIR__ . '/includes/functions.php';
$rows = db_query("DESCRIBE services", "", []);
echo json_encode($rows, JSON_PRETTY_PRINT);
?>
