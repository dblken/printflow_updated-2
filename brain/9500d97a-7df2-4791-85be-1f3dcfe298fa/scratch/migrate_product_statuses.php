<?php
require_once __DIR__ . '/../includes/db.php';

echo "Migrating Product Orders...\n";

// 1. Update main orders table
$sql1 = "UPDATE orders 
         SET status = 'Ready for Pickup' 
         WHERE order_type = 'product' 
         AND status IN ('Processing', 'In Production', 'Printing', 'Approved Design')";

$count1 = db_execute($sql1);
echo "Updated $count1 orders in 'orders' table.\n";

// 2. Update job_orders if any exist for products
$sql2 = "UPDATE job_orders jo
         JOIN orders o ON jo.order_id = o.order_id
         SET jo.status = 'READY_TO_COLLECT'
         WHERE o.order_type = 'product'
         AND jo.status IN ('IN_PRODUCTION', 'PENDING_PRODUCTION')";

$count2 = db_execute($sql2);
echo "Updated $count2 jobs in 'job_orders' table.\n";

echo "Done.\n";
