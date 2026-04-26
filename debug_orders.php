<?php
require 'includes/db.php';
$orders = db_query("SELECT order_id FROM orders ORDER BY order_id DESC LIMIT 5");
foreach ($orders as $o) {
    echo "ORDER " . $o['order_id'] . "\n";
    $items = db_query("SELECT * FROM order_items WHERE order_id = ?", 'i', [$o['order_id']]);
    print_r($items);
}
