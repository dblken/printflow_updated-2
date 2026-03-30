<?php
require_once __DIR__ . '/includes/functions.php';

echo "Ratings for 'Sintraboard Standees':\n";
$r1 = db_query("SELECT COUNT(*) as c FROM ratings WHERE service_type = 'Sintraboard Standees'", "", []);
echo json_encode($r1) . "\n";

echo "Ratings for 'Sintraboard/Standees':\n";
$r2 = db_query("SELECT COUNT(*) as c FROM ratings WHERE service_type = 'Sintraboard/Standees'", "", []);
echo json_encode($r2) . "\n";

echo "Orders for 'Sintraboard Standees':\n";
$o1 = db_query("SELECT COUNT(*) as c FROM orders WHERE service_type = 'Sintraboard Standees'", "", []);
echo json_encode($o1) . "\n";

echo "Orders for 'Sintraboard/Standees':\n";
$o2 = db_query("SELECT COUNT(*) as c FROM orders WHERE service_type = 'Sintraboard/Standees'", "", []);
echo json_encode($o2) . "\n";
?>
