<?php
/**
 * Fix existing service orders to start at Step 1 (Pending status with ₱0)
 * Run this once to fix orders that were created before the status fix
 */

require_once __DIR__ . '/includes/db.php';

echo "<h2>Fixing Service Orders</h2>";

// Find all service orders (order_type = 'custom') that are in 'To Pay' status with a price set
$orders_to_fix = db_query("
    SELECT order_id, total_amount, status 
    FROM orders 
    WHERE order_type = 'custom' 
    AND status = 'To Pay' 
    AND total_amount > 0
    ORDER BY order_id DESC
");

if (empty($orders_to_fix)) {
    echo "<p>No orders need fixing.</p>";
    exit;
}

echo "<p>Found " . count($orders_to_fix) . " orders to fix:</p>";
echo "<ul>";

foreach ($orders_to_fix as $order) {
    $order_id = $order['order_id'];
    $old_total = $order['total_amount'];
    
    // Update order to Pending status with ₱0
    db_execute("
        UPDATE orders 
        SET status = 'Pending', 
            total_amount = 0,
            updated_at = NOW()
        WHERE order_id = ?
    ", 'i', [$order_id]);
    
    // Update order items to ₱0
    db_execute("
        UPDATE order_items 
        SET unit_price = 0
        WHERE order_id = ?
    ", 'i', [$order_id]);
    
    echo "<li>✅ Order #$order_id: Changed from 'To Pay' (₱" . number_format($old_total, 2) . ") → 'Pending' (₱0.00)</li>";
}

echo "</ul>";
echo "<p><strong>Done! All service orders have been reset to Step 1 (Pending).</strong></p>";
echo "<p><a href='staff/customizations.php?status=PENDING'>View Pending Orders</a></p>";
