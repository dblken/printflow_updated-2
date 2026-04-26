<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

// Debug order 2516
$order = db_query("SELECT * FROM orders WHERE order_id = 2516");
$jobs = db_query("SELECT * FROM job_orders WHERE order_id = 2516 OR id = 2516");

header('Content-Type: text/plain');
echo "ORDER 2516:\n";
print_r($order);
echo "\nJOB ORDERS:\n";
print_r($jobs);
