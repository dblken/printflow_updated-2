<?php
require_once __DIR__ . '/includes/db.php';
$res = db_query("SELECT COUNT(*) as total FROM orders");
echo "TOTAL_ORDERS: " . $res[0]['total'] . "\n";
