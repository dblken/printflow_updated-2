<?php
require 'includes/functions.php';

$email = 'kentlloydvillanueva@gmail.com';

echo "Searching for $email...\n";

$customer = db_query("SELECT * FROM customers WHERE email = ?", "s", [$email]);
if (!empty($customer)) {
    echo "Found in customers table:\n";
    print_r($customer[0]);
    $cid = $customer[0]['customer_id'];
    
    // Check for orders
    $orders = db_query("SELECT order_id FROM orders WHERE customer_id = ?", "i", [$cid]);
    echo "Orders count: " . count($orders) . "\n";
    
    // Check for notifications
    $notifs = db_query("SELECT notification_id FROM notifications WHERE customer_id = ?", "i", [$cid]);
    echo "Notifications count: " . count($notifs) . "\n";
} else {
    echo "Not found in customers table.\n";
}

$user = db_query("SELECT * FROM users WHERE email = ?", "s", [$email]);
if (!empty($user)) {
    echo "Found in users table:\n";
    print_r($user[0]);
} else {
    echo "Not found in users table.\n";
}
?>
