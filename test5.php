<?php
require 'includes/db.php';
require 'includes/functions.php';

$order_id = 2543;
$order_result = db_query("SELECT * FROM orders WHERE order_id = ?", 'i', [$order_id]);
$order = $order_result[0];

$customer_id = (int)($order['customer_id'] ?? 0);
$cust_result = db_query("SELECT c.profile_picture FROM customers c WHERE c.customer_id = ?", 'i', [$customer_id]);
print_r($cust_result);
print_r(get_profile_image($cust_result[0]['profile_picture']));
