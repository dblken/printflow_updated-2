<?php
require_once __DIR__ . '/includes/functions.php';
$orders = db_query("SELECT o.*, c.first_name, c.last_name FROM orders o LEFT JOIN customers c ON o.customer_id = c.customer_id ORDER BY o.order_id DESC LIMIT 50");
header('Content-Type: text/plain');
echo "Total Rows in DB matching (LEFT JOIN customers): " . count($orders) . "\n\n";
foreach ($orders as $o) {
    echo "ID: #{$o['order_id']} | Status: {$o['status']} | Cust_ID: " . ($o['customer_id'] ?? 'NULL') . " | Name: {$o['first_name']} {$o['last_name']}\n";
}
$all_orders = db_query("SELECT COUNT(*) as total FROM orders");
echo "\nTotal rows in orders table: " . $all_orders[0]['total'] . "\n";
