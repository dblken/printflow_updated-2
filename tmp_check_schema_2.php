<?php
require_once __DIR__ . '/includes/db.php';
$res_items = db_query("DESCRIBE order_items");
foreach ($res_items as $row) {
    echo "item: " . $row['Field'] . "\n";
}
echo "----\n";
$res_p = db_query("DESCRIBE products");
foreach ($res_p as $row) {
    echo "product: " . $row['Field'] . "\n";
}
