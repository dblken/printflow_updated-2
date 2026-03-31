<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

// Mock session if needed (but we are running from CLI)
// Assuming user ID 1 for testing
$order_id = 2272; 
$customer_id = 1; // You might need a real customer ID

try {
    $order_result = db_query("
        SELECT o.*, b.name as branch_name 
        FROM orders o 
        LEFT JOIN branches b ON o.branch_id = b.branch_id 
        WHERE o.order_id = ? 
    ", 'i', [$order_id]);

    if (empty($order_result)) {
        die("Order $order_id not found or no access");
    }
    $order = $order_result[0];
    echo "Order found: " . $order['order_id'] . "\n";

    $items = db_query("
        SELECT oi.*, p.name as product_name, p.category
        FROM order_items oi
        LEFT JOIN products p ON oi.product_id = p.product_id
        WHERE oi.order_id = ?
    ", 'i', [$order_id]);
    echo "Items found: " . count($items) . "\n";

    if (in_array($order['status'], ['Completed', 'To Rate', 'Rated'], true)) {
        echo "Checking ratings...\n";
        $rating_res = db_query("SELECT * FROM ratings WHERE order_id = ?", 'i', [$order_id]);
        echo "Rating query done\n";
    }

    echo "Success!";
} catch (Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
