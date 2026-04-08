<?php
error_reporting(0);
ini_set('display_errors', 0);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

header('Content-Type: application/json');
ob_clean();

$q = trim($_GET['q'] ?? '');
if (strlen($q) < 1) {
    echo json_encode(['results' => []]);
    exit;
}

$like = '%' . $q . '%';
$results = [];

try {
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
} catch (Exception $e) {
    // Silent fail
}

echo json_encode(['results' => $results]);
