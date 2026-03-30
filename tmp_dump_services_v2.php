<?php
require_once __DIR__ . '/includes/functions.php';
$rows = db_query("SELECT service_id, name, category, status FROM services", "", []);
echo json_encode($rows, JSON_PRETTY_PRINT);
?>
