<?php
require_once __DIR__ . '/includes/functions.php';

$stmt = db_query("SELECT service_type, COUNT(*) as c FROM ratings GROUP BY service_type", "", []);
echo "Ratings groups:\n";
echo json_encode($stmt, JSON_PRETTY_PRINT)."\n";

$svc = db_query("SELECT service_id, name FROM services", "", []);
echo "\nServices in DB:\n";
echo json_encode($svc, JSON_PRETTY_PRINT)."\n";

?>
