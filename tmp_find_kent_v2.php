<?php
require 'includes/functions.php';

$email = 'kentlloydvillanueva@gmail.com';

$res = db_query("SELECT customer_id, first_name, last_name, email FROM customers WHERE email = ?", "s", [$email]);

if (!empty($res)) {
    foreach ($res as $row) {
        echo "ID: " . $row['customer_id'] . " | Name: " . $row['first_name'] . " " . $row['last_name'] . " | Email: " . $row['email'] . "\n";
        
        // Check for orders
        $cid = $row['customer_id'];
        $orders = db_query("SELECT order_id FROM orders WHERE customer_id = ?", "i", [$cid]);
        echo "Orders FOUND: " . count($orders) . "\n";
        
        // Check for notifications
        $notifs = db_query("SELECT notification_id FROM notifications WHERE customer_id = ?", "i", [$cid]);
        echo "Notifications FOUND: " . count($notifs) . "\n";

        // Check for cart
        $cart = db_query("SELECT * FROM customer_cart WHERE customer_id = ?", "i", [$cid]);
        echo "Cart items FOUND: " . count($cart) . "\n";
    }
} else {
    echo "NO CUSTOMER FOUND with email $email.\n";
}
?>
