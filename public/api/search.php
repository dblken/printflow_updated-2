<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

header('Content-Type: application/json');

if (!is_logged_in() || !is_customer()) {
    echo json_encode(['results' => []]);
    exit;
}

$q = trim($_GET['q'] ?? '');
if (strlen($q) < 1) {
    echo json_encode(['results' => []]);
    exit;
}

$like = '%' . $q . '%';
$results = [];

// Services
$services = db_query(
    "SELECT service_id, name, category FROM services WHERE status = 'Activated' AND name LIKE ? LIMIT 4",
    's', [$like]
) ?: [];
foreach ($services as $s) {
    $results[] = [
        'type'  => 'service',
        'label' => $s['name'],
        'sub'   => $s['category'],
        'url'   => '/printflow/customer/order_service_dynamic.php?service_id=' . $s['service_id'],
    ];
}

// Products
$products = db_query(
    "SELECT product_id, name, category FROM products WHERE status = 'Activated' AND name LIKE ? LIMIT 4",
    's', [$like]
) ?: [];
foreach ($products as $p) {
    $results[] = [
        'type'  => 'product',
        'label' => $p['name'],
        'sub'   => $p['category'],
        'url'   => '/printflow/customer/order_create.php?product_id=' . $p['product_id'],
    ];
}

// Orders
$customer_id = get_user_id();
$orders = db_query(
    "SELECT o.order_id, o.status, p.name as product_name
     FROM orders o
     JOIN order_items oi ON oi.order_id = o.order_id
     JOIN products p ON p.product_id = oi.product_id
     WHERE o.customer_id = ? AND (CAST(o.order_id AS CHAR) LIKE ? OR p.name LIKE ?)
     GROUP BY o.order_id
     ORDER BY o.order_date DESC LIMIT 4",
    'iss', [$customer_id, $like, $like]
) ?: [];
foreach ($orders as $o) {
    $results[] = [
        'type'  => 'order',
        'label' => 'Order #' . $o['order_id'] . ' — ' . $o['product_name'],
        'sub'   => $o['status'],
        'url'   => '/printflow/customer/orders.php',
    ];
}

echo json_encode(['results' => $results]);
